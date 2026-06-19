<?php

use App\Modules\Parties\Actions\PlaceHold;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\CustomerHoldPlaced;
use App\Modules\Parties\Events\CustomerSuspended;
use App\Modules\Parties\Events\ProfileSuspended;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the PLACE side of the Hold→`suspended` coupling (parties-membership-suspension task 4.1, design L6/L10;
 * party-registry — Requirement: Hold-Driven Status Coupling; ADR 2026-06-19). After {@see PlaceHold} appends the
 * Hold and records `CustomerHoldPlaced`, it drives the covered scope to `suspended` IFF that scope is currently in
 * its suspendable from-state — by INVOKING the matching explicit Action (`customer ⇒ SuspendCustomer` cascading,
 * `account ⇒ SuspendAccount`, `profile ⇒ SuspendProfile`) in the SAME transaction. A Hold whose covered scope is
 * NOT in its suspendable from-state (the `kyc` Hold auto-placed on a `pending` Customer at onboarding, a Hold on an
 * `Applied` Profile) records the Hold and drives NO transition — the from-state pre-check keeps the status FSM
 * independent of the KYC/sanctions FSMs (the shipped `ComplianceIndependenceTest`).
 *
 * The fixtures are pinned through factories (the sibling cascade/suspension convention) so each from-state is exact
 * and the event-delta proof is honest — factories record NO domain event, so every event observed after a PlaceHold
 * call is one the Hold/Suspend Actions wrote. RefreshDatabase per the directory convention; each Action opens its
 * OWN transaction, so PlaceHold → Suspend* nests a savepoint under the placement transaction (the coupling commits
 * or rolls back atomically with the Hold). Events are asserted BY NAME + counts and `causation_id` via the `(int)`
 * cast (it is an uncast column → a numeric string on PG; the `id` PK round-trips int — knowledge/testing traps 3 &
 * 6), so the file holds on PostgreSQL 17. The LIFT side is pinned in HoldStatusCouplingLiftTest (task 4.2).
 */
uses(RefreshDatabase::class);

/**
 * Builds an `active` Customer with one Profile per given state (each in its own Club), via factories so the
 * from-state graph is pinned in isolation and records NO domain event. Returns the Customer and its Profiles in the
 * given order. Uniquely named (the sibling cascade test owns `cascadeCustomerWithProfiles`; Pest helpers share one
 * global namespace, so each file declares its own — knowledge/testing).
 *
 * @param  list<ProfileState>  $states
 * @return array{customer: Customer, profiles: list<Profile>}
 */
function couplingActiveCustomerWithProfiles(array $states): array
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

it('places a Customer Hold on an active Customer and suspends it, cascading ProfileSuspended to its Active Profiles', function () {
    ['customer' => $customer, 'profiles' => [$activeA, $activeB, $lapsed]] = couplingActiveCustomerWithProfiles([
        ProfileState::Active, ProfileState::Active, ProfileState::Lapsed,
    ]);

    $before = DomainEvent::query()->count();   // 0 — factory-built

    $hold = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Customer, $customer->id, 'manual review');

    // The Hold itself is recorded `active` on the Customer scope.
    expect($hold->scope_type)->toBe(HoldScope::Customer)
        ->and($hold->scope_id)->toBe($customer->id);

    // The coupling drove the active Customer to `suspended` and cascaded to its two `Active` Profiles; the `Lapsed`
    // Profile has no suspend edge and is left untouched.
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Suspended)
        ->and(Profile::findOrFail($activeA->id)->state)->toBe(ProfileState::Suspended)
        ->and(Profile::findOrFail($activeB->id)->state)->toBe(ProfileState::Suspended)
        ->and(Profile::findOrFail($lapsed->id)->state)->toBe(ProfileState::Lapsed);

    // Exactly 1 CustomerHoldPlaced + 1 CustomerSuspended (root) + 2 ProfileSuspended (cascade) = 4 new events, all
    // in the one placement transaction.
    expect(DomainEvent::query()->count() - $before)->toBe(4)
        ->and(DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerSuspended::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileSuspended::NAME)->count())->toBe(2);

    // The cascade ProfileSuspended rows are causation children of the same-transaction CustomerSuspended root.
    $root = DomainEvent::query()->where('name', CustomerSuspended::NAME)->sole();
    DomainEvent::query()->where('name', ProfileSuspended::NAME)->get()
        ->each(function (DomainEvent $child) use ($root): void {
            expect((int) $child->causation_id)->toBe($root->id)
                ->and($child->correlation_id)->toBe($root->correlation_id);
        });
});

it('places an Account Hold on an active Account and suspends it, recording no Account event', function () {
    $account = Account::factory()->create();   // born `active`

    $before = DomainEvent::query()->count();

    app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Account, $account->id);

    // The Account transitioned `active → suspended` (audit-only).
    expect(Account::findOrFail($account->id)->status)->toBe(AccountStatus::Suspended);

    // ONLY CustomerHoldPlaced is recorded — the Account suspension is audit-only (§ 15 names no Account event).
    expect(DomainEvent::query()->count() - $before)->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('entity_type', 'Account')->count())->toBe(0);
});

it('places a Profile Hold on an Active Profile and suspends it, recording a root ProfileSuspended', function () {
    $profile = Profile::factory()->create(['state' => ProfileState::Active]);

    $before = DomainEvent::query()->count();

    app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Profile, $profile->id);

    // Profile-scope Holds isolate (BR-K-Hold-4) — only the Profile transitions; its owning Customer is untouched.
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Suspended);

    // 1 CustomerHoldPlaced + 1 ProfileSuspended (a ROOT — directly the Profile-scope Hold's suspension, no Customer
    // cascade above it, so no causation parent).
    $suspension = DomainEvent::query()->where('name', ProfileSuspended::NAME)->sole();
    expect(DomainEvent::query()->count() - $before)->toBe(2)
        ->and(DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->count())->toBe(1)
        ->and($suspension->causation_id)->toBeNull();
});

it('records the kyc Hold on a pending Customer but drives no transition', function () {
    $customer = Customer::factory()->create();   // born `pending` — not suspendable

    $before = DomainEvent::query()->count();

    app(PlaceHold::class)->handle(HoldType::Kyc, HoldScope::Customer, $customer->id);

    // The from-state pre-check: a `pending` Customer is not in the suspendable from-state, so the Hold is recorded
    // and NO suspension fires (onboarding KYC never suspends — the status FSM stays independent of the KYC FSM).
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending)
        ->and(DomainEvent::query()->count() - $before)->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerSuspended::NAME)->count())->toBe(0);
});

it('records a Hold on an Applied Profile but drives no transition', function () {
    $profile = Profile::factory()->create();   // born `applied` — not suspendable

    $before = DomainEvent::query()->count();

    app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Profile, $profile->id);

    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Applied)
        ->and(DomainEvent::query()->count() - $before)->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileSuspended::NAME)->count())->toBe(0);
});

it('placing a Hold on an already-suspended scope drives no second transition', function () {
    // From-state guard the other way: a scope NOT in its suspendable from-state because it is ALREADY suspended.
    $account = Account::factory()->create(['status' => AccountStatus::Suspended]);

    $before = DomainEvent::query()->count();

    app(PlaceHold::class)->handle(HoldType::Fraud, HoldScope::Account, $account->id);

    // The Hold is recorded; the Account stays `suspended` (no re-suspension), and only CustomerHoldPlaced fires.
    expect(Account::findOrFail($account->id)->status)->toBe(AccountStatus::Suspended)
        ->and(DomainEvent::query()->count() - $before)->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->count())->toBe(1);
});
