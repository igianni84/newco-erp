<?php

namespace App\Modules\Parties\Contracts;

use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\SanctionsStatus;

/**
 * The PII-free compliance-status tuple a downstream surface reads to answer "is this scope clear to transact?"
 * (parties-holds, design L6; party-registry — Requirement: Hold and Sanctions Read-API; DEC-181). Returned by
 * {@see PartyComplianceStatusReader} for a Customer or a Profile scope.
 *
 * It carries exactly the DEC-181 tuple: the scope's `sanctions_status` (the Customer's — nullable; a NULL is an
 * un-screened Customer, treated as not-`passed`) and the cascade-resolved list of ACTIVE Hold types on the
 * scope. The list carries {@see HoldType}s, NEVER the `Hold` Eloquent model — the no-model-leak boundary law
 * (decisions/2026-06-11-modular-monolith-architecture.md): this DTO is the one Module-K surface other modules
 * import, so it exposes enums + a status, no persistence object and no personal data. The types are DISTINCT (a
 * scope may carry multiple concurrent Holds of one type — BR-K-Hold-1 — but "is this scope clear?" is answered
 * by WHICH restrictions apply, not how many rows exist).
 *
 * Module K is Hold-BLIND (DEC-181): this DTO REPORTS the tuple; it does NOT block. The blocking is each
 * downstream transaction-initiation surface's (Module S/C/E), deferred to those modules (proposal slice
 * boundary). {@see isClear()} is the convenience predicate those surfaces call.
 */
class ComplianceStatus
{
    /**
     * @param  list<HoldType>  $activeHoldTypes  the DISTINCT active Hold types on the scope (cascade-resolved); never the Hold model, never PII
     */
    public function __construct(
        public readonly ?SanctionsStatus $sanctionsStatus,
        public readonly array $activeHoldTypes,
    ) {}

    /**
     * Whether the scope is clear to transact: its sanctions screening is `passed` AND it carries no active Hold
     * (party-registry — Requirement: Hold and Sanctions Read-API; design L6). A NULL or non-`passed`
     * `sanctions_status` (un-screened / `pending` / `failed` / `under_review`) is NOT clear, and neither is any
     * scope carrying at least one active Hold.
     */
    public function isClear(): bool
    {
        return $this->sanctionsStatus === SanctionsStatus::Passed && $this->activeHoldTypes === [];
    }
}
