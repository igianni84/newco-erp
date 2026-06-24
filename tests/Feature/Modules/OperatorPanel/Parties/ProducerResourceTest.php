<?php

// Task 1.1–1.3 (operator-console-parties-producer; design D2/D7; ADR 2026-06-19 + 2026-06-20) — the operator
// console's READ-ONLY Producer surface, the FIRST Parties console built on the shared kit (the non-catalog
// trait-reuse pattern). These assertions pin: an authenticated operator sees the paginated list with each
// Producer's identity attributes + its `status` and `kyc_status` badges (the two FSMs — a nullable `kyc_status`
// renders blank when never screened); the view page renders the read-only attribute set incl. the translatable
// description and the operated-Clubs read; and the resource exposes the read pages plus a write-through create
// page but NO edit/delete default action (read-projection + write-through-actions, the capability's
// foundational discipline — enforced structurally by the kit, surfaced here).
//
// Parties enums/models are imported freely here: the {Models, Actions} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests. Seeding uses the factory (a read surface does not care how a row
// was created — the factory records no event).

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\Pages\ListProducers;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\Pages\ViewProducer;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Models\Producer;
use App\Platform\I18n\TranslatableText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists Producers with their identity attributes, status and kyc_status', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $active = Producer::factory()->create([
        'name' => 'Domaine Read-Surface',
        'region' => 'Côte de Nuits',
        'country' => 'France',
        'status' => ProducerStatus::Active,
        'kyc_status' => KycStatus::Verified,
    ]);

    // A never-screened Producer: the factory leaves `kyc_status` unset (NULL), which the badge column renders
    // as the empty string rather than crashing on a missing enum.
    $neverScreened = Producer::factory()->create([
        'name' => 'Weingut Unscreened',
        'status' => ProducerStatus::Draft,
    ]);

    expect($neverScreened->kyc_status)->toBeNull();

    Livewire::test(ListProducers::class)
        ->assertCanSeeTableRecords([$active, $neverScreened])
        ->assertSee('Domaine Read-Surface')
        ->assertSee('Côte de Nuits')
        ->assertSee('France')
        ->assertSee('active')      // `status` rendered via the BackedEnum-cast badge column
        ->assertSee('verified')    // `kyc_status` rendered via the nullable BackedEnum-cast badge column
        ->assertSee('draft');
});

it('renders the read-only identity attributes on the view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $producer = Producer::factory()->create([
        'name' => 'Château View',
        'region' => 'Pauillac',
        'country' => 'France',
        'appellation' => 'Pauillac AOC',
        'website' => 'https://chateau-view.example',
        'description' => TranslatableText::of(['en' => 'A storied left-bank estate.']),
    ]);

    // The operated Clubs and the Producer's agreements now live in interactive relation-manager sub-tables on the
    // view page (operator-console UI pass, 2026-06-24) — covered by OperatorConsoleUiPassTest, no longer the infolist.
    Livewire::test(ViewProducer::class, ['record' => $producer->getKey()])
        ->assertSee('Château View')
        ->assertSee('Pauillac')
        ->assertSee('France')
        ->assertSee('Pauillac AOC')
        ->assertSee('https://chateau-view.example')
        ->assertSee('A storied left-bank estate.');   // the translatable description resolved for the locale
});

it('exposes the read pages plus a write-through create page and no edit or delete default action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    Producer::factory()->create();

    // The read projection (list) + view, plus the dedicated write-through create page (routed through
    // CreateProducer). No edit page — the Parties backend ships no Producer update Action.
    expect(array_keys(ProducerResource::getPages()))
        ->toEqualCanonicalizing(['index', 'create', 'view']);

    // No mutating row/bulk action on the table; the create affordance is a navigation link to the
    // write-through page, never an inline CreateAction.
    Livewire::test(ListProducers::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('reaches the create page through a header navigation link, never an inline CreateAction', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The list header exposes a `create` action whose URL is the dedicated Create page — a plain link, not a
    // CreateAction (a CreateAction has no URL; it opens an inline modal that would `$record->save()`).
    Livewire::test(ListProducers::class)
        ->assertActionExists('create')
        ->assertActionHasUrl('create', ProducerResource::getUrl('create'));
});

it('seeds the operator_console.producer i18n group in EN with an IT translation', function () {
    app()->setLocale('en');
    expect((string) __('operator_console.producer.columns.name'))->toBe('Name')
        ->and((string) __('operator_console.producer.label'))->toBe('Producer');

    app()->setLocale('it');
    expect((string) __('operator_console.producer.columns.name'))->toBe('Nome')
        // 'label' is absent in IT — per-key EN fallback (DEC-127) resolves it to the EN value.
        ->and((string) __('operator_console.producer.label'))->toBe('Producer');
});
