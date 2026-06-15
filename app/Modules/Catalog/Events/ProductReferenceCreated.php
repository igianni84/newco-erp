<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\ProductReference;

/**
 * `ProductReferenceCreated` — recorded when a Product Reference is created in `draft` (catalog-product-spine,
 * design D7/D8; product-catalog — Requirement: Spine Creation Events). The verbatim §14.1 event name
 * (category-neutral per §18 — never `BottleReference*`); `*Created` carries the `<null> → draft` semantics of
 * §14.2.
 *
 * One of the seven one-class-per-event classes under the module's `Events/` surface — the Catalog slice of
 * the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). The
 * class is the single source of truth for an event's three contract facets, so the `CreateProductReference`
 * action stays thin and free of magic strings (the event names no caller — the dependency runs action →
 * event, never back):
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Product Reference;
 *   - {@see payload()} — the PII-free creation payload (the two identity dimensions by id).
 *
 * No `*Activated`/`*Retired` sibling exists in this change (design D3 scope guard).
 */
final class ProductReferenceCreated
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProductReferenceCreated';

    /** The envelope `entity_type` for a Product Reference. */
    public const ENTITY_TYPE = 'ProductReference';

    /**
     * The creation payload: a PII-free snapshot of the PR's identity. A PR carries no descriptive prose and no
     * party reference, so the payload is just the entity id and its two identity dimensions (Variant + Format)
     * by id — the universal product key in event form. A Case Configuration is deliberately absent (it is
     * never part of PR identity — BR-Identity-3).
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
