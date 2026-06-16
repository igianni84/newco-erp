<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\ProductReference;

/**
 * `ProductReferenceRetired` — recorded when a Product Reference transitions `active → retired`
 * (catalog-lifecycle-approval, design D9; product-catalog — Requirement: Product Lifecycle Events). The
 * verbatim §14.1 event name (category-neutral per §18 — never `BottleReference*`); §14.2 binds it to the
 * `active → retired` step ONLY.
 *
 * One of the fourteen `*Activated`/`*Retired` lifecycle events this change adds — the Catalog slice of the
 * ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). It is
 * recorded by the shared `LifecycleTransition` mechanism inside the SAME transaction as the `lifecycle_state`
 * write (§14.1 / invariant 4 — the transactional outbox): the single-entity `RetireProductReference` action
 * records it, and the operator-driven retirement cascade (`RetireProductMasterCascade`, task 5.2) records it
 * parent-before-child (Master → Variants → PRs → …). The class is the single source of truth for the event's
 * three contract facets, so the action stays thin and free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Product Reference;
 *   - {@see payload()} — the PII-free transition payload (the entity id + its TWO within-module parents by
 *     id — the Variant + the Format — + the lifecycle value).
 */
final class ProductReferenceRetired
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProductReferenceRetired';

    /** The envelope `entity_type` for a Product Reference. */
    public const ENTITY_TYPE = 'ProductReference';

    /**
     * The transition payload: a PII-free snapshot keyed on the PR id, its TWO within-module parents (the
     * Product Variant + the Format, by id), and the post-transition `lifecycle_state` (`retired`). A PR
     * carries no descriptive prose and no party reference (its identity IS the two-dimension tuple —
     * BR-Identity-3), so the snapshot is exactly its key plus the lifecycle value.
     *
     * @return array<string, mixed>
     */
    public static function payload(ProductReference $reference): array
    {
        return [
            'product_reference_id' => $reference->id,
            'product_variant_id' => $reference->product_variant_id,
            'format_id' => $reference->format_id,
            'lifecycle_state' => $reference->lifecycle_state->value,
        ];
    }
}
