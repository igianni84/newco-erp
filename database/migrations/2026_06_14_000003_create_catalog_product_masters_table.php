<?php

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProductType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `catalog_product_masters` — the category-neutral core of the Product Master, the top of the
     * product hierarchy and the parent of every Product Variant (catalog-product-spine, design D1/D4/D6;
     * product-catalog — Requirement: Product Master, Category-Neutral Product Type). This is the NEUTRAL
     * core: it carries only category-neutral identity/structural fields — product name, `product_type`,
     * a producer reference, `lifecycle_state`, audit/version. ALL wine-specific attributes
     * (appellation/region, winery story) live off the core in the 1:1 `catalog_product_master_wine_attributes`
     * table (migration 000004), so a future Product Type slots in additively without reshaping this core
     * (§ 16 generalisation guardrail; ADR decisions/2026-06-14-catalog-category-neutral-representation.md).
     *
     * Two columns carry a backed-enum value set enforced from TWO sources, the same layered idiom as
     * `domain_events.actor_role` (the string + cast on both engines, PLUS a PG-only CHECK):
     *   - `product_type` — the § 3.1 first-class classifier; the {@see ProductType} cast + a CHECK whose
     *     accepted set is `ProductType::cases()` (today: `wine` only — AC-0-XM-9). The creation Action also
     *     rejects a non-`WINE` type fail-closed; the CHECK is the DB-level backstop that can never drift.
     *   - `lifecycle_state` — the § 4.1 four-state domain; {@see LifecycleState} cast + a CHECK from
     *     `LifecycleState::cases()`, defaulted `draft` (every spine entity is born `draft`; this change
     *     writes NO transition — design D3).
     *
     * The producer reference is a plain `unsignedBigInteger('producer_id')` with NO database foreign key
     * and NO Eloquent relation: Module K's tables do not exist yet and a cross-module FK/relation violates
     * the boundary law (CLAUDE.md invariant 10; arch test `ModuleBoundariesTest`). Producer validity (an
     * active, KYC-verified producer) is a deferred lifecycle-gate concern (it consumes `ProducerActivated`);
     * this change stores the id only (design D4).
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md): the SQLite
     * dev/test lane falls back on `timestampsTz()` and skips the PG-only CHECKs, relying on the cast for the
     * value-set floor. The `(producer_id, name)` index supports the § 13.1 BR-Identity-1 dedup query
     * (joined to the wine table on `appellation`) and producer-scoped lookups.
     */
    public function up(): void
    {
        Schema::create('catalog_product_masters', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; PIM ids are not customer-facing (design D4).
            $table->id();
            // the neutral product name (e.g. "Château Margaux"); part of the WINE identity key.
            $table->string('name');
            // the §3.1 first-class category classifier. String + the ProductType cast on BOTH engines, PLUS
            // the PG-only CHECK added after create() below. No default — a Master must be explicitly
            // classified (the Action sets it; only `wine` exists at launch).
            $table->string('product_type');
            // the producer reference: a PLAIN id into Module K — NEVER a DB foreign key, NEVER an Eloquent
            // relation (the boundary law, invariant 10). Producer validity is a deferred lifecycle gate.
            $table->unsignedBigInteger('producer_id');
            // the four-state lifecycle (design D3). String + the LifecycleState cast on BOTH engines, PLUS
            // the PG-only CHECK. Born `draft`; no transition exists this change.
            $table->string('lifecycle_state')->default(LifecycleState::Draft->value);
            // §4.8 / §13.3 BR-Audit-1 version-immutability floor: identity-bearing changes create new
            // versions, never deletes. Born at 1; this spine change writes no edit, so it stays 1.
            $table->unsignedInteger('version')->default(1);
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();

            // Supports the BR-Identity-1 dedup (producer_id + name, then joined to the wine table on
            // appellation) and producer-scoped queries. A short explicit name stays well under PG's 63-char
            // identifier limit.
            $table->index(['producer_id', 'name'], 'catalog_product_masters_producer_name_idx');
        });

        // product_type + lifecycle_state CHECKs — PostgreSQL only (the truth engine). Each accepted set
        // derives from its enum's cases() so the constraint can never drift from the enum. On SQLite this
        // branch is skipped; the enum casts carry the value-set floor (mirrors domain_events.actor_role and
        // catalog_formats.lifecycle_state).
        if (DB::getDriverName() === 'pgsql') {
            $productTypes = implode(', ', array_map(
                static fn (ProductType $type): string => "'{$type->value}'",
                ProductType::cases(),
            ));

            $lifecycleStates = implode(', ', array_map(
                static fn (LifecycleState $state): string => "'{$state->value}'",
                LifecycleState::cases(),
            ));

            DB::statement(
                "ALTER TABLE catalog_product_masters ADD CONSTRAINT catalog_product_masters_product_type_check CHECK (product_type IN ({$productTypes}))"
            );

            DB::statement(
                "ALTER TABLE catalog_product_masters ADD CONSTRAINT catalog_product_masters_lifecycle_state_check CHECK (lifecycle_state IN ({$lifecycleStates}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Drop the wine-attribute table
     * first if it is still present — its FK references this table — though migration order already drops
     * 000004 before 000003. The spine carries no immutability triggers.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_product_masters');
    }
};
