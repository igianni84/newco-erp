# NewCo ERP — Module S (Commerce — Offers / Cart / Checkout / Vouchers / Refunds / Storage) Acceptance Criteria — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP acceptance contract for Module S; re-cut from the v0.1 DRAFT).
- **Date**: 2026-06-08
- **Status**: **RATIFIED by Paolo 2026-06-08.** Held in `mvp/`; nothing promoted to `handoff/` until Phase E. **This is the heaviest acceptance delta of the triage so far — but it is annotations + feature-deferrals, not floor removals.** This doc (a) applies the **naming cascade** (Bottle Reference → Product Reference) to the catalog-identity criteria; (b) re-anchors to the v0.3-MVP PRD; (c) **moves the D7 composite criteria + the D5 gifting criteria → roadmap (deferred-with-feature, retained not deleted)** and **re-scopes the Voucher-FSM criteria to the 7-state launch machine**; (d) **annotates** the D8 stacking campaign-sophistication arms *not-exercised-at-launch* + the D6 refund-cost-matrix decisioning *manual-first* (the legal-floor criteria stand); (e) **lands the R2 reconciliation** (BR-S-CrossModule-4 — the AC already carries DEC-119; the PRD §18.16 is reconciled to match); (f) **verifies the three voucher-event names** (`VoucherIssued` / `VoucherVoided`) as Module S emits them, consistent with Module D's consumer side (no `SellThroughRecorded`); (g) **re-scopes the L-PP parity criteria to the Admin-Panel operator surface** (consumer storefront exempt — self-serve criteria stand); and (h) adds a **§6.11 MVP re-baseline** section. **No criterion in launch scope is removed; all consumer-floor + club-VP + tax/inventory-floor criteria stand unchanged.**
- **Owner**: Paolo (product sign-off authority).
- **Companion spec**: [`../02-prd/Module_S_PRD_v0.3-MVP.md`](../02-prd/Module_S_PRD_v0.3-MVP.md) — the source of truth this document validates against. The PRD says *what to build*; this document says *what passes*. Together they are the dev-team's complete brief for the launch-MVP Module S.
- **Predecessor (re-cut from)**: [`../../reference/v1.1/01-prd/Module_S_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_S_Acceptance_v0.1.md) — the v1.1 acceptance template (**DRAFT 2026-05-15; 215 criteria; 99.1% AUTO / 0.5% MIXED / 0.5% HUMAN; Packet verdict EDITS_NEEDED — NOT yet Paolo-validated**, like Module D). `greenfield/` is frozen (plan R4); this is a derivative under `mvp/`. **The MVP re-cut + the original validation land together** at Paolo's batch ratification.
- **Audience** (three concurrent uses): **Paolo** at module-delivery sign-off (verdict report + spot-checks); **dev team** during build (the definition of done, read alongside the PRD from day one); **AI coding agents** during code generation (AUTO criteria as fitness functions in the build loop).
- **Purpose**: the demonstrable behaviours that, taken together, constitute "Module S is delivered as specified per v0.3-MVP." Each criterion is traceable to a PRD anchor (BR-S-* / event / FSM transition / DEC / §) and tagged AUTO / MIXED / HUMAN.
- **Methodology DECs binding this document**: DEC-072 (no-accounting-policy claims — Module S records customer-facing financial-event signals; Module E records; Xero decides GL), DEC-073 (product-spec layer; criteria are business-behaviour, not tech-implementation), DEC-074 (self-contained; anchors restated inline), DEC-095/096/098/101/102/103/104/109 (Offer first-class entity + Hero designation + 5-rule publication + Order/Voucher FSMs + 1-voucher-per-bottle), DEC-105/106 (Cart Hold), DEC-108 (14-day cancellation), DEC-111 (Club Credit auto-apply), DEC-113 (sanctions/Hold gate), DEC-114 (Hero three-gate), DEC-119 (storage + INV3 ownership; three-actor split), DEC-181 (sanctions/Hold uniformity), DEC-187 (two-layer no-overselling), **Phase C R2 (DEC-119 storage Module-S-internal) + item F (`VoucherIssued` sell-through, no `SellThroughRecorded`)**, `feedback_prd_product_not_tech`, `project_prd_testing_methodology`.
- **What this document is NOT**: engineering Definition of Done (coverage thresholds, performance budgets beyond the PRD's explicit ATP-staleness commitment, retry/idempotency mechanics, schema design, the ATP-cache + timer stores, the stacking-pipeline internals); UI / UX acceptance (checkout-screen / cellar / Producer-Portal / Admin-Panel layouts, the in-transit "ETA X" render, the 14-day-WAIVER T&C disclosure UX, accessibility, i18n of UI chrome); operational R&R / approval-tier policy (admin-configurable per `feedback_prd_rr_approval`); non-functional concerns not anchored to a BR / DEC at PRD level.

---

## §0 What changed from v0.1 (the re-cut delta)

Module S is the **first genuinely cut-heavy module** — D7 (defer the multi-producer composite construct), D5 (defer gifting), D8 K.18/K.19 (defer two club-credit peripherals), D6 (simplify the refund-cost matrix) are real net-new Module-S deferrals/simplifies — **yet the consumer core-loop floor, the compliance/tax/inventory floor, and the club VP all stay whole.** This re-cut is the heaviest of the triage, but it is **annotations + feature-deferrals, not floor removals**:

1. **Naming cascade applied to the catalog-identity criteria** (Phase C item A): `Bottle Reference → Product Reference` in **AC-S-XM-1 / XM-36, AC-S-BR-Identity-3, AC-S-J-19** (substitute Product Reference), the Offer-line + Voucher-identity reads; the consumed Module 0 events `BottleReferenceActivated/Retired → ProductReferenceActivated/Retired` (**AC-S-EVT-35**). **Wine-display aliases** ("Bottle Reference / BR") are retained. **Module S's own `Offer*` / `Cart*` / `Order*` / `Voucher*` / `Invoice*` / `DiscoveryRevenueShare*` names are unchanged** (category-neutral). Behaviour identical. See AC-S-MVP-1.
2. **Re-anchored to the v0.3-MVP PRD.** PRD §-numbers now refer to [`../02-prd/Module_S_PRD_v0.3-MVP.md`](../02-prd/Module_S_PRD_v0.3-MVP.md). **§0–§19 keep their v1.1 meaning** (the floor + club VP + spine are KEEP-heavy; §6 composite + §13 gifting are deferred-but-retained section anchors) — every load-bearing AC anchor (§4.2 Offer FSM, §9.1 Order FSM, §11.3 Voucher FSM, §14 storage, §16 events, §18 BRs, §19 boundaries) remains valid. §0 was repurposed to "MVP scope at a glance"; §20 to the deferred set; §21/§22/§23 are the new MVP apparatus.
3. **D7 multi-producer composite criteria → roadmap (deferred-with-feature; retained, not deleted — see §0.1):** **AC-S-J-3** (composite publication + atomic N-decrement), **AC-S-FSM-6** (composite `AllocationClosed` → PAUSED cascade), **AC-S-BR-Publication-6** (composite 5-rule × N), **AC-S-BR-OCShare-3** (composite OC-on-`P_d`), **AC-S-EVT-33** (composite-sale causal order). The single-FK Offer entity + single-Allocation publication + per-Offer OC emission criteria are **retained as the seam**; **the single-producer Offer + multi-Offer-per-Allocation criteria (AC-S-J-1, AC-S-J-2) stand and are exercised at launch.**
4. **D5 gifting criteria → roadmap (deferred-with-feature) + Voucher FSM re-scoped to 7 states (see §0.2):** **AC-S-J-15 / J-16** (gifting end-to-end + decline/expiry), **AC-S-FSM-24** (GIFTED transfer-pending state), **AC-S-BR-Gifting-1..4**, **AC-S-BR-OCShare-5** (gifted-voucher OC preservation), **AC-S-BR-Voucher-7** (terminal-not-transferable — the gifting rule), **AC-S-EVT-14** (`VoucherGift*` family), **AC-S-XM-28** (DEC-181 at gifting initiation) → deferred-with-feature. **The Voucher-FSM criteria (AC-S-FSM-16, AC-S-BR-Voucher-2) re-scope to the 7-state launch machine** (GIFTED dropped). **Seam criterion retained:** the voucher ownership-transfer capability (mutable customer-reference) — AC-S-MVP-2.
5. **D8 Club Credit (K.18/K.19 deferred; K.17 KEPT) + stacking (campaign sophistication not-configured) — see §0.3:** **AC-S-BR-ClubCredit-1..4** + the Club-Credit-apply journey rows **STAND** (auto-apply + carry-forward KEPT, now exercised at launch); the welcome-window-scaling + operator-manual-issuance criteria are **deferred-with-feature** (reconciled to Module K K.18/K.19 retained seams); launch goodwill is verified via the **REFUND_COMPENSATION-coupon** path, not a separate manual-Club-Credit criterion. **AC-S-BR-Stacking-1..4 STAND** (the 7-step chain is the seam); the policy-discount (step 2) + volume/early-bird (step 5) arms are annotated **not-exercised-at-launch**. See AC-S-MVP-3.
6. **D6 refund (legal floor KEPT; matrix decisioning manual-first — see §0.4):** **AC-S-BR-Cancellation-1/2/3 STAND — FLOOR** (14-day window + post-shipment WAIVER + per-voucher partial refund); the refund-cost-matrix cause-routing + store-credit-105% + producer-fault-clawback-netting criteria are re-scoped to **manual-first operator handling** (the clawback netting is verified when Module E settlement lands — D19). **AC-S-BR-Cancellation-4/5** (Module C returns + replacement; supervisor override) stand. See AC-S-MVP-4.
7. **R2 reconciliation landed (Q7 — see §0.5):** **AC-S-BR-CrossModule-4** + **AC-S-XM-19 / XM-30** already carry the **DEC-119 Module-S-internal** framing (the Packet flagged the PRD-vs-AC drift — the AC was correct, the PRD §18.16 was stale); the MVP PRD §18.16 is now reconciled to match. Naming/contract only — no behaviour change; mirrors Module D's DEC-183 fix.
8. **The three voucher-event names verified as Module S emits them (Q6 — see §0.5):** **AC-S-EVT-12 / AC-S-XM-12** verify `VoucherIssued` (the V1/V2 PI auto-fire **and** the sell-through PO PRODUCER→NEWCO title signal — item F; **no `SellThroughRecorded`**) + `VoucherVoided` (PI-cancel) as Module S emits them, consistent with Module D's consumer side (D §14.4). See AC-S-MVP-5.
9. **L-PP parity criteria re-scoped to the Admin-Panel operator surface (Q8 — producer write UIs deferred):** **AC-S-BR-Parity-1..3** + the `actor_role` audit rows are verified at launch against the **Admin-Panel operator surface** (every club-Offer operation `actor_role: newco_ops`; Discovery already Admin-Panel-only); the Producer-Portal Offer-authoring write-UI half is **deferred-with-seam** (backend parity unchanged). **The consumer storefront is EXEMPT — the self-serve criteria (browse / cart / checkout / cellar / cancellation) stand.** No backend criterion changes. See AC-S-MVP-6.
10. **New section §6.11 — MVP re-baseline criteria** (7 criteria, AC-S-MVP-1..7), verifying the naming cascade, the D7 single-FK + D5 ownership-transfer seams, the D8 stacking/club-credit posture, the D6 manual-first refund matrix, the three voucher-event names, the L-PP Admin-Panel re-scope, and the R2 storage reconciliation.
11. **Floor criteria re-affirmed UNCHANGED:** the sanctions/Hold gate (**AC-S-BR-Gate-1/2/3, AC-S-XM-3, AC-S-XM-25..27/29**), INV1/INV2/INV3 + MPV VAT (**AC-S-BR-Invoice-1..7**), no-overselling shared-pool + lesser-of ATP (**AC-S-J-2, AC-S-XM-9/10**), 1-voucher-per-bottle (**AC-S-BR-Voucher-1, AC-S-FSM-25**), the Voucher-FSM core transitions (**AC-S-FSM-17..23**), Cart-Hold strict-timeout (**AC-S-J-4, AC-S-BR-CartHold-1..4**), Hero three-gate (**AC-S-J-20/21, AC-S-XM-6**), the storage cycle (**AC-S-J-10/11/12, AC-S-BR-Storage-1..10**) all stand as-is. **Nothing in launch scope removed.**

### §0.1 The D7 multi-producer composite posture (how the composite criteria are treated)

Per the cut-sheet Q1 ("defer the multi-producer Discovery composite construct; ship single-producer Offers; the Offer-entity single-FK form + Module A's per-constituent primitive + Module 0's Composite SKU are the seam") + Phase C item N (Discovery composites restore as a coordinated S + A + 0 set):

- **Single-producer Offers — KEPT, exercised at launch.** **AC-S-J-1** (club Offer publication), **AC-S-J-2** (multi-Offer-per-Allocation shared-pool decrement), the single-Allocation publication 5-rule validation, and the per-Offer OC-on-`P_d` emission for single-Allocation Discovery (**AC-S-BR-OCShare-1/2**) all stand.
- **Multi-producer composite — NOT exercised at launch (seam retained).** **AC-S-J-3** (composite publication + atomic N-decrement), **AC-S-FSM-6** (composite cascade), **AC-S-BR-Publication-6** (5-rule × N), **AC-S-BR-OCShare-3** (composite OC-on-`P_d`), **AC-S-EVT-33** (composite-sale causal order) → roadmap; **AC-S-MVP-2** verifies the seam is present-but-not-exercised (the Offer entity carries `composite_constituent_allocation_ids[]` in single-FK form; no multi-producer composite Offer is published at launch; **no downstream orphan** — each constituent voucher is a normal per-bottle voucher).

### §0.2 The D5 gifting posture + the Voucher FSM 8→7 (how the gifting criteria are treated)

Per the cut-sheet Q4 ("defer gifting; Voucher FSM 8→7; the ownership-transfer seam — mutable customer-reference — is preserved") + Phase C item N (gifting restores as a coordinated S + K + C set):

- **The 7-state Voucher FSM is exercised at launch.** **AC-S-FSM-16** (the state set) re-scopes to **PENDING_PAYMENT → ISSUED → REDEMPTION_REQUESTED → SHIPPED → CONSUMED + VOIDED / EXPIRED** (GIFTED dropped); **AC-S-FSM-17..23, AC-S-FSM-25/26** (the core transitions + 1-voucher-per-bottle + recall immunity) stand.
- **Gifting — NOT exercised at launch (seam retained).** **AC-S-J-15 / J-16** (gifting end-to-end + decline/expiry), **AC-S-FSM-24** (GIFTED transfer-pending), **AC-S-BR-Gifting-1..4**, **AC-S-BR-OCShare-5** (gifted-voucher OC preservation), **AC-S-BR-Voucher-7** (terminal-not-transferable), **AC-S-EVT-14** (`VoucherGift*`), **AC-S-XM-28** (DEC-181 at gifting init) → roadmap; **AC-S-MVP-2** verifies the **ownership-transfer seam** (the Voucher's customer-reference is mutable — no hard single-permanent-owner assumption; the `originating_club_id` hook is preserved).

### §0.3 The D8 Club-Credit + stacking posture (the customer-value call + the config posture)

Per the cut-sheet Q2/Q3 ("KEEP Club-Credit carry-forward [K.17] + closure-conversion [DEC-043]; DEFER welcome-window scaling [K.18] + operator manual issuance [K.19, goodwill via the REFUND_COMPENSATION coupon]; KEEP the 7-step stacking chain, campaign sophistication not-configured") + Phase C item D (the K-entity / E-events / S-redemption three-way seam holds):

- **Club Credit core — KEPT, now exercised at launch.** **AC-S-BR-ClubCredit-1..4** (auto-apply at checkout-render, `min(credit.balance, eligible totals)`; customer-removable; no cross-Club pooling; Hero exclusion) stand; **carry-forward** (the Remaining balance — Module K K.17) is now exercised at launch (the deferred forfeiture-rule would have been *more* work + worse customer value). **DEC-043 closure-conversion** (Club Credit → Discovery store-credit) stands (**AC-S-EVT-36** `ClubSunset/ClubClosed` consumption — KEEP-lean).
- **K.18 welcome-window scaling + K.19 operator manual issuance — NOT exercised at launch (seams in Module K).** Launch = full-fee → full-credit; **launch goodwill routes through the REFUND_COMPENSATION coupon** (verified via the **AC-S-EVT-20** promo path + AC-S-MVP-3), not a separate manual-Club-Credit criterion.
- **Stacking — the 7-step chain KEPT as the spine; campaign sophistication not-configured.** **AC-S-BR-Stacking-1..4** stand; the **policy-discount (step 2) + volume/early-bird-multiplier (step 5)** arms are **annotated not-exercised-at-launch** (no campaign configured at launch; verified when a campaign lands). *(Honest: thin — a config/QA posture, not a build cut; the chain is v17-inherited-and-built.)*

### §0.4 The D6 refund posture (legal floor KEPT; matrix decisioning manual-first)

Per the cut-sheet Q5 ("KEEP the legal floor; SIMPLIFY the refund-cost-matrix sophistication → manual-first; the clawback netting defers-with-settlement, D19"):

- **The legal floor — KEPT, FLOOR.** **AC-S-BR-Cancellation-1** (14-day pre-shipment window from INV1), **AC-S-BR-Cancellation-2** (post-shipment Article-16 WAIVER), **AC-S-BR-Cancellation-3** (per-voucher partial refund), **AC-S-BR-Cancellation-4/5** (Module C returns + replacement; supervisor override) all stand unchanged. **Nothing touches consumer-withdrawal rights (DEC-057).**
- **The refund-cost-matrix decisioning — manual-first at launch.** The DEC-025 cause-routing + DEC-044 store-credit-105% goodwill + producer-fault-clawback-netting criteria (**AC-S-J-19** consent-mode capture stands; the cost-matrix routing arm of **AC-S-BR-Storage-9** + the clawback netting) are re-scoped to **manual-first operator handling** (the operator records the refund + cause; offers store-credit-105% via the REFUND_COMPENSATION coupon). **The producer-fault clawback netting is verified when Module E settlement lands (D19).** The cause taxonomy + the coupon + the refund event payloads are retained as the seam. See AC-S-MVP-4.

### §0.5 The R2 reconciliation + the three voucher-event names (Q7 + Q6)

- **R2 (DEC-119) — storage Module-S-internal.** The single storage cross-module read is the **Module D → Module S** read of `InboundEventPhysicallyAccepted`; **no bidirectional S↔E at INV2.** **AC-S-BR-CrossModule-4** + **AC-S-XM-19 / XM-30** already carry the DEC-119 framing (the v1.1 AC was *ahead* of the v1.1 PRD — the Packet flagged the drift); the MVP PRD §18.16 is reconciled to match. **AC-S-XM-13** (the single Module D read) stands. *(The §14 body + the AC were always correct; only the v1.1 PRD §18.16 BR text was stale.)*
- **The three Module-D-owed voucher-event names (Q6) — discharged at the PRD layer; verified Module-S-side.** `VoucherIssued` = the V1/V2 ProcurementIntent auto-fire trigger **and** the sell-through signal driving Module D's PO PRODUCER→NEWCO **title** transition — **there is NO separate `SellThroughRecorded` event** (resolving the v1.1 AMB-D-3 nuance); `VoucherShipped` is available for a shipment-keyed title leg; `VoucherVoided` = the PI-cancel signal. **AC-S-EVT-12** (Voucher lifecycle emission) + **AC-S-XM-12** (the `VoucherIssued` → Module D PI chain) verify Module S **emits these names exactly as Module D consumes them** (D §14.4 / §16.4 — the forward-consistency obligation). **Take no accounting position on the title timing (DEC-072 / Phase C item F).** See AC-S-MVP-5.

### §0.6 Acceptance-authoring backlog (orthogonal to MVP scope)

Module S's acceptance is DRAFT **EDITS_NEEDED** (not Paolo-validated, unlike 0/K/A); the MVP re-cut + the original validation land together. The **AMB-S-1..6** PRD ambiguities (Layer-3 immutability framing duplication; §19/§20 boundary overlap; DEC-103 voucher-expiry default-null-vs-10–20yr framing; ship-on-confirmation + mid-semester boundary-day edge cases; MIXED criterion density; DEC-104/066 customer-consent-mode + non-serialized OC-preservation cross-cuts) are an **acceptance-authoring backlog** deferred to a future editorial pass — orthogonal to MVP scope. The Packet's counting-convention + verbatim-policy questions (incl. the BR-S-CrossModule-4 drift) are an authoring review separate from this MVP-scope triage; the BR-S-CrossModule-4 drift is resolved by the §0.5 / R2 reconciliation (the PRD was stale; DEC-119 is correct).

---

## §1 How to use this document

### §1.1 Verification tags

- **AUTO** — an AI agent or automated harness reads the criterion + spec anchor + running system (event stream, entity state, API responses, audit trail) and produces a PASS/FAIL verdict with evidence. Paolo reviews the verdict batch.
- **MIXED** — AI prepares the evidence (e.g., the end-to-end event sequence for a Hero Package purchase incl. Module K consumer effects); Paolo confirms a judgment call (operational coherence; cross-module sequencing; storage-clock-derivation correctness on edge sourcing cases; the three-voucher-name forward-consistency).
- **HUMAN** — Paolo executes personally (a single end-to-end demo session + a small set of subjective spot-checks on the cross-cutting operator experiences).

**Distribution for Module S v0.3-MVP: ~222 total criteria** — the v0.1 215 (99.1% AUTO / 0.5% MIXED / 0.5% HUMAN) **+ 7 MVP re-baseline criteria (5 AUTO / 2 MIXED)**. The D7 composite criteria (AC-S-J-3, AC-S-FSM-6, AC-S-BR-Publication-6, AC-S-BR-OCShare-3, AC-S-EVT-33) + the D5 gifting criteria (AC-S-J-15/16, AC-S-FSM-24, AC-S-BR-Gifting-1..4, AC-S-BR-OCShare-5, AC-S-BR-Voucher-7, AC-S-EVT-14, AC-S-XM-28) are **annotated deferred-with-feature → roadmap** (retained for when the features restore); the Voucher-FSM state-set criterion (AC-S-FSM-16) + AC-S-BR-Voucher-2 **re-scope to 7 states**; the stacking step-2/step-5 arms are **annotated not-exercised-at-launch**; the refund-cost-matrix decisioning arms are **annotated manual-first**; the L-PP parity criteria are **re-scoped to the Admin-Panel surface**. Paolo's hands-on load: the MIXED items + 1 end-to-end demo session.

### §1.2 Build-time usage (dev team + AI coding agents)

Identical to Module 0 acceptance §1.2: consulted from day one of construction; AUTO criteria wired into CI; AI coding agents treat AUTO criteria as fitness functions; MIXED + HUMAN scheduled. The document evolves lock-step with PRD revisions. **Deferred-with-feature criteria stay in the document (annotated) so they are not lost when D7 / D5 / D8-peripherals restore.**

### §1.3 Sign-off cadence

OPEN → DEMOED → ACCEPTED per Module 0. Module S is **delivered** when every **launch-scope** criterion in §2–§6 is ACCEPTED. Deferred-with-feature criteria are out of the launch sign-off set (verified when their feature restores). Sign-off log at §8.

### §1.4 Anchors

PRD §-numbers refer to [`../02-prd/Module_S_PRD_v0.3-MVP.md`](../02-prd/Module_S_PRD_v0.3-MVP.md). BR-S-* refers to §18. Event names refer to §16. FSM states refer to §4.2 (Offer), §9.1 (Order), §11.3 (Voucher — 7 states at launch). DEC refers to `greenfield/04-decisions/decisions.md`.

### §1.5 Format conventions (propagated from Module 0 §1.5; carried)

1. **§4 BR statements are verbatim from PRD §18** (self-containment, DEC-074; drift detection trivial). Where the PRD §18 BR text was reconciled for the MVP (BR-S-CrossModule-4 — R2; the 7-state Voucher FSM in BR-S-Voucher-2; the deferred-with-feature BRs), the AC statement carries the **reconciled** text + an annotation.
2. **§4 BR→AC pointer rows preserve traceability** ("already covered by AC-S-J-X / AC-S-FSM-X"). No BR is missing from §4.
3. **§6 cross-module criteria verify the Module-S-side surface only**; downstream behaviour is verified in the receiving module's acceptance doc. No dual-side overlap.
4. **AUTO criteria dependent on consumer modules carry inline "verified when X lands" notes** (Module S emits ~40 events; many AC rows verify downstream consumption by D / B / C / E / K / 0 / HubSpot). Module-S-side emission verification runs at Module S handover.

---

## §2 Canonical journeys — end-to-end commerce flows

The customer-facing + operator-facing journeys exercised end-to-end: Offer publication, Cart-and-Hold formation, three checkout flows (card / bank-transfer / Hero Package), Voucher issuance + redemption, all three customer-facing invoices (INV1 / INV2 / INV3) incl. storage-fee accrual + mid-semester roll-in, the 14-day cancellation + refund flow, *(gifting — deferred at launch, D5)*, and producer recall coordination.

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-J-1** | Allocation Operator (**Admin Panel at launch — the Producer-Portal write UI is deferred per L-PP; backend parity unchanged**) creates a club Offer against an `ACTIVE` Allocation in DRAFT, configures granularity / pricing / time-window / Hero Package flag / Layer 3, submits for review; reviewer marks REVIEWED → SUBMITTED; the 5-rule publication validation (DEC-098) evaluates; on pass, `OfferActivated` fires and the Offer is buyable on the club page; on fail, `OfferPublicationValidationFailed` fires with reason and the Offer reverts to DRAFT. | §4.2 + §7; DEC-098; BR-S-Offer-2 | AUTO — drive happy path then negative path (rule 1–5 each independently); assert correct event family + reason payload per case |
| **AC-S-J-2** | **Multi-Offer-per-Allocation pattern (DEC-099) — FLOOR (no-overselling)**: from a single 100-unit Allocation, Operator publishes a bottle Offer + a 6-pack case Offer + a Hero Package Offer + an early-bird promotion Offer; all four read the same shared `Allocation.qty − issued` pool; sell-through on any decrements the shared pool; first-to-consume-last-unit wins; **over-issuance is rejected at the issuance operation (Module A Layer 1, `qty − issued ≥ 0`).** *(Naming drift, §0: v1.1 named an `AllocationCapacityExhausted` signal; Module A v0.3-MVP frames over-issuance as an operation-level rejection — no event.)* | §4.4; DEC-099; BR-S-Offer-1 | AUTO — set up shared-pool scenario; run concurrent issuance against the four Offers up to and beyond capacity; assert atomicity, last-unit-wins, and rejection at the issuance operation (no over-issuance) |
| **AC-S-J-3** | **Multi-producer Discovery composite Offer** *(NOT-EXERCISED-AT-LAUNCH → roadmap; D7 deferred — the Offer-entity single-FK form + Module A's per-constituent primitive + Module 0's Composite SKU are the retained seam)*: Discovery Curator creates a composite Offer referencing N constituent Allocations atomically via `composite_constituent_allocation_ids[]` (multi-FK); publication evaluates all 5 rules against **each** constituent per §7.2; one composite purchase decrements all N constituents atomically (DEC-097 + DEC-179); N constituent vouchers issue (one per constituent bottle). Verified when the composite construct is re-enabled. | §6 + §7.2; DEC-097; BR-S-Publication-6; BR-S-Voucher-1 | AUTO (deferred) — when the composite restores, set up a 3-constituent composite spanning producers X/Y/Z; assert N=3 atomic decrements + atomic rollback on any-constituent-exhausted, 3 `VoucherIssued`, each binding the correct constituent Allocation. At launch: AC-S-MVP-2 verifies the single-FK seam present-but-not-exercised |
| **AC-S-J-4** | **Cart Hold strict-timeout — FLOOR**: Customer adds an Offer to Cart → `CartHoldCreated` with 15-min default expiry (DEC-105); customer interaction does NOT reset the timer (DEC-106); after 15 minutes the Hold expires → `CartHoldExpired` + Allocation reservation releases; the Cart line still exists; on re-add a fresh 15-min Hold is acquired (subject to current `available_qty`). | §8.1-§8.2 + §8.5; DEC-105; DEC-106; BR-S-CartHold-1; BR-S-CartHold-2 | AUTO — drive cart-add → hold-expiry without interaction; assert no-reset; assert release; re-add and assert new hold |
| **AC-S-J-5** | **Bank-transfer checkout happy path**: Customer at Checkout selects `payment_method = bank_transfer` → Cart Hold extends to 7 calendar days (DEC-049; `CartHoldExtended`; Order → PENDING_PAYMENT per DEC-101); funds confirmed at Airwallex → Order PENDING_PAYMENT → PAYMENT_CONFIRMED → CONFIRMED; Voucher PENDING_PAYMENT → ISSUED; INV1 + (Discovery only) OC share fire from the same transaction. *(The Hero Package membership fee is **not** a bank-transfer line — it requires a card / SEPA Direct Debit mandate and is approval-triggered; MVP-DEC-016.)* | §8.3 + §9.3 + §10.6; DEC-049; DEC-101; DEC-107; DEC-112 | AUTO — drive bank-transfer happy path; assert event ordering `OrderPlaced` → sanctions/Hold gate → `OrderPaymentPending` → (funds-cleared) → `OrderPaymentCaptured` → `OrderConfirmed` + `InvoiceINV1Issued` + Voucher PENDING_PAYMENT → ISSUED |
| **AC-S-J-6** | **Bank-transfer 7-day timeout**: window expires WITHOUT funds clearing → Cart Hold expires; Voucher auto-VOIDS with reason `bank_transfer_timeout` (DEC-101); Allocation reservation releases; Order PENDING_PAYMENT → CANCELLED; **no INV1, no financial event**. | §8.3 + §9.3; DEC-101; BR-S-Order-3 | AUTO — drive bank-transfer without funds clearance; advance past 7 days; assert no `InvoiceINV1Issued`; assert `VoucherVoided` correct reason; assert `qty − issued` slot released |
| **AC-S-J-7** | **Card checkout happy path**: `OrderPlaced` → sanctions/Hold gate (DEC-113) → `OrderPaymentAuthorized` → `OrderPaymentCaptured` → `OrderConfirmed` + `InvoiceINV1Issued` + Voucher → ISSUED directly (NO PENDING_PAYMENT for cards) + (Discovery only) `DiscoveryRevenueShareAccrued` on headline `P_d` + N `VoucherIssued`. | §9.3 + §10.6 + §10.8; DEC-101; DEC-107; DEC-112; BR-S-Voucher-2 | AUTO — drive card happy path; assert single-step capture + Voucher ISSUED directly + correct emission order from the same transaction |
| **AC-S-J-8** | **Single-transaction mixed cart**: cart contains BOTH club + Discovery + a Hero Package Offer; checkout submits as **one Order** with **one INV1** (BMD §4.7 + DEC-101); each line settles per its own commercial mechanic (DEC-100 + DEC-110); OC 5% × `P_d` fires for Discovery lines only; `MembershipFeePaid` fires for the Hero line. *(A **joining** Hero Package is approval-triggered, not a cart line — MVP-DEC-016; this mixed-cart scenario covers a **renewal** Hero line, and the membership fee is card / SEPA-DD-mandate only.)* | §9.4; DEC-101; BR-S-Order-4 | AUTO — assemble mixed cart (1 club + 2 Discovery + 1 renewal Hero); complete checkout; assert single INV1 ref + per-line commercial events + Module K Profile renewal via `MembershipFeePaid` |
| **AC-S-J-9** | **Redemption + INV2 with mid-semester storage roll-in**: Customer requests shipment on an ISSUED Voucher → ISSUED → REDEMPTION_REQUESTED; Module C runs pick/pack/dispatch; on dispatch → SHIPPED; `VoucherShipped` + `InvoiceINV2Issued` fire from Module S; if shipment occurs mid-semester, unbilled storage-months roll into INV2 (§14.4, Module-S-internal). | §10.7 + §11.3 + §14.4; DEC-107; DEC-119; BR-S-Invoice-3 | AUTO — set up Voucher past `storage_accrual_start_date`; trigger redemption + shipment; assert `InvoiceINV2Issued` carries primary line + N storage-month line items from Module S's own storage state |
| **AC-S-J-10** | **Storage-fee accrual**: a Voucher in ISSUED reaches `storage_accrual_start_date = max(INV1 + 12 months, InboundEventPhysicallyAccepted)`; from that month forward, one `StorageFeeAccrued` per month at €0.25/month (DEC-118); partial months count as full months. | §11.2 + §14.2 + §14.3 + §16.10; DEC-119; BR-S-Storage-2; BR-S-Storage-4; BR-S-Storage-5 | AUTO — set Voucher INV1 in past; advance past 12 months + ensure `InboundEventPhysicallyAccepted` recorded; assert monthly `StorageFeeAccrued` + partial-month-rounding flag on the bridging month |
| **AC-S-J-11** | **Semi-annual INV3**: at end-June + end-December, Module S aggregates the prior 6 months of `StorageFeeAccrued` per Customer + emits `InvoiceINV3Issued`; the aggregate **excludes** months already rolled into mid-semester INV2 (§14.4). | §14.3; DEC-119; BR-S-Invoice-6; BR-S-Storage-6; BR-S-Storage-8 | AUTO — voucher A accrued months 1-6 (in cellar through semester-end); voucher B accrued months 1-3 then shipped (rolled into INV2); advance to semester-end; assert one `InvoiceINV3Issued` covering voucher A's 6 months only |
| **AC-S-J-12** | **Mid-semester INV2 storage roll-in (Module-S-internal, DEC-119)**: a Voucher ships mid-semester; Module S reads its own storage state, computes unbilled months since the last INV3 cycle (or `storage_accrual_start_date`), and adds them as INV2 line items in the same transaction; **no cross-module query** at INV2 issuance. | §10.7 + §14.4; DEC-119; BR-S-Storage-7 | AUTO — Voucher with 3 months `StorageFeeAccrued` since last INV3; ship; assert primary INV2 line + 3 storage-month line items + no cross-module call in the trace |
| **AC-S-J-13** | **14-day pre-shipment cancellation — FLOOR (legal)**: Customer cancels a Voucher within 14 days of INV1 (state PENDING_PAYMENT / ISSUED / REDEMPTION_REQUESTED, NOT SHIPPED) → `VoucherVoided` (`customer_cancellation`) + `qty − issued` restored (DEC-079) + `InvoiceINV1PartialRefundIssued` + (Discovery only) `DiscoveryRevenueShareReversed` proportional (DEC-109). **`VoucherVoided` cascades to Module D to cancel a V1 PI** (item F / §17.4). | §12.1 + §12.4 + §12.7; DEC-108; DEC-109; BR-S-Cancellation-1; BR-S-Cancellation-3 | AUTO — drive cancellation on day 7; assert Voucher VOIDED + slot released + INV1 partial refund + OC reversal in the same transaction; assert `VoucherVoided` emitted for Module D PI-cancel |
| **AC-S-J-14** | **Post-shipment WAIVER — FLOOR (legal)**: once a Voucher transitions REDEMPTION_REQUESTED → SHIPPED, the cancellation right is **WAIVED** (DEC-108 EU Distance Contracts Article 16); a cancel attempt on a shipped Voucher is rejected; post-shipment issues route via Module C returns + replacement (NOT Module S cancellation). | §12.1 + §12.2 + §12.3; DEC-108; BR-S-Cancellation-2; BR-S-Cancellation-4 | AUTO — drive Voucher to SHIPPED; attempt cancellation; assert rejection with WAIVER reason; verify Module C returns + replacement is the documented alternative |
| **AC-S-J-15** | **Gifting end-to-end** *(NOT-EXERCISED-AT-LAUNCH → roadmap; D5 deferred — the Voucher ownership-transfer capability [mutable customer-reference] is the retained seam; Voucher FSM 8→7)*: giver initiates gift on an ISSUED Voucher → eligibility gates (recipient registered + KYC `passed` + Offer-eligibility) + DEC-181 sanctions/Hold on both parties; Voucher ISSUED → GIFTED; 7-day accept window; recipient accepts → GIFTED → ISSUED on recipient's cellar with the **giver's `originating_club_id` preserved**; `VoucherGiftAccepted`; no financial event. Verified when gifting is re-enabled. | §13.1-§13.3; DEC-116; BR-S-Gifting-1/2/3 | AUTO (deferred) — when gifting restores, drive giver-initiate + recipient-accept; assert OC reference preserved; assert no revenue event. At launch: AC-S-MVP-2 verifies the ownership-transfer seam |
| **AC-S-J-16** | **Gifting decline / expiry** *(NOT-EXERCISED-AT-LAUNCH → roadmap; D5)*: recipient declines OR the 7-day window expires → Voucher GIFTED → ISSUED on the **original giver's cellar**; `VoucherGiftDeclined` / `VoucherGiftExpired`; no financial event. Verified when gifting is re-enabled. | §13.2 + §13.3; DEC-116; BR-S-Gifting-1 | AUTO (deferred) — when gifting restores, drive decline + window-expiry; assert voucher returns to giver in both cases |
| **AC-S-J-17** | **Producer recall coordination — FLOOR-adjacent**: Module A emits `AllocationRecallTriggered`; Module S observes; scope is **unsold sub-pool only** (`qty − issued`); Module S does NOT void any ISSUED / REDEMPTION_REQUESTED / SHIPPED / CONSUMED Voucher on that Allocation. *(GIFTED removed from the per-state matrix at launch — D5.)* | §11.6 + §17.3; DEC-117; BR-S-Voucher-6; BR-S-CrossModule-3 | AUTO — Allocation with mix of issued + unsold capacity; emit `AllocationRecallTriggered`; assert ISSUED+ Vouchers unchanged; assert unsold capacity reflected on Module A side |
| **AC-S-J-18** | **Voucher EXPIRED (DEC-103)**: bound `Allocation.expiry_date` reached for a Voucher not yet REDEMPTION_REQUESTED / SHIPPED / CONSUMED / VOIDED → scheduled job fires `VoucherExpired`; customer-fault default = no refund; `qty − issued` does NOT restore (slot consumed). *(GIFTED removed from the exclusion set at launch — D5.)* | §11.4 + §11.7; DEC-103; BR-S-Voucher-4 | AUTO — set Allocation expiry past; advance clock; assert `VoucherExpired` + no refund + Allocation counter unchanged |
| **AC-S-J-19** | **Manual Voucher substitution (DEC-104)**: operator opens the Admin Panel, picks an original Voucher + substitute **Product Reference** *(naming cascade; wine-display alias Bottle Reference)*, captures `customer_consent_mode ∈ {refund, credit, silent}` (DEC-104 Stage-6.5) + records the substitution; `VoucherSubstitutionExecuted` fires with original ref, substitute ref, reason, operator id, consent mode; original Voucher VOIDED; substitute issued. | §11.5 + §11.7; DEC-104; BR-S-Voucher-5 | AUTO — drive substitution via Admin Panel; assert payload carries all five attributes incl. consent mode; verify no automated substitute-matching engine at launch |
| **AC-S-J-20** | **Hero Package three-gate happy path (DEC-114) — club VP**: the joining Hero Package fee is **captured at producer approval** against the charge-on-approval mandate taken at application (Module K §4.2.1; MVP-DEC-016) — not a separate customer cart-submit; gate 1 (Profile state — the Profile being approved) + gate 2 (single-per-club-year) + gate 3 (Capacity Invariant: `count(seat-occupying = Active + Suspended) ≤ Allocation.qty`, Module K §13 reading Module A `qty`; MVP-DEC-017) pass at that moment; `HeroPackagePurchaseAccepted` + `OrderConfirmed` + `InvoiceINV1Issued` (no INV0) + N `VoucherIssued` + `MembershipFeePaid` (→ Module K) fire from the same transaction — **approval = charge = activation, atomically**. | §5.1-§5.3; DEC-114; BR-S-Gate-3; BR-S-Invoice-4; MVP-DEC-016/017 | AUTO — drive the approval-triggered charge; assert all events; assert a failed charge consumes no seat + does not activate; assert Module K receives `MembershipFeePaid` → Profile activation downstream |
| **AC-S-J-21** | **Hero Package gate failures**: three negative paths — (1) Lapsed Profile outside the 30-day grace; (2) re-purchase within the same club year; (3) Capacity Invariant violated (seat-occupying `Active` + `Suspended` Profiles at ceiling — MVP-DEC-017). Each emits `HeroPackagePurchaseRejected` (reason `profile_state_invalid | single_per_year_violated | capacity_invariant_violated`); on a joining approval the charge does not fire and no seat is consumed; the Order does not transition CONFIRMED for that line. | §5.1 + §10.2; DEC-114; BR-S-Gate-3 | AUTO — three parametrised negative paths; assert correct reason per case + no charge taken + Order state untouched if HP-only, or non-HP lines proceed if mixed |
| **AC-S-J-22** | **End-to-end demo session**: Paolo observes the dev team walking through Offer publication (club + single-producer Discovery), card checkout, bank-transfer checkout with the 7-day window, Hero Package purchase, Voucher redemption + INV2 with mid-semester storage roll-in, INV3 semester-end issuance, 14-day cancellation + refund + OC share reversal (manual-first cause recording), and producer recall coordination showing ISSUED-Voucher immunity — exercised from the consumer storefront (self-serve) + the Admin-Panel operator surface. *(Composite + gifting not-exercised-at-launch.)* | §0–§17 (full launch surface) | HUMAN — single session, ~120 min, with dev + commercial-ops teams; Paolo signs off on observed behaviour against this document |
| **AC-S-J-23** | **MIXED — Hero Package end-to-end event-sequence trace**: AI compiles the complete trace for a joining Hero Package incl. (a) sanctions/Hold gate; (b) the three Hero gates; (c) payment capture **at producer approval against the at-application charge-on-approval mandate** (MVP-DEC-016 — approval = charge = activation); (d) all events from the order-completion transaction in causal order; (e) Module K consumer effects on `MembershipFeePaid` (Profile activation + Club Credit auto-generation, Module K §11.1); Paolo confirms causal ordering + cross-module coherence. | §16.12 + §17.7; DEC-114; MVP-DEC-016 | MIXED — AI assembles the trace; Paolo confirms the operational-coherence judgment call |

---

## §3 State machine round-trips — entity FSMs

Module S owns five FSMs: Offer (6 states), Order (12 states), Voucher (**7 states at launch** — GIFTED deferred with D5), Cart Hold lifecycle, Cancellation flow.

### §3.1 Offer FSM (DEC-095 + §4.2)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-FSM-1** | Offer traverses `DRAFT → REVIEWED → SUBMITTED → ACTIVE` (forward-only); `OfferCreated` / `OfferReviewed` / `OfferSubmitted` / `OfferActivated` fire. | §4.2 + §16.1; BR-S-Offer-2 | AUTO |
| **AC-S-FSM-2** | SUBMITTED → DRAFT is the **only** backward transition, fired on `OfferPublicationValidationFailed` (DEC-098); the Offer does not transition backwards through REVIEWED. | §4.2; DEC-098; BR-S-Offer-2 | AUTO — drive validation failure (rule 1); assert direct SUBMITTED → DRAFT + reason payload |
| **AC-S-FSM-3** | `ACTIVE ↔ PAUSED` is the only bidirectional transition; `OfferPaused` / `OfferActivated` fire. | §4.2; BR-S-Offer-2 | AUTO |
| **AC-S-FSM-4** | `ACTIVE → CLOSED` and `PAUSED → CLOSED` admissible; CLOSED is terminal; a CLOSED Offer cannot reactivate (pattern: new Offer on the same Allocation). | §4.2; BR-S-Offer-2 | AUTO — attempt CLOSED → ACTIVE; assert rejection |
| **AC-S-FSM-5** | Layer 3 `commercial_unbreakable` and `is_hero_package` are set at Offer creation and **immutable once ACTIVE** (BR-S-Offer-3 + BR-S-Offer-4 + DEC-098 rule 5). | §4.2 + §15.1 + §18.2; DEC-098; BR-S-Offer-3; BR-S-Offer-4 | AUTO — attempt to mutate both on an ACTIVE Offer; assert rejection each |
| **AC-S-FSM-6** | **Multi-producer Discovery composite Offer FSM cascade** *(NOT-EXERCISED-AT-LAUNCH → roadmap; D7 deferred)*: when Module A emits `AllocationClosed` for any constituent, Module S forces the composite Offer ACTIVE → PAUSED automatically in the same transaction. Verified when the composite restores. *(At launch the single-Allocation cascade — AC-S-FSM-7 / AC-S-XM-11 — is exercised; a single-Allocation Offer's backing Allocation close retires the Offer.)* | §4.2 + §6.3; DEC-097; BR-S-Offer-5 | AUTO (deferred) — when the composite restores, set up a composite + close one constituent; assert atomic `OfferPaused` + reason referencing upstream `AllocationClosed` |
| **AC-S-FSM-7** | In-flight Vouchers issued before Offer CLOSED continue their own lifecycle unaffected; CLOSED is operational-windup for new sales only. | §4.2; BR-S-Offer-2 | AUTO — issue voucher on ACTIVE Offer; close Offer; verify voucher operable through downstream flow |

### §3.2 Order FSM (DEC-101 + §9.1)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-FSM-8** | Order FSM has **12 states** (v17 §5.6, DEC-101): PENDING_PAYMENT, PAYMENT_CONFIRMED, CONFIRMED, FULFILLMENT_STARTED, PARTIALLY_FULFILLED, FULFILLED, HOLD_PLACED, AMENDMENT_REQUESTED, AMENDMENT_APPROVED, AMENDMENT_REJECTED, CANCELLED, + the initial entry state. | §9.1; DEC-101; BR-S-Order-1 | AUTO — schema-level: assert all 12; assert no v17 active-consignment / CruTrade / B2B credit-terms states |
| **AC-S-FSM-9** | **PENDING_PAYMENT IS the bank-transfer 7-day credit-terms state**; card payments do NOT pass through it (auth + capture one step → PAYMENT_CONFIRMED). | §9.1 + §9.3; DEC-101; BR-S-Order-2 | AUTO — card flow: assert no PENDING_PAYMENT; bank-transfer: assert pass-through |
| **AC-S-FSM-10** | Order PENDING_PAYMENT → CANCELLED on 7-day timeout; voucher VOIDS without INV1 (DEC-101 + BR-S-Order-3). | §9.2; DEC-101; BR-S-Order-3 | AUTO — covered by AC-S-J-6 |
| **AC-S-FSM-11** | PAYMENT_CONFIRMED → CONFIRMED gated on: (a) sanctions/Hold re-check (DEC-113); (b) Hero three-gate (DEC-114, if applicable); (c) INV1 (DEC-107); (d) OC accrual (DEC-112, Discovery); (e) Voucher → ISSUED (DEC-102). | §9.2 + §10.6 + §10.8; DEC-113/114/107/112 | AUTO — drive the transition with each gate independently failing + passing; assert correct event family per case |
| **AC-S-FSM-12** | CONFIRMED → FULFILLMENT_STARTED on first shipment request (Voucher ISSUED → REDEMPTION_REQUESTED); first-ship → PARTIALLY_FULFILLED; all-terminal → FULFILLED. | §9.2 + §11.3; DEC-101 | AUTO — three-voucher Order; redeem 1 → FULFILLMENT_STARTED; ship → PARTIALLY_FULFILLED; remainder → FULFILLED |
| **AC-S-FSM-13** | CONFIRMED / PAYMENT_CONFIRMED → HOLD_PLACED on a Module K Hold (**any type** — Module K §4.8) post-CONFIRMED; HOLD_PLACED → CONFIRMED / FULFILLMENT_STARTED on Hold lift. | §9.1 + §9.2 + §17.2; Module K §4.8; BR-S-Gate-2 | AUTO — emit `CustomerHoldPlaced` on confirmed Order; assert HOLD_PLACED; emit `CustomerHoldLifted`; assert resumption |
| **AC-S-FSM-14** | Amendment loop: AMENDMENT_REQUESTED → AMENDMENT_APPROVED / AMENDMENT_REJECTED; either resolves back to the prior state. | §9.1 + §9.2 | AUTO |
| **AC-S-FSM-15** | Order CANCELLED admissible from CONFIRMED / PARTIALLY_FULFILLED **only pre-shipment within the 14-day window** (DEC-108); per-voucher partial cancellation (DEC-109) keeps the Order in PARTIALLY_FULFILLED / CONFIRMED until all Vouchers terminal. | §9.2 + §12.1; DEC-108; BR-S-Cancellation-1 | AUTO — covered by AC-S-J-13; verify partial path (cancel 1 of 3) does not transition the whole Order to CANCELLED |

### §3.3 Voucher FSM (DEC-102 + DEC-103 + DEC-109 + §11.3) — **7 states at launch (GIFTED deferred, D5)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-FSM-16** | **Voucher FSM has 7 states at launch** (DEC-102; GIFTED deferred with D5): PENDING_PAYMENT, ISSUED, REDEMPTION_REQUESTED, SHIPPED, CONSUMED (terminal), VOIDED (terminal), EXPIRED (terminal). *(v1.1's 8th state GIFTED — transfer-pending — is deferred; the Voucher's mutable customer-reference is the re-introduction seam.)* | §11.3; DEC-102; BR-S-Voucher-2 | AUTO — schema-level inspection of the FSM enum; assert exactly 7 states; assert GIFTED absent at launch (re-introducible) |
| **AC-S-FSM-17** | Voucher happy-path: PENDING_PAYMENT (bank-transfer only) → ISSUED → REDEMPTION_REQUESTED → SHIPPED → CONSUMED; `VoucherIssued` / `VoucherRedemptionRequested` / `VoucherShipped` / `VoucherConsumed` fire. | §11.3 + §11.7; DEC-102 | AUTO |
| **AC-S-FSM-18** | Voucher in PENDING_PAYMENT is **non-shippable** (Module C gates on state ≥ ISSUED; BR-S-Voucher-3 + DEC-102). | §11.3; DEC-102; BR-S-Voucher-3 | AUTO — attempt redemption-request on a PENDING_PAYMENT voucher; assert rejection |
| **AC-S-FSM-19** | Voucher PENDING_PAYMENT → VOIDED on bank-transfer 7-day timeout (`bank_transfer_timeout`); **no INV1**. | §11.3 + §11.7; DEC-101; BR-S-Order-3 | AUTO — covered by AC-S-J-6 |
| **AC-S-FSM-20** | Voucher ISSUED → VOIDED via: (a) 14-day pre-shipment cancellation (DEC-108); (b) producer-fault / NewCo-fault / carrier-damage refund (DEC-025 matrix — **manual-first decisioning at launch, D6**); (c) substitution (DEC-104). Reason payload on `VoucherVoided` identifies the path. | §11.3 + §11.7 + §12.4 + §12.5; DEC-108; DEC-104 | AUTO — drive each of the three paths; assert reason payload correctness |
| **AC-S-FSM-21** | Voucher REDEMPTION_REQUESTED → VOIDED admissible only within the 14-day pre-shipment window (BR-S-Cancellation-1/2); the Voucher has not shipped, so the WAIVER has not triggered. | §11.3 + §12.1; DEC-108 | AUTO — drive inside window; drive on a shipped voucher and assert rejection |
| **AC-S-FSM-22** | Voucher SHIPPED → CONSUMED on best-effort delivery confirmation; SHIPPED → VOIDED admissible **only via supervisor override** (DEC-108 exceptional path; `SupervisorOverridePostDeliveryRefund`). | §11.3 + §12.3; DEC-108; BR-S-Cancellation-5 | AUTO — drive happy CONSUMED; drive override; assert event presence + auditability |
| **AC-S-FSM-23** | Voucher EXPIRED triggers from a scheduled job on bound `Allocation.expiry_date` for Vouchers in non-terminal states (not REDEMPTION_REQUESTED / SHIPPED / CONSUMED / VOIDED) (DEC-103); `Allocation.expiry_date` optional (default null = no expiry, persists indefinitely; default horizon 10–20yr). *(GIFTED removed from the exclusion set at launch — D5; AMB-S-3 framing tension is an authoring backlog item, §0.6.)* | §11.4; DEC-103; BR-S-Voucher-4 | AUTO — covered by AC-S-J-18; verify default-null persists indefinitely |
| **AC-S-FSM-24** | Voucher GIFTED transfer-pending state *(NOT-EXERCISED-AT-LAUNCH → roadmap; D5 deferred — Voucher FSM 8→7)*: on recipient accept → ISSUED on recipient's cellar; on decline / 7-day expiry → ISSUED on the original giver's cellar (DEC-116). Verified when gifting restores. | §11.3 + §13.2; DEC-116 | AUTO (deferred) — covered by AC-S-J-15 + AC-S-J-16 (both deferred) |
| **AC-S-FSM-25** | **1-voucher-per-bottle invariant — FLOOR** (DEC-109): every Voucher binds exactly one **Product Reference**; a 12-bottle case → 12 Vouchers; a Hero Package → N; a single bottle Offer → 1. Each Voucher independently redeemable / voidable *(giftable post-launch — D5)*. | §11.1; DEC-109; BR-S-Voucher-1 | AUTO — drive the cases; assert Voucher count + per-Voucher independence (redeem 1 of 12 + verify other 11 in ISSUED) |
| **AC-S-FSM-26** | Voucher state observability for producer recall: per the DEC-117 per-state matrix, ISSUED / REDEMPTION_REQUESTED / SHIPPED / CONSUMED Vouchers are NOT void-targets on `AllocationRecallTriggered`; PENDING_PAYMENT-pre-INV1 is operator-reviewed; VOIDED / EXPIRED terminal-no-effect. *(GIFTED removed from the matrix at launch — D5.)* | §11.6; DEC-117; BR-S-Voucher-6 | AUTO — covered by AC-S-J-17; verify the per-state matrix exhaustively (one Voucher per launch state) |

### §3.4 Cart Hold lifecycle (DEC-105 + DEC-106 + §8.5)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-FSM-27** | Cart Hold lifecycle: `CartHoldCreated` → `CartHoldExtended` (optional, bank-transfer) → either `CartHoldExpired` OR `CartHoldConvertedToOrder`. Each fires exactly once per Hold instance. | §8.5; DEC-105; DEC-106; DEC-049 | AUTO — drive expiry / extended-then-converted / direct-conversion; assert event counts |
| **AC-S-FSM-28** | Bank-transfer extension is **the only** payment-method-conditional override (DEC-049 + BR-S-CartHold-3); switching back to card before submit collapses the extension to the original 15-min window. | §8.3; DEC-049; BR-S-CartHold-3 | AUTO — drive bank-transfer then revert to card; assert hold timer reverts (not extended) |

### §3.5 Cancellation flow (DEC-108 + DEC-109 + §12)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-FSM-29** | Cancellation flow: customer/operator initiates on a Voucher within window → eligibility check (state ≠ SHIPPED / CONSUMED / VOIDED / EXPIRED; timer within 14 days of INV1) → `VoucherVoided` + `InvoiceINV1PartialRefundIssued` + (Discovery) `DiscoveryRevenueShareReversed` + (storage past 12-month-free) `StorageFeeProRataRefundIssued` per the cause-conditional rule (DEC-046 — **cause decisioning manual-first at launch, D6**). | §12.4 + §12.6 + §12.7; DEC-108; DEC-109; DEC-046 | AUTO — drive cancellation with all four conditions met; assert all 4 events from the same transaction |
| **AC-S-FSM-30** | Post-shipment supervisor override (§12.3): rare exceptional post-shipment cancellation requires `SupervisorOverridePostDeliveryRefund` (supervisor identity + reason + amount + Voucher ref); the override emits even when the standard cancellation path is rejected; default routing is Module C returns + replacement. | §12.3 + §12.7; DEC-108; BR-S-Cancellation-5 | AUTO — drive override + non-override; assert audit-trail differentiation |

---

## §4 Business rule enforcement (16 domains)

One criterion per business rule in PRD §18; each BR statement is restated verbatim inline (DEC-074). Cross-references where a BR duplicates FSM / journey behaviour. *(Where the PRD §18 BR was reconciled for the MVP — BR-S-CrossModule-4 [R2], BR-S-Voucher-2 [7-state], the deferred gifting/composite BRs — the AC carries the reconciled text + an annotation.)*

### §4.1 Identity and uniqueness (BR-S-Identity-1..4)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-Identity-1** | Every Offer / Cart / Order / Voucher row carries a unique opaque identifier; no business attributes form the identifier. | AUTO — schema-level inspection of all four entity types |
| **AC-S-BR-Identity-2** | Every Offer references at least one bound Allocation (single FK at launch; the multi-FK multi-producer Discovery composite form defers with D7 — §6). | AUTO — schema inspection; negative: zero-allocation Offer rejected |
| **AC-S-BR-Identity-3** | Every Voucher references exactly one **Product Reference** *(wine-display alias Bottle Reference)* (DEC-109 1-voucher-per-bottle) + exactly one bound Allocation. | AUTO — schema inspection; 1-voucher-per-bottle covered by AC-S-FSM-25 |
| **AC-S-BR-Identity-4** | Every Order references at least one Voucher (a 12-bottle case → 12; a Hero Package → N). | AUTO — schema inspection across the canonical Order shapes; covered for count by AC-S-FSM-25 |

### §4.2 Offer entity and FSM (BR-S-Offer-1..6)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-Offer-1** | Offer is a separate first-class entity (DEC-095); cardinality N:1 (multi-Offer-per-Allocation, DEC-099). *(The N:M multi-producer Discovery composite form defers with D7 — §6.)* | AUTO — covered by AC-S-J-2 *(composite by AC-S-J-3, deferred)* |
| **AC-S-BR-Offer-2** | Offer FSM DRAFT → REVIEWED → SUBMITTED → ACTIVE forward-only; SUBMITTED → DRAFT the only backward; ACTIVE ↔ PAUSED bidirectional; ACTIVE/PAUSED → CLOSED; CLOSED terminal. | AUTO — covered by AC-S-FSM-1..4 |
| **AC-S-BR-Offer-3** | `Offer.commercial_unbreakable` (Layer 3) set at creation, immutable once ACTIVE (DEC-098 rule 5 + v17 §5.2). | AUTO — covered by AC-S-FSM-5 |
| **AC-S-BR-Offer-4** | `Offer.is_hero_package` set at creation, immutable once ACTIVE (v17 §5.2). | AUTO — covered by AC-S-FSM-5 |
| **AC-S-BR-Offer-5** | *(DEFERRED with D7 — §6.)* Any constituent Allocation transitioning ACTIVE → CLOSED on a multi-producer Discovery composite forces the composite to PAUSED (DEC-097). | AUTO (deferred) — covered by AC-S-FSM-6 (deferred) |
| **AC-S-BR-Offer-6** | Per DEC-110 + v17 §5.14 — only one Coupon may apply to a given Order; multiple coupons per Cart not admitted. | AUTO — attempt two coupons; assert rejection |

### §4.3 Publication validation (BR-S-Publication-1..6)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-Publication-1** | Allocation state ACTIVE — every bound Allocation must be `ACTIVE` at publication (DEC-098 rule 1). | AUTO — negative: DRAFT/CLOSED/RETIRED Allocation → reason `allocation_state_not_active` |
| **AC-S-BR-Publication-2** | Visibility match strict — `CLUB ↔ CLUB_ONLY`; `DISCOVERY ↔ DISCOVERY_ONLY` (DEC-098 rule 2 + DEC-076). | AUTO — negative: cross-surface → `visibility_mismatch` |
| **AC-S-BR-Publication-3** | Serialization alignment — `serialization_type` admissible by `non_serialized_offer_admitted` + sub-pool (DEC-098 rule 3 + DEC-080). | AUTO — negative: NON_SERIALIZED Offer on `non_serialized_offer_admitted = FALSE` → `serialization_misaligned` |
| **AC-S-BR-Publication-4** | Commercial_terms.value populated — bound Allocation's `commercial_terms.value` non-null (DEC-098 rule 4 + DEC-092). | AUTO — negative: null value → `commercial_terms_value_null` |
| **AC-S-BR-Publication-5** | Layer 3 cannot downgrade Layer 2 (DEC-098 rule 5 + Module 0 §7.4); operator-override path emits `OfferLayer2OverrideRecorded` with reason + actor. | AUTO — negative: Layer 3 breakable on Layer-2 non-breakable → `layer_3_downgrade_attempt`; verify override path emits `OfferLayer2OverrideRecorded` |
| **AC-S-BR-Publication-6** | *(DEFERRED with D7 — §6/§7.2.)* All N constituent Allocations must satisfy BR-S-Publication-1..5 for composite publication (DEC-097). | AUTO (deferred) — parametrised 5 rules × 3-constituent composite when the composite restores |

### §4.4 Cart Hold (BR-S-CartHold-1..4)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-CartHold-1** | 15-min default, system-wide (DEC-105; configurable system-wide, NOT per-Offer). | AUTO — config inspection: single global 15-min, no per-Offer override |
| **AC-S-BR-CartHold-2** | Customer interaction does NOT reset the timer (DEC-106). | AUTO — covered by AC-S-J-4 |
| **AC-S-BR-CartHold-3** | Bank-transfer 7-day extension is the only payment-method-conditional override (DEC-049 + DEC-101). | AUTO — covered by AC-S-J-5 + AC-S-FSM-28 |
| **AC-S-BR-CartHold-4** | Cart contents persist 48h (cart session); Allocation reservations release at 15 min unless re-acquired (v17 §5.7). | AUTO — Cart-add → 16-min wait → line exists, hold released; → 49h → Cart cleared |

### §4.5 Order FSM (BR-S-Order-1..4)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-Order-1** | 12-state inheritance (DEC-101 + v17 §5.6) with NewCo simplifications (B2B credit-terms deferred DEC-068; active-consignment dropped BMD §13; CruTrade dropped BMD §4.4). | AUTO — covered by AC-S-FSM-8 |
| **AC-S-BR-Order-2** | PENDING_PAYMENT IS bank-transfer credit-terms; cards do NOT pass through it; B2B credit-terms deferred (DEC-068). | AUTO — covered by AC-S-FSM-9 |
| **AC-S-BR-Order-3** | Bank-transfer 7-day window auto-VOID on timeout (DEC-101); no INV1, no financial event. | AUTO — covered by AC-S-J-6 + AC-S-FSM-10 + AC-S-FSM-19 |
| **AC-S-BR-Order-4** | Single-transaction across club + Discovery + cart (BMD §4.7 + DEC-101); one INV1 covers all lines; each settles per its own mechanic (DEC-100 + DEC-110). | AUTO — covered by AC-S-J-8 |

### §4.6 Sanctions / Hold gate (BR-S-Gate-1..3) **(FLOOR)**

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-Gate-1** | Sanctions gate pre-PaymentAuthorization (DEC-113 + Module K §9.3) — non-`passed` `Customer.sanctions_status` blocks order completion before card auth. *(THE consumer-side enforcement point; Module K + Module A sanctions-blind — Module K exposes the read-API tuple, Module S enforces.)* | AUTO — negative: `pending` / `failed` / `under_review` attempts checkout; assert `OrderBlockedBySanctionsGate` + no `OrderPaymentAuthorized` / bank-transfer instructions |
| **AC-S-BR-Gate-2** | Hold gate pre-PaymentAuthorization — any active Hold (**any type** — Module K §4.8) on Customer or Profile blocks at the same gate. | AUTO — parametrised negative paths, one per Module K Hold type (the six base + the two finance-driven `CHARGEBACK_REVIEW` / `STORAGE_PAYMENT_FAILED`); assert `OrderBlockedByHoldGate` + blocking Hold(s) in payload |
| **AC-S-BR-Gate-3** | Hero Package three-gate eligibility (DEC-114) — Profile state + single-per-club-year + Capacity Invariant all pass at order completion. | AUTO — covered by AC-S-J-20 + AC-S-J-21 |

### §4.7 Stacking algebra (BR-S-Stacking-1..4)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-Stacking-1** | v17 §5.14 7-step chain (DEC-110): base → policy discounts → Club Credit → promo/coupons → volume/early-bird → FX → final. **KEPT as the spine.** *(The policy-discount [step 2] + volume/early-bird-multiplier [step 5] campaign sophistication is not-configured-at-launch — D8; the steps remain as no-op seams.)* | AUTO — drive a launch-active scenario (base + Club Credit + promo + FX); assert chain order + per-step trace; assert step 2 / step 5 are no-op at launch (verified when a campaign lands) |
| **AC-S-BR-Stacking-2** | Mutual-exclusivity matrix (DEC-110): promo + Club Credit ME; REFUND_COMPENSATION + promo ME; REFUND_COMPENSATION + Club Credit ALLOWED. One coupon per checkout. | AUTO — parametrised exclusivity tests for all four combinations + multi-coupon rejection |
| **AC-S-BR-Stacking-3** | OC share on headline `P_d` — 5% × `P_d` computed on **headline `P_d`** (NOT post-stacking net) (DEC-110 + BMD §8.14). | AUTO — Discovery sale with promo applied; assert `DiscoveryRevenueShareAccrued.amount = 5% × headline P_d` |
| **AC-S-BR-Stacking-4** | FX captured at order confirmation, immutable (Q-AD-11 + v17 §5.11); refunds use the same captured rate (BMD §4.8). | AUTO — non-EUR Order; alter FX between cart and confirmation; assert confirmation snapshot persists; assert refund uses the same captured rate |

### §4.8 Club Credit auto-apply (BR-S-ClubCredit-1..4) — club VP (KEPT)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-ClubCredit-1** | Auto-apply at checkout-render — Club Credit auto-applies when the cart has ≥1 eligible line (Module K strict `credit.profile.club_id ∈ offer.club_ids`) up to `min(credit.balance, eligible line totals)`. *(K.17 carry-forward — the Remaining balance — KEPT, now exercised at launch; Module K §11.)* | AUTO — €100 Credit; cart €60 eligible + €40 non-eligible; assert auto-apply €60 with `ClubCreditAutoApplied` |
| **AC-S-BR-ClubCredit-2** | Customer can remove — explicit action removes the auto-applied credit; voluntary; balance stays full on the Profile after removal. | AUTO — drive auto-apply then removal; assert `ClubCreditRemovedByCustomer` + balance restored |
| **AC-S-BR-ClubCredit-3** | No cross-Club pooling — each Profile's credit applies only to that Profile's Club's eligible lines (strict match). | AUTO — two Profiles (Club A, Club B) each with Credit; cart with one line each; assert per-Profile application only |
| **AC-S-BR-ClubCredit-4** | Hero Package exclusion — Hero Offers (`is_hero_package = true`) scope-excluded from the auto-apply pool (DEC-043 + DEC-110 + DEC-114). | AUTO — Credit + Hero + non-Hero club line; assert auto-apply lands on the non-Hero line only, Hero paid in cash |

### §4.9 INV1 / INV2 / INV3 emission (BR-S-Invoice-1..7) **(FLOOR — tax)**

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-Invoice-1** | `InvoiceINV1Issued` at order confirmation post-payment-cleared (DEC-107 + DEC-112). Card: at completion. Bank-transfer: at funds-cleared. | AUTO — covered by AC-S-J-5 + AC-S-J-7 |
| **AC-S-BR-Invoice-2** | MPV VAT regime — no excise / no destination VAT on INV1 (BMD §8.7); VAT recognised at INV2. | AUTO — inspect INV1 payload (no excise/VAT fields); inspect INV2 (present at shipment) |
| **AC-S-BR-Invoice-3** | `InvoiceINV2Issued` at shipment with mid-semester storage roll-in (DEC-107 + DEC-119) — Module S internally computes unbilled storage months + adds INV2 line items in the same transaction (no cross-module query — §14.4). | AUTO — covered by AC-S-J-12 |
| **AC-S-BR-Invoice-4** | Hero Package: one INV1, N `VoucherIssued`, INV2 per shipped constituent (DEC-107). | AUTO — Hero (N=12); assert single INV1 + 12 `VoucherIssued`; redeem 3; assert 3 INV2 referencing the single INV1 |
| **AC-S-BR-Invoice-5** | Ship-on-confirmation — distinct INV1 + INV2 simultaneous (DEC-107; combining REJECTED). | AUTO — drive ship-on-confirmation; assert TWO distinct events from the same transaction |
| **AC-S-BR-Invoice-6** | `InvoiceINV3Issued` at semester-end (DEC-119) — end-June + end-December; aggregates the prior 6 months of `StorageFeeAccrued` per Customer (excluding months rolled into INV2). | AUTO — covered by AC-S-J-11 |
| **AC-S-BR-Invoice-7** | One customer-facing invoice per bottle's storage months (DEC-118 + DEC-119) — INV3 if in custody through semester-end; INV2 if it ships; no double-billing. | AUTO — bottle A in cellar 2 semesters (2 INV3s); bottle B ships mid-semester-1 (1 INV2 with all months rolled in, 0 INV3 lines) |

### §4.10 Storage-fee accrual (BR-S-Storage-1..10)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-Storage-1** | Module S owns storage-fee computation + INV3 issuance + per-bottle accrual events (DEC-119; supersedes DEC-118's ownership clause; mechanics preserved). | AUTO — `StorageFeeAccrued`, `InvoiceINV3Issued`, `StorageFeeProRataRefundIssued` all Module-S-emitted; Module E consumes but does not emit; matches the DEC-119 three-actor split (S EVENT, Xero ARTIFACT, E PAYMENT + RECORD + ROUTING) |
| **AC-S-BR-Storage-2** | Storage-clock-start trigger — `storage_accrual_start_date = max(INV1 + 12 months, InboundEventPhysicallyAccepted)`; both conditions must be satisfied before accrual. | AUTO — parametrised V2 / V1 fast-ship / V1 slow-ship / *(Direct Purchase arms idle — deferred)*; assert correct `storage_accrual_start_date` per case |
| **AC-S-BR-Storage-3** | Rate — €3/bottle/year = €0.25/bottle/month (DEC-118); configurable per Finance config; read at compute time. | AUTO — assert `accrued_amount = 0.25` per default; alter config; assert recomputation |
| **AC-S-BR-Storage-4** | Partial-month rounding — any partial month counts as a full month (DEC-118). | AUTO — `storage_accrual_start_date` mid-month; assert first month with `partial_month_rounding_flag = TRUE` at full €0.25 |
| **AC-S-BR-Storage-5** | `StorageFeeAccrued` emission — monthly per Voucher (§16.10); stops on REDEMPTION_REQUESTED / SHIPPED / VOIDED / EXPIRED *(GIFTED-accepted deferred with D5)*. | AUTO — drive each terminal-from-storage transition; assert no further `StorageFeeAccrued` post-transition |
| **AC-S-BR-Storage-6** | Semi-annual INV3 cadence — end-June + end-December (DEC-118); covers the prior 6-month period. | AUTO — covered by AC-S-J-11; verify mid-semester boundary days (June 30 / Dec 31) inclusive (§14.4) |
| **AC-S-BR-Storage-7** | Mid-semester INV2 storage roll-in — Module S includes unbilled storage months as INV2 line items (Module-S-internal; no cross-module query — DEC-119). | AUTO — covered by AC-S-J-12 |
| **AC-S-BR-Storage-8** | One customer-facing invoice per bottle's storage months — INV2 roll-in OR INV3 aggregation, not both. | AUTO — covered by AC-S-BR-Invoice-7 |
| **AC-S-BR-Storage-9** | Storage-fee pro-rata refund cause-conditional (§12.6 + DEC-046; Module-S-internal computation, DEC-119); NewCo-fault → yes; customer-fraud → no. *(The cause decisioning is manual-first at launch — D6; the producer-fault clawback netting defers-with-settlement, D19.)* | AUTO — parametrised: NewCo-fault (yes), producer-fault (yes — netting verified when E settlement lands), carrier-damage (yes), customer-cancellation-pre-shipment (bottle-cost-only, E6-44), customer-fraud (no); assert `StorageFeeProRataRefundIssued` per cause |
| **AC-S-BR-Storage-10** | Module D `InboundEventPhysicallyAccepted` cross-module read — the single storage cross-module read at launch (§17.4 + §14.7). **R2 — Module-S-internal; no bidirectional S↔E.** | AUTO — schema-level: confirm single subscription to `InboundEventPhysicallyAccepted`; no other cross-module call in the storage-fee path |

### §4.11 OC share emission (BR-S-OCShare-1..5) — emission KEPT; computation deferred (D19)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-OCShare-1** | `DiscoveryRevenueShareAccrued` at INV1 = post-payment-cleared (DEC-112). Card: at completion; bank-transfer: at funds-cleared. | AUTO — covered by AC-S-J-5 + AC-S-J-7; assert event timing matches INV1 |
| **AC-S-BR-OCShare-2** | Read-at-emission (DEC-066) — payload reads the Customer's Originating Club link (`originating_club_id`) → resolves to the Club's operating-Producer; null-OC payload (DEC-040) records null recipient. | AUTO — happy (OC populated) + null-OC; assert correct payload; assert no caching (OC changed post-cart-add but pre-INV1 reads up-to-date) |
| **AC-S-BR-OCShare-3** | *(DEFERRED with D7 — §6.)* 5% × headline `P_d` on a composite Offer (DEC-097 + DEC-110 + DEC-112) — computed once on the composite `P_d`, not per-constituent. *(Single-Allocation Discovery OC emission — AC-S-BR-OCShare-1/2 — is KEPT and exercised at launch.)* | AUTO (deferred) — when the composite restores, drive a 3-constituent sale; assert one `DiscoveryRevenueShareAccrued` on the composite `P_d` |
| **AC-S-BR-OCShare-4** | Cancellation reversal proportional to vouchers (DEC-108 + DEC-109 + DEC-112) — 14-day pre-shipment cancellation reverses the OC share proportionally to cancelled vouchers. | AUTO — 6-voucher Discovery Order, 2 cancelled in window; assert `DiscoveryRevenueShareReversed` for 2/6; remaining 4 unaffected |
| **AC-S-BR-OCShare-5** | *(DEFERRED with D5 — §13.)* Gifting preservation (DEC-116) — the giver's locked OC reference preserved on the gifted Voucher. *(The seam is the kept Voucher `originating_club_id`; AC-S-MVP-2.)* | AUTO (deferred) — covered by AC-S-J-15 (deferred) |

### §4.12 Voucher state machine (BR-S-Voucher-1..7)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-Voucher-1** | 1-voucher-per-bottle (DEC-109) — vouchers bottle-granular. **FLOOR.** | AUTO — covered by AC-S-FSM-25 |
| **AC-S-BR-Voucher-2** | **7-state machine at launch** (DEC-102; GIFTED deferred with D5): PENDING_PAYMENT → ISSUED → REDEMPTION_REQUESTED → SHIPPED → CONSUMED + VOIDED / EXPIRED. *(Reconciled from v1.1's 8-state — GIFTED is the deferred 8th state, §0.2.)* | AUTO — covered by AC-S-FSM-16 + AC-S-FSM-17 |
| **AC-S-BR-Voucher-3** | PENDING_PAYMENT non-shippable (DEC-102 + DEC-101); Module C gates on state ≥ ISSUED. | AUTO — covered by AC-S-FSM-18 |
| **AC-S-BR-Voucher-4** | EXPIRED trigger (DEC-103) — scheduled job on `Allocation.expiry_date` for non-terminal Vouchers; `expiry_date` optional (default null). | AUTO — covered by AC-S-FSM-23 |
| **AC-S-BR-Voucher-5** | Substitution manual at launch (DEC-104) — Admin Panel `VoucherSubstitutionExecuted`; full automation deferred. | AUTO — covered by AC-S-J-19; verify absence of an automated substitute-matching API at launch |
| **AC-S-BR-Voucher-6** | Recall scope unsold-only; ISSUED immune (DEC-117). | AUTO — covered by AC-S-J-17 + AC-S-FSM-26 |
| **AC-S-BR-Voucher-7** | *(DEFERRED with D5 — §13.)* Terminal Vouchers not transferable (DEC-116) — the gifting rule; retained for when gifting restores. | AUTO (deferred) — negative-path gift attempts when gifting restores |

### §4.13 Cancellation and refund (BR-S-Cancellation-1..6) — legal floor KEPT; D6 matrix manual-first

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-Cancellation-1** | 14-day pre-shipment window from INV1 (DEC-108). Card: at completion; bank-transfer: at funds-cleared. **FLOOR.** | AUTO — drive both flows; assert timer from `InvoiceINV1Issued.timestamp` |
| **AC-S-BR-Cancellation-2** | Post-shipment WAIVER (DEC-108) — once REDEMPTION_REQUESTED → SHIPPED, the cancellation right is WAIVED (Article 16). **FLOOR.** | AUTO — covered by AC-S-J-14 |
| **AC-S-BR-Cancellation-3** | Per-voucher partial refund (DEC-109) — cancelling one Voucher voids it + refunds the per-bottle amount; Order → PARTIALLY_FULFILLED. **FLOOR.** | AUTO — covered by AC-S-J-13 |
| **AC-S-BR-Cancellation-4** | Post-delivery issues via Module C returns + replacement, NOT Module S cancellation (DEC-108) — replacement without new Voucher / new INV2; cost recorded as a non-revenue event by Module E. | AUTO — drive post-shipment damage; assert routing to Module C; verify no new Voucher / no new INV2 |
| **AC-S-BR-Cancellation-5** | Exceptional post-delivery refund supervisor override (DEC-108) — auditable `SupervisorOverridePostDeliveryRefund`. | AUTO — covered by AC-S-FSM-30 |
| **AC-S-BR-Cancellation-6** | Storage-fee pro-rata refund cause-conditional (DEC-046 + DEC-118) — NewCo-fault → yes; customer-fraud → no. *(Cause decisioning manual-first at launch — D6.)* | AUTO — covered by AC-S-BR-Storage-9 |

### §4.14 Gifting (BR-S-Gifting-1..4) — **DEFERRED with D5 (§13)**

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-Gifting-1** | *(DEFERRED — D5.)* 7-day accept window (DEC-116 + v17 Module A §12). | AUTO (deferred) — covered by AC-S-J-15 + AC-S-J-16 (deferred); the seam is verified by AC-S-MVP-2 |
| **AC-S-BR-Gifting-2** | *(DEFERRED — D5.)* Recipient gates — registered NewCo Customer + KYC `passed` + Offer-eligibility match (DEC-116). | AUTO (deferred) — recipient-gate negative paths when gifting restores |
| **AC-S-BR-Gifting-3** | *(DEFERRED — D5.)* Originating Club preservation (DEC-116 + BMD §4.13) — giver's `originating_club_id` preserved on the gifted Voucher. | AUTO (deferred) — covered by AC-S-J-15 (deferred); the `originating_club_id` seam is the kept hook (AC-S-MVP-2) |
| **AC-S-BR-Gifting-4** | *(DEFERRED — D5.)* No financial event — gifting is a non-revenue transfer; original INV1 stands; Allocation lineage preserved (DEC-116). | AUTO (deferred) — no revenue event from the gift transaction when gifting restores |

### §4.15 Producer Portal ↔ Admin Panel parity (BR-S-Parity-1..3) — L-PP (re-scoped to Admin Panel)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-Parity-1** | Club Offers parity-shared — every club Offer-level operation exposable from BOTH Producer Portal (`actor_role: producer`) and NewCo Admin Panel (`actor_role: newco_ops`) (DEC-115). *(At launch, operator-driven via the Admin Panel — the Producer-Portal write UI is deferred per L-PP; the backend parity contract is unchanged.)* | AUTO — inspect the API contract: every club-Offer-operation endpoint accessible from both origins (backend parity); at launch every operation emits `actor_role: newco_ops`; the producer-write-UI half is deferred-with-seam |
| **AC-S-BR-Parity-2** | Discovery Offers NewCo-Admin-Panel-only at launch (DEC-115 + DEC-039 + DEC-097) — no `actor_role: producer` on Discovery Offer events. | AUTO — attempt Discovery authorship from Producer Portal; assert rejection; assert every Discovery Offer event `actor_role: newco_ops` |
| **AC-S-BR-Parity-3** | `actor_role` on every Offer-level event (DEC-115 + DEC-083 audit pattern). | AUTO — inspect all Offer-family events; assert each carries `actor_role ∈ {producer, newco_ops}` |

### §4.16 Cross-module dependency (BR-S-CrossModule-1..7) — **R2 reconciled at BR-S-CrossModule-4**

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-S-BR-CrossModule-1** | Allocation read at Offer creation + publication + voucher issuance (DEC-098 + DEC-099). Module A is the upstream supply primitive. | AUTO — covered by §6 cross-module criteria |
| **AC-S-BR-CrossModule-2** | `VoucherIssued` triggers Module D PI for V1/V2 (the voucher-issuance signal). *(Item F: `VoucherIssued` is also the sell-through PO-title signal; no `SellThroughRecorded`.)* | AUTO — Module-S-side: issue Voucher against V1 / V2; assert `VoucherIssued` payload carries the `sourcing_model` discriminator (Module D PI consumption verified when Module D lands — D §14.4) |
| **AC-S-BR-CrossModule-3** | Voucher state observability for recall (DEC-090 + DEC-117) — recall scope unsold-only; ISSUED immune. | AUTO — covered by AC-S-J-17 |
| **AC-S-BR-CrossModule-4** | **Storage is Module-S-internal — a single Module D → Module S read of `InboundEventPhysicallyAccepted`; no bidirectional Module S ↔ Module E at INV2 (DEC-119 — R2).** *(Reconciled from v1.1's stale DEC-118 "Module E coordination on storage fees — bidirectional contract at INV2." The §14 body + this AC always carried the DEC-119 framing; the MVP PRD §18.16 is now reconciled to match. Naming/contract only — no behaviour change; mirrors Module D's DEC-183 fix.)* | AUTO — inspect the cross-module surface: only direction is Module S `Invoice*Issued` / `StorageFeeAccrued` outbound; no inbound storage-fee event from Module E to Module S |
| **AC-S-BR-CrossModule-5** | Outbound communication per the Module K §14.9.1 purpose split (MVP-DEC-035): Module S's operational comms route through the single ERP email service; HubSpot consumes Module S events for marketing / lifecycle only; Module S never integrates the mail provider directly and never sends marketing email. *(Was "HubSpot owns outbound communication" — reversed by MVP-DEC-035.)* | AUTO — inspect Module S surface; no direct mail-provider / SMS integration (operational sends route through the ERP email service); verify HubSpot subscribes for the lifecycle lane |
| **AC-S-BR-CrossModule-6** | Module K reads — sanctions/Hold/Profile/Originating-Club/Club-Credit (§17.2); Module S does NOT edit Module K state. | AUTO — inspect Module K interactions: all reads; no writes |
| **AC-S-BR-CrossModule-7** | Module 0 reads — Product Reference / Composite SKU / Layer 1 (§17.1); Module S does NOT edit Module 0 state. *(Naming cascade: Bottle Reference → Product Reference.)* | AUTO — inspect Module 0 interactions: all reads; no writes |

---

## §5 Domain event emission and consumption

Module S emits ~40 events across 11 families *(minus the deferred `VoucherGift*` family — D5; minus composite-specific emissions — D7)* + consumes from Module 0 / K / A / D. One criterion per family + key emission patterns.

### §5.1 Offer-family events

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-EVT-1** | Offer lifecycle events — `OfferCreated` / `OfferReviewed` / `OfferSubmitted` / `OfferActivated` / `OfferPaused` / `OfferClosed` / `OfferPublicationValidationFailed` — fire on FSM transitions (§4.2 + §16.1); each carries Offer id, surface, `is_hero_package`, `composite_constituent_allocation_ids[]` *(single-FK at launch — D7 seam)*, Layer 3, granularity, time-window, `actor_role`. | §16.1 | AUTO |
| **AC-S-EVT-2** | `OfferPromotionalPriceSet` / `OfferPromotionalPriceCleared` (DEC-100 + DEC-039); `OfferPromotionalPriceSet` carries promo value, campaign range, producer-opt-in reference for club promos. | §16.1; DEC-100 | AUTO — overlay set; assert opt-in reference for club; for Discovery (NewCo-unilateral) assert no opt-in required |
| **AC-S-EVT-3** | `OfferHeroPackageDesignated` fires when `is_hero_package` FALSE → TRUE on a DRAFT Offer (immutable post-active, BR-S-Offer-4). | §16.1; DEC-096 | AUTO — flag-on; verify post-active mutation rejected |
| **AC-S-EVT-4** | `OfferLayer2OverrideRecorded` fires at publication when the operator deliberately sets Layer 3 breakable on a Layer-2-unbreakable Allocation (§7.1); payload: Offer ref + Allocation ref + Layer-2 value + operator id + reason. | §7.1 (E6-21); DEC-098 | AUTO — drive override with reason capture; assert payload completeness |

### §5.2 Cart-family events

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-EVT-5** | `CartHoldCreated` / `CartHoldExtended` / `CartHoldExpired` / `CartHoldConvertedToOrder` fire on the Cart Hold lifecycle (§8.5 + §16.2). | §16.2; DEC-105; DEC-106 | AUTO — covered by AC-S-FSM-27 |

### §5.3 Order-family events

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-EVT-6** | `OrderPlaced` fires on Cart submit (before the sanctions/Hold gate); carries cart line items, payment method, shipping Address ref, applied discounts. | §16.3 | AUTO |
| **AC-S-EVT-7** | `OrderBlockedBySanctionsGate` / `OrderBlockedByHoldGate` fire when the gates (DEC-113 + Q-AD-22) block; each carries the blocking reason. *(Module S's own gate events — Module K exposes the read-API tuple, §10.1.)* | §10.1 + §10.2 + §16.3; DEC-113 | AUTO — covered by AC-S-BR-Gate-1 + AC-S-BR-Gate-2 |
| **AC-S-EVT-8** | `OrderPaymentAuthorized` (card only); `OrderPaymentCaptured` (card capture / bank-transfer funds-cleared). | §16.3; DEC-101 | AUTO — drive card + bank-transfer; assert correct emission per flow |
| **AC-S-EVT-9** | `OrderPaymentPending` (bank-transfer PENDING_PAYMENT); `OrderPaymentFailed` (auth/capture failure → recovery or CANCELLED). | §16.3; DEC-101 | AUTO |
| **AC-S-EVT-10** | `OrderConfirmed` fires on PAYMENT_CONFIRMED → CONFIRMED; INV1 + OC accrual + Voucher issuance fire alongside (DEC-107 + DEC-112 + DEC-109) from the same transaction. | §16.3; DEC-107 | AUTO — covered by AC-S-J-7 + AC-S-EVT-31 |
| **AC-S-EVT-11** | `OrderCancelled` (rare under per-voucher partial cancellation); `OrderShippedToFulfillment` (first Voucher ships); `OrderRefunded` (partial/full refund). | §16.3 | AUTO |

### §5.4 Voucher-family events — **the three Module-D-owed names verified Module-S-side**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-EVT-12** | Voucher lifecycle events (§11.7) — `VoucherIssued` / `VoucherRedemptionRequested` / `VoucherShipped` / `VoucherConsumed` / `VoucherVoided` / `VoucherExpired` / `VoucherSubstitutionExecuted` — fire on transitions; each carries Voucher ref, Order ref, Customer ref, **Product Reference** ref, bound Allocation ref, INV1 ref. **`VoucherIssued` = the V1/V2 PI auto-fire trigger AND the sell-through PO-title signal (item F — no `SellThroughRecorded`); `VoucherVoided` = the PI-cancel signal; `VoucherShipped` = the shipment-keyed title leg.** *(Forward-consistency: Module S emits these exactly as Module D consumes them — D §14.4 / §16.4.)* | §11.7 + §16.4; item F | AUTO — assert the seven Voucher events fire with correct payloads; **assert no `SellThroughRecorded` event exists**; the Module D PI auto-fire + title-transition + PI-cancel consumption verified when Module D lands (AC-S-MVP-5) |
| **AC-S-EVT-13** | `VoucherShipped` carries the shipped bottle's serial / NFT identity for serialized stock (BMD §5.5 late binding; **NFT decoupled — D12**); non-serialized → null NFT fields per `BottleShippedAsNonSerialized` (E6-47). | §17.5; DEC-134 | AUTO — serialized shipment: assert serial + NFT identity; non-serialized: assert null fields (NFT-burn consumer verified when Module B lands — decoupled) |

### §5.5 Gifting events — **DEFERRED with D5 (§13)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-EVT-14** | *(DEFERRED — D5.)* Gifting events (§13.3) — `VoucherGiftInitiated` / `VoucherGiftAccepted` / `VoucherGiftDeclined` / `VoucherGiftExpired` — fire on transitions; each preserves the giver's locked OC reference (DEC-116). Verified when gifting restores. | §13.3 + §16.5 | AUTO (deferred) — covered by AC-S-J-15 + AC-S-J-16 (deferred) |

### §5.6 Hero Package events

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-EVT-15** | `HeroPackagePurchaseAccepted` (three-gate passes + payment completes); `HeroPackagePurchaseRejected` (reason `profile_state_invalid | single_per_year_violated | capacity_invariant_violated`). | §16.6; DEC-114 | AUTO — covered by AC-S-J-20 + AC-S-J-21 |
| **AC-S-EVT-16** | `MembershipFeePaid` fires alongside `OrderConfirmed` for Hero Orders (DEC-114); consumed by Module K (§15.2/§15.8) to drive `Profile.fee_paid_at`, `ProfileActivated` / `ProfileRenewed`, Club Credit auto-generation (Module K §11.1). | §5.2 + §16.6; DEC-114 | AUTO — Module-S-side covered by AC-S-J-20; assert payload completeness (Module K consumer effect verified when Module K lands) |

### §5.7 Discovery / OC share events

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-EVT-17** | `DiscoveryRevenueShareAccrued` at INV1 for Discovery sales (DEC-112; post-payment-cleared); carries Order ref, Customer ref, the Customer's Originating Club link (`originating_club_id`, read-at-emission, DEC-066), 5% × headline `P_d`, recipient Producer (via the Club's operating-Producer link); null-OC records null recipient (DEC-040). | §16.7; DEC-112 | AUTO — covered by AC-S-BR-OCShare-1 + AC-S-BR-OCShare-2 |
| **AC-S-EVT-18** | `DiscoveryRevenueShareReversed` on Discovery cancellation (DEC-108 + DEC-112); carries the proportional reversed amount (DEC-109) + the original accrual ref. | §16.7; DEC-112 | AUTO — covered by AC-S-BR-OCShare-4 |
| **AC-S-EVT-19** | OC routing is **per-buyer per-Order** regardless of partial-checkout split (DEC-161 + §10.8); the buyer's `originating_club_id` at Order time is locked. | §10.8; DEC-161 | AUTO — multi-Allocation Discovery cart split across checkouts; assert a single OC Producer beneficiary per the buyer's lock |

### §5.8 Promotion / discount / credit events

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-EVT-20** | `PromoCodeApplied` on Coupon application (§10.9); carries Coupon ref, affected lines, chain-step contribution. *(The REFUND_COMPENSATION coupon is the D6 + K.19 goodwill instrument — launch goodwill routes here; AC-S-MVP-3/4.)* | §10.9 + §16.8 | AUTO |
| **AC-S-EVT-21** | `ClubCreditAutoApplied` at checkout-render (DEC-111); carries Profile ref, balance, applied amount, affected lines. `ClubCreditRemovedByCustomer` on customer removal. | §10.5 + §16.8; DEC-111 | AUTO — covered by AC-S-BR-ClubCredit-1 + AC-S-BR-ClubCredit-2 |
| **AC-S-EVT-22** | `ProducerPromotionConsentGranted` when a producer opts in to a club promotion (DEC-039); carries campaign ref, producer identity, consenting actor. | §16.8; DEC-039 | AUTO |
| **AC-S-EVT-23** | `StoreCreditApplied` on customer-level Store Credit application (BMD §4.12; rare under the DEC-044 store-credit-105% alternative). | §16.8 | AUTO |

### §5.9 Customer-facing invoice events (Module S emits all three per DEC-119)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-EVT-24** | `InvoiceINV1Issued` (Module S, order confirmation post-payment-cleared; DEC-107 + DEC-112); business signals per §10.6 (Order/Customer/Profile/Voucher refs, total, currency [FX captured], Address ref with optional company_name + vat_id [DEC-068], OC carve-out ref [Discovery]). | §10.6 + §16.9; DEC-107 | AUTO — drive happy path; inspect payload completeness |
| **AC-S-EVT-25** | `InvoiceINV2Issued` (Module S, shipment; DEC-107); business signals per §10.7 (original INV1 ref, Voucher ref, shipping Address ref, excise, destination VAT, shipping fee, mid-semester storage roll-in lines). | §10.7 + §16.9; DEC-107 | AUTO — drive shipment; inspect payload completeness |
| **AC-S-EVT-26** | `InvoiceINV3Issued` (Module S, semester-end; DEC-119 — **NOT consumed from Module E** as in DEC-118); business signals: Customer ref, 6-month period, per-Voucher storage-month line items (excluding INV2-rolled months), total, currency. **R2 — Module-S-emitted; no bidirectional S↔E.** | §16.9; DEC-119 | AUTO — covered by AC-S-J-11; verify event-emitter ownership (Module S, not Module E) |
| **AC-S-EVT-27** | `InvoiceINV1PartialRefundIssued` on partial refund (§12.7); carries original INV1 ref + cancelled-line amount. | §12.7 + §16.9 | AUTO — covered by AC-S-J-13 |

### §5.10 Storage-fee accrual events (Module S emits per DEC-119)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-EVT-28** | `StorageFeeAccrued` (Module S, monthly per Voucher; DEC-119 — **NOT consumed from Module E** as in DEC-118); carries Voucher/Allocation/Customer refs, accrued month, €0.25/month, running total, partial-month flag. | §16.10; DEC-119 | AUTO — covered by AC-S-J-10; verify Module S emits (not consumes) |

### §5.11 Refund / cancellation events

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-EVT-29** | `StorageFeeProRataRefundIssued` on storage-fee pro-rata refund (DEC-046 + DEC-119 Module-S-internal); carries bottle ref, amount, period span. *(Cause decisioning manual-first — D6.)* | §12.7 + §16.11; DEC-046 | AUTO — covered by AC-S-BR-Storage-9 |
| **AC-S-EVT-30** | `SupervisorOverridePostDeliveryRefund` on the exceptional supervisor-override path (§12.3); carries supervisor identity, reason, amount, Voucher ref. | §12.7 + §16.11; DEC-108 | AUTO — covered by AC-S-FSM-30 |

### §5.12 Event emission ordering, versioning

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-EVT-31** | Card completion causal order: `OrderPlaced` → sanctions/Hold gate → `OrderPaymentAuthorized` → `OrderPaymentCaptured` → `OrderConfirmed` → `InvoiceINV1Issued` + `DiscoveryRevenueShareAccrued` (Discovery) + `MembershipFeePaid` (Hero) + `VoucherIssued` × N + `CartHoldConvertedToOrder`. All from one transaction. *(For a **joining** Hero Package the card authorize+capture is **producer-approval-triggered** against the at-application mandate — MVP-DEC-016; the causal order within that transaction is unchanged.)* | §16.12 | AUTO — covered by AC-S-J-7; verify causal order in the event stream |
| **AC-S-EVT-32** | Bank-transfer completion causal order: `OrderPlaced` → sanctions/Hold gate → `OrderPaymentPending` → (7-day) → on funds-cleared: `OrderPaymentCaptured` → `OrderConfirmed` → `InvoiceINV1Issued` + `DiscoveryRevenueShareAccrued` (Discovery) + Voucher PENDING_PAYMENT → ISSUED + `CartHoldConvertedToOrder`. | §16.12 | AUTO — covered by AC-S-J-5 |
| **AC-S-EVT-33** | *(DEFERRED with D7 — §6.)* Composite Offer multi-producer sale causal order: `OrderConfirmed` → `InvoiceINV1Issued` + `DiscoveryRevenueShareAccrued` (single emit on headline `P_d`) + `VoucherIssued` × N. Verified when the composite restores. | §16.12; DEC-097 | AUTO (deferred) — covered by AC-S-J-3 (deferred) |
| **AC-S-EVT-34** | Events schema-versioned (Module K §15.9 + Module A §12.3 + Module D §16.3 patterns); consumers (D / B / C / E / HubSpot) evolve independently within a major version. | §16.12 | AUTO — inspect schemas for version field; drive a minor additive change; assert consumers continue |

### §5.13 Consumed events from upstream modules

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-EVT-35** | Module 0 PIM events consumed for Offer admissibility: **`ProductReferenceActivated` / `ProductReferenceRetired`** *(naming cascade — renamed from `BottleReferenceActivated/Retired`; Module 0 §18)* — Module S admits Offer creation against `active` PRs only; retired PRs cannot back new Offers. | §17.1; Module 0 §18 | AUTO — emit Module 0 retirement; attempt new Offer on a retired PR; assert rejection |
| **AC-S-EVT-36** | Module K events consumed: `MembershipApprovedByProducer`, `OriginatingClubLocked` (OC reference availability); `ProfileActivated` / `ProfileExpired` / `ProfileRenewed` / `ProfileSuspended` / `ProfileReactivated` (club + Hero eligibility); `CustomerHoldPlaced` / `CustomerHoldLifted` (Hold-gate + Order HOLD_PLACED); `ClubSunset` / `ClubClosed` (Offer cascade + **Club Credit → Discovery store-credit conversion, DEC-043 — KEEP-lean**). | §17.2 | AUTO — drive each upstream event; assert correct Module S response per documented behaviour |
| **AC-S-EVT-37** | Module A events consumed: `AllocationActivated` / `AllocationClosed` / `AllocationRetired` / `AllocationCapacityIncreased` / `AllocationCapacityDecreased` / `AllocationVisibilityChanged` / `AllocationCommercialTermsChanged` / `AllocationSubPoolRebalanced` / `AllocationNonSerializedOptOutChanged` / `AllocationRecallTriggered`. Each drives the documented Offer re-validation / cascade (§17.3). *(v1.1's `AllocationCapacityExhausted` is not a Module A v0.3-MVP event — over-issuance is an operation-level rejection; §0 drift.)* | §17.3 | AUTO — parametrised: emit each event with affected Offers in various states; assert correct downstream effect per §17.3 |
| **AC-S-EVT-38** | Module D events consumed: **`InboundEventPhysicallyAccepted`** (load-bearing for storage-clock-start, DEC-119; populates the Voucher's `storage_clock_warehouse_anchor` — the single storage cross-module read, R2). *(`SupplierPaymentCompleted` is E-emitted/D-consumed with no Module S role — R1/R4; the v1.1 "Module S observes it indirectly" prose is dropped as moot.)* | §17.4 | AUTO — drive `InboundEventPhysicallyAccepted` (V2 pre-Voucher / V1 post-Voucher); assert Module S records the warehouse anchor + re-derives `storage_accrual_start_date` |

---

## §6 Cross-module contracts + boundary respect

Module S has the densest cross-module surface: it reads from Module 0 + K + A; emits to B + C + D + E + HubSpot; consumes from A + D + K. Boundary discipline is critical around the **DEC-119 three-actor split** (invoicing) + the **DEC-187 two-layer no-overselling guard** + the **R2 storage reconciliation** + the **three voucher-event names (item F)**.

### §6.1 Module 0 (PIM) — read-only

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-XM-1** | Module S reads **Product Reference** identity *(wine-display alias Bottle Reference)* at Offer line composition + Voucher issuance; reads Composite SKU shape (single-producer bundles + the deferred-composite seam, Module 0 §3.8); reads Layer 1 product-variant breakability at publication validation rule 5; reads PR `active` state at Offer creation. Module S does NOT edit Module 0 state. | §17.1; DEC-098; DEC-109 | AUTO — inspect the Module 0 ↔ Module S surface: all reads; no Module 0 mutation from Module S |
| **AC-S-XM-2** | Hero Package is a Module S Offer-level designation (DEC-096 + Module 0 §3.8); NOT a PIM Composite SKU attribute; PIM is silent on `is_hero_package`. | §5 + §17.1; DEC-096 | AUTO — inspect Module 0 schema: no `is_hero_package` on Composite SKU; verify Module S Offer carries the flag |

### §6.2 Module K (Parties) — read at gate + cart-render + order-completion

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-XM-3** | Module S reads `Customer.sanctions_status` (Module K §9.3) at the pre-PaymentAuthorization gate (DEC-113); reads the Module K Hold set on Customer + Profiles (Module K §4.8) at the same gate. **FLOOR.** | §17.2; DEC-113 | AUTO — covered by AC-S-BR-Gate-1 + AC-S-BR-Gate-2 |
| **AC-S-XM-4** | Module S reads the Customer's Originating Club link (`originating_club_id`, Module K §6) read-at-emission at INV1 for OC accrual (DEC-112). | §17.2; DEC-066 | AUTO — covered by AC-S-BR-OCShare-2 |
| **AC-S-XM-5** | Module S reads Profile state (Module K §4.2) at order completion for club Offer eligibility + Hero gate 1 (DEC-114). | §17.2; DEC-114 | AUTO — covered by AC-S-J-21 (Profile-state failure path) |
| **AC-S-XM-6** | Module S evaluates the Hero Package Capacity Invariant (Module K §13) at the approval = charge moment for Hero gate 3: `count(seat-occupying Profiles = Active + Suspended) ≤ Allocation.qty` (MVP-DEC-017), against the current authoritative `qty` at the validation boundary (strongly consistent). **Single source of truth = Module A's `qty`** (Module A §11.4; capacity is the allocation `qty` itself, cannot diverge); whether Module S reads Module A directly or via a Module K capacity view is an implementation choice (DEC-073). | §5.1 + §17.2; DEC-114; MVP-DEC-017 | AUTO — covered by AC-S-J-21 (capacity-invariant failure path) |
| **AC-S-XM-7** | Module S reads Club Credit balance + `credit.profile.club_id` (Module K §11) at checkout-render for auto-apply (DEC-111; strict `credit.profile.club_id ∈ offer.club_ids`). *(K.17 carry-forward KEPT.)* | §17.2; DEC-111 | AUTO — covered by AC-S-BR-ClubCredit-1 |

### §6.3 Module A (Allocation) — read + consume cascade

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-XM-8** | Module S reads Module A Allocation state at publication validation + every voucher-issuance: `state`, `visibility`, `commercial_terms.shape × value`, `non_serialized_offer_admitted`, `qty − issued`, `expiry_date`, `producer_breakability` per case_config. *(`composite_constituent_allocation_ids[]` multi-FK read defers with D7.)* | §17.3 | AUTO — inspect read surface per attribute; drive a transaction reading each |
| **AC-S-XM-9** | **DEC-187 two-layer no-overselling guard — FLOOR**: Module S exposes the **lesser of** (Module A allocation-pool ATP) and (Module B physical-inventory ATP) per Offer (§8.6 + Module A §7.1 + §11.5.1) **for warehouse-resident sourcing (`passive_v2`; received `direct_purchase`)**; both must be readable; both must pass at hold placement / voucher issuance. **For `passive_v1` Offers the storefront ATP and the hold/issuance gate are Module A Layer-1 alone (§8.6 sourcing-model scope, MVP-DEC-027; physical receipt gates shipment, not sale — DEC-081 / Phase C item K).** | §17.3 (Stage 8 cascade); DEC-187 + DEC-081 | AUTO — Module-S-side: stub Module A pool ATP = 10, Module B physical ATP = 5; assert storefront displays 5; attempt 7 issuances; assert transactional rejection at the 6th; stub a `passive_v1` Offer with Module A pool ATP = 10 and NO Module B position: assert storefront displays 10 and issuance proceeds on Layer 1 alone (live push pipelines verified when A + B land) |
| **AC-S-XM-10** | DEC-187 per-sub-pool composition: SERIALIZED reads Module B `atp_serialized`; NON_SERIALIZED reads `atp_non_serialized`; MIXED composes per-sub-pool. Cross-sub-pool fungibility NOT admitted at hold placement (Module A §7.1 BR-A-SubPool-2). *(Within the §8.6 sourcing-model scope — a `passive_v1` line composes per-sub-pool against Module A's Layer-1 sub-pool availability, MVP-DEC-027.)* | §17.3 (Stage 8 cascade); DEC-187 | AUTO — MIXED Allocation with disjoint sub-pools; attempt cross-pool consumption at hold placement; assert rejection |
| **AC-S-XM-11** | Module S observes Module A cascade events + re-validates / re-renders (§17.3): `AllocationCapacityIncreased` unblocks Cart-adds; `AllocationCapacityDecreased` subject to anti-orphan; `AllocationVisibilityChanged` → PAUSE mismatches; `AllocationCommercialTermsChanged` re-renders pricing; `AllocationSubPoolRebalanced` / `AllocationNonSerializedOptOutChanged` re-validate serialization; `AllocationClosed` → retire single-Allocation Offers *(the composite-PAUSED cascade defers with D7)*. | §17.3 | AUTO — parametrised across the cascade events; assert correct Module S behaviour per case |

### §6.4 Module D (Procurement / Inbound) — emit + observe — **the forward-consistency contract (item F)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-XM-12** | **`VoucherIssued` → Module D** for V1/V2 Allocations: Module S issues a Voucher → `VoucherIssued` fires → Module D's PI subroutine consumes it (V1/V2) and fires `ProcurementIntentCreated`. **`VoucherIssued` is also the sell-through signal driving Module D's PO PRODUCER→NEWCO title transition (item F — there is NO separate `SellThroughRecorded` event; `VoucherShipped` available for a shipment-keyed leg).** *(N3 — distinct from Module B's inventory `ownership_flag` PRODUCER→NEWCO, keyed to `SupplierPaymentCompleted`; two distinct ledgers. Module S takes no accounting position on the title timing — DEC-072.)* | §17.4; item F | AUTO — Module-S-side: issue Voucher; assert `VoucherIssued` payload (the Module D PI auto-fire + PO-title transition + PI-cancel consumption verified when Module D lands — D §14.4 / §16.4); **assert Module S emits `VoucherIssued` / `VoucherVoided` exactly as Module D consumes them; assert no `SellThroughRecorded`** |
| **AC-S-XM-13** | Module S subscribes to Module D `InboundEventPhysicallyAccepted` for the bound Allocation's stock-arrival (DEC-119 — **the single storage cross-module read at launch, R2**); the date populates the Voucher's `storage_clock_warehouse_anchor`. V2: fires at allocation activation (pre-Voucher; recorded at the Allocation level). V1 / *(deferred)* Direct-Purchase: fires post-Voucher; Module S re-derives `storage_accrual_start_date`. *(Asserted Module-S-side — Module D lists B/C/A as consumers; the read is additive, cut-sheet S.29.)* | §14.7 + §17.4; DEC-119 | AUTO — drive V2 (emit pre-Voucher; assert recorded at Allocation level) + V1 (emit post-Voucher; assert re-derivation) |
| **AC-S-XM-14** | Module S observes Module D `ReverseInboundEventRecorded` (DEC-090 + DEC-117 — informational; recall scope unsold-only; ISSUED immune) + `DiscrepancyResolutionRecorded` (informational for substitution coordination, §11.5 — manual at launch). | §17.4 | AUTO — drive each upstream event; assert audit-trail record + appropriate (non-)behaviour for ISSUED-voucher immunity |

### §6.5 Module B (Provenance — Wave 4) — emit downstream trigger

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-XM-15** | `VoucherShipped` → Module B NFT burn at shipment for serialized stock (BMD §6.7; **NFT decoupled — D12; the non-serialized path is the universal fallback**); Module S emits the serial / NFT identity (Module C late-binding output); Module B consumes + burns. | §17.5; BMD §6.7 | AUTO — Module-S-side: serialized shipment → assert `VoucherShipped` payload incl. serial / NFT identity; assert Module S does not write its own Voucher state from any Module B handler (Module B NFT-burn verified when Module B lands — decoupled) |

### §6.6 Module C (Fulfilment — Wave 4) — emit downstream trigger + observe

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-XM-16** | `VoucherRedemptionRequested` → Module C fulfilment (BMD §5.5); Module C dispatch → Module S transitions REDEMPTION_REQUESTED → SHIPPED + emits `VoucherShipped` + `InvoiceINV2Issued` (with mid-semester storage roll-in if applicable). | §17.6 | AUTO — drive end-to-end redemption + shipment; assert the event chain |
| **AC-S-XM-17** | Module C shipment gate (DEC-081): Module C reads Module D `InboundEventPhysicallyAccepted` as the shipment gate (decoupled from Module S's sellability gate `Allocation.state = ACTIVE`); Module S surfaces "in transit; ETA X" on Vouchers awaiting receipt (Phase C item K — in-transit redemption-block FLOOR; carrier-ETA-precision deferred D17). *(Direct-Purchase-in-transit arm idles — deferred.)* | §17.6; DEC-081 | AUTO — drive the in-transit scenario (V1 per-order window); assert customer-facing in-transit display + the shipment-gate decoupling |
| **AC-S-XM-18** | Module C returns + replacement (DEC-108 + §12.3): post-shipment damage/loss/fault routes via Module C (NOT Module S cancellation); Module S supports the rare supervisor-override surface (§12.3). | §17.6; DEC-108 | AUTO — covered by AC-S-BR-Cancellation-4 + AC-S-FSM-30 |

### §6.7 Module E (Finance — Wave 5) — emit financial events + DEC-119 three-actor split + R2

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-XM-19** | **DEC-119 three-actor split for customer-facing invoicing**: Module S = **EVENT** (WHEN INV1/INV2/INV3 fire); Xero = **ARTIFACT** (PDF + numbering + legal text); Module E = **PAYMENT + ACCOUNTING RECORD + Xero ROUTING**. Module S does NOT generate PDFs, post to Xero, or execute Airwallex. **R2 — the storage cross-module surface is Module-S-internal; no bidirectional S↔E at INV2.** | §0 + §14.1 + §17.7; DEC-119 | AUTO — inspect Module S surface: no PDF-generation path, no direct-Xero API, no direct-Airwallex charge; verify Module E owns those three roles |
| **AC-S-XM-20** | Module S emits customer-facing financial events consumed by Module E (DEC-072): `InvoiceINV1Issued`, `InvoiceINV2Issued`, `InvoiceINV3Issued` (DEC-119), `InvoiceINV1PartialRefundIssued`, `StorageFeeAccrued` (DEC-119), `OrderRefunded`, `DiscoveryRevenueShareAccrued` / `Reversed`, `MembershipFeePaid`, `StorageFeeProRataRefundIssued`, `SupervisorOverridePostDeliveryRefund`. **Module S takes no accounting positions (DEC-072).** | §17.7; DEC-072 | AUTO — inspect each payload: no GL-account references, no revenue-recognition language, no Xero identifiers; verify Module E subscription |
| **AC-S-XM-21** | Module E retains supplier-side settlement events (sell-through, PO settlement, producer statements — unchanged per DEC-119; **the 5% OC computation + producer settlement deferred-with-settlement, D19, reading K's lock + A's lineage, not re-deriving**); Module S does NOT emit supplier-side settlement events. | §17.7; DEC-119 | AUTO — inspect Module S event catalogue; assert no supplier-settlement / sell-through-settlement / PO-settlement events from Module S |
| **AC-S-XM-22** | Module E retains failed-charge handling for INV3 (DEC-047 — **the chargeback Hold trigger automated D21; the storage-payment Hold trigger manual-first D4**, Phase C N2); Module S role: **storage accrual continues unconditionally regardless of Customer Hold state** (storage is bottle-in-custody). | §14.1 + §17.7; DEC-119; DEC-047 | AUTO — drive a failed INV3 charge; assert Module S continues `StorageFeeAccrued`; verify Module E owns the dunning surface |
| **AC-S-XM-23** | Per DEC-160 + §14.1: multi-cycle INV3 composition under a prior-cycle storage Hold — (a) cadence continues unconditionally; (b) `StoragePaymentSucceeded` for the current INV3 lifts the cycle's Hold only; (c) each cycle's failed INV3 runs its own escalation chain. *(Dunning automation deferred — D4.)* | §14.1; DEC-160 | AUTO — multi-cycle persistent-failure scenario; assert 3 INV3s independently; assert per-cycle Hold-lift |

### §6.8 Communication delivery — the ERP email service + HubSpot (purpose split)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-XM-24** | Communication delivery per the Module K §14.9.1 purpose split (MVP-DEC-035): the operational / transactional comms on Module S events — order confirmation (`OrderConfirmed`); shipment (`VoucherShipped`); cancellation (`OrderCancelled` / `VoucherVoided`); refund (`OrderRefunded` / `InvoiceINV1PartialRefundIssued`); voucher-expiry warnings (`VoucherExpired`) — are ERP-sent through the single email service (catalog-registered); HubSpot consumes the same events for marketing / lifecycle only. *(Gift notifications idle with D5.)* Module S never integrates the mail provider directly. | §17.8; MVP-DEC-035 | AUTO — inspect Module S surface: no direct SMTP/SMS/push integration (operational sends route through the ERP email service); verify the catalog registration of the named sends + the HubSpot lifecycle subscription |

### §6.9 DEC-181 sanctions/Hold uniformity at every transaction-initiation surface

Module S has the most transaction-initiation surfaces of any module — five of the nine named in DEC-181: order completion, Voucher redemption-request, Cart Hold reservation at cart-add, *(gifting initiation — idles with D5)*, INV3 charge (downstream at Module E).

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-XM-25** | DEC-181 at order completion — Module S reads sanctions + Hold at the pre-PaymentAuthorization gate (DEC-113; the load-bearing primary gate). Already covered by AC-S-BR-Gate-1 + AC-S-BR-Gate-2. | §10.1 + §10.2; DEC-181 | AUTO — already covered |
| **AC-S-XM-26** | DEC-181 at Voucher redemption-request — Module S reads sanctions + Hold at the moment of redemption-request (§11.7); non-`passed` or any active Hold blocks emission of `VoucherRedemptionRequested` (Module C SO draft→planned re-check is defence-in-depth). | §11.7; DEC-181 | AUTO — negative: `under_review` Customer attempts redemption-request; assert blocked + no event |
| **AC-S-XM-27** | DEC-181 at Cart Hold reservation — cart-add reads sanctions + Hold (§8); non-`passed` or any active Hold blocks the reservation (composes upstream of the §10 gate). | §8; DEC-181 | AUTO — negative: active-Hold Customer attempts cart-add; assert blocked + no `CartHoldCreated` |
| **AC-S-XM-28** | *(DEFERRED with D5 — §13.)* DEC-181 at gifting initiation — both giver and recipient read at gifting initiation (§13.1); active sanctions failure / Hold on either blocks the gift. Verified when gifting restores. | §13.1; DEC-181 | AUTO (deferred) — giver/recipient negative paths when gifting restores |
| **AC-S-XM-29** | DEC-181 at INV3 charge (downstream at Module E) — INV3 charge execution is a transaction-initiation surface; Module E reads sanctions + Hold at charge. **Storage accrual continues unconditionally** (bottle-in-custody); the gate applies to charge execution, not accrual emission. | §14.1; DEC-181 | AUTO — INV3 cycle with active-Hold Customer; assert `StorageFeeAccrued` continues + `InvoiceINV3Issued` fires + Module E gates the charge downstream |

### §6.10 Module-S-internal storage-fee + INV3 ownership (DEC-119) — R2

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-XM-30** | **Module S owns end-to-end (DEC-119)**: storage-clock-start computation (Module-S-native + single Module D read — R2); per-bottle `StorageFeeAccrued`; semi-annual `InvoiceINV3Issued`; mid-semester INV2 storage roll-in (Module-S-internal — no cross-module query); pro-rata refund (Module-S-internal); customer-account-history across INV1/INV2/INV3 natively. Module S does NOT own Airwallex payment-execution, Xero, or rate-card config. **R2 — no bidirectional S↔E at INV2.** | §14.1; DEC-119 | AUTO — inspect the Module S vs Module E ownership matrix (§14.1); assert Module S has all six owned surfaces + NOT the three Module-E/Finance surfaces; assert no inbound storage event from Module E |

### §6.11 MVP re-baseline criteria **(NEW — v0.3-MVP)**

The naming cascade, the D7 single-FK + D5 ownership-transfer seams, the D8 stacking/club-credit posture, the D6 manual-first refund matrix, the three voucher-event names, the L-PP Admin-Panel re-scope, and the R2 storage reconciliation. These verify the **launch-MVP-specific** properties on top of the carried-over v0.1 contract.

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-S-MVP-1** | **Naming-cascade application (Phase C item A).** The catalog-identity criteria carry **Product Reference** (wine-display alias *Bottle Reference* retained): AC-S-XM-1, AC-S-BR-Identity-3, AC-S-J-19 (substitute PR); the consumed Module 0 events rename `BottleReferenceActivated/Retired → ProductReferenceActivated/Retired` (AC-S-EVT-35). **Module S's own `Offer*` / `Cart*` / `Order*` / `Voucher*` / `Invoice*` / `DiscoveryRevenueShare*` / `StorageFee*` names are unchanged** (category-neutral). Naming/contract only — zero behaviour change. | PRD §21; Phase C item A | AUTO — inspect the event registry + Offer-line + Voucher-identity reads; assert no `Wine*` structural name in any catalog-identity read (wine-display aliases admissible in UI); assert the consumed Module 0 events carry `Product*`; assert Module S's own names are byte-for-byte v1.1; assert payload semantics unchanged |
| **AC-S-MVP-2** | **D7 single-FK seam + D5 ownership-transfer seam (cut-sheet Q1/Q4; Phase C item N).** At launch: **no multi-producer composite Offer is published** (the Offer entity carries `composite_constituent_allocation_ids[]` in **single-FK form** — single-Allocation Offers + multi-Offer-per-Allocation only; AC-S-J-3 / FSM-6 / BR-Publication-6 / BR-OCShare-3 / EVT-33 → roadmap). **No downstream orphan** — each constituent voucher is a normal per-bottle voucher. **The Voucher FSM is 7 states** (GIFTED deferred); **the Voucher's customer-reference is mutable** (the ownership-transfer seam — no hard single-permanent-owner assumption); the `originating_club_id` hook is preserved (AC-S-J-15/16 / FSM-24 / BR-Gifting-1..4 / BR-Voucher-7 / EVT-14 / XM-28 → roadmap). | PRD §6 + §11.2 + §11.3 + §13; cut-sheet Q1/Q4 | AUTO — assert no composite Offer in the launch dataset + the single-FK field present-but-single-valued; assert the Voucher FSM enum = 7 states + the customer-reference is mutable (a Voucher can be re-owned without schema change) + `originating_club_id` retained; assert no `VoucherGift*` event fires at launch |
| **AC-S-MVP-3** | **D8 club-credit + stacking posture (cut-sheet Q2/Q3; Phase C item D).** **K.17 carry-forward KEPT, exercised at launch** (the Remaining balance carries across purchases; AC-S-BR-ClubCredit-1); **DEC-043 closure-conversion KEEP-lean** (AC-S-EVT-36). **K.18 welcome-window scaling + K.19 operator manual issuance NOT exercised at launch** — launch = full-fee → full-credit; **launch goodwill routes through the single REFUND_COMPENSATION coupon** (AC-S-EVT-20), not a manual Club-Credit create. **The 7-step stacking chain is KEPT; the policy-discount (step 2) + volume/early-bird (step 5) campaign sophistication is not-configured-at-launch** (no-op seams; AC-S-BR-Stacking-1). | PRD §10.3 + §10.5; cut-sheet Q2/Q3 | MIXED — AI asserts carry-forward is exercised + K.18/K.19 paths do not fire + goodwill routes through the REFUND_COMPENSATION coupon + steps 2/5 are no-op at launch; Paolo confirms the club-VP-whole + the modest-D8-savings calibration is faithful |
| **AC-S-MVP-4** | **D6 refund-matrix manual-first (cut-sheet Q5).** The **legal floor is KEPT whole** (14-day window + Article-16 WAIVER + per-voucher partial refund + FX-correct refund + OC reversal + `VoucherVoided`→Module D PI-cancel; AC-S-BR-Cancellation-1/2/3). The **refund-cost-matrix decisioning is manual-first** — the operator records the refund + cause and offers store-credit-105% via the REFUND_COMPENSATION coupon; the cause taxonomy + the coupon + the refund event payloads are retained as the seam; **the producer-fault clawback netting is deferred-with-settlement (D19, verified when Module E settlement lands)**. | PRD §12.5 + §12.6; cut-sheet Q5 | MIXED — AI asserts the legal-floor criteria pass identically + the matrix routing/netting is operator-driven (not automated) at launch with the cause taxonomy + coupon retained; Paolo confirms the legal floor is whole + the simplification is in ops sophistication, not consumer rights |
| **AC-S-MVP-5** | **The three voucher-event names (cut-sheet Q6; Phase C item F).** Module S **emits `VoucherIssued`** (the V1/V2 ProcurementIntent auto-fire trigger **and** the sell-through PO PRODUCER→NEWCO **title** signal — **there is NO separate `SellThroughRecorded` event**; `VoucherShipped` available for a shipment-keyed leg) **+ `VoucherVoided`** (the PI-cancel signal) **exactly as Module D consumes them** (D §14.4 / §16.4 — the forward-consistency obligation). **Take no accounting position on the title timing (DEC-072).** *(N3 — distinct from Module B's inventory `ownership_flag` keyed to `SupplierPaymentCompleted`.)* | PRD §11.7 + §16.4 + §17.4; cut-sheet Q6; item F | MIXED — AI asserts Module S emits `VoucherIssued` / `VoucherVoided` (+ `VoucherShipped`) with no `SellThroughRecorded` anywhere in the Module S surface, and the names match Module D's consumed set byte-for-byte; Paolo confirms the forward-consistency with Module D's drafted consumer side |
| **AC-S-MVP-6** | **L-PP Admin-Panel re-scope (cut-sheet Q8).** At launch **Module S retains zero producer writes** — club Offer publication + Hero designation + promo overlays are **operator-driven via the Admin Panel** (`actor_role: newco_ops`); Discovery Offers are already Admin-Panel-only; the DEC-115/083 backend parity is **unchanged** (no backend cut; the Producer-Portal Offer-authoring write UI is the deferred seam). **The consumer storefront is EXEMPT** (browse / cart / checkout / cellar / cancellation self-serve KEPT). Producer Portal **read + reporting (D23) is KEPT**. | PRD §15; cut-sheet Q8 | AUTO — assert every Offer-level operator write carries `actor_role: newco_ops` + the backend exposes each club-Offer operation from both surfaces (parity preserved) + no consumer-storefront operation is gated by L-PP; assert the producer-write-UI half is absent-but-seamed |
| **AC-S-MVP-7** | **R2 storage reconciliation (cut-sheet Q7; Phase C R2 / DEC-119).** Storage is **Module-S-internal** — the single cross-module read is the Module D → Module S read of `InboundEventPhysicallyAccepted` (AC-S-XM-13); **no bidirectional Module S ↔ Module E at INV2** (the v1.1 PRD §18.16 BR-S-CrossModule-4 stale DEC-118 "bidirectional" text is reconciled to DEC-119; the §14 body + the AC always carried the correct framing). Module S emits INV3 + `StorageFeeAccrued` (DEC-119), not consumes them from Module E. | PRD §14 + §18.16; cut-sheet Q7; Phase C R2 | AUTO — assert the only storage cross-module read is `InboundEventPhysicallyAccepted` (inbound) + the only storage cross-module emissions are Module S → Module E (outbound); assert no inbound storage-fee event from Module E; assert BR-S-CrossModule-4 carries the DEC-119 framing PRD-side + AC-side (drift resolved) |

---

## §7 Out of scope for this acceptance pass

The following are deliberately excluded, in line with the methodology DECs in the header:

- **Engineering Definition of Done** (DEC-073): coverage thresholds, performance budgets beyond the PRD-cited ATP-staleness commitment (§8.6 / §17.3 — display ATP staleness ≤5s at peak + hold-placement ≤200ms p99 are PRD-level commitments; the monitoring + horizontal-scale architecture is downstream), error-handling exhaustion, observability, retry/idempotency, schema design (column types, FK declarations, nullability as constraints, indexing), the ATP-cache + timer + stacking-pipeline internals, API style + transport, deployment topology.
- **UI / UX acceptance**: Producer-Portal Offer-creation form layouts, Admin-Panel navigation + Discovery curation surface, customer-facing checkout-screen / cellar / in-transit "ETA X" render, the 14-day-WAIVER T&C disclosure UX (DEC-108), validation copy, accessibility, responsive design, i18n of UI chrome. A separate UX track owns these.
- **Operational R&R / approval-tier policy** (`feedback_prd_rr_approval`): which named individual approves Offer publication; single-approver vs producer-then-NewCo-ops handshake; tiered authority by Hero Package value; the supervisor identity for the post-delivery refund override; producer-relationship management of Layer-2 overrides — admin-configurable; not a build-time concern.
- **Non-functional concerns not anchored to a BR / DEC** at PRD level: latency budgets beyond the cited ATP commitments, throughput, alerting thresholds, error budgets, infrastructure choice.
- **Post-launch deferrals — acceptance moves to the roadmap with the feature** ([`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md)):
  - **Net-new MVP deferrals (seams retained — PRD §20.1):** **D7 multi-producer composite** — AC-S-J-3, AC-S-FSM-6, AC-S-BR-Offer-5, AC-S-BR-Publication-6, AC-S-BR-OCShare-3, AC-S-EVT-33 are **not-exercised-at-launch; retained; restore with the composite construct** (the single-FK Offer entity + Module A per-constituent primitive + Module 0 Composite SKU are the seam, AC-S-MVP-2; restores as a coordinated S + A + 0 set). **D5 gifting** — AC-S-J-15/16, AC-S-FSM-24, AC-S-BR-Gifting-1..4, AC-S-BR-OCShare-5, AC-S-BR-Voucher-7, AC-S-EVT-14, AC-S-XM-28 are **deferred-with-feature** (the Voucher ownership-transfer seam; FSM 8→7; restores as a coordinated S + K + C set, AC-S-MVP-2). **D8 K.18/K.19** — welcome-window scaling + operator manual Club-Credit issuance not-exercised-at-launch (formula + manual-create path retained in Module K; goodwill via the REFUND_COMPENSATION coupon, AC-S-MVP-3). **D8 stacking** — steps 2/5 not-configured-at-launch (no-op seams, AC-S-BR-Stacking-1). **D6 refund matrix** — the automated cause-routing + producer-fault clawback netting deferred (manual-first at launch; netting with D19, AC-S-MVP-4). **L-PP producer-write UIs** — deferred-with-seam (backend parity unchanged; AC-S-MVP-6). **OC 5% computation** — deferred-with-settlement (D19; the emission/capture KEPT, AC-S-BR-OCShare-1/2 + AC-S-XM-21).
  - **v1.1 already-deferred (PRD §20.2, carried verbatim):** CruTrade P2P / ON_CRUTRADE; liquid voucher RESOLVED + BottlingResolution + BOUGHT_BACK; B2B credit-terms Order branches (DEC-068); active consignment (DEC-011); drop-shipping (BMD §13.3); producer-author Discovery Offers (DEC-115 carve-out); voucher-substitution full automation (DEC-104); loyalty/referral (BMD §4.12); multi-currency producer-quoted pricing (BMD §4.8); paid services/experiences + INV4 (BMD §4.14); death/inheritance (BMD §9.13); AI Copilot (DEC-021); multi-tier club eligibility (DEC-062); waitlist/FIFO sophistication (DEC-069/079); native mobile (DEC-018); support beyond email + admin (OQ-5).
- **Cross-module behaviours owned by other modules**: Module K Profile FSM + Club Credit entity/auto-issuance/one-active + sanctions/Hold lifecycle + Capacity-Invariant storage; Module A Allocation creation + FSM + qty-mutation anti-orphan + the over-issuance rejection (no `AllocationCapacityExhausted` event); Module D ProcurementIntent + the `VoucherIssued`/`VoucherVoided` **consumption** + `InboundEventPhysicallyAccepted` **emission**; Module B SerializedBottle + NFC + NFT + the `ownership_flag` PRODUCER→NEWCO consume of `SupplierPaymentCompleted` (N3); Module C pick/pack/dispatch + late-binding + returns; Module E Xero + Airwallex + supplier settlement + the OC 5% computation + the `SupplierPaymentCompleted` **emission**. Module S acceptance verifies only the events Module S emits + consumes at the boundary.
- **PRD ambiguities (AMB-S-1..6)** — collected in `greenfield/03-qa/qa.acceptance_ambiguities.md`; an **acceptance-authoring backlog** deferred to a future editorial pass; orthogonal to MVP scope (§0.6). The BR-S-CrossModule-4 drift the Packet flagged is resolved by the §0.5 / R2 reconciliation (the v1.1 PRD §18.16 was stale; DEC-119 is correct).

---

## §8 Sign-off log

### §8.1 Format-validation milestones (template-level)

| Milestone | Date | Notes |
|---|---|---|
| v0.1 authored (parallel agent) | 2026-05-15 | 215 criteria (99.1% AUTO / 0.5% MIXED / 0.5% HUMAN); Packet verdict EDITS_NEEDED; format-propagated against Module 0 §1.5 conventions. **NOT yet Paolo-validated** (like Module D). |
| **v0.3-MVP re-cut (Phase D)** | **2026-06-08** | **RATIFIED by Paolo 2026-06-08** (the MVP re-cut + the original v0.1 validation landed together — the EDITS_NEEDED Packet verdict is discharged at this MVP ratification). Re-cut from the v0.1 DRAFT per cut-sheet §5: naming cascade applied (AC-S-XM-1, BR-Identity-3, J-19, EVT-35); re-anchored to the v0.3-MVP PRD (§0–§19 anchors unchanged — KEEP-heavy on the floor + club VP + spine; §6 composite + §13 gifting deferred-but-retained anchors); **D7 composite criteria (AC-S-J-3, FSM-6, BR-Offer-5, BR-Publication-6, BR-OCShare-3, EVT-33) → roadmap (deferred-with-feature)**; **D5 gifting criteria (AC-S-J-15/16, FSM-24, BR-Gifting-1..4, BR-OCShare-5, BR-Voucher-7, EVT-14, XM-28) → roadmap + Voucher-FSM re-scoped to 7 states (AC-S-FSM-16, BR-Voucher-2)**; **D8 K.17 carry-forward KEPT/exercised + K.18/K.19 deferred (goodwill via REFUND_COMPENSATION) + stacking steps 2/5 not-configured (AC-S-BR-Stacking-1)**; **D6 legal floor KEPT (AC-S-BR-Cancellation-1/2/3) + matrix decisioning manual-first (AC-S-BR-Storage-9)**; **R2 landed (AC-S-BR-CrossModule-4 + XM-19/30 carry DEC-119; PRD §18.16 reconciled)**; **the three voucher-event names verified Module-S-side (AC-S-EVT-12, XM-12 — no `SellThroughRecorded`)**; **L-PP re-scoped to the Admin-Panel surface (AC-S-BR-Parity-1..3); consumer storefront exempt**; **§6.11 added** (7 MVP re-baseline criteria — naming cascade / D7+D5 seams / D8 posture / D6 manual-first / the three voucher names / L-PP Admin-Panel / R2 storage). **~222 total (212 AUTO / 9 MIXED / 1 HUMAN — incl. AC-S-MVP-1..7).** No launch-scope criterion removed; the consumer-floor + club-VP + tax/inventory-floor criteria re-affirmed unchanged. |

### §8.2 Per-AC delivery sign-off

Maintained at first delivery review. Each criterion's state (OPEN / DEMOED / ACCEPTED) + Paolo's signature + date land here. Deferred-with-feature criteria (D7 composite / D5 gifting / D8 K.18-K.19) are tracked separately as "deferred — verified when the feature restores."

| AC ID | State | Date | Paolo signature | Notes / evidence reference |
|---|---|---|---|---|
| AC-S-J-1 | OPEN | — | — | — |
| ... | ... | ... | ... | ... |

(Full table populated at delivery; placeholder rows omitted in the v0.3-MVP draft.)

---

## §9 Cross-references

- **Spec source**: [`../02-prd/Module_S_PRD_v0.3-MVP.md`](../02-prd/Module_S_PRD_v0.3-MVP.md) (the launch-MVP PRD this validates).
- **Predecessor (re-cut from)**: [`../../reference/v1.1/01-prd/Module_S_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_S_Acceptance_v0.1.md) (v1.1 DRAFT, EDITS_NEEDED) + [`../../reference/v1.1/01-prd/Module_S_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_S_PRD_v0.2.md).
- **Ratified scope source**: [`../01-triage/Module_S_CutSheet_v0.1.md`](../01-triage/Module_S_CutSheet_v0.1.md) §5 (the acceptance delta) + §6 (Q1–Q8).
- **Coherence gate**: [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) (R2; items D/E/F/G/I; floor §6).
- **Decision Register**: `greenfield/04-decisions/decisions.md` (DEC-* cited inline) + [`../04-decisions/MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md) (the MVP index).
- **Roadmap (the deferred set + seams)**: [`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md).
- **Sibling acceptance docs**: `Module_0/K/A/D_Acceptance_v0.3-MVP.md` (in `../03-acceptance/`); `Module_B/C/E_Acceptance_v0.3-MVP.md` (to be authored).

---

*End of Module S Acceptance Criteria v0.3-MVP — Phase D re-baseline. **RATIFIED by Paolo 2026-06-08.** The heaviest acceptance delta of the triage — but annotations + feature-deferrals, not floor removals: the D7 composite + D5 gifting criteria move to the roadmap (retained, deferred-with-feature); the Voucher-FSM re-scopes to 7 states; the D8 stacking steps 2/5 + the D6 refund-matrix decisioning are annotated not-exercised/manual-first; R2 lands (BR-S-CrossModule-4 → DEC-119); the three voucher-event names are verified Module-S-side (no `SellThroughRecorded`, consistent with Module D); L-PP re-scopes to the Admin-Panel surface. The consumer-floor (sanctions/Hold gate, INV1/INV2/INV3 MPV VAT, no-overselling shared-pool + lesser-of ATP, 1-voucher-per-bottle, Cart-Hold strict-timeout, the Voucher FSM core) + the club VP (Hero three-gate, Club Credit auto-apply + carry-forward) stand unchanged. Nothing handed off until Phase E.*
