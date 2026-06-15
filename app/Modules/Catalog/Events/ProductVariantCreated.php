<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\ProductVariant;

/**
 * `ProductVariantCreated` — recorded when a Product Variant is created in `draft` (catalog-product-spine,
 * design D7/D8; product-catalog — Requirement: Spine Creation Events). The verbatim §14.1 event name
 * (category-neutral per §18 — never `WineVariant*`); `*Created` carries the `<null> → draft` semantics of
 * §14.2.
 *
 * One of the seven one-class-per-event classes under the module's `Events/` surface — the Catalog slice of
 * the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). The
 * class is the single source of truth for an event's three contract facets, so the `CreateProductVariant`
 * action stays thin and free of magic strings (the event names no caller — the dependency runs action →
 * event, never back):
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Product Variant;
 *   - {@see payload()} — the PII-free creation payload (the neutral core; the parent Master by id).
 *
 * No `*Activated`/`*Retired` sibling exists in this change (design D3 scope guard).
 */
final class ProductVariantCreated
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProductVariantCreated';

    /** The envelope `entity_type` for a Product Variant. */
    public const ENTITY_TYPE = 'ProductVariant';

    /**
     * The creation payload: a PII-free snapshot of the Variant's NEUTRAL-CORE business fields. The parent
     * Master is referenced by id ONLY. The wine attribute set (vintage / tasting notes) is the per-type
     * extension and is deliberately not restated here — the event contract is keyed on the neutral core and
     * the `product_variant_id`; a consumer that later needs a wine attribute reads it through a published
     * read contract, never by widening this payload.
     *
     * @return array<string, mixed>
     */
    public static function payload(ProductVariant $variant): array
    {
        return [
            'product_variant_id' => $variant->id,
            'product_master_id' => $variant->product_master_id,
            'variant_identifier' => $variant->variant_identifier,
            'lifecycle_state' => $variant->lifecycle_state->value,
        ];
    }
}
