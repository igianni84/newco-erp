<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Club;

/**
 * `ClubCreated` — recorded when a Club is created in `active` (parties-core, design D7/D9; party-registry —
 * Requirement: Club, Spine Creation Events). The verbatim § 15.3 event name; one of the five `*Created`
 * events this slice records (Customer, Profile, Producer, Club, ProducerAgreement) — the Parties slice of the
 * ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling).
 *
 * The class is the single source of truth for the event's three contract facets, so the {@see CreateClub}
 * action stays thin and free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Club;
 *   - {@see payload()} — the PII-free creation payload.
 *
 * No `ClubSunset`/`ClubClosed` sibling exists in this change (design D2 scope guard).
 */
final class ClubCreated
{
    /** The verbatim § 15.3 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ClubCreated';

    /** The envelope `entity_type` for a Club. */
    public const ENTITY_TYPE = 'Club';

    /**
     * The creation payload: a snapshot of the Club's structural identity. The Club references its operating
     * Producer BY ID (`producer_id`) — the substrate's "parties by id only" discipline — and carries no
     * personal data (a Club is a program, not a Party). The `fee` is serialised through {@see Money::toPayload()}
     * to the envelope money shape `{minor_units, currency}` (integer minor units + ISO 4217 code, never a
     * float — invariant 6, DEC-169); a Club with no fee carries a null `fee` key.
     *
     * @return array<string, mixed>
     */
    public static function payload(Club $club): array
    {
        return [
            'club_id' => $club->id,
            'display_name' => $club->display_name,
            'producer_id' => $club->producer_id,
            'status' => $club->status->value,
            'fee' => $club->fee?->toPayload(),
            'registration_flow_type' => $club->registration_flow_type->value,
            'generates_credit' => $club->generates_credit,
            'invite_only' => $club->invite_only,
        ];
    }
}
