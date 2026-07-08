<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Events\ProductVariantActivated;
use App\Modules\Catalog\Exceptions\ActivationCascadeViolation;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\ActivationCascadeGate;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductVariant;

/**
 * Activates a Product Variant (`reviewed → active`) through the shared {@see LifecycleTransition} mechanism
 * (catalog-lifecycle-approval task 4.3; design D1/D7/D9; product-catalog — Requirements: Product Lifecycle
 * State Machine, Approval Governance, Activation Cascade, Product Lifecycle Events).
 *
 * A Product Variant is the FIRST CHILD entity to gain its lifecycle — unlike the standalone Format / Case
 * Configuration, its activation carries a parent gate. The mechanism runs three guards in order before it
 * writes, all in one transaction:
 *   1. the from-state guard against a transaction-locked re-read — activate is valid only from `reviewed`,
 *      else {@see IllegalLifecycleTransition} (so an out-of-state call is rejected before the gate is read);
 *   2. the Creator → Reviewer → Approver approval governance (already wired in `transition()`, task 2.3) —
 *      the operator-principal floor + the separation-of-duties distinctness, else {@see ApprovalGovernanceViolation}
 *      (ordered before the gate, so a self-approval throws the governance error, not the cascade error);
 *   3. the activation-cascade gate ({@see ActivationCascadeGate}, passed as the `$gate` closure) — the parent
 *      {@see ProductMaster} (`product_master_id`) must be `active`, else {@see ActivationCascadeViolation}.
 *      The parent is read WITHIN Module 0 (a sibling spine entity, not a projection — design D7); the read is
 *      lock-free (a read-time gate), and re-activation (`retired → reviewed → active`) re-checks it because
 *      the same Action runs.
 *
 * On success the Variant moves to `active` and the mechanism records ONE `audit_records` row
 * (`catalog.product_variant.activated`) AND the {@see ProductVariantActivated} domain event — the PII-free
 * `*Activated` payload (the parent Master by id only) — in that same transaction (§ 14.1 / invariant 4 — the
 * transactional outbox). Because a child cannot reach `active` before its parent, parent-before-child
 * emission ordering falls out for free (§ 14.3). A thin per-entity wrapper: the entity label is
 * {@see ProductVariantActivated::ENTITY_TYPE}; the model stays persistence-only.
 */
class ActivateProductVariant
{
    public function __construct(
        private readonly LifecycleTransition $lifecycle,
        private readonly ActivationCascadeGate $cascadeGate,
    ) {}

    /**
     * @throws IllegalLifecycleTransition when the Variant is not in `reviewed`
     * @throws ApprovalGovernanceViolation when the approval governance is breached
     * @throws ActivationCascadeViolation when the parent Product Master is not `active`
     */
    public function handle(ProductVariant $variant): ProductVariant
    {
        return $this->lifecycle->transition(
            $variant,
            LifecycleTransitionType::Activate,
            ProductVariantActivated::ENTITY_TYPE,
            // A block closure, not an arrow function: the `$gate` contract is `Closure(T): void`, and
            // `assertParentActive()` hands the proven parent back (a value only `ActivateSellableSku` reads, for
            // the Layer-1 whitelist gate's pair) — an arrow function would implicitly return it. Discarding it
            // here is the point: a Variant has ONE parent and nothing downstream needs the loaded Master.
            gate: function (ProductVariant $v): void {
                $this->cascadeGate->assertParentActive(
                    ProductMaster::query()->whereKey($v->product_master_id)->first(),
                    ProductVariantActivated::ENTITY_TYPE,
                    'ProductMaster',
                );
            },
            event: fn (ProductVariant $v) => ['name' => ProductVariantActivated::NAME, 'payload' => ProductVariantActivated::payload($v)],
        );
    }
}
