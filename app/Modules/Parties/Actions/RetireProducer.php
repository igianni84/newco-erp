<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProducerRetired;
use App\Modules\Parties\Exceptions\IllegalProducerTransition;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\Profile;
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
 * retirement + every cascade sunset commit or roll back together — all-or-nothing (design L6). The cascade THEN
 * performs the PROFILE leg of the § 10.2 offboarding (parties-module-k-br-guards RM-19, design D1): after every
 * operated Club is sunset it queries Profiles by the sunsetting Clubs' ids (there is NO `Club → Profile`
 * relation — the walk is `whereIn('club_id', …)`) and drives {@see CancelProfile} — the `Active | Lapsed →
 * Cancelled` transition — with a Producer-initiated `cancellation_reason` (`OFFBOARDING_CANCELLATION_REASON`) for
 * every Profile still `Active`/`Lapsed` under a sunsetting Club, in this SAME transaction and AFTER the
 * corresponding `ClubSunset` (parent-before-child, AC-K-EVT-20). Profiles in other states
 * (`Applied`/`Suspended`/already-terminal) are out of this leg — the from-state filter also keeps CancelProfile's
 * own guard from tripping. Faithful to zero-invention (design D1): frozen § 15.2 names NO `ProfileCancelled`, so
 * the per-Profile cancellation is AUDIT-ONLY (no domain event); the subscribable Module-S signal event and the
 * Club-Credit conversion math (§ 15.7 / DEC-043 / AC-K-XM-23) stay the deferred Module-S seam — Module K's role
 * ends at the per-Profile cancellation with its reason.
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
    /**
     * The Producer-initiated `cancellation_reason` stamped on every Profile the § 10.2 offboarding cascade
     * cancels — a plain domain token a future Module-S Club-Credit-conversion consumer reads, NOT display copy
     * (the `cancellation_reason` column is uncast free text; this is the `ProfileCancellationTest`
     * producer-offboarding token). It distinguishes an offboarding-driven cancellation from a voluntary/admin one
     * at the audit boundary.
     */
    public const OFFBOARDING_CANCELLATION_REASON = 'producer_offboarding';

    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
        private readonly SunsetClub $sunsetClub,
        private readonly CancelProfile $cancelProfile,
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

            // § 10.2 offboarding cascade (Profile leg — parent-before-child, AC-K-EVT-20): AFTER every operated
            // Club is sunset, cancel every Profile still `Active`/`Lapsed` under one of those sunsetting Clubs,
            // stamping the Producer-initiated reason. There is NO `Club → Profile` relation, so the walk queries
            // Profiles by the sunsetting Clubs' ids (the just-sunset `$activeClubs` — a since-`closed` Club is not
            // sunsetting now, so its Profiles are out of scope). Filtered to the two cancellable from-states so
            // CancelProfile's own from-state guard never trips — an `Applied`/`Suspended`/already-terminal Profile
            // is out of this leg and left to its own lifecycle. AUDIT-ONLY (design D1): CancelProfile records NO
            // domain event (§ 15.2 names no `ProfileCancelled`), so the cascade adds no event; the subscribable
            // Module-S signal event + the Club-Credit conversion math stay the deferred Module-S seam (§ 15.7 /
            // DEC-043). Runs inside this SAME transaction → retirement + sunsets + cancellations are all-or-nothing.
            $sunsetProfiles = Profile::query()
                ->whereIn('club_id', $activeClubs->pluck('id')->all())
                ->whereIn('state', [ProfileState::Active->value, ProfileState::Lapsed->value])
                ->get();

            foreach ($sunsetProfiles as $profile) {
                $this->cancelProfile->handle($profile->id, self::OFFBOARDING_CANCELLATION_REASON);
            }

            return $producer;
        });
    }
}
