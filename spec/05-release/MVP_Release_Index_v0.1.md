# NewCo ERP — Launch-MVP Release Index v0.1 (the launch manifest)

- **Version**: v0.1 — the launch-MVP release manifest. The single document that says *"here is the launch release — these artefacts, this status, this coherence."*
- **Date**: 2026-06-08
- **Status**: **DRAFT — awaiting batch ratification** (MVP-DEC-003: Phase-D ratification is batched; nothing promoted to `handoff/` until Phase E). This index + its coupled [Build Workplan v0.3-MVP](Build_Workplan_v0.3-MVP.md) are **Phase-D artefact #13 — the FINAL Phase-D artefact**; with them drafted, the Phase-D re-baseline is complete.
- **Owner**: Paolo (decides). Claude maintains.
- **Predecessor**: [`greenfield/00-release/NewCo_ERP_Release_v1.1_Index.md`](../../reference/v1.1/00-release/NewCo_ERP_Release_v1.1_Index.md) (RELEASED v1.1 2026-05-09; **frozen — never edited**, plan R4). This index is a **strip + re-baseline** of that v1.1 manifest to the launch-MVP scope.
- **Coupled artefact**: [`Build_Workplan_v0.3-MVP.md`](Build_Workplan_v0.3-MVP.md) — the revised build workplan, the **input to the dev-team sizing exercise**. This index manifests the spec; the workplan sequences its build. Written together (master `Phase_D_Kickoff_Prompt.md` §5 row 13 = one artefact, two coupled files).
- **Scope**: the launch-MVP specification baseline, held in `mvp/` until the single coherent Phase-E handoff. **NOT the build artefact, NOT the operational runbook, NOT the dev-team implementation spec, NOT the re-estimate** (§6).
- **Method**: a **manifest**, not new scope (DEC-073 no tech-implementation; DEC-072 no accounting position; DEC-074 self-contained — anchors restated inline; **take no new scope cuts; re-open no ratified defer**). The scope is settled (Phase B cut-sheets + Phase C reconciliation + the nine v0.3-MVP PRDs + the roadmap). This index *indexes*.

---

## §0 What this release index is (the calibration)

### §0.1 Purpose + altitude

This is the **launch release manifest** — the one place that lists **every artefact composing the launch-MVP specification**, with each artefact's status and pointer, and asserts the headline coherence: *the nine trimmed module scopes compose into one coherent launch system; the floor is whole; the deferred set is cleanly seamed → roadmap.* It is the orientation document a reader (the dev team, a coding agent, the Phase-E handoff) opens **first** to see the shape of the launch spec and where each piece lives.

It operates at the **manifest altitude** — it points to the authoritative artefacts (DEC-074); it does not restate the module specs (the PRDs), the deferred set (the roadmap #12), or the build sequence (the workplan #13, its coupled twin).

### §0.2 What this index lands (the five things — kickoff §C)

| # | Element | Where |
|---|---------|-------|
| **(a)** | **The manifest** — every launch-spec artefact (the 9 PRDs + 9 acceptance docs + Architecture + roadmap + decisions register + build-workplan + this index), each with path + status + a one-line scope descriptor | §2 |
| **(b)** | **The headline coherence assertion** — the nine trimmed scopes compose into one coherent launch system; the six floor chains whole end-to-end; the trimmed event contract internally consistent; the deferred set seamed → roadmap (the release's *"it hangs together"* statement) | §3 |
| **(c)** | **The launch-scope summary** — the one-paragraph *"what the launch IS"* | §1 |
| **(d)** | **The deferred-set pointer** — the launch is a clean SUBSET (P1); the deferred scope lives in the roadmap (#12); this index points there (does not restate it) | §4 |
| **(e)** | **The open-items carry** — the batch-ratification status, the Architecture §8.3 cross-PRD flags, the build-sequencing flag, the time-sensitive Paolo-track items — flagged, pointing to their owners | §5 |

### §0.3 What this index is NOT (the hard boundaries)

- **NOT new scope.** It manifests the settled scope (Phase B + C + the nine PRDs + the roadmap). It takes **no new cut** and **re-opens no ratified defer**.
- **NOT a re-spec.** It points to the PRDs (the module specs) and the roadmap (the deferred set); it does not restate them.
- **NOT the estimate.** The sizing and dating is the **separate sizing/estimation step (done with the dev team)**; its input is the coupled build-workplan (#13). This index neither sizes, staffs, nor dates anything.
- **NOT tech-stack / UX / accounting.** DEC-073 (no tech-implementation), DEC-072 (Module E records, Xero decides GL) hold.
- **NOT a handoff.** Per GOV-2 + MVP-DEC-003, nothing is promoted to `handoff/` until the single coherent Phase-E handoff. This index lives in `mvp/05-release/`.

### §0.4 Why this artefact — and why it is THIN

The v1.1 baseline had a release index (`NewCo_ERP_Release_v1.1_Index.md`) — the manifest of the frozen spec. The launch MVP needs the same: a manifest of the **re-baselined** spec, so the dev team and the Phase-E handoff open one document and see the whole launch slice. This index is a **strip + re-baseline** of that v1.1 manifest: it carries the v1.1 structure (the per-module spec roster + the cross-cutting deliverables + the decisions register + the out-of-launch register + the boundary statement) and re-points it at the `mvp/` re-baseline set. The genuinely net-new content is small — the manifest is a **table of pointers + statuses**; the coherence assertion is **carried from Architecture §8 / Phase C §1** (this index records it, it does not re-derive it). The business-model layer is **unchanged** (the MVP is a scope re-baseline, not a business-model change — the frozen BMD v0.9 remains the upstream anchor; §2.4).

---

## §1 The launch-scope summary — what the launch IS (c)

**The NewCo launch is the producer-club aggregator on passive consignment, B2C-only, single-warehouse, manual-first in operations.** Concretely, the launch system **IS**:

- **The producer-club aggregator core loop** — producers publish allocations (operator-driven at launch); members browse a storefront, buy bottle Vouchers, hold them in a Cellar, redeem for shipment, pay storage; NewCo procures against sell-through, receives into inventory authority, fulfils, and settles. The **club / membership spine is KEPT in full** (D8 — core VP); **producer self-serve reporting is KEPT in full** (D23, all 7 sections).
- **Passive consignment V1 + V2 only** — **Direct Purchase is deferred** (confirmed: no launch-pipeline deal; the `direct_purchase` enum/FSM/discriminators idle across A/D/B/E/S, re-enable additive — item I).
- **B2C-only, single warehouse** — Vinlock (one French warehouse) at launch; B2B / wholesale / active consignment / drop-ship / multi-warehouse all already-deferred (carried verbatim).
- **Six locales, five currencies** — Bottle Page + Consumer storefront in EN+IT+FR+DE+JP+ZH; Producer Portal + Admin Panel EN+IT (content may stagger — D2); EUR base + USD/GBP/CHF/JPY with **dual-record FX KEPT WHOLE** (D18 — FLOOR; FX-correct refunds).
- **Three customer-facing invoice types** — INV1 (bottle) + INV2 (shipment, with excise/VAT) + INV3 (semi-annual storage), all Module-S-issued (down from 7 at v17 — the typology is tighter and mechanically simpler).
- **The compliance + data-integrity floor whole** — KYC/sanctions/OFAC/Hold (uniform, DEC-181), tax-correct invoicing, dual-record FX, two-layer no-overselling, committed-inventory protection, audit/10-yr-retention — **none cut** (§3.2).
- **Manual-first in operations** — the back-office automations (supplier-settlement engine D19, INV3 dunning orchestration D4, Stage-8 inventory-workflow automation D16, returns/recall automation D14/D15, geography engines D3, late-binding optimisation D13, cellar richness D17, refund-cost-matrix D6) are **operator-run at launch**; their integrity cores / FSMs / events / entities are KEPT as the seam; only the *automation* defers. **Customer payment automation is NOT deferred** — Airwallex charge/refund + **chargeback automation (D21 KEPT — Paolo override)** are full launch build ([[keep-payment-automation]]).
- **The Admin Panel as the load-bearing operational surface** — because the manual-first defers made it so (D24). It is a **first-class cross-cutting surface** (Architecture §2.3), specced thin in the 9th PRD, and a **first-class build target** (not a thin mirror).
- **The NFC/on-chain decouple (D12)** — the per-bottle **serialization workflow is launch-ready** (NFC tag + serial + SerializedBottle ledger + Logilize); the **NFT mint/burn + on-chain layer is decoupled and feature-flagged** (`nft_reference` nullable + back-fillable; the **non-serialized [NS] path is the universal fallback** — every downstream degrades gracefully). DECOUPLE ≠ DEFER: the VP is preserved.

The launch is a **clean, coherent subset** of the v1.1 vision — the core loop + the whole floor, with the heavy non-floor automation deferred-with-a-seam to a post-launch roadmap (§4). It is not a different system; it is the v1.1 system with its non-essential mass deferred to pull the launch date forward.

---

## §2 The release manifest (a)

The artefacts composing the launch-MVP specification baseline. All live in `mvp/`; **none is promoted to `handoff/`** until the single coherent Phase-E handoff (GOV-2). Status legend: **✅ RATIFIED** (Paolo, date) · **⏳ DRAFTED** — awaiting batch ratification (the stable input; MVP-DEC-003). Statuses mirror the [decisions register §7](../04-decisions/MVP_Decisions_Register_v0.1.md) (which is the living ratification log — *it* wins on any drift).

### §2.1 The nine v0.3-MVP module PRDs (`02-prd/`)

Each is a strip + faithful re-baseline of its frozen v1.1 predecessor (greenfield v0.2), carrying only the KEPT scope at full fidelity, with every deferred capability named + seamed → roadmap, the naming cascade applied, the owned RECONCILE landed, and the floor preserved in composition (master §4 litmus).

| # | PRD | Path | Status | Launch-scope descriptor |
|---|-----|------|--------|-------------------------|
| 1 | **Module 0 — PIM** | [`Module_0_PRD_v0.3-MVP.md`](../02-prd/Module_0_PRD_v0.3-MVP.md) | ✅ RATIFIED 2026-06-07 | KEEP-in-full **+ the Wine→Product generalisation** (Product spine; `Product Type` = `WINE`). **Source of the naming cascade (§18).** |
| 2 | **Module K — Parties** | [`Module_K_PRD_v0.3-MVP.md`](../02-prd/Module_K_PRD_v0.3-MVP.md) | ✅ RATIFIED 2026-06-08 | KEEP-in-full. Compliance floor (KYC/sanctions/Hold/GDPR) + the D8 club spine + the one producer write (membership approve/decline). |
| 3 | **Module A — Allocations** | [`Module_A_PRD_v0.3-MVP.md`](../02-prd/Module_A_PRD_v0.3-MVP.md) | ✅ RATIFIED 2026-06-08 | KEEP-in-full. Supply primitive + sub-pools + Layer-1 no-oversell + the `VoucherCancelled` release primitive + the `direct_purchase` enum/FSM seam (idle). |
| 4 | **Module D — Procurement / Inbound** | [`Module_D_PRD_v0.3-MVP.md`](../02-prd/Module_D_PRD_v0.3-MVP.md) | ✅ RATIFIED 2026-06-08 | KEEP-heavy + **Direct Purchase deferred** (idle + seam) + **R1** (`SupplierPaymentCompleted` financial-event-only) + **consumes** the E-emitted `SupplierPaymentCompleted` (R4). |
| 5 | **Module S — Sales / Commerce** | [`Module_S_PRD_v0.3-MVP.md`](../02-prd/Module_S_PRD_v0.3-MVP.md) | ✅ RATIFIED 2026-06-08 | Cut-heavy (D7 composites / D5 gifting / D8 club-credit peripherals / D6 refund-matrix defer) + **R2** (storage Module-S-internal). **Voucher 7-state** at launch; INV1/INV2/INV3 + OC accrual at INV1. |
| 6 | **Module B — Bottle (Inventory Authority + Digital Provenance)** | [`Module_B_PRD_v0.3-MVP.md`](../02-prd/Module_B_PRD_v0.3-MVP.md) | ✅ RATIFIED 2026-06-08 | Cut-heavy (**D12 DECOUPLE on-chain** — serialization launch-ready, NFT feature-flagged; **D16 SIMPLIFY** Stage-8 automation → manual-first, integrity core FLOOR) + **R3** (Stream B1) + **consumes** `SupplierPaymentCompleted` (R4) + N1. |
| 7 | **Module C — Fulfilment** | [`Module_C_PRD_v0.3-MVP.md`](../02-prd/Module_C_PRD_v0.3-MVP.md) | ⏳ DRAFTED 2026-06-08 | Cut-heavy (D3 geography-hybrid / D13 pick / D14 returns / D17 cellar / D15 recall simplify) + **R3** (4-fulfilment-stream Logilize) + the in-transit redemption-block floor. |
| 8 | **Module E — Finance / Accounting Integration** | [`Module_E_PRD_v0.3-MVP.md`](../02-prd/Module_E_PRD_v0.3-MVP.md) | ⏳ DRAFTED 2026-06-08 | Cut-heavy (**D19 DEFER** settlement engine; **D4 DEFER** INV3 auto-escalation; **D21 KEEP** chargeback automation; **D18 FLOOR** dual-record FX) + **R4** (**emits** `SupplierPaymentCompleted`). |
| 9 | **Admin Panel — operator-surface / workflow layer** | [`Admin_Panel_PRD_v0.3-MVP.md`](../02-prd/Admin_Panel_PRD_v0.3-MVP.md) | ⏳ DRAFTED 2026-06-08 | **The thin 9th PRD** (Phase C item L) — operator-capability inventory over the 8 backends + the net-new cross-module consoles (finance-ops E; shared Logilize discrepancy queue B+C; white-glove C; returns/recall C; stocktake/quarantine/adjustment B; procurement/discrepancy D) + the producer-write boundary. Owns no entities. |

### §2.2 The nine v0.3-MVP acceptance docs (`03-acceptance/`)

Re-cut per each cut-sheet's §5 acceptance delta (naming cascade + scope annotations + deferred-feature criteria → roadmap + floor criteria unchanged); written alongside each PRD (MVP-DEC-002). Status tracks the paired PRD.

| Acceptance doc | Path | Status |
|----------------|------|--------|
| Module 0 | [`Module_0_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_0_Acceptance_v0.3-MVP.md) | ✅ RATIFIED 2026-06-07 |
| Module K | [`Module_K_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_K_Acceptance_v0.3-MVP.md) | ✅ RATIFIED 2026-06-08 |
| Module A | [`Module_A_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_A_Acceptance_v0.3-MVP.md) | ✅ RATIFIED 2026-06-08 |
| Module D | [`Module_D_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_D_Acceptance_v0.3-MVP.md) | ✅ RATIFIED 2026-06-08 |
| Module S | [`Module_S_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_S_Acceptance_v0.3-MVP.md) | ✅ RATIFIED 2026-06-08 |
| Module B | [`Module_B_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_B_Acceptance_v0.3-MVP.md) | ✅ RATIFIED 2026-06-08 |
| Module C | [`Module_C_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_C_Acceptance_v0.3-MVP.md) | ⏳ DRAFTED 2026-06-08 |
| Module E | [`Module_E_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_E_Acceptance_v0.3-MVP.md) | ⏳ DRAFTED 2026-06-08 |
| Admin Panel | [`Admin_Panel_Acceptance_v0.3-MVP.md`](../03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md) | ⏳ DRAFTED 2026-06-08 |

### §2.3 The cross-cutting deliverables

| Artefact | Path | Status | Role in the launch release |
|----------|------|--------|----------------------------|
| **Architecture v0.3-MVP (#10)** | [`Architecture_v0.3-MVP.md`](../02-prd/Architecture_v0.3-MVP.md) | ⏳ DRAFTED 2026-06-08 | The system-level consolidation (references the 9 backends; re-specs none). Lands the naming cascade + the four RECONCILEs + the Admin Panel as a first-class surface + **the composed-floor coherence assertion (§8) — the spine of this index's §3**. |
| **Post-Launch Roadmap v0.1 (#12)** | [`Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md) | ⏳ DRAFTED 2026-06-08 | The deferred-set buildout register — **the launch's deferred-scope home (§4)**. Extends frozen `greenfield/03-qa/qa.deferred.md`. The four coordinated restorations + the single-module automations + Direct Purchase + the full Admin-Panel surface + the v1.1 carry. |
| **MVP Decisions Register v0.1 (#14)** | [`MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md) | ✅ established 2026-06-07 (living through Phase D) | The thin index of every MVP re-scoping decision → its authoritative doc. **§7 is the living ratification log** this manifest's statuses mirror. Frozen into the Phase-E handoff. |
| **Build Workplan v0.3-MVP (#13)** | [`Build_Workplan_v0.3-MVP.md`](Build_Workplan_v0.3-MVP.md) | ⏳ DRAFT 2026-06-08 (this session) | This index's **coupled twin** — the re-baselined 9-phase build workplan, the **input to the dev-team sizing exercise**. Carries the build-sequencing flag (§5.3), the D12 feature-flag discipline, the finance manual-first posture, and the post-launch build backlog → roadmap. |
| **Release Index v0.1 (#13)** | `MVP_Release_Index_v0.1.md` (this file) | ⏳ DRAFT 2026-06-08 (this session) | This manifest. |

### §2.4 Upstream anchors + the triage / audit trail (the record behind the spec — not launch-spec artefacts)

These are **not** launch-release deliverables; they are the frozen anchors the re-baseline strips *from* and the triage record that produced the settled scope. Listed for audit completeness (the manifest is also the audit anchor); pointers only.

- **The frozen v1.1 baseline (never edited — plan R4):** the business-model layer [`greenfield/00-business-model/NewCo_BusinessModel_v0.9.md`](../../reference/v1.1/00-business-model/NewCo_BusinessModel_v0.9.md) (**unchanged** — the MVP is a scope re-baseline, not a business-model change), the 8 v0.2 PRDs + `Architecture_v0.2.md`, the v1.1 release index [`NewCo_ERP_Release_v1.1_Index.md`](../../reference/v1.1/00-release/NewCo_ERP_Release_v1.1_Index.md), the v1.1 build workplan [`Build_Workplan_v0.1.md`](../../reference/v1.1/09-build-readiness/Build_Workplan_v0.1.md), the frozen decisions register `greenfield/04-decisions/decisions.md` (DEC-001..196 — **bridged, never extended**), and the v1.1 deferred register `greenfield/03-qa/qa.deferred.md`.
- **The MVP triage record (Phase A–C):** the method/dials docs (`00-method/`), the **8 ratified cut-sheets** (`01-triage/Module_X_CutSheet_v0.1.md`), and the **Phase C cross-module reconciliation** ([`Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) — RATIFIED 2026-06-07; the coherence gate that discharged R1 and produced the four RECONCILEs + the build-sequencing flag).

---

## §3 The headline coherence assertion — it hangs together (b)

> This is the release's *"it hangs together"* statement. It is **carried from Phase C §1 (the coherence gate's verdict) + Architecture §8 (the system-level assertion)** — this index records it; it does not re-derive it. If this section drifts from those, **they win.**

### §3.1 The assertion (R1 discharged)

**The nine trimmed module scopes compose into one coherent launch system.** Phase C (the cross-module coherence gate, RATIFIED 2026-06-07) verified — and the nine v0.3-MVP PRDs + the Architecture wrote down — that **R1 (stripping one module silently breaks another) is discharged**: *no orphaned KEEP* (every KEPT capability's upstream is KEPT, or its seam is real), *no orphaned DEFER* (every deferred item idles consistently with its downstream consumers, or its seam is read additively post-launch), the **trimmed event contract is internally consistent** across all nine PRDs (emitter ↔ consumer match; the four RECONCILEs land identically on both sides; the naming cascade is uniform — Architecture §8.1), the **floor is whole end-to-end across the composed system** (§3.2), and the **deferred set composes** into a coherent roadmap (the tri-/multi-module restorations defer + restore as coordinated sets — Phase C item N; §4). A builder could implement the launch scope from this set; every deferred capability is named with its seam + roadmap pointer.

Why it holds (Phase C §1.2, honest calibration): the supply-side quartet (0/K/A/D) stayed near-whole — each forwarded or pre-factored its headline lever — so the cut-heavy modules (S/B/C/E) read whole upstreams; every DEFER/SIMPLIFY named a forward-compat seam (P1), verified real on **both** sides; the four stale-framing drifts were residual *prose* in one owning module's PRD, not contradictions between modules.

### §3.2 The six floor chains composed (Architecture §8.2 / Phase C §6 / item M)

**No cut breaches the floor in composition.** Each chain composes end-to-end across the composed system (not just per-module), all KEPT as FLOOR:

1. **No-overselling** — Module A Layer 1 (`qty − issued ≥ 0`) ∧ Module B Layer 2 (`physical_in_storage − reserved − quarantined − under_adjustment ≥ 0`) ∧ Module S shared-pool decrement + lesser-of storefront read ∧ Module C no-oversell-at-pick StockPosition read — strongly consistent, **per sub-pool**; **Layer-2's sale-gate is sourcing-model-scoped (MVP-DEC-027): warehouse-resident stock (`passive_v2`; received `direct_purchase`) — `passive_v1` sells on Layer 1 alone, physical receipt gating shipment not sale (DEC-081 / Phase C item K).** ✓ *(carries the build-sequencing flag — §5.3)*
2. **KYC / sanctions / OFAC / Hold** — Module K floor → Module S sanctions/Hold gate at order completion (the consumer-side enforcement point) → Module C OFAC at all destinations → Module E re-read at charge; DEC-181 uniformity at every transaction-initiation surface. ✓
3. **Tax-correct invoicing (MPV VAT regime)** — Module S issues INV1/INV2/INV3 (Module-S-internal — R2) → Module C contributes excise (`ExciseCalculated`, runs even in white-glove) + actual shipping → Module S composes + issues INV2 → Module E records + charges + routes to Xero. ✓
4. **Dual-record FX** — Module E records every customer-facing event in customer-currency + EUR; per-leg rate-lock; refund at the original captured rate; `FXVarianceRecorded` (D18 — FLOOR). ✓
5. **Committed-inventory protection** — Module A's `VoucherCancelled` release primitive ↔ Module B's `InventoryShortfallDetected` + committed-inventory guard; applies identically to NS. ✓
6. **Audit / retention** — Module K GDPR erasure + 10-yr txn retention + Module E 10-yr archival + post-sync immutability + the `actor_role` audit envelope across all operator surfaces (the Admin-Panel arm). ✓

### §3.3 The trimmed event contract is internally consistent (Architecture §8.1)

The cross-module event spine for the launch set is internally consistent — the naming cascade applied (`Bottle Reference → Product Reference`; `Wine*/BottleReference* → Product*`; carve-outs for Module E's category-neutral names + Modules B/C's physical-unit names honoured), the four RECONCILEs landed (R1 D/DEC-183 · R2 S/DEC-119 · R3 C/4-stream · **R4 E-emits `SupplierPaymentCompleted` in all three loci** — E emits, D + B consume independently), and the deferred events named + carried to the roadmap with their seam (the four `VoucherGift*`; the multi-FK composite-Offer events; the on-chain `NFTMinted`/recovery events; the settlement-statement FSM events — each restores additively with its coordinated set). The launch contract is whole without them.

---

## §4 The deferred set — the launch is a clean subset (d)

The launch is a **clean, coherent SUBSET (P1) of the v1.1 vision.** The deferred scope is **not deleted** — it lives, in full, in the **[Post-Launch Roadmap v0.1 (#12)](../04-roadmap/Post_Launch_Roadmap_v0.1.md)**, each item with its **seam** (the forward-compat hook that makes the post-launch build *additive*) + its re-introduction trigger + its dependencies. **This index points there; it does not restate the deferred set.** Its shape:

- **The four coordinated multi-module restorations** (defer + restore as coordinated sets — Phase C item N): **gifting** (S + K + C); **Discovery composites** (S + A + 0); **NFT on-chain** (B + downstream — gated on the external EXT-1/EXT-3 blockchain workstream); **the supplier-settlement engine** (E + D + S + A — **the single most-depended-on restore**; composite-OC computation + clawback netting + OC-5% computation + partial-PO settlement all land with it).
- **The single-module manual-first / simplify automations** (the integrity core / FSM / events / entities KEPT as the seam; the automation defers): D4 INV3 dunning (E); D14 returns/replacement FSM automation (C); D16 Stage-8 inventory-workflow automation (B+D); D3 geography engines (C); D13 late-binding bottle-side optimisation (C); D17 cellar richness (C); D6 refund-cost-matrix (S).
- **Direct Purchase** (A/D/B/E/S idle, confirmed no launch deal — re-enable additive).
- **The full Admin-Panel surface** (three additive axes — the restored automation consoles + the producer-portal write-UIs + the UX/IA/RBAC platform layer; the thin MVP slice ships now, the full surface accretes in the roadmap — it had no v1.1 predecessor).
- **The v1.1 already-deferred set carried verbatim** (active consignment / B2B / drop-ship / liquid sales / P2P / multi-warehouse / US-state + DDP-DAP expansion / SDI connector / smart-contract audit / AI-copilot / native mobile / …) — **not re-cut**.

**No KEPT item silently depends on a deferred one** (Phase C item N, verified per coordinated set). The defers are timing-safe where timing matters — the first storage cycle (D4/INV3 dunning) and the first settlement close (D19 engine) are months post-launch, so the manual-first first cycle(s) precede the automation build comfortably.

---

## §5 Open items carried (e)

Flagged here, pointed to their owners — **none is a coherence break; none breaches the floor; none re-opens a ratified decision.**

### §5.1 Batch-ratification status

Per **MVP-DEC-003**, Phase-D ratification is **batched** — production did not block on per-artefact sign-off; the nine PRDs + acceptance + Architecture + roadmap are the stable input. As of this index (2026-06-08): **Modules 0/K/A/D/S/B PRD+acceptance are ✅ RATIFIED**; **Modules C/E + the Admin-Panel PRD+acceptance + the Architecture + the roadmap + this index + the workplan are ⏳ DRAFTED — awaiting batch ratification.** The decisions register [§7](../04-decisions/MVP_Decisions_Register_v0.1.md) is the living log; on ratification, its rows flip and this manifest's §2 statuses follow. **The batch-ratification of the still-⏳ set is the gate into Phase E** (the single coherent handoff).

### §5.2 The Architecture §8.3 cross-PRD consistency flags (for batch ratification)

The Architecture surfaced six minor cross-PRD editorial / consumer-list drifts at the system level — **all naming/contract-consistency items, no behaviour change, none breaches the floor.** Per the anti-patterns they are **flagged for Paolo (batch ratification), not silently resolved into a new contract** (the canonical contract is the emitter's PRD; the consumer-side reference aligns at ratification). Carried here so the launch manifest records the open editorial set:

1. **Module E stale internal cross-ref "§5.11 L-PP"** — E §0/§1.4 cite a non-existent §5.11; the L-PP / finance-ops content is present (E §1.4 + §3.2/§3.3/§4.4). One-line §-anchor fix.
2. **`AllocationSerializationPlanChanged`** — Module B's consumed-event label vs Module A's two atomic events (`AllocationSubPoolRebalanced` + `AllocationNonSerializedOptOutChanged`). Canonical = the two atomic events; align B's label.
3. **`AllocationPoolDebitedDueToLoss`** — Module C / Module B reference an A-side loss/write-off pool-debit emission Module A's catalogue does not enumerate; confirm the emission locus (Module A emit vs derived from Module B's `InventoryAdjusted`).
4. **Consumer-list enumeration gaps (two)** — (i) Module 0 names Module C a `ProductReferenceRetired` consumer; C reads PR identity on-demand and does not enumerate it; (ii) Module S subscribes to `InboundEventPhysicallyAccepted` (the R2 storage-clock anchor) but Module D's consumer list does not enumerate S. Both additive enumeration alignments.
5. **`AllocationCapacityExhausted`** *(resolved — recorded for completeness)* — over-issuance is an operation-level rejection (`qty − issued ≥ 0`), no event; consistent across S/A/B/C.
6. **Club Credit / Store Credit event vocabulary** *(reconciled — MVP-DEC-018)* — the canonical issuance event is **`ClubCreditAccrued`** (Module-E-emitted) and the application events (`ClubCreditAutoApplied` / `ClubCreditRemovedByCustomer` / `StoreCreditApplied`) are **Module-S-emitted** per DEC-166 + DEC-174; Module K owns the balance + consumes from both. The earlier "`ClubCreditAccrued` → `ClubCreditIssued` (resolved)" digest note was itself stale (it contradicted the frozen DEC-174); Module K + Module E swept at MVP-DEC-018.

### §5.3 The build-sequencing flag (→ the workplan + the dev-team sizing exercise)

**The single most delicate floor-sequencing constraint** (Phase C item G/Q5; Architecture §8.3). The no-overselling floor is whole at the *integrated* launch **provided the build workplan sequences Module B's floor artefacts (the Layer-2 push pipeline, InboundBatch, StockPosition, per-sub-pool ATP) to be integration-ready by the integrated launch, not as a post-launch follow-on** — because Module A's Layer 1 (build-phase 3), Module S's lesser-of storefront read (build-phase 4), and Module C's no-oversell-at-pick (build-phase 5) all depend on Module B's side (build-phase 5) being live. This is a **sequencing confirmation, not a cut.** It is the **reason-for-being of the coupled [build-workplan](Build_Workplan_v0.3-MVP.md)** (carried explicitly in its §1 + Phase 5 + §4) and a load-bearing input to the **dev-team sizing exercise**. *(D12 interaction: at launch, if the on-chain workstream is decoupled-and-slipping, Layer 2 = the NS sub-pool ATP — so B's NS ledger + InboundBatch + StockPosition + the B→A push are the load-bearing floor, independent of the decoupled NFT cluster.)*

### §5.4 The time-sensitive Paolo-track items (EXT-1 / DEC-124)

Two **near-term, time-sensitive** items (Phase C Q3 / item J) — the *design* work is dev-phase, but the *scheduling / procurement* is near-term and must not slip (track/schedule early):

1. **Schedule the EXT-1 blockchain-expert review now** — it gates the NFT/on-chain surfaces unflagging in production (the D12 decouple); if not scheduled early it becomes the launch critical path.
2. **Confirm the DEC-124 NFC tag-stock procurement lead-time + tag-content back-fill design** — NFC tags apply at launch (serial + Bottle Page URL); the on-chain reference is back-fillable. The pre-launch tag-stock procurement lead-time is the time-sensitive bit.

Both are **Paolo-track** + tracked/scheduled early; the roadmap [§7.1](../04-roadmap/Post_Launch_Roadmap_v0.1.md) carries them as time-sensitive triggers.

---

## §6 Boundary statement (IS / IS NOT)

The launch-MVP release (this index + the manifest it points to) **IS**:
- the **launch-scope specification baseline** — the KEPT scope at full fidelity, held in `mvp/` until the single coherent Phase-E handoff (GOV-2);
- the **manifest + coherence assertion** for the launch slice — one document orienting the dev team / coding agents / the Phase-E handoff to where each spec piece lives and that they compose;
- once **batch-ratified**, the FROZEN delta point for the build and the audit anchor for subsequent change (the deferred set restores additively against it).

The launch-MVP release **IS NOT**:
- the **build artefact** (no code);
- the **estimate** — the sizing and dating is the **separate sizing/estimation step (done with the dev team)**; its input is the coupled [build-workplan #13](Build_Workplan_v0.3-MVP.md). This release does not size, staff, or date;
- the **operational runbook** (no SOPs / authority-tiers / approval workflows — admin-configurable, `feedback_prd_rr_approval`);
- the **technical-architecture spec** (DEC-073 — no stack / schema / API / framework / hosting / CI-CD / UX; the Admin-Panel UX + the RBAC/authority-tier model are downstream);
- the **GL accounting policy** (DEC-072 — Module E records financial events; Xero decides treatment);
- a **handoff** — nothing is promoted to `handoff/` until Phase E (GOV-2 / MVP-DEC-003).

---

## §N Inheritance & source trace (audit-trail only; not load-bearing)

Per DEC-074, §0–§6 are self-contained NewCo prose. This trace is audit-only.

- **Predecessor:** `greenfield/00-release/NewCo_ERP_Release_v1.1_Index.md` (RELEASED v1.1 2026-05-09; frozen). This index is a **strip + re-baseline** — it carries the v1.1 manifest structure (per-module spec roster + cross-cutting deliverables + decisions register + out-of-launch register + boundary statement) and re-points it at the `mvp/` re-baseline set: **+1 module PRD** (the 9th Admin Panel — no v1.1 predecessor); **+9 acceptance docs** (the v0.3-MVP acceptance set, written alongside each PRD); the v1.1 §1 BMD / §3 Summary / Producer-Reporting roster collapses to the **upstream-anchor note** (§2.4 — the business model is unchanged; the Architecture is the system-level consolidation; producer reporting is folded into Module S/D23 + the Admin-Panel boundary); the v1.1 §7 out-of-launch register re-points to the roadmap (#12).
- **Source inputs (DEC-074):** the nine v0.3-MVP PRDs + acceptance; the Architecture v0.3-MVP (§8 — the coherence assertion); the Post-Launch Roadmap v0.1 (the deferred set); the MVP decisions register (§7 — the statuses); the Phase C reconciliation (§1 verdict / §5 RECONCILEs / §6 floor / item G/L/N); the master `Phase_D_Kickoff_Prompt.md` (§5 row 13 + §6). **No new scope cuts taken; no ratified decision re-opened; no tech-implementation; no accounting position; no re-estimate.**

---

## §X Cross-references

- **The coupled twin** — [`Build_Workplan_v0.3-MVP.md`](Build_Workplan_v0.3-MVP.md) (the re-baselined 9-phase build workplan; the input to the dev-team sizing exercise).
- **The launch spec** — `02-prd/` (the 9 PRDs + Architecture), `03-acceptance/` (the 9 acceptance docs).
- **The deferred set** — [`04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md).
- **The decisions index** — [`04-decisions/MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md) (the living ratification log, §7).
- **The coherence gate** — [`01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md).
- **The frozen v1.1 predecessor** — [`greenfield/00-release/NewCo_ERP_Release_v1.1_Index.md`](../../reference/v1.1/00-release/NewCo_ERP_Release_v1.1_Index.md) (never edited).

---

*End of Launch-MVP Release Index v0.1 — **DRAFT, awaiting batch ratification.** The launch manifest: the nine v0.3-MVP PRDs + nine acceptance docs + the Architecture + the roadmap + the decisions register + the coupled build-workplan, each with its status + pointer. The headline coherence assertion (carried from Phase C §1 / Architecture §8): the nine trimmed module scopes compose into one coherent launch system; the six floor chains are whole end-to-end; the trimmed event contract is internally consistent; the deferred set is a clean subset, seamed → roadmap. The launch IS the producer-club aggregator on passive consignment, B2C-only, single-warehouse, six-locale, five-currency, manual-first in ops, with the Admin Panel as the load-bearing operational surface and the NFC/on-chain layer decoupled. A manifest — not new scope, not a re-spec, not the re-estimate. With this index + its coupled build-workplan drafted, the Phase-D re-baseline is COMPLETE; the triage turns to Phase E.*
