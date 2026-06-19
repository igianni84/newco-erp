<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileSuspended;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Profile membership `Active → Suspended` and records its {@see ProfileSuspended} event atomically
 * (parties-membership-suspension, design L4/L9/L10/L11; party-registry — Requirements: Profile Suspension and
 * Restoration, Demand-Side Status Events).
 *
 * This Action is the SOLE writer of `Profile.state` for the suspension transition and the writer of a ROOT
 * {@see ProfileSuspended} event when directly invoked. Suspension is reachable only from `active` (the post-activation
 * status edge off `active` — § 4.2.1); the inverse restore is {@see ReactivateProfile} (`suspended → active`,
 * recording {@see ProfileReactivated} — the § 15.2 event for THAT edge only; it is never reused for the
 * `lapsed → active` grace, which records `ProfileRenewed` via the deferred `RenewProfile` — design L3).
 *
 * STATE-PRESERVING (design L9; § 10.1 / AC-K-FSM-2a): suspension writes ONLY `Profile.state`. It does NOT cancel
 * vouchers, pending orders or allocation reservations, nor mutate any Club Credit balance — active vouchers stay
 * ACTIVE, pending orders stay pending, reservations stay reserved. Those entities live in Module S/B/E and are
 * unbuilt, so the "Club Credit frozen while suspended" guarantee is a deferred `club-credit` seam (the credit entity
 * will read `state` when it exists): there is nothing to freeze here, and nothing destructive happens — only this
 * one row's `state` changes.
 *
 * HOLD COUPLING — DRIVEN IN PRODUCTION (design L6; ADR 2026-06-19; § 10.1): in production this transition is driven
 * by the Hold→`suspended` coupling — a Profile-scope Hold, or a cascading Customer-scope Hold (via the deferred
 * `SuspendCustomer`) — which `PlaceHold` wires in the coupling tasks (4.x). The Action is also directly
 * operator-invocable (manual suspension — AC-K-BR-Customer-1 "explicit (manual or via Hold)").
 *
 * From-state guarded and race-safe (design L4, mirroring {@see ActivateProfile}): inside ONE {@see DB::transaction}
 * it re-reads the Profile `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `state === active`, then writes `suspended` and records the event.
 * A call on a Profile not in `active` throws {@see IllegalProfileTransition::cannotSuspend()} BEFORE any write, and
 * the transaction rolls back leaving the Profile and the event log unchanged. The payload reflects the POST-transition
 * `state`. `version` is NOT bumped (parties-core identity-revision semantics; the immutable domain event is the audit
 * record of the transition). The Model stays persistence-only; this Action is the sole state writer.
 * `ProfileSuspended` is a ROOT event here — a directly-invoked suspension records no parent in its transaction, so no
 * causation/correlation is threaded (a cascading `SuspendCustomer` records its `ProfileSuspended` children with
 * causation/correlation instead — design L11). The actor is resolved from the {@see ActorContext} seam (System until
 * real principals wire in).
 */
class SuspendProfile
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

            // Suspension is reachable only from `active` (§ 4.2.1); every other state rejects.
            if ($profile->state !== ProfileState::Active) {
                throw IllegalProfileTransition::cannotSuspend($profile->state);
            }

            // State-preserving (design L9): write ONLY `state` — no voucher/order/reservation/Club Credit is touched.
            $profile->update(['state' => ProfileState::Suspended]);

            // No causation/correlation passed → the recorder makes this a root event (its `correlation_id` defaults
            // to its own `event_id`): a directly-invoked suspension records no parent event. The event class is the
            // single source of truth for the name / entity type / PII-free payload.
            $this->recorder->record(
                name: ProfileSuspended::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProfileSuspended::ENTITY_TYPE,
                entityId: (string) $profile->id,
                payload: ProfileSuspended::payload($profile),
            );

            return $profile;
        });
    }
}
