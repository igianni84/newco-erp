<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\Concerns\SurfacesDomainActions;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Widgets\CustomerHoldsTable;
use App\Modules\Parties\Actions\ActivateCustomer;
use App\Modules\Parties\Actions\CloseCustomer;
use App\Modules\Parties\Actions\PlaceHold;
use App\Modules\Parties\Actions\ReactivateCustomer;
use App\Modules\Parties\Actions\SuspendCustomer;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Database\Eloquent\Model;

/**
 * ViewCustomer — the Customer console view page (operator-console-parties-customer, task 3.1; design
 * D1/D3/D4/D5/D8). The FIRST demand-side Parties status surface, built — like {@see ViewProducer} — at the
 * TRAIT level, NOT through the catalog-shaped {@see OperatorConsoleViewRecord} base: that base hard-codes the
 * five catalog governance verbs (submit · reject · activate[SoD] · retire · reopen), which do not fit the
 * Customer's `pending → active → suspended → closed` status FSM. So this page extends Filament's
 * {@see ViewRecord} directly, `use`s {@see SurfacesDomainActions}, and assembles its OWN four status verbs in
 * {@see getHeaderActions()} (design D1/D8 — the rule-of-three verdict: non-catalog consoles stay at the trait
 * level, `OperatorConsoleViewRecord` stays catalog-only; ADR 2026-06-21). The read-only infolist + list table
 * live on {@see CustomerResource}.
 *
 * The four verbs are the MANUAL status-FSM path (design D4; BR-K-Customer-1 "suspension is explicit — manual or
 * via Hold"): activate (`pending → active`), suspend (`active → suspended`, cascading onto the Customer's active
 * Profiles), reactivate (`suspended → active`, coverage-guarded restore) and close
 * (`active | suspended → closed`). All four are FORM-LESS and carry NO confirmation affordance (design D3): the
 * Customer FSM has no separation-of-duties floor (Admin_Panel § 5.2), so no verb collects notes or a
 * "second actor" modal. The Hold-mediated suspend/restore path (`PlaceHold` / `LiftHold`, whose coupling also
 * moves `status` — ADR 2026-06-19) is THIS slice's surface (operator-console-parties-holds) and coexists
 * ADDITIVELY with the four verbs: the verbs above never recompute suspension — the Hold surface calls only
 * `PlaceHold` / `LiftHold` and lets the domain move `status`. The Holds READ table is hosted as a FOOTER WIDGET —
 * {@see CustomerHoldsTable}, a non-relation Filament 5 `TableWidget` (a Hold is no Eloquent relation of Customer;
 * the vehicle pinned in task 1.2 against the installed Filament 5.6.7) — registered in {@see getFooterWidgets()}.
 * The `placeHold` form action is a HEADER action on THIS page — {@see placeHoldAction()} — targeting the page's
 * Customer (NOT a table row), built BESPOKE because it carries a Hold-type / scope / Profile form the kit's
 * form-less {@see SurfacesDomainActions::lifecycleAction()} cannot thread; its write-through routes the form
 * operands into the Parties `PlaceHold` action through {@see SurfacesDomainActions::surfaceLifecycleOutcome()}
 * (task 3.2), while the per-row `lift` lands on the widget's table (task 4). KYC / sanctions / Account / Profile
 * remain their own future slices (design Non-Goals).
 *
 * ACTIVATION IS CROSS-SLICE-GATED (design D5; § 4.1): {@see ActivateCustomer} guards a composite onboarding gate
 * — email-verified ∧ T&C/privacy accepted ∧ `sanctions_status = passed` ∧ KYC-cleared-if-required — that THIS
 * slice sets none of (they come from the consumer-onboarding flow + the compliance console). The console
 * SURFACES `activate`; a gate-unmet attempt is REJECTED by the Action (`IllegalCustomerTransition` gate-not-met,
 * a `RuntimeException`) and rendered as the `notifications.action_failed` danger title for free via the trait's
 * {@see SurfacesDomainActions::surfaceLifecycleOutcome()}. This is correct surface-ahead-of-drivers behaviour,
 * NOT a defect — do not "fix" the rejection by writing gate columns from the console.
 *
 * Each invocation has the uniform `fn (Model $record, string $notes)` signature the trait expects (`$notes`
 * unused — no Customer status verb has a form) and routes to a Parties domain action, which (like the Producer
 * verbs) takes the customer `int $id` — so {@see SurfacesDomainActions::recordOf()} narrows `Model → Customer`
 * and the call passes `->id`. The console NEVER writes `status` itself (the no-Eloquent-write rule); it SURFACES
 * the domain's decision. An out-of-state transition throws `IllegalCustomerTransition` (a `RuntimeException`),
 * caught by base type in {@see SurfacesDomainActions::surfaceLifecycleOutcome()} and rendered as the
 * `action_failed` danger notification — so the console imports only {@see Customer} + the `Parties\Actions\*` it
 * invokes (the {Models, Actions} carve-out), never a `Parties\Exceptions` type (named here in PROSE so Pint's
 * `fully_qualified_strict_types` cannot re-add a forbidden import — lessons.md, 2026-06-20). All copy is
 * localized through `i18nKey()` (invariant 12).
 *
 * HEADER-ACTION VISIBILITY-TEST API (pinned against installed Filament 5.6.7, kyc-sanctions slice task 1.2 — the
 * header-action twin of the Holds table's `assertTableActionVisible/Hidden`): on `Livewire::test(self::class,
 * ['record' => $id])`, `assertActionVisible('verb')` / `assertActionHidden('verb')` resolve a page header action by
 * name and evaluate its `->visible()` closure against THIS page's record (per-record — confirmed empirically); the
 * mount-and-inspect-form path is `mountAction('verb')` + `assertFormFieldExists/Visible/Hidden` + `setActionData([…])`
 * (the placeHold precedent). LANDMINE (design D4): `callAction()` asserts-visible-FIRST, and a `->visible()`-false
 * header verb never mounts server-side — so a hidden verb's reject is UNREACHABLE through the surface; prove it via a
 * domain `toThrow(...)` + `assertActionHidden('verb')`, NEVER an `assertNotified(action_failed)` the page can't raise.
 */
class ViewCustomer extends ViewRecord
{
    use SurfacesDomainActions;

    protected static string $resource = CustomerResource::class;

    protected function i18nKey(): string
    {
        return 'customer';
    }

    /**
     * The Customer's header actions: the four status verbs — activate / suspend / reactivate / close, the manual
     * status-FSM path (design D4) — plus the `placeHold` form action. The four verbs are form-less and carry no
     * confirmation affordance (design D3); each routes to its typed Parties action by the customer `int $id`, never
     * an Eloquent write. `activate` is cross-slice-gated by the Action — a gate-unmet attempt rejects gracefully as
     * a danger notification (design D5). `placeHold` ({@see placeHoldAction()}) is bespoke — it carries a Hold-type
     * / scope / Profile form, so it is NOT a form-less status verb.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->lifecycleAction('activate', 'activated', fn (Model $record, string $notes) => app(ActivateCustomer::class)->handle($this->recordOf(Customer::class, $record)->id)),
            $this->lifecycleAction('suspend', 'suspended', fn (Model $record, string $notes) => app(SuspendCustomer::class)->handle($this->recordOf(Customer::class, $record)->id)),
            $this->lifecycleAction('reactivate', 'reactivated', fn (Model $record, string $notes) => app(ReactivateCustomer::class)->handle($this->recordOf(Customer::class, $record)->id)),
            $this->lifecycleAction('close', 'closed', fn (Model $record, string $notes) => app(CloseCustomer::class)->handle($this->recordOf(Customer::class, $record)->id)),
            $this->placeHoldAction(),
        ];
    }

    /**
     * The Holds READ table, hosted as a footer widget — {@see CustomerHoldsTable}, the non-relation Filament 5
     * TableWidget vehicle pinned in task 1.2 (a Hold is no Eloquent relation of Customer). The page passes its
     * record EXPLICITLY: a ViewRecord does not auto-inject `record` into widgets (base `getWidgetData()` is `[]`).
     *
     * @return array<int, WidgetConfiguration>
     */
    protected function getFooterWidgets(): array
    {
        return [
            CustomerHoldsTable::make(['record' => $this->getRecord()]),
        ];
    }

    /**
     * The `placeHold` header action — the operator's place-a-Hold surface (operator-console-parties-holds, tasks
     * 3.1/3.2; design L1/L5/D3/D4). Built BESPOKE rather than through {@see SurfacesDomainActions::lifecycleAction()}:
     * a Hold carries operands the form-less verb helper cannot thread — a {@see HoldType}, a {@see HoldScope} and (for
     * a profile-scope Hold) the target Profile. The `->action()` narrows the form operands, resolves the scope target
     * via {@see holdScopeId()} and routes them into the Parties `PlaceHold` action through
     * {@see SurfacesDomainActions::surfaceLifecycleOutcome()} — REUSING the trait's uniform
     * success / `RuntimeException`→`action_failed` notification, never an Eloquent write (design D3). The console
     * invokes ONLY `PlaceHold`; the Hold→`suspended` coupling it triggers is domain-owned and additive (design D7).
     *
     * The two enum Selects offer the raw operand-enum tokens as both value and label (the resource's
     * currency/locale-code Selects precedent — domain data, not UI chrome, so no per-value i18n key); importing the
     * {@see HoldType} / {@see HoldScope} OPERAND enums is the {Models, Actions, Enums} carve-out (ADR 2026-06-21 —
     * a namespace-prefix allow, no allow-list widening). The `profile_id` Select is populated from the Customer's
     * Club-membership Profiles and shown ONLY for a profile-scope Hold — the `scope_type` Select is `->live()` so
     * the dependency re-evaluates. Labels resolve the DESCRIPTIVE `fields.*` form group (invariant 12).
     */
    protected function placeHoldAction(): Action
    {
        return Action::make('placeHold')
            ->label((string) __('operator_console.customer.actions.place_hold'))
            ->schema([
                Select::make('hold_type')
                    ->label((string) __('operator_console.customer.fields.hold_type'))
                    ->options($this->holdTypeOptions())
                    ->required(),
                Select::make('scope_type')
                    ->label((string) __('operator_console.customer.fields.hold_scope'))
                    ->options($this->holdScopeOptions())
                    ->required()
                    ->live(),
                Select::make('profile_id')
                    ->label((string) __('operator_console.customer.fields.profile'))
                    ->options(fn (): array => $this->profileOptions())
                    ->visible(fn (Get $get): bool => $get('scope_type') === HoldScope::Profile->value),
                Textarea::make('reason')
                    ->label((string) __('operator_console.customer.fields.reason')),
            ])
            ->action(
                /** @param  array<string, mixed>  $data */
                function (array $data): void {
                    $customer = $this->recordOf(Customer::class, $this->getRecord());

                    // The form state arrives stringly-typed (Filament Select values serialize to strings); narrow
                    // EVERY operand with the slice's `is_string` discipline before constructing the typed PlaceHold
                    // inputs. hold_type / scope_type are `->required()` → present on the happy path (the `: ''` floors
                    // satisfy the type system, the trait's `$notes` idiom). `profile_id` is the int-keyed Select's
                    // chosen key (present only for a profile-scope Hold) → narrowed to `?string` here and cast to int
                    // in holdScopeId(). reason is optional and normalizes blank → NULL (a system/empty reason is NULL,
                    // never '' — the CustomerHoldPlaced payload contract).
                    $type = is_string($data['hold_type'] ?? null) ? $data['hold_type'] : '';
                    $scope = is_string($data['scope_type'] ?? null) ? $data['scope_type'] : '';
                    $profileId = is_string($data['profile_id'] ?? null) ? $data['profile_id'] : null;
                    $reason = is_string($data['reason'] ?? null) && $data['reason'] !== '' ? $data['reason'] : null;

                    $holdScope = HoldScope::from($scope);

                    // The console invokes ONLY PlaceHold (never a Suspend* verb): the Hold→`suspended` coupling is
                    // domain-owned and additive (design D7). surfaceLifecycleOutcome renders the success/`action_failed`
                    // notification and never writes Eloquent.
                    $this->surfaceLifecycleOutcome(
                        fn () => app(PlaceHold::class)->handle(
                            HoldType::from($type),
                            $holdScope,
                            $this->holdScopeId($customer, $holdScope, $profileId),
                            $reason,
                        ),
                        (string) __('operator_console.customer.notifications.hold_placed'),
                    );
                }
            );
    }

    /**
     * Resolve the within-module scope-target id for a placement from the page's Customer (design L4/D4): a
     * `customer`-scope Hold targets the Customer itself, an `account`-scope Hold its co-provisioned Account
     * (always present in production — CreateCustomer provisions the 1:1), a `profile`-scope Hold the selected
     * Club-membership Profile (its already-narrowed Select key, cast to int). The Hold scope carries NO DB FK
     * (design L1); the id is a within-module reference `PlaceHold` writes verbatim.
     */
    private function holdScopeId(Customer $customer, HoldScope $scope, ?string $profileId): int
    {
        return match ($scope) {
            HoldScope::Customer => $customer->id,
            HoldScope::Account => (int) $customer->account?->id,
            HoldScope::Profile => (int) $profileId,
        };
    }

    /**
     * The `hold_type` Select options — the six {@see HoldType} operand-enum tokens keyed value → value (the
     * resource's currency/locale-code Selects precedent: domain data, not UI chrome, so no per-value i18n key).
     *
     * @return array<string, string>
     */
    private function holdTypeOptions(): array
    {
        return collect(HoldType::cases())
            ->mapWithKeys(static fn (HoldType $type): array => [$type->value => $type->value])
            ->all();
    }

    /**
     * The `scope_type` Select options — the three {@see HoldScope} operand-enum tokens keyed value → value.
     *
     * @return array<string, string>
     */
    private function holdScopeOptions(): array
    {
        return collect(HoldScope::cases())
            ->mapWithKeys(static fn (HoldScope $scope): array => [$scope->value => $scope->value])
            ->all();
    }

    /**
     * The `profile_id` Select options for a profile-scope Hold — the page Customer's Club-membership Profiles, keyed
     * by Profile id → its Club's display name (the within-Parties read the infolist already uses). Evaluated lazily
     * (a Closure on the Select) so it reads the resolved record at render time; empty when the Customer holds no
     * Profiles.
     *
     * @return array<int, string>
     */
    private function profileOptions(): array
    {
        return $this->recordOf(Customer::class, $this->getRecord())
            ->profiles
            ->mapWithKeys(static fn (Profile $profile): array => [$profile->id => $profile->club->display_name])
            ->all();
    }
}
