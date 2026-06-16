<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\Format;

/**
 * `FormatRetired` — recorded when a Format transitions `active → retired` (catalog-lifecycle-approval,
 * design D9; product-catalog — Requirement: Product Lifecycle Events). The verbatim §14.1 event name
 * (category-neutral per §18); §14.2 binds it to the `active → retired` step ONLY — the `retired → reviewed`
 * reopen is audit-only (no domain event), and there is no `*Reviewed` event anywhere in the catalog surface.
 *
 * One of the fourteen `*Activated`/`*Retired` lifecycle events this change adds — the Catalog slice of the
 * ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). It is
 * recorded by the shared `LifecycleTransition` mechanism (via the `RetireFormat` action, task 4.1) inside the
 * SAME transaction as the `lifecycle_state` write (§14.1 / invariant 4 — the transactional outbox). The class
 * is the single source of truth for the event's three contract facets, so the action stays thin and free of
 * magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Format;
 *   - {@see payload()} — the PII-free transition payload (entity id + lifecycle value only).
 */
final class FormatRetired
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'FormatRetired';

    /** The envelope `entity_type` for a Format. */
    public const ENTITY_TYPE = 'Format';

    /**
     * The transition payload: a PII-free snapshot keyed on the Format id and the post-transition
     * `lifecycle_state` (`retired`). A Format is a standalone reference entity — it has no parent in the
     * hierarchy and references no party (CLAUDE.md invariant 10 & the substrate's PII-free payload
     * discipline), so the payload carries no further ids. The descriptive physical-measure fields
     * (`name`, `size_label`, `volume_ml`) are the subject of the immutable creation record
     * ({@see FormatCreated}), not of a state transition, and are deliberately omitted.
     *
     * @return array<string, mixed>
     */
    public static function payload(Format $format): array
    {
        return [
            'format_id' => $format->id,
            'lifecycle_state' => $format->lifecycle_state->value,
        ];
    }
}
