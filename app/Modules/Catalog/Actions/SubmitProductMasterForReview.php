<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\ProductMaster;

/**
 * Submits a Product Master for review (`draft → reviewed`) through the shared {@see LifecycleTransition}
 * mechanism (design D1/D2; product-catalog — Requirement: Product Lifecycle State Machine).
 *
 * The `draft → reviewed` checkpoint is internal-to-PIM and AUDIT-ONLY: it records one `audit_records` row
 * (`catalog.product_master.submitted`, before/after `{lifecycle_state}`) and NO domain event (Module 0 PRD
 * § 14.2, AC-0-FSM-8) — the Master's next domain event is its `ProductMasterActivated`, recorded by the
 * activation Action (a later task). From-state guarded against a transaction-locked re-read: a submit on a
 * Master not in `draft` throws {@see IllegalLifecycleTransition} and writes nothing. A thin per-entity
 * wrapper over the shared mechanism — the entity label `ProductMaster` matches the domain-event
 * `entity_type`; the model stays persistence-only.
 */
class SubmitProductMasterForReview
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    public function handle(ProductMaster $master): ProductMaster
    {
        return $this->lifecycle->transition($master, LifecycleTransitionType::Submit, 'ProductMaster');
    }
}
