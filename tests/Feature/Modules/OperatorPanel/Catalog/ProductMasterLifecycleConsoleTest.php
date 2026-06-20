<?php

// Task 4.1 (operator-console-catalog-master; design L2/L5/L8; ADR 2026-06-19; spec — Operator advances a
// Product Master through the review-and-approval lifecycle) — the console's submit-for-review + reject
// header actions on the Product Master view page. These pin the SoD slice's first half: the console SURFACES
// the domain transition (it calls SubmitProductMasterForReview / RejectProductMasterReview and never writes
// `lifecycle_state` itself — the no-Eloquent-write rule, task 1.2) and renders a domain rejection (an
// out-of-state IllegalLifecycleTransition) as a danger NOTIFICATION rather than an unhandled 500. Each
// audit-only step records its audit_records row carrying the operator envelope (actor_role newco_ops + the
// operator id, resolved from the `operator` guard via the platform ActorContext seam) and NO domain event —
// submit/reject are event-silent checkpoints (Module 0 PRD § 14.2). The console never re-checks the
// from-state or the SoD floor (design L5); the domain is the sole authority.
//
// DatabaseMigrations (mirroring ProductMasterCreateConsoleTest / ProductMasterLifecycleTest): the console
// action drives a real domain action that opens its OWN DB::transaction, so the AuditRecorder's
// transaction-level guard sees a real commit (level 0 → 1 → 0) — the faithful production shape (RefreshDatabase
// would wrap every write in a never-committed outer transaction). Catalog enums/models/actions are imported
// freely here: the {Models, Actions} import-boundary carve-out (task 1.3) governs OperatorPanel PRODUCTION
// code, not tests.

use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\ViewProductMaster;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

/**
 * A draft Master created through the real Catalog action as the currently-acting operator (records
 * ProductMasterCreated, no audit row). Submit/reject do not consult the producer gate (only activate does),
 * so no producer-state projection is needed for these checkpoints.
 */
function lifecycleConsoleDraftMaster(int $producerId = 55, string $name = 'Château Console', string $appellation = 'Pauillac'): ProductMaster
{
    return app(CreateProductMaster::class)->handle(
        name: $name,
        producerId: $producerId,
        appellation: $appellation,
        region: 'Bordeaux',
    );
}

it('submits a draft Master for review through the console, recording the submit audit row and no domain event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $master = lifecycleConsoleDraftMaster();
    expect($master->lifecycle_state)->toBe(LifecycleState::Draft);

    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('submit')
        ->assertNotified((string) __('operator_console.product_master.notifications.submitted'));

    // State advanced draft → reviewed via the domain action (the console never writes lifecycle_state).
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // Exactly one submit audit row carrying the operator envelope + the lifecycle edge; submit is audit-only.
    $audit = AuditRecord::query()->where('action', 'catalog.product_master.submitted')->sole();

    expect($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('ProductMaster')
        ->and($audit->entity_id)->toBe((string) $master->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($operator->id)
        ->and($audit->before)->toBe(['lifecycle_state' => 'draft'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($audit->authorization_basis)->toBe('catalog-lifecycle');

    // Event-silent: no *Reviewed, no *Activated — the only ProductMaster event remains the creation's.
    expect(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('entity_type', 'ProductMaster')->count())->toBe(1);
});

it('records a console rejection with notes, keeping the Master in reviewed and emitting no event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $master = lifecycleConsoleDraftMaster();
    // Reach `reviewed` via the real submit action (proven elsewhere); then REJECT through the console.
    app(SubmitProductMasterForReview::class)->handle($master);

    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('reject', ['notes' => 'Label artwork is missing the vintage.'])
        ->assertNotified((string) __('operator_console.product_master.notifications.rejected'));

    // Stays in reviewed — a rejection is a reviewed → reviewed decision (§ 4.3); there is no revert to draft.
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // One rejection audit row carrying the decision + notes + the acting operator principal.
    $rejection = AuditRecord::query()->where('action', 'catalog.product_master.rejected')->sole();
    $after = $rejection->after ?? []; // narrow the nullable jsonb; keys asserted order-independently (PG reorders)

    expect($rejection->entity_type)->toBe('ProductMaster')
        ->and($rejection->entity_id)->toBe((string) $master->id)
        ->and($rejection->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($rejection->actor_id)->toEqual($operator->id)
        ->and($rejection->before)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($after['lifecycle_state'] ?? null)->toBe('reviewed')
        ->and($after['decision'] ?? null)->toBe('rejected')
        ->and($after['notes'] ?? null)->toBe('Label artwork is missing the vintage.');

    // The rejection records no activation event (and the submit before it none either).
    expect(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('surfaces an illegal from-state transition as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft Master: submit would be valid, but REJECT requires `reviewed` — the domain rejects the
    // out-of-state call. The console surfaces it as a danger notification; it does not pre-check the
    // from-state (design L5 — surface, don't reimplement).
    $master = lifecycleConsoleDraftMaster();

    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('reject', ['notes' => 'n/a'])
        ->assertNotified((string) __('operator_console.product_master.notifications.action_failed'));

    // Unchanged: still draft, and the rejected attempt wrote NO audit row (its transaction rolled back).
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('exposes the submit and reject lifecycle actions on the Product Master view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = lifecycleConsoleDraftMaster();

    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->assertActionExists('submit')
        ->assertActionExists('reject');
});
