<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Actions\CreateProducerAgreement;
use App\Modules\Parties\Enums\SettlementCadence;
use RuntimeException;

/**
 * Raised when {@see CreateProducerAgreement} is given a `settlement_cadence` outside the closed
 * {@see SettlementCadence} set — `quarterly`, `monthly`, `semi_annual` (change parties-module-k-br-guards,
 * task 3.1; design D4; party-registry — Requirement: ProducerAgreement; BR-K-Agreement-2 / canon MVP-DEC-010 —
 * ADR 2026-07-07-adopt-mvp-dec-010-settlement-cadence-closed-set). The cadence times Module-E settlement and
 * Module-D PO issuance (the D19 seam), so an out-of-set token would mis-time money movement — the set is
 * enforced server-side at the API layer here, ahead of the raw `ValueError` the model's enum cast would throw
 * on `create()`. The PostgreSQL CHECK (regenerated from `SettlementCadence::cases()`) is the DB backstop; on
 * SQLite this boundary plus the cast are the value-set floor.
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12). Like {@see InvalidAddressCountryCode}
 * — and unlike {@see DuplicateCustomerEmail}, which omits the email because an email is PII — a settlement-cadence
 * token is NOT personal data (a value like `annual` or a typo `quaterly`), so the offending value IS interpolated
 * (the `:country` / `:producer` / `:club` id discipline) to make the rejection self-documenting for the operator
 * who supplied it. `(string)` coerces the translator return (typed `mixed` by Larastan) to the RuntimeException
 * message contract.
 */
class InvalidSettlementCadence extends RuntimeException
{
    public static function forCadence(string $cadence): self
    {
        return new self((string) __('parties.producer_agreement.invalid_settlement_cadence', ['cadence' => $cadence]));
    }
}
