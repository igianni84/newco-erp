<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\KycStatus;
use RuntimeException;

/**
 * Raised when a KYC transition is attempted from a state the FSM does not allow
 * (parties-compliance, design L2; party-registry — Requirements: Customer KYC Lifecycle,
 * Producer KYC Lifecycle).
 *
 * KYC is one shared four-state domain `not_required → pending → verified | rejected` at both the
 * Customer (§ 9.1) and Producer (§ 4.4) levels: a require operation moves `not_required`/NULL →
 * `pending`, verify/reject move `pending → verified`/`rejected`, and the Producer-only waive is the
 * operator "deselect" to `not_required` (rejected when KYC is already `not_required` — there is
 * nothing to waive; ADR 2026-06-17). The transition Action is the SOLE writer of `kyc_status`; it
 * re-reads the row `lockForUpdate` inside its transaction and asserts the from-state before writing,
 * so an out-of-state call throws this and the transaction rolls back — the row is left unchanged and
 * (KYC records no domain event — design L3) nothing is recorded.
 *
 * Localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded user-facing strings):
 * the English baseline lives in the `kyc` group of `lang/en/parties.php` (keys `cannot_require` /
 * `cannot_verify` / `cannot_reject` / `cannot_waive`), with a `:state` placeholder. The offending
 * state token (`$from->value`) is a business enum value, NOT PII (the same discipline as the sibling
 * {@see IllegalProducerTransition} guard). `(string)` coerces the translator return (typed `mixed` by
 * Larastan) to the RuntimeException message contract.
 */
class IllegalKycTransition extends RuntimeException
{
    public static function cannotRequire(KycStatus $from): self
    {
        return new self((string) __('parties.kyc.cannot_require', [
            'state' => $from->value,
        ]));
    }

    public static function cannotVerify(KycStatus $from): self
    {
        return new self((string) __('parties.kyc.cannot_verify', [
            'state' => $from->value,
        ]));
    }

    public static function cannotReject(KycStatus $from): self
    {
        return new self((string) __('parties.kyc.cannot_reject', [
            'state' => $from->value,
        ]));
    }

    public static function cannotWaive(KycStatus $from): self
    {
        return new self((string) __('parties.kyc.cannot_waive', [
            'state' => $from->value,
        ]));
    }
}
