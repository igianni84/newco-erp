# NewCo ERP — Module C (Fulfilment / Shipping / Late Binding / Returns + Replacement / Cellar Render) Acceptance Criteria — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP acceptance contract for Module C; re-cut from the v0.1 DRAFT)
- **Date**: 2026-06-08
- **Status**: **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E. The acceptance delta is **bounded — broad in *touch* (the four dials + the R3 reconcile span most AC buckets) but *annotations + feature-deferrals, not floor removals*** (three of the four SIMPLIFYs are config / Logilize-execution-layer / already-operator-driven postures, not floor cuts). Module C is **KEPT WHOLE on the ship→cellar floor + 4 SIMPLIFYs (D3/D13/D14/D17) + D15 KEEP-minimal + R3 + naming cascade**: this doc (a) **annotates the four SIMPLIFY arms** (D3 white-glove + Tier-1-footprint; D13 bottle-side optimisation; D14 FSM automation; D17 ETA-precision + granular-storage) **deferred-with-feature / manual-first / basic — the floor criteria on each stand UNCHANGED**; (b) **confirms R3** (the WMS criteria already carry the DEC-188 4-stream framing — the AC was *ahead* of the v1.1 PRD; the re-cut verifies the PRD now matches); (c) applies the **naming cascade** to the catalog-identity criteria; (d) **annotates the `is_gift` criteria not-exercised-at-launch** (D5); (e) re-anchors to the v0.3-MVP PRD; (f) adds a small **§6.11 MVP re-baseline** section. **No criterion in launch scope is removed; all floor criteria stand unchanged.**
- **Owner**: Paolo (product sign-off authority)
- **Companion spec**: [`../02-prd/Module_C_PRD_v0.3-MVP.md`](../02-prd/Module_C_PRD_v0.3-MVP.md) — the source of truth this document validates against. The PRD says *what to build*; this document says *what passes*. Together they are the dev-team's complete brief for the launch-MVP Module C.
- **Predecessor (re-cut from)**: [`../../reference/v1.1/01-prd/Module_C_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_C_Acceptance_v0.1.md) — the v1.1 acceptance template (**DRAFT 2026-05-15; 213 criteria; 93.0% AUTO / 6.1% MIXED / 0.9% HUMAN; Packet verdict APPROVE-with-two-confirmations — NOT yet Paolo-validated**, like Module D + Module S + Module B). `greenfield/` is frozen (plan R4); this is a derivative under `mvp/`. **The MVP re-cut + the original validation land together** at Paolo's batch ratification.
- **Audience** (three concurrent uses): **Paolo** at module-delivery sign-off (verdict report + spot-checks + the two HUMAN sessions — the J-30 end-to-end demo + the J-31 operator-handoff coherence review, which reflect Module C's cross-module density); **dev team** during build (the definition of done, read alongside the PRD from day one); **AI coding agents** during code generation (AUTO criteria as fitness functions in the build loop).
- **Purpose**: the demonstrable behaviours that, taken together, constitute "Module C is delivered as specified per v0.3-MVP." Each criterion is traceable to a PRD anchor (INV-C-NN / BR / event / FSM transition / DEC / §) and tagged AUTO / MIXED / HUMAN.
- **Methodology DECs binding this document**: DEC-072 (no-accounting-policy claims — Module C records the operational event; Module E records the financial event; Xero decides GL), DEC-073 (product-spec layer; criteria are business-behaviour, not tech-implementation — incl. the Logilize/carrier API mechanics, the pick-algorithm code, the customs-engine internals, the Cellar render surface), DEC-074 (self-contained; anchors restated inline), **Phase C R3 (the DEC-188 4-fulfilment-stream contract — Module C owns it) + item J (the NFT-burn rides B's D12 decouple — C dispatches regardless) + item K (the in-transit redemption-block FLOOR + basic display)**, DEC-181 (sanctions/Hold uniformity), DEC-184 (Returns/Replacement FSM), `feedback_prd_rr_approval` (operator approval-tier policy admin-configurable — out of scope).
- **What this document is NOT**: engineering Definition of Done (coverage thresholds, performance budgets, retry/idempotency mechanics, schema design, the Logilize/carrier/Avalanche API contract literals); UI / UX acceptance (the Cellar render layout, the Admin Panel form layouts — discrepancy queue / white-glove ticket / Returns operator screens / handover-confirmation / reverse-shipment screens, the Consumer Portal screens); operational R&R / approval-tier *policy* (admin-configurable per `feedback_prd_rr_approval`); Phase 2+ deferrals (the three D3 automation engines, the bottle-side Logilize optimisation, the Returns FSM automation, full reverse-inbound mechanics, multi-warehouse, drop-ship, sub-warehouse display, cellar UX, producer-override late-binding); cross-module behaviours owned by other modules (the Module S Voucher FSM, INV2 composition mechanics, the Module B NFT-burn on-chain semantics + Bottle Page rendering, the Module D `InboundEventPhysicallyAccepted` triggering, the Module A pool mechanics, the Module K Hold/sanctions determination, Xero GL).

---

## §0 What changed from v0.1 (the re-cut delta)

Module C is **KEPT WHOLE on the "ship → cellar" half of the core loop**, with **four dials landing in-module (D3 geography-hybrid · D13 late-binding pick · D14 returns/replacement · D17 cellar render) + D15 recall (KEEP-minimal) + R3 + naming cascade**. The acceptance delta is **bounded — broad in *touch* but annotations + feature-deferrals, not floor removals** (three of the four SIMPLIFYs are config / Logilize-execution-layer / already-operator-driven postures, §0.1–§0.4):

1. **D3 geography hybrid (SIMPLIFY — §0.1):** the destination-eligibility criteria (**AC-C-J-15** Tier-1, **AC-C-J-16** white-glove fallback, **AC-C-J-17** OFAC at all destinations, **AC-C-J-18** DDP/DAP, **AC-C-BR-DEST-1..5**) **stand** — the two-tier model + white-glove + OFAC are KEPT. The **Tier-1 pre-cleared-list footprint** is annotated a launch-configuration scope (narrowed to low-friction at launch); the US-state-matrix (DEC-148), DDP/DAP-country-by-country (DEC-149), and excise-rate-automation (DEC-150) criteria are marked **deferred-with-feature** (already-deferred). The **INV2-tax-composition criteria (AC-C-BR-FEE-*, AC-C-BR-EXCISE-*) stand — FLOOR.**
2. **D13 late-binding (SIMPLIFY — §0.2):** the two-surface algorithm criteria (**AC-C-J-3..7**, **AC-C-BR-LB-1..6**) **stand** — voucher-side FIFO + the `manual_review` tiebreak + the StockPosition no-oversell read (**AC-C-BR-LB-4**) + the 7-step chain are KEPT. The **bottle-side warehouse-efficiency optimisation** arm (**AC-C-J-6** Surface 2) is annotated **deferred-with-feature** (Logilize-internal; verified when Logilize route-optimisation lands). Producer-override (**AC-C-BR-LB-2**) already deferred.
3. **D14 returns/replacement (SIMPLIFY — §0.3):** the Returns/Replacement FSM criteria (**AC-C-FSM-16..24**, **AC-C-EVT-19..26**, **AC-C-J-21**) **stand** — the FSM + the 4-event chain + the original-voucher-preserved discipline (**INV-C-08**) + the no-cash-refund rule (**INV-C-07**) + the supervisor-override-refund discriminator (**AC-C-FSM-23**) + the NonRevenueCost/OC emissions (**AC-C-EVT-25 / AC-C-XM-32**) are KEPT. The **FSM automation** is annotated **manual-first operator handling at launch** (verified end-to-end via the Admin Panel; the J-31 operator-handoff coherence session covers it).
4. **D17 cellar render (SIMPLIFY — §0.4):** the six-module read criteria (**AC-C-J-27**, **AC-C-XM-33**, **AC-C-BR-CELL-1/2**) **stand** — the read contract + anonymisation (**AC-C-BR-CELL-2**) are KEPT. The **in-transit ETA precision** (**AC-C-J-24 / AC-C-BR-INT-2**) + **granular storage** (**AC-C-BR-STORE-1**) arms are annotated **deferred-with-feature** (admin-estimate ETA + warehouse-level storage at launch). The **B-summary data-source switch (AC-C-XM-28)** **stands** (ratified — the R3 cascade).
5. **D15 recall (KEEP-minimal):** the recall criteria (**AC-C-J-25/J-26**, **AC-C-EVT-27**, **AC-C-BR-RECALL-1..5**, **AC-C-XM-14**) **stand** — minimal/manual; the full reverse-inbound mechanics (**AC-C-BR-RECALL-4**) already-deferred (OQ-18/DEC-155).
6. **R3 — the 5→4-stream reconcile (confirmed):** the WMS criteria (**AC-C-BR-WMS-1**, **AC-C-XM-1/3**, **AC-C-EVT-32**) **already carry the DEC-188 4-stream framing** (the Packet §3 confirms the AC reflects the 4-stream contract — **the AC is *ahead* of the v1.1 PRD here**); the MVP PRD §15.2/§15.4 stale 5-stream text is reconciled to match (PRD §0.6). **The AC needs no change for R3 — it already anticipated the fix; the re-cut verifies the PRD now matches the AC.** See AC-C-MVP-2.
7. **D5 gift idle:** the gift criteria (**AC-C-J-11**, **AC-C-BR-MODE-4/5**, **INV-C-10**) are annotated **not-exercised-at-launch** (gifting deferred; the `is_gift` attribute + the recipient read retained as the seam). See AC-C-MVP-3.
8. **Naming cascade applied to the catalog-identity criteria** (Phase C item A): `Bottle Reference → Product Reference`, `Wine Variant → Product Variant` in the late-binding BR reads (**AC-C-J-3/J-6/J-7**, **AC-C-BR-LB-1/3/4**), excise classification (**AC-C-J-19**, **AC-C-XM-20**), and the cellar render (**AC-C-J-27**, **AC-C-XM-19/21**, **AC-C-BR-CELL-1**). **Module C's own physical-unit entity/event names** (`Shipping Order`, `BottlePicked`, `ShipmentDispatched`, `BottleDelivered`, …) are **retained as wine-display naming** (the §18 carve-out). Wine-display aliases ("Bottle Reference / BR") retained. **Behaviour is identical.** See AC-C-MVP-4.
9. **Re-anchored to the v0.3-MVP PRD.** PRD §-numbers now refer to [`../02-prd/Module_C_PRD_v0.3-MVP.md`](../02-prd/Module_C_PRD_v0.3-MVP.md). **Module C had no structural entity insertion (cut-heavy in breadth but four SIMPLIFYs + a RECONCILE, not a re-model), so the body §-anchors (§1–§16) are unchanged from v1.1** — every existing AC anchor remains valid. Only §0 was prepended; §N adds the MVP re-baseline trace.
10. **New section §6.11 — MVP re-baseline criteria** (6 criteria, AC-C-MVP-1..6), verifying the floor parity, the R3 4-stream contract, the gift idle, the naming cascade, the D-dial deferred-with-feature seams, and the NFT-burn-rides-B's-decouple posture.
11. **Floor criteria re-affirmed UNCHANGED:** the SO invariants (**AC-C-BR-INV-01..10**), the shipment gate (**AC-C-J-2**, **AC-C-FSM-1**, **AC-C-XM-11**, INV-C-02), the late-binding voucher→bottle bind (**AC-C-J-3/J-6**, **AC-C-BR-LB-1**), the no-oversell-at-pick (**AC-C-BR-INV-01**, **AC-C-BR-LB-4** StockPosition read, **AC-C-XM-27**), the OFAC/eligibility surface (**AC-C-J-17**, **AC-C-FSM-12/13**, **AC-C-BR-MODE-3** sanctions/Hold uniformity), the dispatch → INV2 + burn chain (**AC-C-XM-6**, **AC-C-EVT-4**), the in-transit redemption-block (**AC-C-J-24**, **AC-C-BR-INT-3**, INV-C-02/03), the INV2 tax composition (**AC-C-BR-FEE-1/2**, **AC-C-BR-EXCISE-1/2**, **AC-C-J-19**) all stand as-is. **Nothing in launch scope removed.**

### §0.1 D3 geography hybrid (SIMPLIFY — the headline; the hybrid IS v1.1's design; OFAC + INV2-tax FLOOR)

Per the cut-sheet Q1 (ratified: SIMPLIFY hybrid — narrow Tier-1 to low-friction; complex via the built white-glove flow; OFAC + INV2 tax-correctness FLOOR; the three automation engines already-deferred). The two-tier model **is** the hybrid mechanism + the seam, so the acceptance delta is **annotations, not removals:**

- **Stand UNCHANGED — the hybrid mechanism + the FLOOR:** **AC-C-J-15** (Tier-1 automated path + dual-point validation), **AC-C-J-16** (Tier-2 white-glove fallback — the "send shipping request" CTA + Customer Care ticket + manual quote — MIXED), **AC-C-J-17** (OFAC at all destinations — **FLOOR**), **AC-C-BR-DEST-1/2** (two-tier + dual-point validation), **AC-C-BR-DEST-3** (US-state simple-at-launch + OFAC), **AC-C-BR-DEST-4/5** (DDP/DAP + the Module-S T&C boundary). **The INV2-tax-composition criteria stand — FLOOR:** **AC-C-BR-FEE-1/2** (INV1 excludes shipping; INV2 = shipping + destination VAT + excise + storage roll-in), **AC-C-BR-EXCISE-1/2** (bonded-warehouse release-for-consumption + the operator-configurable rate matrix), **AC-C-J-18/J-19** (DDP/DAP INV2 split + the `ExciseCalculated` flow).
- **Annotated launch-configuration scope:** the **Tier-1 pre-cleared destination footprint** (**AC-C-J-15 / AC-C-BR-DEST-1**) is narrowed to low-friction destinations (EU/UK/CH) at launch — a configuration of the operator-managed eligible-destinations list, not a criterion removal. **The launch destination footprint is Paolo's owned launch-config call.**
- **Annotated deferred-with-feature (already-deferred — the three automation engines):** the auto-generated US-state rule-matrix expansion (**AC-C-BR-DEST-3** expansion arm; DEC-148), the DDP/DAP country-by-country expansion (**AC-C-BR-DEST-4** expansion arm; DEC-149), the excise rate-matrix automation (**AC-C-BR-EXCISE-2** automation arm; DEC-150) → verified when the automation lands; the operator-managed lists/matrix are the launch posture.
- **The honesty note (the cut-sheet's explicit ask):** even the white-glove manual flow computes excise + customs + DDP/DAP + a correct quote per complex shipment for INV2 — **the INV2 tax-correctness criteria are FLOOR, verified in the white-glove path too** (AC-C-J-13 manual-quote + AC-C-J-19 excise both fire in the white-glove case). **Be careful the manual-quote flow does not drop the INV2 tax floor.**

### §0.2 D13 late-binding pick (SIMPLIFY — the bind FLOOR; the bottle-side optimisation deferred)

Per the cut-sheet Q2 (ratified: defer the bottle-side Logilize warehouse-efficiency optimisation; keep the two-surface structure + voucher-side FIFO + `manual_review` + the 7-step chain + the StockPosition no-oversell read; the bind is FLOOR):

- **Stand UNCHANGED — FLOOR:** **AC-C-J-3** (voucher-side FIFO-by-expiry), **AC-C-J-4** (issuance-timestamp tiebreak), **AC-C-J-5** (the tertiary `manual_review` tiebreak + `ShippingOrderManualReviewRequired`), **AC-C-J-7** (the allocation-pool boundary — INV-C-01), **AC-C-BR-LB-1** (the late-binding bind), **AC-C-BR-LB-3** (the effective-unbreakable read), **AC-C-BR-LB-4** (the StockPosition no-oversell-at-pick read — **FLOOR**; the named B↔C contract), **AC-C-BR-LB-5** (the NS path), the 7-step chain (**AC-C-FSM-1..4**).
- **Annotated deferred-with-feature:** **AC-C-J-6** (the bottle-side Surface 2 warehouse-efficiency optimisation) — **at launch Logilize picks simply (any available bottle from the correct pool); the optimisation is Logilize-internal and deferred; the two-surface contract structure [the Stream 1 "late-binding strategy" field] is the seam.** Verified when Logilize route-optimisation lands. Module C's pick-instruction contract is unchanged.
- **Already-deferred:** **AC-C-BR-LB-2** (producer-override late-binding — Phase 2+ + explainer-pending; carry).

### §0.3 D14 returns/replacement (SIMPLIFY — the FSM + discipline KEPT; automation manual-first)

Per the cut-sheet Q3 (ratified: FSM *automation* → manual-first; keep the FSM + the 4-event chain + the original-voucher-preserved discipline + the no-cash-refund rule + the C-vs-S boundary + the NonRevenueCost/OC emissions). The FSM is already operator-driven by spec, so the acceptance delta is an **annotation, not a removal:**

- **Stand UNCHANGED — the FSM + discipline:** **AC-C-J-21** (the end-to-end Returns workflow), **AC-C-FSM-16..22** (the FSM transitions REPORTED → … → CLOSED + REJECTED/WITHDRAWN off-ramps), **AC-C-FSM-23** (the supervisor-override-refund closure-path discriminator), **AC-C-FSM-24** (the original-voucher-preserved discipline — INV-C-08), **AC-C-EVT-19..26** (the 4-event chain + the FSM transition events), **AC-C-BR-RET-1..8** (the C-vs-S boundary; the Module-A pool interaction; the unfulfillable path; the Module-E NonRevenueCost), **AC-C-EVT-25 / AC-C-XM-32** (the NonRevenueCost wrapper + OC-reversal — the Module E seam), **INV-C-07** (no cash refund — AC-C-BR-INV-07).
- **Annotated manual-first at launch:** the **FSM automation** (auto-transitions / auto-routing / auto-notification) is deferred → operators run returns/replacement end-to-end via the Admin Panel; verified end-to-end via the Admin Panel (the **AC-C-J-31** operator-handoff coherence session covers the Returns FSM Customer Care operator surface). The FSM + events are the seam.
- **Already-deferred:** voucher-substitution full automation (**AC-C-BR-RET-5** substitution arm; DEC-104 — manual at launch); returned-unit full reverse-inbound mechanics (**AC-C-BR-RET-6**; OQ-18/DEC-155).

### §0.4 D17 cellar render (SIMPLIFY — basic; the six-module read + anonymisation KEPT; the B-summary switch)

Per the cut-sheet Q4 (ratified: basic view; defer in-transit ETA precision + granular storage; keep the six-module read + anonymisation; honour the B-summary data-source switch):

- **Stand UNCHANGED — the seam + the floor-adjacent discipline:** **AC-C-J-27** (the six-module read), **AC-C-XM-33** (the cellar render composition), **AC-C-BR-CELL-1** (the six-module read contract), **AC-C-BR-CELL-2** (anonymisation — DEC-024; **floor-adjacent**), **AC-C-XM-26/28** (the Bottle Page link + the **B-summary storage-location switch** — the R3/DEC-188 cascade, ratified).
- **Annotated deferred-with-feature (the D17 defer):** the **in-transit ETA precision** (**AC-C-J-24 / AC-C-BR-INT-2** — admin-configurable estimate at launch, not carrier-ETA-precision integration), the **granular storage** (**AC-C-BR-STORE-1 / AC-C-J-28** — warehouse-level only). The basic view = the six-module read with warehouse-level storage + admin-estimate ETA + the standard PICKED/DISPATCHED/DELIVERED annotations.
- **Already-deferred:** the Cellar UX layout (**AC-C-BR-CELL-3**; DEC-154); sub-warehouse granular display (**AC-C-BR-STORE-1** sub-warehouse arm; DEC-153); multi-warehouse (**AC-C-BR-STORE-2**; OQ-16).

---

## §1 How to use this document

### §1.1 Verification tags

- **AUTO** — an AI agent / automated harness reads the criterion + spec anchor + running system (event stream, entity state, API responses, audit trail) and produces a PASS/FAIL verdict with evidence. Paolo reviews the verdict batch.
- **MIXED** — AI prepares the evidence (the white-glove ticket trace; the in-transit Cellar render across ETA sources; the discrepancy-queue trace; the Returns FSM operator-surface trace; the dual cellar-vs-Bottle-Page anonymisation trace); Paolo confirms a judgment call (display copy quality, operator-workflow coherence).
- **HUMAN** — Paolo executes personally — the **AC-C-J-30** end-to-end demo session + the **AC-C-J-31** operator-handoff coherence review (Module C's high cross-module density warrants the discrete coherence review).

**Distribution for Module C v0.3-MVP: ~219 total criteria** — the v0.1 213 (93.0% AUTO / 6.1% MIXED / 0.9% HUMAN) **+ 6 MVP re-baseline criteria (AC-C-MVP-1..6; 4 AUTO / 2 MIXED).** The D-dial SIMPLIFY arms carry inline "deferred-with-feature" / "manual-first at launch" / "launch-configuration scope" notes; the gift criteria carry an inline "not-exercised-at-launch (D5)" note; the WMS criteria already carry the 4-stream framing (R3). **No criterion in launch scope is removed.** Paolo's hands-on load: the MIXED items + the J-30 end-to-end demo + the J-31 operator-handoff coherence review.

### §1.2 Build-time usage + §1.3 Sign-off cadence

Consulted from day one, not only at handover: the dev reads the PRD + this doc together; AUTO criteria wire into CI as scaffolding lands (the AUTO PASS rate is a continuous completion signal); AI coding agents treat AUTO criteria as fitness functions; MIXED/HUMAN items are scheduled. **Cross-module discipline matters particularly for Module C — the highest cross-module-density module** — so the §5 (events) + §6 (cross-module) AUTO criteria carry disproportionate weight and wire into CI alongside the Module S / D / A / B / K / E fixtures. **Deferred-with-feature criteria stay authored + CI-wired behind the deferred feature** (enabled when the automation / optimisation lands). Each criterion lands OPEN → DEMOED → ACCEPTED; Module C is **delivered** when every §2–§6 launch-scope criterion is ACCEPTED. Sign-off log at §8.

### §1.4 Anchors + §1.5 Format conventions (carried; the v0.3-MVP additions)

PRD §-numbers refer to [`../02-prd/Module_C_PRD_v0.3-MVP.md`](../02-prd/Module_C_PRD_v0.3-MVP.md). INV-C-NN refers to its §2.5; event names to its §15; SO FSM states to its §2.2; the Returns/Replacement FSM to its §10.2 / §15.1. **(Body §-anchors are unchanged from v1.1 — §0 item 9.)** Conventions: (1) §4 BR statements are verbatim from PRD §2.5 + §3 + §4 (per-domain prose) *(for v0.3-MVP the verbatim statements carry the naming cascade on the catalog-identity rules)*; (2) §4 BR→AC pointer rows preserve traceability; (3) §6 cross-module criteria verify the Module-C-side surface only; (4) AUTO criteria dependent on consumer modules carry "verified when X lands" notes; **(5) (NEW, v0.3-MVP) MVP re-baseline criteria live in §6.11 (AC-C-MVP-*); the D-dial SIMPLIFY arms carry an inline "deferred-with-feature" / "manual-first at launch" / "launch-config scope" note; the gift criteria carry an inline "not-exercised-at-launch (D5)" note; the floor criteria carry an inline "(FLOOR)" marker; the NFT-burn-observation criteria carry an inline "rides B's D12 decouple — C dispatches regardless" note.**

---

## §2 Canonical journeys — end-to-end fulfilment flows

Twelve buckets exercised end-to-end (the SO creation; the late-binding two-surface selection; the three shipping modes; the gift sub-flag *(idles — D5)*; carrier auto + manual quote; destination two-tier + white-glove; excise/customs; damages; Returns + Replacement *(FSM manual-first)*; in-transit display + redemption-block *(FLOOR; basic ETA)*; recall *(minimal/manual)*; the six-module cellar render *(basic)*).

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-J-1** | *(FLOOR)* Module S emits `VoucherRedemptionRequested`; Module C consumes it and creates exactly one SO in `draft` per redemption (Voucher ref(s), Customer + Profile ref, delivery Address from Module K, `dispatch_mode = direct`, `is_gift = false`); `ShippingOrderCreated` fires. | §2.1 + §3.5 step 1–2 + §15.1; INV-C-04 | AUTO |
| **AC-C-J-2** | *(FLOOR)* SO `draft → planned` advances only when ALL of: no active Module K Hold; sanctions/OFAC `passed`; `InboundEventPhysicallyAccepted` fired (shipment gate, DEC-081/143); destination eligible OR white-glove approval; `compliance_hold` clear. `ShippingOrderPlanned` fires. | §2.2 + §2.3 + §11.3; INV-C-02/03; DEC-143/181 | AUTO — five parametrised gate-failure tests; clear each; assert advance |
| **AC-C-J-3** | *(FLOOR; naming cascade)* Late-binding voucher-side FIFO by Voucher expiry within the same Allocation pool referencing identical **Product References**; the oldest-expiry Voucher binds first. | §3.2 Surface 1; DEC-137 | AUTO |
| **AC-C-J-4** | Voucher-side tiebreak: identical expiry → earliest-issuance-timestamp wins. | §3.2 Surface 1 tiebreak; DEC-137 | AUTO |
| **AC-C-J-5** | Tertiary tiebreak: identical expiry + identical issuance timestamps → SO into `picking` with `manual_review = true`; `ShippingOrderManualReviewRequired` emitted (SO ref + candidate set + pool ref); operator selects via Admin Panel; flag clears; SO advances. | §2.3 + §3.2 tertiary + §15.1; DEC-137 | AUTO |
| **AC-C-J-6** | *(FLOOR for the bind; **deferred-with-feature** for the optimisation — D13)* Late-binding bottle-side selection: **at launch Logilize picks simply (any available bottle from the correct Allocation pool)**; the bound bottle's identity is captured at `BottlePicked`. **The warehouse-efficiency optimisation [Surface 2] is Logilize-internal and deferred; the Stream 1 "late-binding strategy" field is the seam** (verified when Logilize route-optimisation lands). | §3.2 Surface 2; DEC-137 | AUTO — assert Stream 1 names the pool + strategy field; assert the pick is from the correct pool; assert `BottlePicked` captures the identity. *(Optimisation arm deferred-with-feature.)* |
| **AC-C-J-7** | *(FLOOR; naming cascade)* Allocation-pool boundary: the bottle MUST come from the same Allocation pool as the Voucher; cross-pool picking prohibited regardless of **Product Reference** identity match. | §3.2 + INV-C-01; DEC-099/137 | AUTO |
| **AC-C-J-8** | Direct mode: full `draft → planned → picking → shipped → completed`; carrier pickup from Vinlock + Stream 4 ETA tracking; destination = Customer's Address (or recipient if `is_gift` — idles). | §5.1 direct + §5.3 | AUTO |
| **AC-C-J-9** | Pickup mode: `picking → shipped` on operator-recorded handover at Vinlock via Admin Panel (NOT a Logilize Stream 3 event); `ShipmentDispatched` at handover; `incoterms` N/A; fee zero/minimal. | §5.1 pickup + §5.3 | AUTO |
| **AC-C-J-10** | Event mode: bottles ship to the recorded event-venue address; otherwise like direct (Stream 3 + Stream 4). | §5.1 event + §5.3 | AUTO |
| **AC-C-J-11** | *(not-exercised-at-launch — D5)* Gift sub-flag (`is_gift = true`) on direct mode: uses the Module S gift-recipient address; `ShipmentDispatched` carries `is_gift = true`; `is_gift` on pickup/event is a validation error (INV-C-10). **At launch gifting is deferred (the `is_gift` sub-flag idles); the attribute + recipient read + INV-C-10 are retained as the seam.** | §5.2 + INV-C-10; DEC-116/144 | AUTO — *the validation (INV-C-10) verifiable; the gift sub-flow not-exercised-at-launch* |
| **AC-C-J-12** | Carrier auto-quote: operator-configurable rule set at Checkout; `ShippingFeeQuoted.quote_origin = auto`; carried to `ShipmentDispatched`. | §6.1 auto + §6.3; DEC-145 | AUTO |
| **AC-C-J-13** | *(the D3 white-glove enabler)* Carrier manual-quote: operator enters fee + carrier + transit estimate; `ShippingFeeQuoted.quote_origin = manual`; used for white-glove + complex shipments. | §6.1 manual; DEC-145/147 | AUTO |
| **AC-C-J-14** | *(FLOOR — the actual-cost guarantee)* Re-quote at picking: Module C re-checks rates at `planned → picking`; a material change fires a fresh `ShippingFeeQuoted`; **INV2 ALWAYS uses the `ShipmentDispatched` actual cost, not the Checkout quote.** | §6.2 + §6.4; DEC-146 | AUTO |
| **AC-C-J-15** | *(D3 — launch-config scope)* Destination eligibility Tier 1: validated at Checkout (Module S) + SO creation (Module C re-validates); eligible → standard progression. **The Tier-1 pre-cleared list is narrowed to low-friction destinations at launch [config].** | §7.1 tier 1; DEC-147/041 | AUTO |
| **AC-C-J-16** | *(D3 — the hybrid mechanism; KEPT)* Destination eligibility Tier 2 white-glove fallback: non-eligible → "send shipping request" CTA (not a hard block) → Customer Care ticket → on approval, manual quote (DEC-145) + SO proceeds; on denial, continued storage OR pre-shipment cancellation (DEC-108). | §7.1 tier 2 + §2.3; DEC-147 | MIXED |
| **AC-C-J-17** | *(FLOOR)* OFAC screening applies at ALL destinations regardless of tier; US-state pre-cleared on auto, harder states via white-glove. | §7.2; DEC-148/041 | AUTO |
| **AC-C-J-18** | DDP/DAP non-EU simple model: `ShipmentDispatched.incoterms ∈ {DDP, DAP}`; INV2 has no destination-VAT line for non-EU; EU destinations carry destination VAT under MPV. | §7.3; DEC-149/056/045 | AUTO |
| **AC-C-J-19** | *(FLOOR — tax; naming cascade)* Excise flow: at `planned → picking`, Module C reads destination + **Product** alcohol classification (Module 0) + the rate matrix → emits `ExciseCalculated`; Module S consumes for INV2; Logilize executes customs docs; SO advances on customs-documentation-completed. **Fires in the white-glove path too.** | §8.1 + §8.2; DEC-150/072 | AUTO |
| **AC-C-J-20** | Pre-shipment cancellation (`draft/planned → cancelled`): DEC-108 14-day window valid only pre-`shipped`; Voucher returns to ISSUED via Module S; `ShippingOrderCancelled` fires; post-`shipped` → replacement (DEC-138). | §2.2 + INV-C-09 + §15.1 | AUTO |
| **AC-C-J-21** | *(FSM + discipline FLOOR; automation manual-first — D14)* Returns + replacement (DEC-138 + DEC-184 FSM): `REPORTED → INVESTIGATED → APPROVED → REPLACEMENT_ISSUED → CLOSED`; original Voucher state preserved (no regression, no new Voucher, no new INV2); replacement from the same pool unsold sub-pool; pool-exhausted → DEC-104 substitution. **At launch operators run the FSM end-to-end via the Admin Panel; the FSM automation is the deferred seam.** | §10.1/§10.2/§10.4; DEC-138/184; INV-C-08 | AUTO — *FSM + discipline verified; automation manual-first* |
| **AC-C-J-22** | Damages event ownership (DEC-151): Module-C-owned transit events fire for transit-side issues; Module-B-owned `BottleBreakageInCustody` for in-custody; §9.3 triage routes correctly. | §9.1 + §9.3; DEC-151/132 | AUTO |
| **AC-C-J-23** | Transit-loss: `BottleLossInTransit` → `InsuranceClaimOpened` (`insurance_pool ∈ {carrier, newco_supplementary}`) → replacement if stock → `InsuranceClaimResolved`; SO → `lost`. | §9.1 + §2.2; DEC-151/167/048 | AUTO |
| **AC-C-J-24** | *(redemption-block FLOOR; ETA precision deferred-with-feature — D17/item K)* In-transit display (DEC-143): "in transit; ETA X" on cellar + Voucher detail when `InboundEventPhysicallyAccepted` not yet fired; **the Voucher CANNOT be redeemed** (SO held in `draft` with `compliance_hold` shipment-gate sub-type); the display clears on physical receipt. **ETA X is a basic admin-configurable estimate at launch; carrier-ETA-precision deferred.** | §11.1/§11.2/§11.3 + §14.1 read 3; DEC-143 | MIXED — *redemption-block FLOOR verified; ETA-precision arm deferred-with-feature* |
| **AC-C-J-25** | *(D15 — minimal/manual)* Producer recall: Module D `ReverseInboundEventRecorded` → operator coordinates outside the system → operator initiates the reverse-shipment via Admin Panel → Module C emits `ReverseShipmentDispatched` (Allocation ref, qty/serial refs, producer address, actor, timestamp); no automated reverse-carrier API. | §12.1 + §12.3; DEC-152/090 | MIXED |
| **AC-C-J-26** | *(FLOOR-adjacent)* Recall scope (DEC-117): reverse-pick draws from the unsold sub-pool only (`Allocation.qty − issued`) at instruction-generation time; ISSUED Voucher-bound bottles immune regardless of the producer's stated scope; recalculates on a mid-flight new `VoucherIssued`. | §12.2 + INV-C-06; DEC-117 | AUTO |
| **AC-C-J-27** | *(D17 basic; six-module read KEPT; naming cascade; B-summary switch)* Cellar render six-module read (DEC-154): Module S (Voucher state + storage-fee state) + Module C (physical state for in-flight + in-transit) + Module B (**storage-location summary via B-summary, DEC-188** + Bottle Page link or "non-serialized / no Bottle Page") + Module 0 (**Product Reference** catalog identity). | §14.1; DEC-154/126/188 | MIXED |
| **AC-C-J-28** | *(D17 — warehouse-level; granular deferred-with-feature)* Storage-location granularity (DEC-153): cellar displays warehouse-level only — "Stored at NewCo Vinlock cellar in France"; sub-warehouse granular remains Logilize-internal, not customer-facing. | §13.1 + §14.1 read 4; DEC-153 | MIXED |
| **AC-C-J-29** | *(the universal-fallback path)* Non-serialized late binding (DEC-133/186): Stream 1 / `BottlePicked` / `ShipmentDispatched` name Allocation + InboundBatch + qty (no serial, no NFT identity); Module B does NOT fire `BottleNFTBurned`; cellar returns "non-serialized / no Bottle Page". | §3.6 + §4.2 + §14.1 read 4; DEC-133/186 | AUTO |
| **AC-C-J-30** | HUMAN end-to-end demo: SO creation; automated late-binding selection (FIFO + simple pick); the `manual_review` tertiary-tiebreak Admin Panel resolution; the three modes; a gift shipment *(idles — demoed as the retained seam / validation)*; auto + manual quote; a white-glove non-eligible-destination case; a Returns + Replacement workflow *(manual-first via Admin Panel)*; an in-transit cellar render *(basic ETA)*; a manual reverse-logistics flow; the composed cellar render. | §1–§14 (full surface) | HUMAN — ~90–120 min with dev + logistics ops + Customer Care |
| **AC-C-J-31** | HUMAN operator-handoff coherence review: the operator-mediated surfaces — the shared discrepancy queue (DEC-141, with Module B); the white-glove Customer Care ticket workflow (DEC-147); **the Returns FSM Customer Care operator surface (DEC-184 — the D14 manual-first surface)**; pickup-mode handover-confirmation; manual reverse-logistics initiation (DEC-152). | §4.4 + §7.1 + §10.2 + §5.1 + §12.1 | HUMAN — ~45–60 min; cross-surface handoff coherence + Admin Panel cognitive load |

---

## §3 State machine round-trips — Shipping Order FSM + Returns/Replacement FSM + sub-state flags

### §3.1 Shipping Order primary states (DEC-139)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-FSM-1** | *(FLOOR)* `draft → planned` fires `ShippingOrderPlanned`; gated by no active Hold; sanctions/OFAC `passed`; `InboundEventPhysicallyAccepted` fired (shipment gate); destination eligible OR white-glove approval; all `compliance_hold` triggers cleared. | §2.2 + §2.3; INV-C-02/03; DEC-181 | AUTO |
| **AC-C-FSM-2** | `planned → picking` fires `ShippingOrderPickingStarted`; gated by Logilize Stream 2 pick-confirmation; `BottlePicked` emits in the same chain (the bind). | §2.2 + §3.5 step 5–6 | AUTO |
| **AC-C-FSM-3** | `picking → shipped` fires `ShipmentDispatched`; gated by Logilize Stream 3 (direct/event) OR operator handover (pickup); payload = actual cost + `incoterms` + `dispatch_mode` + `quote_origin` + bound serial/NFT (serialized) or Allocation + InboundBatch + qty (non-serialized). | §2.2 + §4.2 Stream 3 + §5.3 | AUTO |
| **AC-C-FSM-4** | `shipped → completed` fires `ShippingOrderCompleted` + `BottleDelivered`; gated by Logilize Stream 4 (best-effort); pickup handover IS the delivery moment; Module S transitions Voucher → CONSUMED. | §2.2 + §4.2 Stream 4 + §5.3 | AUTO |
| **AC-C-FSM-5** | `draft → cancelled` (terminal): if `compliance_hold` cannot resolve; `ShippingOrderCancelled` fires; Voucher → ISSUED via Module S. | §2.2 | AUTO |
| **AC-C-FSM-6** | `planned → cancelled` (terminal): DEC-108 14-day pre-shipment window; `ShippingOrderCancelled` fires; Voucher → ISSUED. | §2.2 + INV-C-09; DEC-108 | AUTO |
| **AC-C-FSM-7** | `shipped → returned` (resolution): post-shipment per DEC-138; Returns FSM tracks; `ShippingOrderReturned` fires; original Voucher state preserved. | §2.2 + §10; DEC-138; INV-C-08 | AUTO |
| **AC-C-FSM-8** | `shipped → lost` (resolution): transit-loss per DEC-151; `ShippingOrderLost` + `BottleLossInTransit`; insurance + replacement chain. | §2.2 + §9.1; DEC-151 | AUTO |
| **AC-C-FSM-9** | No FSM bypass: every state-changing transition is event-emitting + audit-traceable; no soft-edit path mutates SO state without the corresponding `ShippingOrder*` event. | §2.2 + §15.1 | AUTO |
| **AC-C-FSM-10** | Terminal states (`cancelled`, `completed`, `returned`-after-CLOSED, `lost`-after-closure): no transition out at Module C scope. | §2.2 + §10.2 | AUTO |

### §3.2 Shipping Order sub-state flags

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-FSM-11** | *(FLOOR)* `compliance_hold` on `draft`: set when any eligibility check fails (active Hold; sanctions/OFAC non-passed; destination ineligibility without white-glove; shipment-gate not passed); SO does not advance to `planned` until ALL triggers clear (INV-C-03). | §2.3 + INV-C-03 | AUTO |
| **AC-C-FSM-12** | *(FLOOR)* Sanctions/Hold re-read at `planned` (DEC-181): the eligibility-check pass re-reads sanctions + Hold at the moment of transition; a Hold landing after creation but before `planned` blocks it. | §2.3; DEC-181 | AUTO |
| **AC-C-FSM-13** | *(FLOOR)* Sanctions/Hold re-read at shipment-request initiation (DEC-181): defence-in-depth at §2.3/§3.5 step 2; catches sanctions/Hold landing between Module S `VoucherRedemptionRequested` and SO admittance. | §2.3 + §3.5; DEC-181 | AUTO |
| **AC-C-FSM-14** | `manual_review` on `picking`: set on a Logilize-side pick discrepancy (serial/quantity/batch mismatch; breakage-at-pick) OR the voucher-side tertiary tiebreak; queue entry in the shared Admin Panel queue; `DiscrepancyResolutionRecorded` (Logilize-side) OR operator selection (voucher-side) clears it. | §2.3 + §4.4; DEC-137/141 | AUTO |
| **AC-C-FSM-15** | White-glove destination ineligibility is an SO-side soft-block via `compliance_hold = true` (NOT a new Voucher state); the Voucher FSM is unchanged; on Customer Care approval the flag clears. | §2.3 + §7.1; DEC-147/102 | AUTO |

### §3.3 Returns/Replacement entity FSM (DEC-184) **(manual-first at launch — D14; the FSM + events KEPT)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-FSM-16** | Returns FSM: `REPORTED → INVESTIGATED → APPROVED → REPLACEMENT_ISSUED → CLOSED` + `REJECTED`/`WITHDRAWN` off-ramps; each transition emits a §15.1 event. **At launch operators run it end-to-end via the Admin Panel (the automation is the deferred seam).** | §15.1 Returns FSM; DEC-184 | MIXED — *FSM verified; automation manual-first* |
| **AC-C-FSM-17** | `REPORTED → INVESTIGATED`: `ReturnInvestigationStarted`. | §15.1; DEC-184 | AUTO |
| **AC-C-FSM-18** | `INVESTIGATED → APPROVED`: `ReturnApproved`. | §15.1; DEC-184 | AUTO |
| **AC-C-FSM-19** | `INVESTIGATED → REJECTED` (terminal): `ReturnRejected`. | §15.1; DEC-184 | AUTO |
| **AC-C-FSM-20** | `REPORTED/INVESTIGATED → WITHDRAWN` (terminal): `ReturnWithdrawn`. | §15.1; DEC-184 | AUTO |
| **AC-C-FSM-21** | `APPROVED → REPLACEMENT_ISSUED`: `ReplacementShipmentIssued`; replacement from the same pool unsold sub-pool (INV-C-01); the Module E NonRevenueCost wrapper fires (DEC-167). | §10.2 + INV-C-01; DEC-184/167 | AUTO |
| **AC-C-FSM-22** | `REPLACEMENT_ISSUED → CLOSED`: `ReplacementShipmentDelivered`. | §10.2; DEC-184 | AUTO |
| **AC-C-FSM-23** | Supervisor-override-refund closure path: APPROVED → CLOSED with `closure_path = supervisor_override_refund`; no `ReplacementShipmentIssued`; no Module C NonRevenueCost wrapper; Module S transitions Voucher → VOIDED (§12.3). | §15.1 supervisor-override; DEC-184/108 | AUTO |
| **AC-C-FSM-24** | *(FLOOR — INV-C-08)* Returns FSM operates within the SO `returned` path; original Voucher state preserved throughout (no regression to ISSUED; no new Voucher; no new INV2). | §10.1/§10.4 + INV-C-08; DEC-138/102 | AUTO |

---

## §4 Business rule enforcement — invariants + rules + constraints

### §4.1 SO business invariants (INV-C-01..10)

| AC ID | BR statement (verbatim from §2.5) | Verification |
|---|---|---|
| **AC-C-BR-INV-01** | *(FLOOR — no-oversell-at-pick)* **INV-C-01 — Allocation-pool boundary**: the bottle (or NS batch qty) assigned at `BottlePicked` MUST belong to the same Allocation pool as the Voucher; cross-pool picking prohibited regardless of **Product Reference** identity. | AUTO — covered by AC-C-J-7 |
| **AC-C-BR-INV-02** | *(FLOOR)* **INV-C-02 — Shipment gate before `planned`**: the SO MUST NOT advance `draft → planned` unless `InboundEventPhysicallyAccepted` has fired for the relevant Allocation qty; in-transit Allocations hold the SO in `draft` with `compliance_hold = true`. | AUTO — covered by AC-C-J-2, AC-C-J-24, AC-C-FSM-1 |
| **AC-C-BR-INV-03** | *(FLOOR)* **INV-C-03 — `compliance_hold` fully cleared before `planned`**: all active triggers individually resolved before `planned`. | AUTO — covered by AC-C-FSM-11 |
| **AC-C-BR-INV-04** | **INV-C-04 — One SO per redemption event**: one `VoucherRedemptionRequested` → exactly one SO. | AUTO — covered by AC-C-J-1 |
| **AC-C-BR-INV-05** | **INV-C-05 — Effective-unbreakable discipline at pick**: where `effective_unbreakable = true` (Layer 2 Module A `producer_breakability` OR Layer 3 Module S `commercial_unbreakable`), the pick instruction MUST name a quantity that is a multiple of `bottles_per_case`; a partial pick pauses with `manual_review = true`. | AUTO — set Layer-2 unbreakable + 6/case; assert qty multiple; simulate partial pick; assert `manual_review = true` |
| **AC-C-BR-INV-06** | *(FLOOR-adjacent)* **INV-C-06 — ISSUED Vouchers immune to recall**: reverse-pick draws from the unsold sub-pool only; ISSUED-bound bottles off-limits. | AUTO — covered by AC-C-J-26 |
| **AC-C-BR-INV-07** | *(FLOOR)* **INV-C-07 — No Module C cash refunds**: replacement shipments only; cash refunds are Module S supervisor-override scope. | AUTO — inspect the event catalogue + API surface; assert no cash-refund event/endpoint |
| **AC-C-BR-INV-08** | *(FLOOR)* **INV-C-08 — Original Voucher state preserved on post-shipment resolution**: no regression; no new Voucher; no new INV2. | AUTO — covered by AC-C-FSM-24 |
| **AC-C-BR-INV-09** | **INV-C-09 — Pre-shipment cancellation window only**: valid only in `draft`/`planned`; post-`shipped` → replacement. | AUTO — covered by AC-C-J-20, AC-C-FSM-5/6 |
| **AC-C-BR-INV-10** | *(not-exercised-at-launch — D5)* **INV-C-10 — Gift sub-flag direct-mode only**: `is_gift = true` valid only on direct mode. **Retained as the validation; the gift sub-flow idles at launch.** | AUTO — the validation verifiable; the gift sub-flow not-exercised-at-launch |

### §4.2 Late-binding rules and effective-unbreakable read

| AC ID | Rule statement (verbatim from §3) | Verification |
|---|---|---|
| **AC-C-BR-LB-1** | *(FLOOR; naming cascade)* Late binding = the assignment of a specific physical bottle to a Voucher at pick time; the Voucher is an entitlement to a bottle of a specific **Product Reference** from a specific Allocation pool, not a specific unit; no pre-pick physical-unit exposure to the Customer. | AUTO — covered by AC-C-J-3/J-6 |
| **AC-C-BR-LB-2** | *(already-deferred)* Producer-override on late-binding selection (Interpretation C) deferred Phase 2+; launch operates on the two-surface FIFO baseline only. | AUTO — assert no producer-override config field / code-path |
| **AC-C-BR-LB-3** | *(naming cascade)* Effective-unbreakable = Layer 2 (producer, Module A) OR Layer 3 (commercial, Module S); Module 0 Layer 1 (the **Product Variant** possible-case-configs whitelist) does NOT contribute; resolved per voucher-line before the Stream 1 instruction. | AUTO — four parametrised tests |
| **AC-C-BR-LB-4** | *(FLOOR — no-oversell-at-pick; the named B↔C contract; naming cascade)* When `effective_unbreakable = true`, Module C reads Module B's StockPosition `available_quantity` at `(Product Reference, warehouse, case_config, allocation, ownership)`; the pick quantity = the multiple of `bottles_per_case`; Module B records the case-integrity FSM transition on a partial-case pick of a breakable case (records, does not gate). | §3.4; DEC-137/192/196 | AUTO |
| **AC-C-BR-LB-5** | NS late binding: Stream 1 / `BottlePicked` name Allocation + InboundBatch + qty (no serial); no Module B `BottleNFTBurned`; effective-unbreakable at the quantity-multiple level. | AUTO — covered by AC-C-J-29 |
| **AC-C-BR-LB-6** | NS Voucher CruTrade gate: non-serialized Vouchers CANNOT transition to ON_CRUTRADE (enforced by Module S); cellar surfaces "non-serialized / no Bottle Page". | §3.6 + §14.1; DEC-133 | AUTO |

### §4.3 Logilize integration + reconciliation discipline rules **(R3 — the 4-stream contract already carried)**

| AC ID | Rule statement (verbatim from §4) | Verification |
|---|---|---|
| **AC-C-BR-WMS-1** | *(R3 — already 4-stream)* Module C owns 4 fulfilment streams (DEC-188): Stream 1 (outbound instruction), Stream 2 (pick confirmation), Stream 3 (dispatch confirmation), Stream 4 (delivery confirmation). **Stream 5 storage-location migrated to Module B as Stream B1.** | §4.2; DEC-188 | AUTO — assert exactly four fulfilment streams; assert the storage-location read goes via Module B-summary, not Logilize-direct |
| **AC-C-BR-WMS-2** | Stream 1 sent at `planned → picking`; carries SO id + PR-keyed line items + qty + Allocation context (sub-pool, counterparty, effective-unbreakable) + destination + `dispatch_mode` + the late-binding strategy field; NS → Allocation + InboundBatch + qty. | §4.2 Stream 1 | AUTO |
| **AC-C-BR-WMS-3** | Stream 2 pick-confirmation: serialized → serial + NFT identity; NS → Allocation + InboundBatch + qty; Module C fires `BottlePicked`. | §4.2 Stream 2 | AUTO |
| **AC-C-BR-WMS-4** | Stream 3 dispatch-confirmation: carrier receives the shipment; Module C transitions `picking → shipped` + fires `ShipmentDispatched`. | §4.2 Stream 3 | AUTO |
| **AC-C-BR-WMS-5** | Stream 4 delivery-confirmation (best-effort): Module C transitions `shipped → completed` + fires `BottleDelivered` + `ShippingOrderCompleted`; pickup handover IS the delivery moment. | §4.2 Stream 4 + §5.3 | AUTO |
| **AC-C-BR-WMS-6** | Reconciliation cadence real-time event-driven (no batch jobs at launch). | §4.3; DEC-141 | AUTO |
| **AC-C-BR-WMS-7** | Source-of-truth split (DEC-141): Logilize SoR for physical location + custody + pick-pack-dispatch + storage location; NewCo ERP SoR for Allocation/Voucher/Order (S/A), Customer/Profile (K), SerializedBottle/StockPosition (B), Procurement (D), the SO lifecycle (C), the catalog (0). | §4.3; DEC-141 | AUTO |
| **AC-C-BR-WMS-8** | Pick-discrepancy queue: four types (serial/quantity/batch mismatch; breakage-at-pick) set `manual_review = true`; operator resolves; `DiscrepancyResolutionRecorded` (Logilize-side); flag clears. | §4.4; DEC-141/091 | MIXED |
| **AC-C-BR-WMS-9** | Re-pick path: serial/batch mismatch may require a new Stream 1; SO holds in `picking` with `manual_review = true` until the corrected Stream 2 arrives clean. | §4.4 | AUTO |
| **AC-C-BR-WMS-10** | *(the named B↔C contract — the shared discrepancy queue)* The shared "Logilize discrepancy" queue (DEC-141) across Module C (fulfilment-side) + Module B (inventory-state-side); operator triages both; resolution events route to the correct module. | §4.3; DEC-141/188 | AUTO |

### §4.4 Three shipping modes + gift sub-flag rules

| AC ID | Rule statement (verbatim from §5) | Verification |
|---|---|---|
| **AC-C-BR-MODE-1** | Three modes (`direct`/`pickup`/`event`) as `dispatch_mode`. | AUTO — covered by AC-C-J-8/9/10 |
| **AC-C-BR-MODE-2** | Pickup handover at Vinlock IS the MPV VAT release-for-consumption moment; `ShipmentDispatched` fires at handover; INV2 fires. | §5.1 pickup + §5.3 | AUTO |
| **AC-C-BR-MODE-3** | *(FLOOR)* Pickup sanctions/Hold re-read at handover (DEC-181): the operator's handover-confirmation re-reads sanctions + Hold; an active failure / Hold blocks handover-confirmation. | §5.1 pickup; DEC-181 | AUTO |
| **AC-C-BR-MODE-4** | *(not-exercised-at-launch — D5)* Gift sub-flag valid only on direct (INV-C-10). | covered by AC-C-J-11 |
| **AC-C-BR-MODE-5** | *(not-exercised-at-launch — D5)* Gift recipient address read from the Module S gift sub-flow; `ShipmentDispatched` carries `is_gift = true`. **At launch the gift sub-flow idles; the read is the retained seam.** | §5.2; DEC-116 | AUTO — *not-exercised-at-launch* |

### §4.5 Carrier + shipping-fee rules **(FLOOR — tax)**

| AC ID | Rule statement (verbatim from §6) | Verification |
|---|---|---|
| **AC-C-BR-FEE-1** | *(FLOOR)* INV1 does NOT include shipping (DEC-045); Module C emits `ShippingFeeQuoted` informationally at Checkout; Module S records on the Order without billing. | §6.2 + §6.3; DEC-045 | AUTO |
| **AC-C-BR-FEE-2** | *(FLOOR — the precise division of labour)* INV2 line items: (1) shipping fee actual cost from `ShipmentDispatched` (**Module C contributes**); (2) destination VAT for EU under MPV, none for non-EU under DDP/DAP (**Module S computes**); (3) excise per `ExciseCalculated` (**Module C emits; Module S reads**); (4) storage fee accrued to dispatch (**Module S Module-S-internal roll-in**). **Module S composes + issues INV2.** | §6.3; DEC-146 | AUTO |
| **AC-C-BR-FEE-3** | Carrier selection NOT customer-facing: customers see fee + estimated delivery date but do not choose the carrier; operator-configurable rule set. | §6.1 | MIXED |
| **AC-C-BR-FEE-4** | `quote_origin = auto | manual` on `ShippingFeeQuoted`; carried to `ShipmentDispatched`. | §6.1; DEC-145 | AUTO — covered by AC-C-J-12/13 |
| **AC-C-BR-FEE-5** | Pickup/event fees: pickup zero/minimal admin-configured; event-venue per carrier rate; INV2 fires regardless. | §6.3 | AUTO |

### §4.6 Destination eligibility + DDP/DAP + US-state rules **(D3 — hybrid; OFAC FLOOR)**

| AC ID | Rule statement (verbatim from §7) | Verification |
|---|---|---|
| **AC-C-BR-DEST-1** | *(D3 — the hybrid; launch-config footprint)* Two-tier eligibility (DEC-147): Tier 1 automated for pre-cleared; Tier 2 white-glove fallback; no destination categorically unresolvable. **Tier-1 narrowed to low-friction at launch [config].** | AUTO — covered by AC-C-J-15/16 |
| **AC-C-BR-DEST-2** | Dual-point validation: Checkout (Module S) + SO creation (Module C re-validates). | §7.1 | AUTO |
| **AC-C-BR-DEST-3** | *(D3 — simple-at-launch; expansion deferred-with-feature)* US-state simple-at-launch (DEC-148): operator-configurable pre-cleared subset on auto; harder states via white-glove; OFAC mandatory at all destinations. **The auto US-state rule-matrix expansion is deferred-with-feature.** | MIXED |
| **AC-C-BR-DEST-4** | *(D3 — simple-at-launch; expansion deferred-with-feature)* DDP/DAP simple model (DEC-149): `incoterms` on `ShipmentDispatched`; operator-configurable default; EU under MPV vs non-EU under DDP/DAP INV2 split. **The country-by-country expansion is deferred-with-feature.** | AUTO — covered by AC-C-J-18 |
| **AC-C-BR-DEST-5** | Customer-facing DDP/DAP T&C disclosure is Module S scope; Module C issues no customer-facing terms. | §7.3 | AUTO |

### §4.7 Excise + customs + damages/breakage/transit-loss + insurance rules **(excise FLOOR — tax)**

| AC ID | Rule statement (verbatim from §8 and §9) | Verification |
|---|---|---|
| **AC-C-BR-EXCISE-1** | *(FLOOR)* Vinlock bonded warehouse; VAT + excise deferred until release-for-consumption = shipment; `ShipmentDispatched` is the trigger (pickup: handover; event: departure). | §8.2 | AUTO |
| **AC-C-BR-EXCISE-2** | *(operator-managed launch posture; automation deferred-with-feature)* Excise rate matrix operator-configurable per destination + alcohol classification; **rate-matrix expansion + automated update workflow deferred Phase 2+ (DEC-150).** | §8.2 | AUTO |
| **AC-C-BR-DAMAGE-1** | Module-B-owned `BottleBreakageInCustody`; Module C not involved unless discovered at pick (surfaced via `manual_review`; Module B records). | §9.1 + §4.4; DEC-151/132 | AUTO |
| **AC-C-BR-DAMAGE-2** | Module-C-owned: `BottleBreakageInTransit`, `BottleLossInTransit`, `BottleWriteOff`, `InsuranceClaimOpened`, `InsuranceClaimResolved`. | §9.1; DEC-151 | AUTO |
| **AC-C-BR-DAMAGE-3** | Post-shipment triage (§9.3): physical damage/loss → Module C Returns; NFC tag damage → Module B event-recording; both co-occur → both chains run; distinct + non-overlapping. | §9.3; DEC-130 | MIXED |
| **AC-C-BR-DAMAGE-4** | Module C does not consume Module B's tag-recovery events; Module B does not consume Module C's physical-damage events. | §9.3 | AUTO |
| **AC-C-BR-INSURE-1** | `InsuranceClaimOpened` carries `insurance_pool ∈ {carrier, newco_supplementary}` (DEC-167/048); the recovery routes per the captured pool; the NonRevenueCost wrapper composes as a net-back. | §15.1; DEC-167/048 | AUTO |
| **AC-C-BR-INSURE-2** | Insurance basis deferred to Vinlock contractual conditions (operational scope); transit cost absorption generally NewCo's; event-recording only. | §9.2; DEC-025/048 | AUTO |

### §4.8 Returns + replacement rules + cross-module preservation **(FSM + discipline FLOOR; automation manual-first — D14)**

| AC ID | Rule statement (verbatim from §10) | Verification |
|---|---|---|
| **AC-C-BR-RET-1** | Module C owns returns + replacement end-to-end; original Voucher state preserved; replacements only (no cash refunds — INV-C-07). | covered by AC-C-J-21 |
| **AC-C-BR-RET-2** | Module S consumes ONLY `ReplacementShipmentIssued` for notification — no Voucher state change; the DEC-102 8-state lock preserved. | §10.3; DEC-138/102 | AUTO |
| **AC-C-BR-RET-3** | *(the C-vs-S boundary)* Module S supervisor-override post-delivery cash refund (`SupervisorOverridePostDeliveryRefund`, §12.3) is SEPARATE + independent of Module C's replacement workflow; Module C records the underlying issue events; Module E records the financial event. | §10.3 | AUTO |
| **AC-C-BR-RET-4** | Replacement drawn from the same pool unsold sub-pool (INV-C-01); the original Voucher's `qty_issued` NOT decremented; the replacement is an additional unit (total dispatched-plus-replacement can exceed `qty_issued`). | §10.4 | AUTO |
| **AC-C-BR-RET-5** | *(substitution already-deferred — DEC-104)* Pool-exhausted: Module A surfaces the state; if undispatched (pool exhausted, no DEC-104 substitute approved), `ReplacementShipmentIssued` with `status = unfulfillable`; white-glove escalation → Module S supervisor-override + Voucher VOIDED + Returns closure `supervisor_override_refund`. **The interlock with Module A's `VoucherCancelled` release + Module B's `InventoryShortfallDetected` is the committed-inventory floor.** | §10.4 | MIXED |
| **AC-C-BR-RET-6** | *(full reverse-inbound already-deferred — OQ-18)* `ReturnReceiptRecorded` fires only if the Customer ships the damaged unit back (rare); Module D records the physical receipt (reverse-inbound of a DELIVERED unit); disposition managed operationally at launch. | §10.2 + §10.4; DEC-155 | AUTO |
| **AC-C-BR-RET-7** | Module B-side post-shipment NFC tag damage (`BottlePostShipmentTagIssueReported` + `ProvenanceCertificateIssued`) is event-recording at Module B only; no Module C replacement. *(Rides B's D12 decouple.)* | §10.3; DEC-130 | AUTO |
| **AC-C-BR-RET-8** | Module E consumes Module C's non-revenue cost events (`ReplacementShipmentIssued`, `ReturnReceiptRecorded`, `InsuranceClaimResolved`); Xero decides GL (DEC-072); Module C makes no accounting-policy claim. | §10.3; DEC-072 | AUTO |

### §4.9 In-transit voucher display + redemption-block **(redemption-block FLOOR; ETA precision deferred-with-feature — D17/item K)**

| AC ID | Rule statement (verbatim from §11) | Verification |
|---|---|---|
| **AC-C-BR-INT-1** | *(FLOOR)* Two gates decoupled (DEC-081): sellability gate (`Allocation.state = ACTIVE`); shipment gate (`InboundEventPhysicallyAccepted`); for the V1-per-order window they can fire apart. | §11.1 | AUTO |
| **AC-C-BR-INT-2** | *(deferred-with-feature — D17)* In-transit display "in transit; ETA X" on cellar + Voucher detail when the gate not yet fired; **ETA from a basic admin-configurable estimate at launch (carrier-ETA-precision deferred).** | §11.2 | AUTO — covered by AC-C-J-24; *ETA-precision arm deferred-with-feature* |
| **AC-C-BR-INT-3** | *(FLOOR)* Redemption-block: in-transit Voucher CANNOT be redeemed; SO held in `draft` with `compliance_hold` (shipment-gate sub-type) until `InboundEventPhysicallyAccepted`. | covered by AC-C-J-24 |
| **AC-C-BR-INT-4** | In-transit display clears automatically on `InboundEventPhysicallyAccepted`; V2 stock at Vinlock at/before issuance shows no in-transit display. | §11.2 | AUTO — covered by AC-C-J-24 |
| **AC-C-BR-INT-5** | In-transit Voucher cancellation: DEC-108 14-day window applies normally (Module-S-owned); Module C has no special in-transit cancellation path. | §11.3; DEC-108 | AUTO |

### §4.10 Producer recall reverse logistics **(D15 — minimal/manual)**

| AC ID | Rule statement (verbatim from §12) | Verification |
|---|---|---|
| **AC-C-BR-RECALL-1** | Manual operator capability at launch (DEC-152): Module D `ReverseInboundEventRecorded` → operator coordinates outside the system → operator initiates via Admin Panel → `ReverseShipmentDispatched`; no automated reverse-carrier API. | covered by AC-C-J-25 |
| **AC-C-BR-RECALL-2** | Recall scope = unsold sub-pool only (DEC-117). | covered by AC-C-J-26, AC-C-BR-INV-06 |
| **AC-C-BR-RECALL-3** | `ReverseShipmentDispatched` payload (Allocation ref, qty/serial refs, producer address, actor, timestamp); feeds Module D `ReverseInboundEventRecorded` + Module A pool debit. | §12.3; DEC-152 | AUTO |
| **AC-C-BR-RECALL-4** | *(already-deferred — OQ-18)* Full reverse-inbound mechanics deferred Phase 2+ (reverse 3-gate QC, cost-basis unwind, automated coordination, reverse-discrepancy); none at launch. | §12.3 + §12.4 | AUTO — assert no reverse-QC FSM / reverse-discrepancy queue / reverse-cost-basis path active |
| **AC-C-BR-RECALL-5** | Module E records the financial event for reverse-logistics cost; Xero decides GL; no accounting claim. | §12.3 | AUTO |

### §4.11 Storage-location + Cellar render rules **(D17 — basic; granular deferred-with-feature; six-module read + anonymisation KEPT)**

| AC ID | Rule statement (verbatim from §13 and §14) | Verification |
|---|---|---|
| **AC-C-BR-STORE-1** | *(D17 — warehouse-level; granular deferred-with-feature)* Warehouse-level customer-facing storage display only (DEC-153); **sub-warehouse granular remains Logilize-internal, not customer-facing.** | covered by AC-C-J-28 |
| **AC-C-BR-STORE-2** | *(already-deferred — OQ-16)* Multi-warehouse expansion deferred Phase 2+; the SO has no warehouse-routing attribute. | §13.2 | AUTO |
| **AC-C-BR-CELL-1** | *(D17 — the six-module read KEPT; naming cascade)* Cellar render six-module read (DEC-154). | covered by AC-C-J-27 |
| **AC-C-BR-CELL-2** | *(FLOOR-adjacent)* Anonymisation (DEC-024): the cellar is the Customer's private space (own holdings, no anonymisation); anonymisation applies only to the public Bottle Page (zero customer identifiers, Module B). | §14.2 | MIXED |
| **AC-C-BR-CELL-3** | *(already-deferred)* Cellar UX layout deferred (DEC-154); the v0.3-MVP commitment is the six-module data-source contract; literal UX is tech (DEC-073). | §14.3 | AUTO — covered by AC-C-BR-CELL-1 |

---

## §5 Domain event emission and consumption **(R3 — §5.8/§15.2 the 4-fulfilment-stream contract)**

### §5.1 SO lifecycle events emitted

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-EVT-1** | `ShippingOrderCreated` on `VoucherRedemptionRequested` consume; SO in `draft`. | §15.1 | AUTO |
| **AC-C-EVT-2** | `ShippingOrderPlanned` on `draft → planned`. | §15.1 | AUTO |
| **AC-C-EVT-3** | `ShippingOrderPickingStarted` on `planned → picking` (Stream 2 consumed). | §15.1 | AUTO |
| **AC-C-EVT-4** | *(FLOOR)* `ShipmentDispatched` on `picking → shipped`; payload actual cost + `incoterms` + `dispatch_mode` + `quote_origin` + serial/NFT (serialized) or Allocation + InboundBatch + qty (NS); consumed by Module S → `VoucherShipped` + `InvoiceINV2Issued`; the chain extends to Module B per DEC-134 *(rides B's D12 decouple)*. | §15.1 + §3.5 step 7; DEC-107/146 | AUTO |
| **AC-C-EVT-5** | `ShippingOrderCompleted` on `shipped → completed`. | §15.1 | AUTO |
| **AC-C-EVT-6** | `ShippingOrderCancelled` on `draft/planned → cancelled`; Voucher → ISSUED via Module S. | §15.1 + §2.2 | AUTO |
| **AC-C-EVT-7** | `ShippingOrderReturned` on `shipped → returned` (Returns FSM begins at REPORTED). | §15.1; DEC-138 | AUTO |
| **AC-C-EVT-8** | `ShippingOrderLost` on `shipped → lost`. | §15.1; DEC-151 | AUTO |

### §5.2 Late-binding + delivery + tiebreak events emitted

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-EVT-9** | *(FLOOR — the bind)* `BottlePicked` on Stream 2 consume; records serial + NFT identity (serialized) OR Allocation + InboundBatch + qty (NS); Allocation pool ref; effective-unbreakable status. | §15.1 + §3.5 step 6 | AUTO |
| **AC-C-EVT-10** | `BottleDelivered` on Stream 4; consumed by Module S → Voucher CONSUMED. | §15.1 | AUTO |
| **AC-C-EVT-11** | `ShippingOrderManualReviewRequired` on the voucher-side tertiary tiebreak; carries SO ref + candidate set + pool ref. | §15.1; DEC-137 | AUTO — covered by AC-C-J-5 |

### §5.3 Shipping-fee events emitted

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-EVT-12** | `ShippingFeeQuoted` at Checkout (informational; INV1 excludes shipping) + at re-quote; carries fee, carrier, transit estimate, `quote_origin`. | §15.1; DEC-145 | AUTO |

### §5.4 Excise / customs events emitted **(FLOOR — tax)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-EVT-13** | *(FLOOR; naming cascade)* `ExciseCalculated` at `planned → picking`; carries computed excise amount per Voucher, destination, **Product Reference**, rate applied; consumed by Module S for INV2. | §15.1; DEC-150 | AUTO — covered by AC-C-J-19 |

### §5.5 Damage / transit-loss + insurance events emitted

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-EVT-14** | `BottleBreakageInTransit` (transit damage post-delivery). | §15.1; DEC-151 | AUTO |
| **AC-C-EVT-15** | `BottleLossInTransit` (carrier loss). | §15.1; DEC-151 | AUTO |
| **AC-C-EVT-16** | `BottleWriteOff` when Module C is the trigger (transit-loss total-loss); for in-custody breakage Module B records the write-off. | §15.1 + §9.1 | AUTO |
| **AC-C-EVT-17** | `InsuranceClaimOpened` (`insurance_pool ∈ {carrier, newco_supplementary}`, DEC-167/048). | §15.1 | AUTO — covered by AC-C-BR-INSURE-1 |
| **AC-C-EVT-18** | `InsuranceClaimResolved` (settlement outcome); Module E records the financial event. | §15.1; DEC-151/072 | AUTO |

### §5.6 Returns + replacement events emitted (Returns FSM) **(manual-first at launch — D14)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-EVT-19** | `PostShipmentIssueReported` (Returns → REPORTED). | §15.1; DEC-138/184 | AUTO |
| **AC-C-EVT-20** | `ReturnInvestigationStarted` (→ INVESTIGATED). | §15.1; DEC-184 | AUTO |
| **AC-C-EVT-21** | `ReturnApproved` (→ APPROVED). | §15.1; DEC-184 | AUTO |
| **AC-C-EVT-22** | `ReturnRejected` (terminal). | §15.1; DEC-184 | AUTO |
| **AC-C-EVT-23** | `ReturnWithdrawn` (terminal). | §15.1; DEC-184 | AUTO |
| **AC-C-EVT-24** | `ReturnReceiptRecorded` (damaged unit received back — rare; Module D records the physical receipt). | §15.1; DEC-138 | AUTO |
| **AC-C-EVT-25** | *(the Module E seam)* `ReplacementShipmentIssued` (→ REPLACEMENT_ISSUED); consumed by Module S for notification only (Voucher state preserved); replacement per the late-binding algorithm; the DEC-167 NonRevenueCost wrapper + the DEC-182 OC-reversal (`DiscoveryRevenueShareReversed` from Module S) + fresh-OC-accrual fire here. | §15.1 + DEC-167/182; DEC-184 | AUTO |
| **AC-C-EVT-26** | `ReplacementShipmentDelivered` (→ CLOSED). | §15.1; DEC-184 | AUTO |

### §5.7 Reverse-logistics event emitted

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-EVT-27** | *(D15 — minimal/manual)* `ReverseShipmentDispatched` (recalled stock leaves Vinlock to producer); feeds Module D `ReverseInboundEventRecorded` + Module A pool debit. | §15.1 + §12.3; DEC-152/090/117 | AUTO — covered by AC-C-J-25, AC-C-BR-RECALL-3 |

### §5.8 Events consumed by Module C **(R3 RECONCILED — the 4-fulfilment-stream contract)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-EVT-28** | Module C consumes Module S `VoucherRedemptionRequested` (S §11.7) as the SO creation trigger. | §15.2 | AUTO |
| **AC-C-EVT-29** | *(FLOOR)* Module C consumes Module D `InboundEventPhysicallyAccepted` (D §7 + DEC-081) as the shipment gate; consumed at `draft → planned`. | §15.2 | AUTO |
| **AC-C-EVT-30** | Module C consumes Module D `ReverseInboundEventRecorded` (D §9 + DEC-090) as the recall-coordination trigger. | §15.2 | AUTO |
| **AC-C-EVT-31** | Module C consumes Module A allocation events (`AllocationCreated`, `AllocationActivated`, `AllocationSubPoolRebalanced`, `AllocationNonSerializedOptOutChanged`, `AllocationRecallTriggered`) — observed for boundary reads + recall coordination. | §15.2 | AUTO |
| **AC-C-EVT-32** | *(R3 — the 4-fulfilment-stream contract; already carried)* Module C consumes Logilize fulfilment-stream events (DEC-188): **Stream 2** pick-confirmation, **Stream 3** dispatch-confirmation, **Stream 4** delivery-confirmation, + the **customs-documentation-completed** event (DEC-150). **Storage-location is Module B's Stream B1 — assert NO Stream 5 storage-location direct consumer.** | §15.2; DEC-188 | AUTO — assert exactly the four named consumers; assert no Stream 5 storage-location direct consumer |

### §5.9 Events observed but not Module-C-owned (no Module C state change)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-EVT-33** | Module C observes Module S `VoucherShipped` + `InvoiceINV2Issued` (Module S emits on consuming `ShipmentDispatched`); Module C does not own INV2 issuance. | §15.3 | AUTO |
| **AC-C-EVT-34** | Module C observes Module S `VoucherSubstitutionExecuted` (substitution under DEC-104). | §15.3 | AUTO |
| **AC-C-EVT-35** | Module C observes Module S `SupervisorOverridePostDeliveryRefund` (the post-delivery cash refund; Module C records the underlying issue events only). | §15.3 | AUTO |
| **AC-C-EVT-36** | *(rides B's D12 decouple — C dispatches regardless)* Module C observes Module B `BottleNFTBurned` (chain terminus via Module S `VoucherShipped`, DEC-134); **DECOUPLED at launch — for NS / serialized-minus-NFT, Module B fires `BottleShippedAsNonSerialized`.** | §15.3 + §3.5 step 7; DEC-134 | AUTO — *NS-fallback path verified; the on-chain burn rides the decouple* |
| **AC-C-EVT-37** | Module C observes Module A `AllocationPoolDebitedDueToLoss` (the §17.4 destruction cascade; observed for the replacement-stock-availability check). *(Consistent with Module B's drafted §17.4/§19.3; the A-side emission is confirmed in the A↔B reconciliation — see the PRD digest flag.)* | §15.3; DEC-132 | AUTO |
| **AC-C-EVT-38** | Module C observes Module B `BottleDestroyedInCustody` (in-custody breakage; observed for the rare pre-shipment substitute-pick path per DEC-104). | §15.3; DEC-132 | AUTO |

---

## §6 Cross-module contracts + boundary respect

Module C is the highest cross-module-density module — it consumes Module S (the redemption trigger), Module D (the physical-receipt gate), Module A (the allocation-pool boundary), Module 0 (PR identity + excise classification), Module K (Customer/Profile/Hold/sanctions), Module B (the StockPosition / Bottle Page / NFT-burn terminus), Module E (the financial-event consumer). Each criterion verifies the **Module-C-side surface only**.

### §6.1 Logilize 4-stream contract (DEC-188) **(R3 — already 4-stream)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-XM-1** | *(R3)* Module C retains 4 fulfilment streams (DEC-188); Stream 5 storage-location migrated to Module B as Stream B1. | covered by AC-C-BR-WMS-1 |
| **AC-C-XM-2** | Inventory-state authority at Module B (DEC-185/188); Logilize SoR at the workflow-execution axis; Module B SoR at the inventory-state axis; Module C consumes from each per its axis. | §4.1; DEC-185/188 | MIXED |
| **AC-C-XM-3** | Module C does NOT own inventory-state streams (B1–B5); these are Module B's. | §4.2; DEC-188 | AUTO — assert no inventory-state events emitted by Module C |
| **AC-C-XM-4** | Four-way reconciliation (DEC-141): Logilize ↔ Module B ↔ Module S ↔ Module E; Module C participates indirectly via the SO state (the join surface). | §4.1 + §4.3; DEC-141/185/188 | AUTO |

### §6.2 Module S — redemption trigger + dispatch chain + Voucher state ownership

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-XM-5** | Module S `VoucherRedemptionRequested` is the SO creation trigger. | covered by AC-C-EVT-28, AC-C-J-1 |
| **AC-C-XM-6** | *(FLOOR)* `ShipmentDispatched` consumed by Module S → Voucher REDEMPTION_REQUESTED → SHIPPED + `VoucherShipped` + `InvoiceINV2Issued` (DEC-107/146); the chain extends to Module B `BottleNFTBurned` for serialized stock *(rides B's D12 decouple; NS → `BottleShippedAsNonSerialized`)*. | §15.1 + §15.4; DEC-107/146/134 | AUTO |
| **AC-C-XM-7** | `BottleDelivered` consumed by Module S → Voucher CONSUMED. | §15.4; Module S §11.7 | AUTO |
| **AC-C-XM-8** | Module C does NOT mutate Module S Voucher state; Module C emits triggers (`ShipmentDispatched`, `BottleDelivered`); on Returns, original Voucher state preserved (INV-C-08). | §0.6 Principle 3 + INV-C-08; DEC-102 | AUTO |
| **AC-C-XM-9** | Module S consumes ONLY `ReplacementShipmentIssued` for notification — no Voucher state change. | covered by AC-C-BR-RET-2 |
| **AC-C-XM-10** | The Module S supervisor-override cash refund (§12.3) is separate from Module C; Module C records issue events; Module S records the override; Module E the financial event. | covered by AC-C-BR-RET-3, AC-C-EVT-35 |

### §6.3 Module D — physical-receipt gate + reverse-inbound coordination

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-XM-11** | *(FLOOR)* Module D `InboundEventPhysicallyAccepted` is the shipment gate (DEC-081/143); consumed at `draft → planned`; sourcing-model-uniform (the V1-per-order window survives Direct-Purchase deferral). | covered by AC-C-EVT-29, AC-C-J-24, AC-C-BR-INT-1 |
| **AC-C-XM-12** | Module D `ReverseInboundEventRecorded` is the recall-coordination trigger (DEC-152). | covered by AC-C-EVT-30, AC-C-J-25 |
| **AC-C-XM-13** | `ReturnReceiptRecorded` corresponds to Module D reverse-inbound of a DELIVERED unit (distinct from a producer-recall reverse-inbound); Module D records the physical receipt; Module C the commercial event. | covered by AC-C-BR-RET-6 |
| **AC-C-XM-14** | `ReverseShipmentDispatched` feeds Module D `ReverseInboundEventRecorded` + Module A pool debit. | covered by AC-C-EVT-27, AC-C-BR-RECALL-3 |

### §6.4 Module A — Allocation pool boundary + the shortfall interlock

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-XM-15** | Module C reads Allocation context (sub-pool partition; counterparty per Module A §11.6; commercial terms) at late binding. | covered by AC-C-J-7, AC-C-BR-INV-01 |
| **AC-C-XM-16** | Module C does NOT mutate Module A state; pool reads only; pool debits emitted by Module A (e.g., `AllocationPoolDebitedDueToLoss`); Module C observes. | §0.6 + INV-C-01 | AUTO |
| **AC-C-XM-17** | *(the committed-inventory interlock)* Module A `AllocationRecallTriggered` consumed for the recall unsold sub-pool; **Module A's `VoucherCancelled` release primitive (DEC-099) + Module B's `InventoryShortfallDetected` interlock with Module C's replacement-when-pool-exhausted path** (the shortfall workflow Substitute/Refund/Cancel runs at Module A). | §10.4; DEC-117/099 | AUTO — covered by AC-C-J-26, AC-C-BR-RET-5 |
| **AC-C-XM-18** | The replacement draw is an additional unit beyond original issuance (covered by AC-C-BR-RET-4); Module A observes the pool state change. *(Composition note: over-issuance is a Module A operation-level rejection — no `AllocationCapacityExhausted` event; Module C does not reference it.)* | covered by AC-C-BR-RET-4 |

### §6.5 Module 0 — Product Reference identity + alcohol classification + Layer 1 breakability **(naming cascade)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-XM-19** | *(naming cascade)* Module C reads **Product Reference** identity + Composite SKU shape for shipment composition. | §15.4 | AUTO |
| **AC-C-XM-20** | *(naming cascade)* Module C reads **Product** alcohol classification for `ExciseCalculated` (DEC-150). | covered by AC-C-J-19 |
| **AC-C-XM-21** | *(naming cascade)* Module C reads Module 0 Layer 1 (the **Product Variant** possible-case-configs whitelist) for pick-discipline awareness — Layer 1 does NOT contribute to `effective_unbreakable` (Layer 2 OR Layer 3 only). | covered by AC-C-BR-LB-3 |

### §6.6 Module K — Customer + Profile + Hold + sanctions/OFAC

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-XM-22** | Module C reads Customer + Profile + shipping Address (Module K §3/§4) for eligibility + dispatch; read-only consumer. | §15.4 | AUTO |
| **AC-C-XM-23** | *(FLOOR)* Module C reads the Module K Hold entity (`compliance` type) for `compliance_hold` at `draft`; an active Hold blocks new SO creation/advance. | covered by AC-C-FSM-11, AC-C-BR-INV-03 |
| **AC-C-XM-24** | *(FLOOR)* Module C reads the sanctions/OFAC status (the Module K read-API tuple, §9.3) per DEC-113; **Module K exposes the read-API — Module C is the enforcement surface at its destinations.** | covered by AC-C-J-2/J-17, AC-C-FSM-11/12 |
| **AC-C-XM-25** | *(FLOOR)* Sanctions/Hold uniformity (DEC-181): the three Module C transaction-initiation surfaces re-read sanctions + Hold at the moment of action — SO creation, SO `draft → planned`, pickup-mode handover. | covered by AC-C-FSM-12/13, AC-C-BR-MODE-3 |

### §6.7 Module B — StockPosition read + Bottle Page link + observed NFT-burn terminus

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-XM-26** | *(the named B↔C contract)* Module C reads the Module B Bottle Page link (§16) for serialized stock in the cellar render; reads "non-serialized / no Bottle Page" for NS (DEC-186). | covered by AC-C-J-27, AC-C-J-29, AC-C-BR-LB-6 |
| **AC-C-XM-27** | *(FLOOR — no-oversell-at-pick; the named B↔C contract)* Module C reads Module B StockPosition `available_quantity` at `(Product Reference, warehouse, case_config, allocation, ownership)` for case-config-aware shippable quantity (DEC-196). | covered by AC-C-BR-LB-4 |
| **AC-C-XM-28** | *(the R3/DEC-188 cascade — ratified)* Module C reads the Module B storage-location summary for the cellar render (Stream B1; **the data source switched from Logilize-direct to Module B-summary**, DEC-188). | covered by AC-C-J-27, AC-C-J-28 |
| **AC-C-XM-29** | *(rides B's D12 decouple)* Module C observes Module B `BottleNFTBurned` as the chain terminus; Module C does NOT emit to Module B directly; the chain routes via Module S `VoucherShipped` (DEC-134). **DECOUPLED — NS / serialized-minus-NFT → `BottleShippedAsNonSerialized`; Module C dispatches regardless.** | covered by AC-C-EVT-36 |
| **AC-C-XM-30** | Module C observes Module B `BottleDestroyedInCustody` for the pre-shipment substitute-pick path at `manual_review` (DEC-104). | covered by AC-C-EVT-38 |

### §6.8 Module E — financial event emission

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-XM-31** | Module C emits the financial-triggering events to Module E (`ExciseCalculated`, `ShippingFeeQuoted`/dispatch cost, `InsuranceClaimResolved`, replacement-cost events); Module E records the financial event; Xero decides GL (DEC-072); no accounting-policy claim. *(Module C does NOT touch `SupplierPaymentCompleted` — the E-emitted / D+B-consumed cascade, R4.)* | §15.4; DEC-072 | AUTO |
| **AC-C-XM-32** | *(the Module E seam)* DEC-167 NonRevenueCost wrapper: Module E records the wrapper at the `ReplacementShipmentIssued` moment; composes with the eventual recovery as a net-back; Module C emits the trigger events; Module E owns the wrapper (deferred settlement, D19). | §15.1 + DEC-167 | AUTO |

### §6.9 Cellar render six-module read **(D17 — basic)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-XM-33** | Cellar render six-module read (DEC-154): Module S (Voucher + storage-fee state) + Module C (physical state in-flight + in-transit, basic ETA) + Module B (storage-location summary via B-summary + Bottle Page link / "non-serialized" indicator) + Module 0 (**Product Reference** catalog identity); Module C orchestrates the physical-side data; UX deferred (DEC-073). | covered by AC-C-J-27, AC-C-BR-CELL-1 |

### §6.10 Boundary statements — Module C does NOT carry

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-XM-34** | Module C does NOT execute physical movement / label printing / waybill / carrier API. | §1.3 item 1 | AUTO |
| **AC-C-XM-35** | Module C does NOT own inventory-state transitions outside shipment fulfilment (Module B / Module D). | §1.3 item 2 | AUTO — covered by AC-C-XM-3 |
| **AC-C-XM-36** | Module C does NOT make commercial decisions (Offer / pricing / Cart / Checkout / Voucher FSM — Module S). | §1.3 item 3 | AUTO |
| **AC-C-XM-37** | Module C does NOT do invoicing / payment / VAT determination / cash refunds / credit notes (Module E + Airwallex; Module S issues INV2). | §1.3 item 4 | covered by AC-C-BR-INV-07, AC-C-BR-FEE-1 |
| **AC-C-XM-38** | Module C does NOT make Hold decisions (Module K records; Module C reads). | covered by AC-C-XM-23 |
| **AC-C-XM-39** | Module C does NOT own Customer identity / address book / Profile / KYC / sanctions determination (Module K; Module C reads + enforces at its destinations). | covered by AC-C-XM-22, AC-C-XM-24 |
| **AC-C-XM-40** | Module C does NOT own Product Master data / Product Reference / Case Configuration / Layer 1 (Module 0; Module C reads). | covered by AC-C-XM-19, AC-C-XM-21 |
| **AC-C-XM-41** | Module C does NOT own the Allocation entity / Voucher FSM / sub-pool partition (Module A + Module S; Module C reads context at late binding). | covered by AC-C-XM-15/16, AC-C-XM-8 |
| **AC-C-XM-42** | Module C does NOT execute NFC tag application / NFT mint / burn / recovery (Module B; Module C provides the upstream dispatch event via Module S — DECOUPLED, D12). | covered by AC-C-XM-29 |
| **AC-C-XM-43** | Module C does NOT do supplier-side procurement / PO / supplier payment (Module D; Module C consumes `InboundEventPhysicallyAccepted` + `ReverseInboundEventRecorded` only — and does NOT touch `SupplierPaymentCompleted`, R4). | covered by AC-C-XM-11, AC-C-XM-12 |
| **AC-C-XM-44** | Module C does NOT send customer-facing notifications directly (downstream notification service consumes C lifecycle events). | §1.3 item 11 | AUTO |
| **AC-C-XM-45** | Module C does NOT execute producer-recall full reverse-inbound mechanics (deferred Phase 2+; manual at launch). | covered by AC-C-BR-RECALL-1/4 |
| **AC-C-XM-46** | Module C does NOT support drop-ship (OUT at launch; every shipment through Vinlock). | §1.3 item 13 | AUTO |
| **AC-C-XM-47** | Module C does NOT do multi-warehouse routing (single Vinlock at launch; no warehouse-routing attribute). | covered by AC-C-BR-STORE-2 |
| **AC-C-XM-48** | Module C does NOT own the Logilize integration mechanics (API / payload / retry — tech, DEC-073). | covered by §7 out-of-scope |

---

## §6.11 MVP re-baseline criteria (AC-C-MVP-*) **(NEW — verifies the Phase D re-baseline)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-C-MVP-1** | *(floor parity — the ship→cellar floor whole)* The ship→cellar floor is intact in composition: the shipment gate + redemption-block (INV-C-02/03), the late-binding voucher→bottle bind (AC-C-J-3/J-6), the no-oversell-at-pick StockPosition read (AC-C-BR-LB-4, composing A Layer 1 ∧ B Layer 2 ∧ S lesser-of), the dispatch → INV2 + burn chain (AC-C-XM-6), the OFAC/eligibility surface (AC-C-J-17 + the DEC-181 three-surface re-read), and the INV2 tax contribution (Module C contributes excise + shipping; Module S issues INV2 — AC-C-BR-FEE-2) all PASS unchanged from v1.1. | PRD §0.7 | AUTO — assert the floor criteria PASS-set is unchanged from v0.1 |
| **AC-C-MVP-2** | *(R3 — the 4-fulfilment-stream contract)* Module C's Logilize contract is the DEC-188 4-fulfilment-stream contract (Stream 1 + consume Streams 2–4 + customs-documentation-completed); **storage-location is Module B's Stream B1 (no Stream 5 direct consumer)**; the cellar storage-location summary reads via Module B-summary. The AC was *ahead* of the v1.1 PRD here (already 4-stream); this verifies the PRD §15.2/§15.4 now match. | PRD §0.6 / §4.2 / §15.2; DEC-188 | AUTO — covered by AC-C-BR-WMS-1, AC-C-EVT-32, AC-C-XM-28 |
| **AC-C-MVP-3** | *(D5 gift idle — the seam)* The `is_gift` sub-flag + the gift-recipient-address read + INV-C-10 are retained-but-unexercised at launch (the Voucher FSM is 7 states; gifting deferred); re-enable is additive (rides Module S's mutable customer-reference seam). | PRD §5.2 / §0.9 | AUTO — assert the `is_gift` validation (INV-C-10) present; assert the gift sub-flow not-exercised-at-launch; no orphan |
| **AC-C-MVP-4** | *(naming cascade)* The catalog-identity criteria carry the canonical names (`Bottle Reference → Product Reference`; `Wine Variant → Product Variant`); Module C's own physical-unit names (`Shipping Order`, `BottlePicked`, `ShipmentDispatched`, `BottleDelivered`, …) are retained as wine-display naming; behaviour identical. | PRD §0.9 / §N.2; Module 0 §18 | AUTO — diff event/entity names; assert PR/Variant renames on catalog reads; assert C's physical-unit names unchanged |
| **AC-C-MVP-5** | *(the D-dial deferred-with-feature seams)* Each SIMPLIFY arm's seam is present + additive: D3 (the Tier-1 list operator-expandable; the manual flow records the data a future engine consumes; the three automation engines authored-but-deferred), D13 (the Stream 1 "late-binding strategy" field), D14 (the FSM + events present; automation deferred), D17 (the six-module read contract; the basic-ETA + warehouse-level seams). | PRD §0.1–§0.4 / §16 | MIXED — AI assembles the seam trace per dial; Paolo confirms each deferred capability is named with its seam + roadmap pointer |
| **AC-C-MVP-6** | *(the NFT-burn rides B's D12 decouple — C dispatches regardless)* Module C's late-binding pick + dispatch are launch-ready whether or not the on-chain layer is live: serialized stock records the bound serial; the NFT burn rides B's decoupled workstream (no-op / `BottleShippedAsNonSerialized`); the NS path is the universal fallback. No new decouple decision in Module C. | PRD §0.5 / §0.7 / §3.5; DEC-134 | MIXED — AI drives dispatch with the on-chain layer feature-flagged off; assert `ShipmentDispatched` + `BottleShippedAsNonSerialized` fire; Paolo confirms C dispatches regardless |

---

## §7 Out of scope for this acceptance pass

Carried from v1.1 (the methodology DECs in the header) + the Phase D deferred-with-feature set:

- **Engineering Definition of Done** (DEC-073): coverage thresholds, performance budgets, error-handling exhaustion, observability, retry/idempotency mechanics, schema design, API style + transport, the Logilize / carrier / Avalanche API contract literals.
- **UI / UX acceptance**: the Cellar render layout (DEC-154); the Admin Panel form layouts (discrepancy queue / white-glove ticket / manual quote / Returns operator screens / reverse-shipment screens / handover-confirmation); the Consumer Portal screens (cellar tiles, Voucher detail, redemption-block messaging, in-transit "ETA X" badge, cancellation flow); navigation; validation copy; accessibility; responsive design.
- **Operational R&R / approval-tier policy** (`feedback_prd_rr_approval`): admin-configurable; not a build-time concern at this layer.
- **Non-functional concerns not anchored to a BR / DEC**: latency budgets, throughput, alerting thresholds, integration SLOs, infrastructure choice.
- **Phase 2+ deferrals (PRD §16) — the D-dial deferred-with-feature set + the already-deferred set:** the three D3 automation engines (US-state matrix DEC-148, DDP/DAP country-by-country DEC-149, excise-rate automation DEC-150); the D13 bottle-side Logilize warehouse-efficiency optimisation; producer-override late-binding (DEC-137); the D14 Returns/Replacement FSM automation; voucher-substitution full automation (DEC-104); full reverse-inbound mechanics (OQ-18/DEC-155); automated reverse-carrier API integration (DEC-152); multi-warehouse routing (OQ-16); drop-ship (OQ-17); sub-warehouse storage-location granular display (DEC-153); the D17 carrier-ETA-precision integration; the cellar UX layout (DEC-154); appointment-scheduling for pickup; customer-facing notifications direct from Module C; the B2B credit-term branch + active-consignment SO carve-out (DEC-068/011); auto-SO on combined invoicing (DEC-017). **Each carries its feature to `04-roadmap/` with its seam.**
- **Working-hypothesis NFT cluster (D12-decoupled)**: the on-chain NFT-burn semantics + smart-contract audit/governance (Module B / EXT-1/EXT-3 scope; DEC-120/121/122/124/131). Module C acceptance verifies only that Module C correctly emits the upstream trigger (`ShipmentDispatched`) and observes the terminus, **and dispatches regardless of the on-chain layer** (the NS path is the universal fallback) — not the on-chain semantics.
- **Cross-module behaviours owned by other modules**: the Module S Voucher FSM transitions + INV2 composition mechanics; the Module B `BottleNFTBurned` on-chain semantics + Bottle Page rendering + StockPosition computation; the Module D `InboundEventPhysicallyAccepted` triggering; the Module A pool-partition mechanics; the Module K Hold creation / lift + sanctions determination; the Module E NonRevenueCost accounting policy + Xero GL; the Module 0 catalog data quality. Verified in the receiving module's acceptance doc.
- **PRD ambiguities (AMB-C-1..3)**: an acceptance-authoring backlog (the Returns FSM transition→event 1:1 mapping; the pointer-row verification-tag inheritance; the `BottleWriteOff` Module-B-side correspondence) deferred to a future v0.3+ PRD editorial pass — orthogonal to MVP scope; the cut does not re-open them. *(Distinct from the R3 reconcile, which the MVP PRD lands.)*

---

## §8 Sign-off log

### §8.1 Format-validation milestones (template-level)

| Milestone | Date | Notes |
|---|---|---|
| v0.1 authored (parallel agent) | 2026-05-15 | Initial draft against the Module 0 template; APPROVE-with-two-confirmations; not yet Paolo-validated. |
| **v0.3-MVP re-cut (this session)** | **2026-06-08** | **Phase D re-baseline re-cut from v0.1 per the cut-sheet §5 delta: the four SIMPLIFY arms annotated (deferred-with-feature / manual-first / basic / launch-config); R3 confirmed (the AC already carried 4-stream — verifies the PRD now matches); naming cascade applied; the `is_gift` criteria annotated not-exercised-at-launch (D5); §6.11 MVP re-baseline (AC-C-MVP-1..6) added; floor criteria re-affirmed unchanged. No launch-scope criterion removed. DRAFT — awaiting batch ratification.** |

### §8.2 Per-AC delivery sign-off

Maintained at first delivery review. Each criterion's state (OPEN / DEMOED / ACCEPTED) + Paolo's signature + date land here. (Full table populated at delivery; placeholder rows omitted in this draft.)

---

## §9 Cross-references

- **Spec source (the companion PRD)** — [`../02-prd/Module_C_PRD_v0.3-MVP.md`](../02-prd/Module_C_PRD_v0.3-MVP.md).
- **Predecessor (re-cut from; frozen)** — [`../../reference/v1.1/01-prd/Module_C_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_C_Acceptance_v0.1.md) (the v1.1 acceptance, 213 criteria; never edited, plan R4).
- **Ratified cut-sheet** — [`../01-triage/Module_C_CutSheet_v0.1.md`](../01-triage/Module_C_CutSheet_v0.1.md) §5 (the acceptance-criteria delta this re-cut implements).
- **Phase C reconciliation** — [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) (R3 — the 5→4-stream reconcile; item J — the NFT-burn rides B's decouple; item K — the in-transit redemption-block + basic display; §6 floor chains).
- **Naming source of truth** — [`../02-prd/Module_0_PRD_v0.3-MVP.md`](../02-prd/Module_0_PRD_v0.3-MVP.md) §18.
- **Settled-sibling acceptance docs** — `Module_0/K/A/D/S/B_Acceptance_v0.3-MVP.md` (the cross-module surfaces verified on the receiving side).
- **MVP decisions register** — [`../04-decisions/MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md).

---

### §N MVP re-baseline trace (acceptance)

| v0.3-MVP AC section | v0.1 anchor | Cut-sheet §5 / Phase C | MVP disposition |
|---|---|---|---|
| §0 the re-cut delta | — (new) | cut-sheet §5; Phase C R3/item J/K | NEW — the four SIMPLIFY annotations + R3 + gift idle + naming cascade + floor re-affirmation. |
| §1 how to use | v0.1 §1 | — | KEEP; + the v0.3-MVP conventions (the SIMPLIFY/gift/floor/burn-ride inline notes); re-anchored. |
| §2 canonical journeys | v0.1 §2 | D3/D13/D14/D17/D15; R3 | KEEP all AC-C-J-1..31; annotate the SIMPLIFY arms; naming cascade; floor markers. |
| §3 FSM round-trips | v0.1 §3 | D14; DEC-181 | KEEP all AC-C-FSM-1..24; the Returns FSM annotated manual-first; floor markers. |
| §4 BR enforcement | v0.1 §4 | D3/D13/D14/D17/D15; R3 | KEEP all AC-C-BR-*; annotate the SIMPLIFY arms; naming cascade; floor markers; the INV2-tax precision. |
| §5 events | v0.1 §5 | R3 | KEEP all AC-C-EVT-1..38; §5.8 RECONCILED to the 4-fulfilment-stream contract; the burn-observation rides B's decouple. |
| §6 cross-module | v0.1 §6 | R3; item J | KEEP all AC-C-XM-1..48; the WMS row 4-stream; the B↔C contracts; the NFT-burn-ride note; C does NOT touch `SupplierPaymentCompleted`. |
| §6.11 MVP re-baseline | — (new) | cut-sheet §5; Phase C | NEW — AC-C-MVP-1..6 (floor parity / R3 / gift idle / naming / D-dial seams / NFT-burn ride). |
| §7 out of scope | v0.1 §7 | PRD §16 | KEEP; + the D-dial deferred-with-feature set + the already-deferred set with their seams. |
| §8 sign-off | v0.1 §8 | — | KEEP; + the v0.3-MVP re-cut milestone. |

Notation: *KEEP* = the v0.1 criterion stands unchanged at launch scope; *(FLOOR)* = an un-cuttable floor criterion; *deferred-with-feature* = the criterion's feature moves to roadmap with its seam (verified when the feature lands); *manual-first* = the workflow automation deferred; the FSM + events stand (D14); *not-exercised-at-launch* = retained-but-unexercised (the `is_gift` idle, D5); *RECONCILED* = the R3 contract-consistency confirmation (the AC already carried it); *naming cascade* = a naming-only rename (Product Reference / Variant), non-behavioural; *NEW* = a Phase-D re-baseline addition.

---

*End of Module C Acceptance Criteria v0.3-MVP — Phase D re-baseline. **A bounded re-cut — broad in touch (the four dials + R3 span most AC buckets) but annotations + feature-deferrals, not floor removals.** The four SIMPLIFY arms (D3 white-glove + Tier-1-footprint / D13 bottle-side optimisation / D14 FSM automation / D17 ETA-precision + granular-storage) are annotated deferred-with-feature / manual-first / basic / launch-config — the floor criteria on each stand UNCHANGED; R3 is confirmed (the AC was ahead of the v1.1 PRD — already 4-stream; the re-cut verifies the PRD now matches); the naming cascade applied; the `is_gift` criteria annotated not-exercised-at-launch (D5); §6.11 adds the MVP re-baseline criteria. The ship→cellar / OFAC / INV2-tax / no-oversell-at-pick / late-binding-bind floor criteria are untouched. No launch-scope criterion removed. **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E.*
