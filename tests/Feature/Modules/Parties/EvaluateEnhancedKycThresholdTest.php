<?php

use App\Modules\Parties\Actions\CreateComplianceReview;
use App\Modules\Parties\Actions\EvaluateEnhancedKycThreshold;
use App\Modules\Parties\Actions\RecordCustomerScreening;
use App\Modules\Parties\Contracts\CustomerTransactionTotals;
use App\Modules\Parties\Contracts\CustomerTransactionTotalsReader;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Enums\ThresholdKind;
use App\Modules\Parties\Events\CustomerEnhancedKycReviewRequired;
use App\Modules\Parties\Events\CustomerRescreeningFailed;
use App\Modules\Parties\Events\CustomerRescreeningPassed;
use App\Modules\Parties\Models\ComplianceReview;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\ActorContext;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * The full-workflow proof of the enhanced-KYC AML-threshold detection orchestrator {@see EvaluateEnhancedKycThreshold}
 * (change parties-enhanced-kyc-threshold, task 4.2; design D1/D2/D8; party-registry — Requirement: Enhanced-KYC
 * Threshold Detection; DEC-035/DEC-030). It drives the workflow through its REAL collaborators — a fake
 * {@see CustomerTransactionTotalsReader} standing in for the deferred Module-S source (bound before the Action is
 * resolved), and the genuine {@see CreateComplianceReview} + {@see RecordCustomerScreening}
 * + {@see DomainEventRecorder} — so every assertion observes real substrate behaviour.
 *
 * The invariants pinned here (design D1/D2/D6/D8):
 *   - a single completed transaction ≥ €10,000 escalates (the €10k floor is INCLUSIVE — a compliance floor triggers
 *     AT the threshold); a rolling trailing-12-month cumulative ≥ €50,000 escalates identically; the two are
 *     independent OR signals; and when BOTH trip, `single_transaction` is recorded (the more acute signal);
 *   - an escalation performs the four writes atomically — `enhanced_kyc_flag` + `enhanced_kyc_at`, one open
 *     `parties_compliance_reviews` entry (the right `threshold_kind` + amount), the PII-free
 *     `CustomerEnhancedKycReviewRequired` event, and `sanctions_status = under_review` / `trigger_source =
 *     aml_threshold` — and NO `CustomerRescreening*` completion event fires at initiation (the event-SET is exactly
 *     `{CustomerEnhancedKycReviewRequired}`);
 *   - detection is idempotent, latched on `enhanced_kyc_flag` — a re-scan of a still-above-threshold Customer is a
 *     pure no-op (no second review entry, no second event); and a sub-threshold Customer does nothing;
 *   - the escalation is ONE transaction: a throw at the last write (the AML re-screen) rolls back ALL of it.
 *
 * Uses {@see DatabaseMigrations} (NOT the directory's {@see RefreshDatabase} default)
 * so the Action's `DB::transaction` is the real OUTERMOST transaction with real COMMIT/ROLLBACK — the design mandates
 * this to prove the nested-transaction atomicity faithfully (design.md "Nested transaction": *"whole escalation
 * commits/rolls back atomically; prove with DatabaseMigrations + a rollback test"*), rather than a savepoint under a
 * test wrapper. Money/bigint scalars are read raw via the DB facade `->value('col')` and asserted with `->toEqual`
 * (a `bigInteger` reads back as a numeric STRING on PostgreSQL — never `->toBe`).
 */
uses(DatabaseMigrations::class);

/**
 * Bind a fake totals reader returning caller-set EUR figures — the stand-in for the deferred Module-S source. MUST be
 * called BEFORE resolving the Action so the container injects this fake (not the null adapter).
 */
function bindCustomerTotals(Money $largestSingle, Money $trailingCumulative): void
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

/** €10,000 — the single-transaction floor. */
function eur10k(): Money
{
    return Money::of(1_000_000, Currency::EUR);
}

/** €50,000 — the rolling-12-month cumulative floor. */
function eur50k(): Money
{
    return Money::of(5_000_000, Currency::EUR);
}

/** €0 EUR — the null-adapter / below-any-threshold figure. */
function eurZero(): Money
{
    return Money::of(0, Currency::EUR);
}

it('escalates on a single transaction at the €10k floor (inclusive): flag, review entry, PII-free event, and an aml_threshold under_review re-screen', function () {
    $customer = Customer::factory()->create();
    expect($customer->enhanced_kyc_flag)->toBeNull()
        ->and($customer->sanctions_status)->toBeNull();   // un-flagged, un-screened birth (DEC-071)

    // A largest single transaction EXACTLY at the €10k floor (inclusive ≥), cumulative below €50k.
    bindCustomerTotals(largestSingle: eur10k(), trailingCumulative: eur10k());

    app(EvaluateEnhancedKycThreshold::class)->handle($customer->id);

    // (a) flag + timestamp latched.
    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->enhanced_kyc_flag)->toBeTrue()
        ->and($fresh->enhanced_kyc_at)->not->toBeNull()
        // orthogonal to the KYC FSM — the flag does not move kyc_status (design; § 9.1).
        ->and($fresh->kyc_status)->toBeNull()
        // (d) the AML re-screen blocks: under_review + aml_threshold through the sole sanctions-writer.
        ->and($fresh->sanctions_status)->toBe(SanctionsStatus::UnderReview)
        ->and($fresh->screening_trigger_source)->toBe(ScreeningTriggerSource::AmlThreshold)
        ->and($fresh->last_screening_at)->not->toBeNull();

    // (b) exactly one OPEN review-queue entry recording the single-transaction trigger + amount.
    expect(ComplianceReview::query()->count())->toBe(1);
    $review = ComplianceReview::query()->where('customer_id', $customer->id)->sole();
    expect($review->threshold_kind)->toBe(ThresholdKind::SingleTransaction)
        ->and($review->tripped_currency)->toBe('EUR')
        ->and($review->resolved_at)->toBeNull();
    expect(DB::table('parties_compliance_reviews')->where('id', $review->id)->value('tripped_amount_minor'))
        ->toEqual(1_000_000);   // PG bigint reads back a numeric string → ->toEqual

    // (c) exactly the ONE escalation event — the event-SET excludes any CustomerRescreening* (under_review is not a
    // completion, so no screening completion event fires at initiation).
    expect(DomainEvent::query()->pluck('name')->unique()->values()->all())
        ->toEqualCanonicalizing([CustomerEnhancedKycReviewRequired::NAME]);
    $event = DomainEvent::query()->where('name', CustomerEnhancedKycReviewRequired::NAME)->sole();
    expect($event->entity_type)->toBe('Customer')
        ->and($event->entity_id)->toBe((string) $customer->id)
        // actor resolved from the ActorContext seam (System on the scan/console tick — no operator authenticated).
        ->and($event->actor_role)->toBe(ActorRole::System)
        ->and($event->payload['customer_id'])->toBe($customer->id)
        ->and($event->payload['threshold_kind'])->toBe(ThresholdKind::SingleTransaction->value);
});

it('escalates on a rolling-12-month cumulative ≥ €50k reached via sub-threshold transactions', function () {
    $customer = Customer::factory()->create();

    // No single transaction reaches €10k, but the rolling cumulative is at the €50k floor.
    bindCustomerTotals(largestSingle: Money::of(900_000, Currency::EUR), trailingCumulative: eur50k());

    app(EvaluateEnhancedKycThreshold::class)->handle($customer->id);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->enhanced_kyc_flag)->toBeTrue()
        ->and($fresh->enhanced_kyc_at)->not->toBeNull()
        ->and($fresh->sanctions_status)->toBe(SanctionsStatus::UnderReview)
        ->and($fresh->screening_trigger_source)->toBe(ScreeningTriggerSource::AmlThreshold);

    // The review records the cumulative trigger + its €50k amount.
    $review = ComplianceReview::query()->where('customer_id', $customer->id)->sole();
    expect($review->threshold_kind)->toBe(ThresholdKind::CumulativeAnnual);
    expect(DB::table('parties_compliance_reviews')->where('id', $review->id)->value('tripped_amount_minor'))
        ->toEqual(5_000_000);

    // Same single escalation event, no CustomerRescreening* at initiation.
    expect(DomainEvent::query()->pluck('name')->unique()->values()->all())
        ->toEqualCanonicalizing([CustomerEnhancedKycReviewRequired::NAME]);
    expect(DomainEvent::query()->where('name', CustomerEnhancedKycReviewRequired::NAME)->sole()->payload['threshold_kind'])
        ->toBe(ThresholdKind::CumulativeAnnual->value);
});

it('records the single_transaction trigger (the more acute signal) when both thresholds trip on one scan', function () {
    $customer = Customer::factory()->create();

    // Both breach: a €12k single AND a €60k cumulative. Single wins (design D6).
    bindCustomerTotals(
        largestSingle: Money::of(1_200_000, Currency::EUR),
        trailingCumulative: Money::of(6_000_000, Currency::EUR),
    );

    app(EvaluateEnhancedKycThreshold::class)->handle($customer->id);

    $review = ComplianceReview::query()->where('customer_id', $customer->id)->sole();
    expect($review->threshold_kind)->toBe(ThresholdKind::SingleTransaction);
    // The tripping amount is the single-transaction figure (matching the recorded kind), not the cumulative.
    expect(DB::table('parties_compliance_reviews')->where('id', $review->id)->value('tripped_amount_minor'))
        ->toEqual(1_200_000);
    expect(DomainEvent::query()->where('name', CustomerEnhancedKycReviewRequired::NAME)->sole()->payload['threshold_kind'])
        ->toBe(ThresholdKind::SingleTransaction->value);
});

it('is a pure no-op on a re-scan of an already-flagged Customer (idempotent latch)', function () {
    $customer = Customer::factory()->create();
    bindCustomerTotals(largestSingle: eur10k(), trailingCumulative: eur50k());   // above BOTH thresholds

    // First scan escalates.
    app(EvaluateEnhancedKycThreshold::class)->handle($customer->id);
    expect(ComplianceReview::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerEnhancedKycReviewRequired::NAME)->count())->toBe(1);

    // A second scan — still above threshold — must change NOTHING (the flag is the latch). Even with a reader still
    // reporting a breach, the escalation cannot re-fire: no second review entry, no second event, no second
    // sanctions write (the guard returns before step d, so the review/event counts staying at 1 prove it).
    app(EvaluateEnhancedKycThreshold::class)->handle($customer->id);

    expect(ComplianceReview::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerEnhancedKycReviewRequired::NAME)->count())->toBe(1);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->enhanced_kyc_flag)->toBeTrue()
        ->and($fresh->sanctions_status)->toBe(SanctionsStatus::UnderReview)
        ->and($fresh->screening_trigger_source)->toBe(ScreeningTriggerSource::AmlThreshold);
});

it('does nothing for a sub-threshold Customer (below both floors)', function () {
    $customer = Customer::factory()->create();

    // Just below BOTH floors (€9,999.99 single, €49,999.99 cumulative) — pins the inclusive boundary from below.
    bindCustomerTotals(
        largestSingle: Money::of(999_999, Currency::EUR),
        trailingCumulative: Money::of(4_999_999, Currency::EUR),
    );

    app(EvaluateEnhancedKycThreshold::class)->handle($customer->id);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->enhanced_kyc_flag)->toBeNull()          // untouched (never set)
        ->and($fresh->enhanced_kyc_at)->toBeNull()
        ->and($fresh->sanctions_status)->toBeNull()        // no AML re-screen initiated
        ->and($fresh->screening_trigger_source)->toBeNull();
    expect(ComplianceReview::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('the null-adapter zero totals detect nothing (the launch no-op)', function () {
    $customer = Customer::factory()->create();
    bindCustomerTotals(largestSingle: eurZero(), trailingCumulative: eurZero());

    app(EvaluateEnhancedKycThreshold::class)->handle($customer->id);

    expect(Customer::findOrFail($customer->id)->enhanced_kyc_flag)->toBeNull()
        ->and(ComplianceReview::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rolls back the ENTIRE escalation atomically when the AML re-screen (the last write) throws', function () {
    $customer = Customer::factory()->create();
    bindCustomerTotals(largestSingle: eur10k(), trailingCumulative: eur50k());

    // Replace the sole sanctions-writer (step d) with one that throws — after the flag, review and event writes
    // (steps a–c) have already run inside the Action's transaction. A pure-PHP throwing subclass (the repo uses no
    // Mockery); its `handle` signature mirrors the parent exactly.
    app()->bind(RecordCustomerScreening::class, fn (): RecordCustomerScreening => new class(app(DomainEventRecorder::class), app(ActorContext::class)) extends RecordCustomerScreening
    {
        public function handle(int $customerId, SanctionsStatus $verdict, ScreeningTriggerSource $source): Customer
        {
            throw new RuntimeException('forced failure at the AML re-screen step (atomicity probe)');
        }
    });

    expect(fn () => app(EvaluateEnhancedKycThreshold::class)->handle($customer->id))
        ->toThrow(RuntimeException::class);

    // The whole DB::transaction rolled back: none of steps a–c persisted.
    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->enhanced_kyc_flag)->toBeNull()          // (a) flag not latched
        ->and($fresh->enhanced_kyc_at)->toBeNull()
        ->and($fresh->sanctions_status)->toBeNull();       // (d) never reached a durable state
    expect(ComplianceReview::query()->count())->toBe(0)    // (b) no review row
        ->and(DomainEvent::query()->where('name', CustomerEnhancedKycReviewRequired::NAME)->count())->toBe(0);   // (c) no event
});

// Pin that the never-fired completion events stay absent across the whole file (belt-and-braces on the event-SET
// assertions above): the enhanced-KYC initiation records neither rescreening completion.
it('never records a CustomerRescreening completion event at breach initiation', function () {
    $customer = Customer::factory()->create();
    bindCustomerTotals(largestSingle: eur10k(), trailingCumulative: eurZero());

    app(EvaluateEnhancedKycThreshold::class)->handle($customer->id);

    expect(DomainEvent::query()->where('name', CustomerRescreeningPassed::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', CustomerRescreeningFailed::NAME)->count())->toBe(0);
});
