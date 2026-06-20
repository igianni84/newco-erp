<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\Concerns\SurfacesDomainActions;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource;
use App\Modules\Parties\Actions\ActivateProducer;
use App\Modules\Parties\Actions\RecordProducerKycRejected;
use App\Modules\Parties\Actions\RecordProducerKycVerified;
use App\Modules\Parties\Actions\RequireProducerKyc;
use App\Modules\Parties\Actions\RetireProducer;
use App\Modules\Parties\Actions\WaiveProducerKyc;
use App\Modules\Parties\Models\Producer;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * ViewProducer — the Producer console view page (operator-console-parties-producer, task 3.1; design
 * D1/D3/D4/D5; ADR 2026-06-20). The FIRST non-catalog operator console: it reuses the kit at the TRAIT level,
 * NOT through the catalog-shaped {@see OperatorConsoleViewRecord}
 * base — that base hard-codes the five catalog governance verbs (submit · reject · activate[SoD] · retire ·
 * reopen), which do not fit Producer's `draft → active → retired` FSM. Instead this page extends Filament's
 * {@see ViewRecord} directly, `use`s {@see SurfacesDomainActions}, and assembles its OWN verb set in
 * {@see getHeaderActions()} (D1). The read-only infolist + list table live on {@see ProducerResource}.
 *
 * The page assembles all SIX Producer verbs (D1/D3): the two STATUS verbs — activate (`draft → active`) and
 * retire (`active → retired`, which CASCADES sunset onto the Producer's operated Clubs) — wired in task 3.1, and
 * the four KYC verbs — require (`not_required`/NULL → `pending`), waive (any outstanding state → `not_required`),
 * verify and reject (`pending → verified`/`rejected`) — appended in task 4.1. The KYC FSM is SEPARATE from the
 * status FSM (a KYC verb never moves `status`) and is AUDIT-ONLY: its actions record NO domain event and place NO
 * Hold (§ 4.4). All six are built with {@see SurfacesDomainActions::lifecycleAction()} as FORM-LESS actions with
 * NO confirmation affordance (D3): Producer activation is KYC-gated, not a Creator → Reviewer → Approver
 * separation-of-duties transition, so it carries no "second actor" modal; and no verb collects notes.
 *
 * Each invocation has the uniform `fn (Model $record, string $notes)` signature the trait expects (`$notes`
 * unused — no Producer action has a form) and routes to a Parties domain action, which (unlike the catalog
 * actions, that take the model) takes the producer `int $id` — so {@see SurfacesDomainActions::recordOf()}
 * narrows `Model → Producer` and the call passes `->id` (D4). The console NEVER writes `status`/`kyc_status`
 * itself (the no-Eloquent-write rule); it SURFACES the domain's decision. An out-of-state transition throws
 * `IllegalProducerTransition` (status) or `IllegalKycTransition` (KYC) — both `RuntimeException`s — caught by base
 * type in {@see SurfacesDomainActions::surfaceLifecycleOutcome()} and rendered as the `notifications.action_failed`
 * danger title with the domain's already-localized message as the body — so the console imports only
 * {@see Producer} + the `Parties\Actions\*` it invokes (the {Models, Actions} carve-out), never a
 * `Parties\Exceptions` type (D5; named here in PROSE so Pint's `fully_qualified_strict_types` cannot re-add a
 * forbidden import — lessons.md, 2026-06-20). All copy is localized through `i18nKey()` (invariant 12).
 */
class ViewProducer extends ViewRecord
{
    use SurfacesDomainActions;

    protected static string $resource = ProducerResource::class;

    protected function i18nKey(): string
    {
        return 'producer';
    }

    /**
     * The Producer's six header actions — the two status verbs activate/retire (task 3.1) followed by the four
     * KYC verbs require/waive/verify/reject (task 4.1). All are form-less and carry no confirmation affordance
     * (D3); each routes to its typed Parties action by the producer `int $id` (D4), never an Eloquent write. The
     * KYC verbs are audit-only — the domain records no event and places no Hold — and never move `status`.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->lifecycleAction('activate', 'activated', fn (Model $record, string $notes) => app(ActivateProducer::class)->handle($this->recordOf(Producer::class, $record)->id)),
            $this->lifecycleAction('retire', 'retired', fn (Model $record, string $notes) => app(RetireProducer::class)->handle($this->recordOf(Producer::class, $record)->id)),
            $this->lifecycleAction('requireKyc', 'kyc_required', fn (Model $record, string $notes) => app(RequireProducerKyc::class)->handle($this->recordOf(Producer::class, $record)->id)),
            $this->lifecycleAction('waiveKyc', 'kyc_waived', fn (Model $record, string $notes) => app(WaiveProducerKyc::class)->handle($this->recordOf(Producer::class, $record)->id)),
            $this->lifecycleAction('verifyKyc', 'kyc_verified', fn (Model $record, string $notes) => app(RecordProducerKycVerified::class)->handle($this->recordOf(Producer::class, $record)->id)),
            $this->lifecycleAction('rejectKyc', 'kyc_rejected', fn (Model $record, string $notes) => app(RecordProducerKycRejected::class)->handle($this->recordOf(Producer::class, $record)->id)),
        ];
    }
}
