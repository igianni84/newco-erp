<?php

// operator-console-parties-holds — the Customer console's Holds surface. This file is FOUNDED by task 1.2 (the
// non-relation Holds-table VEHICLE) and accumulates through the slice: the full cross-scope read table (2.1), the
// placeHold header form (3.1/3.2) and the per-row lift action (4.1/4.2) all extend it.
//
// Task 1.2 pins only the vehicle: a Hold is NOT an Eloquent relation of Customer (a polymorphic scope_type +
// scope_id, no FK — design L1), so a RelationManager cannot host it. The vehicle is a non-relation Filament 5
// TableWidget ({@see CustomerHoldsTable}) hosted on {@see ViewCustomer} via getFooterWidgets(). These two tests
// prove (a) the page hosts the widget and (b) the widget renders the customer-scope Holds with a per-row action.
// The query here is the minimal customer-scope slice and the row action is an inert `placeholder`; both are
// replaced with the real surface in tasks 2–4.
//
// DatabaseMigrations (mirroring CustomerLifecycleConsoleTest): the place/lift actions added in tasks 3–4 each open
// their OWN DB::transaction, so the DomainEventRecorder's transaction guard needs a real commit (RefreshDatabase
// wraps every write in a never-committed outer transaction). The file adopts it now so the trait never churns.
// The HoldFactory bypasses the actions — it stands up a bare `active` `admin` customer-scope Hold, recording no
// event. Parties enums/models import freely in tests (the {Models, Actions} carve-out governs production code).

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ViewCustomer;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Widgets\CustomerHoldsTable;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Actions\LiftHold;
use App\Modules\Parties\Actions\PlaceHold;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Events\CustomerHoldPlaced;
use App\Modules\Parties\Events\CustomerReactivated;
use App\Modules\Parties\Events\CustomerSuspended;
use App\Modules\Parties\Exceptions\IllegalHoldLift;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('hosts the Holds table as a footer widget on the Customer view', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $customer = Customer::factory()->create();

    // A customer-scope Hold carrying a recognizable reason. The footer widget (non-lazy, task 1.2) renders inline
    // on the ViewCustomer page, so its row surfaces in the page's initial render — proving the table is HOSTED on
    // the page (its getFooterWidgets() wiring + the customer-scope query both run), not merely mountable in isolation.
    Hold::factory()->create(['scope_id' => $customer->id, 'reason' => 'Holds widget integration probe']);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->assertSee('Holds widget integration probe');
});

it('renders the customer-scope Holds with the per-row lift action', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $customer = Customer::factory()->create();

    // The HoldFactory default is an `active` `admin` Hold at `customer` scope; pin its scope_id onto this Customer
    // so the widget's scope-set query surfaces it. `admin` + `active` is operator-liftable (design D5/D6), so the
    // per-row `lift` action (task 4.1, which replaced the task-1.2 placeholder) is visible on its row.
    $hold = Hold::factory()->create(['scope_id' => $customer->id]);

    Livewire::test(CustomerHoldsTable::class, ['record' => $customer])
        ->assertCanSeeTableRecords([$hold])
        ->assertTableActionVisible('lift', record: $hold);
});

it('renders Holds across the customer, account and profile scopes read-only (no inline edit or delete)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A factory Customer carries no Account/Profiles (CreateCustomer co-provisions them in production); stand up
    // one of each within-Parties so the widget's scope-set union (task 2.1: customer ∪ Account ∪ Profiles) has a
    // row to surface at every scope.
    $customer = Customer::factory()->create();
    $account = Account::factory()->for($customer)->create();
    $profile = Profile::factory()->for($customer)->create();

    // One `active` `admin` Hold (the HoldFactory default) at each of the three scopes, pinned onto this Customer's
    // own id / its Account id / its Profile id, each carrying a distinctive reason so the read renders per-scope.
    $customerHold = Hold::factory()->create([
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
        'reason' => 'customer-scope probe',
    ]);
    $accountHold = Hold::factory()->create([
        'scope_type' => HoldScope::Account,
        'scope_id' => $account->id,
        'reason' => 'account-scope probe',
    ]);
    $profileHold = Hold::factory()->create([
        'scope_type' => HoldScope::Profile,
        'scope_id' => $profile->id,
        'reason' => 'profile-scope probe',
    ]);

    Livewire::test(CustomerHoldsTable::class, ['record' => $customer])
        // All three scope rows surface through the union query.
        ->assertCanSeeTableRecords([$customerHold, $accountHold, $profileHold])
        // type / scope / status / reason render through the cast (`->value`) per row.
        ->assertSee('admin')              // hold_type cast value (HoldType::Admin), shared across the three
        ->assertSee('active')             // status cast value (HoldStatus::Active), shared across the three
        ->assertSee('customer')           // scope_type cast value of the customer-scope row
        ->assertSee('account')            // scope_type cast value of the account-scope row
        ->assertSee('profile')            // scope_type cast value of the profile-scope row
        ->assertSee('customer-scope probe')
        ->assertSee('account-scope probe')
        ->assertSee('profile-scope probe')
        // Read-only: the table exposes no Filament default mutating row action (the only mutation is the per-row
        // `lift`, landing in task 4.1).
        ->assertTableActionDoesNotExist('edit', record: $customerHold)
        ->assertTableActionDoesNotExist('delete', record: $customerHold);
});

it('exposes the placeHold form with the eight Hold types, three scopes and a profile field gated on a profile-scope Hold', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A Customer with one Club-membership Profile so the profile-scope branch resolves a real option
    // ($profile->club->display_name) once profile_id is shown — exercising profileOptions() end to end.
    $customer = Customer::factory()->create();
    Profile::factory()->for($customer)->create();

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        // placeHold is a HEADER action on the page (task 3.1), targeting the page's Customer — mount it to inspect
        // its form schema (the form only collects here; the write-through into PlaceHold lands in task 3.2).
        ->mountAction('placeHold')
        // hold_type exposes EXACTLY the eight HoldType operand-enum tokens (value-keyed, in enum order; canon DEC-008).
        ->assertFormFieldExists('hold_type', fn (Select $field): bool => array_keys($field->getOptions())
            === array_map(static fn (HoldType $type): string => $type->value, HoldType::cases()))
        // scope_type exposes EXACTLY the three HoldScope tokens.
        ->assertFormFieldExists('scope_type', fn (Select $field): bool => array_keys($field->getOptions())
            === array_map(static fn (HoldScope $scope): string => $scope->value, HoldScope::cases()))
        // profile_id is gated on a profile-scope Hold: hidden by default (scope unset), shown only for `profile`,
        // and hidden again for any other scope (the `scope_type` Select is `->live()`).
        ->assertFormFieldHidden('profile_id')
        ->setActionData(['scope_type' => HoldScope::Profile->value])
        ->assertFormFieldVisible('profile_id')
        ->setActionData(['scope_type' => HoldScope::Account->value])
        ->assertFormFieldHidden('profile_id');
});

it('places an admin Hold on an active Customer through the console — one active Hold + CustomerHoldPlaced + CustomerSuspended, now suspended', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // An `active` Customer is in the suspendable from-state, so the domain-owned Hold→`suspended` coupling fires
    // (PlaceHold invokes SuspendCustomer in the same transaction — the console invokes ONLY PlaceHold, design D7).
    // The factory co-provisions no Profile, so the suspension cascade is silent (no ProfileSuspended noise) and
    // CustomerSuspended is the only status event.
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        // callAction mounts placeHold, fills the form with this data, then calls it — the write-through routes the
        // operands into PlaceHold (task 3.2). The console never writes the Hold row itself.
        ->callAction('placeHold', [
            'hold_type' => HoldType::Admin->value,
            'scope_type' => HoldScope::Customer->value,
            'reason' => 'manual review',
        ])
        ->assertNotified((string) __('operator_console.customer.notifications.hold_placed'));

    // Exactly one `active` Hold on the Customer scope, carrying the submitted reason — written by PlaceHold.
    $hold = Hold::query()->sole();
    expect($hold->hold_type)->toBe(HoldType::Admin)
        ->and($hold->scope_type)->toBe(HoldScope::Customer)
        ->and($hold->scope_id)->toBe($customer->id)
        ->and($hold->status)->toBe(HoldStatus::Active)
        ->and($hold->reason)->toBe('manual review');

    // The coupling drove the active Customer to `suspended` (the view's status badge reflects it through the cast).
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Suspended);

    // Exactly one CustomerHoldPlaced, carrying the operator audit envelope (newco_ops + the operator id) resolved
    // by the Action from the `operator` guard — the console constructs no envelope itself.
    $placed = DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->sole();
    expect($placed->module)->toBe('parties')
        ->and($placed->entity_type)->toBe('Hold')
        ->and($placed->entity_id)->toBe((string) $hold->id)
        ->and($placed->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($placed->actor_id)->toEqual($operator->id);  // loose: PG returns a numeric string for the bigint

    // … AND exactly one CustomerSuspended (the coupling's root status event).
    expect(DomainEvent::query()->where('name', CustomerSuspended::NAME)->count())->toBe(1);
});

it('places an admin Hold on a pending Customer through the console — Hold recorded, no suspension', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A fresh factory Customer is born `pending` — NOT in the suspendable from-state, so the coupling's from-state
    // pre-check records the Hold and drives NO transition (the status FSM stays independent of onboarding).
    $customer = Customer::factory()->create();

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        // No `reason` field submitted → the write-through normalizes the blank operand to NULL (never '').
        ->callAction('placeHold', [
            'hold_type' => HoldType::Admin->value,
            'scope_type' => HoldScope::Customer->value,
        ])
        ->assertNotified((string) __('operator_console.customer.notifications.hold_placed'));

    // The Hold is recorded on the Customer scope, with a NULL reason (the un-submitted optional operand).
    $hold = Hold::query()->sole();
    expect($hold->scope_type)->toBe(HoldScope::Customer)
        ->and($hold->scope_id)->toBe($customer->id)
        ->and($hold->reason)->toBeNull();

    // The pending Customer is untouched, and only CustomerHoldPlaced is recorded — no CustomerSuspended.
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending)
        ->and(DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerSuspended::NAME)->count())->toBe(0);
});

it('resolves account scope to the Customer Account id when placing a Hold through the console', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A factory Customer carries no Account (CreateCustomer co-provisions it in production); stand one up so the
    // account-scope target resolution ($record->account->id — design D4) has the co-provisioned Account to find.
    $customer = Customer::factory()->create();
    $account = Account::factory()->for($customer)->create();

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('placeHold', [
            'hold_type' => HoldType::Fraud->value,
            'scope_type' => HoldScope::Account->value,
        ])
        ->assertNotified((string) __('operator_console.customer.notifications.hold_placed'));

    // The scope-target resolution routed `account` scope to the Account id (NOT the Customer id) — design D4.
    $hold = Hold::query()->sole();
    expect($hold->scope_type)->toBe(HoldScope::Account)
        ->and($hold->scope_id)->toBe($account->id);
});

it('shows the per-row lift only on active operator-liftable Holds (admin/fraud/compliance/credit), hiding it on auto-managed and already-lifted Holds', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $customer = Customer::factory()->create();

    // The HoldFactory bypasses the domain Actions (no coupling, no event) — bare customer-scope rows that exercise
    // the per-row visibility predicate in isolation (task 4.1; design D5/D6). Operator-liftable = `active` AND not
    // auto-managed: `admin`/`fraud`/`compliance`/`credit` lift through the operator path…
    $admin = Hold::factory()->create(['hold_type' => HoldType::Admin, 'status' => HoldStatus::Active, 'scope_id' => $customer->id]);
    $fraud = Hold::factory()->create(['hold_type' => HoldType::Fraud, 'status' => HoldStatus::Active, 'scope_id' => $customer->id]);
    $compliance = Hold::factory()->create(['hold_type' => HoldType::Compliance, 'status' => HoldStatus::Active, 'scope_id' => $customer->id]);
    $credit = Hold::factory()->create(['hold_type' => HoldType::Credit, 'status' => HoldStatus::Active, 'scope_id' => $customer->id]);

    // …while `kyc`/`payment` are auto-managed (HoldType::autoLiftable()) — they lift only on their system clearing
    // signal, so the operator path HIDES them (and LiftHold rejects an operator lift of them — task 4.2).
    $kyc = Hold::factory()->create(['hold_type' => HoldType::Kyc, 'status' => HoldStatus::Active, 'scope_id' => $customer->id]);
    $payment = Hold::factory()->create(['hold_type' => HoldType::Payment, 'status' => HoldStatus::Active, 'scope_id' => $customer->id]);

    // An already-`lifted` Hold lifts once — the status half of the predicate hides lift regardless of the (otherwise
    // liftable) `admin` type.
    $lifted = Hold::factory()->create(['hold_type' => HoldType::Admin, 'status' => HoldStatus::Lifted, 'scope_id' => $customer->id]);

    Livewire::test(CustomerHoldsTable::class, ['record' => $customer])
        // Visible on every active operator-liftable row…
        ->assertTableActionVisible('lift', record: $admin)
        ->assertTableActionVisible('lift', record: $fraud)
        ->assertTableActionVisible('lift', record: $compliance)
        ->assertTableActionVisible('lift', record: $credit)
        // …hidden on the auto-managed rows (kyc / payment) and on the already-lifted row.
        ->assertTableActionHidden('lift', record: $kyc)
        ->assertTableActionHidden('lift', record: $payment)
        ->assertTableActionHidden('lift', record: $lifted);
});

it('lifts one of two covering Holds without restoring, then restores the Customer on lifting the last — CustomerHoldLifted ×2, one CustomerReactivated', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // Arrange the suspendable precondition through the REAL domain coupling, NOT the bare factory (which records
    // no event and never suspends). PlaceHold suspends the `active` Customer on the FIRST Hold (CustomerSuspended);
    // the SECOND Hold lands on the now-`suspended` Customer and drives no further transition (BR-K-Hold-1 admits
    // concurrent Holds, the from-state pre-check skips the re-suspend). Both are `active` Customer-scope Holds, so
    // both cover the Customer — the multi-Hold partial-lift case the restore coupling must get right (design D7).
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $admin = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Customer, $customer->id, 'manual review');
    $fraud = app(PlaceHold::class)->handle(HoldType::Fraud, HoldScope::Customer, $customer->id, 'fraud review');

    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Suspended);

    $component = Livewire::test(CustomerHoldsTable::class, ['record' => $customer]);

    // Lift the `admin` Hold through the per-row action (admin is operator-liftable, so `lift` is visible and the
    // standard callTableAction path drives it). The `fraud` Hold STILL covers the Customer, so the restore coupling
    // does NOT fire: exactly one CustomerHoldLifted, the Customer stays `suspended`, NO CustomerReactivated.
    $component->callTableAction('lift', $admin, ['lift_reason' => 'admin cleared'])
        ->assertNotified((string) __('operator_console.customer.notifications.hold_lifted'));

    expect($admin->refresh()->status)->toBe(HoldStatus::Lifted)
        ->and($admin->lift_reason)->toBe('admin cleared')           // the optional operand flowed widget → LiftHold
        ->and($fraud->refresh()->status)->toBe(HoldStatus::Active)   // the second Hold still covers the Customer
        ->and(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Suspended)
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerReactivated::NAME)->count())->toBe(0);

    // Lift the LAST covering Hold (`fraud`). Now no active Hold covers the Customer, so the restore coupling fires
    // in the same transaction: a second CustomerHoldLifted AND exactly one CustomerReactivated, Customer → `active`.
    $component->callTableAction('lift', $fraud, ['lift_reason' => 'fraud cleared'])
        ->assertNotified((string) __('operator_console.customer.notifications.hold_lifted'));

    expect($fraud->refresh()->status)->toBe(HoldStatus::Lifted)
        ->and(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Active)
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(2);

    // The emergent restore records exactly one CustomerReactivated carrying the Customer envelope + the operator
    // audit envelope resolved from the `operator` guard — the console invoked ONLY LiftHold; the restore is the
    // domain coupling's own additive event (design D7), never a Reactivate verb the console called.
    $reactivated = DomainEvent::query()->where('name', CustomerReactivated::NAME)->sole();
    expect($reactivated->module)->toBe('parties')
        ->and($reactivated->entity_type)->toBe('Customer')
        ->and($reactivated->entity_id)->toBe((string) $customer->id)
        ->and($reactivated->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($reactivated->actor_id)->toEqual($operator->id);  // loose: PG returns a numeric string for the bigint
});

it('hides lift on an auto-managed kyc Hold and the domain independently rejects an operator lift of it — Hold unchanged, no event (defense in depth)', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $customer = Customer::factory()->create();

    // An `active` `kyc` Hold: auto-managed (HoldType::autoLiftable()), so it lifts ONLY on its system clearing
    // signal (RecordKycVerified), never by hand. Design D6 is DEFENSE IN DEPTH — the lift discipline is SURFACED by
    // visibility AND ENFORCED by the domain — and this test evidences BOTH halves the console can demonstrate.
    $kyc = Hold::factory()->create([
        'hold_type' => HoldType::Kyc,
        'status' => HoldStatus::Active,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
    ]);

    // The SURFACE half: the kyc row renders read-only — its per-row `lift` is HIDDEN ({@see isOperatorLiftable}
    // excludes auto-managed types). There is NO widget path that reaches the lift's surfaceLifecycleOutcome for a
    // kyc Hold: Filament re-resolves record-action visibility on EVERY resolution and drops a hidden action server
    // side (empirically — a hidden lift never mounts; a row lifted out-of-band stops invoking mid-flight), and the
    // lift's visibility predicate is the EXACT complement of LiftHold's rejection conditions (notActive/autoManaged),
    // so the widget's `action_failed` branch is structurally unreachable for a lift rejection. The kit's
    // RuntimeException→`action_failed` surfacing is a SHARED guarantee (proven on the catalog/page consoles and
    // reused verbatim here — the success half fires in this widget's Livewire context above), not re-asserted here.
    Livewire::test(CustomerHoldsTable::class, ['record' => $customer])
        ->assertCanSeeTableRecords([$kyc])
        ->assertTableActionHidden('lift', record: $kyc);

    // The ENFORCEMENT half: even an operator lift that BYPASSES the hidden UI (invoked straight on the domain) is
    // rejected by LiftHold with IllegalHoldLift::autoManaged — a RuntimeException, the base type the kit catches.
    expect(fn () => app(LiftHold::class)->handle($kyc->id))->toThrow(IllegalHoldLift::class);

    // The rejection rolled back: the Hold is untouched (still `active`, no lift actor/moment) and no lift event was
    // recorded (the recorder's open-transaction guard makes a rejected lift record nothing).
    expect($kyc->refresh()->status)->toBe(HoldStatus::Active)
        ->and($kyc->lifted_actor_role)->toBeNull()
        ->and($kyc->lifted_at)->toBeNull()
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(0);
});
