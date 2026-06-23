<?php

namespace App\Modules\Parties\Exceptions;

use RuntimeException;

/**
 * Raised when `IssueClubCredit` is invoked for a Profile whose Club cannot define a credit amount
 * (change club-credit, design L2; party-registry — Requirement: Club Credit Issuance). Unlike a
 * from-state transition guard ({@see IllegalProfileTransition} et al.), issuance has no prior credit to
 * transition — these are the two issuance PRECONDITIONS on the owning Club (§ 11.1), each rejected with
 * a clean operator reason and creating no row:
 *   - {@see clubDoesNotGenerateCredit} — the Club has `generates_credit = false` (no credit policy):
 *     issuance is gated on `generates_credit = true` (AC-K-J-16), so a non-credit Club issues nothing.
 *   - {@see clubHasNoFee} — the Club has `generates_credit = true` but a null `fee`: at launch the credit
 *     `amount` IS the fee verbatim (full-fee → full-credit; K.18 scaling deferred — design L2), so a fee-less
 *     Club cannot define an amount and issuance is refused rather than minting a zero/undefined credit.
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded user-facing
 * strings): the English baseline lives in the `club_credit` group of `lang/en/parties.php` (keys
 * `issuance_no_credit_policy` / `issuance_no_fee`, added in the change's i18n task), with a `:club`
 * placeholder. The Club id is an operator-facing identity reference, NOT PII (like the producer-id sibling
 * guards) — so it is interpolated to make the reason self-documenting. `(string)` coerces the translator
 * return (typed `mixed` by Larastan) to the RuntimeException message contract.
 */
class ClubCreditIssuancePrecondition extends RuntimeException
{
    public static function clubDoesNotGenerateCredit(int $clubId): self
    {
        return new self((string) __('parties.club_credit.issuance_no_credit_policy', [
            'club' => $clubId,
        ]));
    }

    public static function clubHasNoFee(int $clubId): self
    {
        return new self((string) __('parties.club_credit.issuance_no_fee', [
            'club' => $clubId,
        ]));
    }
}
