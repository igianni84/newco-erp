# NewCo ERP — Module K (Parties) Acceptance Criteria — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP acceptance contract for Module K; re-cut from the PAOLO-VALIDATED v0.1)
- **Date**: 2026-06-07
- **Status**: **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E. The acceptance delta is **very light — essentially naming-only**, like Module 0: Module K is **KEEP-in-full + naming cascade**, so this doc (a) applies the **naming cascade** (Wine Master → Product Master) to the Producer-link criteria, (b) re-anchors to the v0.3-MVP PRD, (c) **annotates** the two deferred-with-feature Club-Credit criteria (K.18 / K.19) + the idle gifting criterion → roadmap, and (d) adds a small **§6.7 MVP re-baseline** section. **No criterion in launch scope is removed; all floor criteria stand unchanged.**
- **Owner**: Paolo (product sign-off authority)
- **Companion spec**: [`../02-prd/Module_K_PRD_v0.3-MVP.md`](../02-prd/Module_K_PRD_v0.3-MVP.md) — the source of truth this document validates against. The PRD says *what to build*; this document says *what passes*. Together they are the dev-team's complete brief for the launch-MVP Module K.
- **Predecessor (re-cut from)**: [`../../reference/v1.1/01-prd/Module_K_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_K_Acceptance_v0.1.md) — the **PAOLO-VALIDATED** (2026-05-15) v1.1 acceptance template (125 criteria; format locked). `greenfield/` is frozen (plan R4); this is a derivative under `mvp/`.
- **Audience** (three concurrent uses): **Paolo** at module-delivery sign-off (verdict report + spot-checks); **dev team** during build (the definition of done, read alongside the PRD from day one); **AI coding agents** during code generation (AUTO criteria as fitness functions in the build loop).
- **Purpose**: the demonstrable behaviours that, taken together, constitute "Module K is delivered as specified per v0.3-MVP." Each criterion is traceable to a PRD anchor (BR-K-* / event / FSM transition / DEC / §) and tagged AUTO / MIXED / HUMAN.
- **Methodology DECs binding this document**: DEC-072 (no-accounting-policy claims — Module K consumes financial events from Module E; Xero decides GL; load-bearing for BR-K-Contract-2), DEC-073 (product-spec layer; criteria are business-behaviour, not tech-implementation), DEC-074 (self-contained; anchors restated inline), DEC-181 (sanctions/Hold uniformity — load-bearing for the §6.2 cross-module bucket), `feedback_prd_rr_approval` (producer-content approval role-count admin-configurable — out of scope as a build-time concern, tested only as separation-of-duties).
- **What this document is NOT**: engineering Definition of Done (coverage thresholds, performance budgets, retry mechanics, schema design); UI / UX acceptance (layouts, copy, accessibility, responsive design); operational R&R / approval-tier *policy* (admin-configurable); non-functional concerns not anchored to a BR/DEC at PRD level.

---

## §0 What changed from v0.1 (the re-cut delta)

Module K is **KEEP-in-full + naming cascade** with a **narrow, well-seamed set of net-new deferrals** (the two Club-Credit peripherals K.18 / K.19 + the gifting-init idle), so this acceptance re-cut is mechanical and additive:

1. **Naming cascade applied to the Producer-link criteria** (Phase C item A): `Wine Master → Product Master` in **AC-K-J-10, AC-K-XM-1, AC-K-XM-2, AC-K-BR-Producer-2, AC-K-BR-Producer-4**. **Wine-display aliases** ("Wine Master," "Bottle Reference") are retained where they aid wine-facing readers. **Behaviour is identical** — every renamed criterion tests the same business behaviour as its v0.1 original. **Module K's own event names are unchanged** (`Producer*`, `Customer*`, `Profile*`, `Club*`, `OriginatingClubLocked`, …) — PIM *consumes* `ProducerActivated`/`ProducerRetired`; only the consumer-note prose renames. **Module E's consumed-event names are unchanged** (`MembershipFeePaid`, `ClubCredit*`, …). See AC-K-MVP-1.
2. **Re-anchored to the v0.3-MVP PRD.** PRD §-numbers now refer to [`../02-prd/Module_K_PRD_v0.3-MVP.md`](../02-prd/Module_K_PRD_v0.3-MVP.md). **Module K had no structural entity insertion (KEEP-in-full), so the body §-anchors (§1–§16) are unchanged from v1.1** — every existing AC anchor remains valid as-is. Only §0 was prepended and the trailing sections repurposed (v1.1 §17 → §17 + MVP deferred set; v1.1 §18 v17-trace → §18 naming-cascade application + §19 MVP trace; cross-refs → §20).
3. **Two Club-Credit criteria annotated DEFERRED-WITH-FEATURE → roadmap (Q2 / Phase C item D — see §0.1):** **AC-K-J-17** (welcome-window proportional scaling = **K.18**) and **AC-K-J-16a** (operator manual Club-Credit issuance = **K.19**). The criteria are **retained, not deleted** — the seams are in Module K and the criteria verify the behaviour when the feature restores. **AC-K-J-16** (auto-issuance core) and **AC-K-J-18** (redemption / carry-forward = **K.17**) **stand and are exercised at launch** (Club-Credit core KEPT).
4. **One criterion annotated not-exercised-at-launch:** **AC-K-XM-4** (sanctions/Hold uniformity at **gifting initiation**) — gifting is deferred (D5, the S+K+C tri-module defer); the generic read-API is unchanged, so the criterion is **retained but idle at launch** (restores with the coordinated gifting set).
5. **New section §6.7 — MVP re-baseline criteria** (5 criteria, AC-K-MVP-1..5), verifying the naming cascade, the N2 trigger-agnostic Hold registry, the K.18 / K.19 deferred-seams, and a scope-parity confirmation.
6. **Floor criteria re-affirmed UNCHANGED:** all KYC, enhanced-KYC, sanctions, Hold, sanctions/Hold-uniformity, and anonymisation criteria (AC-K-J-7/7a/8/9/9a, AC-K-FSM-3/4/5/10/11, AC-K-XM-3/5..12) stand as-is. **Nothing in launch scope removed.** The Stage-6.5 gap-fill ACs (J-7a, EVT-12a, J-9a, J-16a, FSM-2a) all sit on floor or core mechanics and are retained — **except J-16a (manual Club-Credit issuance), which follows Q2** (deferred-with-feature).

### §0.1 The Club-Credit Q2 posture (how the K.16–K.19 criteria are treated)

Per the Module K cut-sheet Q2 ("Club Credit KEPT in K, decided in S") and the Module S cut-sheet §3.2 + Phase C item D (the substantive decision), the Club-Credit acceptance splits cleanly:

- **K.16 — entity + auto-issuance on `MembershipFeePaid` + one-active-per-Profile invariant — KEPT.** Exercised at launch: **AC-K-J-16, AC-K-FSM-17**.
- **K.17 — partial-redemption + carry-forward (`remaining_balance`) — KEPT** (load-bearing customer value; ratified S Q2). Exercised at launch: **AC-K-J-18** (redemption gate + same-club restriction; the carry-forward balance is how annual credit is spent across purchases).
- **K.18 — welcome-window proportional scaling — DEFERRED** (seam: the issuance hook + `policy × (fee_paid/full_fee)` formula retained in K; launch = full-fee → full-credit). **AC-K-J-17** → roadmap; **AC-K-MVP-3** verifies the launch behaviour (no scaling fires) + the retained seam.
- **K.19 — operator manual Club-Credit issuance — DEFERRED** (seam: the manual-create path retained in K; launch goodwill routes through the **single REFUND_COMPENSATION coupon**, Module S S.16). **AC-K-J-16a** → roadmap; **AC-K-MVP-4** verifies the path is present-but-not-exercised + the goodwill-via-coupon routing.
- **DEC-043 — closure-conversion — KEEP-lean** (owned by Module S; K's role ends at the cancellation signal): **AC-K-FSM-17** (Club-closure forfeiture trigger) + **AC-K-XM-23** stand.

---

## §1 How to use this document

### §1.1 Verification tags

- **AUTO** — an AI agent or automated harness reads the criterion + spec anchor + running system (event stream, entity state, API responses, audit trail) and produces a PASS/FAIL verdict with evidence. Paolo reviews the verdict batch.
- **MIXED** — AI prepares the evidence; Paolo confirms a judgment call (audit-trail completeness, cascade coherence, the scope-parity proof).
- **HUMAN** — Paolo executes personally (a single end-to-end demo session + subjective spot-checks).

**Distribution for Module K v0.3-MVP: ~130 total criteria** — the v0.1 125 (115 AUTO / 9 MIXED / 1 HUMAN) **+ 5 MVP re-baseline criteria (4 AUTO / 1 MIXED)** → **119 AUTO / 10 MIXED / 1 HUMAN.** Two v0.1 journey criteria (AC-K-J-16a, AC-K-J-17) and one cross-module criterion's launch-exercise (AC-K-XM-4) are **annotated** (deferred-with-feature / not-exercised-at-launch) rather than counted out — they remain in the contract for when the feature restores. Paolo's hands-on load: the **10 MIXED items + 1 end-to-end demo session.**

### §1.2 Build-time usage

Consulted from day one, not only at handover. The dev reads the PRD + this doc together; AUTO criteria wire into CI as scaffolding lands (the AUTO PASS rate is a continuous completion signal); AI coding agents treat AUTO criteria as fitness functions (read PRD anchor → generate code → run AUTO → iterate); MIXED/HUMAN items are scheduled, not surprised; the acceptance doc evolves with the spec in lock-step.

### §1.3 Sign-off cadence

Each criterion lands in **OPEN** (not yet demonstrated) → **DEMOED** (evidence produced) → **ACCEPTED** (Paolo signed off). Module K is **delivered** when every §2–§6 launch-scope criterion is ACCEPTED. Sign-off log at §8.

### §1.4 Anchors

PRD §-numbers refer to [`../02-prd/Module_K_PRD_v0.3-MVP.md`](../02-prd/Module_K_PRD_v0.3-MVP.md). BR-K-* refers to its §14. Event names refer to its §15. FSM states refer to its §4. DEC refers to the v1.1 Decision Register (cited inline). **(Body §-anchors are unchanged from v1.1 — §0 item 2.)**

### §1.5 Format conventions (locked at the v0.1 review; carried)

1. **§4 BR statements are verbatim from PRD §14** (self-containment per DEC-074; trivial drift detection). *For v0.3-MVP the verbatim statements carry the naming cascade on BR-K-Producer-2 / BR-K-Producer-4 (Wine Master → Product Master), matching the v0.3-MVP PRD §14 prose.*
2. **§4 BR→AC pointer rows preserve traceability** (every BR has an explicit AC ID row, even when covered by an upstream §2/§3 criterion).
3. **§6 cross-module criteria verify the Module-K-side surface only** (downstream behaviour verified in the receiving module's acceptance doc; no dual-side overlap).
4. **AUTO criteria dependent on consumer modules carry inline "verified when X lands" notes** (Module K emits ~30 events; Module-K-side emission/schema verified at Module K handover, downstream consumption when the consumer module is built).
5. **(NEW, v0.3-MVP)** **MVP re-baseline criteria live in §6.7** (AC-K-MVP-*); **deferred-with-feature criteria carry an inline "→ roadmap (restores with the feature)" note** (AC-K-J-16a, AC-K-J-17); **the idle gifting criterion carries a "not-exercised-at-launch (D5)" note** (AC-K-XM-4).

---

## §2 Canonical journeys — end-to-end operator and customer flows

The journeys cover registration through onboarding (three flows), first-Club approval + Originating-Club lock, marketing-consent double-opt-in, KYC submission + clearance, sanctions screening + Hold flow, soft-delete + anonymisation, Producer onboarding through ProducerAgreement activation, Hero Package capacity-invariant enforcement, Club-credit accrual + redemption, Producer offboarding cascade, and the sanctions/Hold uniformity gate at a representative surface.

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-J-1** | Direct registration: prospect submits email + password + name + DOB; email verification + T&C / privacy acceptance + synchronous sanctions screening run; Customer transitions `pending → active` once all gates clear; Account + Party records auto-provision. | §7.1; BR-K-Identity-3, BR-K-Customer-1 | AUTO — drive scripted registration; assert Customer + Account + Party persisted; assert `pending → active` only after all four gates clear |
| **AC-K-J-2** | Club-link registration: prospect arrives via a Club-specific link; the resulting Profile auto-binds to the linking Club in `Applied` state; Customer + Account + Party + Profile all created within the same flow. | §7.2 | AUTO — drive flow with a known Club-binding link; assert Profile created with correct `club_id` in `Applied` state |
| **AC-K-J-3** | Producer-initiated invitation: invitation recorded (operator-driven via Admin Panel at launch — L-PP); `MembershipInvitationSent` fires (HubSpot consumes); invitee accepts via link; `MembershipInvitationAccepted` fires; Customer + Account + Party + Profile created; Profile typically lands in `Approved` per pre-approval. | §7.3; events `MembershipInvitationSent`, `MembershipInvitationAccepted` | AUTO — drive invitation send + accept; assert event sequence; assert Profile state on registration *(producer-invite UI out of scope — operator-driven at launch; backend unchanged)* |
| **AC-K-J-4** | First-Club approval: a Customer's first Profile transitions to `Approved`; `OriginatingClubLocked` fires exactly once and sets the Originating Club to the approving Club; subsequent approvals on other Clubs do NOT re-fire the event or change the Originating Club. | §6.1 + §7.4; event `OriginatingClubLocked`; BR-K-OC-1, BR-K-OC-2 | AUTO — drive two sequential approvals on two Clubs; assert `OriginatingClubLocked` fires once on the first approval only; assert Originating Club is the first approver |
| **AC-K-J-5** | No-OC allowance: a Customer registers, never applies to any Club, buys on Discovery; transacts freely (subject to sanctions / KYC gates); Originating Club stays unset; a later Discovery purchase generates no 5% accrual. | §6.2 + §6; BR-K-OC-3 | AUTO — drive Discovery purchase for Customer with unset Originating Club; assert purchase succeeds; assert no Originating-Club-share accrual event |
| **AC-K-J-6** | **(Marketing KEPT — Q1.)** Marketing-consent double opt-in: Customer opts in (consent → `requested`); Module K sends confirmation email; Customer clicks confirmation link (consent → `confirmed`); HubSpot starts sending marketing; Customer unsubscribes (consent → `revoked`); HubSpot stops. Transactional emails are unaffected throughout. | §8.1; DEC-026 | MIXED — AI drives the full state cycle + assembles the HubSpot-side delivery trace + the transactional-email continuity evidence; Paolo confirms the marketing-vs-transactional separation reads cleanly *(campaigns ship at launch — no change from v0.1)* |
| **AC-K-J-7** | KYC submission + clearance **(FLOOR)**: Compliance flags KYC-required (KYC → `pending`); a `kyc` Hold auto-places; purchase attempts blocked; verification clears (KYC → `verified`); the `kyc` Hold auto-lifts; purchases resume. | §9.1; BR-K-Hold-1, BR-K-Hold-2 | AUTO — drive KYC required + verified path; assert auto-Hold + auto-lift; assert purchase blocked while Hold active, unblocked after |
| **AC-K-J-7a** | Enhanced-KYC threshold trigger **(FLOOR)**: a single transaction crossing €10k triggers the threshold-detection workflow (periodic background job + at-order-completion check); the Enhanced-KYC flag + timestamp record the trigger; a Compliance review-queue entry is created. Cumulative annual ≥ €50k triggers the same workflow with the same downstream signals. | §4.1 + §9.1; DEC-035 | AUTO — drive €10k single transaction; assert flag + timestamp + review-queue entry; drive cumulative ≥ €50k via N transactions; assert same signals; drive both at-order-completion AND periodic-job paths; assert identical state |
| **AC-K-J-8** | Sanctions screening fails **(FLOOR)**: synchronous onboarding screen returns `failed`; Customer cannot transact; Compliance reviews case-by-case; on resolution to `passed`, Customer transitions `active` and can transact; on confirmed `failed`, Customer stays blocked and the resolution path is recorded. | §9.2 + §9.3 | MIXED — AI drives the failed-screening + Compliance-resolution paths + assembles the audit-trail; Paolo confirms the case-by-case resolution recording satisfies the Compliance Reviewer workflow |
| **AC-K-J-9** | Soft-delete + anonymisation **(FLOOR)**: Compliance approves a right-to-erasure request; Module K overwrites Customer PII + Address personal fields with deterministic placeholders; HubSpot sync removes the PII; transactional history (Profile / Order / Voucher / Invoice rows) survives keyed by the anonymised identifier; vouchers remain valid. | §8.2; DEC-027 | MIXED — AI assembles the before/after audit-trail trace + the HubSpot-side sync evidence + a sample voucher-validity check; Paolo confirms PII severance + history-preservation completeness |
| **AC-K-J-9a** | GDPR right-to-erasure × active-Hold precedence **(FLOOR)**: regulatory-record-retention Holds (sanctions-OFAC, `compliance`) block anonymisation; non-regulatory Holds (`payment`, `kyc`, `admin`, `fraud`, `credit`) do not. A request with an active regulatory Hold is blocked with the Customer informed; with a non-regulatory Hold it proceeds and the Hold metadata anonymises alongside the PII. | §8.2 (Stage 6.5 DEC-027) | MIXED — AI drives anonymisation under each Hold-type combination + assembles the per-Hold-type precedence trace + before/after audit evidence; Paolo confirms the precedence matrix matches Compliance + Legal expectation |
| **AC-K-J-10** | Producer onboarding: Operator creates a Producer (`draft`); files KYC; KYC clears; the Creator → Reviewer → Approver workflow runs against the Producer content (name, region, description, website) at the configured role-count; `ProducerActivated` fires on `draft → active`. PIM **Product Master** *(wine-display alias: Wine Master)* activation under this Producer is now admitted. | §4.4; events `ProducerCreated`, `ProducerActivated`; BR-K-Producer-2 | AUTO — drive full onboarding; assert KYC gate; assert workflow with distinct actors at the configured depth; assert event emission. **(Naming cascade: Product Master — §18; behaviour identical.)** |
| **AC-K-J-11** | ProducerAgreement activation: Operator drafts a ProducerAgreement (`draft`) against an active Producer; sets term dates, settlement cadence, minimum-commitment, payment-terms; transitions to `active`; `ProducerAgreementActivated` fires; Module D consumes for procurement gates; Module E reads settlement cadence at first settlement. | §4.6 + §4.6.1; events `ProducerAgreementCreated`, `ProducerAgreementActivated`; BR-K-Agreement-1, BR-K-Agreement-2 | AUTO — drive activation; assert state transition + event emission; assert placeholder field set persists per §4.6.1; Module D procurement-gate + Module E settlement-cadence read verified when those modules land; Q-OQ-9 template fields verified post-template-landing *(D19 settlement engine deferred — the recorded cadence is the seam)* |
| **AC-K-J-12** | ProducerAgreement renewal via supersession: a new agreement is activated against the same Producer scope; the prior transitions to `superseded`; `ProducerAgreementSuperseded` fires; audit history pairs old + new. | §4.6.1; BR-K-Agreement-3; event `ProducerAgreementSuperseded` | AUTO — drive two sequential activations; assert prior transitions to `superseded`; assert audit-pair link |
| **AC-K-J-13** | Hero Package capacity invariant at membership approval: a Club has a Hero Package allocation of qty 50; 50 `Active` Profiles; an attempt to transition a 51st Profile to `Approved`→`Active` is rejected; the Profile lands in `WaitingList`; `WaitingListJoined` fires. | §13.1 + §13.5; event `WaitingListJoined` | AUTO — set up Club with qty 50 + 50 active Profiles; drive 51st application; assert WaitingList placement + event emission |
| **AC-K-J-14** | Mid-year capacity increase + waitlist conversion **(Q5 — KEPT)**: Producer increases Hero Package allocation 50 → 70 via Module A; Module K consumes the capacity-adjustment signal; the Producer may approve up to 20 waitlisted applicants; producer-discretionary order is admitted (no implicit FIFO). | §13.4 + §13.5; DEC-069 | MIXED — AI drives the capacity-adjustment + waitlist-conversion scenarios + assembles the producer-discretionary-order evidence; Paolo confirms the mechanic reads cleanly (no implicit FIFO) |
| **AC-K-J-15** | Capacity-decrease constraint: a Producer attempts to reduce Hero Package allocation below the current count of `Active` Profiles; Module K rejects. Reductions above the current count succeed. | §13.4 | AUTO — negative path: decrease below active count, assert rejection; positive path: decrease above active count, assert success |
| **AC-K-J-16** | **(Club-Credit core — KEPT.)** Club Credit accrual: Module E emits `MembershipFeePaid` on a Profile whose Club has `generates_credit = true`; Module K records `Profile.fee_paid_at`, transitions Profile to `Active` (or fires `ProfileRenewed` on renewal), and records a new Club Credit per §11.1. One-active-per-Profile invariant preserved. | §11.1; consumed events `MembershipFeePaid`, `ClubCreditIssued` | AUTO — emit `MembershipFeePaid` against a `generates_credit = true` Club; assert Profile + Club Credit state; emit a second fee-paid for renewal; assert prior credit forfeited per the renewal forfeit-before-issue rule |
| **AC-K-J-16a** | **⛔ DEFERRED-WITH-FEATURE → roadmap (K.19, Q2).** Operator manual Club Credit issuance (goodwill / make-right): Operator manually creates a Club Credit on a Profile via Admin Panel; satisfies the one-active-per-Profile + issuance-currency invariants + audit (actor + reason); not blocked by active Holds (issuance asymmetry). | §11.1 | AUTO — **at launch this path is not exercised** (goodwill routes through the REFUND_COMPENSATION coupon, Module S — see AC-K-MVP-4); **the criterion is retained for when the feature restores** (the manual-create seam is preserved in Module K). When restored: drive manual create positive path; assert audit; drive prior-active-credit → rejection; drive mismatched currency → rejection; drive with active `fraud` Hold → issuance succeeds (asymmetry). |
| **AC-K-J-17** | **⛔ DEFERRED-WITH-FEATURE → roadmap (K.18, Q2).** Welcome-window scaled Club Credit issuance: `MembershipFeePaid` reports fee paid < full fee; the auto-generated Club Credit scales proportionally (`credit = policy_amount × (fee_paid / full_fee)`); credit ≤ fee_paid. | §11.1 | AUTO — **at launch no proportional scaling fires (full-fee → full-credit — see AC-K-MVP-3); the criterion is retained for when welcome-window scaling restores** (the issuance hook + formula are preserved in Module K). When restored: drive partial-fee scenario; assert generated credit = policy_amount × (fee_paid / full_fee) to numerical precision; assert credit ≤ fee_paid across boundary cases |
| **AC-K-J-18** | **(K.17 carry-forward — KEPT; exercised at launch.)** Club Credit redemption against issuing Club: a Customer redeems against an Offer whose `club_id` is in the credit's issuing-Club set + currency matches → admitted; partial redemption leaves a `remaining_balance` that carries forward; cross-club redemption against a different Club's Offer is rejected. | §11.2 + §11 (remaining_balance) | AUTO — positive path: same-club redemption; partial-redemption path: assert `remaining_balance` carries forward across a subsequent purchase; negative path: cross-club redemption → rejection *(redemption math is Module S; Module-K-side eligibility + balance state verified here; Module S apply-side verified when S lands)* |
| **AC-K-J-19** | Producer offboarding cascade: Operator retires a Producer (`ProducerRetired` fires); each Club operated transitions to `sunset` (`ClubSunset` per Club); Module K transitions each Profile per §10.2; vouchers remain valid; Module S consumes the per-Profile cancellation signal for Club Credit conversion (DEC-043). | §10.2; events `ProducerRetired`, `ClubSunset`; per-Profile cancellation signal §15.7 | MIXED — AI assembles the cascade event trace + Module S Club-Credit-conversion evidence + voucher-validity sample; Paolo confirms voucher integrity + cascade completeness |
| **AC-K-J-20** | Sanctions/Hold uniformity at order completion **(FLOOR)**: a Customer with `sanctions_status = pending` (or any active Hold) attempts to complete an order; Module S reads sanctions + Hold state at moment of action and rejects with a screening-required / Hold signal. When sanctions resolves to `passed` AND no Hold is active, the next attempt succeeds. | §4.8 (DEC-181) + §9.3; BR-K-Hold-2 | AUTO — drive four combinations (sanctions × Hold: both clean, one set, other set, both set); assert Module S gate behaviour for each *(K-side read-API tuple verified here; Module S enforcement [S.15] verified when S lands)* |
| **AC-K-J-21** | End-to-end demo session: Paolo observes the Customer Care + Producer Onboarding + Compliance Reviewer teams walking direct registration, Club-link registration, first-approval + Originating-Club lock, KYC + sanctions screening, marketing-consent double opt-in, Producer onboarding + ProducerAgreement activation, Hero Package capacity flow (incl. a mid-year increase), Club Credit accrual + redemption, soft-delete + anonymisation, Producer offboarding cascade. | §1–§13 (full surface) | HUMAN — single session, ~90 min, with dev + Customer Care + Compliance team; Paolo signs off on observed behaviour |

---

## §3 State machine round-trips — entity FSMs

Module K manages ten state machines: Customer, Profile, KYC (separate from Customer), sanctions (separate from Customer), Club, Producer, Supplier (party-marker only), ProducerAgreement, Account, and Hold. Each is exercised for transition correctness + event emission.

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-FSM-1** | Customer FSM traverses `pending → active → suspended → closed` with `CustomerCreated` / `CustomerActivated` / `CustomerSuspended` / `CustomerReactivated` / `CustomerClosed` on the corresponding transitions. | §4.1 + §15.1; BR-K-Customer-1 | AUTO |
| **AC-K-FSM-2** | Profile FSM traverses the full generic state machine (`Applied → WaitingList/Approved/Rejected`, `Approved → Active`, `Active → Suspended`, `Suspended → Active`, `Active → Lapsed`, `Lapsed → Active`, `Lapsed → Cancelled`, `Active → Cancelled`, `Active → Inactive`). Events `ProfileCreated`/`ProfileActivated`/`ProfileExpired`/`ProfileRenewed`/`ProfileSuspended`/`ProfileReactivated`/`ProfileInactive` fire per transition. | §4.2.1 + §15.2; BR-K-Profile-1 | AUTO — drive every transition in the parametrised matrix; assert event emission per row; assert the FSM transition-reason enum contains no Crurated-Member-specific reason (schema-level inspection); assert audit trail per transition |
| **AC-K-FSM-2a** | Profile suspension state-preservation guarantee: `Active → Suspended` does NOT cancel vouchers, orders, allocation reservations, or Club Credit balance. Active vouchers stay ACTIVE; pending orders pending; reservations reserved; Club Credit frozen (no accrual, no redemption during suspension). On `Suspended → Active`, Club Credit becomes mutable again. | §10.1; BR-K-Hold-5 | AUTO — drive suspension on a Profile with (a) active voucher, (b) pending order, (c) allocation reservation, (d) active Club Credit; assert each downstream state unchanged; assert Club Credit no-mutation guard during suspension; restore to active; assert Club Credit re-mutable; assert no `ProfileExpired`/`ProfileCancelled` fires during suspension |
| **AC-K-FSM-3** | KYC FSM is **separate from Customer FSM** and traverses `not_required → pending → verified` (success) and `not_required → pending → rejected` (fail). Setting `kyc_required` transitions `not_required → pending`. A `kyc` Hold auto-places on `pending` and auto-lifts on `verified`. **(FLOOR.)** | §9.1 + §4.8; DEC-071 | AUTO — verify FSM state surface is distinct from Customer state; assert auto-Hold placement + lift |
| **AC-K-FSM-4** | Sanctions FSM is **separate from Customer FSM and KYC FSM** and traverses `pending → passed/failed/under_review`, `under_review → passed/failed`. Re-screening fires `CustomerRescreeningPassed`/`CustomerRescreeningFailed`. **(FLOOR.)** | §9.2; DEC-071; events `CustomerRescreeningPassed`/`CustomerRescreeningFailed` | AUTO — verify FSM state surface is distinct from KYC state; drive each transition; assert events |
| **AC-K-FSM-5** | Sanctions and KYC are independent **(FLOOR)**: a Customer with `kyc=verified` + `sanctions=pending` is blocked (sanctions gate); `sanctions=passed` + `kyc=pending` is blocked (KYC `kyc` Hold). Each gate fires independently. | §9.4; BR-K-Hold-2 | AUTO — drive 4-cell matrix of (kyc, sanctions) combinations; assert gate fires for each non-clean combination |
| **AC-K-FSM-6** | Club FSM traverses `active → sunset → closed` with `ClubCreated` / `ClubSunset` / `ClubClosed`. Sunset blocks new memberships + offers but preserves existing Profiles. | §4.3 + §15.3; BR-K-Club-3 | AUTO |
| **AC-K-FSM-7** | Producer FSM traverses `draft → active → retired` with `ProducerCreated` / `ProducerActivated` / `ProducerRetired`. Activation gated on Producer KYC = `verified`. | §4.4 + §15.4; BR-K-Producer-2 | AUTO — negative path: attempt activation with KYC ≠ `verified`, assert rejection |
| **AC-K-FSM-8** | ProducerAgreement FSM traverses `draft → active → superseded` (renewal) and `draft → active → terminated` (early-end). Events `ProducerAgreementCreated`/`ProducerAgreementActivated`/`ProducerAgreementSuperseded`/`ProducerAgreementTerminated` fire per transition. | §4.6.1 + §15.5; BR-K-Agreement-1 | AUTO |
| **AC-K-FSM-9** | Account FSM traverses `active → suspended → closed` paralleling Customer FSM; Account-level Holds drive `active → suspended`; lift drives `suspended → active`. | §4.7 | AUTO |
| **AC-K-FSM-10** | Hold lifecycle **(FLOOR)**: a Hold is placed on a scope (Customer / Account / Profile) with one of six types (`admin`, `kyc`, `payment`, `fraud`, `compliance`, `credit`); records placement actor + moment; lift records lift actor + moment. `CustomerHoldPlaced`/`CustomerHoldLifted` (or Profile/Account analogs) fire on placement and lift. | §4.8 + §4.8.1 + §15.1 | AUTO — drive Hold place + lift on each of three scopes + six types; assert audit metadata + event emission |
| **AC-K-FSM-11** | Hold-lift discipline per Hold type (DEC-160) **(FLOOR)**: `kyc` and `payment` Holds **auto-lift** on KYC clear / payment success; `admin`, `fraud`, `compliance`, `credit` require **explicit operator lift**. Auto-lift on `admin`/`fraud`/`compliance`/`credit` is rejected. | §4.8 (Stage 6.5 DEC-160); BR-K-Hold-1 | AUTO — drive auto-lift trigger on each Hold type; assert auto-lift succeeds only for `kyc` + `payment` |
| **AC-K-FSM-12** | Profile lapsed grace: `Active → Lapsed` on validity-period expiry without renewal; a successful renewal within 30 days returns to `Active`; past 30 days, transitions to terminal `Cancelled`. | §4.2.1 + §5; BR-K-Profile-3; DEC-034 | AUTO — drive lapsed-within-grace + lapsed-past-grace paths |
| **AC-K-FSM-13** | Profile soft-delete invariant: terminal `Cancelled` and `Inactive` preserve audit history; Profile rows never hard-deleted at launch. | §4.2.1; BR-K-Profile-2 | AUTO — drive terminal states; assert row remains queryable in admin |
| **AC-K-FSM-14** | Customer-segment FSM (materialised view): segment refreshes on every Profile transition crossing a segment boundary; daily reconciliation catches drift; `CustomerSegmentChanged` fires on every change (Member ↔ Waiting-list ↔ Legacy ↔ unset); `CustomerTransitionedToLegacy` on Legacy materialisation. | §5; events `CustomerSegmentChanged`, `CustomerTransitionedToLegacy` | MIXED — AI drives the segment-transition matrix + assembles the reconciliation-job evidence; Paolo confirms strongest-segment-wins semantics (BMD §2.1) *(marketing consumers exercised at launch — Q1)* |
| **AC-K-FSM-15** | **(Marketing KEPT — Q1.)** Marketing-consent FSM traverses `none → requested → confirmed → revoked → requested` (re-opt-in). Each transition timestamped. Transactional emails independent of this state. | §8.1; DEC-026 | AUTO — drive full cycle including re-opt-in |
| **AC-K-FSM-16** | Soft-delete / anonymisation state **(FLOOR)**: anonymisation overwrites PII with deterministic placeholders + records moment; orthogonal to `closed` (a `closed` Customer can be un-anonymised; an anonymised Customer is typically also `closed`, inverse not required). | §8.2; BR-K-Customer-2 | AUTO — drive `closed` + non-anonymised path; drive anonymised path; assert orthogonality |
| **AC-K-FSM-17** | **(Club-Credit core — KEPT.)** Club Credit FSM traverses `active → redeemed` (full redemption) and `active → forfeited` (year-end lapse / renewal-replacement / Profile-cancellation / Club-closure). One-active-per-Profile invariant preserved across all paths. *(Club-closure forfeiture = DEC-043 conversion trigger, owned by Module S.)* | §11.1 + §11.3 | AUTO — drive each forfeiture trigger; assert one-active invariant holds across each path |

---

## §4 Business rule enforcement (BR-K-*)

One criterion per business rule in PRD §14. Where a BR's behaviour is fully captured by a journey or FSM criterion, the BR row cross-references. **§4 BR statements are verbatim from PRD §14** (carrying the naming cascade on BR-K-Producer-2/4).

### §4.1 Identity and uniqueness (BR-K-Identity-1..5)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-K-BR-Identity-1** | Email is globally unique across all Customers at NewCo. Email changes follow a verification + old-email confirmation workflow. | AUTO — create two Customers with different emails; attempt second Customer with duplicate email, assert rejection; drive email-change workflow + assert verification + old-email confirmation gates |
| **AC-K-BR-Identity-2** | A single Customer may hold multiple `Active` Profiles across different Clubs. One Profile per Customer per Club (uniqueness on the Customer-Club pair). | AUTO — drive three Profiles for one Customer across three Clubs, assert all `Active`; attempt second Profile on same (Customer, Club) pair, assert rejection |
| **AC-K-BR-Identity-3** | Acceptance is captured on Customer, applies across all the Customer's Profiles, and is a hard gate on the `pending → active` transition (alongside email verification and KYC clearance when required). | AUTO — covered by AC-K-J-1; additionally verify cross-Profile applicability (Profile on Club B needs no separate T&C acceptance) |
| **AC-K-BR-Identity-4** | A Customer must have at least one `Active` Profile to transact on a Club's offers. The check is enforced at order-completion time (Module S concern). Discovery purchases are independent of Profile state; a Customer with no Profiles can transact on Discovery freely subject to the standard sanctions / KYC gates. | AUTO — negative path: drive Club-Offer purchase for Customer with no `Active` Profile, assert rejection; positive path: drive Discovery purchase, assert success (Module-S-side order-completion enforcement verified when Module S lands) |
| **AC-K-BR-Identity-5** | The Party-type marker (Customer / Supplier / dormant Third-Party Owner) is immutable once set. A Customer cannot become a Supplier or vice versa. | AUTO — attempt to mutate Party-type marker on existing Party row, assert rejection |

### §4.2 Customer state (BR-K-Customer-1..3)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-K-BR-Customer-1** | A Customer follows `pending → active → suspended → closed`. Suspension is explicit (manual or via Hold) — not automatically driven by Profile state changes. | AUTO — covered by AC-K-FSM-1; additionally drive every Profile to `Cancelled` for one Customer, assert Customer stays `active` (not auto-suspended) |
| **AC-K-BR-Customer-2** | A `closed` Customer remains queryable in admin until anonymised. An anonymised Customer is queryable only as an opaque identifier. The two state changes are independent operations. | AUTO — covered by AC-K-FSM-16 |
| **AC-K-BR-Customer-3** | The Customer is not auto-suspended; the flag enables ops follow-up. Auto-suspension on zero active Profiles is a future-flexibility item. | AUTO — drive Customer to zero-active-Profile state; assert review-flag set + Customer status unchanged |

### §4.3 Profile state (BR-K-Profile-1..4)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-K-BR-Profile-1** | The Profile state machine is the same shape for every Club — there is no Crurated-Member-specific tier flow. | AUTO — covered by AC-K-FSM-2; additionally verify no Crurated-Member-specific state or transition appears in the implemented FSM |
| **AC-K-BR-Profile-2** | Profile is never hard-deleted at launch. Cancelled and Inactive are terminal soft-delete states preserving audit history. | AUTO — covered by AC-K-FSM-13 |
| **AC-K-BR-Profile-3** | A Lapsed Profile re-activates within 30 days on a successful renewal payment (no re-application required). After 30 days the Profile transitions to terminal Cancelled. | AUTO — covered by AC-K-FSM-12 |
| **AC-K-BR-Profile-4** | Every state transition that crosses a Profile-status boundary fires a corresponding domain event consumed by downstream modules and HubSpot (§15). | AUTO — covered by AC-K-FSM-2 |

### §4.4 Club state (BR-K-Club-1..5)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-K-BR-Club-1** | Every NewCo Club is associated with exactly one operating Producer; Club creation rejects a missing Producer association. | AUTO — attempt Club creation with no Producer reference, assert rejection; assert positive path succeeds |
| **AC-K-BR-Club-2** | A Club's operating-Producer link does not change with Club lifecycle state. Settlement deref to Producer works regardless of Club state (`active`, `sunset`, `closed`). | AUTO — drive Club through full lifecycle; at each state, assert Producer deref returns the same Producer |
| **AC-K-BR-Club-3** | A Club follows `active → sunset → closed`. Sunset blocks new memberships and new offers; closure is terminal once all members and obligations have resolved. | AUTO — covered by AC-K-FSM-6; additionally verify new-membership creation rejected when Club is `sunset` |
| **AC-K-BR-Club-4** | Multiple Clubs may share one operating Producer. The Originating Club mechanic resolves to the specific Club, not the aggregated Producer. | AUTO — set up two Clubs under one Producer; drive Customer's first approval into Club A; assert Originating Club = Club A (not the Producer aggregate) |
| **AC-K-BR-Club-5** | Every Club at NewCo launch is configured with a single tier; multi-tier activation post-launch is configuration only (no schema change). | AUTO — verify every Club's tier definitions contains exactly one tier; verify the structure supports multi-entry configuration |

### §4.5 Producer state (BR-K-Producer-1..4)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-K-BR-Producer-1** | The Producer entity is standalone in Module K. Some Producers may never have a Party / Supplier record at all. | AUTO — create a Producer with no corresponding Supplier record; verify Producer is independently queryable and used by the PIM **Product Master** link |
| **AC-K-BR-Producer-2** | A **Product Master** *(wine-display alias: Wine Master)* cannot be activated unless its linked Producer is `active` and KYC-verified. Producer KYC revocation does *not* deactivate existing active Product Masters; only new Product Master activations are blocked. | AUTO — covered by AC-K-FSM-7 + cross-module test at the Module 0 boundary; additionally drive KYC revocation post-Product-Master-activation, assert existing Product Masters remain `active` + new activations blocked (Module-0-side new-Product-Master-activation rejection verified when Module 0 lands). **(Naming cascade — §18; behaviour identical.)** |
| **AC-K-BR-Producer-3** | Creating a Producer does not auto-create a Supplier; creating a Supplier does not auto-create a Producer. Linking is an explicit operator action. | AUTO — create Producer, assert no Supplier auto-created; create Supplier, assert no Producer auto-created |
| **AC-K-BR-Producer-4** | When a Producer is retired, existing active **Product Masters** *(wine-display alias: Wine Masters)* under that Producer remain valid for current references; new Product Master activations are blocked. | AUTO — covered partially by AC-K-J-19; additionally verify PIM-side new-Product-Master-activation under retired Producer is rejected. **(Naming cascade — §18.)** |

### §4.6 ProducerAgreement (BR-K-Agreement-1..3)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-K-BR-Agreement-1** | At any given time, at most one ProducerAgreement is `active` per Producer scope. Multi-Club Producers may have either Producer-wide or per-Club scoping; the two shapes are mutually exclusive on the same Producer at the same time. | AUTO — attempt second `active` agreement at same scope, assert rejection; attempt Producer-wide + per-Club both `active` on same Producer, assert rejection |
| **AC-K-BR-Agreement-2** | The active ProducerAgreement's settlement cadence governs the per-Producer settlement event timing in Module E. Default cadence is the BMD-locked quarterly; per-Producer overrides are admitted via the agreement. | AUTO — covered partially by AC-K-J-11; additionally drive settlement-cadence override (monthly / semi-annual), assert Module E reads the override (verified when Module E lands; D19 deferred — the recorded cadence is the seam) |
| **AC-K-BR-Agreement-3** | Renewal creates a new ProducerAgreement that enters `active`; the prior transitions to `superseded`. Audit history pairs the two. | AUTO — covered by AC-K-J-12 |

### §4.7 Originating Club (BR-K-OC-1..4)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-K-BR-OC-1** | The Originating Club is set at the moment of the Customer's first `MembershipApprovedByProducer` across any Club's lifetime. The lock event fires once per Customer ever. | AUTO — covered by AC-K-J-4 |
| **AC-K-BR-OC-2** | Once set, the Originating Club is treated as immutable. There is no admin-override surface at launch. | AUTO — verify admin API surface contains no Originating-Club mutation endpoint; attempt direct mutation via state-edit, assert rejection |
| **AC-K-BR-OC-3** | A Customer may have no Originating Club indefinitely. The 5% Originating-Club share simply does not accrue on Discovery sales for those Customers. | AUTO — covered by AC-K-J-5 |
| **AC-K-BR-OC-4** | A Customer's Originating Club staying resolvable to its Producer for settlement purposes does not depend on the Club's lifecycle state — the Club's operating-Producer link is immutable once set. | AUTO — set up Originating Club; drive Club to `closed`; assert settlement deref still resolves to the Producer |

### §4.8 Hold and suspension (BR-K-Hold-1..5) **(FLOOR)**

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-K-BR-Hold-1** | A scope (Customer / Account / Profile) may carry multiple active Holds; any Hold blocks the activity types that Hold gates. | AUTO — place two concurrent Holds on one Customer; assert blocking; lift one, assert still blocked; lift the second, assert unblocked |
| **AC-K-BR-Hold-2** | Every transaction-initiation surface reads sanctions state and Hold state at the moment of action. Surfaces include (non-exhaustive at launch): order completion / purchase (Module S §10.1), gifting initiation (Module S §13), pickup handover (Module C §5), INV3 charge execution (Module E §10), refund routing (Module E §5), Cart Hold reservation at cart-add (Module S Cart entity / §11.6), SO `planned` transition (Module C §2.3), Voucher redemption-request (Module S §11.5), shipment-request initiation (Module C §3.3). Future transaction-initiation surfaces inherit by-property without explicit re-enumeration. Any active Hold blocks the surface's commercial action; cross-surface uniformity is structural, not enumerated. Anchored at §4.8 Hold entity. | AUTO — covered by AC-K-J-20 for order completion; expanded coverage in §6.2 (AC-K-XM-3..AC-K-XM-11) for every surface (each downstream surface's commercial-action enforcement verified in that module's acceptance doc) |
| **AC-K-BR-Hold-3** | A Customer-level Hold blocks every Profile under that Customer. | AUTO — place Customer-scope `fraud` Hold; verify every Profile under that Customer is blocked from transacting |
| **AC-K-BR-Hold-4** | A Profile-level Hold blocks only that Profile. | AUTO — place Profile-scope `payment` Hold on Profile X; verify Profile X blocked + Profile Y under same Customer unaffected |
| **AC-K-BR-Hold-5** | Holds block the creation of new commercial commitment, not the completion of in-flight commitment. Shipping orders already in picking or shipped state run to completion. | AUTO — set up in-flight SO in `picking`; place Customer-scope Hold; assert in-flight SO continues; assert new commitment blocked |

### §4.9 Cross-module contract (BR-K-Contract-1..3)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-K-BR-Contract-1** | Every Module K state transition emits a versioned domain event consumed by downstream modules and the HubSpot integration. Module K guarantees backward compatibility within a major event-schema version. | AUTO — inspect every Module K event for version field present; drive a minor-version additive change, assert downstream consumers continue without breakage |
| **AC-K-BR-Contract-2** | Module E records the events of payment, settlement, Club Credit financial impact, and credit / hold triggering. Module K consumes those events and records the resulting state on its own entities. Per DEC-072, the accounting integration determines GL treatment. | AUTO — covered by AC-K-J-16; additionally verify Module K emits no `ClubCredit*` event (Module E owns those) |
| **AC-K-BR-Contract-3** | HubSpot reads Module K's full customer-data sync and delivers all outbound customer communications. Module K never sends a Customer communication directly. | AUTO — inspect Module K outbound-communication API surface; assert no direct email / SMS sending capability; assert HubSpot sync events fire on relevant Module K state changes |

---

## §5 Domain event emission and consumption

Module K emits ~30 lifecycle and net-new events (§15.1–§15.7) and consumes ~10 events from Module E and others (§15.8). **Module K's own event names are unchanged by the naming cascade** (AC-K-MVP-1); **Module E's consumed-event names are unchanged.**

### §5.1 Customer-family event emission

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-EVT-1** | `CustomerCreated` on creation; `CustomerActivated` on `pending → active`; `CustomerSuspended` on `active → suspended`; `CustomerReactivated` on `suspended → active`; `CustomerClosed` on transition to `closed`. | §15.1 | AUTO |
| **AC-K-EVT-2** | `CustomerHoldPlaced` on Customer-scope Hold recorded; `CustomerHoldLifted` on lift. Audit metadata (actor, reason, moment) on the payload. | §15.1; §4.8 | AUTO |
| **AC-K-EVT-3** | `CustomerSegmentChanged` on every materialised-segment change (Member ↔ Waiting-list ↔ Legacy ↔ unset). HubSpot consumes for marketing-segment sync. | §15.1; §5 | AUTO *(marketing consumers exercised at launch — Q1)* |
| **AC-K-EVT-4** | `CustomerActivated` triggers HubSpot full customer-data sync; Module S enables transaction surfaces. | §15.1 | AUTO — (a) Module-K-side: emit event, assert payload + emission to HubSpot boundary; (b) downstream: HubSpot full-sync + Module S enablement verified when those land |

### §5.2 Profile-family event emission

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-EVT-5** | `ProfileCreated` / `ProfileActivated` / `ProfileExpired` / `ProfileRenewed` / `ProfileSuspended` / `ProfileReactivated` / `ProfileInactive` fire on the corresponding transitions. | §15.2 | AUTO — covered by AC-K-FSM-2 |
| **AC-K-EVT-6** | `ProfileTierChanged` fires on multi-tier-activated Clubs when a Profile's tier changes. Carries prior tier, new tier, transition reason (voluntary upgrade / downgrade / Producer-initiated kick / non-payment / KYC re-screen failure / *other* with mandatory note), actor. v17's four LEGACY-related reasons are NOT carried. | §15.2 + §4.2.1 | AUTO — drive each documented reason; assert reason enum excludes the four Crurated-Member-specific reasons |

### §5.3 Club / Producer / ProducerAgreement event emission

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-EVT-7** | `ClubCreated` on creation; `ClubSunset` on `active → sunset`; `ClubClosed` on `sunset → closed`. | §15.3 | AUTO — covered by AC-K-FSM-6 |
| **AC-K-EVT-8** | `ProducerCreated` on creation; `ProducerActivated` on `draft → active` (KYC verified); `ProducerRetired` on transition to `retired`. **(Event names K's own — unchanged by the cascade.)** | §15.4 | AUTO — covered by AC-K-FSM-7 |
| **AC-K-EVT-9** | `ProducerAgreementCreated` on draft creation; `ProducerAgreementActivated` on `draft → active`; `ProducerAgreementSuperseded` when a new agreement supersedes a prior one; `ProducerAgreementTerminated` on permanent end. Termination does NOT auto-cascade to Producer state. | §15.5 | AUTO — drive each transition; verify Producer state unchanged on `ProducerAgreementTerminated` |

### §5.4 NewCo-net-new event emission

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-EVT-10** | `OriginatingClubLocked` fires once per Customer ever, at the first `MembershipApprovedByProducer` across any Club; payload carries Customer, locking Club, moment, triggering membership. Consumers: Module S (settlement-eligibility); Module E (settlement-event enrichment); HubSpot. | §15.6 + §6.1 | AUTO — covered by AC-K-J-4; additionally verify payload fields + consumer wiring *(5% computation deferred with D19 — the accrual is recorded at launch as the seam; Phase C item E)* |
| **AC-K-EVT-11** | `MembershipInvitationSent` and `MembershipInvitationAccepted` fire at the corresponding invitation steps. `WaitingListJoined` fires when a Profile transitions to `WaitingList`. HubSpot consumes all three. | §15.6 + §7.3 + §4.2.1 + §13.5 | AUTO — covered by AC-K-J-3 (invitation pair) and AC-K-J-13 (waitlist) |
| **AC-K-EVT-12** | `CustomerOnboardingScreeningPassed`/`...Failed` fire at onboarding-time sanctions screening; `CustomerRescreeningPassed`/`...Failed` fire on the 12-month-cadence re-screening (daily job picks up due Customers). All four drive sanctions-status state. | §15.6 + §9.2; DEC-030 | MIXED — AI drives each outcome of both screening and re-screening + assembles cadence-detection evidence over a simulated annual window; Paolo confirms the cadence reads operationally correct |
| **AC-K-EVT-12a** | Sanctions re-screening trigger paths between 12-month cycles: (a) AML-threshold breach triggers lightweight re-screening + enhanced-KYC review-queue entry; (b) Compliance ad-hoc trigger via Admin Panel API. Both fire the same outcome events as the cadence path; the trigger source is recorded on the screening record (cadence / aml-threshold / compliance-ad-hoc). | §9.2 (Stage 6.5 DEC-030) | AUTO — drive AML-threshold breach; assert lightweight re-screen + review-queue entry + trigger-source `aml-threshold`; drive Compliance ad-hoc; assert re-screen + trigger-source `compliance-ad-hoc`; assert outcome events identical across all three sources |
| **AC-K-EVT-13** | `CustomerTransitionedToLegacy` fires when a Customer's segment materialises to `Legacy`. Consumer: HubSpot (legacy-segment outreach). | §15.6 + §5 | AUTO *(exercised at launch — Q1)* |

### §5.5 Per-Profile producer-initiated cancellation signal

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-EVT-14** | At Producer offboarding cascade (§10.2), Module K emits a per-Profile cancellation signal carrying the producer-initiated cancellation reason. Module S consumes for the Club Credit conversion (DEC-043). | §15.7 + §10.2 | AUTO — Module-K-side: drive Producer offboarding; assert one per-Profile signal per Profile (Module S Club-Credit conversion verified when Module S lands) |

### §5.6 Events Module K consumes (recorded by Module E and others)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-EVT-15** | Module K consumes `MembershipFeePaid` from Module E on payment-provider-confirmed success for an INV0 charge. Drives `Profile.fee_paid_at`, `ProfileActivated`/`ProfileRenewed`, and Club Credit auto-generation per §11.1. | §15.8 + §11.1 | AUTO — covered by AC-K-J-16 |
| **AC-K-EVT-16** | Module K consumes `ClubCreditIssued`/`ClubCreditApplied`/`ClubCreditRestored`/`ClubCreditForfeited` from Module E and records Club Credit lifecycle state on its own entity. Module K does NOT emit these. | §15.8 + §11.4 | AUTO — verify Module K event-emission registry contains no `ClubCredit*` event; verify each consumed event drives a state change |
| **AC-K-EVT-17** | Module K consumes `CustomerCreditHoldPlaced`/`CustomerCreditHoldLifted` from Module E (AR / credit-limit) and records the Hold state on its own entity. | §15.8 | AUTO — emit each event from Module E; assert Module K records corresponding Hold + emits `CustomerHoldPlaced`/`CustomerHoldLifted` follow-up |
| **AC-K-EVT-18** | Module K consumes `CustomerChargebackFlagged` from Module E (per DEC-168 chargeback chain) and creates a Hold of type `CHARGEBACK_REVIEW`; flags the Customer for fraud-pattern review. Module K is the Hold registry-of-record on chargeback; Module E does not create the Hold directly. **(N2: chargeback trigger AUTOMATED at launch — D21 KEPT; K-side registry unchanged.)** | §15.8 + §4.8 (Stage 6.5 DEC-168) | AUTO — drive chargeback flow; assert Module K creates the Hold + raises the fraud-review flag *(the upstream Airwallex chargeback automation is Module E's; the K-side registry behaviour is trigger-agnostic — see AC-K-MVP-2)* |
| **AC-K-EVT-19** | Module K consumes `StoragePaymentFailed` from Module E (per DEC-160 INV3 chain) and creates a Hold of type `STORAGE_PAYMENT_FAILED`; `StoragePaymentSucceeded` lifts the Hold for that cycle. Prior-cycle Holds remain until each is independently remediated. **(N2: storage-payment trigger MANUAL-FIRST at launch — D4 auto-escalation deferred; Module E emits these from its manual-first path; K-side registry unchanged.)** | §15.8 + §4.8 (Stage 6.5 DEC-160) | AUTO — drive failed-storage-payment; assert Hold creation; emit success for same cycle, assert Hold lift; verify prior-cycle Holds unaffected *(K-side registry behaviour is trigger-agnostic regardless of the upstream automation depth — see AC-K-MVP-2)* |

### §5.7 Event semantics

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-EVT-20** | Cascading events are emitted in parent-before-child order in cascade workflows (Producer retirement: `ProducerRetired` first, then `ClubSunset` per Club, then per-Profile signals). Consumers tolerate eventual-consistency arrival order; Module K guarantees emission order. Events are schema-versioned; backward compatibility guaranteed within a major version. | §15.9; BR-K-Contract-1 | AUTO — drive Producer offboarding; assert event arrival order in stream; versioning covered by AC-K-BR-Contract-1 |

---

## §6 Cross-module contracts + boundary respect

Module K is upstream of every commerce-facing module via Customer / Profile / Hold / Originating-Club state and Producer / ProducerAgreement state. The boundary is enforced both by what Module K emits and by what Module K does NOT do.

### §6.1 Producer link (downstream to Module 0 PIM)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-XM-1** | Module K owns the Producer entity (identity, KYC status, customer-facing description, status lifecycle). Module 0's **Product Master** *(wine-display alias: Wine Master)* holds a `producer_id` link to Module K's Producer Registry. | §4.4 + §16 | AUTO — Module-K-side: inspect Producer entity schema, assert Producer owns identity / KYC / description / lifecycle; verify Module K's Producer-read API is the single read surface (Module 0's `producer_id` link absence-of-duplication verified in Module 0 acceptance). **(Naming cascade — §18.)** |
| **AC-K-XM-2** | Module K emits `ProducerActivated` and `ProducerRetired`; Module 0 consumes the events to enable / block **Product Master** *(wine-display alias: Wine Master)* activation against that Producer. | §15.4 + §16 | AUTO — Module-K-side: drive activation + retirement, assert events emit with correct payload (Module 0's Product-Master-activation-gate consumption verified when Module 0 lands). **(Event names K's own — unchanged; only the consumer-note prose renames — §18.)** |

### §6.2 Sanctions/Hold uniformity at transaction-initiation surfaces (DEC-181, FLOOR)

**Future-surface inheritance note (PAOLO-VALIDATED 2026-05-15).** PRD §14 BR-K-Hold-2 enumerates 9 transaction-initiation surfaces "non-exhaustive at launch" and states future surfaces "inherit by-property without explicit re-enumeration." This inheritance applies at the AC layer: future surfaces inherit Module K's read-API tuple-verification via the uniform read-API at AC-K-XM-12; **no new XM-* row is required** when a future surface is added, provided it consumes the uniform read-API. The receiving-module-side commercial-action gate is the receiving module's AC concern.

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-XM-3** | Sanctions/Hold uniformity at **order completion / purchase** (Module S): Module K's read-API returns the current (`sanctions_status`, active-Hold-list) tuple; the order-completion gate consumes it. | §4.8 (DEC-181) + §9.3; BR-K-Hold-2 | AUTO — Module-K-side read-API tuple under each (sanctions × Hold) combination at the order-completion call site (commercial-action enforcement verified in Module S acceptance [S.15]; covered by AC-K-J-20 for the walkthrough) |
| **AC-K-XM-4** | **⚠ not-exercised-at-launch (D5 gifting deferred).** Sanctions/Hold uniformity at **gifting initiation** (Module S): Module K's read-API returns the (`sanctions_status`, active-Hold-list) tuple at moment of gift initiation. | §4.8 (DEC-181); BR-K-Hold-2 | AUTO — **the generic read-API is unchanged, but gifting is deferred at launch (the S+K+C tri-module defer), so this surface is not exercised at launch; the criterion is retained for the coordinated gifting restoration.** When restored: Module-K-side read-API tuple verified under each (sanctions × Hold) combination |
| **AC-K-XM-5** | Sanctions/Hold uniformity at **pickup handover** (Module C): read-API returns the tuple at moment of pickup handover. | §4.8 (DEC-181); BR-K-Hold-2 | AUTO — Module-K-side tuple verified under non-clean state (Module C enforcement verified in Module C acceptance) |
| **AC-K-XM-6** | Sanctions/Hold uniformity at **INV3 charge execution** (Module E): read-API returns the tuple at moment of INV3 charge. | §4.8 (DEC-181); BR-K-Hold-2 | AUTO — Module-K-side tuple verified under non-clean state (Module E enforcement verified in Module E acceptance) |
| **AC-K-XM-7** | Sanctions/Hold uniformity at **refund routing** (Module E): read-API returns the tuple at moment of refund routing. | §4.8 (DEC-181); BR-K-Hold-2 | AUTO — Module-K-side tuple verified under non-clean state (Module E enforcement verified in Module E acceptance) |
| **AC-K-XM-8** | Sanctions/Hold uniformity at **Cart Hold reservation at cart-add** (Module S): read-API returns the tuple at cart-add. | §4.8 (DEC-181); BR-K-Hold-2 | AUTO — Module-K-side tuple verified under non-clean state (Module S enforcement verified in Module S acceptance) |
| **AC-K-XM-9** | Sanctions/Hold uniformity at **SO `planned` transition** (Module C): read-API returns the tuple at the SO `planned` transition. | §4.8 (DEC-181); BR-K-Hold-2 | AUTO — Module-K-side tuple verified under non-clean state (Module C enforcement verified in Module C acceptance) |
| **AC-K-XM-10** | Sanctions/Hold uniformity at **Voucher redemption-request** (Module S): read-API returns the tuple at moment of redemption-request. | §4.8 (DEC-181); BR-K-Hold-2 | AUTO — Module-K-side tuple verified under non-clean state (Module S enforcement verified in Module S acceptance) |
| **AC-K-XM-11** | Sanctions/Hold uniformity at **shipment-request initiation** (Module C): read-API returns the tuple at shipment-request. | §4.8 (DEC-181); BR-K-Hold-2 | AUTO — Module-K-side tuple verified under non-clean state (Module C enforcement verified in Module C acceptance) |
| **AC-K-XM-12** | Sanctions/Hold uniformity API surface: Module K exposes a uniform "is this scope clear to transact?" read API returning a structured (`sanctions_status`, active-Hold-list) tuple. Every downstream gate (XM-3..XM-11) calls this single path; no surface bypasses with its own read. | §4.8 (DEC-181 source-of-truth) | MIXED — AI assembles the call-site inventory across all downstream gates + asserts no surface implements its own logic; Paolo confirms the uniform API is genuinely the single source-of-truth and that no DEC-181 carve-out has crept in |

### §6.3 Originating Club deref (downstream to Module S + Module E)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-XM-13** | Module K emits `OriginatingClubLocked` and exposes Customer.Originating-Club state for downstream read; Module S consumes the event and reads the state at Discovery-sale moment to determine the 5% Originating-Club share accrual. | §6 + §15.6; DEC-032, DEC-040 | AUTO — Module-K-side emission + state-read verified by AC-K-J-4 + AC-K-J-5 (Module S's 5%-accrual computation verified when Module S lands; **D19 — the accrual is recorded at launch, the computation defers**) |
| **AC-K-XM-14** | Originating Club deref to Producer for settlement works regardless of the Club's lifecycle state. Module E reads the Producer behind the Originating Club at settlement time; if the Club is `closed`, the Producer is still resolvable. | §6.2; BR-K-OC-4 | AUTO — covered by AC-K-BR-OC-4 |

### §6.4 Module-D / Module-E / HubSpot consumer bindings

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-XM-15** | Module K emits `ProducerActivated`, `ProducerRetired`, `ProducerAgreementActivated`, `ProducerAgreementSuperseded`, `ProducerAgreementTerminated` for downstream consumption; SupplierProducerLink is owned by Module D, NOT by Module K. | §15.4 + §15.5 + §16; BR-K-Producer-3 | AUTO — Module-K-side: emit each event, assert payload + schema; verify SupplierProducerLink entity does not live in Module K scope (Module D's procurement-gate + ProcurementIntent consumption verified when Module D lands) |
| **AC-K-XM-16** | Module K exposes ProducerAgreement settlement cadence on the active agreement; Module E consumes `ProducerAgreementActivated` to read settlement cadence and emits the per-Producer settlement event at the agreement-defined cadence. Module K records settlement-eligible state; Module E records the financial event. | §4.6 + §15.5 + §16; BR-K-Agreement-2 | AUTO — covered by AC-K-BR-Agreement-2 (Module E's per-Producer settlement-event emission verified when Module E lands; **D19 deferred — operator-run first cycles; the recorded cadence is the seam**) |
| **AC-K-XM-17** | HubSpot consumes Customer-family + Profile-family + segment events for outbound communication. Module K never sends a Customer communication directly. | §15 + §16; BR-K-Contract-3 | AUTO — covered by AC-K-BR-Contract-3 (HubSpot-side delivery verified at HubSpot-integration acceptance) |

### §6.5 Hero Package capacity invariant (cross-module to Module A)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-XM-18** | Module K does NOT store Club capacity as a Club attribute. The capacity number lives on the Hero Package's underlying Allocation owned by Module A. Module K's role is to ENFORCE the invariant by reading Module A's Allocation `qty` at every membership-approval transition. | §13.2 + §16 | AUTO — inspect Club entity schema, assert no capacity field; drive approval flow, assert Module K reads Module A `qty` at the gate (Module A `qty` storage verified when Module A lands — A is KEEP; Phase C item G) |
| **AC-K-XM-19** | Hero Package shape is irrelevant to the invariant: the capacity arithmetic binds to the underlying Allocation's `qty` regardless of which SKU shape (Sellable SKU Intrinsic / Composite SKU / future shapes) backs the Hero Package Offer. | §13.3 | AUTO — drive Hero Package with each SKU shape (iSKU, cSKU); assert invariant binds to Allocation `qty` not to SKU type |

### §6.6 Boundary statements — Module K does NOT carry

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-XM-20** | Module K holds NO pricing, commercial-term, Offer, Hero Package designation, surface-admissibility validation (Module S owns); NO Allocation, capacity storage, sub-pool, sourcing-model attribute (Module A owns); NO **Product Master / Product Variant / Product Reference** *(wine-display aliases: Wine Master / Wine Variant / Bottle Reference)* / Sellable SKU / Composite SKU (Module 0 owns); NO settlement-execution / invoice-issuance / refund-execution surfaces (Module E owns). | §16 | AUTO — inspect Module K entity schemas + outbound API surface; assert absence of every named attribute and endpoint family. **(Naming cascade on the Module 0 entity names — §18.)** |
| **AC-K-XM-21** | Module K does NOT own SupplierProducerLink (Module D owns) or emit Club Credit lifecycle events (Module E owns). Module K is upstream party registry / event consumer only. | §16; BR-K-Producer-3, BR-K-Contract-2 | AUTO — covered by AC-K-XM-15 + AC-K-EVT-16 |
| **AC-K-XM-22** | Module K does NOT handle payment-method records or PCI boundary. Module E + Airwallex own these. Module K's Account holds only the payment-provider customer reference; raw card data is never touched. | §4.7 + §16; DEC-014 | AUTO — inspect Account entity schema; assert presence of payment-provider customer reference only; assert absence of raw card / PAN / CVV fields |
| **AC-K-XM-23** | Module K does NOT execute Club Credit conversion math (DEC-043: face-value conversion to Discovery store credit with 12-month validity at Club closure — **KEEP-lean, owned by Module S**). Module K's role ends at the upstream cancellation signal. | §10.2 + §16 | AUTO — inspect Module K processing of `ClubClosed`; assert Module K emits per-Profile signal only; assert no conversion-math execution |
| **AC-K-XM-24** | AgencyAgreement entity is **dormant** at NewCo launch (no active-consignment / third-party-agency operations per DEC-011 + DEC-017). The entity exists in the inherited model for future-flexibility; no NewCo flow exercises it; the AgencyAgreement event family is also dormant. | §16; §4.6 (precedent reference only) | AUTO — verify AgencyAgreement entity is present in schema (future flexibility) but no Module K workflow creates / activates / consumes one; verify no AgencyAgreement-family events fire in any test scenario *(carried to roadmap — PRD §17)* |
| **AC-K-XM-25** | B2B segment surfaces are OUT at launch (DEC-017 + DEC-068). Wholesale catalog, business-tier pricing, PO workflow, B2B credit terms — none active. Only the company-billing affordance for individual collectors is active on Address. Customer entity carries NO B2C/B2B discriminator. | §4.1 + §16 | AUTO — verify Customer entity schema does NOT carry B2C / B2B discriminator; verify no business-account creation path exists; verify company-billing fields exist on Address |
| **AC-K-XM-26** | Customer entity carries NO NFT / wallet-linkage state at launch. P2P secondary-market wallet linkage is post-launch (BMD §13.5). Any inherited v17 P2P-wallet field present in schema for future-flexibility is unread by any NewCo flow. *(Module K is intrinsically D12-neutral — no on-chain attribute.)* | §16; future-flexibility item (§17) | AUTO — verify no NewCo flow reads or writes the P2P-wallet field; verify no NFT-mint / wallet-link event consumer exists |

### §6.7 MVP re-baseline criteria **(NEW — v0.3-MVP)**

The naming cascade, the N2 trigger-agnostic Hold registry, the K.18 / K.19 deferred-seams, and a scope-parity confirmation. These verify the **launch-MVP-specific** properties on top of the carried-over v0.1 contract.

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-K-MVP-1** | **Naming-cascade application (Phase C item A).** The Producer-link criteria carry **Product Master** (wine-display alias *Wine Master* retained): AC-K-J-10, AC-K-XM-1, AC-K-XM-2, AC-K-BR-Producer-2, AC-K-BR-Producer-4. **Module K's own event names are unchanged** (`Producer*`, `Customer*`, `Profile*`, `Club*`, `ProducerAgreement*`, `OriginatingClubLocked`, `MembershipInvitation*`, `WaitingListJoined`, `Customer*Screening*`, `CustomerSegmentChanged`, `CustomerTransitionedToLegacy`); **Module E's consumed-event names are unchanged** (`MembershipFeePaid`, `ClubCredit*`, `CustomerCreditHold*`, `CustomerChargebackFlagged`, `StoragePayment*`). Naming/contract only — zero behaviour change. | PRD §18; Phase C item A | AUTO — inspect the event registry; assert no `Wine*`/`BottleReference*` name appears in any Module K surface; assert the Producer-link *prose* carries Product Master; assert Module K's own event names + Module E's consumed-event names are byte-for-byte the v1.1 names; assert payload semantics unchanged |
| **AC-K-MVP-2** | **N2 — trigger-agnostic Hold registry (Phase C item D / §5-N2).** Module K records Hold types + state regardless of trigger automation depth. The chargeback trigger (`CustomerChargebackFlagged`, **automated** — D21 KEPT) and the storage-payment trigger (`StoragePaymentFailed`/`...Succeeded`, **manual-first** — D4 deferred) both drive the **identical** K-side registry behaviour; the Hold types, the registry-of-record role, and the manual-placement path are unchanged for both. | PRD §4.8.1 + §15.8; Phase C §5-N2 | AUTO — drive the `CHARGEBACK_REVIEW` Hold via `CustomerChargebackFlagged` and the `STORAGE_PAYMENT_FAILED` Hold via `StoragePaymentFailed`; assert the K-side Hold-registry creation/lift behaviour is identical regardless of the upstream trigger's automation depth; assert the manual-placement path is present for both. **(Covered partly by AC-K-EVT-18 / EVT-19; this asserts the trigger-agnostic property + the N2 alignment.)** |
| **AC-K-MVP-3** | **K.18 deferred-with-seam (welcome-window proportional scaling).** At launch, fee payment is full-fee → full-credit: **no proportional scaling fires.** The issuance hook + the `policy × (fee_paid/full_fee)` formula are **retained in Module K** as the seam (AC-K-J-17 → roadmap). | PRD §11.1; cut-sheet K.18 / S Q2 | AUTO — drive `MembershipFeePaid` at full fee; assert credit = policy amount (no scaling); assert the scaling formula/hook is present-but-not-invoked at launch (the seam is verifiable; the scaled-issuance behaviour is verified when the feature restores — AC-K-J-17) |
| **AC-K-MVP-4** | **K.19 deferred-with-seam (operator manual Club-Credit issuance).** At launch, the operator manual Club-Credit-create path is **not exercised**; launch goodwill routes through the **single REFUND_COMPENSATION coupon** (Module S, S.16). The manual-create path is **retained in Module K** as the seam (AC-K-J-16a → roadmap). | PRD §11.1; cut-sheet K.19 / S Q2 | AUTO — assert no operator-manual-Club-Credit-create flow is wired into the launch goodwill path; assert goodwill routes through the REFUND_COMPENSATION coupon (Module S — verified in Module S acceptance); assert the manual-create path + invariants are present-but-not-exercised in Module K (the seam; manual-issuance behaviour verified when restored — AC-K-J-16a) |
| **AC-K-MVP-5** | **MVP scope-parity confirmation (KEEP-in-full).** The compliance floor (KYC / sanctions + order-completion gate / unified Hold + DEC-181 / GDPR erasure + 10-yr retention), the D8 club spine, the Club-Credit core (K.16 + K.17 carry-forward), and the OC 5% capture all stand and pass identically to v0.1; only the naming cascade + the K.18/K.19/gifting deferrals + the N2 alignment changed. **No launch-scope criterion was removed.** | PRD §0; cut-sheet §5; Phase C items A/D/E + floor chains 2/6 | MIXED — AI assembles the parity evidence (the carried-over floor + club-spine + Club-Credit-core + OC-capture criteria all PASS identically; a diff showing only the cascade + the K.18/K.19/gifting annotations + N2 changed); Paolo confirms the re-cut is faithful and nothing in launch scope was dropped |

---

## §7 Out of scope for this acceptance pass

The following are deliberately excluded, in line with the methodology DECs in the header:

- **Engineering Definition of Done** (DEC-073): coverage thresholds, performance budgets, error-handling exhaustion, observability, retry/idempotency mechanics, schema design (column types, FK declarations, nullability as constraints), API style + transport, deployment topology, KYC-vendor / screening-vendor wiring.
- **UI / UX acceptance**: Admin Panel form layouts (Customer detail, Producer onboarding wizard, ProducerAgreement editor), Consumer Portal layouts (registration, consent toggles, Originating-Club narrative, Club Credit balance), Producer Portal layouts (the one approve/decline write surface, the deferred invitation / waitlist-review / capacity-adjustment UX), navigation, validation copy, accessibility, responsive design, UI-chrome i18n. A separate UX track owns these.
- **Operational R&R / approval-tier policy** (`feedback_prd_rr_approval`): which named individual approves what; single-approver vs committee; tiered authority; KYC / right-to-erasure approval policy; Compliance × Legal regulatory-Hold playbook — admin-configurable; the producer-content **role-count** is verified only as separation-of-duties (AC-K-J-10), not as a policy.
- **Non-functional concerns not anchored to a BR / DEC** at PRD level: latency, throughput, alerting thresholds, infrastructure. Functional-only scope.
- **Post-launch deferrals — acceptance moves to the roadmap with the feature** ([`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md)):
  - **Net-new MVP deferrals (seams retained — PRD §17.1):** **AC-K-J-17** (welcome-window scaling = K.18), **AC-K-J-16a** (operator manual Club-Credit issuance = K.19) — *deferred-with-feature; retained in the contract, not launch-blocking; restore with the feature.* The **gifting-initiation read-API** exercise (AC-K-XM-4) is **idle at launch** (D5) — restores with the coordinated gifting set (S+K+C).
  - **v1.1 already-deferred (PRD §17.2, carried verbatim):** Q-OQ-9 24-month producer-agreement template; Q-OQ-10 persona; Q-OQ-11 death / inheritance; Supplier-operated Clubs (DEC-067); sophisticated waitlist mechanics (DEC-069); capacity-decrease constraint tightening; multi-tier Club activation (DEC-062); B2B segment reintroduction (DEC-068); active-consignment / third-party agency (AgencyAgreement dormant); Liquid sales (DEC-065); re-acceptance state machine on T&C / Privacy version updates; auto-suspension on zero active Profiles; P2P secondary-market wallet linkage (BMD §13.5); enhanced-KYC document workflow as a state machine; operator override for Originating Club; country-change automated sanctions-detection (DEC-030).
- **Cross-module behaviours owned by other modules**: Module S Offer-publication validation, Club Credit **redemption math** + conversion math (DEC-043) + the REFUND_COMPENSATION goodwill coupon, Hero Package Offer designation, gifting / Cart Hold / coupon mechanics; Module A Allocation creation / capacity storage / Hero Package `qty` storage; Module D SupplierProducerLink / ProcurementIntent; Module B SerializedBottle / NFC / NFT; Module C fulfilment / pickup / shipment-request; Module E payment / settlement / invoice / refund execution / GL treatment. Module K acceptance verifies only the events Module K emits and consumes at the boundary; downstream behaviour is verified in the receiving module's acceptance. The §6.2 sanctions/Hold uniformity criteria (AC-K-XM-3..AC-K-XM-12) verify Module K's read-side API contract + the cross-surface uniformity property; the *commercial action* of each downstream surface is verified in the receiving module's acceptance (the order-completion enforcement point is Module S, S.15).
- **PRD ambiguities (AMB-K-1..7)** — collected in `greenfield/03-qa/qa.acceptance_ambiguities.md`; deferred to a future editorial pass; not addressed at this stage.

---

## §8 Sign-off log

### §8.1 Format-validation milestones (template-level)

| Milestone | Date | Notes |
|---|---|---|
| v0.1 PAOLO-VALIDATED (template) | 2026-05-15 | 125 criteria (115 AUTO / 9 MIXED / 1 HUMAN); four format conventions locked; 5 Stage-6.5 gap-fill ACs landed (J-7a / EVT-12a / J-9a / J-16a / FSM-2a); 3 MIXED→AUTO retags. |
| **v0.3-MVP re-cut (Phase D)** | **2026-06-07** | **DRAFT — awaiting batch ratification.** Re-cut from the PAOLO-VALIDATED v0.1 per cut-sheet §5: naming cascade applied to the Producer-link criteria (AC-K-J-10 / XM-1 / XM-2 / BR-Producer-2 / BR-Producer-4); re-anchored to the v0.3-MVP PRD (body §-anchors unchanged — KEEP-in-full, no structural insertion); **AC-K-J-17 (K.18) + AC-K-J-16a (K.19) annotated deferred-with-feature → roadmap** (seams retained in K; decided in S Q2); **AC-K-XM-4 (gifting) annotated not-exercised-at-launch** (D5); **§6.7 added** (5 MVP re-baseline criteria — naming cascade / N2 / K.18+K.19 seams / scope-parity). **~130 total (119 AUTO / 10 MIXED / 1 HUMAN).** No launch-scope criterion removed (KEEP-in-full). Floor criteria (KYC/sanctions/Hold/anonymisation) re-affirmed unchanged. |

### §8.2 Per-AC delivery sign-off

Populated at first delivery review. Each criterion's state (OPEN / DEMOED / ACCEPTED) + Paolo's signature + date land here.

| AC ID | State | Date | Paolo signature | Notes / evidence reference |
|---|---|---|---|---|
| AC-K-J-1 | OPEN | — | — | — |
| ... | ... | ... | ... | ... |

(Full table populated at delivery; placeholder rows omitted in this draft.)

---

## §9 Cross-references

- **Spec source (this validates against)** — [`../02-prd/Module_K_PRD_v0.3-MVP.md`](../02-prd/Module_K_PRD_v0.3-MVP.md).
- **Predecessor (re-cut from)** — [`../../reference/v1.1/01-prd/Module_K_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_K_Acceptance_v0.1.md) (PAOLO-VALIDATED 2026-05-15; frozen, 125 criteria).
- **Cut-sheet (the delta spec)** — [`../01-triage/Module_K_CutSheet_v0.1.md`](../01-triage/Module_K_CutSheet_v0.1.md) §5 (acceptance delta), §6 (the six ratified Qs).
- **Module S cut-sheet (the Club-Credit decision locus)** — [`../01-triage/Module_S_CutSheet_v0.1.md`](../01-triage/Module_S_CutSheet_v0.1.md) §3.2 (K.17 KEEP; K.18/K.19 DEFER; REFUND_COMPENSATION goodwill path; DEC-043 KEEP-lean).
- **Phase C reconciliation** — [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) item A (naming cascade), item D (Club-Credit three-way seam), item E (OC capture), item L (one producer write), §5-N2 (trigger-agnostic Hold registry), §6 floor chains 2 + 6.
- **Naming source of truth** — [`../02-prd/Module_0_PRD_v0.3-MVP.md`](../02-prd/Module_0_PRD_v0.3-MVP.md) §18.
- **Roadmap (deferred-feature acceptance moves here)** — [`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md).
- **Template precedent** — [`Module_0_Acceptance_v0.3-MVP.md`](Module_0_Acceptance_v0.3-MVP.md) (the first Phase-D acceptance re-cut; format mirrored here).
- **Sibling v0.3-MVP acceptance docs** (written alongside their PRDs) — Module A, D, S, B, C, E, + the Admin-Panel PRD's acceptance.

---

*End of Module K Acceptance Criteria v0.3-MVP — Phase D re-baseline. Re-cut from the PAOLO-VALIDATED v0.1: naming cascade applied to the Producer-link criteria, re-anchored (body §-anchors unchanged — KEEP-in-full), AC-K-J-17 (K.18) + AC-K-J-16a (K.19) annotated deferred-with-feature → roadmap, AC-K-XM-4 (gifting) annotated not-exercised-at-launch, §6.7 MVP re-baseline criteria added. **Floor criteria unchanged; nothing in launch scope removed.** ~130 criteria (119 AUTO / 10 MIXED / 1 HUMAN). **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E.*
