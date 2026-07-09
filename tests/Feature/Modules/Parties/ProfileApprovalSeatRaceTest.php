<?php

use App\Modules\Parties\Actions\ApproveProfile;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileActivated;
use App\Modules\Parties\Events\WaitingListJoined;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Support\ClubSeatOccupancy;
use App\Platform\Events\DomainEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

/**
 * THE CONCURRENCY PROOF OF THE SEAT GATE (parties-hero-package task 3.2, design D3; party-registry — Scenario:
 * *Concurrent approvals into one Club serialise on the Club row*; CLAUDE.md invariant 1).
 *
 * This file exists because every OTHER test of the gate passes against the racy implementation. `AC-K-J-13` drives a
 * *sequential* 51st approve, and `ProfileApprovalCapacityGateTest` pins the SQL statement order — but statement order
 * is not serialisation, and a sequential approve never contends for anything. The bug D3 closes is only observable
 * when two transactions are open on one Club at once: locking the **Profile** row serialises nothing, because two
 * concurrent approvals of DIFFERENT Profiles in one Club lock DIFFERENT rows, both read `49/50`, and both pass. The
 * fix is the `parties_clubs` row lock, acquired STRICTLY BEFORE the occupancy count. Nothing else in the suite can
 * tell the two implementations apart.
 *
 * HOW TWO CONCURRENT TRANSACTIONS ARE DRIVEN FROM ONE PROCESS. Transaction A is the default connection, opened by
 * hand: `ApproveProfile`'s own `DB::transaction` then nests as a SAVEPOINT, so when the Action returns, A is still
 * open and still holds every lock it took — exactly where a real in-flight approval sits at the gate. Transaction B
 * is a SECOND, genuinely independent PostgreSQL session ({@see heroPackageRacerConnection()}) made the default
 * connection for the duration of its call, so the very same Action — its models, its `DB::transaction`, its recorder
 * — resolves onto it. B then invokes `ApproveProfile` on the Club's last free seat and blocks on A's Club row.
 *
 * A single PHP thread cannot let A commit while B waits, so B's wait is made OBSERVABLE rather than eternal:
 * `lock_timeout` fires on B, and the timeout IS the serialisation — the racy implementation never blocks at all, it
 * reads a committed occupancy of `0`, activates, and the Club ends with two seats against a capacity of one. B's
 * transaction rolls back having written nothing; A commits; B — the waiter, now granted the lock — re-reads and finds
 * the seat taken. That is precisely the sequence a real blocked backend executes once the lock is released, and it
 * yields the criterion's outcome: exactly one `Active`, one `WaitingList`, occupancy never above capacity.
 *
 * ENGINE ASYMMETRY, BOTH HALVES ASSERTED, NEVER SKIPPED (the house idiom of
 * `tests/Feature/Platform/ActorRoleConstraintTest.php:110` and its docblock `:17-23`; there is no `markTestSkipped`
 * anywhere in `tests/` and this file does not add the first):
 *   - pgsql  → the two-connection race above. This is the ONLY lane in which D3 is falsifiable.
 *   - sqlite → `lockForUpdate` compiles to no clause at all, and a `:memory:` database is private to its connection,
 *              so a second session cannot even see the fixture. The concurrency claim is not provable here. What IS
 *              asserted, positively, is the *sequential* half of the same criterion: the gate the lock protects still
 *              diverts the second approval. Both lanes therefore converge on the identical outcome assertions below;
 *              only the arrangement differs.
 *
 * Trait — `DatabaseMigrations`, not `RefreshDatabase`: a wrapper transaction would keep the fixture uncommitted and
 * therefore invisible to transaction B, and would make A's hand-rolled `beginTransaction()` a savepoint of the
 * test's own transaction rather than a top-level one holding real locks.
 */
uses(DatabaseMigrations::class);

/**
 * Open transaction B's session: a second connection with the default connection's own credentials, so both sessions
 * address the same physical database.
 *
 * `lock_timeout` bounds the wait. In a real deployment B blocks until A commits and the backend then proceeds; in
 * one PHP thread A cannot commit while B blocks, so the block is surfaced as SQLSTATE 55P03 and the test resumes it
 * by hand. 750ms is far longer than an uncontended `SELECT … FOR UPDATE` on a one-row table (the different-Club test
 * relies on that headroom), and short enough that a regression fails fast rather than hanging the suite.
 */
function heroPackageRacerConnection(): string
{
    $name = 'pgsql_hero_racer';

    config()->set('database.connections.'.$name, config('database.connections.'.DB::getDefaultConnection()));

    DB::purge($name);
    DB::connection($name)->statement("SET lock_timeout = '750ms'");

    return $name;
}

/**
 * Invoke the REAL `ApproveProfile` with $connection as the default connection for the duration of the call, and
 * return the `QueryException` message it raised — or `''` when it completed. The Action resolves its transaction, its
 * models and its recorder through `DB::connection(null)`, i.e. `config('database.default')`, so swapping that key is
 * what puts an independent session behind an otherwise untouched Action.
 *
 * A `null` $connection runs on the default connection (transaction A, and the whole SQLite lane). Only
 * `QueryException` is caught: a domain rejection must still surface, never be read as a lock.
 */
function heroPackageApproveOn(?string $connection, int $profileId): string
{
    $previous = DB::getDefaultConnection();

    if ($connection !== null) {
        DB::setDefaultConnection($connection);
    }

    try {
        app(ApproveProfile::class)->handle($profileId);

        return '';
    } catch (QueryException $e) {
        return $e->getMessage();
    } finally {
        DB::setDefaultConnection($previous);
    }
}

/** A Club capped at $capacity seats, none of them occupied. Merges into `by_club_id` so two Clubs may be capped. */
function heroPackageCappedClub(int $capacity): Club
{
    $club = Club::factory()->create();
    $byClubId = config('parties.hero_package.capacity.by_club_id');

    config()->set('parties.hero_package.capacity.by_club_id', array_replace(
        is_array($byClubId) ? $byClubId : [],
        [$club->id => $capacity],
    ));

    return $club;
}

/** An `applied` Profile under its own Customer — two of them may therefore coexist in one Club. */
function heroPackageApplicant(Club $club): Profile
{
    return Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::Applied]);
}

it('serialises two concurrent same-Club approvals on the parties_clubs row: exactly one lands active, the other waiting_list', function () {
    $club = heroPackageCappedClub(capacity: 1);
    $first = heroPackageApplicant($club);
    $second = heroPackageApplicant($club);

    if (DB::getDriverName() === 'pgsql') {
        $holder = DB::connection(DB::getDefaultConnection());
        $racer = heroPackageRacerConnection();

        try {
            // Transaction A takes the Club's last free seat and STAYS OPEN: the Action's transaction nested as a
            // savepoint, so the `parties_clubs` FOR UPDATE lock is still held and the activation still uncommitted.
            $holder->beginTransaction();

            expect(heroPackageApproveOn(null, $first->id))->toBe('');

            // Transaction B invokes the same Action on the same Club. Its own Profile row is uncontended and the
            // from-state guard passes, so it reaches the seat gate — and blocks there, on A's Club row, until
            // `lock_timeout` cancels the statement. Under the racy implementation (Profile-row lock only, count
            // unguarded) nothing blocks: B reads a committed occupancy of 0 and activates, overselling the Club.
            expect(heroPackageApproveOn($racer, $second->id))
                ->toContain('55P03')          // lock_not_available — locale-independent; no constraint name exists here
                ->toContain('lock timeout');

            // B never counted a seat and never wrote one. Read through B's own session, after its rollback.
            expect(DB::connection($racer)->table('parties_profiles')->where('id', $second->id)->value('state'))
                ->toBe(ProfileState::Applied->value);

            $holder->commit();
        } finally {
            // A must never survive the test with locks held: `migrate:rollback` would block on them.
            if ($holder->transactionLevel() > 0) {
                $holder->rollBack();
            }
        }

        // Nothing the blocked transaction attempted survived it.
        expect(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0);

        // The lock is released and the waiter is granted it. Its occupancy count therefore happens strictly AFTER
        // A's — never beside it — which is the whole of the serialisation claim, and it now sees the taken seat.
        expect(heroPackageApproveOn($racer, $second->id))->toBe('');

        DB::purge($racer);
    } else {
        // SQLite: no lock clause is compiled and `:memory:` is private to its connection, so the race cannot be
        // arranged. Assert the sequential gate the lock exists to protect — positively, never as a skip.
        expect(heroPackageApproveOn(null, $first->id))->toBe('');
        expect(heroPackageApproveOn(null, $second->id))->toBe('');
    }

    // Both lanes converge here — the criterion itself.
    expect(Profile::findOrFail($first->id)->state)->toBe(ProfileState::Active)
        ->and(Profile::findOrFail($second->id)->state)->toBe(ProfileState::WaitingList)
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(1);
});

it('leaves an approval into a different Club unblocked while one Club row is locked', function () {
    $locked = heroPackageCappedClub(capacity: 1);
    $other = heroPackageCappedClub(capacity: 1);
    $here = heroPackageApplicant($locked);
    $there = heroPackageApplicant($other);

    if (DB::getDriverName() === 'pgsql') {
        $holder = DB::connection(DB::getDefaultConnection());
        $racer = heroPackageRacerConnection();

        try {
            $holder->beginTransaction();

            expect(heroPackageApproveOn(null, $here->id))->toBe('');

            // The second half of the design's claim: same-Club approvals serialise, DIFFERENT Clubs stay parallel.
            // B locks its own Club's row and completes immediately, well inside the 750ms `lock_timeout` that the
            // same-Club test exhausts. A table-level lock — or a gate that counted seats globally — would time out.
            expect(heroPackageApproveOn($racer, $there->id))->toBe('');

            $holder->commit();
        } finally {
            if ($holder->transactionLevel() > 0) {
                $holder->rollBack();
            }
        }

        DB::purge($racer);
    } else {
        // SQLite: the gates are still per-Club — each Club's capacity is consulted against its own occupancy.
        expect(heroPackageApproveOn(null, $here->id))->toBe('');
        expect(heroPackageApproveOn(null, $there->id))->toBe('');
    }

    expect(Profile::findOrFail($here->id)->state)->toBe(ProfileState::Active)
        ->and(Profile::findOrFail($there->id)->state)->toBe(ProfileState::Active)
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($locked->id))->toBe(1)
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($other->id))->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(2)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0);
});
