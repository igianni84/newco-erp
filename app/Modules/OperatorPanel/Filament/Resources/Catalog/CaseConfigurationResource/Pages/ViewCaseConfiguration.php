<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\CaseConfigurationResource\Pages;

use App\Modules\Catalog\Actions\ActivateCaseConfiguration;
use App\Modules\Catalog\Actions\RejectCaseConfigurationReview;
use App\Modules\Catalog\Actions\ReopenCaseConfiguration;
use App\Modules\Catalog\Actions\ResubmitCaseConfigurationForReview;
use App\Modules\Catalog\Actions\RetireCaseConfiguration;
use App\Modules\Catalog\Actions\SubmitCaseConfigurationForReview;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CaseConfigurationResource;
use Closure;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

/**
 * ViewCaseConfiguration — the Case Configuration console view page (operator-console-catalog-spine, task 2.2;
 * design L1/L4; ADR 2026-06-20). Pure reuse of the {@see OperatorConsoleViewRecord} kit: the base renders the
 * FIVE uniform lifecycle header actions (submit · reject · activate · retire · reopen) from
 * {@see lifecycleInvocations()}, and Case Configuration's ONLY divergence is the visibility-gated re-submit shared
 * by all seven catalog consoles (RM-06 / canon MVP-DEC-019 — the review-freshness re-arm): it is a standalone
 * reference entity with no parent gate and (unlike Product Master) NO cascade-retire affordance (scope guard:
 * cascade-retire is Master-only, no `RetireCaseConfigurationCascade` Action ships), so this page overrides
 * `getHeaderActions()` ONLY to append re-submit (spreading `parent::getHeaderActions()`).
 *
 * Every action routes to a Catalog domain action and NEVER writes `lifecycle_state` itself (the
 * no-Eloquent-write rule, task 1.2); the console SURFACES the domain's decision — the from-state guard, the
 * Creator → Reviewer → Approver separation-of-duties floor, AND the retire reference-integrity block (a Case
 * Configuration referenced by an `active` Sellable SKU cannot be retired out from under it — the wrapper
 * renders the domain's {@see RetireCaseConfiguration} rejection as a danger notification) — it reimplements none
 * of them (design L4). There is NO field-edit (the Catalog backend ships no update action — lifecycle
 * TRANSITIONS only, proposal slice-boundary). All copy is localized (invariant 12).
 */
class ViewCaseConfiguration extends OperatorConsoleViewRecord
{
    protected static string $resource = CaseConfigurationResource::class;

    protected function i18nKey(): string
    {
        return 'case_configuration';
    }

    /**
     * The five uniform lifecycle invocations for a Case Configuration, each routing to its typed Catalog action
     * (the {Models, Actions} cross-module surface): submit/activate/retire/reopen on the record; reject also
     * carries the operator's notes. {@see recordOf()} narrows the page {@see Model} to a {@see CaseConfiguration}
     * so each call is fully typed.
     *
     * @return array<string, Closure(Model, string): mixed>
     */
    protected function lifecycleInvocations(): array
    {
        return [
            'submit' => fn (Model $record, string $notes) => app(SubmitCaseConfigurationForReview::class)->handle($this->recordOf(CaseConfiguration::class, $record)),
            'reject' => fn (Model $record, string $notes) => app(RejectCaseConfigurationReview::class)->handle($this->recordOf(CaseConfiguration::class, $record), $notes),
            'activate' => fn (Model $record, string $notes) => app(ActivateCaseConfiguration::class)->handle($this->recordOf(CaseConfiguration::class, $record)),
            'retire' => fn (Model $record, string $notes) => app(RetireCaseConfiguration::class)->handle($this->recordOf(CaseConfiguration::class, $record)),
            'reopen' => fn (Model $record, string $notes) => app(ReopenCaseConfiguration::class)->handle($this->recordOf(CaseConfiguration::class, $record)),
        ];
    }

    /**
     * The kit's five uniform lifecycle actions PLUS the visibility-gated re-submit shared by all seven catalog
     * consoles (RM-06 / canon MVP-DEC-019; design D2/D5). Re-submit RE-ARMS the approval flow after a rejection —
     * a `reviewed → reviewed` audit-only decision this page SURFACES via {@see ResubmitCaseConfigurationForReview}
     * (never an Eloquent write). Its `->visible()` is gated to {@see isRejectionPending()} (the derived read):
     * re-submit is OFFERED only while an un-remediated rejection blocks activation, HIDDEN otherwise. The
     * block-gate itself needs no console code — an activation attempt on a rejection-pending Case Configuration
     * throws `ApprovalGovernanceViolation`, which the kit's `surfaceLifecycleOutcome` renders as an `action_failed`
     * danger notification for free.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            $this->lifecycleAction(
                'resubmit',
                'resubmitted',
                fn (Model $record, string $notes) => app(ResubmitCaseConfigurationForReview::class)->handle($this->recordOf(CaseConfiguration::class, $record)),
            )->visible(fn (): bool => $this->isRejectionPending()),
        ];
    }
}
