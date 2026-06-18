<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use Carbon\CarbonImmutable;

/**
 * `OriginatingClubLocked` — recorded on a Customer's FIRST-EVER Profile approval across any Club, when the
 * one-shot Originating-Club link is set (parties-membership-activation, design L3/L9; party-registry —
 * Requirement: Demand-Side Activation Events). The verbatim § 15.6 event name; one of the three demand-side
 * activation events this slice records — the Parties slice of the ~120-event inter-module API (CLAUDE.md: events +
 * contracts are the only cross-module coupling).
 *
 * Recorded by exactly one writer — the {@see ApproveProfile} action (task 2.1) — as an in-transaction side-effect
 * of the first approval (the lock is NOT a standalone Action; `LockOriginatingClub` / `SetOriginatingClub` stay
 * forbidden — design L3), gated on `Customer.originating_club_id` being currently unset (one-shot: a later Club's
 * approval neither re-fires nor re-sets the link). The approval itself records no Profile event (audit-only —
 * § 15.2 names no `ProfileApproved`), so this lock event has no parent in its transaction: it is always a ROOT
 * event (no causation).
 *
 * The downstream consumers § 6 / § 15.6 name — Module S settlement-eligibility, Module E D19 Discovery-revenue
 * accrual, HubSpot — are deferred: this slice RECORDS the lock (the capture); all consumption is downstream.
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` (`Customer` — the lock is a Customer-state event);
 *   - {@see payload()} — the PII-free lock payload (§ 6.1 verbatim).
 */
final class OriginatingClubLocked
{
    /** The verbatim § 15.6 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'OriginatingClubLocked';

    /** The envelope `entity_type` — the Customer (the lock is a Customer-state write; the Club rides in the payload). */
    public const ENTITY_TYPE = 'Customer';

    /**
     * The lock payload (§ 6.1 verbatim): the Customer, the locking Club, the triggering membership and the moment
     * — all by id / as a timestamp, STRICT PII-free (decisions/2026-06-12-event-substrate-and-audit-store.md; the
     * 10-year audit store holds no personal data). The locking `club_id` is the triggering Profile's Club (the
     * Club the Originating-Club link is set to — design L3); `locked_at` is the ISO-8601 moment of the lock — there
     * is no `locked_at` column (the lock surface is `originating_club_id` alone), so the recorded moment is the
     * transaction clock ({@see CarbonImmutable::now()}, the codebase's single-moment idiom). No name, email, phone
     * or date of birth: a consumer needing personal data reads it through a published read contract, never by
     * widening this payload.
     *
     * @return array<string, mixed>
     */
    public static function payload(Customer $customer, Profile $triggering): array
    {
        return [
            'customer_id' => $customer->id,
            'club_id' => $triggering->club_id,
            'profile_id' => $triggering->id,
            'locked_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
