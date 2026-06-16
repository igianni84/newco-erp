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
use App\Modules\Catalog\Actions\SubmitCaseConfigurationForReview;
use App\Modules\Catalog\Actions\SubmitFormatForReview;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Actions\SubmitProductReferenceForReview;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Actions\SubmitSellableSkuForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ActivationCascadeViolation;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\Module;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

/**
 * Pins the activation cascade as an INTEGRATION across the real Module-0 hierarchy (catalog-lifecycle-approval
 * task 5.1; design D7; product-catalog — Requirements: Activation Cascade, Product Lifecycle Events; Module 0
 * PRD § 4.4 / § 14.3 / AC-0-FSM-10). The per-entity activation gates and events are pinned in isolation by the
 * sibling `*LifecycleTest` files; THIS test proves the two emergent properties of composing them across the
 * whole spine Master → Variant → Product Reference → Sellable SKU (with Format and Case Configuration as the
 * other within-module parents):
 *
 *   1. Activating the full chain parent-before-child SUCCEEDS, and the four hierarchy `*Activated` events are
 *      recorded in ascending `domain_events.id` = hierarchy order. Each `Activate*` Action commits in its OWN
 *      transaction, so the auto-increment `id` sequence encodes the activation order — and parent-before-child
 *      is the ONLY order the cascade permits (a child can never reach `active` before its parent), so no
 *      ordering glue is needed: the ordering falls out of the gate composition (design D7).
 *   2. Activating any child BEFORE its parent is `active` is REJECTED — at every level of the cascade
 *      (Variant→Master, Reference→Variant, SellableSKU→Reference) — with {@see ActivationCascadeViolation}
 *      naming the blocking parent, the child held in `reviewed`, and no `*Activated` recorded.
 *
 * The Master's own cross-module parent gate (the Producer being `active` in the projection) is pinned by
 * ProductMasterLifecycleTest (task 3.2); here the Producer is projected `active` up front so the cascade under
 * test is the WITHIN-catalog parent-before-child chain.
 *
 * DatabaseMigrations (per the section-5 standing rule + design D11): each `Activate*` opens its own
 * DB::transaction, so the recorder's `transactionLevel() === 0` guard sees a real commit (and the inline
 * ProducerLifecycleProjector fans out on the post-commit hook in the spine builder). Each step authenticates a
 * distinct operator with actingAs(), satisfying the Creator → Reviewer → Approver separation-of-duties floor
 * at every entity (role_count 3).
 */
uses(DatabaseMigrations::class);

/**
 * Build the full Module-0 spine — its Producer projected `active`, then Master → Variant → Format → Product
 * Reference → Case Configuration → Sellable SKU each CREATED + SUBMITTED (so each rests in `reviewed`) through
 * the real Actions, every step under a distinct operator — but NONE activated. The caller drives the
 * activations, exercising the cascade gate composition over the real hierarchy.
 *
 * Distinctly named (`cascade*`) so the one shared Pest namespace carries no redeclare against the sibling
 * lifecycle tests' global helpers (Codebase Patterns #20).
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
function cascadeReviewedSpine(int $producerId = 7): array
{
    // Project the Producer `active` (record a Module-K ProducerActivated inside a real transaction so the
    // inline ProducerLifecycleProjector upserts catalog_producer_states — the Master gate's read model).
    DB::transaction(fn () => app(DomainEventRecorder::class)->record(
        name: 'ProducerActivated',
        module: Module::Parties->value,
        actorRole: ActorRole::System,
        actorId: null,
        entityType: 'Producer',
        entityId: (string) $producerId,
        payload: ['producer_id' => $producerId, 'status' => 'active'],
    ));

    actingAs(Operator::factory()->create(), 'operator');
    $master = app(CreateProductMaster::class)->handle(name: 'Château Margaux', producerId: $producerId, appellation: 'Margaux', region: 'Bordeaux');
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    actingAs(Operator::factory()->create(), 'operator');
    $variant = app(CreateProductVariant::class)->handle(productMasterId: $master->id, variantIdentifier: '2015');
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);

    actingAs(Operator::factory()->create(), 'operator');
    $format = app(CreateFormat::class)->handle(name: 'Magnum', sizeLabel: '1.5L', volumeMl: 1500);
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitFormatForReview::class)->handle($format);

    actingAs(Operator::factory()->create(), 'operator');
    $reference = app(CreateProductReference::class)->handle(productVariantId: $variant->id, formatId: $format->id);
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);

    actingAs(Operator::factory()->create(), 'operator');
    $caseConfiguration = app(CreateCaseConfiguration::class)->handle(name: 'Original Wooden Case (6)', unitsPerCase: 6, packagingType: 'owc');
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitCaseConfigurationForReview::class)->handle($caseConfiguration);

    actingAs(Operator::factory()->create(), 'operator');
    $sku = app(CreateSellableSku::class)->handle(
        productReferenceId: $reference->id,
        caseConfigurationId: $caseConfiguration->id,
        commercialName: 'Château Margaux 2015 — Magnum (OWC 6)',
    );
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);

    return [
        'master' => $master->refresh(),
        'variant' => $variant->refresh(),
        'format' => $format->refresh(),
        'reference' => $reference->refresh(),
        'caseConfiguration' => $caseConfiguration->refresh(),
        'sku' => $sku->refresh(),
    ];
}

it('activates the full Master → Variant → Reference → Sellable SKU chain parent-before-child, recording the four *Activated events in ascending id order', function () {
    $spine = cascadeReviewedSpine();

    // Activate parent-before-child through the real Actions, each under a distinct approver (so the governance
    // floor holds at every entity). The cascade gate opens at each level only because the parent above it is
    // already `active`: Master (producer projected) → Variant (Master) → Format (standalone) → Reference
    // (Variant + Format) → Case Configuration (standalone) → Sellable SKU (Reference + Case Configuration).
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateProductMaster::class)->handle($spine['master']);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateProductVariant::class)->handle($spine['variant']);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateFormat::class)->handle($spine['format']);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateProductReference::class)->handle($spine['reference']);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateCaseConfiguration::class)->handle($spine['caseConfiguration']);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateSellableSku::class)->handle($spine['sku']);

    // Every spine entity reached `active` (the persisted rows — the chain completed end to end).
    expect(ProductMaster::findOrFail($spine['master']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductVariant::findOrFail($spine['variant']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(Format::findOrFail($spine['format']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductReference::findOrFail($spine['reference']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(CaseConfiguration::findOrFail($spine['caseConfiguration']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(SellableSku::findOrFail($spine['sku']->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // The four hierarchy `*Activated` events appear in ascending domain_events.id = parent-before-child order
    // (§ 14.3 / AC-0-FSM-10). Each Action committed in its own transaction, so the id sequence encodes the
    // activation order — and a child could not have activated before its parent (the gate would have blocked
    // it), so this is the ONLY order the cascade permits. No ordering glue: it falls out of the composition.
    $activationOrder = DomainEvent::query()
        ->whereIn('name', [
            'ProductMasterActivated',
            'ProductVariantActivated',
            'ProductReferenceActivated',
            'SellableSKUActivated',
        ])
        ->orderBy('id')
        ->pluck('name')
        ->all();

    expect($activationOrder)->toBe([
        'ProductMasterActivated',
        'ProductVariantActivated',
        'ProductReferenceActivated',
        'SellableSKUActivated',
    ]);

    // Each hierarchy event binds to the entity we activated (one apiece, the right id).
    expect(DomainEvent::query()->where('name', 'ProductMasterActivated')->sole()->entity_id)->toBe((string) $spine['master']->id)
        ->and(DomainEvent::query()->where('name', 'ProductVariantActivated')->sole()->entity_id)->toBe((string) $spine['variant']->id)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceActivated')->sole()->entity_id)->toBe((string) $spine['reference']->id)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->sole()->entity_id)->toBe((string) $spine['sku']->id);
});

it('rejects activating a child before its parent is active, at every level of the cascade', function () {
    // The whole spine rests in `reviewed` — nothing is `active` yet, so every child's first-checked parent is
    // non-`active`. Each negative uses a distinct (fresh) approver so the approval governance PASSES and the
    // cascade gate is demonstrably the reason for the rejection (governance is ordered before the gate).
    $spine = cascadeReviewedSpine();

    // LEVEL 1 — a Product Variant cannot activate while its Product Master is still `reviewed`.
    actingAs(Operator::factory()->create(), 'operator');
    expect(fn () => app(ActivateProductVariant::class)->handle($spine['variant']))
        ->toThrow(ActivationCascadeViolation::class, 'ProductMaster');

    // LEVEL 2 — a Product Reference cannot activate while its Product Variant is still `reviewed` (the gate
    // checks the Variant first, so it names the Variant even though the Format is also not yet `active`).
    actingAs(Operator::factory()->create(), 'operator');
    expect(fn () => app(ActivateProductReference::class)->handle($spine['reference']))
        ->toThrow(ActivationCascadeViolation::class, 'ProductVariant');

    // LEVEL 3 — a Sellable SKU cannot activate while its Product Reference is still `reviewed` (the gate checks
    // the Reference first, so it names the Reference even though the Case Configuration is also not `active`).
    actingAs(Operator::factory()->create(), 'operator');
    expect(fn () => app(ActivateSellableSku::class)->handle($spine['sku']))
        ->toThrow(ActivationCascadeViolation::class, 'ProductReference');

    // No child moved off `reviewed`, and NO catalog `*Activated` event was recorded for any of them (each gate
    // threw inside the transition's transaction, which rolled back). The projected ProducerActivated (module
    // `parties`) is excluded — this asserts no catalog activation slipped through.
    expect(ProductVariant::findOrFail($spine['variant']->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(ProductReference::findOrFail($spine['reference']->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(SellableSku::findOrFail($spine['sku']->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('module', 'catalog')->where('name', 'like', '%Activated%')->count())->toBe(0);
});
