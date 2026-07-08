<?php

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\EnrichmentDataUpdated;
use App\Modules\Catalog\Models\ProductVariant;
use Tests\TestCase;

// Pins the 22nd — and only non-lifecycle — catalog domain event (catalog-module-0-completeness-sweep task 4.1;
// design D11; product-catalog — Requirement: Enrichment Data Update; AC-0-EVT-8). It mirrors its twenty-one
// siblings' `final` NAME/ENTITY_TYPE/static payload() shape, and differs from every one of them in what it
// means: not a `lifecycle_state` transition, but a change to data that lives outside the lifecycle entirely.
//
// The payload is a bare REFERENCE. The values that moved are prose (and, later, adapter-fed critic scores and
// market data): they belong to the audit record's before/after, never to an event replayed for ten years across
// module boundaries. That is the D11 PII-free floor, and it is also what keeps the event field-agnostic.
//
// Booting the app (TestCase, NO RefreshDatabase/DatabaseMigrations) gives the model its enum casts while touching
// no database: the fixture is built with factory()->make(), which never persists or queries (nor fires the
// factory's afterCreating wine-attribute hook) — the absence of a migrated schema is itself the guard that a
// query would fail loudly.

uses(TestCase::class);

// An in-memory Variant fixture (never saved — make() runs no query) carrying its descriptive variant_identifier
// alongside the ids, so the payload assertion can prove no descriptor leaks into the event. The explicit
// product_master_id override means make() does NOT evaluate the factory's ProductMaster::factory() default.
$enrichedVariant = fn (): ProductVariant => ProductVariant::factory()->make([
    'id' => 42,
    'product_master_id' => 7,
    'variant_identifier' => '2015',
    'lifecycle_state' => LifecycleState::Active,
]);

it('exposes the verbatim EnrichmentDataUpdated contract facets as a final class', function () {
    // The §14.1 name, unchanged by the §16 category-neutral generalisation (it never named a wine).
    expect(EnrichmentDataUpdated::NAME)->toBe('EnrichmentDataUpdated')
        ->and(EnrichmentDataUpdated::ENTITY_TYPE)->toBe('ProductVariant')
        ->and((new ReflectionClass(EnrichmentDataUpdated::class))->isFinal())->toBeTrue();
});

it('snapshots exactly the PII-free Variant reference and nothing else', function () use ($enrichedVariant) {
    $payload = EnrichmentDataUpdated::payload($enrichedVariant());

    // EXACTLY one key: the enrichment values themselves never ride the event (design D11). Not the prose that
    // moved, not the descriptive variant axis, not the lifecycle value — this event says only "it changed".
    expect(array_keys($payload))->toBe(['product_variant_id'])
        ->and($payload)->toBe(['product_variant_id' => 42])
        ->and($payload)->not->toHaveKey('tasting_notes')
        ->and($payload)->not->toHaveKey('lifecycle_state')
        ->and(array_values($payload))->not->toContain('2015');
});

it('is the single non-lifecycle member of a catalog event surface of twenty-two', function () {
    $classes = array_map(
        fn (string $path): string => basename($path, '.php'),
        glob(app_path('Modules/Catalog/Events/*.php')) ?: [],
    );

    // The surface stays CLOSED at the 21 lifecycle events (7 spine entities × Created/Activated/Retired) plus
    // this one (design D2 — a content edit records no domain event). A new event class reds this pin, which is
    // the point: the event surface is the inter-module API, not an implementation detail.
    $lifecycleEvents = array_values(array_filter(
        $classes,
        fn (string $class): bool => str_ends_with($class, 'Created')
            || str_ends_with($class, 'Activated')
            || str_ends_with($class, 'Retired'),
    ));

    expect($classes)->toHaveCount(22)
        ->and($classes)->toContain('EnrichmentDataUpdated')
        ->and($lifecycleEvents)->toHaveCount(21)
        ->and($lifecycleEvents)->not->toContain('EnrichmentDataUpdated');
});
