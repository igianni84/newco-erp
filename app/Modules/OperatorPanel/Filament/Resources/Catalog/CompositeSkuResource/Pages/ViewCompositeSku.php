<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource\Pages;

use App\Modules\Catalog\Actions\ActivateCompositeSku;
use App\Modules\Catalog\Actions\RejectCompositeSkuReview;
use App\Modules\Catalog\Actions\ReopenCompositeSku;
use App\Modules\Catalog\Actions\ResubmitCompositeSkuForReview;
use App\Modules\Catalog\Actions\RetireCompositeSku;
use App\Modules\Catalog\Actions\SubmitCompositeSkuForReview;
use App\Modules\Catalog\Actions\UpdateCompositeSkuComposition;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource;
use Closure;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

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
 * them (design L4). All copy is localized (invariant 12).
 *
 * Since catalog-module-0-completeness-sweep (task 6.3) the page also carries the Composite's ONE field-edit
 * surface — `editComposition`, a {@see contentEditAction()} modal. It is not an Edit PAGE (the read-projection
 * discipline stands; the reason was never a missing backend); it routes through
 * {@see UpdateCompositeSkuComposition}, which owns the state guard, the operator floor, the `N ≥ 2` floor, the
 * edit-time cascade re-assert, the `version` increment and the audit envelope. Because a Composite is
 * attribute-free beyond its ordered constituent set (§ 3.8), that set IS its identity: the edit RE-VERSIONS and
 * re-arms review exactly as a Master's rename does (it shares the `identity_updated` verb, design D5), so a
 * `reviewed` Composite edited here becomes review-stale and the re-submit button below appears.
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
     * The `editComposition` header action (task 6.3; design D8) — a {@see contentEditAction()} modal over the
     * Composite's ordered constituent set, prefilled from the current bundle
     * ({@see CompositeSkuResource::compositionEditState()}) over the create form's own picker
     * ({@see CompositeSkuResource::compositionEditSchema()}), routing the validated state into
     * {@see UpdateCompositeSkuComposition}.
     *
     * ONE operand, so — unlike the Variant's whitelist modal — nothing selects what the picker replaces, and the
     * schema needs neither a `live()` re-prefill nor a scoped read. REPLACEMENT semantics all the same: the whole
     * ordered set travels on every call, and a pure REORDER of the same ids is a real edit.
     *
     * The Action owns every rejection this modal can provoke — the `retired` state guard, the operator floor, the
     * `N ≥ 2 distinct` floor (BR-SKU-2, which the picker's `required()` rule cannot express: it refuses only an
     * EMPTY selection, so a one-element edit reaches the domain), and the activation cascade re-asserted at edit
     * time on an `active` Composite. Each is a localized `RuntimeException`, so the kit lands it uniformly as a
     * validation error on the `constituents` field, leaving the bundle, `version`, audit log and event log
     * untouched (design L4/L5).
     */
    protected function editCompositionAction(): Action
    {
        return $this->contentEditAction(
            'editComposition',
            'composition_updated',
            'constituents',
            CompositeSkuResource::compositionEditSchema(),
            fn (Model $record): array => CompositeSkuResource::compositionEditState($this->recordOf(CompositeSku::class, $record)),
            /** @param  array<string, mixed>  $data */
            function (Model $record, array $data): void {
                // Narrow the post-validation form state to the Catalog action's `list<int>` contract at the
                // boundary — the same narrowing `CreateCompositeSku::createViaAction()` performs on the same
                // picker. InvalidArgumentException is a LogicException, so it sails past the kit's
                // RuntimeException catch: an impossible payload is a programming bug, not a form error.
                $constituents = $data['constituents'] ?? [];

                if (! is_array($constituents)) {
                    throw new InvalidArgumentException('Unexpected Composite SKU composition payload.');
                }

                $productReferenceIds = [];
                foreach ($constituents as $constituent) {
                    if (! is_numeric($constituent)) {
                        throw new InvalidArgumentException('Unexpected Composite SKU constituent.');
                    }

                    $productReferenceIds[] = (int) $constituent;
                }

                app(UpdateCompositeSkuComposition::class)->handle(
                    $this->recordOf(CompositeSku::class, $record),
                    $productReferenceIds,
                );
            },
        );
    }

    /**
     * The kit's five uniform lifecycle actions PLUS the visibility-gated re-submit shared by all seven catalog
     * consoles, and the Composite's one field-edit modal.
     *
     * Re-submit (RM-06 / canon MVP-DEC-019 and its edit leg; design D2/D5 + catalog-module-0-completeness-sweep
     * D4/D9) RE-ARMS the approval flow after a rejection OR an identity edit — a `reviewed → reviewed` audit-only
     * decision this page SURFACES via {@see ResubmitCompositeSkuForReview} (never an Eloquent write). Its
     * `->visible()` is gated to {@see isReviewStale()} (the derived, verb-filtered read): re-submit is OFFERED only
     * while the entity is REVIEW-STALE — its latest review-freshness-relevant audit action is an un-remediated
     * rejection or an un-re-reviewed identity edit — and HIDDEN otherwise. A composition edit DOES arm it: the
     * modal below records `identity_updated` (design D5), one of the four review-freshness suffixes. The block-gate
     * itself needs no console code: an activation attempt on a review-stale Composite SKU throws
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
                fn (Model $record, string $notes) => app(ResubmitCompositeSkuForReview::class)->handle($this->recordOf(CompositeSku::class, $record)),
            )->visible(fn (): bool => $this->isReviewStale()),
            $this->editCompositionAction(),
        ];
    }
}
