<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\SellableSku;

/**
 * `SellableSKURetired` — recorded when a Sellable SKU (Intrinsic) transitions `active → retired`
 * (catalog-lifecycle-approval, design D9; product-catalog — Requirement: Product Lifecycle Events). The
 * verbatim §14.1 event name keeps `SKU` UPPER-case (the inter-module contract key recorded in
 * `domain_events.name`), while the canonical model class is `SellableSku` (the §18 naming cascade); §14.2
 * binds it to the `active → retired` step ONLY.
 *
 * One of the fourteen `*Activated`/`*Retired` lifecycle events this change adds — the Catalog slice of the
 * ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). It is
 * recorded by the shared `LifecycleTransition` mechanism inside the SAME transaction as the `lifecycle_state`
 * write (§14.1 / invariant 4 — the transactional outbox): the single-entity `RetireSellableSku` action
 * records it, and the operator-driven retirement cascade (`RetireProductMasterCascade`, task 5.2) records it
 * parent-before-child (Master → Variants → PRs → SKUs). The class is the single source of truth for the
 * event's three contract facets, so the action stays thin and free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Sellable SKU;
 *   - {@see payload()} — the PII-free transition payload (the entity id + its TWO within-module parents by
 *     id — the Product Reference + the Case Configuration — + the lifecycle value).
 */
final class SellableSKURetired
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'SellableSKURetired';

    /** The envelope `entity_type` for a Sellable SKU (the canonical model class name, §18). */
    public const ENTITY_TYPE = 'SellableSku';

    /**
     * The transition payload: a PII-free snapshot keyed on the SKU id, its TWO within-module parents (the
     * Product Reference + the Case Configuration, by id), and the post-transition `lifecycle_state`
     * (`retired`). The descriptive `commercial_name` belongs to {@see SellableSKUCreated}; the transition
     * event stays a lean id-plus-lifecycle snapshot — and the persistence-only `marketing_copy` / `version`
     * never leak.
     *
     * @return array<string, mixed>
     */
    public static function payload(SellableSku $sellableSku): array
    {
        return [
            'sellable_sku_id' => $sellableSku->id,
            'product_reference_id' => $sellableSku->product_reference_id,
            'case_configuration_id' => $sellableSku->case_configuration_id,
            'lifecycle_state' => $sellableSku->lifecycle_state->value,
        ];
    }
}
