<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Profile;

/**
 * `ProfileCreated` — recorded when a Profile (a Club membership) is created in `applied` (parties-core, design
 * D7; party-registry — Requirement: Profile — Multi-Profile Membership, Spine Creation Events). The verbatim
 * § 15.2 event name; one of the five `*Created` events this slice records (Customer, Profile, Producer, Club,
 * ProducerAgreement) — the Parties slice of the ~120-event inter-module API (CLAUDE.md: events + contracts are
 * the only cross-module coupling).
 *
 * The class is the single source of truth for the event's three contract facets, so the {@see CreateProfile}
 * action stays thin and free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Profile;
 *   - {@see payload()} — the PII-free creation payload.
 *
 * No `ProfileActivated`/`ProfileApproved`/`*Suspended`/lifecycle sibling exists in this change (design D2 scope
 * guard — the nine-state transitions arrive with the deferred parties-membership-lifecycle change).
 */
final class ProfileCreated
{
    /** The verbatim § 15.2 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'ProfileCreated';

    /** The envelope `entity_type` for a Profile. */
    public const ENTITY_TYPE = 'Profile';

    /**
     * The creation payload: a snapshot of the Profile's structural identity. The Customer, Club and (optional)
     * inviter are referenced BY ID (the substrate's "parties by id only" discipline) and the Profile carries no
     * personal data (it is a membership join, not a Party). `tier` / `role` are the nullable launch attributes —
     * business fields, never PII.
     *
     * @return array<string, mixed>
     */
    public static function payload(Profile $profile): array
    {
        return [
            'profile_id' => $profile->id,
            'customer_id' => $profile->customer_id,
            'club_id' => $profile->club_id,
            'state' => $profile->state->value,
            'tier' => $profile->tier,
            'role' => $profile->role,
            'invited_by_customer_id' => $profile->invited_by_customer_id,
        ];
    }
}
