<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Profile;

/**
 * `WaitingListJoined` — recorded when a Profile enters the `WaitingList` state because its Club is at its
 * Hero-Package capacity (parties-hero-package, design D7; party-registry — Requirement: WaitingList Placement,
 * Conversion and Decline). The verbatim § 15.6 event name (Module K PRD § 15.6:796 — `WaitingListJoined` (§ 4.2.1),
 * "fires when a Profile transitions to `WaitingList`"); its declared consumer is HubSpot's waitlist-confirmation.
 * One of the ~120 events of the inter-module API (CLAUDE.md: events + contracts are the only cross-module coupling).
 *
 * TWO WRITERS, ONE EVENT (design D7). `WaitingList` has two entry points and this event is recorded at BOTH, each
 * inside the same transaction as the `state` write:
 *   - `CreateProfile` (task 2.1) — BIRTH at application: an applicant for a full Club is born `waiting_list`
 *     rather than `applied`, recording `ProfileCreated` AND this event (canon § 7.1 step 6). No Club-row lock:
 *     neither `Applied` nor `WaitingList` holds a seat, so the birth gate cannot oversell.
 *   - `ApproveProfile` (task 2.2) — DIVERT at approval: an `applied` Profile approved into a full Club transitions
 *     to `waiting_list` instead of `active`, with NO charge, NO Originating-Club lock and NO `ProfileActivated`
 *     (AC-K-J-13). The gate reads capacity under a `parties_clubs` row lock.
 *
 * A BIRTH IS NOT A TRANSITION, AND WE FIRE ON BOTH ANYWAY (design D7, a RECORDED resolution). AC-K-EVT-11 and
 * § 15.6 both word the trigger as "when a Profile **transitions** to `WaitingList`". The canon recon confirmed the
 * birth case was never asked (zero occurrences across all 18 canon issues), and an applicant *born* on the waitlist
 * needs the same HubSpot confirmation as one diverted at approval. We extend an existing canon event's trigger; we
 * invent no name. Flagged to Paolo (ADR open question 1); it blocks nothing.
 *
 * NOT RECORDED where no entry into `WaitingList` happens: `DeclineProfile` on a waitlisted Profile is event-silent
 * (`WaitingList → Rejected`, the write is its own audit record), and a re-approval of a Profile ALREADY in
 * `waiting_list` whose Club is still full throws `IllegalProfileTransition::clubAtCapacity()` — never a second
 * `WaitingListJoined`. There is no automatic promotion off the waitlist on any trigger (canon § 13.5:655;
 * MVP-DEC-022), so no listener, scheduler, job or observer ever records this event's inverse.
 *
 * A `WaitingListJoined` is always a ROOT event (no causation) — like every other Parties event.
 *
 * The class is the single source of truth for the event's three contract facets, so the actions stay thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Profile;
 *   - {@see payload()} — the PII-free waitlist payload.
 */
final class WaitingListJoined
{
    /** The verbatim § 15.6 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'WaitingListJoined';

    /** The envelope `entity_type` for a Profile. */
    public const ENTITY_TYPE = 'Profile';

    /**
     * The waitlist payload: the Profile, its Customer and its Club BY ID (the substrate's "parties by id only"
     * discipline) plus the post-write `state` (`waiting_list`). Unlike the `ProfileActivated` / `ProfileRenewed`
     * transition payloads, which carry the Profile id alone, this one names the Customer and the Club: the declared
     * consumer (HubSpot's waitlist-confirmation) has to know WHO joined WHICH Club's waitlist, and an id is the only
     * PII-free way to say it. A Profile is a membership join, not a Party — it carries no personal data itself, and
     * no name, email, phone or date of birth reaches the 10-year audit store.
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
        ];
    }
}
