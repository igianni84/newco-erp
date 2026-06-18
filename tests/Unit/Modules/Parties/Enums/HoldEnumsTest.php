<?php

use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;

// Pins the parties-holds enums (parties-holds, task 1.1; design L1/L2).
// The three Hold value domains: HoldType is the six-value unified Hold-type domain
// (Module K PRD § 4.8) carrying the autoLiftable() predicate (the per-type lift
// discipline — DEC-160 / AC-K-FSM-11; ADR 2026-06-18); HoldScope is the three-value
// scope domain (customer/account/profile, § 4.8); HoldStatus is the two-state Hold
// lifecycle (active/lifted, § 4.8). Each case/value map is asserted verbatim and
// order-sensitive, mirroring ComplianceEnumsTest: any drift in a case or its
// persisted token must fail here first.

it('backs HoldType with the six spec Hold types', function () {
    $values = [];

    foreach (HoldType::cases() as $type) {
        $values[$type->name] = $type->value;
    }

    expect($values)->toBe([
        'Admin' => 'admin',
        'Kyc' => 'kyc',
        'Payment' => 'payment',
        'Fraud' => 'fraud',
        'Compliance' => 'compliance',
        'Credit' => 'credit',
    ]);

    expect(HoldType::cases())->toHaveCount(6);
});

it('backs HoldScope with the three spec scopes', function () {
    $values = [];

    foreach (HoldScope::cases() as $scope) {
        $values[$scope->name] = $scope->value;
    }

    expect($values)->toBe([
        'Customer' => 'customer',
        'Account' => 'account',
        'Profile' => 'profile',
    ]);

    expect(HoldScope::cases())->toHaveCount(3);
});

it('backs HoldStatus with the two spec lifecycle states', function () {
    $values = [];

    foreach (HoldStatus::cases() as $status) {
        $values[$status->name] = $status->value;
    }

    expect($values)->toBe([
        'Active' => 'active',
        'Lifted' => 'lifted',
    ]);

    expect(HoldStatus::cases())->toHaveCount(2);
});

it('round-trips the spec tokens through from()', function () {
    expect(HoldType::from('admin'))->toBe(HoldType::Admin);
    expect(HoldType::from('credit'))->toBe(HoldType::Credit);
    expect(HoldScope::from('profile'))->toBe(HoldScope::Profile);
    expect(HoldStatus::from('lifted'))->toBe(HoldStatus::Lifted);
});

it('marks only kyc and payment Hold types auto-liftable', function () {
    // The per-type lift-discipline truth table (design L2; DEC-160 / AC-K-FSM-11;
    // ADR 2026-06-18). kyc + payment are system-managed (auto-lift on the clearing
    // signal); admin/fraud/compliance/credit are operator-lift-only.
    expect(HoldType::Kyc->autoLiftable())->toBeTrue();
    expect(HoldType::Payment->autoLiftable())->toBeTrue();
    expect(HoldType::Admin->autoLiftable())->toBeFalse();
    expect(HoldType::Fraud->autoLiftable())->toBeFalse();
    expect(HoldType::Compliance->autoLiftable())->toBeFalse();
    expect(HoldType::Credit->autoLiftable())->toBeFalse();
});

it('rejects a Hold type outside the spec domain', function () {
    // `suspended` is an Account status, not a Hold type — and `expired` is a deferred,
    // under-specified state, not a launch case.
    expect(fn () => HoldType::from('expired'))->toThrow(ValueError::class);
});

it('rejects a Hold scope outside the spec domain', function () {
    // Producer/Club are supply-side parties, never a Hold scope (§ 4.8).
    expect(fn () => HoldScope::from('producer'))->toThrow(ValueError::class);
});

it('rejects a Hold status outside the spec domain', function () {
    // `expired` is a deferred automation seam, not an active launch state.
    expect(fn () => HoldStatus::from('expired'))->toThrow(ValueError::class);
});
