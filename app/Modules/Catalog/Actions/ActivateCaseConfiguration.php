<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Events\CaseConfigurationActivated;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\CaseConfiguration;

/**
 * Activates a Case Configuration (`reviewed → active`) through the shared {@see LifecycleTransition}
 * mechanism (catalog-lifecycle-approval task 4.2; design D1/D9; product-catalog — Requirements: Product
 * Lifecycle State Machine, Approval Governance, Product Lifecycle Events).
 *
 * A Case Configuration is a STANDALONE reference entity — it has no parent in the hierarchy — so its
 * activation carries NO activation gate (it passes no `$gate`): the only precondition is the approval
 * governance, which the mechanism applies on every commercial-impact step (task 2.3). The mechanism runs two
 * guards in order before it writes, all in one transaction:
 *   1. the from-state guard against a transaction-locked re-read — activate is valid only from `reviewed`,
 *      else {@see IllegalLifecycleTransition};
 *   2. the Creator → Reviewer → Approver approval governance (already wired in `transition()`, task 2.3) —
 *      the operator-principal floor + the separation-of-duties distinctness, else {@see ApprovalGovernanceViolation}.
 *
 * On success the Case Configuration moves to `active` and the mechanism records ONE `audit_records` row
 * (`catalog.case_configuration.activated`) AND the {@see CaseConfigurationActivated} domain event — the
 * PII-free `*Activated` payload — in that same transaction (§ 14.1 / invariant 4 — the transactional
 * outbox). A thin per-entity wrapper: the entity label is {@see CaseConfigurationActivated::ENTITY_TYPE};
 * the model stays persistence-only.
 */
class ActivateCaseConfiguration
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    /**
     * @throws IllegalLifecycleTransition when the Case Configuration is not in `reviewed`
     * @throws ApprovalGovernanceViolation when the approval governance is breached
     */
    public function handle(CaseConfiguration $caseConfiguration): CaseConfiguration
    {
        return $this->lifecycle->transition(
            $caseConfiguration,
            LifecycleTransitionType::Activate,
            CaseConfigurationActivated::ENTITY_TYPE,
            event: fn (CaseConfiguration $c) => ['name' => CaseConfigurationActivated::NAME, 'payload' => CaseConfigurationActivated::payload($c)],
        );
    }
}
