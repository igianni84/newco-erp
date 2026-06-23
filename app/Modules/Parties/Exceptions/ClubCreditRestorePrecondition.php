<?php

namespace App\Modules\Parties\Exceptions;

use RuntimeException;

/**
 * Raised when {@see RestoreClubCredit} is invoked on a legitimately-`redeemed` credit but the one-active-per-Profile
 * invariant would be breached by reactivating it (change club-credit, design L1/L7; party-registry — Requirement:
 * Club Credit Forfeiture and Restoration; Module K PRD § 11). Unlike the FSM from-state guard
 * ({@see IllegalClubCreditTransition::cannotRestore}), this rejects a credit that IS in the right state
 * (`redeemed`) — it is the one value/context precondition on the owning Profile (mirroring how redemption keeps its
 * non-from-state gates in {@see ClubCreditRedemptionPrecondition} and issuance in
 * {@see ClubCreditIssuancePrecondition}), rejected BEFORE any write so the credit's `state` and `remaining` are left
 * unchanged:
 *   - {@see profileHasActiveCredit} — the owning Profile already holds another `active` Club Credit (e.g. a renewal
 *     replacement was issued after this credit was redeemed). Restoring this one would breach the partial unique
 *     index `(profile_id) WHERE state = 'active'` (design L1/L7), so restoration is REFUSED with a clean operator
 *     reason rather than left to abort on the index violation. At launch an order cancellation precedes re-issuance
 *     in practice, so the conflict is rare — but the guard makes the rejection explicit and localized.
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded user-facing strings):
 * the English baseline lives in the `club_credit` group of `lang/en/parties.php` (key `restore_active_conflict`,
 * added in the change's i18n task 5.2). The interpolated token is the Club Credit id (`:credit`) — an operator-facing
 * identity reference, NOT PII (like the sibling guards); the credit's balance is deliberately kept OUT of the message
 * (a balance is customer financial data). `(string)` coerces the translator return (typed `mixed` by Larastan) to the
 * RuntimeException message contract.
 */
class ClubCreditRestorePrecondition extends RuntimeException
{
    public static function profileHasActiveCredit(int $creditId): self
    {
        return new self((string) __('parties.club_credit.restore_active_conflict', [
            'credit' => $creditId,
        ]));
    }
}
