<?php

namespace App\Modules\Parties\Exceptions;

use RuntimeException;

/**
 * Raised when a Customer registration is blocked by the age gate (change parties-module-k-br-guards, design D7;
 * party-registry — Requirement: Registration Age Gate; BR-K-Identity-6 / canon MVP-DEC-022 — ADR
 * 2026-07-07-adopt-mvp-dec-022-club-membership-governance; BMD § 2.8). `CreateCustomer` (task 5.1) throws this
 * pre-creation, so no Customer record, no co-provisioned Account and no `CustomerCreated` event are created. The
 * minimum age is an admin-configurable platform constant (default 18 — the EU alcohol-purchase baseline), NOT
 * hard-coded, mirroring the enhanced-KYC threshold constants (RM-02).
 *
 * Two failure modes, each resolving localized copy (CLAUDE.md invariant 12) from the `customer` group of
 * `lang/en/parties.php`:
 *   - {@see belowMinimum()} — a self-attested `date_of_birth` implies an age below the minimum at the registration date.
 *   - {@see missingDateOfBirth()} — no `date_of_birth` was attested (age attestation is mandatory at launch, BMD § 2.8).
 *
 * The copy names the RULE and interpolates ONLY the `:min_age` platform constant (a public configuration value,
 * NOT PII). The offending `date_of_birth` and the derived age are PII (like the `duplicate_email` email and the
 * `gate_not_met` acceptance timestamps) and are DELIBERATELY never interpolated — the factories only ever receive
 * the constant, so the message is safe to reach logs. `(string)` coerces the translator return (typed `mixed` by
 * Larastan) to the RuntimeException message contract.
 */
class BelowMinimumRegistrationAge extends RuntimeException
{
    public static function belowMinimum(int $minimumAge): self
    {
        return self::build('below_minimum_registration_age', $minimumAge);
    }

    public static function missingDateOfBirth(int $minimumAge): self
    {
        return self::build('missing_date_of_birth', $minimumAge);
    }

    private static function build(string $key, int $minimumAge): self
    {
        return new self((string) __("parties.customer.{$key}", ['min_age' => $minimumAge]));
    }
}
