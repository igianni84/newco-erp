# operator-console Specification

## Purpose
TBD - created by archiving change operator-console-catalog-master. Update Purpose after archive.
## Requirements
### Requirement: Console resources are read-projections; writes route through domain actions

The operator console SHALL bind its Filament resources **read-only** to the operated module's Eloquent models, and SHALL route **every** state change through that module's existing domain actions. No class under `App\Modules\OperatorPanel` SHALL perform an Eloquent write (`save`, `update`, `delete`, `forceDelete`, `create`, `insert`, or mass-assignment) on a module model. Default Filament mutating paths (implicit record save, `DeleteAction`) SHALL be overridden to invoke a domain action or removed.

This is the operator-console capability's foundational discipline (the OperatorPanel owns no entities — it operates the modules' entities) and applies to every console built on the capability.

#### Scenario: The architecture test forbids Eloquent writes in the OperatorPanel namespace

- **WHEN** any class under `App\Modules\OperatorPanel` calls an Eloquent write method on a module model
- **THEN** the architecture test fails (proven by a planted violation), and passes once the write is removed

#### Scenario: A console mutation invokes a domain action, never a direct model save

- **WHEN** an operator triggers a create or lifecycle action on a console resource
- **THEN** the corresponding module domain action is invoked
- **AND** no `$model->save()` / `update()` / `delete()` is executed in the resource, page, or action class
- **AND** the resource exposes no default `DeleteAction`

_Source: decisions/2026-06-19-operator-console-read-binding-write-through-actions.md; spec/02-prd/Architecture_v0.3-MVP.md §2.3; root CLAUDE.md Invariant 10._

### Requirement: Operator-driven console writes carry the actor_role audit envelope

Every state-changing operator action performed through the console SHALL record the standard audit envelope — `actor_role: newco_ops`, the operator identity, a timestamp, the action, and the entity reference — on the domain event or audit record that the invoked domain action drives. The envelope SHALL be obtained from the existing `ActorContext` seam (the authenticated `operator` guard), not constructed by console-specific code.

#### Scenario: An operator-driven write records newco_ops and the operator id

- **WHEN** an authenticated operator creates, activates, or retires a Product Master through the console
- **THEN** the resulting domain event (or audit record for an audit-only transition) carries `actor_role = newco_ops` and `actor_id` equal to the acting operator's id

_Source: spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §1.3; spec/03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md AC-AP-PWB-6; decisions/2026-06-15-identity-auth.md; root CLAUDE.md Invariant 8._

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

### Requirement: The console surfaces the Producer-activation gate

When an operator activates a Product Master whose linked Producer is not `active` in Catalog's producer-state projection, the domain SHALL reject the activation and the console SHALL surface the reason; activation SHALL succeed only when the linked Producer is `active`. The gate is read from Catalog's own `catalog_producer_states` projection, never from Module K.

#### Scenario: Activation is blocked when the producer is not active

- **WHEN** an operator activates a Master whose linked Producer is not `active` in the projection
- **THEN** the activation is rejected, the Master stays `reviewed`, no `ProductMasterActivated` is recorded, and the gate reason is surfaced to the operator

#### Scenario: Activation succeeds when the producer is active

- **WHEN** the linked Producer is `active` in the projection and a distinct operator activates the `reviewed` Master
- **THEN** the Master becomes `active` and `ProductMasterActivated` is recorded

_Source: openspec/specs/product-catalog/spec.md (Producer Activation Gate); spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.0._

### Requirement: Operator retires, cascade-retires, and reopens a Product Master

The console SHALL let an operator retire a Product Master (single-entity, preserving existing active children), run the operator-driven cascade retire (`RetireProductMasterCascade` — the Master plus its active descendants, parent-before-child), and reopen a retired Master to `reviewed` (`ReopenProductMaster`), each invoking the corresponding domain action.

#### Scenario: Operator-driven cascade retire

- **WHEN** an operator runs cascade retire on a Master with active descendants
- **THEN** the Master and its active descendants are retired in parent-before-child order, each recording its `*Retired` event

#### Scenario: Single-entity retire preserves existing active children

- **WHEN** an operator retires a Master (single-entity) that has active children
- **THEN** the Master becomes `retired`, its existing active children remain valid, and no new child may activate under it

#### Scenario: Reopen returns the Master to reviewed and re-checks the gate

- **WHEN** an operator reopens a `retired` Master
- **THEN** the Master returns to `reviewed` (audit-only, no event) and the Producer-activation gate is re-checked on the next activation

_Source: spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.0; openspec/specs/product-catalog/spec.md (Retirement Cascade and Reference Integrity); decisions/2026-06-16-catalog-retirement-reference-integrity-scope.md._

### Requirement: Operator console copy is localized in EN and IT

All operator-facing strings in the console (resource/field/column/action labels, validation and rejection messages, notifications) SHALL resolve through Laravel localization in **EN and IT**; no user-facing copy SHALL be hardcoded. A key missing in IT SHALL fall back to its EN value (per-key fallback).

#### Scenario: The console renders in the operator's locale

- **WHEN** the operator's locale is `it`
- **THEN** console labels and messages render in Italian

#### Scenario: A missing Italian key falls back to English

- **WHEN** a console copy key is absent in the `it` translations
- **THEN** the EN value is rendered in its place

_Source: spec/02-prd/Architecture_v0.3-MVP.md §5.1 (Admin Panel EN+IT); root CLAUDE.md Invariant 12._

### Requirement: Operator creates the standalone reference entities through the console

The console SHALL let an operator create the two standalone catalog reference entities — **Format** (invoking `CreateFormat`) and **Case Configuration** (invoking `CreateCaseConfiguration`) — through a manual create surface that collects each entity's structural attributes (Format: a name, a size label, and a volume in millilitres; Case Configuration: a name, units per case, and a packaging type). A created entity SHALL be persisted in `draft` and SHALL record its `*Created` domain event (`FormatCreated` / `CaseConfigurationCreated`) with `actor_role: newco_ops`. Neither entity binds a parent or a producer, and neither ships a create-time uniqueness guard. The Case Configuration create surface SHALL expose **no** breakability attribute — breakability is decided downstream (Module A/S), never a property of the Case Configuration. Every mutation routes through the domain Action; no `$model->save()` is executed in the resource, page, or action class.

#### Scenario: Creating a Format persists a draft and records FormatCreated

- **WHEN** an operator submits a valid Format (name, size label, volume) through the create surface
- **THEN** `CreateFormat` is invoked, a Format exists in `draft`, and exactly one `FormatCreated` event is recorded with `actor_role: newco_ops` and `actor_id` equal to the operator's id

#### Scenario: Creating a Case Configuration persists a draft with no breakability field

- **WHEN** an operator submits a valid Case Configuration (name, units per case, packaging type) through the create surface
- **THEN** `CreateCaseConfiguration` is invoked, a Case Configuration exists in `draft`, exactly one `CaseConfigurationCreated` event is recorded with `actor_role: newco_ops`, and the create form exposes no breakability attribute

_Source: openspec/specs/product-catalog/spec.md (Format; Case Configuration; Spine Creation Events) · spec/02-prd/Module_0_PRD_v0.3-MVP.md §3.5 (Format — standalone reference entity), §3.6 (Case Configuration — packaging form, no breakability flag), §13.6 BR-RefData-1/2 · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md §4.6 AC-0-BR-RefData-1/2 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.0 · app/Modules/Catalog/Actions/CreateFormat.php, CreateCaseConfiguration.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md._

### Requirement: Operator creates the hierarchical spine entities through the console

The console SHALL let an operator create each hierarchical catalog spine entity through a create surface that selects its parent reference(s) and invokes the entity's `Create<Entity>` Action, returning the created model (never `$model->save()`): a **Product Variant** under exactly one **Product Master** (with the WINE vintage attributes — a vintage year or a non-vintage marker, and translatable tasting notes); a **Product Reference** composed of exactly one **Product Variant** + one **Format**; a **Sellable SKU (Intrinsic)** composed of exactly one **Product Reference** + one **Case Configuration** plus its commercial attributes; and a **Composite SKU** composed of an ordered set of **N ≥ 2** constituent **Product References**. A created entity SHALL be persisted in `draft` and SHALL record its `*Created` event (`ProductVariantCreated` / `ProductReferenceCreated` / `SellableSKUCreated` / `CompositeSKUCreated`) with `actor_role: newco_ops`. The two shipped create guards SHALL be surfaced to the operator as **form validation errors**, not unhandled exceptions: a **Product Reference** whose `(variant, format)` pair already exists (a structural DB uniqueness rejection, which carries no localized domain message — the console maps it to a localized message of its own), and a **Composite SKU** created with fewer than two distinct constituents (a localized domain rejection, surfaced via its own message). The console SHALL NOT validate the producer composition of a Composite SKU — the product-catalog is producer-agnostic about constituents (single-producer admissibility is a Module S Offer-publication concern).

#### Scenario: Selecting a parent creates a draft child

- **WHEN** an operator creates a Product Variant under an existing Product Master (or a Product Reference from a Variant + Format, a Sellable SKU from a Product Reference + Case Configuration, or a Composite SKU from two or more Product References)
- **THEN** the corresponding `Create<Entity>` Action is invoked, the child exists in `draft` referencing the selected parent(s), and its `*Created` event is recorded with `actor_role: newco_ops`

#### Scenario: A duplicate Product Reference is surfaced as a form validation error

- **WHEN** an operator creates a Product Reference whose `(variant, format)` pair matches an existing Product Reference
- **THEN** the create is rejected and surfaced as a form validation error, and no Product Reference is created and no `ProductReferenceCreated` event is recorded

#### Scenario: A Composite SKU with fewer than two constituents is surfaced as a form validation error

- **WHEN** an operator submits a Composite SKU with fewer than two distinct constituent Product References
- **THEN** the create is rejected and surfaced as a form validation error, and no Composite SKU is created and no `CompositeSKUCreated` event is recorded
- **WHEN** an operator submits a Composite SKU with two or more distinct constituents in order
- **THEN** it is created in `draft` with its ordered constituents and `CompositeSKUCreated` is recorded

#### Scenario: PIM is producer-agnostic about Composite constituents

- **WHEN** an operator creates a Composite SKU whose constituent Product References are drawn from more than one producer
- **THEN** the console accepts it without validating producer composition

_Source: openspec/specs/product-catalog/spec.md (Product Variant; Product Reference — the atomic product key; Sellable SKU (Intrinsic); Composite SKU; Spine Creation Events) · spec/02-prd/Module_0_PRD_v0.3-MVP.md §3.3, §3.4, §3.7, §3.8, §13.1 BR-Identity-2/3, §13.5 BR-SKU-2/5 · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md §4.1 AC-0-BR-Identity-2/3, §4.5 AC-0-BR-SKU-2/5, §2 AC-0-J-5 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.0 · app/Modules/Catalog/Actions/CreateProductVariant.php, CreateProductReference.php (DB `UniqueConstraintViolationException`, no app-layer dedup), CreateSellableSku.php, CreateCompositeSku.php (`InsufficientCompositeConstituents`, producer-agnostic) · lang/en/catalog.php (`composite_sku.insufficient_constituents`)._

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

### Requirement: The console surfaces the within-catalog activation-cascade gate

When an operator activates a **child** spine entity whose parent(s) are not all `active`, the domain SHALL reject the activation and the console SHALL surface the reason; activation SHALL succeed only when every parent the child depends on is `active`. The within-catalog cascade gates are: a **Product Variant** requires its **Product Master** `active`; a **Product Reference** requires its **Product Variant** `active` **and** its **Format** `active`; a **Sellable SKU (Intrinsic)** requires its **Product Reference** `active` **and** its **Case Configuration** `active`; a **Composite SKU** requires **every** constituent **Product Reference** `active`. The standalone **Format** and **Case Configuration** have no parent gate and activate subject only to the approval governance. None of the six binds a producer, so the Producer-activation gate (Master-only) does not apply to any of them. The console SHALL surface the domain gate rejection (the gate names the blocking parent) and SHALL NOT reimplement the gate.

#### Scenario: A child cannot activate under a non-active parent

- **WHEN** an operator activates a Product Variant whose Product Master is not `active` (or a Product Reference whose Variant or Format is not `active`, a Sellable SKU whose Product Reference or Case Configuration is not `active`, or a Composite SKU any of whose constituent Product References is not `active`)
- **THEN** the activation is rejected, the console surfaces the reason naming the blocking parent, the child stays `reviewed`, and no `*Activated` event is recorded for it

#### Scenario: A child activates once all its parents are active

- **WHEN** every parent/constituent of a child is `active` and the approval governance passes
- **THEN** the child transitions to `active` and records its `*Activated` event

#### Scenario: A standalone reference entity activates with no parent gate

- **WHEN** an operator activates a Format or a Case Configuration (approval governance satisfied)
- **THEN** it transitions to `active` with no parent gate evaluated, and no producer gate applies

_Source: openspec/specs/product-catalog/spec.md (Activation Cascade) · spec/02-prd/Module_0_PRD_v0.3-MVP.md §4.4 (the activation cascade; per-entity parent rules; standalone entities activate independently), §13.2 BR-Lifecycle-3, §14.3 (parent-before-child emission) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md §3 AC-0-FSM-10 · app/Modules/Catalog/Actions/ActivateProductVariant.php, ActivateProductReference.php, ActivateSellableSku.php, ActivateCompositeSku.php, ActivateFormat.php, ActivateCaseConfiguration.php · lang/en/catalog.php (`gate.parent_not_active`)._

### Requirement: Operator retires and reopens each catalog spine entity, surfacing the reference-integrity block

For each spine entity the console SHALL surface retire (`Retire<Entity>`, `active → retired`, recording the entity's `*Retired` event) and reopen (`Reopen<Entity>`, `retired → reviewed`, audit-only — no event), each invoking the corresponding domain Action. A **hierarchy parent**'s single-entity retire SHALL preserve its existing `active` children (only new activation under the now-`retired` parent is prevented). A single-entity retire **blocked** by an active within-catalog reference SHALL be surfaced with the open references and leave the entity `active`: a **Product Reference** referenced by an `active` Sellable or Composite SKU, or a **Case Configuration** referenced by an `active` Sellable SKU. The other four entities retire without a within-catalog block. The console SHALL NOT offer a cascade-retire affordance for any of the six — no `Retire<Entity>Cascade` Action ships for them (operator-driven cascade retire is Master-only). The cross-module downstream-reference leg of the retirement guard (active Allocations, issued vouchers, in-flight orders, SKUs on live Offers) is a documented Phase-3 seam and is not surfaced by this change.

#### Scenario: Retiring an entity blocked by an active within-catalog reference is surfaced

- **WHEN** an operator retires a Product Reference referenced by an `active` Sellable or Composite SKU (or a Case Configuration referenced by an `active` Sellable SKU)
- **THEN** the retire is rejected, the console surfaces the open references, the entity stays `active`, and no `*Retired` event is recorded

#### Scenario: Retiring a hierarchy parent preserves its active children

- **WHEN** an operator retires (single-entity) a Product Variant that has an `active` Product Reference under it (or a Product Master with an `active` Variant)
- **THEN** the entity becomes `retired`, its existing `active` children remain `active`, and no new child may activate under it

#### Scenario: Reopen returns a retired entity to reviewed

- **WHEN** an operator reopens a `retired` spine entity
- **THEN** it returns to `reviewed` (audit-only, no event), and a subsequent activate re-checks the activation-cascade gate

#### Scenario: No cascade-retire affordance exists for the six spine entities

- **WHEN** the console for any of the six spine entities is inspected
- **THEN** it exposes a single-entity retire and a reopen, but no cascade-retire action (that affordance exists only for Product Master)

_Source: openspec/specs/product-catalog/spec.md (Retirement Cascade and Reference Integrity; Product Lifecycle State Machine — reopen) · spec/02-prd/Module_0_PRD_v0.3-MVP.md §4.5 (existing active children preserved), §4.6/§4.7 (reference-integrity block; operator-driven cascade is the Master path), §13.2 BR-Lifecycle-4/5 · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md §3 AC-0-FSM-11 · decisions/2026-06-16-catalog-retirement-reference-integrity-scope.md (within-catalog terminal-sellable-edge scope, Option B) · app/Modules/Catalog/Actions/RetireProductReference.php, RetireCaseConfiguration.php (reference-integrity guard), RetireProductVariant.php, RetireFormat.php, RetireSellableSku.php, RetireCompositeSku.php, Reopen*.php (no `Retire*Cascade` for the six) · lang/en/catalog.php (`retirement.blocked_by_active_references`)._

### Requirement: Operator creates a Producer through the console

The console SHALL let an operator create a **Producer** — the standalone winery-identity registry (not a Party subtype) — through a manual create surface that collects the Producer's identity attributes (a **name**, a **region** and a **country**, each required; an optional **appellation**, an optional translatable **description**, and an optional **website**), invoking `CreateProducer` and returning the created model (never `$model->save()`). A created Producer SHALL be persisted in `draft` and SHALL record exactly one `ProducerCreated` domain event, tagged module `parties` with a **PII-free** payload, carrying the `actor_role: newco_ops` audit envelope. The Producer ships **no** create-time uniqueness guard (two Producers with the same name both succeed), and the create surface SHALL expose **no** status or KYC field — both `status` (born `draft`) and `kyc_status` (born unset) are lifecycle-managed, never set at creation. Creating a Producer SHALL NOT create a Supplier as a side effect.

#### Scenario: Valid input creates a draft Producer and records ProducerCreated

- **WHEN** an operator submits a valid Producer (name, region, country) through the create surface
- **THEN** `CreateProducer` is invoked, a Producer exists in `draft` with its identity attributes persisted and its `kyc_status` unset
- **AND** exactly one `ProducerCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, entity type `Producer`, and a payload containing no name/email/phone personal data of any party
- **AND** no Supplier row is created

#### Scenario: The create surface exposes the identity attributes and no lifecycle field

- **WHEN** the Producer create surface is inspected
- **THEN** it exposes name, region, country, appellation, description and website fields, and exposes no `status` and no `kyc_status` field

#### Scenario: Producer creation ships no uniqueness guard

- **WHEN** an operator creates two Producers with the same name
- **THEN** both are created in `draft`, each recording its own `ProducerCreated` event (no duplicate-identity rejection)

_Source: openspec/specs/party-registry/spec.md (Producer Registry; Spine Creation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.4 (Producer — standalone winery registry; born `draft`; identity attributes; translatable description), §14.5 BR-K-Producer-1/3 (standalone; no auto-cross-create), §15.4 (`ProducerCreated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-7 (birth half), §5 AC-K-EVT-8 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.0 · app/Modules/Parties/Actions/CreateProducer.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: Operator advances a Producer through its supply-side status lifecycle

The console SHALL surface the Producer's status-FSM transitions — **activate** (`ActivateProducer`, `draft → active`, recording `ProducerActivated`) and **retire** (`RetireProducer`, `active → retired`, recording `ProducerRetired`) — each invoking the corresponding domain Action and recording its verbatim event with `actor_role: newco_ops`. Activation SHALL be **gated on KYC cleared**: the domain rejects it when the Producer's `kyc_status` is `pending` or `rejected` (cleared = `verified`, `not_required`, **or** NULL), and the console SHALL surface the rejection, leaving the Producer in `draft` with no event recorded; the console SHALL NOT re-check the gate. Retirement SHALL **cascade in-domain** to sunset every Club the Producer operates that is currently `active` (each recording a `ClubSunset` causally linked to the `ProducerRetired` root), while Clubs already `sunset`/`closed` are left unchanged — the cascade is the Action's own behaviour, and the console SHALL NOT expose a separate cascade-retire affordance. The activate action SHALL present the **"second actor required"** affordance: Producer activation is subject to the domain **separation-of-duties floor** — an authenticated `newco_ops` operator, **distinct from the Producer's creator**, self-approval never allowed — enforced by the domain alongside the KYC-cleared gate. A same-actor (creator self-approval) or non-operator (`system`/unauthenticated) violation SHALL be rejected by the domain, surfaced to the operator as a notification, and leave the Producer, the audit log and the domain-event log unchanged; the console SHALL NOT reimplement the floor. The console SHALL expose **no** submit, reject, or reopen action — the Producer FSM is linear (`draft → active → retired`) with no review-governance step (the separation-of-duties floor is the spec-admissible 2-step Creator → Approver depth, with no distinct Reviewer leg). An out-of-state transition SHALL be surfaced as a notification without changing state.

#### Scenario: Activate a KYC-cleared draft Producer by a distinct operator

- **GIVEN** a KYC-cleared (`verified`, `not_required`, or NULL) `draft` Producer created by one operator
- **WHEN** a **distinct** authenticated operator activates it
- **THEN** the Producer becomes `active` and exactly one `ProducerActivated` event is recorded with `actor_role: newco_ops` and `actor_id` equal to the activating operator's id

#### Scenario: Self-approval is rejected and surfaced

- **GIVEN** a KYC-cleared `draft` Producer created by operator A
- **WHEN** operator A activates it
- **THEN** the domain rejects it on the separation-of-duties floor, the Producer stays `draft`, no `ProducerActivated` event is recorded, and the console shows a notification that a distinct actor is required

#### Scenario: Activation blocked by uncleared KYC is surfaced

- **WHEN** a distinct authenticated operator activates a `draft` Producer whose `kyc_status` is `pending` or `rejected`
- **THEN** the domain rejects the activation, the console surfaces the reason as a notification, the Producer stays `draft`, and no `ProducerActivated` event is recorded

#### Scenario: Retire an active Producer cascades Club sunset

- **GIVEN** an `active` Producer that operates two `active` Clubs and one already-`closed` Club
- **WHEN** an operator retires the Producer
- **THEN** the Producer becomes `retired` and a `ProducerRetired` event is recorded with `actor_role: newco_ops`
- **AND** the two `active` Clubs transition to `sunset`, each recording a `ClubSunset` carrying the `ProducerRetired` event's id as its causation, while the `closed` Club is unchanged

#### Scenario: The console exposes a cascade-retire-free surface with the second-actor affordance

- **WHEN** the Producer view surface is inspected
- **THEN** it exposes activate and retire but no separate cascade-retire action, no submit/reject/reopen action, and the activate action **presents** the "second actor required" affordance

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator activates a Producer not in `draft`, or retires a Producer not in `active`
- **THEN** the domain raises an `IllegalProducerTransition`, the console surfaces it as a notification, and the Producer's `status` and the domain-event log are unchanged

_Source: openspec/specs/party-registry/spec.md (Producer Lifecycle; Supply-Side Lifecycle Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.4 (Producer FSM `draft → active → retired`; activation requires KYC cleared; the Producer content-approval workflow; §0 Q3 role-count admin-configurable, 2-step admitted, floor holds at any depth; retirement preserves Product Masters, blocks new activations), §10.2 (Producer offboarding cascade → Club sunset), §14.5 BR-K-Producer-2/4, §15.4 (`ProducerActivated`, `ProducerRetired`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-7 (activation gated on KYC cleared — positive `verified`/`not_required`, negative `pending`/`rejected`), §2 AC-K-J-10 (Creator → Reviewer → Approver against Producer content; **assert workflow with distinct actors at the configured depth**), AC-K-J-19, §5 AC-K-EVT-8, §6 AC-K-XM-2 (Module 0 consumes these events) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §5.2 (the multi-actor discipline — the "second actor required" affordance; distinct actors; self-approval never allowed; names Module K §4.4 Producer activation), §3.0 · decisions/2026-06-17-approval-separation-of-duties-role-gated.md (RESOLVED — retain the strict distinct-actor floor) · openspec/specs/operator-console/spec.md (Operator advances each catalog spine entity through the review-and-approval lifecycle — the "second actor required" + `ApprovalGovernance` surface pattern mirrored here) · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md (SoD surfaced, not reimplemented) · app/Modules/Parties/Actions/ActivateProducer.php, RetireProducer.php, SunsetClub.php · app/Modules/Parties/Exceptions/IllegalProducerTransition.php · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md · MODIFIES the frozen *Operator advances a Producer through its supply-side status lifecycle* requirement (openspec/specs/operator-console/spec.md — which stated the activate action presents **no** "second actor required" affordance and Producer activation is not the catalog separation-of-duties governance)._

### Requirement: Operator manages a Producer's KYC through the console

The console SHALL surface the Producer's provenance-KYC FSM (`not_required → pending → verified | rejected`, plus the operator waive to `not_required`) as four operator actions, each invoking the corresponding domain Action: **require** (`RequireProducerKyc`, `not_required`|NULL → `pending`), **waive** (`WaiveProducerKyc`, any outstanding state → `not_required`), **verify** (`RecordProducerKycVerified`, `pending → verified`), and **reject** (`RecordProducerKycRejected`, `pending → rejected`). These KYC transitions are **audit-only** — the PRD event catalog names no KYC event, so each records the `actor_role: newco_ops` audit envelope but **no** domain event — and the KYC FSM is **separate** from the Producer status FSM: a KYC transition SHALL NOT move the Producer's `status`. None of these actions SHALL place or lift a Hold (Producer KYC carries no Hold coupling). The console SHALL display the Producer's current `kyc_status` so that a KYC-blocked activation is explainable. An illegal KYC transition SHALL be surfaced as a notification without changing state.

#### Scenario: Require KYC moves not_required/NULL to pending, audit-only

- **WHEN** an operator requires KYC on a Producer whose `kyc_status` is `not_required` or NULL
- **THEN** `kyc_status` becomes `pending`, the Producer's `status` is unchanged, the transition is recorded with the `actor_role: newco_ops` audit envelope, and no domain event is recorded

#### Scenario: Verify and reject resolve a pending Producer, audit-only

- **WHEN** an operator verifies a Producer in KYC `pending`
- **THEN** `kyc_status` becomes `verified` (a cleared state), no domain event is recorded, and the Producer's `status` is unchanged
- **WHEN** an operator rejects a Producer in KYC `pending`
- **THEN** `kyc_status` becomes `rejected` (a blocking state), no domain event is recorded, and no Hold is placed

#### Scenario: Waive clears the gate from any outstanding state

- **WHEN** an operator waives KYC on a Producer whose `kyc_status` is `pending`, `rejected`, `verified`, or NULL
- **THEN** `kyc_status` becomes `not_required` (a cleared state), audit-only, and the Producer's `status` is unchanged

#### Scenario: KYC management unblocks a KYC-gated activation end-to-end

- **GIVEN** a `draft` Producer whose `kyc_status` is `pending`
- **WHEN** an operator activates it
- **THEN** the activation is rejected and surfaced, the Producer stays `draft`, and no `ProducerActivated` event is recorded
- **WHEN** the operator then verifies the Producer (KYC `pending → verified`) and activates it
- **THEN** the Producer becomes `active` and `ProducerActivated` is recorded

#### Scenario: An illegal KYC transition is surfaced without changing state

- **WHEN** an operator verifies or rejects a Producer not in KYC `pending`, or waives a Producer already `not_required`
- **THEN** the domain raises an `IllegalKycTransition`, the console surfaces it as a notification, and `kyc_status` is unchanged

_Source: openspec/specs/party-registry/spec.md (Producer KYC Lifecycle; Producer Lifecycle — the KYC-cleared activation gate) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.4 (Producer provenance-KYC four-state; `not_required`/`verified` cleared; separate from the status FSM), §9.1 (KYC lifecycle), §14.5 BR-K-Producer-2 · spec/04-decisions/decisions.md DEC-071 (KYC fields additive nullable) · decisions/2026-06-17-producer-kyc-gate-not-required-clears.md (waive → `not_required` clears the gate) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-7 · app/Modules/Parties/Actions/RequireProducerKyc.php, WaiveProducerKyc.php, RecordProducerKycVerified.php, RecordProducerKycRejected.php · app/Modules/Parties/Exceptions/IllegalKycTransition.php · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: Operator creates a Club through the console

The console SHALL let an operator create a **Club** — a Producer-operated membership group — through a manual create surface that collects the Club's **display name**, an operating **Producer** (a required picker), and a **registration flow type** (required), plus an optional **fee** (a minor-unit amount + an ISO 4217 currency, assembled into the `Money` value object) and a **generates-credit** flag (default true), invoking `CreateClub` and returning the created model (never `$model->save()`). The registration-flow select SHALL offer **only the launch-selectable values** — `application_with_approval` (default), `invitation_only`, `link_onboarding` — and SHALL **not** offer `open_registration` (carried latent, not selectable at launch; per *Club Registration Flow and Onboarding Channel*). There SHALL be **no** separate **invite-only** field — the invite-only channel is `invitation_only` (the former `invite_only` boolean is removed/subsumed). The create surface SHALL construct the `ClubRegistrationFlowType` **operand enum** from the selected value to perform the write-through (the carve-out this change widens — ADR 2026-06-21). A created Club SHALL be born **`active`** and SHALL record exactly one `ClubCreated` domain event, tagged module `parties`, carrying the `actor_role: newco_ops` audit envelope. A Club's operating Producer is **required**: a create referencing a Producer that does not exist SHALL be rejected (`MissingClubProducer`) and surfaced on the form, with no Club created. The create surface SHALL expose **no** status field — `status` is born `active` by the Action, never set at creation.

#### Scenario: Valid input creates an active Club and records ClubCreated

- **WHEN** an operator submits a valid Club (display name, an existing operating Producer, a launch-selectable registration flow type) through the create surface
- **THEN** `CreateClub` is invoked and a Club exists in `active` with its attributes persisted
- **AND** exactly one `ClubCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `Club`

#### Scenario: The optional fee is assembled into Money when provided

- **WHEN** an operator creates a Club supplying a fee amount and currency
- **THEN** the Club's `fee` is the `Money` of that minor-unit amount and currency
- **WHEN** an operator creates a Club leaving the fee blank
- **THEN** the Club is created with no fee (null)

#### Scenario: The registration-flow select offers only the launch-selectable channels

- **WHEN** the Club create surface is inspected
- **THEN** the registration-flow select offers `application_with_approval`, `invitation_only` and `link_onboarding`, and does **not** offer `open_registration`; and there is no separate invite-only field

#### Scenario: Creating a Club requires an existing operating Producer

- **WHEN** an operator submits a Club whose selected operating Producer does not exist
- **THEN** the domain raises a `MissingClubProducer`, the console surfaces it on the form, and no Club and no `ClubCreated` event are created

#### Scenario: The create surface exposes the Club attributes and no lifecycle field

- **WHEN** the Club create surface is inspected
- **THEN** it exposes display name, operating Producer, registration flow type, fee and generates-credit fields, exposes no invite-only field, and exposes no `status` field

_Source: openspec/specs/party-registry/spec.md (Club; **Club Registration Flow and Onboarding Channel**; Spine Creation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.3, §15.3 (`ClubCreated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-6, §4.4 AC-K-BR-Club-1, **§4.4 AC-K-BR-Club-6 (registration_flow entry-channel-only; `open` latent; `invite_only` subsumed)**, §5.3 AC-K-EVT-7 · canon **MVP-DEC-022** (LIVE `cmless/main` @ `360df0b`) via this change's mini-ADR `decisions/2026-07-07-adopt-mvp-dec-022-club-membership-governance.md` · app/Modules/Parties/Actions/CreateClub.php · app/Modules/Parties/Enums/ClubRegistrationFlowType.php · decisions/2026-06-21-operator-console-operand-enum-carveout.md · MODIFIES the *Operator creates a Club through the console* requirement (openspec/specs/operator-console/spec.md — which collected an **invite-only flag** and offered every `ClubRegistrationFlowType` value)._

### Requirement: Operator advances a Club through its status lifecycle

The console SHALL surface the Club's status-FSM transitions — **sunset** (`SunsetClub`, `active → sunset`, recording `ClubSunset`) and **close** (`CloseClub`, `sunset → closed`, recording `ClubClosed`) — each invoking the corresponding domain Action and recording its verbatim event with `actor_role: newco_ops`. The console SHALL expose **no activate verb** — a Club is born `active`, so there is no `draft → active` transition to drive. Each transition SHALL present **no** "second actor required" affordance — Club lifecycle is a single-operator transition, not separation-of-duties governance (the spec mandates none for Club). The console SHALL expose **no** submit, reject, or reopen action — the Club FSM is linear (`active → sunset → closed`) with no review-governance step. Because `closed` is reachable only from `sunset`, a close attempted on an `active` Club SHALL be rejected by the domain (`IllegalClubTransition`) and surfaced; any out-of-state transition SHALL be surfaced as a notification without changing state.

#### Scenario: Sunset an active Club

- **WHEN** an operator sunsets an `active` Club
- **THEN** the Club becomes `sunset` and exactly one `ClubSunset` event is recorded with `actor_role: newco_ops` and `actor_id` equal to the operator's id

#### Scenario: Close a sunset Club

- **WHEN** an operator closes a `sunset` Club
- **THEN** the Club becomes `closed` and exactly one `ClubClosed` event is recorded with `actor_role: newco_ops`

#### Scenario: Close is rejected on an active Club — it must pass through sunset

- **WHEN** an operator attempts to close an `active` Club that has not been sunset
- **THEN** the domain raises an `IllegalClubTransition`, the console surfaces it as a notification, and the Club stays `active` with no event recorded

#### Scenario: The console exposes the linear verbs and none of the catalog governance verbs

- **WHEN** the Club view surface is inspected
- **THEN** it exposes sunset and close but no activate action, no submit/reject/reopen action, and neither transition presents a "second actor required" affordance

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator sunsets a Club not in `active`, or closes a Club not in `sunset`
- **THEN** the domain raises an `IllegalClubTransition`, the console surfaces it as a notification, and the Club's `status` and the domain-event log are unchanged

_Source: openspec/specs/party-registry/spec.md (Club Lifecycle; Supply-Side Lifecycle Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.3 (Club FSM `active → sunset → closed`; sunset blocks new memberships/offers, preserves Profiles; `closed` terminal), §10.2 (Club sunset is the per-Club leg of Producer offboarding; operator transitions `sunset → closed`), §15.3 (`ClubSunset`, `ClubClosed`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-6 (FSM path `active → sunset → closed` — `closed` reachable only from `sunset`), §5.3 AC-K-EVT-7 (`ClubSunset` on `active → sunset`; `ClubClosed` on `sunset → closed`) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (Club config operator surface), §5.2 (multi-actor discipline lists neither Club nor ProducerAgreement — single-operator) · app/Modules/Parties/Actions/SunsetClub.php, CloseClub.php · app/Modules/Parties/Exceptions/IllegalClubTransition.php · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: Operator creates a ProducerAgreement through the console

The console SHALL let an operator create a **ProducerAgreement** — the commercial agreement governing a Producer relationship — through a manual create surface that collects a required **Producer** (a picker), an optional **Club** narrowing (a picker; blank = a Producer-wide agreement), optional **term start** and **term end** dates, and an optional **settlement cadence** **selected from the closed set** `{quarterly, monthly, semi-annual}` (default `quarterly`), invoking `CreateProducerAgreement` and returning the created model (never `$model->save()`). The create surface SHALL construct the `SettlementCadence` **operand enum** from the selected value (widening the operand-enum carve-out, ADR 2026-06-21); an out-of-set cadence SHALL be rejected server-side (domain + DB CHECK). The **Club picker SHALL offer only `active` Clubs** of the selected Producer — a `sunset`/`closed` Club is not selectable, and a create scoped to a non-`active` Club SHALL be rejected (`ProducerAgreementClubNotActive`) and surfaced (per BR-K-Agreement-4). A created ProducerAgreement SHALL be born **`draft`** and SHALL record exactly one `ProducerAgreementCreated` event with `actor_role: newco_ops`. The Producer reference is **required**: a create referencing a Producer that does not exist SHALL be rejected (`MissingAgreementProducer`) and surfaced. The single-active-per-scope invariant (and the cross-shape mutual exclusion) SHALL **not** be enforced at creation — draft agreements are created freely (both bind only at activation). The create surface SHALL expose **no** status field.

#### Scenario: Valid input creates a draft ProducerAgreement and records ProducerAgreementCreated

- **WHEN** an operator submits a valid ProducerAgreement (an existing Producer, optionally an `active` Club, term dates and a closed-set cadence) through the create surface
- **THEN** `CreateProducerAgreement` is invoked and a ProducerAgreement exists in `draft` with its attributes persisted
- **AND** exactly one `ProducerAgreementCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `ProducerAgreement`

#### Scenario: A Producer-wide agreement omits the Club narrowing

- **WHEN** an operator creates a ProducerAgreement leaving the Club picker blank
- **THEN** the agreement is created with a null `club_id` (Producer-wide scope)

#### Scenario: The Club picker offers only active Clubs, and settlement cadence is a closed set

- **WHEN** the ProducerAgreement create surface is inspected
- **THEN** the Club picker offers only the selected Producer's `active` Clubs (no `sunset`/`closed`), and the settlement-cadence field is a select over `{quarterly, monthly, semi-annual}` — not a free-text input

#### Scenario: Creating a ProducerAgreement requires an existing Producer

- **WHEN** an operator submits a ProducerAgreement whose selected Producer does not exist
- **THEN** the domain raises a `MissingAgreementProducer`, the console surfaces it, and no agreement and no event are created

#### Scenario: Draft creation does not enforce the single-active-per-scope invariant

- **WHEN** an operator creates two draft ProducerAgreements in the same Producer scope
- **THEN** both are created in `draft`, each recording its own `ProducerAgreementCreated` (the single-active and cross-shape rules bind only at activation)

_Source: openspec/specs/party-registry/spec.md (ProducerAgreement; Spine Creation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.6, §4.6.1, §15.5 (`ProducerAgreementCreated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §2 AC-K-J-11, §3 AC-K-FSM-8, §4.6 **AC-K-BR-Agreement-2 (settlement-cadence override) / AC-K-BR-Agreement-4 (per-Club scope requires an `active` Club)** · canon **MVP-DEC-010** (settlement `{quarterly, monthly, semi-annual}`, server-enforced) + **MVP-DEC-009** (per-Club scope requires active Club), LIVE `cmless/main` @ `360df0b`, via this change's mini-ADRs `decisions/2026-07-07-adopt-mvp-dec-010-settlement-cadence-closed-set.md` + `decisions/2026-07-07-adopt-mvp-dec-009-agreement-club-active-scope.md` · app/Modules/Parties/Actions/CreateProducerAgreement.php · decisions/2026-06-21-operator-console-operand-enum-carveout.md · MODIFIES the *Operator creates a ProducerAgreement through the console* requirement (openspec/specs/operator-console/spec.md — which took settlement cadence as a **free-text string** with "no operand enum" and admitted **any** Club narrowing)._

### Requirement: Operator advances a ProducerAgreement through its status lifecycle

The console SHALL surface the ProducerAgreement's status-FSM transitions — **activate** (`ActivateProducerAgreement`, `draft → active`, recording `ProducerAgreementActivated`) and **terminate** (`TerminateProducerAgreement`, `active → terminated`, recording `ProducerAgreementTerminated`) — each invoking the corresponding domain Action and recording its verbatim event with `actor_role: newco_ops`. Activation SHALL **supersede in-domain** any agreement already `active` in the same `(producer_id, club_id)` scope: the prior transitions `active → superseded`, recording a `ProducerAgreementSuperseded` causally linked to the activation — this is the Action's own behaviour, so the console SHALL expose **no standalone supersede verb** (`superseded` is never a direct operator transition). Termination SHALL **not** cascade to the Producer's state. Each verb SHALL present **no** "second actor required" affordance — ProducerAgreement lifecycle is single-operator (the spec mandates no SoD). The console SHALL expose **no** submit, reject, or reopen action. An out-of-state transition (`IllegalProducerAgreementTransition` — activate not from `draft`, terminate not from `active`) SHALL be surfaced as a notification without changing state.

#### Scenario: Activate a draft ProducerAgreement with no prior active in scope

- **WHEN** an operator activates a `draft` ProducerAgreement that has no prior active agreement in its `(producer_id, club_id)` scope
- **THEN** the agreement becomes `active` and exactly one `ProducerAgreementActivated` event is recorded with `actor_role: newco_ops` and `actor_id` equal to the operator's id, and no `ProducerAgreementSuperseded` is recorded

#### Scenario: Activation supersedes the prior active agreement in the same scope

- **GIVEN** an `active` ProducerAgreement and a second `draft` agreement in the same `(producer_id, club_id)` scope
- **WHEN** an operator activates the draft
- **THEN** the draft becomes `active`, the prior becomes `superseded`, and a `ProducerAgreementSuperseded` event is recorded carrying the `ProducerAgreementActivated` event's id as its causation

#### Scenario: Terminate an active ProducerAgreement without cascading to the Producer

- **GIVEN** an `active` ProducerAgreement whose Producer is `active`
- **WHEN** an operator terminates the agreement
- **THEN** the agreement becomes `terminated`, a `ProducerAgreementTerminated` event is recorded with `actor_role: newco_ops`, and the Producer's `status` is unchanged

#### Scenario: The console exposes activate and terminate but no standalone supersede or governance verbs

- **WHEN** the ProducerAgreement view surface is inspected
- **THEN** it exposes activate and terminate but no supersede action, no submit/reject/reopen action, and neither verb presents a "second actor required" affordance

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator activates an agreement not in `draft`, or terminates one not in `active`
- **THEN** the domain raises an `IllegalProducerAgreementTransition`, the console surfaces it as a notification, and the agreement's `status` and the domain-event log are unchanged

_Source: openspec/specs/party-registry/spec.md (ProducerAgreement Lifecycle; Supply-Side Lifecycle Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.6.1 (FSM `draft → active → superseded | terminated`; activation accepts terms; supersession = a replacement's activation transitions the prior to `superseded`, the two paired in audit; termination does not cascade to Producer-level state), §15.5 (`ProducerAgreementActivated`, `ProducerAgreementSuperseded`, `ProducerAgreementTerminated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §2 AC-K-J-11 (`draft → active`), §2 AC-K-J-12 (renewal via supersession — prior → `superseded`), §3 AC-K-FSM-8 (FSM `draft → active → superseded|terminated`), §4.6 AC-K-BR-Agreement-1 (single active per scope), AC-K-BR-Agreement-3 (renewal supersedes prior), §5.3 AC-K-EVT-9 (the four events; termination does NOT auto-cascade to Producer) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (activate ProducerAgreement = operator action), §5.2 (no SoD for ProducerAgreement) · app/Modules/Parties/Actions/ActivateProducerAgreement.php, TerminateProducerAgreement.php · app/Modules/Parties/Exceptions/IllegalProducerAgreementTransition.php · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: Operator creates a Customer through the console

The console SHALL let an operator create a **Customer** — Module K's natural-person registry entry — through a manual create surface that collects the Customer's **email**, **name**, a **preferred currency** (an ISO 4217 code assembled into the platform `Currency` value object) and a **preferred locale** (a supported-locale value), plus an optional **phone** and a **date of birth**, invoking `CreateCustomer` and returning the created model (never `$model->save()`). A created Customer SHALL be born **`pending`** and SHALL **co-provision exactly one Account** (born `Personal` / `active`) in the same transaction; the Account is event-silent. The create SHALL record exactly one `CustomerCreated` domain event, tagged module `parties`, carrying the `actor_role: newco_ops` audit envelope. The **age gate** SHALL apply: a `date_of_birth` implying an age **below the configured platform minimum** (default 18) — or a **missing** `date_of_birth` (attestation is mandatory, per *Registration Age Gate*, so the field is **effectively required**) — SHALL be rejected (`BelowMinimumRegistrationAge`) and surfaced on the **date-of-birth field**, with **no Customer, Account or event created**. The email is **unique**: a create whose email already exists SHALL be rejected (`DuplicateCustomerEmail`) and surfaced. The create SHALL **not** create any Profile. The create surface SHALL expose **no** status field.

#### Scenario: Valid input creates a pending Customer with a co-provisioned Account and records CustomerCreated

- **WHEN** an operator submits a valid Customer (email, name, preferred currency, preferred locale, and a `date_of_birth` at or above the minimum) through the create surface
- **THEN** `CreateCustomer` is invoked and a Customer exists in `pending` with its attributes persisted and exactly one co-provisioned Account in `active`
- **AND** exactly one `CustomerCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `Customer`

#### Scenario: An under-age registration is rejected and surfaced

- **WHEN** an operator submits a Customer whose `date_of_birth` implies an age below the configured minimum
- **THEN** the domain raises a `BelowMinimumRegistrationAge`, the console surfaces it on the date-of-birth field, and no Customer, no Account and no `CustomerCreated` event are created

#### Scenario: A missing date of birth is rejected and surfaced

- **WHEN** an operator submits a Customer with no date of birth
- **THEN** the domain raises a `BelowMinimumRegistrationAge` (attestation is mandatory — the field is effectively required), the console surfaces it on the date-of-birth field, and no Customer, no Account and no `CustomerCreated` event are created

#### Scenario: A duplicate email is rejected and surfaced

- **WHEN** an operator submits a Customer whose email already belongs to an existing Customer
- **THEN** the domain raises a `DuplicateCustomerEmail`, the console surfaces it on the email field, and no Customer, no Account and no `CustomerCreated` event are created

#### Scenario: The create surface exposes the identity fields, no lifecycle field, and creates no Profile

- **WHEN** the Customer create surface is inspected
- **THEN** it exposes email, name, preferred currency, preferred locale, phone and date-of-birth fields, exposes no `status` field, and a created Customer has zero Profiles

_Source: openspec/specs/party-registry/spec.md (Customer Identity; **Registration Age Gate**; Account — Billing Container) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1, §4.7, §7.1 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §2 AC-K-J-1, **§4.1 AC-K-BR-Identity-6 (18+ age-gate at registration)**, §5 AC-K-EVT-1 · canon **MVP-DEC-022** (LIVE `cmless/main` @ `360df0b`) + **BMD §2.8**, via this change's mini-ADR `decisions/2026-07-07-adopt-mvp-dec-022-club-membership-governance.md` · app/Modules/Parties/Actions/CreateCustomer.php · app/Modules/Parties/Exceptions/DuplicateCustomerEmail.php · MODIFIES the *Operator creates a Customer through the console* requirement (openspec/specs/operator-console/spec.md — which collected an **optional** `date_of_birth` with **no age gate**)._

### Requirement: Operator advances a Customer through its status lifecycle

The console SHALL surface the Customer's status-FSM transitions — **activate** (`ActivateCustomer`, `pending → active`, recording `CustomerActivated`), **suspend** (`SuspendCustomer`, `active → suspended`, recording `CustomerSuspended`), **reactivate** (`ReactivateCustomer`, `suspended → active`, recording `CustomerReactivated`) and **close** (`CloseCustomer`, `active | suspended → closed`, recording `CustomerClosed`) — each invoking the corresponding domain Action and recording its verbatim event with `actor_role: newco_ops`. These direct verbs are the **BR-K-Customer-1 "manual" suspension/reactivation path**; the Hold-mediated path (`PlaceHold` / `LiftHold`, whose coupling also moves Customer status) is realized by the _Operator places and lifts Customer Holds through the console_ requirement — the two paths coexist by design (ADR 2026-06-19). Each verb SHALL be **form-less** and SHALL present **no** "second actor required" affordance — Customer lifecycle is single-operator (the spec mandates no separation-of-duties for Customer). The console SHALL expose **no** submit, reject, or reopen action (the Customer FSM is not review-governed). The Customer's co-provisioned **Account** status verbs (suspend/reactivate/close) ARE surfaced on this same view by the separate _Operator advances a Customer's Account through its status lifecycle_ requirement; **Profile**-lifecycle verbs are **not** on this view — they live on the standalone `ProfileResource` (_Operator advances a Profile through its lifecycle_, with Profile create and membership approval their own requirements). The KYC verbs are the separate _Operator manages a Customer's KYC verification through the console_ requirement, sanctions screening the _Operator records a Customer's sanctions screening through the console_ requirement, and Hold place/lift the _Operator places and lifts Customer Holds through the console_ requirement, none part of these status verbs.

Activation SHALL be governed by the Action's **composite, cross-slice gate** (acceptance of T&C and privacy + verified email + `sanctions_status = passed` + KYC cleared if required): an activation attempted with the gate unmet SHALL be rejected (`IllegalCustomerTransition::gateNotMet`) and surfaced as a notification without changing state — this slice provides **no** surface to satisfy those preconditions (they are set by the consumer-onboarding flow and the compliance console). Suspension SHALL cascade `ProfileSuspended` to the Customer's active Profiles, and reactivation SHALL cascade `ProfileReactivated` to its suspended-and-uncovered Profiles; these cascades are the domain Action's own behaviour, inherited by driving the Action (the console neither re-implements nor suppresses them). Any out-of-state transition (`IllegalCustomerTransition` — activate not from `pending`, suspend not from `active`, reactivate not from `suspended`, close not from `active | suspended`) SHALL be surfaced as a notification without changing state or recording an event.

#### Scenario: Activate a gate-met pending Customer

- **GIVEN** a `pending` Customer whose activation gate is satisfied (email verified, T&C + privacy accepted, `sanctions_status = passed`, KYC not required)
- **WHEN** an operator activates the Customer
- **THEN** the Customer becomes `active` and exactly one `CustomerActivated` event is recorded with `actor_role: newco_ops` and `actor_id` equal to the operator's id

#### Scenario: Activation with the gate unmet is rejected and surfaced

- **GIVEN** a `pending` Customer freshly created through the console (no onboarding acceptance recorded, `sanctions_status` unset)
- **WHEN** an operator attempts to activate the Customer
- **THEN** the domain raises an `IllegalCustomerTransition` (gate-not-met), the console surfaces it as a danger notification, and the Customer stays `pending` with no event recorded

#### Scenario: Suspend an active Customer, then reactivate it

- **GIVEN** an `active` Customer
- **WHEN** an operator suspends the Customer
- **THEN** the Customer becomes `suspended` and exactly one `CustomerSuspended` event is recorded with `actor_role: newco_ops`
- **WHEN** the operator then reactivates the Customer
- **THEN** the Customer becomes `active` and exactly one `CustomerReactivated` event is recorded

#### Scenario: Close a Customer

- **GIVEN** an `active` Customer (or a `suspended` Customer)
- **WHEN** an operator closes the Customer
- **THEN** the Customer becomes `closed` and exactly one `CustomerClosed` event is recorded with `actor_role: newco_ops`

#### Scenario: The console exposes the status verbs and none of the review-governance or deferred-lifecycle verbs

- **WHEN** the Customer view surface is inspected
- **THEN** it exposes activate, suspend, reactivate and close for the Customer **plus** the Account suspend/reactivate/close verbs (the KYC, sanctions and Hold place/lift surfaces are realized by their own requirements), no submit/reject/reopen action, **no** Profile-lifecycle action (Profile verbs live on the `ProfileResource`), and no verb presents a "second actor required" affordance

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator suspends a Customer not in `active`, reactivates one not in `suspended`, or closes one in `pending`
- **THEN** the domain raises an `IllegalCustomerTransition`, the console surfaces it as a notification, and the Customer's `status` and the domain-event log are unchanged

_Source: openspec/specs/party-registry/spec.md (Customer Onboarding Activation; Customer Suspension and Closure; Demand-Side Status Events; Hold-Driven Status Coupling) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (status FSM `pending → active → suspended → closed`; T&C/privacy a hard gate on `pending → active`), §10.1 (Customer-level suspension; reactivation on Hold lift), BR-K-Customer-1 (suspension is explicit — manual or via Hold — not Profile-driven) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-1 (Customer FSM + the five status events), §4.2 AC-K-BR-Customer-1, §5 AC-K-EVT-1 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (onboard/edit + run suspension — operator surface), §5.2 (no SoD mandated for Customer) · app/Modules/Parties/Actions/{ActivateCustomer,SuspendCustomer,ReactivateCustomer,CloseCustomer}.php · app/Modules/Parties/Exceptions/IllegalCustomerTransition.php · app/Modules/Parties/Events/{CustomerActivated,CustomerSuspended,CustomerReactivated,CustomerClosed}.php · decisions/2026-06-19-hold-status-coupling.md · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: The Customer console surfaces the orthogonal compliance and membership context read-only

The console's list and infolist SHALL display the Customer's **status**, **KYC status**, **sanctions status**, the co-provisioned **Account's status**, and the Customer's **Profiles** (membership) — each rendered **read-only** via the model casts and within-module reads (no cross-module import beyond `{Models}`; state enums rendered through their cast, never imported). The KYC and sanctions lifecycles SHALL be presented as **independent** axes carried on the Customer, **distinct from** the status FSM (a Customer may show `kyc_status = verified` with `sanctions_status = pending`, or vice-versa, each rendered on its own). The Account lifecycle and Profile membership **write** surfaces are realized by their own requirements — Account suspend/reactivate/close by _Operator advances a Customer's Account through its status lifecycle_ (on this Customer view), and Profile create, approval and lifecycle by the standalone `ProfileResource` (_Operator creates a Profile through the console_, _Operator approves or declines a Profile membership through the console_, _Operator advances a Profile through its lifecycle_); the **Account status and Profiles rendered in this list/infolist remain read-only**. The KYC write surface is the separate _Operator manages a Customer's KYC verification through the console_ requirement, the sanctions write surface the separate _Operator records a Customer's sanctions screening through the console_ requirement, and the Hold place/lift affordance the separate _Operator places and lifts Customer Holds through the console_ requirement; the KYC and sanctions **status rendered here remains read-only** (the badges display state through the cast — the write verbs invoke domain Actions, never an in-place field edit).

#### Scenario: The list and infolist render the three lifecycles, account status and profiles read-only

- **WHEN** an operator opens a Customer in the console
- **THEN** the surface shows the Customer's status, KYC status, sanctions status, Account status and Profiles, all read-only, with no field editable in place

#### Scenario: KYC and sanctions are rendered as independent axes

- **GIVEN** a Customer with `kyc_status = verified` and `sanctions_status = pending`
- **WHEN** an operator views the Customer
- **THEN** both lifecycles are displayed independently, neither collapsed into the `pending/active/suspended/closed` status FSM

#### Scenario: Account status and Profiles render read-only, their write surfaces realized elsewhere

- **WHEN** the Customer console list and infolist are inspected
- **THEN** the Account status and the Customer's Profiles are shown read-only through the cast (no in-place field edit), with the Account write verbs realized by their own requirement on this view and Profile writes living on the `ProfileResource`

_Source: openspec/specs/party-registry/spec.md (Customer KYC Lifecycle; Customer Sanctions Screening Lifecycle; Account Status Lifecycle; Profile — Multi-Profile Membership) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (KYC + sanctions carried as separate lifecycles on Customer), §9.1 (KYC four-state lifecycle), §9.2 (sanctions four-state lifecycle), §9.4 (KYC and sanctions independent — both must clear independently), §4.7 (Account status parallels Customer) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-3 (KYC separate from Customer FSM), AC-K-FSM-4 (sanctions separate), AC-K-FSM-5 (KYC and sanctions independent), AC-K-FSM-9 (Account FSM) · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md (read-projection) · decisions/2026-06-21-operator-console-operand-enum-carveout.md (state enums rendered via cast, never imported)._

### Requirement: Operator places and lifts Customer Holds through the console

The console SHALL let an operator **place** and **lift** Holds on a Customer through the Customer view, routing every write through the Module K domain Actions (`PlaceHold` / `LiftHold`) and never an Eloquent write. The view SHALL render the Customer's Holds — those scoped to the Customer itself, to its co-provisioned **Account**, and to its **Profiles** — read-only as a table, each row showing the Hold's `hold_type`, `scope_type`, `status`, `reason`, the placement actor + moment and (once lifted) the lift actor + moment, read from the `Hold` model within the `{Models}` read surface (state enums rendered through their cast, never imported).

**Place.** A form-bearing header action SHALL collect a `hold_type` (the eight-value `HoldType` domain `admin | kyc | payment | fraud | compliance | credit | chargeback_review | storage_payment_failed` — canon MVP-DEC-008), a `scope_type` (`HoldScope` — `customer | account | profile`), the scope target resolved from the Customer (the Customer itself, its Account, or a selected Profile) and an optional `reason`, constructing the `HoldType` and `HoldScope` **operand** enums from the form values and invoking `PlaceHold`. The form's type options SHALL derive from `HoldType::cases()` (value-keyed, in enum order), so a manual operator-placement path SHALL exist for **every** one of the eight types (the registry is trigger-agnostic). Placement SHALL record exactly one `CustomerHoldPlaced` domain event carrying the `actor_role: newco_ops` audit envelope and a PII-free payload.

**Lift.** Each **active** Hold whose type is **operator-liftable** — `admin | fraud | compliance | credit | chargeback_review | storage_payment_failed`, i.e. NOT `HoldType::autoLiftable()` (the six operator-lift-only types; the two finance-driven types are operator-lift-only at launch — canon MVP-DEC-008) — SHALL expose a per-row `Lift` action collecting an optional `lift_reason` and invoking `LiftHold` with that Hold's id; lifting SHALL record exactly one `CustomerHoldLifted` event with the audit envelope. An **auto-managed** type (`kyc | payment`) SHALL expose **no** Lift action, and an attempt to lift one through the domain SHALL be rejected (`IllegalHoldLift`, auto-managed) and surfaced as a notification without state change. Lifting a Hold that is not `active` SHALL likewise be rejected (`IllegalHoldLift`, not-active) and surfaced without state change.

**Status coupling (domain-owned, additive).** Placing and lifting SHALL drive the covered scope's status as the domain Action's own behaviour — `PlaceHold` suspends every covered scope currently in its suspendable from-state, and `LiftHold` restores a `suspended` scope only when no other active Hold still covers it — recording the corresponding status events. The console SHALL invoke **only** `PlaceHold` / `LiftHold`; it SHALL NOT call the `Suspend*` / `Reactivate*` Actions itself, SHALL NOT recompute suspension from Holds, and SHALL NOT suppress the coupling. This Hold-mediated path **coexists** with the direct status verbs (the manual path) of the _Operator advances a Customer through its status lifecycle_ requirement.

#### Scenario: Placing a Hold records CustomerHoldPlaced with the audit metadata

- **WHEN** an operator places a Hold of a chosen type and scope on a Customer through the place form
- **THEN** `PlaceHold` is invoked and a Hold exists `active` with its type, scope, reason and the placement actor + moment recorded
- **AND** exactly one `CustomerHoldPlaced` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, entity type `Hold`, and a PII-free payload (no name, email, phone or date of birth)

#### Scenario: Placing an operator Hold on an active Customer suspends it

- **GIVEN** an `active` Customer
- **WHEN** an operator places an `admin` Hold on the Customer
- **THEN** the Customer becomes `suspended`, and a `CustomerHoldPlaced` and a `CustomerSuspended` event are recorded

#### Scenario: A Hold on a non-suspendable scope drives no status transition

- **GIVEN** a `pending` Customer
- **WHEN** an operator places an `admin` Hold on the Customer
- **THEN** the Hold is recorded `active` and the Customer stays `pending`, with a `CustomerHoldPlaced` event and no `CustomerSuspended` event

#### Scenario: An operator lifts an operator-liftable Hold; the last covering lift restores

- **GIVEN** an `active` Customer covered by two active customer-scope Holds (`admin` and `fraud`), hence `suspended`
- **WHEN** the operator lifts the `admin` Hold
- **THEN** that Hold becomes `lifted`, a `CustomerHoldLifted` event is recorded, and the Customer stays `suspended` (the `fraud` Hold still covers it) — no `CustomerReactivated`
- **WHEN** the operator then lifts the `fraud` Hold (the last covering Hold)
- **THEN** the Customer becomes `active` and a `CustomerReactivated` event is recorded

#### Scenario: Operator-lift of an auto-managed Hold is not offered and is rejected by the domain

- **GIVEN** a Customer carrying an active `kyc` Hold
- **WHEN** the Holds table is inspected
- **THEN** the `kyc` Hold row exposes no `Lift` action (`admin` / `fraud` / `compliance` / `credit` / `chargeback_review` / `storage_payment_failed` rows do)
- **AND** an attempt to lift the `kyc` Hold through the domain raises `IllegalHoldLift`, surfaced as a danger notification, with the Hold unchanged and no event recorded

#### Scenario: Lifting an already-lifted Hold is rejected

- **GIVEN** a Hold already in status `lifted`
- **WHEN** an operator attempts to lift it
- **THEN** the domain raises `IllegalHoldLift` (not-active), the console surfaces it as a notification, and no `CustomerHoldLifted` event is recorded

#### Scenario: A Hold can be placed on each of the three scopes

- **GIVEN** a Customer with a co-provisioned Account and at least one Profile
- **WHEN** an operator places a Hold with scope `customer`, `account`, or `profile`
- **THEN** `PlaceHold` is invoked with the matching `HoldScope` and the scope id resolved from the Customer, and the placed Hold carries that `scope_type` and `scope_id`

#### Scenario: The Holds table renders the Customer's Holds read-only across scopes

- **WHEN** an operator opens a Customer that has Holds at `customer`, `account` and `profile` scope
- **THEN** the table shows each Hold's type, scope, status, reason, placement actor + moment (and lift actor + moment once lifted), with no field editable in place

_Source: openspec/specs/party-registry/spec.md (Hold Registry; Hold Lifecycle and Lift Discipline; Hold Events; Hold-Driven Status Coupling) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.8 (Hold — unified blocking mechanism; three scopes × **eight** types — canon MVP-DEC-008; audit metadata), §4.8.1 (Hold semantics — concurrency, cascade, isolation; DEC-160 per-type lift discipline; N2 trigger-agnostic registry, manual-placement path for every type), §15.8 (the two finance-driven types consumed from Module E), §14.8 BR-K-Hold-1..5 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-10 (Hold place + lift on each scope/type; audit metadata + events), AC-K-FSM-11 (per-type lift discipline — `kyc`/`payment` auto-lift, `admin`/`fraud`/`compliance`/`credit` + the two finance-driven types operator-lift), AC-K-EVT-18/19, AC-K-FSM-9 (Account FSM driven by Account-Hold coupling) · canon c-mless/documentation MVP_Decisions_Register_v0.1.md:133 (MVP-DEC-008) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (Place / lift a Customer Hold — the cross-cutting compliance action), §5.2 (no SoD mandated for Hold place/lift), §1.3 (`actor_role` envelope) · app/Modules/Parties/Actions/PlaceHold.php, LiftHold.php · app/Modules/Parties/Models/Hold.php · app/Modules/Parties/Enums/{HoldType,HoldScope,HoldStatus}.php · app/Modules/Parties/Events/{CustomerHoldPlaced,CustomerHoldLifted}.php · app/Modules/Parties/Exceptions/IllegalHoldLift.php · decisions/2026-07-01-adopt-dec-008-hold-types-8.md · decisions/2026-06-18-hold-lift-discipline-per-type.md · decisions/2026-06-19-hold-status-coupling.md · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md · decisions/2026-06-21-operator-console-operand-enum-carveout.md._

### Requirement: Operator manages a Customer's KYC verification through the console

The console SHALL let an operator drive the Customer's KYC verification lifecycle through the Customer view, routing every write through the Module K domain Actions (`RequireKyc` / `RecordKycVerified` / `RecordKycRejected`) and never an Eloquent write. The KYC FSM (`not_required → pending → verified | rejected`) is an **independent** axis carried on the Customer, distinct from the Customer status FSM and from the sanctions axis. Each verb SHALL be **form-less** and SHALL present **no** "second actor required" affordance — KYC is single-operator (the spec mandates no separation-of-duties for KYC). `kyc_status` is a **state** enum rendered and predicated through its model cast (`->value`), never imported.

**Require KYC.** A header action invoking `RequireKyc` SHALL transition `kyc_status` from `not_required` (or unset) to `pending`, set `kyc_required`, and — in the same domain transaction — **auto-place a Customer-scope `kyc` Hold**, recording exactly one `CustomerHoldPlaced` event. Via the shipped Hold-driven status coupling that placement SHALL suspend the Customer when it is `active` (recording `CustomerSuspended`). The verb SHALL record **no** KYC-specific domain event (the catalog names none). It SHALL be visible **iff** `kyc_status` is `not_required` or unset; a require attempted out of that from-state SHALL be rejected by the domain (`IllegalKycTransition::cannotRequire`) and surfaced as a notification without state change.

**Record KYC verified.** A header action invoking `RecordKycVerified` SHALL transition `kyc_status` from `pending` to `verified`, **auto-lift** every active `kyc` Hold (one `CustomerHoldLifted` each) and — via the coupling — reactivate the Customer when it was `suspended` and no other active Hold still covers it (recording `CustomerReactivated`). It SHALL record **no** KYC-specific event. It SHALL be visible **iff** `kyc_status` is `pending`; a verify out of that from-state SHALL be rejected (`IllegalKycTransition::cannotVerify`) and surfaced without state change.

**Record KYC rejected.** A header action invoking `RecordKycRejected` SHALL transition `kyc_status` from `pending` to `rejected`. It SHALL be **audit-only** — recording **no** domain event — and the active `kyc` Hold SHALL **remain** (the Customer stays restricted; rejection does not lift the Hold). It SHALL be visible **iff** `kyc_status` is `pending`; a reject out of that from-state SHALL be rejected (`IllegalKycTransition::cannotReject`) and surfaced without state change.

The console SHALL expose **no** Customer KYC "waive" verb — no such domain Action exists (only producer KYC is waivable). Visibility is the **complement** of each verb's domain from-state guard, so a rejected KYC transition is unreachable through the surface while the domain remains the enforcing floor for any out-of-band call.

#### Scenario: Require KYC on an active Customer flags pending, auto-places a kyc Hold, and suspends the Customer

- **GIVEN** an `active` Customer whose `kyc_status` is `not_required` (or unset)
- **WHEN** an operator requires KYC through the console
- **THEN** `RequireKyc` is invoked, `kyc_status` becomes `pending`, `kyc_required` is set, and a Customer-scope `kyc` Hold exists `active`
- **AND** the Customer becomes `suspended`, with exactly one `CustomerHoldPlaced` and one `CustomerSuspended` event recorded (`actor_role: newco_ops`, `actor_id` equal to the operator's id) and **no** KYC-specific event

#### Scenario: Record KYC verified auto-lifts the kyc Hold and reactivates the Customer

- **GIVEN** a `suspended` Customer with `kyc_status = pending`, covered only by its auto-placed `kyc` Hold
- **WHEN** an operator records KYC verified
- **THEN** `kyc_status` becomes `verified`, the `kyc` Hold becomes `lifted` with exactly one `CustomerHoldLifted` event, and the Customer becomes `active` with exactly one `CustomerReactivated` event — and **no** KYC-specific event

#### Scenario: Record KYC rejected is audit-only and the kyc Hold remains

- **GIVEN** a Customer with `kyc_status = pending` and an active `kyc` Hold
- **WHEN** an operator records KYC rejected
- **THEN** `kyc_status` becomes `rejected`, the `kyc` Hold stays `active` (the Customer stays restricted), and **no** domain event is recorded

#### Scenario: Each KYC verb is visible only in its legal from-state

- **WHEN** the Customer view surface is inspected
- **THEN** **Require KYC** is offered iff `kyc_status` is `not_required` or unset, and **Record KYC verified** and **Record KYC rejected** are offered iff `kyc_status` is `pending`
- **AND** an out-of-state invocation through the domain (require not from `not_required`/unset, verify or reject not from `pending`) raises an `IllegalKycTransition`, surfaced as a danger notification, with `kyc_status` and the domain-event log unchanged

#### Scenario: The console exposes no Customer KYC waive verb

- **WHEN** the Customer view surface is inspected
- **THEN** it exposes Require KYC, Record KYC verified and Record KYC rejected, exposes **no** KYC "waive" action, and no KYC verb presents a "second actor required" affordance

#### Scenario: The KYC verbs leave the sanctions axis untouched

- **GIVEN** a Customer with `sanctions_status = passed`
- **WHEN** an operator requires KYC and later records it verified
- **THEN** the Customer's `sanctions_status` stays `passed` throughout (KYC and sanctions are independent) and no sanctions screening event is recorded

_Source: openspec/specs/party-registry/spec.md (Customer KYC Lifecycle; Hold Lifecycle and Lift Discipline; Hold-Driven Status Coupling; Demand-Side Status Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §9.1 (KYC four-state lifecycle `not_required → pending → verified | rejected`; the `kyc` Hold auto-places on `pending` and auto-lifts on `verified`; manual-first launch posture), §9.4 (KYC and sanctions are independent), §9.5 (operator records the state — manual-first), §4.8 (Hold — unified blocking mechanism) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-3 (KYC FSM separate from Customer FSM; auto-Hold place on `pending`, auto-lift on `verified`), §2 AC-K-J-7 (KYC flag → verify journey with auto-Hold place then lift), §3 AC-K-FSM-5 (KYC and sanctions independent) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (Manage a KYC verification — operator action), §5.2 (no SoD mandated for KYC) · app/Modules/Parties/Actions/{RequireKyc,RecordKycVerified,RecordKycRejected}.php · app/Modules/Parties/Enums/KycStatus.php · app/Modules/Parties/Exceptions/IllegalKycTransition.php · app/Modules/Parties/Events/{CustomerHoldPlaced,CustomerHoldLifted,CustomerSuspended,CustomerReactivated}.php · decisions/2026-06-18-hold-lift-discipline-per-type.md · decisions/2026-06-19-hold-status-coupling.md · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md · decisions/2026-06-21-operator-console-operand-enum-carveout.md._

### Requirement: Operator records a Customer's sanctions screening through the console

The console SHALL let an operator record a sanctions-screening verdict on a Customer through the Customer view, routing the write through the Module K domain Action `RecordCustomerScreening` and never an Eloquent write. The sanctions FSM (`pending → passed | failed | under_review`) is an **independent** axis carried on the Customer, distinct from the Customer status FSM and from the KYC axis. The order-completion purchase gate that reads `sanctions_status = passed` is **Module S's** concern (Module K records the screening state; Module S enforces at checkout) and is out of this console's scope. The action SHALL be single-operator with **no** "second actor required" affordance (the spec mandates no separation-of-duties for sanctions).

A **form-bearing** header action SHALL collect a `verdict` (the four-value `SanctionsStatus` operand — `pending | passed | failed | under_review`) and a `trigger_source` (a restricted `ScreeningTriggerSource` operand offering only the operator-selectable `onboarding` and `compliance_ad_hoc` — the automated `cadence` and `aml_threshold` sources, deferred at launch per the manual-first posture, SHALL **never** be offered), constructing both operand enums from the form values and invoking `RecordCustomerScreening`. Recording SHALL set `sanctions_status`, stamp `last_screening_at`, set `next_rescreen_at` to **12 months** after that moment, and set `screening_trigger_source`.

Recording SHALL record **exactly one** of `CustomerOnboardingScreeningPassed` / `CustomerOnboardingScreeningFailed` / `CustomerRescreeningPassed` / `CustomerRescreeningFailed`, selected by **verdict × source**: `passed` + `onboarding` → onboarding-passed; `failed` + `onboarding` → onboarding-failed; `passed` + a non-onboarding source → rescreening-passed; `failed` + a non-onboarding source → rescreening-failed. A `pending` or `under_review` verdict SHALL update `sanctions_status` but record **no** screening event.

The `onboarding` source is **first-screening-only**: once `last_screening_at` is set, `onboarding` SHALL no longer be offered in the form, and a domain re-onboarding attempt SHALL be rejected (`IllegalSanctionsTransition::onboardingAlreadyScreened`) and surfaced as a notification without state change.

#### Scenario: An onboarding screening with a passed verdict records the onboarding-passed event and stamps the screening fields

- **GIVEN** a Customer never screened (`last_screening_at` unset)
- **WHEN** an operator records a screening with verdict `passed` and source `onboarding`
- **THEN** `sanctions_status` becomes `passed`, `last_screening_at` is stamped, `next_rescreen_at` is 12 months later, `screening_trigger_source` is `onboarding`, and exactly one `CustomerOnboardingScreeningPassed` event is recorded with `actor_role: newco_ops` and `actor_id` equal to the operator's id

#### Scenario: A compliance-ad-hoc re-screen with a failed verdict records the rescreening-failed event

- **GIVEN** a Customer already screened once (`last_screening_at` set)
- **WHEN** an operator records a screening with verdict `failed` and source `compliance_ad_hoc`
- **THEN** `sanctions_status` becomes `failed` and exactly one `CustomerRescreeningFailed` event is recorded with `actor_role: newco_ops`

#### Scenario: A pending or under-review verdict updates the state but records no event

- **WHEN** an operator records a screening with verdict `under_review` (or `pending`)
- **THEN** `sanctions_status` is updated to that verdict and **no** sanctions screening event is recorded

#### Scenario: The form offers only the operator-selectable sources, and onboarding is first-screening-only

- **WHEN** the Record-screening form is inspected on a never-screened Customer
- **THEN** the `trigger_source` field offers exactly `onboarding` and `compliance_ad_hoc` (never `cadence` or `aml_threshold`) and the `verdict` field offers the four `SanctionsStatus` values
- **WHEN** the form is inspected on a Customer already screened (`last_screening_at` set)
- **THEN** `onboarding` is no longer offered, and an attempt to re-record an `onboarding` screening through the domain raises `IllegalSanctionsTransition::onboardingAlreadyScreened`, surfaced as a notification with `sanctions_status` and the domain-event log unchanged

#### Scenario: The screening sets the next re-screen twelve months ahead

- **WHEN** an operator records any screening
- **THEN** `next_rescreen_at` is exactly 12 months after the stamped `last_screening_at`

#### Scenario: Recording a screening leaves the KYC axis untouched

- **GIVEN** a Customer with `kyc_status = pending`
- **WHEN** an operator records a sanctions screening
- **THEN** the Customer's `kyc_status` stays `pending` (sanctions and KYC are independent)

_Source: openspec/specs/party-registry/spec.md (Customer Sanctions Screening Lifecycle; Demand-Side Status Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §9.2 (sanctions four-state lifecycle `pending → passed | failed | under_review`; trigger sources; 12-month re-screen cadence; the four screening events), §9.3 (the order-completion gate is Module S — Module K records the state and is sanctions-blind at the gate), §9.4 (sanctions and KYC independent), §9.5 (manual-first launch posture — operator runs the check and records the state, automation deferred) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-4 (sanctions FSM separate; re-screening events), §3 AC-K-FSM-5 (KYC and sanctions independent), §5 AC-K-EVT-12 (the four sanctions screening events — onboarding + 12-month re-screen) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (Review a sanctions-screening match — operator action), §5.2 (no SoD mandated for sanctions) · app/Modules/Parties/Actions/RecordCustomerScreening.php · app/Modules/Parties/Enums/{SanctionsStatus,ScreeningTriggerSource}.php · app/Modules/Parties/Exceptions/IllegalSanctionsTransition.php · app/Modules/Parties/Events/{CustomerOnboardingScreeningPassed,CustomerOnboardingScreeningFailed,CustomerRescreeningPassed,CustomerRescreeningFailed}.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md · decisions/2026-06-21-operator-console-operand-enum-carveout.md._

### Requirement: Operator creates a Profile through the console

The console SHALL let an operator create a **Profile** — Module K's per-Club membership entry — through a manual create surface that collects a target **Customer** and a target **Club** (both selected from within-module reads, no cross-module import beyond `{Models}`), invoking `CreateProfile($customerId, $clubId)` and returning the created model (never `$model->save()`). The surface SHALL construct **no** `Parties\Enums` operand enum and stay within the `{Models, Actions}` import surface. A created Profile SHALL be born **`Applied`** and SHALL record exactly one `ProfileCreated` domain event, tagged module `parties`, carrying the `actor_role: newco_ops` audit envelope. The create surface SHALL expose **no** `state` field and SHALL **not** collect a `tier`, `role`, or inviter. A duplicate non-terminal Profile for the same Customer–Club pair SHALL be rejected (`DuplicateProfileForClub`) and surfaced. A create targeting a Club that is **not `active`** (`sunset` or `closed`) SHALL be rejected (`ClubNotAcceptingMemberships`) and surfaced on the form, with no Profile and no event created (per *Profile — Multi-Profile Membership*); the Club picker SHOULD present `active` Clubs.

#### Scenario: Valid input creates an applied Profile and records ProfileCreated

- **WHEN** an operator submits a valid Customer + an `active` Club through the create surface
- **THEN** `CreateProfile` is invoked and a Profile exists in `Applied` for that Customer and Club, with `auto_renew` inherited from the Club default
- **AND** exactly one `ProfileCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `Profile`

#### Scenario: A duplicate non-terminal Profile is rejected and surfaced

- **GIVEN** a Customer with a non-terminal Profile in a Club
- **WHEN** an operator submits a second Profile for the same Customer–Club pair
- **THEN** the domain raises a `DuplicateProfileForClub`, the console surfaces it on the form, and no second Profile and no event are created

#### Scenario: A sunset or closed Club is rejected and surfaced

- **WHEN** an operator submits a Profile targeting a Club in `sunset` or `closed`
- **THEN** the domain raises a `ClubNotAcceptingMemberships`, the console surfaces it on the form, and no Profile and no `ProfileCreated` event are created

#### Scenario: The create surface exposes the membership operands and no lifecycle field

- **WHEN** the Profile create surface is inspected
- **THEN** it exposes a Customer select and a Club select, exposes no `state`/`tier`/`role` field, and a created Profile is born `Applied`

_Source: openspec/specs/party-registry/spec.md (Profile — Multi-Profile Membership; Profile Auto-Renewal Preference; Demand-Side Activation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.2.1, §4.3 (**sunset blocks new memberships**) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-2, **AC-K-FSM-6 / §4.4 AC-K-BR-Club-3 (new-membership creation rejected when Club is `sunset`)** · app/Modules/Parties/Actions/CreateProfile.php · app/Modules/Parties/Exceptions/DuplicateProfileForClub.php · MODIFIES the *Operator creates a Profile through the console* requirement (openspec/specs/operator-console/spec.md — which rejected only `DuplicateProfileForClub` and applied **no Club-status gate**)._

### Requirement: Operator approves or declines a Profile membership through the console

The console SHALL surface the Profile membership-approval verbs — **approve** (`ApproveProfile`, `Applied → Approved`) and **decline** (`DeclineProfile`, `Applied → Rejected`) — on the Profile view, each invoking the corresponding domain Action and never an Eloquent write. These are the **one retained producer write** at launch (membership approve/decline, the L-PP / K-Q4 producer write); exercised through the operator console they carry `actor_role: newco_ops` (DEC-083 / DEC-115 admin-parity). Each verb SHALL be **form-less** and **visibility-gated to the `Applied` from-state**, so the from-state rejection (`IllegalProfileTransition`) is unreachable through the surface. **Approve** SHALL record **no** Profile-named event; on the Customer's **first-ever** approval into any Club it SHALL additionally record exactly one `OriginatingClubLocked` event and set the Customer's `originating_club_id` (the one-shot Originating-Club lock, idempotent thereafter). **Decline** SHALL record **no** domain event (the `state = rejected` write is the audit record). The console SHALL author **no** `Applied → WaitingList` transition — that edge has no writer at launch and is deferred to a future change.

#### Scenario: Approve an applied Profile and lock the Originating Club on first approval

- **GIVEN** a Customer whose `originating_club_id` is unset, with an `Applied` Profile
- **WHEN** an operator approves the Profile
- **THEN** the Profile becomes `Approved`, the Customer's `originating_club_id` is set to that Profile's Club, and exactly one `OriginatingClubLocked` event is recorded with `actor_role: newco_ops`
- **WHEN** the operator later approves a second Club's `Applied` Profile for the same Customer
- **THEN** that Profile becomes `Approved` and **no** further `OriginatingClubLocked` event is recorded (the lock is one-shot)

#### Scenario: Decline an applied Profile is terminal and event-silent

- **GIVEN** an `Applied` Profile
- **WHEN** an operator declines the Profile
- **THEN** the Profile becomes `Rejected` and no domain event is recorded (the `state = rejected` write is the audit record)

#### Scenario: Approve and decline are offered only from Applied

- **WHEN** a Profile not in `Applied` is viewed
- **THEN** neither approve nor decline is offered, and an out-of-state approve/decline driven against the domain is rejected (`IllegalProfileTransition`) with state and the event log unchanged

_Source: openspec/specs/party-registry/spec.md (Profile Membership Approval; Demand-Side Activation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.2.1 (`Applied → Approved/Rejected`; `OriginatingClubLocked` on first-ever approval), §3.1 (the one retained producer write — membership approve/decline, L-PP / K-Q4; operator-exercisable via `newco_ops`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-2 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §2 + §3.K (the one producer write; operator-driven parity DEC-083) · app/Modules/Parties/Actions/{ApproveProfile,DeclineProfile}.php · app/Modules/Parties/Events/OriginatingClubLocked.php · app/Modules/Parties/Exceptions/IllegalProfileTransition.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md._

### Requirement: Operator advances a Profile through its lifecycle

The console SHALL surface the Profile's post-approval lifecycle transitions on the Profile view, each invoking its domain Action and never an Eloquent write, each **form-less** and **visibility-gated to its from-state**: **activate** (`ActivateProfile`, `Approved → Active`, recording `ProfileActivated`), **suspend** (`SuspendProfile`, `Active → Suspended`, recording `ProfileSuspended`), **reactivate** (`ReactivateProfile`, `Suspended → Active`, recording `ProfileReactivated`), **lapse** (`LapseProfile`, `Active → Lapsed`, recording `ProfileExpired`), **renew** (`RenewProfile`, `Lapsed → Active` within the 30-day grace, recording `ProfileRenewed`), **cancel** (`CancelProfile`, `Active | Lapsed → Cancelled`, **audit-only — no event**) and **deactivate** (`DeactivateProfile`, `Active → Inactive`, recording `ProfileInactive`). Each recorded event carries the `actor_role: newco_ops` audit envelope.

**Activation SHALL ship uncapped** — the Hero-Package capacity invariant (`Active` Profiles ≤ the Hero-Package Allocation `qty`) is a **deferred Module-A seam**; the console drives `ActivateProfile` without a capacity check and surfaces no cap. **Suspension SHALL be state-preserving** — it changes only `state`; vouchers, orders, allocation reservations and Club Credit are untouched (the Club-Credit freeze is enforced at the redemption site, a club-credit seam). Each from-state-gated rejection (`IllegalProfileTransition`) SHALL be unreachable through the surface (the verb is hidden off its from-state) and SHALL be rejected by the domain when driven directly, leaving state and the event log unchanged. **`renew` is the sole exception**: visible from `Lapsed`, a renew attempted past the 30-day grace SHALL be rejected by the domain and surfaced as a danger notification without state change (the grace sub-gate is domain-internal, not expressible as a visibility predicate). `Cancelled` and `Inactive` are **terminal soft-delete** states — the row is never hard-deleted and stays queryable.

#### Scenario: Activate an approved Profile, uncapped

- **GIVEN** an `Approved` Profile
- **WHEN** an operator activates it
- **THEN** the Profile becomes `Active` and exactly one `ProfileActivated` event is recorded with `actor_role: newco_ops`, with no capacity check applied (the Hero-Package cap is a deferred Module-A seam)

#### Scenario: Suspend then restore an active Profile, state-preserving

- **GIVEN** an `Active` Profile with an active voucher and an active Club Credit
- **WHEN** an operator suspends it
- **THEN** the Profile becomes `Suspended`, exactly one `ProfileSuspended` event is recorded, and the voucher and Club Credit are unchanged (suspension changes only `state`)
- **WHEN** the operator reactivates it
- **THEN** the Profile becomes `Active` and exactly one `ProfileReactivated` event is recorded

#### Scenario: Lapse, then renew within the 30-day grace

- **GIVEN** an `Active` Profile
- **WHEN** an operator lapses it
- **THEN** the Profile becomes `Lapsed` and exactly one `ProfileExpired` event is recorded
- **WHEN** the operator renews it within 30 days of lapse
- **THEN** the Profile becomes `Active` and exactly one `ProfileRenewed` event is recorded

#### Scenario: A renew past the 30-day grace is rejected and surfaced

- **GIVEN** a Profile that lapsed more than 30 days ago
- **WHEN** an operator drives `renew` (visible from `Lapsed`)
- **THEN** the domain raises an `IllegalProfileTransition`, the console surfaces a danger notification, and the Profile stays `Lapsed` with no event recorded

#### Scenario: Cancel is terminal, audit-only, and the row is preserved

- **GIVEN** an `Active` (or `Lapsed`) Profile
- **WHEN** an operator cancels it
- **THEN** the Profile becomes `Cancelled`, no domain event is recorded (audit-only), and the row remains queryable (soft-delete, never hard-deleted)

#### Scenario: Deactivate records ProfileInactive

- **GIVEN** an `Active` Profile
- **WHEN** an operator deactivates it
- **THEN** the Profile becomes `Inactive` and exactly one `ProfileInactive` event is recorded

#### Scenario: Each lifecycle verb is offered only from its from-state

- **WHEN** a Profile is viewed in any given state
- **THEN** only the verbs valid from that state are offered (activate from `Approved`; suspend/lapse/deactivate from `Active`; reactivate from `Suspended`; renew from `Lapsed`; cancel from `Active` or `Lapsed`), and an out-of-state transition driven against the domain is rejected (`IllegalProfileTransition`) with state and the event log unchanged

_Source: openspec/specs/party-registry/spec.md (Profile Activation; Profile Suspension and Restoration; Profile Lapse and Grace Renewal; Profile Cancellation and Deactivation; Demand-Side Status Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.2.1 (full Profile lifecycle), §13 (Hero-Package capacity invariant — Module-A-owned, enforced at transition into `Active`), §10.1 (suspension state-preservation) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-2 (FSM + events), AC-K-FSM-2a (suspension state-preservation), AC-K-FSM-12 (30-day lapse grace, DEC-034), AC-K-FSM-13 (terminal soft-delete) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (run suspension/offboarding — operator surface) · app/Modules/Parties/Actions/{ActivateProfile,SuspendProfile,ReactivateProfile,LapseProfile,RenewProfile,CancelProfile,DeactivateProfile}.php · app/Modules/Parties/Events/{ProfileActivated,ProfileSuspended,ProfileReactivated,ProfileExpired,ProfileRenewed,ProfileInactive}.php · app/Modules/Parties/Exceptions/IllegalProfileTransition.php · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: Operator advances a Customer's Account through its status lifecycle

The console SHALL surface the co-provisioned **Account**'s status transitions on the Customer view — **suspend** (`SuspendAccount`, `active → suspended`), **reactivate** (`ReactivateAccount`, `suspended → active`) and **close** (`CloseAccount`, `active | suspended → closed`) — each invoking its domain Action via the Customer's 1:1 Account (`account->id`) and never an Eloquent write. The Account has **no** activation verb (it is born `active` when co-provisioned with the Customer; there is no `ActivateAccount`). Each verb SHALL be **form-less** and **visibility-gated to its from-state**, so the from-state rejection (`IllegalAccountTransition`) is unreachable through the surface. All three transitions are **audit-only** — no Account domain event exists; the operator action carries the `actor_role: newco_ops` audit envelope. Account status is **orthogonal** to the Customer status FSM and to Profile state: an Account transition cascades to neither.

#### Scenario: Suspend then reactivate an Account, event-silently

- **GIVEN** a Customer whose co-provisioned Account is `active`
- **WHEN** an operator suspends the Account
- **THEN** the Account becomes `suspended` with no domain event recorded (audit-only), and neither the Customer status nor any Profile state changes
- **WHEN** the operator reactivates the Account
- **THEN** the Account becomes `active` with no domain event recorded

#### Scenario: Close an Account is terminal

- **GIVEN** an `active` (or `suspended`) Account
- **WHEN** an operator closes it
- **THEN** the Account becomes `closed` with no domain event recorded

#### Scenario: The Account has no activation verb and rejects illegal transitions

- **WHEN** the Customer view is inspected
- **THEN** it offers suspend/reactivate/close gated to the Account's from-state and offers no Account activation verb; an out-of-state Account transition driven against the domain is rejected (`IllegalAccountTransition`) with status and the event log unchanged

_Source: openspec/specs/party-registry/spec.md (Account Status Lifecycle; Demand-Side Status Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.7 (Account — transactional container; status parallels Customer; born `active`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-9 (Account FSM `active → suspended → closed`; Account Holds drive suspend; lift drives reactivate) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (operator-driven back-office write) · app/Modules/Parties/Actions/{SuspendAccount,ReactivateAccount,CloseAccount}.php · app/Modules/Parties/Exceptions/IllegalAccountTransition.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md._

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

