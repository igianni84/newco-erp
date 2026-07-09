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
// THE TWO UI-REACHABLE REJECTS (design D5; parties-hero-package-residuals design R4): `renew`'s gate is compound —
// `lapsed` AND within the 30-day grace AND a free Hero-Package seat — but the visibility predicate can only check
// `state == lapsed`; BOTH other sub-gates are domain-internal. So a Lapsed Profile is ALWAYS offered `renew`, the
// domain rejects on whichever sub-gate bites first (grace strictly before capacity — RenewProfile's documented gate
// order), and surfaceLifecycleOutcome surfaces the `action_failed` danger notification carrying THAT gate's own
// localized reason. `renew` is the sole VERB whose rejects reach this page, and it has TWO of them: past-grace, and
// within-grace into a Club at capacity. Every other illegal transition is hidden, so its reject is proven by a domain
// toThrow + assertActionHidden, never an action_failed the page can't raise (the Filament hidden-action landmine,
// lessons.md 2026-06-22).
//
// THE EVENT SURFACE (verified in the Action bodies): lapse / renew / deactivate each record exactly one ROOT § 15.2
// event when directly invoked — ProfileExpired / ProfileRenewed / ProfileInactive — carrying the operator audit
// envelope (newco_ops + the operator id) resolved from the `operator` guard via ActorContext; cancel records none. The
// console constructs no envelope itself.
//
// DatabaseMigrations (mirroring ProfileStatusConsoleTest): each console action drives a real domain action opening
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
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

/**
 * Every toast the last Livewire request sent, oldest first — title, status AND body.
 *
 * `assertNotified()` cannot express the capacity reject below: given a STRING it matches the TITLE only, and the
 * refusal's title is the shared `action_failed` while the fact under test lives in the BODY. It also PULLS (its
 * `mount()` claims the session key), so a snapshot taken after it is always empty — read this BEFORE any
 * `assertNotified()`. And on every Livewire `dehydrate` the notifications provider MOVES `filament.notifications`
 * onto `filament.claimed_notifications` (a `put`, so each request overwrites the last claim), hence: claimed key
 * first, unclaimed as the fallback.
 *
 * Named distinctly from `ProfileApprovalConsoleTest`'s `approvalConsoleToasts()` and
 * `SurfacesDomainActionsOutcomeTest`'s `consoleOutcomeToasts()`: Pest `include`s every selected test file into ONE
 * process while building the suite, so two global helpers may never share a name — a duplicate is a fatal redeclare
 * that kills the whole run before any test executes, not a shadow.
 *
 * @return list<array{title: ?string, status: ?string, body: ?string}>
 */
function lifecycleConsoleToasts(): array
{
    /** @var mixed $sent */
    $sent = session()->get('filament.claimed_notifications')
        ?? session()->get('filament.notifications', []);

    if (! is_array($sent)) {
        return [];
    }

    $toasts = [];

    foreach ($sent as $toast) {
        if (! is_array($toast)) {
            continue;
        }

        $toasts[] = [
            'title' => is_string($toast['title'] ?? null) ? $toast['title'] : null,
            'status' => is_string($toast['status'] ?? null) ? $toast['status'] : null,
            'body' => is_string($toast['body'] ?? null) ? $toast['body'] : null,
        ];
    }

    return $toasts;
}

function lifecycleConsoleTitle(string $key): string
{
    return (string) __("operator_console.profile.notifications.{$key}");
}

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

it('rejects a past-grace renew through the page — the FIRST of renew\'s two UI-reachable rejects: action_failed + state unchanged + no event (design D5)', function () {
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

/*
|--------------------------------------------------------------------------
| The Hero-Package capacity reject (parties-hero-package-residuals task 3.2, design R4)
|
| `renew`'s SECOND UI-reachable reject, and the one the surface was blind to. A `lapsed` Profile released its seat,
| so `lapsed → active` RE-CONSUMES one and is capacity-gated (RenewProfile, design D9) — but no visibility predicate
| can see a seat count, so the verb is offered anyway and the domain refuses. Unlike `approve`, the refusal has no
| second lawful outcome to fall through to: canon draws NO `lapsed → waiting_list` edge, and diverting would discard
| `lapsed_at` and burn the grace clock the member is still entitled to (ProfileRenewalCapacityGateTest, claim 2).
|
| WHAT THIS PIN ALONE HOLDS — the verb stays VISIBLE against a FULL Club. The repo drives `renew` through the page
| three times (the happy renew, the past-grace reject, ProfileMembershipChainTest) and the visibility sweep once, and
| every one of them runs against an UNCAPPED Club: a console-side "helpfully hide renew when the Club is full" gate
| would leave all four green while making the domain's own refusal unreachable — the exact inversion design D5 forbids.
|
| WHAT IS DOMINATED, and kept because the requirement names it (the 2.2 corollary): the `action_failed` TITLE is
| dominated by the past-grace test above (same catch branch, same title) and by SurfacesDomainActionsOutcomeTest:224;
| `status: danger` by SurfacesDomainActionsOutcomeTest:210 and ProfileApprovalConsoleTest:395; the BODY-carries-the-
| exception-message mechanism by those same two; and the message's own parameters (`lapsed`, 1 of 1) by
| ProfileRenewalCapacityGateTest:137. They are asserted as ONE exact-array `toBe` — which additionally pins that
| EXACTLY ONE toast was sent, a claim no chained `and()` makes.
|--------------------------------------------------------------------------
*/

it('rejects a within-grace renew into a Club at capacity through the page — renew stays VISIBLE, the danger toast carries the capacity reason, lapsed_at survives (design D5/R4)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A Club at EXACT parity: one Hero-Package seat, one `Active` member holding it (only `active` + `suspended`
    // memberships occupy a seat). Capacity is set INLINE through config — never through the environment (an active
    // PARTIES_HERO_PACKAGE_CAPACITY would cap the whole suite) and never through a new global helper, since
    // `renewalSeatClubTo()` / `approvalConsoleSeatClubTo()` are already taken process-wide.
    $club = Club::factory()->create();
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => 1]);
    Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::Active]);

    // The subject: lapsed WITHIN the 30-day grace, in that same full Club — so the grace sub-gate PASSES and the
    // capacity gate is the one that bites (RenewProfile evaluates grace strictly first). A `lapsed` Profile holds no
    // seat, so the Club sits at 1 of 1. `lapsed_at` is anchored on a whole second: SQLite persists no microseconds,
    // and the `equalTo()` round-trip below compares instants.
    $lapsedAt = CarbonImmutable::now()->startOfSecond();
    $profile = Profile::factory()->create([
        'club_id' => $club->id,
        'state' => ProfileState::Lapsed,
        'lapsed_at' => $lapsedAt,
    ]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        // VISIBLE at capacity — the predicate sees only `state == lapsed` (design D5). This is the assertion the rest
        // of the repository cannot make: every other page-driven renew runs against an uncapped Club.
        ->assertActionVisible('renew')
        ->callAction('renew');

    // Read the toasts BEFORE any assertNotified() — that helper PULLS the session key, and it can see neither the
    // status nor the body, which is where the capacity reason lives. Exactly one toast: the domain's own localized
    // refusal, surfaced verbatim as the danger body under the console's shared `action_failed` title.
    expect(lifecycleConsoleToasts())->toBe([[
        'title' => lifecycleConsoleTitle('action_failed'),
        'status' => 'danger',
        'body' => (string) __('parties.profile.club_at_capacity', [
            'state' => 'lapsed',
            'capacity' => 1,
            'occupied' => 1,
        ]),
    ]]);

    // The rejecting transaction rolled back before any write. `state === lapsed` IS the never-waitlisted assertion
    // (the states are exclusive), and `lapsed_at` intact is the load-bearing one: a divert to `waiting_list` would
    // have cleared it and burned the member's remaining grace. No event was recorded — the factories drive no Action.
    $persisted = Profile::findOrFail($profile->id);
    expect($persisted->state)->toBe(ProfileState::Lapsed)
        ->and($persisted->lapsed_at?->equalTo($lapsedAt))->toBeTrue()
        ->and(DomainEvent::query()->count())->toBe(0);
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
