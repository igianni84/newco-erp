<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalContentEdit;
use App\Modules\Catalog\Exceptions\UnknownCatalogReference;
use App\Modules\Catalog\Lifecycle\CatalogContentEdit;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\VariantCaseWhitelistEntry;

/**
 * Replaces the set of Case Configurations admitted for ONE (Product Variant, Format) pair — the Layer-1
 * possible-case-configurations whitelist (catalog-module-0-completeness-sweep task 3.1; design D6;
 * product-catalog — Requirement: Layer-1 Case-Configuration Whitelist; Module 0 PRD § 3.3 + § 7.1,
 * § 13.x AC-0-J-13 / AC-0-XM-11).
 *
 * The whitelist is NOT the Variant's identity: it is the cataloging-level statement "this product, in this
 * format, can in principle be packaged in these forms". Nothing a reviewer approved moves when it changes, so
 * this Action runs on {@see CatalogContentEdit::maintain()} — the non-versioning sibling of `edit()` — and its
 * effects are exactly four: ONE `catalog.product_variant.whitelist_updated` audit row carrying the pair and its
 * before/after admitted sets, the pivot rows themselves, NO `version` increment, and NO domain event. It
 * therefore does not re-arm review (design D4/D5: `whitelist_updated` ends with none of the four
 * review-freshness suffixes), and a reviewed-then-whitelisted Variant still activates without a re-submit.
 *
 * The mechanism owns everything shared: ONE transaction, the `lockForUpdate` re-read of the Variant (which
 * serialises two operators racing to replace the same pair — the pivot has no row to lock while it is empty),
 * the `draft`/`reviewed`/`active` state guard (a `retired` Variant must be reopened first —
 * {@see IllegalContentEdit}), the operator-principal floor ({@see ApprovalGovernanceViolation}) and the audit
 * envelope. Both guards run BEFORE the `$apply` closure, so a rejected maintenance write validates nothing and
 * touches no pivot row. Reductions on an `active` Variant are the J-13 core case, which is why `active` is an
 * ordinary state here rather than a special one.
 *
 * REPLACEMENT semantics, per pair, not a patch: the whole admitted set for the given Format travels on every
 * call (the console modal prefills it), and the write is computed as a DELTA — only the dropped rows are
 * deleted and only the newly admitted ones inserted, so a survivor keeps its `created_at` ("since when has this
 * packaging been admitted?"). Rows for the Variant's OTHER formats are never in scope: the pair is the unit
 * (design D6, PRD § 3.3's "in this format"), and the pivot's unique triple is what makes each pair independent.
 * An EMPTY set is a legitimate call — it clears the pair, restoring § 7.1's PERMISSIVE default (absence admits,
 * presence narrows) — so there is no non-empty floor to enforce.
 *
 * Two reference re-checks run against the LOCKED row before any write, converting what the pivot's RESTRICT
 * foreign keys would otherwise raise as a driver error into a domain rejection that names the offending ids
 * ({@see UnknownCatalogReference}). Neither checks LIFECYCLE state: a whitelist may be assembled ahead of its
 * references exactly as a `draft` bundle is (the Sellable-SKU activation gate — task 3.2 — is where a Case
 * Configuration's `active`ness is required), and Layer 1 catalogs POSSIBILITY, never breakability: this Action
 * writes no flag because the pivot exposes none (AC-0-XM-11 / BR-RefData-2).
 */
class SetVariantCaseWhitelist
{
    /** The audit entity-type label (the canonical model class name, § 18): the whitelist rides its Variant. */
    private const ENTITY = 'ProductVariant';

    /**
     * The audit verb. It ends with `_updated` but NOT with `.identity_updated`, so the review-freshness
     * derivation cannot see it (design D5's collision discipline, asserted from the governance side in
     * `ApprovalGovernance::latestReviewFreshnessAction`) — which is the whole point: maintaining a whitelist
     * neither sets nor clears the block-gate.
     */
    private const VERB = 'whitelist_updated';

    /** The two reference kinds this Action resolves by id, as they appear in an `UnknownCatalogReference`. */
    private const FORMAT_ENTITY = 'Format';

    private const CASE_CONFIGURATION_ENTITY = 'CaseConfiguration';

    public function __construct(private readonly CatalogContentEdit $contentEdit) {}

    /**
     * @param  int  $formatId  the Format half of the pair whose admitted set is replaced
     * @param  list<int>  $caseConfigurationIds  the REPLACEMENT admitted set (duplicates collapse; `[]` clears the pair, restoring the permissive default)
     *
     * @throws IllegalContentEdit when the locked Variant is `retired`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     * @throws UnknownCatalogReference when the Format, or any supplied Case Configuration, does not exist
     */
    public function handle(ProductVariant $variant, int $formatId, array $caseConfigurationIds): ProductVariant
    {
        return $this->contentEdit->maintain(
            $variant,
            self::ENTITY,
            self::VERB,
            fn (ProductVariant $locked): array => $this->applyWhitelist($locked, $formatId, $caseConfigurationIds),
        );
    }

    /**
     * Validate the references, replace the pair's admitted set, and hand the mechanism the (empty) core-column
     * change plus the before/after sets for the audit row.
     *
     * @param  list<int>  $caseConfigurationIds
     * @return array{attributes: array<string, mixed>, before: array<string, mixed>, after: array<string, mixed>}
     */
    private function applyWhitelist(ProductVariant $locked, int $formatId, array $caseConfigurationIds): array
    {
        // The admitted set is a SET: duplicates collapse, and ascending id order makes the audit snapshots
        // canonical — the same admitted set always audits as the same list, whatever order the console sent.
        $admitted = $this->normalise($caseConfigurationIds);

        $this->assertFormatExists($formatId);
        $this->assertCaseConfigurationsExist($admitted);

        $before = $this->admittedIds($locked, $formatId);

        // Delta, not delete-and-reinsert: `array_diff` on two ascending `list<int>`s yields the dropped and the
        // newly admitted ids, so a survivor's row (and its `created_at`) is never churned. Both writes are scoped
        // to THIS pair — the relation pins the Variant, the `format_id` predicate pins the Format — so the
        // Variant's other formats keep their sets untouched.
        $removed = array_values(array_diff($before, $admitted));
        $added = array_values(array_diff($admitted, $before));

        if ($removed !== []) {
            $locked->caseWhitelistEntries()
                ->where('format_id', $formatId)
                ->whereIn('case_configuration_id', $removed)
                ->delete();
        }

        foreach ($added as $caseConfigurationId) {
            $locked->caseWhitelistEntries()->create([
                'format_id' => $formatId,
                'case_configuration_id' => $caseConfigurationId,
            ]);
        }

        // The `before` read materialised the relation; drop the stale cache so any later read of the model (the
        // console's success re-render, a caller inspecting the returned Variant) sees the new admitted set.
        $locked->unsetRelation('caseWhitelistEntries');

        // No core columns change — the whitelist lives entirely in the pivot — so the mechanism writes no UPDATE
        // at all and the Variant's `version` and `updated_at` both stand. `format_id` travels on BOTH snapshots so
        // the audited pair is readable from either side of the row (`entity_id` already carries the Variant).
        return [
            'attributes' => [],
            'before' => ['format_id' => $formatId, 'case_configurations' => $before],
            'after' => ['format_id' => $formatId, 'case_configurations' => $admitted],
        ];
    }

    /**
     * The distinct supplied ids in ascending order. `sort()` reindexes in place, so the result is a `list<int>`
     * — which is what an index-compared audit snapshot needs (a jsonb round-trip preserves a list's order).
     *
     * @param  list<int>  $caseConfigurationIds
     * @return list<int>
     */
    private function normalise(array $caseConfigurationIds): array
    {
        $admitted = array_values(array_unique($caseConfigurationIds));

        sort($admitted);

        return $admitted;
    }

    /**
     * @throws UnknownCatalogReference
     */
    private function assertFormatExists(int $formatId): void
    {
        if (Format::query()->whereKey($formatId)->doesntExist()) {
            throw UnknownCatalogReference::forIds(self::FORMAT_ENTITY, [$formatId]);
        }
    }

    /**
     * One query resolves the whole set; the rejection names only the ids that resolved to nothing, never the
     * whole input. An empty set clears the pair and resolves vacuously.
     *
     * @param  list<int>  $admitted
     *
     * @throws UnknownCatalogReference
     */
    private function assertCaseConfigurationsExist(array $admitted): void
    {
        if ($admitted === []) {
            return;
        }

        $known = CaseConfiguration::query()
            ->whereKey($admitted)
            ->get()
            ->map(fn (CaseConfiguration $caseConfiguration): int => $caseConfiguration->id)
            ->all();

        $missing = array_values(array_diff($admitted, $known));

        if ($missing !== []) {
            throw UnknownCatalogReference::forIds(self::CASE_CONFIGURATION_ENTITY, $missing);
        }
    }

    /**
     * The pair's currently admitted Case-Configuration ids, ascending. The rows are hydrated rather than
     * `pluck()`ed: `pluck('case_configuration_id')` is `mixed` to static analysis, while the model's
     * `@property int` earns the `list<int>` this snapshot promises on both engines.
     *
     * @return list<int>
     */
    private function admittedIds(ProductVariant $locked, int $formatId): array
    {
        return array_values(
            $locked->caseWhitelistEntries()
                ->where('format_id', $formatId)
                ->orderBy('case_configuration_id')
                ->get()
                ->map(fn (VariantCaseWhitelistEntry $entry): int => $entry->case_configuration_id)
                ->all()
        );
    }
}
