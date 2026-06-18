<?php

namespace App\Modules\Parties\Enums;

/**
 * The Hold scope-type domain (design L1; party-registry — Requirement: Hold
 * Registry).
 *
 * The spec's verbatim three-value scope domain `customer | account | profile`
 * (Module K PRD § 4.8 — a Hold targets a Customer, an Account or a Profile). The
 * scope is modelled polymorphically as a `scope_type` (this enum) + a `scope_id`
 * (the scoped entity's id, a within-module reference, no DB FK — design L1).
 *
 * Scope cascade resolves at read time (§ 4.8.1; BR-K-Hold-3/4): a Customer-scope
 * Hold blocks every Profile of that Customer, while a Profile-scope Hold isolates to
 * that Profile. The cascade lives in the read-API, not in this enum.
 *
 * - case name    = the scope in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum HoldScope: string
{
    case Customer = 'customer';
    case Account = 'account';
    case Profile = 'profile';
}
