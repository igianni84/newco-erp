<?php

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Enums\ProductType;

// Pins the Catalog spine enums (catalog-product-spine, task 1.1; design D2/D3).
// ProductType is the §16 category-neutral classifier with WINE the sole launch
// value (AC-0-XM-9); LifecycleState is the four-state domain (Module 0 PRD § 4.1),
// stored now but with no transition until catalog-lifecycle-approval. Each
// case/value map is asserted verbatim and order-sensitive, mirroring the platform
// EnumsTest: any drift in a case or its persisted token must fail here first.
//
// ProducerProjectionStatus is added by catalog-lifecycle-approval (task 1.1;
// design D3/D4): the two gate-relevant states of the Catalog-owned producer-state
// read model. Pinned here because the projection's PG CHECK derives from its
// cases() — a silent case drift would silently widen the DB constraint.

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

it('backs ProducerProjectionStatus with the two gate-relevant producer states', function () {
    $values = [];

    foreach (ProducerProjectionStatus::cases() as $status) {
        $values[$status->name] = $status->value;
    }

    // Exactly the two states the consumer is fed (`ProducerActivated`/`ProducerRetired`); `draft`/`reviewed`
    // never reach this read model (design D3). The projection's PG CHECK derives from this set.
    expect($values)->toBe([
        'Active' => 'active',
        'Retired' => 'retired',
    ]);

    expect(ProducerProjectionStatus::cases())->toHaveCount(2);
});

it('rejects a product type outside the launch set', function () {
    expect(fn () => ProductType::from('spirit'))->toThrow(ValueError::class);
});

it('rejects a lifecycle state outside the spec domain', function () {
    expect(fn () => LifecycleState::from('archived'))->toThrow(ValueError::class);
});

it('rejects a producer projection status outside the read-model domain', function () {
    expect(fn () => ProducerProjectionStatus::from('suspended'))->toThrow(ValueError::class);
});
