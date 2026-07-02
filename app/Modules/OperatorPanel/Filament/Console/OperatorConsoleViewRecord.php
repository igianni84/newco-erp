<?php

namespace App\Modules\OperatorPanel\Filament\Console;

use App\Modules\OperatorPanel\Filament\Console\Concerns\SurfacesDomainActions;
use App\Platform\Audit\AuditRecord;
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

    /**
     * Is the page record REJECTION-PENDING — its latest catalog governance audit action an un-remediated
     * rejection (RM-06 / canon MVP-DEC-019; catalog-review-freshness-resubmit design D3/D5)? This is the
     * derived read that gates the re-submit header action's `->visible()` on every catalog console: re-submit is
     * OFFERED only while a rejection is un-remediated and HIDDEN otherwise (a redundant re-submit on a fresh
     * `reviewed` entity is a harmless no-op the operator is not shown). It MIRRORS the Catalog block-gate's read
     * (the domain's `ApprovalGovernance::assertNotRejectionPending`: the entity's latest `audit_records.action`,
     * newest by id, ending in `.rejected`), so the console offers re-submit exactly when the domain would block
     * activation — but it never REIMPLEMENTS the gate (the Catalog backend is the sole enforcement, design L4);
     * it only reads the platform substrate to decide what to surface.
     *
     * Boundary: reads {@see AuditRecord} (platform, imported like Money/I18n), never a Catalog type — the
     * {Models, Actions} cross-module surface holds. The Catalog governance/mechanism classes are named in PROSE
     * (never a `{@see}` type), so Pint's fully_qualified_strict_types cannot re-add a forbidden
     * `Catalog\Lifecycle` import (lessons.md 2026-06-20). Rows are scoped by `entity_type` + `entity_id`: the
     * record's class basename is the canonical entity label the shared lifecycle mechanism stamps on every audit
     * row (a `ProductMaster` record → `ProductMaster`), and that label is globally unique to the catalog entity,
     * so no `module` predicate is needed (which also keeps `App\Modules\Module` off the console's imports). The
     * `is_int/is_string` key guard mirrors that mechanism (a spine entity always keys on a scalar); the
     * `is_string` action guard is load-bearing — a never-audited record returns `null` from `->value()` and
     * `str_ends_with(null, …)` would TypeError under PHP 8.5.
     */
    protected function isRejectionPending(): bool
    {
        $record = $this->getRecord();
        $key = $record->getKey();

        if (! is_int($key) && ! is_string($key)) {
            return false;
        }

        $latest = AuditRecord::query()
            ->where('entity_type', class_basename($record))
            ->where('entity_id', (string) $key)
            ->orderByDesc('id')
            ->value('action');

        return is_string($latest) && str_ends_with($latest, '.rejected');
    }
}
