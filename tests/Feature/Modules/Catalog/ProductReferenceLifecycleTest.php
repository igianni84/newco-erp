<?php

use App\Modules\Catalog\Actions\ActivateFormat;
use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\ActivateProductReference;
use App\Modules\Catalog\Actions\ActivateProductVariant;
use App\Modules\Catalog\Actions\CreateFormat;
use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\CreateProductReference;
use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Actions\RejectProductReferenceReview;
use App\Modules\Catalog\Actions\ReopenProductReference;
use App\Modules\Catalog\Actions\RetireProductReference;
use App\Modules\Catalog\Actions\SubmitFormatForReview;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Actions\SubmitProductReferenceForReview;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ActivationCascadeViolation;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductReference;
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
 * Pins the Product Reference lifecycle (catalog-lifecycle-approval task 4.4; design D1/D5/D7/D9; product-catalog
 * — Requirements: Product Lifecycle State Machine, Approval Governance, Activation Cascade, Product Lifecycle
 * Events). A Product Reference is a CHILD entity with TWO within-module parents — its Product Variant
 * (`product_variant_id`) and its Format (`format_id`) — so its activation carries an ACTIVATION-CASCADE GATE
 * that BOTH parents must satisfy: each must be `active`, else {@see ActivationCascadeViolation} naming the
 * first non-`active` parent (the within-catalog gate, design D7, reused verbatim from the Variant — task 4.3).
 *
 * The shared mechanism's internals (the locked from-state re-read, the audit envelope, the governance lineage
 * read) are exhaustively pinned by ProductMasterLifecycleTest; these tests prove the PR WIRING and the
 * two-parent gate: the five Actions drive the mechanism for the PR; activation is rejected when EITHER parent
 * is non-`active` (the two independent negative paths, each naming its blocking parent) and succeeds when both
 * are `active` (recording `ProductReferenceActivated`); the gate is ordered AFTER governance (a self-approval
 * throws the governance error even when both gates would open) and the from-state assert is FIRST (an
 * out-of-state activate throws the FSM error, not the gate).
 *
 * DatabaseMigrations (per the section-4 standing rule + design D11): the mechanism opens its OWN
 * DB::transaction, so the recorder's `transactionLevel() === 0` guard sees a REAL commit (the faithful
 * production shape — and the inline ProducerLifecycleProjector fans out on the post-commit hook in the
 * full-chain helper). Each step authenticates a distinct operator with actingAs(), so the resolved actor on
 * each audit row / event is (newco_ops, that operator's id).
 */
uses(DatabaseMigrations::class);

/**
 * Build a genuinely ACTIVE Product Variant via the FULL parent chain — project its Producer `active` (recording
 * a Module-K `ProducerActivated` inside a real transaction so the inline ProducerLifecycleProjector upserts
 * `catalog_producer_states`, the gate's read model), then create + submit + approve its Master AND the Variant
 * itself through the real Actions, each with three distinct operators — and return the active Variant, so a PR
 * can be activated over an active immediate parent. Distinctly named so the one shared Pest namespace carries
 * no redeclare (the full producer→Master→Variant→PR→SKU chain with event ordering is task 5.1's job; this only
 * stands up an active immediate parent).
 */
function referenceLifecycleActiveVariant(int $producerId = 7, string $name = 'Château Margaux', string $appellation = 'Margaux', string $variantIdentifier = '2015'): ProductVariant
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

    actingAs(Operator::factory()->create(), 'operator');
    $master = app(CreateProductMaster::class)->handle(name: $name, producerId: $producerId, appellation: $appellation, region: 'Bordeaux');
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateProductMaster::class)->handle($master);

    actingAs(Operator::factory()->create(), 'operator');
    $variant = app(CreateProductVariant::class)->handle(productMasterId: $master->id, variantIdentifier: $variantIdentifier);
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateProductVariant::class)->handle($variant);

    return $variant->refresh();
}

/**
 * Build a genuinely ACTIVE Format through the real create + submit + approve Actions with three distinct
 * operators — a Format is STANDALONE (no parent gate), so governance alone gates its activation — and return
 * it, so a PR can be activated over an active second parent. Distinctly named to avoid colliding with
 * FormatLifecycleTest's global `lifecycleCreateDraftFormat` (one shared Pest namespace).
 */
function referenceLifecycleActiveFormat(string $name = 'Magnum', string $sizeLabel = '1.5L', int $volumeMl = 1500): Format
{
    actingAs(Operator::factory()->create(), 'operator');
    $format = app(CreateFormat::class)->handle(name: $name, sizeLabel: $sizeLabel, volumeMl: $volumeMl);
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitFormatForReview::class)->handle($format);
    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateFormat::class)->handle($format);

    return $format->refresh();
}

/**
 * Create a draft Product Reference over $variantId + $formatId as $creator through the real
 * CreateProductReference Action — recording `ProductReferenceCreated` with $creator's actor_id, the creator
 * lineage the governance guard reads. Leaves $creator as the acting principal (the caller switches before the
 * next governance step). Distinctly named to avoid colliding with the sibling lifecycle tests' global
 * create-helpers (one shared Pest namespace).
 */
function referenceLifecycleCreateDraft(Operator $creator, int $variantId, int $formatId): ProductReference
{
    actingAs($creator, 'operator');

    return app(CreateProductReference::class)->handle(productVariantId: $variantId, formatId: $formatId);
}

it('submits a draft Product Reference to reviewed, recording one audit row and no domain event', function () {
    $operator = Operator::factory()->create();

    // Factory parents (the submit checkpoint reads no parent gate) + a draft PR via the real
    // CreateProductReference Action — which records ProductReferenceCreated (a *Created, not an *Activated).
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $reference = referenceLifecycleCreateDraft($operator, $variant->id, $format->id);

    $reviewed = app(SubmitProductReferenceForReview::class)->handle($reference);

    // State moved draft → reviewed — assert the returned model AND the persisted row.
    expect($reviewed->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // Exactly one audit row for the submit, carrying the lifecycle edge + the operator principal.
    $audit = AuditRecord::query()->where('action', 'catalog.product_reference.submitted')->sole();

    expect($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('ProductReference')         // matches the domain-event entity_type
        ->and($audit->entity_id)->toBe((string) $reference->id)      // envelope entity_id is a string
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)         // resolved from ActorContext (operator guard)
        ->and($audit->actor_id)->toEqual($operator->id)              // uncast bigint; loose compare spans engines
        ->and($audit->before)->toBe(['lifecycle_state' => 'draft'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($audit->authorization_basis)->toBe('catalog-lifecycle');

    // The submit checkpoint is event-silent: no *Activated, no *Reviewed (the next event is the activation).
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('activates a reviewed PR to active under active Variant and Format, recording one ProductReferenceActivated', function () {
    // Both parents are activated through the real Actions (the Variant via the full producer→Master→Variant
    // chain, the Format standalone), so the cascade gate reads two genuinely-active siblings — the headline
    // positive path ("both active", AC-0-FSM-10).
    $variant = referenceLifecycleActiveVariant();
    $format = referenceLifecycleActiveFormat();

    // Three DISTINCT operators for the PR's own governance lineage (the parent chains' operators are
    // irrelevant — the guard reads the PR's *Created actor and submit actor, scoped by entity_type + entity_id).
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $reference = referenceLifecycleCreateDraft($creator, $variant->id, $format->id);
    actingAs($reviewer, 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);

    actingAs($approver, 'operator');
    $active = app(ActivateProductReference::class)->handle($reference);

    // State moved reviewed → active (returned model + persisted row) + one activation audit row.
    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(AuditRecord::query()->where('action', 'catalog.product_reference.activated')->count())->toBe(1);

    // Exactly one ProductReferenceActivated, recorded in the writing transaction — module catalog, the entity
    // envelope, the approver principal, and a PII-free payload (BOTH parents BY ID, post-transition active).
    $event = DomainEvent::query()->where('name', 'ProductReferenceActivated')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('ProductReference')
        ->and($event->entity_id)->toBe((string) $reference->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id)             // uncast bigint — loose compare spans engines
        ->and($event->payload['product_reference_id'] ?? null)->toEqual($reference->id)
        ->and($event->payload['product_variant_id'] ?? null)->toEqual($variant->id)
        ->and($event->payload['format_id'] ?? null)->toEqual($format->id)
        ->and($event->payload['lifecycle_state'] ?? null)->toBe('active')
        ->and($event->payload)->not->toHaveKey('version');          // PII-free / minimal (no persistence-only field)
});

it('blocks activation when the parent Variant is not active, naming the Variant (the activation cascade gate)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // Variant NOT active (reviewed), Format active — so ONLY the Variant gate can block, and the rejection
    // names the Variant: proving the first parent is independently gated (AC-0-FSM-10).
    $variant = ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);
    $format = Format::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    $reference = referenceLifecycleCreateDraft($creator, $variant->id, $format->id);
    actingAs($reviewer, 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);

    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductReference::class)->handle($reference))
        ->toThrow(ActivationCascadeViolation::class, 'ProductVariant');

    // The child stays reviewed and records neither the activation audit nor the *Activated event.
    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->where('action', 'catalog.product_reference.activated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceActivated')->count())->toBe(0);
});

it('blocks activation when the parent Format is not active, naming the Format (the activation cascade gate)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // Variant active, Format NOT active (reviewed) — the Variant gate passes, so ONLY the Format gate blocks
    // and the rejection names the Format: proving the SECOND parent is independently gated (each parent counts).
    $variant = ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $format = Format::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    $reference = referenceLifecycleCreateDraft($creator, $variant->id, $format->id);
    actingAs($reviewer, 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);

    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductReference::class)->handle($reference))
        ->toThrow(ActivationCascadeViolation::class, 'Format');

    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->where('action', 'catalog.product_reference.activated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceActivated')->count())->toBe(0);
});

it('rejects self-approval by the creator even when both parent gates would pass (governance precedes the gate)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    // BOTH parents active, so the cascade gate WOULD open — isolating the approval governance as the sole
    // reason for rejection and proving governance is ordered BEFORE the gate (the error names 'creator').
    $variant = ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $format = Format::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    $reference = referenceLifecycleCreateDraft($creator, $variant->id, $format->id);
    actingAs($reviewer, 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);

    actingAs($creator, 'operator');
    expect(fn () => app(ActivateProductReference::class)->handle($reference))
        ->toThrow(ApprovalGovernanceViolation::class, 'creator');

    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceActivated')->count())->toBe(0);
});

it('rejects activation on a non-reviewed PR via the from-state guard, before the gate', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft PR over non-active (factory draft) parents: the from-state guard fires FIRST (activate is valid
    // only from reviewed), so the FSM error is raised — not the cascade gate — proving the ordering.
    $reference = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Draft]);

    expect(fn () => app(ActivateProductReference::class)->handle($reference))
        ->toThrow(IllegalLifecycleTransition::class, 'draft');

    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceActivated')->count())->toBe(0);
});

it('rejects a submit on a non-draft PR, naming the offending state, and writes nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $reference = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    // Out-of-state: submit is valid only from draft. The message names the locked from-state (reviewed).
    expect(fn () => app(SubmitProductReferenceForReview::class)->handle($reference))
        ->toThrow(IllegalLifecycleTransition::class, 'reviewed');

    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('retires an active PR to retired, recording one ProductReferenceRetired', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // Build an active PR over factory-active parents (the gate reads their lifecycle_state).
    $variant = ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $format = Format::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $reference = referenceLifecycleCreateDraft($creator, $variant->id, $format->id);
    actingAs($reviewer, 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);
    actingAs($approver, 'operator');
    app(ActivateProductReference::class)->handle($reference);

    // Retire (active → retired): commercial-impact (operator floor), no activation gate.
    $retired = app(RetireProductReference::class)->handle($reference);

    expect($retired->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(AuditRecord::query()->where('action', 'catalog.product_reference.retired')->count())->toBe(1);

    $event = DomainEvent::query()->where('name', 'ProductReferenceRetired')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('ProductReference')
        ->and($event->entity_id)->toBe((string) $reference->id)
        ->and($event->actor_id)->toEqual($approver->id)
        ->and($event->payload['product_reference_id'] ?? null)->toEqual($reference->id)
        ->and($event->payload['product_variant_id'] ?? null)->toEqual($variant->id)
        ->and($event->payload['format_id'] ?? null)->toEqual($format->id)
        ->and($event->payload['lifecycle_state'] ?? null)->toBe('retired')
        ->and($event->payload)->not->toHaveKey('version');
});

it('reopens a retired PR to reviewed, recording one audit row and no domain event', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A retired PR via the factory (it bypasses the FSM — a pure fixture).
    $reference = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Retired]);

    $reviewed = app(ReopenProductReference::class)->handle($reference);

    expect($reviewed->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    $audit = AuditRecord::query()->where('action', 'catalog.product_reference.reopened')->sole();

    expect($audit->entity_type)->toBe('ProductReference')
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->before)->toBe(['lifecycle_state' => 'retired'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed']);

    // Reopen is event-silent — no *Activated / *Retired / *Reviewed recorded for the step.
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('records a review rejection with notes, keeps the PR in reviewed, and preserves prior audit rows', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $reference = referenceLifecycleCreateDraft($creator, $variant->id, $format->id);
    actingAs($reviewer, 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference); // the prior (submit) audit row

    $rejected = app(RejectProductReferenceReview::class)->handle($reference, 'The Format dimension needs confirmation before activation.');

    // Stays in reviewed — there is no revert to draft (§ 4.3).
    expect($rejected->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // One rejection audit row carrying the decision + notes + the acting reviewer principal.
    $rejection = AuditRecord::query()->where('action', 'catalog.product_reference.rejected')->sole();
    $after = $rejection->after ?? []; // narrow the nullable jsonb to an array; keys asserted order-independently (PG jsonb reorders)

    expect($rejection->entity_type)->toBe('ProductReference')
        ->and($rejection->entity_id)->toBe((string) $reference->id)
        ->and($rejection->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($rejection->actor_id)->toEqual($reviewer->id)
        ->and($rejection->before)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($after['lifecycle_state'] ?? null)->toBe('reviewed')
        ->and($after['decision'] ?? null)->toBe('rejected')
        ->and($after['notes'] ?? null)->toBe('The Format dimension needs confirmation before activation.')
        ->and($rejection->authorization_basis)->toBe('catalog-lifecycle');

    // The earlier submit audit row is intact (append-only) and no domain event was recorded for the rejection.
    expect(AuditRecord::query()->where('action', 'catalog.product_reference.submitted')->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceActivated')->count())->toBe(0);
});
