<?php

// Task 2.1 (operator-console-catalog-master; design L1/L10; ADR 2026-06-19) — the operator console's
// READ-ONLY Product Master surface. These assertions pin: an authenticated operator sees the paginated
// list with each Master's lifecycle_state and a producer column resolved through Catalog's OWN
// producer-state projection (never Module K); the view page renders the WINE attribute set; and the
// resource exposes NO create/edit/delete default action (read-projection + write-through-actions, the
// capability's foundational discipline — enforced structurally by tasks 1.2/1.3, surfaced here).
//
// Catalog enums/models are imported freely here: the {Models, Actions} import-boundary carve-out (task 1.3)
// governs OperatorPanel PRODUCTION code, not tests. Seeding uses the factory (a read surface does not care
// how a row was created); the producer projection row is written directly (no ProducerState factory ships).

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Models\ProducerState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\ListProductMasters;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\ViewProductMaster;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\I18n\TranslatableText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists Product Masters with their lifecycle state and a projection-resolved producer', function () {
    actingAs(Operator::factory()->create(), 'operator');

    ProducerState::create([
        'producer_id' => 7001,
        'status' => ProducerProjectionStatus::Active,
        'last_event_id' => 1,
    ]);

    $master = ProductMaster::factory()->create([
        'producer_id' => 7001,
        'name' => 'Château Read-Surface',
        'lifecycle_state' => LifecycleState::Reviewed,
    ]);

    Livewire::test(ListProductMasters::class)
        ->assertCanSeeTableRecords([$master])
        ->assertSee('Château Read-Surface')
        ->assertSee('reviewed')   // lifecycle_state rendered via the enum cast instance
        ->assertSee('#7001')      // producer column = the plain id …
        ->assertSee('active');    // … plus the status read from the producer-state projection
});

it('shows a not-projected marker when the producer has no projection row', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create(['producer_id' => 9999]);

    Livewire::test(ListProductMasters::class)
        ->assertCanSeeTableRecords([$master])
        ->assertSee((string) __('operator_console.product_master.producer_unprojected'));
});

it('renders the wine attribute set on the read-only view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create([
        'producer_id' => 7001,
        'name' => 'Château View',
    ]);

    // Pin deterministic, ASCII-safe wine attributes (the factory seeds random ones).
    $master->wineAttributes()->firstOrFail()->update([
        'appellation' => 'Pauillac',
        'region' => 'Bordeaux',
        'winery_story' => TranslatableText::of(['en' => 'A storied estate.']),
    ]);

    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->assertSee('Château View')
        ->assertSee('Pauillac')
        ->assertSee('Bordeaux')
        ->assertSee('A storied estate.');
});

it('exposes the read pages plus a write-through create page and no edit or delete default action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    ProductMaster::factory()->create(['producer_id' => 7001]);

    // The read projection (list) + view, plus the dedicated write-through create page (task 3.1, routed
    // through CreateProductMaster). No edit page — the Catalog backend ships no update Action.
    expect(array_keys(ProductMasterResource::getPages()))
        ->toEqualCanonicalizing(['index', 'create', 'view']);

    // No mutating row/bulk action on the table (a future DeleteAction would trip this); the create
    // affordance is a navigation link to the write-through page, never an inline CreateAction.
    Livewire::test(ListProductMasters::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('seeds the operator_console i18n group in EN with an IT translation', function () {
    app()->setLocale('en');
    expect((string) __('operator_console.product_master.columns.name'))->toBe('Name')
        ->and((string) __('operator_console.product_master.label'))->toBe('Product Master');

    app()->setLocale('it');
    expect((string) __('operator_console.product_master.columns.name'))->toBe('Nome');
});
