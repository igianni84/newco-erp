<?php

use App\Modules\Catalog\Enums\LifecycleState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `catalog_composite_skus` — the Composite SKU, a curated bundle of N ≥ 2 constituent Product References
     * (catalog-product-spine, design D5/D9; product-catalog — Requirement: Composite SKU; Module 0 PRD §3.8,
     * §13.5 BR-SKU-2). It is the second SKU shape and the spine's only many-to-many entity: the bundle's
     * content lives ENTIRELY in the `catalog_composite_sku_constituents` join table (the next migration), so a
     * single Product Reference may recur across composites and a composite may share constituents with another.
     *
     * The parent row carries NOTHING but registration + lifecycle — §3.8 makes the Composite SKU "cheap at PIM
     * (registration + lifecycle only)": no commercial name, no marketing copy, no club / Hero-Package /
     * promotional flag, no per-constituent allocation binding. Those are downstream concerns (Module S Offer
     * designation, Module A allocation), deliberately absent here. The Composite SKU's whole substance is its
     * ordered constituent set, which is why this table has no business columns of its own.
     *
     * `lifecycle_state` carries the §4.1 four-state value set enforced from the same TWO sources as
     * `domain_events.actor_role` — the {@see LifecycleState} cast on both engines PLUS a PG-only CHECK from
     * `LifecycleState::cases()` — defaulted `draft` (born `draft`; this change writes NO transition — design D3;
     * the §3.8 immutability-after-active-Offer rule (BR-SKU-4) and atomicity-at-sale (BR-SKU-3) are commercial
     * runtime rules deferred to catalog-lifecycle-approval / Module S). Postgres-truthful, SQLite-compatible
     * (ADR decisions/2026-06-12-production-db-engine.md): SQLite skips the PG-only CHECK and relies on the cast.
     */
    public function up(): void
    {
        Schema::create('catalog_composite_skus', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; PIM ids are not customer-facing (design D4).
            $table->id();
            // the four-state lifecycle (design D3). String + the LifecycleState cast on BOTH engines, PLUS the
            // PG-only CHECK below. Born `draft`; no transition exists this change.
            $table->string('lifecycle_state')->default(LifecycleState::Draft->value);
            // §4.8 / §13.3 BR-Audit-1 version-immutability floor: born at 1; this spine change writes no edit.
            $table->unsignedInteger('version')->default(1);
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();
        });

        // lifecycle_state CHECK — PostgreSQL only (the truth engine). The accepted set derives from
        // LifecycleState::cases() so the constraint can never drift from the enum. On SQLite this branch is
        // skipped; the enum cast carries the value-set floor (mirrors domain_events.actor_role).
        if (DB::getDriverName() === 'pgsql') {
            $lifecycleStates = implode(', ', array_map(
                static fn (LifecycleState $state): string => "'{$state->value}'",
                LifecycleState::cases(),
            ));

            DB::statement(
                "ALTER TABLE catalog_composite_skus ADD CONSTRAINT catalog_composite_skus_lifecycle_state_check CHECK (lifecycle_state IN ({$lifecycleStates}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists).
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_composite_skus');
    }
};
