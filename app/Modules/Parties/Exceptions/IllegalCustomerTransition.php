<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\CustomerStatus;
use RuntimeException;

/**
 * Raised when a Customer status transition is attempted from a state the FSM does not allow, or when
 * activation is blocked because the composite onboarding gate is unmet (parties-membership-activation,
 * design L6; parties-membership-suspension, design L4/L7; party-registry — Requirements: Customer
 * Onboarding Activation, Customer Suspension and Closure).
 *
 * The demand-side Customer FSM is `pending → active → suspended → closed` (Module K PRD § 4.1).
 * Activation (`pending → active`) is valid only from `pending` AND only when the conjunctive onboarding
 * gate clears (AC-K-J-1's four hard gates — email verified ∧ T&C accepted ∧ privacy accepted ∧ sanctions
 * passed — plus the AC-K-BR-Identity-3 KYC-cleared rider). The suspension subset adds the status edges:
 * `active → suspended` ({@see cannotSuspend}), `suspended → active` ({@see cannotReactivate}) and
 * `active | suspended → closed` ({@see cannotClose}) — each explicit (manual or via the Hold coupling),
 * never auto-driven by a Profile state or a KYC/sanctions verdict (§ 9.4; AC-K-BR-Customer-1).
 * `ActivateCustomer` and the suspension Actions (`SuspendCustomer` / `ReactivateCustomer` /
 * `CloseCustomer`) are the SOLE writers of `Customer.status`; each re-reads the row `lockForUpdate` inside
 * its transaction and asserts the from-state ({@see cannotActivate} et al.) — and, for activation, the
 * gate ({@see gateNotMet}) — before writing, so a rejected call throws this and the transaction rolls
 * back, the row and the event log left unchanged.
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded user-facing
 * strings): the English baseline lives in the `customer` group of `lang/en/parties.php`.
 * - {@see cannotActivate}, {@see cannotSuspend}, {@see cannotReactivate} and {@see cannotClose} resolve
 *   `customer.cannot_activate` / `cannot_suspend` / `cannot_reactivate` / `cannot_close` with a `:state`
 *   placeholder — the offending from-state token (`$from->value`) is a business enum value, NOT PII, so it
 *   is interpolated to make the reason self-documenting (the sibling {@see IllegalProducerTransition} discipline).
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

    public static function cannotSuspend(CustomerStatus $from): self
    {
        return new self((string) __('parties.customer.cannot_suspend', [
            'state' => $from->value,
        ]));
    }

    public static function cannotReactivate(CustomerStatus $from): self
    {
        return new self((string) __('parties.customer.cannot_reactivate', [
            'state' => $from->value,
        ]));
    }

    public static function cannotClose(CustomerStatus $from): self
    {
        return new self((string) __('parties.customer.cannot_close', [
            'state' => $from->value,
        ]));
    }
}
