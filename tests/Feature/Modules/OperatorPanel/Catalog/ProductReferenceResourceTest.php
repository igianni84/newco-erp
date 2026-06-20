<?php

// Task 3.2 (operator-console-catalog-spine; design L1/L3; ADR 2026-06-19 + 2026-06-20) — the operator
// console's READ-ONLY Product Reference surface, the SECOND hierarchical spine console (the atomic product key:
// a Product Variant + a Format), built as pure reuse of the kit. These assertions pin: an authenticated operator
// sees the paginated list with each PR's parent Variant (off the within-Catalog variant() relation) and parent
// Format (off format()), plus lifecycle_state + version; the view page renders the read-only attribute set; the
// resource exposes the read pages plus a write-through create page but NO edit/delete default action; and the
// create surface carries TWO parent pickers (Variant + Format — a hierarchical entity, design L3) and NO
// producer picker (design L6).
//
// Catalog enums/models are imported freely here: the {Models, Actions} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests. Seeding uses the factory (a read surface does not care how a row
// was created).

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource\Pages\CreateProductReference as CreateProductReferencePage;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource\Pages\ListProductReferences;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource\Pages\ViewProductReference;
use App\Modules\OperatorPanel\Models\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists Product References with their parent Variant, Format, lifecycle state and version', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = ProductVariant::factory()->create(['variant_identifier' => 'GRAND-CRU-2019']);
    $format = Format::factory()->create(['name' => 'Magnum', 'size_label' => '1.5L']);

    $reference = ProductReference::factory()->create([
        'product_variant_id' => $variant->id,
        'format_id' => $format->id,
        'lifecycle_state' => LifecycleState::Reviewed,
        'version' => 1,
    ]);

    Livewire::test(ListProductReferences::class)
        ->assertCanSeeTableRecords([$reference])
        ->assertSee('GRAND-CRU-2019')  // the parent Variant identifier, off the within-Catalog variant() relation
        ->assertSee('Magnum')          // the parent Format name, off the within-Catalog format() relation
        ->assertSee('reviewed');       // lifecycle_state rendered via the kit's BackedEnum-cast badge column
});

it('renders the read-only attribute set on the view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = ProductVariant::factory()->create(['variant_identifier' => 'VIEW-2018']);
    $format = Format::factory()->create(['name' => 'Double Magnum', 'size_label' => '3L']);

    $reference = ProductReference::factory()->create([
        'product_variant_id' => $variant->id,
        'format_id' => $format->id,
        'lifecycle_state' => LifecycleState::Draft,
    ]);

    Livewire::test(ViewProductReference::class, ['record' => $reference->getKey()])
        ->assertSee('VIEW-2018')         // the parent Variant, off the within-Catalog relation
        ->assertSee('Double Magnum')     // the parent Format, off the within-Catalog relation
        ->assertSee('draft');            // lifecycle_state rendered through its cast
});

it('exposes the read pages plus a write-through create page and no edit or delete default action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    ProductReference::factory()->create();

    // The read projection (list) + view, plus the dedicated write-through create page (routed through
    // CreateProductReference). No edit page — the Catalog backend ships no update Action.
    expect(array_keys(ProductReferenceResource::getPages()))
        ->toEqualCanonicalizing(['index', 'create', 'view']);

    // No mutating row/bulk action on the table; the create affordance is a navigation link to the
    // write-through page, never an inline CreateAction.
    Livewire::test(ListProductReferences::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('reaches the create page through a header navigation link, never an inline CreateAction', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The list header exposes a `create` action whose URL is the dedicated Create page — a plain link, not a
    // CreateAction (a CreateAction has no URL; it opens an inline modal that would `$record->save()`).
    Livewire::test(ListProductReferences::class)
        ->assertActionExists('create')
        ->assertActionHasUrl('create', ProductReferenceResource::getUrl('create'));
});

it('exposes a create form with parent Product Variant and Format pickers, and no producer picker', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A Variant and a Format exist so the pickers have at least one option each (the create surface lists every
    // parent; the activation-cascade gate — not the picker — enforces parent-active at activate time, design
    // L3/L4).
    ProductVariant::factory()->create();
    Format::factory()->create();

    Livewire::test(CreateProductReferencePage::class)
        ->assertFormFieldExists('product_variant_id')  // the first hierarchical parent picker (design L3)
        ->assertFormFieldExists('format_id')           // the second hierarchical parent picker (design L3)
        // A Product Reference binds NO producer — no producer picker, no Producer-gate handling (design L6).
        ->assertFormFieldDoesNotExist('producer_id');
});

it('seeds the operator_console.product_reference i18n group in EN with an IT translation', function () {
    app()->setLocale('en');
    expect((string) __('operator_console.product_reference.fields.product_variant'))->toBe('Product Variant')
        ->and((string) __('operator_console.product_reference.label'))->toBe('Product Reference');

    app()->setLocale('it');
    expect((string) __('operator_console.product_reference.notifications.activated'))->toBe('Product Reference attivato.')
        // 'label' is absent in IT — per-key EN fallback (DEC-127) resolves it to the EN value.
        ->and((string) __('operator_console.product_reference.label'))->toBe('Product Reference');
});
