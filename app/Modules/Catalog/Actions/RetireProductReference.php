<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Events\ProductReferenceRetired;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\ProductReference;

/**
 * Retires a Product Reference (`active → retired`) through the shared {@see LifecycleTransition} mechanism
 * (catalog-lifecycle-approval task 4.4; design D1/D9; product-catalog — Requirements: Product Lifecycle
 * State Machine, Product Lifecycle Events).
 *
 * Retire is a commercial-impact step: the mechanism enforces the from-state guard (valid only from `active`,
 * else {@see IllegalLifecycleTransition}) and the operator-principal floor (a `system`/null actor cannot
 * retire, else {@see ApprovalGovernanceViolation}) before it writes; on success it records ONE
 * `audit_records` row (`catalog.product_reference.retired`) AND the {@see ProductReferenceRetired} domain
 * event — the PII-free `*Retired` payload — in that same transaction (§ 14.1 / invariant 4 — the
 * transactional outbox). No activation gate applies to a retire (it passes no `$gate`).
 *
 * Scope (design D8): this is the SINGLE-entity retire. A Product Reference is referenced by Sellable /
 * Composite SKUs, so the within-catalog reference-integrity guard (reject a retire while the PR is referenced
 * by an `active` SKU — BR-Lifecycle-5) and the operator-driven parent-before-child cascade
 * (`RetireProductReference`'s `*Retired` recorded in `id` order under the Master's cascade) land in task 5.2;
 * the cross-module downstream-reference leg stays a documented Phase-3 seam. A thin per-entity wrapper: the
 * entity label is {@see ProductReferenceRetired::ENTITY_TYPE}; the model stays persistence-only.
 */
class RetireProductReference
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    /**
     * @throws IllegalLifecycleTransition when the PR is not in `active`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function handle(ProductReference $reference): ProductReference
    {
        return $this->lifecycle->transition(
            $reference,
            LifecycleTransitionType::Retire,
            ProductReferenceRetired::ENTITY_TYPE,
            event: fn (ProductReference $r) => ['name' => ProductReferenceRetired::NAME, 'payload' => ProductReferenceRetired::payload($r)],
        );
    }
}
