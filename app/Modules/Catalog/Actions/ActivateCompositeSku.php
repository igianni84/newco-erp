<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Events\CompositeSKUActivated;
use App\Modules\Catalog\Exceptions\ActivationCascadeViolation;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\ActivationCascadeGate;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductReference;

/**
 * Activates a Composite SKU (`reviewed → active`) through the shared {@see LifecycleTransition} mechanism
 * (catalog-lifecycle-approval task 4.6; design D1/D7/D9; product-catalog — Requirements: Product Lifecycle
 * State Machine, Approval Governance, Activation Cascade, Product Lifecycle Events).
 *
 * A Composite SKU is a CHILD entity with N ≥ 2 within-module parents — EVERY constituent Product Reference of
 * its ordered bundle (the {@see CompositeSku::constituents()} junction) — so its activation carries a parent
 * gate that EVERY constituent must satisfy (§4.4 / BR-Lifecycle-3). The mechanism runs three guards in order
 * before it writes, all in one transaction:
 *   1. the from-state guard against a transaction-locked re-read — activate is valid only from `reviewed`,
 *      else {@see IllegalLifecycleTransition} (so an out-of-state call is rejected before the gate is read);
 *   2. the Creator → Reviewer → Approver approval governance (already wired in `transition()`, task 2.3) —
 *      the operator-principal floor + the separation-of-duties distinctness, else {@see ApprovalGovernanceViolation}
 *      (ordered before the gate, so a self-approval throws the governance error, not the cascade error);
 *   3. the activation-cascade gate ({@see ActivationCascadeGate}, passed as the `$gate` closure) — applied
 *      ONCE PER CONSTITUENT: the gate LOOPS over every {@see ProductReference} in the bundle and each must be
 *      `active`, else {@see ActivationCascadeViolation} naming the first non-`active` constituent. This is the
 *      N-constituent case of the same per-parent primitive the single- and two-parent children use (Variant,
 *      Reference, Sellable SKU — tasks 4.3–4.5): a multi-parent child calls `assertParentActive` once per
 *      parent, an N-constituent child loops it. Each constituent is read WITHIN Module 0 (a sibling spine
 *      entity, not a projection — design D7); the reads are lock-free (a read-time gate), and re-activation
 *      (`retired → reviewed → active`) re-checks because the same Action runs.
 *
 * On success the SKU moves to `active` and the mechanism records ONE `audit_records` row
 * (`catalog.composite_sku.activated`) AND the {@see CompositeSKUActivated} domain event — the PII-free
 * `*Activated` payload (the bundle's constituent ids only) — in that same transaction (§ 14.1 / invariant 4 —
 * the transactional outbox). Because a child cannot reach `active` before its parents, parent-before-child
 * emission ordering falls out for free (§ 14.3). A thin per-entity wrapper: the entity label is
 * {@see CompositeSKUActivated::ENTITY_TYPE}; the model stays persistence-only.
 */
class ActivateCompositeSku
{
    public function __construct(
        private readonly LifecycleTransition $lifecycle,
        private readonly ActivationCascadeGate $cascadeGate,
    ) {}

    /**
     * @throws IllegalLifecycleTransition when the SKU is not in `reviewed`
     * @throws ApprovalGovernanceViolation when the approval governance is breached
     * @throws ActivationCascadeViolation when any constituent Product Reference is not `active`
     */
    public function handle(CompositeSku $compositeSku): CompositeSku
    {
        return $this->lifecycle->transition(
            $compositeSku,
            LifecycleTransitionType::Activate,
            CompositeSKUActivated::ENTITY_TYPE,
            gate: function (CompositeSku $sku): void {
                // EVERY constituent Product Reference must be `active` (the N-constituent activation cascade):
                // loop the SAME per-parent primitive the single-/two-parent children use over the within-module
                // junction. A non-`active` (or unresolved — fail-closed) constituent rejects the activation and
                // the rejection names the blocking ProductReference. The relation lazy-loads once here and the
                // event payload reuses the loaded set.
                foreach ($sku->constituents as $constituent) {
                    $this->cascadeGate->assertParentActive(
                        $constituent,
                        CompositeSKUActivated::ENTITY_TYPE,
                        'ProductReference',
                    );
                }
            },
            event: fn (CompositeSku $sku) => ['name' => CompositeSKUActivated::NAME, 'payload' => CompositeSKUActivated::payload($sku)],
        );
    }
}
