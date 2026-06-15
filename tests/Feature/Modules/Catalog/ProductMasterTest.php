<?php

use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Events\ProductMasterCreated;
use App\Modules\Catalog\Exceptions\DuplicateProductMasterIdentity;
use App\Modules\Catalog\Exceptions\UnsupportedProductType;
use App\Modules\Catalog\Models\ProductMaster;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\I18n\TranslatableText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Product Master — the first MULTI-TABLE spine entity and the dedup gate (catalog-product-spine
 * task 3.1; design D1/D2/D4/D6/D8; product-catalog — Requirement: Product Master, Category-Neutral Product
 * Type, Spine Creation Events). It proves the CreateProductMaster action persists the neutral core + the
 * 1:1 `WINE` attribute set in `draft`, records ProductMasterCreated through the platform recorder in the
 * SAME transaction (PII-free, producer by id), enforces the BR-Identity-1 dedup (rejecting non-retired
 * collisions only) and the fail-closed WINE-only-at-launch guard, keeps wine-specific attributes OFF the
 * neutral core (AC-0-GEN-2), and holds the scope guard (no transition out of `draft`).
 *
 * RefreshDatabase (per the task hint): the action opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint even under the wrapper. Portability: the
 * winery story is read back THROUGH the TranslatableTextCast and the event payload BY KEY — never a
 * byte-compare of stored JSON (PG jsonb reorders keys — knowledge/testing trap 3).
 */
uses(RefreshDatabase::class);

it('creates a WINE Product Master in draft with its neutral core and 1:1 wine attribute set', function () {
    $master = app(CreateProductMaster::class)->handle(
        name: 'Château Margaux',
        producerId: 42,
        appellation: 'Margaux',
        region: 'Bordeaux',
        wineryStory: TranslatableText::of(['en' => 'A First Growth estate.']),
    );

    // Re-fetch so the assertions exercise the read/hydration casts, not the in-memory create() values.
    $read = ProductMaster::findOrFail($master->id);

    expect($read->name)->toBe('Château Margaux')
        ->and($read->product_type)->toBe(ProductType::Wine)
        ->and($read->producer_id)->toBe(42)                  // producer captured as a BARE id (no relation)
        ->and($read->lifecycle_state)->toBe(LifecycleState::Draft)  // born draft (design D3)
        ->and($read->version)->toBe(1);                      // §4.8 version floor, born at 1

    // the WINE attributes live 1:1 OFF the neutral core (design D1). sole() = exactly one attribute row.
    $wine = $read->wineAttributes()->sole();

    expect($wine->appellation)->toBe('Margaux')
        ->and($wine->region)->toBe('Bordeaux')
        ->and($wine->winery_story?->resolve('en'))->toBe('A First Growth estate.');
});

it('records a ProductMasterCreated domain event in the same transaction, tagged catalog and PII-free', function () {
    $master = app(CreateProductMaster::class)->handle(
        name: 'Penfolds Grange',
        producerId: 88,
        appellation: 'Barossa Valley',
        region: 'South Australia',
    );

    // sole() asserts EXACTLY one ProductMasterCreated row exists — the one-event contract.
    $event = DomainEvent::query()->where('name', ProductMasterCreated::NAME)->sole();

    expect($event->module)->toBe('catalog')                  // Module::Catalog->value
        ->and($event->entity_type)->toBe('ProductMaster')
        ->and($event->entity_id)->toBe((string) $master->id) // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);  // the ActorContext seam default

    // Payload asserted BY KEY through the array cast (trap 3); PII-free — producer is an id, no party data.
    expect($event->payload['product_master_id'])->toBe($master->id)
        ->and($event->payload['name'])->toBe('Penfolds Grange')
        ->and($event->payload['product_type'])->toBe('wine')
        ->and($event->payload['producer_id'])->toBe(88)
        ->and($event->payload['lifecycle_state'])->toBe('draft');

    // The neutral-core event contract restates no wine attribute (appellation lives on the per-type table).
    expect($event->payload)->not->toHaveKey('appellation')
        ->and($event->payload)->not->toHaveKey('winery_story');
});

it('holds no wine-specific attribute on the neutral core — they live in the WINE attribute set (AC-0-GEN-2)', function () {
    app(CreateProductMaster::class)->handle(
        name: 'Tenuta San Guido',
        producerId: 3,
        appellation: 'Bolgheri',
        region: 'Tuscany',
    );

    // AC-0-GEN-2: wine-specific attributes are NOT columns on the neutral core.
    foreach (['appellation', 'region', 'vintage_year', 'winery_story'] as $wineColumn) {
        expect(Schema::hasColumn('catalog_product_masters', $wineColumn))->toBeFalse();
    }

    // No neutral-core column carries a wine-specific concept as a substring (catches a renamed-but-present
    // column) — the strongest leg of the absence guard.
    $columns = Schema::getColumnListing('catalog_product_masters');

    foreach ($columns as $column) {
        foreach (['appellation', 'region', 'vintage', 'winery'] as $concept) {
            expect($column)->not->toContain($concept);
        }
    }

    // They DO live on the WINE attribute set (the per-type table).
    expect(Schema::hasColumn('catalog_product_master_wine_attributes', 'appellation'))->toBeTrue()
        ->and(Schema::hasColumn('catalog_product_master_wine_attributes', 'region'))->toBeTrue()
        ->and(Schema::hasColumn('catalog_product_master_wine_attributes', 'winery_story'))->toBeTrue();
});

it('resolves the winery story with per-attribute English fallback (assert through the cast — trap 3)', function () {
    $master = app(CreateProductMaster::class)->handle(
        name: 'Domaine Leflaive',
        producerId: 7,
        appellation: 'Puligny-Montrachet',
        region: 'Burgundy',
        wineryStory: TranslatableText::of([
            'en' => 'A storied Burgundy domaine.',
            'fr' => 'Un domaine bourguignon réputé.',
        ]),
    );

    // Re-fetch so the read exercises the TranslatableTextCast (JSON column → TranslatableText), never a
    // byte-compare of the stored JSON (PG jsonb reorders keys).
    $wine = ProductMaster::findOrFail($master->id)->wineAttributes()->sole();

    expect($wine->winery_story)->toBeInstanceOf(TranslatableText::class)
        ->and($wine->winery_story?->resolve('fr'))->toBe('Un domaine bourguignon réputé.')  // exact locale
        ->and($wine->winery_story?->resolve('it'))->toBe('A storied Burgundy domaine.')      // absent → English fallback
        ->and($wine->winery_story?->resolve('en'))->toBe('A storied Burgundy domaine.');
});

it('rejects a duplicate identity key; two distinct identity tuples both succeed (BR-Identity-1)', function () {
    $create = app(CreateProductMaster::class);

    $create->handle(name: 'Vega Sicilia', producerId: 5, appellation: 'Ribera del Duero', region: 'Castilla y León');

    // Same producer + name + appellation → rejected with a clear (localized) reason.
    expect(fn () => $create->handle(name: 'Vega Sicilia', producerId: 5, appellation: 'Ribera del Duero', region: 'Castilla y León'))
        ->toThrow(DuplicateProductMasterIdentity::class);

    // Distinct identity tuples both succeed — a different name, and a different producer.
    $create->handle(name: 'Vega Sicilia Único', producerId: 5, appellation: 'Ribera del Duero', region: 'Castilla y León');
    $create->handle(name: 'Vega Sicilia', producerId: 6, appellation: 'Ribera del Duero', region: 'Castilla y León');

    // The rejected duplicate never persisted (the check runs before any write, inside the transaction).
    expect(ProductMaster::query()->count())->toBe(3);
});

it('ignores a retired Master when deduplicating — only non-retired collisions block (BR-Identity-1)', function () {
    // A RETIRED Master holding the exact identity tuple, built via the factory (which bypasses the dedup).
    $retired = ProductMaster::factory()->create([
        'name' => 'Château Latour',
        'producer_id' => 11,
        'lifecycle_state' => LifecycleState::Retired,
    ]);
    $retired->wineAttributes()->update(['appellation' => 'Pauillac']);

    // The same tuple is accepted — the colliding Master is retired, so it does not block (design D6).
    $master = app(CreateProductMaster::class)->handle(
        name: 'Château Latour',
        producerId: 11,
        appellation: 'Pauillac',
        region: 'Bordeaux',
    );

    expect($master->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(ProductMaster::query()->where('lifecycle_state', LifecycleState::Retired->value)->count())->toBe(1)
        ->and(ProductMaster::query()->where('lifecycle_state', LifecycleState::Draft->value)->count())->toBe(1);
});

it('rejects a non-WINE product type fail-closed — WINE is the only launch type (AC-0-XM-9)', function () {
    $create = app(CreateProductMaster::class);

    expect(fn () => $create->handle(
        name: 'Some Spirit',
        producerId: 1,
        appellation: 'n/a',
        region: 'n/a',
        productType: 'beer',
    ))->toThrow(UnsupportedProductType::class);

    // Nothing persisted and no event recorded — the guard fails closed BEFORE the write.
    expect(ProductMaster::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);

    // WINE is accepted (the positive half of the scenario).
    $create->handle(name: 'A Wine', producerId: 1, appellation: 'Somewhere', region: 'Somewhere');
    expect(ProductMaster::query()->where('product_type', ProductType::Wine->value)->count())->toBe(1);
});

it('records no lifecycle-transition event — the Master stays draft (scope guard)', function () {
    $master = app(CreateProductMaster::class)->handle(
        name: 'Opus One',
        producerId: 2,
        appellation: 'Napa Valley',
        region: 'California',
    );

    // Design D3 scope guard: only the *Created event exists — never an *Activated/*Retired (the deferred
    // catalog-lifecycle-approval change owns those).
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Draft);
});

it('produces a draft Master with its wine attribute set via the factory without recording an event', function () {
    // The factory is a pure fixture: it bypasses the action, so it persists a draft Master + its 1:1 wine
    // attributes but records no ProductMasterCreated (later tasks lean on it to stand up a parent cheaply).
    $master = ProductMaster::factory()->create();

    expect($master->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($master->product_type)->toBe(ProductType::Wine)
        ->and($master->version)->toBe(1)
        ->and($master->wineAttributes()->sole()->appellation)->not->toBeEmpty()  // the 1:1 attrs were attached
        ->and(DomainEvent::query()->count())->toBe(0);
});
