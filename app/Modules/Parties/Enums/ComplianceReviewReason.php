<?php

namespace App\Modules\Parties\Enums;

/**
 * The reason a Compliance review-queue entry (`parties_compliance_reviews`) was raised
 * (change parties-enhanced-kyc-threshold, design D6; party-registry — Requirement:
 * Compliance Review Queue).
 *
 * The enhanced-KYC threshold breach — a Customer crossing €10k single-transaction OR €50k
 * rolling-trailing-12-month cumulative (DEC-035) — is the SOLE reason in this change. The
 * enum is deliberately extensible (future Compliance review triggers add cases here); the
 * value-set CHECK on `parties_compliance_reviews.reason` derives from cases() so it can
 * never drift from the enum.
 *
 * - case name    = the reason in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum ComplianceReviewReason: string
{
    case EnhancedKycThreshold = 'enhanced_kyc_threshold';
}
