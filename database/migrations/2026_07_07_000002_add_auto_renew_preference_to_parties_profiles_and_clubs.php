<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the auto-renewal preference columns for Profile-5 (parties-module-k-br-guards task 2.2; canon
     * MVP-DEC-022 sub-7 / AC-K-BR-Profile-5; ADR 2026-07-07-adopt-mvp-dec-022-club-membership-governance;
     * design D8). Two additive NOT-NULL boolean columns across the two demand-side tables:
     *   - `parties_clubs.auto_renew_default` — the Club-level default a new Profile inherits at creation (the
     *     standalone `auto_renew` element of the otherwise-deferred `renewal_policy` blob, MVP-DEC-013). Born
     *     `true`: a Club auto-renews its memberships unless configured otherwise.
     *   - `parties_profiles.auto_renew` — the per-Profile renewal preference (party-registry — Requirement:
     *     *Profile Auto-Renewal Preference*: "A Profile SHALL carry an `auto_renew` preference"). Task 4.2 makes
     *     `CreateProfile` set it by inheriting the owning Club's `auto_renew_default`; an operator Action is the
     *     sole post-creation writer; the customer self-toggle is a deferred Consumer-Portal seam.
     *
     * Both carry a NOT-NULL DB **default `true`** — REQUIRED for an additive column, unlike the create-table
     * classifiers (`registration_flow_type` / `state`) that carry no default because they are set explicitly at
     * creation: `ALTER TABLE ADD COLUMN` cannot add a NOT NULL column without a default on SQLite, and every
     * existing insert path (`ClubFactory` / `ProfileFactory`, `DemoSeeder`, and `CreateClub` / `CreateProfile`
     * until their task-4.2 wiring) omits the column — so the default is the value-floor that keeps those writes
     * valid. `true` matches the sibling `generates_credit` default and is exactly the value `CreateProfile` will
     * inherit from a default Club, so the DB default remains a harmless floor once task 4.2 layers the explicit
     * inheritance on top: no follow-up migration to drop it (the spec mandates the inheritance, not the absence
     * of a DB default). No value-set CHECK — a boolean's domain is the type itself; the `boolean` cast carries
     * the typed read on both engines.
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md): `boolean()` maps
     * to `boolean` on PostgreSQL / `integer` 0|1 on SQLite; no PG-only feature is used.
     */
    public function up(): void
    {
        // the Club-level auto-renew default a new Profile inherits at creation (task 4.2). Born `true` — matches
        // `generates_credit`; a Club auto-renews its memberships unless configured otherwise.
        Schema::table('parties_clubs', function (Blueprint $table) {
            $table->boolean('auto_renew_default')->default(true);
        });

        // the per-Profile renewal preference. NOT-NULL with a `true` floor: task 4.2 makes CreateProfile inherit
        // the owning Club's `auto_renew_default` explicitly; the default covers the factory / seeder / raw inserts
        // that omit it (an additive NOT-NULL column needs a default on SQLite ADD COLUMN).
        Schema::table('parties_profiles', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(true);
        });
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Drops both columns in reverse; the
     * re-migrate is clean (plain boolean columns — no CHECK / constraint / index to drop first, mirroring
     * 2026_06_19_000001's plain-column down()).
     */
    public function down(): void
    {
        Schema::table('parties_profiles', function (Blueprint $table) {
            $table->dropColumn('auto_renew');
        });

        Schema::table('parties_clubs', function (Blueprint $table) {
            $table->dropColumn('auto_renew_default');
        });
    }
};
