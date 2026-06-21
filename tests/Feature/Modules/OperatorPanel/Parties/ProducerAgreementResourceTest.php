<?php

// Task 7.1–7.3 (operator-console-parties-supply-side; design D2/D6/D7; ADR 2026-06-19 + 2026-06-20 + 2026-06-21)
// — the operator console's READ-ONLY ProducerAgreement surface, the THIRD Parties (Module K) console built as the
// non-catalog trait-reuse pattern. These assertions pin: an authenticated operator sees the paginated list with
// each agreement's required Producer + scoped Club (or the `producer_wide` placeholder when the agreement is
// Producer-wide) + `status` badge (rendered through the BackedEnum cast, no `Parties\Enums` import on the read
// path); the view page renders the read-only attribute set incl. the settlement cadence; and the resource exposes
// the read pages plus a write-through create page but NO edit/delete default action (read-projection +
// write-through-actions, the capability's foundational discipline).
//
// Parties models are imported freely here: the {Models, Actions, Enums} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests. Seeding uses the factory (a read surface does not care how a row was
// created — the factory bypasses CreateProducerAgreement and records no event).

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource\Pages\ListProducerAgreements;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource\Pages\ViewProducerAgreement;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\ProducerAgreement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists ProducerAgreements with their producer, scoped club and status', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $producer = Producer::factory()->create(['name' => 'Domaine Agreement']);
    $club = Club::factory()->create(['display_name' => 'Cercle Scoped', 'producer_id' => $producer->id]);

    $agreement = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => $club->id,
    ]);

    Livewire::test(ListProducerAgreements::class)
        ->assertCanSeeTableRecords([$agreement])
        ->assertSee('Domaine Agreement')   // the required Producer name (within-Parties `producer()` read)
        ->assertSee('Cercle Scoped')       // the scoped Club display_name (within-Parties `club()` read)
        ->assertSee('draft');              // `status` via the BackedEnum-cast badge column (born draft)
});

it('renders the Producer-wide placeholder for an agreement with no scoped club', function () {
    app()->setLocale('en');
    actingAs(Operator::factory()->create(), 'operator');

    // The factory defaults club_id to null — a Producer-wide agreement (§ 4.6).
    $agreement = ProducerAgreement::factory()->create(['club_id' => null]);

    Livewire::test(ListProducerAgreements::class)
        ->assertCanSeeTableRecords([$agreement])
        ->assertSee('Producer-wide');      // the `producer_wide` placeholder the club column renders for null
});

it('renders the read-only attributes including the settlement cadence on the view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $producer = Producer::factory()->create(['name' => 'Château Infolist']);

    $agreement = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => null,
        'settlement_cadence' => 'quarterly',
    ]);

    Livewire::test(ViewProducerAgreement::class, ['record' => $agreement->getKey()])
        ->assertSee('Château Infolist')    // the required Producer
        ->assertSee('quarterly')           // the settlement cadence (infolist-only field)
        ->assertSee('draft');              // status via the cast
});

it('exposes the read pages plus a write-through create page and no edit or delete default action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    ProducerAgreement::factory()->create();

    // The read projection (list) + view, plus the dedicated write-through create page (routed through
    // CreateProducerAgreement). No edit page — the Parties backend ships no agreement update Action.
    expect(array_keys(ProducerAgreementResource::getPages()))
        ->toEqualCanonicalizing(['index', 'create', 'view']);

    // No mutating row/bulk action on the table; the create affordance is a navigation link to the
    // write-through page, never an inline CreateAction.
    Livewire::test(ListProducerAgreements::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('reaches the create page through a header navigation link, never an inline CreateAction', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The list header exposes a `create` action whose URL is the dedicated Create page — a plain link, not a
    // CreateAction (a CreateAction has no URL; it opens an inline modal that would `$record->save()`).
    Livewire::test(ListProducerAgreements::class)
        ->assertActionExists('create')
        ->assertActionHasUrl('create', ProducerAgreementResource::getUrl('create'));
});

it('seeds the operator_console.producer_agreement i18n group in EN with an IT translation', function () {
    app()->setLocale('en');
    expect((string) __('operator_console.producer_agreement.columns.producer'))->toBe('Producer')
        ->and((string) __('operator_console.producer_agreement.label'))->toBe('Producer agreement');

    app()->setLocale('it');
    expect((string) __('operator_console.producer_agreement.columns.producer'))->toBe('Produttore')
        // 'label' is absent in IT — per-key EN fallback (DEC-127) resolves it to the EN value.
        ->and((string) __('operator_console.producer_agreement.label'))->toBe('Producer agreement');
});
