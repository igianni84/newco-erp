<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\ProductReference;

/**
 * Submits a Product Reference for review (`draft → reviewed`) through the shared {@see LifecycleTransition}
 * mechanism (catalog-lifecycle-approval task 4.4; design D1/D2; product-catalog — Requirement: Product
 * Lifecycle State Machine).
 *
 * The `draft → reviewed` checkpoint is internal-to-PIM and AUDIT-ONLY: it records one `audit_records` row
 * (`catalog.product_reference.submitted`, before/after `{lifecycle_state}`) and NO domain event (Module 0 PRD
 * § 14.2, AC-0-FSM-8) — the PR's next domain event is its `ProductReferenceActivated`, recorded by
 * `ActivateProductReference`. From-state guarded against a transaction-locked re-read: a submit on a PR not in
 * `draft` throws {@see IllegalLifecycleTransition} and writes nothing. A thin per-entity wrapper over the
 * shared mechanism — the entity label `ProductReference` matches the domain-event `entity_type`; the model
 * stays persistence-only.
 */
class SubmitProductReferenceForReview
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    public function handle(ProductReference $reference): ProductReference
    {
        return $this->lifecycle->transition($reference, LifecycleTransitionType::Submit, 'ProductReference');
    }
}
