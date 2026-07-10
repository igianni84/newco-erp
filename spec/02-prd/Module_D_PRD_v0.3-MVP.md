# NewCo ERP — Module D PRD (Procurement and Inbound) — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP scope of Module D)
- **Date**: 2026-06-08
- **Status**: **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; **nothing is promoted to `handoff/` until Phase E** (the single coherent handoff). Module D is **KEEP-heavy on the core-loop inbound floor + the uniform procurement flow + the financial-event recording (the D19 seam)**, with **ONE genuine in-module deferral — D11 Direct Purchase *use*** (thin, because v1.1 pre-factored the arm), plus the L-PP producer-write UIs (seamed, no backend cut). Module D **owns RECONCILE R1** (DEC-183 `SupplierPaymentCompleted` financial-event-only) and is the **consumer side of RECONCILE R4** (`SupplierPaymentCompleted` is **E-emitted**, D-consumed — the cut-sheet's "D-emits" framing is superseded).
- **Owner**: Paolo (decides). Claude recommends.
- **Testable companion**: [`../03-acceptance/Module_D_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_D_Acceptance_v0.3-MVP.md) — the MVP-scoped acceptance criteria (re-cut from the v0.1 DRAFT per the cut-sheet §5 delta; the MVP re-cut + the original validation land together).
- **Predecessors / inputs** (the canonical record governs where this PRD is terse):
  - [`../../reference/v1.1/01-prd/Module_D_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_D_PRD_v0.2.md) — the **frozen v1.1 predecessor** (RELEASED 2026-05-09; Stage 8 / Phase C close). This v0.3-MVP carries its inbound floor + procurement spine + financial-event recording **at full fidelity**, defers the Direct-Purchase *use* + the L-PP write UIs with seams, lands R1 + the R4 consumer side + N1/N3/item F, and applies the naming cascade; `greenfield/` is never edited (plan R4).
  - [`../01-triage/Module_D_CutSheet_v0.1.md`](../01-triage/Module_D_CutSheet_v0.1.md) — the **ratified cut-sheet** (Paolo 2026-06-07). §2 feature inventory = the scope; §3 module-specific changes (D11 Direct-Purchase defer / DEC-183 reconciliation / D16 forwarding / D19 recording-seam / L-PP + naming cascade) = the rewrite instructions; §5 = the acceptance delta; §6 = the five ratified Qs. **⚠️ The cut-sheet predates the Phase C R4 flip — its D.24 still says "Module D emits `SupplierPaymentCompleted`." Where the cut-sheet and Phase C conflict on the emitter, Phase C R4 (E-emits) wins (§3.3).**
  - [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) — the **coherence gate** (RATIFIED 2026-06-07). **R1** (DEC-183 financial-event-only — Module D owns it, §5-R1/§2-B) + **R4** (E-emits `SupplierPaymentCompleted` — Module D is the consumer side, §2-C/§5-R4) + **N1** (D16 manual-first, item H) + **N3** (party naming) + **item F** (sale-vs-shipment title timing — resolves AMB-D-3) + **item I** (Direct Purchase deferred, confirmed no launch deal) + floor chain 1 (no-overselling — D's `InboundEventPhysicallyAccepted` creates B's InboundBatch).
  - [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18 — the **source-of-truth name table** for the cascade (applied here, not re-derived). [`Module_A_PRD_v0.3-MVP.md`](Module_A_PRD_v0.3-MVP.md) — the settled sibling (§3.2 the Direct-Purchase joint defer; §5.2/§11.3 the DEC-183 + E-emits framing this PRD mirrors; §4.1/§11.7 the per-constituent `commercial_terms` lineage D reads). [`Module_K_PRD_v0.3-MVP.md`](Module_K_PRD_v0.3-MVP.md) — §4.6 the ProducerAgreement settlement-cadence seam (the Level-1 gate + the deferred D19 read it); §4.4/§4.5 Producer / Supplier identity.
  - [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (method, P1/P2, the floor) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (D11 procurement variants: KEEP both consignment / DEFER Direct Purchase; D16 integrity core floor / workflows manual-first; D19 settlement defer; L-PP one producer write).
- **Methodology** (carried from v1.1; unchanged):
  - **DEC-072** — no accounting-policy positions. Module D **records** procurement / inbound **financial events** carrying business signals (`InboundEventCostFinalized`, `DiscrepancyResolutionRecorded`, `PurchaseOrderIssued`, …); **Module E records + Xero decides GL.** The prose may use accounting-domain terms descriptively. The sale-vs-shipment title-timing nuance (§3.5 / item F) names events only — **no GL position.**
  - **DEC-073** — product-spec layer only (entity concepts, business attributes, lifecycle states, business-meaningful enum values, domain-event names + business signals, module boundaries, invariants). Tech-implementation (column types, FK naming, nullability-as-constraint, indexing, API/payload, **the 5-WD SLA timer mechanics**, override-management UI) is the dev team's call and is out of scope.
  - **DEC-074** — self-contained delivery document. Every entity is reintroduced in full NewCo language; a tech reader who has not read v1.1 can take this into the dev phase. The v1.1 inheritance trace (v17 §8 / §3.10 / §3.8 / v13 Stage 2.1/2.3) is preserved in the frozen v0.2 §19/§19.1; §20 here records the MVP re-baseline trace.
  - **MVP principles (plan §4.1):** **P1 — defer without burning bridges** (every deferred item names the seam that makes the post-launch build additive, and points to the roadmap); **P2 — admin-first, self-serve-later** (producer/back-office writes are operator-driven via the Admin Panel; consumer storefront exempt). *Module D retains **zero** producer writes at launch and **no backend capability is cut** (DEC-083 admin-parity) — §3.6.*

---

## §0 MVP scope at a glance

**Verdict: Module D is KEEP-heavy on the core-loop inbound floor + the uniform procurement flow + the financial-event recording (the D19 settlement seam), with ONE genuine in-module deferral — D11 Direct Purchase *use* — that is *thin* because v1.1 pre-factored the arm.** Module D (Procurement & Inbound) owns the core-loop **"bottle inbound"** step and is load-bearing on the supply side, so — like Module 0 / K / A — it stays near-whole. It is the **first Phase-D module to take a headline defer in-module** (0/K/A all forwarded their headline levers) **and the first to own a RECONCILE** (R1) and to be the **consumer side of another** (R4). But the honest finding (cut-sheet §1) is that even the in-module cut is small: DEC-093 (uniform flow) + DEC-183 (harmonized activation) already collapsed Direct Purchase into a parameterization, so the deferred surface reduces to **one operator action**. Four facts converge:

1. **The uniform procurement flow IS the D11 seam.** Per DEC-093 the flow `ProcurementIntent → PurchaseOrder → InboundEvent (PHYSICALLY_ACCEPTED + COST_FINALIZED) → optional ConsignmentReceipt (V2)` is **one shape for all sourcing models**, parameterized by *when PI fires* + *when PO issues*. There is no separate Direct-Purchase flow, FSM, or event set to remove. The *only* genuinely Direct-Purchase-exclusive surface is the **operator-initiated PI-creation path at allocation-creation time + its at-PO-creation timing branch** (§5/§12.3). Deferring Direct Purchase = not building that one operator surface at launch. **Genuine, but thin.**

2. **Module D carries a piece of the scope floor — the core-loop "bottle inbound" step (KEPT WHOLE).** The two-phase InboundEvent (`PHYSICALLY_ACCEPTED` + `COST_FINALIZED`, 5-WD SLA soft-alert) is the event that brings physical stock into NewCo custody: `InboundEventPhysicallyAccepted` **creates Module B's InboundBatch** (DEC-195 → the inventory ledger / the no-overselling floor — Phase C floor chain 1), **gates Module C shipment** (DEC-081), and the cost phase **feeds Module E**. The documents-side 3-gate QC (DEC-194 split: D = documents-in-order; B = physical-match), the landed-cost finalization + 4 categories, the receiving-party rule (Supplier always, DEC-088), and ConsignmentReceipt (V2 intake) are all floor or floor-adjacent — none heavier than the floor needs.

3. **Module D owns RECONCILE R1 and is the consumer side of RECONCILE R4** (naming/contract only — zero behaviour change):
   - **R1 (DEC-183) — Module D OWNS it.** The stale pre-DEC-183 prose calling `SupplierPaymentCompleted` Module A's Direct-Purchase DRAFT→ACTIVE activation trigger (v1.1 §1 "load-bearing Wave 2 contract," §3 FSM-trigger bullet, §12.3, §12.4) is reconciled to **financial-event-only** (activation is operator-publish post-PO-commit, uniform). Module A is already aligned (Module A PRD §11.3). (§3.2.)
   - **R4 (E-emits) — Module D is on the CONSUMER side. ⚠️ The trap.** `SupplierPaymentCompleted` **moves from Module D's *emitted* set to its *consumed* set: Module E emits it** on payment clearing (the payment executor; three-actor split DEC-119; symmetric with the customer-side `AirwallexChargeExecuted`); **Module D consumes it** → settle/close the PO. **D's other procurement financial events stay D-emitted.** This corrects the cut-sheet's D.24 "Module D emits." (§3.3.)
   - **R1 × R4 land together:** `SupplierPaymentCompleted` is **(a)** financial-event-only (R1, no FSM-activation role) **AND (b)** E-emitted / D-consumed (R4).

4. **D19 — recording KEPT (the seam); settlement *automation* is Module E's.** Module D **records** all procurement/inbound financial events (`InboundEventCostFinalized` + landed-cost categories, `DiscrepancyResolutionRecorded`, `ConsignmentReceiptRecorded`, `ReverseInboundEventRecorded`, `PurchaseOrderIssued`, `POIssuedUnderNonActiveAgreement`); the settlement **engine** (quarterly statement, payment execution, Xero GL) is **Module E's, deferred to operator-run** (D19). **The recording is the seam.** Net Module-D cut: **zero** (the automation defer lives in Module E). Partial PO settlement stays deferred (OQ-20, atomic per PO).

**The two reciprocal-cascade simplifications that *touch* Module D — both manual-first, integrity core KEPT (N1 / item H):**
- The Stage-8 reciprocal round-trips on Module D's side — auto-reopen InboundEvent into DISCREPANCY on Module B's `InboundBatchDiscrepancy` (§13.3); auto cost-basis reconciliation on `BottleQuarantineResolved` (§13.4) — are **manual-first at launch** (operator opens the discrepancy + records the resolution path within the 5-WD window), **identically to Module B's manual-first depth** (B decided this at Phase C in lockstep; D's KEEP-pending-B-review is discharged). **The integrity core is FLOOR, KEPT:** the two-phase InboundEvent, the documents-side 3-gate QC, the DISCREPANCY state, the 6-path resolution enum, the DEC-194 split, and the event consumers all stand; only the *automated round-trips* defer. *(Forward-consistency obligation: the Module B v0.3-MVP PRD must match this manual-first prose — flagged in the digest.)*

**The naming cascade (Phase C item A — the one mechanical change).** Module D applies Module 0's source-of-truth names (`Bottle Reference → Product Reference (PR)`; the `BR → Wine Master → Producer` deref → `Product Reference → Product Master → Producer`; the consumed Module-0 events `BottleReferenceActivated/Retired → ProductReferenceActivated/Retired`) to its **PI/PO line-composition + SupplierProducerLink-deref** prose (§5, §6, §10, §14.3). **Module D's own `ProcurementIntent*` / `PurchaseOrder*` / `InboundEvent*` / `ConsignmentReceipt*` / `ReverseInboundEvent*` / `SupplierProducerLink*` names are already category-neutral — unchanged.** "Bottle Reference" is retained as a wine-display alias. **Naming/contract only — zero behaviour change** (§19).

**The genuine launch-scope reductions — all seamed (P1):**
- **Direct Purchase *use* — DEFERRED at launch** (D11 / Phase C item I; confirmed at ratification — no launch deal). The deferred surface = the **operator-initiated PI-creation path + its at-PO-creation timing branch** (§5, §12.3). **Seam:** the uniform flow stays parameterized; the `trigger_source = operator_initiated` value, the `ownership = NEWCO`-at-issuance derivation, and the at-PO-creation timing rule are **retained-but-unexercised**; re-enable is purely additive. Joint defer with Module A (A keeps the `direct_purchase` enum + uniform FSM — settled). (§3.1.)
- **Producer-Portal procurement write UIs (L-PP) — DEFERRED.** Every Module D operation is **operator-driven via the Admin Panel** at launch; **Module D retains zero producer writes** (the one platform-wide retained producer write — membership approve/decline — is a Module K surface). **No backend capability is cut** (DEC-083 admin-parity is a backend contract). The producer-initiated recall UI + the deferred operator-initiated PI are the producer write surfaces deferred. Producer Portal **read + full reporting (D23) is KEPT.** (§3.6.)
- **v1.1's already-deferred set carried verbatim** to the roadmap with its existing re-introduction hooks (§18): full reverse-inbound mechanics (OQ-12/18, DEC-152); SupplierAgreement entity (DEC-084); partial PO settlement (OQ-20); drop-ship (OQ-17); active consignment + agency (DEC-011); B2B credit (DEC-068); liquid sales (BMD §13.4); multi-warehouse (OQ-16); PI batching optimisation for V1; per-supplier SLA override management. **Do not re-cut.**

**The floor pieces Module D holds (all KEPT, whole) — verified in composition by Phase C §6:**
- **The two-phase InboundEvent → Module B InboundBatch floor** (`InboundEventPhysicallyAccepted` creates the InboundBatch in `expected` state, DEC-195 — feeds the no-overselling Layer 2 / committed-inventory floor). *(Floor chain 1.)*
- **The documents-side 3-gate QC** (DEC-194 split: D = documents-in-order; B = physical-match) + the **DISCREPANCY state** + the **6-path resolution enum** (the integrity core; N1 keeps it, only the automated round-trips defer).
- **The PO-issuance two-level gate** (DEC-094 — L1 ProducerAgreement `active`; L2 Allocation sellable) + the operator-override surface (`POIssuedUnderNonActiveAgreement`) + the **SupplierProducerLink PO-line validation gate** (DEC-087).
- **ALL procurement/inbound financial-event recording** (the D19 seam) + **domain separation** (supplier payment vs customer-facing INV1/INV2, DEC-089) + **event-only framing** (DEC-072).

**The five ratified scope confirmations (cut-sheet §6, Paolo 2026-06-07):**
- **Q1 — Direct Purchase deferred at launch** (passive V1 + V2 only). Deferred surface = the operator-initiated PI-creation path + at-PO-creation timing; the uniform flow + retained enum values are the seam (re-enable additive). Deal-dependent — Module A unchanged either way. (§3.1.)
- **Q2 — DEC-183 reconciliation (R1).** `SupplierPaymentCompleted` reconciled to **financial-event-only** in the PRD + the affected acceptance criteria; Module A already aligned. (§3.2.)
- **Q3 — D16 receiving-workflow depth forwarded to the joint Module B review (now discharged — B decided manual-first in lockstep, Phase C item H).** Module D's side is floor + cheap interlocks; the integrity interlocks are not unilaterally cut; the seam is kept either way; the automated round-trips are manual-first at launch (N1). (§3.4.)
- **Q4 — all Module D operations operator-driven via the Admin Panel** at launch; producer write UIs deferred (esp. producer-initiated recall); zero producer writes; **no backend capability cut** (DEC-083 admin-parity); Producer Portal read + full reporting (D23) KEPT. (§3.6.)
- **Q5 — D19 settlement-recording KEPT (the seam); settlement automation is Module E's, deferred to operator-run; partial PO settlement deferred** (OQ-20, atomic per PO). (§3.7.)

---

## §1 Module Purpose

Module D is NewCo's authoritative registry for the **procurement and inbound** chain — the entities and events that record NewCo's commercial relationships with suppliers and producers (as commercial counterparties, not as content-identity sources), the procurement intents that signal demand against allocations, the purchase orders that operationalise those intents into financial commitments to suppliers, the inbound events that record physical receipt and cost finalisation at the Vinlock-operated warehouse, the consignment receipts specific to V2 passive consignment pre-positioned stock, the reverse-inbound events that record producer-initiated unsold-stock recalls, and the supplier-producer linkage entity that gates PO line items at issuance.

Module D's **load-bearing principle at NewCo launch**: a **uniform procurement flow shape across all sourcing models** (per DEC-093), parameterised by per-sourcing-model trigger and timing rules. The flow is `ProcurementIntent → PurchaseOrder → InboundEvent (PHYSICALLY_ACCEPTED + COST_FINALIZED) → optional ConsignmentReceipt (V2 only)` for every allocation regardless of `sourcing_model`; what differs is *when* PI fires (voucher-issuance-driven for V1/V2; operator-initiated for Direct Purchase) and *when* PO issues (producer-settlement-cadence for V1; sell-through-settlement-cadence for V2; at-PI-creation for Direct Purchase). DEC-093 explicitly rejects a separate Direct-Purchase flow shape; **this uniformity is the D11 seam** — Direct Purchase is a parameter set on the kept flow, deferred at launch (§3.1).

Module D is the **core-loop "bottle inbound" hinge** (MVP-plan §3): the two-phase InboundEvent is the step that brings physical stock into NewCo custody — `InboundEventPhysicallyAccepted` creates Module B's InboundBatch (DEC-195, the no-overselling floor), gates Module C shipment (DEC-081), and the cost phase feeds Module E. Module D **records** the procurement/inbound financial events the (deferred) Module E settlement engine consumes (the D19 seam); it takes **no accounting position** (DEC-072) — Module E records, Xero decides GL.

> **RECONCILE R1 (DEC-183) — landed here.** The v1.1 prose framed *"the cross-cutting Wave 2 cross-module contract is the event chain `SupplierPaymentCompleted` (this Module) → `AllocationActivated` (Module A) for Direct Purchase allocations."* **This is superseded.** Under **DEC-183**, allocation activation is **operator-publish post-PO-commit *uniformly* across V1 / V2 / Direct Purchase**; `SupplierPaymentCompleted` is a **financial event with no Allocation-FSM role** (§3.2). And under **Phase C R4**, `SupplierPaymentCompleted` is **emitted by Module E** (the payment executor) and **consumed by Module D** to settle/close the PO (§3.3) — it is no longer a Module D *emission*. Module A is already aligned (Module A PRD §11.3 / §10.5 BR-A-Lifecycle-2). **Naming/contract only — money moves identically; no behaviour change.**

Module D is **state + operations + events**. It records procurement / inbound state, governs the operations exposed via the **Admin Panel at launch** (operator-driven; Producer-Portal write UIs deferred — §3.6) under the DEC-083 parity contract, enforces business invariants (PO-line SupplierProducerLink validation gate per DEC-087; PO-issuance two-level gate per DEC-094; the InboundEvent two-phase split with 5-WD SLA), emits versioned domain events on every operation, and exposes read contracts downstream modules consume.

Module D does NOT do (boundaries unchanged — §17): Allocation entity / FSM / operations / sub-pool partition / layered-breakability Layer 2 (Module A — Module D consumes Allocation state); Offer / cart / checkout / voucher issuance / customer-facing INV1 / INV2 invoicing (Module S / Module E — supplier payment is domain-separated from customer invoicing, DEC-089); NFC / NFT / serialized-bottle identity / Bottle Page (Module B — serialization runs downstream of `InboundEventPhysicallyAccepted`); pick / pack / ship / late binding / cellar render / physical reverse logistics (Module C); producer/supplier settlement-payment execution / GL treatment / settlement-statement composition (Module E + Xero, DEC-072 — the settlement engine is deferred, D19); customer-side eligibility / Customer / Profile / Originating Club (Module K + Module S); ProducerAgreement state lifecycle (Module K — Module D reads it at the PO Level-1 gate).

---

## §2 Personas

Module D serves operator-facing roles for procurement and inbound workflows, plus cross-module readers / observers consuming Module D events. **At launch, every Module D write is operator-driven via the NewCo Admin Panel (P2 / L-PP — §3.6); the Producer-Portal write UIs are deferred.** The DEC-083 parity remains a backend contract; deferring the producer-facing write UIs cuts no backend capability.

- **Procurement Operator (Admin-Panel-side at launch; Producer-Portal-side write UI deferred per DEC-083 parity)**. Creates ProcurementIntents (the V1/V2 PI auto-fires at voucher issuance — system-triggered; the Direct-Purchase operator-initiated PI is **deferred**, §3.1); reviews PO issuance gates (DEC-094 two-level gate); operates the PO lifecycle (DRAFT → ISSUED → ACKNOWLEDGED → IN_TRANSIT → RECEIVED → CLOSED); coordinates with suppliers on shipment scheduling. **This is the launch write surface for all of Module D's procurement operations.**
- **Inbound Operator (Admin-Panel-side)**. Receives goods at the Vinlock warehouse; runs the documents-side 3-gate inbound QC (quantity / damage / matching) at `InboundEventPhysicallyAccepted`; opens DiscrepancyResolution events (and, at launch, **manually** opens the InboundEvent into DISCREPANCY on a Module B physical-match variance — N1, §3.4 / §13.3).
- **Cost Finalisation Operator (Admin-Panel-side)**. Within the 5-working-day SLA from `InboundEventPhysicallyAccepted`, finalises landed cost (base + transport / customs / insurance / other) and fires `InboundEventCostFinalized`.
- **Supplier-Relationship Operator (NewCo Ops, Admin-Panel-side)**. Creates SupplierProducerLink entries (Module D-owned per DEC-087); reads Supplier records (Module K §4.5 — Module D reads, does not own); monitors supplier performance.
- **Settlement Reviewer (Module E-side, cross-module reader)**. Reads procurement / inbound events at the settlement cadence to compose the producer / supplier settlement statements (BMD §3.10; ProducerAgreement `settlement_cadence`, Module K §4.6). **The settlement engine is deferred (D19) — the first cycles are operator-run; the recorded events are the seam.** Does not edit Module D state.
- **Producer Portal end-user (read-only on procurement-side reporting — KEPT)**. Producers see PO status against their own allocations (units shipped / in-transit / received) + settlement projections from the procurement-side data. **Full self-serve producer reporting (D23) is KEPT at launch; only the producer *write* surfaces are deferred.**
- **Compliance / Logistics Lead (cross-cutting)**. Handles the regulatory frame for inbound (excise, customs, bonded warehousing); opens `POIssuedUnderNonActiveAgreement` audit reviews when the operator-override surface fires (DEC-094 + DEC-091).

The cross-cutting **Admin Panel ↔ Producer Portal parity** (DEC-083) is captured in §5–§10 — every operation is exposable from both surfaces at the backend; every emitted event carries `actor_role` for audit. **At launch every write is `actor_role = newco_ops`** (the producer-facing write UIs are deferred, §3.6). Surface-specific UX is downstream tech work per DEC-073.

---

## §3 Architecture — Uniform Procurement Flow (DEC-093)

Module D's load-bearing pattern is the **uniform procurement flow** across all sourcing models per DEC-093. The flow shape is one entity chain:

```
ProcurementIntent → PurchaseOrder → InboundEvent (PHYSICALLY_ACCEPTED + COST_FINALIZED) → optional ConsignmentReceipt (V2 only)
                                                                                        ↘ optional ReverseInboundEvent (recall path)
                                                                                        ↘ optional DiscrepancyResolution (mismatch path)
```

This shape is **the same** for `passive_v1`, `passive_v2`, and `direct_purchase` allocations. What parameterises per sourcing model:

- **PI trigger** (when PI fires):
  - **V1 (passive consignment, customer-order-driven)**: PI auto-fires at voucher issuance (one PI per voucher in the simple case; batched per shipment in the optimised flow — batching is post-launch tuning, §18). **Exercised at launch.**
  - **V2 (passive consignment, pre-positioned)**: PI auto-fires at voucher issuance (referencing already-arrived stock). **Exercised at launch.**
  - **Direct Purchase**: PI is **operator-initiated** by NewCo ops at allocation-creation time. ***DEFERRED at launch (§3.1)*** — this operator-initiated PI-creation surface is the one genuinely Direct-Purchase-exclusive Module-D surface; the `trigger_source = operator_initiated` enum value is retained as the seam.

- **PO timing** (when PO issues):
  - **V1**: PO at producer-settlement-cadence per ProducerAgreement (Module K §4.6, DEC-070 — typically quarterly). A goods-movement PO (producer → Vinlock).
  - **V2**: PO at sell-through-settlement cadence per ProducerAgreement. A financial PO against pre-positioned inventory.
  - **Direct Purchase**: PO at PI-creation time (operator-initiated, full-amount-at-purchase). ***DEFERRED at launch (§3.1)*** — the at-PO-creation timing branch is part of the deferred arm; the timing parameterization is retained but unexercised.

- **Allocation activation trigger** (cross-module — Module A side, **harmonized per DEC-183 / R1**):
  - **V1 / V2 / Direct Purchase (uniform)**: allocation activation is **operator-publish post-PO-commit** (Module A operation; not driven by a Module D event). `SupplierPaymentCompleted` is **financial-event-only** — no FSM role (§3.2), and is **E-emitted / D-consumed** (§3.3). *(The v1.1 "Direct Purchase: Module D `SupplierPaymentCompleted` triggers Module A `AllocationActivated`" framing is superseded — R1.)*

The single procurement flow is detailed in §5 (PI), §6 (PO), §7 (InboundEvent), §8 (ConsignmentReceipt), §9 (ReverseInboundEvent); the per-sourcing-model parameterisation is summarised in §12; the PO-issuance gate in §11.

**Cross-module reads** (full contracts in §14; naming cascade applied to the catalog-identity reads):

- **Module A** (Allocation — the upstream supply primitive): Module D reads `Allocation.sourcing_model` (drives PO timing, DEC-086), `Allocation.commercial_terms` (drives PO line value computation, DEC-092 — the per-constituent `C_i` lineage the D19 settlement + the 5% Originating-Club share read), `Allocation.producer_id` / `Allocation.supplier_id` (counterparty routing, DEC-082), `Allocation.qty` (PI / PO units), `Allocation.state` (Level 2 of the PO-issuance gate, DEC-094). Module D records `ReverseInboundEventRecorded` triggered by Module A's `AllocationRecallTriggered` (DEC-090). **Module D no longer emits `SupplierPaymentCompleted` to Module A** — under R1 it has no FSM role; under R4 it is E-emitted (§3.3).
- **Module K** (Parties): Producer (`active` + KYC-cleared gate at PO line creation), Supplier (informal metadata at launch, DEC-084), ProducerAgreement `state` at PO issuance for Producer-counterparty POs (Level 1 of the two-level gate, DEC-094; Module K §4.6 KEEP-minimal — the D19 settlement-cadence seam).
- **Module 0** (PIM): **Product Reference** identity at PI / PO line composition (the procurement flow operates on PR-keyed line items per Module 0 §3.4; wine-display alias *Bottle Reference*); the `Product Reference → Product Master → Producer` deref at SupplierProducerLink validation (§10).
- **Module S** (Offer / Cart / Checkout): the voucher-issuance signal auto-fires V1/V2 PIs; `VoucherIssued` is the sell-through signal driving the PO PRODUCER→NEWCO **title** transition (item F, §3.5); `VoucherVoided` cancels a V1 PI (§14.4).
- **Module B** (Inventory): **downstream consumer** of `InboundEventPhysicallyAccepted` (creates InboundBatch, DEC-195) + `InboundEventCostFinalized` (cost-basis flip) + `ConsignmentReceiptRecorded`; **emits to Module D** `InboundBatchDiscrepancy` + `BottleQuarantineResolved` (the reciprocal cascades — manual-first at launch, N1, §3.4). Module B also consumes `SupplierPaymentCompleted` (from E) for the inventory `ownership_flag` PRODUCER→NEWCO transition (§3.3 / §3.5).
- **Module C** (Fulfilment): reads `InboundEventPhysicallyAccepted` as the shipment gate (decoupled from sellability, DEC-081).
- **Module E** (Finance): records Module D's procurement/inbound financial events + forwards to Xero (the deferred settlement engine, D19); **emits `SupplierPaymentCompleted`** on payment clearing, which Module D consumes (§3.3).

### §3.1 D11 Direct-Purchase deferral — lands in-module, but thin (cut-sheet §3.1; ratified Q1 / Phase C item I)

The master kickoff nominated D11 as Module D's headline cut — the first module where a headline defer lands in-module rather than being forwarded. The cut-sheet's finding — **confirmed at ratification (no launch deal)** — is that even this in-module cut is small:

1. **The "procurement arm" was already collapsed into a parameterization by v1.1.** DEC-093 locks **flow uniformity** ("No separate Direct Purchase flow or PI subtype in the data model"); DEC-183 **harmonized activation** to operator-publish-post-PO-commit across all sourcing models and demoted `SupplierPaymentCompleted` to financial-event-only. So there is **no separate Direct-Purchase flow, FSM, or event set to remove.**
2. **The deferred surface reduces to one operator action.** The *only* genuinely Direct-Purchase-exclusive Module-D surface is the **operator-initiated PI-creation path at allocation-creation time + its at-PO-creation timing branch** (§5, §12.3). The `ownership` enum (§6) is shared — `NEWCO` is still reached by V1/V2 at sell-through (the title transition, §3.5); only the `direct_purchase → NEWCO`-at-issuance derivation is unexercised. The PI / PO / InboundEvent entities, the 13-name event contract, the gates — all shared with V1/V2, all KEPT.
3. **The seam is intrinsic** (P1): the uniform flow stays parameterized; the `trigger_source = operator_initiated` value, the `ownership = NEWCO`-at-issuance derivation, and the at-PO-creation timing rule are **retained-but-unexercised**. Re-enabling Direct Purchase is purely additive — wire the operator PI-creation surface back on; nothing else changes. This is the exact mirror of Module A keeping the `direct_purchase` enum + uniform FSM as its (free) seam (Module A PRD §3.2).
4. **Joint defer with Module A — settled.** At launch no `direct_purchase` allocations are created (A) and no operator-initiated PI path is built (D); the joint seam is the uniform flow (DEC-093) + the retained enum values. Deal-dependent: if a launch deal needs Direct Purchase, Module A is unchanged and Module D re-enables the arm (all five of A/D/B/E/S idle in lockstep — Phase C item I).

**Verdict:** DEFER Direct Purchase *use* at launch (passive V1 + V2 only) — a **genuine but thin** in-module cut. The §12.3 / §12.4-equivalent Direct-Purchase activation chain is **documented-but-not-exercised** (§12.3, reconciled to DEC-183 + R4). Acceptance annotates the Direct-Purchase criteria not-exercised-at-launch (AC-D-J-3, AC-D-FSM-6, AC-D-XM-2, the AC-D-BR-CrossModule-1 Direct-Purchase-PI-on-DRAFT carve-out, the `operator_initiated` / `ownership=NEWCO`-at-issuance arms); the V1/V2 procurement-flow criteria (AC-D-J-1/J-2) stand.

### §3.2 RECONCILE R1 — DEC-183 `SupplierPaymentCompleted` financial-event-only (cut-sheet §3.2; ratified Q2 / Phase C §5-R1)

The Module D PRD v0.2 carries **stale pre-DEC-183 prose** describing `SupplierPaymentCompleted` as Module A's Direct-Purchase DRAFT→ACTIVE activation trigger ("the load-bearing Wave 2 cross-module contract," v1.1 §1; the §3 "Allocation FSM trigger" bullet; §12.3 "the canonical Direct Purchase activation trigger"; the §12.4 8-step chain). **DEC-183 superseded this:** ACTIVE = "sellable," reached by **operator-publish post-PO-commit *uniformly*** across V1 / V2 / Direct Purchase; `SupplierPaymentCompleted` is **financial-event-only with no FSM role**. Module D's §12.1/§12.2 (V1/V2) and §3's PI-trigger bullet already carried the corrected framing; only the Direct-Purchase passages were stale.

**Action (landed in this PRD — naming/contract only, no behaviour change):**
- §1 — the "load-bearing Wave 2 contract" framing removed (the RECONCILE box above).
- §3 — the "Allocation FSM trigger" bullet reconciled: activation is uniform operator-publish; no `SupplierPaymentCompleted` FSM trigger.
- §12.3 / §12.4 — the Direct-Purchase activation chain reconciled to DEC-183 (operator-publish; the payment event has no FSM role) **and** documented-but-not-exercised (Direct Purchase deferred, §3.1).
- §15.9 BR-D-CrossModule-4 — reframed (the event has no FSM role; it is E-emitted / D-consumed, §3.3).

Module A is already aligned (Module A PRD §11.3 / §10.5 BR-A-Lifecycle-2 / §10.7 BR-A-CrossModule-4). **With Direct Purchase deferred (§3.1) the chain is doubly moot at launch, but the reconciliation must still land so the retained seam is correct.**

### §3.3 RECONCILE R4 — `SupplierPaymentCompleted` moves from Module D's *emitted* set to its *consumed* set (E-emits / D-consumes) ⚠️ (Phase C §2-C / §5-R4)

This is the **single highest-risk reconciliation this PRD lands** — it **corrects the cut-sheet**. The cut-sheet (D.24) and the v1.1 PRD both have **Module D emitting `SupplierPaymentCompleted`**. Phase C ratification (Paolo Q2) exposed that **Module D has no independent trigger** — it would wait on Module E's confirmation that the payment cleared. **Payment execution is Module E's** (the Airwallex/Xero rails, DEC-014/028; the three-actor split DEC-119 assigns PAYMENT to E; symmetric with the customer-side `AirwallexChargeExecuted`, which E emits). The corrected contract is **E-emits.**

**The corrected contract (the precise pin):**
- **Module E emits `SupplierPaymentCompleted`** — when the supplier payment clears/confirms (E is the payment executor). **At launch:** when the operator records the manual supplier payment in E's finance surface (settlement is operator-run, D19 deferred). **Post-launch:** E's settlement engine. **Atomic per PO** (partial PO settlement deferred, OQ-20).
- **Module D consumes it** → settle/advance/close the PO (§6: the CLOSED transition). **Module D's *own* procurement financial events stay D-emitted, unchanged** — `InboundEventCostFinalized` (landed cost), `DiscrepancyResolutionRecorded`, `ConsignmentReceiptRecorded`, `ReverseInboundEventRecorded`, `PurchaseOrderIssued`, `POIssuedUnderNonActiveAgreement`. **Only the *payment-completion* event moves to E** (§16).
- **Module B also consumes it** (independently) → the inventory `ownership_flag` PRODUCER → NEWCO transition (Module B v0.3-MVP PRD; the bottle becomes NewCo-owned because NewCo has paid for it). *(This is distinct from the PO-level title transition — see §3.5 / N3.)*
- **Direct-Purchase no-op:** for `direct_purchase` the InboundBatch is `NEWCO` from creation → no PRODUCER→NEWCO transition; doubly moot (Direct Purchase deferred, §3.1).

**R1 × R4 land together:** `SupplierPaymentCompleted` is **(a)** financial-event-only (R1 — no Allocation-FSM-activation role) **AND (b)** E-emitted / D-consumed (R4). This PRD **moves `SupplierPaymentCompleted` out of Module D's emitted catalogue (§16.1) into its consumed catalogue (§16.4)**; the cut-sheets stay as the Phase B record (D.24 "D emits"), the v0.3-MVP PRD lands E-emits. **Naming/contract only — money moves identically; B's `ownership_flag` flip + the D19 recording seam are intact** (Module D's other procurement events unchanged; E owns the supplier-payment event directly because it executes the payment).

### §3.4 N1 — D16 receiving-workflow reciprocal cascades manual-first; integrity core KEPT (cut-sheet §3.3; ratified Q3 / Phase C item H)

The cut-sheet directed a delicate, joint-with-Module-B SIMPLIFY hunt on the receiving / DiscrepancyResolution surface, with the explicit caveat *"integrity core = floor; workflow sophistication = SIMPLIFY candidate … don't unilaterally cut the integrity interlocks."* **At Phase C, Module B decided the depth — KEEP the integrity core (FLOOR); SIMPLIFY the Stage-8 workflow automation → manual-first — in lockstep with Module D's KEEP-pending-B-review, which is now discharged** (item H). Module-D-side treatment:

1. **Module D's receiving surface is floor or cheap-interlock (KEPT WHOLE).** The two-phase InboundEvent (§7), the documents-side 3-gate QC (§7.1, DEC-194), the DISCREPANCY state (§13.3), and the 6-path DiscrepancyResolution **enum** (§13.1 — a cheap event vocabulary per DEC-072, not six automated workflows; cutting paths would lose Module E's cost-impact vocabulary) all stand. **The integrity core is FLOOR — KEPT.**
2. **The automated reciprocal round-trips are manual-first at launch.** At launch, the Stage-8 reciprocal cascades on Module D's side — **auto-reopen InboundEvent into DISCREPANCY on Module B's `InboundBatchDiscrepancy`** (§13.3) and **auto cost-basis reconciliation on `BottleQuarantineResolved`** (§13.4) — are handled by **manual operator discrepancy-handling** (the operator opens the discrepancy + records the resolution path manually within the 5-WD window), **identically to Module B's manual-first depth.** Module D's reciprocal side was always thin (observe one event, conditionally re-open / re-emit); manual-first means the operator drives that step rather than an automated round-trip.
3. **Seam (P1):** the InboundEvent two-phase split, the DISCREPANCY state, the 6-path resolution enum, the DEC-194 split, and the event consumers (`InboundBatchDiscrepancy`, `BottleQuarantineResolved`) are **all kept** — the automated round-trip is **additive** when Module B's physical-match / quarantine automation lands post-launch.

> **Forward-consistency obligation (flagged for the Module B session, artefact #6).** This PRD lands the D16 manual-first posture per the ratified Phase C item H decision (B decided manual-first; D's KEEP-pending-B-review is discharged). **The Module B v0.3-MVP PRD must match this manual-first prose** so the D↔B interlocks read consistently — flagged in this session's digest. *(The A↔B-style build-sequencing — B build-phase 5, D build-phase 3 — is a Phase-E re-estimate flag, not a cut: B's InboundBatch / physical-match must be integration-ready when D's inbound floor goes live; the floor is whole at the integrated launch — Phase C item G.)*

### §3.5 Item F + N3 — sale-vs-shipment title timing + party-naming clarity (Phase C item F / §5-N3)

Two precision notes the re-baseline pins so the two ownership ledgers are never conflated. **Both are naming/contract only — no accounting position (DEC-072).**

**Item F — the PO-level title-timing nuance (resolves AMB-D-3).** The v1.1 prose drove the PO PRODUCER→NEWCO **title** transition "via `SellThroughRecorded` from Module S" (§6 ownership-derivation rule; §14.4). **There is no separate `SellThroughRecorded` event** — Module S resolved the names at Phase C: **`VoucherIssued` is the sell-through (customer-sale) signal** driving Module D's PO PRODUCER→NEWCO ownership transition; **`VoucherShipped` is available for a shipment-keyed title leg.** This PRD names those events (§6, §12, §14.4) and **takes no accounting position** — whether the title-transfer timing drives a revenue/COGS treatment is a Xero/GL decision, folded into the deferred settlement recording (D19).

**N3 — two distinct ownership ledgers, same party.** The system carries **two** PRODUCER→NewCo ownership ledgers, keyed to **different signals** at **different moments**:

| Ledger | Owner | Enum / flag | Transition keyed to |
|---|---|---|---|
| **PO-level title** | **Module D** (this PRD, §6) | `ownership` 3-value enum `PRODUCER \| NEWCO \| THIRD_PARTY` (DEC-085 — `NEWCO`) | the **sale/shipment signal** (`VoucherIssued` sell-through; `VoucherShipped` for a shipment-keyed leg — item F) |
| **Inventory `ownership_flag`** | **Module B** (consumer) | `ownership_flag` `PRODUCER → NEWCO` (DEC-185; `NEWCO` per MVP-DEC-028) | **`SupplierPaymentCompleted`** (E-emitted; the payment moment — R4, §3.3) |

The PO-level title (Module D) and the inventory `ownership_flag` (Module B) **denote the same real-world party** and now share the `NEWCO` label (DEC-085 for the PO enum, DEC-185 for the inventory flag; the inventory flag was harmonized to the shared `NEWCO` label by MVP-DEC-028). The `OwnershipTransitioned` cascade prose in this PRD (§6, §14.5) is unambiguous about which signal each ledger keys to — so the title ledger (D, keyed to the sale/shipment signal `VoucherIssued`) and the inventory-ownership ledger (B, keyed to `SupplierPaymentCompleted`) are never conflated.

### §3.6 L-PP producer-write treatment (P2) — Module D retains zero producer writes (cut-sheet §3.5; ratified Q4)

At launch the Producer Portal is full-read + full-reporting + view-only, with exactly **one** producer write retained platform-wide — **membership approve/decline**, a **Module K** surface. **Module D therefore retains *no* producer writes at launch.** Every Module D operation is a producer/back-office write → operator-driven via the Admin Panel:

| Producer write (Module D surface) | Launch treatment | Seam |
|---|---|---|
| ProcurementIntent creation (V1/V2 system-auto-fired; Direct-Purchase operator-initiated PI **deferred**, §3.1) | **Operator-driven via Admin Panel** (no producer-facing PI write at launch) | DEC-083 parity; producer UI post-launch on the same backend. |
| PO lifecycle (DRAFT → ISSUED → … → CLOSED), issuance-gate override, cost-finalization | **Operator-driven via Admin Panel** | Same backend; producer UI post-launch. |
| Producer-initiated **recall** trigger (§9, `producer_initiated`) | **Operator-driven via Admin Panel** (`operator_initiated`); recall is event-record-only anyway (§9) | Same backend; producer recall UI post-launch. |
| SupplierProducerLink creation, discrepancy-resolution actions | **Operator action by definition** (back-office) | n/a. |

Because DEC-083 admin-parity is a *backend contract*, **no backend capability is cut** — only the producer-facing write UIs are deferred (esp. producer-initiated recall + the deferred operator-initiated PI), and the operator path is already functionally complete. Producer Portal **read + full reporting** (PO status — units shipped / in-transit / received; settlement projections from procurement-side data — D23) is **KEPT.** The consolidated operator-surface inventory lives in the 9th Admin-Panel PRD (it references this PRD's operations rather than re-specifying them).

### §3.7 D19 settlement-recording seam + the naming cascade (cut-sheet §3.4–§3.5; ratified Q5)

**D19 — recording KEPT, automation is Module E's.** Module D **records** all procurement/inbound financial events (§16.1: `InboundEventCostFinalized` + landed-cost categories, `DiscrepancyResolutionRecorded`, `ConsignmentReceiptRecorded`, `ReverseInboundEventRecorded`, `PurchaseOrderIssued`, `POIssuedUnderNonActiveAgreement`). The **settlement engine** (quarterly `ProducerSettlementStatement` composition, payment execution, Xero GL) is **Module E's, deferred to operator-run** (D19) — *not a Module D cut.* Module D's obligation is to **keep recording** so the deferred Module E engine can settle later; **the recording is the seam.** Module D also feeds the per-unit cost `C_i` (`commercial_terms`, §6 / DEC-092 — read from Module A's per-constituent lineage) that Module E uses for the deferred 5% Originating-Club Discovery share (Module K K.13 seam — Phase C item E, capture whole at launch). **Partial PO settlement is already deferred** (OQ-20) — `SupplierPaymentCompleted` is atomic per PO at launch; carried verbatim (§18). Net Module-D-layer cut: **zero**.

**Naming cascade (Phase C item A — naming/contract only, no behaviour change).** Per Module 0 v0.3-MVP §18 (the source of truth), Module D renames only its **PR-referencing / Module-0-event-consuming** prose; its own `ProcurementIntent*` / `PurchaseOrder*` / `InboundEvent*` / `ConsignmentReceipt*` / `ReverseInboundEvent*` / `SupplierProducerLink*` names are already category-neutral and unchanged. The full application table is at §19. Headline touchpoints: `Bottle Reference (BR) → Product Reference (PR)` at PI/PO line composition (§5, §6); the `BR → Wine Master → Producer` deref → `Product Reference → Product Master → Producer` (§10 SupplierProducerLink validation, §14.3); the consumed Module 0 events `BottleReferenceActivated/Retired → ProductReferenceActivated/Retired` (§14.3, §16.4). "Bottle Reference" retained as a wine-display alias; payload semantics identical.

---

## §4 Entity Model

Module D's launch entity set comprises six entities, all owned by Module D except where noted:

- **ProcurementIntent (PI)** — the demand signal that triggers PO creation. §5.
- **PurchaseOrder (PO)** — the formal financial commitment to a supplier / producer counterparty. §6.
- **InboundEvent** — the two-phase physical-acceptance + cost-finalisation event for goods received at Vinlock. §7.
- **ConsignmentReceipt** — V2-only intake artefact for pre-positioned stock. §8.
- **ReverseInboundEvent** — producer-initiated unsold-stock recall (event-recording at launch; full mechanics deferred, §18). §9.
- **SupplierProducerLink** — the N:N link between Supplier and Producer; PO-line validation gate at issuance. §10.

The DiscrepancyResolution paths (Accept Shortage, Return + Reorder, Return for Credit, Adjustment, Supplier Replacement, Write-Off) operate as event variants on the InboundEvent / cost-finalisation surface; documented in §13. **All six entities + the procurement spine are KEPT** — none is heavier than the inbound floor needs.

**No SupplierAgreement entity at launch** (DEC-084 — already-deferred, carried verbatim, §18). Module D reads informal Supplier metadata from Module K Supplier (Party `party_type = SUPPLIER`); supplier-counterparty commercial terms live on the **Allocation** entity (`commercial_terms`, DEC-092), not on a separate agreement entity. Two-tier conceptual model: **ProducerAgreement** (Module K §4.6 — the D19 settlement-cadence seam, KEEP-minimal) regulates the club relationship as the commercial UMBRELLA; **per-allocation commercial terms** (DEC-092) regulate the SPECIFIC transactional relationship per allocation. Growth to a SupplierAgreement entity is a post-launch option (§18).

---

## §5 ProcurementIntent (PI)

The **ProcurementIntent** is the demand signal that triggers PO creation. PI carries allocation context, demand qty, target Supplier / Producer counterparty, and a lifecycle that reflects the trigger source and downstream PO state. *(Naming cascade: the PI's line identity is a **Product Reference**; wine-display alias *Bottle Reference*.)*

**PI shape**:

- **Allocation reference**: the Module A allocation the PI is sourced from. Drives every downstream contract — `sourcing_model` (PO timing, §12), `commercial_terms` (PO value computation, §12.2 — the per-constituent `C_i`), counterparty routing (DEC-082), **Product Reference** identity at line items (Module 0 read).
- **Demand qty**: the units the PI requests. For V1, typically the voucher's qty. For V2, the voucher's qty against pre-positioned stock. For Direct Purchase (deferred, §3.1), the operator-initiated full purchase batch (typically the Allocation's full `qty`).
- **Target counterparty**: derived from `Allocation.supplier_id` (when populated, DEC-082) or `Allocation.producer_id` (fallback, DEC-082 + DEC-088 receiving-party rule); the entity the PO will be issued to.
- **Lifecycle**: `DRAFT → COMMITTED → FULFILLED | CANCELLED` (inherited from v17 §8 verbatim).
  - **DRAFT**: PI created; not yet bound to a PO.
  - **COMMITTED**: PI is bound to an issued PO; the procurement chain is in motion.
  - **FULFILLED**: terminal — the PI's demand has been satisfied via successful inbound (`InboundEventPhysicallyAccepted` + `InboundEventCostFinalized`) and the linked PO has reached `CLOSED`.
  - **CANCELLED**: terminal — the PI was cancelled (e.g., voucher voided pre-shipment for V1; allocation closed before V2 sell-through). The transition emits `ProcurementIntentCancelled` (§16.2) carrying the cancellation trigger source; for V1 PIs the trigger source is the upstream `VoucherVoided` signal consumed from Module S (§14.4).
- **Trigger source**: enum — `voucher_issuance | operator_initiated`. **V1 / V2 PIs are `voucher_issuance`** (system-triggered; exercised at launch). **Direct Purchase PIs are `operator_initiated`** — ***deferred at launch (§3.1); the enum value is retained as the seam.*** Captured at PI creation for audit and downstream filtering.
- **Audit identity**: opaque PI id; creation timestamp; creating actor + actor role (`newco_ops | system_voucher_issuance` at launch — the producer-facing PI write UI is deferred, §3.6).

**PI trigger by sourcing model** (per §3 + DEC-093):

- **V1**: voucher issuance against the allocation auto-fires PI creation. One PI per voucher in the simple case (the V1 PO that follows the producer-settlement-cadence aggregates the period's PIs). Batched-per-shipment optimisation is admitted but deferred to post-launch tuning (§18). **Exercised at launch.**
- **V2**: voucher issuance auto-fires PI creation, referencing the already-arrived pre-positioned stock; the PO that follows is a financial PO settled at sell-through-settlement cadence. **Exercised at launch.**
- **Direct Purchase** *(deferred — §3.1)*: NewCo ops would manually create the PI at allocation-creation time (operator-initiated; the PI is created BEFORE the allocation is sellable — Allocation in DRAFT); the PO follows; once the PO is committed, **operator-publish** transitions the Allocation DRAFT → ACTIVE uniformly per DEC-183 (R1 — **not** driven by `SupplierPaymentCompleted`). **Not built at launch; the seam is the retained `trigger_source = operator_initiated` value + the uniform flow.**

**PI ↔ Allocation cardinality** is N:M: a V1 allocation may have many PIs over time (one per voucher batch); a V2 allocation may have one PI (the financial PO covers the pre-positioned inventory) or multiple (one per sell-through cadence period); a Direct Purchase allocation typically has one upfront PI *(deferred)*. *(AMB-D-2 [PI/Allocation cardinality for capacity-increase] is an acceptance-authoring backlog item, §18 — orthogonal to MVP scope.)*

**Domain events**: `ProcurementIntentCreated` (§16.1 — PI id, bound allocation, demand qty, trigger source; consumers: Module A audit, Module D's own PO-issuance subroutine); `ProcurementIntentBackCommitted` (§16.1 — fires on the COMMITTED transition; audit); `ProcurementIntentCancelled` (§16.2 — fires on cancellation).

---

## §6 PurchaseOrder (PO)

The **PurchaseOrder** is the formal financial commitment NewCo issues to the supplier / producer counterparty. PO inherits the v17 §8 entity verbatim, with the ownership enum tokenised to the NewCo names (`PRODUCER \| NEWCO \| THIRD_PARTY`, per DEC-085). *(Naming cascade: PO line items reference a **Product Reference**; wine-display alias *Bottle Reference*.)*

**PO shape**:

- **PI reference(s)**: one or more PIs the PO is bound to (V1 PO may aggregate the settlement period's PIs; V2 PO covers the period's pre-positioned-stock PIs; Direct Purchase PO typically binds one upfront PI — *deferred*).
- **Counterparty**: the Supplier (Module K §4.5) or Producer (Module K §4.4) per DEC-082's two-FK pattern read from the source Allocation — Supplier when `Allocation.supplier_id` is populated (the COMMON Discovery pattern); Producer when null (the Producer-as-Supplier collapse, DEC-088).
- **Ownership 3-value enum** (DEC-085 — the **PO-level title ledger**; see §3.5 / N3):
  - `PRODUCER` — title remains with Producer until sell-through. Default for `passive_v1` / `passive_v2` through sell-through.
  - `NEWCO` — title is NewCo's. **For V1/V2: transitions PRODUCER→NEWCO at sell-through** (the title transition, keyed to `VoucherIssued` — item F, §3.5). **For `direct_purchase`: set at PO issuance** (NewCo paid outright) — ***deferred at launch (§3.1); the at-issuance derivation is the unexercised seam.*** *(Note: `NEWCO` is still reached by V1/V2 at sell-through — only the `direct_purchase → NEWCO`-at-issuance derivation is unexercised.)*
  - `THIRD_PARTY` — reserved for future use (consignee-not-NewCo paths). Dormant at launch (carry, dormant-enum convention).
- **PO line items**: each line references a **Product Reference** (Module 0 §3.4), a qty, and the per-unit value computed from `Allocation.commercial_terms` per §12.2.
- **Lifecycle**: `DRAFT → ISSUED → ACKNOWLEDGED → IN_TRANSIT → RECEIVED → CLOSED` (inherited from v17 §8 verbatim):
  - **DRAFT**: drafted; ready for the issuance-gate evaluation (§11).
  - **ISSUED**: cleared the two-level gate (DEC-094, §11); financial commitment binding. `PurchaseOrderIssued` fires (D-emitted).
  - **ACKNOWLEDGED**: counterparty confirmed receipt (may be implicit for prompt-paid POs).
  - **IN_TRANSIT**: goods physically en route to Vinlock. *(For Direct Purchase, IN_TRANSIT may overlap `Allocation.state = ACTIVE` — sales proceed before physical receipt, DEC-081 — deferred, §3.1.)*
  - **RECEIVED**: physical receipt at Vinlock has fired `InboundEventPhysicallyAccepted` for the PO's units.
  - **CLOSED**: terminal — cost finalisation has fired `InboundEventCostFinalized` (D-emitted) **and the supplier payment has cleared, signalled by `SupplierPaymentCompleted`, which Module D consumes from Module E** (R4 — the payment-completion event is E-emitted; Module D settles/closes the PO on consumption, §3.3).
- **Audit identity**: opaque PO id; creation timestamp; issuing actor + actor role; ownership enum value at issuance.

**Ownership derivation rule** (DEC-085 — the PO-level **title** ledger; see §3.5 / N3): at PO creation, ownership derives from `Allocation.sourcing_model`:

- `direct_purchase` → ownership = NEWCO at PO issuance *(deferred at launch, §3.1 — the at-issuance derivation is the unexercised seam)*.
- `passive_v1 | passive_v2` → ownership = PRODUCER through sell-through; **transitions PRODUCER → NEWCO at sell-through, keyed to `VoucherIssued`** (the sell-through signal — item F; there is **no separate `SellThroughRecorded` event**, resolving AMB-D-3). `VoucherShipped` is available for a shipment-keyed title leg. Module D's PO ownership attribute reflects the current title-bearing state of the PO's covered units, evaluated **at PO issuance and on `VoucherIssued` events covering an open PO's units** (MVP-DEC-036): **in the launch V1/V2 cadence flow every covering voucher precedes its settlement PO** (voucher → PI → cadence PO, §12.1/§12.2), so the PO is issued `ownership = PRODUCER` (the derivation rule above; `PurchaseOrderIssued` carries that at-issuance value, §16.1) **and the PRODUCER → NEWCO title transition applies immediately upon issuance, in the same operation, recorded as its own audited transition** citing the already-issued covering vouchers. The event-driven flip remains wired for any `VoucherIssued` covering a title-bearing open PO's units — scoped to the PO's bound PIs, not any voucher on the allocation (a later voucher belongs to the next cadence PO) — unexercised at launch (the covered PI set is fixed at issuance). The transition is **atomic per PO** (the `ownership` enum is a PO-level attribute; no per-line / per-voucher partial title at launch — mirroring the atomic-per-PO settlement, §16.4 / OQ-20) and is an audited attribute transition, **not a new domain event** (audit-record representation = DEC-073). **No accounting position (DEC-072)** — the precise GL keying folds into the deferred settlement recording (D19).

> **N3 — two distinct ledgers (§3.5).** The PO-level **title** transition (Module D, keyed to `VoucherIssued`) is **distinct** from the inventory **`ownership_flag`** transition (Module B, keyed to `SupplierPaymentCompleted`) — same real-world party, same `NEWCO` value, two different signals at two different moments. The cascade prose keeps the two unambiguous.

**PO line value computation** (DEC-092 — reads Module A's `commercial_terms`, the per-constituent `C_i` lineage feeding the D19 settlement + the 5% Originating-Club share):

- `commercial_terms.shape = fixed_per_unit` → PO line total = `value × qty`.
- `commercial_terms.shape = percent_of_selling_price` → PO line total = `value% × selling_price × qty` (the `selling_price` is producer-set for club, NewCo-set for Discovery; read at sell-through). The two shapes are orthogonal to sourcing model; both ship (clubs price percent, Discovery fixed).

**Sourcing-model drives PO timing; commercial-terms shape drives the value computation** (DEC-086 + DEC-092 — independent dimensions; §12).

**Domain events**: `PurchaseOrderIssued` (§16.1 — PO id, ownership enum value, counterparty, bound PIs, line items, total committed value; consumers: Module A [the PO-commit moment is the gate for operator-publish-driven activation, DEC-183], Module E [financial-event recording]); `PurchaseOrderAcknowledged` / `PurchaseOrderInTransit` (§16.1 — audit); `POIssuedUnderNonActiveAgreement` (§16.2 — the operator-override path, §11.1).

---

## §7 InboundEvent **(FLOOR — the core-loop "bottle inbound" step)**

The **InboundEvent** records the physical-acceptance + cost-finalisation of goods received at the Vinlock warehouse. InboundEvent inherits the v17 §8 two-phase model verbatim (v13 Stage 2.3). **This is the core-loop "bottle inbound" step (MVP-plan §3) — KEPT WHOLE; nothing heavier than the floor needs.**

**Two-phase lifecycle**:

- **Phase 1 — `PHYSICALLY_ACCEPTED`**: Vinlock confirms physical receipt. The **documents-side 3-gate inbound QC** fires (DEC-194 split — Module D = documents-in-order; Module B = physical-match, §7.1):
  - **Quantity** — units received match the producer's manifest (the expected delivery; for direct purchase, the PO).
  - **Damage** — units are usable; no damage-in-transit beyond acceptable thresholds.
  - **Matching** — units match the **Product Reference** identity declared on the producer's manifest (for direct purchase, the PO) — no wrong-vintage / wrong-format misshipment.
  - On all-three-gates-pass: `PHYSICALLY_ACCEPTED` fires; the goods enter the salable pool (V2) or V1 staging-for-customer-shipment. **`InboundEventPhysicallyAccepted` creates Module B's InboundBatch** in `expected` state (DEC-195 — the no-overselling Layer-2 / committed-inventory floor, Phase C floor chain 1) and **gates Module C shipment** (DEC-081).
  - On any-gate-fail: a `DiscrepancyResolution` event opens (§13); Phase 1 is paused pending resolution.
- **Phase 2 — `COST_FINALIZED`**: within a 5-working-day SLA from Phase 1, the Cost Finalisation Operator finalises **landed cost** = base cost + **4 adjustment categories** (transport / customs / insurance / other). **Base cost = the accepted units' supplier-share value per `Allocation.commercial_terms`** (DEC-092; MVP-DEC-032): the PO line value where a priced PO precedes arrival (direct purchase — PO unit price × accepted qty; *deferred, §3.1*); the voucher-resolved PI values for V1 (sell-through precedes arrival); the commercial-terms evaluation against the allocation's set selling price for V2 pre-positioning (no PO at arrival — the producer-set price is set at allocation creation, BMD §5.1, so the base is derivable for both `commercial_terms` shapes; the percent-shape *settlement* truth stays sell-through-keyed per DEC-092 — any drift reconciles at the settlement layer, Module E). The base is **provisional from Phase 1** (Module B sets the InboundBatch cost basis at creation, DEC-195) and confirmed — with the adjustment categories — at Phase 2; pre-fill/override mechanics are tech-implementation (DEC-073). The breakdown payload is recorded on the `InboundEventCostFinalized` event; **Module E records + Xero decides accounting treatment** (DEC-072 — Module D takes no position on inventory capitalisation, COGS timing). This is the **D19 settlement cost-basis seam** (Module B flips the InboundBatch cost-basis flag provisional → finalized; Module E reads it).

**5-working-day SLA**: per-InboundEvent independent timer; a **configurable default** (working days = Mon–Fri at launch; no holiday calendar — MVP-DEC-032). **SLA breach is a soft alert** (operator alert + audit flag), never a schema constraint — the `COST_FINALIZED` event still fires when the cost is eventually finalised; the breach is captured on the event (`sla_breach` flag) + the audit trail. *(The timer mechanics + the override-management surface are tech-implementation, DEC-073; per-supplier SLA override management is already-deferred, §18.)*

**Receiving-party rule** (DEC-088): the inbound-receiving party is **always the Supplier** (the PO counterparty). Producer-as-Supplier collapses trivially (the Producer's Party row carries both `party_type`); Discovery-with-Supplier-not-Producer routes through the Supplier; the data model reads `PO.supplier_counterparty`. **KEPT** — cheap derivation, load-bearing for inbound routing.

**Domain events** (§16.1): `InboundEventPhysicallyAccepted` (Phase 1 — procurement-source anchor (BR-D-Identity-3: the PO where one precedes arrival; the allocation + PI lineage for V1; the allocation via the paired ConsignmentReceipt for V2), accepted qty per line, provisional cost basis (the Phase-2 base derivation above — Module B reads it at InboundBatch creation, DEC-195), 3-gate QC result, receiving party; consumers: Module B [InboundBatch creation, DEC-195 + serialization workflow on serialized stock], Module C [shipment gate], Module A [audit]); `InboundEventCostFinalized` (Phase 2 — procurement-source anchor (as Phase 1; the V1 settlement-cadence PO binds once issued), landed cost base + 4-category breakdown, `sla_breach` flag; consumers: Module E [financial-event recording], Module B [cost-basis flip provisional → finalized]).

### §7.1 Receiving discrepancy split — Module D = documents in order; Module B = physical match (DEC-194) **(integrity core — FLOOR, KEPT)**

Per **DEC-194** the receiving discrepancy authority is **split across Module D and Module B** (restores the v17 §B.9 two-stage receiving discipline):

- **Module D — documents in order** (Phase 1). Module D's 3-gate inbound QC at `PHYSICALLY_ACCEPTED` checks paperwork (quantity vs the producer's manifest — the PO for direct purchase), provenance (**Product Reference** identity match), and physical-condition-on-arrival — DEC-194's framing of the §7 gates (paperwork ↔ Quantity; provenance ↔ Matching; physical-condition ↔ Damage; MVP-DEC-032). ProducerAgreement state is **not** part of the receiving QC — agreement gating sits at PO issuance (§11, DEC-094). Pass fires `InboundEventPhysicallyAccepted`; Module B consumes it and creates the InboundBatch in `expected` state (DEC-195). **The Module D-side check is the documents-side check; it does NOT verify physical counts against any independent ledger** — that is the Module B-side check.
- **Module B — physical match** (Module B v0.2 §11). Module B compares Logilize-reported physical counts against the InboundBatch's expected quantity; variance triggers `InboundBatchDiscrepancy` to Module D (§13.3).

The two-stage check is the discipline that delivers the v17 KPI "inbound physical-discrepancy rate <5%" — the independent ledger detects Logilize-side errors the single-stage check cannot. **The split + the discipline are FLOOR — KEPT.** The Module D side of the cascade — `InboundBatchDiscrepancy` consumption + InboundEvent re-opening into DISCREPANCY (§13.3) — is **manual-first at launch (N1, §3.4):** the operator opens the discrepancy; the automated round-trip is the deferred-but-additive seam. The integrity interlock (the DISCREPANCY state has a destination for the physical-match variance) is **kept**.

---

## §8 ConsignmentReceipt

The **ConsignmentReceipt** is the V2-only intake artefact recording the producer's pre-positioning of stock at Vinlock under passive consignment V2 (v17 §8 inheritance verbatim). **KEPT** — V2 passive consignment ships at launch (D11 keeps both consignment variants), so the V2 intake artefact is needed; cheap, v17-verbatim.

**Shape**: lightweight QC at intake (the standard documents-side 3-gate QC per §7 applies via the parallel InboundEvent); records the producer's notification (notified-at timestamp + optional free-text note at launch — MVP-DEC-032) + the pre-positioned qty + the **Product Reference** identity + the **source-allocation reference** (the BR-D-Identity-3 anchor for the paired V2 InboundEvent — already normative on the `ConsignmentReceiptRecorded` payload + AC-D-BR-Identity-3; Shape-list wording alignment, MVP-DEC-037) + the Vinlock storage location + the **paired-InboundEvent reference** (late-bound: null at `PENDING_RECEIPT` — the notification precedes arrival; set from `RECEIVED`; required for `AVAILABLE`); lifecycle `PENDING_RECEIPT → RECEIVED → AVAILABLE` (AVAILABLE = post-`PHYSICALLY_ACCEPTED`, in the salable pool). **Transitions are operator-recorded at launch** (manual-first, MVP-DEC-032): `RECEIVED` at physical arrival; `AVAILABLE` only once the paired InboundEvent is `PHYSICALLY_ACCEPTED` — the gate is the invariant; whether `AVAILABLE` is operator-confirmed under that validation or auto-derived from `InboundEventPhysicallyAccepted` is tech-implementation (DEC-073).

**V2-specific role**: V2 stock arrives without a per-customer-order trigger (pre-positioned at allocation activation); the ConsignmentReceipt records the pre-positioning event itself, while the parallel InboundEvent records the operational acceptance + cost finalisation. Both fire for V2; only InboundEvent fires for V1 (no pre-positioning) and Direct Purchase (deferred; no pre-positioning).

**Domain event**: `ConsignmentReceiptRecorded` (§16.1, V2-only — allocation reference, pre-positioned qty, Product Reference identity, storage location; consumers: Module B [composes with `InboundEventPhysicallyAccepted` into a single InboundBatch for V2], Module A [audit], Module E [V2 settlement-timing lineage]). *(AMB-D-1 [`ConsignmentReceiptRecorded` consumer-list reconciliation] is an acceptance-authoring backlog item, §18 — orthogonal.)*

---

## §9 ReverseInboundEvent

The **ReverseInboundEvent** records producer-initiated unsold-stock recall at launch (DEC-090). **KEPT (minimal) — do not re-cut:** the event-recording role is in scope; **full reverse-inbound mechanics** (reverse 3-gate QC, reverse cost-basis unwind precision, partial-recall UX, recall-dispute path, automated return-shipment carrier coordination, reverse-discrepancy paths) are **already-deferred in v1.1** (OQ-12/18, DEC-152) — carried verbatim to the roadmap (§18). Matches D15 (manual recall).

**Shape**: allocation reference; recalled qty + **Product Reference** identity (bounded by Module A's anti-orphan rule on the unsold portion — `Allocation.qty − issued voucher count`); destination (return-to-Producer at launch); trigger source enum `producer_initiated | operator_initiated`; lifecycle `RECORDED` (terminal — the event is the artefact at launch; physical reverse logistics are handled operationally by Module C with manual coordination).

**L-PP (§3.6):** the **producer-initiated recall UI is deferred** → at launch the operator records the recall via the Admin Panel (`operator_initiated`); the seam is the same backend + the producer recall UI post-launch. Module A emits `AllocationRecallTriggered` on the upstream operator action; Module D consumes the signal and records `ReverseInboundEventRecorded` (DEC-090). Downstream Module E records the financial event of cost reversal (DEC-072 event-only framing).

**Domain event**: `ReverseInboundEventRecorded` (§16.2 — recalled allocation reference, recalled qty + Product Reference identity, destination, trigger source; consumers: Module A [audit], Module E [financial-event recording for cost-basis unwind]).

---

## §10 SupplierProducerLink

The **SupplierProducerLink** is the N:N link entity between Supplier (Module K §4.5) and Producer (Module K §4.4); owned by Module D (DEC-087 — the link gates a Module D operation, PO-line validation at issuance). **KEPT + naming cascade.**

**Shape**: Supplier reference (FK to Module K §4.5); Producer reference (FK to Module K §4.4); lifecycle `active | inactive`; **operator-explicit creation** (no auto-link on Producer/Supplier onboarding — carried forward verbatim per DEC-087); audit identity (opaque link id, timestamps, actors).

**PO-line validation gate at PO issuance** (DEC-087):

> At PO issuance, every PO line item validates that an **active** SupplierProducerLink exists between the PO's Supplier and the line's **Product Reference**'s Producer (**`Product Reference → Product Master → Producer` deref**, per Module 0 §3.4 — *naming cascade: the v1.1 `BR → Wine Master → Producer` deref renames to `Product Reference → Product Master → Producer`, naming only*). Validation failure blocks PO issuance.

Operational cases: **Common — Discovery + Supplier-not-Producer** (a Discovery Supplier spanning N Producers must have an active link to each constituent Producer); **Trivial — Producer-as-Supplier collapse** (self-referential link; 95%+ of launch Producers have a 1:1 link to themselves); **Admitted — Club-with-Supplier-not-Producer** (lower frequency). Cheap gate logic.

**Domain events** (§16.1): `SupplierProducerLinkActivated` (on creation or `inactive → active`; audit); `SupplierProducerLinkDeactivated` (on `active → inactive`; audit — downstream Procurement Operator may need to re-validate in-flight POs).

---

## §11 PO Issuance Two-Level Gate (DEC-094) **(FLOOR — the financial-commitment discipline)**

PO issuance applies a **two-level gate** depending on the PO counterparty type — the load-bearing business invariant of Module D's PO-issuance flow. **KEPT WHOLE.**

### §11.1 Producer-counterparty POs

- **Level 1 — ProducerAgreement umbrella** (Module K §4.6, DEC-070): the Producer's ProducerAgreement must be `active` at PO issuance. Blocks PO if `draft / superseded / terminated`. **Operator override surface**: an operator may explicitly override Level 1 for in-flight commitments under terminating agreements (the BMD §3.13 offboarding case); documented per PO with a free-text reason; fires `POIssuedUnderNonActiveAgreement` (§16.2) for compliance review. The override is **soft** (operator-explicit, audit-logged), not blocked. *(Module K's ProducerAgreement is KEEP-minimal — the D19 settlement-cadence seam; Module D reads its state at the gate.)*
- **Level 2 — Allocation state** (Module A FSM, DEC-077): the source Allocation must be in valid sellable state — `ACTIVE` (canonical) or `CLOSED` (in-flight PO settlement against pre-close vouchers). DRAFT / RETIRED block PO issuance.

### §11.2 Supplier-not-Producer-counterparty POs

- **Level 1 — N/A** at launch (no SupplierAgreement entity, DEC-084 — already-deferred, §18; supplier commercial terms live on `Allocation.commercial_terms`, DEC-092). No umbrella state to gate on. `POIssuedUnderNonActiveAgreement` is **not applicable** (no umbrella to override).
- **Level 2 — Allocation state**: same as the Producer-counterparty path.

### §11.3 Two-tier conceptual model

The gate operationalises the two-tier model (DEC-084 + DEC-094): **ProducerAgreement** (umbrella legal contract — one per Producer; required for any Producer-counterparty transaction) + **per-allocation commercial terms** (transaction-level — one per Allocation). Module D's PO issuance reads both gates for Producer-counterparty POs; only Level 2 for Supplier-not-Producer POs. **Lifecycle orthogonality** (DEC-077): Allocation lifecycle is orthogonal to ProducerAgreement state — Module A's operations admit independently of agreement state; Module D's PO-issuance gate is the protective gate at the procurement layer.

---

## §12 PO Timing per Sourcing Model (DEC-086) and the Activation Framing

PO issuance timing branches on the upstream Allocation's `sourcing_model` (DEC-086 + DEC-093). The branching is at the timing level only; the flow shape is uniform.

### §12.1 V1 (passive consignment, customer-order-driven) — exercised at launch

- **PI trigger**: voucher issuance auto-fires PI creation (one per voucher simple; batched per shipment optimised — batching deferred, §18).
- **PO timing**: PO at the producer's ProducerAgreement settlement cadence (Module K §4.6, DEC-070 — typically quarterly). A goods-movement PO (producer → Vinlock) for the period's accumulated PIs.
- **PO line value**: per `commercial_terms.shape` (typically `percent_of_selling_price` 12.5% for club → 87.5% × selling_price × qty; or `fixed_per_unit`).
- **Activation + `SupplierPaymentCompleted` framing (R1 + R4)**: V1 allocations transition DRAFT → ACTIVE on **operator-publish post-PO-commit uniformly** (DEC-183 — **not** driven by a payment event). `SupplierPaymentCompleted` (per ProducerAgreement payment terms, typically net-30) is a **financial event with no FSM role** (R1) and is **emitted by Module E** on payment clearing, consumed by Module D to settle/close the PO (R4, §3.3).

### §12.2 V2 (passive consignment, pre-positioned) — exercised at launch

- **PI trigger**: voucher issuance auto-fires PI creation, referencing already-arrived pre-positioned stock.
- **PO timing**: PO at sell-through-settlement cadence — a financial PO against pre-positioned inventory (goods already at Vinlock; the PO settles the financial relationship at the cadence-driven moment).
- **PO line value**: same per-shape rules as V1.
- **Activation + `SupplierPaymentCompleted` framing (R1 + R4)**: same as V1 — operator-publish post-PO-commit (DEC-183); `SupplierPaymentCompleted` financial-event-only (R1), E-emitted / D-consumed (R4). Pre-positioning via ConsignmentReceipt remains the V2 inbound mechanic but does not gate the FSM.

### §12.3 Direct Purchase *(documented-but-not-exercised — deferred at launch, §3.1)*

- **PI trigger**: NewCo ops would manually create the PI at allocation-creation time (operator-initiated). ***Deferred — the operator-initiated PI-creation surface is not built at launch; the `trigger_source = operator_initiated` value is the seam.***
- **PO timing**: PO at PI-creation (operator-initiated, full-amount-at-purchase; a financial + goods-movement PO in one; ownership = NEWCO at issuance per DEC-085). ***Deferred — the at-PO-creation timing branch is the unexercised seam.***
- **Activation framing (R1)**: even when Direct Purchase is re-enabled, activation is **operator-publish post-PO-commit uniformly** (DEC-183) — the same trigger as V1/V2. **`SupplierPaymentCompleted` is NOT an activation trigger** (the v1.1 "canonical Direct Purchase activation trigger" framing is superseded — R1, §3.2). It is a financial event, E-emitted / D-consumed (R4).

### §12.4 The Direct-Purchase activation chain *(documented-but-not-exercised; reconciled to DEC-183 + R4)*

The corrected chain a Direct Purchase Allocation *would* follow when Direct Purchase is re-enabled post-launch (the v1.1 §12.4 chain, **reconciled** — the stale "`SupplierPaymentCompleted → AllocationActivated`" step removed per R1; the emitter corrected per R4):

1. NewCo ops creates the Allocation in DRAFT (Module A).
2. NewCo ops creates a ProcurementIntent in Module D (§5; operator-initiated).
3. Module D issues + commits a PO against the PI (ownership = NEWCO, derived from `sourcing_model = direct_purchase`; DEC-085 / N3 — the PO-level title ledger).
4. NewCo ops **operator-publishes** (Admin Panel "activate") → Module A transitions DRAFT → ACTIVE + emits `AllocationActivated` (DEC-183 — **does not wait for payment**; no `SupplierPaymentCompleted` FSM trigger).
5. Module S Offer-publication surfaces become valid; Cart Holds, voucher issuance, sales proceed.
6. Module D processes supplier payment; **Module E emits `SupplierPaymentCompleted`** on clearing (R4) — a financial event with no Allocation-FSM role; **Module D consumes it to settle/close the PO**; Module B consumes it for the inventory `ownership_flag` PRODUCER→NEWCO transition *(no-op for `direct_purchase` — NEWCO from creation, §3.3)*.
7. Module D's inbound flow proceeds independently; physical receipt fires `InboundEventPhysicallyAccepted`; Module C gates physical shipment for vouchers issued against this allocation ("in transit; ETA X" until receipt — DEC-081 decoupling; carrier-ETA-precision deferred D17, Phase C item K).

**Seam (P1):** the `trigger_source = operator_initiated` value, the uniform operator-publish FSM, the `ownership = NEWCO`-at-issuance derivation, and the at-PO-creation timing rule are retained; this chain re-activates additively with zero rework. The substantive Direct-Purchase build (the operator-initiated PI surface + its timing branch) is the deferred Module-D arm.

---

## §13 DiscrepancyResolution Paths **(integrity core — FLOOR, KEPT; the reciprocal cascades manual-first, N1)**

When the documents-side 3-gate inbound QC at `InboundEventPhysicallyAccepted` (§7) fails any gate, or an InboundEvent is re-opened into DISCREPANCY (§13.3), a **DiscrepancyResolution** event opens. Module D inherits the v17 §3.8 six-path enumeration verbatim (DEC-072 event-only framing). **KEPT** — examined as a SIMPLIFY candidate, kept: this is a **6-value event enum + a cost-impact payload, not six automated workflows** (the physical return/credit/replacement work is operational/manual anyway). Cutting paths would lose the audit + financial-event vocabulary Module E needs.

### §13.1 The six discrepancy paths

**Accept Shortage** (fewer units than manifest; NewCo accepts the short + adjusts settlement) · **Return + Reorder** (wrong/damaged; return + reorder) · **Return for Credit** (return + accept a credit) · **Adjustment** (minor negotiated qty/cost adjustment; no return) · **Supplier Replacement** (counterparty ships replacement units at zero cost) · **Write-Off** (unrecoverable; NewCo writes off the cost). Downstream accounting (Module E + Xero) decides treatment (DEC-072 — Module D records the operational event with the cost-impact payload, takes no accounting position).

### §13.2 Domain event

`DiscrepancyResolutionRecorded` (§16.1) fires on path resolution. Carries the path enum (one of six), the cost-impact payload, the original InboundEvent reference, the resolving actor. **Cost-impact payload shape (MVP-DEC-032 — the AC-D-J-10 confirmation): an optional signed `quantity_delta` (units) + an optional signed `value_delta` (amount + explicit currency — the affected line's settlement currency; Module E owns FX, DEC-169) + a required free-text note, uniform across the six paths.** Sign convention: positive increases NewCo's cost / payable; negative reduces it. Qty-adjusting resolutions (Accept Shortage; a qty-flavoured Adjustment) carry `quantity_delta` — it feeds the §13.3 downstream qty flow; zero-impact resolutions are admitted (explicit zeros / both deltas absent); field representation is tech-implementation (DEC-073). Consumers: Module E (financial-event recording for Xero forwarding); Module A (audit on allocation-level cost-basis impact). **D-emitted** (one of Module D's own procurement financial events — unchanged by R4).

### §13.3 InboundEvent DISCREPANCY state — `InboundBatchDiscrepancy` consumption (DEC-194) **(integrity interlock KEPT; manual-first, N1)**

The **InboundEvent DISCREPANCY state** is the Module-D-side reciprocal of Module B's physical-match check (DEC-194). **Module D consumes Module B's `InboundBatchDiscrepancy`** (carrying the InboundBatch reference, the upstream `InboundEventPhysicallyAccepted` reference, the variance-type discriminator, the variance quantity, the Logilize audit payload, the actor, the timestamp) and **re-opens the InboundEvent into DISCREPANCY** without retroactively invalidating the already-live InboundBatch records on the Module B side; the resolution routes through the §13.1 six-path enumeration.

> **N1 — manual-first at launch (§3.4).** At launch this reciprocal cascade is **manual operator discrepancy-handling**: the operator opens the InboundEvent into DISCREPANCY on a physical-match variance and records the resolution path within the 5-WD window, **identically to Module B's manual-first depth** (B decided this at Phase C in lockstep — item H). **The integrity core is KEPT** (the DISCREPANCY state has a destination for the physical-match variance; the 6-path enum + the event consumer stand); only the **automated round-trip** defers. **Seam:** the InboundEvent two-phase split, the DISCREPANCY state, the 6-path enum, and the `InboundBatchDiscrepancy` consumer are kept — automation is additive when Module B's physical-match automation lands. **Without the DISCREPANCY-state restoration, Module B's `InboundBatchDiscrepancy` would land with no destination** — the integrity interlock is preserved.

**Resolution flow + cost-basis consequences**: the resolution decision is recorded via `DiscrepancyResolutionRecorded`. **If the resolution adjusts qty** (Accept Shortage, Adjustment), the downstream `InboundEventCostFinalized` (Phase 2) reflects the adjusted qty; Module B updates the InboundBatch's `qty` + cost basis accordingly; already-live InboundBatch records are NOT retroactively invalidated (the resolution updates expected qty + flows the cost-basis adjustment forward without rewriting batch identity).

### §13.4 QuarantineRecord-driven cost-basis reconciliation — `BottleQuarantineResolved` consumption (DEC-191) **(integrity interlock KEPT; manual-first, N1)**

**Module D observes Module B's `BottleQuarantineResolved`** to drive the cost-basis reconciliation where applicable:

- Resolution path **"associate with existing batch"** AND the association affects InboundBatch qty → Module D records a follow-up `InboundEventCostFinalized` with the adjusted qty (or revises the prior emission if cost basis was not yet finalized).
- Resolution paths **"create new record" / "reject as invalid" / "escalate"** → no Module-D follow-up event (the new batch is a separate Module-B entity; the bogus entity → no mutation; escalate → Module D defers until the eventual resolution).

> **N1 — manual-first at launch (§3.4).** This cost-basis-correctness interlock is **manual-first**: the operator records the follow-up cost-finalization adjustment rather than an automated round-trip, **in lockstep with Module B's manual-first quarantine workflow** (item H). **The integrity core is KEPT** (the observation + conditional re-emit stand; cost-basis correctness feeds the no-overselling / committed-inventory floor + Module E); only the **automation** defers. **Seam:** the observation + conditional re-emit are kept — automation tracks whatever Module B's quarantine-workflow automation lands post-launch.

Per DEC-072, Module D records the operational event with the cost-impact payload; Module E forwards to Xero; Xero decides treatment. *(AMB-D-6 [`InboundBatchDiscrepancy` variance-type enum coverage] is an acceptance-authoring backlog item, §18 — orthogonal.)*

---

## §14 Cross-Module Contracts

Every cross-module read and event-flow Module D participates in (DEC-074 — NewCo prose; the v17 trace is in the frozen v0.2 §19). *(Naming cascade applied to the Module 0 reads + consumed-event names; Module D's own names unchanged. **R1 + R4 land on the Module A + Module E contracts — `SupplierPaymentCompleted` is E-emitted / D-consumed, no Module A FSM role.**)*

### §14.1 Module A (Allocation) — read + emit + observe

The load-bearing supply-side cross-module contract.

- **Module D reads Module A**: `Allocation.sourcing_model` (PO timing, §12), `Allocation.commercial_terms` (PO line value, §12.2 — the per-constituent `C_i` lineage feeding the D19 settlement + the 5% Originating-Club share, Phase C item E), `Allocation.producer_id` / `Allocation.supplier_id` (counterparty routing, DEC-082), `Allocation.qty` (PI / PO units), `Allocation.state` (Level 2 of the PO-issuance gate, DEC-094).
- **Module D emits**: `ReverseInboundEventRecorded` (on producer recall, DEC-090; Module A audit). **Module D no longer emits `SupplierPaymentCompleted` to Module A** — under R1 it has no Allocation-FSM role; under R4 it is E-emitted (§14.7 / §3.3). Module A takes no FSM action on it (Module A PRD §11.3 / §10.7 BR-A-CrossModule-4 — already aligned).
- **Module D observes**: `AllocationCreated` (surface PI/PO candidates); `AllocationActivated` (V1/V2 PI auto-fire on subsequent voucher issuance; for Direct Purchase — deferred — Module A emits it in response to operator-publish, not to a Module D payment event, R1); `AllocationCapacityIncreased` (follow-on PO for a Direct-Purchase capacity increase — *not exercised at launch*); `AllocationCounterpartyChanged` (update PO routing prospectively); `AllocationRecallTriggered` (record `ReverseInboundEventRecorded`, DEC-090); `AllocationClosed` / `AllocationRetired` (PI/PO state machines acknowledge the upstream transition).

### §14.2 Module K (Parties) — read + observe

- **Producer `active` + KYC-cleared state**: read at PO line creation.
- **Supplier informal metadata** (DEC-084 — no SupplierAgreement entity at launch, §18): read at PO issuance for counterparty identity (`payment_terms`, `notes` per Module K §4.5 informal extension).
- **ProducerAgreement state**: read at PO issuance for **Producer-counterparty POs only** as Level 1 of the two-level gate (DEC-094, §11.1). **Not** read for Allocation operations (DEC-077 orthogonality). *(Module K's ProducerAgreement is KEEP-minimal — the D19 settlement-cadence seam, Module K §4.6.)*
- **Module K lifecycle events**: `ProducerActivated` / `ProducerRetired` (Producer KYC gate at PO line creation); `ProducerAgreementActivated` / `ProducerAgreementSuperseded` / `ProducerAgreementTerminated` (Level 1 gate at PO issuance; cascades to operator-alert surfaces in the Admin Panel for in-flight PO awareness); `SupplierActivated` (Supplier identity gate when `supplier_id` is populated).

### §14.3 Module 0 (PIM) — read

- **Product Reference identity** (wine-display alias *Bottle Reference*): read at PI / PO line composition (the procurement flow operates on PR-keyed line items, Module 0 §3.4).
- **PR `active` state**: read at PO line creation (a `retired` PR cannot have new PO lines; existing in-flight lines under a now-retired PR run to natural completion, Module 0 §4.5 cascade).
- **`Product Reference → Product Master → Producer` deref**: read at PO line validation against SupplierProducerLink (§10). *(Naming cascade: the v1.1 `BR → Wine Master → Producer` deref — naming only.)*
- **Consumed Module 0 events**: `ProductReferenceActivated` / `ProductReferenceRetired` (drive the PR-active gate at PO line creation). *(Renamed from `BottleReferenceActivated/Retired` — naming cascade; payload semantics identical.)*

### §14.4 Module S (Offer / Cart / Checkout) — downstream cross-link

- **Module D consumes the voucher-issuance signal (V1 / V2)**: voucher issuance against an allocation auto-fires PI creation (§5).
- **`VoucherIssued` = the sell-through signal (item F, §3.5)**: drives Module D's PO PRODUCER→NEWCO **title** transition (§6 — there is **no separate `SellThroughRecorded` event**, resolving AMB-D-3). *(Launch V1/V2 timing: every covering voucher precedes its cadence PO, so the title transition applies at PO issuance — §6 evaluation rule, MVP-DEC-036; the event-driven flip covers vouchers issued against an open title-bearing PO's units, unexercised at launch.)* `VoucherShipped` is available for a shipment-keyed title leg. **No accounting position (DEC-072).** *(Distinct from the inventory `ownership_flag` transition, which keys to `SupplierPaymentCompleted` — N3, §3.5.)*
- **`VoucherVoided` consumption (V1)**: Module D consumes Module S's `VoucherVoided` and transitions any DRAFT or COMMITTED V1 PI bound to the voided voucher to CANCELLED, emitting `ProcurementIntentCancelled` (§16.2). For DRAFT PIs the cancellation is internal; for COMMITTED PIs the operator handles downstream PO unwind via the §13 DiscrepancyResolution paths. V2 + Direct Purchase PIs are not auto-cancelled.
- **Module D does NOT read Module S customer-facing pricing or surface state** — the procurement flow is producer-side / supplier-side.

### §14.5 Module B (Inventory Authority + Digital Provenance) — bidirectional contract **(FLOOR — floor chain 1; reciprocal cascades manual-first, N1)**

The Stage-8 contract is **bidirectional**: Module B is the downstream consumer of Module D's two-phase InboundEvent (DEC-195) AND emits `InboundBatchDiscrepancy` + `BottleQuarantineResolved` that Module D consumes (§13.3 / §13.4 — **manual-first at launch, N1**).

- **Module D emits to Module B**:
  - `InboundEventPhysicallyAccepted` (Phase 1) — Module B creates the **InboundBatch** in `expected` state with cost basis = provisional (DEC-195 — the no-overselling Layer-2 / committed-inventory floor, Phase C floor chain 1); Module B's physical-match check fires (DEC-194).
  - `InboundEventCostFinalized` (Phase 2) — Module B flips the InboundBatch cost-basis flag provisional → finalized (DEC-195).
  - `ConsignmentReceiptRecorded` (V2-only) — Module B composes it with `InboundEventPhysicallyAccepted` into a single InboundBatch for V2 stock.
  - Module B's serialization workflow consumes `InboundEventPhysicallyAccepted` for serialized stock (NFC tag application; NFT mint **decoupled per D12** — if the on-chain workstream slips, Module D's inbound events still fire; the non-serialized path is the universal fallback, Phase C item J).
- **Module B emits to Module D** *(consumed — manual-first at launch, N1)*:
  - `InboundBatchDiscrepancy` (DEC-194) — Module D re-opens the InboundEvent into DISCREPANCY (§13.3).
  - `BottleQuarantineResolved` (DEC-191) — Module D drives the §13.4 cost-basis reconciliation where the resolution path is "associate with existing batch" AND the association affects qty.
- **`SupplierPaymentCompleted` (E-emitted) → Module B's inventory `ownership_flag` PRODUCER → NEWCO (R4, §3.3 / N3, §3.5)**: Module B consumes the E-emitted payment event **independently** of Module D, driving the inventory-ownership-ledger transition. **This is distinct from Module D's PO-level title transition** (keyed to `VoucherIssued`, value `NEWCO` — §6 / item F). Same real-world party, same `NEWCO` value; two ledgers, two signals. Direct-Purchase no-op (`NEWCO` from creation — deferred, §3.1).
- **Receiving discrepancy authority split** (DEC-194): Module D = documents in order; Module B = physical match — the integrity core, FLOOR-KEPT (§7.1).
- **Module D does not read Module B state directly** — the cross-module contract is purely event-driven.

### §14.6 Module C (Fulfilment) — downstream consumer

- **Shipment gate**: Module C reads `InboundEventPhysicallyAccepted` for the relevant qty as the shipment gate (DEC-081 decoupling — `Allocation.state = ACTIVE` is the Module S sellability gate; `InboundEventPhysicallyAccepted` is Module C's shipment gate).
- **In-transit voucher UX**: for vouchers whose physical receipt has not yet fired, Module C / Module S surface "in transit; ETA X" (carrier-ETA-precision deferred, D17 — admin-estimate at launch; the V1-per-order producer→Vinlock window survives the Direct-Purchase deferral, Phase C item K).
- **Producer recall reverse logistics**: Module D records `ReverseInboundEventRecorded` (§9); Module C handles physical reverse logistics (operationally manual at launch; full mechanics deferred, §18).

### §14.7 Module E (Finance) — downstream consumer + the `SupplierPaymentCompleted` emitter (R4)

- **Module D emits financial events Module E records + forwards to Xero** (DEC-072): `PurchaseOrderIssued`, `InboundEventCostFinalized`, `ConsignmentReceiptRecorded`, `ReverseInboundEventRecorded`, `DiscrepancyResolutionRecorded`, `POIssuedUnderNonActiveAgreement`. **These stay D-emitted, unchanged.**
- **⚠️ `SupplierPaymentCompleted` is EMITTED BY MODULE E — Module D CONSUMES it (R4, §3.3).** Module E is the payment executor (Airwallex/Xero rails, DEC-014/028; three-actor split DEC-119; symmetric with the customer-side `AirwallexChargeExecuted`). At launch E emits it when the operator records the manual supplier payment in E's finance surface (settlement operator-run, D19 deferred); post-launch via E's settlement engine. **Module D consumes it to settle/close the PO** (§6 — the CLOSED transition). Atomic per PO (partial PO settlement deferred, OQ-20, §18). *(This corrects the cut-sheet's D.24 "Module D emits" — Phase C R4 supersedes; the cut-sheet stays as the Phase B record.)*
- **The D19 settlement-recording seam**: Module D **records** all procurement/inbound financial events; the **settlement engine** (quarterly statement, payment execution, Xero GL) is **Module E's, deferred to operator-run** (D19). Module D's recording is the seam the deferred engine settles against later. Module E reads the per-constituent `C_i` (`commercial_terms`, §6) for the deferred 5% Originating-Club Discovery share (Phase C item E — capture whole at launch).
- **Domain separation** (DEC-089): the INV1 / INV2 two-invoice mechanic is **customer-facing only**; Module D's supplier-counterparty payment is a separate domain; Module D emits no INV1/INV2.
- **No accounting positions** (DEC-072): Module D records business events; Module E records + Xero decides GL.

---

## §15 Business Rules and Invariants

Load-bearing rules, prefixed `BR-D-{Domain}-NN`. Tech-implementation enforcement is downstream (DEC-073). *(Naming cascade applied to the catalog-identity rules: §15.4 BR-D-Link-1, §15.9 BR-D-CrossModule-2. R1 + R4 land on §15.9 BR-D-CrossModule-4.)*

### §15.1 Identity and uniqueness
- **BR-D-Identity-1**: every PI / PO / InboundEvent / ConsignmentReceipt / ReverseInboundEvent / SupplierProducerLink row carries a unique opaque identifier; no business attributes form the identifier.
- **BR-D-Identity-2**: every PI references exactly one Allocation; every PO references one or more PIs (§6 cardinality).
- **BR-D-Identity-3**: every InboundEvent anchors to exactly one procurement source, scoped by sourcing model (MVP-DEC-032): the covering PO where one precedes arrival (direct purchase — bound at creation); the source allocation + PI lineage for V1 (the settlement-cadence PO binds when issued — arrivals may precede it); the source allocation via the paired ConsignmentReceipt for V2 pre-positioning (no PO at arrival; the sell-through settlement POs do not retro-anchor the inbound — DEC-195's "PO or consignment receipt"). Every PO may have multiple InboundEvents over time (V1's multi-shipment; capacity-increase follow-ons).

### §15.2 Procurement flow uniformity
- **BR-D-Flow-1 (uniform shape)**: the flow `PI → PO → InboundEvent → optional ConsignmentReceipt` is uniform across all sourcing models (DEC-093). Sourcing-model differences live in trigger + timing parameterisation, not in flow shape. **This uniformity is the D11 seam (§3.1).**
- **BR-D-Flow-2 (PI trigger)**: V1 / V2 PIs are auto-triggered by voucher issuance; Direct Purchase PIs are operator-initiated *(deferred at launch, §3.1; the `operator_initiated` enum value is the seam)*.
- **BR-D-Flow-3 (PO timing)**: V1 PO at producer-settlement-cadence; V2 PO at sell-through-settlement-cadence; Direct Purchase PO at PI creation *(deferred, §3.1)*.

### §15.3 PO issuance two-level gate (DEC-094)
- **BR-D-Gate-1 (Producer-counterparty Level 1)**: Producer-counterparty POs require ProducerAgreement `active`; admits operator override with `POIssuedUnderNonActiveAgreement` audit event for in-flight commitments under terminating agreements (§11.1).
- **BR-D-Gate-2 (Supplier-not-Producer Level 1)**: Supplier-not-Producer POs have no Level 1 gate (no SupplierAgreement entity at launch, DEC-084, §11.2).
- **BR-D-Gate-3 (Level 2 universal)**: every PO requires the source Allocation in valid sellable state (ACTIVE or CLOSED with in-flight settlement against pre-close vouchers).

### §15.4 SupplierProducerLink validation (DEC-087)
- **BR-D-Link-1 (PO-line gate)**: every PO line item requires an active SupplierProducerLink between the PO's Supplier and the line's **Product Reference**'s Producer (**`Product Reference → Product Master → Producer` deref** per Module 0 §3.4 — *naming cascade*). Validation failure blocks PO issuance.
- **BR-D-Link-2 (operator-explicit creation)**: SupplierProducerLink is operator-explicitly created (DEC-087); no auto-creation rule.

### §15.5 Inbound acceptance **(FLOOR)**
- **BR-D-Inbound-1 (two-phase)**: every InboundEvent comprises Phase 1 (PHYSICALLY_ACCEPTED) + Phase 2 (COST_FINALIZED).
- **BR-D-Inbound-2 (5-WD SLA)**: Phase 2 fires within 5 working days of Phase 1 by default; SLA breach triggers a soft operator alert (never blocks Phase 2); the SLA value is configurable.
- **BR-D-Inbound-3 (documents-side 3-gate QC)**: Phase 1 fires only after the documents-side 3-gate QC (quantity / damage / matching) passes; gate failure opens DiscrepancyResolution (§13). *(DEC-194 split: D = documents-in-order; B = physical-match.)*
- **BR-D-Inbound-4 (Supplier-as-receiving-party)**: the inbound-receiving party is always the Supplier (the PO counterparty); Producer-as-Supplier collapse is trivial (DEC-088).

### §15.6 V2-specific intake
- **BR-D-V2-1 (ConsignmentReceipt mandatory)**: V2 allocations record a ConsignmentReceipt at producer pre-positioning (§8). V1 and Direct Purchase do not.

### §15.7 Reverse-inbound at launch
- **BR-D-Reverse-1 (event-recording at launch)**: producer-initiated unsold-stock recall is event-recorded as `ReverseInboundEventRecorded` at launch; full mechanics deferred (OQ-12/18, DEC-152 — §18).
- **BR-D-Reverse-2 (manual operator capability)**: the NewCo Admin Panel admits manual operator entry of `ReverseInboundEventRecorded` at launch (the producer-initiated recall UI is deferred, L-PP, §3.6).

### §15.8 Domain separation **(FLOOR)**
- **BR-D-Separation-1 (INV1 / INV2 customer-facing only)**: the two-invoice mechanic is customer-facing only; supplier-counterparty payment is a separate domain (DEC-089).
- **BR-D-Separation-2 (event-recording, not accounting)**: Module D records financial events; Module E records + Xero decides treatment (DEC-072).

### §15.9 Cross-module dependency
- **BR-D-CrossModule-1 (Allocation read at PI creation)**: PI creation requires the source Allocation to exist in Module A (any state including DRAFT for the *Direct-Purchase initial PI* — *deferred at launch, §3.1*; subsequent PIs require ACTIVE).
- **BR-D-CrossModule-2 (PR active gate at PO line)**: PO line creation requires the **Product Reference** to be `active` in Module 0 *(naming cascade)*.
- **BR-D-CrossModule-3 (Producer active + KYC gate at PO line)**: PO line creation requires the line's PR's Producer to be `active` and KYC-cleared (`verified` or `not_required`) in Module K.
- **BR-D-CrossModule-4 (`SupplierPaymentCompleted` — E-emitted, D-consumed, no FSM role) — RECONCILED (R1 + R4)**: `SupplierPaymentCompleted` is a **financial event with no Allocation-FSM role** (DEC-183 / R1 — *the v1.1 framing of it as Module A's Direct-Purchase activation trigger is superseded*) and is **emitted by Module E** on payment clearing (R4 — *the v1.1/cut-sheet framing of Module D as the emitter is superseded*); **Module D consumes it to settle/close the PO** (§6); Module B consumes it for the inventory `ownership_flag` transition (§14.5). Activation is operator-publish post-PO-commit uniformly (Module A PRD §10.5 BR-A-Lifecycle-2 / §10.7 BR-A-CrossModule-4 — already aligned).

---

## §16 Domain Events

Module D's launch event contract (DEC-091). Per DEC-073, payload field-by-field listings are out of scope; the catalogue lists names + one-line business-signal semantics. Every event carries the standard audit envelope: opaque event id, source-entity reference, emission timestamp, actor identity, `actor_role` (DEC-083 — `newco_ops | system_voucher_issuance` at launch, the producer write UIs deferred). **Module D's own event names are category-neutral — unchanged by the cascade.**

> **The R4 emitted→consumed flip (§3.3).** In v1.1, `SupplierPaymentCompleted` was a Module-D-emitted event (one of the "10 v17-inherited"). Under **Phase C R4 it moves to Module D's *consumed* set** (§16.4) — **Module E emits it; Module D consumes it.** **All of Module D's *other* procurement/inbound financial events stay D-emitted** (§16.1–§16.2). So the v1.1 catalogue (10 v17-inherited + 3 NewCo) is unchanged in its set of *names*; only the direction of `SupplierPaymentCompleted` flips (D-emitted → D-consumed). *(This corrects the cut-sheet's D.24; the cut-sheet stays as the Phase B record.)*

### §16.1 Emitted — inherited from v17 §8 (the procurement/inbound financial + lifecycle events; `SupplierPaymentCompleted` moved out per R4)

- **`ProcurementIntentCreated`** — on PI creation. PI id, bound allocation, demand qty, trigger source (`voucher_issuance | operator_initiated`). Consumers: audit; Module D's PO-issuance subroutine.
- **`ProcurementIntentBackCommitted`** — on PI `DRAFT → COMMITTED` (bound to a PO). PI id, bound PO id. Audit.
- **`PurchaseOrderIssued`** — on PO `DRAFT → ISSUED`. PO id, ownership enum value (PRODUCER | NEWCO | THIRD_PARTY, DEC-085), counterparty, bound PIs, line items, total committed value. Consumers: Module A (the PO-commit moment is the gate for operator-publish-driven activation, DEC-183); Module E (financial-event recording).
- **`PurchaseOrderAcknowledged`** / **`PurchaseOrderInTransit`** — on the respective transitions. Audit.
- **`InboundEventPhysicallyAccepted`** (Phase 1) — on physical receipt + documents-side 3-gate QC pass. Procurement-source anchor (BR-D-Identity-3 — the PO where one precedes arrival; the allocation + PI lineage for V1; the allocation via the paired ConsignmentReceipt for V2; MVP-DEC-032), accepted qty per line, provisional cost basis (the §7 base derivation — Module B sets the InboundBatch cost basis from it, DEC-195), 3-gate result, receiving party. Consumers: Module B (InboundBatch creation, DEC-195 — floor chain 1; serialization on serialized stock); Module C (shipment gate); Module A (audit). **FLOOR.**
- **`InboundEventCostFinalized`** (Phase 2) — on landed-cost finalisation. Procurement-source anchor (as Phase 1; the V1 settlement-cadence PO binds once issued — MVP-DEC-032), landed cost (base per the §7 derivation + transport / customs / insurance / other), `sla_breach` flag. Consumers: Module E (financial-event recording); Module B (cost-basis flip provisional → finalized). **The D19 cost-basis seam. D-emitted.**
- **`ConsignmentReceiptRecorded`** (V2-only) — on V2 pre-positioning. Allocation reference, pre-positioned qty, Product Reference identity, storage location. Consumers: Module B; Module A (audit); Module E (V2 settlement-timing lineage). **D-emitted.**
- **`DiscrepancyResolutionRecorded`** — on discrepancy-path resolution. Path enum (one of six), cost-impact payload, original InboundEvent reference, resolving actor. Consumers: Module E (financial-event recording); Module A (audit). **D-emitted.**
- **`SupplierProducerLinkActivated`** / **`SupplierProducerLinkDeactivated`** — on link lifecycle transitions (§10). Audit.

### §16.2 Emitted — NewCo additions (3 events)

- **`POIssuedUnderNonActiveAgreement`** — on the operator-override path of the two-level gate (Producer-counterparty Level 1 only; not applicable to Supplier-not-Producer, DEC-094). PO id, ProducerAgreement reference, agreement state at override (`draft | superseded | terminated`), override actor, override reason (free-text). Consumers: Compliance / Logistics Lead review surface; audit. **D-emitted.**
- **`ReverseInboundEventRecorded`** — on producer-initiated unsold-stock recall (§9 + DEC-090). Recalled allocation reference, recalled qty + Product Reference identity, destination (return-to-Producer at launch), trigger source (`producer_initiated | operator_initiated` — the producer UI deferred, §3.6). Consumers: Module A (audit); Module E (financial-event recording for cost-basis unwind). **D-emitted.**
- **`ProcurementIntentCancelled`** — on PI `DRAFT → CANCELLED` or `COMMITTED → CANCELLED` (§5). PI id, prior state, cancellation trigger source (`voucher_voided | allocation_closed | operator_initiated`), upstream `VoucherVoided` reference (where applicable, §14.4). Consumers: Module A (audit); Module E (financial-event recording where the prior state was COMMITTED with PO issued — for cost-basis unwind coordination with the §13 DiscrepancyResolution path). **D-emitted.** *(AMB-D-4 [`ProcurementIntentCancelled` trigger-source enum coverage] is an acceptance-authoring backlog item, §18 — orthogonal.)*

### §16.3 Naming, ordering, versioning

**Naming**: lifecycle events use entity-prefix + state-suffix (`ProcurementIntentCreated`, `PurchaseOrderIssued`, `InboundEventPhysicallyAccepted`); operator-override events use semantic names (`POIssuedUnderNonActiveAgreement`); recall events use directional reverse-inbound naming (`ReverseInboundEventRecorded`). **All category-neutral — unchanged by the cascade** (only the consumed Module 0 events rename, §16.4). **Ordering**: cascading events within a single business transaction are emitted in causal order; consumers tolerate eventual-consistency arrival order across transactions. **Versioning**: events are schema-versioned; consumers (Module A, Module S, Module E, Module B) evolve independently within a major version with backward-compat.

### §16.4 Consumed by Module D (the cross-module inputs — incl. the R4 `SupplierPaymentCompleted` flip)

- **`SupplierPaymentCompleted`** — **emitted by Module E** on supplier payment clearing (R4, §3.3 / §14.7). PO id, paid amount, payment timestamp, ProducerAgreement `settlement_cadence` context. **Module D consumes it to settle/close the PO** (§6 — the CLOSED transition); financial-event-only (R1 — no Allocation-FSM role); atomic per PO (partial settlement deferred, OQ-20, §18). *(Module B also consumes it independently for the inventory `ownership_flag` transition — §14.5.)*
- **`InboundBatchDiscrepancy`** (from Module B, DEC-194) — Module D re-opens the InboundEvent into DISCREPANCY (§13.3 — manual-first at launch, N1).
- **`BottleQuarantineResolved`** (from Module B, DEC-191) — Module D drives the §13.4 cost-basis reconciliation where applicable (manual-first at launch, N1).
- **Voucher-issuance signal + `VoucherVoided`** (from Module S) — auto-fire V1/V2 PI creation; cancel a V1 PI (§14.4). `VoucherIssued` is the sell-through title signal (item F, §3.5); `VoucherShipped` available for a shipment-keyed leg.
- **`ProductReferenceActivated` / `ProductReferenceRetired`** (from Module 0) — drive the PR-active gate at PO line creation (§14.3). *(Renamed from `BottleReferenceActivated/Retired` — naming cascade; payload semantics identical.)*
- **`AllocationRecallTriggered`, `AllocationActivated`, `AllocationCounterpartyChanged`, …** (from Module A — §14.1); **`ProducerActivated/Retired`, `ProducerAgreement*`, `SupplierActivated`** (from Module K — §14.2).

---

## §17 Module Boundary Notes — what Module D does NOT do

For clarity on cross-module hand-offs:

- **Allocation entity, allocation FSM, allocation operations (qty mutation, visibility flip, commercial-terms updates, recall trigger), sub-pool partition, layered-breakability Layer 2** — Module A. Module D consumes Allocation state as the upstream supply primitive. *(The substantive Direct-Purchase deferral, D11, is Module D's — §3.1; Module A keeps the enum + uniform FSM seam.)*
- **Offer, customer-facing pricing, cart hold, checkout, voucher issuance against a customer order, sell-through, customer-facing INV1 / INV2 invoicing** — Module S / Module E. INV1 / INV2 is **customer-facing only** (DEC-089); Module D's supplier-counterparty payment is a separate domain.
- **NFC tag application, NFT minting, predecessor / successor recovery chain, serialized bottle identity, Bottle Page rendering** — Module B *(NFT decoupled, D12 — Module D's inbound events fire regardless; the non-serialized path is the universal fallback, Phase C item J)*.
- **Pick / pack / dispatch, shipment, late binding, customer-cellar render, in-transit display, delivery confirmation, physical reverse logistics for producer recalls** — Module C.
- **Producer / supplier settlement-payment execution, GL treatment, accounting recognition, financial-statement composition** — Module E + Xero (DEC-028 + DEC-072). **The settlement engine is deferred, D19 — operator-run first; Module D's recording is the seam.** **`SupplierPaymentCompleted` is E-emitted** (the payment executor — R4); Module D consumes it.
- **Customer-side eligibility (KYC, sanctions, marketing consent, Holds), Customer / Profile / Originating Club** — Module K + Module S.
- **ProducerAgreement state lifecycle / supersession chain** — Module K (§4.6 — the D19 settlement-cadence seam; KEEP-minimal). Module D **reads** ProducerAgreement state at PO issuance for the Level 1 gate (DEC-094); does not own the entity.
- **SupplierAgreement entity** — none at launch (DEC-084 — already-deferred, §18). Supplier commercial terms live on `Allocation.commercial_terms` (DEC-092); informal Supplier metadata at Module K Supplier.
- **Drop-ship paths, active consignment, B2B credit terms, liquid voucher resolution, CruTrade P2P trading, agency-model sourcing** — all OUT at launch (BMD §13 — already-deferred, §18). Every voucher → physical-shipment flow goes through NewCo physical custody (Module C).
- **Full reverse-inbound mechanics** — deferred to the roadmap (OQ-12/18, §18); at launch Module D records `ReverseInboundEventRecorded` with manual operator capability (DEC-090).

---

## §18 Deferred set & post-launch roadmap pointers (MVP)

**Module D takes ONE thin net-new spec deferral (Direct Purchase *use*) + the L-PP producer-write UIs (no backend cut); plus v1.1's already-deferred set is carried verbatim.** All feed [`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md) (which extends `greenfield/03-qa/qa.deferred.md`). **Do not re-cut the already-deferred items.** *(This section repurposes v1.1's §18 "Open Threads / Future-Flexibility Hooks" — the already-deferred list is carried here verbatim + the net-new MVP deferrals are added; see the §20 section-numbering note.)*

### §18.1 Net-new MVP deferrals (seamed — restore additively)

| # | Deferred item | Seam preserved (P1) | Restores with |
|---|---|---|---|
| §18.1a | **Direct Purchase *use*** (the operator-initiated PI-creation path + its at-PO-creation timing branch; no `direct_purchase` PIs at launch — D11 / Phase C item I) | The **uniform flow** (DEC-093) stays parameterized; the `trigger_source = operator_initiated` value, the `ownership = NEWCO`-at-issuance derivation, and the at-PO-creation timing rule are retained-but-unexercised; the §12.3/§12.4 chain is documented-but-not-exercised. **Re-enable is purely additive** (wire the operator PI surface back on). | The Direct-Purchase set (A enum-idle + **D**'s operator-PI/timing arm + B/E/S idles), restored together as a coordinated set when a deal needs it (Phase C item N). |
| §18.1b | **Producer-Portal procurement write UIs** (L-PP — every operation operator-driven via the Admin Panel; Module D retains zero producer writes; the producer-initiated recall UI + the deferred operator-initiated PI are the producer write surfaces) | **No backend capability is cut** — DEC-083 admin-parity is a backend contract; the operator path is functionally complete. The producer-facing write UIs build on the same backend. Producer Portal **read + full reporting (D23) is KEPT.** | The post-launch Producer-Portal write-UI workstream (the broader Admin-Panel → Producer-Portal self-serve buildout). |
| §18.1c | **D19 settlement *automation*** (the engine; recording KEPT) | Module D **keeps recording** all procurement/inbound financial events (the seam); the quarterly statement / payment execution / Xero GL is Module E's, deferred to operator-run. **Partial PO settlement** stays deferred (OQ-20 — `SupplierPaymentCompleted` atomic per PO). | The settlement engine (E + D + S + A restore together — Phase C item N; E's engine reads D's recorded events + A's lineage; the first close is months out). |
| §18.1d | **D16 Stage-8 reciprocal-cascade automation** (the automated round-trips; integrity core KEPT) | The integrity core (two-phase InboundEvent, documents-side 3-gate QC, DISCREPANCY state, 6-path enum, DEC-194 split, event consumers) is **FLOOR — KEPT**; only the **automated round-trips** defer (manual-first at launch, N1, §3.4). | Module B's physical-match / quarantine automation (B + D restore in lockstep — the automated round-trips are additive). |

### §18.2 v1.1 already-deferred / future-flex set (carried verbatim — do not re-cut)

Carried from v1.1 §18 with their existing re-introduction seams:

- **Full reverse-inbound mechanics** (Q-OQ-12 / OQ-18, DEC-152): reverse 3-gate QC, reverse cost-basis unwind precision (partial-recall accuracy, multi-event netting), partial-recall UX, recall-dispute path, automated return-shipment carrier coordination, reverse-discrepancy paths. At launch: event-recording + manual operator capability only (§9).
- **SupplierAgreement entity** (DEC-084): deferred at launch; Module D reads informal Supplier metadata. Pattern precedent: Module K §4.6 ProducerAgreement.
- **Partial PO settlement** (OQ-20): `SupplierPaymentCompleted` atomic per PO at launch; multi-event netting against one PO is a post-launch option.
- **Drop-ship inbound paths** (Q-AD-12, OQ-17): out at launch (BMD §13.3 + Appendix B.3); a future-DEC introduces a new InboundEvent variant / entity.
- **Active consignment + agency-model sourcing** (DEC-011), **B2B credit terms** (DEC-068), **liquid voucher resolution** (BMD §13.4), **CruTrade P2P trading**, **multi-warehouse** (OQ-16): out at NewCo launch (BMD §13); post-launch extensions require new entities + flow shapes.
- **PI batching optimisation for V1**: launch admits one-PI-per-voucher (simple) + batched-per-shipment (optimised); the optimised batching mechanics are post-launch tuning.
- **Configurable per-supplier SLA on InboundEvent Phase 1 → Phase 2** (Q-AD-6): 5-WD default at launch with a configurable-default surface; per-supplier override management UI / store is downstream tech work (DEC-073).

> **Tri-module restoration coherence (Phase C item N).** Module D participates in three coordinated restorations: **Direct Purchase (D11) = A + D + B + E + S** (D carries the substantive operator-PI/timing arm, §18.1a); **settlement engine (D19) = E + D + S + A** (D keeps the recording seam, §18.1c); **D16 Stage-8 automation = B + D** (D keeps the integrity core, §18.1d). It also stays neutral to the **NFT on-chain (D12)** decouple — Module D's inbound events fire regardless of the on-chain workstream (the non-serialized path is the universal fallback, Phase C item J). **No KEPT Module D item depends on a deferred one.**

---

## §19 Naming-cascade application (Phase C item A)

Module 0 v0.3-MVP §18 is the **source-of-truth** name table; this section records **how those names land in Module D** — and **what does NOT rename.** The change is **naming/contract only — zero behaviour change** (every event carries the same business signal; BR and PR denote the same key).

**What renames in Module D (the PR-referencing / Module-0-event-consuming prose only):**

| Touchpoint | v1.1 prose | v0.3-MVP prose | Wine-display alias retained |
|---|---|---|---|
| §5, §6 PI / PO line composition | "**Bottle Reference (BR)**" (the line identity) | "**Product Reference (PR)**" | Bottle Reference / BR |
| §10, §14.3, §15.4 SupplierProducerLink deref | "**BR → Wine Master → Producer**" deref | "**Product Reference → Product Master → Producer**" deref | Bottle Reference; Wine Master |
| §7, §8, §9 line/identity references | "Bottle Reference identity" on InboundEvent / ConsignmentReceipt / ReverseInboundEvent | "Product Reference identity" | Bottle Reference |
| §14.3, §16.4 consumed Module 0 events | `BottleReferenceActivated` / `BottleReferenceRetired` | `ProductReferenceActivated` / `ProductReferenceRetired` | — |
| §15.9 BR-D-CrossModule-2 | "`bottle_reference` to be `active`" | "**Product Reference** to be `active`" | bottle_reference (field alias) |

**What does NOT rename in Module D (the carve-outs — Phase C item A):**
- **Module D's own names are unchanged.** `ProcurementIntent`, `PurchaseOrder`, `InboundEvent`, `ConsignmentReceipt`, `ReverseInboundEvent`, `SupplierProducerLink` (entities); `ProcurementIntentCreated/BackCommitted/Cancelled`, `PurchaseOrderIssued/Acknowledged/InTransit`, `InboundEventPhysicallyAccepted`, `InboundEventCostFinalized`, `ConsignmentReceiptRecorded`, `ReverseInboundEventRecorded`, `DiscrepancyResolutionRecorded`, `POIssuedUnderNonActiveAgreement`, `SupplierProducerLinkActivated/Deactivated` (events); the attributes `sourcing_model`, `commercial_terms`, `ownership`, `trigger_source`, … — all **category-neutral, unchanged.**
- **Module D's consumed cross-module event names** that are not Module-0 catalog-identity events are unchanged — `SupplierPaymentCompleted` (E-emitted), `InboundBatchDiscrepancy` / `BottleQuarantineResolved` (Module B — physical-unit / wine-display names retained), `VoucherIssued` / `VoucherShipped` / `VoucherVoided` (Module S), `Allocation*` (Module A), `Producer*` / `Supplier*` / `ProducerAgreement*` (Module K).
- **"Bottle Reference" is retained everywhere as a wine-display alias** for Product Reference. The PI/PO line's PR-referencing attribute keeps `bottle_reference` as its retained wine-display field alias (the structural concept is the Product Reference; the literal field naming is tech-implementation, DEC-073).

**Rule of thumb:** rename only the PR-referencing / Module-0-event-consuming prose; keep Module D's own `ProcurementIntent*` / `PurchaseOrder*` / `InboundEvent*` / … names and every sibling's own names alone.

---

## §20 v1.1 inheritance & MVP re-baseline trace (audit appendix)

This appendix preserves the audit trail of Module D v0.3-MVP against its **frozen v1.1 predecessor** ([`../../reference/v1.1/01-prd/Module_D_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_D_PRD_v0.2.md), whose §19/§19.1 carry the v17 §8 / §3.10 / §3.8 / v13 Stage 2.1/2.3 + the Stage-8/Phase-C cascade trace) and the **ratified cut-sheet** + **Phase C reconciliation**. The load-bearing prose is the body above (DEC-074); this trace is for audit / diff.

> **Section-numbering note.** Module D is **KEEP-heavy with no structural entity insertion**, so **§1–§17 keep their v1.1 numbering** — the acceptance doc's PRD §-anchors (§5 PI, §6 PO, §7/§7.1 InboundEvent/discrepancy-split, §8 ConsignmentReceipt, §9 ReverseInboundEvent, §10 SupplierProducerLink, §11.x two-level gate, §12.x PO timing, §13.x DiscrepancyResolution, §14.x cross-module, §15.x BRs, §16.x events, §17 boundaries) **remain valid against this PRD.** Only **§0** is prepended (MVP framing). The trailing sections are repurposed: **§18** = the MVP deferred set (it **folds in v1.1's §18 "Open Threads / Future-Flexibility Hooks" verbatim** + adds the net-new MVP deferrals); **§19** = NEW (naming-cascade application — *v1.1's §19 v17 inheritance trace lives in the frozen v0.2 §19/§19.1*); **§20** = NEW (this trace); **§21** = cross-references (carried from v1.1 §20). v1.1's Appendix A (Wave 2 Divergence Summary vs v17) lives in the frozen v0.2 — not reproduced here (DEC-074: the body restates the substance).

| v0.3-MVP section | v1.1 (v0.2) anchor | Cut-sheet / Phase C | MVP disposition |
|---|---|---|---|
| §0 MVP scope at a glance | — (new) | cut-sheet §1; Phase C §1 | NEW — Phase D framing; KEEP-heavy + 1 thin Direct-Purchase defer + R1 + R4-consumer verdict. |
| §1 Module Purpose | v0.2 §1 | cut-sheet §1/§3.2; R1 | KEEP; **R1 landed** (the "load-bearing Wave 2 contract / `SupplierPaymentCompleted → AllocationActivated`" framing removed → financial-event-only); + core-loop-inbound-floor framing. |
| §2 Personas | v0.2 §2 | cut-sheet §3.5; Q4 | KEEP; + P2 operator-surface + L-PP zero-producer-writes (write UIs deferred; D23 reporting KEPT). |
| §3 Architecture (uniform flow) | v0.2 §3 | cut-sheet §3.1–§3.5 | KEEP; **R1 reconciled** in the activation-trigger bullet; + §3.1 D11 defer, §3.2 R1, §3.3 R4 (E-emits), §3.4 N1, §3.5 item F + N3, §3.6 L-PP, §3.7 D19 + cascade. |
| §4 Entity Model | v0.2 §4 | cut-sheet D.1–D.31 | KEEP all six entities; SupplierAgreement deferred (DEC-084); naming-cascade note. |
| §5 ProcurementIntent | v0.2 §5 | cut-sheet D.2–D.4; Q1 | KEEP; the operator-initiated (Direct-Purchase) PI deferred-with-seam; naming cascade on the line identity. |
| §6 PurchaseOrder | v0.2 §6 | cut-sheet D.5–D.9; item F / N3 / R4 | KEEP; **item F** (`VoucherIssued` title signal, no `SellThroughRecorded`); **N3** (PO-level title ledger [D, `VoucherIssued`] vs the inventory `ownership_flag` [B, `SupplierPaymentCompleted`] — same party, both `NEWCO`); **R4** (CLOSED consumes E's `SupplierPaymentCompleted`); naming cascade. |
| §7 InboundEvent (+ §7.1) | v0.2 §7 / §7.1 | cut-sheet D.10–D.14; floor chain 1 / DEC-194 | KEEP — FLOOR; documents-side 3-gate QC; receiving-party; §7.1 split = integrity core (manual-first reciprocal, N1). |
| §8 ConsignmentReceipt | v0.2 §8 | cut-sheet D.15 | KEEP; naming cascade on the line identity. |
| §9 ReverseInboundEvent | v0.2 §9 | cut-sheet D.23; L-PP | KEEP (minimal); producer-recall UI deferred (operator-records); full mechanics already-deferred → §18. |
| §10 SupplierProducerLink | v0.2 §10 | cut-sheet D.22; item A | KEEP + GENERALISE (the `Product Reference → Product Master → Producer` deref). |
| §11 PO Issuance Two-Level Gate | v0.2 §11 | cut-sheet D.19–D.21; DEC-094 | KEEP — FLOOR; ProducerAgreement Level 1 (Module K §4.6 seam) + Allocation Level 2 + operator override. |
| §12 PO Timing + activation framing | v0.2 §12 | cut-sheet D.6–D.9; R1 / R4 / item F | KEEP V1/V2 timing; **R1 reconciled** (§12.3/§12.4 — operator-publish, no `SupplierPaymentCompleted` FSM trigger); **R4** (E-emits in the §12.4 chain); Direct Purchase documented-but-not-exercised. |
| §13 DiscrepancyResolution (+ §13.3/§13.4) | v0.2 §13 | cut-sheet D.16–D.18; N1 / item H | KEEP the 6-path enum + DISCREPANCY state (integrity core); **N1** — the reciprocal cascades manual-first (the automated round-trips defer). |
| §14 Cross-Module Contracts | v0.2 §14 | cut-sheet §4; R1/R4/item F/N3 | KEEP; **§14.1** (no `SupplierPaymentCompleted` emit to A); **§14.5** (B bidirectional + the E-emitted `ownership_flag` consume — N3); **§14.7** (E emits `SupplierPaymentCompleted`, D consumes — R4; D19 seam); naming cascade on §14.3. |
| §15 Business Rules | v0.2 §15 | cut-sheet §3.2; R1/R4/item A | KEEP all; **BR-D-CrossModule-4 reconciled** (E-emitted, D-consumed, no FSM role — R1 + R4); naming cascade on BR-D-Link-1 / BR-D-CrossModule-2. |
| §16 Domain Events | v0.2 §16 | cut-sheet D.24/D.27; R4 | KEEP the catalogue; **`SupplierPaymentCompleted` moved emitted → consumed (R4, §16.4)**; D's other financial events stay D-emitted; consumed Module 0 events renamed. |
| §17 Module Boundary Notes | v0.2 §17 | cut-sheet D.30 | KEEP; + Direct-Purchase-deferred / D12-neutral / settlement-deferred / E-emits-`SupplierPaymentCompleted` notes; already-deferred set → §18. |
| §18 Deferred set & roadmap | v0.2 §18 (Open Threads) | cut-sheet §2 (DEFER rows); Phase C item N | NEW framing — folds v1.1 §18 verbatim + the net-new seamed deferrals (Direct-Purchase use, L-PP UIs, D19 automation, D16 automation). |
| §19 Naming-cascade application | — (new) | Phase C item A; Module 0 §18 | NEW — the cascade application + carve-outs. |
| §20 v1.1 & MVP trace | v0.2 §19/§19.1 (v17 trace) | — | NEW — this audit appendix (the v17 trace lives in the frozen v0.2 §19/§19.1). |
| §21 Cross-references | v0.2 §20 | — | Carried from v1.1 §20 (re-anchored to the MVP docs). |

Notation: *KEEP* = the v1.1 substance is restated in full NewCo language without semantic change; *cascade* = naming-only rename (Product Reference / Master + consumed Module-0 events), non-behavioural; *RECONCILE* = the R1/R4 contract-consistency fix (naming/contract only); *seamed defer* = the *use* is deferred with the backend/seam retained; *NEW* = Phase-D framing with no direct v1.1 predecessor.

---

## §21 Cross-references

- **v1.1 predecessor (frozen)** — [`../../reference/v1.1/01-prd/Module_D_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_D_PRD_v0.2.md). The source spec carried at full fidelity; never edited (plan R4). Its §19/§19.1 carry the v17 §8 / §3.10 / §3.8 / v13 Stage 2.1/2.3 + the Stage-8/Phase-C cascade trace; its §20 the v1.1 cross-references (DECs, qa.modD, BMD v0.6); its Appendix A the Wave 2 divergence summary.
- **Ratified cut-sheet** — [`../01-triage/Module_D_CutSheet_v0.1.md`](../01-triage/Module_D_CutSheet_v0.1.md). §2 inventory (scope), §3 module-specific changes (D11 / DEC-183 / D16 / D19 / L-PP + naming cascade), §5 acceptance delta, §6 the five ratified Qs. **⚠️ Its D.24 "Module D emits `SupplierPaymentCompleted`" is superseded by Phase C R4 (E-emits — §3.3); the cut-sheet stays as the Phase B record.**
- **Phase C reconciliation** — [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md). R1 (`SupplierPaymentCompleted` financial-event-only — Module D owns it, §5-R1), R4 (E-emits — Module D is the consumer side, §2-C/§5-R4), N1 (D16 manual-first, item H), N3 (party naming), item F (sale-vs-shipment title timing — resolves AMB-D-3), item I (Direct Purchase deferred), item N (tri-module restorations), §6 floor chain 1 (no-overselling — D's `InboundEventPhysicallyAccepted` creates B's InboundBatch).
- **Naming source of truth** — [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18 (the canonical name table + carve-outs). Applied here, not re-derived.
- **Settled siblings (the cross-module contracts D shares)** — [`Module_A_PRD_v0.3-MVP.md`](Module_A_PRD_v0.3-MVP.md) §3.2 (the Direct-Purchase joint defer) + §5.2/§11.3 (the DEC-183 + E-emits framing D mirrors — A owns no RECONCILE, is already aligned) + §4.1/§11.7 (the per-constituent `commercial_terms` lineage D reads) · [`Module_K_PRD_v0.3-MVP.md`](Module_K_PRD_v0.3-MVP.md) §4.6 (the ProducerAgreement settlement-cadence seam — the Level-1 gate + the deferred D19 read it) + §4.4/§4.5 (Producer / Supplier identity) · [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §3.4 (Product Reference — the PI/PO line-composition + SupplierProducerLink-deref rename).
- **MVP decisions register** — [`../04-decisions/MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md) (the thin index → authoritative docs; R1/R4/N1/N3 + D11/D19 rows).
- **Method + dials** — [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (P1/P2, the floor) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (D11 / D16 / D19 / L-PP).
- **Testable companion** — [`../03-acceptance/Module_D_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_D_Acceptance_v0.3-MVP.md).
- **Sibling v0.3-MVP PRDs** — Module 0 + K + A (written first; the catalog identity + Producer/Supplier/ProducerAgreement + Allocation D reads). Next in the cascade: **Module S** (the voucher-issuance trigger + `VoucherIssued`/`VoucherVoided` D consumes; R2) → B (the E-emits `ownership_flag` consumer + N1 manual-first lockstep) / C → E (R4 — the `SupplierPaymentCompleted` emitter), then the Admin-Panel PRD + Architecture.

---

*End of Module D PRD v0.3-MVP — Phase D re-baseline. **Verdict: KEEP-heavy on the core-loop inbound floor + the uniform procurement flow + the financial-event recording (the D19 seam); ONE thin in-module defer (Direct Purchase *use*) + the L-PP producer-write UIs, both seamed.** Module D **owns RECONCILE R1** (DEC-183 — `SupplierPaymentCompleted` financial-event-only, no Allocation-FSM role) and is the **consumer side of RECONCILE R4** (`SupplierPaymentCompleted` is **E-emitted / D-consumed** — the cut-sheet's "D-emits" is superseded; D's other procurement financial events stay D-emitted). **N1** (the D16 reciprocal cascades manual-first; integrity core KEPT), **N3** (the PO-level title ledger [D, keyed to `VoucherIssued`] vs B's inventory `ownership_flag` [keyed to `SupplierPaymentCompleted`] — same party, both `NEWCO`, two signals), and **item F** (`VoucherIssued` = the sell-through title signal; no `SellThroughRecorded`; no accounting position) all land. The two-phase InboundEvent → B InboundBatch floor, the documents-side 3-gate QC, the PO-issuance two-level gate, the SupplierProducerLink gate, and all procurement/inbound financial-event recording are KEPT whole; the naming cascade is applied. **Forward-consistency obligation: the Module B v0.3-MVP PRD must match D's D16 manual-first prose (N1).** **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E.*
