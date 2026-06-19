<?php

use App\Modules\Parties\Actions\LiftHold;
use App\Modules\Parties\Actions\PlaceHold;
use App\Modules\Parties\Actions\RecordKycVerified;
use App\Modules\Parties\Actions\RequireKyc;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Events\CustomerReactivated;
use App\Modules\Parties\Events\ProfileReactivated;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the LIFT side of the Hold→`suspended` coupling (parties-membership-suspension task 4.2, design L6/L10/L11;
 * party-registry — Requirement: Hold-Driven Status Coupling; ADR 2026-06-19). After {@see LiftHold} (operator) and
 * the system `kyc`-lift in {@see RecordKycVerified} lift a Hold, they RESTORE every covered scope currently
 * `suspended` to `active`/`Active` by INVOKING the matching `Reactivate*` Action in the SAME transaction — but ONLY
 * iff re-querying coverage shows no OTHER active Hold still covers that scope (BR-K-Hold-1: many Holds coexist;
 * restore only when the LAST covering Hold is gone). The just-lifted Hold is already `lifted` before the re-query, so
 * it never counts as its own residual coverage. A lift off a scope that is not `suspended` (a Hold on a `pending`
 * Customer / `Applied` Profile that never suspended) records the lift and drives NO transition — the from-state
 * pre-check that keeps the status FSM independent of the KYC/sanctions FSMs.
 *
 * The PLACE side is pinned in {@see HoldStatusCouplingPlaceTest}; this file is its lifting counterpart. Fixtures are
 * pinned through factories (which record NO domain event), so every event observed after a Hold call is one the
 * Hold/Suspend/Reactivate Actions wrote — the event-count and causation proofs are honest. RefreshDatabase per the
 * directory convention; each Action opens its OWN transaction, so a lift → Reactivate* nests a savepoint under the
 * lift transaction (the restore commits or rolls back atomically with the lift). Events are asserted BY NAME + counts
 * and `causation_id` via the `(int)` cast (it is an uncast column → a numeric string on PG; the `id` PK round-trips
 * int — knowledge/testing traps 3 & 6), so the file holds on PostgreSQL 17.
 */
uses(RefreshDatabase::class);

/**
 * Builds an `active` Customer with one Profile per given state (each in its own Club), via factories so the
 * from-state graph is pinned in isolation and records NO domain event. Returns the Customer and its Profiles in the
 * given order. Uniquely named (the sibling place/cascade tests own their helpers; Pest helpers share one global
 * namespace, so each file declares its own — knowledge/testing).
 *
 * @param  list<ProfileState>  $states
 * @return array{customer: Customer, profiles: list<Profile>}
 */
function liftCouplingActiveCustomerWithProfiles(array $states): array
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

it('lifts the single covering Customer Hold and restores the Customer, cascade-restoring its Profiles', function () {
    ['customer' => $customer, 'profiles' => [$profileA, $profileB]] = liftCouplingActiveCustomerWithProfiles([
        ProfileState::Active, ProfileState::Active,
    ]);

    // Place a Customer Hold → suspends the Customer and cascade-suspends both Active Profiles (the place coupling, 4.1).
    $hold = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Customer, $customer->id, 'manual review');
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Suspended)
        ->and(Profile::findOrFail($profileA->id)->state)->toBe(ProfileState::Suspended)
        ->and(Profile::findOrFail($profileB->id)->state)->toBe(ProfileState::Suspended);

    $before = DomainEvent::query()->count();

    // Lift the only covering Hold → no residual coverage → restore the Customer + cascade-restore both Profiles.
    app(LiftHold::class)->handle($hold->id, 'review cleared');

    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Active)
        ->and(Profile::findOrFail($profileA->id)->state)->toBe(ProfileState::Active)
        ->and(Profile::findOrFail($profileB->id)->state)->toBe(ProfileState::Active);

    // The lift records CustomerHoldLifted (1) + the restore's CustomerReactivated (root) + 2 ProfileReactivated
    // (cascade children) = 4 new events, all in the one lift transaction.
    expect(DomainEvent::query()->count() - $before)->toBe(4)
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerReactivated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileReactivated::NAME)->count())->toBe(2);

    // The cascade ProfileReactivated rows are causation children of the same-transaction CustomerReactivated root.
    $root = DomainEvent::query()->where('name', CustomerReactivated::NAME)->sole();
    DomainEvent::query()->where('name', ProfileReactivated::NAME)->get()
        ->each(function (DomainEvent $child) use ($root): void {
            expect((int) $child->causation_id)->toBe($root->id)
                ->and($child->correlation_id)->toBe($root->correlation_id);
        });
});

it('keeps a Customer suspended on an earlier lift with coverage remaining, restoring only on the last covering Hold', function () {
    ['customer' => $customer, 'profiles' => [$profile]] = liftCouplingActiveCustomerWithProfiles([ProfileState::Active]);

    // Two concurrent Customer Holds: the first suspends (cascade); the second finds the Customer already `suspended`
    // and drives no second transition (BR-K-Hold-1 — many Holds coexist, the place coupling's from-state pre-check).
    $first = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Customer, $customer->id, 'review one');
    $second = app(PlaceHold::class)->handle(HoldType::Fraud, HoldScope::Customer, $customer->id, 'review two');
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Suspended)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Suspended);

    // Lift the FIRST Hold → the second still covers the Customer → it stays `suspended` (no restore event).
    app(LiftHold::class)->handle($first->id, 'one cleared');
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Suspended)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Suspended)
        ->and(DomainEvent::query()->where('name', CustomerReactivated::NAME)->count())->toBe(0);

    // Lift the LAST covering Hold → no residual coverage → restore the Customer + cascade-restore the Profile.
    app(LiftHold::class)->handle($second->id, 'two cleared');
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Active)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active)
        ->and(DomainEvent::query()->where('name', CustomerReactivated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileReactivated::NAME)->count())->toBe(1);
});

it('lifts a Profile Hold and restores the Profile with a root ProfileReactivated', function () {
    $profile = Profile::factory()->create(['state' => ProfileState::Active]);

    // Place then lift a Profile-scope Hold: isolates (BR-K-Hold-4) — only the Profile transitions, its Customer untouched.
    $hold = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Profile, $profile->id);
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Suspended);

    $before = DomainEvent::query()->count();

    app(LiftHold::class)->handle($hold->id, 'review cleared');

    // Restored to Active with exactly one ProfileReactivated — a ROOT (directly via ReactivateProfile, no Customer
    // cascade above it, so no causation parent).
    $reactivation = DomainEvent::query()->where('name', ProfileReactivated::NAME)->sole();
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active)
        ->and(DomainEvent::query()->count() - $before)->toBe(2)   // CustomerHoldLifted + ProfileReactivated
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(1)
        ->and($reactivation->causation_id)->toBeNull();
});

it('keeps a Profile suspended while its Customer Hold remains, restoring it when the last covering Hold is lifted', function () {
    // The headline scenario (§ Hold-Driven Status Coupling): a Profile under TWO covering Holds — its own and a
    // cascading Customer-scope Hold.
    ['customer' => $customer, 'profiles' => [$profile]] = liftCouplingActiveCustomerWithProfiles([ProfileState::Active]);

    // Place the Profile Hold first (Profile → Suspended, root), then a Customer Hold (Customer → suspended; its cascade
    // skips the already-Suspended Profile). The Profile is now covered by BOTH Holds.
    $profileHold = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Profile, $profile->id);
    $customerHold = app(PlaceHold::class)->handle(HoldType::Fraud, HoldScope::Customer, $customer->id, 'review');
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Suspended)
        ->and(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Suspended);

    // Lift the Profile-scope Hold → the cascading Customer Hold STILL covers the Profile → it stays `Suspended`.
    app(LiftHold::class)->handle($profileHold->id, 'profile hold cleared');
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Suspended)
        ->and(DomainEvent::query()->where('name', ProfileReactivated::NAME)->count())->toBe(0);

    // Lift the Customer-scope Hold (the last covering Hold) → the Customer restores and cascade-restores the now
    // uncovered Profile → `Active` with exactly one ProfileReactivated (a cascade child of CustomerReactivated).
    app(LiftHold::class)->handle($customerHold->id, 'customer hold cleared');
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active)
        ->and(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Active);

    $root = DomainEvent::query()->where('name', CustomerReactivated::NAME)->sole();
    $child = DomainEvent::query()->where('name', ProfileReactivated::NAME)->sole();
    expect((int) $child->causation_id)->toBe($root->id)
        ->and($child->correlation_id)->toBe($root->correlation_id);
});

it('lifts an Account Hold and restores the Account event-silently', function () {
    $account = Account::factory()->create();   // born `active`

    // Place → suspends the Account (audit-only — § 15 names no Account event).
    $hold = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Account, $account->id);
    expect(Account::findOrFail($account->id)->status)->toBe(AccountStatus::Suspended);

    $before = DomainEvent::query()->count();

    // Lift → no residual coverage → restore the Account `suspended → active`, recording NO Account event.
    app(LiftHold::class)->handle($hold->id, 'review cleared');

    expect(Account::findOrFail($account->id)->status)->toBe(AccountStatus::Active)
        ->and(DomainEvent::query()->count() - $before)->toBe(1)   // ONLY CustomerHoldLifted (restore is audit-only)
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('entity_type', 'Account')->count())->toBe(0);
});

it('keeps an Account suspended on an earlier lift with coverage remaining, restoring only on the last Account Hold', function () {
    $account = Account::factory()->create();

    // Two concurrent Account Holds; the first suspends, the second is a no-op (already suspended).
    $first = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Account, $account->id);
    $second = app(PlaceHold::class)->handle(HoldType::Fraud, HoldScope::Account, $account->id);
    expect(Account::findOrFail($account->id)->status)->toBe(AccountStatus::Suspended);

    // Lift one → the other still covers → stays `suspended`.
    app(LiftHold::class)->handle($first->id);
    expect(Account::findOrFail($account->id)->status)->toBe(AccountStatus::Suspended);

    // Lift the last → restored.
    app(LiftHold::class)->handle($second->id);
    expect(Account::findOrFail($account->id)->status)->toBe(AccountStatus::Active);
});

it('restores the Customer via RecordKycVerified after a post-activation kyc re-screen', function () {
    // The system `kyc`-lift restore path (design L6): an `active`, un-screened Customer (kyc NULL) with an Active
    // Profile. RequireKyc opens KYC and auto-places the `kyc` Customer Hold → the place coupling suspends the active
    // Customer (cascade). RecordKycVerified lifts the `kyc` Hold and restores the now-uncovered Customer.
    ['customer' => $customer, 'profiles' => [$profile]] = liftCouplingActiveCustomerWithProfiles([ProfileState::Active]);

    app(RequireKyc::class)->handle($customer->id);
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Suspended)
        ->and(Customer::findOrFail($customer->id)->kyc_status)->toBe(KycStatus::Pending)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Suspended);

    app(RecordKycVerified::class)->handle($customer->id);

    // The system lift restored the Customer (no other Hold covers it) and cascade-restored the Profile.
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Active)
        ->and(Customer::findOrFail($customer->id)->kyc_status)->toBe(KycStatus::Verified)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active)
        ->and(DomainEvent::query()->where('name', CustomerReactivated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileReactivated::NAME)->count())->toBe(1);
});

it('records the lift but drives no restore when the lifted Hold never suspended its scope', function () {
    // The from-state pre-check on the LIFT side: a Hold placed on a `pending` Customer never suspended it (the place
    // coupling's pre-check, 4.1), so lifting it restores nothing — the Customer stays `pending`, no restore event.
    $customer = Customer::factory()->create();   // born `pending` — never suspendable

    $hold = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Customer, $customer->id, 'review');
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending);

    $before = DomainEvent::query()->count();

    app(LiftHold::class)->handle($hold->id, 'cleared');

    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending)
        ->and(DomainEvent::query()->count() - $before)->toBe(1)   // ONLY CustomerHoldLifted — no restore
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerReactivated::NAME)->count())->toBe(0);
});
