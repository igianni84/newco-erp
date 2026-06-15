<?php

use App\Modules\Catalog\Actions\CreateCaseConfiguration;
use App\Modules\Catalog\Actions\CreateCompositeSku;
use App\Modules\Catalog\Actions\CreateFormat;
use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\CreateProductReference;
use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Actions\CreateSellableSku;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\CaseConfigurationCreated;
use App\Modules\Catalog\Events\CompositeSKUCreated;
use App\Modules\Catalog\Events\FormatCreated;
use App\Modules\Catalog\Events\ProductMasterCreated;
use App\Modules\Catalog\Events\ProductReferenceCreated;
use App\Modules\Catalog\Events\ProductVariantCreated;
use App\Modules\Catalog\Events\SellableSKUCreated;
use App\Modules\Catalog\Exceptions\DuplicateProductMasterIdentity;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\Module;
use App\Platform\Events\DomainEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The full-chain integration test — the substrate-wiring proof for the whole product-catalog spine, driven
 * end-to-end through the seven `Create*` actions (catalog-product-spine task 5.3; design D3/D7/D8;
 * product-catalog — Requirement: Spine Creation Events; Module 0 PRD §14.1/§14.2; Acceptance § 2 AC-0-J-4,
 * the creation half of the chain + event emission). Where each entity's own feature test pins ONE node in
 * isolation, this test wires them together — Master → Variant → (two Formats) → two References → an Intrinsic
 * SKU + a Composite bundling both References — and proves the spine COHERES:
 *   - every spine entity records its category-neutral `*Created` event, and ONLY those seven families are
 *     recorded — no `*Activated`/`*Retired` (design D3 scope guard: this change creates only);
 *   - every entity is born — and stays — `draft` (§14.2 `<null> → draft`);
 *   - every recorded payload is PII-free — the only party reference in the whole chain is the Master's bare
 *     integer `producer_id` (no personal data anywhere; CLAUDE.md invariant; the substrate's payload discipline);
 *   - the BR-Identity-1 dedup gate and the producer-agnostic Composite rule (design D9 / BR-SKU-5) both hold in
 *     the INTEGRATED flow, not just in isolation.
 *
 * RefreshDatabase: each action opens its OWN DB::transaction, so the platform recorder's `transactionLevel()
 * === 0` guard is satisfied by the savepoint even under the wrapper. Event payloads are asserted BY KEY —
 * never a byte-compare of stored JSON (knowledge/testing trap 3). This is the SQLite lane; the task's
 * cross-engine gate re-runs the whole Catalog suite on PostgreSQL 17 (knowledge/testing/rules.md).
 */
uses(RefreshDatabase::class);

/**
 * Drives the entire spine creation chain through the seven `Create*` actions and returns the created entities
 * keyed by role. The Composite bundles two Product References of the SAME wine in two bottle sizes (750ml +
 * magnum) — the minimal shape that exercises a Composite (N ≥ 2 distinct PRs) end-to-end, so Format and
 * Product Reference are each created twice while every other node is created once.
 *
 * @return array{
 *     format: Format,
 *     magnum: Format,
 *     caseConfiguration: CaseConfiguration,
 *     master: ProductMaster,
 *     variant: ProductVariant,
 *     reference: ProductReference,
 *     magnumReference: ProductReference,
 *     sku: SellableSku,
 *     composite: CompositeSku,
 * }
 */
function createCatalogSpineChain(): array
{
    $format = app(CreateFormat::class)->handle(name: 'Bordeaux 750ml', sizeLabel: '750ml', volumeMl: 750);
    $magnum = app(CreateFormat::class)->handle(name: 'Bordeaux Magnum', sizeLabel: '1.5L', volumeMl: 1500);
    $caseConfiguration = app(CreateCaseConfiguration::class)->handle(name: 'OWC 6', unitsPerCase: 6, packagingType: 'owc');

    $master = app(CreateProductMaster::class)->handle(
        name: 'Château Margaux',
        producerId: 1001,
        appellation: 'Margaux',
        region: 'Bordeaux',
    );
    $variant = app(CreateProductVariant::class)->handle(
        productMasterId: $master->id,
        variantIdentifier: '2015',
        vintageYear: 2015,
    );

    $reference = app(CreateProductReference::class)->handle(productVariantId: $variant->id, formatId: $format->id);
    $magnumReference = app(CreateProductReference::class)->handle(productVariantId: $variant->id, formatId: $magnum->id);

    $sku = app(CreateSellableSku::class)->handle(
        productReferenceId: $reference->id,
        caseConfigurationId: $caseConfiguration->id,
        commercialName: 'Château Margaux 2015 — OWC 6',
    );

    // A gift bundle of the same wine in two formats — a genuinely multi-PR Composite (N ≥ 2).
    $composite = app(CreateCompositeSku::class)->handle([$reference->id, $magnumReference->id]);

    return [
        'format' => $format,
        'magnum' => $magnum,
        'caseConfiguration' => $caseConfiguration,
        'master' => $master,
        'variant' => $variant,
        'reference' => $reference,
        'magnumReference' => $magnumReference,
        'sku' => $sku,
        'composite' => $composite,
    ];
}

it('drives the whole spine and records exactly the seven *Created event families — no lifecycle-transition event', function () {
    createCatalogSpineChain();

    // The DISTINCT recorded event names ARE the spine's published creation contract: exactly the seven
    // category-neutral *Created families (the SKU events keep §14.1's UPPER-`SKU` spelling), and nothing else
    // (delta-spec "Spine Creation Events"; AC-0-J-4 creation half).
    $distinctNames = DomainEvent::query()->orderBy('name')->distinct()->pluck('name')->all();

    expect($distinctNames)->toBe([
        'CaseConfigurationCreated',
        'CompositeSKUCreated',
        'FormatCreated',
        'ProductMasterCreated',
        'ProductReferenceCreated',
        'ProductVariantCreated',
        'SellableSKUCreated',
    ]);

    // Each spine entity recorded exactly its own creation event — the chain built two Formats and two
    // References (the Composite's two constituents), every other node once.
    expect(DomainEvent::query()->where('name', FormatCreated::NAME)->count())->toBe(2)
        ->and(DomainEvent::query()->where('name', CaseConfigurationCreated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProductMasterCreated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProductVariantCreated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProductReferenceCreated::NAME)->count())->toBe(2)
        ->and(DomainEvent::query()->where('name', SellableSKUCreated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CompositeSKUCreated::NAME)->count())->toBe(1);

    // Scope guard (design D3): a create-only change records NO lifecycle-transition event. The `%Activated%`/
    // `%Retired%` sweeps would catch any spine entity's transition event leaking into this slice.
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0);

    // Every recorded event is tagged with the catalog module (the inter-module contract's module key).
    expect(DomainEvent::query()->where('module', '!=', Module::Catalog->value)->count())->toBe(0);
});

it('creates — and leaves — every spine entity in draft', function () {
    $entities = createCatalogSpineChain();

    // Born draft AND persisted draft: re-fetched from the DB so the assertion proves the stored state, not the
    // in-memory create() value. Nothing in this create-only change transitions any entity out of draft (§14.2).
    expect(Format::findOrFail($entities['format']->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(Format::findOrFail($entities['magnum']->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(CaseConfiguration::findOrFail($entities['caseConfiguration']->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(ProductMaster::findOrFail($entities['master']->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(ProductVariant::findOrFail($entities['variant']->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(ProductReference::findOrFail($entities['reference']->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(ProductReference::findOrFail($entities['magnumReference']->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(SellableSku::findOrFail($entities['sku']->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(CompositeSku::findOrFail($entities['composite']->id)->lifecycle_state)->toBe(LifecycleState::Draft);
});

it('keeps every spine creation payload PII-free — parties are referenced by id only', function () {
    $entities = createCatalogSpineChain();

    // The Master is the ONLY spine entity that references a party: its producer, captured as a bare integer id
    // (producer_id is a plain column — no relation crosses the module boundary, invariant 10). The exact key
    // set proves no producer name / party object / personal data was folded into the payload.
    $masterPayload = DomainEvent::query()->where('name', ProductMasterCreated::NAME)->sole()->payload;

    // The producer is asserted BY KEY (trap 3 — never byte-compare or order-compare a stored jsonb payload).
    expect($masterPayload['producer_id'])->toBe($entities['master']->producer_id)
        ->and($masterPayload['producer_id'])->toBeInt();

    // The exact key SET proves no producer name / party object / personal data was folded in. Sorted before
    // comparing because PostgreSQL `jsonb` reorders object keys (by length, then bytewise) — the key ORDER is
    // not portable, only the set is (trap 3; the codebase's getColumnListing sort-then-compare idiom).
    $masterPayloadKeys = array_keys($masterPayload);
    sort($masterPayloadKeys);
    expect($masterPayloadKeys)->toBe(['lifecycle_state', 'name', 'producer_id', 'product_master_id', 'product_type']);

    // Across the WHOLE chain, no recorded payload carries a party/personal-data key — the only producer
    // reference anywhere is the Master's bare id (a regression guard: a future payload widening to embed a
    // producer name or any PII would turn this red).
    $forbidden = ['producer_name', 'producer', 'producer_name_translations', 'email', 'phone', 'address', 'customer', 'party', 'winery_story'];

    /** @var Collection<int, DomainEvent> $events */
    $events = DomainEvent::query()->get();
    $payloadKeys = $events->flatMap(fn (DomainEvent $event): array => array_keys($event->payload))->unique()->values()->all();

    expect(array_values(array_intersect($payloadKeys, $forbidden)))->toBe([]);
});

it('holds the identity dedup in the integrated flow — a duplicate Master is rejected end-to-end', function () {
    // The first Master persists; a second with the SAME non-retired identity tuple (producer + name +
    // appellation) is rejected — BR-Identity-1 holds when the action is exercised through the live flow, not
    // only in the unit-level ProductMasterTest (AC-0-J-4 creation half includes the dedup gate).
    app(CreateProductMaster::class)->handle(name: 'Château Latour', producerId: 1001, appellation: 'Pauillac', region: 'Bordeaux');

    expect(fn () => app(CreateProductMaster::class)->handle(
        name: 'Château Latour',
        producerId: 1001,
        appellation: 'Pauillac',
        region: 'Bordeaux',
    ))->toThrow(DuplicateProductMasterIdentity::class);

    // Exactly one Master persisted; exactly one ProductMasterCreated recorded — the rejected duplicate wrote
    // nothing (the dedup guard runs inside the transaction, before the insert + emit).
    expect(ProductMaster::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProductMasterCreated::NAME)->count())->toBe(1);
});

it('accepts a multi-producer Composite in the integrated flow — PIM is producer-agnostic (BR-SKU-5)', function () {
    // Two full sub-chains driven through the actions, whose Masters carry DIFFERENT producers (producer_id is a
    // plain column). The two References share one Format (PR identity is (variant, format), and the variants
    // differ), so the constituent set is genuinely multi-producer.
    $format = app(CreateFormat::class)->handle(name: 'Bordeaux 750ml', sizeLabel: '750ml', volumeMl: 750);

    $masterA = app(CreateProductMaster::class)->handle(name: 'Château A', producerId: 1001, appellation: 'Margaux', region: 'Bordeaux');
    $variantA = app(CreateProductVariant::class)->handle(productMasterId: $masterA->id, variantIdentifier: '2015', vintageYear: 2015);
    $referenceA = app(CreateProductReference::class)->handle(productVariantId: $variantA->id, formatId: $format->id);

    $masterB = app(CreateProductMaster::class)->handle(name: 'Château B', producerId: 2002, appellation: 'Pomerol', region: 'Bordeaux');
    $variantB = app(CreateProductVariant::class)->handle(productMasterId: $masterB->id, variantIdentifier: '2016', vintageYear: 2016);
    $referenceB = app(CreateProductReference::class)->handle(productVariantId: $variantB->id, formatId: $format->id);

    expect($masterA->producer_id)->not->toBe($masterB->producer_id); // the constituent set really is multi-producer

    // PIM accepts the multi-producer bundle WITHOUT validating producer composition (design D9): the
    // single-producer-at-launch rule is a Module S Offer-publication concern, never a PIM check. The creation
    // succeeding with both constituents is the proof that no producer guard ran in the integrated flow.
    $composite = app(CreateCompositeSku::class)->handle([$referenceA->id, $referenceB->id]);

    expect($composite->constituents)->toHaveCount(2)
        ->and(DomainEvent::query()->where('name', CompositeSKUCreated::NAME)->count())->toBe(1);
});
