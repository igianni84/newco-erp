<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\CompositeSKURetired;
use App\Modules\Catalog\Events\ProductMasterRetired;
use App\Modules\Catalog\Events\ProductReferenceRetired;
use App\Modules\Catalog\Events\ProductVariantRetired;
use App\Modules\Catalog\Events\SellableSKURetired;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use Illuminate\Support\Facades\DB;

/**
 * The operator-driven retirement cascade — retires a Product Master together with its descendants in ONE
 * transaction, parent-before-child (catalog-lifecycle-approval task 5.2; design D8; product-catalog —
 * Requirements: Retirement Cascade and Reference Integrity, Product Lifecycle Events; Module 0 PRD § 4.7 /
 * § 14.3 / AC-0-FSM-11). This is the distinct multi-entity workflow the spec separates from the guarded
 * single-entity retire (§ 4.6): an operator MAY retire a Master and everything under it at once, and this
 * Action records each entity's `*Retired` in hierarchy order — Master → its Variants → their Product
 * References → the Sellable / Composite SKUs under those PRs.
 *
 * Ordering is EXPLICIT, not emergent (Codebase Pattern #24). The activation cascade (task 5.1) needs no glue —
 * each child is activated by a separate Action in a separate transaction, so `domain_events.id` ascends in
 * activation order on its own. The retirement cascade records MULTIPLE `*Retired` events inside ONE
 * transaction, so their parent-before-child `id` ordering must be produced by recording Master → Variants →
 * PRs → SKUs in that sequence here. The structural descendant set is collected up front (regardless of state)
 * so a non-`active` intermediate never hides a live grandchild; each level then retires only its `active`
 * members — the only entities with an `active → retired` edge (a `draft`/`reviewed` descendant was never
 * sellable; an already-`retired` one is a no-op).
 *
 * The cascade is GUARD-FREE: it calls the shared {@see LifecycleTransition::transition()} directly (NOT the
 * per-entity `Retire*` Actions), so the within-catalog reference-integrity guard does NOT run. That is
 * deliberate — the guard (on the single-entity {@see RetireProductReference} / {@see RetireCaseConfiguration})
 * blocks retiring a PR/Case Configuration out from under an `active` SKU; here the operator is retiring those
 * SKUs too, recorded AFTER their PRs, so applying the guard would falsely block the cascade. (Case
 * Configurations are NOT descended into — they are standalone reference entities shared across Masters, not
 * children of one; only Master → Variants → PRs → SKUs is the ownership tree, § 4.7.)
 *
 * The whole cascade is ATOMIC (CLAUDE.md invariant 4 — all-or-nothing): every transition's nested
 * {@see DB::transaction} is a savepoint inside this outer transaction, so a failure at any level (or a
 * non-`active` Master, which the Master's from-state guard rejects with {@see IllegalLifecycleTransition})
 * rolls the entire tree back — nothing is half-retired and no partial `*Retired` events survive. Each retire
 * still carries the operator-principal floor (a `system`/null actor cannot retire,
 * {@see ApprovalGovernanceViolation}); the same operator may retire every entity (the separation-of-duties
 * distinctness applies only to the approval step, not retire).
 */
class RetireProductMasterCascade
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    /**
     * @return ProductMaster the retired Master (its `active` descendants retired alongside it)
     *
     * @throws IllegalLifecycleTransition when the Master (or a descendant being retired) is not in `active`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function handle(ProductMaster $master): ProductMaster
    {
        return DB::transaction(function () use ($master): ProductMaster {
            // 1. Master first (parent-before-child). The from-state guard requires it to be `active`.
            $master = $this->lifecycle->transition(
                $master,
                LifecycleTransitionType::Retire,
                ProductMasterRetired::ENTITY_TYPE,
                event: fn (ProductMaster $m) => ['name' => ProductMasterRetired::NAME, 'payload' => ProductMasterRetired::payload($m)],
            );

            // The structural descendant ids (any state), collected up front for the level-by-level walk.
            $variantIds = ProductVariant::query()->where('product_master_id', $master->getKey())->pluck('id')->all();
            $referenceIds = ProductReference::query()->whereIn('product_variant_id', $variantIds)->pluck('id')->all();

            // 2. Variants under the Master (active only).
            foreach (
                ProductVariant::query()->whereKey($variantIds)->where('lifecycle_state', LifecycleState::Active)->get() as $variant
            ) {
                $this->lifecycle->transition(
                    $variant,
                    LifecycleTransitionType::Retire,
                    ProductVariantRetired::ENTITY_TYPE,
                    event: fn (ProductVariant $v) => ['name' => ProductVariantRetired::NAME, 'payload' => ProductVariantRetired::payload($v)],
                );
            }

            // 3. Product References under those Variants (active only).
            foreach (
                ProductReference::query()->whereKey($referenceIds)->where('lifecycle_state', LifecycleState::Active)->get() as $reference
            ) {
                $this->lifecycle->transition(
                    $reference,
                    LifecycleTransitionType::Retire,
                    ProductReferenceRetired::ENTITY_TYPE,
                    event: fn (ProductReference $r) => ['name' => ProductReferenceRetired::NAME, 'payload' => ProductReferenceRetired::payload($r)],
                );
            }

            // 4. The SKUs under those PRs (the leaf level), recorded after every PR so the id order stays
            //    parent-before-child (§ 14.3): Sellable (by `product_reference_id`) then Composite (by junction).
            foreach (
                SellableSku::query()->whereIn('product_reference_id', $referenceIds)->where('lifecycle_state', LifecycleState::Active)->get() as $sellableSku
            ) {
                $this->lifecycle->transition(
                    $sellableSku,
                    LifecycleTransitionType::Retire,
                    SellableSKURetired::ENTITY_TYPE,
                    event: fn (SellableSku $s) => ['name' => SellableSKURetired::NAME, 'payload' => SellableSKURetired::payload($s)],
                );
            }

            foreach (
                CompositeSku::query()
                    ->with('constituents')
                    ->where('lifecycle_state', LifecycleState::Active)
                    ->whereHas('constituents', fn ($constituents) => $constituents->whereKey($referenceIds))
                    ->get() as $compositeSku
            ) {
                $this->lifecycle->transition(
                    $compositeSku,
                    LifecycleTransitionType::Retire,
                    CompositeSKURetired::ENTITY_TYPE,
                    event: fn (CompositeSku $s) => ['name' => CompositeSKURetired::NAME, 'payload' => CompositeSKURetired::payload($s)],
                );
            }

            return $master;
        });
    }
}
