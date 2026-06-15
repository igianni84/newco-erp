<?php

use App\Modules\Parties\Enums\ProducerAgreementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `parties_producer_agreements` — the NewCo↔Producer commercial agreement (parties-core, design D2/D3/D4/D7;
     * party-registry — Requirement: ProducerAgreement, Birth States Recorded). A NewCo net-new entity (DEC-070,
     * § 4.6): it references EXACTLY ONE Producer (required) and MAY be narrowed to one of that Producer's Clubs
     * (optional). Both references are WITHIN-module foreign keys (`parties_producers` / `parties_clubs`), so the
     * cross-module ban (invariant 10) does not apply:
     *   - `producer_id` — required (non-nullable FK), the agreement's Producer.
     *   - `club_id` — NULLABLE FK: a null narrows nothing (a Producer-wide agreement); a value scopes the
     *     agreement to that single Club. RESTRICT on delete for both (the framework default) — Producer and Club
     *     are shared parents the agreement references, not rows it owns.
     *
     * FK/index names are given SHORT and EXPLICIT (`parties_pa_*`): `parties_producer_agreements` is the longest
     * table name in this slice, and an auto-generated composite/FK identifier risks PostgreSQL's 63-char limit
     * (design D3, the catalog trap). One value-set column carries the `domain_events.actor_role` layered idiom
     * (string + the enum cast on both engines, PLUS a PG-only CHECK from `Enum::cases()`):
     *   - `status` — the § 4.6.1 lifecycle ({@see ProducerAgreementStatus}), defaulted `draft` (every agreement
     *     is born `draft`; this change writes NO transition — design D2). The "at most one ACTIVE agreement per
     *     Producer scope" rule (BR-K-Agreement-1) is an activation-time invariant, NOT enforced here: draft
     *     agreements are created freely.
     *
     * `term_start` / `term_end` are the (nullable) agreement term dates; `settlement_cadence` is the nullable
     * D19 settlement-cadence seam Module E reads. On SQLite the CHECK is skipped; the cast + NOT-NULL default
     * carry the value-set floor. Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md).
     */
    public function up(): void
    {
        Schema::create('parties_producer_agreements', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; party ids are not customer-facing (design D3).
            $table->id();
            // WITHIN-module FK to the REQUIRED Producer (§ 4.6: exactly one). RESTRICT on delete (framework
            // default) — the Producer is a shared parent, not owned by the agreement. Short explicit FK name
            // (well under PG's 63-char identifier limit, which the long table name pressures).
            $table->foreignId('producer_id')
                ->constrained(table: 'parties_producers', indexName: 'parties_pa_producer_fk');
            // WITHIN-module FK to the OPTIONAL narrowing Club (§ 4.6: Club narrowing optional). Nullable — a
            // null is a Producer-wide agreement; a value scopes it to one Club. RESTRICT on delete; short name.
            $table->foreignId('club_id')
                ->nullable()
                ->constrained(table: 'parties_clubs', indexName: 'parties_pa_club_fk');
            // the § 4.6.1 lifecycle (design D2). String + the ProducerAgreementStatus cast on BOTH engines, PLUS
            // the PG-only CHECK added after create() below. Born `draft`; no transition exists this change, and
            // the single-active-per-scope rule (BR-K-Agreement-1) is an activation-time invariant, not enforced.
            $table->string('status')->default(ProducerAgreementStatus::Draft->value);
            // the agreement term window (§ 4.6). Nullable plain dates — DATE columns on both engines.
            $table->date('term_start')->nullable();
            $table->date('term_end')->nullable();
            // the D19 settlement-cadence seam Module E reads (§ 4.6). A free nullable string at launch — no
            // enumerated domain this slice (no CHECK), set explicitly when known.
            $table->string('settlement_cadence')->nullable();
            // version-immutability floor: identity-bearing changes create new versions, never deletes. Born at
            // 1; this spine change writes no edit, so it stays 1.
            $table->unsignedInteger('version')->default(1);
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();
        });

        // status CHECK — PostgreSQL only (the truth engine). The accepted set derives from
        // ProducerAgreementStatus::cases() so the constraint can never drift from the enum. On SQLite this
        // branch is skipped; the enum cast + the NOT-NULL default carry the value-set floor (mirrors
        // domain_events.actor_role and parties_clubs.status).
        if (DB::getDriverName() === 'pgsql') {
            $statuses = implode(', ', array_map(
                static fn (ProducerAgreementStatus $status): string => "'{$status->value}'",
                ProducerAgreementStatus::cases(),
            ));

            DB::statement(
                "ALTER TABLE parties_producer_agreements ADD CONSTRAINT parties_producer_agreements_status_check CHECK (status IN ({$statuses}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). The spine carries no immutability
     * triggers.
     */
    public function down(): void
    {
        Schema::dropIfExists('parties_producer_agreements');
    }
};
