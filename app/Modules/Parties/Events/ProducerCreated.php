<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Producer;

/**
 * `ProducerCreated` — recorded when a Producer is created in `draft` (parties-core, design D7; party-registry
 * — Requirement: Producer Registry, Spine Creation Events). The verbatim § 15.4 event name; one of the five
 * `*Created` events this slice records (Customer, Profile, Producer, Club, ProducerAgreement) — the Parties
 * slice of the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module
 * coupling).
 *
 * The class is the single source of truth for the event's three contract facets, so the {@see CreateProducer}
 * action stays thin and free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Producer;
 *   - {@see payload()} — the PII-free creation payload.
 *
 * No `*Activated`/`*Retired` sibling exists in this change (design D2 scope guard).
 */
final class ProducerCreated
{
    /** The verbatim § 15.4 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProducerCreated';

    /** The envelope `entity_type` for a Producer. */
    public const ENTITY_TYPE = 'Producer';

    /**
     * The creation payload: a snapshot of the Producer's structural identity. The Producer is NOT a Party
     * (§ 4.4) and references no party, so the payload carries no party/personal data. The translatable
     * `description` is deliberately not restated here — the event contract is keyed on the structural
     * identity and the `producer_id`; a consumer that later needs the description reads it through a published
     * read contract, never by widening this payload (mirrors ProductMasterCreated / winery_story).
     *
     * @return array<string, mixed>
     */
    public static function payload(Producer $producer): array
    {
        return [
            'producer_id' => $producer->id,
            'name' => $producer->name,
            'region' => $producer->region,
            'appellation' => $producer->appellation,
            'country' => $producer->country,
            'status' => $producer->status->value,
        ];
    }
}
