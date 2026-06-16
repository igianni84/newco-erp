<?php

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\ProductReferenceActivated;
use App\Modules\Catalog\Events\ProductReferenceRetired;
use App\Modules\Catalog\Models\ProductReference;
use Tests\TestCase;

// Pins the two Product Reference lifecycle events (catalog-lifecycle-approval, task 4.4; design D9;
// product-catalog — Requirement: Product Lifecycle Events). `*Activated` covers the `reviewed → active` step,
// `*Retired` covers `active → retired`; both mirror the `final` NAME/ENTITY_TYPE/static payload() shape of
// ProductReferenceCreated. A PR is a CHILD entity with TWO within-module parents — its Product Variant and its
// Format both ride the payload BY ID (the parents the activation cascade gated on), alongside the entity id and
// the lifecycle value. A PR carries no descriptive prose (its identity IS the two-dimension tuple,
// BR-Identity-3), so the only "minimal snapshot" guard is that the persistence-only version field never leaks.
//
// Booting the app (TestCase, NO RefreshDatabase/DatabaseMigrations) gives the model its enum casts while
// touching no database: the fixtures are built with factory()->make(), which never persists or queries — the
// scalar product_variant_id/format_id overrides also stop make() evaluating the factory's nested
// ProductVariant::factory()/Format::factory() defaults, so no parent is built and no query runs; the absence of
// a migrated schema is itself the guard that a query would fail loudly.

uses(TestCase::class);

// An in-memory PR fixture (never saved — make() runs no query) carrying a version alongside the identity ids,
// so the payload assertions can prove that persistence-only field never leaks into a lifecycle event.
$reference = fn (LifecycleState $state): ProductReference => ProductReference::factory()->make([
    'id' => 51,
    'product_variant_id' => 42,
    'format_id' => 9,
    'version' => 7,
    'lifecycle_state' => $state,
]);

it('exposes the verbatim ProductReferenceActivated contract facets as a final class', function () {
    expect(ProductReferenceActivated::NAME)->toBe('ProductReferenceActivated')
        ->and(ProductReferenceActivated::ENTITY_TYPE)->toBe('ProductReference')
        ->and((new ReflectionClass(ProductReferenceActivated::class))->isFinal())->toBeTrue();
});

it('exposes the verbatim ProductReferenceRetired contract facets as a final class', function () {
    expect(ProductReferenceRetired::NAME)->toBe('ProductReferenceRetired')
        ->and(ProductReferenceRetired::ENTITY_TYPE)->toBe('ProductReference')
        ->and((new ReflectionClass(ProductReferenceRetired::class))->isFinal())->toBeTrue();
});

it('snapshots exactly the PII-free id + both parents + lifecycle for ProductReferenceActivated', function () use ($reference) {
    $payload = ProductReferenceActivated::payload($reference(LifecycleState::Active));

    // "Exactly" the four keys, in order, with both within-module parents by id and the post-transition value.
    expect(array_keys($payload))->toBe(['product_reference_id', 'product_variant_id', 'format_id', 'lifecycle_state'])
        ->and($payload)->toBe([
            'product_reference_id' => 51,
            'product_variant_id' => 42,
            'format_id' => 9,
            'lifecycle_state' => 'active',
        ])
        // Minimal transition snapshot: the persistence-only optimistic-lock field never rides a lifecycle event.
        ->and($payload)->not->toHaveKey('version')
        ->and(array_values($payload))->not->toContain(7);
});

it('snapshots exactly the PII-free id + both parents + lifecycle for ProductReferenceRetired', function () use ($reference) {
    $payload = ProductReferenceRetired::payload($reference(LifecycleState::Retired));

    expect(array_keys($payload))->toBe(['product_reference_id', 'product_variant_id', 'format_id', 'lifecycle_state'])
        ->and($payload)->toBe([
            'product_reference_id' => 51,
            'product_variant_id' => 42,
            'format_id' => 9,
            'lifecycle_state' => 'retired',
        ])
        ->and($payload)->not->toHaveKey('version')
        ->and(array_values($payload))->not->toContain(7);
});
