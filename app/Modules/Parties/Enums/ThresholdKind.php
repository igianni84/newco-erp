<?php

namespace App\Modules\Parties\Enums;

/**
 * Which enhanced-KYC threshold a Customer tripped (change parties-enhanced-kyc-threshold,
 * design D6/D8; party-registry — Requirement: Enhanced-KYC Threshold Detection). DEC-035
 * defines two INDEPENDENT (OR) signals; this records which one fired — stamped on the
 * `parties_compliance_reviews.threshold_kind` column and carried on the
 * `CustomerEnhancedKycReviewRequired` event.
 *
 *   - SingleTransaction — a single transaction ≥ €10,000.
 *   - CumulativeAnnual  — the rolling trailing-12-month cumulative total ≥ €50,000.
 *
 * If both trip on the same scan, `single_transaction` is recorded (the more acute signal —
 * design D6). The value-set CHECK on the column derives from cases() so it can never drift
 * from the enum.
 *
 * - case name    = the threshold in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum ThresholdKind: string
{
    case SingleTransaction = 'single_transaction';
    case CumulativeAnnual = 'cumulative_annual';
}
