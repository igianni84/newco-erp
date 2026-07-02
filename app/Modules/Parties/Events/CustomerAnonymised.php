<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Customer;

/**
 * `CustomerAnonymised` — recorded when a Customer's personal data is erased in place by the GDPR right-to-erasure
 * (parties-anonymisation, design D3; party-registry — Requirement: Customer Anonymisation (Right-to-Erasure)). The
 * PII-free erasure signal downstream consumers key on to drop any personal data they cached for the Customer — the
 * Parties slice of the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module
 * coupling).
 *
 * Recorded by exactly one writer — the {@see AnonymiseCustomer} action (task 3.4) — inside the SAME transaction as
 * the PII overwrite / `anonymised_at` stamp / audit redaction, so the erasure and its signal commit or roll back
 * together. Anonymisation is ORTHOGONAL to the Customer status FSM (BR-K-Customer-2): a Customer of ANY status
 * (typically `closed`) may be anonymised and keeps its status, so `CustomerAnonymised` is NEVER a status event
 * ({@see CustomerClosed}) — it is the erasure event, always a ROOT (the erasure has no parent transition). The
 * Action's idempotent early-return means an already-anonymised Customer records NO second `CustomerAnonymised`.
 *
 * DELIBERATE ADDITION over the frozen event-free anonymisation: § 15.1 left erasure emitting no event; this event
 * is the addition adopted by ADR decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md (canon
 * MVP-DEC-015, point (d) — design D3), so erasure leaves an auditable, PII-free trace rather than mutating
 * silently.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Customer (also the scope the Action passes to the audit
 *     redaction, so the event and the redaction share one entity_type source);
 *   - {@see payload()} — the PII-free erasure payload.
 */
final class CustomerAnonymised
{
    /** The event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'CustomerAnonymised';

    /** The envelope `entity_type` for a Customer — shared with every other Customer event AND the audit-redaction scope. */
    public const ENTITY_TYPE = 'Customer';

    /**
     * The erasure payload: the Customer BY ID (`customer_id`) and the moment it was anonymised (`anonymised_at`, the
     * ISO-8601 render of the persisted `anonymised_at` column — a SINGLE source of truth, so the event's moment
     * equals the row's; contrast {@see OriginatingClubLocked}, whose moment has no column and so stamps the
     * transaction clock). STRICT PII-free (decisions/2026-06-12-event-substrate-and-audit-store.md; the 10-year
     * event store holds no personal data) — the whole POINT of the event is erasure, so it carries NO
     * name/email/phone/date-of-birth/address, only the structural id + the timestamp. A consumer needing more reads
     * a published read contract, never a widened payload.
     *
     * @return array<string, mixed>
     */
    public static function payload(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'anonymised_at' => $customer->anonymised_at?->toIso8601String(),
        ];
    }
}
