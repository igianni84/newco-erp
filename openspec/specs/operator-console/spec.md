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

The console SHALL let an operator create a Product Master via the **manual baseline path** (the LWIN/Liv-ex enrichment adapter is not shipped), invoking `CreateProductMaster`. The type-defined identity-key collision (for `WINE`: producer + product name + appellation) SHALL be surfaced to the operator as a validation error rather than an unhandled exception. A created Master SHALL be in `draft`.

#### Scenario: Valid input creates a draft Master

- **WHEN** an operator submits a valid Product Master (name, producer, appellation, region) through the create surface
- **THEN** `CreateProductMaster` is invoked, a Master exists in `draft`, and `ProductMasterCreated` is recorded with `actor_role: newco_ops`

#### Scenario: Duplicate identity key is surfaced as a validation error

- **WHEN** the submitted (producer + name + appellation) collides with an existing non-retired Master
- **THEN** the create is rejected and surfaced as a form validation error
- **AND** no Master is created and no `ProductMasterCreated` event is recorded

_Source: spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.0; spec/03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md AC-AP-INV-0; openspec/specs/product-catalog/spec.md (Product Master, BR-Identity-1)._

### Requirement: Operator advances a Product Master through the review-and-approval lifecycle

The console SHALL surface submit-for-review, reject, and activate for a Product Master, each invoking the corresponding domain action (`SubmitProductMasterForReview`, `RejectProductMasterReview`, `ActivateProductMaster`). The activation step SHALL present a **"second actor required"** affordance. The Creator → Reviewer → Approver separation-of-duties floor (distinct actors; self-approval never allowed) is enforced by the domain (`ApprovalGovernance`); a same-actor violation SHALL be rejected and surfaced to the operator as a notification. The console SHALL NOT reimplement the floor.

#### Scenario: Distinct actors complete the lifecycle

- **WHEN** operator A submits a draft Master for review and a distinct operator B activates it (with the producer active)
- **THEN** the Master becomes `active` and `ProductMasterActivated` is recorded

#### Scenario: Self-approval is rejected and surfaced

- **WHEN** the operator who performed the prior step attempts the next governance step
- **THEN** the domain rejects it, the Master is unchanged, no `ProductMasterActivated` is recorded, and the console shows a notification that a distinct actor is required

#### Scenario: Rejection keeps the Master in reviewed with notes

- **WHEN** an operator rejects a `reviewed` Master with notes
- **THEN** the Master stays `reviewed`, the rejection and notes are recorded (audit-only), and no activation event is recorded

_Source: spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §5.2; spec/03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md AC-AP-MA-1; openspec/specs/product-catalog/spec.md (Approval Governance)._

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

For each of the six spine entities the console SHALL surface submit-for-review, reject, and activate, each invoking the corresponding domain Action (`Submit<Entity>ForReview`, `Reject<Entity>Review`, `Activate<Entity>`). Submit (`draft → reviewed`) and reject (a Reviewer/Approver decision that keeps the entity in `reviewed`, recording the notes) are **audit-only** (no domain event); activate (`reviewed → active`) records the entity's verbatim `*Activated` event. The activate Action SHALL present a **"second actor required"** affordance. The Creator → Reviewer → Approver separation-of-duties floor (distinct actors; self-approval never allowed) is enforced by the domain (`ApprovalGovernance`); a same-actor (or non-operator) violation SHALL be rejected by the domain, surfaced to the operator as a notification, and leave the entity, the audit log and the domain-event log unchanged. The console SHALL NOT reimplement the floor, and SHALL surface an out-of-state transition (e.g. activate on a `draft`) as a notification without changing state.

#### Scenario: Distinct actors complete submit then activate

- **WHEN** operator A submits a `draft` spine entity for review and a distinct operator B activates the `reviewed` entity (with any parent gate satisfied)
- **THEN** the entity becomes `active` and exactly one `*Activated` event is recorded with `actor_role: newco_ops` and `actor_id` equal to operator B

#### Scenario: Self-approval is rejected and surfaced

- **WHEN** the operator who performed the prior governance step attempts the next governance step on the same entity
- **THEN** the domain rejects it, the entity is unchanged, no `*Activated` event is recorded, and the console shows a notification that a distinct actor is required

#### Scenario: Rejection keeps the entity in reviewed with notes

- **WHEN** an operator rejects a `reviewed` spine entity with notes
- **THEN** the entity stays `reviewed`, the rejection and notes are recorded (audit-only), and no `*Activated` event is recorded

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator invokes a transition on an entity not in its required from-state (e.g. activate on a `draft`)
- **THEN** the domain raises an `IllegalLifecycleTransition`, the console surfaces it as a notification, and the entity's `lifecycle_state`, audit log and domain-event log are unchanged

_Source: openspec/specs/product-catalog/spec.md (Product Lifecycle State Machine; Approval Governance; Product Lifecycle Events) · spec/02-prd/Module_0_PRD_v0.3-MVP.md §4.1, §4.2 (Creator → Reviewer → Approver; self-approval never allowed; review checkpoint audit-only), §4.3 (rejection stays in reviewed), §13.2 BR-Lifecycle-1/2/6, §14.2 · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md §3 AC-0-FSM-1/8/9 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §5.2 · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md (SoD surfaced, not reimplemented) · app/Modules/Catalog/Actions/Submit*ForReview.php, Reject*Review.php, Activate*.php · lang/en/catalog.php (`lifecycle.*`, `approval.*`)._

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

