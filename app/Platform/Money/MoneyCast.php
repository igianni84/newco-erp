<?php

namespace App\Platform\Money;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The Eloquent cast that stores a {@see Money} across two columns and rehydrates it
 * with no precision loss (foundations-money-i18n-flags, task 1.5; money capability —
 * Requirement: Money Value Object; design D2). This is the reusable cast every F2
 * money column will use (Club Credit balance, Offer/Allocation prices); no production
 * model uses it yet — it is proven against a throwaway test model/table.
 *
 * Money discipline (CLAUDE.md invariant 6) end-to-end: a `Money` persists as an
 * integer minor-units count + an ISO 4217 currency code and reads back equal — there
 * is no float column and no float path, so a fractional minor unit or a binary-rounding
 * amount cannot reach storage. The cast is the on-disk counterpart of {@see Money::of()}.
 *
 * Convention over configuration: the two backing columns are derived from the cast
 * attribute key — a column cast as `'price' => MoneyCast::class` is backed by
 * `price_minor` (an integer column) + `price_currency` (a short string column for the
 * ISO 4217 code). No per-column wiring is needed; F2 schemas follow the `{key}_minor`
 * / `{key}_currency` naming.
 *
 * A null money is representable (both columns null), so the cast is safe on a nullable
 * money column. Assigning anything other than a `Money` (or null) is a caller bug and
 * throws fail-closed with an intentful message (mirrors {@see Currency::of()}), rather
 * than fataling deep inside the persistence layer.
 *
 * The `set()` leg is typed `mixed` because Eloquent passes through whatever a caller
 * assigned (it does not enforce the generic), so the runtime type-guard is real and
 * load-bearing; the `get()` leg is typed `Money`, which is what lets a column cast as
 * `MoneyCast::class` surface as a typed `Money` property on the owning model.
 *
 * @implements CastsAttributes<Money, mixed>
 */
class MoneyCast implements CastsAttributes
{
    /**
     * Rebuild the `Money` from its two backing columns — `{$key}_minor` (the integer
     * minor-units count) and `{$key}_currency` (the ISO 4217 code). A null minor-units
     * column means "no money" → null (the nullable-column case). The minor-units value
     * is read as `int` because some drivers return integer columns as numeric strings;
     * a non-numeric minor-units or non-string currency column is corrupt money data and
     * fails closed rather than being silently coerced (invariant 6).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        $minorUnits = $attributes["{$key}_minor"] ?? null;
        $currency = $attributes["{$key}_currency"] ?? null;

        if ($minorUnits === null || $currency === null) {
            return null;
        }

        if (! is_numeric($minorUnits) || ! is_string($currency)) {
            throw new InvalidArgumentException(sprintf(
                'Corrupt %s columns for attribute [%s]: expected a numeric minor-units value and a string '
                .'currency code, got %s and %s.',
                self::class,
                $key,
                get_debug_type($minorUnits),
                get_debug_type($currency),
            ));
        }

        return Money::of((int) $minorUnits, Currency::of($currency));
    }

    /**
     * Map a `Money` (or null) to its two backing columns. Returning an array of
     * column ⇒ value pairs writes multiple underlying attributes at once — Laravel's
     * documented multi-column cast contract (HasAttributes::normalizeCastClassResponse
     * spreads the array into the model's attributes).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [
                "{$key}_minor" => null,
                "{$key}_currency" => null,
            ];
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException(sprintf(
                'A %s attribute accepts only a %s instance or null, %s given. Build the amount '
                .'with Money::of($minorUnits, $currency) — money is integer minor units, never a float.',
                self::class,
                Money::class,
                get_debug_type($value),
            ));
        }

        return [
            "{$key}_minor" => $value->minorUnits,
            "{$key}_currency" => $value->currency->value,
        ];
    }
}
