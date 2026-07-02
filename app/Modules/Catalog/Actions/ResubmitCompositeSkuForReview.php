<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Models\CompositeSku;

/**
 * Re-submits a Composite SKU under review (§ 4.3; RM-06 / canon MVP-DEC-019) through the shared
 * {@see LifecycleTransition} mechanism (design D2; product-catalog — Requirement: Approval Governance) —
 * the twin of {@see RejectCompositeSkuReview} that RE-ARMS the approval flow.
 *
 * After a reviewer/approver rejects a SKU (it stays in `reviewed`, "rejection-pending" DERIVED from the
 * latest governance audit action — no schema flag), the Creator edits in place and re-submits: the SKU
 * STAYS in `reviewed`, one `audit_records` row captures the actor and the `decision: resubmitted`, and NO
 * domain event is recorded. The re-submit is then the freshest governance action, so it clears the
 * review-freshness activation block-gate — a distinct approver can subsequently activate. Operator-floored
 * (a `system`/null actor cannot re-submit) and from-state guarded (a re-submit on a non-`reviewed` SKU
 * throws {@see IllegalLifecycleTransition} and writes nothing). A thin per-entity wrapper — the entity label
 * `CompositeSku` matches the domain-event `entity_type`; the model stays persistence-only.
 */
class ResubmitCompositeSkuForReview
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    /**
     * @throws IllegalLifecycleTransition when the SKU is not in `reviewed`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function handle(CompositeSku $compositeSku): CompositeSku
    {
        return $this->lifecycle->resubmit($compositeSku, 'CompositeSku');
    }
}
