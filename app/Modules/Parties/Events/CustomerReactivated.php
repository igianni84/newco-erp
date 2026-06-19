<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Customer;

/**
 * `CustomerReactivated` — recorded when a Customer transitions `suspended → active` (parties-membership-suspension,
 * design L3/L7/L11; party-registry — Requirement: Customer Suspension and Closure / Demand-Side Status Events). The
 * verbatim § 15.1 event name; one of the eight demand-side status events this slice records — the Parties slice of
 * the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling).
 *
 * Recorded by exactly one writer — the `ReactivateCustomer` action (task 3.1) — inside the same transaction as the
 * `status` write. The restore cascades to the Customer's `Suspended` Profiles, but ONLY to those no longer covered
 * by any active Hold (the coverage-recompute — design L7): each cascade `ProfileReactivated` recorded in the same
 * transaction is a CAUSATION CHILD of THIS `CustomerReactivated` root (its `event_id` threaded as `causationId` +
 * `correlationId` — design L11). In production the transition is driven by the Hold→`suspended` coupling on the
 * lift of the last covering Customer-scope Hold (ADR 2026-06-19); the Action is also directly operator-invocable.
 * A `CustomerReactivated` is always a ROOT event: it is the head of the cascade, never a cascade target.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Customer;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class CustomerReactivated
{
    /** The verbatim § 15.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'CustomerReactivated';

    /** The envelope `entity_type` for a Customer. */
    public const ENTITY_TYPE = 'Customer';

    /**
     * The transition payload: the Customer BY ID (`customer_id`) and the post-transition `status` (`active`).
     * STRICT PII-free (decisions/2026-06-12-event-substrate-and-audit-store.md; the 10-year audit store holds no
     * personal data). The Customer holds email/name/phone/date_of_birth on the module table (where GDPR erasure
     * operates); this payload carries only the structural id + the business `status` enum value.
     *
     * @return array<string, mixed>
     */
    public static function payload(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'status' => $customer->status->value,
        ];
    }
}
