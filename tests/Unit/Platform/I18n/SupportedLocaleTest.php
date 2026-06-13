<?php

use App\Platform\I18n\SupportedLocale;

// Pins the supported-locale registry (foundations-money-i18n-flags, task 2.1;
// design D4; i18n capability — Requirement: Supported Locales). The enum is the
// single source of truth for the six launch locales; its case/value map is asserted
// verbatim and order-sensitive (mirroring EnumsTest): any drift in a case or its
// canonical locale token must fail here first. en is the final fallback (DEC-127);
// validation is fail-closed (a locale outside the set is rejected — invariant 12,
// DEC-064 application-layer locale validation).

it('registers exactly the six launch locales, in order', function () {
    $values = [];

    foreach (SupportedLocale::cases() as $locale) {
        $values[$locale->name] = $locale->value;
    }

    expect($values)->toBe([
        'En' => 'en',
        'It' => 'it',
        'Fr' => 'fr',
        'De' => 'de',
        'Ja' => 'ja',
        'ZhHans' => 'zh_Hans',
    ]);
});

it('exposes the locale codes as a derived list, in declaration order', function () {
    expect(SupportedLocale::values())->toBe(['en', 'it', 'fr', 'de', 'ja', 'zh_Hans']);
});

it('uses English as the final fallback locale', function () {
    expect(SupportedLocale::fallback())->toBe(SupportedLocale::En)
        ->and(SupportedLocale::fallback()->value)->toBe('en');
});

it('reports every registered locale as supported', function () {
    foreach (SupportedLocale::values() as $locale) {
        expect(SupportedLocale::isSupported($locale))->toBeTrue();
    }
});

it('resolves a supported locale to its typed case via assertSupported', function () {
    expect(SupportedLocale::assertSupported('it'))->toBe(SupportedLocale::It)
        ->and(SupportedLocale::assertSupported('zh_Hans'))->toBe(SupportedLocale::ZhHans);
});

it('rejects a locale outside the registry', function (string $locale) {
    expect(SupportedLocale::isSupported($locale))->toBeFalse();
    expect(fn () => SupportedLocale::assertSupported($locale))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'Spanish (unsupported language)' => 'es',
    'Traditional Chinese (wrong script subtag)' => 'zh_Hant',
    'nonsense code' => 'xx',
    'non-canonical case of a supported locale' => 'EN',
    'hyphenated script subtag (not Laravel underscore form)' => 'zh-Hans',
    'empty string' => '',
]);
