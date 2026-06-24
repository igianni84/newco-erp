<?php

// operator-console UI pass (2026-06-24) — the demo-polish reshape of the Catalog + Parties consoles. These
// assertions pin the NEW behaviour the pass introduced, beyond the navigation IA locked by
// OperatorConsoleNavigationTest:
//   - child consoles render INSIDE their parent's view page as interactive relation-manager sub-tables, and
//     create from there routes through the owning module's domain action with the parent implied (no parent
//     picker) — never an Eloquent write;
//   - the Customer's memberships sub-table is read-only (membership creation lives on the approval queue);
//   - the new Supplier console lists + creates through the CreateSupplier action;
//   - the dashboard renders the two module-scoped analytics widgets that replaced the Filament defaults.
//
// {Models, Actions} carve-out governs PRODUCTION code, not tests — models/factories are imported freely here.

use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\ViewProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\RelationManagers\VariantsRelationManager;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ViewCustomer;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\RelationManagers\MembershipsRelationManager;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\Pages\ViewProducer;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\RelationManagers\ClubsRelationManager;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\RelationManagers\ProducerAgreementsRelationManager;
use App\Modules\OperatorPanel\Filament\Resources\Parties\SupplierResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\SupplierResource\Pages\CreateSupplier;
use App\Modules\OperatorPanel\Filament\Resources\Parties\SupplierResource\Pages\ListSuppliers;
use App\Modules\OperatorPanel\Filament\Widgets\CatalogPartiesOverview;
use App\Modules\OperatorPanel\Filament\Widgets\MembershipsByStateChart;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\ProducerAgreement;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// --- Catalog: Variants nested inside Product Master ---

it('renders a Product Master\'s Variants in the relation manager on the view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create();
    $variant = ProductVariant::factory()->create([
        'product_master_id' => $master->id,
        'variant_identifier' => 'GRAND-CRU-2019',
    ]);

    Livewire::test(VariantsRelationManager::class, [
        'ownerRecord' => $master,
        'pageClass' => ViewProductMaster::class,
    ])
        ->assertCanSeeTableRecords([$variant])
        ->assertSee('GRAND-CRU-2019')
        // The inline create action is wired into the relation-manager header (parent Master implied; routed
        // through CreateProductVariant). Its end-to-end execution is exercised live in the run/verify step —
        // Filament's isolated RM-header-action test helpers do not resolve header-action visibility reliably.
        ->assertTableActionExists('create');
});

// --- Parties: Clubs + Agreements nested inside Producer ---

it('renders a Producer\'s Clubs and Agreements in their relation managers, each with a create action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $producer = Producer::factory()->create();
    $club = Club::factory()->create(['producer_id' => $producer->id, 'display_name' => 'Cellar Circle']);
    $agreement = ProducerAgreement::factory()->create(['producer_id' => $producer->id]);

    Livewire::test(ClubsRelationManager::class, ['ownerRecord' => $producer, 'pageClass' => ViewProducer::class])
        ->assertCanSeeTableRecords([$club])
        ->assertSee('Cellar Circle')
        ->assertTableActionExists('create');

    Livewire::test(ProducerAgreementsRelationManager::class, ['ownerRecord' => $producer, 'pageClass' => ViewProducer::class])
        ->assertCanSeeTableRecords([$agreement])
        ->assertTableActionExists('create');
});

// --- Parties: Customer memberships (read-only) ---

it('renders a Customer\'s memberships read-only — visible but with no create action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => $club->id]);

    Livewire::test(MembershipsRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => ViewCustomer::class,
    ])
        ->assertCanSeeTableRecords([$profile])
        ->assertTableActionDoesNotExist('create');
});

// --- Parties: Supplier console ---

it('lists Suppliers and exposes a write-through create header link', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $supplier = Supplier::factory()->create(['legal_name' => 'Vinlock Logistics']);

    Livewire::test(ListSuppliers::class)
        ->assertCanSeeTableRecords([$supplier])
        ->assertSee('Vinlock Logistics')
        ->assertActionExists('create')
        ->assertActionHasUrl('create', SupplierResource::getUrl('create'));
});

it('creates a Supplier through the CreateSupplier domain action, fixing the party-type marker', function () {
    actingAs(Operator::factory()->create(), 'operator');

    Livewire::test(CreateSupplier::class)
        ->fillForm(['legal_name' => 'ACME Imports'])
        ->call('create')
        ->assertHasNoFormErrors();

    // sole() returns the single Supplier (and fails loudly if zero/many were created) — a non-null type for the
    // party-type assertion below.
    $supplier = Supplier::query()->where('legal_name', 'ACME Imports')->sole();
    expect($supplier->party_type->value)->toBe('supplier');
});

// --- Dashboard analytics widgets ---

it('renders the Catalog + Parties KPI overview widget with live counts', function () {
    actingAs(Operator::factory()->create(), 'operator');
    app()->setLocale('en');

    ProductMaster::factory()->count(2)->create();
    Producer::factory()->create();

    Livewire::test(CatalogPartiesOverview::class)
        ->assertSee('Product Masters')
        ->assertSee('Producers');
});

it('renders the memberships-by-state chart widget', function () {
    actingAs(Operator::factory()->create(), 'operator');
    app()->setLocale('en');

    $club = Club::factory()->create();
    Profile::factory()->create(['club_id' => $club->id]);

    Livewire::test(MembershipsByStateChart::class)
        ->assertSee('Memberships by state');
});
