<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\ProductVariantCreated;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Module;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\I18n\TranslatableText;
use Illuminate\Support\Facades\DB;

/**
 * Creates a `WINE` Product Variant (neutral core + the wine attribute set) under a Product Master and records
 * its {@see ProductVariantCreated} event atomically (catalog-product-spine, design D5/D8; product-catalog —
 * Requirement: Product Variant, Spine Creation Events).
 *
 * Unlike the Master's creation seam, the Variant has NO identity dedup and NO fail-closed type guard: those
 * are Master-specific (the Product Type is fixed by the parent Master; BR-Identity-1 is a Master rule). The
 * single-parent invariant (BR-Identity-2 — a Variant belongs to exactly one Master) is enforced STRUCTURALLY
 * by the single `product_master_id` foreign key, not by an application check.
 *
 * In ONE {@see DB::transaction}: insert the neutral core, insert the 1:1 wine attribute set through the
 * within-module {@see ProductVariant::wineAttributes()} relation, and record the PII-free event via the
 * platform {@see DomainEventRecorder} (the actor resolved from the {@see ActorContext} seam — System until the
 * auth ADR wires real principals in). The recorder's own transaction guard makes write + emit atomic. The
 * model stays persistence-only; this action is the seam the deferred lifecycle/approval change extends.
 *
 * For WINE the variant axis is the vintage, held in the attribute set: `vintageYear` (null for a non-vintage
 * wine) and `nonVintage` (the explicit marker) — never a column on the neutral core (AC-0-GEN-3).
 */
class CreateProductVariant
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(
        int $productMasterId,
        string $variantIdentifier,
        ?int $vintageYear = null,
        bool $nonVintage = false,
        ?TranslatableText $tastingNotes = null,
    ): ProductVariant {
        return DB::transaction(function () use ($productMasterId, $variantIdentifier, $vintageYear, $nonVintage, $tastingNotes): ProductVariant {
            $variant = ProductVariant::create([
                'product_master_id' => $productMasterId,
                'variant_identifier' => $variantIdentifier,
                'lifecycle_state' => LifecycleState::Draft,
            ]);

            // 1:1 wine attribute set, written through the within-module relation (sets the FK).
            $variant->wineAttributes()->create([
                'vintage_year' => $vintageYear,
                'non_vintage' => $nonVintage,
                'tasting_notes' => $tastingNotes,
            ]);

            $this->recorder->record(
                name: ProductVariantCreated::NAME,
                module: Module::Catalog->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProductVariantCreated::ENTITY_TYPE,
                entityId: (string) $variant->id,
                payload: ProductVariantCreated::payload($variant),
            );

            return $variant;
        });
    }
}
