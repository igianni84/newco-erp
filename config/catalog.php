<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Approval Governance
    |--------------------------------------------------------------------------
    |
    | The Creator -> Reviewer -> Approver separation-of-duties workflow that
    | gates every commercial-impact lifecycle transition of a Module 0 spine
    | entity (catalog-lifecycle-approval, design D5; product-catalog —
    | Requirement: Approval Governance; Module 0 PRD § 4.2, feedback_prd_rr_approval).
    |
    | role_count is the number of DISTINCT approval roles in the workflow and is
    | operational configuration (∈ {2, 3}): the full three-step
    | Creator -> Reviewer -> Approver is the default, and a lighter two-step
    | Creator -> Approver MAY be configured. The separation-of-duties FLOOR —
    | each configured step performed by a distinct actor, no self-approval, every
    | step audited — holds at any configured depth, so this knob only widens or
    | narrows the distinctness set, never relaxes the floor. Read by
    | App\Modules\Catalog\Lifecycle\ApprovalGovernance, which normalises any
    | non-numeric value back to the three-step default.
    |
    */

    'approval' => [
        'role_count' => env('CATALOG_APPROVAL_ROLE_COUNT', 3),
    ],

];
