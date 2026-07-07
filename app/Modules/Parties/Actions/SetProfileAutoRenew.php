<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * Sets a Profile's `auto_renew` preference after creation — the operator-override half of Profile-5 (canon
 * MVP-DEC-022, CML-89 sub-7; parties-module-k-br-guards, design D8; party-registry — Requirement: Profile
 * Auto-Renewal Preference). It is the SOLE post-creation writer of `auto_renew`: {@see CreateProfile} sets the
 * value ONCE at creation by inheriting the owning Club's `auto_renew_default`, and thereafter only this Action
 * changes it. The CUSTOMER SELF-TOGGLE (the BMD § 2.4 / B2 self-serve auto-renewal) is a DEFERRED Consumer-Portal
 * frontend seam — the Consumer Portal does not exist at launch — so no customer-facing writer ships here; this
 * operator Action is the only mutation surface for the preference.
 *
 * AUDIT-ONLY (design D8; § 15.2): the § 15.2 Profile event family names NO `auto_renew` event, so — exactly as
 * {@see CancelProfile} / {@see ApproveProfile} write their column and record no Profile event (the append-only
 * audit trail of the write IS the record) — this Action writes `auto_renew` and records NO domain event.
 * Fabricating one is forbidden (zero-invention — it would coin a name the catalog leaves open). `auto_renew` is a
 * last-writer-wins renewal PREFERENCE, not an FSM state, so this Action applies NO from-state guard: an operator
 * MAY set it on a Profile in ANY state (§ Profile-5 mandates only "an operator MAY set … after creation", with no
 * state restriction). Setting the same value again is a harmless idempotent write.
 *
 * Race-safe (the {@see CancelProfile} precedent): inside ONE {@see DB::transaction} it re-reads the Profile
 * `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite) so two concurrent sets serialize, then
 * writes the new `auto_renew`. `version` is NOT bumped (parties-core identity-revision semantics). The Model stays
 * persistence-only; this Action is the sole post-creation writer. The actor is resolved from the ActorContext seam
 * at the operator caller — but, being audit-only, this Action records no event envelope of its own.
 */
class SetProfileAutoRenew
{
    public function handle(int $profileId, bool $autoRenew): Profile
    {
        return DB::transaction(function () use ($profileId, $autoRenew): Profile {
            // Transaction-locked re-read so two concurrent sets serialize on PostgreSQL (a no-op lock under SQLite).
            $profile = Profile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();

            // AUDIT-ONLY (design D8): write ONLY the `auto_renew` preference and record NO domain event — the § 15.2
            // Profile family names none for `auto_renew`; the append-only audit trail of this write IS the record.
            $profile->update(['auto_renew' => $autoRenew]);

            return $profile;
        });
    }
}
