<?php

namespace App\Modules\Parties\Enums;

/**
 * The Account lifecycle domain (design D2; party-registry — Requirement: Account —
 * Billing Container / Birth States Recorded, Lifecycle Transitions Deferred).
 *
 * The spec's verbatim Account state domain `active → suspended → closed` (Module K
 * PRD § 4.7). An Account is co-provisioned with its Customer and born `Active`;
 * this change stores the state but writes NO transition (suspension/closure arrive
 * with the deferred `parties-membership-lifecycle` change). The full domain is
 * defined now so that change can drive it without a migration.
 *
 * - case name    = the state in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum AccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
