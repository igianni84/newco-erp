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

