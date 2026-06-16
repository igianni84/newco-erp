<?php

use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\ActivateProductVariant;
use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Actions\RejectProductVariantReview;
use App\Modules\Catalog\Actions\ReopenProductVariant;
use App\Modules\Catalog\Actions\RetireProductVariant;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ActivationCascadeViolation;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductVariant;
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
 * Pins the Product Variant lifecycle (catalog-lifecycle-approval task 4.3; design D1/D5/D7/D9; product-catalog
 * — Requirements: Product Lifecycle State Machine, Approval Governance, Activation Cascade, Product Lifecycle
 * Events). The Variant is the FIRST CHILD entity to gain its transitions, so this is where the 4.x recipe
 * DIVERGES from the two standalone entities (Format, Case Configuration): its activation carries a within-
 * module ACTIVATION-CASCADE GATE — the parent Product Master (`product_master_id`) must be `active`, else
 * {@see ActivationCascadeViolation} (the within-catalog sibling of the cross-module Producer gate, design D7).
 *
 * The shared mechanism's internals (the locked from-state re-read, the audit envelope, the governance lineage
 * read) are exhaustively pinned by ProductMasterLifecycleTest; these tests prove the VARIANT WIRING and the
 * cascade gate: the five Actions drive the mechanism for Variant; activation is rejected under a non-`active`
 * parent (AC-0-FSM-10) and succeeds under an `active` one (recording `ProductVariantActivated`); the gate is
 * ordered AFTER governance (a self-approval throws the governance error even when the gate would open) and the
 * from-state assert is FIRST (an out-of-state activate throws the FSM error, not the gate).
 *
 * DatabaseMigrations (per the section-4 standing rule + design D11): the mechanism opens its OWN
 * DB::transaction, so the recorder's `transactionLevel() === 0` guard sees a REAL commit (the faithful
 * production shape — and the inline ProducerLifecycleProjector fans out on the post-commit hook in the
 * full-chain helper). Each step authenticates a distinct operator with actingAs(), so the resolved actor on
 * each audit row / event is (newco_ops, that operator's id).
 */
uses(DatabaseMigrations::class);

/**
 * Build a genuinely ACTIVE parent Product Master via the FULL chain — project its Producer `active` (recording
 * a Module-K `ProducerActivated` inside a real transaction so the inline ProducerLifecycleProjector upserts
 * `catalog_producer_states`, the gate's read model), then create + submit + approve the Master through the
 * real Actions with three distinct operators — and return it, so a child Variant can be activated under an
 * active parent. The producer projection is inlined (NOT ProductMasterLifecycleTest's global
 * `lifecycleProjectProducer`) and this helper is distinctly named so the one shared Pest namespace carries no
 * redeclare.
 */
function lifecycleActiveParentMaster(int $producerId = 7, string $name = 'Château Margaux', string $appellation = 'Margaux'): ProductMaster
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

    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    actingAs($creator, 'operator');
    $master = app(CreateProductMaster::class)->handle(name: $name, producerId: $producerId, appellation: $appellation, region: 'Bordeaux');

    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    actingAs($approver, 'operator');
    app(ActivateProductMaster::class)->handle($master);

    return $master->refresh();
}

/**
 * Create a draft Variant under $parentMasterId as $creator through the real CreateProductVariant Action —
 * recording `ProductVariantCreated` with $creator's actor_id, the creator lineage the governance guard reads.
 * Leaves $creator as the acting principal (the caller switches before the next governance step). Distinctly
 * named to avoid colliding with the sibling lifecycle tests' global create-helpers (one shared Pest namespace).
 */
function lifecycleCreateDraftVariant(Operator $creator, int $parentMasterId, string $variantIdentifier = '2015'): ProductVariant
{
    actingAs($creator, 'operator');

    return app(CreateProductVariant::class)->handle(productMasterId: $parentMasterId, variantIdentifier: $variantIdentifier);
}

it('submits a draft Variant to reviewed, recording one audit row and no domain event', function () {
    $operator = Operator::factory()->create();

    // A factory parent Master (the submit checkpoint reads no parent gate) + a draft Variant via the real
    // CreateProductVariant Action — which records ProductVariantCreated (a *Created, not an *Activated/*Reviewed).
    $master = ProductMaster::factory()->create();
    $variant = lifecycleCreateDraftVariant($operator, $master->id);

    $reviewed = app(SubmitProductVariantForReview::class)->handle($variant);

    // State moved draft → reviewed — assert the returned model AND the persisted row.
    expect($reviewed->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // Exactly one audit row for the submit, carrying the lifecycle edge + the operator principal.
    $audit = AuditRecord::query()->where('action', 'catalog.product_variant.submitted')->sole();

    expect($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('ProductVariant')          // matches the domain-event entity_type
        ->and($audit->entity_id)->toBe((string) $variant->id)       // envelope entity_id is a string
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)        // resolved from ActorContext (operator guard)
        ->and($audit->actor_id)->toEqual($operator->id)             // uncast bigint; loose compare spans engines
        ->and($audit->before)->toBe(['lifecycle_state' => 'draft'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($audit->authorization_basis)->toBe('catalog-lifecycle');

    // The submit checkpoint is event-silent: no *Activated, no *Reviewed (the next event is the activation).
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('activates a reviewed Variant to active under an active parent Master, recording one ProductVariantActivated', function () {
    // The parent Master is activated through the FULL chain (producer projected active → create → submit →
    // approve), so the cascade gate reads a genuinely-active sibling — the headline positive path (AC-0-FSM-10).
    $master = lifecycleActiveParentMaster();

    // Three DISTINCT operators for the Variant's own governance lineage (the Master chain's operators are
    // irrelevant — the guard reads the Variant's *Created actor and submit actor, scoped by entity_type).
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $variant = lifecycleCreateDraftVariant($creator, $master->id);
    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);

    actingAs($approver, 'operator');
    $active = app(ActivateProductVariant::class)->handle($variant);

    // State moved reviewed → active (returned model + persisted row) + one activation audit row.
    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(AuditRecord::query()->where('action', 'catalog.product_variant.activated')->count())->toBe(1);

    // Exactly one ProductVariantActivated, recorded in the writing transaction — module catalog, the entity
    // envelope, the approver principal, and a PII-free payload (the parent Master BY ID, post-transition active).
    $event = DomainEvent::query()->where('name', 'ProductVariantActivated')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('ProductVariant')
        ->and($event->entity_id)->toBe((string) $variant->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id)             // uncast bigint — loose compare spans engines
        ->and($event->payload['product_variant_id'] ?? null)->toEqual($variant->id)
        ->and($event->payload['product_master_id'] ?? null)->toEqual($master->id)
        ->and($event->payload['lifecycle_state'] ?? null)->toBe('active')
        ->and($event->payload)->not->toHaveKey('variant_identifier'); // PII-free (no descriptive variant axis)
});

it('blocks activation when the parent Master is not active (the activation cascade gate)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // A non-`active` parent Master (reviewed — a representative non-active state; the gate rejects ANY non-
    // active parent). The Variant is fully created + submitted, so ONLY the cascade gate can block it (AC-0-FSM-10).
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    $variant = lifecycleCreateDraftVariant($creator, $master->id);
    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);

    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductVariant::class)->handle($variant))
        ->toThrow(ActivationCascadeViolation::class);

    // The child stays reviewed and records neither the activation audit nor the *Activated event.
    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->where('action', 'catalog.product_variant.activated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'ProductVariantActivated')->count())->toBe(0);
});

it('rejects self-approval by the creator even when the parent gate would pass (governance precedes the gate)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    // The parent Master is active, so the cascade gate WOULD open — isolating the approval governance as the
    // sole reason for rejection and proving governance is ordered BEFORE the gate (the error names 'creator').
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    $variant = lifecycleCreateDraftVariant($creator, $master->id);
    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);

    actingAs($creator, 'operator');
    expect(fn () => app(ActivateProductVariant::class)->handle($variant))
        ->toThrow(ApprovalGovernanceViolation::class, 'creator');

    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductVariantActivated')->count())->toBe(0);
});

it('rejects activation on a non-reviewed Variant via the from-state guard, before the gate', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft Variant under a non-active (factory draft) parent: the from-state guard fires FIRST (activate is
    // valid only from reviewed), so the FSM error is raised — not the cascade gate — proving the ordering.
    $variant = ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Draft]);

    expect(fn () => app(ActivateProductVariant::class)->handle($variant))
        ->toThrow(IllegalLifecycleTransition::class, 'draft');

    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'ProductVariantActivated')->count())->toBe(0);
});

it('rejects a submit on a non-draft Variant, naming the offending state, and writes nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    // Out-of-state: submit is valid only from draft. The message names the locked from-state (reviewed).
    expect(fn () => app(SubmitProductVariantForReview::class)->handle($variant))
        ->toThrow(IllegalLifecycleTransition::class, 'reviewed');

    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('retires an active Variant to retired, recording one ProductVariantRetired', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // Build an active Variant under an active parent (factory-active Master — the gate reads its lifecycle_state).
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $variant = lifecycleCreateDraftVariant($creator, $master->id);
    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);
    actingAs($approver, 'operator');
    app(ActivateProductVariant::class)->handle($variant);

    // Retire (active → retired): commercial-impact (operator floor), no activation gate.
    $retired = app(RetireProductVariant::class)->handle($variant);

    expect($retired->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(AuditRecord::query()->where('action', 'catalog.product_variant.retired')->count())->toBe(1);

    $event = DomainEvent::query()->where('name', 'ProductVariantRetired')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('ProductVariant')
        ->and($event->entity_id)->toBe((string) $variant->id)
        ->and($event->actor_id)->toEqual($approver->id)
        ->and($event->payload['product_variant_id'] ?? null)->toEqual($variant->id)
        ->and($event->payload['product_master_id'] ?? null)->toEqual($master->id)
        ->and($event->payload['lifecycle_state'] ?? null)->toBe('retired')
        ->and($event->payload)->not->toHaveKey('variant_identifier');
});

it('reopens a retired Variant to reviewed, recording one audit row and no domain event', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A retired Variant via the factory (it bypasses the FSM — a pure fixture).
    $variant = ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Retired]);

    $reviewed = app(ReopenProductVariant::class)->handle($variant);

    expect($reviewed->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    $audit = AuditRecord::query()->where('action', 'catalog.product_variant.reopened')->sole();

    expect($audit->entity_type)->toBe('ProductVariant')
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->before)->toBe(['lifecycle_state' => 'retired'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed']);

    // Reopen is event-silent — no *Activated / *Retired / *Reviewed recorded for the step.
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('records a review rejection with notes, keeps the Variant in reviewed, and preserves prior audit rows', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    $master = ProductMaster::factory()->create();
    $variant = lifecycleCreateDraftVariant($creator, $master->id);
    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant); // the prior (submit) audit row

    $rejected = app(RejectProductVariantReview::class)->handle($variant, 'Vintage year is missing from the attribute set.');

    // Stays in reviewed — there is no revert to draft (§ 4.3).
    expect($rejected->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // One rejection audit row carrying the decision + notes + the acting reviewer principal.
    $rejection = AuditRecord::query()->where('action', 'catalog.product_variant.rejected')->sole();
    $after = $rejection->after ?? []; // narrow the nullable jsonb to an array; keys asserted order-independently (PG jsonb reorders)

    expect($rejection->entity_type)->toBe('ProductVariant')
        ->and($rejection->entity_id)->toBe((string) $variant->id)
        ->and($rejection->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($rejection->actor_id)->toEqual($reviewer->id)
        ->and($rejection->before)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($after['lifecycle_state'] ?? null)->toBe('reviewed')
        ->and($after['decision'] ?? null)->toBe('rejected')
        ->and($after['notes'] ?? null)->toBe('Vintage year is missing from the attribute set.')
        ->and($rejection->authorization_basis)->toBe('catalog-lifecycle');

    // The earlier submit audit row is intact (append-only) and no domain event was recorded for the rejection.
    expect(AuditRecord::query()->where('action', 'catalog.product_variant.submitted')->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'ProductVariantActivated')->count())->toBe(0);
});
