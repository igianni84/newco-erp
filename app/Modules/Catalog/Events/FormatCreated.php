<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\Format;

/**
 * `FormatCreated` — recorded when a Format is created in `draft` (catalog-product-spine, design D7/D8;
 * product-catalog — Requirement: Spine Creation Events). The verbatim §14.1 event name; `*Created`
 * carries the `<null> → draft` semantics of §14.2.
 *
 * This is the FIRST of the seven one-class-per-event classes under the module's `Events/` surface — the
 * Catalog slice of the ~120-event inter-module API (CLAUDE.md: events + contracts are the only
 * cross-module coupling). The class is the single source of truth for an event's three contract facets,
 * so the `CreateFormat` action stays thin and free of magic strings (the event names no caller — the
 * dependency runs action → event, never back):
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Format;
 *   - {@see payload()} — the PII-free creation payload (ids + non-PII business data only).
 *
 * Its `*Activated`/`*Retired` lifecycle siblings ({@see FormatActivated}, {@see FormatRetired}) record the
 * later `reviewed → active` / `active → retired` transitions (catalog-lifecycle-approval, design D9).
 */
final class FormatCreated
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'FormatCreated';

    /** The envelope `entity_type` for a Format. */
    public const ENTITY_TYPE = 'Format';

    /**
     * The creation payload: a PII-free snapshot of the Format's business fields (no parties, no personal
     * data — Format references none). The substrate envelope already carries the entity type and id; the
     * payload restates the id and the born-state for consumers reading the event in isolation.
     *
     * @return array<string, mixed>
     */
    public static function payload(Format $format): array
    {
        return [
            'format_id' => $format->id,
            'name' => $format->name,
            'size_label' => $format->size_label,
            'volume_ml' => $format->volume_ml,
            'lifecycle_state' => $format->lifecycle_state->value,
        ];
    }
}
