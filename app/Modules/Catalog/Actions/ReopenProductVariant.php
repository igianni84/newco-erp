<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\ProductVariant;

/**
 * Reopens a retired Product Variant for re-activation (`retired → reviewed`) through the shared
 * {@see LifecycleTransition} mechanism (catalog-lifecycle-approval task 4.3; design D1/D2; product-catalog —
 * Requirement: Product Lifecycle State Machine).
 *
 * Re-activation flows `retired → reviewed → active`: this reopen is the first leg and, like the
 * `draft → reviewed` submit, is AUDIT-ONLY — one `audit_records` row (`catalog.product_variant.reopened`) and
 * NO domain event (Module 0 PRD § 14.2). The Variant is edited in place from `reviewed` and re-activated; the
 * `reviewed → active` step re-checks the approval governance AND the parent-active cascade gate
 * (`ActivateProductVariant`). From-state guarded against a transaction-locked re-read: a reopen on a Variant
 * not in `retired` throws {@see IllegalLifecycleTransition} and writes nothing.
 */
class ReopenProductVariant
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    public function handle(ProductVariant $variant): ProductVariant
    {
        return $this->lifecycle->transition($variant, LifecycleTransitionType::Reopen, 'ProductVariant');
    }
}
