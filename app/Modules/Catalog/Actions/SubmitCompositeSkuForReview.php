<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\CompositeSku;

/**
 * Submits a Composite SKU for review (`draft → reviewed`) through the shared {@see LifecycleTransition}
 * mechanism (catalog-lifecycle-approval task 4.6; design D1/D2; product-catalog — Requirement: Product
 * Lifecycle State Machine).
 *
 * The `draft → reviewed` checkpoint is internal-to-PIM and AUDIT-ONLY: it records one `audit_records` row
 * (`catalog.composite_sku.submitted`, before/after `{lifecycle_state}`) and NO domain event (Module 0 PRD
 * § 14.2, AC-0-FSM-8) — the SKU's next domain event is its `CompositeSKUActivated`, recorded by
 * `ActivateCompositeSku`. From-state guarded against a transaction-locked re-read: a submit on a SKU not in
 * `draft` throws {@see IllegalLifecycleTransition} and writes nothing. A thin per-entity wrapper over the
 * shared mechanism — the entity label `CompositeSku` matches the domain-event `entity_type`; the model stays
 * persistence-only.
 */
class SubmitCompositeSkuForReview
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    public function handle(CompositeSku $compositeSku): CompositeSku
    {
        return $this->lifecycle->transition($compositeSku, LifecycleTransitionType::Submit, 'CompositeSku');
    }
}
