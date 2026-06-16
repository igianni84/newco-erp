<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Events\SellableSKURetired;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\SellableSku;

/**
 * Retires a Sellable SKU (`active → retired`) through the shared {@see LifecycleTransition} mechanism
 * (catalog-lifecycle-approval task 4.5; design D1/D9; product-catalog — Requirements: Product Lifecycle
 * State Machine, Product Lifecycle Events).
 *
 * Retire is a commercial-impact step: the mechanism enforces the from-state guard (valid only from `active`,
 * else {@see IllegalLifecycleTransition}) and the operator-principal floor (a `system`/null actor cannot
 * retire, else {@see ApprovalGovernanceViolation}) before it writes; on success it records ONE
 * `audit_records` row (`catalog.sellable_sku.retired`) AND the {@see SellableSKURetired} domain event — the
 * PII-free `*Retired` payload — in that same transaction (§ 14.1 / invariant 4 — the transactional outbox).
 * No activation gate applies to a retire (it passes no `$gate`).
 *
 * Scope (design D8): this is the SINGLE-entity retire — a Sellable SKU is a LEAF in the catalog hierarchy
 * (nothing within catalog references it), so it carries no within-catalog reference-integrity guard; the
 * operator-driven parent-before-child cascade (its `*Retired` recorded last, under the Master's cascade) and
 * the cross-module downstream-reference leg (Allocations/Offers — Phase 3) land in task 5.2. A thin
 * per-entity wrapper: the entity label is {@see SellableSKURetired::ENTITY_TYPE}; the model stays
 * persistence-only.
 */
class RetireSellableSku
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    /**
     * @throws IllegalLifecycleTransition when the SKU is not in `active`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function handle(SellableSku $sellableSku): SellableSku
    {
        return $this->lifecycle->transition(
            $sellableSku,
            LifecycleTransitionType::Retire,
            SellableSKURetired::ENTITY_TYPE,
            event: fn (SellableSku $s) => ['name' => SellableSKURetired::NAME, 'payload' => SellableSKURetired::payload($s)],
        );
    }
}
