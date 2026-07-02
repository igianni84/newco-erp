<?php

// Task 3.2 (operator-console-catalog-spine; design L1/L3/L4/L5; ADR 2026-06-19 + 2026-06-20; spec — Operator
// creates / advances / surfaces the activation-cascade gate / retires & reopens the Product Reference through the
// console, with the duplicate create-error and the retire reference-integrity block surfaced). The Product
// Reference is the SECOND hierarchical spine entity and the atomic product key: its create form binds exactly TWO
// parents (a Product Variant + a Format), its `(variant, format)` pair is unique at the DB (a duplicate is
// surfaced as a form error — the ONE console-owned message, design L5), its activation is gated on BOTH parents
// being `active`, and its retire is blocked while an active Sellable / Composite SKU references it. These pin the
// console's write-through surface, all built as PURE reuse of the kit (tasks 1.1/1.2): the create page routes the
// form into CreateProductReference, and the view page's five uniform lifecycle actions (submit · reject ·
// activate · retire · reopen) each route to the matching Catalog domain action through the shared
// surfaceLifecycleOutcome helper. The console NEVER writes lifecycle_state itself (the no-Eloquent-write rule,
// task 1.2) and SURFACES the domain's decision — the from-state guard, the Creator → Reviewer → Approver
// separation-of-duties floor, the activation-cascade gate (a PR cannot activate under a non-active Variant OR
// Format), AND the retire reference-integrity block — it reimplements none of them (design L4). A PR binds NO
// producer (no producer gate) and has NO cascade-retire affordance (Master-only, scope guard). Submit/reject/
// reopen are event-silent audit checkpoints (Module 0 PRD § 14.2); activate/retire record
// ProductReferenceActivated/Retired; create records ProductReferenceCreated.
//
// DatabaseMigrations (mirroring the Master/Format/Case Configuration/Variant console tests): each console action
// drives a real domain action that opens its OWN DB::transaction, so the recorders' transaction-level guards see
// a real commit (level 0 → 1 → 0) — the faithful production shape (RefreshDatabase would wrap every write in a
// never-committed outer transaction). The duplicate-pair insert's UniqueConstraintViolationException is raised
// inside the action's savepoint and rolled back to it, so the connection survives for the assertions (the same
// isolation the Catalog ProductReferenceTest relies on). Catalog enums/models/actions are imported freely here:
// the {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\Catalog\Actions\ActivateProductReference;
use App\Modules\Catalog\Actions\CreateProductReference;
use App\Modules\Catalog\Actions\RetireProductReference;
use App\Modules\Catalog\Actions\SubmitProductReferenceForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource\Pages\CreateProductReference as CreateProductReferencePage;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource\Pages\ViewProductReference;
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
 * A parent Product Variant in `active`, stood up directly through the factory — the factory bypasses
 * CreateProductVariant, so it records NO event (the activation cascade reads only the parent's `lifecycle_state`,
 * so a factory-active Variant is a legitimate fixture) and the count of operator-driven PR events stays clean.
 */
function productReferenceConsoleActiveVariant(string $identifier = 'GRAND-CRU-2019'): ProductVariant
{
    return ProductVariant::factory()->create(['variant_identifier' => $identifier, 'lifecycle_state' => LifecycleState::Active]);
}

/**
 * A parent Format in `active`, stood up directly through the factory (records no event) — the PR's second parent.
 */
function productReferenceConsoleActiveFormat(string $name = 'Magnum'): Format
{
    return Format::factory()->create(['name' => $name, 'lifecycle_state' => LifecycleState::Active]);
}

/**
 * A draft Product Reference created through the real Catalog action as the currently-acting operator (records
 * ProductReferenceCreated, no audit row), over the given Variant + Format. Distinctly named (`productReference
 * Console` prefix) to avoid colliding with the Catalog lifecycle test's helpers (one shared Pest function
 * namespace).
 */
function productReferenceConsoleDraft(int $variantId, int $formatId): ProductReference
{
    return app(CreateProductReference::class)->handle(productVariantId: $variantId, formatId: $formatId);
}

/**
 * Stand a Product Reference up in `active` under ACTIVE parents through the real domain chain with three DISTINCT
 * operators (the default Creator → Reviewer → Approver floor). Leaves the `approver` as the acting operator.
 * Used by the retire / reopen / reference-integrity tests, which start from `active`.
 */
function productReferenceConsoleActive(Operator $creator, Operator $reviewer, Operator $approver, int $variantId, int $formatId): ProductReference
{
    actingAs($creator, 'operator');
    $reference = productReferenceConsoleDraft($variantId, $formatId);
    actingAs($reviewer, 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);
    actingAs($approver, 'operator');
    app(ActivateProductReference::class)->handle($reference);

    return $reference->refresh();
}

it('creates a draft Product Reference under a Variant + Format through the console, recording one ProductReferenceCreated with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // Two parents to select. Create does NOT gate on parent state — the cascade gate is an ACTIVATE-time rule,
    // so draft parents are valid for creation.
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();

    Livewire::test(CreateProductReferencePage::class)
        ->fillForm([
            'product_variant_id' => $variant->id,
            'format_id' => $format->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // The write routed through the action: a draft PR referencing both selected parents.
    $reference = ProductReference::query()
        ->where('product_variant_id', $variant->id)
        ->where('format_id', $format->id)
        ->sole();

    expect($reference->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($reference->product_variant_id)->toEqual($variant->id)
        ->and($reference->format_id)->toEqual($format->id);

    // Exactly one ProductReferenceCreated, carrying the operator audit envelope (newco_ops + the operator id)
    // resolved by the action from the `operator` guard — the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', 'ProductReferenceCreated')->sole();

    expect($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id)
        ->and($event->entity_type)->toBe('ProductReference')
        ->and($event->entity_id)->toBe((string) $reference->id);
});

it('surfaces a duplicate (variant, format) pair as a localized console form error, recording nothing new', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();

    // A pre-existing PR holding the (variant, format) identity (BR-Identity-3).
    $existing = productReferenceConsoleDraft($variant->id, $format->id);

    expect(ProductReference::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceCreated')->count())->toBe(1);

    // Submitting the colliding pair through the console → the DB unique index throws a framework
    // UniqueConstraintViolationException (no domain message), which the Create page catches and re-raises as a
    // ValidationException carrying the CONSOLE-OWNED localized message — surfaced as a form error on the Variant
    // field. The exact rendered message must equal the console key, NOT a raw SQL string (design L5).
    Livewire::test(CreateProductReferencePage::class)
        ->fillForm([
            'product_variant_id' => $variant->id,
            'format_id' => $format->id,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'product_variant_id' => (string) __('operator_console.product_reference.duplicate_reference'),
        ]);

    // No second PR, no second event — the collision is rejected at the DB index and rolled back to the action's
    // savepoint; the first PR is untouched.
    expect(ProductReference::query()->count())->toBe(1)
        ->and(ProductReference::query()->sole()->id)->toBe($existing->id)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceCreated')->count())->toBe(1);
});

it('submits a draft Product Reference for review through the console, recording the submit audit row and no domain event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $reference = productReferenceConsoleDraft($variant->id, $format->id);
    expect($reference->lifecycle_state)->toBe(LifecycleState::Draft);

    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->callAction('submit')
        ->assertNotified((string) __('operator_console.product_reference.notifications.submitted'));

    // State advanced draft → reviewed via the domain action (the console never writes lifecycle_state).
    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // Exactly one submit audit row carrying the operator envelope + the lifecycle edge; submit is audit-only.
    $audit = AuditRecord::query()->where('action', 'catalog.product_reference.submitted')->sole();

    expect($audit->entity_type)->toBe('ProductReference')
        ->and($audit->entity_id)->toBe((string) $reference->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($operator->id)
        ->and($audit->before)->toBe(['lifecycle_state' => 'draft'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed']);

    // Event-silent: the only PR event remains the creation's (no *Activated, no *Reviewed).
    expect(DomainEvent::query()->where('name', 'ProductReferenceActivated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('entity_type', 'ProductReference')->count())->toBe(1);
});

it('records a console rejection with notes, keeping the Product Reference in reviewed and emitting no event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $reference = productReferenceConsoleDraft($variant->id, $format->id);
    app(SubmitProductReferenceForReview::class)->handle($reference);

    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->callAction('reject', ['notes' => 'Format does not match the listed bottle size.'])
        ->assertNotified((string) __('operator_console.product_reference.notifications.rejected'));

    // Stays reviewed — a rejection is a reviewed → reviewed decision (§ 4.3); there is no revert to draft.
    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // One rejection audit row carrying the decision + notes + the acting operator principal.
    $rejection = AuditRecord::query()->where('action', 'catalog.product_reference.rejected')->sole();
    $after = $rejection->after ?? []; // narrow the nullable jsonb; keys asserted order-independently (PG reorders)

    expect($rejection->entity_type)->toBe('ProductReference')
        ->and($rejection->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($rejection->actor_id)->toEqual($operator->id)
        ->and($after['lifecycle_state'] ?? null)->toBe('reviewed')
        ->and($after['decision'] ?? null)->toBe('rejected')
        ->and($after['notes'] ?? null)->toBe('Format does not match the listed bottle size.');

    expect(DomainEvent::query()->where('name', 'ProductReferenceActivated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('exposes an activate action carrying the localized "second actor required" affordance and no cascade-retire action', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $reference = productReferenceConsoleDraft($variant->id, $format->id);

    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        // The five uniform lifecycle actions are present …
        ->assertActionExists('submit')
        ->assertActionExists('reject')
        ->assertActionExists('retire')
        ->assertActionExists('reopen')
        // … activate SURFACES the separation-of-duties floor as a confirmation affordance (design L4): a
        // distinct approver is required — the console reminds, it never re-checks.
        ->assertActionExists('activate', fn (Action $action): bool => $action->isConfirmationRequired()
            && $action->getModalDescription() === (string) __('operator_console.product_reference.affordance.second_actor'))
        // … and NO cascade-retire affordance exists for a spine entity (Master-only, scope guard).
        ->assertActionDoesNotExist('retireCascade');
});

it('activates a reviewed Product Reference under active parents through the console when a distinct approver acts', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // BOTH parents are `active`, and three DISTINCT operators satisfy the default Creator → Reviewer → Approver
    // floor — both activation preconditions (the two-parent cascade gate + the SoD floor) are met.
    $variant = productReferenceConsoleActiveVariant();
    $format = productReferenceConsoleActiveFormat();

    actingAs($creator, 'operator');
    $reference = productReferenceConsoleDraft($variant->id, $format->id);

    actingAs($reviewer, 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);

    actingAs($approver, 'operator');
    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_reference.notifications.activated'));

    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Exactly one ProductReferenceActivated carrying the operator envelope — actor_role newco_ops + the APPROVER
    // (not the creator/reviewer) as actor_id.
    $event = DomainEvent::query()->where('name', 'ProductReferenceActivated')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('ProductReference')
        ->and($event->entity_id)->toBe((string) $reference->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id);
});

it('surfaces a cascade-gate-blocked activate (a parent Format not active) as a danger notification, leaving the Product Reference reviewed', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // The Variant is active but the Format is NOT (factory-reviewed). Three DISTINCT operators satisfy the SoD
    // floor, so it is the activation-CASCADE gate — not the approval governance — that blocks the activate: a PR
    // may activate only once BOTH its parents (Variant AND Format) are `active`. The console SURFACES the
    // domain's ActivationCascadeViolation (catalog.gate.parent_not_active) and re-checks the parents NOTHING
    // (design L4).
    $variant = productReferenceConsoleActiveVariant();
    $format = Format::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    actingAs($creator, 'operator');
    $reference = productReferenceConsoleDraft($variant->id, $format->id);

    actingAs($reviewer, 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);

    actingAs($approver, 'operator');
    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_reference.notifications.action_failed'));

    // Unchanged: still reviewed (the gate rolled the transition back), NO ProductReferenceActivated event, and NO
    // activation audit row.
    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceActivated')->count())->toBe(0)
        ->and(AuditRecord::query()->where('action', 'catalog.product_reference.activated')->count())->toBe(0);
});

it('surfaces a self-approval governance rejection as a danger notification, leaving the Product Reference reviewed', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    // Both parents are active, so the cascade gate would clear — it is the SoD floor that must block here.
    $variant = productReferenceConsoleActiveVariant();
    $format = productReferenceConsoleActiveFormat();

    actingAs($creator, 'operator');
    $reference = productReferenceConsoleDraft($variant->id, $format->id);

    actingAs($reviewer, 'operator');
    app(SubmitProductReferenceForReview::class)->handle($reference);

    // The reviewer (who performed the prior governance step) attempts the approval — the domain rejects the
    // self-approval; the console SURFACES it as a danger notification and never re-checks the floor (design L4).
    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_reference.notifications.action_failed'));

    // Unchanged — still reviewed, NO activation event, NO activation audit row (the action's txn rolled back).
    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceActivated')->count())->toBe(0)
        ->and(AuditRecord::query()->where('action', 'catalog.product_reference.activated')->count())->toBe(0);
});

it('retires an active Product Reference through the console, recording one ProductReferenceRetired with the operator envelope', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $variant = productReferenceConsoleActiveVariant();
    $format = productReferenceConsoleActiveFormat();
    $reference = productReferenceConsoleActive($creator, $reviewer, $approver, $variant->id, $format->id);
    expect($reference->lifecycle_state)->toBe(LifecycleState::Active);

    // Retire THROUGH THE CONSOLE. No active SKU references this PR, so the reference-integrity gate clears.
    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.product_reference.notifications.retired'));

    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    $event = DomainEvent::query()->where('name', 'ProductReferenceRetired')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('ProductReference')
        ->and($event->entity_id)->toBe((string) $reference->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id);
});

it('surfaces a retire blocked by an active Sellable SKU reference as a danger notification, leaving the Product Reference active', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $variant = productReferenceConsoleActiveVariant();
    $format = productReferenceConsoleActiveFormat();
    $reference = productReferenceConsoleActive($creator, $reviewer, $approver, $variant->id, $format->id);

    // An active Sellable SKU references this PR (its product key). The factory bypasses the Create action — no
    // SellableSKUCreated event — and auto-builds the SKU's Case Configuration.
    $sku = SellableSku::factory()->create([
        'product_reference_id' => $reference->id,
        'lifecycle_state' => LifecycleState::Active,
    ]);

    $retireEventsBefore = DomainEvent::query()->where('name', 'ProductReferenceRetired')->count();

    // Retire THROUGH THE CONSOLE → the domain's reference-integrity gate rejects it; the console surfaces the
    // rejection as a danger notification (it never pre-checks open references — design L4).
    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.product_reference.notifications.action_failed'));

    // Unchanged: still active (the gate rolled the transition back), NO ProductReferenceRetired recorded — the
    // open SKU is still active and unaffected.
    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceRetired')->count())->toBe($retireEventsBefore);
});

it('surfaces a retire blocked by an active Composite SKU constituent as a danger notification, leaving the Product Reference active', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $variant = productReferenceConsoleActiveVariant();
    $format = productReferenceConsoleActiveFormat();
    $reference = productReferenceConsoleActive($creator, $reviewer, $approver, $variant->id, $format->id);

    // An active Composite SKU bundles this PR as a constituent (the within-module junction). hasAttached runs
    // before the factory's afterCreating (which auto-attaches two PRs only when the bundle is empty), so this PR
    // is the bundle's sole constituent here — enough for the reference-integrity gate's whereHas. No
    // CompositeSKUCreated event (the factory bypasses the Create action).
    $composite = CompositeSku::factory()
        ->hasAttached($reference, ['position' => 1], 'constituents')
        ->create(['lifecycle_state' => LifecycleState::Active]);

    $retireEventsBefore = DomainEvent::query()->where('name', 'ProductReferenceRetired')->count();

    // Retire THROUGH THE CONSOLE → blocked by the active Composite SKU constituent reference; surfaced as a
    // danger notification.
    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.product_reference.notifications.action_failed'));

    // Unchanged: still active, NO ProductReferenceRetired recorded — the open Composite SKU is unaffected.
    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceRetired')->count())->toBe($retireEventsBefore);
});

it('reopens a retired Product Reference to reviewed through the console (audit-only, no event)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $variant = productReferenceConsoleActiveVariant();
    $format = productReferenceConsoleActiveFormat();
    $reference = productReferenceConsoleActive($creator, $reviewer, $approver, $variant->id, $format->id);
    app(RetireProductReference::class)->handle($reference);
    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    $eventsBeforeReopen = DomainEvent::query()->count();

    // Reopen THROUGH THE CONSOLE → retired → reviewed.
    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->callAction('reopen')
        ->assertNotified((string) __('operator_console.product_reference.notifications.reopened'));

    // Back to `reviewed`, AUDIT-ONLY: reopen recorded NO new domain event (the event total is unchanged). A
    // subsequent activate would re-check the activation-cascade gate (the same Action runs).
    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->count())->toBe($eventsBeforeReopen);

    // One reopen audit row carrying the operator envelope + the lifecycle edge.
    $reopen = AuditRecord::query()->where('action', 'catalog.product_reference.reopened')->sole();
    expect($reopen->entity_type)->toBe('ProductReference')
        ->and($reopen->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($reopen->actor_id)->toEqual($approver->id)
        ->and($reopen->before)->toBe(['lifecycle_state' => 'retired'])
        ->and($reopen->after)->toBe(['lifecycle_state' => 'reviewed']);
});

it('surfaces an out-of-state retire as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft PR: retire requires `active`, so the domain rejects the out-of-state call. The console surfaces it
    // as a danger notification; it never pre-checks the from-state (design L4).
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $reference = productReferenceConsoleDraft($variant->id, $format->id);

    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.product_reference.notifications.action_failed'));

    // Unchanged: still draft, and no ProductReferenceRetired recorded (the rejected attempt's txn rolled back).
    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'ProductReferenceRetired')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Task 4.2 (catalog-review-freshness-resubmit) — the visibility-gated re-submit header action
|--------------------------------------------------------------------------
|
| The review-freshness re-arm on the Product Reference console (RM-06 / canon MVP-DEC-019; design D5) — the same
| visibility-gated re-submit the Product Master console gained in task 4.1, now on every spine console. Re-submit
| routes through the shared kit's lifecycleAction factory to ResubmitProductReferenceForReview (never an Eloquent
| write); its ->visible() is gated to the DERIVED rejection-pending read
| (OperatorConsoleViewRecord::isRejectionPending) — OFFERED only while an un-remediated rejection blocks
| activation, HIDDEN otherwise. A ->visible()-false action is undrivable via test helpers, so the gating is proven
| with assertActionHidden/assertActionVisible and the re-arm is driven while re-submit IS visible (lessons.md
| 2026-06-23/24). Submit/reject never gate on parent state (only activate does), so draft parents suffice.
*/

it('offers re-submit on the Product Reference console only when rejection-pending, re-arming review when driven', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // Draft parents to satisfy the FKs — create/submit/reject never gate on parent state (the cascade gate is an
    // ACTIVATE-time rule), and this flow never activates.
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $reference = productReferenceConsoleDraft($variant->id, $format->id);
    app(SubmitProductReferenceForReview::class)->handle($reference);

    // Fresh `reviewed` (never rejected): the derived rejection-pending read is false, so a redundant re-submit is
    // NOT offered — the action is HIDDEN (design D5; OperatorConsoleViewRecord::isRejectionPending).
    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->assertActionHidden('resubmit');

    // A rejection (through the console) makes it rejection-pending — its latest governance action ends in
    // `.rejected` — so on a fresh mount re-submit is VISIBLE.
    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->callAction('reject', ['notes' => 'The Variant and Format pairing needs review.']);

    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->assertActionVisible('resubmit')
        ->callAction('resubmit')
        ->assertNotified((string) __('operator_console.product_reference.notifications.resubmitted'));

    // Re-arm is state-preserving (reviewed → reviewed, audit-only) and clears the pending flag, so on a fresh
    // mount re-submit is HIDDEN again (the latest governance action is now `.resubmitted`, not `.rejected`) — the
    // write-through routed to ResubmitProductReferenceForReview with the ProductReference label, else the derived
    // read would still see the `.rejected` as latest and keep re-submit visible.
    expect(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->assertActionHidden('resubmit');
});
