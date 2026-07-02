<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages;

use App\Modules\Catalog\Actions\ActivateProductVariant;
use App\Modules\Catalog\Actions\RejectProductVariantReview;
use App\Modules\Catalog\Actions\ReopenProductVariant;
use App\Modules\Catalog\Actions\ResubmitProductVariantForReview;
use App\Modules\Catalog\Actions\RetireProductVariant;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource;
use Closure;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

/**
 * ViewProductVariant — the Product Variant console view page (operator-console-catalog-spine, task 3.1; design
 * L1/L4; ADR 2026-06-20). Pure reuse of the {@see OperatorConsoleViewRecord} kit: the base renders the FIVE
 * uniform lifecycle header actions (submit · reject · activate · retire · reopen) from
 * {@see lifecycleInvocations()}, and a Product Variant's ONLY divergence is the visibility-gated re-submit shared
 * by all seven catalog consoles (RM-06 / canon MVP-DEC-019 — the review-freshness re-arm): it is the FIRST
 * hierarchical entity but its parent gate is surfaced FOR FREE (it does not add a header action), and (unlike
 * Product Master) it has NO cascade-retire affordance (scope guard: cascade-retire is Master-only, no
 * `RetireProductVariantCascade` Action ships), so this page overrides `getHeaderActions()` ONLY to append
 * re-submit (spreading `parent::getHeaderActions()`).
 *
 * Every action routes to a Catalog domain action and NEVER writes `lifecycle_state` itself (the
 * no-Eloquent-write rule, task 1.2); the console SURFACES the domain's decision — the from-state guard, the
 * Creator → Reviewer → Approver separation-of-duties floor, AND (new for the hierarchical entities) the
 * activation-cascade gate: activating a Variant whose parent Product Master is not `active` throws the domain's
 * {@see ActivateProductVariant} `ActivationCascadeViolation`, which the wrapper renders as a danger
 * notification (`catalog.gate.parent_not_active`) — the console re-checks the parent NOTHING (design L4).
 * A single-entity retire of a Variant preserves its existing `active` Product Reference children (only NEW
 * activation under the now-`retired` Variant is prevented — the domain's concern, not the console's). There is
 * NO field-edit (the Catalog backend ships no update action — lifecycle TRANSITIONS only, proposal
 * slice-boundary). All copy is localized (invariant 12).
 */
class ViewProductVariant extends OperatorConsoleViewRecord
{
    protected static string $resource = ProductVariantResource::class;

    protected function i18nKey(): string
    {
        return 'product_variant';
    }

    /**
     * The five uniform lifecycle invocations for a Product Variant, each routing to its typed Catalog action
     * (the {Models, Actions} cross-module surface): submit/activate/retire/reopen on the record; reject also
     * carries the operator's notes. {@see recordOf()} narrows the page {@see Model} to a {@see ProductVariant}
     * so each call is fully typed. `activate` routes to {@see ActivateProductVariant}, whose activation-cascade
     * gate (parent Master active) the wrapper surfaces — this page neither evaluates nor branches on it.
     *
     * @return array<string, Closure(Model, string): mixed>
     */
    protected function lifecycleInvocations(): array
    {
        return [
            'submit' => fn (Model $record, string $notes) => app(SubmitProductVariantForReview::class)->handle($this->recordOf(ProductVariant::class, $record)),
            'reject' => fn (Model $record, string $notes) => app(RejectProductVariantReview::class)->handle($this->recordOf(ProductVariant::class, $record), $notes),
            'activate' => fn (Model $record, string $notes) => app(ActivateProductVariant::class)->handle($this->recordOf(ProductVariant::class, $record)),
            'retire' => fn (Model $record, string $notes) => app(RetireProductVariant::class)->handle($this->recordOf(ProductVariant::class, $record)),
            'reopen' => fn (Model $record, string $notes) => app(ReopenProductVariant::class)->handle($this->recordOf(ProductVariant::class, $record)),
        ];
    }

    /**
     * The kit's five uniform lifecycle actions PLUS the visibility-gated re-submit shared by all seven catalog
     * consoles (RM-06 / canon MVP-DEC-019; design D2/D5). Re-submit RE-ARMS the approval flow after a rejection —
     * a `reviewed → reviewed` audit-only decision this page SURFACES via {@see ResubmitProductVariantForReview}
     * (never an Eloquent write). Its `->visible()` is gated to {@see isRejectionPending()} (the derived read):
     * re-submit is OFFERED only while an un-remediated rejection blocks activation, HIDDEN otherwise. The
     * block-gate itself needs no console code — an activation attempt on a rejection-pending Product Variant throws
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
                fn (Model $record, string $notes) => app(ResubmitProductVariantForReview::class)->handle($this->recordOf(ProductVariant::class, $record)),
            )->visible(fn (): bool => $this->isRejectionPending()),
        ];
    }
}
