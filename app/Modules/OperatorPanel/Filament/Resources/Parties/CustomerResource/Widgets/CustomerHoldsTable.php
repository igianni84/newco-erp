<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Widgets;

use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Models\Hold;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/**
 * CustomerHoldsTable — the Customer console's Holds-table VEHICLE (operator-console-parties-holds, task 1.2;
 * design L1). A Hold is NOT an Eloquent relation of Customer — its scope is a polymorphic `scope_type` +
 * `scope_id` with no DB FK ({@see Hold}, design L1) — so a Filament RelationManager cannot host it. The vehicle
 * pinned here AGAINST THE INSTALLED Filament (v5.6.7, verified in the vendor tree, NOT written from memory — the
 * repo's arch-from-memory ban) is a non-relation {@see TableWidget}: a `HasTable` Livewire component whose
 * `table()` sources rows from a direct {@see Hold::query()} (a READ — `NoEloquentWriteInOperatorPanelRule`
 * forbids only Eloquent WRITES from the console) and carries PER-ROW actions through Filament 5's
 * `recordActions()` (the v5 rename of the old `actions()`), each a unified {@see Action}. {@see ViewCustomer}
 * hosts it via `getFooterWidgets()`, passing the page record EXPLICITLY (`::make(['record' => $this->getRecord()])`):
 * a resource ViewRecord page does NOT auto-inject its record into widgets (base `Page::getWidgetData()` returns
 * `[]`). The class lives UNDER `CustomerResource/` — deliberately OUTSIDE the panel's discovered
 * `Filament/Widgets/` directory — so the panel never auto-registers it as a dashboard widget; it renders only
 * where ViewCustomer mounts it.
 *
 * This task pins the VEHICLE only. The query is the minimal customer-scope slice and the row action is an inert
 * `placeholder`; the full cross-scope read (customer ∪ the Customer's Account ∪ its Profiles), the real columns
 * rendered through the model casts, and the per-row `lift` action with its visibility gate + write-through land
 * in tasks 2.1 / 4.1. The `$record` property is typed `?Model` (Filament's own widget convention — the Eloquent
 * synth rehydrates the concrete Customer across Livewire requests); `Hold` / `HoldScope` import freely under the
 * OperatorPanel {Models, Actions, Enums} carve-out (ModuleBoundariesTest needs no change).
 */
class CustomerHoldsTable extends TableWidget
{
    /**
     * The hosting ViewCustomer's Customer record (a within-module Module-K entity), injected by the page through
     * `::make(['record' => …])`. Typed `?Model` per Filament's widget convention — the Eloquent synth stores the
     * concrete class and rehydrates the Customer across Livewire round-trips.
     */
    public ?Model $record = null;

    /**
     * A table spans the full footer-widget row (the page's footer grid is multi-column by default). The PHPDoc
     * mirrors the parent {@see Widget::$columnSpan} exactly (phpstan property covariance).
     *
     * @var int|string|array<string, int|null>
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * Render inline with the host page rather than deferred — the per-Customer Holds set is small, and inline
     * rendering lets the ViewCustomer integration test observe the table in the page's initial render.
     */
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            // Minimal customer-scope placeholder (task 1.2). Task 2.1 widens this to the full scope-set union
            // (customer ∪ the Customer's Account ∪ its Profiles). A READ — no Eloquent write crosses the boundary.
            ->query(
                Hold::query()
                    ->where('scope_type', HoldScope::Customer->value)
                    ->where('scope_id', $this->record?->getKey()),
            )
            ->columns([
                TextColumn::make('reason'),
            ])
            ->recordActions([
                // Placeholder per-row action — proves the vehicle carries row actions. The real `lift` action
                // (visibility-gated, wired to LiftHold) replaces it in task 4.1.
                Action::make('placeholder')->action(fn () => null),
            ]);
    }
}
