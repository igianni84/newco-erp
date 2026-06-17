<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\SanctionsStatus;
use RuntimeException;

/**
 * Raised when a sanctions-screening transition is attempted in a way the lifecycle does not allow
 * (parties-compliance, design L2/L4; party-registry — Requirement: Customer Sanctions Screening
 * Lifecycle).
 *
 * The sanctions FSM is `pending → passed | failed | under_review`, `under_review → passed | failed`
 * (§ 9.2). Two invariants are guarded: an **onboarding** screening (`trigger_source = onboarding`)
 * must be the Customer's FIRST — it is rejected when `last_screening_at` is already set
 * ({@see onboardingAlreadyScreened}); and a screening that **resolves** an open review is valid only
 * from `under_review` ({@see cannotResolve}). Re-screens (any non-onboarding trigger) are admissible
 * from any prior state — re-screening can flip `passed → failed` — so they are not guarded here
 * (design L4). The screening Action is the SOLE writer of `sanctions_status`; it re-reads the row
 * `lockForUpdate` inside its transaction and asserts the invariant before writing, so a rejected call
 * throws this and the transaction rolls back, leaving the sanctions state and the event log unchanged.
 *
 * Localized through Laravel's translator (invariant 12): the English baseline lives in the `sanctions`
 * group of `lang/en/parties.php` (keys `onboarding_already_screened` / `cannot_resolve`). The
 * `cannot_resolve` reason interpolates the offending from-state token (`$from->value`) — a business
 * enum value, NOT PII; `onboarding_already_screened` names only the rule (the offending fact is the
 * presence of a prior screening, and its timestamp is PII, so neither is interpolated). `(string)`
 * coerces the translator return (typed `mixed` by Larastan) to the RuntimeException message contract.
 */
class IllegalSanctionsTransition extends RuntimeException
{
    public static function onboardingAlreadyScreened(): self
    {
        return new self((string) __('parties.sanctions.onboarding_already_screened'));
    }

    public static function cannotResolve(SanctionsStatus $from): self
    {
        return new self((string) __('parties.sanctions.cannot_resolve', [
            'state' => $from->value,
        ]));
    }
}
