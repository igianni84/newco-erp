<?php

use App\Modules\Parties\Enums\ThresholdKind;
use App\Modules\Parties\Events\CustomerEnhancedKycReviewRequired;
use App\Modules\Parties\Models\ComplianceReview;
use App\Modules\Parties\Models\Customer;
use Carbon\CarbonImmutable;
use Tests\TestCase;

// Pins the net-new enhanced-KYC escalation event (parties-enhanced-kyc-threshold task 2.3; design D5; party-registry
// — Requirement: Compliance Review Queue). It carries the `final` NAME / ENTITY_TYPE / static payload() shape of the
// shipped Parties events (CustomerRescreeningPassed, CustomerAnonymised). It is DELIBERATELY added over the frozen
// § 15.6 event-free catalogue (design D5, the CustomerAnonymised precedent). The payload is STRICT PII-free: the
// Customer carries email/name/phone/date_of_birth, none of which may reach the 10-year audit store — only the
// customer_id, the ISO enhanced_kyc_at, the tripped threshold_kind (value), and the tripping amount as the money
// envelope {minor_units, currency} (invariant 6).
//
// Booting the app (TestCase, NO RefreshDatabase) gives the models their enum/datetime casts while touching no
// database: the fixtures are built with factory()->make() and an explicit `customer_id` override, so the review's
// `Customer::factory()` FK never resolves and nothing persists or queries — the absence of a migrated schema is
// itself the guard that a stray query would fail loudly.

uses(TestCase::class);

// An in-memory Customer (never saved — make() runs no query) carrying PII sentinels + the escalation timestamp, so
// the payload assertions can prove no personal field leaks into the event.
$customer = fn (): Customer => Customer::factory()->make([
    'id' => 7,
    'email' => 'collector@example.test',
    'name' => 'Jane Collector',
    'phone' => '+39 02 9999999',
    'date_of_birth' => CarbonImmutable::parse('1975-04-11'),
    'enhanced_kyc_at' => CarbonImmutable::parse('2026-07-02T09:30:00+00:00'),
]);

// An in-memory open review for the given breach — the explicit `customer_id` override replaces the factory's
// `Customer::factory()` FK so make() resolves no parent and touches no database.
$review = fn (ThresholdKind $kind, int $minor): ComplianceReview => ComplianceReview::factory()->make([
    'customer_id' => 7,
    'threshold_kind' => $kind,
    'tripped_amount_minor' => $minor,
    'tripped_currency' => 'EUR',
]);

it('exposes the net-new event NAME', function () {
    expect(CustomerEnhancedKycReviewRequired::NAME)->toBe('CustomerEnhancedKycReviewRequired');
});

it('declares the Customer ENTITY_TYPE', function () {
    expect(CustomerEnhancedKycReviewRequired::ENTITY_TYPE)->toBe('Customer');
});

it('is a final class', function () {
    expect((new ReflectionClass(CustomerEnhancedKycReviewRequired::class))->isFinal())->toBeTrue();
});

it('builds exactly the four escalation keys, PII-free, for a single-transaction breach', function () use ($customer, $review) {
    $c = $customer();
    $payload = CustomerEnhancedKycReviewRequired::payload($c, $review(ThresholdKind::SingleTransaction, 1_000_000));

    // Exactly the four contract keys (order-independent — the hint's assertion).
    expect(array_keys($payload))->toEqualCanonicalizing(['customer_id', 'enhanced_kyc_at', 'threshold_kind', 'amount'])
        // Values: id, the ISO-8601 render of the persisted enhanced_kyc_at (single source of truth), the tripped
        // threshold_kind as its enum value, and the tripping amount as the money envelope (money discipline).
        ->and($payload['customer_id'])->toBe(7)
        ->and($payload['enhanced_kyc_at'])->toBe($c->enhanced_kyc_at?->toIso8601String())
        ->and($payload['threshold_kind'])->toBe('single_transaction')
        ->and($payload['amount'])->toBe(['minor_units' => 1_000_000, 'currency' => 'EUR'])
        // PII-free: none of the Customer's four personal fields, by key AND by value.
        ->and($payload)->not->toHaveKey('email')
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('phone')
        ->and($payload)->not->toHaveKey('date_of_birth')
        ->and(array_values($payload))->not->toContain('Jane Collector')
        ->and(array_values($payload))->not->toContain('collector@example.test')
        ->and(array_values($payload))->not->toContain('+39 02 9999999');

    // Defence-in-depth: no PII value appears anywhere in the serialised payload (catches nested leakage / the DOB).
    $json = json_encode($payload, JSON_THROW_ON_ERROR);
    expect($json)->not->toContain('Jane Collector')
        ->and($json)->not->toContain('collector@example.test')
        ->and($json)->not->toContain('+39 02 9999999')
        ->and($json)->not->toContain('1975-04-11');
});

it('records the cumulative-annual threshold_kind and its €50k tripping amount', function () use ($customer, $review) {
    $payload = CustomerEnhancedKycReviewRequired::payload($customer(), $review(ThresholdKind::CumulativeAnnual, 5_000_000));

    expect($payload['threshold_kind'])->toBe('cumulative_annual')
        ->and($payload['amount'])->toBe(['minor_units' => 5_000_000, 'currency' => 'EUR']);
});
