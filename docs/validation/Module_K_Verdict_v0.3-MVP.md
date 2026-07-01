# Module K (Parties) — Verdict Report vs v0.3-MVP Acceptance

> **What this is.** The Paolo-style verdict report (mail 2026-07-01), applied to **our** underwater AI-native build of Module K. Each criterion handed back with **Verdict · Evidence · Notes**.
> **Baseline built-against (ask #3).** `spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md` — frozen c-mless `handoff/` snapshot at **MVP-DEC-007**. The **Canon Overlay** flags where canon (through **DEC-023**) has moved — Module K is where the delta is heaviest.
> **Method.** AUTO criteria mapped to real code + Pest (`517` domain + `518` console Parties tests pass; the 5 console "failures" are a subset-isolation artifact of a shared i18n scanner — 327/327 co-loaded). Evidence cited `file:line` / `Test::name`. "Deferred" = doc-annotated deferred-with-feature / not-exercised / downstream-verified. The six canon-divergence probes were **re-verified by the reviewer against source** (Hold enum, membership flow, capacity, anonymisation, ClubCredit event name, SoD).
> **Date.** 2026-07-01.

## Summary — where Module K stands (vs local baseline)

| Verdict | Count | % of 130 |
|---|---|---|
| **Pass** | 77 | 59% |
| **Partial** | 26 | 20% |
| **Fail / Gap** | 19 | 15% |
| **Deferred** (expected) | 8 | 6% |
| **Total** | 130 | |

**In one line:** a **tale of two halves.** The **within-module spine + compliance floor** is real and heavily tested — Customer/Account/Profile/Producer/ProducerAgreement/Club/Hold/ClubCredit FSMs, KYC + sanctions (separate FSMs, independent gates), the unified Hold registry with suspension coupling + per-type lift discipline, the DEC-181 uniform compliance read-API, Originating-Club one-shot lock, ProducerAgreement supersession. **Hollow** on everything cross-module: **GDPR erasure/anonymisation** (floor, absent), **Hero-Package capacity** (ships UNCAPPED), **enhanced-KYC threshold** (floor, seam columns only), **customer-segment + marketing**, and **all Module-E/S event consumption** (`PartiesServiceProvider::boot()` is empty). Two consumed events even require Hold types that don't exist (enum is 6, not 8).

## Ask #1 — Verdict table (against local v0.3-MVP baseline)

| AC ID | Verdict | Evidence | Notes |
|---|---|---|---|
| AC-K-J-1 | Pass | `CustomerOnboardingActivationTest.php:44,93,121`; `CustomerTest.php:61` | Composite gate (email∧T&C∧privacy∧sanctions∧KYC) + Account/Party provision. Self-service HTTP + live vendor deferred. |
| AC-K-J-2 | Partial | `Actions/CreateProfile.php:78`; `Enums/ClubRegistrationFlowType.php:25` | Primitives present; no single orchestrated club-link flow (deferred surface). |
| AC-K-J-3 | **Fail/Gap** | no `MembershipInvitation*` events | Invitation send/accept flow absent (`invited_by_customer_id` col only). |
| AC-K-J-4 | Pass | `Actions/ApproveProfile.php:83-98`; `ProfileMembershipApprovalTest.php:41,90` | `OriginatingClubLocked` fires once, first Club. |
| AC-K-J-5 | Pass | `Models/Customer.php:126-136`; `ComplianceIndependenceTest.php:122` | No-OC allowance; 5% accrual → Module S. |
| AC-K-J-6 | **Fail/Gap** | no consent/marketing columns | Marketing double opt-in FSM absent (KEPT-Q1 launch scope). |
| AC-K-J-7 | Pass | `CustomerKycLifecycleTest.php:41,116` | FLOOR. Auto-Hold on pending, auto-lift on verified. Purchase-block → Module S. |
| AC-K-J-7a | Partial | `Models/Customer.php:68-69`; `RecordCustomerScreening.php:42-44` | FLOOR. Only `enhanced_kyc_flag/_at` seam columns; €10k/€50k detection + review-queue absent. |
| AC-K-J-8 | Pass | `Actions/RecordCustomerScreening.php`; `CustomerSanctionsLifecycleTest.php:41,119,140` | MIXED/FLOOR. Failed→blocked, resolution recorded. |
| AC-K-J-9 | **Fail/Gap** | no anonymisation action/column; no Address entity | **FLOOR. Right-to-erasure / PII-overwrite not implemented. Launch-critical compliance gap.** |
| AC-K-J-9a | **Fail/Gap** | depends on missing anonymisation | FLOOR. Regulatory-vs-non-regulatory Hold precedence absent. See Canon Overlay (DEC-015). |
| AC-K-J-10 | Partial | `Actions/CreateProducer.php` + `ActivateProducer.php:64-71`; `ProducerLifecycleTest.php:116,133` | create→KYC-gate→activate ✔; **no Creator→Reviewer→Approver SoD** (§7 puts SoD in scope). See Probe 6. |
| AC-K-J-11 | Pass | `Actions/ActivateProducerAgreement.php`; `ProducerAgreementTest.php:32` | Module D/E consumption + D19 settlement engine deferred. |
| AC-K-J-12 | Pass | `ActivateProducerAgreement.php:88-123`; `ProducerAgreementLifecycleTest.php:88` | Supersession pairs old+new. |
| AC-K-J-13 | **Fail/Gap** | `ApproveProfile.php:40-45` + `ActivateProfile.php:37-42` ("ships UNCAPPED"); no `WaitingListJoined` | **Capacity invariant + WaitingList not implemented** (deferred to `parties-hero-package` after Module A). See Probe 3 + Canon Overlay (DEC-017). |
| AC-K-J-14 | **Fail/Gap** | no capacity/waitlist consumer | MIXED. Depends on absent capacity model + Module A. |
| AC-K-J-15 | **Fail/Gap** | no capacity storage/enforcement | Capacity-decrease constraint absent. |
| AC-K-J-16 | Partial | `Actions/IssueClubCredit.php:93`; `ClubCreditIssuanceTest.php:49,84,132`; `ClubCreditForfeitureTest.php:196` | Core K.16 + one-active invariant tested via within-module writer; **`MembershipFeePaid` consumer + `Profile.fee_paid_at` absent.** See Probe 2 + Canon Overlay (DEC-016/018). |
| AC-K-J-16a | Deferred | doc "⛔ DEFERRED-WITH-FEATURE (K.19)"; seam `IssueClubCredit.php:52-55` | Expected. Goodwill via Module-S coupon. |
| AC-K-J-17 | Deferred | doc "⛔ DEFERRED-WITH-FEATURE (K.18)"; `ClubCreditIssuanceTest.php:49` | Expected. Scaling hook retained. |
| AC-K-J-18 | Partial | `ClubCreditRedemptionTest.php:40,96,112,127,145` | Balance/carry-forward complete; **cross-club eligibility** (the defining behaviour) is a Module-S seam, not enforced K-side. |
| AC-K-J-19 | Partial | `Actions/RetireProducer.php:84-96`; `ProducerLifecycleTest.php:187` | Club-sunset cascade done + ordered; **per-Profile cancellation leg NOT wired** (`RetireProducer.php:37-38`). |
| AC-K-J-20 | Pass | `ComplianceReadApiTest.php:39,50,141,156` | FLOOR. Tuple under all sanctions×Hold combos. Module-S gate (S.15) downstream. |
| AC-K-J-21 | Deferred (HUMAN) | — | Paolo's ~90-min live session. |
| AC-K-FSM-1 | Pass | `CustomerOnboardingActivationTest`, `CustomerSuspensionCascadeTest`, `CustomerClosureAndAccountStatusTest`; `EnumsTest.php:46` | All 5 events. |
| AC-K-FSM-2 | Partial | `Profile*Test` (approval/activation/suspension/lapse/cancellation); `StatusEventsTest` | 7 events + transitions ✔; **`Applied→WaitingList` branch not implemented** (deferred capacity gate). |
| AC-K-FSM-2a | Pass | `ProfileSuspensionTest.php:59`; `ClubCreditRedemptionTest.php:127,145` | Credit-freeze on suspension tested; voucher/order preservation vacuous (S/B/E unbuilt). |
| AC-K-FSM-3 | Pass | `CustomerKycLifecycleTest.php:41,116`; `ComplianceEnumsTest.php:16` | FLOOR. KYC FSM separate from Customer. |
| AC-K-FSM-4 | Pass | `RecordCustomerScreening.php`; `CustomerSanctionsLifecycleTest.php:41,99,119` | FLOOR. |
| AC-K-FSM-5 | Pass | `ComplianceIndependenceTest.php:54,74` (4-cell) | FLOOR. KYC ⟂ sanctions. |
| AC-K-FSM-6 | Partial | `ClubLifecycleTest.php:32,119` | Transitions+events ✔; **"sunset blocks new memberships" NOT enforced** (`CreateProfile` has no club-status guard). |
| AC-K-FSM-7 | Pass | `ActivateProducer.php:64-71`; `ProducerLifecycleTest.php:116,133` | KYC-cleared gate, all 4 paths. |
| AC-K-FSM-8 | Pass | `ProducerAgreementLifecycleTest.php:47,88,255` | All 4 events. |
| AC-K-FSM-9 | Pass | `CustomerClosureAndAccountStatusTest.php:125,154`; Hold couples suspend/restore | |
| AC-K-FSM-10 | Pass* | `Enums/HoldType.php:28-33` (6 types); `HoldRegistryTest.php:87,108` | FLOOR. **Pass vs local (doc says "six"); *behind canon* (DEC-008 = 8).** See Probe 1 + Canon Overlay. |
| AC-K-FSM-11 | Pass* | `Enums/HoldType.php:41-44`; `LiftHold.php:103-105`; `HoldLifecycleTest.php:173,191` | FLOOR. Lift discipline for the 6; the two finance-driven types absent. |
| AC-K-FSM-12 | Pass | `LapseProfile.php`+`RenewProfile.php:66`; `ProfileLapseGraceTest.php:71,129,174,197` | 30-day grace. |
| AC-K-FSM-13 | Pass | `ProfileCancellationTest.php:74,153` | Never hard-deleted. |
| AC-K-FSM-14 | **Fail/Gap** | no segment model; `CustomerSegmentChanged`/`...ToLegacy` don't exist | MIXED. Segment materialisation + reconciliation absent. |
| AC-K-FSM-15 | **Fail/Gap** | no marketing-consent FSM/columns | KEPT-Q1 launch scope; absent. |
| AC-K-FSM-16 | **Fail/Gap** | no anonymisation state/column | FLOOR. Absent. |
| AC-K-FSM-17 | Partial | `Apply/Forfeit/RestoreClubCredit`; `ClubCreditForfeitureTest.php:55,130,196` | FSM + one-active invariant tested; **forfeiture triggers** (year-end/cancellation/closure) deferred seams. |
| AC-K-BR-Identity-1 | Partial | `CustomerTest.php:84`; `Exceptions/DuplicateCustomerEmail.php` | Global uniqueness ✔; **email-change verification workflow absent**. |
| AC-K-BR-Identity-2 | Pass | `CreateProfile.php:64-76` + partial index; `ProfileTest.php:79,94,110` | |
| AC-K-BR-Identity-3 | Pass | `CustomerOnboardingActivationTest.php:93` | T&C on Customer, cross-Profile. |
| AC-K-BR-Identity-4 | Deferred | doc (Module-S order-completion) | K exposes Profile state; enforcement downstream. |
| AC-K-BR-Identity-5 | Pass | `Models/Supplier.php:17`; `EnumsTest.php:198` | Party-type marker immutable by construction. |
| AC-K-BR-Customer-1 | Pass | `CustomerClosureAndAccountStatusTest.php:51` | No auto-suspend from Profiles. |
| AC-K-BR-Customer-2 | **Fail/Gap** | covered by FSM-16 | Anonymisation-orthogonality undemonstrable (no anonymised state). |
| AC-K-BR-Customer-3 | Partial | non-suspension correct (BR-Customer-1) | **"review-flag set" affordance not implemented**. |
| AC-K-BR-Profile-1 | Pass | `EnumsTest.php:161` (generic 9-state) | No member-specific tier flow. |
| AC-K-BR-Profile-2 | Pass | covered by FSM-13 | |
| AC-K-BR-Profile-3 | Pass | covered by FSM-12 | |
| AC-K-BR-Profile-4 | Pass | covered by FSM-2 | HubSpot consumption downstream. |
| AC-K-BR-Club-1 | Pass | `ClubTest.php:34,67` (`MissingClubProducer`) | |
| AC-K-BR-Club-2 | Pass | `ClubTest.php:137` (immutable FK) | |
| AC-K-BR-Club-3 | Partial | covered by FSM-6 | Lifecycle ✔; sunset-blocks-new-membership not enforced. |
| AC-K-BR-Club-4 | Pass | `ProducerLifecycleTest.php:42`; `MembershipActivationChainTest.php:185` | |
| AC-K-BR-Club-5 | Partial | `Models/Club.php` (`generates_credit`/`invite_only`); `Models/Profile.php` (nullable `tier`) | Single-tier holds; no tier-definitions structure to verify. |
| AC-K-BR-Producer-1 | Pass | `ProducerTest.php:118` | Standalone Producer. |
| AC-K-BR-Producer-2 | Pass | covered by FSM-7 | Module-0 gate downstream. |
| AC-K-BR-Producer-3 | Pass | `ProducerTest.php:118` + `SupplierTest.php:52` | No auto-cross-create. |
| AC-K-BR-Producer-4 | Pass | covered by J-19 | Module-0 block downstream. |
| AC-K-BR-Agreement-1 | Partial | `ActivateProducerAgreement.php:77-88`; `ProducerAgreementLifecycleTest.php:88,135,184` | One-active-per-scope ✔; **DIVERGENCE: code allows Producer-wide AND Club-narrowed active at once; AC requires mutual exclusion.** |
| AC-K-BR-Agreement-2 | Deferred | doc (Module E; D19); `settlement_cadence` present | Cadence seam present. See Canon Overlay (DEC-010). |
| AC-K-BR-Agreement-3 | Pass | covered by J-12 | |
| AC-K-BR-OC-1..4 | Pass | covered by J-4/J-5; `ComplianceIndependenceTest.php:122`; `ClubTest.php:137` | OC lock, immutability, no-OC allowance, closed-club deref. |
| AC-K-BR-Hold-1 | Pass | `HoldRegistryTest.php:65`; `ComplianceReadApiTest.php:179` | Multiple concurrent Holds. |
| AC-K-BR-Hold-2 | Pass | `Reads/DatabaseComplianceStatusReader.php`; J-20 | Single read-API; 9 downstream surfaces deferred. |
| AC-K-BR-Hold-3 | Pass | `ComplianceReadApiTest.php:67` | Customer→Profile cascade. |
| AC-K-BR-Hold-4 | Pass | `ComplianceReadApiTest.php:95` | Profile isolation. |
| AC-K-BR-Hold-5 | Partial | K gates at initiation only | In-flight-continues needs Module C (SOs). |
| AC-K-BR-Contract-1 | Pass | migration `2026_06_12_000001:35` (`schema_version`) | |
| AC-K-BR-Contract-2 | Pass | `ClubCreditEventOwnershipTest.php:50,63` (K emits no ClubCredit*) | Consumption deferred (Module E). |
| AC-K-BR-Contract-3 | Pass | no direct email/SMS in Parties | HubSpot delivery downstream. |
| AC-K-EVT-1 | Pass | `StatusEventsTest.php:52`; `ActivationEventsTest.php:52` | |
| AC-K-EVT-2 | Pass | `HoldEventsTest.php:33,48,66` | Audit metadata on payload. |
| AC-K-EVT-3 | **Fail/Gap** | `CustomerSegmentChanged` doesn't exist | Depends on absent segment. |
| AC-K-EVT-4 | Pass | `ActivationEventsTest.php` | K-side emission; HubSpot/Module-S deferred. |
| AC-K-EVT-5 | Pass | covered by FSM-2 | 7 Profile events. |
| AC-K-EVT-6 | Deferred | `ProfileTierChanged` absent; multi-tier post-launch (DEC-062) | |
| AC-K-EVT-7 | Pass | covered by FSM-6 | |
| AC-K-EVT-8 | Pass | covered by FSM-7 | K's own event names (unchanged by cascade). |
| AC-K-EVT-9 | Pass | `ProducerAgreementLifecycleTest.php:255` | |
| AC-K-EVT-10 | Pass | covered by J-4; `ActivationEventsTest.php:101` | |
| AC-K-EVT-11 | **Fail/Gap** | `MembershipInvitationSent/Accepted/WaitingListJoined` all absent | |
| AC-K-EVT-12 | Partial | `ScreeningEventsTest.php:55`; `next_rescreen_at` | MIXED. 12-month cadence daily job deferred. |
| AC-K-EVT-12a | Partial | `ScreeningTriggerSource` enum; `CustomerSanctionsLifecycleTest.php:140` | Ad-hoc + trigger-source ✔; AML-threshold auto-detection absent. |
| AC-K-EVT-13 | **Fail/Gap** | `CustomerTransitionedToLegacy` absent | Depends on absent segment. |
| AC-K-EVT-14 | **Fail/Gap** | `RetireProducer.php:37`; `CancelProfile` emits no signal | Per-Profile cancellation signal not emitted. |
| AC-K-EVT-15 | Partial | `ActivateProfile.php:26-31`; `IssueClubCredit.php:46` | **`MembershipFeePaid` consumer + `fee_paid_at` not wired** (`PartiesServiceProvider::boot` empty). |
| AC-K-EVT-16 | Partial | `ClubCreditEventOwnershipTest.php:50,63` | "Emits none" ✔; no Module-E listener wired. See Canon Overlay (DEC-018). |
| AC-K-EVT-17 | Partial | `credit` HoldType + Place/LiftHold | No `CustomerCreditHold*` consumer wired. |
| AC-K-EVT-18 | **Fail/Gap** | no `CHARGEBACK_REVIEW`; no `CustomerChargebackFlagged` consumer | **Canon divergence (6 vs 8 Hold types).** See Probe 1. |
| AC-K-EVT-19 | **Fail/Gap** | no `STORAGE_PAYMENT_FAILED`; no `StoragePaymentFailed` consumer | **Canon divergence (6 vs 8).** |
| AC-K-EVT-20 | Partial | `RetireProducer.php:84-96`; `ProducerLifecycleTest.php:187` | 2-level ordering ✔; 3rd level (per-Profile) absent. |
| AC-K-XM-1..3 | Pass | `Models/Producer.php`; `ComplianceReadApiTest.php`; J-20 | Producer ownership; compliance tuple. |
| AC-K-XM-4 | Deferred | doc "⚠ not-exercised-at-launch (D5 gifting)" | Read-API retained for gifting restoration. |
| AC-K-XM-5..11 | Pass | `Reads/DatabaseComplianceStatusReader.php` (uniform tuple) | Module-K side of all 7 sanctions/Hold surfaces; each downstream enforcement deferred. |
| AC-K-XM-12 | Pass | `Contracts/PartyComplianceStatusReader.php` bound `PartiesServiceProvider.php:26`; `ComplianceReadApiTest.php:35` | MIXED. Single source-of-truth; call-site inventory awaits consumers. |
| AC-K-XM-13..14 | Pass | covered by J-4/J-5/BR-OC-4 | |
| AC-K-XM-15 | Pass | `CreateProducer.php:20-21` | Module D owns SupplierProducerLink. |
| AC-K-XM-16 | Deferred | covered by BR-Agreement-2 (D19) | |
| AC-K-XM-17 | Pass | covered by BR-Contract-3 | Segment events absent (EVT-3/13). |
| AC-K-XM-18 | Partial | `Models/Club.php` (no capacity field ✓) | **"K enforces by reading Module A qty at the gate" NOT implemented** — ships UNCAPPED. See Canon Overlay (DEC-017/020). |
| AC-K-XM-19 | **Fail/Gap** | no capacity enforcement | Depends on absent gate + Module A/0. |
| AC-K-XM-20 | Pass | no Offer/Pricing/Allocation/SKU in Parties | |
| AC-K-XM-21 | Pass | covered by XM-15 + EVT-16 | |
| AC-K-XM-22 | Pass | `AccountTest.php:22,33` (no card/PAN) | PCI boundary respected. |
| AC-K-XM-23 | Partial | `ForfeitClubCredit.php:39-42` (no conversion math) | No-conversion ✔; per-Profile signal not emitted (EVT-14). |
| AC-K-XM-24 | Partial | no `AgencyAgreement` entity | Functional dormancy holds by absence; "entity present for future flexibility" not satisfied. |
| AC-K-XM-25 | Partial | `Models/Customer.php` (no B2C/B2B discriminator ✓) | **"company-billing fields on Address" fails — no Address entity exists.** |
| AC-K-XM-26 | Pass | no NFT/wallet fields in Parties | D12-neutral. |
| AC-K-MVP-1 | Pass | no `Wine*`/`BottleReference*` in K; `ClubCreditEventOwnershipTest.php:63` | Naming/contract-only property holds. |
| AC-K-MVP-2 | **Fail/Gap** | `Enums/HoldType.php` = 6; no `CHARGEBACK_REVIEW`/`STORAGE_PAYMENT_FAILED` | **Headline canon divergence.** Trigger-agnostic placement holds for the 6; the 2 N2 types can't be driven. |
| AC-K-MVP-3 | Pass | `ClubCreditIssuanceTest.php:49`; hook `IssueClubCredit.php:52` | K.18 launch behaviour + seam. |
| AC-K-MVP-4 | Pass | no operator manual-credit goodwill flow; `IssueClubCredit` = seam | K.19 present-but-not-exercised. |
| AC-K-MVP-5 | Partial | KYC/sanctions/Hold/order-gate/club-spine/OC all Pass | MIXED. **Scope-parity NOT fully achieved: GDPR-erasure floor absent + `MembershipFeePaid` auto-trigger unwired.** |

## Canon Overlay (ask #3) — where a local verdict is behind current canon

Module K's canon delta is **heavy**: **7 new criteria + ~30 touched rows** (130 → 137). The three highest-impact items land on Paolo's exact test scenarios.

| Canon change | DEC | Our position | Severity |
|---|---|---|---|
| **Membership flow: charge-on-approval, `approval = charge = activation`, atomic; `MembershipFeePaid` emitted by Module S; INV1 no INV0** (`J-16`, `J-1/2/3`, `EVT-15`) | DEC-016 | We built the **now-declared-wrong** flow: distinct `Approved` (approved-but-unpaid) → separate `ActivateProfile` gated on a deferred Module-E signal. **Not just behind canon — the old design canon rejected.** | 🔴 High |
| **Hero-Package capacity: seat-set = `Active` + `Suspended`**, enforced at the atomic approve-moment; decrease-floor counts both; `+ J-15a` renewal grandfather/attrition | DEC-011/017 | We **don't enforce capacity at all** (ships UNCAPPED). Doubly behind: feature missing **and** the refined seat-set rule. Paolo's "Club hitting capacity" scenario. | 🔴 High |
| **Hold enum → 8** (six + `CHARGEBACK_REVIEW` + `STORAGE_PAYMENT_FAILED`) | DEC-008 | Enum is **6**, asserted `toHaveCount(6)`. Blocks `EVT-18/19`, `MVP-2`. | 🟠 Med |
| **Erasure × Hold block-set over 8 types — only `compliance` blocks; no separate `sanctions` Hold** (`J-9a`, `+ J-9b` data-export, NEW) | DEC-015 | Anonymisation is **entirely absent**, so the refined block-set is moot until the feature is built. Paolo's "anonymisation" scenario. | 🔴 High (floor) |
| **Club Credit issuance event renamed `ClubCreditIssued` → `ClubCreditAccrued`; application events Module-S-emitted** | DEC-018 | Our vocabulary is still `ClubCreditIssued` (`ClubCredit.php`, `IssueClubCredit.php`, tests); `ClubCreditAccrued` appears nowhere. | 🟡 Low (rename/seam) |
| **`settlement_cadence` closed to {quarterly, monthly, semi-annual}, server-enforced** (`BR-Agreement-2`) | DEC-010 | Field present; closed-set enforcement not verified (Deferred). | 🟡 Low |
| **7 NEW criteria**: `J-9b` (data export), `J-15a` (renewal grandfather), `BR-Agreement-4` (per-Club needs active Club, DEC-009), `BR-Club-6` (config field-set, DEC-013), `BR-Identity-6`, `BR-Producer-5`, `BR-Profile-5` (CML-88/89 clarifications, DEC-021/022) | 009/013/021/022 | Not in our built-against set; mostly unbuilt or untested. | mixed |

## Top launch-critical gaps (most severe first)

1. **GDPR right-to-erasure / anonymisation entirely absent (FLOOR)** — J-9, J-9a, FSM-16, BR-Customer-2. No anonymisation action, no `anonymised_at`, no PII-overwrite, **no Address entity**. Legal/compliance floor, 100% unbuilt.
2. **Hold registry 6 not 8** — EVT-18/19, MVP-2. Primary canon divergence (DEC-008); the K-side registry can't record a type that doesn't exist.
3. **Hero-Package capacity invariant unenforced** — J-13/14/15, XM-18/19, FSM-2. Membership ships UNCAPPED; no-oversell-at-membership (a core invariant) deferred to `parties-hero-package` pending Module A.
4. **Enhanced-KYC €10k/€50k threshold + review-queue absent (FLOOR)** — J-7a, EVT-12a. Only seam columns.
5. **All Module-E/S event consumption unwired** — EVT-15/16/17(+18/19). `PartiesServiceProvider::boot()` empty; `MembershipFeePaid`→activation+credit not chained.
6. **Customer-segment + marketing absent** — FSM-14/15, EVT-3/13, J-6.
7. **Producer-onboarding separation-of-duties absent** — J-10. Single-operator create→KYC→activate; no distinct-actor floor (contrast Catalog, which has it).
8. **Producer offboarding per-Profile leg + invitation flow absent** — J-3, J-19, EVT-11/14/20, XM-23.
9. **ProducerAgreement scope-exclusivity divergence** — BR-Agreement-1. Code allows Producer-wide + Club-narrowed active at once; AC requires mutual exclusion.
10. **Club "sunset blocks new memberships" not enforced** — FSM-6, BR-Club-3.

## Special probes (re-verified against source)

1. **Hold enum = 6, NOT 8.** `app/Modules/Parties/Enums/HoldType.php:28-33` → `Admin, Kyc, Payment, Fraud, Compliance, Credit`; `HoldEnumsTest.php:32` asserts `toHaveCount(6)`; PG CHECK derives from `HoldType::cases()`. No `CHARGEBACK_REVIEW`/`STORAGE_PAYMENT_FAILED` (grep NONE).
2. **Membership NOT atomic.** `ProfileState.php` carries distinct `Approved` **and** `Active`; `ActivateProfile.php:69-74` does `approved → active` as a separate step "driven by Module E's membership-fee-paid signal" — the exact "approved-but-unpaid" intermediate DEC-016 declared wrong. No `fee_paid_at` column; `MembershipFeePaid` emitted by no module (`PartiesServiceProvider::boot` empty).
3. **Capacity seat-set: undefined — unenforced.** `ApproveProfile.php:40-45` / `ActivateProfile.php:37-42` both state "ships UNCAPPED"; no capacity column on Club, no `Applied→WaitingList` transition, no `WaitingListJoined`. The "Active vs Active+Suspended occupies a seat" question has **no answer in code**.
4. **Anonymisation × Hold block-set: absent.** No anonymisation action/column, no Address entity, no `anonymised_at` — the J-9a precedence matrix does not exist.
5. **Club Credit issuance event = `ClubCreditIssued` (old name); `ClubCreditAccrued` nowhere.** Issuance is audit-only (`IssueClubCredit.php:39-44` records no event); `ClubCreditEventOwnershipTest.php:63` asserts K emits none of the ClubCredit family.
6. **Self-approval / SoD: NOT enforced in Module K.** `CreateProducer` unconditional; `ActivateProducer.php:60-71` gates only on from-state + KYC; `ApproveProfile` has no distinct-actor check. (Contrast: Catalog **does** enforce SoD — Module 0 Probe 3.)
