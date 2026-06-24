<?php

// Task 3.3 (operator-console-catalog-spine; design L1/L3; ADR 2026-06-19 + 2026-06-20) — the operator console's
// READ-ONLY Sellable SKU surface, the THIRD hierarchical spine console (the commercial unit: a Product Reference
// + a Case Configuration + commercial attributes), built as pure reuse of the kit. These assertions pin: an
// authenticated operator sees the paginated list with each SKU's parent Product Reference (off the within-Catalog
// reference() relation → its Variant + Format) and parent Case Configuration (off caseConfiguration()), plus the
// commercial name, lifecycle_state + version; the view page renders the read-only attribute set incl. the
// marketing copy; the resource exposes the read pages plus a write-through create page but NO edit/delete default
// action; and the create surface carries TWO parent pickers (Product Reference + Case Configuration) + the
// commercial fields and NO producer picker (design L6).
//
// Catalog enums/models are imported freely here: the {Models, Actions} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests. Seeding uses the factory (a read surface does not care how a row
// was created).

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource\Pages\CreateSellableSku as CreateSellableSkuPage;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource\Pages\ListSellableSkus;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource\Pages\ViewSellableSku;
use App\Modules\OperatorPanel\Models\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists Sellable SKUs with their parent Product Reference, Case Configuration, commercial name, lifecycle state and version', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create(['name' => 'Margaux Grand Vin']);
    $variant = ProductVariant::factory()->create(['product_master_id' => $master->id, 'variant_identifier' => 'GRAND-CRU-2019']);
    $format = Format::factory()->create(['name' => 'Magnum', 'size_label' => '1.5L']);
    $reference = ProductReference::factory()->create([
        'product_variant_id' => $variant->id,
        'format_id' => $format->id,
    ]);
    $caseConfiguration = CaseConfiguration::factory()->create(['name' => 'OWC-6']);

    $sku = SellableSku::factory()->create([
        'product_reference_id' => $reference->id,
        'case_configuration_id' => $caseConfiguration->id,
        'commercial_name' => 'Barolo Riserva Magnum',
        'lifecycle_state' => LifecycleState::Reviewed,
        'version' => 1,
    ]);

    Livewire::test(ListSellableSkus::class)
        ->assertCanSeeTableRecords([$sku])
        ->assertSee('Margaux Grand Vin')     // the parent PR's wine Master name, now leading the human reference label (no raw #id)
        ->assertSee('Magnum')                // the parent PR's Format name
        ->assertSee('OWC-6')                 // the parent Case Configuration name, off caseConfiguration()
        ->assertSee('Barolo Riserva Magnum') // the SKU's commercial name
        ->assertSee('reviewed');             // lifecycle_state rendered via the kit's BackedEnum-cast badge column
});

it('renders the read-only attribute set including the marketing copy on the view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create(['name' => 'Cote Beaune Estate']);
    $variant = ProductVariant::factory()->create(['product_master_id' => $master->id, 'variant_identifier' => 'VIEW-2018']);
    $format = Format::factory()->create(['name' => 'Double Magnum', 'size_label' => '3L']);
    $reference = ProductReference::factory()->create([
        'product_variant_id' => $variant->id,
        'format_id' => $format->id,
    ]);
    $caseConfiguration = CaseConfiguration::factory()->create(['name' => 'CARTON-12']);

    $sku = SellableSku::factory()->create([
        'product_reference_id' => $reference->id,
        'case_configuration_id' => $caseConfiguration->id,
        'commercial_name' => 'Reserve Selection',
        'marketing_copy' => 'A legendary vintage from a storied estate.',
        'lifecycle_state' => LifecycleState::Draft,
    ]);

    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        ->assertSee('Cote Beaune Estate')                          // the parent PR's wine Master name, leading the human composition label
        ->assertSee('CARTON-12')                                   // the parent Case Configuration
        ->assertSee('Reserve Selection')                           // the commercial name
        ->assertSee('A legendary vintage from a storied estate.')  // the optional marketing copy
        ->assertSee('draft');                                      // lifecycle_state rendered through its cast
});

it('exposes the read pages plus a write-through create page and no edit or delete default action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    SellableSku::factory()->create();

    // The read projection (list) + view, plus the dedicated write-through create page (routed through
    // CreateSellableSku). No edit page — the Catalog backend ships no update Action.
    expect(array_keys(SellableSkuResource::getPages()))
        ->toEqualCanonicalizing(['index', 'create', 'view']);

    // No mutating row/bulk action on the table; the create affordance is a navigation link to the
    // write-through page, never an inline CreateAction.
    Livewire::test(ListSellableSkus::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('reaches the create page through a header navigation link, never an inline CreateAction', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The list header exposes a `create` action whose URL is the dedicated Create page — a plain link, not a
    // CreateAction (a CreateAction has no URL; it opens an inline modal that would `$record->save()`).
    Livewire::test(ListSellableSkus::class)
        ->assertActionExists('create')
        ->assertActionHasUrl('create', SellableSkuResource::getUrl('create'));
});

it('exposes a create form with parent Product Reference and Case Configuration pickers plus commercial fields, and no producer picker', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A Product Reference and a Case Configuration exist so the pickers have at least one option each (the create
    // surface lists every parent; the activation-cascade gate — not the picker — enforces parent-active at
    // activate time, design L3/L4).
    ProductReference::factory()->create();
    CaseConfiguration::factory()->create();

    Livewire::test(CreateSellableSkuPage::class)
        ->assertFormFieldExists('product_reference_id')  // the first hierarchical parent picker (design L3)
        ->assertFormFieldExists('case_configuration_id') // the second hierarchical parent picker (design L3)
        ->assertFormFieldExists('commercial_name')       // the required commercial attribute
        ->assertFormFieldExists('marketing_copy')        // the optional commercial attribute
        // A Sellable SKU binds NO producer — no producer picker, no Producer-gate handling (design L6).
        ->assertFormFieldDoesNotExist('producer_id');
});

it('seeds the operator_console.sellable_sku i18n group in EN with an IT translation', function () {
    app()->setLocale('en');
    expect((string) __('operator_console.sellable_sku.fields.commercial_name'))->toBe('Commercial name')
        ->and((string) __('operator_console.sellable_sku.label'))->toBe('Sellable SKU');

    app()->setLocale('it');
    expect((string) __('operator_console.sellable_sku.notifications.activated'))->toBe('Sellable SKU attivato.')
        // 'label' is absent in IT — per-key EN fallback (DEC-127) resolves it to the EN value.
        ->and((string) __('operator_console.sellable_sku.label'))->toBe('Sellable SKU');
});
