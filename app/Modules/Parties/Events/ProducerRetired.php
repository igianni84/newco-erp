<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Producer;

/**
 * `ProducerRetired` — recorded when a Producer transitions `active → retired` (parties-producer-lifecycle,
 * design L4/L5/L6; party-registry — Requirements: Producer Lifecycle, Supply-Side Lifecycle Events). The
 * verbatim § 15.4 event name; one of the seven supply-side lifecycle events this slice records, the Parties
 * slice of the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling).
 * Module 0 consumes it alongside {@see ProducerActivated} to gate Product Master activation (AC-K-XM-2) —
 * retirement preserves existing Product Masters but blocks new activations.
 *
 * It is recorded by exactly one writer — the {@see RetireProducer} action — and is the ROOT of the
 * Producer-retirement cascade (design L5/L6): retirement is never itself a cascade target (nothing retires a
 * Producer as a derived step in this slice), so a `ProducerRetired` always carries no causation and is the
 * correlation root, while every cascade {@see ClubSunset} the same action records carries this event's `id` as
 * its `causation_id` and shares its `correlation_id` — making the § 10.2 offboarding one queryable thread in the
 * 10-year audit log. The Profile leg of that cascade (per-Profile cancellation) is deferred (design L6) — this
 * event drives Club sunset only.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Producer;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class ProducerRetired
{
    /** The verbatim § 15.4 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProducerRetired';

    /** The envelope `entity_type` for a Producer. */
    public const ENTITY_TYPE = 'Producer';

    /**
     * The transition payload: the Producer BY ID (`producer_id`) and the post-transition `status` (`retired`).
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
