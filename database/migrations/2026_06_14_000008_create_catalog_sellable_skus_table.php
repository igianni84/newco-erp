<?php

use App\Modules\Catalog\Enums\LifecycleState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `catalog_sellable_skus` — the Sellable SKU (Intrinsic), the commercial unit composed of EXACTLY one
     * Product Reference + one Case Configuration + commercial attributes (catalog-product-spine, design D5;
     * product-catalog — Requirement: Sellable SKU (Intrinsic); Module 0 PRD §3.7, §13.5 BR-SKU-1). It is the
     * ONLY SKU shape that references a Case Configuration — the Composite SKU (task 4.2) bundles N Product
     * References and references none.
     *
     * SINGLE-table entity (no per-type attribute set): both dimensions are structural references and the
     * commercial attributes are plain SKU-level fields. §3.7 names them "commercial name, marketing copy",
     * carried AT THE SKU LEVEL — distinct from the identity/descriptive attributes that live on Master /
     * Variant / PR. §8.1 scopes translatable content to those UPSTREAM entities, not the SKU, so
     * `commercial_name`/`marketing_copy` are plain columns (no TranslatableText — modelling exactly what the
     * spec places here, no more).
     *
     * Packaging does NOT change the PR (BR-Identity-3): the same Variant + Format resolves to the ONE Product
     * Reference whether sold loose, in an OWC, or in a carton — those are three DIFFERENT Sellable SKUs (three
     * Case Configurations) over the SAME PR. There is therefore deliberately NO unique constraint on
     * `(product_reference_id, case_configuration_id)`: the spec defines no SKU uniqueness rule, and a PR + Case
     * Configuration pair may back more than one SKU (contrast the PR, whose `(variant, format)` identity IS
     * unique). The two scalar foreign keys enforce exactly-one PR / exactly-one Case Configuration (BR-SKU-1).
     *
     * Both foreign keys are WITHIN the Catalog module (`product_reference_id` → `catalog_product_references`,
     * `case_configuration_id` → `catalog_case_configurations`): the cross-module ban (invariant 10) does not
     * apply. `product_reference_id` CASCADES on delete — the SKU is a commercial composition built on its PR
     * (the spine's atomic key), so the PR owns it and reaps it (the same asymmetry as the PR's own
     * `product_variant_id`). `case_configuration_id` RESTRICTS (the framework default) — a Case Configuration
     * is a standalone SHARED reference entity (like Format), referenced by many SKUs, so it cannot be deleted
     * out from under them. `lifecycle_state` carries the §4.1 four-state value set enforced from the same TWO
     * sources as `domain_events.actor_role` — the {@see LifecycleState} cast on both engines PLUS a PG-only
     * CHECK from `LifecycleState::cases()` — defaulted `draft` (born `draft`; this change writes NO transition —
     * design D3; the §3.7 activation prerequisite that the PR + Case Configuration both be `active` is deferred
     * to catalog-lifecycle-approval). Postgres-truthful, SQLite-compatible (ADR
     * decisions/2026-06-12-production-db-engine.md): SQLite skips the PG-only CHECK and relies on the cast.
     */
    public function up(): void
    {
        Schema::create('catalog_sellable_skus', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; PIM ids are not customer-facing (design D4).
            $table->id();
            // WITHIN-module FK to the Product Reference. The SKU is a commercial composition built on its PR
            // (the atomic product key), so the PR owns it and it cascades on delete — the same ownership
            // asymmetry as the PR's own product_variant_id. Short explicit FK name (PG 63-char limit).
            $table->foreignId('product_reference_id')
                ->constrained(table: 'catalog_product_references', indexName: 'catalog_sellable_skus_reference_fk')
                ->cascadeOnDelete();
            // WITHIN-module FK to the Case Configuration. A Case Configuration is a standalone SHARED reference
            // entity (like Format), referenced by many SKUs, NOT an owner — so it restricts on delete (the
            // framework default): it cannot be deleted out from under its SKUs. Short explicit FK name.
            $table->foreignId('case_configuration_id')
                ->constrained(table: 'catalog_case_configurations', indexName: 'catalog_sellable_skus_case_config_fk');
            // §3.7 commercial attributes carried at the SKU level (not on Master/Variant/PR). Plain columns —
            // §8.1 scopes translatable content to the upstream entities, not the SKU. commercial_name is the
            // sellable name (required); marketing_copy is optional free-form copy.
            $table->string('commercial_name');
            $table->text('marketing_copy')->nullable();
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
                "ALTER TABLE catalog_sellable_skus ADD CONSTRAINT catalog_sellable_skus_lifecycle_state_check CHECK (lifecycle_state IN ({$lifecycleStates}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists).
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_sellable_skus');
    }
};
