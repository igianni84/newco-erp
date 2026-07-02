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
use App\Modules\Catalog\Actions\RejectProductMasterReview;
use App\Modules\Catalog\Actions\RetireProductMaster;
use App\Modules\Catalog\Actions\RetireProductMasterCascade;
use App\Modules\Catalog\Actions\SubmitCaseConfigurationForReview;
use App\Modules\Catalog\Actions\SubmitFormatForReview;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Actions\SubmitProductReferenceForReview;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Actions\SubmitSellableSkuForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\Module;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\ViewProductMaster;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
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

/*
|--------------------------------------------------------------------------
| Task 4.2 — Activate + the "second actor required" affordance + Producer-gate surfacing
|--------------------------------------------------------------------------
|
| The console's activate action (design L5/L6; spec — Operator advances a Product Master…, The console surfaces
| the Producer-activation gate). The console SURFACES the domain's two activation guards — the Creator →
| Reviewer → Approver separation-of-duties floor (ApprovalGovernance) and the Producer activation gate — and
| never re-checks either. A confirmation affordance reminds the operator a distinct approver is required; a
| governance or gate rejection (both extend RuntimeException, surfaced by the shared surfaceLifecycleOutcome
| helper) renders as a danger notification, leaving the Master unchanged.
|
| The success path uses THREE distinct operators (creator → reviewer → approver) against the production-default
| role_count 3, mirroring tests/Feature/Modules/Catalog/ProductMasterLifecycleTest.php — no config override. The
| producer-state projection the gate reads is seeded through the real consumer (a ProducerActivated event fanned
| out to the ProducerLifecycleProjector), the faithful Module-K-emits shape.
*/

/**
 * Project a producer state into Catalog's read model exactly as Module K's emit would: record a supply-side
 * ProducerActivated/ProducerRetired (module `parties`, entity_type `Producer`, payload {producer_id, status})
 * inside a real DB::transaction, so the inline post-commit hook fans it out to the ProducerLifecycleProjector,
 * which upserts catalog_producer_states — the projection the Producer activation gate reads. Distinctly named
 * (the `Console` infix) to avoid colliding with the Catalog lifecycle test's global lifecycleProjectProducer
 * (one shared Pest namespace).
 */
function lifecycleConsoleProjectProducer(string $name, int $producerId, string $status): void
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

it('exposes an activate action carrying the localized "second actor required" affordance', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $master = lifecycleConsoleDraftMaster();

    // The activate action exists and SURFACES the separation-of-duties floor as a confirmation affordance
    // (design L5/L6): the console reminds the operator a distinct approver is required, it never re-checks it.
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->assertActionExists('activate', fn (Action $action): bool => $action->isConfirmationRequired()
            && $action->getModalDescription() === (string) __('operator_console.product_master.affordance.second_actor'));
});

it('localizes the second-actor affordance copy in EN and IT', function () {
    $key = 'operator_console.product_master.affordance.second_actor';

    app()->setLocale('en');
    $en = (string) __($key);
    app()->setLocale('it');
    $it = (string) __($key);

    // Both locales resolve the key (not the raw key) and the IT copy is a genuine translation, not the EN value
    // verbatim — the affordance is "present + localized" (task 4.2 acceptance; invariant 12).
    expect($en)->not->toBe($key)
        ->and($it)->not->toBe($key)
        ->and($it)->not->toBe($en);
});

it('activates a reviewed Master through the console when a distinct approver acts and the producer is active', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // Producer 7 active in Catalog's projection (the gate opens); three DISTINCT operators satisfy the
    // default role_count-3 Creator → Reviewer → Approver floor.
    lifecycleConsoleProjectProducer('ProducerActivated', 7, 'active');

    actingAs($creator, 'operator');
    $master = lifecycleConsoleDraftMaster(producerId: 7);

    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    // The distinct approver activates THROUGH THE CONSOLE → reviewed → active, success notification.
    actingAs($approver, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_master.notifications.activated'));

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Exactly one ProductMasterActivated carrying the operator envelope — actor_role newco_ops + the APPROVER
    // (not the creator/reviewer) as actor_id (spec: an operator-driven write records newco_ops + the operator id).
    $event = DomainEvent::query()->where('name', 'ProductMasterActivated')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('ProductMaster')
        ->and($event->entity_id)->toBe((string) $master->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id);
});

it('surfaces a self-approval governance rejection as a danger notification, leaving the Master reviewed', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    // Producer active so the ONLY thing that can reject is the SoD floor — isolating the self-approval path.
    lifecycleConsoleProjectProducer('ProducerActivated', 7, 'active');

    actingAs($creator, 'operator');
    $master = lifecycleConsoleDraftMaster(producerId: 7);

    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    // The reviewer (who performed the prior governance step) attempts the approval — the domain rejects the
    // self-approval; the console SURFACES it as a danger notification and never re-checks the floor (design L5).
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_master.notifications.action_failed'));

    // Unchanged — still reviewed, NO activation event, NO activation audit row (the action's txn rolled back).
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.activated')->count())->toBe(0);
});

it('surfaces the Producer-activation gate block as a danger notification, leaving the Master reviewed', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // Producer 9 is NEVER projected active (no row) — the gate rejects. The three actors are distinct, so the
    // approval governance passes and the GATE is the sole rejection (proving the console surfaces the gate).
    actingAs($creator, 'operator');
    $master = lifecycleConsoleDraftMaster(producerId: 9);

    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    actingAs($approver, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_master.notifications.action_failed'));

    // Stays reviewed, no activation event — the gate blocked the transition (AC-0-FSM-12).
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Task 5.1 — Retire + Reopen (the retire / reopen half of the lifecycle)
|--------------------------------------------------------------------------
|
| The console's retire + reopen header actions (design L7; spec — Operator retires, cascade-retires, and reopens
| a Product Master). Both route through a Catalog domain action via the shared surfaceLifecycleOutcome helper and
| never write lifecycle_state themselves (the no-Eloquent-write rule, task 1.2):
|   - Retire (`active → retired`) is SINGLE-ENTITY — a hierarchy parent carries no reference-integrity guard, so
|     it PRESERVES existing active children (§ 4.5); it records one ProductMasterRetired. The operator-driven
|     cascade is a distinct action (task 5.2).
|   - Reopen (`retired → reviewed`) is AUDIT-ONLY (no domain event); it returns the Master to the activatable
|     `reviewed` state, where the next Activate RE-RUNS the Producer gate.
| Retire/reopen carry only the operator-principal floor (no distinct-actor SoD — that is the activation step's
| floor), so any authenticated operator may perform them; the domain rejects only an out-of-state call, surfaced
| as a danger notification. The success paths drive the Master to `active` through the real domain actions with
| three distinct operators + an active producer (the role_count-3 shape, as in task 4.2).
*/

/**
 * Drive a freshly-created draft Master to `active` through the real Catalog domain actions, faithfully: a
 * distinct creator → reviewer → approver lineage (the production-default role_count 3, no config override) over
 * an `active` producer projection. Returns the active Master; the three operators are the caller's to reuse.
 * Distinctly named (the `Console`/`ActiveMaster` infix) to avoid colliding with the Catalog lifecycle test's
 * helpers (one shared Pest function namespace).
 */
function lifecycleConsoleActiveMaster(Operator $creator, Operator $reviewer, Operator $approver, int $producerId = 7): ProductMaster
{
    lifecycleConsoleProjectProducer('ProducerActivated', $producerId, 'active');

    actingAs($creator, 'operator');
    $master = lifecycleConsoleDraftMaster(producerId: $producerId);

    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    actingAs($approver, 'operator');
    app(ActivateProductMaster::class)->handle($master);

    return $master;
}

it('retires an active Master single-entity through the console, recording one ProductMasterRetired and preserving an active child', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $master = lifecycleConsoleActiveMaster($creator, $reviewer, $approver);
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Seed an ACTIVE child Variant under the now-active Master through the real Catalog actions (the same three
    // operators carry the Variant's OWN approval lineage; its activation re-confirms the parent is active via the
    // activation-cascade gate). The {Models, Actions} carve-out governs OperatorPanel PRODUCTION code, not tests,
    // so the Catalog actions/models are imported freely here.
    actingAs($creator, 'operator');
    $variant = app(CreateProductVariant::class)->handle(productMasterId: $master->id, variantIdentifier: '2019');
    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);
    actingAs($approver, 'operator');
    app(ActivateProductVariant::class)->handle($variant);
    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Retire the Master single-entity THROUGH THE CONSOLE. Retire carries only the operator floor, so any
    // authenticated operator may perform it; here the approver does.
    actingAs($approver, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.product_master.notifications.retired'));

    // The Master is retired and the existing active child is PRESERVED — single-entity retire never cascades
    // (§ 4.5 / BR-Lifecycle-4); only NEW activation under the now-retired Master would be blocked.
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Exactly one ProductMasterRetired carrying the operator envelope — actor_role newco_ops + the retiring
    // operator as actor_id (spec: an operator-driven write records newco_ops + the operator id).
    $event = DomainEvent::query()->where('name', 'ProductMasterRetired')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('ProductMaster')
        ->and($event->entity_id)->toBe((string) $master->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id);
});

it('reopens a retired Master to reviewed through the console (audit-only, no event) and re-checks the producer gate on the next activation', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $master = lifecycleConsoleActiveMaster($creator, $reviewer, $approver);

    // Retire it (carries only the operator floor) so there is a `retired` Master to reopen.
    actingAs($approver, 'operator');
    app(RetireProductMaster::class)->handle($master);
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    $eventsBeforeReopen = DomainEvent::query()->count();

    // Reopen THROUGH THE CONSOLE → retired → reviewed.
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('reopen')
        ->assertNotified((string) __('operator_console.product_master.notifications.reopened'));

    // Back to `reviewed`, AUDIT-ONLY: reopen recorded NO new domain event (the event total is unchanged).
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->count())->toBe($eventsBeforeReopen);

    // One reopen audit row carrying the operator envelope + the lifecycle edge.
    $reopen = AuditRecord::query()->where('action', 'catalog.product_master.reopened')->sole();
    expect($reopen->entity_type)->toBe('ProductMaster')
        ->and($reopen->entity_id)->toBe((string) $master->id)
        ->and($reopen->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($reopen->actor_id)->toEqual($approver->id)
        ->and($reopen->before)->toBe(['lifecycle_state' => 'retired'])
        ->and($reopen->after)->toBe(['lifecycle_state' => 'reviewed']);

    // The Producer gate is RE-CHECKED on the next activation (design L7): flip the producer to retired and the
    // subsequent activate is gate-blocked — proving reopen restored an activatable `reviewed` whose gate re-runs
    // fresh (it now BLOCKS where it passed before). The approver is distinct from creator + reviewer, so the SoD
    // floor passes and the GATE is the sole rejection.
    lifecycleConsoleProjectProducer('ProducerRetired', 7, 'retired');

    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_master.notifications.action_failed'));

    // Stays reviewed; still exactly the one original ProductMasterActivated (the blocked re-activation added none).
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(1);
});

it('surfaces an out-of-state retire as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft Master: retire requires `active`, so the domain rejects the out-of-state call. The console
    // surfaces it as a danger notification; it never pre-checks the from-state (design L5 — surface, don't
    // reimplement).
    $master = lifecycleConsoleDraftMaster();

    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.product_master.notifications.action_failed'));

    // Unchanged: still draft, and no ProductMasterRetired recorded (the rejected attempt's transaction rolled back).
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'ProductMasterRetired')->count())->toBe(0);
});

it('exposes the retire and reopen lifecycle actions on the Product Master view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = lifecycleConsoleDraftMaster();

    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->assertActionExists('retire')
        ->assertActionExists('reopen');
});

/*
|--------------------------------------------------------------------------
| Task 5.2 — Cascade-retire (the operator-driven multi-entity retirement)
|--------------------------------------------------------------------------
|
| The console's cascade-retire header action (design L7; § 4.7; spec — Operator retires, cascade-retires, and
| reopens a Product Master). It is a DISTINCT affordance from the single-entity retire (task 5.1): it routes to
| RetireProductMasterCascade, which retires the Master AND its active descendants (Variants → Product References
| → SKUs) parent-before-child in ONE atomic transaction, recording each entity's `*Retired`. Because the
| operation reaches beyond the viewed Master, the action carries a confirmation modal WARNING that active
| descendants are retired too (the same confirmation-affordance shape as activate's second-actor reminder,
| task 4.2). The console only triggers the domain action via the shared surfaceLifecycleOutcome helper and never
| writes lifecycle_state itself (the no-Eloquent-write rule, task 1.2); an out-of-state cascade (Master not
| `active`) is rejected by the domain and surfaced as a danger notification.
|
| Cascade carries only the operator-principal floor (no distinct-actor SoD — that is the activation step's
| floor), so any authenticated operator may trigger it. The active descendant tree is seeded through the REAL
| Catalog create + submit + activate domain actions (task 5.2 acceptance), reusing the single creator → reviewer
| → approver lineage at every level — the SoD floor is per-entity (it reads each entity's OWN *Created event +
| submit audit), so three mutually-distinct operators satisfy it everywhere (proven for Master + Variant by the
| 5.1 retire test). The exhaustive domain proof of ordering/atomicity/non-active-skip lives in
| tests/Feature/Modules/Catalog/RetirementCascadeTest.php + CatalogLifecycleChainTest.php; THESE tests pin the
| console wiring.
*/

/**
 * Extend lifecycleConsoleActiveMaster() into a fully-`active` Module-0 ownership tree —
 * Master → Variant → (Format) → Product Reference → (Case Configuration) → Sellable SKU — built ENTIRELY
 * through the real Catalog create + submit + activate domain actions (task 5.2 acceptance: "seeded via the
 * Catalog create+activate actions"). The same creator → reviewer → approver lineage drives every entity (the
 * separation-of-duties floor is per-entity, so three mutually-distinct operators satisfy it at each level — no
 * fresh operators per level). Format + Case Configuration are STANDALONE reference entities (no parent gate);
 * the cascade does NOT descend into them (§ 4.7) — they exist only to satisfy the Product Reference / Sellable
 * SKU activation gates. Distinctly named (the `Tree` infix) so the one shared Pest namespace carries no
 * redeclare. Returns the whole tree for the caller's assertions.
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
function lifecycleConsoleActiveTree(Operator $creator, Operator $reviewer, Operator $approver, int $producerId = 7): array
{
    $master = lifecycleConsoleActiveMaster($creator, $reviewer, $approver, $producerId);

    // Variant — gated on the parent Master being active (the within-module activation cascade).
    actingAs($creator, 'operator');
    $variant = app(CreateProductVariant::class)->handle(productMasterId: $master->id, variantIdentifier: '2019');
    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);
    actingAs($approver, 'operator');
    app(ActivateProductVariant::class)->handle($variant);

    // Format — standalone (approval governance only, no parent gate).
    actingAs($creator, 'operator');
    $format = app(CreateFormat::class)->handle(name: 'Magnum', sizeLabel: '1.5L', volumeMl: 1500);
    actingAs($reviewer, 'operator');
    app(SubmitFormatForReview::class)->handle($format);
    actingAs($approver, 'operator');
    app(ActivateFormat::class)->handle($format);

    // Product Reference — gated on BOTH the Variant and the Format being active.
    actingAs($creator, 'operator');
    $reference = app(CreateProductReference::class)->handle(productVariantId: $variant->id, formatId: $format->id);
    actingAs($reviewer, 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);
    actingAs($approver, 'operator');
    app(ActivateProductReference::class)->handle($reference);

    // Case Configuration — standalone.
    actingAs($creator, 'operator');
    $caseConfiguration = app(CreateCaseConfiguration::class)->handle(name: 'Original Wooden Case (6)', unitsPerCase: 6, packagingType: 'owc');
    actingAs($reviewer, 'operator');
    app(SubmitCaseConfigurationForReview::class)->handle($caseConfiguration);
    actingAs($approver, 'operator');
    app(ActivateCaseConfiguration::class)->handle($caseConfiguration);

    // Sellable SKU — the leaf; gated on BOTH the Product Reference and the Case Configuration being active.
    actingAs($creator, 'operator');
    $sku = app(CreateSellableSku::class)->handle(
        productReferenceId: $reference->id,
        caseConfigurationId: $caseConfiguration->id,
        commercialName: 'Château Console 2019 — Magnum (OWC 6)',
    );
    actingAs($reviewer, 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);
    actingAs($approver, 'operator');
    app(ActivateSellableSku::class)->handle($sku);

    return [
        'master' => $master,
        'variant' => $variant,
        'format' => $format,
        'reference' => $reference,
        'caseConfiguration' => $caseConfiguration,
        'sku' => $sku,
    ];
}

it('exposes a cascade-retire action carrying the localized "retires active descendants" warning affordance', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $master = lifecycleConsoleDraftMaster();

    // The cascade-retire action is a DISTINCT affordance from the single-entity retire (design L7): it carries a
    // confirmation modal whose description WARNS that active descendants are retired too — surfaced BEFORE the
    // operator commits. Asserted without HTML scraping via the resolved unmounted action (the task-4.2 shape).
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->assertActionExists('retireCascade', fn (Action $action): bool => $action->isConfirmationRequired()
            && $action->getModalDescription() === (string) __('operator_console.product_master.affordance.cascade_warning'));
});

it('localizes the cascade-retire warning copy in EN and IT', function () {
    $key = 'operator_console.product_master.affordance.cascade_warning';

    app()->setLocale('en');
    $en = (string) __($key);
    app()->setLocale('it');
    $it = (string) __($key);

    // Both locales resolve the key (not the raw key) and the IT copy is a genuine translation, not the EN value
    // verbatim — the warning is "present + localized" (task 5.2; invariant 12).
    expect($en)->not->toBe($key)
        ->and($it)->not->toBe($key)
        ->and($it)->not->toBe($en);
});

it('cascade-retires an active Master and its active descendants parent-before-child through the console', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $tree = lifecycleConsoleActiveTree($creator, $reviewer, $approver);

    // The whole ownership tree is active before the cascade (seeded through the real create+activate actions).
    expect(ProductMaster::findOrFail($tree['master']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductVariant::findOrFail($tree['variant']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(ProductReference::findOrFail($tree['reference']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(SellableSku::findOrFail($tree['sku']->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Cascade-retire THROUGH THE CONSOLE. Cascade carries only the operator-principal floor (no distinct-actor
    // SoD), so any authenticated operator may trigger it; here the approver does.
    actingAs($approver, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $tree['master']->getKey()])
        ->callAction('retireCascade')
        ->assertNotified((string) __('operator_console.product_master.notifications.cascade_retired'));

    // The whole OWNERSHIP tree (Master → Variant → PR → SKU) reached `retired`.
    expect(ProductMaster::findOrFail($tree['master']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductVariant::findOrFail($tree['variant']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductReference::findOrFail($tree['reference']->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(SellableSku::findOrFail($tree['sku']->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    // The STANDALONE reference entities are NOT descended into by the cascade (§ 4.7) — they stay active.
    expect(Format::findOrFail($tree['format']->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(CaseConfiguration::findOrFail($tree['caseConfiguration']->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Each entity recorded its `*Retired` in ascending domain_events.id = parent-before-child order (§ 14.3):
    // the cascade records them Master → Variant → PR → SKU explicitly inside ONE transaction.
    $order = DomainEvent::query()
        ->whereIn('name', ['ProductMasterRetired', 'ProductVariantRetired', 'ProductReferenceRetired', 'SellableSKURetired'])
        ->orderBy('id')
        ->pluck('name')
        ->all();

    expect($order)->toBe(['ProductMasterRetired', 'ProductVariantRetired', 'ProductReferenceRetired', 'SellableSKURetired']);

    // Every cascade write carries the operator envelope — actor_role newco_ops + the cascading operator (the
    // approver) as actor_id (spec: an operator-driven write records newco_ops + the operator id).
    $retiredEvents = DomainEvent::query()
        ->whereIn('name', ['ProductMasterRetired', 'ProductVariantRetired', 'ProductReferenceRetired', 'SellableSKURetired'])
        ->get();

    expect($retiredEvents)->toHaveCount(4);
    foreach ($retiredEvents as $event) {
        expect($event->actor_role)->toBe(ActorRole::NewcoOps)
            ->and($event->actor_id)->toEqual($approver->id);
    }
});

it('surfaces an out-of-state cascade retire as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft Master: cascade retire requires the Master to be `active`, so the domain rejects the out-of-state
    // call (the Master's from-state guard). The console surfaces it as a danger notification; it never pre-checks
    // the from-state (design L5 — surface, don't reimplement).
    $master = lifecycleConsoleDraftMaster();

    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('retireCascade')
        ->assertNotified((string) __('operator_console.product_master.notifications.action_failed'));

    // Unchanged: still draft, and no *Retired recorded (the rejected attempt's transaction rolled back).
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Task 4.1 (catalog-review-freshness-resubmit) — the re-submit header action + the block it surfaces
|--------------------------------------------------------------------------
|
| The review-freshness re-arm on the console (RM-06 / canon MVP-DEC-019; design D2/D5). The console gains a
| `re-submit` header action wired through the shared kit's lifecycleAction factory to
| ResubmitProductMasterForReview (never an Eloquent write). Its ->visible() is gated to the DERIVED
| rejection-pending read (OperatorConsoleViewRecord::isRejectionPending) — OFFERED only while an un-remediated
| rejection blocks activation, HIDDEN otherwise. The block-gate itself needs no console code: an activation
| attempt on a rejection-pending Master throws ApprovalGovernanceViolation, which the kit's
| surfaceLifecycleOutcome renders as an action_failed danger notification for free (design D5). A hidden
| ->visible()-false action is undrivable via test helpers, so re-submit's gating is proven with
| assertActionHidden/assertActionVisible and its re-arm is driven while it IS visible (lessons.md 2026-06-23/24).
*/

it('offers re-submit only when the Master is rejection-pending — hidden on a fresh reviewed Master, visible after a rejection', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $master = lifecycleConsoleDraftMaster();
    app(SubmitProductMasterForReview::class)->handle($master);

    // Fresh `reviewed` (never rejected): the derived rejection-pending read is false, so a redundant re-submit
    // is NOT offered — the action is HIDDEN (design D5; isRejectionPending).
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->assertActionHidden('resubmit');

    // A rejection makes the Master rejection-pending (its latest governance action ends in `.rejected`); on a
    // fresh mount re-submit is now VISIBLE.
    app(RejectProductMasterReview::class)->handle($master, 'Label artwork is missing the vintage.');

    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->assertActionVisible('resubmit');
});

it('surfaces the block on a rejection-pending Master, then re-arms it via console re-submit so a distinct approver activates', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // Producer 7 active in Catalog's projection (the activation gate opens); three DISTINCT operators satisfy
    // the default role_count-3 Creator → Reviewer → Approver floor across the whole flow.
    lifecycleConsoleProjectProducer('ProducerActivated', 7, 'active');

    actingAs($creator, 'operator');
    $master = lifecycleConsoleDraftMaster(producerId: 7);

    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);
    app(RejectProductMasterReview::class)->handle($master, 'A corrected appellation is required.');

    // (block) A distinct approver attempts to activate the rejection-pending Master THROUGH THE CONSOLE — the
    // review-freshness block-gate (ApprovalGovernanceViolation) surfaces as a danger notification, nothing
    // changes. Activate is NOT visibility-gated, so it is drivable; the domain floors it (design D5/L4 —
    // surface, don't reimplement). The three actors are distinct, so this is the block, not an SoD failure.
    actingAs($approver, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_master.notifications.action_failed'));

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0);

    // (re-arm) The Creator re-submits THROUGH THE CONSOLE → reviewed → reviewed, audit-only, no event; the one
    // resubmitted row carries the creator principal and the `.resubmitted` action becomes the freshest.
    actingAs($creator, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('resubmit')
        ->assertNotified((string) __('operator_console.product_master.notifications.resubmitted'));

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    $resubmit = AuditRecord::query()->where('action', 'catalog.product_master.resubmitted')->sole();
    expect($resubmit->entity_type)->toBe('ProductMaster')
        ->and($resubmit->entity_id)->toBe((string) $master->id)
        ->and($resubmit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($resubmit->actor_id)->toEqual($creator->id)
        ->and(DomainEvent::query()->where('name', 'like', '%Resubmitted%')->count())->toBe(0);

    // (re-armed) The SAME distinct approver now activates THROUGH THE CONSOLE → active, exactly one
    // ProductMasterActivated — blocked moments ago, it succeeds ONLY because the re-submit cleared the gate.
    actingAs($approver, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_master.notifications.activated'));

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(1);
});
