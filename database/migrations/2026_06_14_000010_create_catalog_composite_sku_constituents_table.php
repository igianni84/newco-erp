<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `catalog_composite_sku_constituents` — the join table realising the Composite SKU ⇄ Product Reference
     * many-to-many (catalog-product-spine, design D5/D9; product-catalog — Requirement: Composite SKU; Module 0
     * PRD §3.8). A Composite SKU is a curated bundle of N ≥ 2 ORDERED constituent Product References, and one PR
     * may be a constituent across multiple composites — both directions of the M:N live here, one row per
     * (composite, constituent) link.
     *
     * `composite_sku_id` CASCADES on delete — a constituent link belongs to its Composite SKU (the owning
     * parent in the bundle), so deleting the composite reaps its links (the same ownership asymmetry as the PR's
     * own `product_variant_id`). `product_reference_id` RESTRICTS (the framework default) — a Product Reference
     * is the spine's atomic key, SHARED across composites and the wider catalog, so it cannot be deleted out
     * from under a bundle that lists it.
     *
     * `position` records the constituent's order within the bundle (design D5 / the delta-spec "ordered
     * constituents"); the constituents are persisted and read back in this order. The DB UNIQUE on
     * `(composite_sku_id, product_reference_id)` (BR-Identity idiom, reused from the PR's own unique) makes a PR
     * appear AT MOST ONCE per composite — constituents are an ordered SET, not a multiset — while still allowing
     * the same PR across DIFFERENT composites (the M:N). This is a pure link table: no surrogate id, no audit
     * columns — the bundle's audit lives on the parent Composite SKU and its CompositeSKUCreated event; the
     * natural key is the unique pair. All index names are given SHORT explicit forms (the `csc` abbreviation):
     * the framework auto-names on this long table name would breach PostgreSQL's 63-char identifier limit.
     */
    public function up(): void
    {
        Schema::create('catalog_composite_sku_constituents', function (Blueprint $table) {
            // WITHIN-module FK to the owning Composite SKU. The link belongs to the bundle, so it cascades on
            // delete. Short explicit FK name (the auto-name would breach PG's 63-char identifier limit).
            $table->foreignId('composite_sku_id')
                ->constrained(table: 'catalog_composite_skus', indexName: 'catalog_csc_composite_fk')
                ->cascadeOnDelete();
            // WITHIN-module FK to the constituent Product Reference. The PR is the SHARED atomic product key,
            // referenced across composites and the wider catalog, NOT owned by any one bundle — so it restricts
            // on delete (the framework default): it cannot be deleted out from under a composite. Short FK name.
            $table->foreignId('product_reference_id')
                ->constrained(table: 'catalog_product_references', indexName: 'catalog_csc_reference_fk');
            // the constituent's 1-based order within the bundle (ordered constituents — design D5).
            $table->unsignedInteger('position');

            // BR-Identity idiom (reused from the PR's own unique): a PR appears AT MOST ONCE per composite — the
            // (composite, constituent) pair is unique. Constituents are an ordered SET, not a multiset; the same
            // PR may still recur across DIFFERENT composites (the M:N). Short explicit name (PG 63-char limit).
            $table->unique(['composite_sku_id', 'product_reference_id'], 'catalog_csc_composite_reference_unique');
        });
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists).
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_composite_sku_constituents');
    }
};
