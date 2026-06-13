<?php

namespace App\Platform\Money;

use InvalidArgumentException;

/**
 * An immutable monetary amount: an integer count of minor units in a single
 * `Currency` (foundations-money-i18n-flags, task 1.2; money capability —
 * Requirement: Money Value Object; design D2).
 *
 * Money discipline (CLAUDE.md invariant 6; Architecture § 5.2 D18 FLOOR) is made
 * unrepresentable to violate, not merely documented: there is NO float-accepting
 * construction path — `of()` takes an `int` minor-units count, the constructor is
 * private, and no factory accepts a float — so a fractional minor unit or a
 * binary-rounding amount cannot be expressed. (A minor unit is the smallest
 * indivisible unit of the currency — a cent for EUR, a whole yen for JPY; how many
 * make one major unit is the currency's {@see Currency::minorUnitExponent()}.)
 *
 * Arithmetic operates on the integer minor units and is closed over a single
 * currency: `plus`/`minus`/`negate` return a new `Money` (the value never mutates),
 * and combining two currencies throws rather than silently adding raw minor units
 * — cross-currency amounts must convert through an explicit, locked FX rate first
 * ({@see DualCurrencyAmount} / Module E), never here. Equality is by value (minor
 * units + currency). Negative amounts are valid — Club Credit, refunds and
 * reversals are negative money (CONTEXT.md; invariant 5).
 *
 * `toPayload()` yields the envelope shape the domain-event and audit recorders
 * persist: an integer `minor_units` field + an ISO 4217 `currency` code, never a
 * float and never a formatted string (DEC-169; the substrate's payload discipline,
 * decisions/2026-06-12-event-substrate-and-audit-store.md). Rehydration composes
 * the public factory: `Money::of($p['minor_units'], Currency::of($p['currency']))`.
 */
class Money
{
    private function __construct(
        public readonly int $minorUnits,
        public readonly Currency $currency,
    ) {}

    /**
     * Build a `Money` from an integer minor-units count and a currency — the sole
     * construction path, and integer-only by design (there is no float overload).
     */
    public static function of(int $minorUnits, Currency $currency): self
    {
        return new self($minorUnits, $currency);
    }

    /**
     * This amount plus another of the SAME currency, as a new value (this one is
     * unchanged). Throws on a currency mismatch — see {@see assertSameCurrency()}.
     */
    public function plus(self $addend): self
    {
        $this->assertSameCurrency($addend);

        return new self($this->minorUnits + $addend->minorUnits, $this->currency);
    }

    /**
     * This amount minus another of the SAME currency, as a new value. Throws on a
     * currency mismatch.
     */
    public function minus(self $subtrahend): self
    {
        $this->assertSameCurrency($subtrahend);

        return new self($this->minorUnits - $subtrahend->minorUnits, $this->currency);
    }

    /**
     * The additive inverse, as a new value (same currency): a debit becomes a
     * credit and vice versa. Negating a negative amount yields a positive one.
     */
    public function negate(): self
    {
        return new self(-$this->minorUnits, $this->currency);
    }

    /**
     * Value equality: same minor units AND same currency. Two `Money` built from
     * the same components are equal even though they are distinct instances.
     */
    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits
            && $this->currency === $other->currency;
    }

    /**
     * Serialise to the recorder/payload shape: an integer minor-units count + the
     * ISO 4217 currency code (DEC-169; the substrate payload discipline). Rehydrate
     * with `Money::of($payload['minor_units'], Currency::of($payload['currency']))`.
     *
     * @return array{minor_units: int, currency: string}
     */
    public function toPayload(): array
    {
        return [
            'minor_units' => $this->minorUnits,
            'currency' => $this->currency->value,
        ];
    }

    /**
     * Guard that two amounts share a currency before combining them. A mismatch is
     * a caller bug — cross-currency arithmetic needs an explicit FX conversion
     * (DualCurrencyAmount / Module E), never a raw minor-unit add — so it throws
     * `InvalidArgumentException` naming both currencies (mirrors the fail-closed,
     * intentful-message convention of {@see Currency::of()}).
     */
    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(sprintf(
                'Cannot combine Money of different currencies [%s and %s]: convert through '
                .'an explicit locked FX rate (DualCurrencyAmount / Module E) before combining, '
                .'never add raw minor units.',
                $this->currency->value,
                $other->currency->value,
            ));
        }
    }
}
