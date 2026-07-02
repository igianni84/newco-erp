<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource\Pages;

use App\Modules\Catalog\Actions\ActivateCompositeSku;
use App\Modules\Catalog\Actions\RejectCompositeSkuReview;
use App\Modules\Catalog\Actions\ReopenCompositeSku;
use App\Modules\Catalog\Actions\ResubmitCompositeSkuForReview;
use App\Modules\Catalog\Actions\RetireCompositeSku;
use App\Modules\Catalog\Actions\SubmitCompositeSkuForReview;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource;
use Closure;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

/**
 * ViewCompositeSku — the Composite SKU console view page (operator-console-catalog-spine, task 4.1; design
 * L1/L4; ADR 2026-06-20). Pure reuse of the {@see OperatorConsoleViewRecord} kit: the base renders the FIVE
 * uniform lifecycle header actions (submit · reject · activate · retire · reopen) from
 * {@see lifecycleInvocations()}, and a Composite SKU's ONLY divergence is the visibility-gated re-submit shared by
 * all seven catalog consoles (RM-06 / canon MVP-DEC-019 — the review-freshness re-arm): its N-constituent
 * activation-cascade gate (EVERY constituent Product Reference must be `active`) is surfaced FOR FREE (it does not
 * add a header action), and (unlike Product Master) it has NO cascade-retire affordance (scope guard:
 * cascade-retire is Master-only, no `RetireCompositeSkuCascade` Action ships). It is a LEAF within Module 0, so
 * retire carries no within-catalog reference-integrity block either, so this page overrides `getHeaderActions()`
 * ONLY to append re-submit (spreading `parent::getHeaderActions()`).
 *
 * Every action routes to a Catalog domain action and NEVER writes `lifecycle_state` itself (the
 * no-Eloquent-write rule, task 1.2); the console SURFACES the domain's decision — the from-state guard, the
 * Creator → Reviewer → Approver separation-of-duties floor, and the activation-cascade gate (activating a SKU any
 * of whose constituent Product References is not `active` throws the domain's {@see ActivateCompositeSku}
 * `ActivationCascadeViolation`, surfaced via `catalog.gate.parent_not_active`) — the console re-checks NONE of
 * them (design L4). There is NO field-edit (the Catalog backend ships no update action — lifecycle TRANSITIONS
 * only, proposal slice-boundary). All copy is localized (invariant 12).
 */
class ViewCompositeSku extends OperatorConsoleViewRecord
{
    protected static string $resource = CompositeSkuResource::class;

    protected function i18nKey(): string
    {
        return 'composite_sku';
    }

    /**
     * The five uniform lifecycle invocations for a Composite SKU, each routing to its typed Catalog action (the
     * {Models, Actions} cross-module surface): submit/activate/retire/reopen on the record; reject also carries
     * the operator's notes. {@see recordOf()} narrows the page {@see Model} to a {@see CompositeSku} so each call
     * is fully typed. `activate` routes to {@see ActivateCompositeSku}, whose N-constituent activation-cascade
     * gate (EVERY constituent Product Reference active) the wrapper surfaces; `retire` routes to
     * {@see RetireCompositeSku}, a leaf retire with no within-catalog block — this page neither evaluates nor
     * branches on the gate.
     *
     * @return array<string, Closure(Model, string): mixed>
     */
    protected function lifecycleInvocations(): array
    {
        return [
            'submit' => fn (Model $record, string $notes) => app(SubmitCompositeSkuForReview::class)->handle($this->recordOf(CompositeSku::class, $record)),
            'reject' => fn (Model $record, string $notes) => app(RejectCompositeSkuReview::class)->handle($this->recordOf(CompositeSku::class, $record), $notes),
            'activate' => fn (Model $record, string $notes) => app(ActivateCompositeSku::class)->handle($this->recordOf(CompositeSku::class, $record)),
            'retire' => fn (Model $record, string $notes) => app(RetireCompositeSku::class)->handle($this->recordOf(CompositeSku::class, $record)),
            'reopen' => fn (Model $record, string $notes) => app(ReopenCompositeSku::class)->handle($this->recordOf(CompositeSku::class, $record)),
        ];
    }

    /**
     * The kit's five uniform lifecycle actions PLUS the visibility-gated re-submit shared by all seven catalog
     * consoles (RM-06 / canon MVP-DEC-019; design D2/D5). Re-submit RE-ARMS the approval flow after a rejection —
     * a `reviewed → reviewed` audit-only decision this page SURFACES via {@see ResubmitCompositeSkuForReview}
     * (never an Eloquent write). Its `->visible()` is gated to {@see isRejectionPending()} (the derived read):
     * re-submit is OFFERED only while an un-remediated rejection blocks activation, HIDDEN otherwise. The
     * block-gate itself needs no console code — an activation attempt on a rejection-pending Composite SKU throws
     * `ApprovalGovernanceViolation`, which the kit's `surfaceLifecycleOutcome` renders as an `action_failed` danger
     * notification for free.
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
                fn (Model $record, string $notes) => app(ResubmitCompositeSkuForReview::class)->handle($this->recordOf(CompositeSku::class, $record)),
            )->visible(fn (): bool => $this->isRejectionPending()),
        ];
    }
}
