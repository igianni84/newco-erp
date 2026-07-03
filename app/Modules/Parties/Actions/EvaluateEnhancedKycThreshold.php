<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Contracts\CustomerTransactionTotalsReader;
use App\Modules\Parties\Enums\ComplianceReviewReason;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Enums\ThresholdKind;
use App\Modules\Parties\Events\CustomerEnhancedKycReviewRequired;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Detects an enhanced-KYC AML-threshold crossing for one Customer and, on the FIRST crossing, escalates them to
 * enhanced review — idempotently (change parties-enhanced-kyc-threshold, design D1/D2/D8; party-registry —
 * Requirement: Enhanced-KYC Threshold Detection; DEC-035/DEC-030).
 *
 * This is the single detection unit both trigger paths call: the daily periodic scan (task 5.1) and the future
 * at-order-completion check (Module S, deferred) both invoke {@see handle()}, so "both paths, identical state"
 * (AC-K-J-7a) holds BY CONSTRUCTION — there is no per-path detection logic to diverge. The action reads spend only
 * through the within-module {@see CustomerTransactionTotalsReader} read-port (invariant 10 — never a cross-module
 * query into Module S), which is null-bound at launch, so detection is a correct NO-OP until Module S ships the real
 * adapter (design D4).
 *
 * The two thresholds are INDEPENDENT OR signals in EUR minor units (design D8; no floats — invariant 6): a single
 * completed transaction ≥ €10,000, OR a rolling trailing-12-month cumulative ≥ €50,000. The boundary is INCLUSIVE
 * (≥) — a compliance floor triggers AT the threshold. If BOTH trip on one scan, `single_transaction` is recorded
 * (the more acute signal — design D6).
 *
 * Idempotency is latched on `enhanced_kyc_flag` under a transaction-locked re-read (design D1): the whole escalation
 * runs inside ONE {@see DB::transaction} against a `lockForUpdate()` Customer, so a periodic-scan / order-completion
 * race serializes on the row lock and the escalation fires AT MOST ONCE. Already flagged, or sub-threshold, → a pure
 * no-op (no second review-queue entry, no second event, no second sanctions write).
 *
 * On a first crossing the escalation performs four writes atomically, in order:
 *   (a) latch `enhanced_kyc_flag = true` + stamp `enhanced_kyc_at` (the event's single source of truth for the moment);
 *   (b) raise exactly one open Compliance review-queue entry via {@see CreateComplianceReview} (the sole row writer);
 *   (c) record the PII-free {@see CustomerEnhancedKycReviewRequired} event through the {@see DomainEventRecorder}
 *       (actor resolved from the {@see ActorContext} seam — System on the console/scan tick), the audit anchor + the
 *       future-consumer seam (design D5);
 *   (d) initiate the lightweight AML re-screen through {@see RecordCustomerScreening} (the SOLE sanctions-writer) —
 *       an `under_review` verdict with `trigger_source = aml_threshold`, which makes the Customer non-clean and
 *       BLOCKS them from transacting until Compliance resolves it (design D2; invariant 7 — a compliance gate, never
 *       auto-lifted). `under_review` is not a completion, so it records NO screening event at initiation; the
 *       operator resolution later records the matching `CustomerRescreening{Passed,Failed}` (the same outcome events
 *       as the cadence path). A never-screened Customer (`sanctions_status` NULL) crossing the floor is admissibly
 *       flagged `under_review` — only the `onboarding` source is from-state guarded in the sanctions writer.
 *
 * `RecordCustomerScreening` opens its OWN transaction (a PG savepoint under this one), so the four writes commit or
 * roll back together — the whole escalation is atomic. The escalation's effects are observable via the Customer flag,
 * the review queue and the event log; the method returns nothing (a fire-and-forget side-effecting orchestrator).
 */
class EvaluateEnhancedKycThreshold
{
    /** €10,000 in EUR minor units — the single-transaction enhanced-KYC floor (DEC-035; design D8). */
    private const SINGLE_TRANSACTION_THRESHOLD_MINOR = 1_000_000;

    /** €50,000 in EUR minor units — the rolling trailing-12-month cumulative floor (DEC-035; design D8). */
    private const CUMULATIVE_ANNUAL_THRESHOLD_MINOR = 5_000_000;

    public function __construct(
        private readonly CustomerTransactionTotalsReader $totals,
        private readonly CreateComplianceReview $createReview,
        private readonly RecordCustomerScreening $recordScreening,
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $customerId): void
    {
        DB::transaction(function () use ($customerId): void {
            // Transaction-locked re-read: the idempotency latch reads and writes `enhanced_kyc_flag` under a real row
            // lock (a no-op on SQLite's single writer), so a periodic-scan / order-completion race serializes here.
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

            // Idempotent no-op once escalated (design D1): a re-scan of a still-above-threshold Customer changes
            // nothing. The flag is the latch — the escalation below fires at most once per Customer.
            if ($customer->enhanced_kyc_flag === true) {
                return;
            }

            // Spend arrives as two EUR Money figures through the within-module read-port (invariant 10; zero-bound
            // until Module S lands → a correct no-op at launch). The two are independent OR signals (DEC-035).
            $totals = $this->totals->forCustomer($customerId);

            $singleBreached = $this->meetsOrExceeds(
                $totals->largestSingleTransaction,
                Money::of(self::SINGLE_TRANSACTION_THRESHOLD_MINOR, Currency::EUR),
            );
            $cumulativeBreached = $this->meetsOrExceeds(
                $totals->trailingTwelveMonthCumulative,
                Money::of(self::CUMULATIVE_ANNUAL_THRESHOLD_MINOR, Currency::EUR),
            );

            if (! $singleBreached && ! $cumulativeBreached) {
                return;   // sub-threshold — nothing to escalate
            }

            // Single wins if both trip on the same scan (the more acute signal — design D6): the recorded
            // `threshold_kind` and tripping amount are the single-transaction pair, else the cumulative pair.
            if ($singleBreached) {
                $thresholdKind = ThresholdKind::SingleTransaction;
                $trippedAmount = $totals->largestSingleTransaction;
            } else {
                $thresholdKind = ThresholdKind::CumulativeAnnual;
                $trippedAmount = $totals->trailingTwelveMonthCumulative;
            }

            // (a) Latch the flag + stamp the moment (the event reads `enhanced_kyc_at` back off the Customer).
            $customer->update([
                'enhanced_kyc_flag' => true,
                'enhanced_kyc_at' => CarbonImmutable::now(),
            ]);

            // (b) Raise exactly one open Compliance review-queue entry (the sole row writer; records no event).
            $review = $this->createReview->handle(
                customerId: $customerId,
                reason: ComplianceReviewReason::EnhancedKycThreshold,
                thresholdKind: $thresholdKind,
                trippedAmount: $trippedAmount,
            );

            // (c) Record the PII-free escalation event — the audit anchor + the future-consumer seam (design D5).
            // A root event (no causation): a scan-detected breach is not a cascade step in this slice.
            $this->recorder->record(
                name: CustomerEnhancedKycReviewRequired::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: CustomerEnhancedKycReviewRequired::ENTITY_TYPE,
                entityId: (string) $customer->id,
                payload: CustomerEnhancedKycReviewRequired::payload($customer, $review),
            );

            // (d) Initiate the lightweight AML re-screen through the SOLE sanctions-writer: `under_review` +
            // `aml_threshold` blocks the Customer until Compliance resolves it (design D2; invariant 7). It records
            // no completion event (under_review is not a completion); the resolution records the CustomerRescreening*.
            $this->recordScreening->handle(
                $customerId,
                SanctionsStatus::UnderReview,
                ScreeningTriggerSource::AmlThreshold,
            );
        });
    }

    /**
     * `$amount ≥ $threshold`, inclusive (design D8 — a compliance floor triggers AT the threshold), computed on
     * integer minor units through {@see Money::minus()} so the comparison is FAIL-CLOSED on currency: a non-EUR
     * figure from a mis-implemented reader throws rather than silently comparing raw minor units across currencies
     * (invariant 6). Both figures are EUR by the reader's contract, so this never throws in practice.
     */
    private function meetsOrExceeds(Money $amount, Money $threshold): bool
    {
        return $amount->minus($threshold)->minorUnits >= 0;
    }
}
