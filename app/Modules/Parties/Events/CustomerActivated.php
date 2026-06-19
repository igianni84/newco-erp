<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Customer;

/**
 * `CustomerActivated` — recorded when a Customer transitions `pending → active` (parties-membership-activation,
 * design L9; party-registry — Requirement: Demand-Side Activation Events). The verbatim § 15.1 event name; one of
 * the three demand-side activation events this slice records — the Parties slice of the ~120-event inter-module
 * API (CLAUDE.md: events + contracts are the only cross-module coupling).
 *
 * Recorded by exactly one writer — the {@see ActivateCustomer} action (task 2.3) — inside the same transaction as
 * the `status` write, behind the composite onboarding gate (email verified ∧ T&C ∧ privacy accepted ∧ sanctions
 * passed ∧ KYC cleared — § 4.1). Activation is an explicit operator / registration-surface action, never a
 * cascade target, so a `CustomerActivated` is always a ROOT event (it carries no causation) and its single writer
 * needs no causation/correlation threading parameters.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Customer;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class CustomerActivated
{
    /** The verbatim § 15.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'CustomerActivated';

    /** The envelope `entity_type` for a Customer. */
    public const ENTITY_TYPE = 'Customer';

    /**
     * The transition payload: the Customer BY ID (`customer_id`) and the post-transition `status` (`active`).
     * STRICT PII-free (decisions/2026-06-12-event-substrate-and-audit-store.md; the 10-year audit store holds no
     * personal data). The Customer holds email/name/phone/date_of_birth on the module table (where GDPR erasure
     * operates); this payload carries only the structural id + the business `status` enum value — never personal
     * data, and never an acceptance-timestamp value.
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
