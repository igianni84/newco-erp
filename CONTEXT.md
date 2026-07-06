# NewCo ERP — Ubiquitous Language

Glossary of record for the NewCo producer-club wine aggregator. Seeded from the v0.3-MVP spec; extended via grill-with-docs sessions as terms are resolved. Definitions only — no implementation details. Full semantics: the module PRDs in `spec/02-prd/`.

## Product Catalog (PIM)

The structural product spine of Module 0 — category-neutral by design (§16/§18): each entity has a neutral core with type-specific attributes held off it, so a future Product Type slots in additively without reshaping the core or the cross-module event contract. At launch the only Product Type is `WINE`. Spine entities are born `draft`; their **lifecycle transitions**, the **Creator → Reviewer → Approver approval governance** and the **Producer-activation gate** are implemented by `catalog-lifecycle-approval` (the spine recorded the `lifecycle_state` but deferred every transition — see *Product lifecycle* below).

**Product Master**:
The top of the product hierarchy and the parent of every Product Variant. Its category-neutral core carries the product name, the Product Type, a producer reference **by id** (a plain identifier into Module K — never a cross-module relation, join or model import), lifecycle state and audit/version; its `WINE` attribute set holds appellation/region and the translatable winery story. For `WINE` a Master is unique on `producer + product name + appellation`; a creation colliding with a non-retired Master is rejected at creation. Wine-display alias: "Wine Master".
_Avoid_: Wine Master (a display alias, never a code/contract name), product, SKU

**Product Variant**:
A release of a Product Master, belonging to **exactly one** Master (structurally enforced). Its variant axis is a type-neutral identifier on the core; for `WINE` the axis is **vintage** (a year or a non-vintage marker) held in the `WINE` attribute set, never as a core column. Wine-display alias: "Wine Variant".
_Avoid_: Wine Variant (a display alias, never a code/contract name), vintage (that is the WINE axis, not the entity)

**Product Reference (PR)**:
The atomic product identity and the universal product key across modules, composed of **exactly two dimensions** — a Product Variant and a Format. A Case Configuration is **never** part of PR identity: the same Variant + Format resolves to the **same** PR whether later sold loose, in an OWC, or in a carton. The `(variant, format)` pair is unique and is the PR's identity (changing the composition is a new PR, not an in-place edit). Wine-display alias: "Bottle Reference (BR)".
_Avoid_: Bottle Reference, BR (display aliases, never code/contract names), SKU, packaging

**Format**:
A standalone PIM reference entity (no parent) representing the physical size/measure of the atomic unit — for `WINE`, the bottle size. A Product Reference references exactly one Format. Name kept unchanged by the naming cascade.
_Avoid_: bottle size (Format is category-neutral; bottle size is the WINE reading), packaging (that is Case Configuration)

**Case Configuration**:
A standalone PIM reference entity, distinct from Format, carrying packaging-form attributes only — units per case, packaging type, physical form — referenced **only** by a Sellable SKU (Intrinsic). It carries **no breakability flag**: whether a case may be split at sale is decided downstream (Module A Layer 2 / Module S Layer 3), never as a property here.
_Avoid_: Format (a distinct entity), Case (the physical Case entity is Module B), breakability flag

**Sellable SKU**:
The commercial sellable unit, in two shapes. An **Intrinsic** SKU = one Product Reference + one Case Configuration + commercial attributes (commercial name, marketing copy); it is the **only** SKU shape that references a Case Configuration. A **Composite SKU** = a curated, ordered bundle of **N ≥ 2** constituent Product References, where one PR may recur across composites (many-to-many). PIM is **producer-agnostic** about a Composite's constituents — single-producer admissibility is a Module S Offer-publication rule, not a PIM rule. Both names kept unchanged by the naming cascade.
_Avoid_: Offer (the published commercial proposition — Module S), Voucher (the per-bottle entitlement), bundle (use Composite SKU)

**Product Type**:
A first-class classifier on the Product Master; at launch the **only** value is `WINE`, and constructing a Master of any other type is rejected (fail-closed). It is the switch selecting, per product, the per-type attribute set, the variant-defining dimension and the type-defined identity key. Modelled as a backed enum (not EAV / a rules engine); a future type is a new enum case plus its attribute table(s).
_Avoid_: category (overloaded), EAV, dynamic/configurable type

**Naming cascade (category-neutral)**:
The §18 rule that the category-neutral `Product*` names — Product Master, Product Variant, Product Reference, and the `ProductMaster*`/`ProductVariant*`/`ProductReference*` event families — are the canonical code and contract identifiers, while the former wine-specific names ("Wine Master", "Wine Variant", "Bottle Reference"/"BR") survive **only as wine-display aliases** (presentation/documentation), never as structural or event identifiers. Format, Case Configuration, Sellable SKU and Composite SKU keep their names unchanged.
_Avoid_: WineMaster / WineVariant / BottleReference as code or event names

**Product lifecycle (the four-state FSM)**:
The uniform `draft → reviewed → active → retired` state machine every spine entity carries (§4.1, BR-Lifecycle-2), driven by explicit operator Actions that are the **sole writers** of `lifecycle_state` (the immutability discipline of the `Create*` seam, extended to state — the Models stay persistence-only). Four transitions: **submit for review** (`draft → reviewed`), **activate** (`reviewed → active`), **retire** (`active → retired`), **reopen** (`retired → reviewed`). Each is **from-state guarded** against a transaction-locked re-read — an out-of-state call is rejected with a localized `IllegalLifecycleTransition`, leaving state, audit and event logs unchanged. Implemented by `catalog-lifecycle-approval` (the spine recorded the state but deferred the transitions).
_Avoid_: status (the column is `lifecycle_state`), a transition method on the Model (the Action is the sole writer)

**Review checkpoint**:
The `draft → reviewed` transition — an internal-to-PIM checkpoint, **audit-only and event-silent**: it records an audit record but **no** domain event, and **no `*Reviewed` event exists** anywhere in the catalog surface (§4.2, AC-0-FSM-8). The next domain event in an entity's lifecycle after its `*Created` is its `*Activated`.
_Avoid_: ProductMasterReviewed / any `*Reviewed` event (none is ever recorded)

**Reopen**:
The `retired → reviewed` transition that returns a retired entity to the approval flow; **audit-only, no domain event**. Re-activation therefore flows `retired → reviewed → active`, and the `reviewed → active` step **re-checks** the activation gate and the approval governance afresh (AC-0-J-10) — re-activation is never exempt.
_Avoid_: un-retire / restore (it re-enters review, it does not jump straight back to `active`)

**Approval governance (Creator → Reviewer → Approver)**:
The separation-of-duties workflow over every commercial-impact transition (`reviewed → active`, `active → retired`, `retired → reviewed`): the actors performing the configured steps **must be distinct people — self-approval is never allowed** (the SoD floor, §4.2, BR-Lifecycle-1). The **role count** is operational configuration (`config('catalog.approval.role_count')` ∈ {2, 3}, default **3** — the full Creator → Reviewer → Approver, or a lighter two-step Creator → Approver; `feedback_prd_rr_approval`); the floor holds at any depth. A governance step **requires an authenticated operator** (`newco_ops` + non-null `actor_id`); a `system`/null actor is rejected. The **append-only audit trail is the system of record** for who performed each step (no per-entity governance columns); **rejection** keeps the entity in `reviewed` (actor + notes + decision recorded, edited in place, **no revert to `draft`** — §4.3, BR-Lifecycle-6), and "rejection-pending" is **derived** from the latest governance audit action.
_Avoid_: maker-checker (use Creator → Reviewer → Approver), `created_by`/`approved_by` columns (the audit trail is the SoR), revert-to-draft (rejection stays in `reviewed`)

**Producer activation gate**:
The hard cross-module gate (§5.4, BR-Producer-1): a **Product Master** cannot reach `active` unless its **linked Producer is `active`**, checked at the `reviewed → active` moment against the **producer-state projection** — never a Module K table (invariant 10). A `producer_id` with no projection row (never activated / unknown to Catalog) is **fail-closed** (not gated open). The PRD's "and KYC-verified" conjunct is satisfied **transitively upstream** (`parties-compliance` tightens `ActivateProducer`) — a documented seam, so the KYC bit never enters Module 0's gate or the cross-module event contract.
_Avoid_: a KYC gate inside Module 0 (KYC is upstream), querying `parties_producers` (read the projection)

**Producer-state projection**:
Catalog's **first cross-module read model** — `catalog_producer_states` (`producer_id`, `status` ∈ {`active`, `retired`}, a per-producer `last_event_id` watermark) that the *Producer activation gate* reads. Maintained by the codebase's **first registered `DomainEventConsumer`**, which consumes Module K's `ProducerActivated`/`ProducerRetired` (§14.5) **idempotently and order-tolerantly** (latest-wins on the watermark). Consuming `ProducerRetired` is **block-new, never cascade-retire** (existing `active` Masters are preserved); consuming `ProducerActivated` **never auto-activates** a queued Master (re-submission is operator-initiated).
_Avoid_: a Producer mirror table (it holds only the gate-relevant `active`/`retired` state, by id — not the Producer), a cross-module query at gate time

**Activation cascade**:
The parent-before-child hard gate evaluated at the child's `reviewed → active` moment (§4.4, BR-Lifecycle-3): a child cannot activate while a parent it composes is not `active` — a **Product Variant** requires its **Product Master** `active`; a **Product Reference** its **Product Variant** `active` **and** its **Format** `active`; a **Sellable SKU** its **Product Reference** `active` **and** its **Case Configuration** `active`; a **Composite SKU** **every** constituent **Product Reference** `active`. **Format** and **Case Configuration** are standalone (no parent gate). Parent reads are **within-module** (the lone cross-module parent, Master → Producer, goes through the projection); because a child can never precede its parent, `*Activated` events fall out **parent-before-child** (§14.3).
_Avoid_: a denormalized "parent active" flag (read the parent's own `lifecycle_state`)

**Retirement cascade**:
Retiring a parent **preserves** existing `active` children (no retroactive invalidation — they run to natural completion) and only **blocks new** activation under the now-`retired` parent (§4.5, BR-Lifecycle-4). An **operator-driven cascade** (§4.7) retires a Master + descendants in one transaction, **parent-before-child** (Master → Variants → PRs → SKUs), recording each `*Retired` in that order. A **single-entity** retire is **blocked only** while the entity is referenced by an `active` terminal sellable SKU — a **Product Reference** ← `active` Sellable/Composite SKU, or a **Case Configuration** ← `active` Sellable SKU (the within-catalog subset of BR-Lifecycle-5, surfaced not silently cascaded); a **hierarchy parent is not blocked** on its children. The cross-module downstream-reference leg is a deferred seam (the Phase-3 referencers).
_Avoid_: blocking a hierarchy parent on its `active` children (parents preserve; only the terminal sellable edge blocks), auto-cascading a single-entity retire to descendants

### Catalog spine creation events — payload contract

On creation, each spine entity records its `*Created` Domain Event through the platform `DomainEventRecorder`, in the **same transaction** as the write, tagged module `catalog`, with the `ActorContext`-resolved `actor_role`, the entity type + id, and a **PII-free** payload (ids + non-PII business data only — a producer is referenced by id, never any party/personal data). Creation records **only** the `*Created` event — the `*Activated`/`*Retired` lifecycle events are recorded later by the transition Actions (see *Catalog spine lifecycle events — payload contract* below). The §14.1 event names keep `SKU` upper-case (`SellableSKUCreated`, `CompositeSKUCreated`) while the canonical model classes are `SellableSku`/`CompositeSku` (the cascade). The payload keys below are the published inter-module contract:

| Event (`name`) | `entity_type` | Payload keys |
|---|---|---|
| `FormatCreated` | `Format` | `format_id`, `name`, `size_label`, `volume_ml`, `lifecycle_state` |
| `CaseConfigurationCreated` | `CaseConfiguration` | `case_configuration_id`, `name`, `units_per_case`, `packaging_type`, `lifecycle_state` |
| `ProductMasterCreated` | `ProductMaster` | `product_master_id`, `name`, `product_type`, `producer_id`, `lifecycle_state` |
| `ProductVariantCreated` | `ProductVariant` | `product_variant_id`, `product_master_id`, `variant_identifier`, `lifecycle_state` |
| `ProductReferenceCreated` | `ProductReference` | `product_reference_id`, `product_variant_id`, `format_id`, `lifecycle_state` |
| `SellableSKUCreated` | `SellableSku` | `sellable_sku_id`, `product_reference_id`, `case_configuration_id`, `commercial_name`, `lifecycle_state` |
| `CompositeSKUCreated` | `CompositeSku` | `composite_sku_id`, `constituent_product_reference_ids`, `constituent_count`, `lifecycle_state` |

### Catalog spine lifecycle events — payload contract

On each `reviewed → active` **activation** and `active → retired` **retirement**, a spine entity records its **verbatim** Module 0 lifecycle event (§14.1, category-neutral per §18) through the platform `DomainEventRecorder`, in the **same transaction** as the `lifecycle_state` write, tagged module `catalog`, with the `ActorContext`-resolved `actor_role` + `actor_id`, the entity type + id, and a **PII-free** payload (entity ids + lifecycle/enum values only — a producer or parent entity is referenced **by id**, never by personal data). The `draft → reviewed` review checkpoint and the `retired → reviewed` reopen are **audit-only — they record no domain event, and no `*Reviewed` event exists** (§14.2, AC-0-FSM-8). In an activation chain or the operator-driven retirement cascade the events are recorded **parent-before-child**, so `domain_events.id` order encodes the hierarchy (§14.3). As with the creation events, the §14.1 names keep `SKU` upper-case (`SellableSKUActivated`/`…Retired`, `CompositeSKUActivated`/`…Retired`) while the `entity_type` is the canonical model class `SellableSku`/`CompositeSku` (the §18 cascade). Each `*Activated` and its paired `*Retired` carry the **identical** payload shape — only the recorded `lifecycle_state` value differs (`active` vs `retired`). The fourteen payload contracts (seven entities × {`*Activated`, `*Retired`}) are the published inter-module contract:

| Event (`name`) | `entity_type` | Payload keys |
|---|---|---|
| `ProductMasterActivated` | `ProductMaster` | `product_master_id`, `producer_id`, `lifecycle_state` |
| `ProductMasterRetired` | `ProductMaster` | `product_master_id`, `producer_id`, `lifecycle_state` |
| `ProductVariantActivated` | `ProductVariant` | `product_variant_id`, `product_master_id`, `lifecycle_state` |
| `ProductVariantRetired` | `ProductVariant` | `product_variant_id`, `product_master_id`, `lifecycle_state` |
| `ProductReferenceActivated` | `ProductReference` | `product_reference_id`, `product_variant_id`, `format_id`, `lifecycle_state` |
| `ProductReferenceRetired` | `ProductReference` | `product_reference_id`, `product_variant_id`, `format_id`, `lifecycle_state` |
| `FormatActivated` | `Format` | `format_id`, `lifecycle_state` |
| `FormatRetired` | `Format` | `format_id`, `lifecycle_state` |
| `CaseConfigurationActivated` | `CaseConfiguration` | `case_configuration_id`, `lifecycle_state` |
| `CaseConfigurationRetired` | `CaseConfiguration` | `case_configuration_id`, `lifecycle_state` |
| `SellableSKUActivated` | `SellableSku` | `sellable_sku_id`, `product_reference_id`, `case_configuration_id`, `lifecycle_state` |
| `SellableSKURetired` | `SellableSku` | `sellable_sku_id`, `product_reference_id`, `case_configuration_id`, `lifecycle_state` |
| `CompositeSKUActivated` | `CompositeSku` | `composite_sku_id`, `constituent_product_reference_ids`, `lifecycle_state` |
| `CompositeSKURetired` | `CompositeSku` | `composite_sku_id`, `constituent_product_reference_ids`, `lifecycle_state` |

The Composite payload's `constituent_product_reference_ids` is the ordered `list<int>` of constituent Product Reference ids; the `constituent_count` that `CompositeSKUCreated` carries is **omitted** from the transition events (trivially derivable, and no transition event carries a derived enrichment).

**Two consumed events (the cross-module gate seam).** This change is the codebase's **first cross-module domain-event consumer**. A registered `DomainEventConsumer` consumes Module K's two supply-side events — **`ProducerActivated`** and **`ProducerRetired`** (payload `{producer_id, status}`, see *Parties supply-side lifecycle events — payload contract* below) — into the Catalog-owned **producer-state projection** (`catalog_producer_states`), which the *Producer activation gate* reads. `ProducerActivated` projects the producer `active` (**enabling** Product Master activation against it); `ProducerRetired` projects `retired` (**blocking new** activations, never cascade-retiring existing actives). The consumer reads only the payload `producer_id`/`status` — it imports and queries **no** Module K model or table (invariant 10).

**Two deferred seams** — spec-faithful preconditions this slice ships scoped, each completed by a named future change:

- **KYC-on-activation** → `parties-compliance` (**now landed**). The §5.4 gate's "linked Producer `active` **and** KYC-verified" conjunct is satisfied **transitively upstream**: Module 0 gates on producer-`active` only, and `parties-compliance` tightened `ActivateProducer` (DEC-071 — KYC fields nullable, added additively) so a Producer cannot reach `active` un-KYC'd — with **no change to Module 0's gate**. The KYC bit never enters the cross-module event contract.
- **Cross-module retirement-blocking references** → the Phase-3 referencer changes (Module A / B / S). BR-Lifecycle-5's downstream-reference leg (active Allocations, issued vouchers, in-flight orders, SKUs on live Offers) is enforced here over **within-catalog** references only (a Product Reference ← `active` Sellable/Composite SKU; a Case Configuration ← `active` Sellable SKU); those cross-module referencers do not exist yet, and the changes that introduce them extend the guard.

## Parties (Party Registry)

The system-of-record of **Module K** — who NewCo deals with: the natural persons it sells to (Customer), the counterparts it procures from (Supplier), the wine Producers and their commercial agreements, and each Customer's Club memberships (Profile). Built spine-first like the Catalog: entities are born in their birth state. The **supply-side** lifecycle transitions (Producer, ProducerAgreement, Club) are implemented by `parties-producer-lifecycle`, the **compliance-screening** lifecycles (Customer + Producer KYC, Customer sanctions) by `parties-compliance`, and the **unified Hold registry** (the trigger-agnostic account-restriction primitive + the `kyc`-Hold coupling) by `parties-holds`; the **demand-side activation** subset (Customer `pending → active`, Profile `Applied → Approved | Rejected → Active`, the Originating-Club one-shot lock) is implemented by `parties-membership-activation`, and the **demand-side suspension** subset (Customer/Profile suspension·lapse·cancellation·deactivation, the Account-status FSM, and the **Hold→`suspended` coupling**) by `parties-membership-suspension`; only the Hero-Package capacity invariant, the `Applied → WaitingList` path and Customer segments remain deferred. Every reference in this spine is **within Module K** (Account→Customer, Club→Producer, ProducerAgreement→Producer/Club, Profile→Customer/Club, Customer→originating Club) — ordinary Eloquent relations and DB foreign keys, never a cross-module reference. **Club** and its **Originating-Club** field are defined under *Commerce & Membership*.

**Party / party-type marker**:
The registry abstraction for an entity NewCo has a commercial relationship with. At launch there are two concrete subtypes, each a distinct `parties_*` table — **Customer** and **Supplier** — stamped with an **immutable party-type marker** (`party_type`, one of `customer` / `supplier` / `third_party_owner`). The marker lives **on the subtype** ("marker-on-subtype"; ADR `2026-06-15-party-type-marker-on-subtype`), so BR-K-Identity-5 ("a Customer can never become a Supplier") holds **by construction** — distinct strongly-typed entities, not rows discriminated in one shared table. The unified `parties_parties` registry, the `third_party_owner` subtype and any marker overlap are **deferred** (none exercised at launch). A **Producer is not a Party** (§4.4) and carries no marker.
_Avoid_: discriminator column on one shared table, a Customer↔Supplier conversion, Producer-as-Party

**Customer**:
A natural person NewCo sells to — the identity-and-eligibility record, **not** a login (the Authentication principal references it by id; the party stays authoritative). Holds the personal data (`email` — globally unique — name, phone, date of birth), currency/locale preferences, and the Originating-Club seam; born `pending` with the immutable marker `customer`. Creating a Customer co-provisions exactly one Account and records a **strictly PII-free** `CustomerCreated`. Carries two **compliance-screening FSMs separate from its `status`** and from each other (`parties-compliance`, additive nullable — DEC-071): a **KYC lifecycle** (`kyc_status` + the administratively-set `kyc_required` flag + the enhanced-KYC trigger fields) and a **sanctions lifecycle** (`sanctions_status` + `last_screening_at` / `next_rescreen_at` / `screening_trigger_source`) — see *KYC lifecycle* and *Sanctions screening lifecycle* under Compliance & Finance. Its **`pending → active` activation** is the explicit `ActivateCustomer` Action behind the *Onboarding activation gate* (`parties-membership-activation`); its **`active → suspended | closed`** edges are now implemented (`parties-membership-suspension`) — `SuspendCustomer` (cascading to the Customer's `Active` Profiles), `ReactivateCustomer` (coverage-guarded cascade-restore) and `CloseCustomer` (terminal, **no** Profile cascade — § 15.1 names none; `closed` is orthogonal to anonymisation). Suspension is **explicit** (manual or via the Hold coupling), never auto-driven by a Profile state or a KYC/sanctions verdict (§ 9.4; AC-K-BR-Customer-1). The activation performs **no** Account transition (the Account is born `active` — §4.7).
_Avoid_: user, login principal, Account (the billing container, a distinct record), auto-suspend on a compliance verdict (the verdict drives a Hold; the Hold drives status)

**Account**:
The Customer's **billing container** — a 1:1 record co-provisioned with the Customer in one transaction, born `active` / `personal` (the sole `AccountType` at launch). It is **not a money ledger**: it carries no balance, no credit and no payment-provider column (monetary credit is the separate **Club Credit** entity; §4.7/DEC-014). It is **event-silent** — its creation records no `AccountCreated`, and so are its status transitions. Its `active → suspended → closed` FSM is implemented (`parties-membership-suspension`) by `SuspendAccount` / `ReactivateAccount` / `CloseAccount`, each from-state guarded and **audit-only** (§ 15 names **no** Account-family event — the `status` write captured in the append-only audit trail is the record). Because the Account is born `active` there is **no `ActivateAccount`** — its only `→ active` edge is the restore `ReactivateAccount`. In production `active → suspended` is driven by an Account-level Hold and its lift restores (the Hold coupling, AC-K-FSM-9).
_Avoid_: wallet, ledger, balance, the Authentication principal (a login record), an `ActivateAccount` Action (born `active`; restore is `ReactivateAccount`), an `AccountSuspended` / `AccountClosed` event (the family is audit-only)

**Address**:
A **billing Address** scoped to the **Customer** (the natural person) — a Module K entity (`parties_addresses`) the Customer **`hasMany`**, a **within-Module-K** relation + FK only (never a cross-module reference — invariant 10). Carries the standard personal address fields (`line1`, optional `line2`, `locality`, optional `region`, `postal_code`, `country_code` — ISO 3166-1 alpha-2, validated at the `CreateCustomerAddress` boundary, no DB CHECK — the `amount_currency` open-code-set precedent) plus the **company-billing affordance** (DEC-068; AC-K-XM-25): **optional** `company_name` + `vat_id`, for an individual collector who transacts through their own company for fiscal reasons — **the Customer stays the natural person and carries no company data and no B2C/B2B discriminator** (the affordance lives on the Address). On anonymisation (`AnonymiseCustomer`) the Address's personal fields (and any `company_name` / `vat_id`) are **overwritten in place with deterministic placeholders in the same operation** as the Customer PII overwrite, and the **row is preserved** (never deleted). At launch only **billing** Addresses are modelled; **shipping** Addresses and the "Address used at purchase" invoice snapshot are downstream (Module C / Module S + E) and out of scope.
_Avoid_: address fields on the Account (the §2 persona prose — DEC-068 puts them on a Customer-scoped Address entity; hanging PII off Account forks the erasure target), a B2C/B2B discriminator on the Customer (the company-billing affordance lives on the Address), hard-deleting an Address on anonymisation (overwrite-in-place — the row is preserved), a shipping Address (deferred)

**Supplier**:
A **minimal Party subtype** — a commercial counterpart NewCo procures from, kept deliberately thin at launch: `legal_name` + the immutable marker `supplier` + timestamps, with **no status column, no version and no creation event** (the PRD names none). The Supplier↔Producer link is Module D's `SupplierProducerLink`, not modelled here; creating a Supplier never auto-creates a Producer (and vice-versa).
_Avoid_: Producer (a distinct entity with a distinct marker), a lifecycle/status on the Supplier

**Producer**:
The wine producer — root of the supply-side registry and the parent of Clubs and ProducerAgreements; referenced across modules **by id** (Catalog's Product Master, Module D). Born `draft`; carries a translatable `description` (English fallback). Its lifecycle is `draft → active → retired` (§4.4), and **activation now enforces the KYC-cleared gate** (`parties-compliance`): `ActivateProducer` admits a Producer whose provenance `kyc_status` is **cleared** — `verified`, `not_required`, **or NULL** (NULL-cleared for additive safety — a Producer created before this change keeps activating) — and **blocks** `pending` / `rejected` (BR-K-Producer-2). **Retiring** a Producer **cascades** — every `active` Club it operates is **sunset** as part of the offboarding (§10.2), while the per-Profile leg of that cascade is demand-side (deferred). The provenance `kyc_status` is **distinct from Customer KYC** and records no domain event (see *KYC lifecycle* / *KYC cleared state* under Compliance & Finance). A Producer is **not a Party** (§4.4); creating one never auto-creates a Supplier (BR-K-Producer-3).
_Avoid_: Supplier (distinct entity/marker), winery (display copy, not the entity name)

**ProducerAgreement**:
The NewCo↔Producer commercial agreement — settlement cadence and optional term dates, optionally narrowed to a single Club; born `draft`. Its lifecycle is `draft → active → superseded | terminated` (§4.6.1): activating a replacement in the same scope **supersedes** the prior `active` agreement (`active → superseded`), pairing old + new in audit history (BR-K-Agreement-3); **terminating** (`active → terminated`) is a permanent end that does **not** cascade to Producer state (§4.6.1). A NewCo net-new entity (DEC-070). The "single active agreement per scope" rule is an **activation-time** rule and is **not** enforced at creation — drafts are created freely.
_Avoid_: contract (overloaded), a Supplier agreement (a different counterpart)

**Agreement scope**:
The partition within which BR-K-Agreement-1 holds — **at most one `active` ProducerAgreement per scope** (§14.6 BR-K-Agreement-1). Scope is the `(producer_id, club_id)` tuple, where a `NULL` `club_id` denotes the **distinct Producer-wide scope**: a Producer-wide agreement and a Club-narrowed one occupy different scopes and may both be `active` at once, while the single-active rule binds two agreements only *within* the same scope (§4.6.1). Resolved by founder decision (2026-06-15).
_Avoid_: a single per-Producer active-agreement uniqueness (Producer-wide and per-Club are distinct scopes; a `NULL` `club_id` is its own scope, not a wildcard)

**Profile**:
A Customer's **membership in one Club** — the Netflix-style model where one Customer holds **many Profiles**, one per Club, with **no separate Membership entity** (the Profile *is* the membership). Born `applied`; carries a `state` (the nine-state §4.2.1 machine), nullable `tier` / `role`, and an `invited_by_customer_id` referral seam (a non-FK id). Uniqueness is **"one non-terminal Profile per (Customer, Club)"** — enforced by a partial unique index over non-terminal states, so a `rejected` / `cancelled` / `inactive` Profile never blocks a fresh one (a re-application after a decline inserts a fresh `applied` row). The **activation subset** of the FSM is implemented (`parties-membership-activation`): `Applied → Approved` / `Applied → Rejected` are the *Membership approval (audit-only)* pair, and `Approved → Active` is `ActivateProfile`. The **demand-side status subset** is now implemented too (`parties-membership-suspension`): `Active ↔ Suspended` (`SuspendProfile` / `ReactivateProfile`, state-preserving — AC-K-FSM-2a), `Active → Lapsed → Active` grace (`LapseProfile` → `ProfileExpired`; `RenewProfile` → `ProfileRenewed`, within the 30-day grace from `lapsed_at`, DEC-034), `Active | Lapsed → Cancelled` (`CancelProfile`, **audit-only**, stamps `cancellation_reason`) and `Active → Inactive` (`DeactivateProfile` → `ProfileInactive`). `Cancelled` / `Inactive` are **terminal soft-delete** (never hard-deleted; the partial index excludes them so a fresh application is admitted). Only the **`Applied → WaitingList` capacity path** stays deferred (the Module-A Hero-Package seam).
_Avoid_: Membership (no such entity — the Profile is it), subscription, the Account (a billing container, not a membership), a `ProfileLapsed` event (the lapse event is `ProfileExpired`), a `ProfileCancelled` event (cancellation is audit-only — the catalog names none)

**Onboarding activation gate**:
The **hard composite gate** `ActivateCustomer` enforces on the Customer `pending → active` transition (§4.1 / §7.1; AC-K-J-1 + AC-K-BR-Identity-3) — a conjunction of the four onboarding gates plus the KYC-cleared rider: `email_verified_at` set **∧** `tc_accepted_at` set **∧** `privacy_accepted_at` set (three **additive-nullable acceptance-timestamp** columns on `parties_customers`, born NULL, written by the deferred registration surface or an operator — no production setter in this slice) **∧** `sanctions_status = passed` **∧** KYC **cleared** (`KycStatus::clears()`, NULL = cleared per DEC-071) whenever `kyc_required`. The transition is **explicit** — `ActivateCustomer` is never auto-fired by a sanctions verdict or a KYC transition (the status FSM is independent of the KYC/sanctions FSMs — §9.4; AC-K-BR-Customer-1); a gate-unmet or wrong-from-state call raises a localized `IllegalCustomerTransition`. The **Hero-Package capacity ceiling** (§13) is a deferred Module-A seam — activation ships **uncapped**.
_Avoid_: auto-activation on a sanctions pass (the gate READS the compliance fields; it never couples the FSMs), a single `terms_accepted_at` (T&C and privacy are two distinct acceptances), a NULL-sanctions Customer treated as passed (NULL sanctions is not `passed`)

**Membership approval (audit-only)**:
The Profile `Applied → Approved` (`ApproveProfile`) and `Applied → Rejected` (`DeclineProfile`) operator Actions — **the one retained producer write** (L-PP / K-Q4; the producer-facing HTTP surface is deferred, the Actions are operator/console-invocable — DEC-083). Both are **audit-only**: §15.2 names **no** `ProfileApproved` / `ProfileRejected`, so — exactly as a KYC transition records no KYC event — the `state` write IS the audit record. The **sole** domain event the approve path may record is the conditional **Originating-Club one-shot lock** (`OriginatingClubLocked`): on the Customer's **first-ever** approval across any Club, `ApproveProfile` sets `originating_club_id` to the approving Club and records the lock in the same transaction — **idempotent** (a later Club's approval finds it set → no write, no event) and **immutable**. The lock is **not** a standalone Action (`LockOriginatingClub` / `SetOriginatingClub` never exist — it is an in-transaction side-effect). Approval ships **uncapped** (the §13 capacity gate is the same deferred Module-A seam as activation).
_Avoid_: a `ProfileApproved` / `ProfileRejected` event (the catalog names none), a standalone Originating-Club lock Action, re-firing the lock on a second Club approval

### Parties spine creation events — payload contract

On creation, each evented spine entity records its `*Created` Domain Event through the platform `DomainEventRecorder`, in the **same transaction** as the write, tagged module `parties`, with the `ActorContext`-resolved `actor_role`, the entity type + id, and a **PII-free** payload (ids + non-PII business data only — parties are referenced by id, never by personal data; a Producer/Club/Agreement is an organisation or program, not a natural person). **Two entities are deliberately event-silent — Supplier and Account record no `*Created`** (the PRD §15 names none; symmetry is not a spec source — an invented `SupplierCreated`/`AccountCreated` would breach fidelity). No `*Activated`/lifecycle/`OriginatingClubLocked` event is recorded at creation — supply-side lifecycle events are recorded by the transition Actions (see *Parties supply-side lifecycle events — payload contract* below), while the demand-side lifecycle events and `OriginatingClubLocked` remain deferred to the demand-side change(s). `CustomerCreated` is the **strict** case — the Customer is a natural person, so its payload **omits** name/email/phone/date_of_birth (those live on the module table, where GDPR erasure operates). `ClubCreated` serialises the fee through `Money::toPayload()` to `{minor_units, currency}` (integer minor units + ISO 4217, never a float — invariant 6). The payload keys below are the published inter-module contract:

| Event (`name`) | `entity_type` | Payload keys |
|---|---|---|
| `CustomerCreated` | `Customer` | `customer_id`, `party_type`, `status`, `preferred_currency`, `preferred_locale`, `originating_club_id` — **PII-free** (no name/email/phone/date_of_birth) |
| `ProfileCreated` | `Profile` | `profile_id`, `customer_id`, `club_id`, `state`, `tier`, `role`, `invited_by_customer_id` |
| `ClubCreated` | `Club` | `club_id`, `display_name`, `producer_id`, `status`, `fee` (`{minor_units, currency}` or null), `registration_flow_type`, `generates_credit`, `invite_only` |
| `ProducerCreated` | `Producer` | `producer_id`, `name`, `region`, `appellation`, `country`, `status` |
| `ProducerAgreementCreated` | `ProducerAgreement` | `agreement_id`, `producer_id`, `club_id`, `status`, `term_start`, `term_end`, `settlement_cadence` |

**Event-silent (no `*Created`):** `Supplier`, `Account`.

### Parties supply-side lifecycle events — payload contract

The supply-side transitions (Producer, ProducerAgreement, Club — implemented by `parties-producer-lifecycle`) each record a **verbatim** Module K lifecycle event (§15.3 / §15.4 / §15.5) through the platform `DomainEventRecorder`, in the **same transaction** as the state write, tagged module `parties`, with the `ActorContext`-resolved `actor_role`, the entity type + id, and a **strictly PII-free** payload (these three entities carry no personal data; other parties appear by id only — invariants 4 & 10). The two **derived** chains are causally linked: each cascade `ClubSunset` carries the `ProducerRetired` event's `id` as its `causation_id` and shares that event's `correlation_id`; the `ProducerAgreementSuperseded` recorded during an activation carries the `ProducerAgreementActivated` event's `id` as its `causation_id` and shares its `correlation_id`. The payload keys below are the published inter-module contract:

| Event (`name`) | `entity_type` | Payload keys |
|---|---|---|
| `ProducerActivated` | `Producer` | `producer_id`, `status` |
| `ProducerRetired` | `Producer` | `producer_id`, `status` |
| `ClubSunset` | `Club` | `club_id`, `producer_id`, `status` |
| `ClubClosed` | `Club` | `club_id`, `producer_id`, `status` |
| `ProducerAgreementActivated` | `ProducerAgreement` | `producer_agreement_id`, `producer_id`, `club_id`, `status`, `supersedes` (the superseded agreement id, or null) |
| `ProducerAgreementSuperseded` | `ProducerAgreement` | `producer_agreement_id`, `producer_id`, `club_id`, `status`, `superseded_by` (the superseding agreement id) |
| `ProducerAgreementTerminated` | `ProducerAgreement` | `producer_agreement_id`, `producer_id`, `club_id`, `status` |

**Key-naming note.** The three ProducerAgreement *transition* events use the fully-qualified id key **`producer_agreement_id`** (matching `producer_id` / `club_id`), which deliberately diverges from the *creation* event `ProducerAgreementCreated`'s shorter `agreement_id` (an archived `parties-core` choice, left unchanged). If a future change wants one family-wide key, normalise it there — do not silently rename the creation event.

**Two deferred seams** — spec-faithful preconditions this slice ships **ungated**, each tightened by a named future change:

- **KYC-on-activation** → `parties-compliance`. `ActivateProducer` enforces **no** KYC-verified gate yet (the §4.4 precondition). The KYC four-state lifecycle and its fields are owned by `parties-compliance` (DEC-071 — sanctions/KYC fields nullable, added additively), which tightens the gate.
- **All-members-gone-on-close** → the demand-side change. `CloseClub` enforces **no** all-members-migrated/expired gate yet (the §4.3 precondition reads Profile state, absent in this slice — vacuously satisfiable today, since no Profile can be `Active` without the demand-side transitions). The demand-side change tightens it when Profile lifecycle lands.

### Parties compliance screening events — payload contract

Each sanctions-screening **completion** records its **verbatim** Module K event (§15.6) through the platform `DomainEventRecorder`, in the **same transaction** as the `sanctions_status` write, tagged module `parties`, with the `ActorContext`-resolved `actor_role` + `actor_id`, entity type `Customer` and id, and a **strictly PII-free** payload (the customer id plus the verdict / trigger-source enum **values** only — never name/email/phone/date_of_birth; the 10-year event store holds no PII — invariants 4 & 10). The recording Action (`RecordCustomerScreening`) selects the event family by `trigger_source`: **`onboarding` → the `CustomerOnboardingScreening*` pair; every other source → the `CustomerRescreening*` pair**. A screening landing **`under_review` is not a completion and records no event** (a later resolution to `passed`/`failed` records the matching `CustomerRescreening*`). **KYC records no event at all** — Customer and Producer KYC transitions are audit-only (the PRD §15.1 names no KYC event); the cleared semantics are carried by `ProducerActivated` when activation fires. The four payload contracts are the published inter-module contract:

| Event (`name`) | `entity_type` | Payload keys |
|---|---|---|
| `CustomerOnboardingScreeningPassed` | `Customer` | `customer_id`, `sanctions_status`, `trigger_source` |
| `CustomerOnboardingScreeningFailed` | `Customer` | `customer_id`, `sanctions_status`, `trigger_source` |
| `CustomerRescreeningPassed` | `Customer` | `customer_id`, `sanctions_status`, `trigger_source` |
| `CustomerRescreeningFailed` | `Customer` | `customer_id`, `sanctions_status`, `trigger_source` |

**Key-naming note.** The published payload key is **`trigger_source`** while the persisted column is **`screening_trigger_source`** — the event publishes the shorter contract key; `sanctions_status` and `trigger_source` are the verdict + source enum `->value`s. No KYC field, name, email or other PII appears.

**Four deferred seams** — spec-faithful preconditions this slice ships scoped, each completed by a named future change:

- **`kyc` Hold coupling** → `parties-holds` (**now landed**). The auto-place-on-`pending` / auto-lift-on-`verified` coupling is now implemented (`RequireKyc` places a Customer-scope `kyc` Hold, `RecordKycVerified` lifts it; `RecordKycRejected` leaves it — §9.1). Because KYC has no domain event (above), the coupling is **within-module Action orchestration** (the KYC Action calls the Hold place/lift), not event-driven — the unified Hold registry (eight `HoldType`s, three `HoldScope`s) and the DEC-181 read-API are `parties-holds` (see *Parties Hold events — payload contract* below and the *Hold* cluster under Compliance & Finance).
- **Sanctions order-completion enforcement** → **Module S** (Phase 4). Module K is **sanctions-blind by design** (§9.3) — it records `sanctions_status`; the purchase precondition `sanctions_status = passed` is enforced at order completion by Module S, not here (a Customer may exist `pending` / NULL).
- **Enhanced-KYC + AML-threshold detection** → **`parties-enhanced-kyc-threshold`** (**now landed**). The €10k-single / €50k-cumulative enhanced-KYC detection scan (idempotent, latched on `enhanced_kyc_flag`) and the AML-threshold auto re-screen (`under_review` + `trigger_source = aml_threshold`, which blocks) are now built — see *Enhanced-KYC Threshold* and *Compliance Review Queue* under Compliance & Finance. The transaction-totals source is a deferred **Module-S** seam (*CustomerTransactionTotalsReader*, null-bound at launch, so the scan is a correct no-op until Module S provides it), and the **daily 12-month re-screen cadence job** (reading `next_rescreen_at`) stays **deferred** (manual-first — §9.5); the operator ad-hoc re-screen Action ships as before.
- **KYC document handling / storage** → operator console + the object-storage ADR gate. This is a domain-**state** slice; document capture/storage is an Admin-Panel + object-storage concern, kept off the open object-storage gate.

### Parties Hold events — payload contract

The two Hold lifecycle transitions each record a **verbatim** Module K event (§15.1) through the platform `DomainEventRecorder`, in the **same transaction** as the `parties_holds` write, tagged module `parties`, with the `ActorContext`-resolved `actor_role` + `actor_id`, entity type **`Hold`** (the Hold is the subject; the scope rides in the payload) and the Hold id, and a **strictly PII-free** payload (the Hold model carries no personal data — invariants 4 & 10). The §15.1 catalog names **only these two** Hold events — there is **no** `ProfileHold*` / `AccountHold*` variant — so a Hold of **every** scope records one of these two names, the `scope_type` + `scope_id` in the payload distinguishing the scope (the zero-invention reading of AC-K-FSM-10's "or Profile/Account analogs", design L4). `CustomerHoldPlaced` is recorded by `PlaceHold` (and, for the auto `kyc` Hold, by `RequireKyc` reusing that path); `CustomerHoldLifted` by **both** lift paths — the operator `LiftHold` (`admin`/`fraud`/`compliance`/`credit`/`chargeback_review`/`storage_payment_failed`) and the system auto-lift `RecordKycVerified` drives for the `kyc` Hold. The `reason` / `lift_reason` are controlled business strings (NULL for system-placed Holds — design L5). The payload keys below are the published inter-module contract:

| Event (`name`) | `entity_type` | Payload keys |
|---|---|---|
| `CustomerHoldPlaced` | `Hold` | `hold_id`, `hold_type`, `scope_type`, `scope_id`, `reason` |
| `CustomerHoldLifted` | `Hold` | `hold_id`, `hold_type`, `scope_type`, `scope_id`, `lift_reason` |

**The read contract (DEC-181).** The Hold registry's one cross-module surface is **not** an event but a read contract: `PartyComplianceStatusReader` (an interface under `app/Modules/Parties/Contracts/`) returns the `ComplianceStatus` DTO — the `(sanctions_status, active-Hold-list)` tuple — for a Customer (`forCustomer(int)`) or a Profile (`forProfile(int)`) scope. `forProfile` cascade-resolves the parent Customer's active Holds into the Profile read (BR-K-Hold-3) and reads the parent Customer's `sanctions_status`; a Profile-scope Hold isolates (BR-K-Hold-4); Account-scope is **not** cascaded (unspecified — design L6 risk note). The DTO carries `HoldType`s + a nullable `SanctionsStatus` and `isClear()` (sanctions `passed` ∧ no active Hold) — **never the `Hold` Eloquent model** (the no-model-leak boundary law). The bound implementation is `DatabaseComplianceStatusReader` (registered in `PartiesServiceProvider`). Module K **reports**; it does **not** block (Hold-blind, §9.3) — the consuming surfaces resolve this by-position when they land.

**Deferred Hold seams** — each ships scoped here, completed by a named future change:

- **Downstream enforcement** → **Module S / C / E** (Phase 3+). Every DEC-181 transaction-initiation surface that *consumes* the read-API (Module S order-completion / cart-add / redemption, Module C pickup / shipment, Module E INV3-charge / refund) and the by-position gate/decorator (AC-K-XM-3..11) is the receiving module's; those modules are stubs today. The read-API ships **ready** so they inherit it.
- **Automatic Hold triggers** for `payment` / `fraud` / `compliance` / `credit` and the two finance-driven types `chargeback_review` (on `CustomerChargebackFlagged` — DEC-168) + `storage_payment_failed` (on `StoragePaymentFailed` — DEC-160) → **Module E** (Phase 6). Each trigger keys off a Module E/S event that does not exist yet; the registry is trigger-agnostic, so all **eight** types + the manual path + the lift discipline ship now (canon MVP-DEC-008).
- **Hold → `suspended` coupling** (demand-side, AC-K-FSM-9) → **`parties-membership-suspension`** (**now landed**). Placing a Hold drives every covered scope in its suspendable from-state to `suspended` and lifting it restores the scope iff no other active Hold still covers it (coverage-recompute, ADR 2026-06-19); a Hold on a non-suspendable scope (the onboarding `kyc` Hold on a `pending` Customer) records the Hold and drives no transition.
- **Hold expiry** — the daily auto-transition job for Hold types carrying an explicit expiry (§4.8.1) → deferred automation (the PRD enumerates no expiring types nor a terminal `EXPIRED` state; the `active | lifted` lifecycle ships).
- **GDPR right-to-erasure × active-Hold precedence** (regulatory vs non-regulatory partition, AC-K-J-9a) → **`parties-anonymisation`** (**now landed**). `AnonymiseCustomer` blocks anonymisation **iff an active `compliance` Hold covers the Customer** — canon **MVP-DEC-015** (`compliance`-only over the 8-type set; there is **no** `sanctions` Hold type — sanctions is the separate `sanctions_status` FSM; ADR `2026-07-02-adopt-dec-015-anonymisation-hold-block-set`, resolving the frozen DEC-027-vs-§8.2 contradiction); every other Hold type proceeds and its controlled non-PII `reason` / `lift_reason` need no overwrite (PII-free by construction). Coverage is read through `PartyComplianceStatusReader` (never the `Hold` model); a block raises the localized `parties.anonymisation.blocked_by_compliance_hold`.
- The **Filament Hold / compliance-ops console** (manual place/lift UI, active-holds view) → **`parties-operator-console`**.

### Parties demand-side activation events — payload contract

Each demand-side **activation** transition (implemented by `parties-membership-activation`) records its **verbatim** Module K event (§15.1 / §15.2 / §15.6) through the platform `DomainEventRecorder`, in the **same transaction** as the state write, tagged module `parties`, with the `ActorContext`-resolved `actor_role` + `actor_id`, the entity type + id, and a **strictly PII-free** payload (entity ids + enum/business values only — never name/email/phone/date-of-birth, and never an acceptance-timestamp **value**). All three are **root** events (no `causation_id`; `correlation_id` defaults to the event's own `event_id`) — the transition each records has no parent event in its transaction. **Approve and decline record no Profile event of their own** — they are audit-only (§15.2 names no `ProfileApproved` / `ProfileRejected`), and the §6.1 spec signal `MembershipApprovedByProducer` is **never recorded** (the approval write is the audit record); the approve path's only event is the conditional `OriginatingClubLocked`. The payload keys below are the published inter-module contract:

| Event (`name`) | `entity_type` | Payload keys |
|---|---|---|
| `CustomerActivated` | `Customer` | `customer_id`, `status` |
| `ProfileActivated` | `Profile` | `profile_id`, `state` |
| `OriginatingClubLocked` | `Customer` | `customer_id`, `club_id`, `profile_id`, `locked_at` |

**Key-naming note.** `OriginatingClubLocked.club_id` is the **locking** Club — the **triggering Profile's** `club_id` (the Club being approved into), not a pre-existing Customer field; `profile_id` is that triggering membership; `locked_at` is an ISO-8601 moment (there is **no** `locked_at` column — the lock surface is `originating_club_id` alone). `status` / `state` are the **post-transition** enum `->value`s (`active`).

**Deferred seams** — spec-faithful preconditions this slice ships scoped, each completed by a named future change:

- **`MembershipFeePaid` listener** (the production trigger for `Approved → Active`) → **Module E** (Phase 6). Module E does not exist; `ActivateProfile` is the within-module writer, invoked by the free-club / operator path now — **no** Module-E event contract is fabricated. **Club Credit** (also fee-paid-coupled) is now built (`club-credit`) with `IssueClubCredit` as its within-module writer; its production issuance trigger — Module E's `MembershipFeePaid` listener — remains the deferred Phase-6 seam.
- **Hero-Package Capacity Invariant** (§13) at approval **and** activation + the `Applied → WaitingList` path → **`parties-hero-package`** (after Module A — the cap lives on Module A's Hero-Package Allocation `qty`). Approval and activation ship **uncapped**.
- **Hold → `suspended` coupling**, `Active → Suspended | Lapsed | Cancelled | Inactive`, and Account-status transitions → **`parties-membership-suspension`** (**now landed** — see *Parties demand-side suspension events — payload contract* below). **Customer segments** (Member / Waiting-list / Legacy + `CustomerSegmentChanged`) → **`parties-customer-segments`** (still deferred).
- **`OriginatingClubLocked` consumers** — Module S settlement-eligibility, Module E D19 accrual (`DiscoveryRevenueShareAccrued`), HubSpot (§6 / §15.6) → **Module S / E**. This slice **records** the lock (the capture); all consumption is downstream.
- The **producer-facing approve/decline HTTP surface** + the Filament operator console over these Actions → **`parties-operator-console`** / producer portal.

### Parties demand-side suspension events — payload contract

Each demand-side **status** transition (implemented by `parties-membership-suspension`) records its **verbatim** Module K event (§15.1 / §15.2) through the platform `DomainEventRecorder`, in the **same transaction** as the state write, tagged module `parties`, with the `ActorContext`-resolved `actor_role` + `actor_id`, the entity type + id, and a **strictly PII-free** payload (entity ids + the post-transition enum **value** only — never name/email/phone/date-of-birth). The Customer events carry `entity_type = 'Customer'` with `{customer_id, status}`; the Profile events carry `entity_type = 'Profile'` with `{profile_id, state}`. A directly-invoked transition records a **root** event (no `causation_id`; `correlation_id` defaults to its own `event_id`); a `ProfileSuspended` / `ProfileReactivated` recorded **inside** the `SuspendCustomer` / `ReactivateCustomer` cascade is a **causation child** of the parent `CustomerSuspended` / `CustomerReactivated` (its `causation_id` + `correlation_id` set to the parent) — the cascade is one honest causal chain (the only non-root events in the slice). The eight payload contracts are the published inter-module contract:

| Event (`name`) | `entity_type` | Payload keys |
|---|---|---|
| `CustomerSuspended` | `Customer` | `customer_id`, `status` |
| `CustomerReactivated` | `Customer` | `customer_id`, `status` |
| `CustomerClosed` | `Customer` | `customer_id`, `status` |
| `ProfileSuspended` | `Profile` | `profile_id`, `state` |
| `ProfileReactivated` | `Profile` | `profile_id`, `state` |
| `ProfileExpired` | `Profile` | `profile_id`, `state` |
| `ProfileRenewed` | `Profile` | `profile_id`, `state` |
| `ProfileInactive` | `Profile` | `profile_id`, `state` |

**Event↔transition naming traps (verbatim §15.2 — invent no event).** The event names do **not** track the state names: `Active → Lapsed` records **`ProfileExpired`** (there is **no `ProfileLapsed`** — the state is `Lapsed`, the event is `ProfileExpired`); `Lapsed → Active` (grace) records **`ProfileRenewed`** (never `ProfileReactivated`); `Suspended → Active` records **`ProfileReactivated`** (**only** this edge — never the grace edge); `Active → Inactive` records `ProfileInactive`; `Active → Suspended` records `ProfileSuspended`. **Audit-only — record NO event:** `CancelProfile` (`→ Cancelled`; §15.2 names **no `ProfileCancelled`** — §15.7 defers the cancellation-signal shape) and **all three Account transitions** (`SuspendAccount` / `ReactivateAccount` / `CloseAccount`; §15 names no Account-family event). No name outside the eight above is recorded — no `ProfileLapsed`, `ProfileCancelled`, `AccountSuspended`, `AccountClosed`, `WaitingListJoined` or `CustomerSegmentChanged`.

**Coverage-recompute suspension coupling**:
The mechanism (ADR 2026-06-19) by which a Hold drives demand-side status. A status-bearing scope (Customer, Account, Profile) is `suspended` **iff** covered by ≥1 **active** Hold — coverage being an active Hold on that exact scope, **plus** for a Profile an active **Customer-scope** Hold on its owning Customer (the BR-K-Hold-3 cascade; Profile-scope and Account-scope Holds isolate — BR-K-Hold-4). Status is **recomputed from Hold coverage on every place/lift, never tracked by provenance**: `PlaceHold` drives every covered scope **in its suspendable from-state** to `suspended` by invoking the explicit `Suspend*` Action (`customer` ⇒ `SuspendCustomer` + cascade, `account` ⇒ `SuspendAccount`, `profile` ⇒ `SuspendProfile`); `LiftHold` and the system `kyc`-lift in `RecordKycVerified` **restore** a covered `suspended` scope by invoking the matching `Reactivate*` Action **iff re-querying coverage shows no other active Hold still covers it** ("the triggering Hold" of §10.1 read as "the last covering Hold" — BR-K-Hold-1). The from-state pre-check keeps the status FSM **independent of the KYC/sanctions FSMs**: a Hold on a non-suspendable scope (the onboarding `kyc` Hold on a `pending` Customer) records the Hold and drives no transition. Suspension is **state-preserving** (AC-K-FSM-2a) — the Action writes only the status field. The transitions are **explicit within-module Actions** (the sole status writers, from-state guarded), so they are also directly operator-invocable (manual suspension — AC-K-BR-Customer-1).
_Avoid_: provenance tracking (`suspended_by_hold_id`), a verdict→status edge (the verdict drives a Hold; the Hold drives status), inline status writes in `PlaceHold` (it invokes the explicit Action), restoring while another Hold still covers the scope

**Deferred suspension seams** — spec-faithful preconditions this slice ships scoped, each completed by a named future change:

- **`MembershipFeePaid` listener** (the production trigger for `Lapsed → Active` renewal / validity extension) → **Module E** (Phase 6). Module E does not exist; `RenewProfile` is the within-module writer, invoked directly now — **no** Module-E event contract is fabricated. The validity-period-expiry trigger for `Active → Lapsed` is likewise a **scheduler** seam (`LapseProfile` is the writer).
- **The per-Profile cancellation signal** Module S consumes for **Club-Credit conversion at Producer offboarding** (AC-K-EVT-14 / §10.2 / DEC-043) → **Module S**. `CancelProfile` ships the within-module `→ Cancelled` transition + the Producer-initiated `cancellation_reason`; the consumer-shaped signal + the offboarding orchestration are Module-S concerns (§15.7: the event shape is a downstream-consumer concern). The Module-K `ForfeitClubCredit` writer now ships (`club-credit`), but `CancelProfile` does **not** call it — the Profile-cancellation → forfeit cascade is a deferred within-module seam.
- **Club-Credit freeze on suspension** ("frozen, no accrual/redemption while suspended; mutable again on restore" — §10.1) → **`club-credit`** (**now landed**). The Club Credit entity is built (Module K); the freeze is enforced at the redemption site — `ApplyClubCredit` reads the owning Profile's live `state` and rejects redemption while `Suspended` (AC-K-FSM-2a) — so suspension stays state-preserving here and the credit becomes redeemable again on restore. The remaining Club-Credit triggers (Module-E issuance/renewal listener, Module-S redemption / DEC-043 conversion, the year-end scheduler, the Profile-cancellation forfeit cascade) stay deferred.
- **Hero-Package Capacity Invariant** (§13) + the `Applied → WaitingList` path → **`parties-hero-package`** (after Module A); **Customer segments** (+ `CustomerSegmentChanged`) → **`parties-customer-segments`**. **Anonymisation** (`closed` ≠ anonymised — AC-K-BR-Customer-2) → **`parties-anonymisation`** (**now landed**): `AnonymiseCustomer` overwrites the Customer PII + its Addresses' personal fields in place with deterministic id-keyed placeholders, stamps `anonymised_at` (**orthogonal to `status`** — a `closed` Customer stays `closed`), redacts the Customer's `audit_records` `before` / `after`, and records the PII-free `CustomerAnonymised`; idempotent, and blocked only by an active `compliance` Hold (canon MVP-DEC-015). The Filament/operator + producer surfaces over these Actions → **`parties-operator-console`** / portal.

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (Customer FSM `pending → active → suspended → closed`), §4.2.1 (Profile FSM — Hold-driven `Active → Suspended`, lapse/grace, cancel/inactive), §4.7 (Account FSM `active → suspended → closed`), §10.1 (suspension model — state-preservation; restore on lift), §15 / §15.1 / §15.2 (the event families; no Account event; no `ProfileCancelled`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-1/2/2a/9/12/13, AC-K-BR-Customer-1/2, AC-K-BR-Hold-1/3/4, AC-K-EVT-1/5 · decisions/2026-06-19-hold-status-coupling.md._

## Commerce & Membership

**Club**:
A Producer-operated membership community — a **Module K (Parties)** registry entity (`parties_clubs`), born `active`, owned by exactly one Producer through a **required and immutable** link. Members pay an annual `fee` (a `Money` — integer minor units + ISO 4217, never a float) equal to that year's Hero Package price. A `registration_flow_type` classifier (`open_registration` / `application_with_approval` / `invitation_only` / `link_onboarding`) governs how members join; `generates_credit` and `invite_only` are launch flags. Its lifecycle is `active → sunset → closed` (§4.3): **sunset** (`active → sunset`) blocks new memberships and new offers while preserving existing Profiles, and is also the per-Club step of the Producer-retirement cascade (§10.2); **close** (`sunset → closed`) is terminal (supply-side transitions implemented by `parties-producer-lifecycle`).
_Avoid_: subscription, tier

**Hero Package**:
The producer's curated annual package whose price sets the club's membership fee for that year.

**Originating Club (OC)**:
The Club through which a Customer first joined. It accrues a 5% share on that customer's Discovery purchases (accrued at INV1). Modelled as a **nullable field** on the Customer (`originating_club_id`), **born NULL** with **no mutation surface** in the registry spine. Its mutation surface is the *Originating-Club one-shot lock* (`parties-membership-activation`): it is set once — **locked at the Customer's first-ever membership approval** to the approving Club (the one-shot `OriginatingClubLocked`, §6.1), **immutable** thereafter (no admin-override at launch), and **may stay NULL** indefinitely for a Customer never approved into any Club (DEC-040). Capturing the seam at creation preserves it (it is unreconstructable later, §6); the lock's downstream consumers (Module S settlement-eligibility, Module E D19 accrual, HubSpot) are deferred.
_Avoid_: home club, referral club

**Discovery**:
The cross-producer storefront available to all members, distinct from each club's own offerings.
_Avoid_: marketplace, shop

**Offer**:
A published sellable proposition with its own lifecycle FSM.
_Avoid_: listing, deal, promotion

**Voucher**:
A customer's entitlement to one specific bottle, held in the Cellar until shipment; 7-state FSM.
_Avoid_: coupon, credit, token, NFT

**Club Credit**:
A **per-Profile prepayment instrument** — the Module K entity (`parties_club_credits`) a member's Hero-Package membership fee converts into spendable credit redeemable against that Club's Offers (DEC-007; PRD §11). Entirely distinct from the Voucher. Carries an `amount` and a `remaining` balance (each a `Money` — integer minor units + ISO 4217, never a float; one currency, **immutable** across the credit's lifetime), a `valid_from`/`valid_to` window (default 31 Dec of the issuance year), and a `state` FSM `active → redeemed | forfeited` (with `redeemed → active` reachable only via restore on order cancellation). At most **one `active` Club Credit per Profile**, enforced **structurally** by a partial unique index on `(profile_id) WHERE state = 'active'` (a terminal credit frees the slot). Its four within-module writer Actions are the **sole** state writers, each from-state guarded + transaction-locked: `IssueClubCredit` (creates `active`, `amount = Club.fee` verbatim when `generates_credit = true`; rejects a null fee), `ApplyClubCredit` (redemption decrement — **K.17 carry-forward** keeps it `active` until `remaining` hits zero → `redeemed`; frozen while the owning Profile is `Suspended`), `ForfeitClubCredit` (`active → forfeited`, terminal) and `RestoreClubCredit` (`redeemed → active`, one-active-respecting). **Audit-only, §11.4 boundary**: Module K records Club Credit *state* and emits **no** domain event — the `ClubCreditIssued` / `ClubCreditApplied` / `ClubCreditRestored` / `ClubCreditForfeited` lifecycle events and the `MembershipFeePaid` issuance trigger are **Module E's** (§11.4 / §15.8); no such event class is fabricated under Module K (mirrors the audit-only `RecordKycVerified` precedent). The cross-module triggers — the Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers, the Module-S checkout redemption (DEC-110/111) + DEC-043 Club-closure conversion, the year-end-lapse scheduler, and the Profile-cancellation forfeit cascade — remain deferred seams.
_Avoid_: voucher (a distinct per-bottle entitlement), coupon (a contra-revenue commercial discount, mutually exclusive with Club Credit at checkout), balance, a Module-K `ClubCredit*` / `MembershipFeePaid` event (Module E owns those — §11.4)

**Cellar**:
The customer's digital holding of vouchers/bottles in storage, from which shipment is requested.
_Avoid_: wallet, inventory (reserved for Module B)

**INV1 / INV2 / INV3**:
The three invoice types — bottle sale (INV1), shipment with excise/VAT (INV2), storage fees (INV3). Issuance lifecycle is Module-S-internal.
_Avoid_: receipt, bill

## Supply & Inventory

**Allocation**:
The single supply primitive: a quantity of a wine lot made sellable by a producer/supplier under commercial terms. Carries the Layer-1 no-oversell guard (`qty − issued ≥ 0`).
_Avoid_: lot (use for wine lots), batch (reserved for InboundBatch)

**Sub-pool**:
The partition of an allocation into `qty_to_serialize` and `qty_non_serialized`. No-oversell is enforced per sub-pool.

**Passive consignment**:
The launch sourcing model: the producer retains ownership until the sale trigger; NewCo procures at 87.5% of producer price on club sales.

**Seller of Record (SOR)**:
NewCo sells to the consumer in its own name and carries the tax/compliance obligations.

**ProcurementIntent / PurchaseOrder**:
The two-level procurement gate in Module D preceding any inbound.

**InboundEvent**:
Two-phase receipt record: `PHYSICALLY_ACCEPTED` then `COST_FINALIZED`.

**InboundBatch**:
The physical receipt of bottles into the warehouse — Module B's inventory entry point.
_Avoid_: shipment (reserved for outbound), delivery

**StockPosition**:
Module B's five-dimension inventory view; the source of Layer-2 ATP.

**ATP (Available-to-Promise)**:
Sellable quantity guard. Layer 1 = allocation-level (Module A); Layer 2 = physical-inventory-level (Module B). Both must hold.

**Case**:
A physical case entity with its own FSM; breakability governed by the Layer-1 whitelist.

**SerializedBottle / NS**:
An NFC-tagged bottle with canonical serial vs the non-serialized (NS) universal fallback path. NFT mint/burn is decoupled behind a feature flag.

## Compliance & Finance

**Hold**:
The unified, trigger-agnostic account-restriction primitive of Module K (`parties_holds`) — the compliance floor every transaction-initiation gate rests on (§4.8 "FLOOR"; AC-K-FSM-10). One registry-of-record table holds **every** Hold (DEC-168 — the `chargeback_review` / `storage_payment_failed` **types** exist now; only their automatic placement lands with Module E), each carrying a `hold_type`, a polymorphic `scope` (`scope_type` + `scope_id`), a two-state `status` (`active | lifted`), an optional placement `reason`, and placement + lift audit metadata (actor + moment, plus `lift_reason`). Born `active`; the **Action is the sole writer** (`PlaceHold` / `LiftHold`). Module K is **Hold-blind by design** (§9.3) for *commercial-action* enforcement — that is the consuming module's (Module S/C/E, deferred). The Hold registry itself is **not** status-blind, however: the Hold → `suspended` **status** coupling is now implemented (`parties-membership-suspension`, ADR 2026-06-19) — `PlaceHold` drives every covered scope in its suspendable from-state to `suspended` (by invoking the explicit `Suspend*` Action), and `LiftHold` restores it **iff no other active Hold still covers it** (coverage-recompute — see *Coverage-recompute suspension coupling*). The Hold is still a distinct primitive from the suspension **state** (a `lifted` Hold leaves no status trace beyond the recomputed status).
_Avoid_: ban, block (use Hold), suspension (a distinct demand-side escalation **state**, not a Hold), coupon/credit (Club Credit is a separate entity)

**Hold type**:
The trigger family of a Hold — a `HoldType` enum with **exactly eight** values (§4.8 + §4.8.1/§15.8; canon MVP-DEC-008): `admin`, `kyc`, `payment`, `fraud`, `compliance`, `credit`, `chargeback_review`, `storage_payment_failed`. The first six are the §4.8 base types; `chargeback_review` (DEC-168, placed on `CustomerChargebackFlagged`) and `storage_payment_failed` (DEC-160, the per-cycle INV3 Hold) are the two finance-driven types §4.8.1/§15.8 name and Module K consumes from Module E — **first-class enum values, not sub-types** (MVP-DEC-008, Q-AD-3 Option B; ADR `2026-07-01-adopt-dec-008-hold-types-8`; AC-K-EVT-18/19). This closed set is the registry's controlled vocabulary; because the registry is **trigger-agnostic** (AC-K-MVP-2), a new automatic trigger reuses an existing type rather than coining a new one. At launch the **only wired automatic trigger** is the `kyc` coupling (see *KYC lifecycle*); the `payment` / `fraud` / `compliance` / `credit` placements and the two finance-driven placements arrive with their Module E/S signals — deferred Module-E seams, but the eight types + the manual operator-placement path + the per-type lift discipline ship now.
_Avoid_: treating `chargeback_review` / `storage_payment_failed` as sub-types that "map onto" `fraud` / `payment` (they are the seventh & eighth first-class values — MVP-DEC-008); assuming a new trigger needs a new type (the trigger-agnostic registry reuses an existing one)

**Hold scope**:
The polymorphic subject of a Hold — a `HoldScope` enum `customer | account | profile` (the `scope_type`) plus a `scope_id` into that subtype's table, with **no DB foreign key** (one column cannot FK three tables — design L1; within-module integrity is the Action's). **Cascade is asymmetric and resolves at read time** (§4.8.1; BR-K-Hold-3/4): a **Customer-scope Hold cascades to all that Customer's Profiles** (interrogating any Profile returns the parent Customer's Holds), while a **Profile-scope Hold isolates** to that one Profile (a sibling Profile is unaffected). The cascade writes **no** duplicate rows — the read-API unions the parent Customer's active Holds into a Profile read. **Account-scope is placeable but is NOT cascaded by the read-API** — the PRD specifies Customer→Profile cascade only and is silent on Account, so an Account Hold has no read-API consumer this slice (design L6 risk note).
_Avoid_: a per-scope Hold-event variant (there is none — see *Parties Hold events*), an Account→Customer cascade (unspecified — deliberately left out), writing one Hold row per Profile (cascade is read-time)

**Hold-lift discipline (per type)**:
A Hold's lift policy is a function of its `hold_type`, **not uniform** (DEC-160 §4.8.1; AC-K-FSM-11; ADR `2026-06-18-hold-lift-discipline-per-type.md`), encoded as `HoldType::autoLiftable(): bool` (true for `kyc` / `payment` only). **Auto-managed** — `kyc` (auto-lifts on KYC clear) and `payment` (auto-lifts on payment success): the system lifts on the resolving signal and an **operator-initiated lift is rejected** (`IllegalHoldLift::autoManaged`). **Operator-lift-only** — `admin`, `fraud`, `compliance`, `credit`, `chargeback_review`, `storage_payment_failed`: never auto-lifted; a lift requires an explicit operator action with actor + reason (`storage_payment_failed` is operator-lift-only *at launch* — manual-first, D4; its `StoragePaymentSucceeded` per-cycle auto-lift is a deferred Module-E path). Lifting an already-`lifted` Hold is rejected (`IllegalHoldLift::notActive`). The one auto-lift exercised end-to-end at launch is the `kyc` Hold on `RecordKycVerified` (the `payment` auto-lift is a deferred Module E seam — the classification + guard ship now). This **refines** root `CLAUDE.md` Invariant #7 ("Holds are never auto-lifted"), which is over-broad relative to the spec — the ADR is the standing authority.
_Avoid_: a uniform never-auto-lift rule (true for only six of eight types), an `auto:` bypass flag on `LiftHold` (the system auto-lift is a distinct within-module path)

**Hold `reason`**:
The placement `reason` and lift `lift_reason` are **controlled business strings** — the same non-PII class as the enum-value and id references the event store already carries, so they are safe in the PII-free Hold event payload (AC-K-EVT-2). **System-placed Holds carry `reason = null`** (the auto `kyc` Hold — the *type is the reason*; design L5): no hardcoded reason string anywhere (i18n invariant 12 stays clean) and no free text in the immutable event log. Operators keep PII out of an operator-supplied reason by contract (UI-enforced later).
_Avoid_: PII / personal data in `reason`, a hardcoded reason string for a system Hold (it is null)

**Compliance read-API (the `(sanctions_status, active-Hold-list)` tuple)**:
The uniform "is this scope clear to transact?" read contract (DEC-181; §4.8.1; AC-K-XM-12) — the single path every downstream transaction-initiation surface will call. It returns the **`(sanctions_status, active-Hold-list)`** tuple for a Customer or a Profile scope, cascade-resolved, as a PII-free DTO carrying `HoldType`s — never the `Hold` model (the no-model-leak boundary law). `isClear()` ⇔ sanctions `passed` ∧ no active Hold. Module K **reports** the tuple; it does **not** block — the blocking is each consuming module's (Module S/C/E, deferred). Exposed **ready** at launch with no live consumer (the seam, not dead code). Contract mechanics: see *Parties Hold events — payload contract*.
_Avoid_: returning the `Hold` model across the boundary, enforcing/blocking inside Module K (it is Hold-blind — Module S/C/E enforce)

**KYC lifecycle**:
The four-state verification FSM `not_required → pending → verified | rejected` carried by both Customer (§9.1) and Producer (§4.4, provenance KYC), held in an **additive nullable** `kyc_status` (DEC-071 — entities are creatable un-screened) and **separate from the entity `status` FSM**. Setting a Customer's `kyc_required` flag transitions `not_required → pending`; explicit operator Actions are the **sole writers** of `kyc_status`, each from-state guarded (an out-of-state call raises a localized `IllegalKycTransition`, leaving state unchanged). The Producer adds a **waive** transition (any state → `not_required`, the operator "deselect"). KYC records **no domain event** — the change is audit-only (§15.1 names none). The **Customer** KYC lifecycle now **couples to the `kyc` Hold** (`parties-holds`): setting `kyc_required` (→ `pending`) auto-places a Customer-scope `kyc` Hold, `→ verified` auto-lifts it, and `rejected` leaves it in place (§9.1) — within-module Action orchestration, so KYC itself still records no *KYC* event (the coupled events are the Hold events `CustomerHoldPlaced` / `CustomerHoldLifted`). Customer KYC and Producer (provenance) KYC are **distinct** fields on distinct entities.
_Avoid_: a KYC status on the login principal (it is a party field), a KYC domain event (none exists), conflating Customer and Producer KYC

**KYC cleared state**:
The non-blocking predicate **`cleared = verified ∨ not_required`** (§9.1 / §4.4; ADR `2026-06-17-producer-kyc-gate-not-required-clears.md` — `not_required` ≡ `verified` at every gate), encoded as `KycStatus::clears()`. At the **Producer activation gate** a **NULL** `kyc_status` is **also treated as cleared** (additive safety — a Producer created before `parties-compliance` keeps activating; the NULL-cleared rule lives **at the gate**, not in the enum). The asymmetry is deliberate: a Customer's NULL `sanctions_status` is **not** cleared — it is treated as not-`passed` / blocked by the downstream purchase gate. `pending` and `rejected` always block.
_Avoid_: NULL-blocks-on-the-producer-side (NULL clears the Producer gate; only Customer sanctions NULL blocks), encoding the NULL-cleared rule in `KycStatus` (it is a gate concern)

**Sanctions screening lifecycle**:
The Customer's four-state sanctions FSM `pending → passed | failed | under_review` plus `under_review → passed | failed` (§9.2), held in additive nullable `sanctions_status` with `last_screening_at` / `next_rescreen_at`. **Independent of KYC** (§9.4) — a sanctions transition never touches `kyc_status` and a KYC transition never touches `sanctions_status`; the two clear separately. An operator Action records each verdict (manual-first — §9.5; no vendor adapter built), stamping `last_screening_at` and `next_rescreen_at` (12 months forward). A `passed` / `failed` is a **completion** and records the matching screening event; **`under_review` is event-silent**. Module K only **records** state — the `sanctions_status = passed` purchase precondition is enforced by **Module S** at order completion (Module K is sanctions-blind, §9.3).
_Avoid_: enforcing the sanctions gate in Module K (it records; Module S enforces), an `under_review` event (none exists), coupling the sanctions FSM to KYC

**Screening trigger source**:
The provenance of a sanctions screening (`onboarding | cadence | aml_threshold | compliance_ad_hoc` — §9.2, DEC-030 / DEC-035), persisted as `screening_trigger_source` and published in the event payload as `trigger_source`. **`onboarding`** is the Customer's **first** screen (guarded — rejected with `IllegalSanctionsTransition` if `last_screening_at` is already set) and selects the `CustomerOnboardingScreening*` events; **every other source is a re-screen** and selects the `CustomerRescreening*` events. The **`aml_threshold`** automation (the enhanced-KYC breach → lightweight re-screen) is **now landed** (`parties-enhanced-kyc-threshold` — see *Enhanced-KYC Threshold*); the **`cadence`** (daily 12-month re-screen job reading `next_rescreen_at`) stays **deferred**, with the operator `compliance_ad_hoc` re-screen shipping as before (manual-first).
_Avoid_: country-change as a launch trigger (not in the set), conflating the column `screening_trigger_source` with the payload key `trigger_source`

**Enhanced-KYC Threshold**:
The compliance-floor detection (§9.1 / §9.2; DEC-035) — a Customer crossing **€10,000 in a single completed transaction OR €50,000 rolling trailing-12-month cumulative** (two **independent (OR)** signals) is escalated to enhanced review. The thresholds are EUR minor-unit constants compared as `Money` (`1_000_000` / `5_000_000`, inclusive `≥` — a floor triggers *at* the threshold; invariant 6). Detection is **idempotent, latched on `enhanced_kyc_flag`** — the `EvaluateEnhancedKycThreshold` workflow fires the escalation **at most once** per Customer; on the first crossing, in one transaction against a locked re-read, it stamps `enhanced_kyc_flag` + `enhanced_kyc_at`, raises a *Compliance Review Queue* entry, records the PII-free `CustomerEnhancedKycReviewRequired`, and initiates the **`aml_threshold`** re-screen (`under_review` via `RecordCustomerScreening`, which **blocks** the Customer until Compliance resolves it). Setting the flag is **orthogonal to `kyc_status`** — it does not move the KYC FSM (enhanced review is handled operationally, no state machine — §9.1). Two trigger paths share the one workflow: a **daily periodic scan** (built now — a scheduler tick, not a queued consumer) and an **at-order-completion check** (a deferred Module-S seam). Totals are read only through *CustomerTransactionTotalsReader* — never a cross-module query (invariant 10).
_Avoid_: calendar-YTD cumulative (it is **rolling** trailing-12-month — a Dec-31 total does not reset on Jan-1), moving `kyc_status` on the flag (orthogonal), a float threshold (EUR minor units), re-escalating an already-flagged Customer (idempotent), forcing `aml_threshold` onto the operator *resolution* (the console re-screen tags `compliance_ad_hoc`; the AML origin stays durable on the review entry + event — design D2)

**Compliance Review Queue** (`ComplianceReview`):
A **within-Module-K** store (`parties_compliance_reviews`) of Compliance work-items raised when a Customer requires review. Each entry records the `customer_id` (a within-module FK — no cross-module relation), the `reason` (`ComplianceReviewReason` — `enhanced_kyc_threshold` is the sole reason in this change), the tripping `threshold_kind` (`ThresholdKind` — `single_transaction | cumulative_annual`), the tripping amount (`tripped_amount_minor` + `tripped_currency` — EUR minor units, invariant 6) and `resolved_at` (NULL on creation). Open vs resolved is **boolean-derivable** (`resolved_at IS NOT NULL`), **not** an FSM (§9.1 — no enhanced-KYC state machine; the `anonymised_at` flag pattern). The sole writer is `CreateComplianceReview` (a `Create*` Action); the resolve action is **deferred** (entries ship creatable + readable). Creating an entry records the **PII-free** `CustomerEnhancedKycReviewRequired` — a **net-new** event over the frozen §15.6 catalog (which names none — added as the breach's audit anchor + the seam a future Compliance dashboard / Module-E consumer reads; the *CustomerAnonymised* precedent), payload `customer_id`, `enhanced_kyc_at` (ISO), `threshold_kind` (value), `amount` (`{minor_units, currency}` via `Money::toPayload()`) — **never** name/email/phone/date_of_birth. If both thresholds trip on one scan, `single_transaction` is recorded (the more acute signal).
_Avoid_: an open/resolved status enum + transition Action (it is a boolean-derivable flag, no FSM), a cross-module FK to Module S/E (within-module `customer_id` only), name/email/phone/dob on the entry or its event (PII-free), a `ProfileReview` / per-scope variant (the queue keys on the Customer)

**CustomerTransactionTotalsReader**:
The within-Module-K read-port (an interface under `app/Modules/Parties/Contracts/`) the *Enhanced-KYC Threshold* detection reads a Customer's spend through — `forCustomer(int): CustomerTransactionTotals`, a DTO carrying two EUR `Money`: `largestSingleTransaction` and `trailingTwelveMonthCumulative` (a **rolling** trailing-12-month window — design D3). The real source is **Module S** order/invoice history (deferred); the launch binding is `NullCustomerTransactionTotalsReader` returning zero totals (registered in `PartiesServiceProvider`), so the periodic scan runs and **detects nothing** until Module S provides the real adapter. This is the sanctioned module-boundary crossing — a small read contract, never a cross-module Eloquent query (invariant 10; the *Compliance read-API* / RM-01 export-manifest precedent).
_Avoid_: a cross-module query into Module S order tables (invariant 10 — use the port), a K-side projection table fed by Module-S events (heavier for no launch benefit — the port defers the seam), a non-EUR total (the reader honours the dual-currency EUR-side rule)

**actor_role**:
The audit envelope field recorded on every operator action (`newco_ops`, `producer`, …).

**ownership_flag** (inventory):
Module B flag on stock: `PRODUCER` or `CRURATED`. Keys to the supplier-payment signal. **Never confuse with PO `ownership`.**

**ownership** (purchase order):
Module D enum on POs: `PRODUCER` / `NEWCO` / `THIRD_PARTY`. Keys to the sale/shipment signal. **Never confuse with inventory `ownership_flag`.**

**Dual-currency recording**:
Every customer-facing financial event records the customer-currency amount AND the EUR amount with a locked FX rate; refunds settle at the original captured rate.

**Money**:
An amount as integer minor units plus an ISO 4217 currency code — never a float or a major-unit decimal (invariant 6). The platform money primitive that Club Credit, prices, refunds and Dual-currency recording are all built on. Negative values are valid (credits, reversals).
_Avoid_: float, decimal amount, major units

**Currency**:
An ISO 4217 code with its minor-unit exponent. The launch set is exactly five: EUR (the base, exp 2), USD, GBP, CHF, JPY (exp 0). An unknown code is rejected (fail-closed), never assumed to be exp 2.
_Avoid_: locale (a currency is not a locale), money (use Money)

**FX Rate**:
The exact decimal string locking a customer-currency↔EUR conversion — never a float (binary-rounding drift would break the exact-rate refund, invariant 5). Stored verbatim; well-formedness only — economic validity (positivity, bounds, snapshot timing) is Module E policy.
_Avoid_: exchange rate as a float, conversion factor

**Dual-Currency Amount**:
The pure-representation bundle that realises Dual-currency recording: a customer-currency Money, its EUR-equivalent Money, the locked FX Rate and the rate's timestamp. Carries no FX policy (snapshot timing, buffers and per-leg lock moments are Module E).
_Avoid_: converted amount, EUR-only amount

## Events & Audit

**Domain Event**:
A named, versioned business fact that one module announces and other modules consume — the sole inter-module API (~120 in the launch catalogue). Immutable once recorded; consumers tolerate cross-transaction arrival order. Payloads reference parties by ID and never carry personal data.
_Avoid_: message, notification, webhook

**Audit Record**:
The immutable trace of an operator or system action: who (actor_role), what, when, before/after state, and the authorization basis. Write-only — inspected for compliance, never consumed by modules. Distinct from a Domain Event (a contract between modules).
_Avoid_: log entry, history

**Financial Event**:
A domain event recording a monetary fact (Module E's catalogue, ~30 types at launch). Dual-currency recorded, immutable post-sync, corrections only via compensating events (credit notes), retained 10 years.
_Avoid_: transaction (overloaded), ledger entry

**Actor context**:
The single seam that resolves the acting `actor_role` and actor id for the current context — the one place an emitter obtains who is acting. An authenticated operator (read from the `operator` session guard) yields (`newco_ops`, the `Operator` id); console, queue, unauthenticated contexts — and the not-yet-wired customer/producer guards — yield (`system`, `null`); a scoped run-as override beats both. The customer and producer guards are a deferred seam that extends the same precedence.
_Avoid_: session, current user, auth guard

## Platform Foundations

**Supported locale**:
One of the six launch locales — `en` (the fallback), `it`, `fr`, `de`, `ja`, `zh_Hans` — the closed set the registry validates against. A locale is a language plus an optional script in Laravel's underscore form (`zh_Hans` = Chinese, Simplified). All six are fixed at launch; adding one is configuration, not a migration.
_Avoid_: language (`zh_Hans` is language + script), currency

**Translatable text**:
Per-row content held as i18n-keyed JSON (`{locale: text}`) with per-attribute English fallback — the mechanism for translatable entity attributes (PIM product copy, etc.). Distinct from the `lang/` files, which carry static UI chrome shared across rows.
_Avoid_: lang/ string, label, caption

**Feature flag / EXT-1**:
A named gate an operator can toggle without a deploy. **EXT-1** (`nft-on-chain`) is the single launch flag — the one named gate for every on-chain surface (NFT mint/burn, custodial wallet, on-chain recovery, the Bottle-Page chain-link), shipped OFF. While it is off, the non-serialized (NS) path is the universal fallback; the per-bottle serialization workflow is never flagged (decoupled, not deferred).
_Avoid_: config toggle, kill switch, NFT switch (it gates more than mint/burn)

## Identity & Access

**Operator**:
A NewCo staff principal who authenticates to the Admin Panel. Carries one or more operator roles; every action it drives records `actor_role: newco_ops`. **Not** a Module K party — operators have no Customer/Producer/Supplier row, so for an Operator the login principal and the acting identity are one and the same record. Owned by the OperatorPanel module.
_Avoid_: user (overloaded), admin (a role, not the principal), Customer/Producer (those are parties, not staff)

**Authentication principal**:
The thin, first-party login record (credentials only) that authenticates an actor — a platform-foundation concern, never a business module's. For a Customer or Producer it **references the Module K party by id** and holds no business identity of its own (the party stays authoritative — a Customer is identity+eligibility, never a login); for an Operator the principal *is* the identity. The Actor context resolves `actor_role` + `actor_id` from the authenticated principal, where `actor_id` is the party/operator id — never the principal's own id. No external IdP at launch (EU-resident, first-party); social/SSO is a deferred seam.
_Avoid_: User, account (the Module K Account is a billing container), password-on-the-Customer

**Operator console**:
The per-module operator surface within the Admin Panel — the OperatorPanel module's window onto one other module's entities (the Catalog console, the Parties console). It owns no entities: it displays the operated module's data and drives every change through that module's domain actions, never writing the entity directly. "Admin Panel" names the whole surface; an Operator console is one module's slice of it.
_Avoid_: admin screen, back-office UI, CRUD page (every write is a domain action)

**Operand enum**:
A domain enum that appears as a **parameter of a domain Action's `handle()`** (e.g. `ClubRegistrationFlowType` on `CreateClub`, `HoldType` / `HoldScope` on `PlaceHold`). The Operator console may import and **construct** an operated module's operand enums — they are part of the Action's write-through call contract, as inseparable from `Actions` as the Eloquent model is from read-`Models` (carve-out widened by [[decisions/2026-06-21-operator-console-operand-enum-carveout]]). Contrast a State enum.
_Avoid_: input enum, config enum, argument enum

**State enum**:
A module's FSM status enum that a domain Action sets **internally** (e.g. `ClubStatus`, `KycStatus`, `ProducerAgreementStatus`). The Operator console only **renders** it, through the model's cast (`->value`), and never imports or constructs it. Contrast an Operand enum.
_Avoid_: status enum, lifecycle enum

## Operations & Integrations

**Vinlock**:
The single French warehouse operator at launch; applies NFC tags at receipt.

**Logilize**:
The WMS (separate tenant). Module C owns its 4 fulfilment streams; Module B owns its 5 inventory-state streams (spec R3).

**White-glove**:
The manual fallback fulfilment path for complex destinations (D3).

**Shipping Order**:
Module C's 5-state outbound FSM with `compliance_hold` / `manual_review` flags; bottle selection is late-binding (FIFO + manual tiebreak at launch).
