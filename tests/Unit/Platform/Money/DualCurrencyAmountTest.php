<?php

use App\Platform\Money\Currency;
use App\Platform\Money\DualCurrencyAmount;
use App\Platform\Money\FxRate;
use App\Platform\Money\Money;
use Carbon\CarbonImmutable;

// Pins the DualCurrencyAmount value object (foundations-money-i18n-flags, task 1.4;
// money capability — Requirement: Dual-Currency Amount; design D3). It is the D18
// dual-record bundle every customer-facing financial event carries: the customer
// leg, the EUR-equivalent leg, the locked FxRate and the rate's timestamp,
// serialising to the DEC-169 payload shape. The three delta scenarios are pinned
// below — the exact DEC-169 key/value set, the EUR-leg guard, and verbatim
// rate-preservation for refunds — plus a purity guard proving it carries no FX
// policy (no rate-deriving/snapshot method) and a structural guard that the rate
// timestamp is held immutably. The example is internally consistent (USD 108.42 =
// EUR 100.00 at 1.0842) for readability; the value object computes none of it.

it('serialises to the exact DEC-169 dual-currency payload shape', function () {
    $payload = DualCurrencyAmount::of(
        Money::of(10842, Currency::USD),
        Money::of(10000, Currency::EUR),
        FxRate::of('1.0842'),
        new CarbonImmutable('2026-06-13T00:00:00+00:00'),
    )->toPayload();

    expect($payload)->toBe([
        'amount' => 10842,
        'currency' => 'USD',
        'eur_equivalent_amount' => 10000,
        'fx_rate' => '1.0842',
        'fx_rate_date' => '2026-06-13T00:00:00+00:00',
    ])
        ->and($payload['amount'])->toBeInt()
        ->and($payload['eur_equivalent_amount'])->toBeInt()
        ->and($payload['currency'])->toBeString()
        ->and($payload['fx_rate'])->toBeString();
});

it('rejects a non-EUR currency on the EUR-equivalent leg', function () {
    // The payload records the base leg as eur_equivalent_amount with no currency code
    // (EUR implied), so a non-EUR leg must fail closed rather than silently mis-state
    // the base ledger.
    expect(fn () => DualCurrencyAmount::of(
        Money::of(10842, Currency::USD),
        Money::of(10000, Currency::USD),
        FxRate::of('1.0842'),
        new CarbonImmutable('2026-06-13T00:00:00+00:00'),
    ))->toThrow(InvalidArgumentException::class);
});

it('accepts an EUR customer leg — both legs in EUR is valid', function () {
    // A EUR-denominated purchase has EUR on both legs at rate "1"; the guard rejects
    // only a non-EUR *EUR-equivalent* leg, never a EUR customer leg.
    $payload = DualCurrencyAmount::of(
        Money::of(10000, Currency::EUR),
        Money::of(10000, Currency::EUR),
        FxRate::of('1'),
        new CarbonImmutable('2026-06-13T00:00:00+00:00'),
    )->toPayload();

    expect($payload['currency'])->toBe('EUR')
        ->and($payload['eur_equivalent_amount'])->toBe(10000);
});

it('preserves the locked rate verbatim for a refund — never re-deriving it', function () {
    $rate = FxRate::of('1.0842');

    $dca = DualCurrencyAmount::of(
        Money::of(10842, Currency::USD),
        Money::of(10000, Currency::EUR),
        $rate,
        new CarbonImmutable('2026-06-13T00:00:00+00:00'),
    );

    // Read back later (e.g. for a refund): the same locked rate, bit-for-bit, with no
    // fresh derivation.
    expect($dca->fxRate)->toBe($rate)
        ->and((string) $dca->fxRate)->toBe('1.0842')
        ->and($dca->toPayload()['fx_rate'])->toBe('1.0842');
});

it('carries no FX policy — exposes no rate-deriving or snapshot method (pure representation)', function () {
    // Guard the public method surface to exactly construction + serialisation: any
    // deriveRate()/convert()/snapshot()/refresh()-style method would be FX policy that
    // belongs to Module E (design D3 landmine), so the allow-list must stay this small.
    $methods = get_class_methods(DualCurrencyAmount::class);
    sort($methods);

    expect($methods)->toBe(['of', 'toPayload']);
});

it('holds the rate timestamp immutably — of() takes a non-nullable DateTimeImmutable', function () {
    // The bundle is immutable, so the date leg is typed DateTimeImmutable (a
    // CarbonImmutable satisfies it; a mutable Carbon/DateTime is rejected at the
    // boundary) — asserted structurally so the immutability guarantee is non-vacuous.
    $type = (new ReflectionMethod(DualCurrencyAmount::class, 'of'))->getParameters()[3]->getType();

    expect($type)->toBeInstanceOf(ReflectionNamedType::class);
    assert($type instanceof ReflectionNamedType); // narrow ReflectionType|null for static analysis

    expect($type->getName())->toBe('DateTimeImmutable')
        ->and($type->allowsNull())->toBeFalse();
});
