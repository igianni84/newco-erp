<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Club;

/**
 * `ClubClosed` — recorded when a Club transitions `sunset → closed` (parties-producer-lifecycle, design L4/L8;
 * party-registry — Requirements: Club Lifecycle, Supply-Side Lifecycle Events). The verbatim § 15.3 event name;
 * one of the seven supply-side lifecycle events this slice records, the Parties slice of the ~120-event
 * inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling).
 *
 * It is recorded by exactly one writer — the {@see CloseClub} action — the terminal step of the Club FSM
 * `active → sunset → closed`. Unlike its sibling {@see ClubSunset}, closure is never a cascade target in this
 * slice: the Producer-retirement cascade sunsets the operated Clubs, it does not close them. So a `ClubClosed`
 * is always a root event (it carries no causation), and its single writer needs no causation/correlation
 * threading parameters.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Club;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class ClubClosed
{
    /** The verbatim § 15.3 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ClubClosed';

    /** The envelope `entity_type` for a Club. */
    public const ENTITY_TYPE = 'Club';

    /**
     * The transition payload: the Club's id, its operating Producer BY ID (`producer_id` — the substrate's
     * "parties by id only" discipline), and the post-transition `status` (`closed`). PII-free by nature — a
     * Club is a program, not a Party, so it carries no personal data; the structural-identity fields the
     * creation event snapshots (display name, fee, flags) are not the subject of a state transition and are
     * deliberately omitted (the immutable creation record holds them).
     *
     * @return array<string, mixed>
     */
    public static function payload(Club $club): array
    {
        return [
            'club_id' => $club->id,
            'producer_id' => $club->producer_id,
            'status' => $club->status->value,
        ];
    }
}
