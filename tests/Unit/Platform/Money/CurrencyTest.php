<?php

use App\Platform\Money\Currency;

// Pins the launch currency set (foundations-money-i18n-flags, task 1.1; money
// capability — Requirement: Currency; design D2). The five-currency set (DEC-037)
// and each ISO 4217 minor-unit exponent are the floor of the money-discipline
// invariant — a wrong exponent silently mis-scales money. The set is asserted
// verbatim and order-sensitive (mirroring EnumsTest); construction is fail-closed.

it('registers exactly the five launch currencies', function () {
    $values = [];

    foreach (Currency::cases() as $currency) {
        $values[$currency->name] = $currency->value;
    }

    expect($values)->toBe([
        'EUR' => 'EUR',
        'USD' => 'USD',
        'GBP' => 'GBP',
        'CHF' => 'CHF',
        'JPY' => 'JPY',
    ]);
});

it('gives JPY a zero minor-unit exponent', function () {
    expect(Currency::JPY->minorUnitExponent())->toBe(0);
});

it('gives the cent currencies a two-digit minor-unit exponent', function () {
    expect(Currency::EUR->minorUnitExponent())->toBe(2)
        ->and(Currency::USD->minorUnitExponent())->toBe(2)
        ->and(Currency::GBP->minorUnitExponent())->toBe(2)
        ->and(Currency::CHF->minorUnitExponent())->toBe(2);
});

it('treats EUR as the base currency', function () {
    expect(Currency::base())->toBe(Currency::EUR);
});

it('resolves a supported ISO 4217 code via of()', function () {
    expect(Currency::of('EUR'))->toBe(Currency::EUR)
        ->and(Currency::of('JPY'))->toBe(Currency::JPY);
});

it('rejects an unsupported currency code (fail-closed)', function () {
    expect(fn () => Currency::of('XAU'))->toThrow(InvalidArgumentException::class);
});

it('rejects a malformed or non-canonical currency code (fail-closed)', function () {
    expect(fn () => Currency::of('eur'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Currency::of('1.08.42'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Currency::of(''))->toThrow(InvalidArgumentException::class);
});
