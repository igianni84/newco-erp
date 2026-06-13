<?php

use App\Platform\Money\Currency;
use App\Platform\Money\Money;

// Pins the Money value object (foundations-money-i18n-flags, task 1.2; money
// capability — Requirement: Money Value Object; design D2). Money is the typed
// floor of the money-discipline invariant (CLAUDE.md invariant 6): an integer
// minor-units count + a Currency, never a float. The five delta scenarios are
// pinned below — no float construction path, lossless minor-units round-trip,
// the cross-currency arithmetic guard, the minor-units payload shape, and
// representable negatives — alongside the arithmetic and value-equality happy
// paths the rest of the money stack composes.

it('builds from an integer minor-units count and a currency', function () {
    $money = Money::of(1234, Currency::EUR);

    expect($money->minorUnits)->toBe(1234)
        ->and($money->currency)->toBe(Currency::EUR);
});

it('exposes no float construction path — of() takes a non-nullable int and the constructor is private', function () {
    // Scenario: Money cannot be constructed from a float. The guarantee is
    // structural, so it is asserted against the public construction surface via
    // reflection (rather than a runtime float call the type system would already
    // reject) — making the invariant non-vacuous: of() accepts only an int, and
    // it is the sole construction path (the constructor is private).
    $constructor = new ReflectionMethod(Money::class, '__construct');
    expect($constructor->isPrivate())->toBeTrue();

    $type = (new ReflectionMethod(Money::class, 'of'))->getParameters()[0]->getType();

    expect($type)->toBeInstanceOf(ReflectionNamedType::class);
    assert($type instanceof ReflectionNamedType); // narrow ReflectionType|null for static analysis

    expect($type->getName())->toBe('int')
        ->and($type->allowsNull())->toBeFalse();
});

it('round-trips minor units and currency through its payload without precision loss', function () {
    $money = Money::of(1234, Currency::EUR);

    $payload = $money->toPayload();
    $rehydrated = Money::of($payload['minor_units'], Currency::of($payload['currency']));

    expect($rehydrated->equals($money))->toBeTrue()
        ->and($rehydrated->minorUnits)->toBe(1234)
        ->and($rehydrated->currency)->toBe(Currency::EUR);
});

it('adds and subtracts same-currency amounts on their minor units', function () {
    $sum = Money::of(1234, Currency::EUR)->plus(Money::of(66, Currency::EUR));
    $difference = Money::of(1300, Currency::EUR)->minus(Money::of(66, Currency::EUR));

    expect($sum->equals(Money::of(1300, Currency::EUR)))->toBeTrue()
        ->and($difference->equals(Money::of(1234, Currency::EUR)))->toBeTrue();
});

it('rejects adding or subtracting different currencies', function () {
    expect(fn () => Money::of(1234, Currency::EUR)->plus(Money::of(1, Currency::USD)))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => Money::of(1234, Currency::EUR)->minus(Money::of(1, Currency::USD)))
        ->toThrow(InvalidArgumentException::class);
});

it('serialises to the minor-units payload shape — an integer and an ISO 4217 code', function () {
    $payload = Money::of(1234, Currency::EUR)->toPayload();

    expect($payload)->toBe(['minor_units' => 1234, 'currency' => 'EUR'])
        ->and($payload['minor_units'])->toBeInt()
        ->and($payload['currency'])->toBeString();
});

it('represents negative amounts and preserves their sign through arithmetic', function () {
    $credit = Money::of(-500, Currency::EUR);

    expect($credit->minorUnits)->toBe(-500)
        ->and($credit->negate()->minorUnits)->toBe(500)
        ->and($credit->plus(Money::of(200, Currency::EUR))->minorUnits)->toBe(-300)
        ->and($credit->toPayload())->toBe(['minor_units' => -500, 'currency' => 'EUR']);
});

it('treats amounts with the same minor units and currency as equal', function () {
    expect(Money::of(1234, Currency::EUR)->equals(Money::of(1234, Currency::EUR)))->toBeTrue()
        ->and(Money::of(1234, Currency::EUR)->equals(Money::of(1234, Currency::USD)))->toBeFalse()
        ->and(Money::of(1234, Currency::EUR)->equals(Money::of(99, Currency::EUR)))->toBeFalse();
});
