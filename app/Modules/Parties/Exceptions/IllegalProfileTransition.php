<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\ProfileState;
use RuntimeException;

/**
 * Raised when a Profile membership transition is attempted from a state the FSM does not allow
 * (parties-membership-activation, design L2/L4; parties-membership-suspension, design L4/L5;
 * party-registry — Requirements: Profile Membership Approval, Profile Activation, Profile Suspension
 * and Restoration, Profile Lapse and Grace Renewal, Profile Cancellation and Deactivation).
 *
 * The retained demand-side Profile FSM is `applied → approved | rejected → active` (Module K PRD
 * § 4.2.1): approval and decline are valid only from `applied`, activation only from `approved`.
 * The suspension subset adds the status edges off `active`: `active ↔ suspended`
 * ({@see cannotSuspend} / {@see cannotReactivate}), `active → lapsed → active` grace
 * ({@see cannotLapse} / {@see cannotRenew} — the renewal also rejects a from-state past the
 * 30-day grace window, DEC-034), `active | lapsed → cancelled` ({@see cannotCancel}) and
 * `active → inactive` ({@see cannotDeactivate}). The transition Actions (`ApproveProfile` /
 * `DeclineProfile` / `ActivateProfile`, and the suspension Actions `SuspendProfile` /
 * `ReactivateProfile` / `LapseProfile` / `RenewProfile` / `CancelProfile` / `DeactivateProfile`)
 * are the SOLE writers of `Profile.state`; each re-reads the row `lockForUpdate` inside its
 * transaction and asserts the from-state before writing, so a disallowed call throws this and the
 * transaction rolls back — the row and the event log are left unchanged. Approve/decline and
 * cancellation are **audit-only** (they record no Profile event — § 15.2 names none; L2); the other
 * edges record their verbatim § 15.2 event.
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded
 * user-facing strings): the English baseline lives in the `profile` group of `lang/en/parties.php`
 * (keys `cannot_approve` / `cannot_reject` / `cannot_activate` / `cannot_suspend` /
 * `cannot_reactivate` / `cannot_lapse` / `cannot_renew` / `cannot_cancel` / `cannot_deactivate`),
 * with a `:state` placeholder. The
 * offending state token (`$from->value`) is a business enum value, NOT PII — so, like the sibling
 * {@see IllegalProducerTransition}, it is interpolated to make the reason self-documenting.
 * `(string)` coerces the translator return (typed `mixed` by Larastan) to the RuntimeException
 * message contract.
 */
class IllegalProfileTransition extends RuntimeException
{
    public static function cannotApprove(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_approve', [
            'state' => $from->value,
        ]));
    }

    public static function cannotReject(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_reject', [
            'state' => $from->value,
        ]));
    }

    public static function cannotActivate(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_activate', [
            'state' => $from->value,
        ]));
    }

    public static function cannotSuspend(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_suspend', [
            'state' => $from->value,
        ]));
    }

    public static function cannotReactivate(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_reactivate', [
            'state' => $from->value,
        ]));
    }

    public static function cannotLapse(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_lapse', [
            'state' => $from->value,
        ]));
    }

    /**
     * Renewal rejected — either the Profile is not in `lapsed`, or it is `lapsed` but the 30-day grace
     * window has elapsed (DEC-034; design L5). The `:state` reason names the from-state and the grace rule.
     */
    public static function cannotRenew(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_renew', [
            'state' => $from->value,
        ]));
    }

    public static function cannotCancel(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_cancel', [
            'state' => $from->value,
        ]));
    }

    public static function cannotDeactivate(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_deactivate', [
            'state' => $from->value,
        ]));
    }
}
