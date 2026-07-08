<?php

namespace App\Modules\Parties\Exceptions;

use RuntimeException;

/**
 * Raised when a new ProducerAgreement is scoped to a specific Club that is not `active` — `sunset` or `closed`
 * (change parties-module-k-br-guards, design D5; party-registry — Requirement: ProducerAgreement; BR-K-Agreement-4
 * / canon MVP-DEC-009 — ADR 2026-07-07-adopt-mvp-dec-009-agreement-club-active-scope). The `CreateProducerAgreement`
 * path (task 3.2) throws this pre-write, so no agreement is created. Producer-wide scope (`club_id` NULL) is
 * ungated; supersession/renewal INHERITS the superseded agreement's scope and is EXEMPT from this check (a
 * wind-down amendment on a since-`sunset` Club is unaffected — canon-specified).
 *
 * The reason is localized (CLAUDE.md invariant 12) from the `producer_agreement` group of `lang/en/parties.php`.
 * It interpolates the operator-facing `:club` id (an identity reference, NOT PII) and the offending `:state`
 * token (a `ClubStatus` backing value — a business enum, not PII, the same discipline as the `cannot_sunset`
 * / `cannot_close` from-state reasons). `(string)` coerces the translator return (typed `mixed` by Larastan)
 * to the RuntimeException message contract.
 */
class ProducerAgreementClubNotActive extends RuntimeException
{
    public static function forClub(int $clubId, string $state): self
    {
        return new self((string) __('parties.producer_agreement.club_not_active', [
            'club' => $clubId,
            'state' => $state,
        ]));
    }
}
