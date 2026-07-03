<?php

use App\Modules\Parties\Actions\CreateComplianceReview;
use App\Modules\Parties\Enums\ComplianceReviewReason;
use App\Modules\Parties\Enums\ThresholdKind;
use App\Modules\Parties\Models\ComplianceReview;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\DomainEvent;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pins the thin {@see CreateComplianceReview} action — the SOLE writer of a Compliance review-queue row
 * (change parties-enhanced-kyc-threshold, task 4.1; design D6; party-registry — Requirement: Compliance
 * Review Queue). It proves the action persists exactly one open row with the passed fields, splits the
 * tripping {@see Money} into the row's two scalars (`tripped_amount_minor` + `tripped_currency`, invariant 6),
 * returns the created model, and records NO domain event of its own — the lone escalation event
 * `CustomerEnhancedKycReviewRequired` belongs to the detection action `EvaluateEnhancedKycThreshold` (task 4.2).
 *
 * RefreshDatabase per the directory convention; the action is a plain single insert (no event, no transaction),
 * so there is no recorder-guard interaction. The Customer is stood up by its factory (a pure fixture — it records
 * no event and co-provisions no Account), so a `DomainEvent` count isolates the action's effect precisely.
 * Money/bigint columns are read raw via `->value('col')` and asserted with `->toEqual` (a `bigInteger` reads back
 * as a numeric STRING on PostgreSQL — never `->toBe`).
 */
uses(RefreshDatabase::class);

it('creates exactly one open review row with the passed fields and returns the created model', function () {
    $customer = Customer::factory()->create();

    $review = app(CreateComplianceReview::class)->handle(
        customerId: $customer->id,
        reason: ComplianceReviewReason::EnhancedKycThreshold,
        thresholdKind: ThresholdKind::SingleTransaction,
        trippedAmount: Money::of(1_000_000, Currency::EUR),   // €10,000 — the single-transaction floor (DEC-035)
    );

    // Returns the created model.
    expect($review)->toBeInstanceOf(ComplianceReview::class);

    // Exactly one row was written.
    expect(ComplianceReview::query()->count())->toBe(1);

    // Re-fetch so the assertions exercise read/hydration, not the in-memory create() values.
    $read = ComplianceReview::findOrFail($review->id);

    expect($read->customer_id)->toBe($customer->id)
        ->and($read->reason)->toBe(ComplianceReviewReason::EnhancedKycThreshold)          // enum cast on read
        ->and($read->threshold_kind)->toBe(ThresholdKind::SingleTransaction)
        ->and($read->tripped_currency)->toBe('EUR')
        ->and($read->resolved_at)->toBeNull();                                            // born open (NULL = open)

    // The stored backing values + the money scalar, read RAW via the DB facade (bypasses the model casts — an
    // Eloquent `->value()` would re-apply them and hand back the enum; a PG bigint reads back a numeric string,
    // so ->toEqual). A closure yields a fresh builder per read (the `ComplianceReviewModelTest` DB-facade idiom).
    $row = fn () => DB::table('parties_compliance_reviews')->where('id', $review->id);
    expect($row()->value('reason'))->toBe('enhanced_kyc_threshold')
        ->and($row()->value('threshold_kind'))->toBe('single_transaction')
        ->and($row()->value('tripped_amount_minor'))->toEqual(1_000_000);
});

it('persists the cumulative-annual threshold_kind with its tripping amount', function () {
    $customer = Customer::factory()->create();

    $review = app(CreateComplianceReview::class)->handle(
        customerId: $customer->id,
        reason: ComplianceReviewReason::EnhancedKycThreshold,
        thresholdKind: ThresholdKind::CumulativeAnnual,
        trippedAmount: Money::of(5_000_000, Currency::EUR),   // €50,000 — the rolling-12-month cumulative floor
    );

    $read = ComplianceReview::findOrFail($review->id);

    expect($read->threshold_kind)->toBe(ThresholdKind::CumulativeAnnual)
        ->and($read->tripped_currency)->toBe('EUR');

    $row = fn () => DB::table('parties_compliance_reviews')->where('id', $review->id);
    expect($row()->value('threshold_kind'))->toBe('cumulative_annual')
        ->and($row()->value('tripped_amount_minor'))->toEqual(5_000_000);
});

it('records no domain event — the detection action owns the escalation event', function () {
    $customer = Customer::factory()->create();

    // Capture AFTER the Customer fixture (which records nothing) so the delta isolates the action alone.
    $before = DomainEvent::count();

    app(CreateComplianceReview::class)->handle(
        customerId: $customer->id,
        reason: ComplianceReviewReason::EnhancedKycThreshold,
        thresholdKind: ThresholdKind::SingleTransaction,
        trippedAmount: Money::of(1_000_000, Currency::EUR),
    );

    // The writer is audit-only: it raises the review row but records no event of its own (design D5/D6).
    expect(DomainEvent::count())->toBe($before);
});
