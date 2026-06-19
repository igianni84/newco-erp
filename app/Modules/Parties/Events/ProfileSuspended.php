<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Profile;

/**
 * `ProfileSuspended` — recorded when a Profile transitions `Active → Suspended` (parties-membership-suspension,
 * design L3/L9/L11; party-registry — Requirement: Profile Suspension and Restoration / Demand-Side Status Events).
 * The verbatim § 15.2 event name; one of the eight demand-side status events this slice records — the Parties slice
 * of the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling).
 *
 * Recorded by two writers, both inside the same transaction as the `state` write:
 *   - `SuspendProfile` (task 2.1, directly invoked) records a ROOT `ProfileSuspended`;
 *   - `SuspendCustomer` (task 3.1) records a `ProfileSuspended` for each cascaded `Active` Profile as a CAUSATION
 *     CHILD of the `CustomerSuspended` root (its `event_id` threaded as `causationId` + `correlationId` — L11).
 * In production these transitions are driven by the Hold→`suspended` coupling (a Profile-scope or cascading
 * Customer-scope Hold — ADR 2026-06-19); the Action is also directly operator-invocable. Suspension is
 * state-preserving (AC-K-FSM-2a — design L9): only `state` is written, no voucher/order/reservation/Club Credit.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Profile;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class ProfileSuspended
{
    /** The verbatim § 15.2 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProfileSuspended';

    /** The envelope `entity_type` for a Profile. */
    public const ENTITY_TYPE = 'Profile';

    /**
     * The transition payload: the Profile BY ID (`profile_id`) and the post-transition `state` (`suspended`).
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
