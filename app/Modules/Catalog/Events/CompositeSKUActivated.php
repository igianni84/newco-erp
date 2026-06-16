<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductReference;

/**
 * `CompositeSKUActivated` — recorded when a Composite SKU (a curated bundle of N ≥ 2 ordered constituent
 * Product References) transitions `reviewed → active` (catalog-lifecycle-approval, design D9; product-catalog —
 * Requirement: Product Lifecycle Events). The verbatim §14.1 event name keeps `SKU` UPPER-case (the
 * inter-module contract key recorded in `domain_events.name`), while the canonical model class is
 * `CompositeSku` (the §18 naming cascade); §14.2 binds it to the `reviewed → active` step ONLY — the
 * `draft → reviewed` checkpoint and the `retired → reviewed` reopen are audit-only (no domain event), and there
 * is no `*Reviewed` event anywhere in the catalog surface.
 *
 * One of the fourteen `*Activated`/`*Retired` lifecycle events this change adds — the Catalog slice of the
 * ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). It is
 * recorded by the shared `LifecycleTransition` mechanism (via the `ActivateCompositeSku` action, task 4.6)
 * inside the SAME transaction as the `lifecycle_state` write (§14.1 / invariant 4 — the transactional outbox).
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings (the event names no caller — the dependency runs action → event, never back):
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Composite SKU;
 *   - {@see payload()} — the PII-free transition payload (the entity id + its ordered constituent Product
 *     Reference ids — the parents the activation cascade gated on — + the lifecycle value).
 */
final class CompositeSKUActivated
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'CompositeSKUActivated';

    /** The envelope `entity_type` for a Composite SKU (the canonical model class name, §18). */
    public const ENTITY_TYPE = 'CompositeSku';

    /**
     * The transition payload: a PII-free snapshot keyed on the Composite SKU id, its ordered constituent
     * Product Reference ids (the N ≥ 2 parents the activation cascade gated on — design D7 — read back in
     * bundle `position` order from the within-module {@see CompositeSku::constituents()} relation) and the
     * post-transition `lifecycle_state` (`active`). A Composite SKU references no party and carries no
     * descriptive prose (it is producer-agnostic and attribute-free beyond lifecycle/audit — design D9 / §3.8),
     * so the snapshot is purely structural ids + the lifecycle value, exactly what the spec's "entity ids +
     * lifecycle/enum values only" mandates.
     *
     * The lean transition shape mirrors the other twelve `*Activated`/`*Retired` events (entity id + the parent
     * ids the cascade gated on + the lifecycle value); the `constituent_count` that {@see CompositeSKUCreated}
     * carries is intentionally omitted here — it is a creation-event convenience, trivially derivable as the
     * count of `constituent_product_reference_ids`, and none of the twelve sibling transition events carries a
     * derived enrichment. Each constituent id is cast to `int` so the contract is a clean `list<int>` on both
     * engines (an uncast bigint read returns a numeric string under PostgreSQL's text protocol — Pattern #16).
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
