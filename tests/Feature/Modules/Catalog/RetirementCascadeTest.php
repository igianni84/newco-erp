<?php

use App\Modules\Catalog\Actions\ActivateProductVariant;
use App\Modules\Catalog\Actions\RetireCaseConfiguration;
use App\Modules\Catalog\Actions\RetireProductMaster;
use App\Modules\Catalog\Actions\RetireProductMasterCascade;
use App\Modules\Catalog\Actions\RetireProductReference;
use App\Modules\Catalog\Actions\RetireProductVariant;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\CompositeSKURetired;
use App\Modules\Catalog\Events\ProductMasterRetired;
use App\Modules\Catalog\Events\ProductReferenceRetired;
use App\Modules\Catalog\Events\ProductVariantRetired;
use App\Modules\Catalog\Events\SellableSKURetired;
use App\Modules\Catalog\Exceptions\ActivationCascadeViolation;
use App\Modules\Catalog\Exceptions\RetirementReferenceIntegrityViolation;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Events\ActorRole;
use App\Platform\Events\ConsumerRegistry;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\Events\InlineDeliveryExecutor;
use Illuminate\Foundation\Testing\DatabaseMigrations;

use function Pest\Laravel\actingAs;

/**
 * Pins the retirement cascade + the within-catalog reference-integrity guard (catalog-lifecycle-approval task
 * 5.2; design D8; product-catalog — Requirement: Retirement Cascade and Reference Integrity; Module 0 PRD
 * § 4.5 / § 4.6 / § 4.7 / § 14.3 / AC-0-FSM-11). Scope per the founder's resolution
 * (`decisions/2026-06-16-catalog-retirement-reference-integrity-scope.md`, Option B): the within-catalog block
 * covers the TERMINAL SELLABLE EDGE only (a PR / Case Configuration referenced by an `active` SKU), while a
 * HIERARCHY PARENT (a Master with `active` Variants, a Variant with `active` PRs) is single-retirable and
 * PRESERVES its children. Four properties:
 *
 *   (a) BLOCK — a single-entity retire of a PR referenced by an `active` Sellable / Composite SKU, or of a Case
 *       Configuration referenced by an `active` Sellable SKU, is rejected ({@see RetirementReferenceIntegrityViolation})
 *       surfacing the open references; the entity stays `active`.
 *   (b) PRESERVE — a single-entity retire of a hierarchy parent (Master with an `active` Variant, Variant with
 *       an `active` PR) SUCCEEDS and preserves the children (they stay `active`); only NEW activation under the
 *       now-`retired` parent is prevented (the activation-cascade gate, design D7).
 *   (c) CASCADE — the operator-driven {@see RetireProductMasterCascade} retires the whole tree parent-before-child
 *       in one transaction, recording each `*Retired` in ascending `domain_events.id` = hierarchy order.
 *   (d) ATOMICITY — a forced failure mid-cascade rolls the entire tree back (all-or-nothing; invariant 4).
 *
 * The shared mechanism internals (the locked from-state re-read, the audit envelope, the operator floor) are
 * exhaustively pinned by ProductMasterLifecycleTest; the per-entity gates / events by the sibling
 * `*LifecycleTest` files. THESE tests prove the retirement-side composition. Fixtures use the model FACTORIES
 * to stand entities up directly in `active` (the gate and the cascade read only `lifecycle_state`, so a
 * factory-active entity is a legitimate fixture — and the factories bypass the Create actions, so the only
 * domain events in play are the cascade's own `*Retired`, making the id-order assertion exact).
 *
 * DatabaseMigrations (per the section-5 standing rule + design D11): each retire / the cascade opens its OWN
 * top-level DB::transaction, so the recorder's `transactionLevel() === 0` guard sees a real commit and the
 * atomicity rollback is a genuine top-level rollback (not a savepoint inside a test wrapper).
 */
uses(DatabaseMigrations::class);

/**
 * Stand up a fully-`active` Module-0 ownership tree — Master → Variant → Format → Product Reference → Case
 * Configuration → Sellable SKU — directly through the factories (no activation ceremony; the retire path reads
 * only `lifecycle_state`). Distinctly named (`retirement*`) so the one shared Pest namespace carries no
 * redeclare against the sibling tests' helpers (Codebase Patterns #20).
 *
 * @return array{
 *     master: ProductMaster,
 *     variant: ProductVariant,
 *     format: Format,
 *     reference: ProductReference,
 *     caseConfiguration: CaseConfiguration,
 *     sellableSku: SellableSku,
 * }
 */
function retirementActiveTree(): array
{
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $variant = ProductVariant::factory()->create(['product_master_id' => $master->id, 'lifecycle_state' => LifecycleState::Active]);
    $format = Format::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $reference = ProductReference::factory()->create([
        'product_variant_id' => $variant->id,
        'format_id' => $format->id,
        'lifecycle_state' => LifecycleState::Active,
    ]);
    $caseConfiguration = CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $sellableSku = SellableSku::factory()->create([
        'product_reference_id' => $reference->id,
        'case_configuration_id' => $caseConfiguration->id,
        'lifecycle_state' => LifecycleState::Active,
    ]);

    return [
        'master' => $master,
        'variant' => $variant,
        'format' => $format,
        'reference' => $reference,
        'caseConfiguration' => $caseConfiguration,
        'sellableSku' => $sellableSku,
    ];
}

// ---------------------------------------------------------------------------------------------------------
// (a) BLOCK — the terminal sellable edge (Option B): a referenced entity may not be retired out from under an
//     `active` SKU; the open references are surfaced and the entity stays `active`.
// ---------------------------------------------------------------------------------------------------------

it('blocks retiring a Product Reference referenced by an active Sellable SKU, surfacing the open SKU', function () {
    $reference = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $caseConfiguration = CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $sku = SellableSku::factory()->create([
        'product_reference_id' => $reference->id,
        'case_configuration_id' => $caseConfiguration->id,
        'lifecycle_state' => LifecycleState::Active,
    ]);

    actingAs(Operator::factory()->create(), 'operator');

    expect(fn () => app(RetireProductReference::class)->handle($reference))
        ->toThrow(RetirementReferenceIntegrityViolation::class, 'SellableSku#'.$sku->id);

    // The PR is untouched — the gate rolled the transition back; no *Retired recorded.
    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceRetired')->count())->toBe(0);
});

it('blocks retiring a Product Reference bundled by an active Composite SKU, surfacing the open SKU', function () {
    $reference = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    // hasAttached runs before the factory's afterCreating (which auto-attaches two PRs only when the bundle is
    // empty), so the Composite has exactly this constituent — the within-module junction the gate reads.
    $composite = CompositeSku::factory()
        ->hasAttached($reference, ['position' => 1], 'constituents')
        ->create(['lifecycle_state' => LifecycleState::Active]);

    actingAs(Operator::factory()->create(), 'operator');

    expect(fn () => app(RetireProductReference::class)->handle($reference))
        ->toThrow(RetirementReferenceIntegrityViolation::class, 'CompositeSku#'.$composite->id);

    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Active);
});

it('blocks retiring a Case Configuration referenced by an active Sellable SKU, surfacing the open SKU', function () {
    $caseConfiguration = CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $reference = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $sku = SellableSku::factory()->create([
        'product_reference_id' => $reference->id,
        'case_configuration_id' => $caseConfiguration->id,
        'lifecycle_state' => LifecycleState::Active,
    ]);

    actingAs(Operator::factory()->create(), 'operator');

    expect(fn () => app(RetireCaseConfiguration::class)->handle($caseConfiguration))
        ->toThrow(RetirementReferenceIntegrityViolation::class, 'SellableSku#'.$sku->id);

    expect(CaseConfiguration::findOrFail($caseConfiguration->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'CaseConfigurationRetired')->count())->toBe(0);
});

it('allows retiring a Product Reference once no active SKU references it (a retired SKU does not block)', function () {
    $reference = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $caseConfiguration = CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    // A SKU that already closed (retired) is not an open reference — the gate clears.
    SellableSku::factory()->create([
        'product_reference_id' => $reference->id,
        'case_configuration_id' => $caseConfiguration->id,
        'lifecycle_state' => LifecycleState::Retired,
    ]);

    actingAs(Operator::factory()->create(), 'operator');
    $retired = app(RetireProductReference::class)->handle($reference);

    expect($retired->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceRetired')->count())->toBe(1);
});

// ---------------------------------------------------------------------------------------------------------
// (b) PRESERVE — a hierarchy parent is NOT blocked on its children: its single-entity retire succeeds, the
//     existing active children stay active, only NEW activation under the retired parent is prevented.
// ---------------------------------------------------------------------------------------------------------

it('preserves an active Variant when its Master is retired single-entity, and blocks new activation under it', function () {
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $variant = ProductVariant::factory()->create(['product_master_id' => $master->id, 'lifecycle_state' => LifecycleState::Active]);

    actingAs(Operator::factory()->create(), 'operator');
    $retired = app(RetireProductMaster::class)->handle($master);

    // The Master retires (no reference-integrity guard on a hierarchy parent) and the existing Variant is
    // PRESERVED — it stays active for its current lifecycle (§ 4.5 / BR-Lifecycle-4 — no retroactive invalidation).
    expect($retired->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // But a NEW child may not be activated under the now-retired Master (the activation-cascade gate, design D7).
    $newVariant = ProductVariant::factory()->create(['product_master_id' => $master->id, 'lifecycle_state' => LifecycleState::Reviewed]);
    actingAs(Operator::factory()->create(), 'operator');
    expect(fn () => app(ActivateProductVariant::class)->handle($newVariant))
        ->toThrow(ActivationCascadeViolation::class, 'ProductMaster');
});

it('preserves an active Product Reference when its Variant is retired single-entity', function () {
    $variant = ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $reference = ProductReference::factory()->create(['product_variant_id' => $variant->id, 'lifecycle_state' => LifecycleState::Active]);

    actingAs(Operator::factory()->create(), 'operator');
    $retired = app(RetireProductVariant::class)->handle($variant);

    expect($retired->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Active);
});

// ---------------------------------------------------------------------------------------------------------
// (c) CASCADE — the operator-driven workflow retires the whole tree parent-before-child in one transaction.
// ---------------------------------------------------------------------------------------------------------

it('retires a Master and its descendants parent-before-child, recording *Retired in ascending id order', function () {
    $tree = retirementActiveTree();
    // A Composite SKU over the same PR — the leaf level also covers the constituent-junction branch.
    $composite = CompositeSku::factory()
        ->hasAttached($tree['reference'], ['position' => 1], 'constituents')
        ->create(['lifecycle_state' => LifecycleState::Active]);

    actingAs(Operator::factory()->create(), 'operator');
    $retired = app(RetireProductMasterCascade::class)->handle($tree['master']);

    // The whole tree reached `retired` (the persisted rows).
    expect($retired->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductMaster::findOrFail($tree['master']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductVariant::findOrFail($tree['variant']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductReference::findOrFail($tree['reference']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(SellableSku::findOrFail($tree['sellableSku']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    // The `*Retired` events appear in ascending domain_events.id = parent-before-child order (§ 14.3): the
    // cascade records them Master → Variant → PR → SKUs explicitly inside one transaction (Pattern #24 —
    // unlike the activation cascade, a single-transaction multi-event cascade orders by emission sequence).
    $order = DomainEvent::query()
        ->whereIn('name', [
            ProductMasterRetired::NAME,
            ProductVariantRetired::NAME,
            ProductReferenceRetired::NAME,
            SellableSKURetired::NAME,
            CompositeSKURetired::NAME,
        ])
        ->orderBy('id')
        ->pluck('name')
        ->all();

    expect($order)->toBe([
        ProductMasterRetired::NAME,
        ProductVariantRetired::NAME,
        ProductReferenceRetired::NAME,
        SellableSKURetired::NAME,
        CompositeSKURetired::NAME,
    ]);
});

it('skips non-active descendants in the cascade but still retires the active subtree', function () {
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $activeVariant = ProductVariant::factory()->create(['product_master_id' => $master->id, 'lifecycle_state' => LifecycleState::Active]);
    // A draft Variant under the same Master was never live — it has no active → retired edge, so the cascade
    // leaves it (it cannot be activated under the now-retired Master anyway — block-new).
    $draftVariant = ProductVariant::factory()->create(['product_master_id' => $master->id, 'lifecycle_state' => LifecycleState::Draft]);

    actingAs(Operator::factory()->create(), 'operator');
    app(RetireProductMasterCascade::class)->handle($master);

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductVariant::findOrFail($activeVariant->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductVariant::findOrFail($draftVariant->id)->lifecycle_state)->toBe(LifecycleState::Draft);
});

// ---------------------------------------------------------------------------------------------------------
// (d) ATOMICITY — a forced failure mid-cascade rolls the whole tree back (all-or-nothing).
// ---------------------------------------------------------------------------------------------------------

it('rolls the whole tree back when a retire fails mid-cascade (all-or-nothing)', function () {
    $tree = retirementActiveTree();

    // A throwing recorder decorator: it delegates every event EXCEPT ProductReferenceRetired (the third level),
    // which it rejects — so the cascade fails AFTER the Master + Variant have already been retired in the same
    // transaction. Bound before the cascade resolves, so its LifecycleTransition is injected with this recorder.
    app()->bind(DomainEventRecorder::class, fn () => new class(app(ConsumerRegistry::class), app(InlineDeliveryExecutor::class)) extends DomainEventRecorder
    {
        /**
         * @param  array<string, mixed>  $payload
         */
        public function record(
            string $name,
            string $module,
            ActorRole $actorRole,
            ?int $actorId,
            string $entityType,
            string $entityId,
            array $payload,
            ?string $correlationId = null,
            ?int $causationId = null,
        ): DomainEvent {
            if ($name === ProductReferenceRetired::NAME) {
                throw new RuntimeException('forced mid-cascade failure (atomicity probe)');
            }

            return parent::record($name, $module, $actorRole, $actorId, $entityType, $entityId, $payload, $correlationId, $causationId);
        }
    });

    actingAs(Operator::factory()->create(), 'operator');

    expect(fn () => app(RetireProductMasterCascade::class)->handle($tree['master']))
        ->toThrow(RuntimeException::class, 'forced mid-cascade failure');

    // Nothing is half-retired: the Master + Variant retires (recorded before the failing PR) rolled back with
    // the rest, and NO *Retired event survived the rolled-back transaction.
    expect(ProductMaster::findOrFail($tree['master']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductVariant::findOrFail($tree['variant']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductReference::findOrFail($tree['reference']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(SellableSku::findOrFail($tree['sellableSku']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0);
});
