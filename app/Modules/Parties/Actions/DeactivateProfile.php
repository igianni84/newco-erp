<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileInactive;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Profile membership `Active → Inactive` and records its {@see ProfileInactive} event atomically
 * (parties-membership-suspension, design L3/L4/L9/L10/L11; party-registry — Requirements: Profile Cancellation and
 * Deactivation, Demand-Side Status Events).
 *
 * This Action is the SOLE writer of `Profile.state` for the deactivation transition and the writer of a ROOT
 * {@see ProfileInactive} event. `Active → Inactive` is the operational corner case off `active` (§ 4.2.1) — distinct
 * from {@see CancelProfile} (`active | lapsed → cancelled`), which is AUDIT-ONLY (the § 15.2 family names no
 * `ProfileCancelled`, design L2): deactivation DOES record its verbatim § 15.2 event.
 *
 * TERMINAL SOFT-DELETE (§ 4.2.1 / AC-K-FSM-13 / AC-K-BR-Profile-2): `Inactive` is a terminal state; the Profile is
 * NEVER hard-deleted at launch, preserving audit history. Because the partial-unique index on `parties_profiles`
 * already excludes `{rejected, cancelled, inactive}`, an `Inactive` Profile does NOT block a fresh `Applied` Profile
 * for the same Customer–Club pair (no index migration; making the terminal state reachable merely exercises the
 * predicate).
 *
 * STATE-PRESERVING (design L9): deactivation writes ONLY `Profile.state` — it does NOT cancel vouchers, pending
 * orders or allocation reservations, nor mutate any Club Credit balance. Vouchers/orders/reservations live in Module
 * S/B/E and are unbuilt; the Club Credit entity is built (Module K, `club-credit`), but `Active → Inactive` mutates
 * no credit — a credit's own state is written only by its dedicated within-module writers. Nothing destructive
 * happens — only this one row's `state` changes.
 *
 * From-state guarded and race-safe (design L4, mirroring {@see SuspendProfile}): inside ONE {@see DB::transaction}
 * it re-reads the Profile `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `state === active`, then writes `inactive` and records the event.
 * A call on a Profile not in `active` throws {@see IllegalProfileTransition::cannotDeactivate()} BEFORE any write, and
 * the transaction rolls back leaving the Profile and the event log unchanged. The payload reflects the POST-transition
 * `state`. `version` is NOT bumped (parties-core identity-revision semantics; the immutable domain event is the audit
 * record of the transition). The Model stays persistence-only; this Action is the sole state writer. `ProfileInactive`
 * is a ROOT event — a directly-invoked deactivation records no parent in its transaction, so no causation/correlation
 * is threaded. The actor is resolved from the {@see ActorContext} seam (System until real principals wire in).
 */
class DeactivateProfile
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

            // Deactivation is reachable only from `active` (the operational corner case — § 4.2.1); every other
            // state rejects.
            if ($profile->state !== ProfileState::Active) {
                throw IllegalProfileTransition::cannotDeactivate($profile->state);
            }

            // State-preserving (design L9): write ONLY `state` — no voucher/order/reservation/Club Credit is touched.
            $profile->update(['state' => ProfileState::Inactive]);

            // No causation/correlation passed → the recorder makes this a root event (its `correlation_id` defaults
            // to its own `event_id`): a directly-invoked deactivation records no parent event. The event class is the
            // single source of truth for the name / entity type / PII-free payload.
            $this->recorder->record(
                name: ProfileInactive::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProfileInactive::ENTITY_TYPE,
                entityId: (string) $profile->id,
                payload: ProfileInactive::payload($profile),
            );

            return $profile;
        });
    }
}
