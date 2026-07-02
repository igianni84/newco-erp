<?php

use App\Modules\Parties\Actions\AnonymiseCustomer;
use App\Modules\Parties\Actions\ExportCustomerData;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Models\Address;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the GDPR right-of-access / data-portability export — the {@see ExportCustomerData} action
 * (parties-anonymisation task 5.1; design D5; party-registry — Requirement: Customer Data Export; canon J-9b;
 * PRD § 12). It drives the REAL Action and asserts the § 12 minimal / synchronous / IN-MEMORY contract:
 *   - it returns a structured payload = the Customer's personal data (`customer` PII block + every scoped Address's
 *     personal fields — the same data the erasure overwrites) plus a by-id manifest of the retained transactional
 *     history (`transactional_history.profiles` — the within-module Profiles; Order/Voucher/Invoice join as those
 *     modules land);
 *   - it is strictly READ-ONLY — the Customer/Address/Profile rows are unchanged, NO file/durable artifact is
 *     written, and NO domain event is recorded (the count is unchanged across the call);
 *   - for an already-anonymised Customer it reflects the deterministic PLACEHOLDER PII (it reads current row state,
 *     and anonymisation is overwrite-in-place), not the original data.
 *
 * Fixtures are stood up through factories (the sibling CustomerAnonymisationTest convention — factories bypass the
 * Actions and record NO event, so every counted event is one an Action recorded). RefreshDatabase per the directory
 * convention; assertions read the returned payload + re-fetched rows and events BY NAME/COUNT (never a byte-compare
 * of stored jsonb), so the file holds on PostgreSQL 17.
 */
uses(RefreshDatabase::class);

it('assembles the Customer PII plus a by-id transactional-history manifest, read-only', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $profileA = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => Club::factory()->create()->id]);
    $profileB = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => Club::factory()->create()->id]);
    $address = Address::factory()->forCompany()->create(['customer_id' => $customer->id]);

    // The company-billing Address genuinely carries company data — so it must surface in the access export below.
    expect($address->company_name)->not->toBeNull();

    $payload = app(ExportCustomerData::class)->handle($customer->id);

    // (1) The Customer's own PII block — the four personal-data columns plus the opaque id.
    expect($payload['customer'])->toEqualCanonicalizing([
        'id' => $customer->id,
        'name' => $customer->name,
        'email' => $customer->email,
        'phone' => $customer->phone,
        'date_of_birth' => '1990-01-01',   // the CustomerFactory literal, via the immutable_date cast
    ]);

    // (1) Every scoped Address's personal fields (also the Customer's personal data) — full, not by-id.
    expect($payload['addresses'])->toEqualCanonicalizing([[
        'id' => $address->id,
        'line1' => $address->line1,
        'line2' => $address->line2,
        'locality' => $address->locality,
        'region' => $address->region,
        'postal_code' => $address->postal_code,
        'country_code' => $address->country_code,
        'company_name' => $address->company_name,
        'vat_id' => $address->vat_id,
    ]]);

    // (2) The transactional-history manifest — the Customer's Profiles BY ID (order-independent).
    expect($payload['transactional_history']['profiles'])->toEqualCanonicalizing([$profileA->id, $profileB->id]);

    // Read-only — the Customer row is untouched by the export.
    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->email)->toBe($customer->email)
        ->and($fresh->name)->toBe($customer->name)
        ->and($fresh->anonymised_at)->toBeNull();
});

it('persists nothing and records no domain event — a pure read-only assembly', function () {
    $customer = Customer::factory()->create();
    Address::factory()->create(['customer_id' => $customer->id]);

    // Factories record no event — the delta below is honest.
    expect(DomainEvent::query()->count())->toBe(0);

    $payload = app(ExportCustomerData::class)->handle($customer->id);

    // No event recorded, no durable artifact — and nothing mutated.
    expect(DomainEvent::query()->count())->toBe(0)
        ->and($payload)->toHaveKeys(['customer', 'addresses', 'transactional_history']);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->anonymised_at)->toBeNull()
        ->and($fresh->email)->toBe($customer->email);
});

it('reflects the deterministic placeholder PII when the Customer has been anonymised', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $address = Address::factory()->forCompany()->create(['customer_id' => $customer->id]);

    $originalEmail = $customer->email;

    // Anonymise first — overwrites the PII in place, recording exactly one CustomerAnonymised event.
    app(AnonymiseCustomer::class)->handle($customer->id);
    expect(DomainEvent::query()->count())->toBe(1);

    $payload = app(ExportCustomerData::class)->handle($customer->id);

    // The export reads current row state → it reflects the deterministic placeholder PII, not the original data.
    expect($payload['customer']['email'])->toBe("anonymised+{$customer->id}@anonymised.invalid")
        ->and($payload['customer']['name'])->toBe("Anonymised Customer {$customer->id}")
        ->and($payload['customer']['phone'])->toBeNull()
        ->and($payload['customer']['date_of_birth'])->toBeNull();
    expect($payload['customer']['email'])->not->toBe($originalEmail);

    // The Address personal fields likewise reflect the placeholders in the export.
    expect($payload['addresses'])->toEqualCanonicalizing([[
        'id' => $address->id,
        'line1' => 'Anonymised',
        'line2' => null,
        'locality' => 'Anonymised',
        'region' => null,
        'postal_code' => 'Anonymised',
        'country_code' => 'ZZ',
        'company_name' => null,
        'vat_id' => null,
    ]]);

    // The export itself records NO further event — the count stays exactly the one anonymisation recorded.
    expect(DomainEvent::query()->count())->toBe(1);
});

it('returns empty manifests for a Customer with no Addresses or Profiles, still carrying the PII block', function () {
    $customer = Customer::factory()->create();

    $payload = app(ExportCustomerData::class)->handle($customer->id);

    expect($payload['addresses'])->toBe([])
        ->and($payload['transactional_history']['profiles'])->toBe([])
        ->and($payload['customer']['id'])->toBe($customer->id)
        ->and($payload['customer']['email'])->toBe($customer->email);
});
