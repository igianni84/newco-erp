<?php

use App\Modules\Parties\Actions\CreateProfile;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileCreated;
use App\Modules\Parties\Events\WaitingListJoined;
use App\Modules\Parties\Exceptions\ClubNotAcceptingMemberships;
use App\Modules\Parties\Exceptions\DuplicateProfileForClub;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pins `CreateProfile`'s BIRTH-STATE ROUTING (parties-hero-package task 2.1, design D6/D7; canon § 7.1 step 6 —
 * *"each application creates a Profile in `Applied` state (or `WaitingList` if the target Club is at capacity)"*;
 * ADR 2026-07-09-hero-package-capacity-seat-set-and-waitinglist).
 *
 * Four claims:
 *   1. THE GATE ROUTES, IT NEVER REJECTS. An applicant for a full Club is born `waiting_list` and admitted onto the
 *      waitlist — `ProfileCreated` AND `WaitingListJoined`, both in the write's transaction. A free seat, or an
 *      uncapped Club (the shipped default), births `applied` and records `ProfileCreated` alone.
 *   2. THE CLUB-STATUS GUARD IS EVALUATED STRICTLY FIRST. A `sunset` Club at capacity REJECTS the application; it
 *      never waitlists it. Waitlisting for a Club that will never admit anyone is a lie to the customer.
 *   3. IT TAKES NO CLUB-ROW LOCK (D6). Neither `Applied` nor `WaitingList` occupies a seat, so this gate
 *      structurally cannot oversell and the lock would serialise every application in a Club for no invariant gain.
 *      The sole enforcement point of the no-oversell invariant is the seat-CONSUMING approve instant (task 2.2).
 *   4. `waiting_list` IS NON-TERMINAL, so a waitlisted Profile blocks a second live one for the pair exactly as
 *      `applied` does — the partial unique index excludes only the three terminal tokens, and needs no migration.
 *
 * Capacity is set per-test via `config()->set(...)`, never via the environment: the shipped test default is unset
 * ⇒ `null` ⇒ uncapped, which is what keeps every pre-existing Parties test running against unchanged behaviour.
 *
 * RefreshDatabase per the directory convention; the action opens its own `DB::transaction`, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper. Event payloads are asserted BY
 * KEY, never as a byte-compare of stored JSON (PostgreSQL's `jsonb` reorders keys).
 */
uses(RefreshDatabase::class);

/** Cap $club at $capacity seats and seat $occupied `Active` members in it, each under its own Customer. */
function clubAtCapacity(Club $club, int $capacity, int $occupied): void
{
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => $capacity]);

    Profile::factory()->count($occupied)->create([
        'club_id' => $club->id,
        'state' => ProfileState::Active,
    ]);
}

it('births a Profile in waiting_list when the target Club is at capacity, recording both events', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    clubAtCapacity($club, capacity: 2, occupied: 2);

    $profile = app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id);

    // The applicant is ADMITTED — onto the waitlist, not turned away. No exception, a real row.
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::WaitingList);

    // Both events, exactly once each, in the same transaction as the write (design D7: `WaitingList` has two entry
    // points — this birth and ApproveProfile's divert — and the event is recorded at BOTH).
    $created = DomainEvent::query()->where('name', ProfileCreated::NAME)->sole();
    $joined = DomainEvent::query()->where('name', WaitingListJoined::NAME)->sole();

    // ProfileCreated reads the birth state off the row rather than hardcoding `applied`.
    expect($created->payload['state'])->toBe('waiting_list');

    expect($joined->module)->toBe('parties')                     // Module::Parties->value
        ->and($joined->entity_type)->toBe('Profile')
        ->and($joined->entity_id)->toBe((string) $profile->id)   // envelope entity_id is a string
        ->and($joined->actor_role)->toBe(ActorRole::System);     // the ActorContext seam default

    // PII-free, parties by id only — pinned by the EXACT key set so the payload cannot silently widen.
    expect(array_keys($joined->payload))->toEqualCanonicalizing(['profile_id', 'customer_id', 'club_id', 'state']);

    expect($joined->payload['profile_id'])->toBe($profile->id)
        ->and($joined->payload['customer_id'])->toBe($customer->id)
        ->and($joined->payload['club_id'])->toBe($club->id)
        ->and($joined->payload['state'])->toBe('waiting_list');
});

it('births a Profile in applied when the target Club still has a free seat, recording ProfileCreated alone', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    clubAtCapacity($club, capacity: 2, occupied: 1);

    $profile = app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id);

    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Applied)
        ->and(DomainEvent::query()->where('name', ProfileCreated::NAME)->sole()->payload['state'])->toBe('applied')
        // A Profile born `applied` records ProfileCreated alone — no waitlist confirmation reaches HubSpot.
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0);
});

it('births a Profile in applied in an uncapped Club however many members it already seats', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();

    // The shipped launch posture (a dark launch): no PARTIES_HERO_PACKAGE_CAPACITY in the test environment, so the
    // routing collapses to the historical `applied` birth and no pre-existing caller is moved.
    expect(config('parties.hero_package.capacity.default'))->toBeNull();

    Profile::factory()->count(5)->create(['club_id' => $club->id, 'state' => ProfileState::Active]);

    $profile = app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id);

    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Applied)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0);
});

it('waitlists at a capacity of zero — a Club admitting nobody is full while still empty', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    clubAtCapacity($club, capacity: 0, occupied: 0);

    // `0` is a real capacity, never confused with an absent one (`null` ⇒ uncapped). The `>=` comparison in
    // `wouldOversell()` is what makes an empty Club oversell on its very first seat.
    expect(app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id)->state)
        ->toBe(ProfileState::WaitingList);
});

it('counts a Suspended member as occupying a seat when it routes the birth state', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => 2]);

    // The seat set is `Active` + `Suspended` (ClubSeatOccupancy::OCCUPYING_STATES). A suspension is a temporary
    // restriction, not a departure — the seat was never freed, so the Club is at parity and the applicant waits.
    Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::Active]);
    Profile::factory()->create(['club_id' => $club->id, 'state' => ProfileState::Suspended]);

    expect(app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id)->state)
        ->toBe(ProfileState::WaitingList);
});

it('routes per Club — a full Club waitlists while its uncapped neighbour still admits', function () {
    $customer = Customer::factory()->create();
    $full = Club::factory()->create();
    $neighbour = Club::factory()->create();

    clubAtCapacity($full, capacity: 1, occupied: 1);
    Profile::factory()->count(9)->create(['club_id' => $neighbour->id, 'state' => ProfileState::Active]);

    // One Customer, two Clubs, two birth states — the multi-profile model (canon MVP-DEC-012) crossed with the
    // per-Club capacity override. The neighbour answers to the (unset ⇒ uncapped) global default.
    expect(app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $full->id)->state)
        ->toBe(ProfileState::WaitingList)
        ->and(app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $neighbour->id)->state)
        ->toBe(ProfileState::Applied);
});

it('rejects an application to a non-active Club that is also at capacity — the status guard runs first', function (string $status) {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create(['status' => ClubStatus::from($status)]);
    clubAtCapacity($club, capacity: 1, occupied: 1);

    // BR-K-Club-3 / AC-K-FSM-6: a `sunset` Club blocks new memberships and `closed` is terminal. Were the capacity
    // read evaluated first, this applicant would be born `waiting_list` for a Club that will never admit anyone.
    expect(fn () => app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id))
        ->toThrow(ClubNotAcceptingMemberships::class);

    // Nothing was created and nothing was recorded — the throw rolled the transaction back. The single Profile is
    // the seeded `Active` member; no `waiting_list` row exists anywhere.
    expect(Profile::query()->where('state', ProfileState::WaitingList->value)->count())->toBe(0)
        ->and(Profile::query()->where('customer_id', $customer->id)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with(['sunset', 'closed']);   // the two non-active Club states — both block a new membership

it('blocks a duplicate for the pair once a Profile is born waiting_list (it is non-terminal)', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    clubAtCapacity($club, capacity: 1, occupied: 1);

    $waitlisted = app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id);
    expect($waitlisted->state)->toBe(ProfileState::WaitingList);

    // `waiting_list` is NOT one of the three terminal tokens the partial unique index excludes, so it blocks a
    // second live Profile for the pair exactly as `applied` does — no index migration was needed for this change.
    expect(fn () => app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id))
        ->toThrow(DuplicateProfileForClub::class);

    // The rejected creation persisted nothing: one Profile for the pair, one WaitingListJoined, one ProfileCreated.
    expect(Profile::query()->where('customer_id', $customer->id)->where('club_id', $club->id)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileCreated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(1);
});

it('inherits auto_renew from the Club even when the Profile is born waitlisted', function (bool $clubDefault) {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create(['auto_renew_default' => $clubDefault]);
    clubAtCapacity($club, capacity: 1, occupied: 1);

    // Profile-5 (canon MVP-DEC-022) is orthogonal to the birth state: the inheritance reuses the `$club` the
    // Club-active guard already fetched, and the routing gate never touches it.
    $profile = app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id);

    $read = Profile::findOrFail($profile->id);

    expect($read->state)->toBe(ProfileState::WaitingList)
        ->and($read->auto_renew)->toBe($clubDefault);
})->with([true, false]);

it('takes no Club-row lock while routing the birth state (design D6)', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    clubAtCapacity($club, capacity: 1, occupied: 1);

    /** @var list<string> $statements */
    $statements = [];

    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id);

    // A seat-consuming transition serialises same-Club transactions on the `parties_clubs` row; this one must not.
    // Meaningful on PostgreSQL (where `lockForUpdate()` compiles `for update`); vacuously true on SQLite, whose
    // grammar compiles no lock clause at all — which is exactly why the claim is ALSO pinned structurally below.
    expect($statements)->not->toBeEmpty();

    foreach ($statements as $sql) {
        expect($sql)->not->toContain('for update');
    }

    // The cross-engine pin: the action calls the seat ledger's LOCK-FREE count, and never its locking sibling.
    // `lockAndCountOccupiedSeats()` is reserved for the seat-CONSUMING callers (tasks 2.2 / 2.4 / 3.2).
    $source = (string) file_get_contents(app_path('Modules/Parties/Actions/CreateProfile.php'));

    expect($source)->toContain('countOccupiedSeats(')
        ->and($source)->not->toContain('lockAndCountOccupiedSeats(');
});
