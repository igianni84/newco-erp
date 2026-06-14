<?php

use App\Modules\Catalog\Enums\LifecycleState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `catalog_case_configurations` — the Case Configuration reference entity (catalog-product-spine,
     * design D5; product-catalog — Requirement: Case Configuration). A standalone PIM reference entity
     * with no parent in the hierarchy, distinct from Format: it carries packaging-form attributes only —
     * units per case and packaging type (Module 0 PRD § 3.6). A Sellable SKU (Intrinsic) references
     * exactly one Case Configuration (task 4.1); it is the only SKU shape that does (BR-SKU-1).
     *
     * § 7-stays-downstream guard (BR-RefData-2 / AC-0-BR-RefData-2): this table carries **no breakability
     * column**. Whether a case may be split at sale is the layered breakability rule decided downstream in
     * Module A (Layer 2) / Module S (Layer 3) — never a property of the Case Configuration. The absence is
     * the contract; a feature test asserts `Schema::hasColumn(..., 'breakable'|'breakability')` is false.
     *
     * Follows the spine table convention set by `catalog_formats` (migration 000001): a bigint id; the
     * entity's own attributes; a `lifecycle_state` string column carrying the four-state domain (string +
     * the {@see LifecycleState} cast on both engines + a DB CHECK on PostgreSQL only), defaulted `draft` —
     * every spine entity is born `draft` and this change writes NO transition (design D3); a `version`
     * integer for the § 4.8 / § 13.3 BR-Audit-1 version-immutability floor (born at 1); and the
     * created_at/updated_at audit timestamps.
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md): the SQLite
     * dev/test lane falls back on `timestampsTz()` and skips the PG-only CHECK, relying on the NOT NULL
     * default + the enum cast for the value-set floor — the same layered pattern as
     * `domain_events.actor_role` (migration 000001).
     */
    public function up(): void
    {
        Schema::create('catalog_case_configurations', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; PIM ids are not customer-facing (design D4).
            $table->id();
            // the Case Configuration's name (e.g. "Original Wooden Case (6)"); kept in the §18 generalisation.
            $table->string('name');
            // packaging-form attributes (§ 3.6): how many atomic units a case holds, and the packaging type
            // (e.g. loose / owc / carton). NO breakability column — that decision lives downstream (BR-RefData-2).
            $table->unsignedInteger('units_per_case');
            $table->string('packaging_type');
            // the four-state lifecycle (design D3). Value-set enforcement is layered exactly like
            // domain_events.actor_role: the LifecycleState cast on BOTH engines, PLUS the PG-only CHECK added
            // after create() below. Born `draft`; no transition exists this change.
            $table->string('lifecycle_state')->default(LifecycleState::Draft->value);
            // §4.8 / §13.3 BR-Audit-1 version-immutability floor: identity-bearing changes create new
            // versions, never deletes. Born at 1; this spine change writes no edit, so it stays 1.
            $table->unsignedInteger('version')->default(1);
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();
        });

        // lifecycle_state CHECK — PostgreSQL only (the truth engine). Accepted values derive from
        // LifecycleState::cases() so the constraint can never drift from the enum. On SQLite this branch
        // is skipped; the defaulted NOT NULL column + the enum cast carry the value-set floor (design D3,
        // mirroring the domain_events.actor_role CHECK in migration 000001 and catalog_formats in 000001).
        if (DB::getDriverName() === 'pgsql') {
            $values = implode(', ', array_map(
                static fn (LifecycleState $state): string => "'{$state->value}'",
                LifecycleState::cases(),
            ));

            DB::statement(
                "ALTER TABLE catalog_case_configurations ADD CONSTRAINT catalog_case_configurations_lifecycle_state_check CHECK (lifecycle_state IN ({$values}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Dropping the table is safe —
     * the spine carries no immutability triggers.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_case_configurations');
    }
};
