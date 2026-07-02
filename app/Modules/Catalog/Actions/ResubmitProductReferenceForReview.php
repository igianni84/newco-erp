<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Models\ProductReference;

/**
 * Re-submits a Product Reference under review (§ 4.3; RM-06 / canon MVP-DEC-019) through the shared
 * {@see LifecycleTransition} mechanism (design D2; product-catalog — Requirement: Approval Governance) —
 * the twin of {@see RejectProductReferenceReview} that RE-ARMS the approval flow.
 *
 * After a reviewer/approver rejects a PR (it stays in `reviewed`, "rejection-pending" DERIVED from the
 * latest governance audit action — no schema flag), the Creator edits in place and re-submits: the PR
 * STAYS in `reviewed`, one `audit_records` row captures the actor and the `decision: resubmitted`, and NO
 * domain event is recorded. The re-submit is then the freshest governance action, so it clears the
 * review-freshness activation block-gate — a distinct approver can subsequently activate. Operator-floored
 * (a `system`/null actor cannot re-submit) and from-state guarded (a re-submit on a non-`reviewed` PR
 * throws {@see IllegalLifecycleTransition} and writes nothing). A thin per-entity wrapper — the entity label
 * `ProductReference` matches the domain-event `entity_type`; the model stays persistence-only.
 */
class ResubmitProductReferenceForReview
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    /**
     * @throws IllegalLifecycleTransition when the PR is not in `reviewed`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function handle(ProductReference $reference): ProductReference
    {
        return $this->lifecycle->resubmit($reference, 'ProductReference');
    }
}
