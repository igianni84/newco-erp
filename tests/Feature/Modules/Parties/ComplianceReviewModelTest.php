<?php

use App\Modules\Parties\Enums\ComplianceReviewReason;
use App\Modules\Parties\Enums\ThresholdKind;
use App\Modules\Parties\Models\ComplianceReview;
use App\Modules\Parties\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pins the ComplianceReview model + factory (change parties-enhanced-kyc-threshold task 2.2; design D6) — the
 * within-module Compliance review-queue entry raised on an enhanced-KYC threshold breach (party-registry —
 * Requirement: Compliance Review Queue). It proves the persistence-only model hydrates `reason` / `threshold_kind`
 * to their backed enums, casts `tripped_amount_minor` to a PHP int on both engines (the PG bigint-as-string trap),
 * carries `tripped_currency` as a raw string (NO MoneyCast — the event re-assembles the Money, task 2.3),
 * normalizes `resolved_at` through the `immutable_datetime` cast, and resolves the within-module
 * {@see ComplianceReview::customer()} `belongsTo`. The raw schema-layer proof is the sibling
 * ComplianceReviewSchemaTest (task 1.1); this is the Eloquent counterpart (model casts + factory + relation),
 * not a duplicate.
 *
 * RefreshDatabase: rows are created via the factory (the Eloquent write path — the enum/immutable_datetime `set`
 * casts) and RE-FETCHED so the assertions exercise the read/hydration casts, not the in-memory create() values.
 * The amount is read back BOTH through the model's `integer` cast (a strict int compare) AND at the raw column
 * level with ->toEqual (the ClubCreditTest idiom — PostgreSQL returns bigint as a numeric string). SQLite here;
 * the cross-engine close re-runs the suite on PostgreSQL 17 (task 7.1, tests-pgsql lane).
 */
uses(RefreshDatabase::class);

it('round-trips a ComplianceReview through the factory with the enum, amount and currency casts intact', function () {
    $customer = Customer::factory()->create();

    // Override to the cumulative signal + the €50k floor so the assertions exercise the OTHER threshold_kind case
    // (not just the factory default single-transaction), keeping the fixture coherent (amount ↔ kind).
    $review = ComplianceReview::factory()->create([
        'customer_id' => $customer->id,
        'threshold_kind' => ThresholdKind::CumulativeAnnual,
        'tripped_amount_minor' => 5_000_000,
        'tripped_currency' => 'EUR',
    ]);

    // Re-fetch so the assertions exercise the read/hydration casts, not the in-memory create() values.
    $read = ComplianceReview::findOrFail($review->id);

    expect($read->reason)->toBe(ComplianceReviewReason::EnhancedKycThreshold)   // reason casts to the enum
        ->and($read->threshold_kind)->toBe(ThresholdKind::CumulativeAnnual)      // threshold_kind casts to the enum
        ->and($read->tripped_amount_minor)->toBe(5_000_000)                      // the `integer` cast → a real PHP int on BOTH engines
        ->and($read->tripped_currency)->toBe('EUR')                             // the raw ISO 4217 code (no MoneyCast)
        ->and($read->resolved_at)->toBeNull();                                  // open review (factory default: resolved_at NULL)

    // The amount is a bigint column on disk; read RAW it round-trips as a numeric string on PostgreSQL, so assert
    // it with ->toEqual (never ->toBe) — the model's `integer` cast is what normalized it to a PHP int above.
    expect(DB::table('parties_compliance_reviews')->where('id', $review->id)->value('tripped_amount_minor'))
        ->toEqual(5_000_000);

    // The within-module belongsTo resolves the owning Customer (relations are allowed within Module K — no
    // cross-module relation, so ModuleBoundariesTest stays green).
    expect($read->customer)->toBeInstanceOf(Customer::class)
        ->and($read->customer->is($customer))->toBeTrue();
});

it('hydrates resolved_at through the immutable_datetime cast for a resolved review', function () {
    // The resolved() state stamps `resolved_at` (NULL = open ⇒ non-NULL = resolved — design D6, no FSM); the read
    // hydrates it through the `immutable_datetime` cast to a CarbonImmutable on both engines.
    $review = ComplianceReview::factory()->resolved()->create();

    $read = ComplianceReview::findOrFail($review->id);

    expect($read->resolved_at)->toBeInstanceOf(CarbonImmutable::class);
});
