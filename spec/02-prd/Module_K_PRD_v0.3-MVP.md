# NewCo ERP — Module K PRD (Parties, Customers, Memberships, Producers) — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP scope of Module K)
- **Date**: 2026-06-07
- **Status**: **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; **nothing is promoted to `handoff/` until Phase E** (the single coherent handoff). Per the Phase D cadence, this artefact does not block on Module 0's ratification — the naming cascade contract it consumes was ratified at Phase C (item A) and the source-of-truth names are stable in [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18.
- **Owner**: Paolo (decides). Claude recommends.
- **Testable companion**: [`../03-acceptance/Module_K_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_K_Acceptance_v0.3-MVP.md) — the MVP-scoped acceptance criteria (re-cut from the PAOLO-VALIDATED v0.1 per the cut-sheet §5 delta).
- **Predecessors / inputs** (the canonical record governs where this PRD is terse):
  - [`../../reference/v1.1/01-prd/Module_K_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_K_PRD_v0.2.md) — the **frozen v1.1 predecessor** (RELEASED 2026-05-09). This v0.3-MVP carries its scope **in full** (KEEP-in-full) and applies the naming cascade; `greenfield/` is never edited (plan R4).
  - [`../01-triage/Module_K_CutSheet_v0.1.md`](../01-triage/Module_K_CutSheet_v0.1.md) — the **ratified cut-sheet** (Paolo 2026-06-07). §2 feature inventory = the scope; §3 module-specific changes = the rewrite instructions; §5 = the acceptance delta; §6 = the six ratified Qs.
  - [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) — the **coherence gate** (RATIFIED 2026-06-07). Item A (naming cascade), item D (the Club-Credit three-way seam — K.17 KEPT, K.18/K.19 deferred), item E (OC 5% capture whole at launch), item L (one producer write = K membership approve/decline), editorial note **N2** (K's Hold registry is trigger-agnostic), and §6 floor chains 2 (KYC/sanctions/Hold) + 6 (audit/retention).
  - [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18 — the **source-of-truth name table** for the cross-module naming cascade. **Applied here, not re-derived.**
  - [`../01-triage/Module_S_CutSheet_v0.1.md`](../01-triage/Module_S_CutSheet_v0.1.md) §3.2 — where the **substantive Club-Credit decision** lands (the heavy redemption/financial logic lives in S; K Q2 = "KEPT in K, decided in S").
  - [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (method, P1/P2, the floor) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (locked dials — D8 club KEEP, D1 currencies KEEP, D2 locales KEEP, L-PP one producer write).
- **Methodology** (carried from v1.1; unchanged):
  - **DEC-072** — no accounting-policy positions. The prose may use accounting-domain terms descriptively; Module E records financial events and Xero decides GL treatment. Module K records party / credit / hold **state**, never accounting treatment.
  - **DEC-073** — product-spec layer only (entity concepts, business attributes, lifecycle states, business-meaningful enum values, domain-event names + business signals, module boundaries, invariants). Physical representation (column types, FK naming, JSON shapes, indexing, KYC-vendor / screening-vendor wiring, UX/layout) is the dev team's call and is out of scope.
  - **DEC-074** — self-contained delivery document. Every entity is reintroduced in full NewCo language; a tech reader who has not read v1.1 can take this into the dev phase. The v1.1 + cut-sheet + Phase C trace is preserved at §19.
  - **MVP principles (plan §4.1):** **P1 — defer without burning bridges** (every deferred item names the seam that makes the post-launch build additive, and points to the roadmap); **P2 — admin-first, self-serve-later** (producer/back-office writes are operator-driven via the Admin Panel; consumer storefront/cellar/Bottle-Page exempt). *Module K's instance of P2: exactly **one** producer write is retained platform-wide — membership approve/decline (L-PP); everything else producer-facing is operator-driven (§2, §3 below).*

---

## §0 MVP scope at a glance

**Verdict: Module K is KEPT IN FULL + the naming cascade.** Like Module 0, Module K is a foundational module a Lean MVP does **not** gut — and it carries the compliance floor on top, so it stays whole. Two facts converge:

1. **Module K houses essentially the entire compliance floor.** KYC, sanctions screening, the unified Hold gate (+ DEC-181 uniformity), and GDPR right-to-erasure (soft-delete + anonymisation + 10-yr retention) all live here. The MVP plan §3 names all four un-cuttable. None defers or simplifies.
2. **D8 (the club / membership model) is locked KEEP-FULL as a core value proposition** (the locked dial). The heaviest club machinery — multi-profile identity, Club-Credit **redemption** + closure-conversion math, Hero/stacking price resolution — is either load-bearing or physically resident **downstream in Module S/E**, so the Module-K-side cut is thin by construction. The substantive D8 savings-hunt lands in the **Module S** cut-sheet.

**Net Module-K-layer deferrals beyond what v1.1 already defers are narrow:** the two Club-Credit *peripheral* mechanics **K.18** (welcome-window proportional scaling) and **K.19** (operator manual Club-Credit issuance) defer (decided in Module S, Phase C item D — each with a retained seam in K), and the **gifting-initiation read-API idles** at launch (D5, the S+K+C tri-module gifting defer). **Everything else KEEPS** — the compliance floor, the D8 club spine, the Club-Credit **core** (entity + auto-issuance + one-active invariant + **K.17 carry-forward**), and the **OC 5% capture** are all whole.

**The naming cascade (Phase C item A — the one mechanical change).** Module K applies Module 0's source-of-truth names to the **Producer → Product Master** link prose and to any prose that references Module-0 (PIM) events. **K's own event names are unchanged** — `ProducerActivated` / `ProducerRetired` / `ProducerCreated` are *K's* events that PIM **consumes**; they do not rename. The category-neutral consumed-event names (`MembershipFeePaid` — Module-S-emitted per DEC-173, `ClubCredit*` — Module-E + Module-S-emitted per DEC-174, …) are likewise unchanged by the cascade (the Module E / Module S carve-out). "Wine Master" is retained as a wine-display alias for Product Master. **Naming/contract only — zero consumer behaviour change.**

**The N2 editorial note (Phase C item D / §5-N2 — landed here).** Module K's Hold registry is **trigger-agnostic**: it records Hold types + state regardless of what triggered them. Post-ratification the **chargeback Hold trigger is automated** (D21 KEPT — Paolo override; payment automation stays) while the **storage-payment Hold trigger is manual-first** (D4 INV3 auto-escalation deferred). The K.28 prose is aligned (§4.8.1, §15.8) so "manual-first" does not imply *both* triggers are manual. **No behaviour change** — one-line clarity; the registry, the Hold types, and the manual-placement path are unchanged either way.

**The floor pieces Module K holds (all KEPT, whole) — verified in composition by Phase C §6:**
- **KYC / enhanced-KYC** (§9.1) — the four-state KYC lifecycle + the €10k-single / €50k-cumulative enhanced trigger. *(Floor chain 2.)*
- **Sanctions screening + the order-completion gate** (§9.2–§9.3) — EU + UIF + OFAC; a Customer can exist `sanctions=pending`; the single enforcement point fires at **order completion** (Module S [S.15] enforces — Module K + Module A are sanctions-blind by design; K exposes the read-API tuple). *(Floor chain 2.)*
- **The unified Hold gate + DEC-181 uniformity + DEC-160 per-type lift discipline** (§4.8). *(Floor chain 2.)*
- **GDPR right-to-erasure (soft-delete + anonymisation) + 10-yr txn-history retention** (§8.2, §12). *(Floor chain 6.)*
- **The Originating-Club `OriginatingClubLocked` capture** (§6) — one-shot at first approval, immutable, unreconstructable; whole at launch (the 5% computation defers with settlement, D19). *(Phase C item E — seam-critical.)*
- **The Club-Credit entity + auto-issuance on `MembershipFeePaid` + one-active-per-Profile invariant + K.17 carry-forward** (§11). *(Phase C item D.)*
- **The Hero Package capacity invariant** (§13) — the membership no-oversell guard; enforced against Module A's allocation `qty`.

**The six ratified scope confirmations (cut-sheet §6, Paolo 2026-06-07):**
- **Q1 — launch marketing consent KEPT.** NewCo runs outbound campaigns at launch, so the double-opt-in marketing-consent lifecycle ships alongside the always-kept T&C-governed transactional path (§8.1). (Confirm single-vs-double opt-in with legal at build time.)
- **Q2 — Club Credit KEPT in K; the substantive call decided in S.** **K.17** partial-redemption/carry-forward **KEPT** (load-bearing customer value — ratified S Q2); **K.18** welcome-window scaling + **K.19** operator manual issuance **DEFERRED** with retained seams in K; the entity + auto-issuance + one-active invariant KEPT (§11).
- **Q3 — producer-content approval role-count admin-configurable, no spec change.** The 3-step Creator → Reviewer → Approver Producer-content workflow is the spec; a small launch onboarding team may run a lighter (e.g. 2-step) approval by configuration. The separation-of-duties floor (no self-approval; distinct actors; audited) holds at any depth (§4.4, §2). *(Same decision as Module 0 Q2.)*
- **Q4 — exactly one producer write platform-wide = membership approve/decline (= L-PP).** Producer-initiated invitation is operator-driven; Hero-Package designation / capacity adjustment operator-driven; ProducerAgreement drafting is back-office (§3.2, §2).
- **Q5 — Hero Package mid-year capacity mutability KEPT** (lets a sold-out Club add capacity; cheap — §13.4).
- **Q6 — ProducerAgreement KEEP-minimal** (the entity is the D19 settlement-cadence seam; full lifecycle retained, placeholder fields pending Q-OQ-9 — §4.6).

**Deferred set (MVP):** the **K.18 / K.19** Club-Credit peripherals (seams retained — §11.1, §17), the **gifting-initiation read-API idle** (D5 — §17, §16), and v1.1's **already-deferred set carried verbatim** to the post-launch roadmap with their existing re-introduction hooks (§17). **No new Module-K floor or club-spine scope is cut.**

---

## §1 Module Purpose

Module K is NewCo's authoritative registry for the **parties** NewCo transacts with and around, plus the **eligibility, consent, screening, and lifecycle state** that surrounds them. The parties are: **Customers** (B2C individual collectors), **Profiles** (memberships, one per Customer per Club), **Producers** (winery identity), **Suppliers** (commercial counterpart distinct from Producer), **Clubs** (one Producer per Club at launch), **ProducerAgreements** (the commercial contract scoping each Producer relationship), **Accounts** (the transactional container per Customer), and **Holds** (the unified blocking mechanism that gates commercial activity across Customer / Account / Profile scopes).

Module K is identity-and-eligibility-only. It does NOT do pricing, allocation, inventory, fulfilment, settlement payment, invoicing, or accounting treatment. It records *who exists, what state they are in, what they are eligible to do, what holds gate that eligibility*, and emits versioned domain events when those states change. Downstream commerce (Module S Sales, Module A Allocations, Module D Procurement, Module B Bottle / Stock, Module C Fulfilment, Module E Financial Events) consumes those events and reads Module K state on demand.

NewCo's Module K diverges from Crurated v17's Module K on a focused set of surfaces driven by NewCo's narrower launch scope and net-new mechanics (all carried into the launch MVP unchanged from v1.1):

- **B2B segment dropped at Customer level** (DEC-068). NewCo serves individual collectors only at launch. A company-billing affordance is preserved at Address / payment-method level for collectors who transact through their own companies for fiscal reasons; the Customer record itself stays the natural person.
- **Crurated-Member club type and tier model dropped**. NewCo's Clubs are all Producer-operated partner clubs; the v17 MEMBER ↔ IO ↔ LEGACY tier flow does not apply. NewCo's Profile state machine is generic across all Clubs; Legacy is a *Customer segment* (not a Profile tier).
- **Originating Club mechanic added** (DEC-008 / DEC-066). Each Customer has an *Originating Club* — the first Club to approve them — captured one-shot at first approval and immutable thereafter. It drives the 5% Originating-Club share on Discovery sales (DEC-032).
- **ProducerAgreement entity added** (DEC-070). NewCo manages each Producer relationship through a first-class commercial agreement with its own lifecycle, distinct from the Producer entity itself.
- **Marketing consent + soft-delete + anonymisation activated at launch** (DEC-026 / DEC-027). v17 left these deferred; NewCo activates them on day one (Q1: outbound marketing runs at launch).
- **Sanctions screening separated from KYC** (DEC-071). v17 conflated identity verification and sanctions screening; NewCo carries them as two independent lifecycles on Customer.
- **Direct payment provider is Airwallex** (DEC-014). The Account entity holds the payment-provider customer reference; the rest of the integration is unchanged in shape.
- **AgencyAgreement entity absent-or-dormant**. NewCo does not operate active consignment / third-party agency at launch; the v17 entity may stay in the inherited model for future-flexibility **or be omitted from the schema entirely** — either satisfies (MVP-DEC-034; the operative seam is the Third-Party-Owner Party subtype, MVP-DEC-021 Q1); it is not exercised by any NewCo flow (carried to roadmap, §17).

Cross-cuts at launch:

- **Module 0** (PIM): a **Product Master** *(wine-display alias: Wine Master)* holds a link to a Producer in Module K's Producer Registry. PIM holds no Producer attributes. **(Naming cascade — Phase C item A; §18.)**
- **Module S** (Sales): consumes Profile state, Customer segment, Hold state, and Originating Club state for Offer eligibility, Hero Package capacity-invariant enforcement, Club Credit redemption gating, and Discovery single-producer admissibility. **Module S is the consumer-side sanctions/Hold enforcement point at order completion** (S.15).
- **Module A** (Allocations): consumes Customer + Profile + Hold state for offer-eligibility checks; the Hero Package capacity invariant binds to the Hero Package's underlying Allocation `qty`.
- **Module D** (Procurement): owns the SupplierProducerLink that joins Producer to Supplier; consumes ProducerAgreement state for procurement-side gates.
- **Module E** (Financial Events): records the financial events of membership-fee payment, Club Credit lifecycle, settlement, invoice issuance, and payment. Per DEC-072, Module E records the events; Xero decides GL treatment. Module E also **emits** the financial events Module K consumes to set Hold + Club-Credit state; the membership **fee-paid** signal (`MembershipFeePaid`) is **emitted by Module S** (Module E records it; Module K consumes — DEC-173), driving Profile activation (§15.8).

---

## §2 Personas

Module K serves several operator-facing roles plus a customer-facing surface (the Consumer Portal) that reads Module K state and makes only a **narrow set of self-service writes** (consent capture, and the membership `auto_renew` toggle — §4.2 / BR-K-Profile-5), plus a producer-facing surface that at launch is read-only except for the single retained producer write.

- **Customer Care / Onboarding Operator.** Creates and edits Customer records; runs onboarding flows (direct registration, Club-link registration, invitation); resolves Customer-side issues; manages address and payment-method-reference state on Account.
- **Producer Onboarding Operator.** Creates and edits Producer records; manages Producer KYC state; drafts and activates ProducerAgreement entries; configures Clubs operated by the Producer; **operates the producer-facing writes that are operator-driven at launch** (invitation send, Hero-Package designation, capacity adjustment — P2 / L-PP).
- **Compliance Reviewer.** Manages KYC verification state (the four-state lifecycle) and sanctions-screening state (the separate four-state lifecycle); reviews enhanced-KYC threshold trips; manages compliance Holds; runs the right-to-erasure approval path.
- **Marketing-Consent Operator** (typically Customer Care with a marketing-policy hat). Reviews marketing-consent state changes for audit; supports right-to-object workflows; coordinates with the HubSpot integration that owns outbound marketing communications.
- **Catalog Lead** (cross-cutting, typically the same role as Module 0's Catalog Lead). Approves Producer activation as a step in the Producer-content approval workflow alongside the Module 0 Product Master approval flow.
- **Consumer Portal end-user (read-mostly; narrow self-service writes).** Sees their own Customer + Profile state, their Originating Club narrative, their Club Credits, their consent state. The only customer-driven **self-service writes** to Module K state at launch are **consent capture and the membership `auto_renew` toggle** (turn auto-renewal off / on before the renewal date — §4.2 / BR-K-Profile-5 / BMD §2.4); every other Customer-driven change flows through onboarding and admin-mediated paths.
- **Producer Portal end-user.** At launch: **full read + full reporting** (D23 KEPT) and **exactly one write — membership approve/decline** (the one retained producer write, L-PP). Every other producer-facing write (invitation, Hero-Package designation, capacity adjustment) is operator-driven via the Admin Panel at launch (§3.2).
- **HubSpot integration (system actor).** Receives Module K's full customer-data sync; owns outbound **marketing / lifecycle** delivery (consent-gated); **operational / transactional email is ERP-sent through the single email service, not HubSpot** (§14.9.1 — MVP-DEC-035); never edits Module K state directly.

**Operator surface (P2).** All Module-K back-office and producer-facing writes — **except** membership approve/decline — are operator-driven through the **Admin Panel** at launch. The consolidated operator-surface inventory lives in the 9th Admin-Panel PRD (it references this PRD's capabilities rather than re-specifying them). The consumer storefront/cellar/Bottle-Page reads are exempt (self-serve).

**Producer-content approval role-count is admin-configurable (Q3).** The 3-step Creator → Reviewer → Approver Producer-content workflow (§4.4) is the specified governance; the **number of distinct roles is an operational configuration** (`feedback_prd_rr_approval`): a small launch onboarding team may run a lighter (e.g. 2-step Creator → Approver) approval **by configuration, with no spec change**. The separation-of-duties floor is preserved either way — **self-approval is never allowed**, every step that runs is performed by a distinct actor, and each step is audited. *(Same decision as Module 0 Q2.)*

Module K consumers (the Module S Pricing Operator, the Module A Allocation Operator, the Module B / C / D operations teams, the Module E settlement team) interact with Module K through events and reads, not through Module K-internal workflows.

---

## §3 Architecture — Netflix-Style Customer-Profile Model

Module K's load-bearing pattern is the **Netflix-style Customer-Profile model**: one Customer (the natural-person identity, the email address, the payment-provider identity) can hold **multiple Profiles**, one per Club they have joined. The Profile, not the Customer, is the level at which Club-side eligibility, fees, tier, Club Credit, and per-Club state live.

The pattern lets NewCo represent a single individual collector who is a member of multiple producer Clubs — **a common case at launch (many target collectors are members of three to five producer clubs simultaneously)** — without duplicating customer identity. Each membership is a Profile; the Customer is the cross-cutting identity above.

> **Savings-hunt verdict (cut-sheet §3.1): multi-profile is load-bearing — NOT a safe cut.** The dials worksheet floated "consider single-profile." Verdict: **unsafe.** Single-profile would break the multi-club-collector use case *and* force a later rebuild (violates P1). The Netflix-style model stays. (This is the most consequential D8 savings-hunt finding — a *negative* one.)

Concretely:

- **Identity, consent, screening, and segment** live on Customer. Email, name, phone, date of birth, T&C / privacy acceptance, KYC status, sanctions-screening status, marketing-consent state, soft-delete / anonymisation state, preferred currency, preferred locale, and the Originating Club link — all attach to Customer.
- **Per-Club state** lives on Profile. Membership status, role, joined date, fee-paid moment, tier (single-tier at launch per DEC-062), Club Credit reference, auto-renew flag, membership validity period — all attach to Profile.
- **Customer segment** is a *materialised view computed from Profile states across all Clubs* (Member, Waiting-list, Legacy — see §5). The strongest segment governs cross-cutting access logic.
- **Holds** can attach to Customer (cross-cutting block — KYC failure, fraud, compliance), to Account (payment / credit issues), or to Profile (per-Club issues). The unified Hold entity (§4.8) records the scope and type.
- **Originating Club** is the *first* Club to approve a Customer's membership across their lifetime — captured one-shot at the moment of first approval, then immutable. Drives the 5% Originating-Club share on Discovery sales (§6).

The Profile-as-Membership collapse is intentional: there is no separate Membership table. The Profile *is* the membership; one Profile per Customer per Club.

### §3.1 Producer-write treatment at launch (P2 / L-PP) — the one retained producer write

At launch the producer surface is **full-read + full-reporting + view-only**, with **one** producer write retained platform-wide: **membership approve/decline** (incl. waitlist approval). This is the single instance of L-PP (ratified K Q4); every other producer-facing write is operator-driven via the Admin Panel. **No backend capability is cut** — only producer-facing write *UIs* are deferred, and admin-parity (DEC-083) covers the operator path.

| Producer write (Module K surface) | Launch treatment | Seam (P1) |
|---|---|---|
| Membership **approve/decline** (incl. waitlist approval) (§4.2.1, §13.5) | **Retained — the one producer write (L-PP, Q4).** A minimal producer approve/decline surface ships. | Backend approval logic ships at launch; the richer "waitlist review" UX is post-launch Producer-Portal scope. |
| Producer-initiated **invitation** (§7.3) | **Operator-driven via Admin Panel** (producer invite UI deferred). | Same backend; producer invite UI built later. |
| Hero Package **designation** / **capacity adjustment** (§13) | **Operator-driven via Admin Panel** (already post-launch Producer-Portal UX per the v1.1 §16 boundary). | Same backend; producer UI built later. |
| ProducerAgreement drafting (§4.6) | **Operator action by definition** (back-office; not a producer surface). | n/a. |

The **Producer Portal read + full 7-section reporting (D23) is KEPT** at launch (it reads A / S / D / E / K + B — the MVP-DEC-037 read-list; sell-through measured against `received_to_date` with the committed `qty` alongside, Admin Panel PRD §2.2). The consumer storefront / cellar / Bottle-Page reads are exempt (self-serve).

---

## §4 Entity Model

Module K's entity set at launch comprises eight entities. Four are inherited from v17 with NewCo-specific scope adjustments (Customer, Profile, Club, Account); two are inherited verbatim (Producer, Supplier); one is NewCo net-new (ProducerAgreement); one is the unified blocking mechanism (Hold). **All eight are KEPT in the launch MVP.**

### §4.1 Customer

The **Customer** is NewCo's natural-person registry — every Customer at launch is conceptually an individual collector. NewCo does not sell to restaurants, hospitality businesses, or other B2B segments at launch (DEC-017 + DEC-068).

Each Customer carries:

- **Identity**: name, email (globally unique across NewCo), phone, date of birth.
- **Status lifecycle**: `pending → active → suspended → closed`. `pending` at registration; `active` once email is verified, T&C and privacy policy are accepted, and KYC has cleared if required; `suspended` on cross-cutting Holds; `closed` on permanent closure (independent of and orthogonal to anonymisation — see §8).
- **T&C and privacy acceptance**: tracked at Customer level with timestamps. Acceptance is a hard gate on the `pending → active` transition. Re-acceptance on policy version updates is operationally determined and not enumerated in the lifecycle.
- **Preferred currency** (one of NewCo's five launch currencies per DEC-037 / D1 — KEEP all 5) and **preferred locale** (one of the six launch locales per DEC-031 / D2 — KEEP all 6).
- **KYC state** (§9) **(FLOOR)**: a four-state lifecycle (`not_required / pending / verified / rejected`) and a `kyc_required` flag administratively set per Customer.
- **Sanctions-screening state** (§9) **(FLOOR)**: a *separate* lifecycle from KYC, with four states (`pending / passed / failed / under_review`), the moment of last screening, and the next scheduled re-screen (12-month cadence per DEC-030).
- **Enhanced-KYC trigger** (§9) **(FLOOR)**: a flag and timestamp recording whether the Customer has crossed the €10,000 single-purchase or €50,000 cumulative-annual threshold (DEC-035).
- **Marketing-consent lifecycle** (§8) **(Q1 — KEPT)**: four states (`none / requested / confirmed / revoked`) with timestamps for each transition (DEC-026).
- **Soft-delete / anonymisation state** (§8) **(FLOOR)**: a flag and timestamp recording whether the Customer's PII has been overwritten under a GDPR erasure request (DEC-027).
- **Originating Club link** (§6): a reference to the *first* Club that approved this Customer's membership across their lifetime; immutable once set; may stay unset indefinitely for Customers who only ever buy on Discovery (the no-OC allowance per DEC-040).
- **Customer segment** (§5): a materialised view of `Member / Waiting-list / Legacy` derived from the Customer's Profile states; refreshed on every Profile state transition that crosses a segment boundary plus a daily background reconciliation job.

**Dropped from v17 inheritance** (carried in v1.1; unchanged at MVP): the B2C / B2B discriminator and the B2B-specific attribute cluster (company name, registration number, VAT identifier, legal entity type, primary-contact name and email). The B2B onboarding flow is also dropped.

**Company-billing affordance preserved at Address level** (DEC-068). An individual collector may transact using their own company's billing details for fiscal reasons. The information attaches to **Address**, not to Customer: a billing Address record can carry optional company name and VAT identifier; payment methods (Module E scope) may be corporate cards. Invoice issuance reads company-billing data from the Address used at purchase. Customer remains the natural person. **At launch the company-billing field set is exactly `company_name` + `vat_id` (free-text — echoed onto the invoice, with no VAT/VIES validation); the fuller B2B billing cluster DEC-068 dropped (registration number, legal-entity type, billing / primary contact) stays out (MVP-DEC-021).** Address itself is a **Customer-scoped record (an address book Module K owns; Module C reads it read-only and copies it onto the SO `destination`)** — a referenced field-cluster, **not** one of Module K's eight first-class entities, and **not typed billing-vs-shipping**: the "billing" role is simply the Address selected at purchase, and there is no stored default-billing designation at launch. Whether the physical model carries an `address_type` / default flag is the dev team's call (DEC-073).

### §4.2 Profile

The **Profile** is the membership in one Club. One Customer holds at most one Profile per Club; one Profile belongs to exactly one Customer and one Club. Profile *is* the membership — there is no separate Membership table.

Each Profile carries:

- **Status lifecycle**: the state machine in §4.2.1.
- **Joined moment**: the timestamp of Profile creation.
- **Membership validity period**: the start and (optionally) end of the current paid period; null end means open-ended.
- **Fee-paid moment**: the timestamp at which the most recent membership fee payment was confirmed by the payment provider — set by Module K on consumption of the `MembershipFeePaid` event (Module S emits; Module E records — DEC-173), never by Module K independently.
- **Tier**: a string referencing one entry in the Club's tier definitions. At launch every Club is single-tier (DEC-062), so every Profile is on its Club's single tier; the field exists for future-flexibility multi-tier activation.
- **Auto-renew flag**: whether the Profile auto-renews at the end of the validity period. It **default-inherits the Club's `renewal_policy.auto_renew`** (§4.3) at Profile creation; thereafter the Customer may **self-toggle it via the Consumer Portal** (a member can turn auto-renewal off — and back on — before the renewal date; BMD §2.4 / B2), and an Operator may also set it (BR-K-Profile-5; MVP-DEC-022).
- **Club Credit reference**: a link to the Profile's currently-active Club Credit record, if any (§11). At most one active Club Credit per Profile.
- **Role**: a per-Club configurable role (e.g., Member, IO, Admin); single-role at launch but the field carries the v17 inheritance.
- **Invited-by reference** (optional): when the Profile was created via a producer-initiated invitation flow (§7), the inviting actor is recorded here for audit.
- **Lapsed timestamp** (optional): when the Profile most recently transitioned to Lapsed; supports the 30-day grace mechanic (DEC-034).

#### §4.2.1 Profile state machine

The state machine is generic across all Clubs — there is no Crurated-Member-specific MEMBER ↔ IO ↔ LEGACY auto-flow. Legacy in NewCo is a *Customer segment*, not a Profile state.

- **`Applied`** — initial state when a Customer applies to a Club (or when an invited Customer accepts an invitation; the application-vs-invitation distinction is a §7 Onboarding concern, not a state distinction).
- **`Applied → WaitingList`** — when applications exceed the current Hero Package capacity (§13), the Profile lands on the waitlist. From WaitingList the Profile can transition to Approved (capacity opens up — §13's mid-year mutability mechanic) or to Rejected (the Producer declines).
- **`Applied → Approved`** — the Producer approves the Profile (the one retained producer write, L-PP). Approval is **not** a "pay later" step: it **atomically** charges the Hero Package fee against the **charge-on-approval mandate captured at application** (§7) and activates the Profile, gated by the Hero Package capacity invariant at this moment (§13). `Approved` is therefore the **transient approve-and-charge action, not a durable unpaid state** — on a successful charge the Profile passes through `Approved` to `Active` in one operation, and a Customer is never left sitting in an unpaid `Approved` state. On the Customer's *first* successful approval into any Club across their lifetime, the `OriginatingClubLocked` event fires (§6). **If the charge fails, the approval does not complete:** the Profile remains `Applied` (or `WaitingList`), **no Hero Package seat is consumed** (§13.1), and the Customer is re-prompted to remedy the payment method so the approval can be re-attempted. *(The mechanism — saved card vs. mandate token, the exact instrument — is the dev team's call per DEC-073; it must be charge-on-approval, hold no funds, and survive arbitrary approval delay + waitlisting — MVP-DEC-016.)*
- **`Applied → Rejected`** — terminal-for-this-application state. The Customer can re-apply to the same Club later; that creates a new application path on a new Profile row (Profile is the membership; rejected Profiles are not reused).
- **`Approved → Active`** — completes **atomically within the approval above**, on payment-provider-confirmed capture of the Hero Package fee, signalled by **Module S's `MembershipFeePaid` event** (Module S emits; Module E records the financial event; Module K consumes — DEC-173 / DEC-157; the Hero Package fires INV1, there is **no separate INV0**). Free-club activation (where no fee applies) transitions directly. The Customer can now transact against the Club.
- **`Active → Suspended`** — on a Profile-level Hold (Producer-initiated kick, payment hold, admin action targeting this Profile) or via a Customer-level Hold (cross-cutting fraud / KYC / compliance) that cascades to all of the Customer's Profiles (§10).
- **`Suspended → Active`** — when the triggering Hold is lifted. The Profile **retains its Hero Package seat throughout suspension**, so the lift is **never capacity-re-checked and is never blocked** by the cap (§13.1 — a `Suspended` Profile occupies a seat exactly as an `Active` one does; MVP-DEC-017).
- **`Active → Lapsed`** — when the membership validity period passes without a successful renewal payment. The Profile carries a `lapsed_at` timestamp; re-activation within the 30-day grace window (DEC-034) requires only the renewal payment, not a re-application.
- **`Lapsed → Active`** — within the 30-day grace, on a successful renewal payment. After 30 days, the Profile transitions to Cancelled.
- **`Lapsed → Cancelled`** — at 30 days post-lapse without renewal, terminal cancellation.
- **`Active → Cancelled`** — Customer-initiated voluntary cancellation, admin action, Producer offboarding cascade (§10), or Customer death / corporate dissolution (operationally handled per the Q-OQ-11 deferral).
- **`Active → Inactive`** — when a Profile holds no Club Credit balance, no in-flight orders, and the membership has been deliberately deactivated without cancellation (operational corner case; rarely used at launch — inherited and cheap, left intact).

The Profile is never hard-deleted at launch — Cancelled and Inactive are terminal soft-delete states preserving audit history. Re-activation from Cancelled / Inactive requires a fresh application unless the lapse-grace path applies.

**`ProfileTierChanged` transition reasons.** When a Profile's tier changes (multi-tier activation post-launch), the transition records one of: voluntary upgrade, voluntary downgrade, Producer-initiated kick, non-payment of renewal, KYC re-screen failure, or *other* with a mandatory free-text note. The four LEGACY-related transition reasons that v17 carried (Crurated-Member-specific) are dropped per the segment-not-tier model (§5).

### §4.3 Club

A **Club** is a Producer-operated membership program at NewCo. Every NewCo Club at launch is operated by exactly one Producer; a single Producer may operate multiple Clubs (BMD §3.4).

Each Club carries:

- **Identity**: a display name, a reference to the operating Producer (immutable once set), and a status.
- **Status lifecycle**: `active → sunset → closed`. `active` is the steady state; `sunset` blocks new memberships and new offers but preserves existing Profiles until membership periods end (the dissolution workflow in §10); `closed` is terminal once all members have migrated or expired.
- **Fee model**: per-Club fee amount, billing frequency, waiver rules.
- **Tier definitions**: at launch every Club's tier definition contains a single tier; the structure carries forward to support future multi-tier activation as a configuration change (DEC-062).
- **Credit policy**: the Club's rules for issuing, scoping, and forfeiting Club Credit (§11).
- **Renewal policy**: auto-renew default, grace period, lapse rules.
- **Generates-credit flag**: whether membership-fee payment auto-generates a Club Credit. Producer-operated Clubs at NewCo typically have this true.
- **Registration flow type**: the onboarding **entry channel** that governs how a prospect reaches the application step — **not** the approval gate. Producer approval (the atomic approve = charge = activation, §4.2.1) is mandatory for **every** value; **no value auto-approves or bypasses the producer write** (approve/decline is the one retained producer write — DEC-069). Launch-live values map to the three §7 onboarding flows: **`application_with_approval`** — the open self-application path (§7.1), the launch default; **`invitation_only`** — the open path is closed, entry only via a producer/operator-issued invitation (§7.3); **`link_onboarding`** — entry via a shared Club-specific registration link (§7.2). The legacy value **`open`** (auto-join *without* producer approval) is carried latent for v17 inheritance and is **not selectable at launch** — it would contradict the mandatory producer-approval write. This field **subsumes the former `invite_only` boolean** (`invite_only = true` ⟺ `invitation_only`); physical representation is the dev team's (DEC-073), but the spec carries one field (BR-K-Club-6; MVP-DEC-022).
- **Revenue-share terms** (placeholder): per-Club override of the standard revenue-share defaults (12.5% PO on club sales, 5% Originating-Club share on Discovery), if any. Empty at launch since terms are uniform.

*(The former standalone `invite_only` boolean is removed — it is subsumed by `registration_flow = invitation_only`, see the Registration-flow-type bullet above; two fields encoding the same fact could contradict. MVP-DEC-022.)*

**Producer association is required.** NewCo Clubs are always Producer-operated at launch; Club creation rejects a missing Producer association. **Crurated-Member club type dropped** — Crurated is a Discovery supplier (DEC-020), not a Club operator; every NewCo Club is the same shape. **Club creation is a direct operator action** — a `ClubCreated` event fires on creation. **Multi-club-per-producer** is admitted natively; the Originating Club mechanic (§6) resolves to the *specific* Club, not the aggregated Producer.

**Future-flexibility — Supplier-operated Clubs**: at launch only Producers operate Clubs (DEC-067). Generalising the operator association to admit Suppliers is **not in launch scope** (§17).

### §4.4 Producer

The **Producer** is the winery — **the identity source for Product Master *(wine-display alias: Wine Master)* in Module 0 PIM** *(naming cascade — Phase C item A; §18)*. The Producer entity answers "who made this wine?" — independent of who NewCo transacts with commercially (the Supplier role; §4.5).

Each Producer carries:

- **Identity**: name, region, optional appellation, country. *(These describe the **Producer** — its home region / appellation / country. They are **distinct from the wine's** region / appellation, which live in Module 0's `WINE` attribute set on the Product Master and are authoritative for the wine's region on the Bottle Page; the two are not duplicates and neither is derived from the other — Module 0 PRD §3.9 / §9.1.)*
- **Status lifecycle**: `draft → active → retired`. Activation requires KYC to be **cleared** (`verified` or `not_required` — see the KYC-status bullet); retirement preserves existing Product Masters but blocks new Product Master activation (the cascade rule documented in the Module 0 PRD).
- **KYC status**: a four-state lifecycle (`not_required / pending / verified / rejected`) at the Producer level — verification of wine authenticity and provenance, distinct from Customer-side KYC. **KYC is *cleared* (non-blocking) when it is `verified` *or* `not_required`, and *blocking* when it is `pending` or `rejected`** — exactly as on the Customer side (§9.1), where `not_required` is likewise a cleared, non-blocking state. Every gate that requires the Producer to be "`active` and KYC-cleared" — the Producer's own `draft → active` activation (above), Product Master activation (Module 0), allocation creation (Module A), and PO-line creation (Module D) — passes when the Producer is `active` **and** KYC is cleared (`verified` or `not_required`), and blocks only while KYC is `pending` or `rejected`. A Producer for whom provenance KYC is `not_required` is therefore not held back from activation or any downstream use; `not_required` and `verified` are equivalent at every gate.
- **Customer-facing description**: the producer story / winery narrative the Bottle Page (Module B) reads at render time. Translatable across the six launch locales (DEC-031 / DEC-064 / D2 KEEP). This is the canonical home for producer-level descriptive content; Product-Master-level and Product-Variant-level prose lives in Module 0 PIM.
- **Website** (optional).

**Producer is NOT a Party subtype.** The Producer entity is a standalone registry in Module K, distinct from the Party Registry (which subtypes Customer, Supplier, and a dormant Third-Party Owner). Some Producers (e.g. châteaux distributed only via négociants) may never have a Party / Supplier record at all.

**Content workflow.** Producer content (name, description, region, website) follows a 3-step Creator → Reviewer → Approver approval workflow analogous to the PIM lifecycle in Module 0 — same governance pattern, applied to the upstream Producer that Product Master depends on. **The analogy includes review-freshness (MVP-DEC-019): editing the review-governed descriptive content of an already-`active` Producer (name, description, region, website) re-arms the review — the edit re-enters the Creator → Reviewer → Approver workflow and does not publish until it passes; post-activation content edits are *not* operator-driven without approval. The Producer stays `active` serving the last-approved content meanwhile (a content edit never takes a live Producer offline), and how the re-arm is tracked — a flag, a version-stamped review record — is the dev team's (DEC-073).** The role-count is admin-configurable (Q3): a lighter (e.g. 2-step) approval may be configured for the small launch onboarding team — so re-review stays lightweight — no spec change; the separation-of-duties floor (no self-approval; distinct actors; audited) holds at any configured depth. *(Same decision as Module 0 Q2 + the MVP-DEC-019 review-freshness invariant; BR-K-Producer-5.)*

**Discovery-only Producer admission.** A Discovery-only Producer is simply a Producer Registry entry (active, KYC-cleared) for whom no Club references them. There is no separate "Discovery-only" attribute; the absence of any Club operating under that Producer *is* the Discovery-only state. The Module 0 PIM Product Master holds the link to the Producer regardless of whether the Producer operates a Club. The Producer's relationship with NewCo is governed by a ProducerAgreement (§4.6) regardless of Club operation.

**KYC-revocation symmetry.** If a Producer's KYC verification is revoked after Product Masters have already been activated, those existing Product Masters remain active; the revocation only blocks *new* Product Master activations under that Producer.

**No auto-linking to Supplier.** Creating a Producer does NOT auto-create a Supplier; creating a Supplier does NOT auto-create a Producer. When a real-world entity plays both roles, the operator creates both records explicitly and links them via the SupplierProducerLink owned by Module D.

### §4.5 Supplier

A **Supplier** is the **commercial counterpart** — the legal entity NewCo transacts with to obtain wine. Supplier answers "who does NewCo do business with?" — distinct from Producer ("who made it?"). In the Bordeaux model the producer (château) and the supplier (négociant) are different entities; in the direct-buy Italian model they are often the same real-world entity, but the two roles always occupy distinct system identities.

Supplier is a **Party Registry subtype** (the Party entity has subtypes Customer, Supplier, and a dormant Third-Party Owner subtype that is inherited but unused at NewCo launch). Each Supplier carries the legal name, the immutable party-type marker, and standard timestamps; richer Supplier-side commercial state (Supplier Profile, Supplier Agreement, payment terms) lives downstream in Module D.

**SupplierProducerLink.** The N:N relationship between Supplier and Producer is recorded by the SupplierProducerLink entity, **owned by Module D** — Module K is the upstream party registry, not the link-owner. NewCo's launch reality is that 95%+ of Producers will have a 1:1 SupplierProducerLink (Producer and Supplier are the same real-world entity); the separation persists for edge cases and inheritance discipline.

**Discovery-side Suppliers.** NewCo's Discovery business at launch already operates with Suppliers that are not themselves Producers — e.g. Crurated as a Discovery supplier (DEC-020). The Producer ≠ Supplier separation is an active operational reality at launch, not just inheritance discipline. **(This is why the minimal Supplier entity is needed now — cut-sheet K.23.)**

### §4.6 ProducerAgreement **(KEEP-minimal — Q6)**

The **ProducerAgreement** is the commercial agreement between NewCo and a Producer — a NewCo net-new entity (DEC-070) carrying the terms that govern the Producer relationship. **It is KEPT-minimal at launch: the entity is cheap, and it is the stable seam for D19** (settlement is operator-run for the first cycles, but the cadence + term must be recorded somewhere stable).

Each ProducerAgreement carries:

- **Producer reference**: the Producer the agreement governs.
- **Optional Club narrowing**: an agreement may be scoped Producer-wide or scoped to a specific Club (brand-specific sub-agreement). Both shapes are admitted; producer-by-producer call. **For the per-Club shape, the target Club must be `active` at the moment of scoping — `sunset` and `closed` Clubs are not selectable for a new agreement (BR-K-Agreement-4, §14.6). Producer-wide scope carries no Club gate. Supersession/renewal (BR-K-Agreement-3) inherits the superseded agreement's scope rather than re-selecting a Club, so it is exempt — terms for a Club that has since entered `sunset` can still be amended.**
- **Term dates**: 24-month default per BMD §3.3; term-start and term-end record the operative window.
- **Settlement cadence**: the cadence that governs when Module E emits the per-Producer settlement event. Default is the BMD-locked quarterly cadence; per-Producer overrides are admitted (DEC-042). **The launch value set is closed to the three cadences the business model contemplates (BMD §3.10): `quarterly` (default), `monthly` (high-volume producers), and `semi-annual` (low-volume producers). It is enforced server-side (API + DB, not UI-only) — the value times settlement (Module E read) and V1 PO issuance (Module D), so an out-of-set value would mis-time money movement; it is extensible post-launch (a new cadence is a deliberate, business-approved configuration addition, not arbitrary free-text); and the literal representation — enum vs lookup, casing, string form — is the dev team's call (DEC-073).** This is the D19 settlement-cadence seam — recorded at launch even though the settlement engine is operator-run first (E / D19 deferred).
- **Minimum-commitment expression**: a placeholder for the agreement's minimum-allocation commitment. Final shape lands when the 24-month template (Q-OQ-9, deferred per DEC-058) is shared.
- **Payment terms**: standard payment-terms placeholder until the template lands.
- **Special-clause references**: a free-text or document-reference placeholder for non-standard clauses.
- **Status lifecycle**: `draft → active → superseded | terminated`.

**Producer-portal read scope (L-PP).** The **launch-real** commercial terms — Producer reference, optional Club narrowing (scope), term window (`term_start` / `term_end`), and settlement cadence — are **producer-portal-visible (read)**: they are the Producer's own operative contract terms and appropriate to disclose. **The placeholder fields — minimum-commitment, payment terms, and special-clause references (deferred per Q-OQ-9 / DEC-058) — are operator-only and MUST NOT surface on the Producer Portal at launch:** they are unfinalised placeholders that carry internal operator-authored working notes, and ProducerAgreement drafting is back-office by definition (§2). This applies the BMD §3.11 producer-visibility discipline (contract-scoped disclosure; DEC-162) to the ProducerAgreement card. (MVP-DEC-026.)

#### §4.6.1 Lifecycle states

- **`draft`**: created; commercial terms may still be incomplete pending owner sign-off.
- **`active`**: terms accepted by both parties; the operative agreement governing the Producer relationship.
- **`superseded`**: replaced by a newer ProducerAgreement (renewal or amendment); the new agreement enters `active`, the prior transitions to `superseded`. The two are paired in audit history.
- **`terminated`**: permanent end (early termination, dispute, mutual cancellation). Termination does *not* auto-cascade to Producer-level state changes; the Producer's own retirement is a separate, dual-control-gated operator action (§10.2 / BR-K-Producer-6).

**Single-active-per-scope rule.** At any given time, at most one *active* agreement per Producer scope. For multi-Club Producers, "scope" can mean Producer-wide *or* a specific Club, but the two shapes are mutually exclusive for the same Producer at the same time. **Full lifecycle (supersession chain + single-active-per-scope) is retained — it is cheap and it *is* the settlement seam; don't over-cut the bridge (Q6).**

**Sequenced over time.** A Producer may have a sequence of ProducerAgreement rows over their relationship's lifetime — initial 24-month, renewal 24-month, mid-term amendment — with one active at any moment via the supersession chain.

**Cross-references.** Module E reads the active agreement's settlement cadence to time settlement events (D19 deferred — operator-run first; the recorded cadence is the seam). Module D consumes ProducerAgreement state for procurement-side gates. Q-OQ-9 feeds the agreement's full content shape; until then the placeholder field set above is operational. v17's `AgencyAgreement` (governing third-party-owner consignment, dormant at NewCo — §16) is the pattern precedent.

### §4.7 Account

The **Account** is the **transactional container** per Customer — the entity purchase and billing operate against, distinct from Customer (the natural-person identity).

Each Account carries:

- **Customer reference**: the natural-person Customer the Account belongs to.
- **Account type**: at NewCo launch every Account is `personal` (no business-Account creation per the B2B drop in DEC-068). The field carries the v17 inheritance for future-flexibility.
- **Account name**: a display label (defaults to "Personal").
- **Status lifecycle**: `active → suspended → closed` (parallel to Customer's lifecycle, blocked by Account-level Holds).
- **Default currency**: the currency the Account transacts in.
- **Payment-provider customer reference**: a reference to the customer record at NewCo's payment provider (Airwallex per DEC-014), created **lazily** on first payment-related action — not at registration.
- **B2B credit-terms attributes** (`credit_terms`, `credit_limit`): inherited from v17; **unused at NewCo launch** (no B2B segment). Carried for future-flexibility per DEC-068.

**One Customer = one Account** at launch; the Account auto-provisions on Customer activation. Additional Accounts per Customer are admitted by the inherited model but not exercised. **Lazy payment-provider provisioning**: the payment-provider customer reference is created on the first action that requires it; Module E owns the payment-execution side; Module K stores the reference only. **Goodwill / refund credits**: NewCo issues goodwill or refund-compensation as **vouchers** (Module B / S concern), not as an Account-level monetary balance. There is no "Account Credit" instrument at NewCo. Club Credits are tracked separately on Profile (§11).

### §4.8 Hold **(FLOOR)**

A **Hold** is the **unified blocking mechanism** that gates commercial activity — named un-cuttable (plan §3). A Hold attaches to one of three scopes — Customer, Account, or Profile — and one of **eight types**: the **six base types** `admin`, `kyc`, `payment`, `fraud`, `compliance`, `credit`, plus **two NewCo-specific finance-driven additions** — `CHARGEBACK_REVIEW` (DEC-168) and `STORAGE_PAYMENT_FAILED` (DEC-160) — that Module K places on consuming the corresponding Module E events (§15.8). Each Hold records the type, the reason, the actor who placed it, the moment placed, and (when lifted) the actor who lifted it and the moment lifted.

> **Enum provenance + casing (Q-AD-3 — ratified Option B).** The two finance-driven types are **first-class, coordinate enum values alongside the six base types** — *not* sub-types or cause-discriminators of `payment` / `fraud` (the rejected Q-AD-3 Options A/C). They are kept distinct precisely so their lift discipline does not collide with the base types' (§4.8.1). The mixed casing shown here is **illustrative, not normative** — the literal enum form (casing, string values) is the dev team's call per **DEC-073**; the recommended convention is to normalise all eight to one style. *(DEC-160 + DEC-168 already lock the trigger + the Hold creation; the enum additions are editorial completeness — no new DEC.)*

The Hold types and the activity each blocks:

- **`admin`**: manual hold placed by an Operator. Blocks all activity scoped to the entity.
- **`kyc`**: placed automatically when KYC is required and not yet verified, lifted automatically when verified. Blocks purchases on the affected scope until resolved.
- **`payment`**: placed automatically on failed payments or invalid payment methods. Blocks purchases until payment resolves.
- **`fraud`**: placed when suspicious activity is detected. Blocks all activity scoped to the entity.
- **`compliance`**: placed for regulatory issues. Blocks all activity scoped to the entity.
- **`credit`**: triggered by Module E when AR exceeds terms or a credit limit is breached. Blocks purchases and pauses fulfilment. Module E emits the triggering event; Module K records the Hold state.
- **`CHARGEBACK_REVIEW`** *(NewCo-specific — DEC-168; Customer-scope)*: placed by Module K on consuming Module E's `CustomerChargebackFlagged` (the automated Airwallex chargeback chain — **D21 KEPT**). Blocks new orders + new shipping; in-progress fulfilment unaffected. Also flags the Customer for fraud-pattern review. **Module K is the Hold registry-of-record; Module E does not create the Hold directly.** No-auto-lift — explicit operator lift even on a chargeback win (§4.8.1; Module E §6.2).
- **`STORAGE_PAYMENT_FAILED`** *(NewCo-specific — DEC-160; Customer-scope)*: placed by Module K on consuming Module E's `StoragePaymentFailed` (the INV3 storage-fee chain — **manual-first at launch, D4**: an operator records the outcome / operator-triggers the event). Blocks new orders + new shipping; in-progress fulfilment unaffected. Lifted **per-cycle** on Module E's `StoragePaymentSucceeded` (prior-cycle Holds remain until each is independently remediated — §15.8).

#### §4.8.1 Hold semantics

- **Multiple Holds may exist concurrently on one scope; any Hold blocks activity.** A Customer with both a `kyc` and a `payment` Hold cannot transact until both clear.
- **Customer-scope Holds cascade to all the Customer's Profiles.** A Customer-level `fraud` Hold blocks every Profile under that Customer.
- **Profile-scope Holds isolate to the affected Profile.** A `payment` Hold on Profile X does not affect the same Customer's Profile Y.
- **In-progress fulfilment is not affected by Holds.** Shipping orders already in picking or shipped state run to completion. Holds block the *creation of new* commercial commitment.
- **Holds carry full audit history.** Placement actor, reason, timestamp, and lift actor / timestamp are preserved.
- **Expired Holds auto-transition** by a daily scheduled job where the Hold type carries an explicit expiry.

**Sanctions/Hold uniformity principle (DEC-181) (FLOOR).** Every transaction-initiation surface reads sanctions state and Hold state at the moment of action. Transaction-initiation surfaces include (non-exhaustive at NewCo launch): order completion / purchase (Module S); gifting initiation (Module S — *deferred at launch, D5; the read-API simply isn't exercised — §17*); pickup handover (Module C); INV3 charge execution (Module E); refund routing (Module E); Cart Hold reservation at cart-add (Module S); SO `planned` transition (Module C); Voucher redemption-request (Module S); shipment-request initiation (Module C). Each downstream module's gate prose locally re-cites the principle; **this section is the source-of-truth wording.** Future transaction-initiation surfaces inherit by-property without explicit re-enumeration. **Module K exposes the uniform read-API (the `(sanctions_status, active-Hold-list)` tuple); the *enforcement* of the commercial action is the downstream module's — at order completion the enforcement point is Module S (S.15). Module K and Module A are sanctions-blind by design** (they provide / read the tuple; they do not block). *(Floor chain 2 — Phase C §6.)*

**Hold-lift discipline per Hold type (DEC-160).** Hold-lift discipline applies per type, not uniformly: **auto-lift permitted** on `kyc` (auto on KYC clear) and `payment` (auto on payment success); **explicit operator lift required** on `admin`, `fraud`, `compliance`, and `credit` Holds. The two finance-driven types (§4.8, §15.8) carry their own discipline: **`STORAGE_PAYMENT_FAILED`** lifts **per-cycle** on Module E's `StoragePaymentSucceeded` (that cycle only; prior-cycle Holds remain until each is independently remediated); **`CHARGEBACK_REVIEW`** is **no-auto-lift — explicit operator lift required even on a chargeback win** (the Hold + the `fraud_pattern_review` flag persist until operator review with an auditable reason — Module E §6.2). Each lift fires the corresponding `*Reactivated` event with the actor and reason recorded.

> **The Hold registry is trigger-agnostic (N2 — Phase C item D / §5-N2).** Module K records Hold types + state **regardless of what triggered the placement.** Two finance-driven Hold triggers consumed from Module E (§15.8) have *different automation depths at launch*, and the K-side registry is unchanged for both: **(i) the chargeback trigger is automated** — `CustomerChargebackFlagged` arrives from Module E's automated Airwallex chargeback chain (D21 KEPT — Paolo override, payment automation stays) and Module K creates the `CHARGEBACK_REVIEW` Hold; **(ii) the storage-payment trigger is manual-first** — the INV3 saved-card auto-escalation/dunning is deferred (D4), so at launch an operator records the storage-payment outcome and `StoragePaymentFailed` / `StoragePaymentSucceeded` arrive from Module E's manual-first path, placing / lifting the per-cycle storage-payment Hold. **The Hold types, the registry, and the manual-placement path are identical either way — only the upstream trigger's automation depth differs, and that depth is Module E's deferral, not a Module-K change.** *(One-line clarity; no behaviour change.)*

The Hold entity is the unified surface every downstream module reads when deciding whether to admit a commercial action.

---

## §5 Customer Segments

NewCo materialises three Customer segments — **Member**, **Waiting-list**, **Legacy** — computed from the Customer's Profile states across all Clubs. The strongest segment governs cross-cutting access logic (BMD §2.1).

The resolution rule:

- **Member**: at least one of the Customer's Profiles is in the `Active` state on any Club.
- **Waiting-list**: no `Active` Profile; at least one Profile in the `WaitingList` state.
- **Legacy**: no `Active` and no `WaitingList` Profile; at least one historically-active Profile (now `Cancelled` or `Lapsed`). The Customer was once a member; they retain the Legacy narrative on the Consumer Portal and remain reachable for re-activation campaigns.

A Customer who has never been a member, never been on a waiting list, and never been historically active sits outside the three segments — typically a fresh Discovery-only Customer. The segment is left unset for these Customers; they continue to transact on Discovery freely.

The segment is computed as a materialised view on Customer (materialised-vs-on-read is a build-team call per DEC-073 — not a spec cut). The view is refreshed on every Profile state transition that crosses a segment boundary; a `CustomerSegmentChanged` event fires on transition (§15). A daily background reconciliation job catches drift.

> **Marketing-consumer note (Q1 — KEPT).** *Member* is load-bearing for club eligibility and is unconditionally required. The Legacy / Waiting-list segments + their HubSpot **marketing-segment** sync ride on the same view; since launch marketing is **KEPT** (Q1), the `CustomerSegmentChanged` / `CustomerTransitionedToLegacy` marketing consumers are exercised at launch. (Were marketing ever deferred, these consumers would simply idle while the view still served eligibility — but it is not deferred.)

**v17's Crurated-Member tier model is NOT inherited.** The MEMBER ↔ IO ↔ LEGACY tier flow does not apply. NewCo's Profile state machine is generic across all Clubs (§4.2.1). Legacy is a *Customer segment* materialised from Profile states, not a Profile tier.

The 30-day grace mechanic (DEC-034) is a *Profile-state property* (§4.2): a Profile records the moment of lapse and re-activation within 30 days requires only the renewal payment. After 30 days the Profile transitions to terminal Cancelled. This is independent of and orthogonal to the Customer-segment view.

---

## §6 Originating Club Mechanic **(capture KEPT — seam-critical; Phase C item E)**

The **Originating Club** is a NewCo net-new mechanic — no v17 analog. Each Customer carries a reference to the *first* Club whose membership they were approved into across their lifetime. The reference is **set one-shot at first approval, immutable thereafter, and may stay unset indefinitely** for Customers who only ever buy on Discovery (DEC-040).

The Originating Club drives two things at NewCo:

1. **The 5% Originating-Club share** on Discovery sales (DEC-032). When a Customer with an Originating Club buys on Discovery, 5% of the Discovery price `P_d` accrues to the Originating Club's Producer at the standard settlement cadence. Customers without an Originating Club (no-OC allowance) do not generate this accrual.
2. **The "you joined via Club X" narrative** on the Consumer Portal.

The link target is the **Club** (not the Producer) — preserving multi-Club-per-Producer disambiguation. The eventual settlement recipient (Producer) is found by following the Club's operating-Producer link (itself immutable once set).

> **MVP seam (cut-sheet K.13 / Phase C item E — the capture is whole at launch; only the computation defers).** The **5% share computation/settlement defers with the settlement engine (D19 — operator-run first cycles, the first close months out)** — but the **lock must be captured at launch**: it is one-shot at first approval and **unreconstructable** later. So at launch: **Module K captures the OC link + fires `OriginatingClubLocked`** (the data); **Module S** emits `DiscoveryRevenueShareAccrued` at INV1 reading K's lock + Module A's lineage at that one-shot moment; **Module E** *records* the accrual (the seam) and *computes* the 5% + settles when the engine is built — **reading K's lock, not re-deriving it.** Cutting the capture would burn the bridge (P1). **Capture confirmed whole across the composed system** (Phase C item E).

#### §6.1 The lock event

A one-shot domain event, `OriginatingClubLocked`, fires at the moment of the Customer's *first* `MembershipApprovedByProducer` across any Club. The event is gated on the Customer's Originating Club link being currently unset; once the field is set, the event never fires again for that Customer. The payload carries the Customer, the Club that triggered the lock, the moment of the lock, and the triggering membership for audit.

The lock is **immutable in the application layer** — there is no admin-override surface at launch. (A future-DEC could introduce an override path — §17.)

#### §6.2 Edge cases

- **Closed Club resolves cleanly.** If the Originating Club later transitions to `closed`, settlement still resolves to the Producer because the Club's operating-Producer link is immutable once set. Settlement deref works regardless of the Club's lifecycle state.
- **No-OC allowance.** A Customer who buys on Discovery without ever being approved by a Club has no Originating Club; the 5% share simply does not accrue. Past Discovery purchases do not retroactively gain an Originating Club when the Customer is later approved — the share is determined at sale time against whatever Originating Club state exists then.
- **Multi-Club-per-Producer disambiguation.** When a Producer operates multiple Clubs, the Originating Club resolves to the *specific* Club that first approved the Customer, not to the aggregated Producer.
- **Re-application after rejection.** A Customer rejected by their first applied-to Club has no Originating Club set yet. If they later apply (and are approved) by a second Club, *that* second Club becomes the Originating Club — the first-approved-across-any-Club rule binds at approval, not at application.

---

## §7 Onboarding Flows

NewCo runs three Customer-onboarding flows at launch — direct registration, Club-link registration, and producer-initiated invitation. The B2B onboarding flow is dropped (DEC-068).

#### §7.1 Direct registration

1. The prospect submits email, password, name, and date of birth. **Registration is age-gated (FLOOR, BMD §2.8; BR-K-Identity-6): a prospect whose self-attested `date_of_birth` implies an age below the configured minimum at the registration date is rejected — no Customer record is created.** The minimum age is an **admin-configurable platform constant, default 18** (the EU alcohol-purchase baseline). At launch the check is self-attestation (the entered date of birth) plus the payment-method-bound minimum-age signal when a card is captured (step 7 / BMD §2.8) — **no physical-document age verification at launch**; per-shipping-jurisdiction higher floors (e.g. 21 for US destinations) are a downstream / post-launch refinement.
2. NewCo sends a verification email; the prospect clicks the link and confirms their email address.
3. Module K creates the Customer record, the Account record, and the Party record (with the Customer Party-type marker).
4. The prospect accepts the T&C and the Privacy Policy on the registration form (acceptance captured at Customer level with timestamps).
5. **Sanctions screening runs (§9) (FLOOR).** Synchronous via the screening-vendor adapter in the standard flow; **operator-run (manual-first) is acceptable at launch** (§9.5) — either way the screen must complete before the Customer can transact. If the screen passes, the Customer transitions `pending → active`. If it fails or lands `under_review`, onboarding pauses and Compliance reviews.
6. The Customer can now apply to one or more Clubs; each application creates a Profile in `Applied` state (or `WaitingList` if the target Club is at capacity — §13).
7. **At application, the Customer supplies a payment method + a charge-on-approval mandate** for the Hero Package fee — consent to be charged the fee **when (and if) the Producer approves** the application. **No funds are held** (this is a save-card-plus-mandate, like a subscription sign-up — *not* a held pre-authorisation, which would not survive an approval that arrives after arbitrary delay or a waitlist wait). The mandate is what lets approval be **atomic** with the charge (§4.2.1). At launch the mandate requires a **pull-capable instrument — a card (one-step authorize+capture on approval, DEC-101) or a SEPA Direct Debit mandate; a one-off bank/wire transfer is *not* a Hero Package payment method** (it cannot be auto-charged on approval), though bank transfer remains available for Discovery purchases (DEC-101 / DEC-159 unchanged). The exact instrument set is configurable within that rule (DEC-073) — MVP-DEC-016.

A direct-registration Customer has no Profile until they apply to a Club. They can buy on Discovery from registration onward (subject to the standard sanctions / KYC gates).

#### §7.2 Club-link registration

A Producer, marketing partner, or Operator shares a Club-specific registration link pre-bound to a specific Club. **The Club-link token is reusable and long-lived (it may onboard many prospects) and is operator-revocable — revocation disables further registrations through it; its exact lifetime / rotation mechanics are the dev team's (DEC-073).** The flow:

1. The prospect submits email, password, name, date of birth — the form auto-binds the resulting Profile to the linking Club and captures the payment method + charge-on-approval mandate for the Hero Package fee (as in §7.1 step 7).
2. Email verification, T&C / privacy acceptance, and synchronous sanctions screening run as in §7.1.
3. Module K creates the Customer + Account + Party + a Club-specific Profile in `Applied` state simultaneously.
4. The Profile follows the standard state machine (§4.2.1).

#### §7.3 Producer-initiated invitation

1. The Producer's invitation is recorded — **at launch the invitation is operator-driven via the Admin Panel** (the producer-facing invite UI is deferred, L-PP; the backend capability is unchanged — §3.2). **The invitation token is single-use (bound to one invitee email), carries an expiry, and is operator-revocable before acceptance; its exact mechanics are the dev team's (DEC-073).**
2. Module K records a `MembershipInvitationSent` event carrying the invitation identity, the inviting Club, the invitee email, the inviting actor, and the moment of sending. **The ERP email service delivers the invitation email** (token-bearing flow mail — §14.9.1); HubSpot consumes the event for CRM / lifecycle sync.
3. The invitee clicks the invitation link and lands on a pre-bound registration form (similar to §7.2 with the invitation context attached).
4. On registration, Module K creates the Customer + Account + Party + Profile (capturing the payment method + charge-on-approval mandate as in §7.1 step 7), fires `MembershipInvitationAccepted`, and the Profile follows the standard state machine. The invitation typically **pre-approves** the Profile, so the charge-on-approval mandate fires **~immediately** on acceptance — the Profile completes the atomic approve-and-charge (§4.2.1) and lands `Active` (it does not linger in `Approved`). If the immediate charge fails, the Profile stays `Applied` pending a remedied payment method (no seat consumed).

#### §7.4 First approval triggers Originating Club lock

In any of the three flows, the moment a Profile transitions to `Approved` for the first time across the Customer's lifetime, the `OriginatingClubLocked` event fires (§6) and the Customer's Originating Club is set to the approving Club. This is independent of which onboarding flow the Customer came through. Because approval is now the **atomic approve-and-charge** action (§4.2.1), the lock binds to a *successful* (paid) approval — a charge that fails at approval never reaches `Approved`, so it never locks an Originating Club.

#### §7.5 Cross-cuts

- **Sanctions screening synchronous** in standard onboarding (§9). A Customer record can exist with screening incomplete (DEC-071); the screening gate fires at **order completion**, not at Customer creation (§9.3).
- **Outbound email splits by purpose (§14.9.1 — MVP-DEC-035).** The onboarding flows' mail — registration verification, invitation emails, waitlist confirmations, the flow-completing approval / welcome confirmation — is operational, token-bearing mail: **ERP-sent through the single email service**, catalog-registered, consent-independent. HubSpot delivers **marketing / lifecycle** email only (including the welcome-*journey* nurture content), driven by Module K events and gated by marketing consent (§8.1). No module talks to the mail provider directly.
- **Welcome window.** A welcome window (e.g. free first months on a Club fee) can be applied at registration as a promotional incentive — a Module S concern at fee-collection time. Module K records the resulting Profile state without owning the welcome-window logic. *(The Club-Credit consequence of a partial fee — proportional scaling — is **deferred at launch**: K.18, §11.1.)*
- **Charge-on-approval mandate persists through waitlisting.** The payment method + mandate captured at application **hold no funds** and **persist** while a Profile sits in `Applied` or `WaitingList` — a waitlisted Profile carries the mandate unexercised until the Producer converts it (capacity opens, §13.5), at which point the conversion *is* the atomic approve-and-charge moment (§4.2.1). This is precisely why the design is a save-card-plus-mandate, not a held pre-authorisation (which would expire across an arbitrary approval delay or waitlist wait) — MVP-DEC-016.

---

## §8 Marketing Consent, Soft-delete, and Anonymisation

NewCo activates at launch what v17 left deferred — a four-state marketing-consent lifecycle and a soft-delete + anonymisation mechanic that supports GDPR right-to-erasure (DEC-026 / DEC-027).

#### §8.1 Marketing-consent lifecycle **(Q1 — KEPT)**

Marketing consent is **separate from** the T&C and Privacy Policy acceptance booleans on Customer (which cover transactional emails and basic operating consent). **NewCo runs outbound campaigns at launch (Q1), so the double-opt-in marketing-consent lifecycle ships.** It is a four-state lifecycle:

- **`none`**: default on Customer creation. No marketing communication.
- **`requested`**: the Customer opted in (a marketing-consent checkbox at registration, an opt-in form, or an admin-recorded opt-in). Module K sends a confirmation email (via the ERP email service — §14.9.1) with a confirmation link — the second leg of double opt-in (DEC-026).
- **`confirmed`**: the Customer clicks the confirmation link. Module K records the timestamp; HubSpot reads the consent state and starts sending marketing campaigns.
- **`revoked`**: the Customer opts out (unsubscribe link, preferences page, or admin-recorded right-to-object). Module K records the revocation timestamp; HubSpot stops sending marketing.

The four states form a directed flow `none → requested → confirmed → revoked`; re-opt-in after revocation cycles back to `requested`; each transition is timestamped for audit. **(Confirm single-vs-double opt-in with legal at build time — cut-sheet K.8.)**

**Transactional emails are independent.** Order confirmations, invoices, fulfilment notifications, membership-fee reminders are governed by the T&C / Privacy acceptance on Customer (single-opt-in, captured at registration), not by marketing-consent state. Revoking marketing consent does *not* opt the Customer out of transactional emails. *(Operational mail is ERP-sent through the email service — §14.9.1; marketing consent gates only the HubSpot marketing / lifecycle class.)*

#### §8.2 Soft-delete and anonymisation **(FLOOR — GDPR right-to-erasure; Phase C floor chain 6)**

A Customer who exercises their GDPR right-to-erasure has their PII overwritten — but their transactional history is preserved (the 10-year retention window required by financial-record-keeping law). NewCo's launch flow:

1. The right-to-erasure request is verified by Compliance (typically Customer Care + Compliance Reviewer in tandem).
2. On approval, Module K overwrites the Customer's PII fields (name, email, phone, date of birth) with deterministic placeholders. Address records scoped to this Customer have their personal fields overwritten in the same operation.
3. Module K records the moment and the fact of anonymisation; the Customer's status is typically already `closed` by this point but the two are orthogonal.
4. Transactional history (Profile / Order / Voucher / Invoice rows) survives keyed by the (now-anonymised) Customer for the 10-year retention window. The data remains queryable for accounting and audit; only the PII is severed.
5. Vouchers owned by an anonymised Customer remain valid (right to fulfilment is preserved); the customer-identity link is the opaque anonymised identifier.
6. HubSpot's full customer-data sync removes the Customer's PII from HubSpot in the same operation.

**Closed and anonymised are orthogonal.** A `closed` Customer can be un-anonymised; an `anonymised` Customer is typically also `closed` but the inverse is not required.

**GDPR right-to-erasure × active Hold edge (DEC-027).** Anonymisation precedence with an active Hold is **per-Hold-type and case-by-case**. Across the **eight** Hold types (§4.8), **exactly one blocks: the `compliance` Hold.** A `compliance` Hold **blocks** anonymisation until it is lifted, and the Customer is informed of the regulatory delay — `compliance` is the regulatory-record-retention type, and **it is where a sanctions / OFAC / UIF screening finding is recorded (there is no separate `sanctions` Hold type — the sanctions lifecycle lives on `Customer.sanctions_status`, §9.2, not as a Hold).** The **seven** other types — `payment`, `fraud`, `kyc`, `admin`, `credit`, and the two finance-driven types `CHARGEBACK_REVIEW` (DEC-168) and `STORAGE_PAYMENT_FAILED` (DEC-160) — are **non-regulatory and do not block** anonymisation: anonymisation proceeds and Hold metadata anonymises alongside Customer PII (the structural Hold flag is preserved as boolean for blocking-state continuity even after handle removal). The Compliance × Legal coordination playbook for the regulatory-Hold (`compliance`) case is operating-manual scope.

**10-year retention** is enforced by separate operational policy on the financial-record side (Module E concern). *(Floor chain 6: K's erasure + retention ∧ E's 10-yr archival + post-sync immutability.)*

---

## §9 KYC and Sanctions Screening **(FLOOR — Phase C floor chain 2)**

NewCo separates two screening processes that v17 conflated: **KYC** (identity verification) and **sanctions screening** (a separate compliance check against EU + UIF + OFAC sanctions lists). Each runs on its own lifecycle on Customer (DEC-071). Both are **named un-cuttable** (plan §3); neither defers or simplifies.

#### §9.1 KYC lifecycle

A four-state lifecycle on Customer:

- **`not_required`**: default. No threshold crossed that requires identity verification.
- **`pending`**: KYC required (administratively flagged or threshold-triggered) but not completed. A `kyc` Hold is automatically placed; purchases are blocked until verification.
- **`verified`**: identity verification cleared. The `kyc` Hold is automatically lifted.
- **`rejected`**: identity verification failed. The `kyc` Hold remains; Compliance reviews case-by-case.

A Customer's `kyc_required` flag is administratively set; setting it transitions KYC state `not_required → pending`.

**Enhanced-KYC trigger (DEC-035) (FLOOR).** When a Customer crosses €10,000 in a single transaction or €50,000 cumulative annual purchases, the Customer is flagged for enhanced review with a timestamp. Detection runs **both** as a periodic background job **and** as a trigger at order completion. The enhanced-KYC workflow itself (Compliance review, optional document-based verification) is handled operationally at launch — there is no separate enhanced-KYC state machine beyond the trigger flag + timestamp. (A richer document-driven workflow is a future-DEC item — §17.)

**Launch delivery (MVP-DEC-034).** The automated threshold scanner's delivery is time-boxed to **before Module S go-live** — pre-S there are no orders, so no threshold can trip. Until it lands, the compensating manual control is a periodic Compliance review of trailing-12-month gross-EUR per Customer (aggregation semantics per MVP-DEC-014: gross incl. VAT/shipping, EUR at order time, rolling 12 months). The trigger specification above is unchanged and re-arms at Module S integration (AC-K-J-7a).

#### §9.2 Sanctions screening lifecycle

Sanctions screening covers EU + UIF + OFAC (DEC-030 + DEC-041) at launch. It runs at onboarding and on a 12-month re-screen cadence. The lifecycle on Customer:

- **`pending`**: screening not yet completed (in-flight onboarding, admin-imported records, pre-screening states).
- **`passed`**: cleared all screened lists at the most recent screen; the Customer can transact.
- **`failed`**: matched a sanctions list. Module K records the match metadata; the Customer cannot transact; Compliance reviews case-by-case.
- **`under_review`**: a possible match requiring manual review. The Customer cannot transact until review concludes (`passed` or `failed`).

Module K records the moment of the last screening and the next scheduled re-screen. The 12-month re-screen cadence runs as a daily background job that picks up due Customers, runs the screening via the screening-vendor adapter, and updates the state. `CustomerRescreeningPassed` / `CustomerRescreeningFailed` fire on completion.

**Trigger-event detection between 12-month cycles (DEC-030).** Beyond the cadence, between-cycle re-screening triggers operate via two paths: **(i) AML-threshold detection** per DEC-035 — daily scan of cumulative annual totals; a €10k-single / €50k-cumulative breach fires the lightweight DEC-030 sanctions re-check plus the enhanced-KYC review-queue entry; **(ii) Compliance ad-hoc trigger** via Admin Panel for case-by-case re-screening. **Country-change automated detection is NOT enabled at launch** (signal-to-noise; carried to roadmap — §17). Sanctions screening at the **payment moment** is independent and handled at the payment-rail layer (Airwallex).

#### §9.3 Screening as a business gate at order completion **(the single enforcement point)**

The BMD-locked policy (DEC-030 + DEC-041) is that sanctions screening must complete before a Customer can purchase. NewCo enforces this as a **business gate at order completion**, not as a Customer-creation prerequisite (DEC-071). A Customer record can exist with `sanctions_status = pending`; the gate fires when the Customer attempts to **complete an order** — **Module S (S.15) checks `sanctions_status = passed` as a precondition and rejects with a screening-required signal otherwise.**

NewCo's standard onboarding runs sanctions screening synchronously, so most Customers transition `pending → passed` before they ever hit the gate. Customers whose screening is in-flight, missing, or under review can exist as records and progress through the rest of onboarding; **the order-completion gate is the single enforcement point.** *(Floor chain 2: K screens + maintains state → S enforces at order completion → C OFAC at destinations → E sanctions/Hold re-read at charge. Module K + Module A are sanctions-blind by design — K exposes the read-API tuple, the downstream surface enforces.)*

#### §9.4 Sanctions and KYC are independent

A Customer with `kyc_status = verified` and `sanctions_status = pending` is blocked from purchasing (sanctions gate); a Customer with `sanctions_status = passed` and `kyc_status = pending` is also blocked (KYC `kyc` Hold). Both must clear independently. The two lifecycles emit independent events (§15).

#### §9.5 The screen vs the integration — launch posture **(the FLOOR is the screen + the gate, not the integration)**

The screening **outcome** (the KYC / `sanctions_status` state) and the **order-completion gate** (§9.3) are the FLOOR and are unchanged. The **screening-vendor integration** is the dev team's call (DEC-073) and may be **manual-first at launch** — an operator runs the check via the provider and records the resulting state into Module K — with the automated synchronous adapter as the post-launch path. Module K records the state identically whether the screen was operator-run or adapter-run; the gate (no order completion until `sanctions_status = passed`, and KYC cleared where required) is the same either way. **The screen is non-negotiable; only the integration is deferrable.** *(Same manual-first-vs-automated distinction as the N2 Hold-trigger note, §4.8.1: the automation depth differs, the Module-K-side state + gate behaviour does not. Building or deferring the vendor adapter therefore does not block Module K's completion or its acceptance — the acceptance criteria drive the lifecycles by setting state, not by calling a live vendor.)*

---

## §10 Suspension and Producer Offboarding

Module K's role in suspension and offboarding is **state changes and events**. The financial mechanics — Club Credit conversion at offboarding, refund execution, settlement adjustment — live downstream in Module S and Module E.

#### §10.1 Suspension model

NewCo inherits a two-level suspension model:

- **Profile-level suspension by default** for per-Club issues (a Producer-initiated kick on Profile X does not affect the Customer's other Profiles; admin/credit actions targeting Profile X are isolated).
- **Customer-level suspension** for cross-cutting reasons (KYC failure, fraud, compliance) — blocks all the Customer's Profiles via a Customer-scoped Hold.

A Profile-level Hold blocks only that Profile's eligibility; other active Profiles remain usable. A Customer-level Hold blocks every Profile.

**Effect on existing state during suspension.** Active vouchers remain ACTIVE (not frozen, not cancelled). Pending orders remain pending unless an Operator explicitly cancels them. Allocation reservations remain reserved unless explicitly released. **Club Credit balance is frozen — no new accrual, no redemption — while the Profile is suspended.**

**In-progress fulfilment is not affected by suspension.** Shipping orders already in picking or shipped state run to completion. Suspension blocks the *creation of new* shipping orders and the *opening of new* commercial commitment.

**Reactivation paths.** A Profile or Customer transitions out of suspension when the triggering Hold is lifted. Each lift fires the corresponding `*Reactivated` event; on `Suspended → Active`, Club Credit becomes mutable again. A suspended Profile **retains its Hero Package seat throughout suspension** (suspension preserves state), so `Suspended → Active` is **never capacity-re-checked or blocked by the cap** — only an explicit Cancellation frees the seat (§13.1; MVP-DEC-017).

#### §10.2 Producer offboarding

When a Producer ends their relationship with NewCo, the offboarding cascades through Module K and downstream modules:

1. **Producer retirement is initiated by an operator and requires dual-control approval before it commits.** Because offboarding is the highest-blast-radius action in Module K — it cascades to `sunset` every Club the Producer operates, winds down all member Profiles, and triggers downstream Club-Credit conversion and refunds (steps 2–6) — the `active → retired` transition is **approval-gated**: a **distinct second operator** (not the initiator) must approve it, and the action is audited (initiator, approver, timestamp, reason). This reuses the platform's configurable separation-of-duties primitive (MVP-DEC-007) — **not** the Producer *content* Creator → Reviewer → Approver lane (§4.4), which governs data-quality review of producer content, a different concern from authorising a commercial-relationship wind-down; the offboarding gate is a two-actor authorisation (initiator ≠ approver) with no data-quality "review" step. The role-count / whether a review step is interposed is admin-configurable, but the no-self-approval floor holds at any depth. Only on approval does the Producer transition to `retired` and `ProducerRetired` fire. *(BR-K-Producer-6.)*
2. **Module K cascades to the affected Producer's Clubs.** Each Club operated by the retired Producer transitions to `sunset`; `ClubSunset` fires per Club. Sunset blocks new memberships and new offers but preserves existing Profiles until membership periods end.
3. **Per-Profile state changes.** For each Profile under a sunsetting Club, Module K transitions the Profile per its remaining lifecycle. Existing members retain access until the validity period ends; non-renewing Profiles transition `Lapsed → Cancelled` per the standard 30-day grace. Producer-initiated cancellations (faster wind-down) are recorded with a Producer-initiated cancellation reason.
4. **Cross-module cascade for Club Credit conversion.** Module S consumes the events and handles Club Credit conversion to Discovery store credit at face value with 12-month validity (DEC-043 — **KEEP-lean**). **Module K's role ends at the upstream per-Profile cancellation signal.**
5. **Module E records the financial events.** Per DEC-072, Module E records the events of Club Credit conversion and any associated refund execution; Xero decides GL treatment.
6. **Club closure.** Once all Profiles under the sunsetting Club have resolved, the Operator transitions the Club `sunset → closed` and `ClubClosed` fires.

Module K's responsibility stays crisp: parties, state, events. Downstream modules consume and do the money work. **(Cut-sheet K.20: rare at launch — no producer offboards in month 1 — but mostly state + event emission, already lean; KEEP.)**

#### §10.3 Sunset preserves voucher integrity

Existing vouchers under a sunsetting Club remain valid. The Customer's right to fulfilment is preserved across Producer offboarding — the wine has been bought and paid for; offboarding is a commercial-relationship termination, not a Customer-side cancellation. Vouchers run to redemption or natural expiry per their own lifecycle (Module B / C concerns).

---

## §11 Club Credits **(core KEPT; K.18 / K.19 peripherals deferred — Phase C item D)**

A **Club Credit** is a per-Profile **prepayment instrument** issued at membership-fee payment. The Customer paid a portion of their fee that goes into a Club Credit balance, redeemable against Club-scoped Offers later in the year. **Club Credit *is* how the Hero Package fee converts to spendable value — a core club value proposition (BMD: fee → Club Credit → redeem).** The entity + auto-issuance + the one-active-per-Profile invariant + **partial-redemption/carry-forward (K.17)** are **KEPT**; the two *peripheral* mechanics K.18 (welcome-window scaling) and K.19 (operator manual issuance) **defer** with retained seams (the substantive call was taken in the Module S cut-sheet — Q2; reconciled here).

**Producer-portal read scope (L-PP).** Producer-facing Club Credit visibility is the **aggregate** per BMD §3.11 — per-Profile credit **issuance count** + **total outstanding balance per currency** for the Club. **The per-state breakdown — the `redeemed` (applied) and `forfeited` amounts, and any internal available/outstanding split — is operator / ERP-only and does NOT surface on the Producer Portal at launch:** "club credit outstanding" is the contractually-scoped KPI (BMD §3.11 aggregate-KPI list; DEC-162 disclosure discipline). The full per-state detail stays available to operators in the Admin Panel / ERP. (MVP-DEC-026.)

Each Club Credit carries:

- **Profile reference**: the Profile that holds the credit. One Customer may hold multiple Club Credits across their Profiles (one per Profile per cycle), but only **one active Club Credit per Profile** at any moment.
- **Amount and currency**: the credit balance and its currency. Currency is set at issuance to the Account's currency at the moment of fee payment and is immutable across the credit's lifetime.
- **Status lifecycle**: `active → redeemed | forfeited`, with a path back to `active` from `redeemed` only on order cancellation within the cancellation window (a downstream effect, not a Club Credit primitive).
- **Validity window**: a `valid_from` and a `valid_to`. The default `valid_to` is December 31 of the issuance year unless the Club's credit policy specifies otherwise.
- **Remaining balance** **(K.17 — KEPT)**: tracks balance after partial redemption. Full redemption against a package of equal or higher value is the norm (the Customer pays the difference if the package exceeds the credit). **Partial redemption (credit exceeds package value) leaves a remaining balance that carries forward for future purchases until forfeiture** — this is how annual club credit works (members spend it across several purchases through the year); **load-bearing customer value, ratified KEEP (S Q2), now exercised at launch.**

#### §11.1 Issuance

Club Credits are auto-generated when Module K consumes the `MembershipFeePaid` event (**Module S emits; Module E records the financial event; Module K consumes** — DEC-173, no separate INV0 per DEC-157) and the Profile's Club has `generates_credit = true`. Issuance is **gated on payment-provider-confirmed payment success** — Module K never auto-generates a Club Credit before the Hero Package fee capture is confirmed. With the corrected membership flow (§4.2.1), that capture occurs at the **producer-approval moment** (joining) or the **renewal cycle** (renewal); the trigger is unchanged — it stays pinned to actual cash receipt. **(Core — KEPT.)**

> **K.18 — welcome-window proportional scaling — DEFERRED (Phase C item D; decided S Q2).** In v1.1, when the membership-fee payment reports a fee paid less than the full fee (a welcome window applied), the credit amount scales proportionally (`credit = policy_amount × (fee_paid / full_fee)`). **At launch this does not fire: launch is full-fee → full-credit (no prorated welcome windows).** **Seam (P1):** the issuance hook + the `policy × (fee_paid/full_fee)` formula are **retained in Module K** — when welcome-window scaling restores post-launch, the path activates with no rework. Low customer-value risk (it is about *prorating*, not *losing* credit). **Roadmap:** [`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md).

> **K.19 — operator manual Club-Credit issuance — DEFERRED (Phase C item D; decided S Q2).** In v1.1, an Operator may manually create a Club Credit on a Profile (goodwill / make-right), subject to the one-active-per-Profile + issuance-currency invariants, with the actor + reason audited. **At launch, goodwill routes through the single REFUND_COMPENSATION coupon instrument (Module S, S.16)** — partly redundant with the voucher-goodwill path — and the operator manual Club-Credit-create path is **not exercised at launch.** **Seam (P1):** Module K **retains the manual-create path** (the entity, the invariants, the audit trail); Module S defers *exercising* it, routing launch goodwill through the one coupon instrument. When manual Club-Credit issuance restores, the path activates with no rework. **Roadmap:** as above.

#### §11.2 Redemption

A Club Credit redeems only against Offers tied to its **issuing Club** — `credit.profile.club_id ∈ offer.club_ids`. Cross-club redemption is not allowed. Currency-match is also required.

Redemption mechanics live in Module S (checkout-side); Module K provides the eligibility data. Mutual-exclusivity with commercial coupons (only one applies per checkout) is enforced at Module S price resolution.

**Redemption is gated by Holds.** Even when a Club Credit is `active`, an active Customer- or Profile-scoped Hold blocks Module S from applying the credit at price resolution. **Issuance is *not* blocked by Holds** (the Customer paid the fee; the entitlement is recorded), but **redemption *is*** — the asymmetry preserves the entitlement audit trail while gating cash-equivalent use behind Hold clearance.

#### §11.3 Forfeiture

A Club Credit transitions `active → forfeited` on any of:

- **Year-end lapse**: `valid_to` passes without full redemption. A daily background job auto-transitions credits past their `valid_to`. **This runs irrespective of Profile suspension: suspension freezes the credit *balance* (no accrual, no redemption — §10.1) but does *not* pause the `valid_to` clock, so a credit reaching `valid_to` while the Profile is suspended is forfeited** (the member's self-cure is to lift the Hold and redeem before `valid_to`). A suspension-aware clock pause / extension is a post-launch option, folded into the deferred credit-validity review (MVP-DEC-013 / MVP-DEC-022).
- **Renewal-triggered replacement**: at renewal, any remaining active credit from the prior period is auto-forfeited and a new credit for the new period replaces it (forfeit-before-issue, sequenced within the renewal-time `MembershipFeePaid` consumption).
- **Profile cancellation**: when a Profile transitions to terminal Cancelled, any active Club Credit is forfeited (with optional grace period configured per Club).
- **Club closure**: when the issuing Club transitions to `closed` mid-credit-life, the credit is converted to Discovery store credit at face value with 12-month validity (**DEC-043 — KEEP-lean**). The conversion is owned by Module S; Module K's role ends at the upstream cancellation / closure signal.

**Forfeiture event.** Forfeiture is recorded by Module E (`ClubCreditForfeited`); Module K consumes the event and applies the state change. At most one forfeiture event per Club Credit lifetime — once forfeited, the credit is terminal.

#### §11.4 Module K records the state change; Module E + Module S emit the events (DEC-174 three-actor split)

Module K does NOT emit Club Credit lifecycle events. **The lifecycle is a three-actor split (DEC-166 + DEC-174, reconciled at MVP-DEC-018): Module E emits the financial accrual / reversal events — `ClubCreditAccrued` (creation), `ClubCreditRestored` (cancellation-window restoration, §11.1), `ClubCreditForfeited` (lapse / replacement / cancellation, §11.3); Module S emits the customer-facing application events — `ClubCreditAutoApplied` / `ClubCreditRemovedByCustomer` at checkout-render / customer action (DEC-111); Module K consumes from BOTH and records the resulting state on its Club Credit entity.** This is the pattern across financial events (the `MembershipFeePaid` consumer pattern is the same): the emitter records the event; Module K records the state change to its own entity. Per DEC-072, Xero decides GL treatment. *(Phase C item D: the three-way seam **entity (K) ↔ accrual / forfeiture events (E) ↔ application / redemption (S)** holds; the deferred K.18/K.19 paths simply don't fire, so no accrual fires for them — no Module-E cut.)*

#### §11.5 Commercial coupons

Commercial coupons (promotional discounts owned by Module S — incl. the REFUND_COMPENSATION goodwill coupon, S.16) are distinct from Club Credits. Module K records coupon-redemption history on the Customer / Profile for audit and analytics only; the coupon entity, lifecycle, and redemption mechanics live in Module S. Mutual exclusivity with Club Credit at checkout is a Module S enforcement.

---

## §12 GDPR and Privacy Compliance **(FLOOR — floor chain 6)**

NewCo's Module K is the system of record for Customer PII, consent, and the GDPR-grounded data-subject-rights surface. The launch posture:

- **Data retention.** Customer data is retained per NewCo's Privacy Policy. Retention periods for Profile, Order, Voucher, Invoice, and audit-log records are governed by the 10-year financial-record retention window (operationally enforced on the Module E side).
- **Right to erasure** (right to be forgotten). Verified by Compliance; executed via the soft-delete + anonymisation mechanic in §8.2. Personal data overwritten with deterministic placeholders; transactional history preserved keyed by the anonymised identifier; vouchers remain valid; HubSpot PII removed in the same operation.
- **Right of access / data portability.** A Customer can request export of their personal data and transactional history in a standard, machine-readable format. **The right is operator-executed at launch** — Compliance + Customer Care verify the request and trigger the export; it is **not customer self-serve and not modelled as a state machine.** An automated structured-export endpoint may serve it (the format — JSON or equivalent — and the endpoint mechanism are the dev team's call, DEC-073). **At launch the automated export covers Module-K-owned PII** (Customer, Account, Profile, consent, Holds, Addresses, Club Credits, audit); the **cross-module transactional bundle** (Orders / Invoices — Module S / E; serialized bottles — Module B / C; shipments — Module C) is **phased as a dependency** and assembled by the manual ops process until it lands (no data debt — the Module-K slice is exactly what the phased export composes with). A **`closed` (not-yet-anonymised) Customer retains the full right** (PII intact — BR-K-Customer-2); an **anonymised Customer has no personal data to return** — the right is extinguished by the erasure itself (§8.2), and the surviving transactional history is keyed only to the opaque anonymised identifier (MVP-DEC-021).
- **Right to object** (marketing consent revocation). Executed via the marketing-consent revoke transition (§8.1).
- **Consent management.** T&C and Privacy Policy acceptance captured at Customer level with timestamps (a hard gate on activation). Marketing consent is the separate four-state lifecycle (§8.1). Cookie consent and portal preferences are managed at registration and via account settings.
- **Data minimisation.** NewCo collects only the PII necessary for transaction, regulatory compliance (KYC + sanctions), and the customer experience. The B2B-segment attributes that v17 carried are dropped (DEC-068).

Re-acceptance on Privacy-Policy or T&C version updates is operationally determined; Module K does not enumerate a re-acceptance state machine at launch (§17).

---

## §13 Hero Package Capacity Invariant **(KEEP — D8 core; reads Module A `qty`)**

Each Producer-operated Club runs an annual **Hero Package** offering — the structural primitive of the membership purchase. The number of Hero Packages a Producer commits to source for the year *is* the maximum number of active members the Club can have at any moment that year. Module K enforces this invariant at every membership approval (BMD §2.3 / §2.5 / DEC-007). **It is the membership no-oversell guard — the eligibility analogue of the inventory floor.**

#### §13.1 The invariant

For any given Club: the count of **seat-occupying Profiles ≤ the current quantity of that Club's Hero Package allocation.** The **seat-occupying set is `Active` + `Suspended`** — a `Suspended` Profile (a temporary Hold, §10.1) **keeps its Hero Package seat** exactly as an `Active` one does. This is required for invariant soundness: freeing a seat on suspension would let a returning member exceed the cap or be evicted by a temporary Hold, contradicting §10.1 (suspension preserves state) and the never-evict-a-member rule (MVP-DEC-011). Accordingly, **`Suspended → Active` is never capacity-re-checked and is never blocked by the cap.** Seats are **freed only by `Lapsed`, `Cancelled`, or `Inactive`** (an explicit Cancellation being the only deliberate way to free one); `Applied`, `WaitingList`, and `Rejected` Profiles **never** hold a seat.

The cap gates every transition that **newly consumes** a seat — membership approval, waitlist conversion, and re-activation from `Lapsed` / `Cancelled` (where the seat was freed). **Enforcement is at the approval moment — which, with the corrected membership flow (§4.2.1 / MVP-DEC-016), is the atomic approve = charge = activation instant.** Because there is no longer an "approved-but-unpaid" gap, *reserving a seat at approval* and *counting only seat-occupying members* are the **same instant**; a charge that fails at approval consumes **no** seat. A **renewal that continues an in-good-standing `Active` membership into a new period does NOT newly consume a seat** (it was never freed), so it is **not cap-gated** — it is admitted as a continuation, protected by the renewal-boundary floor (§13.4). **The cap therefore never evicts a renewing member.**

A lapsing or cancelled Profile frees its seat; a re-activation within the 30-day grace re-consumes a seat (subject to the cap at re-activation time). **(Seat-occupying set + at-approval enforcement: MVP-DEC-017.)**

#### §13.2 Single source of truth — the Hero Package allocation

Club capacity is **not stored as a Club attribute.** The capacity number lives on the **Hero Package's underlying Allocation owned by Module A** — the year's Hero Package Offer references an Allocation; the Allocation's quantity is the cap. Module K's responsibility is to *enforce* the invariant; **Module A owns the storage of the cap.** *(Cross-module dependency — Phase C item G / floor chain 1: the Hero Package allocation primitive must survive the Module A triage; A is KEEP, the Hero-Package-backing allocation `qty` is preserved. No orphan.)*

A consequence: a producer changing their Hero Package count is a Module A operation (the Allocation's quantity is adjusted), and Module K consumes the resulting capacity-adjustment signal.

**Interim representation pre-Module-A (MVP-DEC-034 / CML-79).** Until Module A lands there is no Allocation `qty` to read. The build's independent Club-capacity attribute is accepted as **interim** authority for that window only, and MUST convert to the reconciling read-model (or live read) of A's `qty` at Module-A integration — where AC-K-XM-18 re-arms and is re-verified. Module A remains the source of truth; this is a timing accommodation, not an ownership flip (MVP-DEC-020 stands).

#### §13.3 Hero Package shape is irrelevant to the invariant

A Hero Package is a Module S Offer-level **designation** — a role attached to an Offer that backs the Club's annual membership purchase — not a structural type at PIM. It can be backed by any standard PIM artefact: a single-PR Intrinsic SKU, a Composite SKU, or any future SKU shape. The capacity arithmetic binds to the underlying Allocation's quantity regardless of which SKU shape backs the Hero Package Offer.

#### §13.4 Mid-year capacity mutability (DEC-069) **(Q5 — KEEP)**

Hero Package capacity is **not frozen at year-start.** Producers may scale capacity during the Club year:

- **Increase**: a Producer launches Year N with 50 Hero Packages, sells out, and adds 20 more. The Allocation's quantity is adjusted to 70 via the Module A capacity-adjustment surface (operator-driven at launch, §3.2); Module K consumes the adjustment event and refreshes the eligibility surface for waitlist conversions.
- **Decrease constraint**: a Producer cannot reduce the quantity below the current count of **seat-occupying (`Active` + `Suspended`) Profiles** (would orphan members — including suspended members, who retain their seats, §13.1). Reductions above the current count are admitted but rare. Tighter constraints are future-flexibility (§17).
- **Reduction at the renewal boundary (drawdown by attrition).** The decrease constraint holds across the year boundary: a member entitled to renew (an in-good-standing `Active` Profile, including one within its renewal / 30-day-grace window) **counts toward the floor** when the Producer sets the new club-year capacity — so the new period's `qty` cannot be set below the renewing cohort, and a continuing renewal is **grandfathered** (admitted regardless of the cap — §13.1). To reach a *smaller* club a Producer sets a lower **target** and lets natural attrition (declined renewals, lapse, cancellation) draw the count down: new approvals and waitlist conversions stay blocked while `Active` = `qty`, and `qty` may then be reduced step-wise to track attrition (never below current `Active`). **The invariant holds at every step.** A renewal is **never auto-cancelled to force a reduction** — that would contradict the auto-renew + stable-price membership model (DEC-007 / DEC-033).

**(Q5 ratified KEEP — lets a sold-out Club add capacity; cheap, real commercial value; sophisticated waitlist mechanics already deferred.)**

#### §13.5 Waitlist conversion on capacity increase

When capacity increases by N, the Producer may approve up to N waitlisted applicants:

- **Priority order is producer-discretionary at launch.** The Producer reviews the waitlist and approves whichever applicants they choose — there is no automatic FIFO conversion at launch. **(Approve/decline incl. waitlist approval is the one retained producer write — L-PP, Q4, §3.2.)**
- More sophisticated waitlist mechanics — automatic FIFO conversion, priority-by-application-date, producer-defined ranking — are deferred (§17).

The Producer Portal renders the invariant at the approval surface; the UX is post-launch Producer-Portal scope. Module K's responsibility ends at the invariant check and the events it consumes / emits.

---

## §14 Business Rules and Invariants

The Module K business rules cluster into nine groups. The naming cascade renames the Producer-link prose (BR-K-Producer-2 / BR-K-Producer-4: `Wine Master → Product Master`, wine-display alias retained); all other rules are unchanged at the MVP.

### §14.1 Identity and uniqueness

**BR-K-Identity-1. Unique email.** Email is globally unique across all Customers at NewCo. An email change commits only after the **new** address completes a blocking click-verification; the **old** address receives a non-blocking security notice (with a revoke link), not a second blocking confirmation — so a lost old mailbox can never strand the change. The Customer stays `active` and transacts on the existing verified email throughout (the new address becomes contact-of-record only on its verification); an email change is a field-scoped re-verification, never a revert to `pending` (MVP-DEC-021).

**BR-K-Identity-2. One Customer, multiple Profiles.** A single Customer may hold multiple `Active` Profiles across different Clubs. One Profile per Customer per Club (uniqueness on the Customer-Club pair).

**BR-K-Identity-3. T&C and Privacy Policy at Customer level.** Acceptance is captured on Customer, applies across all the Customer's Profiles, and is a hard gate on the `pending → active` transition (alongside email verification and KYC clearance when required).

**BR-K-Identity-4. Active Profile requirement to transact on Clubs.** A Customer must have at least one `Active` Profile to transact on a Club's offers. The check is enforced at order-completion time (Module S concern). Discovery purchases are independent of Profile state; a Customer with no Profiles can transact on Discovery freely subject to the standard sanctions / KYC gates.

**BR-K-Identity-5. Immutable party-type marker.** The Party-type marker (Customer / Supplier / dormant Third-Party Owner) is immutable once set. A Customer cannot become a Supplier or vice versa.

**BR-K-Identity-6. Minimum-age gate at registration (FLOOR).** Registration is blocked for any prospect whose self-attested `date_of_birth` implies an age below the configured minimum at the registration date; no Customer record is created. The minimum age is an admin-configurable platform constant (default 18 — the alcohol-purchase baseline across the EU launch markets; cf. the KYC-threshold constants, MVP-DEC-014), not hard-coded. At launch the check is self-attestation plus the payment-method-bound minimum-age signal — no physical-document verification (BMD §2.8); the gate applies to all three onboarding flows (§7). Per-shipping-jurisdiction higher floors (e.g. 21 for US destinations) are a post-launch refinement. (MVP-DEC-022.)

### §14.2 Customer state

**BR-K-Customer-1. Customer status lifecycle.** A Customer follows `pending → active → suspended → closed`. Suspension is explicit (manual or via Hold) — not automatically driven by Profile state changes.

**BR-K-Customer-2. Closed and anonymised are orthogonal.** A `closed` Customer remains queryable in admin until anonymised. An anonymised Customer is queryable only as an opaque identifier. The two state changes are independent operations.

**BR-K-Customer-3. A Customer whose last `Active` Profile leaves is flagged for review.** The flag fires on the transition from one-or-more `Active` Profiles to **zero** (the last `Active` Profile moving to `Lapsed` / `Cancelled` / `Inactive`); it does **not** fire for a Customer who was never active — a pure-Discovery Customer with no Profile, or a Waiting-list-only applicant (both are normal states, §5). The Customer is not auto-suspended; the flag enables ops follow-up and **auto-clears when an `Active` Profile returns**. The flag is informational at launch — downstream re-activation outreach rides the existing `CustomerTransitionedToLegacy` / `CustomerSegmentChanged` events (§5, §15), not the flag itself (MVP-DEC-021). Auto-suspension on zero active Profiles is a future-flexibility item.

### §14.3 Profile state

**BR-K-Profile-1. Generic state machine across all Clubs.** The Profile state machine is the same shape for every Club — there is no Crurated-Member-specific tier flow.

**BR-K-Profile-2. Profile is never hard-deleted at launch.** Cancelled and Inactive are terminal soft-delete states preserving audit history.

**BR-K-Profile-3. 30-day grace on Lapsed.** A Lapsed Profile re-activates within 30 days on a successful renewal payment (no re-application required). After 30 days, the Profile transitions to terminal Cancelled.

**BR-K-Profile-4. Profile-state transitions emit lifecycle events.** Every state transition that crosses a Profile-status boundary fires a corresponding domain event consumed by downstream modules and HubSpot (§15).

**BR-K-Profile-5. Auto-renew default-inherits and is customer-toggleable.** A Profile's `auto_renew` flag default-inherits the Club's `renewal_policy.auto_renew` (§4.3) at creation; thereafter the Customer may self-toggle it via the Consumer Portal (turn auto-renewal off — and back on — before the renewal date; BMD §2.4 / B2) and an Operator may also set it. (MVP-DEC-022.)

### §14.4 Club state

**BR-K-Club-1. Producer association required at NewCo.** Every NewCo Club is associated with exactly one operating Producer; Club creation rejects a missing Producer association.

**BR-K-Club-2. Producer association immutable once set.** A Club's operating-Producer link does not change with Club lifecycle state. Settlement deref to Producer works regardless of Club state (`active`, `sunset`, `closed`).

**BR-K-Club-3. Club lifecycle.** A Club follows `active → sunset → closed`. Sunset blocks new memberships and new offers; closure is terminal once all members and obligations have resolved.

**BR-K-Club-4. Multi-Club-per-Producer admitted.** Multiple Clubs may share one operating Producer. The Originating Club mechanic resolves to the specific Club, not the aggregated Producer.

**BR-K-Club-5. Single-tier launch.** Every Club at NewCo launch is configured with a single tier; multi-tier activation post-launch is configuration only (no schema change).

**BR-K-Club-6. Registration flow is an entry channel, not an approval bypass.** A Club's `registration_flow` selects the onboarding entry channel only; producer approval (the atomic approve = charge = activation, §4.2.1) is mandatory for every value and no value auto-approves. Launch-live values: `application_with_approval` (open self-application, §7.1 — default), `invitation_only` (entry only via a producer / operator invitation, §7.3), `link_onboarding` (entry via a shared Club link, §7.2). `open` (auto-join without approval) is carried latent and not selectable at launch. The field subsumes the former `invite_only` boolean (`invite_only = true` ⟺ `invitation_only`); representation is the dev team's (DEC-073). (MVP-DEC-022.)

### §14.5 Producer state

**BR-K-Producer-1. Producer is not a Party subtype.** The Producer entity is standalone in Module K. Some Producers may never have a Party / Supplier record at all.

**BR-K-Producer-2. KYC clearance gates Product Master activation.** A **Product Master** *(wine-display alias: Wine Master)* cannot be activated unless its linked Producer is `active` and KYC-cleared (`verified` or `not_required`); KYC `pending` or `rejected` blocks. Producer KYC revocation does *not* deactivate existing active Product Masters; only new Product Master activations are blocked. *(Naming cascade — Phase C item A; §18. Cleared-state semantics per §4.4 — `not_required` clears the gate exactly as `verified`, aligning producer KYC with the Customer side.)*

**BR-K-Producer-3. No auto-link between Producer and Supplier.** Creating a Producer does not auto-create a Supplier; creating a Supplier does not auto-create a Producer. Linking is an explicit operator action.

**BR-K-Producer-4. Producer retirement preserves existing Product Masters.** When a Producer is retired, existing active **Product Masters** *(wine-display alias: Wine Masters)* under that Producer remain valid for current references; new Product Master activations are blocked. *(Naming cascade — §18.)*

**BR-K-Producer-5. Content edits re-arm review (review-freshness).** Editing the review-governed descriptive content of an `active` Producer (name, description, region, website) invalidates the prior approval and re-enters the Creator → Reviewer → Approver workflow; the edited content does not publish until a fresh review passes, while the Producer stays `active` serving the last-approved content meanwhile. This is the Module-0 review-freshness invariant (MVP-DEC-019) applied to the Producer; the review depth is admin-configurable (§4.4) and the re-arm representation is the dev team's (DEC-073). (MVP-DEC-022.)

**BR-K-Producer-6. Producer retirement requires dual-control approval.** The `active → retired` transition (Producer offboarding, §10.2) is approval-gated: a distinct second operator (not the initiator) must approve before the Producer transitions to `retired` and `ProducerRetired` fires; the action is audited (initiator, approver, timestamp, reason). This reuses the configurable separation-of-duties primitive (MVP-DEC-007), **not** the Producer content-review workflow (§4.4) — offboarding is a commercial-relationship wind-down authorisation, not a data-quality review. The no-self-approval floor holds at any configured depth; the gating mechanism / RBAC tier is the dev team's (DEC-073). Rationale: offboarding cascades to `sunset` every Club the Producer operates, winds down member Profiles, and triggers Club-Credit conversion and refunds — the highest-blast-radius action in Module K. *(MVP-DEC-024.)*

### §14.6 ProducerAgreement

**BR-K-Agreement-1. Single active per Producer scope.** At any given time, at most one ProducerAgreement is `active` per Producer scope. Multi-Club Producers may have either Producer-wide or per-Club scoping; the two shapes are mutually exclusive on the same Producer at the same time.

**BR-K-Agreement-2. Settlement-cadence override.** The active ProducerAgreement's settlement cadence governs the per-Producer settlement event timing in Module E. Default cadence is the BMD-locked quarterly; per-Producer overrides are admitted via the agreement. **The launch value set is closed to three cadences — `quarterly` (default), `monthly`, and `semi-annual` (BMD §3.10) — enforced server-side (API + DB, not UI-only); extensible post-launch by configuration; the literal representation (enum vs lookup, casing, string form) is the dev team's call (DEC-073).** *(D19 settlement engine deferred — operator-run first cycles; the recorded cadence is the seam.)*

**BR-K-Agreement-3. Renewal via supersession.** Renewal creates a new ProducerAgreement that enters `active`; the prior agreement transitions to `superseded`. Audit history pairs the two.

**BR-K-Agreement-4. Per-Club scope requires an `active` Club at first scoping.** When a new ProducerAgreement is scoped to a specific Club (the per-Club shape, §4.6), the target Club must be `active`; Clubs in `sunset` or `closed` are not selectable as a new agreement's scope. A ProducerAgreement is forward-looking (24-month default term + an ongoing settlement cadence), so a non-active Club has no ongoing relationship for it to govern — `closed` is terminal once all members and obligations have resolved (BR-K-Club-3), and `sunset` is a wind-down that blocks new memberships/offers (§4.3). **Producer-wide scope carries no Club gate** (no Club is selected). **Supersession/renewal (BR-K-Agreement-3) inherits the superseded agreement's scope and does not re-select a Club, so it is not blocked by this rule** — terms for a Club that has since entered `sunset` can still be amended via supersession. *(This guards new-agreement **scoping** only. It is distinct from BR-K-Club-2 / §6.2, which dereferences an **already-locked** Club reference for historical settlement regardless of the Club's lifecycle state — the opposite direction.)*

### §14.7 Originating Club

**BR-K-OC-1. One-shot at first approval.** The Originating Club is set at the moment of the Customer's first `MembershipApprovedByProducer` across any Club's lifetime. The lock event fires once per Customer ever.

**BR-K-OC-2. Immutable in the application layer.** Once set, the Originating Club is treated as immutable. There is no admin-override surface at launch.

**BR-K-OC-3. No-OC allowance.** A Customer may have no Originating Club indefinitely. The 5% Originating-Club share simply does not accrue on Discovery sales for those Customers.

**BR-K-OC-4. Closed Club resolves cleanly.** A Customer's Originating Club staying resolvable to its Producer for settlement purposes does not depend on the Club's lifecycle state — the Club's operating-Producer link is immutable once set.

### §14.8 Hold and suspension

**BR-K-Hold-1. Multiple Holds, any Hold blocks.** A scope (Customer / Account / Profile) may carry multiple active Holds; any Hold blocks the activity types that Hold gates.

**BR-K-Hold-2. Hold precedence at every transaction-initiation surface (per DEC-181 sanctions/Hold uniformity principle).** Every transaction-initiation surface reads sanctions state and Hold state at the moment of action. Surfaces include (non-exhaustive at launch): order completion / purchase (Module S), gifting initiation (Module S — *deferred at launch, D5*), pickup handover (Module C), INV3 charge execution (Module E), refund routing (Module E), Cart Hold reservation at cart-add (Module S), SO `planned` transition (Module C), Voucher redemption-request (Module S), shipment-request initiation (Module C). Future transaction-initiation surfaces inherit by-property without explicit re-enumeration. Any active Hold blocks the surface's commercial action; cross-surface uniformity is structural, not enumerated. Anchored at §4.8. **Module K's role is the read-API tuple at the moment of action; the commercial-action enforcement is the receiving module's.**

**BR-K-Hold-3. Customer-scope cascades to Profiles.** A Customer-level Hold blocks every Profile under that Customer.

**BR-K-Hold-4. Profile-scope isolates.** A Profile-level Hold blocks only that Profile.

**BR-K-Hold-5. In-progress fulfilment unaffected.** Holds block the creation of new commercial commitment, not the completion of in-flight commitment.

### §14.9 Cross-module contract

**BR-K-Contract-1. Domain events as cross-module contract.** Every Module K state transition emits a versioned domain event consumed by downstream modules and the HubSpot integration (§15). Module K guarantees backward compatibility within a major event-schema version.

**BR-K-Contract-2. Module K records, Module E records financial events.** Module E records the events of payment, settlement, Club Credit financial impact, and credit / hold triggering. Module K consumes those events and records the resulting state on its own entities. Per DEC-072, Xero decides GL treatment.

**BR-K-Contract-3. Outbound email splits by purpose — the ERP sends operational mail; HubSpot sends marketing / lifecycle.** Every operational / transactional send from Module K (and from every ERP module) routes through the single ERP email service (§14.9.1) with its template resolved from the registered email catalog; marketing-consent state is never consulted on the operational path (§8.1 / DEC-026). HubSpot delivers marketing / lifecycle email only — driven by the customer-data sync and the domain events, gated by marketing consent. No ERP module integrates the mail provider directly, and no ERP module sends marketing email. *(MVP-DEC-035 — reverses the v1.1-inherited "HubSpot owns all outbound delivery; Module K never sends a Customer communication directly.")*

#### §14.9.1 The ERP email service (the email-service seam) — MVP-DEC-035

Operational email is a launch dependency the marketing platform cannot serve: registration verification (§7.1) and the email-change dual-token mail (BR-K-Identity-1) are hard gates in flows that must work before any HubSpot integration exists, and operational mail is consent-independent by legal basis (§8.1 / DEC-026). The ERP therefore owns it end-to-end:

- **Purpose split.** The ERP sends **operational / transactional** email; **HubSpot** sends **marketing / lifecycle** email (campaigns, newsletters, nurture, win-back, welcome-journey content), driven by the existing Module K event sync and gated by marketing consent (§8.1). Marketing consent gates ONLY the HubSpot class — operational mail is consent-independent (single-opt-in T&C / Privacy basis; AC-K-FSM-15).
- **ERP-sent classes at launch.** (i) **Security / identity** — registration verification (§7.1), email-change dual-token verify + notice (BR-K-Identity-1), password reset. (ii) **Token-bearing flow mail** — where the email IS the flow's delivery vehicle: Club-link and producer invitations (§7.2 / §7.3), waitlist / approval confirmations (§4.2.1 / §13.5), the §8.1 double-opt-in confirmation. (iii) **Money mail** — receipts and failed-charge notices (Module E side — joins when Module E lands; Module S order / shipment mail likewise joins the catalog when Module S lands).
- **One email service.** A single internal adapter every module calls; **no module talks to the mail provider directly**; one send log / audit trail, one suppression + locale policy. *(The dev build's existing direct sends consolidate behind the service — a refactor, not a rip-out.)*
- **Governed template layer outside code.** One base layout on the CRCLES design-system tokens; per-email templates owned by design / marketing; engineering injects variables only.
- **Registered email catalog.** Every ERP-sent email = ID + trigger event + audience + template + locales. Adding an email is a **product decision**, not an engineering side-effect. The catalog + template bindings are operator-managed via the Admin Panel (a bounded list, not a CMS).
- **Separate sending subdomains** for transactional vs marketing mail.
- **Provider behind the adapter = the dev team's call (DEC-073).** Dedicated ESP vs HubSpot's transactional API — an implementation choice behind the seam (the BMD §11.5 already contemplates either).

The service is platform infrastructure specced here (Module K is the party / communication spec home); where it is built and deployed is the dev team's call (DEC-073). Modules trigger sends by class and catalog ID; the service owns delivery mechanics.

---

## §15 Domain Events

Module K emits a versioned set of domain events that downstream modules and the HubSpot integration consume — ~30 emitted lifecycle + net-new events, plus ~10 events Module K *consumes* from Module E (recording the resulting state on its own entities). **The naming cascade touches only prose that references Module-0 (PIM) events; Module K's own event names are unchanged** (§18). Payload semantics are unchanged at the MVP.

### §15.1 Customer-family events

- `CustomerCreated` — on Customer record creation. Audit consumer.
- `CustomerActivated` — on `pending → active`. Triggers HubSpot full customer-data sync; downstream Module S enables transaction surfaces.
- `CustomerSuspended` — on `active → suspended`. Cascades to all the Customer's Profiles.
- `CustomerReactivated` — on `suspended → active`.
- `CustomerClosed` — on `active → closed` or `suspended → closed`. Terminal.
- `CustomerHoldPlaced` — on a Customer-scoped Hold being recorded.
- `CustomerHoldLifted` — on a Customer-scoped Hold being lifted.
- `CustomerSegmentChanged` — on every materialised-segment change (Member ↔ Waiting-list ↔ Legacy ↔ unset). Consumer-facing trigger for HubSpot marketing-segment sync (**Q1 — exercised at launch**).

### §15.2 Profile-family events

- `ProfileCreated` — on Profile record creation (application or invitation acceptance).
- `ProfileActivated` — on `Approved → Active` (membership-fee paid, atomic with producer approval — §4.2.1; or free-club activation). Module K consumes **Module S's** `MembershipFeePaid` to drive this (Module S emits; Module E records — DEC-173).
- `ProfileExpired` — on `Active → Lapsed`.
- `ProfileRenewed` — when a renewal cycle's `MembershipFeePaid` extends the membership validity period.
- `ProfileSuspended` — on `Active → Suspended`.
- `ProfileReactivated` — on `Suspended → Active`.
- `ProfileTierChanged` — on multi-tier-activated Clubs when a Profile's tier changes. Carries prior tier, new tier, transition reason (voluntary upgrade / voluntary downgrade / Producer-initiated kick / non-payment of renewal / KYC re-screen failure / *other* with mandatory note), actor. v17's four LEGACY-related reasons are NOT carried.
- `ProfileInactive` — on `Active → Inactive` (operational corner case at launch).

### §15.3 Club-family events

- `ClubCreated` — on Club creation.
- `ClubSunset` — on `active → sunset`. Triggers downstream offer / membership-creation gates.
- `ClubClosed` — on `sunset → closed`.

### §15.4 Producer-family events **(K's own names — UNCHANGED by the naming cascade)**

- `ProducerCreated` — on Producer record creation.
- `ProducerActivated` — on `draft → active` (KYC cleared — `verified` or `not_required`). **Consumer: Module 0 (enables Product Master activation against this Producer).** *(The event name is K's own and is unchanged; only the consumer-note prose renames Wine Master → Product Master — §18.)*
- `ProducerRetired` — on transition to `retired`. Consumers: Module 0 (blocks new Product Master activations); Module D (cascades to ProcurementIntent state); Module K's own offboarding cascade (§10).

### §15.5 ProducerAgreement-family events

- `ProducerAgreementCreated` — on draft creation.
- `ProducerAgreementActivated` — on `draft → active`. Consumers: Module D (procurement gates); Module E (settlement-cadence read at first settlement — D19 deferred).
- `ProducerAgreementSuperseded` — when a new agreement supersedes a prior one.
- `ProducerAgreementTerminated` — on permanent end. Does not auto-cascade to Producer-level state changes.

### §15.6 NewCo-net-new events

Six logical events that v17 did not carry:

- **`OriginatingClubLocked`** (§6 / DEC-066). Fires once per Customer ever, at the first `MembershipApprovedByProducer` across any Club. Carries the Customer, the locking Club, the moment, and the triggering membership. Consumers: Module S (settlement-eligibility resolution); Module E (settlement-event enrichment — **D19 deferred; the accrual is recorded at launch as the seam**); HubSpot.
- **`MembershipInvitationSent`** (§7.3). Fires when a prospect is invited to a Club (operator-driven at launch — §3.2). Consumers: the ERP email service (delivers the invitation email — §14.9.1); HubSpot (CRM / lifecycle sync); audit.
- **`MembershipInvitationAccepted`** (§7.3). Fires when an invitee accepts. Consumers: Module K's Profile-creation flow; HubSpot.
- **`WaitingListJoined`** (§4.2.1). Fires when a Profile transitions to `WaitingList`. Consumers: the ERP email service (waitlist-confirmation email — §14.9.1); HubSpot (lifecycle sync); the capacity-invariant evaluator (§13).
- **`CustomerOnboardingScreeningPassed`** / **`CustomerOnboardingScreeningFailed`** (§9). Fire at onboarding-time sanctions screening completion.
- **`CustomerRescreeningPassed`** / **`CustomerRescreeningFailed`** (§9). Fire on the 12-month-cadence (and between-cycle trigger) sanctions re-screening completion.
- **`CustomerTransitionedToLegacy`** (§5). Fires when a Customer's segment materialises to `Legacy`. Consumer: HubSpot (legacy-segment outreach — **Q1 exercised at launch**).

(The list reads as six logical events; the screening / re-screening pair is two events with two outcomes each.)

### §15.7 Per-Profile producer-initiated cancellation signal

When a Producer offboarding (§10.2) cascades to its Profiles, Module K emits a per-Profile cancellation signal that Module S consumes for the Club Credit migration mechanic (DEC-043). The exact event shape (a NewCo-specific name or a reason-coded `ProfileExpired` variant) is a downstream consumer concern; Module K's contribution is the producer-initiated transition logic + the cancellation reason at the originating boundary.

### §15.8 Events Module K *consumes* (recorded by Module E) **(N2 — trigger-agnostic registry)**

Module K does NOT emit the membership-fee-paid signal or the financial events that drive its Profile / Club Credit / Hold state. **`MembershipFeePaid` is emitted by Module S** (DEC-173); the remaining events below are emitted by Module E. Module E **records** all of them as financial events; Module K **consumes** them and records the resulting state on its own entities. **(Module E's and Module S's names are category-neutral and unchanged — the Module E carve-out, §18.)**

- `MembershipFeePaid` — **emitted by Module S** on payment-provider-confirmed capture of the Hero Package fee; **Module E records** the financial event; **Module K consumes**. The Hero Package fires **INV1** — there is **no separate INV0** (DEC-157). Fires at the **producer-approval moment** (joining) or the **renewal cycle** (renewal) per the corrected flow (§4.2.1). Drives `Profile.fee_paid_at`, `ProfileActivated` / `ProfileRenewed`, and Club Credit auto-generation (§11.1). *(Per DEC-173 the v1.1-inherited "Module E emits / INV0 charge" framing was stale — corrected at MVP-DEC-016; aligns with Module S §5.2, Module E §3.4, and the Architecture event contract, which already record Module S as the emitter.)*
- `ClubCreditAccrued` / `ClubCreditRestored` / `ClubCreditForfeited` — **emitted by Module E** (the financial accrual / reversal side); `ClubCreditAutoApplied` / `ClubCreditRemovedByCustomer` — **emitted by Module S** (the customer-facing application side, DEC-111). Module K consumes from BOTH and records the Club Credit lifecycle state on its own entity. *(Per DEC-174 + DEC-166 the v1.1-inherited "Module E emits `ClubCredit{Issued,Applied,Restored,Forfeited}`" framing was stale — corrected at MVP-DEC-018: the issuance event is `ClubCreditAccrued`, and the application events are Module-S-emitted. The K.18 welcome-window-scaled-issuance path and the K.19 manual-issuance path are deferred at launch, so the corresponding accrual variants simply don't fire — §11.1.)*
- `CustomerCreditHoldPlaced` / `CustomerCreditHoldLifted` — emitted by Module E (AR / credit-limit conditions). Module K records the Hold state on its own entity.
- `CustomerChargebackFlagged` — emitted by Module E (per DEC-168 chargeback chain). Module K consumes it and creates a Hold of type `CHARGEBACK_REVIEW` (Module K is the Hold registry-of-record on chargeback; Module E does not create the Hold directly), and flags the Customer for fraud-pattern review. **(N2: the chargeback trigger is AUTOMATED at launch — D21 KEPT, the Airwallex chargeback chain stays. The K-side registry behaviour is unchanged.)**
- `StoragePaymentFailed` / `StoragePaymentSucceeded` — emitted by Module E (per DEC-160 INV3 chain). `StoragePaymentFailed` drives Module K to create a Hold of type `STORAGE_PAYMENT_FAILED`; `StoragePaymentSucceeded` lifts the Hold for that cycle (per-cycle lift discipline; prior-cycle Holds remain until each is independently remediated). **(N2: the storage-payment trigger is MANUAL-FIRST at launch — the INV3 saved-card auto-escalation/dunning is deferred [D4]; an operator records the storage-payment outcome, and Module E emits these events from its manual-first path. The K-side Hold types + registry + placement path are unchanged — only the upstream automation depth differs.)**

### §15.9 Naming, ordering, versioning

**Naming.** Lifecycle events follow a `*Created` / `*Activated` / `*Retired|*Closed|*Suspended|*Reactivated` pattern aligned with the entity state machines. NewCo-net-new events use NewCo-specific names where the v17 vocabulary does not extend (e.g. `OriginatingClubLocked`). **(Module K's own event names are unchanged by the naming cascade — §18.)**

**Ordering.** Cascading events are emitted in parent-before-child order where applicable (a Producer retirement cascades `ProducerRetired` first, then `ClubSunset` per Club, then per-Profile signals). Consumers tolerate eventual-consistency arrival order; Module K guarantees emission order in cascade workflows.

**Versioning.** Events are schema-versioned so consumers evolve independently; Module K guarantees backward compatibility within a major schema version.

---

## §16 Module Boundary Notes — what Module K does NOT do

For clarity on cross-module hand-offs (these deliberate silences keep K neutral to the downstream cuts — NFT-decouple D12, settlement-defer D19, etc.):

- **Pricing, commercial terms, Offers, Hero Package designation, Club / Discovery surface admissibility validation.** Module S. Module K provides Customer + Profile + Hold + Originating Club state; Module S decides what the Customer can buy at what price.
- **Allocation creation, capacity management, sub-pool carve-out, sourcing-model attribute, Hero Package allocation `qty` storage.** Module A. Module K enforces the Hero Package capacity invariant against Module A's `qty` — the **single source of truth** (Hero Package capacity is the allocation `qty` itself; the two cannot diverge). Module K holds no *independent* capacity value; whether it enforces via a live read of Module A or a derived, reconciling read-model of `qty` (fed by A's `AllocationCapacity*` signal) is the dev team's implementation choice (DEC-073 — cf. the Module A ATP-cache pattern, §11.5.1).
- **Product Master / Product Variant / Product Reference / Intrinsic SKU / Composite SKU** *(wine-display aliases: Wine Master / Wine Variant / Bottle Reference)*. Module 0 (PIM). Module K holds the Producer entity that PIM links to; it does not duplicate Module 0 state. *(Naming cascade — §18.)*
- **SupplierProducerLink** (the N:N link between Supplier and Producer). Owned by Module D. Module K is upstream party registry only.
- **Settlement payment** (12.5% PO to Producer on Club sales per DEC-010; 5% Originating-Club share on Discovery per DEC-032; Discovery negotiated-cost payment). Module E. Module K provides Producer + Customer + Originating Club identity for settlement to resolve. *(D19 settlement engine deferred — operator-run first cycles; Module K's capture + cadence-recording is the seam.)*
- **Payment-method records and PCI boundary.** Module E + the payment-provider integration (Airwallex). Module K's Account holds the payment-provider customer reference; raw card data is never handled by Module K.
- **Club Credit conversion math** (DEC-043 — converting Club Credit to Discovery store credit at face value, 12-month validity, at Club closure). Module S. Module K's role ends at the upstream cancellation signal.
- **Invoice issuance** (INV1 / INV2 / INV3). Module E. Module K stores the company-billing affordance fields on Address; Module E selects which content surfaces on which invoice.
- **Gifting initiation + voucher ownership transfer.** Module S. **Gifting is deferred at launch (D5 — the S+K+C tri-module defer); Module K's generic Hold read-API at gifting initiation simply is not exercised at launch (§17). The voucher ownership-transfer seam is a Module S concern.**
- **AgencyAgreement / third-party-owner consignment / active consignment.** Inherited entity **absent-or-dormant** at launch — present-but-inactive or omitted from the schema entirely, either satisfies (DEC-011 + DEC-017; MVP-DEC-034). The `AgencyAgreement*` / `AgencyConsentGranted` event family is likewise dormant-or-absent (carried to roadmap — §17).
- **B2B segment surfaces.** Wholesale catalog, business-tier pricing, PO workflow, B2B credit terms — out at launch (DEC-017 + DEC-068). Only the company-billing affordance for individual collectors is active.
- **NFT linkage / wallet management.** Module B. The Customer entity carries no NFT-related state at launch; P2P secondary-market integration is post-launch (§17). *(Module K is intrinsically D12-neutral — it carries no on-chain attribute.)*
- **Outbound email delivery mechanics.** The single ERP email service (§14.9.1) owns operational-send mechanics platform-wide (the adapter, send log, suppression + locale policy, sending subdomains; provider behind the adapter = DEC-073); HubSpot owns marketing / lifecycle delivery. Module K triggers its operational sends through the service and emits the events HubSpot consumes; it never integrates the mail provider directly and never sends marketing email (MVP-DEC-035).
- **Producer Portal "design Hero Package" surface, "increase capacity" surface, waitlist review and approval flow** (beyond the single retained approve/decline write). Producer Portal UX scope (post-launch implementation); operator-driven via Admin Panel at launch (§3.2). Module K's responsibility ends at the invariant check (§13) and the events it consumes / emits.

> **Cross-MVP note (Direct Purchase deferred — Phase C item I).** Direct Purchase is **deferred at launch** (confirmed: no launch deal — Phase C Q4); the `direct_purchase` path idles across Modules A/D/B/E/S. **This does not touch Module K** — Module K is party/eligibility-only, carries no sourcing-model attribute (that is Module A), and needs no change whether Direct Purchase is active or idle.

---

## §17 Open Threads / Future-Flexibility Hooks & MVP deferred set

**Module K's MVP strip adds a narrow, well-seamed set of deferrals beyond v1.1; the floor + club spine + Club-Credit core + OC-capture are all KEPT.** All items below feed [`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md) (which extends `greenfield/03-qa/qa.deferred.md`). **Do not re-cut the already-deferred items — they carry verbatim with their existing hooks.**

### §17.1 Net-new MVP deferrals (Module-K-relevant; seams stated)

| # | Deferred item | Seam preserved (P1) | Restores with |
|---|---|---|---|
| 1 | **K.18 — Club-Credit welcome-window proportional scaling** (§11.1) | The issuance hook + the `policy × (fee_paid/full_fee)` formula are **retained in Module K**; at launch full-fee → full-credit, so no scaling fires. Additive when restored. | Post-launch welcome-window campaigns (decided S Q2). |
| 2 | **K.19 — operator manual Club-Credit issuance** (§11.1) | The **manual-create path is retained in Module K** (entity + one-active + currency invariants + audit); at launch goodwill routes through the **single REFUND_COMPENSATION coupon** (Module S, S.16), so the manual path is not exercised. Additive when restored. | Post-launch goodwill-instrument expansion (decided S Q2). |
| 3 | **Gifting-initiation read-API idle** (§16; D5) | The Hold read-API at gifting initiation is **generic and unchanged**; gifting is deferred (the S+K+C tri-module defer), so the surface simply isn't exercised. The voucher ownership-transfer seam is a Module S concern. | The **coordinated gifting restoration** (S + K + C) — Phase C item N. |

*(These three are the genuine Module-K-layer MVP additions to the deferred set. The Club-Credit entity + auto-issuance + one-active invariant + K.17 carry-forward are KEPT; marketing consent is KEPT (Q1).)*

### §17.2 v1.1 already-deferred set (carried verbatim — do not re-cut)

The following were explicitly deferred at v1.1 with documented re-introduction seams; they carry to the roadmap unchanged:

1. **Q-OQ-9 — 24-month producer-agreement template.** ProducerAgreement placeholder fields (§4.6) finalise when the template lands.
2. **Q-OQ-10 — persona profile for target customer.** Feeds Customer-attribute prioritisation; does not block the data model.
3. **Q-OQ-11 — death / inheritance / corporate dissolution policy.** Case-by-case ops handling via the existing Suspended → Cancelled / Closed flow with admin-recorded reason until policy lands.
4. **Supplier-operated Clubs** (DEC-067) — generalise the Club operator association post-launch.
5. **Sophisticated waitlist mechanics** (DEC-069) — automatic FIFO conversion, priority-by-application-date, producer-defined ranking.
6. **Capacity-decrease constraint tightening** (DEC-069).
7. **Multi-tier Club activation** (DEC-062) — single-tier launch; multi-tier is configuration only.
8. **B2B segment reintroduction** (DEC-068) — the v17 Customer / Account B2B shape re-loads as a discrete future-DEC.
9. **Active consignment / third-party agency reintroduction** (DEC-011 + DEC-017) — the Third-Party-Owner Party subtype + AgencyAgreement entity become live; shapes preserved.
10. **Liquid sales reintroduction** (DEC-065 — Module 0 cross-reference) — any Module-K-side liquid hooks.
11. **Re-acceptance on T&C / Privacy Policy version updates** — currently operationally determined; a future-DEC if a richer model is needed.
12. **Auto-suspension on zero active Profiles** — currently flagged for review, not auto-suspended.
13. **P2P secondary-market wallet linkage on Customer** (BMD §13.5) — inherited deferred field; activation is a configuration / event-handler addition, no schema change.
14. **Enhanced-KYC document workflow** — currently the trigger fires a flag + Compliance handles operationally; a state machine if it gets a real workflow post-launch.
15. **Operator override for Originating Club** — currently immutable in the application layer.
16. **Country-change automated sanctions re-screen detection** (DEC-030) — NOT enabled at launch (signal-to-noise); deferred to Phase 2+.

---

## §18 Naming-cascade application (Phase C item A) + the N2 editorial landing

Module 0 v0.3-MVP §18 is the **source-of-truth** name table; this section records **how those names land in Module K** — and, equally important, **what does NOT rename.** The change is **naming/contract only — zero behaviour change** (every event carries the same business signal; BR and PR denote the same key).

**What renames in Module K (the PR-referencing / Module-0-event-consuming prose only):**

| Touchpoint | v1.1 prose | v0.3-MVP prose | Wine-display alias retained |
|---|---|---|---|
| §4.4 Producer | "identity source for **Wine Master** in Module 0 PIM" | "identity source for **Product Master** in Module 0 PIM" | Wine Master |
| §14.5 BR-K-Producer-2 | "A **Wine Master** cannot be activated unless its linked Producer is active and KYC-verified…" | "A **Product Master** cannot be activated…" | Wine Master |
| §14.5 BR-K-Producer-4 | "existing active **Wine Masters** … remain valid" | "existing active **Product Masters** … remain valid" | Wine Masters |
| §15.4 `ProducerActivated` consumer note | "Consumer: Module 0 (enables **Wine Master** activation…)" | "Consumer: Module 0 (enables **Product Master** activation…)" | — |
| §16 boundary list | "**Wine Master / Wine Variant / Bottle Reference** / Sellable SKU / Composite SKU" | "**Product Master / Product Variant / Product Reference** / Intrinsic SKU / Composite SKU" | Wine Master / Wine Variant / Bottle Reference |

**What does NOT rename in Module K (the carve-outs — Phase C item A):**
- **Module K's own event names are unchanged.** `ProducerCreated` / `ProducerActivated` / `ProducerRetired`, `ProducerAgreement*`, `Customer*`, `Profile*`, `Club*`, `OriginatingClubLocked`, `MembershipInvitation*`, `WaitingListJoined`, `Customer{Onboarding,Re}Screening{Passed,Failed}`, `CustomerSegmentChanged`, `CustomerTransitionedToLegacy` — all **K's own**; PIM **consumes** `ProducerActivated` / `ProducerRetired` (those are upstream-from-K Producer events, **not** PIM `Wine*` events). Only the consumer-note *prose* renames Wine Master → Product Master.
- **The consumed-event names are unchanged by the Module 0 naming cascade** (the Module E / Module S carve-out — category-neutral): `MembershipFeePaid` (**Module-S-emitted** — DEC-173; Module E records, Module K consumes), `ClubCreditAccrued` / `ClubCreditRestored` / `ClubCreditForfeited` (**Module-E-emitted**) + `ClubCreditAutoApplied` / `ClubCreditRemovedByCustomer` (**Module-S-emitted** — DEC-174 three-actor split, corrected at MVP-DEC-018), `CustomerCreditHold{Placed,Lifted}`, `CustomerChargebackFlagged`, `StoragePayment{Failed,Succeeded}`.
- **"Bottle Reference" is retained everywhere as a wine-display alias** for Product Reference.

**The N2 editorial landing (Phase C item D / §5-N2) — recapped.** K's Hold registry is **trigger-agnostic** (§4.8.1). The two finance-driven Hold triggers consumed from Module E (§15.8) have *different automation depths at launch* — **chargeback automated (D21 KEPT)**, **storage-payment manual-first (D4 deferred)** — and the K-side registry, Hold types, and manual-placement path are **identical for both.** The K.28 prose is aligned so "manual-first" does not imply *both* triggers are manual. The mirror of this note lands in the Module E v0.3-MVP PRD (the trigger side). **No behaviour change.**

---

## §19 v1.1 inheritance & MVP re-baseline trace (audit appendix)

This appendix preserves the audit trail of Module K v0.3-MVP against its **frozen v1.1 predecessor** ([`../../reference/v1.1/01-prd/Module_K_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_K_PRD_v0.2.md), whose §18 carries the v17 §6 trace) and the **ratified cut-sheet** + **Phase C reconciliation**. The load-bearing prose is the body above (DEC-074); this trace is for audit / diff.

> **Section-numbering note.** Module K is **KEEP-in-full with no structural entity insertion**, so **§1–§16 keep their v1.1 numbering** (unlike Module 0, whose §3.x shifted +1 for the new Product Type). Only **§0** is prepended (MVP framing) and the trailing sections are repurposed: v1.1 §17 (open threads) → §17 (+ the MVP deferred set); v1.1 §18 (v17 trace) → **§18 naming-cascade application** + **§19 this trace**; v1.1 §19 (cross-refs) → §20. **The acceptance doc's PRD §-anchors therefore remain valid against this PRD.**

| v0.3-MVP section | v1.1 (v0.2) anchor | Cut-sheet / Phase C | MVP disposition |
|---|---|---|---|
| §0 MVP scope at a glance | — (new) | cut-sheet §1; Phase C §1 | NEW — Phase D framing; KEEP-in-full + naming cascade verdict. |
| §1 Module Purpose | v0.2 §1 | cut-sheet K.21/§3.3 | KEEP; Producer "identity source for Product Master" (cascade). |
| §2 Personas | v0.2 §2 | cut-sheet §3.2; K-Q3/Q4 | KEEP; + P2 operator-surface + L-PP one-producer-write + Q3 role-count config notes. |
| §3 Architecture (Netflix-style) | v0.2 §3 | cut-sheet §3.1 | KEEP; multi-profile load-bearing (savings-hunt: not a safe cut). + §3.1 L-PP producer-write table. |
| §4.1 Customer | v0.2 §4.1 | cut-sheet K.1–K.8 | KEEP (floor: KYC/sanctions/anonymisation state). |
| §4.2 Profile | v0.2 §4.2 | cut-sheet K.9/K.10 | KEEP (generic FSM; 11 transitions). |
| §4.3 Club | v0.2 §4.3 | cut-sheet K.11 | KEEP (single-tier launch; Producer-operated). |
| §4.4 Producer | v0.2 §4.4 | cut-sheet K.21/K.22/§3.3 | KEEP + GENERALISE (Product Master link; Q3 role-count config). |
| §4.5 Supplier | v0.2 §4.5 | cut-sheet K.23 | KEEP-minimal (Discovery suppliers active at launch). |
| §4.6 ProducerAgreement | v0.2 §4.6 | cut-sheet K.24 / K-Q6 | KEEP-minimal (the D19 settlement-cadence seam). |
| §4.7 Account | v0.2 §4.7 | cut-sheet K.25 | KEEP (Airwallex ref; B2B credit dormant). |
| §4.8 Hold | v0.2 §4.8 | cut-sheet K.26/K.27/K.28; Phase C N2 | KEEP — FLOOR; + N2 trigger-agnostic-registry alignment. |
| §5 Customer Segments | v0.2 §5 | cut-sheet K.12; K-Q1 | KEEP; marketing-segment consumers exercised at launch (Q1). |
| §6 Originating Club | v0.2 §6 | cut-sheet K.13; Phase C item E | KEEP-capture (seam-critical; 5% computation defers with D19). |
| §7 Onboarding Flows | v0.2 §7 | cut-sheet K.29/K.30/K.31; L-PP | KEEP; invitation operator-driven at launch. |
| §8 Marketing Consent / Soft-delete / Anonymisation | v0.2 §8 | cut-sheet K.6/K.8; K-Q1 | KEEP (Q1 marketing KEPT; anonymisation FLOOR). |
| §9 KYC + Sanctions Screening | v0.2 §9 | cut-sheet K.2–K.5; Phase C floor chain 2 | KEEP — FLOOR; order-completion gate = the single enforcement point (S.15). |
| §10 Suspension & Producer Offboarding | v0.2 §10 | cut-sheet K.20 | KEEP (lean; DEC-043 closure-conversion KEEP-lean, owned by S). |
| §11 Club Credits | v0.2 §11 | cut-sheet K.16–K.19; Phase C item D | KEEP core (entity + auto-issuance + one-active + K.17 carry-forward); **DEFER K.18 + K.19** (seams retained). |
| §12 GDPR & Privacy | v0.2 §12 | cut-sheet K.7; Phase C floor chain 6 | KEEP — FLOOR (erasure + 10-yr retention). |
| §13 Hero Package Capacity Invariant | v0.2 §13 | cut-sheet K.14/K.15; K-Q5; Phase C item G | KEEP (reads Module A `qty`; Q5 mid-year mutability KEEP). |
| §14 Business Rules | v0.2 §14 | cut-sheet §3.3 | KEEP all; BR-K-Producer-2/4 → Product Master (cascade). |
| §15 Domain Events | v0.2 §15 | cut-sheet K.32/§3.3; Phase C N2 | KEEP; cascade on PIM-referencing prose only (K's own names unchanged); §15.8 N2 alignment. |
| §16 Module Boundary Notes | v0.2 §16 | cut-sheet K.33; Phase C items I/N | KEEP; + Direct-Purchase-neutral + gifting-idle notes. |
| §17 Open Threads + MVP deferred set | v0.2 §17 | cut-sheet K.34; Phase C item N | KEEP verbatim (already-deferred) + the K.18/K.19/gifting-idle net-new deferrals. |
| §18 Naming-cascade application + N2 | v0.2 §18 (v17 trace) | Phase C items A + D/N2; Module 0 §18 | REPURPOSED — NEW; the cascade application + carve-outs + N2 landing. |
| §19 v1.1 & MVP trace | v0.2 §18 (v17 trace) | — | NEW — this audit appendix (the v17 trace lives in the frozen v0.2 §18). |

Notation: *KEEP* = the v1.1 substance is restated in full NewCo language without semantic change; *GENERALISE* = naming-cascade rename only (Product Master), non-behavioural; *DEFER* = moved to the roadmap with a stated seam; *NEW / REPURPOSED* = Phase-D framing with no direct v1.1 predecessor (or a repurposed section slot).

---

## §20 Cross-references

- **v1.1 predecessor (frozen)** — [`../../reference/v1.1/01-prd/Module_K_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_K_PRD_v0.2.md). The source spec carried in full; never edited (plan R4). Its §18 carries the v17 §6 trace; its §19 the v1.1 cross-references (DECs, qa.modK, BMD v0.4).
- **Ratified cut-sheet** — [`../01-triage/Module_K_CutSheet_v0.1.md`](../01-triage/Module_K_CutSheet_v0.1.md). §2 inventory (scope), §3 module-specific changes (rewrite instructions — savings-hunt / L-PP / naming cascade), §5 acceptance delta, §6 the six ratified Qs.
- **Phase C reconciliation** — [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md). Item A (naming cascade), item D (Club-Credit three-way seam — K.17 KEPT, K.18/K.19 deferred), item E (OC 5% capture whole at launch), item L (one producer write), editorial note N2 (§5-N2 — trigger-agnostic Hold registry), §6 floor chains 2 (KYC/sanctions/Hold) + 6 (audit/retention) + 1 (Hero Package allocation, item G).
- **Naming source of truth** — [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) §18 (the canonical name table + carve-outs). Applied here, not re-derived.
- **Module S cut-sheet (the Club-Credit decision locus)** — [`../01-triage/Module_S_CutSheet_v0.1.md`](../01-triage/Module_S_CutSheet_v0.1.md) §3.2 (K.17 KEEP; K.18/K.19 DEFER; the REFUND_COMPENSATION coupon goodwill path; DEC-043 KEEP-lean).
- **MVP decisions register** — [`../04-decisions/MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md) (the thin index → authoritative docs).
- **Method + dials** — [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (P1/P2, the floor) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (D8 club KEEP; D1 currencies / D2 locales KEEP; L-PP one producer write).
- **Testable companion** — [`../03-acceptance/Module_K_Acceptance_v0.3-MVP.md`](../03-acceptance/Module_K_Acceptance_v0.3-MVP.md).
- **Sibling v0.3-MVP PRDs** — Module 0 (written first; the Producer entity links to its Product Master). Next in the cascade: Module A → D → S → B / C → E, then the Admin-Panel PRD + Architecture.

---

*End of Module K PRD v0.3-MVP — Phase D re-baseline. **Verdict: KEEP-in-full + the naming cascade.** The compliance floor (KYC / sanctions + the order-completion gate / unified Hold + DEC-181 / GDPR erasure + 10-yr retention), the D8 club spine, the Club-Credit core (entity + auto-issuance + one-active + K.17 carry-forward), and the OC 5% capture are all KEPT whole. The genuine MVP deferrals are the K.18 / K.19 Club-Credit peripherals (seams retained in K; decided in S) + the gifting-init read-API idle (D5) — plus v1.1's already-deferred set carried verbatim. The N2 editorial note is landed (trigger-agnostic Hold registry: chargeback automated [D21], storage-payment manual-first [D4]). No RECONCILE is owned by K. **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E.*
