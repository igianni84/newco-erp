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

Every product-catalog spine entity SHALL carry a `lifecycle_state` whose domain is exactly `draft`, `reviewed`, `active`, `retired`, and SHALL be created in the `draft` state. This change SHALL record state but SHALL NOT implement any state transition, the Creator→Reviewer→Approver approval workflow, rejection handling, the activation/retirement cascades, or the Producer-activation gate; those behaviours are deferred to the `catalog-lifecycle-approval` change. Consequently, downstream-facing `*Activated` and `*Retired` domain events SHALL NOT be emitted by this change.

#### Scenario: A newly created entity is draft

- **WHEN** any spine entity (Product Master, Product Variant, Product Reference, Format, Case Configuration, Sellable SKU, Composite SKU) is created
- **THEN** its `lifecycle_state` is `draft`

#### Scenario: No transition path exists in this change

- **WHEN** the catalog code surface is inspected for this change
- **THEN** there is no operation that transitions an entity out of `draft` (no review, approve, activate, or retire path) and no `*Activated`/`*Retired` event is ever recorded; the four-state domain is defined on the column for the lifecycle change to drive

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 4.1 (the four-state lifecycle `draft → reviewed → active → retired`) · § 14.2 (`*Created` = `<null> → draft`; `*Activated`/`*Retired` are transition events) · Build Workplan § 2 Phase 2 (the 4-state lifecycle + 3-step approval is a Module 0 subtask — sliced after the spine) · openspec/changes/catalog-product-spine/proposal.md (slice boundary)._

### Requirement: Product Master

The Product Master SHALL be the top of the product hierarchy and the parent of every Product Variant. Its category-neutral core SHALL carry the product name, the Product Type, a **producer reference by id** (a plain identifier into Module K — never a cross-module Eloquent relationship, join, or model import), the `lifecycle_state`, and audit/version fields; its `WINE` attribute set SHALL carry appellation/region and translatable descriptive prose (the winery story) held as i18n-keyed text with per-attribute English fallback. For Product Type `WINE`, a Product Master SHALL be unique on the **type-defined identity key** `producer + product name + appellation`, and a creation whose identity key collides with an existing non-retired Product Master SHALL be rejected (deduplication enforced at creation, on the manual baseline path). `appellation` SHALL be a real, indexable attribute so the uniqueness constraint is enforceable portably on both PostgreSQL and SQLite.

#### Scenario: Create a WINE Product Master

- **WHEN** an operator creates a Product Master of type `WINE` with a product name, a producer id, and an appellation
- **THEN** it is persisted in `draft` with the neutral core fields set, the appellation/region and winery-story prose held in the `WINE` attribute set, and the producer captured as a bare id (no Eloquent relation crosses the module boundary)

#### Scenario: Duplicate identity key is rejected

- **WHEN** a Product Master is created whose `producer + product name + appellation` matches an existing non-retired `WINE` Product Master
- **THEN** the creation is rejected with a clear reason; two distinct identity tuples both succeed

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 3.2 (Product Master — highest-level identity; producer link is a hard reference, not the entity) · § 13.1 BR-Identity-1 (uniqueness per type; `WINE` = producer + name + appellation) · § 8 (translatable descriptive content, six locales) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 2 AC-0-J-3 (manual baseline creation + dedup — launch-critical), § 4.1 AC-0-BR-Identity-1 (type-defined key; manual-path half launch-critical) · CLAUDE.md invariant 10 (no cross-module DB access — events + contracts only) · openspec/specs/i18n/spec.md (Translatable text)._

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

