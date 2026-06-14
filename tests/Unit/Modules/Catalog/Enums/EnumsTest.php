<?php

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProductType;

// Pins the Catalog spine enums (catalog-product-spine, task 1.1; design D2/D3).
// ProductType is the §16 category-neutral classifier with WINE the sole launch
// value (AC-0-XM-9); LifecycleState is the four-state domain (Module 0 PRD § 4.1),
// stored now but with no transition until catalog-lifecycle-approval. Each
// case/value map is asserted verbatim and order-sensitive, mirroring the platform
// EnumsTest: any drift in a case or its persisted token must fail here first.

it('backs ProductType with WINE as the only launch type', function () {
    $values = [];

    foreach (ProductType::cases() as $type) {
        $values[$type->name] = $type->value;
    }

    expect($values)->toBe([
        'Wine' => 'wine',
    ]);

    // The WINE-only-at-launch guard (AC-0-XM-9): a second type must be a
    // deliberate change here, never an accident.
    expect(ProductType::cases())->toHaveCount(1);
});

it('backs LifecycleState with the four spec lifecycle states', function () {
    $values = [];

    foreach (LifecycleState::cases() as $state) {
        $values[$state->name] = $state->value;
    }

    expect($values)->toBe([
        'Draft' => 'draft',
        'Reviewed' => 'reviewed',
        'Active' => 'active',
        'Retired' => 'retired',
    ]);

    expect(LifecycleState::cases())->toHaveCount(4);
});

it('rejects a product type outside the launch set', function () {
    expect(fn () => ProductType::from('spirit'))->toThrow(ValueError::class);
});

it('rejects a lifecycle state outside the spec domain', function () {
    expect(fn () => LifecycleState::from('archived'))->toThrow(ValueError::class);
});
