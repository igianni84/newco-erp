<?php

namespace App\Modules\Parties\Contracts;

use App\Platform\Money\Money;

/**
 * The PII-free transaction-totals tuple the enhanced-KYC threshold detection reads for one Customer
 * (parties-enhanced-kyc-threshold, design D3/D4; party-registry — Requirement: Enhanced-KYC Threshold Detection;
 * DEC-035). Returned by {@see CustomerTransactionTotalsReader} for a Customer scope; carries only the two
 * monetary figures the €10k-single / €50k-cumulative (independent OR) detection compares, never any personal data.
 *
 * Both figures are **EUR** {@see Money} (integer minor units + currency — invariant 6; no floats): the detection
 * workflow (`EvaluateEnhancedKycThreshold`, task 4.2) compares `largestSingleTransaction` against €10,000 and
 * `trailingTwelveMonthCumulative` against €50,000, both held as EUR minor-unit constants. The two are DISTINCT
 * signals — the largest *single* completed transaction versus the *rolling* cumulative total — so a Customer can
 * trip either independently (one €10k order, or many sub-threshold orders summing past €50k).
 *
 * `trailingTwelveMonthCumulative` is a **rolling trailing-12-month** total measured from the evaluation instant
 * (standard AML; design D3), NOT a calendar year-to-date figure — a Customer at €49k on 31 Dec does not reset to
 * €0 on 1 Jan. The rolling window is a semantic obligation of the {@see CustomerTransactionTotalsReader} contract
 * that the future Module-S implementation honours; Module K only compares the returned figure to €50k.
 *
 * A plain readonly carrier (the {@see ComplianceStatus} boundary-DTO precedent): it exposes {@see Money} values,
 * no persistence object and no PII, so it is safe to hand across the module boundary (invariant 10).
 */
class CustomerTransactionTotals
{
    public function __construct(
        public readonly Money $largestSingleTransaction,
        public readonly Money $trailingTwelveMonthCumulative,
    ) {}
}
