<?php

use App\Modules\Parties\Actions\LiftHold;
use App\Modules\Parties\Actions\PlaceHold;
use App\Modules\Parties\Actions\RequireKyc;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Events\CustomerHoldPlaced;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the unified Hold REGISTRY behaviour and the demand-side SCOPE GUARD (parties-holds, design L1/L3;
 * party-registry — Requirements: Hold Registry, and the MODIFIED Requirement: Birth States Recorded — Lifecycle
 * Transitions Deferred). Where {@see HoldLifecycleTest} pins one place/lift in isolation, this file pins the
 * emergent registry contract of the slice as a whole:
 *
 *   - a scope MAY carry MULTIPLE concurrent `active` Holds, and lifting one leaves the others active — the scope is
 *     not single-Hold (§ 4.8.1 / § 14.8 BR-K-Hold-1 / AC-K-BR-Hold-1; any one Hold blocks independently);
 *   - the registry is TRIGGER-AGNOSTIC with a manual-placement path for every one of the six types, on every one
 *     of the three scopes (§ 4.8 / § 4.8.1 / AC-K-MVP-2) — recorded identically regardless of type or scope;
 *   - the SCOPE GUARD under the Hold→`suspended` coupling (task 4.1): opening KYC (a real compliance transition)
 *     moves the KYC FSM but NEVER the status FSM, and placing a Hold on a NON-suspendable birth-state scope (a
 *     `pending` Customer, an `applied` Profile) records the Hold and drives NO status transition; a Hold on the
 *     `active`-born Account, however, drives it `active → suspended` (audit-only — § 15 names no Account event). No
 *     demand-side status event (`CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked` /
 *     `CustomerSegmentChanged`) is recorded across the sequence (§ 10.1 / AC-K-FSM-9).
 *
 * This complements {@see SpineCreationChainTest}, which pins the demand-side guard for the creation slice and stays
 * green unamended (this file adds the Hold-registry dimension of the same guard). The factories are pure fixtures
 * (no domain event), so the only events recorded here are the Hold events the Actions write — which is what lets
 * the "no demand-side status event" assertions be exact.
 *
 * RefreshDatabase per the directory convention; each Action opens its OWN transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper. Cross-engine close on
 * PostgreSQL 17 in task 6.3.
 */
uses(RefreshDatabase::class);

/**
 * The `->value` tokens of the active Holds on a Customer scope, for order-insensitive set assertions.
 *
 * @return array<int, string>
 */
function activeHoldTypesOnCustomer(int $customerId): array
{
    return Hold::query()
        ->where('scope_type', HoldScope::Customer->value)
        ->where('scope_id', $customerId)
        ->where('status', HoldStatus::Active->value)
        ->get()
        ->map(fn (Hold $hold): string => $hold->hold_type->value)
        ->all();
}

it('records multiple concurrent active Holds on one scope and lifting one leaves the others active', function () {
    // Two concurrent Holds on ONE Customer scope: a `kyc` Hold (auto-placed by the KYC coupling via RequireKyc)
    // AND an `admin` Hold (the manual operator path). The scope is NOT single-Hold (§ 4.8.1 / BR-K-Hold-1).
    $customer = Customer::factory()->create();

    app(RequireKyc::class)->handle($customer->id);
    $admin = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Customer, $customer->id, 'manual review');

    // Both recorded `active` concurrently on the same scope.
    expect(activeHoldTypesOnCustomer($customer->id))->toEqualCanonicalizing([
        HoldType::Kyc->value,
        HoldType::Admin->value,
    ]);

    // Lifting the `admin` Hold (operator-liftable) leaves the `kyc` Hold active — lifting one does not lift the
    // others (any one Hold blocks independently — BR-K-Hold-1).
    app(LiftHold::class)->handle($admin->id, 'review cleared');

    expect(activeHoldTypesOnCustomer($customer->id))->toEqualCanonicalizing([HoldType::Kyc->value])
        ->and(Hold::findOrFail($admin->id)->status)->toBe(HoldStatus::Lifted);
});

it('places a Hold of each of the six types via the manual operator path', function (HoldType $type) {
    // The registry is trigger-agnostic: every one of the six types is placeable through the manual operator path
    // and recorded identically — no automatic upstream trigger is required for the record to exist (AC-K-MVP-2).
    $customer = Customer::factory()->create();

    $hold = app(PlaceHold::class)->handle($type, HoldScope::Customer, $customer->id);

    $fresh = Hold::findOrFail($hold->id);
    expect($fresh->hold_type)->toBe($type)
        ->and($fresh->status)->toBe(HoldStatus::Active)
        ->and($fresh->scope_type)->toBe(HoldScope::Customer)
        ->and($fresh->scope_id)->toBe($customer->id);
})->with([
    'admin' => HoldType::Admin,
    'kyc' => HoldType::Kyc,
    'payment' => HoldType::Payment,
    'fraud' => HoldType::Fraud,
    'compliance' => HoldType::Compliance,
    'credit' => HoldType::Credit,
]);

it('places a Hold on each of the three scopes — customer, account and profile', function () {
    // The registry's polymorphic scope spans three tables (Customer/Account/Profile — § 4.8). Real scoped entities
    // give realistic scope_ids; the registry records every scope_type identically (PlaceHold does not resolve the
    // scope — design L1 risk note — so the manual path is genuinely scope-uniform).
    $customer = Customer::factory()->create();
    $account = Account::factory()->create(['customer_id' => $customer->id]);
    $profile = Profile::factory()->create(['customer_id' => $customer->id]);

    $onCustomer = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Customer, $customer->id);
    $onAccount = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Account, $account->id);
    $onProfile = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Profile, $profile->id);

    expect(Hold::findOrFail($onCustomer->id)->scope_type)->toBe(HoldScope::Customer)
        ->and(Hold::findOrFail($onCustomer->id)->scope_id)->toBe($customer->id)
        ->and(Hold::findOrFail($onAccount->id)->scope_type)->toBe(HoldScope::Account)
        ->and(Hold::findOrFail($onAccount->id)->scope_id)->toBe($account->id)
        ->and(Hold::findOrFail($onProfile->id)->scope_type)->toBe(HoldScope::Profile)
        ->and(Hold::findOrFail($onProfile->id)->scope_id)->toBe($profile->id);

    // All three scopes carry an active Hold — every scope_type recorded.
    expect(Hold::query()->where('status', HoldStatus::Active->value)->count())->toBe(3);
});

it('places/lifts Holds: a pending Customer and applied Profile stay put while an active Account suspends (audit-only), recording no demand-side status event', function () {
    // Three real scope entities in their BIRTH states: Customer `pending`, Account `active`, Profile `applied`.
    $customer = Customer::factory()->create();
    $account = Account::factory()->create(['customer_id' => $customer->id]);
    $profile = Profile::factory()->create(['customer_id' => $customer->id]);

    // Exercise the full place/lift surface across all three scopes, plus the KYC coupling (a real compliance
    // transition): RequireKyc auto-places the `kyc` Hold AND moves kyc_status → pending, yet must NOT move the
    // Customer STATUS (the KYC FSM is separate from the status FSM — § 9.1). The Fraud Hold lands on the
    // `active`-born Account, so the coupling (task 4.1) drives it `active → suspended` (audit-only). Then lift one
    // to exercise the lift path.
    app(RequireKyc::class)->handle($customer->id);
    $adminOnCustomer = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Customer, $customer->id, 'review');
    app(PlaceHold::class)->handle(HoldType::Fraud, HoldScope::Account, $account->id);
    app(PlaceHold::class)->handle(HoldType::Compliance, HoldScope::Profile, $profile->id);
    app(LiftHold::class)->handle($adminOnCustomer->id, 'cleared');

    // The Holds were really placed/lifted (so the guard below is not vacuous): 4 placed + 1 lifted.
    expect(DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->count())->toBe(4)
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(1);

    // SCOPE GUARD under the coupling (task 4.1): a Hold on a NON-suspendable birth-state scope drives no transition —
    // the Customer is still `pending` (the admin/kyc Holds suspend nothing off `pending`) and the Profile still
    // `applied` — while the `active`-born Account, covered by the Fraud Hold, is now `suspended` (audit-only).
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending)
        ->and(Account::findOrFail($account->id)->status)->toBe(AccountStatus::Suspended)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Applied);

    // NO demand-side status event is recordable, even though the Account was suspended: the Account suspension is
    // AUDIT-ONLY (§ 15 names no Account event), none of the deferred demand-side names appears, and the ONLY events
    // recorded across the whole sequence are the Hold events (the coupling adds no event for the audit-only Account).
    expect(DomainEvent::query()->whereIn('name', [
        'CustomerActivated', 'ProfileActivated', 'OriginatingClubLocked', 'CustomerSegmentChanged',
    ])->count())->toBe(0)
        ->and(DomainEvent::query()->whereNotIn('name', [CustomerHoldPlaced::NAME, CustomerHoldLifted::NAME])->count())->toBe(0);
});
