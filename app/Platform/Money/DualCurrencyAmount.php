<?php

namespace App\Platform\Money;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The D18 dual-record amount: a customer-currency `Money`, its EUR-equivalent
 * `Money`, the locked `FxRate` and the rate's timestamp, as one immutable bundle
 * (foundations-money-i18n-flags, task 1.4; money capability — Requirement:
 * Dual-Currency Amount; design D3).
 *
 * Every customer-facing financial event records both currency legs with a locked
 * FX rate (CLAUDE.md invariant 5; Architecture § 5.2 — D18 dual-recording FLOOR;
 * spec/04-decisions/decisions.md DEC-169). This value object IS that record and
 * nothing more: it bundles the two amounts, the rate that ties them and the moment
 * the rate was locked, and serialises to the DEC-169 payload shape `amount` +
 * `currency` (the original leg) + `eur_equivalent_amount` + `fx_rate` +
 * `fx_rate_date`. The base leg carries no currency code in the payload — it is EUR
 * by definition — so its `Money` is asserted to be in EUR at construction
 * ({@see assertEurLeg()}); recording a non-EUR amount under `eur_equivalent_amount`
 * would silently mis-state the base-currency ledger.
 *
 * The locked rate is preserved verbatim: a refund settles at the original captured
 * rate (invariant 5; DEC-169 refund rule — "refunds use original payment's FX rate,
 * NOT a fresh snapshot"), so the bundle hands back the same `FxRate` it was given
 * and never re-derives one. The rate's timestamp is held as an immutable
 * `DateTimeImmutable` (a `CarbonImmutable` satisfies it; a mutable date is rejected
 * at the type boundary, keeping the bundle immutable) and serialised as a full ISO
 * 8601 timestamp — the snapshot's granularity is the caller's and is preserved, not
 * truncated here.
 *
 * **Landmine — pure representation, no FX policy (design D3).** It encodes none of
 * the FX mechanics: not which leg locks when, not the snapshot time, buffer % or
 * refresh cadence (DEC-038/DEC-169) — all of that is Module E's and out of this
 * change's scope. The bundle derives no rate and exposes no method that would; a
 * guard test pins the public method surface to construction + serialisation only.
 */
class DualCurrencyAmount
{
    private function __construct(
        public readonly Money $amount,
        public readonly Money $eurEquivalent,
        public readonly FxRate $fxRate,
        public readonly DateTimeImmutable $fxRateDate,
    ) {
        $this->assertEurLeg();
    }

    /**
     * Bundle a customer-currency amount with its EUR equivalent, the locked rate and
     * the rate's timestamp — the sole construction path. The EUR-equivalent leg must
     * be in EUR ({@see assertEurLeg()}); the rate and date are stored verbatim.
     */
    public static function of(
        Money $amount,
        Money $eurEquivalent,
        FxRate $fxRate,
        DateTimeImmutable $fxRateDate,
    ): self {
        return new self($amount, $eurEquivalent, $fxRate, $fxRateDate);
    }

    /**
     * Serialise to the DEC-169 dual-currency payload shape: the original leg as an
     * integer `amount` + ISO 4217 `currency`, the base leg as an integer
     * `eur_equivalent_amount` (EUR implied), the locked `fx_rate` as its exact
     * decimal string, and `fx_rate_date` as a full ISO 8601 timestamp. Integers and
     * strings only — never a float, never a formatted money string (the substrate
     * payload discipline; DEC-169).
     *
     * @return array{amount: int, currency: string, eur_equivalent_amount: int, fx_rate: string, fx_rate_date: string}
     */
    public function toPayload(): array
    {
        return [
            'amount' => $this->amount->minorUnits,
            'currency' => $this->amount->currency->value,
            'eur_equivalent_amount' => $this->eurEquivalent->minorUnits,
            'fx_rate' => $this->fxRate->value,
            'fx_rate_date' => $this->fxRateDate->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * Guard that the EUR-equivalent leg is actually in EUR. The payload records it as
     * `eur_equivalent_amount` with no currency code — EUR is implied — so a non-EUR
     * leg would silently mis-state the base-currency ledger; that is a caller bug and
     * throws `InvalidArgumentException` (mirrors the fail-closed, intentful-message
     * convention of {@see Currency::of()} and {@see Money}'s same-currency guard).
     */
    private function assertEurLeg(): void
    {
        if ($this->eurEquivalent->currency !== Currency::base()) {
            throw new InvalidArgumentException(sprintf(
                'The EUR-equivalent leg of a DualCurrencyAmount must be in EUR, got [%s]: the '
                .'dual-currency payload records this leg as eur_equivalent_amount with no currency '
                .'code (EUR is implied), so a non-EUR amount would mis-state the base ledger. '
                .'Convert to EUR through the locked FX rate before bundling.',
                $this->eurEquivalent->currency->value,
            ));
        }
    }
}
