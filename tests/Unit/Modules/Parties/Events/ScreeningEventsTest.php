<?php

use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Events\CustomerOnboardingScreeningFailed;
use App\Modules\Parties\Events\CustomerOnboardingScreeningPassed;
use App\Modules\Parties\Events\CustomerRescreeningFailed;
use App\Modules\Parties\Events\CustomerRescreeningPassed;
use App\Modules\Parties\Models\Customer;
use Tests\TestCase;

// Pins the four sanctions screening events (parties-compliance task 4.1; design L3/L4; party-registry —
// Requirement: Sanctions Screening Events). Each is the verbatim § 15.6 name with the `final` NAME / ENTITY_TYPE /
// static payload() shape of the shipped Parties events (ProducerActivated, CustomerCreated). The four split two
// phases × two outcomes — onboarding {Passed,Failed} / rescreening {Passed,Failed}; `under_review` is event-silent
// (design L4) and KYC records no event at all (design L3), so neither has a class — a fact the SupplyLifecycleChain
// scope guard pins separately. Every payload is PII-free: the Customer carries email/name/phone/date_of_birth, none
// of which may reach the 10-year audit store.
//
// Booting the app (TestCase, NO RefreshDatabase) gives the model its enum casts while touching no database: the
// fixture is built with factory()->make(), which never persists or queries — the absence of a migrated schema is
// itself the guard that a query would fail loudly.

uses(TestCase::class);

// An in-memory Customer (never saved — make() runs no query) carrying PII sentinels alongside the post-screening
// compliance state, so the payload assertions can prove no personal field leaks into a screening event.
$customer = fn (SanctionsStatus $status, ScreeningTriggerSource $source): Customer => Customer::factory()->make([
    'id' => 7,
    'email' => 'collector@example.test',
    'name' => 'Jane Collector',
    'phone' => '+39 02 9999999',
    'sanctions_status' => $status,
    'screening_trigger_source' => $source,
]);

// Asserts a screening payload is EXACTLY the PII-free triple — the three keys in order with the expected verdict /
// source values — and leaks none of the Customer's four personal fields (by key and by value).
$expectPiiFreeTriple = function (array $payload, string $status, string $source): void {
    expect(array_keys($payload))->toBe(['customer_id', 'sanctions_status', 'trigger_source'])
        ->and($payload)->toBe([
            'customer_id' => 7,
            'sanctions_status' => $status,
            'trigger_source' => $source,
        ])
        ->and($payload)->not->toHaveKey('email')
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('phone')
        ->and($payload)->not->toHaveKey('date_of_birth')
        ->and(array_values($payload))->not->toContain('Jane Collector')
        ->and(array_values($payload))->not->toContain('collector@example.test')
        ->and(array_values($payload))->not->toContain('+39 02 9999999');
};

it('exposes the four verbatim § 15.6 screening event NAMEs', function () {
    expect(CustomerOnboardingScreeningPassed::NAME)->toBe('CustomerOnboardingScreeningPassed')
        ->and(CustomerOnboardingScreeningFailed::NAME)->toBe('CustomerOnboardingScreeningFailed')
        ->and(CustomerRescreeningPassed::NAME)->toBe('CustomerRescreeningPassed')
        ->and(CustomerRescreeningFailed::NAME)->toBe('CustomerRescreeningFailed');
});

it('declares the Customer ENTITY_TYPE on each screening event', function () {
    expect(CustomerOnboardingScreeningPassed::ENTITY_TYPE)->toBe('Customer')
        ->and(CustomerOnboardingScreeningFailed::ENTITY_TYPE)->toBe('Customer')
        ->and(CustomerRescreeningPassed::ENTITY_TYPE)->toBe('Customer')
        ->and(CustomerRescreeningFailed::ENTITY_TYPE)->toBe('Customer');
});

it('declares each screening event a final class', function () {
    expect((new ReflectionClass(CustomerOnboardingScreeningPassed::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(CustomerOnboardingScreeningFailed::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(CustomerRescreeningPassed::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(CustomerRescreeningFailed::class))->isFinal())->toBeTrue();
});

it('snapshots the PII-free triple for CustomerOnboardingScreeningPassed', function () use ($customer, $expectPiiFreeTriple) {
    $expectPiiFreeTriple(
        CustomerOnboardingScreeningPassed::payload($customer(SanctionsStatus::Passed, ScreeningTriggerSource::Onboarding)),
        'passed',
        'onboarding',
    );
});

it('snapshots the PII-free triple for CustomerOnboardingScreeningFailed', function () use ($customer, $expectPiiFreeTriple) {
    $expectPiiFreeTriple(
        CustomerOnboardingScreeningFailed::payload($customer(SanctionsStatus::Failed, ScreeningTriggerSource::Onboarding)),
        'failed',
        'onboarding',
    );
});

it('snapshots the PII-free triple for CustomerRescreeningPassed', function () use ($customer, $expectPiiFreeTriple) {
    $expectPiiFreeTriple(
        CustomerRescreeningPassed::payload($customer(SanctionsStatus::Passed, ScreeningTriggerSource::ComplianceAdHoc)),
        'passed',
        'compliance_ad_hoc',
    );
});

it('snapshots the PII-free triple for CustomerRescreeningFailed', function () use ($customer, $expectPiiFreeTriple) {
    $expectPiiFreeTriple(
        CustomerRescreeningFailed::payload($customer(SanctionsStatus::Failed, ScreeningTriggerSource::Cadence)),
        'failed',
        'cadence',
    );
});
