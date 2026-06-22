<?php

// Task 3.1 / 3.2 (operator-console-parties-customer; design D1/D3/D4/D5/D8; ADR 2026-06-19 + 2026-06-20 +
// 2026-06-21) — the Customer console's write-through STATUS surface. These pin the four status verbs (activate
// `pending → active`, suspend `active → suspended`, reactivate `suspended → active`, close
// `active | suspended → closed`) the ViewCustomer page assembles via the SurfacesDomainActions trait — NOT the
// catalog OperatorConsoleViewRecord base (design D1/D8), so the five catalog governance verbs (submit/reject/
// reopen), the compliance Hold verbs (placeHold/liftHold) and the KYC verb (requireKyc) are deliberately ABSENT
// (scope guard — each is its own future slice). Each verb routes through a Parties domain action by the customer
// id (design D4) and NEVER writes `status` itself (the no-Eloquent-write rule); the console SURFACES the domain's
// decision — an out-of-state transition becomes the `action_failed` danger notification. The Customer FSM has no
// separation-of-duties floor, so every verb is form-less and carries NO confirmation affordance (design D3).
//
// ACTIVATION IS CROSS-SLICE-GATED (design D5): ActivateCustomer guards a composite onboarding gate — email-
// verified + T&C/privacy accepted + sanctions passed + KYC-cleared-if-required — that THIS slice sets none of. A
// gate-MET pending Customer is seeded with the acceptance timestamps + sanctions=passed (kyc_required left null →
// the KYC rider clears); a fresh factory Customer is gate-UNMET (no acceptances, NULL sanctions) and its activate
// rejects gracefully (a danger notification, no event) — correct surface-ahead-of-drivers behaviour, not a bug.
//
// DatabaseMigrations (mirroring ProducerLifecycleConsoleTest): each console action drives a real domain action
// that opens its OWN DB::transaction, so the DomainEventRecorder's transaction-level guard sees a real commit
// (RefreshDatabase would wrap every write in a never-committed outer transaction). The factory bypasses the
// actions, records NO event and co-provisions NO Account/Profile — so the only events are the console verbs'
// (and a profile-less Customer keeps suspend/reactivate cascade-silent). Parties enums/models are imported freely
// here: the {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ViewCustomer;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('activates a gate-met pending Customer through the console, recording one CustomerActivated with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // A gate-MET `pending` Customer: the three onboarding acceptances set, sanctions `passed`, `kyc_required` left
    // NULL so the KYC rider short-circuits (DEC-071). The factory bypasses CreateCustomer → it records no event
    // and co-provisions no Account, so the activate verb's CustomerActivated is the only event.
    $customer = Customer::factory()->create([
        'email_verified_at' => now(),
        'tc_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'sanctions_status' => SanctionsStatus::Passed,
    ]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.customer.notifications.activated'));

    // State advanced pending → active via the domain action (the console never writes `status`).
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Active);

    // Exactly one CustomerActivated, carrying the operator audit envelope (newco_ops + the operator id) resolved
    // by the action from the `operator` guard — the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', 'CustomerActivated')->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Customer')
        ->and($event->entity_id)->toBe((string) $customer->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id);  // loose: PG returns a numeric string for the bigint
});

it('surfaces a gate-unmet activate as a danger notification, changing nothing and recording no event', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A fresh factory Customer is `pending` but gate-UNMET (no acceptance timestamps, NULL sanctions_status), so
    // the composite onboarding gate blocks activation. The console surfaces the rejection; it never sets gate
    // columns or pre-checks the gate itself (design D5 — surface ahead of its drivers, not a defect).
    $customer = Customer::factory()->create();

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.customer.notifications.action_failed'));

    // Unchanged: still pending, and the rejected attempt recorded NO event (its transaction rolled back).
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('suspends an active Customer through the console, recording one CustomerSuspended with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('suspend')
        ->assertNotified((string) __('operator_console.customer.notifications.suspended'));

    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Suspended);

    // A profile-less Customer keeps the suspension cascade silent — exactly one CustomerSuspended, no cascade
    // ProfileSuspended noise (the cascade is the Action's behaviour, covered by parties-core).
    $event = DomainEvent::query()->where('name', 'CustomerSuspended')->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Customer')
        ->and($event->entity_id)->toBe((string) $customer->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id);
});

it('reactivates a suspended Customer through the console, recording one CustomerReactivated with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $customer = Customer::factory()->create(['status' => CustomerStatus::Suspended]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('reactivate')
        ->assertNotified((string) __('operator_console.customer.notifications.reactivated'));

    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Active);

    $event = DomainEvent::query()->where('name', 'CustomerReactivated')->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Customer')
        ->and($event->entity_id)->toBe((string) $customer->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id);
});

it('closes an active Customer through the console, recording one CustomerClosed with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('close')
        ->assertNotified((string) __('operator_console.customer.notifications.closed'));

    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Closed);

    $event = DomainEvent::query()->where('name', 'CustomerClosed')->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Customer')
        ->and($event->entity_id)->toBe((string) $customer->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id);
});

it('surfaces an out-of-state status verb as a danger notification, changing nothing', function (string $verb, CustomerStatus $seedStatus) {
    actingAs(Operator::factory()->create(), 'operator');

    // Each verb is reachable only from its documented from-state (§ 4.1): suspend needs `active`, reactivate needs
    // `suspended`, close needs `active|suspended`. Seeded out-of-state, the domain rejects the call before any
    // write; the console surfaces it as a danger notification without pre-checking the from-state (design D5).
    $customer = Customer::factory()->create(['status' => $seedStatus]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction($verb)
        ->assertNotified((string) __('operator_console.customer.notifications.action_failed'));

    // Unchanged: the from-state held, and the rejected attempt recorded NO event (its transaction rolled back).
    expect(Customer::findOrFail($customer->id)->status)->toBe($seedStatus)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'suspend a pending Customer (needs active)' => ['suspend', CustomerStatus::Pending],
    'reactivate an active Customer (needs suspended)' => ['reactivate', CustomerStatus::Active],
    'close a pending Customer (needs active|suspended)' => ['close', CustomerStatus::Pending],
]);

it('exposes only the four form-less status verbs and none of the catalog governance, Hold, or KYC verbs', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $customer = Customer::factory()->create();

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        // The four Customer status verbs are present …
        ->assertActionExists('activate')
        ->assertActionExists('suspend')
        ->assertActionExists('reactivate')
        ->assertActionExists('close')
        // … each form-less and carrying NO confirmation affordance — the Customer FSM has no separation-of-duties
        // floor (design D3) …
        ->assertActionExists('activate', fn (Action $action): bool => ! $action->isConfirmationRequired())
        ->assertActionExists('suspend', fn (Action $action): bool => ! $action->isConfirmationRequired())
        ->assertActionExists('reactivate', fn (Action $action): bool => ! $action->isConfirmationRequired())
        ->assertActionExists('close', fn (Action $action): bool => ! $action->isConfirmationRequired())
        // … none of the catalog governance verbs leak in (this page is NOT OperatorConsoleViewRecord — design
        // D1/D8) …
        ->assertActionDoesNotExist('submit')
        ->assertActionDoesNotExist('reject')
        ->assertActionDoesNotExist('reopen')
        // … no Hold verbs (the Hold-mediated path is the compliance slice's surface — design D4 / Non-Goals) …
        ->assertActionDoesNotExist('placeHold')
        ->assertActionDoesNotExist('liftHold')
        // … and no KYC verb (the compliance slice's surface).
        ->assertActionDoesNotExist('requireKyc');
});
