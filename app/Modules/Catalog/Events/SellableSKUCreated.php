<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\SellableSku;

/**
 * `SellableSKUCreated` — recorded when a Sellable SKU (Intrinsic) is created in `draft` (catalog-product-spine,
 * design D7/D8; product-catalog — Requirement: Spine Creation Events). The verbatim §14.1 event name — note
 * the §14.1 spelling keeps `SKU` upper-case (the inter-module contract key recorded in `domain_events.name`),
 * while the canonical model class is `SellableSku` (the §18 naming cascade, design D7); `*Created` carries the
 * `<null> → draft` semantics of §14.2.
 *
 * One of the seven one-class-per-event classes under the module's `Events/` surface — the Catalog slice of the
 * ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). The class is
 * the single source of truth for an event's three contract facets, so the `CreateSellableSku` action stays thin
 * and free of magic strings (the event names no caller — the dependency runs action → event, never back):
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Sellable SKU;
 *   - {@see payload()} — the PII-free creation payload (ids + non-PII business data only).
 *
 * No `*Activated`/`*Retired` sibling exists in this change (design D3 scope guard).
 */
final class SellableSKUCreated
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'SellableSKUCreated';

    /** The envelope `entity_type` for a Sellable SKU (the canonical model class name, §18). */
    public const ENTITY_TYPE = 'SellableSku';

    /**
     * The creation payload: a PII-free snapshot of the SKU's identity + commercial name. A Sellable SKU
     * references no party (the producer lives on the Master, by id), so the payload is the entity id, its two
     * structural dimensions (Product Reference + Case Configuration) by id, the commercial name (non-PII
     * business data — mirrors Format/CaseConfiguration restating their `name`), and the born state. The
     * free-form `marketing_copy` is deliberately omitted — the event stays a lean snapshot; a consumer needing
     * the copy reads it through a published contract.
     *
     * @return array<string, mixed>
     */
    public static function payload(SellableSku $sellableSku): array
    {
        return [
            'sellable_sku_id' => $sellableSku->id,
            'product_reference_id' => $sellableSku->product_reference_id,
            'case_configuration_id' => $sellableSku->case_configuration_id,
            'commercial_name' => $sellableSku->commercial_name,
            'lifecycle_state' => $sellableSku->lifecycle_state->value,
        ];
    }
}
