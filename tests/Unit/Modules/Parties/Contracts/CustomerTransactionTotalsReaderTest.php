<?php

use App\Modules\Parties\Contracts\CustomerTransactionTotals;
use App\Modules\Parties\Contracts\CustomerTransactionTotalsReader;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;

// Pins the Module-S transaction-totals read-port seam (parties-enhanced-kyc-threshold task 3.1; design D3/D4;
// party-registry — Requirement: Enhanced-KYC Threshold Detection). This is a PURE value/contract test — no database
// and no app boot: the DTO and Money are plain immutable values, and the deferred Module-S implementation is stood in
// for by an in-test fake (the sanctioned module-boundary shape — a small read contract, never a cross-module query,
// invariant 10). Task 3.2 adds the zero-returning NullCustomerTransactionTotalsReader + the container binding.

$eur = fn (int $minor): Money => Money::of($minor, Currency::of('EUR'));

// A fake reader standing in for the deferred Module-S adapter: it returns exactly the totals the caller seeds, so the
// test asserts the contract round-trips the two EUR figures the detection workflow (task 4.2) will compare.
$readerReturning = fn (CustomerTransactionTotals $totals): CustomerTransactionTotalsReader => new class($totals) implements CustomerTransactionTotalsReader
{
    public function __construct(private CustomerTransactionTotals $totals) {}

    public function forCustomer(int $customerId): CustomerTransactionTotals
    {
        return $this->totals;
    }
};

it('yields the reader caller-set EUR totals through the DTO', function () use ($eur, $readerReturning) {
    $totals = new CustomerTransactionTotals(
        largestSingleTransaction: $eur(1_500_000),
        trailingTwelveMonthCumulative: $eur(6_200_000),
    );

    $read = $readerReturning($totals)->forCustomer(42);

    expect($read)->toBe($totals)
        ->and($read->largestSingleTransaction->equals($eur(1_500_000)))->toBeTrue()
        ->and($read->trailingTwelveMonthCumulative->equals($eur(6_200_000)))->toBeTrue()
        // Both figures are EUR Money (invariant 6) — the currency the €10k/€50k detection constants are held in.
        ->and($read->largestSingleTransaction->currency)->toBe(Currency::EUR)
        ->and($read->trailingTwelveMonthCumulative->currency)->toBe(Currency::EUR);
});

it('keeps the largest-single and trailing-12-month figures independent', function (int $single, int $cumulative) use ($eur, $readerReturning) {
    $read = $readerReturning(new CustomerTransactionTotals(
        largestSingleTransaction: $eur($single),
        trailingTwelveMonthCumulative: $eur($cumulative),
    ))->forCustomer(1);

    // The two OR-trigger figures are held distinctly — a field swap or alias would surface here.
    expect($read->largestSingleTransaction->minorUnits)->toBe($single)
        ->and($read->trailingTwelveMonthCumulative->minorUnits)->toBe($cumulative);
})->with([
    // cumulative ≥ single always holds (a single transaction contributes to the cumulative).
    'a €15k single within a €22k year (single-transaction trigger)' => [1_500_000, 2_200_000],
    'sub-€10k singles summing past €50k (cumulative trigger)' => [400_000, 5_400_000],
    'no activity (the null-adapter zero shape)' => [0, 0],
]);
