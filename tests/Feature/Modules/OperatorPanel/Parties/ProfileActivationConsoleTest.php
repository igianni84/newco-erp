<?php

// Task 4.1 / 4.2 (operator-console-parties-membership; design D4/D9) — the Profile console's membership-STATUS
// surface on ViewProfile. The three form-less verbs the page APPENDS to its SurfacesDomainActions-built header-action
// array, each routing through a Parties domain action by the Profile id and NEVER writing the model itself (the
// no-Eloquent-write rule):
//   - activate    (`approved → active`)    — UNCAPPED: the Hero-Package capacity cap is a deferred Module-A seam
//                                             (design Non-Goals); ActivateProfile is driven as-is, no cap invented.
//   - suspend     (`active → suspended`)   — STATE-PRESERVING: only `Profile.state` moves; a co-existing active Club
//                                             Credit is left entirely untouched (AC-K-FSM-2a — design L9).
//   - reactivate  (`suspended → active`)   — the inverse restore (records `ProfileReactivated`, NOT `ProfileRenewed` —
//                                             that is the deferred lapsed→active grace edge, design L3).
//
// EACH VERB IS VISIBILITY-GATED to its own from-state (design D4) — the EXACT COMPLEMENT of the domain Action's
// from-state guard, so an out-of-state transition is UNREACHABLE through the surface: the verb is simply hidden; its
// reject is proven by a domain toThrow + assertActionHidden, never an action_failed the page can't raise (the Filament
// hidden-action landmine, lessons.md 2026-06-22).
//
// THE EVENT SURFACE (verified in the Action bodies): each status edge records exactly one ROOT § 15.2 event when
// directly invoked — ProfileActivated / ProfileSuspended / ProfileReactivated — carrying the operator audit envelope
// (newco_ops + the operator id) resolved from the `operator` guard via ActorContext. The console constructs no
// envelope itself.
//
// DatabaseMigrations (mirroring ProfileApprovalConsoleTest): each console action drives a real domain action opening
// its OWN DB::transaction, so the DomainEventRecorder's in-transaction append commits for real (RefreshDatabase would
// wrap every write in a never-committed outer transaction). The factories bypass the actions → they record no event.
// Parties enums/models/exceptions/events are imported freely here: the {Models, Actions} import-boundary carve-out
// governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages\ViewProfile;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Actions\ActivateProfile;
use App\Modules\Parties\Actions\ReactivateProfile;
use App\Modules\Parties\Actions\SuspendProfile;
use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileActivated;
use App\Modules\Parties\Events\ProfileReactivated;
use App\Modules\Parties\Events\ProfileSuspended;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\ClubCredit;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('activates an Approved Profile through the console — active + one ProfileActivated with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // An `approved` Profile (the factory bypasses ApproveProfile → records no event), so the activate verb's lone
    // ProfileActivated is the only event. Activation is UNCAPPED (the Hero-Package cap is a deferred Module-A seam).
    $profile = Profile::factory()->create(['state' => ProfileState::Approved]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        // callAction asserts-visible-first (activate is visible iff `approved`), then drives the form-less verb into
        // ActivateProfile by the Profile id — the console writes nothing itself (the no-Eloquent-write rule).
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.profile.notifications.activated'));

    // State advanced approved → active via the domain action (the console never writes `state`).
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active);

    // Exactly one ProfileActivated — a Profile-state ROOT event — carrying the operator audit envelope (newco_ops +
    // the operator id) resolved by the action from the `operator` guard; the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', ProfileActivated::NAME)->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Profile')
        ->and($event->entity_id)->toBe((string) $profile->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id);  // loose: PG returns a numeric string for the bigint
});

it('suspends an Active Profile through the console — suspended + one ProfileSuspended, the active Club Credit untouched (state-preserving, AC-K-FSM-2a)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // An `active` Profile carrying a co-existing `active` Club Credit (seeded straight via the factory — it bypasses
    // IssueClubCredit, so it records no event). Suspension must leave the credit ENTIRELY untouched.
    $profile = Profile::factory()->create(['state' => ProfileState::Active]);
    $credit = ClubCredit::factory()->create(['profile_id' => $profile->id]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        // suspend is visible iff `active`; callAction asserts-visible-first then drives SuspendProfile by the id.
        ->callAction('suspend')
        ->assertNotified((string) __('operator_console.profile.notifications.suspended'));

    // State advanced active → suspended via the domain action …
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Suspended);

    // … and suspension is STATE-PRESERVING (design L9 / § 10.1 / AC-K-FSM-2a): only `Profile.state` moved. The
    // co-existing Club Credit is unchanged — still `active`, same `remaining` balance (the "frozen while suspended"
    // guarantee is enforced at the redemption site, not by a mutation here; SuspendProfile writes ONLY `state`).
    $reloaded = ClubCredit::findOrFail($credit->id);
    expect($reloaded->state)->toBe(ClubCreditState::Active)
        ->and($reloaded->remaining->equals($credit->remaining))->toBeTrue();

    // Exactly one ProfileSuspended (a ROOT Profile-state event) with the operator envelope.
    $event = DomainEvent::query()->where('name', ProfileSuspended::NAME)->sole();

    expect($event->entity_type)->toBe('Profile')
        ->and($event->entity_id)->toBe((string) $profile->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps);
});

it('reactivates a Suspended Profile through the console — active + one ProfileReactivated', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A `suspended` Profile (factory-set, no event). reactivate restores it `suspended → active`, recording
    // ProfileReactivated — NOT ProfileRenewed (that is the deferred lapsed→active grace edge — design L3).
    $profile = Profile::factory()->create(['state' => ProfileState::Suspended]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        // reactivate is visible iff `suspended`; callAction asserts-visible-first then drives ReactivateProfile.
        ->callAction('reactivate')
        ->assertNotified((string) __('operator_console.profile.notifications.reactivated'));

    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active);

    $event = DomainEvent::query()->where('name', ProfileReactivated::NAME)->sole();

    expect($event->entity_type)->toBe('Profile')
        ->and($event->entity_id)->toBe((string) $profile->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps);
});

it('shows each status verb only from its own from-state (design D4)', function (ProfileState $from, ?string $visibleVerb) {
    actingAs(Operator::factory()->create(), 'operator');

    // Each status verb is reachable from exactly ONE from-state (§ 4.2.1): activate iff `approved`, suspend iff
    // `active`, reactivate iff `suspended` — each visible iff the page record is in that state, the EXACT COMPLEMENT
    // of the Action's from-state guard. Every other (verb, state) pair is hidden.
    $profile = Profile::factory()->create(['state' => $from]);

    $component = Livewire::test(ViewProfile::class, ['record' => $profile->id]);

    foreach (['activate', 'suspend', 'reactivate'] as $verb) {
        if ($verb === $visibleVerb) {
            $component->assertActionVisible($verb);
        } else {
            $component->assertActionHidden($verb);
        }
    }
})->with([
    'applied → none' => [ProfileState::Applied, null],
    'waiting_list → none' => [ProfileState::WaitingList, null],
    'approved → activate' => [ProfileState::Approved, 'activate'],
    'rejected → none' => [ProfileState::Rejected, null],
    'active → suspend' => [ProfileState::Active, 'suspend'],
    'suspended → reactivate' => [ProfileState::Suspended, 'reactivate'],
    'lapsed → none' => [ProfileState::Lapsed, null],
    'cancelled → none' => [ProfileState::Cancelled, null],
    'inactive → none' => [ProfileState::Inactive, null],
]);

it('proves the status-verb reject floor — every verb hidden out of its from-state AND the domain rejects an out-of-band call, state + the event log unchanged (design D4)', function (ProfileState $from) {
    actingAs(Operator::factory()->create(), 'operator');

    // Out of its from-state each verb is hidden (callAction would assert-visible-FIRST and fail), so the reject is
    // proven the only way the surface allows: the surface HIDES the verb AND the domain INDEPENDENTLY rejects an
    // out-of-band call. The bare factory records no event, so "the event log unchanged" is a clean zero.
    $profile = Profile::factory()->create(['state' => $from]);

    $component = Livewire::test(ViewProfile::class, ['record' => $profile->id]);

    // Each status verb mapped to its from-state and its out-of-band domain invocation (literal `app(X::class)` so the
    // typed `handle(int): Profile` resolves under PHPStan-max — never an `app($variable)` call on an inferred mixed).
    $fromStateOf = [
        'activate' => ProfileState::Approved,
        'suspend' => ProfileState::Active,
        'reactivate' => ProfileState::Suspended,
    ];
    $invokeOutOfBand = [
        'activate' => fn () => app(ActivateProfile::class)->handle($profile->id),
        'suspend' => fn () => app(SuspendProfile::class)->handle($profile->id),
        'reactivate' => fn () => app(ReactivateProfile::class)->handle($profile->id),
    ];

    foreach ($fromStateOf as $verb => $legalFrom) {
        if ($legalFrom === $from) {
            continue;  // this is the verb's legal from-state — its happy path + visibility are covered elsewhere.
        }

        // Half 1 — the surface HIDES the verb (the from-state guard's exact complement).
        $component->assertActionHidden($verb);

        // Half 2 — the domain FLOOR: an out-of-band call throws IllegalProfileTransition and rolls back BEFORE any
        // write (each Action guards the from-state inside its transaction).
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
