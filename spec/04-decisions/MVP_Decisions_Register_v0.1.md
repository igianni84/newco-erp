# NewCo ERP — Launch-MVP Decisions Register v0.1 (thin index)

- **Version**: v0.1 (living — accretes through Phase D; **frozen into the Phase E handoff**).
- **Date**: 2026-06-07
- **Status**: OPEN — **Phase-D re-baseline drafting COMPLETE + BATCH-RATIFIED 2026-06-08** (all artefacts #1–#14 drafted + ratified; the still-⏳ set batch-ratified by Paolo 2026-06-08 — §7; the gate into Phase E is CLEARED). Living through Phase E; **frozen into the Phase-E handoff.** Established by Paolo's decision 2026-06-07 (a **thin index**, not a full restatement).
- **Owner**: Paolo (decides). Claude maintains.
- **Purpose**: a single greppable map of **every decision the launch-MVP re-scoping has made** — for the dev/build phase, coding agents, and the Phase E handoff. The MVP's decisions were otherwise spread across the method docs, the 8 cut-sheets (§6 Q's), Phase C (§5 RECONCILEs + notes), and the PRD headers; this file indexes them in one place.

---

## §1 How to read this register

- **This is an INDEX, not a source of truth.** Each row points to the **authoritative doc** where the decision is specified in full and self-contained (DEC-074). If this register ever conflicts with the cut-sheet / Phase C / PRD it points to, **that doc wins.** Do not restate or re-litigate decisions here — add/update a one-line row and point.
- **ID scheme** (reuses the handles already in play; does **not** invent a parallel numbering that competes with greenfield):
  - **GOV-1..3 / P1–P2** — the governing decisions + binding principles (plan §2 / §4.1).
  - **D1–D25 / L-PP** — the macro dials (`Dials_Worksheet_v0.1.md` §5).
  - **Module Q's** — the per-module cut-sheet ratifications (`01-triage/Module_X_CutSheet_v0.1.md` §6), cited as e.g. *Mod0-Q1*, *K-Q4*.
  - **A–N / R1–R4 / N1–N3** — the Phase C register items / RECONCILEs / editorial notes (`01-triage/Phase_C_Reconciliation_v0.1.md`).
  - **MVP-DEC-NNN** — a small **new** stream for net-new Phase-C/D decisions with no prior handle (§6).
  - **DEC-NNN** — **greenfield's frozen register** (`greenfield/04-decisions/decisions.md`, DEC-001..196). **Never extended here** — this register only *bridges* to it ("touches / supersedes-at-launch").
- **Status legend**: ✅ RATIFIED (Paolo, date) · ⏳ DRAFTED (awaiting batch ratification) · ⛔ DEFERRED-to-roadmap · 🔁 SUPERSEDED-at-launch (greenfield DEC reframed for the MVP; restores post-launch).
- **The deferred set + its seams (P1) lives in** `04-roadmap/Post_Launch_Roadmap_v0.1.md`; this register flags *that* a thing is deferred and points there.

---

## §2 Governing decisions & binding principles (locked 2026-06-05)

| ID | Decision | Status | Authoritative doc |
|---|---|---|---|
| **GOV-1** | **Lean MVP** — aggressive defer + manual-first where safe; KEEP only what the core loop or the compliance/data-integrity floor requires. | ✅ 2026-06-05 | `00-method/MVP_Restructure_Plan_v0.1.md` §2.1 |
| **GOV-2** | **One coherent re-baseline, then a single handoff** — strip + reconcile the whole MVP in `mvp/`; promote to `handoff/` once, coherent. **No piecemeal handoff.** | ✅ 2026-06-05 | plan §2.2 |
| **GOV-3** | **High-touch governance** — Paolo ratifies; Claude recommends. | ✅ 2026-06-05 | plan §2.3 |
| **P1** | **Defer without burning bridges** — every deferred/simplified item names the **seam** that makes the post-launch build additive (+ points to the roadmap). | ✅ 2026-06-05 | plan §4.1 |
| **P2** | **Admin-first, self-serve-later** — producer/back-office writes are operator-driven via the Admin Panel (admin-parity DEC-083); consumer storefront exempt. | ✅ 2026-06-05 | plan §4.1; bridges **DEC-083** |

---

## §3 Macro dials (decided 2026-06-05; `Dials_Worksheet_v0.1.md` §5)

The **final ratified** decision per dial (where it diverges from the §1 Lean recommendation — e.g. D1, D2, D21 — the §5 decision / Paolo override governs).

| Dial | Final decision | Status | Greenfield DEC bridge |
|---|---|---|---|
| **D1** Currencies | **KEEP all 5** (dual-record machinery is fixed-cost) | ✅ | — |
| **D2** Locales | **KEEP** (6 locales; i18n infra reportedly built; content may stagger) | ✅ | DEC-031, DEC-064 |
| **D3** Geography | **SIMPLIFY (hybrid)** — automate low-friction; manual operator quote for complex (US/high-excise/state-alcohol); **OFAC retained (floor)**; automated US-state/excise engines deferred | ✅ | 🔁 DEC-148/149/150 (deferred) |
| **D4** Payments | **KEEP card + SEPA; DEFER saved-card auto-escalation (INV3 dunning)** — timing-driven (storage billing months out) | ✅ / ⛔(escalation) | — |
| **D5** Gifting | **DEFER** — seam: voucher ownership-transfer capability (no hard single-permanent-owner) | ⛔ | — |
| **D6** Cancellation/refund | **KEEP 14-day (legal) + basic refund; SIMPLIFY the matrix** (goodwill/partial-voucher → manual) | ✅ | — |
| **D7** Discovery composites | **SIMPLIFY → single-producer at launch**; defer multi-producer atomic composites — seam: Composite SKU + N-constituent retained (Mod 0) | ✅ / ⛔(multi-producer) | DEC-019, DEC-061 |
| **D8** Club / membership | **KEEP (full — core VP)** | ✅ | — |
| **D9** Catalog LWIN + bulk | **KEEP** + basic bulk; **manual-first enrichment** (LWIN pluggable, off critical path — Mod0-Q3) | ✅ | — |
| **D10** Allocation sub-pools | **KEEP** (core supply primitive) | ✅ | — |
| **D11** Procurement variants | **KEEP both consignment (V1/V2); DEFER Direct Purchase** — seam: uniform flow parameterized | ✅ / ⛔(Direct Purchase) | DEC-011, DEC-063, **DEC-093** |
| **D12** NFT / blockchain + NFC | **KEEP (core VP), DECOUPLED parallel workstream** — on-chain off the critical path; serialization stays launch-ready | ✅ / 🔁(on-chain decoupled) | DEC-050, DEC-052, DEC-134 |
| **D13** Late-binding pick | **SIMPLIFY → FIFO + manual tiebreak** | ✅ | — |
| **D14** Returns / replacements | **SIMPLIFY → manual-first (Admin Panel)**; defer the FSM automation | ✅ / ⛔(automation) | — |
| **D15** Producer recall | **KEEP minimal / manual** | ✅ | — |
| **D16** Stocktake / quarantine / adjustments | **KEEP integrity core (floor); SIMPLIFY the workflows → manual-first** (delicate — joint B/D review) | ✅ / ⛔(automation) | DEC-194, DEC-195 |
| **D17** Cellar render | **SIMPLIFY → basic view**; defer richest aggregation (in-transit ETA precision, granular storage) | ✅ / ⛔(richness) | — |
| **D18** Multi-currency dual-recording | **KEEP (floor)** — FX-correct refunds; unsafe to simplify | ✅ | — |
| **D19** Supplier settlement | **DEFER → operator-run first cycle(s)**; the engine builds after — first close is months post-launch | ⛔ | — |
| **D20** Italian SDI connector | **already deferred (EXT-2)** — no action | ⛔ | — |
| **D21** Chargeback | **KEEP chargeback automation** — **Paolo override** of the Lean "manual" rec (payment automation is floor) | ✅ | DEC-014, DEC-028; see [[keep-payment-automation]] |
| **D22** Services / experiences | **KEEP storage-only** (already lean) | ✅ | — |
| **D23** Producer reporting | **KEEP full self-serve** (all 7 sections, real-time) — core to the producer promise | ✅ | — |
| **D24** Admin Panel | **KEEP (scoped to MVP ops)** — *more* load-bearing in a manual-first MVP (→ the 9th PRD, Phase C item L) | ✅ | DEC-083 |
| **D25** Support tooling | **KEEP email + admin lookup** (already lean) | ✅ | — |
| **L-PP** | **Producer portal = full read, ops-driven writes**; exactly **ONE producer write** retained: membership approve/decline (K) | ✅ | DEC-083; bridges K-Q4 |

---

## §4 Phase B cut-sheet verdicts (RATIFIED 2026-06-07; `01-triage/Module_X_CutSheet_v0.1.md`)

One row per module (the §6 Q's are in each cut-sheet). Verdicts per the master `Phase_D_Kickoff_Prompt.md` §5.

| Module | Verdict | Authoritative doc |
|---|---|---|
| **0 (PIM)** | KEEP-in-full **+ Wine→Product generalisation**; ~0 net-new deferrals | `Module_0_CutSheet_v0.1.md` |
| **K (Parties)** | KEEP-in-full + naming cascade (compliance floor + D8 club spine intact) | `Module_K_CutSheet_v0.1.md` |
| **A (Allocations)** | KEEP-in-full + cascade (supply primitive + Layer-1 no-oversell + Direct-Purchase enum/FSM seam) | `Module_A_CutSheet_v0.1.md` |
| **D (Procurement)** | KEEP-heavy + **Direct Purchase deferred** (idle + seam) + R1 + E-emits consumer | `Module_D_CutSheet_v0.1.md` |
| **S (Sales)** | Cut-heavy (D7 composite / D5 gifting / D8 club-credit peripherals / D6 refund-matrix) + R2 | `Module_S_CutSheet_v0.1.md` |
| **B (Bottle/Stock)** | Cut-heavy (**D12 DECOUPLE on-chain**; serialization launch-ready; **D16 SIMPLIFY** Stage-8 → manual-first) + E-emits ownership + N1 | `Module_B_CutSheet_v0.1.md` |
| **C (Fulfilment)** | Cut-heavy (D3 geography-hybrid / D13 pick / D14 returns / D17 cellar / D15 recall) + R3 + in-transit redemption-block floor | `Module_C_CutSheet_v0.1.md` |
| **E (Finance)** | Cut-heavy (**D19 DEFER** settlement engine; **D4 DEFER** INV3 auto-escalation; **D21 KEEP** chargeback automation; **D18 FLOOR**) + R4 | `Module_E_CutSheet_v0.1.md` |

**Load-bearing cross-cutting ratifications (called out; full set in each §6):**
- **Mod0-Q1** Composite SKU KEPT (single-producer bundles + D7 seam) · **Mod0-Q2** approval role-count admin-configurable (no spec change) · **Mod0-Q3** manual-first enrichment (LWIN pluggable, off critical path). ✅
- **K-Q4** exactly one producer write platform-wide = membership approve/decline (= L-PP). ✅
- **A-Q1** D7 multi-producer-composite cut **forwarded to Module S** — Module A takes no D7 cut, ships its full supply primitive as the seam (mirrors K→S for D8). · **A-Q2** Direct Purchase deferred, A keeps the `direct_purchase` enum + uniform-FSM seam (substantive defer is D's). · **A-Q3** all A ops operator-driven via Admin Panel, **zero producer writes, no backend cut**. ✅
- **D21 override** — chargeback automation KEPT (Paolo). ✅ → [[keep-payment-automation]]

---

## §5 Phase C reconciliation outcomes (RATIFIED 2026-06-07; `01-triage/Phase_C_Reconciliation_v0.1.md`)

**Verdict: the 8 trimmed scopes compose into one coherent system; R1 (cross-module incoherence) discharged.** No new scope cuts. The four RECONCILEs are the concrete Phase-D PRD edits (naming/contract only, zero behaviour change).

| ID | Decision | Status | Authoritative locus / greenfield bridge |
|---|---|---|---|
| **A** | Naming cascade (BR→PR; Wine*→Product*); Module 0 v0.3-MVP §18 = source of truth; carve-outs (E neutral; B/C physical-unit) | ✅ CONSISTENT | Phase C §2-A; Module 0 PRD §18 |
| **R1** | Module D — `SupplierPaymentCompleted` is **financial-event-only** (no Direct-Purchase FSM-activation role); activation is uniform operator-publish | ✅ RECONCILE | Phase C §5-R1; 🔁 **DEC-183** |
| **R2** | Module S — storage is **Module-S-internal** (single D→S read of `InboundEventPhysicallyAccepted`; no bidirectional S↔E at INV2) | ✅ RECONCILE | Phase C §5-R2; 🔁 **DEC-118→DEC-119** |
| **R3** | Module C — **4-fulfilment-stream** Logilize contract (storage-location migrated to Module B's Stream B1) | ✅ RECONCILE | Phase C §5-R3; 🔁 **DEC-188** |
| **R4** | **`SupplierPaymentCompleted` = E-emits** (flipped at ratification, Q2) → Module D consumes (settle/close PO) + Module B consumes (`ownership_flag` PRODUCER→CRURATED). Corrects the cut-sheets' "D-emits". | ✅ RECONCILE | Phase C §2-C/§5-R4; 🔁 **DEC-091 / DEC-119** |
| **N1** | D16 manual-first-automation posture landed **identically** in B + D PRDs (integrity core = floor; only automated round-trips defer) | ✅ note | Phase C §5-N1; DEC-194 |
| **N2** | Finance Hold triggers: **chargeback automated (D21)**, **storage-payment manual-first (D4 deferred)**; K's Hold registry trigger-agnostic — align K.28 + E prose | ✅ note | Phase C §5-N2 |
| **N3** | Party naming: PO-level enum `NEWCO` (DEC-085) vs inventory `ownership_flag` `CRURATED` (DEC-185) — same party; make `OwnershipTransitioned` cascade prose unambiguous | ✅ note | Phase C §5-N3; DEC-085, DEC-185 |
| **I / Q4** | **Direct Purchase deferred at launch** — confirmed no launch deal; idles across A/D/B/E/S with retained enum/FSM seam | ⛔ | Phase C item I / Q4; DEC-093 (seam) |
| **L / Q1** | **The 9th, thin Admin-Panel MVP PRD** (operator-surface scope); the *full* Admin-Panel surface → roadmap; Architecture records it as first-class | ✅ | Phase C item L / Q1 |
| **G / Q5** | No-overselling **build-sequencing** (B's floor artefacts integration-ready by the integrated launch) → carried to the dev-team sizing exercise | ✅ (sequencing flag) | Phase C item G / Q5 |
| **M** | **The end-to-end floor is whole across the composed system** (no-oversell · KYC/sanctions/OFAC/Hold · tax-correct invoicing · dual-record FX · committed-inventory · audit/retention) | ✅ CONSISTENT | Phase C §6; DEC-181, DEC-099 |
| **Q3 (P-C)** | D12 owned action items (EXT-1 blockchain-review scheduling; DEC-124 tag-stock lead-time) → Paolo-track, time-sensitive | ✅ (Paolo-track) | Phase C Q3; DEC-124 |

*(Phase C items D/E/F/H/J/K/N were CONSISTENT — no change needed; see Phase C §2–§4 for the verification.)*

---

## §6 Phase D net-new decisions (MVP-DEC stream)

Decisions with no prior handle, made during Phase C ratification / Phase D.

| ID | Decision | Status | Authoritative doc |
|---|---|---|---|
| **MVP-DEC-001** | **Production cadence** — one artefact per fresh context window; write the next sub-session kickoff before moving (Phase B + D) | ✅ | [[mvp-triage-cadence]]; `00-method/*_Kickoff_Prompt.md` |
| **MVP-DEC-002** | **Phase D acceptance rides alongside each PRD** — write `Module_X_Acceptance_v0.3-MVP.md` in the same session as the PRD | ✅ 2026-06-07 | [[mvp-triage-cadence]]; master kickoff §5 |
| **MVP-DEC-003** | **Phase D ratification is batched** — Paolo ratifies several artefacts at checkpoints he chooses; production continues; nothing to `handoff/` until Phase E | ✅ 2026-06-07 | [[mvp-triage-cadence]] |
| **MVP-DEC-004** | **The Module 0 generalisation is folded into the v0.3-MVP PRD** (not a separate greenfield v0.3); **no new greenfield DEC minted** (greenfield frozen, R4) — bridges the generalisation brief's proposed DEC substance + DEC-065 | ✅ 2026-06-07 | `Module_0_CutSheet_v0.1.md` §3; brief; Module 0 PRD §16; bridges **DEC-065** |
| **MVP-DEC-005** | **Q3 manual-first enrichment acceptance scheduling** — LWIN-adapter criteria are "verified when the adapter lands" (not launch-blocking); the manual baseline is launch-critical | ✅ 2026-06-07 | Module 0 Acceptance §0.1 |
| **MVP-DEC-006** | **This MVP decisions register established** — a thin index in `mvp/04-decisions/`; reuse handles + MVP-DEC stream; bridges (never extends) greenfield DEC-NNN; **frozen into the Phase E handoff** | ✅ 2026-06-07 | this file |
| **MVP-DEC-007** | **The Admin-Panel surface is role-agnostic at the PRD layer** — the authority-tier / RBAC / persona-gating policy is admin-configurable + downstream (`feedback_prd_rr_approval`); the **only** PRD-level discipline is the spec-mandated multi-actor separation-of-duties floor (3-step approval; supervisor-override; single-supervisor-approval — self-approval never allowed). Applies Mod0-Q2 / K-Q3 to the consolidated Admin-Panel surface. | ✅ 2026-06-08 (ratified with the Admin-Panel PRD) | Admin-Panel PRD §1.4 / §5.2; `feedback_prd_rr_approval` |

---

## §7 Phase D artefact ratification log (accretes)

| Artefact | Status | Date |
|---|---|---|
| **Module 0 PRD v0.3-MVP** (`02-prd/`) | ✅ RATIFIED | 2026-06-07 |
| **Module 0 Acceptance v0.3-MVP** (`03-acceptance/`) | ✅ RATIFIED | 2026-06-07 |
| **Module K PRD v0.3-MVP** (`02-prd/`) | ✅ RATIFIED | 2026-06-08 |
| **Module K Acceptance v0.3-MVP** (`03-acceptance/`) | ✅ RATIFIED | 2026-06-08 |
| **Module A PRD v0.3-MVP** (`02-prd/`) | ✅ RATIFIED | 2026-06-08 |
| **Module A Acceptance v0.3-MVP** (`03-acceptance/`) | ✅ RATIFIED | 2026-06-08 |
| **Module D PRD v0.3-MVP** (`02-prd/`) | ✅ RATIFIED | 2026-06-08 |
| **Module D Acceptance v0.3-MVP** (`03-acceptance/`) | ✅ RATIFIED | 2026-06-08 |
| **Module S PRD v0.3-MVP** (`02-prd/`) | ✅ RATIFIED | 2026-06-08 |
| **Module S Acceptance v0.3-MVP** (`03-acceptance/`) | ✅ RATIFIED | 2026-06-08 |
| **Module B PRD v0.3-MVP** (`02-prd/`) | ✅ RATIFIED | 2026-06-08 |
| **Module B Acceptance v0.3-MVP** (`03-acceptance/`) | ✅ RATIFIED | 2026-06-08 |
| **Module C PRD v0.3-MVP** (`02-prd/`) | ✅ RATIFIED | 2026-06-08 |
| **Module C Acceptance v0.3-MVP** (`03-acceptance/`) | ✅ RATIFIED | 2026-06-08 |
| **Module E PRD v0.3-MVP** (`02-prd/`) | ✅ RATIFIED | 2026-06-08 |
| **Module E Acceptance v0.3-MVP** (`03-acceptance/`) | ✅ RATIFIED | 2026-06-08 |
| **Admin-Panel PRD v0.3-MVP** (`02-prd/`) | ✅ RATIFIED | 2026-06-08 |
| **Admin-Panel Acceptance v0.3-MVP** (`03-acceptance/`) | ✅ RATIFIED | 2026-06-08 |
| **Architecture v0.3-MVP** (`02-prd/`) | ✅ RATIFIED | 2026-06-08 |
| **Post-Launch Roadmap v0.1** (`04-roadmap/`) | ✅ RATIFIED | 2026-06-08 |
| **MVP Release Index v0.1** (`05-release/`) — #13 | ✅ RATIFIED | 2026-06-08 |
| **Build Workplan v0.3-MVP** (`05-release/`) — #13 | ✅ RATIFIED | 2026-06-08 |

> **The full launch-MVP spec is ratified and coherent.** All 9 PRDs + 9 acceptance docs + the Architecture (#10) + the roadmap (#12) + the two #13 release artefacts (the Release Index + the Build Workplan) are drafted, ratified, and coherent; the §7 rows above are the authoritative ratification log (it wins on any interim drift).
>
> **Maintenance:** when an artefact ratifies, add/flip its row here (and add any net-new MVP-DEC the session produced). Keep rows to one line + a pointer — this stays an index.

---

## §8 Cross-references

- **Method / dials** — `00-method/MVP_Restructure_Plan_v0.1.md`, `Dials_Worksheet_v0.1.md`, `Dials_Grounding_v1.1_reference.md`, `Phase_D_Kickoff_Prompt.md`.
- **Triage** — `01-triage/Module_X_CutSheet_v0.1.md` (×8), `Phase_C_Reconciliation_v0.1.md`.
- **PRDs / acceptance** — `02-prd/`, `03-acceptance/`.
- **Roadmap (the deferred set + seams)** — `04-roadmap/Post_Launch_Roadmap_v0.1.md`.
- **Greenfield frozen register (bridged, never extended)** — `greenfield/04-decisions/decisions.md` (DEC-001..196).

---

*End of Launch-MVP Decisions Register v0.1 — a **thin index** (one row per decision → its authoritative doc), established 2026-06-07. Reuses the existing handles (GOV / P / D-dials / module-Q / Phase-C A–N·R1–R4·N1–N3) + a small MVP-DEC stream; bridges but never extends greenfield's frozen DEC-001..196. Living through Phase D; frozen into the Phase E handoff.*
