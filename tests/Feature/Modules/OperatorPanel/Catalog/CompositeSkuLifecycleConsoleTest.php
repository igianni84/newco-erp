<?php

// Task 4.1 (operator-console-catalog-spine; design L1/L3/L4/L5; ADR 2026-06-19 + 2026-06-20; spec — Operator
// creates / advances / surfaces the activation-cascade gate / retires & reopens the Composite SKU through the
// console). The Composite SKU is the FINAL spine entity and the spine's only many-to-many entity: a curated bundle
// of N ≥ 2 ORDERED constituent Product References. Its create form binds a single ordered constituents picker (no
// single parent FK, no producer — producer-agnostic, design D9), its ONE create guard is the `< 2 distinct
// constituents` floor (a localized domain RuntimeException surfaced as a form error via the kit base catch, design
// L5), its activation is gated on EVERY constituent being `active`, and — like the Sellable SKU — it is a LEAF
// within Module 0 (nothing within catalog references it, so retire carries no reference-integrity block). These pin
// the console's write-through surface, all built as PURE reuse of the kit (tasks 1.1/1.2): the create page routes
// the form's ordered constituent list into CreateCompositeSku, and the view page's five uniform lifecycle actions
// (submit · reject · activate · retire · reopen) each route to the matching Catalog domain action through the shared
// surfaceLifecycleOutcome helper. The console NEVER writes lifecycle_state itself (the no-Eloquent-write rule, task
// 1.2) and SURFACES the domain's decision — the from-state guard, the Creator → Reviewer → Approver
// separation-of-duties floor, and the N-constituent activation-cascade gate (a SKU cannot activate while ANY
// constituent Product Reference is non-active) — it reimplements none of them (design L4). Submit/reject/reopen are
// event-silent audit checkpoints (Module 0 PRD § 14.2); activate/retire record CompositeSKUActivated/Retired;
// create records CompositeSKUCreated (note: SKU UPPER-case in the event name).
//
// DatabaseMigrations (mirroring the Master/Format/Case Configuration/Variant/PR/Sellable SKU console tests): each
// console action drives a real domain action that opens its OWN DB::transaction, so the recorders'
// transaction-level guards see a real commit (level 0 → 1 → 0) — the faithful production shape (RefreshDatabase
// would wrap every write in a never-committed outer transaction). Catalog enums/models/actions are imported freely
// here: the {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\Catalog\Actions\ActivateCompositeSku;
use App\Modules\Catalog\Actions\CreateCompositeSku;
use App\Modules\Catalog\Actions\RetireCompositeSku;
use App\Modules\Catalog\Actions\SubmitCompositeSkuForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource\Pages\CreateCompositeSku as CreateCompositeSkuPage;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource\Pages\ViewCompositeSku;
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
 * A constituent Product Reference in `active`, stood up directly through the factory — the factory bypasses
 * CreateProductReference, so it records NO event (the activation cascade reads only each constituent's
 * `lifecycle_state`, so a factory-active PR is a legitimate fixture) and the count of operator-driven SKU events
 * stays clean. The PR factory auto-builds its own (draft) Variant + Format, irrelevant to the Composite's own
 * cascade gate (which reads the constituent PR's `lifecycle_state` directly, not the PR's parents).
 */
function compositeSkuConsoleActiveReference(): ProductReference
{
    return ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Active]);
}

/**
 * A draft Composite SKU created through the real Catalog action as the currently-acting operator (records
 * CompositeSKUCreated, no audit row), over the given ordered constituent Product Reference ids. Distinctly named
 * (`compositeSkuConsole` prefix) to avoid colliding with the Catalog lifecycle test's helpers (one shared Pest
 * function namespace).
 *
 * @param  list<int>  $referenceIds
 */
function compositeSkuConsoleDraft(array $referenceIds): CompositeSku
{
    return app(CreateCompositeSku::class)->handle($referenceIds);
}

/**
 * Stand a Composite SKU up in `active` under ACTIVE constituents through the real domain chain with three DISTINCT
 * operators (the default Creator → Reviewer → Approver floor). Leaves the `approver` as the acting operator. Used
 * by the retire / reopen tests, which start from `active`.
 *
 * @param  list<int>  $referenceIds
 */
function compositeSkuConsoleActive(Operator $creator, Operator $reviewer, Operator $approver, array $referenceIds): CompositeSku
{
    actingAs($creator, 'operator');
    $sku = compositeSkuConsoleDraft($referenceIds);
    actingAs($reviewer, 'operator');
    app(SubmitCompositeSkuForReview::class)->handle($sku);
    actingAs($approver, 'operator');
    app(ActivateCompositeSku::class)->handle($sku);

    return $sku->refresh();
}

it('creates a draft Composite SKU from an ordered N≥2 constituent set through the console, recording one CompositeSKUCreated with the ordered constituents and the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // Two constituents. Create does NOT gate on constituent state — the cascade gate is an ACTIVATE-time rule,
    // so draft constituents are valid for creation.
    $first = ProductReference::factory()->create();
    $second = ProductReference::factory()->create();

    // Fill the picker in REVERSE id order ([second, first]) so the assertion proves the bundle ORDER is preserved
    // end-to-end (a sort would re-order to ascending id and fail this).
    Livewire::test(CreateCompositeSkuPage::class)
        ->fillForm(['constituents' => [$second->id, $first->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    // The write routed through the action: a draft Composite SKU with the ordered constituent set.
    $composite = CompositeSku::query()->sole();

    expect($composite->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($composite->constituents->pluck('id')->all())->toEqual([$second->id, $first->id]);

    // Exactly one CompositeSKUCreated, carrying the ordered constituent ids + the operator audit envelope
    // (newco_ops + the operator id) resolved by the action from the `operator` guard.
    $event = DomainEvent::query()->where('name', 'CompositeSKUCreated')->sole();

    expect($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id)
        ->and($event->entity_type)->toBe('CompositeSku')
        ->and($event->entity_id)->toBe((string) $composite->id)
        ->and($event->payload['constituent_product_reference_ids'])->toEqual([$second->id, $first->id]);
});

it('surfaces a Composite SKU with fewer than two constituents as a form validation error, persisting nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A single constituent passes the picker's `required` rule (a non-empty array) but breaches the domain's
    // N ≥ 2 floor (BR-SKU-2). The action throws the localized InsufficientCompositeConstituents (a RuntimeException);
    // the kit base catch maps its message to the `constituents` form field — NOT a 500, and NOT the PR-style
    // framework catch (this rejection already carries a localized domain message, design L5).
    $only = ProductReference::factory()->create();

    Livewire::test(CreateCompositeSkuPage::class)
        ->fillForm(['constituents' => [$only->id]])
        ->call('create')
        // The rendered form error equals the localized domain message (count = 1 distinct constituent) — proving
        // the console surfaces the DOMAIN reason, not a generic validation string. The message is colon-free, so
        // Livewire's matcher does not truncate it.
        ->assertHasFormErrors([
            'constituents' => (string) __('catalog.composite_sku.insufficient_constituents', ['count' => 1]),
        ]);

    // Nothing persisted, no event recorded — the guard runs before the action's transaction.
    expect(CompositeSku::query()->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'CompositeSKUCreated')->count())->toBe(0);
});

it('accepts a multi-producer constituent bundle through the console (PIM is producer-agnostic, BR-SKU-5)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // Two full chains whose Masters carry DIFFERENT producers (producer_id is a plain column — no relation,
    // invariant 10), so the constituent set is genuinely multi-producer.
    $masterA = ProductMaster::factory()->create(['producer_id' => 1001]);
    $variantA = ProductVariant::factory()->create(['product_master_id' => $masterA->id]);
    $prA = ProductReference::factory()->create(['product_variant_id' => $variantA->id, 'format_id' => Format::factory()]);

    $masterB = ProductMaster::factory()->create(['producer_id' => 2002]);
    $variantB = ProductVariant::factory()->create(['product_master_id' => $masterB->id]);
    $prB = ProductReference::factory()->create(['product_variant_id' => $variantB->id, 'format_id' => Format::factory()]);

    expect($masterA->producer_id)->not->toBe($masterB->producer_id); // the bundle really is multi-producer

    // PIM accepts the multi-producer bundle WITHOUT validating producer composition (design D9): the console
    // applies no producer filter/validation, and the action runs no producer guard — the creation simply
    // succeeding is the proof.
    Livewire::test(CreateCompositeSkuPage::class)
        ->fillForm(['constituents' => [$prA->id, $prB->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    $composite = CompositeSku::query()->sole();

    expect($composite->constituents->pluck('id')->all())->toEqual([$prA->id, $prB->id])
        ->and(DomainEvent::query()->where('name', 'CompositeSKUCreated')->count())->toBe(1);
});

it('submits a draft Composite SKU for review through the console, recording the submit audit row and no domain event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $sku = compositeSkuConsoleDraft([
        ProductReference::factory()->create()->id,
        ProductReference::factory()->create()->id,
    ]);
    expect($sku->lifecycle_state)->toBe(LifecycleState::Draft);

    Livewire::test(ViewCompositeSku::class, ['record' => $sku->getKey()])
        ->callAction('submit')
        ->assertNotified((string) __('operator_console.composite_sku.notifications.submitted'));

    // State advanced draft → reviewed via the domain action (the console never writes lifecycle_state).
    expect(CompositeSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // Exactly one submit audit row carrying the operator envelope + the lifecycle edge; submit is audit-only.
    $audit = AuditRecord::query()->where('action', 'catalog.composite_sku.submitted')->sole();

    expect($audit->entity_type)->toBe('CompositeSku')
        ->and($audit->entity_id)->toBe((string) $sku->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($operator->id)
        ->and($audit->before)->toBe(['lifecycle_state' => 'draft'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed']);

    // Event-silent: the only Composite SKU event remains the creation's (no *Activated, no *Reviewed).
    expect(DomainEvent::query()->where('name', 'CompositeSKUActivated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('entity_type', 'CompositeSku')->count())->toBe(1);
});

it('records a console rejection with notes, keeping the Composite SKU in reviewed and emitting no event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $sku = compositeSkuConsoleDraft([
        ProductReference::factory()->create()->id,
        ProductReference::factory()->create()->id,
    ]);
    app(SubmitCompositeSkuForReview::class)->handle($sku);

    Livewire::test(ViewCompositeSku::class, ['record' => $sku->getKey()])
        ->callAction('reject', ['notes' => 'Bundle composition needs revision.'])
        ->assertNotified((string) __('operator_console.composite_sku.notifications.rejected'));

    // Stays reviewed — a rejection is a reviewed → reviewed decision (§ 4.3); there is no revert to draft.
    expect(CompositeSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // One rejection audit row carrying the decision + notes + the acting operator principal.
    $rejection = AuditRecord::query()->where('action', 'catalog.composite_sku.rejected')->sole();
    $after = $rejection->after ?? []; // narrow the nullable jsonb; keys asserted order-independently (PG reorders)

    expect($rejection->entity_type)->toBe('CompositeSku')
        ->and($rejection->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($rejection->actor_id)->toEqual($operator->id)
        ->and($after['lifecycle_state'] ?? null)->toBe('reviewed')
        ->and($after['decision'] ?? null)->toBe('rejected')
        ->and($after['notes'] ?? null)->toBe('Bundle composition needs revision.');

    expect(DomainEvent::query()->where('name', 'CompositeSKUActivated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('exposes an activate action carrying the localized "second actor required" affordance and no cascade-retire action', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $sku = compositeSkuConsoleDraft([
        ProductReference::factory()->create()->id,
        ProductReference::factory()->create()->id,
    ]);

    Livewire::test(ViewCompositeSku::class, ['record' => $sku->getKey()])
        // The five uniform lifecycle actions are present …
        ->assertActionExists('submit')
        ->assertActionExists('reject')
        ->assertActionExists('retire')
        ->assertActionExists('reopen')
        // … activate SURFACES the separation-of-duties floor as a confirmation affordance (design L4): a
        // distinct approver is required — the console reminds, it never re-checks.
        ->assertActionExists('activate', fn (Action $action): bool => $action->isConfirmationRequired()
            && $action->getModalDescription() === (string) __('operator_console.composite_sku.affordance.second_actor'))
        // … and NO cascade-retire affordance exists for a spine entity (Master-only, scope guard).
        ->assertActionDoesNotExist('retireCascade');
});

it('activates a reviewed Composite SKU under active constituents through the console when a distinct approver acts', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // EVERY constituent is `active`, and three DISTINCT operators satisfy the default Creator → Reviewer → Approver
    // floor — both activation preconditions (the N-constituent cascade gate + the SoD floor) are met.
    $first = compositeSkuConsoleActiveReference();
    $second = compositeSkuConsoleActiveReference();

    actingAs($creator, 'operator');
    $sku = compositeSkuConsoleDraft([$first->id, $second->id]);

    actingAs($reviewer, 'operator');
    app(SubmitCompositeSkuForReview::class)->handle($sku);

    actingAs($approver, 'operator');
    Livewire::test(ViewCompositeSku::class, ['record' => $sku->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.composite_sku.notifications.activated'));

    expect(CompositeSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Exactly one CompositeSKUActivated carrying the operator envelope — actor_role newco_ops + the APPROVER (not
    // the creator/reviewer) as actor_id.
    $event = DomainEvent::query()->where('name', 'CompositeSKUActivated')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('CompositeSku')
        ->and($event->entity_id)->toBe((string) $sku->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id);
});

it('surfaces a cascade-gate-blocked activate (one constituent not active) as a danger notification, leaving the Composite SKU reviewed', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // One constituent is active but the other is NOT (factory-reviewed). Three DISTINCT operators satisfy the SoD
    // floor, so it is the activation-CASCADE gate — not the approval governance — that blocks the activate: a
    // Composite SKU may activate only once EVERY constituent is `active`. The console SURFACES the domain's
    // ActivationCascadeViolation (catalog.gate.parent_not_active) and re-checks the constituents NOTHING (design L4).
    $active = compositeSkuConsoleActiveReference();
    $reviewed = ProductReference::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    actingAs($creator, 'operator');
    $sku = compositeSkuConsoleDraft([$active->id, $reviewed->id]);

    actingAs($reviewer, 'operator');
    app(SubmitCompositeSkuForReview::class)->handle($sku);

    actingAs($approver, 'operator');
    Livewire::test(ViewCompositeSku::class, ['record' => $sku->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.composite_sku.notifications.action_failed'));

    // Unchanged: still reviewed (the gate rolled the transition back), NO CompositeSKUActivated event, and NO
    // activation audit row.
    expect(CompositeSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'CompositeSKUActivated')->count())->toBe(0)
        ->and(AuditRecord::query()->where('action', 'catalog.composite_sku.activated')->count())->toBe(0);
});

it('surfaces a self-approval governance rejection as a danger notification, leaving the Composite SKU reviewed', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    // Every constituent is active, so the cascade gate would clear — it is the SoD floor that must block here.
    $first = compositeSkuConsoleActiveReference();
    $second = compositeSkuConsoleActiveReference();

    actingAs($creator, 'operator');
    $sku = compositeSkuConsoleDraft([$first->id, $second->id]);

    actingAs($reviewer, 'operator');
    app(SubmitCompositeSkuForReview::class)->handle($sku);

    // The reviewer (who performed the prior governance step) attempts the approval — the domain rejects the
    // self-approval; the console SURFACES it as a danger notification and never re-checks the floor (design L4).
    Livewire::test(ViewCompositeSku::class, ['record' => $sku->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.composite_sku.notifications.action_failed'));

    // Unchanged — still reviewed, NO activation event, NO activation audit row (the action's txn rolled back).
    expect(CompositeSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'CompositeSKUActivated')->count())->toBe(0)
        ->and(AuditRecord::query()->where('action', 'catalog.composite_sku.activated')->count())->toBe(0);
});

it('retires an active Composite SKU through the console, recording one CompositeSKURetired with the operator envelope', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $sku = compositeSkuConsoleActive($creator, $reviewer, $approver, [
        compositeSkuConsoleActiveReference()->id,
        compositeSkuConsoleActiveReference()->id,
    ]);
    expect($sku->lifecycle_state)->toBe(LifecycleState::Active);

    // Retire THROUGH THE CONSOLE. A Composite SKU is a LEAF within Module 0 — nothing within catalog references it
    // — so there is no within-catalog reference-integrity block; the retire succeeds straight away.
    Livewire::test(ViewCompositeSku::class, ['record' => $sku->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.composite_sku.notifications.retired'));

    expect(CompositeSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    $event = DomainEvent::query()->where('name', 'CompositeSKURetired')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('CompositeSku')
        ->and($event->entity_id)->toBe((string) $sku->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id);
});

it('reopens a retired Composite SKU to reviewed through the console (audit-only, no event)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $sku = compositeSkuConsoleActive($creator, $reviewer, $approver, [
        compositeSkuConsoleActiveReference()->id,
        compositeSkuConsoleActiveReference()->id,
    ]);
    app(RetireCompositeSku::class)->handle($sku);
    expect(CompositeSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    $eventsBeforeReopen = DomainEvent::query()->count();

    // Reopen THROUGH THE CONSOLE → retired → reviewed.
    Livewire::test(ViewCompositeSku::class, ['record' => $sku->getKey()])
        ->callAction('reopen')
        ->assertNotified((string) __('operator_console.composite_sku.notifications.reopened'));

    // Back to `reviewed`, AUDIT-ONLY: reopen recorded NO new domain event (the event total is unchanged). A
    // subsequent activate would re-check the activation-cascade gate (the same Action runs).
    expect(CompositeSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->count())->toBe($eventsBeforeReopen);

    // One reopen audit row carrying the operator envelope + the lifecycle edge.
    $reopen = AuditRecord::query()->where('action', 'catalog.composite_sku.reopened')->sole();
    expect($reopen->entity_type)->toBe('CompositeSku')
        ->and($reopen->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($reopen->actor_id)->toEqual($approver->id)
        ->and($reopen->before)->toBe(['lifecycle_state' => 'retired'])
        ->and($reopen->after)->toBe(['lifecycle_state' => 'reviewed']);
});

it('surfaces an out-of-state retire as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft Composite SKU: retire requires `active`, so the domain rejects the out-of-state call. The console
    // surfaces it as a danger notification; it never pre-checks the from-state (design L4).
    $sku = compositeSkuConsoleDraft([
        ProductReference::factory()->create()->id,
        ProductReference::factory()->create()->id,
    ]);

    Livewire::test(ViewCompositeSku::class, ['record' => $sku->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.composite_sku.notifications.action_failed'));

    // Unchanged: still draft, and no CompositeSKURetired recorded (the rejected attempt's txn rolled back).
    expect(CompositeSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'CompositeSKURetired')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Task 4.2 (catalog-review-freshness-resubmit) — the visibility-gated re-submit header action
|--------------------------------------------------------------------------
|
| The review-freshness re-arm on the Composite SKU console (RM-06 / canon MVP-DEC-019; design D5) — the same
| visibility-gated re-submit the Product Master console gained in task 4.1, now on every spine console. Re-submit
| routes through the shared kit's lifecycleAction factory to ResubmitCompositeSkuForReview (never an Eloquent
| write); its ->visible() is gated to the DERIVED rejection-pending read
| (OperatorConsoleViewRecord::isRejectionPending) — OFFERED only while an un-remediated rejection blocks
| activation, HIDDEN otherwise. A ->visible()-false action is undrivable via test helpers, so the gating is proven
| with assertActionHidden/assertActionVisible and the re-arm is driven while re-submit IS visible (lessons.md
| 2026-06-23/24). Two distinct constituents satisfy the create floor; this flow never activates.
*/

it('offers re-submit on the Composite SKU console only when rejection-pending, re-arming review when driven', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $constituentA = compositeSkuConsoleActiveReference();
    $constituentB = compositeSkuConsoleActiveReference();
    $sku = compositeSkuConsoleDraft([$constituentA->id, $constituentB->id]);
    app(SubmitCompositeSkuForReview::class)->handle($sku);

    // Fresh `reviewed` (never rejected): the derived rejection-pending read is false, so a redundant re-submit is
    // NOT offered — the action is HIDDEN (design D5; OperatorConsoleViewRecord::isRejectionPending).
    Livewire::test(ViewCompositeSku::class, ['record' => $sku->getKey()])
        ->assertActionHidden('resubmit');

    // A rejection (through the console) makes it rejection-pending — its latest governance action ends in
    // `.rejected` — so on a fresh mount re-submit is VISIBLE.
    Livewire::test(ViewCompositeSku::class, ['record' => $sku->getKey()])
        ->callAction('reject', ['notes' => 'The constituent ordering needs review.']);

    Livewire::test(ViewCompositeSku::class, ['record' => $sku->getKey()])
        ->assertActionVisible('resubmit')
        ->callAction('resubmit')
        ->assertNotified((string) __('operator_console.composite_sku.notifications.resubmitted'));

    // Re-arm is state-preserving (reviewed → reviewed, audit-only) and clears the pending flag, so on a fresh
    // mount re-submit is HIDDEN again (the latest governance action is now `.resubmitted`, not `.rejected`) — the
    // write-through routed to ResubmitCompositeSkuForReview with the CompositeSku label, else the derived read
    // would still see the `.rejected` as latest and keep re-submit visible.
    expect(CompositeSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    Livewire::test(ViewCompositeSku::class, ['record' => $sku->getKey()])
        ->assertActionHidden('resubmit');
});
