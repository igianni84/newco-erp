<?php

namespace App\Platform\Money;

use InvalidArgumentException;

/**
 * An ISO 4217 currency in the launch-supported set (foundations-money-i18n-flags,
 * design D2; money capability — Requirement: Currency).
 *
 * A `Currency` carries its ISO 4217 alphabetic code (the case name and backing
 * value, e.g. EUR) and its ISO 4217 minor-unit exponent — the number of decimal
 * places, so `Money` knows how many minor units make one major unit (2 for the
 * cent currencies, 0 for the yen). The launch set is fixed by DEC-037 at exactly
 * five: EUR (the base currency), USD, GBP, CHF and JPY (Architecture § 5.2).
 *
 * Fail-closed: a code outside this set is rejected at construction (`of()`), never
 * defaulted to exponent 2 — a wrong or assumed exponent silently mis-scales money
 * (one yen would read as one-hundredth of a yen), and that is a money bug
 * (CLAUDE.md invariant 6). Adding a currency post-launch ("on demand", DEC-037) is
 * a single registry change here — add a case below and its exponent arm in
 * `minorUnitExponent()` — never a schema migration; currencies are not data rows.
 *
 * - case name    = the ISO 4217 alphabetic code (App\Platform vocabulary)
 * - backing value = the same code (the persisted / payload token)
 */
enum Currency: string
{
    case EUR = 'EUR';
    case USD = 'USD';
    case GBP = 'GBP';
    case CHF = 'CHF';
    case JPY = 'JPY';

    /**
     * The base currency every dual-currency amount reconciles to (DEC-038 — EUR
     * base; Architecture § 5.2). The single accessor so call-sites never hardcode
     * the literal `Currency::EUR`.
     */
    public static function base(): self
    {
        return self::EUR;
    }

    /**
     * Resolve a `Currency` from its ISO 4217 code, fail-closed.
     *
     * Throws (rather than returning a default) for any code outside the launch
     * set — the money-discipline guarantee that an unknown or assumed exponent can
     * never be silently used. `InvalidArgumentException` is chosen over the native
     * enum `ValueError` for an explicit, debuggable message that names the
     * supported set and the one-line fix; the codebase already raises domain-rule
     * guards as Runtime/SPL exceptions with intentful messages (cf.
     * NotInTransactionException). Matching is exact — no case-folding — so a
     * non-canonical code (`eur`) is also rejected.
     */
    public static function of(string $code): self
    {
        return self::tryFrom($code) ?? throw new InvalidArgumentException(sprintf(
            'Unsupported currency code [%s]: the launch set is EUR, USD, GBP, CHF, JPY. '
            .'Add a case to %s to support a new currency.',
            $code,
            self::class,
        ));
    }

    /**
     * The ISO 4217 minor-unit exponent: how many decimal places the currency has,
     * i.e. 10^exponent minor units make one major unit. JPY has no minor unit
     * (exponent 0 — one minor unit is one yen); the cent currencies have two
     * (100 minor units per major unit).
     */
    public function minorUnitExponent(): int
    {
        return match ($this) {
            self::JPY => 0,
            self::EUR, self::USD, self::GBP, self::CHF => 2,
        };
    }
}
