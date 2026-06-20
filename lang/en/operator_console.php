<?php

// Operator console UI copy (operator-console-catalog-master, task 2.1; design L8; invariant 12).
// The static UI chrome of the OperatorPanel Filament surface — resolved with __('operator_console.…').
// EN is the baseline; lang/it/operator_console.php may be partial and falls back per-key to EN (DEC-127).
// Domain rejection messages are NOT here — they live in lang/{en,it}/catalog.php and are reused where an
// invoked Catalog action's exception message is surfaced (tasks 3–5).
return [

    'product_master' => [
        // The canonical structural domain term — kept verbatim (CONTEXT.md), untranslated in IT.
        'label' => 'Product Master',
        'plural_label' => 'Product Masters',

        // List-table + view-infolist field labels for the neutral core.
        'columns' => [
            'name' => 'Name',
            'product_type' => 'Product type',
            'lifecycle_state' => 'Lifecycle state',
            'producer' => 'Producer',
            'version' => 'Version',
        ],

        // Create-form input labels + view-infolist labels for the neutral core and the WINE per-type
        // attribute set. `name`/`producer` are the create-form inputs; appellation/region/winery_story are
        // shared by the create form and the view infolist.
        'fields' => [
            'name' => 'Name',
            'producer' => 'Producer',
            'appellation' => 'Appellation',
            'region' => 'Region',
            'winery_story' => 'Winery story',
            'winery_story_help' => 'Optional. Captured in English, the baseline locale.',
            // The reject action's notes field (recorded on the audit row, never reverting state — § 4.3).
            'rejection_notes' => 'Rejection notes',
        ],

        // Create-page header link + write-through lifecycle action labels. Every action routes through a
        // Catalog domain action, never a Filament default mutating path (ADR 2026-06-19; design L2).
        'actions' => [
            'create' => 'New Product Master',
            'submit' => 'Submit for review',
            'reject' => 'Reject',
            'activate' => 'Activate',
            'retire' => 'Retire',
            'retire_cascade' => 'Retire (cascade)',
            'reopen' => 'Reopen',
        ],

        // The "second actor required" affordance (design L5/L6) — rendered as the activate confirmation copy.
        // The console SURFACES the Creator → Reviewer → Approver separation-of-duties floor (a distinct
        // approver), it never reimplements it (ApprovalGovernance is the sole authority); the copy reminds the
        // operator BEFORE they commit that the domain will reject a same-actor activation.
        'affordance' => [
            'second_actor' => 'Activation must be approved by a different operator than the one who created or reviewed this Product Master.',
            // Cascade-retire confirmation copy (design L7): warns that the operation reaches the whole active
            // subtree, distinguishing it from the single-entity Retire above.
            'cascade_warning' => 'Cascade retire also retires every active descendant of this Product Master — its variants, references, and SKUs — in one step. Use Retire to retire only the Product Master itself.',
        ],

        // Outcome notifications for the write-through lifecycle actions. The success titles confirm the
        // domain transition; `action_failed` is the danger title shown when the domain rejects a transition
        // (the rejection's own localized message — from lang/*/catalog.php — is rendered as the body).
        'notifications' => [
            'submitted' => 'Product Master submitted for review.',
            'rejected' => 'Rejection recorded; the Product Master stays under review.',
            'activated' => 'Product Master activated.',
            'retired' => 'Product Master retired.',
            'cascade_retired' => 'Product Master and its active descendants retired.',
            'reopened' => 'Product Master reopened for review.',
            'action_failed' => 'The action could not be completed.',
        ],

        // Shown for a producer that has no row in Catalog's producer-state projection yet
        // (a producer is only projected once Parties emits ProducerActivated/Retired — design D3).
        'producer_unprojected' => 'Not projected',
    ],

    // Format — a standalone PIM reference entity (a wine bottle size). No parent, no producer; activates
    // subject only to the approval governance (no cascade gate). Operator-console-catalog-spine, task 2.1.
    'format' => [
        // The canonical structural domain term — kept verbatim (CONTEXT.md), untranslated in IT.
        'label' => 'Format',
        'plural_label' => 'Formats',

        // List-table + view-infolist field labels.
        'columns' => [
            'name' => 'Name',
            'size_label' => 'Size label',
            'volume_ml' => 'Volume (ml)',
            'lifecycle_state' => 'Lifecycle state',
            'version' => 'Version',
        ],

        // Create-form input labels + the reject action's notes field (recorded on the audit row, never
        // reverting state — § 4.3).
        'fields' => [
            'name' => 'Name',
            'size_label' => 'Size label',
            'volume_ml' => 'Volume (ml)',
            'rejection_notes' => 'Rejection notes',
        ],

        // Create-page header link + write-through lifecycle action labels. Every action routes through a
        // Catalog domain action, never a Filament default mutating path (ADR 2026-06-19). Format is standalone
        // with no cascade-retire (Master-only, scope guard) — no `retire_cascade` key.
        'actions' => [
            'create' => 'New Format',
            'submit' => 'Submit for review',
            'reject' => 'Reject',
            'activate' => 'Activate',
            'retire' => 'Retire',
            'reopen' => 'Reopen',
        ],

        // The "second actor required" affordance — rendered as the activate confirmation copy. The console
        // SURFACES the Creator → Reviewer → Approver separation-of-duties floor (a distinct approver), it never
        // reimplements it (ApprovalGovernance is the sole authority).
        'affordance' => [
            'second_actor' => 'Activation must be approved by a different operator than the one who created or reviewed this Format.',
        ],

        // Outcome notifications for the write-through lifecycle actions. The success titles confirm the domain
        // transition; `action_failed` is the danger title shown when the domain rejects a transition (the
        // rejection's own localized message — from lang/*/catalog.php — is rendered as the body).
        'notifications' => [
            'submitted' => 'Format submitted for review.',
            'rejected' => 'Rejection recorded; the Format stays under review.',
            'activated' => 'Format activated.',
            'retired' => 'Format retired.',
            'reopened' => 'Format reopened for review.',
            'action_failed' => 'The action could not be completed.',
        ],
    ],

];
