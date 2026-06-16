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
use App\Modules\Catalog\Actions\RejectSellableSkuReview;
use App\Modules\Catalog\Actions\ReopenSellableSku;
use App\Modules\Catalog\Actions\RetireSellableSku;
use App\Modules\Catalog\Actions\SubmitCaseConfigurationForReview;
use App\Modules\Catalog\Actions\SubmitFormatForReview;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Actions\SubmitProductReferenceForReview;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Actions\SubmitSellableSkuForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ActivationCascadeViolation;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\Module;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

/**
 * Pins the Sellable SKU (Intrinsic) lifecycle (catalog-lifecycle-approval task 4.5; design D1/D5/D7/D9;
 * product-catalog — Requirements: Product Lifecycle State Machine, Approval Governance, Activation Cascade,
 * Product Lifecycle Events). A Sellable SKU is a CHILD entity with TWO within-module parents — its Product
 * Reference (`product_reference_id`) and its Case Configuration (`case_configuration_id`) — so its activation
 * carries an ACTIVATION-CASCADE GATE that BOTH parents must satisfy (§3.7 / BR-Lifecycle-3): each must be
 * `active`, else {@see ActivationCascadeViolation} naming the first non-`active` parent (the within-catalog
 * gate, design D7, reused verbatim from the Variant/PR — tasks 4.3/4.4). The §14.1 event NAME keeps `SKU`
 * UPPER-case (`SellableSKUActivated`) while the entity_type is the canonical model class `SellableSku`.
 *
 * The shared mechanism's internals (the locked from-state re-read, the audit envelope, the governance lineage
 * read) are exhaustively pinned by ProductMasterLifecycleTest; these tests prove the SKU WIRING and the
 * two-parent gate: the five Actions drive the mechanism for the SKU; activation is rejected when EITHER parent
 * is non-`active` (the two independent negative paths, each naming its blocking parent) and succeeds when both
 * are `active` (recording `SellableSKUActivated`); the gate is ordered AFTER governance (a self-approval throws
 * the governance error even when both gates would open) and the from-state assert is FIRST (an out-of-state
 * activate throws the FSM error, not the gate).
 *
 * DatabaseMigrations (per the section-4 standing rule + design D11): the mechanism opens its OWN
 * DB::transaction, so the recorder's `transactionLevel() === 0` guard sees a REAL commit (the faithful
 * production shape — and the inline ProducerLifecycleProjector fans out on the post-commit hook in the
 * full-chain helper). Each step authenticates a distinct operator with actingAs(), so the resolved actor on
 * each audit row / event is (newco_ops, that operator's id).
 */
uses(DatabaseMigrations::class);

/**
 * Build a genuinely ACTIVE Product Reference via the FULL parent chain — project its Producer `active`
 * (recording a Module-K `ProducerActivated` inside a real transaction so the inline ProducerLifecycleProjector
 * upserts `catalog_producer_states`, the Master gate's read model), then create + submit + approve its Master,
 * its Variant, its Format AND the Product Reference itself through the real Actions, each with three distinct
 * operators — and return the active PR, so a Sellable SKU can be activated over an active immediate parent.
 * Distinctly named so the one shared Pest namespace carries no redeclare (the full producer→…→SKU chain with
 * event ordering is task 5.1's job; this only stands up an active immediate parent).
 */
function sellableSkuLifecycleActiveReference(): ProductReference
{
    DB::transaction(fn () => app(DomainEventRecorder::class)->record(
        name: 'ProducerActivated',
        module: Module::Parties->value,
        actorRole: ActorRole::System,
        actorId: null,
        entityType: 'Producer',
        entityId: '7',
        payload: ['producer_id' => 7, 'status' => 'active'],
    ));

    actingAs(Operator::factory()->create(), 'operator');
    $master = app(CreateProductMaster::class)->handle(name: 'Château Margaux', producerId: 7, appellation: 'Margaux', region: 'Bordeaux');
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateProductMaster::class)->handle($master);

    actingAs(Operator::factory()->create(), 'operator');
    $variant = app(CreateProductVariant::class)->handle(productMasterId: $master->id, variantIdentifier: '2015');
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateProductVariant::class)->handle($variant);

    actingAs(Operator::factory()->create(), 'operator');
    $format = app(CreateFormat::class)->handle(name: 'Magnum', sizeLabel: '1.5L', volumeMl: 1500);
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitFormatForReview::class)->handle($format);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateFormat::class)->handle($format);

    actingAs(Operator::factory()->create(), 'operator');
    $reference = app(CreateProductReference::class)->handle(productVariantId: $variant->id, formatId: $format->id);
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateProductReference::class)->handle($reference);

    return $reference->refresh();
}

/**
 * Build a genuinely ACTIVE Case Configuration through the real create + submit + approve Actions with three
 * distinct operators — a Case Configuration is STANDALONE (no parent gate), so governance alone gates its
 * activation — and return it, so a Sellable SKU can be activated over an active second parent. Distinctly named
 * to avoid colliding with CaseConfigurationLifecycleTest's global `lifecycleCreateDraftCaseConfiguration`
 * (one shared Pest namespace).
 */
function sellableSkuLifecycleActiveCaseConfiguration(): CaseConfiguration
{
    actingAs(Operator::factory()->create(), 'operator');
    $caseConfiguration = app(CreateCaseConfiguration::class)->handle(name: 'Original Wooden Case (6)', unitsPerCase: 6, packagingType: 'owc');
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitCaseConfigurationForReview::class)->handle($caseConfiguration);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateCaseConfiguration::class)->handle($caseConfiguration);

    return $caseConfiguration->refresh();
}

/**
 * Create a draft Sellable SKU over $referenceId + $caseConfigurationId as $creator through the real
 * CreateSellableSku Action — recording `SellableSKUCreated` with $creator's actor_id, the creator lineage the
 * governance guard reads. Leaves $creator as the acting principal (the caller switches before the next
 * governance step). Distinctly named to avoid colliding with the sibling lifecycle tests' global create-helpers
 * (one shared Pest namespace).
 */
function sellableSkuLifecycleCreateDraft(Operator $creator, int $referenceId, int $caseConfigurationId): SellableSku
{
    actingAs($creator, 'operator');

    return app(CreateSellableSku::class)->handle(
        productReferenceId: $referenceId,
        caseConfigurationId: $caseConfigurationId,
        commercialName: 'Château Margaux 2015 — Magnum (OWC 6)',
    );
}

it('submits a draft Sellable SKU to reviewed, recording one audit row and no domain event', function () {
    $operator = Operator::factory()->create();

    // Factory parents (the submit checkpoint reads no parent gate) + a draft SKU via the real CreateSellableSku
    // Action — which records SellableSKUCreated (a *Created, not an *Activated).
    $reference = ProductReference::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();
    $sku = sellableSkuLifecycleCreateDraft($operator, $reference->id, $caseConfiguration->id);

    $reviewed = app(SubmitSellableSkuForReview::class)->handle($sku);

    // State moved draft → reviewed — assert the returned model AND the persisted row.
    expect($reviewed->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // Exactly one audit row for the submit, carrying the lifecycle edge + the operator principal.
    $audit = AuditRecord::query()->where('action', 'catalog.sellable_sku.submitted')->sole();

    expect($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('SellableSku')             // matches the domain-event entity_type
        ->and($audit->entity_id)->toBe((string) $sku->id)           // envelope entity_id is a string
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)        // resolved from ActorContext (operator guard)
        ->and($audit->actor_id)->toEqual($operator->id)             // uncast bigint; loose compare spans engines
        ->and($audit->before)->toBe(['lifecycle_state' => 'draft'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($audit->authorization_basis)->toBe('catalog-lifecycle');

    // The submit checkpoint is event-silent: no *Activated, no *Reviewed (the next event is the activation).
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('activates a reviewed SKU to active under active PR and Case Configuration, recording one SellableSKUActivated', function () {
    // Both parents are activated through the real Actions (the PR via the full producer→Master→Variant→Format→PR
    // chain, the Case Configuration standalone), so the cascade gate reads two genuinely-active siblings — the
    // headline positive path ("both active", AC-0-BR-Lifecycle-3).
    $reference = sellableSkuLifecycleActiveReference();
    $caseConfiguration = sellableSkuLifecycleActiveCaseConfiguration();

    // Three DISTINCT operators for the SKU's own governance lineage (the parent chains' operators are
    // irrelevant — the guard reads the SKU's *Created actor and submit actor, scoped by entity_type + entity_id).
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $sku = sellableSkuLifecycleCreateDraft($creator, $reference->id, $caseConfiguration->id);
    actingAs($reviewer, 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);

    actingAs($approver, 'operator');
    $active = app(ActivateSellableSku::class)->handle($sku);

    // State moved reviewed → active (returned model + persisted row) + one activation audit row.
    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(AuditRecord::query()->where('action', 'catalog.sellable_sku.activated')->count())->toBe(1);

    // Exactly one SellableSKUActivated, recorded in the writing transaction — module catalog, the entity
    // envelope, the approver principal, and a PII-free payload (BOTH parents BY ID, post-transition active).
    $event = DomainEvent::query()->where('name', 'SellableSKUActivated')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('SellableSku')
        ->and($event->entity_id)->toBe((string) $sku->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id)             // uncast bigint — loose compare spans engines
        ->and($event->payload['sellable_sku_id'] ?? null)->toEqual($sku->id)
        ->and($event->payload['product_reference_id'] ?? null)->toEqual($reference->id)
        ->and($event->payload['case_configuration_id'] ?? null)->toEqual($caseConfiguration->id)
        ->and($event->payload['lifecycle_state'] ?? null)->toBe('active')
        ->and($event->payload)->not->toHaveKey('commercial_name')   // descriptive prose stays on *Created
        ->and($event->payload)->not->toHaveKey('version');          // PII-free / minimal (no persistence-only field)
});

it('blocks activation when the parent Product Reference is not active, naming the PR (the activation cascade gate)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // PR NOT active (reviewed), Case Configuration active — so ONLY the PR gate can block, and the rejection
    // names the Product Reference: proving the first parent is independently gated (AC-0-BR-Lifecycle-3).
    $reference = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);
    $caseConfiguration = CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    $sku = sellableSkuLifecycleCreateDraft($creator, $reference->id, $caseConfiguration->id);
    actingAs($reviewer, 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);

    actingAs($approver, 'operator');
    expect(fn () => app(ActivateSellableSku::class)->handle($sku))
        ->toThrow(ActivationCascadeViolation::class, 'ProductReference');

    // The child stays reviewed and records neither the activation audit nor the *Activated event.
    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->where('action', 'catalog.sellable_sku.activated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(0);
});

it('blocks activation when the parent Case Configuration is not active, naming it (the activation cascade gate)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // PR active, Case Configuration NOT active (reviewed) — the PR gate passes, so ONLY the Case Configuration
    // gate blocks and the rejection names it: proving the SECOND parent is independently gated (each counts).
    $reference = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $caseConfiguration = CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    $sku = sellableSkuLifecycleCreateDraft($creator, $reference->id, $caseConfiguration->id);
    actingAs($reviewer, 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);

    actingAs($approver, 'operator');
    expect(fn () => app(ActivateSellableSku::class)->handle($sku))
        ->toThrow(ActivationCascadeViolation::class, 'CaseConfiguration');

    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->where('action', 'catalog.sellable_sku.activated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(0);
});

it('rejects self-approval by the creator even when both parent gates would pass (governance precedes the gate)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    // BOTH parents active, so the cascade gate WOULD open — isolating the approval governance as the sole
    // reason for rejection and proving governance is ordered BEFORE the gate (the error names 'creator').
    $reference = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $caseConfiguration = CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    $sku = sellableSkuLifecycleCreateDraft($creator, $reference->id, $caseConfiguration->id);
    actingAs($reviewer, 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);

    actingAs($creator, 'operator');
    expect(fn () => app(ActivateSellableSku::class)->handle($sku))
        ->toThrow(ApprovalGovernanceViolation::class, 'creator');

    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(0);
});

it('rejects activation on a non-reviewed SKU via the from-state guard, before the gate', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft SKU over non-active (factory draft) parents: the from-state guard fires FIRST (activate is valid
    // only from reviewed), so the FSM error is raised — not the cascade gate — proving the ordering.
    $sku = SellableSku::factory()->create(['lifecycle_state' => LifecycleState::Draft]);

    expect(fn () => app(ActivateSellableSku::class)->handle($sku))
        ->toThrow(IllegalLifecycleTransition::class, 'draft');

    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(0);
});

it('rejects a submit on a non-draft SKU, naming the offending state, and writes nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $sku = SellableSku::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    // Out-of-state: submit is valid only from draft. The message names the locked from-state (reviewed).
    expect(fn () => app(SubmitSellableSkuForReview::class)->handle($sku))
        ->toThrow(IllegalLifecycleTransition::class, 'reviewed');

    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('retires an active SKU to retired, recording one SellableSKURetired', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // Build an active SKU over factory-active parents (the gate reads their lifecycle_state).
    $reference = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $caseConfiguration = CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $sku = sellableSkuLifecycleCreateDraft($creator, $reference->id, $caseConfiguration->id);
    actingAs($reviewer, 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);
    actingAs($approver, 'operator');
    app(ActivateSellableSku::class)->handle($sku);

    // Retire (active → retired): commercial-impact (operator floor), no activation gate.
    $retired = app(RetireSellableSku::class)->handle($sku);

    expect($retired->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(AuditRecord::query()->where('action', 'catalog.sellable_sku.retired')->count())->toBe(1);

    $event = DomainEvent::query()->where('name', 'SellableSKURetired')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('SellableSku')
        ->and($event->entity_id)->toBe((string) $sku->id)
        ->and($event->actor_id)->toEqual($approver->id)
        ->and($event->payload['sellable_sku_id'] ?? null)->toEqual($sku->id)
        ->and($event->payload['product_reference_id'] ?? null)->toEqual($reference->id)
        ->and($event->payload['case_configuration_id'] ?? null)->toEqual($caseConfiguration->id)
        ->and($event->payload['lifecycle_state'] ?? null)->toBe('retired')
        ->and($event->payload)->not->toHaveKey('commercial_name')
        ->and($event->payload)->not->toHaveKey('version');
});

it('reopens a retired SKU to reviewed, recording one audit row and no domain event', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A retired SKU via the factory (it bypasses the FSM — a pure fixture).
    $sku = SellableSku::factory()->create(['lifecycle_state' => LifecycleState::Retired]);

    $reviewed = app(ReopenSellableSku::class)->handle($sku);

    expect($reviewed->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    $audit = AuditRecord::query()->where('action', 'catalog.sellable_sku.reopened')->sole();

    expect($audit->entity_type)->toBe('SellableSku')
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->before)->toBe(['lifecycle_state' => 'retired'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed']);

    // Reopen is event-silent — no *Activated / *Retired / *Reviewed recorded for the step.
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('records a review rejection with notes, keeps the SKU in reviewed, and preserves prior audit rows', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    $reference = ProductReference::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();
    $sku = sellableSkuLifecycleCreateDraft($creator, $reference->id, $caseConfiguration->id);
    actingAs($reviewer, 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku); // the prior (submit) audit row

    $rejected = app(RejectSellableSkuReview::class)->handle($sku, 'The packaging configuration needs sign-off before activation.');

    // Stays in reviewed — there is no revert to draft (§ 4.3).
    expect($rejected->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // One rejection audit row carrying the decision + notes + the acting reviewer principal.
    $rejection = AuditRecord::query()->where('action', 'catalog.sellable_sku.rejected')->sole();
    $after = $rejection->after ?? []; // narrow the nullable jsonb to an array; keys asserted order-independently (PG jsonb reorders)

    expect($rejection->entity_type)->toBe('SellableSku')
        ->and($rejection->entity_id)->toBe((string) $sku->id)
        ->and($rejection->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($rejection->actor_id)->toEqual($reviewer->id)
        ->and($rejection->before)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($after['lifecycle_state'] ?? null)->toBe('reviewed')
        ->and($after['decision'] ?? null)->toBe('rejected')
        ->and($after['notes'] ?? null)->toBe('The packaging configuration needs sign-off before activation.')
        ->and($rejection->authorization_basis)->toBe('catalog-lifecycle');

    // The earlier submit audit row is intact (append-only) and no domain event was recorded for the rejection.
    expect(AuditRecord::query()->where('action', 'catalog.sellable_sku.submitted')->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(0);
});
