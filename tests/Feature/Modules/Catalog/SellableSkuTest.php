<?php

use App\Modules\Catalog\Actions\CreateSellableSku;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\SellableSKUCreated;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\SellableSku;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Sellable SKU (Intrinsic) — the commercial unit composed of EXACTLY one Product Reference + one Case
 * Configuration + commercial attributes, and the ONLY SKU shape that references a Case Configuration
 * (catalog-product-spine task 4.1; design D5/D8; product-catalog — Requirement: Sellable SKU (Intrinsic),
 * Spine Creation Events; Module 0 PRD §3.7, §13.5 BR-SKU-1). It proves the CreateSellableSku action persists
 * the row in `draft` over one PR + one Case Configuration with its commercial attributes, records
 * SellableSKUCreated through the platform recorder in the SAME transaction (PII-free), resolves both
 * within-module belongsTo to exactly one parent each, completes the "Packaging does not change the PR"
 * scenario (three Case Configurations → three SKUs → all over the ONE PR), and holds the scope guard (no
 * transition out of `draft`).
 *
 * RefreshDatabase (per the task hint): the action opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint even under the wrapper. Event payload is
 * asserted BY KEY — never a byte-compare of stored JSON (knowledge/testing trap 3).
 */
uses(RefreshDatabase::class);

it('creates a Sellable SKU in draft from a Product Reference + Case Configuration with commercial attributes', function () {
    $reference = ProductReference::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    $sku = app(CreateSellableSku::class)->handle(
        productReferenceId: $reference->id,
        caseConfigurationId: $caseConfiguration->id,
        commercialName: 'Margaux 2015 — OWC 6',
        marketingCopy: 'A landmark vintage, presented in the original wooden case.',
    );

    // Re-fetch so the assertions exercise the read/hydration casts, not the in-memory create() values.
    $read = SellableSku::findOrFail($sku->id);

    expect($read->product_reference_id)->toBe($reference->id)
        ->and($read->case_configuration_id)->toBe($caseConfiguration->id)
        ->and($read->commercial_name)->toBe('Margaux 2015 — OWC 6')
        ->and($read->marketing_copy)->toBe('A landmark vintage, presented in the original wooden case.')
        ->and($read->lifecycle_state)->toBe(LifecycleState::Draft)  // born draft (design D3)
        ->and($read->version)->toBe(1);                            // §4.8 version floor, born at 1
});

it('treats marketing copy as an optional commercial attribute', function () {
    $reference = ProductReference::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    // commercial_name is the required commercial attribute; marketing_copy is optional free-form copy (§3.7).
    $sku = app(CreateSellableSku::class)->handle(
        productReferenceId: $reference->id,
        caseConfigurationId: $caseConfiguration->id,
        commercialName: 'Loose bottle',
    );

    expect(SellableSku::findOrFail($sku->id)->marketing_copy)->toBeNull();
});

it('records a SellableSKUCreated domain event in the same transaction, tagged catalog and PII-free', function () {
    $reference = ProductReference::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    $sku = app(CreateSellableSku::class)->handle(
        productReferenceId: $reference->id,
        caseConfigurationId: $caseConfiguration->id,
        commercialName: 'Margaux 2015 — OWC 6',
    );

    // sole() asserts EXACTLY one SellableSKUCreated row exists — the one-event contract.
    $event = DomainEvent::query()->where('name', SellableSKUCreated::NAME)->sole();

    expect($event->module)->toBe('catalog')                    // Module::Catalog->value
        ->and($event->entity_type)->toBe('SellableSku')        // the canonical model class name (§18)
        ->and($event->entity_id)->toBe((string) $sku->id)      // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);    // the ActorContext seam default

    // Payload asserted BY KEY through the array cast (trap 3); PII-free — ids + the non-PII commercial name.
    expect($event->payload['sellable_sku_id'])->toBe($sku->id)
        ->and($event->payload['product_reference_id'])->toBe($reference->id)
        ->and($event->payload['case_configuration_id'])->toBe($caseConfiguration->id)
        ->and($event->payload['commercial_name'])->toBe('Margaux 2015 — OWC 6')
        ->and($event->payload['lifecycle_state'])->toBe('draft');

    // The lean event omits the free-form marketing copy (a consumer reads it through a contract).
    expect($event->payload)->not->toHaveKey('marketing_copy');
});

it('references exactly one Product Reference and one Case Configuration via the within-module belongsTo relations', function () {
    $reference = ProductReference::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    $sku = app(CreateSellableSku::class)->handle(
        productReferenceId: $reference->id,
        caseConfigurationId: $caseConfiguration->id,
        commercialName: 'Margaux 2015 — OWC 6',
    );
    $read = SellableSku::findOrFail($sku->id);

    // both within-module belongsTo resolve to exactly one parent each (sole() = exactly one, non-null).
    expect($read->reference()->sole()->id)->toBe($reference->id)
        ->and($read->reference()->sole())->toBeInstanceOf(ProductReference::class)
        ->and($read->caseConfiguration()->sole()->id)->toBe($caseConfiguration->id)
        ->and($read->caseConfiguration()->sole())->toBeInstanceOf(CaseConfiguration::class);
});

it('keeps the same Product Reference across packaging — three Case Configurations yield three SKUs over the one PR', function () {
    // One Product Reference = one (Variant, Format) pair (BR-Identity-3). Packaging is a SKU dimension, never
    // part of PR identity — so three Case Configurations over this one PR are three SKUs sharing the ONE PR.
    $reference = ProductReference::factory()->create();

    // Three packaging forms: loose, six-bottle OWC, twelve-bottle carton (the spec's exact example).
    $caseConfigurations = collect([
        CaseConfiguration::factory()->create(['name' => 'Loose', 'units_per_case' => 1, 'packaging_type' => 'loose']),
        CaseConfiguration::factory()->create(['name' => 'OWC 6', 'units_per_case' => 6, 'packaging_type' => 'owc']),
        CaseConfiguration::factory()->create(['name' => 'Carton 12', 'units_per_case' => 12, 'packaging_type' => 'carton']),
    ]);

    $skus = $caseConfigurations->map(
        fn (CaseConfiguration $caseConfiguration): SellableSku => app(CreateSellableSku::class)->handle(
            productReferenceId: $reference->id,
            caseConfigurationId: $caseConfiguration->id,
            commercialName: "Margaux 2015 — {$caseConfiguration->name}",
        )
    );

    // All three SKUs reference the ONE same PR — "Packaging does not change the PR" (the SKU half of the scenario).
    expect($skus->pluck('product_reference_id')->unique()->values()->all())->toBe([$reference->id])
        ->and($skus->pluck('id')->unique())->toHaveCount(3)                    // three DISTINCT SKUs
        ->and($skus->pluck('case_configuration_id')->unique())->toHaveCount(3) // over three DISTINCT Case Configs
        ->and(ProductReference::query()->count())->toBe(1);                    // exactly the one PR exists
});

it('pins the commercial column set — PR + Case Configuration + commercial attributes only', function () {
    // The Intrinsic SKU's columns: two structural FKs (product_reference_id, case_configuration_id), the §3.7
    // commercial attributes (commercial_name, marketing_copy), and lifecycle/audit — and nothing else. Sorted:
    // order-independent, cross-engine stable (PG & SQLite list columns in ordinal order; sorting removes it).
    $columns = Schema::getColumnListing('catalog_sellable_skus');
    sort($columns);

    expect($columns)->toBe([
        'case_configuration_id', 'commercial_name', 'created_at', 'id', 'lifecycle_state',
        'marketing_copy', 'product_reference_id', 'updated_at', 'version',
    ]);
});

it('records no lifecycle-transition event — the Sellable SKU stays draft (scope guard)', function () {
    $sku = app(CreateSellableSku::class)->handle(
        productReferenceId: ProductReference::factory()->create()->id,
        caseConfigurationId: CaseConfiguration::factory()->create()->id,
        commercialName: 'Margaux 2015 — OWC 6',
    );

    // Design D3 scope guard: only the *Created event exists — never an *Activated/*Retired (the §3.7 activation
    // prerequisite + transitions belong to the deferred catalog-lifecycle-approval change).
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Draft);
});

it('produces a draft Sellable SKU via the factory without recording an event', function () {
    // The factory is a pure fixture: it bypasses the action, so it persists a draft SKU (and a parent PR +
    // Case Configuration) but records no SellableSKUCreated.
    $sku = SellableSku::factory()->create();

    expect($sku->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($sku->version)->toBe(1)
        ->and($sku->reference()->sole())->toBeInstanceOf(ProductReference::class)            // within-module parents attached
        ->and($sku->caseConfiguration()->sole())->toBeInstanceOf(CaseConfiguration::class)
        ->and(DomainEvent::query()->count())->toBe(0);                                       // the factory records no event
});
