<?php

use App\Modules\Catalog\Actions\ResubmitCaseConfigurationForReview;
use App\Modules\Catalog\Actions\ResubmitCompositeSkuForReview;
use App\Modules\Catalog\Actions\ResubmitFormatForReview;
use App\Modules\Catalog\Actions\ResubmitProductReferenceForReview;
use App\Modules\Catalog\Actions\ResubmitProductVariantForReview;
use App\Modules\Catalog\Actions\ResubmitSellableSkuForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use Illuminate\Foundation\Testing\DatabaseMigrations;

use function Pest\Laravel\actingAs;

/**
 * The six thin per-entity Resubmit actions for the OTHER spine entities (task 3.1; design D2; product-catalog
 * — Requirement: Approval Governance): Product Variant, Product Reference, Format, Case Configuration, Sellable
 * SKU, Composite SKU. Where {@see ProductMasterLifecycleTest} exhaustively pins the shared
 * {@see LifecycleTransition::resubmit()} mechanism (audit-only, no event, from-
 * state guard, operator floor) + the Master wiring, THIS proves each of the six other actions is wired THIN —
 * it delegates to `resubmit()` with ITS canonical entity label, so the re-submit stays in `reviewed` and
 * records exactly one `catalog.<segment>.resubmitted` audit row whose `entity_type` is that label.
 *
 * The label is load-bearing: the derive-from-audit reviewer / activation block-gate reads filter on
 * `entity_type`, so a copy-paste label would silently mis-scope re-submits — this test is where that would
 * surface. Each row is read by its table-derived (label-INDEPENDENT) `action` segment, so the `entity_type`
 * assertion is a genuine label check, never a tautology. The reject → block → re-submit → activate uniformity
 * across all seven entities is task 3.2's cross-entity proof; here the wiring is pinned in isolation.
 *
 * DatabaseMigrations (consistent with the sibling `*LifecycleTest` files): each Action opens its OWN top-level
 * DB::transaction, so the recorder commits at `transactionLevel() === 0` — the faithful production shape.
 */
uses(DatabaseMigrations::class);

/**
 * The `entity_type` on the SOLE audit row for a re-submit action segment — the canonical entity label the thin
 * action passed to `resubmit()`. Read by the table-derived, label-INDEPENDENT `action` segment (so the
 * returned `entity_type` is a genuine label check, not a tautology) and `->sole()` (so exactly one row per
 * segment is also asserted). Distinctly named for the one shared Pest namespace (Codebase Patterns #20).
 */
function resubmittedEntityType(string $action): string
{
    return AuditRecord::query()->where('action', $action)->sole()->entity_type;
}

it('wires each of the six spine Resubmit actions thin — delegating to resubmit() with its canonical entity label', function () {
    // A re-submit is a `reviewed → reviewed` Creator decision, so it needs an operator principal. Each entity
    // is factory-built directly in `reviewed` — `resubmit()` asserts only the from-state, not HOW it got there
    // (the full reject → re-submit lineage is ProductMasterLifecycleTest / task 3.2) — then driven once.
    actingAs(Operator::factory()->create(), 'operator');

    $variant = app(ResubmitProductVariantForReview::class)->handle(
        ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Reviewed])
    );
    $reference = app(ResubmitProductReferenceForReview::class)->handle(
        ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Reviewed])
    );
    $format = app(ResubmitFormatForReview::class)->handle(
        Format::factory()->create(['lifecycle_state' => LifecycleState::Reviewed])
    );
    $caseConfiguration = app(ResubmitCaseConfigurationForReview::class)->handle(
        CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Reviewed])
    );
    $sellableSku = app(ResubmitSellableSkuForReview::class)->handle(
        SellableSku::factory()->create(['lifecycle_state' => LifecycleState::Reviewed])
    );
    $compositeSku = app(ResubmitCompositeSkuForReview::class)->handle(
        CompositeSku::factory()->create(['lifecycle_state' => LifecycleState::Reviewed])
    );

    // Each action kept its entity in `reviewed` (§ 4.3 — a re-submit changes no state; the twin of reject).
    expect($variant->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and($reference->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and($format->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and($caseConfiguration->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and($sellableSku->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and($compositeSku->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // Each recorded exactly one `.resubmitted` row whose `entity_type` is the action's CANONICAL label — the
    // proof each thin action delegated to `resubmit()` with the right label (matching the entity's
    // `Reject*Review` / `*Activated::ENTITY_TYPE`).
    expect(resubmittedEntityType('catalog.product_variant.resubmitted'))->toBe('ProductVariant')
        ->and(resubmittedEntityType('catalog.product_reference.resubmitted'))->toBe('ProductReference')
        ->and(resubmittedEntityType('catalog.format.resubmitted'))->toBe('Format')
        ->and(resubmittedEntityType('catalog.case_configuration.resubmitted'))->toBe('CaseConfiguration')
        ->and(resubmittedEntityType('catalog.sellable_sku.resubmitted'))->toBe('SellableSku')
        ->and(resubmittedEntityType('catalog.composite_sku.resubmitted'))->toBe('CompositeSku');

    // Exactly six re-submit rows in total — one per action, no strays (the factory-built PARENT fixtures record
    // no audit rows; only the six explicit re-submits do).
    expect(AuditRecord::query()->where('action', 'like', 'catalog.%.resubmitted')->count())->toBe(6);
});
