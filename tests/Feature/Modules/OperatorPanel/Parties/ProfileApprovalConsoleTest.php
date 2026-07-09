<?php

// Task 3.1 / 3.2 (operator-console-parties-membership; design D4/D9) — the Profile console's membership-APPROVAL
// surface on ViewProfile. The two form-less verbs the page APPENDS to its SurfacesDomainActions-built header-action
// array: approve (`applied → approved`) and decline (`applied → rejected`), each routing through a Parties domain
// action by the Profile id and NEVER writing the model itself (the no-Eloquent-write rule).
//
// BOTH VERBS ARE VISIBILITY-GATED to `applied` (design D4) — the EXACT COMPLEMENT of ApproveProfile's /
// DeclineProfile's domain from-state guard, so a rejected transition is UNREACHABLE through the surface: the verb is
// simply hidden; its reject is proven by a domain toThrow + assertActionHidden, never an action_failed the page can't
// raise (the Filament hidden-action landmine, lessons.md 2026-06-22).
//
// THE EVENT SURFACE (verified in the Action bodies): approve records NO Profile event except the conditional
// OriginatingClubLocked on the Customer's FIRST-EVER approval (idempotent NULL-gate on `originating_club_id` — a later
// Club's approval re-fires nothing). Decline records NOTHING (the `state = rejected` write IS the audit record). Both
// carry the operator audit envelope (newco_ops + the operator id) resolved from the `operator` guard via ActorContext.
//
// DatabaseMigrations (mirroring CustomerLifecycleConsoleTest / ProfileCreateConsoleTest): each console action drives a
// real domain action opening its OWN DB::transaction, so the DomainEventRecorder's in-transaction append commits for
// real (RefreshDatabase would wrap every write in a never-committed outer transaction). The factory bypasses the
// actions → records no event. Parties enums/models/exceptions/events are imported freely here: the {Models, Actions}
// import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages\ViewProfile;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Actions\ApproveProfile;
use App\Modules\Parties\Actions\DeclineProfile;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\OriginatingClubLocked;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('approves an Applied Profile through the console — active (atomic approve = activation), the Originating Club locked to that Club, one OriginatingClubLocked with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // A never-approved Customer (originating_club_id NULL — the factory's born-unset default) holds an `applied`
    // Profile in one Club. The factory bypasses CreateProfile → it records no event, so the approve verb's lone
    // OriginatingClubLocked is the only event.
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Applied,
    ]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        // callAction asserts-visible-first (approve is visible iff `applied`), then drives the form-less verb into
        // ApproveProfile by the Profile id — the console writes nothing itself (the no-Eloquent-write rule).
        ->callAction('approve')
        ->assertNotified((string) __('operator_console.profile.notifications.approved'));

    // State advanced applied → active via the domain action, atomically (approve = charge = activation — MVP-DEC-016;
    // the console never writes `state`); `approved` is a transient pass-through, never durably rested-in.
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active)
        // The Customer's first-ever approval locked the Originating Club to THIS approving Club (design L3 / § 6.1).
        ->and(Customer::findOrFail($customer->id)->originating_club_id)->toBe($club->id);

    // Exactly one OriginatingClubLocked — a Customer-state event — carrying the operator audit envelope (newco_ops +
    // the operator id) resolved by the action from the `operator` guard; the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', OriginatingClubLocked::NAME)->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Customer')
        ->and($event->entity_id)->toBe((string) $customer->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id);  // loose: PG returns a numeric string for the bigint
});

it('locks the Originating Club only once — a second Club approval for the same Customer records no further OriginatingClubLocked (one-shot)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // One Customer, two `applied` Profiles in two different Clubs (distinct (customer, club) pairs → no partial-unique
    // conflict). Approving both must lock the Originating Club exactly once — to the FIRST approving Club.
    $customer = Customer::factory()->create();
    $firstClub = Club::factory()->create();
    $secondClub = Club::factory()->create();
    $first = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $firstClub->id,
        'state' => ProfileState::Applied,
    ]);
    $second = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $secondClub->id,
        'state' => ProfileState::Applied,
    ]);

    // Two separate mounts (each Livewire::test re-reads the record): approve the first, then the second.
    Livewire::test(ViewProfile::class, ['record' => $first->id])
        ->callAction('approve')
        ->assertNotified((string) __('operator_console.profile.notifications.approved'));
    Livewire::test(ViewProfile::class, ['record' => $second->id])
        ->callAction('approve')
        ->assertNotified((string) __('operator_console.profile.notifications.approved'));

    // Both memberships are `active` (each approval activates atomically — MVP-DEC-016) …
    expect(Profile::findOrFail($first->id)->state)->toBe(ProfileState::Active)
        ->and(Profile::findOrFail($second->id)->state)->toBe(ProfileState::Active)
        // … the Originating Club is locked to the FIRST approving Club and the second approval never re-set it
        // (immutable after the first — the NULL-gate found it set) …
        ->and(Customer::findOrFail($customer->id)->originating_club_id)->toBe($firstClub->id)
        // … and exactly ONE OriginatingClubLocked fired across BOTH approvals (the one-shot lock — design L3).
        ->and(DomainEvent::query()->where('name', OriginatingClubLocked::NAME)->count())->toBe(1);
});

it('declines an Applied Profile through the console — rejected, zero domain events (audit-only)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A bare-factory `applied` Profile (records no event): decline is audit-only — the `state = rejected` write IS the
    // audit record (§ 15.2 names no ProfileRejected), so the event log must stay a clean zero.
    $profile = Profile::factory()->create(['state' => ProfileState::Applied]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        // decline is visible iff `applied`; callAction asserts-visible-first then drives DeclineProfile by the id.
        ->callAction('decline')
        ->assertNotified((string) __('operator_console.profile.notifications.declined'));

    // State advanced applied → rejected via the domain action …
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Rejected)
        // … and decline recorded NOTHING (audit-only; DeclineProfile touches no recorder — design L2).
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('shows approve and decline only from Applied (design D4)', function (ProfileState $from, bool $visible) {
    actingAs(Operator::factory()->create(), 'operator');

    // Approve and decline are each reachable ONLY from `applied` (§ 4.2.1); each is visible iff stateIs('applied') —
    // the EXACT COMPLEMENT of ApproveProfile's / DeclineProfile's domain from-state guard. Both gate identically.
    $profile = Profile::factory()->create(['state' => $from]);

    $component = Livewire::test(ViewProfile::class, ['record' => $profile->id]);

    if ($visible) {
        $component->assertActionVisible('approve')
            ->assertActionVisible('decline');
    } else {
        $component->assertActionHidden('approve')
            ->assertActionHidden('decline');
    }
})->with([
    'applied → visible' => [ProfileState::Applied, true],
    'waiting_list → hidden' => [ProfileState::WaitingList, false],
    'approved → hidden' => [ProfileState::Approved, false],
    'rejected → hidden' => [ProfileState::Rejected, false],
    'active → hidden' => [ProfileState::Active, false],
    'suspended → hidden' => [ProfileState::Suspended, false],
    'lapsed → hidden' => [ProfileState::Lapsed, false],
    'cancelled → hidden' => [ProfileState::Cancelled, false],
    'inactive → hidden' => [ProfileState::Inactive, false],
]);

it('proves the approve/decline reject floor — hidden out of Applied AND the domain rejects an out-of-band call, state + the event log unchanged (design D4)', function (ProfileState $from) {
    actingAs(Operator::factory()->create(), 'operator');

    // Out of `applied`, both verbs are hidden (callAction would assert-visible-FIRST and fail), so the reject is proven
    // the only way the surface allows: the surface HIDES the verb AND the domain INDEPENDENTLY rejects an out-of-band
    // call. The bare factory records no event, so "the event log unchanged" is a clean zero.
    $profile = Profile::factory()->create(['state' => $from]);

    // Half 1 — the surface HIDES both verbs.
    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        ->assertActionHidden('approve')
        ->assertActionHidden('decline');

    // Half 2 — the domain FLOOR: an out-of-band call throws IllegalProfileTransition (imported freely in the test) and
    // rolls back BEFORE any write (each Action guards the from-state inside its transaction).
    expect(fn () => app(ApproveProfile::class)->handle($profile->id))->toThrow(IllegalProfileTransition::class);
    expect(fn () => app(DeclineProfile::class)->handle($profile->id))->toThrow(IllegalProfileTransition::class);

    // Nothing moved: the state is exactly as arranged and the event log is still empty (both transactions rolled back).
    expect(Profile::findOrFail($profile->id)->state)->toBe($from)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    // `waiting_list` is NOT in this floor. Since parties-hero-package, approve is LEGAL from `waiting_list` (the
    // waitlist conversion — the same atomic instant, § 13.5), so under the uncapped test default the domain half
    // below would activate the Profile rather than throw. The console still HIDES both verbs there today; that half
    // is covered by the visibility dataset above, and the conversion + its at-parity rejection are pinned in
    // tests/Feature/Modules/Parties/ProfileApprovalCapacityGateTest.php.
    'approved → hidden + rejected' => [ProfileState::Approved],
    'rejected → hidden + rejected' => [ProfileState::Rejected],
    'active → hidden + rejected' => [ProfileState::Active],
    'suspended → hidden + rejected' => [ProfileState::Suspended],
    'lapsed → hidden + rejected' => [ProfileState::Lapsed],
    'cancelled → hidden + rejected' => [ProfileState::Cancelled],
    'inactive → hidden + rejected' => [ProfileState::Inactive],
]);
