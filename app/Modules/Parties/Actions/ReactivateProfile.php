<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileReactivated;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Profile membership `Suspended → Active` and records its {@see ProfileReactivated} event atomically
 * (parties-membership-suspension, design L3/L4/L10/L11; party-registry — Requirements: Profile Suspension and
 * Restoration, Demand-Side Status Events).
 *
 * This Action is the SOLE writer of `Profile.state` for the restore transition and the writer of a ROOT
 * {@see ProfileReactivated} event when directly invoked. It is the inverse of {@see SuspendProfile}.
 *
 * NAMING TRAP (design L3): `ProfileReactivated` is recorded ONLY on this `suspended → active` restore. The
 * `lapsed → active` grace edge records `ProfileRenewed` (via the deferred `RenewProfile`), NOT this event — never
 * conflate the two. Reactivation is therefore reachable only from `suspended` (§ 4.2.1); every other state rejects.
 *
 * HOLD COUPLING — DRIVEN IN PRODUCTION (design L6; ADR 2026-06-19; § 10.1): in production this transition is driven
 * by the Hold→`suspended` coupling on the lift of the LAST covering Hold — `LiftHold` (operator) and the system
 * `kyc`-lift in `RecordKycVerified` restore a covered `suspended` scope iff no other active Hold still covers it
 * (coverage-recompute), wired in the coupling tasks (4.x). A cascading `ReactivateCustomer` (task 3.1) also records a
 * `ProfileReactivated` per restored Profile as a causation child of `CustomerReactivated` (design L11). The Action is
 * also directly operator-invocable.
 *
 * From-state guarded and race-safe (design L4, mirroring {@see ActivateProfile}): inside ONE {@see DB::transaction}
 * it re-reads the Profile `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `state === suspended`, then writes `active` and records the event.
 * A call on a Profile not in `suspended` throws {@see IllegalProfileTransition::cannotReactivate()} BEFORE any write,
 * and the transaction rolls back leaving the Profile and the event log unchanged. The payload reflects the
 * POST-transition `state`. `version` is NOT bumped (parties-core identity-revision semantics; the immutable domain
 * event is the audit record of the transition). The Model stays persistence-only; this Action is the sole state
 * writer. `ProfileReactivated` is a ROOT event here — a directly-invoked restore records no parent in its
 * transaction, so no causation/correlation is threaded (a cascading `ReactivateCustomer` records its children with
 * causation/correlation instead — design L11). The actor is resolved from the {@see ActorContext} seam (System until
 * real principals wire in).
 */
class ReactivateProfile
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $profileId): Profile
    {
        return DB::transaction(function () use ($profileId): Profile {
            // Transaction-locked re-read so two concurrent attempts serialize on PostgreSQL; the from-state assert
            // below is the correctness guarantee (the lock is a no-op on SQLite).
            $profile = Profile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();

            // Restore is reachable only from `suspended` (§ 4.2.1) — and ONLY the suspend-restore edge; the
            // `lapsed → active` grace is RenewProfile's `ProfileRenewed`, not this event (design L3).
            if ($profile->state !== ProfileState::Suspended) {
                throw IllegalProfileTransition::cannotReactivate($profile->state);
            }

            // State-preserving inverse of suspension (design L9): write ONLY `state`.
            $profile->update(['state' => ProfileState::Active]);

            // No causation/correlation passed → the recorder makes this a root event (its `correlation_id` defaults
            // to its own `event_id`): a directly-invoked restore records no parent event. The event class is the
            // single source of truth for the name / entity type / PII-free payload.
            $this->recorder->record(
                name: ProfileReactivated::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProfileReactivated::ENTITY_TYPE,
                entityId: (string) $profile->id,
                payload: ProfileReactivated::payload($profile),
            );

            return $profile;
        });
    }
}
