<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Events\ProductMasterCreated;
use Carbon\CarbonInterface;
use Database\Factories\Catalog\ProductMasterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Product Master — the top of the product hierarchy and the parent of every Product Variant
 * (catalog-product-spine, design D1/D4/D6; product-catalog — Requirement: Product Master).
 * §18 wine-display alias: "Wine Master" (a presentation term only — the canonical structural name is
 * the category-neutral `ProductMaster`).
 *
 * This is the category-neutral CORE: it carries only neutral identity/structural fields — `name`,
 * `product_type`, the `producer_id` reference, `lifecycle_state`, audit/version. The wine-specific
 * attributes (appellation/region, winery story) live 1:1 off the core in {@see ProductMasterWineAttributes}
 * via {@see wineAttributes()}, so a future Product Type adds its own attribute table without reshaping this
 * core (§16; ADR decisions/2026-06-14-catalog-category-neutral-representation.md).
 *
 * The `producer_id` is a PLAIN id into Module K — never an Eloquent relation (the boundary law, invariant
 * 10). Persistence-only by design (D8): the {@see CreateProductMaster} action is the sole writer — it runs
 * the BR-Identity-1 dedup, inserts the core + wine attribute set, and records {@see ProductMasterCreated},
 * all in one transaction — so `$guarded = []` carries no mass-assignment-from-request risk. Born `draft`;
 * this change defines no transition out of it (design D3).
 *
 * @property int $id
 * @property string $name
 * @property ProductType $product_type
 * @property int $producer_id
 * @property LifecycleState $lifecycle_state
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read ProductMasterWineAttributes|null $wineAttributes
 */
class ProductMaster extends Model
{
    /** @use HasFactory<ProductMasterFactory> */
    use HasFactory;

    protected $table = 'catalog_product_masters';

    /**
     * The Create* action is the only writer; it builds the attribute set internally, so there is no
     * mass-assignment from request input to guard (mirrors the sibling spine models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The factory lives outside `Database\Factories\` convention (it is namespaced per module under
     * `Database\Factories\Catalog\`), so the model names it explicitly — and the explicit return type lets
     * static analysis infer the factory's model for `ProductMaster::factory()->create()`.
     */
    protected static function newFactory(): ProductMasterFactory
    {
        return ProductMasterFactory::new();
    }

    /**
     * The `WINE` per-type attribute set, held 1:1 off the neutral core. A WITHIN-module relation (the per-
     * type table is this entity's own extension, not another module's) — distinct from the forbidden
     * cross-module producer relation. Default key convention applies: FK `product_master_id`, local `id`.
     *
     * @return HasOne<ProductMasterWineAttributes, $this>
     */
    public function wineAttributes(): HasOne
    {
        return $this->hasOne(ProductMasterWineAttributes::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'lifecycle_state' => LifecycleState::class,
            'producer_id' => 'integer',
            'version' => 'integer',
        ];
    }
}
