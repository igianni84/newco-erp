<?php

// Task 1.1–1.3 (operator-console-parties-customer; design D1/D2/D7; ADR 2026-06-19 + 2026-06-20 + 2026-06-21) —
// the operator console's READ-ONLY Customer surface, the FIRST DEMAND-SIDE Parties (Module K) console built on
// the shared kit (the non-catalog trait-reuse pattern). These assertions pin: an authenticated operator sees the
// paginated list with each Customer's name/email + its THREE orthogonal lifecycle badges (`status`,
// `kyc_status`, `sanctions_status` — each rendered through the BackedEnum cast, a nullable compliance axis blank
// when never screened, no `Parties\Enums` import on the read path) + the co-provisioned Account status; the view
// page renders the read-only attribute set incl. the three badges; and the resource exposes the read pages plus a
// write-through create page but NO edit/delete default action (read-projection + write-through-actions, the
// capability's foundational discipline), with the create affordance a header navigation link.
//
// Parties enums/models are imported freely here: the {Models, Actions, Enums} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests. Seeding uses the factory (a read surface does not care how a row was
// created — the factory bypasses CreateCustomer, records no event and co-provisions no Account).

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ListCustomers;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ViewCustomer;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists Customers with their identity attributes, the three orthogonal lifecycle badges and account status', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $customer = Customer::factory()->create([
        'name' => 'Read Surface Customer',
        'email' => 'read.surface@example.test',
        'status' => CustomerStatus::Active,
        'kyc_status' => KycStatus::Verified,
        'sanctions_status' => SanctionsStatus::Passed,
    ]);

    // The factory co-provisions no Account; stand one up directly (a within-Parties read) with a DISTINCT status
    // so the `account_status` badge is unambiguous against the Customer's own `active` status.
    Account::factory()->create([
        'customer_id' => $customer->id,
        'status' => AccountStatus::Suspended,
    ]);

    // A never-screened Customer: the factory leaves `kyc_status` / `sanctions_status` unset (NULL), which the
    // badge columns render as the empty string rather than crashing on a missing enum.
    $neverScreened = Customer::factory()->create(['name' => 'Walk-In Prospect']);

    expect($neverScreened->kyc_status)->toBeNull()
        ->and($neverScreened->sanctions_status)->toBeNull();

    Livewire::test(ListCustomers::class)
        ->assertCanSeeTableRecords([$customer, $neverScreened])
        ->assertSee('Read Surface Customer')
        ->assertSee('read.surface@example.test')
        ->assertSee('active')      // `status` via the BackedEnum-cast badge column
        ->assertSee('verified')    // `kyc_status` via the nullable BackedEnum-cast badge column
        ->assertSee('passed')      // `sanctions_status` via the nullable BackedEnum-cast badge column
        ->assertSee('suspended');  // `account_status` — the co-provisioned Account's state (within-Parties read)
});

it('renders the read-only identity attributes and the three lifecycle badges on the view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $customer = Customer::factory()->create([
        'name' => 'View Page Customer',
        'email' => 'view.page@example.test',
        'status' => CustomerStatus::Active,
        'kyc_status' => KycStatus::Verified,
        'sanctions_status' => SanctionsStatus::Passed,
    ]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->getKey()])
        ->assertSee('View Page Customer')
        ->assertSee('view.page@example.test')
        ->assertSee('active')      // `status` badge on the infolist
        ->assertSee('verified')    // `kyc_status` badge on the infolist
        ->assertSee('passed');     // `sanctions_status` badge on the infolist
});

it('exposes the read pages plus a write-through create page and no edit or delete default action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    Customer::factory()->create();

    // The read projection (list) + view, plus the dedicated write-through create page (routed through
    // CreateCustomer). No edit page — the Parties backend ships no Customer update Action.
    expect(array_keys(CustomerResource::getPages()))
        ->toEqualCanonicalizing(['index', 'create', 'view']);

    // No mutating row/bulk action on the table; the create affordance is a navigation link to the
    // write-through page, never an inline CreateAction.
    Livewire::test(ListCustomers::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('reaches the create page through a header navigation link, never an inline CreateAction', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The list header exposes a `create` action whose URL is the dedicated Create page — a plain link, not a
    // CreateAction (a CreateAction has no URL; it opens an inline modal that would `$record->save()`).
    Livewire::test(ListCustomers::class)
        ->assertActionExists('create')
        ->assertActionHasUrl('create', CustomerResource::getUrl('create'));
});

it('seeds the operator_console.customer i18n group in EN with an IT translation', function () {
    app()->setLocale('en');
    expect((string) __('operator_console.customer.columns.name'))->toBe('Name')
        ->and((string) __('operator_console.customer.label'))->toBe('Customer');

    app()->setLocale('it');
    expect((string) __('operator_console.customer.columns.name'))->toBe('Nome')
        // 'label' is absent in IT — per-key EN fallback (DEC-127) resolves it to the EN value.
        ->and((string) __('operator_console.customer.label'))->toBe('Customer');
});
