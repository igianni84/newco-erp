<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Customer;

/**
 * `CustomerCreated` — recorded when a Customer is created in `pending` (parties-core, design D7; party-registry
 * — Requirement: Customer Identity, Spine Creation Events). The verbatim § 15.1 event name; one of the five
 * `*Created` events this slice records (Customer, Profile, Producer, Club, ProducerAgreement) — the Parties
 * slice of the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling).
 *
 * The class is the single source of truth for the event's three contract facets, so the {@see CreateCustomer}
 * action stays thin and free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Customer;
 *   - {@see payload()} — the PII-free creation payload.
 *
 * No `CustomerActivated`/`*Suspended` sibling exists in this change (design D2 scope guard). The co-provisioned
 * Account is event-silent — there is no `AccountCreated` (design D7).
 */
final class CustomerCreated
{
    /** The verbatim § 15.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'CustomerCreated';

    /** The envelope `entity_type` for a Customer. */
    public const ENTITY_TYPE = 'Customer';

    /**
     * The creation payload — the STRICT PII-free case (design D7; the event substrate's 10-year audit store
     * holds no personal data — decisions/2026-06-12-event-substrate-and-audit-store.md). The Customer holds
     * email/name/phone/date_of_birth on the module table (where GDPR erasure operates); this payload
     * DELIBERATELY OMITS all four. It carries only the structural identity + non-PII business fields: the
     * `customer_id`, the immutable `party_type` marker, the birth `status`, the currency/locale PREFERENCE
     * strings, and the `originating_club_id` (by id, null at creation — design D6). A consumer that needs
     * personal data reads it through a published read contract, never by widening this payload.
     *
     * @return array<string, mixed>
     */
    public static function payload(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'party_type' => $customer->party_type->value,
            'status' => $customer->status->value,
            'preferred_currency' => $customer->preferred_currency,
            'preferred_locale' => $customer->preferred_locale,
            'originating_club_id' => $customer->originating_club_id,
        ];
    }
}
