# NewCo ERP — Module S PRD (Commerce — Offers / Cart / Checkout / Vouchers / Refunds / Storage) — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP scope of Module S).
- **Date**: 2026-06-08
- **Status**: **RATIFIED by Paolo 2026-06-08.** Held in `mvp/`; nothing promoted to `handoff/` until Phase E. *(Modules K / A / D remain DRAFTED-awaiting-batch-ratification; Module 0 RATIFIED 2026-06-07.)*
- **Owner**: Paolo (decides). Claude recommends.
- **Testable companion**: [`Module_S_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_S_Acceptance_v0.3-MVP.md) — the MVP acceptance re-cut (rides alongside this PRD).
- **Predecessors / inputs**:
  - **Frozen v1.1 predecessor** (strip *from*; NEVER edit): [`../../reference/v1.1/01-prd/Module_S_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_S_PRD_v0.2.md) (the largest v1.1 PRD, ~22 sections) + [`../../reference/v1.1/01-prd/Module_S_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_S_Acceptance_v0.1.md) (DRAFT 2026-05-15; 215 criteria; EDITS_NEEDED — not yet Paolo-validated).
  - **Ratified scope source**: [`../01-triage/Module_S_CutSheet_v0.1.md`](../01-triage/Module_S_CutSheet_v0.1.md) (RATIFIED 2026-06-07; Q1–Q8). §2 = the scope; §3 = the rewrite instructions; §5 = the acceptance delta.
  - **Coherence gate**: [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) (RATIFIED 2026-06-07). Module S owns **RECONCILE R2** (§5-R2); items D / E / F / G / I + the floor chains (§6).
  - **Source-of-truth names**: [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18 (the canonical name table; apply, don't re-derive). **Composite SKU KEPT** as the D7 seam (Module 0 §3.8).
  - **Settled siblings** (cross-checked): [`Module_K_PRD_v0.3-MVP.md`](Module_K_PRD_v0.3-MVP.md) (Club Credit §11; OriginatingClubLocked §6; Hero Capacity Invariant §13; sanctions/Hold read-API §4.8/§9.3) · [`Module_A_PRD_v0.3-MVP.md`](Module_A_PRD_v0.3-MVP.md) (per-constituent supply primitive §3.1/§4.1; Layer 1 §7.1; Hero `qty` §11.4; OC lineage §11.7) · [`Module_D_PRD_v0.3-MVP.md`](Module_D_PRD_v0.3-MVP.md) (consumes `VoucherIssued`/`VoucherVoided` §14.4; emits `InboundEventPhysicallyAccepted` §16.1; item F / N3 / R4 §3.5).
- **Methodology** (the four binding NewCo DECs, carried from v1.1):
  - **DEC-072** — no accounting positions. Module S **records** customer-facing financial events (`InvoiceINV1Issued`, `InvoiceINV2Issued`, `InvoiceINV3Issued`, `DiscoveryRevenueShareAccrued`, `ClubCreditAutoApplied`, refund events …) with the business signal each carries; Module E records + the Xero integration decides GL treatment. **This is Module S's highest discipline-drift surface** — every invoice / OC-share / storage / refund event below is event + business-signal-only, no GL claim.
  - **DEC-073** — product-spec layer. Name the *contract* (entity concepts, attributes, lifecycle states, business-meaningful enums, domain-event names + signals, invariants, module boundaries); tech-implementation (JSON shapes, FK/column declarations, indexing, the ATP-cache push-vs-pull mechanics, the stacking-pipeline internals, UX/layout) is downstream and out of scope.
  - **DEC-074** — self-contained. Anchors are restated inline so a builder who has not read v1.1 can take this into the dev phase; the v1.1 predecessor + the cut-sheet + Phase C are cited for audit.
  - **P1 / P2** (MVP principles) — every deferred/simplified item names the **seam** that makes the post-launch build additive + points to the roadmap (§20); producer/back-office writes are operator-driven via the Admin Panel, consumer storefront exempt.

---

## §0 MVP scope at a glance

> **Verdict: Module S is the FIRST genuinely cut-heavy module of the triage — D7 (defer the multi-producer composite construct), D5 (defer gifting), D8 K.18/K.19 (defer two club-credit peripherals), and D6 (simplify the refund-cost matrix) are real net-new Module-S deferrals/simplifies — YET the consumer core-loop floor, the compliance/tax/inventory floor, and the club value proposition all stay whole.** The supply-side quartet (0 / K / A / D) each forwarded or pre-factored its headline lever and netted ~0 at its own layer; **Module S is where the deferred levers come to ground.** But the honest shape is *defer the heavy-but-non-core constructs* (a multi-producer merchandising format; a non-core feature; thin club-credit peripherals; ops sophistication) while *keep the consumer floor + the club VP + the commerce spine*. Six facts converge:

1. **D7 — the multi-producer Discovery composite construct lands here as the headline cut (ratified Q1).** The substantive D7 machinery is entirely Module S's: the `composite_constituent_allocation_ids[]` multi-FK atomic bind (DEC-097), the atomic N-way decrement + atomic-rollback at issuance (DEC-179), the composite mid-life cascade, the 5-rule publication extension × N, and the composite OC-on-headline-`P_d`. **DEFER the multi-producer composite; SHIP single-producer Offers** (club mixed-cases per DEC-019 + single-Allocation Discovery Offers + the multi-Offer-per-Allocation multi-granularity pattern DEC-099, all unchanged). **Seam (P1):** the Offer entity ships with `composite_constituent_allocation_ids[]` in **single-FK form** (the multi-FK *binding logic* is the additive part); Module A keeps the per-constituent single-Allocation supply primitive (A §3.1); Module 0 keeps Composite SKU (0 §3.8). **No downstream orphan** — each constituent voucher is a normal per-bottle voucher; B / C / D / E see N normal vouchers + N `VoucherIssued` events, never a "composite." (§6.)

2. **D5 — gifting is a clean in-module defer (ratified Q4).** Gifting is not in the core loop. **DEFER** the GIFTED voucher state + the 7-day accept flow + the recipient-gate validation + the four `VoucherGift*` events. **The Voucher FSM collapses 8 → 7 states at launch.** **Seam (P1):** preserve the Voucher's **ownership-transfer capability** (the customer-reference is mutable — no hard single-permanent-owner assumption), so member-to-member gifting is an additive post-launch build; the recipient-KYC + Originating-Club hooks ride on the kept Voucher `originating_club_id`. (§13.)

3. **D8 — the club VP is load-bearing, so the safe savings are modest and clean (ratified Q2/Q3).** **KEEP** Club Credit auto-apply (DEC-111) + **K.17 partial-redemption/carry-forward** (the remaining balance that carries across purchases — *load-bearing customer value*, KEEP against the K-draft's tentative defer) + DEC-043 closure-conversion (KEEP-lean). **DEFER** K.18 welcome-window proportional scaling (launch = full-fee → full-credit; the formula retained in Module K §11.1) + K.19 operator manual Club-Credit issuance (route launch goodwill through the single REFUND_COMPENSATION coupon; Module K retains the manual-create path). **KEEP the 7-step stacking chain as the spine**; the campaign sophistication (policy-discount step 2 + volume/early-bird-multiplier step 5) is **not-configured-at-launch** (no-op seams). **Honest: the stacking "simplification" is thin — mostly a config/QA posture, not a build cut; the machinery is v17-inherited-and-built. The D8 savings are modest, and I say so.** (§10.)

4. **D6 — keep the cancellation/refund *legal floor* whole; simplify the *cost-matrix decisioning* to manual-first (ratified Q5).** **KEEP** the 14-day pre-shipment window from INV1 + the post-shipment Article-16 WAIVER + per-voucher partial refund + refund-at-original-FX + the OC reversal + `VoucherVoided` → Module D PI-cancel. **SIMPLIFY** the DEC-025 cause-routing + DEC-044 store-credit-105% goodwill + producer-fault clawback netting → **manual-first operator handling** (operator records refund + cause; offers store-credit-105% by judgment via the REFUND_COMPENSATION coupon). The producer-fault clawback netting is **deferred-with-settlement** (D19, Module E). **The legal floor is whole; the simplification is in ops sophistication, not consumer rights.** (§12.)

5. **Module S owns the load-bearing floor on the consumer side — verified whole (KEPT).** The **sanctions/Hold gate at order completion** (DEC-113 — **THE consumer-side compliance enforcement point**; Module K + Module A are sanctions-blind by design); **tax-correct INV1/INV2/INV3** under the MPV VAT regime; the **no-overselling hold/issuance surface** (Cart-Hold strict-timeout, multi-Offer shared-pool decrement, the lesser-of storefront ATP, 1-voucher-per-bottle); the **Voucher FSM** (the artefact the customer owns). **None is a cut candidate.** (§8, §10, §11, §14.)

6. **Module S lands RECONCILE R2 + discharges the three Module-D-owed voucher-event names (naming/contract only — zero behaviour change).** **R2 (DEC-119):** reconcile `BR-S-CrossModule-4` (§18.16) from the stale DEC-118 "bidirectional Module S ↔ Module E at INV2" framing to **storage Module-S-internal** (single Module D → S read of `InboundEventPhysicallyAccepted`; no bidirectional S↔E). The §14 body + the acceptance doc already carry the correct framing — only the §18.16 BR text was stale. **The three voucher-event names (Q6):** `VoucherIssued` (the V1/V2 PI auto-fire trigger **and** the sell-through PO PRODUCER→NEWCO title signal — **there is NO separate `SellThroughRecorded` event**; `VoucherShipped` available for a shipment-keyed leg) + `VoucherVoided` (the PI-cancel signal) — **all existing Module S events; no net-new.** Module D's drafted PRD already consumes these names exactly (D §14.4); **Module S emits them consistently** (§11.7, §16.4, §17.4). **Take no accounting position on the title timing (DEC-072 / item F).** (§14, §16, §17, §18.)

**The naming cascade (Phase C item A — the one mechanical change).** `Bottle Reference → Product Reference (PR)` at Offer line items + Voucher PR reference + the Module 0 reads; `Wine Master/Variant → Product Master/Variant` in cross-reads; the consumed Module 0 events `BottleReferenceActivated/Retired → ProductReferenceActivated/Retired`, `Wine* → Product*`. **Composite SKU retained** as the D7 seam. "Bottle Reference" retained as a wine-display alias. **Module S's own `Offer*` / `Cart*` / `Order*` / `Voucher*` / `Invoice*` / `DiscoveryRevenueShare*` names are already category-neutral — unchanged.** Zero behaviour change. (§21.)

**The floor pieces Module S holds (all KEPT, whole) — verified in composition by Phase C §6:**
- **No-overselling** — the multi-Offer shared-pool decrement (`Allocation.qty − issued`) + the lesser-of storefront ATP (min(Module A Layer 1, Module B Layer 2) per sub-pool) + 1-voucher-per-bottle. Composes Module A Layer 1 ∧ Module B Layer 2 ∧ Module C no-oversell-at-pick. (§8, §11.)
- **KYC / sanctions / OFAC / Hold** — the sanctions/Hold gate at order completion (S.15 — THE consumer-side enforcement point) + the DEC-181 uniformity re-reads at cart-add / redemption-request / INV3-charge. (§10.)
- **Tax-correct invoicing** — INV1 (no excise/VAT, MPV) / INV2 (excise + destination VAT at shipment) / INV3 (semi-annual storage), under the MPV VAT regime. (§10, §14.)
- **The cancellation legal floor** — 14-day pre-shipment + Article-16 post-shipment WAIVER + per-voucher partial refund + FX-correct refund. (§12.)

**The genuine launch-scope reductions — all seamed (P1):**
- **D7** the multi-producer composite construct → roadmap (Offer-entity-single-FK seam; §6, §20).
- **D5** gifting → roadmap (Voucher ownership-transfer seam; FSM 8→7; §13, §20).
- **D8** K.18 welcome-window scaling + K.19 operator manual Club-Credit issuance → roadmap (formula + manual-create path retained in Module K; launch goodwill via the REFUND_COMPENSATION coupon; §10, §20).
- **D6** the refund-cost-matrix *decisioning* → manual-first (the cause taxonomy + coupon + event payloads retained; the automated routing/netting additive; §12, §20).
- **L-PP** the Producer-Portal Offer-authoring **write UIs** → roadmap (DEC-115/083 backend parity unchanged — no backend cut; §15, §20).
- **The v1.1 already-deferred set** (CruTrade/ON_CRUTRADE + C2C/P2P resale; liquid voucher RESOLVED/BOUGHT_BACK; B2B credit-terms branches; active consignment; drop-ship; producer-author Discovery Offers; voucher-substitution full automation; loyalty/referral; multi-currency producer-quoted pricing; paid services/experiences + INV4; death/inheritance; AI Copilot; multi-tier club eligibility; waitlist/FIFO sophistication; native mobile; support beyond email+admin) — **carried verbatim** (§20). Do not re-cut.

**The eight ratified scope confirmations (cut-sheet §6, Paolo 2026-06-07):** **Q1** DEFER the multi-producer Discovery composite; ship single-producer (§6). **Q2** KEEP Club-Credit carry-forward (K.17) + closure-conversion (DEC-043); DEFER welcome-window scaling (K.18) + manual issuance (K.19) (§10). **Q3** KEEP the 7-step stacking chain; campaign sophistication not-configured (§10). **Q4** DEFER gifting; Voucher FSM 8→7; ownership-transfer seam (§13). **Q5** KEEP the legal floor; SIMPLIFY the refund-cost matrix → manual-first (§12). **Q6** the three voucher-event names = `VoucherIssued`/`VoucherIssued`/`VoucherVoided`, no net-new, no `SellThroughRecorded` (§16, §17). **Q7** RECONCILE BR-S-CrossModule-4 → DEC-119 (§18.16). **Q8** zero producer writes; storefront exempt (§15).

> **One drift flagged for Paolo (not a cut; a naming reconciliation).** v1.1 Module S named an `AllocationCapacityExhausted` *signal from Module A* as the over-issuance block (§4.4, §17.3). Module A's drafted v0.3-MVP frames Layer 1 over-issuance as an **operation-level rejection** (`qty − issued ≥ 0`), not an emitted event (Module A §7.1) — the only `AllocationCapacity*` events are the mutation events `AllocationCapacityIncreased`/`AllocationCapacityDecreased`. This PRD therefore describes over-issuance as a **rejection at the issuance operation** composing with Module A's Layer 1, and does not rely on an `AllocationCapacityExhausted` event. Naming/contract only — no behaviour change; flagged so Paolo can confirm the reconciliation (digest).

---

## §1 Module Scope

### §1.1 In scope (launch)

Module S owns these surfaces at NewCo launch:

- **Offer entity** — first-class commercial-presentation entity (DEC-095); 6-state FSM (DRAFT → REVIEWED → SUBMITTED → ACTIVE → PAUSED → CLOSED); pricing surface derived from Allocation `commercial_terms` (DEC-100); granularity (bottle / case / mixed_package / vertical); eligibility filters; time-window; `is_hero_package` boolean (DEC-096); `composite_constituent_allocation_ids[]` **(single-FK at launch — the D7 seam; the multi-FK multi-producer composite binding is deferred, §6)**; promotional-price overlay (DEC-100 + DEC-039); Layer 3 commercial_unbreakable.
- **Multi-Offer-per-Allocation shared-pool decrement** (DEC-099) — bottle + case + Hero + time-windowed promo Offers reading one Allocation's `qty − issued`; first-to-consume-last-unit wins; over-issuance rejected at the issuance operation (Module A Layer 1). **FLOOR — no-overselling.**
- **Offer publication validation** — the 5-rule contract at SUBMITTED → ACTIVE (DEC-098) + the cascade re-validation on Allocation state changes.
- **Cart entity + Cart Hold** (DEC-105/106/049) — 48h cart-session persistence; 15-min strict Allocation reservation; bank-transfer 7-day extension; the DEC-181 sanctions/Hold read at cart-add. **FLOOR.**
- **Storefront ATP lesser-of read** — min(Module A allocation-pool ATP, Module B physical-inventory ATP) per sub-pool (DEC-185/187). **FLOOR.**
- **Order FSM** (DEC-101) — the 12-state machine; PENDING_PAYMENT *is* the bank-transfer 7-day credit-terms state; single-transaction across club + Discovery + cart.
- **Checkout gates + stacking** — the sanctions/Hold gate at pre-PaymentAuthorization (DEC-113); the Hero Package three-gate eligibility check (DEC-114); the 7-step stacking chain + mutual-exclusivity matrix (DEC-110, **campaign sophistication not-configured at launch — D8**); Club Credit auto-apply (DEC-111); INV1 emission (DEC-107) + OC 5% × `P_d` emission (DEC-112).
- **Voucher entity** — 1-voucher-per-bottle (DEC-109); **7-state machine at launch** (DEC-102, GIFTED deferred — D5); EXPIRED mechanics (DEC-103); manual substitution (DEC-104); recall observability (DEC-117).
- **Cancellation and refund** — the 14-day pre-shipment window from INV1 (DEC-108); post-shipment Article-16 WAIVER; per-voucher partial refund (DEC-109); refund-at-original-FX; **the refund-cost-matrix decisioning manual-first at launch — D6** (cause taxonomy + REFUND_COMPENSATION coupon retained).
- **Storage-fee computation + INV3 issuance + per-bottle accrual** (DEC-119 — Module-S-internal; supersedes DEC-118's ownership clause; mechanics preserved). Reads Module D's `InboundEventPhysicallyAccepted` (the single storage cross-module read).
- **Producer Portal ↔ Admin Panel parity** for Offer-level operations (DEC-115) — a backend contract; **producer Offer-authoring write UIs deferred (L-PP); Discovery Offers already Admin-Panel-only; consumer storefront exempt.**

### §1.2 Out of scope (deferred at launch — seamed; see §20)

- **The multi-producer Discovery composite construct** (D7) — the multi-FK atomic bind, the N-way atomic decrement + rollback, the composite cascade, the 5-rule × N extension, the composite OC-on-`P_d`. **Single-producer Offers ship; the Offer entity ships single-FK-capable.** (§6.)
- **Gifting** (D5) — the GIFTED voucher state, the 7-day accept flow, the recipient-gate validation, the four `VoucherGift*` events. **The Voucher ownership-transfer capability is preserved as the seam.** (§13.)
- **K.18 welcome-window proportional scaling** + **K.19 operator manual Club-Credit issuance** (D8) — launch = full-fee → full-credit; launch goodwill via the REFUND_COMPENSATION coupon. (§10.2, §20.)
- **The automated refund-cost-matrix routing + producer-fault clawback netting** (D6) — manual-first at launch; the netting defers-with-settlement (D19). (§12.)
- **Producer-author Offer write UIs** (L-PP) — operator-driven via the Admin Panel; the DEC-115/083 backend parity is unchanged. (§15.)

### §1.3 Out of scope (other modules / permanently-deferred)

Carried verbatim from v1.1 (§19 boundary notes; §20 deferred set): Allocation operations (Module A); ProcurementIntent/PO/Inbound/supplier-payment (Module D); NFC/NFT/serialization/Bottle-Page (Module B); pick/pack/dispatch/late-binding/cellar-render (Module C); Airwallex payment-execution + Xero GL + supplier settlement (Module E + Xero); Customer/Profile/Club/Hold/Originating-Club entities (Module K); Wine/Product Master/Variant/Reference/SKU/Composite-SKU (Module 0). Plus the v1.1 already-deferred set (§20). **Direct Purchase is deferred (Phase C item I) — the storage-clock Direct-Purchase-in-transit arm idles; the read is the same `InboundEventPhysicallyAccepted` for V1/V2 (§14).**

---

## §2 Personas

Module S serves customer-facing roles + operator surfaces governed by the Producer-Portal ↔ Admin-Panel parity principle (DEC-115). UX/layout is downstream (DEC-073).

- **Customer (Member / Waiting-list / Legacy — Module K §5).** Browses Offers (club page + Discovery Tab), adds to Cart, completes Checkout, holds Vouchers in cellar, requests shipment, exercises the 14-day pre-shipment cancellation right. Members access club Offers + Discovery; Waiting-list/Legacy access Discovery only. Customer-side eligibility (KYC / sanctions / Hold) is owned by Module K and read at order completion (DEC-113). **Consumer storefront is self-serve — EXEMPT from L-PP (kickoff §3). Gifting is deferred at launch (D5, §13).**
- **Allocation Operator (Admin-Panel-side at launch; Producer-Portal write UI deferred per L-PP).** Creates club Offers from active Allocations; configures granularity / pricing / eligibility / time-window / Hero designation / Layer 3; drives the Offer FSM; applies promo overlays subject to producer opt-in (DEC-039). At launch, **operator-driven via the Admin Panel** (DEC-115 parity is a backend contract; the producer self-service write UI is deferred — §15).
- **Discovery Curator (NewCo Admin-Panel-only).** Curates single-producer Discovery Offers (DEC-115); sets `P_d`; configures granularity / eligibility / time-window / Layer 3; applies promo overlays unilaterally (DEC-039). **Multi-producer composite curation is deferred at launch (D7, §6).**
- **Sanctions / Compliance Reviewer (NewCo Ops).** Reviews Customers in `sanctions_status = under_review` (Module K §9.2) for the order-completion gate (DEC-113). Admin Panel.
- **Customer Care Operator (NewCo Ops).** Operates manual voucher substitution (DEC-104); the supervisor-override surface for exceptional post-delivery refunds (DEC-108); the pre-shipment cancellation surface; **and — at launch — records the refund cause + offers store-credit-105% by judgment via the REFUND_COMPENSATION coupon (D6 manual-first, §12).** Admin Panel.
- **Settlement Reviewer (Module E-side reader).** Reads Module S's invoice + OC-share + refund events to compose producer settlement statements. Does not edit Module S state. **The 5% computation + clawback netting are operator-run at launch / engine-built post-launch (D19).**
- **Customer Portal end-user (read-only on order / cellar / invoice history).** Reads Module S state; edits come through the Customer's own Checkout / Cancellation surfaces or the Customer Care Operator.

The **Producer-Portal ↔ Admin-Panel parity** (DEC-115) is captured in §15 — every per-Offer operation on club Offers is exposable from both surfaces (backend); every Offer-level event carries `actor_role: producer | newco_ops` for audit. Discovery Offer operations are Admin-Panel-only (`actor_role: newco_ops`).

---

## §3 Architecture — Offer as Separate First-Class Entity (DEC-095)

Module S's load-bearing pattern is the **Offer as a separate first-class entity** (DEC-095): Offer carries its own row, FSM, pricing surface, granularity, eligibility filters, time-window, Layer 3 commercial_unbreakable, `is_hero_package` flag, and `composite_constituent_allocation_ids[]`. Offer is **distinct from Allocation** (Module A): Allocation governs producer-relationship state (lifecycle, mutability, sourcing model, counterparty FKs); Offer governs commercial-presentation state (publication validation DEC-098, customer-facing pricing DEC-100, granularity, eligibility, time-window, Layer 3).

The single-entity pattern composes with two NewCo patterns — one shipping at launch, one deferred:

- **Multi-Offer-per-Allocation** (DEC-099 — §4.4, **ships at launch**): one Allocation carries multiple Offers (a bottle Offer + a 6-pack case Offer + a Hero Package Offer + a time-windowed promotion Offer); each Offer's voucher issuance decrements the **shared** `Allocation.qty − issued`; first-to-consume-last-unit wins; over-issuance is rejected at the issuance operation (Module A Layer 1, `qty − issued ≥ 0`). **This is one Allocation → N Offers — NOT the D7 composite** (which is N Allocations → one Offer).
- **Multi-producer Discovery composite Offer** (DEC-097 — §6, **DEFERRED at launch, D7**): ONE composite Offer referencing N constituent Allocations atomically via the `composite_constituent_allocation_ids[]` **multi-FK**. **Deferred; the Offer entity ships with the field in single-FK form (one constituent — the canonical single-Allocation case), so the multi-FK binding logic restores additively.** Module A keeps the per-constituent single-Allocation supply primitive (A §3.1/§4.1); Module 0 keeps Composite SKU (0 §3.8).

The Offer entity is consumed by every customer-facing Module S surface (Cart, Checkout, Voucher) and observed downstream:

- **Module D (Procurement)**: Module S's `VoucherIssued` is the trigger for Module D's V1/V2 PI auto-fire (the voucher-issuance signal); Module D observes `OfferActivated` / `OfferClosed` for PI lifecycle alignment. `VoucherIssued` is also the sell-through signal driving Module D's PO PRODUCER→NEWCO **title** transition (item F — §17.4); `VoucherVoided` cancels a V1 PI.
- **Module B (Inventory / Provenance — Wave 4)**: `VoucherShipped` triggers NFT burn at shipment (NFT decoupled — D12; the non-serialized path is the universal fallback); Module B reads Voucher state + the Allocation sub-pool partition to drive serialization.
- **Module C (Fulfilment — Wave 4)**: `VoucherRedemptionRequested` triggers pick / pack / dispatch; Module C consumes `VoucherShipped` for late-binding the physical bottle.
- **Module E (Finance — Wave 5)**: Module S emits the customer-facing invoice events (INV1 / INV2 / INV3) + the OC-share accrual for Module E to record + route to Xero (DEC-072).

The alternative (Offer collapsed onto Allocation) was rejected at DEC-095 (it forces row-splitting at publication for the multi-Offer pattern and conflates producer-relationship state with commercial-presentation state). v17 §5.2's Offer FSM is inherited.

---

## §4 Offer Entity

The **Offer** is NewCo's customer-facing commercial-presentation primitive: the row that publishes a quantity of a specific **Product Reference** *(wine-display alias: Bottle Reference)* — or, post-launch, a curated bundle of constituent Product References — at a customer-facing price on a surface (club page or Discovery Tab), under eligibility filters and a time-window, governed by an FSM. Offer ↔ Allocation cardinality is **N:1** at launch (single-Allocation Offer — the canonical case; multi-Offer-per-Allocation per DEC-099). The **N:M** form (multi-producer Discovery composite per DEC-097) is deferred (§6).

### §4.1 Offer attributes (conceptual)

Business attributes only; tech-implementation shape is downstream (DEC-073).

**Identity attributes**:
- **Product Reference reference (single-Allocation Offers)** OR **`composite_constituent_allocation_ids[]` (single-FK at launch)**: the Allocation(s) the Offer publishes from. **At launch the field carries a single constituent** (the canonical single-Allocation case — one bottle Offer from one club Allocation; one case Offer from one Discovery Allocation). The multi-FK multi-producer composite form is deferred (§6).
- **Offer surface**: enum `CLUB | DISCOVERY` (matches the bound Allocation's `visibility` strictly — DEC-076 2-value enum). Determines which Customer segments can see + purchase.
- **Audit identity**: opaque Offer id; creation timestamp; creating actor + `actor_role` (`producer | newco_ops` per DEC-115); last-mutation timestamp + actor + role.

**Commercial attributes**:
- **Granularity**: enum `bottle | case | mixed_package | vertical`. The bound Allocation's `producer_breakability` per case_config (Module A Layer 2) constrains admissible granularities.
- **Customer-facing price**: derived from Allocation `commercial_terms` (DEC-100; §4.3). For `commercial_terms.shape = fixed_per_unit`: club `P = value × 100 / 87.5`; Discovery `P_d` is **set on the Offer** with `value` as cost `C`. For `percent_of_selling_price`: `selling_price` set on the Offer; producer share = `value × selling_price`. **Sell-through settlement always reads Allocation `commercial_terms`, not the Offer's `promotional_price`.**
- **`promotional_price` overlay**: optional Offer-level overlay (DEC-100; Allocation `commercial_terms` untouched). Producer opt-in via `ProducerPromotionConsentGranted` required for **club** promotions (DEC-039); **Discovery** promotions are NewCo-unilateral.
- **`is_hero_package` boolean** (DEC-096): default `false`. When `true`, the Offer is a Hero Package realisation (§5) — a Module S Offer-level designation, NOT a PIM Composite SKU attribute (Module 0 §3.8); three Hero-conditional concerns attach at order completion (DEC-114).
- **Layer 3 commercial_unbreakable** (Module 0 §7.4): boolean; default = the bound Allocation's Layer 2 producer_breakability per case_config (the operator-without-action default). Layer 3 **cannot downgrade Layer 2** (DEC-098 rule 5; effective rule = Layer 1 OR Layer 2 OR Layer 3 — any layer declaring unbreakable wins); an explicit operator-override path is admitted with mandatory reason capture (`OfferLayer2OverrideRecorded`, §7.1). Immutable once the Offer transitions to ACTIVE.

**Eligibility + surface attributes**:
- **Eligibility filters**: optional per-Offer filter set — Profile state (e.g., Hero Offers gate on Approved Profile), KYC tier (via Module K Hold on KYC), purchase limits (per-offer max, per-customer-per-period, per-club annual cap, Discovery curator-set). Enforced at render / Cart-add / Checkout.
- **Time-window**: optional `valid_from` / `valid_to`. Outside the window the Offer renders read-only/hidden; inside it, Cart-add + Checkout proceed.

**Serialization attribute**:
- **`serialization_type`**: enum `SERIALIZED | NON_SERIALIZED | MIXED` (from Module A's derived attribute). Must align with the bound Allocation's `non_serialized_offer_admitted` + sub-pool partition (DEC-098 rule 3). A non-serialized badge renders on the Offer card.

**State**: the FSM-tracked Offer `state` (§4.2).

### §4.2 Offer FSM

Six canonical states (v17 §5.2 inheritance, DEC-095):

```
DRAFT → REVIEWED → SUBMITTED → ACTIVE → PAUSED → CLOSED
                                  ↑ ↓
                               re-publish
```

- **DRAFT** — created against an `ACTIVE` Allocation; editable (granularity / pricing / eligibility / time-window / Layer 3 / `is_hero_package` / sub-pool serialization).
- **REVIEWED** — marked ready for review; a second actor verifies data quality.
- **SUBMITTED** — the **5-rule publication validation** (DEC-098, §7) runs. Pass → ACTIVE (`OfferActivated`); fail → `OfferPublicationValidationFailed` (reason payload) + revert to DRAFT.
- **ACTIVE** — publicly buyable on its surface. Layer 3 + `is_hero_package` are immutable from here; other attributes (promo overlay, time-window, eligibility tightening) mutable subject to producer opt-in (DEC-039) + `actor_role`.
- **PAUSED** — temporarily un-buyable; existing Cart Holds count down; new Cart-adds blocked. The recovery state for mid-life issues (most commonly a producer pulling availability mid-cycle; at launch the deferred composite-constituent-close cascade does not apply — §6). Operator re-publishes PAUSED → ACTIVE.
- **CLOSED** — terminal; in-flight Vouchers issued before close continue their own lifecycle. The pattern for "publish more under the same Allocation" is a new Offer, not reactivation.

DRAFT → REVIEWED → SUBMITTED → ACTIVE is forward-only (SUBMITTED → DRAFT on validation failure is the only backward transition); ACTIVE ↔ PAUSED is the only bidirectional cycle; CLOSED is terminal.

**Domain events**: `OfferCreated`, `OfferReviewed`, `OfferSubmitted`, `OfferActivated` (publication moment), `OfferPaused`, `OfferClosed`, `OfferPublicationValidationFailed` (reason payload). Standard audit envelope + `actor_role` on each.

*(Note: v1.1's "multi-producer Discovery composite Offer FSM cascade" — any constituent ACTIVE → CLOSED forces the composite to PAUSED — is **deferred with D7** (§6). At launch the cascade re-validation operates on single-Allocation Offers only, §7.3.)*

### §4.3 Offer pricing-surface derivation (DEC-100)

Pricing derives strictly from Allocation `commercial_terms` (DEC-100), by shape:

**`fixed_per_unit`** — Club: `P = value × 100 / 87.5` (inverse of the 12.5% / 87.5% margin, DEC-010). Discovery: `P_d` set on the Offer; `value` = cost `C` per unit; NewCo margin = `P_d − C`. Producer settlement = `value × qty_sold`.

**`percent_of_selling_price`** — Club: producer-set `selling_price`; settlement = `value × selling_price × qty_sold` (canonical 12.5%/87.5% instance, `value = 87.5%`). Discovery: NewCo-set `selling_price` (= `P_d`); admitted but rare.

**Promotion overlay** (DEC-100 + DEC-039) — the `Offer.promotional_price` renders the customer-facing price at the promotional value for the campaign range; Allocation `commercial_terms` is untouched. Club promotions require producer opt-in (`ProducerPromotionConsentGranted`); Discovery promotions are NewCo-unilateral. **Sell-through settlement always reads Allocation `commercial_terms`, not `promotional_price`** — the producer's economic interest is locked at the Allocation level.

### §4.4 Multi-Offer-per-Allocation shared-pool decrement (DEC-099) — FLOOR (no-overselling)

NewCo admits multiple Offers publishing off a single Allocation, all drawing from the shared `Allocation.qty − issued` pool (the canonical: a bottle Offer + a 6-pack case Offer + a Hero Package Offer + an early-bird promotion Offer off one 100-unit Allocation). **Cross-Offer atomicity at sell-through** (DEC-099):

- Each Offer's voucher issuance decrements the **shared** `Allocation.qty − issued`.
- **Over-allocation across Offers is blocked at the issuance operation**: the first Offer to consume the last unit wins; subsequent issuance is **rejected** by Module A's Layer 1 rule (`qty − issued ≥ 0` — Module A §7.1). *(v1.1 named an `AllocationCapacityExhausted` signal here; Module A v0.3-MVP frames this as an operation-level rejection, no event — §0 drift flag.)*
- **Mid-year capacity-increase** (DEC-079, Module A side) reactivates issuance up to the new ceiling; Module S consumes Module A's `AllocationCapacityIncreased` and unblocks issuance against affected Offers.
- **Cross-Offer mutability**: each Offer's FSM / pricing / time-window / granularity / Hero designation / Layer 3 are independent; only sell-through volume is shared.

**Multi-granularity composition**: bottle + case + mixed-package Offers can publish from one Allocation provided each granularity is admissible by the Allocation's `producer_breakability` per case_config (Module A Layer 2); Module S enforces this at publication (DEC-098 rule 5). Hero + non-Hero Offers off one Allocation is admitted (`is_hero_package` is per-Offer).

This is Module S's half of the **two-layer no-overselling guard** (Phase C floor chain 1): the shared-pool decrement is the issuance-side block, composing Module A Layer 1 ∧ Module B Layer 2 (§8.6 storefront ATP).

---

## §5 Hero Package Designation (DEC-096 + DEC-114) — club VP (KEPT)

The Hero Package is NewCo's **structural primitive of membership** (BMD §2.3 + DEC-007) — a producer-curated mixed case released once per club year whose price *is* the annual membership cost. Module S realises it as the **`Offer.is_hero_package` boolean** (DEC-096; a Module S Offer-level designation, NOT a PIM Composite SKU attribute — Module 0 §3.8). The flag defaults `false`; setting it `true` activates three concerns at order completion (DEC-114). **This is the core club value proposition the whole D8 dial protects — KEPT whole.**

### §5.1 Three Hero-Package-conditional concerns

When `Offer.is_hero_package = true`, the order-completion path enforces three concerns beyond the standard sanctions / Hold / capacity gates:

- **Gate 1 — Profile state precondition**: the Customer must have an `Active` (or grace-window `Lapsed` reactivating) Profile for the Hero Package's Club at order completion (Module K §4.2). Lapsed-outside-grace / Cancelled Profiles block; the Customer re-applies (new Profile) before the purchase is admissible.
- **Gate 2 — Single-purchase-per-Profile-per-club-year**: each Profile may purchase the Hero Package once per club year. Specific to `is_hero_package = true`; non-Hero club Offers operate under standard shared-pool semantics without the gate.
- **Gate 3 — Capacity Invariant cross-check**: at order completion, Module S validates the Hero Package Capacity Invariant per **Module K §13** — `count(Active Profiles for the Club) ≤ Allocation.qty` (current runtime value). **The single source of truth is Module A's Allocation `qty`** (Module A §11.4; Module K §13.2 reads it); Module S reads the runtime value at validation time (no caching). Mid-year capacity-increase (DEC-079) admits new purchases up to the new ceiling; capacity-decrease cannot orphan customer-held vouchers (anti-orphan rule).

**Failure on any gate** → `HeroPackagePurchaseRejected` (reason `profile_state_invalid | single_per_year_violated | capacity_invariant_violated`); the order does not transition to CONFIRMED for that line; the Cart Hold releases on the Hero line; cart contents persist (the Customer may remove the Hero line and complete the rest, or retry next club year).

### §5.2 `MembershipFeePaid` emission

When the three-gate check passes **and** the Order completes payment (§9), Module S emits `MembershipFeePaid` alongside `OrderConfirmed` / `VoucherIssued` (DEC-114). Module K consumes it (Module K §15.2/§15.8) to drive `Profile.fee_paid_at`, `ProfileActivated` / `ProfileRenewed`, and Club Credit auto-generation (Module K §11.1). Module S emits at INV1 issuance = post-payment-cleared (DEC-107 + DEC-112), preserving Module K's cash-receipt invariant. The Hero Package payment is a **dual-nature transaction** (a membership state change consumed by Module K + a purchase with voucher issuance internal to Module S); both effects fire from one Order completion:

- `OrderConfirmed` · `InvoiceINV1Issued` · `VoucherIssued` × N (one per constituent bottle, DEC-109) · `MembershipFeePaid` (→ Module K) · `HeroPackagePurchaseAccepted`.
- *(`OriginatingClubLocked` is a **Module K** event — fired by Module K on the Customer's first `MembershipApprovedByProducer` if the Originating Club link was unset; Module K §6.1. NOT a Module S event.)*

### §5.3 Hero Package realisation shape

Hero Offers are typically backed by a `CLUB_ONLY` Allocation under `percent_of_selling_price` with `value = 12.5%`, but the Offer-level designation does not constrain the Allocation's commercial-terms shape. Per DEC-114, the backing Allocation is a normal Allocation — Module A surfaces no Hero-specific attribute or event. Per DEC-109 (1-voucher-per-bottle), a 12-bottle Hero Package yields 12 Vouchers issued at confirmation; each is independently redeemable + independently voidable in the 14-day window. Membership-status implications of partial-Hero cancellation are handled at Module K (DEC-109 — Module K renewal/lapse).

### §5.4 Hero Package + Club Credit interaction

The Hero Package payment **is the membership fee** — it is **not a Club-Credit-eligible line**: a Customer's existing Club Credit is **not redeemable** against the Hero Package line (Hero Offers are scope-excluded from the auto-apply pool — §10.5 + DEC-111). Hero + standard Credit-eligible club Offers can coexist on different lines of one Order (BMD §4.7's single-transaction framing); the Hero line is excluded from the auto-apply pool. Auto-renewal forfeits/replaces prior-period Club Credit at renewal (Module K §11.3); Module S's role is the standard Order completion + payment capture.

---

## §6 Multi-Producer Discovery Composite Offer (DEC-097) — **DEFERRED at launch (D7)**

> **D7 — the headline cut (ratified Q1).** The multi-producer Discovery composite construct — ONE composite Offer reading N constituent Allocations atomically — is **DEFERRED at launch.** Discovery launches with **single-producer Offers**: single-Allocation Discovery Offers + the multi-Offer-per-Allocation multi-granularity pattern (DEC-099, §4.4) + club single-producer mixed-cases (DEC-019). This is the first real net-new Module-layer DEFER of the triage; the saved machinery (the N-way atomic transaction + rollback + composite cascade + N-constituent publication) is genuinely heavy. **The capability deferred = the curated multi-producer mixed-case (the "Tuscany Discovery Case" merchandising format); Discovery as a business pillar otherwise launches whole.**

**The deferred construct** (carried for the roadmap; §20): a multi-producer Discovery composite Offer carries `composite_constituent_allocation_ids[]` as a **multi-FK** list of N constituent Allocations bound atomically at publication (each constituent sourced from its own Producer; DISCOVERY_ONLY — clubs single-producer per DEC-019). Its deferred mechanics:
- **The multi-FK atomic bind** (DEC-097) — one Offer row, N constituents; customer-facing `P_d` on the Offer; per-constituent cost `C_i` read from each constituent `Allocation.commercial_terms.value` at sell-through (read-at-emission); NewCo margin = `P_d − Σ C_i`.
- **The atomic N-way decrement + atomic-rollback at issuance** (DEC-097 + DEC-179) — all N constituent Allocations decrement in one transaction; any constituent exhausted → the entire composite issuance fails atomically (no partial issuance); N `VoucherIssued` events fire.
- **The composite mid-life cascade** — any constituent ACTIVE → CLOSED forces the composite Offer to PAUSED; capacity-decrease / Layer-2 / commercial-terms changes on a constituent re-validate the composite.
- **The 5-rule publication extension × N** (§7.2) — all 5 rules apply to each of the N constituents.
- **The composite OC-on-headline-`P_d`** — the 5% OC share computed once on the composite headline `P_d`, not per-constituent.

**The seam (P1).** The Offer entity ships with `composite_constituent_allocation_ids[]` in **single-FK form** (one constituent — the canonical single-Allocation case); the multi-FK **binding logic** is the deferred-but-additive part. Module A keeps the per-constituent single-Allocation supply primitive + the two-FK `producer_id`/`supplier_id` + per-constituent `commercial_terms` `C_i` (Module A §3.1/§4.1 — "a multi-producer composite is N single-producer Allocations sharing a `supplier_id`; the atomic bind is Module S's"); Module 0 keeps Composite SKU as the producer-agnostic N-constituent bundle structure (Module 0 §3.8). **No downstream orphan**: each constituent voucher of a (future) composite is a normal per-bottle voucher; B / C / D / E see N normal vouchers + N `VoucherIssued` events, never a "composite." Restoration is a coordinated S + A + 0 set (Phase C item N; §20).

**What ships at launch (single-producer, unchanged):** single-Allocation Discovery Offers run the standard single-Allocation cascade (§7.3) + the standard 5-rule validation (§7.1) + the per-Offer OC-on-`P_d` emission for single-Allocation Discovery sales (§10.8 — KEPT; only the *composite* OC variant defers). The OC accrual capture is whole at launch (Phase C item E).

---

## §7 Offer Publication Validation (DEC-098) — FLOOR-adjacent (KEPT)

Module S enforces a **5-rule validation contract** at SUBMITTED → ACTIVE (DEC-098) — the load-bearing publication invariant preventing a sale against a non-sellable / mis-surfaced / mis-priced Allocation. Failure on any rule emits `OfferPublicationValidationFailed` (reason payload) + reverts the Offer to DRAFT.

### §7.1 The five rules

- **Rule 1 — Allocation state ACTIVE** (Module A FSM): every bound Allocation must be `ACTIVE`. Fail → `allocation_state_not_active`.
- **Rule 2 — Visibility match strict** (DEC-076 2-value enum): `Offer.surface = CLUB ↔ Allocation.visibility = CLUB_ONLY`; `DISCOVERY ↔ DISCOVERY_ONLY`. Cross-surface publication rejected. Fail → `visibility_mismatch`.
- **Rule 3 — Serialization alignment** (DEC-080): the Offer's `serialization_type` must be admissible by the Allocation's `non_serialized_offer_admitted` + sub-pool partition. Fail → `serialization_misaligned`.
- **Rule 4 — Commercial terms value populated** (DEC-092): the bound Allocation's `commercial_terms.value` must be non-null. Fail → `commercial_terms_value_null`.
- **Rule 5 — Layer 3 cannot downgrade Layer 2** (Module 0 §7.4): the Offer's Layer 3 cannot mark a case breakable when the Allocation's Layer 2 declares it non-breakable; effective rule = Layer 1 OR Layer 2 OR Layer 3. Fail → `layer_3_downgrade_attempt`.

**Layer-2 propagation as the Layer-3 default + operator-override path** (DEC-098 Stage-6.5): Layer 3 defaults to the bound Allocation's Layer 2 per case_config (operator-without-action publishes matching the producer's declaration). An explicit operator-override (deliberately setting Layer 3 breakable on a Layer-2-unbreakable Allocation) is admitted via Admin Panel with **mandatory reason capture** (`OfferLayer2OverrideRecorded`: Offer ref + Allocation ref + Layer-2 value overridden + operator id + reason). The override is the binding fulfilment rule on the published Offer; producer-relationship management of the override is operating-manual scope.

### §7.2 Composite Offer publication extension — **DEFERRED with D7 (§6)**

For multi-producer Discovery composites (DEC-097), the contract extends so all 5 rules apply to each of the N constituent Allocations, with the atomic mid-life cascade. **This extension is deferred with the composite construct (D7).** At launch, the 5 rules apply to the single bound Allocation (§7.1). The extension restores additively with the composite (§20).

### §7.3 Validation timing + cascade re-validation (KEPT)

- **At publication** (SUBMITTED → ACTIVE): all 5 rules evaluated; pass → `OfferActivated`; fail → `OfferPublicationValidationFailed` + revert to DRAFT.
- **At Allocation state changes** — Module S consumes Module A's `AllocationClosed`, `AllocationVisibilityChanged`, `AllocationCommercialTermsChanged`, `AllocationSubPoolRebalanced`, `AllocationNonSerializedOptOutChanged`, `AllocationCapacityDecreased` and re-validates any ACTIVE Offers backed by the affected Allocation, forcing affected Offers to PAUSED (`OfferPaused`, reason referencing the upstream event) if a rule now fails. *(The composite-specific cascade variant — constituent close → composite PAUSED — defers with D7; the single-Allocation cascade is KEPT.)*
- **At Offer attribute changes mid-life** — rule 5 (Layer 3) re-evaluates on DRAFT/REVIEWED/SUBMITTED Offers; rules 1–4 depend on Allocation state (not re-derivable from Offer attributes alone).

---

## §8 Cart and Cart Hold (DEC-105 + DEC-106 + DEC-049) — FLOOR (no-overselling)

The **Cart** is the customer's working set of Offer line items pre-Checkout (per-Customer; contents persist 48h, v17 §5.7). The **Cart Hold** is a soft reservation against the bound Allocation when an Offer is added — gating on `Allocation.qty − issued`; releasing on expiry. Cart Hold timeout = **15 minutes default, system-wide configurable, NOT per-Offer** (DEC-105).

**Sanctions/Hold uniformity at Cart-add (DEC-181).** Cart-add is a transaction-initiation surface (Module K §4.8 / BR-K-Hold-2): Module S reads `Customer.sanctions_status` (Module K §9.3) + any active `Hold` on the Customer or Profile (Module K §4.8) at the moment of the cart-add action; non-`passed` sanctions or any active Hold blocks the Cart Hold reservation (the Customer cannot reserve scarce capacity while screening is unresolved or a Hold is active). This composes upstream of the §10 order-completion gate — the earliest customer-side compliance-floor touch.

### §8.1 Cart-session vs Cart-Hold

- **Cart session** (48h): cart **contents** persist 48h of inactivity (convenience persistence); cleared after.
- **Cart Hold** (15-min strict, DEC-106): the **Allocation reservation** releases after 15 min regardless of activity. After expiry the Cart line still exists (not removed); on next interaction Module S re-attempts a fresh Hold against the Allocation's current `available_qty` (which may now be 0).

### §8.2 Cart Hold strict-timeout discipline (DEC-106)

The Cart Hold timer is **strict** — customer interaction does NOT reset it. The 15-min window starts at Cart-add and counts down regardless of subsequent activity. Rationale: resetting on every interaction would let long browse sessions indefinitely block other customers from scarce stock; the strict timeout circulates stock.

### §8.3 Bank-transfer payment-method extension (DEC-049)

The **only** payment-method-conditional override at launch is the bank-transfer extension to **7 calendar days**:
- Cart-add → 15-min Hold. At Checkout, selecting `payment_method = bank_transfer` → the Hold timer **extends** to a fresh 7-day countdown (`CartHoldExtended`); the Allocation reservation is held for the window.
- Funds confirmed at Airwallex → Order PENDING_PAYMENT → CONFIRMED (§9); Voucher PENDING_PAYMENT → ISSUED; INV1 fires; the Hold converts (`CartHoldConvertedToOrder`).
- Funds not cleared in 7 days → Hold expires (`CartHoldExpired`); Voucher auto-VOIDS without INV1 (`VoucherVoided`, reason `bank_transfer_timeout`); reservation releases; **no INV1, no financial event.**
- **Switch-back to card** before submit reverts the timer to the original 15-min window (capped at original; the 7-day extension is conditional on proceeding through the bank-transfer flow).

### §8.4 Pricing snapshot at re-add

When a Hold expires and the Customer re-adds the same Offer later, the pricing snapshot is at-that-moment (DEC-038 FX + DEC-100 derivation): the customer-facing price renders at the current Offer surface state (including any active `promotional_price`, current FX, current eligibility); the customer's actual-purchase-moment FX rate is locked at order confirmation (DEC-101), not at Cart-add.

### §8.5 Cart Hold domain events

`CartHoldCreated` (on Cart-add; carries Cart-line ref, bound Allocation ref, holding qty, expiry = `created_at + 15 min` default / `+ 7 days` for bank-transfer) · `CartHoldExtended` (bank-transfer extension; original + new expiry + reason) · `CartHoldExpired` (timer expiry without conversion; released Allocation ref + qty) · `CartHoldConvertedToOrder` (Checkout submit; original Hold ref + new Order ref + Voucher pre-issuance state).

### §8.6 Storefront ATP lesser-of read (DEC-185/187) — FLOOR

The storefront ATP rendered at Offer browse + Cart-add + Checkout is the **lesser of** two layers (DEC-185 + DEC-187 + Q-CL-5):
- **Layer 1 — Module A allocation-pool ATP**: `qty − issued` (Module A §7.1, build-phase 3).
- **Layer 2 — Module B physical-inventory ATP**: per-allocation per-sub-pool ATP (`atp_serialized` + `atp_non_serialized`), exposed by Module B's push pattern + read by Module A's strongly-consistent ATP cache (Module A §11.5.1; Module B Wave 4, build-phase 5).

Module S exposes the **minimum** of the two as available-to-sell per Offer. Both layers must be readable; **both must pass at hold placement / voucher issuance** per the two-layer no-overselling guard. **Per-sub-pool composition**: SERIALIZED Offers read `atp_serialized`; NON_SERIALIZED read `atp_non_serialized`; MIXED compose per-sub-pool against the corresponding surface. Cross-sub-pool fungibility is NOT admitted at hold placement (Module A §7.1 BR-A-SubPool-2). The cache mechanics (push/pull, staleness) are tech-implementation (DEC-073). **Build-sequencing flag (Phase C item G — carried to Phase E):** Module B is build-phase 5, Module S build-phase 4 — confirm Module B's Layer-2 push pipeline is integration-ready when Module S's storefront guard goes live at the integrated launch (a sequencing confirmation, not a cut).

---

## §9 Order FSM and Checkout Flow (DEC-101) — core loop (KEPT)

Module S inherits the Order FSM from v17 §5.6 (DEC-101): a **12-state machine**, with three NewCo simplifications (already in v1.1 — do not re-cut): B2B credit-terms branches deferred (DEC-068); active-consignment branches dropped (DEC-011); CruTrade branches dropped (BMD §4.4). **PENDING_PAYMENT IS the bank-transfer 7-day credit-terms state** (DEC-101) — the load-bearing NewCo refinement; card payments authorize-and-capture in one step (no PENDING_PAYMENT for cards).

### §9.1 Order FSM states

PENDING_PAYMENT (bank-transfer 7-day window — Voucher pre-state; INV1 not yet issued) · PAYMENT_CONFIRMED (payment confirmed at Airwallex; the sanctions/Hold gate per DEC-113 already fired pre-PaymentAuthorization) · CONFIRMED (all gates cleared; INV1 issued; OC share accrued for Discovery; Hero three-gate confirmed; Vouchers issued; storage clock starts) · FULFILLMENT_STARTED · PARTIALLY_FULFILLED · FULFILLED · HOLD_PLACED (an active Module K Hold blocks fulfilment progression) · AMENDMENT_REQUESTED / AMENDMENT_APPROVED / AMENDMENT_REJECTED · CANCELLED (pre-shipment within the 14-day window; per-voucher partial cancellation per DEC-109).

### §9.2 Order FSM transitions (key)

| From | To | Trigger |
|---|---|---|
| (none) | PENDING_PAYMENT (bank-transfer) **or** PAYMENT_CONFIRMED (card) | Customer submits Cart; sanctions/Hold gate clears (DEC-113) |
| PENDING_PAYMENT | PAYMENT_CONFIRMED | Airwallex confirms bank-transfer funds-cleared |
| PENDING_PAYMENT | CANCELLED | 7-day window expires without funds; Voucher VOIDS (no INV1) |
| PAYMENT_CONFIRMED | CONFIRMED | sanctions/Hold re-check; Hero three-gate (if applicable); INV1 (DEC-107); OC accrual (DEC-112, Discovery); Voucher → ISSUED |
| CONFIRMED | FULFILLMENT_STARTED | Customer requests shipment (Voucher ISSUED → REDEMPTION_REQUESTED) |
| FULFILLMENT_STARTED | PARTIALLY_FULFILLED / FULFILLED | first / all Vouchers ship |
| CONFIRMED / PAYMENT_CONFIRMED | HOLD_PLACED | Module K Hold fires post-CONFIRMED; lifts → resume |
| CONFIRMED / PARTIALLY_FULFILLED | CANCELLED | pre-shipment cancellation within the 14-day window (DEC-108); per-voucher (DEC-109) |

### §9.3 Bank-transfer flow (DEC-101)

1. Customer submits with `payment_method = bank_transfer`; sanctions/Hold gate (DEC-113) fires; pass → PENDING_PAYMENT. 2. Cart Hold extends to 7 days (`CartHoldExtended`). 3. Voucher created in PENDING_PAYMENT (non-shippable — Module C gates on state ≥ ISSUED). 4. Module S surfaces bank-transfer instructions. 5. Funds-cleared within window → Order → CONFIRMED; Voucher → ISSUED; INV1 fires; OC share accrues (Discovery); Hold converts. 6. Not cleared in 7 days → Hold expires; Voucher auto-VOIDS (`bank_transfer_timeout`); reservation releases; Order → CANCELLED; **no INV1, no financial event.** Card payments skip PENDING_PAYMENT (auth + capture one step → PAYMENT_CONFIRMED → CONFIRMED with INV1 + OC share in the same transaction).

### §9.4 Single-transaction across club + Discovery + cart (BMD §4.7)

A Checkout can mix club + Discovery + Hero lines in one Order — **one INV1** covering all lines; each line settles per its own commercial mechanic: club lines drive a Producer PO at 87.5% × `P` (DEC-010); Discovery lines settle to the bottle's producer at cost `C` (DEC-032/092); the **5% × `P_d` OC share applies to Discovery lines only** (computed on headline `P_d`, DEC-112); Hero lines apply the three-gate check + emit `MembershipFeePaid`. Each line carries its own event sequence; downstream accounting (Xero) determines per-line treatment from the events (DEC-072).

---

## §10 Checkout Gates and Stacking Algebra

Module S enforces gates + price-resolution during Checkout. Per DEC-073, the PRD names the gates + the events + the chain; tech picks execution + UX.

### §10.1 Sanctions gate + Hold gate at pre-PaymentAuthorization (DEC-113 + Q-AD-22) — FLOOR

**THE consumer-side compliance enforcement point** (Phase C floor chain 2). The gate fires **between OrderPlaced and PaymentAuthorization** — pre-payment, before card auth (or before bank-transfer instructions). Module S reads Module K's **read-API tuple** (`sanctions_status`, active-Hold-list — Module K §4.8.1): non-`passed` `sanctions_status` (`pending | failed | under_review`) blocks order completion + emits `OrderBlockedBySanctionsGate`; any active **Hold** (the six types `admin | kyc | payment | fraud | compliance | credit`, on the Customer or any Order Profile — Module K §4.8) blocks + emits `OrderBlockedByHoldGate`. **No card authorization fires for a blocked Order; no bank-transfer instructions are generated.** **Module K and Module A are sanctions-blind by design** — Module K exposes the read-API tuple + maintains the state; the floor *fires here* (Module K §9.3: "the order-completion gate is the single enforcement point"). *(`OrderBlockedBySanctionsGate` / `OrderBlockedByHoldGate` are Module S's own events; Module K does not emit them.)*

**DEC-181 uniformity.** Order completion is one of the transaction-initiation surfaces; cart-add (§8), Voucher redemption-request (§11.7), and INV3 charge (§14, downstream at Module E) re-read sanctions + Hold at their respective moments. *(The gifting re-read idles with D5 — the generic read at gifting initiation is not exercised at launch; §13.)*

### §10.2 Hero Package three-gate eligibility check (DEC-114)

When the Order contains a Hero line (`is_hero_package = true`), Module S validates the three concerns at order completion (§5.1): Profile state · single-per-club-year · Capacity Invariant (Module K §13, reading Module A `qty`). Failure → `HeroPackagePurchaseRejected`; non-Hero lines in the same Order are unaffected.

### §10.3 Stacking algebra — the 7-step chain (DEC-110), KEPT as the spine; campaign sophistication not-configured (D8)

Module S inherits the v17 §5.14 **7-step price-resolution chain** (DEC-110) — KEPT as the seam (the pipeline is cheap v17-inherited machinery):

1. **Base** — Allocation `commercial_terms` / Price Book lookup (DEC-092 + DEC-100 derivation).
2. **Policy discounts** — *(campaign sophistication — **not-configured-at-launch**; the step remains a no-op seam, D8.)*
3. **Club Credit** — auto-apply (DEC-111; §10.5). **KEPT — core club VP.**
4. **Promo codes / coupons** — single `Coupon` entity (DEC-110); club promos subject to producer opt-in (DEC-039). **KEPT (marketing).**
5. **Volume / early-bird multipliers** — *(campaign sophistication — **not-configured-at-launch**; the step remains a no-op seam, D8.)*
6. **FX conversion** — non-EUR display derived from EUR base (DEC-038); FX rate captured at order confirmation (Q-AD-11), immutable from confirmation; refunds use the same captured rate.
7. **Final price** — immutable at order confirmation.

> **D8 — honest calibration (ratified Q3).** The launch-active interactions are **base → Club Credit → promo coupon (single) → FX → final**. The **policy-discount (step 2) + volume/early-bird-multiplier (step 5) campaign sophistication is not-configured-at-launch** (the steps remain as no-op seams — additive when a campaign needs them). **This is thin — mostly a config/QA posture, not a build cut, because the chain is v17-inherited-and-built.** The 7-step chain + the mutual-exclusivity matrix + the Coupon entity all stay (cheap; REFUND_COMPENSATION is also the D6 goodwill instrument).

**Mutual-exclusivity matrix** (v17 §5.14 + DEC-110): `PROMOTIONAL` coupon + Club Credit = **mutually exclusive**; `REFUND_COMPENSATION` + `PROMOTIONAL` = **mutually exclusive**; `REFUND_COMPENSATION` + Club Credit = **ALLOWED**. **One coupon per checkout.**

### §10.4 Coupon entity (DEC-110)

Attributes (concept-level): coupon code; `coupon_type` (`PROMOTIONAL | REFUND_COMPENSATION`); type (`fixed_amount | percentage`); value; currency; valid_from/to; max_redemptions; status; applicable_channels (CLUB | DISCOVERY | both); applicable_offers. **Authorship**: club promo codes — producer via Portal (deferred write UI, §15) OR NewCo ops via Admin Panel (producer opt-in `ProducerPromotionConsentGranted`); Discovery promo codes — NewCo ops Admin-Panel-only; **`REFUND_COMPENSATION` coupons — NewCo ops Admin-Panel-only (the D6 goodwill instrument; §12).** Event: `PromoCodeApplied`.

### §10.5 Club Credit auto-apply at checkout-render (DEC-111) — club VP (KEPT)

When a Customer has Club Credit on a Profile **AND** the Cart contains ≥1 eligible line (an Offer from that Profile's Club — Module K's strict `credit.profile.club_id ∈ offer.club_ids` match), Module S **auto-applies the credit at checkout-render** up to capacity needed = `min(credit.balance, sum of eligible line totals)`. The customer can remove it via explicit action (voluntary; the credit stays at full balance on Module K's side — Module K §11.2). **No cross-Club credit pooling.** **Hero Package lines are scope-excluded** from the auto-apply pool (§5.4). Module S owns redemption/auto-apply; **Module K owns the Club Credit entity + auto-issuance + the one-active-per-Profile invariant** (Module K §11). Events: `ClubCreditAutoApplied`, `ClubCreditRemovedByCustomer`.

> **D8 — Club Credit carry-forward KEPT (ratified Q2).** Module K's **K.17 partial-redemption + carry-forward** (the **Remaining balance** that carries across purchases — Module K §11) is **KEPT and now exercised at launch** — it is how annual club credit works (members spend it across several purchases through the year). Deferring it would *add* a forfeiture rule (more work, worse customer value). **DEC-043 closure-conversion** (Club Credit → Discovery store credit at face value, 12-month validity, on Club closure) is **KEPT-lean** — owned by Module S; Module K's role ends at the upstream cancellation/closure signal (Module K §11.3). *(K.18 welcome-window scaling + K.19 operator manual issuance are deferred — §1.2; launch is full-fee → full-credit, and launch goodwill routes through the REFUND_COMPENSATION coupon.)*

### §10.6 INV1 emission at order confirmation (DEC-107 + DEC-112) — FLOOR (tax)

`InvoiceINV1Issued` fires at **order confirmation = post-payment-cleared** (DEC-112): card — at order completion; bank-transfer — at funds-cleared (PENDING_PAYMENT → CONFIRMED). **Business signals**: Order ref; Customer ref; Profile ref (club/Hero; null for pure-Discovery); Voucher refs (1-per-bottle); total amount (post-stacking net); currency (FX captured at confirmation); Address ref (optional `company_name` + `vat_id`, DEC-068); OC carve-out ref (Discovery only). **MPV VAT regime (BMD §8.7 + DEC-045): no excise / no destination VAT on INV1** — the destination is unknown under late binding; MPV defers VAT to redemption (INV2). **Hero Package: one INV1 / N `VoucherIssued` / INV2 per shipped constituent.**

### §10.7 INV2 emission at shipment + mid-semester storage roll-in (DEC-107) — FLOOR (tax)

`InvoiceINV2Issued` fires at **shipment** (Voucher REDEMPTION_REQUESTED → SHIPPED; Module C's dispatch is the upstream trigger). **Business signals**: original INV1 ref; Voucher ref; shipping Address ref; **excise amount** (destination, BMD §8.6; pass-through, DEC-045); **destination-jurisdiction VAT** (recognised at INV2 only, MPV); shipping fee. **Mid-semester storage roll-in** (DEC-119 Module-S-internal): when a Voucher ships mid-semester, Module S computes the unbilled storage months on the shipped bottle from its own storage state and adds them as additional INV2 line items in the same transaction (no cross-module query — §14.4). **Ship-on-confirmation**: distinct INV1 + INV2 fire simultaneously (collapsing into one combined event is rejected — DEC-107).

### §10.8 OC 5% × `P_d` emission at INV1 (`DiscoveryRevenueShareAccrued`, DEC-112) — emission KEPT; computation deferred (D19)

`DiscoveryRevenueShareAccrued` fires at **INV1 issuance = post-payment-cleared** (DEC-112) for Discovery sales. **Read-at-emission**: the payload reads the Customer's **Originating Club link** (Module K §6 — Module S's read-reference: `originating_club_id`) → resolves to the **Club's operating-Producer** (Module K's operating-Producer link — Module S's read-reference: `Club.partner_producer_id`) → that Producer is the recipient. **Null-OC payload** (DEC-040): null recipient (no share accrues; full Discovery margin to NewCo). **5% on headline `P_d`** (DEC-110 + BMD §8.14), NOT post-stacking net — NewCo's discount discretion does not reduce the OC share. **Cancellation reversal**: proportional to cancelled vouchers (`DiscoveryRevenueShareReversed`, §12). Per-buyer-per-Order routing is locked at Order time (DEC-161). *(The composite OC-on-`P_d` variant defers with D7, §6; single-Allocation Discovery OC emission is KEPT.)*

> **OC 5% — capture whole at launch; computation deferred-with-settlement (Phase C item E; D19).** The **emission is the seam**: Module K captures the Originating Club link + fires `OriginatingClubLocked` (one-shot, immutable, unreconstructable — Module K §6); Module A preserves the per-constituent lineage the share reads (`commercial_terms` `C_i` + the two-FK `producer_id`/`supplier_id` — Module A §11.7); **Module S emits `DiscoveryRevenueShareAccrued` at INV1** reading K's lock + A's lineage at that one-shot moment; **Module E records the accrual at launch and computes the 5% + settles when the engine is built — reading K's lock + A's lineage, not re-deriving** (D19 operator-run first). **If the accrual were not recorded at INV1 it could not be reconstructed — it is recorded; capture is whole.**

---

## §11 Voucher Entity and State Machine (DEC-102 + DEC-103 + DEC-104 + DEC-109) — FLOOR + D5

The **Voucher** is NewCo's customer-side primitive: the Customer's right to a specific Product Reference at a producer-set (or NewCo-set, Discovery) price, redeemable for shipment. Module S issues Vouchers at order confirmation (DEC-107 + DEC-109) and owns the Voucher state machine through the customer-facing lifecycle; Module B (Wave 4) consumes voucher state for NFT lifecycle; Module C (Wave 4) consumes for fulfilment.

### §11.1 1-voucher-per-bottle invariant (DEC-109) — FLOOR

Vouchers are bottle-granular: **one Voucher row per bottle, regardless of Offer granularity.** A 12-bottle case = 12 Vouchers; a Hero Package = N Vouchers; a single bottle Offer = 1 Voucher. Each Voucher is independently redeemable / voidable / (post-launch) giftable. **This is the load-bearing simplification that makes per-bottle partial refund (DEC-108/109) and per-bottle late binding clean.**

### §11.2 Voucher attributes (conceptual)

**Identity**: Product Reference ref *(wine-display alias: Bottle Reference)* — late binding selects the physical bottle at shipment; bound Allocation ref; Order ref; INV1 ref; Customer ref (**mutable customer-reference — the D5 gifting ownership-transfer seam, §13**); audit identity. **Pricing**: per-bottle amount (proportional split of the Order's per-line price). **State**: the FSM-tracked `state` (§11.3 — 7 states at launch). **Storage-clock attributes** (DEC-119; mechanics from DEC-118):
- **`storage_clock_purchase_anchor`** = INV1 issuance date (anchors the first-12-months-free-from-purchase protection).
- **`storage_clock_warehouse_anchor`** = `InboundEventPhysicallyAccepted` date for the bound Allocation (read-on-event from Module D — §17.4; anchors the bottle-must-be-in-warehouse condition).
- **`storage_accrual_start_date`** = `max(storage_clock_purchase_anchor + 12 months, storage_clock_warehouse_anchor)`; partial month → full month (DEC-118).

Storage accrual stops on Voucher REDEMPTION_REQUESTED / VOIDED / EXPIRED *(GIFTED-accepted is deferred with D5)*.

### §11.3 Voucher state machine — **7 states at launch (GIFTED deferred with D5)** (DEC-102)

> **D5 — the Voucher FSM collapses 8 → 7 states at launch (ratified Q4).** v1.1's 8th state, **GIFTED** (the transfer-pending state for member-to-member gifting), is **deferred with gifting (§13).** The launch FSM is 7 states.

```
PENDING_PAYMENT → ISSUED → REDEMPTION_REQUESTED → SHIPPED → CONSUMED
       ↓             ↓             ↓
     VOIDED       VOIDED         VOIDED
  (7-day timeout) EXPIRED    (refund pre-ship)
```

- **PENDING_PAYMENT** — bank-transfer pre-state (7-day window, DEC-049/101). → ISSUED on funds-cleared (INV1 fires); 7-day timeout → VOIDS without INV1 (`bank_transfer_timeout`). Card payments skip this (→ ISSUED directly). Non-shippable (Module C gates on state ≥ ISSUED).
- **ISSUED** — held in the Customer's cellar; storage fee accrues from `storage_accrual_start_date` (§14); redeemable + voidable in the 14-day pre-shipment window. *(Giftable post-launch — D5.)*
- **REDEMPTION_REQUESTED** — Customer requested shipment (→ Module C pick/pack/dispatch; late binding selects the physical bottle); storage accrual stops; voidable in the 14-day window until the Voucher physically ships.
- **SHIPPED** — dispatched; Module B NFT burn fires (NFT decoupled — D12; non-serialized path is the fallback); `InvoiceINV2Issued` fires (with mid-semester storage roll-in if applicable). **Cancellation right WAIVED from here** (DEC-108 Article-16; §12).
- **CONSUMED** — terminal; delivery confirmation (best-effort).
- **VOIDED** — terminal; sources: 14-day cancellation (`customer_cancellation`); bank-transfer timeout (`bank_transfer_timeout`); refund per the matrix (`producer_fault | newco_fault | carrier_damage | …`); substitution (`VoucherSubstitutionExecuted`); producer recall (only the PENDING_PAYMENT-pre-INV1 collision case — ISSUED+ Vouchers immune, §11.6).
- **EXPIRED** — terminal; reached `Allocation.expiry_date` without redemption (§11.4).

*(GIFTED — deferred with D5. The Voucher's mutable customer-reference is preserved so the GIFTED state re-introduces additively — §13.)*

### §11.4 Voucher EXPIRED mechanics (DEC-103) — KEPT

A scheduled job fires on the bound `Allocation.expiry_date` for any Voucher not yet REDEMPTION_REQUESTED / SHIPPED / CONSUMED / VOIDED *(GIFTED deferred)*. `Allocation.expiry_date` is optional (default null = no expiry; default horizon 10–20yr aligned with fine-wine aging — structurally rare at launch). The Voucher sale completes commercially at INV1; at expiry the redemption right lapses **without refund of the INV1 payment** (customer-fault default = no refund; bottle ownership never transferred — no VAT/duty unwind); NewCo handles physical disposition ad-hoc. `VoucherExpired` fires; `qty − issued` does **not** restore (the slot was consumed; expiry ≠ cancellation). *(AMB-S-3 flags a 10–20yr-vs-optional-null framing tension — an acceptance-authoring backlog item, orthogonal to MVP scope; §22.)*

### §11.5 Voucher reissuance / substitution (DEC-104) — manual at launch (KEPT minimal; do not re-cut)

A **manual operator capability** at launch (already at the floor; full automation already deferred in v1.1 — carry verbatim). The Admin Panel surfaces a `VoucherSubstitutionExecuted` action (operator picks original Voucher → substitute Product Reference + reason; the event records it). The payload carries `customer_consent_mode ∈ {refund, credit, silent}` captured pre-execution (DEC-104 Stage-6.5 — no silent refund-vs-credit-vs-absorption). Operationally rare under passive consignment; full automation (catalogue-driven matching + auto-notification) deferred to roadmap (§20). For non-serialized stock, Module B emits `BottleShippedAsNonSerialized` (informational mirror; null NFT fields) and the OC lineage preserves through the chain.

### §11.6 Voucher state observability for producer recall (DEC-117) — FLOOR-adjacent

Producer recall scope is the **unsold sub-pool only** (`qty − issued`); **ISSUED Vouchers are NOT subject to recall** (INV1 fired, the Customer paid, the holding is committed). Module S **observes** Module A's `AllocationRecallTriggered` (the payload identifies the recalled qty as the unsold portion); Module S does **NOT void any ISSUED Vouchers.** Per-state matrix: PENDING_PAYMENT-pre-INV1 → operator-reviewed (VOIDS without INV1/refund if recall lands before INV1; committed once ISSUED); ISSUED / REDEMPTION_REQUESTED / SHIPPED / CONSUMED → NOT void-targets; VOIDED / EXPIRED → terminal. *(Recall ≠ producer offboarding — offboarding preserves NewCo's commitment to honour outstanding ISSUED Vouchers; substitution per DEC-104 may apply.)* Matches Module A A.15 + Module D event-record-only recall (manual recall, D15).

### §11.7 Voucher domain events

- **`VoucherIssued`** — fires on Voucher creation (PENDING_PAYMENT for bank-transfer, or directly ISSUED for card). Carries Voucher id, Order ref, Customer ref, Product Reference ref, bound Allocation ref, INV1 ref (null until INV1 fires). **Consumers: Module D** (the V1/V2 PI auto-fire trigger — fires on the ISSUED transition, post-payment-cleared; **AND the sell-through signal driving Module D's PO PRODUCER→NEWCO title transition** — item F, §17.4); **Module B** (serialization on serialized stock); the cellar render. *(See §16.4 / §17.4 for the forward-consistency contract with Module D.)*
- **`VoucherRedemptionRequested`** — fires on shipment request. Carries Voucher ref, shipping Address ref, timestamp. Consumer: Module C (pick/pack/dispatch). **DEC-181 sanctions/Hold re-read** — non-`passed` sanctions or any active Hold blocks emission (Module C's SO draft→planned re-check is defence-in-depth).
- **`VoucherShipped`** — fires when dispatched (Module C's dispatch consumed). Carries Voucher ref, INV2 ref, the shipped bottle's serial / NFT identity (serialized stock, late binding). Consumers: Module B (NFT burn — decoupled, D12); customer notification. **Available as the shipment-keyed title leg for Module D (item F)** — not currently wired to a title transition (the sale-keyed `VoucherIssued` is).
- **`VoucherConsumed`** — fires on delivery confirmation (best-effort).
- **`VoucherVoided`** — fires on VOIDED. Carries Voucher ref, void reason (`customer_cancellation | bank_transfer_timeout | producer_fault | newco_fault | carrier_damage | substitution | …`), void actor. **Consumer: Module D** (cancels a V1 PI → `ProcurementIntentCancelled`, trigger source `voucher_voided` — §17.4).
- **`VoucherExpired`** — fires on EXPIRED. Carries Voucher ref, expiry trigger context.
- **`VoucherSubstitutionExecuted`** — fires on substitution (§11.5).

*(Deferred with D5: `VoucherGifted` / the `VoucherGift*` family — §13.)*

---

## §12 Cancellation and Refund (DEC-108 + DEC-109) — legal floor KEPT; D6 matrix SIMPLIFY

Module S owns the customer-facing cancellation surface. **KEEP the legal floor whole** (DEC-108: cancellation pre-shipment only within the 14-day window from INV1; post-shipment WAIVED per EU Distance Contracts Article 16; DEC-109: per-voucher under 1-voucher-per-bottle). **SIMPLIFY the refund-cost-matrix decisioning to manual-first (D6).**

### §12.1 14-day pre-shipment cancellation window (DEC-108) — FLOOR (legal)

The 14-day timer starts at **INV1 issuance** (card — at order completion; bank-transfer — at funds-cleared). The window applies **only pre-shipment** — the Customer can cancel while the Voucher is PENDING_PAYMENT, ISSUED, or REDEMPTION_REQUESTED. **Once REDEMPTION_REQUESTED → SHIPPED, the cancellation right is WAIVED** (DEC-108).

### §12.2 Post-shipment WAIVER rationale (DEC-108)

Returning shipped wine compromises provenance + temperature integrity (the cold chain breaks at hand-off; NewCo does not resell returned bottles); reverse-logistics cost is prohibitive at scale. The WAIVER is permitted under **EU Distance Contracts Directive 2011/83/EU Article 16** (goods liable to deteriorate or expire rapidly). The customer-facing T&C must disclose the waiver-at-shipment rule (the disclosure UX is downstream, DEC-073; the substantive policy posture is the PRD commitment). Launch jurisdictions EU/UK/CH (D3) are covered.

### §12.3 Post-shipment-issue handling (Module C returns + replacement) — KEPT

Damage / loss / fault discovered **after** shipment is handled via the **Module C returns + replacement flow**, NOT Module S cancellation: NewCo issues a replacement shipment (no new Voucher; no new INV2 — the original entitlement preserved); the replacement is recorded as a non-revenue event by Module E (producer-fault claims clawed back from settlement — **deferred-with-settlement, D19**; carrier-damage via carrier insurance; in-custody breakage absorbed). **Exceptional post-delivery refunds require supervisor override** (`SupervisorOverridePostDeliveryRefund` — supervisor identity, reason, amount; the only R&R-adjacent admission — a single audit-event surface, KEEP-lean).

### §12.4 Partial refund per voucher (DEC-109) — FLOOR

Cancelling one Voucher in a multi-voucher Order = void that Voucher + refund the per-bottle amount (proportional split); `qty − issued` restored per voucher (the unsold pool grows by 1); Order → PARTIALLY_FULFILLED (or stays CONFIRMED). **Hero Package partial refund**: the N constituent Vouchers are individually voidable in the window; membership-status implications are handled at Module K (DEC-109). **Module D V1/V2 PI cancellation**: voiding a Voucher in the window cascades to Module D via **`VoucherVoided`** → Module D transitions the bound DRAFT/COMMITTED V1 PI to CANCELLED (`ProcurementIntentCancelled`); Module E records the refund financial-event chain.

### §12.5 Refund forms (DEC-025 + DEC-044) — **D6: manual-first decisioning; legal floor KEPT**

> **D6 — KEEP the mechanism; SIMPLIFY the matrix decisioning (ratified Q5).** The refund **mechanism** is KEPT (void + per-bottle refund to original payment, FX-correct). The **refund-cost-matrix sophistication** — the DEC-025 multi-cause routing, the DEC-044 store-credit-105% goodwill decisioning, the producer-fault clawback netting — is **SIMPLIFIED to manual-first operator handling at launch**: the operator records the refund + cause, and offers store-credit-105% by judgment via the **REFUND_COMPENSATION coupon** (§10.4). The **producer-fault clawback netting is deferred-with-settlement** (D19, Module E). **Seam:** the cause taxonomy + the REFUND_COMPENSATION coupon + the refund event payloads are **retained**; the automated routing/netting is additive (§20). **The legal floor is whole; the simplification is in ops sophistication, not consumer rights.**

Forms: default = full refund to original payment (100% face value, original captured FX rate, DEC-038); partial where partial responsibility (per-voucher proportional, DEC-109); **store-credit alternative** at an admin-configurable goodwill premium (default **105%**) — the Customer can always opt for cash at 100%; the premium is the upside for accepting store credit. The `Coupon` with `coupon_type = REFUND_COMPENSATION` is the Module S realisation of the goodwill store credit (NewCo-ops Admin-Panel-authored).

### §12.6 Storage-fee pro-rata refund (DEC-046) — KEEP-lean (Module-S-internal)

When part of a customer's cellar is refunded, accrued storage fee on the refunded item is refunded **pro-rata** back to the bottle's storage-clock start **where the underlying refund cause warrants it** (NewCo-fault breakage → yes; customer-fraud → no) — cause-conditional. For `customer_cancellation_pre_shipment` specifically, storage refund covers **bottle cost only** (storage generally has not accrued in the window — 12-month-free starts at INV1). Module S reads its own storage-fee history natively (no cross-module query — DEC-119) and emits `StorageFeeProRataRefundIssued`; Module E records + routes to Xero (DEC-072). *(The cause decisioning is part of the D6 manual-first simplification; the storage pro-rata computation itself is cheap + Module-S-internal — KEEP-lean.)*

### §12.7 Cancellation domain events

`OrderCancelled` (rare under per-voucher partial cancellation) · `OrderRefunded` (amount, form `cash | store_credit_with_premium`, cause) · `VoucherVoided` (per voucher) · `InvoiceINV1PartialRefundIssued` (original INV1 + cancelled-line amount) · `DiscoveryRevenueShareReversed` (proportional OC reversal + original accrual ref) · `StorageFeeProRataRefundIssued` (bottle ref, amount, period span) · `SupervisorOverridePostDeliveryRefund` (supervisor identity, reason, amount, Voucher ref). Per DEC-072, Module E + Xero decide accounting treatment.

---

## §13 Gifting (DEC-116) — **DEFERRED at launch (D5)**

> **D5 — gifting is a clean in-module defer (ratified Q4).** Member-to-member gifting is **not in the core loop** (browse / buy / pay / ship / cellar). **DEFER** the GIFTED voucher state (§11.3), the 7-day accept flow, the recipient-gate validation, and the four `VoucherGift*` events. **The Voucher FSM collapses 8 → 7 states at launch.**

**The deferred mechanism** (carried for the roadmap; §20): v1.1 gifting inherits v17 Module A §12 with NewCo gates — a 7-day recipient accept window (Voucher locked PENDING_TRANSFER); no financial event (the original INV1 stands; Allocation lineage preserved); recipient gates (registered NewCo Customer + KYC `passed` + Offer-eligibility match + the DEC-181 sanctions/Hold read on both giver and recipient); Originating Club preservation (the giver's Originating Club link stays with the gifted voucher — BMD §4.13); the events `VoucherGiftInitiated` / `VoucherGiftAccepted` / `VoucherGiftDeclined` / `VoucherGiftExpired`. Only ISSUED Vouchers are giftable; terminal/in-flight Vouchers are not.

**The seam (P1).** Preserve the Voucher's **ownership-transfer capability** — the customer-reference is **mutable** (§11.2), with **no hard single-permanent-owner assumption** — so member-to-member gifting is an additive post-launch build. The recipient-KYC + Originating-Club-preservation hooks ride on the kept Voucher `originating_club_id`. **No orphan across the composed system** (Phase C item N — gifting restores as a coordinated S + K + C set): Module K's gifting-init read-API idles (ratified not-exercised); Module C's `is_gift` sub-flag idles; HubSpot gift notifications idle. Broader C2C/P2P resale (incl. CruTrade) is already deferred — do not re-cut (§20).

---

## §14 Storage-Fee Computation and INV3 Issuance — Module-S-Owned (DEC-119; supersedes DEC-118 ownership clause) — FLOOR (tax) + R2

Storage fees are **owned by Module S** (DEC-119; all DEC-118 mechanics preserved — rate, cadence, partial-month rounding, first-12-months-free, mid-semester INV2 roll-in, INV3 as the third customer-facing invoice). Module S owns the storage-fee computation, the per-bottle accrual events, the semi-annual INV3 issuance, the mid-semester INV2 roll-in (Module-S-internal), and the pro-rata refund (Module-S-internal). **D22 KEEP storage-only** (already lean — paid services/experiences + INV4 already deferred; do not re-cut). Module E (Wave 5) consumes the invoice events + routes to Xero + executes the Airwallex charge — uniform with INV1/INV2.

> **R2 (DEC-119) — the single storage cross-module read; no bidirectional S↔E.** The **one** cross-module coordination on storage fees at launch is the single **Module D → Module S** read of **`InboundEventPhysicallyAccepted`** (the storage-clock warehouse anchor — §14.7). **This is materially simpler than DEC-118's prior bidirectional Module S ↔ Module E contract at INV2 issuance.** The §14 body + the acceptance doc already carry this correct framing; the residual stale DEC-118 "bidirectional" text in **BR-S-CrossModule-4 (§18.16)** is reconciled to DEC-119 (R2; mirrors Module D's DEC-183 fix). Naming/contract only — no behaviour change.

### §14.1 Module S role on storage fees (DEC-119) — the three-actor split

Module S owns end-to-end: storage-clock-start computation (`storage_accrual_start_date` per §11.2 — Module-S-native + the single Module D read); per-bottle `StorageFeeAccrued` emission (monthly per Voucher, after the 12-month-free + bottle-at-warehouse double condition); semi-annual `InvoiceINV3Issued` (end-June + end-December); mid-semester INV2 roll-in (Module-S-internal); pro-rata refund (Module-S-internal); customer-account-history rendering across INV1/INV2/INV3 natively. **The DEC-119 three-actor split**: Module S = **EVENT** (decides WHEN INV1/INV2/INV3 fire); Xero = **ARTIFACT** (PDF + numbering + legal text); Module E = **PAYMENT + ACCOUNTING RECORD + Xero ROUTING** (records the financial event + routes to Xero + executes the Airwallex charge). Module S does NOT own Airwallex payment-execution, Xero GL, or rate-card config (Finance team config; read at compute time).

**Sanctions/Hold gate at INV3 charge (DEC-181).** INV3 charge execution is a transaction-initiation surface; Module E reads sanctions + Hold at the moment of charge. **Storage accrual continues unconditionally regardless of Customer Hold state** (storage is bottle-in-custody, not customer-state-dependent); the gate applies to charge execution, not accrual emission. **Multi-cycle composition under a prior-cycle storage Hold** (DEC-160): (a) cadence continues unconditionally; (b) `StoragePaymentSucceeded` for the current INV3 lifts the Hold tied to that cycle only (Module K's `STORAGE_PAYMENT_FAILED` Hold — manual-first at launch, D4 deferred, Phase C N2); (c) each cycle's failed INV3 runs its own escalation chain independently. *(Failed-charge dunning automation is deferred — D4; Phase C N2: the chargeback Hold trigger is automated, the storage-payment Hold trigger is manual-first.)*

### §14.2 Storage-clock-start trigger (DEC-119)

`storage_accrual_start_date = max(storage_clock_purchase_anchor + 12 months, storage_clock_warehouse_anchor)` (§11.2); partial month → full month. **Sourcing-model collapse**: V2 (pre-positioned, NewCo default) → `INV1 + 12 months` (warehouse anchor earlier); V1 fast-ship → `INV1 + 12 months`; V1 slow-ship (rare) → warehouse anchor wins; **Direct Purchase in-transit → `max(INV1 + 12 months, InboundEventPhysicallyAccepted)` — the arm idles at launch (Direct Purchase deferred, Phase C item I; the read is the same event for V1/V2).** Preserves the first-12-months-free protection + the bottle-must-be-in-warehouse condition.

### §14.3 Per-bottle accrual + semi-annual INV3 cycle

Module S accrues `StorageFeeAccrued` monthly per Voucher (€0.25/bottle/month — €3/bottle/year, DEC-118) once `storage_accrual_start_date` is reached and the Voucher is not yet terminal-from-storage. At semester-end (end-June + end-December) Module S aggregates the prior 6 months per Customer and emits `InvoiceINV3Issued`, excluding any months already rolled into a prior INV2 (mid-semester carve-out — §14.4). **A bottle's storage costs always appear on exactly one customer-facing invoice** (INV3 if in custody through semester-end; INV2 if it ships during the semester). Module E + Airwallex execute the saved-payment-method charge.

### §14.4 Mid-semester INV2 storage roll-in (Module-S-internal)

When a Voucher ships mid-semester, Module S internally computes the unbilled storage months (from the last INV3 cycle, or from `storage_accrual_start_date`, to the shipment date; partial-month rounding) and adds them as additional INV2 line items in the same transaction as the primary INV2 line; after shipment no further accrual fires. **Module-S-internal — no cross-module query** (Module S has all storage state natively). Mid-semester boundary days (June 30 / Dec 31) are inclusive.

### §14.5 Customer-account-history rendering

Module S renders the unified INV1 / INV2 / INV3 invoice history on the cellar surface (all three Module-S-emitted; full history native). The cellar shows per-bottle storage accrual in real-time from `storage_accrual_start_date`. UX is downstream (DEC-073); the PRD commitment is the data ownership.

### §14.6 Storage-fee pro-rata refund (Module-S-internal)

At refund (§12.6), Module S computes the pro-rata amount internally (the Voucher's storage-fee history is Module-S-native — no cross-module query); `StorageFeeProRataRefundIssued` fires; Module E records + routes to Xero (DEC-072). Producer-fault clawback follows the refund-cost-matrix path (deferred-with-settlement, D19).

### §14.7 Cross-module read — Module D → Module S (R2 / DEC-119)

The single storage cross-module read: **`InboundEventPhysicallyAccepted`** (Module D §16.1) — Module S subscribes for the bound Allocation's stock-arrival; the event date populates the Voucher's `storage_clock_warehouse_anchor` (§11.2). For V2 (NewCo default): the event fires at allocation activation, well before any Voucher is issued; Module S records the date once at the Allocation level (subsequent Vouchers read the same anchor). For V1 / (deferred) Direct-Purchase-in-transit: the event fires after issuance; Module S records the date on arrival and re-derives `storage_accrual_start_date`. **Module D side has no change** (its PRD lists `InboundEventPhysicallyAccepted` consumers as Module B / C / A; **Module S asserts this storage-clock subscription on its own side** — the read is additive, consistent with cut-sheet S.29). Module C consumes the same event for its shipment gate (DEC-081) — a parallel consumer.

---

## §15 Producer Portal ↔ Admin Panel Parity for Offer-Level Operations (DEC-115) — L-PP

Module S inherits the **Producer-Portal ↔ Admin-Panel parity** principle (DEC-083; extended to Offer-level ops by DEC-115). The parity is a **contract-level statement** about which operations are exposable from which surface, with `actor_role` audit discipline. **L-PP (P2): Module S retains ZERO producer writes at launch** (identical to Module A A.14/A.17 + Module D §3.6).

### §15.1 Club Offers — backend parity KEPT; producer write UI deferred

Every Module S Offer-level operation on **club Offers** (`Offer.surface = CLUB`) is exposable from BOTH surfaces at the contract level (`actor_role: producer` Producer Portal / `actor_role: newco_ops` Admin Panel): Offer creation; submission for review; publication (the 5-rule validation); pause / re-publication; close; promotional-pricing overlay set/clear (producer opt-in for club promos, DEC-039); Hero designation; Layer 3; granularity; time-window; eligibility-filter config. **At launch, all are operator-driven via the Admin Panel** — the **Producer-Portal Offer-authoring write UI is deferred (L-PP / P2)**. Because DEC-115/083 parity is a **backend contract, no backend capability is cut**; the producer write UI builds post-launch on the same backend (§20).

### §15.2 Discovery Offers — Admin-Panel-only at launch

Discovery Offers are **NewCo Admin-Panel-only** (DEC-115 carve-out — no producer write exists): the constituent Allocation(s), `P_d`, granularity / eligibility / time-window, and promotional pricing are NewCo's commercial discretion (DEC-039). **Multi-producer composite curation is deferred (D7, §6); single-producer Discovery Offers ship.** Every Discovery Offer event carries `actor_role: newco_ops`.

### §15.3 Audit trail + consumer storefront exemption

Every Offer-level event (§16) carries `actor_role: producer | newco_ops` + the standard audit envelope (DEC-115 + DEC-083). **The consumer storefront is EXEMPT (kickoff §3): browse / buy / cart / checkout / cellar / cancellation are self-serve KEPT.** Producer Portal **read + reporting** (D23) is KEPT (reads Module S sell-through / Offer data). Voucher substitution / cancellation / supervisor-override are operator actions by definition (back-office Customer Care). State propagation between surfaces is downstream tech (DEC-073).

---

## §16 Domain Event Catalogue

Module S emits a versioned set of domain events. Per DEC-073, payload field-by-field listings are out of scope; this lists names + one-line business signals. Every event carries the standard audit envelope (event id, source-entity ref, timestamp, actor, `actor_role` where applicable). **The catalogue is category-neutral — unchanged by the naming cascade (§21); only BR-referencing payload/prose renames `Bottle Reference → Product Reference`.** *(Deferred families: the `VoucherGift*` family defers with D5; composite-specific emissions defer with D7 — both as additive seams, §20.)*

### §16.1 Offer-family

`OfferCreated` · `OfferReviewed` · `OfferSubmitted` · `OfferActivated` (publication moment) · `OfferPaused` · `OfferClosed` · `OfferPublicationValidationFailed` (reason: `allocation_state_not_active | visibility_mismatch | serialization_misaligned | commercial_terms_value_null | layer_3_downgrade_attempt`) · `OfferPromotionalPriceSet` / `OfferPromotionalPriceCleared` · `OfferHeroPackageDesignated` · `OfferLayer2OverrideRecorded` (§7.1).

### §16.2 Cart-family

`CartHoldCreated` · `CartHoldExtended` · `CartHoldExpired` · `CartHoldConvertedToOrder` (§8.5).

### §16.3 Order-family

`OrderPlaced` · `OrderBlockedBySanctionsGate` · `OrderBlockedByHoldGate` *(Module S's own gate events — Module K exposes the read-API tuple, §10.1)* · `OrderPaymentAuthorized` (card) · `OrderPaymentCaptured` (card capture / bank-transfer funds-cleared) · `OrderPaymentPending` (bank-transfer PENDING_PAYMENT) · `OrderPaymentFailed` · `OrderConfirmed` · `OrderCancelled` · `OrderShippedToFulfillment` · `OrderRefunded`.

### §16.4 Voucher-family — **the three Module-D-owed names discharge here**

Per §11.7: `VoucherIssued` · `VoucherRedemptionRequested` · `VoucherShipped` · `VoucherConsumed` · `VoucherVoided` · `VoucherExpired` · `VoucherSubstitutionExecuted`.

> **The three Module-D-owed Wave-3 event names — discharged (Q6); all existing Module S events, no net-new.** (1) **`VoucherIssued`** = the V1/V2 ProcurementIntent auto-fire trigger (fires on the ISSUED transition, post-payment-cleared). (2) **`VoucherIssued`** = the sell-through signal driving Module D's PO PRODUCER→NEWCO **title** transition — **there is NO separate `SellThroughRecorded` event** (resolving AMB-D-3); `VoucherShipped` is available for a shipment-keyed title leg. (3) **`VoucherVoided`** = the PI-cancel signal (→ `ProcurementIntentCancelled`, trigger source `voucher_voided`). **Module D's drafted PRD consumes these names exactly (D §14.4 / §16.4); Module S emits them consistently — the forward-consistency obligation. Take no accounting position on the title timing (DEC-072 / Phase C item F).**

### §16.5 Gifting events — **DEFERRED with D5 (§13)**

`VoucherGiftInitiated` / `VoucherGiftAccepted` / `VoucherGiftDeclined` / `VoucherGiftExpired` — deferred to the roadmap (§20); restore as a coordinated S + K + C set.

### §16.6 Hero Package

`HeroPackagePurchaseAccepted` · `HeroPackagePurchaseRejected` (reason: `profile_state_invalid | single_per_year_violated | capacity_invariant_violated`) · `MembershipFeePaid` (→ Module K; §5.2).

### §16.7 Discovery / OC share

`DiscoveryRevenueShareAccrued` (INV1 issuance; Customer's Originating Club link [`originating_club_id`], 5% × headline `P_d`, recipient Producer via the Club's operating-Producer link; null-OC payload) · `DiscoveryRevenueShareReversed` (cancellation; proportional). *(Composite OC variant defers with D7.)*

### §16.8 Promotion / discount / credit

`PromoCodeApplied` · `StoreCreditApplied` · `ClubCreditAutoApplied` · `ClubCreditRemovedByCustomer` · `ProducerPromotionConsentGranted`.

### §16.9 Customer-facing invoice events (Module S emits all three per DEC-119)

`InvoiceINV1Issued` (§10.6) · `InvoiceINV2Issued` (§10.7) · **`InvoiceINV3Issued`** (semester-end; **emitted by Module S** per DEC-119, NOT consumed from Module E as in DEC-118) · `InvoiceINV1PartialRefundIssued`.

### §16.10 Storage-fee accrual

**`StorageFeeAccrued`** (monthly per Voucher; **emitted by Module S** per DEC-119; €0.25/month, running total, partial-month flag; fires only after the 12-month-free + bottle-at-warehouse double condition).

### §16.11 Refund / cancellation

Per §12.7: `OrderRefunded` · `VoucherVoided` · `InvoiceINV1PartialRefundIssued` · `DiscoveryRevenueShareReversed` · `StorageFeeProRataRefundIssued` · `SupervisorOverridePostDeliveryRefund`.

### §16.12 Naming, ordering, versioning

Lifecycle events use entity-prefix + state-suffix; operator-driven events use verb names; invoice-emission uses the `Invoice*Issued` pattern. Cascading events within one business transaction fire in causal order (e.g., card completion: `OrderPlaced` → sanctions/Hold gate → `OrderPaymentAuthorized` → `OrderPaymentCaptured` → `OrderConfirmed` → `InvoiceINV1Issued` + `DiscoveryRevenueShareAccrued` [Discovery] + `MembershipFeePaid` [Hero] + `VoucherIssued` × N + `CartHoldConvertedToOrder`). Events are schema-versioned; consumers (D / B / C / E / HubSpot) evolve independently within a major version.

---

## §17 Cross-Module Event Chains and Contracts

Per DEC-074, contracts in NewCo prose. The naming cascade (§21) renames only the Module-0-catalog-identity reads; Module S's own names + every sibling's own names are unchanged.

### §17.1 Module 0 (PIM) — read

- **Product Reference identity** *(wine-display alias: Bottle Reference)*: read at Offer line composition + Voucher issuance (Module 0 §3.4).
- **Composite SKU shape**: read for the (deferred) multi-producer composite seam + single-producer bundles (Module 0 §3.8 — KEPT, the D7 seam).
- **Layer 1 product-variant breakability**: read at publication validation rule 5 (Module 0 §7.4).
- **PR `active` state**: read at Offer creation (a `retired` PR cannot back a new Offer; consumed Module 0 events `ProductReferenceActivated` / `ProductReferenceRetired` — renamed from `BottleReference*`, §21).

### §17.2 Module K (Parties) — read

- **Customer identity** (at Order placement); **the Originating Club link** (Module K §6 — read-at-emission at INV1 for OC share, Module S read-reference `originating_club_id`); **`sanctions_status`** (Module K §9.3 — at the pre-PaymentAuthorization gate); **the active-Hold set** (Module K §4.8 — at the same gate); **Profile state** (Module K §4.2 — club eligibility + Hero gate 1); **the Hero Package Capacity Invariant** (Module K §13 — Hero gate 3, reading Module A `qty`); **Club Credit balance + `credit.profile.club_id`** (Module K §11 — at checkout-render for auto-apply, strict `credit.profile.club_id ∈ offer.club_ids` match).
- **Event consumption**: `OriginatingClubLocked` (Module K §6.1 — OC reference availability); `ProfileActivated` / `ProfileExpired` / `ProfileRenewed` / `ProfileSuspended` / `ProfileReactivated` (club + Hero eligibility); `CustomerHoldPlaced` / `CustomerHoldLifted` (Hold-gate state changes; Order HOLD_PLACED transitions); `ClubSunset` / `ClubClosed` (Offer cascade; **Club Credit → Discovery store-credit conversion per DEC-043** — Module S records the converted credit, §10.5). *(Module K is sanctions-blind — it exposes the read-API tuple; Module S enforces at order completion.)*

### §17.3 Module A (Allocation) — read + consume cascade

One of the three load-bearing cross-module contracts. **Module S reads** at publication validation + every voucher-issuance: `Allocation.state` (rule 1); `visibility` (rule 2); `commercial_terms.shape × value` (rule 4 + pricing); `non_serialized_offer_admitted` (rule 3); **`qty − issued`** (the shared-pool decrement — over-issuance rejected at the issuance operation, Module A Layer 1 §7.1); `expiry_date` (EXPIRED); `producer_breakability` per case_config (rule 5). **Storefront ATP lesser-of**: min(Layer 1 `qty − issued`, Layer 2 `atp_serialized`/`atp_non_serialized`) per sub-pool (§8.6; Module A §7.1 + §11.5.1). *(`composite_constituent_allocation_ids[]` multi-FK read defers with D7.)*

**Module A event consumption** (the cascade re-validation, §7.3): `AllocationActivated` (Offers can publish); `AllocationCapacityIncreased` / `AllocationCapacityDecreased` (re-derive the Cart-Hold / Offer ceiling; anti-orphan rule prevents below-issued decrease); `AllocationVisibilityChanged` (re-validate rule 2 → PAUSE mismatches); `AllocationCommercialTermsChanged` (re-render pricing); `AllocationSubPoolRebalanced` / `AllocationNonSerializedOptOutChanged` (re-validate rule 3); `AllocationClosed` (force ACTIVE Offers to PAUSED → CLOSED — the single-Allocation cascade; the composite-constituent cascade defers with D7); `AllocationRetired` (no new Offers); **`AllocationRecallTriggered`** (Module S observes the recall scope — unsold sub-pool only; does NOT void ISSUED Vouchers, §11.6 — asserted Module-S-side). *(Note: v1.1's `AllocationCapacityExhausted` event is not defined in Module A v0.3-MVP; over-issuance is an operation-level rejection — §0 drift flag.)*

### §17.4 Module D (Procurement / Inbound) — emit + observe — **the forward-consistency contract**

Module S emits the upstream signals Module D's drafted PRD consumes exactly (D §14.4 / §16.4 — D leads as the just-drafted consumer; S matches as the emitter):

- **`VoucherIssued` → Module D**: (a) the **V1/V2 ProcurementIntent auto-fire trigger** (voucher issuance against a V1/V2 Allocation auto-fires Module D's PI creation; fires on the ISSUED transition, post-payment-cleared); (b) the **sell-through signal driving Module D's PO PRODUCER→NEWCO title transition** (item F — **NO separate `SellThroughRecorded` event**; `VoucherShipped` available for a shipment-keyed title leg). **Take no accounting position on the title timing (DEC-072).** *(N3 — distinct from the inventory `ownership_flag` PRODUCER→CRURATED transition, which Module B keys to `SupplierPaymentCompleted`; two distinct ledgers, same real-world party: PO-level title `NEWCO` [Module D] vs inventory flag `CRURATED` [Module B]. Module S emits `VoucherIssued`; it takes no position on either ledger's accounting.)*
- **`VoucherVoided` → Module D**: Module D consumes it and transitions any DRAFT/COMMITTED V1 PI bound to the voided voucher to CANCELLED (`ProcurementIntentCancelled`, trigger source `voucher_voided`). V2 + (deferred) Direct-Purchase PIs are not auto-cancelled.
- **`InboundEventPhysicallyAccepted` ← Module D** (the storage-clock warehouse anchor, R2 / §14.7): Module S subscribes for the bound Allocation's stock-arrival; the read is asserted Module-S-side (Module D's PRD lists B/C/A as consumers).
- **Recall observability** (§11.6): Module S observes Module A's `AllocationRecallTriggered`; the recall scope is unsold-only; ISSUED Vouchers immune; Module D records the reverse-inbound for the unsold portion only.
- *(`SupplierPaymentCompleted` is **E-emitted / D-consumed** (R4); it has **no Allocation-FSM role** (R1, DEC-183 — activation is uniform operator-publish). It is **not a Module S event** and Module S takes no position on it — the v1.1 "Module S observes it indirectly" prose is dropped as moot.)*

### §17.5 Module B (Provenance — Wave 4) — emit downstream trigger

`VoucherShipped` → Module B NFT burn at shipment for serialized stock (BMD §6.7; **NFT decoupled — D12; the non-serialized path is the universal fallback**). Module B reads Voucher state + the Allocation sub-pool partition to drive serialization; Module B does not edit Module S Voucher state. For non-serialized stock, Module B emits `BottleShippedAsNonSerialized` (informational mirror; §11.5).

### §17.6 Module C (Fulfilment — Wave 4) — emit downstream trigger + observe

`VoucherRedemptionRequested` → Module C pick / pack / dispatch (late binding selects the physical bottle). Module C's dispatch → Module S transitions REDEMPTION_REQUESTED → SHIPPED + emits `VoucherShipped` + `InvoiceINV2Issued` (with mid-semester storage roll-in). **Module C shipment gate** (DEC-081): Module C reads Module D's `InboundEventPhysicallyAccepted` as the shipment gate (decoupled from Module S's sellability gate `Allocation.state = ACTIVE`); Module S surfaces "in transit; ETA X" on Vouchers awaiting physical receipt (Phase C item K — the in-transit redemption-block is FLOOR; carrier-ETA-precision deferred D17). **Returns + replacement** (§12.3) route via Module C, not Module S cancellation.

### §17.7 Module E (Finance — Wave 5) — emit financial events (DEC-072 / DEC-119 three-actor split)

Module S **emits** (consumed by Module E for accounting integration — Module S takes no accounting positions, DEC-072): `InvoiceINV1Issued`, `InvoiceINV2Issued`, **`InvoiceINV3Issued`** (DEC-119 — Module-S-emitted), **`StorageFeeAccrued`** (DEC-119 — Module-S-emitted), `InvoiceINV1PartialRefundIssued`, `OrderRefunded`, `DiscoveryRevenueShareAccrued` / `DiscoveryRevenueShareReversed`, `MembershipFeePaid` (also consumed by Module K), `StorageFeeProRataRefundIssued`, `SupervisorOverridePostDeliveryRefund`. **Module E's role on customer-facing invoices is simplified per DEC-119** (consume Module S's `Invoice*Issued` + route to Xero + execute the Airwallex charge — the bidirectional INV2 contract is replaced by Module-S-internal computation — R2). **Module E retains** supplier-side settlement events (the 5% OC computation + producer settlement — **deferred-with-settlement, D19, reading K's lock + A's lineage, not re-deriving**) + failed-charge handling for INV3 (the chargeback Hold trigger automated D21; the storage-payment Hold trigger manual-first D4 — Phase C N2). Module E does not edit Module S state.

### §17.8 HubSpot — communication delivery

HubSpot consumes Module S customer-facing events and delivers outbound comms: order confirmation (`OrderConfirmed`), shipment (`VoucherShipped`), cancellation (`OrderCancelled` / `VoucherVoided`), refund (`OrderRefunded` / `InvoiceINV1PartialRefundIssued`), voucher-expiry warnings (`VoucherExpired`). *(Gift notifications idle with D5.)* **Module S does NOT send communications directly** (BMD §11.5 + Module K §14.9).

---

## §18 Business Rules and Invariants

Rules prefixed `BR-S-{Domain}-NN`. Tech-implementation enforcement is downstream (DEC-073). *(BRs for the deferred constructs — composite publication BR-S-Publication-6, gifting BR-S-Gifting-1..4, the GIFTED Voucher state — are retained-but-deferred-with-feature; see §20. The launch-active BRs are below; the R2 reconciliation lands at §18.16.)*

### §18.1 Identity and uniqueness

- **BR-S-Identity-1**: every Offer / Cart / Order / Voucher carries a unique opaque identifier.
- **BR-S-Identity-2**: every Offer references at least one bound Allocation (single-FK at launch; the multi-FK composite form defers — §6).
- **BR-S-Identity-3**: every Voucher references exactly one Product Reference (1-voucher-per-bottle, DEC-109) + exactly one bound Allocation.
- **BR-S-Identity-4**: every Order references at least one Voucher (a 12-bottle case → 12; a Hero Package → N).

### §18.2 Offer entity and FSM

- **BR-S-Offer-1 (entity boundary)**: Offer is a separate first-class entity (DEC-095); cardinality N:1 at launch (multi-Offer-per-Allocation per DEC-099). *(The N:M composite form defers — §6.)*
- **BR-S-Offer-2 (FSM monotonicity)**: DRAFT → REVIEWED → SUBMITTED → ACTIVE forward-only; SUBMITTED → DRAFT the only backward transition; ACTIVE ↔ PAUSED bidirectional; CLOSED terminal.
- **BR-S-Offer-3 (Layer 3 immutability post-active)**: per DEC-098 rule 5 + v17 §5.2.
- **BR-S-Offer-4 (`is_hero_package` immutability post-active)**: per v17 §5.2.
- **BR-S-Offer-5 (single coupon per checkout)**: per DEC-110.
- *(BR-S-Offer-6, v1.1 "composite Offer cascade to PAUSED" — deferred with D7, §6.)*

### §18.3 Publication validation (DEC-098)

- **BR-S-Publication-1..5**: Allocation state ACTIVE / visibility match strict / serialization alignment / `commercial_terms.value` populated / Layer 3 cannot downgrade Layer 2.
- *(BR-S-Publication-6, composite Offer publication × N — deferred with D7, §6/§7.2; retained-but-deferred.)*

### §18.4 Cart Hold

- **BR-S-CartHold-1 (15-min default, system-wide)** · **-2 (strict timeout, no reset)** · **-3 (bank-transfer 7-day extension, the only override)** · **-4 (48h cart-session vs 15-min hold)**.

### §18.5 Order FSM (DEC-101)

- **BR-S-Order-1 (12-state inheritance + NewCo simplifications)** · **-2 (PENDING_PAYMENT IS bank-transfer credit-terms; cards skip it)** · **-3 (7-day timeout auto-VOID, no INV1, no financial event)** · **-4 (single-transaction across club + Discovery + cart)**.

### §18.6 Sanctions / Hold gate (DEC-113 + Q-AD-22) — FLOOR

- **BR-S-Gate-1 (sanctions gate pre-PaymentAuthorization)** — non-`passed` `sanctions_status` blocks order completion (THE consumer-side enforcement point; Module K + Module A sanctions-blind).
- **BR-S-Gate-2 (Hold gate pre-PaymentAuthorization)** — any active Hold (`admin | kyc | payment | fraud | compliance | credit`) on Customer or Profile blocks.
- **BR-S-Gate-3 (Hero three-gate eligibility)** — Profile state + single-per-club-year + Capacity Invariant.

### §18.7 Stacking algebra (DEC-110)

- **BR-S-Stacking-1 (7-step chain)** — KEPT as the spine; *the policy-discount (step 2) + volume/early-bird-multiplier (step 5) campaign sophistication is not-configured-at-launch (no-op seams — D8).*
- **BR-S-Stacking-2 (mutual-exclusivity matrix; one coupon per checkout)** · **-3 (OC share on headline `P_d`)** · **-4 (FX captured at confirmation, immutable; refunds at the captured rate)**.

### §18.8 Club Credit auto-apply (DEC-111)

- **BR-S-ClubCredit-1 (auto-apply at checkout-render, `min(credit.balance, eligible line totals)`)** · **-2 (customer can remove; voluntary)** · **-3 (no cross-Club pooling; strict `credit.profile.club_id ∈ offer.club_ids`)** · **-4 (Hero exclusion)**. *(K.17 carry-forward — the Remaining balance — KEPT, now exercised at launch; K.18/K.19 deferred, §10.5.)*

### §18.9 INV1 / INV2 / INV3 emission (DEC-107 + DEC-112 + DEC-119)

- **BR-S-Invoice-1 (INV1 at order confirmation post-payment-cleared)** · **-2 (MPV: no excise/VAT on INV1; VAT at INV2)** · **-3 (INV2 at shipment with mid-semester storage roll-in, Module-S-internal)** · **-4 (Hero: one INV1, N `VoucherIssued`, INV2 per shipped constituent)** · **-5 (ship-on-confirmation: distinct INV1 + INV2)** · **-6 (INV3 at semester-end, Module S emits per DEC-119)** · **-7 (one customer-facing invoice per bottle's storage months)**.

### §18.10 Storage-fee accrual (DEC-118 mechanics + DEC-119 ownership)

- **BR-S-Storage-1 (Module S owns computation + INV3 + accrual events, DEC-119)** · **-2 (`storage_accrual_start_date = max(INV1 + 12mo, InboundEventPhysicallyAccepted)`)** · **-3 (€0.25/month)** · **-4 (partial month → full month)** · **-5 (`StorageFeeAccrued` monthly; stops on terminal-from-storage)** · **-6 (semi-annual INV3)** · **-7 (mid-semester INV2 roll-in, Module-S-internal)** · **-8 (one invoice per bottle's storage months)** · **-9 (pro-rata refund cause-conditional)** · **-10 (single Module D `InboundEventPhysicallyAccepted` read — R2)**.

### §18.11 OC share emission (DEC-112)

- **BR-S-OCShare-1 (`DiscoveryRevenueShareAccrued` at INV1 = post-payment-cleared)** · **-2 (read-at-emission of the Originating Club link → operating-Producer; null-OC allowed)** · **-3 (5% × headline `P_d`)** · **-4 (cancellation reversal proportional to vouchers)** · **-5 (gifting preservation — deferred with D5; the seam is the kept Voucher `originating_club_id`)**. *(The 5% computation defers-with-settlement, D19; the emission/capture is KEPT whole.)*

### §18.12 Voucher state machine (DEC-102 + DEC-103 + DEC-109)

- **BR-S-Voucher-1 (1-voucher-per-bottle)** · **-2 (7-state machine at launch — GIFTED deferred with D5)** · **-3 (PENDING_PAYMENT non-shippable)** · **-4 (EXPIRED trigger; `Allocation.expiry_date` optional)** · **-5 (substitution manual at launch)** · **-6 (recall scope unsold-only; ISSUED immune)** · **-7 (terminal Vouchers not transferable — the gifting rule; retained-but-deferred with D5)**.

### §18.13 Cancellation and refund (DEC-108 + DEC-109) — legal floor KEPT; D6 matrix manual-first

- **BR-S-Cancellation-1 (14-day pre-shipment window from INV1)** — FLOOR · **-2 (post-shipment WAIVER, Article 16)** — FLOOR · **-3 (per-voucher partial refund)** — FLOOR · **-4 (post-delivery issues via Module C returns + replacement)** · **-5 (exceptional post-delivery refund supervisor override)** · **-6 (storage-fee pro-rata refund cause-conditional)**. *(The DEC-025 cause-routing + DEC-044 goodwill-105% decisioning + producer-fault clawback netting are manual-first at launch — D6; the cause taxonomy + REFUND_COMPENSATION coupon + event payloads retained; netting deferred-with-settlement, D19.)*

### §18.14 Gifting (DEC-116) — **DEFERRED with D5 (§13)**

- *(BR-S-Gifting-1..4 — 7-day accept window / recipient gates / Originating-Club preservation / no financial event — retained-but-deferred-with-feature; §20. The seam is the kept Voucher mutable customer-reference + `originating_club_id`.)*

### §18.15 Producer Portal ↔ Admin Panel parity (DEC-115) — L-PP

- **BR-S-Parity-1 (club Offers parity-shared — backend; producer write UI deferred at launch)** · **-2 (Discovery Offers Admin-Panel-only)** · **-3 (`actor_role` on every Offer-level event)**.

### §18.16 Cross-module dependency — **R2 reconciled here**

- **BR-S-CrossModule-1 (Allocation read at Offer creation + publication + voucher issuance)**: Module A is the upstream supply primitive.
- **BR-S-CrossModule-2 (`VoucherIssued` triggers Module D PI for V1/V2)**: Module D consumes the upstream signal.
- **BR-S-CrossModule-3 (Voucher state observability for recall; unsold-only; ISSUED immune)**.
- **BR-S-CrossModule-4 (storage is Module-S-internal — single Module D → S read of `InboundEventPhysicallyAccepted`; no bidirectional S↔E at INV2)** — **RECONCILED to DEC-119 (R2).** *Was (v1.1, stale DEC-118): "Module E coordination on storage fees — bidirectional contract at INV2 issuance." The §14 body + the acceptance doc already carry the DEC-119 framing; this BR text is reconciled to match. Naming/contract only — no behaviour change. Mirrors Module D's DEC-183 reconciliation.*
- **BR-S-CrossModule-5 (HubSpot owns outbound communication)**.
- **BR-S-CrossModule-6 (Module K reads — sanctions/Hold/Profile/Originating-Club/Club-Credit; Module S does NOT edit Module K state)**.
- **BR-S-CrossModule-7 (Module 0 reads — Product Reference / Composite SKU / Layer 1; Module S does NOT edit Module 0 state)**.

---

## §19 Module Boundary Notes — what Module S does NOT do

- **Allocation entity / FSM / operations / sub-pool / Layer 2 / sourcing-model attribute** — Module A. Module S consumes Allocation state; emits voucher-issuance signals; observes `AllocationRecallTriggered`.
- **ProcurementIntent / PO / InboundEvent / ConsignmentReceipt / ReverseInboundEvent / SupplierProducerLink / supplier-payment / landed-cost** — Module D. Module S's `VoucherIssued` is the V1/V2 PI auto-fire + sell-through signal; `VoucherVoided` cancels a V1 PI. *(`SupplierPaymentCompleted` is E-emitted/D-consumed — not a Module S concern, R1/R4.)*
- **NFC / NFT / serialization / Bottle-Page** — Module B. Module S's `VoucherShipped` triggers NFT burn (decoupled — D12).
- **Pick / pack / dispatch / late-binding / cellar-render / in-transit display / delivery / reverse logistics** — Module C. Module S's `VoucherRedemptionRequested` triggers fulfilment.
- **Airwallex payment-execution (INV1/INV2/INV3 capture)** — Module E + Airwallex. Module S issues the invoices.
- **Xero GL / settlement-statement generation / supplier settlement payment** — Module E + Xero (DEC-072). Module S records customer-facing financial events.
- **Customer / Profile / Club / Producer / Supplier / ProducerAgreement / Hold / Originating-Club entities / Capacity-Invariant storage** — Module K. Module S reads; does NOT edit.
- **Product Master / Variant / Reference / Sellable SKU / Composite SKU / Format / Case Configuration** — Module 0. Module S references identity; does not duplicate.
- **Active consignment / drop-ship / B2B credit-terms / liquid-voucher / CruTrade / agency sourcing** — OUT (BMD §13). No attributes/events/branches.
- **Producer-author Discovery Offers** — Admin-Panel-only (DEC-115). **Full automation of substitution** — manual at launch (DEC-104).
- *(Gifting — deferred at launch, D5, §13. The multi-producer composite construct — deferred at launch, D7, §6.)*

---

## §20 Deferred set & post-launch roadmap pointers (MVP)

Every deferred/simplified item names its seam (P1) + points to `04-roadmap/Post_Launch_Roadmap_v0.1.md`. **Net-new MVP deferrals** restore as coordinated sets where cross-module (Phase C item N).

### §20.1 Net-new MVP deferrals / simplifications (this PRD)

- **D7 — multi-producer Discovery composite construct (§6).** Deferred: the multi-FK atomic bind (DEC-097), the N-way atomic decrement + rollback (DEC-179), the composite cascade, the 5-rule × N extension (§7.2), the composite OC-on-`P_d`. **Seam:** Offer entity ships single-FK-capable; Module A per-constituent primitive; Module 0 Composite SKU. **Restores as a coordinated S + A + 0 set.**
- **D5 — gifting (§13).** Deferred: GIFTED state, 7-day accept flow, recipient-gate validation, the four `VoucherGift*` events; Voucher FSM 8→7. **Seam:** the Voucher mutable customer-reference + `originating_club_id`. **Restores as a coordinated S + K + C set.**
- **D8 — K.18 welcome-window proportional scaling + K.19 operator manual Club-Credit issuance (§10.5).** **Seam:** the `policy × (fee_paid/full_fee)` formula + the manual-create path retained in Module K; launch goodwill via the REFUND_COMPENSATION coupon. **K.17 carry-forward + DEC-043 closure-conversion KEPT.**
- **D8 — stacking campaign sophistication (§10.3).** The policy-discount (step 2) + volume/early-bird-multiplier (step 5) are not-configured-at-launch (no-op seams; the chain is KEPT). *(Thin — a config/QA posture, not a build cut.)*
- **D6 — the automated refund-cost-matrix routing + producer-fault clawback netting (§12.5).** **Seam:** the cause taxonomy + the REFUND_COMPENSATION coupon + the refund event payloads retained; manual-first at launch; the netting defers-with-settlement (D19). **The legal floor is KEPT whole.**
- **L-PP — the Producer-Portal Offer-authoring write UIs (§15).** **Seam:** the DEC-115/083 backend parity is unchanged — no backend cut; the producer write UI builds post-launch on the same backend. **(Part of the full Admin-Panel buildout target — Phase C item L.)**
- **OC 5% computation + producer settlement (§10.8).** Deferred-with-settlement (D19, Module E); the emission/capture is KEPT whole (the seam: K's `OriginatingClubLocked` + A's lineage + S's INV1 accrual + E's recording).

### §20.2 v1.1 already-deferred / future-flex set (carried verbatim — do NOT re-cut)

CruTrade P2P / ON_CRUTRADE + C2C/P2P resale (BMD §13.5); liquid voucher RESOLVED + BottlingResolution N:M reissuance + BOUGHT_BACK (BMD §13.4); B2B credit-terms Order branches (DEC-068); active consignment (DEC-011); drop-shipping (BMD §13.3); producer-author Discovery Offers (DEC-115 carve-out); voucher-substitution full automation (DEC-104); loyalty/referral (BMD §4.12); multi-currency producer-quoted pricing (BMD §4.8); paid services/experiences + INV4 (BMD §4.14); death/inheritance/corporate-dissolution (BMD §9.13); AI Copilot (DEC-021); multi-tier club eligibility (DEC-062); waitlist/FIFO sophistication (DEC-069/079); native mobile (DEC-018); support tooling beyond email + admin (OQ-5). Each retains its v1.1 re-introduction seam.

---

## §21 Naming-cascade application (Phase C item A)

Module 0 v0.3-MVP §18 is the **source-of-truth** name table; this section records how those names land in Module S — and what does NOT rename. The change is **naming/contract only — zero behaviour change** (every event carries the same business signal; BR/PR denote the same key). **Cascade position: Module 0 → A / D → S (here) → B / C → E.**

**What renames in Module S (the PR-referencing / Module-0-event-consuming prose only):**

| Touchpoint | v1.1 prose | v0.3-MVP prose | Wine-display alias retained |
|---|---|---|---|
| §4 Offer line items | "**Bottle Reference (BR)**" reference | "**Product Reference (PR)**" reference | Bottle Reference / BR |
| §11.2 Voucher identity | "Bottle Reference reference" | "Product Reference reference" | Bottle Reference / BR |
| §17.1 Module 0 reads (cross-reads) | "Wine Master / Wine Variant / Bottle Reference"; "Layer 1 wine-variant breakability" | "Product Master / Product Variant / Product Reference"; "Layer 1 product-variant breakability" | Wine Master; Wine Variant; Bottle Reference |
| §11.5, §17.1 consumed Module 0 events | `BottleReferenceActivated` / `BottleReferenceRetired`; `Wine*` | `ProductReferenceActivated` / `ProductReferenceRetired`; `Product*` | — |

**What does NOT rename in Module S (the carve-outs — Phase C item A):**
- **Module S's own entity/event/attribute names** are already **category-neutral — unchanged**: `Offer*`, `Cart*`, `Order*`, `Voucher*` (incl. `VoucherIssued` / `VoucherShipped` / `VoucherVoided`), `Invoice*`, `DiscoveryRevenueShare*`, `ClubCredit*`, `StorageFee*`, `Coupon`, `HeroPackage*`, `MembershipFeePaid`, `Cart Hold`, the Offer/Order/Voucher FSM state names, etc.
- **Module S's consumed sibling event names** are unchanged — `Allocation*` (Module A), `SupplierPaymentCompleted` / `InboundEventPhysicallyAccepted` (Module D — physical-unit / category-neutral names retained), the Module K `Profile*` / `OriginatingClubLocked` / `Customer*Hold*` / `Club*` events.
- **Composite SKU** (Module 0) is retained as the **D7 seam** — not renamed.
- **"Bottle Reference"** is retained **everywhere** as a wine-display alias for Product Reference.

**Rule of thumb:** rename only the PR-referencing / Module-0-event-consuming prose to the canonical names (payload semantics identical); keep Module S's own `Offer*` / `Cart*` / `Order*` / `Voucher*` / `Invoice*` names and every sibling's own names alone.

---

## §22 v1.1 inheritance & MVP re-baseline trace (audit appendix)

This appendix preserves the audit trail of Module S v0.3-MVP against its **frozen v1.1 predecessor** ([`../../reference/v1.1/01-prd/Module_S_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_S_PRD_v0.2.md), whose §21 carries the v17 §5.x inheritance trace) + the **ratified cut-sheet** + the **Phase C reconciliation**. The load-bearing prose is the body above (DEC-074); this trace is for audit / diff.

> **Section-numbering note.** Module S is **KEEP-heavy on the floor + club VP + commerce spine, with two whole sections deferred-not-deleted** (§6 composite, §13 gifting), so **§0–§19 keep their v1.1 meaning** — the acceptance doc's PRD §-anchors (§4.2 Offer FSM, §9.1 Order FSM, §11.3 Voucher FSM, §14 storage, §16 events, §18 BRs, §19 boundaries, etc.) **remain valid against this PRD.** v1.1's §0 Executive Summary slot is **repurposed to §0 "MVP scope at a glance"** (the MVP framing — the executive-summary-level content it carried is distributed across §0 + the body). **§6** (composite) and **§13** (gifting) are **retained as section anchors but reframed as deferred-with-seam** (the construct prose is carried for the roadmap; the launch path is in §4 / §11). **§20** (v1.1 "Out of Scope at Launch") is **repurposed to "Deferred set & post-launch roadmap pointers (MVP)"** — it folds in v1.1's deferred set verbatim + adds the net-new MVP deferrals. **§21** = NEW (naming-cascade application — *v1.1's §21 v17-inheritance trace lives in the frozen v0.2 §21*); **§22** = NEW (this trace); **§23** = cross-references. v1.1's Appendix A divergence summary lives in the frozen v0.2 (DEC-074: the body restates the substance).

| v0.3-MVP section | v1.1 (v0.2) anchor | Cut-sheet / Phase C | MVP disposition |
|---|---|---|---|
| §0 MVP scope at a glance | §0 Executive Summary (repurposed) | cut-sheet §1; Phase C §1 | NEW framing — cut-heavy verdict; floor + club VP + spine whole. |
| §1 Module Scope | §1 | cut-sheet §2 | KEEP; deferred items flagged (D7/D5/D8/D6/L-PP) + cascade. |
| §2 Personas | §2 | cut-sheet §3.6 | KEEP; L-PP operator framing; gifting persona idles. |
| §3 Architecture — Offer entity | §3 | cut-sheet S.1; Q1 | KEEP; single-FK seam note (D7). |
| §4 Offer Entity | §4 | S.1–S.4; DEC-095/099/100 | KEEP; `composite_constituent_allocation_ids[]` single-FK (D7 seam); cascade (PR). |
| §5 Hero Package | §5 | S.5; DEC-096/114 | KEEP (club VP); reads Module A `qty` / Module K §13. |
| §6 Multi-Producer Composite | §6 | S.6–S.8; **Q1 / D7** | **DEFERRED** — construct carried for roadmap; single-FK seam; no downstream orphan. |
| §7 Publication Validation | §7 | S.9/S.10; DEC-098 | KEEP; §7.2 composite extension defers with D7. |
| §8 Cart + Cart Hold | §8 | S.11/S.12; DEC-105/106/049/185/187 | KEEP — FLOOR; storefront ATP lesser-of; build-sequencing flag. |
| §9 Order FSM + Checkout | §9 | S.13/S.14; DEC-101 | KEEP. |
| §10 Checkout Gates + Stacking | §10 | S.15–S.19; **Q2/Q3 / D8** | KEEP floor (sanctions gate, INV1, OC emission); stacking spine KEPT, campaign sophistication not-configured; Club-Credit carry-forward KEPT, K.18/K.19 deferred. |
| §11 Voucher Entity + FSM | §11 | S.20–S.25; **Q4 / D5** | KEEP — FLOOR; **FSM 8→7 (GIFTED deferred)**; recall observability. |
| §12 Cancellation + Refund | §12 | S.26a–c; **Q5 / D6** | KEEP legal floor; refund-matrix decisioning manual-first. |
| §13 Gifting | §13 | S.27; **Q4 / D5** | **DEFERRED** — ownership-transfer seam; restores S+K+C. |
| §14 Storage + INV3 | §14 | S.28–S.30; **Q7 / R2 / DEC-119** | KEEP — FLOOR; Module-S-internal; single Module D read; R2 framing (body already correct). |
| §15 Parity (L-PP) | §15 | S.31; **Q8** | KEEP backend; producer write UIs deferred; storefront exempt. |
| §16 Domain Event Catalogue | §16 | S.32/S.33; **Q6** | KEEP + GENERALISE; GIFTED/composite families defer; **the three voucher-event names discharge (no `SellThroughRecorded`)**. |
| §17 Cross-Module Contracts | §17 | S.34; R2/R4/item F; DEC-185/187 | KEEP; naming cascade on §17.1; **§17.4 forward-consistency with Module D**; R2 §17.7. |
| §18 Business Rules | §18 | S.* | KEEP; **§18.16 BR-S-CrossModule-4 RECONCILED (R2)**; deferred BRs retained-but-deferred. |
| §19 Module Boundary Notes | §19 | S.35 | KEEP. |
| §20 Deferred set & roadmap (MVP) | §20 (repurposed) | S.36; Phase C item N | Folds v1.1 already-deferred verbatim + the net-new MVP deferrals. |
| §21 Naming-cascade application | — (NEW) | Phase C item A | NEW. |
| §22 MVP re-baseline trace | — (NEW) | — | NEW (this table). |
| §23 Cross-references | §21→frozen v0.2 | — | NEW pointer. |

**Notation.** *KEEP* = carried at full fidelity. *cascade* = naming-only (PR/Product). *RECONCILE* = contract-consistency fix, no behaviour change (R2). *deferred (D-dial)* = moved to the roadmap with a named seam. *NEW* = Phase-D MVP apparatus.

---

## §23 Cross-references

- **Frozen v1.1 predecessor** (audit/diff anchor; never edited): [`../../reference/v1.1/01-prd/Module_S_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_S_PRD_v0.2.md) (§21 carries the v17 §5.x trace) + [`../../reference/v1.1/01-prd/Module_S_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_S_Acceptance_v0.1.md).
- **Ratified scope source**: [`../01-triage/Module_S_CutSheet_v0.1.md`](../01-triage/Module_S_CutSheet_v0.1.md) (§2 scope / §3 changes / §5 acceptance delta / §6 Q1–Q8).
- **Coherence gate**: [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) (R2 §5-R2; items D/E/F/G/I; floor §6).
- **Source-of-truth names**: [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18; Composite SKU §3.8.
- **Settled siblings**: [`Module_K_PRD_v0.3-MVP.md`](Module_K_PRD_v0.3-MVP.md) (§6 OC lock; §11 Club Credit; §13 Hero Capacity Invariant; §4.8/§9.3 sanctions/Hold) · [`Module_A_PRD_v0.3-MVP.md`](Module_A_PRD_v0.3-MVP.md) (§3.1/§4.1 per-constituent primitive; §7.1 Layer 1; §11.4 Hero `qty`; §11.7 OC lineage) · [`Module_D_PRD_v0.3-MVP.md`](Module_D_PRD_v0.3-MVP.md) (§14.4 `VoucherIssued`/`VoucherVoided`; §16.1 `InboundEventPhysicallyAccepted`; §3.5 item F / N3 / R4).
- **MVP decisions register**: [`../04-decisions/MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md).
- **Testable companion**: [`../03-acceptance/Module_S_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_S_Acceptance_v0.3-MVP.md).

---

*End of Module S PRD v0.3-MVP — Phase D re-baseline. **RATIFIED by Paolo 2026-06-08.** The first genuinely cut-heavy module: D7 (defer the multi-producer composite construct), D5 (defer gifting; FSM 8→7), D8 K.18/K.19 (defer two club-credit peripherals), D6 (simplify the refund-cost matrix → manual-first) are real net-new Module-S deferrals/simplifies — yet the consumer core-loop floor (browse/buy/pay, the sanctions/Hold gate at order completion, tax-correct INV1/INV2/INV3, no-overselling shared-pool + lesser-of ATP, 1-voucher-per-bottle, the Voucher FSM) and the club VP (Hero → Club Credit → redeem, carry-forward KEPT) stay whole. R2 (DEC-119 BR-S-CrossModule-4) landed; the three voucher-event names discharged (`VoucherIssued` / `VoucherIssued` / `VoucherVoided` — no `SellThroughRecorded`, consistent with Module D's consumer side); the OC-5% emission KEPT (computation deferred, D19); the naming cascade applied. Nothing handed off until Phase E.*
