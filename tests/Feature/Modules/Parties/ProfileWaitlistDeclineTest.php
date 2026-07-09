<?php

use App\Modules\Parties\Actions\CreateProfile;
use App\Modules\Parties\Actions\DeclineProfile;
use App\Modules\Parties\Contracts\HeroPackageCapacityReader;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileCreated;
use App\Modules\Parties\Events\WaitingListJoined;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Support\ClubSeatOccupancy;
use App\Platform\Events\DomainEvent;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pins `DeclineProfile`'s WIDENED FROM-STATE SET (parties-hero-package task 2.3, design D8; canon Module K PRD
 * § 4.2.1:186 — the waitlist exits to `Approved` **or** `Rejected`; party-registry — Requirement: WaitingList
 * Placement, Conversion and Decline).
 *
 * Declining a waitlisted applicant is the waitlist's OTHER exit, and it is the same Action as declining an applied
 * one — there is no `DeclineWaitlistedProfile` (design D10: this change adds no Action class). Four claims:
 *   1. `waiting_list → rejected` transitions, terminally and EVENT-SILENTLY (§ 15.2 names no `ProfileRejected`, and
 *      leaving the waitlist records no counterpart to the `WaitingListJoined` that entered it).
 *   2. IT READS NO CAPACITY AND LOCKS NO CLUB ROW. Neither `applied` nor `waiting_list` holds a seat, so a decline
 *      neither frees one nor consumes one and can never oversell. Pinned three ways: the Action is still
 *      constructor-less, it imports neither the seat ledger nor the capacity port, and it emits no `parties_clubs`
 *      statement at all. A decline inside a FULL Club therefore succeeds, and leaves the Club exactly as full.
 *   3. THE DECLINE IS TERMINAL-FOR-THIS-APPLICATION, NOT FOR THE CUSTOMER. The partial unique index excludes
 *      `rejected`, so a re-application inserts a fresh row — itself capacity-routed, so a declined waitlister who
 *      re-applies to a still-full Club is born back onto the waitlist (`CreateProfile`, design D6).
 *   4. THE COMPLEMENT STILL REJECTS. All seven states outside `{applied, waiting_list}` throw `cannotReject`, even
 *      when the Club is at parity — the from-state guard is the only gate this Action has.
 *
 * The `applied → rejected` leg, unchanged by this task, stays pinned in `ProfileMembershipApprovalTest`.
 *
 * RefreshDatabase per the directory convention. Capacity is set per-test via `config()->set(...)`, never via the
 * environment: the shipped test default is uncapped, which is what keeps every pre-existing test on the historical
 * behaviour.
 */
uses(RefreshDatabase::class);

/**
 * Cap $club at $capacity seats and seat $occupied `Active` members in it, each under its own Customer. Named
 * distinctly from the sibling fixtures (`clubAtCapacity` in ProfileBirthStateRoutingTest, `seatClubTo` in
 * ProfileApprovalCapacityGateTest): Pest includes every selected test file into ONE process while building the
 * suite, so two global helpers may never share a name — a redeclare is a fatal error, not a shadow.
 */
function fillClubForDecline(Club $club, int $capacity, int $occupied): void
{
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => $capacity]);

    Profile::factory()->count($occupied)->create([
        'club_id' => $club->id,
        'state' => ProfileState::Active,
    ]);
}

it('declines a waitlisted Profile to rejected, terminally and event-silently', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::WaitingList,
    ]);

    $returned = app(DeclineProfile::class)->handle($profile->id);

    expect($returned->state)->toBe(ProfileState::Rejected)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Rejected)
        // Event-silent from this from-state too: the `state = rejected` write IS the audit record. Leaving the
        // waitlist records nothing — there is no `WaitingListLeft` counterpart to `WaitingListJoined`.
        ->and(DomainEvent::query()->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0)
        // A decline locks no Originating Club (that is the approve path's conditional one-shot, design L3).
        ->and(Customer::findOrFail($customer->id)->originating_club_id)->toBeNull();
});

it('declines a waitlisted Profile inside a FULL Club, leaving the Club exactly as full — it reads no capacity', function () {
    $club = Club::factory()->create();
    fillClubForDecline($club, capacity: 2, occupied: 2);

    $profile = Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::WaitingList]);

    // The Club is at parity, and `ApproveProfile` on this very Profile would raise `clubAtCapacity`. The decline has
    // no capacity to consult: `waiting_list` holds no seat, so removing the applicant from it changes no occupancy.
    expect(app(DeclineProfile::class)->handle($profile->id)->state)->toBe(ProfileState::Rejected)
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('declines an applied Profile inside a FULL Club — it is rejected, never diverted onto the waitlist', function () {
    $club = Club::factory()->create();
    fillClubForDecline($club, capacity: 2, occupied: 2);

    $profile = Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::Applied]);

    // `ApproveProfile` at parity DIVERTS an `applied` Profile to `waiting_list` (design D8). `DeclineProfile` never
    // does: the two verbs share a from-state set, not an outcome.
    expect(app(DeclineProfile::class)->handle($profile->id)->state)->toBe(ProfileState::Rejected)
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('takes no Club-row lock and emits no parties_clubs statement while declining a waitlisted Profile (design D8)', function () {
    $club = Club::factory()->create();
    fillClubForDecline($club, capacity: 1, occupied: 1);

    $profile = Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::WaitingList]);

    /** @var list<string> $statements */
    $statements = [];

    // Registered AFTER the fixture so only the Action's own traffic is captured. `DB::transaction()` issues its
    // SAVEPOINT through the raw PDO handle, so it never lands here.
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        if (str_contains($query->sql, 'parties_clubs') || str_contains($query->sql, 'parties_profiles')) {
            $statements[] = $query->sql;
        }
    });

    app(DeclineProfile::class)->handle($profile->id);

    // The whole Action, in SQL: re-read the Profile row under its lock, then write. It never reads `parties_clubs`,
    // so it can neither count seats nor serialise a Club — the property `ApproveProfile` must pay for and this
    // Action must not (a decline can never oversell, and must not queue behind an unrelated approval).
    expect($statements)->not->toBeEmpty();

    foreach ($statements as $statement) {
        expect($statement)->not->toContain('parties_clubs');
    }

    expect($statements[0])->toContain('parties_profiles')
        ->and($statements[0])->toContain('select');
});

it('keeps DeclineProfile constructor-less, coupled to neither the seat ledger nor the capacity port', function () {
    // The structural complement of the SQL pin above: this Action gained no dependency when its from-state set
    // widened. `ApproveProfile` needs both (the ledger for the locked count, the port for the rejection's numbers);
    // a decline needs neither, so injecting either "for symmetry" would be dead weight PHPStan max would flag.
    expect((new ReflectionClass(DeclineProfile::class))->getConstructor())->toBeNull();

    expect(DeclineProfile::class)->not->toUse([
        ClubSeatOccupancy::class,
        HeroPackageCapacityReader::class,
    ]);
});

it('admits a re-application after a waitlist decline, routed by capacity like any other birth', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    fillClubForDecline($club, capacity: 1, occupied: 1);

    $waitlisted = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::WaitingList,
    ]);

    app(DeclineProfile::class)->handle($waitlisted->id);

    // `rejected` is excluded from the partial unique index on `(customer_id, club_id)`, so the declined row no
    // longer blocks the pair: CreateProfile's pre-check and the index both admit a fresh Profile. It is a NEW row —
    // rejected Profiles are not reused (§ 4.2.1).
    $reapplied = app(CreateProfile::class)->handle($customer->id, $club->id);

    expect($reapplied->id)->not->toBe($waitlisted->id)
        // The re-application is capacity-routed like any other birth (design D6): the Club is still full, so the
        // returning applicant is born back onto the waitlist rather than into `applied`.
        ->and($reapplied->state)->toBe(ProfileState::WaitingList)
        ->and(Profile::findOrFail($waitlisted->id)->state)->toBe(ProfileState::Rejected);

    // The birth records both of its events; the decline before it recorded none.
    expect(DomainEvent::query()->where('name', ProfileCreated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->count())->toBe(2);
});

it('admits a re-application into `applied` once the Club has freed a seat', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    fillClubForDecline($club, capacity: 2, occupied: 2);

    $waitlisted = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::WaitingList,
    ]);

    app(DeclineProfile::class)->handle($waitlisted->id);

    // A seat frees by attrition, WITHOUT an Action — the fixture must record no event of its own. Nothing promotes
    // anyone off the waitlist when it frees (design D5), and here there is nobody left on it to promote.
    $seat = Profile::query()->where('club_id', $club->id)->where('state', ProfileState::Active->value)->firstOrFail();
    Profile::query()->whereKey($seat->id)->update(['state' => ProfileState::Cancelled->value]);

    $reapplied = app(CreateProfile::class)->handle($customer->id, $club->id);

    // Below parity now, so the returning applicant is born `applied` — the decline cost them their place, not their
    // eligibility. The re-application reads the CURRENT occupancy, never the one that waitlisted them.
    expect($reapplied->state)->toBe(ProfileState::Applied)
        ->and($reapplied->id)->not->toBe($waitlisted->id)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', ProfileCreated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->count())->toBe(1);
});

it('rejects a decline from any state outside {applied, waiting_list}, even when the Club is at parity', function (ProfileState $from) {
    $club = Club::factory()->create();

    // Capacity `0` is the cheapest at-parity fixture: a Club admitting nobody is full while still empty, so every
    // from-state below is arranged inside a Club at parity. The from-state guard is this Action's ONLY gate — it
    // must reject on the state alone, never reach for a capacity reason it does not carry.
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => 0]);

    $profile = Profile::factory()->create(['club_id' => $club->id, 'state' => $from]);

    expect(fn () => app(DeclineProfile::class)->handle($profile->id))
        ->toThrow(IllegalProfileTransition::class, (string) __('parties.profile.cannot_reject', ['state' => $from->value]));

    // The guard fires before any write and the transaction rolls back.
    expect(Profile::findOrFail($profile->id)->state)->toBe($from)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'approved' => [ProfileState::Approved],
    'rejected' => [ProfileState::Rejected],
    'active' => [ProfileState::Active],
    'suspended' => [ProfileState::Suspended],
    'lapsed' => [ProfileState::Lapsed],
    'cancelled' => [ProfileState::Cancelled],
    'inactive' => [ProfileState::Inactive],
]);
