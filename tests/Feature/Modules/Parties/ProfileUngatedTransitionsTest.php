<?php

use App\Modules\Parties\Actions\ActivateProfile;
use App\Modules\Parties\Actions\ApproveProfile;
use App\Modules\Parties\Actions\ReactivateProfile;
use App\Modules\Parties\Actions\SuspendProfile;
use App\Modules\Parties\Contracts\HeroPackageCapacityReader;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\OriginatingClubLocked;
use App\Modules\Parties\Events\ProfileActivated;
use App\Modules\Parties\Events\ProfileReactivated;
use App\Modules\Parties\Events\ProfileSuspended;
use App\Modules\Parties\Events\WaitingListJoined;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Support\ClubSeatOccupancy;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pins the two transitions into `active` that the Hero-Package seat gate deliberately DOES NOT gate
 * (parties-hero-package task 3.1, design D4; § 13.1 / § 10.1 / AC-K-J-13 leg 2 / AC-K-FSM-2a; canon MVP-DEC-017;
 * ADR 2026-07-09-hero-package-capacity-seat-set-and-waitinglist).
 *
 * A non-gate is invisible: nothing in a diff shows an absent `if`. So the absence is pinned here as behaviour, and
 * a later iteration that "completes" the gate by copying it onto {@see ReactivateProfile} or {@see ActivateProfile}
 * turns this file red. Four claims:
 *   1. `ReactivateProfile` RESTORES AT PARITY. A `suspended` Profile never released its seat (the seat set is
 *      `active` + `suspended`), so `suspended → active` re-consumes nothing. Gating it would let a temporary Hold
 *      EVICT a member — AC-K-FSM-2a.
 *   2. …AND IT RESTORES EVEN BENEATH A LOWERED CAPACITY, where `wouldOversell()`'s `>=` would certainly refuse. A
 *      capacity decrease must shrink a Club by attrition, never by expelling the members already sitting in it.
 *   3. `SuspendProfile` FREES NO SEAT. The complement of claim 1, and the reason it is sound: at parity, suspending
 *      a member does not open a seat for the next applicant, who is still diverted onto the waitlist.
 *   4. `ActivateProfile` IS UNGATED because `Approved` is TRANSIENT. Its only caller, {@see ApproveProfile}, has
 *      already decided the seat under the `parties_clubs` row lock in the same transaction; a gate here would
 *      re-decide it. Proved directly, by placing a Profile in `approved` — an arrangement no Action can produce.
 *
 * Plus the structural pin: neither Action injects, imports, or otherwise reaches the seat ledger — the doomed call
 * emits no `parties_clubs` statement at all. That negative shape (a claim about a gate that never ran, pinned by
 * the trace it did not leave) is the same one `ProfileRenewalCapacityGateTest` uses for its grace sub-gate; the
 * reason it reports would prove nothing, because an ungated Action reports nothing.
 *
 * Capacity is set per-test via `config()->set(...)`, never via the environment: no `PARTIES_HERO_PACKAGE_CAPACITY`
 * exists in the test environment, so the default is `null` ⇒ uncapped, and an ungated Action would look identical
 * to a gated one. Every Club below is therefore explicitly capped and explicitly at (or over) parity.
 *
 * RefreshDatabase per the directory convention; each Action opens its own `DB::transaction`, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper. Event payloads are asserted BY
 * KEY, never as a byte-compare of stored JSON (PostgreSQL's `jsonb` reorders keys).
 */
uses(RefreshDatabase::class);

/**
 * Cap $club at $capacity seats and seat $occupied `Active` members in it, each under its own Customer (the partial
 * unique index on `(customer_id, club_id)` admits one non-terminal Profile per pair). Named distinctly from the
 * `seatClubTo()` / `renewalSeatClubTo()` / `clubAtCapacity()` siblings: Pest loads every selected test file into ONE
 * process while building the suite, so two global helpers may never share a name — a duplicate is a fatal redeclare
 * that kills the whole run, not a shadow.
 */
function ungatedSeatClubTo(Club $club, int $capacity, int $occupied): void
{
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => $capacity]);

    Profile::factory()->count($occupied)->create([
        'club_id' => $club->id,
        'state' => ProfileState::Active,
    ]);
}

/**
 * The constructor parameter types of $action, in order. This is the honest pin for "this Action injects no seat
 * ledger and no capacity port": a `grep` for `ClubSeatOccupancy` passes on a promoted property renamed to something
 * innocuous, and passes on a `Club::query()` call that imports nothing at all. The type list cannot be talked around.
 *
 * @param  class-string  $action
 * @return list<string>
 */
function ungatedConstructorTypes(string $action): array
{
    $constructor = (new ReflectionClass($action))->getConstructor();

    if ($constructor === null) {
        return [];
    }

    return array_map(function (ReflectionParameter $parameter): string {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType ? $type->getName() : '(untyped)';
    }, $constructor->getParameters());
}

it('restores a suspended Profile into a Club at exact parity — a Hold must never evict a member (AC-K-FSM-2a)', function () {
    $club = Club::factory()->create();

    // One `Active` member plus the `Suspended` one below fills both seats: `suspended` OCCUPIES, so this Club is at
    // exact parity WHILE the member under test sits outside the door.
    ungatedSeatClubTo($club, capacity: 2, occupied: 1);

    $profile = Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::Suspended]);

    expect(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2);

    // No `clubAtCapacity` rejection: the seat was never freed, so there is nothing to re-acquire. Were this Action
    // gated, a suspension for a KYC re-check would become a permanent expulsion the moment the Club filled up.
    $returned = app(ReactivateProfile::class)->handle($profile->id);

    expect($returned->state)->toBe(ProfileState::Active)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active)
        // The restore re-consumed NOTHING: occupancy is exactly what it was before it ran.
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2);

    $reactivated = DomainEvent::query()->where('name', ProfileReactivated::NAME)->sole();

    expect(DomainEvent::query()->count())->toBe(1)
        ->and($reactivated->entity_id)->toBe((string) $profile->id);

    expect(array_keys($reactivated->payload))->toEqualCanonicalizing(['profile_id', 'state']);
    expect($reactivated->payload['state'])->toBe('active');
});

it('restores a suspended Profile even when the capacity now sits BELOW the members already seated', function () {
    $club = Club::factory()->create();

    // A capacity DECREASE (Module A's surface, AC-K-J-14 — not built here) can leave a Club over its own ceiling.
    ungatedSeatClubTo($club, capacity: 1, occupied: 2);

    $profile = Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::Suspended]);

    // 3 occupied against a capacity of 1 — `wouldOversell()` compares with `>=`, so a gate here would refuse with
    // certainty. Canon shrinks a Club by attrition (MVP-DEC-011), never by expelling a member who already sits in it.
    expect(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(3)
        ->and(app(ReactivateProfile::class)->handle($profile->id)->state)->toBe(ProfileState::Active)
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(3)
        ->and(DomainEvent::query()->where('name', ProfileReactivated::NAME)->count())->toBe(1);
});

it('does not free a seat when a member is suspended — the next applicant is still diverted to the waitlist', function () {
    $club = Club::factory()->create();
    ungatedSeatClubTo($club, capacity: 2, occupied: 2);

    $seat = Profile::query()
        ->where('club_id', $club->id)
        ->where('state', ProfileState::Active->value)
        ->firstOrFail();

    app(SuspendProfile::class)->handle($seat->id);

    // THE COMPLEMENT OF THE NON-GATE, and the reason it is sound: the suspension kept its seat. If `SuspendProfile`
    // freed one, `ReactivateProfile` would have to re-acquire it — and then a full Club could evict on restore.
    expect(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2);

    $customer = Customer::factory()->create();
    $applicant = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Applied,
    ]);

    // So the Club is still full, and the applicant lands on the waitlist rather than in the suspended member's chair.
    expect(app(ApproveProfile::class)->handle($applicant->id)->state)->toBe(ProfileState::WaitingList)
        ->and(Profile::findOrFail($applicant->id)->state)->toBe(ProfileState::WaitingList)
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2)
        ->and(Customer::findOrFail($customer->id)->originating_club_id)->toBeNull();

    expect(DomainEvent::query()->where('name', ProfileSuspended::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', OriginatingClubLocked::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(2);
});

it('activates a Profile resting in approved even at exact parity — the transient seat is never counted twice', function () {
    $club = Club::factory()->create();
    ungatedSeatClubTo($club, capacity: 1, occupied: 1);

    // `approved` does NOT occupy a seat, so this Club is at EXACT parity (1 of 1) with the Profile below waiting to
    // be activated — the arrangement in which a gate on this Action would fire. Only a fixture can build it: no
    // Action rests a Profile in `approved` (ApproveProfile gates first, then drives straight through to `active` in
    // the same transaction — MVP-DEC-016), and it is precisely that unreachability which makes the non-gate sound.
    $profile = Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::Approved]);

    expect(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(1)
        ->and(app(ActivateProfile::class)->handle($profile->id)->state)->toBe(ProfileState::Active)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->count())->toBe(1);

    // The seat the caller would have reserved. In production ApproveProfile has already refused (or diverted) before
    // this Action is ever reached, so the ceiling holds; the gate belongs on the seat-CONSUMING caller (design D4).
    expect(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2);
});

it('never reaches the seat ledger — an ungated transition emits no parties_clubs statement at all', function (ProfileState $from, Closure $invoke) {
    $club = Club::factory()->create();

    // Capacity `0` makes `wouldOversell()` true at EVERY occupancy — a Club admitting nobody is full while still
    // empty. So a gate on either Action would refuse here regardless of the from-state's own seat, which is what
    // lets one fixture serve both. The Actions succeed instead, and touch `parties_clubs` zero times.
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => 0]);

    $profile = Profile::factory()->create(['club_id' => $club->id, 'state' => $from]);

    /** @var list<string> $statements */
    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        if (str_contains($query->sql, 'parties_clubs')) {
            $statements[] = $query->sql;
        }
    });

    $invoke($profile->id);

    // A NEGATIVE PIN, and the only shape available: an ungated Action reports no reason, so the claim can only be
    // made about the trace the gate did not leave. It also carries the operational half — on PostgreSQL, a restore
    // that grabbed the Club-row lock would serialise behind every unrelated approval in the same Club.
    expect($statements)->toBeEmpty()
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active);
})->with([
    'ReactivateProfile — suspended → active' => [
        ProfileState::Suspended,
        fn (int $profileId) => app(ReactivateProfile::class)->handle($profileId),
    ],
    'ActivateProfile — approved → active' => [
        ProfileState::Approved,
        fn (int $profileId) => app(ActivateProfile::class)->handle($profileId),
    ],
]);

it('injects neither the seat ledger nor the capacity port into either ungated Action', function () {
    // The constructors are untouched by parties-hero-package: recorder + actor, exactly as they shipped. Passed as
    // `::class` literals rather than through a dataset — PHPStan reads those as `class-string`, a plain `string`
    // parameter it would not.
    $shipped = [DomainEventRecorder::class, ActorContext::class];

    expect(ungatedConstructorTypes(ReactivateProfile::class))->toBe($shipped)
        ->and(ungatedConstructorTypes(ActivateProfile::class))->toBe($shipped);
});

it('imports no capacity symbol into either ungated Action', function () {
    // Closes the third route into the ledger — a `use` statement without an injected dependency. (Task 4.1 widens
    // this to the whole of Module K against `App\Modules\Allocation`.)
    $capacitySymbols = [ClubSeatOccupancy::class, HeroPackageCapacityReader::class];

    expect(ReactivateProfile::class)->not->toUse($capacitySymbols)
        ->and(ActivateProfile::class)->not->toUse($capacitySymbols);
});
