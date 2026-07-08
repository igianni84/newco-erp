<?php

namespace App\Modules\Parties\Enums;

/**
 * The closed settlement-cadence domain of a ProducerAgreement (party-registry — Requirement:
 * ProducerAgreement; the D19 settlement-cadence seam Module E reads). Canon MVP-DEC-010 (RM-22)
 * closes DEC-042's previously open "e.g." cadence set to EXACTLY three values — `quarterly` (the
 * default), `monthly`, `semi_annual` — server-enforced at the API + DB layer, not a UI hint, because
 * the cadence times Module-E settlement AND Module-D PO issuance (ADR
 * 2026-07-07-adopt-mvp-dec-010-settlement-cadence-closed-set). `annual` and any sub-monthly cadence
 * are OUT of the set. The literal token representation is the dev's call (DEC-073); this backed enum
 * plus the PostgreSQL CHECK derived from cases()
 * (migration 2026_07_07_000001_add_settlement_cadence_check_to_parties_producer_agreements) are that
 * representation — the CHECK regenerates from cases() so it can never drift from the enum, and on
 * SQLite (no DB CHECK) the `ProducerAgreement` cast is the value-set floor.
 *
 * - case name    = the cadence in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum SettlementCadence: string
{
    case Quarterly = 'quarterly';
    case Monthly = 'monthly';
    case SemiAnnual = 'semi_annual';

    /**
     * The default cadence of the closed set (MVP-DEC-010: "quarterly (default)") — the operand a create
     * surface preselects when the operator makes no explicit choice (the console Select default, task 6.1).
     * Kept on the enum so the default has ONE canonical home, never a magic string re-declared per surface.
     */
    public static function default(): self
    {
        return self::Quarterly;
    }
}
