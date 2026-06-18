<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\CustomerStatus;
use RuntimeException;

/**
 * Raised when a Customer status transition is attempted from a state the FSM does not allow, or when
 * activation is blocked because the composite onboarding gate is unmet (parties-membership-activation,
 * design L6; party-registry — Requirement: Customer Onboarding Activation).
 *
 * The retained demand-side Customer FSM step is `pending → active` (Module K PRD § 4.1): activation is
 * valid only from `pending` AND only when the conjunctive onboarding gate clears (AC-K-J-1's four hard
 * gates — email verified ∧ T&C accepted ∧ privacy accepted ∧ sanctions passed — plus the
 * AC-K-BR-Identity-3 KYC-cleared rider). `ActivateCustomer` is the SOLE writer of `Customer.status`; it
 * re-reads the row `lockForUpdate` inside its transaction and asserts the from-state ({@see cannotActivate})
 * then the gate ({@see gateNotMet}) before writing, so a rejected call throws this and the transaction rolls
 * back — the row and the event log are left unchanged. Activation is **explicit**, never auto-driven from
 * the KYC/sanctions FSMs (§ 9.4; AC-K-BR-Customer-1).
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded user-facing
 * strings): the English baseline lives in the `customer` group of `lang/en/parties.php`.
 * - {@see cannotActivate} resolves `customer.cannot_activate` with a `:state` placeholder — the offending
 *   from-state token (`$from->value`) is a business enum value, NOT PII, so it is interpolated to make the
 *   reason self-documenting (the sibling {@see IllegalProducerTransition} discipline).
 * - {@see gateNotMet} resolves `customer.gate_not_met` and interpolates **nothing**: the offending acceptance
 *   values (verification / T&C / privacy timestamps) are PII and this message can reach logs, so — like the
 *   PII-omitting {@see DuplicateCustomerEmail} — only the gate conditions are named.
 *
 * `(string)` coerces the translator return (typed `mixed` by Larastan) to the RuntimeException message contract.
 */
class IllegalCustomerTransition extends RuntimeException
{
    public static function cannotActivate(CustomerStatus $from): self
    {
        return new self((string) __('parties.customer.cannot_activate', [
            'state' => $from->value,
        ]));
    }

    /**
     * Activation rejected because the composite onboarding gate is unmet (§ 4.1 / AC-K-J-1 +
     * AC-K-BR-Identity-3). Names only the gate conditions — interpolates no acceptance value (PII-free).
     */
    public static function gateNotMet(): self
    {
        return new self((string) __('parties.customer.gate_not_met'));
    }
}
