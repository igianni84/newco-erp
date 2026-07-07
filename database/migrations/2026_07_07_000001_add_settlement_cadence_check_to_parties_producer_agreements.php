<?php

use App\Modules\Parties\Enums\SettlementCadence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Closes `parties_producer_agreements.settlement_cadence` to the three-value {@see SettlementCadence} domain
     * (parties-module-k-br-guards task 2.1; RM-22 / MVP-DEC-010; ADR
     * 2026-07-07-adopt-mvp-dec-010-settlement-cadence-closed-set). The create-table migration
     * (2026_06_15_000004) left the column a free nullable string — no enumerated domain at the spine slice.
     * Canon MVP-DEC-010 later closed DEC-042's open "e.g." set to {quarterly (default), monthly, semi_annual},
     * server-enforced at API + DB (the cadence times Module-E settlement + Module-D PO issuance). This additive
     * migration adds the value-set CHECK; the model gains the SettlementCadence cast in the same task (the
     * SQLite floor, where the CHECK is skipped).
     *
     * The column stays NULLABLE (the D19 seam is set only when known), so the CHECK takes the additive-nullable
     * form `col IS NULL OR col IN (...)` (mirrors 2026_06_17_000001_add_compliance_to_parties), and the accepted
     * set derives from SettlementCadence::cases() so it can never drift from the enum. PostgreSQL only (the
     * truth engine); on SQLite the branch is skipped and the cast carries the floor. Postgres-truthful,
     * SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md); no PG extension.
     */
    public function up(): void
    {
        // value-set CHECK — PostgreSQL only. The accepted set derives from SettlementCadence::cases() (so it can
        // never drift from the enum) and is widened with `IS NULL OR` because the D19 seam column is nullable. On
        // SQLite this branch is skipped; the enum cast is the value-set floor. Auto-named constraint
        // `parties_producer_agreements_settlement_cadence_check` (52 chars, under PG's 63-char identifier limit).
        if (DB::getDriverName() === 'pgsql') {
            $accepted = implode(', ', array_map(
                static fn (SettlementCadence $case): string => "'{$case->value}'",
                SettlementCadence::cases(),
            ));

            DB::statement(
                "ALTER TABLE parties_producer_agreements ADD CONSTRAINT parties_producer_agreements_settlement_cadence_check CHECK (settlement_cadence IS NULL OR settlement_cadence IN ({$accepted}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Drops the PG-only CHECK (idempotent —
     * it never existed on SQLite), leaving the column a free string again.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE parties_producer_agreements DROP CONSTRAINT IF EXISTS parties_producer_agreements_settlement_cadence_check');
        }
    }
};
