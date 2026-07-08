<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ActivationCascadeViolation;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalContentEdit;
use App\Modules\Catalog\Exceptions\InsufficientCompositeConstituents;
use App\Modules\Catalog\Lifecycle\ActivationCascadeGate;
use App\Modules\Catalog\Lifecycle\CatalogContentEdit;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductReference;

/**
 * Replaces a Composite SKU's ORDERED constituent Product Reference set in place, through the shared
 * {@see CatalogContentEdit} mechanism (catalog-module-0-completeness-sweep task 2.2; design D1/D2;
 * product-catalog — Requirement: Identity Edit and Re-Versioning; Module 0 PRD § 4.8 + § 13.3 BR-Audit-1,
 * § 3.8 + § 13.5 BR-SKU-2).
 *
 * A Composite SKU is attribute-free beyond lifecycle/audit (§ 3.8): its constituent set IS its content, so a
 * composition change IS this entity's identity change — which is why it shares the Master's `identity_updated`
 * verb (design D5: one uniform suffix keeps the review-freshness filter a 4-suffix set) and, with it, the
 * re-arm semantics. Editing a `reviewed` Composite's composition makes it review-stale until it is explicitly
 * re-submitted; an `active` Composite stays `active` (the FSM has no `active → reviewed` edge) and the version
 * increment plus the audited before/after are the control on that operator-correction path.
 *
 * The mechanism owns everything shared: ONE transaction, the `lockForUpdate` re-read, the
 * `draft`/`reviewed`/`active` state guard (a `retired` Composite must be reopened first —
 * {@see IllegalContentEdit}), the operator-principal floor ({@see ApprovalGovernanceViolation}), the in-place
 * `version` increment, the single `catalog.composite_sku.identity_updated` audit row, and the absence of any
 * domain event. Both guards run BEFORE the `$apply` closure below, so a rejected edit runs none of this
 * Action's re-checks and writes nothing.
 *
 * REPLACEMENT semantics, not a patch: the whole ordered set travels on every call (the console modal prefills
 * it from the current bundle). Two re-checks run against the LOCKED row, in the order a reader would expect —
 * input validity, then the invariant:
 *   1. **N ≥ 2 distinct** (BR-SKU-2), reusing {@see InsufficientCompositeConstituents}. The incoming list is
 *      first normalised to its distinct ids in input order (constituents are an ordered SET — the join's unique
 *      `(composite, PR)` makes a PR appear at most once), then counted. Unlike {@see CreateCompositeSku}, which
 *      runs the same floor as pure pre-transaction input validation, this one lives INSIDE the closure: the
 *      mechanism's state guard and operator floor must win over it, so a 1-element edit of a `retired` Composite
 *      reports the `retired` state, not the count.
 *   2. **The activation cascade, re-asserted at edit time** (design D2): when the LOCKED Composite is `active`,
 *      every Product Reference in the NEW set must itself be `active` — otherwise an `active` Composite could
 *      come to reference a non-`active` constituent through the back door of an edit, since
 *      {@see ActivateCompositeSku}'s gate never runs again on an already-`active` entity. Rejected with
 *      {@see ActivationCascadeViolation}, the same class the gate throws, under an edit-specific reason. In
 *      `draft`/`reviewed` NO constituent-state condition applies: the {@see ActivationCascadeGate} will check
 *      the whole set at activation, and a `draft` bundle is expected to be assembled ahead of its parents.
 *
 * The condition covers the RESULTING set, not merely the newly added ids (the delta spec: "every constituent
 * Product Reference in the **new** set"). An incumbent constituent can never spuriously trip it — the
 * retirement reference-integrity guard already refuses to retire a Product Reference that an `active` Composite
 * lists (`catalog.retirement.blocked_by_active_references`) — so the check is exactly the gate's own loop,
 * evaluated over the set the edit would leave behind.
 *
 * The Composite has no content columns of its own: the bundle lives entirely in the
 * `catalog_composite_sku_constituents` join, written here (inside the mechanism's transaction) through the
 * within-module {@see CompositeSku::constituents()} relation. So the mechanism's single `UPDATE` carries only
 * the `version` increment — the composition change and the version can still never be observed apart, because
 * both commit with the same transaction. Producer-agnostic on the way through (design D9 / BR-SKU-5): a
 * multi-producer bundle is accepted here exactly as it is at creation; single-producer admissibility is a
 * Module S Offer-publication rule, never a PIM check.
 */
class UpdateCompositeSkuComposition
{
    /** The audit entity-type label (the canonical model class name, § 18) — the Composite's own name. */
    private const ENTITY = 'CompositeSku';

    /** The blocking parent's label in the cascade rejection: a Composite's parents are its constituent PRs. */
    private const CONSTITUENT_ENTITY = 'ProductReference';

    /**
     * The audit verb, shared verbatim with `UpdateProductMasterIdentity` (design D5): a Composite's composition
     * IS its identity, so a composition change participates in review freshness exactly as a Master's rename
     * does — it ENDS with one of the four review-freshness-relevant suffixes deliberately.
     */
    private const VERB = 'identity_updated';

    public function __construct(private readonly CatalogContentEdit $contentEdit) {}

    /**
     * @param  list<int>  $productReferenceIds  the REPLACEMENT constituent PR ids, in bundle order (duplicates collapse)
     *
     * @throws IllegalContentEdit when the locked Composite is `retired`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     * @throws InsufficientCompositeConstituents when fewer than two distinct constituents are supplied
     * @throws ActivationCascadeViolation when the Composite is `active` and any constituent in the new set is not
     */
    public function handle(CompositeSku $compositeSku, array $productReferenceIds): CompositeSku
    {
        return $this->contentEdit->edit(
            $compositeSku,
            self::ENTITY,
            self::VERB,
            fn (CompositeSku $locked): array => $this->applyComposition($locked, $productReferenceIds),
        );
    }

    /**
     * Re-check the two rules against the LOCKED Composite, replace the ordered constituent set, and hand the
     * mechanism the (empty) core-column change plus the before/after ORDERED id lists for the audit row.
     *
     * @param  list<int>  $productReferenceIds
     * @return array{attributes: array<string, mixed>, before: array<string, mixed>, after: array<string, mixed>}
     */
    private function applyComposition(CompositeSku $locked, array $productReferenceIds): array
    {
        // Distinct constituents in input order, then the N ≥ 2 floor (BR-SKU-2) — the same normalise-then-count
        // as `CreateCompositeSku`, so the persisted positions stay contiguous 1..N.
        $constituents = array_values(array_unique($productReferenceIds));

        if (count($constituents) < 2) {
            throw InsufficientCompositeConstituents::forCount(count($constituents));
        }

        // The cascade condition, re-asserted at edit time on an `active` Composite (design D2). Evaluated before
        // any write, so a rejection leaves the join table exactly as it was.
        if ($locked->lifecycleState() === LifecycleState::Active) {
            $this->assertEveryConstituentIsActive($constituents);
        }

        $before = $this->orderedConstituentIds($locked);

        // The ordered set is content: a pure REORDER of the same ids is a real change, and `!==` on two `list<int>`
        // compares element-wise by index, so it sees one. An identical ordered set writes nothing to the join —
        // the mechanism's unconditional `version` increment and audit row still stand (an edit is an edit; only the
        // enrichment Action's no-op rule short-circuits, and that is its own semantics).
        if ($before === $constituents) {
            return ['attributes' => [], 'before' => [], 'after' => []];
        }

        // Replace: `sync()` detaches the dropped constituents, attaches the added ones and rewrites `position` on
        // the survivors — one call, add and remove together, 1-based positions in input order. Each id's EXISTENCE
        // is enforced structurally by the join's `product_reference_id` foreign key (the same stance
        // `CreateCompositeSku` takes: the DB owns structural constraints, no redundant application-layer query).
        $pivot = [];
        foreach ($constituents as $index => $productReferenceId) {
            $pivot[$productReferenceId] = ['position' => $index + 1];
        }
        $locked->constituents()->sync($pivot);

        // The relation was materialised by the `before` read; drop the stale cache so any later read of the model
        // (the console's success re-render, a caller inspecting the returned entity) sees the new bundle.
        $locked->unsetRelation('constituents');

        // No core columns change — a Composite SKU is attribute-free beyond lifecycle/audit (§ 3.8) — so the
        // mechanism's single UPDATE carries the `version` increment alone. The audit's before/after are the
        // ORDERED id lists: the bundle's whole content, and BR-Audit-1's "old version retrievable" for this entity.
        return [
            'attributes' => [],
            'before' => ['constituents' => $before],
            'after' => ['constituents' => $constituents],
        ];
    }

    /**
     * The locked Composite's current constituent ids in bundle `position` order (the relation carries the
     * `orderByPivot`). Each id is cast to `int` so the audit snapshot is a clean `list<int>` on both engines — an
     * uncast bigint reads back as a numeric string under PostgreSQL's text protocol. `array_values` re-indexes
     * the Eloquent collection's keys, which PHPStan cannot otherwise prove contiguous — and a `list` is what an
     * ORDERED, index-compared snapshot needs.
     *
     * @return list<int>
     */
    private function orderedConstituentIds(CompositeSku $locked): array
    {
        return array_values(
            $locked->constituents()
                ->get()
                ->map(fn (ProductReference $constituent): int => (int) $constituent->id)
                ->all()
        );
    }

    /**
     * The activation cascade over the REPLACEMENT set: every constituent Product Reference must be `active`.
     * One query loads the whole set; an id that does not resolve is treated exactly as a non-`active` one
     * (fail-closed — the same stance {@see ActivationCascadeGate::assertParentActive()} takes on a null parent).
     *
     * The gate's own primitive is deliberately NOT reused: it is a read-time gate on a single loaded parent whose
     * reason names an ACTIVATION, and the operator here pressed *save composition*. The predicate is identical;
     * only the copy differs (see {@see ActivationCascadeViolation}).
     *
     * @param  list<int>  $constituents
     *
     * @throws ActivationCascadeViolation
     */
    private function assertEveryConstituentIsActive(array $constituents): void
    {
        $active = ProductReference::query()
            ->whereKey($constituents)
            ->where('lifecycle_state', LifecycleState::Active->value)
            ->count();

        if ($active !== count($constituents)) {
            throw ActivationCascadeViolation::constituentNotActiveOnCompositionEdit(self::ENTITY, self::CONSTITUENT_ENTITY);
        }
    }
}
