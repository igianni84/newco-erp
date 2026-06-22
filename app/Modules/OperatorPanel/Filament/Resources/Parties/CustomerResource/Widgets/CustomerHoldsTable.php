<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Widgets;

use App\Modules\OperatorPanel\Filament\Console\Concerns\SurfacesDomainActions;
use App\Modules\Parties\Actions\LiftHold;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
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
 * every Hold mutation is the per-row `lift` action routed through the `LiftHold` domain Action, never a Filament
 * default mutating path.
 *
 * Task 4.1 wires that per-row `lift` — the console's FIRST per-row action. It is VISIBLE iff the Hold is
 * operator-liftable ({@see isOperatorLiftable}: still `active` AND not auto-managed — `admin`/`fraud`/`compliance`/
 * `credit`, design D5/D6), carries an optional `lift_reason`, and routes the Hold's own id (`$record->id`, the
 * typed `int` key — NOT the untyped `getKey()`) into {@see LiftHold} through
 * {@see SurfacesDomainActions::surfaceLifecycleOutcome()} — the SAME write-through kit the
 * {@see ViewCustomer} page uses, REUSED here (the widget `use`s the trait and implements {@see i18nKey()} → its
 * `customer` copy root) rather than forked for one caller (design D3). The console invokes ONLY `LiftHold`; the
 * Hold→`suspended` RESTORE coupling it triggers is domain-owned and additive (design D7). The lift discipline is
 * SURFACED by visibility AND ENFORCED by the domain: `LiftHold` independently rejects an auto-managed
 * (`IllegalHoldLift::autoManaged`) or already-`lifted` (`::notActive`) Hold, caught by base `RuntimeException` in
 * the trait and surfaced as the `action_failed` danger notification — so the exception type is named in PROSE,
 * never imported (design D6). `Hold` / `HoldScope` / `Customer` and the `LiftHold` Action import freely under the
 * OperatorPanel {Models, Actions, Enums} carve-out; the `HoldStatus` STATE enum is NEVER imported — visibility
 * reads it through the model cast `->value` (design D2) — so `ModuleBoundariesTest` needs no change.
 */
class CustomerHoldsTable extends TableWidget
{
    // Reuses the console's write-through kit for the per-row `lift` (design D3): surfaceLifecycleOutcome() runs
    // LiftHold and renders the uniform success / base-RuntimeException→`action_failed` notification. The trait's
    // page-oriented lifecycleAction()/recordOf() go unused here; only surfaceLifecycleOutcome() + the i18nKey()
    // contract are exercised — no fork for one caller.
    use SurfacesDomainActions;

    /**
     * The `operator_console.customer` copy root the reused {@see SurfacesDomainActions} kit resolves the lift's
     * success / `action_failed` notification titles through — the SAME root the host {@see ViewCustomer} page uses,
     * so the Holds surface speaks one localized voice (invariant 12).
     */
    protected function i18nKey(): string
    {
        return 'customer';
    }

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
                // The per-row `lift` — the console's FIRST per-row action (design D5). Shown only for an
                // operator-liftable Hold ({@see isOperatorLiftable}); carries an optional `lift_reason`; routes the
                // Hold's own key into LiftHold through the reused surfaceLifecycleOutcome() kit. It keys off the
                // Hold id (NOT the page record — a verb like suspend keys off the Customer id; LiftHold keys off a
                // specific Hold), and the domain still enforces the lift discipline the visibility surfaces (D6).
                Action::make('lift')
                    ->label((string) __('operator_console.customer.actions.lift_hold'))
                    ->visible(fn (Hold $record): bool => self::isOperatorLiftable($record))
                    ->schema([
                        Textarea::make('lift_reason')
                            ->label((string) __('operator_console.customer.fields.lift_reason')),
                    ])
                    ->action(
                        /** @param  array<string, mixed>  $data */
                        function (Hold $record, array $data): void {
                            // The optional lift note arrives stringly-typed (Filament Textarea); narrow with the
                            // slice's is_string discipline and normalize blank → NULL (a system/empty reason is
                            // NULL, never '' — the CustomerHoldLifted payload contract, mirroring placeHold).
                            $reason = is_string($data['lift_reason'] ?? null) && $data['lift_reason'] !== ''
                                ? $data['lift_reason']
                                : null;

                            // Invoke ONLY LiftHold (never a Reactivate* verb): the Hold→`suspended` RESTORE coupling
                            // is domain-owned and additive (design D7). surfaceLifecycleOutcome renders the success /
                            // base-RuntimeException→`action_failed` notification (the domain's IllegalHoldLift
                            // rejection of an auto-managed or already-lifted Hold — D6 — is caught by base type,
                            // never imported) and never writes Eloquent.
                            $this->surfaceLifecycleOutcome(
                                fn () => app(LiftHold::class)->handle($record->id, $reason),
                                (string) __('operator_console.customer.notifications.hold_lifted'),
                            );
                        }
                    ),
            ]);
    }

    /**
     * Whether the per-row `lift` action is offered for this Hold (design D5/D6). True IFF the Hold is still `active`
     * — read through the model cast `->value` (`active`), NEVER importing the `HoldStatus` STATE enum (design D2);
     * the Hold FSM is `active | lifted`, so an un-lifted Hold reads `active` — AND its type is not auto-managed
     * ({@see HoldType::autoLiftable()} → `kyc` / `payment` lift only on their system clearing signal, never by hand).
     * This only HIDES an action the domain would reject anyway: {@see LiftHold} independently enforces both guards
     * (`IllegalHoldLift::notActive` / `::autoManaged`), surfaced as the `action_failed` danger notification —
     * visibility is the surface, the domain is the enforcer (design D6).
     */
    private static function isOperatorLiftable(Hold $hold): bool
    {
        return $hold->status->value === 'active' && ! $hold->hold_type->autoLiftable();
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
