<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Events\ClubClosed;
use App\Modules\Parties\Exceptions\IllegalClubTransition;
use App\Modules\Parties\Models\Club;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Club `sunset → closed` and records its {@see ClubClosed} event atomically
 * (parties-producer-lifecycle, design L1/L2/L4/L8; party-registry — Requirement: Club Lifecycle).
 *
 * This action is the SOLE writer of `Club.status` for the closure transition and the SINGLE writer of the
 * {@see ClubClosed} event. Closure is the terminal step of the Club FSM `active → sunset → closed`: it is
 * reachable only from `sunset`, so an `active` Club cannot be closed directly — it must first pass through
 * {@see SunsetClub}. Unlike sunset, closure is never a cascade target in this slice (the Producer-retirement
 * cascade sunsets the operated Clubs, it does not close them), so `CloseClub` is a pure standalone operator
 * action and its `ClubClosed` is always a root event — hence the simpler signature, with no
 * causation/correlation threading parameters.
 *
 * DEFERRED SEAM (design L8): the PRD precondition that a Club closes only once all members have migrated or
 * expired (Module K PRD § 4.3) reads Profile state, which does not exist in this supply-side slice. The gate
 * is therefore NOT enforced here — it is vacuously satisfiable today (no Profile can be `Active` without the
 * demand-side transitions) and the demand-side change SHALL tighten `CloseClub` to enforce it. This is the
 * same "seam now, behaviour later" discipline the spine used for `originating_club_id`.
 *
 * From-state guarded and race-safe (design L2): inside ONE {@see DB::transaction} it re-reads the row
 * `->lockForUpdate()` (a real row lock on PostgreSQL, a harmless no-op under SQLite's single writer — the
 * from-state assert carries correctness either way), asserts `status === sunset`, then writes `closed` and
 * records the event. A call on a Club not in `sunset` throws {@see IllegalClubTransition::cannotClose()} and
 * the transaction rolls back, leaving the row and the event log unchanged. The status write and the event are
 * recorded in the same transaction (the recorder's open-transaction guard makes write + emit atomic), and the
 * payload reflects the POST-transition state. `version` is NOT bumped — it is reserved for identity-attribute
 * revisions (its parties-core meaning), and the immutable domain event is the audit record of the transition
 * (design L3). The Model stays persistence-only; this action is the only state writer (design L1). The actor
 * is resolved from the {@see ActorContext} seam (System until real principals wire in — design L9).
 */
class CloseClub
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $clubId): Club
    {
        return DB::transaction(function () use ($clubId): Club {
            // Transaction-locked re-read so two concurrent closure attempts serialize on PostgreSQL; the
            // from-state assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $club = Club::query()->whereKey($clubId)->lockForUpdate()->firstOrFail();

            if ($club->status !== ClubStatus::Sunset) {
                throw IllegalClubTransition::cannotClose($club->status);
            }

            $club->update(['status' => ClubStatus::Closed]);

            // No causation/correlation passed → the recorder makes this a root event (its `correlation_id`
            // defaults to its own `event_id`): closure is never part of a cascade in this slice.
            $this->recorder->record(
                name: ClubClosed::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ClubClosed::ENTITY_TYPE,
                entityId: (string) $club->id,
                payload: ClubClosed::payload($club),
            );

            return $club;
        });
    }
}
