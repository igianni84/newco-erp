<?php

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Events\ProductMasterActivated;
use App\Modules\Catalog\Events\ProductMasterRetired;
use App\Modules\Catalog\Models\ProductMaster;
use Tests\TestCase;

// Pins the two Product Master lifecycle events (catalog-lifecycle-approval, task 3.1; design D9;
// product-catalog — Requirement: Product Lifecycle Events). `*Activated` covers the `reviewed → active`
// step, `*Retired` covers `active → retired`; both mirror the `final` NAME/ENTITY_TYPE/static payload()
// shape of ProductMasterCreated, and both payloads are PII-free — entity ids + the lifecycle value only,
// the producer referenced BY ID (invariant 10 & the substrate's payload discipline).
//
// Booting the app (TestCase, NO RefreshDatabase/DatabaseMigrations) gives the model its enum casts while
// touching no database: the fixtures are built with factory()->make(), which never persists or queries —
// the absence of a migrated schema is itself the guard that a query would fail loudly.

uses(TestCase::class);

// An in-memory Master fixture (never saved — make() runs no query) carrying a descriptive `name` alongside
// the structural ids, so the payload assertions can prove that descriptive field never leaks into a
// lifecycle event.
$master = fn (LifecycleState $state): ProductMaster => ProductMaster::factory()->make([
    'id' => 42,
    'name' => 'Château Margaux',
    'product_type' => ProductType::Wine,
    'producer_id' => 7,
    'lifecycle_state' => $state,
]);

it('exposes the verbatim ProductMasterActivated contract facets as a final class', function () {
    expect(ProductMasterActivated::NAME)->toBe('ProductMasterActivated')
        ->and(ProductMasterActivated::ENTITY_TYPE)->toBe('ProductMaster')
        ->and((new ReflectionClass(ProductMasterActivated::class))->isFinal())->toBeTrue();
});

it('exposes the verbatim ProductMasterRetired contract facets as a final class', function () {
    expect(ProductMasterRetired::NAME)->toBe('ProductMasterRetired')
        ->and(ProductMasterRetired::ENTITY_TYPE)->toBe('ProductMaster')
        ->and((new ReflectionClass(ProductMasterRetired::class))->isFinal())->toBeTrue();
});

it('snapshots exactly the PII-free id triple for ProductMasterActivated', function () use ($master) {
    $payload = ProductMasterActivated::payload($master(LifecycleState::Active));

    // "Exactly" the three keys, in order, with the producer BY ID and the post-transition lifecycle value.
    expect(array_keys($payload))->toBe(['product_master_id', 'producer_id', 'lifecycle_state'])
        ->and($payload)->toBe([
            'product_master_id' => 42,
            'producer_id' => 7,
            'lifecycle_state' => 'active',
        ])
        // PII-free: no descriptive core, no expanded party — only ids + the enum value.
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('product_type')
        ->and($payload)->not->toHaveKey('producer')
        ->and(array_values($payload))->not->toContain('Château Margaux');
});

it('snapshots exactly the PII-free id triple for ProductMasterRetired', function () use ($master) {
    $payload = ProductMasterRetired::payload($master(LifecycleState::Retired));

    expect(array_keys($payload))->toBe(['product_master_id', 'producer_id', 'lifecycle_state'])
        ->and($payload)->toBe([
            'product_master_id' => 42,
            'producer_id' => 7,
            'lifecycle_state' => 'retired',
        ])
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('product_type')
        ->and($payload)->not->toHaveKey('producer')
        ->and(array_values($payload))->not->toContain('Château Margaux');
});
