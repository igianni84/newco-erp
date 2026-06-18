<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use RuntimeException;

/**
 * Raised when a Hold lift violates the per-type lift discipline (parties-holds, design L2;
 * party-registry — Requirement: Hold Lifecycle and Lift Discipline).
 *
 * A Hold is born `active` and moves to `lifted` only through the `LiftHold` operator Action, the sole
 * lift-writer of `parties_holds`. Two rejections guard that path (DEC-160 § 4.8.1; AC-K-FSM-11; ADR
 * 2026-06-18-hold-lift-discipline-per-type):
 *
 * - `autoManaged` — the operator path refuses an auto-managed Hold type (`HoldType::autoLiftable()` —
 *   `kyc` or `payment`). Those types are system-managed and lift only on their clearing signal: the
 *   `kyc` Hold auto-lifts via `RecordKycVerified` (wired in this change), the `payment` auto-lift is a
 *   deferred Module-E seam. An operator therefore never lifts them by hand.
 * - `notActive` — a Hold that is not `active` (already `lifted`) cannot be lifted again; `LiftHold`
 *   re-reads the row `lockForUpdate` inside its transaction and asserts `status === active` before
 *   writing, so an out-of-state call throws this and the transaction rolls back — state and the event
 *   log are left unchanged.
 *
 * Localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded user-facing strings):
 * the English baseline lives in the `hold` group of `lang/en/parties.php` (keys `cannot_lift_auto_managed`
 * with a `:type` placeholder / `cannot_lift_not_active` with a `:state` placeholder). The offending
 * `$type->value` / `$from->value` token is a business enum value, NOT PII (the same discipline as the
 * sibling {@see IllegalKycTransition} guard). `(string)` coerces the translator return (typed `mixed` by
 * Larastan) to the RuntimeException message contract.
 */
class IllegalHoldLift extends RuntimeException
{
    public static function autoManaged(HoldType $type): self
    {
        return new self((string) __('parties.hold.cannot_lift_auto_managed', [
            'type' => $type->value,
        ]));
    }

    public static function notActive(HoldStatus $from): self
    {
        return new self((string) __('parties.hold.cannot_lift_not_active', [
            'state' => $from->value,
        ]));
    }
}
