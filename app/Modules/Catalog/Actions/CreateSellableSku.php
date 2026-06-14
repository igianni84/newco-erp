<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\SellableSKUCreated;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\Module;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Sellable SKU (Intrinsic) — the commercial unit composing one Product Reference + one Case
 * Configuration + commercial attributes — and records its {@see SellableSKUCreated} event atomically
 * (catalog-product-spine, design D5/D8; product-catalog — Requirement: Sellable SKU (Intrinsic), Spine
 * Creation Events; Module 0 PRD §3.7, §13.5 BR-SKU-1).
 *
 * Like the Variant's and the PR's creation seams (and unlike the Master's), this action has NO
 * application-layer dedup and NO type guard: the spec defines no SKU uniqueness rule, and packaging variants
 * legitimately back many SKUs over one PR (the "Packaging does not change the PR" rule — the same Variant +
 * Format resolves to the ONE PR for loose / OWC / carton). The single-PR / single-Case-Configuration invariant
 * (BR-SKU-1) is enforced STRUCTURALLY by the two scalar foreign keys.
 *
 * In ONE {@see DB::transaction}: insert the row (`draft`) and record the PII-free event via the platform
 * {@see DomainEventRecorder} (the actor resolved from the {@see ActorContext} seam — System until the auth ADR
 * wires real principals in). The recorder's own transaction guard makes write + emit atomic. The §3.7
 * activation prerequisite (the PR and the Case Configuration both `active`) is NOT enforced here — there is no
 * lifecycle in this change (design D3); it is deferred to catalog-lifecycle-approval. The model stays
 * persistence-only; this action is the seam that change extends.
 */
class CreateSellableSku
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(
        int $productReferenceId,
        int $caseConfigurationId,
        string $commercialName,
        ?string $marketingCopy = null,
    ): SellableSku {
        return DB::transaction(function () use ($productReferenceId, $caseConfigurationId, $commercialName, $marketingCopy): SellableSku {
            $sellableSku = SellableSku::create([
                'product_reference_id' => $productReferenceId,
                'case_configuration_id' => $caseConfigurationId,
                'commercial_name' => $commercialName,
                'marketing_copy' => $marketingCopy,
                'lifecycle_state' => LifecycleState::Draft,
            ]);

            $this->recorder->record(
                name: SellableSKUCreated::NAME,
                module: Module::Catalog->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: SellableSKUCreated::ENTITY_TYPE,
                entityId: (string) $sellableSku->id,
                payload: SellableSKUCreated::payload($sellableSku),
            );

            return $sellableSku;
        });
    }
}
