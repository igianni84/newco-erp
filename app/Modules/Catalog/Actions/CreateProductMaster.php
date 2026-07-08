<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Events\ProductMasterCreated;
use App\Modules\Catalog\Exceptions\DuplicateProductMasterIdentity;
use App\Modules\Catalog\Exceptions\UnknownCatalogReference;
use App\Modules\Catalog\Exceptions\UnsupportedProductType;
use App\Modules\Catalog\Models\ProducerState;
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
 * Three guards make the launch invariants enforced, not advised:
 *   - FAIL-CLOSED TYPE (D2): a non-`WINE` `product_type` is rejected BEFORE the transaction opens — at
 *     launch `WINE` is the only supported type (AC-0-XM-9). The string boundary (`tryFrom`) is where an
 *     unsupported token is caught; the PostgreSQL `product_type` CHECK is the DB-level backstop.
 *   - PRODUCER EXISTENCE (AC-0-XM-2; catalog-module-0-completeness-sweep, design D7): inside the
 *     transaction, before any write, the producer must be KNOWN to Catalog — i.e. carry a row in the
 *     {@see ProducerState} projection. See {@see assertProducerExists()} for why the projection is the
 *     right (and only) place to ask.
 *   - IDENTITY DEDUP (D6, BR-Identity-1): inside the transaction, a plain-column join over
 *     `catalog_product_masters` ⋈ `catalog_product_master_wine_attributes` checks for a NON-RETIRED Master
 *     matching `producer_id + name + appellation`; a collision rejects with a localized reason. The join is
 *     portable across SQLite and PostgreSQL because `appellation` is a real column (ADR
 *     decisions/2026-06-14-catalog-category-neutral-representation.md).
 *
 * The two in-transaction guards run existence-then-dedup. Usually the order is unobservable — the dedup is
 * scoped BY `producer_id`, so under an unknown producer it scans an empty slice and passes vacuously. It
 * becomes observable exactly when a Master already exists under a producer whose projection row later went
 * away (pre-guard data, or a purged read model): both rejections then match, and the operator must be told the
 * ROOT fact ("that producer does not exist") rather than its downstream consequence ("something already claims
 * this name"). A reference that resolves to nothing is refused before any rule reasons about what it
 * references. Pinned by `ProducerExistenceGuardTest`.
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
    /** The entity-type label carried by the unknown-reference rejection — PII-free, never the producer's name. */
    private const PRODUCER_ENTITY = 'Producer';

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
            // AC-0-XM-2 (D7): the producer must EXIST. Checked first — the dedup below is producer-scoped and
            // would pass vacuously on an id that names nothing.
            $this->assertProducerExists($producerId);

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

    /**
     * AC-0-XM-2 — a Product Master may only be created under a producer Catalog KNOWS (design D7).
     *
     * "Knows" is answered by the {@see ProducerState} projection, and nowhere else: invariant 10 forbids
     * querying Module K, and `catalog_product_masters.producer_id` deliberately carries NO foreign key (a
     * cross-module FK is the same coupling by another name). So unlike every other unknown-reference guard in
     * this module, there is no database constraint standing behind this one — it is the SOLE protection, not
     * the readable face of a backstop. That is precisely why it must run inside the transaction and before any
     * write: nothing downstream will catch what it misses.
     *
     * ANY row admits creation — `registered` and `retired` alike. EXISTENCE IS NOT ACTIVENESS: the projection
     * answers two questions at two granularities, and this is the coarse one. Activation asks the sharp one
     * (`status === active`) through the untouched `ProducerActivationGate`, so a merely-registered producer
     * yields a Master that saves and holds in `draft`, and a retired producer yields one that can never be
     * activated. Widening this guard to demand `active` would silently move the gate upstream and make a
     * producer's activation order dictate the catalogue's data-entry order — the two rules are separate on
     * purpose.
     *
     * The projection is written INLINE today (the producer's `ProducerCreated` lands in Catalog within
     * `CreateProducer`'s own transaction), so the guard is exact. When the queue ADR moves delivery
     * off-thread it becomes eventually consistent: a Master created in the delivery window is rejected, never
     * wrongly admitted. Fail-closed is the acceptable direction (D7).
     *
     * @throws UnknownCatalogReference
     */
    private function assertProducerExists(int $producerId): void
    {
        if (ProducerState::query()->where('producer_id', $producerId)->doesntExist()) {
            throw UnknownCatalogReference::forIds(self::PRODUCER_ENTITY, [$producerId]);
        }
    }
}
