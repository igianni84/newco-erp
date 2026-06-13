<?php

use App\Platform\I18n\TranslatableText;

/*
 * TranslatableText — the per-row i18n-keyed-JSON value object (DEC-064): holds
 * {"<locale>": "<text>"}, resolves with per-attribute English fallback (DEC-127 item 4),
 * validates locale keys against the supported registry at construction, and round-trips
 * to/from JSON without loss. Pure VO (no app/DB) → a Unit test; the Eloquent persistence
 * is proven by TranslatableTextCastTest (Feature).
 */

it('resolves the requested locale\'s text when present', function () {
    $text = TranslatableText::of(['en' => 'Welcome', 'it' => 'Benvenuto']);

    expect($text->resolve('it'))->toBe('Benvenuto')
        ->and($text->resolve('en'))->toBe('Welcome');
});

it('falls back to English for that attribute only when the locale is absent', function () {
    $text = TranslatableText::of(['en' => 'Welcome', 'it' => 'Benvenuto']);

    // fr is unauthored on this attribute → its English value, not an exception, not a whole-object fallback.
    expect($text->resolve('fr'))->toBe('Welcome');
});

it('resolves a null locale to the English fallback directly', function () {
    $text = TranslatableText::of(['en' => 'Welcome', 'it' => 'Benvenuto']);

    expect($text->resolve())->toBe('Welcome')
        ->and($text->resolve(null))->toBe('Welcome');
});

it('returns null when neither the requested locale nor English is present (partial coverage allowed)', function () {
    // AC-0-XM-4: partial coverage is allowed, so a value may legitimately lack English.
    $text = TranslatableText::of(['it' => 'Benvenuto']);

    expect($text->resolve('fr'))->toBeNull()
        ->and($text->resolve('it'))->toBe('Benvenuto')
        ->and($text->resolve())->toBeNull();
});

it('rejects an unsupported locale key at construction (application-layer validation)', function () {
    expect(fn () => TranslatableText::of(['es' => 'Hola', 'en' => 'Hi']))
        ->toThrow(InvalidArgumentException::class);
});

it('round-trips to and from JSON without loss, preserving every locale entry exactly', function () {
    $text = TranslatableText::of(['en' => 'Red Wine', 'it' => 'Vino Rosso', 'ja' => 'ワイン']);

    $json = json_encode($text, JSON_THROW_ON_ERROR);
    $rehydrated = TranslatableText::fromJson($json);

    expect($rehydrated->translations)->toBe($text->translations)
        ->and($rehydrated->resolve('ja'))->toBe('ワイン')      // non-Latin script preserved exactly
        ->and($rehydrated->resolve('it'))->toBe('Vino Rosso')
        ->and($rehydrated->resolve('fr'))->toBe('Red Wine');  // per-attribute fallback survives the round-trip
});

it('rejects malformed JSON on rehydration (fail-closed)', function () {
    expect(fn () => TranslatableText::fromJson('not-json{'))->toThrow(InvalidArgumentException::class)   // unparseable
        ->and(fn () => TranslatableText::fromJson('"a bare string"'))->toThrow(InvalidArgumentException::class) // not an object
        ->and(fn () => TranslatableText::fromJson('{"en": 123}'))->toThrow(InvalidArgumentException::class)  // non-string text
        ->and(fn () => TranslatableText::fromJson('["a", "b"]'))->toThrow(InvalidArgumentException::class); // list → int keys
});

it('exposes of() as the sole construction path (private constructor)', function () {
    expect((new ReflectionMethod(TranslatableText::class, '__construct'))->isPrivate())->toBeTrue();
});
