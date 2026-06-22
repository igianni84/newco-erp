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

The console SHALL surface the Producer's status-FSM transitions — **activate** (`ActivateProducer`, `draft → active`, recording `ProducerActivated`) and **retire** (`RetireProducer`, `active → retired`, recording `ProducerRetired`) — each invoking the corresponding domain Action and recording its verbatim event with `actor_role: newco_ops`. Activation SHALL be **gated on KYC cleared**: the domain rejects it when the Producer's `kyc_status` is `pending` or `rejected` (cleared = `verified`, `not_required`, **or** NULL), and the console SHALL surface the rejection, leaving the Producer in `draft` with no event recorded; the console SHALL NOT re-check the gate. Retirement SHALL **cascade in-domain** to sunset every Club the Producer operates that is currently `active` (each recording a `ClubSunset` causally linked to the `ProducerRetired` root), while Clubs already `sunset`/`closed` are left unchanged — the cascade is the Action's own behaviour, and the console SHALL NOT expose a separate cascade-retire affordance. The activate action SHALL present **no** "second actor required" affordance — Producer activation is a single-operator, KYC-gated transition, not the catalog separation-of-duties governance. The console SHALL expose **no** submit, reject, or reopen action — the Producer FSM is linear (`draft → active → retired`) with no review-governance step. An out-of-state transition SHALL be surfaced as a notification without changing state.

#### Scenario: Activate a KYC-cleared draft Producer

- **WHEN** an operator activates a `draft` Producer whose `kyc_status` is cleared (`verified`, `not_required`, or NULL)
- **THEN** the Producer becomes `active` and exactly one `ProducerActivated` event is recorded with `actor_role: newco_ops` and `actor_id` equal to the operator's id

#### Scenario: Activation blocked by uncleared KYC is surfaced

- **WHEN** an operator activates a `draft` Producer whose `kyc_status` is `pending` or `rejected`
- **THEN** the domain rejects the activation, the console surfaces the reason as a notification, the Producer stays `draft`, and no `ProducerActivated` event is recorded

#### Scenario: Retire an active Producer cascades Club sunset

- **GIVEN** an `active` Producer that operates two `active` Clubs and one already-`closed` Club
- **WHEN** an operator retires the Producer
- **THEN** the Producer becomes `retired` and a `ProducerRetired` event is recorded with `actor_role: newco_ops`
- **AND** the two `active` Clubs transition to `sunset`, each recording a `ClubSunset` carrying the `ProducerRetired` event's id as its causation, while the `closed` Club is unchanged

#### Scenario: The console exposes neither a cascade-retire affordance nor the catalog governance verbs

- **WHEN** the Producer view surface is inspected
- **THEN** it exposes activate and retire but no separate cascade-retire action, no submit/reject/reopen action, and the activate action presents no "second actor required" affordance

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator activates a Producer not in `draft`, or retires a Producer not in `active`
- **THEN** the domain raises an `IllegalProducerTransition`, the console surfaces it as a notification, and the Producer's `status` and the domain-event log are unchanged

_Source: openspec/specs/party-registry/spec.md (Producer Lifecycle; Supply-Side Lifecycle Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.4 (Producer FSM `draft → active → retired`; activation requires KYC cleared; retirement preserves Product Masters, blocks new activations), §10.2 (Producer offboarding cascade → Club sunset), §14.5 BR-K-Producer-2/4, §15.4 (`ProducerActivated`, `ProducerRetired`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-7 (activation gated on KYC cleared — positive `verified`/`not_required`, negative `pending`/`rejected`), §2 AC-K-J-10/AC-K-J-19, §5 AC-K-EVT-8, §6 AC-K-XM-2 (Module 0 consumes these events) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.0/§5.2 · app/Modules/Parties/Actions/ActivateProducer.php, RetireProducer.php, SunsetClub.php · app/Modules/Parties/Exceptions/IllegalProducerTransition.php · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

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

The console SHALL let an operator create a **Club** — a Producer-operated membership group — through a manual create surface that collects the Club's **display name**, an operating **Producer** (a required picker), and a **registration flow type** (required), plus an optional **fee** (a minor-unit amount + an ISO 4217 currency, assembled into the `Money` value object), a **generates-credit** flag (default true) and an **invite-only** flag (default false), invoking `CreateClub` and returning the created model (never `$model->save()`). The create surface SHALL construct the `ClubRegistrationFlowType` **operand enum** from the selected value to perform the write-through (the carve-out this change widens — ADR 2026-06-21). A created Club SHALL be born **`active`** and SHALL record exactly one `ClubCreated` domain event, tagged module `parties`, carrying the `actor_role: newco_ops` audit envelope. A Club's operating Producer is **required**: a create referencing a Producer that does not exist SHALL be rejected (`MissingClubProducer`) and surfaced on the form, with no Club created. The create surface SHALL expose **no** status field — `status` is born `active` by the Action, never set at creation.

#### Scenario: Valid input creates an active Club and records ClubCreated

- **WHEN** an operator submits a valid Club (display name, an existing operating Producer, a registration flow type) through the create surface
- **THEN** `CreateClub` is invoked and a Club exists in `active` with its attributes persisted
- **AND** exactly one `ClubCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `Club`

#### Scenario: The optional fee is assembled into Money when provided

- **WHEN** an operator creates a Club supplying a fee amount and currency
- **THEN** the Club's `fee` is the `Money` of that minor-unit amount and currency
- **WHEN** an operator creates a Club leaving the fee blank
- **THEN** the Club is created with no fee (null)

#### Scenario: Creating a Club requires an existing operating Producer

- **WHEN** an operator submits a Club whose selected operating Producer does not exist
- **THEN** the domain raises a `MissingClubProducer`, the console surfaces it on the form, and no Club and no `ClubCreated` event are created

#### Scenario: The create surface exposes the Club attributes and no lifecycle field

- **WHEN** the Club create surface is inspected
- **THEN** it exposes display name, operating Producer, registration flow type, fee, generates-credit and invite-only fields, and exposes no `status` field

_Source: openspec/specs/party-registry/spec.md (Club; Spine Creation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.3 (Club — Producer-operated; operating-Producer required + immutable; born `active`; fee/credit/flags/registration-flow config; "Club creation is a direct operator action") , §15.3 (`ClubCreated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-6 (Club FSM rooted at `active`), §4.4 AC-K-BR-Club-1 (operating Producer required), §5.3 AC-K-EVT-7 (`ClubCreated` on creation) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (Club config = operator action), §2.1 (operator-driven back-office write), §1.3 (`actor_role` envelope) · spec/03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md §3 AC-AP-INV-K (Club within the producer-onboarding operator surface) · app/Modules/Parties/Actions/CreateClub.php · app/Modules/Parties/Enums/ClubRegistrationFlowType.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md · decisions/2026-06-21-operator-console-operand-enum-carveout.md._

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

The console SHALL let an operator create a **ProducerAgreement** — the commercial agreement governing a Producer relationship — through a manual create surface that collects a required **Producer** (a picker), an optional **Club** narrowing (a picker; blank = a Producer-wide agreement), optional **term start** and **term end** dates, and an optional **settlement cadence** (a free-text string — the D19 Module-E seam), invoking `CreateProducerAgreement` and returning the created model (never `$model->save()`). All inputs are ids, dates, and a string — the surface constructs **no** operand enum and stays within the `{Models, Actions}` import surface. A created ProducerAgreement SHALL be born **`draft`** and SHALL record exactly one `ProducerAgreementCreated` event with `actor_role: newco_ops`. The Producer reference is **required**: a create referencing a Producer that does not exist SHALL be rejected (`MissingAgreementProducer`) and surfaced on the form. The single-active-per-scope invariant SHALL **not** be enforced at creation — draft agreements are created freely (it binds only at activation). The create surface SHALL expose **no** status field — `status` is born `draft`, never set at creation.

#### Scenario: Valid input creates a draft ProducerAgreement and records ProducerAgreementCreated

- **WHEN** an operator submits a valid ProducerAgreement (an existing Producer, optionally a Club and term/cadence) through the create surface
- **THEN** `CreateProducerAgreement` is invoked and a ProducerAgreement exists in `draft` with its attributes persisted
- **AND** exactly one `ProducerAgreementCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `ProducerAgreement`

#### Scenario: A Producer-wide agreement omits the Club narrowing

- **WHEN** an operator creates a ProducerAgreement leaving the Club picker blank
- **THEN** the agreement is created with a null `club_id` (Producer-wide scope)

#### Scenario: Creating a ProducerAgreement requires an existing Producer

- **WHEN** an operator submits a ProducerAgreement whose selected Producer does not exist
- **THEN** the domain raises a `MissingAgreementProducer`, the console surfaces it, and no agreement and no event are created

#### Scenario: Draft creation does not enforce the single-active-per-scope invariant

- **WHEN** an operator creates two draft ProducerAgreements in the same Producer scope
- **THEN** both are created in `draft`, each recording its own `ProducerAgreementCreated` (the single-active rule binds only at activation)

#### Scenario: The create surface exposes the agreement attributes and no lifecycle field

- **WHEN** the ProducerAgreement create surface is inspected
- **THEN** it exposes Producer, Club, term start, term end and settlement cadence fields, and exposes no `status` field

_Source: openspec/specs/party-registry/spec.md (ProducerAgreement; Spine Creation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.6 (ProducerAgreement — NewCo net-new entity; required Producer reference + optional Club narrowing, both shapes admitted; term dates, settlement cadence [D19], placeholders), §4.6.1 (born `draft`; terms may be incomplete), §15.5 (`ProducerAgreementCreated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §2 AC-K-J-11 (operator drafts against an active Producer → born `draft`), §3 AC-K-FSM-8 (FSM rooted at `draft`), §4.6 AC-K-BR-Agreement-1 (scope = Producer-wide OR per-Club) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (draft ProducerAgreement = operator action), §2.1/§3.1 (operator action by definition), §1.3 (`actor_role` envelope) · spec/03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md §3 AC-AP-INV-K (ProducerAgreement within the producer-onboarding operator surface) · app/Modules/Parties/Actions/CreateProducerAgreement.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md._

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

The console SHALL let an operator create a **Customer** — Module K's natural-person registry entry — through a manual create surface that collects the Customer's **email**, **name**, a **preferred currency** (an ISO 4217 code assembled into the platform `Currency` value object) and a **preferred locale** (a supported-locale value), plus an optional **phone** and an optional **date of birth**, invoking `CreateCustomer` and returning the created model (never `$model->save()`). All create operands are platform-level (`App\Platform\Money\Currency`, `App\Platform\I18n\SupportedLocale`) or scalar, so the surface constructs **no** `Parties\Enums` operand enum and stays within the `{Models, Actions}` import surface. A created Customer SHALL be born **`pending`** and SHALL **co-provision exactly one Account** (born `Personal` / `active`) in the same transaction; the Account is event-silent (there is no `AccountCreated` event — the console SHALL NOT invent one). The create SHALL record exactly one `CustomerCreated` domain event, tagged module `parties`, carrying the `actor_role: newco_ops` audit envelope. The create SHALL **not** create any Profile — a new Customer has its Account and zero Profiles (Profile creation is a separate, deferred surface). The email is **unique**: a create whose email already exists SHALL be rejected (`DuplicateCustomerEmail`) and surfaced on the form, with no Customer and no event created. The create surface SHALL expose **no** status field — `status` is born `pending` by the Action, never set at creation.

#### Scenario: Valid input creates a pending Customer with a co-provisioned Account and records CustomerCreated

- **WHEN** an operator submits a valid Customer (email, name, preferred currency, preferred locale) through the create surface
- **THEN** `CreateCustomer` is invoked and a Customer exists in `pending` with its attributes persisted and exactly one co-provisioned Account in `active`
- **AND** exactly one `CustomerCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `Customer`

#### Scenario: A duplicate email is rejected and surfaced

- **WHEN** an operator submits a Customer whose email already belongs to an existing Customer
- **THEN** the domain raises a `DuplicateCustomerEmail`, the console surfaces it on the email field, and no Customer, no Account and no `CustomerCreated` event are created

#### Scenario: The create surface exposes the identity fields, no lifecycle field, and creates no Profile

- **WHEN** the Customer create surface is inspected
- **THEN** it exposes email, name, preferred currency, preferred locale, phone and date-of-birth fields, exposes no `status` field, and a created Customer has zero Profiles

_Source: openspec/specs/party-registry/spec.md (Customer Identity; Account — Billing Container; Spine Creation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (Customer — natural-person registry; born `pending`), §4.7 (Account — transactional container; one Customer = one Account, auto-provisions; payment-provider reference created lazily, not at registration) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §2 AC-K-J-1 (direct registration; Account + Party auto-provision), §5 AC-K-EVT-1 (`CustomerCreated` on creation) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (onboard/edit a Customer — operator-driven), §2.1 (operator-driven back-office write), §1.3 (`actor_role` envelope) · app/Modules/Parties/Actions/CreateCustomer.php · app/Modules/Parties/Exceptions/DuplicateCustomerEmail.php · app/Modules/Parties/Events/CustomerCreated.php · app/Platform/Money/Currency.php · app/Platform/I18n/SupportedLocale.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md._

### Requirement: Operator advances a Customer through its status lifecycle

The console SHALL surface the Customer's status-FSM transitions — **activate** (`ActivateCustomer`, `pending → active`, recording `CustomerActivated`), **suspend** (`SuspendCustomer`, `active → suspended`, recording `CustomerSuspended`), **reactivate** (`ReactivateCustomer`, `suspended → active`, recording `CustomerReactivated`) and **close** (`CloseCustomer`, `active | suspended → closed`, recording `CustomerClosed`) — each invoking the corresponding domain Action and recording its verbatim event with `actor_role: newco_ops`. These direct verbs are the **BR-K-Customer-1 "manual" suspension/reactivation path**; the Hold-mediated path (`PlaceHold` / `LiftHold`, whose coupling also moves Customer status) is **out of scope** for this slice and belongs to the compliance console — the two paths coexist by design (ADR 2026-06-19). Each verb SHALL be **form-less** and SHALL present **no** "second actor required" affordance — Customer lifecycle is single-operator (the spec mandates no separation-of-duties for Customer). The console SHALL expose **no** submit, reject, or reopen action (the Customer FSM is not review-governed), and **no** Hold, KYC, sanctions, Account-lifecycle or Profile-lifecycle verb.

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

#### Scenario: The console exposes the status verbs and none of the governance, Hold, or compliance verbs

- **WHEN** the Customer view surface is inspected
- **THEN** it exposes activate, suspend, reactivate and close, no submit/reject/reopen action, no place-hold/lift-hold/KYC/sanctions/account/profile action, and no verb presents a "second actor required" affordance

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator suspends a Customer not in `active`, reactivates one not in `suspended`, or closes one in `pending`
- **THEN** the domain raises an `IllegalCustomerTransition`, the console surfaces it as a notification, and the Customer's `status` and the domain-event log are unchanged

_Source: openspec/specs/party-registry/spec.md (Customer Onboarding Activation; Customer Suspension and Closure; Demand-Side Status Events; Hold-Driven Status Coupling) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (status FSM `pending → active → suspended → closed`; T&C/privacy a hard gate on `pending → active`), §10.1 (Customer-level suspension; reactivation on Hold lift), BR-K-Customer-1 (suspension is explicit — manual or via Hold — not Profile-driven) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-1 (Customer FSM + the five status events), §4.2 AC-K-BR-Customer-1, §5 AC-K-EVT-1 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (onboard/edit + run suspension — operator surface), §5.2 (no SoD mandated for Customer) · app/Modules/Parties/Actions/{ActivateCustomer,SuspendCustomer,ReactivateCustomer,CloseCustomer}.php · app/Modules/Parties/Exceptions/IllegalCustomerTransition.php · app/Modules/Parties/Events/{CustomerActivated,CustomerSuspended,CustomerReactivated,CustomerClosed}.php · decisions/2026-06-19-hold-status-coupling.md · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: The Customer console surfaces the orthogonal compliance and membership context read-only

The console's list and infolist SHALL display the Customer's **status**, **KYC status**, **sanctions status**, the co-provisioned **Account's status**, and the Customer's **Profiles** (membership) — each rendered **read-only** via the model casts and within-module reads (no cross-module import beyond `{Models}`; state enums rendered through their cast, never imported). The KYC and sanctions lifecycles SHALL be presented as **independent** axes carried on the Customer, **distinct from** the status FSM (a Customer may show `kyc_status = verified` with `sanctions_status = pending`, or vice-versa, each rendered on its own). The console SHALL expose **no write affordance** for KYC, sanctions, Holds, Account lifecycle, or Profile lifecycle in this slice — those surfaces belong to the compliance / account / profile slices.

#### Scenario: The list and infolist render the three lifecycles, account status and profiles read-only

- **WHEN** an operator opens a Customer in the console
- **THEN** the surface shows the Customer's status, KYC status, sanctions status, Account status and Profiles, all read-only, with no field editable in place

#### Scenario: KYC and sanctions are rendered as independent axes

- **GIVEN** a Customer with `kyc_status = verified` and `sanctions_status = pending`
- **WHEN** an operator views the Customer
- **THEN** both lifecycles are displayed independently, neither collapsed into the `pending/active/suspended/closed` status FSM

#### Scenario: The console exposes no compliance or membership write action

- **WHEN** the Customer console (list, view and create surfaces) is inspected
- **THEN** it exposes no action to set KYC, record a sanctions screening, place or lift a Hold, transition the Account, or transition a Profile

_Source: openspec/specs/party-registry/spec.md (Customer KYC Lifecycle; Customer Sanctions Screening Lifecycle; Account Status Lifecycle; Profile — Multi-Profile Membership) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (KYC + sanctions carried as separate lifecycles on Customer), §9.1 (KYC four-state lifecycle), §9.2 (sanctions four-state lifecycle), §9.4 (KYC and sanctions independent — both must clear independently), §4.7 (Account status parallels Customer) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-3 (KYC separate from Customer FSM), AC-K-FSM-4 (sanctions separate), AC-K-FSM-5 (KYC and sanctions independent), AC-K-FSM-9 (Account FSM) · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md (read-projection) · decisions/2026-06-21-operator-console-operand-enum-carveout.md (state enums rendered via cast, never imported)._

