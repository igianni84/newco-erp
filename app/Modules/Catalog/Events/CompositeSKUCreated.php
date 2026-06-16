<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\CompositeSku;

/**
 * `CompositeSKUCreated` — recorded when a Composite SKU is created in `draft` with its ordered constituent
 * Product References (catalog-product-spine, design D7/D8/D9; product-catalog — Requirement: Spine Creation
 * Events). The verbatim §14.1 event name — note the §14.1 spelling keeps `SKU` upper-case (the inter-module
 * contract key recorded in `domain_events.name`), while the canonical model class is `CompositeSku` (the §18
 * naming cascade, design D7); `*Created` carries the `<null> → draft` semantics of §14.2.
 *
 * One of the seven one-class-per-event classes under the module's `Events/` surface — the Catalog slice of the
 * ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). The class is
 * the single source of truth for an event's three contract facets, so the `CreateCompositeSku` action stays
 * thin and free of magic strings (the event names no caller — the dependency runs action → event, never back):
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Composite SKU;
 *   - {@see payload()} — the PII-free creation payload (ids only).
 *
 * Its `*Activated`/`*Retired` lifecycle siblings ({@see CompositeSKUActivated}, {@see CompositeSKURetired})
 * record the later `reviewed → active` / `active → retired` transitions (catalog-lifecycle-approval, design D9).
 */
final class CompositeSKUCreated
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'CompositeSKUCreated';

    /** The envelope `entity_type` for a Composite SKU (the canonical model class name, §18). */
    public const ENTITY_TYPE = 'CompositeSku';

    /**
     * The creation payload: a PII-free snapshot of the bundle's identity + its ordered constituent set. A
     * Composite SKU references no party (it is producer-agnostic — design D9), so the payload is the entity id,
     * the ordered constituent Product Reference ids, the constituent count (the N of the N ≥ 2 bundle), and the
     * born state. The constituent ids are passed in by the action (the ordered, de-duplicated list it persisted)
     * rather than re-read from the relation — the event is recorded in the same transaction as the write, and
     * the action already holds the authoritative ordered set.
     *
     * @param  list<int>  $constituentProductReferenceIds  the ordered constituent PR ids, as persisted
     * @return array<string, mixed>
     */
    public static function payload(CompositeSku $compositeSku, array $constituentProductReferenceIds): array
    {
        return [
            'composite_sku_id' => $compositeSku->id,
            'constituent_product_reference_ids' => $constituentProductReferenceIds,
            'constituent_count' => count($constituentProductReferenceIds),
            'lifecycle_state' => $compositeSku->lifecycle_state->value,
        ];
    }
}
