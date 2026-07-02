<?php

// parties-enhanced-kyc-threshold, task 6.1 — the Customer console's read-only enhanced-KYC surface. Two read
// projections land on ViewCustomer: (a) a DISTINCT infolist section (heading `sections.compliance_reviews`),
// VISIBILITY-GATED to a flagged Customer (`enhanced_kyc_flag === true`), carrying the flag IconEntry + the
// `enhanced_kyc_at` timestamp; (b) a non-relation footer widget ({@see CustomerComplianceReviewsTable}) listing the
// Customer's OPEN review-queue entries (`resolved_at IS NULL`) — the Holds-table vehicle reused (a ComplianceReview
// is no Eloquent relation of Customer). Read-projection ONLY: the review-resolve write surface is deferred this
// change (§ 9.1 — enhanced-KYC is handled operationally, no FSM), so there is no per-row action.
//
// These tests drive the CONSOLE SURFACE, so they stand up the escalated state with FACTORIES (the Holds console
// read-test precedent — Hold::factory bypasses the domain action), not the detection Action: the full chain
// through the real EvaluateEnhancedKycThreshold is task 7.1's integration proof. RefreshDatabase suffices — the
// read surface performs no nested-transaction domain writes (contrast CustomerHoldsConsoleTest's DatabaseMigrations,
// needed there for the place/lift actions' own transactions). The ComplianceReviewFactory default is an OPEN
// single-transaction €10k breach; `resolved()` stamps `resolved_at`. Parties enums/models import freely in tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ViewCustomer;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Widgets\CustomerComplianceReviewsTable;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\ThresholdKind;
use App\Modules\Parties\Models\ComplianceReview;
use App\Modules\Parties\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows the gated enhanced-KYC section and the open review on the ViewCustomer page for a flagged Customer', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A flagged Customer (the €10k-single latch tripped) carrying one OPEN single-transaction €10k review — the
    // factory default. The footer widget is non-lazy, so its row renders inline on the ViewCustomer page (the
    // Holds precedent), and the flag-gated infolist section becomes visible.
    $customer = Customer::factory()->create([
        'enhanced_kyc_flag' => true,
        'enhanced_kyc_at' => now(),
    ]);
    ComplianceReview::factory()->create(['customer_id' => $customer->id]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        // The visibility-gated section is shown (its heading proves the flag latch is surfaced).
        ->assertSee((string) __('operator_console.customer.sections.compliance_reviews'))
        // The open review row renders through the footer widget: reason / threshold_kind map the enum cast ->value
        // through the Module-K DOMAIN copy; amount renders the tripping money readably (invariant 6).
        ->assertSee((string) __('parties.compliance_review.reason.enhanced_kyc_threshold'))
        ->assertSee((string) __('parties.compliance_review.threshold_kind.single_transaction'))
        ->assertSee('10,000.00 EUR');
});

it('hides the enhanced-KYC section and surfaces no review rows for an un-escalated Customer', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A fresh factory Customer is never-flagged (`enhanced_kyc_flag` NULL) with no review-queue entry — the
    // acceptance "un-escalated shows neither": the gated section hides AND the always-mounted footer widget
    // surfaces no rows.
    $customer = Customer::factory()->create();

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->assertDontSee((string) __('operator_console.customer.sections.compliance_reviews'))
        ->assertDontSee((string) __('parties.compliance_review.reason.enhanced_kyc_threshold'))
        ->assertDontSee((string) __('parties.compliance_review.threshold_kind.single_transaction'))
        ->assertDontSee('10,000.00 EUR');
});

it('lists only OPEN reviews in the footer widget, excluding resolved entries', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $customer = Customer::factory()->create([
        'enhanced_kyc_flag' => true,
        'enhanced_kyc_at' => now(),
    ]);

    // One OPEN review (`resolved_at IS NULL`, the factory default) and one RESOLVED review (the `resolved()` state
    // stamps `resolved_at`). The queue surface shows only the open one — a resolved review leaves the queue
    // (design D6: open-vs-resolved is boolean-derivable, not an FSM).
    $open = ComplianceReview::factory()->create(['customer_id' => $customer->id]);
    $resolved = ComplianceReview::factory()->resolved()->create(['customer_id' => $customer->id]);

    Livewire::test(CustomerComplianceReviewsTable::class, ['record' => $customer])
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$resolved]);
});

it('renders the review columns read-only for both threshold kinds, with no per-row action', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $customer = Customer::factory()->create([
        'enhanced_kyc_flag' => true,
        'enhanced_kyc_at' => now(),
    ]);

    // The single-transaction €10k default and a cumulative-annual €50k variant — exercising BOTH ThresholdKind
    // domain-label mappings and the money render on each.
    $single = ComplianceReview::factory()->create(['customer_id' => $customer->id]);
    $cumulative = ComplianceReview::factory()->create([
        'customer_id' => $customer->id,
        'threshold_kind' => ThresholdKind::CumulativeAnnual,
        'tripped_amount_minor' => 5_000_000,
    ]);

    Livewire::test(CustomerComplianceReviewsTable::class, ['record' => $customer])
        ->assertCanSeeTableRecords([$single, $cumulative])
        // Both threshold-kind domain labels + both amounts render read-only.
        ->assertSee((string) __('parties.compliance_review.threshold_kind.single_transaction'))
        ->assertSee((string) __('parties.compliance_review.threshold_kind.cumulative_annual'))
        ->assertSee('10,000.00 EUR')
        ->assertSee('50,000.00 EUR')
        // Read-projection only: no Filament default mutating action and no deferred resolve action on any row.
        ->assertTableActionDoesNotExist('edit', record: $single)
        ->assertTableActionDoesNotExist('delete', record: $single)
        ->assertTableActionDoesNotExist('resolve', record: $single);
});
