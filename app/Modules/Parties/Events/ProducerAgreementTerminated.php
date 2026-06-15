<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\ProducerAgreement;

/**
 * `ProducerAgreementTerminated` — recorded when a ProducerAgreement transitions `active → terminated`
 * (parties-producer-lifecycle, design L1/L2/L4; party-registry — Requirement: ProducerAgreement Lifecycle).
 * The verbatim § 15.5 event name; one of the seven supply-side lifecycle events this slice records, the Parties
 * slice of the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling).
 *
 * Termination is the terminal branch of the agreement FSM `draft → active → superseded | terminated` — a direct
 * operator transition (unlike supersession, which is DERIVED from activating a replacement). Its sole writer is
 * the {@see TerminateProducerAgreement} action. Termination is never a cascade target or source: it does NOT
 * cascade to any Producer-level state change (§ 4.6.1 — the Producer FSM is independent of its agreements), and
 * it drives no derived event (there is no old + new pairing as in renewal/supersession). So a
 * `ProducerAgreementTerminated` is always a ROOT event — it carries no `causation_id` and is its own correlation
 * root.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a ProducerAgreement;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class ProducerAgreementTerminated
{
    /** The verbatim § 15.5 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProducerAgreementTerminated';

    /** The envelope `entity_type` for a ProducerAgreement. */
    public const ENTITY_TYPE = 'ProducerAgreement';

    /**
     * The transition payload: the agreement BY ID (`producer_agreement_id` — the fully-qualified id key the
     * supply-side transition events use, matching `producer_id`/`club_id`; the creation event's `agreement_id`
     * is a separate parties-core choice left unchanged), both parties referenced BY ID (`producer_id` required,
     * `club_id` nullable — a null `club_id` is the distinct Producer-wide scope), and the post-transition
     * `status` (`terminated`). No linkage field — termination pairs with nothing and cascades to nothing, so
     * (unlike {@see ProducerAgreementActivated}'s `supersedes` / {@see ProducerAgreementSuperseded}'s
     * `superseded_by`) the payload is the bare four-key terminal subset.
     *
     * PII-free by nature — a ProducerAgreement is a commercial agreement, not a Party, so it carries no personal
     * data; the structural-identity fields the creation event snapshots (term dates, settlement cadence) are not
     * the subject of a state transition and are deliberately omitted (the immutable creation record holds them).
     *
     * @return array<string, mixed>
     */
    public static function payload(ProducerAgreement $agreement): array
    {
        return [
            'producer_agreement_id' => $agreement->id,
            'producer_id' => $agreement->producer_id,
            'club_id' => $agreement->club_id,
            'status' => $agreement->status->value,
        ];
    }
}
