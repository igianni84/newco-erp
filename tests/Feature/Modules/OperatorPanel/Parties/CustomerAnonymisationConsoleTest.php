<?php

// Task 6.1 (parties-anonymisation; design D1/D5; console kit ADR 2026-06-19 + 2026-06-20 + 2026-06-21) — the
// Customer console's GDPR data-rights WRITE surface on ViewCustomer. The two form-less write-through verbs the
// page APPENDS to its SurfacesDomainActions-built header-action array: `anonymise` (write-through to
// AnonymiseCustomer — the right-to-erasure) and `export` (write-through to the read-only ExportCustomerData — the
// right-of-access). Each routes through a Parties domain action by the customer id and NEVER writes the model
// itself (the no-Eloquent-write rule, ADR 2026-06-19); the console SURFACES the domain's decision.
//
// `anonymise` IS VISIBILITY-GATED to a not-yet-anonymised Customer (design D1): visible iff `anonymised_at` is
// NULL, hidden once set (the IDEMPOTENCY gate — an already-erased Customer is a domain no-op). CRUCIALLY — unlike
// the KYC/Account verbs whose visibility predicate is the EXACT COMPLEMENT of the domain guard — this gate is NOT
// the complement of the domain's rejection: a `compliance`-Hold block is a RUNTIME rejection
// (AnonymisationBlockedByComplianceHold, a RuntimeException), so a not-yet-anonymised but `compliance`-held
// Customer keeps `anonymise` VISIBLE and its block surfaces as `action_failed` on click (the `activate`
// cross-slice-gate precedent — design D5, cf. CustomerLifecycleConsoleTest's gate-unmet activate). `export` is
// UNGATED — an anonymised Customer still exports (its access-export reflects the placeholder PII); the in-memory
// payload is assembled + discarded by the surface (the file/download vehicle is the deferred J-9b follow-up —
// design D5), the click confirming via the kit's success notification.
//
// DatabaseMigrations (mirroring CustomerLifecycleConsoleTest + CustomerKycSanctionsConsoleTest): the anonymise
// console action drives a real domain action opening its OWN DB::transaction, so the in-transaction event append
// commits for real (RefreshDatabase would wrap every write in a never-committed outer transaction). The factory
// bypasses the actions → records no event, co-provisions no Account/Profile/Address. Parties enums/models/events
// are imported freely here: the {Models, Actions, Enums} import-boundary carve-out governs OperatorPanel
// PRODUCTION code, not tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ViewCustomer;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Events\CustomerAnonymised;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Modules\Parties\Support\AnonymisedPlaceholders;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('shows anonymise only while not yet anonymised — hidden once anonymised_at is set (design D1)', function (bool $alreadyAnonymised, bool $visible) {
    actingAs(Operator::factory()->create(), 'operator');

    // The gate is `anonymised_at IS NULL` — the idempotency gate, NOT a status/from-state guard (anonymisation is
    // orthogonal to the status FSM). A not-yet-anonymised Customer shows the verb; an already-anonymised one hides it.
    $customer = Customer::factory()->create($alreadyAnonymised ? ['anonymised_at' => now()] : []);

    $component = Livewire::test(ViewCustomer::class, ['record' => $customer->id]);

    if ($visible) {
        $component->assertActionVisible('anonymise');
    } else {
        $component->assertActionHidden('anonymise');
    }
})->with([
    'not yet anonymised → visible' => [false, true],
    'already anonymised → hidden' => [true, false],
]);

it('exposes anonymise + export as form-less verbs with no confirmation; export is ungated (design D1/D5)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A fresh (never-anonymised) Customer: both verbs are present and VISIBLE, each form-less and carrying NO
    // confirmation affordance (the Customer FSM has no separation-of-duties floor — design D3, the status-verb
    // precedent). `export` is ungated; `anonymise` is visible here because `anonymised_at` is NULL.
    $customer = Customer::factory()->create();

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->assertActionExists('anonymise')
        ->assertActionExists('export')
        ->assertActionExists('anonymise', fn (Action $action): bool => ! $action->isConfirmationRequired())
        ->assertActionExists('export', fn (Action $action): bool => ! $action->isConfirmationRequired())
        ->assertActionVisible('anonymise')
        ->assertActionVisible('export');
});

it('anonymises an un-held Customer through the console — PII overwritten, anonymised_at set, one CustomerAnonymised with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // A fresh, un-held, not-yet-anonymised Customer: `anonymise` is VISIBLE (anonymised_at NULL) and — no `compliance`
    // Hold covering — the domain proceeds. The factory bypasses CreateCustomer → records no event and co-provisions no
    // Account/Profile/Address, so the verb's CustomerAnonymised is the only event.
    $customer = Customer::factory()->create();

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        // callAction asserts-visible-first, then drives the form-less verb into AnonymiseCustomer by the customer id —
        // the console writes nothing itself (the no-Eloquent-write rule).
        ->callAction('anonymise')
        ->assertNotified((string) __('operator_console.customer.notifications.anonymised'));

    // The PII is overwritten in place with the deterministic id-derived placeholder and `anonymised_at` is stamped
    // (the domain action's overwrite — the console never writes the model). The status FSM is untouched (orthogonal).
    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->anonymised_at)->not->toBeNull()
        ->and($fresh->email)->toBe(AnonymisedPlaceholders::for($customer->id)->email);

    // Exactly one CustomerAnonymised, carrying the operator audit envelope (newco_ops + the operator id) resolved by
    // the action from the `operator` guard — the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', CustomerAnonymised::NAME)->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Customer')
        ->and($event->entity_id)->toBe((string) $customer->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id);  // loose: PG returns a numeric string for the bigint
});

it('surfaces a compliance-Hold-blocked anonymise as a danger notification, changing nothing and recording no event (design D2)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A not-yet-anonymised Customer covered by an active Customer-scope `compliance` Hold. The Hold is placed via the
    // BARE factory (bypasses PlaceHold's suspend coupling → the un-suspended Customer isolates the gate read;
    // DatabaseComplianceStatusReader reads parties_holds directly). The factory records NO event, so the event log
    // starts — and must stay — empty.
    $customer = Customer::factory()->create();
    $originalEmail = $customer->email;
    Hold::factory()->create([
        'hold_type' => HoldType::Compliance,
        'status' => HoldStatus::Active,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
    ]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        // `anonymise` is VISIBLE (anonymised_at is NULL) — the `compliance` block is a RUNTIME rejection, NOT a
        // visibility gate (the contrast with the KYC verbs — design D5). callAction asserts-visible-first, drives the
        // verb, and the domain throws AnonymisationBlockedByComplianceHold (a RuntimeException) → the kit surfaces it.
        ->assertActionVisible('anonymise')
        ->callAction('anonymise')
        ->assertNotified((string) __('operator_console.customer.notifications.action_failed'));

    // Unchanged: not anonymised, the PII intact, and the rejected attempt recorded NO event (its transaction rolled
    // back before any write — the gate throws before the overwrite). The Hold factory records nothing → a clean zero.
    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->anonymised_at)->toBeNull()
        ->and($fresh->email)->toBe($originalEmail)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('exports Customer data through the console — a success notification, the Customer unchanged, no event, no mutation (design D5)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // `export` is a READ-ONLY write-through: it assembles the in-memory access payload and returns it (the payload is
    // discarded by the surface — the delivery vehicle is the deferred J-9b follow-up). The console confirms via the
    // kit's success notification and mutates nothing.
    $customer = Customer::factory()->create();
    $originalEmail = $customer->email;

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('export')
        ->assertNotified((string) __('operator_console.customer.notifications.exported'));

    // Read-only: the Customer is untouched and the export records NO domain event (ExportCustomerData has no recorder;
    // it never mutates — design D5).
    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->email)->toBe($originalEmail)
        ->and($fresh->anonymised_at)->toBeNull()
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('keeps export available on an anonymised Customer while anonymise is hidden — the access-export reflects placeholder PII (design D1/D5)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // An already-anonymised Customer: `anonymise` is HIDDEN (the idempotency gate — a domain no-op there), but
    // `export` stays VISIBLE and drivable (ungated — the right-of-access is not extinguished by erasure; the payload
    // reflects the deterministic placeholder PII the erasure wrote, proven at the action level in CustomerDataExportTest).
    $customer = Customer::factory()->create(['anonymised_at' => now()]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->assertActionHidden('anonymise')
        ->assertActionVisible('export')
        ->callAction('export')
        ->assertNotified((string) __('operator_console.customer.notifications.exported'));

    // The export mutated nothing — the Customer stays anonymised and no event was recorded.
    expect(Customer::findOrFail($customer->id)->anonymised_at)->not->toBeNull()
        ->and(DomainEvent::query()->count())->toBe(0);
});
