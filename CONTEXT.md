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

- **KYC-on-activation** → `parties-compliance`. The §5.4 gate's "linked Producer `active` **and** KYC-verified" conjunct is satisfied **transitively upstream**: Module 0 gates on producer-`active` only, and `parties-compliance` tightens `ActivateProducer` (DEC-071 — KYC fields nullable, added additively) so a Producer cannot reach `active` un-KYC'd — with **no change to Module 0's gate** when it lands. The KYC bit never enters the cross-module event contract.
- **Cross-module retirement-blocking references** → the Phase-3 referencer changes (Module A / B / S). BR-Lifecycle-5's downstream-reference leg (active Allocations, issued vouchers, in-flight orders, SKUs on live Offers) is enforced here over **within-catalog** references only (a Product Reference ← `active` Sellable/Composite SKU; a Case Configuration ← `active` Sellable SKU); those cross-module referencers do not exist yet, and the changes that introduce them extend the guard.

## Parties (Party Registry)

The system-of-record of **Module K** — who NewCo deals with: the natural persons it sells to (Customer), the counterparts it procures from (Supplier), the wine Producers and their commercial agreements, and each Customer's Club memberships (Profile). Built spine-first like the Catalog: entities are born in their birth state. The **supply-side** lifecycle transitions (Producer, ProducerAgreement, Club) are implemented by `parties-producer-lifecycle`; the **demand-side** lifecycle (Customer / Account / Profile transitions, the Originating-Club lock) remains deferred. Every reference in this spine is **within Module K** (Account→Customer, Club→Producer, ProducerAgreement→Producer/Club, Profile→Customer/Club, Customer→originating Club) — ordinary Eloquent relations and DB foreign keys, never a cross-module reference. **Club** and its **Originating-Club** field are defined under *Commerce & Membership*.

**Party / party-type marker**:
The registry abstraction for an entity NewCo has a commercial relationship with. At launch there are two concrete subtypes, each a distinct `parties_*` table — **Customer** and **Supplier** — stamped with an **immutable party-type marker** (`party_type`, one of `customer` / `supplier` / `third_party_owner`). The marker lives **on the subtype** ("marker-on-subtype"; ADR `2026-06-15-party-type-marker-on-subtype`), so BR-K-Identity-5 ("a Customer can never become a Supplier") holds **by construction** — distinct strongly-typed entities, not rows discriminated in one shared table. The unified `parties_parties` registry, the `third_party_owner` subtype and any marker overlap are **deferred** (none exercised at launch). A **Producer is not a Party** (§4.4) and carries no marker.
_Avoid_: discriminator column on one shared table, a Customer↔Supplier conversion, Producer-as-Party

**Customer**:
A natural person NewCo sells to — the identity-and-eligibility record, **not** a login (the Authentication principal references it by id; the party stays authoritative). Holds the personal data (`email` — globally unique — name, phone, date of birth), currency/locale preferences, and the Originating-Club seam; born `pending` with the immutable marker `customer`. Creating a Customer co-provisions exactly one Account and records a **strictly PII-free** `CustomerCreated`.
_Avoid_: user, login principal, Account (the billing container, a distinct record)

**Account**:
The Customer's **billing container** — a 1:1 record co-provisioned with the Customer in one transaction, born `active` / `personal` (the sole `AccountType` at launch). It is **not a money ledger**: it carries no balance, no credit and no payment-provider column (monetary credit is the separate **Club Credit** entity; §4.7/DEC-014). It is **event-silent** — its creation records no `AccountCreated`.
_Avoid_: wallet, ledger, balance, the Authentication principal (a login record)

**Supplier**:
A **minimal Party subtype** — a commercial counterpart NewCo procures from, kept deliberately thin at launch: `legal_name` + the immutable marker `supplier` + timestamps, with **no status column, no version and no creation event** (the PRD names none). The Supplier↔Producer link is Module D's `SupplierProducerLink`, not modelled here; creating a Supplier never auto-creates a Producer (and vice-versa).
_Avoid_: Producer (a distinct entity with a distinct marker), a lifecycle/status on the Supplier

**Producer**:
The wine producer — root of the supply-side registry and the parent of Clubs and ProducerAgreements; referenced across modules **by id** (Catalog's Product Master, Module D). Born `draft`; carries a translatable `description` (English fallback). Its lifecycle is `draft → active → retired` (§4.4); **retiring** a Producer **cascades** — every `active` Club it operates is **sunset** as part of the offboarding (§10.2), while the per-Profile leg of that cascade is demand-side (deferred). A Producer is **not a Party** (§4.4); creating one never auto-creates a Supplier (BR-K-Producer-3).
_Avoid_: Supplier (distinct entity/marker), winery (display copy, not the entity name)

**ProducerAgreement**:
The NewCo↔Producer commercial agreement — settlement cadence and optional term dates, optionally narrowed to a single Club; born `draft`. Its lifecycle is `draft → active → superseded | terminated` (§4.6.1): activating a replacement in the same scope **supersedes** the prior `active` agreement (`active → superseded`), pairing old + new in audit history (BR-K-Agreement-3); **terminating** (`active → terminated`) is a permanent end that does **not** cascade to Producer state (§4.6.1). A NewCo net-new entity (DEC-070). The "single active agreement per scope" rule is an **activation-time** rule and is **not** enforced at creation — drafts are created freely.
_Avoid_: contract (overloaded), a Supplier agreement (a different counterpart)

**Agreement scope**:
The partition within which BR-K-Agreement-1 holds — **at most one `active` ProducerAgreement per scope** (§14.6 BR-K-Agreement-1). Scope is the `(producer_id, club_id)` tuple, where a `NULL` `club_id` denotes the **distinct Producer-wide scope**: a Producer-wide agreement and a Club-narrowed one occupy different scopes and may both be `active` at once, while the single-active rule binds two agreements only *within* the same scope (§4.6.1). Resolved by founder decision (2026-06-15).
_Avoid_: a single per-Producer active-agreement uniqueness (Producer-wide and per-Club are distinct scopes; a `NULL` `club_id` is its own scope, not a wildcard)

**Profile**:
A Customer's **membership in one Club** — the Netflix-style model where one Customer holds **many Profiles**, one per Club, with **no separate Membership entity** (the Profile *is* the membership). Born `applied`; carries a `state` (the nine-state §4.2.1 machine, transitions deferred), nullable `tier` / `role`, and an `invited_by_customer_id` referral seam (a non-FK id). Uniqueness is **"one non-terminal Profile per (Customer, Club)"** — enforced by a partial unique index over non-terminal states, so a `rejected` / `cancelled` / `inactive` Profile never blocks a fresh one.
_Avoid_: Membership (no such entity — the Profile is it), subscription, the Account (a billing container, not a membership)

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

## Commerce & Membership

**Club**:
A Producer-operated membership community — a **Module K (Parties)** registry entity (`parties_clubs`), born `active`, owned by exactly one Producer through a **required and immutable** link. Members pay an annual `fee` (a `Money` — integer minor units + ISO 4217, never a float) equal to that year's Hero Package price. A `registration_flow_type` classifier (`open_registration` / `application_with_approval` / `invitation_only` / `link_onboarding`) governs how members join; `generates_credit` and `invite_only` are launch flags. Its lifecycle is `active → sunset → closed` (§4.3): **sunset** (`active → sunset`) blocks new memberships and new offers while preserving existing Profiles, and is also the per-Club step of the Producer-retirement cascade (§10.2); **close** (`sunset → closed`) is terminal (supply-side transitions implemented by `parties-producer-lifecycle`).
_Avoid_: subscription, tier

**Hero Package**:
The producer's curated annual package whose price sets the club's membership fee for that year.

**Originating Club (OC)**:
The Club through which a Customer first joined. It accrues a 5% share on that customer's Discovery purchases (accrued at INV1). Modelled as a **nullable field** on the Customer (`originating_club_id`), **born NULL** with **no mutation surface** in the registry spine; it is set once — **locked at the first membership approval** (the one-shot `OriginatingClubLocked`, §6.1) — which is **deferred** to `parties-membership-lifecycle`. Capturing the seam at creation preserves it (it is unreconstructable later, §6).
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
A monetary credit entity attached to a membership (Module K). Entirely distinct from Voucher.
_Avoid_: voucher, balance

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
A unified, trigger-agnostic restriction on a customer account (6 types: chargeback, storage non-payment, KYC, sanctions, …). Never auto-lifted.
_Avoid_: ban, block, suspension (suspension is a distinct escalation state)

**KYC / Sanctions screening**:
Four-state customer verification flows checked at onboarding and at every transaction-initiation surface.

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

## Operations & Integrations

**Vinlock**:
The single French warehouse operator at launch; applies NFC tags at receipt.

**Logilize**:
The WMS (separate tenant). Module C owns its 4 fulfilment streams; Module B owns its 5 inventory-state streams (spec R3).

**White-glove**:
The manual fallback fulfilment path for complex destinations (D3).

**Shipping Order**:
Module C's 5-state outbound FSM with `compliance_hold` / `manual_review` flags; bottle selection is late-binding (FIFO + manual tiebreak at launch).
