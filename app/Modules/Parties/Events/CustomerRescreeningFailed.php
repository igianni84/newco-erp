<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Customer;

/**
 * `CustomerRescreeningFailed` — recorded when a Customer's RE-SCREEN (any screening whose `trigger_source` is NOT
 * `onboarding` — the 12-month cadence, an AML-threshold trigger, or an operator ad-hoc re-screen) completes with a
 * `failed` verdict (parties-compliance, design L3/L4; party-registry — Requirement: Sanctions Screening Events).
 * The verbatim § 15.6 event name; the `failed` outcome of the rescreening pair (its `passed` sibling is
 * {@see CustomerRescreeningPassed}). One of the four sanctions screening events this slice records — the ONLY
 * compliance events in the change (KYC records none — design L3). The four split two phases × two outcomes:
 * onboarding `{Passed,Failed}` (the first screen) and rescreening `{Passed,Failed}` (every later re-screen). A
 * verdict landing `under_review` is NOT a completion and records nothing (design L4); its later resolution to
 * `failed` records THIS event.
 *
 * Recorded by exactly one writer — the forthcoming `RecordCustomerScreening` action (parties-compliance task 4.2)
 * — inside the same transaction as the `sanctions_status` write, tagged module `parties`, entity type `Customer`,
 * with the actor resolved from the `ActorContext` seam. The class is the single source of truth for the event's
 * three contract facets, so the action stays thin and free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Customer;
 *   - {@see payload()} — the PII-free screening payload.
 */
final class CustomerRescreeningFailed
{
    /** The verbatim § 15.6 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'CustomerRescreeningFailed';

    /** The envelope `entity_type` for a Customer. */
    public const ENTITY_TYPE = 'Customer';

    /**
     * The screening payload — the STRICT PII-free case (decisions/2026-06-12-event-substrate-and-audit-store.md;
     * the 10-year audit store holds no personal data). The Customer holds email/name/phone/date_of_birth on the
     * module table (where GDPR erasure operates); this payload DELIBERATELY OMITS all four. It carries only the
     * `customer_id`, the post-screening `sanctions_status`, and the `trigger_source` — the verdict and source as
     * their enum values, never personal data (party-registry — Requirement: Sanctions Screening Events). A consumer
     * needing personal data reads it through a published read contract, never by widening this payload.
     *
     * @return array<string, mixed>
     */
    public static function payload(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'sanctions_status' => $customer->sanctions_status?->value,
            'trigger_source' => $customer->screening_trigger_source?->value,
        ];
    }
}
