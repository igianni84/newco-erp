<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Profile;

/**
 * `ProfileInactive` — recorded when a Profile transitions `Active → Inactive` (parties-membership-suspension,
 * design L3/L11; party-registry — Requirement: Profile Cancellation and Deactivation / Demand-Side Status Events).
 * The verbatim § 15.2 event name; one of the eight demand-side status events this slice records — the Parties slice
 * of the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling).
 *
 * Recorded by exactly one writer — the `DeactivateProfile` action (task 2.3) — inside the same transaction as the
 * `state` write. `Inactive` is one of the terminal states the partial-unique index excludes (`state NOT IN
 * ('rejected','cancelled','inactive')`), so a deactivated Profile does not block a fresh application for the same
 * Customer–Club pair. Contrast `CancelProfile` (`→ Cancelled`), which is AUDIT-ONLY and records no event (§ 15.2
 * names no `ProfileCancelled` — design L2): deactivation DOES record this event. A `ProfileInactive` is always a
 * ROOT event.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Profile;
 *   - {@see payload()} — the PII-free transition payload.
 */
final class ProfileInactive
{
    /** The verbatim § 15.2 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProfileInactive';

    /** The envelope `entity_type` for a Profile. */
    public const ENTITY_TYPE = 'Profile';

    /**
     * The transition payload: the Profile BY ID (`profile_id`) and the post-transition `state` (`inactive`).
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
