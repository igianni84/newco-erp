# product-catalog Specification

## Purpose
TBD - created by archiving change catalog-product-spine. Update Purpose after archive.
## Requirements
### Requirement: Category-Neutral Product Type

The product-catalog SHALL carry `Product Type` as a first-class classifier on the Product Master, and at launch the **only** supported value SHALL be `WINE`; constructing a Product Master of any other type SHALL be rejected (fail-closed), never silently accepted. The Product Type SHALL be the switch that selects, per product, the applicable per-type attribute set, the variant-defining dimension, the type-defined identity-uniqueness key, and (when it lands) the enrichment adapter. The core spine entities (Master, Variant, Reference) SHALL carry only category-neutral identity and structural fields; **all** type-specific descriptive and identity attributes (for `WINE`: appellation/region, vintage, descriptive prose) SHALL live in a per-type attribute set held off the neutral core, so a future Product Type slots in additively without reshaping the core entities or the cross-module event contract. The product-catalog SHALL NOT implement a dynamic EAV or rules engine, and SHALL NOT define any non-wine Product Type, attribute set, or Format vocabulary at launch.

#### Scenario: WINE is the only launch Product Type

- **WHEN** a Product Master is created with Product Type `WINE`
- **THEN** it is accepted
- **WHEN** a Product Master is created with any Product Type other than `WINE`
- **THEN** construction is rejected (fail-closed), and no non-wine type, attribute set, or Format vocabulary is defined anywhere in the catalog

#### Scenario: The neutral core holds no wine-specific attribute

- **WHEN** the Product Master and Product Variant core entities are inspected
- **THEN** wine-specific attributes (appellation, region, vintage) are NOT columns on the neutral core — they live in the `WINE` per-type attribute set; the core carries only category-neutral identity/structural fields (product name, product type, producer reference, lifecycle state, audit/version on the Master; the type-neutral variant identifier on the Variant)

#### Scenario: The variant axis is type-neutral

- **WHEN** the Product Variant core entity is inspected
- **THEN** it does not hard-name a wine-only "vintage" dimension; `WINE`'s vintage (year or non-vintage marker) is held in the `WINE` attribute set, and wine variant behaviour is unchanged

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 3.1 (Product Type — first-class classifier; sole launch value `WINE`) · § 3.9 (neutral core + additive per-type attribute sets; intent guard — category-readiness not maximal configurability, no EAV) · § 16 (the Wine→Product generalisation + the five guardrails) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 6.4 AC-0-GEN-1/2/3, § 6.3 AC-0-XM-9 · MVP-DEC-004 / DEC-065 (generalisation folded into the v0.3-MVP PRD) · decisions/2026-06-14-catalog-category-neutral-representation.md._

### Requirement: Lifecycle State Recorded, Transitions Deferred

Every product-catalog spine entity SHALL carry a `lifecycle_state` whose domain is exactly `draft`, `reviewed`, `active`, `retired`, and SHALL be created in the `draft` state. The spine entities SHALL now implement their state transitions, the Creator → Reviewer → Approver approval workflow, rejection handling, the activation and retirement cascades, and the Producer-activation gate, as governed by the Requirements *Product Lifecycle State Machine*, *Approval Governance*, *Producer Activation Gate*, *Producer-State Projection and Event Consumption*, *Activation Cascade*, *Retirement Cascade and Reference Integrity*, and *Product Lifecycle Events*. Consequently the spine's `*Activated` and `*Retired` domain events SHALL now be recorded on the corresponding `reviewed → active` and `active → retired` transitions; the `draft → reviewed` checkpoint SHALL remain event-silent (an internal-to-PIM, audit-only checkpoint). The cross-module downstream-reference leg of the retirement guard (Allocations, vouchers, stock, Offers) and the KYC conjunct of the Producer gate SHALL remain documented seams (the first realised by the Phase-3 referencer changes; the second tightened upstream by `parties-compliance`).

#### Scenario: A newly created entity is draft

- **WHEN** any spine entity (Product Master, Product Variant, Product Reference, Format, Case Configuration, Sellable SKU, Composite SKU) is created
- **THEN** its `lifecycle_state` is `draft`

#### Scenario: Transition paths now exist

- **WHEN** the catalog code surface is inspected
- **THEN** every spine entity exposes operations that transition it through `draft → reviewed → active → retired` (and `retired → reviewed` for re-activation), the `reviewed → active` and `active → retired` transitions record the entity's `*Activated` / `*Retired` event, and the `draft → reviewed` transition records no domain event

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 4.1 (the four-state lifecycle `draft → reviewed → active → retired`) · § 4.2 (the `draft → reviewed` checkpoint emits no distinct domain event) · § 14.2 (`*Created` = `<null> → draft`; `*Activated` = `reviewed → active`; `*Retired` = `active → retired`) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 3 AC-0-FSM-1, AC-0-FSM-8 · openspec/specs/product-catalog/spec.md (the prior "Transitions Deferred" requirement this change discharges) · openspec/changes/archive/2026-06-15-catalog-product-spine/proposal.md (the deferred-table slice boundary naming this change)._

### Requirement: Product Master

The Product Master SHALL be the top of the product hierarchy and the parent of every Product Variant. Its category-neutral core SHALL carry the product name, the Product Type, a **producer reference by id** (a plain identifier into Module K — never a cross-module Eloquent relationship, join, or model import), the `lifecycle_state`, and audit/version fields; its `WINE` attribute set SHALL carry appellation/region and translatable descriptive prose (the winery story) held as i18n-keyed text with per-attribute English fallback. For Product Type `WINE`, a Product Master SHALL be unique on the **type-defined identity key** `producer + product name + appellation`, and a creation whose identity key collides with an existing non-retired Product Master SHALL be rejected (deduplication enforced at creation, on the manual baseline path). `appellation` SHALL be a real, indexable attribute so the uniqueness constraint is enforceable portably on both PostgreSQL and SQLite.

A Product Master SHALL NOT be created with a `producer_id` referencing a Producer **unknown to Catalog**: creation SHALL be rejected with a localized exception when the `producer_id` has no row in the Catalog-owned producer-state projection (which records every Producer from its `ProducerCreated` event — the *Producer-State Projection and Event Consumption* Requirement). This is an **existence** check, not an activeness check: a `registered` or `retired` projection row admits creation (the Master is saveable and holdable in `draft`/`reviewed`), and activation stays separately gated by the *Producer Activation Gate*.

#### Scenario: Create a WINE Product Master

- **WHEN** an operator creates a Product Master of type `WINE` with a product name, a producer id known to the producer-state projection, and an appellation
- **THEN** it is persisted in `draft` with the neutral core fields set, the appellation/region and winery-story prose held in the `WINE` attribute set, and the producer captured as a bare id (no Eloquent relation crosses the module boundary)

#### Scenario: Duplicate identity key is rejected

- **WHEN** a Product Master is created whose `producer + product name + appellation` matches an existing non-retired `WINE` Product Master
- **THEN** the creation is rejected with a clear reason; two distinct identity tuples both succeed

#### Scenario: A producer unknown to the projection is rejected at creation

- **WHEN** a Product Master creation references a `producer_id` with no producer-state projection row
- **THEN** the creation is rejected with a localized exception, no Master row is persisted and no `ProductMasterCreated` event is recorded
- **WHEN** the `producer_id` has a `registered` (created, never activated) projection row
- **THEN** the creation succeeds and the Master is held in `draft` (its activation remains gate-blocked until the Producer is `active`)

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 3.2 (Product Master — highest-level identity; producer link is a hard reference, not the entity) · § 13.1 BR-Identity-1 (uniqueness per type; `WINE` = producer + name + appellation) · § 8 (translatable descriptive content, six locales) · § 5.1 step 3 (no-match → the Producer must be registered in Module K first; the Master is saveable as `draft`) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 2 AC-0-J-3 (manual baseline creation + dedup — launch-critical), § 4.1 AC-0-BR-Identity-1 (type-defined key; manual-path half launch-critical), § 6.1 AC-0-XM-2 (no creation against a non-existent Producer) · CLAUDE.md invariant 10 (no cross-module DB access — events + contracts only) · openspec/specs/i18n/spec.md (Translatable text) · interview decisions 2026-07-08 (existence via the widened projection; not an activeness check)._

### Requirement: Product Variant

A Product Variant SHALL belong to **exactly one** Product Master and SHALL express its variant axis through a type-neutral identifier on the core, with the axis value and meaning held in the Product Type's attribute set; for `WINE` the variant axis SHALL be **vintage** (a vintage year or a non-vintage marker) held in the `WINE` attribute set alongside translatable vintage-level prose. The single-parent relationship SHALL be structurally enforced — a Variant SHALL NOT belong to more than one Product Master.

#### Scenario: A Variant belongs to exactly one Master

- **WHEN** a Product Variant is created under a Product Master
- **THEN** it references exactly one Product Master, and an attempt to give it more than one parent is rejected

#### Scenario: Wine vintage lives in the attribute set

- **WHEN** a `WINE` Product Variant records its vintage
- **THEN** the vintage (year or non-vintage marker) is stored in the `WINE` attribute set, not as a column on the neutral Variant core

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 3.3 (Product Variant — a release of a Master; variant axis type-neutral on the core, `WINE` axis = vintage) · § 3.9 (per-type attribute placement) · § 13.1 BR-Identity-2 (each child has exactly one parent) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 4.1 AC-0-BR-Identity-2, § 6.4 AC-0-GEN-3._

### Requirement: Product Reference — the atomic product key

The Product Reference (PR) SHALL be the atomic product identity and the universal product key across modules, composed of **exactly two dimensions**: a Product Variant and a Format. A Case Configuration SHALL **never** be part of PR identity — the same Variant + Format SHALL resolve to the **same** PR whether it is later sold loose, in an OWC, or in a carton. The `(variant, format)` pair SHALL be unique, and SHALL constitute the PR's identity such that changing the composition is not an in-place edit but a new PR. _(Full enforcement of immutability-once-referenced-downstream — BR-Identity-4 — is deferred to `catalog-lifecycle-approval`, which lands with the first Allocation/voucher/stock/Offer referencers.)_

#### Scenario: A PR is Variant + Format only

- **WHEN** a Product Reference is created
- **THEN** it references exactly one Product Variant and one Format, and the PR entity carries no `case_configuration` dimension

#### Scenario: Packaging does not change the PR

- **WHEN** three Sellable SKUs are later defined for the same Variant + Format with different Case Configurations (loose, six-bottle OWC, twelve-bottle carton)
- **THEN** all three reference the one same Product Reference; the `(variant, format)` pair is unique and is the PR's identity

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 3.4 (PR — atomic identity; the two-dimension invariant; Case Configuration never part of identity; PR is the traceability spine and universal key) · § 13.1 BR-Identity-3 (exactly Variant + Format) / BR-Identity-4 (immutable on reference — enforcement deferred) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 4.1 AC-0-BR-Identity-3._

### Requirement: Format

Format SHALL be a standalone PIM reference entity (no parent in the hierarchy) representing the physical size/measure of the atomic unit — for `WINE`, the bottle size — and a Product Reference SHALL reference exactly one Format. Adding a Format SHALL be an ordinary catalog operation. _(Format's role as a Product-Reference activation gate is part of the deferred lifecycle change; this change only models the entity and the reference.)_

#### Scenario: Create a Format and reference it

- **WHEN** a Format (e.g. a bottle size) is created
- **THEN** it is persisted in `draft` as a standalone reference entity, and a Product Reference can reference exactly one Format

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 3.5 (Format — standalone reference entity; `WINE` = bottle sizes; name kept in the generalisation) · § 13.6 BR-RefData-1 · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 4.6 AC-0-BR-RefData-1._

### Requirement: Case Configuration

Case Configuration SHALL be a standalone PIM reference entity (distinct from Format) carrying packaging-form attributes only — units per case, packaging type, physical form — and SHALL be referenced by a Sellable SKU (Intrinsic). It SHALL **carry no breakability flag**: whether a case may be split at sale is decided downstream by the layered breakability rule (Module A Layer 2 / Module S Layer 3), never as a property of the Case Configuration.

#### Scenario: Create a Case Configuration

- **WHEN** a Case Configuration is created with a units-per-case and packaging type
- **THEN** it is persisted in `draft` as a standalone reference entity

#### Scenario: Case Configuration has no breakability flag

- **WHEN** the Case Configuration entity is inspected
- **THEN** it has no breakability attribute or column; breakability is not a property of the Case Configuration

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 3.6 (Case Configuration — packaging form; distinct from Format; carries no breakability flag) · § 7 (the layered breakability rule lives in Modules A/S) · § 13.6 BR-RefData-2 · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 4.6 AC-0-BR-RefData-2._

### Requirement: Sellable SKU (Intrinsic)

A Sellable SKU (Intrinsic) SHALL be the commercial unit composed of **one Product Reference + one Case Configuration + commercial attributes**, and SHALL be the **only** SKU shape that references a Case Configuration. It SHALL reference exactly one Product Reference and exactly one Case Configuration.

#### Scenario: An Intrinsic SKU composes PR + Case Configuration

- **WHEN** a Sellable SKU (Intrinsic) is created
- **THEN** it references exactly one Product Reference and exactly one Case Configuration, carries its commercial attributes, and is persisted in `draft`

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 3.7 (Sellable SKU Intrinsic — PR + Case Configuration + commercial attributes; the only SKU shape referencing Case Configuration) · § 13.5 BR-SKU-1 · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 4.5 AC-0-BR-SKU-1._

### Requirement: Composite SKU

A Composite SKU SHALL be a curated bundle of **N ≥ 2 constituent Product References** (ordered), and a single Product Reference MAY be a constituent of multiple Composite SKUs (a many-to-many relationship). The product-catalog SHALL be **producer-agnostic** about a Composite SKU's constituents — it SHALL NOT validate producer composition; admissibility on any commercial surface (e.g. Club Offers rejecting mixed-producer sets, the deferred multi-producer Discovery composite) is a Module S Offer-publication concern, not a PIM rule. The N-constituent, producer-agnostic structure is retained as the Mod0-Q1 / D7 seam. _(Atomicity-at-sale (BR-SKU-3) and immutability-after-active-Offer (BR-SKU-4) are runtime/commercial rules deferred to `catalog-lifecycle-approval` / Module S; this change models only the bundle structure.)_

#### Scenario: A Composite requires at least two constituents

- **WHEN** a Composite SKU is created with two or more constituent Product References
- **THEN** it is persisted in `draft` with its ordered constituents
- **WHEN** a Composite SKU is created with fewer than two constituents
- **THEN** the creation is rejected

#### Scenario: PIM is producer-agnostic about constituents

- **WHEN** a Composite SKU is created with constituent Product References drawn from more than one producer
- **THEN** the product-catalog accepts it without validating producer composition (the single-producer-at-launch rule is enforced by Module S at Offer publication, not by PIM)

#### Scenario: A Product Reference can be in multiple Composites

- **WHEN** the same Product Reference is added as a constituent of two different Composite SKUs
- **THEN** both Composite SKUs are valid (the constituent relationship is many-to-many)

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 3.8 (Composite SKU — N constituent PRs; producer-agnostic at PIM; Mod0-Q1 KEPT + D7 seam; atomicity + post-commitment immutability are commercial rules) · § 13.5 BR-SKU-2/3/4/5 · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 2 AC-0-J-5, § 4.5 AC-0-BR-SKU-5, AC-0-GEN-11 · D7 / Mod0-Q1 (Composite SKU KEPT; multi-producer Discovery deferred) · DEC-019 / DEC-061 (Module S surface admissibility)._

### Requirement: Naming Cascade (category-neutral canonical names)

The product-catalog SHALL adopt the §18 naming cascade as the canonical naming of its structural entities and domain events: the category-neutral `Product*` names — Product Master, Product Variant, Product Reference, and the event families `ProductMaster*`, `ProductVariant*`, `ProductReference*` — SHALL be the code identifiers, and the former wine-specific structural names (`Wine Master`, `Wine Variant`, `Bottle Reference`/`BR`, `WineMaster*`, `WineVariant*`, `BottleReference*`) SHALL NOT appear as structural/contract identifiers. "Wine Master", "Wine Variant", and "Bottle Reference (BR)" SHALL be retained only as **wine-display aliases** (presentation/documentation terms), never as structural names. Format, Case Configuration, Sellable SKU, Composite SKU, and `EnrichmentDataUpdated` SHALL keep their names unchanged.

#### Scenario: Canonical structural names are used

- **WHEN** the catalog models and event names are inspected
- **THEN** they use the category-neutral `Product*` names; no `Wine*`/`BottleReference*` name appears as a structural or event identifier, and "Bottle Reference" survives only as a documented wine-display alias for Product Reference

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 18 (the canonical renames + the wine-display-alias retention + the unchanged set) · spec/04-decisions/MVP_Decisions_Register_v0.1.md § 5 item A (naming cascade; Module 0 §18 = source of truth) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 6.4 AC-0-GEN-6._

### Requirement: Spine Creation Events

On the creation of each product-catalog spine entity, the catalog SHALL record the entity's `*Created` domain event through the platform `DomainEventRecorder`, within the same database transaction as the write, tagged with module `catalog`, the acting `actor_role` resolved from the `ActorContext` seam, the entity type and id, and a **PII-free** payload that references parties only by id. The recorded event names SHALL be the category-neutral families `ProductMasterCreated`, `ProductVariantCreated`, `ProductReferenceCreated`, `SellableSKUCreated`, `CompositeSKUCreated`, `FormatCreated`, `CaseConfigurationCreated`. No `*Activated` or `*Retired` event SHALL be recorded by this change.

#### Scenario: Creating a Product Master records ProductMasterCreated

- **WHEN** a Product Master is created
- **THEN** a `ProductMasterCreated` domain event is recorded in the same transaction, tagged module `catalog`, with the master's entity type and id and a PII-free payload (producer referenced by id, no personal data)

#### Scenario: Every spine entity records its Created event

- **WHEN** a Product Variant, Product Reference, Format, Case Configuration, Sellable SKU, or Composite SKU is created
- **THEN** the corresponding `*Created` event (`ProductVariantCreated`, `ProductReferenceCreated`, `FormatCreated`, `CaseConfigurationCreated`, `SellableSKUCreated`, `CompositeSKUCreated`) is recorded; no `*Activated`/`*Retired` event is recorded in this change

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 14.1 (the event set, renamed per §18) · § 14.2 (`*Created` = `<null> → draft`; semantics) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 2 AC-0-J-4 (the creation half of the chain + event emission) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional outbox; PII-free payloads; money/FX payload discipline) · openspec/specs/event-substrate/spec.md (Domain Event Envelope) · CONTEXT.md (Actor context — system default)._

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

Each governance step SHALL be recorded in the **append-only audit trail** with the acting `actor_role` and `actor_id` (resolved from the `ActorContext` seam), the action, a before/after snapshot, and the decision; the audit trail SHALL be the system of record for which actor performed each step, and **both** the distinct-actor guard **and** the review-freshness condition SHALL be evaluated against it. No per-entity governance status column SHALL be added — the review-freshness condition is a **derived** read of the entity's audit history, never persisted state. Because the catalog audit trail now carries non-governance rows too (identity edits, enrichment updates, whitelist maintenance), the derivation SHALL consider **only** the review-freshness-relevant action verbs — `submitted`, `resubmitted`, `rejected` and `identity_updated`; any other catalog audit row SHALL neither set nor clear the condition (a post-rejection enrichment or whitelist row never unblocks activation without a re-submit).

Rejection, re-submission, identity edits, and the review-freshness block-gate (`§ 4.3`): a Reviewer or Approver MAY **reject** an entity in `reviewed`; the entity SHALL **stay in `reviewed`** with the rejection recorded in the audit trail (actor, notes, decision), and there SHALL be **no** revert-to-`draft` step. A rejection leaves the entity **review-stale** (rejection-pending) — a condition DERIVED from the audit trail as "the entity's latest review-freshness-relevant action is a rejection **or an identity edit**." An **identity edit** on an entity in `reviewed` (the *Identity Edit and Re-Versioning* Requirement) SHALL likewise leave it review-stale — edited review-governed content has not been re-reviewed. While an entity is review-stale its activation (`reviewed → active`) SHALL be **blocked** with a localized exception, leaving it in `reviewed` and recording no `*Activated` event: the approval flow SHALL restart from review, never complete from a still-rejected or edited-but-not-re-reviewed state. The Creator SHALL edit the entity **in place** and then perform an explicit **`re-submit`** operation — a `reviewed → reviewed`, **audit-only** governance decision (recorded as `resubmitted`; **no** domain event), the twin of `reject`, requiring an authenticated operator principal and from-state guarded on `reviewed` — which **re-arms review** by clearing the review-stale condition (the latest review-freshness-relevant action becomes the re-submission — no longer a rejection or an un-re-reviewed edit); a distinct approver MAY then activate. The full rejection history (every round's notes, actor identities and timestamps) SHALL be preserved as part of the entity's permanent append-only audit record.

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

#### Scenario: A pending rejection blocks activation until re-submit

- **WHEN** an entity in `reviewed` has been rejected (its latest review-freshness-relevant action is a rejection) and a distinct approver attempts to activate it (`reviewed → active`)
- **THEN** the activation is blocked with a localized exception, the entity stays in `reviewed`, and no `*Activated` event is recorded

#### Scenario: An identity edit in reviewed re-arms review and blocks activation until re-submit

- **WHEN** an entity in `reviewed` (submitted, never rejected) has its review-governed identity content edited, and a distinct approver then attempts to activate it
- **THEN** the activation is blocked with a localized exception (the latest review-freshness-relevant action is the identity edit), the entity stays in `reviewed`, and after an explicit `re-submit` the same distinct approver can activate it

#### Scenario: Re-submit re-arms review and clears the review-stale condition

- **WHEN** the Creator performs the explicit `re-submit` operation on a review-stale entity in `reviewed`
- **THEN** the entity stays in `reviewed`, an audit record (`resubmitted`, with acting `actor_id` and a before/after snapshot) is written and **no** domain event is recorded, the review-stale condition is cleared (the latest review-freshness-relevant action is now the re-submission), and a distinct approver may then activate the entity to `active`

#### Scenario: Two rejection rounds each block until re-submit and preserve full history

- **WHEN** an entity is rejected, re-submitted, rejected again, re-submitted again, then activated by a distinct approver
- **THEN** activation is blocked after each rejection until the following re-submit, the entity stays in `reviewed` throughout the rounds, both rejection rows (with their notes and actors) and both re-submission rows are preserved in the append-only audit trail, and the final activation succeeds and records exactly one `*Activated` event

#### Scenario: Non-governance audit rows never affect review-freshness

- **WHEN** a rejection-pending Product Variant receives an enrichment update or a whitelist maintenance write (each appending a catalog audit row) and a distinct approver then attempts activation
- **THEN** the activation is still blocked (the enrichment/whitelist rows did not clear the rejection)
- **WHEN** a `reviewed`, never-rejected, never-identity-edited Variant receives an enrichment update and a distinct approver then attempts activation
- **THEN** the activation succeeds (the enrichment row did not set the review-stale condition)

#### Scenario: Re-submit is operator-floored and from-state guarded

- **WHEN** a `re-submit` is attempted in a `system`/unauthenticated context, or on an entity not in `reviewed` (e.g. a `draft` or `active` entity)
- **THEN** it is rejected — respectively on the operator-principal floor, or with a localized `IllegalLifecycleTransition` naming the state — and no audit row is written and no state changes

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 4.2 (Creator → Reviewer → Approver; distinct people; self-approval never allowed; steps recorded in the audit trail with actor identity, timestamp, decision; the `draft → reviewed` checkpoint is audit-only; Q2 role-count is operational configuration, the floor holds at any depth) · § 4.3 (rejection stays in `reviewed`, edited in place, no revert to `draft`, re-submission restarts the flow, history preserved) · § 13.2 BR-Lifecycle-1 (multi-step approval; distinct people; no self-approval), BR-Lifecycle-6 (rejection handling — rejection flag + notes, edit in place, re-submit, flow restarts from review, history preserved) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 2 AC-0-J-7 (2-rejection-round scenario; state stays `reviewed`, audit trail contains every round), § 3 AC-0-FSM-8 (review audited, no event), § 4.2 AC-0-BR-Lifecycle-6 · decisions/2026-07-02-adopt-dec-019-review-freshness-resubmit.md (canon MVP-DEC-019 local adoption: explicit re-submit operation + the activation block-gate, derived from the audit trail; **the edit-re-arms leg deferred to RM-14 is discharged by this change**, together with the RM-06 semantic-verify **S1** forward fix — the verb-filtered derivation) · decisions/2026-06-15-identity-auth.md (operator authenticated → `newco_ops` + `Operator.id` via ActorContext) · openspec/specs/event-substrate/spec.md (Audit Records — append-only; before/after; authorization basis) · CLAUDE.md invariant 8 (audit envelope; actor_role on every operator action; append-only) · interview decisions 2026-07-08._

### Requirement: Producer Activation Gate

A **Product Master** SHALL NOT transition to `active` unless its linked Producer is `active`. This SHALL be a **hard gate**: the `reviewed → active` step SHALL be rejected at the workflow level, with a localized exception, if the linked Producer is not `active` at the moment of transition, leaving the Master in `reviewed` and recording no `ProductMasterActivated` event. The gate SHALL be evaluated against the Catalog-owned producer-state projection (the *Producer-State Projection and Event Consumption* Requirement), never against a Module K table (no cross-module DB access — invariant 10). A Master whose `producer_id` references a Producer with **no** projection row (unknown to Catalog), or whose projection row is not `active` (`registered` — created but never activated — or `retired`), SHALL be treated as **not gated open** and its activation rejected; a Master may still be saved and held in `draft`/`reviewed` while its Producer is not yet `active`.

The PRD precondition that the Producer be **`active` and KYC-verified** (§ 5.4) SHALL be satisfied with the KYC conjunct enforced **transitively upstream**: the KYC four-state lifecycle is owned by `parties-compliance` (DEC-071 — KYC fields nullable, added additively), which tightens **`ActivateProducer`** so a Producer cannot reach `active` without a clear KYC verdict. This change SHALL therefore gate Product Master activation on the linked Producer being `active`, inheriting the KYC tightening when it lands with **no change to this gate** (a documented seam). Re-activation of a `retired` Master (via `retired → reviewed → active`) SHALL re-evaluate this gate at the `reviewed → active` step.

#### Scenario: Activation is blocked when the linked Producer is not active

- **WHEN** `activate` is invoked on a Product Master in `reviewed` whose linked Producer is `registered` (created, never activated), `retired`, or absent from the producer-state projection
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

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 5.4 (Producer activation gate — KYC compliance floor; hard gate, rejected at workflow level, checked at the moment of transition) · § 4.4 (a Product Master cannot be activated unless its linked Producer is `active` and KYC-verified) · § 3.2 (the Producer entity — incl. KYC status — is owned by Module K; PIM stores only the `producer_id` link) · § 5.1 step 3 (a Master is saveable as `draft` but cannot activate until a `producer_id` is bound) · § 13.4 BR-Producer-1 (activation gate) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 3 AC-0-FSM-12 (three negative gate paths), § 4 AC-0-BR-Producer-1, § 4.2 AC-0-BR-Lifecycle-3, § 2 AC-0-J-2 (block until bound), AC-0-J-10 (re-activation re-checks) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 6.1 AC-K-XM-2 · openspec/specs/party-registry/spec.md Requirement: Producer Lifecycle (the upstream `ActivateProducer` the KYC gate tightens — DEC-071) · CLAUDE.md invariants 7 (compliance gate) & 10 (no cross-module DB access) · interview decisions 2026-07-08 (`registered` status introduced by the widened projection is not gated open)._

### Requirement: Producer-State Projection and Event Consumption

The product-catalog SHALL consume the Module K supply-side events **`ProducerCreated`**, **`ProducerActivated`** and **`ProducerRetired`** through a registered platform `DomainEventConsumer`, maintaining a **Catalog-owned producer-state projection** (a read model) that the *Producer Activation Gate* reads and that Product Master creation consults for producer existence (the *Product Master* Requirement). The consumer SHALL be registered in `inline` delivery mode (the launch substrate mode; no `queued` consumer) and SHALL read only the event envelope and its payload (`producer_id`) — it SHALL NOT import or query any Module K model or table (invariant 10). On `ProducerCreated` the projection SHALL record the Producer as **`registered`** (making it **known to Catalog** — admitting Product Master creation against it — without gating activation open); on `ProducerActivated` the projection SHALL record the Producer as `active` (**enabling** Product Master activation against it); on `ProducerRetired` the projection SHALL record the Producer as `retired` (**blocking new** Product Master activation against it). The projection status domain SHALL be exactly `registered`, `active`, `retired`.

The consumer SHALL be **idempotent and order-tolerant**: it SHALL carry a per-producer `last_event_id` watermark (the persisted `domain_events.id` of the last applied event) and SHALL ignore an event whose `id` does not advance the watermark (the substrate's documented latest-wins pattern), so at-least-once and out-of-order delivery converge on the latest producer state — in particular, a stale `ProducerCreated` (re)delivered after a `ProducerActivated` SHALL NOT downgrade an `active` row to `registered`. The handler SHALL perform database work only; its projection write and the delivery-status flip SHALL share one transaction.

Consuming these events SHALL have **no Product Master lifecycle side effects**: `ProducerRetired` SHALL **never** transition any existing `active` Product Master — existing actives are **preserved**; only **new** activations (and new child activations under those Masters) are blocked (block-new, never cascade-retire). `ProducerActivated` SHALL **never** auto-activate any `draft`/`reviewed` Product Master — it only updates the projection; the Master's `reviewed → active` transition remains operator-initiated (no auto-replay). `ProducerCreated` SHALL only insert the `registered` projection row.

#### Scenario: ProducerCreated makes a producer known for Master creation

- **GIVEN** a Producer with no producer-state projection row
- **WHEN** a `ProducerCreated` event for that `producer_id` is delivered to the consumer
- **THEN** the projection records the Producer as `registered`, a Product Master can now be created against that `producer_id` (held in `draft`/`reviewed`), and its activation remains gate-blocked until the Producer becomes `active`

#### Scenario: ProducerActivated enables a previously-blocked Master

- **GIVEN** a Product Master in `reviewed` whose linked Producer is not yet `active` (its activation is currently gate-blocked)
- **WHEN** a `ProducerActivated` event for that `producer_id` is delivered to the consumer
- **THEN** the projection records the Producer `active`, and the Master is now activatable by an operator — but no Master state changed as a side effect of consuming the event

#### Scenario: ProducerRetired blocks new activations and preserves existing actives

- **GIVEN** a Producer with one `active` Product Master and one `reviewed` Product Master
- **WHEN** a `ProducerRetired` event for that `producer_id` is delivered
- **THEN** the projection records the Producer `retired`, the `active` Master is left unchanged (`active`), and any attempt to activate the `reviewed` Master is now rejected by the gate

#### Scenario: The consumer is idempotent and order-tolerant

- **WHEN** the same `ProducerActivated` event is delivered twice, or a stale event with a lower `id` than the watermark arrives after a newer one (e.g. a `ProducerCreated` redelivered after the Producer's `ProducerActivated` was applied)
- **THEN** the projection reflects the latest applied event exactly once, the watermark never regresses, and re-delivery produces no duplicate, downgraded or reverted state

#### Scenario: The gate reads only Catalog's projection

- **WHEN** the producer-state projection and the consumer are inspected
- **THEN** the consumer reads only the event payload (`producer_id`) and writes only the Catalog-owned projection table, with no import of or query against any Module K model or table

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 14.5 (PIM consumes `ProducerActivated` to enable Product Master activation and `ProducerRetired` to block new activations; existing actives preserved per BR-Lifecycle-4) · § 13.4 BR-Producer-2 (revocation/retirement is block-new, not cascade) · § 3.2 + § 5.1 step 3 (the Producer must exist in Module K's Registry for the Master's link to be bound) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 5 AC-0-EVT-20 (consume `ProducerActivated` → enable), AC-0-EVT-21 (consume `ProducerRetired` → block new, preserve actives), § 3 AC-0-FSM-13 (existing actives preserved), § 4 AC-0-BR-BulkImport-4 (no auto-replay on upstream `*Activated`), § 6.1 AC-0-XM-2 (creation-time producer existence — served by the `registered` rows) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 6.1 AC-K-XM-2, § 4 AC-K-BR-Producer-4 · openspec/specs/event-substrate/spec.md (Per-Consumer Delivery Ledger; Inline Delivery and Scheduled Sweep; Ordering and Consumer Obligations — the per-entity watermark pattern) · app/Modules/Parties/Events/{ProducerCreated,ProducerActivated,ProducerRetired}.php · decisions/2026-06-12-event-substrate-and-audit-store.md (inline consumer; DB-work-only; idempotent) · CLAUDE.md invariant 10 · interview decisions 2026-07-08 (existence via the widened projection — one Catalog-side source of producer knowledge)._

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

### Requirement: Layer-1 Case-Configuration Whitelist

The product-catalog SHALL carry the **Layer-1 input to the layered breakability model** as an optional whitelist of Case Configurations admissible **per (Product Variant, Format) pair** — the cataloging-level statement "this product, in this format, can in principle be packaged in these forms". The whitelist SHALL be modelled as a within-module, FK-integral structure keyed by Product Variant, Format and Case Configuration (never a JSON blob or configuration file — the whitelist is operator-maintained runtime data), and an **empty/absent whitelist for a pair SHALL mean permissive** (every Case Configuration is admissible for that pair). The whitelist SHALL carry **no breakability flag** and SHALL NOT be read as one: Layer 1 catalogs *possibility* only — a whitelisted Case Configuration still defaults to breakable unless Layer 2 (Module A) or Layer 3 (Module S) declares otherwise, the effective rule is computed downstream, and **no module reads any `is_breakable` flag from PIM because PIM exposes none**.

Maintaining a pair's whitelist (replacing its admitted Case-Configuration set) SHALL be an operator write requiring an authenticated operator principal, permitted while the Variant is `draft`, `reviewed` or `active` (rejected on a `retired` Variant): reductions on an `active` Variant are the J-13 core case. Each maintenance write SHALL record an audit record (action verb `whitelist_updated`) carrying the before-and-after Case-Configuration sets for the pair, and SHALL record **no domain event**, SHALL NOT increment the Variant's `version`, and SHALL NOT re-arm review (the whitelist is neither review-governed identity content nor observational enrichment).

Enforcement at PIM scope SHALL be at **Sellable SKU (Intrinsic) activation**: beside the existing *Activation Cascade* conjuncts, the `reviewed → active` transition SHALL be rejected with a localized exception when the SKU's referenced Case Configuration is not in a **non-empty** whitelist for the SKU's (Product Variant, Format) pair, resolved through its Product Reference. Reductions SHALL follow the retirement-cascade semantics (§ 4.5): existing Sellable SKUs referencing a now-excluded Case Configuration **remain valid for their current lifecycle** — only **new** Sellable SKU activations against the excluded entry are blocked. The downstream legs — Module A's Layer-2 upper-bound check at allocation creation (§ 7.2) and the rule that Layer-1 changes never retroactively invalidate Layer-2 declarations on already-active allocations — SHALL remain **documented seams** (Modules A/S do not exist yet).

#### Scenario: Reducing an active Variant's whitelist blocks only new SKU activations

- **GIVEN** an `active` Product Variant whose whitelist for a Format admits three Case Configurations (e.g. OWC6, CARTON12, loose) and an `active` Sellable SKU referencing OWC6 for that (Variant, Format) pair
- **WHEN** an operator removes OWC6 from the pair's whitelist, and a new Sellable SKU referencing OWC6 for the same pair is created, submitted, and its activation attempted
- **THEN** the existing `active` SKU remains `active` and untouched, the new SKU's activation is rejected with a localized exception naming the whitelist condition (the SKU stays `reviewed`, no `SellableSKUActivated` event), and the whitelist change is recorded in the audit trail with the before/after sets, no domain event, and no Variant `version` change

#### Scenario: An empty whitelist is permissive

- **WHEN** a Sellable SKU is activated whose (Product Variant, Format) pair has no whitelist rows (and the Activation Cascade and Approval Governance pass)
- **THEN** the activation succeeds regardless of which `active` Case Configuration the SKU references

#### Scenario: Layer 1 exposes no breakability flag

- **WHEN** the PIM schema is inspected
- **THEN** the whitelist structure carries only Product Variant / Format / Case Configuration references (with FK integrity and a uniqueness constraint on the triple), and no `is_breakable`/`breakable`/equivalent field exists anywhere in PIM

#### Scenario: Whitelist maintenance is operator-floored and state-guarded

- **WHEN** a whitelist replace is attempted in a `system`/unauthenticated context, or against a `retired` Product Variant
- **THEN** it is rejected — respectively on the operator-principal floor, or with a localized exception naming the state — and no whitelist row, audit record or event is written

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 7.1 (Layer 1 — possible case configurations; permissive default; reduction semantics), § 3.3 (the Variant carries the Layer-1 whitelist per Format), § 7.4 (Layer 1 does not contribute to the effective rule; no is_breakable in PIM), § 7.2 (Module A upper-bound seam), § 4.5 (retirement-cascade semantics for reductions) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 2 AC-0-J-13 (whitelist reduction on an active Variant), § 6.3 AC-0-XM-11 (Layer 1 is PIM's only breakability input; no is_breakable), § 4.6 AC-0-BR-RefData-2 · interview decisions 2026-07-08 (per-(Variant, Format) pivot; activation-gate enforcement; audit-only maintenance)._

### Requirement: Identity Edit and Re-Versioning

The product-catalog SHALL provide **in-place, versioned identity edits** for the two AC-governed identity-bearing surfaces: the **Product Master identity content** (product name, appellation, region, and the winery-story prose — all review-governed content) and the **Composite SKU constituent composition** (the ordered set of constituent Product References). An identity edit SHALL: require an authenticated operator principal; be state-guarded against a transaction-locked re-read — permitted in `draft`, `reviewed` and `active`, rejected on a `retired` entity (reopen first) with a localized exception; write the new values **in place** on the same row (stable primary key and downstream references — the physical-shape decision under DEC-073), incrementing the entity's `version` by exactly one in the same transaction; and record an audit record (action verb `identity_updated`) carrying the **full before-and-after state** of the changed fields — the append-only audit trail is how every old version remains retrievable (old versions are deprecated, never deleted). An identity edit SHALL record **no domain event**: the catalog event surface stays closed to the 21 lifecycle events plus `EnrichmentDataUpdated`.

A Product Master identity edit that changes the product name or appellation SHALL re-check the type-defined identity key (`producer + product name + appellation`, BR-Identity-1) against every **other** non-retired Master and reject a collision, leaving the entity unchanged. A Composite SKU composition edit SHALL re-check the **N ≥ 2 distinct-constituent** floor; when the Composite is `active`, every constituent Product Reference in the **new** set SHALL itself be `active` (the *Activation Cascade* condition re-asserted at edit time, so an `active` Composite can never come to reference a non-`active` constituent through an edit); in `draft`/`reviewed` no constituent-state condition applies (the cascade gate applies at activation).

Editing review-governed identity content while the entity is in `reviewed` SHALL **re-arm review** (see the *Approval Governance* Requirement): activation is blocked until an explicit `re-submit`. An identity edit on an `active` entity SHALL leave it `active` — the state machine has no `active → reviewed` transition; the version increment and the audited before/after are the control on the operator correction path.

The Product Reference is explicitly **not** an edit surface: its `(variant, format)` composition is its identity — changing the composition is a new PR, never an in-place edit (BR-Identity-3/4).

#### Scenario: A Master identity edit creates a new version with full before and after

- **WHEN** an operator edits an `active` Product Master's product name
- **THEN** the Master's `version` increments by exactly one, the Master stays `active` on the same row, an audit record with action `catalog.product_master.identity_updated` carries the full before/after of the changed fields (the old name retrievable from the audit trail), and no domain event is recorded

#### Scenario: A Composite composition edit follows the same versioning semantics

- **WHEN** an operator replaces an `active` Composite SKU's ordered constituent set with two or more distinct `active` Product References
- **THEN** the Composite's `version` increments by one, the before/after ordered constituent id lists are recorded in the audit record, the Composite stays `active`, and no domain event is recorded
- **WHEN** the replacement set contains fewer than two distinct Product References, or (on an `active` Composite) any non-`active` constituent
- **THEN** the edit is rejected with a localized exception and the composition, `version`, audit log and event log are unchanged

#### Scenario: The dedup key is re-checked on edit

- **WHEN** a Product Master identity edit changes name or appellation into collision with another non-retired `WINE` Master's `producer + name + appellation` key
- **THEN** the edit is rejected with the BR-Identity-1 reason, the original values persist and `version` is unchanged

#### Scenario: Identity edits are operator-floored and blocked on retired

- **WHEN** an identity edit is attempted in a `system`/unauthenticated context, or on a `retired` entity
- **THEN** it is rejected — respectively on the operator-principal floor, or with a localized exception naming the state — and no field, `version`, audit or event write occurs

#### Scenario: A draft edit versions the entity without arming review

- **WHEN** a `draft` Product Master is edited, then submitted for review, then activated by a distinct approver (with the producer gate passing)
- **THEN** the edit incremented `version`, and the activation is NOT blocked by the earlier edit — the latest review-freshness-relevant action at activation time is the submit

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 4.8 (version immutability and audit — new versions, old deprecated never deleted, full before/after), § 13.3 BR-Audit-1, § 5.5 (editing captured identity post-creation = standard audited change under § 4.8), § 4.3 (the Creator edits in place), § 3.4 + § 13.1 BR-Identity-3/4 (PR composition is never an in-place edit), § 3.8 + § 13.5 BR-SKU-2 (N ≥ 2 distinct constituents) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 4.3 AC-0-BR-Audit-1 (identity edit on Master + Composite constituent-composition change, same versioning semantics), § 2 AC-0-J-7, § 4.1 AC-0-BR-Identity-1 · decisions/2026-07-02-adopt-dec-019-review-freshness-resubmit.md (the edit-re-arms leg deferred to RM-14 — discharged here) · interview decisions 2026-07-08 (in-place + version++; per-state matrix; cascade re-assert on active Composite; scope = Master identity + Composite composition only)._

### Requirement: Enrichment Data Update

The product-catalog SHALL treat the Product Variant's **observational enrichment metadata** — at launch the tasting notes; the surface SHALL be field-agnostic so the adapter-fed enrichment fields (critic scores, market data) join it additively when the enrichment adapter lands — as **mutable outside the lifecycle**. An operator MAY update a Variant's enrichment while the Variant is `draft`, `reviewed` or `active` (rejected on `retired` with a localized exception), under an operator-principal floor. An update that actually changes the stored value SHALL record the **`EnrichmentDataUpdated`** domain event — the 22nd catalog event, name unchanged by the generalisation — in the same transaction, tagged module `catalog`, entity type `ProductVariant`, with a **PII-free** payload referencing the Variant by id, plus an audit record (action verb `enrichment_updated`) with the before/after values. An update that changes nothing SHALL be a **no-op** (no event, no audit record, no write). An enrichment update SHALL NOT increment the Variant's `version`, SHALL NOT re-arm review (observational edits never gate the *Approval Governance* review-freshness condition), and enrichment data SHALL never be used for pricing or allocation decisions (BR-Audit-2 — observational only; the Module S marketing consumer is a documented seam).

#### Scenario: EnrichmentDataUpdated fires on a post-active enrichment change

- **WHEN** an operator updates an `active` Product Variant's tasting notes to a different value
- **THEN** exactly one `EnrichmentDataUpdated` domain event is recorded in the same transaction (module `catalog`, entity type `ProductVariant`, PII-free payload referencing the Variant by id), an audit record carries the before/after, and the Variant stays `active` with its `version` unchanged

#### Scenario: Enrichment is distinct from the lifecycle triplet

- **WHEN** the catalog event log is inspected after an enrichment update
- **THEN** the update recorded no `*Created`/`*Activated`/`*Retired` event, and `EnrichmentDataUpdated` is recorded only by the enrichment path — never by a lifecycle transition

#### Scenario: A no-change update is a no-op

- **WHEN** an enrichment update carries a value identical to the stored one
- **THEN** no domain event, no audit record and no write occur

#### Scenario: Enrichment never re-arms review

- **GIVEN** a `reviewed` Product Variant whose latest review-freshness-relevant action is its submit (no rejection, no identity edit)
- **WHEN** its tasting notes are updated and a distinct approver then activates it (parent gate passing)
- **THEN** the activation succeeds — the enrichment audit row neither blocks nor clears the review-freshness condition

#### Scenario: Enrichment updates are operator-floored and blocked on retired

- **WHEN** an enrichment update is attempted in a `system`/unauthenticated context, or on a `retired` Variant
- **THEN** it is rejected — respectively on the operator-principal floor, or with a localized exception naming the state — and no event, audit or field write occurs

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 14.1 last paragraph (`EnrichmentDataUpdated` — emitted when observational enrichment metadata changes on a Product Variant; mutable post-activation; does not pass through the lifecycle), § 3.3 (vintage-level enrichment in the WINE attribute set), § 9.1 (variant-specific prose: tasting notes, critic scores), § 13.3 BR-Audit-2 (enrichment is observational only), § 14.5 (Module S consumes it for marketing — deferred seam) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 5 AC-0-EVT-8, AC-0-EVT-15 (consumer deferred), § 4.3 AC-0-BR-Audit-2 · interview decisions 2026-07-08 (tasting_notes only at launch; event on real change; no version bump; no re-arm)._

