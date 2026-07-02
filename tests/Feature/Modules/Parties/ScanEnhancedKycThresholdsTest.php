<?php

use App\Modules\Parties\Actions\EvaluateEnhancedKycThreshold;
use App\Modules\Parties\Contracts\CustomerTransactionTotals;
use App\Modules\Parties\Contracts\CustomerTransactionTotalsReader;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Events\CustomerEnhancedKycReviewRequired;
use App\Modules\Parties\Models\ComplianceReview;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\DomainEvent;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

/**
 * Pins the periodic enhanced-KYC scan command `parties:scan-enhanced-kyc-thresholds` and its daily schedule entry
 * (change parties-enhanced-kyc-threshold, task 5.1; design D7; party-registry — Requirement: Enhanced-KYC Threshold
 * Detection). The command iterates every Customer and drives the REAL detection orchestrator
 * {@see EvaluateEnhancedKycThreshold} through its real collaborators; a PER-CUSTOMER fake
 * {@see CustomerTransactionTotalsReader} (keyed on id) stands in for the deferred Module-S source, so exactly the
 * intended Customer escalates while the rest stay untouched — the property a scan (many Customers) must have that the
 * single-Customer orchestrator test cannot express with its one-figure-for-all fake.
 *
 * Uses {@see DatabaseMigrations} (not the directory's {@see RefreshDatabase} default):
 * the command is invoked through Artisan::call(), so the orchestrator's per-Customer `DB::transaction` runs against
 * the live connection at transaction level 0 with real COMMIT — the SweepTest precedent for a command exercised via
 * Artisan.
 */
uses(DatabaseMigrations::class);

/**
 * Bind a PER-CUSTOMER fake totals reader: every id in $breachingIds reports a €10k single-transaction breach (the
 * inclusive floor); every other Customer reports €0 (below both floors). Keyed on the customer id — unlike the
 * EvaluateEnhancedKycThresholdTest fake, which returns one figure for ALL Customers — so one scan can flag some
 * Customers and leave others clean. MUST be bound BEFORE the command resolves the orchestrator.
 *
 * @param  list<int>  $breachingIds
 */
function bindScanTotals(array $breachingIds): void
{
    app()->bind(CustomerTransactionTotalsReader::class, fn (): CustomerTransactionTotalsReader => new class($breachingIds) implements CustomerTransactionTotalsReader
    {
        /** @param  list<int>  $breachingIds */
        public function __construct(private readonly array $breachingIds) {}

        public function forCustomer(int $customerId): CustomerTransactionTotals
        {
            $single = in_array($customerId, $this->breachingIds, true)
                ? Money::of(1_000_000, Currency::EUR)   // €10k single-transaction breach (the inclusive floor)
                : Money::of(0, Currency::EUR);

            return new CustomerTransactionTotals($single, Money::of(0, Currency::EUR));
        }
    });
}

it('escalates exactly the breaching Customer and leaves the rest untouched', function () {
    $breaching = Customer::factory()->create();
    $clean = Customer::factory()->create();

    bindScanTotals([$breaching->id]);

    expect(Artisan::call('parties:scan-enhanced-kyc-thresholds'))->toBe(0);

    // The breaching Customer is fully escalated: flag + timestamp, one open review, and the aml_threshold under_review
    // re-screen — the orchestrator's four writes, driven through the command.
    $freshBreaching = Customer::findOrFail($breaching->id);
    expect($freshBreaching->enhanced_kyc_flag)->toBeTrue()
        ->and($freshBreaching->enhanced_kyc_at)->not->toBeNull()
        ->and($freshBreaching->sanctions_status)->toBe(SanctionsStatus::UnderReview)
        ->and($freshBreaching->screening_trigger_source)->toBe(ScreeningTriggerSource::AmlThreshold);
    expect(ComplianceReview::query()->where('customer_id', $breaching->id)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerEnhancedKycReviewRequired::NAME)->count())->toBe(1);

    // The clean Customer is untouched — no flag, no re-screen, no review row.
    $freshClean = Customer::findOrFail($clean->id);
    expect($freshClean->enhanced_kyc_flag)->toBeNull()
        ->and($freshClean->sanctions_status)->toBeNull()
        ->and($freshClean->screening_trigger_source)->toBeNull();
    expect(ComplianceReview::query()->where('customer_id', $clean->id)->count())->toBe(0);
});

it('is a no-op under the launch null totals reader (detects nothing)', function () {
    // No fake bound → the container's NullCustomerTransactionTotalsReader (zero EUR totals) drives every read, so the
    // scan runs to completion but escalates nobody (the launch posture until Module S lands).
    Customer::factory()->count(3)->create();

    expect(Artisan::call('parties:scan-enhanced-kyc-thresholds'))->toBe(0);

    expect(Customer::query()->where('enhanced_kyc_flag', true)->count())->toBe(0)
        ->and(ComplianceReview::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('is idempotent — a second scan of an already-escalated Customer changes nothing', function () {
    $breaching = Customer::factory()->create();
    bindScanTotals([$breaching->id]);

    // First scan escalates: one review, one event.
    expect(Artisan::call('parties:scan-enhanced-kyc-thresholds'))->toBe(0);
    expect(ComplianceReview::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerEnhancedKycReviewRequired::NAME)->count())->toBe(1);

    // Second scan — the reader STILL reports the breach — but the orchestrator's `enhanced_kyc_flag` latch no-ops:
    // no second review, no second event.
    expect(Artisan::call('parties:scan-enhanced-kyc-thresholds'))->toBe(0);
    expect(ComplianceReview::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerEnhancedKycReviewRequired::NAME)->count())->toBe(1);
});

it('registers the command signature', function () {
    expect(Artisan::all())->toHaveKey('parties:scan-enhanced-kyc-thresholds');
});

it('schedules the scan daily', function () {
    // Bootstrapping the console kernel registers routes/console.php's schedule entries on the shared Schedule
    // singleton (the SweepTest precedent — this resolution and the routes-file facade are the same instance).
    app(Kernel::class)->bootstrap();

    // sole() throws if the entry is absent (or duplicated), so finding it IS the existence proof.
    $scan = collect(app(Schedule::class)->events())
        ->sole(fn (Event $event): bool => is_string($event->command) && str_contains($event->command, 'parties:scan-enhanced-kyc-thresholds'));

    expect($scan->expression)->toBe('0 0 * * *');   // ->daily() == midnight every day
});
