## ADDED Requirements

### Requirement: Enhanced-KYC Threshold Detection

NewCo SHALL detect when a Customer crosses an enhanced-KYC monetary threshold and escalate the Customer to enhanced review. The two thresholds are **independent (OR)** triggers, each measured in EUR minor units: a **single completed transaction ≥ €10,000**, OR a **rolling trailing-12-month cumulative purchase total ≥ €50,000** (DEC-035). Detection SHALL read the Customer's transaction totals only through the within-module `CustomerTransactionTotalsReader` contract — never by cross-module database access to Module S (invariant 10); the real source is Module S order/invoice history (deferred), and the launch binding SHALL be a null adapter returning zero totals, so detection is a correct no-op until Module S provides the data (a documented seam).

Detection SHALL be **idempotent per Customer, latched on `enhanced_kyc_flag`**: the escalation fires at most once, on the first crossing. On a first crossing, in one `DB::transaction` against a transaction-locked re-read, the workflow SHALL: (a) set `enhanced_kyc_flag = true` and stamp `enhanced_kyc_at`; (b) create exactly one Compliance review-queue entry recording the tripping `threshold_kind` and amount (per the *Compliance Review Queue* requirement); (c) record the PII-free `CustomerEnhancedKycReviewRequired` event; and (d) initiate the lightweight AML re-screen by recording an `under_review` sanctions verdict with `trigger_source = aml_threshold` through the sole sanctions-writer (per the *Customer Sanctions Screening Lifecycle* requirement), which blocks the Customer from transacting until Compliance resolves it. When `enhanced_kyc_flag` is already set, detection SHALL be a **no-op** — no second review-queue entry, no second event, no sanctions write.

Detection SHALL run via **two trigger paths sharing one workflow**: (i) a **periodic background job** — a daily scheduled command evaluating every Customer, built in this change and running as a scheduler tick (not a queued consumer); and (ii) an **at-order-completion check** for the just-completed order's Customer, invoking the same workflow — its trigger wired by Module S when checkout lands (deferred). Both paths SHALL produce identical Customer state and identical recorded events.

#### Scenario: A single transaction ≥ €10k triggers escalation

- **WHEN** the totals reader reports a largest single transaction ≥ €10,000 EUR for a Customer whose `enhanced_kyc_flag` is not set
- **THEN** `enhanced_kyc_flag` is set and `enhanced_kyc_at` stamped, one Compliance review-queue entry recording the `single_transaction` trigger is created, a `CustomerEnhancedKycReviewRequired` event is recorded, and the Customer's `sanctions_status` becomes `under_review` with `trigger_source = aml_threshold`

#### Scenario: Cumulative annual ≥ €50k triggers the same escalation

- **WHEN** the totals reader reports a rolling trailing-12-month cumulative total ≥ €50,000 EUR (via several sub-threshold transactions) for an un-flagged Customer
- **THEN** the same downstream signals occur — flag + timestamp, one review-queue entry recording the `cumulative_annual` trigger, the `CustomerEnhancedKycReviewRequired` event, and `sanctions_status = under_review` with `trigger_source = aml_threshold`

#### Scenario: Detection is idempotent for an already-flagged Customer

- **WHEN** detection evaluates a Customer whose `enhanced_kyc_flag` is already set (e.g. the daily scan runs again while the Customer is still above threshold)
- **THEN** nothing changes — no second review-queue entry, no second event, and no sanctions write

#### Scenario: Both trigger paths produce identical state

- **WHEN** the same crossing is evaluated through the periodic scan and (when wired) the at-order-completion check
- **THEN** the resulting Customer state and the recorded events are identical (the two paths invoke one workflow)

#### Scenario: The totals source is a deferred Module-S seam

- **WHEN** the Parties code surface is inspected at launch
- **THEN** `CustomerTransactionTotalsReader` is bound to a null adapter returning zero totals (no cross-module access to Module S order data), so the periodic scan runs and detects nothing until Module S provides the real adapter

_Source: spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 2 AC-K-J-7a (single-tx €10k / cumulative-annual €50k → enhanced-KYC flag + timestamp + Compliance review-queue entry; both periodic-job and at-order-completion paths; assert identical state) · § 5.4 AC-K-EVT-12a (AML-threshold breach → lightweight re-screen + trigger-source aml-threshold) · spec/02-prd/Module_K_PRD_v0.3-MVP.md § 9.1 (DEC-035 — enhanced-KYC trigger: crosses €10,000 single OR €50,000 cumulative annual; detection runs both as a periodic background job and at order completion; no state machine beyond the flag + timestamp), § 9.2 (DEC-030 E6-10 — AML-threshold detection, daily scan of cumulative annual totals, fires the lightweight DEC-030 sanctions re-check + the enhanced-KYC review-queue entry), § 9.5 (manual-first launch posture — the screen is the floor, the vendor integration deferrable) · spec/04-decisions/decisions.md DEC-035, DEC-030 · decisions/2026-06-12-event-substrate-and-audit-store.md (scheduler ticks are not queued consumers — the queue-driver gate is not tripped) · CLAUDE.md invariants 6 (money = minor units + currency), 7 (compliance gates; blocks never auto-lifted), 10 (module boundaries — events + read contracts, no cross-module DB access)._

### Requirement: Compliance Review Queue

NewCo SHALL maintain a **Compliance review-queue** — a within-module store (`parties_compliance_reviews`) of Compliance work-items raised when a Customer requires review. Each entry SHALL record: the Customer it concerns (a `customer_id` FK to `parties_customers`; no cross-module relation), a `reason` (the enhanced-KYC threshold breach — `enhanced_kyc_threshold` — is the sole reason in this change), the tripping `threshold_kind` (`single_transaction` | `cumulative_annual`), the tripping amount as **EUR minor units + currency code** (money discipline, invariant 6), and a `resolved_at` timestamp that is NULL on creation. Open vs resolved SHALL be **boolean-derivable** (`resolved_at IS NOT NULL`), NOT an FSM — enhanced-KYC is handled operationally, with no state machine (§ 9.1). Entries SHALL be created only by the *Enhanced-KYC Threshold Detection* workflow.

Creating a review-queue entry SHALL record a **PII-free** `CustomerEnhancedKycReviewRequired` domain event carrying only the `customer_id`, `enhanced_kyc_at`, `threshold_kind`, and the tripping amount (minor units + currency) — never `name` / `email` / `phone` / `date_of_birth` (the 10-year event store holds no PII). It is a net-new event over the frozen § 15.6 catalog (which names none), added as the audit anchor for the breach and as the seam a future Compliance surface / Module-E consumer reads.

Resolving a review-queue entry SHALL be handled operationally and is NOT modeled in this change: entries ship creatable + readable (`resolved_at` open), and the operator resolve action is deferred.

#### Scenario: A threshold breach creates one open review-queue entry

- **WHEN** the *Enhanced-KYC Threshold Detection* workflow escalates a Customer
- **THEN** exactly one `parties_compliance_reviews` row exists for that Customer with `reason = enhanced_kyc_threshold`, the tripping `threshold_kind` and amount recorded, and `resolved_at` NULL (open)

#### Scenario: The review event is PII-free

- **WHEN** a review-queue entry is created and `CustomerEnhancedKycReviewRequired` is recorded
- **THEN** the event payload carries only `customer_id`, `enhanced_kyc_at`, `threshold_kind` and the amount (minor units + currency), and none of the Customer's `name` / `email` / `phone` / `date_of_birth`

#### Scenario: The queue is within-module

- **WHEN** the review-queue schema and model are inspected
- **THEN** the entry links to the Customer by `customer_id` (a within-module FK) and holds no relationship to Module S / Module E tables

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 9.2 (DEC-030 E6-10 — the enhanced-KYC review-queue entry created on an AML-threshold breach), § 9.1 (DEC-035 — enhanced-KYC handled operationally at launch; no separate state machine beyond the flag + timestamp), § 2 Personas (the Compliance Reviewer reviews enhanced-KYC threshold trips) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 2 AC-K-J-7a (a Compliance review-queue entry is created) · docs/validation/Remediation_Tracker.md RM-02 (the `enhanced-kyc` review event) · openspec/specs/party-registry/spec.md (the *Customer Anonymisation (Right-to-Erasure)* requirement — the precedent for a net-new PII-free event over the frozen-catalog silence) · decisions/2026-06-12-event-substrate-and-audit-store.md (the PII-free 10-year event store) · CLAUDE.md invariants 6 (money), 10 (module boundaries — within-module FK only)._

## MODIFIED Requirements

### Requirement: Customer KYC Lifecycle

The Customer SHALL carry a **KYC lifecycle that is separate from the Customer status FSM**: the four-state domain `not_required → pending → verified | rejected`, held in an **additive nullable** `kyc_status` field (DEC-071 — a NULL `kyc_status` denotes a Customer created un-screened). The Customer SHALL also carry an administratively-set `kyc_required` flag and an enhanced-KYC trigger flag + timestamp, both additive nullable.

Setting `kyc_required` SHALL transition KYC `not_required → pending`. A Customer in KYC `pending` SHALL transition to `verified` (identity verification cleared) or to `rejected` (failed) via explicit operator Actions that are the sole writers of `kyc_status`. KYC `verified` and `not_required` are the **cleared** (non-blocking) states; `pending` and `rejected` are blocking. The blocking effect on purchases is realized by the **`kyc` Hold** (the *Hold Registry*): setting `kyc_required` SHALL **auto-place** a Customer-scope `kyc` Hold within the same transaction as the `not_required → pending` write; recording `verified` SHALL **auto-lift** the Customer's active `kyc` Hold(s) within the same transaction (the system auto-lift the per-type discipline permits — DEC-160); recording `rejected` SHALL **leave** the `kyc` Hold in place (Compliance reviews case-by-case — § 9.1). This coupling is within-module Action orchestration (the KYC Action calls the Hold place/lift), since KYC records no domain event of its own.

The enhanced-KYC trigger flag + timestamp SHALL exist as additive nullable fields recording whether the Customer crossed the €10,000-single / €50,000-cumulative threshold. The **detection** of that crossing is implemented by the *Enhanced-KYC Threshold Detection* requirement: a periodic scan (and a deferred at-order-completion trigger) reads the Customer's transaction totals through the `CustomerTransactionTotalsReader` contract — null-bound until Module S provides the data — and, on the first crossing, SHALL set `enhanced_kyc_flag` + `enhanced_kyc_at`, raise a Compliance review-queue entry and initiate the AML-threshold re-screen. **Setting the flag SHALL be orthogonal to `kyc_status`** — it does not, by itself, move the KYC FSM (enhanced review is handled operationally, § 9.1, with no enhanced-KYC state machine).

KYC state changes SHALL record **no KYC domain event** (the PRD event catalog § 15.1 names none); the KYC change is captured in the append-only audit trail only, while the coupled `kyc` Hold place/lift records its own `CustomerHoldPlaced` / `CustomerHoldLifted` (per the *Hold Events* requirement). Every KYC transition SHALL be **from-state guarded** against a transaction-locked re-read and SHALL reject an out-of-state call with a localized `IllegalKycTransition`, leaving state and the event log unchanged.

#### Scenario: The kyc_required flag transitions not_required to pending and auto-places the kyc Hold

- **WHEN** an operator sets a Customer's `kyc_required` flag and the Customer's `kyc_status` is `not_required` or NULL
- **THEN** `kyc_status` becomes `pending`, a Customer-scope `kyc` Hold is auto-placed in the same transaction, and the only domain event recorded is `CustomerHoldPlaced` (KYC itself records no event)

#### Scenario: Verified auto-lifts the kyc Hold; rejected leaves it in place

- **WHEN** a Customer in KYC `pending` (with an active `kyc` Hold) is recorded `verified`
- **THEN** `kyc_status` becomes `verified` (a cleared state), the active `kyc` Hold is auto-lifted, and `CustomerHoldLifted` is recorded (KYC itself records no event)
- **WHEN** a Customer in KYC `pending` is recorded `rejected`
- **THEN** `kyc_status` becomes `rejected` (a blocking state), the `kyc` Hold remains in place (no automatic onward transition — Compliance reviews case-by-case), and no Hold event is recorded

#### Scenario: The KYC FSM is separate from the Customer status FSM

- **WHEN** the Parties code surface is inspected
- **THEN** `kyc_status` is a field and FSM distinct from the Customer status (`pending / active / suspended / closed`), and a KYC transition does not move the Customer status

#### Scenario: Enhanced-KYC detection sets the flag on a threshold crossing

- **WHEN** a Customer's transaction totals cross €10,000 in a single transaction or €50,000 rolling trailing-12-month cumulative, and `enhanced_kyc_flag` is not set
- **THEN** the *Enhanced-KYC Threshold Detection* workflow sets `enhanced_kyc_flag` and stamps `enhanced_kyc_at`, and `kyc_status` is unchanged (the flag is orthogonal to the KYC FSM)

#### Scenario: Illegal KYC transitions are rejected

- **WHEN** `RecordKycVerified` or `RecordKycRejected` is invoked on a Customer whose `kyc_status` is not `pending`
- **THEN** an `IllegalKycTransition` is raised, `kyc_status` is unchanged, and no `kyc` Hold is placed or lifted

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer KYC state + `kyc_required` flag + enhanced-KYC trigger fields), § 9.1 (KYC four-state lifecycle; `not_required` default; setting `kyc_required` → `pending`; `pending` auto-places the `kyc` Hold, `verified` auto-lifts it, `rejected` leaves it; cleared = `verified` ∨ `not_required`), § 4.8 / § 4.8.1 (the `kyc` Hold — auto-place/auto-lift coupling; DEC-160), § 15.1 (no KYC event family; `CustomerHoldPlaced`/`CustomerHoldLifted`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-3 (KYC FSM separate; the `kyc` Hold auto-places on `pending` and auto-lifts on `verified`), AC-K-J-7 (KYC required → Hold blocks → verified → Hold lifts → purchases resume), AC-K-J-7a (enhanced-KYC trigger fields + detection) · spec/04-decisions/decisions.md DEC-071, DEC-035, DEC-160 · decisions/2026-06-18-hold-lift-discipline-per-type.md (the `kyc` auto-lift is the system path; operator lift of a `kyc` Hold is rejected) · decisions/2026-06-12-event-substrate-and-audit-store.md (audit trail; transactional Hold events). The enhanced-KYC detection (AC-K-J-7a) is implemented by the *Enhanced-KYC Threshold Detection* requirement._

### Requirement: Customer Sanctions Screening Lifecycle

The Customer SHALL carry a **sanctions-screening lifecycle that is separate from both the Customer status FSM and the KYC FSM, and independent of KYC**: the four-state domain `pending → passed | failed | under_review` plus `under_review → passed | failed`, held in additive nullable fields (DEC-071) — `sanctions_status`, `last_screening_at`, `next_rescreen_at`, and the screening `trigger_source` (`onboarding | cadence | aml_threshold | compliance_ad_hoc`). A NULL `sanctions_status` denotes a Customer created un-screened and SHALL be treated, for any downstream purchase gate, as not-`passed` (blocked) — exactly like `pending`.

An explicit operator Action SHALL record each screening verdict (manual-first — the screen is the floor, the vendor integration is deferrable): it SHALL set `sanctions_status` to the verdict, stamp `last_screening_at`, set `next_rescreen_at` to the 12-month-forward moment, record the `trigger_source`, and (on a `passed`/`failed` completion) record the matching screening event per the *Sanctions Screening Events* requirement. A verdict carrying `trigger_source = onboarding` SHALL be the Customer's **first** screening (rejected with `IllegalSanctionsTransition` if `last_screening_at` is already set); every other `trigger_source` denotes a **re-screen**.

The sanctions lifecycle SHALL be **independent of KYC**: a sanctions transition SHALL NOT change `kyc_status` and a KYC transition SHALL NOT change `sanctions_status`; the two clear independently. The **enforcement** of `sanctions_status = passed` as a purchase precondition is **Module S's** at order completion (Module K is sanctions-blind by design) and is NOT in this change.

The **AML-threshold re-screen** is now implemented (per the *Enhanced-KYC Threshold Detection* requirement): on a €10k-single / €50k-cumulative breach the detection SHALL record an **`under_review`** verdict with `trigger_source = aml_threshold` through this requirement's sole screening-writer Action — a **lightweight re-screen** that records **no** completion event at initiation (`under_review` is not a completion) and **blocks the Customer from transacting** until Compliance resolves it; the resolution records the matching `CustomerRescreening{Passed,Failed}` (the same outcome events as the cadence path). The **automated 12-month re-screen cadence** (the daily background job reading `next_rescreen_at`) remains **deferred** (manual-first); the operator ad-hoc re-screen Action, the four events, the `trigger_source` field and the `next_rescreen_at` field continue to ship.

#### Scenario: Onboarding screening records the first verdict and event

- **WHEN** an operator records an onboarding sanctions screening of `passed` for a Customer whose `sanctions_status` is NULL or `pending` (no prior `last_screening_at`)
- **THEN** `sanctions_status` becomes `passed`, `last_screening_at` is stamped, `next_rescreen_at` is set 12 months forward, `trigger_source` is `onboarding`, and a `CustomerOnboardingScreeningPassed` event is recorded
- **WHEN** the onboarding verdict is `failed`
- **THEN** `sanctions_status` becomes `failed` and a `CustomerOnboardingScreeningFailed` event is recorded

#### Scenario: under_review resolves to passed or failed

- **WHEN** a Customer in `under_review` is re-screened to `passed` (resp. `failed`)
- **THEN** `sanctions_status` becomes that verdict and the matching `CustomerRescreening*` event is recorded

#### Scenario: Re-screening records the rescreening events with the trigger source

- **WHEN** an operator runs an ad-hoc re-screen on a previously-screened Customer to `passed`, with `trigger_source = compliance_ad_hoc`
- **THEN** `sanctions_status` becomes `passed`, `trigger_source` is recorded as `compliance_ad_hoc`, and a `CustomerRescreeningPassed` event is recorded

#### Scenario: Sanctions and KYC are independent state machines

- **WHEN** one Customer has `kyc_status = verified` and `sanctions_status = pending`, and another has `sanctions_status = passed` and `kyc_status = pending`
- **THEN** each pair is recorded independently — no sanctions transition changes `kyc_status` and no KYC transition changes `sanctions_status` (the purchase-gate consequence of each non-clean state is enforced downstream, not in this slice)

#### Scenario: An AML-threshold breach initiates an under_review re-screen tagged aml_threshold

- **WHEN** the *Enhanced-KYC Threshold Detection* workflow escalates a Customer on a threshold breach
- **THEN** the Customer's `sanctions_status` becomes `under_review` with `trigger_source = aml_threshold` (recorded through the sole screening-writer), no completion screening event is recorded at initiation, and the Customer is blocked from transacting until an operator resolves the re-screen — whose `passed`/`failed` resolution records the matching `CustomerRescreening{Passed,Failed}` event

#### Scenario: The 12-month cadence job remains deferred

- **WHEN** the Parties code surface is inspected
- **THEN** `last_screening_at`, `next_rescreen_at` and `trigger_source` exist and both the AML-threshold re-screen and the operator ad-hoc path operate, but there is no daily cadence job reading `next_rescreen_at` in this change (a documented seam)

#### Scenario: An onboarding screening on an already-screened Customer is rejected

- **WHEN** a verdict with `trigger_source = onboarding` is recorded for a Customer whose `last_screening_at` is already set
- **THEN** an `IllegalSanctionsTransition` is raised and the sanctions state is unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer sanctions state, last-screening moment, next re-screen), § 9.2 (sanctions four-state lifecycle; EU+UIF+OFAC; 12-month cadence as a daily job; between-cycle trigger paths and trigger sources — DEC-030/DEC-035), § 9.3 (the order-completion gate is Module S — the single enforcement point; a Customer can exist `sanctions_status = pending`), § 9.4 (KYC and sanctions independent — both clear independently), § 9.5 (manual-first launch posture; the screen + gate are the floor, the integration is deferrable; acceptance drives state, not a live vendor) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-4 (sanctions FSM separate; `pending → passed/failed/under_review`, `under_review → passed/failed`; re-screening fires the events), AC-K-FSM-5 (KYC and sanctions independent), AC-K-EVT-12 (onboarding + rescreening events drive state), AC-K-EVT-12a (AML-threshold breach → lightweight re-screen; trigger source recorded) · spec/04-decisions/decisions.md DEC-071, DEC-030, DEC-041. The order-completion enforcement (AC-K-J-20) is Module S's; the AML-threshold re-screen is implemented by the *Enhanced-KYC Threshold Detection* requirement, while the 12-month cadence job remains deferred._

