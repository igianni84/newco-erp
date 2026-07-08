<?php

// Operator console UI copy (operator-console-catalog-master, task 2.1; design L8; invariant 12).
// The static UI chrome of the OperatorPanel Filament surface — resolved with __('operator_console.…').
// EN is the baseline; lang/it/operator_console.php may be partial and falls back per-key to EN (DEC-127).
// Domain rejection messages are NOT here — they live in lang/{en,it}/catalog.php and are reused where an
// invoked Catalog action's exception message is surfaced (tasks 3–5).
return array_replace_recursive([

    // Sidebar navigation groups — one per spec module surfaced as a console — resolved by
    // OperatorConsoleNavigationGroup::getLabel(). Unlike the English-invariant entity labels below (Product
    // Master, Customer…), the module group names "Catalog"/"Parties" DO localize, so both are authored here as
    // the EN baseline and translated in lang/it (DEC-127 per-key fallback still applies).
    'navigation_group' => [
        'catalog' => 'Catalog',
        'parties' => 'Parties',
    ],

    // Shared branded empty state for every console list (OperatorConsoleResource::applyConsoleDefaults()).
    'empty' => [
        'heading' => 'Nothing here yet',
        'description' => 'No records match the current filters. Adjust the filters or create the first record.',
    ],

    // The em-dash shown for an empty/optional value in tables and infolists (a localized copy sink, invariant 12).
    'placeholder_none' => '—',

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

        // View-page section headings — the premium grouped infolist (operator-console UI pass, 2026-06-24).
        'sections' => [
            'identity' => 'Identity',
            'classification' => 'Classification & state',
            'provenance' => 'Provenance & story',
            'variants' => 'Variants',
            'metadata' => 'Metadata',
        ],

        // Create-form input labels + view-infolist labels for the neutral core and the WINE per-type
        // attribute set. `name`/`producer` are the create-form inputs; appellation/region/winery_story are
        // shared by the create form and the view infolist.
        'fields' => [
            'name' => 'Name',
            'producer' => 'Producer',
            'country' => 'Country',
            'appellation' => 'Appellation',
            'appellation_help' => 'Suggestions vary by region — free text is allowed for new appellations.',
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
            // Re-submit RE-ARMS review after a rejection (RM-06); offered only while rejection-pending.
            'resubmit' => 'Re-submit for review',
            'activate' => 'Activate',
            'retire' => 'Retire',
            'retire_cascade' => 'Retire (cascade)',
            'reopen' => 'Reopen',
            // The one field-edit surface (catalog-module-0-completeness-sweep, task 6.1): a modal over the four
            // review-governed identity fields, routed through UpdateProductMasterIdentity — never an Edit page.
            'edit_identity' => 'Edit identity',
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
            'resubmitted' => 'Re-submitted for review; the Product Master is ready for approval again.',
            'activated' => 'Product Master activated.',
            'retired' => 'Product Master retired.',
            'cascade_retired' => 'Product Master and its active descendants retired.',
            'reopened' => 'Product Master reopened for review.',
            'action_failed' => 'The action could not be completed.',
            // The identity-edit success title. A domain rejection on that path is NOT `action_failed`: it
            // surfaces as a validation error on the modal's own field, carrying the action's localized message.
            'identity_updated' => 'Product Master identity updated; a new version was recorded.',
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
            // Re-submit RE-ARMS review after a rejection (RM-06); offered only while rejection-pending.
            'resubmit' => 'Re-submit for review',
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
            'resubmitted' => 'Re-submitted for review; the Format is ready for approval again.',
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
            // Re-submit RE-ARMS review after a rejection (RM-06); offered only while rejection-pending.
            'resubmit' => 'Re-submit for review',
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
            'resubmitted' => 'Re-submitted for review; the Case Configuration is ready for approval again.',
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
        // and the view infolist). The `whitelist_*` trio is the manage-whitelist modal's two operands
        // (catalog-module-0-completeness-sweep, task 6.2): the Format that names the pair, and the admitted
        // Case-Configuration set replaced for it — whose EMPTINESS is meaningful (§ 7.1's permissive default:
        // absence admits, presence narrows), hence the help text.
        'fields' => [
            'product_master' => 'Product Master',
            'variant_identifier' => 'Variant identifier',
            'vintage_year' => 'Vintage year',
            'non_vintage' => 'Non-vintage',
            'tasting_notes' => 'Tasting notes',
            'tasting_notes_help' => 'Optional. Captured in English, the baseline locale.',
            'rejection_notes' => 'Rejection notes',
            'whitelist_format' => 'Format',
            'whitelist_case_configurations' => 'Admitted Case Configurations',
            'whitelist_case_configurations_help' => 'Replaces the admitted set for the chosen Format. Leave it empty to admit every Case Configuration again.',
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
            // Re-submit RE-ARMS review after a rejection (RM-06); offered only while rejection-pending.
            'resubmit' => 'Re-submit for review',
            'activate' => 'Activate',
            'retire' => 'Retire',
            'reopen' => 'Reopen',
            // The Variant's two MAINTENANCE surfaces (task 6.2): modal write-throughs over
            // UpdateProductVariantEnrichment / SetVariantCaseWhitelist — never an Edit page. Neither moves
            // `version` nor re-arms review, which is what distinguishes them from a Master's identity edit.
            'edit_enrichment' => 'Edit tasting notes',
            'manage_whitelist' => 'Manage case whitelist',
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
            'resubmitted' => 'Re-submitted for review; the Product Variant is ready for approval again.',
            'activated' => 'Product Variant activated.',
            'retired' => 'Product Variant retired.',
            'reopened' => 'Product Variant reopened for review.',
            'action_failed' => 'The action could not be completed.',
            // The two maintenance modals (task 6.2). Neither claims a version bump or a review re-arm — an
            // enrichment edit carrying the value already stored is a silent domain no-op, and the operator's
            // request still succeeded, so this title is honest for both outcomes.
            'enrichment_updated' => 'Tasting notes saved.',
            'whitelist_updated' => 'Case whitelist saved for this Format.',
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
            // Re-submit RE-ARMS review after a rejection (RM-06); offered only while rejection-pending.
            'resubmit' => 'Re-submit for review',
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
            'resubmitted' => 'Re-submitted for review; the Product Reference is ready for approval again.',
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
            // Re-submit RE-ARMS review after a rejection (RM-06); offered only while rejection-pending.
            'resubmit' => 'Re-submit for review',
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
            'resubmitted' => 'Re-submitted for review; the Sellable SKU is ready for approval again.',
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
            // Re-submit RE-ARMS review after a rejection OR a composition edit (RM-06 + its edit leg); offered
            // only while the Composite is review-stale.
            'resubmit' => 'Re-submit for review',
            'activate' => 'Activate',
            'retire' => 'Retire',
            'reopen' => 'Reopen',
            // The composition-edit modal (catalog-module-0-completeness-sweep task 6.3). A Composite is
            // attribute-free beyond its ordered constituent set (§3.8), so that set IS its identity: this is the
            // entity's identity-edit surface, the twin of the Master's `edit_identity`.
            'edit_composition' => 'Edit composition',
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
            'resubmitted' => 'Re-submitted for review; the Composite SKU is ready for approval again.',
            'activated' => 'Composite SKU activated.',
            'retired' => 'Composite SKU retired.',
            'reopened' => 'Composite SKU reopened for review.',
            'action_failed' => 'The action could not be completed.',
            // The composition edit re-versions the Composite (its constituent set is its identity) — the success
            // title says the bundle was saved; the incremented `version` is visible on the view.
            'composition_updated' => 'Composition saved.',
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
        // domain action, never a Filament default mutating path (ADR 2026-06-19). Producer activation now carries a
        // separation-of-duties floor (a distinct operator-principal approves, never the creator — RM-08,
        // parties-producer-approval-sod) LAYERED ON the KYC gate, so `activate` surfaces the `affordance.second_actor`
        // confirmation cue (below) while the other five verbs carry no modal. The KYC verbs are audit-only
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

        // The "second actor required" cue on `activate` — Producer activation is separation-of-duties-gated
        // (a distinct operator-principal must approve, never the Producer's creator; RM-08,
        // parties-producer-approval-sod), so the console surfaces this confirmation modal exactly as the catalog
        // consoles do. Producer's status FSM is linear (no reviewer leg), so the copy names only the creator.
        'affordance' => [
            'second_actor' => 'Activation must be approved by a different operator than the one who created this Producer.',
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
        // both are present — D11); generates_credit is the single-tier-at-launch flag (also a view-infolist
        // label). The per-Club `fee` (Money) is view-only. The create form exposes NO `status` — a Club is born
        // `active` (design D9).
        'fields' => [
            'display_name' => 'Name',
            'producer' => 'Operating producer',
            'registration_flow_type' => 'Registration flow',
            'amount' => 'Fee amount (minor units)',
            'currency' => 'Fee currency',
            'fee' => 'Fee',
            'generates_credit' => 'Generates credit',
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

    'producer_agreement' => [
        // The NewCo↔Producer commercial agreement (§ 4.6). `label`/`plural_label` are the model labels the kit
        // base resolves off i18nKey(); IT omits them (per-key EN fallback, DEC-127).
        'label' => 'Producer agreement',
        'plural_label' => 'Producer agreements',

        // List-table + view-infolist field labels. `producer` is the required Producer (a within-Parties read);
        // `club` is the OPTIONAL narrowing Club — when null the column renders the `producer_wide` placeholder;
        // `status` is the agreement lifecycle FSM; `term_start`/`term_end` are the agreement term dates;
        // `version` is the optimistic lock.
        'columns' => [
            'producer' => 'Producer',
            'club' => 'Club',
            'status' => 'Status',
            'term_start' => 'Term start',
            'term_end' => 'Term end',
            'version' => 'Version',
        ],

        // The placeholder the `club` column/entry renders for a Producer-wide agreement (a null `club_id`).
        'producer_wide' => 'Producer-wide',

        // View-infolist labels for the attributes the list omits + the create-form input labels. The form (task
        // 8.1) uses `fields.*` for every input: `producer` is the required Producer party; `club` is the OPTIONAL
        // narrowing (blank = a Producer-wide agreement, § 4.6); `term_start`/`term_end` are the OPTIONAL agreement
        // term dates; `settlement_cadence` is the view-only D19 settlement-cadence seam (also a create input). The
        // create form exposes NO `status` — an agreement is born `draft` (design D7).
        'fields' => [
            'producer' => 'Producer',
            'club' => 'Scoped Club',
            'term_start' => 'Term start',
            'term_end' => 'Term end',
            'settlement_cadence' => 'Settlement cadence',
        ],

        // Create-page header link + the write-through lifecycle action labels: the two STATUS verbs activate
        // (`draft → active`) and terminate (`active → terminated`), assembled on the ViewProducerAgreement page
        // (task 9.1). Both route through a Parties domain action, never a Filament default mutating path (ADR
        // 2026-06-19). There is NO supersede verb — supersession is the inline side-effect of activation (design
        // D8), not an operator action — and no separation-of-duties affordance: agreement lifecycle is
        // single-operator (no confirmation modal, design D3).
        'actions' => [
            'create' => 'New agreement',
            'activate' => 'Activate',
            'terminate' => 'Terminate',
        ],

        // Outcome notifications for the write-through lifecycle actions. The success titles confirm the domain
        // transition; `action_failed` is the danger title shown when the domain rejects a transition (e.g. an
        // out-of-state activate/terminate — activate is reachable only from `draft`, terminate only from
        // `active`) — its own localized message (from lang/*/parties.php) is rendered as the body, so the console
        // owns only this title, never the per-rejection copy (design D5).
        'notifications' => [
            'activated' => 'Producer agreement activated.',
            'terminated' => 'Producer agreement terminated.',
            'action_failed' => 'The action could not be completed.',
        ],
    ],

    // Customer — NewCo's natural-person registry (Module K § 4.1). The FIRST DEMAND-SIDE Parties operator
    // console (operator-console-parties-customer), the "least kit-shaped surface" the supply-side trilogy
    // deferred to. It carries THREE orthogonal lifecycles on one record — the status FSM (`status`), the KYC
    // lifecycle (`kyc_status`) and the sanctions lifecycle (`sanctions_status`) — each a self-owned badge
    // rendered through its BackedEnum cast (NOT the catalog `lifecycle_state`, design D2), plus the
    // co-provisioned Account status and the Club-membership Profiles, all read-only. A Customer is born `pending`
    // (no `status` create input); the create surface CONSTRUCTS the platform `Currency`/`SupportedLocale`
    // operands (no `Parties\Enums` import — design D6). The four status verbs activate/suspend/reactivate/close
    // are assembled on the ViewCustomer page (task 3.1).
    'customer' => [
        // The canonical structural domain term — kept verbatim (CONTEXT.md).
        'label' => 'Customer',
        'plural_label' => 'Customers',

        // List-table + view-infolist field labels. `status` / `kyc_status` / `sanctions_status` are the three
        // orthogonal lifecycles (each a self-owned badge via the cast); `account_status` is the co-provisioned
        // Account's state (a within-Parties read); `profiles` is the Club-membership count (list) / list
        // (infolist); `version` is the optimistic lock.
        'columns' => [
            'name' => 'Name',
            'email' => 'Email',
            'status' => 'Status',
            'kyc_status' => 'KYC status',
            'sanctions_status' => 'Sanctions status',
            'account_status' => 'Account status',
            'profiles' => 'Profiles',
            'version' => 'Version',
        ],

        // View-infolist labels for the personal-data attributes the list omits + (from task 2.1) the create-form
        // input labels. The form uses `fields.*` for every input: phone/date_of_birth are the OPTIONAL
        // personal-data inputs (also view-infolist labels); preferred_currency/preferred_locale are the required
        // ISO 4217 / locale preference Selects (also view-infolist labels). email/name re-use `columns.*` on the
        // view infolist and gain their `fields.*` create-input labels with the create form (task 2.2). The create
        // form exposes NO `status` — a Customer is born `pending` (design D5).
        'fields' => [
            'email' => 'Email',
            'name' => 'Name',
            'phone' => 'Phone',
            'date_of_birth' => 'Date of birth',
            'preferred_currency' => 'Preferred currency',
            'preferred_locale' => 'Preferred locale',
            // The place-Hold / lift-Hold form inputs (operator-console-parties-holds, tasks 3.1/4.1). `hold_type`
            // selects over HoldType::cases(), `hold_scope` over HoldScope::cases(); `profile` is the Profile
            // picker shown only for a profile-scope Hold; `reason` is the optional place-Hold note and
            // `lift_reason` the optional lift note (controlled business strings, never PII — Hold model design L5).
            'hold_type' => 'Hold type',
            'hold_scope' => 'Hold scope',
            'profile' => 'Profile',
            'reason' => 'Reason',
            'lift_reason' => 'Lift reason',
            // The sanctions-screening form inputs (operator-console-parties-kyc-sanctions, task 3.1).
            // `screening_verdict` labels the verdict Select over SanctionsStatus::cases(); `screening_source`
            // labels the trigger-source Select, whose options are record-dependent — {onboarding,
            // compliance_ad_hoc}, `onboarding` only on a never-screened Customer (design D6). The three KYC verbs
            // are form-less (no inputs).
            'screening_verdict' => 'Screening verdict',
            'screening_source' => 'Trigger source',
        ],

        // Create-page header link + the four write-through status-FSM verb labels on the ViewCustomer page
        // (activate / suspend / reactivate / close — the manual path, design D4), plus the two Hold-surface verbs
        // (operator-console-parties-holds): `place_hold` (the ViewCustomer header action, task 3.1) and
        // `lift_hold` (the per-row action on the Holds table, task 4.1). Each routes through a Parties domain
        // action (the status verbs + PlaceHold / LiftHold), never a Filament default mutating path (ADR
        // 2026-06-19; design D1).
        'actions' => [
            'create' => 'New Customer',
            'activate' => 'Activate',
            'suspend' => 'Suspend',
            'reactivate' => 'Reactivate',
            'close' => 'Close',
            'place_hold' => 'Place hold',
            'lift_hold' => 'Lift hold',
            // The KYC + sanctions write-through verbs (operator-console-parties-kyc-sanctions, tasks 2.1/3.1):
            // three form-less, visibility-gated KYC verbs — `require_kyc` (RequireKyc auto-places a `kyc` Hold +
            // suspends), `record_kyc_verified` (RecordKycVerified auto-lifts + reactivates), `record_kyc_rejected`
            // (RecordKycRejected, audit-only) — design D2/D4/D7, plus the one form-bearing `record_screening`
            // (RecordCustomerScreening). No Customer KYC waive verb exists (design D8). Each routes through a
            // Parties domain action, never a Filament default mutating path (ADR 2026-06-19).
            'require_kyc' => 'Require KYC',
            'record_kyc_verified' => 'Record KYC verified',
            'record_kyc_rejected' => 'Record KYC rejected',
            'record_screening' => 'Record sanctions screening',
            // The Account status-FSM verbs (operator-console-parties-membership, task 6.1) — three form-less,
            // visibility-gated verbs on ViewCustomer, each routing through a Parties Account action by the
            // co-provisioned 1:1 Account id: `suspend_account` (SuspendAccount, `active → suspended`),
            // `reactivate_account` (ReactivateAccount, `suspended → active` — the Account's only `→ active` edge;
            // there is no ActivateAccount, born active), `close_account` (CloseAccount, `active|suspended → closed`,
            // terminal). All three are AUDIT-ONLY (§ 15 names no Account event — design L8) and orthogonal to the
            // Customer status FSM (AC-K-FSM-9). Each routes through a Parties domain action, never a Filament default.
            'suspend_account' => 'Suspend account',
            'reactivate_account' => 'Reactivate account',
            'close_account' => 'Close account',
            // The GDPR data-rights verbs (parties-anonymisation, task 6.1) — the demand-side right-to-erasure +
            // right-of-access surface on ViewCustomer. `anonymise` is a form-less write-through to AnonymiseCustomer,
            // VISIBILITY-GATED to a not-yet-anonymised Customer (hidden once `anonymised_at` is set — the idempotency
            // gate); a `compliance`-Hold block is a RUNTIME rejection (not a visibility gate — unlike the KYC verbs),
            // surfaced as `action_failed`. `export` is a form-less write-through to the read-only ExportCustomerData
            // (ungated — an anonymised Customer still exports its placeholder PII). Each routes through a Parties domain
            // action, never a Filament default mutating path (ADR 2026-06-19).
            'anonymise' => 'Anonymise (erase PII)',
            'export' => 'Export data',
        ],

        // Outcome notifications for the four write-through status verbs + the two Hold-surface verbs
        // (operator-console-parties-holds). The success titles confirm the domain transition; `hold_placed` /
        // `hold_lifted` confirm the place / lift; `action_failed` is the shared danger title shown when the domain
        // rejects ANY of these — an out-of-state call, the cross-slice activation gate not yet met, or an illegal
        // Hold lift (design D5) — with the rejection's own localized message (from lang/*/parties.php) as the body.
        'notifications' => [
            'activated' => 'Customer activated.',
            'suspended' => 'Customer suspended.',
            'reactivated' => 'Customer reactivated.',
            'closed' => 'Customer closed.',
            'hold_placed' => 'Hold placed on the customer.',
            'hold_lifted' => 'Hold lifted from the customer.',
            // The KYC + sanctions success titles (operator-console-parties-kyc-sanctions, tasks 2/3):
            // `kyc_required` (the customer is suspended + a `kyc` Hold placed), `kyc_verified` (the Hold lifted +
            // the customer reactivated), `kyc_rejected` (the customer stays on hold), `screening_recorded` (the
            // screening logged). The shared `action_failed` below covers their domain rejections too (design D5/D7).
            'kyc_required' => 'KYC required; the customer is suspended pending verification.',
            'kyc_verified' => 'KYC verified; the customer is reactivated.',
            'kyc_rejected' => 'KYC rejected; the customer stays on hold.',
            'screening_recorded' => 'Sanctions screening recorded.',
            // The Account status-FSM success titles (operator-console-parties-membership, task 6.1) — the audit-only
            // Account transitions record no domain event. The shared `action_failed` below covers their domain
            // rejections too (an out-of-state Account transition — IllegalAccountTransition).
            'account_suspended' => 'Account suspended.',
            'account_reactivated' => 'Account reactivated.',
            'account_closed' => 'Account closed.',
            // The GDPR data-rights success titles (parties-anonymisation, task 6.1): `anonymised` confirms the PII
            // overwrite + `anonymised_at` stamp (one PII-free CustomerAnonymised event); `exported` confirms the
            // in-memory access export assembled (no file — the delivery vehicle is the deferred J-9b follow-up,
            // design D5). The shared `action_failed` below covers the anonymise compliance-Hold block.
            'anonymised' => 'Customer PII anonymised.',
            'exported' => 'Customer data export ready.',
            'action_failed' => 'The action could not be completed.',
        ],

        // The read-only Holds table on the ViewCustomer page (operator-console-parties-holds, tasks 2.1/4.1) — a
        // non-relation table sourced by a direct Hold::query() over the Customer's scope-set (customer ∪ Account ∪
        // Profiles). Terse column headers for the Hold registry: `hold_type` / `scope_type` / `status` render the
        // model's BackedEnum casts; `reason` is the place note; `placed_by` / `lifted_by` render the actor (role +
        // id); `placed_at` is the Hold's created_at and `lifted_at` its lift timestamp (null while active).
        'holds' => [
            'columns' => [
                'hold_type' => 'Type',
                'scope_type' => 'Scope',
                'status' => 'Status',
                'reason' => 'Reason',
                'placed_by' => 'Placed by',
                'placed_at' => 'Placed at',
                'lifted_by' => 'Lifted by',
                'lifted_at' => 'Lifted at',
            ],
        ],

        // The read-only enhanced-KYC & Compliance-review panel on the ViewCustomer page (change
        // parties-enhanced-kyc-threshold; SURFACED in task 6.1, front-loaded here in task 1.2 so the i18n contract is
        // green before the resolving code lands — the holds / kyc-sanctions precedent). Read-projection ONLY (no write
        // action — the review resolve action is deferred): `enhanced_kyc_flag` / `enhanced_kyc_at` label the Customer's
        // latched enhanced-KYC trigger (a boolean badge + its timestamp); `columns.*` head the Customer's OPEN
        // review-queue entries (`resolved_at IS NULL`). The `reason` / `threshold_kind` COLUMN HEADERS live here (console
        // chrome), while the VALUES in those columns render the domain enums via `parties.compliance_review.*` (the
        // Module-K domain copy — the enum backing values are domain vocabulary, not console chrome). `amount` is the
        // tripping Money; `opened_at` the entry's created_at. The section heading is `customer.sections.compliance_reviews`.
        'compliance_reviews' => [
            'enhanced_kyc_flag' => 'Enhanced KYC required',
            'enhanced_kyc_at' => 'Flagged at',
            'columns' => [
                'reason' => 'Reason',
                'threshold_kind' => 'Threshold',
                'amount' => 'Amount',
                'opened_at' => 'Opened at',
            ],
        ],
    ],

    // The demand-side MEMBERSHIP console (operator-console-parties-membership) — a standalone read-only
    // ProfileResource whose list is the cross-Customer approval queue (ProfileResource's i18nKey is `profile`).
    'profile' => [
        // The canonical structural domain term — kept verbatim (CONTEXT.md).
        'label' => 'Profile',
        'plural_label' => 'Profiles',

        // List-table + view-infolist column labels. `customer` renders the membership's Customer (email + name),
        // `club` its Club (display name); `state` is the 9-state membership-FSM badge (via the BackedEnum cast);
        // `version` is the optimistic lock.
        'columns' => [
            'customer' => 'Customer',
            'club' => 'Club',
            'state' => 'State',
            'version' => 'Version',
        ],

        // View-infolist labels for the demand-side lifecycle attributes the list omits + the create-form select
        // labels. `tier` is the single-tier-at-launch attribute (DEC-062); `lapsed_at` is the grace-window anchor
        // `LapseProfile` stamps; `cancellation_reason` is the optional Producer-initiated cancellation reason.
        // `customer` / `club` label the create-form selects (the membership's Customer and its Club). `auto_renew`
        // labels the ViewProfile auto-renew preference toggle (Profile-5, canon MVP-DEC-022).
        'fields' => [
            'tier' => 'Tier',
            'lapsed_at' => 'Lapsed at',
            'cancellation_reason' => 'Cancellation reason',
            'customer' => 'Customer',
            'club' => 'Club',
            'auto_renew' => 'Auto-renew',
        ],

        // The approval-queue tabs on the Profile list: "Pending" (the default — `applied` Profiles awaiting an
        // approve/decline decision) and "All" (every membership state).
        'tabs' => [
            'pending' => 'Pending',
            'all' => 'All',
        ],

        // Header / lifecycle action labels. `create` is the list-header link to the write-through create surface;
        // the membership lifecycle verbs append here across groups 3–5. Group 3 adds the approval pair: `approve`
        // (`applied → active` atomically — approve = charge = activation, MVP-DEC-016) and `decline`
        // (`applied → rejected`); group 4 adds the status verbs: `suspend` (`active → suspended`) and `reactivate`
        // (`suspended → active`); group 5 adds the lapse/renew/terminal verbs: `lapse` (`active → lapsed`), `renew`
        // (`lapsed → active` within grace), `cancel` (`active|lapsed → cancelled`, audit-only terminal) and
        // `deactivate` (`active → inactive`) — all form-less ViewProfile header verbs visibility-gated to their
        // from-state (design D4). The former `activate` verb is gone — approval reaches `active` in one transaction.
        // `set_auto_renew` is the one NON-lifecycle affordance: the auto-renew preference toggle (Profile-5), ungated
        // (settable in any state).
        'actions' => [
            'create' => 'New Profile',
            'approve' => 'Approve',
            'decline' => 'Decline',
            'suspend' => 'Suspend',
            'reactivate' => 'Reactivate',
            'lapse' => 'Lapse',
            'renew' => 'Renew',
            'cancel' => 'Cancel',
            'deactivate' => 'Deactivate',
            'set_auto_renew' => 'Set auto-renew',
        ],

        // Outcome notifications for the write-through membership verbs. The success titles confirm the domain
        // transition; `action_failed` is the shared danger title shown when the domain rejects a transition — its
        // own localized message (from lang/*/parties.php, e.g. the illegal-from-state text) is rendered as the body,
        // so the console owns only this title (design D5/D9). Group 3 adds `approved` (the membership application is
        // approved AND activated atomically — approve = charge = activation, MVP-DEC-016; the Originating Club locks
        // on the Customer's first-ever approval) and `declined` (the application is rejected, audit-only); group 4
        // adds `suspended` and `reactivated` (the status edges); group 5 adds `lapsed`, `renewed`, `cancelled` and
        // `deactivated` (the lapse/renew/terminal edges) — `action_failed` is reached through the UI only by a
        // past-grace `renew` (design D5).
        'notifications' => [
            'approved' => 'Membership approved and activated.',
            'declined' => 'Membership application declined.',
            'suspended' => 'Membership suspended.',
            'reactivated' => 'Membership reactivated.',
            'lapsed' => 'Membership lapsed.',
            'renewed' => 'Membership renewed.',
            'cancelled' => 'Membership cancelled.',
            'deactivated' => 'Membership deactivated.',
            'auto_renew_set' => 'Auto-renew preference updated.',
            'action_failed' => 'The action could not be completed.',
        ],
    ],

    // --- Operator-console UI pass (2026-06-24) — cluster / relation-manager / supplier / dashboard copy ---

    // The Catalog "Settings" cluster sidebar entry ({@see CatalogSettings}).
    'cluster' => [
        'catalog_settings' => 'Settings',
    ],

    // Relation-manager tab headings + inline create-action labels: child entities surfaced inside their parent's
    // view page (Variants in a Product Master; Clubs / Agreements in a Producer; Memberships in a Customer).
    'relations' => [
        'variants' => 'Variants',
        'create_variant' => 'New variant',
        'clubs' => 'Clubs',
        'create_club' => 'New club',
        'agreements' => 'Agreements',
        'create_agreement' => 'New agreement',
        'memberships' => 'Memberships',
    ],

    // Sidebar-label overrides distinct from the model label (Profile's nav entry reads "Memberships").
    'nav' => [
        'memberships' => 'Memberships',
    ],

    // Supplier — the thin commercial-counterpart Party subtype (§ 4.5). label / plural_label are EN-invariant
    // canonical terms (omitted from IT — per-key EN fallback, DEC-127).
    'supplier' => [
        'label' => 'Supplier',
        'plural_label' => 'Suppliers',
        'columns' => [
            'legal_name' => 'Legal name',
            'party_type' => 'Type',
            'created_at' => 'Created',
        ],
        'fields' => [
            'legal_name' => 'Legal name',
        ],
        'actions' => [
            'create' => 'New Supplier',
        ],
    ],

    // Dashboard analytics widgets (the {@see CatalogPartiesOverview} KPI band + the {@see MembershipsByStateChart}).
    'dashboard' => [
        'stats' => [
            'product_masters' => 'Product Masters',
            'sellable_skus' => 'Sellable SKUs',
            'producers' => 'Producers',
            'clubs' => 'Clubs',
            'customers' => 'Customers',
            'active_memberships' => 'Active memberships',
        ],
        'memberships_by_state' => [
            'heading' => 'Memberships by state',
            'dataset' => 'Memberships',
        ],
    ],

], [
    // === Premium UI pass (2026-06-24): infolist sections, table/column labels, form helpers and
    // human-label values added by the operator-console premium pass. Deep-merged onto the base copy
    // above via array_replace_recursive so the annotated base stays untouched. EN baseline / IT here.
    'product_variant' => [
        'sections' => [
            'identity' => 'Identity',
            'classification' => 'Classification & state',
            'attributes' => 'Vintage & attributes',
            'metadata' => 'Metadata',
        ],
        'fields' => [
            'description' => 'Description / Tasting notes',
        ],
    ],
    'product_reference' => [
        'columns' => [
            'master' => 'Wine Master',
            'format_size' => 'Bottle size',
        ],
        'sections' => [
            'composition' => 'Composition',
            'state' => 'State',
        ],
        'untitled' => 'Untitled',
        'values' => [
            'non_vintage' => 'NV',
        ],
    ],
    'sellable_sku' => [
        'sections' => [
            'identity' => 'Commercial identity',
            'composition' => 'Composition',
            'state' => 'State',
            'metadata' => 'Metadata',
        ],
        'placeholders' => [
            'no_marketing_copy' => 'No marketing copy',
        ],
        'non_vintage' => 'Non-vintage',
        'unnamed_reference' => 'Unnamed reference',
    ],
    'composite_sku' => [
        'columns' => [
            'bundle' => 'Bundle',
            'position' => 'Position',
            'reference' => 'Product Reference',
            'reference_state' => 'Reference state',
        ],
        'sections' => [
            'state' => 'State',
            'constituents' => 'Constituents',
            'metadata' => 'Metadata',
        ],
        'bundle_summary' => 'Bundle of :count — :first',
        'bundle_empty' => 'Empty bundle',
        'position_value' => 'Position :position',
        'non_vintage' => 'NV',
    ],
    'format' => [
        'sections' => [
            'identity' => 'Identity',
            'state' => 'State',
            'metadata' => 'Metadata',
        ],
    ],
    'case_configuration' => [
        'sections' => [
            'identity' => 'Identity',
            'packaging' => 'Packaging',
            'state' => 'State',
            'metadata' => 'Metadata',
        ],
    ],
    'producer' => [
        'sections' => [
            'identity' => 'Identity',
            'state' => 'State',
            'metadata' => 'Metadata',
        ],
    ],
    'club' => [
        'fields' => [
            'amount_help' => 'Amount in minor units (cents): enter 5000 for EUR 50.00.',
            'amount_prefix' => 'cents',
        ],
        'registration_flow' => [
            'open_registration' => 'Open registration',
            'application_with_approval' => 'Application with approval',
            'invitation_only' => 'Invitation only',
            'link_onboarding' => 'Link onboarding',
        ],
        'sections' => [
            'identity' => 'Identity',
            'membership' => 'Membership terms',
            'state' => 'State',
            'metadata' => 'Metadata',
        ],
        'columns' => [
            'generates_credit' => 'Generates credit',
        ],
        'values' => [
            'yes' => 'Yes',
            'no' => 'No',
            'no_fee' => 'No fee',
        ],
    ],
    'producer_agreement' => [
        'sections' => [
            'parties' => 'Parties',
            'terms' => 'Status & terms',
            'metadata' => 'Metadata',
        ],
        'fields' => [
            'club_help' => 'Leave blank for a Producer-wide agreement; select a Club to narrow it.',
            'settlement_cadence_help' => 'Optional. The settlement cadence Module E reads, from the closed set (default quarterly).',
        ],
        'not_set' => 'Not set',
    ],
    'customer' => [
        'sections' => [
            'identity' => 'Identity',
            'preferences' => 'Preferences',
            'compliance' => 'Compliance',
            // The read-only enhanced-KYC & Compliance-review section heading (change parties-enhanced-kyc-threshold,
            // task 6.1; front-loaded in task 1.2). Groups the enhanced-KYC flag/timestamp + the open review-queue
            // entries (`customer.compliance_reviews.*`). Kept a distinct section from `compliance` (the two orthogonal
            // KYC/sanctions badges) — this one carries the review-queue table.
            'compliance_reviews' => 'Enhanced KYC & compliance reviews',
            'state' => 'State',
            'metadata' => 'Metadata',
        ],
    ],
    'profile' => [
        'sections' => [
            'membership' => 'Membership',
            'status' => 'Status',
            'lifecycle' => 'Lifecycle',
            'metadata' => 'Metadata',
        ],
        'fields' => [
            'customer_name' => 'Name',
        ],
    ],
    'supplier' => [
        'sections' => [
            'identity' => 'Identity',
            'metadata' => 'Metadata',
        ],
        'columns' => [
            'updated_at' => 'Updated',
        ],
    ],
]);
