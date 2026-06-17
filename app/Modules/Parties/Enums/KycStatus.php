<?php

namespace App\Modules\Parties\Enums;

/**
 * The KYC compliance lifecycle domain (design L1/L3/L4; party-registry —
 * Requirement: Customer KYC Lifecycle, Producer KYC Lifecycle).
 *
 * The spec's verbatim four-state KYC domain `not_required → pending → verified |
 * rejected` (Module K PRD § 9.1 Customer-side, § 4.4 Producer-side — one shared
 * domain at both levels). `not_required` is the default; setting a Customer's
 * `kyc_required` flag transitions `not_required → pending`. The lifecycle is held in
 * an additive nullable `kyc_status` column (DEC-071) on both `parties_customers` and
 * `parties_producers`; a NULL column denotes an entity never touched by KYC.
 *
 * Cleared (non-blocking) ≡ `verified` ∨ `not_required` — at every gate the two are
 * equivalent (§ 4.4: "`not_required` and `verified` are equivalent at every gate").
 * `pending` and `rejected` block. The Producer activation gate additionally treats a
 * NULL `kyc_status` as cleared for additivity (ADR 2026-06-17); that NULL rule lives
 * at the gate, not in this enum, since NULL is the absence of a case here.
 *
 * - case name    = the state in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum KycStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    /**
     * Whether this KYC state clears (does not block) the gates that require KYC —
     * the Producer activation gate (§ 4.4 / BR-K-Producer-2) and the deferred
     * Customer purchase gate. Cleared ≡ `verified` ∨ `not_required`; `pending` and
     * `rejected` block. A NULL `kyc_status` is the absence of a state, not a case
     * here — the Producer gate treats NULL as cleared separately (ADR 2026-06-17).
     */
    public function clears(): bool
    {
        return $this === self::Verified || $this === self::NotRequired;
    }
}
