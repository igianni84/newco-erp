<?php

// Task 5.2 (operator-console-catalog-spine; design L10; ADR 2026-06-19 + 2026-06-20; spec — all five ADDED
// requirements) — the change's CLOSING integration proof: ONE feature test that drives the WHOLE Module-0 spine
// THROUGH THE CONSOLES, exactly as a human operator would demo it, and asserts the EMERGENT event-SET over the
// entire run (the closing-integration rule, knowledge/testing/rules.md). It builds a full
// Product Master → Product Variant → Product Reference → {Sellable SKU, Composite SKU} ownership tree to `active`
// PARENT-BEFORE-CHILD — over its standalone Format + Case Configuration parents — every create / submit / activate
// / retire / reopen routed through the six spine consoles + the Master console (NOT the raw Catalog actions), and
// along the way exercises every divergence the spine surfaces:
//   • the activation-cascade gate — a child (Variant) activate is BLOCKED while its parent (Master) is non-active,
//     then succeeds once the parent activates (the parent-before-child ordering proven, not merely followed);
//   • the two create form-errors — a duplicate (Variant, Format) Product Reference and a < 2-constituent Composite
//     SKU each surface as a localized form error (no 500, no persist, no event);
//   • the retire reference-integrity blocks — retiring a Product Reference and a Case Configuration that an active
//     Sellable SKU still references is rejected and surfaced (the entity stays active, no *Retired);
//   • reopen — a retired Composite SKU returns to `reviewed`, audit-only.
//
// It proves three things hold over the COMPOSED chain that no single per-entity test proves alone:
//   1. submit / reject / reopen are EVENT-SILENT — across nine submits and a reopen NOT ONE *Reviewed or *Reopened
//      domain event is recorded (Module 0 PRD § 14.2 — audit-only checkpoints).
//   2. the emergent *Created / *Activated / *Retired SET is EXACTLY the entities the demo touched — every evented
//      transition fired AND nothing extraneous leaked (the blocked activate, the two form-errors, and the two
//      reference-integrity blocks each recorded NOTHING).
//   3. EVERY recorded domain event is a console-driven catalog write carrying the operator audit envelope
//      (module catalog + actor_role newco_ops + the acting operator id) — the spine-wide "operator-driven writes
//      carry the actor_role envelope" requirement, proven set-wide, not entity-by-entity.
//
// SELF-CONTAINED (the spineChain* helpers, uniquely prefixed — Pest shares ONE global function namespace across all
// files, knowledge/testing/rules.md): hermetic, so it runs identically in isolation, by directory, and in the full
// suite. Producers are seeded EVENT-FREE via ProducerState::create() (the read model the Producer-activation gate
// reads) — NOT via a ProducerActivated event + projector — so domain_events holds ONLY operator-driven catalog
// writes, and the "every event is newco_ops" claim needs no module scoping.
//
// DatabaseMigrations (mirroring the per-entity console tests): each console action drives a real domain action that
// opens its OWN DB::transaction, so the recorder's in-transaction append commits for real — the faithful production
// shape. Catalog enums/models are imported freely here: the {Models, Actions} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests.

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProducerState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CaseConfigurationResource\Pages\CreateCaseConfiguration;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CaseConfigurationResource\Pages\ViewCaseConfiguration;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource\Pages\CreateCompositeSku;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource\Pages\ViewCompositeSku;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource\Pages\CreateFormat;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource\Pages\ViewFormat;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\CreateProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\ViewProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource\Pages\CreateProductReference;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource\Pages\ViewProductReference;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages\CreateProductVariant;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages\ViewProductVariant;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource\Pages\CreateSellableSku;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource\Pages\ViewSellableSku;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

/**
 * Open the Producer-activation gate for one producer EVENT-FREE: write the Catalog-owned producer-state projection
 * row directly (status `active`), the exact read model ProducerActivationGate::assertProducerActive consults AND the
 * Master create form's producer select lists. No ProducerActivated event, no projector — so the domain_events table
 * this test asserts over holds ONLY console-driven catalog writes. Distinctly prefixed (spineChain) — Pest declares
 * every top-level test `function` in one global namespace.
 */
function spineChainProjectActiveProducer(int $producerId): void
{
    ProducerState::create([
        'producer_id' => $producerId,
        'status' => ProducerProjectionStatus::Active,
        'last_event_id' => 1,
    ]);
}

/**
 * Submit an entity for review THROUGH ITS CONSOLE View page as $operator — a `draft → reviewed` audit-only
 * checkpoint (no domain event). $entity is the `operator_console.<entity>` i18n root (e.g. `format`); $viewPage is
 * the entity's View page class. Uniform across all seven Module-0 console entities (the kit's submit action).
 */
function spineChainSubmit(Operator $operator, string $viewPage, Model $record, string $entity): void
{
    actingAs($operator, 'operator');
    Livewire::test($viewPage, ['record' => $record->getKey()])
        ->callAction('submit')
        ->assertNotified((string) __("operator_console.{$entity}.notifications.submitted"));
}

/**
 * Activate a reviewed entity THROUGH ITS CONSOLE View page as $operator (a distinct approver) — a `reviewed → active`
 * transition recording the entity's *Activated event. Used only on the success path (the cascade-blocked and
 * self-approval paths are surfaced inline). Uniform across all seven console entities (the kit's activate action).
 */
function spineChainActivate(Operator $operator, string $viewPage, Model $record, string $entity): void
{
    actingAs($operator, 'operator');
    Livewire::test($viewPage, ['record' => $record->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __("operator_console.{$entity}.notifications.activated"));
}

it('drives the entire Module-0 spine to active through the consoles as an operator demo, asserting the emergent event set and the newco_ops envelope on every write', function () {
    // Three DISTINCT operators — the production-default Creator → Reviewer → Approver lineage. The separation-of-duties
    // floor is per-entity (it reads each entity's OWN *Created event + submit audit), so the same three mutually-
    // distinct operators satisfy it at every level of the tree. The approver also drives retire / reopen (no SoD).
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // Producer 12 active in Catalog's projection (the Master create form's producer select AND the activation gate),
    // seeded event-free so every domain event below is a console-driven catalog write.
    spineChainProjectActiveProducer(12);

    // ── Phase 1 — Standalone reference parents (two Formats + a Case Configuration), each create → submit → activate
    //              through its console. Standalone: NO parent gate — they activate on the SoD floor alone. ──────────
    actingAs($creator, 'operator');
    Livewire::test(CreateFormat::class)
        ->fillForm(['name' => 'Spine Chain Magnum', 'size_label' => '1.5L', 'volume_ml' => 1500])
        ->call('create')
        ->assertHasNoFormErrors();
    $format1 = Format::query()->where('name', 'Spine Chain Magnum')->sole();
    spineChainSubmit($reviewer, ViewFormat::class, $format1, 'format');
    spineChainActivate($approver, ViewFormat::class, $format1, 'format');
    expect(Format::findOrFail($format1->id)->lifecycle_state)->toBe(LifecycleState::Active);

    actingAs($creator, 'operator');
    Livewire::test(CreateFormat::class)
        ->fillForm(['name' => 'Spine Chain Jeroboam', 'size_label' => '3L', 'volume_ml' => 3000])
        ->call('create')
        ->assertHasNoFormErrors();
    $format2 = Format::query()->where('name', 'Spine Chain Jeroboam')->sole();
    spineChainSubmit($reviewer, ViewFormat::class, $format2, 'format');
    spineChainActivate($approver, ViewFormat::class, $format2, 'format');
    expect(Format::findOrFail($format2->id)->lifecycle_state)->toBe(LifecycleState::Active);

    actingAs($creator, 'operator');
    Livewire::test(CreateCaseConfiguration::class)
        ->fillForm(['name' => 'Spine Chain OWC Six', 'units_per_case' => 6, 'packaging_type' => 'owc'])
        ->call('create')
        ->assertHasNoFormErrors();
    $caseConfig = CaseConfiguration::query()->where('name', 'Spine Chain OWC Six')->sole();
    spineChainSubmit($reviewer, ViewCaseConfiguration::class, $caseConfig, 'case_configuration');
    spineChainActivate($approver, ViewCaseConfiguration::class, $caseConfig, 'case_configuration');
    expect(CaseConfiguration::findOrFail($caseConfig->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // ── Phase 2 — Master + Variant, with the CASCADE-GATE ordering proven: the Variant is created under a non-active
    //              Master, its activate is BLOCKED, the Master activates, then the Variant activates. ───────────────
    actingAs($creator, 'operator');
    Livewire::test(CreateProductMaster::class)
        ->fillForm([
            'name' => 'Spine Chain Estate',
            'producer_id' => 12,
            'appellation' => 'Pauillac',
            'region' => 'Bordeaux',
            'winery_story' => 'An estate created end-to-end through the operator console.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();
    $master = ProductMaster::query()->where('name', 'Spine Chain Estate')->sole();
    spineChainSubmit($reviewer, ViewProductMaster::class, $master, 'product_master');
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed); // NOT yet active

    // Variant created under the still-`reviewed` Master (create does not gate on parent state) + submitted.
    actingAs($creator, 'operator');
    Livewire::test(CreateProductVariant::class)
        ->fillForm([
            'product_master_id' => $master->id,
            'variant_identifier' => 'SPINE-CHAIN-2019',
            'vintage_year' => 2019,
            'non_vintage' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();
    $variant = ProductVariant::query()->where('variant_identifier', 'SPINE-CHAIN-2019')->sole();
    spineChainSubmit($reviewer, ViewProductVariant::class, $variant, 'product_variant');

    // CASCADE-GATE BLOCK: a distinct approver attempts to activate the Variant while the Master is still `reviewed`.
    // Three distinct operators satisfy the SoD floor, so it is the activation-cascade gate (Variant ← Master active),
    // not governance, that blocks — the console surfaces ActivationCascadeViolation (catalog.gate.parent_not_active)
    // as a danger notification and re-checks the parent NOTHING (design L4). No ProductVariantActivated recorded.
    actingAs($approver, 'operator');
    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_variant.notifications.action_failed'));
    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductVariantActivated')->count())->toBe(0);

    // Activate the Master, THEN the Variant — the cascade now clears (parent active) and the SoD floor holds.
    spineChainActivate($approver, ViewProductMaster::class, $master, 'product_master');
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Active);
    spineChainActivate($approver, ViewProductVariant::class, $variant, 'product_variant');
    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // ── Phase 3 — Two Product References (Variant × each Format), with the DUPLICATE create-error between them. ────
    actingAs($creator, 'operator');
    Livewire::test(CreateProductReference::class)
        ->fillForm(['product_variant_id' => $variant->id, 'format_id' => $format1->id])
        ->call('create')
        ->assertHasNoFormErrors();
    $reference1 = ProductReference::query()->where('product_variant_id', $variant->id)->where('format_id', $format1->id)->sole();
    spineChainSubmit($reviewer, ViewProductReference::class, $reference1, 'product_reference');
    spineChainActivate($approver, ViewProductReference::class, $reference1, 'product_reference');
    expect(ProductReference::findOrFail($reference1->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // DUPLICATE create-error: a second (Variant, Format1) pair collides with PR1's unique identity (BR-Identity-3).
    // The DB unique index throws a framework UniqueConstraintViolationException (no domain message); the Create page
    // catches it and re-raises a ValidationException carrying the CONSOLE-OWNED localized message — surfaced as a
    // form error on the variant field, NOT a raw SQL string (design L5). No second PR, no second event.
    actingAs($creator, 'operator');
    Livewire::test(CreateProductReference::class)
        ->fillForm(['product_variant_id' => $variant->id, 'format_id' => $format1->id])
        ->call('create')
        ->assertHasFormErrors(['product_variant_id' => (string) __('operator_console.product_reference.duplicate_reference')]);
    expect(ProductReference::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceCreated')->count())->toBe(1);

    // PR2 (Variant × Format2) — the Composite's second constituent.
    actingAs($creator, 'operator');
    Livewire::test(CreateProductReference::class)
        ->fillForm(['product_variant_id' => $variant->id, 'format_id' => $format2->id])
        ->call('create')
        ->assertHasNoFormErrors();
    $reference2 = ProductReference::query()->where('product_variant_id', $variant->id)->where('format_id', $format2->id)->sole();
    spineChainSubmit($reviewer, ViewProductReference::class, $reference2, 'product_reference');
    spineChainActivate($approver, ViewProductReference::class, $reference2, 'product_reference');
    expect(ProductReference::findOrFail($reference2->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // ── Phase 4 — The two terminal SKUs: a Sellable SKU (PR1 + Case Config) and a Composite SKU ([PR1, PR2]), with
    //              the Composite < 2-constituent create-error between them. ───────────────────────────────────────
    actingAs($creator, 'operator');
    Livewire::test(CreateSellableSku::class)
        ->fillForm([
            'product_reference_id' => $reference1->id,
            'case_configuration_id' => $caseConfig->id,
            'commercial_name' => 'Spine Chain Cuvée',
            'marketing_copy' => 'The estate flagship, demoed through the console.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();
    $sku = SellableSku::query()->where('commercial_name', 'Spine Chain Cuvée')->sole();
    spineChainSubmit($reviewer, ViewSellableSku::class, $sku, 'sellable_sku');
    spineChainActivate($approver, ViewSellableSku::class, $sku, 'sellable_sku');
    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // COMPOSITE < 2-constituent create-error: a single-constituent bundle passes the picker's `required` rule but
    // breaches the domain's N ≥ 2 floor (BR-SKU-2). The action throws the localized InsufficientCompositeConstituents
    // (a RuntimeException); the kit base catch maps its message to the `constituents` form field — NOT a 500 and NOT
    // the PR-style framework catch (this rejection already carries a localized domain message, design L5). Nothing
    // persists, no event.
    actingAs($creator, 'operator');
    Livewire::test(CreateCompositeSku::class)
        ->fillForm(['constituents' => [$reference1->id]])
        ->call('create')
        ->assertHasFormErrors(['constituents' => (string) __('catalog.composite_sku.insufficient_constituents', ['count' => 1])]);
    expect(CompositeSku::query()->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'CompositeSKUCreated')->count())->toBe(0);

    // The real Composite SKU bundles the two active Product References in order.
    actingAs($creator, 'operator');
    Livewire::test(CreateCompositeSku::class)
        ->fillForm(['constituents' => [$reference1->id, $reference2->id]])
        ->call('create')
        ->assertHasNoFormErrors();
    $composite = CompositeSku::query()->sole();
    spineChainSubmit($reviewer, ViewCompositeSku::class, $composite, 'composite_sku');
    spineChainActivate($approver, ViewCompositeSku::class, $composite, 'composite_sku');
    expect(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and($composite->constituents->pluck('id')->all())->toEqual([$reference1->id, $reference2->id]);

    // ── Phase 5 — Retire reference-integrity blocks: the active Sellable SKU still references BOTH PR1 and the Case
    //              Configuration, so retiring either through its console is rejected and surfaced (design L4). ──────
    $prRetiredBefore = DomainEvent::query()->where('name', 'ProductReferenceRetired')->count();
    actingAs($approver, 'operator');
    Livewire::test(ViewProductReference::class, ['record' => $reference1->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.product_reference.notifications.action_failed'));
    expect(ProductReference::findOrFail($reference1->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceRetired')->count())->toBe($prRetiredBefore);

    $ccRetiredBefore = DomainEvent::query()->where('name', 'CaseConfigurationRetired')->count();
    actingAs($approver, 'operator');
    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfig->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.case_configuration.notifications.action_failed'));
    expect(CaseConfiguration::findOrFail($caseConfig->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'CaseConfigurationRetired')->count())->toBe($ccRetiredBefore);

    // ── Phase 6 — Retire + reopen the Composite SKU (a Module-0 LEAF — nothing references it). Snapshot the event ids
    //              before the retire so its single CompositeSKURetired isolates exactly; reopen is audit-only. ──────
    $idsBeforeRetire = DomainEvent::query()->pluck('id')->all();
    actingAs($approver, 'operator');
    Livewire::test(ViewCompositeSku::class, ['record' => $composite->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.composite_sku.notifications.retired'));
    expect(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Retired);
    $retireDelta = DomainEvent::query()->whereNotIn('id', $idsBeforeRetire)->orderBy('id')->pluck('name')->all();
    expect($retireDelta)->toBe(['CompositeSKURetired']); // EXACTLY the retire, nothing else leaked

    $eventsBeforeReopen = DomainEvent::query()->count();
    actingAs($approver, 'operator');
    Livewire::test(ViewCompositeSku::class, ['record' => $composite->getKey()])
        ->callAction('reopen')
        ->assertNotified((string) __('operator_console.composite_sku.notifications.reopened'));
    expect(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->count())->toBe($eventsBeforeReopen); // reopen is audit-only — no new event

    // ══ Emergent event-SET proof over the WHOLE demo ═══════════════════════════════════════════════════════════
    // (a) submit / reject / reopen are EVENT-SILENT — across nine submits + the reopen NOT ONE *Reviewed or *Reopened
    //     event was recorded anywhere (Module 0 PRD § 14.2 — audit-only checkpoints).
    expect(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reopened%')->count())->toBe(0);

    // (b) the emergent name SET is EXACTLY the entities the demo touched — every evented transition fired AND nothing
    //     extraneous leaked (the cascade-blocked activate, the two form-errors, and the two reference-integrity blocks
    //     each recorded NOTHING; SKU is UPPER-case in the SKU event names).
    $names = DomainEvent::query()->pluck('name')->all();
    expect($names)->toEqualCanonicalizing([
        'FormatCreated', 'FormatActivated',                         // Format 1
        'FormatCreated', 'FormatActivated',                         // Format 2
        'CaseConfigurationCreated', 'CaseConfigurationActivated',   // Case Configuration
        'ProductMasterCreated', 'ProductMasterActivated',           // Master
        'ProductVariantCreated', 'ProductVariantActivated',         // Variant
        'ProductReferenceCreated', 'ProductReferenceActivated',     // Product Reference 1
        'ProductReferenceCreated', 'ProductReferenceActivated',     // Product Reference 2
        'SellableSKUCreated', 'SellableSKUActivated',               // Sellable SKU
        'CompositeSKUCreated', 'CompositeSKUActivated', 'CompositeSKURetired', // Composite SKU (retired in Phase 6)
    ]);

    // (c) EVERY recorded event is a console-driven catalog write carrying the operator audit envelope — module
    //     catalog + actor_role newco_ops + a non-null operator actor (producers were seeded event-free, so the table
    //     holds ONLY operator-driven catalog writes; no System-actor projection rows to scope out).
    $events = DomainEvent::query()->get();
    expect($events)->not->toBeEmpty();
    foreach ($events as $event) {
        expect($event->module)->toBe('catalog');
        expect($event->actor_role)->toBe(ActorRole::NewcoOps);
        expect($event->actor_id)->not->toBeNull();
    }

    // (d) …and the actor_id is concretely the ACTING operator on representative writes — the create by the creator,
    //     the activation by the approver (the proven loose toEqual idiom — an uncast bigint reads back as a numeric
    //     string on PG, never strict-compare it).
    $masterCreated = DomainEvent::query()->where('entity_type', 'ProductMaster')->where('name', 'ProductMasterCreated')->sole();
    $masterActivated = DomainEvent::query()->where('entity_type', 'ProductMaster')->where('name', 'ProductMasterActivated')->sole();
    expect($masterCreated->actor_id)->toEqual($creator->id)
        ->and($masterActivated->actor_id)->toEqual($approver->id);

    // (e) the demo left a real, navigable active tree — every ownership + reference entity `active`, the Composite
    //     `reviewed` after its retire→reopen.
    expect(Format::findOrFail($format1->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(Format::findOrFail($format2->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(CaseConfiguration::findOrFail($caseConfig->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductReference::findOrFail($reference1->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductReference::findOrFail($reference2->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);
});
