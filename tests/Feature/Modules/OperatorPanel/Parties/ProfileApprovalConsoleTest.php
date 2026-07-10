<?php

// Task 3.1 / 3.2 (operator-console-parties-membership; design D4/D9), widened by parties-hero-package task 5.2
// (design D11) — the Profile console's membership-APPROVAL surface on ViewProfile. The two form-less verbs the page
// APPENDS to its SurfacesDomainActions-built header-action array: approve (`applied | waiting_list → active`, or
// `applied → waiting_list` at capacity) and decline (`applied | waiting_list → rejected`), each routing through a
// Parties domain action by the Profile id and NEVER writing the model itself (the no-Eloquent-write rule).
//
// BOTH VERBS ARE VISIBILITY-GATED to `{applied, waiting_list}` — the EXACT COMPLEMENT of ApproveProfile's /
// DeclineProfile's widened domain from-state guard, so a rejected FROM-STATE stays UNREACHABLE through the surface:
// the verb is simply hidden; its reject is proven by a domain toThrow + assertActionHidden, never an action_failed the
// page can't raise (the Filament hidden-action landmine, lessons.md 2026-06-22). `waiting_list` was hidden before
// task 5.2 and the waitlist conversion was therefore unreachable through the console at all — which is what made
// AC-K-J-13 undemonstrable in a walkthrough.
//
// A HIDDEN VERB IS NOT THE ONLY FLOOR ANY MORE. The visibility predicate can see a from-state and never a capacity, so
// approve on a `waiting_list` Profile in a STILL-FULL Club is visible, driveable, and refused by the domain — the one
// action_failed APPROVE raises, alongside the two `renew` raises from `lapsed` (past-grace, and within-grace into a
// Club at capacity — ProfileLifecycleConsoleTest, which shares this ViewProfile page). Design D8/D11; the three are
// enumerated together in the lang/*/operator_console.php notifications comment. And an approve at capacity from
// `applied` SUCCEEDS into `waiting_list`: a second lawful outcome, which the console must name with its own copy
// rather than reporting a divert as an approval. Both are pinned below on the NOTIFICATION, not merely on the state —
// the pre-5.2 bug (a green "Membership approved and activated." over a waitlisted Profile) was invisible precisely
// because no test asserted a title.
//
// THE EVENT SURFACE (verified in the Action bodies): approve records NO Profile event except the conditional
// OriginatingClubLocked on the Customer's FIRST-EVER approval (idempotent NULL-gate on `originating_club_id` — a later
// Club's approval re-fires nothing) and the ProfileActivated of its internal activation; a capacity-diverted approve
// records exactly one WaitingListJoined and NOTHING else (no charge, no lock, no activation). Decline records NOTHING
// (the `state = rejected` write IS the audit record). All carry the operator audit envelope (newco_ops + the operator
// id) resolved from the `operator` guard via ActorContext.
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
use App\Modules\Parties\Events\ProfileActivated;
use App\Modules\Parties\Events\WaitingListJoined;
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

/**
 * Cap $club at $capacity Hero-Package seats and seat $occupied `Active` members in it, each under its own Customer
 * (the partial unique index on `(customer_id, club_id)` admits one non-terminal Profile per pair). Capacity is set
 * per-test through config, NEVER through the environment: the suite-wide default must stay `null` (uncapped) or every
 * pre-existing test changes behaviour. Named distinctly from `ProfileApprovalCapacityGateTest`'s `seatClubTo()` —
 * Pest loads every selected test file into ONE process, so two global helpers may never share a name.
 */
function approvalConsoleSeatClubTo(Club $club, int $capacity, int $occupied): void
{
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => $capacity]);

    Profile::factory()->count($occupied)->create([
        'club_id' => $club->id,
        'state' => ProfileState::Active,
    ]);
}

/**
 * Every toast the last Livewire request sent, oldest first — title, status AND body.
 *
 * Filament's `assertNotified()` cannot express this file's new claims. Given a STRING it matches the title only, and
 * the capacity refusal's title is the shared `action_failed` while the fact under test lives in the BODY; given a
 * `Notification` it matches by that notification's RANDOM id. It also PULLS (its `mount()` claims the session key),
 * so a snapshot taken after it is always empty. And on every Livewire `dehydrate` the notifications provider MOVES
 * `filament.notifications` onto `filament.claimed_notifications` (a `put`, so each request overwrites the last claim).
 * Hence: read the claimed key first, fall back to the unclaimed one, and read BEFORE any `assertNotified()`.
 *
 * @return list<array{title: ?string, status: ?string, body: ?string}>
 */
function approvalConsoleToasts(): array
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

function approvalConsoleTitle(string $key): string
{
    return (string) __("operator_console.profile.notifications.{$key}");
}

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

it('shows approve and decline only from Applied or WaitingList (design D4; parties-hero-package D11)', function (ProfileState $from, bool $visible) {
    actingAs(Operator::factory()->create(), 'operator');

    // Approve and decline are each reachable from `applied` AND from `waiting_list` (§ 4.2.1 / § 13.5 — the waitlist
    // conversion is the same atomic instant as an approval, and a waitlist decline is its terminal sibling); each is
    // visible iff stateIs('applied') || stateIs('waiting_list') — the EXACT COMPLEMENT of ApproveProfile's /
    // DeclineProfile's widened domain from-state guard. Both verbs still gate identically to each other.
    //
    // No capacity is configured here, so the Club is uncapped: visibility is a pure from-state predicate and cannot
    // consult a seat count (which is why a full Club's `waiting_list` approve is visible, driveable, and refused by
    // the domain — pinned below).
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
    'waiting_list → visible' => [ProfileState::WaitingList, true],
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

/*
|--------------------------------------------------------------------------
| The Hero-Package capacity outcomes (parties-hero-package task 5.2, design D11)
|
| Approve is the console's ONE verb with two lawful SUCCESSFUL outcomes. Every assertion below therefore pins the
| notification the operator actually reads, not merely the state the row landed in: a console that reported the
| divert as "Membership approved and activated." would satisfy every state assertion in this file and still lie.
|--------------------------------------------------------------------------
*/

it('waitlists an at-capacity approve and SAYS SO — the waitlisted copy, never the approved copy (AC-K-J-13)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // One seat, one Active member holding it: the Club is at EXACT parity. A never-approved Customer applies.
    $club = Club::factory()->create();
    approvalConsoleSeatClubTo($club, capacity: 1, occupied: 1);

    $customer = Customer::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Applied,
    ]);

    // The verb is visible (the predicate sees `applied`, never the seat count) and the click SUCCEEDS — the domain
    // diverts rather than refusing, so no danger toast is raised. Read the toasts before any assertNotified() claims.
    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        ->assertActionVisible('approve')
        ->callAction('approve');

    // THE ASSERTION 5.2 EXISTS FOR. A success toast, and its title names the WAITLIST — not the approval. Before this
    // task the fixed key made this a green "Membership approved and activated." over a Profile that never activated.
    expect(approvalConsoleToasts())->toBe([
        ['title' => approvalConsoleTitle('waitlisted'), 'status' => 'success', 'body' => null],
    ])->and(approvalConsoleTitle('waitlisted'))->not->toBe(approvalConsoleTitle('approved'));

    // The domain's half: the Profile landed on the waitlist, the seat did not move, and the diverted approval took no
    // charge and locked no Originating Club (it consumed no seat, so there is nothing to originate from).
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::WaitingList)
        ->and(Customer::findOrFail($customer->id)->originating_club_id)->toBeNull()
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', OriginatingClubLocked::NAME)->count())->toBe(0);

    // Exactly one WaitingListJoined, carrying the operator audit envelope the console never constructs itself.
    $event = DomainEvent::query()->where('name', WaitingListJoined::NAME)->sole();

    expect($event->entity_type)->toBe('Profile')
        ->and($event->entity_id)->toBe((string) $profile->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps);
});

it('converts a waitlisted Profile through the console once a seat frees — active, ProfileActivated, the approved copy', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // One seat, nobody holding it: the Club has room. The Profile is already parked on the waitlist — the state the
    // console could not act on at all before task 5.2, which is what made AC-K-J-13 undemonstrable in a walkthrough.
    $club = Club::factory()->create();
    approvalConsoleSeatClubTo($club, capacity: 1, occupied: 0);

    $customer = Customer::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::WaitingList,
    ]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        // Visible from `waiting_list` (the 5.2 widening); callAction asserts-visible-first, so this drive is itself a
        // proof the verb is reachable there.
        ->assertActionVisible('approve')
        ->callAction('approve');

    // A conversion IS an approval — same atomic instant, same copy (§ 13.5). The resolver must not re-title it merely
    // because the record ARRIVED from the waitlist: it reads where the Profile LANDED.
    expect(approvalConsoleToasts())->toBe([
        ['title' => approvalConsoleTitle('approved'), 'status' => 'success', 'body' => null],
    ]);

    // `waiting_list → active` in one atomic operation (`approved` is transient), and the conversion is where this
    // Customer's first-ever activation happens — so the one-shot Originating-Club lock fires HERE, not at the earlier
    // waitlist placement.
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active)
        ->and(Customer::findOrFail($customer->id)->originating_club_id)->toBe($club->id)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', OriginatingClubLocked::NAME)->count())->toBe(1)
        // Nothing re-joined the waitlist on the way out.
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0);
});

it('surfaces the domain capacity refusal as a danger toast when the Club is still full — the only action_failed approve raises, alongside the two from renew', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // At parity, with the Profile ALREADY on the waitlist: it has no edge left to take (design D8), so the domain
    // REFUSES rather than diverting a second time. The verb is nonetheless visible — the predicate sees a from-state,
    // never a seat count — which is exactly why this rejection is UI-reachable where every other one is not.
    $club = Club::factory()->create();
    approvalConsoleSeatClubTo($club, capacity: 1, occupied: 1);

    $profile = Profile::factory()->create([
        'club_id' => $club->id,
        'state' => ProfileState::WaitingList,
    ]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        ->assertActionVisible('approve')
        ->callAction('approve');

    // The console owns the shared `action_failed` TITLE; the BODY is the domain's own already-localized reason,
    // reaching the operator verbatim — naming the capacity and the occupancy the gate decided on, so the toast says
    // WHY. The console re-words nothing and re-checks no gate (design L4).
    expect(approvalConsoleToasts())->toBe([
        [
            'title' => approvalConsoleTitle('action_failed'),
            'status' => 'danger',
            'body' => (string) __('parties.profile.club_at_capacity', [
                'state' => ProfileState::WaitingList->value,
                'capacity' => 1,
                'occupied' => 1,
            ]),
        ],
    ]);

    // The rejecting transaction rolled back: no state write, no SECOND WaitingListJoined, a clean event log.
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::WaitingList)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('declines a waitlisted Profile through the console — rejected, terminal, zero domain events', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // Decline is the waitlist's OTHER exit (the one that never reaches `active`). It takes no Club-row lock and reads
    // no capacity — a decline neither frees nor consumes a seat — so a full Club is the honest fixture: the verb must
    // work exactly as it does with room to spare.
    $club = Club::factory()->create();
    approvalConsoleSeatClubTo($club, capacity: 1, occupied: 1);

    $profile = Profile::factory()->create([
        'club_id' => $club->id,
        'state' => ProfileState::WaitingList,
    ]);

    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        ->assertActionVisible('decline')
        ->callAction('decline')
        ->assertNotified(approvalConsoleTitle('declined'));

    // `waiting_list → rejected`, audit-only: the `state = rejected` write IS the record (§ 15.2 names no event), and
    // the capacity gate was never consulted (a decline is not a seat-consuming transition).
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Rejected)
        ->and(DomainEvent::query()->count())->toBe(0);
});
