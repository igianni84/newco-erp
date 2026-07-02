<?php

use App\Modules\Catalog\Actions\ActivateCaseConfiguration;
use App\Modules\Catalog\Actions\ActivateCompositeSku;
use App\Modules\Catalog\Actions\ActivateFormat;
use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\ActivateProductReference;
use App\Modules\Catalog\Actions\ActivateProductVariant;
use App\Modules\Catalog\Actions\ActivateSellableSku;
use App\Modules\Catalog\Actions\CreateCaseConfiguration;
use App\Modules\Catalog\Actions\CreateCompositeSku;
use App\Modules\Catalog\Actions\CreateFormat;
use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\CreateProductReference;
use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Actions\CreateSellableSku;
use App\Modules\Catalog\Actions\RejectCaseConfigurationReview;
use App\Modules\Catalog\Actions\RejectCompositeSkuReview;
use App\Modules\Catalog\Actions\RejectFormatReview;
use App\Modules\Catalog\Actions\RejectProductMasterReview;
use App\Modules\Catalog\Actions\RejectProductReferenceReview;
use App\Modules\Catalog\Actions\RejectProductVariantReview;
use App\Modules\Catalog\Actions\RejectSellableSkuReview;
use App\Modules\Catalog\Actions\ResubmitCaseConfigurationForReview;
use App\Modules\Catalog\Actions\ResubmitCompositeSkuForReview;
use App\Modules\Catalog\Actions\ResubmitFormatForReview;
use App\Modules\Catalog\Actions\ResubmitProductMasterForReview;
use App\Modules\Catalog\Actions\ResubmitProductReferenceForReview;
use App\Modules\Catalog\Actions\ResubmitProductVariantForReview;
use App\Modules\Catalog\Actions\ResubmitSellableSkuForReview;
use App\Modules\Catalog\Actions\SubmitCaseConfigurationForReview;
use App\Modules\Catalog\Actions\SubmitCompositeSkuForReview;
use App\Modules\Catalog\Actions\SubmitFormatForReview;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Actions\SubmitProductReferenceForReview;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Actions\SubmitSellableSkuForReview;
use App\Modules\Catalog\Consumers\ProducerLifecycleProjector;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Lifecycle\ApprovalGovernance;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Module;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

/**
 * Proves the review-freshness reject → block → re-submit → activate cycle (RM-06 / canon MVP-DEC-019; design
 * D1/D2/D3; product-catalog — Requirement: Approval Governance) is UNIFORM across ALL SEVEN catalog spine
 * entities — not a Product-Master-only fix. Where {@see ProductMasterLifecycleTest} exhaustively pins the
 * mechanism + the block-gate's edge cases on the Master (single/never-rejected/reopened/two-round), and
 * {@see ResubmitActionsTest} pins each of the six other {@see ResubmitProductVariantForReview}-style
 * actions THIN in isolation, THIS drives the whole cycle per entity: the shared block-gate lives in
 * {@see ApprovalGovernance::guard()} (task 2.2), so it is ALREADY enforcing on
 * every entity the moment each can reach `reviewed`; this test proves the per-entity RE-ARM completes the loop
 * (the entity-specific `Resubmit*` clears the block and the SAME distinct approver then activates).
 *
 * The airtight per-entity proof is the delta: for one fixed entity, with a fixed distinct approver and every
 * activation prerequisite satisfied, activation flips from BLOCKED (the `un-remediated` token — unique to the
 * review-freshness block, absent from every separation-of-duties reason) to `active` with ONLY an explicit
 * re-submit in between. Nothing else changes, so the re-arm is the sole cause.
 *
 * Each entity is built to `reviewed` through the REAL create + submit Actions under a genuine
 * Creator → Reviewer → Approver lineage (three distinct operators — so "a distinct approver activates" is a real
 * separation-of-duties assertion, not a vacuous one), with the MINIMAL valid activation prerequisite per entity:
 *   - Product Master   — an `active`-projected Producer (the cross-module Producer Activation Gate).
 *   - Variant / PR / Sellable SKU / Composite SKU — factory-`active` parent(s) (the within-module activation
 *     cascade gate; factory-active parents are proven sufficient by the sibling `*LifecycleTest` positive/
 *     governance-precedes-the-gate paths).
 *   - Format / Case Configuration — standalone (governance alone gates activation).
 *
 * DatabaseMigrations (consistent with every sibling `*LifecycleTest`): each Action opens its OWN top-level
 * DB::transaction, so the recorder commits at `transactionLevel() === 0` and the inline
 * `ProducerLifecycleProjector` fires on the post-commit hook (the Master's producer projection) — which
 * `RefreshDatabase`'s wrapping transaction would suppress. The block-gate reads only `audit_records.action`
 * (a string column, engine-neutral); the task's cross-engine gate re-runs this on PostgreSQL 17.
 *
 * The shared body reads state via `Model::getAttribute('lifecycle_state')` (a real base-Model method returning
 * the cast enum), NOT the `->lifecycle_state` magic property — so the one generic body stays type-clean over the
 * base `Model` the per-entity scenarios return, with the entity-specific Actions bound concretely inside each
 * scenario's closures (no cross-entity union reaches the body — PHPStan max clean).
 */
uses(DatabaseMigrations::class);

/**
 * Project a Producer `active` in Catalog's OWN read model — record a Module-K `ProducerActivated` inside a real
 * transaction so the inline {@see ProducerLifecycleProjector} upserts
 * `catalog_producer_states` (the read model the Product Master activation gate reads). Mirrors the inline
 * projection in {@see lifecycleActiveParentMaster} / {@see compositeSkuLifecycleActiveReferences}. Distinctly
 * named for the one shared Pest namespace (Codebase Patterns #20).
 */
function reviewFreshnessProjectProducerActive(int $producerId): void
{
    DB::transaction(fn () => app(DomainEventRecorder::class)->record(
        name: 'ProducerActivated',
        module: Module::Parties->value,
        actorRole: ActorRole::System,
        actorId: null,
        entityType: 'Producer',
        entityId: (string) $producerId,
        payload: ['producer_id' => $producerId, 'status' => 'active'],
    ));
}

/**
 * Build a Product Master to `reviewed` under an `active`-projected Producer (Producer Activation Gate open),
 * created by $creator + submitted by $reviewer through the real Actions, with the three canonical Master
 * lifecycle closures (reject / re-submit / activate — bound concretely; the acting principal is set by the body).
 *
 * @return array{0: Model, 1: Closure, 2: Closure, 3: Closure}
 */
function reviewFreshnessMasterScenario(Operator $creator, Operator $reviewer): array
{
    reviewFreshnessProjectProducerActive(7);

    actingAs($creator, 'operator');
    $master = app(CreateProductMaster::class)->handle(name: 'Château Margaux', producerId: 7, appellation: 'Margaux', region: 'Bordeaux');
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);
    $master->refresh();

    return [
        $master,
        fn () => app(RejectProductMasterReview::class)->handle($master, 'Provenance note needs work.'),
        fn () => app(ResubmitProductMasterForReview::class)->handle($master),
        fn () => app(ActivateProductMaster::class)->handle($master),
    ];
}

/**
 * Build a Product Variant to `reviewed` under a factory-`active` parent Master (the within-module cascade gate
 * open — proven sufficient by {@see ProductVariantLifecycleTest}), created by $creator + submitted by $reviewer.
 *
 * @return array{0: Model, 1: Closure, 2: Closure, 3: Closure}
 */
function reviewFreshnessProductVariantScenario(Operator $creator, Operator $reviewer): array
{
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    actingAs($creator, 'operator');
    $variant = app(CreateProductVariant::class)->handle(productMasterId: $master->id, variantIdentifier: '2015');
    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);
    $variant->refresh();

    return [
        $variant,
        fn () => app(RejectProductVariantReview::class)->handle($variant, 'Vintage year is missing.'),
        fn () => app(ResubmitProductVariantForReview::class)->handle($variant),
        fn () => app(ActivateProductVariant::class)->handle($variant),
    ];
}

/**
 * Build a Format to `reviewed` — STANDALONE (no parent gate; governance alone gates activation), created by
 * $creator + submitted by $reviewer.
 *
 * @return array{0: Model, 1: Closure, 2: Closure, 3: Closure}
 */
function reviewFreshnessFormatScenario(Operator $creator, Operator $reviewer): array
{
    actingAs($creator, 'operator');
    $format = app(CreateFormat::class)->handle(name: 'Magnum', sizeLabel: '1.5L', volumeMl: 1500);
    actingAs($reviewer, 'operator');
    app(SubmitFormatForReview::class)->handle($format);
    $format->refresh();

    return [
        $format,
        fn () => app(RejectFormatReview::class)->handle($format, 'Size label needs review.'),
        fn () => app(ResubmitFormatForReview::class)->handle($format),
        fn () => app(ActivateFormat::class)->handle($format),
    ];
}

/**
 * Build a Product Reference to `reviewed` under a factory-`active` Variant AND Format (BOTH cascade gates open —
 * proven sufficient by {@see ProductReferenceLifecycleTest}), created by $creator + submitted by $reviewer.
 *
 * @return array{0: Model, 1: Closure, 2: Closure, 3: Closure}
 */
function reviewFreshnessProductReferenceScenario(Operator $creator, Operator $reviewer): array
{
    $variant = ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $format = Format::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    actingAs($creator, 'operator');
    $reference = app(CreateProductReference::class)->handle(productVariantId: $variant->id, formatId: $format->id);
    actingAs($reviewer, 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);
    $reference->refresh();

    return [
        $reference,
        fn () => app(RejectProductReferenceReview::class)->handle($reference, 'Format pairing needs sign-off.'),
        fn () => app(ResubmitProductReferenceForReview::class)->handle($reference),
        fn () => app(ActivateProductReference::class)->handle($reference),
    ];
}

/**
 * Build a Case Configuration to `reviewed` — STANDALONE (no parent gate), created by $creator + submitted by
 * $reviewer.
 *
 * @return array{0: Model, 1: Closure, 2: Closure, 3: Closure}
 */
function reviewFreshnessCaseConfigurationScenario(Operator $creator, Operator $reviewer): array
{
    actingAs($creator, 'operator');
    $caseConfiguration = app(CreateCaseConfiguration::class)->handle(name: 'Original Wooden Case (6)', unitsPerCase: 6, packagingType: 'owc');
    actingAs($reviewer, 'operator');
    app(SubmitCaseConfigurationForReview::class)->handle($caseConfiguration);
    $caseConfiguration->refresh();

    return [
        $caseConfiguration,
        fn () => app(RejectCaseConfigurationReview::class)->handle($caseConfiguration, 'Packaging type needs review.'),
        fn () => app(ResubmitCaseConfigurationForReview::class)->handle($caseConfiguration),
        fn () => app(ActivateCaseConfiguration::class)->handle($caseConfiguration),
    ];
}

/**
 * Build a Sellable SKU to `reviewed` under a factory-`active` Product Reference AND Case Configuration (BOTH
 * cascade gates open — proven sufficient by {@see SellableSkuLifecycleTest}), created by $creator + submitted by
 * $reviewer.
 *
 * @return array{0: Model, 1: Closure, 2: Closure, 3: Closure}
 */
function reviewFreshnessSellableSkuScenario(Operator $creator, Operator $reviewer): array
{
    $reference = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $caseConfiguration = CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    actingAs($creator, 'operator');
    $sku = app(CreateSellableSku::class)->handle(productReferenceId: $reference->id, caseConfigurationId: $caseConfiguration->id, commercialName: 'Château Margaux 2015 — Magnum (OWC 6)');
    actingAs($reviewer, 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);
    $sku->refresh();

    return [
        $sku,
        fn () => app(RejectSellableSkuReview::class)->handle($sku, 'Commercial name needs review.'),
        fn () => app(ResubmitSellableSkuForReview::class)->handle($sku),
        fn () => app(ActivateSellableSku::class)->handle($sku),
    ];
}

/**
 * Build a Composite SKU to `reviewed` over TWO factory-`active` constituent Product References (every constituent
 * satisfies the N-constituent cascade gate — proven sufficient by {@see CompositeSkuLifecycleTest}), created by
 * $creator + submitted by $reviewer.
 *
 * @return array{0: Model, 1: Closure, 2: Closure, 3: Closure}
 */
function reviewFreshnessCompositeSkuScenario(Operator $creator, Operator $reviewer): array
{
    $referenceA = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $referenceB = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    actingAs($creator, 'operator');
    $composite = app(CreateCompositeSku::class)->handle([$referenceA->id, $referenceB->id]);
    actingAs($reviewer, 'operator');
    app(SubmitCompositeSkuForReview::class)->handle($composite);
    $composite->refresh();

    return [
        $composite,
        fn () => app(RejectCompositeSkuReview::class)->handle($composite, 'Bundle composition needs sign-off.'),
        fn () => app(ResubmitCompositeSkuForReview::class)->handle($composite),
        fn () => app(ActivateCompositeSku::class)->handle($composite),
    ];
}

it('uniformly enforces reject → block → re-submit → activate across every catalog spine entity', function (string $entity) {
    // Three DISTINCT operators — the Creator → Reviewer → Approver lineage the shared governance guard reads, so
    // "a distinct approver activates" is a genuine separation-of-duties assertion (approver ∉ {creator, reviewer}).
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // Build THIS entity to `reviewed` with its MINIMAL valid activation prerequisite satisfied, through the real
    // create + submit Actions. The three closures reject / re-submit / activate it through ITS canonical Actions
    // (bound concretely inside each scenario — no cross-entity union reaches this body).
    [$model, $reject, $resubmit, $activate] = match ($entity) {
        'ProductMaster' => reviewFreshnessMasterScenario($creator, $reviewer),
        'ProductVariant' => reviewFreshnessProductVariantScenario($creator, $reviewer),
        'Format' => reviewFreshnessFormatScenario($creator, $reviewer),
        'ProductReference' => reviewFreshnessProductReferenceScenario($creator, $reviewer),
        'CaseConfiguration' => reviewFreshnessCaseConfigurationScenario($creator, $reviewer),
        'SellableSku' => reviewFreshnessSellableSkuScenario($creator, $reviewer),
        'CompositeSku' => reviewFreshnessCompositeSkuScenario($creator, $reviewer),
        default => throw new InvalidArgumentException("Unhandled spine entity: {$entity}"),
    };

    // Built clean to `reviewed`, never rejected yet.
    expect($model->getAttribute('lifecycle_state'))->toBe(LifecycleState::Reviewed);

    // REJECT (the reviewer) — stays `reviewed`; "rejection-pending" is now DERIVED from the latest audit action
    // (§ 4.3 — a rejection never reverts to draft).
    actingAs($reviewer, 'operator');
    $reject();
    expect($model->fresh()?->getAttribute('lifecycle_state'))->toBe(LifecycleState::Reviewed);

    // BLOCK — the distinct approver's activation is refused by the review-freshness gate, even though every
    // activation prerequisite (parent cascade / Producer gate) is satisfied. The `un-remediated` token is unique
    // to the block (absent from every separation-of-duties reason), so it proves the BLOCK fired — not a gate or
    // SoD failure — and the entity is unchanged.
    actingAs($approver, 'operator');
    expect($activate)->toThrow(ApprovalGovernanceViolation::class, 'un-remediated');
    expect($model->fresh()?->getAttribute('lifecycle_state'))->toBe(LifecycleState::Reviewed);

    // RE-ARM — the entity-specific `Resubmit*` (the Creator re-submitting an edited entity) makes `.resubmitted`
    // the freshest governance action, clearing the block; it is state-preserving (`reviewed → reviewed`).
    actingAs($creator, 'operator');
    $resubmit();
    expect($model->fresh()?->getAttribute('lifecycle_state'))->toBe(LifecycleState::Reviewed);

    // ACTIVATE — the SAME distinct approver now activates. Blocked → active with ONLY the re-submit in between,
    // the fixture / approver / prerequisites unchanged, is the airtight per-entity re-arm proof.
    actingAs($approver, 'operator');
    $activate();
    expect($model->fresh()?->getAttribute('lifecycle_state'))->toBe(LifecycleState::Active);
})->with([
    'ProductMaster',
    'ProductVariant',
    'Format',
    'ProductReference',
    'CaseConfiguration',
    'SellableSku',
    'CompositeSku',
]);
