<?php

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ViewCustomer;
use App\Modules\Parties\Actions\CreateComplianceReview;
use App\Modules\Parties\Actions\EvaluateEnhancedKycThreshold;
use App\Modules\Parties\Actions\RecordCustomerScreening;
use App\Modules\Parties\Contracts\CustomerTransactionTotals;
use App\Modules\Parties\Contracts\CustomerTransactionTotalsReader;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Enums\ThresholdKind;
use App\Modules\Parties\Events\CustomerEnhancedKycReviewRequired;
use App\Modules\Parties\Events\CustomerOnboardingScreeningFailed;
use App\Modules\Parties\Events\CustomerOnboardingScreeningPassed;
use App\Modules\Parties\Events\CustomerRescreeningFailed;
use App\Modules\Parties\Events\CustomerRescreeningPassed;
use App\Modules\Parties\Models\ComplianceReview;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * The CLOSING integration proof + cross-engine close for the enhanced-KYC AML-threshold slice
 * (change parties-enhanced-kyc-threshold, task 7.1; design D1/D2/D6/D8; party-registry — all four requirements:
 * Enhanced-KYC Threshold Detection, Compliance Review Queue, and the MODIFIED Customer KYC Lifecycle + Customer
 * Sanctions Screening Lifecycle; DEC-035/DEC-030; AC-K-J-7a + AC-K-EVT-12a). Where {@see EvaluateEnhancedKycThresholdTest}
 * pins the DETECTION orchestrator in isolation and {@see ScanEnhancedKycThresholdsTest} pins the periodic trigger,
 * this one drives the WHOLE lifecycle END-TO-END through its REAL Actions — the breach detection AND the operator
 * resolution — and asserts the emergent event-SET of the slice as a whole (knowledge/testing/rules.md: "the closing
 * integration test of a module slice drives the chain THROUGH the Actions, never factories, and asserts the emergent
 * event-SET"; the {@see ComplianceChainTest} precedent).
 *
 * The chain: a Customer crossing a threshold is escalated by the genuine {@see EvaluateEnhancedKycThreshold} (fed by a
 * fake {@see CustomerTransactionTotalsReader} standing in for the deferred Module-S source — the only double; every
 * other collaborator is real: {@see CreateComplianceReview}, {@see RecordCustomerScreening},
 * the {@see DomainEventRecorder}), which latches the flag, opens a review, records the PII-free
 * {@see CustomerEnhancedKycReviewRequired}, and BLOCKS them via `under_review` / `aml_threshold`. Then the operator
 * clears the block through the genuine {@see RecordCustomerScreening} — a `passed` / `compliance_ad_hoc` re-screen,
 * EXACTLY what the {@see ViewCustomer}
 * surface invokes post-breach (design D2: once `last_screening_at` is set, `ViewCustomer::screeningSourceOptions()`
 * offers ONLY `compliance_ad_hoc` — never `aml_threshold`; the AML origin stays DURABLE on the review + the event),
 * recording {@see CustomerRescreeningPassed} — the SAME outcome event as the deferred 12-month cadence path (any
 * non-onboarding re-screen → `CustomerRescreening*`, regardless of trigger source; AC-K-EVT-12a).
 *
 * The emergent event-SET across the whole lifecycle is EXACTLY `{CustomerEnhancedKycReviewRequired,
 * CustomerRescreeningPassed}` — two distinct names, two rows. NOTE the "excludes any KYC event" from the task hint
 * means the KYC-FSM verbs (require/verify/reject) are event-silent (design L3 — none are called here); it does NOT
 * mean `%Kyc%`-free, because the escalation event's own name CONTAINS "Kyc" — so this file pins the SET (+ count),
 * never a `%Kyc%` exclusion.
 *
 * Uses {@see DatabaseMigrations} (NOT the directory's {@see RefreshDatabase} default;
 * the {@see EvaluateEnhancedKycThresholdTest} precedent + design "Nested transaction"): the escalation opens its OWN
 * `DB::transaction` and NESTS `RecordCustomerScreening`'s (a real PG savepoint), so `migrate:fresh` (no wrapper txn)
 * makes each Action's transaction the real OUTERMOST one — the faithful production commit shape for an invariant-grade
 * close, not a savepoint-under-savepoint-under-a-test-wrapper. This is the cross-engine gate (knowledge/testing/rules.md
 * "green on BOTH SQLite AND PostgreSQL 17"): events are asserted BY NAME + count (never a byte-compare of PG jsonb),
 * enum columns round-trip through the model casts, and money/bigint scalars are read raw via the DB facade
 * `->value('col')` and asserted with `->toEqual` (a `bigInteger` reads back a numeric STRING on PostgreSQL — never
 * `->toBe`).
 */
uses(DatabaseMigrations::class);

/**
 * Bind a fake {@see CustomerTransactionTotalsReader} returning caller-set EUR figures — the stand-in for the deferred
 * Module-S spend source (invariant 10; design D4). Uniquely named (NOT the sibling {@see bindCustomerTotals} /
 * {@see bindScanTotals}) so the full-suite load never hits a redeclaration fatal — every top-level test `function`
 * shares ONE global namespace (knowledge/testing/rules.md). MUST be called BEFORE resolving the Action so the
 * container injects this fake, not the launch-time null adapter. The fake is never PERSISTED (a container binding, not
 * a column value), so the anonymous class is PG-safe (the NUL-byte anonymous-name trap only bites a stored identity).
 */
function bindEnhancedKycChainTotals(Money $largestSingle, Money $trailingCumulative): void
{
    app()->bind(CustomerTransactionTotalsReader::class, fn (): CustomerTransactionTotalsReader => new class($largestSingle, $trailingCumulative) implements CustomerTransactionTotalsReader
    {
        public function __construct(
            private readonly Money $largestSingle,
            private readonly Money $trailingCumulative,
        ) {}

        public function forCustomer(int $customerId): CustomerTransactionTotals
        {
            return new CustomerTransactionTotals($this->largestSingle, $this->trailingCumulative);
        }
    });
}

it('drives the whole enhanced-KYC lifecycle — a single-transaction breach escalates + blocks, an operator re-screen clears it — with an emergent event-SET of exactly the escalation + the re-screen completion', function () {
    $customer = Customer::factory()->create();
    expect($customer->enhanced_kyc_flag)->toBeNull()
        ->and($customer->sanctions_status)->toBeNull();   // un-flagged, un-screened birth (DEC-071)

    // ─── Leg 1: the breach, through the REAL EvaluateEnhancedKycThreshold + its real collaborators ───────────────────
    // A largest single transaction EXACTLY at the €10k floor (inclusive ≥), cumulative below €50k.
    bindEnhancedKycChainTotals(
        largestSingle: Money::of(1_000_000, Currency::EUR),
        trailingCumulative: Money::of(1_000_000, Currency::EUR),
    );

    app(EvaluateEnhancedKycThreshold::class)->handle($customer->id);

    // The escalation latched the flag + stamp, opened one review, and BLOCKED via under_review + aml_threshold (the
    // sole sanctions-writer) — the Customer is non-clean until Compliance resolves it (design D2; invariant 7).
    $breached = Customer::findOrFail($customer->id);
    expect($breached->enhanced_kyc_flag)->toBeTrue()
        ->and($breached->enhanced_kyc_at)->not->toBeNull()
        ->and($breached->kyc_status)->toBeNull()                                            // orthogonal to the KYC FSM (§ 9.1)
        ->and($breached->sanctions_status)->toBe(SanctionsStatus::UnderReview)
        ->and($breached->screening_trigger_source)->toBe(ScreeningTriggerSource::AmlThreshold)
        ->and($breached->last_screening_at)->not->toBeNull();

    $review = ComplianceReview::query()->where('customer_id', $customer->id)->sole();
    expect($review->threshold_kind)->toBe(ThresholdKind::SingleTransaction)
        ->and($review->tripped_currency)->toBe('EUR')
        ->and($review->resolved_at)->toBeNull();                                            // born open (NULL = open, design D6)
    expect(DB::table('parties_compliance_reviews')->where('id', $review->id)->value('tripped_amount_minor'))
        ->toEqual(1_000_000);   // PG bigint reads back a numeric string → ->toEqual

    // The event-SET at breach is EXACTLY the one escalation event — NO CustomerRescreening* completion at initiation
    // (under_review is not a completion, so the sole sanctions-writer records no event yet — task hint: "no
    // CustomerRescreening* before resolution").
    expect(DomainEvent::query()->distinct()->pluck('name')->all())
        ->toEqualCanonicalizing([CustomerEnhancedKycReviewRequired::NAME]);

    // ─── Leg 2: the operator resolution, through the REAL RecordCustomerScreening ────────────────────────────────────
    // Post-breach the console offers ONLY compliance_ad_hoc (last_screening_at is set — ViewCustomer::screeningSourceOptions;
    // design D2), so the operator clears the block with exactly this call. The trigger_source REVERTS to compliance_ad_hoc
    // (the design D2 landmine — the surface never re-offers aml_threshold); the AML origin stays durable on the review + event.
    app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::Passed, ScreeningTriggerSource::ComplianceAdHoc);

    $resolved = Customer::findOrFail($customer->id);
    expect($resolved->sanctions_status)->toBe(SanctionsStatus::Passed)                      // un-blocked
        ->and($resolved->screening_trigger_source)->toBe(ScreeningTriggerSource::ComplianceAdHoc)  // D2: reverts to ad-hoc
        // The enhanced-KYC latch is DURABLE: the sanctions resolution touches NEITHER the flag NOR the review row (the
        // review-queue resolve action is deferred, § 9.1) — the two are decoupled from the sanctions clear (design D2/D6).
        ->and($resolved->enhanced_kyc_flag)->toBeTrue();
    expect(ComplianceReview::query()->where('customer_id', $customer->id)->sole()->resolved_at)->toBeNull();

    // The resolution records the RE-SCREEN PASSED completion — the SAME outcome event as the deferred cadence path (any
    // non-onboarding re-screen → CustomerRescreening*, AC-K-EVT-12a); its PII-free payload reflects the POST-screening state.
    $rescreen = DomainEvent::query()->where('name', CustomerRescreeningPassed::NAME)->sole();
    expect($rescreen->entity_type)->toBe('Customer')
        ->and($rescreen->entity_id)->toBe((string) $customer->id)
        ->and($rescreen->actor_role)->toBe(ActorRole::System)                               // resolved from the ActorContext seam
        ->and($rescreen->payload['sanctions_status'])->toBe(SanctionsStatus::Passed->value)
        ->and($rescreen->payload['trigger_source'])->toBe(ScreeningTriggerSource::ComplianceAdHoc->value);

    // No OTHER screening completion family leaked: the resolution is a re-screen (last_screening_at was already set by
    // the AML under_review write, so never onboarding) and it PASSED (never failed).
    expect(DomainEvent::query()->where('name', CustomerOnboardingScreeningPassed::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', CustomerOnboardingScreeningFailed::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', CustomerRescreeningFailed::NAME)->count())->toBe(0);

    // THE emergent event-SET across the whole lifecycle: exactly {escalation, re-screen completion} — two distinct
    // names, two rows, nothing extraneous (the closing-integration set assertion — one guarantee that every evented
    // step fired AND nothing leaked; knowledge/testing/rules.md).
    expect(DomainEvent::query()->distinct()->pluck('name')->all())
        ->toEqualCanonicalizing([CustomerEnhancedKycReviewRequired::NAME, CustomerRescreeningPassed::NAME]);
    expect(DomainEvent::query()->count())->toBe(2)
        ->and(DomainEvent::query()->where('module', 'parties')->count())->toBe(2);
});

it('reaches the identical closing outcome through the cumulative €50k path — both threshold paths converge on one lifecycle', function () {
    $customer = Customer::factory()->create();

    // No single transaction reaches €10k, but the rolling trailing-12-month cumulative is at the €50k floor.
    bindEnhancedKycChainTotals(
        largestSingle: Money::of(900_000, Currency::EUR),
        trailingCumulative: Money::of(5_000_000, Currency::EUR),
    );

    app(EvaluateEnhancedKycThreshold::class)->handle($customer->id);

    // The differentiator from the single path: the review records the CUMULATIVE trigger + its €50k amount.
    $review = ComplianceReview::query()->where('customer_id', $customer->id)->sole();
    expect($review->threshold_kind)->toBe(ThresholdKind::CumulativeAnnual);
    expect(DB::table('parties_compliance_reviews')->where('id', $review->id)->value('tripped_amount_minor'))
        ->toEqual(5_000_000);

    // Same block + same single escalation event at breach as the single path.
    expect(Customer::findOrFail($customer->id)->sanctions_status)->toBe(SanctionsStatus::UnderReview);
    expect(DomainEvent::query()->distinct()->pluck('name')->all())
        ->toEqualCanonicalizing([CustomerEnhancedKycReviewRequired::NAME]);

    // Same operator resolution → same emergent event-SET (AC-K-J-7a "both paths, identical state" holds through the
    // whole lifecycle, not just at detection).
    app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::Passed, ScreeningTriggerSource::ComplianceAdHoc);

    expect(Customer::findOrFail($customer->id)->sanctions_status)->toBe(SanctionsStatus::Passed);
    expect(DomainEvent::query()->distinct()->pluck('name')->all())
        ->toEqualCanonicalizing([CustomerEnhancedKycReviewRequired::NAME, CustomerRescreeningPassed::NAME]);
    expect(DomainEvent::query()->count())->toBe(2);
});

it('does not re-escalate or re-block a Customer already cleared by Compliance — the flag latch survives the sanctions resolution (idempotency)', function () {
    $customer = Customer::factory()->create();

    // Above BOTH thresholds — and the reader KEEPS reporting the breach on every scan (a real spend history does not shrink).
    bindEnhancedKycChainTotals(
        largestSingle: Money::of(1_000_000, Currency::EUR),
        trailingCumulative: Money::of(5_000_000, Currency::EUR),
    );

    // Breach → resolve — the full lifecycle.
    app(EvaluateEnhancedKycThreshold::class)->handle($customer->id);
    app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::Passed, ScreeningTriggerSource::ComplianceAdHoc);
    expect(Customer::findOrFail($customer->id)->sanctions_status)->toBe(SanctionsStatus::Passed);

    // The daily scan runs AGAIN, reader still breaching. The idempotency latch is `enhanced_kyc_flag` (design D1) — NOT
    // the sanctions status — so a Compliance-cleared Customer is NOT re-escalated and, crucially, NOT bounced back into
    // under_review. This is the compliance-UX guarantee: resolving the re-screen does not re-open the Customer to a
    // nightly-scan re-block. (A property neither the detection-unit test nor the console tests can express.)
    app(EvaluateEnhancedKycThreshold::class)->handle($customer->id);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->sanctions_status)->toBe(SanctionsStatus::Passed)                         // still cleared — NOT re-blocked
        ->and($fresh->screening_trigger_source)->toBe(ScreeningTriggerSource::ComplianceAdHoc)
        ->and($fresh->enhanced_kyc_flag)->toBeTrue();

    // No second review, no third event: exactly the one review + the two lifecycle events survive the re-scan.
    expect(ComplianceReview::query()->where('customer_id', $customer->id)->count())->toBe(1);
    expect(DomainEvent::query()->distinct()->pluck('name')->all())
        ->toEqualCanonicalizing([CustomerEnhancedKycReviewRequired::NAME, CustomerRescreeningPassed::NAME]);
    expect(DomainEvent::query()->count())->toBe(2);
});
