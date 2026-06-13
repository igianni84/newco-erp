<?php

namespace App\Platform\I18n;

use App\Platform\Money\MoneyCast;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The Eloquent cast that stores a {@see TranslatableText} as i18n-keyed JSON in a
 * single schema-less column and rehydrates it without loss (foundations-money-i18n-flags,
 * task 2.3; i18n capability — Requirement: Translatable Entity Attributes; design D4).
 *
 * This is the reusable cast Module 0 PIM (F2) will attach to a translatable column —
 * `'name' => TranslatableTextCast::class` over a single `json` column — with no further
 * machinery; no production model uses it yet (it is proven against a throwaway model/
 * table). Unlike {@see MoneyCast} (two columns), a `TranslatableText`
 * persists to ONE column: {@see TranslatableText} is `JsonSerializable`, so `set()` emits
 * the i18n-keyed JSON string and `get()` rehydrates it via {@see TranslatableText::fromJson()}.
 * The column stays schema-less JSON (DEC-064 — "the database column is schema-less JSON");
 * locale-key validity is enforced by the value object at the application layer, not by a
 * column constraint.
 *
 * A null translatable is representable (a null column), so the cast is safe on a nullable
 * translatable column. The trust boundary lives here: `set()` rejects a non-`TranslatableText`
 * assignment fail-closed, and `get()` rejects a corrupt (non-string) column rather than
 * fataling deeper in the persistence layer (the JSON-shape narrowing itself is owned by
 * {@see TranslatableText::fromJson()}).
 *
 * The `set()` leg is typed `mixed` because Eloquent passes through whatever a caller
 * assigned (it does not enforce the generic), so the runtime type-guard is load-bearing;
 * the `get()` leg returns `TranslatableText`, which lets a column cast as
 * `TranslatableTextCast::class` surface as a typed `?TranslatableText` property.
 *
 * @implements CastsAttributes<TranslatableText, mixed>
 */
class TranslatableTextCast implements CastsAttributes
{
    /**
     * Rebuild the `TranslatableText` from its single JSON column. A null column means
     * "no translatable value" → null. The raw value is the stored JSON string; a
     * non-string column is corrupt translatable data and fails closed (the JSON
     * structure is then validated by {@see TranslatableText::fromJson()}).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?TranslatableText
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'Corrupt %s column [%s]: expected an i18n-keyed JSON string, got %s.',
                self::class,
                $key,
                get_debug_type($value),
            ));
        }

        return TranslatableText::fromJson($value);
    }

    /**
     * Map a `TranslatableText` (or null) to its single JSON column. Returning a plain
     * string (or null) writes that one underlying attribute — Laravel normalises a
     * non-array cast response to `[$key => $value]` (HasAttributes::normalizeCastClassResponse).
     * `JSON_THROW_ON_ERROR` makes a serialisation failure throw rather than persist
     * `false`; `JSON_UNESCAPED_UNICODE` keeps non-Latin scripts (JA/ZH) readable on disk
     * (escaping is round-trip-lossless either way).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof TranslatableText) {
            throw new InvalidArgumentException(sprintf(
                'A %s attribute accepts only a %s instance or null, %s given. Build it with '
                .'TranslatableText::of([...]) — locale keys are validated against the supported set.',
                self::class,
                TranslatableText::class,
                get_debug_type($value),
            ));
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
