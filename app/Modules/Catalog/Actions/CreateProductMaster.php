<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Events\ProductMasterCreated;
use App\Modules\Catalog\Exceptions\DuplicateProductMasterIdentity;
use App\Modules\Catalog\Exceptions\UnsupportedProductType;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Module;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\I18n\TranslatableText;
use Illuminate\Support\Facades\DB;

/**
 * Creates a `WINE` Product Master (neutral core + the wine attribute set) and records its
 * {@see ProductMasterCreated} event atomically (catalog-product-spine, design D2/D6/D8; product-catalog —
 * Requirement: Product Master, Category-Neutral Product Type, Spine Creation Events).
 *
 * Two guards make the launch invariants enforced, not advised:
 *   - FAIL-CLOSED TYPE (D2): a non-`WINE` `product_type` is rejected BEFORE the transaction opens — at
 *     launch `WINE` is the only supported type (AC-0-XM-9). The string boundary (`tryFrom`) is where an
 *     unsupported token is caught; the PostgreSQL `product_type` CHECK is the DB-level backstop.
 *   - IDENTITY DEDUP (D6, BR-Identity-1): inside the transaction, a plain-column join over
 *     `catalog_product_masters` ⋈ `catalog_product_master_wine_attributes` checks for a NON-RETIRED Master
 *     matching `producer_id + name + appellation`; a collision rejects with a localized reason. The join is
 *     portable across SQLite and PostgreSQL because `appellation` is a real column (ADR
 *     decisions/2026-06-14-catalog-category-neutral-representation.md).
 *
 * Then, in ONE {@see DB::transaction}: insert the neutral core, insert the 1:1 wine attribute set through
 * the within-module {@see ProductMaster::wineAttributes()} relation, and record the PII-free event via the
 * platform {@see DomainEventRecorder} (the actor resolved from the {@see ActorContext} seam — System until
 * the auth ADR wires real principals in). The recorder's own transaction guard makes write + emit atomic.
 * The model stays persistence-only; this action is the seam the deferred lifecycle/approval change extends.
 *
 * The producer is captured as a bare `producer_id` — no Eloquent relation crosses the module boundary
 * (CLAUDE.md invariant 10).
 */
class CreateProductMaster
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(
        string $name,
        int $producerId,
        string $appellation,
        string $region,
        ?TranslatableText $wineryStory = null,
        string $productType = ProductType::Wine->value,
    ): ProductMaster {
        // Fail-closed: only WINE is supported at launch (D2). Caught at the string boundary, before any write.
        $type = ProductType::tryFrom($productType);

        if ($type !== ProductType::Wine) {
            throw UnsupportedProductType::forToken($productType);
        }

        return DB::transaction(function () use ($name, $producerId, $appellation, $region, $wineryStory, $type): ProductMaster {
            // BR-Identity-1 dedup (D6): reject a collision on producer + name + appellation against any
            // NON-RETIRED Master. Plain-column join — portable on both engines (appellation is real).
            $collides = ProductMaster::query()
                ->join('catalog_product_master_wine_attributes as wine', 'wine.product_master_id', '=', 'catalog_product_masters.id')
                ->where('catalog_product_masters.producer_id', $producerId)
                ->where('catalog_product_masters.name', $name)
                ->where('wine.appellation', $appellation)
                ->where('catalog_product_masters.lifecycle_state', '!=', LifecycleState::Retired->value)
                ->exists();

            if ($collides) {
                throw DuplicateProductMasterIdentity::forWine($producerId, $name, $appellation);
            }

            $master = ProductMaster::create([
                'name' => $name,
                'product_type' => $type,
                'producer_id' => $producerId,
                'lifecycle_state' => LifecycleState::Draft,
            ]);

            // 1:1 wine attribute set, written through the within-module relation (sets the FK).
            $master->wineAttributes()->create([
                'appellation' => $appellation,
                'region' => $region,
                'winery_story' => $wineryStory,
            ]);

            $this->recorder->record(
                name: ProductMasterCreated::NAME,
                module: Module::Catalog->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProductMasterCreated::ENTITY_TYPE,
                entityId: (string) $master->id,
                payload: ProductMasterCreated::payload($master),
            );

            return $master;
        });
    }
}
