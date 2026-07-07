<?php

use App\Modules\Parties\Actions\CreateCustomer;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Events\CustomerCreated;
use App\Modules\Parties\Exceptions\BelowMinimumRegistrationAge;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\DomainEvent;
use App\Platform\I18n\SupportedLocale;
use App\Platform\Money\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Registration Age Gate (change parties-module-k-br-guards, task 5.1; design D7; party-registry —
 * Requirement: Registration Age Gate; BR-K-Identity-6 / canon MVP-DEC-022 — ADR
 * 2026-07-07-adopt-mvp-dec-022-club-membership-governance; BMD § 2.8). It drives the REAL {@see CreateCustomer}
 * action and asserts the emergent contract: a self-attested `date_of_birth` whose implied age at the registration
 * date is below the platform minimum — and a missing DOB (attestation is mandatory at launch) — is rejected with
 * {@see BelowMinimumRegistrationAge} BEFORE any Customer, co-provisioned Account or {@see CustomerCreated} exists
 * (the guard is a pure input-validity reject at the boundary, ahead of the transaction), while a `date_of_birth`
 * AT or ABOVE the minimum is admitted. The minimum age is the admin-configurable platform constant
 * {@see CreateCustomer::MINIMUM_REGISTRATION_AGE} (default 18), NOT hard-coded.
 *
 * The clock is FROZEN (the ProfileLapseGraceTest idiom) so the "exactly at the minimum" and "one day under"
 * boundaries are deterministic, and every boundary DOB is derived from the constant so the assertions can never
 * drift from the enforced value. The gate lives in the single creation chokepoint ({@see CreateCustomer}), so it
 * covers every onboarding entry channel (§ 7.1 / § 7.2 / § 7.3) by construction. PII discipline: the reject
 * message names the rule and interpolates only `:min_age` — never the DOB.
 */
uses(RefreshDatabase::class);

// Reset the frozen clock after each test so the global test-now never leaks into a sibling (the SweepTest idiom).
afterEach(fn () => CarbonImmutable::setTestNow());

/** A fixed registration instant; boundary DOBs are derived from it and the enforced min-age constant. */
function ageGateFreezeNow(): CarbonImmutable
{
    $now = CarbonImmutable::parse('2026-07-07 12:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    return $now;
}

it('rejects a registration whose self-attested date_of_birth is one day below the minimum, creating nothing', function () {
    $now = ageGateFreezeNow();

    // Born one day after the "exactly the minimum" mark → they turn the minimum age tomorrow, so today they are below it.
    $underAge = $now->subYears(CreateCustomer::MINIMUM_REGISTRATION_AGE)->addDay();

    expect(fn () => app(CreateCustomer::class)->handle(
        email: 'under.age@example.com',
        name: 'Too Young',
        preferredCurrency: Currency::EUR,
        preferredLocale: SupportedLocale::En,
        dateOfBirth: $underAge,
    ))->toThrow(BelowMinimumRegistrationAge::class);

    // The reject preceded the transaction: no Customer, no co-provisioned Account, no CustomerCreated event.
    expect(Customer::query()->count())->toBe(0)
        ->and(Account::query()->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', CustomerCreated::NAME)->count())->toBe(0);
});

it('rejects a registration with no attested date_of_birth, creating nothing (attestation is mandatory at launch)', function () {
    ageGateFreezeNow();

    // A null DOB (the parameter default) is rejected — age attestation is mandatory (BMD § 2.8).
    expect(fn () => app(CreateCustomer::class)->handle(
        email: 'no.dob@example.com',
        name: 'No Birthdate',
        preferredCurrency: Currency::EUR,
        preferredLocale: SupportedLocale::En,
    ))->toThrow(BelowMinimumRegistrationAge::class);

    expect(Customer::query()->count())->toBe(0)
        ->and(Account::query()->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', CustomerCreated::NAME)->count())->toBe(0);
});

it('admits a registration whose date_of_birth is exactly the minimum age today (the inclusive boundary)', function () {
    $now = ageGateFreezeNow();

    // Born exactly the minimum number of years ago today → age equals the minimum → "at the minimum" → admitted.
    $exactlyMinimum = $now->subYears(CreateCustomer::MINIMUM_REGISTRATION_AGE);

    $customer = app(CreateCustomer::class)->handle(
        email: 'exactly.min@example.com',
        name: 'Just Old Enough',
        preferredCurrency: Currency::EUR,
        preferredLocale: SupportedLocale::En,
        dateOfBirth: $exactlyMinimum,
    );

    // The Customer was created (born pending) with the DOB round-tripping through the immutable_date cast, and the
    // 1:1 Account + CustomerCreated event landed — the full happy path is unaffected by the gate.
    $read = Customer::findOrFail($customer->id);
    expect($read->status)->toBe(CustomerStatus::Pending)
        ->and($read->date_of_birth?->toDateString())->toBe($exactlyMinimum->toDateString())
        ->and(Account::query()->where('customer_id', $read->id)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerCreated::NAME)->count())->toBe(1);
});

it('admits a registration whose date_of_birth is comfortably over the minimum age', function () {
    $now = ageGateFreezeNow();

    // A decade past the minimum — unambiguously an adult.
    $adult = $now->subYears(CreateCustomer::MINIMUM_REGISTRATION_AGE + 10);

    $customer = app(CreateCustomer::class)->handle(
        email: 'clearly.adult@example.com',
        name: 'Grown Up',
        preferredCurrency: Currency::EUR,
        preferredLocale: SupportedLocale::En,
        dateOfBirth: $adult,
    );

    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending)
        ->and(DomainEvent::query()->where('name', CustomerCreated::NAME)->count())->toBe(1);
});

it('exposes the minimum age as a configurable platform constant defaulting to 18', function () {
    // The gate reads its floor from a single admin-configurable platform constant (not a hard-coded literal),
    // defaulting to 18 — the EU alcohol-purchase baseline (mirroring the RM-02 enhanced-KYC threshold constants).
    expect(CreateCustomer::MINIMUM_REGISTRATION_AGE)->toBe(18);
});

it('throws the localized age-gate reason naming the minimum age when the gate fires', function () {
    $now = ageGateFreezeNow();
    $underAge = $now->subYears(CreateCustomer::MINIMUM_REGISTRATION_AGE)->addDay();

    // toThrow's second argument asserts the thrown message CONTAINS the min-age constant — proving the gate raises
    // the localized reason itself (not just any exception). The full PII-free copy (only :min_age is interpolated;
    // the DOB is structurally un-leakable) is pinned in BrGuardExceptionsTest.
    expect(fn () => app(CreateCustomer::class)->handle(
        email: 'pii.check@example.com',
        name: 'Privacy Probe',
        preferredCurrency: Currency::EUR,
        preferredLocale: SupportedLocale::En,
        dateOfBirth: $underAge,
    ))->toThrow(BelowMinimumRegistrationAge::class, (string) CreateCustomer::MINIMUM_REGISTRATION_AGE);
});
