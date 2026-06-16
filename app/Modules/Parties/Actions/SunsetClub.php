<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Events\ClubSunset;
use App\Modules\Parties\Exceptions\IllegalClubTransition;
use App\Modules\Parties\Models\Club;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Club `active → sunset` and records its {@see ClubSunset} event atomically
 * (parties-producer-lifecycle, design L1/L2/L4/L6; party-registry — Requirements: Club Lifecycle,
 * Supply-Side Lifecycle Events).
 *
 * This action is the SOLE writer of `Club.status` for the sunset transition and the SINGLE writer of the
 * {@see ClubSunset} event (design L6), invoked on both paths:
 *   - a standalone operator action — the caller passes no causation, so the event is a root (its
 *     `correlation_id` defaults to its own `event_id` in the recorder);
 *   - the per-Club step of the Producer-retirement cascade — the `RetireProducer` action (task 3.2) passes
 *     the `ProducerRetired` event's `id` as `$causationId` and its `correlation_id` as `$correlationId`, so
 *     each cascade `ClubSunset` is causally linked to (and shares the correlation of) the retirement. The two
 *     threading parameters exist for exactly that cascade and are the only difference between the two paths.
 *
 * From-state guarded and race-safe (design L2): inside ONE {@see DB::transaction} it re-reads the row
 * `->lockForUpdate()` (a real row lock on PostgreSQL, a harmless no-op under SQLite's single writer — the
 * from-state assert carries correctness either way), asserts `status === active`, then writes `sunset` and
 * records the event. A call on a Club not in `active` throws {@see IllegalClubTransition::cannotSunset()} and
 * the transaction rolls back, leaving the row and the event log unchanged. The status write and the event are
 * recorded in the same transaction (the recorder's open-transaction guard makes write + emit atomic), and the
 * payload reflects the POST-transition state. `version` is NOT bumped — it is reserved for identity-attribute
 * revisions (its parties-core meaning), and the immutable domain event is the audit record of the transition
 * (design L3). The Model stays persistence-only; this action is the only state writer (design L1). The actor
 * is resolved from the {@see ActorContext} seam (System until real principals wire in — design L9).
 */
class SunsetClub
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $clubId, ?int $causationId = null, ?string $correlationId = null): Club
    {
        return DB::transaction(function () use ($clubId, $causationId, $correlationId): Club {
            // Transaction-locked re-read so two concurrent sunset attempts serialize on PostgreSQL; the
            // from-state assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $club = Club::query()->whereKey($clubId)->lockForUpdate()->firstOrFail();

            if ($club->status !== ClubStatus::Active) {
                throw IllegalClubTransition::cannotSunset($club->status);
            }

            $club->update(['status' => ClubStatus::Sunset]);

            $this->recorder->record(
                name: ClubSunset::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ClubSunset::ENTITY_TYPE,
                entityId: (string) $club->id,
                payload: ClubSunset::payload($club),
                correlationId: $correlationId,
                causationId: $causationId,
            );

            return $club;
        });
    }
}
