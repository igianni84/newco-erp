<?php

namespace App\Modules\Catalog\Models;

use App\Platform\I18n\TranslatableText;
use App\Platform\I18n\TranslatableTextCast;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * The `WINE` per-type attribute set for a {@see ProductVariant}, held 1:1 off the neutral core
 * (catalog-product-spine, design D1; product-catalog — Requirement: Product Variant; ADR
 * decisions/2026-06-14-catalog-category-neutral-representation.md).
 *
 * The §16 generalisation keeps the Variant core type-neutral and places the WINE variant axis here: the
 * vintage — `vintage_year` (nullable; null for a non-vintage wine) and `non_vintage` (the explicit
 * non-vintage marker) — and `tasting_notes`, translatable vintage-level prose persisted as i18n-keyed JSON
 * through {@see TranslatableTextCast} with per-attribute English fallback. Holding the vintage HERE (not on
 * the core) is the AC-0-GEN-3 contract; a future Product Type contributes its own `*_<type>_attributes` table.
 *
 * Persistence-only and written exclusively through the variant's creation seam (the
 * {@see ProductVariant::wineAttributes()} relation `create()` called by the CreateProductVariant action / the
 * factory), so `$guarded = []` carries no request-driven mass-assignment risk.
 *
 * @property int $id
 * @property int $product_variant_id
 * @property int|null $vintage_year
 * @property bool $non_vintage
 * @property TranslatableText|null $tasting_notes
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class ProductVariantWineAttributes extends Model
{
    protected $table = 'catalog_product_variant_wine_attributes';

    /** @var list<string> */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_variant_id' => 'integer',
            'vintage_year' => 'integer',
            'non_vintage' => 'boolean',
            'tasting_notes' => TranslatableTextCast::class,
        ];
    }
}
