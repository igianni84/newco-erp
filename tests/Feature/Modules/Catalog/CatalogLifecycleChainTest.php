<?php

use App\Modules\Catalog\Actions\ActivateCaseConfiguration;
use App\Modules\Catalog\Actions\ActivateFormat;
use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\ActivateProductReference;
use App\Modules\Catalog\Actions\ActivateProductVariant;
use App\Modules\Catalog\Actions\ActivateSellableSku;
use App\Modules\Catalog\Actions\CreateCaseConfiguration;
use App\Modules\Catalog\Actions\CreateFormat;
use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\CreateProductReference;
use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Actions\CreateSellableSku;
use App\Modules\Catalog\Actions\RetireProductMasterCascade;
use App\Modules\Catalog\Actions\SubmitCaseConfigurationForReview;
use App\Modules\Catalog\Actions\SubmitFormatForReview;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Actions\SubmitProductReferenceForReview;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Actions\SubmitSellableSkuForReview;
use App\Modules\Catalog\Consumers\ProducerLifecycleProjector;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Events\ProductMasterActivated;
use App\Modules\Catalog\Events\ProductMasterRetired;
use App\Modules\Catalog\Events\ProductReferenceActivated;
use App\Modules\Catalog\Events\ProductReferenceRetired;
use App\Modules\Catalog\Events\ProductVariantActivated;
use App\Modules\Catalog\Events\ProductVariantRetired;
use App\Modules\Catalog\Events\SellableSKUActivated;
use App\Modules\Catalog\Events\SellableSKURetired;
use App\Modules\Catalog\Exceptions\ProducerActivationGateViolation;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProducerState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\Module;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Actions\ActivateProducer;
use App\Modules\Parties\Actions\RetireProducer;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;

use function Pest\Laravel\actingAs;

/**
 * The end-to-end cross-module proof for the catalog lifecycle (catalog-lifecycle-approval task 6.2; design
 * D3/D4/D6/D7/D8/D9; product-catalog — Requirements: Producer Activation Gate, Producer-State Projection and
 * Event Consumption, Activation Cascade, Retirement Cascade and Reference Integrity, Product Lifecycle Events;
 * Module 0 PRD § 4.4/§ 4.5/§ 4.7/§ 5.4/§ 14.3; Module K Acceptance § 6.1 AC-K-XM-2). Where the sibling
 * `*LifecycleTest` files pin each entity's transition in isolation and ActivationCascade/RetirementCascade pin
 * the within-catalog cascades against factory-`active` fixtures, THIS test drives the **real cross-module
 * path** — a genuine Module K `ActivateProducer` / `RetireProducer` whose events fan out, through the platform
 * substrate, to Catalog's registered `ProducerLifecycleProjector`, feeding the producer-state projection the
 * Producer Activation Gate reads. Nothing here is simulated (contrast `cascadeReviewedSpine`, which records a
 * hand-built `ProducerActivated`): the producer is created with the Parties FACTORY and transitioned with the
 * Parties ACTIONS, so a projected row PROVES the real fan-out ran.
 *
 * Two properties:
 *   1. ENABLE → ACTIVATE → CASCADE-RETIRE — a real `ProducerActivated` projects the producer `active`, which
 *      unblocks the Master gate; the full Master → Variant → Format → Product Reference → Case Configuration →
 *      Sellable SKU spine then activates through the real gated Actions, recording the four hierarchy
 *      `*Activated` events parent-before-child; the operator-driven {@see RetireProductMasterCascade} retires
 *      the ownership tree parent-before-child (the standalone Format / Case Configuration preserved); and NO
 *      `*Reviewed` event exists anywhere (the `draft → reviewed` submit checkpoints are audit-only).
 *   2. BLOCK-NEW / PRESERVE — a real `ProducerRetired` projects the producer `retired`; the existing `active`
 *      chain is untouched (preserve — consuming the event never transitions a Master), while a NEW Master on
 *      the same producer can no longer be activated (the gate reads the now-`retired` projection — AC-K-XM-2).
 *
 * Boundary law (invariant 10): the production cross-module coupling stays event-payload only — the gate reads
 * Catalog's own projection, the consumer reads only `producer_id`/`status`. The Parties imports here are
 * **test-only** (factories + Actions, which `ModuleBoundariesTest` does not scan), standing up the real emitter
 * so the fan-out is exercised. The companion `SpineCreationChainTest` (the creation chain emits only `*Created`)
 * and the architecture tests (`ModuleBoundariesTest`, `ModulePersistenceConventionsTest`) stay GREEN UNAMENDED —
 * this change adds only this test file (no production code, no model).
 *
 * DatabaseMigrations (per design D11 + the section-5/6 standing rule): every Action opens its OWN top-level
 * `DB::transaction`, so the recorder's `transactionLevel() === 0` guard sees a real commit and the inline
 * `ProducerLifecycleProjector` fires on the post-commit hook — which `RefreshDatabase`'s wrapping transaction
 * would suppress. This is the SQLite lane; the task's cross-engine gate re-runs the whole Catalog suite + the
 * architecture tests on PostgreSQL 17 (knowledge/testing/rules.md). Event order is asserted by ascending
 * `domain_events.id` and payloads by key — never a byte-compare of stored jsonb (PG reorders keys, trap 3).
 */
uses(DatabaseMigrations::class);

/**
 * Stand up a fully-`active` Module-0 spine on a genuinely Module-K-activated Producer, every transition through
 * the real Actions. The Producer is created with the Parties factory (born `draft`, records no event) and
 * activated with {@see ActivateProducer} (the real `ProducerActivated` the inline projector consumes into
 * `catalog_producer_states`). Each spine entity is then create + submit + approve under THREE distinct operators
 * (the Creator → Reviewer → Approver floor at the default role_count 3), processed parent-before-child so each
 * activation clears its gate: the Master against the producer projection, the children against their in-module
 * parents. Distinctly named (`chain*`) so the one shared Pest namespace carries no redeclare against the sibling
 * helpers (`cascadeReviewedSpine`, `retirementActiveTree`, …) (Codebase Patterns #20).
 *
 * @return array{
 *     producer: Producer,
 *     master: ProductMaster,
 *     variant: ProductVariant,
 *     format: Format,
 *     reference: ProductReference,
 *     caseConfiguration: CaseConfiguration,
 *     sku: SellableSku,
 * }
 */
function chainActiveSpineUnderRealProducer(): array
{
    // The REAL cross-module enable path: a Parties Producer (factory → `draft`) activated through Module K's
    // ActivateProducer. The recorded ProducerActivated fans out (post-commit, txn level 0) to Catalog's
    // ProducerLifecycleProjector, which upserts catalog_producer_states — the read model the Master gate reads.
    // Activation now enforces the separation-of-duties floor (change parties-producer-approval-sod), so it needs an
    // authenticated operator; the factory Producer has no ProducerCreated lineage → a null creator, so the
    // distinctness check is vacuous and any single operator activates it.
    $producer = Producer::factory()->create();
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateProducer::class)->handle($producer->id);

    // Master — gated on the producer projection being `active` (the cross-module Producer Activation Gate).
    actingAs(Operator::factory()->create(), 'operator');
    $master = app(CreateProductMaster::class)->handle(name: 'Château Margaux', producerId: $producer->id, appellation: 'Margaux', region: 'Bordeaux');
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateProductMaster::class)->handle($master);

    // Variant — gated on its parent Master being `active` (within-module activation cascade).
    actingAs(Operator::factory()->create(), 'operator');
    $variant = app(CreateProductVariant::class)->handle(productMasterId: $master->id, variantIdentifier: '2015');
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateProductVariant::class)->handle($variant);

    // Format — standalone (approval governance only, no parent gate).
    actingAs(Operator::factory()->create(), 'operator');
    $format = app(CreateFormat::class)->handle(name: 'Magnum', sizeLabel: '1.5L', volumeMl: 1500);
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitFormatForReview::class)->handle($format);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateFormat::class)->handle($format);

    // Product Reference — gated on BOTH the Variant and the Format being `active`.
    actingAs(Operator::factory()->create(), 'operator');
    $reference = app(CreateProductReference::class)->handle(productVariantId: $variant->id, formatId: $format->id);
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateProductReference::class)->handle($reference);

    // Case Configuration — standalone.
    actingAs(Operator::factory()->create(), 'operator');
    $caseConfiguration = app(CreateCaseConfiguration::class)->handle(name: 'Original Wooden Case (6)', unitsPerCase: 6, packagingType: 'owc');
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitCaseConfigurationForReview::class)->handle($caseConfiguration);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateCaseConfiguration::class)->handle($caseConfiguration);

    // Sellable SKU — gated on BOTH the Product Reference and the Case Configuration being `active`.
    actingAs(Operator::factory()->create(), 'operator');
    $sku = app(CreateSellableSku::class)->handle(productReferenceId: $reference->id, caseConfigurationId: $caseConfiguration->id, commercialName: 'Château Margaux 2015 — Magnum (OWC 6)');
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateSellableSku::class)->handle($sku);

    return [
        'producer' => $producer,
        'master' => $master->refresh(),
        'variant' => $variant->refresh(),
        'format' => $format->refresh(),
        'reference' => $reference->refresh(),
        'caseConfiguration' => $caseConfiguration->refresh(),
        'sku' => $sku->refresh(),
    ];
}

it('drives the real cross-module lifecycle — ActivateProducer enables the gate, the spine activates parent-before-child, and the operator cascade retires it parent-before-child with no *Reviewed event', function () {
    $spine = chainActiveSpineUnderRealProducer();

    // ENABLE (AC-K-XM-2): the genuine Module-K ProducerActivated was consumed into Catalog's projection. The
    // factory records no event, so a projected `active` row PROVES the real ActivateProducer → recorder →
    // inline ProducerLifecycleProjector fan-out ran (not a hand-built event).
    expect(DomainEvent::query()->where('module', Module::Parties->value)->where('name', ProducerLifecycleProjector::PRODUCER_ACTIVATED)->count())->toBe(1);
    expect(ProducerState::query()->where('producer_id', $spine['producer']->id)->sole()->status)->toBe(ProducerProjectionStatus::Active);

    // The whole spine reached `active` through the real gated Actions — the cross-module Producer gate on the
    // Master, the within-module cascade gates on the children.
    expect(ProductMaster::findOrFail($spine['master']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductVariant::findOrFail($spine['variant']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(Format::findOrFail($spine['format']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductReference::findOrFail($spine['reference']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(CaseConfiguration::findOrFail($spine['caseConfiguration']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(SellableSku::findOrFail($spine['sku']->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // The four hierarchy *Activated events recorded in ascending domain_events.id = parent-before-child order
    // (§ 14.3 / AC-0-FSM-10): each Action committed in its own transaction, and a child could never reach
    // `active` before its parent (the gate would block it), so this is the only order the cascade permits.
    expect(DomainEvent::query()
        ->whereIn('name', [ProductMasterActivated::NAME, ProductVariantActivated::NAME, ProductReferenceActivated::NAME, SellableSKUActivated::NAME])
        ->orderBy('id')->pluck('name')->all())
        ->toBe([ProductMasterActivated::NAME, ProductVariantActivated::NAME, ProductReferenceActivated::NAME, SellableSKUActivated::NAME]);

    // The operator-driven cascade retires the Master + its descendants in one workflow.
    actingAs(Operator::factory()->create(), 'operator');
    app(RetireProductMasterCascade::class)->handle($spine['master']);

    // The ownership tree (Master → Variant → PR → SKU) reached `retired`; the STANDALONE reference entities
    // (Format, Case Configuration) are NOT descended into by the cascade and stay `active` (§ 4.7).
    expect(ProductMaster::findOrFail($spine['master']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductVariant::findOrFail($spine['variant']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductReference::findOrFail($spine['reference']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(SellableSku::findOrFail($spine['sku']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(Format::findOrFail($spine['format']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(CaseConfiguration::findOrFail($spine['caseConfiguration']->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // The four hierarchy *Retired events recorded in ascending id = parent-before-child order (§ 14.3 /
    // AC-0-FSM-11) — the cascade records them Master → Variant → PR → SKU explicitly inside one transaction.
    expect(DomainEvent::query()
        ->whereIn('name', [ProductMasterRetired::NAME, ProductVariantRetired::NAME, ProductReferenceRetired::NAME, SellableSKURetired::NAME])
        ->orderBy('id')->pluck('name')->all())
        ->toBe([ProductMasterRetired::NAME, ProductVariantRetired::NAME, ProductReferenceRetired::NAME, SellableSKURetired::NAME]);

    // NO *Reviewed event exists anywhere across the whole lifecycle — the six `draft → reviewed` submit
    // checkpoints are audit-only (§ 4.2); the only lifecycle events in the chain are *Activated then *Retired.
    expect(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('blocks a new Master activation after a real ProducerRetired while preserving existing actives (AC-K-XM-2 block-new / preserve)', function () {
    $spine = chainActiveSpineUnderRealProducer();

    // A NEW Master on the SAME producer (distinct identity — BR-Identity-1), submitted to `reviewed`. This is
    // the activation that must be blocked once the producer retires.
    actingAs(Operator::factory()->create(), 'operator');
    $newMaster = app(CreateProductMaster::class)->handle(name: 'Château Latour', producerId: $spine['producer']->id, appellation: 'Pauillac', region: 'Bordeaux');
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitProductMasterForReview::class)->handle($newMaster);

    // Retire the Producer through the REAL Module-K RetireProducer — its ProducerRetired is consumed into the
    // projection by the same inline consumer (the producer has no Clubs, so no within-Parties sunset cascade).
    app(RetireProducer::class)->handle($spine['producer']->id);

    // BLOCK-NEW (AC-K-XM-2): the consumer transitioned the projection to `retired` off the genuine event.
    expect(DomainEvent::query()->where('module', Module::Parties->value)->where('name', ProducerLifecycleProjector::PRODUCER_RETIRED)->count())->toBe(1);
    expect(ProducerState::query()->where('producer_id', $spine['producer']->id)->sole()->status)->toBe(ProducerProjectionStatus::Retired);

    // PRESERVE (AC-0-FSM-13 / BR-Lifecycle-4): consuming ProducerRetired NEVER transitions an existing Product
    // Master — the already-`active` chain is untouched (block-new, never cascade-retire).
    expect(ProductMaster::findOrFail($spine['master']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductVariant::findOrFail($spine['variant']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductReference::findOrFail($spine['reference']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(SellableSku::findOrFail($spine['sku']->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // BLOCK-NEW (AC-0-EVT-21 / AC-0-FSM-12): the new Master can no longer be activated — the gate reads the
    // now-`retired` projection and rejects, leaving it in `reviewed` with no ProductMasterActivated recorded.
    actingAs(Operator::factory()->create(), 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($newMaster))
        ->toThrow(ProducerActivationGateViolation::class);

    expect(ProductMaster::findOrFail($newMaster->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', ProductMasterActivated::NAME)->count())->toBe(1); // only the original chain's
});
