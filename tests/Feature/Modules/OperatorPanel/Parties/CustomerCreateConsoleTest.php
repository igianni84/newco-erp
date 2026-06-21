<?php

// Task 2.1 / 2.2 (operator-console-parties-customer; design D5/D6/D7; ADR 2026-06-19 + 2026-06-20 + 2026-06-21) —
// the Customer operator console's write-through Create surface, the FIRST demand-side Parties create page. These
// assertions pin the one law (the page NEVER saves the model; it routes the form into the Parties CreateCustomer
// action) and the audit envelope (a console-driven create records CustomerCreated with actor_role newco_ops + the
// operator id, resolved automatically from the `operator` guard via the platform ActorContext seam — the console
// builds no envelope). The create form constructs the PLATFORM Currency / SupportedLocale operands (design D6 — no
// Parties\Enums import, no boundary change) and the action co-provisions the 1:1 Account (event-silent) while
// creating NO Profile (design D7). The form never exposes `status`: a Customer is born `pending` (design D5).
//
// DatabaseMigrations (mirroring ClubCreateConsoleTest): the create flow drives a real domain action that opens its
// OWN DB::transaction inside Filament's create() transaction, so the DomainEventRecorder's in-transaction append
// commits for real (RefreshDatabase would wrap every write in a never-committed outer transaction). Parties
// enums/models/pages are imported freely here: the {Models, Actions, Enums} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests. A factory-built Customer bypasses CreateCustomer, records no event and
// co-provisions no Account — so the only recorded event is the console's CustomerCreated.

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\CreateCustomer;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('creates a pending Customer through the console, co-provisioning one active Account and recording one CustomerCreated with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    Livewire::test(CreateCustomer::class)
        ->fillForm([
            'email' => 'new.console.customer@example.test',
            'name' => 'New Console Customer',
            'preferred_currency' => 'EUR',
            'preferred_locale' => 'en',
            'phone' => '+39 02 1234567',
            'date_of_birth' => '1985-07-15',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // The write routed through the action: a Customer born `pending`, carrying the narrowed scalars and the
    // platform Currency/SupportedLocale operands persisted as their plain ISO-code / locale strings (design D9);
    // the optional phone + date_of_birth flowed through (date_of_birth parsed to an immutable date).
    $customer = Customer::query()->where('email', 'new.console.customer@example.test')->sole();

    expect($customer->status)->toBe(CustomerStatus::Pending)
        ->and($customer->name)->toBe('New Console Customer')
        ->and($customer->preferred_currency)->toBe('EUR')
        ->and($customer->preferred_locale)->toBe('en')
        ->and($customer->phone)->toBe('+39 02 1234567')
        ->and($customer->date_of_birth)->not->toBeNull();

    // Exactly one co-provisioned Account, born `active` — event-silent (no AccountCreated exists — design D7).
    $accounts = Account::query()->where('customer_id', $customer->id)->get();
    expect($accounts)->toHaveCount(1)
        ->and($accounts->first()?->status)->toBe(AccountStatus::Active);

    // A console-created Customer has ZERO Profiles — CreateCustomer co-provisions only the Account (design D7).
    expect($customer->profiles()->count())->toBe(0);

    // Exactly one CustomerCreated, carrying the operator audit envelope (newco_ops + the operator id) resolved by
    // the action from the `operator` guard — the console constructs no envelope itself. The Account leg records no
    // event (design D7), so CustomerCreated is the only event.
    $event = DomainEvent::query()->where('name', 'CustomerCreated')->sole();

    expect($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id)
        ->and($event->entity_type)->toBe('Customer')
        ->and($event->entity_id)->toBe((string) $customer->id);
});

it('surfaces DuplicateCustomerEmail on the email field for a colliding email, persisting no Customer, Account or event', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // An existing Customer holds the target email (factory-built → records no event, co-provisions no Account), so
    // the action's BR-K-Identity-1 uniqueness pre-check rejects the console's attempt.
    Customer::factory()->create(['email' => 'taken@example.test']);

    $accountsBefore = Account::query()->count();

    // A colliding email → the action throws DuplicateCustomerEmail (a RuntimeException), mapped by the kit base
    // catch to an `email` form error (createRejectionField) rather than an unhandled 500. The transaction rolls
    // back: no new Customer, no Account, no event.
    Livewire::test(CreateCustomer::class)
        ->fillForm([
            'email' => 'taken@example.test',
            'name' => 'Duplicate Attempt',
            'preferred_currency' => 'EUR',
            'preferred_locale' => 'en',
        ])
        ->call('create')
        ->assertHasFormErrors(['email']);

    expect(Customer::query()->where('name', 'Duplicate Attempt')->exists())->toBeFalse()
        ->and(Account::query()->count())->toBe($accountsBefore)
        ->and(DomainEvent::query()->where('name', 'CustomerCreated')->exists())->toBeFalse();
});

it('exposes the Customer create fields and no status field', function () {
    actingAs(Operator::factory()->create(), 'operator');

    Livewire::test(CreateCustomer::class)
        ->assertFormFieldExists('email')
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('preferred_currency')
        ->assertFormFieldExists('preferred_locale')
        ->assertFormFieldExists('phone')
        ->assertFormFieldExists('date_of_birth')
        // A Customer is born `pending` by CreateCustomer (status advances only through the ViewCustomer verbs —
        // task 3.1), so the create form never sets `status` (design D5).
        ->assertFormFieldDoesNotExist('status');
});
