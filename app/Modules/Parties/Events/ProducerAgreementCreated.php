<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\ProducerAgreement;

/**
 * `ProducerAgreementCreated` — recorded when a ProducerAgreement is created in `draft` (parties-core, design
 * D7; party-registry — Requirement: ProducerAgreement, Spine Creation Events). The verbatim § 15.5 event name;
 * one of the five `*Created` events this slice records (Customer, Profile, Producer, Club, ProducerAgreement) —
 * the Parties slice of the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module
 * coupling).
 *
 * The class is the single source of truth for the event's three contract facets, so the
 * {@see CreateProducerAgreement} action stays thin and free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a ProducerAgreement;
 *   - {@see payload()} — the PII-free creation payload.
 *
 * No `ProducerAgreementSuperseded`/`*Terminated` sibling exists in this change (design D2 scope guard).
 */
final class ProducerAgreementCreated
{
    /** The verbatim § 15.5 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProducerAgreementCreated';

    /** The envelope `entity_type` for a ProducerAgreement. */
    public const ENTITY_TYPE = 'ProducerAgreement';

    /**
     * The creation payload: a snapshot of the agreement's structural identity. Both parties are referenced BY
     * ID (`producer_id` required, `club_id` nullable — the substrate's "parties by id only" discipline) and the
     * agreement carries no personal data (it is a commercial agreement, not a Party). The term dates are
     * serialised as ISO `Y-m-d` strings (or null) and `settlement_cadence` is the D19 seam string — business
     * attributes, never PII.
     *
     * @return array<string, mixed>
     */
    public static function payload(ProducerAgreement $agreement): array
    {
        return [
            'agreement_id' => $agreement->id,
            'producer_id' => $agreement->producer_id,
            'club_id' => $agreement->club_id,
            'status' => $agreement->status->value,
            'term_start' => $agreement->term_start?->toDateString(),
            'term_end' => $agreement->term_end?->toDateString(),
            'settlement_cadence' => $agreement->settlement_cadence,
        ];
    }
}
