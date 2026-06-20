<?php

// Task 2.1 (operator-console-catalog-spine; design L1/L3; ADR 2026-06-19 + 2026-06-20) — the operator
// console's READ-ONLY Format surface, the first spine console built as pure reuse of the kit. These assertions
// pin: an authenticated operator sees the paginated list with each Format's structural attributes +
// lifecycle_state + version; the view page renders the read-only attribute set; and the resource exposes the
// read pages plus a write-through create page but NO edit/delete default action (read-projection +
// write-through-actions, the capability's foundational discipline — enforced structurally by the kit, surfaced
// here).
//
// Catalog enums/models are imported freely here: the {Models, Actions} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests. Seeding uses the factory (a read surface does not care how a row
// was created).

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\Format;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource\Pages\ListFormats;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource\Pages\ViewFormat;
use App\Modules\OperatorPanel\Models\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists Formats with their structural attributes, lifecycle state and version', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $format = Format::factory()->create([
        'name' => 'Read-Surface Magnum',
        'size_label' => '1.5L',
        'volume_ml' => 1500,
        'lifecycle_state' => LifecycleState::Reviewed,
        'version' => 1,
    ]);

    Livewire::test(ListFormats::class)
        ->assertCanSeeTableRecords([$format])
        ->assertSee('Read-Surface Magnum')
        ->assertSee('1.5L')
        ->assertSee('1500')
        ->assertSee('reviewed');   // lifecycle_state rendered via the kit's BackedEnum-cast badge column
});

it('renders the read-only attribute set on the view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $format = Format::factory()->create([
        'name' => 'View Imperial',
        'size_label' => '6L',
        'volume_ml' => 6000,
    ]);

    Livewire::test(ViewFormat::class, ['record' => $format->getKey()])
        ->assertSee('View Imperial')
        ->assertSee('6L')
        ->assertSee('6000');
});

it('exposes the read pages plus a write-through create page and no edit or delete default action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    Format::factory()->create();

    // The read projection (list) + view, plus the dedicated write-through create page (routed through
    // CreateFormat). No edit page — the Catalog backend ships no update Action.
    expect(array_keys(FormatResource::getPages()))
        ->toEqualCanonicalizing(['index', 'create', 'view']);

    // No mutating row/bulk action on the table; the create affordance is a navigation link to the
    // write-through page, never an inline CreateAction.
    Livewire::test(ListFormats::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('reaches the create page through a header navigation link, never an inline CreateAction', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The list header exposes a `create` action whose URL is the dedicated Create page — a plain link, not a
    // CreateAction (a CreateAction has no URL; it opens an inline modal that would `$record->save()`).
    Livewire::test(ListFormats::class)
        ->assertActionExists('create')
        ->assertActionHasUrl('create', FormatResource::getUrl('create'));
});

it('seeds the operator_console.format i18n group in EN with an IT translation', function () {
    app()->setLocale('en');
    expect((string) __('operator_console.format.columns.name'))->toBe('Name')
        ->and((string) __('operator_console.format.label'))->toBe('Format');

    app()->setLocale('it');
    expect((string) __('operator_console.format.columns.name'))->toBe('Nome')
        // 'label' is absent in IT — per-key EN fallback (DEC-127) resolves it to the EN value.
        ->and((string) __('operator_console.format.label'))->toBe('Format');
});
