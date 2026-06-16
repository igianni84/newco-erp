<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\ProductVariant;

/**
 * Submits a Product Variant for review (`draft → reviewed`) through the shared {@see LifecycleTransition}
 * mechanism (catalog-lifecycle-approval task 4.3; design D1/D2; product-catalog — Requirement: Product
 * Lifecycle State Machine).
 *
 * The `draft → reviewed` checkpoint is internal-to-PIM and AUDIT-ONLY: it records one `audit_records` row
 * (`catalog.product_variant.submitted`, before/after `{lifecycle_state}`) and NO domain event (Module 0 PRD
 * § 14.2, AC-0-FSM-8) — the Variant's next domain event is its `ProductVariantActivated`, recorded by
 * `ActivateProductVariant`. From-state guarded against a transaction-locked re-read: a submit on a Variant
 * not in `draft` throws {@see IllegalLifecycleTransition} and writes nothing. A thin per-entity wrapper over
 * the shared mechanism — the entity label `ProductVariant` matches the domain-event `entity_type`; the model
 * stays persistence-only.
 */
class SubmitProductVariantForReview
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    public function handle(ProductVariant $variant): ProductVariant
    {
        return $this->lifecycle->transition($variant, LifecycleTransitionType::Submit, 'ProductVariant');
    }
}
