<?php

use App\Modules\Parties\Actions\RenewProfile;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileRenewed;
use App\Modules\Parties\Events\WaitingListJoined;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Support\ClubSeatOccupancy;
use App\Platform\Events\DomainEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pins `RenewProfile`'s HERO-PACKAGE CAPACITY GATE (parties-hero-package task 2.4, design D3/D8/D9; canon
 * MVP-DEC-017 / § 13.1:629; ADR 2026-07-09-hero-package-capacity-seat-set-and-waitinglist).
 *
 * ⚠️ THE NAMING TRAP THIS FILE EXISTS TO DEFEND (design D9). `RenewProfile` is the 30-day grace RE-ACTIVATION
 * (`lapsed → active`), and a `lapsed` Profile released its seat — so the renewal RE-CONSUMES one and IS cap-gated.
 * The *grandfathered* renewal of canon MVP-DEC-011 / AC-K-J-15a — explicitly NOT cap-gated — is the period rollover
 * of an already-`active` Profile into a new club year, which this codebase does not model at all (no `valid_to`, no
 * period column, no rollover Action). Same word, opposite rule: a future reader who "grandfathers" this Action
 * breaks CLAUDE.md invariant 1.
 *
 * Five claims:
 *   1. WITHIN GRACE, WITH A FREE SEAT — unchanged behaviour: `active`, `lapsed_at` cleared, exactly one
 *      `ProfileRenewed`, and the Club's occupancy rises by one (the seat really is re-consumed).
 *   2. AT PARITY, THE RENEWAL THROWS — AND NEVER DIVERTS. Unlike an `applied` approval (which LANDS in
 *      `waiting_list`), canon draws no `lapsed → waiting_list` edge; diverting would clear `lapsed_at` and burn the
 *      grace clock. The Profile stays `lapsed`, its anchor intact, its grace still running, and no event is recorded.
 *   3. THE REJECTION IS NOT TERMINAL. Free a seat and the same Profile renews, still inside its window.
 *   4. THE GRACE SUB-GATE IS EVALUATED FIRST. Past grace, the rejection names the GRACE reason **regardless of
 *      capacity** — and neither a past-grace nor an out-of-state call locks the Club row at all.
 *   5. AN UNCAPPED CLUB IS UNGATED, at any occupancy — the shipped launch posture, and the reason every pre-existing
 *      renewal test runs against unchanged behaviour.
 *
 * Capacity is set per-test via `config()->set(...)`, never via the environment: no `PARTIES_HERO_PACKAGE_CAPACITY`
 * exists in the test environment, so the default is `null` ⇒ uncapped.
 *
 * RefreshDatabase per the directory convention; the Action opens its own `DB::transaction`, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper. The clock is frozen (the
 * SweepTest/ProfileLapseGraceTest idiom) so the 30-day boundary arithmetic is deterministic on both engines, and
 * `lapsed_at` is compared as an INSTANT via `equalTo` (tz-robust across the SQLite/PG `timestamptz` asymmetry).
 * Event payloads are asserted BY KEY, never as a byte-compare of stored JSON (PostgreSQL's `jsonb` reorders keys).
 */
uses(RefreshDatabase::class);

// Reset the frozen clock after each test so the global test-now never leaks into a sibling (the SweepTest idiom).
afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * Cap $club at $capacity seats and seat $occupied `Active` members in it, each under its own Customer (the partial
 * unique index on `(customer_id, club_id)` admits one non-terminal Profile per pair). Named distinctly from
 * `ProfileApprovalCapacityGateTest`'s `seatClubTo()`: Pest loads every selected test file into ONE process while
 * building the suite, so two global helpers may never share a name — a duplicate is a fatal redeclare, not a shadow.
 */
function renewalSeatClubTo(Club $club, int $capacity, int $occupied): void
{
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => $capacity]);

    Profile::factory()->count($occupied)->create([
        'club_id' => $club->id,
        'state' => ProfileState::Active,
    ]);
}

/** Free one seat by attrition, WITHOUT an Action — the fixture must record no event of its own. */
function renewalFreeOneSeat(Club $club): void
{
    $seat = Profile::query()
        ->where('club_id', $club->id)
        ->where('state', ProfileState::Active->value)
        ->firstOrFail();

    Profile::query()->whereKey($seat->id)->update(['state' => ProfileState::Cancelled->value]);
}

/**
 * A `lapsed` Profile in $club whose grace anchor sits $daysAgo days back — the factory pins the from-state directly
 * (it drives no Action and records no event), so the renewal under test starts from a clean event log.
 */
function renewalLapsedProfile(Club $club, int $daysAgo): Profile
{
    return Profile::factory()->create([
        'club_id' => $club->id,
        'state' => ProfileState::Lapsed,
        'lapsed_at' => CarbonImmutable::now()->subDays($daysAgo),
    ]);
}

it('renews a lapsed Profile within grace when a seat is free, clearing lapsed_at and re-consuming the seat', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-19 12:00:00', 'UTC'));

    $club = Club::factory()->create();
    renewalSeatClubTo($club, capacity: 2, occupied: 1);   // one free seat

    $profile = renewalLapsedProfile($club, daysAgo: 10);   // well inside the 30-day grace

    // A `lapsed` Profile holds NO seat, so the Club sits at 1 of 2 before the renewal.
    expect(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(1);

    $returned = app(RenewProfile::class)->handle($profile->id);

    $persisted = Profile::findOrFail($profile->id);
    expect($returned->state)->toBe(ProfileState::Active)
        ->and($persisted->state)->toBe(ProfileState::Active)
        ->and($persisted->lapsed_at)->toBeNull()
        // The seat really is re-consumed (canon § 13.1) — the occupancy rose by exactly one.
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2);

    // A capped Club is not a gated Club: below parity the historical path runs untouched — exactly one ProfileRenewed
    // (never `ProfileReactivated`, the suspend-restore edge — design L3) and nothing else.
    $renewed = DomainEvent::query()->where('name', ProfileRenewed::NAME)->sole();

    expect(DomainEvent::query()->count())->toBe(1)
        ->and($renewed->entity_id)->toBe((string) $profile->id);

    expect(array_keys($renewed->payload))->toEqualCanonicalizing(['profile_id', 'state']);
    expect($renewed->payload['state'])->toBe('active');
});

it('rejects a within-grace renewal into a Club at parity, leaving it lapsed with its grace clock still running', function () {
    $now = CarbonImmutable::parse('2026-06-19 12:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    $club = Club::factory()->create();
    renewalSeatClubTo($club, capacity: 2, occupied: 2);   // at exact parity

    $lapsedAt = $now->subDays(10);
    $profile = renewalLapsedProfile($club, daysAgo: 10);

    // There is no edge to take — canon draws only `applied → waiting_list` — so the gate raises rather than diverting.
    // The reason names the two cardinals the gate itself decided on, in the operator's language.
    expect(fn () => app(RenewProfile::class)->handle($profile->id))
        ->toThrow(
            IllegalProfileTransition::class,
            (string) __('parties.profile.club_at_capacity', [
                'state' => 'lapsed',
                'capacity' => 2,
                'occupied' => 2,
            ]),
        );

    // The transaction rolled back before any write. `lapsed_at` is the load-bearing assertion: a divert to
    // `waiting_list` would have cleared it and burned the member's remaining 20 days of grace.
    $persisted = Profile::findOrFail($profile->id);
    expect($persisted->state)->toBe(ProfileState::Lapsed)
        ->and($persisted->state)->not->toBe(ProfileState::WaitingList)
        ->and($persisted->lapsed_at?->equalTo($lapsedAt))->toBeTrue()
        // No `WaitingListJoined` — this Action has no waitlist entry point at all (design D8).
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', ProfileRenewed::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0)
        // The invariant itself: the gate admitted nobody, so occupancy never exceeded capacity.
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2);
});

it('renews the same Profile once a seat frees, still inside its untouched grace window', function () {
    $now = CarbonImmutable::parse('2026-06-19 12:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    $club = Club::factory()->create();
    renewalSeatClubTo($club, capacity: 2, occupied: 2);

    $profile = renewalLapsedProfile($club, daysAgo: 10);

    expect(fn () => app(RenewProfile::class)->handle($profile->id))->toThrow(IllegalProfileTransition::class);

    renewalFreeOneSeat($club);

    // Nothing promoted the Profile when the seat freed — no listener, scheduler, job or observer (design D5). The
    // capacity rejection was never terminal: the grace clock kept running, so the same call now succeeds.
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Lapsed)
        ->and(app(RenewProfile::class)->handle($profile->id)->state)->toBe(ProfileState::Active);

    expect(Profile::findOrFail($profile->id)->lapsed_at)->toBeNull()
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2)
        ->and(DomainEvent::query()->where('name', ProfileRenewed::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->count())->toBe(1);
});

it('reports the GRACE reason for a past-grace renewal regardless of capacity, and locks no Club row', function (?int $capacity, int $occupied) {
    $now = CarbonImmutable::parse('2026-06-19 12:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    $club = Club::factory()->create();

    // A `null` capacity is UNCAPPED — pinned per-Club by an EXPLICIT null override, which the adapter honours on
    // `array_key_exists` rather than `??` (so it is a genuinely uncapped Club, not a very large cap).
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => $capacity]);
    Profile::factory()->count($occupied)->create(['club_id' => $club->id, 'state' => ProfileState::Active]);

    $lapsedAt = $now->subDays(31);
    $profile = renewalLapsedProfile($club, daysAgo: 31);   // one full day past the inclusive 30-day edge

    /** @var list<string> $statements */
    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        if (str_contains($query->sql, 'parties_clubs')) {
            $statements[] = $query->sql;
        }
    });

    // THE ORDER IS THE CLAIM (design D8/D9). The grace sub-gate runs BEFORE the capacity gate, so an expired
    // membership is told its membership expired — not that the Club is full. Were the gate evaluated first, the
    // `$posture` = "at parity" row would report a capacity the member could do nothing about.
    expect(fn () => app(RenewProfile::class)->handle($profile->id))
        ->toThrow(IllegalProfileTransition::class, (string) __('parties.profile.cannot_renew', ['state' => 'lapsed']));

    // …and a doomed call serialises nothing: it never reached `lockAndCountOccupiedSeats()`, so no `parties_clubs`
    // statement was emitted at all. On PostgreSQL that is the difference between a no-op and an unrelated renewal
    // queueing behind this one.
    expect($statements)->toBeEmpty();

    $persisted = Profile::findOrFail($profile->id);
    expect($persisted->state)->toBe(ProfileState::Lapsed)
        ->and($persisted->lapsed_at?->equalTo($lapsedAt))->toBeTrue()
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'at parity — capacity is NOT the reason' => [2, 2],
    'a free seat — the grace guard rejects anyway' => [2, 1],
    'uncapped — the grace guard is capacity-independent' => [null, 1],
]);

it('guards the from-state BEFORE the capacity gate — an out-of-state renewal into a full Club throws cannotRenew, never clubAtCapacity', function (ProfileState $from) {
    $club = Club::factory()->create();

    // Capacity `0` is the cheapest at-parity fixture there is: a Club admitting nobody is full while still empty,
    // so this Club is at parity for every one of the eight states below.
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => 0]);

    $profile = Profile::factory()->create(['club_id' => $club->id, 'state' => $from]);

    expect(fn () => app(RenewProfile::class)->handle($profile->id))
        ->toThrow(IllegalProfileTransition::class, (string) __('parties.profile.cannot_renew', ['state' => $from->value]));

    expect(Profile::findOrFail($profile->id)->state)->toBe($from)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'applied' => [ProfileState::Applied],
    'waiting_list' => [ProfileState::WaitingList],
    'approved' => [ProfileState::Approved],
    'rejected' => [ProfileState::Rejected],
    'active' => [ProfileState::Active],
    'suspended' => [ProfileState::Suspended],
    'cancelled' => [ProfileState::Cancelled],
    'inactive' => [ProfileState::Inactive],
]);

it('rejects a within-grace renewal into a zero-capacity Club — 0 is a real capacity, never an absence', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-19 12:00:00', 'UTC'));

    $club = Club::factory()->create();
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => 0]);

    $profile = renewalLapsedProfile($club, daysAgo: 10);

    // A Club admitting nobody oversells on its very first seat, so it is at parity while still empty — and the
    // reason says so, naming `0 of 0`. An `empty()`/`??` capacity read would have treated this as uncapped.
    expect(fn () => app(RenewProfile::class)->handle($profile->id))
        ->toThrow(
            IllegalProfileTransition::class,
            (string) __('parties.profile.club_at_capacity', ['state' => 'lapsed', 'capacity' => 0, 'occupied' => 0]),
        );

    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Lapsed)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('renews into an uncapped Club however many members it already seats', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-19 12:00:00', 'UTC'));

    $club = Club::factory()->create();

    // The shipped launch posture (a dark launch): no PARTIES_HERO_PACKAGE_CAPACITY in the test environment.
    expect(config('parties.hero_package.capacity.default'))->toBeNull();

    Profile::factory()->count(3)->create(['club_id' => $club->id, 'state' => ProfileState::Active]);
    Profile::factory()->count(2)->create(['club_id' => $club->id, 'state' => ProfileState::Suspended]);

    $profile = renewalLapsedProfile($club, daysAgo: 30);   // the inclusive grace edge, and still ungated

    expect(app(RenewProfile::class)->handle($profile->id)->state)->toBe(ProfileState::Active)
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(6)
        ->and(DomainEvent::query()->where('name', ProfileRenewed::NAME)->count())->toBe(1);
});

it('takes the parties_clubs row lock strictly before it counts the seats (design D3)', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-19 12:00:00', 'UTC'));

    $club = Club::factory()->create();
    renewalSeatClubTo($club, capacity: 2, occupied: 1);

    $profile = renewalLapsedProfile($club, daysAgo: 10);

    /** @var list<string> $statements */
    $statements = [];

    // Capture, in order, only the statements against the two tables the gate touches. `DB::transaction()` issues its
    // SAVEPOINT through the raw PDO handle, so it never lands here; the event insert hits another table.
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        if (str_contains($query->sql, 'parties_clubs') || str_contains($query->sql, 'parties_profiles')) {
            $statements[] = $query->sql;
        }
    });

    app(RenewProfile::class)->handle($profile->id);

    // The first three statements of a seat-consuming renewal, in the order that IS the fix: re-read the Profile row
    // under its lock (the from-state + grace guarantee), then lock the CLUB row, and only THEN count the seats.
    expect($statements[0])->toContain('parties_profiles')
        ->and($statements[1])->toContain('parties_clubs')
        ->and($statements[2])->toContain('parties_profiles')
        ->and($statements[2])->toContain('count(*)');

    if (DB::getDriverName() === 'pgsql') {
        // The lock-truth engine: the Club select really is `SELECT … FOR UPDATE`, so same-Club seat-consuming
        // transitions queue. That serialisation is proved behaviourally, with two live connections, in task 3.2.
        expect($statements[1])->toContain('for update');
    } else {
        // SQLite's grammar compiles NO lock clause, so the lock is a no-op here and the concurrency claim is not
        // provable on this engine. What survives is the statement ORDER above — the property PostgreSQL turns into
        // serialisation — and the sequential gate, which every other test in this file pins.
        expect($statements[1])->not->toContain('for update');
    }
});
