<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Events\CaseConfigurationRetired;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\CaseConfiguration;

/**
 * Retires a Case Configuration (`active → retired`) through the shared {@see LifecycleTransition} mechanism
 * (catalog-lifecycle-approval task 4.2; design D1/D9; product-catalog — Requirements: Product Lifecycle State
 * Machine, Product Lifecycle Events).
 *
 * Retire is a commercial-impact step: the mechanism enforces the from-state guard (valid only from `active`,
 * else {@see IllegalLifecycleTransition}) and the operator-principal floor (a `system`/null actor cannot
 * retire, else {@see ApprovalGovernanceViolation}) before it writes; on success it records ONE
 * `audit_records` row (`catalog.case_configuration.retired`) AND the {@see CaseConfigurationRetired} domain
 * event — the PII-free `*Retired` payload — in that same transaction (§ 14.1 / invariant 4 — the
 * transactional outbox). No activation gate applies to a retire (it passes no `$gate`).
 *
 * Scope (design D8): this is the SINGLE-entity retire. A Case Configuration has no within-catalog child
 * references (it is referenced BY a Sellable SKU, never the reverse), so the BR-Lifecycle-5
 * reference-integrity guard landing in task 5.2 attaches to the entities that own children; the cross-module
 * downstream-reference leg (a Case Configuration referenced by an active Sellable SKU) stays a documented
 * Phase-3 seam. A thin per-entity wrapper: the entity label is {@see CaseConfigurationRetired::ENTITY_TYPE};
 * the model stays persistence-only.
 */
class RetireCaseConfiguration
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    /**
     * @throws IllegalLifecycleTransition when the Case Configuration is not in `active`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function handle(CaseConfiguration $caseConfiguration): CaseConfiguration
    {
        return $this->lifecycle->transition(
            $caseConfiguration,
            LifecycleTransitionType::Retire,
            CaseConfigurationRetired::ENTITY_TYPE,
            event: fn (CaseConfiguration $c) => ['name' => CaseConfigurationRetired::NAME, 'payload' => CaseConfigurationRetired::payload($c)],
        );
    }
}
