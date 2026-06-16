<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Events\SellableSKUActivated;
use App\Modules\Catalog\Exceptions\ActivationCascadeViolation;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\ActivationCascadeGate;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\SellableSku;

/**
 * Activates a Sellable SKU (`reviewed → active`) through the shared {@see LifecycleTransition} mechanism
 * (catalog-lifecycle-approval task 4.5; design D1/D7/D9; product-catalog — Requirements: Product Lifecycle
 * State Machine, Approval Governance, Activation Cascade, Product Lifecycle Events).
 *
 * A Sellable SKU is a CHILD entity with TWO within-module parents — its Product Reference
 * (`product_reference_id`) and its Case Configuration (`case_configuration_id`) — so its activation carries a
 * parent gate that BOTH parents must satisfy (§3.7 / BR-Lifecycle-3). The mechanism runs three guards in
 * order before it writes, all in one transaction:
 *   1. the from-state guard against a transaction-locked re-read — activate is valid only from `reviewed`,
 *      else {@see IllegalLifecycleTransition} (so an out-of-state call is rejected before the gate is read);
 *   2. the Creator → Reviewer → Approver approval governance (already wired in `transition()`, task 2.3) —
 *      the operator-principal floor + the separation-of-duties distinctness, else {@see ApprovalGovernanceViolation}
 *      (ordered before the gate, so a self-approval throws the governance error, not the cascade error);
 *   3. the activation-cascade gate ({@see ActivationCascadeGate}, passed as the `$gate` closure) — applied
 *      ONCE PER PARENT: the {@see ProductReference} AND the {@see CaseConfiguration} must each be `active`,
 *      else {@see ActivationCascadeViolation} naming the first non-`active` parent. Each parent is read WITHIN
 *      Module 0 (a sibling spine entity, not a projection — design D7); the reads are lock-free (a read-time
 *      gate), and re-activation (`retired → reviewed → active`) re-checks both because the same Action runs.
 *
 * On success the SKU moves to `active` and the mechanism records ONE `audit_records` row
 * (`catalog.sellable_sku.activated`) AND the {@see SellableSKUActivated} domain event — the PII-free
 * `*Activated` payload (both parents by id only) — in that same transaction (§ 14.1 / invariant 4 — the
 * transactional outbox). Because a child cannot reach `active` before its parents, parent-before-child
 * emission ordering falls out for free (§ 14.3). A thin per-entity wrapper: the entity label is
 * {@see SellableSKUActivated::ENTITY_TYPE}; the model stays persistence-only.
 */
class ActivateSellableSku
{
    public function __construct(
        private readonly LifecycleTransition $lifecycle,
        private readonly ActivationCascadeGate $cascadeGate,
    ) {}

    /**
     * @throws IllegalLifecycleTransition when the SKU is not in `reviewed`
     * @throws ApprovalGovernanceViolation when the approval governance is breached
     * @throws ActivationCascadeViolation when the parent Product Reference or Case Configuration is not `active`
     */
    public function handle(SellableSku $sellableSku): SellableSku
    {
        return $this->lifecycle->transition(
            $sellableSku,
            LifecycleTransitionType::Activate,
            SellableSKUActivated::ENTITY_TYPE,
            gate: function (SellableSku $s): void {
                $this->cascadeGate->assertParentActive(
                    ProductReference::query()->whereKey($s->product_reference_id)->first(),
                    SellableSKUActivated::ENTITY_TYPE,
                    'ProductReference',
                );
                $this->cascadeGate->assertParentActive(
                    CaseConfiguration::query()->whereKey($s->case_configuration_id)->first(),
                    SellableSKUActivated::ENTITY_TYPE,
                    'CaseConfiguration',
                );
            },
            event: fn (SellableSku $s) => ['name' => SellableSKUActivated::NAME, 'payload' => SellableSKUActivated::payload($s)],
        );
    }
}
