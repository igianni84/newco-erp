<?php

namespace App\Modules\Parties\Enums;

/**
 * The unified Hold-type domain (design L1/L2; party-registry — Requirement: Hold
 * Registry, Hold Lifecycle and Lift Discipline).
 *
 * The eight-value Hold-type domain (canon DEC-008; ADR
 * 2026-07-01-adopt-dec-008-hold-types-8): the six § 4.8 types `admin | kyc | payment
 * | fraud | compliance | credit` PLUS the two finance-driven types §4.8.1/§15.8 name
 * and Module K consumes from Module E — `chargeback_review` (placed on
 * `CustomerChargebackFlagged`) and `storage_payment_failed` (the per-cycle INV3 Hold).
 * Module K's Hold registry is trigger-agnostic (AC-K-MVP-2): it records the type + state
 * regardless of what triggered the placement. One type per restriction reason; a scope
 * may carry multiple concurrent `active` Holds of different types (BR-K-Hold-1).
 *
 * The per-type lift discipline (DEC-160 § 4.8.1; AC-K-FSM-11; ADRs
 * 2026-06-18-hold-lift-discipline-per-type + 2026-07-01-adopt-dec-008-hold-types-8)
 * lives on the type as the autoLiftable() predicate (the first method, mirroring
 * KycStatus::clears()): `kyc` and `payment` are system-managed and auto-lift on their
 * clearing signal; `admin`, `fraud`, `compliance`, `credit` AND the two DEC-008
 * finance-driven types require an explicit operator lift and are never auto-lifted.
 * Only the `kyc` auto-lift trigger exists at launch (via RecordKycVerified); the
 * `payment` auto-lift, the non-`kyc` placement triggers, and the two DEC-008 consumers
 * (`CustomerChargebackFlagged`, `StoragePaymentFailed`) are deferred Module-E/S seams —
 * the classification + guard ship now, unwired until Module E. `storage_payment_failed`
 * is operator-lift-only *at launch* (manual-first, D4 — the operator places AND lifts
 * it; the `StoragePaymentSucceeded` auto-lift is the deferred Module-E path).
 *
 * - case name    = the type in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum HoldType: string
{
    case Admin = 'admin';
    case Kyc = 'kyc';
    case Payment = 'payment';
    case Fraud = 'fraud';
    case Compliance = 'compliance';
    case Credit = 'credit';
    // The two DEC-008 finance-driven types, consumed from Module E (§15.8) — appended last.
    case ChargebackReview = 'chargeback_review';
    case StoragePaymentFailed = 'storage_payment_failed';

    /**
     * Whether this Hold type is system-managed and auto-lifts on its clearing signal
     * (true for `kyc` and `payment` only — DEC-160; AC-K-FSM-11). The operator
     * LiftHold path rejects an auto-managed type with IllegalHoldLift; `admin`,
     * `fraud`, `compliance`, `credit` and the two DEC-008 finance-driven types
     * (`chargeback_review`, `storage_payment_failed`) fall through to false — they
     * require an explicit operator lift (ADR 2026-07-01-adopt-dec-008-hold-types-8).
     */
    public function autoLiftable(): bool
    {
        return $this === self::Kyc || $this === self::Payment;
    }
}
