<?php

use App\Modules\Catalog\Enums\LifecycleState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `catalog_product_references` — the Product Reference (PR), the atomic product identity and the universal
     * product key across modules (catalog-product-spine, design D5; product-catalog — Requirement: Product
     * Reference — the atomic product key). A PR is composed of EXACTLY TWO dimensions: a Product Variant and a
     * Format. It is a SINGLE-table entity — unlike the Master/Variant it has no per-type attribute set; both of
     * its dimensions are structural references, not descriptive prose.
     *
     * The two-dimension identity is enforced STRUCTURALLY: the `(product_variant_id, format_id)` pair carries a
     * DB UNIQUE index (BR-Identity-3 / AC-0-BR-Identity-3) — the same Variant + Format always resolves to the
     * SAME PR, and a second identical pair is rejected by the database, never silently duplicated. Changing the
     * composition is therefore a new PR, not an in-place edit (the identity-is-the-tuple rule; immutability
     * once referenced downstream — BR-Identity-4 — is deferred to catalog-lifecycle-approval, which lands with
     * the first Allocation/voucher/stock/Offer referencers).
     *
     * A Case Configuration is NEVER part of PR identity (BR-Identity-3): there is deliberately NO
     * `case_configuration_id` column here. Packaging (loose / OWC / carton) is a Sellable-SKU dimension (task
     * 4.1), so the same Variant + Format sells as several SKUs but resolves to the ONE PR. The absence of that
     * column IS the contract (asserted in the feature test, schema-absence guard).
     *
     * Both foreign keys are WITHIN the Catalog module (`product_variant_id` → `catalog_product_variants`,
     * `format_id` → `catalog_formats`): the parents are the same module's entities, so the cross-module ban
     * (invariant 10) does not apply. `lifecycle_state` carries the §4.1 four-state value set enforced from the
     * same TWO sources as `domain_events.actor_role` — the {@see LifecycleState} cast on both engines PLUS a
     * PG-only CHECK from `LifecycleState::cases()` — defaulted `draft` (born `draft`; this change writes NO
     * transition — design D3). Postgres-truthful, SQLite-compatible (ADR
     * decisions/2026-06-12-production-db-engine.md): SQLite skips the PG-only CHECK and relies on the cast.
     */
    public function up(): void
    {
        Schema::create('catalog_product_references', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; PIM ids are not customer-facing (design D4).
            $table->id();
            // WITHIN-module FK to the parent Variant. The PR sits in the Variant's identity subtree
            // (Master → Variant → PR), so it cascades on delete: a PR is owned by its Variant. Short explicit
            // FK name (the framework auto-name approaches PG's 63-char identifier limit).
            $table->foreignId('product_variant_id')
                ->constrained(table: 'catalog_product_variants', indexName: 'catalog_product_references_variant_fk')
                ->cascadeOnDelete();
            // WITHIN-module FK to the Format. A Format is a SHARED reference dimension (no parent in the
            // hierarchy, referenced by many PRs), NOT an owner — so it restricts on delete (the framework
            // default): a Format in use cannot be deleted out from under its PRs. Short explicit FK name.
            $table->foreignId('format_id')
                ->constrained(table: 'catalog_formats', indexName: 'catalog_product_references_format_fk');
            // the four-state lifecycle (design D3). String + the LifecycleState cast on BOTH engines, PLUS the
            // PG-only CHECK below. Born `draft`; no transition exists this change.
            $table->string('lifecycle_state')->default(LifecycleState::Draft->value);
            // §4.8 / §13.3 BR-Audit-1 version-immutability floor: born at 1; this spine change writes no edit.
            $table->unsignedInteger('version')->default(1);
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();

            // BR-Identity-3: the (Variant, Format) pair IS the PR's identity — unique on both engines. A second
            // identical pair is rejected by the DB (no application-layer dedup is needed — distinct from the
            // Master's cross-table identity key, which spans two tables and must be checked in the action).
            // Short explicit index name (the auto-name would be ~62 chars — perilously close to PG's limit).
            $table->unique(['product_variant_id', 'format_id'], 'catalog_product_references_variant_format_unique');
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
                "ALTER TABLE catalog_product_references ADD CONSTRAINT catalog_product_references_lifecycle_state_check CHECK (lifecycle_state IN ({$lifecycleStates}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists).
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_product_references');
    }
};
