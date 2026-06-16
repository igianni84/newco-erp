<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\ProductMaster;

/**
 * `ProductMasterActivated` — recorded when a Product Master transitions `reviewed → active`
 * (catalog-lifecycle-approval, design D9; product-catalog — Requirement: Product Lifecycle Events). The
 * verbatim §14.1 event name (category-neutral per §18 — never `WineMaster*`); §14.2 binds it to the
 * `reviewed → active` step ONLY — the `draft → reviewed` checkpoint and the `retired → reviewed` reopen are
 * audit-only (no domain event), and there is no `*Reviewed` event anywhere in the catalog surface.
 *
 * One of the fourteen `*Activated`/`*Retired` lifecycle events this change adds — the Catalog slice of the
 * ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). It is
 * recorded by the shared `LifecycleTransition` mechanism (via the `ActivateProductMaster` action, task 3.2)
 * inside the SAME transaction as the `lifecycle_state` write (§14.1 / invariant 4 — the transactional
 * outbox). The class is the single source of truth for the event's three contract facets, so the action stays
 * thin and free of magic strings (the event names no caller — the dependency runs action → event, never back):
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Product Master;
 *   - {@see payload()} — the PII-free transition payload (the producer by id only).
 */
final class ProductMasterActivated
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProductMasterActivated';

    /** The envelope `entity_type` for a Product Master. */
    public const ENTITY_TYPE = 'ProductMaster';

    /**
     * The transition payload: a PII-free snapshot keyed on the Master id, its producer BY ID only, and the
     * post-transition `lifecycle_state` (`active`). The producer is referenced by id — never any party/personal
     * data (CLAUDE.md invariant 10 & the substrate's PII-free payload discipline). The descriptive neutral core
     * (`name`, `product_type`) and the wine attribute set are the subject of the immutable creation record
     * ({@see ProductMasterCreated}), not of a state transition, and are deliberately omitted.
     *
     * @return array<string, mixed>
     */
    public static function payload(ProductMaster $master): array
    {
        return [
            'product_master_id' => $master->id,
            'producer_id' => $master->producer_id,
            'lifecycle_state' => $master->lifecycle_state->value,
        ];
    }
}
