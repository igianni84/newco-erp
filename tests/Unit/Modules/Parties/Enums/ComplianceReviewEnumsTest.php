<?php

use App\Modules\Parties\Enums\ComplianceReviewReason;
use App\Modules\Parties\Enums\ThresholdKind;

// Pins the parties-enhanced-kyc-threshold enums (parties-enhanced-kyc-threshold, task 2.1;
// design D6/D8; DEC-035). The two Compliance review-queue value domains: ComplianceReviewReason
// is the extensible reason a `parties_compliance_reviews` row was raised (the enhanced-KYC
// threshold breach is the SOLE launch reason); ThresholdKind records WHICH of the two
// independent (OR) enhanced-KYC signals fired — a single transaction ≥ €10k or the rolling
// trailing-12-month cumulative ≥ €50k. Each is stamped on the review row and carried on the
// CustomerEnhancedKycReviewRequired event. Each case/value map is asserted verbatim and
// order-sensitive, mirroring ComplianceEnumsTest: because the value-set CHECK on
// `parties_compliance_reviews.{reason,threshold_kind}` (task 1.1) derives from cases(), any
// drift in a case or its persisted token must fail HERE first (before it silently reshapes
// the constraint).

it('backs ComplianceReviewReason with the sole enhanced-KYC-threshold reason', function () {
    $values = [];

    foreach (ComplianceReviewReason::cases() as $reason) {
        $values[$reason->name] = $reason->value;
    }

    expect($values)->toBe([
        'EnhancedKycThreshold' => 'enhanced_kyc_threshold',
    ]);

    expect(ComplianceReviewReason::cases())->toHaveCount(1);
});

it('backs ThresholdKind with the two independent enhanced-KYC signals', function () {
    $values = [];

    foreach (ThresholdKind::cases() as $kind) {
        $values[$kind->name] = $kind->value;
    }

    expect($values)->toBe([
        'SingleTransaction' => 'single_transaction',
        'CumulativeAnnual' => 'cumulative_annual',
    ]);

    expect(ThresholdKind::cases())->toHaveCount(2);
});

it('round-trips the spec tokens through from()', function () {
    expect(ComplianceReviewReason::from('enhanced_kyc_threshold'))->toBe(ComplianceReviewReason::EnhancedKycThreshold);
    expect(ThresholdKind::from('single_transaction'))->toBe(ThresholdKind::SingleTransaction);
    expect(ThresholdKind::from('cumulative_annual'))->toBe(ThresholdKind::CumulativeAnnual);
});

it('rejects a compliance-review reason outside the domain', function () {
    // The enhanced-KYC threshold is the sole launch reason; a future trigger adds a case here,
    // it is never an arbitrary token.
    expect(fn () => ComplianceReviewReason::from('manual_flag'))->toThrow(ValueError::class);
});

it('rejects a threshold kind outside the domain', function () {
    // DEC-035 defines exactly the €10k single / €50k rolling-12-month pair — there is no
    // monthly/quarterly/calendar-YTD window.
    expect(fn () => ThresholdKind::from('monthly_cumulative'))->toThrow(ValueError::class);
});
