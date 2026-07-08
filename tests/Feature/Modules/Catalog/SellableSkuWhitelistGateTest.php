<?php

use App\Modules\Catalog\Actions\ActivateSellableSku;
use App\Modules\Catalog\Actions\CreateSellableSku;
use App\Modules\Catalog\Actions\SetVariantCaseWhitelist;
use App\Modules\Catalog\Actions\SubmitSellableSkuForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ActivationCascadeViolation;
use App\Modules\Catalog\Exceptions\CaseConfigurationNotWhitelisted;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\Catalog\Models\VariantCaseWhitelistEntry;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;

use function Pest\Laravel\actingAs;

/**
 * Pins the Layer-1 whitelist's ENFORCEMENT half — the case-configuration gate on a Sellable SKU's
 * `reviewed → active` transition (catalog-module-0-completeness-sweep task 3.2; design D6, risk R10;
 * product-catalog — Requirement: Layer-1 Case-Configuration Whitelist; Module 0 PRD § 7.1 + § 4.5,
 * AC-0-J-13). The maintenance half (the writer) is pinned by `SetVariantCaseWhitelistTest`.
 *
 * Three facts carry the requirement, and each has a test below:
 *   1. PERMISSIVE DEFAULT — a (Variant, Format) pair with zero whitelist rows admits every Case
 *      Configuration; presence narrows, absence admits (§ 7.1). The gate must not fail closed.
 *   2. PER-PAIR SCOPING — the whitelist is keyed on the pair, not on the Variant, so narrowing one Format's
 *      set leaves the SAME Variant's other formats permissive.
 *   3. NON-RETROACTIVITY (R10 / § 4.5's retirement-cascade semantics) — the whitelist is consulted ONLY at
 *      activation. Removing an admitted Case Configuration never reaches back into an already-`active` SKU
 *      that references it: no state change, no audit row, no event. Only the NEXT activation is blocked.
 *
 * The gate is ordered LAST among the activation conjuncts, after both cascade parents — so a SKU that is
 * both non-whitelisted AND sitting on a non-`active` Case Configuration is rejected for the state, which is
 * the fact the operator can act on first. That precedence is pinned with a deliberately doubly-invalid SKU.
 *
 * DatabaseMigrations (the standing rule for Action-driven catalog tests): the lifecycle mechanism opens its
 * OWN top-level `DB::transaction`, so the audit/event recorders' `transactionLevel() === 0` guard sees a real
 * commit — which `RefreshDatabase`'s wrapping transaction would suppress. Spine FIXTURES come from the
 * factories (which bypass the creation Actions and record nothing); every `SellableSKUActivated` counted below
 * is attributable to an `ActivateSellableSku` call actually made.
 */
uses(DatabaseMigrations::class);

/**
 * An `active` Product Reference over an `active` Variant + Format, returned with both parents — the fixture
 * every scenario starts from. Distinctly named: Pest's top-level functions share one global namespace
 * (knowledge/testing/rules.md), and `SellableSkuLifecycleTest` already owns `sellableSkuLifecycle*`.
 *
 * @return array{ProductReference, ProductVariant, Format}
 */
function whitelistGateActiveReference(?ProductVariant $variant = null): array
{
    $variant ??= ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $format = Format::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    $reference = ProductReference::factory()->create([
        'product_variant_id' => $variant->id,
        'format_id' => $format->id,
        'lifecycle_state' => LifecycleState::Active,
    ]);

    return [$reference, $variant, $format];
}

/**
 * Create + submit a Sellable SKU over the reference and Case Configuration through the REAL Actions, with a
 * distinct creator and reviewer, and leave a THIRD distinct operator (the approver) as the acting principal —
 * so the caller's `ActivateSellableSku` meets the separation-of-duties floor and the only thing left that can
 * reject it is a gate.
 */
function whitelistGateReviewedSku(ProductReference $reference, CaseConfiguration $caseConfiguration): SellableSku
{
    actingAs(Operator::factory()->create(), 'operator');
    $sku = app(CreateSellableSku::class)->handle(
        productReferenceId: $reference->id,
        caseConfigurationId: $caseConfiguration->id,
        commercialName: 'Château Margaux 2015 — Magnum',
    );

    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);

    actingAs(Operator::factory()->create(), 'operator');

    return $sku;
}

/** Persist one admitted triple directly — the fixture shape of a pre-existing whitelist. */
function whitelistGateAdmit(ProductVariant $variant, Format $format, CaseConfiguration $caseConfiguration): void
{
    VariantCaseWhitelistEntry::create([
        'product_variant_id' => $variant->id,
        'format_id' => $format->id,
        'case_configuration_id' => $caseConfiguration->id,
    ]);
}

it('blocks a new SKU activation on a removed Case Configuration while the already-active SKU stands (AC-0-J-13)', function () {
    // The J-13 fixture verbatim: an `active` Variant whose whitelist for one Format admits three Case
    // Configurations, and an `active` Sellable SKU referencing the first of them.
    [$reference, $variant, $format] = whitelistGateActiveReference();
    [$owc6, $carton12, $loose] = CaseConfiguration::factory()->count(3)->create(['lifecycle_state' => LifecycleState::Active])->all();

    whitelistGateAdmit($variant, $format, $owc6);
    whitelistGateAdmit($variant, $format, $carton12);
    whitelistGateAdmit($variant, $format, $loose);

    $standing = whitelistGateReviewedSku($reference, $owc6);
    app(ActivateSellableSku::class)->handle($standing);

    expect(SellableSku::findOrFail($standing->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Snapshot the standing SKU's whole footprint BEFORE the reduction — state, version, its audit rows, and
    // the one activation event. R10's claim is that none of these move.
    $standingAuditIds = AuditRecord::query()
        ->where('entity_type', 'SellableSku')
        ->where('entity_id', (string) $standing->id)
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($standingAuditIds)->toHaveCount(2)   // created is event-only; submitted + activated are the audit rows
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(1);

    // An operator removes OWC6 from the pair's whitelist, through the real maintenance Action.
    actingAs(Operator::factory()->create(), 'operator');
    app(SetVariantCaseWhitelist::class)->handle($variant, $format->id, [$carton12->id, $loose->id]);

    // NON-RETROACTIVITY: the standing SKU is untouched — same state, same version, not one new audit row, and
    // still exactly one activation event. The reduction reached nothing that was already `active`.
    $standingAfter = SellableSku::findOrFail($standing->id);

    expect($standingAfter->lifecycle_state)->toBe(LifecycleState::Active)
        ->and($standingAfter->version)->toBe(1)
        ->and(AuditRecord::query()->where('entity_type', 'SellableSku')->where('entity_id', (string) $standing->id)->orderBy('id')->pluck('id')->all())->toBe($standingAuditIds)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(1);

    // The whitelist change itself is audit-only: before/after sets, no event, no Variant `version` bump.
    $whitelistAudit = AuditRecord::query()->where('action', 'catalog.product_variant.whitelist_updated')->sole();
    $expectedBefore = collect([$owc6->id, $carton12->id, $loose->id])->sort()->values()->all();
    $expectedAfter = collect([$carton12->id, $loose->id])->sort()->values()->all();

    expect($whitelistAudit->before)->toEqual(['format_id' => $format->id, 'case_configurations' => $expectedBefore])
        ->and($whitelistAudit->after)->toEqual(['format_id' => $format->id, 'case_configurations' => $expectedAfter])
        ->and(ProductVariant::findOrFail($variant->id)->version)->toBe(1);

    // A NEW SKU on the now-excluded OWC6, over the SAME pair, created and submitted normally — its activation
    // is the only surface the whitelist gates, and it is rejected there.
    $blocked = whitelistGateReviewedSku($reference, $owc6);

    expect(fn () => app(ActivateSellableSku::class)->handle($blocked))
        ->toThrow(CaseConfigurationNotWhitelisted::class, 'SellableSku');

    // The blocked SKU stays `reviewed`, records no activation audit row, and adds no `SellableSKUActivated`:
    // the transition's transaction rolled back whole.
    expect(SellableSku::findOrFail($blocked->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->where('action', 'catalog.sellable_sku.activated')->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(1);
});

it('admits any Case Configuration when the pair has no whitelist rows (the permissive default)', function () {
    [$reference, $variant, $format] = whitelistGateActiveReference();
    $caseConfiguration = CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    // Zero rows for the pair — § 7.1's default. Absence admits; the gate must not fail closed like the
    // cascade gates do (an absent whitelist is a statement about nothing, not about this SKU).
    expect(VariantCaseWhitelistEntry::query()->where('product_variant_id', $variant->id)->where('format_id', $format->id)->count())->toBe(0);

    $sku = whitelistGateReviewedSku($reference, $caseConfiguration);
    $active = app(ActivateSellableSku::class)->handle($sku);

    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(1);
});

it('admits a whitelisted Case Configuration on a narrowed pair', function () {
    [$reference, $variant, $format] = whitelistGateActiveReference();
    [$owc6, $carton12] = CaseConfiguration::factory()->count(2)->create(['lifecycle_state' => LifecycleState::Active])->all();

    // A NON-empty whitelist: the gate now has an opinion, and OWC6 is in it.
    whitelistGateAdmit($variant, $format, $owc6);
    whitelistGateAdmit($variant, $format, $carton12);

    $sku = whitelistGateReviewedSku($reference, $owc6);
    $active = app(ActivateSellableSku::class)->handle($sku);

    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(1);
});

it('scopes the whitelist to the (Variant, Format) pair, leaving the same Variant\'s other formats permissive', function () {
    // ONE Variant, TWO formats, each with its own Product Reference. Only the first format's pair is narrowed.
    $variant = ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    [$narrowedReference, , $narrowedFormat] = whitelistGateActiveReference($variant);
    [$permissiveReference] = whitelistGateActiveReference($variant);

    [$owc6, $carton12] = CaseConfiguration::factory()->count(2)->create(['lifecycle_state' => LifecycleState::Active])->all();
    whitelistGateAdmit($variant, $narrowedFormat, $owc6);

    // The OTHER format's pair holds no rows, so CARTON12 activates there — the narrowing did not travel across
    // the Variant. (If the whitelist were keyed on the Variant alone, this would reject.)
    $permissive = whitelistGateReviewedSku($permissiveReference, $carton12);
    app(ActivateSellableSku::class)->handle($permissive);

    expect(SellableSku::findOrFail($permissive->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // The SAME Case Configuration on the NARROWED pair is rejected — the two pairs are independent.
    $blocked = whitelistGateReviewedSku($narrowedReference, $carton12);

    expect(fn () => app(ActivateSellableSku::class)->handle($blocked))
        ->toThrow(CaseConfigurationNotWhitelisted::class);

    expect(SellableSku::findOrFail($blocked->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(1);
});

it('rejects a non-active Case Configuration on the cascade gate before consulting the whitelist', function () {
    // Deliberately DOUBLY invalid: the Case Configuration is neither `active` NOR whitelisted. Both conjuncts
    // would reject, but the cascade runs first — the operator is told the fact they must fix first, and the
    // whitelist's permissive-default reasoning never runs against a parent that cannot be referenced anyway.
    [$reference, $variant, $format] = whitelistGateActiveReference();
    $admitted = CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $draft = CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Draft]);

    whitelistGateAdmit($variant, $format, $admitted);

    $sku = whitelistGateReviewedSku($reference, $draft);

    expect(fn () => app(ActivateSellableSku::class)->handle($sku))
        ->toThrow(ActivationCascadeViolation::class, 'CaseConfiguration');

    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(0);
});
