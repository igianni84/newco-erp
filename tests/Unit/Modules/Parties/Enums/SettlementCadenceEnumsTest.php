<?php

use App\Modules\Parties\Enums\SettlementCadence;

// Pins the SettlementCadence enum (parties-module-k-br-guards task 2.1; RM-22 / MVP-DEC-010; ADR
// 2026-07-07-adopt-mvp-dec-010-settlement-cadence-closed-set). The closed three-value settlement-cadence
// domain MVP-DEC-010 fixed from DEC-042's open "e.g." set — quarterly (default), monthly, semi_annual —
// server-enforced at API + DB because the cadence times Module-E settlement + Module-D PO issuance. The
// case/value map is asserted verbatim and order-sensitive (mirroring HoldEnumsTest): any drift in a case or
// its persisted token must fail here first; the excluded `annual`/sub-monthly cadences are the negative path.

it('backs SettlementCadence with the three MVP-DEC-010 cadences', function () {
    $values = [];

    foreach (SettlementCadence::cases() as $cadence) {
        $values[$cadence->name] = $cadence->value;
    }

    // Order-sensitive: quarterly first (the default), then monthly, then semi_annual.
    expect($values)->toBe([
        'Quarterly' => 'quarterly',
        'Monthly' => 'monthly',
        'SemiAnnual' => 'semi_annual',
    ]);

    expect(SettlementCadence::cases())->toHaveCount(3);
});

it('defaults to quarterly (MVP-DEC-010: "quarterly (default)")', function () {
    expect(SettlementCadence::default())->toBe(SettlementCadence::Quarterly);
});

it('round-trips the three spec tokens through from()', function () {
    expect(SettlementCadence::from('quarterly'))->toBe(SettlementCadence::Quarterly)
        ->and(SettlementCadence::from('monthly'))->toBe(SettlementCadence::Monthly)
        ->and(SettlementCadence::from('semi_annual'))->toBe(SettlementCadence::SemiAnnual);
});

it('rejects a cadence outside the closed MVP-DEC-010 set', function () {
    // `annual` is the exact value MVP-DEC-010 excludes (and the old DemoSeeder row, migrated in task 2.1); a
    // sub-monthly `weekly` and the common typo `quaterly` are likewise out of the closed set.
    expect(fn () => SettlementCadence::from('annual'))->toThrow(ValueError::class);
    expect(fn () => SettlementCadence::from('weekly'))->toThrow(ValueError::class);
    expect(fn () => SettlementCadence::from('quaterly'))->toThrow(ValueError::class);
});
