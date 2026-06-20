<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\Concerns\SurfacesDomainActions;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource;
use App\Modules\Parties\Actions\ActivateProducer;
use App\Modules\Parties\Actions\RetireProducer;
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
 * This task wires the two STATUS verbs — activate (`draft → active`) and retire (`active → retired`, which
 * CASCADES sunset onto the Producer's operated Clubs). Both are built with {@see SurfacesDomainActions::lifecycleAction()}
 * as FORM-LESS actions with NO confirmation affordance (D3): Producer activation is KYC-gated, not a
 * Creator → Reviewer → Approver separation-of-duties transition, so it carries no "second actor" modal; and
 * neither verb collects notes. The four KYC verbs (require/waive/verify/reject) are appended in task 4.1.
 *
 * Each invocation has the uniform `fn (Model $record, string $notes)` signature the trait expects (`$notes`
 * unused — no Producer action has a form) and routes to a Parties domain action, which (unlike the catalog
 * actions, that take the model) takes the producer `int $id` — so {@see SurfacesDomainActions::recordOf()}
 * narrows `Model → Producer` and the call passes `->id` (D4). The console NEVER writes `status` itself (the
 * no-Eloquent-write rule); it SURFACES the domain's decision. An out-of-state transition throws
 * `IllegalProducerTransition` (a `RuntimeException`), caught by base type in
 * {@see SurfacesDomainActions::surfaceLifecycleOutcome()} and rendered as the `notifications.action_failed`
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
     * The Producer status header actions — activate and retire (task 3.1). Both are form-less and carry no
     * confirmation affordance (D3); each routes to its typed Parties action by the producer `int $id` (D4),
     * never an Eloquent write. The four KYC verbs are appended in task 4.1.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->lifecycleAction('activate', 'activated', fn (Model $record, string $notes) => app(ActivateProducer::class)->handle($this->recordOf(Producer::class, $record)->id)),
            $this->lifecycleAction('retire', 'retired', fn (Model $record, string $notes) => app(RetireProducer::class)->handle($this->recordOf(Producer::class, $record)->id)),
        ];
    }
}
