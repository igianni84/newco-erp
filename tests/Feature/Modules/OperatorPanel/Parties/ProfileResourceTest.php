<?php

// Group 1 (operator-console-parties-membership; design D1/D2/D3) — the operator console's READ-ONLY
// ProfileResource, the demand-side MEMBERSHIP surface whose list doubles as the cross-Customer approval queue.
// These assertions pin: a seeded Profile renders in the list with its customer (email + name) / club / `state`
// badge (state via the BackedEnum cast — no `Parties\Enums` import on the read path); the default "Pending" tab
// surfaces ONLY `applied` Profiles (the approval queue) and the "All" tab every state; the resource exposes
// index/create/view and NO edit/delete/bulk default action (read-projection — the capability's foundational
// discipline); and the read-only attribute set renders on the view page.
//
// Parties enums/models are imported freely here: the {Models, Actions, Enums} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests. Seeding uses the factory (a read surface does not care how a row was
// created — the factory bypasses CreateProfile and records no event).

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages\ListProfiles;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages\ViewProfile;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists a Profile with its customer, club and state badge', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $customer = Customer::factory()->create(['email' => 'applicant@example.test', 'name' => 'Applicant One']);
    $club = Club::factory()->create(['display_name' => 'Grand Cru Club']);
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Applied,
    ]);

    Livewire::test(ListProfiles::class)
        ->assertCanSeeTableRecords([$profile])
        ->assertSee('applicant@example.test')   // `customer` column — the email primary
        ->assertSee('Applicant One')            // `customer` column — the name secondary line
        ->assertSee('Grand Cru Club')           // `club` column — the Club display name
        ->assertSee('applied');                 // `state` badge via the BackedEnum cast
});

it('defaults to the Pending approval queue of applied Profiles, and the All tab shows every state', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $applied = Profile::factory()->create(['state' => ProfileState::Applied]);
    $approved = Profile::factory()->create(['state' => ProfileState::Approved]);
    $active = Profile::factory()->create(['state' => ProfileState::Active]);

    Livewire::test(ListProfiles::class)
        // "Pending" is the default active tab — only `applied` Profiles (the approval queue).
        ->set('activeTab', 'pending')
        ->assertCanSeeTableRecords([$applied])
        ->assertCanNotSeeTableRecords([$approved, $active])
        // "All" drops the filter — every membership state.
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$applied, $approved, $active]);
});

it('exposes index/create/view pages and no edit, delete or bulk default action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    Profile::factory()->create(['state' => ProfileState::Applied]);

    // The read projection (list) + view, plus the dedicated write-through create page (routed through
    // CreateProfile). No edit page — the Parties backend ships no Profile attribute-update Action.
    expect(array_keys(ProfileResource::getPages()))
        ->toEqualCanonicalizing(['index', 'create', 'view']);

    // No mutating row/bulk action on the read surface (the lifecycle writes live on ViewProfile, the create on
    // CreateProfile — never an inline Filament mutating action that would `$record->save()`).
    Livewire::test(ListProfiles::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('renders the read-only Profile attributes on the view page', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $customer = Customer::factory()->create(['email' => 'view.applicant@example.test']);
    $club = Club::factory()->create(['display_name' => 'View Club']);
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Approved,
    ]);

    Livewire::test(ViewProfile::class, ['record' => $profile->getKey()])
        ->assertSee('view.applicant@example.test')  // `customer` infolist entry
        ->assertSee('View Club')                    // `club` infolist entry
        ->assertSee('approved');                    // `state` badge on the infolist
});

it('seeds the operator_console.profile i18n group in EN with an IT translation', function () {
    app()->setLocale('en');
    expect((string) __('operator_console.profile.columns.customer'))->toBe('Customer')
        ->and((string) __('operator_console.profile.label'))->toBe('Profile');

    app()->setLocale('it');
    expect((string) __('operator_console.profile.columns.customer'))->toBe('Cliente')
        // 'label' is absent in IT — per-key EN fallback (DEC-127) resolves it to the EN value.
        ->and((string) __('operator_console.profile.label'))->toBe('Profile');
});
