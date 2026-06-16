<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\ProductVariant;

/**
 * `ProductVariantRetired` — recorded when a Product Variant transitions `active → retired`
 * (catalog-lifecycle-approval, design D9; product-catalog — Requirement: Product Lifecycle Events). The
 * verbatim §14.1 event name (category-neutral per §18 — never `WineVariant*`); §14.2 binds it to the
 * `active → retired` step ONLY.
 *
 * One of the fourteen `*Activated`/`*Retired` lifecycle events this change adds — the Catalog slice of the
 * ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). It is
 * recorded by the shared `LifecycleTransition` mechanism inside the SAME transaction as the `lifecycle_state`
 * write (§14.1 / invariant 4 — the transactional outbox): the single-entity `RetireProductVariant` action
 * records it, and the operator-driven retirement cascade (`RetireProductMasterCascade`, task 5.2) records it
 * parent-before-child (Master → Variants → …). The class is the single source of truth for the event's three
 * contract facets, so the action stays thin and free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Product Variant;
 *   - {@see payload()} — the PII-free transition payload (the entity id + the parent Master by id + the
 *     lifecycle value).
 */
final class ProductVariantRetired
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProductVariantRetired';

    /** The envelope `entity_type` for a Product Variant. */
    public const ENTITY_TYPE = 'ProductVariant';

    /**
     * The transition payload: a PII-free snapshot keyed on the Variant id, its single parent Product Master
     * (by id), and the post-transition `lifecycle_state` (`retired`). The descriptive `variant_identifier`
     * and the wine attribute set are the subject of the creation record ({@see ProductVariantCreated}), not of
     * a state transition, and are deliberately omitted.
     *
     * @return array<string, mixed>
     */
    public static function payload(ProductVariant $variant): array
    {
        return [
            'product_variant_id' => $variant->id,
            'product_master_id' => $variant->product_master_id,
            'lifecycle_state' => $variant->lifecycle_state->value,
        ];
    }
}
