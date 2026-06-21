<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\Concerns\SurfacesDomainActions;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource;
use App\Modules\Parties\Actions\ActivateCustomer;
use App\Modules\Parties\Actions\CloseCustomer;
use App\Modules\Parties\Actions\ReactivateCustomer;
use App\Modules\Parties\Actions\SuspendCustomer;
use App\Modules\Parties\Models\Customer;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
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
 * moves `status` — ADR 2026-06-19) is the compliance slice's surface and coexists ADDITIVELY; it is NOT
 * duplicated here. No Hold / KYC / sanctions / Account / Profile verb belongs on this page — each is its own
 * future slice (design Non-Goals).
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
     * The Customer's four status header actions — activate / suspend / reactivate / close, the manual status-FSM
     * path (design D4). All are form-less and carry no confirmation affordance (design D3); each routes to its
     * typed Parties action by the customer `int $id`, never an Eloquent write. `activate` is cross-slice-gated by
     * the Action — a gate-unmet attempt rejects gracefully as a danger notification (design D5).
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
        ];
    }
}
