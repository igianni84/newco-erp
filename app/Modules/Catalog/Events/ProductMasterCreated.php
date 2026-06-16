<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\ProductMaster;

/**
 * `ProductMasterCreated` — recorded when a Product Master is created in `draft` (catalog-product-spine,
 * design D7/D8; product-catalog — Requirement: Spine Creation Events). The verbatim §14.1 event name
 * (category-neutral per §18 — never `WineMaster*`); `*Created` carries the `<null> → draft` semantics of
 * §14.2.
 *
 * One of the seven one-class-per-event classes under the module's `Events/` surface — the Catalog slice of
 * the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). The
 * class is the single source of truth for an event's three contract facets, so the `CreateProductMaster`
 * action stays thin and free of magic strings (the event names no caller — the dependency runs action →
 * event, never back):
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Product Master;
 *   - {@see payload()} — the PII-free creation payload (the neutral core; producer by id only).
 *
 * Its `*Activated`/`*Retired` lifecycle siblings ({@see ProductMasterActivated}, {@see ProductMasterRetired})
 * record the later `reviewed → active` / `active → retired` transitions (catalog-lifecycle-approval, design D9).
 */
final class ProductMasterCreated
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProductMasterCreated';

    /** The envelope `entity_type` for a Product Master. */
    public const ENTITY_TYPE = 'ProductMaster';

    /**
     * The creation payload: a PII-free snapshot of the Master's NEUTRAL-CORE business fields. The producer
     * is referenced by id ONLY — never any party/personal data (CLAUDE.md invariant; the substrate's
     * payload discipline). The wine attribute set (appellation/region/winery_story) is the per-type
     * extension and is deliberately not restated here — the event contract is keyed on the neutral core and
     * the `product_master_id`; a consumer that later needs a wine attribute reads it through a published
     * read contract, never by widening this payload.
     *
     * @return array<string, mixed>
     */
    public static function payload(ProductMaster $master): array
    {
        return [
            'product_master_id' => $master->id,
            'name' => $master->name,
            'product_type' => $master->product_type->value,
            'producer_id' => $master->producer_id,
            'lifecycle_state' => $master->lifecycle_state->value,
        ];
    }
}
