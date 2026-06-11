# Module K (Parties / Customers / Memberships / Producers) — MVP Cut-Sheet v0.1

- **Version**: v0.1 (**RATIFIED by Paolo 2026-06-07**). Second cut-sheet; follows the Module 0 template format.
- **Date**: 2026-06-07
- **Status**: RATIFIED — Phase B triage complete for Module K (Q1–Q6 resolved §6)
- **Owner**: Paolo
- **Inputs**: [`../../reference/v1.1/01-prd/Module_K_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_K_PRD_v0.2.md) (source spec) · [`../../reference/v1.1/01-prd/Module_K_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_K_Acceptance_v0.1.md) (PAOLO-VALIDATED 2026-05-15; 125 criteria) · [`../../reference/v1.1/01-prd/Module_K_Packet_v0.1.md`](../../reference/v1.1/01-prd/Module_K_Packet_v0.1.md) (gap/ambiguity packet) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (locked dials — §5 Decisions log) · [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (method + P1/P2) · [`Module_0_CutSheet_v0.1.md`](Module_0_CutSheet_v0.1.md) (template + naming cascade).
- **Triage tags**: KEEP · SIMPLIFY · DEFER · DROP · **GENERALISE** (naming-only cascade from the Module 0 generalisation).

---

## §1 Verdict & counts

**Module K is KEPT on its full compliance floor, its core-identity spine, and the D8 club/membership model (per the locked dial).** Two facts converge to make this — like Module 0 — a near-full KEEP:

1. **Module K houses essentially the entire scope floor.** KYC, sanctions screening, the unified Hold gate, and GDPR right-to-erasure (soft-delete + anonymisation) all live here. The MVP plan §3 names all four as un-cuttable. None can be deferred or simplified.
2. **D8 (club / membership model) is locked KEEP-FULL as a core value proposition** — multi-profile, Club Credit, Originating Club, Hero Package capacity. The locked dial says *no upfront cut; hunt for safe simplifications in the K/S detail.* The savings-hunt below finds a **small** set of safe trims — and confirms that the heaviest club machinery (multi-profile identity; Club Credit redemption + closure-conversion; Hero/stacking) is either load-bearing or physically resident **downstream in Module S/E**, so the Module-K-side cut is thin by construction.

Ratification (2026-06-07) confirmed the savings are narrow — narrower still than first drafted: (a) **launch marketing consent is KEPT** — NewCo runs campaigns at launch (Q1), so the double-opt-in lifecycle ships; (b) the **Club-Credit peripheral mechanics stay KEPT at the Module-K layer** — they are cheap here, and the substantive Club-Credit simplification call is **forwarded to the Module S cut-sheet** where the heavy redemption/financial logic lives (Q2); (c) the **L-PP producer-write deferrals** are real but the PRD already books the Producer-Portal write UIs as post-launch (§16), so no backend capability is cut (Q4). **Net Module-K-layer deferrals beyond what v1.1 already defers are ~0 — Module K, like Module 0, is KEEP-in-full + generalise. The substantive D8 cut lands downstream in Module S.**

| Tag | Count | Notes |
|---|---:|---|
| KEEP | ~all | The compliance floor (KYC, sanctions, Hold gate, GDPR erasure); core identity (Customer, Profile, Producer, Supplier, Account); the D8 club spine (Club, Club Credit, Originating Club capture, Hero Package capacity invariant **incl. mid-year mutability — Q5**); marketing-consent double-opt-in (**Q1 — campaigns at launch**); the ~40-event cross-module contract; module boundaries. |
| GENERALISE | 1 cascade | Naming-only: `Wine Master → Product Master` references (§4.4, §14.5, §15.4, §16) and the `Wine* → Product*` event-name family. Zero behaviour change. |
| SIMPLIFY | 0 (Module-K layer) | Club-Credit peripheral mechanics stay KEPT in K; the substantive Club-Credit simplification is **forwarded to the Module S cut-sheet** (Q2). ProducerAgreement KEPT-minimal (Q6); Hero-Package mid-year mutability KEPT (Q5). |
| DEFER (net-new) | 0 (Module-K layer) | Producer-facing **write UIs** are operator-driven via Admin Panel (L-PP / Q4) but the PRD already books these as post-launch (§16) — no backend capability cut. The §17 already-deferred set carries to roadmap unchanged. |
| DROP | 0 | — |

**Calibration takeaway (confirms Module 0's prediction).** A Lean MVP does **not** gut a foundational module, and Module K carries the compliance floor on top — so it stays whole. The real cutting still starts at **A/D** and intensifies through **S/B/C/E**. The most consequential D8 savings-hunt *finding* is a negative one: **multi-profile is load-bearing, not a safe cut** (see §3.1) — the safe club trims are the peripheral Club-Credit mechanics, and those mostly live in Module S.

---

## §2 Feature inventory & triage

Grouped by area. For every SIMPLIFY/DEFER the forward-compat seam (P1) is named. "FLOOR" marks an MVP-plan §3 un-cuttable.

### §2.1 Customer identity & compliance (the floor)

| # | Capability (PRD §) | v1.1 behaviour | Tag | Rationale / forward-compat seam (P1) | Cross-module deps |
|---|---|---|---|---|---|
| K.1 | Customer entity — identity, `pending→active→suspended→closed`, T&C/privacy gate, preferred currency + locale (§4.1) | Natural-person registry; B2B dropped (DEC-068), company-billing affordance at Address | **KEEP** | Core-loop identity (*customer register/KYC*). D1 keeps 5 currencies, D2 keeps 6 locales — no narrowing. Already lean (B2B stripped in v1.1). | Read by S/A/E + all surfaces. |
| K.2 | KYC lifecycle — `not_required/pending/verified/rejected` + `kyc_required` flag + auto-`kyc`-Hold (§9.1) | Four-state on Customer, separate from sanctions (DEC-071) | **KEEP — FLOOR** | KYC is named un-cuttable (plan §3). No change. | Module S order-completion gate. |
| K.3 | Enhanced-KYC trigger — €10k single / €50k cumulative-annual (§4.1, §9.1; DEC-035) | Flag + timestamp; periodic job + at-order-completion detection | **KEEP — FLOOR** | AML floor. Detection both paths retained. | Module S (order-completion), Module E (totals). |
| K.4 | Sanctions screening — `pending/passed/failed/under_review`, 12-mo re-screen, between-cycle AML + ad-hoc triggers (§9.2) | EU+UIF+OFAC (DEC-030/041); separate lifecycle (DEC-071); country-change auto-detection already deferred | **KEEP — FLOOR** | Sanctions screening + OFAC are floor (plan §3; D3 retains OFAC even in the hybrid-geography cut). No change. Country-change detection stays deferred (carry to roadmap). | Module S gate; screening-vendor adapter. |
| K.5 | Screening as business gate **at order completion** (§9.3; DEC-071) | Customer can exist `sanctions=pending`; gate fires at order completion, not at creation | **KEEP — FLOOR** | The single enforcement point the floor depends on. | Module S enforces; DEC-181 surfaces. |
| K.6 | Soft-delete + anonymisation — GDPR erasure; regulatory-Hold precedence (§8.2; DEC-027) | PII overwrite, 10-yr txn-history retention, voucher validity preserved, HubSpot PII purge; sanctions/`compliance` Holds block, others proceed | **KEEP — FLOOR** | GDPR right-to-erasure is floor (plan §3 audit/retention). No change. | Module E (retention), HubSpot (PII purge). |
| K.7 | GDPR data-subject-rights surface — erasure / access / object / consent / minimisation (§12) | System of record for PII + consent | **KEEP — FLOOR** | Compliance floor. Mostly operational. | Module E (retention window). |
| K.8 | Marketing-consent lifecycle — `none→requested→confirmed→revoked` double-opt-in (§8.1; DEC-026) | Activated at launch (v17 deferred); HubSpot reads consent to send campaigns; transactional email independent (T&C-governed) | **KEEP (Q1 — ratified)** | NewCo runs outbound campaigns at launch, so the double-opt-in marketing-consent lifecycle ships (alongside the always-kept T&C-governed transactional path). Confirm single-vs-double opt-in with legal at build time. | HubSpot (marketing delivery + segments). |

### §2.2 Membership & club model (D8 — KEEP FULL, savings-hunt applied)

| # | Capability (PRD §) | v1.1 behaviour | Tag | Rationale / forward-compat seam (P1) | Cross-module deps |
|---|---|---|---|---|---|
| K.9 | Netflix-style **multi-profile** model — 1 Customer ↔ N Profiles, one per Club (§3, §4.2) | Profile *is* the membership; per-Club state on Profile, identity/consent/screening on Customer | **KEEP (D8 core)** | **Savings-hunt verdict: load-bearing, NOT a safe cut.** Target collectors are commonly members of 3–5 clubs at launch (§3); single-profile would break that core use case *and* force a later rebuild (violates P1). Multi-profile stays. | Module S (eligibility), Module A (capacity). |
| K.10 | Profile state machine — 11 transitions incl. WaitingList, Lapsed/30-day-grace, Suspended, Cancelled, Inactive (§4.2.1; DEC-034) | Generic across all Clubs; no Crurated-Member tier flow | **KEEP** | Generic FSM is the membership backbone. `Inactive` is a rare corner case but inherited and cheap — leave intact (DROP would save ~nothing and risk a downstream read). 30-day grace is cheap. | HubSpot, Module S. |
| K.11 | Club entity — `active→sunset→closed`, fee/credit/renewal policy, single-tier (§4.3; DEC-062) | Producer-operated; multi-club-per-producer; Crurated-Member club type dropped | **KEEP** | D8 core. Single-tier launch already locked (multi-tier = post-launch config, no schema change). | Module A, Module S. |
| K.12 | Customer segments — Member / Waiting-list / Legacy materialised view (§5) | Strongest-segment-wins; daily reconciliation; `CustomerSegmentChanged` / `CustomerTransitionedToLegacy` | **KEEP** | *Member* is load-bearing for club eligibility. Legacy/Waiting-list + their HubSpot marketing-segment sync are cheap and ride on the same view. (Materialised-vs-on-read is a build-team call per DEC-073 — not a spec cut.) If marketing is deferred (Q1) the segment→marketing consumers simply idle; the view still serves eligibility. | HubSpot (marketing segments). |
| K.13 | **Originating Club** mechanic + one-shot immutable lock event (§6; DEC-008/040/066) | First Club to approve a Customer; drives 5% Discovery share (DEC-032); no-OC allowance | **KEEP (capture) — seam-critical** | The **5% share computation** is settlement (D19 — deferred to operator-run). But the *lock must be captured at launch*: it is one-shot at first approval and unreconstructable later. **Seam:** ship the OC link + `OriginatingClubLocked` event now (the data); the 5% accrual is computed by Module S/E when settlement is built. Cutting the capture would burn the bridge (P1). | Module S/E (5% share, deferred). |
| K.14 | **Hero Package capacity invariant** — active Profiles ≤ Hero Package allocation `qty` (§13.1–13.3; DEC-007) | Enforced at every approval; cap stored on Module A allocation, not on Club; binds regardless of SKU shape | **KEEP (D8 core)** | A membership no-oversell guard — the eligibility analogue of the inventory floor. Load-bearing for the club purchase loop. | **Module A owns the `qty` storage** — must survive A triage (see §4). |
| K.15 | Hero Package **mid-year capacity mutability** + producer-discretionary waitlist conversion (§13.4–13.5; DEC-069) | Producers may increase/decrease capacity mid-year (decrease ≥ active count); discretionary waitlist approval; FIFO/ranking already deferred | **KEEP (Q5 — ratified)** | Lets a sold-out Club add capacity — commercially useful, and cheap (adjust A's `qty`, K consumes). Sophisticated waitlist mechanics already deferred in v1.1. | Module A (qty adjustment). |
| K.16 | **Club Credit** entity + one-active-per-Profile invariant + auto-issuance on `MembershipFeePaid` (§11, §11.1) | Per-Profile prepayment from membership fee; Module E emits the financial events, K records state | **KEEP entity (D8 core)** | Club Credit *is* how the Hero Package fee converts to spendable value — core club VP (BMD: fee → Club Credit → redeem). The entity + auto-issuance + one-active invariant stay. Peripheral mechanics → K.17–K.19. | Module E (emits `ClubCredit*`), Module S (redemption). |
| K.17 | Club Credit — **partial redemption + carry-forward balance** (§11) | Credit exceeding package value leaves a remaining balance that carries forward until forfeiture | **KEEP in K; decide in S (Q2 — ratified)** | On review, likely load-bearing if members spend annual credit across several purchases; the K-side saving is small and the customer-value risk (lost leftover credit) real. Kept as specified in K; the substantive call is taken in the **Module S** cut-sheet where redemption logic lives. **Seam:** `remaining_balance` retained. | Module S (redemption math). |
| K.18 | Club Credit — **welcome-window proportional scaling** (§11.1) | If fee paid < full fee, credit scales = policy × (fee_paid/full_fee) | **KEEP in K; decide in S (Q2 — ratified)** | Marginal K-side saving; kept as specified. Revisit in the **Module S** Club-Credit pass. **Seam:** issuance hook + formula retained. | Module E (fee event), Module S. |
| K.19 | Club Credit — **operator manual issuance** (goodwill/make-right) (§11.1) | Operator manually creates a credit on a Profile, subject to one-active + currency invariants | **KEEP in K; decide in S (Q2 — ratified)** | Partly redundant with the voucher goodwill instrument (§4.7); kept as specified in K, with the redundancy resolved in the **Module S** pass (goodwill-via-voucher vs manual credit). **Seam:** manual-create path retained. | Module S/B (vouchers). |
| K.20 | Producer **offboarding cascade** — `ProducerRetired→ClubSunset→per-Profile→`Club-Credit-conversion signal (§10.2) | Cascade ordering + per-Profile cancellation signal; vouchers preserved | **KEEP (lean)** | Rare at launch (no producer offboards in month 1) but it is mostly state + event emission, already lean. Manual-first would save little and risk an inconsistent cascade. Keep. | Module S (credit conversion DEC-043), Module E. |

### §2.3 Producer / Supplier / Agreement

| # | Capability (PRD §) | v1.1 behaviour | Tag | Rationale / forward-compat seam (P1) | Cross-module deps |
|---|---|---|---|---|---|
| K.21 | Producer entity — `draft→active→retired`, KYC gate, customer-facing description (6 locales) (§4.4) | Winery identity; source for **Wine Master** link in PIM; standalone (not a Party subtype) | **KEEP + GENERALISE** | Core loop (*producer onboard*). Naming cascade: "Wine Master" → **"Product Master"** (wine alias retained). D2 keeps the 6-locale description. Behaviour identical. | **Module 0** (Product Master link). |
| K.22 | Producer 3-step content approval — Creator → Reviewer → Approver (§4.4) | Three distinct actors, analogous to PIM lifecycle | **KEEP (Q3 — ratified)** | Data-quality gate on the upstream Producer. **Role-count is admin-configurable** — the small launch onboarding team may run a lighter 2-step approval by configuration, **no spec change** (same decision as Module 0 Q2). | — |
| K.23 | Supplier entity — Party Registry subtype; dormant Third-Party-Owner subtype (§4.5) | Commercial counterpart distinct from Producer; SupplierProducerLink owned by Module D | **KEEP (minimal)** | Needed now — Discovery already runs with non-Producer Suppliers (e.g. Crurated as Discovery supplier, DEC-020). Rich Supplier state is Module D. Third-Party-Owner stays dormant (active consignment deferred, D11). | Module D (link, agreement). |
| K.24 | **ProducerAgreement** — `draft→active→superseded/terminated`, term dates, settlement cadence, min-commitment, single-active-per-scope (§4.6; DEC-070) | NewCo net-new; rich terms already deferred (Q-OQ-9 24-mo template); holds settlement cadence Module E reads | **KEEP-minimal (Q6 — ratified)** | The entity is the **seam for D19**: settlement is operator-run first cycles, but the cadence + term must be recorded somewhere stable. Already minimal (placeholder fields pending Q-OQ-9). Full lifecycle (supersession-chain, single-active-per-scope) retained — it is cheap and it *is* the settlement seam. | Module E (cadence — D19 deferred), Module D (gates). |
| K.25 | Account — `active→suspended→closed`, payment-provider customer ref (Airwallex), B2B credit-terms dormant (§4.7) | Transactional container; one Customer = one Account; goodwill = vouchers (no Account Credit) | **KEEP** | Needed for payments (D4 keeps card + SEPA). Already lean — business-account + B2B credit terms dormant (DEC-068). Lazy payment-provider provisioning. | Module E (payment execution). |

### §2.4 Holds, onboarding, events, boundaries

| # | Capability (PRD §) | v1.1 behaviour | Tag | Rationale / forward-compat seam (P1) | Cross-module deps |
|---|---|---|---|---|---|
| K.26 | **Unified Hold** — 6 types (`admin/kyc/payment/fraud/compliance/credit`) × 3 scopes (Customer/Account/Profile), cascade + isolation, audit (§4.8) | The unified blocking mechanism gating all commercial activity | **KEEP — FLOOR** | The "unified hold gate" is named un-cuttable (plan §3). No change. | Every transaction-initiation surface. |
| K.27 | Sanctions/Hold **uniformity principle** (DEC-181) + per-type Hold-lift discipline (DEC-160) (§4.8) | Every transaction-initiation surface reads sanctions + Hold at moment of action; auto-lift only `kyc`/`payment` | **KEEP — FLOOR** | Compliance-uniformity invariant. Generic read-API means deferred surfaces (e.g. gifting, D5) simply aren't exercised — no cut needed. | S/C/E surfaces. |
| K.28 | Chargeback Hold + storage-payment Hold (consumed events) (§15.8; DEC-168/160) | `CustomerChargebackFlagged`→`CHARGEBACK_REVIEW` Hold; `StoragePaymentFailed/Succeeded`→ per-cycle Hold | **KEEP (registry); trigger manual-first** | D21 simplifies chargebacks → manual; D4 defers saved-card auto-escalation → manual dunning. **Module K side is unchanged** — the Hold types + registry stay; whether the trigger is an automated Airwallex webhook or a manual operator action is the **Module E** deferral. **Seam:** Hold registry + manual-placement path. | Module E (auto-ingestion deferred — D21/D4). |
| K.29 | Onboarding — direct registration (§7.1) | Email-verify + T&C + synchronous sanctions screen → `active` | **KEEP** | Core loop (*customer register*). | Module S. |
| K.30 | Onboarding — club-link registration (§7.2) | Pre-bound Club; Profile auto-binds in `Applied` | **KEEP** | Cheap variant that streamlines first-Club application. | — |
| K.31 | Onboarding — producer-initiated **invitation** (§7.3) | Producer enters invitee email via Producer Portal; `MembershipInvitationSent/Accepted`; HubSpot delivers | **KEEP capability; producer UI operator-driven (L-PP / P2)** | The invitation *capability* (record + HubSpot delivery) stays. The producer-facing invite **UI is a producer write** → operator-driven via Admin Panel at launch (L-PP). PRD §16 already books the Producer-Portal invitation surface as post-launch. **Seam:** producer invite UI built later on the same backend. | Admin Panel; Producer Portal (deferred UI); HubSpot. |
| K.32 | Domain events — ~30 emitted + ~10 consumed; parent-before-child cascade ordering; schema-versioned (§15) | The cross-module contract incl. 6 NewCo-net-new events | **KEEP + GENERALISE** | The backbone every downstream module reads. Naming cascade: `Wine*`-referencing prose → `Product*`; event payload semantics identical. AgencyAgreement event family stays **dormant** (active consignment deferred, D11). | All modules + HubSpot. |
| K.33 | Module boundary notes — what K does NOT do (§16) | Pricing/Offers (S), Allocation/capacity (A), PIM (0), SupplierProducerLink (D), settlement/invoice (E), NFT/wallet (B), HubSpot delivery | **KEEP** | These deliberate silences keep K neutral to the downstream cuts (NFT-decoupling D12, settlement-defer D19, etc.). Leave intact. | A/S/B/C/D/E. |
| K.34 | Already-deferred / future-flexibility set (§17) — Q-OQ-9/10/11; Supplier-operated Clubs; multi-tier; B2B reintro; active consignment; liquid sales; P2P wallet; enhanced-KYC doc workflow; OC override; auto-suspend; re-accept FSM | 15 future-flex hooks, all with clean re-introduction seams | **DEFER (unchanged)** | All already deferred in v1.1 with documented hooks. **Do not re-cut** — carry verbatim into `04-roadmap/`. | — |

---

## §3 Module-specific changes

Three threads carry the Module K MVP beyond a plain KEEP: the D8 savings-hunt findings, the L-PP producer-write treatment, and the naming cascade.

### §3.1 D8 club-model savings-hunt (the kickoff §9 mandate)

The locked dial keeps the club model full but directs an active hunt for safe simplifications in the K/S detail. Findings, Module-K-side:

1. **Multi-profile — examined, KEEP (not a safe cut).** The dials worksheet floated "consider single-profile." Verdict: **unsafe.** Multi-club collectors (3–5 clubs) are a common launch persona (§3); single-profile breaks the use case and forces a rebuild (P1). The Netflix-style model stays.
2. **Club Credit — KEPT in full at the Module-K layer; the substantive cut is forwarded to Module S (Q2 — ratified).** Auto-issuance on fee payment, the one-active-per-Profile invariant, and issuing-Club-scoped redemption are load-bearing (the club VP). The peripheral mechanics (partial-redemption/carry-forward K.17, welcome-window scaling K.18, manual issuance K.19) were examined and found to give only a *marginal* K-side saving — with real customer-value risk on carry-forward — so they stay KEPT in K. *The heavy redemption + closure-conversion logic is physically in Module S/E; the deep Club-Credit decision is taken in the S/E sheets, reconciled to K's seam.*
3. **Hero Package mid-year mutability (K.15)** — optional freeze-at-year-start simplification (Q5); low value, recommend KEEP.
4. **Stacking algebra (7-step, DEC-110)** — **not in Module K.** It is Module S (Hero/stacking/Club-Credit price resolution). Flagged here, hunted in the **S** sheet.

Net: the Module-K club cut is **thin and peripheral**; the substantive D8 savings-hunt lands in Module S.

### §3.2 L-PP producer-write treatment (P2 instance)

At launch the producer portal is full-read + full-reporting + view-only, with **one** producer write retained: **membership approve/decline.** Module K's producer-facing writes and their launch treatment:

| Producer write (Module K surface) | Launch treatment | Seam |
|---|---|---|
| Membership **approve/decline** (incl. waitlist approval) (§4.2.1, §13.5) | **Retained as the one producer write (L-PP, Q4 — ratified):** a minimal producer approve/decline surface ships; everything else operator-driven. (The richer "waitlist review" UX from PRD §16 stays post-launch.) | Backend approval logic ships; minimal producer approve/decline surface at launch. |
| Producer-initiated **invitation** (§7.3) | **Operator-driven via Admin Panel** (producer invite UI deferred) | Same backend; producer UI post-launch. |
| Hero Package **designation** / **capacity adjustment** (§13) | **Operator-driven via Admin Panel** (already post-launch Producer-Portal UX per §16) | Same backend; producer UI post-launch. |
| ProducerAgreement drafting (§4.6) | **Operator action by definition** (back-office; not a producer surface) | n/a. |

This is almost entirely **already** how the PRD is written (§16 puts the Producer-Portal write UIs in post-launch scope) — L-PP formalizes it. **No backend capability is cut**; only producer-facing write UIs are deferred, and admin-parity (DEC-083) covers the operator path.

### §3.3 Naming cascade (generalisation — naming only, no behaviour change)

Per the Module 0 cut-sheet §4, land Module 0 v0.3-MVP names as source-of-truth, then cascade into the Module K MVP PRD. Module K touchpoints:

- `Wine Master → Product Master` in: §4.4 Producer ("identity source for Wine Master"); BR-K-Producer-2 / BR-K-Producer-4 (§14.5); `ProducerActivated` consumer note (§15.4); §16 boundary list.
- `Wine* → Product*` event-name family wherever Module K prose references PIM events.
- "Wine Master" retained as a **wine-display alias**; payload semantics identical. This is contract/naming only — zero consumer behaviour change.

---

## §4 Cross-module ripple (for Phase C reconciliation)

Module K is **upstream of every commerce-facing module**, so its KEEPs and seams must be checked against the downstream cuts. No Module K KEEP is orphaned by a deferred upstream (K's only upstream read is Module A's Hero Package `qty`, and A = KEEP). The reconciliation flags:

1. **Hero Package capacity invariant ↔ Module A (must-keep).** K *enforces* the invariant against Module A's allocation `qty` (K does not store the cap). **The Hero Package allocation primitive must survive the Module A triage.** Verify in the A sheet (A is KEEP, but confirm the Hero-Package-backing allocation isn't simplified away). No orphan expected.
2. **Originating Club capture ↔ Module S/E (seam-critical).** The **5% OC Discovery share** computation is deferred with settlement automation (D19). The **OC lock/capture ships at launch** so the data exists when settlement is built (it is one-shot + unreconstructable). Verify the **S** and **E** sheets treat the 5% as *deferred-but-seam-preserved*, reading K's OC state, not re-deriving it.
3. **Club Credit ↔ Module S/E (joint reconciliation).** K records Club-Credit state; **Module E** emits the `ClubCredit*` financial events; **Module S** owns redemption + the closure-conversion math (DEC-043). Any K-side mechanic simplification (K.17–K.19) must be reconciled so the **entity (K) ↔ events (E) ↔ redemption logic (S)** stay consistent. **Flag for joint K/S/E review** — the substantive Club-Credit cut is taken in S/E.
4. **Marketing consent ↔ HubSpot (Q1 dependency).** If the double-opt-in lifecycle is deferred (K.8), HubSpot's marketing-segment sync + the `CustomerSegmentChanged`/`CustomerTransitionedToLegacy` marketing consumers simply idle at launch — consistent, since launch marketing is what's being deferred. The **transactional** email path (T&C-governed) is unaffected. Verify no downstream assumes launch marketing campaigns.
5. **Producer writes / L-PP ↔ Admin Panel + Producer Portal.** Invitation, Hero-Package designation, capacity adjustment, ProducerAgreement drafting are operator-driven via Admin Panel at launch; producer write UIs deferred (L-PP) **except membership approve/decline** (the one retained producer write). **Open tension (Q4):** PRD §16 defers "waitlist review and approval flow" to post-launch — reconcile whether approve/decline is producer-facing or operator-driven at launch when the **Admin Panel** and **Producer Portal** are triaged.
6. **Chargeback / storage-payment Holds ↔ Module E (D21/D4).** K's Hold types + registry are unchanged; the **trigger** (Airwallex auto-ingestion vs manual operator placement) is Module E's deferral. Verify the **E** sheet books auto-ingestion as deferred with manual placement as the launch path feeding K's Hold registry.
7. **Gifting-initiation surface ↔ Module S (D5).** Gifting deferred. K's read-API at gifting initiation (AC-K-XM-4) is generic and simply not exercised at launch — no orphan, seam intact (the voucher ownership-transfer seam is a Module S concern per D5).
8. **Naming cascade ↔ all modules + Architecture.** `Wine Master → Product Master` in K's Producer references must land consistently with the Module 0 source-of-truth and every sibling PRD — the reason the re-baseline is coherent (no piecemeal handoff).

---

## §5 Acceptance-criteria delta

Module K's acceptance doc is **PAOLO-VALIDATED** (2026-05-15; 125 criteria, 92% AUTO). With ratification landing Module K as **KEEP-in-full + generalise** (no Module-K-layer scope cut), the acceptance delta is **very light — essentially naming-only**, like Module 0. The MVP acceptance doc needs:

- **Naming cascade applied** to the Producer-link criteria: AC-K-XM-1, AC-K-XM-2, AC-K-BR-Producer-2, AC-K-BR-Producer-4, AC-K-J-10 (`Wine Master → Product Master`).
- **No Module-K criteria deferred or deleted** — Module K scope is unchanged. The only feature-linked notes:
  - *Marketing (Q1 — KEPT):* no change — AC-K-J-6, AC-K-FSM-15 stand as-is (campaigns ship at launch).
  - *Club Credit (Q2 — KEPT in K, decided in S):* no Module-K acceptance change — AC-K-J-16a, AC-K-J-17, AC-K-J-18 stand as specified; any deferral lands via the Module S cut-sheet and is reconciled back to K's entity criteria there.
  - *D5 gifting:* AC-K-XM-4 (gifting-initiation read-API) marked *not-exercised-at-launch* (criterion retained; generic read-API unchanged).
  - *L-PP (Q4):* no acceptance change — the criteria verify Module-K-side backend behaviour, which is unchanged; producer-UI is out of acceptance scope already (§7 of the AC doc).
- **Floor criteria re-affirmed UNCHANGED**: all KYC, sanctions, enhanced-KYC, Hold, sanctions/Hold-uniformity, and anonymisation criteria (AC-K-J-7/7a/8/9/9a, FSM-3/4/5/10/11, XM-3..12) stand as-is.
- **No criteria removed** (nothing is DROPPED). Deferred criteria are carried to `04-roadmap/` with their feature.

The Stage-6.5 gap-fill ACs that landed at validation (J-7a, EVT-12a, J-9a, J-16a, FSM-2a) all sit on floor or core mechanics and are **retained** — except J-16a (manual Club-Credit issuance) which follows Q2.

---

## §6 Open questions for Paolo (ratification)

Prioritised — the first three are the substantive scope calls; Q4–Q6 are lighter.

- **Q1 — Launch marketing consent (the one genuine scope cut).** Does NewCo run **outbound marketing campaigns** at launch? **If no → DEFER** the double-opt-in marketing-consent lifecycle (K.8): keep the consent field as the seam, ship only the T&C-governed *transactional* email path (order confirmations, invoices, reminders — unaffected). **If yes → KEEP**, and confirm single vs double opt-in with legal. *Recommendation: defer unless a launch campaign calendar exists — it is the cleanest non-floor saving in K.*
- **Q2 — Club Credit peripheral mechanics (D8 savings-hunt).** At launch, **(a)** full-redemption-only — defer partial-redemption/carry-forward (K.17)? **(b)** all-or-nothing welcome windows — defer proportional scaling (K.18)? **(c)** route goodwill through **vouchers** (per §4.7) and defer operator manual Club-Credit issuance (K.19)? *Recommendation: yes to all three — each has a clean additive seam, and the heavy redemption logic is in Module S where the deep cut is taken. Keep the Club-Credit entity, auto-issuance, and one-active invariant (core club VP).* These reconcile with the Module S sheet.
- **Q3 — Producer approval role-count** (mirrors Module 0 Q2). The 3-step Creator → Reviewer → Approver Producer-content workflow needs ≥3 distinct onboarding staff. Keep 3-step, or configure a lighter (e.g. 2-step) approval for the small launch team? *Operational config — no spec change either way.*
- **Q4 — L-PP producer-write reconciliation.** Confirm: at launch the **only** producer-facing write is **membership approve/decline**, and producer-initiated **invitation** (§7.3) is operator-driven via Admin Panel (producer invite UI deferred). And resolve the tension: PRD §16 books "waitlist review and approval flow" as post-launch Producer-Portal UX — so is **approve/decline producer-facing or operator-driven** at launch? *Recommendation: retain a minimal producer approve/decline surface (the locked L-PP write); operator-drive everything else. Final reconciliation in the Admin-Panel / Producer-Portal triage.*
- **Q5 — Hero Package mid-year capacity mutability (K.15).** Keep (lets a sold-out Club add capacity; cheap) or freeze capacity at year-start for launch simplicity? *Recommendation: KEEP — low cost, real commercial value; sophisticated waitlist mechanics already deferred.*
- **Q6 — ProducerAgreement lifecycle (K.24).** Keep the full entity lifecycle (supersession chain + single-active-per-scope), or run agreements as lightweight operator-tracked records for the first cycle (settlement is operator-run per D19; few producers at launch)? *Recommendation: KEEP-minimal — the entity is cheap and it is the settlement-cadence seam; don't over-cut the bridge.*

---

*End of Module K Cut-Sheet v0.1 — **RATIFIED by Paolo 2026-06-07** (Q1 marketing KEEP · Q2 Club Credit KEPT in K, decided in S · Q3 lighter approval OK · Q4 one producer write = approve/decline · Q5 mid-year mutability KEEP · Q6 ProducerAgreement KEEP-minimal). Verdict: KEEP-in-full on the compliance floor + core identity + the D8 club model; **net Module-K-layer deferrals ~0**; naming cascade applied; the substantive D8 cut lands downstream in Module S. Real cutting begins at A/D.*
