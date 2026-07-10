# NewCo ERP — Module E PRD (Finance — Financial-Event Recorder + Payment Execution + Xero Routing) — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP scope of Module E). **The eighth and final module PRD — the finance terminus + build Phase 6.**
- **Date**: 2026-06-08
- **Status**: **DRAFT — awaiting batch ratification** (Paolo). Held in `mvp/`; nothing promoted to `handoff/` until Phase E. *(Modules 0 / K / A / D / S / B RATIFIED 2026-06-07/08; Module C DRAFTED-awaiting-batch.)*
- **Owner**: Paolo (decides). Claude recommends.
- **Testable companion**: [`Module_E_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_E_Acceptance_v0.3-MVP.md) — the MVP acceptance re-cut (rides alongside this PRD).
- **Predecessors / inputs**:
  - **Frozen v1.1 predecessor** (strip *from*; NEVER edit): [`../../reference/v1.1/01-prd/Module_E_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_E_PRD_v0.2.md) (the finance terminus, ~1,036 lines — the four-clause structure) + [`../../reference/v1.1/01-prd/Module_E_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_E_Acceptance_v0.1.md) (DRAFT 2026-05-15; 223 criteria, 95.5% AUTO / 4.0% MIXED / 0.4% HUMAN; Packet APPROVE-WITH-CLARIFICATIONS — not yet Paolo-validated). `greenfield/` is the frozen audit/diff anchor (plan R4).
  - **Ratified scope source**: [`../01-triage/Module_E_CutSheet_v0.1.md`](../01-triage/Module_E_CutSheet_v0.1.md) (RATIFIED 2026-06-07; Q1–Q7). §2 = the scope; §3 = the rewrite instructions; §5 = the acceptance delta. **⚠️ The cut-sheet predates the Phase C R4 flip** — where its RECONCILE reads "D-emits `SupplierPaymentCompleted`" (§1 fact 7 / §3.6 / Q6 / the E.* rows / AC-E-J-37), **Phase C R4 (E-emits, D+B-consume) WINS.**
  - **Coherence gate**: [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) (RATIFIED 2026-06-07). Module E owns **RECONCILE R4** (§5-R4 / §2-C — **E-emits ⚠️**); editorial notes **N2** (finance triggers) + **N3** (party naming); items **E** (OC capture) + **F** (title timing); and the §6 floor chains (tax-correct invoicing · dual-record FX · KYC/sanctions/Hold · audit/retention).
  - **Source-of-truth names**: [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18 (the canonical name table; apply, don't re-derive). **Module E keeps its own category-neutral names** per the §18 carve-out — **the lightest cascade of the eight** (§12).
  - **Settled siblings** (cross-checked — E is the terminal downstream of all seven): [`Module_S_PRD_v0.3-MVP.md`](Module_S_PRD_v0.3-MVP.md) (the three-actor split DEC-119; INV1/2/3 §16.9; the refund family + OC accrual §16.7/§16.11; storage Module-S-internal R2 §14; **§17.7 names E-emits**) · [`Module_D_PRD_v0.3-MVP.md`](Module_D_PRD_v0.3-MVP.md) (**consumes the E-emitted `SupplierPaymentCompleted` to close the PO** §6/§14.7; keeps recording all procurement financial events — the D19 seam; item F/N3 §3.5) · [`Module_B_PRD_v0.3-MVP.md`](Module_B_PRD_v0.3-MVP.md) (**the R4 consumer side §0.3/§2.2 — B consumes the E-emitted event for `ownership_flag` PRODUCER→NEWCO; does NOT emit it**; `InventoryAdjusted`/cost-basis-at-dispatch feed E) · [`Module_C_PRD_v0.3-MVP.md`](Module_C_PRD_v0.3-MVP.md) (the operational-event seam: `ExciseCalculated`/`ShippingFeeQuoted`/damages/`InsuranceClaimResolved`/`ReplacementShipmentIssued` + the DEC-167 NonRevenueCost-wrapper + DEC-182 OC-reversal — explicitly "the Module E seam") · [`Module_A_PRD_v0.3-MVP.md`](Module_A_PRD_v0.3-MVP.md) (per-constituent `commercial_terms` `C_i` + `producer_id`/`supplier_id` + `sourcing_model` — the settlement lineage E reads, not re-derives) · [`Module_K_PRD_v0.3-MVP.md`](Module_K_PRD_v0.3-MVP.md) (**N2 — the Hold registry is trigger-agnostic**; the Club-Credit entity ↔ E's events ↔ S's redemption; `OriginatingClubLocked` capture) · [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) (the naming source of truth).
  - **Method + dials**: [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (P1/P2, the floor) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (D4 DEFER INV3 dunning; D18 KEEP dual-record FLOOR; D19 DEFER settlement engine; D20 SDI already-deferred; D21 KEEP chargeback automation — Paolo override; L-PP).
  - **Auto-memory**: [[keep-payment-automation]] (the durable Paolo steer — customer payment automation [Airwallex charge/refund/chargeback] is FLOOR; only the back-office settlement engine [D19] + the failed-charge dunning [D4] defer, both "first cycle is months out") · [[phase-c-structural-outcomes]] (**the E-emits trap**).
- **Methodology** (the four binding NewCo DECs, carried from v1.1):
  - **DEC-072** — **no accounting positions.** Module E **records** financial events with the business signal each carries; **Xero decides GL treatment** (revenue recognition, deferred-revenue, COGS timing, journal-entry posting, chart-of-accounts, balance-sheet vs P&L, IFRS 15). **This is Module E's defining discipline** — every event below is event + business-signal-only, no GL claim. Where a choice of mechanic would force an accounting claim, it defers to Xero policy.
  - **DEC-073** — product-spec layer. Name the *contract* (entity concepts, attributes, lifecycle states, business-meaningful enums, domain-event names + signals, invariants, module boundaries); tech-implementation (the Airwallex/Xero/SDI/HubSpot literal API contracts, JSON shapes, FK/column declarations, retry/idempotency/webhook-signature mechanics, the settlement-run code, the dunning-orchestration internals, UX/layout) is downstream and out of scope.
  - **DEC-074** — self-contained. Anchors are restated inline so a builder who has not read v1.1 can take this into the dev phase; the v1.1 predecessor + the cut-sheet + Phase C are cited for audit.
  - **P1 / P2** (MVP principles) — every deferred/simplified item names the **seam** that makes the post-launch build additive + points to the roadmap (§11); Module E is **back-office finance — ZERO producer writes, ZERO consumer self-serve writes** (the cleanest L-PP); every finance-ops surface is operator-driven via the Admin Panel (§5.11, the 9th Admin-Panel PRD).

---

## §0 MVP scope at a glance

> **Verdict: Module E is the genuinely cut-heaviest finish — it carries the #3-heaviest lever of the whole exercise (D19) plus D4 + the already-deferred D20 — YET the shape holds one final time: defer the *settlement engine* and the *INV3 dunning orchestration*; keep the *customer-side payment-execution + Xero-routing + tax-correct-invoicing-recording floor + the dual-record FX-correctness (D18) + the three-actor split* whole, and keep chargeback automation (D21, Paolo override).** Module E is the terminal downstream of all seven upstream modules (0/K/A/D/S/B/C, all ratified KEEP-in-full / cut-with-floor-whole), so no Module E KEEP is orphaned. Its distinctive risk: a *deferred* engine must still keep **recording** the events its future build aggregates — and it does. Seven facts converge:

1. **D19 — the supplier-settlement *engine* is the headline DEFER (the #3 lever overall), and it is genuinely heavy — but cleanly deferrable, because the first quarterly close lands months post-launch and the *recording is the seam* (ratified Q1).** What defers (Clause 2, §4): the per-Producer/Supplier quarterly runs; the `ProducerSettlementStatementIssued` 5-section composition (DEC-156); the OC 5% aggregation into Section D (DEC-161/162); the producer-fault clawback netting (DEC-164/025, Section C); the settlement-counterparty disambiguation routing (DEC-161); the producer settlement-currency option; the settlement-statement FSM; the Xero AP routing of statements — all **to operator-run for the first cycle(s).** **Seam (P1):** Module E keeps **recording** every settlement-input event the deferred engine aggregates (§4.7) — the E-emitted `SupplierPaymentCompleted` (§5.9, R4), D's `InboundEventCostFinalized`, S's `DiscoveryRevenueShareAccrued`/refund/reversal stream, C's NonRevenueCost triggers, B's `InventoryAdjusted`/cost-basis — and routes each to Xero in real time. At launch the operator composes the first statement(s) manually from the recorded events + runs Xero AP manually; the engine reads the same recorded events when it is built — purely additive. **Honest: be careful the defer does NOT drop the recording — it does not.** (§4.)

2. **D4 — the INV3-failed-charge 3-stage auto-escalation orchestration DEFERs → manual first cycle (ratified Q2, "ok because is months away").** The saved-card *charge itself* — card auth+capture (DEC-158), bank-transfer PENDING_PAYMENT (DEC-159), the saved-card charge for INV2 at shipment + INV3 at storage cadence (DEC-158) — is **core-loop FLOOR, KEPT.** What defers (§3.3): the DEC-160 3-stage chain (Stage 1 auto-retry → Stage 2 auto-`StoragePaymentFailed`→K-Hold → Stage 3 auto-Profile-Suspension) + the automated multi-cycle composition. **Same "first cycle is months out" logic as D19** — storage (INV3) accrues only after the 12-month-free + bottle-at-warehouse double anchor; INV3 fires semi-annually end-Jun/end-Dec. **Seam (P1):** the `StoragePaymentFailed`→K-Hold→Profile-Suspension **event chain** + the admin-configurable staged thresholds + the multi-cycle rules are retained; the operator handles the first cycle(s) manually (Admin Panel + manual K Hold placement). **The sanctions/Hold re-read at charge (DEC-181) + the Hold-no-auto-lift discipline are FLOOR — KEPT, never deferred.** (§3.3.)

3. **D21 — chargeback automation is KEPT from day 1 (Paolo override of the locked dial — NOT a defer).** The Airwallex integration is floor from day 1 (you cannot process customer charges/refunds without it), so the chargeback dispute webhook (`dispute.created`/`dispute.resolved`) rides the same integration cheaply; deferring it to manual dashboard-monitoring would open a **fraud/Hold-latency gap** for a marginal saving. Paolo's steer: **"payment automation should be KEPT."** So the full 5-step chargeback chain auto-ingestion is KEPT and automated (§6) — `dispute.created` → auto-record → `CustomerChargebackFlagged` → K `CHARGEBACK_REVIEW` Hold + 7-BD SLA + no-auto-lift; `dispute.resolved` → auto-record. **(N2: the chargeback Hold trigger is automated; the storage-payment Hold trigger is manual-first — D4. Module K's registry is trigger-agnostic, §6.3.)** (See [[keep-payment-automation]].)

4. **D18 — the multi-currency dual-record is the data-integrity heart of FX-correct refunds — FLOOR; KEPT WHOLE; NOT a candidate (ratified Q4).** Every customer-facing financial event in customer-currency **and** EUR; per-leg FX rate-lock at each leg's emission moment; EOD Rome snapshot + admin buffer + daily refresh + Airwallex mid-market; **refund at the original captured rate (no fresh snapshot)**; `FXVarianceRecorded` for the Airwallex-vs-snapshot gap. **The honest answer to "can we simplify the FX layer?" is "no, and it would not save anything"** — D1 keeps all 5 currencies, and the dual-record machinery is **fixed-cost regardless of count** (the per-leg rate-lock, the snapshot pipeline, the refund-at-original-rate logic, the variance recording are the same whether 2 currencies or 5). Simplifying it would silently mis-refund customers — a data-integrity failure. (§7.2.)

5. **Module E owns the load-bearing customer-side floor — verified whole (KEPT).** The **three-actor split (DEC-119 — Module S = the EVENT / Xero = the ARTIFACT / Module E = the PAYMENT + the ACCOUNTING RECORD + the Xero ROUTING)** is the most architecturally load-bearing element of the PRD and is **FLOOR** (§1.1). **Clause 1** — consume S's INV1/INV2/INV3 → execute the Airwallex charge → route the financial-event payload to Xero for accounting (DEC-072) → route the document-generation request to Xero (DEC-028) → confirm the Xero-hosted document URL back to Module S — is the "pay" step of the core loop, **none of it cut** (§3). **Tax-correct invoicing *recording*** (INV1/INV2/INV3 under the MPV VAT regime, DEC-157) is FLOOR (§2). Refund execution + credit-note discipline + OC-reversal symmetry (§5.1); the `NonRevenueCostRecorded` unified wrapper (§5.4) + Stage-8 ingestion (§5.6); real-time per-event Xero sync + sync FSM + **post-sync immutability** + reversal-ordering (§7.1); sanctions/Hold re-read at charge (DEC-181, §3.3); the **OC 5% accrual *recording*** (the seam, §4.2) — all KEPT, floor or floor-adjacent. (§1, §2, §3, §5, §7.)

6. **Module E owns RECONCILE R4 — and ⚠️ lands E-EMITS `SupplierPaymentCompleted` (the trap; the cut-sheet says "D-emits"; Phase C flips it).** The cut-sheet's RECONCILE (E.32 / §3.6 / Q6) framed `SupplierPaymentCompleted` as "**Module D** emits; E + B consume." **Phase C ratification (Paolo Q2) FLIPPED this to E-emits — and Phase C WINS.** **Module E emits `SupplierPaymentCompleted`** on payment clearing (E is the payment executor — three-actor split DEC-119; symmetric with the customer-side `AirwallexChargeExecuted`); **Module D consumes it** → settle/close the PO; **Module B consumes it** → inventory `ownership_flag` PRODUCER→NEWCO. At launch E emits it when the operator records the manual supplier payment in E's finance surface (settlement operator-run, D19 deferred); post-launch via E's settlement engine. Atomic per PO (partial PO settlement deferred, OQ-20). This PRD **moves `SupplierPaymentCompleted` from Module E's *consumed-from-D* set into its *emitted* set** (§8.1 / §8.2 / §9.2 / §9.6 / §5.9 / AC-E-J-37). **N3:** the two ledgers are distinct — the inventory `ownership_flag` (B, keyed to `SupplierPaymentCompleted`) vs the PO-level title (D, keyed to the sale signal `VoucherIssued`) — same real-world party, both `NEWCO`, two ledgers, two signals (§5.9). **Item F:** name `VoucherIssued` (sell-through) + `VoucherShipped` (shipment leg); **take no accounting position** (DEC-072), folded into the deferred settlement (§5.9). (Ratified Q6; Phase C R4/N3/item F.)

7. **The OC 5% Discovery-share — the *capture* is whole at launch; only the *computation + settlement* defer with the engine (ratified Q5; Phase C item E — seam-critical).** Module S emits `DiscoveryRevenueShareAccrued` at INV1 (reading K's `OriginatingClubLocked` [K.13] + A's per-constituent lineage [A.11.7] at a one-shot, unreconstructable moment); **Module E records it at launch** (the seam). The **5% aggregation into Section D + the producer-fault clawback netting + the settlement** defer with the engine (D19) — E **computes the 5% + nets the clawbacks when the engine is built, reading K's OC lock + A's lineage, NOT re-deriving.** The composite OC-on-`P_d` defers with D7 (Module S); single-Allocation Discovery OC emission is KEPT. The Section D info-disclosure constraint (DEC-180) is preserved on the recorded accrual payload. (§4.2.)

**The naming cascade (Phase C item A — the lightest of the eight).** Module E reads no catalog identity directly except for **financial-event allocation lineage**: `Bottle Reference → Product Reference (PR)`, `Wine Variant/Master → Product Variant/Master` **only** in the BR-referencing payload/prose (the lineage carried on `NonRevenueCostRecorded`/`SellThroughSettled`/`OCShareAccrued`/`COGSAdjustmentRecorded`) + the Module 0 entities E reads for context. **Module E's own entity/event names (`Invoice*`, `Payment*`, `Settlement*`, `NonRevenueCost*`, `OCShare*`, `Chargeback*`, `Refund*`, `Xero*`, `FXVariance*`, `ClubCredit*`, `StoreCredit*`) are category-neutral — unchanged** (the §18 carve-out). "Bottle Reference" retained as a wine-display alias; payload semantics identical. Zero behaviour change. (§12.)

**The floor pieces Module E holds (all KEPT, whole) — verified in composition by Phase C §6:**
- **Tax-correct invoicing** — S emits INV1/INV2/INV3 → C contributes the INV2 excise/VAT → **E records the typology + executes the charge + routes to Xero** (the MPV VAT regime). (§2, §3.)
- **Dual-record FX (the FX-correct-refund floor)** — every customer-facing event in customer-currency + EUR; per-leg rate-lock; **refund at the original captured rate**; `FXVarianceRecorded` (D18, NOT a candidate). (§7.2.)
- **KYC / sanctions / OFAC / Hold** — **sanctions/Hold re-read at charge execution + at refund routing** (DEC-181, the uniformity invariant on the payment surface; interlocks with K's ratified floor). (§3.3, §5.1.)
- **Audit / retention** — real-time per-event Xero sync + post-sync immutability + reversal-ordering + credit-note discipline + 10-yr archival. (§7.1, §7.6.)

**The genuine launch-scope reductions — all seamed (P1):**
- **D19** the supplier-settlement *engine* → operator-run first cycle(s) (the recording of every settlement-input event is the seam; §4, §11).
- **D4** the INV3-failed-charge auto-escalation orchestration → manual first cycle (the `StoragePaymentFailed`→Hold→Suspension chain + thresholds are the seam; §3.3, §11).
- **The OC 5% computation + the producer-fault clawback netting** → deferred-with-settlement (D19); the accrual emission (S) + the cause-tagged recorded refunds (E) are the seam; E computes when the engine is built, not re-derives (§4.2, §4.5).
- **The v1.1 already-deferred set** — **D20 SDI connector** (DEC-171/EXT-2); paid services/experiences + INV4 + `EVENT_CONSUMPTION_SETTLEMENT`; partial PO settlement (OQ-20); B2B types INV0/INV-P/INV1_INV2_COMBINED; active-consignment SELL_THROUGH_SETTLEMENT; membership-fee INV0; AR-aging dunning — **carried verbatim** (§11). Do not re-cut.

**The seven ratified scope confirmations (cut-sheet §6, Paolo 2026-06-07):** **Q1** DEFER the settlement engine → operator-run; the recording is the seam (§4). **Q2** DEFER the INV3 auto-escalation orchestration → manual first cycle; card+SEPA + saved-card charge + the Hold chain + the sanctions gate + no-auto-lift KEPT (§3.3). **Q3 — REVISED to KEEP** chargeback automation from day 1 (Paolo override, §6). **Q4** KEEP the dual-record whole — FLOOR, not a candidate (§7.2). **Q5** OC 5% + clawback netting defer-with-settlement; the accrual emission + cause-tagged refunds are the seam (§4.2, §4.5). **Q6** RECONCILE `SupplierPaymentCompleted` — **landed as E-emits per Phase C R4** (the cut-sheet's "D-emits" superseded); name the title-timing nuance, no accounting position (§5.9). **Q7** L-PP zero producer/consumer self-serve writes; D20 + the already-deferred set carry verbatim (§5.11, §11).

> **Credit-event vocabulary — reconciled to DEC-174 (MVP-DEC-018).** Module E emits the **financial accrual / reversal** events `ClubCreditAccrued` / `ClubCreditRestored` / `ClubCreditForfeited` + `StoreCreditIssued`; the **application** events `ClubCreditAutoApplied` / `ClubCreditRemovedByCustomer` / `StoreCreditApplied` are **Module-S-emitted** (DEC-111), which Module E consumes for Xero. Module K owns the balance entity and consumes from BOTH. *(An earlier draft of this PRD had aligned E's emission names to Module K's then-stale registry — `ClubCredit{Issued,Applied,Restored,Forfeited}`; that reconciliation went the **opposite** way from the frozen DEC-166 + DEC-174 and was corrected at MVP-DEC-018 — `ClubCreditAccrued` is canonical, not `ClubCreditIssued`, and the application events are Module S's.)* Naming/contract only, no behaviour change. (§5.3, §8.1, §12.)

---

## §1 Module Purpose, the Three-Actor Split + the Four-Clause Role + Boundary

### §1.1 Module purpose at NewCo + the three-actor architectural lock (LOAD-BEARING — DEC-119) — FLOOR

Module E is **NewCo's financial-event recorder + Airwallex/Xero integration layer.** It owns: the AirwallexAdapter (charges, refunds, chargeback ingestion, multi-currency capture, saved-card storage — DEC-014/158); the sole NewCo-side Xero integration (routing financial-event payloads + document-generation requests — DEC-028/072); the supplier-side settlement *recording* (the engine deferred, D19); refund + chargeback + non-revenue-cost execution + recording (DEC-165/167/168); dunning trigger emission (DEC-160 — Hold creation owned by Module K). Module E does **NOT** own customer-facing invoice issuance, storage-fee computation, or Voucher-activation gating — all Module S (DEC-119).

**The three-actor architectural lock (DEC-119 clarification — the most load-bearing element of this PRD; FLOOR; *was v1.1 §0.2*):** every section that touches invoice issuance, document generation, or Xero integration anchors against this split:

- **Module S = the EVENT.** Module S owns the commercial-state moment that triggers an invoice — the INV1/INV2/INV3 issuance lifecycle, Voucher state transitions, cancellation-right closure, the storage clock, customer-account-history rendering. Module S decides **WHEN** an invoice fires.
- **Xero = the ARTIFACT.** Xero generates the formal financial document — PDF + template + document numbering + legal-text composition + jurisdiction-specific compliance (SDI/MTD/Factur-X plugin compatibility, DEC-028). Xero produces **WHAT** the customer and the tax authority receive.
- **Module E = the PAYMENT + the ACCOUNTING RECORD + the Xero ROUTING LAYER.** Module E executes the Airwallex charge against the customer's payment method (DEC-014), routes the financial-event payload to Xero for accounting (DEC-072), routes the document-generation request to Xero (Xero is the Document Generator, DEC-028), handles failed-charge handling / refund execution / chargeback ingestion / non-revenue-cost recording / supplier-side settlement *recording* / multi-currency dual-recording, and emits the Xero sync lifecycle events. Module E is the **HOW** between commercial event and accounting record.

When Module S emits `InvoiceINV1Issued` / `InvoiceINV2Issued` / `InvoiceINV3Issued`, Module E routes the event to Xero for **both** accounting AND document generation; Xero returns a document URL/reference; Module E confirms back to Module S; the Customer Portal surfaces the Xero-hosted document via that link. **This split is FLOOR — not a candidate; the entire customer-side finance loop rests on it.**

### §1.2 Four-clause role (the load-bearing role statement)

Module E's NewCo role is structured as **four scope clauses.** The MVP disposition is tagged per clause:

**Clause 1 — Consume + route + execute (customer-side). FLOOR — KEPT WHOLE.**
Module E consumes Module S `InvoiceINV1Issued` / `InvoiceINV2Issued` / `InvoiceINV3Issued`. For each: (a) executes the Airwallex charge against the customer's payment method per DEC-158 (cards, authorize+capture in one step) / DEC-159 (bank transfer, webhook-driven funds-cleared) / DEC-158 (saved card for INV2/INV3); (b) routes the financial-event payload to Xero for accounting (DEC-072 + DEC-028); (c) routes the document-generation request to Xero (Xero is the Document Generator, DEC-028); (d) confirms the Xero-hosted document URL back to Module S for the Customer Portal. This is the "pay" step of the core loop. **None of it is cut.** *(The D4 defer trims only the INV3-failed-charge auto-escalation orchestration around this clause — §3.3 — never the charge execution.)*

**Clause 2 — Supplier-side settlement. The settlement *recording* is KEPT (the seam); the *engine* is DEFERRED → operator-run (D19).**
Module E **records** every settlement-input event — the **E-emitted** `SupplierPaymentCompleted` (R4, §5.9), Module D's `InboundEventCostFinalized` + cost events, Module S's `DiscoveryRevenueShareAccrued` (OC accrual) + `RefundIssued` (cause-tagged) + `DiscoveryRevenueShareReversed` stream, the per-purchase OC accrual — and routes each to Xero in real time. The **settlement engine** (the quarterly per-Producer/Supplier runs, the `ProducerSettlementStatementIssued` 5-section composition, the OC 5% aggregation, the clawback netting, the counterparty disambiguation routing, the producer settlement-currency option, the settlement-statement FSM, the Xero AP routing) is **deferred to operator-run for the first cycle(s)** — the first quarterly close lands months post-launch. At launch the operator composes the first statement(s) manually from the recorded events + runs Xero AP manually; the engine reads the same recorded events when it is built. **The recording is the seam — verified whole, not dropped** (§4).

**Clause 3 — Refund + chargeback + non-revenue cost execution + recording. KEPT (floor-adjacent); chargeback automation KEPT (D21).**
Module E executes the Airwallex refund on Module S `RefundRequested` (DEC-165, refund-at-original-FX + credit-note discipline + OC-reversal symmetry); records the unified `NonRevenueCostRecorded` wrapper for replacement / custody breakage / transit damage+loss+write-off / insurance recovery (DEC-167); consumes Airwallex chargeback webhooks **automated from day 1** (DEC-168, D21 KEPT — Paolo override). **None cut.** (§5, §6.)

**Clause 4 — Xero + Airwallex + SDI integration boundary + multi-currency dual-record. FLOOR (D18) + the boundary KEPT; SDI already-deferred (D20).**
Module E is the sole NewCo-side integration point with Airwallex (DEC-014) and Xero (DEC-028). Real-time per-event Xero sync + post-sync immutability + reversal-ordering (§7.1). Multi-currency dual-record + per-leg FX rate-lock + refund-at-original-FX + `FXVarianceRecorded` (DEC-169, D18, **FLOOR — not a candidate**). SDI connector deferred (DEC-171/EXT-2 — D20, already-deferred; the architectural principle [E → Xero only; SDI XML downstream of Xero] holds as a jurisdiction-agnostic working assumption). (§7.)

### §1.3 Module boundary (the 20-item "does NOT do")

Module E does NOT take positions on accounting policy (Xero, DEC-072); does NOT generate financial documents (Xero is the Document Generator, DEC-028); does NOT own customer-facing invoice issuance / storage-fee computation / Voucher-activation gating (Module S, DEC-119); does NOT own Voucher/Order/Allocation/Hold state machines (Module S / Module A / Module K); does NOT own the credit-balance entity (Module K); does NOT touch NFT/wallet (Module B — no Module E NFT touchpoint, DEC-014); does NOT generate SDI XML (downstream of Xero). The full 20-item boundary statement is at §10. **These deliberate silences keep Module E neutral to every upstream cut** (the NFT-decouple = B; the settlement-defer is its own; storage = S; Hold = K).

### §1.4 Personas (informational — L-PP / P2: zero producer writes, zero consumer self-serve writes)

Module E is **back-office finance.** Every ops surface is operator-driven via the Admin Panel (the literal authority-tier policy is downstream, `feedback_prd_rr_approval`):

- **Finance Manager** — admin-configurable thresholds (dunning cadences, retry windows, FX buffer percentage, refund-compensation premiums, Hold-lift authority); Xero sync exception management; **the operator-run settlement composition + Xero AP (D19 — now load-bearing at launch).**
- **Finance Analyst** — manual reconciliation of bank-transfer payments without auto-match (DEC-159 operator-fallback); chargeback dispute-evidence preparation (DEC-168 step 4 — operator by spec); FX-variance review; **manual INV3 dunning / K-Hold placement on the first storage cycle (D4 — manual-first).**
- **Operations** — chargeback dispute response per the DEC-047 7-BD SLA (the auto-ingestion + Hold trigger are automated, D21; step 4 is operator-driven).
- **Customer-Service Operator** — read-only views over Module E events (payment / refund / Hold-status queries); remediation actions are cross-module.

The consumer sees only the **outcome** — charges, refunds, and the Xero-hosted invoice link surfaced via Module S / the Consumer Portal. **No producer write, no consumer self-serve write — there is no write UI to defer, so no backend capability is cut** (the cleanest L-PP, §5.11). The D4/D19 defers *increase* operator load, so the Admin-Panel finance surfaces are **more** load-bearing at launch (D24).

---

## §2 Customer-Facing Invoice Typology at Launch (DEC-157) — FLOOR (tax-correct invoicing recording)

### §2.1 Locked typology — INV1 + INV2 + INV3 only

Customer-facing invoice typology at NewCo launch = **INV1 + INV2 + INV3 only** (three types, DEC-157). All three are **Module-S-emitted** (DEC-119); Module E consumes for Xero routing + Airwallex charge execution per the uniform pattern at §3. **This is the tax-correct-invoicing-recording floor under the MPV VAT regime** (plan §3 / kickoff §4) — already maximally lean (the v17 7-type set was collapsed to 3); **no change.**

- **INV1.** Commerce invoice at checkout / post-payment-cleared (DEC-107/112/119). Carries bottle/Voucher amount only; **no excise + no destination-VAT** (MPV regime defers VAT to redemption). Module S trigger = order confirmation post-payment-cleared. Module E consumes for Xero routing + Airwallex charge execution (card DEC-158 / bank-transfer DEC-159).
- **INV2.** Fulfilment invoice at shipment (DEC-107/119). Carries excise pass-through + destination-country VAT + shipping fee + any unbilled storage months (mid-semester roll-in, Module-S-internal). Module S trigger = `ShipmentDispatched` consumption from Module C. Module E consumes for Xero routing + Airwallex charge against the saved payment method (DEC-158). *(Module C contributes the INV2 excise via `ExciseCalculated` — Module S composes the line; Module E records + charges + routes; Xero decides GL — DEC-072.)*
- **INV3.** Storage-fee invoice on the semi-annual cadence (DEC-118/119 — end-June + end-December covering the prior 6-month period). Module S owns issuance + storage-fee computation + per-bottle accrual + mid-semester roll-in (Module-S-internal). Module E consumes `InvoiceINV3Issued` for Xero routing + Airwallex charge against the saved card; **the failed-charge escalation orchestration is manual-first at launch (D4, §3.3).**

### §2.2 Out-of-launch invoice / financial-event types (already-deferred — carry verbatim)

Out at NewCo launch, each with a clean re-introduction hook (carry verbatim to §11 / roadmap; **do not re-cut**): **membership-fee INV0** (Hero fires INV1 like any other purchase, DEC-007/114); **proforma INV-P** (bank-transfer uses the PENDING_PAYMENT Order state, DEC-101/159 — no proforma); **paid-services INV4 + EVENT_CONSUMPTION_SETTLEMENT** (DEC-171/Q-OQ-19); **B2B combined INV1_INV2_COMBINED** (no B2B, DEC-017/068); **active-consignment SELL_THROUGH_SETTLEMENT + EVENT_CONSUMPTION_SETTLEMENT** (no active consignment, DEC-011 — the launch per-Producer/Supplier `SellThroughSettled` for *passive* consignment is a different event with a different shape).

### §2.3 Cross-module surfaces

INV1/INV2/INV3 issuance triggers + composition mechanics live in Module S (§16.9 customer-facing invoice events; §11 Voucher state machine; §14 storage-fee computation + INV3 issuance; §10.6/§10.7 INV1/INV2 composition). This PRD references those sections (DEC-074 + §9); does not restate.

---

## §3 Customer-Side Payment Execution (Airwallex) — FLOOR (Clause 1) + the D4 arm

Module E owns the AirwallexAdapter (DEC-014/158) under a Provider-Agnostic Adapter Pattern (the same adapter contract for forward charges, refunds, chargeback ingestion, saved-card capture). The four customer-side flows differ on issuance trigger + charge mechanics; the Xero-routing + document-generation-routing tail is identical across all three invoice types per the three-actor split (§1.1). **All of §3 is FLOOR except the §3.3 INV3 auto-escalation orchestration (D4 — manual-first).**

### §3.1 Card payment flow — INV1 (DEC-158) — FLOOR

Cards authorize-and-capture in one step (no PENDING_PAYMENT intermediate state for cards):

1. **Cart submit (Module S).** Customer finalizes cart; Module S calls Module E's payment-execution service with order context (Customer ref, total, currency, line items, `payment_method = card`).
2. **Airwallex authorize+capture (Module E).** Module E creates the Payment record (status `pending`); the AirwallexAdapter invokes authorize-and-capture in one step; Airwallex confirms `charge.succeeded` / `charge.failed`; Module E updates the Payment record + emits `AirwallexChargeExecuted` / `AirwallexChargeFailed`.
3. **Confirmation back to Module S.** Module E confirms payment-cleared.
4. **INV1 issuance (Module S).** On payment-cleared, Module S transitions Order → CONFIRMED + Voucher → ISSUED + emits `InvoiceINV1Issued`.
5. **Xero routing — accounting + document generation (Module E).** Module E consumes the just-emitted `InvoiceINV1Issued`; routes the financial-event payload to Xero for accounting (DEC-072) + routes the document-generation request to Xero (DEC-028 — Xero is the Document Generator); Xero returns a document URL; Module E confirms back to Module S; the Customer Portal surfaces the Xero-hosted document. **The five-event Xero-routing tail is the three-actor split in action.**

*Tech downstream (DEC-073): the literal AirwallexAdapter API contract (3-D Secure/SCA/PSD2, retry, webhook-signature, idempotency, key rotation). GL downstream (DEC-072): how Xero treats the financial event.*

### §3.2 Bank-transfer flow — INV1 with PENDING_PAYMENT pre-state (DEC-159) — FLOOR

Bank transfer uses the PENDING_PAYMENT Order state (no proforma; INV-P dropped):

1. **Cart submit `payment_method = bank_transfer` (Module S).** Order in PENDING_PAYMENT; Cart Hold extends to 7 calendar days (SEPA same-region 1–2 days + SWIFT cross-region 3–5 days); Module S surfaces Airwallex IBAN/SWIFT instructions; Voucher PENDING_PAYMENT pre-state.
2. **Customer initiates wire externally.** Outside the system.
3. **Airwallex `transfer.received` webhook → Module E.** Module E validates + matches the Order via the transfer-reference identifier.
4. **Module E emits `BankTransferFundsCleared` → Module S.** Module S transitions Order PENDING_PAYMENT → CONFIRMED + Voucher → ISSUED + emits `InvoiceINV1Issued`.
5. **Xero routing (Module E).** Per the §3.1 step-5 pattern.

**Operator-fallback path (Admin Panel — Finance Analyst).** For edge cases (webhook fails, transfer reaches Airwallex without auto-matching), the Admin Panel exposes a manual-reconciliation surface; on operator-confirmed match, Module E emits `BankTransferFundsCleared` exactly as in step 4. **This is the L-PP ops surface — already operator-driven by spec; no cut** (§5.11). Surface UX downstream (DEC-073).

**7-day expiry (Module S).** No funds-cleared event within 7 days → Module S auto-VOIDs the Voucher + releases the Allocation reservation; no INV1; no Module E financial event.

### §3.3 INV2 + INV3 saved-card flow + the INV3 failed-charge escalation — D4 DEFER (manual first cycle); the charge itself FLOOR

INV2 + INV3 follow the **post-Module-S-emission** sequence (Module S emits the invoice event first; Module E consumes; Module E charges the saved card; Module E routes to Xero):

1. **Module S emits `InvoiceINV2Issued` (trigger = Module C `ShipmentDispatched`) or `InvoiceINV3Issued` (trigger = the semi-annual cadence).**
2. **Module E consumes.** Reads the Customer's Airwallex-stored saved card.
3. **AirwallexAdapter executes the charge.** On confirmation Module E updates the Payment record + emits `AirwallexChargeExecuted` / `AirwallexChargeFailed`.
4. **Xero routing.** Per the §3.1 step-5 pattern.

**The saved-card charge itself is FLOOR — KEPT** (store at checkout, charge at shipment/storage cadence; INV2 fires at shipment, which happens at launch).

> **The INV3-failed-charge 3-stage auto-escalation orchestration is DEFERRED → manual first cycle (D4; ratified Q2 "ok because is months away").** The first INV3 storage-billing cycle lands **months post-launch** (storage accrues only after the 12-month-free + bottle-at-warehouse double anchor; INV3 fires semi-annually end-Jun/end-Dec), so there is no dunning cycle to automate for months. **The automated retry/dunning orchestration is operator-handled at the first cycle(s).** What this means concretely (the chain + thresholds retained as the seam; the operator drives the steps):

- **Stage 1 — retry within the 14-day window.** *(At launch:)* the operator monitors the failed INV3 charge in the Admin Panel; the saved-card re-charge + the payment-reminder (money mail — ERP-sent via the email service, Module K §14.9.1 / MVP-DEC-035) are operator-triggered. *(The DEC-160 Airwallex built-in retry + the auto-`AirwallexChargeFailed`-per-attempt audit + the auto-reminder are the deferred automation; the retry counter + the per-invoice threshold are retained.)* No Hold; no Suspension.
- **Stage 2 — `StoragePaymentFailed` → Module K Hold.** *(At launch:)* after the retry window the operator places the K Hold manually (or operator-triggers `StoragePaymentFailed`); Module K creates the Hold `hold_type = STORAGE_PAYMENT_FAILED` (blocks NEW orders + NEW shipping; in-progress fulfilment unaffected). *(The auto-emission of `StoragePaymentFailed` at Day-14 default is the deferred automation; the event + the Hold contract are retained.)*
- **Stage 3 — Profile Suspension.** *(At launch:)* if the Hold persists past the grace period (default 30 days), the operator drives the Module K Suspension. *(The auto-Suspension-after-grace + the multi-cycle composition [parallel chains, per-cycle Hold-lift, strongest-Suspension-wins] are the deferred automation.)*

**Seam (P1):** the **`StoragePaymentFailed` → K-Hold (`STORAGE_PAYMENT_FAILED`) → Profile-Suspension event chain** + the **admin-configurable staged thresholds** + the multi-cycle rules are all **retained** — the operator drives them manually at the first cycle(s); re-enabling the automated orchestration is **purely additive.** Module K's side is unchanged (**N2 — its Hold registry is trigger-agnostic**: the Hold types + registry stay whether the trigger is automated or operator-placed; ratified K-side at Module K §15.8).

**Hold-lift on remediation (Module E → Module K).** When the Customer remediates (updates saved card → Module E charges, OR pays via bank transfer via the §3.2 operator-fallback), Module E emits `StoragePaymentSucceeded`; Module K lifts the Hold; Suspended Profiles transition back (operator review). **`StoragePaymentSucceeded` for a given cycle lifts only that cycle's Hold** (per-cycle Hold-lift, no aggregate lift). **The Hold-no-auto-lift discipline is FLOOR — KEPT:** write-off (recording bad debt) does NOT auto-lift the credit Hold — lift requires explicit operator action with an auditable reason.

**Sanctions/Hold uniformity at INV3 charge execution (DEC-181) — FLOOR, never deferred.** INV3 charge execution is a transaction-initiation surface; Module E re-reads sanctions + Hold state at the moment of each charge attempt. **Storage accrual continues unconditionally** (storage is bottle-in-custody, not customer-state-dependent — INV3 cadence fires regardless of Hold), **but charge execution is gated.** Only the *auto-escalation orchestration* defers (D4); the compliance gate at charge is floor.

### §3.4 Hero Package payment flow (DEC-007/114/157) — KEEP (club VP)

Hero Package = membership purchase. Fires INV1 like any other purchase (**no separate INV0** — DEC-157). **The charge is triggered by producer approval** (the joining charge — Module K §4.2.1) against the **charge-on-approval mandate / saved card captured at application**, or by the auto-renew cycle (renewal); it is **not** a separate post-approval consumer checkout (MVP-DEC-016). Module E executes it via the **card one-step authorize+capture path (§3.1, DEC-101/158)**; the **`bank_transfer` PENDING_PAYMENT path (§3.2) is not a membership method** — a push transfer cannot be auto-charged on approval, so the Hero Package fee requires a **pull-capable instrument (card or SEPA Direct Debit mandate)**. Module E charges + routes to Xero; **Module S emits `MembershipFeePaid`** (→ Module K auto-issues Club Credit if `Club.generates_credit = true`); **Module E records the `MembershipFeePaid` financial event** (DEC-173). No separate membership-billing flow; renewal follows the same pattern (Module-K + Module-S scope).

### §3.5 Pickup-at-warehouse / immediate-ship INV1 + INV2 collapse (BMD §8.7) — KEEP

For pickup/immediate-ship orders where INV1 + INV2 collapse, Module S emits INV1 then INV2 as separate events; Module E consumes each in sequence + routes each to Xero independently. **Xero decides any aggregation/reconciliation treatment** (DEC-072). Cheap (sequence-independent recording); no Module E batching.

---

## §4 Producer / Supplier-Side Settlement (DEC-156/161/162/163/164) — the D19 DEFER (the #3 lever); the recording is the seam

> **D19 — DEFER the supplier-settlement *engine* → operator-run first cycle(s) (ratified Q1; the headline in-module defer + the #3-heaviest lever overall).** The settlement *runs*, the 5-section statement *composition*, the OC 5% *aggregation*, the clawback *netting*, the counterparty *disambiguation routing*, the producer settlement-currency option, the settlement-statement *FSM*, and the Xero AP *routing* — all defer. **Why safely deferrable:** the settlement cadence is quarterly, and **the first quarterly close lands months post-launch** (a full quarter of sell-through must accumulate before any statement is due), so there is *no* settlement run to automate for months. **The recording is the seam (§4.7) — verified whole, not dropped.** At launch the operator composes the first statement(s) manually from the recorded events + runs Xero AP manually (the Admin-Panel finance-ops console, §5.11); the engine reads the same recorded events when it is built — purely additive. **Honest weight: this is genuinely the heaviest in-module defer after Module B's D12 — intricate Module-E settlement mechanics feeding Xero under strict immutability/credit-note discipline — but the structural reason it is safe (first close months out + recording = seam) holds.** This section describes the engine the deferred build will implement; **every payload it composes from is recorded at launch.**

### §4.1 Settlement-counterparty disambiguation rule (DEC-161) — the routing defers; the capture is the seam

Per DEC-161, every settlement event carries the **counterparty resolved per its nature** (no generic "settlement counterparty"):

- **Sell-through settlement** — the `SellThroughSettled` event carries `supplier_party_id` = the **PO counterparty** (Supplier; DEC-082's "Producer ≠ Supplier" governs — in the common Discovery-with-Supplier-not-Producer pattern the Supplier ≠ the bottle's Producer). Aggregated per Supplier per period.
- **OC 5% × P_d share** — the `OCShareAccrued` event carries `originating_club_producer_party_id` = the **buyer's OC Producer** (resolved via `Customer.originating_club_id → Club.partner_producer_id`, DEC-066). **The bottle's Producer is irrelevant to OC routing — Paolo's canonical rule.** Aggregated per OC Producer per period → Section D.

**The disambiguation *routing* (which statement an event lands on) is part of the deferred engine (D19).** **The seam:** the event payloads **capture both party references at emission** (the unreconstructable data — read from Module A's two-FK lineage `producer_id`/`supplier_id` [A §3.1] + K's OC lock [K.13]); the *aggregation-into-statements* defers. The Producer-as-Supplier collapse case (bottle's Producer = OC Producer = Supplier → one statement) and the Discovery-with-Supplier-not-Producer case (two statements per period) are resolved when the engine is built — reading the recorded captures.

### §4.2 OC 5% × P_d — the *accrual recording* is whole at launch; the *5% aggregation + Section D* defer (DEC-162/180) — Phase C item E (seam-critical)

> **The capture is whole at launch; only the computation/settlement defers (ratified Q5; Phase C item E — confirmed whole).** Module S emits `DiscoveryRevenueShareAccrued` at **INV1** (reading K's `OriginatingClubLocked` + A's per-constituent lineage at that one-shot, unreconstructable moment; ratified S §16.7 KEEP). **Module E records the accrual at launch** — the per-purchase data the future engine needs. **If the accrual were not recorded at INV1 it could not be reconstructed — but it is recorded (S emits, E records, both ratified KEEP).**

The **5% aggregation into Section D + the settlement** defer with the engine (D19) — Module E **computes the 5% + composes Section D when the engine is built, reading K's OC lock + A's lineage, NOT re-deriving.** The composite OC-on-`P_d` defers with **D7** (Module S); single-Allocation Discovery OC emission is KEPT.

**Section D info-disclosure constraint (DEC-180 — preserved on the recorded accrual payload, a producer-trust/compliance discipline KEPT at launch).** The recorded `OCShareAccrued` payload is **aggregate-only**: share amount + anonymized transaction reference + period anchors; **NO per-purchase-buyer detail** (Producer X cannot see that buyer A purchased); **NO bottle's-Producer identity disclosure** (cannot reveal which other Producers' bottles drove the accrual). The constraint is enforced at Module E event-payload composition — at the recording moment, not a downstream filter. Full per-purchase detail is shared **only** for own-club sales (Section A — where the Producer is the bottle's Producer AND the buyer is their member).

### §4.3 Sell-through routing + Direct Purchase — recording KEPT (seam); aggregation deferred (D19); Direct-Purchase arm IDLES (item I)

The `SellThroughSettled` event recording is the seam (the future engine aggregates it). **V1 + V2** (passive consignment) → quarterly aggregation (monetary, per `commercial_terms`); the discriminator is `Allocation.sourcing_model` (read from Module A, `passive_v1 | passive_v2 | direct_purchase`). **Direct Purchase is deferred (A/D ratified, Phase C item I)** → E's Direct-Purchase immediate-Xero routing + the Section E informational rows **idle at launch** (not-exercised; the `sourcing_model` discriminator + the routing are **retained as the seam** — re-enable is additive when a deal needs Direct Purchase). The V1/V2 sell-through recording is the launch surface; **the quarterly aggregation defers (D19).**

### §4.4 `ProducerSettlementStatementIssued` — five-section shape (DEC-156) — DEFERRED (operator-run first cycle); the recording is the seam

The statement composition (one per Producer per period; default quarterly per DEC-042, per-Producer override per DEC-070 read from Module K) carries **five sections**: **A** per-Club sell-through (own-club, buyer-identity disclosed); **B** Discovery sell-through (aggregate-only, no buyer identity); **C** refunds + clawbacks netted; **D** OC shares (aggregate-only, the §4.2 discipline); **E** Direct-Purchase informational reconciliation rows (idle at launch — item I). The Producer issues one invoice-back-to-NewCo per period (net-30).

**The composition + the statement FSM (`accruing → composing → issued → settled`) + the Xero AP routing DEFER → operator-run (D19).** At launch the operator composes the first statement(s) manually from the recorded settlement-input events (§4.7) and runs Xero AP manually (the finance-ops console, §5.11). The `settled` transition is Xero-side either way (Xero owns AP-payment treatment, DEC-072). Section-rendering UX, line-item field lists, document numbering, PDF layout = downstream + Xero scope (DEC-073/028).

### §4.5 Refund clawback netting (Section C, DEC-164/025) — the cause-tagged recording is the seam; the netting defers (D19)

Module E **records** the `RefundExecuted` events with the `refund_cause` discriminator at launch (the seam — so the future engine can net by cause + original-sale period). The `refund_cause` taxonomy (read from Module S's `RefundRequested` — Module E does NOT classify): `producer_fault` / `newco_fault` / `carrier_fault` / `customer_cancellation_pre_shipment` / `customer_fraud` (DEC-025).

**The producer-fault clawback netting computation defers with the engine (D19; ratified S-side S.26b).** When the engine is built: `producer_fault` → a deduction line in Section A/B (cross-period clawback indexed by the original sale's settlement period; net = accruals − reversals); the other four → no producer clawback, NewCo absorbs as `NonRevenueCostRecorded` (§5.4). At launch, refunds are executed (§5.1) + recorded with cause; the netting against producer statements happens when the engine is built. **The clawback-cause taxonomy + the `DiscoveryRevenueShareReversed` reversals (DEC-182) + the cause-tagged refund payloads are the recorded seam.** *No accounting position on whether Xero treats a clawback as a prior-period reversal or a current-period adjustment (DEC-072).*

### §4.6 Producer settlement-currency option (DEC-169) — DEFERRED (with the engine)

EUR default; a producer may opt for their currency at the ProducerAgreement layer (Module K). The statement carries dual-currency + the snapshot `fx_rate`/`fx_rate_date`. **Part of the deferred statement composition (D19).** Seam: the ProducerAgreement currency preference (Module K) + the dual-record machinery (§7.2, KEPT) are retained; the statement-currency application defers with the engine.

### §4.7 The settlement-input recording seam (the heart of the D19 defer — verified whole)

**This is the seam that must NOT be dropped.** Module E keeps **recording** — at launch, in real time, routed to Xero — every event the deferred engine will aggregate:

| Settlement-input event | Source | Module E recording at launch |
|---|---|---|
| **`SupplierPaymentCompleted`** | **Module E emits it** (R4, §5.9 — the payment executor) | Recorded + routed to Xero per `sourcing_model`; the supplier-EUR FX leg locks here (§7.2). |
| `InboundEventCostFinalized` + landed-cost categories | Module D (D-emitted, ratified KEEP) | Recorded; `COGSAdjustmentRecorded` on provisional→finalized flip (§5.7). |
| `DiscoveryRevenueShareAccrued` (the OC accrual) | Module S (ratified KEEP, at INV1) | Recorded as `OCShareAccrued`, Section-D info-disclosure preserved (§4.2). |
| `RefundIssued` (cause-tagged) / `DiscoveryRevenueShareReversed` | Module S (ratified KEEP) | Recorded as `RefundExecuted` + reversal; the netting seam (§4.5). |
| `NonRevenueCostRecorded` triggers (replacement / damages / insurance) | Module C / Module B (ratified KEEP) | Recorded as the unified wrapper (§5.4). |
| `InventoryAdjusted` + cost-basis-at-dispatch | Module B (ratified KEEP) | Recorded (§5.6, §5.7). |

**All seven upstreams are ratified KEEP-in-full / cut-with-floor-whole — no Module E recording is orphaned** (the B-side D16 *automation* defer doesn't orphan E: B's `InventoryAdjusted` events still fire, only the auto-cascade defers, so E still consumes them). At launch the operator composes the first statement(s) from these recorded events; the engine aggregates the same records post-launch. **The defer is the *engine*, not the *recording*.**

---

## §5 Refund + Credit + Non-Revenue Cost (DEC-165/166/167) — KEEP (floor-adjacent)

### §5.1 Refund execution — Module S emits, Module E executes (DEC-165) — FLOOR-adjacent (the mechanism + credit-note discipline)

Module S emits the refund event; Module E executes the Airwallex refund. The five-step flow: (1) Module S emits `RefundRequested` (Voucher + Order ref + `refund_cause` + amount + currency); (2) Module E reads the original Payment record + calls the Airwallex refund API + emits `RefundExecuted`; (3) Module E confirms refund-completed to Module S (Order state + the customer-notification trigger — the refund notice is money mail, ERP-sent via the email service, Module K §14.9.1); (4) Module E routes `RefundExecuted` to Xero (§7.1); (5) the producer-fault clawback aggregation defers with the engine (§4.5).

**Refund-at-original-FX (data-integrity, D18).** Refund currency = the original payment currency at the **original captured FX rate** — **no fresh snapshot at refund time.** The original Payment record is read at refund time; `RefundExecuted` carries the original `fx_rate`/`fx_rate_date`. (§7.2.)

**Credit-note discipline (single payment path) — FLOOR (audit/immutability).** All refunds flow through credit-note discipline at the Xero layer — **no direct-refund-to-payment-method path outside credit-note generation.** Module E records `RefundExecuted` + routes the credit-note generation request to Xero (the standard accounting + document-generation routing); Xero generates the credit note (Xero is the Document Generator).

**Sanctions/Hold uniformity at refund routing (DEC-181) — FLOOR.** Refund routing is a transaction-initiation surface; Module E reads sanctions/Hold at refund-execution time. An active **sanctions** failure on the recipient blocks refund execution (operator review). Active **non-regulatory Holds** (`payment`, `fraud`, …) do **not** block refunds (refund-to-original-payment-method is a customer-protection path).

**OC-reversal symmetry (DEC-182).** When a Discovery refund triggers `RefundRequested`, Module S also emits the OC-reversal mirror `DiscoveryRevenueShareReversed`; Module E records the reversal into the (deferred) producer-clawback aggregation + the Section D running balance (net = accruals − reversals). Replacement (Module C `ReplacementShipmentIssued` → Module S → Module E) follows the same symmetry — replacement does not preserve OC binding from the original; reversal + fresh accrual fire on replacement composition.

**Refund cause coverage** (the execution is identical across causes): 14-day pre-shipment cancellation (DEC-108); supervisor-override post-delivery refund (rare, auditable; Module S §12.3 owns the trigger; Module E executes identically); Module C-emitted loss triggers routed via Module S `RefundRequested`. *(The DEC-025 cause-routing decisioning is Module-S-side manual-first at launch — D6; Module E consumes the cause classification, never derives it.)*

### §5.2 Refund-cost matrix routing (DEC-025/164) — Module E consumes the cause; does NOT classify

The `refund_cause` discriminator on `RefundRequested` drives the (deferred) clawback routing (§4.5). **Module E does NOT classify the cause** — it consumes the classification from Module S's payload (Module S decides the cause at trigger time, D6 manual-first). The cause-classification logic upstream is Module S scope (DEC-073).

### §5.3 Store credit + Club Credit recording (DEC-166) — KEEP (the financial-event side; the entity at Module K)

**Module K owns the credit-balance entity** (Club Credit at the Customer-Profile level + store credit at the Customer level); **Module E records the credit-issuance + credit-application financial events for Xero.** Credit (both kinds) is a **prepayment instrument, not a discount** — INV1 issues at the full sale price; credit applies as a payment-method-equivalent against AR (Xero decides GL, DEC-072).

**Module E emits the financial accrual / reversal events** (DEC-166 + DEC-174 three-actor split, reconciled at MVP-DEC-018): `ClubCreditAccrued` (creation) / `ClubCreditRestored` (cancellation-window restoration) / `ClubCreditForfeited` (lapse / replacement / cancellation) + the store-credit analog `StoreCreditIssued`. The **customer-facing application events** (`ClubCreditAutoApplied` / `ClubCreditRemovedByCustomer` / `StoreCreditApplied`) are **Module-S-emitted** at checkout-render / customer action (DEC-111); Module E **consumes** them to route the prepayment offset to Xero (§8.2). Module K consumes from BOTH Module S and Module E to record the balance state on its own entity. **Module E does NOT own the credit-balance entity** — it reads via the Module K API for event-payload context (issuer, applied-amount, remaining-balance).

**The K.18/K.19 paths simply don't fire at launch (Phase C item D, ratified):** S ratified KEEP carry-forward (K.17), DEFER welcome-window scaling (K.18 — launch = full-fee→full-credit) + manual issuance (K.19 — goodwill via the single REFUND_COMPENSATION coupon). So at launch E records what fires; **no Module-E cut.** The mutual-exclusivity matrix is enforced at Module S checkout; Module E records the resulting credit-application events. *(Forfeiture is recorded by Module E as `ClubCreditForfeited`; Module K consumes — at most one per Club-Credit lifetime.)*

### §5.4 Non-revenue cost wrapper — `NonRevenueCostRecorded` (DEC-167) — KEEP

Module E emits a unified `NonRevenueCostRecorded` wrapper financial event for each upstream non-revenue trigger — a uniform Xero-routing surface independent of which upstream module emitted the trigger, preserving the **financial-event-record immutability discipline** (§7.1). The wrapper carries: a `cost_cause` discriminator; the upstream event reference; the cost basis (resolved at recording time — Module E has native access via the **Product Reference** / SerializedBottle reference + the allocation lineage); the allocation lineage *(GENERALISE — `Bottle Reference → Product Reference` in this lineage payload, §12)*; currency + dual-currency (§7.2).

**Per-trigger mapping** (consuming the ratified Module C + Module B events — the operational-event seam, C records the operational event, E records the financial event, Xero decides GL):

- **Replacement shipment (Module C `ReplacementShipmentIssued`, DEC-138/184).** Cost = the **substitute** Allocation's bottle cost + replacement shipping. The **original** Allocation's bottle cost is recorded as a **separate write-off** — margin reconciliation reads **two distinct financial events** (NonRevenueCost on the substitute + write-off on the original) plus an optional `InsurancePoolPayment`, **not a conflated single wrapper.** No new INV2 fires (the original Voucher's commercial state is preserved, DEC-138); Module E records the operational cost event only. **(The DEC-182 OC-reversal-mirror fires here — the Module E seam; §5.1.)**
- **Custody breakage (Module B `BottleBreakageInCustody`, DEC-132).** Cost = bottle cost basis.
- **Transit breakage / transit loss / write-off (Module C `BottleBreakageInTransit` / `BottleLossInTransit` / `BottleWriteOff`, DEC-151).** Cost = bottle cost basis; carrier-insurance recovery flows back as a separate `InsuranceRecoveryReceived` (a **net-back, not a synchronous offset**).
- **Insurance recovery (Module C `InsuranceClaimResolved`, DEC-151/048).** Module E records `InsuranceRecoveryReceived` (net negative cost); the **`insurance_pool ∈ {carrier, newco_supplementary}`** metadata (captured on `InsuranceClaimOpened`, Module C) routes the recovery; **Xero offsets the prior `NonRevenueCostRecorded`** per its policy (DEC-072 — this PRD takes no offset position).

**No NFT touchpoint (DEC-014).** The wrapper carries Module B's `BottleId` / SerializedBottle reference for traceability but **no on-chain data** (Module B scope, DEC-122/124/131; D12 decoupled). Module E has no NFT mint/burn/recovery flow.

*Tech downstream (DEC-073): the cost-basis-resolution algorithm + the replacement-shipping compositing mechanics. GL downstream (DEC-072): COGS-vs-OpEx classification, matching-period, impairment, warranty-reserve.*

### §5.5 Refund / credit / non-revenue boundaries

The three streams are strictly partitioned: **refunds** (`RefundExecuted` — cash to the customer's original method); **credits** (`ClubCredit*` / `StoreCredit*` — non-cash prepayment); **non-revenue costs** (`NonRevenueCostRecorded` / `InsuranceRecoveryReceived` — operational costs, never returned to a customer). A refund may compose with a credit issuance (refund at 100% face value OR opt-in to store-credit at the 105% admin-configurable goodwill premium — the cross-stream choice is Module S scope, D6 manual-first). Module E records each event independently; Xero decides aggregation (DEC-072).

### §5.6 Module B inventory-adjustment financial-event ingestion (DEC-190) — KEEP

Module E consumes Module B's `InventoryAdjusted` for damage / loss / recount / found financial-event recording, mapping Module B's `adjustment_type` to a `cost_cause` discriminator extension on the §5.4 wrapper: `damage → custody_breakage_per_DEC-132`; `loss → custody_loss_per_DEC-190`; `recount → recount_variance_per_DEC-190`; `found → inventory_found_per_DEC-190` (a positive cost-recovery cost-cause netting a prior `loss`). `consumption` + `transfer` are Phase-2+ placeholders (no events at launch). The cost-cause extensions compose with the §5.4 wrapper without changing its structure. Cost basis is read from the affected InboundBatch (provisional or finalized, whichever is current). **`InventoryShortfallDetected` is NOT consumed directly** — the shortfall short-circuit is a Module-A-side workflow; Module E records the eventual financial event only downstream of the resolution (refund → §5.1 / replacement → §5.4). **The B-side D16 automation defer doesn't orphan E** — B's `InventoryAdjusted` events still fire (the adjustment workflow is kept; only the auto-cascade defers).

### §5.7 Cost-basis-at-dispatch read (DEC-195) — KEEP

Module E reads the InboundBatch cost basis at dispatch via the `inventory_cost_basis` payload on the Module C dispatch event chain (Module C reads the bound bottle's source InboundBatch at dispatch → fires `ShipmentDispatched` carrying `inventory_cost_basis` + `cost_basis_provisional` → Module S emits `VoucherShipped` + `InvoiceINV2Issued` → Module E routes to Xero with the cost-basis attribute). The cost basis is **provisional** at PHYSICALLY_ACCEPTED and **finalized** when Module D's `InboundEventCostFinalized` fires; Module E records whichever is current at dispatch, and a downstream **`COGSAdjustmentRecorded`** fires when the basis flips provisional → finalized for already-shipped bottles (carrying the InboundBatch ref + prior/new cost + affected qty + dispatch event ref). **Rare at NewCo** (V2 default — cost is known at allocation activation; the flip typically completes pre-dispatch); primarily relevant for V1 + Direct-Purchase paths. *No GL position on capitalisation/COGS timing (DEC-072).*

### §5.8 Bottle-days storage-fee data flow (DEC-118/119) — three-actor (KEEP)

The storage-fee data flow is three-actor: **Module B owns the data** (bottle-days-in-storage per allocation lineage; no storage-fee pricing, no PII); **Module S owns the computation + customer-identity join + INV3 issuance** (DEC-119); **Module E owns the financial-event recording + Xero routing** (consumes `InvoiceINV3Issued` → routes to Xero + charges the saved card, §3.3). Module E's role on INV3 is unchanged at the v1.1 layer (the D4 defer is only the *auto-escalation orchestration*, §3.3; the data flow is upstream). *No GL position on storage-fee classification (DEC-072).*

### §5.9 Ownership-transition trigger — `SupplierPaymentCompleted` — ⚠️ R4 (E-EMITS) + N3 + item F (Phase C §2-C / §5-R4)

> **⚠️ THE E-EMITS TRAP — landed here.** The cut-sheet (E.32 / §3.6 / Q6) and the v1.1 PRD's §8.2/§9.2/AC-E-J-37 framed `SupplierPaymentCompleted` as "**Module D** emits; Module E + Module B consume independently" (the dominant textual reading). **Phase C ratification (Paolo Q2) FLIPPED this to E-emits — and Phase C WINS.** Paolo's Q2 exposed that **Module D has no independent trigger** — it would wait on Module E's confirmation that the payment cleared. **Payment execution is Module E's** (the Airwallex/Xero rails, DEC-014/028; the three-actor split DEC-119 assigns PAYMENT to E; symmetric with the customer-side `AirwallexChargeExecuted`, which E emits). **The corrected contract is E-emits.** This PRD reconciles the v1.1 §9.6/§5.9 "E emits to B" prose **and** the §8.2/§9.2 "D emits" prose to a single coherent **E-emits / D+B-consume-independently** contract (§8.1 / §8.2 / §9.2 / §9.6). The cut-sheet stays as the Phase B record; **the v0.3-MVP PRD lands E-emits.**

**The corrected contract (the precise pin):**
- **Module E emits `SupplierPaymentCompleted`** — when the supplier payment clears/confirms (Module E is the payment executor). **At launch:** when the operator records the manual supplier payment in Module E's finance surface (settlement operator-run, D19 deferred). **Post-launch:** Module E's settlement engine. **Atomic per PO** (partial PO settlement deferred, OQ-20, §11). Module E routes the financial event to Xero per `Allocation.sourcing_model` (the §4.3 discriminator); the supplier-EUR FX leg locks here (§7.2).
- **Module D consumes it** → settle/advance/**close the PO** (Module D §6 — the CLOSED transition; Module D §14.7). Module D's *own* procurement financial events (`InboundEventCostFinalized`, `DiscrepancyResolutionRecorded`, `ConsignmentReceiptRecorded`, …) stay **D-emitted, unchanged** — only the *payment-completion* event is Module E's.
- **Module B consumes it** (independently) → the inventory **`ownership_flag` PRODUCER → NEWCO** transition (Module B §0.3/§2.2 — the bottle becomes NewCo-owned because NewCo has paid for it). **Module B does NOT emit it; Module E does.** No fresh Module E recording fires on B's downstream `OwnershipTransitioned` (Module E observes it for audit only — the financial event is the E-emitted `SupplierPaymentCompleted`).
- **Direct-Purchase no-op:** for `direct_purchase` the InboundBatch is `NEWCO` from creation → no PRODUCER→NEWCO transition (Module E's financial event still fires for the cost-out-the-door recording). Doubly moot at launch (Direct Purchase deferred, item I).

**N3 — two distinct ownership ledgers, same real-world party (Phase C §5-N3).** The system carries **two** PRODUCER→NewCo ownership ledgers, keyed to **different signals** at **different moments** — Module E's prose keeps them unambiguous so the two are never conflated:

| Ledger | Owner | Enum / flag | Transition keyed to |
|---|---|---|---|
| **Inventory `ownership_flag`** | **Module B** (consumer of the E-emitted event) | `ownership_flag` `PRODUCER → NEWCO` (DEC-185; `NEWCO` per MVP-DEC-028) | **`SupplierPaymentCompleted`** (E-emitted; the **payment** moment — R4) |
| **PO-level title** | **Module D** (consumer of the same E-emitted event) | `ownership` 3-value enum `PRODUCER \| NEWCO \| THIRD_PARTY` (DEC-085 — `NEWCO`) | the **sale/shipment signal** (`VoucherIssued` sell-through — Module D item F) |

The inventory `ownership_flag` (B) and the PO-level title (D) both read `NEWCO` — the **same party** (DEC-185 for the inventory flag, DEC-085 for the PO enum; the inventory flag was harmonized to the shared `NEWCO` label by MVP-DEC-028).

**Item F — the sale-vs-shipment title-timing nuance (Phase C item F — forwarded from Module S).** Module S resolved the event names: **`VoucherIssued` is the sell-through signal** driving Module D's PO PRODUCER→NEWCO title transition (there is **NO separate `SellThroughRecorded` event**); **`VoucherShipped` is available for a shipment-keyed title leg.** On Module E's side, the *financial* events anchor the per-leg FX rate-lock (§7.2): the supplier-EUR leg at `SupplierPaymentCompleted`, the customer leg at capture, the OC leg at INV1. **Take NO accounting position (DEC-072)** — whether the title-transfer timing drives a revenue/COGS treatment is a Xero/GL decision; the events are named, the precise keying is folded into the deferred settlement recording (D19).

### §5.10 Active-consignment SELL_THROUGH_SETTLEMENT carve-out (DEC-193/068) — already-deferred (carry verbatim)

The active-consignment SELL_THROUGH_SETTLEMENT + EVENT_CONSUMPTION_SETTLEMENT financial events are **OUT at launch** (B2C-only; no B2B Account; no `ConsignmentPlacementRecorded`/`ConsignmentSellThroughRecorded`). Stage-2+ recovery is a **coordinated tri-module restoration** (Module B active-consignment placement entity + Module C sell-through workflow + Module E SELL_THROUGH_SETTLEMENT financial event) anchored to DEC-193 — restoring one without the others would dangle. **Carry verbatim** (§11). *(Distinct from the launch per-Producer/Supplier `SellThroughSettled` for passive consignment.)*

---

## §6 Chargeback Flow (DEC-168) — D21 KEPT (Paolo override — payment automation from day 1)

> **D21 — chargeback ingestion KEPT, automated from day 1 (Paolo override of the locked dial; ratified Q3).** Drafted SIMPLIFY→manual; **KEPT at ratification.** The Airwallex integration is floor from day 1 (Clause 1 — customer charges/refunds cannot run without it), so the chargeback dispute webhook (`dispute.created`/`dispute.resolved`) rides the same integration cheaply; deferring it to manual dashboard-monitoring opens a **fraud/Hold-latency gap** (a chargeback not caught promptly = no Hold = the bad actor keeps transacting) for a marginal saving. Paolo's steer: **"payment automation should be KEPT."** The **full 5-step chargeback chain auto-ingestion is KEPT from day 1** (see [[keep-payment-automation]]).

### §6.1 The chargeback 5-step cross-module chain — automated (D21 KEPT)

Module E owns the chargeback flow as a 5-step cross-module chain (Module E decides + emits the trigger; **Module K records + manages the Hold lifecycle**; Module S + Module C enforce at their surfaces). The Hold-no-auto-lift discipline applies:

1. **Airwallex `dispute.created` webhook → Module E.** Module E validates + auto-records receipt. *(Automated.)*
2. **Module E records the financial loss + routes to Xero.** Emits `ChargebackReceived` + `ChargebackPotentialLoss` (provisional — loss pending resolution); both routed to Xero real-time (Xero decides bad-debt/contingent-liability treatment, DEC-072). *(Automated.)*
3. **Module E emits `CustomerChargebackFlagged` → Module K.** **Module K is the Hold registry-of-record:** it consumes the event + creates a Hold `hold_type = CHARGEBACK_REVIEW` (blocks NEW orders + NEW shipping; in-progress fulfilment unaffected) + flags the Customer for fraud-pattern review. **Module E does NOT create the Hold directly.** *(Automated; the chargeback Hold uses a distinct `hold_type` from `STORAGE_PAYMENT_FAILED` so the two flows don't collide on lift discipline.)*
4. **Operations submits dispute evidence (Admin Panel — Operations).** Per the DEC-047 7-BD SLA, via Airwallex tooling. *(Operator-driven by spec — unchanged; the launch KPI is chargeback rate < 2%.)*
5. **Airwallex `dispute.resolved` webhook → Module E.** Module E auto-records `ChargebackResolved`; on win records `ChargebackRecovered` (loss reversed); on loss the `ChargebackPotentialLoss` materialises (Xero decides, DEC-072). *(Automated.)*

### §6.2 Hold-no-auto-lift on dispute resolution — FLOOR-adjacent

The Hold does **NOT** auto-lift on dispute resolution — **even on a win.** Although the financial loss is reversed via `ChargebackRecovered`, the Hold + fraud-flag persist until explicit operator review (auditable reason); the `fraud_pattern_review` flag persists for the configurable retention window. Module E's role ends at the financial-event recording; **Module K owns the Hold lifecycle.**

### §6.3 Composition with the §3.3 INV3 chain (N2 — trigger-agnostic registry)

`CHARGEBACK_REVIEW` and `STORAGE_PAYMENT_FAILED` Holds compose — any active Hold blocks; each is independently remediable. **N2 (Phase C §5-N2):** the **`CHARGEBACK_REVIEW` trigger is automated** (D21 KEPT — the webhook), while the **`STORAGE_PAYMENT_FAILED` trigger is manual-first** (D4 deferred, months-away). **Module K's Hold registry is trigger-agnostic by design** (K §15.8) — it accommodates both, whether the trigger is an automated webhook or an operator action; both Holds compose; the chargeback Hold is no-auto-lift.

### §6.4 Customer-fraud refund composition

Customer-fraud refunds (a chargeback after receiving the bottle, DEC-025 row 6): NewCo absorbs the refund cost immediately (Module E records as a NewCo-side `NonRevenueCostRecorded`); recovery is pursued via the Airwallex dispute flow (§6.1). **No producer clawback.** The two flows (refund-cost recording §5 + chargeback-loss recording §6) operate in parallel; the `dispute.resolved` outcome determines whether the loss is recovered (win) or finalised (loss).

---

## §7 Xero + Multi-Currency (DEC-169) — FLOOR (D18) + the integration boundary

### §7.1 Xero routing — real-time per-event sync + post-sync immutability + reversal-ordering — FLOOR-adjacent (audit/immutability)

Module E uses **real-time per-event Xero sync at launch** (the *simplest correct* posture — batch would be more complex, not less; the Phase-2+ revisit; **not a Lean cut candidate**). Each financial event maintains a per-event sync state machine: `pending → syncing → synced` (happy path); `syncing → sync_failed` (configurable retry); `synced` is **terminal**.

- **Post-sync immutability.** Once a financial event syncs to Xero, it **cannot be modified** in NewCo's ERP. Corrections flow through credit notes (the single-payment-path discipline, §5.1) — modification of a synced document is impossible by design (Xero owns the document). **FLOOR.**
- **Reversal-ordering invariant.** Reversals against `sync_failed` source invoices are queued until the source syncs; persistent stuck reversals escalate to a Finance Manager review queue.
- **Configurable retry / no batch.** Admin-Panel-configurable retry parameters (cadence, max attempts, escalation threshold); persistent failures auto-escalate. No batched-aggregation alternative at launch (Xero rate limits are not a constraint at launch volumes). *Tech downstream (DEC-073): exponential-backoff parameters, escalation-queue mechanics.*

### §7.2 Multi-currency dual-record + per-leg FX rate-lock (DEC-169) — FLOOR (D18); NOT a candidate

> **D18 — KEPT WHOLE; FLOOR; NOT a candidate (ratified Q4). The data-integrity heart of FX-correct refunds.** Every Module E financial event records `amount` + `currency` + `eur_equivalent_amount` + `fx_rate` + `fx_rate_date` (both numbers on every event, for audit + Xero treatment). **Honest: narrowing the currency count would save ~nothing — the dual-record machinery is fixed-cost regardless of count** (the per-leg rate-lock, the snapshot pipeline, the refund-at-original-rate logic, the variance recording are the same whether 2 currencies or 5). Simplifying it (single-currency recording, or refund-at-current-rate) would directly break FX-correct refunds — a data-integrity failure that silently mis-refunds customers. **The honest answer to "can we simplify the FX layer?" is "no, and it would not save anything."**

**FX policy operational mechanics (DEC-038 → DEC-169).** Snapshot time = end of day Europe/Rome; buffer = admin-configurable; refresh = daily; rate source = Airwallex's published mid-market rate; weekend/bank-holiday = prior business-day rate.

**Refund currency rule (D18 floor).** Refunds use the **original payment's currency at the original payment's FX rate** — NOT a fresh snapshot. `RefundExecuted` carries the original `fx_rate`/`fx_rate_date`.

**Per-leg rate-lock (the FX-correct-refund floor).** For a cross-currency transaction (e.g., a customer pays USD on a Discovery purchase where the supplier was paid EUR + OC accrued), each leg's FX rate is locked at its respective financial-event-emission moment — **three independent FX moments:**
- **Customer-currency leg** locked at `PaymentCaptureSucceeded` (or `BankTransferFundsCleared`).
- **Supplier-EUR leg** locked at **`SupplierPaymentCompleted`** (E-emitted, §5.9).
- **OC leg** locked at INV1 emission.

Inter-leg FX delta accumulates as P&L per Xero downstream treatment (DEC-072).

**`FXVarianceRecorded` (the Airwallex-vs-snapshot gap).** If Airwallex's actual capture FX differs from Module E's EOD snapshot rate, Module E records the gap as `FXVarianceRecorded`; routes to Xero (Xero decides realized FX gain/loss vs period-end reconciliation, DEC-072).

### §7.3 SDI connector — DEFERRED (DEC-171/EXT-2 — D20; already-deferred; carry verbatim)

The SDI connector (Italian e-invoicing) is **not built at launch** (Italian incorporation unconfirmed, DEC-015; already-deferred, D20 — **no action; carry verbatim**, §11). **The architectural principle holds as a jurisdiction-agnostic working assumption:** Module E sends the financial-event payload + document-generation request to Xero only; SDI XML generation + submission happen **downstream of Xero** (a Xero plugin or third-party SDI connector). The principle generalises to UK Making Tax Digital / French Factur-X / German GoBD — Xero-side plugins, no Module E change. The literal connector selection waits on incorporation confirmation (Phase 2+ DEC).

### §7.4 GL-treatment boundary (DEC-072 — load-bearing) — FLOOR (methodology)

Module E records financial events with sufficient context (allocation lineage, counterparty references, dual-currency amounts, cost-cause discriminators); **Xero decides all GL treatment** — revenue recognition, COGS timing, deferred-revenue mechanics, chart-of-accounts mapping, period-close reconciliation, balance-sheet vs P&L, IFRS 15 / matching-principle. **The PRD takes no position on any of these.** Module E names the contract, never the GL mechanism.

### §7.5 Bank reconciliation — Airwallex → Xero via Module E

Bank reconciliation flows from Airwallex (transaction confirmation source) to Xero (accounting record), with Module E as the routing layer. Module E records each Airwallex confirmation (`AirwallexChargeExecuted`, `BankTransferFundsCleared`, `RefundExecuted`, `ChargebackReceived`, `ChargebackRecovered`) + routes to Xero; **Xero performs the reconciliation** against its synced records (DEC-072 — no Module E position on the reconciliation algorithm or period-close mechanics). *(The bank-transfer operator-fallback reconciliation surface is the L-PP Admin-Panel ops surface, §3.2 / §5.11.)*

### §7.6 Audit / 10-year retention (DEC-027) — FLOOR (audit/retention)

Italian invoice retention requires 10-year archival of invoices + tax documents — satisfied jointly by Xero (document storage) + the SDI connector (regulatory compliance, deferred) + NewCo-side archival (operational backup). Module E retains its financial-event records per the same retention horizon. *Tech downstream (DEC-073): the literal retention mechanism.*

---

## §8 Module E Event Catalogue at Launch (DEC-170)

Per DEC-073, payload field-by-field listings are out of scope — event names + business signals only. **The catalogue is category-neutral — unchanged by the naming cascade (§12); only the BR-referencing payload/prose renames `Bottle Reference → Product Reference`** (the allocation lineage carried on `NonRevenueCostRecorded`/`SellThroughSettled`/`OCShareAccrued`/`COGSAdjustmentRecorded`). **⚠️ The R4 change lands here: `SupplierPaymentCompleted` moves into Module E's EMITTED set (§8.1) and out of its consumed-from-D set (§8.2).**

### §8.1 Module E EMITS at launch

**Payment-execution lifecycle:** `AirwallexChargeExecuted` (successful charge for INV1/INV2/INV3; routed to Xero) · `AirwallexChargeFailed` (per attempt; reset on success; *the INV3-escalation orchestration around it is manual-first — D4, §3.3*) · `BankTransferFundsCleared` (→ Module S to fire INV1) · `RefundExecuted` (Airwallex refund completed).

**Supplier-payment lifecycle (⚠️ R4 — net-new to Module E's emitted set):**
- **`SupplierPaymentCompleted`** — **emitted by Module E on supplier-payment clearing** (E is the payment executor; at launch via the operator's manual settlement record in E's finance surface [D19 deferred]; post-launch via the engine). **Consumed by Module D** (settle/close the PO) **and Module B** (inventory `ownership_flag` PRODUCER→NEWCO) — independently. Atomic per PO (partial PO settlement deferred, OQ-20). *(Phase C R4; supersedes the cut-sheet/v1.1 "D-emits." §5.9.)*

**Chargeback lifecycle (D21 KEPT — automated):** `ChargebackReceived` (`dispute.created`) · `ChargebackPotentialLoss` (provisional) · `ChargebackResolved` (`dispute.resolved`) · `ChargebackRecovered` (win) · `CustomerChargebackFlagged` (→ Module K Hold + fraud-flag).

**Storage-fee charge lifecycle (DEC-160 — *the chain retained; the auto-trigger is manual-first, D4*):** `StoragePaymentFailed` (→ Module K `STORAGE_PAYMENT_FAILED` Hold) · `StoragePaymentSucceeded` (→ Module K lifts the per-cycle Hold).

**Non-revenue cost + cost-basis revision + FX variance:** `NonRevenueCostRecorded` (the unified wrapper) · `InsuranceRecoveryReceived` (net-back) · `COGSAdjustmentRecorded` (provisional→finalized cost-basis flip; rare at NewCo) · `FXVarianceRecorded` (Airwallex-vs-snapshot gap).

**Producer/Supplier settlement lifecycle (*recorded at launch; the aggregation/composition defers — D19*):** `SellThroughSettled` (carries `supplier_party_id`) · `OCShareAccrued` (at INV1; carries `originating_club_producer_party_id`; Section-D info-disclosure preserved) · `ProducerSettlementStatementIssued` (the 5-section statement — **composed operator-run at launch, D19**).

**Xero sync lifecycle:** `XeroSyncCompleted` · `XeroSyncFailed`.

**Credit lifecycle (the financial accrual / reversal side; the balance entity at Module K — DEC-166 + DEC-174):** `ClubCreditAccrued` · `ClubCreditRestored` · `ClubCreditForfeited` · `StoreCreditIssued`. *(The application events `ClubCreditAutoApplied` / `ClubCreditRemovedByCustomer` / `StoreCreditApplied` are **Module-S-emitted** — DEC-174 three-actor split, MVP-DEC-018; Module E consumes them per §8.2, it does not emit them.)*

### §8.2 Module E CONSUMES at launch

**From Module S:** `InvoiceINV1Issued` / `InvoiceINV2Issued` / `InvoiceINV3Issued` (route to Xero + charge) · `RefundRequested` (execute Airwallex refund) · `OrderPaymentPending` (watch for `transfer.received`) · `StoreCreditApplied` / `ClubCreditAutoApplied` / `ClubCreditRemovedByCustomer` (record the application / removal for Xero — DEC-174) · `MembershipFeePaid` (record) · `StorageFeeAccrued` (informational) · `StorageFeeProRataRefundIssued` (record, cause-conditional) · `DiscoveryRevenueShareAccrued` (record as `OCShareAccrued` — the OC seam) · `DiscoveryRevenueShareReversed` (record the reversal) · `SupervisorOverridePostDeliveryRefund` (execute refund). *(Module S emits all three customer-facing invoices + the OC accrual + the refund family per DEC-119 / ratified S §16/§17.7.)*

**From Module D:** `InboundEventCostFinalized` (→ `COGSAdjustmentRecorded`, rare at NewCo). **⚠️ `SupplierPaymentCompleted` is NO LONGER consumed-from-D — Module E *emits* it (§8.1, R4); Module D *consumes* it.**

**From Module C:** `ReplacementShipmentIssued` (→ `NonRevenueCostRecorded` + separate write-off; the DEC-182 OC-reversal-mirror) · `BottleBreakageInTransit` / `BottleLossInTransit` / `BottleWriteOff` (→ `NonRevenueCostRecorded`) · `InsuranceClaimResolved` (→ `InsuranceRecoveryReceived`, `insurance_pool` net-back) · `ShippingFeeQuoted` / `ShipmentDispatched` / `ExciseCalculated` (informational — Module S composes the INV2 line; Module E records the financial-event side via INV2 routing, DEC-072).

**From Module B:** `BottleBreakageInCustody` (→ `NonRevenueCostRecorded`) · `InventoryAdjusted` (→ `NonRevenueCostRecorded`, §5.6) · `OwnershipTransitioned` (observed for audit only — the E-emitted `SupplierPaymentCompleted` is the financial event; no fresh recording on B's downstream transition) · `BottleQuarantineResolved` / `StocktakeReconciled` (observed for audit; downstream `InventoryAdjusted` events source the financial events). *(`InventoryShortfallDetected` NOT consumed directly, §5.6.)*

**From Module K (read-only):** `ProducerAgreement.settlement_cadence` (deferred settlement) · `Customer.originating_club_id → Club.partner_producer_id` (OC routing) · `OriginatingClubLocked` (the OC capture — the accrual recorded at launch as the seam) · credit-balance entities (credit-event context).

**From Module A (read-only):** `Allocation.sourcing_model` (the `SupplierPaymentCompleted` routing discriminator) · `Allocation.commercial_terms` (per-constituent `C_i` — sell-through amount) · `Allocation.visibility` (OC applicability) · `Allocation.producer_id` / `Allocation.supplier_id` (settlement-routing two-FK).

**From Module 0 (read-only):** **Product Reference** / Product Variant / Composite SKU constituent context — read for financial-event payload allocation lineage *(GENERALISE — `Bottle Reference → Product Reference`, §12; payload semantics identical)*.

### §8.3 External integration boundaries

- **Airwallex (DEC-014).** Inbound webhooks: `charge.succeeded`, `charge.failed`, `transfer.received`, `dispute.created`, `dispute.resolved`. Outbound: authorize+capture, charge against saved card, refund, dispute-evidence submission. *Literal contract downstream (DEC-073).*
- **Xero (DEC-028).** Sole NewCo-side Xero integration point; real-time per-event sync (§7.1); document-generation routing (§3.1 step 5).
- **HubSpot (DEC-014) — marketing / lifecycle only (Module K §14.9.1 purpose split, MVP-DEC-035).** Module E emits trigger events (`StoragePaymentFailed`, `CustomerChargebackFlagged`, `RefundExecuted`); the resulting **money mail** (failed-charge notices, refund receipts) is ERP-sent through the single email service (catalog-registered — joins with Module E); HubSpot may consume the same events for lifecycle automation. Module E carries no content / template / recipient logic either way.
- **SDI connector (deferred, DEC-171 — D20).** Phase-2+; the architectural principle (E → Xero only; SDI XML downstream) holds as a working assumption (§7.3).

---

## §9 Cross-Module Read Contracts

Per DEC-074, contracts in NewCo prose; the §8 catalogue names the surface, this section anchors each to the upstream PRD. **The naming cascade (§12) renames only the Module-0-catalog-identity reads (the lineage payloads); Module E's own names + every sibling's own names are unchanged. ⚠️ §9.2 + §9.6 land R4 (E-emits).**

### §9.1 Module E ↔ Module S
**Consumes:** the three `Invoice*Issued` events (route to Xero + charge); `RefundRequested` (execute); `OrderPaymentPending`; `StoreCreditApplied`/`ClubCreditAutoApplied`/`ClubCreditRemovedByCustomer`; `MembershipFeePaid`; `StorageFeeAccrued` (informational); `StorageFeeProRataRefundIssued`; `DiscoveryRevenueShareAccrued`/`DiscoveryRevenueShareReversed`; `SupervisorOverridePostDeliveryRefund` (Module S §16.9/§16.7/§16.11/§17.7). **Emits to Module S:** `BankTransferFundsCleared`; `AirwallexChargeExecuted`/`AirwallexChargeFailed`; `StoragePaymentSucceeded`; `RefundExecuted`. **Read-only — Module E does NOT mutate Module S state; its role on customer-facing invoices is uniformly consume + route + execute (DEC-119, the three-actor split; storage Module-S-internal per R2 — no bidirectional S↔E).**

### §9.2 Module E ↔ Module D — ⚠️ R4 (E emits `SupplierPaymentCompleted`; D consumes)
- **Module E emits `SupplierPaymentCompleted`** on supplier-payment clearing (the payment executor; §5.9, R4); **Module D consumes it** to settle/close the PO (Module D §6/§14.7 — already aligned as the consumer). At launch E emits it when the operator records the manual supplier payment (D19 deferred); post-launch via the engine. Atomic per PO (OQ-20).
- **Module E consumes `InboundEventCostFinalized`** (Module D §7/§16 — landed cost) → `COGSAdjustmentRecorded` (rare at NewCo per V2 default).
- **Module D's other procurement financial events** (`PurchaseOrderIssued`, `DiscrepancyResolutionRecorded`, `ConsignmentReceiptRecorded`, `ReverseInboundEventRecorded`, `POIssuedUnderNonActiveAgreement`) stay **D-emitted**; Module E records + forwards to Xero (the D19 recording seam). **Module E reads** `Allocation.sourcing_model`/`commercial_terms`/`producer_id`/`supplier_id` via Module A (§9.4) for the settlement context. *(R1: `SupplierPaymentCompleted` has no Allocation-FSM-activation role — Module A is already aligned; activation is uniform operator-publish, DEC-183.)*

### §9.3 Module E ↔ Module K
**Emits to Module K:** `StoragePaymentFailed` (→ `STORAGE_PAYMENT_FAILED` Hold — *the trigger manual-first at launch, D4*); `CustomerChargebackFlagged` (→ `CHARGEBACK_REVIEW` Hold + fraud-flag — *automated, D21*); `ClubCreditAccrued`/`ClubCreditRestored`/`ClubCreditForfeited` + `StoreCreditIssued` (→ Module K records balance state; the application events `ClubCreditAutoApplied`/`ClubCreditRemovedByCustomer`/`StoreCreditApplied` are Module-S-emitted, not from here — DEC-174). **N2 — Module K's Hold registry is trigger-agnostic (K §15.8); it accommodates both the automated chargeback trigger and the manual-first storage-payment trigger.** **Reads:** `ProducerAgreement.settlement_cadence` (deferred settlement); `Customer.originating_club_id → Club.partner_producer_id` + `OriginatingClubLocked` (OC routing + the recorded accrual seam); credit-balance entities. **Module E does NOT own the Hold lifecycle** — it only emits the trigger events.

### §9.4 Module E ↔ Module A (read-only)
`Allocation.sourcing_model` (the `SupplierPaymentCompleted` routing discriminator, §4.3); `Allocation.commercial_terms` (per-constituent `C_i` — the sell-through amount, §4.1); `Allocation.visibility` (OC applicability); `Allocation.producer_id`/`Allocation.supplier_id` (settlement-routing two-FK). **E reads A's per-constituent settlement lineage as the seam for the deferred 5% OC share + settlement — it does NOT re-derive** (Module A §11.7). No events; no state mutation. **Direct Purchase deferred — the `sourcing_model = direct_purchase` arm idles; the discriminator is retained as the seam.**

### §9.5 Module E ↔ Module C (consume)
`ReplacementShipmentIssued` (→ `NonRevenueCostRecorded` + separate write-off; the DEC-182 OC-reversal-mirror — the Module E seam); `BottleBreakageInTransit`/`BottleLossInTransit`/`BottleWriteOff` (→ `NonRevenueCostRecorded`); `InsuranceClaimResolved` (→ `InsuranceRecoveryReceived`; `insurance_pool` net-back); `ShippingFeeQuoted`/`ShipmentDispatched`/`ExciseCalculated` (informational — Module S composes the INV2 line; Module E records the financial-event side via INV2 routing). **Module C records the operational event; Module E records the financial event; Xero decides GL (DEC-072).** No Module E → Module C emission; no state mutation.

### §9.6 Module E ↔ Module B — ⚠️ R4 (E emits `SupplierPaymentCompleted`; B consumes → ownership flip)
- **Module E emits `SupplierPaymentCompleted`** (§5.9, R4); **Module B consumes it** → the inventory `ownership_flag` PRODUCER→NEWCO transition (Module B §0.3/§2.2 — **B does NOT emit it; E does**). The contract is event-driven (no synchronous coupling); the Direct-Purchase no-op (InboundBatch `NEWCO` from creation) is moot at launch.
- **Module E consumes** `BottleBreakageInCustody` (→ `NonRevenueCostRecorded`); `InventoryAdjusted` (→ `NonRevenueCostRecorded`, §5.6); observes `OwnershipTransitioned`/`BottleQuarantineResolved`/`StocktakeReconciled` for audit. **`InventoryShortfallDetected` NOT consumed directly** (§5.6). **Reads** InboundBatch cost-basis at dispatch (§5.7) + bottle-days-in-storage per allocation lineage (§5.8).
- **No NFT touchpoint** (DEC-014; D12 decoupled — `NonRevenueCostRecorded` carries `BottleId` but no on-chain data). No Module E → Module B state mutation.

### §9.7 Module E ↔ Module 0 (read-only)
**Product Reference** / Product Variant / Composite SKU constituent context — read for financial-event payload allocation lineage *(GENERALISE — the lightest cascade touchpoint, §12)*. No events; no state mutation.

### §9.8 Module E ↔ External integrations
Airwallex (§3/§5.1/§6/§7.2); Xero (§3.1 step 5 / §7.1 / §7.5); HubSpot (§3.3/§5.1/§6.1 trigger events); SDI (deferred, §7.3). Literal API contracts are tech (DEC-073).

---

## §10 Boundary Statement — Module E Does NOT Do (DEC-119/072/073/074)

The 20-item boundary (the three actor-split items first, LOAD-BEARING):

**Three-actor split items (FLOOR):**
1. **Document generation** (Xero, DEC-028/119) — no PDFs, templates, numbering, legal text, SDI/MTD/Factur-X; Module E sends the payload + document-generation request to Xero; Xero returns the URL; Module E confirms back to Module S.
2. **Customer-facing invoice issuance** (Module S, DEC-119) — Module S emits the three `Invoice*Issued`; Module E consumes.
3. **Storage-fee computation + clock + accrual events** (Module S, DEC-119) — Module E consumes `InvoiceINV3Issued` only.

**Module-ownership boundary items:** 4. **GL treatment / accounting policy** (Xero, DEC-072). 5. **Voucher state machine** (Module S). 6. **Order state machine** (Module S — E provides only the bank-transfer funds-cleared notification). 7. **Allocation state + sub-pool + capacity** (Module A — E consumes lineage only). 8. **Hold entity creation + lifecycle** (Module K — E EMITS triggers). 9. **Customer / Profile / KYC / sanctions / Originating-Club lock** (Module K — E reads). 10. **Producer / Supplier / ProducerAgreement / SupplierProducerLink** (Module K + Module D — E reads `settlement_cadence`). 11. **Shipping Order + carrier integration + shipping-fee quote-generation** (Module C — E records the actual fee on INV2 routing only). 12. **NFC tag + NFT mint/burn/recovery + Bottle Page** (Module B — no Module E interaction; no Avalanche touchpoint, DEC-014). 13. **Product master data, PR, Product Variant, Composite SKU, Case Configuration** (Module 0 — E reads context for the lineage payload only).

**Tech/UX boundary items:** 14. **SDI XML generation + Italian e-invoicing submission** (downstream of Xero, DEC-171 — E's scope ends at the Xero API call). 15. **Credit-balance entity** (Module K — E records the financial events only). 16. **Credit-Hold lift authorisation / write-off authority / refund-comp thresholds / dunning-config edit** (operator-authority, `feedback_prd_rr_approval`; admin-configurable). 17. **B2B credit terms + INV0 + INV-P + INV1_INV2_COMBINED + INV4 + SELL_THROUGH_SETTLEMENT + EVENT_CONSUMPTION_SETTLEMENT** (out at launch — §2.2 / §11). 18. **Voucher activation gate** (Module S, DEC-119). 19. **Customer-facing email / SMS / in-app notifications** (the ERP email service for operational / money mail + HubSpot for marketing / lifecycle — Module K §14.9.1, MVP-DEC-035; E emits triggers only). 20. **Airwallex / Xero / SDI / HubSpot operational integration mechanics** (tech-implementation, DEC-073).

These deliberate silences keep Module E the recorder/router, not an authority — neutral to every upstream cut.

---

## §11 Deferred Set & Post-Launch Roadmap Pointers (MVP)

Every deferred/simplified item names its seam (P1) + points to `04-roadmap/Post_Launch_Roadmap_v0.1.md`. **Net-new MVP deferrals** restore as coordinated sets where cross-module (Phase C item N).

### §11.1 Net-new MVP deferrals / simplifications (this PRD)
- **D19 — the supplier-settlement *engine* (§4).** Deferred: the quarterly runs, the 5-section `ProducerSettlementStatementIssued` composition (DEC-156), the OC 5% aggregation into Section D, the producer-fault clawback netting (Section C), the counterparty disambiguation routing, the producer settlement-currency option, the settlement-statement FSM, the Xero AP routing → **operator-run first cycle(s).** **Seam:** Module E keeps **recording** every settlement-input event (§4.7) + routes each to Xero in real time; the operator composes the first statement(s) manually; the engine reads the same records post-launch. **Restores as a coordinated E + D + S + A set** (D keeps recording `SupplierPaymentCompleted`-adjacent cost events; S keeps emitting the OC accrual + refunds; A keeps the lineage — Phase C item N).
- **D4 — the INV3-failed-charge 3-stage auto-escalation orchestration (§3.3).** Deferred: the auto-retry → auto-`StoragePaymentFailed` → auto-Hold → auto-Suspension orchestration + the automated multi-cycle composition → **manual first cycle.** **Seam:** the `StoragePaymentFailed` → K-Hold → Profile-Suspension event chain + the admin-configurable staged thresholds + the multi-cycle rules are retained; the operator drives the first cycle(s); the automated orchestration is additive. (The card+SEPA + saved-card charge + the sanctions gate + no-auto-lift are FLOOR — KEPT.)
- **The OC 5% computation + the producer-fault clawback netting (§4.2, §4.5).** Deferred-with-settlement (D19). **Seam:** the accrual emission (S `DiscoveryRevenueShareAccrued` at INV1) + the cause-tagged recorded refunds (E `RefundExecuted` + `DiscoveryRevenueShareReversed`) are the seam; E computes the 5% + nets the clawbacks when the engine is built, reading K's lock + A's lineage, **not re-deriving**; the Section-D info-disclosure constraint (DEC-180) is preserved on the recorded accrual.

### §11.2 v1.1 already-deferred / future-flex set (carried verbatim — do NOT re-cut)
- **SDI connector (D20 / DEC-171 / EXT-2, §7.3)** — deferred pending Italian incorporation confirmation (DEC-015); the architectural principle (E → Xero only; SDI XML downstream) holds as a jurisdiction-agnostic working assumption; the literal connector selection is a Phase-2+ DEC.
- **Paid services / experiences + INV4 + `EVENT_CONSUMPTION_SETTLEMENT`** (DEC-171 / Q-OQ-19 / BMD §4.14) — no INV4 typology, no event-consumption settlement at launch.
- **Partial PO settlement** (OQ-20 / DEC-091 / `project_v12_partial_po_settlement_gap`) — `SupplierPaymentCompleted` is **atomic per PO** at launch; partial-settlement scenarios are operationally exceptional + handled via manual Xero adjustment; Phase-2+ extends the event with `partial_amount` + `outstanding_balance`.
- **B2B types INV0 / INV-P / INV1_INV2_COMBINED** (DEC-017/068/157); **active-consignment SELL_THROUGH_SETTLEMENT + EVENT_CONSUMPTION_SETTLEMENT** (DEC-011/193/068 — the coordinated tri-module B + C + E restoration, §5.10); **membership-fee INV0** (DEC-007/157 — Hero fires INV1); **AR-aging dunning** (Phase-2+; the launch INV3 dunning chain is the only payment-failure dunning mechanism).
- **Phase-2+ items not Module E scope** (E records the resulting financial/cost events identically once activated): producer-override on shipping (DEC-137, C); US-state expansion (DEC-148, C/0); excise rate-matrix (DEC-150, C); DDP/DAP country-by-country (DEC-149, C); reverse-logistics + multi-warehouse + drop-ship + full reverse-inbound (DEC-152/155, B/C/D); blockchain-expert review (no Module E Avalanche touchpoint).

*(The AMB-E-1..7 acceptance-authoring backlog is orthogonal to MVP scope — not re-opened. AMB-E-4 [settlement-statement FSM] + AMB-E-6 [AR-aging dunning] intersect the D19/D4 defers; fold into the engine-build if convenient — not scope decisions.)*

---

## §12 Naming-Cascade Application (Phase C item A) — the lightest of the eight

Module 0 v0.3-MVP §18 is the **source-of-truth** name table; this section records how those names land in Module E. **Module E's touch is the lightest of any module** — its own names are category-neutral, and it reads no catalog identity directly except for financial-event allocation lineage. The change is **naming/contract only — zero behaviour change** (every event carries the same business signal; BR/PR denote the same key). **Cascade position: Module 0 → A/D → S → B/C → E (here).**

**What renames in Module E (the PR-referencing / Module-0-event-consuming prose only):**

| Touchpoint | v1.1 prose | v0.3-MVP prose | Wine-display alias retained |
|---|---|---|---|
| §5.4 NonRevenueCost wrapper lineage | "Bottle Reference / Wine Variant" cost-basis + allocation lineage | "Product Reference / Product Variant" | Bottle Reference |
| §8.1/§9.7 financial-event lineage payloads (`NonRevenueCostRecorded`/`SellThroughSettled`/`OCShareAccrued`/`COGSAdjustmentRecorded`) | "BR / Wine Variant / Composite SKU constituent context" | "PR / Product Variant / Composite SKU constituent context" | Bottle Reference |
| §8.2/§9.7 Module 0 reads | "Wine Variant / Bottle Reference" | "Product Variant / Product Reference" | Wine Variant; Bottle Reference |

**What does NOT rename in Module E (the carve-out — the lightest touch):**
- **Module E's own entity/event/attribute names** are already **category-neutral — unchanged**: `Invoice*` (consumed), `Payment*`, `Settlement*`/`SellThroughSettled`/`ProducerSettlementStatementIssued`, `NonRevenueCost*`, `OCShare*`/`DiscoveryRevenueShare*` (consumed), `Chargeback*`, `Refund*`/`RefundExecuted`, `Xero*`, `FXVariance*`, `ClubCredit*`, `StoreCredit*`, `AirwallexCharge*`, `StoragePayment*`, `BankTransferFundsCleared`, `COGSAdjustmentRecorded`, `InsuranceRecoveryReceived`, **`SupplierPaymentCompleted`** (E-emitted, R4).
- **Module E's consumed sibling event names** are unchanged — `InboundEventCostFinalized` (D), `ExciseCalculated`/`ShippingFeeQuoted`/`ShipmentDispatched`/`ReplacementShipmentIssued`/`BottleBreakageInTransit`/`InsuranceClaimResolved` (C — physical-unit / category-neutral names retained), `InventoryAdjusted`/`BottleBreakageInCustody`/`OwnershipTransitioned` (B), `OriginatingClubLocked` (K), `MembershipFeePaid` (S — DEC-173), `Allocation*` (A).
- **Composite SKU** (Module 0) is retained — not renamed. **"Bottle Reference"** is retained **everywhere** as a wine-display alias for Product Reference.

**Rule of thumb:** rename only the PR-referencing / Module-0-event-consuming **lineage prose** to the canonical names (payload semantics identical); keep Module E's own `Invoice*`/`Payment*`/`Settlement*`/… names and every sibling's own names alone.

---

## §13 v1.1 Inheritance & MVP Re-Baseline Trace (audit appendix)

This appendix preserves the audit trail of Module E v0.3-MVP against its **frozen v1.1 predecessor** ([`../../reference/v1.1/01-prd/Module_E_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_E_PRD_v0.2.md), whose §N carries the v17 §E inheritance trace + App A the divergence summary) + the **ratified cut-sheet** + the **Phase C reconciliation**. The load-bearing prose is the body above (DEC-074); this trace is for audit / diff.

> **Section-numbering note.** Module E is **KEEP-whole on the customer-side floor + the three-actor split + the dual-record FX + chargeback automation, with the settlement *engine* (Clause 2) deferred-not-deleted and the INV3 dunning *orchestration* deferred-not-deleted** — so **§1–§10 keep their v1.1 meaning** (the acceptance doc's PRD §-anchors — §2 typology, §3 payment execution, §4 settlement, §5 refund/credit/non-revenue, §6 chargeback, §7 Xero/FX, §8 events, §9 cross-module, §10 boundary — **remain valid against this PRD**). The one relocation: **v1.1's §0.2 three-actor architectural lock moves to §1.1** (foregrounded with the module purpose), so the few acceptance rows anchored "§0.2" (AC-E-XM-2/4, AC-E-BR-Actor-1) re-anchor to "§1.1" in the re-cut acceptance doc. v1.1's §0 methodology (§0.1) folds into the header **Methodology** block; v1.1's §0.3–§0.6 (scope summary / does-NOT-do / deferred / no-BMD) distribute across §0 + §1.3 + §2.2 + §11. **§11** (v1.1 "Deferred items") is **repurposed to "Deferred set & roadmap pointers (MVP)"** — folds v1.1's deferred set verbatim + the net-new D19/D4 defers. **§12** = NEW (naming-cascade application); **§13** = NEW (this trace); **§14** = cross-references. **v1.1's §N v17-trace + App A divergence summary live in the frozen v0.2** (DEC-074: the body restates the substance).

| v0.3-MVP section | v1.1 (v0.2) anchor | Cut-sheet / Phase C | MVP disposition |
|---|---|---|---|
| §0 MVP scope at a glance | — (new; v1.1 §0.3/§0.5 distributed) | cut-sheet §1; Phase C §1 | NEW framing — cut-heaviest finish; floor + three-actor + dual-record + chargeback whole. |
| §1.1 Purpose + three-actor split | §0.2 + §1.1 | DEC-119; cut-sheet E.1 | KEEP — FLOOR; three-actor lock relocated here from §0.2. |
| §1.2 Four-clause role | §1.2 | cut-sheet E.2; **D19/D21** | KEEP; Clause 2 reframed (engine deferred D19; recording = seam; **R4 E-emits**); Clause 3 chargeback KEPT (D21). |
| §1.3 Boundary / §1.4 Personas | §1.3 / §1.4 | cut-sheet E.3; **L-PP** | KEEP; L-PP zero producer/consumer self-serve writes. |
| §2 Invoice typology | §2 | E.5/E.6; DEC-157 | KEEP — FLOOR (tax); out-of-launch types carried verbatim. |
| §3 Payment execution | §3 | E.7–E.13; **D4** | KEEP — FLOOR; **§3.3 INV3 auto-escalation orchestration manual-first (D4); the chain + thresholds + sanctions gate KEPT.** |
| §4 Producer/Supplier settlement | §4 | E.14–E.19; **D19 / Q1 / Q5** | **DEFER the engine → operator-run**; the recording (§4.7) is the seam; OC 5% + clawback netting defer-with-settlement; capture whole (item E). |
| §5 Refund + credit + non-revenue cost | §5 | E.20–E.23; **R4 / N3 / item F** | KEEP (floor-adjacent); **§5.9 reframed to E-emits `SupplierPaymentCompleted` (R4) + N3 two-ledger + item F title-timing**; §5.3 credit names aligned to K. |
| §6 Chargeback | §6 | E.24/E.25; **D21 / Q3 / N2** | **KEEP — automated (Paolo override)**; N2 chargeback-automated/storage-manual; K trigger-agnostic. |
| §7 Xero + multi-currency | §7 | E.26–E.29; **D18 / Q4 / D20** | KEEP — FLOOR (dual-record D18, not a candidate); real-time sync + immutability; SDI deferred (D20). |
| §8 Event catalogue | §8 | E.30; **R4** | KEEP + GENERALISE; **§8.1 ADDS `SupplierPaymentCompleted` (E-emits); §8.2 REMOVES it from consumed-from-D**; settlement emissions recorded/aggregation-deferred; credit names aligned to K. |
| §9 Cross-module reads | §9 | E.31/E.32; **R4 / N2** | KEEP + GENERALISE + **RECONCILE**; §9.2 (D consumes) + §9.6 (B consumes) land E-emits; §9.3 N2. |
| §10 Boundary (20 items) | §10 | E.33 | KEEP. |
| §11 Deferred set & roadmap (MVP) | §11 (repurposed) | E.34; Phase C item N | Folds v1.1 already-deferred verbatim + D19/D4/OC net-new. |
| §12 Naming-cascade application | — (NEW) | Phase C item A | NEW — the lightest cascade. |
| §13 MVP re-baseline trace | — (NEW; v1.1 §N → frozen v0.2) | — | NEW (this table). |
| §14 Cross-references | — (NEW) | — | NEW pointer. |

**Notation.** *KEEP* = carried at full fidelity. *GENERALISE* = naming-only (PR/Product) in the lineage payloads. *RECONCILE* = contract-consistency fix, no behaviour change (R4). *DEFER (D-dial)* = moved to the roadmap with a named seam. *NEW* = Phase-D MVP apparatus.

---

## §14 Cross-References

- **Frozen v1.1 predecessor** (audit/diff anchor; never edited): [`../../reference/v1.1/01-prd/Module_E_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_E_PRD_v0.2.md) (§N carries the v17 §E trace; App A the divergence summary) + [`../../reference/v1.1/01-prd/Module_E_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_E_Acceptance_v0.1.md) + `../../reference/v1.1/01-prd/Module_E_Packet_v0.1.md` (the AMB-E-1..7 backlog — orthogonal to MVP scope).
- **Ratified scope source**: [`../01-triage/Module_E_CutSheet_v0.1.md`](../01-triage/Module_E_CutSheet_v0.1.md) (§2 scope / §3 changes / §5 acceptance delta / §6 Q1–Q7). **⚠️ Its "D-emits `SupplierPaymentCompleted`" RECONCILE is superseded by Phase C R4 (E-emits).**
- **Coherence gate**: [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) (R4 §5-R4/§2-C — E-emits; N2 §5-N2; N3 §5-N3; items E/F §2; floor §6).
- **Source-of-truth names**: [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18 (Module E carve-out — category-neutral).
- **Settled siblings**: [`Module_S_PRD_v0.3-MVP.md`](Module_S_PRD_v0.3-MVP.md) (§14 storage R2; §16.7/§16.9/§16.11 invoice + OC + refund events; §17.7 names E-emits) · [`Module_D_PRD_v0.3-MVP.md`](Module_D_PRD_v0.3-MVP.md) (§6/§14.7 consumes the E-emitted `SupplierPaymentCompleted`; §3.5 item F/N3) · [`Module_B_PRD_v0.3-MVP.md`](Module_B_PRD_v0.3-MVP.md) (§0.3/§2.2 the R4 consumer side — B consumes, does not emit) · [`Module_C_PRD_v0.3-MVP.md`](Module_C_PRD_v0.3-MVP.md) (§9/§15 the NonRevenueCost/excise/damages/insurance seam) · [`Module_A_PRD_v0.3-MVP.md`](Module_A_PRD_v0.3-MVP.md) (§11.7 the settlement lineage) · [`Module_K_PRD_v0.3-MVP.md`](Module_K_PRD_v0.3-MVP.md) (§15.8 N2 trigger-agnostic Hold; the Club-Credit registry).
- **MVP decisions register**: [`../04-decisions/MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md).
- **Testable companion**: [`../03-acceptance/Module_E_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_E_Acceptance_v0.3-MVP.md).

---

*End of Module E PRD v0.3-MVP — Phase D re-baseline. **DRAFT — awaiting batch ratification.** The eighth and final module PRD — the finance terminus. Genuinely the cut-heaviest finish (the #3 lever D19 + D4 + the already-deferred D20) — yet the customer-side payment-execution + Xero-routing + tax-correct-invoicing-recording + dual-record-FX (D18) + three-actor-split floor stays whole, and chargeback automation is KEPT (D21, Paolo override). **D19 defers the settlement engine → operator-run (the recording of every settlement-input event is the seam, §4.7); D4 defers the INV3 dunning orchestration → manual first cycle (the Hold chain + thresholds are the seam).** ⚠️ **R4 landed: Module E EMITS `SupplierPaymentCompleted` (the cut-sheet's "D-emits" is superseded by Phase C); Module D + Module B consume independently** (§5.9/§8.1/§9.2/§9.6); N2 (chargeback automated / storage-payment manual-first; K trigger-agnostic) + N3 (two-ledger clarity) + item F (title-timing, no accounting position) landed. The OC 5% accrual capture is whole at launch (the computation defers, D19). The naming cascade is the lightest of the eight (E's own names category-neutral). Nothing handed off until Phase E. **With Module E drafted, all 8 module PRDs are drafted — the re-baseline turns to the 9th Admin-Panel PRD, the Architecture, the roadmap, and the release index.***
