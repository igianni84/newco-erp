<?php

use App\Modules\Parties\Contracts\CustomerTransactionTotals;
use App\Modules\Parties\Contracts\CustomerTransactionTotalsReader;
use App\Modules\Parties\Contracts\PartyComplianceStatusReader;
use App\Modules\Parties\Reads\NullCustomerTransactionTotalsReader;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;

/**
 * Pins the launch-time transaction-totals seam (parties-enhanced-kyc-threshold task 3.2, design D4; party-registry
 * — Requirement: Enhanced-KYC Threshold Detection; DEC-035). The real {@see CustomerTransactionTotalsReader} reads
 * Module S (Commerce) EUR history and is DEFERRED — Module S is a Phase-4 stub — so `PartiesServiceProvider` binds
 * the interface to {@see NullCustomerTransactionTotalsReader}, which returns EUR-zero totals for every Customer.
 *
 * The invariants this file pins: (1) the contract resolves from the container to the null adapter (the
 * {@see PartyComplianceStatusReader} binding precedent); (2) it yields `0 EUR` for
 * BOTH figures regardless of the id — so the €10k-single / €50k-cumulative detection (`EvaluateEnhancedKycThreshold`,
 * task 4.2) is a correct no-op at launch; and (3) the adapter, being a pure constant, introduces NO cross-module
 * coupling — it references nothing under `App\Modules\Commerce` (invariant 10; the module-boundary law made
 * class-specific for this one Module-S seam, complementing the whole-module law in ModuleBoundariesTest).
 *
 * No RefreshDatabase: the null adapter performs no Customer lookup and touches no database (it ignores the id), so
 * there is nothing to migrate — booting the app (Feature → TestCase) to resolve the binding is the whole fixture.
 */
it('resolves the bound null adapter from the container', function () {
    expect(app(CustomerTransactionTotalsReader::class))
        ->toBeInstanceOf(NullCustomerTransactionTotalsReader::class);
});

it('returns zero EUR totals for both figures, regardless of the customer id', function (int $customerId) {
    $totals = app(CustomerTransactionTotalsReader::class)->forCustomer($customerId);

    $zeroEur = Money::of(0, Currency::EUR);

    expect($totals)->toBeInstanceOf(CustomerTransactionTotals::class)
        // largest single completed transaction — zero EUR (by component AND by value equality)...
        ->and($totals->largestSingleTransaction->minorUnits)->toBe(0)
        ->and($totals->largestSingleTransaction->currency)->toBe(Currency::EUR)
        ->and($totals->largestSingleTransaction->equals($zeroEur))->toBeTrue()
        // ...and the rolling trailing-12-month cumulative — likewise zero EUR, held independently.
        ->and($totals->trailingTwelveMonthCumulative->minorUnits)->toBe(0)
        ->and($totals->trailingTwelveMonthCumulative->currency)->toBe(Currency::EUR)
        ->and($totals->trailingTwelveMonthCumulative->equals($zeroEur))->toBeTrue();
})->with([
    // The adapter ignores the id (no lookup) — a real seeded id and a never-seeded one both return zero.
    'id 1' => [1],
    'a never-seeded id' => [999_999],
]);

it('holds no cross-module coupling — the null adapter references nothing under Module S (Commerce)', function () {
    expect(NullCustomerTransactionTotalsReader::class)
        ->not->toUse('App\\Modules\\Commerce');
});
