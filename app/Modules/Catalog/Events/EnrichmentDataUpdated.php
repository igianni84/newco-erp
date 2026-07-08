<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\ProductVariant;

/**
 * `EnrichmentDataUpdated` — recorded when a Product Variant's observational enrichment metadata actually
 * changes (catalog-module-0-completeness-sweep, design D11; product-catalog — Requirement: Enrichment Data
 * Update; Module 0 PRD § 14.1 last paragraph, § 13.3 BR-Audit-2; AC-0-EVT-8).
 *
 * The 22nd — and only non-lifecycle — catalog domain event. Its twenty-one siblings mark a `lifecycle_state`
 * transition; this one marks a change to data that lives entirely OUTSIDE the lifecycle: the enrichment prose
 * is mutable in `draft`, `reviewed` and `active` alike, its update neither moves the FSM nor re-arms review nor
 * increments the Variant's identity `version`. The name is the verbatim § 14.1 string, unchanged by the § 16
 * category-neutral generalisation (it never named a wine).
 *
 * It is recorded by `UpdateProductVariantEnrichment` alone — from inside the `$apply` closure the content-edit
 * mechanism runs, so it lands in the SAME transaction as the enrichment write and its audit row (§ 14.1 /
 * invariant 4 — the transactional outbox). No lifecycle transition records it, and an enrichment update that
 * changes nothing records neither it nor an audit row (the idempotent no-op).
 *
 * The class is the single source of truth for the event's three contract facets, so the Action stays free of
 * magic strings:
 *   - {@see NAME} — the canonical event name recorded in `domain_events.name`, and the key the deferred Module
 *     S marketing consumer (§ 14.5 / AC-0-EVT-15) will dispatch on;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Product Variant;
 *   - {@see payload()} — the PII-free reference payload.
 */
final class EnrichmentDataUpdated
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'EnrichmentDataUpdated';

    /** The envelope `entity_type` for a Product Variant — enrichment is a Variant-level fact (§ 9.1). */
    public const ENTITY_TYPE = 'ProductVariant';

    /**
     * The payload is a bare REFERENCE: the Variant's id, and nothing else (design D11). The values that moved
     * are translatable prose (and, when the enrichment adapter lands, critic scores and market data) — they
     * belong to the audit record's before/after, which carries the redaction posture, not to an event replayed
     * for ten years across module boundaries. A consumer that needs the new value reads it from the Variant.
     *
     * This also keeps the event field-agnostic: the adapter's future columns join the enrichment surface
     * additively without ever changing this contract.
     *
     * @return array<string, mixed>
     */
    public static function payload(ProductVariant $variant): array
    {
        return [
            'product_variant_id' => $variant->id,
        ];
    }
}
