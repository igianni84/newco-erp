<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\Concerns\SurfacesDomainActions;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource;
use App\Modules\Parties\Actions\ActivateProducerAgreement;
use App\Modules\Parties\Actions\TerminateProducerAgreement;
use App\Modules\Parties\Models\ProducerAgreement;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * ViewProducerAgreement — the ProducerAgreement console view page (operator-console-parties-supply-side, task
 * 9.1; design D1/D3/D4/D5/D8; ADR 2026-06-20). The THIRD non-catalog operator console: like the Producer and
 * Club console view pages it reuses the kit at the TRAIT level, NOT through the catalog-shaped
 * {@see OperatorConsoleViewRecord} base — that base hard-codes the five catalog governance verbs (submit ·
 * reject · activate[SoD] · retire · reopen), which do not fit the agreement FSM `draft → active →
 * superseded | terminated`. Instead this page extends Filament's {@see ViewRecord} directly, `use`s
 * {@see SurfacesDomainActions}, and assembles its OWN verb set in {@see getHeaderActions()} (D1). The read-only
 * infolist + list table live on {@see ProducerAgreementResource}.
 *
 * The page assembles the agreement's TWO status verbs (D1/D3/D8): activate (`draft → active`) and terminate
 * (`active → terminated`). There is NO supersede verb — supersession is NOT an operator action but a SIDE-EFFECT
 * of activation (D8): {@see ActivateProducerAgreement} enforces BR-K-Agreement-1 (at most one active agreement
 * per `(producer_id, club_id)` scope) by superseding any prior active in the same scope INLINE, recording the
 * derived `ProducerAgreementSuperseded` itself (caused by its own `ProducerAgreementActivated`). Surfacing a
 * `supersede` verb would let an operator drive that transition out of band — it is the domain's, not the
 * console's. Both verbs are built with {@see SurfacesDomainActions::lifecycleAction()} as FORM-LESS actions with
 * NO confirmation affordance (D3): Admin Panel § 5.2 lists neither agreement transition among the multi-actor or
 * the notes-collecting patterns — agreement lifecycle is single-operator.
 *
 * Each invocation has the uniform `fn (Model $record, string $notes)` signature the trait expects (`$notes`
 * unused — no agreement action has a form) and routes to a Parties domain action, which takes the agreement
 * `int $id` — so {@see SurfacesDomainActions::recordOf()} narrows `Model → ProducerAgreement` and the call passes
 * `->id` (D4). The console NEVER writes `status` itself (the no-Eloquent-write rule); it SURFACES the domain's
 * decision. An out-of-state transition (activate not from `draft`, terminate not from `active`) throws
 * `IllegalProducerAgreementTransition` — a `RuntimeException` — caught by base type in
 * {@see SurfacesDomainActions::surfaceLifecycleOutcome()} and rendered as the `notifications.action_failed`
 * danger title with the domain's already-localized message as the body — so the console imports only
 * {@see ProducerAgreement} + the `Parties\Actions\*` it invokes (the {Models, Actions} carve-out), never a
 * `Parties\Exceptions` type (D5; named here in PROSE so Pint's `fully_qualified_strict_types` cannot re-add a
 * forbidden import — lessons.md, 2026-06-20). Terminating an agreement does NOT cascade onto the Producer (§
 * 4.6.1) — that is a domain fact owned by the action, not surfaced here. All copy is localized through
 * `i18nKey()` (invariant 12).
 */
class ViewProducerAgreement extends ViewRecord
{
    use SurfacesDomainActions;

    protected static string $resource = ProducerAgreementResource::class;

    protected function i18nKey(): string
    {
        return 'producer_agreement';
    }

    /**
     * The agreement's two header actions — activate (`draft → active`) and terminate (`active → terminated`).
     * Both are form-less and carry no confirmation affordance (D3); each routes to its typed Parties action by
     * the agreement `int $id` (D4), never an Eloquent write. There is no supersede verb — supersession is the
     * inline side-effect of {@see ActivateProducerAgreement} (D8), never a standalone operator action.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->lifecycleAction('activate', 'activated', fn (Model $record, string $notes) => app(ActivateProducerAgreement::class)->handle($this->recordOf(ProducerAgreement::class, $record)->id)),
            $this->lifecycleAction('terminate', 'terminated', fn (Model $record, string $notes) => app(TerminateProducerAgreement::class)->handle($this->recordOf(ProducerAgreement::class, $record)->id)),
        ];
    }
}
