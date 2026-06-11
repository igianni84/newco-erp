# NewCo ERP — Phase C Cross-Module Reconciliation (the Coherence Gate) v0.1

- **Version**: v0.1 (**RATIFIED by Paolo 2026-06-07**). The capstone of the triage phase, alongside the 8 ratified cut-sheets.
- **Date**: 2026-06-07
- **Status**: **RATIFIED by Paolo 2026-06-07** — Phase C cross-module reconciliation complete; **R1 (cross-module incoherence) discharged.** Two refinements landed at ratification (see Ratification log below): RECONCILE-4 flipped to **E-emits `SupplierPaymentCompleted`** (Q2); the consolidated Admin-Panel surface (item L) resolved to a **thin 9th MVP PRD** (full version → roadmap, Q1). **The triage now moves to Phase D (re-baseline); its kickoff is written in the next session.**
- **Owner**: Paolo (decides). Claude recommends.
- **Inputs** (all govern where this summary is terse):
  - Method: [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (the 5 phases; R1 cross-module incoherence → this gate) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (locked scope decisions; P1/P2) · [`../00-method/Dials_Grounding_v1.1_reference.md`](../00-method/Dials_Grounding_v1.1_reference.md) (v1.1 anchors).
  - The 8 RATIFIED cut-sheets (the inputs reconciled): [`Module_0_CutSheet_v0.1.md`](Module_0_CutSheet_v0.1.md) · [`Module_K_CutSheet_v0.1.md`](Module_K_CutSheet_v0.1.md) · [`Module_A_CutSheet_v0.1.md`](Module_A_CutSheet_v0.1.md) · [`Module_D_CutSheet_v0.1.md`](Module_D_CutSheet_v0.1.md) · [`Module_S_CutSheet_v0.1.md`](Module_S_CutSheet_v0.1.md) · [`Module_B_CutSheet_v0.1.md`](Module_B_CutSheet_v0.1.md) · [`Module_C_CutSheet_v0.1.md`](Module_C_CutSheet_v0.1.md) · [`Module_E_CutSheet_v0.1.md`](Module_E_CutSheet_v0.1.md). Each §4 (Cross-module ripple) is the Phase-C agenda; this doc consolidates and discharges them.
  - Kickoff: [`../00-method/Phase_C_Kickoff_Prompt.md`](../00-method/Phase_C_Kickoff_Prompt.md) (§5 register A–N).
- **Method**: product-spec layer per DEC-073 (no tech-implementation decisions); no accounting positions per DEC-072 (Module E records; Xero decides GL); self-contained per DEC-074 (anchors restated inline). **Take no new scope cuts** — reconcile the ratified cuts; flag genuine gaps as OPEN for Paolo.

---

## Ratification log (Paolo, 2026-06-07)

Phase C ratified. The coherence finding stands: **the 8 trimmed module scopes compose into one coherent system; R1 is discharged.** Decisions:

- **Q1 — Admin-Panel surface (the one OPEN) → RESOLVED.** Add a **thin 9th MVP PRD** for the Admin Panel in Phase D — operator-surface / workflow scope at the product-spec layer (*not* a UX spec per DEC-073; *not* a duplication of the 8 module backends). It consolidates the operator capabilities the manual-first MVP made load-bearing (referencing the module PRDs) + specs the net-new cross-module operator consoles (shared Logilize discrepancy queue B+C; finance-ops console — settlement/dunning/reconciliation/FX-variance/Xero exceptions, E; white-glove quote flow C; returns/recall consoles), and carries a short **"full target surface"** seam section (the north-star). **The *full* Admin-Panel PRD is a roadmap deliverable** — it had no frozen v1.1 predecessor (v1.1 treated the Admin Panel as an implicit DEC-083 parity mirror), so the full surface accretes in the roadmap as the deferred automation + producer-write-UIs restore. The Phase-D Architecture records the Admin Panel as a first-class cross-cutting surface.
- **Q2 — `SupplierPaymentCompleted` emitter → FLIPPED to E-emits.** Paolo's question exposed that Module D has no independent trigger (it would wait on E's payment confirmation). Payment execution is E's (the Airwallex/Xero rails, DEC-014/028; the three-actor split DEC-119 assigns PAYMENT to E; symmetric with the customer-side `AirwallexChargeExecuted`, which E emits). **Corrected contract: Module E emits `SupplierPaymentCompleted` on payment clearing; Module D consumes it (settle/close the PO) + Module B consumes it (ownership flip PRODUCER→CRURATED).** This *corrects* the cut-sheets' "D-emits" resolution (D.24/E.32 — the dominant textual reading); the cut-sheets stay as the Phase B record, the correction lands in this doc + the Phase-D D/E/B PRDs. Naming/contract only (money moves identically; B's flip + the D19 seam intact). See §2-C + §5-R4.
- **Q3 — D12 owned action items → handled in the dev phase** (Paolo's call). Flagged once: the EXT-1 *scheduling* + the NFC tag-stock *procurement lead-time* are the time-sensitive bits (long lead times) — worth calendaring now even though the design work is dev-phase.
- **Q4 — Direct Purchase → deferred at launch, CONFIRMED (no launch deal).** Firms item I from "deal-dependent defer" to "deferred; confirmed no launch-pipeline deal needs it." The five-module idle (A/D/B/E/S) + the additive re-enable seam stand.
- **Q5 — no-overselling build-sequencing → carried to the dev-team sizing exercise** (a sequencing confirmation, not a cut).
- **Q6 — the gate → ratified.** R1 discharged; no new scope cuts taken.

---

## §0 What Phase C is (the coherence gate)

Phase C is the **verification + contract-reconciliation pass** that discharges the central risk of the strip-down — **R1: stripping one module silently breaks another.** It is *not* a rewrite. It confirms the 8 trimmed module scopes compose into **one coherent system** before any PRD is rewritten in Phase D.

The litmus applied to every register item (A–N):
- **No orphaned KEEP** — does any KEPT capability rely on a deferred upstream? (If yes: the upstream wasn't really deferred, or the KEEP must be re-scoped.)
- **No orphaned DEFER** — does any DEFER/SIMPLIFY strand a downstream consumer that still needs the deferred thing at launch? (If yes: the seam is insufficient — re-scope.)
- **The trimmed event contract is internally consistent** — cross-module event names match (emitter ↔ consumer); the four stale-framing fixes land identically on both sides; the naming cascade is uniform.
- **The floor is whole end-to-end across the *composed* system** (not just per-module): no-overselling, KYC/sanctions/OFAC/Hold, tax-correct invoicing, dual-record FX, committed-inventory protection, audit/retention.
- **The deferred set composes** into a coherent roadmap (no deferred item silently depended-on by a KEPT item; tri-module deferrals restore together).

Each register item is tagged:
- **CONSISTENT** — verified; both/all sides of the contract agree; no change needed.
- **RECONCILE** — a contract-consistency fix (naming/framing) to land in the Phase-D PRDs; **no behaviour change**.
- **OPEN** — a genuine scope/process gap needing a Paolo decision.

---

## §1 Verdict & headline coherence assertion

**The 8 trimmed module scopes compose into one coherent system. R1 is discharged.** The supply-side quartet (0/K/A/D) stayed near-whole — each *forwarded* or *pre-factored* its headline lever — so almost every upstream a downstream reads is whole; the cut-heavy modules (S/B/C/E) each removed the heavy-but-non-floor dimension while keeping their floor whole and naming the seam in lockstep with the module on the other side. **No orphaned KEEP** (every KEPT capability's upstream is KEPT or its seam is real); **no orphaned DEFER** (every deferred item idles consistently with its downstream consumers, or its seam is read additively post-launch). The end-to-end floor is **whole across the composed system** (§6). The expected finding holds: Phase C **largely confirms CONSISTENT**, with **four contract-consistency RECONCILEs** (the concrete Phase-D PRD edits) and **one OPEN** (the consolidated Admin-Panel surface — a scoping-process call, not a coherence break).

### §1.1 Register summary (A–N)

| # | Register item | Tag | One-line resolution |
|---|---|---|---|
| **A** | Naming cascade (BR→PR; Wine*→Product*) | **CONSISTENT** | Mechanical, zero behaviour change; pin Module 0 v0.3-MVP as source-of-truth, cascade order 0→A/D→S→B/C→E + Architecture; B/C/E keep own category-neutral / physical-unit names. |
| **B** | The four stale-framing RECONCILEs | **RECONCILE ×4** | D/DEC-183 · S/DEC-119 · C/5→4-stream · E/`SupplierPaymentCompleted`-emission — each already consistent between the two sides; the fix is a residual-stale-prose edit in the owning module's Phase-D PRD. |
| **C** | `SupplierPaymentCompleted` → `OwnershipTransitioned` cascade (E↔D↔B) | **RECONCILE** | **Flipped at ratification (Q2):** **E emits** (on payment clearing; E owns payment per the three-actor split); **D + B consume independently** (D = settle/close PO; B = `ownership_flag` PRODUCER→CRURATED); Direct-Purchase no-op (deferred). = RECONCILE-4. |
| **D** | Club-Credit three-way seam (entity K ↔ events E ↔ redemption S) | **CONSISTENT** | K.17 carry-forward KEPT; K.18/K.19 deferred paths simply don't fire; E records what fires; no orphan. |
| **E** | OC 5% Discovery-share — deferred-with-settlement | **CONSISTENT** | Accrual *capture* whole at launch (K locks → S emits at INV1 → E records); only the 5% computation/settlement defers (reads the recorded seam). Seam-critical capture confirmed whole. |
| **F** | Sale-vs-shipment title-timing nuance | **CONSISTENT** | Events named (`VoucherIssued` = sell-through; `VoucherShipped` = shipment leg); **no accounting position** (DEC-072); folded into deferred settlement. |
| **G** | Two-layer no-overselling guard build-sequencing | **CONSISTENT** | Floor composes whole at the *integrated* launch (A L1 ∧ B L2 ∧ S lesser-of ∧ C no-oversell-at-pick, per sub-pool); flag build-sequencing for the dev-team sizing exercise (not a cut). |
| **H** | D16 receiving-workflow depth (D↔B lockstep) | **CONSISTENT** | B decided the depth (manual-first the automated round-trips; integrity core FLOOR-kept) in lockstep with D's KEEP-pending-B-review; seam kept; land the manual-first depth identically on both PRDs. |
| **I** | Direct Purchase joint defer (A/D/B/E/S) | **CONSISTENT** | All five idle the `direct_purchase` path in lockstep; seam = uniform flow + retained enum/discriminators; re-enable additive. **Confirmed at ratification (Q4): no launch deal — deferred.** |
| **J** | NFT/on-chain DECOUPLE (B's D12) | **CONSISTENT** | NS path is the universal fallback; every downstream degrades gracefully; no Module E NFT touchpoint. Carry B's two owned action items (EXT-1 + DEC-124 tag-content) — time-sensitive, Paolo-track. |
| **K** | In-transit-pre-receipt UX scope (C/S) | **CONSISTENT** | Redemption-block FLOOR-kept + basic in-transit display (admin-estimate ETA); carrier-ETA-precision is the D17 defer; V1-per-order window survives Direct-Purchase deferral. |
| **L** | Consolidated Admin-Panel + Producer-Portal surface | **OPEN → RESOLVED (Q1)** | Surface *contract* is consistent (one producer write platform-wide = K membership approve/decline; all else operator-driven; storefront exempt). Manual-first ops made the Admin Panel load-bearing → **ratified: a thin 9th MVP Admin-Panel PRD in Phase D** (full version → roadmap). |
| **M** | The floor verified end-to-end across the composed system | **CONSISTENT** | The headline assertion: all six floor chains compose (no-oversell · KYC/sanctions/OFAC/Hold · tax-correct invoicing · dual-record FX · committed-inventory · audit/retention). §6. |
| **N** | Deferred-set roadmap coherence | **CONSISTENT** | The union of deferrals composes; the tri-module restorations (gifting S+K+C; Discovery composites S+A+0; NFT on-chain B+downstream; settlement E+D+S+A) defer + restore as coordinated sets; no KEPT item silently depends on a deferred one. |

**Counts:** CONSISTENT ×9 (A, D, E, F, G, H, I, J, K, M, N — 11 register items, of which A/D/E/F/G/H/I/J/K/M/N) · RECONCILE ×4 (item B's four fixes, one of which = item C) · OPEN ×1 (item L). *(Items B and C name the same four-fix set; C is the precise pin of RECONCILE-4. Net distinct Phase-D PRD edits: 4 RECONCILEs.)*

### §1.2 Honest calibration

The system composes coherently — and I say so plainly, because the kickoff asked for honesty over manufactured incoherence. **Why it holds:** (1) the supply-side quartet forwarded/pre-factored each headline lever, so the cut-heavy modules read whole upstreams; (2) every DEFER/SIMPLIFY named a forward-compat seam (P1), and Phase C confirms each seam is real on **both** sides of its contract; (3) the four stale-framing drifts are residual *prose* in the owning module's PRD, not contradictions between modules — each module's body + AC already carry the correct framing, and the sibling on the other side is already aligned. The only genuine open is **L** — and it is a *scoping-process* call (does the now-load-bearing Admin-Panel surface warrant its own artefact?), not a break in the trimmed contract. I did not manufacture incoherence; I did not paper over a gap.

---

## §2 Register walk — Group A–F (contract-consistency)

### A. The naming cascade (all modules + Architecture) — **CONSISTENT**

**Contract.** `Bottle Reference → Product Reference (PR)`; `Wine Master/Variant → Product Master/Variant`; the `Wine*/BottleReference* → Product*` event-name family. Module 0's generalisation (Wine → generic Product spine; `Product Type` with sole launch value `WINE`) is the source of these names.

**Both/all sides verified.** Module 0 owns the source-of-truth names (0.1/0.13/0.14 + §3 generalisation, ratified KEEP-in-full + GENERALISE). Every sibling carries a naming-cascade thread that renames only the **BR-referencing / Module-0-event-consuming** prose, with **payload semantics identical**: K §3.3 (Producer→Product Master link), A §3.4, D §3.5, S §3.6, B §3.6, C §3.7, E §3.7. Module E's touch is the lightest — its own `Invoice*/Payment*/Settlement*/NonRevenueCost*/OCShare*/Chargeback*/Refund*/Xero*/FXVariance*/ClubCredit*/StoreCredit*` names are category-neutral and unchanged. Modules B and C retain their **physical-unit** names (`SerializedBottle`, `InboundBatch`, `Case`, `StockPosition`, "Bottle Page"; `Shipping Order`, `BottlePicked`, `ShipmentDispatched`, `BottleDelivered`) as **wine-display naming** — the physical unit is a bottle for the `WINE` product type, per Module 0 guardrail 8 ("wine-facing UI labels stay 'wine/bottle/vintage'"). "Bottle Reference" is retained everywhere as a wine-display alias.

**Resolution.** **CONSISTENT** — mechanical, zero behaviour change (every event carries the same business signal; BR and PR denote the same key). The one thing to pin for Phase D: the **source-of-truth ordering** — land Module 0 v0.3-MVP names first, then cascade into each MVP PRD in dependency order (A/D → S → B/C → E) and into the Architecture. This is exactly why the coherent re-baseline (no piecemeal handoff) matters — the contract stays internally consistent because it is applied once, in order.

### B. The four stale-framing RECONCILEs — **RECONCILE ×4**

These are the most concrete Phase-C deliverable: four residual-stale-prose drifts inside a single owning module's PRD, each of which the cut-sheets already verified is **consistent between the two sides** (the module's own body + AC and the sibling on the other side already carry the correct framing). The fix is a Phase-D PRD edit; **no behaviour change**. The precise contract each must land on is in §5.

1. **D / DEC-183** — the stale pre-DEC-183 prose calling `SupplierPaymentCompleted` Module A's Direct-Purchase DRAFT→ACTIVE activation trigger (Module D PRD §1, §3 FSM-trigger bullet, §12.3, §12.4). **Correct framing:** activation is operator-publish-post-PO-commit *uniformly* (DEC-183); `SupplierPaymentCompleted` is **financial-event-only**, no FSM role. **Both sides aligned:** Module A is already correct (A.13); Module D's §12.1/§12.2 + §3 PI-trigger bullet already carry it — only the Direct-Purchase passages are stale. Ratified D Q2.
2. **S / DEC-119** — `BR-S-CrossModule-4` (Module S PRD §18.16) still carries the stale DEC-118 "bidirectional Module S ↔ Module E at INV2" framing. **Correct framing:** DEC-119 made storage **Module-S-internal** (single Module D → Module S read of `InboundEventPhysicallyAccepted`; **no** bidirectional S↔E at INV2). **Both sides aligned:** the §14 body + the Module S AC already carry the correct framing; Module E confirms its side (E.9 consumes INV3 + charges; no bidirectional — E §4 flag 1). Ratified S Q7.
3. **C / 5-stream → 4-stream** — Module C PRD §15.2 (consumed events) + §15.4 (cross-module summary) still carry the stale pre-DEC-188 "Logilize 5-stream / Streams 2–5 incl. storage-location" framing. **Correct framing:** DEC-188 4-fulfilment-stream contract (C consumes Streams 2–4 pick-confirm/dispatch/delivery + the customs-documentation-completed event; **storage-location migrated to Module B as Stream B1**). **Both sides aligned:** the §4.2 body is already correct, and the Module C AC is *ahead* of the PRD (already 4-stream); Module B owns Stream B1 (B.31). Ratified C Q8.
4. **E / `SupplierPaymentCompleted` emission** — Module E PRD is internally inconsistent: §8.2 + §9.2 + AC-E-J-37 say "Module D emits," while §9.6 + §5.9 say "Module E emits to Module B." **Correct framing (FLIPPED at ratification, Paolo Q2): E-emits** — `SupplierPaymentCompleted` is a *payment-execution* event, and payment is E's (three-actor split DEC-119; symmetric with the customer-side `AirwallexChargeExecuted`); D has no independent trigger. So Module E emits it on payment clearing; Module D + Module B consume independently. This *corrects* the cut-sheets' "D-emits" resolution (D.24/E.32). **This is item C** (the `OwnershipTransitioned` cascade) — see §2-C for the precise pin. Ratified E Q6 + the Q2 flip.

**Resolution.** **RECONCILE ×4** — name each precisely (§5); land each in the owning module's Phase-D PRD; verify each lands identically on both sides. Each is naming/contract only.

### C. The `SupplierPaymentCompleted` → `OwnershipTransitioned` cascade (E ↔ D ↔ B) — **RECONCILE** (= RECONCILE-4)

**Contract (the one genuinely cross-module emission contract).** This is the precise pin of RECONCILE-4 above — the single emission/consumption contract that spans three modules.

**FLIPPED at ratification (Paolo Q2).** The cut-sheets resolved this as "D emits; E + B consume" (the dominant textual reading — DEC-091/D.24/E.32). Paolo's Q2 exposed that **Module D has no independent trigger** — it would have to wait on E's confirmation that the payment cleared. Payment execution is **E's** (the Airwallex/Xero rails, DEC-014/028; the three-actor split DEC-119 assigns **PAYMENT** to E; and it is symmetric with the customer-side `AirwallexChargeExecuted`, which E emits). So the corrected contract is **E-emits.**

**The corrected contract (the precise pin):**
- **Module E emits `SupplierPaymentCompleted`** — when the supplier payment clears/confirms (E is the payment executor). At launch: when the operator records the manual supplier payment in E's finance surface (settlement is operator-run, D19 deferred). Post-launch: E's settlement engine. Atomic per PO (partial PO settlement deferred, OQ-20).
- **Module D consumes it** — to settle/advance/close the PO. D's *own* procurement financial events (`InboundEventCostFinalized`, cost basis, discrepancy) remain **D-emitted, unchanged** — only the *payment-completion* event moves to E.
- **Module B consumes it** — drives the inventory `ownership_flag` transition **PRODUCER → CRURATED** (B.2; the bottle becomes NewCo-owned because NewCo has paid for it). Consumer unchanged; just sourced from E.
- **Direct-Purchase no-op case.** For `direct_purchase` the InboundBatch is `CRURATED` from creation (NewCo bought it outright) → **no** PRODUCER→CRURATED transition. Doubly moot at launch (Direct Purchase deferred, item I).

**Two precision notes for the Phase-D PRDs** (naming/contract only, no behaviour change — money moves identically):
- *(i) The emission direction (reversed from the cut-sheets).* Module E §8.2/§9.2/AC-E-J-37 ("D emits") **flip to "E emits"**; §9.6/§5.9 ("E emits to B") were closer to correct but must add **D** as a consumer. Module D's PRD moves `SupplierPaymentCompleted` from its *emitted* set to its *consumed* set (its other procurement financial events stay emitted). This *corrects* the ratified cut-sheets (D.24/E.32) — the cut-sheets stay as the Phase B record; the correction lands here + in the Phase-D D/E/B PRDs.
- *(ii) The trigger event for B's flag.* B's `ownership_flag` PRODUCER→CRURATED transitions **on `SupplierPaymentCompleted`** (the payment moment) — distinct from the PO-level title transition (item F, keyed to the sale/shipment signal). Reconcile B.2's loose "V1/V2 reach CRURATED at sell-through" prose in the Phase-D Module B PRD to "on `SupplierPaymentCompleted`," so the inventory-ownership ledger (B) and the PO-title ledger (D) are not conflated. *(Two distinct ledgers — see item F.)*

**Resolution.** **RECONCILE** — pin the **E-emits / D+B-consume-independently** contract + the Direct-Purchase no-op + the trigger-event precision (i)/(ii) in the Phase-D D/E/B PRDs. Naming/contract only; B's ownership flip + the D19 seam are intact (E owns the supplier-payment event directly; D's other procurement events unchanged).

### D. The Club-Credit joint reconciliation (entity K ↔ events E ↔ redemption S) — **CONSISTENT**

**Contract.** Module K owns the Club-Credit **entity** + auto-issuance on `MembershipFeePaid` + the one-active-per-Profile invariant (K.16). Module E **records** the `ClubCredit*`/`StoreCredit*` financial events (E.21). Module S owns **redemption** + auto-apply (DEC-111) + closure-conversion (DEC-043) (S.17). The ratified cuts: **K.17 partial-redemption/carry-forward KEPT** (S Q2, against the K-draft's tentative defer — load-bearing customer value); **K.18 welcome-window proportional scaling DEFERRED**; **K.19 operator manual Club-Credit issuance DEFERRED** (launch goodwill routes through the single REFUND_COMPENSATION coupon, S.16); **DEC-043 closure-conversion KEEP-lean.**

**Both/all sides verified.** K retains the entity + auto-issuance + one-active (whole), and retains the K.18 issuance hook + formula and the K.19 manual-create path **as seams** (ratified K Q2 = "KEPT in K, decided in S"). S keeps redemption + auto-apply + carry-forward (`remaining_balance` carries across purchases — the annual-credit pattern) and routes launch goodwill through the REFUND_COMPENSATION coupon (ratified S Q2). E records the events that fire; at launch the K.18 path (full-fee→full-credit, so no scaling fires) and the K.19 path (goodwill via coupon, so no manual-create fires) **simply don't fire** — E records nothing for them; no Module-E cut (E.21).

**No-orphan check.** No KEPT item depends on a deferred one: carry-forward (K.17 KEPT) depends only on the entity + redemption (both KEPT); closure-conversion (DEC-043 KEEP-lean) depends only on the entity + the Discovery store-credit conversion (both KEPT). K.18/K.19 are deferred with retained seams and have no KEPT downstream.

**Resolution.** **CONSISTENT** — the entity (K) ↔ events (E) ↔ redemption (S) three-way seam holds; deferred paths idle cleanly. *(Phase-D acceptance note: K.17 criteria stand and are now exercised at launch; the K.18/K.19 criteria move to roadmap with their seam.)*

### E. The OC 5% Discovery-share — deferred-with-settlement (D19) — **CONSISTENT** (seam-critical, confirmed whole)

**Contract.** The Originating-Club 5%×`P_d` Discovery revenue share. The **capture** must be whole at launch because it is one-shot and unreconstructable; the **computation/settlement** defers with the settlement engine (D19).

**Both/all sides verified — the capture chain is whole at launch:**
- **Module K** captures `OriginatingClubLocked` at first approval — one-shot, immutable, unreconstructable later; ratified KEEP-capture (K.13). The 5% *computation* is deferred to S/E.
- **Module A** preserves the per-constituent lineage the share reads: `commercial_terms` per-constituent `C_i` (A.5) + `producer_id`/`supplier_id` two-FK (A.2) + sibling shared-keys (A.7). Ratified KEEP (A §4 flag 2).
- **Module S** emits `DiscoveryRevenueShareAccrued` at **INV1** — reading K's `OriginatingClubLocked` + A's lineage at that one-shot moment; ratified KEEP-emission (S.19). The 5% computation + producer settlement are deferred-but-seam-preserved. Composite OC-on-`P_d` defers with D7 (S.8); single-Allocation Discovery OC emission is KEPT.
- **Module E** **records** the accrual at launch (the seam); **computes** the 5% + composes Section D + settles **when the engine is built — reading K's lock + A's lineage, not re-deriving** (E.17 / §3.5). The Section D info-disclosure constraint (DEC-180) is preserved on the recorded accrual payload.

**The seam-critical assertion.** Every one-shot capture happens at launch: K locks the OC, S emits the accrual at INV1, E records it. The *only* deferred part is the aggregation/computation/settlement, which is fully reconstructable from the recorded accrual + K's lock + A's lineage. **If the accrual were not recorded at INV1 it could not be reconstructed — but it is recorded (S emits, E records, both ratified KEEP).** Capture confirmed whole.

**Resolution.** **CONSISTENT** — no gap. The seam-critical capture is whole at launch; only the computation/settlement defers (reads the recorded seam).

### F. The sale-vs-shipment title-timing nuance (settlement-timing-adjacent) — **CONSISTENT**

**Contract.** Whether the **PO-level** PRODUCER→NEWCO *title* transition keys to the **sale** (`VoucherIssued`) or to **physical shipment** (`VoucherShipped`). *(Distinct from item C's inventory `ownership_flag`, which keys to `SupplierPaymentCompleted`.)*

**Both/all sides verified.** Module S resolved the event names (ratified S.33/Q6): **`VoucherIssued` is the sell-through (customer-sale) signal** that drives Module D's PO PRODUCER→NEWCO ownership transition (AMB-D-3) — **there is no separate `SellThroughRecorded` event**; **`VoucherShipped` is available for a shipment-keyed title leg.** Module D's consumer is aligned (D.6). Module E names the events and anchors the per-leg FX rate-lock against the *financial* events (supplier-EUR leg at `SupplierPaymentCompleted`, customer leg at capture, OC leg at INV1; E.26/§3.6).

**The discipline (kickoff + DEC-072/073).** **Name the events** (done — DEC-073). **Take no accounting position** (DEC-072) — whether the title-transfer timing drives a revenue/COGS treatment is a Xero/GL decision. Folded into the deferred settlement recording: E records the events (`VoucherIssued`/`VoucherShipped`/`SupplierPaymentCompleted`); the precise keying is resolved when the engine is built, taking no accounting position.

**Resolution.** **CONSISTENT** — the events are named; no accounting position is taken; the nuance is folded into the deferred settlement. Confirm the Phase-D PRDs (D/E) carry the **naming**, not an accounting claim. *(Precision tie-in to item C: the PO-level title ledger [D, keyed to the sale/shipment signal] and the inventory `ownership_flag` ledger [B, keyed to `SupplierPaymentCompleted`] are two distinct ledgers — both named, no accounting position on either.)*

---

## §3 Register walk — Group G–K (floor / seam coherence)

### G. The two-layer no-overselling guard build-sequencing (FLOOR — the most delicate floor item) — **CONSISTENT** (at the integrated launch)

**Contract.** The no-overselling floor composes across **Module A Layer 1** (`qty − issued ≥ 0`, build-phase 3) ∧ **Module B Layer 2** (per-sub-pool physical-inventory ATP + the B→A push + InboundBatch + StockPosition, build-phase 5) ∧ **Module S** (shared-pool decrement + the lesser-of storefront ATP, build-phase 4) ∧ **Module C** (no-oversell-at-pick StockPosition read, build-phase 5), strongly consistent, **per sub-pool**.

**Both/all sides verified.** A.18/A.19/A.20 (Layer 1 + per-sub-pool ATP + cache, FLOOR; A ratified Q4 carried the build-sequencing + D16-workflow-depth here). S.2/S.12 (shared-pool decrement + lesser-of read, FLOOR; S §4 flag 5). B.19/B.20/B.21/B.5/B.18 (Layer 2 + B→A push + two-layer guard + InboundBatch + StockPosition, FLOOR; B ratified Q3). C.8 (no-oversell-at-pick StockPosition read, FLOOR; C ratified Q8). D.10/DEC-195 (`InboundEventPhysicallyAccepted` creates InboundBatch; D §4 flag 4). Module A's `VoucherCancelled` release (A.21) ↔ Module B's `InventoryShortfallDetected` (B.28) interlock both KEPT FLOOR.

**The build-sequencing reconciliation.** The build *phases* (A/D=3, S=4, B/C=5) are an **implementation sequence within ONE coherent launch** (no piecemeal handoff), **not** a launch-staging. So the floor is whole at the integrated launch **provided the build workplan sequences Module B's floor artefacts (Layer-2 push pipeline, InboundBatch, StockPosition, per-sub-pool ATP) to be integration-ready by the integrated launch, not as a post-launch follow-on** — because A's Layer 1, S's storefront read, and C's no-oversell-at-pick all depend on B's side being live. This is a **sequencing confirmation, not a cut.**

**The D12 interaction (important).** At launch, if the on-chain workstream is decoupled-and-slipping, **Layer 2 = the NS sub-pool ATP** — so **B's NS ledger + InboundBatch + StockPosition + the B→A push are the load-bearing floor at launch, independent of the decoupled serialized/NFT cluster** (B.14/§3.3). The floor does **not** depend on the decoupled NFT workstream — no orphan.

**Resolution.** **CONSISTENT** at the integrated launch — the floor composes whole across A/B/S/C per sub-pool. **Flag the build-sequencing for the dev-team sizing exercise** (confirm the build workplan sequences B's floor artefacts to be integration-ready at the integrated launch). This is the single most delicate floor item, and it holds.

### H. The D16 receiving-workflow depth (D ↔ B lockstep — FLOOR integrity core kept) — **CONSISTENT**

**Contract.** The Stage-8 inventory-control workflows. The **integrity core is FLOOR — KEPT**: the two-layer guard Layer 2, committed-inventory protection + `InventoryShortfallDetected`, cost-basis correctness, the four-way reconciliation *discipline*, the quarantine-before-trust *gate*. The **workflow *automation*** is the SIMPLIFY target: the automated reciprocal round-trips + reconciliation automation.

**Both/all sides verified — the depth is decided in lockstep.** Module D ratified **KEEP-pending-B-review** on its reciprocal cascades (D.17 `InboundBatchDiscrepancy` auto-reopen; D.18 `BottleQuarantineResolved` cost-basis reconciliation; ratified Q3 = "KEEP the receiving floor + interlocks; forward the workflow-depth to the joint Module B review; do not unilaterally cut the integrity interlocks; the seam is kept either way"). Module B ratified Q2 = **KEEP the integrity core (FLOOR); SIMPLIFY the Stage-8 workflow automation → manual-first** (defer the Stocktake tolerance-driven auto-reconciliation + cadence automation [B.25], the QuarantineRecord automated cross-module cascades [B.29], and the automated reciprocal round-trips with Module D [B.24/B.29]), **in lockstep with Module D's KEEP-pending-B-review.** **Module B's Q2 ratification therefore *discharges* Module D's KEEP-pending-B-review** — the depth is now decided: manual-first for the automated round-trips; integrity core FLOOR-kept.

**The seam (both sides).** Kept either way: InboundBatch, StockPosition, the ATP layers, `InventoryShortfallDetected`, the **DEC-194 split** (D=documents / B=physical-match), the **DISCREPANCY state** + Module D's **6-path resolution enum** + the event consumers, the Stocktake entity + `StocktakeReconciled`, the QuarantineRecord gate + 4 paths + immutability. The automated round-trips are **additive** when the automation lands post-launch.

**Honest calibration (carried from B §3.2).** The cut is thinner than the "single largest v1.1 increment" billing because the Stage-8 workflows are *already* single-supervisor-approval / operator-driven by spec — the entities **are** the integrity core + the seam; the genuine cut is the automated round-trips, not the entities.

**Resolution.** **CONSISTENT** — B decided the depth in lockstep with D; integrity core FLOOR-kept; the seam kept on both sides. **Phase-D PRD note:** land the manual-first-automation posture *identically* in the Phase-D Module B and Module D PRDs (D's reciprocal-cascade prose must match B's manual-first depth) so the D↔B interlocks read consistently.

### I. The Direct Purchase joint defer (A/D/B/E/S — deal-dependent) — **CONSISTENT**

**Contract.** Direct Purchase is deferred at launch (passive V1 + V2 only). Re-enable is additive if a launch deal needs it.

**Both/all sides verified — all five idle in lockstep:**
- **Module A** keeps the `direct_purchase` enum value + the uniform operator-publish FSM (DEC-183) as the **free seam** (A.3; ratified A Q2). At launch no `direct_purchase` allocations are created.
- **Module D** defers the one genuinely Direct-Purchase-exclusive surface — the **operator-initiated PI-creation path + its at-PO-creation timing** (D.4/D.8; ratified D Q1). Seam = the uniform flow (DEC-093) + the retained `trigger_source = operator_initiated` / `ownership = NEWCO` enum values.
- **Module B** — the `direct_purchase → CRURATED`-at-issuance ownership derivation is **not-exercised-at-launch** (B.2; V1/V2 reach CRURATED via `SupplierPaymentCompleted`, item C). Scope annotation, not a cut.
- **Module E** — the Direct-Purchase immediate-Xero routing + Section E informational rows **idle** (E.16; the `sourcing_model` discriminator + routing retained as seam). E §4 flag 6.
- **Module S** — the storage-clock Direct-Purchase-in-transit arm **idles** (S.29; the read is the same `InboundEventPhysicallyAccepted` event for V1/V2).

**No-orphan check.** The chain idles consistently: no `direct_purchase` allocations (A) → no operator PI path (D) → no at-issuance ownership flip (B) → no immediate-Xero/Section-E (E) → the in-transit-at-INV1 window less exercised (S). No KEPT item depends on `direct_purchase` being exercised. The seam (uniform flow + retained enum values + discriminators) makes re-enable additive.

**Resolution.** **CONSISTENT** — all five modules idle the `direct_purchase` path in lockstep; the seam is intrinsic. **Confirmed at ratification (Paolo Q4): no launch-pipeline deal needs Direct Purchase → deferred at launch.** Re-enable is additive if a future deal requires it. *(V1's per-order producer→Vinlock shipping window survives the deferral — see item K.)*

### J. The NFT/on-chain DECOUPLE (B's D12) — graceful degradation everywhere — **CONSISTENT**

**Contract.** Module B ratified **DECOUPLE the NFT/on-chain layer** off the launch critical path (DECOUPLE ≠ DEFER — the VP is preserved). **Refined at ratification (B Q1):** the per-bottle **serialization workflow** — physical NFC tagging + serial capture + the `SerializedBottle` inventory-ledger record + WMS/Logilize integration (B.10/B.11/B.23) — **stays launch-ready**; only the **NFT mint/burn + custodial wallet + on-chain recovery + Bottle-Page chain-link content** (B.12/B.13/B.34) decouple. At launch each `SerializedBottle` carries `nft_reference = NULL`, **back-filled** when the on-chain workstream lands. Two launch-ready paths: **(i) serialized-minus-NFT** (tag + serial + ledger; NFT back-filled) and **(ii) non-serialized** (the residual fallback for NFC-opt-out allocations, A.9).

**Both/all sides verified — every downstream degrades gracefully to the NS path:**
- **Module S** — `VoucherShipped → B NFT burn` (DEC-134) rides the decouple; the NS path fires `BottleShippedAsNonSerialized` (S.21/S.23/S.32; S §4 flag 7).
- **Module C** — picks NS at allocation+quantity granularity (no per-bottle serial); **dispatches regardless of the on-chain layer**; the burn rides B's decouple (C.10/§3.5; C §4 flag 7). For serialized stock C records the bound serial (launch-ready per B Q1).
- **Module E** — **no Module E NFT touchpoint** (DEC-014/122/124/131; E §4 flag 3); reads the NS informational event (`BottleShippedAsNonSerialized`, B.15) for OC settlement (E.22/E.23).
- **Bottle Page** — renders the non-NFT content (producer profile, tasting notes, allocation context, waypoint trail); the chain-link lights up when the on-chain workstream lands (B.33/B.34).

**No orphan.** The NS path is the **universal fallback**; the re-scoped EXT-1 acceptance gate already draws the boundary (gates only the NFT/on-chain criteria, not the physical-tagging/serial/ledger criteria — B §5).

**Resolution.** **CONSISTENT** — graceful degradation everywhere; the NS path is the universal fallback; no Module E NFT touchpoint. **Carry forward B's two owned action items (time-sensitive, Paolo-track — not Phase-C scope decisions, but they must not slip):** (1) **schedule the EXT-1 blockchain-expert review now** (or it becomes the launch critical path); (2) **confirm the DEC-124 tag-content back-fill design** (tags apply at launch with serial + Bottle Page URL; on-chain reference back-fillable) — the pre-launch NFC tag-stock procurement lead-time makes this time-sensitive. These are the D12 critical-path risks; flag them for the dev-team sizing exercise + Paolo's track.

### K. The in-transit-pre-receipt UX scope (C/S surface — named in C) — **CONSISTENT**

**Contract.** Forwarded from Module D (§4 flag 6) + Module S (§4 flag 7) to Module C, which named it (ratified C Q5).

**Both/all sides verified.** **KEEP the in-transit redemption-block — FLOOR** (INV-C-02/03: cannot redeem stock not physically at Vinlock; the shipment-gate enforcement, C.28/C.29). **KEEP a basic in-transit display** ("in transit; ETA X" with an admin-configurable estimate). **The carrier-ETA-precision is the D17 defer** (admin-estimate at launch, not carrier-ETA-precision integration). The window remains even with Direct Purchase deferred because **V1 passive consignment still ships producer→Vinlock per order**, so a voucher-before-physical-receipt window persists (C §3.6). It is a Module C/S surface — Module S reads C's in-flight SO + ETA for the cellar render (S.34; ratified). UX rendering already deferred (DEC-073).

**Resolution.** **CONSISTENT** — redemption-block FLOOR-kept + basic in-transit display; carrier-ETA-precision deferred (D17); the V1-per-order window survives the Direct-Purchase deferral. Named in C Q5; both forwarders (D, S) discharged.

---

## §4 Register walk — Group L–N (surface / roadmap coherence)

### L. The consolidated Admin-Panel + Producer-Portal surface (P2 — the recurring forward) — **OPEN**

**Contract.** Every cut-sheet forwarded "final reconciliation in the Admin-Panel / Producer-Portal triage" to Phase C. The reconciled surface:

**Both/all sides verified — the surface contract is consistent:**
- **Exactly ONE producer write platform-wide: Module K's membership approve/decline** (ratified K Q4). Producer-initiated invitation is operator-driven (K.31).
- **All other producer/back-office writes are operator-driven via the Admin Panel:** allocation ops (A §3.3, ratified A Q3 — zero producer writes); procurement ops (D §3.5, ratified D Q4 — zero producer writes); Club Offer publication + Hero designation + promo overlays (S §3.6, ratified S Q8 — zero producer writes); **Discovery Offers are already Admin-Panel-only** (DEC-115); inventory/quarantine/stocktake/adjustment/recall/destruction recording (B §3.6, ratified B Q6 — no self-serve writes); pick/pack/dispatch, discrepancy queue, white-glove quote, Returns/Replacement FSM, recall reverse-logistics (C §3.7, ratified C Q7 — zero producer writes); finance ops — bank-transfer reconciliation, settlement runs, dunning, chargeback handling, FX-variance, Xero exception (E §3.7, ratified E Q7 — zero producer/consumer self-serve writes).
- **Consumer-facing reads/self-serve are EXEMPT** (kickoff §3): the consumer storefront (browse/buy/cart/checkout/cellar/cancellation, S), the cellar render + in-transit display (C), the Bottle Page (B).
- **Producer Portal read + full reporting (D23) is KEPT** (reads A/S/E/K) — full 7-section self-serve reporting at launch.
- **No backend capability is cut** — DEC-083/115 admin-parity is a *backend* contract; only producer-facing write UIs are deferred (P1 seam: built post-launch on the same backend).

**The genuine open.** The cross-module surface *contract* is **consistent**. But the manual-first defers made the **Admin-Panel surface materially more load-bearing** than v1.1 assumed — it now carries: Module E's operator-run settlement runs + manual INV3 dunning + manual bank-transfer reconciliation + FX-variance + Xero exception (D19/D4); Module B's manual stocktake/quarantine/discrepancy handling (D16); Module C's manual Returns/Replacement + white-glove quotes + manual recall (D14/D3/D15); Module D's manual discrepancy/procurement ops. This is consistent with D24 (Admin Panel *more* important in a manual-first MVP) — but the consolidated surface has **not been triaged as its own artefact**; each module only forwarded it here. The kickoff (§7) notes the frontend is triaged alongside the modules it surfaces, and asks whether a separate Admin-Panel cut-sheet is warranted.

**Resolution.** **OPEN → RESOLVED at ratification (Paolo Q1).** The surface contract is coherent; the manual-first defers made the Admin Panel the load-bearing operational surface. **Ratified: add a thin 9th MVP Admin-Panel PRD in Phase D** — operator-surface / workflow scope at the product-spec layer (*not* a UX spec per DEC-073; *not* a duplication of the 8 module backends). It consolidates the operator capabilities each module exposes at launch (referencing the module PRDs) + specs the net-new cross-module operator consoles the manual-first MVP created (shared Logilize discrepancy queue B+C; finance-ops console — settlement/dunning/reconciliation/FX-variance/Xero exceptions, E; white-glove quote flow C; returns/recall consoles), and carries a short **"full target surface"** seam section (the north-star).

**Why a 9th PRD now, and why the "full version" is a roadmap item (Paolo's follow-up).** The 8 MVP PRDs are each stripped *from* a full v1.1 predecessor (greenfield v0.2). The Admin Panel had **no full predecessor** — v1.1 treated it as an implicit DEC-083 parity mirror and never specced it — so its MVP PRD is the *first* time the surface is specced, not a stripped-down version of anything. The **"full version" is therefore a forward target, not a backward predecessor:** writing a standalone full Admin-Panel PRD now would cut against the Lean re-scoping (and is largely *derivable* — the full operator surface = the 8 full PRDs' operations [DEC-083 parity] + the deferred-automation roadmap + the deferred producer-portal write-UIs; a composition, not a new source of truth), and v1.1 cannot be retrofitted (greenfield is frozen). So the **full Admin-Panel surface lives in the roadmap** (`mvp/04-roadmap/`) as the buildout target — accreting as the deferred automation (settlement engine, dunning, Stage-8, returns FSM) + the producer-write-UIs restore — exactly where every other module's deferred scope lives. Symmetric in *intent* (every module: an MVP scope + a roadmap of deferred scope); it simply has no frozen full predecessor because there never was one. The **Phase-D Architecture records the Admin Panel as a first-class cross-cutting surface** (correcting v1.1's implicit treatment).

### M. The floor verified end-to-end across the composed system (FLOOR) — **CONSISTENT** (the headline assertion)

This is the headline coherence assertion Phase C exists to make. **No cut breaches the floor *in composition*** (not just per-module). Each of the six floor chains is verified to compose end-to-end in §6.

**Resolution.** **CONSISTENT** — the floor is whole across the composed system. See §6 for the chain-by-chain verification.

### N. The deferred-set roadmap coherence — **CONSISTENT**

**Contract.** The union of all already-deferred + newly-deferred items must compose into a coherent post-launch roadmap with **no KEPT item silently depending on a deferred one**, and the **tri-module deferrals must defer + restore as coordinated sets.**

**Both/all sides verified — each tri-module restoration is coordinated:**
- **Gifting (D5) = S + K + C.** S defers the GIFTED state + flow + `VoucherGift*` events (Voucher FSM 8→7, S.27); K's gifting-init read-API idles (ratified not-exercised, K §4 flag 7); C's `is_gift` sub-flag idles (C.16). Restores together. Seam: the Voucher's mutable customer-reference (ownership-transfer capability, S.21). No KEPT item depends on gifting.
- **Discovery composites (D7) = S + A + 0.** S defers the multi-FK composite-Offer construct (S.6–S.8); A keeps the per-constituent single-Allocation + two-FK + per-constituent `C_i` (A.2/A.5, the seam); 0 keeps Composite SKU (the seam). Restores together (additive). No KEPT item depends on the composite construct — each constituent voucher is a normal per-bottle voucher; B/C/D/E see N normal vouchers, never a "composite."
- **NFT on-chain (D12) = B + downstream.** B decouples the on-chain layer; S/C/E/Bottle-Page degrade to the NS path. Restores together. Seam: `nft_reference` nullable + back-fillable + re-scoped EXT-1 gate + NS path. No KEPT item depends on the on-chain layer (NS path is the universal fallback — item J).
- **Settlement engine (D19) = E + D + S + A.** E defers the engine; D keeps recording (`SupplierPaymentCompleted` etc., D.24); S keeps emitting (`DiscoveryRevenueShareAccrued`, S.19); A keeps lineage (A.5). Restores: E's engine reads the recorded events (all upstreams keep recording). No KEPT item depends on the engine — the recording is the seam; the first close is months out (items C/E).
- **Active-consignment SELL_THROUGH_SETTLEMENT (DEC-193) = B + C + E** — already-deferred in v1.1; carried verbatim (do not re-cut). The automation defers (D4 INV3 dunning, D14 returns automation, D16 Stage-8 automation, D3 automation engines, D13 bottle-side optimisation, D17 cellar richness) each keep their event/entity/FSM seam; none has a KEPT item depending on the deferred automation.

**No-orphaned-DEFER check across the composed system.** Verified per item D/I/J/N above: no deferred item is silently depended-on by a KEPT item. The most subtle (Club-Credit K.18/K.19, item D) is clean — carry-forward (K.17 KEPT) and closure-conversion (DEC-043 KEEP-lean) depend only on the entity + redemption, both KEPT.

**Resolution.** **CONSISTENT** — the deferred set composes into a coherent roadmap; the tri-module restorations defer + restore as coordinated sets; no orphaned DEFER. Feeds the Phase-D roadmap (`mvp/04-roadmap/`).

---

## §5 The resolved contract list (the concrete Phase-D deliverable)

The four RECONCILEs are the concrete output of Phase C — the contract-consistency edits the Phase-D MVP PRDs must land. **All are naming/contract only — zero behaviour change.** Each is already consistent between the two sides; the fix removes residual stale prose in the owning module's PRD so it matches its own body + AC and the already-aligned sibling.

| # | Owning module / PRD locus | Stale framing (to remove) | Correct framing (to land) | Sibling already aligned | Ratified |
|---|---|---|---|---|---|
| **R1** | **Module D** PRD §1, §3 FSM-trigger bullet, §12.3, §12.4 | `SupplierPaymentCompleted` = Module A's Direct-Purchase DRAFT→ACTIVE activation trigger ("load-bearing Wave 2 contract") | **DEC-183:** activation is operator-publish-post-PO-commit *uniformly*; `SupplierPaymentCompleted` is **financial-event-only** (no FSM role) | Module A (A.13); Module D §12.1/§12.2 + §3 PI-trigger bullet | D Q2 |
| **R2** | **Module S** PRD §18.16 (BR-S-CrossModule-4) | "bidirectional Module S ↔ Module E at INV2" (DEC-118) | **DEC-119:** storage is **Module-S-internal** (single Module D → S read of `InboundEventPhysicallyAccepted`; no bidirectional S↔E) | Module S §14 body + AC; Module E (E.9) | S Q7 |
| **R3** | **Module C** PRD §15.2 (consumed) + §15.4 (cross-module summary) | "Logilize 5-stream / Streams 2–5 incl. storage-location" (pre-DEC-188) | **DEC-188:** 4-fulfilment-stream contract (C consumes Streams 2–4 + customs-doc-completed; **storage-location = Module B's Stream B1**) | Module C §4.2 body + AC (ahead of PRD); Module B (B.31) | C Q8 |
| **R4** | **Module E** PRD §8.2/§9.2/AC-E-J-37 (and §9.6/§5.9) | "Module D emits `SupplierPaymentCompleted`" (internal inconsistency vs §9.6/§5.9) | **FLIPPED (Q2): E-emits** — Module E emits on payment clearing; Module D + Module B consume independently (corrects the cut-sheets' "D-emits") | Module D (consumes → close PO); Module B (B.2, ownership flip); three-actor split DEC-119 | E Q6 + Q2-flip |

**R4 = item C (the `OwnershipTransitioned` cascade) — the precise cross-module pin (FLIPPED to E-emits at ratification, Q2):**
- **Module E emits `SupplierPaymentCompleted`** — on payment clearing (E is the payment executor; three-actor split DEC-119; symmetric with the customer-side `AirwallexChargeExecuted`). At launch via the operator's manual settlement record in E's finance surface (D19 deferred); post-launch via E's settlement engine. Atomic per PO (partial PO settlement deferred OQ-20).
- **Module D consumes it** → settle/close the PO. D's *other* procurement financial events (`InboundEventCostFinalized`, cost basis, discrepancy) stay D-emitted.
- **Module B consumes it** → inventory `ownership_flag` PRODUCER→CRURATED (consumer unchanged; sourced from E).
- **Direct-Purchase no-op:** InboundBatch is `CRURATED` from creation for `direct_purchase` → no transition; doubly moot (Direct Purchase deferred, item I).
- **Trigger-event precision (Phase-D Module B PRD):** reconcile B.2's loose "V1/V2 reach CRURATED at sell-through" prose to "**on `SupplierPaymentCompleted`**" — the inventory `ownership_flag` ledger (B) keys to payment, distinct from the PO-level title ledger (D), which keys to the sale/shipment signal (item F).
- **Why the flip:** the cut-sheets picked "D-emits" as the dominant textual reading (DEC-091); Paolo's Q2 identified that D has no independent trigger and that payment-completion is E's by the three-actor split + customer-side symmetry. Naming/contract only — the cut-sheets stay as the Phase B record; the Phase-D PRDs land E-emits.

**Minor Phase-D editorial consistency notes** (not new RECONCILEs — fold into the relevant PRD edits; named here so Phase D doesn't re-derive them):
- **N1 (item H):** land the D16 manual-first-automation posture *identically* in the Phase-D Module B + Module D PRDs (D's reciprocal-cascade prose must match B's manual-first depth; the integrity-core + DEC-194 split + DISCREPANCY state + 6-path enum + event consumers are the kept seam on both sides).
- **N2 (item D — finance triggers):** Module K's K.28 prose used "manual-first" as the example for *both* the chargeback and storage-payment Hold triggers; post-ratification the **chargeback trigger is automated** (D21 KEPT — Paolo override, "payment automation should be KEPT") while the **storage-payment trigger is manual-first** (D4 deferred). K's registry is trigger-agnostic, so this is a one-line Phase-D prose alignment in the Module K + Module E PRDs (no behaviour change).
- **N3 (party naming):** the PO-level ownership enum uses `NEWCO` (DEC-085) while the inventory `ownership_flag` uses `CRURATED` (DEC-185) — same party, pre-existing v1.1 naming; ensure the Phase-D `OwnershipTransitioned` cascade prose is unambiguous about which ledger uses which label (not a scope item; an editorial clarity note).

---

## §6 The end-to-end floor assertion (item M — the headline coherence statement)

Phase C exists to assert this: **no cut breaches the floor in composition.** Each of the six floor chains composes end-to-end across the *composed* system (not just per-module). All KEPT as FLOOR; verified on both/all sides.

1. **No-overselling.** Module A Layer 1 (`qty − issued`, A.18) ∧ Module B Layer 2 (per-sub-pool physical ATP + B→A push, B.19/B.20/B.21) ∧ Module S shared-pool decrement + lesser-of storefront read (S.2/S.12) ∧ Module C no-oversell-at-pick StockPosition read (C.8) — strongly consistent, per sub-pool. Composes (item G). ✓

2. **KYC / sanctions / OFAC / Hold.** Module K floor (KYC K.2/K.3; sanctions screening + order-completion gate K.4/K.5; unified Hold + DEC-181 uniformity K.26/K.27) → **Module S sanctions/Hold gate at order completion** (S.15 — THE consumer-side enforcement point; Module K + Module A are sanctions-blind by design) → Module C OFAC at all destinations + `compliance_hold` (C.20/C.2) → Module E sanctions/Hold re-read at charge (E.11). DEC-181 uniformity: every transaction-initiation surface re-reads sanctions + Hold at the moment of action (cart-add, order-completion, redemption-request, SO creation/planned/pickup, charge). Composes. ✓

3. **Tax-correct invoicing (MPV VAT regime).** Module S emits INV1/INV2/INV3 (S.18/S.28; INV1 no excise/VAT — MPV defers to redemption; INV2 at shipment) → Module C composes the INV2 tax (excise + destination VAT + shipping + storage roll-in, C.18) + emits `ExciseCalculated` (C.23) → Module E records the typology + executes the charge + routes to Xero (E.5/E.7/E.9). Composes. ✓

4. **Dual-record FX (the FX-correct-refund floor).** Module E records every customer-facing financial event in customer-currency + EUR; per-leg FX rate-lock; **refund at the original captured rate**; `FXVarianceRecorded` (E.26, D18). Paolo confirmed **FLOOR — not a candidate** (E Q4; narrowing currencies saves ~nothing — the machinery is fixed-cost). KEPT WHOLE. ✓

5. **Committed-inventory protection.** Module A's `VoucherCancelled` release primitive (A.21) ↔ Module B's `InventoryShortfallDetected` + committed-inventory protection (B.28); the single release primitive (`VoucherCancelled` first, DEC-099) keeps A's commitment ledger + B's physical ledger consistent. Applies identically to NS (B.14/§5.5). Composes. ✓

6. **Audit / retention.** Module K GDPR erasure + 10-yr txn-history retention (K.6/K.7) + Module E 10-yr archival + post-sync immutability + credit-note discipline (E.27/E.29) + the `actor_role` audit envelope across all operator surfaces (A.17, DEC-083). Composes. ✓

**Assertion.** The floor is whole end-to-end across the composed system. This is the coherence the strip-down had to preserve, and it does.

---

## §7 Open questions for Paolo (ratification)

> **✅ ALL RESOLVED at ratification (Paolo, 2026-06-07) — see the Ratification log near the top.** Q1 → thin 9th MVP Admin-Panel PRD (full version → roadmap); Q2 → `SupplierPaymentCompleted` flipped to **E-emits**; Q3 → dev phase (EXT-1 scheduling + tag-stock lead-time flagged time-sensitive); Q4 → Direct Purchase deferred, no launch deal; Q5 → build-sequencing to Phase E; Q6 → gate ratified, R1 discharged. The questions are retained below as the record of what was asked.

**Q1 (the one OPEN — item L) — the consolidated Admin-Panel surface.** The cross-module surface contract is consistent (one producer write platform-wide = membership approve/decline; all else operator-driven via the Admin Panel; consumer storefront/cellar/Bottle-Page exempt; Producer Portal read + full reporting KEPT; no backend cut). **But the manual-first defers (D19 settlement runs + D4 dunning + D16 stocktake/quarantine/discrepancy + D14 returns + D3 white-glove quotes + D15 recall) made the Admin-Panel surface the load-bearing operational surface of the launch — and it has not been triaged as its own artefact.** *Recommendation: scope a short **Admin-Panel surface cut-sheet** before/at the start of Phase D, consolidating the operator surfaces the manual-first defers created, so Phase D rewrites against a named surface scope.* **Decision: add an Admin-Panel surface cut-sheet to the Phase-D work, or rewrite the surface per-module inside each PRD?**

**Q2 (confirmation) — the four RECONCILEs (§5).** Confirm the four contract-consistency fixes land in the Phase-D PRDs as named (R1 D/DEC-183 · R2 S/DEC-119 · R3 C/5→4-stream · R4 E/`SupplierPaymentCompleted`-emission + the `OwnershipTransitioned` cascade pin), all naming/contract only, no behaviour change. *Recommendation: confirm — each is already consistent between the two sides; the fix removes residual stale prose.*

**Q3 (Paolo-track action items — item J, time-sensitive) — the D12 owned items.** These are **not** Phase-C scope decisions but must not slip: (1) **schedule the EXT-1 blockchain-expert review now** (or it becomes the launch critical path); (2) **confirm the DEC-124 tag-content back-fill design** (tags apply at launch with serial + Bottle Page URL; on-chain reference back-fillable) — pre-launch NFC tag-stock procurement lead-time makes this time-sensitive. *Recommendation: own both on the Paolo track; feed the feasibility into the dev-team sizing exercise.*

**Q4 (market confirmation — item I) — Direct-Purchase deal pipeline.** Is any known launch-pipeline deal Direct-Purchase? The modules are consistent either way (defer if no deal; re-enable additively if a deal needs it). *Recommendation: confirm defer unless a launch deal requires it — this is a deal-pipeline confirmation, not a coherence gap.*

**Q5 (Phase-E flag — item G) — the no-overselling build-sequencing.** The floor is whole at the *integrated* launch, but it requires the build workplan to sequence Module B's floor artefacts (Layer-2 push, InboundBatch, StockPosition, per-sub-pool ATP) to be integration-ready by the integrated launch (B is build-phase 5; A/D=3, S=4, C=5 all depend on B's side). *Recommendation: carry the build-sequencing into the dev-team sizing exercise as a sequencing confirmation (not a cut).*

**Q6 (ratify the gate) — the coherence finding.** Confirm the headline: **the 8 trimmed module scopes compose into one coherent system; R1 is discharged** (no orphaned KEEP; no orphaned DEFER; the floor whole end-to-end; the deferred set composes). Phase C took **no new scope cuts.** *Recommendation: ratify — then the triage moves to Phase D (re-baseline the stripped MVP PRDs + acceptance + roadmap + Architecture), whose kickoff is written after this ratifies.*

---

## §8 What Phase C did NOT do · immediate next

- **No new scope cuts.** Phase C reconciled the ratified cuts; the one genuine gap (item L) is flagged OPEN for Paolo, not unilaterally cut.
- **No re-opening of ratified module decisions.** Phase C verified coherence; it did not re-litigate Phase B.
- **No Phase D.** No PRDs rewritten; no naming cascade applied to PRDs; no roadmap/acceptance/Architecture written. Those are Phase D (its kickoff is written after this ratifies).
- **No tech-implementation (DEC-073); no accounting positions (DEC-072).** Contracts named, not mechanisms; Module E records, Xero decides GL.
- **Nothing promoted to `handoff/`** — that is Phase E (coherent handoff + dev-team sizing exercise).

**Immediate next:** Phase C is **ratified.** Write the **Phase D kickoff** (next session) — re-baseline: the stripped MVP PRDs v0.3-MVP + **the thin 9th Admin-Panel PRD** (Q1) + Module 0 generalisation + the naming cascade + the four RECONCILEs (R4 = **E-emits**) + MVP acceptance + the post-launch roadmap (incl. the **full Admin-Panel surface** as a buildout target) + updated Architecture (Admin Panel as a first-class cross-cutting surface) + release index.

---

*End of Phase C Cross-Module Reconciliation v0.1 — **RATIFIED by Paolo 2026-06-07.** The coherence gate's finding: the 8 trimmed module scopes compose into one coherent system — **R1 (cross-module incoherence) is discharged.** Largely CONSISTENT, with four contract-consistency RECONCILEs (R1 D/DEC-183 · R2 S/DEC-119 · R3 C/5→4-stream · **R4 E/`SupplierPaymentCompleted` — flipped to E-emits at ratification, = the `OwnershipTransitioned` cascade**) as the concrete Phase-D PRD edits, and the one OPEN (the consolidated Admin-Panel surface) resolved to a **thin 9th MVP Admin-Panel PRD** (full version → roadmap). The end-to-end floor is whole across the composed system; the OC 5% accrual capture is whole at launch; every seam is real on both sides; the deferred set composes into a coherent roadmap. No new scope cuts taken. **Phase D (re-baseline — now including the 9th Admin-Panel PRD) follows in its own session; its kickoff is written next.***
