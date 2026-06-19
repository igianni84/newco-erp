<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Profile membership `Active | Lapsed → Cancelled` and records the optional Producer-initiated
 * `cancellation_reason` — AUDIT-ONLY, recording NO domain event (parties-membership-suspension, design L2/L4/L9/L10;
 * party-registry — Requirements: Profile Cancellation and Deactivation, Demand-Side Status Events).
 *
 * AUDIT-ONLY (design L2; § 15.2 / § 15.7): the § 15.2 Profile event family names **no `ProfileCancelled`**, so —
 * exactly as {@see ApproveProfile} / {@see DeclineProfile} write `state` and record no Profile event (the audit trail
 * is the record) — `CancelProfile` writes `state = cancelled` (+ the reason) and records NO domain event. The
 * `state = cancelled` write captured in the append-only audit log IS the record; inventing a `ProfileCancelled` event
 * is forbidden (zero-invention — it would coin a name the catalog leaves open). The per-Profile cancellation SIGNAL
 * Module S consumes for Club-Credit conversion at Producer offboarding (AC-K-EVT-14 / § 10.2 / DEC-043) is a DEFERRED
 * Module-S seam: this change ships the within-module `→ Cancelled` transition + the cancellation reason, NOT the
 * offboarding orchestration (Module S is unbuilt and the event shape is its concern — § 15.7 defers it).
 *
 * THE CANCELLATION REASON (design L1; § 10.2): the optional `$reason` is the Producer-initiated `cancellation_reason`
 * — a plain nullable string column (no cast), domain data a future Module-S consumer reads. A direct/voluntary
 * cancellation passes none.
 *
 * TERMINAL SOFT-DELETE (§ 4.2.1 / AC-K-FSM-13 / AC-K-BR-Profile-2): `Cancelled` is a terminal state; the Profile is
 * NEVER hard-deleted at launch, preserving audit history. Re-entry requires a fresh application — and because the
 * partial-unique index on `parties_profiles` already excludes `{rejected, cancelled, inactive}`, a `Cancelled`
 * Profile does NOT block a fresh `Applied` Profile for the same Customer–Club pair (no index migration; making the
 * terminal state reachable merely exercises the predicate). A `suspended`/`lapsed` (non-terminal) Profile still
 * blocks a second live Profile.
 *
 * From-state guarded and race-safe (design L4, mirroring {@see SuspendProfile}): inside ONE {@see DB::transaction} it
 * re-reads the Profile `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `state ∈ {active, lapsed}` (the two cancellable from-states —
 * `Active → Cancelled` voluntary/admin/offboarding/death, `Lapsed → Cancelled` after the 30-day grace, § 4.2.1), then
 * writes `cancelled` + the reason. A call on a Profile not in `active`/`lapsed` throws
 * {@see IllegalProfileTransition::cannotCancel()} BEFORE any write, and the transaction rolls back leaving the Profile
 * and the event log unchanged. State-preserving (design L9): the cancellation writes ONLY `state` + `cancellation_reason`
 * — it touches no voucher/order/reservation/Club Credit (those entities live in Module S/B/E and are unbuilt).
 * `version` is NOT bumped (parties-core identity-revision semantics). The Model stays persistence-only; this Action is
 * the sole state writer. The actor is resolved from the {@see ActorContext} seam at the operator/offboarding caller —
 * but, being audit-only, this Action records no envelope of its own.
 */
class CancelProfile
{
    public function handle(int $profileId, ?string $reason = null): Profile
    {
        return DB::transaction(function () use ($profileId, $reason): Profile {
            // Transaction-locked re-read so two concurrent attempts serialize on PostgreSQL; the from-state assert
            // below is the correctness guarantee (the lock is a no-op on SQLite).
            $profile = Profile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();

            // Cancellation is reachable from `active` (voluntary/admin/Producer-offboarding/death) or `lapsed` (after
            // the 30-day grace, § 4.2.1); every other state rejects.
            if (! in_array($profile->state, [ProfileState::Active, ProfileState::Lapsed], true)) {
                throw IllegalProfileTransition::cannotCancel($profile->state);
            }

            // State-preserving (design L9): write ONLY `state` + the optional Producer-initiated reason. AUDIT-ONLY
            // (design L2): NO domain event is recorded — the § 15.2 family names no `ProfileCancelled`; the
            // append-only audit trail of this write IS the record.
            $profile->update([
                'state' => ProfileState::Cancelled,
                'cancellation_reason' => $reason,
            ]);

            return $profile;
        });
    }
}
