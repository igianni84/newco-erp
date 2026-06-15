<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Events\ProducerRetired;
use App\Modules\Parties\Exceptions\IllegalProducerTransition;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Producer `active → retired`, records its {@see ProducerRetired} event, and CASCADES the
 * offboarding sunset onto every operated Club still `active` — all atomically in one transaction
 * (parties-producer-lifecycle, design L1/L2/L4/L5/L6; party-registry — Requirements: Producer Lifecycle,
 * Supply-Side Lifecycle Events).
 *
 * This action is the SOLE writer of `Producer.status` for the retirement transition and the SINGLE writer of the
 * {@see ProducerRetired} event. Retirement is the terminal step of the Producer FSM `draft → active → retired`:
 * it is reachable only from `active`. It is a standalone operator action — never a cascade target in this slice —
 * so a `ProducerRetired` is always a ROOT event and the action takes the simpler standalone signature
 * `handle(int $producerId)` (no causation/correlation parameters): it GENERATES the linkage for its cascade from
 * the recorded root rather than receiving it (the signature rule it shares with `ActivateProducer`/`CloseClub`).
 *
 * CASCADE (design L5/L6 — the § 10.2 producer-offboarding cascade, Producer → Club leg only): after recording
 * `ProducerRetired` it walks {@see Producer::clubs()} (the within-module `hasMany`) and, for each Club currently
 * `active`, invokes {@see SunsetClub} — the SINGLE `ClubSunset` writer — threading the `ProducerRetired` event's
 * `id` as `causationId` and its `correlation_id` as `correlationId`, so every cascade `ClubSunset` is causally
 * linked to (and shares the correlation of) the retirement (one queryable offboarding thread in the 10-year
 * audit log). Clubs already in `sunset` or `closed` are filtered out — the cascade is idempotent over
 * already-transitioned Clubs (belt-and-braced by SunsetClub's own from-state guard). The cascade runs INSIDE
 * this action's transaction (SunsetClub's nested {@see DB::transaction} is a savepoint), so the whole
 * retirement + every cascade sunset commit or roll back together — all-or-nothing (design L6). The PROFILE leg
 * of the § 10.2 cascade (per-Profile cancellation, the Module-S Club-Credit conversion signal) is NOT performed
 * here — Profile transitions are demand-side and deferred (design L6).
 *
 * From-state guarded and race-safe (design L2): inside ONE {@see DB::transaction} it re-reads the row
 * `->lockForUpdate()` (a real row lock on PostgreSQL, a harmless no-op under SQLite's single writer — the
 * from-state assert carries correctness either way), asserts `status === active`, then writes `retired` and
 * records the event. A call on a Producer not in `active` throws {@see IllegalProducerTransition::cannotRetire()}
 * and the transaction rolls back, leaving every row and the event log unchanged (no `ProducerRetired`, no cascade
 * `ClubSunset`). The status write and the event are recorded in the same transaction, and the payload reflects
 * the POST-transition state. `version` is NOT bumped — it is reserved for identity-attribute revisions (its
 * parties-core meaning), and the immutable domain event is the audit record of the transition (design L3). The
 * Model stays persistence-only; this action is the only state writer (design L1). The actor is resolved from the
 * {@see ActorContext} seam (System until real principals wire in — design L9).
 */
class RetireProducer
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
        private readonly SunsetClub $sunsetClub,
    ) {}

    public function handle(int $producerId): Producer
    {
        return DB::transaction(function () use ($producerId): Producer {
            // Transaction-locked re-read so two concurrent retirement attempts serialize on PostgreSQL; the
            // from-state assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $producer = Producer::query()->whereKey($producerId)->lockForUpdate()->firstOrFail();

            if ($producer->status !== ProducerStatus::Active) {
                throw IllegalProducerTransition::cannotRetire($producer->status);
            }

            $producer->update(['status' => ProducerStatus::Retired]);

            // The retirement is a ROOT event (no causation/correlation passed → the recorder defaults its
            // `correlation_id` to its own `event_id`); its `id` + `correlation_id` thread the cascade below.
            $retired = $this->recorder->record(
                name: ProducerRetired::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProducerRetired::ENTITY_TYPE,
                entityId: (string) $producer->id,
                payload: ProducerRetired::payload($producer),
            );

            // § 10.2 offboarding cascade (Producer → Club leg): sunset every operated Club still `active`,
            // linking each cascade ClubSunset to the retirement root. Clubs already sunset/closed are filtered
            // out (idempotent); SunsetClub re-locks and re-asserts each Club's from-state inside this same
            // transaction, so the whole cascade is all-or-nothing.
            $activeClubs = $producer->clubs()->where('status', ClubStatus::Active->value)->get();

            foreach ($activeClubs as $club) {
                $this->sunsetClub->handle(
                    $club->id,
                    causationId: $retired->id,
                    correlationId: $retired->correlation_id,
                );
            }

            return $producer;
        });
    }
}
