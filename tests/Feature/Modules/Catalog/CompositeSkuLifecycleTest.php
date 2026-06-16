<?php

use App\Modules\Catalog\Actions\ActivateCompositeSku;
use App\Modules\Catalog\Actions\ActivateFormat;
use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\ActivateProductReference;
use App\Modules\Catalog\Actions\ActivateProductVariant;
use App\Modules\Catalog\Actions\CreateCompositeSku;
use App\Modules\Catalog\Actions\CreateFormat;
use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\CreateProductReference;
use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Actions\RejectCompositeSkuReview;
use App\Modules\Catalog\Actions\ReopenCompositeSku;
use App\Modules\Catalog\Actions\RetireCompositeSku;
use App\Modules\Catalog\Actions\SubmitCompositeSkuForReview;
use App\Modules\Catalog\Actions\SubmitFormatForReview;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Actions\SubmitProductReferenceForReview;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ActivationCascadeViolation;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductReference;
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
 * Pins the Composite SKU lifecycle (catalog-lifecycle-approval task 4.6; design D1/D5/D7/D9; product-catalog —
 * Requirements: Product Lifecycle State Machine, Approval Governance, Activation Cascade, Product Lifecycle
 * Events). A Composite SKU is an N-constituent CHILD entity — a curated bundle of N ≥ 2 ordered constituent
 * Product References (the `catalog_composite_sku_constituents` junction) — so its activation carries an
 * ACTIVATION-CASCADE GATE that EVERY constituent must satisfy (§4.4 / BR-Lifecycle-3): the gate LOOPS the SAME
 * per-parent primitive the single-/two-parent children use over the whole bundle, rejecting with
 * {@see ActivationCascadeViolation} naming the first non-`active` `ProductReference`. The §14.1 event NAME keeps
 * `SKU` UPPER-case (`CompositeSKUActivated`) while the entity_type is the canonical model class `CompositeSku`.
 *
 * The shared mechanism's internals (the locked from-state re-read, the audit envelope, the governance lineage
 * read) are exhaustively pinned by ProductMasterLifecycleTest; these tests prove the Composite SKU WIRING and
 * the N-constituent gate: the five Actions drive the mechanism for the bundle; activation is rejected when ANY
 * constituent is non-`active` (the negative holds the first constituent active so the rejection proves the loop
 * checks beyond it) and succeeds when EVERY constituent is `active` (recording `CompositeSKUActivated` with the
 * ordered constituent ids); the gate is ordered AFTER governance (a self-approval throws the governance error
 * even when every constituent would open the gate) and the from-state assert is FIRST (an out-of-state activate
 * throws the FSM error, not the gate).
 *
 * DatabaseMigrations (per the section-4 standing rule + design D11): the mechanism opens its OWN
 * DB::transaction, so the recorder's `transactionLevel() === 0` guard sees a REAL commit (the faithful
 * production shape — and the inline ProducerLifecycleProjector fans out on the post-commit hook in the
 * active-constituents helper). Each step authenticates a distinct operator with actingAs(), so the resolved
 * actor on each audit row / event is (newco_ops, that operator's id).
 */
uses(DatabaseMigrations::class);

/**
 * Build TWO genuinely ACTIVE Product References for a Composite SKU's constituent set — project their Producer
 * `active` (recording a Module-K `ProducerActivated` inside a real transaction so the inline
 * ProducerLifecycleProjector upserts `catalog_producer_states`, the Master gate's read model), then activate one
 * Master + one Variant + TWO distinct Formats → TWO distinct active Product References `(V, F1)` and `(V, F2)`,
 * each through the real create + submit + approve Actions with distinct operators — and return the two active
 * PRs, so a Composite SKU can be activated over a genuinely-active constituent set. Distinctly named so the one
 * shared Pest namespace carries no redeclare (the full producer→…→SKU chain with parent-before-child event
 * ordering is task 5.1's job; this only stands up an active constituent set).
 *
 * @return array<int, ProductReference>
 */
function compositeSkuLifecycleActiveReferences(): array
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

    $references = [];
    foreach ([['Magnum', '1.5L', 1500], ['Double Magnum', '3L', 3000]] as [$name, $sizeLabel, $volumeMl]) {
        actingAs(Operator::factory()->create(), 'operator');
        $format = app(CreateFormat::class)->handle(name: $name, sizeLabel: $sizeLabel, volumeMl: $volumeMl);
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

        $references[] = $reference->refresh();
    }

    return $references;
}

/**
 * Create a draft Composite SKU over $productReferenceIds as $creator through the real CreateCompositeSku Action
 * — recording `CompositeSKUCreated` with $creator's actor_id, the creator lineage the governance guard reads.
 * Leaves $creator as the acting principal (the caller switches before the next governance step). Distinctly
 * named to avoid colliding with the sibling lifecycle tests' global create-helpers (one shared Pest namespace).
 *
 * @param  list<int>  $productReferenceIds
 */
function compositeSkuLifecycleCreateDraft(Operator $creator, array $productReferenceIds): CompositeSku
{
    actingAs($creator, 'operator');

    return app(CreateCompositeSku::class)->handle($productReferenceIds);
}

it('submits a draft Composite SKU to reviewed, recording one audit row and no domain event', function () {
    $operator = Operator::factory()->create();

    // Factory constituents (the submit checkpoint reads no parent gate) + a draft Composite via the real
    // CreateCompositeSku Action — which records CompositeSKUCreated (a *Created, not an *Activated).
    $prA = ProductReference::factory()->create();
    $prB = ProductReference::factory()->create();
    $composite = compositeSkuLifecycleCreateDraft($operator, [$prA->id, $prB->id]);

    $reviewed = app(SubmitCompositeSkuForReview::class)->handle($composite);

    // State moved draft → reviewed — assert the returned model AND the persisted row.
    expect($reviewed->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // Exactly one audit row for the submit, carrying the lifecycle edge + the operator principal.
    $audit = AuditRecord::query()->where('action', 'catalog.composite_sku.submitted')->sole();

    expect($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('CompositeSku')            // matches the domain-event entity_type
        ->and($audit->entity_id)->toBe((string) $composite->id)     // envelope entity_id is a string
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)        // resolved from ActorContext (operator guard)
        ->and($audit->actor_id)->toEqual($operator->id)             // uncast bigint; loose compare spans engines
        ->and($audit->before)->toBe(['lifecycle_state' => 'draft'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($audit->authorization_basis)->toBe('catalog-lifecycle');

    // The submit checkpoint is event-silent: no *Activated, no *Reviewed (the next event is the activation).
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('activates a reviewed Composite SKU to active once every constituent is active, recording one CompositeSKUActivated', function () {
    // Both constituents are activated through the real Actions (each via the full producer→Master→Variant→Format→PR
    // chain), so the cascade gate loops over two genuinely-active siblings — the headline positive path
    // ("every constituent active", AC-0-BR-Lifecycle-3 / §4.4).
    $references = compositeSkuLifecycleActiveReferences();

    // Three DISTINCT operators for the Composite's own governance lineage (the constituent chains' operators are
    // irrelevant — the guard reads the Composite's *Created actor and submit actor, scoped by entity_type + entity_id).
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $composite = compositeSkuLifecycleCreateDraft($creator, [$references[0]->id, $references[1]->id]);
    actingAs($reviewer, 'operator');
    app(SubmitCompositeSkuForReview::class)->handle($composite);

    actingAs($approver, 'operator');
    $active = app(ActivateCompositeSku::class)->handle($composite);

    // State moved reviewed → active (returned model + persisted row) + one activation audit row.
    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(AuditRecord::query()->where('action', 'catalog.composite_sku.activated')->count())->toBe(1);

    // Exactly one CompositeSKUActivated, recorded in the writing transaction — module catalog, the entity
    // envelope, the approver principal, and a PII-free payload (the ordered constituent ids, post-transition active).
    $event = DomainEvent::query()->where('name', 'CompositeSKUActivated')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('CompositeSku')
        ->and($event->entity_id)->toBe((string) $composite->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id)             // uncast bigint — loose compare spans engines
        ->and($event->payload['composite_sku_id'] ?? null)->toEqual($composite->id)
        ->and($event->payload['constituent_product_reference_ids'] ?? null)->toEqual([$references[0]->id, $references[1]->id])
        ->and($event->payload['lifecycle_state'] ?? null)->toBe('active')
        ->and($event->payload)->not->toHaveKey('constituent_count')  // the *Created convenience stays off transitions
        ->and($event->payload)->not->toHaveKey('version');          // PII-free / minimal (no persistence-only field)
});

it('blocks activation when any constituent Product Reference is not active, naming the PR (the activation cascade gate)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // First constituent active, second NOT active (reviewed). Ordering it second proves the gate does NOT
    // short-circuit on the first active constituent — it loops and rejects on the inactive one, naming the
    // ProductReference (§4.4 — every constituent must be active).
    $active = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $inactive = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    $composite = compositeSkuLifecycleCreateDraft($creator, [$active->id, $inactive->id]);
    actingAs($reviewer, 'operator');
    app(SubmitCompositeSkuForReview::class)->handle($composite);

    actingAs($approver, 'operator');
    expect(fn () => app(ActivateCompositeSku::class)->handle($composite))
        ->toThrow(ActivationCascadeViolation::class, 'ProductReference');

    // The child stays reviewed and records neither the activation audit nor the *Activated event.
    expect(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->where('action', 'catalog.composite_sku.activated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'CompositeSKUActivated')->count())->toBe(0);
});

it('rejects self-approval by the creator even when every constituent would pass the gate (governance precedes the gate)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    // BOTH constituents active, so the cascade gate WOULD open — isolating the approval governance as the sole
    // reason for rejection and proving governance is ordered BEFORE the gate (the error names 'creator').
    $prA = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $prB = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    $composite = compositeSkuLifecycleCreateDraft($creator, [$prA->id, $prB->id]);
    actingAs($reviewer, 'operator');
    app(SubmitCompositeSkuForReview::class)->handle($composite);

    actingAs($creator, 'operator');
    expect(fn () => app(ActivateCompositeSku::class)->handle($composite))
        ->toThrow(ApprovalGovernanceViolation::class, 'creator');

    expect(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'CompositeSKUActivated')->count())->toBe(0);
});

it('rejects activation on a non-reviewed Composite SKU via the from-state guard, before the gate', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft Composite over the factory's draft constituents: the from-state guard fires FIRST (activate is
    // valid only from reviewed), so the FSM error is raised — not the cascade gate — proving the ordering.
    $composite = CompositeSku::factory()->create(['lifecycle_state' => LifecycleState::Draft]);

    expect(fn () => app(ActivateCompositeSku::class)->handle($composite))
        ->toThrow(IllegalLifecycleTransition::class, 'draft');

    expect(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'CompositeSKUActivated')->count())->toBe(0);
});

it('rejects a submit on a non-draft Composite SKU, naming the offending state, and writes nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $composite = CompositeSku::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    // Out-of-state: submit is valid only from draft. The message names the locked from-state (reviewed).
    expect(fn () => app(SubmitCompositeSkuForReview::class)->handle($composite))
        ->toThrow(IllegalLifecycleTransition::class, 'reviewed');

    expect(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('retires an active Composite SKU to retired, recording one CompositeSKURetired', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // Build an active Composite over factory-active constituents (the gate reads their lifecycle_state).
    $prA = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $prB = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $composite = compositeSkuLifecycleCreateDraft($creator, [$prA->id, $prB->id]);
    actingAs($reviewer, 'operator');
    app(SubmitCompositeSkuForReview::class)->handle($composite);
    actingAs($approver, 'operator');
    app(ActivateCompositeSku::class)->handle($composite);

    // Retire (active → retired): commercial-impact (operator floor), no activation gate.
    $retired = app(RetireCompositeSku::class)->handle($composite);

    expect($retired->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(AuditRecord::query()->where('action', 'catalog.composite_sku.retired')->count())->toBe(1);

    $event = DomainEvent::query()->where('name', 'CompositeSKURetired')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('CompositeSku')
        ->and($event->entity_id)->toBe((string) $composite->id)
        ->and($event->actor_id)->toEqual($approver->id)
        ->and($event->payload['composite_sku_id'] ?? null)->toEqual($composite->id)
        ->and($event->payload['constituent_product_reference_ids'] ?? null)->toEqual([$prA->id, $prB->id])
        ->and($event->payload['lifecycle_state'] ?? null)->toBe('retired')
        ->and($event->payload)->not->toHaveKey('constituent_count')
        ->and($event->payload)->not->toHaveKey('version');
});

it('reopens a retired Composite SKU to reviewed, recording one audit row and no domain event', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A retired Composite via the factory (it bypasses the FSM — a pure fixture).
    $composite = CompositeSku::factory()->create(['lifecycle_state' => LifecycleState::Retired]);

    $reviewed = app(ReopenCompositeSku::class)->handle($composite);

    expect($reviewed->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    $audit = AuditRecord::query()->where('action', 'catalog.composite_sku.reopened')->sole();

    expect($audit->entity_type)->toBe('CompositeSku')
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->before)->toBe(['lifecycle_state' => 'retired'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed']);

    // Reopen is event-silent — no *Activated / *Retired / *Reviewed recorded for the step.
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('records a review rejection with notes, keeps the Composite SKU in reviewed, and preserves prior audit rows', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    $prA = ProductReference::factory()->create();
    $prB = ProductReference::factory()->create();
    $composite = compositeSkuLifecycleCreateDraft($creator, [$prA->id, $prB->id]);
    actingAs($reviewer, 'operator');
    app(SubmitCompositeSkuForReview::class)->handle($composite); // the prior (submit) audit row

    $rejected = app(RejectCompositeSkuReview::class)->handle($composite, 'The bundle composition needs sign-off before activation.');

    // Stays in reviewed — there is no revert to draft (§ 4.3).
    expect($rejected->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // One rejection audit row carrying the decision + notes + the acting reviewer principal.
    $rejection = AuditRecord::query()->where('action', 'catalog.composite_sku.rejected')->sole();
    $after = $rejection->after ?? []; // narrow the nullable jsonb to an array; keys asserted order-independently (PG jsonb reorders)

    expect($rejection->entity_type)->toBe('CompositeSku')
        ->and($rejection->entity_id)->toBe((string) $composite->id)
        ->and($rejection->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($rejection->actor_id)->toEqual($reviewer->id)
        ->and($rejection->before)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($after['lifecycle_state'] ?? null)->toBe('reviewed')
        ->and($after['decision'] ?? null)->toBe('rejected')
        ->and($after['notes'] ?? null)->toBe('The bundle composition needs sign-off before activation.')
        ->and($rejection->authorization_basis)->toBe('catalog-lifecycle');

    // The earlier submit audit row is intact (append-only) and no domain event was recorded for the rejection.
    expect(AuditRecord::query()->where('action', 'catalog.composite_sku.submitted')->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'CompositeSKUActivated')->count())->toBe(0);
});
