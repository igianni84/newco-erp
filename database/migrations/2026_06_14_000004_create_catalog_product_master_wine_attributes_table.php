<?php

use App\Platform\I18n\TranslatableTextCast;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `catalog_product_master_wine_attributes` — the `WINE` per-type attribute set for a Product Master,
     * held 1:1 OFF the neutral core (catalog-product-spine, design D1; product-catalog — Requirement:
     * Product Master, Category-Neutral Product Type; ADR
     * decisions/2026-06-14-catalog-category-neutral-representation.md). The §16 generalisation keeps the
     * core category-neutral and puts every wine-specific attribute here, so a future Product Type adds its
     * own `*_<type>_attributes` table and the core (and the cross-module event contract) is never reshaped.
     *
     * Columns: `appellation` and `region` (identity/descriptive structural fields), and `winery_story`, the
     * translatable descriptive prose held as i18n-keyed JSON via {@see TranslatableTextCast}
     * with per-attribute English fallback (§ 8 — six-locale translatable content; DEC-064 — the column stays
     * schema-less JSON, locale validity enforced at the application layer). `winery_story` is nullable
     * (partial coverage is allowed — AC-0-XM-4); `appellation`/`region` are NOT NULL (`appellation` is part
     * of the WINE identity key).
     *
     * `appellation` is a REAL, indexable column (not a value buried in JSON) precisely so the § 13.1
     * BR-Identity-1 uniqueness key `producer + name + appellation` is enforced by a plain-column join over
     * `catalog_product_masters` ⋈ this table — identical on PostgreSQL and SQLite, with no jsonb
     * functional-index asymmetry (the reason per-type tables were chosen over a JSON attribute column —
     * the ADR's alternative 1). The FK and `appellation` index carry SHORT explicit names: the table name
     * is long, so the framework's auto-generated identifiers would exceed PostgreSQL's 63-char limit.
     */
    public function up(): void
    {
        Schema::create('catalog_product_master_wine_attributes', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines (design D4).
            $table->id();
            // WITHIN-module 1:1 FK to the neutral core (allowed — same module; the cross-module ban is about
            // OTHER modules' tables, not the entity's own per-type extension). Cascade on delete: the
            // attribute set is owned by the Master. Short explicit FK name (PG 63-char identifier limit).
            $table->foreignId('product_master_id')
                ->constrained(table: 'catalog_product_masters', indexName: 'catalog_pm_wine_attrs_master_fk')
                ->cascadeOnDelete();
            // identity/descriptive WINE fields. appellation is part of the BR-Identity-1 key — a real column.
            $table->string('appellation');
            $table->string('region');
            // translatable descriptive prose (the winery story), i18n-keyed JSON via TranslatableTextCast.
            // Nullable: a Master may carry no winery story yet (partial locale coverage allowed).
            $table->json('winery_story')->nullable();
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();

            // Supports the appellation leg of the BR-Identity-1 dedup join. Short explicit name (PG limit).
            $table->index('appellation', 'catalog_pm_wine_attrs_appellation_idx');
        });
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Runs before 000003's down()
     * (reverse migration order), so the FK is gone before the referenced table is dropped.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_product_master_wine_attributes');
    }
};
