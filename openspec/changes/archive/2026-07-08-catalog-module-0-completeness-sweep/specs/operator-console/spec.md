## ADDED Requirements

### Requirement: Operator edits catalog identity content through the console

The console SHALL surface the catalog identity-edit paths as **modal header actions on the existing View pages** — no Edit pages ship (the read-projection discipline stands, and every write routes through the domain Action): an **edit identity** action on the Product Master View (form prefilled with the current product name, appellation, region and winery story; invoking `UpdateProductMasterIdentity`) and an **edit composition** action on the Composite SKU View (the ordered constituent Product Reference set; invoking `UpdateCompositeSkuComposition`). Domain rejections SHALL be surfaced, never reimplemented: a BR-Identity-1 dedup collision SHALL surface as a **form validation error** on the edit form; the state guard (`retired`), the N ≥ 2 distinct-constituent floor and the active-Composite active-constituent condition SHALL be surfaced (validation error or notification) leaving the entity, audit log and event log unchanged. A successful edit SHALL notify the operator, and the View SHALL reflect the incremented `version`. Both actions SHALL run under the authenticated-operator envelope (`actor_role: newco_ops` with the acting operator's id on the audit record).

#### Scenario: A Master's identity is edited through the console

- **WHEN** an authenticated operator opens the edit-identity action on an `active` Product Master, changes the product name, and submits
- **THEN** `UpdateProductMasterIdentity` is invoked, the Master's `version` increments by one and is visible on the View, an audit record `catalog.product_master.identity_updated` carries the before/after with `actor_role: newco_ops` and the operator's id, no domain event is recorded, and a success notification is shown

#### Scenario: A dedup collision on edit is surfaced as a validation error

- **WHEN** the edit changes name/appellation into collision with another non-retired Master's identity key
- **THEN** the console surfaces a form validation error, and the Master's fields, `version`, audit log and event log are unchanged

#### Scenario: An invalid composition edit is surfaced without changing state

- **WHEN** an operator submits a composition edit on an `active` Composite SKU whose new set contains a non-`active` Product Reference (or fewer than two distinct constituents)
- **THEN** the domain rejection is surfaced to the operator, and the Composite's constituents, `version`, audit log and event log are unchanged

_Source: openspec/specs/product-catalog/spec.md (Identity Edit and Re-Versioning — this change) · spec/02-prd/Module_0_PRD_v0.3-MVP.md § 4.8, § 13.3 BR-Audit-1, § 2 (the Catalog Operator creates and edits PIM entities) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 4.3 AC-0-BR-Audit-1 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md § 1.3 (audit envelope), § 3.0 · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md · interview decisions 2026-07-08 (modal header actions on View pages; no Edit pages)._

### Requirement: Operator maintains Variant enrichment and the Layer-1 whitelist through the console

The Product Variant View SHALL surface two maintenance actions, both available while the Variant is `draft`, `reviewed` or `active` (the domain rejects `retired` and non-operator contexts; the console surfaces the rejection): an **edit enrichment** modal action (the tasting notes; invoking `UpdateProductVariantEnrichment`) — a real change records `EnrichmentDataUpdated` plus its audit record and notifies success, an identical value is a no-op, and the Variant's `version` never changes; and a **manage whitelist** modal action (replacing the admitted Case-Configuration set for a chosen Format; invoking the Layer-1 whitelist maintenance Action) — an audit-only write (before/after sets), recording no domain event and no `version` change, available on an `active` Variant (the J-13 reduction case). The console SHALL NOT reimplement the Sellable-SKU whitelist activation gate: it surfaces the domain's localized rejection when a blocked SKU activation is attempted.

#### Scenario: Enrichment is updated on an active Variant through the console

- **WHEN** an authenticated operator edits an `active` Variant's tasting notes to a new value through the console
- **THEN** `UpdateProductVariantEnrichment` is invoked, exactly one `EnrichmentDataUpdated` event and one `enrichment_updated` audit record are recorded with the operator envelope, the Variant's `version` is unchanged, and a success notification is shown

#### Scenario: The whitelist is reduced on an active Variant through the console

- **WHEN** an authenticated operator replaces a (Variant, Format) whitelist removing a previously-admitted Case Configuration
- **THEN** the whitelist maintenance Action is invoked, an audit record carries the before/after sets (no domain event, no `version` change), and a subsequent console attempt to activate a new Sellable SKU referencing the removed Case Configuration for that pair is rejected by the domain and surfaced as a notification naming the whitelist condition

_Source: openspec/specs/product-catalog/spec.md (Enrichment Data Update; Layer-1 Case-Configuration Whitelist — this change) · spec/02-prd/Module_0_PRD_v0.3-MVP.md § 14.1 last paragraph (EVT-8), § 7.1 (J-13 reduction on an active Variant), § 2 (the Catalog Operator maintains enrichment metadata) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 5 AC-0-EVT-8, § 2 AC-0-J-13, § 2 AC-0-J-11 (demo path) · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md · interview decisions 2026-07-08._

## MODIFIED Requirements

### Requirement: Operator creates a Product Master through the console

The console SHALL let an operator create a Product Master via the **manual baseline path** (the LWIN/Liv-ex enrichment adapter is not shipped), invoking `CreateProductMaster`. The type-defined identity-key collision (for `WINE`: producer + product name + appellation) SHALL be surfaced to the operator as a validation error rather than an unhandled exception. A `producer_id` unknown to the Catalog producer-state projection SHALL likewise be surfaced as a **form validation error**: the create form's producer selector is populated from the projection (which, with the widened projection, lists `registered`, `active` and `retired` producers), and the domain existence guard is the backstop behind it. A created Master SHALL be in `draft`.

#### Scenario: Valid input creates a draft Master

- **WHEN** an operator submits a valid Product Master (name, producer, appellation, region) through the create surface
- **THEN** `CreateProductMaster` is invoked, a Master exists in `draft`, and `ProductMasterCreated` is recorded with `actor_role: newco_ops`

#### Scenario: Duplicate identity key is surfaced as a validation error

- **WHEN** the submitted (producer + name + appellation) collides with an existing non-retired Master
- **THEN** the create is rejected and surfaced as a form validation error
- **AND** no Master is created and no `ProductMasterCreated` event is recorded

#### Scenario: An unknown producer is surfaced as a validation error

- **WHEN** the submitted producer reference has no producer-state projection row (e.g. a stale form value)
- **THEN** the create is rejected by the domain and surfaced as a form validation error
- **AND** no Master is created and no `ProductMasterCreated` event is recorded

_Source: spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.0; spec/03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md AC-AP-INV-0; openspec/specs/product-catalog/spec.md (Product Master, BR-Identity-1; the creation-time producer-existence guard — this change); spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 6.1 AC-0-XM-2; interview decisions 2026-07-08._

### Requirement: Operator advances a Product Master through the review-and-approval lifecycle

The console SHALL surface submit-for-review, reject, **re-submit** and activate for a Product Master, each invoking the corresponding domain action (`SubmitProductMasterForReview`, `RejectProductMasterReview`, `ResubmitProductMasterForReview`, `ActivateProductMaster`). The re-submit action SHALL be **visibility-gated to a review-stale Master** (rejection-pending, or identity-edited while in `reviewed`). The activation step SHALL present a **"second actor required"** affordance. The Creator → Reviewer → Approver separation-of-duties floor (distinct actors; self-approval never allowed) and the review-freshness block-gate are enforced by the domain (`ApprovalGovernance`); a same-actor violation — or an activation blocked by the review-freshness condition — SHALL be rejected by the domain and surfaced to the operator as a notification. The console SHALL NOT reimplement the floor or the condition.

#### Scenario: Distinct actors complete the lifecycle

- **WHEN** operator A submits a draft Master for review and a distinct operator B activates it (with the producer active)
- **THEN** the Master becomes `active` and `ProductMasterActivated` is recorded

#### Scenario: Self-approval is rejected and surfaced

- **WHEN** the operator who performed the prior step attempts the next governance step
- **THEN** the domain rejects it, the Master is unchanged, no `ProductMasterActivated` is recorded, and the console shows a notification that a distinct actor is required

#### Scenario: Rejection keeps the Master in reviewed with notes

- **WHEN** an operator rejects a `reviewed` Master with notes
- **THEN** the Master stays `reviewed`, the rejection and notes are recorded (audit-only), and no activation event is recorded

#### Scenario: A rejected Master is re-submitted through the console and then activated

- **WHEN** a `reviewed` Master is rejected, the re-submit action (visible on the review-stale Master) is invoked by an operator, and a distinct approver then activates it
- **THEN** the re-submit records an audit-only `resubmitted` decision (no domain event), the blocked activation is re-enabled, and the final activation succeeds recording `ProductMasterActivated`

_Source: spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §5.2; spec/03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md AC-AP-MA-1; openspec/specs/product-catalog/spec.md (Approval Governance — incl. the review-freshness condition and explicit re-submit); openspec/changes/archive/2026-07-02-catalog-review-freshness-resubmit (the console re-submit surface shipped by RM-06 — truth-spec sync folded into this change per the F4 precedent); interview decisions 2026-07-08._

### Requirement: Operator advances each catalog spine entity through the review-and-approval lifecycle

For each of the six spine entities the console SHALL surface submit-for-review, reject, **re-submit** and activate, each invoking the corresponding domain Action (`Submit<Entity>ForReview`, `Reject<Entity>Review`, `Resubmit<Entity>ForReview`, `Activate<Entity>`). Submit (`draft → reviewed`), reject (a Reviewer/Approver decision that keeps the entity in `reviewed`, recording the notes) and re-submit (the audit-only twin of reject, **visibility-gated to a review-stale entity**) are **audit-only** (no domain event); activate (`reviewed → active`) records the entity's verbatim `*Activated` event. The activate Action SHALL present a **"second actor required"** affordance. The Creator → Reviewer → Approver separation-of-duties floor (distinct actors; self-approval never allowed) and the review-freshness block-gate are enforced by the domain (`ApprovalGovernance`); a same-actor (or non-operator) violation — or an activation blocked by the review-freshness condition — SHALL be rejected by the domain, surfaced to the operator as a notification, and leave the entity, the audit log and the domain-event log unchanged. The console SHALL NOT reimplement the floor or the condition, and SHALL surface an out-of-state transition (e.g. activate on a `draft`) as a notification without changing state.

#### Scenario: Distinct actors complete submit then activate

- **WHEN** operator A submits a `draft` spine entity for review and a distinct operator B activates the `reviewed` entity (with any parent gate satisfied)
- **THEN** the entity becomes `active` and exactly one `*Activated` event is recorded with `actor_role: newco_ops` and `actor_id` equal to operator B

#### Scenario: Self-approval is rejected and surfaced

- **WHEN** the operator who performed the prior governance step attempts the next governance step on the same entity
- **THEN** the domain rejects it, the entity is unchanged, no `*Activated` event is recorded, and the console shows a notification that a distinct actor is required

#### Scenario: Rejection keeps the entity in reviewed with notes

- **WHEN** an operator rejects a `reviewed` spine entity with notes
- **THEN** the entity stays `reviewed`, the rejection and notes are recorded (audit-only), and no `*Activated` event is recorded

#### Scenario: A rejected spine entity is re-submitted through the console

- **WHEN** a `reviewed` spine entity is rejected and an operator invokes the re-submit action (visible on the review-stale entity)
- **THEN** an audit-only `resubmitted` decision is recorded (no domain event), the entity stays `reviewed`, and a distinct approver can then activate it

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator invokes a transition on an entity not in its required from-state (e.g. activate on a `draft`)
- **THEN** the domain raises an `IllegalLifecycleTransition`, the console surfaces it as a notification, and the entity's `lifecycle_state`, audit log and domain-event log are unchanged

_Source: openspec/specs/product-catalog/spec.md (Product Lifecycle State Machine; Approval Governance — incl. the review-freshness condition and explicit re-submit; Product Lifecycle Events) · spec/02-prd/Module_0_PRD_v0.3-MVP.md §4.1, §4.2 (Creator → Reviewer → Approver; self-approval never allowed; review checkpoint audit-only), §4.3 (rejection stays in reviewed), §13.2 BR-Lifecycle-1/2/6, §14.2 · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md §3 AC-0-FSM-1/8/9 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §5.2 · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md (SoD surfaced, not reimplemented) · openspec/changes/archive/2026-07-02-catalog-review-freshness-resubmit (the console re-submit surface shipped by RM-06 — truth-spec sync folded into this change per the F4 precedent) · app/Modules/Catalog/Actions/Submit*ForReview.php, Reject*Review.php, Resubmit*ForReview.php, Activate*.php · lang/en/catalog.php (`lifecycle.*`, `approval.*`) · interview decisions 2026-07-08._

