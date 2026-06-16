<?php

use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\ReopenProductMaster;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
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
