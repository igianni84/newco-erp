<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Producer;

/**
 * `ProducerActivated` — recorded when a Producer transitions `draft → active` (parties-producer-lifecycle,
 * design L4/L8; party-registry — Requirements: Producer Lifecycle, Supply-Side Lifecycle Events). The verbatim
 * § 15.4 event name; one of the seven supply-side lifecycle events this slice records, the Parties slice of the
 * ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling). Module 0
 * consumes it to gate Product Master activation (AC-K-XM-2) — which is why emitting it unblocks the downstream
 * `catalog-lifecycle-approval` change.
 *
 * It is recorded by exactly one writer — the {@see ActivateProducer} action — the first step of the Producer
 * FSM `draft → active → retired`. Activation is a standalone operator action, never a cascade target in this
 * slice, so a `ProducerActivated` is always a root event (it carries no causation) and its single writer needs
 * no causation/correlation threading parameters.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Producer;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class ProducerActivated
{
    /** The verbatim § 15.4 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProducerActivated';

    /** The envelope `entity_type` for a Producer. */
    public const ENTITY_TYPE = 'Producer';

    /**
     * The transition payload: the Producer BY ID (`producer_id`) and the post-transition `status` (`active`).
     * PII-free by nature — a Producer is NOT a Party (§ 4.4) and references no party, so it carries no personal
     * data; the structural-identity fields the creation event snapshots (name, region, appellation, country)
     * are not the subject of a state transition and are deliberately omitted (the immutable creation record
     * holds them).
     *
     * @return array<string, mixed>
     */
    public static function payload(Producer $producer): array
    {
        return [
            'producer_id' => $producer->id,
            'status' => $producer->status->value,
        ];
    }
}
