<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\CaseConfiguration;

/**
 * `CaseConfigurationCreated` — recorded when a Case Configuration is created in `draft`
 * (catalog-product-spine, design D7/D8; product-catalog — Requirement: Spine Creation Events). The
 * verbatim §14.1 event name; `*Created` carries the `<null> → draft` semantics of §14.2.
 *
 * One of the seven one-class-per-event classes under the module's `Events/` surface — the Catalog slice
 * of the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling).
 * The class is the single source of truth for an event's three contract facets, so the
 * `CreateCaseConfiguration` action stays thin and free of magic strings (the event names no caller — the
 * dependency runs action → event, never back):
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Case Configuration;
 *   - {@see payload()} — the PII-free creation payload (ids + non-PII business data only).
 *
 * Its `*Activated`/`*Retired` lifecycle siblings ({@see CaseConfigurationActivated},
 * {@see CaseConfigurationRetired}) record the later `reviewed → active` / `active → retired` transitions
 * (catalog-lifecycle-approval, design D9).
 */
final class CaseConfigurationCreated
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'CaseConfigurationCreated';

    /** The envelope `entity_type` for a Case Configuration. */
    public const ENTITY_TYPE = 'CaseConfiguration';

    /**
     * The creation payload: a PII-free snapshot of the Case Configuration's business fields (no parties,
     * no personal data — a Case Configuration references none). The substrate envelope already carries
     * the entity type and id; the payload restates the id and the born-state for consumers reading the
     * event in isolation. No breakability field — breakability is not a property of the entity
     * (BR-RefData-2).
     *
     * @return array<string, mixed>
     */
    public static function payload(CaseConfiguration $caseConfiguration): array
    {
        return [
            'case_configuration_id' => $caseConfiguration->id,
            'name' => $caseConfiguration->name,
            'units_per_case' => $caseConfiguration->units_per_case,
            'packaging_type' => $caseConfiguration->packaging_type,
            'lifecycle_state' => $caseConfiguration->lifecycle_state->value,
        ];
    }
}
