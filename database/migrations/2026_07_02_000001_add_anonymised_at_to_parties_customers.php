<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds `anonymised_at` to `parties_customers` — the GDPR right-to-erasure timestamp (parties-anonymisation
     * task 1.2; design D4 + Migration Plan; party-registry — Requirement: Customer Anonymisation
     * (Right-to-Erasure)). `AnonymiseCustomer` (task 3.2) stamps it in the same transaction it overwrites the
     * Customer PII + every scoped Address's personal fields. A set `anonymised_at` is the boolean-derivable
     * anonymised state (`anonymised_at IS NOT NULL`), NOT a status value — anonymisation is ORTHOGONAL to the
     * § 4.1 status FSM (AC-K-FSM-16 / BR-K-Customer-2), so this is a flag+timestamp, never a `CustomerStatus`
     * case: a Customer in any status MAY be anonymised and keeps its status.
     *
     * Additive NULLABLE with no default (DEC-071 pattern; mirrors 2026_06_18_000002's onboarding timestamps):
     * born `NULL` (a Customer is not anonymised at birth), no backfill, written later by `AnonymiseCustomer`.
     * A plain `timestamptz` column with NO value-set CHECK — there is no enum to pin (a timestamp's domain is the
     * type itself); the `immutable_datetime` cast the model gains in task 3.2 carries the typed-read floor.
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md): `timestampTz()`
     * falls back to a datetime text column on SQLite; no PG-only feature is used, no PG extension.
     */
    public function up(): void
    {
        Schema::table('parties_customers', function (Blueprint $table) {
            // the GDPR erasure moment (§ 8.2 / AC-K-J-9 / AC-K-FSM-16). Nullable, no default: a Customer is born
            // un-anonymised; AnonymiseCustomer (task 3.2) stamps it. Orthogonal to `status` (a flag, not an FSM
            // case) — a `closed` Customer stays `closed` after anonymisation.
            $table->timestampTz('anonymised_at')->nullable();
        });
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Drops the column; re-migrating is clean
     * (no CHECK/constraint to drop first — a plain timestamp).
     */
    public function down(): void
    {
        Schema::table('parties_customers', function (Blueprint $table) {
            $table->dropColumn('anonymised_at');
        });
    }
};
