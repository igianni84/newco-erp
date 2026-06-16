<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Events\ProductReferenceActivated;
use App\Modules\Catalog\Exceptions\ActivationCascadeViolation;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\ActivationCascadeGate;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;

/**
 * Activates a Product Reference (`reviewed → active`) through the shared {@see LifecycleTransition} mechanism
 * (catalog-lifecycle-approval task 4.4; design D1/D7/D9; product-catalog — Requirements: Product Lifecycle
 * State Machine, Approval Governance, Activation Cascade, Product Lifecycle Events).
 *
 * A Product Reference is a CHILD entity with TWO within-module parents — its Product Variant
 * (`product_variant_id`) and its Format (`format_id`) — so its activation carries a parent gate that BOTH
 * parents must satisfy. The mechanism runs three guards in order before it writes, all in one transaction:
 *   1. the from-state guard against a transaction-locked re-read — activate is valid only from `reviewed`,
 *      else {@see IllegalLifecycleTransition} (so an out-of-state call is rejected before the gate is read);
 *   2. the Creator → Reviewer → Approver approval governance (already wired in `transition()`, task 2.3) —
 *      the operator-principal floor + the separation-of-duties distinctness, else {@see ApprovalGovernanceViolation}
 *      (ordered before the gate, so a self-approval throws the governance error, not the cascade error);
 *   3. the activation-cascade gate ({@see ActivationCascadeGate}, passed as the `$gate` closure) — applied
 *      ONCE PER PARENT: the {@see ProductVariant} AND the {@see Format} must each be `active`, else
 *      {@see ActivationCascadeViolation} naming the first non-`active` parent. Each parent is read WITHIN
 *      Module 0 (a sibling spine entity, not a projection — design D7); the reads are lock-free (a read-time
 *      gate), and re-activation (`retired → reviewed → active`) re-checks both because the same Action runs.
 *
 * On success the PR moves to `active` and the mechanism records ONE `audit_records` row
 * (`catalog.product_reference.activated`) AND the {@see ProductReferenceActivated} domain event — the PII-free
 * `*Activated` payload (both parents by id only) — in that same transaction (§ 14.1 / invariant 4 — the
 * transactional outbox). Because a child cannot reach `active` before its parents, parent-before-child
 * emission ordering falls out for free (§ 14.3). A thin per-entity wrapper: the entity label is
 * {@see ProductReferenceActivated::ENTITY_TYPE}; the model stays persistence-only.
 */
class ActivateProductReference
{
    public function __construct(
        private readonly LifecycleTransition $lifecycle,
        private readonly ActivationCascadeGate $cascadeGate,
    ) {}

    /**
     * @throws IllegalLifecycleTransition when the PR is not in `reviewed`
     * @throws ApprovalGovernanceViolation when the approval governance is breached
     * @throws ActivationCascadeViolation when the parent Product Variant or Format is not `active`
     */
    public function handle(ProductReference $reference): ProductReference
    {
        return $this->lifecycle->transition(
            $reference,
            LifecycleTransitionType::Activate,
            ProductReferenceActivated::ENTITY_TYPE,
            gate: function (ProductReference $r): void {
                $this->cascadeGate->assertParentActive(
                    ProductVariant::query()->whereKey($r->product_variant_id)->first(),
                    ProductReferenceActivated::ENTITY_TYPE,
                    'ProductVariant',
                );
                $this->cascadeGate->assertParentActive(
                    Format::query()->whereKey($r->format_id)->first(),
                    ProductReferenceActivated::ENTITY_TYPE,
                    'Format',
                );
            },
            event: fn (ProductReference $r) => ['name' => ProductReferenceActivated::NAME, 'payload' => ProductReferenceActivated::payload($r)],
        );
    }
}
