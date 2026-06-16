<?php

use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\RejectProductMasterReview;
use App\Modules\Catalog\Actions\ReopenProductMaster;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;

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
        producerId: 42,
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
 * Leaves $creator as the acting principal (the caller switches before the next governance step).
 */
function lifecycleCreateDraftMaster(Operator $creator, int $producerId = 7): ProductMaster
{
    actingAs($creator, 'operator');

    return app(CreateProductMaster::class)->handle(
        name: 'Château Margaux',
        producerId: $producerId,
        appellation: 'Margaux',
        region: 'Bordeaux',
    );
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

it('lets the approval flow complete after a rejection (rejection is not terminal)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $master = lifecycleCreateDraftMaster($creator);
    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    // The reviewer rejects with notes — the Master stays in reviewed, "rejection-pending" derived from the
    // latest governance audit action (no schema flag).
    app(RejectProductMasterReview::class)->handle($master, 'Needs a clearer provenance note.');
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(latestGovernanceAction($master))->toBe('catalog.product_master.rejected');

    // A distinct approver then approves — the rejection did not block the flow; it now completes.
    actingAs($approver, 'operator');
    $active = app(LifecycleTransition::class)->transition($master, LifecycleTransitionType::Activate, 'ProductMaster');

    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        // The latest governance action is now the activation — rejection-pending is cleared (derived).
        ->and(latestGovernanceAction($master))->toBe('catalog.product_master.activated')
        // The rejection row is preserved in the append-only trail.
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.rejected')->count())->toBe(1);
});
