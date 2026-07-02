<?php

// Task 2.1 (operator-console-catalog-spine; design L1/L3/L4; ADR 2026-06-19 + 2026-06-20; spec — Operator
// creates / advances / retires & reopens the standalone Format through the console). These pin the Format
// console's write-through surface, all built as PURE reuse of the kit (tasks 1.1/1.2): the create page routes
// the form into CreateFormat, and the view page's five uniform lifecycle actions (submit · reject · activate ·
// retire · reopen) each route to the matching Catalog domain action through the shared surfaceLifecycleOutcome
// helper. The console NEVER writes lifecycle_state itself (the no-Eloquent-write rule, task 1.2) and SURFACES
// the domain's decision — the from-state guard and the Creator → Reviewer → Approver separation-of-duties floor
// — it reimplements none of them (design L4). Format is STANDALONE: no parent gate, no producer gate, and NO
// cascade-retire affordance (Master-only, scope guard). Submit/reject/reopen are event-silent audit checkpoints
// (Module 0 PRD § 14.2); activate/retire record FormatActivated/FormatRetired; create records FormatCreated.
//
// DatabaseMigrations (mirroring the Master console tests): each console action drives a real domain action that
// opens its OWN DB::transaction, so the recorders' transaction-level guards see a real commit (level 0 → 1 → 0)
// — the faithful production shape (RefreshDatabase would wrap every write in a never-committed outer
// transaction). Catalog enums/models/actions are imported freely here: the {Models, Actions} import-boundary
// carve-out governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\Catalog\Actions\ActivateFormat;
use App\Modules\Catalog\Actions\CreateFormat;
use App\Modules\Catalog\Actions\RetireFormat;
use App\Modules\Catalog\Actions\SubmitFormatForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\Format;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource\Pages\CreateFormat as CreateFormatPage;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource\Pages\ViewFormat;
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
 * A draft Format created through the real Catalog action as the currently-acting operator (records
 * FormatCreated, no audit row). Distinctly named (`formatConsole` prefix) to avoid colliding with the Catalog
 * lifecycle test's `lifecycleCreateDraftFormat` (one shared Pest function namespace).
 */
function formatConsoleDraft(string $name = 'Console Magnum', string $sizeLabel = '1.5L', int $volumeMl = 1500): Format
{
    return app(CreateFormat::class)->handle(name: $name, sizeLabel: $sizeLabel, volumeMl: $volumeMl);
}

it('creates a draft Format through the console, recording one FormatCreated with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    Livewire::test(CreateFormatPage::class)
        ->fillForm([
            'name' => 'Console Magnum',
            'size_label' => '1.5L',
            'volume_ml' => 1500,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // The write routed through the action: a draft Format with its scalar attributes.
    $format = Format::query()->where('name', 'Console Magnum')->sole();

    expect($format->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($format->size_label)->toBe('1.5L')
        ->and($format->volume_ml)->toBe(1500);

    // Exactly one FormatCreated, carrying the operator audit envelope (newco_ops + the operator id) resolved by
    // the action from the `operator` guard — the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', 'FormatCreated')->sole();

    expect($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id)
        ->and($event->entity_type)->toBe('Format')
        ->and($event->entity_id)->toBe((string) $format->id);
});

it('submits a draft Format for review through the console, recording the submit audit row and no domain event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $format = formatConsoleDraft();
    expect($format->lifecycle_state)->toBe(LifecycleState::Draft);

    Livewire::test(ViewFormat::class, ['record' => $format->getKey()])
        ->callAction('submit')
        ->assertNotified((string) __('operator_console.format.notifications.submitted'));

    // State advanced draft → reviewed via the domain action (the console never writes lifecycle_state).
    expect(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // Exactly one submit audit row carrying the operator envelope + the lifecycle edge; submit is audit-only.
    $audit = AuditRecord::query()->where('action', 'catalog.format.submitted')->sole();

    expect($audit->entity_type)->toBe('Format')
        ->and($audit->entity_id)->toBe((string) $format->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($operator->id)
        ->and($audit->before)->toBe(['lifecycle_state' => 'draft'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed']);

    // Event-silent: the only Format event remains the creation's (no FormatActivated, no *Reviewed).
    expect(DomainEvent::query()->where('name', 'FormatActivated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('entity_type', 'Format')->count())->toBe(1);
});

it('records a console rejection with notes, keeping the Format in reviewed and emitting no event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $format = formatConsoleDraft();
    app(SubmitFormatForReview::class)->handle($format);

    Livewire::test(ViewFormat::class, ['record' => $format->getKey()])
        ->callAction('reject', ['notes' => 'Volume does not match the size label.'])
        ->assertNotified((string) __('operator_console.format.notifications.rejected'));

    // Stays reviewed — a rejection is a reviewed → reviewed decision (§ 4.3); there is no revert to draft.
    expect(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // One rejection audit row carrying the decision + notes + the acting operator principal.
    $rejection = AuditRecord::query()->where('action', 'catalog.format.rejected')->sole();
    $after = $rejection->after ?? []; // narrow the nullable jsonb; keys asserted order-independently (PG reorders)

    expect($rejection->entity_type)->toBe('Format')
        ->and($rejection->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($rejection->actor_id)->toEqual($operator->id)
        ->and($after['lifecycle_state'] ?? null)->toBe('reviewed')
        ->and($after['decision'] ?? null)->toBe('rejected')
        ->and($after['notes'] ?? null)->toBe('Volume does not match the size label.');

    expect(DomainEvent::query()->where('name', 'FormatActivated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('surfaces an illegal from-state transition as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft Format: submit would be valid, but REJECT requires `reviewed` — the domain rejects the
    // out-of-state call. The console surfaces it as a danger notification; it does not pre-check the from-state
    // (design L4 — surface, don't reimplement).
    $format = formatConsoleDraft();

    Livewire::test(ViewFormat::class, ['record' => $format->getKey()])
        ->callAction('reject', ['notes' => 'n/a'])
        ->assertNotified((string) __('operator_console.format.notifications.action_failed'));

    // Unchanged: still draft, and the rejected attempt wrote NO audit row (its transaction rolled back).
    expect(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('exposes an activate action carrying the localized "second actor required" affordance and no cascade-retire action', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $format = formatConsoleDraft();

    Livewire::test(ViewFormat::class, ['record' => $format->getKey()])
        // The five uniform lifecycle actions are present …
        ->assertActionExists('submit')
        ->assertActionExists('reject')
        ->assertActionExists('retire')
        ->assertActionExists('reopen')
        // … activate SURFACES the separation-of-duties floor as a confirmation affordance (design L4): a
        // distinct approver is required — the console reminds, it never re-checks.
        ->assertActionExists('activate', fn (Action $action): bool => $action->isConfirmationRequired()
            && $action->getModalDescription() === (string) __('operator_console.format.affordance.second_actor'))
        // … and NO cascade-retire affordance exists for a spine entity (Master-only, scope guard).
        ->assertActionDoesNotExist('retireCascade');
});

it('activates a reviewed Format through the console with no parent gate when a distinct approver acts', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // A standalone Format has no parent gate; three DISTINCT operators satisfy the default role_count-3
    // Creator → Reviewer → Approver floor — the SOLE activation precondition (spec: "A standalone reference
    // entity activates with no parent gate").
    actingAs($creator, 'operator');
    $format = formatConsoleDraft();

    actingAs($reviewer, 'operator');
    app(SubmitFormatForReview::class)->handle($format);

    actingAs($approver, 'operator');
    Livewire::test(ViewFormat::class, ['record' => $format->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.format.notifications.activated'));

    expect(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Exactly one FormatActivated carrying the operator envelope — actor_role newco_ops + the APPROVER (not the
    // creator/reviewer) as actor_id (spec: an operator-driven write records newco_ops + the operator id).
    $event = DomainEvent::query()->where('name', 'FormatActivated')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('Format')
        ->and($event->entity_id)->toBe((string) $format->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id);
});

it('surfaces a self-approval governance rejection as a danger notification, leaving the Format reviewed', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    actingAs($creator, 'operator');
    $format = formatConsoleDraft();

    actingAs($reviewer, 'operator');
    app(SubmitFormatForReview::class)->handle($format);

    // The reviewer (who performed the prior governance step) attempts the approval — the domain rejects the
    // self-approval; the console SURFACES it as a danger notification and never re-checks the floor (design L4).
    Livewire::test(ViewFormat::class, ['record' => $format->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.format.notifications.action_failed'));

    // Unchanged — still reviewed, NO activation event, NO activation audit row (the action's txn rolled back).
    expect(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'FormatActivated')->count())->toBe(0)
        ->and(AuditRecord::query()->where('action', 'catalog.format.activated')->count())->toBe(0);
});

it('retires an active Format through the console, recording one FormatRetired with the operator envelope', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    actingAs($creator, 'operator');
    $format = formatConsoleDraft();
    actingAs($reviewer, 'operator');
    app(SubmitFormatForReview::class)->handle($format);
    actingAs($approver, 'operator');
    app(ActivateFormat::class)->handle($format);
    expect(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Retire THROUGH THE CONSOLE. Retire carries only the operator floor, so any authenticated operator may
    // perform it; here the approver does.
    Livewire::test(ViewFormat::class, ['record' => $format->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.format.notifications.retired'));

    expect(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    $event = DomainEvent::query()->where('name', 'FormatRetired')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('Format')
        ->and($event->entity_id)->toBe((string) $format->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id);
});

it('reopens a retired Format to reviewed through the console (audit-only, no event)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    actingAs($creator, 'operator');
    $format = formatConsoleDraft();
    actingAs($reviewer, 'operator');
    app(SubmitFormatForReview::class)->handle($format);
    actingAs($approver, 'operator');
    app(ActivateFormat::class)->handle($format);
    app(RetireFormat::class)->handle($format);
    expect(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    $eventsBeforeReopen = DomainEvent::query()->count();

    // Reopen THROUGH THE CONSOLE → retired → reviewed.
    Livewire::test(ViewFormat::class, ['record' => $format->getKey()])
        ->callAction('reopen')
        ->assertNotified((string) __('operator_console.format.notifications.reopened'));

    // Back to `reviewed`, AUDIT-ONLY: reopen recorded NO new domain event (the event total is unchanged).
    expect(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->count())->toBe($eventsBeforeReopen);

    // One reopen audit row carrying the operator envelope + the lifecycle edge.
    $reopen = AuditRecord::query()->where('action', 'catalog.format.reopened')->sole();
    expect($reopen->entity_type)->toBe('Format')
        ->and($reopen->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($reopen->actor_id)->toEqual($approver->id)
        ->and($reopen->before)->toBe(['lifecycle_state' => 'retired'])
        ->and($reopen->after)->toBe(['lifecycle_state' => 'reviewed']);
});

it('surfaces an out-of-state retire as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft Format: retire requires `active`, so the domain rejects the out-of-state call. The console
    // surfaces it as a danger notification; it never pre-checks the from-state (design L4).
    $format = formatConsoleDraft();

    Livewire::test(ViewFormat::class, ['record' => $format->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.format.notifications.action_failed'));

    // Unchanged: still draft, and no FormatRetired recorded (the rejected attempt's transaction rolled back).
    expect(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'FormatRetired')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Task 4.2 (catalog-review-freshness-resubmit) — the visibility-gated re-submit header action
|--------------------------------------------------------------------------
|
| The review-freshness re-arm on the Format console (RM-06 / canon MVP-DEC-019; design D5) — the same
| visibility-gated re-submit the Product Master console gained in task 4.1, now on every spine console. Re-submit
| routes through the shared kit's lifecycleAction factory to ResubmitFormatForReview (never an Eloquent write);
| its ->visible() is gated to the DERIVED rejection-pending read (OperatorConsoleViewRecord::isRejectionPending) —
| OFFERED only while an un-remediated rejection blocks activation, HIDDEN otherwise. A ->visible()-false action is
| undrivable via test helpers, so the gating is proven with assertActionHidden/assertActionVisible and the re-arm
| is driven while re-submit IS visible (lessons.md 2026-06-23/24).
*/

it('offers re-submit on the Format console only when rejection-pending, re-arming review when driven', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $format = formatConsoleDraft();
    app(SubmitFormatForReview::class)->handle($format);

    // Fresh `reviewed` (never rejected): the derived rejection-pending read is false, so a redundant re-submit is
    // NOT offered — the action is HIDDEN (design D5; OperatorConsoleViewRecord::isRejectionPending).
    Livewire::test(ViewFormat::class, ['record' => $format->getKey()])
        ->assertActionHidden('resubmit');

    // A rejection (through the console) makes the Format rejection-pending — its latest governance action ends in
    // `.rejected` — so on a fresh mount re-submit is VISIBLE.
    Livewire::test(ViewFormat::class, ['record' => $format->getKey()])
        ->callAction('reject', ['notes' => 'Volume does not match the size label.']);

    Livewire::test(ViewFormat::class, ['record' => $format->getKey()])
        ->assertActionVisible('resubmit')
        ->callAction('resubmit')
        ->assertNotified((string) __('operator_console.format.notifications.resubmitted'));

    // Re-arm is state-preserving (reviewed → reviewed, audit-only) and clears the pending flag, so on a fresh
    // mount re-submit is HIDDEN again (the latest governance action is now `.resubmitted`, not `.rejected`) — the
    // write-through routed to ResubmitFormatForReview with the Format label, else the derived read would still see
    // the `.rejected` as latest and keep re-submit visible.
    expect(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    Livewire::test(ViewFormat::class, ['record' => $format->getKey()])
        ->assertActionHidden('resubmit');
});
