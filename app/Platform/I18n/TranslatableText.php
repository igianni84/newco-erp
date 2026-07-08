<?php

namespace App\Platform\I18n;

use InvalidArgumentException;
use JsonSerializable;

/**
 * A translatable attribute held as i18n-keyed JSON — an object of
 * `{"<locale>": "<text>"}` with at most one entry per supported locale
 * (foundations-money-i18n-flags, task 2.3; i18n capability — Requirement:
 * Translatable Entity Attributes; design D4).
 *
 * This is the reusable primitive Module 0 PIM (F2) attaches to a translatable
 * column (a wine name, a tasting note) without any further machinery: the storage
 * column stays a schema-less JSON column, and validity is enforced here at the
 * application layer (DEC-064 — "Locale validation: application-layer; the database
 * column is schema-less JSON; no separate translation registry at launch"). This
 * change ships the primitive but attaches it to no module column (no module entities
 * exist yet).
 *
 * Two distinct i18n mechanisms must not be confused: the `lang/` group files hold the
 * platform's STATIC UI chrome (the same key across every row — `welcome.headline`),
 * resolved with `__()`; `TranslatableText` holds PER-ROW content (this bottle's name
 * differs from that bottle's), resolved with {@see resolve()}.
 *
 * Resolution applies per-attribute English fallback (DEC-127 item 4): a requested
 * locale that is absent falls back to English FOR THIS ATTRIBUTE ONLY — never a
 * whole-object or whole-page fallback. Partial coverage is allowed (AC-0-XM-4 — PIM
 * does not enforce locale-completeness), so a value may legitimately carry only some
 * locales, and {@see resolve()} returns null only when neither the requested locale
 * nor English is present (rather than fabricating text or throwing on a render).
 *
 * Construction is fail-closed on the WRITE side: every locale key is validated against
 * the supported-locale registry ({@see SupportedLocale::assertSupported()}), so an
 * unsupported key (`es`) is rejected. The READ side ({@see resolve()}) is lenient: an
 * unknown requested locale simply falls back to English — a stray request locale must
 * not crash a page render. It round-trips to and from its JSON form without loss
 * ({@see jsonSerialize()} / {@see fromJson()}), the inverse pair the cast persists.
 */
class TranslatableText implements JsonSerializable
{
    /**
     * @param  array<string, string>  $translations  locale code ⇒ text, every key a supported locale
     */
    private function __construct(
        public readonly array $translations,
    ) {}

    /**
     * Build a `TranslatableText` from a map of locale code ⇒ text — the sole
     * construction path. Every key is validated against the supported-locale registry
     * (DEC-064 application-layer validation); a key outside the launch set is rejected
     * fail-closed. An empty map is allowed (an as-yet-untranslated attribute); the array
     * structure itself enforces "at most one entry per locale" (duplicate keys cannot
     * exist). Values are stored verbatim, preserving insertion order for a lossless
     * round-trip.
     *
     * @param  array<string, string>  $translations
     */
    public static function of(array $translations): self
    {
        foreach (array_keys($translations) as $locale) {
            SupportedLocale::assertSupported($locale);
        }

        return new self($translations);
    }

    /**
     * Do these two values carry the SAME CONTENT? — the equality every content-diff must use, because
     * `TranslatableText` is a value object and PHP's own comparison operators both get it wrong:
     *
     *   `===` on two instances is IDENTITY (two objects built from the same map are "different"), and
     *   `==` on their maps is LOOSE ARRAY comparison, which recurses into loose VALUE comparison — under
     *   which two NUMERIC STRINGS compare numerically. On PHP 8, `['en' => '1e2'] == ['en' => '100']` is
     *   `true`, as is `['en' => '0'] == ['en' => '0.0']`. A diff built on `!=` therefore silently misses a
     *   real edit whenever both the old and the new text are numeric strings — rare in prose, ordinary the
     *   moment an adapter-fed numeric field (a critic score) travels this same path.
     *
     * The content is the locale ⇒ text MAP, so equality is: same locale keys, same texts, compared as
     * STRINGS, insensitive to the order the map was built in ({@see canonical()} sorts by locale, and `===`
     * on two same-ordered arrays compares values strictly). An ABSENT value and an EMPTY map are the same
     * content — neither carries text — so `null` and `of([])` are equal here, and a cleared attribute does
     * not read as a change merely because it was stored as `{}` rather than `NULL`.
     */
    public static function sameContent(?self $left, ?self $right): bool
    {
        return self::canonical($left) === self::canonical($right);
    }

    /**
     * The comparable form of a (possibly absent) value: its locale ⇒ text map sorted by locale key, with
     * absence and emptiness collapsed to the same `[]`. Sorting is what makes {@see sameContent()} blind to
     * construction order while still comparing the texts with `===`.
     *
     * @return array<string, string>
     */
    private static function canonical(?self $text): array
    {
        if ($text === null) {
            return [];
        }

        $translations = $text->translations;

        ksort($translations);

        return $translations;
    }

    /**
     * Resolve to the requested locale's text, falling back to English for THIS
     * attribute only when the locale is absent (DEC-127 item 4 — per-attribute, never
     * whole-object). Returns null only when neither the requested locale nor English is
     * present (partial coverage is allowed — AC-0-XM-4 — so a value may lack English).
     *
     * The locale is the resolution INPUT: a null locale resolves to the English
     * fallback directly. Wiring this to the active request locale (the DEC-127
     * `Accept-Language` → sticky cookie → English chain, the manual switcher, the
     * Bottle-Page render) is deferred to Module B / the consumer frontend — this
     * primitive stays pure and reads no request or application state.
     */
    public function resolve(?string $locale = null): ?string
    {
        $fallback = SupportedLocale::fallback()->value;
        $locale ??= $fallback;

        return $this->translations[$locale] ?? $this->translations[$fallback] ?? null;
    }

    /**
     * The i18n-keyed JSON shape — `{"<locale>": "<text>"}`. Implementing
     * `JsonSerializable` makes `json_encode($text)` emit the flat locale ⇒ text object
     * (not a nested `{"translations": {...}}`), the on-disk form the cast persists and
     * the inverse of {@see fromJson()}.
     *
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->translations;
    }

    /**
     * Rehydrate from the i18n-keyed JSON produced by `json_encode($this)` — the inverse
     * of {@see jsonSerialize()}, preserving every locale entry exactly. Fail-closed on
     * anything that is not a JSON object of string ⇒ string (a non-object, a non-string
     * key or value): persisted translatable data that has been corrupted must not be
     * silently coerced. Locale keys are then re-validated by {@see of()}.
     */
    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException(sprintf(
                'Malformed %s JSON: expected an object of {"<locale>": "<text>"}, got %s.',
                self::class,
                get_debug_type($decoded),
            ));
        }

        $translations = [];

        foreach ($decoded as $locale => $text) {
            if (! is_string($locale) || ! is_string($text)) {
                throw new InvalidArgumentException(sprintf(
                    'Malformed %s JSON: every entry must be "<locale>": "<text>" (string ⇒ string), got %s ⇒ %s.',
                    self::class,
                    get_debug_type($locale),
                    get_debug_type($text),
                ));
            }

            $translations[$locale] = $text;
        }

        return self::of($translations);
    }
}
