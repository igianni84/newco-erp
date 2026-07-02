<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Models\Format;

/**
 * Re-submits a Format under review (§ 4.3; RM-06 / canon MVP-DEC-019) through the shared
 * {@see LifecycleTransition} mechanism (design D2; product-catalog — Requirement: Approval Governance) —
 * the twin of {@see RejectFormatReview} that RE-ARMS the approval flow.
 *
 * After a reviewer/approver rejects a Format (it stays in `reviewed`, "rejection-pending" DERIVED from the
 * latest governance audit action — no schema flag), the Creator edits in place and re-submits: the Format
 * STAYS in `reviewed`, one `audit_records` row captures the actor and the `decision: resubmitted`, and NO
 * domain event is recorded. The re-submit is then the freshest governance action, so it clears the
 * review-freshness activation block-gate — a distinct approver can subsequently activate. Operator-floored
 * (a `system`/null actor cannot re-submit) and from-state guarded (a re-submit on a non-`reviewed` Format
 * throws {@see IllegalLifecycleTransition} and writes nothing). A thin per-entity wrapper — the entity label
 * `Format` matches the domain-event `entity_type`; the model stays persistence-only.
 */
class ResubmitFormatForReview
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    /**
     * @throws IllegalLifecycleTransition when the Format is not in `reviewed`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function handle(Format $format): Format
    {
        return $this->lifecycle->resubmit($format, 'Format');
    }
}
