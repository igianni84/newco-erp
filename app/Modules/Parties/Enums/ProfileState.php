<?php

namespace App\Modules\Parties\Enums;

/**
 * The Profile membership-state domain (design D2/D8; party-registry — Requirement:
 * Profile — Multi-Profile Membership / Birth States Recorded, Lifecycle Transitions
 * Deferred).
 *
 * The spec's verbatim nine-state Profile state machine (Module K PRD § 4.2.1):
 * `applied → waiting_list → approved → rejected → active → suspended → lapsed →
 * cancelled → inactive`. A Profile is born `Applied` — or `WaitingList`, when its
 * target Club is already at its Hero-Package capacity (parties-hero-package, design
 * D6; canon § 7.1 step 6): `Actions\CreateProfile` routes the birth state, and an
 * applicant to a full Club is waitlisted, never turned away. The three terminal states
 * `rejected`, `cancelled`, `inactive` (§ 4.2.1 — `rejected` is terminal-for-this-
 * application; `cancelled`/`inactive` are terminal soft-delete states preserving
 * audit history) are exactly the set the D8 partial-unique index on
 * `parties_profiles` excludes — `(customer_id, club_id) WHERE state NOT IN
 * ('rejected','cancelled','inactive')` — so "at most one non-terminal Profile per
 * Customer–Club pair" (BR-K-Identity-2) coexists with "rejected Profiles are not
 * reused" (§ 4.2.1). Every edge between these states is written by a Parties
 * Action, which records the § 15.2 status event for that edge (`ProfileActivated`
 * / `ProfileSuspended` / `ProfileReactivated` / `ProfileExpired` / `ProfileRenewed`
 * / `ProfileInactive`; approve, decline and cancel are AUDIT-ONLY — the catalog
 * names no event for them). `WaitingList` is the Hero-Package capacity-overflow
 * state (parties-hero-package, design D7): it is entered at application (birth, by
 * `Actions\CreateProfile`) and at approval (the divert, by `Actions\ApproveProfile` —
 * task 2.2), recording `Events\WaitingListJoined` at both entry points, and it is
 * left ONLY by a Producer's manual approve or decline — nothing promotes a Profile
 * off it automatically.
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
