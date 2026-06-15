<?php

namespace App\Modules\Catalog\Models;

use App\Platform\I18n\TranslatableText;
use App\Platform\I18n\TranslatableTextCast;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * The `WINE` per-type attribute set for a {@see ProductMaster}, held 1:1 off the neutral core
 * (catalog-product-spine, design D1; product-catalog — Requirement: Product Master, Category-Neutral
 * Product Type; ADR decisions/2026-06-14-catalog-category-neutral-representation.md).
 *
 * The §16 generalisation keeps the core category-neutral and places every wine-specific attribute here:
 * `appellation` and `region` (real, indexable columns — `appellation` is part of the BR-Identity-1 identity
 * key, so it is NOT buried in JSON), and `winery_story`, translatable descriptive prose persisted as
 * i18n-keyed JSON through {@see TranslatableTextCast} with per-attribute English fallback. A future Product
 * Type contributes its own `*_<type>_attributes` table; this one is never widened with non-wine columns.
 *
 * Persistence-only and written exclusively through the master's creation seam (the
 * {@see ProductMaster::wineAttributes()} relation `create()` called by the CreateProductMaster action / the
 * factory), so `$guarded = []` carries no request-driven mass-assignment risk.
 *
 * @property int $id
 * @property int $product_master_id
 * @property string $appellation
 * @property string $region
 * @property TranslatableText|null $winery_story
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class ProductMasterWineAttributes extends Model
{
    protected $table = 'catalog_product_master_wine_attributes';

    /** @var list<string> */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_master_id' => 'integer',
            'winery_story' => TranslatableTextCast::class,
        ];
    }
}
