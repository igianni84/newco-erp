<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\ProductVariantCreated;
use App\Modules\Catalog\Lifecycle\HasLifecycleState;
use Carbon\CarbonInterface;
use Database\Factories\Catalog\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Product Variant — a release of a Product Master and the parent of every Product Reference
 * (catalog-product-spine, design D1/D5; product-catalog — Requirement: Product Variant).
 * §18 wine-display alias: "Wine Variant" (a presentation term only — the canonical structural name is the
 * category-neutral `ProductVariant`).
 *
 * This is the category-neutral CORE: it carries only neutral identity/structural fields — the single-parent
 * `product_master_id` reference, a TYPE-NEUTRAL `variant_identifier` (the variant axis; the axis value and
 * meaning live in the per-type set), `lifecycle_state`, audit/version. The wine-specific attributes (the
 * vintage year / non-vintage marker, tasting notes) live 1:1 off the core in {@see ProductVariantWineAttributes}
 * via {@see wineAttributes()}, so the core never hard-names a wine-only "vintage" dimension (§16 / AC-0-GEN-3;
 * ADR decisions/2026-06-14-catalog-category-neutral-representation.md).
 *
 * The {@see master()} relation is WITHIN the Catalog module (the parent is the same module's Product Master),
 * so it is allowed — distinct from the forbidden cross-module producer relation (invariant 10). The single FK
 * structurally enforces BR-Identity-2 (a Variant belongs to exactly one Master). Persistence-only by design
 * (D8): the {@see CreateProductVariant} action is the sole writer — it inserts the core + the wine attribute
 * set and records {@see ProductVariantCreated} in one transaction — so `$guarded = []` carries no
 * mass-assignment-from-request risk. Born `draft`; its lifecycle transitions are driven by the shared
 * `LifecycleTransition` mechanism — the {@see HasLifecycleState} marker opts this entity in (a CHILD entity:
 * its activation is additionally gated on the parent Product Master being `active`, design D7) — which stays
 * the sole `lifecycle_state` writer, so the model remains persistence-only (design D1/D2).
 *
 * @property int $id
 * @property int $product_master_id
 * @property string $variant_identifier
 * @property LifecycleState $lifecycle_state
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read ProductMaster|null $master
 * @property-read ProductVariantWineAttributes|null $wineAttributes
 */
class ProductVariant extends Model implements HasLifecycleState
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected $table = 'catalog_product_variants';

    /**
     * The Create* action is the only writer; it builds the attribute set internally, so there is no
     * mass-assignment from request input to guard (mirrors the sibling spine models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The factory lives outside `Database\Factories\` convention (namespaced per module under
     * `Database\Factories\Catalog\`), so the model names it explicitly — and the explicit return type lets
     * static analysis infer the factory's model for `ProductVariant::factory()->create()`.
     */
    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }

    /**
     * The single parent Product Master. A WITHIN-module relation (the Master is the same module's core, not
     * another module's table) — the single FK structurally enforces BR-Identity-2 (exactly one parent).
     *
     * @return BelongsTo<ProductMaster, $this>
     */
    public function master(): BelongsTo
    {
        return $this->belongsTo(ProductMaster::class, 'product_master_id');
    }

    /**
     * The `WINE` per-type attribute set, held 1:1 off the neutral core. A WITHIN-module relation (the per-type
     * table is this entity's own extension, not another module's). Default key convention applies: FK
     * `product_variant_id`, local `id`.
     *
     * @return HasOne<ProductVariantWineAttributes, $this>
     */
    public function wineAttributes(): HasOne
    {
        return $this->hasOne(ProductVariantWineAttributes::class);
    }

    /**
     * The {@see HasLifecycleState} read contract: report the current `lifecycle_state` so the shared
     * lifecycle mechanism reads it generically. A pure accessor — the mechanism, never this model, writes
     * the state (design D1).
     */
    public function lifecycleState(): LifecycleState
    {
        return $this->lifecycle_state;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_master_id' => 'integer',
            'lifecycle_state' => LifecycleState::class,
            'version' => 'integer',
        ];
    }
}
