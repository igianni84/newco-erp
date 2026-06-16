<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\CaseConfiguration;

/**
 * Reopens a retired Case Configuration for re-activation (`retired → reviewed`) through the shared
 * {@see LifecycleTransition} mechanism (catalog-lifecycle-approval task 4.2; design D1/D2; product-catalog —
 * Requirement: Product Lifecycle State Machine).
 *
 * Re-activation flows `retired → reviewed → active`: this reopen is the first leg and, like the
 * `draft → reviewed` submit, is AUDIT-ONLY — one `audit_records` row (`catalog.case_configuration.reopened`)
 * and NO domain event (Module 0 PRD § 14.2). The Case Configuration is edited in place from `reviewed` and
 * re-activated; the `reviewed → active` step re-checks the approval governance
 * ({@see ActivateCaseConfiguration}). From-state guarded against a transaction-locked re-read: a reopen on a
 * Case Configuration not in `retired` throws {@see IllegalLifecycleTransition} and writes nothing.
 */
class ReopenCaseConfiguration
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    public function handle(CaseConfiguration $caseConfiguration): CaseConfiguration
    {
        return $this->lifecycle->transition($caseConfiguration, LifecycleTransitionType::Reopen, 'CaseConfiguration');
    }
}
