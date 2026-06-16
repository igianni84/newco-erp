<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Actions\CreateSellableSku;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\SellableSKUCreated;
use App\Modules\Catalog\Lifecycle\HasLifecycleState;
use Carbon\CarbonInterface;
use Database\Factories\Catalog\SellableSkuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sellable SKU (Intrinsic) — the commercial unit composed of EXACTLY one Product Reference + one Case
 * Configuration + commercial attributes (catalog-product-spine, design D5; product-catalog — Requirement:
 * Sellable SKU (Intrinsic); Module 0 PRD §3.7, §13.5 BR-SKU-1). It is the ONLY SKU shape that references a
 * Case Configuration — the Composite SKU (task 4.2) bundles N Product References and references none.
 *
 * A SINGLE-table entity: both dimensions are structural references ({@see reference()} → Product Reference,
 * {@see caseConfiguration()} → Case Configuration) and the commercial attributes (`commercial_name`,
 * `marketing_copy`) are plain SKU-level columns (§3.7 carries them at the SKU level; §8.1 scopes translatable
 * content to Master/Variant/PR, not the SKU). Packaging does NOT change the PR (BR-Identity-3): the same
 * Variant + Format resolves to the ONE PR, so three Case Configurations (loose / OWC / carton) yield three
 * SKUs over the SAME `product_reference_id`. There is no SKU uniqueness rule — a PR + Case Configuration pair
 * may back more than one SKU (contrast the PR's unique `(variant, format)` identity).
 *
 * Both relations are WITHIN the Catalog module (Product Reference and Case Configuration are the same module's
 * entities), so they are allowed — distinct from the forbidden cross-module producer relation (invariant 10).
 * Persistence-only by design (D8): the {@see CreateSellableSku} action is the sole writer — it inserts the row
 * and records {@see SellableSKUCreated} in one transaction — so `$guarded = []` carries no
 * mass-assignment-from-request risk. Born `draft`; its lifecycle transitions are driven by the shared
 * `LifecycleTransition` mechanism — the {@see HasLifecycleState} marker opts this entity in (a CHILD entity
 * with TWO within-module parents: its activation is additionally gated on BOTH its referenced Product
 * Reference AND its referenced Case Configuration being `active`, the §3.7 prerequisite / BR-Lifecycle-3,
 * design D7) — which stays the sole `lifecycle_state` writer, so the model remains persistence-only
 * (design D1/D2).
 *
 * @property int $id
 * @property int $product_reference_id
 * @property int $case_configuration_id
 * @property string $commercial_name
 * @property string|null $marketing_copy
 * @property LifecycleState $lifecycle_state
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read ProductReference|null $reference
 * @property-read CaseConfiguration|null $caseConfiguration
 */
class SellableSku extends Model implements HasLifecycleState
{
    /** @use HasFactory<SellableSkuFactory> */
    use HasFactory;

    protected $table = 'catalog_sellable_skus';

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
     * static analysis infer the factory's model for `SellableSku::factory()->create()`.
     */
    protected static function newFactory(): SellableSkuFactory
    {
        return SellableSkuFactory::new();
    }

    /**
     * The Product Reference dimension — the atomic product key the SKU is built on. A WITHIN-module relation
     * (the PR is the same module's entity, not another module's table).
     *
     * @return BelongsTo<ProductReference, $this>
     */
    public function reference(): BelongsTo
    {
        return $this->belongsTo(ProductReference::class, 'product_reference_id');
    }

    /**
     * The Case Configuration dimension — the packaging form. A WITHIN-module relation (Case Configuration is
     * the same module's reference entity). The Sellable SKU (Intrinsic) is the only shape that references one.
     *
     * @return BelongsTo<CaseConfiguration, $this>
     */
    public function caseConfiguration(): BelongsTo
    {
        return $this->belongsTo(CaseConfiguration::class, 'case_configuration_id');
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
            'product_reference_id' => 'integer',
            'case_configuration_id' => 'integer',
            'lifecycle_state' => LifecycleState::class,
            'version' => 'integer',
        ];
    }
}
