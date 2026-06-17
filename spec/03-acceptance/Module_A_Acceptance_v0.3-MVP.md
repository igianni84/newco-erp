# NewCo ERP — Module A (Allocation) Acceptance Criteria — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP acceptance contract for Module A; re-cut from the PAOLO-VALIDATED v0.1)
- **Date**: 2026-06-07
- **Status**: **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E. The acceptance delta is **very light** — Module A is **KEEP-in-full + naming cascade** (net spec deferrals ~0): this doc (a) applies the **naming cascade** (Bottle Reference → Product Reference, Wine Variant → Product Variant) to the catalog-identity criteria, (b) re-anchors to the v0.3-MVP PRD, (c) **annotates** the Direct-Purchase criteria *not-exercised-at-launch* + **re-scopes** the L-PP parity criteria to the Admin-Panel operator surface (both retained, not deleted), and (d) adds a small **§6.9 MVP re-baseline** section. **No criterion in launch scope is removed; all floor criteria stand unchanged.**
- **Owner**: Paolo (product sign-off authority)
- **Companion spec**: [`../02-prd/Module_A_PRD_v0.3-MVP.md`](../02-prd/Module_A_PRD_v0.3-MVP.md) — the source of truth this document validates against. The PRD says *what to build*; this document says *what passes*. Together they are the dev-team's complete brief for the launch-MVP Module A.
- **Predecessor (re-cut from)**: [`../../reference/v1.1/01-prd/Module_A_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_A_Acceptance_v0.1.md) — the **PAOLO-VALIDATED** (2026-05-15) v1.1 acceptance template (111 criteria; 91.9% AUTO; format locked). `greenfield/` is frozen (plan R4); this is a derivative under `mvp/`.
- **Audience** (three concurrent uses): **Paolo** at module-delivery sign-off (verdict report + spot-checks); **dev team** during build (the definition of done, read alongside the PRD from day one); **AI coding agents** during code generation (AUTO criteria as fitness functions in the build loop).
- **Purpose**: the demonstrable behaviours that, taken together, constitute "Module A is delivered as specified per v0.3-MVP." Each criterion is traceable to a PRD anchor (BR-A-* / event / FSM transition / DEC / §) and tagged AUTO / MIXED / HUMAN.
- **Methodology DECs binding this document**: DEC-072 (no-accounting-policy claims — Module A emits business-signal events; Module E records; Xero decides GL), DEC-073 (product-spec layer; criteria are business-behaviour, not tech-implementation — incl. the **ATP-cache mechanics**, cut-sheet Q4), DEC-074 (self-contained; anchors restated inline), DEC-075/076/077/080/083/092/099/183/185/187/190 (the load-bearing Module A design decisions), `feedback_prd_rr_approval` (operator approval-tier policy admin-configurable — out of scope), `feedback_prd_product_not_tech`.
- **What this document is NOT**: engineering Definition of Done (coverage thresholds, performance budgets, retry/idempotency mechanics, schema design, the ATP-cache literal store / latency thresholds); UI / UX acceptance (Admin Panel + Producer Portal layouts, navigation, validation copy, accessibility, responsive design); operational R&R / approval-tier *policy* (admin-configurable); non-functional concerns not anchored to a BR/DEC at PRD level.

---

## §0 What changed from v0.1 (the re-cut delta)

Module A is **KEEP-in-full + naming cascade** with **net spec deferrals ~0** — the genuine launch-scope reductions are *seamed, not cut* (Direct-Purchase *use*; the L-PP producer-write UIs) and the headline D7 lever is *forwarded to Module S*. So this acceptance re-cut is mechanical and additive:

1. **Naming cascade applied to the catalog-identity criteria** (Phase C item A): `Bottle Reference → Product Reference`, `Wine Variant → Product Variant` in **AC-A-J-1, AC-A-J-7, AC-A-BR-Identity-3, AC-A-BR-CrossModule-1, AC-A-XM-1, AC-A-XM-2, AC-A-EVT-18** (`BottleReferenceActivated/Retired → ProductReferenceActivated/Retired`). **Wine-display aliases** ("Bottle Reference / BR," "Wine Variant") are retained where they aid wine-facing readers. **Behaviour is identical** — every renamed criterion tests the same business behaviour as its v0.1 original. **Module A's own `Allocation*` event/attribute names are unchanged** (category-neutral). See AC-A-MVP-1.
2. **Re-anchored to the v0.3-MVP PRD.** PRD §-numbers now refer to [`../02-prd/Module_A_PRD_v0.3-MVP.md`](../02-prd/Module_A_PRD_v0.3-MVP.md). **Module A had no structural entity insertion (KEEP-in-full), so the body §-anchors (§1–§13) are unchanged from v1.1** — every existing AC anchor remains valid as-is. Only §0 was prepended and the trailing sections repurposed (§14 deferred set; §15 naming-cascade application; §16 trace; §17 cross-refs).
3. **Direct-Purchase criteria annotated NOT-EXERCISED-AT-LAUNCH → roadmap (D11 / Phase C item I — see §0.1):** **AC-A-J-5** (Direct-Purchase activation chain), **AC-A-XM-7** (the 8-step cross-module trace), and the **Direct-Purchase arms** of **AC-A-EVT-6** (follow-on PO on capacity increase) + **AC-A-EVT-9** (post-supplier-payment counterparty change). The criteria are **retained, not deleted** — the `direct_purchase` enum value + the uniform operator-publish FSM (DEC-183) are the seam; the criteria verify the behaviour when Direct Purchase is re-enabled. **The V1/V2 activation criteria (AC-A-J-3, AC-A-J-4) stand and are exercised at launch.**
4. **L-PP parity criteria re-scoped to the Admin-Panel operator surface (Q3 — producer write UIs deferred):** **AC-A-J-11** (DEC-083 operation parity) + **AC-A-EVT-17** (`actor_role`) are verified at launch against the **Admin-Panel operator surface** (every operation `actor_role: newco_ops`); the Producer-Portal write-surface half is **deferred-with-seam** (backend parity unchanged; producer write UI already out of acceptance scope per §7). **No backend criterion changes** (DEC-083 admin-parity is a backend contract). See AC-A-MVP-3.
5. **New section §6.9 — MVP re-baseline criteria** (5 criteria, AC-A-MVP-1..5), verifying the naming cascade, the Direct-Purchase enum/FSM seam, the L-PP Admin-Panel re-scope, the floor parity, and a scope-parity confirmation.
6. **Floor criteria re-affirmed UNCHANGED:** the two-layer no-overselling guard (**AC-A-XM-14**), per-sub-pool composition (**AC-A-XM-15**), the ATP cache (**AC-A-EVT-22..28, EVT-31, EVT-32**), the shortfall workflow + `VoucherCancelled` release (**AC-A-EVT-29, EVT-30**), Layer-1 / sub-pool floors (**AC-A-BR-SubPool-1..3**), the KYC-cleared-Producer gate (**AC-A-BR-CrossModule-2**), and the sanctions-blind boundary (**AC-A-XM-19, AC-A-BR-CrossModule-3**) all stand as-is. **Nothing in launch scope removed.**

### §0.1 The Direct-Purchase posture (how the D11 criteria are treated)

Per the Module A cut-sheet Q2 ("defer Direct Purchase at launch; A keeps the enum + uniform-FSM seam, free") + Phase C item I (confirmed — no launch deal; A/D/B/E/S idle in lockstep) + the master kickoff's E-emits correction (R4):

- **V1 / V2 activation — KEPT, exercised at launch.** **AC-A-J-3** (V2), **AC-A-J-4** (V1), **AC-A-FSM-2** all stand: operator-publish post-PO-commit (DEC-183).
- **`SupplierPaymentCompleted` non-trigger — KEPT, exercised at launch (it is floor-adjacent correctness).** **AC-A-FSM-7** + **AC-A-EVT-20** + **AC-A-BR-CrossModule-4** stand: the event does **not** drive the Allocation FSM. *(The event is now E-emitted and consumed by Module D + Module B, Phase C R4 — Module A is neither its emitter nor a load-bearing consumer; the negative-path test is unchanged in substance.)*
- **Direct-Purchase chain — NOT exercised at launch (seam retained).** **AC-A-J-5**, **AC-A-XM-7**, and the Direct-Purchase arms of **AC-A-EVT-6 / AC-A-EVT-9** → roadmap; **AC-A-MVP-2** verifies the seam is present-but-not-exercised (the `direct_purchase` enum value + the uniform FSM exist; no `direct_purchase` allocation is created at launch).

---

## §1 How to use this document

### §1.1 Verification tags

- **AUTO** — an AI agent or automated harness reads the criterion + spec anchor + running system (event stream, entity state, API responses, audit trail) and produces a PASS/FAIL verdict with evidence. Paolo reviews the verdict batch.
- **MIXED** — AI prepares the evidence; Paolo confirms a judgment call (audit-trail readability, parity-symmetric coherence, the scope-parity proof).
- **HUMAN** — Paolo executes personally (a single end-to-end demo session + subjective spot-checks).

**Distribution for Module A v0.3-MVP: ~116 total criteria** — the v0.1 111 (102 AUTO / 8 MIXED / 1 HUMAN) **+ 5 MVP re-baseline criteria (4 AUTO / 1 MIXED)** → **106 AUTO / 9 MIXED / 1 HUMAN.** Two journey criteria (AC-A-J-5, AC-A-XM-7) + two event-arm annotations (AC-A-EVT-6/-9 Direct-Purchase arms) are **annotated not-exercised-at-launch** (retained for when Direct Purchase restores), and two parity criteria (AC-A-J-11, AC-A-EVT-17) are **re-scoped to the Admin-Panel operator surface** (not counted out). Paolo's hands-on load: the **9 MIXED items + 1 end-to-end demo session.**

### §1.2 Build-time usage

Consulted from day one, not only at handover. The dev reads the PRD + this doc together; AUTO criteria wire into CI as scaffolding lands (the AUTO PASS rate is a continuous completion signal); AI coding agents treat AUTO criteria as fitness functions (read PRD anchor → generate code → run AUTO → iterate); MIXED/HUMAN items are scheduled, not surprised; the acceptance doc evolves with the spec in lock-step.

### §1.3 Sign-off cadence

Each criterion lands in **OPEN** (not yet demonstrated) → **DEMOED** (evidence produced) → **ACCEPTED** (Paolo signed off). Module A is **delivered** when every §2–§6 launch-scope criterion is ACCEPTED. Sign-off log at §8.

### §1.4 Anchors

PRD §-numbers refer to [`../02-prd/Module_A_PRD_v0.3-MVP.md`](../02-prd/Module_A_PRD_v0.3-MVP.md). BR-A-* refers to its §10. Event names refer to its §12. FSM states refer to its §5.1. DEC refers to the v1.1 Decision Register (cited inline). **(Body §-anchors are unchanged from v1.1 — §0 item 2.)**

### §1.5 Format conventions (locked at the v0.1 review; carried)

1. **§4 BR statements are verbatim from PRD §10** (self-containment per DEC-074; trivial drift detection). *For v0.3-MVP the verbatim statements carry the naming cascade on BR-A-Identity-3 / BR-A-CrossModule-1 (Bottle Reference → Product Reference) + the BR-A-CrossModule-4 E-emits alignment, matching the v0.3-MVP PRD §10 prose.*
2. **§4 BR→AC pointer rows preserve traceability** (every BR has an explicit AC ID row, even when covered by an upstream §2/§3 criterion).
3. **§6 cross-module criteria verify the Module-A-side surface only** (downstream behaviour verified in the receiving module's acceptance doc; no dual-side overlap).
4. **AUTO criteria dependent on consumer modules carry inline "verified when X lands" notes** (Module A emits ~15 events; Module-A-side emission/schema verified at Module A handover, downstream consumption when the consumer module is built).
5. **(NEW, v0.3-MVP)** **MVP re-baseline criteria live in §6.9** (AC-A-MVP-*); **not-exercised-at-launch criteria carry an inline "→ roadmap (restores with Direct Purchase)" note**; **L-PP parity criteria carry an inline "Admin-Panel operator surface at launch; Producer-Portal write UI deferred" note.**

---

## §2 Canonical journeys — end-to-end allocation flows

Six buckets exercised end-to-end: the activation flows (V1 / V2 at launch; Direct Purchase not-exercised-at-launch) per DEC-183, sub-pool carve-out per DEC-080, commercial-terms configuration per DEC-092, the operation parity per DEC-083 (Admin-Panel surface at launch), split-by-visibility per DEC-076, and the full lifecycle round-trip.

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-J-1** | Allocation Operator (Admin-Panel-side / NewCo Ops) creates an Allocation against an `active` **Product Reference** (wine-display alias *Bottle Reference*); inputs `producer_id` (active + KYC-cleared), Product Reference, `sourcing_model = passive_v2`, `visibility = CLUB_ONLY`, `qty`, `commercial_terms = {percent_of_selling_price, 12.5%}`, `non_serialized_offer_admitted = FALSE`. Allocation enters DRAFT. `AllocationCreated` fires carrying every input + `actor_role: newco_ops`. *(Naming cascade: Bottle Reference → Product Reference.)* | §5.3.1 + §12.1 | AUTO — drive create flow; assert state, event payload, actor_role tag |
| **AC-A-J-2** | The same creation flow runs from the Admin Panel on behalf of a Producer (the launch write path); operation accepts identical inputs; emitted `AllocationCreated` carries `actor_role: newco_ops`. *(L-PP: producer-side create is the deferred Producer-Portal write UI; backend parity unchanged.)* | §5.3.1 + DEC-083 | AUTO — drive create from the Admin-Panel surface; assert success + correct actor_role tag |
| **AC-A-J-3** | **V2 activation flow**: Producer commits + sets up the allocation in DRAFT; operator-publish (Admin Panel "activate") transitions DRAFT → ACTIVE; `AllocationActivated` fires. PI fires at voucher issuance per DEC-093; PO at settlement cadence. No upfront cash; the FSM trigger is operator-initiated. **Exercised at launch.** | §5.2 + §5.3.2 + DEC-183 | AUTO — drive end-to-end DRAFT → ACTIVE via operator-publish on V2; assert transition + event + no PO-commit dependency on FSM |
| **AC-A-J-4** | **V1 activation flow**: same operator-publish trigger pattern as V2 per DEC-183; DRAFT → ACTIVE; `AllocationActivated` fires. (V1 is the rare very-expensive-bottle exception; FSM-wise identical to V2.) **Exercised at launch.** | §5.2 + §5.3.2 + DEC-183 | AUTO — drive V1 operator-publish flow; assert identical FSM behaviour to V2 |
| **AC-A-J-5** | **Direct Purchase activation flow** *(NOT-EXERCISED-AT-LAUNCH → roadmap; Direct Purchase deferred, D11/Phase C item I — the `direct_purchase` enum + uniform FSM are the retained seam)*: NewCo ops creates Allocation in DRAFT → creates PI (Module D) → Module D issues PO + commits → operator-publishes via Admin Panel "activate" → DRAFT → ACTIVE + `AllocationActivated`. Activation does NOT wait for `SupplierPaymentCompleted`. Verified when Direct Purchase is re-enabled. | §5.2 + §5.3.2 + §11.3.1 + DEC-183 | AUTO (deferred) — when Direct Purchase restores, drive the full cross-module chain through Module D PO commit; assert activation fires on operator-publish, not on payment. At launch: AC-A-MVP-2 verifies the seam is present-but-not-exercised. |
| **AC-A-J-6** | Sub-pool carve-out at creation: operator inputs `qty = 100`, `non_serialized_offer_admitted = TRUE`, `qty_to_serialize = 70`, `qty_non_serialized = 30`. Partition invariant validated; allocation persists with derived `serialization_type = MIXED`. | §5.3.1 + §7 | AUTO — drive create with sub-pool inputs; assert persistence + derived attribute; assert rejection on partition-invariant violation |
| **AC-A-J-7** | Operator creates allocation with `producer_breakability` per `case_config = OWC6` declared non-breakable; Module A validates OWC6 appears in the **Product Variant**'s Layer 1 whitelist (Module 0 §7.1); allocation persists; `AllocationProducerBreakabilityDeclared` fires. Declaring a Case Configuration outside the whitelist is rejected. *(Naming cascade: Wine Variant → Product Variant.)* | §5.3.1 + §8 + §10.4 BR-A-Breakability-1 | AUTO — drive positive (whitelisted) + negative (non-whitelisted) paths |
| **AC-A-J-8** | Full lifecycle round-trip: create (DRAFT) → activate (ACTIVE) → close (CLOSED) → retire (RETIRED). Each transition emits the corresponding `Allocation*` event in order. Backward transitions rejected at every state. | §5.1 + §5.2 + §5.3.9 + §5.3.10 + §12.1 | AUTO — drive forward round-trip; attempt backward transitions; assert rejections |
| **AC-A-J-9** | **Split-by-visibility realisation**: producer commits N units split M club + (N−M) Discovery; system materialises TWO sibling Allocation rows (CLUB_ONLY `qty = M` + club commercial_terms; DISCOVERY_ONLY `qty = N−M` + Discovery commercial_terms). Each row has its own lifecycle. Sibling-aggregation by shared `producer_id + product_reference + sourcing_model + creation_timestamp` (+ `supplier_id`). | §6 + DEC-076 | MIXED — AI drives a "create commitment" wrapper; asserts two distinct rows with expected attributes; gathers sibling-aggregation evidence; Paolo confirms operator-visibility coherence of the auto-spawn + the two-rows-as-one-commitment rendering (Admin Panel at launch) |
| **AC-A-J-10** | Discovery allocation creation with `supplier_id` populated (the COMMON Discovery pattern, DEC-082): operator inputs both `producer_id` + `supplier_id`; SupplierProducerLink read from Module D in `active` state; allocation persists with both FKs. Attempt with no active link rejected. | §4.1 + §5.3.1 + §10.6 BR-A-Counterparty-2 | AUTO — positive path with active link; negative path with missing / non-active link |
| **AC-A-J-11** | **Operation parity (DEC-083) — Admin-Panel operator surface at launch; Producer-Portal write UI deferred (L-PP/Q3).** Every operation in the §5.3 catalogue (create / activate / mutate visibility / mutate qty / update commercial_terms / counterparty change / sub-pool rebalance / opt-out toggle / recall / close / retire) executes successfully from the **Admin Panel** with the specified input parameters, business validation, state transition, and emitted event; every event records `actor_role: newco_ops`. The Producer-Portal write half is verified when its write UI lands (backend parity unchanged — AC-A-MVP-3). | §5.3 + DEC-083 | MIXED — AI drives each operation from the Admin-Panel surface; gathers event payloads + state snapshots; Paolo confirms operator-surface coherence + audit-trail readability |
| **AC-A-J-12** | Commercial-terms configuration per DEC-092: (a) club `{percent_of_selling_price, 12.5%}` (DEC-010); (b) Discovery `{fixed_per_unit, value=C}` (DEC-032); (c) *(Direct Purchase either shape — not-exercised-at-launch; the shape mechanism itself is exercised via (a)/(b))*. Creations succeed; downstream Module S pricing read returns shape-correct values. | §4.1 + §5.3.1 + DEC-092 | AUTO — drive each launch-exercised commercial-terms shape; assert persistence + Module S pricing-read derivation (verified when Module S lands) |
| **AC-A-J-13** | End-to-end demo session: Paolo observes the NewCo Ops Operator team walking through allocation creation across V1 / V2 (Direct-Purchase not-exercised-at-launch), split-by-visibility, visibility mutation, qty mutation, sub-pool rebalance, recall trigger, close, retire — exercised from the **Admin-Panel operator surface** with `actor_role` event evidence. | §1–§13 (full surface) | HUMAN — single session, ~60–90 min, with dev team + NewCo Ops; Paolo signs off against this acceptance document |

---

## §3 State machine round-trips — Allocation FSM

The 4-state monotonic FSM (DRAFT → ACTIVE → CLOSED → RETIRED, DEC-077) is the load-bearing lifecycle; DEC-183 harmonizes the DRAFT → ACTIVE trigger to operator-publish post-PO-commit uniformly.

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-FSM-1** | Allocation enters DRAFT on `AllocationCreated`. DRAFT is NOT sellable: no Module S Offers publish; no vouchers issue. | §5.1 + §5.2 + §12.1 | AUTO — create; attempt Offer publish against DRAFT; assert rejection |
| **AC-A-FSM-2** | DRAFT → ACTIVE is operator-initiated (Admin Panel "activate") uniformly across V1 / V2 / Direct Purchase per DEC-183. Emits `AllocationActivated`. Now sellable. *(At launch exercised via V1/V2; the Direct-Purchase arm rides the same trigger — seam.)* | §5.2 + §5.3.2 + DEC-183 | AUTO — drive operator-publish; assert transition + event + downstream sellability |
| **AC-A-FSM-3** | ACTIVE → CLOSED is operator-initiated. Emits `AllocationClosed`. CLOSED is NOT sellable (no new vouchers/Offers). In-flight state continues (existing vouchers redeem/ship/refund; settlement may still fire). | §5.1 + §5.2 + §5.3.9 + §12.1 | AUTO — drive close; assert FSM + event + Module S Offer-retirement signal + in-flight voucher continues |
| **AC-A-FSM-4** | CLOSED → RETIRED is operator-initiated. Emits `AllocationRetired`. RETIRED is terminal; no further operations or events. | §5.1 + §5.2 + §5.3.10 + §12.1 | AUTO — drive retire; assert FSM + event + post-retire operation attempts rejected |
| **AC-A-FSM-5** | Retire-blocked-by-in-flight-state: CLOSED → RETIRED rejected if any in-flight state remains (vouchers awaiting redemption/shipment; settlement events pending; for V1/V2, any settlement not yet recorded against issued vouchers). | §5.3.10 + §10.5 BR-A-Lifecycle-4 | AUTO — set up CLOSED with one outstanding voucher; attempt retire; assert rejection with in-flight list; resolve; retry; assert success |
| **AC-A-FSM-6** | FSM monotonicity: backward transitions (CLOSED → ACTIVE, RETIRED → CLOSED, ACTIVE → DRAFT, …) are NOT admitted. The pattern for "re-open" is close + spawn new sibling. | §5.1 + §10.5 BR-A-Lifecycle-1 | AUTO — parametrised negative-path across all backward transitions; assert uniform rejection |
| **AC-A-FSM-7** | `SupplierPaymentCompleted` does NOT drive the Allocation FSM (observed as a financial-event signal only per DEC-183). Wiring `SupplierPaymentCompleted → AllocationActivated` is INCORRECT and must not exist. Voucher issuance on a DRAFT Allocation cannot occur by construction. *(The event is E-emitted + D/B-consumed per Phase C R4; Module A is not a load-bearing consumer — the negative-path assertion is unchanged.)* **Floor-adjacent — exercised at launch.** | §5.3.2 + §10.7 BR-A-CrossModule-4 + DEC-183 | AUTO — negative-path: emit `SupplierPaymentCompleted` against a DRAFT allocation; assert FSM unchanged + no `AllocationActivated` + no voucher can issue |
| **AC-A-FSM-8** | FSM orthogonality to ProducerAgreement (DEC-077): Module K `superseded`/`terminated` do NOT auto-cascade onto Allocation state. An ACTIVE allocation whose producer's agreement terminates stays ACTIVE; the anomaly surfaces as a soft alert in the Admin Panel. | §5.2 + §10.5 BR-A-Lifecycle-3 + DEC-077 | AUTO — set up ACTIVE allocation; emit Module K `ProducerAgreementTerminated`; assert state unchanged + soft-alert surface present |
| **AC-A-FSM-9** | Each FSM transition emits exactly one lifecycle event in the correct direction: `AllocationCreated` (none → DRAFT), `AllocationActivated` (DRAFT → ACTIVE), `AllocationClosed` (ACTIVE → CLOSED), `AllocationRetired` (CLOSED → RETIRED). No transition emits multiple lifecycle events or a different family. | §5.2 + §12.1 | AUTO — parametrised test per transition; assert exactly-one-event-per-transition |

---

## §4 Business rule enforcement (BR-A-*)

One criterion per business rule in PRD §10. BR statements restated verbatim inline per DEC-074. *(Naming cascade on BR-A-Identity-3 / BR-A-CrossModule-1; E-emits alignment on BR-A-CrossModule-4.)*

### §4.1 Identity and uniqueness (BR-A-Identity-1..3)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-A-BR-Identity-1** | Every Allocation row carries a unique opaque allocation identifier; no business attributes form the identifier. | AUTO — inspect entity schema; assert opaque identifier independent of business attributes; assert two allocations with identical business attributes coexist with distinct identifiers |
| **AC-A-BR-Identity-2** | `producer_id` is always populated (DEC-082 two-FK pattern); `supplier_id` is optional. | AUTO — attempt creation with null `producer_id`, assert rejection; populated + null `supplier_id` both succeed; common pattern covered by AC-A-J-10 |
| **AC-A-BR-Identity-3** | The **Product Reference** (wine-display alias *Bottle Reference*) is always populated; references an `active` PR in Module 0 PIM at allocation creation. *(Naming cascade.)* | AUTO — covered by AC-A-J-1 (positive) + negative path: attempt creation against a `draft` / `retired` PR, assert rejection |

### §4.2 Operations and mutability (BR-A-Mutability-1..5)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-A-BR-Mutability-1** | `qty` cannot decrease below the count of vouchers already issued (per §5.3.4 + DEC-079). | AUTO — set up N issued vouchers; attempt decrease below issued, assert rejection; decrease to (issued + delta), assert success; increase, assert success; assert `AllocationCapacity{Increased,Decreased}` fire correctly |
| **AC-A-BR-Mutability-2** | A visibility flip applies to the unsold portion only; vouchers already issued under the original visibility remain bound to the original commercial relationship (per §5.3.3 + DEC-076). | AUTO — CLUB_ONLY with K issued vouchers + unsold remainder; flip to DISCOVERY_ONLY with new commercial_terms; assert issued vouchers stay bound; unsold remainder associates with Discovery terms; `AllocationVisibilityChanged` fires with prior/new |
| **AC-A-BR-Mutability-3** | A `commercial_terms` update cannot disadvantage already-issued vouchers' subsequent sell-through retroactively (per §5.3.5). | MIXED — AI drives same-shape favourable + disfavourable + cross-shape favourable + cross-shape disfavourable; assembles the at-least-as-favourable comparison; Paolo confirms the "favourable" semantics match business intent |
| **AC-A-BR-Mutability-4** | `sourcing_model` is immutable post-allocation-creation (per §4.1). Changes require retire + new-allocation-create. | AUTO — attempt `sourcing_model` mutation on DRAFT / ACTIVE, assert rejection; close + retire + create new with different sourcing_model, assert success |
| **AC-A-BR-Mutability-5** | `producer_breakability` is immutable once the first voucher is issued (per §8). | AUTO — set at creation; pre-first-voucher mutate, assert success + `AllocationProducerBreakabilityDeclared`; issue first voucher; attempt mutate, assert rejection |

### §4.3 Sub-pool partition (BR-A-SubPool-1..3) **(FLOOR-adjacent — unchanged)**

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-A-BR-SubPool-1** | `qty_to_serialize + qty_non_serialized = qty` at all times (per §7). | AUTO — create with violating partition, assert rejection; rebalance violating invariant, assert rejection; positive path covered by AC-A-J-6 |
| **AC-A-BR-SubPool-2** | `qty_to_serialize` cannot decrease below issued serialized-backed vouchers; `qty_non_serialized` cannot decrease below issued non-serialized-backed vouchers (per §7). | AUTO — vouchers issued from both sub-pools; attempt orphaning decrease, assert rejection; legal rebalance, assert success + `AllocationSubPoolRebalanced` |
| **AC-A-BR-SubPool-3** | `non_serialized_offer_admitted` is admissible across all `visibility × sourcing_model` combinations (per DEC-080). | AUTO — drive opt-out toggle on each {CLUB_ONLY, DISCOVERY_ONLY} × {passive_v1, passive_v2, direct_purchase} combination; assert each accepts + `AllocationNonSerializedOptOutChanged` fires. *(The `direct_purchase` combinations validate the enum-retained seam; no `direct_purchase` allocation is sold at launch.)* |

### §4.4 Layered breakability — Layer 2 (BR-A-Breakability-1..2)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-A-BR-Breakability-1** | Every `producer_breakability` per-case-config declaration must reference a Case Configuration in the **Product Variant**'s Layer 1 whitelist (Module 0 §7.1). *(Naming cascade.)* | AUTO — covered by AC-A-J-7 |
| **AC-A-BR-Breakability-2** | The `effective_unbreakable` rule is computed at sale time across Layer 2 (this Module) and Layer 3 (Module S); Module A does not compute the effective rule. | AUTO — inspect Module A API; assert no `effective_unbreakable` computation exposed; assert Module A exposes only Layer 2 (`producer_breakability` per case_config) |

### §4.5 Lifecycle (BR-A-Lifecycle-1..4)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-A-BR-Lifecycle-1** | Lifecycle transitions are forward-only — DRAFT → ACTIVE → CLOSED → RETIRED. Backward transitions are not admitted. | AUTO — covered by AC-A-FSM-6 |
| **AC-A-BR-Lifecycle-2** | The DRAFT → ACTIVE transition is **operator-publish post-PO-commit** uniformly across V1 / V2 / Direct Purchase; operator-initiated; ACTIVE = "sellable"; `SupplierPaymentCompleted` does not drive FSM (per DEC-183). | AUTO — covered by AC-A-J-3 (V2) + AC-A-J-4 (V1) + AC-A-FSM-2 + AC-A-FSM-7 (negative path). Direct-Purchase arm (AC-A-J-5) not-exercised-at-launch; the uniform trigger is exercised via V1/V2. |
| **AC-A-BR-Lifecycle-3** | ProducerAgreement transitions (`superseded` / `terminated`) do NOT auto-cascade onto Allocation state (per DEC-077). | AUTO — covered by AC-A-FSM-8 |
| **AC-A-BR-Lifecycle-4** | An Allocation cannot transition CLOSED → RETIRED while in-flight state remains. | AUTO — covered by AC-A-FSM-5 |

### §4.6 Counterparty (BR-A-Counterparty-1..2)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-A-BR-Counterparty-1** | The data model preserves Module K's Producer vs Supplier separation per DEC-067; the two-FK pattern (`producer_id` always; `supplier_id` optional) operationalises it per DEC-082. | AUTO — inspect schema; assert two distinct FK fields; assert collapse pattern (producer_id populated; supplier_id null) admitted when Producer is also Supplier |
| **AC-A-BR-Counterparty-2** | When `supplier_id` is populated, the SupplierProducerLink (Module D) between that Supplier and the Producer must be `active` (read at creation + counterparty-change). | AUTO — covered by AC-A-J-10 for creation; additionally drive a §5.3.6 counterparty change against missing / non-active link, assert rejection; against active link, assert success + `AllocationCounterpartyChanged` (Module D link-state authoring verified when Module D lands) |

### §4.7 Cross-module dependency (BR-A-CrossModule-1..4)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-A-BR-CrossModule-1** | Allocation creation requires the **Product Reference** to be `active` in Module 0. *(Naming cascade.)* | AUTO — covered by AC-A-BR-Identity-3 |
| **AC-A-BR-CrossModule-2** | Allocation creation requires `producer_id` to be `active` and KYC-cleared (`verified` or `not_required`) in Module K. **(FLOOR — the upstream KYC expression.)** | AUTO — negative paths: Producer `draft`, `reviewed`, `active` with KYC `pending`, `active` with KYC `rejected`, `retired` — all rejected; positive paths: `active` + KYC `verified` and `active` + KYC `not_required` — both succeed |
| **AC-A-BR-CrossModule-3** | Module A operations do not gate on `Customer.sanctions_status` (per DEC-071); the enforcement point is order completion in Module S. **(FLOOR chain 2 — unchanged.)** | AUTO — inspect Module A operations API; assert no read of `Customer.sanctions_status`; assert operations proceed regardless of any Customer's screening state |
| **AC-A-BR-CrossModule-4** | Module A takes no FSM action on `SupplierPaymentCompleted` per DEC-183; activation is operator-publish post-PO-commit (BR-A-Lifecycle-2). *(The event is E-emitted + D/B-consumed per Phase C R4 — Module A is neither its emitter nor a load-bearing consumer.)* | AUTO — covered by AC-A-FSM-7 |

### §4.8 Operations-detail invariants (anchored to §5.3 + §6)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-A-BR-Ops-1** | Visibility mutation: allocation in DRAFT or ACTIVE (CLOSED / RETIRED reject); new value the opposite of current; commercial_terms flips concurrently per DEC-076 + DEC-092 as a single atomic operation. Partial state (visibility flipped but commercial_terms on the old shape) NOT admitted. | MIXED — AI drives flip on each state; gathers event-stream + state snapshot pre/post + audit trail showing the atomic flip + rollback evidence; Paolo confirms atomic-flip behaviour is operationally readable + auditable |
| **AC-A-BR-Ops-2** | qty increase on a CLUB_ONLY Hero Package allocation surfaces a side-effect signal to Module K's Hero Package Capacity Invariant (Module K §13); waitlist-conversion priority is producer-discretionary at launch (no automatic FIFO). | AUTO — drive qty-increase on a Hero-Package-backing club allocation; assert Module K Capacity Invariant receives signal; assert no automatic FIFO conversion fires (Module K re-evaluation verified when Module K lands) |
| **AC-A-BR-Ops-3** | Recall trigger admits only when allocation is in ACTIVE or CLOSED (not DRAFT or RETIRED); recalled qty ≤ `qty − issued`; trigger source recorded as `producer` or `newco_ops` per DEC-090 + DEC-083. *(At launch operator-driven via Admin Panel → `newco_ops`; the `producer` path is the deferred Producer-Portal write UI.)* | AUTO — drive recall positive (ACTIVE + CLOSED) + negative (DRAFT + RETIRED); positive + negative on recalled-qty bound; assert `AllocationRecallTriggered` with correct trigger source tag |
| **AC-A-BR-Ops-4** | Allocation cannot be created with a `producer_breakability` declaration referencing a Case Configuration outside the **Product Variant**'s Layer 1 whitelist (Module 0 §7.1). *(Naming cascade.)* | AUTO — covered by AC-A-J-7 |
| **AC-A-BR-Ops-5** | Split-by-visibility produces sibling rows sharing `producer_id + product_reference + sourcing_model + creation_timestamp` (+ `supplier_id`); each row has its own lifecycle; mid-life rebalancing is per-row. | AUTO — covered by AC-A-J-9; additionally drive independent close on one sibling while the other stays ACTIVE; assert FSM independence |
| **AC-A-BR-Ops-6** | Settlement-cadence is NOT carried on the Allocation; it is read from `ProducerAgreement.settlement_cadence` (Module K §4.6) at settlement-event-handling time. | AUTO — inspect entity schema; assert no settlement-cadence field; Module E handler reads cadence from Module K ProducerAgreement (verified when Module E lands) |

---

## §5 Domain event emission and consumption

Module A emits lifecycle + operations events on every state-change/operation. Every event carries the audit envelope (opaque event id, allocation id, timestamp, actor identity, `actor_role: producer | newco_ops` per DEC-083 — `newco_ops` for every write at launch). Module A consumes Module B's inventory-event family (ATP push per DEC-187 + `InventoryShortfallDetected` per DEC-190) + Module 0 lifecycle events. *(Module A's own `Allocation*` event names are unchanged by the cascade; the consumed Module 0 events rename.)*

### §5.1 Lifecycle event emission

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-EVT-1** | `AllocationCreated` fires on row creation. Payload carries `producer_id`, optional `supplier_id`, Product Reference, `sourcing_model`, `visibility`, `qty`, `commercial_terms`, sub-pool partition, `non_serialized_offer_admitted`, `producer_breakability`, `actor_role`. | §12.1 | AUTO — covered for shape by AC-A-J-1 + AC-A-J-2; additionally assert payload completeness |
| **AC-A-EVT-2** | `AllocationActivated` fires on DRAFT → ACTIVE uniformly across V1 / V2 / Direct Purchase via operator-publish per DEC-183. Consumer: Module S. | §12.1 + §5.3.2 + DEC-183 | AUTO — covered by AC-A-J-3 + AC-A-J-4 + AC-A-FSM-2 (Direct-Purchase arm via AC-A-J-5 when restored) |
| **AC-A-EVT-3** | `AllocationClosed` fires on ACTIVE → CLOSED. Consumer: Module S (retire Offers). | §12.1 + §5.3.9 | AUTO — Module-A-side covered by AC-A-FSM-3 (Module S Offer-retirement verified when Module S lands) |
| **AC-A-EVT-4** | `AllocationRetired` fires on CLOSED → RETIRED. Terminal; audit anchor. | §12.1 + §5.3.10 | AUTO — covered by AC-A-FSM-4 |

### §5.2 Operations event emission

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-EVT-5** | `AllocationVisibilityChanged` fires on §5.3.3. Carries prior/new `visibility` + prior/new `commercial_terms` (atomic flip). Consumer: Module S. | §12.2 + §5.3.3 | AUTO — Module-A-side covered by AC-A-BR-Mutability-2 + AC-A-BR-Ops-1 (Module S re-validation verified when Module S lands) |
| **AC-A-EVT-6** | `AllocationCapacityIncreased` fires on §5.3.4 increase. Carries prior/new `qty`. Consumer: Module D (follow-on PO for Direct Purchase capacity increase — *not-exercised-at-launch → roadmap*); Module K (Hero Package Capacity Invariant re-evaluation, club only per DEC-079). | §12.2 + §5.3.4 | AUTO — Module-A-side: drive qty increase on {V2 club, V2 Discovery, Hero Package backing}; assert event + payload. **Direct-Purchase follow-on-PO arm not-exercised-at-launch** (verified with Module D when Direct Purchase restores). Module K Hero-Package re-evaluation verified when Module K lands. |
| **AC-A-EVT-7** | `AllocationCapacityDecreased` fires on §5.3.4 decrease. Carries prior/new `qty`. Consumer: Module S. | §12.2 + §5.3.4 | AUTO — Module-A-side covered by AC-A-BR-Mutability-1 (Module S Offer-qty re-derive verified when Module S lands) |
| **AC-A-EVT-8** | `AllocationCommercialTermsChanged` fires on §5.3.5. Carries prior/new `commercial_terms {shape, value}`. Consumer: Module S (pricing re-render); Module E (settlement read at next event). | §12.2 + §5.3.5 | AUTO — Module-A-side covered for emission by AC-A-BR-Mutability-3 (Module S/E consumption verified when those modules land) |
| **AC-A-EVT-9** | `AllocationCounterpartyChanged` fires on §5.3.6. Carries prior/new `supplier_id`. Consumer: Module D (prospective PO routing; existing POs NOT retroactively re-routed). | §12.2 + §5.3.6 | AUTO — Module-A-side: drive null→populated, populated→null, populated→different; assert event + payload. **The "existing POs NOT retroactively re-routed" post-supplier-payment Direct-Purchase arm is not-exercised-at-launch** (ADMITTED-WITH-CONSTRAINT per AMB-A-4; verified at the Module D PO-routing surface when Direct Purchase restores). |
| **AC-A-EVT-10** | `AllocationSubPoolRebalanced` fires on §5.3.8. Carries prior/new sub-pool numerics. Consumer: Module B; Module S. | §12.2 + §5.3.8 | AUTO — Module-A-side covered for emission by AC-A-BR-SubPool-2 (Module B/S consumption verified when those land) |
| **AC-A-EVT-11** | `AllocationNonSerializedOptOutChanged` fires on §5.3.8. Carries prior/new `non_serialized_offer_admitted`. Consumer: Module S. | §12.2 + §5.3.8 | AUTO — covered by AC-A-BR-SubPool-3 (Module S consumption verified when Module S lands) |
| **AC-A-EVT-12** | `AllocationProducerBreakabilityDeclared` fires on §8 (creation-time or pre-first-voucher). Carries case_config + declaration value. Audit; rare. | §12.2 + §8 | AUTO — covered by AC-A-J-7 + AC-A-BR-Mutability-5 |
| **AC-A-EVT-13** | `AllocationRecallTriggered` fires on §5.3.7. Carries recalled qty, destination, trigger source (`producer | newco_ops`). Consumer: Module D (`ReverseInboundEventRecorded`, DEC-090). | §12.2 + §5.3.7 | AUTO — Module-A-side covered for emission + trigger source tag by AC-A-BR-Ops-3 (Module D recording verified when Module D lands) |

### §5.3 Event ordering, naming, versioning

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-EVT-14** | Cascading events within a single business transaction are emitted in causal order; consumers tolerate eventual-consistency arrival order across transactions. | §12.4 | MIXED — AI drives a multi-event transaction; assembles the event-stream trace + audit-trail reconstruction; Paolo confirms the trace is operationally readable + audit-quality |
| **AC-A-EVT-15** | All Module A events use the `Allocation*` prefix (category-neutral — unchanged by the cascade); lifecycle `*Created/*Activated/*Closed/*Retired`; operations semantic verbs. | §12.4 | AUTO — enumerate event types; assert all conform |
| **AC-A-EVT-16** | Events are schema-versioned; consumers evolve independently within a major version with backward-compat. | §12.4 | AUTO — inspect schemas for version field; drive a minor-version additive change; assert downstream consumers continue |
| **AC-A-EVT-17** | Every emitted event carries `actor_role: producer | newco_ops` per DEC-083. **At launch every Module A write is from the Admin Panel → `newco_ops`; the `producer` tag is exercised when the Producer-Portal write UI lands (L-PP/Q3 — backend parity unchanged).** | §12 + DEC-083 | AUTO — covered by AC-A-J-1 / AC-A-J-2 (`newco_ops`); assert every event from §12.1 + §12.2 carries the tag; the `producer`-tag path is verified when the Producer-Portal write UI lands |

### §5.4 Events consumed from upstream modules

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-EVT-18** | Module A consumes Module 0 `ProductReferenceActivated` to enable allocation creation against that PR; creation is rejected against a non-`active` PR per BR-A-CrossModule-1. *(Naming cascade: `BottleReferenceActivated → ProductReferenceActivated`.)* | §11.1 | AUTO — covered for the gate by AC-A-BR-Identity-3 + AC-A-BR-CrossModule-1; additionally emit `ProductReferenceActivated`, assert previously-blocked creation against that PR now admitted |
| **AC-A-EVT-19** | Module A consumes Module K `ProducerActivated` (+ KYC cleared — `verified` or `not_required`) to enable allocation creation under that Producer per BR-A-CrossModule-2. | §11.2 + Module K §15 | AUTO — covered by AC-A-BR-CrossModule-2 negative paths + positive recovery: emit `ProducerActivated` with KYC cleared, assert creation under that Producer now admitted |
| **AC-A-EVT-20** | Module A observes `SupplierPaymentCompleted` as a financial-event audit signal only per DEC-183 — does NOT trigger FSM. *(Per Phase C R4 the event is E-emitted + consumed by Module D + Module B; Module A is not a load-bearing consumer — it takes no FSM action regardless of emitter.)* | §11.3 + §5.3.2 + DEC-183 | AUTO — covered for non-trigger by AC-A-FSM-7; assert no FSM transition fires on the event |
| **AC-A-EVT-21** | Module A consumes Module D `InboundEventPhysicallyAccepted` only as an informational signal for downstream Module C shipment-gate handling (DEC-081 decoupling); Module A FSM is unaffected. Sellability gate (`state = ACTIVE`) is decoupled from shipment gate. | §11.6 + DEC-081 + DEC-183 | AUTO — emit `InboundEventPhysicallyAccepted` against an ACTIVE allocation; assert state unchanged; assert Module C shipment gate fires downstream |

### §5.5 Module B inventory-event consumption (DEC-187 + DEC-190) **(FLOOR — unchanged)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-EVT-22** | Module A maintains a strongly-consistent ATP cache per allocation per DEC-187, sourced from Module B push events. Per-sub-pool (`atp_serialized` + `atp_non_serialized`). | §11.5 + §11.5.1 + §7.1 + DEC-187 | AUTO — inspect cache structure; assert per-allocation + per-sub-pool ATP values; drive a Module B inventory event; assert cache delta applied |
| **AC-A-EVT-23** | Module A consumes `BottleStateChanged` — applies delta to the affected allocation's cached `atp_serialized`. | §12.3 + §11.5.1 | AUTO — emit `BottleStateChanged` for each transition; assert cache delta correct |
| **AC-A-EVT-24** | Module A consumes `InventoryAdjusted` (DEC-190) — applies per-bottle/per-batch delta. If the proposed adjustment would reduce committed inventory, `InventoryShortfallDetected` fires upstream of `InventoryAdjusted`. | §12.3 + §11.5.1 + §11.5.2 | AUTO — drive adjustment-without-shortfall, assert delta applied; adjustment-with-shortfall, assert `InventoryShortfallDetected` fires upstream |
| **AC-A-EVT-25** | Module A consumes `OwnershipTransitioned` (PRODUCER → CRURATED) — audit-only delta; does NOT gate sellability. | §12.3 + §11.5.1 | AUTO — emit `OwnershipTransitioned`; assert audit recorded; assert sellability unchanged |
| **AC-A-EVT-26** | Module A consumes `BottleQuarantined` / `BottleQuarantineResolved` — quarantined units excluded from ATP; resolved units restored (associate-with-batch / create-new) or permanently excluded (reject-as-invalid). | §12.3 + §11.5.1 + Module B v0.2 §14 | AUTO — drive each entry + each resolution path; assert ATP cache delta matches |
| **AC-A-EVT-27** | Module A consumes `StocktakeReconciled` — applies the cumulative stocktake-reconciliation delta. | §12.3 + §11.5.1 + Module B v0.2 §12 | AUTO — drive stocktake with positive + negative + zero delta; assert cache updates per case |
| **AC-A-EVT-28** | Module A consumes NS-pool counter mutations (`qty_non_serialized_committed` / `qty_non_serialized_reserved` deltas) — applies to cached `atp_non_serialized`. | §12.3 + §11.5.1 + DEC-186 | AUTO — drive NS-pool counter mutations; assert `atp_non_serialized` delta correct |
| **AC-A-EVT-29** | Module A consumes `InventoryShortfallDetected` per DEC-190 + §11.5.2. On consumption, surfaces the shortfall to NewCo Operations via the Admin Panel (affected allocation + proposed scope + delta) and admits resolution paths: substitute (`VoucherSubstitutionExecuted`, DEC-104), refund (DEC-108), or cancel (`VoucherCancelled`, DEC-099). **(FLOOR — committed-inventory protection.)** | §11.5.2 + §12.3 + DEC-190 | MIXED — AI emits `InventoryShortfallDetected`; assembles the Admin Panel rendering + the three resolution-path traces; Paolo confirms the operations-facing surface is sufficiently informative + the paths cohere |
| **AC-A-EVT-30** | Module A emits `VoucherCancelled` per DEC-099 — the release primitive that lets a Module B-side adjustment proceed against committed inventory per Q-CL-6. Module B is a downstream consumer. **(FLOOR.)** | §11.5 + §12.3 + DEC-099 | AUTO — set up the shortfall scenario; emit `VoucherCancelled`; assert Module B-side previously-blocked adjustment now proceeds |
| **AC-A-EVT-31** | ATP cache reconciliation against Module B's StockPosition fires at three triggers per DEC-187: cold start (rebuild for every active allocation), outage recovery (re-read + rebuild for affected allocations), reconciliation tick (operator-initiated; confirm cache delta matches event-stream-applied delta within tolerance). *(The literal cadence / tolerance thresholds are tech-implementation, DEC-073 / Q4 — not asserted here.)* | §11.5.1 + DEC-187 | AUTO — drive cold-start, simulated-outage, reconciliation-tick; for each assert the cache is rebuilt from StockPosition for affected allocations; assert the reconciliation-state surface records trigger, scope, timing, per-allocation delta |
| **AC-A-EVT-32** | Hold placement reads the ATP cache AND validates against Module B's StockPosition strongly consistent at the transactional boundary per DEC-187 + Q-CL-5. If stale beyond tolerance (zero tolerance at the per-allocation absolute-quantity per-sub-pool level), the hold is rejected with a reconciliation reason; the cache reconciles before subsequent holds. **(FLOOR.)** | §11.5.1 + §7.1 + DEC-187 | MIXED — AI drives consistent-cache + stale-cache scenarios; gathers the hold-rejection-with-reconciliation-reason surface + reconciliation evidence; Paolo confirms the rejection reason is operationally informative + the reconciliation transition observable |

---

## §6 Cross-module contracts + boundary respect

Module A sits in the middle of the supply-chain flow. Upstream: Module 0 (PIM identity) + Module K (Producer / Supplier / ProducerAgreement). Downstream: Module D (Procurement) + Module S (Offer/Cart/Checkout) + Module B (Inventory/Provenance) + Module C (Fulfilment) + Module E (Finance). The boundaries are enforced by what Module A does NOT do.

### §6.1 Upstream reads — Module 0 (PIM)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-XM-1** | Module A reads **Product Variant + Product Reference** identity at allocation creation and on operations requiring identity validation (e.g. the `producer_breakability` Layer-1 upper-bound check reads `Product Variant.possible_case_configs`, Module 0 §7.1). *(Naming cascade.)* | §11.1 + §10.7 BR-A-CrossModule-1 | AUTO — covered by AC-A-J-1 (creation read) + AC-A-J-7 (Layer-1 check) |
| **AC-A-XM-2** | Module 0 lifecycle events (`ProductReferenceActivated/Retired`, `ProductMaster*/ProductVariant*`) consumed read-on-demand at operation-validation time; Module A does not subscribe at run-time. Retirement of an upstream Module 0 entity does NOT retroactively invalidate existing allocations (Module 0 §4.5 cascade). *(Naming cascade.)* | §11.1 + Module 0 §4.5 | AUTO — emit `ProductReferenceRetired` for a PR with an existing ACTIVE allocation; assert state unchanged + new-allocation attempts against that PR rejected |

### §6.2 Upstream reads — Module K (Parties)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-XM-3** | Module A reads Producer `active` + KYC-cleared at creation per BR-A-CrossModule-2; reads Supplier `active` at creation when `supplier_id` populated. Module A does not gate on ProducerAgreement state per DEC-077. | §11.2 | AUTO — Producer covered by AC-A-BR-CrossModule-2; Supplier by AC-A-J-10 + AC-A-BR-Counterparty-2; assert no read of `ProducerAgreement.state` across any operation |
| **AC-A-XM-4** | ProducerAgreement state is NOT read by Module A's operations; it is read by Module D at PO issuance (DEC-094); Module A operations are decoupled. | §11.2 + DEC-077 + DEC-094 | AUTO — inspect Module A operations API; assert no read of ProducerAgreement (Module D's gate verified when Module D lands) |

### §6.3 Downstream emit — Module D (Procurement / Inbound)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-XM-5** | Module A emits `AllocationCreated`, `AllocationActivated` (post-operator-publish uniformly per DEC-183), `AllocationCapacityIncreased` (Module D may consume for follow-on PO on Direct Purchase capacity increase — *not-exercised-at-launch*), `AllocationCounterpartyChanged` (Module D prospective PO routing), `AllocationRecallTriggered` (`ReverseInboundEventRecorded`, DEC-090). | §11.3 + §12 | AUTO — Module-A-side covered by AC-A-EVT-1/-2/-6/-9/-13 (Module D consumer effects verified when Module D lands) |
| **AC-A-XM-6** | Module A observes `SupplierPaymentCompleted` as a financial-event audit signal per DEC-183; the event does NOT drive the Module A FSM. *(E-emitted + D/B-consumed per Phase C R4; Module A not a load-bearing consumer.)* | §11.3 + §5.3.2 + §10.7 BR-A-CrossModule-4 + DEC-183 | AUTO — covered by AC-A-FSM-7 + AC-A-EVT-20 |
| **AC-A-XM-7** | The Direct Purchase cross-module event chain *(NOT-EXERCISED-AT-LAUNCH → roadmap; Direct Purchase deferred — the enum + uniform FSM are the seam)* executes in this order per §11.3.1: (1) Allocation created DRAFT; (2) PI created (Module D); (3) PO issued + committed (Module D); (4) operator-publish via Admin Panel; (5) DRAFT → ACTIVE + `AllocationActivated`; (6) Module S Offer surfaces valid; (7) Module D inbound proceeds independently; (8) `InboundEventPhysicallyAccepted` fires + Module C gates physical shipment. Sellability at step 5; physical shipment gates at step 8. *(Step 6 supplier payment → Module E emits `SupplierPaymentCompleted`, Phase C R4 — no Module A FSM role.)* | §11.3.1 + DEC-183 + DEC-081 | MIXED (deferred) — when Direct Purchase restores, AI drives the full chain + assembles the cross-module trace; Paolo confirms the sellability-shipment decoupling (DEC-081) + the operator-publish-post-PO-commit harmonization (DEC-183). At launch: AC-A-MVP-2 verifies the seam present-but-not-exercised. |

### §6.4 Downstream emit — Module S (Offer / Cart / Checkout)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-XM-8** | Module S validates at Offer publication: Offer `surface` matches Allocation `visibility` (single-visibility-per-row, DEC-076); Offer `serialization_type` aligns with `non_serialized_offer_admitted` + sub-pool (DEC-080); Layer 3 cannot downgrade Layer 2 (Module 0 §7.4). | §11.4 | AUTO — Module-A-side: assert Allocation exposes `visibility`, `non_serialized_offer_admitted`, sub-pool partition, `producer_breakability` as readable flags (Module S validation verified when Module S lands) |
| **AC-A-XM-9** | Module S reads `commercial_terms {shape, value}` at Offer pricing render (DEC-092). For `percent_of_selling_price`, settlement-per-unit = `value% × selling_price`; for `fixed_per_unit`, settlement-per-unit = `value`, gross margin = `selling_price − value`. | §11.4 + DEC-092 | AUTO — Module-A-side: assert Allocation exposes `commercial_terms {shape, value}` via read API in both shapes (Module S derivation verified when Module S lands) |
| **AC-A-XM-10** | Module S consumes `AllocationActivated`, `AllocationVisibilityChanged`, `AllocationCapacity{Increased,Decreased}`, `AllocationCommercialTermsChanged`, `AllocationClosed/Retired`. | §11.4 + §12 | AUTO — Module-A-side: for each, assert Module A emission + payload shape (Module S consumer effects verified when Module S lands) |
| **AC-A-XM-11** | Hero Package designation lives at Module S Offer level, NOT Module A (Module 0 §3.7). Module A exposes no Hero-Package-specific attribute/event; the backing Allocation is a normal Allocation (`CLUB_ONLY`, `{percent_of_selling_price, 12.5%}`, `qty` = Club's annual member capacity). **Module K's Hero Package Capacity Invariant reads Module A's `qty`** (Module K §13 — the hard cross-module contract, both sides KEPT). | §13 + Module 0 §3.7 + Module K §13 | AUTO — inspect entity schema; assert no `is_hero_package` field; assert `qty` is exposed for Module K's invariant read (Module K enforcement verified when Module K lands) |

### §6.5 Bidirectional contract — Module B (Inventory Authority + Digital Provenance) **(FLOOR — unchanged)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-XM-12** | Module B reads from Module A: `non_serialized_offer_admitted`, `qty_to_serialize`, `qty_non_serialized`, derived `serialization_type`. Drives NFC + NFT on serialized stock; non-serialized stock skips the digital-provenance pipeline (DEC-186). *(NFT decoupled per D12; the non-serialized path is the launch fallback.)* | §11.5 + §7 + DEC-186 | AUTO — drive allocation with mixed sub-pool; assert Module B serialization pipeline reads correct values + applies NFC/NFT to `qty_to_serialize` units only (Module B side verified when Module B lands) |
| **AC-A-XM-13** | Module A reads from Module B: per DEC-187, downstream consumer of Module B's inventory events (per-sub-pool ATP push). Strongly-consistent ATP cache per allocation. Hold placement reads cache + validates against StockPosition at the transactional boundary. | §11.5 + §11.5.1 + DEC-187 | AUTO — covered by AC-A-EVT-22 through AC-A-EVT-32 |
| **AC-A-XM-14** | Two-layer no-overselling guard per Q-CL-5 + DEC-185/187/196: Layer 1 (Module A allocation-pool, `qty − issued ≥ 0`, DEC-099) ∧ Layer 2 (Module B physical-inventory, `physical_in_storage − reserved − quarantined − under_adjustment ≥ 0`). Both must pass at hold placement / voucher issuance, strongly consistent; either failure rejects the hold. **(FLOOR — unchanged.)** | §7.1 + §11.5 + DEC-185 + DEC-187 | AUTO — drive the four corner cases ({L1 pass + L2 pass}→accept; the three failure combinations→reject); for each rejection assert the per-layer failure-attribution + the underlying counter values |
| **AC-A-XM-15** | Sub-pool composition at hold placement per DEC-187 + DEC-196 + BR-A-SubPool-2: SERIALIZED-offer line rejected if `atp_serialized` < requested; NON_SERIALIZED-offer line rejected if `atp_non_serialized` < requested. **Cross-sub-pool fungibility NOT admitted.** **(FLOOR — unchanged.)** | §7.1 + §11.5 + DEC-187 + DEC-196 | AUTO — drive SERIALIZED-offer with insufficient `atp_serialized` but sufficient `atp_non_serialized`, assert rejection; the inverse for NON_SERIALIZED-offer; for each assert the per-sub-pool ATP values + the partition-enforcement constraint in the rejection trace |
| **AC-A-XM-16** | Module B does NOT edit Module A state; Module A does NOT edit Module B state. The contract is event-based — Module A consumes Module B inventory events for ATP cache + shortfall; Module A emits `VoucherCancelled` as the release primitive Module B consumes (DEC-099 + Q-CL-6). | §11.5 + DEC-099 | AUTO — inspect Module A operations API; assert no direct write into Module B inventory ledger; (Module B side verified when Module B lands) |

### §6.6 Downstream reads — Module C (Fulfilment) + Module E (Finance)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-XM-17** | Module C reads Allocation context (sub-pool partition; counterparty for chain-of-custody) at shipment time for late binding (BMD §5.5). Module C's shipment gate is Module D `InboundEventPhysicallyAccepted` (decoupled from `state = ACTIVE` sellability per DEC-081). Vouchers issued pre-physical-receipt display "in transit; ETA X" (carrier-ETA-precision deferred, D17). | §11.6 + DEC-081 | AUTO — Module-A-side covered for decoupling by AC-A-EVT-21; assert Allocation exposes sub-pool partition + counterparty read API (Module C late-binding verified when Module C lands) |
| **AC-A-XM-18** | Module E reads `commercial_terms` at settlement-event time; `producer_id` / `supplier_id` for settlement routing; `ProducerAgreement.settlement_cadence` (Module K) for cadence-driven statements. Module E does NOT edit Module A state. *(Settlement engine deferred, D19 — operator-run first; Module A preserves the per-constituent lineage the deferred OC 5% + settlement read, Phase C item E.)* | §11.7 + §10.7 + AC-A-BR-Ops-6 | AUTO — Module-A-side: assert Allocation exposes `commercial_terms` + counterparty FKs via read API; assert state unchanged when Module E settlement events arrive (Module E statement generation verified when Module E lands) |

### §6.7 Sanctions-blind boundary **(FLOOR chain 2 — unchanged)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-XM-19** | Module A is sanctions-screening-blind per §9 + DEC-071. The substantive enforcement point is order completion in Module S; Module A does not gate voucher issuance or allocation operations on `Customer.sanctions_status`. KYC at Customer level (Module K §9.1) is similarly enforced at order completion in Module S, not at Module A. *(Mirror of Module K §9.3 — K + A sanctions-blind by design.)* | §9 + §10.7 BR-A-CrossModule-3 + DEC-071 | AUTO — covered by AC-A-BR-CrossModule-3; additionally drive allocation operations with a simulated non-passed `Customer.sanctions_status`, assert all operations proceed |

### §6.8 Boundary statements — Module A does NOT carry

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-XM-20** | Module A does NOT own Offer entity, customer-facing pricing surfaces, cart hold, checkout, voucher issuance against a customer order, or sell-through event. Module S. *(The deferred multi-producer composite-Offer construct, D7, is Module S's — §3.1.)* | §13 | AUTO — inspect API; assert no Offer / cart / checkout / voucher-issuance / sell-through endpoints |
| **AC-A-XM-21** | Module A does NOT own ProcurementIntent, PurchaseOrder, InboundEvent, ConsignmentReceipt, ReverseInboundEvent, SupplierProducerLink, supplier-payment execution, landed-cost computation, or DiscrepancyResolution. Module D. | §13 | AUTO — schema inspection; assert no PI / PO / InboundEvent / SupplierProducerLink / cost-computation entities |
| **AC-A-XM-22** | Module A does NOT own inventory-ledger authority: InboundBatch, SerializedBottle inventory state, Case + integrity FSM, StockPosition, QuarantineRecord, Stocktake, inventory-adjustment workflow, receiving physical-match check. Module B. Module A's allocation-pool layer is **Layer 1 of the two-layer guard**; Module B's physical layer is Layer 2. | §13 + §7.1 + DEC-185..DEC-196 | AUTO — schema inspection; assert no InboundBatch / SerializedBottle / Case / StockPosition / QuarantineRecord / Stocktake / inventory-adjustment entities |
| **AC-A-XM-23** | Module A does NOT own NFC tagging, NFT minting, predecessor/successor recovery chain, serialized bottle identity, or Bottle Page rendering. Module B *(NFT decoupled, D12)*. | §13 | AUTO — schema + API inspection; assert no NFC / NFT / Bottle-Page / serialized-bottle-state surface |
| **AC-A-XM-24** | Module A does NOT own pick / pack / dispatch, shipment, late binding, cellar render, in-transit display, delivery confirmation. Module C. | §13 | AUTO — API inspection; assert no fulfilment / shipment / cellar / in-transit surfaces |
| **AC-A-XM-25** | Module A does NOT own settlement-payment execution, INV1 / INV2 invoicing, payment-method records, or GL treatment. Module E + Xero (DEC-072). *(Settlement engine deferred, D19.)* | §13 + DEC-072 | AUTO — API inspection; assert no settlement-payment / invoicing / payment-method / GL-treatment surfaces |
| **AC-A-XM-26** | Module A does NOT carry an `unsold_handler_policy` field (Q-AD-17). At launch, per-allocation unsold-handling actions (recall, Discovery relist via §5.3.3 visibility flip, hybrid) are operator-driven via the Admin Panel at the moment of decision. | §13 | AUTO — schema inspection; assert no `unsold_handler_policy` field |
| **AC-A-XM-27** | Module A does NOT recognise Hero Package designation as a Module A attribute (Module S Offer-level designation per Module 0 §3.7 + Module K §13). | §13 + Module 0 §3.7 | AUTO — covered by AC-A-XM-11 |
| **AC-A-XM-28** | Module A does NOT carry active-consignment, drop-shipping, B2B credit terms, liquid voucher resolution, CruTrade P2P trading, or AgencyAgreement attributes. All OUT at launch (BMD §13). *(The `direct_purchase` enum value is retained-but-idle — the Direct-Purchase seam, §3.2; not "carried" as an active surface.)* | §13 + BMD §13 | AUTO — schema inspection; assert absence of all named attributes / event families; assert `direct_purchase` enum value present-but-unused (no `direct_purchase` allocation created at launch) |

### §6.9 MVP re-baseline criteria **(NEW — v0.3-MVP)**

The naming cascade, the Direct-Purchase enum/FSM seam, the L-PP Admin-Panel re-scope, the floor parity, and a scope-parity confirmation. These verify the **launch-MVP-specific** properties on top of the carried-over v0.1 contract.

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-A-MVP-1** | **Naming-cascade application (Phase C item A).** The catalog-identity criteria carry **Product Reference** / **Product Variant** (wine-display aliases *Bottle Reference* / *Wine Variant* retained): AC-A-J-1, AC-A-J-7, AC-A-BR-Identity-3, AC-A-BR-CrossModule-1, AC-A-XM-1, AC-A-XM-2; the consumed Module 0 events rename `BottleReferenceActivated/Retired → ProductReferenceActivated/Retired` (AC-A-EVT-18). **Module A's own `Allocation*` event + attribute names are unchanged** (category-neutral). Naming/contract only — zero behaviour change. | PRD §15; Phase C item A | AUTO — inspect the event registry + Allocation read API; assert no `Wine*` structural name appears in any catalog-identity read (wine-display aliases admissible in UI labels); assert the consumed Module 0 events carry the `Product*` names; assert Module A's own `Allocation*` names are byte-for-byte the v1.1 names; assert payload semantics unchanged |
| **AC-A-MVP-2** | **Direct-Purchase deferred-with-seam (D11 / Phase C item I).** At launch **no `direct_purchase` allocation is created**; passive V1 + V2 only. The `direct_purchase` **enum value** + the **uniform operator-publish FSM** (DEC-183) are **retained in Module A** as the seam (the §11.3.1 chain + the capacity-increase follow-on-PO note are documented-but-not-exercised; AC-A-J-5 / AC-A-XM-7 + the Direct-Purchase arms of AC-A-EVT-6/-9 → roadmap). | PRD §3.2 + §11.3.1; cut-sheet Q2 / Phase C item I | AUTO — assert no `direct_purchase` allocation exists in the launch dataset; assert the `direct_purchase` enum value + the uniform DRAFT→ACTIVE operator-publish trigger are present-but-not-invoked for Direct Purchase (the seam is verifiable; the Direct-Purchase chain is verified when the feature restores — AC-A-J-5 / AC-A-XM-7) |
| **AC-A-MVP-3** | **L-PP Admin-Panel re-scope (Q3 — producer write UIs deferred).** Every Module A operation is **operator-driven via the Admin Panel** at launch (`actor_role: newco_ops`); **Module A retains zero producer writes**. The DEC-083 operation-parity is a **backend contract** that is **unchanged** — the Producer-Portal write half is deferred-with-seam (built post-launch on the same backend). Producer Portal **read + full reporting (D23) is KEPT**. | PRD §2 + §3.3; cut-sheet Q3 / L-PP | MIXED — AI drives the §5.3 catalogue from the Admin Panel (asserts `actor_role: newco_ops` on every event) + asserts the backend exposes each operation from both surfaces (parity preserved) + asserts no producer write path is wired into the launch surface other than via the operator; Paolo confirms zero-producer-writes + the reporting-KEPT boundary |
| **AC-A-MVP-4** | **Floor parity (KEPT, unchanged).** The two-layer no-overselling guard (AC-A-XM-14), per-sub-pool composition + no cross-sub-pool fungibility (AC-A-XM-15), the ATP cache + reconciliation (AC-A-EVT-22..28/31/32), the shortfall workflow + `VoucherCancelled` release (AC-A-EVT-29/30), the KYC-cleared-Producer gate (AC-A-BR-CrossModule-2), and the sanctions-blind boundary (AC-A-XM-19) all stand and pass identically to v0.1. The **ATP-cache mechanics** (push/pull, cadence, tolerance, latency KPIs) are tech-implementation (DEC-073 / Q4) — not asserted. | PRD §7.1 + §9 + §11.5; cut-sheet Q4 / Phase C floor chains 1+2 | AUTO — re-run the floor criteria above; assert all PASS identically to v0.1; assert no floor criterion's *contract* changed (only the latency/mechanics moved out of scope per DEC-073) |
| **AC-A-MVP-5** | **MVP scope-parity confirmation (KEEP-in-full).** The supply primitive (single Allocation + two-FK + per-constituent `commercial_terms`), the FSM + the full mid-life mutation set (Q5), the sub-pool partition, Layer 2 breakability, the no-overselling floor, the Hero Package `qty` contract, and the per-constituent settlement lineage all stand and pass identically to v0.1; only the naming cascade + the Direct-Purchase/L-PP seam annotations + the E-emits alignment changed. The headline D7 cut is **forwarded to Module S** (Module A keeps the per-constituent seam — no D7 criterion is removed from Module A). **No launch-scope criterion was removed.** | PRD §0; cut-sheet §5; Phase C items A/E/G/I + floor chains 1/2 | MIXED — AI assembles the parity evidence (the carried-over supply-primitive + floor + Hero-`qty` + lineage criteria all PASS identically; a diff showing only the cascade + the Direct-Purchase/L-PP annotations + the E-emits alignment changed); Paolo confirms the re-cut is faithful and nothing in launch scope was dropped |

---

## §7 Out of scope for this acceptance pass

The following are deliberately excluded, in line with the methodology DECs in the header:

- **Engineering Definition of Done** (DEC-073): coverage thresholds, performance budgets, error-handling exhaustion, observability, retry/idempotency mechanics (esp. for the ATP push pipeline), schema design (column types, FK declarations, nullability as constraints, indexing on sibling-aggregation keys), API style + transport, deployment topology, **the ATP-cache literal store choice + the literal latency / tolerance / cadence thresholds** (cut-sheet Q4 — the cache *mechanics* are tech, not a product-spec cut; the *contract* is verified at AC-A-XM-14/15 + AC-A-EVT-22..32).
- **UI / UX acceptance**: Admin Panel form layouts + navigation (the launch write surface), the deferred Producer-Portal write-UI layouts, validation copy, accessibility, responsive design, UI-chrome i18n. The DEC-083 parity is a **functional contract** between the two surfaces; the UX layer is a separate UX track.
- **Operational R&R / approval-tier policy** (`feedback_prd_rr_approval`): which named individual approves what allocation operation; single-approver vs committee; tiered authority by sourcing model — admin-configurable. The ATP-cache reconciliation cadence is operator-configurable; not tested at specific values.
- **Non-functional concerns not anchored to a BR / DEC** at PRD level: latency budgets, throughput, alerting thresholds, error budgets, infrastructure, horizontal-scale architecture for the ATP cache rebuild.
- **Post-launch deferrals — acceptance moves to the roadmap with the feature** ([`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md)):
  - **Net-new MVP deferrals (seams retained — PRD §14.1):** **Direct Purchase *use*** — AC-A-J-5, AC-A-XM-7, and the Direct-Purchase arms of AC-A-EVT-6 / AC-A-EVT-9 are **not-exercised-at-launch; retained in the contract; restore with Direct Purchase** (the `direct_purchase` enum + uniform FSM are the seam, AC-A-MVP-2). **L-PP producer-write UIs** — the Producer-Portal write-surface half of AC-A-J-11 / AC-A-EVT-17 is **deferred-with-seam** (backend parity unchanged; re-scoped to the Admin-Panel surface at launch, AC-A-MVP-3).
  - **v1.1 already-deferred (PRD §14.2, carried verbatim):** active consignment + AgencyAgreement (DEC-011/017); SupplierAgreement entity (DEC-084); partial PO settlement (OQ-20); reverse-inbound full mechanics (reverse 3-gate QC, cost-basis unwind, partial-recall UX, recall-dispute path, automated return-shipment carrier coordination, reverse-discrepancy paths — Module D §17); drop-ship (OQ-17); liquid sales (BMD §13.4); CruTrade P2P (BMD §13.5); multi-warehouse (OQ-16); `parent_commitment_id` sibling FK; `BOTH` visibility reactivation (DEC-076); auto-FIFO waitlist conversion (DEC-069/079); auto-cascade on agreement transition (DEC-077); substitution full automation (DEC-104).
- **Cross-module behaviours owned by other modules**: Module S Offer-publication validation + cart/checkout/voucher-issuance + the deferred multi-producer composite-Offer (D7); Module D PI/PO/InboundEvent/reverse-inbound + the deferred Direct-Purchase procurement flow; Module B InboundBatch/SerializedBottle/Case/StockPosition/Stocktake/inventory-adjustment/QuarantineRecord + the decoupled NFT/on-chain layer (D12); Module C pick/pack/shipment/late-binding; Module E settlement/invoicing/Xero + the deferred settlement engine (D19); Module K Producer/Supplier/ProducerAgreement + the Hero Package Capacity Invariant enforcement (Module A side verified only at the boundary read pattern; the `qty` contract is verified here at AC-A-XM-11).
- **PRD ambiguities (AMB-A-1..5)** — collected in `greenfield/03-qa/qa.acceptance_ambiguities.md`; deferred to a future editorial pass; not addressed at this stage (orthogonal to MVP scope).

---

## §8 Sign-off log

### §8.1 Format-validation milestones (template-level)

| Milestone | Date | Notes |
|---|---|---|
| v0.1 PAOLO-VALIDATED (template) | 2026-05-15 | 111 criteria (102 AUTO / 8 MIXED / 1 HUMAN); four format conventions locked; G-2 follow-on-PO note + G-3 admitted-with-constraint; 3 MIXED→AUTO retags (EVT-31 / XM-14 / XM-15); G-1 composite-event + AMB-A-1..5 deferred. |
| **v0.3-MVP re-cut (Phase D)** | **2026-06-07** | **DRAFT — awaiting batch ratification.** Re-cut from the PAOLO-VALIDATED v0.1 per cut-sheet §5: naming cascade applied to the catalog-identity criteria (AC-A-J-1 / J-7 / BR-Identity-3 / BR-CrossModule-1 / XM-1 / XM-2 / EVT-18); re-anchored to the v0.3-MVP PRD (body §-anchors unchanged — KEEP-in-full, no structural insertion); **AC-A-J-5 + AC-A-XM-7 + the Direct-Purchase arms of AC-A-EVT-6/-9 annotated not-exercised-at-launch → roadmap** (enum + uniform-FSM seam retained; D11 / Phase C item I); **AC-A-J-11 + AC-A-EVT-17 re-scoped to the Admin-Panel operator surface** (L-PP / Q3 — backend parity unchanged); **BR-A-CrossModule-4 / EVT-20 / XM-6 aligned to the E-emits `SupplierPaymentCompleted` contract** (Phase C R4 — no Module A behaviour change); **§6.9 added** (5 MVP re-baseline criteria — naming cascade / Direct-Purchase seam / L-PP re-scope / floor parity / scope-parity). **~116 total (106 AUTO / 9 MIXED / 1 HUMAN).** No launch-scope criterion removed (KEEP-in-full). Floor criteria (two-layer guard / per-sub-pool ATP / `VoucherCancelled` release / KYC gate / sanctions-blind) re-affirmed unchanged. |

### §8.2 Per-AC delivery sign-off

Populated at first delivery review. Each criterion's state (OPEN / DEMOED / ACCEPTED) + Paolo's signature + date land here.

| AC ID | State | Date | Paolo signature | Notes / evidence reference |
|---|---|---|---|---|
| AC-A-J-1 | OPEN | — | — | — |
| ... | ... | ... | ... | ... |

(Full table populated at delivery; placeholder rows omitted in this draft.)

---

## §9 Cross-references

- **Spec source (this validates against)** — [`../02-prd/Module_A_PRD_v0.3-MVP.md`](../02-prd/Module_A_PRD_v0.3-MVP.md).
- **Predecessor (re-cut from)** — [`../../reference/v1.1/01-prd/Module_A_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_A_Acceptance_v0.1.md) (PAOLO-VALIDATED 2026-05-15; frozen, 111 criteria).
- **Cut-sheet (the delta spec)** — [`../01-triage/Module_A_CutSheet_v0.1.md`](../01-triage/Module_A_CutSheet_v0.1.md) §5 (acceptance delta), §6 (the five ratified Qs).
- **Phase C reconciliation** — [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) item A (naming cascade), item E (OC 5% capture — the per-constituent lineage), item G (two-layer guard + build-sequencing), item I (Direct Purchase deferred), R1 + R4 (`SupplierPaymentCompleted` financial-event-only + E-emits — Module A aligned, owns no RECONCILE), §6 floor chains 1 + 2 + committed-inventory.
- **Naming source of truth** — [`../02-prd/Module_0_PRD_v0.3-MVP.md`](../02-prd/Module_0_PRD_v0.3-MVP.md) §18.
- **Roadmap (deferred-feature acceptance moves here)** — [`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md).
- **Template precedent** — [`Module_K_Acceptance_v0.3-MVP.md`](Module_K_Acceptance_v0.3-MVP.md) + [`Module_0_Acceptance_v0.3-MVP.md`](Module_0_Acceptance_v0.3-MVP.md) (the prior Phase-D acceptance re-cuts; format mirrored here).
- **Sibling v0.3-MVP acceptance docs** (written alongside their PRDs) — Module D (the Direct-Purchase chain verified bidirectionally there when restored), then S / B / C / E, + the Admin-Panel PRD's acceptance. The Module B side verifies the ATP push pipeline + the `VoucherCancelled` consumer reciprocally.

---

*End of Module A Acceptance Criteria v0.3-MVP — Phase D re-baseline. Re-cut from the PAOLO-VALIDATED v0.1: naming cascade applied to the catalog-identity criteria, re-anchored (body §-anchors unchanged — KEEP-in-full), AC-A-J-5 + AC-A-XM-7 + the Direct-Purchase arms of EVT-6/-9 annotated not-exercised-at-launch → roadmap, AC-A-J-11 + EVT-17 re-scoped to the Admin-Panel operator surface, the `SupplierPaymentCompleted` criteria aligned to the E-emits contract (R4), §6.9 MVP re-baseline criteria added. **Floor criteria unchanged; nothing in launch scope removed.** ~116 criteria (106 AUTO / 9 MIXED / 1 HUMAN). **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E.*
