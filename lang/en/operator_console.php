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

    // Case Configuration — a standalone PIM reference entity (the packaging form of a Sellable SKU: units per
    // case + packaging type). No parent, no producer; activates subject only to the approval governance (no
    // cascade gate). It carries NO breakability attribute (BR-RefData-2) — that decision lives downstream in
    // Module A/S, so there is no breakability field. Operator-console-catalog-spine, task 2.2.
    'case_configuration' => [
        // The canonical structural domain term — kept verbatim (CONTEXT.md), untranslated in IT.
        'label' => 'Case Configuration',
        'plural_label' => 'Case Configurations',

        // List-table + view-infolist field labels.
        'columns' => [
            'name' => 'Name',
            'units_per_case' => 'Units per case',
            'packaging_type' => 'Packaging type',
            'lifecycle_state' => 'Lifecycle state',
            'version' => 'Version',
        ],

        // Create-form input labels + the reject action's notes field (recorded on the audit row, never
        // reverting state — § 4.3). No breakability input (BR-RefData-2).
        'fields' => [
            'name' => 'Name',
            'units_per_case' => 'Units per case',
            'packaging_type' => 'Packaging type',
            'rejection_notes' => 'Rejection notes',
        ],

        // Create-page header link + write-through lifecycle action labels. Every action routes through a
        // Catalog domain action, never a Filament default mutating path (ADR 2026-06-19). A Case Configuration
        // is standalone with no cascade-retire (Master-only, scope guard) — no `retire_cascade` key.
        'actions' => [
            'create' => 'New Case Configuration',
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
            'second_actor' => 'Activation must be approved by a different operator than the one who created or reviewed this Case Configuration.',
        ],

        // Outcome notifications for the write-through lifecycle actions. The success titles confirm the domain
        // transition; `action_failed` is the danger title shown when the domain rejects a transition (the
        // rejection's own localized message — from lang/*/catalog.php, incl. the retire reference-integrity
        // block — is rendered as the body).
        'notifications' => [
            'submitted' => 'Case Configuration submitted for review.',
            'rejected' => 'Rejection recorded; the Case Configuration stays under review.',
            'activated' => 'Case Configuration activated.',
            'retired' => 'Case Configuration retired.',
            'reopened' => 'Case Configuration reopened for review.',
            'action_failed' => 'The action could not be completed.',
        ],
    ],

    // Product Variant — the FIRST hierarchical PIM spine entity (a release of a Product Master; the parent of
    // every Product Reference). It binds exactly ONE parent Product Master (BR-Identity-2) — so its create form
    // carries a Master picker — and NO producer (design L6). Its activation is gated on the parent Master being
    // `active` (the within-catalog activation cascade); the console SURFACES that domain gate, it never
    // reimplements it (design L4) — so there is no cascade-specific copy here, only the shared
    // `notifications.action_failed` danger title (the gate's own body comes from lang/*/catalog.php). The WINE
    // variant axis is the vintage, held 1:1 off the neutral core. Operator-console-catalog-spine, task 3.1.
    'product_variant' => [
        // The canonical structural domain term — kept verbatim (CONTEXT.md), untranslated in IT.
        'label' => 'Product Variant',
        'plural_label' => 'Product Variants',

        // List-table + view-infolist field labels for the neutral core. `vintage` is the combined list column
        // (the year, or a "Non-vintage" marker); `master` is the parent Product Master.
        'columns' => [
            'variant_identifier' => 'Variant identifier',
            'master' => 'Product Master',
            'vintage' => 'Vintage',
            'lifecycle_state' => 'Lifecycle state',
            'version' => 'Version',
        ],

        // Create-form input labels + view-infolist labels for the WINE attribute set, plus the reject action's
        // notes field (recorded on the audit row, never reverting state — § 4.3). `product_master` is the parent
        // picker; vintage_year/non_vintage/tasting_notes are the wine attribute set (shared by the create form
        // and the view infolist).
        'fields' => [
            'product_master' => 'Product Master',
            'variant_identifier' => 'Variant identifier',
            'vintage_year' => 'Vintage year',
            'non_vintage' => 'Non-vintage',
            'tasting_notes' => 'Tasting notes',
            'tasting_notes_help' => 'Optional. Captured in English, the baseline locale.',
            'rejection_notes' => 'Rejection notes',
        ],

        // Rendered enum-like display values. `non_vintage` is the marker shown in the combined `vintage` column;
        // `yes`/`no` render the standalone non-vintage flag in the view infolist.
        'values' => [
            'non_vintage' => 'Non-vintage',
            'yes' => 'Yes',
            'no' => 'No',
        ],

        // Create-page header link + write-through lifecycle action labels. Every action routes through a Catalog
        // domain action, never a Filament default mutating path (ADR 2026-06-19). A Product Variant is a
        // hierarchical spine entity with no cascade-retire (Master-only, scope guard) — no `retire_cascade` key.
        'actions' => [
            'create' => 'New Product Variant',
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
            'second_actor' => 'Activation must be approved by a different operator than the one who created or reviewed this Product Variant.',
        ],

        // Outcome notifications for the write-through lifecycle actions. The success titles confirm the domain
        // transition; `action_failed` is the danger title shown when the domain rejects a transition (the
        // rejection's own localized message — from lang/*/catalog.php, incl. the activation-cascade gate
        // `gate.parent_not_active` — is rendered as the body).
        'notifications' => [
            'submitted' => 'Product Variant submitted for review.',
            'rejected' => 'Rejection recorded; the Product Variant stays under review.',
            'activated' => 'Product Variant activated.',
            'retired' => 'Product Variant retired.',
            'reopened' => 'Product Variant reopened for review.',
            'action_failed' => 'The action could not be completed.',
        ],
    ],

    // Product Reference — the atomic product KEY and the second hierarchical PIM spine entity (a Product Variant
    // + a Format, BR-Identity-3; §18 wine-display alias "Bottle Reference"). It binds exactly TWO within-catalog
    // parents — so its create form carries a Variant picker AND a Format picker — and NO producer (design L6).
    // Its `(variant, format)` pair is unique at the database; a duplicate carries no domain message, so the
    // console owns the `duplicate_reference` form-error copy (design L5 — the ONE console-owned key this change
    // adds). Its activation is gated on BOTH parents being `active` (the within-catalog activation cascade), and
    // its retire is blocked while an active Sellable / Composite SKU references it; the console SURFACES both
    // domain rejections, it never reimplements them (design L4) — so the only failure title here is the shared
    // `notifications.action_failed` (the gate / reference-integrity body comes from lang/*/catalog.php).
    // Operator-console-catalog-spine, task 3.2.
    'product_reference' => [
        // The canonical structural domain term — kept verbatim (CONTEXT.md), untranslated in IT.
        'label' => 'Product Reference',
        'plural_label' => 'Product References',

        // List-table + view-infolist field labels. `variant` / `format` are the two parent dimensions, rendered
        // off the within-Catalog variant() / format() relations.
        'columns' => [
            'variant' => 'Product Variant',
            'format' => 'Format',
            'lifecycle_state' => 'Lifecycle state',
            'version' => 'Version',
        ],

        // Create-form input labels (the two parent pickers) + the reject action's notes field (recorded on the
        // audit row, never reverting state — § 4.3).
        'fields' => [
            'product_variant' => 'Product Variant',
            'format' => 'Format',
            'rejection_notes' => 'Rejection notes',
        ],

        // The console-owned duplicate-pair form error (design L5). The PR's `(variant, format)` uniqueness is a
        // DB-structural rule with no localized domain message, so the console owns this copy — rendered on the
        // create form instead of the raw SQL UniqueConstraintViolationException. Kept colon-free so the test's
        // exact-message assertion is not truncated by Livewire's rule/message matcher.
        'duplicate_reference' => 'A Product Reference for this Product Variant and Format already exists. Each Variant and Format pair must be unique.',

        // Create-page header link + write-through lifecycle action labels. Every action routes through a Catalog
        // domain action, never a Filament default mutating path (ADR 2026-06-19). A Product Reference is a
        // hierarchical spine entity with no cascade-retire (Master-only, scope guard) — no `retire_cascade` key.
        'actions' => [
            'create' => 'New Product Reference',
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
            'second_actor' => 'Activation must be approved by a different operator than the one who created or reviewed this Product Reference.',
        ],

        // Outcome notifications for the write-through lifecycle actions. The success titles confirm the domain
        // transition; `action_failed` is the danger title shown when the domain rejects a transition (the
        // rejection's own localized message — from lang/*/catalog.php, incl. the activation-cascade gate
        // `gate.parent_not_active` and the retire reference-integrity block
        // `retirement.blocked_by_active_references` — is rendered as the body).
        'notifications' => [
            'submitted' => 'Product Reference submitted for review.',
            'rejected' => 'Rejection recorded; the Product Reference stays under review.',
            'activated' => 'Product Reference activated.',
            'retired' => 'Product Reference retired.',
            'reopened' => 'Product Reference reopened for review.',
            'action_failed' => 'The action could not be completed.',
        ],
    ],

    // Sellable SKU (Intrinsic) — the commercial unit and the third hierarchical PIM spine entity (one Product
    // Reference + one Case Configuration + commercial attributes, BR-SKU-1). It binds exactly TWO within-catalog
    // parents — so its create form carries a Product Reference picker AND a Case Configuration picker (plus the
    // commercial name + optional marketing copy) — and NO producer (design L6). Unlike the Product Reference it
    // has NO uniqueness rule (the same Product Reference + Case Configuration pair may back many SKUs), so there
    // is no duplicate form-error key. Its activation is gated on BOTH parents being `active` (the within-catalog
    // activation cascade); the console SURFACES that domain gate, it never reimplements it (design L4) — so the
    // only failure title here is the shared `notifications.action_failed` (the gate's own body comes from
    // lang/*/catalog.php). It is a LEAF (nothing within catalog references it), so retire carries no
    // reference-integrity block. Operator-console-catalog-spine, task 3.3.
    'sellable_sku' => [
        // The canonical structural domain term — kept verbatim (CONTEXT.md), untranslated in IT.
        'label' => 'Sellable SKU',
        'plural_label' => 'Sellable SKUs',

        // List-table + view-infolist field labels. `reference` / `case_configuration` are the two parent
        // dimensions, rendered off the within-Catalog reference() / caseConfiguration() relations.
        'columns' => [
            'reference' => 'Product Reference',
            'case_configuration' => 'Case Configuration',
            'commercial_name' => 'Commercial name',
            'lifecycle_state' => 'Lifecycle state',
            'version' => 'Version',
        ],

        // Create-form input labels (the two parent pickers + the commercial attributes) + the reject action's
        // notes field (recorded on the audit row, never reverting state — § 4.3). `marketing_copy` is an optional
        // SKU-level string (NOT translatable — §8.1 scopes translatable content to Master/Variant/PR).
        'fields' => [
            'product_reference' => 'Product Reference',
            'case_configuration' => 'Case Configuration',
            'commercial_name' => 'Commercial name',
            'marketing_copy' => 'Marketing copy',
            'rejection_notes' => 'Rejection notes',
        ],

        // Create-page header link + write-through lifecycle action labels. Every action routes through a Catalog
        // domain action, never a Filament default mutating path (ADR 2026-06-19). A Sellable SKU is a hierarchical
        // spine entity with no cascade-retire (Master-only, scope guard) — no `retire_cascade` key.
        'actions' => [
            'create' => 'New Sellable SKU',
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
            'second_actor' => 'Activation must be approved by a different operator than the one who created or reviewed this Sellable SKU.',
        ],

        // Outcome notifications for the write-through lifecycle actions. The success titles confirm the domain
        // transition; `action_failed` is the danger title shown when the domain rejects a transition (the
        // rejection's own localized message — from lang/*/catalog.php, incl. the activation-cascade gate
        // `gate.parent_not_active` — is rendered as the body).
        'notifications' => [
            'submitted' => 'Sellable SKU submitted for review.',
            'rejected' => 'Rejection recorded; the Sellable SKU stays under review.',
            'activated' => 'Sellable SKU activated.',
            'retired' => 'Sellable SKU retired.',
            'reopened' => 'Sellable SKU reopened for review.',
            'action_failed' => 'The action could not be completed.',
        ],
    ],

    // Composite SKU — the FINAL spine entity and the spine's only many-to-many entity (a curated bundle of N ≥ 2
    // ORDERED constituent Product References, BR-SKU-2). It binds NO parent FK and NO producer (the catalog is
    // PRODUCER-AGNOSTIC about constituents, design D9) — so its create form carries a single ORDERED, N≥2
    // Product-Reference multi-select picker, no producer picker. Its ONE create guard is the `< 2 distinct
    // constituents` floor, a localized domain rejection (catalog.composite_sku.insufficient_constituents) the
    // console surfaces as a form error via the kit base catch (design L5) — so there is no console-owned create
    // message here. Its activation is gated on EVERY constituent being `active` (the within-catalog activation
    // cascade); the console SURFACES that domain gate, it never reimplements it (design L4) — so the only failure
    // title here is the shared `notifications.action_failed` (the gate's own body comes from lang/*/catalog.php).
    // It is a LEAF (nothing within catalog references it), so retire carries no reference-integrity block.
    // Operator-console-catalog-spine, task 4.1.
    'composite_sku' => [
        // The canonical structural domain term — kept verbatim (CONTEXT.md), untranslated in IT.
        'label' => 'Composite SKU',
        'plural_label' => 'Composite SKUs',

        // List-table + view-infolist field labels. `constituent_count` is the bundle size (the N of the N ≥ 2
        // bundle), shown in the list and the view.
        'columns' => [
            'constituent_count' => 'Constituents',
            'lifecycle_state' => 'Lifecycle state',
            'version' => 'Version',
        ],

        // Create-form input labels (the single ordered constituents picker) + the reject action's notes field
        // (recorded on the audit row, never reverting state — § 4.3). `constituents` also labels the ordered
        // constituent list on the view infolist.
        'fields' => [
            'constituents' => 'Constituents',
            'constituents_help' => 'Select two or more Product References, in bundle order.',
            'rejection_notes' => 'Rejection notes',
        ],

        // Create-page header link + write-through lifecycle action labels. Every action routes through a Catalog
        // domain action, never a Filament default mutating path (ADR 2026-06-19). A Composite SKU is a spine
        // entity with no cascade-retire (Master-only, scope guard) — no `retire_cascade` key.
        'actions' => [
            'create' => 'New Composite SKU',
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
            'second_actor' => 'Activation must be approved by a different operator than the one who created or reviewed this Composite SKU.',
        ],

        // Outcome notifications for the write-through lifecycle actions. The success titles confirm the domain
        // transition; `action_failed` is the danger title shown when the domain rejects a transition (the
        // rejection's own localized message — from lang/*/catalog.php, incl. the activation-cascade gate
        // `gate.parent_not_active` — is rendered as the body).
        'notifications' => [
            'submitted' => 'Composite SKU submitted for review.',
            'rejected' => 'Rejection recorded; the Composite SKU stays under review.',
            'activated' => 'Composite SKU activated.',
            'retired' => 'Composite SKU retired.',
            'reopened' => 'Composite SKU reopened for review.',
            'action_failed' => 'The action could not be completed.',
        ],
    ],

    // Producer — the standalone winery-identity registry (Module K § 4.4), the source of the producer
    // reference Module 0's Product Master keys off. The FIRST Parties operator console
    // (operator-console-parties-producer). Its state is `status` (draft → active → retired) with a SEPARATE
    // provenance-KYC lifecycle in `kyc_status` — not the catalog `lifecycle_state` (design D2).
    'producer' => [
        // The canonical structural domain term — kept verbatim (CONTEXT.md).
        'label' => 'Producer',
        'plural_label' => 'Producers',

        // List-table + view-infolist field labels. `status` / `kyc_status` are the Producer's two FSMs (the
        // status lifecycle and the separate provenance-KYC lifecycle); `version` is the optimistic lock.
        'columns' => [
            'name' => 'Name',
            'region' => 'Region',
            'country' => 'Country',
            'status' => 'Status',
            'kyc_status' => 'KYC status',
            'version' => 'Version',
        ],

        // Create-form input labels + the view-infolist labels for the attributes the list omits.
        // name/region/country are required create inputs (also re-used as view labels via `columns.*`);
        // appellation/website/description are optional create inputs shared with the view infolist; `clubs` is
        // view-only (the operated-Clubs read). The create form exposes NEITHER `status` NOR `kyc_status` — a
        // Producer is born `draft` with no KYC and both FSMs advance only through the view-page actions (D6).
        'fields' => [
            'name' => 'Name',
            'region' => 'Region',
            'country' => 'Country',
            'appellation' => 'Appellation',
            'website' => 'Website',
            'description' => 'Description',
            'clubs' => 'Operated clubs',
        ],

        // Create-page header link + the write-through lifecycle action labels: the two STATUS verbs (task 3.1)
        // and the four KYC verbs require/waive/verify/reject (task 4.1). Every action routes through a Parties
        // domain action, never a Filament default mutating path (ADR 2026-06-19). Producer has NO
        // separation-of-duties activation (it is KYC-gated, not Creator→Reviewer→Approver), so there is no
        // `affordance` block and no verb carries a confirmation modal (design D3). The KYC verbs are audit-only
        // (the domain records no event and places no Hold); `waive` is the operator "deselect" of the KYC
        // requirement (any outstanding state → not_required, clearing the activation gate as if verified).
        'actions' => [
            'create' => 'New Producer',
            'activate' => 'Activate',
            'retire' => 'Retire',
            'require_kyc' => 'Require KYC',
            'waive_kyc' => 'Waive KYC',
            'verify_kyc' => 'Verify KYC',
            'reject_kyc' => 'Reject KYC',
        ],

        // Outcome notifications for the write-through lifecycle actions. The success titles confirm the domain
        // transition; `action_failed` is the danger title shown when the domain rejects a transition — its own
        // localized message (from lang/*/parties.php, e.g. the illegal-from-state, KYC-not-cleared or illegal
        // KYC-transition text) is rendered as the body, so the console owns only this title, never the
        // per-rejection copy (design D5). The four KYC success titles confirm the audit-only `kyc_status` move.
        'notifications' => [
            'activated' => 'Producer activated.',
            'retired' => 'Producer retired.',
            'kyc_required' => 'Producer KYC required.',
            'kyc_waived' => 'Producer KYC waived.',
            'kyc_verified' => 'Producer KYC verified.',
            'kyc_rejected' => 'Producer KYC rejected.',
            'action_failed' => 'The action could not be completed.',
        ],
    ],

    // Club — a Producer-operated membership program (Module K § 4.3). The SECOND Parties operator console
    // (operator-console-parties-supply-side). Its state is `status` (active → sunset → closed), a self-owned
    // badge column rendered through the cast — not the catalog `lifecycle_state` (design D2). A Club is born
    // `active` (no activate verb — D9); the create surface CONSTRUCTS the `registration_flow_type` operand enum
    // (the {Models, Actions, Enums} carve-out — D7).
    'club' => [
        // The canonical structural domain term — kept verbatim (CONTEXT.md).
        'label' => 'Club',
        'plural_label' => 'Clubs',

        // List-table + view-infolist field labels. `producer` is the operating Producer (a within-Parties read);
        // `registration_flow_type` is the fixed per-Club registration classifier; `status` is the Club lifecycle
        // FSM; `version` is the optimistic lock.
        'columns' => [
            'display_name' => 'Name',
            'producer' => 'Producer',
            'registration_flow_type' => 'Registration flow',
            'status' => 'Status',
            'version' => 'Version',
        ],

        // Create-form input labels + the view-infolist labels for the attributes the list omits. The form uses
        // `fields.*` for every input (mirroring the Producer console): display_name/producer/registration_flow_type
        // re-label the create inputs; amount/currency are the OPTIONAL fee inputs (assembled into a Money only when
        // both are present — D11); generates_credit/invite_only are the two single-tier flags (also view-infolist
        // labels). The per-Club `fee` (Money) is view-only. The create form exposes NO `status` — a Club is born
        // `active` (design D9).
        'fields' => [
            'display_name' => 'Name',
            'producer' => 'Operating producer',
            'registration_flow_type' => 'Registration flow',
            'amount' => 'Fee amount (minor units)',
            'currency' => 'Fee currency',
            'fee' => 'Fee',
            'generates_credit' => 'Generates credit',
            'invite_only' => 'Invite only',
        ],

        // Create-page header link + the write-through lifecycle action labels: the two STATUS verbs sunset
        // (`active → sunset`) and close (`sunset → closed`), assembled on the ViewClub page (task 4.1). Both route
        // through a Parties domain action, never a Filament default mutating path (ADR 2026-06-19). A Club is born
        // `active`, so there is NO activate verb (D9) and no separation-of-duties affordance — Club lifecycle is
        // single-operator (no confirmation modal, design D3).
        'actions' => [
            'create' => 'New Club',
            'sunset' => 'Sunset',
            'close' => 'Close',
        ],

        // Outcome notifications for the write-through lifecycle actions. The success titles confirm the domain
        // transition; `action_failed` is the danger title shown when the domain rejects a transition (e.g. an
        // out-of-state sunset/close, or a close attempted on an `active` Club — close is reachable only from
        // `sunset`) — its own localized message (from lang/*/parties.php) is rendered as the body, so the console
        // owns only this title, never the per-rejection copy (design D5).
        'notifications' => [
            'sunset' => 'Club sunset.',
            'closed' => 'Club closed.',
            'action_failed' => 'The action could not be completed.',
        ],
    ],

];
