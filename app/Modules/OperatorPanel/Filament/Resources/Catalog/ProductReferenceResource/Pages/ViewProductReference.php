<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource\Pages;

use App\Modules\Catalog\Actions\ActivateProductReference;
use App\Modules\Catalog\Actions\RejectProductReferenceReview;
use App\Modules\Catalog\Actions\ReopenProductReference;
use App\Modules\Catalog\Actions\ResubmitProductReferenceForReview;
use App\Modules\Catalog\Actions\RetireProductReference;
use App\Modules\Catalog\Actions\SubmitProductReferenceForReview;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource;
use Closure;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

/**
 * ViewProductReference — the Product Reference console view page (operator-console-catalog-spine, task 3.2;
 * design L1/L4; ADR 2026-06-20). Pure reuse of the {@see OperatorConsoleViewRecord} kit: the base renders the
 * FIVE uniform lifecycle header actions (submit · reject · activate · retire · reopen) from
 * {@see lifecycleInvocations()}, and a Product Reference's ONLY divergence is the visibility-gated re-submit shared
 * by all seven catalog consoles (RM-06 / canon MVP-DEC-019 — the review-freshness re-arm): its parent gate (TWO
 * parents: Variant AND Format) is surfaced FOR FREE (it does not add a header action), and (unlike Product Master)
 * it has NO cascade-retire affordance (scope guard: cascade-retire is Master-only, no
 * `RetireProductReferenceCascade` Action ships), so this page overrides `getHeaderActions()` ONLY to append
 * re-submit (spreading `parent::getHeaderActions()`).
 *
 * Every action routes to a Catalog domain action and NEVER writes `lifecycle_state` itself (the
 * no-Eloquent-write rule, task 1.2); the console SURFACES the domain's decision — the from-state guard, the
 * Creator → Reviewer → Approver separation-of-duties floor, the activation-cascade gate (activating a PR whose
 * parent Variant OR Format is not `active` throws the domain's {@see ActivateProductReference}
 * `ActivationCascadeViolation`, surfaced via `catalog.gate.parent_not_active`), AND the retire
 * reference-integrity block: retiring a PR still referenced by an `active` Sellable or Composite SKU throws the
 * domain's {@see RetireProductReference} `RetirementReferenceIntegrityViolation`, which the wrapper renders as a
 * danger notification (`catalog.retirement.blocked_by_active_references`) — the console re-checks NONE of them
 * (design L4). There is NO field-edit (the Catalog backend ships no update action — lifecycle TRANSITIONS only,
 * proposal slice-boundary). All copy is localized (invariant 12).
 */
class ViewProductReference extends OperatorConsoleViewRecord
{
    protected static string $resource = ProductReferenceResource::class;

    protected function i18nKey(): string
    {
        return 'product_reference';
    }

    /**
     * The five uniform lifecycle invocations for a Product Reference, each routing to its typed Catalog action
     * (the {Models, Actions} cross-module surface): submit/activate/retire/reopen on the record; reject also
     * carries the operator's notes. {@see recordOf()} narrows the page {@see Model} to a {@see ProductReference}
     * so each call is fully typed. `activate` routes to {@see ActivateProductReference}, whose activation-cascade
     * gate (parent Variant AND Format active) the wrapper surfaces; `retire` routes to
     * {@see RetireProductReference}, whose reference-integrity block (no `active` Sellable/Composite SKU) the
     * wrapper surfaces — this page neither evaluates nor branches on either.
     *
     * @return array<string, Closure(Model, string): mixed>
     */
    protected function lifecycleInvocations(): array
    {
        return [
            'submit' => fn (Model $record, string $notes) => app(SubmitProductReferenceForReview::class)->handle($this->recordOf(ProductReference::class, $record)),
            'reject' => fn (Model $record, string $notes) => app(RejectProductReferenceReview::class)->handle($this->recordOf(ProductReference::class, $record), $notes),
            'activate' => fn (Model $record, string $notes) => app(ActivateProductReference::class)->handle($this->recordOf(ProductReference::class, $record)),
            'retire' => fn (Model $record, string $notes) => app(RetireProductReference::class)->handle($this->recordOf(ProductReference::class, $record)),
            'reopen' => fn (Model $record, string $notes) => app(ReopenProductReference::class)->handle($this->recordOf(ProductReference::class, $record)),
        ];
    }

    /**
     * The kit's five uniform lifecycle actions PLUS the visibility-gated re-submit shared by all seven catalog
     * consoles (RM-06 / canon MVP-DEC-019 and its edit leg; design D2/D5 + catalog-module-0-completeness-sweep
     * D4/D9). Re-submit RE-ARMS the approval flow after a rejection OR an identity edit — a
     * `reviewed → reviewed` audit-only decision this page SURFACES via {@see ResubmitProductReferenceForReview} (never an
     * Eloquent write). Its `->visible()` is gated to {@see isReviewStale()} (the derived, verb-filtered read):
     * re-submit is OFFERED only while the entity is REVIEW-STALE — its latest review-freshness-relevant audit
     * action is an un-remediated rejection or an un-re-reviewed identity edit — and HIDDEN otherwise. The
     * block-gate itself needs no console code: an activation attempt on a review-stale Product Reference throws
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
                fn (Model $record, string $notes) => app(ResubmitProductReferenceForReview::class)->handle($this->recordOf(ProductReference::class, $record)),
            )->visible(fn (): bool => $this->isReviewStale()),
        ];
    }
}
