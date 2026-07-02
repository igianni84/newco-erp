<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages;

use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\RejectProductMasterReview;
use App\Modules\Catalog\Actions\ReopenProductMaster;
use App\Modules\Catalog\Actions\ResubmitProductMasterForReview;
use App\Modules\Catalog\Actions\RetireProductMaster;
use App\Modules\Catalog\Actions\RetireProductMasterCascade;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource;
use Closure;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

/**
 * ViewProductMaster — the Product Master console view page, retrofitted onto the shared
 * {@see OperatorConsoleViewRecord} kit (operator-console-catalog-spine, task 1.1; ADR 2026-06-20; design
 * L1/L2/L6). It is now "the kit + the Master-only cascade-retire extension": the base renders the five uniform
 * lifecycle actions (submit · reject · activate · retire · reopen) from {@see lifecycleInvocations()}, and this
 * page appends the visibility-gated re-submit (RM-06 / canon MVP-DEC-019 — the review-freshness re-arm,
 * shared by all seven catalog consoles) and Master's operator-driven cascade retire (the one catalog entity
 * with a cascade, so it stays Master-only — design L6). The producer picker (create form) is the Resource's
 * extension, not here.
 *
 * Every action routes to a Catalog domain action and NEVER writes `lifecycle_state` itself (the
 * no-Eloquent-write rule, task 1.2); the console SURFACES the domain's decision — the from-state guard, the
 * Creator → Reviewer → Approver separation-of-duties floor, the Producer activation gate, the cascade ordering
 * — it reimplements none of them (design L4). There is still NO field-edit (the Catalog backend ships no update
 * action — lifecycle TRANSITIONS only, proposal slice-boundary). All copy is localized (invariant 12).
 */
class ViewProductMaster extends OperatorConsoleViewRecord
{
    protected static string $resource = ProductMasterResource::class;

    protected function i18nKey(): string
    {
        return 'product_master';
    }

    /**
     * The five uniform lifecycle invocations for Product Master, each routing to its typed Catalog action (the
     * {Models, Actions} cross-module surface, task 1.3): submit/activate/retire/reopen on the record; reject
     * also carries the operator's notes. {@see recordOf()} narrows the page {@see Model} to a
     * {@see ProductMaster} so each call is fully typed.
     *
     * @return array<string, Closure(Model, string): mixed>
     */
    protected function lifecycleInvocations(): array
    {
        return [
            'submit' => fn (Model $record, string $notes) => app(SubmitProductMasterForReview::class)->handle($this->recordOf(ProductMaster::class, $record)),
            'reject' => fn (Model $record, string $notes) => app(RejectProductMasterReview::class)->handle($this->recordOf(ProductMaster::class, $record), $notes),
            'activate' => fn (Model $record, string $notes) => app(ActivateProductMaster::class)->handle($this->recordOf(ProductMaster::class, $record)),
            'retire' => fn (Model $record, string $notes) => app(RetireProductMaster::class)->handle($this->recordOf(ProductMaster::class, $record)),
            'reopen' => fn (Model $record, string $notes) => app(ReopenProductMaster::class)->handle($this->recordOf(ProductMaster::class, $record)),
        ];
    }

    /**
     * The kit's five uniform lifecycle actions PLUS re-submit and Master's operator-driven cascade retire.
     *
     * Re-submit (RM-06 / canon MVP-DEC-019; design D2/D5) RE-ARMS the approval flow after a rejection — a
     * `reviewed → reviewed` audit-only decision this page SURFACES via {@see ResubmitProductMasterForReview}
     * (never an Eloquent write). Its `->visible()` is gated to {@see isRejectionPending()} (the derived read):
     * re-submit is OFFERED only while an un-remediated rejection blocks activation, HIDDEN otherwise. The
     * block-gate itself needs no console code — an activation attempt on a rejection-pending Master throws
     * `ApprovalGovernanceViolation`, which the kit's `surfaceLifecycleOutcome` renders as an `action_failed`
     * danger notification for free.
     *
     * Cascade retire (design L6; § 4.7) retires the Master AND its active descendants (Variants → Product
     * References → SKUs) parent-before-child in one atomic transaction; it carries a confirmation modal WARNING
     * that descendants are retired too. The domain owns the ordering/atomicity; this page only triggers
     * {@see RetireProductMasterCascade} and surfaces the outcome.
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
                fn (Model $record, string $notes) => app(ResubmitProductMasterForReview::class)->handle($this->recordOf(ProductMaster::class, $record)),
            )->visible(fn (): bool => $this->isRejectionPending()),
            $this->lifecycleAction(
                'retireCascade',
                'cascade_retired',
                fn (Model $record, string $notes) => app(RetireProductMasterCascade::class)->handle($this->recordOf(ProductMaster::class, $record)),
                confirmationKey: 'affordance.cascade_warning',
            ),
        ];
    }
}
