<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\ProductMaster;

/**
 * Reopens a retired Product Master for re-activation (`retired → reviewed`) through the shared
 * {@see LifecycleTransition} mechanism (design D1/D2; product-catalog — Requirement: Product Lifecycle
 * State Machine).
 *
 * Re-activation flows `retired → reviewed → active`: this reopen is the first leg and, like the
 * `draft → reviewed` submit, is AUDIT-ONLY — one `audit_records` row (`catalog.product_master.reopened`)
 * and NO domain event (Module 0 PRD § 14.2). The Creator edits in place from `reviewed` and re-activates;
 * the `reviewed → active` step re-checks the activation gate and approval governance (later tasks,
 * AC-0-J-10). From-state guarded against a transaction-locked re-read: a reopen on a Master not in
 * `retired` throws {@see IllegalLifecycleTransition} and writes nothing.
 */
class ReopenProductMaster
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    public function handle(ProductMaster $master): ProductMaster
    {
        return $this->lifecycle->transition($master, LifecycleTransitionType::Reopen, 'ProductMaster');
    }
}
