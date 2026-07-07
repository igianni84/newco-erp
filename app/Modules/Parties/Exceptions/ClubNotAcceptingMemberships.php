<?php

namespace App\Modules\Parties\Exceptions;

use RuntimeException;

/**
 * Raised when a `CreateProfile` targets a Club that is not `active` — `sunset` or `closed` (change
 * parties-module-k-br-guards, design D3; party-registry — Requirement: Profile — Multi-Profile Membership;
 * BR-K-Club-3 / AC-K-FSM-6; Module K PRD § 4.3). Creation is the chokepoint enforcing the frozen rule that a
 * `sunset` Club blocks new memberships: the guard (task 4.1) throws this pre-write, so no Profile and no
 * `ProfileCreated` event are created. It closes the "enforcement … is a downstream concern" deferral of the
 * Club Lifecycle requirement.
 *
 * The reason is localized (CLAUDE.md invariant 12) from the `club` group of `lang/en/parties.php`. It
 * interpolates the operator-facing `:club` id (an identity reference, NOT PII) and the offending `:state`
 * token (a `ClubStatus` backing value — a business enum, not PII, the same discipline as the `cannot_sunset`
 * / `cannot_close` from-state reasons). `(string)` coerces the translator return (typed `mixed` by Larastan)
 * to the RuntimeException message contract.
 */
class ClubNotAcceptingMemberships extends RuntimeException
{
    public static function forClub(int $clubId, string $state): self
    {
        return new self((string) __('parties.club.not_accepting_memberships', [
            'club' => $clubId,
            'state' => $state,
        ]));
    }
}
