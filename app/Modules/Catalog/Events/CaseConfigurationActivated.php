<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\CaseConfiguration;

/**
 * `CaseConfigurationActivated` — recorded when a Case Configuration transitions `reviewed → active`
 * (catalog-lifecycle-approval, design D9; product-catalog — Requirement: Product Lifecycle Events). The
 * verbatim §14.1 event name (category-neutral per §18); §14.2 binds it to the `reviewed → active` step
 * ONLY — the `draft → reviewed` checkpoint and the `retired → reviewed` reopen are audit-only (no domain
 * event), and there is no `*Reviewed` event anywhere in the catalog surface.
 *
 * One of the fourteen `*Activated`/`*Retired` lifecycle events this change adds — the Catalog slice of the
 * ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). It is
 * recorded by the shared `LifecycleTransition` mechanism (via the `ActivateCaseConfiguration` action, task
 * 4.2) inside the SAME transaction as the `lifecycle_state` write (§14.1 / invariant 4 — the transactional
 * outbox). The class is the single source of truth for the event's three contract facets, so the action
 * stays thin and free of magic strings (the event names no caller — the dependency runs action → event,
 * never back):
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Case Configuration;
 *   - {@see payload()} — the PII-free transition payload (entity id + lifecycle value only).
 */
final class CaseConfigurationActivated
{
    /** The verbatim §14.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'CaseConfigurationActivated';

    /** The envelope `entity_type` for a Case Configuration. */
    public const ENTITY_TYPE = 'CaseConfiguration';

    /**
     * The transition payload: a PII-free snapshot keyed on the Case Configuration id and the post-transition
     * `lifecycle_state` (`active`). A Case Configuration is a standalone reference entity — it has no parent
     * in the hierarchy and references no party (CLAUDE.md invariant 10 & the substrate's PII-free payload
     * discipline), so the payload carries no further ids. The descriptive packaging fields (`name`,
     * `units_per_case`, `packaging_type`) are the subject of the immutable creation record
     * ({@see CaseConfigurationCreated}), not of a state transition, and are deliberately omitted.
     *
     * @return array<string, mixed>
     */
    public static function payload(CaseConfiguration $caseConfiguration): array
    {
        return [
            'case_configuration_id' => $caseConfiguration->id,
            'lifecycle_state' => $caseConfiguration->lifecycle_state->value,
        ];
    }
}
