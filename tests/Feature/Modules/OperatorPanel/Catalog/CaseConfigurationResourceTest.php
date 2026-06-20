<?php

// Task 2.2 (operator-console-catalog-spine; design L1/L3; ADR 2026-06-19 + 2026-06-20) — the operator
// console's READ-ONLY Case Configuration surface, the second spine console built as pure reuse of the kit.
// These assertions pin: an authenticated operator sees the paginated list with each Case Configuration's
// structural attributes (name, units per case, packaging type) + lifecycle_state + version; the view page
// renders the read-only attribute set; the resource exposes the read pages plus a write-through create page but
// NO edit/delete default action; and the create surface carries NO breakability field (BR-RefData-2).
//
// Catalog enums/models are imported freely here: the {Models, Actions} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests. Seeding uses the factory (a read surface does not care how a row
// was created).

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CaseConfigurationResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CaseConfigurationResource\Pages\CreateCaseConfiguration as CreateCaseConfigurationPage;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CaseConfigurationResource\Pages\ListCaseConfigurations;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CaseConfigurationResource\Pages\ViewCaseConfiguration;
use App\Modules\OperatorPanel\Models\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists Case Configurations with their structural attributes, lifecycle state and version', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $caseConfiguration = CaseConfiguration::factory()->create([
        'name' => 'Read-Surface OWC Six',
        'units_per_case' => 6,
        'packaging_type' => 'owc',
        'lifecycle_state' => LifecycleState::Reviewed,
        'version' => 1,
    ]);

    Livewire::test(ListCaseConfigurations::class)
        ->assertCanSeeTableRecords([$caseConfiguration])
        ->assertSee('Read-Surface OWC Six')
        ->assertSee('owc')
        ->assertSee('reviewed');   // lifecycle_state rendered via the kit's BackedEnum-cast badge column
});

it('renders the read-only attribute set on the view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $caseConfiguration = CaseConfiguration::factory()->create([
        'name' => 'View Carton Twelve',
        'units_per_case' => 12,
        'packaging_type' => 'carton',
    ]);

    Livewire::test(ViewCaseConfiguration::class, ['record' => $caseConfiguration->getKey()])
        ->assertSee('View Carton Twelve')
        ->assertSee('12')
        ->assertSee('carton');
});

it('exposes the read pages plus a write-through create page and no edit or delete default action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    CaseConfiguration::factory()->create();

    // The read projection (list) + view, plus the dedicated write-through create page (routed through
    // CreateCaseConfiguration). No edit page — the Catalog backend ships no update Action.
    expect(array_keys(CaseConfigurationResource::getPages()))
        ->toEqualCanonicalizing(['index', 'create', 'view']);

    // No mutating row/bulk action on the table; the create affordance is a navigation link to the
    // write-through page, never an inline CreateAction.
    Livewire::test(ListCaseConfigurations::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('reaches the create page through a header navigation link, never an inline CreateAction', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The list header exposes a `create` action whose URL is the dedicated Create page — a plain link, not a
    // CreateAction (a CreateAction has no URL; it opens an inline modal that would `$record->save()`).
    Livewire::test(ListCaseConfigurations::class)
        ->assertActionExists('create')
        ->assertActionHasUrl('create', CaseConfigurationResource::getUrl('create'));
});

it('exposes a create form with no breakability field (BR-RefData-2)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The create form collects only name / units_per_case / packaging_type — the three inputs the
    // CreateCaseConfiguration action consumes. Breakability is decided downstream (Module A/S), never captured
    // on the Case Configuration, so the form must NOT surface it (and there is no such model column to bind).
    Livewire::test(CreateCaseConfigurationPage::class)
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('units_per_case')
        ->assertFormFieldExists('packaging_type')
        ->assertFormFieldDoesNotExist('breakability')
        ->assertFormFieldDoesNotExist('breakable');
});

it('seeds the operator_console.case_configuration i18n group in EN with an IT translation', function () {
    app()->setLocale('en');
    expect((string) __('operator_console.case_configuration.columns.units_per_case'))->toBe('Units per case')
        ->and((string) __('operator_console.case_configuration.label'))->toBe('Case Configuration');

    app()->setLocale('it');
    expect((string) __('operator_console.case_configuration.columns.units_per_case'))->toBe('Unità per cassa')
        // 'label' is absent in IT — per-key EN fallback (DEC-127) resolves it to the EN value.
        ->and((string) __('operator_console.case_configuration.label'))->toBe('Case Configuration');
});
