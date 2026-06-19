<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Profile;

/**
 * `ProfileReactivated` — recorded when a Profile transitions `Suspended → Active` (parties-membership-suspension,
 * design L3/L11; party-registry — Requirement: Profile Suspension and Restoration / Demand-Side Status Events). The
 * verbatim § 15.2 event name; one of the eight demand-side status events this slice records — the Parties slice of
 * the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling).
 *
 * NAMING TRAP (design L3): `ProfileReactivated` is recorded **only** on the `Suspended → Active` restore. The
 * `Lapsed → Active` grace edge records `ProfileRenewed`, NOT this event — never conflate the two.
 *
 * Recorded by two writers, both inside the same transaction as the `state` write:
 *   - `ReactivateProfile` (task 2.1, directly invoked) records a ROOT `ProfileReactivated`;
 *   - `ReactivateCustomer` (task 3.1) records a `ProfileReactivated` for each cascade-restored Profile (only those
 *     no longer covered by any active Hold — the coverage-recompute, L7) as a CAUSATION CHILD of the
 *     `CustomerReactivated` root (its `event_id` threaded as `causationId` + `correlationId` — L11).
 * In production these transitions are driven by the Hold→`suspended` coupling on the lift of the last covering Hold
 * (ADR 2026-06-19); the Action is also directly operator-invocable.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Profile;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class ProfileReactivated
{
    /** The verbatim § 15.2 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProfileReactivated';

    /** The envelope `entity_type` for a Profile. */
    public const ENTITY_TYPE = 'Profile';

    /**
     * The transition payload: the Profile BY ID (`profile_id`) and the post-transition `state` (`active`).
     * PII-free by nature — a Profile is a membership join, not a Party, and references its Customer/Club by id; it
     * carries no personal data. The payload is the structural id + the business `state` enum value only.
     *
     * @return array<string, mixed>
     */
    public static function payload(Profile $profile): array
    {
        return [
            'profile_id' => $profile->id,
            'state' => $profile->state->value,
        ];
    }
}
