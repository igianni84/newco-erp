<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\CompositeSku;

/**
 * Reopens a retired Composite SKU for re-activation (`retired → reviewed`) through the shared
 * {@see LifecycleTransition} mechanism (catalog-lifecycle-approval task 4.6; design D1/D2; product-catalog —
 * Requirement: Product Lifecycle State Machine).
 *
 * Re-activation flows `retired → reviewed → active`: this reopen is the first leg and, like the
 * `draft → reviewed` submit, is AUDIT-ONLY — one `audit_records` row (`catalog.composite_sku.reopened`)
 * and NO domain event (Module 0 PRD § 14.2). The SKU is edited in place from `reviewed` and re-activated; the
 * `reviewed → active` step re-checks the approval governance AND the parent-active cascade gate (EVERY
 * constituent Product Reference must be `active`; `ActivateCompositeSku`). From-state guarded against a
 * transaction-locked re-read: a reopen on a SKU not in `retired` throws {@see IllegalLifecycleTransition} and
 * writes nothing.
 */
class ReopenCompositeSku
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    public function handle(CompositeSku $compositeSku): CompositeSku
    {
        return $this->lifecycle->transition($compositeSku, LifecycleTransitionType::Reopen, 'CompositeSku');
    }
}
