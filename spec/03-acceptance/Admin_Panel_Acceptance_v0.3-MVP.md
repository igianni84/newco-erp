# NewCo ERP — Admin Panel (Operator-Surface / Workflow Product-Spec) Acceptance Criteria — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP acceptance contract for the **9th, thin Admin-Panel PRD**). **The ninth and final PRD-paired acceptance doc.**
- **Date**: 2026-06-08
- **Status**: **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E.
- **⚠️ NEW — no v0.1 predecessor to re-cut.** Unlike the 8 module acceptance docs (each re-cut from a frozen v1.1 acceptance template), the Admin Panel had **no v1.1 PRD and no v1.1 acceptance doc** — v1.1 treated it as an implicit DEC-083 admin-parity mirror and never specced it (PRD §8). **This doc authors the Admin-Panel-surface acceptance for the FIRST time** — there is no re-cut delta, only a fresh authoring against the four content blocks of the PRD.
- **Owner**: Paolo (product sign-off authority).
- **Companion spec**: [`../02-prd/Admin_Panel_PRD_v0.3-MVP.md`](../02-prd/Admin_Panel_PRD_v0.3-MVP.md) — the source of truth this document validates against. The PRD says *what operators can do* (the operator-surface / workflow product-spec); this document says *what passes*. Together they are the dev-team's complete brief for the launch-MVP Admin-Panel surface.
- **The verification boundary (load-bearing)**: **this doc verifies the Admin-Panel-SURFACE side only.** The cross-module consoles' **downstream behaviour** (the FSM transitions, the event emissions, the financial-event recording, the inventory-ledger mutations) is verified in the **owning module's acceptance doc** — this doc verifies that the operator capability is *exposed*, *operator-driven*, *audited with `actor_role`*, *honours the producer-write boundary*, and (for the consoles) *composes / triages from the recorded events*. Each criterion carries a pointer to the owning module AC for the downstream half.
- **Methodology DECs binding this document**: **DEC-073** (product-spec layer — criteria are operator-capability + workflow-contract + audit behaviour, **NOT** UI/UX acceptance: no screen layouts, form fields, navigation, components, IA, design tokens, page templates); **DEC-074** (self-contained; anchors restated inline); **DEC-083** (admin-parity is a backend contract — the operator path is functionally complete; only producer write UIs deferred); **DEC-072** (no accounting positions — Module E records, Xero decides GL); **DEC-141** (the shared Logilize discrepancy queue B+C); **L-PP / K-Q4** (exactly ONE producer write); **D23** (Producer Portal read + full reporting KEPT); **D24** (Admin Panel more load-bearing in a manual-first MVP); `feedback_prd_rr_approval` (operator authority-tier / RBAC policy admin-configurable + downstream — out of scope).
- **What this document is NOT**: engineering Definition of Done (coverage thresholds, performance budgets, retry/idempotency mechanics, schema design, the Admin-Panel front-end framework / state-propagation / component contracts); **UI / UX acceptance** (screen layouts, form fields, navigation trees, IA wireframes, design tokens, the literal console screens — all DEC-073 tech, deferred; the `greenfield/12-admin-panel/` design-side work is a read-only reference, not a contract); operational R&R / authority-tier *policy* (admin-configurable per `feedback_prd_rr_approval`); **the downstream module behaviours** (verified in each module's own acceptance doc — this doc points to them, does not re-verify them); GL accounting policy (DEC-072 — Xero scope); the full Admin-Panel surface (a roadmap deliverable — PRD §6).

---

## §0 What this document is (the fresh authoring — no re-cut)

This doc authors the acceptance contract for the four content blocks of the Admin-Panel PRD — **the Admin-Panel-surface side of each**:

1. **The producer-write boundary (block (c) — PRD §2):** exactly ONE producer write platform-wide (K membership approve/decline); every other producer/back-office write operator-driven (`actor_role: newco_ops`); D23 Producer-Portal read + full reporting KEPT; the consumer storefront/cellar/Bottle-Page EXEMPT (self-serve). → §2.
2. **The per-module operator-capability inventory (block (a) — PRD §3):** each operator capability the launch ships is *exposed* + *operator-driven* + *audited with `actor_role`*; the downstream behaviour is verified in the owning module AC (pointer). → §3.
3. **The net-new cross-module operator consoles (block (b) — PRD §4):** each console's Admin-Panel-side workflow contract — the shared Logilize discrepancy queue (B+C); the finance-ops console (E — settlement/dunning/reconciliation/FX/Xero/chargeback); the white-glove quote flow (C); the returns/recall consoles (C); the stocktake/quarantine/adjustment surfaces (B); the procurement/discrepancy surfaces (D). → §4.
4. **The "full target surface" seam (block (d) — PRD §6):** the MVP slice is a clean SUBSET; the seam is real on both sides (the manual-first consoles record what their future engines consume; the operator writes sit on the same backend their future producer UIs will use). → §6.

Plus the cross-cutting disciplines the surface owns: the composed-surface model + the multi-actor patterns + the `actor_role` audit envelope (→ §5).

**The honest calibration (PRD §0 / master §8): net-new but THIN.** The criteria are mostly **AUTO** (assert the surface exists + is operator-driven + emits the audit envelope + honours the producer-write count + composes from the recorded events) with a **MIXED** cluster on the finance-ops console (the operator settlement composition + the white-glove / refund judgment) and **one HUMAN** end-to-end operator demo. **No criterion re-verifies a module backend** — every downstream behaviour carries a pointer to its owning module AC.

---

## §1 How to use this document

### §1.1 Verification tags
- **AUTO** — an AI agent / test harness reads the criterion + the PRD anchor + the running system (the operator-action event stream, the `actor_role` audit envelope, the producer-write registry, the recorded settlement-input / discrepancy events) → PASS/FAIL with evidence. Paolo reviews the batch.
- **MIXED** — AI prepares the evidence (assembles the operator-composed 5-section settlement statement from the recorded events for a representative period; gathers the white-glove approve/deny audit trail; renders the refund store-credit-105% judgment record); Paolo confirms the operator-judgment call.
- **HUMAN** — Paolo executes personally (a single end-to-end operator demo session — see §5.3 / AC-AP-DEMO-1).

**Distribution (target):** ~85% AUTO / ~13% MIXED / ~2% HUMAN. The MIXED items cluster on the finance-ops console (the operator-run settlement composition — D19; the white-glove judgment — D3; the refund store-credit judgment — D6) where operator judgment + composition quality benefit from human review. **The deferred-automation annotations (D19/D4/D16/D14/D3 manual-first) do not change a criterion's tag — they change *when* it verifies the automated arm: the manual-first operator surface verifies at launch; the automated arm verifies when the engine/orchestration lands (the seam, §6).**

### §1.2 Build-time usage
The dev reads the PRD + this doc together; AUTO criteria wire into CI as fitness functions (assert the operator surface emits the action event + `actor_role`; assert the platform-wide producer-write count = 1; assert the console composes from the recorded events); MIXED + HUMAN are scheduled, not surprised. **The launch-critical criteria (the surface exists + operator-driven + audited + the producer-write boundary) verify at the integrated launch; the deferred-automation criteria carry their "verified when X lands" note to the roadmap.**

### §1.3 Sign-off cadence
At the integrated-launch handover the engineering team produces the AUTO verdict report; Paolo reviews + executes the MIXED items + walks the HUMAN demo. States: OPEN → DEMOED → ACCEPTED. The Admin-Panel surface is **delivered** when every launch-scope criterion is ACCEPTED (the seam criteria carry their "verified when the automation/producer-UI lands" state to the roadmap).

### §1.4 Anchors + the criterion ID scheme
PRD §-numbers refer to [`../02-prd/Admin_Panel_PRD_v0.3-MVP.md`](../02-prd/Admin_Panel_PRD_v0.3-MVP.md). Module-AC pointers refer to the named module's v0.3-MVP acceptance doc (`Module_X_Acceptance_v0.3-MVP.md`). DEC refers to [`greenfield/04-decisions/decisions.md`](../../reference/v1.1/04-decisions/decisions.md) (bridged, never extended — see the MVP decisions register). Criterion IDs: **AC-AP-PWB-*** (producer-write boundary, §2) · **AC-AP-INV-*** (operator-capability inventory, §3) · **AC-AP-CON-*** (cross-module consoles, §4) · **AC-AP-XM-* / AC-AP-MA-*** (composed-surface + multi-actor, §5) · **AC-AP-SEAM-*** (the full-surface seam, §6) · **AC-AP-DEMO-1** (the HUMAN demo).

### §1.5 Format convention (the surface-side discipline)
Every criterion verifies the **Admin-Panel-surface side**: the capability is *exposed* as an operator surface, *operator-driven* (`actor_role: newco_ops`, or the one producer write), *audited*, and (for consoles) *composes/triages from the recorded events*. Where a criterion references a downstream behaviour (an FSM transition, an event emission, a ledger mutation), it carries a **→ Module_X_AC** pointer; the downstream half is verified there, **not re-verified here.**

---

## §2 The producer-write boundary acceptance (block (c) — PRD §2)

*The framing contract for the whole surface. → PRD §2; L-PP / K-Q4; DEC-083/115; D23.*

| # | Criterion | Anchor | Tag |
|---|---|---|---|
| **AC-AP-PWB-1** | *(the boundary — the load-bearing count)* Exactly **ONE** producer write exists platform-wide: **Module K membership approve/decline** (incl. waitlist approval). A scan of every producer-facing write surface across 0/K/A/D/S/B/C/E returns exactly one with `actor_role: producer` capability — the K membership decision; **all others are `newco_ops`-only at launch.** | PRD §2.1; Module K §3.1; → Module_K_AC | AUTO — enumerate producer-write surfaces; assert count = 1 |
| **AC-AP-PWB-2** | The one producer write (K membership approve/decline) is exercisable from the Producer Portal (`actor_role: producer`) OR operator-run on the producer's behalf (`actor_role: newco_ops`) — DEC-115 parity; both paths drive the same backend approval. | PRD §2.1; Module K §3.1, §4.2.1 | AUTO → Module_K_AC |
| **AC-AP-PWB-3** | *(no backend cut)* Every other producer/back-office write (allocation ops A; procurement ops D; Club-Offer authoring S; inventory ops B; fulfilment ops C; finance ops E; catalog ops 0; the K operator-driven producer writes — invitation/Hero/capacity) is exposed as an **operator-driven Admin-Panel surface** (`actor_role: newco_ops`); the backend operation exists (DEC-083 parity); **only the producer write UI is deferred.** | PRD §2.1 (table); §3 (per-module) | AUTO — assert each module's writes exposed operator-side |
| **AC-AP-PWB-4** | *(D23 — KEPT)* The Producer Portal read + full 7-section self-serve reporting is available at launch (reads A/S/D/E/K + B — sell-through **measured against `received_to_date` with the committed `qty` alongside (MVP-DEC-037)**, settlement projections, PO status, **received-to-date per allocation (Module B §8.3)**, financial dashboards, membership/club state); the Producer Portal is read-only except the one write (AC-AP-PWB-1). | PRD §2.2; D23; MVP-DEC-037 | AUTO (read availability) + MIXED (7-section completeness) |
| **AC-AP-PWB-5** | *(consumer exemption)* The consumer storefront / cellar / Bottle Page (browse/buy/cart/checkout/cellar/14-day-cancellation S; cellar render + in-transit display C; Bottle Page B) are **self-serve — NOT Admin-Panel surfaces**; no operator-write path replaces the consumer's self-serve action (the Customer-Care operator acts *on* consumer state — substitution/refund/cancellation — but the consumer's own surfaces stay self-serve). | PRD §2.3; master §3; → Module_S_AC / Module_C_AC / Module_B_AC | AUTO |
| **AC-AP-PWB-6** | *(audit envelope — floor chain 6 arm)* Every operator action through the Admin Panel records the standard audit envelope: `actor_role` + actor identity + timestamp + action + entity reference, on the event the action drives. Discovery Offer operations always carry `actor_role: newco_ops` (Admin-Panel-only). | PRD §1.3; DEC-083; Module S §15.2 | AUTO — assert envelope on every operator-driven event |

---

## §3 The per-module operator-capability inventory acceptance (block (a) — PRD §3)

*Each criterion asserts the capabilities are EXPOSED + operator-driven + audited; the downstream behaviour is verified in the owning module AC (pointer). One criterion per module (the inventory is reference, not re-spec — PRD §3).*

| # | Criterion | Anchor | Tag |
|---|---|---|---|
| **AC-AP-INV-0** | *(catalog console)* The Admin Panel exposes the Module 0 catalog operator surface: entity create (adapter **or manual baseline** — manual-first, Mod0-Q3), review+approve (3-step, role-count admin-configurable — Mod0-Q2), retire/re-activate (operator-driven cascade), bulk import, Format/Case-Config governance, enrichment-metadata update. **Zero producer writes.** Downstream (the lifecycle FSM + cascade) → Module_0_AC. | PRD §3.0; Module 0 §2/§4/§5 | AUTO → Module_0_AC |
| **AC-AP-INV-K** | *(onboarding + compliance-ops console)* The Admin Panel exposes the Module K operator surface: onboarding flows (direct/club-link/invitation), KYC management, sanctions-match review (order-completion-gate queue), **Hold place/lift** (the cross-cutting compliance action), suspension/producer-offboarding, **GDPR right-to-erasure approval**, producer onboarding (record/KYC/ProducerAgreement/Club/3-step Producer-activation), marketing-consent review, and **the one producer write (membership approve/decline)**. Downstream (KYC/sanctions/Hold/GDPR FSMs — floor chains 2/6) → Module_K_AC. | PRD §3.K; Module K §2/§3.1/§9/§4.8/§8.2/§12 | AUTO → Module_K_AC |
| **AC-AP-INV-A** | *(allocation-ops console)* The Admin Panel exposes every Module A allocation operation operator-driven (`newco_ops`): create, publish (DRAFT→ACTIVE, operator-publish-post-PO-commit uniform — DEC-183), all mid-life mutations, recall trigger, close/retire. **Zero producer writes, no backend cut.** **Plus the allocation-position read (MVP-DEC-037):** the four-figure strip (committed `qty` / `received_to_date` / issued / available-to-sell — same figures + order as the Producer Portal) + the **soft under-receipt flag** at close/retire and on Accept-Shortage (non-blocking; no Module A state change). Downstream (the Allocation FSM + no-oversell Layer-1) → Module_A_AC; (the `received_to_date` derivation) → Module_B_AC AC-B-FSM-22. | PRD §3.A; Module A §3.3; Module B §8.3 | AUTO → Module_A_AC |
| **AC-AP-INV-D** | *(procurement-ops console)* The Admin Panel exposes the Module D procurement spine operator-driven: PI creation (Direct-Purchase PI deferred), PO lifecycle, issuance-gate override, cost-finalization, inbound 3-gate QC (documents side — DEC-194), discrepancy resolution (6-path enum), SupplierProducerLink, producer-initiated recall (operator-driven). **Zero producer writes.** Downstream (the procurement entities/FSMs) → Module_D_AC; detailed console at §4.6. | PRD §3.D; Module D §3.6 | AUTO → Module_D_AC |
| **AC-AP-INV-S** | *(offer-authoring + customer-care console)* The Admin Panel exposes the Module S surface: Club-Offer authoring (create/publish/FSM/Hero/Layer-3/promo — Admin-Panel-driven, producer UI deferred), Discovery curation (Admin-Panel-only; single-producer at launch — D7), order-completion sanctions/Hold review (the consumer-side enforcement point), and Customer Care (refund + cause + store-credit-105% judgment D6; supervisor-override refund; voucher substitution; pre-shipment cancellation; INV3 cycle). **Zero producer writes; consumer storefront exempt.** Downstream (the Offer/Order/Voucher FSMs) → Module_S_AC. | PRD §3.S; Module S §2/§15/§12 | AUTO → Module_S_AC |
| **AC-AP-INV-B** | *(inventory-integrity console)* The Admin Panel exposes the Module B surface: stocktake plan/run (manual-first — D16), inventory adjustment (single-supervisor; committed-inventory protection FLOOR), QuarantineRecord triage (4 paths; cascades manual-first), receiving physical-match discrepancy (manual-first round-trip — N1), NFC re-tag / destruction recording. **Zero producer/consumer self-serve writes** (the one customer surface = read-only Bottle Page). Downstream (the integrity-core FSMs) → Module_B_AC; detailed console at §4.5. | PRD §3.B; Module B §0.8/§11–§15 | AUTO → Module_B_AC |
| **AC-AP-INV-C** | *(fulfilment-ops console)* The Admin Panel exposes the Module C surface: SO supervision + pick/pack/dispatch (4 Logilize streams; pickup-handover recorded), pick-discrepancy resolution, white-glove destination quote (D3), manual shipping-fee quote, returns/replacement FSM (D14 manual-first), recall reverse-shipment (D15), carrier/excise/DDP-DAP config. **Zero producer writes; consumer cellar/in-transit reads exempt.** Downstream (the SO + Returns FSMs) → Module_C_AC; detailed consoles at §4.1/§4.3/§4.4. | PRD §3.C; Module C §0.9/§4/§6/§7/§10/§12 | AUTO → Module_C_AC |
| **AC-AP-INV-E** | *(finance-ops console — cleanest L-PP)* The Admin Panel exposes the Module E surface: operator-run settlement composition + Xero AP (D19), manual INV3 dunning + K-Hold placement (D4), bank-transfer reconciliation (operator-fallback), FX-variance review, Xero exception/retry queue, chargeback dispute-evidence (D21 step 4), admin-configurable thresholds. **Zero producer/consumer self-serve writes — no write UI to defer, no backend cut.** Downstream (the financial-event recording + GL boundary) → Module_E_AC; detailed console at §4.2. | PRD §3.E; Module E §1.4/§3/§4/§6/§7 | AUTO → Module_E_AC |

---

## §4 The net-new cross-module operator consoles acceptance (block (b) — PRD §4)

*The substantive net-new content. Each criterion verifies the Admin-Panel-side workflow contract; the downstream behaviour → the owning module AC.*

### §4.1 The shared Logilize discrepancy queue (B + C — DEC-141)

| # | Criterion | Anchor | Tag |
|---|---|---|---|
| **AC-AP-CON-LQ-1** | *(the unified surface)* A **single** "Logilize discrepancy" queue in the Admin Panel receives BOTH the C-side fulfilment discrepancies (pick — serial/quantity/batch mismatch + breakage-at-pick) AND the B-side inventory-state discrepancies (QuarantineRecord resolution, `InboundBatchDiscrepancy` flow-back, stocktake variance). One operator triages both kinds. | PRD §4.1; DEC-141; Module B §15.3; Module C §4.3/§4.4 | AUTO |
| **AC-AP-CON-LQ-2** | *(B/C boundary on resolution)* A resolution recorded from the queue lands in the **correct module** per the bottle-state-vs-inventory-state boundary: a C-side resolution fires `DiscrepancyResolutionRecorded` + clears the SO `manual_review` flag; a B-side resolution records the QuarantineRecord / adjustment / stocktake path in B. → Module_C_AC (C-side) / Module_B_AC (B-side). | PRD §4.1; Module C §4.4; Module B §15.3 | AUTO → Module_B_AC / Module_C_AC |
| **AC-AP-CON-LQ-3** | *(real-time; manual-first landing)* Reconciliation is real-time event-driven (no batch jobs at launch); the D16 manual-first inventory workflows (Module B §0.2) land in this queue as their operator triage surface. The reconciliation algorithm + queue UX are tech (DEC-073 — out of scope). | PRD §4.1; DEC-073 | AUTO (event-driven landing) |

### §4.2 The finance-ops console (E) — the most load-bearing

| # | Criterion | Anchor | Tag |
|---|---|---|---|
| **AC-AP-CON-FO-1** | *(D19 — operator-run settlement; the recording is the seam)* The console lets the operator **compose the producer settlement statement(s) from the recorded settlement-input events** (the E-emitted `SupplierPaymentCompleted` + D's `InboundEventCostFinalized` + S's `DiscoveryRevenueShareAccrued` + cause-tagged refunds + reversals + C/B NonRevenueCost/cost-basis) + **run Xero AP manually.** The settlement-input recording is whole at launch (the seam); the engine is the roadmap. → Module_E_AC (the recording criteria stand FLOOR). | PRD §4.2(a); Module E §4.4/§4.7; DEC-156/180 | MIXED — assemble the 5-section statement from recorded events for a period; Paolo confirms |
| **AC-AP-CON-FO-2** | *(D4 — manual INV3 dunning)* The console lets the operator drive the INV3 failed-charge chain **manually on the first cycle**: monitor failed charge → operator-triggered re-charge/reminder (Stage 1) → operator places K-Hold `STORAGE_PAYMENT_FAILED` (Stage 2) → operator drives Suspension past grace (Stage 3). The `StoragePaymentFailed`→Hold→Suspension chain + admin-configurable thresholds are the seam. → Module_E_AC / Module_K_AC (the Hold creation). | PRD §4.2(b); Module E §3.3; N2 | AUTO (the manual steps drive the chain) → Module_K_AC |
| **AC-AP-CON-FO-3** | *(D4 floor — never deferred)* The sanctions/Hold re-read at INV3 charge (DEC-181) + the Hold-no-auto-lift discipline are exercised at launch regardless of the manual-first orchestration (the compliance gate at charge is FLOOR; write-off does not auto-lift). → Module_E_AC. | PRD §4.2(b); Module E §3.3; DEC-181 | AUTO → Module_E_AC |
| **AC-AP-CON-FO-4** | *(bank-transfer reconciliation)* The console exposes the operator-fallback reconciliation surface (webhook fails / no auto-match → operator-confirmed match → `BankTransferFundsCleared`). Already operator-driven by spec. → Module_E_AC. | PRD §4.2(c); Module E §3.2; DEC-159 | AUTO → Module_E_AC |
| **AC-AP-CON-FO-5** | *(FX-variance + Xero exception)* The console exposes: the FX-variance review (`FXVarianceRecorded` — D18 dual-record is FLOOR, the surface is review not recompute) + the Xero sync-failed retry queue (per-event sync FSM; reversal-ordering escalation; post-sync immutability FLOOR; admin-configurable retry). → Module_E_AC. | PRD §4.2(d)(e); Module E §7.1/§7.2 | AUTO → Module_E_AC |
| **AC-AP-CON-FO-6** | *(D21 — chargeback step 4)* The chargeback chain is **automated from day 1** (auto-ingestion + auto-`CustomerChargebackFlagged` → K Hold); the **one operator surface is step 4 — submit dispute evidence per the 7-BD SLA.** N2: the chargeback Hold trigger is automated, composing with the manual-first storage-payment trigger on K's trigger-agnostic registry. → Module_E_AC / Module_K_AC. | PRD §4.2(f); Module E §6.1; D21; [[keep-payment-automation]] | AUTO → Module_E_AC |
| **AC-AP-CON-FO-7** | *(admin-configurable thresholds)* The console exposes admin-configurable finance thresholds: dunning cadences, retry windows, FX buffer %, refund-compensation premiums (default 105%), Hold-lift authority. The threshold *values* are config; the *mechanism* is the surface. | PRD §4.2; Module E §1.4 | AUTO |

### §4.3 The white-glove quote flow (C — D3)

| # | Criterion | Anchor | Tag |
|---|---|---|---|
| **AC-AP-CON-WG-1** | *(the Tier-2 manual fallback)* A non-eligible destination offers a "send shipping request" CTA (not a hard block) → Customer Care ticket → operator review → **approve** (manual carrier quote `quote_origin = manual`; SO proceeds; approval recorded for audit) OR **deny** (continued storage / pre-shipment cancellation). → Module_C_AC. | PRD §4.3; Module C §7.1/§6.1; DEC-147 | MIXED — the approve/deny judgment; Paolo confirms a sample |
| **AC-AP-CON-WG-2** | *(floor even in the manual path)* INV2 tax-correctness is preserved in the white-glove flow: `ExciseCalculated` runs even in the manual path; OFAC screening applies at all destinations regardless of tier. **The floor cannot be cut by the manual routing.** → Module_C_AC. | PRD §4.3; Module C §8.2/§7.2 | AUTO → Module_C_AC |

### §4.4 The returns/replacement + recall consoles (C — D14/D15)

| # | Criterion | Anchor | Tag |
|---|---|---|---|
| **AC-AP-CON-RR-1** | *(D14 — manual-first FSM)* Operators run the DEC-184 Returns/Replacement FSM **end-to-end via the Admin Panel** (REPORTED→INVESTIGATED→APPROVED→REPLACEMENT_ISSUED→CLOSED + REJECTED/WITHDRAWN); the discipline is KEPT (original-voucher-preserved INV-C-08; no-cash-refund INV-C-07). The FSM automation (auto-transitions/routing/notification) is the seam. → Module_C_AC. | PRD §4.4(a); Module C §10.2; DEC-184 | AUTO → Module_C_AC |
| **AC-AP-CON-RR-2** | *(supervisor-override closure — multi-actor)* The supervisor-override-refund closure path (APPROVED→CLOSED, `closure_path = supervisor_override_refund`) requires a distinct supervisor actor (initiator ≠ supervisor — §5.2). → Module_C_AC / Module_S_AC. | PRD §4.4(a); Module C §10.2; Module S §12.3 | AUTO (the multi-actor gate) |
| **AC-AP-CON-RR-3** | *(D15 — manual recall)* The recall console lets the operator initiate the reverse-shipment via the Admin Panel (`ReverseShipmentDispatched`) after out-of-system coordination; **unsold-only (ISSUED Vouchers immune — INV-C-06).** Full reverse-inbound mechanics are deferred (the manual posture is the launch floor). → Module_C_AC. | PRD §4.4(b); Module C §12; DEC-117 | AUTO → Module_C_AC |

### §4.5 The manual stocktake / quarantine / adjustment surfaces (B — D16)

| # | Criterion | Anchor | Tag |
|---|---|---|---|
| **AC-AP-CON-IV-1** | *(stocktake manual-first)* The console lets the operator schedule manual counts + review variance manually; book variances through the adjustment path / QuarantineRecord / the Logilize queue; `StocktakeReconciled` on resolution. The auto-reconciliation engine + cadence automation are the seam. → Module_B_AC. | PRD §4.5; Module B §12 | AUTO → Module_B_AC |
| **AC-AP-CON-IV-2** | *(adjustment — committed-inventory protection FLOOR; multi-actor)* The console runs operator proposal → **single-supervisor approval (proposer ≠ supervisor)**; a negative-delta adjustment breaching committed inventory is REJECTED → `InventoryShortfallDetected` to A (cannot proceed until A's `VoucherCancelled` releases the commitment). **Committed-inventory protection is FLOOR — verified at launch.** → Module_B_AC / Module_A_AC. | PRD §4.5; Module B §13; DEC-099 | AUTO → Module_B_AC |
| **AC-AP-CON-IV-3** | *(quarantine-before-trust FLOOR; cascades manual-first)* The console runs the QuarantineRecord triage (4 paths — associate/create-new[explicit sign-off, no auto-create]/reject/escalate); resolved records immutable; **the automated resolution cascades are manual-first (operator records the cost-basis/financial/ATP follow-ups).** The gate is FLOOR; the cascades are the seam. → Module_B_AC. | PRD §4.5; Module B §14 | AUTO → Module_B_AC |

### §4.6 The manual procurement / discrepancy surfaces (D)

| # | Criterion | Anchor | Tag |
|---|---|---|---|
| **AC-AP-CON-PR-1** | *(procurement spine operator-driven)* The console exposes the PI/PO lifecycle (PI V1/V2 auto; Direct-Purchase PI deferred), the issuance-gate override (`POIssuedUnderNonActiveAgreement` + audit), and cost-finalization — all operator-driven. → Module_D_AC. | PRD §4.6; Module D §3.6/§5/§6 | AUTO → Module_D_AC |
| **AC-AP-CON-PR-2** | *(N1 — manual-first discrepancy, identical with B)* The console runs the manual receiving-discrepancy handling: the operator opens the discrepancy + records the resolution path (the **6-path enum**) manually within the 5-WD window — **landed identically to Module B's manual-first depth** (the D↔B interlocks read consistently). The DISCREPANCY state + 6-path enum + consumers are the seam. → Module_D_AC / Module_B_AC. | PRD §4.6; Module D §3.6/§13.3; Module B §11.2; N1 | AUTO → Module_D_AC / Module_B_AC |

---

## §5 The composed-surface + multi-actor + audit-envelope acceptance (PRD §5)

| # | Criterion | Anchor | Tag |
|---|---|---|---|
| **AC-AP-XM-1** | *(the canonical composed surface)* "Refund a customer's order" composes K + S + C + E on one operator surface: cancellation eligibility (S) + voucher/shipping state (S/C) + Hold/sanctions (K) read together → operator records cause + store-credit-105% judgment (D6, S) → refund executed + recorded (E). Each module owns its half (pointers); the criterion asserts they **compose on one surface.** → Module_S_AC / Module_E_AC / Module_K_AC / Module_C_AC. | PRD §5.1; D6 | MIXED — the store-credit-105% judgment; Paolo confirms |
| **AC-AP-XM-2** | *(composed-surface coverage)* The composed surfaces flagged in PRD §3 (allocation cluster A; procurement cluster D+B; inventory cluster B; fulfilment cluster C; finance cluster E; onboarding/compliance cluster K) each present as a single operator surface spanning their modules; the downstream cross-module events → the owning module ACs. | PRD §5.1; §3 (composed? column) | AUTO (surface composition) |
| **AC-AP-MA-1** | *(multi-actor — 3-step lifecycle)* The Admin Panel surfaces a "second actor required" affordance on the 3-step Creator→Reviewer→Approver lifecycle (Module 0 entity activation; Module K Producer activation); **self-approval is rejected** (distinct actors on the steps that run; role-count admin-configurable). → Module_0_AC / Module_K_AC. | PRD §5.2; Module 0 §4.2; Module K §4.4 | AUTO — assert self-approval rejected |
| **AC-AP-MA-2** | *(multi-actor — override + single-supervisor)* The supervisor-override pattern (S §12.3 post-shipment refund; C §10.2 closure) requires initiator ≠ authoriser; the single-supervisor-approval pattern (B §13 adjustment) requires proposer ≠ supervisor. The affordance is exposed; the gate blocks same-actor completion. → Module_S_AC / Module_B_AC. | PRD §5.2; Module S §12.3; Module B §13 | AUTO |
| **AC-AP-MA-3** | *(authority-tier downstream)* Beyond the multi-actor floor (AC-AP-MA-1/2), the surface is role-agnostic at launch — the authority-tier / RBAC / persona-gating policy is admin-configurable + downstream (`feedback_prd_rr_approval`); no PRD-layer role enum is asserted. | PRD §1.4; §5.2; `feedback_prd_rr_approval` | AUTO (no role-gating asserted) |
| **AC-AP-XM-3** | *(audit envelope — the surface's owned discipline)* Every operator-driven event carries `actor_role` + identity + timestamp + action + entity ref (AC-AP-PWB-6); the envelope composes the audit/retention floor (chain 6) with K's GDPR + E's 10-yr retention. → Module_K_AC / Module_E_AC. | PRD §1.3; DEC-083 | AUTO |

---

## §6 The "full target surface" seam acceptance (block (d) — PRD §6)

*The MVP slice is a clean SUBSET; the seam is real on both sides. These criteria verify the seam, not the full surface (the full surface is a roadmap deliverable — they carry a "verified when X lands" note).*

| # | Criterion | Anchor | Tag |
|---|---|---|---|
| **AC-AP-SEAM-1** | *(the consoles record what their engines consume — additive)* Each manual-first console records the same data its future automated engine consumes: the finance-ops settlement composition reads the recorded settlement-input events (the engine reads the same — D19); the inventory consoles book through the kept integrity-core entities/events (the automation reads the same — D16); the returns console runs the kept DEC-184 FSM (the automation drives the same — D14). **The automated arm is purely additive** (verified when the engine/orchestration lands). → the owning module ACs (the recording/integrity criteria stand FLOOR). | PRD §6 axis 1; §0 | AUTO (the recording is whole) — automated arm: verified when it lands |
| **AC-AP-SEAM-2** | *(the operator writes sit on the producer-UI backend — additive)* Every operator-driven write (A/D/S/B/C/E + the K operator-driven producer writes) sits on the DEC-083/115 backend its future producer write UI will use (AC-AP-PWB-3); the producer UI restores on the same backend (verified when the producer UI lands). | PRD §6 axis 2; §2.1; DEC-083/115 | AUTO (the backend parity) — producer UI: verified when it lands |
| **AC-AP-SEAM-3** | *(the platform layer is deferred, not absent)* The UX/IA/design-system layer (DEC-073) + the RBAC/authority-tier model (`feedback_prd_rr_approval`) are explicitly deferred to the roadmap (the `greenfield/12-admin-panel/` exploration is the read-only design-side north-star); the MVP slice is a clean subset, not a degraded surface. The full surface lives in `04-roadmap/` (master §5 #12). | PRD §6 axis 3, §8; master §5 #12 | AUTO (the seam pointer) |

---

## §7 MVP re-baseline note + criteria summary

### §7.1 Re-baseline note (NEW — no predecessor)

This acceptance doc is authored **fresh** — there is no v1.1 Admin-Panel acceptance template to re-cut (the Admin Panel had no v1.1 PRD; PRD §8). It validates the four content blocks of the Admin-Panel PRD at the **operator-surface / workflow product-spec layer** (DEC-073 — not UI/UX acceptance). It is **self-contained** (DEC-074) and **verifies the Admin-Panel-surface side only** — every downstream module behaviour carries a pointer to its owning module's v0.3-MVP acceptance doc, which is where it is verified. **No criterion re-verifies a module backend; no criterion asserts a UX/layout behaviour; no new scope is introduced** (the surface contract is settled — Phase C item L). The genuine drifts are flagged in PRD §9.1 (the Module E §5.11 stale cross-reference; the intentionally-unspecced RBAC layer).

### §7.2 Criteria summary

| Block | Section | Criteria | Tag mix |
|---|---|---|---|
| (c) producer-write boundary | §2 | AC-AP-PWB-1..6 | 5 AUTO / 1 AUTO+MIXED |
| (a) operator-capability inventory | §3 | AC-AP-INV-0/K/A/D/S/B/C/E | 8 AUTO (→ module ACs) |
| (b) net-new consoles | §4 | AC-AP-CON-LQ-1..3 / FO-1..7 / WG-1..2 / RR-1..3 / IV-1..3 / PR-1..2 | ~15 AUTO / 3 MIXED |
| composed-surface + multi-actor + audit | §5 | AC-AP-XM-1..3 / MA-1..3 | 5 AUTO / 1 MIXED |
| (d) full-surface seam | §6 | AC-AP-SEAM-1..3 | 3 AUTO (seam pointers) |
| end-to-end demo | §5.3 | **AC-AP-DEMO-1** | 1 HUMAN |

**AC-AP-DEMO-1 (HUMAN).** Paolo walks one end-to-end operator session: compose a settlement statement from the recorded events + run Xero AP (finance-ops console, D19); triage one Logilize discrepancy across the B+C queue; run one returns/replacement FSM end-to-end; confirm the `actor_role` audit envelope on each action + that exactly one producer write exists. **Confirms the Admin Panel is the launch's load-bearing operational surface (D24), composed from the recorded events, with the producer-write boundary whole.**

**Distribution: ~85% AUTO / ~13% MIXED / ~2% HUMAN.** The deferred-automation criteria (the §6 seam + the manual-first console arms) carry their "verified when the engine/orchestration/producer-UI lands" state to the roadmap; the launch-critical criteria (the surface exists + operator-driven + audited + the producer-write boundary + composes from the recorded events) verify at the integrated launch.

---

## §8 Cross-references

- **Companion spec**: [`../02-prd/Admin_Panel_PRD_v0.3-MVP.md`](../02-prd/Admin_Panel_PRD_v0.3-MVP.md) (the four content blocks).
- **The spine**: [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) item L (RESOLVED Q1).
- **Authoritative scope brief**: master [`../00-method/Phase_D_Kickoff_Prompt.md`](../00-method/Phase_D_Kickoff_Prompt.md) §6.D.
- **The 8 module acceptance docs (the downstream half is verified there — pointers throughout)**: `Module_0_Acceptance_v0.3-MVP.md` · `Module_K_…` · `Module_A_…` · `Module_D_…` · `Module_S_…` · `Module_B_…` · `Module_C_…` · `Module_E_…`.
- **Decisions index**: [`../04-decisions/MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md).
- **The full target surface → roadmap**: `04-roadmap/Post_Launch_Roadmap_v0.1.md` + the read-only design-side reference `greenfield/12-admin-panel/`.

---

*End of Admin-Panel Acceptance v0.3-MVP — **DRAFT, awaiting batch ratification (Paolo).** NEW — authored fresh (no v1.1 predecessor to re-cut). Verifies the four content blocks of the Admin-Panel PRD at the operator-surface / workflow product-spec layer (DEC-073 — not UI/UX): (c) the producer-write boundary (exactly ONE producer write; D23 read; consumer exempt; the `actor_role` envelope); (a) the per-module operator-capability inventory (exposed + operator-driven + audited; downstream → the owning module AC); (b) the net-new cross-module consoles (the finance-ops console E + the Logilize discrepancy queue B+C substantive; plus white-glove, returns/recall, stocktake/quarantine/adjustment, procurement); (d) the full-target-surface seam (a clean subset, real on both sides). **The Admin-Panel-surface side only — every downstream behaviour points to its owning module AC.** ~85% AUTO / ~13% MIXED / ~2% HUMAN. Recommend; Paolo decides. Nothing handed off until Phase E.*
