<?php

// Task 3.1 (operator-console-catalog-spine; design L1/L3/L4; ADR 2026-06-19 + 2026-06-20; spec — Operator
// creates / advances / surfaces the activation-cascade gate / retires & reopens the Product Variant through the
// console). The Product Variant is the FIRST hierarchical spine entity: its create form binds exactly ONE parent
// Product Master, and its activation is gated on that Master being `active` (the within-catalog activation
// cascade). These pin the console's write-through surface, all built as PURE reuse of the kit (tasks 1.1/1.2):
// the create page routes the form into CreateProductVariant, and the view page's five uniform lifecycle actions
// (submit · reject · activate · retire · reopen) each route to the matching Catalog domain action through the
// shared surfaceLifecycleOutcome helper. The console NEVER writes lifecycle_state itself (the no-Eloquent-write
// rule, task 1.2) and SURFACES the domain's decision — the from-state guard, the Creator → Reviewer → Approver
// separation-of-duties floor, AND (new for the hierarchical entities) the activation-cascade gate (a Variant
// cannot activate under a non-active Master) — it reimplements none of them (design L4). A Variant binds NO
// producer (no producer gate) and has NO cascade-retire affordance (Master-only, scope guard); its single-entity
// retire PRESERVES existing active children (only new activation under the now-retired Variant is prevented).
// Submit/reject/reopen are event-silent audit checkpoints (Module 0 PRD § 14.2); activate/retire record
// ProductVariantActivated/Retired; create records ProductVariantCreated.
//
// DatabaseMigrations (mirroring the Master/Format/Case Configuration console tests): each console action drives a
// real domain action that opens its OWN DB::transaction, so the recorders' transaction-level guards see a real
// commit (level 0 → 1 → 0) — the faithful production shape (RefreshDatabase would wrap every write in a
// never-committed outer transaction). Catalog enums/models/actions are imported freely here: the
// {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\Catalog\Actions\ActivateProductVariant;
use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Actions\RetireProductVariant;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages\CreateProductVariant as CreateProductVariantPage;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages\ViewProductVariant;
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
 * A parent Product Master in `active`, stood up directly through the factory — the factory bypasses
 * CreateProductMaster, so it records NO event (the activation cascade reads only the parent's `lifecycle_state`,
 * so a factory-active Master is a legitimate fixture) and the count of operator-driven Variant events stays
 * clean. Used by the success-activate path.
 */
function productVariantConsoleActiveMaster(string $name = 'Active Parent Château'): ProductMaster
{
    return ProductMaster::factory()->create(['name' => $name, 'lifecycle_state' => LifecycleState::Active]);
}

/**
 * A draft Product Variant created through the real Catalog action as the currently-acting operator (records
 * ProductVariantCreated, no audit row), under the given parent Master. Distinctly named (`productVariantConsole`
 * prefix) to avoid colliding with the Catalog lifecycle test's helpers (one shared Pest function namespace).
 */
function productVariantConsoleDraft(int $masterId, string $identifier = 'GRAND-CRU-2019'): ProductVariant
{
    return app(CreateProductVariant::class)->handle(productMasterId: $masterId, variantIdentifier: $identifier);
}

/**
 * Stand a Product Variant up in `active` under an ACTIVE parent Master through the real domain chain with three
 * DISTINCT operators (the default Creator → Reviewer → Approver floor). Leaves the `approver` as the acting
 * operator. Used by the retire / reopen tests, which start from `active`.
 */
function productVariantConsoleActive(Operator $creator, Operator $reviewer, Operator $approver, int $masterId, string $identifier = 'GRAND-CRU-2019'): ProductVariant
{
    actingAs($creator, 'operator');
    $variant = productVariantConsoleDraft($masterId, $identifier);
    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);
    actingAs($approver, 'operator');
    app(ActivateProductVariant::class)->handle($variant);

    return $variant->refresh();
}

it('creates a draft Product Variant under a parent Master through the console, recording one ProductVariantCreated with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // A parent Master to select. Create does NOT gate on parent state — the cascade gate is an ACTIVATE-time
    // rule, so a draft Master is a valid parent for creation.
    $master = ProductMaster::factory()->create();

    Livewire::test(CreateProductVariantPage::class)
        ->fillForm([
            'product_master_id' => $master->id,
            'variant_identifier' => 'GRAND-CRU-2019',
            'vintage_year' => 2019,
            'non_vintage' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // The write routed through the action: a draft Variant referencing the selected parent, with its 1:1 WINE
    // attribute set persisted.
    $variant = ProductVariant::query()->where('variant_identifier', 'GRAND-CRU-2019')->sole();

    expect($variant->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($variant->product_master_id)->toEqual($master->id)
        ->and($variant->wineAttributes?->vintage_year)->toBe(2019)
        ->and($variant->wineAttributes?->non_vintage)->toBeFalse();

    // Exactly one ProductVariantCreated, carrying the operator audit envelope (newco_ops + the operator id)
    // resolved by the action from the `operator` guard — the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', 'ProductVariantCreated')->sole();

    expect($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id)
        ->and($event->entity_type)->toBe('ProductVariant')
        ->and($event->entity_id)->toBe((string) $variant->id);
});

it('submits a draft Product Variant for review through the console, recording the submit audit row and no domain event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $master = ProductMaster::factory()->create();
    $variant = productVariantConsoleDraft($master->id);
    expect($variant->lifecycle_state)->toBe(LifecycleState::Draft);

    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->callAction('submit')
        ->assertNotified((string) __('operator_console.product_variant.notifications.submitted'));

    // State advanced draft → reviewed via the domain action (the console never writes lifecycle_state).
    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // Exactly one submit audit row carrying the operator envelope + the lifecycle edge; submit is audit-only.
    $audit = AuditRecord::query()->where('action', 'catalog.product_variant.submitted')->sole();

    expect($audit->entity_type)->toBe('ProductVariant')
        ->and($audit->entity_id)->toBe((string) $variant->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($operator->id)
        ->and($audit->before)->toBe(['lifecycle_state' => 'draft'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed']);

    // Event-silent: the only Variant event remains the creation's (no *Activated, no *Reviewed).
    expect(DomainEvent::query()->where('name', 'ProductVariantActivated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('entity_type', 'ProductVariant')->count())->toBe(1);
});

it('records a console rejection with notes, keeping the Product Variant in reviewed and emitting no event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $master = ProductMaster::factory()->create();
    $variant = productVariantConsoleDraft($master->id);
    app(SubmitProductVariantForReview::class)->handle($variant);

    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->callAction('reject', ['notes' => 'Vintage year does not match the release label.'])
        ->assertNotified((string) __('operator_console.product_variant.notifications.rejected'));

    // Stays reviewed — a rejection is a reviewed → reviewed decision (§ 4.3); there is no revert to draft.
    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // One rejection audit row carrying the decision + notes + the acting operator principal.
    $rejection = AuditRecord::query()->where('action', 'catalog.product_variant.rejected')->sole();
    $after = $rejection->after ?? []; // narrow the nullable jsonb; keys asserted order-independently (PG reorders)

    expect($rejection->entity_type)->toBe('ProductVariant')
        ->and($rejection->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($rejection->actor_id)->toEqual($operator->id)
        ->and($after['lifecycle_state'] ?? null)->toBe('reviewed')
        ->and($after['decision'] ?? null)->toBe('rejected')
        ->and($after['notes'] ?? null)->toBe('Vintage year does not match the release label.');

    expect(DomainEvent::query()->where('name', 'ProductVariantActivated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('exposes an activate action carrying the localized "second actor required" affordance and no cascade-retire action', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $master = ProductMaster::factory()->create();
    $variant = productVariantConsoleDraft($master->id);

    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        // The five uniform lifecycle actions are present …
        ->assertActionExists('submit')
        ->assertActionExists('reject')
        ->assertActionExists('retire')
        ->assertActionExists('reopen')
        // … activate SURFACES the separation-of-duties floor as a confirmation affordance (design L4): a
        // distinct approver is required — the console reminds, it never re-checks.
        ->assertActionExists('activate', fn (Action $action): bool => $action->isConfirmationRequired()
            && $action->getModalDescription() === (string) __('operator_console.product_variant.affordance.second_actor'))
        // … and NO cascade-retire affordance exists for a spine entity (Master-only, scope guard).
        ->assertActionDoesNotExist('retireCascade');
});

it('activates a reviewed Product Variant under an active Master through the console when a distinct approver acts', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // The parent Master is `active`, and three DISTINCT operators satisfy the default Creator → Reviewer →
    // Approver floor — both activation preconditions (the cascade gate + the SoD floor) are met.
    $master = productVariantConsoleActiveMaster();

    actingAs($creator, 'operator');
    $variant = productVariantConsoleDraft($master->id);

    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);

    actingAs($approver, 'operator');
    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_variant.notifications.activated'));

    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // Exactly one ProductVariantActivated carrying the operator envelope — actor_role newco_ops + the APPROVER
    // (not the creator/reviewer) as actor_id.
    $event = DomainEvent::query()->where('name', 'ProductVariantActivated')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('ProductVariant')
        ->and($event->entity_id)->toBe((string) $variant->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id);
});

it('surfaces a cascade-gate-blocked activate (parent Master not active) as a danger notification, leaving the Product Variant reviewed', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // The parent Master is NOT active (factory-reviewed). Three DISTINCT operators satisfy the SoD floor, so it
    // is the activation-CASCADE gate — not the approval governance — that blocks the activate: a child may
    // activate only once every parent it depends on is `active` (Variant ← Master). The console SURFACES the
    // domain's ActivationCascadeViolation (catalog.gate.parent_not_active) as a danger notification and
    // re-checks the parent NOTHING (design L4).
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    actingAs($creator, 'operator');
    $variant = productVariantConsoleDraft($master->id);

    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);

    actingAs($approver, 'operator');
    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_variant.notifications.action_failed'));

    // Unchanged: still reviewed (the gate rolled the transition back), NO ProductVariantActivated event, and NO
    // activation audit row.
    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductVariantActivated')->count())->toBe(0)
        ->and(AuditRecord::query()->where('action', 'catalog.product_variant.activated')->count())->toBe(0);
});

it('surfaces a self-approval governance rejection as a danger notification, leaving the Product Variant reviewed', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    // The parent Master is active, so the cascade gate would clear — it is the SoD floor that must block here.
    $master = productVariantConsoleActiveMaster();

    actingAs($creator, 'operator');
    $variant = productVariantConsoleDraft($master->id);

    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);

    // The reviewer (who performed the prior governance step) attempts the approval — the domain rejects the
    // self-approval; the console SURFACES it as a danger notification and never re-checks the floor (design L4).
    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.product_variant.notifications.action_failed'));

    // Unchanged — still reviewed, NO activation event, NO activation audit row (the action's txn rolled back).
    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductVariantActivated')->count())->toBe(0)
        ->and(AuditRecord::query()->where('action', 'catalog.product_variant.activated')->count())->toBe(0);
});

it('retires an active Product Variant through the console, recording one ProductVariantRetired with the operator envelope', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $master = productVariantConsoleActiveMaster();
    $variant = productVariantConsoleActive($creator, $reviewer, $approver, $master->id);
    expect($variant->lifecycle_state)->toBe(LifecycleState::Active);

    // Retire THROUGH THE CONSOLE. Retire carries only the operator floor, so any authenticated operator may
    // perform it; here the approver does. A Variant has no within-catalog reference-integrity block.
    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.product_variant.notifications.retired'));

    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    $event = DomainEvent::query()->where('name', 'ProductVariantRetired')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('ProductVariant')
        ->and($event->entity_id)->toBe((string) $variant->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id);
});

it('retires a Product Variant through the console while preserving its existing active Product Reference children', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $master = productVariantConsoleActiveMaster();
    $variant = productVariantConsoleActive($creator, $reviewer, $approver, $master->id);

    // An already-active child Product Reference under this Variant, stood up directly via the factory (no event,
    // no Create-action chain). A single-entity retire of a HIERARCHY PARENT preserves its existing active
    // children — only NEW activation under the now-retired Variant is prevented (§ 4.5 / BR-Lifecycle-4); the
    // six spine entities ship NO cascade-retire (Master-only). The factory auto-builds the child's Format.
    $childReference = ProductReference::factory()->create([
        'product_variant_id' => $variant->id,
        'lifecycle_state' => LifecycleState::Active,
    ]);

    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.product_variant.notifications.retired'));

    // The Variant retired; its existing active child Reference is UNAFFECTED (single-entity retire, not cascade).
    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(ProductReference::findOrFail($childReference->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductVariantRetired')->count())->toBe(1);
});

it('reopens a retired Product Variant to reviewed through the console (audit-only, no event)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $master = productVariantConsoleActiveMaster();
    $variant = productVariantConsoleActive($creator, $reviewer, $approver, $master->id);
    app(RetireProductVariant::class)->handle($variant);
    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    $eventsBeforeReopen = DomainEvent::query()->count();

    // Reopen THROUGH THE CONSOLE → retired → reviewed.
    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->callAction('reopen')
        ->assertNotified((string) __('operator_console.product_variant.notifications.reopened'));

    // Back to `reviewed`, AUDIT-ONLY: reopen recorded NO new domain event (the event total is unchanged). A
    // subsequent activate would re-check the activation-cascade gate (the same Action runs).
    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->count())->toBe($eventsBeforeReopen);

    // One reopen audit row carrying the operator envelope + the lifecycle edge.
    $reopen = AuditRecord::query()->where('action', 'catalog.product_variant.reopened')->sole();
    expect($reopen->entity_type)->toBe('ProductVariant')
        ->and($reopen->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($reopen->actor_id)->toEqual($approver->id)
        ->and($reopen->before)->toBe(['lifecycle_state' => 'retired'])
        ->and($reopen->after)->toBe(['lifecycle_state' => 'reviewed']);
});

it('surfaces an out-of-state retire as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft Variant: retire requires `active`, so the domain rejects the out-of-state call. The console
    // surfaces it as a danger notification; it never pre-checks the from-state (design L4).
    $master = ProductMaster::factory()->create();
    $variant = productVariantConsoleDraft($master->id);

    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.product_variant.notifications.action_failed'));

    // Unchanged: still draft, and no ProductVariantRetired recorded (the rejected attempt's txn rolled back).
    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'ProductVariantRetired')->count())->toBe(0);
});
