<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `catalog_variant_case_whitelists` — the Layer-1 possible-case-configurations whitelist
     * (catalog-module-0-completeness-sweep, design D6; product-catalog — Requirement: Layer-1
     * Case-Configuration Whitelist; Module 0 PRD § 3.3 + § 7.1). One row per admitted
     * (Product Variant, Format, Case Configuration) triple: the cataloging-level statement
     * "this product, IN THIS FORMAT, can in principle be packaged in this form".
     *
     * Keyed per (Variant, Format) PAIR, not per Variant: § 3.3 and § 7.1 both read "this product, in this
     * format" — a 1.5L magnum admits different packaging forms than a 0.75L bottle of the same release. The
     * AC's flat example is one format's view of the same structure.
     *
     * An ABSENT pair (zero rows) is PERMISSIVE — every Case Configuration is admissible for it (§ 7.1's
     * default). The whitelist therefore has no "allow everything" row and no boolean: presence narrows,
     * absence admits. Enforcement is at Sellable SKU activation only (task 3.2) — never retroactive
     * (§ 4.5 retirement-cascade semantics: reductions leave already-`active` SKUs valid).
     *
     * § 7-stays-downstream guard (BR-RefData-2 / AC-0-XM-11), inherited verbatim from
     * `catalog_case_configurations`: this table carries **no breakability column**. Layer 1 catalogs
     * POSSIBILITY only; whether a whitelisted case may be split at sale is the layered breakability rule
     * decided downstream in Module A (Layer 2) / Module S (Layer 3). The absence is the contract — no
     * module can read an `is_breakable` flag from PIM because PIM exposes none. A feature test asserts no
     * column here carries the concept.
     *
     * A pure link table in substance (references + timestamps, no lifecycle_state, no version): the
     * whitelist is neither review-governed identity content nor observational enrichment — maintenance is
     * an audited operator write (`catalog.product_variant.whitelist_updated`) against the parent Variant,
     * recording no domain event and incrementing no `version` (design D6). It carries a surrogate `id`
     * (unlike `catalog_composite_sku_constituents`, whose natural key is its unique pair) because the
     * maintenance Action replaces a pair's set row-by-row and a stable PK keeps that write ordinary.
     *
     * FK ownership asymmetry, mirroring `catalog_product_references`' own (variant, format) pair:
     * `product_variant_id` CASCADES — a whitelist entry is a statement ABOUT its Variant and has no meaning
     * without it. `format_id` and `case_configuration_id` RESTRICT (the framework default) — both are
     * standalone SHARED reference entities referenced across the catalog, so neither can be deleted out
     * from under a whitelist that names it.
     *
     * All index/FK/unique names are given SHORT explicit forms under a stable `catalog_vcw_*` prefix: the
     * framework auto-names on this table would breach PostgreSQL's 63-byte identifier limit — silently
     * (knowledge/data-model/rules.md).
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md): no enum
     * column, hence no PG-only CHECK — the two engines carry identical DDL here.
     */
    public function up(): void
    {
        Schema::create('catalog_variant_case_whitelists', function (Blueprint $table) {
            // surrogate bigint PK — the maintenance Action rewrites a pair's rows, so a stable PK keeps the
            // replace an ordinary delete+insert rather than a composite-key dance.
            $table->id();
            // WITHIN-module FK to the Product Variant that owns the statement. A whitelist entry is a
            // statement ABOUT the Variant and is meaningless without it — so it cascades on delete.
            $table->foreignId('product_variant_id')
                ->constrained(table: 'catalog_product_variants', indexName: 'catalog_vcw_variant_fk')
                ->cascadeOnDelete();
            // WITHIN-module FK to the Format. The whitelist is scoped per (Variant, FORMAT) pair (§ 3.3,
            // § 7.1). A Format is a standalone SHARED reference entity, NOT an owner — it restricts on
            // delete (the framework default), exactly as it does on `catalog_product_references`.
            $table->foreignId('format_id')
                ->constrained(table: 'catalog_formats', indexName: 'catalog_vcw_format_fk');
            // WITHIN-module FK to the admitted Case Configuration — likewise a standalone SHARED reference
            // entity (restricts on delete, as on `catalog_sellable_skus`). NO breakability qualifier travels
            // with it: admission is possibility, not splittability (BR-RefData-2 / AC-0-XM-11).
            $table->foreignId('case_configuration_id')
                ->constrained(table: 'catalog_case_configurations', indexName: 'catalog_vcw_case_config_fk');
            // audit: created_at / updated_at (timestamptz on PG). No lifecycle_state, no version — the
            // whitelist rides its Variant's lifecycle and never versions it (design D6).
            $table->timestampsTz();

            // A Case Configuration is admitted AT MOST ONCE per (Variant, Format) pair — the admitted set is
            // a SET, not a multiset (the BR-Identity idiom reused from the PR's own unique). The same CC may
            // of course recur across DIFFERENT pairs. Short explicit name (PG's 63-byte limit).
            //
            // Column ORDER is load-bearing: `(variant, format, …)` is the leftmost prefix of every read this
            // table serves — "the admitted set for this pair" (maintenance, task 3.1) and "is this CC admitted
            // for this pair?" (the SKU activation gate, task 3.2). Both engines answer those from this index,
            // so design D6's "supporting index on the pair" IS this unique's prefix; a separate pair index
            // would be pure duplication and is deliberately not created.
            $table->unique(
                ['product_variant_id', 'format_id', 'case_configuration_id'],
                'catalog_vcw_variant_format_case_unique',
            );
        });
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Dropping the table is safe —
     * it carries no immutability triggers, and its rows are reconstructible operator statements.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_variant_case_whitelists');
    }
};
