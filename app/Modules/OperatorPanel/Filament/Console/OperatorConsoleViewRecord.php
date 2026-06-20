<?php

namespace App\Modules\OperatorPanel\Filament\Console;

use App\Modules\OperatorPanel\Filament\Console\Concerns\SurfacesDomainActions;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * OperatorConsoleViewRecord — the shared base view page for every catalog operator console
 * (operator-console-catalog-spine, task 1.1; ADR 2026-06-20; design L1/L2/L4; it resolves the predecessor's
 * design L9 deferral). It renders the FIVE uniform lifecycle header actions — submit · reject (notes) ·
 * activate (second-actor affordance) · retire · reopen — from the per-entity domain-action invocations the
 * subclass supplies in {@see lifecycleInvocations()}, wiring each through the {@see SurfacesDomainActions}
 * concern so a write routes to the module's domain action and a domain rejection surfaces as a danger
 * notification (never an Eloquent write — the no-Eloquent-write rule, task 1.2; never a reimplemented gate —
 * design L4).
 *
 * A per-entity view page sets `$resource`, returns its `operator_console.<entity>` root from {@see i18nKey()}
 * and its five invocations from {@see lifecycleInvocations()}. Entity-specific extras (e.g. Product Master's
 * operator-driven cascade retire) are appended by overriding {@see getHeaderActions()} and spreading
 * `parent::getHeaderActions()` — the base owns the uniform five, the subclass owns only its divergence
 * (design L6). The read-only list/infolist lives on the {@see \Filament\Resources\Resource}, not here.
 */
abstract class OperatorConsoleViewRecord extends ViewRecord
{
    use SurfacesDomainActions;

    /**
     * The per-entity domain-action invocations keyed by lifecycle verb — `submit`, `reject`, `activate`,
     * `retire`, `reopen`. Each closure receives the page record and the reject notes (`''` for the form-less
     * verbs) and routes to the entity's typed Catalog action via {@see recordOf()} — e.g.
     * `fn (Model $record) => app(SubmitFormatForReview::class)->handle($this->recordOf(Format::class, $record))`.
     *
     * @return array<string, Closure(Model, string): mixed>
     */
    abstract protected function lifecycleInvocations(): array;

    /**
     * The five uniform lifecycle header actions. Reject carries the notes form; activate carries the
     * "second actor required" confirmation affordance (the SoD floor the domain enforces and this surfaces,
     * design L4). A subclass appends entity-specific actions by spreading `parent::getHeaderActions()`.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        $invocations = $this->lifecycleInvocations();

        return [
            $this->lifecycleAction('submit', 'submitted', $invocations['submit']),
            $this->lifecycleAction('reject', 'rejected', $invocations['reject'], form: [
                Textarea::make('notes')
                    ->label((string) __('operator_console.'.$this->i18nKey().'.fields.rejection_notes'))
                    ->required(),
            ]),
            $this->lifecycleAction('activate', 'activated', $invocations['activate'], confirmationKey: 'affordance.second_actor'),
            $this->lifecycleAction('retire', 'retired', $invocations['retire']),
            $this->lifecycleAction('reopen', 'reopened', $invocations['reopen']),
        ];
    }
}
