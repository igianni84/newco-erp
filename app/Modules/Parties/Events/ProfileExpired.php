<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Profile;

/**
 * `ProfileExpired` — recorded when a Profile transitions `Active → Lapsed` (parties-membership-suspension, design
 * L3/L5/L11; party-registry — Requirement: Profile Lapse and Grace Renewal / Demand-Side Status Events). The
 * verbatim § 15.2 event name; one of the eight demand-side status events this slice records — the Parties slice of
 * the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling).
 *
 * NAMING TRAP (design L3): the STATE is `Lapsed`, the EVENT is `ProfileExpired`. There is **no `ProfileLapsed`**
 * event — § 15.2 names the lapse event `ProfileExpired`. Never coin `ProfileLapsed`.
 *
 * Recorded by exactly one writer — the `LapseProfile` action (task 2.2) — inside the same transaction as the
 * `state` write (which also stamps `lapsed_at`, the 30-day grace anchor — DEC-034). In production the trigger is
 * "the membership validity period passes without a successful renewal" (§ 4.2.1) — a deferred scheduler/Module-E
 * seam; `LapseProfile` is the within-module writer, invoked directly (design L5). A `ProfileExpired` is always a
 * ROOT event (the lapse has no parent transition).
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Profile;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class ProfileExpired
{
    /** The verbatim § 15.2 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProfileExpired';

    /** The envelope `entity_type` for a Profile. */
    public const ENTITY_TYPE = 'Profile';

    /**
     * The transition payload: the Profile BY ID (`profile_id`) and the post-transition `state` (`lapsed`).
     * PII-free by nature — a Profile is a membership join, not a Party, and references its Customer/Club by id; it
     * carries no personal data. The payload is the structural id + the business `state` enum value only (the
     * `lapsed_at` grace anchor lives on the Profile row, not in the event payload).
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
