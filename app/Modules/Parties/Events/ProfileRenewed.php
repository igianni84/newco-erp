<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Profile;

/**
 * `ProfileRenewed` — recorded when a Profile transitions `Lapsed → Active` within the grace window
 * (parties-membership-suspension, design L3/L5/L11; party-registry — Requirement: Profile Lapse and Grace Renewal /
 * Demand-Side Status Events). The verbatim § 15.2 event name; one of the eight demand-side status events this slice
 * records — the Parties slice of the ~120-event inter-module API (CLAUDE.md: events + contracts are the only
 * cross-module coupling).
 *
 * NAMING TRAP (design L3): the grace restore (`Lapsed → Active`) records `ProfileRenewed`, NOT `ProfileReactivated`
 * (which is the `Suspended → Active` edge only). Never conflate the lapse-renewal cycle with suspension restore.
 *
 * Recorded by exactly one writer — the `RenewProfile` action (task 2.2) — inside the same transaction as the
 * `state` write (which also clears `lapsed_at`). The Action enforces the 30-day grace in code: it transitions only
 * when `state === Lapsed` AND `now ≤ lapsed_at + 30 days` (DEC-034); past the grace it rejects. In production the
 * trigger is a renewal payment (Module E `MembershipFeePaid` — § 4.2.1), a deferred Module-E seam; `RenewProfile`
 * is the within-module writer, invoked directly (design L5). A `ProfileRenewed` is always a ROOT event.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Profile;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class ProfileRenewed
{
    /** The verbatim § 15.2 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProfileRenewed';

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
