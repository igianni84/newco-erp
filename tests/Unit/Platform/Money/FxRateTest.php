<?php

use App\Platform\Money\FxRate;

// Pins the FxRate value object (foundations-money-i18n-flags, task 1.3; money
// capability — Requirement: FX Rate; design D3). FxRate is the typed enforcement of
// the substrate's payload contract that FX rates are decimal strings, never floats
// — a float cannot hold most rates exactly (1.0842 has no finite binary form), so a
// locked rate must survive bit-for-bit (invariant 5: refunds settle at the captured
// rate). The three delta scenarios are pinned below — verbatim preservation, no
// float construction path, malformed-string rejection — with the decimal grammar's
// accepted/rejected boundaries hardened against regression.

it('preserves a decimal-string rate exactly', function () {
    $rate = FxRate::of('1.0842');

    expect((string) $rate)->toBe('1.0842')->toBeString()
        ->and($rate->value)->toBe('1.0842');
});

it('preserves the rate string bit-for-bit, without numeric normalisation', function () {
    // Trailing zeros, a bare integer and a long fraction are all kept verbatim — the
    // VO never reformats to a "canonical" number, so a locked rate reads back with
    // no precision drift (the deeper guarantee behind "preserved exactly").
    expect((string) FxRate::of('1.08420'))->toBe('1.08420')
        ->and((string) FxRate::of('1'))->toBe('1')
        ->and((string) FxRate::of('0.000001'))->toBe('0.000001');
});

it('exposes no float construction path — of() takes a non-nullable string and the constructor is private', function () {
    // Scenario: an FX rate cannot be constructed from a float. The guarantee is
    // structural, so it is asserted against the public construction surface via
    // reflection — making the invariant non-vacuous: of() accepts only a string, and
    // it is the sole construction path (the constructor is private).
    $constructor = new ReflectionMethod(FxRate::class, '__construct');
    expect($constructor->isPrivate())->toBeTrue();

    $type = (new ReflectionMethod(FxRate::class, 'of'))->getParameters()[0]->getType();

    expect($type)->toBeInstanceOf(ReflectionNamedType::class);
    assert($type instanceof ReflectionNamedType); // narrow ReflectionType|null for static analysis

    expect($type->getName())->toBe('string')
        ->and($type->allowsNull())->toBeFalse();
});

it('rejects a malformed rate string', function () {
    expect(fn () => FxRate::of('1.08.42'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FxRate::of('abc'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FxRate::of(''))->toThrow(InvalidArgumentException::class);
});

it('rejects non-decimal forms — sign, partial decimals, whitespace and scientific notation', function () {
    expect(fn () => FxRate::of('-1.5'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FxRate::of('+1.5'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FxRate::of('1.'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FxRate::of('.5'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FxRate::of(' 1.5'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FxRate::of("1.5\n"))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FxRate::of('1.0842e3'))->toThrow(InvalidArgumentException::class);
});
