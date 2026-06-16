<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Events\ProductMasterActivated;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Exceptions\ProducerActivationGateViolation;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Lifecycle\ProducerActivationGate;
use App\Modules\Catalog\Models\ProductMaster;

/**
 * Activates a Product Master (`reviewed → active`) through the shared {@see LifecycleTransition} mechanism
 * (catalog-lifecycle-approval task 3.2; design D1/D6/D9; product-catalog — Requirements: Product Lifecycle
 * State Machine, Approval Governance, Producer Activation Gate, Product Lifecycle Events).
 *
 * The mechanism runs three guards in order before it writes the state, all in one transaction:
 *   1. the from-state guard against a transaction-locked re-read — activate is valid only from `reviewed`,
 *      else {@see IllegalLifecycleTransition} (so an out-of-state call is rejected before the gate is read);
 *   2. the Creator → Reviewer → Approver approval governance (already wired in `transition()`, task 2.3) —
 *      the operator-principal floor + the separation-of-duties distinctness, else {@see ApprovalGovernanceViolation};
 *   3. the Producer activation gate ({@see ProducerActivationGate}, passed as the `$gate` closure) — the
 *      linked Producer must be `active` in Catalog's producer-state projection, else
 *      {@see ProducerActivationGateViolation}. The gate reads the Catalog-owned projection only, never a
 *      Module K table (invariant 10), and re-activation (`retired → reviewed → active`) re-checks it because
 *      the same Action runs (AC-0-J-10).
 *
 * On success the Master moves to `active` and the mechanism records ONE `audit_records` row
 * (`catalog.product_master.activated`) AND the {@see ProductMasterActivated} domain event — the PII-free
 * `*Activated` payload (the producer by id only) — in that same transaction (§ 14.1 / invariant 4 — the
 * transactional outbox). A thin per-entity wrapper: the entity label is {@see ProductMasterActivated::ENTITY_TYPE}
 * (it MUST match the event/audit `entity_type`); the model stays persistence-only.
 */
class ActivateProductMaster
{
    public function __construct(
        private readonly LifecycleTransition $lifecycle,
        private readonly ProducerActivationGate $producerGate,
    ) {}

    /**
     * @throws IllegalLifecycleTransition when the Master is not in `reviewed`
     * @throws ApprovalGovernanceViolation when the approval governance is breached
     * @throws ProducerActivationGateViolation when the linked Producer is not `active` in the projection
     */
    public function handle(ProductMaster $master): ProductMaster
    {
        return $this->lifecycle->transition(
            $master,
            LifecycleTransitionType::Activate,
            ProductMasterActivated::ENTITY_TYPE,
            gate: fn (ProductMaster $m) => $this->producerGate->assertProducerActive($m->producer_id, ProductMasterActivated::ENTITY_TYPE),
            event: fn (ProductMaster $m) => ['name' => ProductMasterActivated::NAME, 'payload' => ProductMasterActivated::payload($m)],
        );
    }
}
