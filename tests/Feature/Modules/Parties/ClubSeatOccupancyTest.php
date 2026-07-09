<?php

use App\Modules\Parties\Contracts\HeroPackageCapacityReader;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Support\ClubSeatOccupancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pins the K-internal seat ledger {@see ClubSeatOccupancy} (parties-hero-package task 1.2, design D3/D6/D10; ADR
 * 2026-07-09-hero-package-capacity-seat-set-and-waitinglist; § 13 / AC-K-J-13).
 *
 * Three claims, each load-bearing for the whole change:
 *   1. THE SEAT SET IS `Active` + `Suspended`. All nine Profile states are driven through one Club — the AC-K-J-13
 *      seat-set proof. `Suspended` occupies (a Hold must never evict a member — AC-K-FSM-2a); `Applied` /
 *      `WaitingList` do not (which is why the birth gate cannot oversell — D6); `Approved` does not (it is a
 *      transient pass-through, so counting it would count the seat twice — D4); the four departed states do not.
 *   2. THE CLUB-ROW LOCK IS TAKEN STRICTLY BEFORE THE COUNT. Asserted structurally, by the ORDER of the SQL the
 *      helper emits — the ordering IS the oversell fix (D3), and no behavioural test on SQLite can observe it. The
 *      driver gate asserts BOTH halves and never skips (the `ActorRoleConstraintTest:110` house idiom): PostgreSQL
 *      compiles `for update`, SQLite compiles no lock clause at all, so the serialisation proof itself lives in the
 *      PG17 lane (task 3.2), not here.
 *   3. `null` CAPACITY IS UNCAPPED, `0` IS A CAPACITY. The predicate reads the {@see HeroPackageCapacityReader}
 *      port (never config directly) and compares with `>=`, never `>`.
 *
 * RefreshDatabase per the directory convention. `wouldOversell()` touches no database at all — the tests that pin
 * it use ids that need not exist, which is itself the property `CreateProfile` relies on to consult it lock-free.
 */
uses(RefreshDatabase::class);

/**
 * Run $work while capturing, in order, every SQL statement it emitted against the two Parties tables the seat
 * ledger touches. `DB::transaction()` issues its SAVEPOINT through the raw PDO handle, so it never appears here —
 * what lands in the list is exactly the helper's own queries.
 *
 * @param  Closure(): mixed  $work
 * @return list<string>
 */
function seatLedgerQueries(Closure $work): array
{
    /** @var list<string> $statements */
    $statements = [];

    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        if (str_contains($query->sql, 'parties_clubs') || str_contains($query->sql, 'parties_profiles')) {
            $statements[] = $query->sql;
        }
    });

    $work();

    return $statements;
}

it('counts the Active + Suspended seat set and excludes the other seven Profile states', function () {
    $club = Club::factory()->create();

    // One Profile per state, each under its own Customer — the partial unique index on (customer_id, club_id)
    // admits at most one non-terminal Profile per pair, so distinct Customers are what let all nine coexist.
    foreach (ProfileState::cases() as $state) {
        Profile::factory()->create(['club_id' => $club->id, 'state' => $state]);
    }

    // Guard the premise: all nine rows really landed, so a count of 2 means "seven excluded", not "seven missing".
    expect(Profile::query()->where('club_id', $club->id)->count())->toBe(9)
        ->and(ProfileState::cases())->toHaveCount(9)
        ->and(ClubSeatOccupancy::OCCUPYING_STATES)->toBe([ProfileState::Active, ProfileState::Suspended])
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2);
});

it('counts a lone Profile as a seat only in the occupying states', function (ProfileState $state, int $expected) {
    $club = Club::factory()->create();
    Profile::factory()->create(['club_id' => $club->id, 'state' => $state]);

    expect(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe($expected);
})->with([
    // A suspension is a temporary restriction, never a departure: the seat was never freed (canon § 13.1 / § 10.1).
    'active occupies' => [ProfileState::Active, 1],
    'suspended occupies — a Hold must never evict a member' => [ProfileState::Suspended, 1],
    // Neither birth state holds a seat — the reason CreateProfile's gate cannot oversell (design D6).
    'applied holds no seat' => [ProfileState::Applied, 0],
    'waiting_list holds no seat' => [ProfileState::WaitingList, 0],
    // Transient pass-through: ApproveProfile gates before writing it and drives through to `active` in the same
    // transaction. Counting it would count the same seat twice (design D4).
    'approved holds no seat — it is transient, never durably rested-in' => [ProfileState::Approved, 0],
    // The membership is over; the freed seat is never auto-filled (design D5 — shrink by attrition, no backfill).
    'rejected holds no seat' => [ProfileState::Rejected, 0],
    'lapsed holds no seat' => [ProfileState::Lapsed, 0],
    'cancelled holds no seat' => [ProfileState::Cancelled, 0],
    'inactive holds no seat' => [ProfileState::Inactive, 0],
]);

it('counts seats per Club — a second Club\'s occupied seats never leak in', function () {
    $club = Club::factory()->create();
    $neighbour = Club::factory()->create();

    Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::Active]);
    Profile::factory()->count(3)->create(['club_id' => $neighbour->id, 'state' => ProfileState::Active]);
    Profile::factory()->create(['club_id' => $neighbour->id, 'state' => ProfileState::Suspended]);

    $seats = app(ClubSeatOccupancy::class);

    expect($seats->countOccupiedSeats($club->id))->toBe(1)
        ->and($seats->countOccupiedSeats($neighbour->id))->toBe(4);
});

it('takes the parties_clubs row lock strictly before it counts the seats', function () {
    $club = Club::factory()->create();
    Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::Active]);

    $seats = app(ClubSeatOccupancy::class);
    $occupied = 0;

    $statements = seatLedgerQueries(function () use ($seats, $club, &$occupied): void {
        $occupied = DB::transaction(fn (): int => $seats->lockAndCountOccupiedSeats($club->id));
    });

    // The ORDER is the fix (design D3). Locking the Profile row instead serialises nothing: two concurrent
    // approvals of different Profiles in one Club would lock different rows, both read the last free seat, and
    // both pass. The Club row is the one row every same-Club seat-consuming transaction contends for.
    expect($statements)->toHaveCount(2)
        ->and($statements[0])->toContain('parties_clubs')
        ->and($statements[1])->toContain('parties_profiles')
        ->and($statements[1])->toContain('count(*)')
        ->and($occupied)->toBe(1);

    if (DB::getDriverName() === 'pgsql') {
        // The lock-truth engine: the Club select really is `SELECT … FOR UPDATE`, so same-Club transactions queue.
        // That serialisation is proved behaviourally, with two live connections, in task 3.2 — this lane only.
        expect($statements[0])->toContain('for update');
    } else {
        // SQLite's grammar compiles NO lock clause (Grammar::compileLock returns ''), so the lock is a no-op here
        // and the concurrency claim is unprovable on this engine. What survives is the statement ORDER above —
        // which is precisely the property PostgreSQL turns into serialisation.
        expect($statements[0])->not->toContain('for update');
    }
});

it('agrees with the lock-free count, and fails loudly on a Club that does not exist', function () {
    $club = Club::factory()->create();
    Profile::factory()->count(2)->create(['club_id' => $club->id, 'state' => ProfileState::Suspended]);

    $seats = app(ClubSeatOccupancy::class);

    expect(DB::transaction(fn (): int => $seats->lockAndCountOccupiedSeats($club->id)))
        ->toBe($seats->countOccupiedSeats($club->id))
        ->toBe(2);

    // A seat-consuming transition against an unknown Club is a programming error: gating against a phantom
    // occupancy of zero would silently admit everyone. The lock-free read has no Club to look up, so it answers 0.
    expect(fn () => DB::transaction(fn (): int => $seats->lockAndCountOccupiedSeats($club->id + 1_000)))
        ->toThrow(ModelNotFoundException::class)
        ->and($seats->countOccupiedSeats($club->id + 1_000))->toBe(0);
});

it('never oversells an uncapped Club — a null capacity is not a ceiling of zero', function () {
    // The shipped launch posture: no PARTIES_HERO_PACKAGE_CAPACITY in the test environment, so the gate is inert
    // and every pre-existing Parties test runs against unchanged behaviour.
    expect(config('parties.hero_package.capacity.default'))->toBeNull();

    $seats = app(ClubSeatOccupancy::class);

    // The Club id need not exist: the predicate reads the capacity port and compares. No database access — which
    // is what lets CreateProfile consult it without taking the Club-row lock (design D6).
    expect($seats->wouldOversell(1, 0))->toBeFalse()
        ->and($seats->wouldOversell(1, 10_000))->toBeFalse();
});

it('reports an oversell at parity and above, and never below', function (int $capacity, int $occupied, bool $expected) {
    config()->set('parties.hero_package.capacity.default', $capacity);

    expect(app(ClubSeatOccupancy::class)->wouldOversell(1, $occupied))->toBe($expected);
})->with([
    'an empty Club under a capacity of 2' => [2, 0, false],
    'the last free seat is still free' => [2, 1, false],
    // The comparison is `>=`, not `>`: at parity the next seat is the breach. This is the 51st-approve instant.
    'exact parity has no free seat' => [2, 2, true],
    // A capacity lowered beneath the sitting members must not admit anyone either.
    'an occupancy above a lowered capacity' => [2, 3, true],
    // Zero is a real capacity (a Club admitting nobody), never confused with an absent one.
    'a capacity of zero oversells on its very first seat' => [0, 0, true],
]);

it('honours a per-Club capacity override beneath a capped default', function () {
    $club = Club::factory()->create();

    config()->set('parties.hero_package.capacity.default', 50);
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => 2]);

    $seats = app(ClubSeatOccupancy::class);

    expect($seats->wouldOversell($club->id, 2))->toBeTrue()
        ->and($seats->wouldOversell($club->id, 1))->toBeFalse()
        // Its neighbour still answers to the global default.
        ->and($seats->wouldOversell($club->id + 1, 2))->toBeFalse()
        ->and($seats->wouldOversell($club->id + 1, 50))->toBeTrue();
});

it('reads the capacity through the injected port, never from config directly', function () {
    $club = Club::factory()->create();

    // A capped Club and an uncapped one, so BOTH arms of the port's `?int` return are exercised — a fake that can
    // never answer `null` is too weak to prove the consumer handles the uncapped arm (and PHPStan max says so).
    app()->bind(HeroPackageCapacityReader::class, fn (): HeroPackageCapacityReader => new class($club->id) implements HeroPackageCapacityReader
    {
        public function __construct(private readonly int $cappedClubId) {}

        public function forClub(int $clubId): ?int
        {
            return $clubId === $this->cappedClubId ? 0 : null;
        }
    });

    $seats = app(ClubSeatOccupancy::class);

    expect($seats->wouldOversell($club->id, 0))->toBeTrue()
        ->and($seats->wouldOversell($club->id + 1, 10_000))->toBeFalse()
        // Config was never written, so the answers above can only have come through the port.
        ->and(config('parties.hero_package.capacity.default'))->toBeNull();
});
