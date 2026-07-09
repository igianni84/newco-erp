<?php

use App\Modules\Parties\Actions\ApproveProfile;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\OriginatingClubLocked;
use App\Modules\Parties\Events\ProfileActivated;
use App\Modules\Parties\Events\WaitingListJoined;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Support\ClubSeatOccupancy;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pins `ApproveProfile`'s HERO-PACKAGE CAPACITY GATE (parties-hero-package task 2.2, design D3/D4/D8; canon
 * MVP-DEC-017; AC-K-J-13; ADR 2026-07-09-hero-package-capacity-seat-set-and-waitinglist).
 *
 * Approval is the seat-CONSUMING instant, so it is the sole enforcement point of the membership no-oversell
 * invariant (CLAUDE.md invariant 1). Five claims:
 *   1. AT PARITY, AN `applied` APPROVAL TRANSITIONS — it does not throw. The Profile LANDS in `waiting_list`, one
 *      `WaitingListJoined` is recorded — a ROOT event — and no charge, no Originating-Club lock and no
 *      `ProfileActivated` follow. This is the DIVERT entry point of that event; the BIRTH one is pinned separately,
 *      in `ProfileBirthStateRoutingTest` (two `record()` call sites, so one pin cannot cover the other —
 *      parties-hero-package-residuals design R3; party-registry — Scenario: *WaitingListJoined carries a PII-free
 *      payload and is a root event*, "at either entry point").
 *   2. AT PARITY, AN ALREADY-`waiting_list` APPROVAL THROWS — there is no edge left to take. No state write, and
 *      never a second `WaitingListJoined`.
 *   3. THE CONVERSION IS THE SAME ATOMIC INSTANT. Once a seat frees, approving off the waitlist reaches `active`
 *      through the transient `approved`, recording `ProfileActivated` and the first-ever `OriginatingClubLocked` —
 *      exactly as an approval from `applied` does. Nothing converted the Profile when the seat freed (design D5).
 *   4. THE FROM-STATE GUARD PRECEDES THE GATE. An out-of-state approval into a FULL Club throws `cannotApprove`,
 *      never `clubAtCapacity`, and is never diverted onto the waitlist. That ORDER is pinned NEGATIVELY — by the
 *      `parties_clubs` statement the doomed call never emitted — because the reason it reports is identical under
 *      BOTH orderings whenever the capacity gate would have rejected too, so the reason discriminates nothing. The
 *      negative pin also carries the operational half: a doomed call must not serialise a Club against healthy
 *      concurrent approvals (parties-hero-package-residuals design R1/R2; party-registry — Scenario: *An
 *      out-of-state approve is rejected before any Club row is locked*).
 *   5. THE COUNT IS TAKEN UNDER THE `parties_clubs` ROW LOCK, ACQUIRED FIRST (design D3). Pinned as SQL statement
 *      order, plus the driver-gated `for update` needle — the only cross-engine proof of the race fix. Statement
 *      order is not serialisation: the two-connection proof is task 3.2's, and runs on PostgreSQL 17 only.
 *
 * An UNCAPPED Club (the shipped test default — no `PARTIES_HERO_PACKAGE_CAPACITY` in the environment) passes the
 * gate unconditionally, which is what keeps every pre-existing approval test running against unchanged behaviour.
 * Capacity is therefore set per-test via `config()->set(...)`, never via the environment.
 *
 * RefreshDatabase per the directory convention; the action opens its own `DB::transaction`, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper. Event payloads are asserted BY
 * KEY, never as a byte-compare of stored JSON (PostgreSQL's `jsonb` reorders keys).
 */
uses(RefreshDatabase::class);

/**
 * Cap $club at $capacity seats and seat $occupied `Active` members in it, each under its own Customer (the partial
 * unique index on `(customer_id, club_id)` admits one non-terminal Profile per pair). Named distinctly from
 * `ProfileBirthStateRoutingTest`'s `clubAtCapacity()`: Pest loads every test file into one process, so two global
 * helpers may never share a name.
 */
function seatClubTo(Club $club, int $capacity, int $occupied): void
{
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => $capacity]);

    Profile::factory()->count($occupied)->create([
        'club_id' => $club->id,
        'state' => ProfileState::Active,
    ]);
}

/** Free one seat by attrition, WITHOUT an Action — the fixture must record no event of its own. */
function freeOneSeat(Club $club): void
{
    $seat = Profile::query()
        ->where('club_id', $club->id)
        ->where('state', ProfileState::Active->value)
        ->firstOrFail();

    Profile::query()->whereKey($seat->id)->update(['state' => ProfileState::Cancelled->value]);
}

it('diverts the 51st approval of a 50-seat Club into waiting_list — no activation, no Originating-Club lock, occupancy unmoved (AC-K-J-13)', function () {
    $club = Club::factory()->create();
    seatClubTo($club, capacity: 50, occupied: 50);

    // A never-approved Customer (originating_club_id NULL — the factory's born-unset default) holds the 51st Profile.
    $customer = Customer::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Applied,
    ]);

    // NO exception: canon has the 51st applicant LAND on the waitlist. The Action returns the diverted Profile, which
    // is what the operator console reads to choose its notification (task 5.1/5.2).
    $returned = app(ApproveProfile::class)->handle($profile->id);

    expect($returned->state)->toBe(ProfileState::WaitingList)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::WaitingList)
        // The invariant itself: the Club's seat occupancy never exceeded its capacity.
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(50)
        // No charge was taken and no seat consumed, so the Originating Club stays unlocked (spec: the lock fires
        // ONLY on an approval that reaches `active`).
        ->and(Customer::findOrFail($customer->id)->originating_club_id)->toBeNull();

    // Exactly one event across the whole approval — the divert's WaitingListJoined, and nothing else.
    $joined = DomainEvent::query()->where('name', WaitingListJoined::NAME)->sole();

    expect(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', OriginatingClubLocked::NAME)->count())->toBe(0)
        ->and($joined->module)->toBe('parties')
        ->and($joined->entity_type)->toBe('Profile')
        ->and($joined->entity_id)->toBe((string) $profile->id)
        ->and($joined->actor_role)->toBe(ActorRole::System);

    // PII-free, parties by id only — pinned by the EXACT key set so the payload cannot silently widen. The `state`
    // is read off the row post-write, never hardcoded, so a writer bug reaches the audit store.
    expect(array_keys($joined->payload))->toEqualCanonicalizing(['profile_id', 'customer_id', 'club_id', 'state']);

    expect($joined->payload['profile_id'])->toBe($profile->id)
        ->and($joined->payload['customer_id'])->toBe($customer->id)
        ->and($joined->payload['club_id'])->toBe($club->id)
        ->and($joined->payload['state'])->toBe('waiting_list');

    // `WaitingListJoined` IS A ROOT EVENT at the DIVERT entry point too (party-registry — Scenario: *WaitingListJoined
    // carries a PII-free payload and is a root event*, "at either entry point"). The two conjuncts are NOT equally
    // load-bearing here, and the asymmetry is worth knowing before someone prunes one of them:
    //
    // `correlation_id` is live. A caller may pass any correlation it likes, and a fresh UUID is the recorder's own
    // documented trap (a root's correlation defaults to its OWN `event_id`, never an independent UUID). Mutating the
    // `record()` call to pass one reds THIS LINE ALONE across the whole suite — nothing else in the repository sees it.
    //
    // `causation_id` is, at this entry point, structurally null — there are exactly three ways it could go non-null
    // and every one is already loud. (i) A dangling id: `causation_id` is an FK onto `domain_events.id`, so the insert
    // raises. (ii) Self-parenting after the insert: `domain_events` carries an immutability trigger and rejects the
    // UPDATE. (iii) A donor event recorded first in this transaction — the natural refactor the day a `ProfileApproved`
    // event exists (§ 15.2 names none today, so the divert records exactly the one event counted above) — which reds
    // the `count()->toBe(1)` assertion 27 lines up, before this one ever runs. So the assertion below cannot fail
    // today; it is here because the spec requires it at BOTH entry points and because the count that dominates it is
    // not a statement about causality. If that count ever stops being `1`, this line is the only guard left standing.
    expect($joined->causation_id)->toBeNull()
        ->and($joined->correlation_id)->toBe($joined->event_id);
});

it('rejects an approval of a still-waitlisted Profile whose Club is still full — no second WaitingListJoined, no state write', function () {
    $club = Club::factory()->create();
    seatClubTo($club, capacity: 2, occupied: 2);

    $profile = Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::WaitingList]);

    // There is no transition to make, so the gate raises rather than no-opping idempotently — the operator who
    // clicked must be told WHY, in the two cardinals the gate itself decided on, and in their own language.
    expect(fn () => app(ApproveProfile::class)->handle($profile->id))
        ->toThrow(
            IllegalProfileTransition::class,
            (string) __('parties.profile.club_at_capacity', [
                'state' => 'waiting_list',
                'capacity' => 2,
                'occupied' => 2,
            ]),
        );

    // The transaction rolled back before any write: the Profile is exactly as arranged and the log is a clean zero.
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::WaitingList)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('converts waiting_list → active once a seat frees, recording ProfileActivated and the first-ever OriginatingClubLocked', function () {
    $club = Club::factory()->create();
    seatClubTo($club, capacity: 2, occupied: 2);

    $customer = Customer::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::WaitingList,
    ]);

    freeOneSeat($club);

    // Design D5 — the freed seat is NEVER auto-filled: no listener, scheduler, job or observer promoted anyone.
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::WaitingList)
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(1);

    // The conversion is the SAME atomic approve = charge = activation instant, not a distinct Action: `approved` is
    // a transient pass-through and the Profile is never observably left resting there.
    expect(app(ApproveProfile::class)->handle($profile->id)->state)->toBe(ProfileState::Active);

    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active)
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2)
        // … and the conversion obeys the Originating-Club one-shot rule exactly as an approval from `applied` does.
        ->and(Customer::findOrFail($customer->id)->originating_club_id)->toBe($club->id);

    $activated = DomainEvent::query()->where('name', ProfileActivated::NAME)->sole();
    $locked = DomainEvent::query()->where('name', OriginatingClubLocked::NAME)->sole();

    expect($activated->entity_id)->toBe((string) $profile->id)
        ->and($locked->entity_id)->toBe((string) $customer->id)
        // The conversion is an ENTRY into `active`, not into `waiting_list`: it records no WaitingListJoined.
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(2);
});

it('activates an applied Profile normally while the capped Club still has a free seat', function () {
    $club = Club::factory()->create();
    seatClubTo($club, capacity: 2, occupied: 1);

    $profile = Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::Applied]);

    // A capped Club is not a gated Club: below parity the gate passes and the historical path runs untouched.
    expect(app(ApproveProfile::class)->handle($profile->id)->state)->toBe(ProfileState::Active)
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(1);
});

it('activates every approval in an uncapped Club, however many members it already seats', function () {
    $club = Club::factory()->create();

    // The shipped launch posture (a dark launch): no PARTIES_HERO_PACKAGE_CAPACITY in the test environment.
    expect(config('parties.hero_package.capacity.default'))->toBeNull();

    Profile::factory()->count(3)->create(['club_id' => $club->id, 'state' => ProfileState::Active]);

    $applicants = Profile::factory()->count(3)->create(['club_id' => $club->id, 'state' => ProfileState::Applied]);

    foreach ($applicants as $applicant) {
        expect(app(ApproveProfile::class)->handle($applicant->id)->state)->toBe(ProfileState::Active);
    }

    // Nobody was diverted and no capacity rejection was raised — the invariant is vacuously satisfied.
    expect(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(6)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(3);
});

it('guards the from-state BEFORE the capacity gate — an out-of-state approval into a full Club throws cannotApprove, never clubAtCapacity', function (ProfileState $from) {
    $club = Club::factory()->create();

    // Capacity `0` is the cheapest at-parity fixture there is: a Club admitting nobody is full while still empty,
    // so this Club is at parity for every one of the seven states below.
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => 0]);

    $profile = Profile::factory()->create(['club_id' => $club->id, 'state' => $from]);

    // The ORDER is the claim. Were the gate evaluated first, an `active` or `lapsed` Profile in a full Club would
    // fall into the at-parity branch and — not being `waiting_list` — be DIVERTED onto the waitlist. The from-state
    // guard is what makes the capacity column of design D8's table read "—" for every other state.
    expect(fn () => app(ApproveProfile::class)->handle($profile->id))
        ->toThrow(IllegalProfileTransition::class, (string) __('parties.profile.cannot_approve', ['state' => $from->value]));

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

it('locks no Club row for an out-of-state approval, whatever the gate that never ran would have said', function (ProfileState $from, ?int $capacity, int $occupied) {
    $club = Club::factory()->create();

    // The `default` is CAPPED so the `uncapped` rows below are honest ones: the adapter resolves `by_club_id` on
    // `array_key_exists`, so an explicit `null` there pins THIS Club uncapped beneath that capped default. A huge cap
    // would instead exercise the CAPPED branch of a Club that merely is not full — a different code path.
    config()->set('parties.hero_package.capacity.default', 1);
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => $capacity]);

    Profile::factory()->count($occupied)->create(['club_id' => $club->id, 'state' => ProfileState::Active]);

    $profile = Profile::factory()->create(['club_id' => $club->id, 'state' => $from]);

    /** @var list<string> $statements */
    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        if (str_contains($query->sql, 'parties_clubs')) {
            $statements[] = $query->sql;
        }
    });

    // THE ORDER IS THE CLAIM (design D3/D8). The from-state guard runs before the Club-row lock, so a Profile that
    // was never approvable is told THAT — and the capacity gate is never consulted at all.
    expect(fn () => app(ApproveProfile::class)->handle($profile->id))
        ->toThrow(IllegalProfileTransition::class, (string) __('parties.profile.cannot_approve', ['state' => $from->value]));

    // The DISCRIMINATOR. The reason above is reported under both orderings whenever the gate would also have
    // rejected (the `at parity` rows); only the trace separates them. A doomed call reached
    // `lockAndCountOccupiedSeats()` never, so it emitted no `parties_clubs` statement at all — on PostgreSQL, the
    // difference between a no-op and an unrelated healthy approval queueing behind this one.
    expect($statements)->toBeEmpty();

    // …and it was never diverted onto the waitlist merely because its Club happened to be full: the at-capacity
    // branch is reachable only from `applied` (which diverts) and `waiting_list` (which is rejected).
    $persisted = Profile::findOrFail($profile->id);

    expect($persisted->state)->toBe($from)
        ->and($persisted->state)->not->toBe(ProfileState::WaitingList)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    // The dataset sweeps the LATER gate's three outcomes under each from-state, so the guard's independence from
    // capacity is demonstrated rather than coincidental. Capacity `0` is full while empty (`wouldOversell()` is true
    // at every occupancy), so it needs no seated members; the `free seat` rows are capped well above the occupancy
    // the subject itself contributes — `active` occupies a seat, `lapsed` does not.
    'active · at parity — the gate would have DIVERTED' => [ProfileState::Active, 0, 0],
    'active · a free seat — the gate would have PASSED' => [ProfileState::Active, 5, 1],
    'active · explicitly uncapped — no gate to run' => [ProfileState::Active, null, 1],
    'lapsed · at parity — the gate would have DIVERTED' => [ProfileState::Lapsed, 0, 0],
    'lapsed · a free seat — the gate would have PASSED' => [ProfileState::Lapsed, 5, 1],
    'lapsed · explicitly uncapped — no gate to run' => [ProfileState::Lapsed, null, 1],
]);

it('takes the parties_clubs row lock strictly before it counts the seats (design D3)', function () {
    $club = Club::factory()->create();
    seatClubTo($club, capacity: 2, occupied: 1);

    $profile = Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::Applied]);

    /** @var list<string> $statements */
    $statements = [];

    // Capture, in order, only the statements against the two tables the gate touches. `DB::transaction()` issues its
    // SAVEPOINT through the raw PDO handle, so it never lands here; the Customer read and the event insert hit other
    // tables. What remains is the Action's own Profile/Club traffic.
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        if (str_contains($query->sql, 'parties_clubs') || str_contains($query->sql, 'parties_profiles')) {
            $statements[] = $query->sql;
        }
    });

    app(ApproveProfile::class)->handle($profile->id);

    // The first three statements of a seat-consuming approval, in the order that IS the fix: re-read the Profile row
    // under its lock (the from-state guarantee), then lock the CLUB row, and only THEN count the seats. Locking the
    // Profile row serialises nothing here — two concurrent approvals in one Club lock two different Profile rows and
    // both observe the same last free seat.
    expect($statements[0])->toContain('parties_profiles')
        ->and($statements[1])->toContain('parties_clubs')
        ->and($statements[2])->toContain('parties_profiles')
        ->and($statements[2])->toContain('count(*)');

    if (DB::getDriverName() === 'pgsql') {
        // The lock-truth engine: the Club select really is `SELECT … FOR UPDATE`, so same-Club approvals queue. That
        // serialisation is proved behaviourally, with two live connections, in task 3.2 — that lane only.
        expect($statements[1])->toContain('for update');
    } else {
        // SQLite's grammar compiles NO lock clause, so the lock is a no-op here and the concurrency claim is not
        // provable on this engine. What survives is the statement ORDER above — the property PostgreSQL turns into
        // serialisation — and the sequential gate, which every other test in this file pins.
        expect($statements[1])->not->toContain('for update');
    }
});
