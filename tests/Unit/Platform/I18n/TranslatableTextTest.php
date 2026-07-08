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

/**
 * `sameContent()` is the equality every content-diff must use, and it exists because BOTH of PHP's own
 * comparisons are wrong for this type: `===` on two instances is object identity, and `==` on their maps is
 * loose array comparison, which recurses into loose VALUE comparison — under which two numeric strings compare
 * numerically. The catalog's enrichment diff decides whether `EnrichmentDataUpdated` fires; if the comparison
 * swallowed a real edit, the event would never reach its consumer and the operator would still see "saved".
 */
it('compares by content: order-insensitive over locales, strict over texts', function () {
    $notes = TranslatableText::of(['en' => 'Cherry, cedar.', 'it' => 'Ciliegia, cedro.']);
    $reordered = TranslatableText::of(['it' => 'Ciliegia, cedro.', 'en' => 'Cherry, cedar.']);

    expect(TranslatableText::sameContent($notes, $reordered))->toBeTrue()          // construction order is not content
        ->and(TranslatableText::sameContent($notes, $notes))->toBeTrue()
        ->and(TranslatableText::sameContent($notes, TranslatableText::of(['en' => 'Cherry, cedar.'])))->toBeFalse()  // a dropped locale IS a change
        ->and(TranslatableText::sameContent($notes, TranslatableText::of(['en' => 'Cherry, cedar.', 'it' => 'Ciliegia.'])))->toBeFalse();
});

it('treats an absent value and an empty map as the same content, and either as different from real text', function () {
    expect(TranslatableText::sameContent(null, null))->toBeTrue()
        ->and(TranslatableText::sameContent(null, TranslatableText::of([])))->toBeTrue()   // neither carries text
        ->and(TranslatableText::sameContent(TranslatableText::of([]), null))->toBeTrue()
        ->and(TranslatableText::sameContent(null, TranslatableText::of(['en' => 'Cherry.'])))->toBeFalse()
        ->and(TranslatableText::sameContent(TranslatableText::of(['en' => 'Cherry.']), null))->toBeFalse();
});

/**
 * The regression this method was extracted for. On PHP 8 `['en' => '1e2'] == ['en' => '100']` is TRUE (both
 * texts are numeric strings, so loose comparison compares them NUMERICALLY), as is `['en' => '0'] == ['en' =>
 * '0.0']` and `['en' => '10'] == ['en' => ' 10']`. Prose rarely trips this; the enrichment surface is
 * deliberately field-agnostic, so the adapter's numeric critic scores would land on this exact diff.
 */
it('does not compare two numeric strings numerically (the loose-comparison trap)', function (string $left, string $right) {
    // The trap itself, asserted so this test cannot silently stop being about anything.
    expect(['en' => $left] == ['en' => $right])->toBeTrue();

    expect(TranslatableText::sameContent(
        TranslatableText::of(['en' => $left]),
        TranslatableText::of(['en' => $right]),
    ))->toBeFalse();
})->with([
    'exponent vs decimal' => ['1e2', '100'],
    'integer vs float' => ['0', '0.0'],
    'leading whitespace' => ['10', ' 10'],
    'plus sign' => ['5', '+5'],
]);
