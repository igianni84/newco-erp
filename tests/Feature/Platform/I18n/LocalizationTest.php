<?php

use App\Platform\I18n\SupportedLocale;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

// Pins the lang/ scaffolding (foundations-money-i18n-flags, task 2.2; design D4; i18n
// capability — Requirement: No Hardcoded User-Facing Strings). The app translation
// resources use PHP-array group files (lang/{locale}/welcome.php, dotted keys
// __('welcome.*')) and lean on Laravel's native per-key fallback to English (DEC-127).
//
// The per-locale filesystem assertion is driven off SupportedLocale::values() so a missing
// resource directory reds the suite instead of drifting silently from the registry.
// Feature test: the container/translator must be booted (Pest binds the Laravel TestCase
// only in tests/Feature) — no DB, so no RefreshDatabase.

it('ships a resolvable translation resource for every supported locale', function () {
    foreach (SupportedLocale::values() as $locale) {
        expect(is_dir(lang_path($locale)))
            ->toBeTrue("missing lang/{$locale} translation resource directory")
            ->and(file_exists(lang_path("{$locale}/welcome.php")))
            ->toBeTrue("missing lang/{$locale}/welcome.php");
    }
});

it('resolves a key to the active locale value when that locale defines it', function () {
    App::setLocale('it');

    // `welcome.tagline` is authored in every locale, so under `it` it resolves to the
    // Italian text — proven by being genuinely present in `it` (no fallback) AND differing
    // from the English value (so this is not the fallback firing).
    expect(Lang::has('welcome.tagline', 'it', false))->toBeTrue()
        ->and(__('welcome.tagline'))->toBe(trans('welcome.tagline', [], 'it'))
        ->and(__('welcome.tagline'))->not->toBe(trans('welcome.tagline', [], 'en'));
});

it('falls back to English per key when the active locale lacks that key', function () {
    App::setLocale('it');

    // `welcome.coming_soon` is authored only in `en`. Under `it` it is genuinely absent
    // (the non-vacuity guard) yet still resolves — to the English value, per key.
    expect(Lang::has('welcome.coming_soon', 'it', false))->toBeFalse()
        ->and(__('welcome.coming_soon'))->toBe(trans('welcome.coming_soon', [], 'en'))
        ->and(__('welcome.coming_soon'))->not->toBe('welcome.coming_soon');
});

it('keeps the authored English baseline complete', function () {
    // English is the authored baseline and the final fallback: it must define every key the
    // app introduces (the other five may stagger). Pin the en welcome keys so a future key
    // addition without an English value reds here.
    $en = trans('welcome', [], 'en');

    expect($en)->toBeArray();
    assert(is_array($en)); // narrow string|array → array for PHPStan max (tests/ is analysed)

    expect(array_keys($en))->toBe(['headline', 'tagline', 'coming_soon']);
});
