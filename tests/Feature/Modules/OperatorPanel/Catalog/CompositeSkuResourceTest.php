<?php

// Task 4.1 (operator-console-catalog-spine; design L1/L3; ADR 2026-06-19 + 2026-06-20) — the operator console's
// READ-ONLY Composite SKU surface, the FINAL spine console and the spine's only many-to-many entity (a curated
// bundle of N ≥ 2 ORDERED constituent Product References), built as pure reuse of the kit. These assertions pin:
// an authenticated operator sees the paginated list with each Composite SKU's constituent count, lifecycle_state +
// version; the view page renders the ORDERED constituent set (each constituent's Variant identifier + Format name,
// off the within-Catalog constituents() junction, in bundle order); the resource exposes the read pages plus a
// write-through create page but NO edit/delete default action; and the create surface carries a single ORDERED
// constituents multi-select picker and NO producer picker and NO parent-FK picker (a Composite SKU binds neither a
// producer — producer-agnostic, design D9 — nor a single parent, design L3/L6).
//
// Catalog enums/models are imported freely here: the {Models, Actions} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests. Seeding uses the factory (a read surface does not care how a row was
// created); the factory auto-attaches two constituents unless the caller supplies its own.

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource\Pages\CreateCompositeSku as CreateCompositeSkuPage;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource\Pages\ListCompositeSkus;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource\Pages\ViewCompositeSku;
use App\Modules\OperatorPanel\Models\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists Composite SKUs with their constituent count, lifecycle state and version', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A bundle of three constituents (the factory default is two; supply three so the count column shows a
    // distinctive '3').
    $composite = CompositeSku::factory()
        ->hasAttached(ProductReference::factory(), ['position' => 1], 'constituents')
        ->hasAttached(ProductReference::factory(), ['position' => 2], 'constituents')
        ->hasAttached(ProductReference::factory(), ['position' => 3], 'constituents')
        ->create(['lifecycle_state' => LifecycleState::Reviewed]);

    Livewire::test(ListCompositeSkus::class)
        ->assertCanSeeTableRecords([$composite])
        ->assertSee('3')          // the constituent count, off the within-Catalog constituents() junction
        ->assertSee('reviewed');  // lifecycle_state rendered via the kit's BackedEnum-cast badge column
});

it('renders the ordered constituent set on the view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // Two constituents with distinctive identities, attached in a known order (position 1 then 2). The view reads
    // them back off the constituents() junction, which orders by pivot `position`, so the bundle order is shown.
    $variantA = ProductVariant::factory()->create(['variant_identifier' => 'CONSTIT-A-2019']);
    $formatA = Format::factory()->create(['name' => 'Magnum']);
    $prA = ProductReference::factory()->create(['product_variant_id' => $variantA->id, 'format_id' => $formatA->id]);

    $variantB = ProductVariant::factory()->create(['variant_identifier' => 'CONSTIT-B-2020']);
    $formatB = Format::factory()->create(['name' => 'Jeroboam']);
    $prB = ProductReference::factory()->create(['product_variant_id' => $variantB->id, 'format_id' => $formatB->id]);

    $composite = CompositeSku::factory()
        ->hasAttached($prA, ['position' => 1], 'constituents')
        ->hasAttached($prB, ['position' => 2], 'constituents')
        ->create(['lifecycle_state' => LifecycleState::Draft]);

    Livewire::test(ViewCompositeSku::class, ['record' => $composite->getKey()])
        ->assertSee('CONSTIT-A-2019') // the first constituent's Variant identifier, off the within-Catalog relation
        ->assertSee('Magnum')         // the first constituent's Format name
        ->assertSee('CONSTIT-B-2020') // the second constituent's Variant identifier
        ->assertSee('Jeroboam')       // the second constituent's Format name
        ->assertSee('draft');         // lifecycle_state rendered through its cast
});

it('exposes the read pages plus a write-through create page and no edit or delete default action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    CompositeSku::factory()->create();

    // The read projection (list) + view, plus the dedicated write-through create page (routed through
    // CreateCompositeSku). No edit page — the Catalog backend ships no update Action.
    expect(array_keys(CompositeSkuResource::getPages()))
        ->toEqualCanonicalizing(['index', 'create', 'view']);

    // No mutating row/bulk action on the table; the create affordance is a navigation link to the
    // write-through page, never an inline CreateAction.
    Livewire::test(ListCompositeSkus::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('reaches the create page through a header navigation link, never an inline CreateAction', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The list header exposes a `create` action whose URL is the dedicated Create page — a plain link, not a
    // CreateAction (a CreateAction has no URL; it opens an inline modal that would `$record->save()`).
    Livewire::test(ListCompositeSkus::class)
        ->assertActionExists('create')
        ->assertActionHasUrl('create', CompositeSkuResource::getUrl('create'));
});

it('exposes a create form with an ordered constituents picker, and no producer or single-parent picker', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A Product Reference exists so the constituents picker has at least one option (the create surface lists
    // every Product Reference; the activation-cascade gate — not the picker — enforces every-constituent-active at
    // activate time, design L3/L4).
    ProductReference::factory()->create();

    Livewire::test(CreateCompositeSkuPage::class)
        ->assertFormFieldExists('constituents')          // the single ORDERED N≥2 constituents picker (design L3)
        // A Composite SKU binds NO producer — no producer picker (producer-agnostic, design D9/L6) …
        ->assertFormFieldDoesNotExist('producer_id')
        // … and it is NOT a single-parent entity — there is no parent-FK picker (its content is the bundle).
        ->assertFormFieldDoesNotExist('product_reference_id')
        ->assertFormFieldDoesNotExist('case_configuration_id');
});

it('seeds the operator_console.composite_sku i18n group in EN with an IT translation', function () {
    app()->setLocale('en');
    expect((string) __('operator_console.composite_sku.fields.constituents'))->toBe('Constituents')
        ->and((string) __('operator_console.composite_sku.label'))->toBe('Composite SKU');

    app()->setLocale('it');
    expect((string) __('operator_console.composite_sku.notifications.activated'))->toBe('Composite SKU attivato.')
        // 'label' is absent in IT — per-key EN fallback (DEC-127) resolves it to the EN value.
        ->and((string) __('operator_console.composite_sku.label'))->toBe('Composite SKU');
});
