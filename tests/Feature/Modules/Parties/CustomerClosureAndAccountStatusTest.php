<?php

use App\Modules\Parties\Actions\CloseAccount;
use App\Modules\Parties\Actions\CloseCustomer;
use App\Modules\Parties\Actions\ReactivateAccount;
use App\Modules\Parties\Actions\SuspendAccount;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\CustomerClosed;
use App\Modules\Parties\Events\ProfileSuspended;
use App\Modules\Parties\Exceptions\IllegalAccountTransition;
use App\Modules\Parties\Exceptions\IllegalCustomerTransition;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Customer terminal closure + the whole Account status FSM (parties-membership-suspension, design L4/L7/L8/L10;
 * party-registry — Requirements: Customer Suspension and Closure, Account Status Lifecycle, Demand-Side Status Events).
 * It drives the REAL {@see CloseCustomer} / {@see SuspendAccount} / {@see ReactivateAccount} / {@see CloseAccount}
 * Actions and asserts the emergent contract:
 *   - {@see CloseCustomer} (`active | suspended → closed`) is the SOLE writer of that transition, records exactly one
 *     ROOT {@see CustomerClosed} ({customer_id, status}, PII-free), and — unlike {@see SuspendCustomer} — does NOT
 *     cascade to the Customer's Profiles (§ 15.1 names no cascade for closure — design L7): a Profile is left exactly
 *     as it was, and no Profile event is recorded;
 *   - the Account FSM `active → suspended → active → closed` flips `Account.status` through {@see SuspendAccount} /
 *     {@see ReactivateAccount} / {@see CloseAccount} and records ZERO domain events (audit-only — § 15 names no
 *     Account-family event, design L8; the `CancelProfile` no-recorder shape — proven by an event-count delta of 0);
 *   - there is NO {@see ActivateAccount} Action — the Account is born `active` (AC-K-FSM-9; design L8), so its only
 *     `→ active` edge is the restore {@see ReactivateAccount};
 *   - each transition is from-state guarded: a wrong-from-state Customer closure throws {@see IllegalCustomerTransition},
 *     and a wrong-from-state Account transition throws {@see IllegalAccountTransition}, both before any write (the
 *     transaction rolls back — status and the event log unchanged).
 *
 * The state graph is pinned through factories (the sibling CustomerSuspensionCascadeTest / ProfileSuspensionTest
 * convention) so the from-state is exact and the event-delta proof is honest (factories record no event — every
 * counted event is one of the Actions'; the Customer/Account factories also co-provision nothing and run no
 * duplicate-email pre-check). RefreshDatabase per the directory convention; each Action opens its OWN DB::transaction,
 * so the recorder's `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper. Events are
 * asserted BY NAME and payloads BY KEY — never a byte-compare of stored jsonb (PG reorders keys; the `id` PK
 * round-trips int while an uncast envelope column reads back as a numeric string on PG — knowledge/testing traps 3 &
 * 6) — so the file holds on PostgreSQL 17.
 */
uses(RefreshDatabase::class);

it('closes a suspended Customer, records one root CustomerClosed, and does NOT cascade to its Profiles', function () {
    // A `suspended` Customer carrying an `Active` Profile — an artificial state-pin (closure is reachable from
    // `suspended`) whose point is to prove closure transitions NO Profile regardless of the Profile's state.
    $customer = Customer::factory()->create(['status' => CustomerStatus::Suspended]);
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => Club::factory()->create()->id,
        'state' => ProfileState::Active,
    ]);

    // Snapshot the world before the close — no row is created or destroyed by a closure.
    $clubsBefore = Club::query()->count();
    $customersBefore = Customer::query()->count();
    $profilesBefore = Profile::query()->count();

    $returned = app(CloseCustomer::class)->handle($customer->id);

    // The Customer transitions to `closed` (returned model + the persisted row).
    expect($returned->status)->toBe(CustomerStatus::Closed)
        ->and(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Closed);

    // NO cascade (design L7): the Profile is untouched — still `Active`, no Profile transition.
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active);

    // Exactly 1 CustomerClosed and nothing else — no ProfileSuspended, no event carrying a Profile entity type, and
    // no row created or destroyed in any table.
    expect(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerClosed::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileSuspended::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->where('entity_type', 'Profile')->count())->toBe(0)
        ->and(Club::query()->count())->toBe($clubsBefore)
        ->and(Customer::query()->count())->toBe($customersBefore)
        ->and(Profile::query()->count())->toBe($profilesBefore);

    // The root CustomerClosed: PII-free {customer_id, status}, ROOT (no parent → correlation is its own event_id).
    $root = DomainEvent::query()->where('name', CustomerClosed::NAME)->sole();
    expect($root->module)->toBe('parties')
        ->and($root->entity_type)->toBe('Customer')
        ->and($root->entity_id)->toBe((string) $customer->id)
        ->and($root->actor_role)->toBe(ActorRole::System)
        ->and($root->causation_id)->toBeNull()
        ->and($root->correlation_id)->toBe($root->event_id);
    expect(array_keys($root->payload))->toEqualCanonicalizing(['customer_id', 'status']);
    expect($root->payload['customer_id'])->toBe($customer->id)
        ->and($root->payload['status'])->toBe('closed')
        ->and($root->payload)->not->toHaveKey('name')
        ->and($root->payload)->not->toHaveKey('email');
});

it('closes an active Customer too — closure is reachable from active', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

    $returned = app(CloseCustomer::class)->handle($customer->id);

    expect($returned->status)->toBe(CustomerStatus::Closed)
        ->and(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Closed)
        ->and(DomainEvent::query()->where('name', CustomerClosed::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->count())->toBe(1);
});

it('rejects closing a Customer not in active or suspended, leaving status and the log unchanged', function (CustomerStatus $status) {
    $customer = Customer::factory()->create(['status' => $status]);

    expect(fn () => app(CloseCustomer::class)->handle($customer->id))
        ->toThrow(IllegalCustomerTransition::class);

    // The from-state guard fires before any write; the transaction rolls back — status and the log untouched.
    expect(Customer::findOrFail($customer->id)->status)->toBe($status)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'pending' => [CustomerStatus::Pending],   // not yet activated — closure is active|suspended only
    'closed' => [CustomerStatus::Closed],     // already terminal — no re-close
]);

it('walks an Account active → suspended → active → closed, event-silently', function () {
    $account = Account::factory()->create(['status' => AccountStatus::Active]);
    expect(DomainEvent::query()->count())->toBe(0);   // the factory records nothing — the delta is the Actions'

    // active → suspended
    expect(app(SuspendAccount::class)->handle($account->id)->status)->toBe(AccountStatus::Suspended)
        ->and(Account::findOrFail($account->id)->status)->toBe(AccountStatus::Suspended);

    // suspended → active (the Account's ONLY `→ active` edge — there is no ActivateAccount)
    expect(app(ReactivateAccount::class)->handle($account->id)->status)->toBe(AccountStatus::Active)
        ->and(Account::findOrFail($account->id)->status)->toBe(AccountStatus::Active);

    // active → closed (terminal)
    expect(app(CloseAccount::class)->handle($account->id)->status)->toBe(AccountStatus::Closed)
        ->and(Account::findOrFail($account->id)->status)->toBe(AccountStatus::Closed);

    // AUDIT-ONLY (design L8): the whole walk records ZERO domain events — § 15 names no Account-family event.
    expect(DomainEvent::query()->count())->toBe(0)
        ->and(DomainEvent::query()->where('entity_type', 'Account')->count())->toBe(0);
});

it('closes a suspended Account (closure is reachable from suspended too), event-silently', function () {
    $account = Account::factory()->create(['status' => AccountStatus::Suspended]);

    expect(app(CloseAccount::class)->handle($account->id)->status)->toBe(AccountStatus::Closed)
        ->and(Account::findOrFail($account->id)->status)->toBe(AccountStatus::Closed)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('has NO ActivateAccount Action — the Account is born active (its only → active edge is ReactivateAccount)', function () {
    // Reflect the Parties Actions namespace the way the guard tests do: a flat class file per Action under Actions/.
    $actions = array_map(
        static fn (string $file): string => basename($file, '.php'),
        glob(app_path('Modules/Parties/Actions/*.php')) ?: [],
    );

    expect($actions)->not->toBeEmpty()                                              // the walk must have run
        ->and($actions)->toContain('ReactivateAccount')                            // the restore edge DOES exist
        ->and($actions)->not->toContain('ActivateAccount')                         // ...but no activation edge
        ->and(class_exists('App\\Modules\\Parties\\Actions\\ActivateAccount'))->toBeFalse();
});

it('rejects suspending an Account not in active, leaving status unchanged with no event', function (AccountStatus $status) {
    $account = Account::factory()->create(['status' => $status]);

    expect(fn () => app(SuspendAccount::class)->handle($account->id))
        ->toThrow(IllegalAccountTransition::class);

    expect(Account::findOrFail($account->id)->status)->toBe($status)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'suspended' => [AccountStatus::Suspended],   // already suspended — no re-suspend
    'closed' => [AccountStatus::Closed],         // terminal
]);

it('rejects reactivating an Account not in suspended, leaving status unchanged with no event', function (AccountStatus $status) {
    $account = Account::factory()->create(['status' => $status]);

    expect(fn () => app(ReactivateAccount::class)->handle($account->id))
        ->toThrow(IllegalAccountTransition::class);

    expect(Account::findOrFail($account->id)->status)->toBe($status)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'active' => [AccountStatus::Active],    // already active — restore is suspended-only
    'closed' => [AccountStatus::Closed],    // terminal
]);

it('rejects closing an Account already closed, leaving status unchanged with no event', function () {
    $account = Account::factory()->create(['status' => AccountStatus::Closed]);

    expect(fn () => app(CloseAccount::class)->handle($account->id))
        ->toThrow(IllegalAccountTransition::class);

    expect(Account::findOrFail($account->id)->status)->toBe(AccountStatus::Closed)
        ->and(DomainEvent::query()->count())->toBe(0);
});
