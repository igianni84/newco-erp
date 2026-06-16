<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\ProductReference;

/**
 * Reopens a retired Product Reference for re-activation (`retired → reviewed`) through the shared
 * {@see LifecycleTransition} mechanism (catalog-lifecycle-approval task 4.4; design D1/D2; product-catalog —
 * Requirement: Product Lifecycle State Machine).
 *
 * Re-activation flows `retired → reviewed → active`: this reopen is the first leg and, like the
 * `draft → reviewed` submit, is AUDIT-ONLY — one `audit_records` row (`catalog.product_reference.reopened`)
 * and NO domain event (Module 0 PRD § 14.2). The PR is edited in place from `reviewed` and re-activated; the
 * `reviewed → active` step re-checks the approval governance AND the parent-active cascade gate (both parents
 * — the Variant AND the Format — must be `active`; `ActivateProductReference`). From-state guarded against a
 * transaction-locked re-read: a reopen on a PR not in `retired` throws {@see IllegalLifecycleTransition} and
 * writes nothing.
 */
class ReopenProductReference
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    public function handle(ProductReference $reference): ProductReference
    {
        return $this->lifecycle->transition($reference, LifecycleTransitionType::Reopen, 'ProductReference');
    }
}
