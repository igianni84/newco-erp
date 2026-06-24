<?php

// Task 2.1–2.3 (operator-console-parties-supply-side; design D2/D6/D7; ADR 2026-06-19 + 2026-06-20 + 2026-06-21)
// — the operator console's READ-ONLY Club surface, the SECOND Parties (Module K) console built as the
// non-catalog trait-reuse pattern. These assertions pin: an authenticated operator sees the paginated list with
// each Club's display_name + operating Producer + registration_flow_type + `status` badge (rendered through the
// BackedEnum cast, no `Parties\Enums` import on the read path); the view page renders the read-only attribute set
// incl. the Money fee; and the resource exposes the read pages plus a write-through create page but NO
// edit/delete default action (read-projection + write-through-actions, the capability's foundational discipline).
//
// Parties enums/models are imported freely here: the {Models, Actions, Enums} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests. Seeding uses the factory (a read surface does not care how a row was
// created — the factory bypasses CreateClub and records no event).

use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource\Pages\ListClubs;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource\Pages\ViewClub;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists Clubs with their display name, operating producer, registration flow and status', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $producer = Producer::factory()->create(['name' => 'Domaine Supply-Side']);

    $club = Club::factory()->create([
        'display_name' => 'Premier Cercle',
        'producer_id' => $producer->id,
        'registration_flow_type' => ClubRegistrationFlowType::InvitationOnly,
        'status' => ClubStatus::Active,
    ]);

    Livewire::test(ListClubs::class)
        ->assertCanSeeTableRecords([$club])
        ->assertSee('Premier Cercle')
        ->assertSee('Domaine Supply-Side')   // the operating Producer name (within-Parties `producer()` read)
        ->assertSee('invitation_only')       // registration_flow_type via the BackedEnum cast
        ->assertSee('active');               // `status` via the BackedEnum-cast badge column
});

it('renders the read-only attributes including the fee on the view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $producer = Producer::factory()->create(['name' => 'Château Infolist']);

    $club = Club::factory()->create([
        'display_name' => 'Grand Cercle',
        'producer_id' => $producer->id,
        'registration_flow_type' => ClubRegistrationFlowType::OpenRegistration,
        'fee' => Money::of(50000, Currency::EUR),
        'generates_credit' => true,
        'invite_only' => false,
    ]);

    Livewire::test(ViewClub::class, ['record' => $club->getKey()])
        ->assertSee('Grand Cercle')
        ->assertSee('Château Infolist')      // the operating Producer
        ->assertSee('Open Registration')     // registration_flow_type humanized via Str::headline (premium label, no raw token)
        ->assertSee('500.00');               // the Money fee rendered as human major units + ISO code ("500.00 EUR"), not raw minor units
});

it('exposes the read pages plus a write-through create page and no edit or delete default action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    Club::factory()->create();

    // The read projection (list) + view, plus the dedicated write-through create page (routed through
    // CreateClub). No edit page — the Parties backend ships no Club update Action.
    expect(array_keys(ClubResource::getPages()))
        ->toEqualCanonicalizing(['index', 'create', 'view']);

    // No mutating row/bulk action on the table; the create affordance is a navigation link to the
    // write-through page, never an inline CreateAction.
    Livewire::test(ListClubs::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('reaches the create page through a header navigation link, never an inline CreateAction', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The list header exposes a `create` action whose URL is the dedicated Create page — a plain link, not a
    // CreateAction (a CreateAction has no URL; it opens an inline modal that would `$record->save()`).
    Livewire::test(ListClubs::class)
        ->assertActionExists('create')
        ->assertActionHasUrl('create', ClubResource::getUrl('create'));
});

it('seeds the operator_console.club i18n group in EN with an IT translation', function () {
    app()->setLocale('en');
    expect((string) __('operator_console.club.columns.display_name'))->toBe('Name')
        ->and((string) __('operator_console.club.label'))->toBe('Club');

    app()->setLocale('it');
    expect((string) __('operator_console.club.columns.display_name'))->toBe('Nome')
        // 'label' is absent in IT — per-key EN fallback (DEC-127) resolves it to the EN value.
        ->and((string) __('operator_console.club.label'))->toBe('Club');
});
