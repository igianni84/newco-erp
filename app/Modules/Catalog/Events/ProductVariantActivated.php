<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\ProductVariant;

/**
 * `ProductVariantActivated` — recorded when a Product Variant transitions `reviewed → active`
 * (catalog-lifecycle-approval, design D9; product-catalog — Requirement: Product Lifecycle Events). The
 * verbatim §14.1 event name (category-neutral per §18 — never `WineVariant*`); §14.2 binds it to the
 * `reviewed → active` step ONLY — the `draft → reviewed` checkpoint and the `retired → reviewed` reopen are
 * audit-only (no domain event), and there is no `*Reviewed` event anywhere in the catalog surface.
 *
 * One of the fourteen `*Activated`/`*Retired` lifecycle events this change adds — the Catalog slice of the
 * ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). It is
 * recorded by the shared `LifecycleTransition` mechanism (via the `ActivateProductVariant` action, task 4.3)
 * inside the SAME transaction as the `lifecycle_state` write (§14.1 / invariant 4 — the transactional
 * outbox). The class is the single source of truth for the event's three contract facets, so the action
 * stays thin and free of magic strings (the event names no caller — the dependency runs action → event,
 * never back):
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Product Variant;
 *   - {@see payload()} — the PII-free transition payload (the entity id + the parent Master by id + the
 *     lifecycle value).
 */
final class ProductVariantActivated
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProductVariantActivated';

    /** The envelope `entity_type` for a Product Variant. */
    public const ENTITY_TYPE = 'ProductVariant';

    /**
     * The transition payload: a PII-free snapshot keyed on the Variant id, its single parent Product Master
     * (by id — the within-module parent the activation cascade gated on, design D7), and the post-transition
     * `lifecycle_state` (`active`). The descriptive `variant_identifier` and the wine attribute set (vintage /
     * tasting notes) are the subject of the immutable creation record ({@see ProductVariantCreated}), not of a
     * state transition, and are deliberately omitted.
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
