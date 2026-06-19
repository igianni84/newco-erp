<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the three onboarding-acceptance timestamps to `parties_customers` (parties-membership-activation
     * task 1.1; design L1; party-registry — Requirement: Customer Onboarding Activation). These are the gate
     * inputs the `ActivateCustomer` composite gate reads — § 4.1: a Customer reaches `active` only once email is
     * verified AND T&C AND privacy are accepted (alongside sanctions = passed and KYC cleared), and acceptance is
     * "tracked at Customer level with timestamps". § 4.1 names T&C and privacy as two distinct documents, so the
     * acceptance is two columns, not a single combined `terms_accepted_at` (DEC-073 realization).
     *
     * Every column is **additive nullable with no default** (DEC-071 pattern; mirrors `2026_06_17_000001`): born
     * `NULL`, no backfill, and written later by the (deferred) consumer registration surface or an operator — the
     * same additive-seam shape as the compliance columns (the column exists now; the flow that fills it lands in a
     * later slice). A NULL acceptance timestamp is an unmet gate, so `ActivateCustomer` simply stays blocked.
     *
     * Unlike the compliance migration these are **plain `timestamptz` columns with no value-set CHECK** — there is
     * no enum to pin (a timestamp's domain is the type itself); the `immutable_datetime` cast on `Customer` carries
     * the typed-read floor on both engines. No change to `parties_profiles` (the `ProfileState` domain and its
     * partial-unique index already exist — the demand-side Profile transitions only exercise them).
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md): `timestampTz()`
     * falls back to a datetime text column on SQLite; no PG-only feature is used.
     */
    public function up(): void
    {
        Schema::table('parties_customers', function (Blueprint $table) {
            // email verification moment (§ 4.1 / AC-K-J-1) — the first of the four hard activation gates.
            $table->timestampTz('email_verified_at')->nullable();
            // Terms & Conditions acceptance moment (§ 4.1 / AC-K-BR-Identity-3) — a distinct document from privacy.
            $table->timestampTz('tc_accepted_at')->nullable();
            // privacy-policy acceptance moment (§ 4.1 / AC-K-BR-Identity-3) — the second acceptance document.
            $table->timestampTz('privacy_accepted_at')->nullable();
        });
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Drops the three columns; re-migrating is
     * clean (no CHECK/constraint to drop first — these are plain timestamps).
     */
    public function down(): void
    {
        Schema::table('parties_customers', function (Blueprint $table) {
            $table->dropColumn([
                'email_verified_at',
                'tc_accepted_at',
                'privacy_accepted_at',
            ]);
        });
    }
};
