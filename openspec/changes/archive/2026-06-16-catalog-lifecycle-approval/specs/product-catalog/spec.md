# product-catalog (delta — catalog-lifecycle-approval)

## MODIFIED Requirements

### Requirement: Lifecycle State Recorded, Transitions Deferred

Every product-catalog spine entity SHALL carry a `lifecycle_state` whose domain is exactly `draft`, `reviewed`, `active`, `retired`, and SHALL be created in the `draft` state. The spine entities SHALL now implement their state transitions, the Creator → Reviewer → Approver approval workflow, rejection handling, the activation and retirement cascades, and the Producer-activation gate, as governed by the Requirements *Product Lifecycle State Machine*, *Approval Governance*, *Producer Activation Gate*, *Producer-State Projection and Event Consumption*, *Activation Cascade*, *Retirement Cascade and Reference Integrity*, and *Product Lifecycle Events*. Consequently the spine's `*Activated` and `*Retired` domain events SHALL now be recorded on the corresponding `reviewed → active` and `active → retired` transitions; the `draft → reviewed` checkpoint SHALL remain event-silent (an internal-to-PIM, audit-only checkpoint). The cross-module downstream-reference leg of the retirement guard (Allocations, vouchers, stock, Offers) and the KYC conjunct of the Producer gate SHALL remain documented seams (the first realised by the Phase-3 referencer changes; the second tightened upstream by `parties-compliance`).

#### Scenario: A newly created entity is draft

- **WHEN** any spine entity (Product Master, Product Variant, Product Reference, Format, Case Configuration, Sellable SKU, Composite SKU) is created
- **THEN** its `lifecycle_state` is `draft`

#### Scenario: Transition paths now exist

- **WHEN** the catalog code surface is inspected
- **THEN** every spine entity exposes operations that transition it through `draft → reviewed → active → retired` (and `retired → reviewed` for re-activation), the `reviewed → active` and `active → retired` transitions record the entity's `*Activated` / `*Retired` event, and the `draft → reviewed` transition records no domain event

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 4.1 (the four-state lifecycle `draft → reviewed → active → retired`) · § 4.2 (the `draft → reviewed` checkpoint emits no distinct domain event) · § 14.2 (`*Created` = `<null> → draft`; `*Activated` = `reviewed → active`; `*Retired` = `active → retired`) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 3 AC-0-FSM-1, AC-0-FSM-8 · openspec/specs/product-catalog/spec.md (the prior "Transitions Deferred" requirement this change discharges) · openspec/changes/archive/2026-06-15-catalog-product-spine/proposal.md (the deferred-table slice boundary naming this change)._

## ADDED Requirements

### Requirement: Product Lifecycle State Machine

Every product-catalog spine entity SHALL transition through its state machine `draft → reviewed → active → retired` via explicit operator Actions that are the **sole writers** of `lifecycle_state`, each recording its audit and (where applicable) its domain event in the **same database transaction** as the state write. The four transition operations SHALL be:

- **submit for review** (`draft → reviewed`) — records an audit record only; **no** domain event (the `draft → reviewed` checkpoint is internal-to-PIM, § 4.2);
- **activate** (`reviewed → active`) — records the entity's verbatim `*Activated` domain event, subject to the *Approval Governance*, *Producer Activation Gate* and *Activation Cascade* Requirements;
- **retire** (`active → retired`) — records the entity's verbatim `*Retired` domain event, subject to the *Retirement Cascade and Reference Integrity* Requirement;
- **reopen** (`retired → reviewed`) — records an audit record only; **no** domain event. Re-activation therefore flows `retired → reviewed → active`, and the `reviewed → active` step re-checks the activation gate and the approval governance.

Every transition SHALL be **from-state guarded** against a transaction-locked re-read of the row: an operation invoked on an entity not in its required from-state SHALL be rejected with a localized `IllegalLifecycleTransition` and SHALL leave all state, the audit log and the domain-event log unchanged. The guard SHALL be evaluated against the locked re-read so two concurrent transition attempts on the same entity cannot both succeed.

#### Scenario: An entity traverses the four states

- **WHEN** a spine entity is submitted (`draft → reviewed`), activated (`reviewed → active`), then retired (`active → retired`)
- **THEN** its `lifecycle_state` is, respectively, `reviewed`, `active`, `retired`, and the activation records a `*Activated` event and the retirement a `*Retired` event, each in the same transaction as its state write

#### Scenario: The review checkpoint is event-silent

- **WHEN** a spine entity is submitted for review (`draft → reviewed`)
- **THEN** an audit record is written for the step and **no** `*Reviewed` (or any) domain event is recorded — the next domain event in the entity's lifecycle is its `*Activated`

#### Scenario: Re-activation flows back through reviewed and re-checks the gate

- **WHEN** a `retired` entity is reopened (`retired → reviewed`) and then activated (`reviewed → active`)
- **THEN** it returns to `active` only if the activation gate and approval governance pass at that moment, and a fresh `*Activated` event is recorded; the `retired → reviewed` reopen records no domain event

#### Scenario: Illegal transitions are rejected

- **WHEN** a transition operation is invoked on an entity not in its required from-state (e.g. activate on a `draft`, retire on a `reviewed`, close-the-loop on a state that does not permit it)
- **THEN** an `IllegalLifecycleTransition` is raised, the entity's `lifecycle_state` is unchanged, and no audit or domain-event row is written for the rejected attempt

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 4.1 (the four-state lifecycle; re-activation from `retired` follows the same approval workflow) · § 4.2 (the `draft → reviewed` checkpoint emits no distinct domain event) · § 4.3 (rejection stays in `reviewed`, edited in place, no revert to `draft`) · § 13.2 BR-Lifecycle-2 (four-state lifecycle; re-activation) · § 14.2 (transition → event mapping) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 3 AC-0-FSM-1, AC-0-FSM-8, AC-0-FSM-9, § 5 AC-0-EVT-1, § 4.2 AC-0-BR-Lifecycle-2, § 2 AC-0-J-10 · app/Modules/Catalog/Enums/LifecycleState.php (the four cases ship from the spine) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional recording) · CLAUDE.md invariant 8 (append-only audit; actor on every action)._

### Requirement: Approval Governance

Every commercial-impact transition (`reviewed → active`, `active → retired`, `retired → reviewed`) SHALL pass a **Creator → Reviewer → Approver** approval workflow. The actors that perform the configured steps SHALL be **distinct people — self-approval SHALL never be allowed** (the separation-of-duties floor). The **number of distinct approval roles SHALL be operational configuration** (`feedback_prd_rr_approval`): the full three-step Creator → Reviewer → Approver SHALL be the default, and a lighter two-step Creator → Approver MAY be configured; the separation-of-duties floor — each configured step performed by a distinct actor, no self-approval, every step audited — SHALL hold at any configured depth. Because these are operator decisions, a governance transition SHALL require an authenticated operator principal (`actor_role = newco_ops` with a non-null `actor_id`); a `system`/null actor cannot satisfy the distinct-actor floor and SHALL be rejected.

Each governance step SHALL be recorded in the **append-only audit trail** with the acting `actor_role` and `actor_id` (resolved from the `ActorContext` seam), the action, a before/after snapshot, and the decision; the audit trail SHALL be the system of record for which actor performed each step, and the distinct-actor guard SHALL be evaluated against it.

Rejection (`§ 4.3`): a Reviewer or Approver MAY **reject** an entity in `reviewed`; the entity SHALL **stay in `reviewed`** with the rejection recorded in the audit trail (actor, notes, decision), the Creator SHALL edit the entity **in place** — there SHALL be **no** revert-to-`draft` step — and re-submission SHALL restart the approval flow. The full rejection history (every round's notes, actor identities and timestamps) SHALL be preserved as part of the entity's permanent audit record.

#### Scenario: Self-approval is rejected

- **WHEN** the operator who created (or, in the three-step configuration, reviewed) an entity attempts to perform the approval step (`reviewed → active`) on it
- **THEN** the transition is rejected on the separation-of-duties floor, the entity stays in `reviewed`, and no `*Activated` event is recorded

#### Scenario: Distinct actors satisfy the floor at the configured depth

- **WHEN** the configured role count is three and three distinct operators perform create, review and approve in turn (or, under a two-step configuration, two distinct operators perform create and approve)
- **THEN** the entity reaches `active`, and each step is recorded in the audit trail with its distinct acting `actor_id`

#### Scenario: A non-operator context cannot perform a governance step

- **WHEN** a governance transition is attempted in a `system`/unauthenticated context (no operator `actor_id`)
- **THEN** it is rejected — the distinct-actor floor cannot be satisfied without an operator principal

#### Scenario: Rejection keeps the entity in reviewed and preserves history

- **WHEN** a Reviewer or Approver rejects an entity in `reviewed` with notes
- **THEN** the entity stays in `reviewed`, the rejection (actor, notes, decision, timestamp) is recorded in the append-only audit trail, and after the Creator edits in place and re-submits, the approval flow restarts with the full rejection history preserved

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 4.2 (Creator → Reviewer → Approver; distinct people; self-approval never allowed; steps recorded in the audit trail with actor identity, timestamp, decision; the `draft → reviewed` checkpoint is audit-only; Q2 role-count is operational configuration, the floor holds at any depth) · § 4.3 (rejection stays in `reviewed`, edited in place, no revert to `draft`, history preserved) · § 13.2 BR-Lifecycle-1 (multi-step approval; distinct people; no self-approval), BR-Lifecycle-6 (rejection handling) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 3 AC-0-FSM-8 (review audited, no event) · decisions/2026-06-15-identity-auth.md (operator authenticated → `newco_ops` + `Operator.id` via ActorContext) · openspec/specs/event-substrate/spec.md (Audit Records — append-only; before/after; authorization basis) · CLAUDE.md invariant 8 (audit envelope; actor_role on every operator action; append-only)._

### Requirement: Producer Activation Gate

A **Product Master** SHALL NOT transition to `active` unless its linked Producer is `active`. This SHALL be a **hard gate**: the `reviewed → active` step SHALL be rejected at the workflow level, with a localized exception, if the linked Producer is not `active` at the moment of transition, leaving the Master in `reviewed` and recording no `ProductMasterActivated` event. The gate SHALL be evaluated against the Catalog-owned producer-state projection (the *Producer-State Projection and Event Consumption* Requirement), never against a Module K table (no cross-module DB access — invariant 10). A Master whose `producer_id` references a Producer with **no** projection row (never activated, or unknown to Catalog) SHALL be treated as **not gated open** and its activation rejected; a Master may still be saved and held in `draft`/`reviewed` while its Producer is not yet `active`.

The PRD precondition that the Producer be **`active` and KYC-verified** (§ 5.4) SHALL be satisfied with the KYC conjunct enforced **transitively upstream**: the KYC four-state lifecycle is owned by `parties-compliance` (DEC-071 — KYC fields nullable, added additively), which tightens **`ActivateProducer`** so a Producer cannot reach `active` without a clear KYC verdict. This change SHALL therefore gate Product Master activation on the linked Producer being `active`, inheriting the KYC tightening when it lands with **no change to this gate** (a documented seam). Re-activation of a `retired` Master (via `retired → reviewed → active`) SHALL re-evaluate this gate at the `reviewed → active` step.

#### Scenario: Activation is blocked when the linked Producer is not active

- **WHEN** `activate` is invoked on a Product Master in `reviewed` whose linked Producer is `draft`, `retired`, or absent from the producer-state projection
- **THEN** the activation is rejected with a localized exception, the Master stays in `reviewed`, and no `ProductMasterActivated` event is recorded

#### Scenario: Activation succeeds when the linked Producer is active

- **WHEN** `activate` is invoked on a Product Master in `reviewed` whose linked Producer is `active` in the projection (and the approval governance passes)
- **THEN** the Master transitions to `active` and a `ProductMasterActivated` event is recorded

#### Scenario: A Master with no bound active Producer is saveable but not activatable

- **WHEN** a Product Master references a Producer that is not `active` in Catalog's projection
- **THEN** the Master can be created and held in `draft`/`reviewed`, but any attempt to activate it is rejected by the gate until the Producer becomes `active`

#### Scenario: Re-activation re-checks the gate

- **GIVEN** a `retired` Product Master whose linked Producer has since become non-`active`
- **WHEN** the Master is reopened and re-activation is attempted
- **THEN** the `reviewed → active` step is rejected by the gate (re-activation is not exempt)

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 5.4 (Producer activation gate — KYC compliance floor; hard gate, rejected at workflow level, checked at the moment of transition) · § 4.4 (a Product Master cannot be activated unless its linked Producer is `active` and KYC-verified) · § 3.2 (the Producer entity — incl. KYC status — is owned by Module K; PIM stores only the `producer_id` link) · § 5.1 step 3 (a Master is saveable as `draft` but cannot activate until a `producer_id` is bound) · § 13.4 BR-Producer-1 (activation gate) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 3 AC-0-FSM-12 (three negative gate paths), § 4 AC-0-BR-Producer-1, § 4.2 AC-0-BR-Lifecycle-3, § 2 AC-0-J-2 (block until bound), AC-0-J-10 (re-activation re-checks) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 6.1 AC-K-XM-2 · openspec/specs/party-registry/spec.md Requirement: Producer Lifecycle (the upstream `ActivateProducer` the KYC gate tightens — DEC-071) · CLAUDE.md invariants 7 (compliance gate) & 10 (no cross-module DB access)._

### Requirement: Producer-State Projection and Event Consumption

The product-catalog SHALL consume the Module K supply-side events **`ProducerActivated`** and **`ProducerRetired`** through a registered platform `DomainEventConsumer`, maintaining a **Catalog-owned producer-state projection** (a read model) that the *Producer Activation Gate* reads. The consumer SHALL be registered in `inline` delivery mode (the launch substrate mode; no `queued` consumer) and SHALL read only the event envelope and its payload (`producer_id`, `status`) — it SHALL NOT import or query any Module K model or table (invariant 10). On `ProducerActivated` the projection SHALL record the Producer as `active` (**enabling** Product Master activation against it); on `ProducerRetired` the projection SHALL record the Producer as `retired` (**blocking new** Product Master activation against it).

The consumer SHALL be **idempotent and order-tolerant**: it SHALL carry a per-producer `last_event_id` watermark (the persisted `domain_events.id` of the last applied event) and SHALL ignore an event whose `id` does not advance the watermark (the substrate's documented latest-wins pattern), so at-least-once and out-of-order delivery converge on the latest producer state. The handler SHALL perform database work only; its projection write and the delivery-status flip SHALL share one transaction.

Consuming `ProducerRetired` SHALL **never** transition any existing `active` Product Master — existing actives are **preserved**; only **new** activations (and new child activations under those Masters) are blocked (block-new, never cascade-retire). Consuming `ProducerActivated` SHALL **never** auto-activate any `draft`/`reviewed` Product Master — it only updates the projection; the Master's `reviewed → active` transition remains operator-initiated (no auto-replay).

#### Scenario: ProducerActivated enables a previously-blocked Master

- **GIVEN** a Product Master in `reviewed` whose linked Producer is not yet `active` (its activation is currently gate-blocked)
- **WHEN** a `ProducerActivated` event for that `producer_id` is delivered to the consumer
- **THEN** the projection records the Producer `active`, and the Master is now activatable by an operator — but no Master state changed as a side effect of consuming the event

#### Scenario: ProducerRetired blocks new activations and preserves existing actives

- **GIVEN** a Producer with one `active` Product Master and one `reviewed` Product Master
- **WHEN** a `ProducerRetired` event for that `producer_id` is delivered
- **THEN** the projection records the Producer `retired`, the `active` Master is left unchanged (`active`), and any attempt to activate the `reviewed` Master is now rejected by the gate

#### Scenario: The consumer is idempotent and order-tolerant

- **WHEN** the same `ProducerActivated` event is delivered twice, or a stale event with a lower `id` than the watermark arrives after a newer one
- **THEN** the projection reflects the latest applied event exactly once, the watermark never regresses, and re-delivery produces no duplicate or reverted state

#### Scenario: The gate reads only Catalog's projection

- **WHEN** the producer-state projection and the consumer are inspected
- **THEN** the consumer reads only the event payload (`producer_id`, `status`) and writes only the Catalog-owned projection table, with no import of or query against any Module K model or table

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 14.5 (PIM consumes `ProducerActivated` to enable Product Master activation and `ProducerRetired` to block new activations; existing actives preserved per BR-Lifecycle-4) · § 13.4 BR-Producer-2 (revocation/retirement is block-new, not cascade) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 5 AC-0-EVT-20 (consume `ProducerActivated` → enable), AC-0-EVT-21 (consume `ProducerRetired` → block new, preserve actives), § 3 AC-0-FSM-13 (existing actives preserved), § 4 AC-0-BR-BulkImport-4 (no auto-replay on upstream `*Activated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 6.1 AC-K-XM-2, § 4 AC-K-BR-Producer-4 · openspec/specs/event-substrate/spec.md (Per-Consumer Delivery Ledger; Inline Delivery and Scheduled Sweep; Ordering and Consumer Obligations — the per-entity watermark pattern) · app/Modules/Parties/Events/{ProducerActivated,ProducerRetired}.php (payload `{producer_id, status}`) · decisions/2026-06-12-event-substrate-and-audit-store.md (inline consumer; DB-work-only; idempotent) · CLAUDE.md invariant 10._

### Requirement: Activation Cascade

A child spine entity SHALL NOT transition to `active` while its parent (or any parent it composes) is not `active`. This SHALL be a **hard gate**, rejected at the workflow level with a localized exception, evaluated at the moment of the child's `reviewed → active` transition against a within-module read of the parent's `lifecycle_state` (the only cross-module parent — a Product Master's Producer — is gated by the *Producer Activation Gate*). Specifically:

- a **Product Variant** SHALL require its parent **Product Master** to be `active`;
- a **Product Reference** SHALL require its **Product Variant** to be `active` **and** its referenced **Format** to be `active`;
- a **Sellable SKU (Intrinsic)** SHALL require its referenced **Product Reference** to be `active` **and** its referenced **Case Configuration** to be `active`;
- a **Composite SKU** SHALL require **every** constituent **Product Reference** to be `active`.

The standalone reference entities **Format** and **Case Configuration** have no parent in the hierarchy and SHALL activate independently, subject only to the *Approval Governance*. Because a child can never reach `active` before its parent, **activation events SHALL be emitted in parent-before-child order** in any workflow that activates a chain.

#### Scenario: A child cannot activate under a non-active parent

- **WHEN** `activate` is invoked on a Product Variant whose Product Master is not `active` (or a Product Reference whose Variant or Format is not `active`, a Sellable SKU whose PR or Case Configuration is not `active`, or a Composite SKU any of whose constituent PRs is not `active`)
- **THEN** the activation is rejected with a localized exception, the child stays in `reviewed`, and no `*Activated` event is recorded for it

#### Scenario: A child activates once all parents are active

- **WHEN** every parent/constituent of a child is `active` and the child's approval governance passes
- **THEN** the child transitions to `active` and records its `*Activated` event

#### Scenario: Standalone reference entities activate independently

- **WHEN** a Format or a Case Configuration is activated
- **THEN** it transitions to `active` subject only to the approval governance, with no parent gate

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 4.4 (the activation cascade — child cannot activate while parent is not `active`; the per-entity parent rules; standalone reference entities activate independently; the cascade is a hard gate rejected at the workflow level) · § 14.3 (activation events emitted parent-before-child, naturally enforced by the cascade) · § 13.2 BR-Lifecycle-3 (activation cascade; Sellable SKU requires PR and Case Configuration active; Product Master requires its Producer active) · § 3.8 (a Composite SKU's N constituents) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 3 AC-0-FSM-10 (child cannot activate under a non-active parent), § 4.2 AC-0-BR-Lifecycle-3 · app/Modules/Catalog/Models/{ProductVariant,ProductReference,SellableSku,CompositeSku}.php (the within-module parent FKs `product_master_id`, `product_variant_id`/`format_id`, `product_reference_id`/`case_configuration_id`, and the `catalog_composite_sku_constituents` junction)._

### Requirement: Retirement Cascade and Reference Integrity

When a parent spine entity is retired, its existing `active` child entities and their downstream references SHALL **remain valid** for their current lifecycle (no retroactive invalidation); retirement SHALL only **prevent new** commercial commitment — no new child SHALL be activated under a retired parent, and no new activation SHALL be opened against the retired entity. An operator MAY retire a Product Master together with its descendants in a single workflow; this **operator-driven cascade** SHALL retire the entities in **parent-before-child** order (Product Master → its Product Variants → their Product References → the Sellable / Composite SKUs under those PRs), recording each entity's `*Retired` event in that order.

A single-entity retirement SHALL be **blocked only while the entity is referenced by an `active` terminal sellable object that has not completed** — concretely, a Product Reference referenced by an `active` Sellable or Composite SKU, or a Case Configuration referenced by an `active` Sellable SKU — surfacing the open references; retirement proceeds once they close (or via the operator-driven cascade that retires them in order). A **hierarchy parent** (a Product Master with `active` Variants, or a Product Variant with `active` Product References) SHALL **not** be blocked on its children: per the cascade rule above its retirement **succeeds and preserves** them (they stay `active`; only new activation under the now-`retired` parent is prevented). The **cross-module** downstream-reference leg of this guard (active Allocations, issued vouchers, in-flight orders, SKUs on live Offers) SHALL be a **documented seam**: those referencers do not exist until Phase 3, so this change enforces the guard over within-catalog references only, and the Phase-3 referencer changes SHALL extend it.

#### Scenario: Retiring a parent preserves existing active children

- **GIVEN** a Product Master in `active` with an `active` Product Variant under it
- **WHEN** the Master is retired
- **THEN** the existing `active` Variant remains `active` for its current lifecycle, but no new child may be activated under the now-`retired` Master

#### Scenario: Operator-driven cascade retires parent-before-child

- **WHEN** an operator retires a Product Master and its descendants in one workflow
- **THEN** the `*Retired` events are recorded in parent-before-child order — `ProductMasterRetired` first, then each `ProductVariantRetired`, then each `ProductReferenceRetired`, then the SKUs under those PRs

#### Scenario: Single-entity retirement is blocked by active within-catalog references

- **WHEN** a single-entity retirement is attempted on an entity that still has active within-catalog references (e.g. a Product Reference referenced by an `active` Sellable SKU)
- **THEN** the retirement is rejected, the open references are surfaced, and the entity stays `active` until they close or are retired in order

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 4.5 (retirement cascade — existing active children stay valid for current references; no new child activated under a retired parent; in-flight commercial state runs to natural completion) · § 4.7 (operator-driven cascade retirement in parent-before-child order) · § 14.3 (retirement events follow parent-before-child ordering in a cascade) · § 13.2 BR-Lifecycle-4 (retirement cascade), BR-Lifecycle-5 (retirement blocked by active downstream references; the system surfaces them) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 3 AC-0-FSM-11 (retirement cascade — actives valid, no new children), § 4.2 AC-0-BR-Lifecycle-4 · CLAUDE.md invariant 3 (committed inventory — the broader committed-state discipline this guards locally) & invariant 10._

### Requirement: Product Lifecycle Events

Each spine-entity activation and retirement SHALL record its **verbatim** Module 0 event — the category-neutral `*Activated` / `*Retired` families `ProductMasterActivated` / `ProductMasterRetired`, `ProductVariantActivated` / `ProductVariantRetired`, `ProductReferenceActivated` / `ProductReferenceRetired`, `FormatActivated` / `FormatRetired`, `CaseConfigurationActivated` / `CaseConfigurationRetired`, `SellableSKUActivated` / `SellableSKURetired`, `CompositeSKUActivated` / `CompositeSKURetired` — through the platform `DomainEventRecorder`, **within the same database transaction** as the state write, tagged with module `catalog`, the acting `actor_role` and `actor_id` resolved from the `ActorContext` seam, the entity type and id, and a **PII-free** payload (entity ids + lifecycle/enum values only; other parties — e.g. a Master's producer — referenced **by id** only). The `*Activated` event SHALL cover `reviewed → active` and the `*Retired` event SHALL cover `active → retired`; the `draft → reviewed` checkpoint and the `retired → reviewed` reopen SHALL record **no** domain event. No event name outside this fourteen-name set (plus the spine's existing `*Created` and the unchanged `EnrichmentDataUpdated`) SHALL be recorded by this change, and **no `*Reviewed` event** SHALL exist. In a cascade, the events SHALL be recorded **parent-before-child**.

#### Scenario: Activation and retirement record their verbatim events

- **WHEN** a spine entity is activated, then retired
- **THEN** exactly its `*Activated` event (on `reviewed → active`) and then its `*Retired` event (on `active → retired`) are recorded in the writing transaction, tagged module `catalog` with the entity type/id and an `actor_role`/`actor_id` from `ActorContext`, and each payload contains only entity ids and lifecycle/enum values (no name, email or other personal data; the producer referenced by id)

#### Scenario: The review and reopen checkpoints record no event

- **WHEN** an entity transitions `draft → reviewed` or `retired → reviewed`
- **THEN** no domain event is recorded for the step (only an audit record), and no `*Reviewed` event exists anywhere in the catalog event surface

#### Scenario: Cascade events are parent-before-child

- **WHEN** a chain is activated (or an operator-driven retirement cascade runs)
- **THEN** the recorded `*Activated` (resp. `*Retired`) events appear in parent-before-child order — Product Master, then Product Variant, then Product Reference, then the SKUs

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 14.1 (the seven-entity `*Activated`/`*Retired` event set, category-neutral per § 18 — never `WineMaster*`/`BottleReference*`) · § 14.2 (`*Activated` = `reviewed → active`; `*Retired` = `active → retired`; `draft → reviewed` emits no distinct event) · § 14.3 (parent-before-child emission ordering in cascades) · § 18 (the canonical category-neutral names; "Wine Master"/"Bottle Reference" only as wine-display aliases) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 3 AC-0-FSM-1, AC-0-FSM-8, § 5 AC-0-EVT-1 · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional outbox; PII-free payloads) · openspec/specs/event-substrate/spec.md (Domain Event Envelope) · app/Modules/Catalog/Events/ProductMasterCreated.php (the `final` `NAME`/`ENTITY_TYPE`/static `payload()` pattern the `*Activated`/`*Retired` classes mirror) · CLAUDE.md invariants 4 & 10._
