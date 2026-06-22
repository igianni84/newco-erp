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
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Modules\Parties\Models\Profile;
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

it('renders the customer-scope Holds with the placeholder per-row action', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $customer = Customer::factory()->create();

    // The HoldFactory default is an `active` `admin` Hold at `customer` scope; pin its scope_id onto this Customer
    // so the widget's customer-scope query (task 1.2) surfaces it.
    $hold = Hold::factory()->create(['scope_id' => $customer->id]);

    Livewire::test(CustomerHoldsTable::class, ['record' => $customer])
        ->assertCanSeeTableRecords([$hold])
        ->assertTableActionExists('placeholder', record: $hold);
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

it('exposes the placeHold form with the six Hold types, three scopes and a profile field gated on a profile-scope Hold', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A Customer with one Club-membership Profile so the profile-scope branch resolves a real option
    // ($profile->club->display_name) once profile_id is shown — exercising profileOptions() end to end.
    $customer = Customer::factory()->create();
    Profile::factory()->for($customer)->create();

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        // placeHold is a HEADER action on the page (task 3.1), targeting the page's Customer — mount it to inspect
        // its form schema (the form only collects here; the write-through into PlaceHold lands in task 3.2).
        ->mountAction('placeHold')
        // hold_type exposes EXACTLY the six HoldType operand-enum tokens (value-keyed, in enum order).
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
