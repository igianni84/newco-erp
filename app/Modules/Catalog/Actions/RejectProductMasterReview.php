<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Models\ProductMaster;

/**
 * Rejects a Product Master under review (§ 4.3) through the shared {@see LifecycleTransition} mechanism
 * (catalog-lifecycle-approval task 2.3; design D5; product-catalog — Requirement: Approval Governance).
 *
 * A reviewer or approver rejects a Master in `reviewed`: the Master STAYS in `reviewed` (there is no revert to
 * `draft`), one `audit_records` row captures the actor, the `decision: rejected` and the `$notes`, and NO
 * domain event is recorded. The Creator then edits the Master in place and the approval flow continues — the
 * append-only audit trail preserves the full rejection history, and the un-remediated rejection leaves the
 * entity REVIEW-STALE — a condition DERIVED from the latest review-freshness-relevant audit verb, never a
 * schema flag (`ApprovalGovernance`). Only an explicit re-submit clears it; a later approval cannot, because a
 * review-stale entity is not activatable by ANY operator. Operator-floored (a `system`/null actor cannot
 * reject) and from-state guarded (a reject on a non-`reviewed` Master throws {@see IllegalLifecycleTransition}
 * and writes nothing). A thin per-entity wrapper — the entity label `ProductMaster` matches the domain-event
 * `entity_type`; the model stays persistence-only.
 */
class RejectProductMasterReview
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    /**
     * @throws IllegalLifecycleTransition when the Master is not in `reviewed`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function handle(ProductMaster $master, string $notes): ProductMaster
    {
        return $this->lifecycle->reject($master, 'ProductMaster', $notes);
    }
}
