<?php

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\SellableSKUActivated;
use App\Modules\Catalog\Events\SellableSKURetired;
use App\Modules\Catalog\Models\SellableSku;
use Tests\TestCase;

// Pins the two Sellable SKU lifecycle events (catalog-lifecycle-approval, task 4.5; design D9; product-catalog
// — Requirement: Product Lifecycle Events). `*Activated` covers the `reviewed → active` step, `*Retired` covers
// `active → retired`; both mirror the `final` NAME/ENTITY_TYPE/static payload() shape of SellableSKUCreated. The
// §14.1 event NAME keeps `SKU` UPPER-case (`SellableSKUActivated`) while the canonical model class is
// `SellableSku` (the §18 naming cascade) — the ENTITY_TYPE is the model class name. A Sellable SKU is a CHILD
// entity with TWO within-module parents — its Product Reference and its Case Configuration both ride the payload
// BY ID (the parents the activation cascade gated on), alongside the entity id and the lifecycle value. The
// descriptive commercial_name belongs to SellableSKUCreated (the identity snapshot), and marketing_copy/version
// are persistence-only — the "minimal snapshot" guard is that none of those three ever leaks into a lifecycle
// event.
//
// Booting the app (TestCase, NO RefreshDatabase/DatabaseMigrations) gives the model its enum casts while
// touching no database: the fixtures are built with factory()->make(), which never persists or queries — the
// scalar product_reference_id/case_configuration_id overrides also stop make() evaluating the factory's nested
// ProductReference::factory()/CaseConfiguration::factory() defaults, so no parent is built and no query runs; the
// absence of a migrated schema is itself the guard that a query would fail loudly.

uses(TestCase::class);

// An in-memory SKU fixture (never saved — make() runs no query) carrying the descriptive commercial_name +
// marketing_copy + the persistence-only version alongside the identity ids, so the payload assertions can prove
// none of those fields leak into a lifecycle event.
$sku = fn (LifecycleState $state): SellableSku => SellableSku::factory()->make([
    'id' => 88,
    'product_reference_id' => 51,
    'case_configuration_id' => 6,
    'commercial_name' => 'Château Margaux 2015 — Magnum (OWC 6)',
    'marketing_copy' => 'A legendary first-growth vintage.',
    'version' => 4,
    'lifecycle_state' => $state,
]);

it('exposes the verbatim SellableSKUActivated contract facets as a final class', function () {
    expect(SellableSKUActivated::NAME)->toBe('SellableSKUActivated')
        ->and(SellableSKUActivated::ENTITY_TYPE)->toBe('SellableSku')
        ->and((new ReflectionClass(SellableSKUActivated::class))->isFinal())->toBeTrue();
});

it('exposes the verbatim SellableSKURetired contract facets as a final class', function () {
    expect(SellableSKURetired::NAME)->toBe('SellableSKURetired')
        ->and(SellableSKURetired::ENTITY_TYPE)->toBe('SellableSku')
        ->and((new ReflectionClass(SellableSKURetired::class))->isFinal())->toBeTrue();
});

it('snapshots exactly the PII-free id + both parents + lifecycle for SellableSKUActivated', function () use ($sku) {
    $payload = SellableSKUActivated::payload($sku(LifecycleState::Active));

    // "Exactly" the four keys, in order, with both within-module parents by id and the post-transition value.
    expect(array_keys($payload))->toBe(['sellable_sku_id', 'product_reference_id', 'case_configuration_id', 'lifecycle_state'])
        ->and($payload)->toBe([
            'sellable_sku_id' => 88,
            'product_reference_id' => 51,
            'case_configuration_id' => 6,
            'lifecycle_state' => 'active',
        ])
        // Minimal transition snapshot: no descriptive prose and no persistence-only field rides a lifecycle event.
        ->and($payload)->not->toHaveKey('commercial_name')
        ->and($payload)->not->toHaveKey('marketing_copy')
        ->and($payload)->not->toHaveKey('version')
        ->and(array_values($payload))->not->toContain('Château Margaux 2015 — Magnum (OWC 6)')
        ->and(array_values($payload))->not->toContain('A legendary first-growth vintage.')
        ->and(array_values($payload))->not->toContain(4);
});

it('snapshots exactly the PII-free id + both parents + lifecycle for SellableSKURetired', function () use ($sku) {
    $payload = SellableSKURetired::payload($sku(LifecycleState::Retired));

    expect(array_keys($payload))->toBe(['sellable_sku_id', 'product_reference_id', 'case_configuration_id', 'lifecycle_state'])
        ->and($payload)->toBe([
            'sellable_sku_id' => 88,
            'product_reference_id' => 51,
            'case_configuration_id' => 6,
            'lifecycle_state' => 'retired',
        ])
        ->and($payload)->not->toHaveKey('commercial_name')
        ->and($payload)->not->toHaveKey('marketing_copy')
        ->and($payload)->not->toHaveKey('version')
        ->and(array_values($payload))->not->toContain('Château Margaux 2015 — Magnum (OWC 6)')
        ->and(array_values($payload))->not->toContain('A legendary first-growth vintage.')
        ->and(array_values($payload))->not->toContain(4);
});
