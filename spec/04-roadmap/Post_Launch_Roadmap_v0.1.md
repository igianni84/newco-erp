# NewCo ERP — Post-Launch Roadmap v0.1 (the deferred-set buildout register)

- **Version**: v0.1 (Phase D re-baseline — **artefact #12**; the single post-launch buildout register that collects the union of all deferred/simplified items across the whole re-baseline + organises them into one coherent forward plan). **A consolidation + organisation of settled defers — NOT new scope.**
- **Date**: 2026-06-08
- **Status**: **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E. **It records + organises the deferred set; it does NOT re-decide it** (the defers are all ratified — Phase B cut-sheets + Phase C + the 9 v0.3-MVP PRDs). It takes **no new scope cuts**, re-opens **no ratified defer**, and **does not sequence the build** (the launch build order + the build sizing/estimation step (#13 / dev-team) are the release index / build-workplan).
- **Owner**: Paolo (product + business — decides). Claude recommends.
- **Predecessor (the v1.1 deferred register this roadmap EXTENDS — frozen, NEVER edited, plan R4)**: [`../../reference/v1.1/03-qa/qa.deferred.md`](../../reference/v1.1/03-qa/qa.deferred.md) (the first NewCo-wide deferred-items register; DRAFT 2026-05-13; 17 Q-OQ atoms + 18 Phase-2+ DEC calls + 4 vendor/external rows + 12 BMD §13 explicit-out items + the re-evaluation-trigger map). **This roadmap re-baselines its content into `mvp/04-roadmap/`** — it accretes the **newly-deferred** MVP items (from the strip-down) onto the **already-deferred** v1.1 set (carried verbatim with its existing hooks). It does **not** edit the frozen file. Also: [`../../reference/v1.1/01-prd/Architecture_v0.2.md`](../../reference/v1.1/01-prd/Architecture_v0.2.md) §6 (the v1.1 out-of-launch surfaces table).
- **The consolidated seam this roadmap expands (the spine)**: [`../02-prd/Architecture_v0.3-MVP.md`](../02-prd/Architecture_v0.3-MVP.md) **§6** (Out-of-Launch + Deferred Surfaces → roadmap seam). The Architecture **names the seam**; this roadmap **details each item's seam (P1) + re-introduction hook + dependencies + expected next-decision moment**.
- **The structural brief (the coordinated-restoration coherence)**: [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) **item N** (§4-N: the deferred-set roadmap coherence) — the four tri-/multi-module restorations as coordinated sets, each with the no-orphaned-DEFER verification. **This is the roadmap's organising principle.**
- **The settled input (the per-item seams — collected, not re-derived)**: the nine v0.3-MVP PRDs' deferred-set sections — [Module 0 §17](../02-prd/Module_0_PRD_v0.3-MVP.md) · [Module K §17](../02-prd/Module_K_PRD_v0.3-MVP.md) · [Module A §14](../02-prd/Module_A_PRD_v0.3-MVP.md) · [Module D §18](../02-prd/Module_D_PRD_v0.3-MVP.md) · [Module S §20](../02-prd/Module_S_PRD_v0.3-MVP.md) · [Module B §21](../02-prd/Module_B_PRD_v0.3-MVP.md) · [Module C §16](../02-prd/Module_C_PRD_v0.3-MVP.md) · [Module E §11](../02-prd/Module_E_PRD_v0.3-MVP.md) · [Admin Panel §6](../02-prd/Admin_Panel_PRD_v0.3-MVP.md).
- **Decisions index**: [`../04-decisions/MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md) (the D-dials with ⛔ deferred / 🔁 superseded-at-launch flags; the four RECONCILEs; item N).
- **Methodology DECs binding this document**: **DEC-072** (no accounting positions — Module E records financial events; **Xero decides GL**); **DEC-073** (name the deferred *capability* + the *seam*, **not** the mechanism — no UX/IA, no API/payload, no schema, no infra); **DEC-074** (self-contained — anchors restated inline; cite the v1.1 register + the 9 PRDs' deferred sets + Phase C item N + the Architecture §6).

---

## §0 What this roadmap is (the calibration)

### §0.1 Purpose + altitude

**This is the single post-launch buildout register — the destination every deferred/simplified item across the whole re-baseline has been pointing to (P1).** Every v0.3-MVP PRD ends with a deferred-set section that names each item's **seam** and says "→ `04-roadmap/Post_Launch_Roadmap_v0.1.md`." This file is that file: it (a) collects the **union of all deferred items** — the newly-deferred (from the MVP strip-down) **+** the already-deferred (v1.1's set, carried verbatim); (b) frames the **multi-module restorations as coordinated sets** (Phase C item N); (c) carries the **full Admin-Panel surface** as a buildout target; and (d) **extends** the v1.1 deferred register (`greenfield/03-qa/qa.deferred.md`), accreting onto its structure.

**The genuinely net-new content of this artefact is the *organisation*** — the coordinated-set framing, the cross-set dependencies, and the unified register. **The items + the seams already exist** (in the PRDs + Phase C + the Architecture §6 + qa.deferred.md); this roadmap *collects* them into one coherent forward plan. It is a consolidation + organisation, not a re-derivation.

### §0.2 The five things this roadmap lands (master §5 row 12; kickoff §C)

1. **(a) The newly-deferred MVP items** (from the strip-down) — each with its **seam (P1) + re-introduction hook + dependencies**, organised as Phase C item N frames them: the **four coordinated multi-module restorations** (§1) + the **single-module manual-first / simplify automations** (§2) + **Direct Purchase** (§3).
2. **(b) The full Admin-Panel surface** as a buildout target (§4) — the three additive axes (restored automation consoles + producer-portal write-UIs + UX/IA/RBAC platform layer). The full Admin-Panel PRD is itself a roadmap deliverable (it had no v1.1 predecessor).
3. **(c) The v1.1 already-deferred set** — carried **verbatim** (§5; do not re-cut, do not re-derive a seam qa.deferred.md already states).
4. **(d) The coordinated-restoration coherence** (Phase C item N — §6) — the no-orphaned-DEFER spine + the cross-set dependency map. **This is what makes the deferred set a *coherent* forward plan, not a flat list.**
5. **(e) The disposition per item** (§0.5 schema, applied throughout + the §7 trigger map) — the seam, the re-introduction hook, the dependencies, the expected next-decision moment / re-evaluation trigger (extending qa.deferred.md's structure).

### §0.3 What this roadmap is NOT (the hard boundaries)

- **NOT new scope.** It collects the settled defers; it does not invent a deferred item, re-cut, or re-derive a seam. The seams are in the PRDs — collected here.
- **NOT a re-litigation of the defers.** They are ratified (Phase B + C + the 9 PRDs). The roadmap *records + organises* them; it does not re-decide them. **Especially:** **D21 chargeback automation is KEPT — NOT a roadmap item** ([[keep-payment-automation]] — payment automation is floor); **D18 dual-record FX is FLOOR-kept**; the **integrity cores** (no-oversell, committed-inventory, quarantine-before-trust, receiving, KYC/sanctions/Hold, tax-correct invoicing) are **FLOOR-kept** — only the *automations / peripherals* defer.
- **NOT a build-plan / sequencing.** The launch build sequence + the build sizing/estimation step (#13 / dev-team) are the **release index / build-workplan (#13)**. This roadmap is *post-launch* scope; it states **inter-item dependencies** (which deferred item waits on which), **not the launch build order**. (The build-sequencing flag — Module B's floor artefacts integration-ready by the integrated launch — is a #13 / dev-team-sizing item, Phase C item G/Q5, not a roadmap item.)
- **NOT tech-implementation (DEC-073)** — name the *capability* + the seam, not the mechanism.
- **NOT an accounting position (DEC-072)** — Module E records; Xero decides GL.

### §0.4 How this roadmap extends `qa.deferred.md` (the predecessor it accretes onto)

`qa.deferred.md` is the **frozen v1.1 deferred register** (17 Q-OQ atoms + 18 Phase-2+ DEC calls + 4 vendor/external rows + 12 BMD §13 items + the trigger map). It is the predecessor this roadmap **re-baselines into `mvp/04-roadmap/`** — **never edited** (frozen audit anchor, plan R4). The structural relationship:

| `qa.deferred.md` (v1.1 register) | This roadmap (v0.3-MVP buildout register) |
|---|---|
| The **already-deferred** set (v1.1's deferrals) | **Carried verbatim** → §5 (with its existing re-introduction hooks + re-evaluation triggers) |
| (none — v1.1 had not yet stripped to a Lean MVP) | The **newly-deferred** set (the MVP strip-down) → §1 (coordinated sets) + §2 (single-module automations) + §3 (Direct Purchase) |
| (none — the Admin Panel was an implicit DEC-083 parity mirror) | The **full Admin-Panel surface** as a buildout target → §4 |
| §5 the re-evaluation-trigger map | **Extended** → §7 (adds the MVP-specific triggers: first storage cycle, first settlement close, EXT-1 scheduling, a Direct-Purchase deal, operator-load telemetry, producer self-serve demand) |
| The register-not-analysis rule (§0.2) | The same discipline: **consolidation, not re-analysis** — sourced from the PRDs + Phase C, no re-litigation, no new candidates |

### §0.5 The disposition schema (the per-item structure — extending qa.deferred.md)

Every deferred item below carries the same four-part disposition (the kickoff (e)):

- **Seam (P1)** — the kept-at-launch hook that makes the post-launch build *additive* (collected from the owning PRD; the recording / entity / FSM / event / enum that is KEPT so re-introduction does not re-architect).
- **Re-introduction hook** — *what gets built back* (the deferred capability) and *how it attaches* to the seam.
- **Dependencies** — which other deferred items / launch outcomes it waits on (the cross-set coherence, §6).
- **Re-evaluation trigger / next-decision moment** — *when* it is revisited (date / volume / external-decision / business-event — the §7 map).

---

## §1 The newly-deferred MVP set — the four coordinated multi-module restorations (the organising spine — Phase C item N)

> **The MVP strip-down deferred scope as *coordinated sets*, not scattered single cuts.** Four restorations span multiple modules; each **defers + restores as a coordinated set** — the modules that participate keep their piece of the seam in lockstep, so the capability re-introduces as one additive unit. **In every case the recording / entities / events / FSM / lineage are KEPT at launch as the seam; only the automation / construct / on-chain layer defers.** Phase C verified **no KEPT item silently depends on a deferred one** (§6). The first close of the timing-driven sets (settlement, dunning) is months post-launch — the defer is timing-safe.

### §1.1 Gifting (D5) — the coordinated **S + K + C** restoration

| Field | Disposition |
|---|---|
| **What defers** | Member-to-member voucher gifting: the **GIFTED Voucher state**, the 7-day accept flow, recipient-gate validation, and the **four `VoucherGift*` events** (Module S, §13 / §11.3). The **Voucher FSM runs 7 states at launch (GIFTED deferred → 8 with restore)**. Module K's gifting-initiation read-API idles. Module C's `is_gift` sub-flag + gift-recipient-address read + INV-C-10 idle. |
| **Seam (P1)** | **Module S:** the Voucher's **mutable customer-reference** (no hard single-permanent-owner — the ownership-transfer capability) + `originating_club_id` retained (S.21). **Module K:** the Hold read-API at gifting initiation is **generic and unchanged** — the surface simply isn't exercised. **Module C:** the `is_gift` sub-flag is **retained-but-unexercised** (rides Module S's voucher-ownership-transfer seam). |
| **Re-introduction hook** | Restore the GIFTED state + the 7-day accept flow + the four `VoucherGift*` events on Module S's mutable-customer-reference seam; light up K's gifting read-API and C's `is_gift` sub-flag. Additive — the Voucher FSM goes 7→8. |
| **Dependencies** | **None at launch** — no KEPT item depends on gifting (Phase C item N). Independent of the other three sets. *(It is the launch's only deferred customer-to-customer path — CruTrade-style P2P resale, §5.1, remains separately already-deferred; gifting is the lighter C2C capability per BMD §4.13.)* |
| **Re-evaluation trigger** | Post-launch product-roadmap review (gifting is a customer-value feature, not a floor/timing item — revisit on customer demand for member-to-member gifting). |

### §1.2 Discovery composites (D7) — the coordinated **S + A + 0** restoration

| Field | Disposition |
|---|---|
| **What defers** | The **multi-producer Discovery composite construct** (Module S, §6): the multi-FK atomic bind (DEC-097), the N-way atomic decrement + rollback (DEC-179), the composite cascade, the 5-rule × N publication-validation extension (§7.2), and the **composite OC-on-`P_d`** computation. At launch Discovery Offers are **single-producer**. |
| **Seam (P1)** | **Module S:** the Offer entity ships **single-FK-capable** (`composite_constituent_allocation_ids[]`). **Module A:** keeps the **per-constituent single-Allocation primitive** + the two-FK counterparty (`producer_id` always; `supplier_id` optional) + the per-constituent `commercial_terms` `C_i` (A.2/A.5). **Module 0:** keeps the **Composite SKU** (Mod0-Q1). |
| **Re-introduction hook** | Restore the multi-FK atomic-bind / N-way-decrement construct on Module S's single-FK-capable Offer; the per-constituent Module A primitive + the Module 0 Composite SKU already hold the shape. **Additive — each constituent is a normal per-bottle voucher; B/C/D/E see N normal vouchers, never a "composite."** |
| **Dependencies** | The **composite construct itself is independent** (no KEPT item depends on it — the constituents are normal vouchers). The **composite OC-on-`P_d` *computation*** intersects the **settlement engine (D19, §1.4)** — when both restore, the composite OC computes in Module E's engine. (The single-Allocation Discovery OC accrual is KEPT at launch; only the composite-OC computation waits on D19.) |
| **Re-evaluation trigger** | Post-launch — when NewCo wants to sell **multi-producer Discovery bundles** (Discovery-curation evolution; producer-relationship maturity — cross-ref the v1.1 DEC-115 producer-author-Discovery row, §5.1). |

### §1.3 NFT on-chain (D12) — the coordinated **B + downstream** restoration *(DECOUPLE ≠ DEFER — the VP is preserved)*

| Field | Disposition |
|---|---|
| **What defers** | The **on-chain layer** (Module B, §0.1/§21): NFT mint/burn, the custodial-wallet operational architecture, on-chain recovery, and the Bottle-Page chain-link content. The on-chain events idle — `NFTMinted` + the recovery-chain set (`NFTReissued`, `NFTBurnedAsTagDamaged`, `NFTLossInWalletDetected`, `BottleNFTBurnedAsDestroyed`, …). *(DECOUPLE off the launch critical path — not a delete; the value-proposition is preserved.)* |
| **Seam (P1)** | Each `SerializedBottle` carries **`nft_reference = NULL` at launch, back-fillable** when the on-chain workstream lands. The **re-scoped EXT-1 feature-flag** gates *only* the NFT/on-chain criteria, not the serialization workflow. **The per-bottle serialization workflow stays launch-ready** (NFC tag application + serial capture + the `SerializedBottle` ledger record + Logilize integration). **The non-serialized (NS) path is the universal fallback** — every downstream (S/C/E/Bottle-Page) degrades gracefully. |
| **Re-introduction hook** | Land the on-chain workstream behind EXT-1; **back-fill `nft_reference`** onto the already-serialized bottles; light up the NFT mint/burn chain + the Bottle-Page chain-link. Downstream consumers (S `VoucherShipped`→burn; C dispatch; E settlement read; Bottle Page) **switch from NS to the on-chain path additively** — no downstream rework. |
| **Dependencies** | Gated by the **already-deferred blockchain prerequisites (§5.4)**: **EXT-1** (blockchain-expert validation of the Wave-4 NFT/blockchain working hypotheses), **EXT-3** (smart-contract audit + governance + key-rotation), and the **NFT working-hypothesis cluster** (DEC-120/121/122/124/131). These are the on-chain set's gating prerequisites. |
| **Re-evaluation trigger** | **⚠️ TIME-SENSITIVE — Paolo-track near-term (Phase C Q3 / item J):** (1) **schedule the EXT-1 blockchain-expert review now** — or it becomes the launch critical path; (2) **the DEC-124 NFC tag-stock procurement lead-time** — tags apply at launch (serial + Bottle-Page URL) with the on-chain reference back-fillable, but the pre-launch tag-stock procurement lead-time makes the back-fill-content design time-sensitive. **The *design* work is dev-phase, but the *scheduling / procurement* is near-term** — flagged in §7.1 + carried to the dev-team sizing exercise. |

### §1.4 Settlement engine (D19) — the coordinated **E + D + S + A** restoration *(the recording is the seam)*

| Field | Disposition |
|---|---|
| **What defers** | The **supplier-settlement engine** (Module E, §4): the quarterly runs; the 5-section `ProducerSettlementStatementIssued` composition (DEC-156); the **OC 5% aggregation into Section D**; the **producer-fault clawback netting (Section C)**; the counterparty-disambiguation routing; the producer settlement-currency option; the settlement-statement FSM; the Xero AP routing → **operator-run first cycle(s)**. |
| **Seam (P1)** | **The recording is the seam.** **Module E** keeps **recording every settlement-input event** (§4.7) + routes each to Xero in real time at launch; the operator composes the first statement(s) **manually**; the engine reads the same records post-launch. **Module D** keeps recording all procurement/inbound financial events (`SupplierPaymentCompleted` [E-emits, R4], `InboundEventCostFinalized`, `DiscrepancyResolutionRecorded`, …). **Module S** keeps emitting the OC accrual (`DiscoveryRevenueShareAccrued` at INV1) + the cause-tagged refunds. **Module A** keeps the per-constituent `C_i` lineage. |
| **Re-introduction hook** | Build the engine to **read the recorded events** (it re-derives nothing — K's `OriginatingClubLocked` + A's lineage + S's accruals + D's cost events + E's payment records are all captured at launch); compose the 5-section statement; run the settlement-statement FSM + the Xero AP routing. **Additive — every upstream already records.** |
| **Sub-items that defer *with* the engine** | **OC 5% computation + the Section-D info-disclosure (DEC-180)** — the accrual is recorded; E computes the 5% + composes Section D when the engine is built. **Producer-fault clawback netting (D6, §2)** — the cause-tagged refunds are recorded; the netting nets when the engine is built. **Partial PO settlement (OQ-20, §5.3)** — `SupplierPaymentCompleted` is atomic per PO at launch; the multi-tranche extension lands with the engine. |
| **Dependencies** | **The recording is whole at launch** (all upstreams KEPT — Phase C item E confirmed the OC-accrual capture whole). **No KEPT item depends on the engine.** **Timing-safe:** the first producer-settlement close is **months post-launch** (the producer-settlement cadence). |
| **Re-evaluation trigger** | **The first producer-settlement close** (months out — the settlement cadence). Unblocks: the engine + the OC-5% computation + the clawback netting + the partial-PO-settlement extension together. |

---

## §2 The newly-deferred MVP set — the single-module manual-first / simplify automations

> **In each item the *automation* defers; the integrity-core / FSM / events / entities are KEPT as the seam.** The manual-first operator surface at launch **records the same data its future automated engine consumes** — the recording is whole; the automation is the roadmap. These restore **additively** (the automated arm replaces the manual-first console — §4 axis 1). Most are **timing-safe or volume-gated** (the first storage cycle is months out; returns/geography volume builds post-launch).

| # | Deferred automation (owner) | What defers | Seam (P1) — KEPT at launch | Re-introduction hook | Re-evaluation trigger |
|---|---|---|---|---|---|
| **D4** | **INV3 dunning orchestration** (Module E, §3.3) | The auto-retry → auto-`StoragePaymentFailed` → auto-Hold → auto-Suspension orchestration + the automated multi-cycle composition → **manual first cycle** | The **`StoragePaymentFailed` → K-Hold → Profile-Suspension event chain** + the admin-configurable staged thresholds + the multi-cycle rules are retained; the operator drives the first cycle(s). **FLOOR-kept:** card+SEPA + saved-card charge + the sanctions/Hold re-read at charge (DEC-181) + Hold-no-auto-lift. (N2: chargeback **automated** [D21 KEPT]; storage-payment **manual-first**; K's Hold registry trigger-agnostic.) | Build the 3-stage auto-escalation on the retained event chain + thresholds. Additive. | **The first storage-billing cycle** (months out — 12-month-free + semi-annual cadence; timing-safe). |
| **D14** | **Returns / Replacement FSM automation** (Module C, §10.2) | The auto-transitions / routing / notification automation → **manual-first FSM** | The **DEC-184 Returns/Replacement FSM** + the 4-event chain (`PostShipmentIssueReported` → `ReturnInvestigationStarted` → `ReturnApproved`/`Rejected`/`Withdrawn` → `ReturnReceiptRecorded` + `ReplacementShipmentIssued`) + the discipline (original-voucher-preserved; **no-cash-refund**) are KEPT. | Automate the FSM transitions + routing + customer notification on the kept FSM + events. Additive. | Post-launch returns/replacement-volume telemetry. |
| **D16** | **Stage-8 inventory-workflow automation** (Module B + Module D — N1, identical depth both sides) | The automated reciprocal round-trips + tolerance-driven auto-reconciliation + the automated quarantine/discrepancy cross-module cascades → **manual-first** | The **integrity core is FLOOR — KEPT**: the two-layer no-oversell guard (Layer 2), committed-inventory protection + `InventoryShortfallDetected`, the quarantine-before-trust gate, cost-basis correctness, the four-way reconciliation *discipline*. **+ the DEC-194 receiving split** (D=documents / B=physical-match), the **DISCREPANCY state + the 6-path resolution enum**, the event consumers, InboundBatch / StockPosition, and the Stocktake / QuarantineRecord entities. (The cut is the *automated round-trips*, not the entities — Phase C item H.) | Automate the reciprocal round-trips + the tolerance-driven auto-reconciliation on the kept integrity core. **Restores B + D in lockstep** (N1). Additive. | Post-launch inventory-ops volume / operator load. |
| **D3** | **Geography automation engines** (Module C, §16) | The **automated US-state rule-matrix** (DEC-148), **DDP/DAP country-by-country** expansion (DEC-149), and **excise rate-matrix automation + update workflow** (DEC-150) → **operator-managed lists / white-glove manual quote** | The **two-tier destination eligibility** + the **white-glove manual quote (Tier-2)** are KEPT; the Tier-1 pre-cleared list is **operator-expandable**; the manual flow records the same data the future engine consumes. **OFAC at all destinations is FLOOR — KEPT** (never deferred). **Excise computation runs even in the white-glove flow** (the tax floor cannot be cut by manual routing). | Build the auto-generated rule-matrices on the kept two-tier structure + the recorded manual-quote data. Additive. | US / non-EU sales-volume growth + rate-matrix maintenance burden (volume-driven; DEC-148/149/150). |
| **D13** | **Late-binding bottle-side optimisation** (Module C, §3.2) | The **Logilize warehouse-efficiency optimisation** (Surface 2) → **FIFO + manual tiebreak** | The **two-surface selection structure is the seam**: Surface 1 (voucher-side **FIFO by Voucher expiry + manual tiebreak**) is KEPT; the **no-oversell-at-pick StockPosition read** is KEPT FLOOR. Surface 2 (the bottle-side warehouse-efficiency optimisation) is the deferred arm. | Add the bottle-side optimisation engine as Surface 2 on the kept two-surface structure. Additive. *(Cross-ref the already-deferred producer-override on late-binding, DEC-137 — explainer-pending; §5.2.)* | Post-launch warehouse-efficiency need + the DEC-137 producer-override explainer session (Paolo-track). |
| **D17** | **Cellar-render richness** (Module C, §14) | **Carrier-ETA-precision integration** + the richest cellar aggregation + sub-warehouse granularity → **basic view** | The **six-module read contract is the seam** (S Voucher+storage state; C physical/in-transit state; B storage-location summary + Bottle-Page link; 0 PR identity). At launch: **warehouse-level storage summary + admin-estimate ETA** ("in transit; ETA X") + the standard physical-state annotations. The richer aggregation is additive. | Add carrier-ETA-precision + granular storage on the kept six-module read contract. Additive. | Post-launch cellar-experience enhancement (product-roadmap). |
| **D6** | **Refund-cost-matrix automation + producer-fault clawback netting** (Module S, §12.5) | The automated refund-cost-matrix routing + the **producer-fault clawback netting** (the netting **defers WITH settlement, D19**) → **manual-first refund decisioning** | The **cause taxonomy** + the single **REFUND_COMPENSATION coupon** + the refund-event payloads are retained; the operator records the refund + cause + offers store-credit-105% by judgment at launch. **The legal floor is KEPT whole** (14-day pre-shipment cancellation; FX-correct refund at the original captured rate, D18). | Build the automated cost-matrix routing on the kept cause taxonomy + coupon; the clawback netting lands **with the settlement engine** (§1.4). | Post-launch refund-volume + **the settlement engine** (for the clawback netting). |

> **OC 5% computation note.** The Originating-Club 5%×`P_d` Discovery revenue-share **computation** defers **with the settlement engine (D19, §1.4)** — the **accrual capture is whole at launch** (K locks `OriginatingClubLocked` → S emits `DiscoveryRevenueShareAccrued` at INV1 → E records it; Phase C item E confirmed the one-shot capture whole + reconstructable). E computes the 5% + composes Section D when the engine is built, **reading the recorded accrual + K's lock + A's lineage — not re-deriving** (the Section-D info-disclosure constraint DEC-180 is preserved on the recorded payload).

---

## §3 The newly-deferred MVP set — Direct Purchase (item I — confirmed no launch deal)

> **Direct Purchase is deferred at launch (confirmed: no launch-pipeline deal needs it — Phase C Q4).** The launch model is passive consignment **V1 + V2 only**. The five modules that touch the model **idle the `direct_purchase` path in lockstep** with the enum / FSM / discriminators retained — **re-enable is purely additive** (a coordinated set when a deal needs it). *(Note: Direct Purchase is a deferred **commercial model**, not an automation — it restores as a coordinated set like §1, but it is gated on a business deal, not a build-cadence.)*

| Module | Idle at launch | Seam (P1) — retained |
|---|---|---|
| **Module A** | No `direct_purchase` allocations created (§3.2 / §14.1a) | The `direct_purchase` **enum value** + the **uniform operator-publish FSM** (DEC-183) — the §11.3.1 activation chain documented-but-not-exercised. **Zero Module A rework to re-enable.** |
| **Module D** | The operator-initiated PI-creation path + its at-PO-creation timing branch idle (§18.1a) | The **uniform flow** (DEC-093) stays parameterized; `trigger_source = operator_initiated` + the `ownership = NEWCO`-at-issuance derivation + the at-PO-creation timing rule retained-but-unexercised. **Re-enable = wire the operator PI surface back on (additive).** *(Module D carries the substantive Direct-Purchase arm.)* |
| **Module B** | The `direct_purchase → CRURATED`-at-issuance ownership derivation is not-exercised (V1/V2 reach CRURATED via `SupplierPaymentCompleted`, R4) | The derivation is a **scope annotation, not a cut** — InboundBatch would be `CRURATED` from creation for `direct_purchase` (no PRODUCER→CRURATED transition). |
| **Module E** | The Direct-Purchase immediate-Xero routing + Section-E informational rows idle (§4.3) | The `sourcing_model` discriminator + the immediate-Xero/Section-E routing retained as seam. |
| **Module S** | The storage-clock Direct-Purchase-in-transit arm idles (the read is the same `InboundEventPhysicallyAccepted` for V1/V2) | The in-transit-at-INV1 window is simply less-exercised; same event seam. |

- **Re-introduction hook**: when a launch-pipeline deal needs Direct Purchase, re-enable the five idles together — wire Module D's operator PI surface back on; the A/B/E/S seams already hold the shape. **Additive — the chain idles consistently** (no `direct_purchase` allocations → no operator PI → no at-issuance ownership flip → no immediate-Xero/Section-E → the in-transit window less-exercised).
- **Dependencies**: none at launch — **no KEPT item depends on `direct_purchase` being exercised** (Phase C item I). The V1 per-order producer→Vinlock shipping window survives the deferral (item K).
- **Re-evaluation trigger**: **a known launch-pipeline Direct-Purchase deal** (a business-event trigger, not a date/volume one — the modules are consistent either way).

---

## §4 The full Admin-Panel surface as a buildout target (the three additive axes)

> **The thin MVP Admin-Panel slice ships now; the full Admin-Panel surface is a roadmap buildout target.** The Admin Panel had **no v1.1 predecessor** (v1.1 treated it as an implicit DEC-083 admin-parity *mirror* and never specced it), so — symmetric in *intent* with every other module (an MVP scope + a roadmap of deferred scope) — **the full surface accretes *here*, in the roadmap**, as the deferred automation + the producer-write-UIs restore. The full operator surface is largely **derivable** (= the 8 full PRDs' operations under DEC-083 parity + the deferred-automation roadmap + the deferred producer-portal write-UIs — a composition, not a new source of truth). The thin MVP slice (Admin-Panel PRD §0–§5) + the seam (§6) ship at launch; **the full surface is the buildout target below.**

**The full target surface accretes along three additive axes** (Admin-Panel PRD §6):

### §4.1 Axis 1 — the restored automation consoles (each replaces its manual-first MVP console additively)

As each manual-first defer's automation lands (§1 / §2), the corresponding console gains its automated arm — **purely additive** (the manual-first surface is the seam):

| Console | Replaces (the MVP manual-first surface) | Restores with |
|---|---|---|
| **Settlement engine** (E) | The operator-run 5-section statement composition + Xero AP run | **D19** (§1.4) — the quarterly runs + statement FSM + OC-5% aggregation + clawback netting + Xero AP |
| **Dunning orchestration** (E) | The manual INV3 retry → K-Hold → Suspension chain | **D4** (§2) — the 3-stage auto-escalation |
| **Stage-8 inventory automation** (B) | The manual stocktake / quarantine / discrepancy / adjustment round-trips | **D16** (§2) — the tolerance-driven auto-reconciliation + automated cross-module cascades |
| **Returns / Replacement FSM automation** (C) | The manual-first DEC-184 FSM | **D14** (§2) — the auto-transitions / routing / notification |
| **D3 geography automation engines** (C) | The white-glove manual quote + operator-managed lists | **D3** (§2) — the automated US-state / excise / DDP-DAP engines |

### §4.2 Axis 2 — the producer-portal write-UIs (build back on the same DEC-083/115 backend)

At launch every producer/back-office write is operator-driven via the Admin Panel (P2; the **one** retained producer write platform-wide is Module K membership approve/decline). The producer-facing write **UIs** are the deferred layer — **no backend capability is cut** (DEC-083/115 admin-parity is a *backend* contract; the operator path is already functionally complete). They restore as producer self-serve on the same backend:

- **Allocation ops** (Module A) · **procurement ops** (Module D) · **Club-Offer authoring** (Module S — DEC-115) · the **richer waitlist-review UX** (Module K) · the **producer recall UI** (Module A/D).
- *(The consumer storefront / cellar / Bottle Page are already self-serve — **exempt**, not a roadmap item; D23 Producer Portal read + full 7-section reporting is **KEPT** at launch.)*

### §4.3 Axis 3 — the cross-cutting platform layer (UX/IA/design-system + RBAC/authority-tier)

- **The UX / IA / design-system layer** — screen layouts, navigation, component library, design tokens, page templates (all **DEC-073 tech-implementation**, deferred).
- **The RBAC / authority-tier / persona-gating model** — **admin-configurable + downstream** (`feedback_prd_rr_approval`; MVP-DEC-007). The **only** PRD-level operator discipline retained at launch is the spec-mandated **multi-actor separation-of-duties** (3-step Creator→Reviewer→Approver; supervisor-override; single-supervisor-approval — self-approval never allowed).
- **Design-side north-star (read-only reference — NOT scope to import):** `greenfield/12-admin-panel/` (the Stage-8 operator-task inventory — **57 tasks / ~20 composed surfaces** — the IA model, the canonical journeys, the component library + design tokens, the `archived/operator_role_model_v0.1.md` RBAC reference). It is mapped *down* to the MVP slice in the Admin-Panel PRD; the full target surface accretes *up* to it here.

- **Seam (P1)**: every manual-first console (axis 1) records the same data its future automated engine consumes; every operator-driven write (axis 2) sits on the same backend its future producer UI will use; the platform layer (axis 3) sits over the same operator surfaces. **The seam is real on both sides** — the full surface is a forward target, not a backward predecessor.
- **Dependencies**: axis 1 tracks the §1/§2 automation restores (the consoles light up as their engines land); axis 2 is independent (producer self-serve demand); axis 3 is a frontend/platform workstream (UX phase + RBAC maturity).
- **Re-evaluation trigger**: per-axis — axis 1 with each automation restore (§7); axis 2 on producer self-serve demand; axis 3 at the Admin-Panel frontend/design phase.

---

## §5 The v1.1 already-deferred set (carried VERBATIM — do NOT re-cut)

> **These were explicitly deferred at v1.1 with documented re-introduction seams; they carry to this roadmap unchanged.** The authoritative, self-contained register for each is the frozen [`greenfield/03-qa/qa.deferred.md`](../../reference/v1.1/03-qa/qa.deferred.md) (its §1 Q-OQ atoms, §2 Phase-2+ DEC calls, §3 vendor/external rows, §4 BMD §13 items) + each v0.3-MVP PRD's "already-deferred / carry verbatim" section. **Do not re-derive a seam qa.deferred.md already states; do not re-cut.** Grouped below by theme for roadmap legibility (the grouping is organisational; the dispositions are verbatim).

### §5.1 Commercial-model expansions (the launch is producer-club aggregator, passive-consignment V1+V2, B2C-only)

| Item | Disposition at launch (verbatim seam) | Re-evaluation trigger |
|---|---|---|
| **Active consignment** + the **SELL_THROUGH_SETTLEMENT** financial event + **ConsignmentPlacement** entity (`ConsignmentPlacementRecorded`/`ConsignmentSellThroughRecorded`) | B2C-only at launch (DEC-011/193/068 — BMD §13.1). The tri-module **B + C + E** carve-out (Module E §5.10; Module B §21); shapes preserved; restores together. | NewCo business-model shift to include B2B |
| **B2B / Wholesale** — incl. B2B credit terms, **INV-P** proforma, **INV1_INV2_COMBINED**, the v17 Customer/Account B2B shape, auto-SO on combined invoicing | Consumer-only at launch (DEC-017/068 — BMD §13.2). Re-loads as a discrete future-DEC. | Customer-base shape change |
| **Drop-shipping** (producer-direct shipment skipping the warehouse) | Out at launch (BMD §13.3 / OQ-17 — every voucher→shipment goes through NewCo physical custody at Vinlock). A future-DEC introduces a producer-direct-shipment SO subtype + a new InboundEvent variant. | Future commercial demand (not on roadmap per BMD §13.3) |
| **Liquid sales / pre-bottling** — **Liquid Product** entity + **Liquid SKU** + **BottlingResolution** (N:M reissuance) + `BOUGHT_BACK` | Out at launch (BMD §13.4 / DEC-065 — PIM bottled-only). The v17 Liquid Product block is well-specified + re-introducible as a coherent set (Module 0 §17.2). | Phase-2+ product-roadmap review |
| **CruTrade-style P2P / secondary-market trading** (`ON_CRUTRADE` + C2C/P2P resale + the wallet-linkage field) | Out at launch (BMD §13.5 / DEC-008 envelope). Member-to-member **gifting** (§1.1) is the only deferred C2C path with a launch-near hook. | Phase-2+ product-roadmap; CruTrade integration appetite |
| **Third-party-owner `THIRD_PARTY` ownership value + AgencyAgreement entity** | Out at launch (2-value `ownership_flag` PRODUCER/CRURATED; no agency intake, no third-party custody — Module B §21; Module K §17.2 #9). | Active-consignment / agency reintroduction |
| **SupplierAgreement entity** (DEC-084) | No SupplierAgreement at launch; Module D reads informal Supplier metadata; supplier terms live on Allocation `commercial_terms`. Pattern precedent: Module K §4.6 ProducerAgreement. | Supplier-relationship diversification |
| **Multi-tier Club activation** (DEC-062) + **Supplier-operated Clubs** (DEC-067) | Single-tier launch (multi-tier is configuration only); `Club.partner_producer_id` admits Producers only. | A Supplier wants to operate a Club; multi-tier demand |

### §5.2 Fulfilment / geography / warehouse already-deferred

| Item | Disposition at launch (verbatim seam) | Re-evaluation trigger |
|---|---|---|
| **Multi-warehouse / multi-site custody** (OQ-16) | Single Vinlock-operated warehouse in France at launch (BMD §13.8); the SO has no warehouse-routing attribute (the v17 Split-Shipment Constraint trivially satisfied). | Capacity / regional expansion need |
| **Full reverse-inbound mechanics** (OQ-12/18, DEC-152) — reverse 3-gate QC, reverse cost-basis unwind precision (partial-recall accuracy, multi-event netting), partial-recall UX, recall-dispute path, automated return-shipment carrier coordination, reverse-discrepancy paths | At launch: **event-recording + manual operator capability only** (Module C §12.4; Module D §18.2; Module A §14.2). **The D15 producer-recall capability ships KEPT-minimal/manual** (event-record + manual reverse-logistics); the *full automation* is this deferred set. | Post-launch operational data on recall frequency |
| **Producer-override on late-binding selection** (DEC-137, Interpretation C) | Two-surface selection at launch; producer-override (per-allocation FEFO / specific-vintage rule) deferred; **Paolo-requested explainer queued as a separate session** before any Phase-2+ commitment. | Paolo's explainer session + post-launch producer feedback |
| **US-state alcohol rule-matrix** (DEC-148) · **DDP/DAP country-by-country** (DEC-149) · **excise rate-matrix + automated update** (DEC-150) | The **D3 manual-first floor** (§2) — operator-managed lists / white-glove at launch; the automated engines are this already-deferred set. | US / non-EU sales-volume growth; rate-matrix maintenance burden |
| **Voucher-substitution full automation** (DEC-104) | Manual operator capability at launch (`VoucherSubstitutionExecuted` operator-recorded); catalogue-driven matching + automated notification deferred. | Post-launch substitution-volume data |
| **Sophisticated waitlist mechanics + capacity-decrease tightening** (DEC-069/079) | Producer-discretionary at launch (no FIFO auto-conversion); FIFO auto-conversion / priority-by-application-date / producer-ranking deferred. | Post-launch waitlist data + producer feedback |
| **Auto-cascade on ProducerAgreement transitions** (DEC-077) | No auto-cascade onto Allocations at launch (operator decides per-allocation); default-cascade rules deferred. | Post-launch agreement-transition data |

### §5.3 Finance / invoicing already-deferred

| Item | Disposition at launch (verbatim seam) | Re-evaluation trigger |
|---|---|---|
| **Partial PO settlement** (OQ-20) | `SupplierPaymentCompleted` is **atomic per PO** at launch; partial settlements operationally exceptional (manual Xero adjustment). Phase-2+ extends the event with `partial_amount` + `outstanding_balance`. **Lands with the settlement engine (§1.4).** | Post-launch multi-tranche supplier-payment patterns |
| **Paid services / experiences + INV4 + EVENT_CONSUMPTION_SETTLEMENT** (OQ-8/13/19) | Storage is the sole launch "service" (DEC-118); no INV4 typology, no event-consumption settlement (DEC-157/171). Free experiences book operationally without catalog representation. | Paid experiences operationalize (Phase-2+; future-DEC) |
| **SDI connector — Italian e-invoicing** (EXT-2 / DEC-171 / D20) | Deferred pending Italian incorporation confirmation (DEC-015); the principle (Module E → Xero only; SDI XML downstream) holds as a jurisdiction-agnostic working assumption; the literal connector selection is a Phase-2+ DEC (UK MTD / French Factur-X / German GoBD substitute by jurisdiction). | Italian incorporation jurisdiction confirmation; legal-counsel finalisation |
| **Producer-direct multi-currency price quoting** (DEC-038) | FX-derived from EUR base at launch (daily-snapshot mid-rate-plus-buffer); producers quoting per-currency directly is post-launch. | Producer-relationship maturity + multi-currency demand |
| **AR-aging dunning** | The launch INV3 dunning chain (D4, §2) is the only payment-failure dunning mechanism at launch; broader AR-aging dunning is Phase-2+ (Module E §11.2). | Post-launch AR-aging need |

### §5.4 Blockchain / on-chain already-deferred (the **D12 on-chain set's gating prerequisites** — §1.3)

| Item | Disposition at launch (verbatim seam) | Re-evaluation trigger |
|---|---|---|
| **Blockchain-expert validation of the Wave-4 NFT/blockchain DECs** (EXT-1) | The Wave-4 NFT/blockchain DECs are locked as **working hypothesis** pending Paolo's blockchain-expert colleague's review (DEC-120 recovery-chain shape; DEC-121 1:1 mint timing; DEC-122 mint-payload non-PII; DEC-124 NFC tag content; DEC-131 stale-attestation). **⚠️ TIME-SENSITIVE** (§1.3 / §7.1). | Paolo's blockchain-expert engagement initiation; pre-launch tag-stock procurement lead-time |
| **Smart-contract audit + governance + key-rotation cadence** (EXT-3 / OQ-15) | Tech-team / operations / external-audit scope (DEC-073); the Avalanche-wallet operational architecture (cold-storage, multi-signature, key-rotation) + pre-launch external audit. | Pre-launch external audit + blockchain-expert review |
| **The NFT working-hypothesis cluster** (DEC-120/121/122/124/131) + the §17.3 stale-attestation operational mechanism + richer Bottle-Page media (video/AR) + post-shipment NFC re-tagging | **Carry verbatim; do not re-cut** (Module B §21). The on-chain encoding / NFT-payload literal encoding / NFC tag-write protocol / wallet architecture are downstream of EXT-1. | Blockchain-expert validating-questions session |

### §5.5 Platform / product / policy already-deferred

| Item | Disposition at launch (verbatim seam) | Re-evaluation trigger |
|---|---|---|
| **AI / Operator Copilot** (DEC-021) | All AI capabilities deferred at launch (operator copilot, customer assistant, anomaly/fraud beyond Airwallex baseline, content recommendation). | Post-launch product-roadmap review |
| **Native mobile apps** (DEC-018) | Web + mobile-web only at launch. | Post-launch mobile-engagement telemetry |
| **Dedicated customer-support tooling** (OQ-5, BMD §13.10) | Admin Panel + Consumer-Portal contact-us form + email at launch. | Post-launch support-volume telemetry |
| **Community features** (OQ-6) · **producer-side communication features** (OQ-7) | Deferred under DEC-058; post-launch product-roadmap candidates. | Post-launch product-roadmap review |
| **Death / inheritance / corporate-dissolution policy** (OQ-11, BMD §13.9) | No terminal-by-death Customer state at launch; case-by-case ops via the existing Suspended → Cancelled/Closed flow with admin-recorded reason. | Customer-base aging + base-size growth → policy required |
| **Country-change automated re-KYC trigger** (DEC-030) | NOT enabled at launch (signal-to-noise); AML-threshold detection (€10k single / €50k cumulative) + Compliance ad-hoc trigger are the launch paths. | Post-launch country-change frequency + KYC false-positive rate |
| **Crurated intragroup mechanics** (OQ-14, BMD §13.11) — Avalanche-wallet tenant carve-out | NewCo + Crurated operate independently (DEC-001/020); tenancy-separation principle locked; operational implementation downstream. | NewCo-Crurated ownership clarification |
| **Producer-reporting Phase-2+** (REP-1 per-Producer custom dashboards; REP-2 cross-Producer comparison/benchmark; REP-3 export / API access) | Launch renders the seven sections uniformly (D23 KEPT — full self-serve); customisation / benchmark / export are Phase-2+ (REP-3 is downstream tech, DEC-073). | Producer-Portal frontend-design start; producer integration demand |
| **Re-acceptance on T&C/Privacy version updates · auto-suspension on zero active Profiles · enhanced-KYC document workflow · operator override for Originating Club · PI batching optimisation for V1 · per-supplier InboundEvent SLA override management UI** | Operationally / minimally handled at launch (Module K §17.2; Module D §18.2); richer models are future-DECs / downstream tech. | Post-launch operational data |
| **Transactional-email-provider operational choice** (EXT-4) | HubSpot named for delivery (BMD §11.5); the literal provider + template-engine + sender-domain + bounce-handling is downstream tech (DEC-073). | Downstream tech selection |

*(Permanent / N-A — not roadmap candidates, recorded for completeness: **OQ-2** Bottle-Page customer-identity exposure = **locked anonymous, never** [DEC-024 — the public Bottle-Page anonymisation floor]; **BMD §13.12** data migration = **N/A** [greenfield, no legacy].)*

---

## §6 The coordinated-restoration coherence (Phase C item N — the no-orphaned-DEFER spine)

> **This is the roadmap's structural spine — what makes the deferred set a *coherent* forward plan, not a flat list.** Phase C item N verified, across the composed system, that **the union of all deferrals composes**: **no KEPT item silently depends on a deferred one** (no orphaned DEFER), and the multi-module deferrals **defer + restore as coordinated sets**. This section restates that verification at the roadmap level + maps the cross-set dependencies.

### §6.1 The no-orphaned-DEFER verification (per coordinated set)

| Coordinated set | Modules | No-orphaned-DEFER check (no KEPT item depends on the deferred thing) |
|---|---|---|
| **Gifting (D5)** | S + K + C | ✓ No KEPT item depends on gifting. S defers the GIFTED state/flow/events on the mutable-customer-reference seam; K's gifting read-API idles (generic, unchanged); C's `is_gift` sub-flag idles. Restores together. |
| **Discovery composites (D7)** | S + A + 0 | ✓ No KEPT item depends on the composite construct — **each constituent is a normal per-bottle voucher; B/C/D/E see N normal vouchers, never a "composite."** S keeps the single-FK-capable Offer; A keeps the per-constituent primitive + `C_i`; 0 keeps the Composite SKU. Restores together (additive). |
| **NFT on-chain (D12)** | B + downstream | ✓ No KEPT item depends on the on-chain layer — **the NS path is the universal fallback** (every downstream degrades gracefully; the floor's Layer-2 = the NS sub-pool ATP at launch, independent of the decoupled serialized/NFT cluster — Phase C item G/J). `nft_reference` back-fillable. Restores together. |
| **Settlement engine (D19)** | E + D + S + A | ✓ No KEPT item depends on the engine — **the recording is the seam** (E records; D records; S emits the accrual; A keeps lineage). The OC-5% accrual capture is whole + reconstructable (item E). The first close is months out. Restores together. |
| **Direct Purchase (item I)** | A + D + B + E + S | ✓ No KEPT item depends on `direct_purchase` being exercised — the chain idles consistently end-to-end; the enum/FSM/discriminators are retained. Re-enable additive. |
| **Active consignment (DEC-193)** *(already-deferred)* | B + C + E | ✓ Carried verbatim from v1.1 (§5.1); the shapes are preserved; restores together. No KEPT item depends on it (B2C-only at launch). |

**The single-module automations (§2) each keep their event/entity/FSM seam; none has a KEPT item depending on the deferred automation** (the integrity core / FSM / events are KEPT; only the automation defers — verified per item in §2).

### §6.2 The cross-set dependency map (which deferred sets / outcomes gate which)

The coordinated sets are **mostly independent** — that is the point of the seams. The genuine cross-set dependencies (a deferred item that waits on *another* deferred item or a launch outcome, not just a calendar trigger):

| Deferred item | Waits on | Nature of the dependency |
|---|---|---|
| **Composite OC-on-`P_d` computation** (D7 sub-item) | **Settlement engine (D19)** | The composite-OC *computation* runs in E's engine; the composite *construct* itself is independent. |
| **Producer-fault clawback netting** (D6 sub-item) | **Settlement engine (D19)** | The netting nets in E's engine; the cause-tagged refunds are recorded at launch. |
| **OC 5% computation + Section-D composition** | **Settlement engine (D19)** | The accrual is captured at launch; the computation is the engine's. |
| **Partial PO settlement** (OQ-20) | **Settlement engine (D19)** | The multi-tranche `SupplierPaymentCompleted` extension lands with the engine (atomic-per-PO at launch). |
| **NFT on-chain set (D12)** | **EXT-1 + EXT-3 + the NFT working-hypothesis cluster** (§5.4) | The on-chain workstream is gated on blockchain-expert validation + smart-contract audit (the already-deferred prerequisites). |
| **D3 geography automation engines** | **The already-deferred US-state / DDP-DAP / excise rule-matrices** (§5.2) | The D3 manual-first floor + the automated engines are the same workstream (DEC-148/149/150). |
| **D13 bottle-side optimisation** | **The DEC-137 producer-override explainer** (Paolo-track, §5.2) | The two-surface optimisation + producer-override are adjacent late-binding work. |
| **Restored automation consoles** (Admin-Panel axis 1, §4.1) | **Each underlying automation restore** (D19/D4/D16/D14/D3) | The console's automated arm lights up as its engine lands. |
| **Producer-portal write-UIs** (Admin-Panel axis 2, §4.2) | *(independent)* — the DEC-083/115 backend is whole at launch | Restores on producer self-serve demand, no upstream gate. |

**Reading the map:** the **settlement engine (D19) is the single most-depended-on restore** — four sub-items (composite-OC computation, clawback netting, OC-5% computation, partial-PO settlement) land *with* it. The **NFT on-chain set (D12) is gated on the external blockchain workstream** (EXT-1/EXT-3) — its time-sensitivity is a *scheduling/procurement* near-term flag (§7.1), not a build-cadence one. Everything else is **independent** — deferred on its own calendar/volume/business trigger (§7).

---

## §7 The re-evaluation-trigger / next-decision-moment map (extending qa.deferred.md §5)

> **The inverse view:** given a trigger (date / volume / external-decision / business-event), **which deferred items unblock?** This extends `qa.deferred.md` §5 with the **MVP-specific triggers** the strip-down created. *(The trigger answers "when to revisit," not "in what build order" — the build order is the release index #13.)*

### §7.1 ⚠️ Time-sensitive Paolo-track near-term items (flagged even though the design work is dev-phase — Phase C Q3 / kickoff (e))

These two are **near-term scheduling / procurement actions**, not post-launch design — they must not slip:

1. **Schedule the EXT-1 blockchain-expert review now** — the D12 on-chain set (§1.3 / §5.4) is gated on it; if it slips, the on-chain workstream becomes the launch critical path. *(The design work is dev-phase; the **scheduling** is near-term.)*
2. **Confirm the DEC-124 NFC tag-stock procurement lead-time + tag-content back-fill design** — NFC tags apply at launch (serial + Bottle-Page URL) with the on-chain reference back-fillable, but the **pre-launch tag-stock procurement lead-time** is long. *(The design is dev-phase; the **procurement lead-time** is near-term.)*

**Both are near-term items to schedule early** — carried here as flagged near-term items.

### §7.2 Date-driven / cadence triggers (the timing-safe MVP defers)

| Trigger | Unblocks |
|---|---|
| **The first storage-billing cycle** (months out — 12-month-free + semi-annual INV3) | **D4** INV3 dunning orchestration (§2) |
| **The first producer-settlement close** (months out — the settlement cadence) | **D19** settlement engine + OC-5% computation + clawback netting + partial-PO settlement (§1.4 / §6.2) |
| **Post-launch product-roadmap review** (general sweep) | Gifting (D5, §1.1); cellar richness (D17); community/producer-comms features; AI Copilot; CruTrade P2P candidacy; cross-Producer comparison (REP-2) |

### §7.3 Volume-driven / operational-data triggers

| Trigger | Unblocks |
|---|---|
| Inventory-ops volume / operator load | **D16** Stage-8 automation (§2) |
| Returns/replacement volume | **D14** Returns/Replacement FSM automation (§2) |
| US / non-EU sales-volume growth; rate-matrix maintenance burden | **D3** geography engines (§2) + the US-state/DDP-DAP/excise matrices (DEC-148/149/150, §5.2) |
| Warehouse-efficiency need | **D13** late-binding bottle-side optimisation (§2) |
| Post-launch recall frequency | Full reverse-inbound mechanics (OQ-12/18, DEC-152, §5.2) |
| Substitution / waitlist / agreement-transition volume | DEC-104 / DEC-069·079 / DEC-077 (§5.2) |
| Operator-load telemetry (Admin Panel) | Axis-1 automation consoles (§4.1, as their engines land) |

### §7.4 Business-event triggers

| Trigger | Unblocks |
|---|---|
| **A launch-pipeline Direct-Purchase deal** | Direct Purchase (item I, §3 — the A/D/B/E/S coordinated re-enable) |
| **Multi-producer Discovery-bundle demand** | Discovery composites (D7, §1.2) |
| **Producer self-serve demand** | The producer-portal write-UIs (Admin-Panel axis 2, §4.2) |
| NewCo business-model shift to B2B | Active consignment + B2B/wholesale + B2B credit (§5.1) |
| Paid experiences operationalize | Services/experiences + INV4 + EVENT_CONSUMPTION_SETTLEMENT (§5.3) |
| A Supplier wants to operate a Club; supplier-relationship diversification | Supplier-operated Clubs (DEC-067); SupplierAgreement (DEC-084) (§5.1) |
| Liquid-sales / CruTrade / drop-ship appetite | Liquid sales (BMD §13.4); CruTrade P2P (BMD §13.5); drop-ship (OQ-17) (§5.1) |

### §7.5 External-decision triggers

| Trigger | Unblocks |
|---|---|
| **Blockchain-expert validating-questions session executed** (EXT-1) | The D12 on-chain set (§1.3); the NFT working-hypothesis cluster + smart-contract audit/governance (EXT-3) (§5.4) |
| **Italian incorporation jurisdiction confirmed** | SDI connector (EXT-2); the literal e-invoicing connector spec (§5.3) |
| **Pre-launch external audit on smart-contract code** | Smart-contract audit + governance + key-rotation (EXT-3, §5.4) |
| **Paolo's DEC-137 producer-override explainer session** | Producer-override on late-binding (DEC-137, §5.2) — explainer before any Phase-2+ commitment |
| **NewCo-Crurated ownership clarification** | Intragroup mechanics (BMD §13.11); the Avalanche-wallet tenant carve-out (OQ-14) (§5.5) |
| **Legal-counsel finalisation on inheritance/dissolution** | Death/inheritance/corporate-dissolution policy (OQ-11, §5.5) |
| Paolo to share producer-agreement template / customer persona | ProducerAgreement field finalisation (OQ-9); Customer-attribute prioritisation (OQ-10) (§5.5) |

---

## §8 Boundaries restated + flags for Paolo

### §8.1 What this roadmap did NOT do (the discipline — master §8)

- **No new scope cuts.** The deferred set is the union of the ratified defers (Phase B + C + the 9 PRDs) + the v1.1 carry. No deferred item was invented; no seam was re-derived (collected from the owning PRD).
- **No re-opened defer or KEEP.** The roadmap records + organises; it does not re-decide. **D21 chargeback automation is KEPT (not a roadmap item — [[keep-payment-automation]]); D18 dual-record FX is FLOOR; the integrity cores are FLOOR** — only the automations / peripherals defer.
- **No build-plan / sequencing.** Inter-item dependencies are stated (§6); the **launch build order + the build sizing/estimation step (#13 / dev-team) are the release index / build-workplan (#13)**. The build-sequencing flag (Module B's floor artefacts integration-ready by the integrated launch — Phase C item G/Q5) is a #13 / dev-team-sizing item.
- **No tech-implementation (DEC-073); no accounting position (DEC-072).** Capabilities + seams named, not mechanisms; Module E records, Xero decides GL.

### §8.2 Flags for Paolo (genuine items surfaced — for the digest; none re-cuts or invents scope)

1. **The two time-sensitive Paolo-track near-term items (§7.1)** — the EXT-1 blockchain-expert review scheduling + the DEC-124 NFC tag-stock procurement lead-time. These are the only **near-term** items in an otherwise post-launch register; they feed the dev-team sizing exercise. *(Recommendation: own both on the Paolo track now; the design is dev-phase but the scheduling/procurement is not.)*
2. **The settlement engine (D19) is the single most-depended-on restore (§6.2)** — four sub-items (composite-OC computation, clawback netting, OC-5% computation, partial-PO settlement) land with it. *(Recommendation: when the dev-team sizing exercise sizes the first-settlement-close timing, note that these four ride the same workstream — useful for the post-launch sequencing the release index will carry.)*
3. **No cross-PRD seam drift found.** Every newly-deferred item's seam in this roadmap matches the seam in its owning PRD (cross-checked §1–§3 against Module 0 §17 / K §17 / A §14 / D §18 / S §20 / B §21 / C §16 / E §11 / Admin-Panel §6); every v1.1 carry matches `qa.deferred.md`. *(The Architecture §8.3 cross-PRD flags are naming/consumer-list-enumeration items that do not bear on a deferred item — they stay Architecture / batch-ratification items, not roadmap items.)*

---

## §N Inheritance & source trace (audit-trail only; not load-bearing)

Per DEC-074, the body §0–§8 is self-contained NewCo prose. This trace is audit-only.

**This roadmap is a consolidation + organisation of settled defers** — it collects the seams already written in the PRDs, organises them by the coordinated-restoration sets (Phase C item N), and extends the v1.1 deferred register. The genuinely net-new content is **the organisation** (§1's coordinated-set framing + §6's cross-set dependency map + §7's extended trigger map + §4's full-Admin-Panel-surface buildout target) — the items + seams pre-exist.

**Sources consumed (DEC-074):**
- **The v1.1 deferred register this roadmap EXTENDS (frozen, never edited):** `greenfield/03-qa/qa.deferred.md` (§1–§5) + `greenfield/01-prd/Architecture_v0.2.md` §6 — the already-deferred set (§5) carried verbatim with its existing hooks + the trigger map (§7) extended.
- **The consolidated seam (the spine):** `mvp/02-prd/Architecture_v0.3-MVP.md` §6.1 (the four coordinated sets + the single-module automations + Direct Purchase + the full Admin-Panel surface), §6.2 (the v1.1 already-deferred set), §6.3 (the invoice typology) + §8.1 (the deferred events named).
- **The structural brief (the organising principle):** `mvp/01-triage/Phase_C_Reconciliation_v0.1.md` item N (§4-N — the coordinated restorations + the no-orphaned-DEFER check) + items D/E/F/H/I/J/K (the per-item defer verifications) + the ratification log (Q1 Admin-Panel; Q3 EXT-1/DEC-124 time-sensitive; Q4 Direct Purchase confirmed deferred).
- **The settled input (the per-item seams — collected, not re-derived):** the nine v0.3-MVP PRDs' deferred-set sections — Module 0 §17, Module K §17, Module A §14, Module D §18, Module S §20, Module B §21, Module C §16, Module E §11, Admin-Panel §6.
- **The decisions index:** `mvp/04-decisions/MVP_Decisions_Register_v0.1.md` (the D-dials with ⛔/🔁 flags; the four RECONCILEs; item N).
- **The authoritative scope brief:** master `mvp/00-method/Phase_D_Kickoff_Prompt.md` §5 row 12 + P1 (§3) + §6.E (the floor).

**Consistency anchors:**
- Every newly-deferred item's seam matches its owning PRD (§8.2 #3); every v1.1 carry (§5) matches `qa.deferred.md`'s disposition.
- The four coordinated sets (§1) + Direct Purchase (§3) + the active-consignment carry (§5.1) match Phase C item N's six coordinated restorations; the no-orphaned-DEFER check (§6.1) matches Phase C §4-N.
- The full Admin-Panel surface's three axes (§4) match Admin-Panel PRD §6.
- The deferred events named in Architecture §8.1 (the four `VoucherGift*`; the composite-bind events; the on-chain `NFT*` set; the settlement-statement FSM events; the active-consignment events) are carried with their coordinated sets (§1.1/§1.2/§1.3/§1.4 + §5.1).

**Out of scope for this roadmap** (the discipline — §8.1): no new scope cuts; no ratified defer re-opened; no build-plan / sequencing (that is #13); no tech-implementation (DEC-073); no accounting position (DEC-072); the frozen `qa.deferred.md` is extended (re-baselined into `mvp/04-roadmap/`), never edited.

---

*End of Post-Launch Roadmap v0.1 — Phase D re-baseline, **artefact #12.** The single post-launch buildout register: (a) the newly-deferred MVP items — the four coordinated multi-module restorations (gifting S+K+C; Discovery composites S+A+0; NFT on-chain B+downstream; settlement E+D+S+A), the single-module manual-first/simplify automations (D4/D14/D16/D3/D13/D17 + D6), and Direct Purchase (A/D/B/E/S) — each with seam + re-introduction hook + dependencies; (b) the full Admin-Panel surface as a buildout target (three additive axes); (c) the v1.1 already-deferred set carried verbatim; (d) the coordinated-restoration coherence (Phase C item N — the no-orphaned-DEFER spine + the cross-set dependency map); (e) the disposition per item + the extended re-evaluation-trigger map. **A consolidation + organisation of settled defers — collects the seams from the PRDs; takes no new cuts; re-opens no ratified defer; does not sequence the build (that is #13); takes no accounting position; specifies no tech.** **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E. With the roadmap drafted, the deferred set is whole — the re-baseline turns to its final artefact, the release index / build-workplan (#13), and then Phase E (the single coherent handoff + the dev-team sizing exercise).*
