<?php

// Task 2.2 (operator-console-catalog-spine; design L1/L3/L4; ADR 2026-06-19 + 2026-06-20; spec — Operator
// creates / advances / retires & reopens the standalone Case Configuration through the console, with the retire
// reference-integrity block surfaced). These pin the Case Configuration console's write-through surface, all
// built as PURE reuse of the kit (tasks 1.1/1.2): the create page routes the form into CreateCaseConfiguration,
// and the view page's five uniform lifecycle actions (submit · reject · activate · retire · reopen) each route
// to the matching Catalog domain action through the shared surfaceLifecycleOutcome helper. The console NEVER
// writes lifecycle_state itself (the no-Eloquent-write rule, task 1.2) and SURFACES the domain's decision — the
// from-state guard, the Creator → Reviewer → Approver separation-of-duties floor, AND the retire
// reference-integrity block — it reimplements none of them (design L4). A Case Configuration is STANDALONE: no
// parent gate, no producer gate, and NO cascade-retire affordance (Master-only, scope guard). Submit/reject/
// reopen are event-silent audit checkpoints (Module 0 PRD § 14.2); activate/retire record
// CaseConfigurationActivated/Retired; create records CaseConfigurationCreated.
//
// DatabaseMigrations (mirroring the Master/Format console tests): each console action drives a real domain
// action that opens its OWN DB::transaction, so the recorders' transaction-level guards see a real commit
// (level 0 → 1 → 0) — the faithful production shape (RefreshDatabase would wrap every write in a
// never-committed outer transaction). Catalog enums/models/actions are imported freely here: the
// {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\Catalog\Actions\ActivateCaseConfiguration;
use App\Modules\Catalog\Actions\CreateCaseConfiguration;
use App\Modules\Catalog\Actions\RetireCaseConfiguration;
use App\Modules\Catalog\Actions\SubmitCaseConfigurationForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CaseConfigurationResource\Pages\CreateCaseConfiguration as CreateCaseConfigurationPage;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CaseConfigurationResource\Pages\ViewCaseConfiguration;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

/**
 * A draft Case Configuration created through the real Catalog action as the currently-acting operator (records
 * CaseConfigurationCreated, no audit row). Distinctly named (`caseConfigConsole` prefix) to avoid colliding with
 * the Catalog lifecycle test's `lifecycleCreateDraftCaseConfiguration` (one shared Pest function namespace).
 */
function caseConfigConsoleDraft(string $name = 'Console OWC Six', int $unitsPerCase = 6, string $packagingType = 'owc'): CaseConfiguration
{
    return app(CreateCaseConfiguration::class)->handle(name: $name, unitsPerCase: $unitsPerCase, packagingType: $packagingType);
}

/**
 * Stand a Case Configuration up in `active` through the real domain chain with three DISTINCT operators (the
 * default Creator → Reviewer → Approver floor — a standalone entity activates with no parent gate). Leaves the
 * `approver` as the acting operator. Used by the retire / reference-integrity tests, which start from `active`.
 */
function caseConfigConsoleActive(Operator $creator, Operator $reviewer, Operator $approver, string $name = 'Active OWC Six'): CaseConfiguration
{
    actingAs($creator, 'operator');
    $caseConfiguration = caseConfigConsoleDraft($name);
    actingAs($reviewer, 'operator');
    app(SubmitCaseConfigurationForReview::class)->handle($caseConfiguration);
    actingAs($approver, 'operator');
    app(ActivateCaseConfiguration::class)->handle($caseConfiguration);

    return $caseConfiguration->refresh();
}

it('creates a draft Case Configuration through the console, recording one CaseConfigurationCreated with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    Livewire::test(CreateCaseConfigurationPage::class)
        ->fillForm([
            'name' => 'Console OWC Six',
            'units_per_case' => 6,
            'packaging_type' => 'owc',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // The write routed through the action: a draft Case Configuration with its scalar attributes.
    $caseConfiguration = CaseConfiguration::query()->where('name', 'Console OWC Six')->sole();

    expect($caseConfiguration->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($caseConfiguration->units_per_case)->toBe(6)
        ->and($caseConfiguration->packaging_type)->toBe('owc');

    // Exactly one CaseConfigurationCreated, carrying the operator audit envelope (newco_ops + the operator id)
    // resolved by the action from the `operator` guard — the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', 'CaseConfigurationCreated')->sole();

    expect($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id)
        ->and($event->entity_type)->toBe('CaseConfiguration')
        ->and($event->entity_id)->toBe((string) $caseConfiguration->id);
});

it('submits a draft Case Configuration for review through the console, recording the submit audit row and no domain event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $caseConfiguration = caseConfigConsoleDraft();
    expect($caseConfiguration->lifecycle_state)->toBe(LifecycleState::Draft);

    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->callAction('submit')
        ->assertNotified((string) __('operator_console.case_configuration.notifications.submitted'));

    // State advanced draft → reviewed via the domain action (the console never writes lifecycle_state).
    expect(CaseConfiguration::findOrFail($caseConfiguration->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // Exactly one submit audit row carrying the operator envelope + the lifecycle edge; submit is audit-only.
    $audit = AuditRecord::query()->where('action', 'catalog.case_configuration.submitted')->sole();

    expect($audit->entity_type)->toBe('CaseConfiguration')
        ->and($audit->entity_id)->toBe((string) $caseConfiguration->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($operator->id)
        ->and($audit->before)->toBe(['lifecycle_state' => 'draft'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed']);

    // Event-silent: the only Case Configuration event remains the creation's (no *Activated, no *Reviewed).
    expect(DomainEvent::query()->where('name', 'CaseConfigurationActivated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('entity_type', 'CaseConfiguration')->count())->toBe(1);
});

it('records a console rejection with notes, keeping the Case Configuration in reviewed and emitting no event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $caseConfiguration = caseConfigConsoleDraft();
    app(SubmitCaseConfigurationForReview::class)->handle($caseConfiguration);

    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->callAction('reject', ['notes' => 'Units per case do not match the packaging type.'])
        ->assertNotified((string) __('operator_console.case_configuration.notifications.rejected'));

    // Stays reviewed — a rejection is a reviewed → reviewed decision (§ 4.3); there is no revert to draft.
    expect(CaseConfiguration::findOrFail($caseConfiguration->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // One rejection audit row carrying the decision + notes + the acting operator principal.
    $rejection = AuditRecord::query()->where('action', 'catalog.case_configuration.rejected')->sole();
    $after = $rejection->after ?? []; // narrow the nullable jsonb; keys asserted order-independently (PG reorders)

    expect($rejection->entity_type)->toBe('CaseConfiguration')
        ->and($rejection->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($rejection->actor_id)->toEqual($operator->id)
        ->and($after['lifecycle_state'] ?? null)->toBe('reviewed')
        ->and($after['decision'] ?? null)->toBe('rejected')
        ->and($after['notes'] ?? null)->toBe('Units per case do not match the packaging type.');

    expect(DomainEvent::query()->where('name', 'CaseConfigurationActivated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('surfaces an illegal from-state transition as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft Case Configuration: submit would be valid, but REJECT requires `reviewed` — the domain rejects the
    // out-of-state call. The console surfaces it as a danger notification; it does not pre-check the from-state
    // (design L4 — surface, don't reimplement).
    $caseConfiguration = caseConfigConsoleDraft();

    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->callAction('reject', ['notes' => 'n/a'])
        ->assertNotified((string) __('operator_console.case_configuration.notifications.action_failed'));

    // Unchanged: still draft, and the rejected attempt wrote NO audit row (its transaction rolled back).
    expect(CaseConfiguration::findOrFail($caseConfiguration->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('exposes an activate action carrying the localized "second actor required" affordance and no cascade-retire action', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $caseConfiguration = caseConfigConsoleDraft();

    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        // The five uniform lifecycle actions are present …
        ->assertActionExists('submit')
        ->assertActionExists('reject')
        ->assertActionExists('retire')
        ->assertActionExists('reopen')
        // … activate SURFACES the separation-of-duties floor as a confirmation affordance (design L4): a
        // distinct approver is required — the console reminds, it never re-checks.
        ->assertActionExists('activate', fn (Action $action): bool => $action->isConfirmationRequired()
            && $action->getModalDescription() === (string) __('operator_console.case_configuration.affordance.second_actor'))
        // … and NO cascade-retire affordance exists for a spine entity (Master-only, scope guard).
        ->assertActionDoesNotExist('retireCascade');
});

it('activates a reviewed Case Configuration through the console with no parent gate when a distinct approver acts', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // A standalone Case Configuration has no parent gate; three DISTINCT operators satisfy the default
    // role_count-3 Creator → Reviewer → Approver floor — the SOLE activation precondition (spec: "A standalone
    // reference entity activates with no parent gate").
    actingAs($creator, 'operator');
    $caseConfiguration = caseConfigConsoleDraft();

    actingAs($reviewer, 'operator');
    app(SubmitCaseConfigurationForReview::class)->handle($caseConfiguration);

    actingAs($approver, 'operator');
    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.case_configuration.notifications.activated'));

    expect(CaseConfiguration::findOrFail($caseConfiguration->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Exactly one CaseConfigurationActivated carrying the operator envelope — actor_role newco_ops + the
    // APPROVER (not the creator/reviewer) as actor_id.
    $event = DomainEvent::query()->where('name', 'CaseConfigurationActivated')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('CaseConfiguration')
        ->and($event->entity_id)->toBe((string) $caseConfiguration->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id);
});

it('surfaces a self-approval governance rejection as a danger notification, leaving the Case Configuration reviewed', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    actingAs($creator, 'operator');
    $caseConfiguration = caseConfigConsoleDraft();

    actingAs($reviewer, 'operator');
    app(SubmitCaseConfigurationForReview::class)->handle($caseConfiguration);

    // The reviewer (who performed the prior governance step) attempts the approval — the domain rejects the
    // self-approval; the console SURFACES it as a danger notification and never re-checks the floor (design L4).
    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.case_configuration.notifications.action_failed'));

    // Unchanged — still reviewed, NO activation event, NO activation audit row (the action's txn rolled back).
    expect(CaseConfiguration::findOrFail($caseConfiguration->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'CaseConfigurationActivated')->count())->toBe(0)
        ->and(AuditRecord::query()->where('action', 'catalog.case_configuration.activated')->count())->toBe(0);
});

it('retires an active Case Configuration through the console, recording one CaseConfigurationRetired with the operator envelope', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $caseConfiguration = caseConfigConsoleActive($creator, $reviewer, $approver);
    expect($caseConfiguration->lifecycle_state)->toBe(LifecycleState::Active);

    // Retire THROUGH THE CONSOLE. Retire carries only the operator floor, so any authenticated operator may
    // perform it; here the approver does. No active SKU references this Case Configuration, so the
    // reference-integrity gate clears.
    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.case_configuration.notifications.retired'));

    expect(CaseConfiguration::findOrFail($caseConfiguration->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    $event = DomainEvent::query()->where('name', 'CaseConfigurationRetired')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('CaseConfiguration')
        ->and($event->entity_id)->toBe((string) $caseConfiguration->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id);
});

it('reopens a retired Case Configuration to reviewed through the console (audit-only, no event)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $caseConfiguration = caseConfigConsoleActive($creator, $reviewer, $approver);
    app(RetireCaseConfiguration::class)->handle($caseConfiguration);
    expect(CaseConfiguration::findOrFail($caseConfiguration->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    $eventsBeforeReopen = DomainEvent::query()->count();

    // Reopen THROUGH THE CONSOLE → retired → reviewed.
    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->callAction('reopen')
        ->assertNotified((string) __('operator_console.case_configuration.notifications.reopened'));

    // Back to `reviewed`, AUDIT-ONLY: reopen recorded NO new domain event (the event total is unchanged).
    expect(CaseConfiguration::findOrFail($caseConfiguration->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->count())->toBe($eventsBeforeReopen);

    // One reopen audit row carrying the operator envelope + the lifecycle edge.
    $reopen = AuditRecord::query()->where('action', 'catalog.case_configuration.reopened')->sole();
    expect($reopen->entity_type)->toBe('CaseConfiguration')
        ->and($reopen->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($reopen->actor_id)->toEqual($approver->id)
        ->and($reopen->before)->toBe(['lifecycle_state' => 'retired'])
        ->and($reopen->after)->toBe(['lifecycle_state' => 'reviewed']);
});

it('surfaces an out-of-state retire as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft Case Configuration: retire requires `active`, so the domain rejects the out-of-state call. The
    // console surfaces it as a danger notification; it never pre-checks the from-state (design L4).
    $caseConfiguration = caseConfigConsoleDraft();

    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.case_configuration.notifications.action_failed'));

    // Unchanged: still draft, and no CaseConfigurationRetired recorded (the rejected attempt's txn rolled back).
    expect(CaseConfiguration::findOrFail($caseConfiguration->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'CaseConfigurationRetired')->count())->toBe(0);
});

// ---------------------------------------------------------------------------------------------------------
// Reference integrity (product-catalog — Retirement Cascade and Reference Integrity; Module 0 PRD § 4.6,
// BR-Lifecycle-5 — within-catalog subset). A Case Configuration referenced by an `active` Sellable SKU cannot
// be retired out from under it; the console SURFACES the domain's RetirementReferenceIntegrityViolation as a
// danger notification and re-checks nothing (design L4). The blocking active SKU is stood up directly through
// the factory — the gate reads only `lifecycle_state`, so a factory-active SKU is a legitimate fixture, and the
// factory bypasses CreateSellableSku so no SKU creation event muddies the count (proven shape:
// RetirementCascadeTest).
// ---------------------------------------------------------------------------------------------------------

it('surfaces a retire blocked by an active Sellable SKU reference as a danger notification, leaving the Case Configuration active', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $caseConfiguration = caseConfigConsoleActive($creator, $reviewer, $approver);

    // An active Sellable SKU references this Case Configuration (its packaging dimension). The factory bypasses
    // the Create action — no SellableSKUCreated event — and auto-builds the SKU's Product Reference.
    $sku = SellableSku::factory()->create([
        'case_configuration_id' => $caseConfiguration->id,
        'lifecycle_state' => LifecycleState::Active,
    ]);

    $retireEventsBefore = DomainEvent::query()->where('name', 'CaseConfigurationRetired')->count();

    // Retire THROUGH THE CONSOLE → the domain's reference-integrity gate rejects it; the console surfaces the
    // rejection as a danger notification (it never pre-checks open references — design L4).
    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.case_configuration.notifications.action_failed'));

    // Unchanged: still active (the gate rolled the transition back), and NO CaseConfigurationRetired recorded —
    // the open SKU is still active and unaffected.
    expect(CaseConfiguration::findOrFail($caseConfiguration->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'CaseConfigurationRetired')->count())->toBe($retireEventsBefore);
});

it('retires the Case Configuration once no active Sellable SKU references it (a retired SKU does not block)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $caseConfiguration = caseConfigConsoleActive($creator, $reviewer, $approver);

    // A SKU that already closed (retired) is not an open reference — the reference-integrity gate clears.
    SellableSku::factory()->create([
        'case_configuration_id' => $caseConfiguration->id,
        'lifecycle_state' => LifecycleState::Retired,
    ]);

    // Retire THROUGH THE CONSOLE now succeeds — no active SKU references the Case Configuration.
    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.case_configuration.notifications.retired'));

    expect(CaseConfiguration::findOrFail($caseConfiguration->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(DomainEvent::query()->where('name', 'CaseConfigurationRetired')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Task 4.2 (catalog-review-freshness-resubmit) — the visibility-gated re-submit header action
|--------------------------------------------------------------------------
|
| The review-freshness re-arm on the Case Configuration console (RM-06 / canon MVP-DEC-019; design D5) — the same
| visibility-gated re-submit the Product Master console gained in task 4.1, now on every spine console. Re-submit
| routes through the shared kit's lifecycleAction factory to ResubmitCaseConfigurationForReview (never an Eloquent
| write); its ->visible() is gated to the DERIVED rejection-pending read
| (OperatorConsoleViewRecord::isReviewStale) — OFFERED only while an un-remediated rejection blocks
| activation, HIDDEN otherwise. A ->visible()-false action is undrivable via test helpers, so the gating is proven
| with assertActionHidden/assertActionVisible and the re-arm is driven while re-submit IS visible (lessons.md
| 2026-06-23/24).
*/

it('offers re-submit on the Case Configuration console only when rejection-pending, re-arming review when driven', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $caseConfiguration = caseConfigConsoleDraft();
    app(SubmitCaseConfigurationForReview::class)->handle($caseConfiguration);

    // Fresh `reviewed` (never rejected): the derived rejection-pending read is false, so a redundant re-submit is
    // NOT offered — the action is HIDDEN (design D5; OperatorConsoleViewRecord::isReviewStale).
    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->assertActionHidden('resubmit');

    // A rejection (through the console) makes it rejection-pending — its latest governance action ends in
    // `.rejected` — so on a fresh mount re-submit is VISIBLE.
    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->callAction('reject', ['notes' => 'Packaging type needs confirmation.']);

    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->assertActionVisible('resubmit')
        ->callAction('resubmit')
        ->assertNotified((string) __('operator_console.case_configuration.notifications.resubmitted'));

    // Re-arm is state-preserving (reviewed → reviewed, audit-only) and clears the pending flag, so on a fresh
    // mount re-submit is HIDDEN again (the latest governance action is now `.resubmitted`, not `.rejected`) — the
    // write-through routed to ResubmitCaseConfigurationForReview with the CaseConfiguration label, else the derived
    // read would still see the `.rejected` as latest and keep re-submit visible.
    expect(CaseConfiguration::findOrFail($caseConfiguration->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->assertActionHidden('resubmit');
});
