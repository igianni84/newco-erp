<?php

use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the compliance-screening state to `parties_customers` and `parties_producers` (parties-compliance
     * task 1.2; design L1; party-registry — Requirements: Customer KYC Lifecycle, Customer Sanctions Screening
     * Lifecycle, Producer KYC Lifecycle). Every column is **additive nullable with no default** (DEC-071 — both
     * entities are creatable un-screened; this migration backfills nothing), so existing rows keep their
     * behaviour and the new compliance Actions (tasks 2.x/3.x/4.x) are the sole writers.
     *
     * The two NULL meanings are **deliberately asymmetric** (design L1), each asserted on PostgreSQL 17:
     *   - Producer `kyc_status = NULL` ⇒ CLEARED at the activation gate (a never-screened Producer still
     *     activates — preserves the previously-shipped ungated behaviour additively, design L5).
     *   - Customer `sanctions_status = NULL` ⇒ NOT-`passed`/blocked for the downstream purchase gate
     *     (un-screened ≠ cleared); the gate itself is Module S's, not this slice's.
     *
     * The three Customer value-set columns (`kyc_status`, `sanctions_status`, `screening_trigger_source`) and
     * the Producer `kyc_status` carry the layered enforcement idiom of the create-table migrations
     * (`2026_06_15_000005_create_parties_customers_table.php`): a `string` column + the backed-enum cast on both
     * engines, PLUS a PostgreSQL-only CHECK whose accepted set derives from `Enum::cases()` so it can never drift
     * — here widened to `col IS NULL OR col IN (...)` because the columns are nullable. On SQLite the CHECKs are
     * skipped; the casts carry the value-set floor. The boolean/timestamptz columns need no value-set guard.
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md); no PG-only
     * features beyond the optional CHECK.
     */
    public function up(): void
    {
        Schema::table('parties_customers', function (Blueprint $table) {
            // Customer KYC lifecycle (§ 9.1 / § 4.4) — `not_required → pending → verified | rejected`, separate
            // from the Customer status FSM. String + the KycStatus cast on both engines; NULL = never touched.
            $table->string('kyc_status')->nullable();
            // the administratively-set KYC trigger flag (§ 4.1): setting it transitions not_required → pending.
            $table->boolean('kyc_required')->nullable();
            // enhanced-KYC trigger fields (§ 4.1 / DEC-035) — the flag + the moment the €10k-single / €50k-cumulative
            // threshold crossed. The DETECTION that sets them reads cumulative-spend data absent at launch and is
            // DEFERRED (design L7); only the fields ship — no operation in this slice auto-sets them from totals.
            $table->boolean('enhanced_kyc_flag')->nullable();
            $table->timestampTz('enhanced_kyc_at')->nullable();
            // Customer sanctions-screening lifecycle (§ 9.2) — `pending → passed | failed | under_review`,
            // independent of KYC (§ 9.4). String + the SanctionsStatus cast; NULL = un-screened (blocked downstream).
            $table->string('sanctions_status')->nullable();
            // the last screening moment and the 12-month-forward re-screen moment (§ 4.1 / § 9.2). next_rescreen_at
            // is stamped by the screening Action; the daily cadence job that READS it is deferred (design L4/L6).
            $table->timestampTz('last_screening_at')->nullable();
            $table->timestampTz('next_rescreen_at')->nullable();
            // why a screening ran (§ 9.2 / DEC-030) — the onboarding-vs-rescreen event-family selector (task 4.2).
            // String + the ScreeningTriggerSource cast on both engines.
            $table->string('screening_trigger_source')->nullable();
        });

        Schema::table('parties_producers', function (Blueprint $table) {
            // provenance-KYC lifecycle, distinct from Customer KYC (§ 4.4). Additive nullable: a NULL kyc_status is
            // a Producer never touched by KYC and is treated as CLEARED at the activation gate (design L1/L5).
            // String + the KycStatus cast on both engines.
            $table->string('kyc_status')->nullable();
        });

        // value-set CHECKs — PostgreSQL only (the truth engine), widened to admit NULL (the columns are
        // additive-nullable). Each accepted set derives from the enum's cases() so the constraint can never drift
        // from the enum. On SQLite these branches are skipped; the enum casts carry the value-set floor (mirrors
        // domain_events.actor_role and the create-table migrations).
        if (DB::getDriverName() === 'pgsql') {
            $this->addNullableValueSetCheck('parties_customers', 'kyc_status', KycStatus::cases());
            $this->addNullableValueSetCheck('parties_customers', 'sanctions_status', SanctionsStatus::cases());
            $this->addNullableValueSetCheck('parties_customers', 'screening_trigger_source', ScreeningTriggerSource::cases());
            $this->addNullableValueSetCheck('parties_producers', 'kyc_status', KycStatus::cases());
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Drops the PG-only CHECKs first
     * (idempotent — they never existed on SQLite), then the columns.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE parties_customers DROP CONSTRAINT IF EXISTS parties_customers_kyc_status_check');
            DB::statement('ALTER TABLE parties_customers DROP CONSTRAINT IF EXISTS parties_customers_sanctions_status_check');
            DB::statement('ALTER TABLE parties_customers DROP CONSTRAINT IF EXISTS parties_customers_screening_trigger_source_check');
            DB::statement('ALTER TABLE parties_producers DROP CONSTRAINT IF EXISTS parties_producers_kyc_status_check');
        }

        Schema::table('parties_customers', function (Blueprint $table) {
            $table->dropColumn([
                'kyc_status',
                'kyc_required',
                'enhanced_kyc_flag',
                'enhanced_kyc_at',
                'sanctions_status',
                'last_screening_at',
                'next_rescreen_at',
                'screening_trigger_source',
            ]);
        });

        Schema::table('parties_producers', function (Blueprint $table) {
            $table->dropColumn('kyc_status');
        });
    }

    /**
     * Add a PostgreSQL value-set CHECK for a NULLABLE column: the accepted set derives from the enum's cases()
     * (so the constraint can never drift from the enum), widened with `col IS NULL OR …` because the column is
     * additive-nullable (DEC-071 — NULL is always admitted). The auto-named constraint
     * `<table>_<column>_check` stays well under PostgreSQL's 63-char identifier limit.
     *
     * @param  list<BackedEnum>  $cases
     */
    private function addNullableValueSetCheck(string $table, string $column, array $cases): void
    {
        $accepted = implode(', ', array_map(
            static fn (BackedEnum $case): string => "'{$case->value}'",
            $cases,
        ));

        DB::statement(
            "ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column} IS NULL OR {$column} IN ({$accepted}))"
        );
    }
};
