<?php

use App\Platform\I18n\SupportedLocale;

// Pins that config/i18n.php derives the registry from the SupportedLocale enum (the
// single source of truth, design D4) — the config surface and the typed anchor can
// never drift, and the config file actually loads (it references app code, and
// config/ is NOT under PHPStan, so this test is its only static-correctness guard).
// Feature test: the container/config must be booted (Pest binds the Laravel TestCase
// only in tests/Feature).

it('derives the supported-locale config from the enum', function () {
    expect(config('i18n.supported'))->toBe(SupportedLocale::values())
        ->and(config('i18n.supported'))->toBe(['en', 'it', 'fr', 'de', 'ja', 'zh_Hans']);
});

it('exposes English as the configured fallback locale', function () {
    expect(config('i18n.fallback'))->toBe('en')
        ->and(config('i18n.fallback'))->toBe(SupportedLocale::fallback()->value);
});
