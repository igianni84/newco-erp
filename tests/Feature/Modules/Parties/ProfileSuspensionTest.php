<?php

use App\Modules\Parties\Actions\ActivateProfile;
use App\Modules\Parties\Actions\ApproveProfile;
use App\Modules\Parties\Actions\CreateProfile;
use App\Modules\Parties\Actions\ReactivateProfile;
use App\Modules\Parties\Actions\SuspendProfile;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileReactivated;
use App\Modules\Parties\Events\ProfileSuspended;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Profile suspend/restore pair (parties-membership-suspension, design L3/L4/L9/L10/L11; party-registry —
 * Requirements: Profile Suspension and Restoration, Demand-Side Status Events). It drives the REAL
 * {@see SuspendProfile} / {@see ReactivateProfile} Actions and asserts the emergent contract:
 *   - `active → suspended` is the SOLE writer of that transition and records exactly one ROOT {@see ProfileSuspended}
 *     ({profile_id, state}, PII-free); `suspended → active` records exactly one ROOT {@see ProfileReactivated} — the
 *     § 15.2 event for the restore edge ONLY (the `lapsed → active` grace records `ProfileRenewed`, design L3);
 *   - suspension is STATE-PRESERVING (design L9; AC-K-FSM-2a): the suspend writes ONLY `Profile.state` — the
 *     DomainEvent delta around the call is exactly one (the `ProfileSuspended` itself) and no other table row is
 *     created or mutated (no voucher/order/reservation/Club Credit exists to touch, and none is fabricated);
 *   - each transition is from-state guarded: a suspend from any non-`active` state, or a restore from any
 *     non-`suspended` state, throws {@see IllegalProfileTransition} before any write and the transaction rolls back
 *     (no state change, no event).
 *
 * The happy-path Profile is driven to `active` through the GENUINE create → approve → activate Actions (not a factory
 * shortcut), so the suspend operates on a legitimately-activated membership and the event-delta proof is honest. The
 * guard / restore cases use the factory to pin a precise from-state in isolation (the sibling ProfileActivationTest
 * convention). RefreshDatabase per the directory convention; each Action opens its OWN DB::transaction, so the
 * recorder's `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper. Events are asserted BY
 * NAME and payloads BY KEY — never a byte-compare of stored jsonb (PG reorders keys — knowledge/testing trap 3) — so
 * the file holds on PostgreSQL 17.
 */
uses(RefreshDatabase::class);

/**
 * Drives a Profile to `active` through the real create → approve → activate Actions (each its own DB::transaction +
 * recorder), exactly as production would — so the suspension under test operates on a genuinely-activated membership.
 */
function createActiveProfile(): Profile
{
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();

    $profile = app(CreateProfile::class)->handle($customer->id, $club->id);   // born `applied`
    app(ApproveProfile::class)->handle($profile->id);                          // `applied → approved`
    app(ActivateProfile::class)->handle($profile->id);                         // `approved → active`

    return Profile::findOrFail($profile->id);
}

it('suspends an active Profile, records exactly one root ProfileSuspended, and writes only the Profile state', function () {
    $profile = createActiveProfile();
    expect($profile->state)->toBe(ProfileState::Active);   // precondition: the setup reached `active`

    // Snapshot the world right before the suspend — the state-preservation proof is the DELTA across this one call.
    $eventsBefore = DomainEvent::query()->count();
    $customersBefore = Customer::query()->count();
    $clubsBefore = Club::query()->count();
    $profilesBefore = Profile::query()->count();

    $returned = app(SuspendProfile::class)->handle($profile->id);

    // The Profile transitions to `suspended` (returned model + the persisted row).
    expect($returned->state)->toBe(ProfileState::Suspended)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Suspended);

    // State-preserving (design L9): the suspend recorded EXACTLY ONE new event (the ProfileSuspended) and mutated no
    // other table — no row created or destroyed anywhere, and the Profile's own non-state columns are untouched.
    expect(DomainEvent::query()->count())->toBe($eventsBefore + 1)
        ->and(DomainEvent::query()->where('name', ProfileSuspended::NAME)->count())->toBe(1)
        ->and(Customer::query()->count())->toBe($customersBefore)
        ->and(Club::query()->count())->toBe($clubsBefore)
        ->and(Profile::query()->count())->toBe($profilesBefore);

    $persisted = Profile::findOrFail($profile->id);
    expect($persisted->customer_id)->toBe($profile->customer_id)
        ->and($persisted->club_id)->toBe($profile->club_id);

    $event = DomainEvent::query()->where('name', ProfileSuspended::NAME)->sole();

    expect($event->module)->toBe('parties')                     // Module::Parties->value
        ->and($event->entity_type)->toBe('Profile')             // the suspension is a Profile-state event
        ->and($event->entity_id)->toBe((string) $profile->id)   // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);     // the ActorContext seam default

    // Payload asserted BY KEY (knowledge/testing trap 3 — never byte-compare PG jsonb): the {profile_id, state} shape,
    // pinned so the PII-free contract cannot silently widen. `state` is the post-transition business enum value.
    expect(array_keys($event->payload))->toEqualCanonicalizing(['profile_id', 'state']);
    expect($event->payload['profile_id'])->toBe($profile->id)
        ->and($event->payload['state'])->toBe('suspended');

    // PII-free: no name/email/phone/DOB leaks into the 10-year audit store.
    expect($event->payload)->not->toHaveKey('name')
        ->and($event->payload)->not->toHaveKey('email');

    // A directly-invoked suspension is a ROOT event: it records no parent in its transaction.
    expect($event->causation_id)->toBeNull()
        ->and($event->correlation_id)->toBe($event->event_id);
});

it('restores a suspended Profile and records exactly one root ProfileReactivated', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Suspended,
    ]);

    $returned = app(ReactivateProfile::class)->handle($profile->id);

    // The Profile transitions back to `active` (returned model + the persisted row).
    expect($returned->state)->toBe(ProfileState::Active)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active);

    // Exactly one domain event total — the factory bypasses the Create*/transition Actions and records nothing, so the
    // only event is the ProfileReactivated from this restore.
    expect(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileReactivated::NAME)->count())->toBe(1);

    $event = DomainEvent::query()->where('name', ProfileReactivated::NAME)->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Profile')
        ->and($event->entity_id)->toBe((string) $profile->id)
        ->and($event->actor_role)->toBe(ActorRole::System);

    expect(array_keys($event->payload))->toEqualCanonicalizing(['profile_id', 'state']);
    expect($event->payload['profile_id'])->toBe($profile->id)
        ->and($event->payload['state'])->toBe('active');   // the post-transition state

    expect($event->payload)->not->toHaveKey('name')
        ->and($event->payload)->not->toHaveKey('email');

    // A directly-invoked restore is a ROOT event.
    expect($event->causation_id)->toBeNull()
        ->and($event->correlation_id)->toBe($event->event_id);
});

it('rejects suspending a Profile not in active, leaving it unchanged with no event', function (ProfileState $state) {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => $club->id, 'state' => $state]);

    expect(fn () => app(SuspendProfile::class)->handle($profile->id))
        ->toThrow(IllegalProfileTransition::class);

    // The from-state guard fires before any write and the transaction rolls back: the state is unchanged and no
    // event was recorded.
    expect(Profile::findOrFail($profile->id)->state)->toBe($state)
        ->and(DomainEvent::query()->where('name', ProfileSuspended::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'applied' => [ProfileState::Applied],         // not yet activated
    'suspended' => [ProfileState::Suspended],     // already suspended — no re-suspend
    'lapsed' => [ProfileState::Lapsed],           // a different non-active edge
    'cancelled' => [ProfileState::Cancelled],     // terminal soft-delete
]);

it('rejects reactivating a Profile not in suspended, leaving it unchanged with no event', function (ProfileState $state) {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => $club->id, 'state' => $state]);

    expect(fn () => app(ReactivateProfile::class)->handle($profile->id))
        ->toThrow(IllegalProfileTransition::class);

    expect(Profile::findOrFail($profile->id)->state)->toBe($state)
        ->and(DomainEvent::query()->where('name', ProfileReactivated::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'active' => [ProfileState::Active],            // already active — `ProfileReactivated` is restore-only
    'applied' => [ProfileState::Applied],          // never suspended
    'lapsed' => [ProfileState::Lapsed],            // the grace edge is RenewProfile, not this Action (design L3)
    'cancelled' => [ProfileState::Cancelled],      // terminal soft-delete
]);
