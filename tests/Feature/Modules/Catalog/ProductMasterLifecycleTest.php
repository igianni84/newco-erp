<?php

use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\RejectProductMasterReview;
use App\Modules\Catalog\Actions\ReopenProductMaster;
use App\Modules\Catalog\Actions\ResubmitProductMasterForReview;
use App\Modules\Catalog\Actions\RetireProductMaster;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Exceptions\ProducerActivationGateViolation;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\ProducerState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Module;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Support\Catalog\ProducerProjectionFixture;

use function Pest\Laravel\actingAs;

/**
 * Pins the shared lifecycle-transition mechanism, established on Product Master's two AUDIT-ONLY transitions
 * — submit (`draft → reviewed`) and reopen (`retired → reviewed`) (catalog-lifecycle-approval task 2.2;
 * design D1/D2; product-catalog — Requirement: Product Lifecycle State Machine). It proves the mechanism is
 * the sole `lifecycle_state` writer, records ONE audit row per step (before/after the lifecycle edge, the
 * actor from the ActorContext seam, the `catalog.product_master.<verb>` action) and NO domain event on
 * either checkpoint (Module 0 PRD § 14.2, AC-0-FSM-8), and that an out-of-state call is rejected against a
 * TRANSACTION-LOCKED re-read — leaving the row, the audit log and the event log unchanged.
 *
 * DatabaseMigrations (per the task hint + design D11): the mechanism opens its OWN DB::transaction, so the
 * audit recorder's `transactionLevel() === 0` guard sees a REAL commit (level 0 → 1 → 0) — the faithful
 * production shape, consistent with the change's consumer tests. Each test authenticates a distinct operator
 * with actingAs() (in memory, composes with the trait), so the resolved actor on each audit row is
 * (newco_ops, that operator's id).
 */
uses(DatabaseMigrations::class);

it('submits a draft Master to reviewed, recording one audit row and no domain event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // The real creation Action (per the task hint) — records ProductMasterCreated, but NO audit row.
    $master = app(CreateProductMaster::class)->handle(
        name: 'Château Margaux',
        producerId: ProducerProjectionFixture::known(42),
        appellation: 'Margaux',
        region: 'Bordeaux',
    );

    $reviewed = app(SubmitProductMasterForReview::class)->handle($master);

    // State moved draft → reviewed — assert the returned model AND the persisted row.
    expect($reviewed->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // Exactly one audit row for the submit, carrying the lifecycle edge + the operator principal.
    $audit = AuditRecord::query()->where('action', 'catalog.product_master.submitted')->sole();

    expect($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('ProductMaster')                 // matches the domain-event entity_type
        ->and($audit->entity_id)->toBe((string) $master->id)             // envelope entity_id is a string
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)            // resolved from ActorContext (operator guard)
        ->and($audit->actor_id)->toEqual($operator->id)                // uncast bigint; loose compare spans engines
        ->and($audit->before)->toBe(['lifecycle_state' => 'draft'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($audit->authorization_basis)->toBe('catalog-lifecycle');

    // The submit checkpoint is event-silent: no *Activated, no *Reviewed (the next event is the activation).
    expect(DomainEvent::query()->where('entity_type', 'ProductMaster')->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('reopens a retired Master to reviewed, recording one audit row and no domain event', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A retired Master via the factory (it bypasses the FSM — the retire Action lands in a later task).
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Retired]);

    $reviewed = app(ReopenProductMaster::class)->handle($master);

    expect($reviewed->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    $audit = AuditRecord::query()->where('action', 'catalog.product_master.reopened')->sole();

    expect($audit->entity_type)->toBe('ProductMaster')
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->before)->toBe(['lifecycle_state' => 'retired'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed']);

    // Reopen is event-silent — no *Activated / *Retired / *Reviewed recorded for the step.
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('rejects a submit on a non-draft Master, naming the offending state, and writes nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    // Out-of-state: submit is valid only from draft. The message names the locked from-state (reviewed),
    // proving the rejection carries the state the mechanism actually read.
    expect(fn () => app(SubmitProductMasterForReview::class)->handle($master))
        ->toThrow(IllegalLifecycleTransition::class, 'reviewed');

    // The rejected attempt left the state untouched and wrote NO audit row.
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('rejects a reopen on a non-retired Master and writes nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Draft]);

    expect(fn () => app(ReopenProductMaster::class)->handle($master))
        ->toThrow(IllegalLifecycleTransition::class, 'draft');

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('guards the from-state against the locked re-read, not the caller stale instance', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Draft]);
    $stale = ProductMaster::findOrFail($master->id); // a second instance, draft in memory

    app(SubmitProductMasterForReview::class)->handle($master); // → reviewed in the database

    // $stale still reports draft in memory, but the mechanism re-reads the row under lock and sees reviewed,
    // so the stale-driven submit is rejected — the lock re-read, not the passed instance, guards correctness.
    expect($stale->lifecycle_state)->toBe(LifecycleState::Draft);
    expect(fn () => app(SubmitProductMasterForReview::class)->handle($stale))
        ->toThrow(IllegalLifecycleTransition::class);

    // The stale-driven attempt changed nothing: still reviewed, still exactly one submit audit row.
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.submitted')->count())->toBe(1);
});

it('round-trips draft → reviewed → (retired) → reviewed, recording an audit row per audit-only step', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // Submit, then simulate the (later-task) retire by setting retired directly, then reopen — proving both
    // audit-only legs traverse the shared mechanism and each leaves exactly one matching audit row.
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Draft]);

    app(SubmitProductMasterForReview::class)->handle($master);
    $master->update(['lifecycle_state' => LifecycleState::Retired]); // stand-in for the retire transition (task 3.2)
    app(ReopenProductMaster::class)->handle($master);

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.submitted')->count())->toBe(1)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.reopened')->count())->toBe(1)
        // No lifecycle domain event from either audit-only checkpoint.
        ->and(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Approval governance (task 2.3) — separation of duties, role count, rejection
|--------------------------------------------------------------------------
|
| The Creator → Reviewer → Approver governance (design D5; product-catalog — Requirement: Approval Governance;
| Module 0 PRD § 4.2) layered onto the shared mechanism's commercial-impact steps. The approval step
| (`reviewed → active`) is driven here through the bare LifecycleTransition mechanism, because the per-entity
| ActivateProductMaster Action and the Producer-activation gate land in task 3.2 — these tests exercise the
| GOVERNANCE layer in isolation (the governance guard runs, and rejects, before any gate would). The lineage
| the guard reads is real: CreateProductMaster records the `*Created` event (the creator), and the submit
| records the `draft → reviewed` audit row (the reviewer).
*/

/**
 * Create a draft Master as $creator through the real CreateProductMaster Action — recording the
 * `ProductMasterCreated` event with $creator's actor_id, the creator lineage the governance guard reads.
 * Leaves $creator as the acting principal (the caller switches before the next governance step). The
 * name/appellation default to a single identity; a caller standing up two Masters under one producer passes
 * a DISTINCT identity for the second, else the create-time BR-Identity-1 dedup rejects it.
 *
 * The producer is made KNOWN to Catalog first (AC-0-XM-2, task 5.2 — creation refuses an unprojected id).
 * `registered` is the weakest status that admits creation, so nothing here accidentally opens the ACTIVATION
 * gate the tests below are pinning; a caller wanting an activatable producer calls lifecycleProjectProducer
 * with `ProducerActivated` (in either order — the fixture is idempotent and the projector's watermark, seeded
 * at 0, is strictly advanced by any real producer event).
 */
function lifecycleCreateDraftMaster(Operator $creator, int $producerId = 7, string $name = 'Château Margaux', string $appellation = 'Margaux'): ProductMaster
{
    actingAs($creator, 'operator');

    return app(CreateProductMaster::class)->handle(
        name: $name,
        producerId: ProducerProjectionFixture::known($producerId),
        appellation: $appellation,
        region: 'Bordeaux',
    );
}

/**
 * Project a producer state into Catalog's read model exactly as Module K's emit would: record a supply-side
 * `ProducerActivated`/`ProducerRetired` (module `parties`, entity_type `Producer`, payload
 * `{producer_id, status}`) inside a real DB::transaction, so the inline post-commit hook fans it out to the
 * registered ProducerLifecycleProjector, which upserts `catalog_producer_states` — the projection the
 * Producer activation gate reads. Distinctly named to avoid colliding with the projector test's global
 * `recordProducerLifecycleEvent` (one shared Pest namespace).
 */
function lifecycleProjectProducer(string $name, int $producerId, string $status): void
{
    DB::transaction(fn () => app(DomainEventRecorder::class)->record(
        name: $name,
        module: Module::Parties->value,
        actorRole: ActorRole::System,
        actorId: null,
        entityType: 'Producer',
        entityId: (string) $producerId,
        payload: ['producer_id' => $producerId, 'status' => $status],
    ));
}

/** The entity's latest audit action — the derivation behind "rejection-pending" (design D5; no schema flag). */
function latestGovernanceAction(ProductMaster $master): ?string
{
    $action = AuditRecord::query()
        ->where('entity_type', 'ProductMaster')
        ->where('entity_id', (string) $master->id)
        ->orderByDesc('id')
        ->value('action');

    return is_string($action) ? $action : null;
}

it('rejects approval by the reviewer in the three-step flow (separation of duties)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    $master = lifecycleCreateDraftMaster($creator);
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master); // the reviewer submits draft → reviewed

    // The reviewer attempts the approval step on the Master they reviewed — three-step self-approval.
    expect(fn () => app(LifecycleTransition::class)->transition($master, LifecycleTransitionType::Activate, 'ProductMaster'))
        ->toThrow(ApprovalGovernanceViolation::class, 'reviewer');

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.activated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('entity_type', 'ProductMaster')->where('name', 'like', '%Activated%')->count())->toBe(0);
});

it('rejects approval by the creator (separation of duties)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    $master = lifecycleCreateDraftMaster($creator);
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    // The creator attempts the approval step — rejected on the no-self-approval floor (default role_count 3).
    actingAs($creator, 'operator');
    expect(fn () => app(LifecycleTransition::class)->transition($master, LifecycleTransitionType::Activate, 'ProductMaster'))
        ->toThrow(ApprovalGovernanceViolation::class, 'creator');

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('entity_type', 'ProductMaster')->where('name', 'like', '%Activated%')->count())->toBe(0);
});

it('enforces reviewer ≠ creator under role_count 3 but admits the two-actor path under role_count 2', function () {
    $creator = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // The creator both creates AND submits, so the reviewer == the creator (only two distinct operators
    // participate in create+submit+approve).
    $master = lifecycleCreateDraftMaster($creator);
    app(SubmitProductMasterForReview::class)->handle($master); // still acting as the creator

    // role_count 3 (default): three DISTINCT operators are required, but the creator-as-reviewer collapses
    // the lineage to two — the approval is refused even though the approver is distinct.
    actingAs($approver, 'operator');
    expect(fn () => app(LifecycleTransition::class)->transition($master, LifecycleTransitionType::Activate, 'ProductMaster'))
        ->toThrow(ApprovalGovernanceViolation::class, 'three distinct');
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // role_count 2 (Creator → Approver): the reviewer role collapses, so only approver ≠ creator is
    // required — the same distinct approver now satisfies the floor and the Master activates.
    config(['catalog.approval.role_count' => 2]);
    $active = app(LifecycleTransition::class)->transition($master, LifecycleTransitionType::Activate, 'ProductMaster');
    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Active);
});

it('rejects an approval step performed by a system actor', function () {
    // A reviewed Master with no operator context — ActorContext resolves (system, null), which cannot
    // satisfy the distinct-actor floor.
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    expect(fn () => app(LifecycleTransition::class)->transition($master, LifecycleTransitionType::Activate, 'ProductMaster'))
        ->toThrow(ApprovalGovernanceViolation::class, 'operator');

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->where('entity_type', 'ProductMaster')->where('name', 'like', '%Activated%')->count())->toBe(0);
});

it('records a review rejection with notes, keeps the Master in reviewed, and preserves prior audit rows', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    $master = lifecycleCreateDraftMaster($creator);
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master); // the prior (submit) audit row

    $rejected = app(RejectProductMasterReview::class)->handle($master, 'Label artwork is missing the vintage.');

    // Stays in reviewed — there is no revert to draft (§ 4.3).
    expect($rejected->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // One rejection audit row carrying the decision + notes + the acting reviewer principal.
    $rejection = AuditRecord::query()->where('action', 'catalog.product_master.rejected')->sole();
    $after = $rejection->after ?? []; // narrow the nullable jsonb to an array; keys asserted order-independently (PG jsonb reorders)

    expect($rejection->entity_type)->toBe('ProductMaster')
        ->and($rejection->entity_id)->toBe((string) $master->id)
        ->and($rejection->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($rejection->actor_id)->toEqual($reviewer->id)
        ->and($rejection->before)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($after['lifecycle_state'] ?? null)->toBe('reviewed')
        ->and($after['decision'] ?? null)->toBe('rejected')
        ->and($after['notes'] ?? null)->toBe('Label artwork is missing the vintage.')
        ->and($rejection->authorization_basis)->toBe('catalog-lifecycle');

    // The earlier submit audit row is intact (append-only) and no domain event was recorded for the rejection.
    expect(AuditRecord::query()->where('action', 'catalog.product_master.submitted')->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('entity_type', 'ProductMaster')->where('name', 'like', '%Activated%')->count())->toBe(0);
});

it('rejects a review rejection on a non-reviewed Master, naming the state, and writes nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Draft]);

    // A rejection is a reviewed → reviewed decision: invalid from draft. The message names the locked state.
    expect(fn () => app(RejectProductMasterReview::class)->handle($master, 'n/a'))
        ->toThrow(IllegalLifecycleTransition::class, 'draft');

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('rejects a review rejection performed by a system actor', function () {
    // A reviewed Master with no operator context — a rejection is a reviewer/approver decision, so a
    // system actor cannot perform it (the from-state passes; the operator floor rejects).
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    expect(fn () => app(RejectProductMasterReview::class)->handle($master, 'n/a'))
        ->toThrow(ApprovalGovernanceViolation::class, 'operator');

    expect(AuditRecord::query()->count())->toBe(0)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);
});

/*
|--------------------------------------------------------------------------
| Re-submit — the twin of reject that re-arms review (task 2.1; RM-06)
|--------------------------------------------------------------------------
|
| ResubmitProductMasterForReview (`reviewed → reviewed`, § 4.3; canon MVP-DEC-019) mirrors the rejection
| decision: audit-only, no domain event, from-state `reviewed`, operator-floored. It re-arms the approval
| flow after a rejection — becoming the freshest governance action so the block-gate (task 2.2) clears. Here
| the mechanism is proven in isolation; the block-until-resubmit behaviour lands in task 2.2.
*/

it('re-submits a rejected Master, keeps it in reviewed, records one resubmitted row and no domain event', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    $master = lifecycleCreateDraftMaster($creator);
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master); // draft → reviewed (the reviewer)

    // The reviewer rejects — the Master stays reviewed, rejection-pending derived from the latest action.
    app(RejectProductMasterReview::class)->handle($master, 'Needs a clearer provenance note.');
    expect(latestGovernanceAction($master))->toBe('catalog.product_master.rejected');

    // The Creator edits in place and re-submits — the twin of reject: reviewed → reviewed, audit-only.
    actingAs($creator, 'operator');
    $resubmitted = app(ResubmitProductMasterForReview::class)->handle($master);

    // Stays in reviewed (§ 4.3 — no revert to draft); the re-submit is now the freshest governance action.
    expect($resubmitted->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(latestGovernanceAction($master))->toBe('catalog.product_master.resubmitted');

    // Exactly one re-submit audit row carrying the decision + the acting principal (the Creator).
    $resubmit = AuditRecord::query()->where('action', 'catalog.product_master.resubmitted')->sole();
    $after = $resubmit->after ?? []; // narrow the nullable jsonb to an array; keys asserted order-independently (PG jsonb reorders)

    expect($resubmit->entity_type)->toBe('ProductMaster')
        ->and($resubmit->entity_id)->toBe((string) $master->id)
        ->and($resubmit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($resubmit->actor_id)->toEqual($creator->id)
        ->and($resubmit->before)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($after['lifecycle_state'] ?? null)->toBe('reviewed')
        ->and($after['decision'] ?? null)->toBe('resubmitted')
        ->and($resubmit->authorization_basis)->toBe('catalog-lifecycle');

    // No `notes` on a re-submit (unlike reject) — the "what changed" history is RM-14's concern (design D2).
    expect($after)->not->toHaveKey('notes');

    // The earlier rejection row is intact (append-only) and the re-submit records NO domain event.
    expect(AuditRecord::query()->where('action', 'catalog.product_master.rejected')->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('entity_type', 'ProductMaster')->where('name', 'like', '%Activated%')->count())->toBe(0);
});

it('rejects a re-submit on a non-reviewed Master, naming the state, and writes nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Draft]);

    // A re-submit is a reviewed → reviewed decision: invalid from draft. The message names the locked state.
    expect(fn () => app(ResubmitProductMasterForReview::class)->handle($master))
        ->toThrow(IllegalLifecycleTransition::class, 'draft');

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('rejects a re-submit performed by a system actor', function () {
    // A reviewed Master with no operator context — a re-submit is a Creator decision, so a system actor
    // cannot perform it (the from-state passes; the operator floor rejects).
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    expect(fn () => app(ResubmitProductMasterForReview::class)->handle($master))
        ->toThrow(ApprovalGovernanceViolation::class, 'operator');

    expect(AuditRecord::query()->count())->toBe(0)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);
});

/*
|--------------------------------------------------------------------------
| Review-freshness block-gate — a pending rejection blocks activation (task 2.2; RM-06)
|--------------------------------------------------------------------------
|
| Canon MVP-DEC-019 (design D1/D3; product-catalog — Requirement: Approval Governance, "A pending rejection
| blocks activation until re-submit"): while an entity's latest governance action is an un-remediated
| rejection it is REJECTION-PENDING, and `activate` (`reviewed → active`) is BLOCKED — enforced in
| ApprovalGovernance::guard() as a DERIVE-FROM-AUDIT read (no schema flag, design D3), thrown as
| ApprovalGovernanceViolation so it surfaces through the console kit's outcome path (task 4.1). An explicit
| `re-submit` becomes the freshest action and clears the block. These drive the REAL ActivateProductMaster
| against an active-projected producer, so the blocked "no ProductMasterActivated event" assertion is meaningful
| and the block is proven to precede the Producer gate. This INVERTS the pre-RM-06 "rejection is not terminal"
| behaviour.
|
| Since catalog-module-0-completeness-sweep task 1.2 the derivation is VERB-FILTERED (design D4): only actions
| ending `.submitted` / `.resubmitted` / `.rejected` / `.identity_updated` participate, and the last two are the
| STALE ones. `.activated` / `.retired` / `.reopened` are therefore INVISIBLE to it — the reopen scenario below
| still activates because the buried rejection was remediated by a `.resubmitted`, NOT because the `.reopened`
| row cleared anything. ReviewFreshnessVerbFilterTest pins the filter (and the identity-edit stale cause) itself.
*/

it('blocks activation while a rejection is pending and admits it only after a re-submit', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    lifecycleProjectProducer('ProducerActivated', 7, 'active'); // producer gate open — the BLOCK is what fires

    $master = lifecycleCreateDraftMaster($creator, 7);
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    // The reviewer rejects — the Master stays reviewed; "rejection-pending" is DERIVED (latest audit action).
    app(RejectProductMasterReview::class)->handle($master, 'Needs a clearer provenance note.');
    expect(latestGovernanceAction($master))->toBe('catalog.product_master.rejected');

    // A DISTINCT approver (no self-approval) attempts activation: the review-freshness block-gate fires FIRST
    // — before the Producer gate (which would pass here) — because the latest governance action is a rejection.
    // The 'un-remediated' token pins the BLOCK message (absent from every separation-of-duties reason).
    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($master))
        ->toThrow(ApprovalGovernanceViolation::class, 'un-remediated');

    // Blocked: still reviewed, no activation audit row, no ProductMasterActivated — the rejection row stands.
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.activated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.rejected')->count())->toBe(1);

    // The Creator edits in place and re-submits — the freshest governance action becomes `.resubmitted`.
    actingAs($creator, 'operator');
    app(ResubmitProductMasterForReview::class)->handle($master);
    expect(latestGovernanceAction($master))->toBe('catalog.product_master.resubmitted');

    // The distinct approver now activates → active, exactly one ProductMasterActivated, rejection row preserved.
    actingAs($approver, 'operator');
    $active = app(ActivateProductMaster::class)->handle($master);

    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(1)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.rejected')->count())->toBe(1);
});

it('activates a never-rejected Master — the block-gate does not false-fire on a fresh submit', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    lifecycleProjectProducer('ProducerActivated', 7, 'active');

    $master = lifecycleCreateDraftMaster($creator, 7);
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    // Latest governance action is the submit (never rejected) — the block-gate must let activation through.
    expect(latestGovernanceAction($master))->toBe('catalog.product_master.submitted');

    actingAs($approver, 'operator');
    $active = app(ActivateProductMaster::class)->handle($master);

    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(1);
});

it('does not treat a reopened Master as rejection-pending even with a rejection earlier in its history', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    lifecycleProjectProducer('ProducerActivated', 7, 'active');

    $master = lifecycleCreateDraftMaster($creator, 7);
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    // A rejection early in history, remediated by a re-submit, then a full activate → retire → reopen.
    app(RejectProductMasterReview::class)->handle($master, 'Early nit.');
    actingAs($creator, 'operator');
    app(ResubmitProductMasterForReview::class)->handle($master);
    actingAs($approver, 'operator');
    app(ActivateProductMaster::class)->handle($master); // → active (the rejection is already remediated)
    app(RetireProductMaster::class)->handle($master);   // → retired
    app(ReopenProductMaster::class)->handle($master);    // → reviewed; latest action is now `.reopened`

    // The `.rejected` row is still in history, but it was already remediated by the `.resubmitted` above — the
    // latest review-freshness-RELEVANT action. (`.reopened` is the raw latest action, and the verb filter simply
    // does not see it: it neither blocks nor clears.) So the buried rejection does not block.
    expect(latestGovernanceAction($master))->toBe('catalog.product_master.reopened')
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.rejected')->count())->toBe(1);

    $reactivated = app(ActivateProductMaster::class)->handle($master);

    expect($reactivated->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Two rejection rounds — the block-gate holds across rounds (task 2.3; RM-06)
|--------------------------------------------------------------------------
|
| AC-0-J-7 / product-catalog — Requirement: Approval Governance, "Two rejection rounds each block until
| re-submit and preserve full history". One scenario drives reject → re-submit → reject → re-submit → activate
| with a clean three-operator lineage: the Creator (C) creates + re-submits both rounds, the Reviewer (R)
| submits + rejects both rounds, and a distinct Approver (A) activates. The review-freshness block-gate fires
| after EACH rejection (latest action `.rejected`) and clears after EACH re-submit (latest action
| `.resubmitted`); the final separation-of-duties holds because `reviewerOf` reads the latest `%.submitted` —
| the single submit by R, NEVER a `.resubmitted` (the char before `submitted` is `e`, not `.`) — so the
| reviewer stays R across both rounds and A ∉ {C, R}. The append-only trail keeps BOTH rejection rows (with
| their distinct notes + acting reviewer) and BOTH re-submission rows; the final activation records exactly one
| ProductMasterActivated. This is the composed proof over the isolated block-gate + re-submit tests above.
*/

it('runs two rejection rounds, blocking activation after each until the following re-submit and preserving the full history', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    lifecycleProjectProducer('ProducerActivated', 7, 'active'); // producer gate open — the BLOCK is what fires

    $master = lifecycleCreateDraftMaster($creator, 7);         // C creates the draft
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);  // R submits draft → reviewed (the sole `.submitted`)

    // ── Round 1 ───────────────────────────────────────────────────────────────────────────────────
    // R rejects with a distinct note; the Master stays reviewed and becomes rejection-pending (latest action).
    app(RejectProductMasterReview::class)->handle($master, 'Round 1: vintage missing from the label.');
    expect(latestGovernanceAction($master))->toBe('catalog.product_master.rejected');

    // The distinct approver's activation is BLOCKED by the review-freshness gate — no state change, no event.
    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($master))
        ->toThrow(ApprovalGovernanceViolation::class, 'un-remediated');
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0);

    // C re-submits — the freshest action becomes `.resubmitted`, clearing the block.
    actingAs($creator, 'operator');
    app(ResubmitProductMasterForReview::class)->handle($master);
    expect(latestGovernanceAction($master))->toBe('catalog.product_master.resubmitted');

    // ── Round 2 ───────────────────────────────────────────────────────────────────────────────────
    // R rejects again with a SECOND distinct note; rejection-pending again.
    actingAs($reviewer, 'operator');
    app(RejectProductMasterReview::class)->handle($master, 'Round 2: provenance note still unclear.');
    expect(latestGovernanceAction($master))->toBe('catalog.product_master.rejected');

    // Blocked identically on the second round.
    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($master))
        ->toThrow(ApprovalGovernanceViolation::class, 'un-remediated');
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0);

    // C re-submits the second time — the block clears once more.
    actingAs($creator, 'operator');
    app(ResubmitProductMasterForReview::class)->handle($master);
    expect(latestGovernanceAction($master))->toBe('catalog.product_master.resubmitted');

    // ── Final activation ──────────────────────────────────────────────────────────────────────────
    // A distinct approver activates: SoD holds (creator C, reviewer R = the sole `.submitted` actor, approver A
    // — all distinct — because `.resubmitted` never matches `%.submitted`, so R stays the reviewer both rounds).
    actingAs($approver, 'operator');
    $active = app(ActivateProductMaster::class)->handle($master);

    // Final state active, and exactly the append-only shape across both rounds: 2 rejections, 2 re-submits,
    // 1 activation, one ProductMasterActivated.
    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.rejected')->count())->toBe(2)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.resubmitted')->count())->toBe(2)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.activated')->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(1);

    // BOTH rejection rows are preserved with their distinct notes, in append order (history not collapsed),
    // each carrying the acting reviewer principal.
    $rejections = AuditRecord::query()
        ->where('action', 'catalog.product_master.rejected')
        ->orderBy('id')
        ->get();

    expect($rejections)->toHaveCount(2);

    $firstAfter = $rejections[0]->after ?? [];  // narrow the nullable jsonb to an array; keys read order-independently (PG reorders)
    $secondAfter = $rejections[1]->after ?? [];
    $actorIds = $rejections->pluck('actor_id')->all(); // pluck to a plain array — a bare `$rejections[$i]->actor_id` reads a nullable collection offset (PHPStan)

    expect($firstAfter['notes'] ?? null)->toBe('Round 1: vintage missing from the label.')
        ->and($secondAfter['notes'] ?? null)->toBe('Round 2: provenance note still unclear.')
        ->and($actorIds[0])->toEqual($reviewer->id)   // uncast bigint; loose compare spans engines
        ->and($actorIds[1])->toEqual($reviewer->id);
});

/*
|--------------------------------------------------------------------------
| Activate / Retire + the Producer activation gate (task 3.2)
|--------------------------------------------------------------------------
|
| ActivateProductMaster (`reviewed → active`) wires the per-entity Producer gate (design D6) + the
| `ProductMasterActivated` event onto the shared mechanism; RetireProductMaster (`active → retired`) records
| `ProductMasterRetired` (product-catalog — Requirements: Producer Activation Gate, Product Lifecycle State
| Machine, Product Lifecycle Events). These tests drive the real Actions (distinct operators) against the
| producer-state projection the inline ProducerLifecycleProjector maintains, proving: the gate's three
| negative paths (absent / draft-as-absent / retired — AC-0-FSM-12) and its positive path (AC-0-EVT-20);
| block-new while preserving actives after a real `ProducerRetired` (AC-0-EVT-21 / AC-0-FSM-13);
| re-activation re-checks the gate (AC-0-J-10); a held-but-unactivatable Master (AC-0-J-2); and the
| transactional, PII-free `*Activated`/`*Retired` events (AC-0-EVT-1).
*/

it('activates a reviewed Master to active when its Producer is active, recording one ProductMasterActivated', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    lifecycleProjectProducer('ProducerActivated', 7, 'active'); // the consumer projects producer 7 active

    $master = lifecycleCreateDraftMaster($creator, 7);
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    actingAs($approver, 'operator');
    $active = app(ActivateProductMaster::class)->handle($master);

    // State moved reviewed → active (returned model + persisted row) + one activation audit row.
    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.activated')->count())->toBe(1);

    // Exactly one ProductMasterActivated, recorded in the writing transaction — module catalog, the entity
    // envelope, the approver principal, and a PII-free payload (producer BY ID only, post-transition active).
    $event = DomainEvent::query()->where('name', 'ProductMasterActivated')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('ProductMaster')
        ->and($event->entity_id)->toBe((string) $master->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id)              // uncast bigint — loose compare spans engines
        ->and($event->payload['product_master_id'] ?? null)->toEqual($master->id)
        ->and($event->payload['producer_id'] ?? null)->toEqual(7)
        ->and($event->payload['lifecycle_state'] ?? null)->toBe('active')
        ->and($event->payload)->not->toHaveKey('name');             // PII-free (no descriptive core)
});

it('blocks activation when the linked Producer is absent from the projection, holding it reviewed', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // The gate's ABSENT-row branch: no row ⇒ not-gated-open (fail-closed). Reaching it takes a deliberate
    // detour now, and the detour is the point. Since AC-0-XM-2 (task 5.2) creation itself demands a projection
    // row, so a Master can no longer be BORN under an unknown producer — the branch survives for the states
    // creation cannot police: a Master predating the guard, or a read model purged/not yet rebuilt beneath a
    // live Master. We construct exactly that: create through the real lineage (the SoD triple below reads the
    // creator off `ProductMasterCreated`, so a factory Master would not do), then remove the row.
    $master = lifecycleCreateDraftMaster($creator, 9);
    ProducerState::query()->where('producer_id', 9)->delete();

    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($master))
        ->toThrow(ProducerActivationGateViolation::class);

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.activated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0);
});

it('blocks activation when the linked Producer is only registered in the projection', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // The `registered` half of the gate matrix (catalog-module-0-completeness-sweep, task 5.1; the delta's
    // "Producer Activation Gate" scenario lists `registered` beside `retired` and absent). The widened
    // projection now gives a merely-CREATED producer a row — proving that EXISTENCE (which admits Master
    // creation, task 5.2) is not activeness: the gate is unchanged and still demands `status === active`.
    lifecycleProjectProducer('ProducerCreated', 9, 'draft');

    // Non-vacuity: the row really is there and really is `registered` — the gate is rejecting a PRESENT row,
    // not silently taking the no-row branch that the sibling test above already covers.
    expect(ProducerState::query()->where('producer_id', 9)->sole()->status)
        ->toBe(ProducerProjectionStatus::Registered);

    $master = lifecycleCreateDraftMaster($creator, 9);
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($master))
        ->toThrow(ProducerActivationGateViolation::class);

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.activated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0);
});

it('blocks activation when the linked Producer is retired in the projection', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // First event seen for producer 9 is the retirement — the consumer seeds the row retired; the gate rejects.
    lifecycleProjectProducer('ProducerRetired', 9, 'retired');

    $master = lifecycleCreateDraftMaster($creator, 9);
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($master))
        ->toThrow(ProducerActivationGateViolation::class);

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0);
});

it('rejects activation on a non-reviewed Master via the from-state guard, before the gate', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft Master with an unprojected producer: the from-state guard fires FIRST (activate is valid only
    // from reviewed), so the FSM error is raised — not the gate — proving the ordering (assert + no event).
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Draft, 'producer_id' => 9]);

    expect(fn () => app(ActivateProductMaster::class)->handle($master))
        ->toThrow(IllegalLifecycleTransition::class, 'draft');

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0);
});

it('retires an active Master to retired, recording one ProductMasterRetired', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    lifecycleProjectProducer('ProducerActivated', 7, 'active');
    $master = lifecycleCreateDraftMaster($creator, 7);
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);
    actingAs($approver, 'operator');
    app(ActivateProductMaster::class)->handle($master);

    // Retire (active → retired): commercial-impact (operator floor), no activation gate.
    $retired = app(RetireProductMaster::class)->handle($master);

    expect($retired->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.retired')->count())->toBe(1);

    $event = DomainEvent::query()->where('name', 'ProductMasterRetired')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('ProductMaster')
        ->and($event->entity_id)->toBe((string) $master->id)
        ->and($event->actor_id)->toEqual($approver->id)
        ->and($event->payload['product_master_id'] ?? null)->toEqual($master->id)
        ->and($event->payload['producer_id'] ?? null)->toEqual(7)
        ->and($event->payload['lifecycle_state'] ?? null)->toBe('retired')
        ->and($event->payload)->not->toHaveKey('name');
});

it('blocks a new activation after the Producer retires while preserving an already-active Master', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    lifecycleProjectProducer('ProducerActivated', 7, 'active');

    // M1: created, submitted, approved → active while producer 7 is active.
    $m1 = lifecycleCreateDraftMaster($creator, 7, 'Château Margaux', 'Margaux');
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($m1);
    actingAs($approver, 'operator');
    app(ActivateProductMaster::class)->handle($m1);
    expect(ProductMaster::findOrFail($m1->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // M2 on the SAME producer 7 (a DISTINCT identity — else the create-time dedup would reject it), submitted
    // to reviewed but not yet activated.
    $m2 = lifecycleCreateDraftMaster($creator, 7, 'Château Latour', 'Pauillac');
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($m2);

    // Producer 7 retires (the consumer projects retired) — block-new.
    lifecycleProjectProducer('ProducerRetired', 7, 'retired');

    // M2's activation is now gate-blocked…
    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($m2))
        ->toThrow(ProducerActivationGateViolation::class);

    // …while M1 stays active (block-new, never cascade-retire), and only M1's activation event exists.
    expect(ProductMaster::findOrFail($m2->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(ProductMaster::findOrFail($m1->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(1);
});

it('re-activates a reopened Master when its Producer is still active (re-activation re-checks the gate)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    lifecycleProjectProducer('ProducerActivated', 7, 'active');

    $master = lifecycleCreateDraftMaster($creator, 7);
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);
    actingAs($approver, 'operator');
    app(ActivateProductMaster::class)->handle($master);

    // Retire then reopen → reviewed (both operator-floored; the reopen records no submit, so the reviewer
    // lineage the governance reads is still the original submitter).
    app(RetireProductMaster::class)->handle($master);
    app(ReopenProductMaster::class)->handle($master);

    // Producer 7 is still active, so the re-activation re-passes the gate and records a fresh *Activated.
    $reactivated = app(ActivateProductMaster::class)->handle($master);

    expect($reactivated->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(2);
});

it('blocks re-activation when the Producer has since retired (re-activation is not exempt from the gate)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    lifecycleProjectProducer('ProducerActivated', 7, 'active');

    $master = lifecycleCreateDraftMaster($creator, 7);
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);
    actingAs($approver, 'operator');
    app(ActivateProductMaster::class)->handle($master);

    app(RetireProductMaster::class)->handle($master);
    app(ReopenProductMaster::class)->handle($master); // → reviewed

    // The Producer retires before the re-activation — the gate re-check now blocks it.
    lifecycleProjectProducer('ProducerRetired', 7, 'retired');

    expect(fn () => app(ActivateProductMaster::class)->handle($master))
        ->toThrow(ProducerActivationGateViolation::class);

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        // Only the first activation recorded an event; the blocked re-activation recorded none.
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(1);
});
