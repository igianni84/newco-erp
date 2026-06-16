<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransitionType;
use App\Modules\Catalog\Models\CaseConfiguration;

/**
 * Submits a Case Configuration for review (`draft → reviewed`) through the shared {@see LifecycleTransition}
 * mechanism (catalog-lifecycle-approval task 4.2; design D1/D2; product-catalog — Requirement: Product
 * Lifecycle State Machine).
 *
 * The `draft → reviewed` checkpoint is internal-to-PIM and AUDIT-ONLY: it records one `audit_records` row
 * (`catalog.case_configuration.submitted`, before/after `{lifecycle_state}`) and NO domain event (Module 0
 * PRD § 14.2, AC-0-FSM-8) — the Case Configuration's next domain event is its `CaseConfigurationActivated`,
 * recorded by {@see ActivateCaseConfiguration}. From-state guarded against a transaction-locked re-read: a
 * submit on a Case Configuration not in `draft` throws {@see IllegalLifecycleTransition} and writes nothing.
 * A thin per-entity wrapper over the shared mechanism — the entity label `CaseConfiguration` matches the
 * domain-event `entity_type`; the model stays persistence-only.
 */
class SubmitCaseConfigurationForReview
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    public function handle(CaseConfiguration $caseConfiguration): CaseConfiguration
    {
        return $this->lifecycle->transition($caseConfiguration, LifecycleTransitionType::Submit, 'CaseConfiguration');
    }
}
