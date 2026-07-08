<?php

// Task 3.3 (operator-console-catalog-spine; design L1/L3/L4; ADR 2026-06-19 + 2026-06-20; spec — Operator
// creates / advances / surfaces the activation-cascade gate / retires & reopens the Sellable SKU through the
// console). The Sellable SKU is the THIRD hierarchical spine entity and the commercial unit: its create form binds
// exactly TWO parents (a Product Reference + a Case Configuration) plus the commercial attributes, its activation
// is gated on BOTH parents being `active`, and — unlike the Product Reference — it has NO uniqueness rule (a PR +
// Case Configuration pair may back MANY SKUs, BR-SKU-1) and is a LEAF within Module 0 (nothing within catalog
// references it, so retire carries no reference-integrity block). These pin the console's write-through surface,
// all built as PURE reuse of the kit (tasks 1.1/1.2): the create page routes the form into CreateSellableSku, and
// the view page's five uniform lifecycle actions (submit · reject · activate · retire · reopen) each route to the
// matching Catalog domain action through the shared surfaceLifecycleOutcome helper. The console NEVER writes
// lifecycle_state itself (the no-Eloquent-write rule, task 1.2) and SURFACES the domain's decision — the
// from-state guard, the Creator → Reviewer → Approver separation-of-duties floor, and the activation-cascade gate
// (a SKU cannot activate under a non-active Product Reference OR Case Configuration) — it reimplements none of
// them (design L4). A SKU binds NO producer (no producer gate) and has NO cascade-retire affordance (Master-only,
// scope guard). Submit/reject/reopen are event-silent audit checkpoints (Module 0 PRD § 14.2); activate/retire
// record SellableSKUActivated/Retired; create records SellableSKUCreated (note: SKU UPPER-case in the event name).
//
// DatabaseMigrations (mirroring the Master/Format/Case Configuration/Variant/PR console tests): each console
// action drives a real domain action that opens its OWN DB::transaction, so the recorders' transaction-level
// guards see a real commit (level 0 → 1 → 0) — the faithful production shape (RefreshDatabase would wrap every
// write in a never-committed outer transaction). Catalog enums/models/actions are imported freely here: the
// {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\Catalog\Actions\ActivateSellableSku;
use App\Modules\Catalog\Actions\CreateSellableSku;
use App\Modules\Catalog\Actions\RetireSellableSku;
use App\Modules\Catalog\Actions\SubmitSellableSkuForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource\Pages\CreateSellableSku as CreateSellableSkuPage;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource\Pages\ViewSellableSku;
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
 * A parent Product Reference in `active`, stood up directly through the factory — the factory bypasses
 * CreateProductReference, so it records NO event (the activation cascade reads only the parent's `lifecycle_state`,
 * so a factory-active PR is a legitimate fixture) and the count of operator-driven SKU events stays clean. The PR
 * factory auto-builds its own (draft) Variant + Format, which are irrelevant to the SKU's own cascade gate (it
 * reads the PR's `lifecycle_state` directly, not the PR's parents).
 */
function sellableSkuConsoleActiveReference(): ProductReference
{
    return ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
}

/**
 * A parent Case Configuration in `active`, stood up directly through the factory (records no event) — the SKU's
 * second parent.
 */
function sellableSkuConsoleActiveCaseConfiguration(): CaseConfiguration
{
    return CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Active]);
}

/**
 * A draft Sellable SKU created through the real Catalog action as the currently-acting operator (records
 * SellableSKUCreated, no audit row), over the given Product Reference + Case Configuration. Distinctly named
 * (`sellableSkuConsole` prefix) to avoid colliding with the Catalog lifecycle test's helpers (one shared Pest
 * function namespace).
 */
function sellableSkuConsoleDraft(int $referenceId, int $caseConfigurationId, string $commercialName = 'Console SKU'): SellableSku
{
    return app(CreateSellableSku::class)->handle(
        productReferenceId: $referenceId,
        caseConfigurationId: $caseConfigurationId,
        commercialName: $commercialName,
    );
}

/**
 * Stand a Sellable SKU up in `active` under ACTIVE parents through the real domain chain with three DISTINCT
 * operators (the default Creator → Reviewer → Approver floor). Leaves the `approver` as the acting operator.
 * Used by the retire / reopen tests, which start from `active`.
 */
function sellableSkuConsoleActive(Operator $creator, Operator $reviewer, Operator $approver, int $referenceId, int $caseConfigurationId): SellableSku
{
    actingAs($creator, 'operator');
    $sku = sellableSkuConsoleDraft($referenceId, $caseConfigurationId);
    actingAs($reviewer, 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);
    actingAs($approver, 'operator');
    app(ActivateSellableSku::class)->handle($sku);

    return $sku->refresh();
}

it('creates a draft Sellable SKU under a Product Reference + Case Configuration through the console, recording one SellableSKUCreated with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // Two parents to select. Create does NOT gate on parent state — the cascade gate is an ACTIVATE-time rule,
    // so draft parents are valid for creation.
    $reference = ProductReference::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    Livewire::test(CreateSellableSkuPage::class)
        ->fillForm([
            'product_reference_id' => $reference->id,
            'case_configuration_id' => $caseConfiguration->id,
            'commercial_name' => 'Barolo Riserva',
            'marketing_copy' => 'A storied estate.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // The write routed through the action: a draft SKU referencing both selected parents + the commercial fields.
    $sku = SellableSku::query()
        ->where('product_reference_id', $reference->id)
        ->where('case_configuration_id', $caseConfiguration->id)
        ->sole();

    expect($sku->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($sku->product_reference_id)->toEqual($reference->id)
        ->and($sku->case_configuration_id)->toEqual($caseConfiguration->id)
        ->and($sku->commercial_name)->toBe('Barolo Riserva')
        ->and($sku->marketing_copy)->toBe('A storied estate.');

    // Exactly one SellableSKUCreated, carrying the operator audit envelope (newco_ops + the operator id) resolved
    // by the action from the `operator` guard — the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', 'SellableSKUCreated')->sole();

    expect($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id)
        ->and($event->entity_type)->toBe('SellableSku')
        ->and($event->entity_id)->toBe((string) $sku->id);
});

it('creates two Sellable SKUs over the same Product Reference + Case Configuration pair through the console (no uniqueness rule)', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $reference = ProductReference::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    // First SKU over the pair.
    Livewire::test(CreateSellableSkuPage::class)
        ->fillForm([
            'product_reference_id' => $reference->id,
            'case_configuration_id' => $caseConfiguration->id,
            'commercial_name' => 'Loose bottle',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Second SKU over the SAME pair — a Product Reference + Case Configuration pair may legitimately back many
    // SKUs (BR-SKU-1: packaging does not change the PR), so this must ALSO succeed: there is NO uniqueness guard
    // (contrast the Product Reference's unique (variant, format) identity, which surfaces a form error).
    Livewire::test(CreateSellableSkuPage::class)
        ->fillForm([
            'product_reference_id' => $reference->id,
            'case_configuration_id' => $caseConfiguration->id,
            'commercial_name' => 'Carton bottle',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Both SKUs persisted over the same pair, each recording its own SellableSKUCreated.
    expect(
        SellableSku::query()
            ->where('product_reference_id', $reference->id)
            ->where('case_configuration_id', $caseConfiguration->id)
            ->count()
    )->toBe(2)
        ->and(DomainEvent::query()->where('name', 'SellableSKUCreated')->count())->toBe(2);
});

it('submits a draft Sellable SKU for review through the console, recording the submit audit row and no domain event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $reference = ProductReference::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();
    $sku = sellableSkuConsoleDraft($reference->id, $caseConfiguration->id);
    expect($sku->lifecycle_state)->toBe(LifecycleState::Draft);

    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        ->callAction('submit')
        ->assertNotified((string) __('operator_console.sellable_sku.notifications.submitted'));

    // State advanced draft → reviewed via the domain action (the console never writes lifecycle_state).
    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // Exactly one submit audit row carrying the operator envelope + the lifecycle edge; submit is audit-only.
    $audit = AuditRecord::query()->where('action', 'catalog.sellable_sku.submitted')->sole();

    expect($audit->entity_type)->toBe('SellableSku')
        ->and($audit->entity_id)->toBe((string) $sku->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($operator->id)
        ->and($audit->before)->toBe(['lifecycle_state' => 'draft'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed']);

    // Event-silent: the only SKU event remains the creation's (no *Activated, no *Reviewed).
    expect(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('entity_type', 'SellableSku')->count())->toBe(1);
});

it('records a console rejection with notes, keeping the Sellable SKU in reviewed and emitting no event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $reference = ProductReference::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();
    $sku = sellableSkuConsoleDraft($reference->id, $caseConfiguration->id);
    app(SubmitSellableSkuForReview::class)->handle($sku);

    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        ->callAction('reject', ['notes' => 'Commercial name does not match the listing.'])
        ->assertNotified((string) __('operator_console.sellable_sku.notifications.rejected'));

    // Stays reviewed — a rejection is a reviewed → reviewed decision (§ 4.3); there is no revert to draft.
    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // One rejection audit row carrying the decision + notes + the acting operator principal.
    $rejection = AuditRecord::query()->where('action', 'catalog.sellable_sku.rejected')->sole();
    $after = $rejection->after ?? []; // narrow the nullable jsonb; keys asserted order-independently (PG reorders)

    expect($rejection->entity_type)->toBe('SellableSku')
        ->and($rejection->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($rejection->actor_id)->toEqual($operator->id)
        ->and($after['lifecycle_state'] ?? null)->toBe('reviewed')
        ->and($after['decision'] ?? null)->toBe('rejected')
        ->and($after['notes'] ?? null)->toBe('Commercial name does not match the listing.');

    expect(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('exposes an activate action carrying the localized "second actor required" affordance and no cascade-retire action', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $reference = ProductReference::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();
    $sku = sellableSkuConsoleDraft($reference->id, $caseConfiguration->id);

    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        // The five uniform lifecycle actions are present …
        ->assertActionExists('submit')
        ->assertActionExists('reject')
        ->assertActionExists('retire')
        ->assertActionExists('reopen')
        // … activate SURFACES the separation-of-duties floor as a confirmation affordance (design L4): a
        // distinct approver is required — the console reminds, it never re-checks.
        ->assertActionExists('activate', fn (Action $action): bool => $action->isConfirmationRequired()
            && $action->getModalDescription() === (string) __('operator_console.sellable_sku.affordance.second_actor'))
        // … and NO cascade-retire affordance exists for a spine entity (Master-only, scope guard).
        ->assertActionDoesNotExist('retireCascade');
});

it('activates a reviewed Sellable SKU under active parents through the console when a distinct approver acts', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // BOTH parents are `active`, and three DISTINCT operators satisfy the default Creator → Reviewer → Approver
    // floor — both activation preconditions (the two-parent cascade gate + the SoD floor) are met.
    $reference = sellableSkuConsoleActiveReference();
    $caseConfiguration = sellableSkuConsoleActiveCaseConfiguration();

    actingAs($creator, 'operator');
    $sku = sellableSkuConsoleDraft($reference->id, $caseConfiguration->id);

    actingAs($reviewer, 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);

    actingAs($approver, 'operator');
    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.sellable_sku.notifications.activated'));

    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Exactly one SellableSKUActivated carrying the operator envelope — actor_role newco_ops + the APPROVER (not
    // the creator/reviewer) as actor_id.
    $event = DomainEvent::query()->where('name', 'SellableSKUActivated')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('SellableSku')
        ->and($event->entity_id)->toBe((string) $sku->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id);
});

it('surfaces a cascade-gate-blocked activate (a parent Case Configuration not active) as a danger notification, leaving the Sellable SKU reviewed', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // The Product Reference is active but the Case Configuration is NOT (factory-reviewed). Three DISTINCT
    // operators satisfy the SoD floor, so it is the activation-CASCADE gate — not the approval governance — that
    // blocks the activate: a SKU may activate only once BOTH its parents (Product Reference AND Case
    // Configuration) are `active`. The console SURFACES the domain's ActivationCascadeViolation
    // (catalog.gate.parent_not_active) and re-checks the parents NOTHING (design L4).
    $reference = sellableSkuConsoleActiveReference();
    $caseConfiguration = CaseConfiguration::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    actingAs($creator, 'operator');
    $sku = sellableSkuConsoleDraft($reference->id, $caseConfiguration->id);

    actingAs($reviewer, 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);

    actingAs($approver, 'operator');
    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.sellable_sku.notifications.action_failed'));

    // Unchanged: still reviewed (the gate rolled the transition back), NO SellableSKUActivated event, and NO
    // activation audit row.
    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(0)
        ->and(AuditRecord::query()->where('action', 'catalog.sellable_sku.activated')->count())->toBe(0);
});

it('surfaces a self-approval governance rejection as a danger notification, leaving the Sellable SKU reviewed', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    // Both parents are active, so the cascade gate would clear — it is the SoD floor that must block here.
    $reference = sellableSkuConsoleActiveReference();
    $caseConfiguration = sellableSkuConsoleActiveCaseConfiguration();

    actingAs($creator, 'operator');
    $sku = sellableSkuConsoleDraft($reference->id, $caseConfiguration->id);

    actingAs($reviewer, 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);

    // The reviewer (who performed the prior governance step) attempts the approval — the domain rejects the
    // self-approval; the console SURFACES it as a danger notification and never re-checks the floor (design L4).
    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.sellable_sku.notifications.action_failed'));

    // Unchanged — still reviewed, NO activation event, NO activation audit row (the action's txn rolled back).
    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(0)
        ->and(AuditRecord::query()->where('action', 'catalog.sellable_sku.activated')->count())->toBe(0);
});

it('retires an active Sellable SKU through the console, recording one SellableSKURetired with the operator envelope', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $reference = sellableSkuConsoleActiveReference();
    $caseConfiguration = sellableSkuConsoleActiveCaseConfiguration();
    $sku = sellableSkuConsoleActive($creator, $reviewer, $approver, $reference->id, $caseConfiguration->id);
    expect($sku->lifecycle_state)->toBe(LifecycleState::Active);

    // Retire THROUGH THE CONSOLE. A Sellable SKU is a LEAF within Module 0 — nothing within catalog references
    // it — so there is no within-catalog reference-integrity block; the retire succeeds straight away.
    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.sellable_sku.notifications.retired'));

    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    $event = DomainEvent::query()->where('name', 'SellableSKURetired')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('SellableSku')
        ->and($event->entity_id)->toBe((string) $sku->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id);
});

it('reopens a retired Sellable SKU to reviewed through the console (audit-only, no event)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $reference = sellableSkuConsoleActiveReference();
    $caseConfiguration = sellableSkuConsoleActiveCaseConfiguration();
    $sku = sellableSkuConsoleActive($creator, $reviewer, $approver, $reference->id, $caseConfiguration->id);
    app(RetireSellableSku::class)->handle($sku);
    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    $eventsBeforeReopen = DomainEvent::query()->count();

    // Reopen THROUGH THE CONSOLE → retired → reviewed.
    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        ->callAction('reopen')
        ->assertNotified((string) __('operator_console.sellable_sku.notifications.reopened'));

    // Back to `reviewed`, AUDIT-ONLY: reopen recorded NO new domain event (the event total is unchanged). A
    // subsequent activate would re-check the activation-cascade gate (the same Action runs).
    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->count())->toBe($eventsBeforeReopen);

    // One reopen audit row carrying the operator envelope + the lifecycle edge.
    $reopen = AuditRecord::query()->where('action', 'catalog.sellable_sku.reopened')->sole();
    expect($reopen->entity_type)->toBe('SellableSku')
        ->and($reopen->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($reopen->actor_id)->toEqual($approver->id)
        ->and($reopen->before)->toBe(['lifecycle_state' => 'retired'])
        ->and($reopen->after)->toBe(['lifecycle_state' => 'reviewed']);
});

it('surfaces an out-of-state retire as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft SKU: retire requires `active`, so the domain rejects the out-of-state call. The console surfaces it
    // as a danger notification; it never pre-checks the from-state (design L4).
    $reference = ProductReference::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();
    $sku = sellableSkuConsoleDraft($reference->id, $caseConfiguration->id);

    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.sellable_sku.notifications.action_failed'));

    // Unchanged: still draft, and no SellableSKURetired recorded (the rejected attempt's txn rolled back).
    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'SellableSKURetired')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Task 4.2 (catalog-review-freshness-resubmit) — the visibility-gated re-submit header action
|--------------------------------------------------------------------------
|
| The review-freshness re-arm on the Sellable SKU console (RM-06 / canon MVP-DEC-019; design D5) — the same
| visibility-gated re-submit the Product Master console gained in task 4.1, now on every spine console. Re-submit
| routes through the shared kit's lifecycleAction factory to ResubmitSellableSkuForReview (never an Eloquent
| write); its ->visible() is gated to the DERIVED rejection-pending read
| (OperatorConsoleViewRecord::isReviewStale) — OFFERED only while an un-remediated rejection blocks
| activation, HIDDEN otherwise. A ->visible()-false action is undrivable via test helpers, so the gating is proven
| with assertActionHidden/assertActionVisible and the re-arm is driven while re-submit IS visible (lessons.md
| 2026-06-23/24). This flow never activates, so the parents' state is immaterial; the active-parent fixtures are
| reused only because they are the file's cheapest valid SKU parents.
*/

it('offers re-submit on the Sellable SKU console only when rejection-pending, re-arming review when driven', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $reference = sellableSkuConsoleActiveReference();
    $caseConfiguration = sellableSkuConsoleActiveCaseConfiguration();
    $sku = sellableSkuConsoleDraft($reference->id, $caseConfiguration->id);
    app(SubmitSellableSkuForReview::class)->handle($sku);

    // Fresh `reviewed` (never rejected): the derived rejection-pending read is false, so a redundant re-submit is
    // NOT offered — the action is HIDDEN (design D5; OperatorConsoleViewRecord::isReviewStale).
    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        ->assertActionHidden('resubmit');

    // A rejection (through the console) makes it rejection-pending — its latest governance action ends in
    // `.rejected` — so on a fresh mount re-submit is VISIBLE.
    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        ->callAction('reject', ['notes' => 'Commercial name needs revision.']);

    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        ->assertActionVisible('resubmit')
        ->callAction('resubmit')
        ->assertNotified((string) __('operator_console.sellable_sku.notifications.resubmitted'));

    // Re-arm is state-preserving (reviewed → reviewed, audit-only) and clears the pending flag, so on a fresh
    // mount re-submit is HIDDEN again (the latest governance action is now `.resubmitted`, not `.rejected`) — the
    // write-through routed to ResubmitSellableSkuForReview with the SellableSku label, else the derived read would
    // still see the `.rejected` as latest and keep re-submit visible.
    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        ->assertActionHidden('resubmit');
});
