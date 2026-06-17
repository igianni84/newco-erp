<?php

namespace App\Modules\Parties\Enums;

/**
 * The sanctions-screening lifecycle domain (design L1/L4; party-registry —
 * Requirement: Customer Sanctions Screening Lifecycle).
 *
 * The spec's verbatim four-state sanctions domain `pending → passed | failed |
 * under_review` plus `under_review → passed | failed` (Module K PRD § 9.2), held in
 * an additive nullable `sanctions_status` column (DEC-071) on `parties_customers`.
 * The lifecycle is separate from both the Customer status FSM and the KYC FSM, and
 * independent of KYC (§ 9.4). A NULL column denotes an un-screened Customer and is
 * treated, for the downstream purchase gate, as not-`passed` (blocked) — exactly
 * like `pending`; that NULL rule lives at the gate (Module S), not in this enum.
 *
 * `passed` and `failed` are screening completions (each fires a § 15.6 event);
 * `under_review` is a possible-match awaiting manual review and fires no event.
 *
 * - case name    = the state in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum SanctionsStatus: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
    case UnderReview = 'under_review';
}
