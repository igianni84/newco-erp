<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\SellableSku;

/**
 * Reopens a retired Sellable SKU for re-activation (`retired → reviewed`) through the shared
 * {@see LifecycleTransition} mechanism (catalog-lifecycle-approval task 4.5; design D1/D2; product-catalog —
 * Requirement: Product Lifecycle State Machine).
 *
 * Re-activation flows `retired → reviewed → active`: this reopen is the first leg and, like the
 * `draft → reviewed` submit, is AUDIT-ONLY — one `audit_records` row (`catalog.sellable_sku.reopened`)
 * and NO domain event (Module 0 PRD § 14.2). The SKU is edited in place from `reviewed` and re-activated; the
 * `reviewed → active` step re-checks the approval governance AND the parent-active cascade gate (both parents
 * — the Product Reference AND the Case Configuration — must be `active`; `ActivateSellableSku`). From-state
 * guarded against a transaction-locked re-read: a reopen on a SKU not in `retired` throws
 * {@see IllegalLifecycleTransition} and writes nothing.
 */
class ReopenSellableSku
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    public function handle(SellableSku $sellableSku): SellableSku
    {
        return $this->lifecycle->transition($sellableSku, LifecycleTransitionType::Reopen, 'SellableSku');
    }
}
