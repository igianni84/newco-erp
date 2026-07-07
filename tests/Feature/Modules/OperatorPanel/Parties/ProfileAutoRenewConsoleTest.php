<?php

// Task 6.2 (parties-module-k-br-guards; design D8; party-registry — Profile Auto-Renewal Preference) — the Profile
// console's AUTO-RENEW PREFERENCE surface on ViewProfile. Pins the operator affordance for Profile-5 (canon
// MVP-DEC-022, CML-89 sub-7): a `setAutoRenew` header action carrying a Toggle that, on submit, drives the audit-only
// SetProfileAutoRenew action by the Profile id and NEVER writes the model itself (the no-Eloquent-write rule). The
// domain contract (inherit-at-creation + the operator override + the audit-only no-event property) is pinned in
// ProfileAutoRenewTest / ProfileTest; this file pins its CONSOLE realization.
//
// AUDIT-ONLY (§ 15.2 names no `auto_renew` event — design D8): the write records NO domain event, so every "no event"
// assertion here is a clean zero (the factory-built Profile records none either). Unlike the lifecycle verbs, the
// preference is settable in ANY state (§ Profile-5 imposes no from-state restriction), so the affordance is UNGATED —
// the exact opposite of the group-3/4/5 verbs, each hidden outside its own from-state.
//
// DatabaseMigrations (mirroring ProfileStatusConsoleTest): the console action drives a real domain action opening its
// OWN DB::transaction, so the write commits for real and the re-read observes it; the factory bypasses the action and
// records no event. Parties enums/models/actions are imported freely — the {Models, Actions} carve-out governs
// OperatorPanel PRODUCTION code, not tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages\ViewProfile;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\DomainEvent;
use Filament\Forms\Components\Toggle;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('flips a Profile auto_renew through the ViewProfile toggle, driving SetProfileAutoRenew — persists, records no domain event', function (bool $from, bool $to) {
    actingAs(Operator::factory()->create(), 'operator');

    // A factory Profile with a known auto_renew (bypasses CreateProfile → records no event).
    $profile = Profile::factory()->create(['auto_renew' => $from]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        // The Toggle carries the new value; the action drives SetProfileAutoRenew(profileId, autoRenew) through
        // surfaceLifecycleOutcome (never an Eloquent write) and surfaces the success outcome.
        ->callAction('setAutoRenew', ['auto_renew' => $to])
        ->assertNotified((string) __('operator_console.profile.notifications.auto_renew_set'));

    // The preference flipped and persisted (the re-read exercises the boolean cast) …
    expect(Profile::findOrFail($profile->id)->auto_renew)->toBe($to)
        // … and the write is AUDIT-ONLY: no domain event at all (§ 15.2 names none for `auto_renew`).
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'true → false' => [true, false],
    'false → true' => [false, true],
]);

it('exposes the auto-renew affordance as a Toggle', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $profile = Profile::factory()->create(['auto_renew' => true]);

    // Mounting the header action reveals its form: an `auto_renew` field that is a Toggle — the `Toggle $field`
    // type-hint TypeErrors on any other component, so it doubles as the "it's a toggle, not a plain button/field"
    // proof (the 6.1 Select type-hint idiom). The `->default(fn)` closure reading the record is exercised by the
    // mount itself (a wiring error there would fail this test).
    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        ->mountAction('setAutoRenew')
        ->assertFormFieldExists('auto_renew', fn (Toggle $field): bool => true);
});

it('shows the auto-renew affordance in every Profile state (a preference, not a from-state-gated verb)', function (ProfileState $state) {
    actingAs(Operator::factory()->create(), 'operator');

    // Profile-5 imposes no from-state restriction on the preference, so the affordance is visible in EVERY membership
    // state — the exact opposite of the lifecycle verbs, each hidden outside its own from-state (design D4).
    $profile = Profile::factory()->create(['state' => $state]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        ->assertActionVisible('setAutoRenew');
})->with([
    'applied' => [ProfileState::Applied],
    'active' => [ProfileState::Active],
    'suspended' => [ProfileState::Suspended],
    'lapsed' => [ProfileState::Lapsed],
    'cancelled' => [ProfileState::Cancelled],
    'inactive' => [ProfileState::Inactive],
]);
