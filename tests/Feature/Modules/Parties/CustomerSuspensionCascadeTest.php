<?php

use App\Modules\Parties\Actions\PlaceHold;
use App\Modules\Parties\Actions\ReactivateCustomer;
use App\Modules\Parties\Actions\SuspendCustomer;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\CustomerReactivated;
use App\Modules\Parties\Events\CustomerSuspended;
use App\Modules\Parties\Events\ProfileReactivated;
use App\Modules\Parties\Events\ProfileSuspended;
use App\Modules\Parties\Exceptions\IllegalCustomerTransition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Customer suspend/restore cascade (parties-membership-suspension, design L4/L6/L7/L9/L11; party-registry —
 * Requirements: Customer Suspension and Closure, Demand-Side Status Events). It drives the REAL {@see SuspendCustomer}
 * / {@see ReactivateCustomer} Actions and asserts the emergent contract:
 *   - `active → suspended` is the SOLE writer of that transition, records exactly one ROOT {@see CustomerSuspended}
 *     ({customer_id, status}, PII-free), and CASCADES to each `Active` Profile — one {@see ProfileSuspended} per
 *     Profile, each a CAUSATION CHILD of the `CustomerSuspended` root (its `causation_id` = the root's `id`, its
 *     `correlation_id` = the root's, design L11); a non-`Active` (e.g. `Lapsed`) Profile is left untouched;
 *   - the cascade is STATE-PRESERVING (design L9): it writes only `Customer.status` + each cascaded `Profile.state`
 *     and records only the status events — no row is created or destroyed anywhere;
 *   - `suspended → active` records one ROOT {@see CustomerReactivated} and cascade-restores only the Profiles no
 *     longer covered by any active Hold (the coverage-recompute, design L6/L7 — a Profile retaining its own active
 *     Hold stays `Suspended`); each restore is a `ProfileReactivated` causation child;
 *   - each transition is from-state guarded: a suspend from any non-`active` status, or a restore from any
 *     non-`suspended` status, throws {@see IllegalCustomerTransition} before any write (the transaction rolls back —
 *     status, Profiles and the event log unchanged).
 *
 * The state graph is pinned through factories (the sibling ProfileSuspensionTest / ProfileActivationTest convention)
 * so the from-state and the residual-Hold coverage are exact and the event-delta proof is honest (factories record no
 * event — every counted event is one of the Actions'). PlaceHold's coupling (task 4.1) suspends only a scope in its
 * suspendable from-state, so a Hold placed on an already-`Suspended` Profile drives no transition — cleanly setting
 * up residual coverage for the restore.
 * RefreshDatabase per the directory convention; each Action opens its OWN DB::transaction. Events are asserted BY NAME
 * and payloads BY KEY — never a byte-compare of stored jsonb (PG reorders keys; `causation_id` is an uncast column so
 * it reads back as a numeric string on PG while the `id` PK round-trips int — knowledge/testing traps 3 & 6) — so the
 * file holds on PostgreSQL 17.
 */
uses(RefreshDatabase::class);

/**
 * Builds an `active` Customer with one Profile per given state (each in its own Club), via factories so the from-state
 * graph is pinned in isolation and records NO domain event. Returns the Customer and its Profiles in the given order.
 *
 * @param  list<ProfileState>  $states
 * @return array{customer: Customer, profiles: list<Profile>}
 */
function cascadeCustomerWithProfiles(array $states): array
{
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

    $profiles = array_map(
        static fn (ProfileState $state): Profile => Profile::factory()->create([
            'customer_id' => $customer->id,
            'club_id' => Club::factory()->create()->id,
            'state' => $state,
        ]),
        $states,
    );

    return ['customer' => $customer, 'profiles' => $profiles];
}

it('suspends an active Customer, cascades ProfileSuspended to its Active Profiles as causation children, and leaves a Lapsed Profile untouched', function () {
    ['customer' => $customer, 'profiles' => [$activeA, $activeB, $lapsed]] = cascadeCustomerWithProfiles([
        ProfileState::Active, ProfileState::Active, ProfileState::Lapsed,
    ]);

    // Snapshot the world before the suspend — state-preservation (design L9) is the DELTA across this one call.
    $clubsBefore = Club::query()->count();
    $customersBefore = Customer::query()->count();
    $profilesBefore = Profile::query()->count();

    $returned = app(SuspendCustomer::class)->handle($customer->id);

    // The Customer transitions to `suspended` (returned model + the persisted row).
    expect($returned->status)->toBe(CustomerStatus::Suspended)
        ->and(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Suspended);

    // Cascade: the two `Active` Profiles → `Suspended`; the `Lapsed` Profile is untouched (no suspend edge off Lapsed).
    expect(Profile::findOrFail($activeA->id)->state)->toBe(ProfileState::Suspended)
        ->and(Profile::findOrFail($activeB->id)->state)->toBe(ProfileState::Suspended)
        ->and(Profile::findOrFail($lapsed->id)->state)->toBe(ProfileState::Lapsed);

    // State-preserving (design L9): exactly 1 CustomerSuspended (root) + 2 ProfileSuspended (one per cascaded Profile)
    // and no row created or destroyed in any table.
    expect(DomainEvent::query()->count())->toBe(3)
        ->and(DomainEvent::query()->where('name', CustomerSuspended::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileSuspended::NAME)->count())->toBe(2)
        ->and(Club::query()->count())->toBe($clubsBefore)
        ->and(Customer::query()->count())->toBe($customersBefore)
        ->and(Profile::query()->count())->toBe($profilesBefore);

    // The root CustomerSuspended: PII-free {customer_id, status}, ROOT (no parent → correlation is its own event_id).
    $root = DomainEvent::query()->where('name', CustomerSuspended::NAME)->sole();
    expect($root->module)->toBe('parties')
        ->and($root->entity_type)->toBe('Customer')
        ->and($root->entity_id)->toBe((string) $customer->id)
        ->and($root->actor_role)->toBe(ActorRole::System)
        ->and($root->causation_id)->toBeNull()
        ->and($root->correlation_id)->toBe($root->event_id);
    expect(array_keys($root->payload))->toEqualCanonicalizing(['customer_id', 'status']);
    expect($root->payload['customer_id'])->toBe($customer->id)
        ->and($root->payload['status'])->toBe('suspended')
        ->and($root->payload)->not->toHaveKey('name')
        ->and($root->payload)->not->toHaveKey('email');

    // Each cascade ProfileSuspended is a CAUSATION CHILD of the root (design L11): causation_id = the root's id,
    // sharing the root's correlation_id (one causal chain). causation_id is uncast → numeric string on PG, so compare
    // as int against the int PK (trap #6). The two children reference exactly the two Active Profiles.
    $children = DomainEvent::query()->where('name', ProfileSuspended::NAME)->get();
    expect($children)->toHaveCount(2);
    foreach ($children as $child) {
        expect((int) $child->causation_id)->toBe($root->id)
            ->and($child->correlation_id)->toBe($root->correlation_id)
            ->and($child->entity_type)->toBe('Profile')
            ->and($child->actor_role)->toBe(ActorRole::System);
        expect(array_keys($child->payload))->toEqualCanonicalizing(['profile_id', 'state']);
        expect($child->payload['state'])->toBe('suspended');
    }
    expect($children->pluck('entity_id')->all())
        ->toEqualCanonicalizing([(string) $activeA->id, (string) $activeB->id]);
});

it('reactivates a suspended Customer, restoring only the Profiles no longer covered by an active Hold', function () {
    ['customer' => $customer, 'profiles' => [$held, $free]] = cascadeCustomerWithProfiles([
        ProfileState::Active, ProfileState::Active,
    ]);

    // Suspend the Customer → both Profiles cascade to `Suspended`.
    app(SuspendCustomer::class)->handle($customer->id);
    expect(Profile::findOrFail($held->id)->state)->toBe(ProfileState::Suspended)
        ->and(Profile::findOrFail($free->id)->state)->toBe(ProfileState::Suspended);

    // Place a Profile-scope `admin` Hold on ONE of the two suspended Profiles. PlaceHold's coupling (task 4.1)
    // suspends only a scope in its suspendable from-state; this Profile is already `Suspended`, so the placement
    // drives no transition and only establishes residual coverage.
    app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Profile, $held->id, 'residual review');

    $eventsBefore = DomainEvent::query()->count();   // 3 cascade events + 1 CustomerHoldPlaced = 4

    // Restore the Customer (modelling the Customer-scope Hold having been lifted — there is none active here).
    $returned = app(ReactivateCustomer::class)->handle($customer->id);

    expect($returned->status)->toBe(CustomerStatus::Active)
        ->and(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Active);

    // The Profile with no remaining Hold returns to `Active`; the one still carrying its own active Hold stays `Suspended`.
    expect(Profile::findOrFail($free->id)->state)->toBe(ProfileState::Active)
        ->and(Profile::findOrFail($held->id)->state)->toBe(ProfileState::Suspended);

    // Exactly 1 CustomerReactivated (root) + 1 ProfileReactivated (only the uncovered Profile) = 2 new events.
    expect(DomainEvent::query()->count())->toBe($eventsBefore + 2)
        ->and(DomainEvent::query()->where('name', CustomerReactivated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileReactivated::NAME)->count())->toBe(1);

    $root = DomainEvent::query()->where('name', CustomerReactivated::NAME)->sole();
    expect($root->entity_type)->toBe('Customer')
        ->and($root->entity_id)->toBe((string) $customer->id)
        ->and($root->causation_id)->toBeNull()
        ->and($root->correlation_id)->toBe($root->event_id)
        ->and($root->payload['status'])->toBe('active');

    // The lone restore event references the FREE Profile and is a causation child of the CustomerReactivated root.
    $child = DomainEvent::query()->where('name', ProfileReactivated::NAME)->sole();
    expect($child->entity_id)->toBe((string) $free->id)
        ->and((int) $child->causation_id)->toBe($root->id)
        ->and($child->correlation_id)->toBe($root->correlation_id)
        ->and($child->payload['state'])->toBe('active');
});

it('cascades only to the suspended Customer\'s own Profiles, never another Customer\'s', function () {
    ['customer' => $customer] = cascadeCustomerWithProfiles([ProfileState::Active]);

    // A second, unrelated `active` Customer with its own `Active` Profile.
    ['customer' => $other, 'profiles' => [$otherProfile]] = cascadeCustomerWithProfiles([ProfileState::Active]);

    app(SuspendCustomer::class)->handle($customer->id);

    // The other Customer and its Profile are untouched — the cascade is scoped by customer_id (the `profiles()` relation).
    expect(Customer::findOrFail($other->id)->status)->toBe(CustomerStatus::Active)
        ->and(Profile::findOrFail($otherProfile->id)->state)->toBe(ProfileState::Active);

    // The other Customer's Profile is NOT among the cascade children.
    $suspendedProfileIds = DomainEvent::query()->where('name', ProfileSuspended::NAME)->pluck('entity_id')->all();
    expect($suspendedProfileIds)->not->toContain((string) $otherProfile->id);
});

it('rejects suspending a Customer not in active, leaving status and Profiles unchanged with no event', function (CustomerStatus $status) {
    $customer = Customer::factory()->create(['status' => $status]);
    $club = Club::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id, 'club_id' => $club->id, 'state' => ProfileState::Active,
    ]);

    expect(fn () => app(SuspendCustomer::class)->handle($customer->id))
        ->toThrow(IllegalCustomerTransition::class);

    // The from-state guard fires before any write; the transaction rolls back — status, Profile and the log untouched.
    expect(Customer::findOrFail($customer->id)->status)->toBe($status)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'pending' => [CustomerStatus::Pending],       // not yet activated
    'suspended' => [CustomerStatus::Suspended],   // already suspended — no re-suspend
    'closed' => [CustomerStatus::Closed],         // terminal
]);

it('rejects reactivating a Customer not in suspended, leaving status and Profiles unchanged with no event', function (CustomerStatus $status) {
    $customer = Customer::factory()->create(['status' => $status]);
    $club = Club::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id, 'club_id' => $club->id, 'state' => ProfileState::Suspended,
    ]);

    expect(fn () => app(ReactivateCustomer::class)->handle($customer->id))
        ->toThrow(IllegalCustomerTransition::class);

    expect(Customer::findOrFail($customer->id)->status)->toBe($status)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Suspended)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'pending' => [CustomerStatus::Pending],   // never suspended
    'active' => [CustomerStatus::Active],     // already active — restore is suspended-only
    'closed' => [CustomerStatus::Closed],     // terminal
]);
