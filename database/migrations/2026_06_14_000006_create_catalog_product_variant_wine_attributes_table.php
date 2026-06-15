<?php

use App\Platform\I18n\TranslatableTextCast;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `catalog_product_variant_wine_attributes` — the `WINE` per-type attribute set for a Product Variant,
     * held 1:1 OFF the neutral core (catalog-product-spine, design D1; product-catalog — Requirement: Product
     * Variant; ADR decisions/2026-06-14-catalog-category-neutral-representation.md). The §16 generalisation
     * keeps the Variant core type-neutral and puts the WINE variant axis here, so a future Product Type adds
     * its own `*_<type>_attributes` table and neither the core nor the cross-module event contract is reshaped.
     *
     * For WINE the variant axis is the VINTAGE: `vintage_year` (a nullable integer — null when the wine is
     * non-vintage) and `non_vintage` (the explicit non-vintage marker), plus `tasting_notes`, the translatable
     * vintage-level prose held as i18n-keyed JSON via {@see TranslatableTextCast} with
     * per-attribute English fallback (§8 — six-locale translatable content; DEC-064 — the column stays
     * schema-less JSON, locale validity enforced at the application layer). Holding the vintage HERE (not on
     * the core) is the §16 / AC-0-GEN-3 contract: the neutral Variant core never hard-names a wine-only
     * "vintage" column.
     */
    public function up(): void
    {
        Schema::create('catalog_product_variant_wine_attributes', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines (design D4).
            $table->id();
            // WITHIN-module 1:1 FK to the neutral Variant core (allowed — same module; the cross-module ban is
            // about OTHER modules' tables, not the entity's own per-type extension). Cascade on delete: the
            // attribute set is owned by the Variant. Short explicit FK name (PG 63-char identifier limit — the
            // framework auto-name `catalog_product_variant_wine_attributes_product_variant_id_foreign` overflows).
            $table->foreignId('product_variant_id')
                ->constrained(table: 'catalog_product_variants', indexName: 'catalog_pv_wine_attrs_variant_fk')
                ->cascadeOnDelete();
            // the WINE variant axis = vintage. Nullable integer: a non-vintage wine carries no vintage year.
            $table->integer('vintage_year')->nullable();
            // the explicit non-vintage marker (a WINE may be a non-vintage release). Defaults false.
            $table->boolean('non_vintage')->default(false);
            // translatable vintage-level prose (tasting notes), i18n-keyed JSON via TranslatableTextCast.
            // Nullable: a Variant may carry no tasting notes yet (partial locale coverage allowed).
            $table->json('tasting_notes')->nullable();
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();
        });
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Runs before 000005's down()
     * (reverse migration order), so the FK is gone before the referenced table is dropped.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_product_variant_wine_attributes');
    }
};
