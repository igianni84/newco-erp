<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\CaseConfigurationRetired;
use App\Modules\Catalog\Events\SellableSKURetired;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Exceptions\RetirementReferenceIntegrityViolation;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Lifecycle\RetirementReferenceIntegrityGate;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\SellableSku;

/**
 * Retires a Case Configuration (`active → retired`) through the shared {@see LifecycleTransition} mechanism
 * (catalog-lifecycle-approval tasks 4.2 / 5.2; design D1/D8/D9; product-catalog — Requirements: Product
 * Lifecycle State Machine, Retirement Cascade and Reference Integrity, Product Lifecycle Events).
 *
 * Retire is a commercial-impact step: the mechanism enforces the from-state guard (valid only from `active`,
 * else {@see IllegalLifecycleTransition}) and the operator-principal floor (a `system`/null actor cannot
 * retire, else {@see ApprovalGovernanceViolation}) before it writes; on success it records ONE
 * `audit_records` row (`catalog.case_configuration.retired`) AND the {@see CaseConfigurationRetired} domain
 * event — the PII-free `*Retired` payload — in that same transaction (§ 14.1 / invariant 4 — the
 * transactional outbox).
 *
 * Within-catalog reference-integrity guard (design D8; Module 0 PRD § 4.6, BR-Lifecycle-5 — within-catalog
 * subset; scoped to the terminal sellable edge per
 * `decisions/2026-06-16-catalog-retirement-reference-integrity-scope.md`, Option B). A Case Configuration is
 * the packaging dimension of a Sellable SKU (`case_configuration_id`) — it is referenced BY a Sellable SKU,
 * never by a Composite SKU (a Composite bundles Product References only). Retiring it out from under a
 * still-`active` Sellable SKU would orphan something currently sellable, so this Action passes the
 * {@see RetirementReferenceIntegrityGate} as the transition's `$gate` closure (evaluated after the operator
 * floor, before the write): it reads — WITHIN Module 0 — the `active` Sellable SKUs referencing this Case
 * Configuration, and if any remain the retire is rejected with {@see RetirementReferenceIntegrityViolation}
 * surfacing the open references; the transaction rolls back, so it stays `active` and records no `*Retired`.
 * The operator closes those SKUs (or retires the tree via {@see RetireProductMasterCascade}) and the retire
 * then proceeds. The cross-module downstream-reference leg stays a documented Phase-3 seam. A thin per-entity
 * wrapper: the entity label is {@see CaseConfigurationRetired::ENTITY_TYPE}; the model stays persistence-only.
 */
class RetireCaseConfiguration
{
    public function __construct(
        private readonly LifecycleTransition $lifecycle,
        private readonly RetirementReferenceIntegrityGate $referenceIntegrityGate,
    ) {}

    /**
     * @throws IllegalLifecycleTransition when the Case Configuration is not in `active`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     * @throws RetirementReferenceIntegrityViolation when an `active` Sellable SKU still references the Case Configuration
     */
    public function handle(CaseConfiguration $caseConfiguration): CaseConfiguration
    {
        return $this->lifecycle->transition(
            $caseConfiguration,
            LifecycleTransitionType::Retire,
            CaseConfigurationRetired::ENTITY_TYPE,
            gate: function (CaseConfiguration $c): void {
                // The active Sellable SKUs still referencing this Case Configuration, surfaced as
                // entity-type + id tokens. A WITHIN-module read by `case_configuration_id` (invariant 10
                // untouched); a Composite SKU references no Case Configuration, so only Sellable SKUs apply.
                $openReferences = SellableSku::query()
                    ->where('case_configuration_id', $c->getKey())
                    ->where('lifecycle_state', LifecycleState::Active)
                    ->get()
                    ->map(fn (SellableSku $sku): string => SellableSKURetired::ENTITY_TYPE.'#'.$sku->id)
                    ->all();

                $this->referenceIntegrityGate->assertNoActiveReferencers($openReferences, CaseConfigurationRetired::ENTITY_TYPE);
            },
            event: fn (CaseConfiguration $c) => ['name' => CaseConfigurationRetired::NAME, 'payload' => CaseConfigurationRetired::payload($c)],
        );
    }
}
