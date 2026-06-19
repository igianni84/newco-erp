<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Customer;

/**
 * `CustomerClosed` — recorded when a Customer transitions `active | suspended → closed` (parties-membership-suspension,
 * design L3/L7/L11; party-registry — Requirement: Customer Suspension and Closure / Demand-Side Status Events). The
 * verbatim § 15.1 event name; one of the eight demand-side status events this slice records — the Parties slice of
 * the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling).
 *
 * Recorded by exactly one writer — the `CloseCustomer` action (task 3.2) — inside the same transaction as the
 * `status` write. `closed` is terminal. Unlike `CustomerSuspended`, closure does **NOT** cascade to the Customer's
 * Profiles — § 15.1 `CustomerClosed` names no cascade, and zero-invention leaves Profile resolution-at-closure to
 * the spec's silence (design L7). `closed` is a status, NOT anonymisation (a separate deferred seam). So a
 * `CustomerClosed` is always a ROOT event with no causation children.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Customer;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class CustomerClosed
{
    /** The verbatim § 15.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'CustomerClosed';

    /** The envelope `entity_type` for a Customer. */
    public const ENTITY_TYPE = 'Customer';

    /**
     * The transition payload: the Customer BY ID (`customer_id`) and the post-transition `status` (`closed`).
     * STRICT PII-free (decisions/2026-06-12-event-substrate-and-audit-store.md; the 10-year audit store holds no
     * personal data — and `closed` ≠ anonymised: erasure of the PII columns is a separate deferred seam). This
     * payload carries only the structural id + the business `status` enum value.
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
