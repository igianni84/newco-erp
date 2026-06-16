<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductReference;

/**
 * `CompositeSKURetired` — recorded when a Composite SKU (a curated bundle of N ≥ 2 ordered constituent Product
 * References) transitions `active → retired` (catalog-lifecycle-approval, design D9; product-catalog —
 * Requirement: Product Lifecycle Events). The verbatim §14.1 event name keeps `SKU` UPPER-case (the
 * inter-module contract key recorded in `domain_events.name`), while the canonical model class is
 * `CompositeSku` (the §18 naming cascade); §14.2 binds it to the `active → retired` step ONLY.
 *
 * One of the fourteen `*Activated`/`*Retired` lifecycle events this change adds — the Catalog slice of the
 * ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). It is
 * recorded by the shared `LifecycleTransition` mechanism inside the SAME transaction as the `lifecycle_state`
 * write (§14.1 / invariant 4 — the transactional outbox): the single-entity `RetireCompositeSku` action
 * records it, and the operator-driven retirement cascade (`RetireProductMasterCascade`, task 5.2) records it
 * parent-before-child (Master → Variants → PRs → SKUs). The class is the single source of truth for the
 * event's three contract facets, so the action stays thin and free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Composite SKU;
 *   - {@see payload()} — the PII-free transition payload (the entity id + its ordered constituent Product
 *     Reference ids + the lifecycle value).
 */
final class CompositeSKURetired
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'CompositeSKURetired';

    /** The envelope `entity_type` for a Composite SKU (the canonical model class name, §18). */
    public const ENTITY_TYPE = 'CompositeSku';

    /**
     * The transition payload: a PII-free snapshot keyed on the Composite SKU id, its ordered constituent
     * Product Reference ids (read back in bundle `position` order from the within-module
     * {@see CompositeSku::constituents()} relation) and the post-transition `lifecycle_state` (`retired`). A
     * Composite SKU references no party and carries no descriptive prose (producer-agnostic and attribute-free
     * beyond lifecycle/audit — design D9 / §3.8), so the snapshot is purely structural ids + the lifecycle
     * value.
     *
     * The lean transition shape mirrors the other twelve `*Activated`/`*Retired` events; the
     * `constituent_count` that {@see CompositeSKUCreated} carries is intentionally omitted (a creation-event
     * convenience, trivially derivable as the count of `constituent_product_reference_ids`). Each constituent
     * id is cast to `int` so the contract is a clean `list<int>` on both engines (Pattern #16).
     *
     * @return array<string, mixed>
     */
    public static function payload(CompositeSku $compositeSku): array
    {
        return [
            'composite_sku_id' => $compositeSku->id,
            'constituent_product_reference_ids' => $compositeSku->constituents
                ->map(fn (ProductReference $constituent): int => (int) $constituent->id)
                ->all(),
            'lifecycle_state' => $compositeSku->lifecycle_state->value,
        ];
    }
}
