# NewCo ERP — Module C PRD (Fulfilment / Shipping / Late Binding / Returns + Replacement / Cellar Render) — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP scope of Module C)
- **Date**: 2026-06-08
- **Status**: **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; **nothing is promoted to `handoff/` until Phase E** (the single coherent handoff). Module C is **KEPT WHOLE on its load-bearing "ship → cellar" half of the core loop** (the shipment gate, the late-binding voucher→bottle bind, the dispatch event that triggers INV2 + the burn chain, the no-oversell-at-pick StockPosition read, the OFAC/eligibility surface, the in-transit redemption-block, and the INV2 tax-correctness contribution), with the **broadest dial-footprint of the triage — four scope dials land in-module (D3 geography-hybrid · D13 late-binding pick · D14 returns/replacement · D17 cellar render) plus D15 recall.** Module C is the **owning module of RECONCILE R3** (the stale 5-stream Logilize framing → the DEC-188 4-fulfilment-stream contract; storage-location migrated to Module B's Stream B1) and consumes the just-drafted **B→C contracts** (StockPosition / serialized-bottle identity / Bottle Page link + inventory summary / the Cellar data-source switch to B-summary / the dispatch-originated NFT-burn chain riding B's D12 decouple).
- **Owner**: Paolo (decides). Claude recommends.
- **Testable companion**: [`../03-acceptance/Module_C_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_C_Acceptance_v0.3-MVP.md) — the MVP-scoped acceptance criteria (re-cut from the v0.1 DRAFT per the cut-sheet §5 delta; the MVP re-cut + the original validation land together — the AC was *ahead* of the v1.1 PRD on R3 [already 4-stream], so the re-cut verifies the PRD now matches).
- **Predecessors / inputs** (the canonical record governs where this PRD is terse):
  - [`../../reference/v1.1/01-prd/Module_C_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_C_PRD_v0.2.md) — the **frozen v1.1 predecessor** (RELEASED 2026-05-09; Stage 8 close — the highest cross-module-density module, 1,156 lines). This v0.3-MVP carries its **ship→cellar floor + OFAC + INV2 tax-correctness + late-binding bind at full fidelity**, **SIMPLIFIES** the four dials (D3/D13/D14/D17) + D15 with seams, lands the **R3 reconcile**, and applies the naming cascade; `greenfield/` is never edited (plan R4).
  - [`../01-triage/Module_C_CutSheet_v0.1.md`](../01-triage/Module_C_CutSheet_v0.1.md) — the **ratified cut-sheet** (Paolo 2026-06-07). §2 feature inventory (C.1–C.38) = the scope; §3 module-specific changes (D3 / D13 / D14 / D17 / D15 / the floor verification + the NFT-burn ride / L-PP + D5-idle + naming cascade + the 5-stream reconcile) = the rewrite instructions; §5 = the acceptance delta; §6 = the eight ratified Qs.
  - [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) — the **coherence gate** (RATIFIED 2026-06-07). **R3** (the 5→4 Logilize-stream reconcile — Module C owns it, §5-R3) + **item G** (the two-layer no-oversell guard — C's no-oversell-at-pick StockPosition read is FLOOR) + **item I** (Direct Purchase deferred) + **item J** (the NFT/on-chain DECOUPLE — C dispatches regardless; the NS path is the universal fallback) + **item K** (the in-transit-pre-receipt UX scope — named in C: redemption-block FLOOR + basic in-transit display) + §6 floor chains (no-overselling · KYC/sanctions/OFAC/Hold · tax-correct invoicing).
  - [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18 — the **source-of-truth name table** for the cascade (applied here, not re-derived). **Module C keeps its physical-unit / wine-display names** (`Shipping Order`, `BottlePicked`, `ShipmentDispatched`, `BottleDelivered`, "in-transit display"…) per the §18 carve-out; only the **PR-referencing / Module-0-event-consuming** prose renames (§0.9).
  - **The settled siblings (the cross-module contracts C shares — all drafted/ratified, all stable):**
    - [`Module_B_PRD_v0.3-MVP.md`](Module_B_PRD_v0.3-MVP.md) — the **just-drafted DIRECT UPSTREAM** (C reads B's output). The five B↔C contracts B named for this session (Module B §0.6): **`StockPosition`** (§8 — C reads at late-binding pick, no-oversell-at-pick, per sub-pool, per case-config); **serialized-bottle identity** (§4 — C late-binds the physical bottle reading B's bound serial; NS → allocation+quantity batch tuple); **Stream B1 storage-location** (§15.1 — the R3 migration target; the shared Logilize discrepancy queue B+C, §15.3, DEC-141); **the Bottle Page link + inventory summary** (§16 — the Cellar data source switches Logilize-direct → B-summary, DEC-188/DEC-154); **the NFT-burn chain** (§9.5 — originates at C's `ShipmentDispatched` → S `VoucherShipped` → B burn, decoupled D12; NS fires `BottleShippedAsNonSerialized`).
    - [`Module_S_PRD_v0.3-MVP.md`](Module_S_PRD_v0.3-MVP.md) — emits `VoucherRedemptionRequested` (§11.7 → C pick/pack/dispatch); C's dispatch → S transitions REDEMPTION_REQUESTED → SHIPPED + emits `VoucherShipped` + `InvoiceINV2Issued` (§17.6 / §10.7); C's `BottleDelivered` → S Voucher CONSUMED (`VoucherConsumed`); S surfaces "in transit; ETA X" (§17.6 — item K); the lesser-of storefront ATP composes B's Layer 2 (§8.6); post-shipment damage/loss = C returns+replacement, NOT S cancellation; cash refund = S supervisor-override only (`SupervisorOverridePostDeliveryRefund`, §12.3); the **Voucher FSM is 7 states** (GIFTED deferred — D5; C's `is_gift` sub-flag idles).
    - [`Module_D_PRD_v0.3-MVP.md`](Module_D_PRD_v0.3-MVP.md) — D **emits** `InboundEventPhysicallyAccepted` (§7) = **C's shipment gate** (DEC-081; §14.6 frames the D→C contract from D's side; sourcing-model-uniform; decoupled from the Module S sellability gate `Allocation.state = ACTIVE`); D emits `ReverseInboundEventRecorded` (§9 — C's recall-coordination trigger; full reverse-inbound mechanics deferred Phase 2+); the V1-per-order producer→Vinlock window survives the Direct-Purchase deferral (§12.1 / §14.6).
    - [`Module_A_PRD_v0.3-MVP.md`](Module_A_PRD_v0.3-MVP.md) — C late-binds within A's allocation-pool boundary (§7 sub-pool partition / §11.6 the A→C read); A's `VoucherCancelled` release primitive (§11.5.2, DEC-099) ↔ B's `InventoryShortfallDetected` interlock with C's shortfall workflow; `producer_breakability` Layer 2 (§8); `AllocationRecallTriggered` (§12.2 — C observes for recall scope); **over-issuance is an operation-level rejection — there is NO `AllocationCapacityExhausted` event** (§7.1; relevant only if C's prose references it — it does not).
    - [`Module_K_PRD_v0.3-MVP.md`](Module_K_PRD_v0.3-MVP.md) — the sanctions/OFAC read-API tuple `(sanctions_status, active-Hold-list)` + the Hold entity (`compliance` type) for `compliance_hold` (§9.3 / §4.8.1; DEC-181 uniformity; K's registry is **trigger-agnostic** and exposes the read-API — **enforcement is the downstream surface's**, so Module C is the enforcement point at its destinations); Customer/Profile/Address reads (§4.1/§4.2); the gifting-recipient read-API idles (D5).
    - [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) — Product Reference / Composite SKU / alcohol classification (excise) / breakability Layer 1; the Bottle Page catalog content the cellar render reads (DEC-024).
  - [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (method, P1/P2, the floor) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (D3 geography-hybrid; D13 FIFO pick; D14 returns manual-first; D17 cellar basic; D15 recall minimal/manual; L-PP).
- **Methodology** (carried from v1.1; unchanged):
  - **DEC-072** — no accounting-policy positions. Module C **records** the operational event (`ExciseCalculated`, `ShippingFeeQuoted`, the damage/loss/replacement events); **Module E records the financial event + Xero decides GL.** The excise amount + shipping cost are event data only.
  - **DEC-073** — product-spec layer only (entity concepts, business attributes, lifecycle states, business-meaningful enum values, domain-event names + business signals, module boundaries, invariants). Tech-implementation (the Logilize/carrier API contract + payload/retry, the pick-algorithm code, the customs-engine internals, the Cellar render surface, UX/layout) is the dev team's call and is out of scope.
  - **DEC-074** — self-contained delivery document. Every entity is reintroduced in full NewCo language; a tech reader who has not read v1.1 can take this into the dev phase. The v1.1 inheritance trace (v17 §C / Stage-8 cascade) is preserved in the frozen v0.2 §N; §N here adds the MVP re-baseline trace.
  - **MVP principles (plan §4.1):** **P1 — defer without burning bridges** (every simplified item names the seam that makes the post-launch build additive, and points to the roadmap); **P2 — admin-first, self-serve-later** (back-office writes are operator-driven via the Admin Panel; the consumer storefront/cellar is exempt). *Module C has **zero producer writes** — every fulfilment/post-sale ops surface is operator-driven via the Admin Panel + Logilize/Vinlock execution; the two customer-facing surfaces are the read-only **cellar render** + the **in-transit voucher display** (storefront-exempt). **No backend capability is cut** (§0.9).*
- **Working-hypothesis caveat (inherited):** where this PRD touches the NFT / on-chain surface (the late-binding chain carrying serial / NFT identity; the `BottleNFTBurned` observation), the working-hypothesis caveat propagates from Module B v0.3-MVP §0.1 / §9 — **the on-chain layer is DECOUPLED off the launch critical path (D12), back-filled when the on-chain workstream + the blockchain-expert review land.** Module C carries no on-chain DECs of its own; it **dispatches regardless of the on-chain layer** (§0.7).

---

## §0 MVP scope at a glance

**Verdict: Module C is KEPT WHOLE on the ship→cellar half of the core loop — and it is the broadest-dial-footprint module of the triage (FOUR dials land in-module: D3 · D13 · D14 · D17, plus D15) — yet the shape holds once more: simplify the sophistication *around* the floor, keep the floor whole.** Module C (Fulfilment) owns the load-bearing "ship → cellar" half of the scope floor — the shipment gate (reading Module D's `InboundEventPhysicallyAccepted`), the late-binding voucher→bottle bind, the dispatch event that triggers INV2 + the C→S→B burn chain, the no-oversell-at-pick StockPosition read, the OFAC/eligibility surface, the in-transit redemption-block, and the INV2 tax-correctness contribution (excise + shipping). **None of that is cut.** The honest twist (the cut-sheet's explicit finding): **three of the four cuts are thinner at the Module-C spec layer than their billing, because v1.1 already pre-factored the manual-first / hybrid posture** —

1. **D3 — SIMPLIFY geography/customs/excise → hybrid (the headline; #2 lever overall) — and the hybrid is already v1.1's design.** Paolo's locked D3 decision (automate low-friction destinations; route complex ones via a manual operator quote flow) maps **exactly** onto v1.1's two-tier eligibility model (§7; DEC-147): Tier 1 automated pre-cleared + Tier 2 white-glove Customer Care fallback. So the genuine Module-C cut is thin: **narrow the Tier-1 automated pre-cleared list to low-friction destinations (EU/UK/CH) at launch [configuration]; route complex destinations (US, high-excise, state-alcohol) via the already-built white-glove manual operator flow** (request → operator quote → customer pays → ship). The three automation engines (US-state matrix DEC-148, DDP/DAP country-by-country DEC-149, excise-rate automation DEC-150) **stay already-deferred** — carry verbatim. **OFAC + INV2 tax-correctness are FLOOR** (§0.1).

2. **D13 — SIMPLIFY late-binding pick → FIFO + manual tiebreak — and the deferred surface is Logilize-internal.** KEEP the voucher-side FIFO-by-expiry + the `manual_review` tiebreak + the 7-step event chain + the StockPosition no-oversell-at-pick read; **defer the bottle-side Logilize warehouse-efficiency optimisation** (Logilize picks *simply* — any available bottle from the correct pool — at launch). The late-binding **moment** + the voucher→bottle **bind** are FLOOR — not a candidate. Honest: thin at the Module-C layer (the optimisation is Logilize-internal; C's pick-instruction contract is unchanged) (§0.2).

3. **D14 — SIMPLIFY returns/replacement → manual-first (Admin Panel) — and the FSM is already operator-driven.** At low launch volume operators run returns/replacement end-to-end via the Admin Panel; **defer the FSM *automation*** (auto-transitions / auto-routing / auto-notification). KEEP whole: the Returns/Replacement FSM + the 4-event chain + the original-voucher-preserved discipline (INV-C-08) + the no-cash-refund rule (INV-C-07) + the C-vs-S boundary + the NonRevenueCost/OC emissions (the Module E seam) (§0.3).

4. **D17 — SIMPLIFY cellar render → basic (C is the composer) — the one clean SIMPLIFY of the four.** KEEP the six-module read contract + the anonymisation discipline; **defer the richest aggregation** — in-transit ETA *precision* (admin-estimate at launch, not carrier-ETA-precision integration) + granular storage (warehouse-level only). Honour the ratified B→C cascade: the storage-location data source switches Logilize-direct → **Module B-summary** (DEC-188) (§0.4).

5. **Module C owns RECONCILE R3 — the stale 5-stream Logilize framing → the DEC-188 4-fulfilment-stream contract (naming/contract only — zero behaviour change).** The v1.1 §4.2 body + the v1.1 AC already carry the correct 4-stream contract; only §15.2 (consumed events) + §15.4 (cross-module summary) carried the residual stale "5-stream / Streams 2–5 incl. storage-location" framing. This PRD reconciles §15.2 + §15.4 to 4 streams (C consumes Streams 2–4 + the customs-documentation-completed event; **storage-location is Module B's Stream B1**). Mirrors Module S's DEC-119 + Module D's DEC-183 stale-prose fixes (§0.6).

6. **Module C owns the ship→cellar half of the scope floor — verified whole in composition (Phase C item G/K/M).** The shipment gate (C consumes D's `InboundEventPhysicallyAccepted`, DEC-081; INV-C-02) + the in-transit redemption-block (INV-C-02/03); the late-binding voucher→bottle bind (DEC-142); the no-oversell-at-pick StockPosition read (DEC-196; composing A Layer 1 ∧ B Layer 2 ∧ S lesser-of); the dispatch event (`ShipmentDispatched` → S `VoucherShipped` + INV2 + the C→S→B burn chain); the OFAC/eligibility surface (DEC-041/113/181); and the **INV2 tax-correctness contribution** — C contributes the excise (`ExciseCalculated`, C.23) + the shipping fee (`ShippingFeeQuoted`/actual on `ShipmentDispatched`), which Module S composes into INV2 — are all named-floor. **None is cut** (§0.7).

7. **Module C is fulfilment / post-sale ops — L-PP / P2 is the cleanest of the triage after Module B: zero producer writes; the two customer-facing surfaces are the read-only cellar render + in-transit display; no backend capability is cut. The `is_gift` sub-flag idles (D5 gifting deferred). The naming cascade applies (C keeps its physical-unit names) (§0.9).**

**The launch scope at a glance (KEEP / SIMPLIFY / RECONCILE / GENERALISE / DEFER — confirm against the cut-sheet §1):**

| Disposition | Scope | Where |
|---|---|---|
| **KEEP — FLOOR** | The SO entity + 5-state FSM + 2 sub-flags (DEC-139); the **shipment gate** (D's `InboundEventPhysicallyAccepted`, DEC-081; INV-C-02) + the **in-transit redemption-block** (INV-C-02/03); the **late-binding voucher→bottle bind** + the 7-step chain (DEC-142); the **no-oversell-at-pick StockPosition read** (DEC-196); the **dispatch event** (`ShipmentDispatched` → S `VoucherShipped` + INV2 + the C→S→B burn chain, DEC-134/146/107); the **OFAC/eligibility surface** + `compliance_hold` + the DEC-181 uniformity re-read; the **INV2 tax-correctness contribution** (excise `ExciseCalculated` + shipping fee — S composes/issues INV2); the SO invariants INV-C-01..10; the damages/transit-loss events; the module boundaries. | §2, §3, §4, §6, §8, §9, §11 |
| **SIMPLIFY (D3 — geography hybrid; OFAC + INV2-tax FLOOR)** | KEEP the two-tier eligibility + white-glove manual flow (the hybrid IS v1.1's design + the seam); narrow the Tier-1 automated pre-cleared list to low-friction at launch; route complex destinations via the white-glove manual quote; the automation engines (DEC-148/149/150) already-deferred. | §0.1, §7, §6, §8 |
| **SIMPLIFY (D13 — FIFO pick; the bind FLOOR)** | KEEP voucher-side FIFO-by-expiry + `manual_review` tiebreak + the 7-step chain + the StockPosition no-oversell read; defer the bottle-side Logilize warehouse-efficiency optimisation (Logilize picks simply at launch). | §0.2, §3 |
| **SIMPLIFY (D14 — returns/replacement manual-first)** | Operators run returns/replacement end-to-end via the Admin Panel; defer the FSM *automation*; KEEP the FSM + the 4-event chain + the original-voucher-preserved discipline + the no-cash-refund rule + the C-vs-S boundary + the NonRevenueCost/OC emissions. | §0.3, §9, §10 |
| **SIMPLIFY (D17 — cellar render basic)** | KEEP the six-module read contract + anonymisation; defer in-transit ETA precision (admin-estimate) + granular storage (warehouse-level); the B-summary data-source switch. | §0.4, §13, §14 |
| **KEEP-minimal (D15 — recall reverse logistics)** | Operator-driven `ReverseShipmentDispatched`; unsold-only (INV-C-06 — ISSUED immune); full reverse-inbound mechanics already-deferred (OQ-18/DEC-155). | §0.5, §12 |
| **RECONCILE R3 (DEC-188 — naming/contract only)** | §15.2 (consumed) + §15.4 (cross-module summary) stale "5-stream" → the 4-fulfilment-stream contract (C consumes Streams 2–4 + customs-doc-completed; storage-location = Module B's Stream B1); the §4.2 body + the AC already correct. | §0.6, §4.2, §15.2, §15.4 |
| **GENERALISE (naming cascade — naming only, zero behaviour change)** | `Bottle Reference → Product Reference (PR)` (BR retained as wine-display alias) at catalog-identity reads (late-binding §3, excise classification §8, cellar render §14); `Wine Variant → Product Variant` (§3.4 Layer-1 read); consumed Module 0 events `Wine*/BottleReference* → Product*`. **C's own physical-unit names are unchanged.** | §0.9, §3, §8, §14 |
| **DEFER (carried verbatim — already-deferred; do not re-cut)** | Producer-override late-binding (DEC-137); the three automation engines (US-state matrix DEC-148, DDP/DAP country-by-country DEC-149, excise-rate automation DEC-150); full reverse-inbound mechanics (OQ-18/DEC-155); sub-warehouse granular display (DEC-153); multi-warehouse (OQ-16); drop-ship (OQ-17); cellar UX layout (DEC-154); voucher-substitution full automation (DEC-104); B2B carve-out / auto-SO (DEC-068/017/011); appointment-scheduling (pickup); customer-facing notifications. | §16 |
| **IDLE (D5 gifting deferred — retained-but-unexercised)** | The `is_gift` sub-flag + the gift-recipient-address read + INV-C-10 (retained as the seam; re-enable additive when gifting returns — rides S's preserved voucher-ownership-transfer seam). | §5.2, §0.9 |
| **DROP** | — (nothing dropped). | — |

**The cross-module contracts consumed (the dependency-order seam — C reads its upstreams' just-drafted output; §0.8):** Module B (StockPosition / serialized identity / Bottle Page link + inventory summary / Stream B1 + the shared discrepancy queue / the dispatch-originated burn chain riding D12); Module S (`VoucherRedemptionRequested` → pick; dispatch → `VoucherShipped` + INV2; `BottleDelivered` → CONSUMED; the in-transit ETA; the post-shipment damage/loss boundary; gift idles); Module D (the `InboundEventPhysicallyAccepted` shipment gate; the reverse-inbound trigger); Module A (late-bind within the pool + the shortfall interlock); Module K (the OFAC/Hold read-API; DEC-181 uniformity); Module 0 (PR identity + excise classification + Layer 1); Module E (the financial-event seam — C records, E records the financial event, Xero decides GL).

**The eight ratified scope confirmations (cut-sheet §6, Paolo 2026-06-07):**
- **Q1 — D3 SIMPLIFY (hybrid):** keep the two-tier eligibility + white-glove manual flow; narrow Tier-1 to low-friction at launch; route complex destinations via the white-glove manual flow; the three automation engines stay already-deferred; **OFAC + INV2 tax-correctness FLOOR.** The launch destination footprint is Paolo's owned launch-config call. (§0.1.)
- **Q2 — D13:** defer the bottle-side Logilize warehouse-efficiency optimisation; keep the two-surface structure + voucher-side FIFO + `manual_review` + the 7-step chain + the StockPosition no-oversell read; the bind is FLOOR. (§0.2.)
- **Q3 — D14:** returns/replacement FSM *automation* → manual-first; keep the FSM + the 4-event chain + the original-voucher-preserved discipline + the no-cash-refund rule + the C-vs-S boundary + the NonRevenueCost/OC emissions. (§0.3.)
- **Q4 — D17:** cellar render → basic; defer in-transit ETA precision + granular storage; keep the six-module read + anonymisation; honour the B-summary data-source switch. (§0.4.)
- **Q5 — the in-transit-pre-receipt UX scope (item K):** keep the redemption-block (FLOOR) + a basic in-transit display (admin-estimate ETA); defer carrier-ETA-precision (the D17 defer). The V1-per-order window survives the Direct-Purchase deferral. (§0.8.)
- **Q6 — D15 recall:** KEEP minimal/manual; unsold-only (ISSUED immune); full reverse-inbound mechanics already-deferred. (§0.5.)
- **Q7 — L-PP / P2:** zero producer writes; cellar render + in-transit display are consumer-facing reads (storefront-exempt); no backend cut; the `is_gift` sub-flag idles (D5); naming cascade applied. (§0.9.)
- **Q8 — the ship→cellar floor verified whole; the NFT-burn chain originates at C's `ShipmentDispatched` and rides Module B's ratified D12 decouple (C dispatches regardless); the 5-stream RECONCILE (R3) lands; the B↔C build-sequencing (both build-phase 5) carries to the Phase-E re-estimate.** (§0.6 / §0.7.)

> **Section-numbering note.** Module C is **cut-heavy in breadth but takes *no structural entity insertion*** (every entity exists in v1.1; the four cuts are SIMPLIFYs + a RECONCILE, not a re-model), so **§1–§16 keep their v1.1 numbering** — the acceptance doc's PRD §-anchors (§2.2 SO FSM, §2.5 INV-C invariants, §3 late-binding, §4.2 the 4 streams, §7 eligibility, §8 excise, §10 returns FSM, §11 in-transit, §14 cellar, §15 events) **remain valid against this PRD.** Only **§0** is prepended (MVP framing + the change threads §0.1–§0.9). §15 (events) + §16 (out-of-scope) are restated at MVP scope; **§N** (the audit appendix) adds **§N.2 — the MVP re-baseline trace** (the v17 inheritance / Stage-8-cascade traces live in the frozen v0.2 §N + App A). The naming-cascade application lands in §0.9 + §N.2.

### §0.1 D3 — the geography hybrid lands in-module, but the hybrid is already v1.1's design (the headline; Paolo market call; cut-sheet §3.1)

The cut-sheet nominated D3 as Module C's headline cut — "THE heaviest Module C lever (Paolo market call; #2 lever overall)." The ratified shape:

1. **The hybrid IS v1.1's two-tier eligibility model — KEEP it (it is the hybrid mechanism *and* the seam).** v1.1 §7 (DEC-147) is already a two-tier model: **Tier 1** automated pre-cleared destinations (an operator-configured eligible-destinations list) + **Tier 2** white-glove Customer Care fallback ("send shipping request" CTA → operator quote per DEC-145 → customer pays → ship). The auto/manual `quote_origin` discriminator (DEC-145), the simple-at-launch US-state (DEC-148) + DDP/DAP (DEC-149) dispositions, and the three deferred automation engines are **all already in v1.1.** This PRD keeps all of it.

2. **The genuine launch cut is thin — config + lean on the built manual flow.** (a) **Narrow the Tier-1 automated pre-cleared destination list to low-friction destinations (EU/UK/CH + whatever low-friction set Paolo confirms)** at launch — a configuration of the operator-managed eligible-destinations list, not a code cut. (b) **Route complex destinations (US, high-excise, state-alcohol) via the already-built white-glove manual operator flow.** (c) The three automation engines (US-state matrix DEC-148, DDP/DAP country-by-country DEC-149, excise-rate automation DEC-150) **stay already-deferred** — carry verbatim (§16). Paolo's hybrid is **more permissive** than the original Worksheet "defer US" recommendation: US stays *reachable* via white-glove rather than being categorically deferred. **The launch destination footprint is Paolo's owned launch-config call.**

3. **OFAC + INV2 tax-correctness are FLOOR — the residual heaviness, not a candidate.** **OFAC RETAINED** (§7.2 — applies at all destinations regardless of tier; composes with Module K's sanctions/Hold gate, DEC-041/113). **INV2 tax-correctness RETAINED** (excise + destination VAT under MPV + shipping + storage roll-in; DEC-146 — Module S composes/issues INV2, Module C contributes the excise + shipping; §6/§8). **Honest (the cut-sheet's explicit ask):** even the "simple" baseline is heavy compliance — the white-glove manual flow still computes excise + customs + DDP/DAP + a correct quote per complex shipment for INV2; that compliance/tax floor cannot be cut. **The seam (P1):** the manual flow records the same shipment/payment/excise data a future automated engine consumes; the Tier-1 list is operator-expandable post-launch. **Be careful the manual-quote flow does not drop the INV2 tax floor** — the excise computation (`ExciseCalculated`, §8) runs even in the white-glove flow.

### §0.2 D13 — late-binding pick → FIFO + manual tiebreak; the deferred surface is Logilize-internal (cut-sheet §3.2)

1. **The floor + the structure stay whole.** The late-binding **moment** + the voucher→bottle **bind** (§3.1; the core-loop ship step, BMD §5.5) is **FLOOR — not a candidate.** KEEP: the voucher-side **FIFO-by-expiry** + the earliest-issuance-timestamp tiebreak + the `manual_review` tertiary fallback (§3.2 Surface 1); the **7-step event chain** (§3.5; DEC-142); the **StockPosition no-oversell-at-pick read** (§3.4; DEC-196).

2. **The genuine cut — defer the bottle-side Logilize warehouse-efficiency optimisation.** Surface 2 (bottle-side) is **Logilize's responsibility by spec** ("Logilize selects the most efficient physical pick"). At launch, **Logilize picks *simply* — any available bottle from the correct Allocation pool — instead of route-optimised.** Module C's pick-instruction contract (allocation-pool boundary + voucher-side FIFO + effective-unbreakable) is **unchanged.** Honest: thin at the Module-C layer.

3. **The seam (P1)** is the two-surface contract structure: the Stream 1 outbound instruction keeps the "late-binding strategy" field, so the bottle-side optimisation is additive when Logilize route-optimisation lands. Producer-override late-binding (Interpretation C, §3.3; DEC-137/Q-CL-14) is **already Phase 2+ + explainer-pending** — carry verbatim (§16).

### §0.3 D14 — returns/replacement → manual-first (Admin Panel); the FSM is already operator-driven (cut-sheet §3.3)

1. **The FSM is already operator-driven by spec.** The DEC-184 Returns/Replacement FSM (`REPORTED → INVESTIGATED → APPROVED → REPLACEMENT_ISSUED → CLOSED` + `REJECTED`/`WITHDRAWN`) is a sequence of operator actions (`feedback_prd_rr_approval` governs the supervisor-override approval). So the "FSM automation" deferred is thin — the entities + events + FSM **are** the seam.

2. **The genuine cut.** At low launch volume operators run returns/replacement end-to-end via the Admin Panel **without the automated FSM orchestration** (auto-transitions / auto-routing / auto-notification). **KEEP whole:** the FSM states + the 4-event chain (`PostShipmentIssueReported → ReturnReceiptRecorded → ReplacementShipmentIssued → ReplacementShipmentDelivered`) + the **original-voucher-preserved discipline** (INV-C-08 — no new Voucher, no new INV2; structural per the DEC-102 8-state lock) + the **no-cash-refund rule** (INV-C-07 — replacements only; cash refund = S supervisor-override).

3. **The interlocks are honoured (ratified).** The **C-vs-S boundary** — post-shipment damage/loss = C returns+replacement, **not** S cancellation (Module S §12.3); cash refunds = S supervisor-override only (`SupervisorOverridePostDeliveryRefund`). The pool-exhausted path interlocks with **Module A's `VoucherCancelled` release** (§11.5.2) + **Module B's `InventoryShortfallDetected`** → substitution per DEC-104 (manual at launch, already-deferred — carry). The **DEC-167 NonRevenueCost wrapper + the DEC-182 OC-reversal-mirror emissions at `ReplacementShipmentIssued` are the Module E seam** (deferred settlement D19; D18 dual-record KEPT). Returned-unit full reverse-inbound mechanics already-deferred (OQ-18/DEC-155) — carry.

### §0.4 D17 — cellar render → basic (C is the composer); the one clean SIMPLIFY (cut-sheet §3.4)

1. **KEEP the six-module read contract (the seam) + anonymisation (floor-adjacent).** The DEC-154 six-module read (§14.1) is the seam; the **anonymisation discipline** (§14.2; DEC-024 — zero customer identifiers leak from the public Bottle Page) is compliance-adjacent floor.

2. **Defer the richest aggregation:** in-transit ETA *precision* (admin-estimate at launch, not carrier-ETA-precision integration; §11/§0.8) + granular storage (warehouse-level only; §13). The basic view = the six-module read with warehouse-level storage + admin-estimate ETA + the standard PICKED/DISPATCHED/DELIVERED physical-state annotations.

3. **Honour the ratified B→C cascade (DEC-188).** The storage-location summary read **switches from Logilize-direct to Module B-summary** (§14.1 item 4; ratified on B's side, Module B §0.6 / §15.1 / §16). For non-serialized Vouchers, Module B returns "non-serialized / no Bottle Page" (DEC-186). Sub-warehouse granular display (DEC-153) + the Cellar UX layout (§14.3, DEC-154) already-deferred — carry.

### §0.5 D15 recall + the ship→cellar floor verification + the NFT-burn ride on B's decouple (cut-sheet §3.5)

- **D15 (recall reverse logistics → minimal/manual):** already lean — operator-driven `ReverseShipmentDispatched` (§12); unsold-only (INV-C-06 — ISSUED Vouchers immune; committed-customer-holdings protection); reads D's `ReverseInboundEventRecorded` + A's `AllocationRecallTriggered`. Full reverse-inbound mechanics already-deferred (OQ-18/DEC-155, §12.4 preview) — carry. Matches Module A + Module D + Module B's recall side (all ratified, event-record-only).
- **The NFT-burn chain rides Module B's ratified D12 DECOUPLE.** Module C's `ShipmentDispatched` originates the C→S→B burn chain (DEC-134); **C dispatches regardless of the on-chain layer** — for serialized stock C records the bound serial (launch-ready per B's serialization workflow); the NFT burn rides the decoupled workstream (no-op / feature-flagged if the on-chain layer slips); for non-serialized stock (and serialized-minus-NFT at launch) Module B fires `BottleShippedAsNonSerialized` (the universal fallback). **No new decouple decision here** — it rides B's ratified decouple; C's chain responsibility ends at `ShipmentDispatched` (§3.5, §9 reframed — §0.7).
- **B↔C build-sequencing (both build-phase 5 — a confirmation, not a cut → the Phase-E re-estimate flag).** C's StockPosition no-oversell-at-pick read + the serialized-identity / NS reads + the Bottle Page link + storage-location summary all depend on Module B's side being integration-ready. Confirm the build workplan sequences B's floor artefacts (StockPosition, serialized-bottle identity, NS ledger, storage-location summary) to be integration-ready when C's pick/dispatch/cellar surfaces go live at the integrated launch (carried from B's ratified Q3 / Phase C item G).

### §0.6 R3 — the 5→4 Logilize-stream reconcile (the one contract-consistency fix; Phase C §5-R3)

This is the contract-consistency edit Module C owns. The v1.1 §4.2 body **correctly carries** the DEC-188 4-fulfilment-stream contract (Stream 5 storage-location migrated to Module B as Stream B1), and the v1.1 acceptance doc is *ahead* of the PRD (already 4-stream). But the v1.1 **§15.2 (consumed events) + §15.4 (cross-module summary)** still carried the stale pre-DEC-188 "Logilize 5-stream / Streams 2–5 incl. storage-location" framing.

**This PRD reconciles §15.2 + §15.4 to the 4-fulfilment-stream contract:** Module C consumes **Streams 2–4** (pick-confirmation / dispatch-confirmation / delivery-confirmation) + the **customs-documentation-completed** event; **storage-location is Module B's Stream B1** (Module C no longer reads storage-location directly from Logilize). **Naming/contract only — no behaviour change.** Mirrors Module S's DEC-119 (BR-S-CrossModule-4) and Module D's DEC-183 stale-prose reconciliations. The acceptance doc is verified the other way: the AC was ahead (already 4-stream) — the re-cut confirms the PRD body now matches it (acceptance §0).

**The paired B→C cascade (DEC-188):** the **Cellar storage-location data source switches from Logilize-direct to Module B-summary** (§14.1 item 4; ratified on B's side). Already reflected in the v1.1 §14.1 — confirm carry; no behaviour change for the customer-facing display (warehouse-level only at launch).

### §0.7 The ship→cellar / OFAC / INV2-tax / no-oversell-at-pick floor (verified whole) + the NFT-burn ride (Phase C item G/K/M)

The floor Module C holds, all KEPT whole, composes with the siblings:

- **No-overselling — C no-oversell-at-pick** (§3.4): C reads Module B's **StockPosition** shippable quantity at the `(PR, warehouse, case_config, allocation, ownership)` cell at the late-binding pick (DEC-196); C does **not oversell at pick.** Composes **Module A's Layer 1** (`qty − issued ≥ 0`, A §7.1) ∧ **Module B's Layer 2** (per-sub-pool physical ATP, B §10) ∧ **Module S's lesser-of storefront read** (S §8.6), strongly consistent, per sub-pool. *(Composition note — the `AllocationCapacityExhausted` drift: Module A v0.3-MVP frames Layer-1 over-issuance as an **operation-level rejection — there is NO `AllocationCapacityExhausted` event**; this PRD's no-oversell-at-pick prose composes with the StockPosition read + the rejection, not a non-existent event. Module C does not reference `AllocationCapacityExhausted`. Resolved consistently with Module S + Module B.)*
- **The committed-inventory interlock** (§10.4): the replacement-when-pool-exhausted path interlocks with **Module A's `VoucherCancelled` release primitive** (A §11.5.2, the single release primitive DEC-099) ↔ **Module B's `InventoryShortfallDetected`** (the shortfall workflow Substitute/Refund/Cancel runs at Module A).
- **KYC / sanctions / OFAC / Hold** (§2.3, §7.2): C reads Module K's read-API tuple `(sanctions_status, active-Hold-list)` (K §9.3/§4.8.1; K is trigger-agnostic and exposes the read-API — **enforcement is the downstream surface's**). **Module C is the OFAC-at-destinations enforcement surface** + `compliance_hold` (C.20/C.2). Per DEC-181 uniformity, C re-reads sanctions + Hold at **three** transaction-initiation surfaces: SO creation, SO `draft → planned`, and pickup-mode handover. Composes the floor chain: K screens → Module S enforces at order completion → **C OFAC at destinations** → Module E re-read at charge.
- **Tax-correct invoicing (the INV2 contribution)** (§6, §8): **the precise division of labour** — Module C **contributes** the **excise** (`ExciseCalculated`, §8; C reads destination + alcohol classification + the operator-configurable rate matrix → emits the excise amount) + the **shipping fee** (`ShippingFeeQuoted` at checkout; the **actual** cost on `ShipmentDispatched`); **Module S composes + issues INV2** (`InvoiceINV2Issued`, S §10.7 — it reads C's `ExciseCalculated` for the excise line + the dispatched actual cost for the shipping line, computes the destination VAT under MPV, and rolls in the mid-semester storage fee Module-S-internally); **Module E records** the financial event + Xero decides GL (DEC-072). This is the tax-correct-invoicing floor — INV1 carries no excise/VAT (MPV defers to redemption); the contractual guarantee is that INV2 uses the dispatched **actual** cost, not the checkout quote (§6.4). *(Phase C floor chain 3 and the kickoff use the shorthand "Module C composes the INV2 tax" — this PRD lands it precisely: **C contributes the excise + shipping that feed INV2; Module S composes/issues INV2.** Consistent with cut-sheet C.18 + Module S §10.7/§17.6 — see the digest flag.)*
- **The shipment gate + in-transit redemption-block** (§11): C consumes Module D's `InboundEventPhysicallyAccepted` (DEC-081; INV-C-02) — the SO cannot advance `draft → planned` until physical receipt fires; the customer **cannot redeem** in-transit stock (INV-C-02/03). FLOOR.
- **The dispatch event + the C→S→B burn chain** (§3.5 step 7): `ShipmentDispatched` → Module S `VoucherShipped` + `InvoiceINV2Issued` → Module B NFT burn (DEC-134). **The burn rides B's D12 decouple — C dispatches regardless of the on-chain layer** (for serialized stock C records the bound serial; for NS / serialized-minus-NFT, B fires `BottleShippedAsNonSerialized` / the burn is a no-op). **No new decouple decision here** — it rides B's ratified one; the NS path is the universal fallback the floor does not depend on.

### §0.8 The in-transit-pre-receipt UX scope (item K — named in C; forwarded from Module D + Module S)

Both Module D (§4 flag 6) and Module S (§4 flag 7) forwarded the in-transit-pre-receipt UX scope to this session; Module C names it (ratified C Q5; Phase C item K):

1. **The window remains even with Direct Purchase deferred.** Direct Purchase is deferred (Phase C item I — no launch deal), so the in-transit-at-INV1 window (sellability-before-receipt) is *less exercised* — but the shipment gate is **sourcing-model-uniform** (DEC-081), and **V1 passive consignment still ships producer→Vinlock per order** (Module D §12.1/§14.6), so a voucher-before-physical-receipt window persists.
2. **The redemption-block is FLOOR; the ETA precision is the D17 defer.** **KEEP** the in-transit redemption-block (§11.3; INV-C-02/03 — cannot redeem in-transit stock; the shipment-gate enforcement) **+ a basic in-transit display** ("in transit; ETA X" with an **admin-configurable estimate**); **defer the carrier-ETA-precision integration** (the D17 defer). It is a Module C/S surface — **Module S reads C's in-flight SO + ETA for the cellar render + the Voucher detail** (S §17.6, ratified). The UX rendering is already-deferred (DEC-073).

### §0.9 L-PP / P2 + D5 gift idle + the naming cascade (cut-sheet §3.7; Phase C item A)

**L-PP (P2) — Module C has *zero* producer writes** (the cleanest application after Module B). Producers do not write to a fulfilment/post-sale module; every Module C ops surface — the pick/pack/dispatch workflow, the shared Logilize discrepancy queue, the white-glove Customer Care quote flow, the Returns/Replacement FSM, the pickup-mode handover, the manual recall reverse-logistics — is **operator-driven via the Admin Panel + Logilize execution** at launch (already the spec; the personas §20 are all ops/back-office). **EXEMPT (kickoff §3):** the **cellar render + the in-transit voucher display are consumer-facing reads** (the customer's private authenticated space — storefront-exempt). **No backend capability is cut** — there is no producer/consumer write UI to defer (Module C is fulfilment ops + two consumer reads). The consolidated operator-surface inventory (the shared Logilize discrepancy queue B+C; the white-glove quote flow; the returns/recall consoles) lives in the 9th Admin-Panel PRD — it references this PRD's operations rather than re-specifying them.

**D5 gift sub-flag idles.** Gifting is deferred (Module S D5 — the Voucher FSM is 7 states, GIFTED deferred) → the Module S gift sub-flow does not fire → **Module C's `is_gift` sub-flag idles** (not-exercised-at-launch). **Seam (P1):** the `is_gift` attribute + the gift-recipient-address read + INV-C-10 are **retained-but-unexercised**; re-enable is additive (rides S's preserved voucher-ownership-transfer seam — S keeps the Voucher's mutable customer-reference). Consistent with Module S's gift defer + Module K's gifting read-API idle — no orphan.

**Naming cascade (Phase C item A — naming/contract only, no behaviour change).** Per Module 0 v0.3-MVP §18 (the source of truth), Module C renames only its **PR-referencing / Module-0-event-consuming** prose; **its own physical-fulfilment entity/event names are retained as wine-display naming** (`Shipping Order`, `BottlePicked`, `ShipmentDispatched`, `BottleDelivered`, `BottleBreakageInTransit`, … — the physical unit is a bottle for the `WINE` product type, per Module 0 guardrail 5 / §18 carve-out). Touchpoints: `Bottle Reference (BR) → Product Reference (PR)` at BR identity reads (late-binding §3, excise classification §8, cellar render §14); `Wine Variant → Product Variant` (§3.4 Layer-1 read); the consumed Module 0 events `Wine*/BottleReference* → Product*`. **"Bottle Reference" is retained as a wine-display alias; payload semantics identical.** The full application table is at §N.2.

---

## §1 Module Scope

### §1.1 In scope

Module C v0.3-MVP specifies, at the product-spec layer per DEC-073, the following load-bearing surfaces. **The ship→cellar floor (the shipment gate + redemption-block, the late-binding bind, the no-oversell-at-pick read, the dispatch event + burn-chain origin, OFAC/eligibility, and the INV2 tax-correctness contribution) is KEPT whole; the four dials (D3/D13/D14/D17) + D15 SIMPLIFY the sophistication around it.**

- **Shipping Order (SO) entity** + 5-state machine + 2 sub-state flags (§2); DEC-139. **FLOOR anchor.**
- **Late-binding selection algorithm** + the 7-step event flow (§3); DEC-137 + DEC-142. **The bind + chain FLOOR; the bottle-side optimisation deferred (D13).**
- **Logilize WMS integration scope** — the **4 fulfilment streams** + reconciliation discipline + the shared discrepancy queue (§4); DEC-188 + DEC-141. **(R3 reconcile.)**
- **Three shipping modes** (direct / pickup / event) + the `is_gift` sub-flag (§5); DEC-144. **(Gift idles — D5.)**
- **Carrier selection + shipping-fee quote** (automatic + manual operator-entry) + the cross-module shipping-fee event flow + the INV2 contribution (§6); DEC-145 + DEC-146. **FLOOR (tax).**
- **Destination eligibility two-tier model** + white-glove Customer Care fallback + US-state rules simple-at-launch + DDP/DAP non-EU simple model (§7); DEC-147 + DEC-148 + DEC-149. **(D3 hybrid; OFAC FLOOR.)**
- **Excise + customs computation event flow** (§8); DEC-150. **FLOOR (tax).**
- **Damages / breakage / transit-loss event ownership** (§9); DEC-151.
- **Returns + replacement workflow** Module-C-owned end-to-end + the DEC-184 FSM (§10); DEC-138. **(D14 manual-first; FSM + discipline KEPT.)**
- **In-transit voucher display + redemption-block** (§11); DEC-143 + DEC-081. **Redemption-block FLOOR; ETA precision basic (D17/item K).**
- **Producer recall reverse logistics** manual operator capability at launch (§12); DEC-152. **(D15 minimal/manual.)**
- **Storage-location customer-facing granularity** warehouse-level at launch (§13); DEC-153.
- **Cellar render data composition** six-module read contract (§14); DEC-154. **(D17 basic; B-summary source switch.)**
- **Module C event catalogue** at launch (§15) **(R3 reconcile in §15.2 + §15.4).**
- **Out-of-scope boundary** explicit list (§16).

### §1.2 Out of scope at launch

Module C v0.3-MVP carries the v1.1 launch out-of-scope set forward (consolidated at §16 — already-deferred items carried verbatim, do not re-cut). Highlights: the Logilize/carrier/Avalanche API contracts + payload/retry mechanics (DEC-073); on-chain NFT-burn wallet mechanics (Module B scope; D12-decoupled); the Bottle Page render surface (Module B owns the data contract; rendering downstream); cash refunds (Module S supervisor-override); **producer-override on late-binding selection** (DEC-137 Phase 2+ + explainer-pending); the **three automation engines** — auto-generated US-state rule-matrix expansion (DEC-148), DDP/DAP country-by-country expansion (DEC-149), excise rate-matrix automation (DEC-150) — all already-deferred; full reverse-inbound mechanics (OQ-18/DEC-155); multi-warehouse routing (OQ-16); drop-ship (OQ-17); sub-warehouse granular customer-facing display (DEC-153); the Cellar render UX layout (DEC-154); voucher-substitution full automation (DEC-104, manual at launch); the B2B credit-term branch + active-consignment SO carve-out + auto-SO on combined invoicing (DEC-068/017/011 — dropped at NewCo; every Customer is B2C); appointment-scheduling for customer-pickup; customer-facing notifications (downstream notification service consumes C lifecycle events).

### §1.3 Module boundary statement — what Module C does NOT do (unchanged)

Module C does NOT own: physical movement / label printing / waybill generation / carrier API calls (Logilize / Vinlock executes; Module C instructs and records); inventory state transitions outside shipment fulfilment (Module B owns SerializedBottle / StockPosition / InboundBatch digital state; Module D owns InboundEvent / cost basis); commercial decisions — Offer / pricing / purchase limits / Cart / Checkout / the Voucher state machine (Module S); invoicing / payment processing / VAT determination / cash refunds / credit notes (Module E + Airwallex per DEC-072 + DEC-014 — Module C emits triggers; Module S composes/issues INV2); Hold-entity decisions (Module K records; Module C reads); Customer identity / address book / Profile / KYC / sanctions determination (Module K owns; Module C reads the read-API tuple + enforces at its destinations); Product Master data / Product Reference / Case Configuration / layered-breakability Layer 1 (Module 0 PIM owns; Module C reads); the Allocation entity + the Voucher state machine + the sub-pool partition (Module A + Module S own; Module C reads allocation context at late binding); NFC tag application + NFT mint/burn/recovery chain execution (Module B owns; Module C provides the upstream dispatch event that feeds the chain via Module S — **decoupled, D12**); supplier-side procurement / PO / supplier payment (Module D owns; Module C consumes `InboundEventPhysicallyAccepted` + `ReverseInboundEventRecorded` only — **Module C does NOT touch `SupplierPaymentCompleted`**, which is the E-emitted / D+B-consumed cascade, Phase C R4); customer-facing notifications (downstream notification service); producer-recall full reverse-inbound mechanics (deferred Phase 2+); drop-ship; multi-warehouse routing; the Logilize integration mechanics (downstream tech per DEC-073).

---

## §2 Shipping Order (SO) Entity **(FLOOR anchor — the "ship → cellar" core-loop entity)**

### §2.1 Entity boundary

The **Shipping Order (SO)** is Module C's anchor entity — it represents a Customer's intent to receive physical bottles from their cellar and tracks the fulfilment lifecycle from redemption request through delivery. One SO corresponds to one Voucher (or a group of Vouchers in the same customer-initiated delivery action). At NewCo, every SO is **customer-initiated** (the Customer requests delivery from their cellar view; no system-initiated auto-SO at launch — the auto-SO-on-combined-invoicing path is already-deferred, §16). Module S's `VoucherRedemptionRequested` (Module S §11.7) is the upstream trigger that creates the SO in `draft` state. **This entity realises the "ship → cellar" half of the core loop — KEPT WHOLE.**

### §2.2 SO state machine — 5 states + 2 sub-state flags (DEC-139)

Per **DEC-139**, the SO state machine at NewCo launch is **5 linear states** plus two resolution paths (already simplified from v17's 7-state baseline — the B2B-credit-term, active-consignment, and auto-SO branches are dropped/already-deferred per DEC-068/017/011; do not re-cut):

```
draft → planned → picking → shipped → completed
   |         |                  |           |
   |         └── cancelled (terminal, pre-shipped; cancellation right per DEC-108)
   └── cancelled (terminal, if compliance_hold not resolved)
                               |
                               └── returned (resolution path, post-shipment per DEC-138)
                               └── lost (resolution path, transit-loss per DEC-151)
```

- **`draft`**: SO created on `VoucherRedemptionRequested`; eligibility checks running; may carry `compliance_hold` (§2.3). Intent expressed; not yet committed to fulfilment.
- **`planned`**: eligibility checks passed (no compliance hold; destination eligible; physical receipt confirmed per DEC-081/DEC-143); late-binding primed (Allocation context read from Module A); Logilize pick request queued. The Voucher is soft-locked (expiry suspended).
- **`picking`**: Logilize pick-confirmation received; `BottlePicked` emitted (the specific bottle's serial / NFT identity bound for serialized stock — **the late-binding bind, the core-loop ship step**); Voucher hard-locked. May carry `manual_review` (§2.3).
- **`shipped`**: dispatch confirmation received; `ShipmentDispatched` emitted; **Module S consumes → transitions Voucher REDEMPTION_REQUESTED → SHIPPED + emits `VoucherShipped` + `InvoiceINV2Issued`** (S §17.6/§10.7). The carrier has physical custody.
- **`completed`**: delivery confirmation received (best-effort); `BottleDelivered` emitted; **Module S transitions the Voucher to CONSUMED** (`VoucherConsumed`, S §11.7).
- **`cancelled`** (terminal, pre-shipped): SO cancelled before dispatch; Voucher returns to ISSUED; the DEC-108 14-day pre-shipment window applies; Module S handles the Voucher-state regression.
- **`returned`** (resolution path, post-shipment): the DEC-138 resolution path; **original Voucher state preserved** (no regression, INV-C-08); replacement dispatched per §10.
- **`lost`** (resolution path, transit-loss): carrier reports loss; `BottleLossInTransit` + `InsuranceClaimOpened` emitted; replacement dispatched per §10 if stock available.

### §2.3 Sub-state flags **(FLOOR — the compliance + integrity surfaces)**

**`compliance_hold`** (on `draft`) **— FLOOR.** Set when one or more eligibility checks fail at SO creation: (a) an active Module K Hold (any active Hold blocks new SO creation/advance); (b) the sanctions / OFAC check returns non-`passed` (Module K read-API tuple, DEC-113); (c) the shipment gate not yet fired (`InboundEventPhysicallyAccepted` — the in-transit case, §11); (d) destination ineligibility on the automated path without white-glove approval (DEC-147). The SO remains in `draft` with `compliance_hold = true` until **all** triggers individually clear (INV-C-03).

**Sanctions/Hold uniformity (DEC-181) — FLOOR.** The SO `draft → planned` transition is a transaction-initiation surface: the eligibility-check pass **re-reads** sanctions + Hold state at the moment of transition (a Hold landing **after** SO creation but **before** the `planned` transition blocks it). **Module C is one of the downstream enforcement surfaces** of Module K's trigger-agnostic read-API (K §4.8.1 — K exposes the `(sanctions_status, active-Hold-list)` tuple; the enforcement is Module C's at its surfaces). The three Module C transaction-initiation surfaces that re-read are: **SO creation**, **SO `draft → planned`**, and **pickup-mode handover** (§5.1). OFAC at all destinations is retained even in the D3 hybrid cut (§7.2).

**`manual_review`** (on `picking`). Set on (a) a Logilize-side pick discrepancy (§4.4 — serial / quantity / batch mismatch; breakage-at-pick) OR (b) the voucher-side tertiary tiebreak (§3.2 — two+ Vouchers sharing identical expiry + identical issuance timestamps in bulk batch issuance). The SO pauses at `picking`; the discrepancy / candidate set surfaces in the shared NewCo Admin Panel "Logilize discrepancy" / "voucher tiebreak" queue (DEC-141 — shared with Module B); `ShippingOrderManualReviewRequired` is emitted for the voucher-side case. Operator resolves; `DiscrepancyResolutionRecorded` fires for Logilize-side discrepancies; the flag clears; the SO advances. **Operator-driven via the Admin Panel (L-PP).**

**White-glove destination ineligibility — SO-side soft-block.** White-glove destination ineligibility (DEC-147) is an SO-side soft-block: Module C blocks `draft → planned` until Customer Care approves the white-glove path. **No new Voucher state** is introduced; the Voucher FSM is unchanged. The SO carries `compliance_hold = true` while waiting; on approval the flag clears (the D3 hybrid mechanism, §7).

### §2.4 SO entity attributes (business-concept layer)

Per DEC-073 the literal schema is downstream tech. At the PRD level the SO carries: **Voucher reference(s)**; **Customer + Profile reference** (read from Module K); **delivery Address** (Module K; recipient address if `is_gift` — idles, §5.2); **`dispatch_mode`** (`direct` / `pickup` / `event`, §5); **`is_gift`** sub-flag (boolean, direct-mode only — **idles at launch**, D5); **`incoterms`** (`DDP`/`DAP` for non-EU; EU under MPV per DEC-045/056); **`quote_origin`** (`auto`/`manual`, §6); **primary state** + **sub-state flags** (§2.2/§2.3); **Allocation context reference** (read from Module A at late binding — sub-pool, counterparty, commercial terms); **late-binding strategy** field (the D13 seam — carries the FIFO voucher-side strategy; the bottle-side optimisation is additive when Logilize route-optimisation lands).

### §2.5 SO business invariants **(FLOOR — the load-bearing rule set)**

The following invariants hold throughout the SO lifecycle (verbatim-restated per DEC-074):

- **INV-C-01 — Allocation-pool boundary (FLOOR — no-oversell-at-pick).** The physical bottle (or non-serialized batch qty) assigned at `BottlePicked` MUST belong to the same Allocation pool as the Voucher being redeemed. Cross-pool picking is prohibited regardless of Product Reference identity. Composes with the StockPosition read (§3.4).
- **INV-C-02 — Shipment gate must be passed before `planned` (FLOOR).** The SO MUST NOT advance `draft → planned` unless `InboundEventPhysicallyAccepted` has fired for the relevant Allocation qty (Module D §7 + DEC-081). In-transit Allocations hold the SO in `draft` with `compliance_hold = true` until physical receipt (§11).
- **INV-C-03 — `compliance_hold` fully cleared before `planned` (FLOOR).** All active `compliance_hold` triggers — Module K Hold, sanctions/OFAC check, shipment gate, destination ineligibility without white-glove approval — MUST be individually resolved before `planned`.
- **INV-C-04 — One SO per redemption event.** One `VoucherRedemptionRequested` maps to exactly one SO; multiple same-session redemptions produce separate SOs.
- **INV-C-05 — Effective-unbreakable discipline at pick.** Where `effective_unbreakable = true` (Layer 2 Module A `producer_breakability` OR Layer 3 Module S `commercial_unbreakable`; Module 0 Layer 1 does not contribute), the pick instruction MUST name a quantity that is an exact multiple of `bottles_per_case`; a partial-case pick under effective-unbreakable pauses the SO with `manual_review = true`.
- **INV-C-06 — ISSUED Vouchers immune to recall reverse logistics (floor-adjacent).** Recall reverse-pick instructions MUST draw from the unsold sub-pool only (`Allocation.qty − issued`), calculated dynamically at instruction-generation time. Bottles bound to ISSUED Vouchers are off-limits regardless of the producer's stated recall scope (§12; DEC-117).
- **INV-C-07 — No Module C cash refunds (FLOOR).** Module C issues replacement shipments only. Cash refunds are Module-S-supervisor-override scope (Module S §12.3 + DEC-108). Module C has no cash-refund event or workflow path.
- **INV-C-08 — Original Voucher state preserved on post-shipment resolution (FLOOR).** A `returned` SO resolution does NOT trigger a Voucher state regression in Module S. The original Voucher remains SHIPPED or CONSUMED; no new Voucher; no new INV2. Structural basis: the DEC-102 8-state lock.
- **INV-C-09 — Pre-shipment cancellation window only.** Customer cancellation is valid only while the SO is in `draft` or `planned` (before `ShipmentDispatched`); post-`shipped`, the DEC-108 14-day right closes and post-delivery resolution is replacement per DEC-138.
- **INV-C-10 — Gift sub-flag direct-mode only (idles — D5).** `is_gift = true` is valid only when `dispatch_mode = direct`. Retained as the validation but **unexercised at launch** (gifting deferred — §5.2/§0.9).

---

## §3 Late-Binding Selection Algorithm **(the bind + chain FLOOR; the bottle-side optimisation deferred — D13)**

### §3.1 Late-binding moment **(FLOOR — the core-loop ship step)**

Per BMD §5.5 + DEC-142, **late binding** is the assignment of a specific physical bottle to a Customer's Voucher at **pick time** (SO `planned → picking`). The Voucher represents an entitlement to a bottle of a specific Product Reference from a specific Allocation pool, **not** to a specific physical unit. **This moment + the voucher→bottle bind are the load-bearing core-loop ship step — FLOOR, not a D13 candidate.** It is structurally required by NewCo's passive-consignment sell-first-buy-second model.

### §3.2 Two-surface selection algorithm (DEC-137) **(Surface 1 KEPT; Surface 2 optimisation deferred — D13)**

**Surface 1 — Voucher-side (which Voucher among identical Vouchers in the same pool gets the bottle) — KEPT:**
- **FIFO by Voucher expiry** — among Vouchers in the same Allocation pool referencing identical Product References, the earliest-expiry (longest-held) Voucher gets the bottle first.
- **Tiebreak (identical expiry):** secondary criterion is **earliest-issuance-timestamp** (deterministic; preserves customer predictability). **Tertiary fallback** (issuance-timestamps also tie — bulk batch issuance to the millisecond): operator-manual review via the Admin Panel, captured by `ShippingOrderManualReviewRequired` (§15.1), `manual_review = true` on the SO.
- Visibility to the Customer: **none** — the FIFO selection is Module-C-internal.

**Surface 2 — Bottle-side (which physical bottle among identical bottles in the same pool gets shipped) — the D13 SIMPLIFY:**
- v1.1: Logilize warehouse-efficiency rules select the most operationally efficient physical pick.
- **At launch (D13): Logilize picks *simply* — any available bottle from the correct Allocation pool — instead of route-optimised.** The bottle-side warehouse-efficiency optimisation is **Logilize-internal by spec** and is **deferred.** **Module C's pick-instruction contract (allocation-pool boundary + voucher-side FIFO + effective-unbreakable) is unchanged.** **Seam (P1):** the Stream 1 instruction keeps the "late-binding strategy" field, so the bottle-side optimisation is additive when Logilize route-optimisation lands.

**Allocation-pool boundary invariant (INV-C-01):** the bottle selected **must** come from the same Allocation pool as the Voucher being redeemed; cross-pool picking is **prohibited** — preserving commercial-chain integrity (a Voucher against Producer X's Allocation draws from Producer X's stock).

### §3.3 Producer-override — Phase 2+ + explainer-pending (DEC-137) **(already-deferred — carry verbatim)**

The producer-override concept (Interpretation C — a producer specifying a per-allocation selection rule, e.g. FEFO or specific-vintage, instead of the FIFO baseline) is **deferred to Phase 2+** in v1.1, with Paolo's explainer queued as a separate session. **Carry verbatim** (§16); do not re-cut. This PRD locks the working baseline (the two-surface structure + voucher-side FIFO).

### §3.4 Layered breakability read + StockPosition shippable-qty read **(FLOOR — no-oversell-at-pick) + GENERALISE**

At late binding, Module C reads the **effective breakability rule** before composing the pick request:
- **Layer 1** (Module 0 PIM): the possible-case-configurations whitelist per Format. **Cataloging-level possibility — Layer 1 does NOT contribute to the effective rule.** *(Naming cascade: the Layer-1 read renames `Wine Variant.possible_case_configs → Product Variant.possible_case_configs`; naming only.)*
- **Layer 2** (Module A): the producer-set per-Allocation `producer_breakability` declaration per `case_config` (A §8; immutable post-first-voucher).
- **Layer 3** (Module S): the Offer's `commercial_unbreakable` boolean.
- **Effective rule:** `effective_unbreakable = Layer 2 (producer) OR Layer 3 (commercial)`. Either layer is sufficient; PIM's Layer 1 does not contribute. Resolved per voucher-line.

**No-oversell-at-pick — the StockPosition read (FLOOR; DEC-196).** When `effective_unbreakable = true`, Module C reads `available_quantity` from **Module B's StockPosition** at the matching `(Product Reference, warehouse, case_config, allocation, ownership)` cell (Module B §8) — the count is in whole cases, and Module C's pick instruction names a quantity that is the corresponding multiple of `bottles_per_case`; when `effective_unbreakable = false`, Module C selects at the individual-bottle level. **C does not oversell at pick** — the StockPosition read composes with Module B's Layer 2 (B records the case-integrity FSM transition on a partial-case pick of a breakable case — recorder-not-gatekeeper, Module B §7.2). This is the named B↔C contract (Module B §0.6). *(Naming cascade: the BR identity at the cell renames `Bottle Reference → Product Reference`; the physical-unit semantics are unchanged.)*

For **non-serialized stock**, the pick request names the **allocation + InboundBatch + quantity** (no per-bottle serial); the effective-unbreakable rule still applies at the quantity-multiple level (§3.6).

### §3.5 Late-binding event flow — 7-step chain (DEC-142) **(FLOOR — the core-loop ship chain + the D13 seam)**

**Sanctions/Hold uniformity at shipment-request initiation (DEC-181).** The shipment-request initiation surface that opens this chain re-reads sanctions + Hold state at the moment of action; the upstream gate fires at Module S §11.7 (`VoucherRedemptionRequested` emission), the downstream gate at Module C §2.3 (SO `draft → planned`). Both are load-bearing (defence-in-depth for a Hold landing after redemption-request emission but before the SO enters the picking workflow).

```
1. Module S emits `VoucherRedemptionRequested` (S §11.7 — Customer requests shipment from cellar view)
        ↓
2. Module C creates SO in `draft`; runs eligibility checks (§2.3) → compliance_hold if a check fails; advance to `planned` when clear
        ↓
3. Module C reads Allocation context from Module A (sub-pool partition; counterparty; commercial terms; the effective-unbreakable rule §3.4)
        ↓
4. Module C sends the outbound shipment instruction to Logilize (Stream 1 per DEC-188; SO id + PR-keyed line items + qty + allocation context + destination + dispatch_mode + the late-binding strategy field)
        ↓
5. Logilize emits the pick-confirmation event (Stream 2; serialized: the bottle's serial + NFT identity; non-serialized: allocation + InboundBatch + qty)
        ↓
6. Module C fires `BottlePicked` (records the bound serial / NFT identity for serialized — the late-binding bind; or allocation + InboundBatch + qty for non-serialized); SO advances `planned → picking`
        ↓
7. Logilize emits the dispatch-confirmation event; Module C fires `ShipmentDispatched`
   → Module S consumes → Voucher REDEMPTION_REQUESTED → SHIPPED + `VoucherShipped` + `InvoiceINV2Issued`
   → `VoucherShipped` carries the serial / NFT identity for serialized → Module B consumes for the NFT burn (DEC-134 — DECOUPLED, D12; NS / serialized-minus-NFT → `BottleShippedAsNonSerialized`)
```

**The NFT-burn ride (D12 — §0.7).** Step 7's downstream arm (Module B `BottleNFTBurned` via Module S `VoucherShipped`) **rides Module B's ratified D12 decouple — Module C dispatches regardless of the on-chain layer.** Module C's chain responsibility ends at `ShipmentDispatched`; for non-serialized stock (and serialized-minus-NFT at launch) Module B fires `BottleShippedAsNonSerialized` (the universal fallback) / the burn is a no-op. The working-hypothesis caveat propagates from Module B §0.1 / §9.

### §3.6 Non-serialized stock path at late binding **(KEPT — the universal-fallback path)**

For **non-serialized Allocations** (Module A NS sub-pool; DEC-080/186), the late-binding process reads Module B's NS ledger (Module B §5) and differs at:
- **Stream 1 (outbound instruction):** Allocation reference + InboundBatch reference + quantity. No per-bottle serial. Logilize applies the simple bottle-side pick (D13) at the batch level; effective-unbreakable applies at the quantity-multiple level (INV-C-05).
- **`BottlePicked`:** carries Allocation + InboundBatch + quantity (no serial; no NFT identity). **No Module B NFT chain** for non-serialized.
- **`ShipmentDispatched`:** carries Allocation + InboundBatch + qty. Module S consumes → Voucher SHIPPED + `VoucherShipped` + `InvoiceINV2Issued`. `VoucherShipped` carries no NFT identity → Module B fires `BottleShippedAsNonSerialized` (no `BottleNFTBurned`).
- **Cellar render:** Module B returns "non-serialized / no Bottle Page" (DEC-186); the Cellar tile displays PR-level catalog data from Module 0 without a Bottle Page deep-link. Non-serialized Vouchers cannot transition to ON_CRUTRADE (enforced by Module S).
- **Replacement + recall:** draw from the Allocation's non-serialized unsold sub-pool (`Allocation.qty − issued`; INV-C-01); if exhausted, substitution per DEC-104 (manual at launch).

**This path is doubly load-bearing — it is the universal fallback every downstream degrades to gracefully under the D12 decouple (§0.7); Module C dispatches NS regardless of the on-chain layer.**

---

## §4 Logilize WMS Integration Scope **(the records/executes split KEPT; R3 — the 4-fulfilment-stream contract)**

### §4.1 Integration principle **(Module C records, Logilize executes — recorder-not-gatekeeper)**

Logilize is the **system-of-record for physical state at the workflow-execution axis** (pick-pack-dispatch execution; sub-warehouse storage-location detail Logilize-internal; carrier-side custody handover); NewCo ERP is the **system-of-record for commercial state.** Module C is the NewCo-side counterparty for the forward fulfilment workflow (Principle 2: Module C instructs; Logilize executes; NewCo ERP records).

**Inventory-state authority lives at Module B (DEC-185 + DEC-188).** At the **inventory-state axis** (InboundBatch + StockPosition + Case + QuarantineRecord; ATP per allocation; receiving physical-match; stocktake + adjustment authority; storage-location summary), **Module B is the system of record** (Module B §2). The four-way reconciliation discipline (Logilize ↔ Module B ↔ Module S ↔ Module E) is the architectural payoff of the split. **Module C participates indirectly via the SO state** — the SO is the join surface for the customer-facing fulfilment workflow.

### §4.2 Four-stream fulfilment contract at launch (DEC-188 — supersedes DEC-140 on the inventory-state axis)

Per **DEC-188** (the 4 fulfilment streams remain Module C's; Stream 5 storage-location migrated Module C → Module B as **Stream B1**; the net-new B2–B5 inventory-state streams are added at Module B — Module B §15.1), Module C owns the following **4 Logilize fulfilment streams** at launch:

- **Stream 1 — Outbound shipment instruction.** Sent at SO `planned → picking`; carries SO id + PR-keyed line items with quantities + Allocation context (sub-pool, counterparty, effective-unbreakable rule §3.4) + destination address + `dispatch_mode` + the **late-binding strategy field** (FIFO voucher-side; the bottle-side optimisation deferred — D13). For serialized stock, Logilize selects a bottle from the same Allocation pool (a *simple* pick at launch — D13); for non-serialized stock, Module C specifies the allocation + InboundBatch + qty.
- **Stream 2 — Pick confirmation.** Logilize emits when the pick is confirmed; serialized → the bottle's serial + NFT identity (Module C fires `BottlePicked` recording the binding); non-serialized → allocation + InboundBatch + qty.
- **Stream 3 — Dispatch confirmation.** Logilize emits when the carrier physically receives the shipment; Module C transitions SO `picking → shipped` and fires `ShipmentDispatched` (carrying actual shipping cost + `incoterms` + `dispatch_mode` + `quote_origin` + the bound serial / NFT identity for serialized, or allocation + batch + qty for non-serialized). Module S consumes → `VoucherShipped` + `InvoiceINV2Issued`.
- **Stream 4 — Delivery confirmation (best-effort).** Logilize relays carrier-tracking delivery events when available; Module C transitions SO `shipped → completed` and fires `BottleDelivered` + `ShippingOrderCompleted`; Module S transitions the Voucher to CONSUMED. For pickup mode, the handover at Vinlock is the delivery moment (operator-recorded via the Admin Panel; no carrier confirmation).

**Stream 5 — Storage-location tracking — migrated to Module B (R3 / DEC-188).** The original DEC-140 fifth stream is **migrated to Module B as Stream B1** (Module B §15.1). **Module C no longer reads storage-location directly from Logilize** — Module B reads the warehouse-level summary for the Cellar render + Bottle Page (the Cellar data source switches Logilize-direct → **Module B-summary**, §14). Sub-warehouse granular location remains Logilize-internal (already-deferred, §13). *(This is the **R3 reconcile** — §15.2 + §15.4 carried the stale "5-stream / Streams 2–5 incl. storage-location" framing; reconciled here + in §15 to the 4-fulfilment-stream contract.)*

**Inventory-state streams owned by Module B (not Module C):** Stream B1 storage-location (migrated from C), B2 receiving + physical-match (DEC-194), B3 stocktake, B4 inventory-adjustment, B5 QuarantineRecord (Module B §15.1). **The boundary:** bottle-state events (dispatched, delivered) flow through Module C streams; inventory-state events (storage-location, receiving, stocktake, adjustments, quarantine) flow through Module B streams. Module B observes bottle-state events from the dispatch chain only for the NFT-burn cross-module chain (decoupled, D12).

### §4.3 Reconciliation discipline + the shared discrepancy queue (DEC-141)

**Source-of-truth split:** Logilize (physical bottle location + custody state + pick-pack-dispatch workflow execution + storage location); NewCo ERP (Allocation/Voucher/Order commercial state — Module S/A; Customer/Profile — Module K; SerializedBottle/StockPosition/InboundBatch inventory-state — Module B; Procurement/InboundEvent — Module D; the Shipping Order lifecycle — Module C; the catalog — Module 0).

**Reconciliation cadence:** real-time event-driven (no batch-reconciliation jobs at launch).

**The shared "Logilize discrepancy" queue (DEC-141 — a named B↔C contract; the manual-first operator surface).** When a Logilize event contradicts NewCo's commercial state OR NewCo's inventory ledger, the discrepancy appears in the **NewCo Admin Panel "Logilize discrepancy" queue**, **shared across Module C** (fulfilment-side discrepancies — pick discrepancies §4.4) **and Module B** (inventory-state-side discrepancies — QuarantineRecord, `InboundBatchDiscrepancy`, stocktake variance; Module B §15.3). Operator triages both kinds; resolution events are recorded in the appropriate module per the §4.2 boundary. The `manual_review` sub-state flag on the SO signals an in-progress Module-C-side discrepancy. **This is the operator triage surface manual-first ops needs (it is flagged for the 9th Admin-Panel PRD, which references this operation rather than re-specifying it).** The reconciliation algorithm is tech (DEC-073).

### §4.4 Pick discrepancy handling

When Logilize's Stream 2 pick-confirmation contradicts the Allocation pool or commercial state, a **pick discrepancy** is raised; `manual_review = true`. Four types: **serial mismatch** (serialized — violates INV-C-01); **quantity mismatch** (non-serialized — may violate INV-C-05); **batch mismatch** (non-serialized); **breakage-at-pick** (Logilize finds the bottle physically damaged at pick — triggers the Module B `BottleBreakageInCustody` chain, DEC-132; Module C surfaces the discrepancy and awaits operator confirmation of a substitute pick from the same pool per DEC-104). The discrepancy appears in the shared queue; the operator triages; on resolution `DiscrepancyResolutionRecorded` fires (resolution type: `correct_serial_confirmed` / `re_pick_ordered` / `substitution_approved` / `quantity_acknowledged` + operator identity); `manual_review` clears; the SO advances. A serial/batch mismatch may require a corrected Stream 1 re-pick (the SO holds in `picking` with `manual_review = true` until the corrected Stream 2 arrives clean). The discrepancy-queue UX + Logilize retry mechanics are tech (DEC-073).

---

## §5 Three Shipping Modes + Gift Sub-Flag (DEC-144) **(modes KEPT; gift idles — D5)**

### §5.1 Mode overview

Three shipping modes, each a `dispatch_mode` value on the SO:
- **Direct shipment (`direct`):** the default. Bottles ship from Vinlock to the Customer's stated address via carrier; full `picking → shipped → completed` with carrier ETA tracking via Stream 4. Most redemptions use this mode.
- **Customer pickup (`pickup`):** the Customer collects at Vinlock by appointment (Customer-Care-coordinated; no automated appointment-scheduling at launch — already-deferred). The physical handover at Vinlock is the **release-for-consumption moment** for MPV VAT (`ShipmentDispatched` fires with `dispatch_mode = pickup` at handover; Module S emits INV2). **Sanctions/Hold re-read at handover (DEC-181):** the operator's Admin Panel handover-confirmation step re-reads sanctions + Hold state at the moment of handover — an active failure / Hold landed between `planned` and the appointment blocks handover-confirmation (the bottles remain on-premises pending re-check). SO lifecycle `picking → shipped (= handed over) → completed`; no carrier ETA tracking; the shipping fee may be zero/minimal.
- **Events (`event`):** bottles ship to an event venue (recorded on the SO); operationally similar to direct (carrier + Stream 3 + Stream 4); the event venue is the destination address.

### §5.2 Gift sub-flag (DEC-144 + DEC-116) **(IDLES — D5 gifting deferred)**

`is_gift = true` applies to **direct mode only** (INV-C-10). In v1.1: the Customer designates the redemption a gift; Module C reads the gift-recipient reference from Module S; `ShipmentDispatched` carries `is_gift` for recipient notification. **At launch, gifting is deferred (Module S D5 — the Voucher FSM is 7 states, GIFTED deferred), so the Module S gift sub-flow does not fire → Module C's `is_gift` sub-flag idles (not-exercised-at-launch).** **Seam (P1):** the `is_gift` attribute + the gift-recipient-address read + INV-C-10 are **retained-but-unexercised**; re-enable is additive when gifting returns (rides Module S's preserved voucher-ownership-transfer seam — the mutable customer-reference). No orphan — consistent with Module S's gift defer + Module K's gifting read-API idle.

### §5.3 Dispatch-mode-specific lifecycle notes

The three modes share the 5-state SO machine but differ in dispatch trigger, destination, carrier involvement, and INV2 treatment: **direct** → carrier custody from Vinlock; `ShipmentDispatched` on Stream 3; best-effort delivery via Stream 4; `incoterms` DDP/DAP for non-EU. **pickup** → handover at Vinlock IS the dispatch + delivery moment (operator-recorded; no carrier; `incoterms` N/A; fee zero/minimal). **event** → carrier to the event-venue address; Stream 3 + Stream 4. INV2 fires regardless of mode (shipping fee = zero where applicable). For pickup, the operator records the handover in the Admin Panel to advance `picking → shipped`.

---

## §6 Carrier Selection + Shipping-Fee Quote (DEC-145 + DEC-146) **(FLOOR — the INV2 tax contribution; the D3 white-glove enabler)**

### §6.1 Quote-generation two paths (DEC-145) **(the manual path IS the D3 white-glove enabler)**

Module C supports **two quote-generation paths**: **automatic** (the operator-configurable carrier-selection rule set, admin-managed; carrier-API rate where available) + **manual operator-entry** (a Customer Care / Fulfillment Operator enters fee + carrier + transit estimate). The manual path is used for the **white-glove Customer Care fallback** (complex destinations per DEC-147), negotiated rates, and cases the automatic rule set cannot resolve — **it is the D3 hybrid enabler** (§0.1). Both paths emit **`ShippingFeeQuoted`** with a **`quote_origin = auto | manual`** discriminator, carried through to `ShipmentDispatched` for cost-allocation audit. Carrier selection is **not** customer-facing (customers see the outcome — fee + estimated delivery date — but do not choose the carrier). The carrier-API contracts + Admin Panel UX are tech (DEC-073).

### §6.2 Shipping-fee quote at checkout

Module S Checkout calls Module C's quote generator at cart-finalisation (Module S §17.6); Module C receives destination + bottle count + weight + PR/alcohol classification (Module 0) + `dispatch_mode`, applies the automatic-path rule set (or routes to manual for white-glove), and emits `ShippingFeeQuoted` (informational; `quote_origin = auto` for standard checkout). **INV1 does NOT include shipping** (DEC-045) — INV1 is bottle/Voucher amount only. At SO creation + `planned → picking`, Module C re-confirms carrier availability and re-quotes if conditions changed (§6.4).

### §6.3 Cross-module shipping-fee event flow + INV2 composition (DEC-146) **(FLOOR — tax; the precise division of labour)**

```
At Checkout:   Module S calls C quote generator → C emits `ShippingFeeQuoted` (informational; INV1 excludes shipping, DEC-045) → S records the quote on the Order
At Shipment:   Logilize Stream 3 → C fires `ShipmentDispatched` (actual shipping cost) → S consumes → S emits `InvoiceINV2Issued`
```

**INV2 line items at launch (DEC-146 + DEC-045) — the precise division of labour (FLOOR; §0.7):**
1. **Shipping fee** — the **actual** cost from the `ShipmentDispatched` payload (**Module C contributes**).
2. **Destination VAT** — EU destinations under the MPV regime (Italy + France + EU OSS per DEC-056); non-EU under DDP/DAP does **not** carry destination VAT on INV2 (NewCo not registered; carrier collects) (**Module S computes** — the VAT jurisdiction resolves from the shipping Address C provides).
3. **Excise** — computed at `planned → picking`; **Module C emits `ExciseCalculated`** (§8) and **Module S reads it** for the INV2 excise line.
4. **Storage fee accrued up to dispatch** — the mid-semester roll-in (**Module S computes Module-S-internally**, S §14.4; no cross-module query).

**Module C does NOT issue invoices.** Module C contributes the **excise** + the **shipping fee**; **Module S composes + issues INV2** (`InvoiceINV2Issued`, S §10.7); Module E records the financial event + Xero decides GL (DEC-072). *(The kickoff/Phase-C shorthand "Module C composes the INV2 tax" is landed precisely here: Module C contributes the excise + shipping that feed INV2; Module S issues INV2 — consistent with cut-sheet C.18 + Module S §10.7/§17.6.)*

### §6.4 Re-quote at picking **(FLOOR — the actual-cost guarantee)**

Module C re-checks rates at `planned → picking`. If the rate is unchanged/within tolerance → the SO advances. If materially changed → a fresh `ShippingFeeQuoted` fires (`quote_origin = auto`); if it exceeds an operator-configured threshold the SO may pause pending acknowledgement. If the carrier is unavailable → the operator selects an alternative and enters a manual quote (`quote_origin = manual`). **INV2 always uses the `ShipmentDispatched` actual cost — not the checkout quote** (the contractual guarantee between Module C and Module S per DEC-146). The re-quote threshold + notification mechanics are tech (DEC-073).

---

## §7 Destination Eligibility + White-Glove Fallback (DEC-147/148/149) **(the D3 hybrid; OFAC FLOOR)**

### §7.1 Two-tier model (DEC-147) **(the hybrid IS v1.1's design — KEEP it; narrow Tier-1 at launch — D3)**

Destination eligibility operates in two tiers (the D3 hybrid mechanism + the seam):
- **Tier 1 — Automated path (pre-cleared destinations).** The operator configures the **eligible-destinations list** in the Admin Panel (DEC-041 baseline incl. OFAC mandatory). The destination is validated at two points: at **Checkout** (Module S, early signal) and at **SO creation** (Module C re-validates — the address may have changed). **At launch (D3), the Tier-1 automated pre-cleared list is narrowed to low-friction destinations (EU/UK/CH + whatever low-friction set Paolo confirms)** — a configuration of the operator-managed list. **The launch destination footprint is Paolo's owned launch-config call.**
- **Tier 2 — White-glove Customer Care fallback (complex / non-pre-cleared destinations).** A non-eligible destination offers a **"send shipping request" CTA** (not a hard block) → a Customer Care ticket → case-by-case review → **if approved**, a manual quote per DEC-145 (the SO proceeds; the approval recorded for audit) → **if denied**, the Customer chooses continued storage or pre-shipment cancellation (DEC-108). **At launch (D3), complex destinations (US, high-excise, state-alcohol) route via this already-built white-glove manual flow.** **Seam (P1):** the manual flow records the same shipment/payment/excise data a future automated engine consumes; the Tier-1 list is operator-expandable post-launch.

This two-tier model **replaces the v17 "hard block on ineligible destination"** — no destination is categorically unresolvable; the white-glove fallback preserves optionality.

### §7.2 OFAC + US-state alcohol rules **(OFAC FLOOR; US-state simple-at-launch — D3)**

- **OFAC screening — FLOOR.** OFAC applies at **all** destinations regardless of tier (DEC-041); it composes with Module K's sanctions/Hold gate (DEC-113; the read-API tuple, K §9.3). **Retained even in the D3 hybrid cut — non-negotiable, not a candidate.**
- **US-state alcohol rules — simple-at-launch (DEC-148).** A minimal operator-configurable pre-cleared US-state subset routes on the automated path; harder-rule states route via white-glove. **The automated US-state rule-matrix expansion is already-deferred Phase 2+** — carry verbatim (§16). Under the D3 launch footprint, US routes primarily via white-glove.

### §7.3 DDP/DAP non-EU — simple model at launch (DEC-149) **(simple-at-launch — D3; INV2 composition FLOOR)**

For non-EU destinations Module C records `incoterms` (`DDP`/`DAP`; operator-configurable per destination, admin-overridable) on `ShipmentDispatched`. **At launch a simple DDP/DAP default** applies for the pre-cleared non-EU subset; edge cases route via white-glove. **The INV2 composition (FLOOR — tax):** non-EU under DDP/DAP carries **no destination VAT** on INV2 (NewCo registers Italy + France + EU OSS only, DEC-056; the carrier collects destination VAT) + origin-side excise; **EU destinations carry destination VAT under MPV.** **Country-by-country DDP/DAP rule expansion is already-deferred Phase 2+** — carry verbatim (§16). Customer-facing T&C disclosure of DDP/DAP terms at checkout is Module S scope.

---

## §8 Excise + Customs Computation Event Flow (DEC-150) **(FLOOR — tax) + GENERALISE**

### §8.1 Event ownership

NewCo's logistics manager owns the **regulatory frame** (excise classification, rate matrix, compliance disposition); Vinlock (via Logilize) **executes** the operational compliance steps (lodging customs documentation, bonding goods, executing declarations). **Module C records the computation event** at `planned → picking`; consumes Logilize's customs-documentation-completed event for SO progression.

### §8.2 Computation event flow **(FLOOR — tax; runs even in the white-glove flow)**

```
SO planned → picking:
  Module C reads: destination address (Module K) + Product Reference / alcohol classification (Module 0) + the excise rate per destination (the operator-configurable rate matrix)
  Module C emits `ExciseCalculated` (computed excise amount per Voucher, destination, PR reference, rate applied)
  Module S reads `ExciseCalculated` for the INV2 excise line (S §10.7)
Logilize executes the customs documentation; emits customs-documentation-completed → Module C advances SO `planned → picking`
Module E records the financial event for excise (DEC-072); Xero decides GL — Module C makes no accounting-policy claim
```

**Bonded-warehousing.** Vinlock operates as a bonded warehouse; bottles are in bonded state (VAT + excise deferred until release-for-consumption = shipment). The `ShipmentDispatched` moment is the regulatory release-for-consumption trigger (for pickup: handover; for event: departure to the venue).

**Honest (D3):** the excise computation runs **even in the D3 manual white-glove flow** — the floor cannot be cut. **The operator-managed rate matrix is the launch posture (the D3 manual-first floor); the rate-matrix expansion + automated update workflow is already-deferred Phase 2+** (DEC-150) — carry verbatim (§16). *(Naming cascade: the alcohol-classification read renames `Wine* → Product*`; naming only.)*

---

## §9 Damages / Breakage / Transit-Loss Event Ownership (DEC-151) **(KEPT — the trigger + the E seam)**

### §9.1 Event ownership split

**Module B-owned (cross-link only):** `BottleBreakageInCustody` (Vinlock detects in-custody breakage; Module B records per DEC-132; Module A debits the pool; Module C is involved only if the breakage is discovered during pick — §4.4, surfaced via `manual_review`; Module B still records).

**Module C-owned:** `BottleBreakageInTransit` (transit damage post-delivery → returns + replacement, §10); `BottleLossInTransit` (carrier loss → replacement, §10; the `lost` path); `BottleWriteOff` (Module C records when it is the trigger — transit-loss total-loss closing the Allocation pool debit; for in-custody breakage Module B records the write-off); `InsuranceClaimOpened` (carries `insurance_pool ∈ {carrier, newco_supplementary}`, DEC-167/048 — preserving recovery-routing lineage; the recovery composes as a net-back, not a synchronous offset); `InsuranceClaimResolved` (settlement outcome; Module E records the financial event, DEC-072).

### §9.2 Insurance basis + cost allocation

The insurance basis (carrier vs NewCo supplementary cover; liability split) is **deferred to Vinlock contractual conditions** (DEC-048 — operational scope, already out). Module C records the events; the operator manages the claim workflow through the carrier's process. Transit-damage / transit-loss cost absorption is generally NewCo's (customer-fault is rare for transit); Module E records the financial event for recovery; Xero decides GL (DEC-072).

### §9.3 Post-shipment issue triage — Module C vs Module B boundary

Customer Care classifies the report before routing (a business-logic boundary statement at the PRD level — not a UX prescription): **physical bottle damaged / lost** → Module C (`PostShipmentIssueReported` / `BottleLossInTransit`) → returns + replacement / insurance + replacement; **NFC tag unreadable, contents intact** → Module B only (`BottlePostShipmentTagIssueReported` → provenance certificate, DEC-130 — *rides the D12 decouple*); **both damaged** → both chains run concurrently; **contents disputed** → Module C. **Key boundary:** Module B's recovery scope (NFC tag / Bottle Page) is distinct and non-overlapping with Module C's (physical bottle); they may co-occur, in which case both chains run independently. Module C does not consume Module B's tag-recovery events and vice versa.

---

## §10 Returns + Replacement Workflow (DEC-138 + DEC-184) **(D14 manual-first; the FSM + discipline KEPT)**

### §10.1 Module-C-owned end-to-end (DEC-138)

Module C owns the **post-shipment returns + replacement workflow end-to-end** for physical bottle issues. Module C records the physical events; the **original Voucher's commercial state is preserved** (no new Voucher; no new INV2 — INV-C-08); Module C issues **replacement shipments only** (no cash refunds — INV-C-07; cash refunds are Module S supervisor-override scope, §12.3 + DEC-108); Module E records the non-revenue replacement cost (DEC-072). *(Structural-rejection note: a Module-S-authoritative-on-Voucher-state interpretation was rejected as incompatible with the DEC-102 8-state lock; the original commercial right is preserved by the replacement.)*

### §10.2 Module C event chain + the DEC-184 FSM **(D14 — manual-first; the FSM + 4-event chain KEPT as the seam)**

The Returns/Replacement entity carries the **DEC-184 FSM** at launch: `REPORTED → INVESTIGATED → APPROVED → REPLACEMENT_ISSUED → CLOSED` with `REJECTED` and `WITHDRAWN` as terminal pre-replacement off-ramps. **At launch (D14), operators run the FSM end-to-end via the Admin Panel** (`feedback_prd_rr_approval` governs the supervisor-override approval) — **the FSM *automation* (auto-transitions / auto-routing / auto-notification) is deferred.** The FSM + the events **are** the seam. The 4-event chain:

```
1. `PostShipmentIssueReported`   (Returns → REPORTED; operator confirms intake)
2. `ReturnReceiptRecorded`       (only if the Customer ships the damaged unit back — rare; Module D records the physical receipt at Vinlock)
3. `ReplacementShipmentIssued`   (Returns → REPLACEMENT_ISSUED; replacement drawn from the same Allocation pool unsold sub-pool, INV-C-01; pool-exhausted → DEC-104 substitution, manual at launch; Module S consumes for Customer notification only — original Voucher state unchanged)
4. `ReplacementShipmentDelivered`(Returns → CLOSED)
```

Plus the transition events: `ReturnInvestigationStarted` (→ INVESTIGATED), `ReturnApproved` (→ APPROVED), `ReturnRejected` (→ REJECTED), `ReturnWithdrawn` (→ WITHDRAWN). **Supervisor-override-refund closure path:** in the rare exceptional case, the Returns entity transitions APPROVED → CLOSED with `closure_path = supervisor_override_refund` — no `ReplacementShipmentIssued` fires; no NonRevenueCost wrapper fires from Module C; Module S transitions the Voucher to VOIDED (S §12.3). **The DEC-167 NonRevenueCost wrapper + the DEC-182 OC-reversal-mirror (`DiscoveryRevenueShareReversed` from Module S) + fresh-OC-accrual fire at `ReplacementShipmentIssued` — the Module E seam** (deferred settlement D19; D18 dual-record KEPT). Voucher-substitution full automation is already-deferred (DEC-104, manual at launch) — carry.

### §10.3 Cross-module boundaries **(the C-vs-S boundary — the D6/D14 interlock)**

- **Module S boundary:** Module S consumes **only** `ReplacementShipmentIssued` for Customer notification — no Voucher state change. The original Voucher remains SHIPPED (or CONSUMED); the DEC-102 8-state lock preserved. **Post-shipment damage/loss = C returns+replacement, NOT S cancellation** (Module S §12.3). The Module S supervisor-override post-delivery cash refund (`SupervisorOverridePostDeliveryRefund`, DEC-108) is **separate and independent** of Module C's replacement workflow (Module C records the underlying issue events; Module E records the financial event).
- **Module B boundary:** Module-C returns + replacement covers **physical bottle** issues; Module B-side post-shipment **NFC tag** damage (`BottlePostShipmentTagIssueReported` + `ProvenanceCertificateIssued`, DEC-130) is event-recording at Module B only — it does **not** trigger a Module C replacement. Distinct, non-overlapping; they may co-occur.
- **Module E boundary:** Module E consumes Module C's non-revenue cost events (`ReplacementShipmentIssued` + `ReturnReceiptRecorded` where applicable + `InsuranceClaimResolved`); Xero decides GL (DEC-072).

### §10.4 Cross-module state during the `returned` SO path **(the committed-inventory interlock)**

- **Module S Voucher state:** the original Voucher remains in its pre-return state (CONSUMED if `BottleDelivered` fired; SHIPPED otherwise); neither regression is permitted (DEC-102). Module S consumes `ReplacementShipmentIssued` for notification only.
- **Unfulfillable replacement:** if the replacement cannot be dispatched (pool exhausted, no DEC-104 substitute approved), Module C records `ReplacementShipmentIssued` with `status = unfulfillable`; Customer Care escalates via white-glove; the last-resort path is a Module S supervisor-override cash refund (§12.3) — tracked in Module S, not Module C.
- **Module A pool interaction (the committed-inventory interlock):** the replacement bottle is drawn from the Allocation's unsold sub-pool (`Allocation.qty − issued`; INV-C-01). The original Voucher's `qty_issued` is **NOT decremented** (the commercial right is preserved). The pool-exhausted path interlocks with **Module A's `VoucherCancelled` release primitive** (A §11.5.2, DEC-099) ↔ **Module B's `InventoryShortfallDetected`** — the shortfall workflow (Substitute / Refund / Cancel) runs at Module A.
- **Module D boundary during `ReturnReceiptRecorded`:** the returned unit arrives at Vinlock and is received by Module D as a **reverse-inbound of a DELIVERED unit** (distinct from a producer-recall reverse-inbound); Module D records the physical receipt; Module C records `ReturnReceiptRecorded` as the commercial event. The returned bottle's physical disposition is managed operationally at launch — **full reverse-inbound mechanics (disposition tracking, restock eligibility, cost-basis assignment) are already-deferred Phase 2+** (OQ-18/DEC-155) — carry.

---

## §11 In-Transit Voucher Display + Redemption-Block (DEC-081 + DEC-143) **(redemption-block FLOOR; ETA precision basic — D17/item K)**

### §11.1 Shipment gate vs sellability gate (DEC-081) **(FLOOR)**

NewCo decouples two gates: the **sellability gate** (`Allocation.state = ACTIVE` — operator-publish post-PO-commit, uniform across V1/V2/Direct Purchase per DEC-183; the Module S sellability gate) and the **shipment gate** (Module D's `InboundEventPhysicallyAccepted` — at physical receipt; **Module C's gate**). For V2 passive-consignment stock (the typical case) both fire together. **The shipment gate is sourcing-model-uniform** (the same event for V1/V2; Direct Purchase deferred — Phase C item I). **V1 passive consignment still ships producer→Vinlock per order** (Module D §12.1/§14.6), so a voucher-before-physical-receipt window persists even with Direct Purchase deferred (the forwarded item-K window, §0.8).

### §11.2 In-transit display contract (DEC-143) **(basic — D17; admin-estimate ETA)**

When a Voucher's Allocation has **not yet** had `InboundEventPhysicallyAccepted` fire, Module C surfaces **"in transit; ETA X"** on the cellar render (§14) + any Customer-facing Voucher detail surface (the Module C/S surface — Module S reads C's in-flight SO + ETA, S §17.6). **At launch (D17/item K), ETA X is a basic admin-configurable estimate** (carrier-ETA-if-available else an admin estimate from the ProcurementIntent / PurchaseOrder context — **the carrier-ETA-precision integration is deferred**). The display clears automatically when `InboundEventPhysicallyAccepted` fires. For V2 stock at Vinlock at/before issuance, no in-transit display appears.

### §11.3 Redemption-block business rule **(FLOOR — INV-C-02/03)**

**A Customer cannot redeem an in-transit Voucher** — redemption is blocked until the goods are physically at Vinlock. **Enforcement:** at SO `draft → planned`, Module C checks whether `InboundEventPhysicallyAccepted` has fired for the backing Allocation; if not (goods in transit), the SO remains in `draft` with `compliance_hold = true` (the shipment-gate sub-type); the Customer is notified of the in-transit status + ETA. Once the event fires, the display clears, the `compliance_hold` lifts, and the SO advances at the next eligibility-check pass. **In-transit Voucher cancellation:** the DEC-108 14-day pre-shipment right applies normally (Module-S-owned; Voucher ISSUED → VOIDED); Module C has no special in-transit-cancellation path. The UX rendering is tech (DEC-073). **The redemption-block is FLOOR (cannot redeem stock not physically at Vinlock); only the ETA precision is the D17 defer (§0.8).**

---

## §12 Producer Recall Reverse Logistics (DEC-152) **(D15 — minimal/manual)**

### §12.1 Scope and manual posture

Module C's recall reverse-logistics scope at launch is **manual operator capability only** — an operator-driven mirror of the forward shipment workflow (already lean). When Module D records `ReverseInboundEventRecorded` (the producer formally initiated a recall, Module D §9 + DEC-090), NewCo logistics ops coordinates the physical return with Vinlock + the producer **outside the system** (email / phone / Vinlock-direct); the operator initiates the reverse-shipment instruction via the Admin Panel (no automated reverse-logistics-carrier-API integration at launch).

### §12.2 Recall scope discipline (DEC-117) **(INV-C-06 — ISSUED immune; floor-adjacent)**

Recall scope = **unsold sub-pool only** (`Allocation.qty − issued`), calculated dynamically at reverse-pick instruction-generation time (a newly-issued Voucher after recall initiation but before generation is excluded from the recall-eligible sub-pool). **ISSUED Vouchers are immune** — the customer's commercial right is preserved regardless of the producer's recall request; Module S Voucher state is unaffected. Serialized → the instruction names specific serials in the unsold sub-pool; non-serialized → qty units from the unsold non-serialized portion. Module C observes Module A's `AllocationRecallTriggered` (A §12.2) for the recall scope.

### §12.3 Module C event at reverse logistics

When stock physically leaves Vinlock back to the producer (under manual coordination), Module C records **`ReverseShipmentDispatched`** (carrying Allocation reference, qty / serial references, destination = producer address, actor = operator identity, timestamp). It feeds Module D's `ReverseInboundEventRecorded` chain + Module A's Allocation pool debit for the recalled qty (Module A observes). Module E records the financial event for any reverse-logistics cost (DEC-072).

### §12.4 Phase 2+ reverse-logistics capability (already-deferred — carry verbatim)

**Full reverse-inbound mechanics are already-deferred Phase 2+** (OQ-18/DEC-155): the automated reverse-carrier API integration; the three-gate reverse QC (condition check / cost-basis assessment / disposition routing); reverse-discrepancy paths; pool restock on QC pass. None are in scope at launch — the §12.1–§12.3 manual posture is the launch floor; the Phase-2+ mechanics are additive (the seam). Matches Module A + Module D + Module B's recall side (all ratified, event-record-only).

---

## §13 Storage-Location Customer-Facing Granularity (DEC-153) **(warehouse-level; sub-warehouse + multi-warehouse already-deferred)**

The Cellar render (§14) displays storage location at **warehouse-level granularity** at launch — "Stored at NewCo Vinlock cellar in France" (single-warehouse single-location; a brand statement, not a physical address). **Sub-warehouse granular detail (row / rack / cellar zone) remains Logilize-internal — NOT exposed customer-facing at launch (already-deferred, DEC-153).** **Multi-warehouse routing + cross-warehouse-consolidation + per-warehouse visibility are already-deferred Phase 2+** (OQ-16/DEC-155); the SO has no warehouse-routing attribute (the v17 Split Shipment Constraint is trivially satisfied at one warehouse). **The storage-location summary read switches from Logilize-direct to Module B-summary (DEC-188 — the B→C cascade; §14).** Carry verbatim; the literal Cellar UX is tech (DEC-073).

---

## §14 Cellar Render Data Composition (DEC-154) **(D17 basic; the six-module read + anonymisation KEPT; B-summary source switch)**

### §14.1 Six-module read contract **(the seam — KEPT)**

The Cellar render is a **six-module read contract** with Module C as the data orchestrator for physical-side data:
1. **Module S** — Voucher state (the 7-state FSM at launch — GIFTED deferred; only operationally relevant states display) + storage-fee state (the storage clock; accrued months; next-INV3 estimate per DEC-119).
2. **Module C** — physical state for in-flight Vouchers (PICKED / DISPATCHED / DELIVERED annotations on the active SO).
3. **Module C** — the in-transit voucher state ("in transit; ETA X" — basic admin-estimate ETA, §11; D17).
4. **Module B** — the **storage-location summary** (warehouse-level; **the data source switched Logilize-direct → Module B-summary, DEC-188 — the B→C cascade**, Module B §15.1/§16) + the **Bottle Page link** (serialized; for non-serialized → "non-serialized / no Bottle Page", DEC-186 — Module B §16).
5. **Module 0** — Product Reference catalog identity (vintage, producer name, wine name, tasting notes, format; translatable per DEC-031/064). *(Naming cascade: `Bottle Reference → Product Reference`; wine-display alias retained.)*

**The D17 SIMPLIFY:** the basic view = the six-module read with **warehouse-level storage + admin-estimate ETA + the standard physical-state annotations**; **defer the richest aggregation** (carrier-ETA-precision, granular storage). The six-module read contract is the seam (richer aggregation is additive).

### §14.2 Anonymisation discipline **(FLOOR-adjacent — DEC-024)**

The Cellar is the **Customer's private authenticated space** — the Customer sees their own holdings (own Voucher refs, own storage-location reference) fully, with no anonymisation. **The anonymisation discipline (DEC-024) applies only to the public Bottle Page surface** (Module B's data contract — zero customer identifiers: no Customer.id / Profile.id / Voucher.id / recipient / address). **Zero customer identifiers leak from the public Bottle Page — compliance-adjacent floor; the boundary is the floor.** Consistent with Module B's ratified Bottle Page anonymisation (Module B §16).

### §14.3 Cellar UX deferred (already-deferred — carry verbatim)

The Cellar UX layout + design (tile structure, Voucher card, storage-location badge, in-transit indicator, Bottle Page deep-link placement, storage-fee display, state-change CTAs) are **already-deferred** (DEC-073/DEC-154) to the UX phase. Module C's commitment at the PRD layer is the **six-module data-source contract** above (which module provides which element at render time); the literal aggregation mechanism is tech (DEC-073).

---

## §15 Module C Event Catalogue at Launch **(R3 reconcile in §15.2 + §15.4)**

Module C emits a versioned set of domain events; payload field-by-field listings are out of PRD scope (DEC-073). *(Naming cascade applied to PR-referencing payloads; the gift-event arm idles — D5.)*

### §15.1 Emitted by Module C

**SO lifecycle:** `ShippingOrderCreated` (on `VoucherRedemptionRequested` consume), `ShippingOrderPlanned`, `ShippingOrderPickingStarted`, **`ShipmentDispatched`** (carries actual shipping cost + `incoterms` + `dispatch_mode` + `quote_origin` + the bound serial / NFT identity for serialized, or allocation + InboundBatch + qty for non-serialized; consumed by Module S → `VoucherShipped` + `InvoiceINV2Issued`; the chain extends to Module B per DEC-134 — **DECOUPLED, D12**), `ShippingOrderCompleted`, `ShippingOrderCancelled`, `ShippingOrderReturned`, `ShippingOrderLost`.
**Late-binding:** **`BottlePicked`** (the bind — records the bound serial / NFT identity for serialized, or allocation + InboundBatch + qty for non-serialized; the Allocation pool reference; effective-unbreakable status), `ShippingOrderManualReviewRequired` (the voucher-side tertiary tiebreak — DEC-137).
**Delivery:** **`BottleDelivered`** (consumed by Module S → Voucher CONSUMED).
**Shipping-fee:** `ShippingFeeQuoted` (`quote_origin = auto | manual`; informational at checkout + at re-quote).
**Excise / customs (FLOOR — tax):** **`ExciseCalculated`** (at `planned → picking`; consumed by Module S for the INV2 excise line).
**Damage / transit-loss (DEC-151):** `BottleBreakageInTransit`, `BottleLossInTransit`, `BottleWriteOff`, `InsuranceClaimOpened` (`insurance_pool ∈ {carrier, newco_supplementary}`, DEC-167/048), `InsuranceClaimResolved`.
**Returns + replacement (DEC-138/184):** `PostShipmentIssueReported`, `ReturnInvestigationStarted`, `ReturnApproved`, `ReturnRejected`, `ReturnWithdrawn`, `ReturnReceiptRecorded`, **`ReplacementShipmentIssued`** (consumed by Module S for notification only; the DEC-167 NonRevenueCost wrapper + DEC-182 OC-reversal-mirror fire here — the Module E seam), `ReplacementShipmentDelivered`. *(The FSM automation is manual-first at launch — D14; the FSM + events are the seam, §10.2.)*
**Reverse-logistics (DEC-152):** `ReverseShipmentDispatched`.

### §15.2 Consumed by Module C **(R3 RECONCILED — the 4-fulfilment-stream contract)**

- **Module S** `VoucherRedemptionRequested` (S §11.7) — the SO creation trigger.
- **Module D** `InboundEventPhysicallyAccepted` (Module D §7 + DEC-081) — the shipment gate; consumed at SO `draft → planned`.
- **Module D** `ReverseInboundEventRecorded` (Module D §9 + DEC-090) — the recall-coordination trigger.
- **Module A** allocation events (`AllocationCreated`, `AllocationActivated`, `AllocationSubPoolRebalanced`, `AllocationNonSerializedOptOutChanged`, `AllocationRecallTriggered`) — observed for allocation-pool boundary reads + recall coordination.
- **Logilize fulfilment-stream events (the 4-fulfilment-stream contract — R3 reconciled from the stale "5-stream" framing):** **Stream 2** pick-confirmation, **Stream 3** dispatch-confirmation, **Stream 4** delivery-confirmation, + the **customs-documentation-completed** event (for the excise/customs workflow, DEC-150). **Storage-location is Module B's Stream B1 — Module C does NOT consume a storage-location stream directly** (the Cellar storage-location summary is read via Module B, §14; DEC-188).

### §15.3 Observed but not Module-C-owned

`VoucherShipped` + `InvoiceINV2Issued` (Module S emits on consuming `ShipmentDispatched`; Module C does not own INV2 issuance); `VoucherSubstitutionExecuted` (Module S — substitution under DEC-104); `SupervisorOverridePostDeliveryRefund` (Module S — the post-delivery cash refund, §12.3); `BottleNFTBurned` (Module B — the chain terminus via Module S `VoucherShipped`, DEC-134 — **DECOUPLED, D12**; NS → `BottleShippedAsNonSerialized`); `AllocationPoolDebitedDueToLoss` (Module A — the §17.4 destruction cascade; Module C observes for the replacement-stock-availability check — *consistent with Module B's drafted §17.4/§19.3; see the digest flag on the A-side emission*); `BottleDestroyedInCustody` (Module B — in-custody breakage; Module C observes for the rare pre-shipment substitute-pick path per DEC-104).

### §15.4 Cross-module contract summary **(R3 RECONCILED — the WMS row is 4 streams)**

| Contract type | Partner | Event / read object | Module C role | Key DEC |
|---|---|---|---|---|
| Consume — SO creation trigger | Module S | `VoucherRedemptionRequested` | Consumer | DEC-102 + DEC-139 |
| Consume — shipment gate | Module D | `InboundEventPhysicallyAccepted` | Consumer (SO `draft → planned`) | DEC-081 + DEC-143 |
| Consume — recall initiation | Module D | `ReverseInboundEventRecorded` | Consumer | DEC-090 + DEC-152 |
| Read — allocation context | Module A | sub-pool partition, counterparty, commercial terms | Read-only (late binding) | DEC-092 + DEC-099 + DEC-137 |
| Read — effective-unbreakable + StockPosition | Module 0 + A + B | Layer-1 (0) / Layer-2 `producer_breakability` (A) / **StockPosition `available_quantity`** (B) | Read-only (no-oversell-at-pick) | Module B §8; DEC-196 |
| Read — Customer / Profile / Address / Hold / sanctions | Module K | the read-API tuple `(sanctions_status, active-Hold-list)` + Customer/Profile/Address | Read-only (enforce at C's surfaces) | Module K §9.3/§4.8.1 + DEC-113/181 |
| Read — PR identity + excise classification | Module 0 | Product Reference, alcohol classification, Composite SKU | Read-only | Module 0 §3 + DEC-150 |
| Emit → Voucher chain + INV2 | Module S | `ShipmentDispatched` → `VoucherShipped` + `InvoiceINV2Issued` | Emitter; Module S issues INV2 | DEC-107 + DEC-146 |
| Emit → excise INV2 line | Module S | `ExciseCalculated` → INV2 excise line | Emitter; Module S reads | DEC-150 + Module S §10.7 |
| Emit → delivery → CONSUMED | Module S | `BottleDelivered` → Voucher CONSUMED | Emitter | Module S §11.7 |
| Emit → replacement notification | Module S | `ReplacementShipmentIssued` | Emitter; notification only; no Voucher state change | DEC-138 |
| Observe — NFT burn terminus | Module B | `BottleNFTBurned` (via Module S `VoucherShipped`) | Observer — **DECOUPLED, D12**; NS → `BottleShippedAsNonSerialized` | DEC-134 |
| Read — Bottle Page link + storage-location summary | Module B | Bottle Page URL / "non-serialized" indicator + warehouse-level storage summary (**Stream B1**) | Read-only (cellar render; B-summary source switch) | Module B §15.1/§16; DEC-154 + DEC-188 |
| Observe — pool debit on loss | Module A | `AllocationPoolDebitedDueToLoss` | Observer (replacement-stock check) | DEC-132 |
| Observe — recall scope | Module A | `AllocationRecallTriggered` | Observer | DEC-117 |
| **WMS — 4 fulfilment streams (R3 RECONCILED)** | **Logilize** | **Stream 1 (outbound pick instruction) + consume Streams 2–4 (pick-confirm, dispatch-confirm, delivery-confirm) + customs-documentation-completed; storage-location = Module B's Stream B1** | **Sender (Stream 1) + consumer (Streams 2–4)** | **DEC-188** (supersedes DEC-140) + DEC-141 |
| Emit — financial triggers | Module E | `ExciseCalculated`, `ShippingFeeQuoted`/dispatch cost, `InsuranceClaimResolved`, replacement-cost events | Emitter; Module E records the financial event; Xero decides GL | DEC-072 + DEC-151 |

*(Module C does NOT touch `SupplierPaymentCompleted` — the E-emitted / D+B-consumed cascade, Phase C R4 — it is not a Module C contract.)*

---

## §16 Out of Scope at Launch **(already-deferred — carried verbatim; do not re-cut)**

Module C v0.3-MVP carries the v1.1 launch out-of-scope set forward verbatim with its existing re-introduction seams (P1; all feed [`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md)):

**Tech / downstream:** the Logilize API contract / payload / retry / failure-mode handling (DEC-073 + DEC-188); the carrier API endpoints / label-generation / tracking-integration mechanics (DEC-073 + DEC-145); on-chain NFT-burn wallet mechanics (Module B scope; **D12-decoupled**); the Bottle Page render surface (Module B owns the data contract); the Cellar render UX layout (DEC-073 + DEC-154); customer-facing notifications (downstream notification service consumes C lifecycle events).

**The three D3 automation engines (already-deferred — the D3 manual-first floor):** auto-generated US-state rule-matrix expansion (DEC-148 Phase 2+); DDP/DAP country-by-country rule expansion (DEC-149 Phase 2+); excise rate-matrix expansion + automated update workflow (DEC-150 Phase 2+). The operator-managed lists/matrix are the launch posture; the manual flow records the same data a future automated engine consumes.

**Late-binding / fulfilment already-deferred:** producer-override on late-binding selection (DEC-137 Phase 2+ + explainer-pending); the bottle-side Logilize warehouse-efficiency optimisation (the D13 defer — Logilize picks simply at launch; the two-surface structure is the seam); voucher-substitution full automation (DEC-104, manual at launch); the Returns/Replacement FSM *automation* (the D14 defer — manual-first at launch; the FSM + events are the seam); full reverse-inbound mechanics (OQ-18/DEC-155 — manual operator capability only at launch); automated reverse-carrier API integration (DEC-073 + DEC-152).

**Geography / warehouse already-deferred:** multi-warehouse routing (OQ-16/DEC-155 — single Vinlock at launch); drop-ship variant (OQ-17/DEC-155 — every shipment through Vinlock); sub-warehouse storage-location granular customer-facing display (DEC-153 — warehouse-level only); appointment-scheduling for customer-pickup (DEC-073 — Customer Care coordinates operationally).

**Cellar richness (the D17 defer):** carrier-ETA-precision integration (admin-estimate ETA at launch); the richest cellar aggregation. The six-module read contract is the seam.

**Dropped at NewCo (B2C-only):** the B2B credit-term branch + active-consignment SO carve-out (DEC-068/011); auto-SO on combined invoicing (DEC-017 — customer-initiated only).

**Idle (D5 gifting deferred):** the `is_gift` sub-flag + the gift-recipient-address read + INV-C-10 — retained-but-unexercised (the seam; rides Module S's voucher-ownership-transfer seam).

**Working-hypothesis NFT cluster (D12-decoupled / already-deferred):** the on-chain NFT-burn semantics + smart-contract audit/governance (Module B / EXT-1/EXT-3 scope; the DEC-120/121/122/124/131 cluster) — **carry verbatim; do not re-cut.** Module C dispatches regardless (the NS path is the universal fallback).

---

## §N Audit-Trail Trace Appendix (DEC-074)

The v17 §C inheritance map + the Stage-8 / Phase C cascade trace **live in the frozen v0.2 §N + App A** (not reproduced here per DEC-074 — the body restates the substance). This appendix adds **§N.2 — the MVP re-baseline trace.**

### §N.2 MVP re-baseline trace

This trace maps each v0.3-MVP section to its **frozen v1.1 predecessor** (`greenfield/01-prd/Module_C_PRD_v0.2.md`) + the **ratified cut-sheet** + **Phase C**. The load-bearing prose is the body above (DEC-074); this trace is for audit / diff.

| v0.3-MVP section | v1.1 (v0.2) anchor | Cut-sheet / Phase C | MVP disposition |
|---|---|---|---|
| §0 MVP scope at a glance | — (new) | cut-sheet §1; Phase C §1 | NEW — Phase D framing; KEEP-whole-on-ship→cellar-floor + 4 SIMPLIFYs (D3/D13/D14/D17) + D15 + R3 verdict. |
| §0.1 D3 geography-hybrid | — | cut-sheet §3.1; D3 | NEW — narrow Tier-1 to low-friction; complex via white-glove; OFAC + INV2-tax FLOOR; automation engines already-deferred. |
| §0.2 D13 late-binding pick | — | cut-sheet §3.2; D13 | NEW — defer the bottle-side Logilize optimisation; the bind + 7-step chain + StockPosition read KEPT. |
| §0.3 D14 returns/replacement | — | cut-sheet §3.3; D14 | NEW — FSM automation → manual-first; the FSM + 4-event chain + discipline + C-vs-S boundary + E seam KEPT. |
| §0.4 D17 cellar render | — | cut-sheet §3.4; D17 | NEW — basic view; six-module read + anonymisation KEPT; B-summary source switch. |
| §0.5 D15 recall + floor + NFT-burn ride | — | cut-sheet §3.5; Phase C item J | NEW — recall minimal/manual; the NFT-burn rides B's D12 decouple (C dispatches regardless); build-sequencing flag. |
| §0.6 R3 reconcile | — | cut-sheet §3.7 / C.14; Phase C §5-R3 | NEW — §15.2/§15.4 stale 5-stream → DEC-188 4-fulfilment-stream; storage-location = B's Stream B1; the §4.2 body + AC already correct. |
| §0.7 the floor + the INV2-tax precision | — | cut-sheet §1/§3.5; Phase C §6/item G | NEW — the ship→cellar / OFAC / INV2-tax / no-oversell-at-pick floor verified whole; the C-contributes-excise+shipping / S-issues-INV2 precision. |
| §0.8 in-transit UX scope (item K) | — | cut-sheet §3.6; Phase C item K | NEW — redemption-block FLOOR + basic in-transit display; carrier-ETA-precision deferred; V1-per-order window survives. |
| §0.9 L-PP + gift idle + naming cascade | — | cut-sheet §3.7; Phase C item A | NEW — zero producer writes; the `is_gift` idle; naming cascade (C keeps physical-unit names). |
| §1 Module Scope | v0.2 §1 | cut-sheet §2 | KEEP; in-scope re-annotated by floor / SIMPLIFY; naming cascade; the boundary note carries (incl. C does NOT touch `SupplierPaymentCompleted`). |
| §2 SO Entity | v0.2 §2 | cut-sheet C.1–C.4; DEC-139 | KEEP — FLOOR anchor; the 5-state FSM + 2 sub-flags + INV-C-01..10; INV-C-10 idles (D5). |
| §3 Late-Binding | v0.2 §3 | cut-sheet C.5–C.10; D13 / DEC-196 | KEEP the bind + 7-step chain + StockPosition no-oversell read (FLOOR); defer the bottle-side optimisation (D13); GENERALISE the PR/Variant reads. |
| §4 Logilize WMS Integration | v0.2 §4 | cut-sheet C.11–C.14; R3 / DEC-188 | KEEP the records/executes split + the shared discrepancy queue; the 4-fulfilment-stream contract; **R3 reconcile** (storage-location = B's Stream B1). |
| §5 Three Shipping Modes + Gift | v0.2 §5 | cut-sheet C.15/C.16; D5 | KEEP the three modes; the `is_gift` sub-flag IDLES (D5; retained-but-unexercised seam). |
| §6 Carrier + Shipping-Fee | v0.2 §6 | cut-sheet C.17/C.18; FLOOR (tax) | KEEP — FLOOR; the two quote paths (manual = the D3 enabler); the precise INV2 contribution (C contributes excise + shipping; S issues INV2). |
| §7 Destination Eligibility | v0.2 §7 | cut-sheet C.19–C.22; D3 | SIMPLIFY (D3) — narrow Tier-1 to low-friction; complex via white-glove; OFAC FLOOR; the automation engines already-deferred. |
| §8 Excise + Customs | v0.2 §8 | cut-sheet C.23/C.24; FLOOR (tax) | KEEP — FLOOR; `ExciseCalculated` runs even in the white-glove flow; rate-matrix automation already-deferred; GENERALISE. |
| §9 Damages / Transit-Loss | v0.2 §9 | cut-sheet C.25; DEC-151 | KEEP — the trigger + the E seam; the C-vs-B triage boundary. |
| §10 Returns + Replacement | v0.2 §10 | cut-sheet C.26/C.27; D14 | SIMPLIFY (D14) — manual-first; the FSM + 4-event chain + original-voucher-preserved + no-cash-refund + C-vs-S boundary + NonRevenueCost/OC (E seam) KEPT. |
| §11 In-Transit + Redemption-Block | v0.2 §11 | cut-sheet C.28/C.29; FLOOR / item K | KEEP the shipment gate + redemption-block (FLOOR); basic admin-estimate ETA (D17); the V1-per-order window survives. |
| §12 Producer Recall | v0.2 §12 | cut-sheet C.30/C.31; D15 | KEEP-minimal/manual; unsold-only (ISSUED immune); full reverse-inbound already-deferred. |
| §13 Storage-Location Granularity | v0.2 §13 | cut-sheet C.32; D17-adjacent | KEEP warehouse-level; sub-warehouse + multi-warehouse already-deferred; the B-summary source switch. |
| §14 Cellar Render | v0.2 §14 | cut-sheet C.33/C.34; D17 / DEC-154 | SIMPLIFY (D17) — basic; the six-module read + anonymisation KEPT; the B-summary source switch; GENERALISE. |
| §15 Event Catalogue | v0.2 §15 | cut-sheet C.35/C.36; R3 | KEEP + GENERALISE + **RECONCILE** (§15.2 + §15.4 → the 4-fulfilment-stream contract); the burn-chain observation rides B's D12 decouple; the gift-event arm idles. |
| §16 Out of Scope | v0.2 §16 | cut-sheet C.38; item N | KEEP verbatim → roadmap; the three D3 automation engines + the D13/D14/D17 defers + the already-deferred set carried with their seams. |
| §N MVP re-baseline trace | v0.2 §N + App A | — | NEW — this trace (the v17 inheritance / Stage-8 cascade traces live in the frozen v0.2 §N + App A). |

Notation: *KEEP* = the v1.1 substance is restated in full NewCo language without semantic change; *FLOOR* = an un-cuttable floor piece; *SIMPLIFY* = the sophistication around the floor deferred to manual-first / basic / config, the floor + entities/events KEPT (D3/D13/D14/D17); *KEEP-minimal* = already-lean, event-record-only (D15); *RECONCILE* = the R3 contract-consistency fix (naming/contract only); *GENERALISE* = naming-only rename (Product Reference / Variant + consumed Module-0 events), non-behavioural; *IDLE* = retained-but-unexercised at launch (the `is_gift` sub-flag, D5); *NEW* = Phase-D framing with no direct v1.1 predecessor.

---

## Cross-references

- **v1.1 predecessor (frozen)** — [`../../reference/v1.1/01-prd/Module_C_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_C_PRD_v0.2.md). The source spec (the highest cross-module-density module) carried at full fidelity; never edited (plan R4). Its §N + App A carry the v17 §C inheritance + the Stage-8 / Phase C cascade traces.
- **Ratified cut-sheet** — [`../01-triage/Module_C_CutSheet_v0.1.md`](../01-triage/Module_C_CutSheet_v0.1.md). §2 inventory (C.1–C.38 = scope), §3 module-specific changes (D3 / D13 / D14 / D17 / D15 / the floor verification + the NFT-burn ride / L-PP + D5-idle + naming cascade + the 5-stream reconcile), §5 acceptance delta, §6 the eight ratified Qs.
- **Phase C reconciliation** — [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md). R3 (the 5→4-stream reconcile — Module C owns it, §5-R3), item G (the no-oversell-at-pick floor), item I (Direct Purchase deferred), item J (the NFT/on-chain DECOUPLE — C dispatches regardless), item K (the in-transit-pre-receipt UX scope — named in C), §6 floor chains.
- **Naming source of truth** — [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18 (the canonical name table + the C physical-unit carve-out). Applied here, not re-derived.
- **Settled siblings (the cross-module contracts C consumes)** — [`Module_B_PRD_v0.3-MVP.md`](Module_B_PRD_v0.3-MVP.md) (the direct upstream — §0.6 names the five B↔C contracts: StockPosition §8 / serialized identity §4 / Stream B1 + the shared discrepancy queue §15.1/§15.3 / the Bottle Page link + inventory summary §16 / the NFT-burn chain §9.5) · [`Module_S_PRD_v0.3-MVP.md`](Module_S_PRD_v0.3-MVP.md) (`VoucherRedemptionRequested` §11.7 → pick; dispatch → `VoucherShipped` + `InvoiceINV2Issued` §17.6/§10.7; `BottleDelivered` → CONSUMED; the in-transit ETA; the post-shipment damage/loss boundary §12.3; the 7-state Voucher FSM / gift idle; the lesser-of ATP §8.6) · [`Module_D_PRD_v0.3-MVP.md`](Module_D_PRD_v0.3-MVP.md) (`InboundEventPhysicallyAccepted` §7/§14.6 — the shipment gate; `ReverseInboundEventRecorded` §9; the V1-per-order window survives) · [`Module_A_PRD_v0.3-MVP.md`](Module_A_PRD_v0.3-MVP.md) (the sub-pool partition §7/§11.6; `VoucherCancelled` §11.5.2 + the shortfall interlock; `producer_breakability` §8; `AllocationRecallTriggered` §12.2; over-issuance = operation-level rejection, no `AllocationCapacityExhausted` event) · [`Module_K_PRD_v0.3-MVP.md`](Module_K_PRD_v0.3-MVP.md) (the sanctions/OFAC read-API tuple + Hold §9.3/§4.8.1; DEC-181; trigger-agnostic; gifting read-API idles) · [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) (PR / Composite SKU / alcohol classification / Layer 1 / Bottle Page content).
- **Testable companion** — [`../03-acceptance/Module_C_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_C_Acceptance_v0.3-MVP.md).
- **MVP decisions register** — [`../04-decisions/MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md) (the thin index → authoritative docs; R3 + D3/D13/D14/D17/D15 + C-Q rows).
- **Method + dials** — [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (P1/P2, the floor) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (D3 / D13 / D14 / D17 / D15 / L-PP).
- **Next in the cascade** — **Module E** (the last module PRD; its kickoff is written at the end of this session) reads no Module C output directly beyond the financial-event seam (C records the operational event — excise, shipping, damages, NonRevenueCost/OC; E records the financial event + Xero decides GL; the deferred settlement D19 reads the recorded seam).

---

*End of Module C PRD v0.3-MVP — Phase D re-baseline. **Verdict: KEPT WHOLE on the "ship → cellar" half of the core loop (the shipment gate + in-transit redemption-block, the late-binding voucher→bottle bind, the dispatch event → INV2 + the C→S→B burn chain, the no-oversell-at-pick StockPosition read, the OFAC/eligibility surface, and the INV2 tax-correctness contribution) — the broadest dial-footprint of the triage (FOUR dials land in-module: D3 geography-hybrid · D13 late-binding pick · D14 returns/replacement · D17 cellar render, plus D15 recall) — yet the shape holds once more: simplify the sophistication around the floor, keep the floor whole.** Three of the four cuts are thinner than their billing (D3's two-tier + white-glove is already v1.1's design; D13's deferred optimisation is Logilize-internal; D14's Returns FSM is already operator-driven); only D17 is a clean SIMPLIFY. The residual heaviness is the compliance/tax FLOOR itself (OFAC + the INV2 tax-correctness contribution + excise even in the white-glove flow) — floor, not a candidate. Module C owns **RECONCILE R3** (§15.2/§15.4's stale 5-stream framing → the DEC-188 4-fulfilment-stream contract; storage-location = Module B's Stream B1; the §4.2 body + the AC already correct) and consumes the just-drafted **B→C contracts** coherently (StockPosition / serialized identity / Bottle Page link + inventory summary / the Cellar B-summary source switch / the dispatch-originated NFT-burn chain riding B's D12 decouple — C dispatches regardless). The `is_gift` sub-flag idles (D5); the naming cascade applied (C keeps its physical-unit names); L-PP trivial (zero producer writes; the cellar + in-transit display are consumer reads; no backend cut). **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E.*
