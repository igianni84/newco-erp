<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages;

use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\RejectProductMasterReview;
use App\Modules\Catalog\Actions\ReopenProductMaster;
use App\Modules\Catalog\Actions\ResubmitProductMasterForReview;
use App\Modules\Catalog\Actions\RetireProductMaster;
use App\Modules\Catalog\Actions\RetireProductMasterCascade;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Actions\UpdateProductMasterIdentity;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource;
use App\Platform\I18n\TranslatableText;
use Closure;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

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
 * — it reimplements none of them (design L4). All copy is localized (invariant 12).
 *
 * Since catalog-module-0-completeness-sweep (task 6.1) the page also carries the ONE field-edit surface Module 0
 * ships: `editIdentity`, a {@see contentEditAction()} modal routing the four review-governed identity fields into
 * `UpdateProductMasterIdentity`. It is still not an Edit PAGE (the read-projection discipline stands) — the write
 * routes through the domain Action, which owns the BR-Identity-1 dedup re-check, the `version` increment, the
 * audited before/after and the review re-arm. Post-creation edits are no longer out of scope; Edit pages are.
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
     * The `editIdentity` header action — Module 0's one field-edit surface (catalog-module-0-completeness-sweep
     * task 6.1; design D8/R8; spec — Operator edits catalog identity content through the console). A
     * {@see contentEditAction()} modal prefilled from the Master's current identity
     * ({@see ProductMasterResource::identityEditState()}) over the create form's own field builders
     * ({@see ProductMasterResource::identityEditSchema()}), routing the validated state into
     * {@see UpdateProductMasterIdentity}.
     *
     * The Action owns everything the console must not: the BR-Identity-1 dedup re-check against every OTHER
     * non-retired Master, the `retired` state guard, the operator floor, the in-place `version` increment, the
     * audited before/after of only the CHANGED fields, and the review re-arm (`identity_updated` is a
     * review-freshness verb — editing a `reviewed` Master blocks its activation until an explicit re-submit,
     * whose button this page already surfaces). Every one of those rejections is a localized RuntimeException;
     * the kit maps it onto the `name` field of this very modal, so a dedup collision reads as a form validation
     * error (the spec's requirement) and nothing is written.
     *
     * REPLACEMENT semantics: the Action takes all four fields on every call, so the modal submits all four —
     * `null`/`''` winery story CLEARS the prose, deliberately. `country` is the Region cascade's filter and is
     * never dehydrated, so it never reaches the Action.
     */
    protected function editIdentityAction(): Action
    {
        return $this->contentEditAction(
            'editIdentity',
            'identity_updated',
            'name',
            ProductMasterResource::identityEditSchema(),
            fn (Model $record): array => ProductMasterResource::identityEditState($this->recordOf(ProductMaster::class, $record)),
            /** @param  array<string, mixed>  $data */
            function (Model $record, array $data): void {
                // Filament types the post-validation form state as array<string, mixed>; narrow each value to the
                // Catalog action's typed contract at the boundary (the create page's discipline). The three
                // `required` fields make the happy path well-formed; winery_story is the one optional input.
                // InvalidArgumentException is a LogicException, so it sails past the kit's RuntimeException catch
                // — an impossible payload is a programming bug, not a form error.
                $name = $data['name'];
                $appellation = $data['appellation'];
                $region = $data['region'];
                $wineryStory = $data['winery_story'] ?? null;

                if (
                    ! is_string($name)
                    || ! is_string($appellation)
                    || ! is_string($region)
                    || ! (is_null($wineryStory) || is_string($wineryStory))
                ) {
                    throw new InvalidArgumentException('Unexpected Product Master identity-edit payload.');
                }

                app(UpdateProductMasterIdentity::class)->handle(
                    $this->recordOf(ProductMaster::class, $record),
                    name: $name,
                    appellation: $appellation,
                    region: $region,
                    // A cleared Textarea dehydrates to `null`, never `''` — the `=== ''` arm is UNREACHABLE
                    // defence-in-depth against a future field swap, and no test claims to exercise it. Both
                    // arms mean the same thing to the domain: "the operator cleared the winery story."
                    wineryStory: ($wineryStory === null || $wineryStory === '')
                        ? null
                        : TranslatableText::of(['en' => $wineryStory]),
                );
            },
        );
    }

    /**
     * The kit's five uniform lifecycle actions PLUS re-submit, Master's operator-driven cascade retire, and the
     * identity-edit modal.
     *
     * Re-submit (RM-06 / canon MVP-DEC-019 and its edit leg; design D2/D5 + catalog-module-0-completeness-sweep
     * D4/D9) RE-ARMS the approval flow after a rejection OR an identity edit — a `reviewed → reviewed` audit-only
     * decision this page SURFACES via {@see ResubmitProductMasterForReview} (never an Eloquent write). Its
     * `->visible()` is gated to {@see isReviewStale()} (the derived, verb-filtered read): re-submit is OFFERED
     * only while the Master is REVIEW-STALE — its latest review-freshness-relevant audit action is an
     * un-remediated rejection or an un-re-reviewed identity edit — and HIDDEN otherwise. The block-gate itself
     * needs no console code: an activation attempt on a review-stale Master throws `ApprovalGovernanceViolation`,
     * which the kit's `surfaceLifecycleOutcome` renders as an `action_failed` danger notification for free.
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
            )->visible(fn (): bool => $this->isReviewStale()),
            $this->lifecycleAction(
                'retireCascade',
                'cascade_retired',
                fn (Model $record, string $notes) => app(RetireProductMasterCascade::class)->handle($this->recordOf(ProductMaster::class, $record)),
                confirmationKey: 'affordance.cascade_warning',
            ),
            $this->editIdentityAction(),
        ];
    }
}
