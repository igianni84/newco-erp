<?php

namespace App\Platform\I18n;

use InvalidArgumentException;

/**
 * A locale in the launch-supported set (foundations-money-i18n-flags, design D4;
 * i18n capability — Requirement: Supported Locales).
 *
 * This enum is the single source of truth (the "typed anchor") for the locales the
 * platform supports: exactly the six launch locales fixed by DEC-031 / AC-0-XM-4 —
 * English, Italian, French, German, Japanese and Simplified Chinese — with English
 * the final fallback (DEC-127). `config/i18n.php` derives its array form from these
 * cases, so there is one place to change and the two can never drift.
 *
 * Identifiers are the canonical locale strings Laravel resolves against
 * (`App::setLocale('zh_Hans')`, `lang/zh_Hans/…`): lowercase, with the script
 * subtag in Laravel's underscore form (`zh_Hans` — the AC-0-XM-4 "zh-Hans").
 *
 * Fail-closed: a string outside this set is reported `false` by `isSupported()` and
 * rejected by `assertSupported()`, never silently accepted — an unvalidated locale
 * key is a bug (CLAUDE.md invariant 12; DEC-064 application-layer locale validation,
 * the storage column staying schema-less JSON). Adding a locale post-launch is a
 * single registry change here — add a case below and its `lang/<value>/…`
 * resources — never a schema migration (DEC-031; "adding a locale post-launch is
 * configuration, not migration").
 *
 * - case name    = the locale in PascalCase (App\Platform vocabulary)
 * - backing value = the canonical locale string (the persisted / resolved token)
 */
enum SupportedLocale: string
{
    case En = 'en';
    case It = 'it';
    case Fr = 'fr';
    case De = 'de';
    case Ja = 'ja';
    case ZhHans = 'zh_Hans';

    /**
     * The final fallback locale (DEC-127 — "English (final fallback)"). The single
     * accessor so call-sites never hardcode the literal `SupportedLocale::En`.
     */
    public static function fallback(): self
    {
        return self::En;
    }

    /**
     * The supported locale codes in declaration order — the array form of the
     * registry, derived from the cases so `config/i18n.php` and the `lang/`
     * scaffolding reference one source of truth rather than restating the set.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $locale): string => $locale->value, self::cases());
    }

    /**
     * Whether a locale string is in the supported registry. The boolean predicate
     * for conditional resolution (per-attribute fallback, locale negotiation); use
     * `assertSupported()` when an unsupported locale must fail loudly instead.
     */
    public static function isSupported(string $locale): bool
    {
        return self::tryFrom($locale) !== null;
    }

    /**
     * Resolve a `SupportedLocale` from its code, fail-closed.
     *
     * Throws (rather than returning a default or the fallback) for any locale
     * outside the registry — the application-layer locale validation DEC-064
     * mandates, since the storage column stays schema-less JSON and validity is
     * enforced here, not by a constraint. `InvalidArgumentException` is chosen over
     * the native enum `ValueError` for an explicit, debuggable message that names
     * the supported set and the one-line fix (mirrors `Currency::of()`). Matching is
     * exact — no case-folding — so a non-canonical code (`EN`, the hyphenated
     * `zh-Hans`) is also rejected.
     */
    public static function assertSupported(string $locale): self
    {
        return self::tryFrom($locale) ?? throw new InvalidArgumentException(sprintf(
            'Unsupported locale [%s]: the launch set is en, it, fr, de, ja, zh_Hans. '
            .'Add a case to %s to support a new locale.',
            $locale,
            self::class,
        ));
    }
}
