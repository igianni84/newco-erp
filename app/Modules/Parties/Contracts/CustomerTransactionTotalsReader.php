<?php

namespace App\Modules\Parties\Contracts;

use App\Platform\Money\Money;

/**
 * The within-module read-port through which enhanced-KYC threshold detection obtains a Customer's transaction
 * totals (parties-enhanced-kyc-threshold, design D4; party-registry — Requirement: Enhanced-KYC Threshold
 * Detection; DEC-035). It is Module K's sanctioned seam into Module S (Commerce) spend data — a small read
 * contract, NEVER a cross-module Eloquent query or join (invariant 10; the {@see PartyComplianceStatusReader}
 * precedent).
 *
 * {@see forCustomer()} returns the {@see CustomerTransactionTotals} tuple `(largestSingleTransaction,
 * trailingTwelveMonthCumulative)`, both EUR {@see Money}. The cumulative figure is a
 * **rolling trailing-12-month** total measured from the evaluation instant (standard AML; design D3), not a
 * calendar year-to-date figure — that window is this contract's semantic obligation, honoured by the real
 * implementation; the K-side detection only compares the returned figures to the €10k / €50k thresholds.
 *
 * The real implementation reads Module S order/invoice EUR history and is **deferred** — Module S is a stub
 * (Build Workplan Phase 4). At launch the interface is bound in `PartiesServiceProvider` to
 * `NullCustomerTransactionTotalsReader` (task 3.2), which returns zero EUR totals, so the periodic scan runs and
 * detection is a correct **no-op** until Module S provides the real adapter. The detection logic is fully tested
 * meanwhile by binding a fake reader.
 */
interface CustomerTransactionTotalsReader
{
    /**
     * The Customer's transaction-totals tuple: the largest single completed transaction and the rolling
     * trailing-12-month cumulative purchase total, both EUR.
     */
    public function forCustomer(int $customerId): CustomerTransactionTotals;
}
