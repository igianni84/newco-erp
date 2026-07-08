<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource\Pages;

use App\Modules\Catalog\Actions\ActivateFormat;
use App\Modules\Catalog\Actions\RejectFormatReview;
use App\Modules\Catalog\Actions\ReopenFormat;
use App\Modules\Catalog\Actions\ResubmitFormatForReview;
use App\Modules\Catalog\Actions\RetireFormat;
use App\Modules\Catalog\Actions\SubmitFormatForReview;
use App\Modules\Catalog\Models\Format;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource;
use Closure;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

/**
 * ViewFormat — the Format console view page (operator-console-catalog-spine, task 2.1; design L1/L4; ADR
 * 2026-06-20). Pure reuse of the {@see OperatorConsoleViewRecord} kit: the base renders the FIVE uniform
 * lifecycle header actions (submit · reject · activate · retire · reopen) from {@see lifecycleInvocations()},
 * and Format's ONLY divergence is the visibility-gated re-submit shared by all seven catalog consoles (RM-06 /
 * canon MVP-DEC-019 — the review-freshness re-arm): it is a standalone reference entity with no parent gate and
 * (unlike Product Master) NO cascade-retire affordance (scope guard: cascade-retire is Master-only, no
 * `RetireFormatCascade` Action ships), so this page overrides `getHeaderActions()` ONLY to append re-submit
 * (spreading `parent::getHeaderActions()`).
 *
 * Every action routes to a Catalog domain action and NEVER writes `lifecycle_state` itself (the
 * no-Eloquent-write rule, task 1.2); the console SURFACES the domain's decision — the from-state guard and the
 * Creator → Reviewer → Approver separation-of-duties floor — it reimplements none of them (design L4). There
 * is NO field-edit: lifecycle TRANSITIONS only, because the Catalog backend ships no update Action for this
 * entity (catalog-module-0-completeness-sweep added edit Actions only for a Master's identity, a Composite's
 * composition and a Variant's enrichment + whitelist — design D2); the pages that DO carry a field-edit
 * surface it as a modal header action, never as an Edit page (design D8). All copy is localized (invariant
 * 12).
 */
class ViewFormat extends OperatorConsoleViewRecord
{
    protected static string $resource = FormatResource::class;

    protected function i18nKey(): string
    {
        return 'format';
    }

    /**
     * The five uniform lifecycle invocations for Format, each routing to its typed Catalog action (the
     * {Models, Actions} cross-module surface): submit/activate/retire/reopen on the record; reject also carries
     * the operator's notes. {@see recordOf()} narrows the page {@see Model} to a {@see Format} so each call is
     * fully typed.
     *
     * @return array<string, Closure(Model, string): mixed>
     */
    protected function lifecycleInvocations(): array
    {
        return [
            'submit' => fn (Model $record, string $notes) => app(SubmitFormatForReview::class)->handle($this->recordOf(Format::class, $record)),
            'reject' => fn (Model $record, string $notes) => app(RejectFormatReview::class)->handle($this->recordOf(Format::class, $record), $notes),
            'activate' => fn (Model $record, string $notes) => app(ActivateFormat::class)->handle($this->recordOf(Format::class, $record)),
            'retire' => fn (Model $record, string $notes) => app(RetireFormat::class)->handle($this->recordOf(Format::class, $record)),
            'reopen' => fn (Model $record, string $notes) => app(ReopenFormat::class)->handle($this->recordOf(Format::class, $record)),
        ];
    }

    /**
     * The kit's five uniform lifecycle actions PLUS the visibility-gated re-submit shared by all seven catalog
     * consoles (RM-06 / canon MVP-DEC-019 and its edit leg; design D2/D5 + catalog-module-0-completeness-sweep
     * D4/D9). Re-submit RE-ARMS the approval flow after a rejection OR an identity edit — a
     * `reviewed → reviewed` audit-only decision this page SURFACES via {@see ResubmitFormatForReview} (never an
     * Eloquent write). Its `->visible()` is gated to {@see isReviewStale()} (the derived, verb-filtered read):
     * re-submit is OFFERED only while the entity is REVIEW-STALE — its latest review-freshness-relevant audit
     * action is an un-remediated rejection or an un-re-reviewed identity edit — and HIDDEN otherwise. The
     * block-gate itself needs no console code: an activation attempt on a review-stale Format throws
     * `ApprovalGovernanceViolation`, which the kit's `surfaceLifecycleOutcome` renders as an `action_failed`
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
                fn (Model $record, string $notes) => app(ResubmitFormatForReview::class)->handle($this->recordOf(Format::class, $record)),
            )->visible(fn (): bool => $this->isReviewStale()),
        ];
    }
}
