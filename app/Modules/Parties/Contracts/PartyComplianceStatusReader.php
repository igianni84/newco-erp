<?php

namespace App\Modules\Parties\Contracts;

/**
 * The uniform "is this scope clear to transact?" read contract — Module K's single cross-module compliance
 * surface (parties-holds, design L6; party-registry — Requirement: Hold and Sanctions Read-API; DEC-181;
 * AC-K-XM-12). It answers the question by returning the {@see ComplianceStatus} tuple
 * `(sanctions_status, active-Hold-list)` for a Customer or a Profile scope, cascade-resolved.
 *
 * Cascade (BR-K-Hold-3/4): {@see forProfile()} returns the Profile's OWN active Holds UNION its parent
 * Customer's active Holds (a Customer-scope Hold blocks every Profile of that Customer), plus the parent
 * Customer's `sanctions_status`; a Profile-scope Hold isolates to that Profile (a sibling Profile never sees
 * it). {@see forCustomer()} returns the Customer-scope active Holds and the Customer's `sanctions_status`.
 * Account-scope cascade is unspecified by the PRD and intentionally NOT resolved (design L6 risk note).
 *
 * The contract returns a PII-free DTO, NEVER the `Hold` Eloquent model (the no-model-leak boundary law). Module
 * K is Hold-BLIND (DEC-181): it PROVIDES the tuple; the blocking is each downstream transaction-initiation
 * surface's (Module S order-completion, C, E) and is NOT in this slice — those modules resolve this contract
 * by-position when they land. The bound implementation is `DatabaseComplianceStatusReader` (registered in
 * PartiesServiceProvider).
 */
interface PartyComplianceStatusReader
{
    /**
     * The compliance tuple for a Customer scope: the Customer's `sanctions_status` and its active Customer-scope
     * Hold types.
     */
    public function forCustomer(int $customerId): ComplianceStatus;

    /**
     * The compliance tuple for a Profile scope: the parent Customer's `sanctions_status` and the union of the
     * Profile's own active Holds with the parent Customer's active Holds (the Customer→Profile cascade,
     * BR-K-Hold-3).
     */
    public function forProfile(int $profileId): ComplianceStatus;
}
