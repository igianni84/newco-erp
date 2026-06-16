<?php

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\CompositeSKUActivated;
use App\Modules\Catalog\Events\CompositeSKURetired;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductReference;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

// Pins the two Composite SKU lifecycle events (catalog-lifecycle-approval, task 4.6; design D9; product-catalog
// — Requirement: Product Lifecycle Events). `*Activated` covers the `reviewed → active` step, `*Retired` covers
// `active → retired`; both mirror the `final` NAME/ENTITY_TYPE/static payload() shape of CompositeSKUCreated. The
// §14.1 event NAME keeps `SKU` UPPER-case (`CompositeSKUActivated`) while the canonical model class is
// `CompositeSku` (the §18 naming cascade) — the ENTITY_TYPE is the model class name. A Composite SKU is an
// N-constituent CHILD: its ordered constituent Product Reference ids ride the payload (the parents the activation
// cascade gated on), alongside the entity id and the lifecycle value. The "lean transition snapshot" guard is
// twofold: the persistence-only `version` never leaks, AND the `constituent_count` that CompositeSKUCreated
// carries is intentionally omitted from a transition event (a creation-event convenience, derivable as the count
// of the id list — kept consistent with the other twelve `*Activated`/`*Retired` events, none of which carry a
// derived enrichment).
//
// Booting the app (TestCase, NO RefreshDatabase/DatabaseMigrations) gives the models their enum casts while
// touching no database: the parent is built with factory()->make() (never persists or queries — and make() does
// NOT fire the factory's afterCreating constituent attach), and the constituents are set in-memory with
// setRelation() over ProductReference fixtures whose scalar product_variant_id/format_id overrides stop make()
// evaluating the nested factory defaults — so no query runs; the absence of a migrated schema is itself the guard
// that a query would fail loudly.

uses(TestCase::class);

// An in-memory Composite SKU fixture (never saved — make() runs no query) carrying the persistence-only version
// alongside the identity id, with its ordered two-constituent set wired via setRelation, so the payload
// assertions can prove the constituent ids ride in bundle order while version never leaks.
$composite = function (LifecycleState $state): CompositeSku {
    $sku = CompositeSku::factory()->make([
        'id' => 70,
        'version' => 9,
        'lifecycle_state' => $state,
    ]);

    $sku->setRelation('constituents', new EloquentCollection([
        ProductReference::factory()->make(['id' => 51, 'product_variant_id' => 901, 'format_id' => 902]),
        ProductReference::factory()->make(['id' => 52, 'product_variant_id' => 903, 'format_id' => 904]),
    ]));

    return $sku;
};

it('exposes the verbatim CompositeSKUActivated contract facets as a final class', function () {
    expect(CompositeSKUActivated::NAME)->toBe('CompositeSKUActivated')
        ->and(CompositeSKUActivated::ENTITY_TYPE)->toBe('CompositeSku')
        ->and((new ReflectionClass(CompositeSKUActivated::class))->isFinal())->toBeTrue();
});

it('exposes the verbatim CompositeSKURetired contract facets as a final class', function () {
    expect(CompositeSKURetired::NAME)->toBe('CompositeSKURetired')
        ->and(CompositeSKURetired::ENTITY_TYPE)->toBe('CompositeSku')
        ->and((new ReflectionClass(CompositeSKURetired::class))->isFinal())->toBeTrue();
});

it('snapshots exactly the PII-free id + ordered constituents + lifecycle for CompositeSKUActivated', function () use ($composite) {
    $payload = CompositeSKUActivated::payload($composite(LifecycleState::Active));

    // "Exactly" the three keys, in order, with the ordered constituent ids and the post-transition value.
    expect(array_keys($payload))->toBe(['composite_sku_id', 'constituent_product_reference_ids', 'lifecycle_state'])
        ->and($payload)->toBe([
            'composite_sku_id' => 70,
            'constituent_product_reference_ids' => [51, 52],
            'lifecycle_state' => 'active',
        ])
        // Lean transition snapshot: the *Created `constituent_count` convenience is omitted, and no
        // persistence-only field rides a lifecycle event.
        ->and($payload)->not->toHaveKey('constituent_count')
        ->and($payload)->not->toHaveKey('version')
        ->and(array_values($payload))->not->toContain(9);
});

it('snapshots exactly the PII-free id + ordered constituents + lifecycle for CompositeSKURetired', function () use ($composite) {
    $payload = CompositeSKURetired::payload($composite(LifecycleState::Retired));

    expect(array_keys($payload))->toBe(['composite_sku_id', 'constituent_product_reference_ids', 'lifecycle_state'])
        ->and($payload)->toBe([
            'composite_sku_id' => 70,
            'constituent_product_reference_ids' => [51, 52],
            'lifecycle_state' => 'retired',
        ])
        ->and($payload)->not->toHaveKey('constituent_count')
        ->and($payload)->not->toHaveKey('version')
        ->and(array_values($payload))->not->toContain(9);
});
