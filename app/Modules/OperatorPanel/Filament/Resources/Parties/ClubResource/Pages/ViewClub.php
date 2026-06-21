<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\Concerns\SurfacesDomainActions;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource;
use App\Modules\Parties\Actions\CloseClub;
use App\Modules\Parties\Actions\SunsetClub;
use App\Modules\Parties\Models\Club;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * ViewClub — the Club console view page (operator-console-parties-supply-side, task 4.1; design
 * D1/D3/D4/D5/D9; ADR 2026-06-20). The SECOND non-catalog operator console: like the Producer console's view
 * page it reuses the kit at the TRAIT level, NOT through the catalog-shaped {@see OperatorConsoleViewRecord}
 * base — that base hard-codes the five catalog governance verbs (submit · reject · activate[SoD] · retire ·
 * reopen), which do not fit the Club FSM `active → sunset → closed`. Instead this page extends Filament's
 * {@see ViewRecord} directly, `use`s {@see SurfacesDomainActions}, and assembles its OWN verb set in
 * {@see getHeaderActions()} (D1). The read-only infolist + list table live on {@see ClubResource}.
 *
 * The page assembles the Club's TWO status verbs (D1/D3/D9): sunset (`active → sunset`) and close
 * (`sunset → closed`). A Club is born `active` by the `CreateClub` action, so there is NO activate verb (D9);
 * and close is reachable ONLY from `sunset` — `CloseClub` rejects a close on an `active` Club, which must first
 * pass through sunset. Both verbs are built with {@see SurfacesDomainActions::lifecycleAction()} as FORM-LESS
 * actions with NO confirmation affordance (D3): Admin Panel § 5.2 lists Club among neither the multi-actor nor
 * the notes-collecting patterns — Club lifecycle is single-operator.
 *
 * Each invocation has the uniform `fn (Model $record, string $notes)` signature the trait expects (`$notes`
 * unused — no Club action has a form) and routes to a Parties domain action, which takes the club `int $id` — so
 * {@see SurfacesDomainActions::recordOf()} narrows `Model → Club` and the call passes `->id` (D4). The console
 * NEVER writes `status` itself (the no-Eloquent-write rule); it SURFACES the domain's decision. An out-of-state
 * transition (sunset not from `active`, close not from `sunset`) throws `IllegalClubTransition` — a
 * `RuntimeException` — caught by base type in {@see SurfacesDomainActions::surfaceLifecycleOutcome()} and
 * rendered as the `notifications.action_failed` danger title with the domain's already-localized message as the
 * body — so the console imports only {@see Club} + the `Parties\Actions\*` it invokes (the {Models, Actions}
 * carve-out), never a `Parties\Exceptions` type (D5; named here in PROSE so Pint's `fully_qualified_strict_types`
 * cannot re-add a forbidden import — lessons.md, 2026-06-20). All copy is localized through `i18nKey()`
 * (invariant 12).
 */
class ViewClub extends ViewRecord
{
    use SurfacesDomainActions;

    protected static string $resource = ClubResource::class;

    protected function i18nKey(): string
    {
        return 'club';
    }

    /**
     * The Club's two header actions — sunset (`active → sunset`) and close (`sunset → closed`). Both are
     * form-less and carry no confirmation affordance (D3); each routes to its typed Parties action by the club
     * `int $id` (D4), never an Eloquent write. There is no activate verb (a Club is born `active` — D9) and no
     * supersede side-effect; close is reachable only from `sunset` (the domain rejects close-from-`active`).
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->lifecycleAction('sunset', 'sunset', fn (Model $record, string $notes) => app(SunsetClub::class)->handle($this->recordOf(Club::class, $record)->id)),
            $this->lifecycleAction('close', 'closed', fn (Model $record, string $notes) => app(CloseClub::class)->handle($this->recordOf(Club::class, $record)->id)),
        ];
    }
}
