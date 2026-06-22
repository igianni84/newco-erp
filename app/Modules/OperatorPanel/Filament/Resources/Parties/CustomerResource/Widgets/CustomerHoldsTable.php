<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Widgets;

use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * CustomerHoldsTable — the Customer console's read-only Holds table (operator-console-parties-holds, tasks
 * 1.2 + 2.1; design D5/L1). A Hold is NOT an Eloquent relation of Customer — its scope is a polymorphic
 * `scope_type` + `scope_id` with no DB FK ({@see Hold}, design L1) — so a Filament RelationManager cannot host
 * it. The vehicle, pinned AGAINST THE INSTALLED Filament (v5.6.7, verified in the vendor tree, NOT written from
 * memory — the repo's arch-from-memory ban), is a non-relation {@see TableWidget}: a `HasTable` Livewire
 * component whose `table()` sources rows from a direct {@see Hold::query()} (a READ —
 * `NoEloquentWriteInOperatorPanelRule` forbids only Eloquent WRITES from the console) and carries PER-ROW actions
 * through Filament 5's `recordActions()` (the v5 rename of the old `actions()`), each a unified {@see Action}.
 * {@see ViewCustomer} hosts it via `getFooterWidgets()`, passing the page record EXPLICITLY
 * (`::make(['record' => $this->getRecord()])`): a resource ViewRecord page does NOT auto-inject its record into
 * widgets (base `Page::getWidgetData()` returns `[]`). The class lives UNDER `CustomerResource/` — deliberately
 * OUTSIDE the panel's discovered `Filament/Widgets/` directory — so the panel never auto-registers it as a
 * dashboard widget; it renders only where ViewCustomer mounts it.
 *
 * Task 2.1 completes the READ table. The query is the full scope-set UNION — the Customer's own id (`customer`
 * scope) ∪ its co-provisioned Account's id (`account` scope) ∪ its Profile ids (`profile` scope) — an
 * OR-of-scopes over `(scope_type, scope_id)` with no FK to lean on, mirroring the `DatabaseComplianceStatusReader`
 * cascade idiom (design D5; the Account is a nullable `hasOne`, and a profile-less Customer contributes an empty
 * `whereIn` that matches nothing). It shows BOTH active and lifted Holds (design Open-Questions), the `status`
 * column distinguishing them. Columns render the Hold registry read-only: `hold_type` / `scope_type` / `status`
 * through the model's BackedEnum cast `->value` ({@see castValueState}, mirroring the resource's `enumBadgeState`
 * — NEVER importing the enum, so the `status` state enum `HoldStatus` stays cast-only), `reason`, the placement
 * actor (`role #id`, the unattended `system` Hold showing just the role) over `created_at` (placed-at), and the
 * lift actor + `lifted_at` (both blank while active). There is deliberately NO inline edit/delete affordance:
 * every Hold mutation is the per-row `lift` action (task 4.1, replacing the inert `placeholder`) routed through
 * the `LiftHold` domain Action, never a Filament default mutating path. `Hold` / `HoldScope` / `Customer` import
 * freely under the OperatorPanel {Models, Actions, Enums} carve-out (ModuleBoundariesTest needs no change).
 */
class CustomerHoldsTable extends TableWidget
{
    /**
     * The hosting ViewCustomer's Customer record (a within-module Module-K entity), injected by the page through
     * `::make(['record' => …])`. Typed `?Customer` rather than the generic `?Model`: task 2.1's scope-set query
     * reads the Customer's own `account` (a nullable `hasOne`) and `profiles` relations, not merely its key — so
     * the concrete type earns its keep. Filament's Eloquent synth stores the class + key and rehydrates the
     * Customer across Livewire round-trips.
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
     * Render inline with the host page rather than deferred — the per-Customer Holds set is small, and inline
     * rendering lets the ViewCustomer integration test observe the table in the page's initial render.
     */
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $customer = $this->record;
        $customerId = $customer?->id;
        $accountId = $customer?->account?->id;
        $profileIds = $customer === null ? [] : $customer->profiles->pluck('id')->all();

        return $table
            // The full scope-set union (design D5): a Hold "belongs to this Customer" when it is scoped to the
            // Customer's own id, its co-provisioned Account's id, OR any of its Profile ids — an OR-of-scopes over
            // the polymorphic (scope_type, scope_id) with no FK (the DatabaseComplianceStatusReader idiom). A READ
            // — no Eloquent write crosses the boundary.
            ->query(
                Hold::query()->where(function (Builder $query) use ($customerId, $accountId, $profileIds): void {
                    $query
                        ->orWhere(function (Builder $inner) use ($customerId): void {
                            $inner->where('scope_type', HoldScope::Customer->value)
                                ->where('scope_id', $customerId);
                        })
                        ->orWhere(function (Builder $inner) use ($accountId): void {
                            $inner->where('scope_type', HoldScope::Account->value)
                                ->where('scope_id', $accountId);
                        })
                        ->orWhere(function (Builder $inner) use ($profileIds): void {
                            $inner->where('scope_type', HoldScope::Profile->value)
                                ->whereIn('scope_id', $profileIds);
                        });
                }),
            )
            ->columns([
                TextColumn::make('hold_type')
                    ->label((string) __('operator_console.customer.holds.columns.hold_type'))
                    ->getStateUsing(self::castValueState('hold_type')),
                TextColumn::make('scope_type')
                    ->label((string) __('operator_console.customer.holds.columns.scope_type'))
                    ->getStateUsing(self::castValueState('scope_type')),
                TextColumn::make('status')
                    ->label((string) __('operator_console.customer.holds.columns.status'))
                    ->getStateUsing(self::castValueState('status')),
                TextColumn::make('reason')
                    ->label((string) __('operator_console.customer.holds.columns.reason')),
                TextColumn::make('placed_by')
                    ->label((string) __('operator_console.customer.holds.columns.placed_by'))
                    ->getStateUsing(function (Hold $record): string {
                        $role = $record->placed_actor_role;

                        return $record->placed_actor_id === null
                            ? $role->value
                            : $role->value.' #'.$record->placed_actor_id;
                    }),
                TextColumn::make('created_at')
                    ->label((string) __('operator_console.customer.holds.columns.placed_at'))
                    ->dateTime(),
                TextColumn::make('lifted_by')
                    ->label((string) __('operator_console.customer.holds.columns.lifted_by'))
                    ->getStateUsing(function (Hold $record): string {
                        $role = $record->lifted_actor_role;

                        if ($role === null) {
                            return '';
                        }

                        return $record->lifted_actor_id === null
                            ? $role->value
                            : $role->value.' #'.$record->lifted_actor_id;
                    }),
                TextColumn::make('lifted_at')
                    ->label((string) __('operator_console.customer.holds.columns.lifted_at'))
                    ->dateTime(),
            ])
            ->recordActions([
                // Placeholder per-row action — proves the vehicle carries row actions. The real `lift` action
                // (visibility-gated, wired to LiftHold) replaces it in task 4.1.
                Action::make('placeholder')->action(fn () => null),
            ]);
    }

    /**
     * A read resolver rendering one of the Hold's BackedEnum-cast columns — `hold_type` (a HoldType), `scope_type`
     * (a HoldScope) or `status` (the HoldStatus state enum) — through the model cast `->value` (`admin` /
     * `account` / `active`), mirroring the Customer resource's `enumBadgeState`. The state is read off the record
     * and rendered through its cast, NEVER by importing the enum — so the `status` state enum `HoldStatus` stays
     * cast-only (the no-state-enum-import discipline; task 2.1). `getAttribute()` returns mixed, so the
     * `instanceof BackedEnum` test is meaningful (not always-true) and renders the empty string for any unexpected
     * null; it is a read — the no-Eloquent-write rule polices writes only.
     *
     * @return Closure(Hold): string
     */
    private static function castValueState(string $attribute): Closure
    {
        return static function (Hold $record) use ($attribute): string {
            $state = $record->getAttribute($attribute);

            return $state instanceof BackedEnum ? (string) $state->value : '';
        };
    }
}
