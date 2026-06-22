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
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
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
