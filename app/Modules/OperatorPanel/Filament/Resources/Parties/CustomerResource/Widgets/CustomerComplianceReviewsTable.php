<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Widgets;

use App\Modules\Parties\Models\ComplianceReview;
use App\Modules\Parties\Models\Customer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Widgets\Widget;

/**
 * CustomerComplianceReviewsTable — the Customer console's read-only Compliance-review-queue table (change
 * parties-enhanced-kyc-threshold, task 6.1; design D6 + Open-Questions "read-only: flag badge + open-entries
 * list"). The SECOND non-relation footer widget on {@see ViewCustomer}, mirroring {@see CustomerHoldsTable}: a
 * {@see ComplianceReview} carries a within-module FK to the Customer but {@see Customer} exposes NO inverse
 * `hasMany` (the model deliberately stays one-directional — the read surfaces query the queue by `customer_id` +
 * `resolved_at IS NULL`), so a Filament RelationManager cannot host it. The vehicle is the same non-relation
 * Filament 5 {@see TableWidget} the Holds surface pinned against the installed Filament (v5.6.7): a `HasTable`
 * Livewire component whose `table()` sources rows from a direct {@see ComplianceReview::query()} (a READ —
 * `NoEloquentWriteInOperatorPanelRule` forbids only Eloquent WRITES from the console). {@see ViewCustomer} hosts
 * it via `getFooterWidgets()`, passing the page record EXPLICITLY (`::make(['record' => $this->getRecord()])`): a
 * resource ViewRecord page does NOT auto-inject its record into widgets (base `Page::getWidgetData()` returns
 * `[]`). The class lives UNDER `CustomerResource/` — deliberately OUTSIDE the panel's discovered
 * `Filament/Widgets/` directory — so the panel never auto-registers it as a dashboard widget; it renders only
 * where ViewCustomer mounts it.
 *
 * The query is the OPEN slice ONLY — `customer_id = <record>` AND `resolved_at IS NULL` (open-vs-resolved is
 * boolean-derivable, NOT an FSM — design D6). A resolved review drops off the operator's queue. Columns render
 * the review read-only: `reason` / `threshold_kind` map the model's BackedEnum cast `->value` through the
 * Module-K DOMAIN copy (`parties.compliance_review.{reason,threshold_kind}.*` — the enum backing values are
 * domain vocabulary, not console chrome, so they resolve off the module copy file, NEVER by importing the enum —
 * so `ComplianceReviewReason` / `ThresholdKind` stay cast-only and `ModuleBoundariesTest` needs no change), the
 * `amount` renders the tripping money readably from the two raw scalars (`tripped_amount_minor` integer minor
 * units + `tripped_currency`, invariant 6 — the ClubResource fee-display idiom, never coercing to a float), and
 * `opened_at` is the entry's `created_at`. There is deliberately NO per-row action and NO inline edit/delete: the
 * review-resolve write surface is DEFERRED this change (§ 9.1 — enhanced-KYC is handled operationally with no
 * state machine), so this surface is a pure read-projection. `ComplianceReview` / `Customer` import freely under
 * the OperatorPanel {Models, Actions, Enums} carve-out; no enum is imported (the values render through the cast).
 */
class CustomerComplianceReviewsTable extends TableWidget
{
    /**
     * The hosting ViewCustomer's Customer record (a within-module Module-K entity), injected by the page through
     * `::make(['record' => …])`. Typed `?Customer` (mirroring {@see CustomerHoldsTable}); Filament's Eloquent
     * synth stores the class + key and rehydrates the Customer across Livewire round-trips.
     */
    public ?Customer $record = null;

    /**
     * A table spans the full footer-widget row (the page's footer grid is multi-column by default). The PHPDoc
     * mirrors the parent {@see Widget::$columnSpan} exactly (phpstan property covariance).
     *
     * @var int|string|array<string, int|null>
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * Render inline with the host page rather than deferred — the per-Customer open-review set is small, and
     * inline rendering lets the ViewCustomer integration test observe the table in the page's initial render
     * (the {@see CustomerHoldsTable} precedent).
     */
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $customerId = $this->record?->id;

        return $table
            // Newest-first: the most recently opened review reads at the top, matching every console list.
            ->defaultSort('created_at', 'desc')
            // The OPEN slice only (design D6): the Customer's own reviews that are not yet resolved
            // (`resolved_at IS NULL`). A resolved review leaves the operator's queue. A READ — no Eloquent write
            // crosses the boundary. A null record id yields `customer_id IS NULL`, matching nothing (the FK is
            // NOT NULL), so an unmounted widget surfaces no rows.
            ->query(
                ComplianceReview::query()
                    ->where('customer_id', $customerId)
                    ->whereNull('resolved_at'),
            )
            ->columns([
                // reason / threshold_kind render the model's BackedEnum cast ->value through the Module-K DOMAIN
                // copy (parties.compliance_review.*) — the enum backing value is domain vocabulary, resolved off
                // the module file, never by importing the enum (so the state enums stay cast-only).
                TextColumn::make('reason')
                    ->label((string) __('operator_console.customer.compliance_reviews.columns.reason'))
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(fn (ComplianceReview $record): string => (string) __('parties.compliance_review.reason.'.$record->reason->value)),
                TextColumn::make('threshold_kind')
                    ->label((string) __('operator_console.customer.compliance_reviews.columns.threshold_kind'))
                    ->getStateUsing(fn (ComplianceReview $record): string => (string) __('parties.compliance_review.threshold_kind.'.$record->threshold_kind->value)),
                // Money discipline (invariant 6): the tripping amount is held as integer minor units + an ISO 4217
                // code; render the two minor digits readably (e.g. "10,000.00 EUR") without ever coercing to a
                // float for storage (the ClubResource fee-display idiom).
                TextColumn::make('amount')
                    ->label((string) __('operator_console.customer.compliance_reviews.columns.amount'))
                    ->getStateUsing(fn (ComplianceReview $record): string => number_format($record->tripped_amount_minor / 100, 2).' '.$record->tripped_currency),
                TextColumn::make('created_at')
                    ->label((string) __('operator_console.customer.compliance_reviews.columns.opened_at'))
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
