<?php

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\ProductVariantActivated;
use App\Modules\Catalog\Events\ProductVariantRetired;
use App\Modules\Catalog\Models\ProductVariant;
use Tests\TestCase;

// Pins the two Product Variant lifecycle events (catalog-lifecycle-approval, task 4.3; design D9;
// product-catalog — Requirement: Product Lifecycle Events). `*Activated` covers the `reviewed → active` step,
// `*Retired` covers `active → retired`; both mirror the `final` NAME/ENTITY_TYPE/static payload() shape of
// ProductVariantCreated. A Variant is the FIRST CHILD entity — its single parent Product Master rides the
// payload BY ID (the within-module parent the activation cascade gated on), alongside the entity id and the
// lifecycle value; the descriptive variant_identifier / wine attribute set belong to the creation record,
// never a transition snapshot.
//
// Booting the app (TestCase, NO RefreshDatabase/DatabaseMigrations) gives the model its enum casts while
// touching no database: the fixtures are built with factory()->make(), which never persists or queries (nor
// fires the factory's afterCreating wine-attribute hook) — the absence of a migrated schema is itself the
// guard that a query would fail loudly.

uses(TestCase::class);

// An in-memory Variant fixture (never saved — make() runs no query) carrying its descriptive variant_identifier
// alongside the ids, so the payload assertions can prove that descriptor never leaks into a lifecycle event.
// The explicit product_master_id override means make() does NOT evaluate the factory's ProductMaster::factory()
// default — no parent is built, no query is run.
$variant = fn (LifecycleState $state): ProductVariant => ProductVariant::factory()->make([
    'id' => 42,
    'product_master_id' => 7,
    'variant_identifier' => '2015',
    'lifecycle_state' => $state,
]);

it('exposes the verbatim ProductVariantActivated contract facets as a final class', function () {
    expect(ProductVariantActivated::NAME)->toBe('ProductVariantActivated')
        ->and(ProductVariantActivated::ENTITY_TYPE)->toBe('ProductVariant')
        ->and((new ReflectionClass(ProductVariantActivated::class))->isFinal())->toBeTrue();
});

it('exposes the verbatim ProductVariantRetired contract facets as a final class', function () {
    expect(ProductVariantRetired::NAME)->toBe('ProductVariantRetired')
        ->and(ProductVariantRetired::ENTITY_TYPE)->toBe('ProductVariant')
        ->and((new ReflectionClass(ProductVariantRetired::class))->isFinal())->toBeTrue();
});

it('snapshots exactly the PII-free id + parent + lifecycle triple for ProductVariantActivated', function () use ($variant) {
    $payload = ProductVariantActivated::payload($variant(LifecycleState::Active));

    // "Exactly" the three keys, in order, with the parent Master by id and the post-transition lifecycle value.
    expect(array_keys($payload))->toBe(['product_variant_id', 'product_master_id', 'lifecycle_state'])
        ->and($payload)->toBe([
            'product_variant_id' => 42,
            'product_master_id' => 7,
            'lifecycle_state' => 'active',
        ])
        // Minimal transition snapshot: the descriptive variant axis never rides a lifecycle event.
        ->and($payload)->not->toHaveKey('variant_identifier')
        ->and(array_values($payload))->not->toContain('2015');
});

it('snapshots exactly the PII-free id + parent + lifecycle triple for ProductVariantRetired', function () use ($variant) {
    $payload = ProductVariantRetired::payload($variant(LifecycleState::Retired));

    expect(array_keys($payload))->toBe(['product_variant_id', 'product_master_id', 'lifecycle_state'])
        ->and($payload)->toBe([
            'product_variant_id' => 42,
            'product_master_id' => 7,
            'lifecycle_state' => 'retired',
        ])
        ->and($payload)->not->toHaveKey('variant_identifier')
        ->and(array_values($payload))->not->toContain('2015');
});
