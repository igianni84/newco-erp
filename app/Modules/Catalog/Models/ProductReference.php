<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Actions\CreateProductReference;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\ProductReferenceCreated;
use App\Modules\Catalog\Lifecycle\HasLifecycleState;
use Carbon\CarbonInterface;
use Database\Factories\Catalog\ProductReferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Product Reference (PR) — the atomic product identity and the universal product key across modules
 * (catalog-product-spine, design D5; product-catalog — Requirement: Product Reference — the atomic product
 * key). §18 wine-display alias: "Bottle Reference (BR)" (a presentation/documentation term only — the
 * canonical structural name is the category-neutral `ProductReference`).
 *
 * A PR is composed of EXACTLY TWO dimensions — a {@see variant()} (Product Variant) and a {@see format()}
 * (Format) — and nothing else. It is a SINGLE-table entity (no per-type attribute set): both dimensions are
 * structural references. A Case Configuration is NEVER part of PR identity (BR-Identity-3): there is no
 * `case_configuration_id` on this model or its table — packaging is a Sellable-SKU dimension (task 4.1), so
 * the same Variant + Format resolves to the ONE PR whether sold loose, in an OWC, or in a carton. The
 * `(product_variant_id, format_id)` pair is unique at the database (the migration's unique index), making the
 * tuple the PR's identity: changing the composition is a new PR, not an in-place edit.
 *
 * Both relations are WITHIN the Catalog module (Variant and Format are the same module's entities), so they
 * are allowed — distinct from the forbidden cross-module producer relation (invariant 10). Persistence-only by
 * design (D8): the {@see CreateProductReference} action is the sole writer — it inserts the row and records
 * {@see ProductReferenceCreated} in one transaction — so `$guarded = []` carries no mass-assignment-from-request
 * risk. Born `draft`; its lifecycle transitions are driven by the shared `LifecycleTransition` mechanism — the
 * {@see HasLifecycleState} marker opts this entity in (a CHILD entity: its activation is additionally gated on
 * BOTH its parents — the Product Variant AND the Format — being `active`, design D7) — which stays the sole
 * `lifecycle_state` writer, so the model remains persistence-only (design D1/D2).
 *
 * @property int $id
 * @property int $product_variant_id
 * @property int $format_id
 * @property LifecycleState $lifecycle_state
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read ProductVariant|null $variant
 * @property-read Format|null $format
 */
class ProductReference extends Model implements HasLifecycleState
{
    /** @use HasFactory<ProductReferenceFactory> */
    use HasFactory;

    protected $table = 'catalog_product_references';

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
     * static analysis infer the factory's model for `ProductReference::factory()->create()`.
     */
    protected static function newFactory(): ProductReferenceFactory
    {
        return ProductReferenceFactory::new();
    }

    /**
     * The Product Variant dimension. A WITHIN-module relation (the Variant is the same module's entity, not
     * another module's table) — one of the PR's two identity dimensions.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * The Format dimension. A WITHIN-module relation (Format is the same module's reference entity) — the
     * PR's second identity dimension.
     *
     * @return BelongsTo<Format, $this>
     */
    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class, 'format_id');
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
            'product_variant_id' => 'integer',
            'format_id' => 'integer',
            'lifecycle_state' => LifecycleState::class,
            'version' => 'integer',
        ];
    }
}
