<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\ProfileState;
use RuntimeException;

/**
 * Raised when a Profile membership transition is attempted from a state the FSM does not allow
 * (parties-membership-activation, design L2/L4; party-registry — Requirements: Profile Membership
 * Approval, Profile Activation).
 *
 * The retained demand-side Profile FSM is `applied → approved | rejected → active` (Module K PRD
 * § 4.2.1): approval and decline are valid only from `applied`, activation only from `approved`.
 * The transition Actions (`ApproveProfile` / `DeclineProfile` / `ActivateProfile`) are the SOLE
 * writers of `Profile.state`; each re-reads the row `lockForUpdate` inside its transaction and
 * asserts the from-state before writing, so a disallowed call throws this and the transaction rolls
 * back — the row and the event log are left unchanged. Approve/decline are **audit-only** (they
 * record no Profile event — § 15.2 names none; L2); only activation records `ProfileActivated`.
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded
 * user-facing strings): the English baseline lives in the `profile` group of `lang/en/parties.php`
 * (keys `cannot_approve` / `cannot_reject` / `cannot_activate`), with a `:state` placeholder. The
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
}
