<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Events\ProductVariantRetired;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\ProductVariant;

/**
 * Retires a Product Variant (`active → retired`) through the shared {@see LifecycleTransition} mechanism
 * (catalog-lifecycle-approval task 4.3; design D1/D9; product-catalog — Requirements: Product Lifecycle
 * State Machine, Product Lifecycle Events).
 *
 * Retire is a commercial-impact step: the mechanism enforces the from-state guard (valid only from `active`,
 * else {@see IllegalLifecycleTransition}) and the operator-principal floor (a `system`/null actor cannot
 * retire, else {@see ApprovalGovernanceViolation}) before it writes; on success it records ONE
 * `audit_records` row (`catalog.product_variant.retired`) AND the {@see ProductVariantRetired} domain event —
 * the PII-free `*Retired` payload — in that same transaction (§ 14.1 / invariant 4 — the transactional
 * outbox). No activation gate applies to a retire (it passes no `$gate`).
 *
 * Scope (design D8; `decisions/2026-06-16-catalog-retirement-reference-integrity-scope.md`, Option B): this
 * is the SINGLE-entity retire, and a Product Variant is a HIERARCHY PARENT (it owns child Product References),
 * so it carries NO reference-integrity guard (it passes no `$gate`) — retiring a Variant with `active` PRs
 * SUCCEEDS and PRESERVES them (they stay `active`; only new activation under the now-`retired` Variant is
 * prevented — § 4.5 / BR-Lifecycle-4). The within-catalog reference-integrity guard is scoped to the terminal
 * sellable edge and lives only on `RetireProductReference` / `RetireCaseConfiguration`. The operator-driven
 * parent-before-child cascade ({@see RetireProductMasterCascade}, § 4.7) records this `*Retired` in `id` order
 * under the Master's cascade. The cross-module downstream-reference leg stays a documented Phase-3 seam. A thin
 * per-entity wrapper: the entity label is {@see ProductVariantRetired::ENTITY_TYPE}; the model stays
 * persistence-only.
 */
class RetireProductVariant
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    /**
     * @throws IllegalLifecycleTransition when the Variant is not in `active`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function handle(ProductVariant $variant): ProductVariant
    {
        return $this->lifecycle->transition(
            $variant,
            LifecycleTransitionType::Retire,
            ProductVariantRetired::ENTITY_TYPE,
            event: fn (ProductVariant $v) => ['name' => ProductVariantRetired::NAME, 'payload' => ProductVariantRetired::payload($v)],
        );
    }
}
