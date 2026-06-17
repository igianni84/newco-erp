# NewCo ERP — Module 0 PRD (PIM) — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP scope of Module 0)
- **Date**: 2026-06-07
- **Status**: **RATIFIED by Paolo 2026-06-07** (Phase D re-baseline). Held in `mvp/`; **nothing is promoted to `handoff/` until Phase E** (the single coherent handoff).
- **Owner**: Paolo (decides). Claude recommends.
- **Testable companion**: [`../03-acceptance/Module_0_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_0_Acceptance_v0.3-MVP.md) — the MVP-scoped acceptance criteria (re-cut from the PAOLO-VALIDATED v0.1 per the cut-sheet §5 delta).
- **Predecessors / inputs** (the canonical record governs where this PRD is terse):
  - [`../../reference/v1.1/01-prd/Module_0_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_0_PRD_v0.2.md) — the **frozen v1.1 predecessor** (RELEASED 2026-05-09). This v0.3-MVP carries its scope **in full** (KEEP-in-full) and generalises it; `greenfield/` is never edited (plan R4).
  - [`../01-triage/Module_0_CutSheet_v0.1.md`](../01-triage/Module_0_CutSheet_v0.1.md) — the **ratified cut-sheet** (Paolo 2026-06-07). §2 feature inventory = the scope; §3 generalisation workstream = the rewrite instructions; §5 = the acceptance delta; §6 = the three ratified Qs.
  - [`../../reference/v1.1/11-category-expansion/Module_0_Generalisation_Change_Brief_v0.1.md`](../../reference/v1.1/11-category-expansion/Module_0_Generalisation_Change_Brief_v0.1.md) — the **generalisation** (Wine → generic Product spine). Its §3 is executed here; its §6 checklist is this PRD's generalisation acceptance.
  - [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) — the **coherence gate** (RATIFIED 2026-06-07). Item A (the naming cascade) pins this PRD as the **source of truth** for the cross-module names; §18 below carries the canonical table.
  - [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (method, P1/P2, the floor) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (locked dials — D2 locales KEEP, D9 LWIN+bulk KEEP).
- **Methodology** (carried from v1.1; unchanged):
  - **DEC-072** — no accounting-policy positions. The prose may use accounting-domain terms descriptively; Module E records financial events and Xero decides GL treatment. PIM holds no commercial state at all.
  - **DEC-073** — product-spec layer only (entity concepts, business attributes, lifecycle states, business-meaningful enum values, domain-event names + business signals, module boundaries, invariants). Physical representation (typed tables vs JSON attribute bag vs EAV; column types; FK naming; indexing) is the dev team's call and is out of scope.
  - **DEC-074** — self-contained delivery document. Every entity is reintroduced in full NewCo language; a tech reader who has not read v1.1 can take this into the dev phase. The v1.1 + generalisation trace is preserved at §19.
  - **MVP principles (plan §4.1):** **P1 — defer without burning bridges** (every deferred item names the seam that makes the post-launch build additive, and points to the roadmap); **P2 — admin-first, self-serve-later** (producer/back-office writes are operator-driven via the Admin Panel; consumer storefront exempt). *Module 0 has ~0 net-new deferrals and no producer/consumer write surface — P1/P2 touch it only through the carried-forward already-deferred set (§17) and the Admin-Panel-driven catalog operations (§2).*

---

## §0 MVP scope & the generalisation at a glance

**Verdict: Module 0 is KEPT IN FULL + GENERALISED. ~0 net-new deferrals.** Module 0 is the foundational, identity-only catalog spine — the single authoritative source of truth for product *identity*. The launch business loop cannot run without the catalog (producer onboarded → product catalogued → allocation published → offer published → customer buys → bottle received → shipped → settled). A Lean MVP does **not** gut foundational modules: the calendar savings come downstream (Module S commerce, Module B/C inventory & fulfilment, Module E finance), not here. Module 0 was already lean in v1.1 (bottled-wine-only; Liquid Product, Service/Experience SKUs, and a separate translation registry all already deferred) — so there is essentially nothing to defer that v1.1 has not already deferred.

**The one substantive change is the generalisation** (Wine → generic Product spine). It *adds* a small, deliberate amount of structural work rather than cutting, because it is near-free **now** (while Module 0 is still design-on-paper, before any production data, integrations, or on-chain records key to it) and it is the enabler for NewCo's **immediate** post-launch objective: category expansion. **It is non-behavioural for wine** — every rule, lifecycle transition, validation, dedup check, enrichment flow, and event payload behaves identically to v1.1 for the `WINE` product type (§16). The change is purely structural naming + attribute placement.

**This PRD lands the source-of-truth names for the cross-module naming cascade** (Phase C item A): `Bottle Reference → Product Reference (PR)`; `Wine Master/Variant → Product Master/Variant`; the `Wine*/BottleReference* → Product*` event family. Module 0 v0.3-MVP is written **first** in Phase D precisely so the canonical names exist before every sibling PRD applies them (cascade order 0 → A/D → S → B/C → E → Architecture). §18 carries the canonical table + the carve-outs (Module E's category-neutral names; Modules B/C physical-unit / wine-display names).

**The three ratified scope confirmations (cut-sheet §6, Paolo 2026-06-07):**
- **Q1 — Composite SKU KEPT.** Single-producer bundles/verticals are sellable at launch; Composite SKU is retained at PIM as the **forward-compat seam (P1)** for the deferred multi-producer Discovery composites (D7). PIM stays producer-agnostic; surface-asymmetric admissibility (single-producer at launch) is Module S's validation. (§3.8.)
- **Q2 — lighter approval acceptable by configuration, no spec change.** The 3-step Creator → Reviewer → Approver workflow is the spec; the **role-count is admin-configurable**, so the small launch catalog team may run a lighter approval (e.g. 2-step Creator → Approver) by configuration. The separation-of-duties floor (no self-approval; distinct actors on the steps that run; audited) is preserved. (§4.2.)
- **Q3 — manual-first enrichment baseline; LWIN pluggable.** External catalog enrichment is a **pluggable adapter selected by Product Type**; the **manual fallback path is the launch baseline**, so catalog enrichment is **off the launch critical path**. The `WINE` LWIN (Liv-ex) adapter drops in when the vendor lands — at launch if it is ready, otherwise post-launch, with **no rework**. (§5.)

**The floor pieces Module 0 holds (all KEPT, whole) — verified in composition by Phase C §6:**
- **Producer activation / KYC gate** — a Product Master cannot activate unless its linked Producer (Module K) is `active` and KYC-cleared (`verified` or `not_required`) (§5.4, §13.4). This is the first link of the KYC/sanctions/Hold compliance floor.
- **Layer-1 breakability whitelist** (§7) — the cataloging-level input the no-overselling / breakability contract reads (Module A Layer 2, Module S Layer 3, Module B/C at fulfilment).
- **Version immutability + audit trail** (§4.8, §13.3) — the audit/retention floor.
- **Parent-before-child event ordering** (§14.3) — the reliability guarantee the cross-module event contract depends on.

**Deferred set:** only v1.1's already-deferred items (Service/Experience SKU; Liquid Product/Liquid SKU/BottlingResolution; separate translation registry; locale expansion beyond six; producer-content versioning; bulk-import auto-replay queue), carried **verbatim** to the post-launch roadmap with their existing re-introduction hooks (§17). **No new Module 0 deferrals are taken.**

---

## §1 Module Purpose

Module 0 is NewCo's **Product Information Management (PIM)** — the single authoritative source of truth for product *identity*. PIM owns the catalog of every product NewCo can talk about commercially: who made it, which variant/release, in which atomic-unit size, in what packaging form, and (via Sellable SKUs) which commercial units the marketplace can publish around it.

**At NewCo launch the catalog holds exactly one Product Type — `WINE` — and PIM models bottled wine only.** The generalisation (this PRD's one substantive change, §3) makes the spine *category-neutral* so a second product type slots in post-launch additively, without reshaping the core entities or the cross-module event contract — but **no non-wine type is defined at launch** (§16). The pre-bottling-wine entity that exists in Crurated v17's PIM (Liquid Product, with a Liquid SKU subtype and a BottlingResolution boundary into the allocation module) is *not* introduced (deferred, §17). Free experiences are booked operationally without a catalog representation; paid Service / Experience SKU subtypes are deferred (§17). NewCo's launch catalog is, in entity terms: **Product Master, Product Variant, Product Reference, Format, Case Configuration**, and the two Sellable SKU shapes (**Intrinsic** and **Composite**) — with the `WINE` attribute set carrying the wine-specific identity and descriptive fields (§3.9).

Identity is the only thing PIM owns. Pricing, availability, inventory, fulfilment, procurement, financial-event recording, party identity (Producer / Supplier / Customer / Member), and serialization / NFT mechanics are owned by other modules. PIM publishes versioned domain events when product-identity state changes; downstream modules consume those events to stay synchronized.

PIM sits upstream of every commerce-facing module (Module S Sales, Module A Allocations, Module B Bottle / Stock, Module C Fulfilment, Module D Procurement, Module E Financial Events) and downstream of one party module (Module K, which owns the Producer entity that Product Master links to). PIM is identity-only by design, which keeps the catalog clean of commercial state and lets multiple commercial-state shapes (allocations under different sourcing models, offers across multiple commercial surfaces) reference one underlying product identity without duplication.

---

## §2 Personas

Three roles operate inside PIM, plus a generic upstream / downstream linkage:

- **Catalog Operator (Creator).** Creates and edits PIM entities — Product Masters, Product Variants, Product References, Sellable SKUs, and the standalone reference entities Format and Case Configuration. Runs the creation/enrichment workflow (§5) on Product Master / Product Variant (the `WINE` enrichment adapter where available; the manual baseline otherwise); runs bulk imports during legacy migration and producer onboarding (§6); maintains enrichment metadata (critic scores, tasting notes, market-data snapshots).
- **Catalog Reviewer.** Verifies product-data quality before approval — catches errors, duplicates, missing data, inconsistencies before an entity reaches `active`. Cannot be the same person as the Creator on a given entity.
- **Catalog Lead / Approver.** Final approval authority on activations, retirements, re-activations, and on new reference data (Format and Case Configuration entries that expand the reference set). Cannot be the same person as the Creator or the Reviewer on a given entity.

PIM consumers (downstream module owners — the Module S Pricing Operator, the Module A Allocation Operator, the Module B Operations team, the Module K Producer Onboarding team) interact with PIM through events and reads, not through PIM-internal workflows.

**Operator surface (P2).** All PIM workflows are operator-driven through the **Admin Panel** at launch — there is no producer/consumer self-serve write into PIM. This is unchanged from v1.1 (PIM was always an operator-run catalog) and is the natural instance of P2 for this module; the consolidated operator-surface inventory lives in the 9th Admin-Panel PRD (it references this PRD's catalog operations rather than re-specifying them).

**Approval role-count is admin-configurable (Q2).** The 3-step Creator → Reviewer → Approver workflow (§4.2) is the specified governance. The **number of distinct roles is an operational configuration** (`feedback_prd_rr_approval`): a small launch catalog team may run a lighter approval (e.g. a 2-step Creator → Approver) **by configuration, with no spec change**. The separation-of-duties floor is preserved either way — **self-approval is never allowed**, every step that runs is performed by a distinct actor, and each step is recorded in the audit trail.

---

## §3 Entity Model

PIM organises product identity in a strict hierarchy plus two reference entities and two Sellable SKU shapes, all classified by a first-class **Product Type**. Every PIM entity follows the same lifecycle (§4) and the same approval workflow. **The core entities carry only category-neutral identity fields; all type-specific descriptive/identity attributes belong to the Product Type's attribute set** (§3.9). At launch the sole Product Type is `WINE`, and the `WINE` attribute set carries everything wine-specific (appellation, vintage, varietal, scores, tasting notes, …).

> **Naming (generalisation + Phase C item A).** The structural/contract names are category-neutral: **Product Master / Product Variant / Product Reference (PR)**. **"Wine Master / Wine Variant / Bottle Reference / BR" are retained only as wine-facing display aliases** — wine-facing UI labels and wine documentation may still say "wine," "bottle," "vintage." The separation is deliberate: neutral structure, category-flavoured presentation (guardrail, §16).

### §3.1 Product Type (the first-class classifier)

**Product Type** is the category classifier carried by Product Master. **At launch the only value is `WINE`.** Product Type is the switch that selects, per product:

- the **attribute set** that applies (§3.9);
- the **variant-defining dimension(s)** (§3.3 / §3.9 — `WINE` = vintage);
- the **enrichment adapter** used at creation (§5 — `WINE` = the LWIN/Liv-ex adapter);
- the admissible **Format** vocabulary (`WINE` = bottle sizes);
- the **identity-uniqueness key** (§13.1 — `WINE` = producer + product name + appellation).

Product Type is a **classifier, not a hierarchy level**: one Product Master has exactly one Product Type. **Adding a future Product Type must require no change to the core entities or to consuming modules** — it slots in by adding its own attribute set, variant axis, enrichment adapter (or the manual baseline), Format vocabulary, and identity key. (No such type is defined at launch — §16.)

### §3.2 Product Master

A **Product Master** (wine display alias: *Wine Master*) is the highest-level identity for a specific product from a specific producer, *independent of variant/release*. Example (`WINE`): "Sassicaia (Tenuta San Guido)". Product Master is the parent of every variant/release of that product.

Each Product Master:
- Carries the **Product Type** (§3.1; `WINE` at launch).
- Carries category-neutral identity fields — product name, producer link, lifecycle state, audit/version fields — plus the Product Type's attribute set (for `WINE`: appellation/region and the producer-supplied descriptive content — the "winery story" surfaced on the Bottle Page per §9).
- Holds a **link to a Producer entity in Module K's Producer Registry**. The Producer entity itself (legal name, region, KYC status, lifecycle state, Discovery-only marker) is owned by Module K; PIM stores only the link. The link is a **hard activation prerequisite**: a Product Master cannot transition to `active` unless the linked Producer is itself `active` and KYC-cleared (`verified` or `not_required`) in Module K (§5.4).
- Carries translatable descriptive content per §8 (master-level prose: producer story, region context, winery narrative — readable in any of the six launch locales).

A new producer-onboarded product creates a Product Master; subsequent variants/releases create new Product Variants under the existing Product Master rather than new Masters.

### §3.3 Product Variant

A **Product Variant** (wine display alias: *Wine Variant*) is a specific variant/release of a Product Master. Example (`WINE`): "Sassicaia 2018". Product Variant is the level at which variant-specific identity attributes attach.

Each Product Variant:
- Belongs to exactly one Product Master.
- Carries the **variant identifier in a type-neutral way** on the core entity; the *value* and meaning of the variant axis is type-driven and lives in the Product Type's attribute set. For `WINE` the variant axis is **vintage** — vintage year (or the non-vintage marker) — together with vintage-level enrichment (tasting notes, critic scores, vintage-specific producer commentary), all in the `WINE` attribute set. **Behaviour for wine is unchanged** — the wine attribute set absolutely contains a `vintage_year` field; the constraint is only that the *core* entity does not hard-name a wine-only dimension.
- Carries the **Layer-1 input to the layered breakability model** (§7): an optional whitelist of Case Configurations admissible per Format. The whitelist catalogs *physical / commercial possibility* — "this product can be packaged in these forms"; whether a given case actually breaks at sale is decided by the producer-time and offer-time layers (§7).
- Carries translatable descriptive content per §8.

### §3.4 Product Reference (PR)

A **Product Reference (PR)** (wine display alias: *Bottle Reference / BR*) is the **atomic product identity** = Product Variant + Format. Two dimensions only. Example (`WINE`): "Sassicaia 2018 in 0.75L".

**The PR is the universal product key across all modules** — and the highest-impact rename in the generalisation. The two-dimension invariant is fundamental:

- **Case Configuration is never part of PR identity.** One Sassicaia 2018 in 0.75L is the *same product* whether sold loose, in a six-bottle OWC, or in a twelve-bottle carton. The packaging form is a downstream commercial / operational decision (which Sellable SKU references which Case Configuration); it does not change identity.
- **The PR is the atomic "what is this product?" answer.** Every commercial layer above PIM keys at the PR level: Allocations (Module A) carve quantities of a PR; Offers (Module S) reference PRs (directly via an Intrinsic SKU, or as constituents of a Composite SKU); Stock positions (Module B) track quantities of a PR; Fulfilment (Module C) holds and ships against a PR; Procurement (Module D) places purchase orders for a PR.
- **Allocation lineage keys at the PR.** Tracing a unit from sourcing to fulfilment runs through the PR — the PR is the spine of NewCo's product traceability.
- **PR identity is immutable once referenced.** Once any allocation, voucher, stock position, or commercial offer exists against a PR, the Product Variant + Format composition cannot be changed. (Correcting an erroneously-published PR follows the retirement → new-PR path with downstream coordination.)

### §3.5 Format

**Format** is the physical size/measure of the atomic unit — for `WINE`, bottle size: 0.75L, 1.5L (Magnum), 3.0L (Jeroboam / Double Magnum), 6.0L (Imperial / Methuselah), and so on. (A future Product Type would carry its own Format vocabulary, e.g. tin size.) Format is a standalone PIM reference entity, runtime-configurable, **name kept** in the generalisation.

Format is administered by the Catalog Operator and approved through the standard approval workflow. Adding a new Format (a producer ships a new size NewCo has not previously sold) is a normal lifecycle event — propose, review, approve, activate — and the new Format becomes available for new Product References as soon as it is `active`. A PR cannot be activated unless its referenced Format is itself `active` (the parent-before-child cascade applied at the reference-data level, §4).

### §3.6 Case Configuration

**Case Configuration** is the packaging form a product ships in — six-bottle Original Wood Case (OWC6), twelve-bottle carton (CARTON12), single-bottle loose, etc. It is a standalone PIM reference entity, distinct from Format, **name kept** (already packaging-agnostic by definition; an optional future neutralisation to "Packaging Configuration" is **not** done now, to keep churn low).

Case Configuration carries packaging-form attributes only: how many units per case, packaging type, physical form. **Case Configuration carries no breakability flag.** Whether a given case can be split apart at sale is *not* a property of the Case Configuration — it is decided at sale time by the layered breakability rule (§7), which reads the Product Variant's possible-case-configurations whitelist (Layer 1, PIM), the Allocation's per-case-configuration producer-breakability declaration (Layer 2, Module A), and the Offer's commercial-breakability decision (Layer 3, Module S).

Case Configuration is administered by the Catalog Operator and approved through the standard workflow. A Sellable SKU (Intrinsic) cannot be activated unless its referenced Case Configuration is itself `active` (alongside the PR's underlying Product Variant and Format).

### §3.7 Sellable SKU — Intrinsic

A **Sellable SKU (Intrinsic)** is the **commercial unit** that gets priced and that an Offer references. An Intrinsic SKU = one Product Reference + one Case Configuration + commercial attributes carried at the SKU level (commercial name, marketing copy, etc., distinct from the identity attributes which live on Product Master / Product Variant / PR).

Intrinsic SKUs are NewCo's most common commercial unit — "a six-bottle OWC of Sassicaia 2018 in 0.75L", "a single loose bottle of Vega Sicilia Único 2010 in 1.5L". An Offer publishing this SKU at a price is what a member or Discovery customer ultimately buys.

Activation prerequisites (the parent-before-child cascade at the SKU-activation boundary): the referenced PR must be `active` (which by cascade means the Product Variant, the Product Master, and the Format are all `active` and the linked Producer is `active` and KYC-cleared), and the referenced Case Configuration must be `active`. Stating these explicitly at the SKU boundary lets downstream modules consume `SellableSKUActivated` without re-deriving each upstream gate. Intrinsic SKU is the only SKU shape that references Case Configuration.

### §3.8 Composite SKU — **KEPT (Q1)**

A **Composite SKU** is a curated bundle of multiple constituent Product References. Examples: a vertical (multiple vintages of one product), a producer mixed-case (multiple products from one producer), a multi-producer Discovery composite ("Tuscany Discovery Case").

**Launch scope (cut-sheet Q1):** Composite SKU is **KEPT** — it is cheap at PIM (registration + lifecycle only), it enables **single-producer bundles/verticals at launch**, and it is the **forward-compat seam (P1) for the deferred multi-producer Discovery composites (D7)**. The heavy atomic multi-allocation-bind logic lives in Modules A/B/S; D7 defers the *multi-producer* composite construct there, **not** the PIM Composite SKU concept. PIM carries the bundle structure; Module S owns surface-asymmetric admissibility (at launch: single-producer; multi-producer restores additively with D7).

Each Composite SKU:
- References N constituent Product References (N ≥ 2; many-to-many — one PR can be a constituent across multiple Composite SKUs).
- Stays **producer-agnostic at PIM**: it may carry constituent PRs from one or many producers. PIM does not validate producer composition. Whether a Composite SKU is *admissible on a given commercial surface* is a Module S Offer-publication validation (below + §15).
- Carries **no per-constituent commercial state at PIM.** The per-constituent cost mechanic relevant to multi-producer Discovery composites — each constituent settles to its own producer at the negotiated cost on the constituent's Allocation — is owned by Module A (Allocation level) and resolved at Module S Offer-publication time. PIM carries no "allocation binding" attribute on Composite SKU constituents.
- Carries **no club / Hero-Package / promotional flags.** The Hero Package is a Module S Offer-level **designation** (a role attached to an Offer that backs a club's annual membership purchase), not a PIM structural type; any standard PIM artefact (an Intrinsic SKU, a Composite SKU, …) can back it. Crurated v17's `is_club_package` flag on Composite SKU is **not carried** into NewCo PIM.

**Composite SKU atomicity at sale.** When an Offer backed by a Composite SKU sells, the system issues one voucher per constituent unit-equivalent. Allocation reservations, stock holds, and fulfilment must succeed for *every* constituent; partial-bundle issuance is not allowed. Atomicity is a PIM-level invariant on the Composite SKU's lifecycle / commercial-commitment contract; the actual reservation and hold logic runs in Module A and Module B.

**Composite SKU immutability after commercial commitment.** Once a Composite SKU is referenced by an `active` Offer, its constituent composition becomes immutable; the path to change it is to retire the existing Composite SKU and register a new one.

**Surface-asymmetric admissibility (Module S concern).** NewCo's commercial surfaces apply different rules to Composite SKU admissibility — **Club Offers** must reference a single-producer constituent set (DEC-019); **Discovery Offers** admit multi-producer Composite SKUs (DEC-061). **At launch the multi-producer Discovery path is deferred (D7) → all launch composites are single-producer**; the seam is that PIM's producer-agnostic Composite SKU already supports N constituents from any producers, so the multi-producer surface restores additively. **PIM is silent on the admissibility rule**; Module S owns the validation predicate and the surface-by-surface decision.

### §3.9 Attribute model — neutral core + per-type attribute set

The generalisation's attribute-placement principle (brief §3.3/§3.4/§3.6), stated once here:

- **The core Product entities (Master / Variant / Reference) carry only category-neutral identity fields:** producer link, product name, lifecycle state, the variant-identifier handle, the Format link, audit/version fields.
- **All type-specific descriptive and identity attributes belong to the Product Type's attribute set.** For `WINE`: appellation / region, vintage year (or NV marker), varietal, critic scores, tasting notes, drinking window, etc. These are modelled as belonging to the `WINE` attribute set, **not** as columns on the core entity.
- **A future Product Type slots in by adding its own attribute set**, without reshaping the core entities or the cross-module event contract.
- **Identity uniqueness is a type-defined key** (§13.1): `WINE` = producer + product name + appellation (unchanged).
- **Intent guard:** the goal is **category-readiness, not maximal configurability.** Keep wine concrete and well-modelled (a neutral core + additive per-type attribute sets is sufficient). **Do not** build an infinitely dynamic EAV / rules engine (§16).
- **Out of scope (DEC-073):** the *physical* representation (typed tables, JSON attribute bag, EAV, hybrid) is the dev team's decision. This PRD constrains only that the core stays neutral and per-type attribute sets are additive.

---

## §4 Lifecycle Governance

Every PIM entity — Product Master, Product Variant, Product Reference, Sellable SKU (Intrinsic and Composite), Format, Case Configuration — follows the **same 4-state lifecycle** and the **same approval workflow**. Lifecycle uniformity is a load-bearing property of PIM: every consumer module can rely on the same activation / retirement contract regardless of which entity it reads. **The lifecycle and governance are entity-agnostic and category-neutral — unchanged by the generalisation** (brief §3.8).

### §4.1 The 4-state lifecycle

`draft → reviewed → active → retired`.

- **`draft`.** Initial creation. Data may be incomplete; required fields can be filled over multiple sessions. No commercial commitment; downstream modules ignore `draft` entities.
- **`reviewed`.** All required fields populated; the Catalog Reviewer has confirmed data quality and submitted for approval. No commercial commitment yet.
- **`active`.** The Catalog Lead / Approver has approved the entity. Available for downstream consumption — Sellable SKUs can be priced, Allocations created against a PR, Offers published. PIM emits the `*Activated` domain event on this transition.
- **`retired`.** No longer available for *new* downstream references. Existing references (active allocations, issued vouchers, in-flight orders, historical SKUs on past Offers) remain valid for their current lifecycle. PIM emits the `*Retired` domain event on this transition.

Re-activation from `retired` to `active` is allowed, follows the same approval workflow, and (for Product Masters specifically) re-checks the Producer activation gate (§5.4) at the moment of re-activation.

### §4.2 The approval workflow (3-step spec; role-count admin-configurable — Q2)

Every state transition that opens or closes downstream commercial impact (`reviewed → active`, `active → retired`, `retired → active`) passes a **Creator → Reviewer → Approver** workflow:

- The **Creator** is the Catalog Operator who originated the entity (or who is requesting the transition).
- The **Reviewer** verifies data quality and submits the entity for approval.
- The **Approver** issues final approval.

**The roles that run must be distinct people — self-approval is never allowed.** The review and approval steps are recorded in the audit trail with actor identity, timestamp, and decision. The `draft → reviewed` transition is captured by the audit trail but does **not** emit a distinct domain event — review is an internal-to-PIM checkpoint, not a cross-module signal.

**Role-count configuration (Q2, ratified 2026-06-07).** The **number of distinct approval roles is an operational configuration** (`feedback_prd_rr_approval`), **not a spec change**: the full 3-step Creator → Reviewer → Approver is the default; a small launch catalog team may configure a lighter **2-step (Creator → Approver)** workflow. **The separation-of-duties floor holds at any configured depth** — no self-approval; each configured step performed by a distinct actor; every step audited. (Same decision as Module K Q3.)

### §4.3 Rejection handling

If a Reviewer or Approver rejects an entity, it stays in `reviewed` with a visible rejection flag and the reviewer's / approver's notes. The Creator edits the entity **in place** — there is no revert-to-draft step — and re-submits; the approval flow restarts from the review step. Every rejection round (notes, corrections, actor identities, timestamps) is preserved in the audit trail as part of the entity's permanent record.

### §4.4 Lifecycle cascading — activation

A child entity cannot be set to `active` while its parent is not `active`:

- A **Product Master** cannot be activated unless its linked Producer (Module K) is `active` and KYC-cleared (`verified` or `not_required`).
- A **Product Variant** cannot be activated unless its parent Product Master is `active`.
- A **Product Reference** cannot be activated unless its parent Product Variant is `active` and its referenced Format is `active`.
- A **Sellable SKU (Intrinsic)** cannot be activated unless its referenced PR is `active` and its referenced Case Configuration is `active`.
- A **Composite SKU** cannot be activated unless every constituent PR is `active`.

Standalone reference entities (Format, Case Configuration) have no parent in the PIM hierarchy; they activate independently subject to the standard approval. The activation cascade is a **hard gate**: an attempt to activate a child whose parent is not `active` is rejected at the workflow level.

### §4.5 Lifecycle cascading — retirement

When a parent entity is retired, existing active child entities and their downstream references stay valid for their current lifecycle (existing allocations, issued vouchers, historical SKUs on past Offers are not invalidated retroactively). What retirement prevents: no new child can be activated under a retired parent; no new commercial activity can be opened against the retired entity (no new allocations, offers, Sellable SKU versions, or purchase orders) — but in-flight commercial state runs to natural completion. This preserves existing customer commitments while preventing new commitment on retired products.

### §4.6 Retirement blocked by active references

A PIM entity cannot itself be retired while it has *active downstream references that have not yet completed* (open Offers actively selling, allocations still serving orders, vouchers still pending fulfilment). The system surfaces which references are still active; retirement proceeds only after those references close in the natural commercial path. This is the symmetric counterpart of the activation cascade.

### §4.7 Operator-driven cascade retirement

When an operator retires multiple linked entities in one workflow (e.g. a Product Master plus all its Variants and PRs because a producer relationship ended), the cascade emits retirement events in **parent-before-child** order — the Product Master's `ProductMasterRetired` first, then each `ProductVariantRetired`, then each `ProductReferenceRetired`, then any Sellable SKU under those PRs. This matches the activation-cascade ordering and gives consumers a predictable signal sequence. For non-cascading single-entity retirements (§4.6), no ordering constraint applies.

### §4.8 Version immutability and audit

Changes to identity-bearing entities (Product Master, Product Variant, Composite SKU constituent composition, and any other versioned entity) create new versions; old versions are deprecated, **never deleted**. Full before-and-after state for every change is recorded in the audit trail — the system of record for "who changed what when" across the PIM lifecycle. **(Audit/retention floor.)**

---

## §5 Creation & Enrichment Workflow (pluggable adapter; manual-first at launch — Q3)

NewCo PIM creates products through a **pluggable enrichment adapter selected by Product Type**, with a **manual fallback path that is the type-agnostic baseline.** This generalises v1.1's hard-wired LWIN-first workflow (brief §3.5): LWIN is reframed as the `WINE` adapter; the manual path becomes the default for any type without an adapter.

**Launch posture (Q3, ratified 2026-06-07): manual-first.** The **manual fallback path is the launch baseline**, so catalog enrichment is **off the launch critical path**. The `WINE` LWIN (Liv-ex) adapter is *in progress* and drops into the pluggable interface **when the vendor lands — at launch if ready, otherwise post-launch, with no rework.** Liv-ex is an external dependency; planning manual-first means launch does not block on its onboarding.

### §5.1 The enrichment-adapter path (`WINE` = LWIN / Liv-ex)

When the Product Type's enrichment adapter is available, it is the fast, low-error path. For `WINE` (the LWIN adapter — behaviour unchanged from v1.1 §5):

1. The Catalog Operator enters the LWIN code at Product Master creation (LWIN-7 for the wine identity; LWIN-11 / LWIN-16 for vintage-specific identifiers at Product Variant).
2. PIM queries the Liv-ex API and auto-populates the wine's identity fields (into the `WINE` attribute set) — wine name, appellation, region, producer-name candidate.
3. PIM attempts to match the producer-name candidate to Module K's Producer Registry: **exact match** → the Product Master's Producer link is auto-set; **fuzzy match** → PIM surfaces candidate Producers for the Operator to confirm/reject; **no match** → the Operator must register the Producer in Module K first; the Product Master can be saved as `draft` but cannot transition to `active` until a `producer_id` is bound (the §5.4 Producer activation gate).
4. The Operator reviews the auto-populated data, fills in NewCo-specific descriptive content (winery story, vintage-level commentary), and submits for review.
5. The standard approval workflow runs (§4.2).

The same mechanic operates at Product Variant creation.

### §5.2 The manual baseline path (type-agnostic; the launch default — Q3)

When no adapter is available (the launch default while Liv-ex onboarding completes), or for a product with no LWIN code, or when the Liv-ex API is temporarily unreachable, the Operator enters all identity fields manually. The same review / approval lifecycle runs; PIM's deduplication checks (the type-defined identity key, §13.1) apply on both the adapter path and the manual path. **The manual path preserves all validation and deduplication** — it is a full-fidelity creation path, not a degraded one.

### §5.3 Adapter resilience

If a Product Type's enrichment adapter (for `WINE`, the Liv-ex API) is unavailable during a creation, PIM retries with a configurable retry policy. If retries fail, the Operator is notified and offered the manual baseline path (which preserves all validation and deduplication). When the adapter recovers, no manual intervention is needed — the next adapter-path creation uses it normally.

### §5.4 Producer activation gate **(KYC compliance floor)**

A Product Master cannot transition to `active` unless its linked Producer (Module K) is `active` and its KYC is **cleared** — `verified` or `not_required` (Module K §4.4). This is a **hard gate**: the activation step is rejected at the workflow level if, at the moment of transition, the Producer is not `active` or its KYC is **blocking** (`pending` or `rejected`).

A consequence is **KYC-revocation symmetry**: if a Producer's KYC verification is later revoked, *existing* `active` Product Masters under that Producer remain `active`; only *new* Product Master activations (and new child-entity activations under those Masters) are blocked. Existing customer commitments are preserved; new commercial commitment pauses until KYC is reinstated. *(This is Module 0's link in the end-to-end KYC/sanctions/Hold floor — Phase C §6 chain 2.)*

### §5.5 Captured-data ownership (capture-then-own)

Identity fields captured from an enrichment adapter at creation (for `WINE`, from Liv-ex) become **owned by PIM** once saved. PIM does not auto-sync with future adapter-source changes — the captured snapshot is authoritative within PIM. Editing those fields post-creation is a standard audited change subject to version-immutability (§4.8). This matters because external identity data can drift in ways NewCo does not want to follow (a producer-name spelling change, a regional re-classification); the capture-then-own model gives NewCo deterministic control of its catalog.

---

## §6 Bulk Import

Bulk import exists for two recurring operational needs: **legacy migration** at go-live (loading the Phase-1 producer roster's catalogs en masse) and **new-producer onboarding** (loading a producer's full catalog when they join). It is needed to **seed the launch catalog** and is **KEPT** — already lean in v1.1 (the auto-replay queue is already deferred, §17). Generalised: depth options and validation operate on the Product hierarchy and the type-defined identity key.

### §6.1 Configurable depth

The Operator selects depth per operation: Product Master only; Product Master + Product Variant; Product Master + Product Variant + Product Reference; or the full chain through Sellable SKU. Operators choose depth based on what the source data supplies.

### §6.2 File format and validation

Bulk imports come in via spreadsheet upload (CSV or Excel). PIM validates each record against the same business rules as single-entity creation — the type-defined identity key (§13.1), the Producer-existence check (§5.4), all required fields populated.

### §6.3 Partial-failure handling

Records that fail validation are **skipped and reported** in a detailed error log (source-file row identifier, the field(s) that failed, the reason); remaining valid records proceed. The Operator can correct failed records in the source file and re-submit them in a subsequent batch.

### §6.4 Re-attempt is operator-driven

Re-attempt of failed rows is an **operator-initiated manual path** — there is **no automated retry queue at launch**. When the failure cause is an upstream lifecycle gate (a row referencing a `draft` Producer; a child whose parent is not yet `active`), the Operator re-submits the corrected/unchanged row in a subsequent batch once the upstream entity reaches `active`. PIM does not subscribe to upstream `*Activated` events to auto-replay queued failed rows. *(The auto-replay queue is a carried-forward deferred item — §17; the seam is that re-attempt is already an operator path, so the queue adds additively.)*

### §6.5 Standard governance applies

All bulk-imported entities enter as `draft` and follow the standard approval workflow (§4.2, at the configured role-count). **There is no batch-approval shortcut** — bulk import accelerates *data entry*, not *governance*. Each entity is individually reviewed and approved by the configured distinct actors.

### §6.6 Import summary

After each batch, PIM produces a summary (total records, successfully imported, failed, with a breakdown by failure reason) — the Operator's handle for triaging the next batch.

---

## §7 Layered Breakability **(no-overselling / fulfilment floor — Layer 1)**

"Breakability" is whether a packaged case (a six-bottle OWC, a twelve-bottle carton) can be split apart at sale or must ship as a whole case. NewCo's layered breakability model decides this through three orthogonal layers, each owned by a different module. **PIM owns Layer 1 only**; the effective rule at sale is computed across Layers 2 and 3. The Layer-1 mechanism is **generic** (the whitelist is type-neutral) and **unchanged** by the generalisation (brief §3.8).

### §7.1 Layer 1 — possible case configurations (PIM)

Product Variant carries an optional whitelist of Case Configurations admissible per Format — the **cataloging-level** statement: *"this product, in this format, can in principle be packaged in these forms."* If empty/absent, the default is permissive (every Case Configuration compatible with the Format is allowed). Layer 1 catalogs *physical / commercial possibility*; it does not, by itself, make a case unbreakable — a whitelisted case configuration still defaults to *breakable* unless Layer 2 or Layer 3 declares otherwise.

Reductions to a Product Variant's whitelist on an `active` Variant follow the retirement-cascade semantics (§4.5): existing Sellable SKUs and Allocations referencing a now-excluded Case Configuration remain valid for their current lifecycle; only new Sellable SKU versions and new Allocations against the excluded entry are blocked. Layer-1 changes do not retroactively invalidate Layer-2 declarations on already-active allocations.

### §7.2 Layer 2 — producer breakability (Module A, allocation-time)

The Allocation entity (Module A) carries an optional per-case-configuration producer-breakability declaration. When the producer declares a Case Configuration *non-breakable* for a specific allocation, Layer 2 fires (default breakable). Layer 2 cannot reference a Case Configuration outside the Product Variant's Layer-1 whitelist (Module A enforces this upper-bound check at allocation creation). Layer 2 is set at allocation creation; retroactive post-sale changes are out of scope.

### §7.3 Layer 3 — commercial unbreakable (Module S, offer-time)

The Offer entity (Module S) carries an optional commercial-unbreakable boolean. When the commercial team enforces whole-case sale (presentation premium, vertical integrity), Layer 3 fires. Layer 3 **defaults to the bound Allocation's Layer-2 producer-breakability** for each Case Configuration (operator-without-action publishes matching the producer declaration). An explicit **operator-override path** is admitted for the rare commercial exception (the operator may set Layer 3 to breakable on a Layer-2-unbreakable Allocation via Admin Panel UI with mandatory reason capture — free-text reason + operator ID + timestamp — for audit). Default behaviour remains rejection of a downgrade at the Module S Offer-publication validation; when override is exercised, Layer 3's override value is the binding fulfilment rule. Producer-relationship management of the override is operating-manual scope.

### §7.4 The effective rule

A case is treated as **unbreakable** at sale, voucher issuance, inventory recording, and fulfilment **iff either Layer 2 or Layer 3** declares it unbreakable for the relevant Case Configuration:

`effective_unbreakable = Layer 2 (producer) OR Layer 3 (commercial)`

Either layer saying unbreakable is sufficient; PIM's Layer 1 does not contribute to the effective rule — Layer 1 is a *possibility* whitelist, not a *constraint*. The effective value is resolved per voucher-line (per allocation + Case Configuration + Offer triple) and is the single contract every downstream module reads. **No module reads any "is breakable" flag from PIM, because PIM exposes none.**

---

## §8 i18n Implementation **(D2 — KEPT)**

NewCo's Bottle Page and member-facing surfaces operate in **six locales at launch**: English, Italian, French, German, Japanese, simplified Chinese (DEC-031). **D2 is locked KEEP** — the i18n infrastructure is reportedly already built; only translation *content* remains (which may stagger). There is **no structural saving** in cutting locales, so the launch keeps all six. PIM's translatable descriptive content lives directly **on the entities that hold the strings** (Product Master, Product Variant, Product Reference) — there is **no separate translation registry at launch** (DEC-064; the registry is a carried-forward deferred item, §17). The i18n mechanism is **category-neutral and unchanged** by the generalisation (brief §3.8).

### §8.1 Translatable content lives on existing PIM entities

The descriptive-content fields PIM carries — master-level producer story, variant-level tasting notes and commentary, PR-level format-specific notes — are *translatable per attribute* across the six launch locales. The catalog model treats each translatable field as a per-locale collection of strings; the data shape is a downstream tech choice (DEC-073). The translatable-field set carries forward from v1.1 without field-level addition or removal at launch.

### §8.2 Locale-set evolution post-launch

Adding a seventh (or later) locale post-launch is a **configuration change, not a data-model migration**: the new locale joins the supported-locale list; existing translatable content is back-filled progressively as translations land; consumer surfaces start reading the new locale once content is available. *(Locale-set expansion beyond six is a carried-forward deferred item — §17.)*

### §8.3 Locale validation at the application layer

Whether a translatable-content payload covers all six locales is an application-layer concern at the read site (Bottle Page rendering with a per-locale fallback ladder) and the write site (a Catalog Operator's editing UI flagging missing locales). PIM does **not** enforce locale-completeness as a hard activation gate; partial coverage is allowed (consumers fall back to English when a locale's content is missing).

---

## §9 Bottle Page Content Feeding

The **Bottle Page** (the wine-display name retained per the generalisation guardrail — Module B owns the surface; §18) is the customer-facing surface that renders provenance and product context for a serialized bottle (Module B scope). It reads its producer-supplied content directly from PIM at the appropriate level of the catalog hierarchy plus a deref to Module K for producer-level content. **There is no separate Bottle-Page-content entity at PIM** — the Bottle Page is a *read* over PIM (and Module K) entities, not a curated snapshot kept in sync. **PIM is read-only here; the generalisation makes no change to PIM's role.**

### §9.1 Where each kind of content lives

- **Product Master** carries the **product-level prose**: the producer's story for this product, region / appellation context, winery narrative.
- **Product Variant** carries **variant-specific prose**: tasting notes, critic scores (observational metadata, never used for pricing or allocation per §13), variant-level producer commentary.
- **Product Reference** carries **format-specific notes** when present (rare).
- **Producer (Module K)** carries the **producer-level description** independent of any specific product; the Product Master's Producer link makes it reachable at render time.

Each surface is translatable per §8 across the six launch locales.

### §9.2 PIM publishes; Bottle Page reads

Module 0 publishes content surfaces at the Product Master / Product Variant / PR levels and emits the standard PIM lifecycle events (`*Created`, `*Activated`, `*Retired`, `EnrichmentDataUpdated`). The Bottle Page rendering surface (Module B) reads those surfaces at render time, plus the Producer description from Module K, and assembles the customer-facing page. Module 0 has no Bottle-Page-specific entity, event, or governance step.

### §9.3 NFT and provenance content — **PIM silent (D12-neutral)**

The Bottle Page also surfaces NFT-related provenance — the active NFT identity, predecessor-NFT linkage on re-mints, lost / damaged status of any predecessor. **None of this is owned by PIM**: NFT and serialized-bottle state are owned by Module B on the SerializedBottle entity. The Bottle Page joins Module B's SerializedBottle back to PIM's Product Reference at render time. **Module 0 PIM has no NFT-related attribute or event** — and this silence is exactly what keeps PIM *neutral to the D12 on-chain decouple*: at launch the on-chain layer is decoupled (Module B), each serialized unit carries `nft_reference = NULL` back-filled later, and the Bottle Page renders the non-NFT content with the chain-link lighting up when the on-chain workstream lands. **PIM is unchanged regardless** (it was always silent — §11).

---

## §10 Sourcing-Model Boundary — **PIM silent**

NewCo operates three sourcing models for bringing product into commercial reach (DEC-011 + DEC-063): **passive consignment V2 (default)** — producer ships stock to NewCo's bonded warehouse (Vinlock), holds title until sell-through, settled on a quarterly cadence post-sale; **passive consignment V1 (exception)** — stock stays at the producer, ships to Vinlock per customer order (very expensive / rare bottles); **direct purchase (exception)** — NewCo pays outright at purchase, takes title at purchase, holds inventory at Vinlock, sells through.

**The Product Reference is identity** — the same product, variant, format — *regardless of how the unit came into NewCo's commercial reach*. Module 0 carries **no sourcing-model attribute on any PIM entity.** The sourcing model is an Allocation-level distinction owned by Module A; the same PR is referenced by allocations across all three models without duplication or branching at PIM.

> **Cross-MVP note (Direct Purchase deferred — Phase C item I).** Direct Purchase is **deferred at launch** (confirmed: no launch deal — Phase C Q4); the `direct_purchase` path idles across Modules A/D/B/E/S with a retained-enum seam. **This does not touch Module 0** — PIM is sourcing-agnostic by design, carries no sourcing attribute, and needs no change whether Direct Purchase is active or idle. PIM stays clean of commerce: no PIM attribute carries a price, cost, payment-timing distinction, settlement-cadence reference, or any sourcing-related commercial state (those belong to Modules A / S / E; per DEC-072, Module E records the events and the accounting integration determines GL treatment).

---

## §11 NFT Linkage Boundary — **PIM silent (D12-neutral)**

Every serialized bottle on the platform carries an NFT (the NFC / NFT model — physical NFC tag scanned at delivery activates an on-chain NFT representing the physical unit's identity and provenance). The NFT is the unit's **digital twin**.

PIM **holds Product Reference identity only.** The NFT linkage — the active NFT identity on a serialized unit, the predecessor-NFT linkage recording re-mints under recovery scenarios, the lost / damaged / replaced status of any predecessor — is owned by Module B on the SerializedBottle entity, **not on any PIM entity.** The Bottle Page (§9) reads NFT-related provenance by joining Module B's SerializedBottle back to PIM's PR. **Module 0 has no NFT-related attribute or event**, and the four NFC / NFT recovery scenarios are entirely Module B + Module C + Module E concerns.

> **Why this silence is load-bearing for the MVP (D12 DECOUPLE).** Module B decouples the on-chain NFT layer off the launch critical path (DECOUPLE ≠ DEFER — the value-prop is preserved; the per-unit serialization workflow stays launch-ready; only NFT mint/burn + custodial wallet + on-chain recovery + chain-link content decouple, with `nft_reference` nullable + back-fillable). **Because PIM was always silent on NFT, the decouple requires zero Module 0 change** — Module 0 is intrinsically D12-neutral. No PIM consumer reads an NFT attribute from PIM.

---

## §12 Serialization Boundary — **PIM silent**

Serialization is **default-on by NewCo policy** at launch — every unit entering commercial reach is serialized (NFC tag, NFT digital twin, individually tracked) unless explicitly listed on Discovery as non-serialized (DEC-052). The split between serialized and non-serialized stock — and the discriminator that decides which sub-pool a given Offer draws from — runs across two modules: **Allocation (Module A)** carves a single Allocation's quantity into a to-be-serialized portion and a non-serialized portion (sub-pool quantities owned at the Allocation level); **Offer (Module S)** carries the serialization-type discriminator that determines which sub-pool an Offer draws from.

**PIM is silent on serialization.** Module 0 carries no serialization-related attribute and emits no serialization-related event. The Discovery non-serialized "badge" surfaces on Offers drawing from the non-serialized sub-pool — a Module S / UI concern. *(This silence, like §10/§11, is what keeps the catalog clean and neutral to the A/B/S commercial state — unchanged by the generalisation.)*

---

## §13 Business Rules and Invariants

The PIM business rules cluster into seven groups. **Behaviour is unchanged for wine** by the generalisation; the only generalisation touch is that **BR-Identity-1 is expressed as a type-defined identity key** (brief §3.6) and the rule prose is re-named `Wine* → Product*`.

### §13.1 Product identity

**BR-Identity-1. Unique product identity (type-defined key).** Uniqueness is enforced **per Product Type on a type-defined identity key.** For `WINE` the key is **producer + product name + appellation** (unchanged from v1.1's "producer + wine name + appellation"): no two `active` `WINE` Product Masters may share that combination. The deduplication check runs on both the enrichment-adapter creation path and the manual baseline path.

**BR-Identity-2. Hierarchy integrity.** Each Product Variant belongs to exactly one Product Master. Each Product Reference references exactly one Product Variant and one Format. No PIM entity belongs to multiple parents.

**BR-Identity-3. Product Reference is exactly two dimensions.** A PR = Product Variant + Format. Case Configuration is **never** part of PR identity. One Sassicaia 2018 in 0.75L is the same PR whether sold loose, in an OWC, or in a carton. Allocations are keyed at PR level. Enforced system-wide.

**BR-Identity-4. Immutability on reference.** Once a PR is referenced by an Allocation, voucher, stock position, or commercial Offer, its Product Variant + Format composition cannot be changed.

### §13.2 Lifecycle and governance

**BR-Lifecycle-1. Multi-step approval required.** Every PIM entity follows the Creator → Reviewer → Approver workflow; the roles that run must be distinct people; **self-approval is never allowed.** *(The role-count is admin-configurable per Q2 — §4.2; the separation-of-duties floor holds at any configured depth.)*

**BR-Lifecycle-2. Four-state lifecycle.** Every PIM entity follows `draft → reviewed → active → retired`. Re-activation from `retired` to `active` follows the same approval workflow.

**BR-Lifecycle-3. Activation cascade.** A child cannot transition to `active` while its parent is not `active`. Product Master activation also requires its linked Producer (Module K) to be `active` and KYC-cleared (`verified` or `not_required`). Sellable SKU activation requires both the PR and the Case Configuration to be `active`.

**BR-Lifecycle-4. Retirement cascade.** When a parent is retired, existing active children remain valid for current references but cannot be used in new commercial commitment. No new children can be activated under a retired parent.

**BR-Lifecycle-5. Retirement blocked by active references.** A PIM entity cannot be retired while it has active downstream references that have not yet completed. The system surfaces the open references; retirement proceeds after they close.

**BR-Lifecycle-6. Rejection handling.** A rejected entity stays in `reviewed` with a rejection flag and notes. The Creator edits in place — no revert to `draft` — and re-submits; the flow restarts from review. Full rejection history is preserved in the audit trail.

### §13.3 Version, audit, and data ownership **(audit/retention floor)**

**BR-Audit-1. Version immutability.** Changes to identity-bearing entities create new versions; old versions are deprecated, never deleted; full before-and-after state is recorded.

**BR-Audit-2. Enrichment data is observational only.** Critic scores, tasting notes, and market data are informational metadata. They are **never** used for commercial pricing decisions or allocation logic. Hard constraint.

**BR-Audit-3. Captured-data ownership.** Identity data captured from an enrichment adapter at creation (for `WINE`, from Liv-ex) is owned by PIM thereafter; PIM does not auto-sync with future adapter-source changes; the captured snapshot is authoritative.

### §13.4 Producer dependency **(KYC compliance floor)**

**BR-Producer-1. Producer activation gate.** A Product Master cannot transition to `active` unless the linked Producer (Module K Producer Registry) is itself `active` and KYC-cleared (`verified` or `not_required`).

**BR-Producer-2. KYC-revocation symmetry.** If a Producer's KYC verification is revoked after Product Masters have been activated under it, those existing `active` Masters remain `active`; the revocation only blocks *new* Master activations (and new child-entity activations under those Masters) for that Producer.

### §13.5 Sellable SKU rules

**BR-SKU-1. Intrinsic SKU composition.** A Sellable SKU (Intrinsic) = one Product Reference + one Case Configuration + commercial attributes; the activation prerequisite is that the PR and the Case Configuration are both `active`.

**BR-SKU-2. Composite SKU governance.** Composite SKUs are originated by Module S for commercial purposes; PIM registers them and governs their lifecycle (same 4-state lifecycle, same approval workflow). Module S defines the constituent composition; PIM enforces hierarchy integrity (every constituent PR exists and is `active` at activation time) and lifecycle compliance.

**BR-SKU-3. Composite SKU atomicity.** A Composite SKU is commercially atomic. At sale, the system issues one voucher per constituent unit-equivalent; reservations, holds, and fulfilment must succeed for *every* constituent; partial-bundle issuance is not allowed.

**BR-SKU-4. Composite SKU immutability after commercial commitment.** A Composite SKU's constituent composition becomes immutable once referenced by an `active` Offer; the path to change it is retire + register new.

**BR-SKU-5. Composite SKU is producer-agnostic at PIM.** A Composite SKU may carry constituent PRs from one or many producers; PIM does not validate producer composition. Admissibility on a given commercial surface is a Module S Offer-publication validation (club Offers reject mixed-producer sets, DEC-019; Discovery Offers admit them, DEC-061 — *the multi-producer Discovery path is deferred at launch, D7; all launch composites are single-producer*). PIM is silent on this validation.

### §13.6 Format and Case Configuration

**BR-RefData-1. Format governance.** New Formats can be proposed by any Catalog Operator and require the standard approval before becoming available for new PR creation; a PR cannot activate if its referenced Format is not `active`. *(The admissible Format vocabulary is type-driven — for `WINE`, bottle sizes; §3.1.)*

**BR-RefData-2. Case Configuration is packaging form only.** Case Configuration carries packaging-form attributes only — units per case, packaging type, physical attributes. It **carries no breakability flag**; breakability is decided by the §7 layered rule, to which PIM contributes via Layer 1 only.

### §13.7 Bulk import

**BR-BulkImport-1. Configurable depth.** Bulk-import depth is configurable per operation (Product Master only; + Variant; + PR; or full chain through Sellable SKU).

**BR-BulkImport-2. Partial-failure handling.** Records that fail validation are skipped and reported in a detailed error log with specific reasons; valid records proceed regardless.

**BR-BulkImport-3. Standard governance applies.** All bulk-imported entities enter as `draft` and follow the standard approval workflow; there is no batch-approval shortcut.

**BR-BulkImport-4. Operator-driven re-attempt.** Re-attempt of failed rows is operator-initiated; PIM does not subscribe to upstream `*Activated` events to auto-replay queued failures.

### §13.8 System resilience

**BR-Resilience-1. Enrichment-adapter fallback.** If a Product Type's enrichment adapter is unavailable during a creation (for `WINE`, the Liv-ex API), PIM retries automatically; if retries fail, the Operator is notified and offered the manual baseline path, which preserves all validation and deduplication.

### §13.9 Cross-module contract

**BR-Contract-1. Domain events as cross-module contract.** All PIM lifecycle transitions emit versioned domain events consumed by downstream modules (full enumeration in §14). Events are versioned so consumers can evolve independently; PIM guarantees backward compatibility within a major event-schema version.

---

## §14 Domain Events **(the source-of-truth event vocabulary — naming cascade, Phase C item A)**

Module 0 emits a uniform set of lifecycle events across every PIM entity, plus one observational-update event. **This is the cross-module event vocabulary every downstream module reads — and the generalisation renames the `Wine*`/`BottleReference*` families to `Product*` here, at the source.** Payload semantics are **identical** (only the name changes); every event carries the same business signal.

### §14.1 The event set (renamed)

For each PIM entity that goes through the lifecycle, three events fire — one per state transition that opens or closes downstream commercial impact:

| Entity | Created | Activated | Retired | Generalisation |
|---|---|---|---|---|
| Product Master | `ProductMasterCreated` | `ProductMasterActivated` | `ProductMasterRetired` | **renamed** from `WineMaster*` |
| Product Variant | `ProductVariantCreated` | `ProductVariantActivated` | `ProductVariantRetired` | **renamed** from `WineVariant*` |
| Product Reference | `ProductReferenceCreated` | `ProductReferenceActivated` | `ProductReferenceRetired` | **renamed** from `BottleReference*` |
| Sellable SKU (Intrinsic) | `SellableSKUCreated` | `SellableSKUActivated` | `SellableSKURetired` | unchanged |
| Composite SKU | `CompositeSKUCreated` | `CompositeSKUActivated` | `CompositeSKURetired` | unchanged |
| Format | `FormatCreated` | `FormatActivated` | `FormatRetired` | unchanged |
| Case Configuration | `CaseConfigurationCreated` | `CaseConfigurationActivated` | `CaseConfigurationRetired` | unchanged |

Plus one observational-update event:
- `EnrichmentDataUpdated` — emitted when observational enrichment metadata (critic scores, tasting notes, market data) changes on a Product Variant. **Unchanged.** Distinct from the lifecycle triplet because enrichment changes are mutable post-activation and do not pass through the lifecycle.

### §14.2 Naming convention

- `*Created` covers `<null> → draft` — the entity-creation moment, recorded for audit.
- `*Activated` covers `reviewed → active` — the moment the entity becomes available for downstream commercial commitment.
- `*Retired` covers `active → retired` — the moment new commercial commitment against the entity is blocked (existing references run to natural completion per §4.5).

The `draft → reviewed` transition does not emit a distinct event; review is an internal-to-PIM checkpoint captured in the audit trail. Composite SKU events are emitted as a **distinct event family** from Intrinsic SKU events (Composite is a separate governance lane — the constituent-composition rule, the producer-agnostic-at-PIM rule, the surface-asymmetric admissibility at Module S); downstream consumers dispatch Composite vs Intrinsic on the event family rather than on a payload discriminator.

### §14.3 Emission ordering invariant

**Activation events are emitted in parent-before-child order**, naturally enforced by the §4.4 activation cascade (a child cannot activate before its parent reaches `active`). **Retirement events** emitted by an explicit operator-driven cascade (§4.7) follow parent-before-child ordering (Product Master → Product Variant → Product Reference → Sellable SKU); non-cascading single-entity retirements (§4.6) carry no ordering constraint. **Downstream consumers tolerate eventual-consistency arrival order** — PIM guarantees the *emission* order in cascade workflows; arrival is best-effort; consumers dedupe and reconcile on the parent's current state at consume time.

### §14.4 Versioning

Events are versioned at the schema level so downstream modules evolve consumption independently of PIM's emission; PIM guarantees backward compatibility within a major schema version.

### §14.5 Consumer bindings (renamed)

The cross-module event-consumption map at launch (names generalised; consumption behaviour identical):

- **Module S (Sales).** Consumes `ProductMasterActivated`, `ProductVariantActivated`, `ProductReferenceActivated`, `SellableSKUActivated`, `CompositeSKUActivated` — to enable Offer creation and Price-Book entries against the activated entities. Consumes `EnrichmentDataUpdated` for marketing surfaces. Consumes the corresponding `*Retired` events to flag Offers and Price-Book entries for review.
- **Module A (Allocations).** Consumes `ProductReferenceActivated` to enable Allocation creation; consumes `ProductReferenceRetired` to trigger Allocation review.
- **Module B (Bottle / Stock).** Consumes `ProductReferenceActivated` for stock-position tracking; consumes `ProductReferenceRetired`, `ProductVariantRetired`, `ProductMasterRetired` to flag inventory for review.
- **Module C (Fulfilment).** Consumes `ProductReferenceRetired` to trigger fulfilment holds where open vouchers exist.
- **Module D (Procurement).** Consumes `ProductReferenceActivated` to enable purchase-order creation against the PR.
- **Module K (Parties).** PIM is *upstream* of Module K only via the Product Master → Producer link; the event flow runs the other direction at activation. **PIM consumes** `ProducerActivated` (to enable Product Master activation against that Producer) and `ProducerRetired` (to block new Product Master activations under that Producer; existing actives preserved per BR-Lifecycle-4).

The event payload's *meaning* (the business signal) is part of the cross-module contract and stays in the PRD; exact payload field names, types, and serialization shape are tech-implementation downstream of the PRD (DEC-073).

---

## §15 Module Boundary Notes — what Module 0 does NOT do

For clarity on cross-module hand-offs (and to keep the catalog clean of commerce — the property that makes the generalisation safe and PIM neutral to the A/B/S/D/E commercial state):

- **Pricing, commercial terms, Offers.** Module S. PIM never holds price, cost, currency, or commercial term.
- **Allocation, allocation visibility (CLUB / DISCOVERY), sub-pool carve-out, sourcing-model attribute, negotiated cost.** Module A. PIM never holds allocation state, visibility flag, or sourcing-model marker.
- **Producer entity (legal name, region, KYC status, lifecycle, Discovery-only marker).** Module K. PIM holds only the Product Master → Producer link.
- **Supplier entity (commercial counterpart, distinct from Producer).** Module K + the SupplierProducerLink owned by Module D. PIM has no Supplier reference.
- **Serialization mechanics — sub-pool carve-out (A), serialization-type discriminator (S), NFC tag application, NFT mint and provenance (B).** PIM has no serialization or NFT attribute (§11–§12).
- **Bottle Page rendering, NFT recovery scenarios, anonymous-identity exposure rule.** Module B. PIM publishes the translatable content surfaces the Bottle Page reads; PIM exposes no rendering-side or recovery-side state.
- **Settlement, invoicing (INV1 / INV2 / INV3), payment events, title-transfer events.** Module E. PIM has no commercial-event attribute; per DEC-072, Module E records the events and the accounting integration determines GL treatment.
- **Hero Package as an Offer designation; Offer-level single-producer validation for club surfaces (DEC-019); multi-producer Discovery composite Offer construction (DEC-061, deferred D7).** Module S. PIM's Composite SKU stays producer-agnostic; surface-asymmetric admissibility fires at Offer publication, not at PIM.
- **Non-wine product types, attribute sets, or Format vocabularies.** Not defined at launch (generalisation non-goal §16). PIM is *able* to hold them; only `WINE` is populated.
- **Liquid Product / Liquid SKU / BottlingResolution; Service / Experience SKU subtype.** Not in launch scope (§17). PIM has no liquid or service/experience entity at launch.
- **Crurated v17 `is_club_package` flag on Composite SKU.** Dropped; the equivalent club-Offer designation lives on the Module S Offer.

---

## §16 Generalisation guardrails & the non-behavioural guarantee

The guardrails that bound the generalisation (brief §4 non-goals + §6 definition of done). **These are acceptance-bearing** — the v0.3-MVP acceptance doc tests them (cut-sheet §5):

1. **No non-wine Product Type, attribute set, or Format vocabulary is defined.** The model is *able* to hold them; only `WINE` is populated.
2. **No fully dynamic EAV / rules engine.** A neutral core + additive per-type attribute sets is the ceiling. (Physical representation is the dev team's call, DEC-073 — the constraint is only that the core stays neutral and per-type sets are additive.)
3. **No wine behaviour change.** Lifecycle, validations, event semantics, dedup, and the enrichment (LWIN) flow all behave exactly as v1.1 for the `WINE` type.
4. **No other module's wine assumptions generalised** (serialization, custody, club/membership, fulfilment, finance) — those are operating-model-dependent and belong to the post-launch category-expansion workstream. *(Scope honesty: a generic Module 0 makes the platform ready to* catalogue *other products; it does* not *make the platform ready to* sell and fulfil *them under a different operating model. This brief closes the catalogue gap only.)*
5. **No wine-facing UI labels or wine documentation prose renamed.** "Wine," "bottle," "vintage" remain valid presentation terms; only structural/contract names go neutral. ("Wine Master / Wine Variant / Bottle Reference / BR" are retained as wine-facing display aliases.)

**The non-behavioural guarantee (the safety property that makes this low-risk for launch).** For the `WINE` Product Type, **every rule, lifecycle transition, validation, dedup check, enrichment flow, and event payload behaves identically to v1.1.** The change is purely structural naming + attribute placement. This is what lets a hardened v1.1 catalog spine be generalised inside the launch build without re-hardening.

**Areas explicitly UNCHANGED by the generalisation** (called out so they are not needlessly reopened): the 4-state lifecycle, the approval workflow, cascade rules, rejection handling (§4); i18n (§8); layered breakability Layer 1 (§7); the sourcing / NFT / serialization boundaries (§10–§12). Module 0 is already *silent* on all operating-model concerns — exactly the property that keeps the catalogue clean; left intact.

---

## §17 Deferred set & post-launch roadmap pointers (MVP)

**Module 0 takes ~0 net-new deferrals in the MVP strip** (it is KEEP-in-full). The items below are **v1.1's already-deferred set, carried verbatim** to the post-launch roadmap with their existing re-introduction hooks (do not re-cut; do not re-derive). All feed [`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md) (which extends `greenfield/03-qa/qa.deferred.md`).

| # | Deferred item | Seam preserved (P1) | Restores with |
|---|---|---|---|
| §17.1 | **Service / Experience SKU subtype** | Free experiences are booked operationally without a catalog representation; the SellableSKU subtype + lifecycle + pricing surface slot in additively. | Post-launch services/experiences workstream. |
| §17.2 | **Liquid Product / Liquid SKU / BottlingResolution** | The v17 Liquid Product entity shape, its business-rule set, the Liquid SKU composition rule, the `LiquidProduct*` event triple, and the BottlingResolution boundary are well-specified and re-introducible as a coherent block. | Post-launch liquid-sales feature. |
| §17.3 | **Separate translation registry / per-translator workflows** | Launch keeps translatable content on the entities that hold it; a separate translation entity (per-translator queues, per-translation version history, fallback chains beyond English) is additive. | Post-launch when translation workflows grow complex. |
| §17.4 | **Locale-set expansion beyond the six launch locales** | Adding a 7th+ locale is a configuration change, not a data-model migration (§8.2); the six-locale set is the operational starting point, not an upper bound. | Post-launch market expansion. |
| §17.5 | **Producer-content versioning beyond the standard approval** | Producer content follows the standard PIM approval at launch via the entity that holds it; a more granular content lifecycle (per-locale review, separate translator approval lanes, content versioning independent of the entity's version) is additive. | Post-launch. |
| §17.6 | **Bulk-import auto-replay queue** | Re-attempt is operator-driven at launch (§6.4); an auto-replay queue (PIM subscribes to upstream `*Activated` and replays matched failed rows) adds on top of the existing operator path. | Post-launch when volume justifies it. |

> **Generalisation forward-target (not a Module 0 deferral, recorded for roadmap coherence — Phase C item N).** The *category-expansion* workstream (defining a second Product Type — its attribute set, variant axis, enrichment adapter, Format vocabulary, identity key — **and** the A/B/C/D/E + business-model operating-model work to *sell and fulfil* it) is the immediate post-launch objective this generalisation enables. Module 0's generic spine is the catalogue-side readiness; it is **not** itself deferred scope — it ships at launch. The roadmap carries the *second-type* buildout as the forward target.

---

## §18 Naming-cascade source of truth (Phase C item A) — the canonical table

This PRD is the **source of truth** for the cross-module naming cascade. Every sibling v0.3-MVP PRD (and the Architecture) applies these names; the change is **naming/contract only — zero consumer behaviour change** (every event carries the same business signal; BR and PR denote the same key). **Cascade order: Module 0 (here) → A / D → S → B / C → E → Architecture.**

**The canonical renames:**

| v1.1 name | v0.3-MVP canonical name | Wine-display alias (retained) |
|---|---|---|
| Wine Master | **Product Master** | Wine Master |
| Wine Variant | **Product Variant** | Wine Variant |
| Bottle Reference (BR) | **Product Reference (PR)** | Bottle Reference (BR) |
| `WineMaster{Created,Activated,Retired}` | `ProductMaster{Created,Activated,Retired}` | — |
| `WineVariant{Created,Activated,Retired}` | `ProductVariant{Created,Activated,Retired}` | — |
| `BottleReference{Created,Activated,Retired}` | `ProductReference{Created,Activated,Retired}` | — |
| Format, Case Configuration, Sellable SKU, Composite SKU, `EnrichmentDataUpdated` | **unchanged** | — |

**The carve-outs (do NOT rename these — Phase C item A):**
- **Module E** keeps its own **category-neutral** names (`Invoice*`, `Payment*`, `Settlement*`, `NonRevenueCost*`, `OCShare*`, `Chargeback*`, `Refund*`, `Xero*`, `FXVariance*`, `ClubCredit*`, `StoreCredit*`) — unchanged (lightest touch).
- **Modules B and C** retain their **physical-unit / wine-display** names (`SerializedBottle`, `InboundBatch`, `Case`, `StockPosition`, "Bottle Page"; `Shipping Order`, `BottlePicked`, `ShipmentDispatched`, `BottleDelivered`, …) — the physical unit is a bottle for the `WINE` product type (guardrail 5 / §16). These are **wine-display naming**, not category-neutral structural names.
- **"Bottle Reference"** is retained **everywhere** as a wine-display alias for Product Reference.

**Rule of thumb for the sibling PRDs:** rename only the **PR-referencing / Module-0-event-consuming** prose to the canonical names, keeping payload semantics identical; retain the wine-display aliases where they aid wine-facing readers; leave Module E's and Modules B/C's own names alone.

---

## §19 v1.1 inheritance & generalisation trace (audit appendix)

This appendix preserves the audit trail of Module 0 v0.3-MVP against its **frozen v1.1 predecessor** (`greenfield/01-prd/Module_0_PRD_v0.2.md`, which itself traces to Crurated v17 §4 at its own §17) and against the **generalisation brief** (`greenfield/11-category-expansion/Module_0_Generalisation_Change_Brief_v0.1.md`). The load-bearing prose is the body above (DEC-074); this trace is for audit / diff.

| v0.3-MVP section | v1.1 (v0.2) anchor | Generalisation (brief) | MVP disposition |
|---|---|---|---|
| §0 MVP scope at a glance | — (new) | brief §1–§2 | NEW — Phase D framing; KEEP-in-full + GENERALISE verdict. |
| §1 Module Purpose | v0.2 §1 | brief §1, §3.2 | KEEP; generalised to category-neutral spine, `WINE` sole launch type. |
| §2 Personas | v0.2 §2 | — | KEEP; + Q2 lighter-approval config note; + P2 operator-surface note. |
| §3.1 Product Type | — (new) | brief §3.2 | NEW — first-class classifier; sole value `WINE`. |
| §3.2–§3.8 entity spine | v0.2 §3.1–§3.7 | brief §3.1 | KEEP; renamed Master/Variant/Reference; Composite SKU KEPT (Q1). |
| §3.9 Attribute model | — (new) | brief §3.3/§3.4/§3.6 | NEW — neutral core + `WINE` attribute set; type-defined variant axis + identity key. |
| §4 Lifecycle Governance | v0.2 §4 | brief §3.8 (unchanged) | KEEP; + Q2 role-count config. |
| §5 Creation & Enrichment | v0.2 §5 | brief §3.5 | KEEP; generalised to pluggable adapter; Q3 manual-first launch posture. |
| §6 Bulk Import | v0.2 §6 | — | KEEP (already lean); generalised names. |
| §7 Layered Breakability | v0.2 §7 | brief §3.8 (unchanged) | KEEP; Layer 1 (PIM); floor contributor. |
| §8 i18n | v0.2 §8 | brief §3.8 (unchanged) | KEEP (D2); six locales on-entity. |
| §9 Bottle Page Content Feeding | v0.2 §9 | — | KEEP; PIM read-only; D12-neutral NFT silence. |
| §10 Sourcing-Model Boundary | v0.2 §10 | brief §3.8 (unchanged) | KEEP; PIM silent; + Direct-Purchase-deferred note (no Module 0 change). |
| §11 NFT Linkage Boundary | v0.2 §11 | brief §3.8 (unchanged) | KEEP; PIM silent; D12-decouple-neutral. |
| §12 Serialization Boundary | v0.2 §12 | brief §3.8 (unchanged) | KEEP; PIM silent. |
| §13 Business Rules | v0.2 §13 | brief §3.6 (BR-Identity-1) | KEEP all; BR-Identity-1 → type-defined key; rule prose renamed. |
| §14 Domain Events | v0.2 §14 | brief §3.7 | KEEP; `Wine*`/`BottleReference*` → `Product*`; payloads identical; source-of-truth. |
| §15 Module Boundary Notes | v0.2 §15 | brief §4 | KEEP; + generalisation non-goals. |
| §16 Generalisation guardrails | — (new) | brief §4, §6 | NEW — non-goals + non-behavioural guarantee; acceptance-bearing. |
| §17 Deferred set | v0.2 §16 | — | KEEP verbatim → roadmap; ~0 net-new deferrals. |
| §18 Naming-cascade source of truth | — (new) | brief §3.1/§3.7/§5; Phase C item A | NEW — the canonical name table + carve-outs for the cascade. |

Notation: *KEEP* = the v1.1 substance is restated in full NewCo language without semantic change; *generalised* = structural naming / attribute placement changed per the brief, **non-behavioural for wine**; *NEW* = Phase-D / generalisation framing with no v1.1 predecessor.

---

## §20 Cross-references

- **v1.1 predecessor (frozen)** — [`../../reference/v1.1/01-prd/Module_0_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_0_PRD_v0.2.md). The source spec carried in full; never edited (plan R4). Its §17 carries the v17 trace; its §18 the v1.1 cross-references (DECs, qa.mod0, BMD v0.4).
- **Ratified cut-sheet** — [`../01-triage/Module_0_CutSheet_v0.1.md`](../01-triage/Module_0_CutSheet_v0.1.md). §2 inventory (scope), §3 generalisation workstream (rewrite instructions), §5 acceptance delta, §6 the three ratified Qs.
- **Generalisation brief** — [`../../reference/v1.1/11-category-expansion/Module_0_Generalisation_Change_Brief_v0.1.md`](../../reference/v1.1/11-category-expansion/Module_0_Generalisation_Change_Brief_v0.1.md). §3 executed here; §6 checklist = §16 acceptance.
- **Phase C reconciliation** — [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md). Item A (naming cascade — this PRD is the source of truth, §18); §6 (the floor chains Module 0 feeds: KYC gate, Layer-1 breakability, audit/version-immutability, parent-before-child ordering).
- **Method + dials** — [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (P1/P2, the floor, the folder map) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (D2 locales KEEP; D9 LWIN + bulk KEEP).
- **Testable companion** — [`../03-acceptance/Module_0_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_0_Acceptance_v0.3-MVP.md).
- **Sibling v0.3-MVP PRDs** (the cascade consumers, written next in Phase D) — Module K (compliance floor; Producer entity the Product Master links to), then A / D → S → B / C → E, then the Admin-Panel PRD + Architecture.

---

*End of Module 0 PRD v0.3-MVP — Phase D re-baseline. **Verdict: KEEP-in-full + the Wine→Product generalisation; ~0 net-new deferrals.** Non-behavioural for wine. Lands the source-of-truth names for the cross-module naming cascade (§18). Floor pieces (KYC gate, Layer-1 breakability, audit/version-immutability, parent-before-child ordering) KEPT whole. **RATIFIED by Paolo 2026-06-07.** Held in `mvp/`; nothing promoted to `handoff/` until Phase E.*
