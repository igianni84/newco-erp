<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\ProducerAgreement;

/**
 * `ProducerAgreementSuperseded` — recorded when a prior `active` ProducerAgreement transitions `active →
 * superseded` because a replacement was activated in the same `(producer_id, club_id)` scope
 * (parties-producer-lifecycle, design L4/L5/L7; party-registry — Requirements: ProducerAgreement Lifecycle,
 * Supply-Side Lifecycle Events). The verbatim § 15.5 event name; one of the seven supply-side lifecycle events
 * this slice records.
 *
 * Supersession is NOT a direct operator transition — it is DERIVED, driven only by activating a replacement
 * (BR-K-Agreement-1: at most one active per scope). Its sole writer is the {@see ActivateProducerAgreement}
 * action, which records it right after the `ProducerAgreementActivated` of the superseding agreement, threading
 * that activation's `id` as this event's `causation_id` and its `correlation_id` (design L5) — so the renewal is
 * one queryable thread in the 10-year audit log. The `superseded_by` payload field references the superseding
 * agreement (the inverse of the activation's `supersedes`), pairing old + new (BR-K-Agreement-3).
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a ProducerAgreement;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class ProducerAgreementSuperseded
{
    /** The verbatim § 15.5 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProducerAgreementSuperseded';

    /** The envelope `entity_type` for a ProducerAgreement. */
    public const ENTITY_TYPE = 'ProducerAgreement';

    /**
     * The transition payload: the superseded agreement BY ID (`producer_agreement_id`), both parties BY ID
     * (`producer_id` required, `club_id` nullable), the post-transition `status` (`superseded`), and
     * `superseded_by` — the id of the superseding agreement whose activation drove this transition (the inverse
     * of the activation event's `supersedes`, pairing old + new — BR-K-Agreement-3). Always a real id: this
     * event is recorded only when an activation supersedes a prior active.
     *
     * PII-free — a ProducerAgreement is a commercial agreement, not a Party; the creation snapshot fields (term
     * dates, settlement cadence) are not the subject of a state transition and are deliberately omitted.
     *
     * @return array<string, mixed>
     */
    public static function payload(ProducerAgreement $agreement, int $supersededBy): array
    {
        return [
            'producer_agreement_id' => $agreement->id,
            'producer_id' => $agreement->producer_id,
            'club_id' => $agreement->club_id,
            'status' => $agreement->status->value,
            'superseded_by' => $supersededBy,
        ];
    }
}
