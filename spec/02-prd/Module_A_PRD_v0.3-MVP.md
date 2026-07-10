# NewCo ERP — Module A PRD (Allocation) — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP scope of Module A)
- **Date**: 2026-06-07
- **Status**: **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; **nothing is promoted to `handoff/` until Phase E** (the single coherent handoff). Module A is **KEEP-in-full + the naming cascade** — net Module-A-layer spec deferrals ~0; the genuine deferrals (Direct-Purchase *use*, the L-PP producer-write UIs) are seamed-not-cut and the headline D7 lever is **forwarded to Module S**.
- **Owner**: Paolo (decides). Claude recommends.
- **Testable companion**: [`../03-acceptance/Module_A_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_A_Acceptance_v0.3-MVP.md) — the MVP-scoped acceptance criteria (re-cut from the PAOLO-VALIDATED v0.1 per the cut-sheet §5 delta).
- **Predecessors / inputs** (the canonical record governs where this PRD is terse):
  - [`../../reference/v1.1/01-prd/Module_A_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_A_PRD_v0.2.md) — the **frozen v1.1 predecessor** (RELEASED 2026-05-09; Stage 8 / Phase C close). This v0.3-MVP carries its scope **in full** (KEEP-in-full) and applies the naming cascade; `greenfield/` is never edited (plan R4).
  - [`../01-triage/Module_A_CutSheet_v0.1.md`](../01-triage/Module_A_CutSheet_v0.1.md) — the **ratified cut-sheet** (Paolo 2026-06-07). §2 feature inventory = the scope; §3 module-specific changes (D7 forwarding / D11 Direct-Purchase / L-PP / naming cascade) = the rewrite instructions; §5 = the acceptance delta; §6 = the five ratified Qs.
  - [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) — the **coherence gate** (RATIFIED 2026-06-07). Item A (naming cascade), item E (OC 5% capture chain — A preserves the per-constituent lineage), item G (two-layer no-overselling guard + build-sequencing), item I (Direct Purchase deferred — A keeps the enum/FSM seam), floor chains 1 (no-overselling) + 2 (KYC/sanctions). **Module A owns no RECONCILE.**
  - [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18 — the **source-of-truth name table** for the cascade (applied here, not re-derived). [`Module_K_PRD_v0.3-MVP.md`](Module_K_PRD_v0.3-MVP.md) — the settled sibling (§13 Hero Package capacity invariant reads A's `qty`; §9.3 the sanctions-blind boundary).
  - [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (method, P1/P2, the floor) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (D10 allocation sub-pools KEEP; D11 Direct Purchase defer; L-PP one producer write).
- **Methodology** (carried from v1.1; unchanged):
  - **DEC-072** — no accounting-policy positions. Module A records supply / commercial-terms state + events; **Module E records financial events; Xero decides GL.** The prose may use accounting-domain terms descriptively.
  - **DEC-073** — product-spec layer only (entity concepts, business attributes, lifecycle states, business-meaningful enum values, domain-event names + business signals, module boundaries, invariants). Tech-implementation (column types, FK naming, nullability-as-constraint, indexing, API/payload, and **the ATP-cache mechanics — push-vs-pull, reconciliation cadence, tolerance window, latency KPIs**) is the dev team's call and is out of scope (cut-sheet Q4).
  - **DEC-074** — self-contained delivery document. Every entity is reintroduced in full NewCo language; a tech reader who has not read v1.1 can take this into the dev phase. The v1.1 inheritance trace is preserved at §16 (the v17 trace lives in the frozen v0.2 §14).
  - **MVP principles (plan §4.1):** **P1 — defer without burning bridges** (every deferred item names the seam that makes the post-launch build additive, and points to the roadmap); **P2 — admin-first, self-serve-later** (producer/back-office writes are operator-driven via the Admin Panel; consumer storefront exempt). *Module A is the cleanest P2 instance in the triage: it retains **zero** producer writes at launch and **no backend capability is cut** (DEC-083 admin-parity).* 

---

## §0 MVP scope at a glance

**Verdict: Module A is KEPT IN FULL + the naming cascade. Net Module-A-layer spec deferrals ~0.** Module A (Allocation) is the **supply-side primitive inside the scope floor** — it is named in the core-loop floor ("allocation published"), it owns **Layer 1 of the two-layer no-overselling guard**, and it holds the **committed-inventory-protection release primitive** (`VoucherCancelled`). A Lean MVP does not gut a floor-resident foundational module; like Module 0 and Module K it is a near-whole KEEP, with its two headline levers **forwarded downstream, not taken here.** Three facts converge:

1. **D7 (the kickoff's nominated "primary cut") is a Module S cut, not a Module A cut.** The multi-producer atomic-bind — "ONE composite Offer reading N constituent Allocations atomically" — lives entirely in **Module S** (DEC-097; rollback-on-issuance DEC-179). Per DEC-061 each constituent settles to its own producer through its **own single-producer Allocation** at that allocation's per-unit cost `C_i`. Module A's job is only to be the per-constituent supply primitive — which it already is (single-Allocation entity DEC-075 + the two-FK `producer_id`/`supplier_id` pattern DEC-082 + per-constituent `commercial_terms`). **Deferring multi-producer atomic composites removes nothing from Module A**; single-producer offers (club mixed-case DEC-019; single-Allocation Discovery) ship at launch unchanged. **The substantive D7 cut is forwarded to the Module S cut-sheet** — the exact mirror of K→S forwarding for D8. (§3.1; ratified Q1.)
2. **D10 (Allocation) is locked KEEP and was already heavily trimmed in v1.1.** The single Allocation entity (subtypes/split-tables rejected, DEC-075), the 2-value visibility enum (`BOTH` dropped, DEC-076), the sub-pool partition (DEC-080), the harmonized operator-publish activation (DEC-183 — which collapsed the Direct-Purchase-specific FSM trigger into a uniform one), and the ATP-cache contract (DEC-187) are all pre-trimmed. Nothing in the Allocation core is heavier than the supply primitive the core loop requires.
3. **Module A carries a piece of the scope floor — un-cuttable at the product-spec layer.** Layer 1 (`qty − issued ≥ 0`, DEC-099), the per-sub-pool ATP composition with Module B's Layer 2 (DEC-185/187/196), and the `VoucherCancelled` committed-inventory-protection release primitive (DEC-099 + Q-CL-6) are all named-floor in MVP-plan §3.

**The naming cascade (Phase C item A — the one mechanical change).** Module A applies Module 0's source-of-truth names (`Bottle Reference → Product Reference (PR)`; `Wine Variant → Product Variant`; `Wine Master → Product Master`; the consumed Module-0 events `BottleReference*/Wine* → Product*`) to its **catalog-identity reads** (§1, §3, §4.1, §8 Layer-1 read, §10.1, §11.1). **Module A's own `Allocation*` entity / event / attribute names are already category-neutral — unchanged.** "Bottle Reference" is retained everywhere as a **wine-display alias**. **Naming/contract only — zero behaviour change** (see §15).

**The two genuine launch-scope reductions that *touch* Module A — both seamed, both carrying zero backend/spec cut:**
- **Direct Purchase (`sourcing_model = direct_purchase`) — DEFERRED at launch** (D11 / Phase C item I; confirmed at ratification — no launch deal). No `direct_purchase` allocations are created. **The deferral is essentially free for Module A:** DEC-183 already harmonized activation so there is no Direct-Purchase-specific FSM, validation, or event to remove. **Seam (P1):** the `direct_purchase` enum value + the uniform operator-publish FSM are retained; the §11.3.1 activation chain + the capacity-increase follow-on-PO note are documented-but-not-exercised. **The substantive Direct-Purchase deferral (PI / PO / supplier-payment / inbound flow) is Module D's.** (§3.2.)
- **Producer-Portal allocation-operation write UIs (L-PP) — DEFERRED.** Every Module A operation is **operator-driven via the Admin Panel** at launch; **Module A retains zero producer writes** (the one platform-wide retained producer write — membership approve/decline — is a Module K surface). **No backend capability is cut** — DEC-083 admin-parity is a *backend contract*, so operator-driving everything needs no new backend. **Seam (P1):** the producer write UIs are built post-launch on the same backend. Producer Portal **read + full reporting (D23) is KEPT.** (§2, §3.3.)

**The floor pieces Module A holds (all KEPT, whole) — verified in composition by Phase C §6:**
- **Layer 1 no-overselling** (`qty − issued ≥ 0`, §7.1; DEC-099) + the **per-sub-pool ATP composition** with Module B's Layer 2 (§7.1, §11.5; DEC-185/187/196), strongly consistent, per sub-pool, no cross-sub-pool fungibility. *(Floor chain 1 / Phase C item G.)*
- **The `VoucherCancelled` committed-inventory-protection release primitive** (§11.5.2, §12.3; DEC-099/190, Q-CL-6) — the load-bearing cross-module safety interlock that lets a Module B adjustment proceed against committed inventory. *(Committed-inventory floor chain.)*
- **The sanctions-blind boundary** (§9; DEC-071) — Module A does **not** gate on sanctions; enforcement is Module S at order completion (the mirror of Module K §9.3 — K + A are sanctions-blind by design). *(Floor chain 2.)*
- **The producer-side activation gates** — Product Reference `active`; Producer `active` + KYC-cleared (`verified` or `not_required`); SupplierProducerLink `active` (§9) — the floor's upstream expression (no allocation under a producer whose KYC has not cleared).

**The two hard cross-module contracts Module A anchors (KEPT, confirmed from both sides):**
- **Hero Package capacity ↔ Module K.** Module A **owns the allocation `qty`** (§4.1; DEC-079); Module K's Hero Package Capacity Invariant (Module K §13) *reads* it (`active Profiles ≤ qty`), and A's mid-year mutability **is** the capacity-adjustment signal K consumes (ratified A Q5 + K Q5). No orphan. *(Phase C item G.)*
- **Per-constituent settlement lineage ↔ Module S / Module E.** Module A preserves the lineage the deferred OC 5% Discovery share + producer settlement read: `commercial_terms` per-constituent `C_i` (§4.1) + the two-FK `producer_id`/`supplier_id` (§4.1) + sibling shared-keys (§6). The 5% computation + settlement defer with the engine (D19); the **capture** is whole at launch. *(Phase C item E — seam-critical.)*

**The five ratified scope confirmations (cut-sheet §6, Paolo 2026-06-07):**
- **Q1 — D7 forwarding confirmed.** Module A takes **no D7 cut**; the multi-producer atomic-composite defer is forwarded to Module S. Module A ships its full supply primitive as the seam; single-producer offers ship at launch. (§3.1.)
- **Q2 — Direct Purchase deferred at launch** (passive V1 + V2 only). A keeps the `direct_purchase` enum value + uniform operator-publish FSM (DEC-183) as the zero-cost seam; the substantive deferral is Module D's. (§3.2.)
- **Q3 — all Module A operations operator-driven via the Admin Panel** at launch; Producer-Portal write UIs deferred (zero producer writes); **no backend capability cut** (DEC-083 admin-parity); Producer Portal read + full reporting KEPT. (§3.3.)
- **Q4 — the floor contract KEPT as-is.** The two-layer guard, per-sub-pool ATP, and `VoucherCancelled` release are floor; the **cache mechanics are tech-implementation (DEC-073)**; the D16 Module-B workflow-depth review + the A(phase-3)↔B(phase-5) build-sequencing are carried to Phase C/E (confirmed CONSISTENT — Phase C item G/H). (§7.1, §11.5.)
- **Q5 — mid-life mutation operations KEPT** — cheap single-row updates that are the operator levers a manual-first Admin-Panel launch needs (e.g. relisting unsold club stock to Discovery via a visibility flip). (§5.3.)

**Deferred set (MVP):** the **Direct-Purchase *use*** (enum + uniform-FSM seam; §3.2, §14), the **L-PP producer-write UIs** (no backend cut; §3.3, §14), and v1.1's **already-deferred set carried verbatim** to the post-launch roadmap with their existing re-introduction hooks (§14). **No new Module-A floor or supply-primitive scope is cut.**

---

## §1 Module Purpose

Module A is NewCo's authoritative registry for **Allocation** — the producer's (and where applicable, supplier's) commitment of a quantity of a specific **Product Reference** (PR; wine-display alias *Bottle Reference / BR*) for sale on a specified surface. The Allocation is the **load-bearing supply-side primitive**: every Voucher issued to a Customer derives from one Allocation; every Producer PO settlement traces back through one Allocation; every InboundEvent recorded by Module D pertains to one Allocation; every Bottle Page (Module B) joins back to one Allocation through its sourcing lineage.

The Allocation entity answers four cross-cutting questions about each unit of commercial supply:

1. **What is being committed?** → Product Reference (Module 0 §3.4; the catalog identity = Product Variant + Format).
2. **Who is committing it?** → `producer_id` always (Module K §4.4; the wine identity); `supplier_id` optional (Module K §4.5; the commercial counterpart NewCo transacts with, when distinct from the Producer, per DEC-082).
3. **How is it being commercially sourced?** → `sourcing_model` (passive consignment V1 / passive consignment V2 / direct purchase, per DEC-011 + DEC-063 — **`direct_purchase` deferred at launch, enum retained as the seam**, §3.2), `commercial_terms` (the unified `{shape, value}` structure, DEC-092), `visibility` (CLUB_ONLY or DISCOVERY_ONLY, DEC-076).
4. **What state is it in?** → the Allocation FSM (DRAFT → ACTIVE → CLOSED → RETIRED).

Module A is **state + operations + events**. It records the producer / supplier commitment, governs the per-allocation operations exposed from the **Admin Panel at launch** (operator-driven; Producer-Portal write UIs deferred — §3.3) under the DEC-083 parity contract, enforces business invariants (anti-orphan rule, sub-pool partition arithmetic, layered-breakability Layer 2 immutability), holds **Layer 1 of the two-layer no-overselling guard** + the per-sub-pool ATP-cache contract, emits versioned domain events on every lifecycle transition and operation, and exposes read contracts downstream modules consume.

Module A does NOT do (boundaries unchanged — §13): Offer publication / cart / checkout / voucher issuance (Module S); ProcurementIntent / PurchaseOrder / InboundEvent / SupplierProducerLink / supplier-payment execution (Module D); inventory-ledger authority / NFC / NFT / serialized-bottle identity / Bottle Page (Module B); pick / pack / ship / late binding / cellar render (Module C); settlement / invoicing / GL treatment (Module E + Xero, DEC-072); ProducerAgreement lifecycle (Module K).

---

## §2 Personas

Module A serves several operator-facing roles plus the cross-cutting Admin-Panel ↔ Producer-Portal parity principle (DEC-083) that governs which surface each role acts from. **At launch, every Module A write is operator-driven via the NewCo Admin Panel (P2 / L-PP — §3.3); the Producer-Portal write UIs are deferred.** The parity remains a backend contract (DEC-083); deferring the producer-facing write UIs cuts no backend capability.

- **Allocation Operator (Admin-Panel-side / NewCo Ops)**. NewCo's commercial-operations staff. Performs **every** per-allocation operation in the §5.3 catalogue — on behalf of Producers (especially Club owners who prefer email + ops-mediated workflows). Acts from the NewCo Admin Panel. **This is the launch write surface for all of Module A.** Per DEC-083 the Admin Panel is functionally complete on per-allocation operations.
- **Allocation Operator (Producer-Portal-side)** — *write UIs deferred at launch (L-PP).* The Producer's staff would create / mutate / transition Allocations from the Producer Portal post-launch; at launch they do not (the operator path covers it). The persona is retained because the **backend** already exposes every operation from both surfaces; only the producer-facing write UI is deferred. (Seam — §3.3.)
- **Procurement Operator (Module D-side, cross-module reader)**. Consumes Module A allocation state (Allocation row + `commercial_terms` + `sourcing_model`) when issuing PIs / POs (Module D PRD §3–§5). Reads at PI / PO creation time; does not edit Module A state.
- **Catalog Lead (Module 0 ↔ Module A, cross-cutting)**. Approves Product Reference activation in Module 0 PIM (the prerequisite for any Allocation against that PR). Module A's allocation-creation gate is the PR `active` state — read at allocation-creation time per Module 0 §5.4 cascade.
- **Settlement Reviewer (Module E-side, cross-module reader)**. Reads allocation context (`commercial_terms`, `sourcing_model`, counterparty FKs) at settlement-event time to resolve the producer's statement. *Settlement engine deferred (D19); at launch the read is operator-run.* Does not edit Module A state.
- **Producer Portal end-user (read-only reporting — KEPT)**. The Producer's own surface — allocation reporting, sell-through metrics per allocation, settlement projections — reads Module A state **read-only**. Full self-serve producer reporting (D23) is KEPT at launch; only the producer *write* surfaces are deferred.

The cross-cutting **Admin Panel ↔ Producer Portal parity** (DEC-083) is captured in §5 — every per-allocation operation is exposable from both surfaces at the backend; every emitted event carries an `actor_role: producer | newco_ops` tag for audit. Surface-specific UX (form layouts, navigation, validation messages) is downstream tech work per DEC-073.

---

## §3 Architecture — Single Allocation Entity (DEC-075)

Module A's load-bearing pattern at NewCo launch is the **single Allocation entity** (DEC-075): one row per allocation, all sourcing-model × visibility × counterparty combinations expressed via attribute discriminators on a unified entity, governed by a single FSM and a single event set. This pattern is inherited from Crurated v17 §2.1 and extended at NewCo with two enum attributes (`sourcing_model`, `visibility`), the unified `commercial_terms` structure (DEC-092 — supersedes the prior two-field design at DEC-078), and the `non_serialized_offer_admitted` boolean (DEC-080). It is already the *simplest* shape — DEC-075 rejected the heavier subtype-per-sourcing-model / split-table-per-visibility alternatives, which fragmented under the matrix structure and lost the uniform query / event / FSM properties.

The single-entity pattern composes cleanly with two NewCo row-level mechanics:

- **Split-allocation realisation** (DEC-076 — §6): a single Producer commitment exposing across both Club and Discovery surfaces materialises as **separate Allocation rows per visibility** (sibling rows: one CLUB_ONLY + one DISCOVERY_ONLY from the same producer commitment), not a single `BOTH`-flagged row. The visibility enum is **2-value at launch** (`CLUB_ONLY | DISCOVERY_ONLY`); `BOTH` is dropped (DEC-076; `BOTH` reactivation already deferred to a future-DEC — carried verbatim, §14).
- **Sub-pool partition** (DEC-080 — §7): each Allocation carries an explicit numeric partition between serialized stock (`qty_to_serialize`) and non-serialized stock (`qty_non_serialized`), summing to total `qty`. The non-serialization opt-out flag (`non_serialized_offer_admitted`) is admissible across **all** sourcing × visibility combinations.

The entity is consumed by **every other module** at NewCo (naming cascade applied to the catalog-identity reads):

- **Module 0 (PIM)**: Module A reads **Product Variant + Product Reference** identity at allocation creation (PR reference on Allocation; `producer_breakability` keyed by Case Configuration must be a subset of the **Product Variant**'s Layer 1 whitelist per Module 0 §7.1).
- **Module K (Parties)**: Module A reads Producer (`producer_id` always populated) + Supplier (`supplier_id` optional, DEC-082) identity; reads ProducerAgreement state **only at Module D's PO-issuance gate** (DEC-094 — never at Module A's allocation operations, DEC-077).
- **Module D (Procurement / Inbound)**: Module A is the upstream entity PI / PO / InboundEvent reference. Allocation activation is **operator-publish post-PO-commit uniformly across V1 / V2 / Direct Purchase** per DEC-183 (§5.3.2). `SupplierPaymentCompleted` is a **financial event with no FSM role** (DEC-183 / Phase C R1); it is **emitted by Module E** on payment clearing and consumed by Module D + Module B (Phase C R4) — **Module A is neither its emitter nor a load-bearing consumer** (§11.3).
- **Module S (Offer / Cart / Checkout)**: Module A is the upstream entity Offers publish from; the Offer's surface must match the Allocation's `visibility`; the Offer's `serialization_type` must align with `non_serialized_offer_admitted`; the Offer's pricing reads `commercial_terms` (DEC-092). **The deferred multi-producer composite-Offer construct (D7) is Module S's; Module A holds the N single-producer constituent Allocations — the seam** (§3.1).
- **Module B (Inventory Authority + Digital Provenance)**: Module A surfaces `non_serialized_offer_admitted` + sub-pool numerics for Module B's serialization workflow. **Bidirectional**: Module A is a downstream consumer of Module B's per-sub-pool ATP push events for the ATP-cache contract (DEC-187) + the `InventoryShortfallDetected` shortfall workflow (DEC-190). Module B's physical-inventory layer (Layer 2) composes with Module A's allocation-pool layer (Layer 1) to form the **two-layer no-overselling guard** (§7.1, §11.5). *(NFT decoupled per D12; the non-serialized path is the universal fallback — Module A's partition functions even if the on-chain workstream slips, §7.)*
- **Module C (Fulfilment)**: Module C's shipment gate reads `InboundEventPhysicallyAccepted` (Module D) for the relevant qty; Module A's `state = ACTIVE` is the **sellability** gate (decoupled from shipment, DEC-081).

### §3.1 D7 forwarding — the headline cut is taken in Module S, not Module A (cut-sheet §3.1; ratified Q1)

The kickoff nominated D7 (defer multi-producer atomic composites) as Module A's "primary cut." The cut-sheet's finding — **confirmed at ratification** — is that **Module A needs no D7 cut**:

1. **The atomic-bind logic is not in Module A.** Multi-producer Discovery composite publication is "ONE composite Offer reading N Allocations atomically" — a **Module S** entity-and-binding decision (DEC-097), with atomic transactional rollback at voucher issuance also in Module S (DEC-179). Module A carries **no** composite-binding attribute, event, or operation.
2. **Per-constituent settlement is per-Allocation.** Per DEC-061, each constituent of a multi-producer composite settles to its own producer through its **own single-producer Allocation** at that allocation's per-unit cost `C_i`; NewCo's margin = `P_d − Σ C_i`. The composite is an N:M Offer-over-Allocations construct (Module S); Module A just holds the N constituent rows.
3. **The seam is intrinsic to Module A's design** (P1): the single-Allocation entity (§4.1) is referenceable as one of N constituents; the two-FK pattern (§4.1) already supports a Discovery Supplier spanning N producers; `commercial_terms` already carries per-constituent `C_i`. **Nothing must be added or preserved specially — the seam is the supply primitive itself.**
4. **Single-producer offers ship at launch unchanged.** Club mixed-cases are single-producer by definition (DEC-019); single-Allocation Discovery offers use the same two-FK substrate.

**Verdict:** the substantive "defer multi-producer atomic composite Offers; ship single-producer" decision lives in the **Module S** cut-sheet/PRD. Module A ships its full supply primitive. *(Tri-module restoration — Phase C item N: Discovery composites restore as S + A + 0; A keeps the per-constituent seam; 0 keeps Composite SKU; B/C/D/E see N normal vouchers, never a "composite.")*

### §3.2 D11 Direct-Purchase treatment — deferred at launch, free for Module A (cut-sheet §3.2; ratified Q2 / Phase C item I)

D11 locked **KEEP both passive-consignment variants (V1 + V2); DEFER Direct Purchase.** Confirmed at ratification: **no launch-pipeline deal needs Direct Purchase.** Module-A-side treatment:

- **At launch, no `direct_purchase` allocations are created.** Direct Purchase is the fallback sourcing model (used when passive isn't on offer); whether it is needed is a commercial/deal-pipeline call (it is not, at launch).
- **The deferral is essentially free for Module A** because DEC-183 already harmonized activation: V1 / V2 / Direct Purchase all transition DRAFT → ACTIVE via the same operator-publish trigger. There is **no Direct-Purchase-specific FSM, validation, or event** to remove — `SupplierPaymentCompleted` is already financial-event-only (and now E-emitted, Phase C R4).
- **Seam (P1):** the `direct_purchase` enum value is retained; the uniform FSM is unchanged; the §11.3.1 activation chain and the capacity-increase follow-on-PO note (§5.3.4) are **documented-but-not-exercised**. Direct Purchase slots back in additively when a deal requires it — zero rework.
- **The substantive Direct-Purchase deferral is Module D's** (the PI → PO → supplier-payment → inbound flow). Module A's enum-value-unused-at-launch and Module D's deferred procurement path idle in lockstep (Phase C item I — all five of A/D/B/E/S idle consistently).

### §3.3 L-PP producer-write treatment (P2) — Module A has zero retained producer writes (cut-sheet §3.3; ratified Q3)

At launch the Producer Portal is full-read + full-reporting + view-only, with exactly **one** producer write retained platform-wide — **membership approve/decline**, a **Module K** surface. **Module A therefore retains *no* producer writes at launch.** Every Module A operation is a producer/back-office write → operator-driven via the Admin Panel:

| Producer write (Module A surface) | Launch treatment | Seam |
|---|---|---|
| Allocation **creation** + **activation** (publish) (§5.3.1–5.3.2) | **Operator-driven via Admin Panel** | DEC-083 parity already exposes both from the Admin Panel; producer UI post-launch on the same backend. |
| All mid-life **mutations** — visibility / qty / commercial_terms / counterparty / sub-pool / opt-out (§5.3.3–5.3.8) | **Operator-driven via Admin Panel** | Same backend; producer write UI post-launch. |
| **Recall** trigger (§5.3.7) | **Operator-driven via Admin Panel** (already admitted operator-side, DEC-090) | Same backend; producer UI post-launch. |
| **Close / Retire** (§5.3.9–5.3.10) | **Operator-driven via Admin Panel** | Same backend; producer UI post-launch. |
| Hero Package **designation** | **Not a Module A surface** — Hero Package designation is a Module S Offer-level attribute (DEC-076/§13); the backing Allocation is a normal `CLUB_ONLY` allocation. | n/a (Module S). |

This is the cleanest L-PP application in the triage: because DEC-083 admin-parity is a *backend contract*, **no backend capability is cut** — only the producer-facing write UIs are deferred, and the operator path is already functionally complete. Producer Portal **read + full reporting** (sell-through metrics per allocation, settlement projections — D23) is **KEPT.** The consolidated operator-surface inventory lives in the 9th Admin-Panel PRD (it references this PRD's operations rather than re-specifying them).

### §3.4 Naming cascade (Phase C item A — naming/contract only, no behaviour change)

Per Module 0 v0.3-MVP §18 (the source of truth), Module A renames only its **PR-referencing / Module-0-event-consuming** prose; its own `Allocation*` names are already category-neutral and unchanged. The full application table is at §15. Headline touchpoints: `Bottle Reference (BR) → Product Reference (PR)` (§1, §3, §4.1, §10.1, §11.1; "Bottle Reference" retained as a wine-display alias); `Wine Variant → Product Variant` (§3, §4.1, §8 Layer-1 read, §11.1); `Wine Master → Product Master` (§3 cross-reads); consumed Module 0 events `BottleReferenceActivated/Retired → ProductReferenceActivated/Retired`, `Wine* → Product*` (§11.1, §12.4).

---

## §4 Entity Model

Module A's launch entity set is **one entity**: Allocation. The discipline is intentional — DEC-075 unifies on a single row-level entity rather than spawning subtypes; DEC-076 realises split commitments as multiple rows; DEC-080 applies the non-serialization opt-out as a per-row boolean.

### §4.1 Allocation

The **Allocation** is the producer's (and where applicable, supplier's) commitment of a quantity of a specific Product Reference for sale on a specified surface, governed by a state machine and operated through a parity-symmetric set of operations (Admin Panel at launch; Producer-Portal write UIs deferred, DEC-083). Each Allocation carries the following business attributes; tech-implementation shape (column types, nullability constraints, FK declarations, indexing) is downstream per DEC-073.

**Identity attributes**:

- **Product Reference reference** (wine-display alias *Bottle Reference*): the catalog identity the allocation commits to (Module 0 §3.4). The PR must be `active` at allocation creation; retirement of an upstream Module 0 entity (Product Master, Product Variant, PR, Format) does **not** retroactively invalidate existing allocations (Module 0 §4.5 cascade — existing references run to natural completion).
- **Producer reference (`producer_id`)**: always populated; the wine identity ("who made it"); FK to Module K §4.4 Producer.
- **Supplier reference (`supplier_id`)**: optional; the commercial counterparty when distinct from the Producer; FK to Module K §4.5 Supplier. Population frequency per DEC-082:
  - **Discovery (`visibility = DISCOVERY_ONLY`)**: COMMON. Discovery Suppliers hold allocations spanning N Producers; each constituent allocation references its own `producer_id` + the shared `supplier_id`. The two-FK pattern is the **D7 seam** (a multi-producer composite is N single-producer Allocations sharing a `supplier_id`; the atomic bind is Module S) and the general Discovery substrate.
  - **Club (`visibility = CLUB_ONLY`)**: ADMITTED but rare. The general rule is no Supplier (the Producer is the commercial counterparty); a Producer-with-established-Supplier-relationship is admitted; the data model supports it from launch even though operational frequency is low.
  - **Producer-as-Supplier collapse**: when the Producer is also the Supplier (the default 1:1 SupplierProducerLink case), `supplier_id` may be left null; settlement / PO routing falls back to `producer_id` (DEC-082).
- **Audit identity**: opaque allocation identifier; creation timestamp; creating actor + actor role (`producer | newco_ops`, DEC-083 — `newco_ops` for every write at launch); last-mutation timestamp + actor + actor role.

**Commercial attributes**:

- **`sourcing_model`**: enum at launch — `passive_v1 | passive_v2 | direct_purchase` (DEC-011 + DEC-063), immutable post-creation (changing it would invert PO timing + settlement semantics; the pattern for a sourcing-model change is retire + new-allocation-create).
  - `passive_v2` is the NewCo default (cashflow-positive).
  - `passive_v1` is the exception (very expensive / very rare bottles where pre-emptive transfer to Vinlock is impractical).
  - `direct_purchase` is the fallback — **deferred at launch (§3.2): the enum value is retained as the seam; no `direct_purchase` allocations are created.**
- **`visibility`**: enum at launch — `CLUB_ONLY | DISCOVERY_ONLY` (2-value, DEC-076; `BOTH` dropped). Set at creation; **mutable mid-life** subject to the anti-orphan rule (§5.3.3). Mutation cascades to `commercial_terms` (DEC-076 + DEC-092; §5.3.3).
- **`qty`**: integer; total units committed. **Mutable mid-life** across all sourcing × visibility combinations (DEC-079 — generalises DEC-069's mid-year Hero Package mechanic to all allocation types). Subject to the **anti-orphan rule**: `qty` cannot decrease below the count of vouchers already issued against the allocation. **`qty` is the Layer-1 no-overselling ceiling (§7.1) and the single source of truth for Module K's Hero Package Capacity Invariant** (Module K §13 reads it; A's mid-year mutability is the capacity-adjustment signal K consumes — §11 / Phase C item G).
- **`commercial_terms`** (DEC-092 — supersedes DEC-078): a unified `{shape, value}` structure recording the negotiated per-bottle financial relationship between NewCo and the producer / supplier.
  - **`shape`** ∈ {`fixed_per_unit`, `percent_of_selling_price`}; **`value`** = a per-unit amount when `fixed_per_unit`; a percentage when `percent_of_selling_price`.
  - Orthogonal to `sourcing_model` and counterparty type. Every combination admits either shape.
  - **Club default**: `percent_of_selling_price` with `value` = 12.5% (DEC-010 — producer-set price `P`; Producer PO at 87.5% × `P`; NewCo retains 12.5% × `P`). **Discovery default**: `fixed_per_unit` with `value` = the per-unit cost `C` NewCo pays the producer per Discovery sale (DEC-032).
  - **The per-constituent cost `C_i` carried here is the lineage the deferred OC 5% Originating-Club Discovery share + producer settlement read** (Module K / S / E; Phase C item E — capture whole at launch, computation defers with D19). Mutable mid-life under §5.3.5 business validation.
- **`non_serialized_offer_admitted`**: boolean (default FALSE — serialization-by-default stands). When TRUE, Offers publishing from this Allocation may set `serialization_type = NON_SERIALIZED` (Module S validation). Producer / NewCo ops can opt out at creation or mid-life regardless of `visibility` or `sourcing_model`; the flag is **per-allocation, not per-producer**.
- **Sub-pool partition** (DEC-080): **`qty_to_serialize`** (units intended to be serialized — NFC + NFT at Module B) + **`qty_non_serialized`** (units intended NOT to be serialized), subject to the §7 invariant `qty_to_serialize + qty_non_serialized = qty`.
- **`serialization_type`** (derived): `SERIALIZED | NON_SERIALIZED | MIXED`, derived from the sub-pool numerics. Consumed by Module S Offer-publication validation + Module B serialization workflow. Zero storage cost.

**Layered breakability — Layer 2 attribute**:

- **`producer_breakability`** (DEC-060, from v17 §3.4): an optional per-`case_config` declaration that an OWC must ship intact (no loose bottles drawn from it). **Layer-1 upper bound** (Module 0 §7.1): `producer_breakability` cannot reference a Case Configuration outside the **Product Variant**'s Layer 1 whitelist; Module A enforces this at allocation creation. **Immutability post-first-voucher**: set at creation, immutable once the first voucher under the allocation is issued (existing vouchers cannot have their breakability rules retroactively changed). The `qty` mutability (DEC-079) does **not** extend to `producer_breakability`.
- The full effective rule (`effective_unbreakable = Layer 2 (producer) OR Layer 3 (commercial)`) is computed at sale time in **Module S** (Layer 3 lives on Offers); Module A owns Layer 2 only.

**State**: **`state`** — the FSM-tracked allocation state (DRAFT | ACTIVE | CLOSED | RETIRED), governed by §5.

**Sibling-aggregation keys (DEC-076)** — no explicit FK at launch; sibling Allocations from one Producer commitment are aggregated by shared `producer_id + product_reference + sourcing_model + creation_timestamp` (+ `supplier_id` where populated). A `parent_commitment_id` opaque identifier is a future-DEC item (deferred, §14).

**Settlement-cadence read-from-ProducerAgreement**: the Allocation does **not** carry a settlement-cadence attribute. Cadence is read from `ProducerAgreement.settlement_cadence` (Module K §4.6) at settlement-event-handling time (avoids a stale-cache problem on a renewed agreement).

---

## §5 Allocation Lifecycle and Operations

The Allocation lifecycle is the cross-cutting state machine governing what can be done to an allocation at any moment. The FSM is **uniform across all sourcing × visibility × counterparty combinations** (DEC-075 unification); the only sourcing-model-conditional aspect — the **trigger** for DRAFT → ACTIVE — was **harmonized to operator-publish post-PO-commit uniformly across V1 / V2 / Direct Purchase** (DEC-183), which is what makes the D11 Direct-Purchase defer free for Module A (§3.2).

### §5.1 Lifecycle states

- **DRAFT**: created but **not yet sellable**. No Offers may publish; no vouchers may issue. Allows finalising commercial parameters before going live.
- **ACTIVE**: **sellable**. Module S may publish Offers; vouchers may issue; Cart Holds may attach; sales proceed. The bulk of an allocation's operational life.
- **CLOSED**: **no longer sellable** (no new vouchers issue), but in-flight state continues — already-issued vouchers proceed through their own lifecycle (redemption, shipment, refund); settlement events may still fire for V1/V2 against vouchers already issued. The operational-windup state.
- **RETIRED**: terminal. All in-flight state resolved; no further operations admitted; preserved as an audit / historical-reporting anchor; no events fire.

Progression is monotonic: `DRAFT → ACTIVE → CLOSED → RETIRED`. Backward transitions are not admitted (the pattern for "I closed this but now want to sell more" is to create a new sibling Allocation).

### §5.2 Lifecycle transitions and triggers

| From | To | Trigger | Event emitted |
|---|---|---|---|
| (none) | DRAFT | Operator action — allocation creation (Admin Panel at launch; Producer-Portal write UI deferred) | `AllocationCreated` |
| DRAFT | ACTIVE | **V1 / V2 / Direct Purchase** (harmonized, DEC-183): operator action — operator-publish post-PO-commit (Admin Panel "activate") | `AllocationActivated` |
| ACTIVE | CLOSED | Operator action — close (typically at the natural end of the offer window, or on producer-recall, §5.3.7) | `AllocationClosed` |
| CLOSED | RETIRED | Operator action — retire after settlement reconciliation has resolved all in-flight state | `AllocationRetired` |

**The DRAFT → ACTIVE trigger is uniform (DEC-183 / Phase C R1).** Activation is **operator-publish post-PO-commit** for all sourcing models; **`SupplierPaymentCompleted` has no FSM role** (it is a financial event, E-emitted, consumed by Module D + Module B per Phase C R4 — §11.3). 

- **V1 / V2 trigger flow**: producer commits + sets up the allocation; operator-publish transitions DRAFT → ACTIVE; PI fires at voucher issuance (DEC-093); PO at settlement cadence (DEC-070 + DEC-086). No upfront cash; the FSM trigger is operator-initiated.
- **Direct Purchase trigger flow** *(documented-but-not-exercised — Direct Purchase deferred, §3.2)*: NewCo ops creates the Allocation in DRAFT → Module D PI → Module D PO committed → operator-publish → DRAFT → ACTIVE + `AllocationActivated`. Activation does **not** wait for payment. Past activation, the allocation is sellable; physical receipt at Vinlock (`InboundEventPhysicallyAccepted`) gates **Module C shipment** only, not voucher issuance (the cash-flow / time-to-market gain, DEC-081). The chain is documented at §11.3.1; the seam is the retained enum + uniform FSM.

**Lifecycle is orthogonal to ProducerAgreement state** (DEC-077). ProducerAgreement transitions (`superseded` renewal artifact / `terminated` off-cycle exit) do **not** auto-cascade onto child Allocations. The two cadences are intentionally decoupled (umbrella legal contract vs per-commitment operational unit). The rare anomaly ("terminated agreement still has active allocations") is a **soft alert** in the Admin Panel, not a schema constraint; Module D's PO-issuance two-level gate (DEC-094) protects the downstream procurement flow at PO time. Auto-cascade-on-agreement-transition is a future-DEC (deferred, §14).

### §5.3 Allocation operations — the parity-symmetric catalogue

Per DEC-083, every operation is exposable from BOTH surfaces at the backend; **at launch every operation is performed from the NewCo Admin Panel** (the Producer-Portal write UIs are deferred, §3.3). The contract is **operation-level**: same input parameters, same business validation, same state transition, same emitted event, same audit-trail discipline (every event records `actor_role: producer | newco_ops`). The full set is **KEPT** — these are exactly the mid-life levers a manual-first Admin-Panel launch needs (ratified Q5).

| Operation | DRAFT | ACTIVE | CLOSED | RETIRED | Event emitted |
|---|---|---|---|---|---|
| Create allocation | (creates) | n/a | n/a | n/a | `AllocationCreated` |
| Activate (DRAFT → ACTIVE) | ✓ | n/a | n/a | n/a | `AllocationActivated` |
| Mutate visibility | ✓ | ✓ | n/a | n/a | `AllocationVisibilityChanged` |
| Mutate `qty` (increase) | ✓ | ✓ | n/a | n/a | `AllocationCapacityIncreased` |
| Mutate `qty` (decrease) | ✓ | ✓ | n/a | n/a | `AllocationCapacityDecreased` |
| Update `commercial_terms` | ✓ | ✓ | n/a | n/a | `AllocationCommercialTermsChanged` |
| Counterparty assignment / change | ✓ | ✓ | n/a | n/a | `AllocationCounterpartyChanged` |
| Sub-pool rebalance + opt-out toggle | ✓ | ✓ (see §7) | n/a | n/a | `AllocationSubPoolRebalanced` and/or `AllocationNonSerializedOptOutChanged` |
| Recall trigger | n/a | ✓ | ✓ | n/a | `AllocationRecallTriggered` |
| Close (ACTIVE → CLOSED) | n/a | ✓ | n/a | n/a | `AllocationClosed` |
| Retire (CLOSED → RETIRED) | n/a | n/a | ✓ | n/a | `AllocationRetired` |

The **anti-orphan rule** is the dominant invariant (§5.3.4 canonical statement; applies to qty decreases, visibility flips, and commercial-terms downgrades).

#### §5.3.1 Allocation creation

Inputs: `producer_id`; optional `supplier_id`; Product Reference; `sourcing_model` (immutable post-creation); `visibility`; `qty`; `commercial_terms {shape, value}`; `non_serialized_offer_admitted` (default FALSE); `qty_to_serialize` + `qty_non_serialized` partition; optional `producer_breakability` per case_config; actor role. Validation: PR `active` in Module 0; `producer_id` references an `active`, KYC-cleared (`verified` or `not_required`) Producer in Module K; if `supplier_id` populated, an `active` SupplierProducerLink exists in Module D; `qty_to_serialize + qty_non_serialized = qty`; `producer_breakability` ⊆ the Product Variant's Layer 1 whitelist; `commercial_terms` shape/value well-formed. State: enters DRAFT. Side-effects: none (not yet sellable). *(Launch: operator-driven via Admin Panel; `actor_role = newco_ops`.)*

#### §5.3.2 Activation (DRAFT → ACTIVE)

Inputs: allocation reference; actor role. **Trigger — harmonized across sourcing models (DEC-183).** ACTIVE is uniformly "Allocation is sellable." For **V1 / V2 / Direct Purchase**: operator action — operator-publish post-PO-commit (Admin Panel "activate"). For Direct Purchase this happens after the PO is committed (Module D) but does **not** wait for `SupplierPaymentCompleted`. **`SupplierPaymentCompleted` is financial-event-only** (no FSM role; E-emitted per Phase C R4; §11.3) — supplier-payment-failure on a Direct Purchase is a financial-recovery scenario (operator queue + customer refund), not an Allocation-FSM-state-mismatch; voucher issuance can no longer occur on a DRAFT Allocation by construction. Validation: allocation in DRAFT. State: → ACTIVE. Side-effects: Module S Offer-publication surfaces become valid; Cart Holds, voucher issuance, sales proceed.

#### §5.3.3 Visibility mutation

Inputs: allocation reference; new `visibility` (`CLUB_ONLY ↔ DISCOVERY_ONLY`); new `commercial_terms` (renegotiated at flip-time, DEC-076); actor role. Validation: allocation in DRAFT or ACTIVE; new value is the opposite of current (no-op rejected); **anti-orphan on the unsold portion only** — the issued portion stays bound to the original commercial relationship; the flip applies to the not-yet-issued remainder (full repurposing of an allocation with issued vouchers uses the **close-and-spawn pattern**). `commercial_terms` flips concurrently with `visibility` (typically `percent_of_selling_price` ↔ `fixed_per_unit`). State: `visibility` + `commercial_terms` updated; `state` unchanged. Side-effects: sibling-aggregation keys re-evaluate; Module S re-validates / retires Offers whose surface no longer matches (consumes `AllocationVisibilityChanged`). **Atomic**: the flip + commercial-terms update + cascading Offer-retirement signals fire as a single business transaction (no partial state). *(This relisting-unsold-club-stock-to-Discovery path is a load-bearing manual-first operator lever — ratified Q5 KEEP.)*

#### §5.3.4 `qty` mutation (the anti-orphan rule)

Inputs: allocation reference; new `qty` (delta or absolute); actor role. Validation — the **anti-orphan rule** (canonical):

> **Allocation `qty` cannot decrease below the count of vouchers already issued against the allocation.** A decrease that would orphan customer-held vouchers is rejected at the operation level. Decreases above the issued count are legal; below it are not.

Increases are unconstrained at the qty level; downstream side-effects:
- **Club allocations**: a `qty` increase for a Hero Package allocation cascades to Module K's Hero Package Capacity Invariant (Module K §13) — the producer may approve up to N additional waitlisted applicants. Waitlist-conversion priority is **producer-discretionary at launch** (DEC-069; no automatic FIFO — deferred, §14).
- **Non-Hero club allocations**: more vouchers may issue from existing eligible Members.
- **Discovery allocations**: more inventory available via Discovery Offers; no waitlist.
- **Direct Purchase allocations** *(documented-but-not-exercised — deferred, §3.2)*: a capacity increase implies a follow-on PO to the supplier (Module D); the existing `state = ACTIVE` covers the new units' sellability with no FSM re-trigger (DEC-183).

State: `qty` updated; `state` unchanged. Events: `AllocationCapacityIncreased` or `AllocationCapacityDecreased`. **Mid-year `qty` mutability is the capacity-adjustment signal Module K's Hero Package invariant consumes** (Phase C item G — hard cross-module contract; both sides KEPT).

#### §5.3.5 `commercial_terms` updates

Inputs: allocation reference; new `commercial_terms {shape, value}`; actor role. Validation: allocation in DRAFT or ACTIVE; shape/value well-formed; **anti-orphan (commercial-terms variant)** — an update cannot *disadvantage* already-issued vouchers' subsequent sell-through retroactively (same-shape change favouring the producer is admitted; disadvantaging is rejected; a cross-shape transition is admitted only if at-least-as-favourable to the producer for already-issued vouchers; BR-A-CommercialTerms-AntiOrphan in §10.2). A `commercial_terms` flip concurrent with a `visibility` flip (§5.3.3) is the typical CLUB ↔ DISCOVERY pattern (atomic in that case). State: `commercial_terms` updated; `state` unchanged. Side-effects: Module D PO line reads the new terms at next settlement event; Module S pricing surfaces re-render (consumes `AllocationCommercialTermsChanged`). **The per-constituent `C_i` lineage is preserved across updates — the seam the deferred 5%/settlement reads (Phase C item E).**

#### §5.3.6 Counterparty assignment / change

Inputs: allocation reference; new `supplier_id` (or null to clear); actor role. Validation: allocation in DRAFT or ACTIVE; if populated, an `active` SupplierProducerLink between the new Supplier and the Producer must exist in Module D; set / change / clear all admitted. State: `supplier_id` updated; `state` unchanged. Side-effects: Module D PO routing reads the updated `supplier_id` at next PO issuance / prospectively on the next PI / PO cycle; existing POs under the prior counterparty are not retroactively re-routed (the post-supplier-payment Direct-Purchase case is operationally unusual, captured in audit without retroactive PO modification — admitted-with-constraint).

#### §5.3.7 Recall trigger

Inputs: allocation reference; recalled qty (≤ `qty − issued`); destination (return-to-Producer); actor role. Validation: allocation in ACTIVE or CLOSED; recalled qty ≤ unsold portion; operator-initiated via Admin Panel (DEC-090 + DEC-083; producer-initiated path is the deferred Producer-Portal write UI). State: no direct state transition; the recall is recorded as an operation signalling Module D. Side-effects: emits `AllocationRecallTriggered` (allocation, recalled qty, destination, trigger source); Module D records `ReverseInboundEventRecorded` (DEC-090); the recalled qty is logically removed from the available pool (optionally committed via a §5.3.4 qty decrease); composes with the unsold-stock-handling paths (recall / Discovery relist via §5.3.3 / hybrid).

**The recall is event-recorded at launch.** Full reverse-inbound mechanics (reverse 3-gate QC, cost-basis unwind, partial-recall UX, recall-dispute path, automated return-shipment carrier coordination, reverse-discrepancy paths) are **already deferred** per Module D §17 — carried verbatim (§14), not re-cut. Matches D15 (manual recall).

#### §5.3.8 Sub-pool rebalance + non-serialization opt-out toggle

Inputs: allocation reference; new `qty_to_serialize` / `qty_non_serialized`; new `non_serialized_offer_admitted`; actor role. Validation per §7 (partition invariant; post-issuance per-sub-pool floor). State: sub-pool numerics + opt-out flag updated; `state` unchanged. Side-effects: Module S Offer-publication validation re-runs; Module B serialization workflow reads the updated partition. Events: `AllocationSubPoolRebalanced` and/or `AllocationNonSerializedOptOutChanged`.

#### §5.3.9 Close (ACTIVE → CLOSED)

Inputs: allocation reference; actor role; optional close reason. Validation: allocation in ACTIVE. State: → CLOSED. Side-effects: Module S Offer-publication surfaces become invalid; published Offers are retired (Module S consumes `AllocationClosed`); in-flight vouchers continue their lifecycle; settlement events for V1/V2 may still fire against vouchers issued before close.

#### §5.3.10 Retire (CLOSED → RETIRED)

Inputs: allocation reference; actor role; optional retire reason. Validation: allocation in CLOSED; all in-flight state resolved (no vouchers awaiting redemption / shipment; no settlement events pending; for V1/V2, all settlement against issued vouchers recorded). State: → RETIRED (terminal). Side-effects: preserved as an audit / historical-reporting reference; no further operations or events.

---

## §6 Split-Allocation Realisation (DEC-076)

NewCo realises visibility-split producer commitments as **separate Allocation rows per visibility**. One producer commitment of N units exposing M to club + (N−M) to Discovery materialises as two sibling Allocation rows:

- One `CLUB_ONLY`, `qty = M`, `commercial_terms` per the club relationship (typically `percent_of_selling_price`, 12.5%/87.5%, DEC-010).
- One `DISCOVERY_ONLY`, `qty = (N−M)`, `commercial_terms` per the Discovery relationship (typically `fixed_per_unit` cost `C`, DEC-032, or a shared percent per DEC-092).

Each row carries exactly one pricing-relationship structure; the pricing decision-maker per row is unambiguous (one row, one decision-maker). **Visibility is 2-value at launch** (`BOTH` dropped — conceptually inconsistent under the row-per-visibility model; future-DEC reactivates if a use case emerges — deferred, §14).

**Sibling-link**: no explicit FK at launch; aggregation via shared keys `producer_id + product_reference + sourcing_model + creation_timestamp` (+ `supplier_id` where populated). The Admin Panel may render a "create commitment" wrapper that auto-spawns N sibling rows; the data layer always sees N rows. **Mid-life rebalancing across siblings**: edit `qty` per sibling (§5.3.4 anti-orphan); or mutate visibility on a single allocation (§5.3.3 — the unsold portion flips, issued vouchers stay bound); full repurposing uses close-and-spawn. **Settlement aggregation is per row** (the producer's statement may carry two rows for one conceptual commitment of 100; the Module E statement render aggregates by shared keys — settlement engine deferred, D19). **The shared-key lineage is the per-constituent seam the deferred OC 5%/settlement reads (Phase C item E).**

---

## §7 Sub-Pool Partition Mechanics (DEC-080)

Each Allocation carries an explicit numeric partition between serialized and non-serialized sub-pools, controlled by `non_serialized_offer_admitted` + `qty_to_serialize` / `qty_non_serialized`.

**Partition invariant**: `qty_to_serialize + qty_non_serialized = qty` at all times. **Default**: `non_serialized_offer_admitted = FALSE` (serialization-by-default) → `qty_to_serialize = qty`, `qty_non_serialized = 0`. **Opt-out**: producer / NewCo ops can flip to TRUE at creation or mid-life (§5.3.8). Some producers reject NFC on principle; the flexibility is admitted for **all** allocations (including club), **per-allocation, not per-producer**.

**Partition mutation rules**: pre-issuance — rebalance freely subject to the invariant; post-issuance — rebalance only on the not-yet-issued portion of each sub-pool (`qty_to_serialize` cannot drop below issued serialized-backed vouchers; `qty_non_serialized` cannot drop below issued non-serialized-backed vouchers).

**Cross-module reads**: Module S Offer-publication validation reads `non_serialized_offer_admitted` (an Offer setting `serialization_type = NON_SERIALIZED` must be backed by an Allocation with the flag TRUE); Module B consumes the partition numerics to drive the serialization pipeline — only `qty_to_serialize` units enter the NFC/NFT pipeline; `qty_non_serialized` units skip it. **D12-neutral seam:** NFT is KEPT-but-decoupled (D12) — if the on-chain workstream slips past launch, the partition still functions (the non-serialized path ships without on-chain provenance), and the **non-serialized sub-pool ATP is the load-bearing floor at launch** (Phase C item G/J — the floor does not depend on the decoupled NFT workstream). `serialization_type` derived as in §4.1.

### §7.1 Sub-pool overselling rule — Layer 1 of the two-layer no-overselling guard **(FLOOR — Phase C floor chain 1 / item G)**

The **two-layer no-overselling guard** (Q-CL-5 + DEC-185/187/196). Module A's allocation-pool layer is **Layer 1**; Module B's physical-inventory layer is **Layer 2**. Both layers must pass at hold placement / voucher issuance, strongly consistent at the transactional boundary; either failure rejects the hold. **Layer 1 is universal; Layer 2's sale-gate is sourcing-model-scoped (below — MVP-DEC-027).**

- **Layer 1 — Module A allocation-pool layer** (DEC-099): `qty − issued ≥ 0` per allocation. Module A counts vouchers issued; rejects issuance crossing the `qty` ceiling. **Self-contained, Module A's own — no change at the spec layer.**
- **Layer 2 — Module B physical-inventory layer**: `physical_in_storage − reserved − quarantined − under_adjustment ≥ 0` per allocation (Module B's StockPosition aggregated view is the source of truth).

**Layer-2 sale-gate scope (sourcing-model-scoped — MVP-DEC-027).** Layer 2 gates the **sale** only where the allocation's sellable stock is **warehouse-resident**: `passive_v2` always; `direct_purchase` once physically received (pre-receipt Direct-Purchase sales proceed on Layer 1 alone — DEC-081; the arm idles at launch, Phase C item I / §14). For **`passive_v1`** (per-order inbound — BMD §3.7/§5.1: stock stays at the producer until a customer order), the sale is guarded by **Layer 1 alone, always**: no StockPosition exists at sale time, and stock arriving per-order is already committed to issued vouchers — Layer-2 ATP never constrains a `passive_v1` hold/issuance, including after a per-order receipt (scoping by pool-existence would wrongly re-block the allocation once its first committed units arrive). Physical receipt gates **shipment**, never the sale: `InboundEventPhysicallyAccepted` → Module C's shipment gate + in-transit redemption-block ("in transit; ETA X" — §11.6; DEC-081 decoupling; Phase C item K — the voucher-before-physical-receipt window). **No physical protection is lost**: every unit that physically exists stays guarded — Layer 2 at sale for warehouse-resident stock; for arrived `passive_v1` units the committed-inventory protection (§11.5.2, Q-CL-6) and Module C's no-oversell-at-pick read apply unchanged.

**Sub-pool composition**: the two layers compose **per sub-pool** at hold placement / voucher issuance. A SERIALIZED-offer voucher line is rejected if `atp_serialized` < requested qty; a NON_SERIALIZED-offer voucher line is rejected if `atp_non_serialized` < requested qty. **Cross-sub-pool fungibility is NOT admitted at hold placement** — the partition is enforced strictly. The per-sub-pool granularity is required because the sub-pool partition (§7) is kept. **For `passive_v1` (Layer-1-alone scope above) the per-sub-pool check runs against Module A's own sub-pool availability (sub-pool `qty` minus sub-pool issued vouchers — the §7 partition), not Module B ATP.**

The discipline is the architectural payoff: without Module B's physical layer, inventory loss (drift, miscount, breakage) surfaces only at fulfilment; the two-layer guard surfaces it at hold placement, so the Customer never gets a Voucher backed by phantom physical stock. **For `passive_v1` the phantom-stock risk cannot arise at sale time — there is no pool yet to drift; the physical-integrity protections attach from first per-order receipt (§11.5.2 committed-inventory protection + Module C's shipment gate and no-oversell-at-pick).** The full B-side ATP-feed contract is at §11.5 / §11.5.1.

**Build-sequencing (Phase C item G / Q5 — a Phase-E flag, not a cut).** Layer 1 (Module A, build-phase 3) depends on Layer 2 (Module B, build-phase 5) being **integration-ready at the integrated launch** — these are build phases within one coherent launch (no piecemeal handoff), not a launch-staging. The floor is whole at the integrated launch; the build workplan must sequence Module B's floor artefacts (Layer-2 push, InboundBatch, StockPosition, per-sub-pool ATP) to be ready by then. Carried to the Phase-E re-estimate.

---

## §8 Layered Breakability — Module A's Layer 2 Role

NewCo inherits Crurated v17's three-layer breakability model (cited in Module 0 §7). Three orthogonal layers, each owned by a different module:

- **Layer 1 — possible case configurations** (Module 0 PIM §7.1): the **Product Variant** carries an optional whitelist of Case Configurations admissible per Format. A cataloging-level statement of *possibility*; it does not by itself make a case unbreakable.
- **Layer 2 — producer breakability** (Module A — this PRD): the Allocation carries an optional per-`case_config` producer declaration that an OWC must ship intact.
- **Layer 3 — commercial unbreakable** (Module S): the Offer carries an optional commercial-unbreakable boolean.

**The effective rule** (Module 0 §7.4): `effective_unbreakable = Layer 2 (producer) OR Layer 3 (commercial)` — either layer saying unbreakable is sufficient; **Layer 1 does not contribute** (it is a possibility whitelist, not a constraint). Resolved per voucher-line (allocation + Case Configuration + Offer triple); the single contract every downstream module reads.

**Module A's Layer 2 specifics** (DEC-060): `producer_breakability` keyed by `case_config` (one Allocation may carry multiple per-case-config declarations; absent declarations default to breakable). **Layer-1 upper-bound check**: any declaration must reference a Case Configuration in the **Product Variant**'s Layer 1 whitelist (Module 0 §7.1); out-of-whitelist declarations rejected at creation. **Set-time**: at creation (mid-life addition admitted while no vouchers issued). **Immutability post-first-voucher**: once the first voucher issues, `producer_breakability` is immutable (distinct from `qty` mutability). **Domain event**: pre-first-voucher changes emit the audit-only `AllocationProducerBreakabilityDeclared` (case_config + declaration value; rare). **Module S Layer 3 read at Offer publication**: Module S reads Layer 2 and computes `effective_unbreakable`; Layer 3 **cannot downgrade** Layer 2 (an Offer cannot mark a case breakable when the bound Allocation's Layer 2 says non-breakable).

---

## §9 Eligibility Gates and the Sanctions-Blind Boundary **(FLOOR — Phase C floor chain 2)**

Module A is **sanctions-screening-blind** (DEC-071). The substantive sanctions-screening enforcement point at NewCo is **order completion in Module S** (S.15); Module A does not gate voucher issuance or allocation operations on `Customer.sanctions_status`. Allocation lifecycle and operations proceed independently of any Customer's screening state.

The rationale (DEC-071 + Module K §9.3): Module S blocks any non-`passed` Customer from completing an order; pre-completion stages (cart hold, voucher pre-issue under operator preview) do not gate on sanctions. **KYC at Customer level** (Module K §9.1) is similarly enforced at order completion (Module S) via the unified Hold mechanism. **Module A's role is purely allocation-state + operations + events; the Customer-side eligibility gate is downstream.** *(This is the mirror of Module K §9.3 — Module K + Module A are sanctions-blind by design; the read-API tuple is exposed by K, the enforcement is at the downstream surface. Floor chain 2: K screens + maintains state → S enforces at order completion → C OFAC at destinations → E sanctions/Hold re-read at charge.)*

**Producer-side activation gates that DO apply to Module A** (the floor's upstream expression):

- **PR active gate**: an allocation cannot be created against a `draft` / `retired` Product Reference (Module 0 §5.4 cascade).
- **Producer active + KYC-cleared gate**: the `producer_id` must reference an `active` Producer whose KYC is **cleared** — `verified` or `not_required` (Module K §4.4); KYC `pending` or `rejected` blocks (existing allocations under a Producer whose KYC is later revoked remain active, but new allocations cannot be created until KYC clears again). **No allocation under a producer whose KYC has not cleared** — the floor's upstream KYC expression.
- **SupplierProducerLink active gate**: when `supplier_id` is populated, the link between the Supplier and the Producer must be `active` (Module D, DEC-087).

Module A's allocation-operations gate is **upstream of and disjoint from** Module D's PO-issuance two-level gate (DEC-094, which reads ProducerAgreement state at PO time) — Module A admits its operations without reading ProducerAgreement state (DEC-077 orthogonality).

---

## §10 Business Rules and Invariants

Load-bearing rules, prefixed `BR-A-{Domain}-NN`. Tech-implementation enforcement (application-layer vs schema-layer) is downstream (DEC-073). *(Naming cascade applied to the catalog-identity rules: §10.1, §10.7.)*

### §10.1 Identity and uniqueness
- **BR-A-Identity-1**: every Allocation row carries a unique opaque allocation identifier; no business attributes form the identifier.
- **BR-A-Identity-2**: `producer_id` is always populated (DEC-082 two-FK pattern); `supplier_id` is optional.
- **BR-A-Identity-3**: the **Product Reference** (wine-display alias *Bottle Reference*) is always populated; references an `active` PR in Module 0 PIM at allocation creation.

### §10.2 Operations and mutability
- **BR-A-Mutability-1 (anti-orphan, qty)**: `qty` cannot decrease below the count of vouchers already issued (DEC-079).
- **BR-A-Mutability-2 (anti-orphan, visibility)**: a visibility flip applies to the unsold portion only; issued vouchers stay bound to the original commercial relationship (DEC-076).
- **BR-A-Mutability-3 (anti-orphan, commercial-terms)**: a `commercial_terms` update cannot disadvantage already-issued vouchers' subsequent sell-through retroactively.
- **BR-A-Mutability-4 (sourcing-model immutability)**: `sourcing_model` is immutable post-creation; changes require retire + new-allocation-create.
- **BR-A-Mutability-5 (producer-breakability immutability post-first-voucher)**: `producer_breakability` is immutable once the first voucher issues.

### §10.3 Sub-pool partition
- **BR-A-SubPool-1 (partition invariant)**: `qty_to_serialize + qty_non_serialized = qty` at all times.
- **BR-A-SubPool-2 (post-issuance partition floor)**: neither sub-pool can decrease below its issued-voucher count.
- **BR-A-SubPool-3 (opt-out admissibility)**: `non_serialized_offer_admitted` is admissible across all `visibility × sourcing_model` combinations.

### §10.4 Layered breakability (Layer 2)
- **BR-A-Breakability-1 (Layer 1 upper-bound)**: every `producer_breakability` declaration must reference a Case Configuration in the **Product Variant**'s Layer 1 whitelist (Module 0 §7.1).
- **BR-A-Breakability-2 (effective rule placement)**: the `effective_unbreakable` rule is computed at sale time across Layer 2 (this Module) and Layer 3 (Module S); Module A does not compute the effective rule.

### §10.5 Lifecycle
- **BR-A-Lifecycle-1 (FSM monotonicity)**: forward-only — DRAFT → ACTIVE → CLOSED → RETIRED.
- **BR-A-Lifecycle-2 (Activation trigger — harmonized, DEC-183)**: DRAFT → ACTIVE is **operator-publish post-PO-commit** uniformly across V1 / V2 / Direct Purchase; the trigger is operator-initiated; ACTIVE = "sellable." **`SupplierPaymentCompleted` does not drive the FSM** (it is a financial event; E-emitted per Phase C R4; §11.3).
- **BR-A-Lifecycle-3 (orthogonality to ProducerAgreement)**: ProducerAgreement transitions do not auto-cascade onto Allocation state (DEC-077).
- **BR-A-Lifecycle-4 (Retire requires resolved in-flight state)**: cannot transition CLOSED → RETIRED while in-flight state remains.

### §10.6 Counterparty
- **BR-A-Counterparty-1 (Producer ≠ Supplier separation)**: the two-FK pattern operationalises Module K's separation (DEC-067/082).
- **BR-A-Counterparty-2 (SupplierProducerLink active gate)**: when `supplier_id` is populated, the link must be `active` (read at creation + counterparty-change).

### §10.7 Cross-module dependency
- **BR-A-CrossModule-1 (PR active gate at creation)**: allocation creation requires the **Product Reference** to be `active` in Module 0.
- **BR-A-CrossModule-2 (Producer active + KYC gate at creation)**: allocation creation requires `producer_id` to be `active` and KYC-cleared (`verified` or `not_required`) in Module K. *(Floor — the upstream KYC expression.)*
- **BR-A-CrossModule-3 (sanctions-blind)**: Module A operations do not gate on `Customer.sanctions_status` (DEC-071); the enforcement point is order completion in Module S. *(Floor chain 2.)*
- **BR-A-CrossModule-4 (`SupplierPaymentCompleted` has no FSM role)**: Module A takes no FSM action on `SupplierPaymentCompleted`; activation is operator-publish post-PO-commit (BR-A-Lifecycle-2). *(The event is E-emitted and consumed by Module D + Module B per Phase C R4 — Module A is neither its emitter nor a load-bearing consumer.)*

---

## §11 Cross-Module Contracts

Every cross-module read and event-flow Module A participates in. Per DEC-074 the contracts are described in NewCo prose. *(Naming cascade applied to the Module 0 reads + consumed-event names; Module A's own `Allocation*` names unchanged.)*

### §11.1 Module 0 (PIM) — read
- **Product Variant + Product Reference identity**: read at allocation creation and on operations requiring identity validation (e.g. the breakability Layer-1 upper-bound check reads `Product Variant.possible_case_configs`, Module 0 §7.1).
- **PR `active` state**: read at allocation creation as a gate (BR-A-CrossModule-1).
- **Module 0 lifecycle events** (`ProductReferenceActivated/Retired`, `ProductMaster*/ProductVariant*`): consumed **read-on-demand** at the moment of an allocation operation's validation; Module A does not subscribe at run-time. *(Renamed from `BottleReference*/Wine*` — naming cascade; payload semantics identical.)*

### §11.2 Module K (Parties) — read + observe
- **Producer `active` + KYC-cleared state**: read at allocation creation (BR-A-CrossModule-2).
- **Supplier `active` state**: read at allocation creation when `supplier_id` is populated (no SupplierAgreement entity at launch, DEC-084).
- **ProducerAgreement state**: **NOT read by Module A's operations** — read by **Module D at PO issuance** (DEC-094); Module A is decoupled (DEC-077).
- **Module K lifecycle events** (`ProducerActivated`, `ProducerRetired`, `ProducerAgreementTerminated`, …): Module A does not auto-cascade (DEC-077); operator-initiated per-allocation actions are surfaced in the Admin Panel for ops-discretionary handling.

### §11.3 Module D (Procurement / Inbound) — emit + observe

This is the load-bearing supply-side cross-module contract.

- **Module A emits**:
  - `AllocationCreated` — Module D may consume to surface allocations as PI / PO candidates.
  - `AllocationActivated` — fires on DRAFT → ACTIVE uniformly via operator-publish post-PO-commit (DEC-183).
  - `AllocationCapacityIncreased` — Module D may consume for a follow-on PO on a Direct Purchase capacity increase *(documented-but-not-exercised — Direct Purchase deferred)*.
  - `AllocationCounterpartyChanged` — Module D may consume to update PO routing prospectively (existing POs not retroactively re-routed).
  - `AllocationRecallTriggered` — Module D consumes to record `ReverseInboundEventRecorded` (DEC-090).
  - `AllocationVisibilityChanged`, `AllocationCommercialTermsChanged`, `AllocationSubPoolRebalanced`, `AllocationNonSerializedOptOutChanged`, `AllocationProducerBreakabilityDeclared`, `AllocationClosed`, `AllocationRetired` — observed for audit / reconciliation; not load-bearing for Module D's own state machines.
- **`SupplierPaymentCompleted` — no FSM role; not a Module A consumer (Phase C R1 + R4).** The event is **financial-event-only** (DEC-183 / R1 — no Allocation-FSM role) and is **emitted by Module E** on payment clearing, consumed by **Module D** (settle/close the PO) + **Module B** (inventory `ownership_flag` PRODUCER→NEWCO) per Phase C R4 (the corrected emitter — the cut-sheets' "Module D emits" framing is superseded). **Module A is neither its emitter nor a load-bearing consumer**; allocation activation is operator-publish post-PO-commit (BR-A-Lifecycle-2). *(This is a coherence touch for the re-baseline — Module A owns no RECONCILE, but its incidental `SupplierPaymentCompleted` references are aligned to the ratified E-emits contract; naming/contract only, zero behaviour change for Module A.)*

#### §11.3.1 The Direct Purchase activation chain *(documented-but-not-exercised — Direct Purchase deferred, §3.2 / Phase C item I)*

The corrected (DEC-183) chain a Direct Purchase Allocation *would* follow when Direct Purchase is re-enabled post-launch:

1. NewCo ops creates the Allocation in DRAFT (§5.3.1).
2. NewCo ops creates a ProcurementIntent in Module D (§4).
3. Module D issues + commits a PO against the PI (PO ownership = NEWCO, derived from `sourcing_model = direct_purchase`; DEC-085 / N3).
4. NewCo ops **operator-publishes** (Admin Panel "activate") → DRAFT → ACTIVE + `AllocationActivated` (DEC-183 — does **not** wait for payment).
5. Module S Offer-publication surfaces become valid; Cart Holds, voucher issuance, sales proceed.
6. Module D processes supplier payment; **Module E emits `SupplierPaymentCompleted`** on clearing (Phase C R4) — a financial event with no Module A FSM role.
7. Module D's inbound flow proceeds independently; physical receipt fires `InboundEventPhysicallyAccepted`; Module C gates physical shipment for vouchers issued against this allocation ("in transit; ETA X" until receipt — DEC-081 decoupling).

**Seam (P1):** the `direct_purchase` enum value + the uniform operator-publish FSM are retained; this chain re-activates additively with zero Module A rework. The substantive Direct-Purchase build is Module D's.

### §11.4 Module S (Offer / Cart / Checkout) — downstream consumer
- **Offer-publication validation**: Module S validates at publication — Offer `surface` matches Allocation `visibility` (single-visibility-per-row, DEC-076); Offer `serialization_type` aligns with `non_serialized_offer_admitted` + sub-pool (DEC-080); Layer 3 cannot downgrade Layer 2 (Module 0 §7.4).
- **Pricing read**: Module S reads `commercial_terms {shape, value}` at pricing-surface render (DEC-092). For `percent_of_selling_price`: settlement-per-unit = `value% × selling_price`. For `fixed_per_unit`: settlement-per-unit = `value`; NewCo gross margin = `selling_price − value`.
- **Hero Package designation** lives at Module S Offer level, **not** at Module A (Module 0 §3.7). Module A reads no Hero-Package-specific attribute; the backing Allocation is a normal Allocation (typically `CLUB_ONLY`, `percent_of_selling_price` 12.5%, `qty` = the Club's annual member capacity). **Module K's Hero Package Capacity Invariant (Module K §13) reads Module A's Allocation `qty` at runtime** — the hard cross-module contract (Phase C item G; both sides KEPT).
- **The deferred multi-producer composite-Offer construct (D7) is Module S's**; Module A holds the N single-producer constituent Allocations (the seam, §3.1).
- **Module S consumes** `AllocationActivated`, `AllocationVisibilityChanged`, `AllocationCapacityIncreased/Decreased`, `AllocationCommercialTermsChanged`, `AllocationClosed/Retired`.

### §11.5 Module B (Inventory Authority + Digital Provenance) — bidirectional contract **(FLOOR — floor chains 1 + committed-inventory)**

Module B owns inventory-ledger authority (InboundBatch, SerializedBottle, Case, StockPosition, QuarantineRecord, Stocktake, the inventory-adjustment workflow, the receiving physical-match check) + digital provenance (NFC, NFT — **decoupled per D12**, Bottle Page, recovery chain). The contract is **bidirectional**:

- **Module B reads from Module A**: `non_serialized_offer_admitted`, `qty_to_serialize`, `qty_non_serialized`, derived `serialization_type` — drives NFC + NFT on serialized stock; non-serialized stock has its inventory-ledger home at the InboundBatch counter level (no digital-provenance state). Plus Module A lifecycle/operations events (§12).
- **Module A reads from Module B** (DEC-187): Module A is a downstream consumer of Module B's per-sub-pool ATP push events; Module A maintains a strongly-consistent ATP cache per allocation (§11.5.1); hold placement reads the cache and validates against StockPosition strongly consistent at the transactional boundary. Module A also consumes `InventoryShortfallDetected` (DEC-190) for the shortfall workflow (§11.5.2).
- **Neither edits the other's state** — the contract is event-based. Module A emits `VoucherCancelled` as the release primitive Module B consumes (DEC-099 + Q-CL-6).

#### §11.5.1 ATP cache contract — Module A maintains a strongly-consistent cache per allocation (DEC-187) **(FLOOR contract; mechanics are tech, Q4)**

Module A maintains a **strongly-consistent ATP cache per allocation**, per-sub-pool (`atp_serialized` + `atp_non_serialized`), sourced from Module B's inventory events. The cache is the load-bearing surface for hold placement at the §7.1 two-layer guard — Layer 1 reads `qty − issued` from Module A's own state; Layer 2 reads the cached ATP. **The cache serves the §7.1 Layer-2 sale-gate scope (MVP-DEC-027): for `passive_v1` allocations it never gates hold placement (Layer 1 alone — §7.1); it populates from the allocation's first per-order receipt and serves the physical-integrity surfaces (§11.5.2, Module C).**

**The product-spec contract (FLOOR — KEPT):** hold placement validates **both** layers (within the §7.1 Layer-2 sale-gate scope — `passive_v1` holds validate Layer 1 alone, MVP-DEC-027), strongly consistent at the transactional boundary, **per sub-pool**, with **no cross-sub-pool fungibility**; if the cache is stale beyond tolerance, the hold is **rejected** with a reconciliation reason and the cache reconciles before subsequent holds proceed; the cache reconciles against Module B's authoritative StockPosition view at three triggers — **cold start** (rebuild from StockPosition for every active allocation), **outage recovery** (re-read + rebuild for affected allocations), and **reconciliation tick** (periodic background confirmation).

**Tech-implementation — out of scope (DEC-073 / cut-sheet Q4):** the cache **mechanics** — push-vs-pull, the literal reconciliation cadence, the tolerance-window thresholds, and the latency KPIs (ATP-push end-to-end target, hold-placement p99, storefront staleness) — are the dev team's call and are **not a product-spec cut** for this exercise. The PRD names the *contract* (both layers validated, strongly consistent, per sub-pool); the mechanism is downstream. *(Phase C item G/H confirmed: no Module-B-side workflow sophistication inflates Module A's ATP-consumer surface beyond the floor; the D16 workflow-depth review + the A↔B build-sequencing are CONSISTENT — see §7.1.)*

#### §11.5.2 `InventoryShortfallDetected` consumer + the `VoucherCancelled` release primitive (DEC-190 + DEC-099 + Q-CL-6) **(FLOOR — committed-inventory protection)**

Module A consumes Module B's `InventoryShortfallDetected` when an inventory-adjustment proposal at Module B would reduce committed inventory below outstanding vouchers. The event is Module B's short-circuit on the §7.1 guard: at proposal-validation time Module B rejects the proposal upfront and emits the shortfall event; **the proposal cannot proceed until Module A `VoucherCancelled` first releases the commitment** (Q-CL-6).

**Shortfall workflow at Module A**: on consuming `InventoryShortfallDetected`, Module A (1) surfaces the shortfall to NewCo Operations via the Admin Panel (affected allocation + proposed adjustment scope + shortfall delta), and (2) Operations decide a resolution path — **Substitute** (escalate to Module S `VoucherSubstitutionExecuted`, DEC-104 — operator-driven at launch, full automation deferred, §14), **Refund** (escalate to a customer refund, Module S / DEC-108), or **Cancel** (escalate to Module S `VoucherCancelled`, DEC-099 — releases the commitment so the Module B-side adjustment proceeds). **`VoucherCancelled` is the load-bearing committed-inventory-protection release primitive** that keeps Module A's commitment ledger + Module B's physical ledger consistent (applies identically to NS stock). Module B is a downstream consumer of `VoucherCancelled`.

### §11.6 Module C (Fulfilment) — downstream consumer
- **Sellability gate vs shipment gate** (DEC-081 decoupling): Module S reads Module A `state = ACTIVE` for sellability; Module C reads Module D `InboundEventPhysicallyAccepted` for the relevant qty as the shipment gate. The two gates fire independently; Module C surfaces "in transit; ETA X" on vouchers awaiting physical receipt (carrier-ETA-precision deferred, D17 — admin-estimate at launch; the V1-per-order window survives the Direct-Purchase deferral — Phase C item K).
- **Late binding**: Module C resolves voucher → physical-bottle binding at shipment time, reading Allocation context (sub-pool partition; counterparty for chain-of-custody).

### §11.7 Module E (Finance) — downstream consumer
- **Module E reads**: `commercial_terms` at settlement-event time; `producer_id` / `supplier_id` for settlement routing; `ProducerAgreement.settlement_cadence` (Module K) for cadence-driven statements.
- **Module E does not edit Module A state.** **The settlement engine is deferred (D19) — operator-run first cycle(s); the engine builds after.** Module A preserves the per-constituent lineage the deferred OC 5% Discovery share + producer settlement read (the capture is whole at launch; the computation defers — Phase C item E). Module A takes **no accounting position** (DEC-072) — Module E records financial events; Xero decides GL.

---

## §12 Domain Events

Module A emits a versioned set of domain events; per DEC-073 payload field-by-field listings are out of scope (the catalogue lists names + one-line business-signal semantics). Every event carries the standard audit envelope: opaque event id, allocation id reference, emission timestamp, actor identity, and **`actor_role: producer | newco_ops`** (DEC-083 — `newco_ops` for every write at launch, §3.3). **Module A's own `Allocation*` event names are category-neutral — unchanged by the cascade.**

### §12.1 Lifecycle events
- **`AllocationCreated`** — on row creation. Carries `producer_id`, optional `supplier_id`, Product Reference, `sourcing_model`, `visibility`, `qty`, `commercial_terms`, sub-pool partition, `non_serialized_offer_admitted`, `producer_breakability`. Audit; Module D may list candidate allocations.
- **`AllocationActivated`** — on DRAFT → ACTIVE (operator-publish post-PO-commit uniformly, DEC-183). Consumer: Module S (Offers can publish).
- **`AllocationClosed`** — on ACTIVE → CLOSED. Consumer: Module S (retire Offers).
- **`AllocationRetired`** — on CLOSED → RETIRED. Terminal; audit anchor.

### §12.2 Operations events
- **`AllocationVisibilityChanged`** — §5.3.3; carries prior/new `visibility` + prior/new `commercial_terms` (atomic flip). Consumer: Module S.
- **`AllocationCapacityIncreased`** — §5.3.4 increase; carries prior/new `qty`. Consumer: Module D (follow-on PO for Direct Purchase capacity increase — *not exercised at launch*); Module K (Hero Package Capacity Invariant re-evaluation, club allocations only).
- **`AllocationCapacityDecreased`** — §5.3.4 decrease; carries prior/new `qty`. Consumer: Module S (Offer qty re-derive).
- **`AllocationCommercialTermsChanged`** — §5.3.5; carries prior/new `commercial_terms`. Consumer: Module S (pricing re-render); Module E (settlement read at next event).
- **`AllocationCounterpartyChanged`** — §5.3.6; carries prior/new `supplier_id`. Consumer: Module D (prospective PO routing).
- **`AllocationSubPoolRebalanced`** — §5.3.8; carries prior/new sub-pool numerics. Consumer: Module B (serialization workflow); Module S (Offer-validation re-run).
- **`AllocationNonSerializedOptOutChanged`** — §5.3.8; carries prior/new `non_serialized_offer_admitted`. Consumer: Module S.
- **`AllocationProducerBreakabilityDeclared`** — §8 (creation-time or pre-first-voucher); carries case_config + declaration value. Audit; rare.
- **`AllocationRecallTriggered`** — §5.3.7; carries recalled qty, destination, trigger source. Consumer: Module D (`ReverseInboundEventRecorded`, DEC-090).

> **Editorial note (carried from the v0.1 acceptance G-1).** The composite framing `AllocationSerializationPlanChanged` (= `AllocationSubPoolRebalanced` + `AllocationNonSerializedOptOutChanged`) mentioned in some v1.1 cross-cuts is **not** a separate launch event — the two atomic events above are the contract. Left as an editorial item (not a scope decision).

### §12.3 Consumed by Module A (Module B inventory events + the release primitive)
- **Sub-pool ATP push events** (DEC-187 — each sources an ATP delta to Module A's cache, §11.5.1): `BottleStateChanged` (SerializedBottle lifecycle — `atp_serialized` deltas), `InventoryAdjusted` (adjustment workflow, DEC-190 — per-bottle/per-batch deltas), `OwnershipTransitioned` (PRODUCER→NEWCO — audit-only delta; does **not** gate sellability), `BottleQuarantined` / `BottleQuarantineResolved` (quarantine entry/exit — excluded from ATP while quarantined), `StocktakeReconciled` (stocktake-derived adjustments), NS-pool counter mutations (`atp_non_serialized` deltas).
- **`InventoryShortfallDetected`** (DEC-190) — Module B's short-circuit; Module A runs the §11.5.2 shortfall workflow.
- **`VoucherCancelled`** — Module A **emits** this (DEC-099); listed here because it is the release primitive Module B **consumes** to let a previously-blocked adjustment proceed (Q-CL-6).
- **`SupplierPaymentCompleted`** — **not a Module A FSM input** (DEC-183 / R1); E-emitted, D+B-consumed (R4) — §11.3. Module A takes no FSM action.

### §12.4 Naming, ordering, versioning
**Naming**: `Allocation*` prefix (category-neutral — unchanged by the cascade); lifecycle `*Created/*Activated/*Closed/*Retired`; operations semantic verbs. **The consumed Module 0 events rename `BottleReferenceActivated/Retired → ProductReferenceActivated/Retired`, `Wine* → Product*`** (naming cascade — payload semantics identical). **Ordering**: cascading events within a single business transaction are emitted in causal order; consumers tolerate eventual-consistency arrival order across transactions. **Versioning**: events are schema-versioned; consumers evolve independently within a major version with backward-compat.

---

## §13 Module Boundary Notes — what Module A does NOT do

- **Offer entity, customer-facing pricing surfaces, cart hold, checkout, voucher issuance against a customer order, sell-through event** — Module S. *(The deferred multi-producer composite-Offer construct, D7, is Module S's — §3.1.)*
- **ProcurementIntent, PurchaseOrder, InboundEvent, ConsignmentReceipt, ReverseInboundEvent, SupplierProducerLink, supplier-payment execution, landed-cost computation, DiscrepancyResolution** — Module D. *(The substantive Direct-Purchase deferral, D11, is Module D's — §3.2.)*
- **NFC tag application, NFT minting, predecessor / successor recovery chain, serialized bottle identity, Bottle Page rendering** — Module B *(NFT decoupled, D12 — Module A's partition is D12-neutral)*.
- **Inventory-ledger authority** — InboundBatch, SerializedBottle inventory state, Case + integrity FSM, StockPosition, QuarantineRecord, Stocktake, inventory-adjustment workflow, receiving physical-match check — **Module B**. Module A's allocation-pool layer (`qty − issued ≥ 0`) is **Layer 1 of the two-layer guard**; Module B's physical layer is Layer 2; Module A does not own the physical layer or the ledger entities.
- **Pick / pack / dispatch, shipment, late binding, cellar render, in-transit display, delivery confirmation** — Module C.
- **Producer / supplier settlement-payment execution, INV1 / INV2 invoicing, payment-method records, GL treatment** — Module E + Xero (DEC-072). *(Settlement engine deferred, D19 — operator-run first.)* Module A emits business-signal events; Module E records; Xero decides treatment.
- **Outbound communication delivery** — per the Module K §14.9.1 purpose split (the ERP email service sends operational mail; HubSpot sends marketing / lifecycle — MVP-DEC-035); HubSpot reads Module A events downstream; Module A sends no communications and integrates no mail provider.
- **Customer-side eligibility (KYC, sanctions, marketing consent, Holds)** — Module K + Module S at order completion. **Module A is sanctions-blind** (DEC-071 + §9).
- **ProducerAgreement state lifecycle / supersession chain** — Module K (decoupled, DEC-077).
- **PO-issuance two-level gate** — Module D (DEC-094).
- **Active consignment, drop-shipping, B2B credit terms, liquid voucher resolution, CruTrade P2P trading, AgencyAgreement / third-party-owner consignment, `unsold_handler_policy` field, Hero Package designation as a Module A attribute** — all OUT / not-a-Module-A-surface at launch (carried verbatim — §14). The `direct_purchase` enum value is retained-but-idle (the seam, §3.2).

---

## §14 Deferred set & post-launch roadmap pointers (MVP)

**Module A takes ~0 net-new spec deferrals in the MVP strip** (it is KEEP-in-full). The genuine launch-scope reductions that touch Module A are **seamed, not cut** (the backend / supply primitive is whole); plus v1.1's already-deferred set is carried **verbatim**. All feed [`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md) (which extends `greenfield/03-qa/qa.deferred.md`). **Do not re-cut the already-deferred items.**

### §14.1 Net-new MVP deferrals (seamed — restore additively)

| # | Deferred item | Seam preserved (P1) | Restores with |
|---|---|---|---|
| §14.1a | **Direct Purchase *use*** (no `direct_purchase` allocations at launch — D11 / Phase C item I) | The `direct_purchase` **enum value** + the **uniform operator-publish FSM** (DEC-183) are retained; the §11.3.1 activation chain + the capacity-increase follow-on-PO note are documented-but-not-exercised. **Zero Module A rework to re-enable.** | The Direct-Purchase set (A enum-idle + **Module D**'s PI/PO/supplier-payment/inbound flow + B/E/S idles), restored together as a coordinated set when a deal needs it. |
| §14.1b | **Producer-Portal allocation-operation write UIs** (L-PP — every operation operator-driven via the Admin Panel; Module A retains zero producer writes) | **No backend capability is cut** — DEC-083 admin-parity is a backend contract; the operator path is functionally complete. The producer-facing write UIs build on the same backend. Producer Portal **read + full reporting (D23) is KEPT.** | The post-launch Producer-Portal write-UI workstream (the broader Admin-Panel → Producer-Portal self-serve buildout). |

### §14.2 v1.1 already-deferred / future-flex set (carried verbatim — do not re-cut)

Active consignment + AgencyAgreement (DEC-011/017); SupplierAgreement entity (DEC-084 — supplier terms live on `commercial_terms`); partial PO settlement (OQ-20); reverse-inbound full mechanics (reverse 3-gate QC, cost-basis unwind, partial-recall UX, recall-dispute path, automated return-shipment carrier coordination, reverse-discrepancy paths — Module D §17 / OQ-12/18, DEC-152); drop-ship (OQ-17); liquid sales (BMD §13.4); CruTrade P2P (BMD §13.5); multi-warehouse (OQ-16); `unsold_handler_policy` field (Q-AD-17); `parent_commitment_id` sibling FK; `BOTH` visibility reactivation (DEC-076); auto-FIFO waitlist conversion (DEC-069/079); auto-cascade on agreement transition (DEC-077); substitution full automation (DEC-104). Each retains its v1.1 re-introduction seam.

> **Tri-module restoration coherence (Phase C item N).** Module A participates in two coordinated restorations: **Discovery composites (D7) = S + A + 0** (A keeps the per-constituent single-Allocation + two-FK + per-constituent `C_i`, §3.1) and **Direct Purchase (D11) = A + D + B + E + S** (A keeps the enum + uniform FSM, §3.2/§14.1a). It also keeps the **settlement-engine (D19)** recording seam (the per-constituent lineage, §11.7) and the **NFT on-chain (D12)** NS-fallback seam (the sub-pool partition, §7). No KEPT Module A item depends on a deferred one.

---

## §15 Naming-cascade application (Phase C item A)

Module 0 v0.3-MVP §18 is the **source-of-truth** name table; this section records **how those names land in Module A** — and **what does NOT rename.** The change is **naming/contract only — zero behaviour change** (every event carries the same business signal; BR and PR denote the same key).

**What renames in Module A (the PR-referencing / Module-0-event-consuming prose only):**

| Touchpoint | v1.1 prose | v0.3-MVP prose | Wine-display alias retained |
|---|---|---|---|
| §1, §3, §4.1 catalog identity | "**Bottle Reference (BR)**" (the catalog identity the allocation commits to) | "**Product Reference (PR)**" | Bottle Reference / BR |
| §3, §4.1, §8 Layer-1 read, §11.1 | "**Wine Variant**.possible_case_configs" (Layer-1 whitelist) | "**Product Variant**.possible_case_configs" | Wine Variant |
| §3 cross-reads | "**Wine Master**" | "**Product Master**" | Wine Master |
| §10.1 / §10.7 BR-A-Identity-3 / CrossModule-1 | "**Bottle Reference** is always populated; references an `active` **BR**…" | "**Product Reference** is always populated; references an `active` **PR**…" | Bottle Reference / BR |
| §11.1 / §12.4 consumed Module 0 events | `BottleReferenceActivated/Retired`, `Wine*` | `ProductReferenceActivated/Retired`, `Product*` | — |

**What does NOT rename in Module A (the carve-outs — Phase C item A):**
- **Module A's own names are unchanged.** `Allocation` (entity); `Allocation{Created,Activated,Closed,Retired}`, `Allocation{VisibilityChanged,CapacityIncreased,CapacityDecreased,CommercialTermsChanged,CounterpartyChanged,SubPoolRebalanced,NonSerializedOptOutChanged,ProducerBreakabilityDeclared,RecallTriggered}`, `VoucherCancelled`; the attributes `sourcing_model`, `visibility`, `qty`, `commercial_terms`, `non_serialized_offer_admitted`, `qty_to_serialize`, `qty_non_serialized`, `serialization_type`, `producer_breakability`, `producer_id`, `supplier_id` — all **category-neutral, unchanged**.
- **Module B's / Module C's physical-unit names are unchanged** (`SerializedBottle`, `InboundBatch`, `StockPosition`, …) — wine-display naming (the physical unit is a bottle for the `WINE` product type).
- **Module D's / Module E's own event names** consumed-or-referenced by Module A are unchanged (`SupplierPaymentCompleted`, `InboundEventPhysicallyAccepted`, `ReverseInboundEventRecorded`, …) — category-neutral.
- **"Bottle Reference" is retained everywhere as a wine-display alias** for Product Reference. The Allocation's PR-referencing attribute keeps `bottle_reference` as its retained wine-display alias (the structural concept is the Product Reference; the literal field naming is tech-implementation, DEC-073).

**Rule of thumb:** rename only the PR-referencing / Module-0-event-consuming prose; keep Module A's own `Allocation*` names and every sibling's own names alone.

---

## §16 v1.1 inheritance & MVP re-baseline trace (audit appendix)

This appendix preserves the audit trail of Module A v0.3-MVP against its **frozen v1.1 predecessor** ([`../../reference/v1.1/01-prd/Module_A_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_A_PRD_v0.2.md), whose §14 carries the v17 §2.1/§3.x/§4 trace + §14.1 the Stage-8/Phase-C cascade trace) and the **ratified cut-sheet** + **Phase C reconciliation**. The load-bearing prose is the body above (DEC-074); this trace is for audit / diff.

> **Section-numbering note.** Module A is **KEEP-in-full with no structural entity insertion**, so **§1–§13 keep their v1.1 numbering** (the acceptance doc's PRD §-anchors — §5.x FSM/operations, §6 split, §7/§7.1 sub-pool/Layer-1, §8 breakability, §9 sanctions, §10.x BRs, §11.x cross-module, §12.x events, §13 boundaries — therefore remain valid against this PRD). Only **§0** is prepended (MVP framing). The trailing sections are repurposed: **§14** = NEW (the MVP deferred set); **§15** = NEW (naming-cascade application); **§16** = NEW (this trace; the v17 inheritance trace lives in the frozen v0.2 §14/§14.1); **§17** = cross-references (carried from v1.1 §15). v1.1's Appendix A (Wave 2 Divergence Summary vs v17) lives in the frozen v0.2 — not reproduced here (DEC-074: the body restates the substance).

| v0.3-MVP section | v1.1 (v0.2) anchor | Cut-sheet / Phase C | MVP disposition |
|---|---|---|---|
| §0 MVP scope at a glance | — (new) | cut-sheet §1; Phase C §1 | NEW — Phase D framing; KEEP-in-full + naming cascade verdict. |
| §1 Module Purpose | v0.2 §1 | cut-sheet A.1/§3.4 | KEEP; PR/Product-Variant cascade; + supply-primitive + floor framing. |
| §2 Personas | v0.2 §2 | cut-sheet §3.3; A-Q3 | KEEP; + P2 operator-surface + L-PP zero-producer-writes (write UIs deferred; reporting KEPT). |
| §3 Architecture (single entity) | v0.2 §3 | cut-sheet §3.1–§3.4 | KEEP; + §3.1 D7 forwarding, §3.2 Direct-Purchase defer, §3.3 L-PP, §3.4 cascade. |
| §4 Entity Model | v0.2 §4 | cut-sheet A.1–A.11 | KEEP all attributes; cascade on PR/Variant; `direct_purchase` enum retained-but-idle; `qty`/`commercial_terms` cross-module contracts flagged. |
| §5 Lifecycle & Operations | v0.2 §5 | cut-sheet A.12–A.17; A-Q5 | KEEP; DEC-183 uniform activation; mutation set KEPT (Q5); §11.3.1 chain corrected + documented-but-not-exercised; all ops operator-driven. |
| §6 Split-Allocation Realisation | v0.2 §6 | cut-sheet A.7; Phase C item E | KEEP; shared-key lineage = the per-constituent settlement seam. |
| §7 Sub-Pool Partition | v0.2 §7 | cut-sheet A.8–A.10; D12 | KEEP; D12-neutral NS-fallback seam. |
| §7.1 Layer-1 / two-layer guard | v0.2 §7.1 | cut-sheet A.18/A.19; Phase C item G/floor 1 | KEEP — FLOOR; + build-sequencing Phase-E flag. |
| §8 Layered Breakability (Layer 2) | v0.2 §8 | cut-sheet A.11 | KEEP; cascade on the Product-Variant Layer-1 read. |
| §9 Sanctions-Blind Boundary | v0.2 §9 | cut-sheet A.22/A.23; Phase C floor 2 | KEEP — FLOOR; mirrors Module K §9.3 (K + A sanctions-blind by design). |
| §10 Business Rules | v0.2 §10 | cut-sheet §3.4 | KEEP all; BR-A-Identity-3 / CrossModule-1 → Product Reference; BR-A-CrossModule-4 aligned to E-emits (R4). |
| §11 Cross-Module Contracts | v0.2 §11 | cut-sheet §4; Phase C items E/G/I + R1/R4 | KEEP; cascade on Module 0 reads; `SupplierPaymentCompleted` no-FSM + E-emits aligned; ATP-cache contract KEPT, mechanics = tech (Q4); `VoucherCancelled` release FLOOR. |
| §12 Domain Events | v0.2 §12 | cut-sheet A.24 | KEEP ~22-event contract; `Allocation*` unchanged; consumed Module 0 events renamed; G-1 composite-event editorial note. |
| §13 Module Boundary Notes | v0.2 §13 | cut-sheet A.25/A.26 | KEEP; + D7-forwarded / Direct-Purchase-deferred / D12-decoupled notes; already-deferred set → §14. |
| §14 Deferred set & roadmap | v0.2 §13 + Acceptance §7 | cut-sheet §2 (DEFER rows); Phase C item N | NEW — the net-new seamed deferrals (Direct-Purchase use, L-PP write UIs) + the v1.1 already-deferred set carried verbatim. |
| §15 Naming-cascade application | — (new) | Phase C item A; Module 0 §18 | NEW — the cascade application + carve-outs. |
| §16 v1.1 & MVP trace | v0.2 §14/§14.1 (v17 trace) | — | NEW — this audit appendix (the v17 trace lives in the frozen v0.2). |

Notation: *KEEP* = the v1.1 substance is restated in full NewCo language without semantic change; *cascade* = naming-only rename (Product Reference / Variant / Master + consumed Module-0 events), non-behavioural; *seamed defer* = the *use* is deferred with the backend/seam retained; *NEW* = Phase-D framing with no direct v1.1 predecessor.

---

## §17 Cross-references

- **v1.1 predecessor (frozen)** — [`../../reference/v1.1/01-prd/Module_A_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_A_PRD_v0.2.md). The source spec carried in full; never edited (plan R4). Its §14/§14.1 carry the v17 + Stage-8/Phase-C cascade trace; its §15 the v1.1 cross-references (DECs, qa.modA, BMD v0.6); its Appendix A the v17 divergence summary.
- **Ratified cut-sheet** — [`../01-triage/Module_A_CutSheet_v0.1.md`](../01-triage/Module_A_CutSheet_v0.1.md). §2 inventory (scope), §3 module-specific changes (D7 forwarding / D11 Direct-Purchase / L-PP / naming cascade), §5 acceptance delta, §6 the five ratified Qs.
- **Phase C reconciliation** — [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md). Item A (naming cascade), item E (OC 5% capture — A preserves the per-constituent lineage), item G (two-layer guard + build-sequencing), item I (Direct Purchase deferred), item N (tri-module restorations), R1 (`SupplierPaymentCompleted` financial-event-only), R4 (E-emits — Module A aligned, owns no RECONCILE), §6 floor chains 1 (no-overselling) + 2 (KYC/sanctions) + committed-inventory.
- **Naming source of truth** — [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18 (the canonical name table + carve-outs). Applied here, not re-derived.
- **Settled siblings (the cross-module contracts A shares)** — [`Module_K_PRD_v0.3-MVP.md`](Module_K_PRD_v0.3-MVP.md) §13 (Hero Package Capacity Invariant reads A's `qty`) + §9.3 (the sanctions-blind boundary / order-completion gate) · [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §3.8 (Composite SKU KEPT — the D7 seam) + §7 (Layer-1 breakability whitelist A's Layer 2 bounds against).
- **MVP decisions register** — [`../04-decisions/MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md) (the thin index → authoritative docs).
- **Method + dials** — [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (P1/P2, the floor) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (D10 sub-pools KEEP; D11 Direct Purchase defer; L-PP one producer write).
- **Testable companion** — [`../03-acceptance/Module_A_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_A_Acceptance_v0.3-MVP.md).
- **Sibling v0.3-MVP PRDs** — Module 0 + Module K (written first; the catalog identity + Producer/Supplier A reads). Next in the cascade: **Module D** (the Direct-Purchase defer's substantive locus + R1 + the E-emits consumer side) → S → B / C → E, then the Admin-Panel PRD + Architecture.

---

*End of Module A PRD v0.3-MVP — Phase D re-baseline. **Verdict: KEEP-in-full + the naming cascade; net Module-A-layer spec deferrals ~0.** The supply primitive (single Allocation + two-FK + per-constituent `commercial_terms`), the two-layer no-overselling floor (Layer 1 + per-sub-pool ATP + the `VoucherCancelled` release primitive), the Hero Package `qty` contract, the per-constituent settlement lineage, and the sanctions-blind boundary are all KEPT whole. The headline D7 multi-producer-composite cut is **forwarded to Module S**; Direct-Purchase *use* + the L-PP producer-write UIs are **seamed-not-cut** (the backend is whole); Module A owns no RECONCILE (its incidental `SupplierPaymentCompleted` references are aligned to the ratified E-emits contract, R4). **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E.*
