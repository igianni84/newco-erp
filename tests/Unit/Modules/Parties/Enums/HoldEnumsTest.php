<?php

use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;

// Pins the parties-holds enums (parties-holds, task 1.1; design L1/L2).
// The three Hold value domains: HoldType is the eight-value unified Hold-type domain
// (Module K PRD § 4.8 prose + the two finance-driven types §4.8.1/§15.8 names — canon
// DEC-008; ADR 2026-07-01-adopt-dec-008-hold-types-8) carrying the autoLiftable()
// predicate (the per-type lift discipline — DEC-160 / AC-K-FSM-11; ADR 2026-06-18);
// HoldScope is the three-value scope domain (customer/account/profile, § 4.8); HoldStatus
// is the two-state Hold lifecycle (active/lifted, § 4.8). Each case/value map is asserted
// verbatim and order-sensitive, mirroring ComplianceEnumsTest: any drift in a case or its
// persisted token must fail here first.

it('backs HoldType with the eight spec Hold types', function () {
    $values = [];

    foreach (HoldType::cases() as $type) {
        $values[$type->name] = $type->value;
    }

    // The six § 4.8 types + the two finance-driven types §4.8.1/§15.8 name and canon DEC-008
    // adds (chargeback_review, storage_payment_failed). Order-sensitive: the two new types
    // are appended last (Module E consumes them; ADR 2026-07-01-adopt-dec-008-hold-types-8).
    expect($values)->toBe([
        'Admin' => 'admin',
        'Kyc' => 'kyc',
        'Payment' => 'payment',
        'Fraud' => 'fraud',
        'Compliance' => 'compliance',
        'Credit' => 'credit',
        'ChargebackReview' => 'chargeback_review',
        'StoragePaymentFailed' => 'storage_payment_failed',
    ]);

    expect(HoldType::cases())->toHaveCount(8);
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
    // ADR 2026-06-18 + ADR 2026-07-01-adopt-dec-008-hold-types-8). kyc + payment are
    // system-managed (auto-lift on the clearing signal); admin/fraud/compliance/credit
    // AND the two DEC-008 finance-driven types (chargeback_review, storage_payment_failed)
    // are operator-lift-only — auto-lift stays a two-type property, operator-lift-only 4 → 6.
    expect(HoldType::Kyc->autoLiftable())->toBeTrue();
    expect(HoldType::Payment->autoLiftable())->toBeTrue();
    expect(HoldType::Admin->autoLiftable())->toBeFalse();
    expect(HoldType::Fraud->autoLiftable())->toBeFalse();
    expect(HoldType::Compliance->autoLiftable())->toBeFalse();
    expect(HoldType::Credit->autoLiftable())->toBeFalse();
    expect(HoldType::ChargebackReview->autoLiftable())->toBeFalse();
    expect(HoldType::StoragePaymentFailed->autoLiftable())->toBeFalse();
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
