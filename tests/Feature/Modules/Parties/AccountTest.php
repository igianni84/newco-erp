<?php

use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\AccountType;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Account as a billing CONTAINER, not a money ledger (parties-core task 4.1; design D2/D5/D9;
 * party-registry — Requirement: Account — Billing Container). It proves the Account carries NO
 * monetary-balance / credit-ledger column and NO payment-provider reference at creation (§ 4.7, DEC-014), is
 * born `active`/`personal`, belongs to its Customer through a within-module relation, and is event-silent
 * (the co-provisioning behaviour itself is pinned in CustomerTest, which drives the CreateCustomer action).
 *
 * RefreshDatabase: the schema and the factory fixtures both need the migrated tables.
 */
uses(RefreshDatabase::class);

it('carries no monetary-balance or credit-ledger column — the Account is not a money ledger (§ 4.7)', function () {
    // There is no "Account Credit" instrument at NewCo (goodwill is vouchers — Module S; Club Credits live on
    // the Profile — Module K). The container therefore holds NONE of these balance/credit attributes.
    foreach (['balance', 'balance_minor', 'balance_currency', 'credit', 'credit_minor', 'club_credit'] as $column) {
        expect(Schema::hasColumn('parties_accounts', $column))->toBeFalse();
    }

    // It DOES carry a default-currency PREFERENCE string (design D9) — a currency code, never a money amount.
    expect(Schema::hasColumn('parties_accounts', 'default_currency'))->toBeTrue();
});

it('provisions no payment-provider reference at creation (DEC-014 — lazy, out of this slice)', function () {
    // The payment-provider customer reference is created lazily on the first payment-related action, not at
    // Account creation — so no such column exists in this slice.
    foreach (['payment_provider_customer_id', 'payment_provider_reference', 'stripe_customer_id'] as $column) {
        expect(Schema::hasColumn('parties_accounts', $column))->toBeFalse();
    }
});

it('is born active and personal via the factory, under a within-module Customer, with no event', function () {
    $account = Account::factory()->create();

    expect($account->account_type)->toBe(AccountType::Personal)                          // sole launch type (DEC-068)
        ->and($account->status)->toBe(AccountStatus::Active)                             // born active (design D2)
        ->and($account->name)->toBe('Personal')
        ->and($account->default_currency)->toBe('EUR')
        ->and($account->version)->toBe(1)
        ->and(Customer::query()->whereKey($account->customer_id)->exists())->toBeTrue()  // parent Customer built
        ->and(DomainEvent::query()->count())->toBe(0);                                   // event-silent (design D7)
});

it('belongs to its Customer through the within-module relation', function () {
    $customer = Customer::factory()->create();
    $account = Account::factory()->for($customer, 'customer')->create();

    // The within-module belongsTo resolves the owning Customer (relations are allowed within Module K).
    expect(Account::findOrFail($account->id)->customer->is($customer))->toBeTrue();
});
