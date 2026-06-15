<?php

use App\Modules\Catalog\Enums\LifecycleState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `catalog_product_variants` — the category-neutral core of a Product Variant, a release of a Product
     * Master and the parent of every Product Reference (catalog-product-spine, design D1/D5; product-catalog —
     * Requirement: Product Variant). This is the NEUTRAL core: it carries only category-neutral
     * identity/structural fields — the single-parent reference to its Master, a TYPE-NEUTRAL `variant_identifier`
     * (the variant axis on the core; the axis VALUE and meaning live in the per-type attribute set),
     * `lifecycle_state`, audit/version. ALL wine-specific attributes (the vintage year / non-vintage marker,
     * tasting notes) live off the core in the 1:1 `catalog_product_variant_wine_attributes` table (migration
     * 000006), so the core never hard-names a wine-only "vintage" dimension (§16 generalisation guardrail;
     * AC-0-GEN-3; ADR decisions/2026-06-14-catalog-category-neutral-representation.md).
     *
     * `lifecycle_state` carries a backed-enum value set enforced from TWO sources, the same layered idiom as
     * `domain_events.actor_role` (the string + cast on both engines, PLUS a PG-only CHECK): the §4.1 four-state
     * domain ({@see LifecycleState} cast + a CHECK from `LifecycleState::cases()`), defaulted `draft` (every
     * spine entity is born `draft`; this change writes NO transition — design D3). The Variant core carries no
     * `product_type` column — the Product Type is fixed by the parent Master.
     *
     * The Master reference is a WITHIN-module foreign key (`product_master_id` → `catalog_product_masters`):
     * the parent is in the SAME module, so the cross-module ban (invariant 10) does not apply — and the single
     * FK STRUCTURALLY enforces BR-Identity-2 (a Variant belongs to exactly one Master; it cannot reference two).
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md): SQLite falls back
     * on `timestampsTz()` and skips the PG-only CHECK, relying on the cast for the value-set floor.
     */
    public function up(): void
    {
        Schema::create('catalog_product_variants', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; PIM ids are not customer-facing (design D4).
            $table->id();
            // WITHIN-module single-parent FK to the Master core (BR-Identity-2: exactly one parent). The parent
            // is in the same module — the cross-module ban is about OTHER modules' tables. Cascade on delete: a
            // Variant is owned by its Master. Short explicit FK name (well under PG's 63-char identifier limit).
            $table->foreignId('product_master_id')
                ->constrained(table: 'catalog_product_masters', indexName: 'catalog_product_variants_master_fk')
                ->cascadeOnDelete();
            // the TYPE-NEUTRAL variant axis on the core (e.g. a release label). The axis VALUE and meaning for
            // WINE (the vintage year / non-vintage marker) live in the per-type attribute set (000006), so the
            // core never hard-names "vintage" (AC-0-GEN-3 / the type-neutral-axis scenario).
            $table->string('variant_identifier');
            // the four-state lifecycle (design D3). String + the LifecycleState cast on BOTH engines, PLUS the
            // PG-only CHECK. Born `draft`; no transition exists this change.
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
                "ALTER TABLE catalog_product_variants ADD CONSTRAINT catalog_product_variants_lifecycle_state_check CHECK (lifecycle_state IN ({$lifecycleStates}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). The wine-attribute table's FK
     * references this table, but migration order already drops 000006 before this one.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_product_variants');
    }
};
