<?php

namespace App\Modules\Parties\Exceptions;

use RuntimeException;

/**
 * Raised when a Profile creation would add a SECOND non-terminal Profile for a (Customer, Club) pair that
 * already has a live one (parties-core, design D8; party-registry — Requirement: Profile — Multi-Profile
 * Membership). A Customer holds AT MOST ONE non-terminal Profile per Club (BR-K-Identity-2); because rejected
 * Profiles are not reused, the uniqueness is scoped to non-terminal states (§ 4.2.1).
 *
 * The partial unique index `(customer_id, club_id) WHERE state NOT IN ('rejected','cancelled','inactive')` on
 * `parties_profiles` is the true structural guard (it would reject the insert with an integrity error); the
 * `CreateProfile` in-transaction pre-check throws this first to surface a clean, operator-facing reason ahead of
 * the raw violation (the same belt-and-braces pattern as the {@see MissingClubProducer} /
 * {@see MissingAgreementProducer} / {@see DuplicateCustomerEmail} guards).
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded user-facing
 * strings). The `:customer` and `:club` placed in the message are operator-facing identity references, NOT PII
 * (unlike the {@see DuplicateCustomerEmail} email, which is deliberately omitted) — so, like the producer-id
 * sibling guards, they are interpolated to make the reason self-documenting. `(string)` coerces the translator
 * return (typed `mixed` by Larastan) to the RuntimeException message contract.
 */
class DuplicateProfileForClub extends RuntimeException
{
    public static function forCustomerAndClub(int $customerId, int $clubId): self
    {
        return new self((string) __('parties.profile.duplicate_for_club', [
            'customer' => $customerId,
            'club' => $clubId,
        ]));
    }
}
