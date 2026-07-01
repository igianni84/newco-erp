<?php

use App\Modules\Parties\Actions\LiftHold;
use App\Modules\Parties\Actions\PlaceHold;
use App\Modules\Parties\Contracts\ComplianceStatus;
use App\Modules\Parties\Contracts\PartyComplianceStatusReader;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Reads\DatabaseComplianceStatusReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the uniform Hold/sanctions read-API (parties-holds, design L6; party-registry — Requirement: Hold and
 * Sanctions Read-API; DEC-181). {@see PartyComplianceStatusReader} is Module K's single cross-module compliance
 * surface — it answers "is this scope clear to transact?" by returning the PII-free {@see ComplianceStatus}
 * tuple `(sanctions_status, active-Hold-list)` for a Customer or a Profile scope, cascade-resolved.
 *
 * The invariants this file pins: the contract resolves to the bound `DatabaseComplianceStatusReader`; a scope is
 * clear iff its sanctions screening is `passed` AND it carries no active Hold (a NULL / non-`passed` sanctions
 * status or any active Hold makes it not clear); a Customer-scope Hold cascades to every Profile of that Customer
 * (BR-K-Hold-3) while a Profile-scope Hold isolates to that Profile (BR-K-Hold-4) and is not seen by a sibling
 * Profile or by the Customer scope; only `active` Holds count (a lifted Hold drops out); and the contract returns
 * the DTO of enums, never the `Hold` Eloquent model and never PII.
 *
 * RefreshDatabase per the directory convention; the read paths and the cascade query all touch the database, so
 * the cross-engine close on PostgreSQL 17 runs in task 6.3.
 */
uses(RefreshDatabase::class);

it('resolves the bound database implementation from the container', function () {
    expect(app(PartyComplianceStatusReader::class))->toBeInstanceOf(DatabaseComplianceStatusReader::class);
});

it('reports a sanctions-passed Customer with no active Hold as clear', function () {
    $customer = Customer::factory()->create(['sanctions_status' => SanctionsStatus::Passed]);

    $status = app(PartyComplianceStatusReader::class)->forCustomer($customer->id);

    expect($status)->toBeInstanceOf(ComplianceStatus::class)
        ->and($status->sanctionsStatus)->toBe(SanctionsStatus::Passed)
        ->and($status->activeHoldTypes)->toBe([])
        ->and($status->isClear())->toBeTrue();
});

it('reports a sanctions-passed Customer with an active Hold as not clear and lists the Hold type', function () {
    $customer = Customer::factory()->create(['sanctions_status' => SanctionsStatus::Passed]);
    Hold::factory()->create([
        'hold_type' => HoldType::Fraud,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
        'status' => HoldStatus::Active,
    ]);

    $status = app(PartyComplianceStatusReader::class)->forCustomer($customer->id);

    // Sanctions passed but a Customer-scope Hold is active → not clear, and the type is listed.
    expect($status->sanctionsStatus)->toBe(SanctionsStatus::Passed)
        ->and($status->activeHoldTypes)->toContain(HoldType::Fraud)
        ->and($status->isClear())->toBeFalse();
});

it('cascades a Customer-scope Hold to every Profile of that Customer', function () {
    // A Customer-scope `fraud` Hold blocks the Customer AND every Profile of that Customer (BR-K-Hold-3 — the
    // cascade resolves at read, no duplicate Hold rows are written per Profile).
    $customer = Customer::factory()->create(['sanctions_status' => SanctionsStatus::Passed]);
    $profileA = Profile::factory()->create(['customer_id' => $customer->id]);
    $profileB = Profile::factory()->create(['customer_id' => $customer->id]);

    Hold::factory()->create([
        'hold_type' => HoldType::Fraud,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
    ]);

    $reader = app(PartyComplianceStatusReader::class);

    $customerStatus = $reader->forCustomer($customer->id);
    expect($customerStatus->activeHoldTypes)->toContain(HoldType::Fraud)
        ->and($customerStatus->isClear())->toBeFalse();

    // Both Profiles inherit the Customer Hold (cascade) and read the parent Customer's sanctions status.
    foreach ([$profileA, $profileB] as $profile) {
        $status = $reader->forProfile($profile->id);
        expect($status->sanctionsStatus)->toBe(SanctionsStatus::Passed)
            ->and($status->activeHoldTypes)->toContain(HoldType::Fraud)
            ->and($status->isClear())->toBeFalse();
    }
});

it('isolates a Profile-scope Hold to that Profile and leaves a sibling Profile clear', function () {
    // A Profile-scope `payment` Hold on Profile X isolates (BR-K-Hold-4): the sibling Profile Y does not see it,
    // and neither does the Customer scope.
    $customer = Customer::factory()->create(['sanctions_status' => SanctionsStatus::Passed]);
    $profileX = Profile::factory()->create(['customer_id' => $customer->id]);
    $profileY = Profile::factory()->create(['customer_id' => $customer->id]);

    Hold::factory()->create([
        'hold_type' => HoldType::Payment,
        'scope_type' => HoldScope::Profile,
        'scope_id' => $profileX->id,
    ]);

    $reader = app(PartyComplianceStatusReader::class);

    // X carries its own Hold → not clear.
    $x = $reader->forProfile($profileX->id);
    expect($x->activeHoldTypes)->toContain(HoldType::Payment)
        ->and($x->isClear())->toBeFalse();

    // Y has no Hold of its own and its Customer is sanctions-passed with no Customer-scope Hold → clear.
    $y = $reader->forProfile($profileY->id);
    expect($y->activeHoldTypes)->toBe([])
        ->and($y->isClear())->toBeTrue();

    // The Customer scope does not see the Profile-scope Hold either (isolation upward).
    $c = $reader->forCustomer($customer->id);
    expect($c->activeHoldTypes)->toBe([])
        ->and($c->isClear())->toBeTrue();
});

it('counts only active Holds — a lifted Hold drops out and the scope is clear again', function () {
    // Drive the real Actions so the active → lifted lifecycle is exercised, then confirm the read-API reflects it.
    $customer = Customer::factory()->create(['sanctions_status' => SanctionsStatus::Passed]);
    $placed = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Customer, $customer->id, 'manual review');

    $reader = app(PartyComplianceStatusReader::class);
    expect($reader->forCustomer($customer->id)->isClear())->toBeFalse();   // an active admin Hold blocks

    app(LiftHold::class)->handle($placed->id, 'review cleared');

    $status = $reader->forCustomer($customer->id);
    expect($status->activeHoldTypes)->toBe([])
        ->and($status->isClear())->toBeTrue();        // lifted → no longer counted
});

it('treats a null or non-passed sanctions_status as not clear even with no Hold', function (?SanctionsStatus $sanctions) {
    $customer = Customer::factory()->create(['sanctions_status' => $sanctions]);

    $status = app(PartyComplianceStatusReader::class)->forCustomer($customer->id);

    expect($status->sanctionsStatus)->toBe($sanctions)
        ->and($status->activeHoldTypes)->toBe([])
        ->and($status->isClear())->toBeFalse();
})->with([
    'null (un-screened)' => null,
    'pending' => SanctionsStatus::Pending,
    'failed' => SanctionsStatus::Failed,
    'under_review' => SanctionsStatus::UnderReview,
]);

it('reports each of the eight Hold types through the read-API as a not-clear scope', function (HoldType $type) {
    // Every Hold type makes a sanctions-passed Customer not clear — the read-API is type-agnostic (the blocking
    // is the downstream surface's; the read-API only reports the active types). Covers the two DEC-008 finance-driven
    // types too (chargeback_review, storage_payment_failed — ADR 2026-07-01): an active Hold of any type is reported.
    $customer = Customer::factory()->create(['sanctions_status' => SanctionsStatus::Passed]);
    Hold::factory()->create([
        'hold_type' => $type,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
    ]);

    $status = app(PartyComplianceStatusReader::class)->forCustomer($customer->id);

    expect($status->activeHoldTypes)->toContain($type)
        ->and($status->isClear())->toBeFalse();
})->with([
    'admin' => HoldType::Admin,
    'kyc' => HoldType::Kyc,
    'payment' => HoldType::Payment,
    'fraud' => HoldType::Fraud,
    'compliance' => HoldType::Compliance,
    'credit' => HoldType::Credit,
    'chargeback_review' => HoldType::ChargebackReview,
    'storage_payment_failed' => HoldType::StoragePaymentFailed,
]);

it('returns the DISTINCT type once when a scope carries multiple active Holds of one type', function () {
    // BR-K-Hold-1 permits multiple concurrent Holds; the read-API lists the TYPE, not the rows — so two `admin`
    // Holds surface as a single `admin` entry.
    $customer = Customer::factory()->create(['sanctions_status' => SanctionsStatus::Passed]);
    Hold::factory()->count(2)->create([
        'hold_type' => HoldType::Admin,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
    ]);

    $status = app(PartyComplianceStatusReader::class)->forCustomer($customer->id);

    expect($status->activeHoldTypes)->toBe([HoldType::Admin])    // deduped to one entry
        ->and($status->isClear())->toBeFalse();
});

it('returns a PII-free ComplianceStatus DTO of enums, never the Hold model', function () {
    // PII sentinels on the Customer so the assertion can prove no personal data rides the DTO.
    $customer = Customer::factory()->create([
        'email' => 'read-api-sentinel@example.test',
        'name' => 'Read Api Sentinel',
        'sanctions_status' => SanctionsStatus::Passed,
    ]);
    Hold::factory()->create([
        'hold_type' => HoldType::Compliance,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
    ]);

    $status = app(PartyComplianceStatusReader::class)->forCustomer($customer->id);

    // The contract returns the DTO; the list carries HoldType enums, never Hold rows.
    expect($status)->toBeInstanceOf(ComplianceStatus::class)
        ->and($status->activeHoldTypes)->not->toBeEmpty();
    foreach ($status->activeHoldTypes as $type) {
        expect($type)->toBeInstanceOf(HoldType::class)
            ->and($type)->not->toBeInstanceOf(Hold::class);
    }
    // No PII anywhere on the DTO's exposed tuple (re-anchor with ->and between the two negatives — the repo idiom).
    $values = array_map(fn (HoldType $t): string => $t->value, $status->activeHoldTypes);
    expect($values)->not->toContain('read-api-sentinel@example.test')
        ->and($values)->not->toContain('Read Api Sentinel');
});
