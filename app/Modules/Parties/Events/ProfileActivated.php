<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Profile;

/**
 * `ProfileActivated` — recorded when a Profile (a Club membership) transitions `Approved → Active`
 * (parties-membership-activation, design L9; party-registry — Requirement: Demand-Side Activation Events). The
 * verbatim § 15.2 event name; one of the three demand-side activation events this slice records — the Parties
 * slice of the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling).
 *
 * Recorded by exactly one writer — the {@see ActivateProfile} action (task 2.2) — inside the same transaction as
 * the `state` write. In production the transition is driven by Module E's `MembershipFeePaid` (or a free-club
 * activation where no fee applies — § 4.2.1); Module E does not exist, so that listener is a deferred Module-E
 * seam and `ActivateProfile` is invoked by the free-club / operator path directly — no Module-E event class is
 * fabricated. The transition has no parent event in its transaction, so a `ProfileActivated` is always a ROOT
 * event (no causation).
 *
 * The § 15.2 catalog names NO `ProfileApproved` / `ProfileRejected` (approve/decline are audit-only — design L2),
 * so `ProfileActivated` is the only Profile lifecycle event this slice records.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Profile;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class ProfileActivated
{
    /** The verbatim § 15.2 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProfileActivated';

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
