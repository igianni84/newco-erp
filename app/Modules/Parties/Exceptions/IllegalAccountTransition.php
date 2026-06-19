<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\AccountStatus;
use RuntimeException;

/**
 * Raised when an Account status transition is attempted from a state the FSM does not allow
 * (parties-membership-suspension, design L4/L8; party-registry — Requirement: Account Status Lifecycle).
 *
 * The Account status FSM is `active → suspended → closed` (Module K PRD § 4.7): an Account is
 * co-provisioned with its Customer and born `active`, so its only `→ active` edge is the restore
 * `suspended → active` — there is **no** `ActivateAccount` (AC-K-FSM-9; design L8), and this exception
 * therefore exposes no `cannotActivate`. Suspension is valid only from `active` ({@see cannotSuspend}),
 * reactivation only from `suspended` ({@see cannotReactivate}), and closure from `active` or `suspended`
 * ({@see cannotClose}). The transition Actions (`SuspendAccount` / `ReactivateAccount` / `CloseAccount`)
 * are the SOLE writers of `Account.status`; each re-reads the row `lockForUpdate` inside its transaction
 * and asserts the from-state before writing, so a disallowed call throws this and the transaction rolls
 * back — the row is left unchanged. All Account transitions are **audit-only** (§ 15 names no
 * Account-family event — design L8): the `status` write captured in the append-only audit trail is the
 * record.
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded user-facing
 * strings): the English baseline lives in the new `account` group of `lang/en/parties.php` (keys
 * `cannot_suspend` / `cannot_reactivate` / `cannot_close`), with a `:state` placeholder — the offending
 * from-state token (`$from->value`) is a business enum value, NOT PII, so it is interpolated to make the
 * reason self-documenting (the sibling {@see IllegalProfileTransition} discipline). `(string)` coerces the
 * translator return (typed `mixed` by Larastan) to the RuntimeException message contract.
 */
class IllegalAccountTransition extends RuntimeException
{
    public static function cannotSuspend(AccountStatus $from): self
    {
        return new self((string) __('parties.account.cannot_suspend', [
            'state' => $from->value,
        ]));
    }

    public static function cannotReactivate(AccountStatus $from): self
    {
        return new self((string) __('parties.account.cannot_reactivate', [
            'state' => $from->value,
        ]));
    }

    public static function cannotClose(AccountStatus $from): self
    {
        return new self((string) __('parties.account.cannot_close', [
            'state' => $from->value,
        ]));
    }
}
