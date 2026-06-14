<?php

use App\Modules\Catalog\Enums\LifecycleState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `catalog_formats` — the Format reference entity (catalog-product-spine, design D5;
     * product-catalog — Requirement: Format). A standalone PIM reference entity with no parent
     * in the hierarchy, representing the physical size/measure of the atomic unit — for `WINE`,
     * the bottle size (Module 0 PRD § 3.5; § 18 keeps the name "Format" in the generalisation).
     * A Product Reference references exactly one Format (task 3.3).
     *
     * This is the FIRST `catalog_*` table and sets the spine table convention every later entity
     * repeats: a bigint id; the entity's own attributes; a `lifecycle_state` string column carrying
     * the four-state domain (string + the {@see LifecycleState} cast on both engines + a DB CHECK on
     * PostgreSQL only), defaulted `draft` — every spine entity is born `draft` and this change writes
     * NO transition (design D3); a `version` integer for the § 4.8 / § 13.3 BR-Audit-1 version-
     * immutability floor (born at 1, incrementing deferred with the edit/approval surface); and the
     * created_at/updated_at audit timestamps.
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md): the
     * SQLite dev/test lane falls back on `timestampsTz()` (PG `timestamptz` | SQLite datetime `text`,
     * behaviour-preserving under Eloquent's Carbon cast) and skips the PG-only CHECK, relying on the
     * NOT NULL default + the enum cast for the value-set floor — the same layered pattern as
     * `domain_events.actor_role` (migration 000001).
     */
    public function up(): void
    {
        Schema::create('catalog_formats', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; PIM ids are not customer-facing (design D4).
            $table->id();
            // the Format's name (e.g. "Magnum"); kept in the §18 generalisation.
            $table->string('name');
            // the physical measure of the atomic unit. size_label is the display measure (e.g. "1.5L");
            // volume_ml is the canonical numeric measure (integer, sortable/comparable across formats).
            $table->string('size_label');
            $table->unsignedInteger('volume_ml');
            // the four-state lifecycle (design D3). Value-set enforcement is layered exactly like
            // domain_events.actor_role: the LifecycleState cast (task wiring) on BOTH engines, PLUS the
            // PG-only CHECK added after create() below. Born `draft`; no transition exists this change.
            $table->string('lifecycle_state')->default(LifecycleState::Draft->value);
            // §4.8 / §13.3 BR-Audit-1 version-immutability floor: identity-bearing changes create new
            // versions, never deletes. Born at 1; this spine change writes no edit, so it stays 1.
            $table->unsignedInteger('version')->default(1);
            // audit: created_at / updated_at (timestamptz on PG). occurred_at-style app clocks belong to
            // the event store; a normal mutable entity carries the framework audit timestamps.
            $table->timestampsTz();
        });

        // lifecycle_state CHECK — PostgreSQL only (the truth engine). Accepted values derive from
        // LifecycleState::cases() so the constraint can never drift from the enum. On SQLite this branch
        // is skipped; the defaulted NOT NULL column + the enum cast carry the value-set floor (design D3,
        // mirroring the domain_events.actor_role CHECK in migration 000001).
        if (DB::getDriverName() === 'pgsql') {
            $values = implode(', ', array_map(
                static fn (LifecycleState $state): string => "'{$state->value}'",
                LifecycleState::cases(),
            ));

            DB::statement(
                "ALTER TABLE catalog_formats ADD CONSTRAINT catalog_formats_lifecycle_state_check CHECK (lifecycle_state IN ({$values}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Dropping the table is safe —
     * the spine carries no immutability triggers.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_formats');
    }
};
