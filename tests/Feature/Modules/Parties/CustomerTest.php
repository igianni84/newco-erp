<?php

use App\Modules\Parties\Actions\CreateCustomer;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\AccountType;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Parties\Events\CustomerCreated;
use App\Modules\Parties\Exceptions\DuplicateCustomerEmail;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\I18n\SupportedLocale;
use App\Platform\Money\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Customer + Account — NewCo's natural-person registry and its co-provisioned 1:1 billing container
 * (parties-core task 4.1; design D1/D2/D3/D5/D6/D7; party-registry — Requirements: Customer Identity, Account —
 * Billing Container, Birth States Recorded, Spine Creation Events). It proves CreateCustomer, in ONE
 * transaction, persists the Customer in `pending` carrying the immutable `customer` marker and a NULL
 * Originating Club, co-provisions exactly one `active`/`personal` Account, rejects a globally-duplicate email
 * (§ 4.1, BR-K-Identity-1), records ONLY a STRICT PII-free CustomerCreated (no AccountCreated — the Account is
 * event-silent, design D7), exposes NO setter for `originating_club_id` (BR-K-OC-2, design D6), and holds the
 * scope guard (no transition out of `pending`, no lifecycle / OriginatingClubLocked event).
 *
 * RefreshDatabase: the action opens its OWN DB::transaction, so the recorder's `transactionLevel() === 0` guard
 * is satisfied by the savepoint even under the wrapper. Portability: the event payload is asserted BY KEY
 * (never a byte-compare of stored JSON — PG jsonb reorders keys, knowledge/testing trap 3), and the PII-free
 * contract is pinned by the EXACT key set so it cannot silently widen.
 */
uses(RefreshDatabase::class);

it('creates a Customer in pending carrying the customer marker and a null Originating Club', function () {
    $customer = app(CreateCustomer::class)->handle(
        email: 'collector@example.com',
        name: 'Ada Lovelace',
        preferredCurrency: Currency::EUR,
        preferredLocale: SupportedLocale::En,
        phone: '+39 02 1234567',
        dateOfBirth: CarbonImmutable::parse('1985-07-15'),
    );

    // Re-fetch so the assertions exercise the read/hydration casts, not the in-memory create() values.
    $read = Customer::findOrFail($customer->id);

    expect($read->email)->toBe('collector@example.com')
        ->and($read->name)->toBe('Ada Lovelace')
        ->and($read->phone)->toBe('+39 02 1234567')
        ->and($read->date_of_birth?->toDateString())->toBe('1985-07-15')   // DOB round-trips via the cast
        ->and($read->party_type)->toBe(PartyType::Customer)                // the immutable marker (BR-K-Identity-5)
        ->and($read->preferred_currency)->toBe('EUR')                      // ISO-code preference string (design D9)
        ->and($read->preferred_locale)->toBe('en')
        ->and($read->status)->toBe(CustomerStatus::Pending)                // born pending (design D2)
        ->and($read->originating_club_id)->toBeNull()                      // born unset (design D6)
        ->and($read->version)->toBe(1);                                    // version floor, born at 1
});

it('co-provisions exactly one active personal Account in the same transaction', function () {
    $customer = app(CreateCustomer::class)->handle(
        email: 'one.account@example.com',
        name: 'Grace Hopper',
        preferredCurrency: Currency::GBP,
        preferredLocale: SupportedLocale::En,
        dateOfBirth: CarbonImmutable::parse('1990-01-01'),   // an adult DOB — the age gate (task 5.1) requires one
    );

    // Exactly one Account exists for the Customer (the 1:1 co-provision — § 4.7, § 7.1 step 3).
    $accounts = Account::query()->where('customer_id', $customer->id)->get();
    expect($accounts)->toHaveCount(1);

    $account = $accounts->sole();
    expect($account->account_type)->toBe(AccountType::Personal)            // sole launch type (DEC-068)
        ->and($account->status)->toBe(AccountStatus::Active)               // born active (design D2)
        ->and($account->name)->toBe('Personal')                           // the default label (relied on)
        ->and($account->default_currency)->toBe('GBP')                    // mirrors the Customer's preference
        ->and($account->version)->toBe(1);

    // The within-module hasOne resolves the co-provisioned Account (relations are allowed within Module K).
    expect(Customer::findOrFail($customer->id)->account?->is($account))->toBeTrue();
});

it('rejects a Customer creation whose email collides with an existing Customer (§ 4.1, BR-K-Identity-1)', function () {
    app(CreateCustomer::class)->handle(
        email: 'dup@example.com',
        name: 'First Holder',
        preferredCurrency: Currency::EUR,
        preferredLocale: SupportedLocale::En,
        dateOfBirth: CarbonImmutable::parse('1990-01-01'),   // adult DOB — clear the age gate so the dup path is exercised
    );

    // A second creation with the SAME email is rejected by the localized pre-check ahead of the unique index.
    expect(fn () => app(CreateCustomer::class)->handle(
        email: 'dup@example.com',
        name: 'Second Holder',
        preferredCurrency: Currency::USD,
        preferredLocale: SupportedLocale::It,
        dateOfBirth: CarbonImmutable::parse('1990-01-01'),   // adult DOB — so the reject is DuplicateCustomerEmail, not the age gate
    ))->toThrow(DuplicateCustomerEmail::class);

    // The rejected second creation persisted nothing: still one Customer, one Account, one CustomerCreated.
    expect(Customer::query()->count())->toBe(1)
        ->and(Account::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerCreated::NAME)->count())->toBe(1);
});

it('creates two Customers with distinct emails, each with its own Account and event', function () {
    $first = app(CreateCustomer::class)->handle(
        email: 'a@example.com',
        name: 'Holder A',
        preferredCurrency: Currency::EUR,
        preferredLocale: SupportedLocale::En,
        dateOfBirth: CarbonImmutable::parse('1990-01-01'),   // an adult DOB — the age gate (task 5.1) requires one
    );
    $second = app(CreateCustomer::class)->handle(
        email: 'b@example.com',
        name: 'Holder B',
        preferredCurrency: Currency::EUR,
        preferredLocale: SupportedLocale::Fr,
        dateOfBirth: CarbonImmutable::parse('1990-01-01'),   // an adult DOB — the age gate (task 5.1) requires one
    );

    expect($first->id)->not->toBe($second->id)
        ->and(Customer::query()->count())->toBe(2)
        ->and(Account::query()->count())->toBe(2)                                          // one Account each
        ->and(DomainEvent::query()->where('name', CustomerCreated::NAME)->count())->toBe(2);
});

it('records a PII-free CustomerCreated domain event in the same transaction, tagged parties', function () {
    $customer = app(CreateCustomer::class)->handle(
        email: 'evented@example.com',
        name: 'Katherine Johnson',
        preferredCurrency: Currency::CHF,
        preferredLocale: SupportedLocale::De,
        phone: '+41 44 000 0000',
        dateOfBirth: CarbonImmutable::parse('1970-01-01'),
    );

    // sole() asserts EXACTLY one CustomerCreated row exists — the one-event contract.
    $event = DomainEvent::query()->where('name', CustomerCreated::NAME)->sole();

    expect($event->module)->toBe('parties')                          // Module::Parties->value
        ->and($event->entity_type)->toBe('Customer')
        ->and($event->entity_id)->toBe((string) $customer->id)       // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);          // the ActorContext seam default

    // The STRICT PII-free contract (design D7): the exact key set is pinned so it cannot silently widen, and
    // each PII attribute the Customer holds is asserted ABSENT from the payload.
    expect(array_keys($event->payload))->toEqualCanonicalizing([
        'customer_id', 'party_type', 'status', 'preferred_currency', 'preferred_locale', 'originating_club_id',
    ]);

    expect($event->payload)->not->toHaveKey('email')
        ->and($event->payload)->not->toHaveKey('name')
        ->and($event->payload)->not->toHaveKey('phone')
        ->and($event->payload)->not->toHaveKey('date_of_birth');

    // Payload asserted BY KEY (trap 3): structural identity + non-PII business fields only.
    expect($event->payload['customer_id'])->toBe($customer->id)
        ->and($event->payload['party_type'])->toBe('customer')
        ->and($event->payload['status'])->toBe('pending')
        ->and($event->payload['preferred_currency'])->toBe('CHF')
        ->and($event->payload['preferred_locale'])->toBe('de')
        ->and($event->payload['originating_club_id'])->toBeNull();       // born unset (design D6), by id
});

it('records no AccountCreated (or any Account) event — the Account is event-silent (design D7)', function () {
    app(CreateCustomer::class)->handle(
        email: 'silent.account@example.com',
        name: 'Silent Holder',
        preferredCurrency: Currency::EUR,
        preferredLocale: SupportedLocale::En,
        dateOfBirth: CarbonImmutable::parse('1990-01-01'),   // an adult DOB — the age gate (task 5.1) requires one
    );

    // Exactly one CustomerCreated, and ZERO Account events — neither by name nor by entity_type (design D7;
    // inventing an AccountCreated would breach spec fidelity — the PRD § 15 catalog names none).
    expect(DomainEvent::query()->where('name', CustomerCreated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'like', '%Account%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('entity_type', 'Account')->count())->toBe(0);
});

it('exposes no operation that sets the Originating Club, and records no lifecycle event (design D6/D2)', function () {
    // BR-K-OC-2 / design D6: the Originating Club is born unset and has NO mutation surface in this change. The
    // CreateCustomer action surface is exactly creation (construct + handle) — there is no setOriginatingClub /
    // update operation (the one-shot OriginatingClubLocked write arrives with the deferred membership-approval
    // change, which owns any later mutation).
    $publicMethods = collect((new ReflectionClass(CreateCustomer::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->map(fn (ReflectionMethod $method): string => $method->getName())
        ->all();

    expect($publicMethods)->toEqualCanonicalizing(['__construct', 'handle']);

    $customer = app(CreateCustomer::class)->handle(
        email: 'no.lifecycle@example.com',
        name: 'Steady Holder',
        preferredCurrency: Currency::EUR,
        preferredLocale: SupportedLocale::En,
        dateOfBirth: CarbonImmutable::parse('1990-01-01'),   // an adult DOB — the age gate (task 5.1) requires one
    );

    // Design D2/D6 scope guard: only CustomerCreated exists — never a *Activated/*Suspended/OriginatingClubLocked
    // (the deferred parties-membership-lifecycle change owns those), and the Customer stays pending.
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Suspended%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%OriginatingClubLocked%')->count())->toBe(0)
        ->and(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending);
});

it('produces a pending Customer via the factory with no Account and no event', function () {
    // The factory is a pure fixture: it bypasses the action (and its duplicate-email pre-check), so it persists
    // a bare Customer but co-provisions NO Account and records no CustomerCreated.
    $customer = Customer::factory()->create();

    expect($customer->status)->toBe(CustomerStatus::Pending)
        ->and($customer->party_type)->toBe(PartyType::Customer)
        ->and($customer->originating_club_id)->toBeNull()
        ->and($customer->version)->toBe(1)
        ->and(Account::query()->where('customer_id', $customer->id)->count())->toBe(0)   // factory provisions none
        ->and(DomainEvent::query()->count())->toBe(0);
});
