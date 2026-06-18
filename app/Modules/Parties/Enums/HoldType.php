<?php

namespace App\Modules\Parties\Enums;

/**
 * The unified Hold-type domain (design L1/L2; party-registry — Requirement: Hold
 * Registry, Hold Lifecycle and Lift Discipline).
 *
 * The spec's verbatim six-value Hold-type domain `admin | kyc | payment | fraud |
 * compliance | credit` (Module K PRD § 4.8 — the unified, trigger-agnostic Hold
 * primitive; AC-K-FSM-10). One type per restriction reason; a scope may carry
 * multiple concurrent `active` Holds of different types (BR-K-Hold-1).
 *
 * The per-type lift discipline (DEC-160 § 4.8.1; AC-K-FSM-11; ADR
 * 2026-06-18-hold-lift-discipline-per-type) lives on the type as the autoLiftable()
 * predicate (the first method, mirroring KycStatus::clears()): `kyc` and `payment`
 * are system-managed and auto-lift on their clearing signal; `admin`, `fraud`,
 * `compliance` and `credit` require an explicit operator lift and are never
 * auto-lifted. Only the `kyc` auto-lift trigger exists at launch (via
 * RecordKycVerified); the `payment` auto-lift and the non-`kyc` placement triggers
 * are deferred Module-E/S seams — the classification + guard ship now.
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

    /**
     * Whether this Hold type is system-managed and auto-lifts on its clearing signal
     * (true for `kyc` and `payment` only — DEC-160; AC-K-FSM-11). The operator
     * LiftHold path rejects an auto-managed type with IllegalHoldLift; `admin`,
     * `fraud`, `compliance` and `credit` require an explicit operator lift.
     */
    public function autoLiftable(): bool
    {
        return $this === self::Kyc || $this === self::Payment;
    }
}
