<?php

namespace App\Modules\Parties\Enums;

/**
 * The Customer lifecycle domain (design D2; party-registry — Requirement: Customer
 * Identity / Birth States Recorded, Lifecycle Transitions Deferred).
 *
 * The spec's verbatim Customer state domain `pending → active → suspended → closed`
 * (Module K PRD § 4.1). Every Customer is born `Pending` and this change stores the
 * state but writes NO transition — there is no activate/suspend/close path and no
 * `CustomerActivated`/`*Suspended` emission (those need the deferred
 * `parties-membership-lifecycle` change). The full domain is defined now so the
 * lifecycle change can drive it without a migration.
 *
 * - case name    = the state in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum CustomerStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
