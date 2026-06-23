<?php

namespace App\Modules\Parties\Exceptions;

use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use RuntimeException;

/**
 * Raised when {@see ApplyClubCredit} is invoked on a legitimately-`active` credit but a redemption precondition
 * fails (change club-credit, design L6; party-registry — Requirement: Club Credit Redemption and Carry-Forward;
 * Module K PRD § 11.2 / § 10.1). Unlike the FSM from-state guard ({@see IllegalClubCreditTransition::cannotApply}),
 * these reject a credit that IS in the right state — they are the value/context preconditions on the redeemed
 * amount and the owning Profile (mirroring how issuance keeps its from-state-less gates in
 * {@see ClubCreditIssuancePrecondition}), each rejected BEFORE any write so the credit's `remaining` and `state`
 * are left unchanged:
 *   - {@see currencyMismatch} — the redeemed amount's currency differs from the credit currency. The explicit
 *     equality check makes {@see Money::minus()}'s `InvalidArgumentException` unreachable
 *     (design L6): there is no FX in Module K, so a cross-currency redemption is a caller bug, not a conversion.
 *   - {@see overApplication} — the redeemed amount exceeds `remaining` (a negative balance is unrepresentable;
 *     a package exceeding the credit applies the full `remaining` and the difference is paid in cash — a Module S
 *     concern, AC-K-J-18).
 *   - {@see frozenWhileSuspended} — the owning Profile is `Suspended`, which FREEZES the credit: no redemption (or
 *     accrual) while suspended (AC-K-FSM-2a; § 10.1). The credit becomes mutable again once the Profile is restored.
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded user-facing
 * strings): the English baseline lives in the `club_credit` group of `lang/en/parties.php` (keys
 * `currency_mismatch` / `over_application` / `frozen_while_suspended`, added in the change's i18n task 5.2). The
 * interpolated tokens are operator-facing identity/business references, NOT PII (like the club-id sibling guard):
 * the ISO 4217 currency codes (`:expected` / `:actual`) and the Club Credit id (`:credit`) — the redeemed/remaining
 * minor-unit amounts are deliberately kept OUT of the message (a balance is customer financial data). `(string)`
 * coerces the translator return (typed `mixed` by Larastan) to the RuntimeException message contract.
 */
class ClubCreditRedemptionPrecondition extends RuntimeException
{
    public static function currencyMismatch(Currency $creditCurrency, Currency $redeemedCurrency): self
    {
        return new self((string) __('parties.club_credit.currency_mismatch', [
            'expected' => $creditCurrency->value,
            'actual' => $redeemedCurrency->value,
        ]));
    }

    public static function overApplication(int $creditId): self
    {
        return new self((string) __('parties.club_credit.over_application', [
            'credit' => $creditId,
        ]));
    }

    public static function frozenWhileSuspended(int $creditId): self
    {
        return new self((string) __('parties.club_credit.frozen_while_suspended', [
            'credit' => $creditId,
        ]));
    }
}
