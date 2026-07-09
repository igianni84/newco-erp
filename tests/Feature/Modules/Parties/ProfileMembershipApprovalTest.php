<?php

use App\Modules\Parties\Actions\ApproveProfile;
use App\Modules\Parties\Actions\CreateProfile;
use App\Modules\Parties\Actions\DeclineProfile;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\OriginatingClubLocked;
use App\Modules\Parties\Events\ProfileActivated;
use App\Modules\Parties\Events\ProfileCreated;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Profile membership approve/decline pair and the Originating-Club one-shot lock
 * (parties-membership-activation; design L2/L3/L4/L7/L8; party-registry — Requirements: Profile Membership
 * Approval, Demand-Side Activation Events). It drives the REAL Actions and asserts the emergent contract:
 *   - {@see ApproveProfile} drives `applied → approved → active` ATOMICALLY in one transaction (canon MVP-DEC-016 —
 *     approve = charge = activation; `approved` is a TRANSIENT pass-through, never durably rested-in) and, on the
 *     Customer's FIRST-EVER approval across any Club, sets `Customer.originating_club_id` to the approving Club and
 *     records a ROOT {@see OriginatingClubLocked}; the approve WRITE itself is audit-only (§ 15.2 names no
 *     `ProfileApproved` / `ProfileRejected` — design L2), so the events are that lock plus the {@see ProfileActivated}
 *     the internal activation records;
 *   - the lock is ONE-SHOT + IMMUTABLE (design L3): a second Club's approval still transitions the Profile but
 *     neither re-sets the link nor re-fires the event (the NULL-gate idempotency);
 *   - {@see DeclineProfile} transitions `applied → rejected` and is EVENT-SILENT (records nothing — it mirrors
 *     `RecordKycRejected`); `rejected` is terminal-for-this-application, so a fresh re-application on the same
 *     (Customer, Club) pair via {@see CreateProfile} inserts cleanly (the partial unique index excludes `rejected`);
 *   - both transitions are from-state guarded: a call from a state outside `{applied, waiting_list}` throws
 *     {@see IllegalProfileTransition} before any write and the transaction rolls back (no state change, no lock,
 *     no event).
 *
 * Since parties-hero-package (tasks 2.2 / 2.3) BOTH verbs are also reachable from `waiting_list` — the waitlist's
 * two exits are these same two Actions. This file pins the `applied` legs and the Originating-Club lock, which the
 * capacity gate leaves untouched; the `waiting_list` legs are pinned in `ProfileApprovalCapacityGateTest` (the
 * conversion and its at-parity rejection) and `ProfileWaitlistDeclineTest` (the decline), as is the EXHAUSTIVE
 * seven-state complement of `{applied, waiting_list}` for each verb. The reject datasets below are a three-state
 * subset of that complement — a cheap floor kept beside the happy paths they bracket, never the whole proof.
 *
 * RefreshDatabase per the directory convention; each Action opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper (the event being recorded at all
 * is proof of the in-transaction wiring). Events are asserted BY NAME and the payload BY KEY — never a byte-compare
 * of stored jsonb (PG reorders keys — knowledge/testing trap 3) — so the file holds on PostgreSQL 17.
 */
uses(RefreshDatabase::class);

it('approves+activates a first-ever applied Profile atomically, locks the Originating Club, and records OriginatingClubLocked + ProfileActivated (no ProfileApproved)', function () {
    $customer = Customer::factory()->create();   // born `pending`, originating_club_id NULL
    expect($customer->originating_club_id)->toBeNull();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Applied,
    ]);

    $returned = app(ApproveProfile::class)->handle($profile->id);

    // Approve = charge = activation is ATOMIC (canon MVP-DEC-016): the Profile lands `active` in one transaction
    // (returned model + the persisted row); `approved` is a TRANSIENT pass-through, never durably rested-in.
    expect($returned->state)->toBe(ProfileState::Active)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active);

    // The Originating-Club link is locked to the approving Club. `originating_club_id` is an uncast bigint FK, so
    // assert with loose `toEqual` — it reads back as a numeric string on PG, a PHP int on SQLite (testing trap 6).
    expect(Customer::findOrFail($customer->id)->originating_club_id)->toEqual($club->id);

    // Exactly TWO domain events total — the factories bypass the Create* actions and record nothing; the approve
    // WRITE is audit-only (no ProfileApproved — § 15.2), so the two events are the OriginatingClubLocked from the
    // first-ever lock and the ProfileActivated the internal atomic activation records.
    expect(DomainEvent::query()->count())->toBe(2)
        ->and(DomainEvent::query()->where('name', 'ProfileApproved')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(1);

    $event = DomainEvent::query()->where('name', OriginatingClubLocked::NAME)->sole();

    expect($event->module)->toBe('parties')                      // Module::Parties->value
        ->and($event->entity_type)->toBe('Customer')             // the lock is a Customer-state event
        ->and($event->entity_id)->toBe((string) $customer->id)   // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);      // the ActorContext seam default

    // Payload asserted BY KEY (knowledge/testing trap 3 — never byte-compare PG jsonb): the § 6.1 four-key shape,
    // pinned so the PII-free contract cannot silently widen.
    expect(array_keys($event->payload))->toEqualCanonicalizing(['customer_id', 'club_id', 'profile_id', 'locked_at']);
    expect($event->payload['customer_id'])->toBe($customer->id)
        ->and($event->payload['club_id'])->toBe($club->id)        // the locking Club is THIS Profile's Club (design L3)
        ->and($event->payload['profile_id'])->toBe($profile->id)
        ->and($event->payload['locked_at'])->toBeString();        // an ISO-8601 moment — there is no locked_at column

    // PII-free: no name/email/phone/DOB leaks into the 10-year audit store.
    expect($event->payload)->not->toHaveKey('name')
        ->and($event->payload)->not->toHaveKey('email');

    // The lock is a ROOT event: the approval records no Profile event to be its parent.
    expect($event->causation_id)->toBeNull()
        ->and($event->correlation_id)->toBe($event->event_id);

    // The internal atomic activation records ProfileActivated as its OWN root too — the recorder threads only what is
    // explicitly passed, so invoking ActivateProfile inside the approval transaction does NOT parent it to the lock:
    // BOTH events of the atomic approve are roots (the demand-side activation events are a flat root set — design L5).
    $activated = DomainEvent::query()->where('name', ProfileActivated::NAME)->sole();
    expect($activated->entity_type)->toBe('Profile')                 // the activation is a Profile-state event
        ->and($activated->entity_id)->toBe((string) $profile->id)    // envelope entity_id is a string
        ->and($activated->payload['state'])->toBe('active')          // the post-transition business enum value
        ->and($activated->causation_id)->toBeNull()
        ->and($activated->correlation_id)->toBe($activated->event_id);
});

it('does not re-lock or re-fire on a second Club approval — the Originating-Club lock is one-shot and immutable', function () {
    $customer = Customer::factory()->create();
    $clubA = Club::factory()->create();
    $clubB = Club::factory()->create();
    $profileA = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => $clubA->id, 'state' => ProfileState::Applied]);
    $profileB = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => $clubB->id, 'state' => ProfileState::Applied]);

    // First-ever approval → locks the OC to clubA and records the single OriginatingClubLocked. `originating_club_id`
    // is an uncast bigint FK → loose `toEqual` (numeric string on PG, int on SQLite — testing trap 6).
    app(ApproveProfile::class)->handle($profileA->id);
    expect(Customer::findOrFail($customer->id)->originating_club_id)->toEqual($clubA->id)
        ->and(DomainEvent::query()->where('name', OriginatingClubLocked::NAME)->count())->toBe(1);

    // A second Club's approval still activates profileB atomically (approve = charge = activation)...
    app(ApproveProfile::class)->handle($profileB->id);

    expect(Profile::findOrFail($profileB->id)->state)->toBe(ProfileState::Active)
        // ...but the Originating-Club link is unchanged (still clubA — immutable, one-shot)...
        ->and(Customer::findOrFail($customer->id)->originating_club_id)->toEqual($clubA->id)
        // ...and no second OriginatingClubLocked is recorded — the count stays 1 (each approval activates, so the two
        // approvals record two ProfileActivated; with the single lock the run's total is three events).
        ->and(DomainEvent::query()->where('name', OriginatingClubLocked::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(2)
        ->and(DomainEvent::query()->count())->toBe(3);
});

it('declines an applied Profile to rejected, records no event and locks no Club, and admits a fresh re-application on the same pair', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => $club->id, 'state' => ProfileState::Applied]);

    $returned = app(DeclineProfile::class)->handle($profile->id);

    // The Profile transitions to `rejected` (terminal-for-this-application).
    expect($returned->state)->toBe(ProfileState::Rejected)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Rejected);

    // Decline is event-silent (§ 15.2 names no ProfileRejected; design L2) — and it locks no Originating Club.
    expect(DomainEvent::query()->count())->toBe(0)
        ->and(Customer::findOrFail($customer->id)->originating_club_id)->toBeNull();

    // Re-application on the SAME (Customer, Club) pair inserts cleanly: `rejected` is excluded from the partial
    // unique index, so CreateProfile's pre-check + the index both admit a fresh `applied` Profile (rejected
    // Profiles are not reused — § 4.2.1). CreateProfile records its own ProfileCreated.
    $reapplied = app(CreateProfile::class)->handle($customer->id, $club->id);

    expect($reapplied->state)->toBe(ProfileState::Applied)
        ->and($reapplied->id)->not->toBe($profile->id)               // a new row, not a resurrection
        ->and(DomainEvent::query()->where('name', ProfileCreated::NAME)->count())->toBe(1);
});

it('rejects approving a Profile outside {applied, waiting_list}, leaving it unchanged with no event and no lock', function (ProfileState $state) {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => $club->id, 'state' => $state]);

    expect(fn () => app(ApproveProfile::class)->handle($profile->id))
        ->toThrow(IllegalProfileTransition::class);

    // The from-state guard fires before any write and the transaction rolls back: the state is unchanged, the
    // Originating-Club link stays NULL, and no event was recorded.
    expect(Profile::findOrFail($profile->id)->state)->toBe($state)
        ->and(Customer::findOrFail($customer->id)->originating_club_id)->toBeNull()
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'approved' => [ProfileState::Approved],
    'active' => [ProfileState::Active],
    'rejected' => [ProfileState::Rejected],
]);

it('rejects declining a Profile outside {applied, waiting_list}, leaving it unchanged with no event', function (ProfileState $state) {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => $club->id, 'state' => $state]);

    expect(fn () => app(DeclineProfile::class)->handle($profile->id))
        ->toThrow(IllegalProfileTransition::class);

    expect(Profile::findOrFail($profile->id)->state)->toBe($state)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'approved' => [ProfileState::Approved],
    'active' => [ProfileState::Active],
    'rejected' => [ProfileState::Rejected],
]);
