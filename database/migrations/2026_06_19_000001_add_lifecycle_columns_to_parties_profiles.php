<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the two demand-side lifecycle columns to `parties_profiles` (parties-membership-suspension task 1.1;
     * design L1; party-registry — Requirements: Profile Lapse and Grace Renewal, Profile Cancellation and
     * Deactivation). They are the row-level state the deferred Profile transitions need:
     *   - `lapsed_at` — the grace-window anchor (§ 4.2.1 *"the Profile carries a `lapsed_at` timestamp"*):
     *     `LapseProfile` stamps it on `Active → Lapsed`; `RenewProfile` reads it to enforce the 30-day grace
     *     (DEC-034) and clears it on `Lapsed → Active`.
     *   - `cancellation_reason` — the optional Producer-initiated reason `CancelProfile` records on
     *     `Active | Lapsed → Cancelled` (§ 4.2.1 / § 10.2). Cancellation is audit-only (design L2 — § 15.2 names
     *     no `ProfileCancelled`), so the column carries the domain data a future Module-S offboarding consumer
     *     reads (AC-K-EVT-14, deferred); the state write + audit record are the moment.
     *
     * Both columns are **additive nullable with no default** (DEC-071 pattern; mirrors `2026_06_18_000002`): born
     * `NULL`, no backfill — every existing Profile keeps its behaviour and the transition Actions (tasks 2.x) are
     * the sole writers. Like the onboarding-acceptance migration these are **plain columns with no value-set
     * CHECK**: a `timestamptz`'s domain is the type itself, and `cancellation_reason` is a free-text reason, not
     * an enum (the § 15.7 signal shape is a deferred consumer concern — nothing to pin). The `immutable_datetime`
     * cast on `Profile` carries `lapsed_at`'s typed-read floor on both engines.
     *
     * The partial-unique index `parties_profiles_customer_club_nonterminal_unique` is **NOT touched**: it already
     * excludes `{rejected, cancelled, inactive}` (created in `2026_06_15_000007`), so making `Cancelled` reachable
     * merely *exercises* the predicate — no index migration is needed (a regression test in tasks 2.x proves a
     * `Cancelled` Profile does not block a fresh `Applied` one).
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md): `timestampTz()`
     * falls back to a datetime text column on SQLite; no PG-only feature is used.
     */
    public function up(): void
    {
        Schema::table('parties_profiles', function (Blueprint $table) {
            // the grace-window anchor (§ 4.2.1) — stamped by LapseProfile on `Active → Lapsed`, read by
            // RenewProfile for the 30-day grace (DEC-034), cleared on `Lapsed → Active`. Plain timestamptz; the
            // immutable_datetime cast carries the typed-read floor.
            $table->timestampTz('lapsed_at')->nullable();
            // the optional Producer-initiated cancellation reason (§ 4.2.1 / § 10.2) — recorded by CancelProfile.
            // Cancellation is audit-only (design L2); this free-text column is the domain data a deferred Module-S
            // offboarding consumer reads (AC-K-EVT-14). No enum to pin → no value-set CHECK.
            $table->string('cancellation_reason')->nullable();
        });
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Drops both columns; re-migrating is
     * clean (no CHECK/constraint to drop first — these are plain columns; the partial-unique index was never
     * touched).
     */
    public function down(): void
    {
        Schema::table('parties_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'lapsed_at',
                'cancellation_reason',
            ]);
        });
    }
};
