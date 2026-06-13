<?php

namespace App\Platform\Money;

use InvalidArgumentException;

/**
 * An exchange rate held as an exact decimal string — never a float
 * (foundations-money-i18n-flags, task 1.3; money capability — Requirement: FX Rate;
 * design D3).
 *
 * `FxRate` is the typed enforcement of the substrate's payload contract that FX
 * rates are decimal strings, never floats (decisions/2026-06-12-event-substrate-and-audit-store.md;
 * openspec/specs/event-substrate/spec.md — "FX rates as decimal strings (never
 * floats)"). A float cannot represent most decimal rates exactly (`1.0842` has no
 * finite binary form), so binding the rate to a string keeps it bit-for-bit: there
 * is NO float-accepting construction path — `of()` takes a `string`, the
 * constructor is private — and the value is preserved verbatim, never reformatted
 * to a "canonical" number. That is what lets a locked rate survive storage and
 * read-back unchanged so a refund settles at the exact captured rate (CLAUDE.md
 * invariant 5; Architecture § 5.2 — "Refunds use the original captured rate").
 *
 * Construction is fail-closed: `of()` accepts only a well-formed plain decimal
 * numeral — one or more digits with an optional single decimal point and fractional
 * digits ({@see DECIMAL_PATTERN}) — and throws on anything else (a sign, a partial
 * decimal like `1.` or `.5`, whitespace, scientific notation, an empty or
 * non-numeric string). The rate's economic validity (positivity, bounds) and its
 * snapshot/buffer/refresh timing are FX policy owned by Module E (DEC-038/DEC-169)
 * — this value object is pure representation and carries none of it (design D3
 * landmine).
 */
class FxRate
{
    /**
     * A plain, non-negative decimal numeral: digits, then an optional single decimal
     * point and one or more fractional digits. `\A`/`\z` anchor the whole string
     * absolutely (unlike `$`, they reject a trailing newline), so no sign, exponent
     * or surrounding whitespace can slip in — keeping the rate a literal decimal
     * string that round-trips bit-for-bit.
     */
    private const DECIMAL_PATTERN = '/\A\d+(\.\d+)?\z/';

    private function __construct(
        public readonly string $value,
    ) {}

    /**
     * Build an `FxRate` from an exact decimal string — the sole construction path,
     * and string-only by design (there is no float overload). Rejects any value that
     * is not a well-formed decimal numeral (see {@see DECIMAL_PATTERN}); the accepted
     * string is then stored verbatim, with no normalisation.
     */
    public static function of(string $rate): self
    {
        if (preg_match(self::DECIMAL_PATTERN, $rate) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Malformed FX rate [%s]: expected an exact decimal string such as "1.0842" '
                .'(digits with an optional single decimal point and fractional digits; no sign, '
                .'exponent or whitespace). FX rates are decimal strings, never floats.',
                $rate,
            ));
        }

        return new self($rate);
    }

    /**
     * The rate as its exact decimal string — the form persisted in payloads
     * (DualCurrencyAmount's `fx_rate`) and the bit-for-bit value read back for a
     * refund. Identical to the {@see $value} read surface.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
