<?php

namespace App\Modules\Parties\Reads;

use App\Modules\Parties\Contracts\CustomerTransactionTotals;
use App\Modules\Parties\Contracts\CustomerTransactionTotalsReader;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;

/**
 * The launch-time null {@see CustomerTransactionTotalsReader}: it returns EUR-zero totals for every Customer,
 * ignoring the id (parties-enhanced-kyc-threshold, design D4; party-registry — Requirement: Enhanced-KYC Threshold
 * Detection; DEC-035). The real reader reads Module S (Commerce) order/invoice EUR history, but Module S is a stub
 * (Build Workplan Phase 4) — so this adapter is bound in `PartiesServiceProvider` until Module S ships the real one,
 * holding the seam OPEN with the correct no-op semantics.
 *
 * With both figures zero, the €10k-single / €50k-cumulative detection (`EvaluateEnhancedKycThreshold`, task 4.2)
 * never trips, so the periodic scan runs but is inert: the whole enhanced-KYC path is wired end-to-end and safe to
 * ship ahead of its data source. The detection logic is proven meanwhile by binding a fake reader in tests.
 *
 * It is a pure constant — no Customer lookup, no database access, no injected dependency (contrast the
 * {@see DatabaseComplianceStatusReader}, which reads real rows) — so it references NOTHING under `App\Modules\Commerce`
 * and introduces no cross-module coupling (invariant 10; pinned by the arch assertion in the binding test). Stateless,
 * so a plain `bind` (fresh per resolve) is enough.
 */
class NullCustomerTransactionTotalsReader implements CustomerTransactionTotalsReader
{
    public function forCustomer(int $customerId): CustomerTransactionTotals
    {
        $zero = Money::of(0, Currency::EUR);

        return new CustomerTransactionTotals(
            largestSingleTransaction: $zero,
            trailingTwelveMonthCumulative: $zero,
        );
    }
}
