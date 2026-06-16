<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\CompositeSKURetired;
use App\Modules\Catalog\Events\ProductReferenceRetired;
use App\Modules\Catalog\Events\SellableSKURetired;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Exceptions\RetirementReferenceIntegrityViolation;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Lifecycle\RetirementReferenceIntegrityGate;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\SellableSku;

/**
 * Retires a Product Reference (`active → retired`) through the shared {@see LifecycleTransition} mechanism
 * (catalog-lifecycle-approval tasks 4.4 / 5.2; design D1/D8/D9; product-catalog — Requirements: Product
 * Lifecycle State Machine, Retirement Cascade and Reference Integrity, Product Lifecycle Events).
 *
 * Retire is a commercial-impact step: the mechanism enforces the from-state guard (valid only from `active`,
 * else {@see IllegalLifecycleTransition}) and the operator-principal floor (a `system`/null actor cannot
 * retire, else {@see ApprovalGovernanceViolation}) before it writes; on success it records ONE
 * `audit_records` row (`catalog.product_reference.retired`) AND the {@see ProductReferenceRetired} domain
 * event — the PII-free `*Retired` payload — in that same transaction (§ 14.1 / invariant 4 — the
 * transactional outbox).
 *
 * Within-catalog reference-integrity guard (design D8; Module 0 PRD § 4.6, BR-Lifecycle-5 — within-catalog
 * subset; scoped to the terminal sellable edge per
 * `decisions/2026-06-16-catalog-retirement-reference-integrity-scope.md`, Option B). A Product Reference is a
 * dimension of a Sellable SKU (`product_reference_id`) and a constituent of a Composite SKU (the junction), so
 * retiring it out from under a still-`active` SKU would orphan something currently sellable. This Action passes
 * the {@see RetirementReferenceIntegrityGate} as the transition's `$gate` closure (evaluated after the operator
 * floor, before the write): it reads — WITHIN Module 0 — the `active` Sellable SKUs referencing this PR and the
 * `active` Composite SKUs that bundle it, and if any remain the retire is rejected with
 * {@see RetirementReferenceIntegrityViolation} surfacing the open references; the transaction rolls back, so the
 * PR stays `active` and records no `*Retired`. The operator closes those SKUs (or retires the whole tree via
 * {@see RetireProductMasterCascade}) and the retire then proceeds. The cross-module downstream-reference leg
 * (Allocations / vouchers / Offers) stays a documented Phase-3 seam. A thin per-entity wrapper: the entity
 * label is {@see ProductReferenceRetired::ENTITY_TYPE}; the model stays persistence-only.
 */
class RetireProductReference
{
    public function __construct(
        private readonly LifecycleTransition $lifecycle,
        private readonly RetirementReferenceIntegrityGate $referenceIntegrityGate,
    ) {}

    /**
     * @throws IllegalLifecycleTransition when the PR is not in `active`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     * @throws RetirementReferenceIntegrityViolation when an `active` Sellable / Composite SKU still references the PR
     */
    public function handle(ProductReference $reference): ProductReference
    {
        return $this->lifecycle->transition(
            $reference,
            LifecycleTransitionType::Retire,
            ProductReferenceRetired::ENTITY_TYPE,
            gate: function (ProductReference $r): void {
                // The active terminal sellable objects still referencing this PR, surfaced as
                // entity-type + id tokens — Sellable SKUs by `product_reference_id`, Composite SKUs by the
                // within-module constituent junction. Both reads are WITHIN Module 0 (invariant 10 untouched).
                $openReferences = array_merge(
                    SellableSku::query()
                        ->where('product_reference_id', $r->getKey())
                        ->where('lifecycle_state', LifecycleState::Active)
                        ->get()
                        ->map(fn (SellableSku $sku): string => SellableSKURetired::ENTITY_TYPE.'#'.$sku->id)
                        ->all(),
                    CompositeSku::query()
                        ->where('lifecycle_state', LifecycleState::Active)
                        ->whereHas('constituents', fn ($constituents) => $constituents->whereKey($r->getKey()))
                        ->get()
                        ->map(fn (CompositeSku $sku): string => CompositeSKURetired::ENTITY_TYPE.'#'.$sku->id)
                        ->all(),
                );

                $this->referenceIntegrityGate->assertNoActiveReferencers($openReferences, ProductReferenceRetired::ENTITY_TYPE);
            },
            event: fn (ProductReference $r) => ['name' => ProductReferenceRetired::NAME, 'payload' => ProductReferenceRetired::payload($r)],
        );
    }
}
