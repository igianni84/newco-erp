<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Models\CaseConfiguration;

/**
 * Re-submits a Case Configuration under review (§ 4.3; RM-06 / canon MVP-DEC-019) through the shared
 * {@see LifecycleTransition} mechanism (design D2; product-catalog — Requirement: Approval Governance) —
 * the twin of {@see RejectCaseConfigurationReview} that RE-ARMS the approval flow.
 *
 * After a reviewer/approver rejects a Case Configuration (it stays in `reviewed`, left REVIEW-STALE — a
 * condition DERIVED from the latest review-freshness-relevant audit verb, never a schema flag), the Creator
 * edits in place and re-submits: the Case Configuration STAYS in `reviewed`, one `audit_records` row captures
 * the actor and the `decision: resubmitted`, and NO domain event is recorded. The re-submit is then the
 * freshest review-freshness-relevant verb, so it clears the activation block-gate — an un-remediated rejection
 * being this entity's ONLY stale cause, as it carries no identity-edit path today — and a distinct approver
 * can subsequently activate. Operator-floored (a `system`/null actor cannot re-submit) and from-state guarded
 * (a re-submit on a non-`reviewed` Case Configuration throws {@see IllegalLifecycleTransition} and writes
 * nothing). A thin per-entity wrapper — the entity label `CaseConfiguration` matches the domain-event
 * `entity_type`; the model stays persistence-only.
 */
class ResubmitCaseConfigurationForReview
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    /**
     * @throws IllegalLifecycleTransition when the Case Configuration is not in `reviewed`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function handle(CaseConfiguration $caseConfiguration): CaseConfiguration
    {
        return $this->lifecycle->resubmit($caseConfiguration, 'CaseConfiguration');
    }
}
