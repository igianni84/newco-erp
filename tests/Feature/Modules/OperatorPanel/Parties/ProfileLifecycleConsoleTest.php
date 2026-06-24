<?php

// Task 5.1 / 5.2 (operator-console-parties-membership; design D4/D5) — the Profile console's lapse / renew / terminal
// surface on ViewProfile. The four form-less verbs the page APPENDS to its SurfacesDomainActions-built header-action
// array, each routing through a Parties domain action by the Profile id and NEVER writing the model itself (the
// no-Eloquent-write rule):
//   - lapse       (`active → lapsed`)            — records ProfileExpired (the STATE is `Lapsed`, the EVENT is
//                                                  ProfileExpired — § 15.2 names no `ProfileLapsed`, design L3).
//   - renew       (`lapsed → active`)            — records ProfileRenewed; permitted ONLY within the 30-day grace of
//                                                  `lapsed_at` (DEC-034, inclusive boundary).
//   - cancel      (`active|lapsed → cancelled`)  — AUDIT-ONLY: writes `state` (+ the optional reason) and records NO
//                                                  domain event (§ 15.2 names no `ProfileCancelled`, design L2). The
//                                                  `Cancelled` row is NEVER hard-deleted — terminal soft-delete
//                                                  (AC-K-FSM-13): a plain query still returns it.
//   - deactivate  (`active → inactive`)          — records ProfileInactive.
//
// EACH VERB IS VISIBILITY-GATED to its from-state(s) (design D4) — the EXACT COMPLEMENT of the domain Action's
// from-state guard. lapse / cancel / deactivate share `active`; renew / cancel share `lapsed`; so a state can surface
// MORE THAN ONE verb at once (the visibility sweep checks a SET per state, not a single verb).
//
// THE ONE UI-REACHABLE REJECT (design D5): `renew`'s gate is compound — `lapsed` AND within the 30-day grace — but the
// visibility predicate can only check `state == lapsed`; the grace sub-gate is domain-internal. So a Lapsed-but-past-
// grace renew is VISIBLE, the domain rejects it, and surfaceLifecycleOutcome surfaces the `action_failed` danger
// notification. This is the SOLE reject testable through the page; every other illegal transition is hidden, so its
// reject is proven by a domain toThrow + assertActionHidden, never an action_failed the page can't raise (the Filament
// hidden-action landmine, lessons.md 2026-06-22).
//
// THE EVENT SURFACE (verified in the Action bodies): lapse / renew / deactivate each record exactly one ROOT § 15.2
// event when directly invoked — ProfileExpired / ProfileRenewed / ProfileInactive — carrying the operator audit
// envelope (newco_ops + the operator id) resolved from the `operator` guard via ActorContext; cancel records none. The
// console constructs no envelope itself.
//
// DatabaseMigrations (mirroring ProfileActivationConsoleTest): each console action drives a real domain action opening
// its OWN DB::transaction, so the DomainEventRecorder's in-transaction append commits for real (RefreshDatabase would
// wrap every write in a never-committed outer transaction). The factories bypass the actions → they record no event.
// Parties enums/models/exceptions/events are imported freely here: the {Models, Actions} import-boundary carve-out
// governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages\ViewProfile;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Actions\CancelProfile;
use App\Modules\Parties\Actions\DeactivateProfile;
use App\Modules\Parties\Actions\LapseProfile;
use App\Modules\Parties\Actions\RenewProfile;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileExpired;
use App\Modules\Parties\Events\ProfileInactive;
use App\Modules\Parties\Events\ProfileRenewed;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('lapses an Active Profile through the console — lapsed + one ProfileExpired with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // An `active` Profile (the factory bypasses the lifecycle actions → records no event), so the lapse verb's lone
    // ProfileExpired is the only event.
    $profile = Profile::factory()->create(['state' => ProfileState::Active]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        // callAction asserts-visible-first (lapse is visible iff `active`), then drives the form-less verb into
        // LapseProfile by the Profile id — the console writes nothing itself (the no-Eloquent-write rule).
        ->callAction('lapse')
        ->assertNotified((string) __('operator_console.profile.notifications.lapsed'));

    // State advanced active → lapsed via the domain action (the console never writes `state`).
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Lapsed);

    // Exactly one ProfileExpired — a Profile-state ROOT event (the STATE is `Lapsed`, the EVENT is ProfileExpired,
    // design L3) — carrying the operator audit envelope (newco_ops + the operator id) resolved by the action.
    $event = DomainEvent::query()->where('name', ProfileExpired::NAME)->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Profile')
        ->and($event->entity_id)->toBe((string) $profile->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id);  // loose: PG returns a numeric string for the bigint
});

it('renews a within-grace Lapsed Profile through the console — active + one ProfileRenewed', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // Lapsed WITHIN the 30-day grace window: `lapsed_at = now`, so renewal is permitted (DEC-034 — the boundary is
    // inclusive). The factory bypasses LapseProfile, so no ProfileExpired is recorded — the renew's lone
    // ProfileRenewed is the only event.
    $profile = Profile::factory()->create([
        'state' => ProfileState::Lapsed,
        'lapsed_at' => CarbonImmutable::now(),
    ]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        // renew is visible iff `lapsed`; callAction asserts-visible-first then drives RenewProfile by the id.
        ->callAction('renew')
        ->assertNotified((string) __('operator_console.profile.notifications.renewed'));

    // State restored lapsed → active via the domain action (records ProfileRenewed, NOT ProfileReactivated — the
    // grace restore, design L3).
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active);

    $event = DomainEvent::query()->where('name', ProfileRenewed::NAME)->sole();

    expect($event->entity_type)->toBe('Profile')
        ->and($event->entity_id)->toBe((string) $profile->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps);
});

it('rejects a past-grace renew through the page — the sole UI-reachable reject: action_failed + state unchanged + no event (design D5)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // Lapsed 31 days ago — PAST the inclusive 30-day grace boundary (DEC-034): the grace deadline (`lapsed_at` + 30d)
    // is now one day in the past, so RenewProfile rejects. Anchoring `lapsed_at` in the past (rather than travelling
    // the clock) is the same past-grace condition without test-now mutation — and `$this->travelTo()` does not
    // type-check inside a Pest `it()` closure (PHPStan binds `$this` to TestCall, not the TestCase). `renew` stays
    // VISIBLE: the predicate sees only `state == lapsed`, the grace sub-gate is domain-internal (design D5).
    $profile = Profile::factory()->create([
        'state' => ProfileState::Lapsed,
        'lapsed_at' => CarbonImmutable::now()->subDays(31),
    ]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        // renew is VISIBLE (state == lapsed) — the ONE reject reachable through the surface (design D5). callAction
        // drives it; RenewProfile rejects on the grace sub-gate, and surfaceLifecycleOutcome surfaces the danger
        // action_failed (the rejecting transaction rolled back) — never a success.
        ->assertActionVisible('renew')
        ->callAction('renew')
        ->assertNotified((string) __('operator_console.profile.notifications.action_failed'));

    // The domain rolled back: state stays Lapsed and NO ProfileRenewed event was recorded.
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Lapsed)
        ->and(DomainEvent::query()->where('name', ProfileRenewed::NAME)->count())->toBe(0);
});

it('cancels a Profile through the console — cancelled + zero new events (audit-only) + the row stays queryable (soft-delete, AC-K-FSM-13)', function (ProfileState $from) {
    actingAs(Operator::factory()->create(), 'operator');

    // Cancel is reachable from `active` or `lapsed`. A lapsed Profile carries its grace anchor; cancel is the terminal
    // choice (distinct from renew). The factory bypasses the actions → the event log starts empty.
    $attributes = ['state' => $from];
    if ($from === ProfileState::Lapsed) {
        $attributes['lapsed_at'] = CarbonImmutable::now();
    }
    $profile = Profile::factory()->create($attributes);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        // cancel is visible iff `active|lapsed`; callAction asserts-visible-first then drives CancelProfile by the id.
        ->callAction('cancel')
        ->assertNotified((string) __('operator_console.profile.notifications.cancelled'));

    // TERMINAL SOFT-DELETE (AC-K-FSM-13 / AC-K-BR-Profile-2): the Profile is NEVER hard-deleted — a plain query still
    // returns the row (Profile uses no SoftDeletes scope; cancellation only writes `state`).
    expect(Profile::query()->whereKey($profile->id)->exists())->toBeTrue();

    // State advanced → cancelled, and cancellation is AUDIT-ONLY (design L2): the § 15.2 family names no
    // `ProfileCancelled`, so the event log stays empty — the `state = cancelled` write IS the audit record.
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Cancelled)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'active → cancelled' => [ProfileState::Active],
    'lapsed → cancelled' => [ProfileState::Lapsed],
]);

it('deactivates an Active Profile through the console — inactive + one ProfileInactive', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // An `active` Profile; deactivate is the operational corner case off `active` (§ 4.2.1), recording ProfileInactive
    // (UNLIKE cancel, which is audit-only — design L2/L3).
    $profile = Profile::factory()->create(['state' => ProfileState::Active]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        // deactivate is visible iff `active`; callAction asserts-visible-first then drives DeactivateProfile by the id.
        ->callAction('deactivate')
        ->assertNotified((string) __('operator_console.profile.notifications.deactivated'));

    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Inactive);

    $event = DomainEvent::query()->where('name', ProfileInactive::NAME)->sole();

    expect($event->entity_type)->toBe('Profile')
        ->and($event->entity_id)->toBe((string) $profile->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps);
});

it('shows each lifecycle verb only from its own from-state(s) (design D4)', function (ProfileState $from, array $visibleVerbs) {
    actingAs(Operator::factory()->create(), 'operator');

    // lapse / cancel / deactivate gate `active`; renew / cancel gate `lapsed` — each visible iff the page record is in
    // a from-state the verb accepts (the EXACT COMPLEMENT of the Action's from-state guard). A state can therefore
    // surface SEVERAL verbs at once (active → lapse/cancel/deactivate; lapsed → renew/cancel); every other pair hides.
    $profile = Profile::factory()->create(['state' => $from]);

    $component = Livewire::test(ViewProfile::class, ['record' => $profile->id]);

    foreach (['lapse', 'renew', 'cancel', 'deactivate'] as $verb) {
        if (in_array($verb, $visibleVerbs, true)) {
            $component->assertActionVisible($verb);
        } else {
            $component->assertActionHidden($verb);
        }
    }
})->with([
    'applied → none' => [ProfileState::Applied, []],
    'waiting_list → none' => [ProfileState::WaitingList, []],
    'approved → none' => [ProfileState::Approved, []],
    'rejected → none' => [ProfileState::Rejected, []],
    'active → lapse/cancel/deactivate' => [ProfileState::Active, ['lapse', 'cancel', 'deactivate']],
    'suspended → none' => [ProfileState::Suspended, []],
    'lapsed → renew/cancel' => [ProfileState::Lapsed, ['renew', 'cancel']],
    'cancelled → none' => [ProfileState::Cancelled, []],
    'inactive → none' => [ProfileState::Inactive, []],
]);

it('proves the lifecycle-verb reject floor — every verb hidden out of its from-state(s) AND the domain rejects an out-of-band call, state + the event log unchanged (design D4)', function (ProfileState $from) {
    actingAs(Operator::factory()->create(), 'operator');

    // Out of its from-state(s) each verb is hidden (callAction would assert-visible-FIRST and fail), so the reject is
    // proven the only way the surface allows: the surface HIDES the verb AND the domain INDEPENDENTLY rejects an
    // out-of-band call. The bare factory records no event, so "the event log unchanged" is a clean zero.
    $profile = Profile::factory()->create(['state' => $from]);

    $component = Livewire::test(ViewProfile::class, ['record' => $profile->id]);

    // Each verb mapped to its legal from-state(s) and its out-of-band domain invocation (literal `app(X::class)` so the
    // typed `handle(int): Profile` resolves under PHPStan-max — never an `app($variable)` call on an inferred mixed).
    // cancel has TWO legal from-states (active, lapsed); the rest have one.
    $legalFromStatesOf = [
        'lapse' => [ProfileState::Active],
        'renew' => [ProfileState::Lapsed],
        'cancel' => [ProfileState::Active, ProfileState::Lapsed],
        'deactivate' => [ProfileState::Active],
    ];
    $invokeOutOfBand = [
        'lapse' => fn () => app(LapseProfile::class)->handle($profile->id),
        'renew' => fn () => app(RenewProfile::class)->handle($profile->id),
        'cancel' => fn () => app(CancelProfile::class)->handle($profile->id),
        'deactivate' => fn () => app(DeactivateProfile::class)->handle($profile->id),
    ];

    foreach ($legalFromStatesOf as $verb => $legalFroms) {
        if (in_array($from, $legalFroms, true)) {
            continue;  // a legal from-state for this verb — its happy path / visibility / D5 are covered elsewhere.
        }

        // Half 1 — the surface HIDES the verb (the from-state guard's exact complement).
        $component->assertActionHidden($verb);

        // Half 2 — the domain FLOOR: an out-of-band call throws IllegalProfileTransition and rolls back BEFORE any
        // write (each Action guards the from-state inside its transaction). cancel is audit-only but STILL throws here.
        expect($invokeOutOfBand[$verb])->toThrow(IllegalProfileTransition::class);
    }

    // Nothing moved across every out-of-band rejection: the state is exactly as arranged and the event log is still
    // empty (every guarded transaction rolled back).
    expect(Profile::findOrFail($profile->id)->state)->toBe($from)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'applied' => [ProfileState::Applied],
    'waiting_list' => [ProfileState::WaitingList],
    'approved' => [ProfileState::Approved],
    'rejected' => [ProfileState::Rejected],
    'active' => [ProfileState::Active],
    'suspended' => [ProfileState::Suspended],
    'lapsed' => [ProfileState::Lapsed],
    'cancelled' => [ProfileState::Cancelled],
    'inactive' => [ProfileState::Inactive],
]);
