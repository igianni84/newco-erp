<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Actions\CreateCompositeSku;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\CompositeSKUCreated;
use Carbon\CarbonInterface;
use Database\Factories\Catalog\CompositeSkuFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Composite SKU — a curated bundle of N ≥ 2 ordered constituent Product References (catalog-product-spine,
 * design D5/D9; product-catalog — Requirement: Composite SKU; Module 0 PRD §3.8, §13.5 BR-SKU-2). It is the
 * second SKU shape and the spine's only many-to-many entity: its content lives entirely in the
 * `catalog_composite_sku_constituents` join table, reached through {@see constituents()}.
 *
 * The model is deliberately attribute-free beyond lifecycle/audit — §3.8 keeps the Composite SKU "cheap at PIM
 * (registration + lifecycle only)": no commercial name, no marketing copy, no club / Hero-Package /
 * promotional flag, no per-constituent allocation binding (those are Module S Offer / Module A concerns). The
 * Composite SKU's whole substance is its ordered constituent set.
 *
 * {@see constituents()} is a WITHIN-module relation (Product Reference is the same module's entity), so it is
 * allowed — distinct from the forbidden cross-module producer relation (invariant 10). The product-catalog is
 * PRODUCER-AGNOSTIC about constituents (design D9 / BR-SKU-5): nothing here validates producer composition; a
 * multi-producer bundle is accepted, and single-producer admissibility is a Module S Offer-publication rule,
 * never a PIM check. Persistence-only by design (D8): the {@see CreateCompositeSku} action is the sole writer —
 * it inserts the parent + constituent links and records {@see CompositeSKUCreated} in one transaction — so
 * `$guarded = []` carries no mass-assignment-from-request risk. Born `draft`; this change defines no transition
 * out of it (the §3.8 immutability-after-active-Offer rule and atomicity-at-sale are deferred — design D3).
 *
 * @property int $id
 * @property LifecycleState $lifecycle_state
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Collection<int, ProductReference> $constituents
 */
class CompositeSku extends Model
{
    /** @use HasFactory<CompositeSkuFactory> */
    use HasFactory;

    protected $table = 'catalog_composite_skus';

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
     * static analysis infer the factory's model for `CompositeSku::factory()->create()`.
     */
    protected static function newFactory(): CompositeSkuFactory
    {
        return CompositeSkuFactory::new();
    }

    /**
     * The ordered constituent Product References — the bundle's whole content. A WITHIN-module many-to-many
     * over the `catalog_composite_sku_constituents` join table (Product Reference is the same module's entity,
     * not another module's). `position` is exposed on the pivot and orders the set, so the constituents are
     * always read back in bundle order. The same PR may be a constituent of multiple composites (the M:N),
     * while the join's unique `(composite_sku_id, product_reference_id)` keeps it at most once per composite.
     *
     * @return BelongsToMany<ProductReference, $this>
     */
    public function constituents(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductReference::class,
            'catalog_composite_sku_constituents',
            'composite_sku_id',
            'product_reference_id',
        )->withPivot('position')->orderByPivot('position');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lifecycle_state' => LifecycleState::class,
            'version' => 'integer',
        ];
    }
}
