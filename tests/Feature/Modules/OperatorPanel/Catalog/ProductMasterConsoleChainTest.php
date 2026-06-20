<?php

// Task 6.2 (operator-console-catalog-master; design L1–L10; ADR 2026-06-19; spec — all seven ADDED
// requirements) — the change's CLOSING integration proof: one feature test that drives the WHOLE Product
// Master console slice end-to-end, exactly as a human operator would demo it, and asserts the EMERGENT
// event-SET over the entire run (the closing-integration rule, knowledge/testing/rules.md). It walks every
// console surface 2–5 builds — the create page (valid + the dedup-rejection path), submit, the self-approval
// rejection, activate, single-entity retire (child preserved), the operator-driven cascade retire, reopen,
// and the Producer-gate-blocked activation — and proves three things hold over the COMPOSED chain that no
// single per-task test proves alone:
//   1. submit / reject / reopen are EVENT-SILENT — across two submits and a reopen, NOT ONE *Reviewed or
//      *Reopened domain event is recorded; the only ProductMaster events are Created / Activated / Retired.
//   2. the cascade emits EXACTLY its four *Retired in parent-before-child order — nothing else leaks.
//   3. EVERY recorded domain event is a console-driven write carrying the operator audit envelope
//      (actor_role newco_ops + the acting operator id) — the spine-wide "operator-driven writes carry the
//      actor_role envelope" requirement, proven set-wide, not entity-by-entity.
//
// SELF-CONTAINED by design (the chainConsole* helpers, uniquely prefixed — Pest shares ONE global function
// namespace across all files, knowledge/testing/rules.md): the closing test is hermetic, so it runs identically
// in isolation, by directory, and in the full suite, with no cross-file helper dependency. Producers are seeded
// EVENT-FREE via ProducerState::create() (the read model the Producer-activation gate reads, verified in
// ProducerActivationGate::assertProducerActive) rather than via a ProducerActivated event + projector — so the
// domain_events table holds ONLY operator-driven catalog writes (no System-actor projection rows), and the
// "every event is newco_ops" claim needs no module scoping or carve-out.
//
// DatabaseMigrations (mirroring the per-task console tests): each console action drives a real domain action
// that opens its OWN DB::transaction, so the recorder's in-transaction append commits for real — the faithful
// production shape. Catalog enums/models/actions are imported freely here: the {Models, Actions} import-boundary
// carve-out (task 1.3) governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\Catalog\Actions\ActivateCaseConfiguration;
use App\Modules\Catalog\Actions\ActivateFormat;
use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\ActivateProductReference;
use App\Modules\Catalog\Actions\ActivateProductVariant;
use App\Modules\Catalog\Actions\ActivateSellableSku;
use App\Modules\Catalog\Actions\CreateCaseConfiguration;
use App\Modules\Catalog\Actions\CreateFormat;
use App\Modules\Catalog\Actions\CreateProductMaster as CreateProductMasterAction;
use App\Modules\Catalog\Actions\CreateProductReference;
use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Actions\CreateSellableSku;
use App\Modules\Catalog\Actions\SubmitCaseConfigurationForReview;
use App\Modules\Catalog\Actions\SubmitFormatForReview;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Actions\SubmitProductReferenceForReview;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Actions\SubmitSellableSkuForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProducerState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\CreateProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\ViewProductMaster;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

/**
 * Open the Producer-activation gate for one producer EVENT-FREE: write the Catalog-owned producer-state
 * projection row directly (status `active`), the exact read model ProducerActivationGate::assertProducerActive
 * consults. No ProducerActivated event, no projector — so the domain_events table this test asserts over holds
 * ONLY console-driven catalog writes. (A producer with NO row is fail-closed — the gate rejects — which is how
 * this test's gate-blocked path is set up: simply never call this for that producer.) Distinctly prefixed —
 * Pest declares every top-level test `function` in one global namespace.
 */
function chainConsoleProjectActiveProducer(int $producerId): void
{
    ProducerState::create([
        'producer_id' => $producerId,
        'status' => ProducerProjectionStatus::Active,
        'last_event_id' => 1,
    ]);
}

/**
 * Build a fully-`active` Module-0 ownership tree — Master → Variant → (Format) → Product Reference →
 * (Case Configuration) → Sellable SKU — ENTIRELY through the real Catalog create + submit + activate domain
 * actions, over an event-free `active` producer projection. The same Creator → Reviewer → Approver lineage
 * drives every entity (the separation-of-duties floor is per-entity — it reads each entity's OWN *Created event
 * + submit audit — so three mutually-distinct operators satisfy it at every level, no fresh operators needed).
 * Format + Case Configuration are STANDALONE reference entities the cascade does NOT descend into (§ 4.7); they
 * exist only to open the Product Reference / Sellable SKU activation gates. Distinctly prefixed (chainConsole)
 * so the one shared Pest function namespace carries no redeclare. Returns the whole tree for the cascade caller.
 *
 * @return array{
 *     master: ProductMaster,
 *     variant: ProductVariant,
 *     format: Format,
 *     reference: ProductReference,
 *     caseConfiguration: CaseConfiguration,
 *     sku: SellableSku,
 * }
 */
function chainConsoleActiveTree(Operator $creator, Operator $reviewer, Operator $approver, int $producerId): array
{
    chainConsoleProjectActiveProducer($producerId);

    // Master — gated on the producer being active (the cross-module Producer-activation gate).
    actingAs($creator, 'operator');
    $master = app(CreateProductMasterAction::class)->handle(
        name: 'Château Chain Sibling',
        producerId: $producerId,
        appellation: 'Margaux',
        region: 'Bordeaux',
    );
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);
    actingAs($approver, 'operator');
    app(ActivateProductMaster::class)->handle($master);

    // Variant — gated on the parent Master being active (the within-module activation cascade).
    actingAs($creator, 'operator');
    $variant = app(CreateProductVariant::class)->handle(productMasterId: $master->id, variantIdentifier: '2019');
    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);
    actingAs($approver, 'operator');
    app(ActivateProductVariant::class)->handle($variant);

    // Format — standalone (approval governance only, no parent gate).
    actingAs($creator, 'operator');
    $format = app(CreateFormat::class)->handle(name: 'Magnum', sizeLabel: '1.5L', volumeMl: 1500);
    actingAs($reviewer, 'operator');
    app(SubmitFormatForReview::class)->handle($format);
    actingAs($approver, 'operator');
    app(ActivateFormat::class)->handle($format);

    // Product Reference — gated on BOTH the Variant and the Format being active.
    actingAs($creator, 'operator');
    $reference = app(CreateProductReference::class)->handle(productVariantId: $variant->id, formatId: $format->id);
    actingAs($reviewer, 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);
    actingAs($approver, 'operator');
    app(ActivateProductReference::class)->handle($reference);

    // Case Configuration — standalone.
    actingAs($creator, 'operator');
    $caseConfiguration = app(CreateCaseConfiguration::class)->handle(name: 'Original Wooden Case (6)', unitsPerCase: 6, packagingType: 'owc');
    actingAs($reviewer, 'operator');
    app(SubmitCaseConfigurationForReview::class)->handle($caseConfiguration);
    actingAs($approver, 'operator');
    app(ActivateCaseConfiguration::class)->handle($caseConfiguration);

    // Sellable SKU — the leaf; gated on BOTH the Product Reference and the Case Configuration being active.
    actingAs($creator, 'operator');
    $sku = app(CreateSellableSku::class)->handle(
        productReferenceId: $reference->id,
        caseConfigurationId: $caseConfiguration->id,
        commercialName: 'Château Chain Sibling 2019 — Magnum (OWC 6)',
    );
    actingAs($reviewer, 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);
    actingAs($approver, 'operator');
    app(ActivateSellableSku::class)->handle($sku);

    return [
        'master' => $master,
        'variant' => $variant,
        'format' => $format,
        'reference' => $reference,
        'caseConfiguration' => $caseConfiguration,
        'sku' => $sku,
    ];
}

it('drives the entire Product Master console slice end-to-end as an operator demo, asserting the emergent event set and the newco_ops envelope on every write', function () {
    // Three DISTINCT operators — the production-default role_count-3 Creator → Reviewer → Approver lineage (no
    // config override; mirrors tests/Feature/Modules/Catalog/ProductMasterLifecycleTest.php). In the task's
    // narration A = the reviewer (submits, then is rejected on self-approval) and B = the approver (activates).
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();   // "A"
    $approver = Operator::factory()->create();    // "B"

    // ── Phase 1 — CREATE (manual baseline) through the console + the DEDUP-rejection path ───────────────────
    // Producer 7 active in Catalog's projection (the create form's producer select AND the activation gate),
    // seeded event-free so every domain event below is a console-driven catalog write.
    chainConsoleProjectActiveProducer(7);

    actingAs($creator, 'operator');
    Livewire::test(CreateProductMaster::class)
        ->fillForm([
            'name' => 'Château Chain M1',
            'producer_id' => 7,
            'appellation' => 'Pauillac',
            'region' => 'Bordeaux',
            'winery_story' => 'An estate created through the operator console.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $m1 = ProductMaster::query()->where('name', 'Château Chain M1')->sole();
    expect($m1->lifecycle_state)->toBe(LifecycleState::Draft);

    // DEDUP-rejection path: the same (producer + name + appellation) identity collides with the now-existing
    // draft M1 → the BR-Identity-1 domain rejection surfaces as a form error on `name`, not a 500; no second
    // Master, no second ProductMasterCreated.
    actingAs($creator, 'operator');
    Livewire::test(CreateProductMaster::class)
        ->fillForm([
            'name' => 'Château Chain M1',
            'producer_id' => 7,
            'appellation' => 'Pauillac',
            'region' => 'Bordeaux',
        ])
        ->call('create')
        ->assertHasFormErrors(['name']);

    expect(ProductMaster::query()->where('name', 'Château Chain M1')->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'ProductMasterCreated')->count())->toBe(1);

    // ── Phase 2 — SUBMIT for review (A = reviewer), audit-only ──────────────────────────────────────────────
    actingAs($reviewer, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $m1->getKey()])
        ->callAction('submit')
        ->assertNotified((string) __('operator_console.product_master.notifications.submitted'));

    expect(ProductMaster::findOrFail($m1->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // ── Phase 3 — SELF-APPROVAL rejected (A attempts the approval of their own review) ──────────────────────
    // Producer 7 is active, so the separation-of-duties floor is the SOLE possible rejection: the reviewer is
    // the prior governance actor and may not approve. The console SURFACES the domain rejection as a danger
    // notification and never re-checks the floor itself (design L5); the Master is unchanged, no event.
    actingAs($reviewer, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $m1->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_master.notifications.action_failed'));

    expect(ProductMaster::findOrFail($m1->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0);

    // ── Phase 4 — ACTIVATE (B = a distinct approver, producer active) ───────────────────────────────────────
    actingAs($approver, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $m1->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_master.notifications.activated'));

    expect(ProductMaster::findOrFail($m1->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // ── Phase 5 — SINGLE-entity retire preserves an active child ────────────────────────────────────────────
    // Seed an ACTIVE child Variant under M1 through the real Catalog actions (the same lineage carries the
    // Variant's own per-entity approval floor; its activation re-confirms the parent Master is active).
    actingAs($creator, 'operator');
    $child = app(CreateProductVariant::class)->handle(productMasterId: $m1->id, variantIdentifier: '2019');
    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($child);
    actingAs($approver, 'operator');
    app(ActivateProductVariant::class)->handle($child);
    expect(ProductVariant::findOrFail($child->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Single-entity retire THROUGH THE CONSOLE → M1 retired, the active child PRESERVED (§ 4.5 — a single-entity
    // retire never cascades; only NEW activation under the now-retired Master would be blocked).
    actingAs($approver, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $m1->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.product_master.notifications.retired'));

    expect(ProductMaster::findOrFail($m1->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductVariant::findOrFail($child->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // ── Phase 6 — CASCADE-retire a SIBLING subtree ─────────────────────────────────────────────────────────
    // A separate active ownership tree (producer 8): Master → Variant → PR → SKU, plus standalone Format + Case
    // Configuration. Snapshot the existing event ids so the cascade's OWN events isolate exactly.
    $tree = chainConsoleActiveTree($creator, $reviewer, $approver, 8);
    $idsBeforeCascade = DomainEvent::query()->pluck('id')->all();

    actingAs($approver, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $tree['master']->getKey()])
        ->callAction('retireCascade')
        ->assertNotified((string) __('operator_console.product_master.notifications.cascade_retired'));

    // The whole OWNERSHIP subtree (Master → Variant → PR → SKU) reached retired; the STANDALONE reference
    // entities are NOT descended into (§ 4.7) and stay active.
    expect(ProductMaster::findOrFail($tree['master']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductVariant::findOrFail($tree['variant']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductReference::findOrFail($tree['reference']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(SellableSku::findOrFail($tree['sku']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(Format::findOrFail($tree['format']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(CaseConfiguration::findOrFail($tree['caseConfiguration']->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // The cascade emitted EXACTLY its four *Retired in parent-before-child (ascending id) order — and nothing
    // else (the new-event delta since the snapshot is precisely those four).
    $cascadeEvents = DomainEvent::query()
        ->whereNotIn('id', $idsBeforeCascade)
        ->orderBy('id')
        ->pluck('name')
        ->all();
    expect($cascadeEvents)->toBe(['ProductMasterRetired', 'ProductVariantRetired', 'ProductReferenceRetired', 'SellableSKURetired']);

    // ── Phase 7 — REOPEN (M1: retired → reviewed, audit-only) ──────────────────────────────────────────────
    $eventsBeforeReopen = DomainEvent::query()->count();

    actingAs($approver, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $m1->getKey()])
        ->callAction('reopen')
        ->assertNotified((string) __('operator_console.product_master.notifications.reopened'));

    // Back to `reviewed`, AUDIT-ONLY: reopen recorded NO new domain event (the event total is unchanged).
    expect(ProductMaster::findOrFail($m1->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->count())->toBe($eventsBeforeReopen);

    // ── Phase 8 — PRODUCER-GATE-blocked activation (producer 9 never projected active) ─────────────────────
    // M3 reaches `reviewed` through the real actions (three distinct actors → the SoD floor passes), then a
    // distinct approver activates THROUGH THE CONSOLE — producer 9 has no projection row, so the gate fails
    // closed and is the SOLE rejection. The console surfaces the gate reason; the Master stays `reviewed`.
    actingAs($creator, 'operator');
    $m3 = app(CreateProductMasterAction::class)->handle(
        name: 'Château Chain M3',
        producerId: 9,
        appellation: 'Saint-Julien',
        region: 'Bordeaux',
    );
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($m3);

    actingAs($approver, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $m3->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_master.notifications.action_failed'));

    expect(ProductMaster::findOrFail($m3->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('entity_type', 'ProductMaster')->where('entity_id', (string) $m3->id)->where('name', 'ProductMasterActivated')->count())->toBe(0);

    // ══ Emergent event-SET proof over the WHOLE demo ═══════════════════════════════════════════════════════
    // (a) submit / reject / reopen are EVENT-SILENT — across two submits + a reopen NOT ONE *Reviewed or
    //     *Reopened event was recorded anywhere in the chain (Module 0 PRD § 14.2 — audit-only checkpoints).
    expect(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reopened%')->count())->toBe(0);

    // (b) the ProductMaster emergent event set is EXACTLY create / activate / retire — three creates (M1, the
    //     sibling Master, M3), two activations (M3 gate-blocked → none), two retirements (M1 single + the
    //     sibling cascade). The set-assertion proves both that every evented transition fired AND that nothing
    //     extraneous (no ProductMasterReviewed/Reopened) leaked in.
    $masterEvents = DomainEvent::query()->where('entity_type', 'ProductMaster')->pluck('name')->all();
    expect($masterEvents)->toEqualCanonicalizing([
        'ProductMasterCreated', 'ProductMasterActivated', 'ProductMasterRetired',   // M1
        'ProductMasterCreated', 'ProductMasterActivated', 'ProductMasterRetired',   // sibling (via cascade)
        'ProductMasterCreated',                                                      // M3 (gate-blocked)
    ]);

    // (c) EVERY recorded domain event is a console-driven catalog write carrying the operator audit envelope —
    //     actor_role newco_ops + a non-null operator actor (producers were seeded event-free, so the table holds
    //     ONLY operator-driven catalog writes; no System-actor projection rows to scope out).
    $events = DomainEvent::query()->get();
    expect($events)->not->toBeEmpty();
    foreach ($events as $event) {
        expect($event->module)->toBe('catalog');
        expect($event->actor_role)->toBe(ActorRole::NewcoOps);
        expect($event->actor_id)->not->toBeNull();
    }

    // (d) …and the actor_id is concretely the ACTING operator on the representative writes (the proven loose
    //     toEqual idiom — an uncast bigint reads back as a numeric string on PG, never strict-compare it).
    $m1Created = DomainEvent::query()->where('entity_type', 'ProductMaster')->where('entity_id', (string) $m1->id)->where('name', 'ProductMasterCreated')->sole();
    $m1Activated = DomainEvent::query()->where('entity_type', 'ProductMaster')->where('entity_id', (string) $m1->id)->where('name', 'ProductMasterActivated')->sole();
    expect($m1Created->actor_id)->toEqual($creator->id)
        ->and($m1Activated->actor_id)->toEqual($approver->id);
});
