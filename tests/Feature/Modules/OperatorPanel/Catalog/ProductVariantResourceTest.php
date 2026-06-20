<?php

// Task 3.1 (operator-console-catalog-spine; design L1/L3; ADR 2026-06-19 + 2026-06-20) — the operator
// console's READ-ONLY Product Variant surface, the FIRST hierarchical spine console, built as pure reuse of the
// kit. These assertions pin: an authenticated operator sees the paginated list with each Variant's identifier,
// its parent Product Master (off the within-Catalog master() relation), the combined vintage display (the year,
// or a "Non-vintage" marker), plus lifecycle_state + version; the view page renders the read-only attribute set
// INCLUDING the WINE attributes (vintage year, tasting notes) off the wineAttributes() relation; the resource
// exposes the read pages plus a write-through create page but NO edit/delete default action; and the create
// surface carries a PARENT Product Master picker (a hierarchical entity, design L3) and NO producer picker
// (design L6).
//
// Catalog enums/models are imported freely here: the {Models, Actions} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests. Seeding uses the factory (a read surface does not care how a row
// was created).

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages\CreateProductVariant as CreateProductVariantPage;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages\ListProductVariants;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages\ViewProductVariant;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\I18n\TranslatableText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists Product Variants with their identifier, parent Master, vintage, lifecycle state and version', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create(['name' => 'Read-Surface Château']);

    $vintage = ProductVariant::factory()->create([
        'product_master_id' => $master->id,
        'variant_identifier' => 'GRAND-CRU-2019',
        'lifecycle_state' => LifecycleState::Reviewed,
        'version' => 1,
    ]);
    // Update through the loaded relation MODEL (`->wineAttributes`, not the relation query `->wineAttributes()`)
    // so the casts apply — the relation-query update binds raw values and would bypass them. The factory always
    // attaches the 1:1 set, so the nullsafe call always fires (a null would surface loudly in assertSee below).
    $vintage->wineAttributes?->update(['vintage_year' => 2019, 'non_vintage' => false]);

    $nonVintage = ProductVariant::factory()->create([
        'product_master_id' => $master->id,
        'variant_identifier' => 'BRUT-NV',
        'lifecycle_state' => LifecycleState::Draft,
    ]);
    $nonVintage->wineAttributes?->update(['vintage_year' => null, 'non_vintage' => true]);

    Livewire::test(ListProductVariants::class)
        ->assertCanSeeTableRecords([$vintage, $nonVintage])
        ->assertSee('GRAND-CRU-2019')
        ->assertSee('Read-Surface Château')  // the parent Master name, off the within-Catalog master() relation
        ->assertSee('2019')                  // the combined vintage column for a vintage wine
        ->assertSee('Non-vintage')           // the combined vintage column's marker for a non-vintage wine
        ->assertSee('reviewed');             // lifecycle_state rendered via the kit's BackedEnum-cast badge column
});

it('renders the read-only attribute set, including the wine attributes, on the view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create(['name' => 'View Château']);
    $variant = ProductVariant::factory()->create([
        'product_master_id' => $master->id,
        'variant_identifier' => 'VIEW-2018',
        'lifecycle_state' => LifecycleState::Draft,
    ]);
    // Update through the loaded relation MODEL (`->wineAttributes`, not the relation query `->wineAttributes()`)
    // so the TranslatableTextCast serializes tasting_notes — the relation-query update would bind the object raw.
    // The factory always attaches the 1:1 set, so the nullsafe call always fires.
    $variant->wineAttributes?->update([
        'vintage_year' => 2018,
        'non_vintage' => false,
        'tasting_notes' => TranslatableText::of(['en' => 'Bright cassis and graphite']),
    ]);

    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->assertSee('VIEW-2018')
        ->assertSee('View Château')                 // the parent Master, off the within-Catalog relation
        ->assertSee('2018')                         // the vintage_year wine attribute
        ->assertSee('Bright cassis and graphite');  // the tasting_notes wine attribute, resolved to the locale
});

it('exposes the read pages plus a write-through create page and no edit or delete default action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    ProductVariant::factory()->create();

    // The read projection (list) + view, plus the dedicated write-through create page (routed through
    // CreateProductVariant). No edit page — the Catalog backend ships no update Action.
    expect(array_keys(ProductVariantResource::getPages()))
        ->toEqualCanonicalizing(['index', 'create', 'view']);

    // No mutating row/bulk action on the table; the create affordance is a navigation link to the
    // write-through page, never an inline CreateAction.
    Livewire::test(ListProductVariants::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('reaches the create page through a header navigation link, never an inline CreateAction', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The list header exposes a `create` action whose URL is the dedicated Create page — a plain link, not a
    // CreateAction (a CreateAction has no URL; it opens an inline modal that would `$record->save()`).
    Livewire::test(ListProductVariants::class)
        ->assertActionExists('create')
        ->assertActionHasUrl('create', ProductVariantResource::getUrl('create'));
});

it('exposes a create form with a parent Product Master picker and the WINE vintage attribute set, and no producer picker', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A Master exists so the picker has at least one option (the create surface lists every Master; the
    // activation-cascade gate — not the picker — enforces parent-active at activate time, design L3/L4).
    ProductMaster::factory()->create();

    Livewire::test(CreateProductVariantPage::class)
        ->assertFormFieldExists('product_master_id')   // the hierarchical parent picker (design L3)
        ->assertFormFieldExists('variant_identifier')
        ->assertFormFieldExists('vintage_year')
        ->assertFormFieldExists('non_vintage')
        ->assertFormFieldExists('tasting_notes')
        // A Product Variant binds NO producer — no producer picker, no Producer-gate handling (design L6).
        ->assertFormFieldDoesNotExist('producer_id');
});

it('seeds the operator_console.product_variant i18n group in EN with an IT translation', function () {
    app()->setLocale('en');
    expect((string) __('operator_console.product_variant.fields.vintage_year'))->toBe('Vintage year')
        ->and((string) __('operator_console.product_variant.label'))->toBe('Product Variant');

    app()->setLocale('it');
    expect((string) __('operator_console.product_variant.fields.vintage_year'))->toBe('Anno di annata')
        // 'label' is absent in IT — per-key EN fallback (DEC-127) resolves it to the EN value.
        ->and((string) __('operator_console.product_variant.label'))->toBe('Product Variant');
});
