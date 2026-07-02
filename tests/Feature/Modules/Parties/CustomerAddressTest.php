<?php

use App\Modules\Parties\Actions\CreateCustomerAddress;
use App\Modules\Parties\Exceptions\InvalidAddressCountryCode;
use App\Modules\Parties\Models\Address;
use App\Modules\Parties\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Customer Address — Module K's billing Address entity (parties-anonymisation task 2.1; design D4;
 * party-registry — Requirement: Customer Address; DEC-068 / AC-K-XM-25). It proves the thin
 * {@see CreateCustomerAddress} action persists an Address scoped to a Customer with the personal fields + the
 * OPTIONAL company-billing affordance (`company_name` / `vat_id`), resolves the within-module `hasMany` /
 * `belongsTo` both ways, validates `country_code` at the action boundary (ISO 3166-1 alpha-2, fail-closed — the
 * migration's "not a DB enum/CHECK"), and keeps the company data on the Address while the Customer carries NO
 * B2C/B2B discriminator (AC-K-XM-25 — the Customer stays the natural person).
 *
 * The cross-module-import boundary (invariant 10 — Address `belongsTo` Customer / Customer `hasMany` Address are
 * WITHIN Module K) is enforced comprehensively by tests/Architecture/ModuleBoundariesTest (which scans the whole
 * `App\Modules\Parties` namespace, so the new model + action are covered there and it stays green unamended); it
 * is not duplicated here — boundary law lives in the arch suite, not a feature test.
 *
 * RefreshDatabase per the directory convention; the action is a plain single-insert (no event, no transaction), so
 * there is no recorder-guard interaction. The Customer is stood up by its factory (a pure fixture — it records no
 * event and co-provisions no Account), keeping each case focused on the Address.
 */
uses(RefreshDatabase::class);

it('creates a billing Address for a Customer with the optional company fields, resolving the relation both ways', function () {
    $customer = Customer::factory()->create();

    $address = app(CreateCustomerAddress::class)->handle(
        customerId: $customer->id,
        line1: '10 Downing Street',
        locality: 'London',
        postalCode: 'SW1A 2AA',
        countryCode: 'GB',
        line2: 'Westminster',
        region: 'Greater London',
        companyName: 'Acme Collectibles Ltd',
        vatId: 'GB123456789',
    );

    // Re-fetch so the assertions exercise the read/hydration, not the in-memory create() values.
    $read = Address::findOrFail($address->id);

    expect($read->customer_id)->toBe($customer->id)
        ->and($read->line1)->toBe('10 Downing Street')
        ->and($read->line2)->toBe('Westminster')
        ->and($read->locality)->toBe('London')
        ->and($read->region)->toBe('Greater London')
        ->and($read->postal_code)->toBe('SW1A 2AA')
        ->and($read->country_code)->toBe('GB')
        ->and($read->company_name)->toBe('Acme Collectibles Ltd')   // the DEC-068 company-billing affordance
        ->and($read->vat_id)->toBe('GB123456789');

    // The within-module hasMany resolves the Address (fresh query — the property was not eager-loaded); the
    // belongsTo resolves back to the owning Customer (relations are allowed within Module K).
    expect($customer->addresses()->count())->toBe(1)
        ->and($read->customer->is($customer))->toBeTrue();
});

it('creates a natural-person billing Address, leaving the optional company + line fields null', function () {
    $customer = Customer::factory()->create();

    $address = app(CreateCustomerAddress::class)->handle(
        customerId: $customer->id,
        line1: 'Via Roma 1',
        locality: 'Milano',
        postalCode: '20121',
        countryCode: 'IT',
    );

    $read = Address::findOrFail($address->id);

    // The optional fields default to NULL when omitted (a natural-person address with no company data).
    expect($read->line1)->toBe('Via Roma 1')
        ->and($read->locality)->toBe('Milano')
        ->and($read->country_code)->toBe('IT')
        ->and($read->line2)->toBeNull()
        ->and($read->region)->toBeNull()
        ->and($read->company_name)->toBeNull()
        ->and($read->vat_id)->toBeNull();
});

it('exposes many Addresses for one Customer via the within-module hasMany', function () {
    $customer = Customer::factory()->create();

    app(CreateCustomerAddress::class)->handle(
        customerId: $customer->id, line1: 'A1', locality: 'Rome', postalCode: '00100', countryCode: 'IT',
    );
    app(CreateCustomerAddress::class)->handle(
        customerId: $customer->id, line1: 'B2', locality: 'Nice', postalCode: '06000', countryCode: 'FR',
    );

    expect($customer->addresses()->count())->toBe(2)
        ->and(Address::query()->where('customer_id', $customer->id)->count())->toBe(2);
});

it('rejects a country_code outside ISO 3166-1 alpha-2 format at the action boundary, persisting nothing', function (string $badCode) {
    $customer = Customer::factory()->create();

    // Fail-closed: a lowercase, wrong-length, non-alpha or mixed-case code is rejected (never silently
    // normalized) with the localized InvalidAddressCountryCode ahead of persistence (design D4).
    expect(fn () => app(CreateCustomerAddress::class)->handle(
        customerId: $customer->id,
        line1: '1 Rue de la Paix',
        locality: 'Paris',
        postalCode: '75002',
        countryCode: $badCode,
    ))->toThrow(InvalidAddressCountryCode::class);

    // The rejected creation persisted nothing.
    expect(Address::query()->count())->toBe(0);
})->with([
    'three letters' => ['ITA'],
    'lowercase' => ['it'],
    'one letter' => ['I'],
    'letter + digit' => ['I1'],
    'empty' => [''],
    'mixed case' => ['iT'],
]);

it('keeps company-billing on the Address, with no B2C/B2B discriminator on the Customer (AC-K-XM-25 / DEC-068)', function () {
    // The company-billing affordance lives on the Address (an individual collector transacting through a
    // company)...
    expect(Schema::hasColumn('parties_addresses', 'company_name'))->toBeTrue()
        ->and(Schema::hasColumn('parties_addresses', 'vat_id'))->toBeTrue();

    // ...and the Customer carries NO B2C/B2B discriminator and NO company data — it stays the natural person
    // (AC-K-XM-25 / DEC-068: B2B dropped at Customer level, company-billing preserved at Address).
    expect(Schema::hasColumn('parties_customers', 'is_business'))->toBeFalse()
        ->and(Schema::hasColumn('parties_customers', 'is_company'))->toBeFalse()
        ->and(Schema::hasColumn('parties_customers', 'customer_type'))->toBeFalse()
        ->and(Schema::hasColumn('parties_customers', 'company_name'))->toBeFalse()
        ->and(Schema::hasColumn('parties_customers', 'vat_id'))->toBeFalse();
});
