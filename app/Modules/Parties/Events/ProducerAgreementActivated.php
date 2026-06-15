<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\ProducerAgreement;

/**
 * `ProducerAgreementActivated` — recorded when a ProducerAgreement transitions `draft → active`
 * (parties-producer-lifecycle, design L4/L5/L7; party-registry — Requirements: ProducerAgreement Lifecycle,
 * Supply-Side Lifecycle Events). The verbatim § 15.5 event name; one of the seven supply-side lifecycle events
 * this slice records, the Parties slice of the ~120-event inter-module API (CLAUDE.md: events + contracts are
 * the only cross-module coupling).
 *
 * It is recorded by exactly one writer — the {@see ActivateProducerAgreement} action — and is the ROOT of the
 * supersession chain: a lone activation (no prior active in scope) is itself a root event, while an activation
 * that replaces a prior active in the same `(producer_id, club_id)` scope roots the derived
 * {@see ProducerAgreementSuperseded} (which carries this event's `id` as its `causation_id` and shares its
 * `correlation_id` — design L5). The `supersedes` payload field pairs the new agreement with the one it
 * replaced (BR-K-Agreement-3 renewal audit), null when the activation superseded nothing.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a ProducerAgreement;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class ProducerAgreementActivated
{
    /** The verbatim § 15.5 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProducerAgreementActivated';

    /** The envelope `entity_type` for a ProducerAgreement. */
    public const ENTITY_TYPE = 'ProducerAgreement';

    /**
     * The transition payload: the agreement BY ID (`producer_agreement_id` — the fully-qualified id key the
     * supply-side transition events use, matching `producer_id`/`club_id`; the creation event's `agreement_id`
     * is a separate parties-core choice left unchanged), both parties referenced BY ID (`producer_id` required,
     * `club_id` nullable — a null `club_id` is the distinct Producer-wide scope), the post-transition `status`
     * (`active`), and `supersedes` — the id of the prior active agreement this one replaced in the same scope
     * (BR-K-Agreement-1/3 supersession pairing), or null when it superseded nothing.
     *
     * PII-free by nature — a ProducerAgreement is a commercial agreement, not a Party, so it carries no personal
     * data; the structural-identity fields the creation event snapshots (term dates, settlement cadence) are not
     * the subject of a state transition and are deliberately omitted (the immutable creation record holds them).
     *
     * @return array<string, mixed>
     */
    public static function payload(ProducerAgreement $agreement, ?int $supersedes): array
    {
        return [
            'producer_agreement_id' => $agreement->id,
            'producer_id' => $agreement->producer_id,
            'club_id' => $agreement->club_id,
            'status' => $agreement->status->value,
            'supersedes' => $supersedes,
        ];
    }
}
