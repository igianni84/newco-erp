<?php

namespace App\Modules\Parties\Enums;

/**
 * The Profile membership-state domain (design D2/D8; party-registry — Requirement:
 * Profile — Multi-Profile Membership / Birth States Recorded, Lifecycle Transitions
 * Deferred).
 *
 * The spec's verbatim nine-state Profile state machine (Module K PRD § 4.2.1):
 * `applied → waiting_list → approved → rejected → active → suspended → lapsed →
 * cancelled → inactive`. A Profile is born `Applied`. The three terminal states
 * `rejected`, `cancelled`, `inactive` (§ 4.2.1 — `rejected` is terminal-for-this-
 * application; `cancelled`/`inactive` are terminal soft-delete states preserving
 * audit history) are exactly the set the D8 partial-unique index on
 * `parties_profiles` excludes — `(customer_id, club_id) WHERE state NOT IN
 * ('rejected','cancelled','inactive')` — so "at most one non-terminal Profile per
 * Customer–Club pair" (BR-K-Identity-2) coexists with "rejected Profiles are not
 * reused" (§ 4.2.1). This change stores the state but writes NO transition and
 * emits no `*Activated`/`ProfileExpired`/`WaitingListJoined`/etc. (those arrive
 * with the deferred `parties-membership-lifecycle` change).
 *
 * - case name    = the state in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum ProfileState: string
{
    case Applied = 'applied';
    case WaitingList = 'waiting_list';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Active = 'active';
    case Suspended = 'suspended';
    case Lapsed = 'lapsed';
    case Cancelled = 'cancelled';
    case Inactive = 'inactive';
}
