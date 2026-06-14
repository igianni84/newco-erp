<?php

use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\ProductVariantCreated;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductVariant;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\I18n\TranslatableText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Product Variant — a release of a Product Master, the second MULTI-TABLE spine entity
 * (catalog-product-spine task 3.2; design D1/D5/D8; product-catalog — Requirement: Product Variant, Spine
 * Creation Events). It proves the CreateProductVariant action persists the neutral core + the 1:1 `WINE`
 * attribute set in `draft` under a single parent Master, records ProductVariantCreated through the platform
 * recorder in the SAME transaction (PII-free, parent Master by id), keeps the WINE vintage OFF the neutral
 * core (AC-0-GEN-3 — the type-neutral variant axis), structurally enforces the single-parent invariant
 * (BR-Identity-2 via the single FK + the within-module `belongsTo`), and holds the scope guard (no transition
 * out of `draft`).
 *
 * RefreshDatabase (per the task hint): the action opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint even under the wrapper. Portability: tasting
 * notes are read back THROUGH the TranslatableTextCast and the event payload BY KEY — never a byte-compare of
 * stored JSON (PG jsonb reorders keys — knowledge/testing trap 3).
 */
uses(RefreshDatabase::class);

it('creates a WINE Product Variant in draft under a Master with its neutral core and 1:1 wine attribute set', function () {
    $master = ProductMaster::factory()->create();

    $variant = app(CreateProductVariant::class)->handle(
        productMasterId: $master->id,
        variantIdentifier: '2015',
        vintageYear: 2015,
        tastingNotes: TranslatableText::of(['en' => 'Cassis and graphite.']),
    );

    // Re-fetch so the assertions exercise the read/hydration casts, not the in-memory create() values.
    $read = ProductVariant::findOrFail($variant->id);

    expect($read->product_master_id)->toBe($master->id)
        ->and($read->variant_identifier)->toBe('2015')
        ->and($read->lifecycle_state)->toBe(LifecycleState::Draft)  // born draft (design D3)
        ->and($read->version)->toBe(1);                             // §4.8 version floor, born at 1

    // the WINE attributes live 1:1 OFF the neutral core (design D1). sole() = exactly one attribute row.
    $wine = $read->wineAttributes()->sole();

    expect($wine->vintage_year)->toBe(2015)
        ->and($wine->non_vintage)->toBeFalse()
        ->and($wine->tasting_notes?->resolve('en'))->toBe('Cassis and graphite.');
});

it('records a ProductVariantCreated domain event in the same transaction, tagged catalog and PII-free', function () {
    $master = ProductMaster::factory()->create();

    $variant = app(CreateProductVariant::class)->handle(
        productMasterId: $master->id,
        variantIdentifier: '2018',
        vintageYear: 2018,
    );

    // sole() asserts EXACTLY one ProductVariantCreated row exists — the one-event contract.
    $event = DomainEvent::query()->where('name', ProductVariantCreated::NAME)->sole();

    expect($event->module)->toBe('catalog')                   // Module::Catalog->value
        ->and($event->entity_type)->toBe('ProductVariant')
        ->and($event->entity_id)->toBe((string) $variant->id) // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);   // the ActorContext seam default

    // Payload asserted BY KEY through the array cast (trap 3); PII-free — parent Master by id, no party data.
    expect($event->payload['product_variant_id'])->toBe($variant->id)
        ->and($event->payload['product_master_id'])->toBe($master->id)
        ->and($event->payload['variant_identifier'])->toBe('2018')
        ->and($event->payload['lifecycle_state'])->toBe('draft');

    // The neutral-core event contract restates no wine attribute (the vintage lives on the per-type table).
    expect($event->payload)->not->toHaveKey('vintage_year')
        ->and($event->payload)->not->toHaveKey('tasting_notes');
});

it('holds no wine-specific attribute on the neutral core — the vintage lives in the WINE attribute set (AC-0-GEN-3)', function () {
    $master = ProductMaster::factory()->create();

    app(CreateProductVariant::class)->handle(
        productMasterId: $master->id,
        variantIdentifier: '2019',
        vintageYear: 2019,
    );

    // AC-0-GEN-3 / the type-neutral-axis scenario: the core does not hard-name a wine-only "vintage" column.
    foreach (['vintage_year', 'non_vintage', 'tasting_notes'] as $wineColumn) {
        expect(Schema::hasColumn('catalog_product_variants', $wineColumn))->toBeFalse();
    }

    // No neutral-core column carries a wine-specific concept as a substring (catches a renamed-but-present
    // column) — the strongest leg of the absence guard.
    $columns = Schema::getColumnListing('catalog_product_variants');

    foreach ($columns as $column) {
        foreach (['vintage', 'tasting'] as $concept) {
            expect($column)->not->toContain($concept);
        }
    }

    // They DO live on the WINE attribute set (the per-type table).
    expect(Schema::hasColumn('catalog_product_variant_wine_attributes', 'vintage_year'))->toBeTrue()
        ->and(Schema::hasColumn('catalog_product_variant_wine_attributes', 'non_vintage'))->toBeTrue()
        ->and(Schema::hasColumn('catalog_product_variant_wine_attributes', 'tasting_notes'))->toBeTrue();
});

it('belongs to exactly one Master — the single-parent FK resolves through the within-module belongsTo (BR-Identity-2)', function () {
    $master = ProductMaster::factory()->create();

    $variant = app(CreateProductVariant::class)->handle(
        productMasterId: $master->id,
        variantIdentifier: '2016',
        vintageYear: 2016,
    );

    // the within-module belongsTo resolves to the one parent Master (sole() = exactly one, non-null).
    $parent = $variant->master()->sole();

    expect($parent->id)->toBe($master->id)
        ->and($parent)->toBeInstanceOf(ProductMaster::class);

    // structurally single-parent: the neutral core carries exactly ONE parent reference (`product_master_id`)
    // and no second parent axis — asserted as the exact neutral-core column set (sorted: order-independent and
    // cross-engine stable). A Variant cannot reference two Masters.
    $columns = Schema::getColumnListing('catalog_product_variants');
    sort($columns);

    expect($columns)->toBe([
        'created_at', 'id', 'lifecycle_state', 'product_master_id', 'updated_at', 'variant_identifier', 'version',
    ]);
});

it('supports a non-vintage marker — vintage_year null with the non_vintage flag set (Wine vintage scenario)', function () {
    $master = ProductMaster::factory()->create();

    $variant = app(CreateProductVariant::class)->handle(
        productMasterId: $master->id,
        variantIdentifier: 'NV',
        vintageYear: null,
        nonVintage: true,
    );

    // The vintage axis (year or non-vintage marker) lives in the WINE attribute set, never on the core.
    $wine = ProductVariant::findOrFail($variant->id)->wineAttributes()->sole();

    expect($wine->vintage_year)->toBeNull()
        ->and($wine->non_vintage)->toBeTrue();
});

it('resolves the tasting notes with per-attribute English fallback (assert through the cast — trap 3)', function () {
    $master = ProductMaster::factory()->create();

    $variant = app(CreateProductVariant::class)->handle(
        productMasterId: $master->id,
        variantIdentifier: '2010',
        vintageYear: 2010,
        tastingNotes: TranslatableText::of([
            'en' => 'Cedar, tobacco, dark fruit.',
            'fr' => 'Cèdre, tabac, fruits noirs.',
        ]),
    );

    // Re-fetch so the read exercises the TranslatableTextCast (JSON column → TranslatableText), never a
    // byte-compare of the stored JSON (PG jsonb reorders keys).
    $wine = ProductVariant::findOrFail($variant->id)->wineAttributes()->sole();

    expect($wine->tasting_notes)->toBeInstanceOf(TranslatableText::class)
        ->and($wine->tasting_notes?->resolve('fr'))->toBe('Cèdre, tabac, fruits noirs.')  // exact locale
        ->and($wine->tasting_notes?->resolve('it'))->toBe('Cedar, tobacco, dark fruit.')  // absent → English fallback
        ->and($wine->tasting_notes?->resolve('en'))->toBe('Cedar, tobacco, dark fruit.');
});

it('records no lifecycle-transition event — the Variant stays draft (scope guard)', function () {
    $master = ProductMaster::factory()->create();

    $variant = app(CreateProductVariant::class)->handle(
        productMasterId: $master->id,
        variantIdentifier: '2012',
        vintageYear: 2012,
    );

    // Design D3 scope guard: only the *Created event exists — never an *Activated/*Retired (the deferred
    // catalog-lifecycle-approval change owns those).
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Draft);
});

it('produces a draft Variant with its wine attribute set via the factory without recording an event', function () {
    // The factory is a pure fixture: it bypasses the action, so it persists a draft Variant + its 1:1 wine
    // attributes (and a parent Master) but records no ProductVariantCreated (3.3 leans on it for a parent).
    $variant = ProductVariant::factory()->create();

    expect($variant->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($variant->version)->toBe(1)
        ->and($variant->master()->sole())->toBeInstanceOf(ProductMaster::class)        // within-module parent attached
        ->and($variant->wineAttributes()->sole()->vintage_year)->not->toBeNull()       // the 1:1 attrs were attached
        ->and(DomainEvent::query()->count())->toBe(0);                                  // neither factory records an event
});
