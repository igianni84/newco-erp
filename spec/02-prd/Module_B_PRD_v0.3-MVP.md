# NewCo ERP — Module B PRD (Inventory Authority + Digital Provenance) — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP scope of Module B)
- **Date**: 2026-06-08
- **Status**: **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; **nothing is promoted to `handoff/` until Phase E** (the single coherent handoff). Module B is **KEPT WHOLE on its inventory-integrity floor** (the two-layer no-overselling guard Layer 2, committed-inventory protection, InboundBatch, StockPosition, the four-way reconciliation discipline), with the **two heaviest critical-path levers of the whole exercise landing in-module: D12 (DECOUPLE the NFT/on-chain layer off the launch critical path — the per-bottle serialization workflow stays launch-ready) + D16 (SIMPLIFY the Stage-8 workflow *automation* → manual-first, integrity core KEPT).** Module B is the **consumer side of RECONCILE R4** (`SupplierPaymentCompleted` is **E-emitted**, B-consumed for the `ownership_flag` PRODUCER→CRURATED flip — the cut-sheet's "D-emits" framing is superseded) and lands editorial notes **N1** (D16 manual-first, identical with Module D) + **N3** (the CRURATED-vs-NEWCO two-ledger clarity).
- **Owner**: Paolo (decides). Claude recommends.
- **Testable companion**: [`../03-acceptance/Module_B_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_B_Acceptance_v0.3-MVP.md) — the MVP-scoped acceptance criteria (re-cut from the v0.1 DRAFT per the cut-sheet §5 delta; the MVP re-cut + the original validation + the Packet's EDITS_NEEDED reconciliation land together).
- **Predecessors / inputs** (the canonical record governs where this PRD is terse):
  - [`../../reference/v1.1/01-prd/Module_B_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_B_PRD_v0.2.md) — the **frozen v1.1 predecessor** (RELEASED 2026-05-09; Stage 8 close — the inventory-authority module). This v0.3-MVP carries its **inventory-integrity floor + serialization workflow + Bottle Page data contract at full fidelity**, **DECOUPLES** the on-chain layer (D12) and **SIMPLIFIES** the Stage-8 workflow automation to manual-first (D16) with seams, lands the **R4 consumer side + N1 + N3**, and applies the naming cascade; `greenfield/` is never edited (plan R4).
  - [`../01-triage/Module_B_CutSheet_v0.1.md`](../01-triage/Module_B_CutSheet_v0.1.md) — the **ratified cut-sheet** (Paolo 2026-06-07). §2 feature inventory (B.1–B.40) = the scope; §3 module-specific changes (D12 decouple / D16 simplify / R4-consumer / N1 / D17/D15 / L-PP + naming cascade) = the rewrite instructions; §5 = the acceptance delta; §6 = the seven ratified Qs. **⚠️ The cut-sheet predates the Phase C R4 flip — where any "D emits `SupplierPaymentCompleted`" reading survives (cut-sheets D.24/E.32), Phase C R4 (E-emits, B-consumes) wins (§0.3).**
  - [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) — the **coherence gate** (RATIFIED 2026-06-07). **R4** (E-emits `SupplierPaymentCompleted` — Module B is a consumer side, §2-C/§5-R4) + **N1** (D16 manual-first — land identically with Module D, item H) + **N3** (party naming — CRURATED vs NEWCO) + **item G** (the two-layer no-oversell build-sequencing — floor) + **item H** (D16 depth — B decided manual-first in lockstep; discharges D's KEEP-pending-B-review) + **item I** (Direct Purchase deferred) + **item J** (the NFT/on-chain DECOUPLE — graceful degradation everywhere; the NS path is the universal fallback) + §6 floor chains (no-overselling · committed-inventory · audit/retention).
  - [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18 — the **source-of-truth name table** for the cascade (applied here, not re-derived). **Module B keeps its physical-unit / wine-display names** (`SerializedBottle`, `InboundBatch`, `Case`, `StockPosition`, "Bottle Page", "bottle-days-in-storage") per the §18 carve-out; only the **PR-referencing / Module-0-event-consuming** prose renames (§0.8).
  - **The settled siblings (the cross-module contracts B shares — all drafted/ratified, all stable):** [`Module_D_PRD_v0.3-MVP.md`](Module_D_PRD_v0.3-MVP.md) — the **N1 lockstep sibling** (§3.4/§13.3 carries the manual-first depth this PRD matches) + the **inbound-floor upstream** (§14.5 frames the B↔D contract from D's side; D **emits** `InboundEventPhysicallyAccepted` → B creates the InboundBatch, DEC-195; the DEC-194 split D=documents / B=physical-match; D consumes B's `InboundBatchDiscrepancy` + `BottleQuarantineResolved` — manual-first, N1; D + B both consume the **E-emitted** `SupplierPaymentCompleted`, R4). [`Module_A_PRD_v0.3-MVP.md`](Module_A_PRD_v0.3-MVP.md) — Layer 1 (`qty − issued ≥ 0`, §7.1 — over-issuance is an **operation-level rejection, no event**) + the per-sub-pool ATP cache (§11.5.1, sourced from B's push — the **B→A push**) + `VoucherCancelled` (A-emitted, §11.5.2 → B's `InventoryShortfallDetected`) + `AllocationSerializationPlanChanged`. [`Module_S_PRD_v0.3-MVP.md`](Module_S_PRD_v0.3-MVP.md) — reads B's per-sub-pool Layer-2 ATP for the lesser-of storefront read (§8.6); emits `VoucherIssued` (→ B serialization workflow) + `VoucherShipped` (→ B NFT burn — **decoupled, D12**; NS fires `BottleShippedAsNonSerialized`). [`Module_K_PRD_v0.3-MVP.md`](Module_K_PRD_v0.3-MVP.md) — Producer customer-facing description for the Bottle Page. [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) — PR / Composite SKU / breakability Layer 1 + Bottle Page content (DEC-024).
  - [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (method, P1/P2, the floor) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (D12 NFT/blockchain KEEP-decoupled; D16 integrity core floor / workflows manual-first; D17 cellar basic; D15 recall minimal/manual; L-PP).
- **Methodology** (carried from v1.1; unchanged):
  - **DEC-072** — no accounting-policy positions. Module B **records** inventory/cost-basis **business events** (`InventoryAdjusted`, `InboundBatchCreated`, cost-basis attributes); **Module E records the financial event + Xero decides GL.** The cost-basis attribute is event data only.
  - **DEC-073** — product-spec layer only (entity concepts, business attributes, lifecycle states, business-meaningful enum values, domain-event names + business signals, module boundaries, invariants). Tech-implementation (column types, the WMS/Logilize integration internals, the ATP-cache push-vs-pull mechanics + staleness/latency SLAs, the NFC/NFT minting + on-chain encoding, the stocktake-timer mechanics, the Bottle Page render surface, UX/layout) is the dev team's call and is out of scope.
  - **DEC-074** — self-contained delivery document. Every entity is reintroduced in full NewCo language; a tech reader who has not read v1.1 can take this into the dev phase. The v1.1 inheritance trace (v17 §B / v0.1 preservation / Stage-8 supersession) is preserved in the frozen v0.2 §N; §N here adds the MVP re-baseline trace.
  - **MVP principles (plan §4.1):** **P1 — defer without burning bridges** (every decoupled/deferred item names the seam that makes the post-launch build additive, and points to the roadmap); **P2 — admin-first, self-serve-later** (back-office writes are operator-driven via the Admin Panel; the consumer storefront/Bottle Page is exempt). *Module B has **no producer writes** and **no consumer self-serve writes** — every workflow is operator-driven via the Admin Panel + Logilize/Vinlock execution; the one customer-facing surface is the read-only Bottle Page. **No backend capability is cut** (§0.8).*

---

## §0 MVP scope at a glance

**Verdict: Module B is KEPT WHOLE on its inventory-integrity floor — and is the SECOND genuinely cut-heavy module, the most consequential for the launch *date*, yet the shape is once again "decouple/simplify the heavy-but-non-floor dimension while the integrity floor stays whole."** Module B (Inventory Authority + Digital Provenance) owns the load-bearing inventory-integrity half of the scope floor — the two-layer no-overselling guard Layer 2, committed-inventory protection, the InboundBatch that realises the core-loop "bottle received into inventory" step, the StockPosition aggregation, and the four-way reconciliation discipline. **None of that is cut.** The two heaviest critical-path levers of the whole exercise land *in* Module B, but both leave the floor intact:

1. **D12 — DECOUPLE the NFT/on-chain layer off the launch critical path (the single biggest critical-path removal in the exercise) — but DECOUPLE ≠ DEFER, and only the *on-chain layer* decouples.** Refined at ratification (Paolo): once bottles arrive, warehouse operators apply the NFC tag + serial to each bottle, and the ERP + WMS must be ready to record that at launch even if minting is not. So the per-bottle **serialization workflow** — physical NFC tagging + serial capture + the `SerializedBottle` inventory-ledger record + the Logilize/WMS integration (B.10/B.11/B.23) — **stays launch-ready**; only the **NFT mint/burn + custodial wallet + on-chain recovery + Bottle-Page chain-link content** (B.12/B.13/B.34) decouple. At launch each `SerializedBottle` carries `nft_reference = NULL`, **back-filled** when the on-chain workstream lands — no rebuild. The brand VP is preserved (§0.1).

2. **D16 — SIMPLIFY the Stage-8 workflow *automation* → manual-first, KEEPING the integrity core — and the cut is thinner than its "single largest v1.1 increment" billing because the Stage-8 workflows are *already* single-supervisor-approval / operator-driven by spec.** The integrity core (the two-layer guard Layer 2, committed-inventory protection + `InventoryShortfallDetected`, cost-basis correctness, the four-way reconciliation *discipline*, the quarantine-before-trust *gate*) is **FLOOR — not a candidate.** The genuine cut is the **automated round-trips** — the Stocktake tolerance-driven auto-reconciliation + cadence automation (B.25), the QuarantineRecord automated cross-module cascades (B.29), and the automated reciprocal round-trips with Module D (B.24/B.29) → **manual-first operator handling via the Admin Panel** — landed **identically with Module D (N1)** (§0.2).

3. **Module B is the consumer side of RECONCILE R4 — the E-emits trap (naming/contract only — zero behaviour change).** The cut-sheets (D.24/E.32) read "Module D emits `SupplierPaymentCompleted`." **Phase C ratification (Paolo Q2) flipped this to E-emits:** **Module E emits `SupplierPaymentCompleted`** on payment clearing (the payment executor — three-actor split DEC-119; symmetric with the customer-side `AirwallexChargeExecuted`); **Module B consumes it** to flip the inventory `ownership_flag` PRODUCER → CRURATED (B.2). This PRD **reconciles B.2's loose "V1/V2 reach CRURATED at sell-through" prose to "on `SupplierPaymentCompleted`"** and keeps the **CRURATED inventory-ownership ledger distinct from Module D's NEWCO PO-level title ledger (N3)** — same party, two ledgers, two signals (§0.3).

4. **Module B owns the load-bearing inventory-integrity half of the scope floor — verified whole in composition (Phase C item G/M).** The two-layer no-overselling guard Layer 2 (per-sub-pool physical ATP `atp_serialized`/`atp_non_serialized`), the **B→A ATP push** (DEC-187), the storefront **lesser-of read** Module S consumes, committed-inventory protection + `InventoryShortfallDetected`, the **InboundBatch** (created from Module D's `InboundEventPhysicallyAccepted`, DEC-195), the **StockPosition** 5-dimension view, and the four-way reconciliation discipline are all named-floor. **None is cut** (§0.5).

5. **The non-serialized inventory ledger (§5) is doubly load-bearing — Layer 2 for NS stock AND the D12 decouple seam.** The four-counter NS ledger + NS ATP + commitment/reservation lifecycle (DEC-186) is what lets the launch critical path run without the on-chain workstream; the **NS path is the universal fallback every downstream degrades to gracefully** (Phase C item J).

6. **Module B is back-office / warehouse-ops — L-PP / P2 is trivial: zero producer writes, zero consumer self-serve writes; the one customer-facing surface is the read-only Bottle Page; no backend capability is cut (§0.8).**

**The launch scope at a glance (KEEP / DECOUPLE / SIMPLIFY / RECONCILE — confirm against the cut-sheet §1):**

| Disposition | Scope | Where |
|---|---|---|
| **KEEP — FLOOR** | The two-layer no-overselling guard Layer 2 (per-sub-pool ATP) + the **B→A push** + the storefront lesser-of read; **InboundBatch** (from D's `InboundEventPhysicallyAccepted`); **StockPosition** 5-dimension view; **committed-inventory protection + `InventoryShortfallDetected`**; **cost-basis correctness** (provisional→finalized); the **four-way reconciliation discipline**; the **quarantine-before-trust gate** + the **DEC-194 split** + the DISCREPANCY state; the NS four-counter ledger; the `ownership_flag` ledger; the cross-module event contract. | §2, §3, §5, §8, §10, §11, §13, §14 |
| **KEEP — launch-ready (D12 boundary at the *mint*, not the *tag*)** | The per-bottle **serialization workflow** — physical NFC tagging + serial capture + the `SerializedBottle` ledger record + WMS/Logilize integration — with `nft_reference` **nullable + back-fillable**; the **Bottle Page** (renders non-NFT content at launch); **Stream B1** storage-location (the R3 migration target). | §4, §6, §15, §16 |
| **DECOUPLE (D12 — off the launch critical path; DECOUPLE ≠ DEFER)** | The **on-chain layer only**: NFT mint/burn + custodial wallet + the C→S→B burn chain (§9); the on-chain recovery chains (§17.1/§17.3, and the NFT-burn-as-destroyed portion of §17.4); the Bottle-Page chain-link content (§16 NFT portions). Seam = `nft_reference` nullable + the **re-scoped EXT-1 gate** + the NS universal fallback. | §0.1, §6, §9, §16, §17 |
| **SIMPLIFY (D16 — workflow *automation* → manual-first; integrity core KEPT; N1)** | The Stocktake tolerance-driven auto-reconciliation + cadence automation (§12.4); the QuarantineRecord automated cross-module cascades (§14.4); the automated reciprocal round-trips with Module D (`InboundBatchDiscrepancy` auto-reopen §11; `BottleQuarantineResolved` cost-basis reconciliation §14.4) → manual-first operator handling. | §0.2, §11, §12, §13, §14 |
| **RECONCILE R4 (consumer side — E-emits, naming/contract only)** | `SupplierPaymentCompleted` is **E-emitted / B-consumed** → `ownership_flag` PRODUCER→CRURATED; reconcile §2.2's sell-through prose; N3 CRURATED-vs-NEWCO two-ledger clarity. | §0.3, §2.2, §19.2 |
| **GENERALISE (naming cascade — naming only, zero behaviour change)** | `Bottle Reference → Product Reference (PR)` (BR retained as wine-display alias) in catalog-identity reads; `Wine Master/Variant → Product Master/Variant`; consumed Module 0 events `BottleReferenceActivated/Retired → ProductReferenceActivated/Retired`. **B's own physical-unit names are unchanged.** | §0.8, §4, §7, §8, §16 |
| **DEFER (carried verbatim — already-deferred; do not re-cut)** | Third-party custody + `THIRD_PARTY` ownership; ConsignmentPlacement + active-consignment SELL_THROUGH; `AGENCY` sourcing; multi-warehouse; sub-warehouse display; `consumption`/`transfer` adjustment placeholders; richer Bottle Page media; post-shipment re-tagging; full reverse-inbound mechanics; smart-contract audit/governance; the NFT working-hypothesis cluster. | §21 |
| **DROP** | — (nothing dropped). | — |

**The B↔C contracts named for the Module C session (B is upstream of C — the dependency-order seam; §0.6):** (1) **StockPosition** (C reads at late-binding pick); (2) **serialized-bottle identity** (C late-binds the physical bottle reading B's bound serial; NS → allocation+quantity batch tuple); (3) **Stream B1 storage-location** (B owns — the R3 migration target; the shared Logilize discrepancy queue B+C, DEC-141); (4) **the Bottle Page link + inventory summary** (C reads for the cellar render; the Cellar data source switches Logilize-direct → B-summary, DEC-188/DEC-154); (5) **the NFT-burn chain** (C's `ShipmentDispatched` → S `VoucherShipped` → B NFT burn — decoupled, D12; NS fires `BottleShippedAsNonSerialized`).

**Two Paolo-track action items (time-sensitive — NOT Phase-D scope decisions, but they must not slip; Phase C item J / Q3):** **(1) schedule the EXT-1 blockchain-expert review now** (or it becomes the launch critical path); **(2) confirm the DEC-124 tag-content back-fill design** (tags apply at launch with serial + Bottle Page URL; the on-chain reference back-fillable — the pre-launch NFC tag-stock procurement lead-time makes this time-sensitive). Feed the feasibility into the Phase-E re-estimate.

**The seven ratified scope confirmations (cut-sheet §6, Paolo 2026-06-07):**
- **Q1 — D12 DECOUPLE the NFT/on-chain layer only; the per-bottle serialization workflow stays launch-ready** (NFT mint feature-flagged off; `nft_reference` NULL + back-filled). Posture = option 2 (default fallback B1 = NFC-opt-out allocations ship non-serialized; tech-team feasibility-check B2 = serialized-minus-NFT + back-fill, confirmed in the Phase-E re-estimate). Owned action items: schedule EXT-1 now; confirm DEC-124 tag-content back-fill. (§0.1.)
- **Q2 — KEEP the integrity core (FLOOR); SIMPLIFY the Stage-8 workflow automation → manual-first**, in lockstep with Module D's KEEP-pending-B-review (now discharged — Phase C item H). (§0.2.)
- **Q3 — the no-overselling / committed-inventory / InboundBatch floor + build-sequencing confirmed whole** at the integrated launch (a sequencing confirmation, not a cut → Phase-E re-estimate flag). (§0.5.)
- **Q4 — Module S's four ratified contracts honoured whole** (the lesser-of ATP read; `VoucherIssued` → serialization; `VoucherShipped` → NFT burn — rides the decouple; the Bottle Page is B's). (§0.6 / §10.4 / §16.)
- **Q5 — D17 cellar contribution → basic** (Bottle Page link + warehouse-level inventory summary); **D15 recall → minimal/manual** (event-record-only reverse-stock side). (§0.7.)
- **Q6 — L-PP / P2 confirmed** — zero self-serve writes; the Bottle Page read-only/storefront-exempt; no backend cut. (§0.8.)
- **Q7 — the already-deferred set carries verbatim; the three Phase-C cascades** (Module A's ATP-cache cadence; the E-emitted `SupplierPaymentCompleted`; Module C's 5→4 Logilize-stream reduction + Cellar data-source switch) handled at reconciliation. (§21, §0.3, §0.6.)

> **Section-numbering note.** Module B is **cut-heavy but takes *no structural entity insertion*** (every entity exists in v1.1; the cuts are a decouple + an automation simplify, not a re-model), so **§1–§22 keep their v1.1 numbering** — the acceptance doc's PRD §-anchors (§3.4 InboundBatch FSM, §4.2 SerializedBottle FSM, §7.1 Case FSM, §12.2 Stocktake FSM, §14.1 QuarantineRecord, §16.x Bottle Page, §17.x recovery, §18 BRs, §19 events, §20 personas) **remain valid against this PRD.** Only **§0** is prepended (MVP framing + the change threads §0.1–§0.8). §18 (BRs), §19 (events), §21 (out-of-scope), §22 (open threads) are restated at MVP scope; **§N** (the audit appendix) adds **§N.4 — the MVP re-baseline trace** (the v17 inheritance / v0.1 preservation / Stage-8 supersession traces live in the frozen v0.2 §N). The naming-cascade application lands in §0.8 + §N.4.

### §0.1 D12 — DECOUPLE the on-chain layer; the serialization workflow stays launch-ready (the headline; Paolo brand call, refined at ratification; Phase C item J)

The locked dial KEEPS the NFC/NFT value-proposition but DECOUPLES it off the launch critical path — **DECOUPLE ≠ DEFER.** At ratification Paolo sharpened the boundary on a load-bearing operational fact: **once bottles arrive in the warehouse, WH operators still apply the NFC tag + serial to each bottle, and the ERP + WMS must be ready to record that at launch — even if minting / the NFT are not ready.** That governs the cut.

1. **The decouple boundary is at the *mint*, not the *tag*.** **KEEP / launch-ready:** the per-bottle serialization workflow — physical NFC tag application + serial capture + the `SerializedBottle` inventory-ledger record + the Logilize tagging/receiving integration (§4, §6, §11; B.10/B.11/B.23). **DECOUPLE off the critical path (behind the re-scoped EXT-1 gate):** the NFT mint/burn on Avalanche + the custodial wallet (§9; B.12), the on-chain recovery chains (§17.1/§17.3; B.13), and the Bottle-Page chain-link content (§16 NFT portions; B.34). At launch each `SerializedBottle` carries `nft_reference = NULL` (the spec already admits NULL); the NFT is **back-filled** when the on-chain workstream lands — no rebuild.

2. **Two launch-ready paths, not one.** The launch critical path (inbound → stock → ATP → no-oversell → ship) runs on **(i) serialized-minus-NFT** (per-bottle tag + serial + ledger; NFT deferred/back-filled) and **(ii) the non-serialized path** (§5 — zero provenance, the residual fallback for NFC-opt-out allocations; the opt-out `non_serialized_offer_admitted` is Module A's lever). The two-layer no-oversell guard (FLOOR) composes **per sub-pool** (§10.5), so it is intact across both — even when the serialized sub-pool is dormant.

3. **The seam (P1).** (a) `nft_reference` is **nullable + back-fillable** on the `SerializedBottle` record; (b) the **re-scoped EXT-1 feature-flag gate** in the acceptance doc gates only the **NFT/on-chain** criteria — **not** the physical-tagging / serial / `SerializedBottle`-ledger criteria, which are launch-ready (those move to the un-gated launch-floor set; acceptance §0); (c) the **NS universal fallback.** **§17.4's inventory-ledger write-off side is KEPT** (`BottleDestroyedInCustody` → `written_off` lifecycle + `InventoryAdjusted` damage + Module A pool-debit — part of the adjustment floor); only the NFT-burn-as-destroyed portion decouples.

4. **Every downstream degrades gracefully to the NS path (Phase C item J — no orphan).** Module S `VoucherShipped → B NFT burn` rides the decouple (the NS path fires `BottleShippedAsNonSerialized`); Module C dispatches regardless of the on-chain layer (records the bound serial for serialized stock; allocation+quantity batch tuple for NS); **no Module E NFT touchpoint**; the Bottle Page renders the non-NFT content (producer profile, tasting notes, allocation context, waypoint trail) and the chain-link lights up when the workstream lands. The NS path is the **universal fallback the floor does not depend on**.

5. **Honest framing.** D12 takes Avalanche + the custodial wallet + the mint/burn lifecycle + the four on-chain recovery scenarios + the EXT-1 blockchain-expert-review gate off the launch critical path (the single biggest critical-path removal — dials weight XL). But it is a **decouple, not a delete**, and *only the on-chain layer* decouples — the per-bottle tagging + serial + ledger is launch-ready. If the on-chain workstream lands by launch the full VP ships; if it slips, serialized stock still ships with per-bottle tags + serial + ledger tracking (NFT back-filled later), NFC-opt-out allocations ship non-serialized, and the on-chain layer lights up additively post-launch.

**Two Paolo-track action items (time-sensitive — carry; §0):** (1) **schedule the EXT-1 blockchain-expert review now** (or it becomes the launch critical path); (2) **confirm the DEC-124 tag-content back-fill design** (tags apply at launch with serial + Bottle Page URL — online verification works; the on-chain reference back-fillable — offline verification rides the decoupled workstream; pre-launch NFC tag-stock procurement lead-time makes this time-sensitive). Whether tags are rewriteable / two-pass / URL-only-at-launch is tech + procurement (DEC-073) — the feasibility item for the tech team / blockchain review, fed into the Phase-E re-estimate. **Do not re-cut** the already-deferred NFT cluster (smart-contract audit/governance EXT-3; the DEC-120/121/122/124/131 working-hypothesis cluster) — carry verbatim (§21).

### §0.2 D16 — SIMPLIFY the Stage-8 workflow automation → manual-first; integrity core KEPT (the delicate joint review; N1 — identical with Module D; Phase C item H)

The cut-sheet directed a delicate, joint-with-A/D SIMPLIFY hunt on the Stage-8 inventory-control workflows, with the explicit caveat *"integrity core = floor; workflow sophistication = SIMPLIFY candidate … be honest where a simplification risks integrity."* **At Phase C, Module B decided the depth in lockstep with Module D's KEEP-pending-B-review (now discharged — item H).** Module-B-side treatment:

1. **The integrity core is FLOOR — verified whole, not a candidate.** The two-layer no-oversell guard Layer 2 (§10.5), committed-inventory protection + `InventoryShortfallDetected` (§13.4/§13.5), cost-basis correctness (§3.2), the four-way reconciliation *discipline* (§2.4), and the quarantine-before-trust *gate* (§14) are all named-floor. **None is simplified** — these are integrity, not sophistication.

2. **The honest calibration — thinner than the "single largest v1.1 increment" billing.** Every Stage-8 workflow is **already single-supervisor-approval / operator-initiated by spec** (the adjustment proposal→approve §13.1 is already manual; the QuarantineRecord resolution §14.2 is already supervisor-driven; the Stocktake §12 is already supervisor-planned). There is no rich approval-tier FSM to gut — **the entities *are* the integrity core + the seam.** The genuine cut is the automated round-trips.

3. **The genuine D16 SIMPLIFY — defer the automated round-trips + reconciliation automation → manual-first operator handling via the Admin Panel:**
   - **Stocktake (§12.4):** defer the tolerance-driven auto-reconciliation engine + cadence automation → operator-scheduled manual counts + manual variance review, booking variances through the kept adjustment path (§13).
   - **QuarantineRecord (§14.4):** defer the automated cross-module cascades on resolution → manual operator-triggered follow-ups.
   - **Reciprocal cascades with Module D (§11 / §14.4):** defer the automated `InboundBatchDiscrepancy` auto-reopen + the `BottleQuarantineResolved` cost-basis reconciliation → **manual operator discrepancy-open + manual cost-basis follow-up within the 5-WD window**, **identically to Module D's manual-first depth** (Module D §3.4/§13.3).
   - **Adjustment (§13.1):** already manual — minimal trim.

4. **The seam (P1) is the integrity-core entities + events + the DEC-194 split.** KEEP InboundBatch, StockPosition, the ATP layers, `InventoryShortfallDetected`, the **DEC-194 split** (D=documents / B=physical-match), the **DISCREPANCY state + Module D's 6-path resolution enum** + the event consumers, the **Stocktake entity + `StocktakeReconciled`**, the **QuarantineRecord gate + 4 resolution paths + immutability** + the events (`BottleQuarantined`/`BottleQuarantineResolved`) — so the automated round-trips are **additive** when the workflow automation lands post-launch.

> **N1 (Phase C item H) — landed identically with Module D.** This PRD's manual-first posture **matches Module D's §3.4/§13.3 prose** (D's reciprocal-cascade depth: the operator opens the discrepancy + records the resolution path manually within the 5-WD window). B's Q2 ratification **discharges** Module D's KEEP-pending-B-review — the depth is decided on both sides. The D↔B interlocks read consistently.

### §0.3 R4 + N3 — `ownership_flag` PRODUCER→CRURATED on the E-emitted `SupplierPaymentCompleted` (the E-emits trap; Phase C §2-C/§5-R4)

This is the **single highest-risk reconciliation this PRD lands** — and it corrects a trap. The cut-sheets (D.24/E.32) and the v1.1 PRD §2.2 carry framings reading "Module D emits `SupplierPaymentCompleted`" (v1.1 §2.2 cites "Module D §12") and "V1/V2 reach CRURATED at sell-through." **Phase C ratification (Paolo Q2) flipped this to E-emits:** Module D has no independent trigger — it would wait on Module E's confirmation that the payment cleared. **Payment execution is Module E's** (the Airwallex/Xero rails, DEC-014/028; the three-actor split DEC-119 assigns PAYMENT to E; symmetric with the customer-side `AirwallexChargeExecuted`, which E emits).

**The corrected contract (the precise pin — Module B's consumer side):**
- **Module E emits `SupplierPaymentCompleted`** — on payment clearing (E is the payment executor). At launch: when the operator records the manual supplier payment in E's finance surface (settlement operator-run, D19 deferred); post-launch: E's settlement engine. Atomic per PO (partial PO settlement deferred, OQ-20).
- **Module B consumes it** → the inventory `ownership_flag` transition **PRODUCER → CRURATED** (§2.2; the bottle becomes NewCo-owned because NewCo has paid for it). The consumer behaviour is unchanged — **only the source is corrected (from E, not D)** and the trigger is named (the payment moment, not "sell-through").
- **Module D also consumes it (independently)** → settle/close the PO (the PO-level title ledger; Module D §6/§14.5).
- **Direct-Purchase no-op:** for `direct_purchase` the InboundBatch is `CRURATED` from creation → no PRODUCER→CRURATED transition. Doubly moot at launch (Direct Purchase deferred, §0.4).

**Reconcile B.2's prose (landed in §2.2):** the loose "V1/V2 reach `CRURATED` at sell-through" is reconciled to "**on `SupplierPaymentCompleted`**" — the inventory `ownership_flag` ledger keys to **payment**, distinct from the PO-level title ledger, which keys to the **sale/shipment signal** (`VoucherIssued`).

> **N3 — two distinct ownership ledgers, same party (Phase C §5-N3).** The system carries **two** PRODUCER→NewCo ownership ledgers, keyed to **different signals** at **different moments**:
>
> | Ledger | Owner | Enum / flag | Transition keyed to |
> |---|---|---|---|
> | **Inventory `ownership_flag`** | **Module B** (this PRD, §2.2) | `ownership_flag` `PRODUCER → CRURATED` (DEC-185 — `CRURATED`) | **`SupplierPaymentCompleted`** (E-emitted; the payment moment — R4) |
> | **PO-level title** | **Module D** (consumer of the same E-emitted event) | `ownership` 3-value enum `PRODUCER \| NEWCO \| THIRD_PARTY` (DEC-085 — `NEWCO`) | the **sale/shipment signal** (`VoucherIssued` sell-through — Module D item F) |
>
> `CRURATED` (inventory flag) and `NEWCO` (PO-level title) **denote the same real-world party** — pre-existing v1.1 naming (DEC-185 retained `CRURATED` for the inventory flag; DEC-085 renamed the PO ownership enum's middle value to `NEWCO`). The `OwnershipTransitioned` cascade prose in this PRD (§2.2, §19) is unambiguous about which ledger uses which label and which signal each keys to — so the inventory-ownership ledger (B, keyed to `SupplierPaymentCompleted`) and the title ledger (D, keyed to `VoucherIssued`) are never conflated.

**This PRD keeps `SupplierPaymentCompleted` in Module B's *consumed* catalogue (§19.2), sourced from Module E** (the v1.1 already listed it under §19.2 with a "Phase C cascade question" note — now settled as E-emits). **Module B never emits or derives `SupplierPaymentCompleted`** (the cut-sheets' "D-emits" + any "B-derives-at-sell-through" framing is superseded). **Naming/contract only — money moves identically; B's `ownership_flag` flip + provenance immutability across the transition are intact.**

### §0.4 Item I — Direct Purchase `→CRURATED`-at-issuance not-exercised-at-launch (Phase C item I)

Direct Purchase is **deferred at launch** (confirmed at ratification — no launch deal; passive V1 + V2 only). Module B's `direct_purchase → CRURATED`-at-issuance ownership derivation (§2.2 — the InboundBatch is `CRURATED` from creation for `direct_purchase`) is therefore **not-exercised-at-launch** — a **scope annotation, not a cut** (consistent with Module A keeping the `direct_purchase` enum + Module D deferring the use; all five of A/D/B/E/S idle the path in lockstep). The `sourcing_model` discriminator + the at-issuance derivation are **retained as the seam**; re-enable is additive. At launch V1/V2 stock reaches `CRURATED` via the E-emitted `SupplierPaymentCompleted` (§0.3) — the Direct-Purchase no-op is doubly moot.

### §0.5 The inventory-integrity floor + the A↔B / D↔B / S↔B build-sequencing (FLOOR — Phase C item G/M)

The no-overselling / committed-inventory / InboundBatch / StockPosition / four-way-reconciliation floor is **verified whole** (§2.4, §3, §8, §10, §13). The pieces Module B holds (all KEPT, whole) compose with the siblings per sub-pool:

- **No-overselling — B Layer 2 + the B→A push** (§10): the per-sub-pool physical ATP (`atp_serialized` + `atp_non_serialized`) + the push on every inventory state change, composing **Module A's Layer 1 (`qty − issued ≥ 0`, §7.1) ∧ Module S's lesser-of storefront read (§8.6) ∧ Module C's no-oversell-at-pick StockPosition read**, strongly consistent, per sub-pool. *(Composition note: Module A v0.3-MVP frames Layer-1 over-issuance as an **operation-level rejection — there is NO `AllocationCapacityExhausted` event**; Module B's Layer-2 / no-oversell prose composes with the rejection, not a non-existent event. Module S flagged this drift and resolved it consistently; Module B aligns — §10.5.)*
- **Committed-inventory protection** (§13.4/§13.5): Module A's `VoucherCancelled` release primitive (A-emitted, the single release primitive per DEC-099) ↔ Module B's `InventoryShortfallDetected` — the proposal that would reduce committed inventory below outstanding vouchers is rejected upfront; it proceeds only after `VoucherCancelled` releases the commitment. Applies identically to NS (§5.5).
- **Cost-basis correctness** (§3.2): provisional at `InboundEventPhysicallyAccepted`; finalized at `InboundEventCostFinalized`; availability/ATP independent of cost finalization.
- **The four-way reconciliation discipline** (§2.4) + the quarantine-before-trust gate (§14) + the DEC-194 split (§11).

**Build-sequencing (a confirmation, not a cut → Phase-E re-estimate flag, Phase C item G/Q5).** Module B is build-phase 5; Module A/D are phase 3; Module S is phase 4. The two-layer guard requires **Module B's Layer-2 push pipeline + InboundBatch + StockPosition + per-sub-pool ATP** to be **integration-ready by the integrated launch** — because Module A's Layer 1, Module S's storefront read, and Module C's no-oversell-at-pick all depend on B's side being live. The build *phases* are an implementation sequence within ONE coherent launch (no piecemeal handoff), not a launch-staging — so the floor is whole at the integrated launch **provided the build workplan sequences B's floor artefacts to be integration-ready by it.** Flag for the Phase-E re-estimate.

**The D12 interaction (important).** At launch, if the on-chain workstream is decoupled-and-slipping, **Layer 2 = the NS sub-pool ATP** (§5) — so **B's NS ledger + InboundBatch + StockPosition + the B→A push are the load-bearing floor at launch, independent of the decoupled serialized/NFT cluster.** The floor does not depend on the on-chain workstream (Phase C item J — no orphan).

### §0.6 The B↔C contracts named for the Module C session (B is upstream of C)

Module B is upstream of Module C (the dependency-order seam — C reads B's ratified output). The five contracts B owns, named here in B's §-anchors so the Module C session can consume them:

1. **`StockPosition`** (§8) — Module C reads it at late-binding pick (no-oversell-at-pick, per sub-pool, per case-config — whole-case shippable quantity when `effective_unbreakable = true`).
2. **Serialized-bottle identity** (§4) — Module C late-binds the physical bottle reading B's bound serial for serialized stock; for non-serialized stock the bind is at the **allocation + quantity** (batch tuple) granularity (no per-bottle serial — the D12 NS path).
3. **Stream B1 (storage-location)** (§15.1) — B owns it (the **R3 migration target** — storage-location migrated Module C → Module B; Module C's 4-fulfilment-stream contract consumes the *other* streams). The **shared Logilize discrepancy queue (B+C, DEC-141)** spans B's stocktake/quarantine/discrepancy + C's fulfilment discrepancies (§15.3; also flagged for the 9th Admin-Panel PRD).
4. **The Bottle Page link + inventory summary** (§16, §16-D17 contribution) — Module C reads these for the cellar render (D17 / DEC-154); the **Cellar data source switches from Logilize-direct to B-summary (DEC-188)**.
5. **The NFT-burn chain** (§9.5) — originates at Module C's `ShipmentDispatched` → Module S `VoucherShipped` → **Module B NFT burn** (decoupled — D12; NS fires `BottleShippedAsNonSerialized`).

### §0.7 D17 cellar-render contribution → basic + D15 recall reverse-stock side (cut-sheet §3.5; ratified Q5)

- **D17 (cellar render → basic, mostly Module C):** Module B's contribution to the six-module cellar read (DEC-154) is the **Bottle Page link + warehouse-level inventory summary** — kept minimal (§16). Sub-warehouse granular display (DEC-153) is already-deferred — carry (§21). The cellar render UX is Module C scope (the next session; the Cellar data source switches Logilize-direct → B-summary, §0.6).
- **D15 (producer recall → minimal/manual):** Module B's reverse-stock side of the **unsold-only** producer recall is **event-record-only / manual.** ISSUED vouchers are immune (Module S DEC-117); the recall matches Module A + Module D (event-record-only). Module B records the inventory-side reversal; the physical reverse logistics are Module C (deferred). Full reverse-inbound mechanics are already-deferred (OQ-12/18, DEC-152) — carry (§21).

### §0.8 L-PP producer-write treatment (P2) + the naming cascade (cut-sheet §3.6; ratified Q6 / Phase C item A)

**L-PP (P2) — Module B has *no* producer writes and *no* consumer self-serve writes.** Module B is back-office / warehouse-ops; every workflow (stocktake, quarantine, adjustment, recall recording, destruction recording, NFC re-tag authorisation) is **operator-driven via the Admin Panel + Logilize/Vinlock execution** (the personas §20.1 are all ops/back-office). Module B's **one** customer-facing surface is the **read-only Bottle Page render — zero customer identifiers (DEC-024), storefront-exempt** (P2). **No backend capability is cut** — there is no producer/consumer write UI to defer (the cleanest L-PP application of the triage). The consolidated operator-surface inventory (the shared Logilize discrepancy queue + the manual stocktake/quarantine/adjustment surfaces) lives in the 9th Admin-Panel PRD (it references this PRD's operations rather than re-specifying them).

**Naming cascade (Phase C item A — naming/contract only, no behaviour change).** Per Module 0 v0.3-MVP §18 (the source of truth), Module B renames only its **PR-referencing / Module-0-event-consuming** prose; **its own physical-unit / wine-display names are retained** (`SerializedBottle`, `InboundBatch`, `Case`, `StockPosition`, "Bottle Page", "bottle-days-in-storage" — the physical unit is a bottle for the `WINE` product type, per Module 0 guardrail 5 / §16). Touchpoints: `Bottle Reference (BR) → Product Reference (PR)` at SerializedBottle catalog identity (§4.1), Case config (§7.3), StockPosition's `(PR, warehouse, case_config, allocation, ownership)` intersection (§8.1), and the Bottle Page reads (§16.2); `Wine Master/Variant → Product Master/Variant` in the catalog-identity + Bottle-Page reads; the consumed Module 0 events `BottleReferenceActivated/Retired → ProductReferenceActivated/Retired`, `Wine* → Product*`. **"Bottle Reference" is retained as a wine-display alias; payload semantics identical.** The full application table is at §N.4.

---

## §1 Module Scope

### §1.1 In scope

Module B v0.3-MVP specifies, at the product-spec layer per DEC-073, the following load-bearing surfaces. **Inventory-ledger surfaces are the floor (KEPT whole); the digital-provenance serialization workflow is KEEP / launch-ready; the on-chain layer is DECOUPLED off the critical path (D12).**

**Inventory-ledger surfaces (FLOOR — KEPT whole):**

- **InboundBatch entity** + cost-basis flow + serialization-plan target + lifecycle (§3 + §11). Module B owns the InboundBatch; **Module D triggers creation via `InboundEventPhysicallyAccepted`** (DEC-195 — the no-overselling Layer-2 / committed-inventory floor, Phase C floor chain 1); cost basis flows provisional → finalized via Module D `InboundEventCostFinalized`.
- **SerializedBottle entity** + lifecycle (§4 + §6) — **launch-ready** (the per-bottle ledger record + serial identity); `nft_reference` **nullable + back-fillable** (D12 — the NFT decouples, §0.1).
- **Non-serialized inventory at InboundBatch level** (§5) — four counters; NS sub-pool ATP; permanent / fulfillable state (the doubly-load-bearing Layer-2-for-NS + D12 decouple seam).
- **Case entity** + 3-state integrity FSM (§7) — recorder-not-gatekeeper (layered breakability composed at Module 0 / A / S / C).
- **StockPosition aggregated view** (§8) — 5-dimension aggregation `(PR, warehouse, case_config, allocation, ownership)`; sellable + shippable; sub-pool decomposition feeding Module A + Module S + Module C.
- **QuarantineRecord entity** (§14) — the quarantine-before-trust gate; four resolution paths; resolved records immutable (integrity core; the automated cascades defer, §0.2).
- **Stocktake** (§12) — entity + 4-state FSM; the variance-computation discipline (integrity); the tolerance-driven auto-reconciliation + cadence automation defer to manual-first (§0.2).
- **Inventory-adjustment workflow** (§13) — `InventoryAdjusted` + `InventoryShortfallDetected`; single-supervisor-approval; committed-inventory protection (FLOOR).
- **Receiving physical-match check** (§11) — the DEC-194 split (Module D = documents-in-order; Module B = physical-match); `InboundBatchDiscrepancy` on variance (the automated round-trip is manual-first, §0.2).
- **Logilize integration — 5 inventory-state streams** (§15) — storage-location (Stream B1, the R3 migration target), receiving + physical-match, stocktake, adjustments, quarantine.
- **ATP feed (Module B → Module A push)** (§10) — per-sub-pool ATP per allocation; transactional reads strongly consistent (the **B→A push**, FLOOR).

**Digital-provenance surfaces (the serialization workflow KEEP / launch-ready; the on-chain layer DECOUPLED — D12, §0.1):**

- **NFC tag application event-recording** (§6 — KEEP / launch-ready). `NFCTagApplied` fires when Logilize confirms Vinlock has applied a tag; the serial capture + `SerializedBottle` creation are launch-ready. The **on-chain-reference component of the tag content** (DEC-124) decouples.
- **NFT mint and burn** (§9 — DECOUPLED, behind the re-scoped EXT-1 gate). At launch `nft_reference = NULL`; the mint/burn lifecycle + the C→S→B burn chain back-fill when the on-chain workstream lands; the NS path fires `BottleShippedAsNonSerialized` regardless.
- **Bottle Page data-source contract** (§16 — KEEP; the NFT/chain-link content portions decouple). Module B is system of record; renders non-NFT content at launch; zero customer identifiers (DEC-024); six-locale fallback (DEC-127).
- **Recovery scenarios §6.11.1–§6.11.4** (§17 — the on-chain chains DECOUPLED; the §17.4 inventory-ledger write-off side KEPT).

### §1.2 Out of scope at launch

Module B v0.3-MVP carries the v1.1 launch out-of-scope set forward (defensive lock-down; consolidated at §21 — already-deferred items carried verbatim, do not re-cut). Highlights: third-party custody intake + `THIRD_PARTY` ownership_flag value (the enum is 2-value at launch — `PRODUCER` / `CRURATED`); ConsignmentPlacement entity + active-consignment SELL_THROUGH; `AGENCY` sourcing; sub-warehouse storage-location detail (Logilize-internal); multi-warehouse; storage-fee pricing + customer-level billing (Module S/E own); `consumption`/`transfer` adjustment-type placeholders; richer Bottle Page media. **D12-decoupled (off the critical path, not deleted — §0.1):** Avalanche on-chain execution + smart-contract code, the NFT custodial-wallet operational architecture, the on-chain encoding, the NFC tag-write on-chain-reference content, the on-chain recovery mechanics — all behind the re-scoped EXT-1 gate, back-filled when the on-chain workstream lands.

### §1.3 Module boundary statement — what Module B does NOT do (unchanged)

Module B does NOT own: commercial pricing / Offer / Cart / Checkout / Voucher state (Module S — Module B exposes the ATP read contract for Module S display); the Allocation entity / sub-pool partition / lifecycle FSM (Module A — Module B reads sub-pool partition numerics read-only for serialization gating, does not mutate them); Procurement / PO / Supplier (Module D — Module B consumes `InboundEventPhysicallyAccepted` + `InboundEventCostFinalized` + `ConsignmentReceiptRecorded`); Settlement / financial events / GL treatment (Module E + Xero per DEC-072 — Module B records inventory-side operational events; Module E records the financial event; Xero decides GL); Customer identity / Profile / Hold / sanctions / KYC (Module K — Module B does not read Customer state at all per DEC-024 + DEC-029, no PII on Module B records or on-chain); Producer / Product Master / Product Variant / Product Reference / Sellable SKU / Composite SKU / Case Configuration whitelist (Module 0 — Module B reads); layered-breakability authorisation (composed at Module 0 / A / S / C — Module B records and executes the case break but does NOT re-derive or gate `effective_unbreakable`); fulfilment execution — picking, packing, shipment, carrier integration, in-transit tracking, delivery confirmation (Module C — Module C reads Module B's StockPosition + SerializedBottle late-binding state at picking); the Bottle Page render surface, the Logilize integration mechanics, and the wallet operational architecture (downstream tech per DEC-073).

---

## §2 Inventory Authority Framing **(FLOOR — the conceptual anchor)**

This section is the conceptual anchor for §§3–8 + §§10–15. It restates the v17 §B.1/§B.4 four-orthogonal-dimension model with the NewCo simplifications (2-value ownership; the R4 E-emits ownership cascade, §0.3).

### §2.1 Four orthogonal dimensions

Every inventory position is the intersection of four orthogonal dimensions:

| Dimension | What it represents | Values at NewCo launch |
|---|---|---|
| **Ownership** | Legal ownership | 2-value enum: `PRODUCER`, `CRURATED` (§2.2; v17's `THIRD_PARTY` value is OUT — already-deferred, §21) |
| **Custody** | Physical possession — location | Warehouse + storage-location reference (warehouse-level summary exposed by Module B; sub-warehouse detail Logilize-internal — already-deferred) |
| **Commercial status** | Commitment state | `available` (free), `committed` (voucher hold), `reserved` (picking in progress) |
| **Allocation lineage** | Which allocation the goods belong to | Immutable UUID; bottles from different allocations never interchangeable, even for identical wines |

The **StockPosition aggregated view** (§8) reports total / committed / available quantities at the canonical 5-dimension intersection `(PR, warehouse, case_config, allocation, ownership)`. **Sellable quantity** feeds Module A + the storefront (commercially `available`, in storage, not quarantined); ownership flag is independent of commercial availability (`PRODUCER`-owned V2 consignment stock and `CRURATED`-owned stock are both sellable). **Shippable quantity** feeds Module C (committed, in storage or reserved for picking, not quarantined, not under adjustment).

### §2.2 `ownership_flag` at NewCo — 2-value enum + the R4 E-emits transition **(R4 / N3 landed — §0.3)**

The `ownership_flag` enum at NewCo launch is **2-value**: `PRODUCER` and `CRURATED` (the v17 third value `THIRD_PARTY` is OUT — already-deferred, §21).

- **`PRODUCER`** — bottles owned by the Producer counterparty. **Passive consignment V2** (producer pre-positions stock at Vinlock; flag is `PRODUCER` until the ownership transition) and **passive consignment V1** (goods stay at producer until customer order; on inbound to Vinlock the flag is `PRODUCER` for the brief inbound-to-dispatch window).
- **`CRURATED`** — bottles owned by Crurated. Two paths produce this flag:
  - **Passive consignment V1/V2 → on `SupplierPaymentCompleted`** (R4 — **reconciled from the v1.1 "at sell-through" prose, §0.3**): the `PRODUCER → CRURATED` transition fires when **Module E emits `SupplierPaymentCompleted`** (the payment-clearing moment; Module B consumes it — the bottle becomes NewCo-owned because NewCo has paid for it). **The inventory `ownership_flag` ledger keys to the *payment* signal** — distinct from Module D's PO-level title ledger, which keys to the sale/shipment signal `VoucherIssued` (N3).
  - **Direct purchase** *(not-exercised-at-launch — §0.4)*: the InboundBatch is `CRURATED` from creation (NewCo bought it outright) → **no** `PRODUCER → CRURATED` transition. Deferred (no launch deal); the `sourcing_model`-driven derivation is the retained seam.

**Module B records and executes the `PRODUCER → CRURATED` transition but does not decide it.** Module D sets the initial flag at InboundBatch creation; **Module E emits `SupplierPaymentCompleted`** (the payment executor — R4, the E-emits trap; **Module B does NOT emit or derive this event**); Module B records the transition as an immutable provenance event (`OwnershipTransitioned`, §19.1) and preserves custody history + allocation lineage across it (BR-B-Ledger-5, §18.2). The 2-value enum is a NewCo simplification; v17's 3-value enum reactivates if third-party custody intake lands post-launch (§21).

### §2.3 Module B vs Logilize source-of-truth split **(FLOOR-adjacent — the recorder-not-gatekeeper discipline)**

**Logilize is the physical-execution arm; Module B is the ERP-side inventory authority** (the recorder-not-gatekeeper integrity discipline — Module B records, Logilize executes). System-of-record split: sub-warehouse physical location → Logilize (already-deferred from B's exposure); warehouse-level location → Module B (read-summary from Logilize, for the Cellar render + Bottle Page); physical custody state (in-warehouse / in-transit / delivered) → Logilize; the inventory ledger (InboundBatch, SerializedBottle, Case, StockPosition, QuarantineRecord) → Module B; allocation-lineage attribution → Module B; ownership flag → **Module B records, Module D sets initial / Module E's `SupplierPaymentCompleted` triggers the transition** (R4); commercial status → Module B; ATP per allocation → Module B (push to Module A); receiving physical-count match → Module B (DEC-194); stocktake authority + variance computation → Module B; adjustment proposal + supervisor approval → Module B; QuarantineRecord (quarantine-before-trust) → Module B. The integration *mechanics* are tech (DEC-073).

### §2.4 Four-way reconciliation discipline **(FLOOR — the integrity backstop)**

The four-way reconciliation discipline is the architectural payoff of Module B's independent inventory-ledger authority: **Logilize** (physical state) ↔ **Module B** (ERP-side inventory ledger) ↔ **Module S** (commercial state) ↔ **Module E** (financial state). Three reconciliation primitives operate across the four legs — receiving reconciliation (DEC-194; §11), stocktake reconciliation (§12), adjustment reconciliation (§13) — and **QuarantineRecord is the gatekeeper primitive** (§14): Module B never creates ledger records from unverified Logilize data; without the quarantine gate the four-way reconciliation degrades to three-way (the exact failure mode the discipline corrects).

**The discipline is FLOOR — KEPT.** The D16 SIMPLIFY (§0.2) defers only the **workflow *automation* of the three primitives** (the automated round-trips + cadence automation → manual-first); the **discipline, the gate, the entities, and the event consumers stand** (the seam). The KPI target (inbound physical-discrepancy rate <5%; zero ledger-corruption events from Logilize-side bugs) is preserved (§22.1).

---

## §3 InboundBatch Entity **(FLOOR — the core-loop "bottle received into inventory" step)**

Module B owns the **InboundBatch** entity — the logical container for goods arriving from a single source (PO or passive-consignment receipt), created from Module D's `InboundEventPhysicallyAccepted` (DEC-195). **This realises the core-loop "bottle received into inventory" step on the inventory-ledger side (MVP-plan §3) — KEPT WHOLE; the single anchor from which SerializedBottle records (serialized) + NS counters (non-serialized) flow.**

### §3.1 Entity boundary

The InboundBatch is the single anchor where every Module B inventory-ledger trace originates; it links back to Module D (the `InboundEventPhysicallyAccepted` payload that triggered creation) and forward to per-bottle SerializedBottle records + per-batch non-serialized counters. It carries (business-meaningful state; the literal data shape is tech, DEC-073): **source path** (PO-based standard goods, triggered by `InboundEventPhysicallyAccepted`; V2 pre-positioning, triggered by `ConsignmentReceiptRecorded` composing with `InboundEventPhysicallyAccepted`; V1 customer-order shipment); **source allocation** (immutable lineage UUID); **expected quantity** (from the upstream PO line / passive-consignment intake); **received quantity** (Logilize-reported, via the physical-match check, DEC-194); **ownership flag** (`PRODUCER`/`CRURATED` — set by Module D in the payload; Module B records, does not decide, §2.2); **cost basis** (provisional at creation; finalized at `InboundEventCostFinalized` — §3.2); **serialization plan** (`qty_planned_serialize` read from the source allocation's `qty_to_serialize`; `qty_actually_serialized` running counter); **non-serialized counters** (§5); **custody** (warehouse-level summary); **lifecycle state** (§3.4). The InboundBatch is **per-allocation per source-event** (one InboundBatch per allocation for consolidated POs); **immutable on its identity attributes** (source path, source allocation, ownership flag, expected quantity); the mutable fields are the running counters, the cost-basis flag, and the lifecycle state.

### §3.2 Cost-basis flow **(FLOOR-adjacent — provisional → finalized)**

Cost basis flows through two phases (the cost-basis attribute is **event data only** — DEC-072; Module E + Xero decide GL). **Phase 1 — provisional at `PHYSICALLY_ACCEPTED`:** Module D's `InboundEventPhysicallyAccepted` carries the provisional `base_cost`; Module B sets `cost_basis_per_unit` and flags the batch `cost_basis_provisional`. **Goods become available for ATP and fulfillment immediately** — cost finalization is a separate, later event. **Phase 2 — finalized at `COST_FINALIZED`:** Module D's `InboundEventCostFinalized` carries the finalized landed cost (base + transport + customs + insurance + other); Module B flips the flag to `cost_basis_finalized`. Cost basis on the batch is referenced at dispatch (Module C's late-binding dispatch reads it and propagates to Module S `VoucherShipped` + Module E settlement). Cost-basis correctness feeds the no-oversell / committed-inventory floor + Module E's dual-record (the D19 seam on the inventory side).

### §3.3 Plan-driven serialization target

At InboundBatch creation, Module B reads the source allocation's `qty_to_serialize` (Module A's sub-pool partition) and pins it as `qty_planned_serialize`; the residual `received_quantity − qty_planned_serialize` stays non-serialized (permanent state, §5). The plan is revised on Module A's `AllocationSerializationPlanChanged`: **Increase** → Logilize is instructed to serialize additional bottles from the still-non-serialized portion (the **only** NS→serialized conversion path; each new SerializedBottle inherits the batch's allocation lineage; the serialization *pipeline itself* rides the D12 decouple at the on-chain layer, §0.1). **Decrease** → allowed only down to `qty_actually_serialized` (irreversible); below that, Module B's feasibility query rejects and Module A emits `AllocationSerializationPlanInfeasible`. Plan-vs-actual divergence emits `BatchSerializationDiscrepancy` (§3.5).

### §3.4 InboundBatch state machine

**States:** `expected` (created from Module D event; physical-match pending) → `received` (physical-match passed; received = expected; batch enters salable pool subject to allocation `ACTIVE`) | `partially_received` (received < expected; batch salable at the actual received qty; shortfall flows `InboundBatchDiscrepancy`) | `discrepancy` (received > expected OR identity mismatch; `InboundBatchDiscrepancy` emitted; Module D reopens the InboundEvent into DISCREPANCY without retroactively invalidating live batches — **manual-first at launch, N1, §0.2 / §11**) → `closed` (terminal; net inventory zero + no in-flight state; records persist for provenance). Transitions are monotonic-ish: `expected → received | partially_received | discrepancy`; `partially_received → discrepancy` (downstream variance); `discrepancy → received` (DiscrepancyResolution closes with adjusted expected qty); `received|partially_received → closed`.

### §3.5 `InboundBatchDiscrepancy` + `BatchSerializationDiscrepancy`

On any physical-count variance (DEC-194), Module B emits **`InboundBatchDiscrepancy`** to Module D (full contract §11). Plan-vs-actual serialization divergence (labels exhausted, operator stops short, post-serialization damage) emits **`BatchSerializationDiscrepancy`** (event-only; does not auto-mutate inventory or the plan; resolution via Module A plan update / new Logilize instruction / escalate). Both are kept; the **automated round-trip** depth of `InboundBatchDiscrepancy` is the D16 manual-first cut (the operator opens the discrepancy + records the resolution within the 5-WD window — §0.2).

---

## §4 SerializedBottle Entity **(KEEP / launch-ready; only `nft_reference` decouples — D12, §0.1)**

Module B owns the **SerializedBottle** entity — the immutable record of a single physical bottle carrying a globally unique serial, allocation lineage, current state, location, and ownership. **Refined at ratification (D12, §0.1): the per-bottle ledger record + serial identity are launch-ready** — WH operators apply the serial-bearing NFC tag at receipt and the ERP records the SerializedBottle (lineage, ownership, lifecycle, the Layer-2 `atp_serialized` contribution). **The only decoupled attribute is `nft_reference`** (NULL at launch if the on-chain layer isn't live; back-filled when it lands — the spec already admits NULL). The serial originates on the externally-purchased NFC label (not Avalanche-dependent), so the ledger record is fully functional without the mint.

### §4.1 Entity boundary

The SerializedBottle is created when an inbound bottle is partitioned to the **serialized sub-pool** of its source Allocation and an NFC tag is applied. It carries (the literal data shape is tech, DEC-073): **serial identity** (the globally unique serial from the externally-purchased NFC label — the canonical identity; Module B never mints/renumbers/reuses serials); **Product Reference** *(naming cascade — wine-display alias Bottle Reference; the catalog-level identity, Product Variant + Format, read from Module 0)*; **allocation lineage** (immutable); **InboundBatch reference**; **lifecycle state** (§4.2); **custody location** (warehouse-level summary); **ownership flag** (inherited from the InboundBatch at creation; transitions per §2.2); **commercial status** (`available`/`committed`/`reserved`, orthogonal to lifecycle); **`nft_reference`** (the on-chain NFT identifier — **nullable + back-fillable; NULL at launch under the D12 decouple**, §0.1); **predecessor/successor references** (for recovery sequences — the on-chain recovery chains decouple, §17); **external serial** (for pre-serialized bottles — the producer-side identifier preserved for provenance, never the primary key). One physical bottle ↔ one SerializedBottle record at any time.

### §4.2 Lifecycle states

The SerializedBottle lifecycle is the canonical inventory-ledger state machine for every serialized bottle in custody: **`in_storage`** (default post-serialization; available for fulfillment) → **`reserved_for_picking`** (soft lock during fulfillment planning; hard lock at picking — the late-binding moment where a voucher binds to a specific physical bottle) → **`shipped`** (left the warehouse; **the NFT burn at this transition is decoupled — D12**; at launch the SerializedBottle transitions on `VoucherShipped` and the burn back-fills when the on-chain workstream lands; NS fires `BottleShippedAsNonSerialized`) → **`delivered`** (carrier-confirmed) | **`returned`** (awaiting inspection) → **`in_storage`** (restocked) | **`damaged`** (terminal). Also: `in_storage → damaged` (damage in storage) | `in_storage → lost` (missing at stocktake; `lost → in_storage` admitted only on supervisor approval) | `in_storage → written_off` (destroyed in custody — §17.4; the inventory-ledger write-off side is KEPT, only the NFT-burn-as-destroyed decouples). `consumed` is preserved as a terminal state but **N/A at launch** (no events at launch; reactivates Phase 2+). `damaged`/`consumed`/`written_off` are terminal. **Every state transition is recorded as an immutable provenance event** (timestamp, actor, reason — provenance records cannot be edited or deleted; BR-B-Provenance-1). Locking semantics: soft lock during planning; hard lock at picking (late binding).

### §4.3 Cross-module entity boundary — read-only on Module A state

Module B is **read-only** on Module A's Allocation state: the serialization workflow reads the source Allocation's sub-pool partition (`qty_to_serialize`, `qty_non_serialized`, `non_serialized_offer_admitted`, `serialization_type` derived). Module B does **NOT** mutate Module A's sub-pool numerics; it observes mutations via Module A events (`AllocationSerializationPlanChanged`) and runs serialization on the new `qty_to_serialize` increment (§3.3). **This serialized-bottle identity is a named B↔C contract** — Module C late-binds the physical bottle reading B's bound serial for serialized stock (§0.6).

### §4.4 Serial provenance + pre-serialized bottles

Serial numbers are **NOT** generated by Module B — they originate on **externally-purchased NFC labels** (each label carries a globally unique serial before it reaches the warehouse). Module B ingests the serial from the Logilize event when the label is applied and records it; duplicate/previously-used serials are rejected at ingestion (global uniqueness is the business-spec commitment; the literal enforcement is tech). Once assigned and confirmed a serial cannot be un-assigned (serialization irreversibility); allocation lineage is immutable. Pre-marked bottles record their producer-side identifier as `external_serial` (preserved for provenance + producer reconciliation; the NewCo serial remains canonical). **The serial provenance is fully launch-ready** (it does not depend on the on-chain layer — the platform the NFT lives on is Avalanche, which decouples; the serial does not).

---

## §5 Non-Serialized Inventory at InboundBatch **(FLOOR — doubly load-bearing: Layer 2 for NS AND the D12 decouple seam)**

Module B tracks **non-serialized inventory at the InboundBatch level** with the four-counter set. NS inventory is **permanent / fulfillable** state, NOT transitional — it has a full inventory-ledger home (counters, ATP, commitment/reservation lifecycle, conversion path, adjustment workflow) while preserving the digital-provenance no-op. **This is doubly load-bearing: it is Layer 2 for NS stock AND the D12 decouple seam** — what lets the launch critical path run without the on-chain workstream (§0.1).

### §5.1 Four counters + §5.2 NS ATP formula

The NS portion of an InboundBatch is the residual `received_quantity − qty_planned_serialize` — fulfilled at allocation+quantity granularity by Module C late-binding (no requirement that bottles be serialized before picking). Four counters: `qty_planned_serialize` (the serialization target); `qty_actually_serialized` (running, irreversible); `qty_non_serialized_committed` (NS units committed to vouchers — the voucher binds to the **allocation**, not specific bottles); `qty_non_serialized_reserved` (cart-hold / pre-issuance reservation OR reserved for picking by Module C late-binding). The **NS sub-pool ATP** per allocation = `Σ across the allocation's contributing InboundBatches of (received_quantity − qty_planned_serialize − qty_non_serialized_committed − qty_non_serialized_reserved − batch-level adjustments) − batch-level quarantined`. The NS ATP is one of the two sub-pool ATPs Module B exposes per allocation (`atp_serialized` + `atp_non_serialized`); both feed the two-layer guard (§10), strongly consistent at the transactional boundary.

### §5.3 NS commitment + reservation lifecycle

NS units flow through three commercial-status states at the batch-counter level: **Available** (the NS ATP); **Committed** (`qty_non_serialized_committed`); **Reserved for picking** (`qty_non_serialized_reserved` — incremented at Module C `planned → picking`; released on dispatch, reducing the NS portion). **Cluster F atomicity:** when an InboundBatch backing non-zero `qty_non_serialized_reserved` is quarantined, the reservation is released atomically (the decrement is part of the same atomicity envelope as `planned → picking` increments — no inconsistent interleaving); affected SOs trigger the shortfall workflow for re-routing. Cancellation (voucher cancellation per DEC-099 + the committed-inventory floor) reverses the reservation.

### §5.4 NS → serialized conversion + §5.5 NS adjustment workflow

Conversion from non-serialized to serialized is **only** triggered by an upward `AllocationSerializationPlanChanged` from Module A (§3.3); Module B never autonomously serializes; the reverse (serialized → non-serialized) is **NOT** admitted (irreversibility). The conversion is constrained by the **NS-pool floor** — `qty_non_serialized` cannot decrease below the count of issued vouchers backed by NS stock (the anti-orphan rule); Module B's feasibility query rejects breaching plan-changes and Module A emits `AllocationSerializationPlanInfeasible`. **NS adjustments** are recorded as quantity deltas on the batch (no per-bottle SerializedBottle to transition; provenance at the batch level); the standard adjustment workflow (§13) applies and emits `InventoryAdjusted` (scope discriminator `per-batch`). **The Q-CL-6 committed-inventory protection (§13.4/§13.5) applies identically to NS** — a proposed NS adjustment that would breach the NS-pool floor routes through `InventoryShortfallDetected` to Module A; it proceeds only after `VoucherCancelled` releases the commitment (FLOOR).

### §5.6 DEC-133 reframing — five no-op clauses are digital-provenance-only + `BottleShippedAsNonSerialized` **(the NS universal fallback)**

The five DEC-133 no-op clauses (no SerializedBottle, no NFC, no NFT, no Bottle Page, no recovery chain) are correct on the **digital-provenance** axis only; NS stock has full inventory-ledger discipline (§5.1–§5.5). **Informational event for non-serialized shipment:** Module B emits **`BottleShippedAsNonSerialized`** for NS bottles at shipment (mirroring the serialized chain for audit consistency; payload mirrors with `null` NFT-related fields; informational-only, no state change). **This event is the universal fallback the D12 decouple relies on** (§0.1) — when the on-chain workstream is decoupled-and-slipping, serialized-but-not-yet-minted stock and NFC-opt-out NS stock both ship through this path; it is consumed by Module E for OC settlement read + by Producer reporting for aggregate counts. The Voucher's `originating_club_id` OC lineage preserves through the chain (no-op for NS).

---

## §6 NFC Tag Application **(KEEP / launch-ready; only the on-chain-reference tag content + the NFT-mint trigger decouple — D12, §0.1)**

The NFC tag application is **the digital-provenance sub-layer of Module B's inventory-ledger role — and the launch-ready half of the D12 boundary.** Refined at ratification: the physical tag application + serial capture + `NFCTagApplied` event + SerializedBottle creation are **launch-ready** (WH ops + ERP + WMS must support it; Logilize executes); **decoupled** are the NFT mint that fires 1:1 after (§9) + the **on-chain-reference component of the tag content** (DEC-124).

### §6.1 Workflow boundary — Module B records, Logilize executes

**Logilize WMS** is the system of record for physical state (the Vinlock employee applies the tag per the tag-application standard); **Module B** is the system of record for the digital-provenance event (`NFCTagApplied`) + the SerializedBottle lifecycle. On consuming Logilize's tag-application event, Module B fires `NFCTagApplied`, creates/mutates the SerializedBottle record, and **(decoupled, behind the EXT-1 gate)** would trigger the NFT mint (§9). The literal Logilize integration mechanics are tech (DEC-073). **At launch the SerializedBottle is created with `nft_reference = NULL`; the mint back-fills when the on-chain workstream lands (§0.1).**

### §6.2 Workflow timing — at warehouse receipt, by Vinlock employees

At warehouse receipt: the bottle arrives; Vinlock receives/inspects/accepts under Module D's `InboundEventPhysicallyAccepted`; Module B's physical-match check fires (§11). **For the `qty_to_serialize` portion:** the Vinlock employee applies a pre-printed serial-bearing NFC tag; Logilize records the application + emits a downstream event referencing serial + Product Reference + allocation lineage + custody location; **Module B consumes it and emits `NFCTagApplied`** + creates the SerializedBottle (inheriting the InboundBatch's allocation lineage + ownership flag) — **all launch-ready.** **(Decoupled — D12)** the NFT mint would fire 1:1 at this moment (§9); at launch `nft_reference = NULL`. The bottle enters salable state (`SerializedBottle.state = in_storage`); the StockPosition mutation pushes the ATP delta to Module A (§10). For **non-serialized stock** this entire workflow is skipped (the InboundBatch NS counters carry the inventory-ledger state instead).

### §6.3 NFC tag-write content at launch **(the back-fill design — D12 / DEC-124; Paolo-track action item 2)**

The NFC tag's on-chip content has three business-meaningful components: **(1) Bottle Page URL** (the online-resilient verification entry point — **works at launch**); **(2) bottle serial** (the canonical identity — **works at launch**); **(3) on-chain reference** (the NFT-ID hash / Avalanche transaction reference — sufficient for offline / network-independent verification — **decoupled, back-fillable**). **The launch posture (Paolo-track action item 2, §0.1):** tags apply at launch with **serial + Bottle Page URL** (online verification works); the **on-chain reference is back-fillable / resolved later** (offline verification rides the decoupled workstream). The "bake the on-chain reference onto the chip" design is **not a hard prerequisite for applying tags.** Whether tags are rewriteable / two-pass / URL-only-at-launch is tech + procurement (DEC-073); the **pre-launch NFC tag-stock procurement lead-time** makes confirming this design time-sensitive. **Do not re-cut** the already-deferred DEC-124 working-hypothesis cluster (§21).

### §6.4 `NFCTagApplied` — emission contract **(launch-ready)**

Module B emits `NFCTagApplied` at the moment Logilize confirms physical application: bottle serial; Product Reference; source Allocation lineage; InboundBatch reference; custody location; actor; timestamp; (NULL for the standard case) a `predecessor_serial` reference for a recovery-scenario re-application (§17.1 — the on-chain recovery chain decouples). **`NFCTagApplied` is launch-floor / un-gated** (the acceptance EXT-1 gate is re-scoped so this criterion is launch-ready — §0.1 / acceptance §0). The 1:1 trigger for `NFTMinted` (§9) is **decoupled** (the mint back-fills).

---

## §7 Case Entity + Integrity Model **(KEEP — recorder-not-gatekeeper)**

Module B owns the **Case** entity — the container linking multiple bottles, with a 3-state integrity FSM — the case-level inventory-ledger anchor for layered-breakability composition and Module C whole-case dispatch.

### §7.1 Entity + 3-state FSM

Each Case carries: allocation lineage (inherited); ownership flag (inherited; transitions atomically with the bottles inside); Case configuration reference (FK to the PIM Case Configuration — Module 0); member bottles (SerializedBottle references for serialized cases, or an aggregate quantity reference to the InboundBatch for non-serialized cases); integrity state. **States:** `intact` (sealed/complete) → `partially_broken` (some bottles removed; still a logical container) → `broken` (terminal — fully disassembled). Transitions: `intact → partially_broken` (first bottle removed); `partially_broken → broken` (last bottle removed or deliberate full-disassembly); `intact → broken` (single-step full-disassembly). The FSM is **monotonic non-decreasing** — `broken` is terminal; a broken case cannot be reconstituted; all bottles released from a broken case retain their allocation lineage. Every transition is an immutable provenance event (`CaseIntegrityChanged`).

### §7.2 Recorder-not-gatekeeper discipline + §7.3 layered-breakability cross-cite

**Module B is recorder, not gatekeeper** on case-breaking authorisation — it records and executes case-breaking events but does **NOT** re-derive or gate the layered-breakability rule. By the time a case-break instruction arrives, authorisation has been granted upstream via the layered breakability model: **Layer 1** (possible case configurations — Module 0 PIM, a possibility whitelist, does not by itself make a case unbreakable); **Layer 2** (producer breakability per allocation × case_config — Module A); **Layer 3** (commercial unbreakable per offer — Module S). **The effective rule** = `Layer 2 (producer) OR Layer 3 (commercial)` (Layer 1 does not contribute). Module S enforces at cart validation; Module A at voucher issuance; Module C at fulfillment planning; **Module B records** the case break when fulfillment requires it. *(Naming cascade: the Layer-1 read renames `Wine Variant.possible_case_configs → Product Variant.possible_case_configs`; naming only.)* When `effective_unbreakable = true`, the case is sold + dispatched as a whole unit with one voucher per bottle — voucher count = bottle count, making overage structurally impossible (this is the project-locked layered-breakability pattern; unchanged).

---

## §8 StockPosition Aggregated View **(FLOOR — the canonical read-contract surface)**

Module B owns the **StockPosition** aggregated view at the canonical 5-dimension intersection — the architectural surface that lets ATP land per allocation per case-config per warehouse, exactly what Module C late-binding needs for whole-case dispatch. **This is the canonical Module B read-contract surface — a named B↔C contract (§0.6).**

### §8.1 5-dimension aggregation

`StockPosition = (Product Reference, Warehouse, Case Configuration, Allocation, Ownership)` *(naming cascade: `Bottle Reference → Product Reference`; the physical-unit semantics unchanged)*. Each dimension is load-bearing: **Product Reference** (catalog-level identity); **Warehouse** (physical custody at warehouse granularity; multi-warehouse already-deferred); **Case Configuration** (load-bearing for case-integrity-aware ATP — **cannot** be dropped, or mixed-case unbreakable dispatch breaks); **Allocation** (immutable UUID; never interchangeable across allocations); **Ownership** (2-value enum, §2.2). Three headline quantities at the intersection: `total_quantity` (serialized SerializedBottle count + NS InboundBatch residual); `committed_quantity`; `available_quantity` (`total − committed − reserved − quarantined − under_adjustment`).

### §8.2 Sellable vs shippable + sub-pool decomposition

**Sellable** feeds Module A + the storefront (commercially available, in storage, not quarantined; ownership-independent). **Shippable** feeds Module C (committed, in storage or reserved, not quarantined/under-adjustment); the case-config dimension is critical — when `effective_unbreakable = true`, Module C's late-binding selection reads shippable quantity at the case-config level (whole-case dispatch). **Sub-pool decomposition:** StockPosition exposes `available_serialized` (count of SerializedBottle records in `available` status) + `available_non_serialized` (the NS-pool ATP, §5.2) at the allocation dimension; both feed Module A's two-layer guard (§10.5). **Module C reads StockPosition at late-binding pick** (the named B↔C contract — §0.6).

---

## §9 NFT Mint and Burn **(DECOUPLED — D12; off the launch critical path, behind the re-scoped EXT-1 gate; §0.1)**

This section is **decoupled off the launch critical path (D12 — DECOUPLE ≠ DEFER).** The on-chain heart of the digital-provenance VP — the NFT mint/burn lifecycle, the custodial wallet, and the C→S→B burn chain — is **a parallel workstream**, feature-flagged behind the re-scoped EXT-1 gate, back-filled when the on-chain workstream + the blockchain-expert review land. **At launch `nft_reference = NULL` on every SerializedBottle (§4.1); the serialization workflow (tag + serial + ledger, §4/§6) is launch-ready; the NS universal fallback (`BottleShippedAsNonSerialized`, §5.6) carries every shipment regardless.** The VP is preserved (the brand value back-fills additively, no rebuild). **Do not re-cut** the already-deferred working-hypothesis cluster (1:1-vs-batch mint cardinality, the mint-payload composition, the wallet operational architecture, smart-contract audit/governance — §21).

### §9.1 Mint + custody (decoupled)

At the working-hypothesis baseline (decoupled): each `NFCTagApplied` would emit one `NFTMinted` (1:1 cardinality at the business-event layer); the mint payload references catalog identity (Product Reference + Product Variant) + source Allocation + NFC-UID linkage (the bottle serial) + mint timestamp, with **zero PII** (no Customer/Profile/Voucher identifier on-chain — BR-B-Anonymisation-1); the NFT is held in a **NewCo-controlled custodial wallet** while the bottle is in custody (the customer's claim is the Voucher, a NewCo-side record, not the NFT). **All of this is behind the EXT-1 gate at launch.** The literal on-chain encoding + wallet operational architecture are tech (DEC-073).

### §9.5 NFT burn cross-module event chain — Module C → Module S → Module B **(decoupled; the NS path is the universal fallback) — a named B↔C contract (§0.6)**

NFT burn at shipment fires through a three-step cross-module chain: **(1)** Module C dispatch emits `ShipmentDispatched`; **(2)** Module S consumes it and emits `VoucherShipped` (carrying the shipped bottle's serial / NFT identity for serialized stock — the late-binding bind result); **(3)** Module B consumes `VoucherShipped` and **(decoupled — D12)** would emit `BottleNFTBurned` (`reason = shipment_dispatch`). **Module B does NOT subscribe to Module C events directly** — the chain routes via Module S (Module S owns the customer-facing shipment-state authority). **For the launch posture (D12):** the **burn rides the decouple** — at launch Module B records the `SerializedBottle` `reserved_for_picking → shipped` transition on `VoucherShipped` and the NFT-burn back-fills when the on-chain workstream lands; **for non-serialized stock (and serialized-but-not-yet-minted stock under the decouple) Module B emits the informational `BottleShippedAsNonSerialized` (§5.6) — the universal fallback.** Module B reads the source Voucher's bound Allocation `serialization_type` to gate: `NON_SERIALIZED` → `BottleShippedAsNonSerialized`; `SERIALIZED`/`MIXED` serialized-portion → the (decoupled) burn surface. **Module C dispatches regardless of the on-chain layer** (Phase C item J).

### §9.6 Burn-transaction anonymisation (decoupled)

At the decoupled baseline, the on-chain burn transaction carries timestamp + reason + NFT-ID + NFC-UID linkage + an **opaque anonymised reference** (a one-way hash of the Voucher.id for forensic correlation only — the literal Voucher.id never goes on-chain); **no customer-identifying data on-chain** (BR-B-Anonymisation-1). The literal hash function + on-chain encoding are tech (DEC-073). **Gated behind EXT-1 at launch.**

---

## §10 ATP Calculation + Sub-Pool Decomposition **(FLOOR — Layer 2 of the two-layer no-oversell guard + the B→A push)**

Module B owns the **ATP feed** to Module A and (via Module A's cache + the lesser-of read) to the Module S storefront — the inventory-integrity half of the scope floor. **KEPT WHOLE.**

### §10.1 Dual ATP exposure per allocation

Module B exposes **two sub-pool ATPs per allocation**: **`atp_serialized`** (count of SerializedBottle records in `available` status, `in_storage`, not quarantined, not under adjustment) + **`atp_non_serialized`** (the NS four-counter formula, §5.2). Their sum is the total physical-layer ATP per allocation; it composes with Module A's allocation-pool ATP (`qty − issued`) to form the **two-layer no-overselling guard** (§10.5). At launch the serialized sub-pool may be dormant (D12 decouple) — **the guard composes per sub-pool regardless** (§0.5).

### §10.2 ATP feed — Module B → Module A push **(FLOOR — the load-bearing cross-module artery; DEC-187)**

The ATP feed pattern is **push**: Module B emits inventory events on every state change; Module A maintains a strongly-consistent ATP cache per allocation, per-sub-pool (Module A §11.5.1); hold placement reads the cache (real-time strongly consistent by construction). **The push sources:** `BottleStateChanged` (per-bottle lifecycle), `InventoryAdjusted`, `OwnershipTransitioned`, `BottleQuarantined`/`BottleQuarantineResolved`, `StocktakeReconciled`, NS-pool counter mutations. **The cache *mechanics* (push-vs-pull, reconciliation cadence, tolerance window, latency SLAs) are tech-implementation (DEC-073) — name the contract, not the mechanism.** The **contract** (push on every state change; strongly consistent at the transactional boundary) is floor. *(Module A's ATP-cache reconciliation-cadence contract is a Phase-C cascade — confirmed CONSISTENT; the mechanics are tech, Module A Q4.)*

### §10.3 Recalculation triggers + §10.4 storefront lesser-of read

ATP is recalculated and the delta pushed at every §10.2 event. **PRD-level latency contract:** display ATP staleness ≤5s (Module S's storefront read tolerates it); transactional ATP real-time strongly consistent (hold placement). The literal SLA + retry + alarming are tech (DEC-073). **Storefront read path (FLOOR — Module S §8.6):** Module S reads the **lesser of** (Module A allocation-pool ATP) and (Module B physical-inventory ATP) per sub-pool — `min(Layer 1, Layer 2)` — exposing the minimum as the available-to-sell quantity. **Module B must keep exposing per-sub-pool ATP** for Module S's lesser-of read (a Module S ratified contract — §0.6 / Q4).

### §10.5 Two-layer no-overselling guard **(FLOOR — the inventory-integrity half of the scope floor)**

Both layers must pass at hold placement / voucher issuance: **Layer 1 — Module A allocation-pool layer** (`qty − issued ≥ 0`); **Layer 2 — Module B physical-inventory layer** (`physical_in_storage − reserved − quarantined − under_adjustment ≥ 0`). Either failure rejects the hold; both are evaluated at the transactional boundary. The two layers compose **per sub-pool**: a SERIALIZED-offer voucher line is rejected if `atp_serialized` < requested qty; a NON_SERIALIZED-offer voucher line is rejected if `atp_non_serialized` < requested qty; **cross-sub-pool fungibility is NOT admitted at hold placement** (the partition is enforced strictly). Per-sub-pool composition means the guard is **intact even when the serialized sub-pool is dormant** (the D12 decouple — at launch, if the on-chain workstream is slipping, Layer 2 = the NS sub-pool ATP; §0.5). **Composition note (the `AllocationCapacityExhausted` drift — §0.5):** Module A v0.3-MVP frames Layer-1 over-issuance as an **operation-level rejection — there is NO `AllocationCapacityExhausted` event**; Module B's Layer-2 / no-oversell prose composes with the rejection at the issuance operation, not a non-existent event (resolved consistently with Module S, which flagged the drift). The guard composes with **Module S's lesser-of read (§8.6) ∧ Module C's no-oversell-at-pick StockPosition read (§8)** — the floor whole end-to-end per sub-pool (Phase C item G/M).

---

## §11 Receiving + Inbound Workflow + Discrepancy **(integrity core FLOOR; the automated round-trip manual-first — N1, §0.2)**

Module B owns the **physical-match check** on each InboundBatch and emits `InboundBatchDiscrepancy` to Module D on variance (the DEC-194 two-stage receiving discipline). **The DEC-194 split + the physical-match check are the integrity core — FLOOR, KEPT; the automated reciprocal round-trip is manual-first at launch (N1 — identical with Module D §3.4/§13.3).**

### §11.1 Two-stage discrepancy detection **(DEC-194 — FLOOR)**

The receiving discrepancy authority is **split** (DEC-194): **Module D = documents in order** (the 3-gate inbound QC at `PHYSICALLY_ACCEPTED` — paperwork, ProducerAgreement validity, PO-line conformance; pass fires `InboundEventPhysicallyAccepted`, which creates the InboundBatch); **Module B = physical match** (compares Logilize-reported physical counts against the InboundBatch's expected quantity; variance triggers `InboundBatchDiscrepancy`). The independent physical-match check is the integrity primitive that detects Logilize-side errors the single-stage Module D check cannot — it delivers the KPI "inbound physical-discrepancy rate <5%" (§22.1). **This is the integrity core — not "sophistication" — KEPT.**

### §11.2 `InboundBatchDiscrepancy` emission + the manual-first round-trip **(N1)**

Module B emits **`InboundBatchDiscrepancy`** to Module D on quantity-short / quantity-over / identity-mismatch / quarantine-trigger variance (carrying the InboundBatch + upstream InboundEvent references, variance type, variance quantity, the Logilize report, actor, timestamp). **The integrity interlock is KEPT** (Module D reopens the InboundEvent into DISCREPANCY without retroactively invalidating live batches; the DISCREPANCY state + Module D's 6-path resolution enum stand). **The D16 SIMPLIFY (manual-first at launch, N1):** instead of the automated event round-trip, **the operator opens the discrepancy + records the resolution path manually within the 5-WD window** — **identically to Module D's manual-first depth (Module D §13.3).** **Seam:** the `InboundBatchDiscrepancy` event + the DISCREPANCY state + Module D's 6-path resolution enum are all kept, so the automated round-trip is additive when it lands post-launch. Cost-basis consequences flow forward (if resolution adjusts qty, the downstream `InboundEventCostFinalized` reflects it; live batches are not retroactively invalidated).

### §11.3 The `InboundEventPhysicallyAccepted → InboundBatch` creation chain **(DEC-195 — FLOOR, floor chain 1)**

Module D's 3-gate QC pass → `InboundEventPhysicallyAccepted` (with provisional cost basis) → **Module B consumes; creates the InboundBatch** (source path, source allocation, expected qty, ownership flag from payload, cost basis = provisional, serialization plan, NS counters = 0, lifecycle = `expected`) → physical-match check (match → `expected → received` + ATP push; variance → `InboundBatchDiscrepancy` + `partially_received`/`discrepancy`) → Phase 2 `InboundEventCostFinalized` → cost-basis flip provisional → finalized. **Goods become available for ATP and fulfillment immediately at the `expected → received` transition.** For V2: `ConsignmentReceiptRecorded` composes with `InboundEventPhysicallyAccepted` into a single InboundBatch. **Module D's §14.5 frames this contract from D's side — this PRD matches it.**

---

## §12 Stocktake **(integrity discipline FLOOR; the auto-reconciliation + cadence automation manual-first — D16, §0.2)**

Module B owns the **Stocktake** entity and the 4-state lifecycle for warehouse-scoped variance reconciliation — the third leg of the four-way reconciliation discipline. **The reconciliation discipline is integrity — KEPT; the tolerance-driven auto-reconciliation engine + cadence automation defer to manual-first (D16).**

### §12.1 Entity + scope + §12.2 4-state FSM

A Stocktake is a planned physical-count campaign over a scoped subset (warehouse / storage location / Product Reference / Allocation). The supervisor sets the target date, the variance tolerance threshold, and notes. **States:** `planned` (Logilize instructed to execute on target date) → `in_progress` (Logilize executing) → `variance_review` (counts reported; Module B has computed variance; supervisor reviews above-tolerance variances) → `reconciled` (terminal; all above-tolerance variances resolved). Monotonic (no backward transitions); a stocktake whose review surfaces unrecognised entities stays in `variance_review` until the QuarantineRecord resolutions + the resulting adjustments complete.

### §12.4 Variance computation + the manual-first posture **(D16, §0.2)**

Module B compares Logilize-reported counts to the ledger per scoped entity: `variance(entity) = logilize_count − ledger_count` (where `ledger_count` is the StockPosition total at the matching dimensions). **The variance-computation contract is the integrity discipline — KEPT.** **The D16 SIMPLIFY (manual-first at launch):** the **tolerance-driven auto-reconciliation engine + cadence automation defer** → **operator-scheduled manual counts + manual variance review**, booking variances through the kept adjustment path (§13). The 4-state FSM itself is light (monotonic) — the real cut is the auto-reconcile/cadence automation; **the Stocktake entity + the variance-computation contract + `StocktakeReconciled` are the seam** (automation is additive when it lands). Above-tolerance variances resolve via an adjustment (§13), a QuarantineRecord (§14), or escalation to the shared Logilize discrepancy queue (§15.3). On `variance_review → reconciled`, Module B emits **`StocktakeReconciled`** (scope, resolution summary, supervisor, timestamp).

---

## §13 Inventory Adjustment Workflow **(committed-inventory protection FLOOR; the workflow already manual — minimal D16 trim)**

Module B owns the **inventory-adjustment workflow** — the single-supervisor-approval path for variance-driven inventory state changes. **The proposal→approve workflow is *already* single-supervisor-approval / operator-driven by spec — little to cut; committed-inventory protection is FLOOR (explicitly NOT a D16 candidate).**

### §13.1 Adjustment workflow + §13.2 adjustment types

The workflow: **(1)** variance detection (Logilize variance / operator observation / stocktake variance review / QuarantineRecord resolution); **(2)** operator proposal via the Admin Panel (scope: per-bottle / per-batch / per-case; adjustment type; quantity delta; reason); **(3)** pre-validation — the committed-inventory protection (§13.5): if the proposal would reduce committed inventory below outstanding vouchers → REJECT + emit `InventoryShortfallDetected`; else continue; **(4)** single-supervisor approval; **(5)** on approval → `InventoryAdjusted` (ATP push to Module A; Module E consumes for the financial event per DEC-072); **(6)** on rejection → audit-trail entry, no state mutation. **Adjustment types** (`{damage, loss, consumption, recount, transfer, found}`): `consumption` (no events at launch) + `transfer` (multi-warehouse deferred) are **Phase-2 placeholders — carried verbatim, do not re-cut** (§21). §17.4 destruction routes here as `damage`. **The only D16 trim is deferring any *automated* variance-source round-trips** (stocktake/quarantine-triggered) → manual operator follow-up (§0.2).

### §13.3 `InventoryAdjusted` emission + §13.4 `InventoryShortfallDetected` + §13.5 committed-inventory protection **(FLOOR)**

`InventoryAdjusted` carries the scope discriminator + entity reference, the adjustment type, the signed quantity delta, the reason, the actor + timestamp, the source variance reference, and the cost-basis attribute (read from the affected InboundBatch — Module E uses it for the financial event; **DEC-072 — Module B records the operational event, Module E records the financial event, Xero decides GL**). **Committed-inventory protection (Q-CL-6 — FLOOR, NOT a D16 candidate):** inventory committed to vouchers cannot be diverted for adjustments without first releasing the commitment through Module A `VoucherCancelled` (the single release primitive per DEC-099). The pre-validation rejects any negative-delta adjustment on committed inventory that would breach `committed_inventory − outstanding_vouchers + adjustment_delta ≥ 0`; rejection emits **`InventoryShortfallDetected`** to Module A (the affected allocation, the proposed scope/type/quantity, the negative shortfall delta, the actor, the timestamp). **Module A consumes it** and runs the shortfall workflow; the proposal **cannot proceed until Module A `VoucherCancelled` first releases the commitment** — keeping Module A's commitment ledger and Module B's physical ledger consistent at the transactional boundary. **Applies identically to NS** (§5.5). *(Module A §11.5.2 frames the consumer side — this PRD matches it.)*

---

## §14 QuarantineRecord **(the quarantine-before-trust gate FLOOR; the automated resolution cascades manual-first — D16, §0.2)**

Module B owns the **QuarantineRecord** entity and the quarantine-before-trust principle — the **gatekeeper primitive that keeps four-way reconciliation from degrading to three-way** (§2.4). **The gate is integrity — FLOOR, NOT a candidate; the automated cross-module cascades on resolution defer to manual-first (D16).**

### §14.1 Entity + quarantine-before-trust + §14.2 resolution paths + §14.3 immutability

**Module B never creates inventory records from unverified Logilize data** — unknown entities (unrecognised serial / batch / allocation mismatch / PR mismatch / state inconsistency) land in a QuarantineRecord pending supervisor investigation. The record carries the Logilize report, the trigger discriminator, the detection timestamp, the lifecycle state (`open` / `under_investigation` / `resolved`), and (at resolution) the resolution-path discriminator + supervisor identity + timestamp + reason. **The supervisor resolves via one of four paths** (already supervisor-driven by spec): **associate with existing batch** (the entity belongs to an existing InboundBatch; Module B updates it + emits the downstream event); **create new record** (legitimate but unknown; explicit supervisor sign-off — **no auto-create path from Logilize data**); **reject as invalid** (bogus; resolved with no ledger mutation); **escalate** (beyond the supervisor's authority → the shared Logilize discrepancy queue, §15.3). **Resolved records are immutable** — a wrong resolution is corrected via a **new** QuarantineRecord (or a §13 adjustment); the original stays intact as the audit anchor.

### §14.4 Cross-module cascades — the manual-first posture **(D16 / N1, §0.2)**

QuarantineRecord resolutions may cascade to **Module D** (cost-basis reconciliation where the resolution associates the entity with an existing InboundBatch and affects qty — `BottleQuarantineResolved`), **Module E** (a downstream §13 damage/loss/write-off adjustment → financial event), and **Module A** (an ATP push on new SerializedBottle / InboundBatch creation). The events `BottleQuarantined` (entry) and `BottleQuarantineResolved` (resolution) source the §10.2 ATP push. **The D16 SIMPLIFY (manual-first at launch, N1 — identical with Module D §13.4):** the **automated cross-module cascades on resolution defer** → **manual operator-triggered follow-ups** (the operator records the cost-basis reconciliation / financial-event / ATP follow-up manually). **The gate + the 4 resolution paths + immutability + the events are the seam** — the automated cascades are additive when they land. **Quarantine-before-trust composes through every Logilize stream** (§15) — Logilize-reported entities that don't match Module B's ledger land in a QuarantineRecord; the KPI is zero ledger-corruption events from Logilize-side bugs (§22.1).

---

## §15 Logilize Integration (5 inventory-state streams) **(the records/executes split KEPT; mechanics tech; Stream B1 = the R3 migration target)**

Module B owns the **5 inventory-state Logilize streams** (the **Module B records / Logilize executes** recorder-not-gatekeeper contract). The integration *mechanics* (API/payload/retry) are tech (DEC-073); the *automation depth* of streams B3/B4/B5 tracks the D16 manual-first posture (§0.2).

### §15.1 Module B's inventory-state stream contract **(Stream B1 = a named B↔C contract, §0.6)**

- **Stream B1 — Storage-location tracking** (migrated Module C → Module B — **the R3 migration target**; storage-location is Module B's, not Module C's). Module B reads the **warehouse-level summary** for the Cellar render + Bottle Page; sub-warehouse detail is Logilize-internal (already-deferred). **The Cellar data source switches from Logilize-direct to Module B-summary (DEC-188 — a named B↔C contract, §0.6).**
- **Stream B2 — Receiving + physical-match** (§11; DEC-194).
- **Stream B3 — Stocktake instruction + variance reporting** (§12; the auto-reconciliation/cadence automation manual-first, §0.2).
- **Stream B4 — Adjustment proposal-and-confirmation** (§13).
- **Stream B5 — QuarantineRecord resolution flow** (§14; the automated cascades manual-first, §0.2).

### §15.2 Boundary with Module C's fulfilment streams **(R3 — 5→4 stream reduction)**

The original 5-stream contract is split: **Module C retains 4 fulfilment streams** (outbound pick instruction, pick confirmation, dispatch confirmation, delivery confirmation); **Module B owns 5 inventory-state streams** (B1 storage-location migrated from C; B2–B5 net-new for inventory-state authority). **This is RECONCILE R3 from Module C's side** (Module C v0.3-MVP reconciles its contract to 4-fulfilment-stream; storage-location = Module B's Stream B1) — named here as the B↔C contract for the Module C session (§0.6). The boundary: bottle-state events flow through Module C streams; inventory-state events flow through Module B streams.

### §15.3 Shared Logilize discrepancy queue **(DEC-141 — a named B↔C contract; the manual-first operator surface)**

The **NewCo Admin Panel "Logilize discrepancy" queue** is shared across Module B (inventory-state-side discrepancies — QuarantineRecord resolution, `InboundBatchDiscrepancy` flow-back, stocktake variance review) and Module C (fulfilment-side discrepancies). **This is the operator triage surface the D16 manual-first ops needs** (the manual-first workflows land here — §0.2); it is a named B↔C contract (§0.6) and is also flagged for the 9th Admin-Panel PRD. Reconciliation is real-time event-driven (no batch jobs at launch); the reconciliation algorithm is tech (DEC-073).

---

## §16 Bottle Page Data-Source Contract **(KEEP — the one customer-facing surface; the NFT/chain-link content portions DECOUPLED — D12; a named B↔C contract, §0.6)**

Module B is the **system of record for Bottle Page data** — the one customer-facing surface (read-only, storefront-exempt, **zero customer identifiers** — DEC-024). The render surface is tech (DEC-073). **The Bottle Page renders the non-NFT content at launch; the NFT/chain-link content portions ride the D12 decouple and light up when the on-chain workstream lands (§0.1).**

### §16.1 Module B as system of record + §16.2 launch content

Module B is the data-source layer; an external rendering service (tech, DEC-073) consumes it. **Launch content (renders with or without the on-chain layer):** producer profile (Module K's customer-facing description); tasting notes (Product-Master / Product-Variant / PR-level prose from Module 0 *(naming cascade)*); allocation context (Product Reference + Product Variant + Allocation lineage); the provenance trail (the chronological location-waypoint trail). **Decoupled (D12):** the "Link to NFT" (Avalanche tx + chain-explorer URL) + the predecessor/successor chain render + the pre-burn/post-burn variant — the data contract already separates the nullable `nft_reference` field from the rest, so the chain-link is absent at launch and back-fills additively (§16.5). Richer media (video/AR) already-deferred (§21).

### §16.3 Six-locale fallback chain + §16.4 anonymisation framing

**Locale resolution:** visitor preference cookie (consent-banner-gated) > browser `Accept-Language` > English, with **per-attribute fallback** (a missing string in the resolved locale falls back to English **for that string only**, not the whole page). Module 0 owns the translatable-string entities; Module B reads the resolved-locale string at render. **Anonymisation (DEC-128, compliance-adjacent):** the provenance trail uses the **customer-as-anonymous-destination** framing ("in producer cellar from YYYY → in NewCo warehouse from dd/mm/yyyy → delivered to private cellar on dd/mm/yyyy") with **zero customer identifiers** (no Customer.id / Profile.id / Voucher.id / shipment recipient / shipment address). The trail composes from upstream events (vintage from Module 0; warehouse arrival from Module D's `InboundEventPhysicallyAccepted`; dispatch + delivery from Module C). The customer-facing trail is the public slice of the broader internal provenance chain (§18.1); internal-only events (ownership transitions, stocktake reconciliations, adjustments, quarantine resolutions) stay off the Bottle Page.

### §16.5 Pre/post-burn variants (decoupled) + §16.6 anonymous (no personalisation) + the D17 cellar contribution

The **pre-burn vs post-burn variant** is derived from the SerializedBottle's lifecycle state + the NFT-reference status — **decoupled (D12):** at launch (`nft_reference = NULL`) the Bottle Page renders the non-NFT content; the variant distinction + chain-link light up when the on-chain workstream lands. The Bottle Page is **public, anonymous, not a personalisation surface** (no logged-in-Customer code path; no SSO — DEC-024). **D17 cellar-render contribution (basic, §0.7):** Module B contributes the **Bottle Page link + warehouse-level inventory summary** to the six-module cellar read (DEC-154 — minimal; the cellar render UX is Module C scope; the Cellar data source switches Logilize-direct → B-summary, the named B↔C contract §0.6). For non-serialized stock there is **no Bottle Page** (the digital-provenance no-op — Module B's data contract returns "non-serialized" / no-record; the rendering surface handles the absence).

---

## §17 Recovery Scenarios **(the on-chain recovery chains DECOUPLED — D12; the §17.4 inventory-ledger write-off side KEPT; §0.1)**

The four §6.11 recovery scenarios are **digital-provenance content — the on-chain recovery chains are DECOUPLED off the launch critical path (D12), behind the re-scoped EXT-1 gate**, back-filled with the on-chain workstream. **Exception: the §17.4 inventory-ledger write-off side is KEPT** (part of the adjustment floor, §13). **Do not re-cut** the already-deferred working-hypothesis cluster (the recovery-chain shape, the stale-attestation mechanism — §21).

- **§17.1 Damaged tag in warehouse** (DECOUPLED) — the five-event on-chain chain (`NFCTagDamagedInCustody` → ops authorisation → `NFCTagReapplied` → `NFTReissued` → `NFTBurnedAsTagDamaged`) rides the decouple; the SerializedBottle stays `in_storage` (no lifecycle regression); no Module A/S/C cascade. **At launch, behind the EXT-1 gate; the physical re-tag + serial re-capture are launch-ready, the on-chain re-mint/burn back-fill.**
- **§17.2 Damaged tag post-shipment** (event-recording role only) — Module B emits `BottlePostShipmentTagIssueReported` + `ProvenanceCertificateIssued` (a non-NFT signed certificate attesting pre-burn provenance); **no re-tagging** (no-returns-post-shipment + burn-finality stand). The Customer Care remedy (`CustomerServiceRemedyApplied`) is Module S / Module E scope, not Module B (already-bounded). *(Rides the decoupled workstream — the certificate attests the on-chain provenance that back-fills.)*
- **§17.3 NFT lost in NewCo wallet** (DECOUPLED) — `NFTLossInWalletDetected` + `NFTReissuedDueToWalletLoss`; the lost NFT is NOT burned (dangling-token, recorded stale; the tag is unchanged — no `NFCTagReapplied`); the SerializedBottle lifecycle is unaffected. **On-chain; behind the EXT-1 gate.**
- **§17.4 Bottle destroyed pre-shipment** (the inventory-ledger write-off side KEPT; only the NFT-burn-as-destroyed decouples) — `BottleDestroyedInCustody` → **`written_off` lifecycle (terminal) + `InventoryAdjusted` (`adjustment_type = damage`) + Module A `AllocationPoolDebitedDueToLoss`** (the inventory-ledger write-off side — **KEPT**, part of the adjustment floor); the **`BottleNFTBurnedAsDestroyed` on-chain burn decouples** (back-fills). Module S consumes for `VoucherSubstitutionExecuted` only if a bound Voucher pre-existed (rare edge — late binding is at shipment).

---

## §18 Business Rules and Invariants

Load-bearing rules, prefixed `BR-B-{Domain}-NN` (verbatim-restated per DEC-074; naming cascade applied where they reference PR/Wine).

### §18.1 Provenance chain discipline
- **BR-B-Provenance-1** — per-bottle provenance immutability: every state / custody / ownership / commercial-status change on a SerializedBottle is an immutable provenance event (the internal chain extends beyond the customer-facing trail — ownership transitions, stocktake reconciliations, adjustments, quarantine resolutions, NFC re-applications, NFT re-mints/burns *(the on-chain portions decoupled)*).
- **BR-B-Provenance-2** — per-batch provenance for non-serialized (NS adjustments recorded as quantity deltas on the InboundBatch; scope discriminator `per-batch`).
- **BR-B-Provenance-3** — append-only invariant (a correction is a NEW event; the historical event stays intact).
- **BR-B-Provenance-4** — resolved-record immutability (resolved QuarantineRecords, reconciled Stocktakes, approved InventoryAdjustments are immutable).

### §18.2 Ledger-authority invariants
- **BR-B-Ledger-1** — serial uniqueness (every `SerializedBottle.serial` globally unique; never reused).
- **BR-B-Ledger-2** — allocation-lineage immutability (immutable on every InboundBatch / SerializedBottle / Case / StockPosition cell; never interchangeable across allocations).
- **BR-B-Ledger-3** — serialization irreversibility (`qty_actually_serialized` irreversible; serialized → non-serialized NOT admitted).
- **BR-B-Ledger-4** — case-breaking irreversibility (the Case FSM monotonic; `broken` terminal; released bottles retain lineage).
- **BR-B-Ledger-5** — ownership-flag transition discipline **(R4 landed, §0.3):** Module B records and executes the `PRODUCER → CRURATED` transition but does not decide it — Module D sets the initial flag at InboundBatch creation; **Module E emits `SupplierPaymentCompleted`** (the payment executor — B consumes, does NOT emit, the E-emits trap); Module B preserves custody history + allocation lineage across the transition. *(N3: the inventory `ownership_flag` `CRURATED` ledger keys to `SupplierPaymentCompleted`; distinct from Module D's PO-level `NEWCO` title ledger, keyed to `VoucherIssued` — same party, two signals.)*

### §18.3 No-overselling guards **(FLOOR)**
- **BR-B-NoOversell-1** — two-layer guard (Module A Layer 1 `qty − issued ≥ 0` ∧ Module B Layer 2 `physical_in_storage − reserved − quarantined − under_adjustment ≥ 0`; either failure rejects the hold).
- **BR-B-NoOversell-2** — sub-pool overselling rule per allocation (composes per sub-pool; cross-sub-pool fungibility NOT admitted). *(Composes with Module A's operation-level over-issuance rejection — no `AllocationCapacityExhausted` event, §10.5.)*
- **BR-B-NoOversell-3** — ATP push to Module A on every state change (the B→A push; strongly consistent at the transactional boundary; the mechanics are tech, DEC-073).

### §18.4 Committed-inventory protection **(FLOOR)**
- **BR-B-Commit-1** — committed inventory cannot be diverted for adjustments without first releasing the commitment via Module A `VoucherCancelled`; rejection emits `InventoryShortfallDetected`.
- **BR-B-Commit-2** — event-consumption diversion (placeholder; N/A at launch — no events; reactivates Phase 2+).

### §18.5 Quarantine-before-trust **(FLOOR — the gate)**
- **BR-B-Quarantine-1** — Module B never creates inventory records from unverified Logilize data; unknown entities quarantine (composes through every Logilize stream).
- **BR-B-Quarantine-2** — resolution audit trail (supervisor identity, decision path, reason, resulting mutation; immutable). *(The automated resolution cascades are manual-first at launch — D16, §0.2; the gate stands.)*

### §18.6 Recorder-not-gatekeeper on layered breakability
- **BR-B-Breakability-1** — Module B records and executes case-breaking events; does NOT re-derive or gate the layered-breakability rule (`Layer 2 OR Layer 3`, composed upstream).
- **BR-B-Breakability-2** — whole-case dispatch under `effective_unbreakable = true` (voucher count = bottle count; overage structurally impossible).

### §18.7 Storage-location + bottle-days exposure
- **BR-B-Storage-1** — warehouse-level customer-facing display (sub-warehouse detail Logilize-internal, already-deferred).
- **BR-B-Storage-2** — bottle-days-in-storage data exposure per allocation lineage (data only; **storage-fee pricing + customer-level billing live in Module S + Module E** — Module B does not hold pricing or know which customer owns the voucher attached to each bottle).

### §18.8 Receiving + adjustment + stocktake reconciliation discipline
- **BR-B-Reconcile-1** — two-stage receiving discipline (DEC-194; D = documents, B = physical match). *(Integrity core FLOOR; the automated round-trip manual-first, N1.)*
- **BR-B-Reconcile-2** — stocktake variance computation (`logilize_count − ledger_count`; within-tolerance auto-reconcile is manual-first at launch — D16).
- **BR-B-Reconcile-3** — adjustment supervisor approval (single-supervisor at the PRD layer; the R&R policy is admin-configurable downstream).
- **BR-B-Reconcile-4** — cost-basis flow provisional → finalized (FLOOR-adjacent; goods sellable at the physical-match transition).

### §18.9 Anonymisation discipline
- **BR-B-Anonymisation-1** — no PII on-chain *(rides the D12 decouple — the on-chain surface is behind the EXT-1 gate; the invariant stands when the workstream lands)*.
- **BR-B-Anonymisation-2** — Bottle Page customer-as-anonymous-destination (zero customer identifiers in the data feed — KEPT, compliance-adjacent).
- **BR-B-Anonymisation-3** — Bottle Page is anonymous, no personalisation (no logged-in-Customer code path — KEPT).

---

## §19 Domain Events Catalogue

Module B emits a versioned set of domain events; payload field-by-field listings are out of PRD scope (DEC-073). Every event carries the standard audit envelope (opaque event id, source entity reference, timestamp, actor). *(Naming cascade applied to PR-referencing payloads.)*

### §19.1 Emitted by Module B

**Inventory-ledger events (FLOOR — KEPT):** `BottleStateChanged`, `OwnershipTransitioned` *(`PRODUCER → CRURATED`; triggered by the **E-emitted** `SupplierPaymentCompleted` — R4; recorded by Module B for provenance)*, `InboundBatchCreated`, `InboundBatchStateChanged`, `InboundBatchDiscrepancy` *(consumed by Module D; the automated round-trip manual-first — N1)*, `BatchSerializationDiscrepancy`, `InventoryAdjusted` *(parameterised `adjustment_type`; consumed by Module A [ATP push] + Module E [financial event])*, `InventoryShortfallDetected` *(the committed-inventory short-circuit; consumed by Module A)*, `StocktakeReconciled`, `StocktakePlanned`/`StocktakeStarted`, `BottleQuarantined`, `BottleQuarantineResolved`, `CaseIntegrityChanged`. **The NS informational event** `BottleShippedAsNonSerialized` *(the D12 universal fallback — §5.6)*.

**Digital-provenance / serialization events:** `NFCTagApplied` *(KEEP / launch-ready — un-gated; the SerializedBottle creation trigger)*, `BottleSerialized` *(the SerializedBottle-creation composite — the ledger-creation side launch-ready; the NFT-correlation side decoupled)*.

**Digital-provenance / on-chain events (DECOUPLED — D12; behind the re-scoped EXT-1 gate; back-filled):** `NFTMinted`, `BottleNFTBurned` *(parameterised `reason`)*, and the recovery-scenario variants `NFCTagDamagedInCustody`, `NFCTagReapplied`, `NFTReissued`, `NFTBurnedAsTagDamaged`, `BottlePostShipmentTagIssueReported`, `ProvenanceCertificateIssued`, `NFTLossInWalletDetected`, `NFTReissuedDueToWalletLoss`, `BottleDestroyedInCustody` *(the inventory-ledger write-off side KEPT; the NFT-burn portion decoupled)*, `BottleNFTBurnedAsDestroyed` *(decoupled)*.

**N/A at launch (already-deferred):** `ConsignmentPlacementRecorded`, `ConsignmentSellThroughRecorded` (§21).

### §19.2 Consumed by Module B

- **Module D `InboundEventPhysicallyAccepted`** — the InboundBatch creation trigger (DEC-195; floor chain 1).
- **Module D `InboundEventCostFinalized`** — the cost-basis flip provisional → finalized.
- **Module D `ConsignmentReceiptRecorded`** — V2 intake (composes into a single InboundBatch).
- **Module S `VoucherShipped`** — the NFT burn trigger for serialized stock *(the burn decoupled — D12; NS fires `BottleShippedAsNonSerialized`)*.
- **Module S `VoucherIssued`** — observed; triggers the serialization workflow on serialized stock (B reads the bound Allocation sub-pool partition to drive serialization).
- **Module A `AllocationSerializationPlanChanged`** (composed from `AllocationSubPoolRebalanced` + `AllocationNonSerializedOptOutChanged`) — update `qty_planned_serialize` (§3.3 / §5.4); plus `AllocationCreated`/`AllocationActivated` (serialization-pipeline pacing/gating) + `AllocationCapacityIncreased`/`AllocationCapacityDecreased` (ATP recalc).
- **Module A `VoucherCancelled`** — releases the committed-inventory commitment (allows a previously-blocked §13 adjustment to proceed — DEC-099; the committed-inventory floor).
- **⚠️ Module E `SupplierPaymentCompleted` — E-EMITTED, B-CONSUMES it (R4, §0.3 — the E-emits trap).** Triggers the inventory `ownership_flag` `PRODUCER → CRURATED` transition (`OwnershipTransitioned`). **Module B does NOT emit or derive this event** (the cut-sheets' "D-emits" framing is superseded by Phase C R4; the v1.1's "Phase C cascade question" is now settled as E-emits). Distinct from Module D's PO-level title transition (N3).
- **Logilize integration events** — sourcing the 5 inventory-state streams (§15; mechanics tech, DEC-073).

### §19.3 Observed-but-not-Module-B-owned

`AllocationPoolDebitedDueToLoss` (Module A — the §17.4 destruction cascade; the inventory-ledger write-off side KEPT), `CustomerServiceRemedyApplied` (Module S / Customer Care — §17.2), `VoucherSubstitutionExecuted` (Module S — §17.4 bound-Voucher edge), and Module C's `BottlePicked`/`ShipmentDispatched`/`BottleDelivered` (Module B observes via the Module S `VoucherShipped` chain, not directly).

---

## §20 Personas + Cross-Module Dependencies

### §20.1 Personas **(confirms L-PP / P2 — all ops/back-office, §0.8)**

Logistics / Operations Manager (stocktake planning, supervisor approval on adjustments, QuarantineRecord resolution); NewCo Operations Operator (adjustment proposals, QuarantineRecord triage, NFC re-tag authorisation, destruction recording); **NewCo Wallet Operator** (the §17.3 wallet-loss confirmations — **rides the D12 decouple**); **Customer Care Operator** (the §17.2 post-shipment tag-damage reports + certificates — rides the decouple); plus the cross-module observers — Procurement Operator (observes `InboundBatchDiscrepancy`), Finance Operator (observes `InventoryAdjusted`). **Every persona is ops/back-office — zero producer self-serve, zero consumer self-serve writes** (§0.8). The R&R / approval-tier policy is admin-configurable (not specified at the PRD layer).

### §20.2 Cross-module dependencies (the contract summary)

- **Module A** — Module B reads the sub-pool partition + allocation lineage; emits the ATP push (`BottleStateChanged`, `InventoryAdjusted`, `OwnershipTransitioned`, `BottleQuarantined`/`Resolved`, `StocktakeReconciled`, NS-counter mutations) + `InventoryShortfallDetected`; consumes `VoucherCancelled` (the release primitive) + `AllocationSerializationPlanChanged`. The ATP-cache reconciliation contract is Module A's (§11.5.1; mechanics tech).
- **Module D** — Module B consumes `InboundEventPhysicallyAccepted` (→ InboundBatch, floor chain 1) + `InboundEventCostFinalized` + `ConsignmentReceiptRecorded`; emits `InboundBatchDiscrepancy` + `BottleQuarantineResolved` (the reciprocal cascades — **manual-first at launch, N1**). The DEC-194 split (D = documents, B = physical match). **Module D's §14.5 frames this contract from D's side — this PRD matches it.** Both D and B consume the **E-emitted** `SupplierPaymentCompleted` (R4 — D for the PO title, B for the inventory `ownership_flag`; N3).
- **Module S** — Module B exposes the per-sub-pool Layer-2 ATP for S's lesser-of storefront read (§10.4); consumes `VoucherIssued` (→ serialization) + `VoucherShipped` (→ NFT burn — **decoupled, D12**; NS fires `BottleShippedAsNonSerialized`). The Bottle Page is B's (DEC-024); S reads B's Bottle Page link. **(Module S's four ratified contracts honoured whole — Q4.)**
- **Module C** *(next session — the named B↔C contracts, §0.6)* — C reads B's StockPosition (late-binding pick) + serialized-bottle identity + the Bottle Page link + inventory summary (cellar render); B owns Stream B1 (storage-location — the R3 migration; the shared Logilize discrepancy queue B+C); the NFT-burn chain originates at C's `ShipmentDispatched` (decoupled — D12).
- **Module E** — Module B emits `InventoryAdjusted` (damage/loss/write-off) + cost-basis-at-dispatch reads + `BottleShippedAsNonSerialized` (OC settlement read) + bottle-days-in-storage data (storage-fee computation joins downstream); **consumes the E-emitted `SupplierPaymentCompleted`** (R4). DEC-072 — Module B records the operational event, Module E records the financial event, Xero decides GL. **No Module E NFT touchpoint** (the decouple — Phase C item J).
- **Module 0 + Module K** — Module B reads Product Reference / Composite SKU / breakability Layer 1 / Case Configuration whitelist + Bottle Page content (Module 0) + the Producer customer-facing description (Module K). Both upstreams ratified KEEP — no read orphaned. Naming cascade applies.
- **Logilize / Vinlock** — Module B records, Logilize executes (the recorder-not-gatekeeper discipline + four-way reconciliation); mechanics tech (DEC-073).

---

## §21 Out of Scope at Launch **(already-deferred — carried verbatim; do not re-cut)**

Module B v0.3-MVP carries the v1.1 launch out-of-scope set forward verbatim with its existing re-introduction seams (P1; all feed [`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md)):

**Inventory-side already-deferred:** third-party custody intake + `THIRD_PARTY` ownership_flag value (2-value enum at launch); ConsignmentPlacement entity + `ConsignmentPlacementRecorded`/`ConsignmentSellThroughRecorded` + active-consignment SELL_THROUGH_SETTLEMENT (B2C-only at launch — the tri-module restoration trace); `AGENCY` sourcing; multi-warehouse expansion; sub-warehouse storage-location detail (Logilize-internal); storage-fee pricing + customer-level billing (Module S/E own); the `consumption` + `transfer` adjustment-type placeholders (Phase-2; carry); full reverse-inbound mechanics (OQ-12/18, DEC-152).

**Digital-provenance / on-chain — D12-decoupled (off the critical path, behind the re-scoped EXT-1 gate; back-filled — NOT deleted, §0.1) + already-deferred:** Avalanche on-chain execution (smart-contract code, RPC, gas, on-chain encoding); NFT mint-payload literal encoding; NFC tag-write protocol (on-chip encoding, write-protection mode, URL pattern); the wallet operational architecture (cold-storage, multi-signature, key-rotation); smart-contract audit + governance + upgrade-path (EXT-3); the **NFT working-hypothesis cluster** (DEC-120 recovery-chain shape, DEC-121 1:1-vs-batch mint cardinality, DEC-122 mint-payload composition, DEC-124 NFC tag-write content, DEC-131 stale-attestation mechanism — **carry verbatim; do not re-cut**); the §17.3 stale-attestation operational mechanism; richer Bottle Page media (video/AR); post-shipment NFC tag re-tagging (no re-tag — §17.2).

**Out-of-scope (other modules / tech):** the Bottle Page render surface; the Cellar render UX (Module C); the Logilize integration mechanics; Customer Service remedy mechanics (Module S/E); translatable-string entity lifecycle + translation workflow (Module 0).

---

## §22 Open Threads + Success Metrics

### §22.1 Success Metrics (PRD-level; the literal monitoring/alarming is tech, DEC-073)

| Metric | Target at NewCo launch | Disposition |
|---|---|---|
| Inbound physical-discrepancy rate | <5% (baseline 15–20% in the three-way regime) | KEPT — FLOOR (the DEC-194 two-stage discipline) |
| ATP push end-to-end latency (inventory event → Module A cache) | sub-1s | KEPT — FLOOR (the B→A push; the literal SLA is tech) |
| Storefront ATP staleness | ≤5s sustained at peak | KEPT — FLOOR |
| Hold-placement transactional check | ≤200ms p99 | KEPT — FLOOR |
| Ledger-corruption events from Logilize-side bugs | 0 (the quarantine-before-trust gate) | KEPT — FLOOR |
| Provenance-immutability / two-layer-no-oversell / committed-inventory-protection violations | 0 | KEPT — FLOOR |
| Stocktake variance auto-reconciliation rate | tracked; **auto-reconciliation is manual-first at launch (D16)** | SIMPLIFIED — automation deferred |
| NFT-burn 1:1 cardinality compliance | 100% (working-hypothesis) | **DECOUPLED — verified when the on-chain workstream + EXT-1 land** |

### §22.2 Open threads + the Paolo-track action items

- **The two D12 owned action items (time-sensitive, Paolo-track — §0.1):** (1) schedule the EXT-1 blockchain-expert review now; (2) confirm the DEC-124 tag-content back-fill design (serial + URL at launch, on-chain ref back-fillable). Feed the feasibility into the Phase-E re-estimate.
- **The three Phase-C cascades (settled — handled in this PRD):** Module A's ATP-cache reconciliation-cadence contract (CONSISTENT; mechanics tech); the **E-emitted `SupplierPaymentCompleted`** → B's `OwnershipTransitioned` (R4, §0.3); Module C's 5→4 Logilize-stream reduction + the Cellar data-source switch to Module B (R3 / DEC-188 — the named B↔C contracts, §0.6).
- **Build-sequencing (Phase-E flag, §0.5):** B's floor artefacts (Layer-2 push, InboundBatch, StockPosition, per-sub-pool ATP) integration-ready by the integrated launch (B phase 5; A/D=3, S=4, C=5 depend on B).
- **PRD ambiguities (AMB-B-1..5)** — an acceptance-authoring backlog (the `BottleSerialized` composite-event optionality, the `InboundBatchStateChanged` enum coverage, the `consumption`/`transfer` placeholders, the supervisor-role binding, the Stage-8-vs-preserved EXT-1 gate scope) — orthogonal to MVP scope; **AMB-B-5 is exactly resolved by the re-scoped EXT-1 gate** (§0.1 / acceptance §0).

---

## §N Audit-Trail Trace Appendix (DEC-074)

The v17 §B inheritance map, the v0.1 § preservation map, and the Stage-8 supersession ledger **live in the frozen v0.2 §N** (not reproduced here per DEC-074 — the body restates the substance). This appendix adds **§N.4 — the MVP re-baseline trace.**

### §N.4 MVP re-baseline trace

This trace maps each v0.3-MVP section to its **frozen v1.1 predecessor** (`greenfield/01-prd/Module_B_PRD_v0.2.md`) + the **ratified cut-sheet** + **Phase C**. The load-bearing prose is the body above (DEC-074); this trace is for audit / diff.

| v0.3-MVP section | v1.1 (v0.2) anchor | Cut-sheet / Phase C | MVP disposition |
|---|---|---|---|
| §0 MVP scope at a glance | — (new) | cut-sheet §1; Phase C §1 | NEW — Phase D framing; KEEP-whole-on-floor + D12 decouple + D16 simplify + R4-consumer + N1/N3 verdict. |
| §0.1 D12 decouple | — | cut-sheet §3.1; Phase C item J / Q3 | NEW — DECOUPLE the on-chain layer; serialization launch-ready; `nft_reference` nullable; re-scoped EXT-1 gate; NS universal fallback; the two Paolo-track action items. |
| §0.2 D16 simplify | — | cut-sheet §3.2; Phase C item H / N1 | NEW — SIMPLIFY the Stage-8 workflow automation → manual-first; integrity core KEPT; identical with Module D. |
| §0.3 R4 + N3 | — | Phase C §2-C/§5-R4/§5-N3 | NEW — the E-emits `SupplierPaymentCompleted` consumer side; B.2 sell-through prose reconciled; the CRURATED-vs-NEWCO two-ledger clarity. |
| §1 Module Scope | v0.2 §1 | cut-sheet §2 | KEEP; in-scope re-grouped by floor / launch-ready / decoupled; naming cascade. |
| §2 Inventory Authority Framing | v0.2 §2 | cut-sheet B.1–B.4; R4/N3 | KEEP — FLOOR; §2.2 **R4 landed** (the sell-through prose → `SupplierPaymentCompleted`; N3 two-ledger); four-way reconciliation discipline FLOOR. |
| §3 InboundBatch | v0.2 §3 | cut-sheet B.5–B.9; floor chain 1 / DEC-195 | KEEP — FLOOR; the core-loop "bottle received into inventory" step; cost-basis provisional → finalized. |
| §4 SerializedBottle | v0.2 §4 | cut-sheet B.10; D12 / Q1 | KEEP — **launch-ready**; `nft_reference` nullable + back-fillable (only the on-chain attribute decouples); naming cascade on the PR identity. |
| §5 Non-Serialized | v0.2 §5 | cut-sheet B.14/B.15; D12 seam | KEEP — FLOOR (doubly load-bearing: Layer 2 for NS + the D12 decouple seam); `BottleShippedAsNonSerialized` = the universal fallback. |
| §6 NFC Tag Application | v0.2 §6 | cut-sheet B.11; D12 | KEEP / launch-ready (tag + serial + `NFCTagApplied` + SerializedBottle creation); the on-chain-reference tag content + the NFT-mint trigger DECOUPLE; §6.3 the back-fill design (Paolo-track item 2). |
| §7 Case Entity | v0.2 §7 | cut-sheet B.16/B.17; item A | KEEP + GENERALISE (the Layer-1 `Product Variant.possible_case_configs` read); recorder-not-gatekeeper. |
| §8 StockPosition | v0.2 §8 | cut-sheet B.18; FLOOR / item A | KEEP — FLOOR + GENERALISE (the `(PR, …)` intersection); a named B↔C contract. |
| §9 NFT Mint and Burn | v0.2 §9 | cut-sheet B.12; D12 | **DECOUPLED** — off the critical path, behind the re-scoped EXT-1 gate; back-filled; the C→S→B burn chain rides the decouple (NS fallback). |
| §10 ATP + two-layer guard | v0.2 §10 | cut-sheet B.19–B.22; FLOOR / item G | KEEP — FLOOR; the B→A push + the lesser-of read + the two-layer guard per sub-pool; the `AllocationCapacityExhausted` composition note. |
| §11 Receiving + Discrepancy | v0.2 §11 | cut-sheet B.23/B.24; DEC-194 / N1 | KEEP the DEC-194 split + the physical-match (integrity core FLOOR); the automated round-trip manual-first (N1 — identical with Module D). |
| §12 Stocktake | v0.2 §12 | cut-sheet B.25; D16 | KEEP the reconciliation discipline (integrity); the tolerance-driven auto-reconciliation + cadence automation → manual-first (D16). |
| §13 Inventory Adjustment | v0.2 §13 | cut-sheet B.26–B.28; FLOOR / Q-CL-6 | KEEP — committed-inventory protection FLOOR (NOT a D16 candidate); the workflow already manual; `consumption`/`transfer` placeholders carried. |
| §14 QuarantineRecord | v0.2 §14 | cut-sheet B.29/B.30; D16 | KEEP the gate (integrity FLOOR); the automated cross-module cascades on resolution → manual-first (D16 / N1). |
| §15 Logilize Integration | v0.2 §15 | cut-sheet B.31/B.32; R3 / DEC-188 | KEEP the records/executes split; Stream B1 = the R3 migration target (storage-location migrated C→B); the shared discrepancy queue (DEC-141) — named B↔C contracts; mechanics tech. |
| §16 Bottle Page | v0.2 §16 | cut-sheet B.33–B.35; D12 / D17 | KEEP (data contract + anonymisation + 6-locale) + GENERALISE; the NFT/chain-link content DECOUPLED; the D17 cellar contribution → basic (Bottle Page link + inventory summary). |
| §17 Recovery Scenarios | v0.2 §17 | cut-sheet B.13; D12 | the on-chain recovery chains DECOUPLED (behind EXT-1); the §17.4 inventory-ledger write-off side KEPT (the adjustment floor). |
| §18 Business Rules | v0.2 §18 | cut-sheet B.36; R4/N3/item A | KEEP all; **BR-B-Ledger-5 reconciled** (E-emits `SupplierPaymentCompleted`, B consumes — R4; N3 two-ledger); naming cascade; the anonymisation rules ride the decouple. |
| §19 Domain Events | v0.2 §19 | cut-sheet B.37; R4 / D12 | KEEP the inventory-ledger families (FLOOR); `SupplierPaymentCompleted` confirmed **consumed-from-E** (R4); the NFT/recovery families DECOUPLED; `BottleShippedAsNonSerialized` = the universal fallback; naming cascade. |
| §20 Personas + Cross-Module | v0.2 §20 | cut-sheet B.38/B.39; L-PP / R4 | KEEP — confirms L-PP / P2 (all ops; zero self-serve writes); the Wallet/Customer-Care operators ride the decouple; the cross-module contracts (incl. the named B↔C set). |
| §21 Out of Scope | v0.2 §21 | cut-sheet B.40; item N | KEEP verbatim → roadmap; the D12-decoupled on-chain set is off-the-critical-path (back-filled), not deleted; the NFT working-hypothesis cluster carried. |
| §22 Open Threads + Metrics | v0.2 §22 | cut-sheet §5/§6 | KEEP; the metrics annotated (FLOOR kept; the auto-reconciliation manual-first; the NFT-burn metric decoupled); the two Paolo-track action items + the build-sequencing flag. |
| §N MVP re-baseline trace | v0.2 §N (v17/v0.1/Stage-8 traces) | — | NEW — this trace (the v17 inheritance / v0.1 preservation / Stage-8 supersession traces live in the frozen v0.2 §N). |

Notation: *KEEP* = the v1.1 substance is restated in full NewCo language without semantic change; *FLOOR* = an un-cuttable floor piece; *launch-ready* = KEPT on the critical path (the serialization workflow); *DECOUPLE* = KEPT as a value-prop but moved off the launch critical path as a parallel workstream, back-fillable (D12 — DECOUPLE ≠ DEFER); *SIMPLIFY* = the workflow automation deferred to manual-first, the integrity core + entities/events KEPT (D16); *RECONCILE* = the R4 contract-consistency fix (naming/contract only); *GENERALISE* = naming-only rename (Product Reference / Master + consumed Module-0 events), non-behavioural; *NEW* = Phase-D framing with no direct v1.1 predecessor.

---

## Cross-references

- **v1.1 predecessor (frozen)** — [`../../reference/v1.1/01-prd/Module_B_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_B_PRD_v0.2.md). The source spec (the inventory-authority module) carried at full fidelity; never edited (plan R4). Its §N carries the v17 §B / v0.1 preservation / Stage-8 supersession traces.
- **Ratified cut-sheet** — [`../01-triage/Module_B_CutSheet_v0.1.md`](../01-triage/Module_B_CutSheet_v0.1.md). §2 inventory (B.1–B.40 = scope), §3 module-specific changes (D12 / D16 / R4-consumer / N1 / D17/D15 / L-PP + naming cascade), §5 acceptance delta, §6 the seven ratified Qs. **⚠️ Any "D emits `SupplierPaymentCompleted`" reading (D.24/E.32) is superseded by Phase C R4 (E-emits — §0.3); the cut-sheet stays as the Phase B record.**
- **Phase C reconciliation** — [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md). R4 (E-emits — B is a consumer side, §2-C/§5-R4), N1 (D16 manual-first — identical with Module D, item H), N3 (party naming), item G (no-oversell build-sequencing), item I (Direct Purchase deferred), item J (the NFT/on-chain DECOUPLE — NS universal fallback), §6 floor chains.
- **Naming source of truth** — [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18 (the canonical name table + the B physical-unit carve-out). Applied here, not re-derived.
- **Settled siblings (the cross-module contracts B shares)** — [`Module_D_PRD_v0.3-MVP.md`](Module_D_PRD_v0.3-MVP.md) (§3.4/§13.3 the N1 manual-first depth this PRD matches; §14.5 the B↔D contract from D's side; the E-emitted `SupplierPaymentCompleted` both consume) · [`Module_A_PRD_v0.3-MVP.md`](Module_A_PRD_v0.3-MVP.md) (§7.1 Layer 1 — operation-level rejection, no `AllocationCapacityExhausted` event; §11.5.1 the ATP cache / the B→A push; §11.5.2 `InventoryShortfallDetected` + `VoucherCancelled`) · [`Module_S_PRD_v0.3-MVP.md`](Module_S_PRD_v0.3-MVP.md) (§8.6 the lesser-of read; `VoucherIssued`/`VoucherShipped`; `BottleShippedAsNonSerialized`) · [`Module_K_PRD_v0.3-MVP.md`](Module_K_PRD_v0.3-MVP.md) (the Producer customer-facing description) · [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) (PR / Composite SKU / breakability Layer 1 / Bottle Page content).
- **Testable companion** — [`../03-acceptance/Module_B_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_B_Acceptance_v0.3-MVP.md).
- **MVP decisions register** — [`../04-decisions/MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md) (the thin index → authoritative docs; R4/N1/N3 + D12/D16 + B-Q rows).
- **Method + dials** — [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (P1/P2, the floor) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (D12 / D16 / D17 / D15 / L-PP).
- **Next in the cascade** — **Module C** (its kickoff is already written — `Module_C_Kickoff_Prompt.md`) reads B's just-drafted output as a settled-sibling upstream: the named B↔C contracts (§0.6) — StockPosition, serialized-bottle identity, Stream B1 / the shared Logilize discrepancy queue, the Bottle Page link + inventory summary / the Cellar data-source switch, and the NFT-burn chain.

---

*End of Module B PRD v0.3-MVP — Phase D re-baseline. **Verdict: KEPT WHOLE on the inventory-integrity floor (the two-layer no-overselling guard Layer 2 + the B→A push + InboundBatch + StockPosition + committed-inventory protection + cost-basis + the four-way reconciliation discipline), with the two heaviest critical-path levers of the whole exercise landing in-module — D12 (DECOUPLE the NFT/on-chain layer off the launch critical path; KEEP the per-bottle serialization workflow launch-ready with `nft_reference` nullable + back-filled; the re-scoped EXT-1 gate + the NS universal fallback are the seam; DECOUPLE ≠ DEFER) + D16 (SIMPLIFY the Stage-8 workflow automation → manual-first, KEEPING the integrity core; identical with Module D — N1; thinner than its billing because the workflows are already operator-driven).** Module B is the **consumer side of RECONCILE R4** (`SupplierPaymentCompleted` is **E-emitted / B-consumed** for the `ownership_flag` PRODUCER→CRURATED flip — the E-emits trap; Module B does NOT emit it; §2.2's sell-through prose reconciled to "on `SupplierPaymentCompleted`") and lands **N3** (the CRURATED inventory-ownership ledger distinct from Module D's NEWCO PO-level title ledger — same party, two signals). D17 → basic cellar contribution; D15 → minimal/manual recall; Module S's four ratified contracts honoured whole; L-PP trivial (zero self-serve writes; no backend cut); the naming cascade applied (B keeps its physical-unit names). **The B↔C contracts are named for the Module C session (§0.6); the two Paolo-track action items (schedule EXT-1 now; confirm the DEC-124 tag-content back-fill design) carry, time-sensitive.** The no-oversell / committed-inventory / InboundBatch / four-way-reconciliation integrity floor stays non-negotiably whole. **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E.*
