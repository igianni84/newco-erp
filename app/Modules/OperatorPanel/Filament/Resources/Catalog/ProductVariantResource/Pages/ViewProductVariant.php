<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages;

use App\Modules\Catalog\Actions\ActivateProductVariant;
use App\Modules\Catalog\Actions\RejectProductVariantReview;
use App\Modules\Catalog\Actions\ReopenProductVariant;
use App\Modules\Catalog\Actions\ResubmitProductVariantForReview;
use App\Modules\Catalog\Actions\RetireProductVariant;
use App\Modules\Catalog\Actions\SetVariantCaseWhitelist;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Actions\UpdateProductVariantEnrichment;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\VariantCaseWhitelistEntry;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource;
use App\Platform\I18n\TranslatableText;
use Closure;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

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
 * activation under the now-`retired` Variant is prevented — the domain's concern, not the console's). All copy is
 * localized (invariant 12).
 *
 * Since catalog-module-0-completeness-sweep (task 6.2) the page also carries the Variant's TWO maintenance
 * surfaces — `editEnrichment` and `manageWhitelist`, both {@see contentEditAction()} modals. Neither is an Edit
 * PAGE (the read-projection discipline stands): each routes through its Catalog Action, which owns the state
 * guard, the operator floor and the audit envelope. What makes them MAINTENANCE rather than identity edits is what
 * they leave alone — neither moves `version`, and neither re-arms review, so a `reviewed` Variant that is enriched
 * or whitelisted still activates without a re-submit. Enrichment records `EnrichmentDataUpdated` on a real change
 * (and nothing at all on an identical value); the whitelist records no domain event at all.
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
     * The `editEnrichment` header action (task 6.2; design D8/R8/D11) — a {@see contentEditAction()} modal over the
     * Variant's observational enrichment, prefilled from the WINE attribute set
     * ({@see ProductVariantResource::enrichmentEditState()}) over the create form's own tasting-notes field builder
     * ({@see ProductVariantResource::enrichmentEditSchema()}), routing the validated state into
     * {@see UpdateProductVariantEnrichment}.
     *
     * The Action owns everything the console must not: the `retired` state guard, the operator floor, the
     * i18n-map diff that decides whether anything actually moved, the audited before/after of the changed fields,
     * and the in-transaction `EnrichmentDataUpdated` record (named in PROSE — a `{@see}` would let Pint re-import a
     * forbidden `Catalog\Events` type, design R6). An identical value is a SILENT no-op — no event, no audit row,
     * no write — yet still a success from the operator's point of view, which is why the kit notifies
     * unconditionally: the operator asked for the notes to read thus, and they do.
     *
     * REPLACEMENT semantics: the modal submits the field on every call, so an emptied textarea CLEARS the prose,
     * deliberately. `version` never moves — enrichment is not the Variant's identity.
     */
    protected function editEnrichmentAction(): Action
    {
        return $this->contentEditAction(
            'editEnrichment',
            'enrichment_updated',
            'tasting_notes',
            ProductVariantResource::enrichmentEditSchema(),
            fn (Model $record): array => ProductVariantResource::enrichmentEditState($this->recordOf(ProductVariant::class, $record)),
            /** @param  array<string, mixed>  $data */
            function (Model $record, array $data): void {
                // Narrow the post-validation form state to the Catalog action's typed contract at the boundary
                // (the create page's discipline). The optional textarea yields a string or nothing;
                // InvalidArgumentException is a LogicException, so it sails past the kit's RuntimeException catch —
                // an impossible payload is a programming bug, not a form error.
                $tastingNotes = $data['tasting_notes'] ?? null;

                if (! is_null($tastingNotes) && ! is_string($tastingNotes)) {
                    throw new InvalidArgumentException('Unexpected Product Variant enrichment payload.');
                }

                // The `=== ''` arm is DEFENCE-IN-DEPTH, not live logic: Filament dehydrates a blank Textarea to
                // `null`, so an emptied field already arrives null (proven by mutation — deleting the arm reds
                // nothing). It stays because `TranslatableText::of(['en' => ''])` is a value the domain should
                // never be handed, and this is the one place that can promise it never will, whatever a future
                // input component or `dehydrateStateUsing()` does. The CLEARING contract is observable and tested;
                // this arm is not, and no test claims to reach it.
                app(UpdateProductVariantEnrichment::class)->handle(
                    $this->recordOf(ProductVariant::class, $record),
                    tastingNotes: ($tastingNotes === null || $tastingNotes === '')
                        ? null
                        : TranslatableText::of(['en' => $tastingNotes]),
                );
            },
        );
    }

    /**
     * The `manageWhitelist` header action (task 6.2; design D6/D8) — a {@see contentEditAction()} modal that
     * REPLACES the admitted Case-Configuration set of ONE (Variant, Format) pair, routing into
     * {@see SetVariantCaseWhitelist}.
     *
     * It is the first console modal with TWO operands, and the Format is the one that selects WHICH set the second
     * operand replaces — so the schema re-prefills the admitted set whenever the Format changes
     * ({@see admittedCaseConfigurationIds()}). Both are narrowed inside this closure, never inside the schema.
     *
     * The Action owns the rest: the `retired` state guard, the operator floor, the reference re-checks, the
     * add/remove DELTA that preserves a survivor's `created_at`, and the audit row carrying the pair with its
     * before/after sets. It is an AUDIT-ONLY write — no `version` change, no domain event — because a whitelist
     * states what packaging is POSSIBLE, not what a reviewer approved. Removing a Case Configuration therefore
     * reaches no already-`active` Sellable SKU: it blocks the NEXT activation, and the domain's rejection surfaces
     * on the SKU's own view page as a danger notification (this page reimplements no gate — design L4).
     *
     * The set may legitimately be EMPTIED: that clears the pair and restores § 7.1's permissive default.
     */
    protected function manageWhitelistAction(): Action
    {
        return $this->contentEditAction(
            'manageWhitelist',
            'whitelist_updated',
            'case_configuration_ids',
            ProductVariantResource::whitelistEditSchema($this->admittedCaseConfigurationIds(...)),
            // The pair is chosen INSIDE the modal, so there is no pair to prefill on open: the admitted set
            // arrives the moment a Format does (the schema's live re-prefill).
            fn (Model $record): array => ['format_id' => null, 'case_configuration_ids' => []],
            /** @param  array<string, mixed>  $data */
            function (Model $record, array $data): void {
                $formatId = $data['format_id'] ?? null;
                $caseConfigurationIds = $data['case_configuration_ids'] ?? [];

                if (! is_numeric($formatId) || ! is_array($caseConfigurationIds)) {
                    throw new InvalidArgumentException('Unexpected Product Variant whitelist payload.');
                }

                // The multi-select's state is a list of option keys; narrow it to the Action's `list<int>` contract.
                // An EMPTY selection is well-formed — it clears the pair.
                $admitted = [];
                foreach ($caseConfigurationIds as $caseConfigurationId) {
                    if (! is_numeric($caseConfigurationId)) {
                        throw new InvalidArgumentException('Unexpected Product Variant whitelist entry.');
                    }

                    $admitted[] = (int) $caseConfigurationId;
                }

                app(SetVariantCaseWhitelist::class)->handle(
                    $this->recordOf(ProductVariant::class, $record),
                    formatId: (int) $formatId,
                    caseConfigurationIds: $admitted,
                );
            },
        );
    }

    /**
     * The Case-Configuration ids currently admitted for this Variant in the given Format — the manage-whitelist
     * modal's live prefill. A READ off the record's within-Catalog {@see ProductVariant::caseWhitelistEntries()}
     * relation (the console reads module models and writes only through module Actions, ADR 2026-06-19).
     *
     * The rows are hydrated rather than `pluck()`ed: `pluck()` is `mixed` to static analysis, while the model's
     * `@property int` earns the `list<int>` a Filament multi-select's state needs — mirroring the Action's own read.
     *
     * @return list<int>
     */
    private function admittedCaseConfigurationIds(int $formatId): array
    {
        return array_values(
            $this->recordOf(ProductVariant::class, $this->getRecord())
                ->caseWhitelistEntries()
                ->where('format_id', $formatId)
                ->orderBy('case_configuration_id')
                ->get()
                ->map(fn (VariantCaseWhitelistEntry $entry): int => $entry->case_configuration_id)
                ->all()
        );
    }

    /**
     * The kit's five uniform lifecycle actions PLUS the visibility-gated re-submit shared by all seven catalog
     * consoles, and the Variant's two maintenance modals.
     *
     * Re-submit (RM-06 / canon MVP-DEC-019 and its edit leg; design D2/D5 + catalog-module-0-completeness-sweep
     * D4/D9) RE-ARMS the approval flow after a rejection OR an identity edit — a `reviewed → reviewed` audit-only
     * decision this page SURFACES via {@see ResubmitProductVariantForReview} (never an Eloquent write). Its
     * `->visible()` is gated to {@see isReviewStale()} (the derived, verb-filtered read): re-submit is OFFERED only
     * while the entity is REVIEW-STALE — its latest review-freshness-relevant audit action is an un-remediated
     * rejection or an un-re-reviewed identity edit — and HIDDEN otherwise. Neither maintenance modal below can arm
     * it: `enrichment_updated` and `whitelist_updated` end with none of the four review-freshness suffixes, so the
     * derivation is blind to them (design D5). The block-gate itself needs no console code: an activation attempt
     * on a review-stale Product Variant throws `ApprovalGovernanceViolation`, which the kit's
     * `surfaceLifecycleOutcome` renders as an `action_failed` danger notification for free.
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
            )->visible(fn (): bool => $this->isReviewStale()),
            $this->editEnrichmentAction(),
            $this->manageWhitelistAction(),
        ];
    }
}
