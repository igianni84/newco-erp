<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Models\CaseConfiguration;

/**
 * Rejects a Case Configuration under review (§ 4.3) through the shared {@see LifecycleTransition} mechanism
 * (catalog-lifecycle-approval task 4.2; design D5; product-catalog — Requirement: Approval Governance).
 *
 * A reviewer or approver rejects a Case Configuration in `reviewed`: the Case Configuration STAYS in
 * `reviewed` (there is no revert to `draft`), one `audit_records` row captures the actor, the
 * `decision: rejected` and the `$notes`, and NO domain event is recorded. The Case Configuration is then
 * edited in place and the approval flow continues — the append-only audit trail preserves the full rejection
 * history, and "rejection-pending" is DERIVED from the latest governance audit action (no schema flag) so a
 * later approval clears it. Operator-floored (a `system`/null actor cannot reject) and from-state guarded (a
 * reject on a non-`reviewed` Case Configuration throws {@see IllegalLifecycleTransition} and writes nothing).
 * A thin per-entity wrapper — the entity label `CaseConfiguration` matches the domain-event `entity_type`;
 * the model stays persistence-only.
 */
class RejectCaseConfigurationReview
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    /**
     * @throws IllegalLifecycleTransition when the Case Configuration is not in `reviewed`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function handle(CaseConfiguration $caseConfiguration, string $notes): CaseConfiguration
    {
        return $this->lifecycle->reject($caseConfiguration, 'CaseConfiguration', $notes);
    }
}
