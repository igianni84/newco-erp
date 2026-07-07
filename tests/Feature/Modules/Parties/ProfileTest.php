<?php

use App\Modules\Parties\Actions\CreateProfile;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileCreated;
use App\Modules\Parties\Exceptions\ClubNotAcceptingMemberships;
use App\Modules\Parties\Exceptions\DuplicateProfileForClub;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pins the Profile — the membership in one Club (parties-core task 5.1; design D2/D3/D4/D8; party-registry —
 * Requirement: Profile — Multi-Profile Membership, Birth States Recorded, Spine Creation Events). It proves
 * CreateProfile persists the Profile in `applied` referencing exactly one Customer + one Club, records
 * ProfileCreated through the platform recorder in the SAME transaction (PII-free, parties by id), supports the
 * MULTI-PROFILE model (a Customer across many Clubs), enforces the one-per-(Customer,Club) non-terminal
 * uniqueness (BR-K-Identity-2) at BOTH layers — the app pre-check AND the DB partial unique index — and holds
 * the scope guard (no transition out of `applied`, no lifecycle event).
 *
 * RefreshDatabase: the action opens its OWN DB::transaction, so the recorder's `transactionLevel() === 0` guard
 * is satisfied by the savepoint even under the wrapper. The DB-index probe is itself savepoint-wrapped
 * (DB::transaction, testing-rule #5) so PostgreSQL's transaction-abort on the unique violation stays isolated
 * and the row-state check after the throw is valid on both engines. Portability: the event payload is asserted
 * BY KEY (never a byte-compare of stored JSON — PG jsonb reorders keys, trap 3), and the PII-free contract is
 * pinned by the EXACT key set so it cannot silently widen.
 */
uses(RefreshDatabase::class);

it('creates a Profile in applied referencing one Customer and one Club', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();

    $profile = app(CreateProfile::class)->handle(
        customerId: $customer->id,
        clubId: $club->id,
    );

    // Re-fetch so the assertions exercise the read/hydration casts, not the in-memory create() values.
    $read = Profile::findOrFail($profile->id);

    expect($read->customer_id)->toBe($customer->id)
        ->and($read->club_id)->toBe($club->id)
        ->and($read->state)->toBe(ProfileState::Applied)         // born applied (design D2)
        ->and($read->tier)->toBeNull()                           // single-tier launch (DEC-062)
        ->and($read->role)->toBeNull()
        ->and($read->invited_by_customer_id)->toBeNull()         // no inviter by default
        ->and($read->version)->toBe(1);                          // version floor, born at 1

    // Both required references resolve through the within-module belongsTo (relations allowed within Module K).
    expect($read->customer->is($customer))->toBeTrue()
        ->and($read->club->is($club))->toBeTrue();
});

it('persists the optional tier, role and inviter when supplied', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $inviter = Customer::factory()->create();

    $profile = app(CreateProfile::class)->handle(
        customerId: $customer->id,
        clubId: $club->id,
        tier: 'founding',
        role: 'primary',
        invitedByCustomerId: $inviter->id,
    );

    $read = Profile::findOrFail($profile->id);

    expect($read->tier)->toBe('founding')
        ->and($read->role)->toBe('primary')
        ->and($read->invited_by_customer_id)->toBe($inviter->id);   // the referral seam captured by id
});

it('lets a Customer hold Profiles across three different Clubs (the multi-profile model)', function () {
    // BR-K-Identity-2 caps Profiles per (Customer, Club) pair, NOT per Customer — a Customer may hold many
    // Profiles across DIFFERENT Clubs (DEC-012 multi-profile). Three Clubs → three Profiles, all born applied.
    $customer = Customer::factory()->create();
    $clubs = Club::factory()->count(3)->create();

    foreach ($clubs as $club) {
        app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id);
    }

    expect(Profile::query()->where('customer_id', $customer->id)->count())->toBe(3)
        ->and(Profile::query()->where('customer_id', $customer->id)->where('state', ProfileState::Applied->value)->count())->toBe(3)
        ->and(DomainEvent::query()->where('name', ProfileCreated::NAME)->count())->toBe(3);
});

it('rejects a second non-terminal Profile for the same (Customer, Club) pair via the app pre-check (BR-K-Identity-2)', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();

    app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id);

    // A second creation for the SAME live (Customer, Club) pair is rejected by the localized pre-check ahead of
    // the partial unique index.
    expect(fn () => app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id))
        ->toThrow(DuplicateProfileForClub::class);

    // The rejected creation persisted nothing — still exactly one Profile and one ProfileCreated for the pair.
    expect(Profile::query()->where('customer_id', $customer->id)->where('club_id', $club->id)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileCreated::NAME)->count())->toBe(1);
});

it('enforces the non-terminal uniqueness at the database via the partial unique index (both engines)', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();

    // One live (non-terminal) Profile via the action.
    app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id);

    // A RAW duplicate insert (bypassing the app pre-check) for the same (customer, club) in a non-terminal state
    // must be rejected by the partial unique index — the structural backstop on BOTH engines (the index is
    // created unconditionally, unlike the PG-only state CHECK). The insert is wrapped in DB::transaction (a
    // SAVEPOINT under RefreshDatabase's wrapper, testing-rule #5) so PostgreSQL's transaction-abort stays
    // isolated and the count check after the throw is valid.
    expect(fn () => DB::transaction(fn () => DB::table('parties_profiles')->insert([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Applied->value,
        'created_at' => now(),
        'updated_at' => now(),
    ])))->toThrow(QueryException::class);

    // The duplicate did not land — still exactly one live Profile for the pair.
    expect(Profile::query()->where('customer_id', $customer->id)->where('club_id', $club->id)->count())->toBe(1);
});

it('rejects a CreateProfile against a non-active Club with no Profile and no event (RM-21 / BR-K-Club-3)', function (string $state) {
    // BR-K-Club-3 / AC-K-FSM-6 (RM-21): a `sunset` Club blocks new memberships (§ 4.3) and `closed` is terminal —
    // the target Club MUST be `active`. A CreateProfile against a non-active Club is rejected with a localized
    // ClubNotAcceptingMemberships BEFORE the write, so no Profile row and no ProfileCreated event are created (the
    // throw rolls back the transaction). The active-Club ADMIT path is covered by every other test in this file —
    // all create against the default-`active` ClubFactory.
    $customer = Customer::factory()->create();
    $club = Club::factory()->create(['status' => ClubStatus::from($state)]);

    expect(fn () => app(CreateProfile::class)->handle(
        customerId: $customer->id,
        clubId: $club->id,
    ))->toThrow(ClubNotAcceptingMemberships::class);

    expect(Profile::query()->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', ProfileCreated::NAME)->count())->toBe(0);
})->with(['sunset', 'closed']);   // the two non-active Club states — both block a new membership

it('inherits auto_renew from the target Club auto_renew_default at creation (Profile-5)', function (bool $clubDefault) {
    // Profile-5 (canon MVP-DEC-022): a new Profile's `auto_renew` DEFAULT-INHERITS the owning Club's
    // `auto_renew_default` at creation — the `auto_renew` element of the (otherwise deferred) `renewal_policy`
    // config, shipped standalone here. CreateProfile reads it from the SAME Club the Club-active guard fetches.
    $customer = Customer::factory()->create();
    $club = Club::factory()->create(['auto_renew_default' => $clubDefault]);

    $profile = app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id);

    // Re-fetch so the assertion exercises the read/hydration boolean cast, not the in-memory create() value.
    expect(Profile::findOrFail($profile->id)->auto_renew)->toBe($clubDefault);
})->with([
    'Club default true → Profile auto_renew true' => [true],
    'Club default false → Profile auto_renew false' => [false],
]);

it('records a PII-free ProfileCreated domain event in the same transaction, tagged parties', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $inviter = Customer::factory()->create();

    $profile = app(CreateProfile::class)->handle(
        customerId: $customer->id,
        clubId: $club->id,
        tier: 'founding',
        role: 'primary',
        invitedByCustomerId: $inviter->id,
    );

    // sole() asserts EXACTLY one ProfileCreated row exists — the one-event contract.
    $event = DomainEvent::query()->where('name', ProfileCreated::NAME)->sole();

    expect($event->module)->toBe('parties')                      // Module::Parties->value
        ->and($event->entity_type)->toBe('Profile')
        ->and($event->entity_id)->toBe((string) $profile->id)    // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);      // the ActorContext seam default

    // The exact key set is pinned so the PII-free contract cannot silently widen; the Profile is a membership
    // join holding NO personal data — all parties (Customer, Club, inviter) are referenced BY ID.
    expect(array_keys($event->payload))->toEqualCanonicalizing([
        'profile_id', 'customer_id', 'club_id', 'state', 'tier', 'role', 'invited_by_customer_id',
    ]);

    // Payload asserted BY KEY (trap 3): structural identity + non-PII business fields only.
    expect($event->payload['profile_id'])->toBe($profile->id)
        ->and($event->payload['customer_id'])->toBe($customer->id)
        ->and($event->payload['club_id'])->toBe($club->id)
        ->and($event->payload['state'])->toBe('applied')
        ->and($event->payload['tier'])->toBe('founding')
        ->and($event->payload['role'])->toBe('primary')
        ->and($event->payload['invited_by_customer_id'])->toBe($inviter->id);   // inviter by id (not PII)
});

it('records no lifecycle-transition event — the Profile stays applied (scope guard)', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();

    $profile = app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id);

    // Design D2 scope guard: only the *Created event exists — never a *Activated/*Approved/*Suspended (the
    // deferred parties-membership-lifecycle change owns the nine-state transitions), and the Profile stays applied.
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Approved%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Suspended%')->count())->toBe(0)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Applied);
});

it('produces an applied Profile via the factory without recording an event', function () {
    // The factory is a pure fixture: it bypasses the action (and its duplicate pre-check), so it persists an
    // applied Profile under within-module parent Customer + Club but records no ProfileCreated.
    $profile = Profile::factory()->create();

    expect($profile->state)->toBe(ProfileState::Applied)
        ->and($profile->version)->toBe(1)
        ->and($profile->tier)->toBeNull()
        ->and(Customer::query()->whereKey($profile->customer_id)->exists())->toBeTrue()   // parent Customer built
        ->and(Club::query()->whereKey($profile->club_id)->exists())->toBeTrue()           // parent Club built
        ->and(DomainEvent::query()->count())->toBe(0);
});
