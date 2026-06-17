## ADDED Requirements

### Requirement: Customer KYC Lifecycle

The Customer SHALL carry a **KYC lifecycle that is separate from the Customer status FSM**: the four-state domain `not_required → pending → verified | rejected`, held in an **additive nullable** `kyc_status` field (DEC-071 — a NULL `kyc_status` denotes a Customer created un-screened). The Customer SHALL also carry an administratively-set `kyc_required` flag and an enhanced-KYC trigger flag + timestamp, both additive nullable.

Setting `kyc_required` SHALL transition KYC `not_required → pending`. A Customer in KYC `pending` SHALL transition to `verified` (identity verification cleared) or to `rejected` (failed) via explicit operator Actions that are the sole writers of `kyc_status`. KYC `verified` and `not_required` are the **cleared** (non-blocking) states; `pending` and `rejected` are blocking. The blocking effect on purchases is realized by the `kyc` Hold, which is owned by the deferred `parties-holds` change — this slice records the KYC **state** only and SHALL NOT place or lift any Hold.

The enhanced-KYC trigger flag + timestamp SHALL exist as additive nullable fields recording whether the Customer crossed the €10,000-single / €50,000-cumulative threshold. The **detection** of that crossing (the periodic scan and the at-order-completion check) reads cumulative-spend data that does not exist at launch and is **deferred**; only the fields ship in this change.

KYC state changes SHALL record **no domain event** (the PRD event catalog names no KYC event); the change is captured in the append-only audit trail only. Every KYC transition SHALL be **from-state guarded** against a transaction-locked re-read and SHALL reject an out-of-state call with a localized `IllegalKycTransition`, leaving state and the event log unchanged.

#### Scenario: The kyc_required flag transitions not_required to pending

- **WHEN** an operator sets a Customer's `kyc_required` flag and the Customer's `kyc_status` is `not_required` or NULL
- **THEN** `kyc_status` becomes `pending` and no domain event is recorded (audit only)

#### Scenario: Verified and rejected are reachable from pending

- **WHEN** a Customer in KYC `pending` is recorded `verified`
- **THEN** `kyc_status` becomes `verified` (a cleared state)
- **WHEN** a Customer in KYC `pending` is recorded `rejected`
- **THEN** `kyc_status` becomes `rejected` (a blocking state); no automatic onward transition is performed (Compliance reviews case-by-case)

#### Scenario: The KYC FSM is separate from the Customer status FSM

- **WHEN** the Parties code surface is inspected
- **THEN** `kyc_status` is a field and FSM distinct from the Customer status (`pending / active / suspended / closed`), and a KYC transition does not move the Customer status

#### Scenario: Enhanced-KYC fields exist but detection is deferred

- **WHEN** a Customer is inspected
- **THEN** it carries a nullable enhanced-KYC flag and timestamp, and there is no operation in this change that auto-sets them from purchase totals (the detection job is a documented seam)

#### Scenario: This slice places no kyc Hold

- **WHEN** a Customer transitions KYC `not_required → pending`
- **THEN** the `kyc_status` is `pending` and no Hold is created (the `kyc` Hold auto-placement is owned by `parties-holds`)

#### Scenario: Illegal KYC transitions are rejected

- **WHEN** `RecordKycVerified` or `RecordKycRejected` is invoked on a Customer whose `kyc_status` is not `pending`
- **THEN** an `IllegalKycTransition` is raised and `kyc_status` is unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer KYC state + `kyc_required` flag + enhanced-KYC trigger fields), § 9.1 (KYC four-state lifecycle; `not_required` default; setting `kyc_required` → `pending`; cleared = `verified` ∨ `not_required`; enhanced-KYC handled operationally — no extra state machine), § 15.1 (no KYC event family) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-3 (KYC FSM separate from Customer FSM; `not_required → pending → verified|rejected`; the `kyc`-Hold half is deferred), AC-K-J-7 (KYC required → verified path), AC-K-J-7a (enhanced-KYC trigger fields) · spec/04-decisions/decisions.md DEC-071 (KYC/sanctions fields nullable, added additively here), DEC-035 (enhanced-KYC threshold) · decisions/2026-06-17-producer-kyc-gate-not-required-clears.md (cleared-state semantics) · decisions/2026-06-12-event-substrate-and-audit-store.md (audit trail; no invented events). The `kyc` Hold auto-place/auto-lift coupling (AC-K-FSM-3 Hold half, AC-K-J-7) and the enhanced-KYC detection job (AC-K-J-7a) are deferred seams._

### Requirement: Customer Sanctions Screening Lifecycle

The Customer SHALL carry a **sanctions-screening lifecycle that is separate from both the Customer status FSM and the KYC FSM, and independent of KYC**: the four-state domain `pending → passed | failed | under_review` plus `under_review → passed | failed`, held in additive nullable fields (DEC-071) — `sanctions_status`, `last_screening_at`, `next_rescreen_at`, and the screening `trigger_source` (`onboarding | cadence | aml_threshold | compliance_ad_hoc`). A NULL `sanctions_status` denotes a Customer created un-screened and SHALL be treated, for any downstream purchase gate, as not-`passed` (blocked) — exactly like `pending`.

An explicit operator Action SHALL record each screening verdict (manual-first — the screen is the floor, the vendor integration is deferrable): it SHALL set `sanctions_status` to the verdict, stamp `last_screening_at`, set `next_rescreen_at` to the 12-month-forward moment, record the `trigger_source`, and (on a `passed`/`failed` completion) record the matching screening event per the *Sanctions Screening Events* requirement. A verdict carrying `trigger_source = onboarding` SHALL be the Customer's **first** screening (rejected with `IllegalSanctionsTransition` if `last_screening_at` is already set); every other `trigger_source` denotes a **re-screen**.

The sanctions lifecycle SHALL be **independent of KYC**: a sanctions transition SHALL NOT change `kyc_status` and a KYC transition SHALL NOT change `sanctions_status`; the two clear independently. The **enforcement** of `sanctions_status = passed` as a purchase precondition is **Module S's** at order completion (Module K is sanctions-blind by design) and is NOT in this change.

The **automated 12-month re-screen cadence** (the daily background job) and the **AML-threshold auto re-screen** (the cumulative-totals scan) are **deferred** (manual-first); the operator ad-hoc re-screen Action, the four events, the `trigger_source` field and the `next_rescreen_at` field ship now.

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

#### Scenario: Automated cadence and AML detection are deferred; the fields and ad-hoc path ship

- **WHEN** the Parties code surface is inspected
- **THEN** `last_screening_at`, `next_rescreen_at` and `trigger_source` exist and an operator can record an ad-hoc re-screen, but there is no daily cadence job and no cumulative-totals scan in this change (documented seams)

#### Scenario: An onboarding screening on an already-screened Customer is rejected

- **WHEN** a verdict with `trigger_source = onboarding` is recorded for a Customer whose `last_screening_at` is already set
- **THEN** an `IllegalSanctionsTransition` is raised and the sanctions state is unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer sanctions state, last-screening moment, next re-screen), § 9.2 (sanctions four-state lifecycle; EU+UIF+OFAC; 12-month cadence as a daily job; between-cycle trigger paths and trigger sources — DEC-030/DEC-035), § 9.3 (the order-completion gate is Module S — the single enforcement point; a Customer can exist `sanctions_status = pending`), § 9.4 (KYC and sanctions independent — both clear independently), § 9.5 (manual-first launch posture; the screen + gate are the floor, the integration is deferrable; acceptance drives state, not a live vendor) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-4 (sanctions FSM separate; `pending → passed/failed/under_review`, `under_review → passed/failed`; re-screening fires the events), AC-K-FSM-5 (KYC and sanctions independent), AC-K-EVT-12 (onboarding + rescreening events drive state), AC-K-EVT-12a (trigger source recorded; the cadence/AML automation is the seam) · spec/04-decisions/decisions.md DEC-071, DEC-030, DEC-041. The order-completion enforcement (AC-K-J-20) is Module S's; the cadence/AML automation is deferred._

### Requirement: Producer KYC Lifecycle

The Producer SHALL carry a **provenance-KYC lifecycle distinct from Customer KYC**: the four-state domain `not_required → pending → verified | rejected`, held in an additive nullable `kyc_status` field (DEC-071). A NULL `kyc_status` denotes a Producer never touched by KYC and SHALL be treated as **cleared** at the activation gate (so existing and never-screened Producers keep activating — see *Producer Lifecycle*).

Operator Actions SHALL be the sole writers of the Producer `kyc_status`: a require operation (`not_required`/NULL → `pending`), a verified operation (`pending → verified`), a rejected operation (`pending → rejected`), and a **waive** operation (→ `not_required`) — the operator "deselect" that clears the gate exactly as `verified`. KYC `verified` and `not_required` are the **cleared** states; `pending` and `rejected` block. Producer KYC changes record **no domain event** (the PRD names none); the cleared semantics are carried by `ProducerActivated` when activation fires. Every transition SHALL be **from-state guarded** and reject an out-of-state call with a localized `IllegalKycTransition`, leaving state unchanged.

#### Scenario: Require, verify, reject the Producer KYC

- **WHEN** the require operation is invoked on a Producer whose `kyc_status` is NULL or `not_required`
- **THEN** `kyc_status` becomes `pending`
- **WHEN** the Producer in `pending` is recorded `verified` (resp. `rejected`)
- **THEN** `kyc_status` becomes that state, and no domain event is recorded

#### Scenario: Operator waives Producer KYC to not_required

- **WHEN** the waive operation is invoked on a Producer in `pending` or `rejected`
- **THEN** `kyc_status` becomes `not_required` (a cleared state) — the operator-deselect that lets the Producer activate as if verified

#### Scenario: Producer KYC is distinct from Customer KYC

- **WHEN** the Producer entity is inspected
- **THEN** its `kyc_status` is a Producer-level field independent of any Customer KYC state

#### Scenario: Illegal Producer KYC transitions are rejected

- **WHEN** the verified or rejected operation is invoked on a Producer not in `pending`
- **THEN** an `IllegalKycTransition` is raised and `kyc_status` is unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.4 (Producer KYC four-state lifecycle; cleared = `verified` ∨ `not_required`; `not_required` ≡ `verified` at every gate; distinct from Customer KYC), § 14.5 BR-K-Producer-2 (KYC clearance gates Product Master activation), § 15.4 (`ProducerActivated` — KYC cleared) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-7 (Producer activation gated on KYC cleared — positive `verified`/`not_required`, negative `pending`/`rejected`) · spec/04-decisions/decisions.md DEC-071 · decisions/2026-06-17-producer-kyc-gate-not-required-clears.md (operator-waive via `not_required`; NULL treated as cleared for additivity)._

### Requirement: Sanctions Screening Events

Each sanctions screening **completion** SHALL record its **verbatim** Module K event through the platform `DomainEventRecorder`, within the same database transaction as the `sanctions_status` write, tagged module `parties`, the acting `actor_role` and id from the `ActorContext` seam, entity type `Customer` and id, and a **PII-free** payload (customer id plus the verdict / `trigger_source` enum values only — never name, email, phone or date of birth). The four event names are `CustomerOnboardingScreeningPassed`, `CustomerOnboardingScreeningFailed` (recorded when the **onboarding** screening completes), and `CustomerRescreeningPassed`, `CustomerRescreeningFailed` (recorded when any **re-screen** completes). A screening landing `under_review` is **not** a completion and SHALL record **no** event. No event name outside this set, and **no KYC event**, SHALL be recorded by this change.

#### Scenario: Onboarding completion records the onboarding event

- **WHEN** an onboarding screening completes `passed` (resp. `failed`)
- **THEN** `CustomerOnboardingScreeningPassed` (resp. `CustomerOnboardingScreeningFailed`) is recorded in the writing transaction, module `parties`, with a PII-free payload

#### Scenario: Re-screen completion records the rescreening event

- **WHEN** a re-screen (`trigger_source` cadence / aml_threshold / compliance_ad_hoc) completes `passed` (resp. `failed`)
- **THEN** `CustomerRescreeningPassed` (resp. `CustomerRescreeningFailed`) is recorded, PII-free

#### Scenario: under_review records no event

- **WHEN** a screening lands `under_review`
- **THEN** `sanctions_status` becomes `under_review` and no screening event is recorded; a later resolution to `passed`/`failed` records the corresponding `CustomerRescreening*` event

#### Scenario: No KYC event is recorded

- **WHEN** any KYC transition runs (Customer or Producer)
- **THEN** no domain event is recorded for it (audit only) — the PRD names no KYC event

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 15.6 (`CustomerOnboardingScreeningPassed`/`...Failed`, `CustomerRescreeningPassed`/`...Failed` — "the screening / re-screening pair is two events with two outcomes each"), § 15.1 (no KYC event family) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-EVT-12 (the four events drive sanctions state), AC-K-EVT-12a (trigger source recorded on the screening record) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional outbox; PII-free payloads) · openspec/specs/event-substrate/spec.md (Transactional Event Recording; Domain Event Envelope) · CLAUDE.md invariants 4 & 10._

## MODIFIED Requirements

### Requirement: Producer Lifecycle

The Producer SHALL transition through its state machine `draft → active → retired` (one operating direction; the FSM is linear) via explicit operator Actions that are the sole writers of `Producer.status`, each recording its lifecycle event in the same database transaction as the state write.

A Producer in `draft` SHALL transition to `active` on an `ActivateProducer` operation, recording **`ProducerActivated`**. Activation SHALL enforce the **KYC-cleared gate** (§ 4.4; BR-K-Producer-2): the Producer's `kyc_status` SHALL be **cleared** — `verified`, `not_required`, **or NULL** (a Producer never touched by KYC) — and the activation SHALL be **rejected** while `kyc_status` is `pending` or `rejected`, leaving the Producer in `draft` and recording no event. NULL is treated as cleared so the additive KYC field (DEC-071) does not break the activation of Producers created before this change; an operator may explicitly set `not_required` to **waive** KYC (ADR `2026-06-17-producer-kyc-gate-not-required-clears.md`). This closes the deferred seam the previously-shipped slice left ungated; `ProducerActivated` therefore fires on `draft → active` only when KYC is cleared (§ 15.4).

A Producer in `active` SHALL transition to `retired` on a `RetireProducer` operation, recording **`ProducerRetired`**, and SHALL **cascade**: every Club the Producer operates that is currently in `active` SHALL transition to `sunset` (recording its own `ClubSunset`, per the Club Lifecycle requirement) within the same transaction. Clubs already in `sunset` or `closed` SHALL be left unchanged (the cascade is idempotent over already-transitioned Clubs). The **Profile leg** of the § 10.2 offboarding cascade (per-Profile cancellation and the Module-S Club-Credit conversion signal) SHALL NOT be performed by this change — it is deferred with Profile lifecycle.

Every transition SHALL be **from-state guarded**: an `ActivateProducer` on a Producer not in `draft`, or a `RetireProducer` on a Producer not in `active`, SHALL be rejected with a localized `IllegalProducerTransition` and SHALL leave all state and the event log unchanged. The guard SHALL be evaluated against a transaction-locked re-read of the row so concurrent transition attempts cannot both succeed.

#### Scenario: Activate a draft Producer

- **WHEN** `ActivateProducer` is invoked on a Producer in `draft` whose `kyc_status` is cleared (`verified`, `not_required`, or NULL)
- **THEN** the Producer's status becomes `active` and a `ProducerActivated` event is recorded in the same transaction (module `parties`, entityType `Producer`, PII-free payload)

#### Scenario: Activation requires KYC cleared

- **WHEN** `ActivateProducer` is invoked on a `draft` Producer whose `kyc_status` is `verified`, `not_required`, or NULL
- **THEN** the activation succeeds and `ProducerActivated` is recorded
- **WHEN** `ActivateProducer` is invoked on a `draft` Producer whose `kyc_status` is `pending` or `rejected`
- **THEN** the activation is rejected, the Producer stays `draft`, and no `ProducerActivated` event is recorded

#### Scenario: Retire an active Producer cascades Club sunset

- **GIVEN** a Producer in `active` that operates two Clubs in `active` and one Club already in `closed`
- **WHEN** `RetireProducer` is invoked
- **THEN** the Producer's status becomes `retired` and a `ProducerRetired` event is recorded
- **AND** the two `active` Clubs transition to `sunset`, each recording a `ClubSunset` caused by the retirement, while the `closed` Club is left unchanged

#### Scenario: Illegal Producer transitions are rejected

- **WHEN** `ActivateProducer` is invoked on a Producer not in `draft`, or `RetireProducer` on a Producer not in `active`
- **THEN** an `IllegalProducerTransition` is raised, the Producer's status is unchanged, and no `ProducerActivated` / `ProducerRetired` (and no cascade `ClubSunset`) event is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.4 (Producer FSM `draft → active → retired`; **activation requires KYC cleared — `verified` or `not_required`**; retirement preserves Product Masters, blocks new activations) · § 10.2 (Producer offboarding cascade → Club sunset) · § 14.5 BR-K-Producer-2/4 · § 15.4 (`ProducerActivated`, `ProducerRetired`) · spec/04-decisions/decisions.md DEC-071 (KYC/sanctions fields nullable, added in compliance) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-7 (activation gated on KYC cleared — positive `verified`/`not_required`, negative `pending`/`rejected`), § 2 AC-K-J-10 / AC-K-J-19, § 5 AC-K-EVT-8, § 6 AC-K-XM-2 (Module 0 consumes these events to gate Product Master activation) · decisions/2026-06-17-producer-kyc-gate-not-required-clears.md (this change tightens the previously-deferred KYC gate; NULL treated as cleared) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional recording)._

### Requirement: Birth States Recorded, Lifecycle Transitions Deferred

Every Parties entity that carries a lifecycle state SHALL define its full state domain and SHALL be created in its birth state: Customer `pending`, Account `active`, Producer `draft`, Club `active`, ProducerAgreement `draft`, Profile `Applied` (Supplier carries no lifecycle state). The **supply-side** lifecycle — Producer, ProducerAgreement and Club — SHALL implement its state transitions and emit its lifecycle events, as governed by the Requirements *Producer Lifecycle*, *ProducerAgreement Lifecycle*, *Club Lifecycle* and *Supply-Side Lifecycle Events*. The **Customer and Producer compliance-screening lifecycles** — the KYC FSM and the Customer sanctions FSM, **each separate from the Customer/Producer status FSM** — SHALL now be implemented as governed by the Requirements *Customer KYC Lifecycle*, *Customer Sanctions Screening Lifecycle*, *Producer KYC Lifecycle* and *Sanctions Screening Events*; their fields are added additively (nullable — DEC-071). The **demand-side status** lifecycle SHALL remain deferred: there SHALL be no Customer, Account or Profile **status** transition (`pending → active → …`), no Profile approval/activation workflow, no producer membership approve/decline write, no Originating-Club lock (the `originating_club_id` field SHALL retain its no-mutation seam), no Hero Package Capacity Invariant, and no Customer-segment derivation — and consequently no demand-side status-change domain event (for example `CustomerActivated`, `ProfileActivated`, `OriginatingClubLocked`, `CustomerSegmentChanged`) SHALL be emitted, until the demand-side change(s) implement them. The **`kyc` Hold coupling** (auto-place on KYC `pending`, auto-lift on `verified`) and the unified Hold registry SHALL remain deferred to `parties-holds`.

#### Scenario: Each entity is born in its birth state

- **WHEN** a Customer, Account, Producer, Club, ProducerAgreement or Profile is created
- **THEN** its state is, respectively, `pending`, `active`, `draft`, `active`, `draft`, `Applied`

#### Scenario: Supply-side and compliance transitions exist; demand-side status transitions do not

- **WHEN** the Parties code surface is inspected
- **THEN** Producer, ProducerAgreement and Club expose lifecycle-transition operations and record their lifecycle events, and the Customer/Producer KYC and Customer sanctions screening FSMs expose their transitions
- **AND** Customer, Account and Profile expose no operation that transitions their **status** out of its birth state, the `originating_club_id` field has no mutation surface, no demand-side status event (`CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked` / `CustomerSegmentChanged`) is recordable, and no `kyc` Hold is placed (the Hold registry is deferred to `parties-holds`)

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 / § 4.2.1 / § 4.3 / § 4.4 / § 4.6.1 / § 4.7 (per-entity state machines + birth states) · § 9.1 / § 9.2 (KYC and sanctions screening FSMs — separate from the Customer status FSM) · § 6.1 (the Originating-Club lock fires on first approval — demand-side) · § 13 (Hero Package Capacity Invariant — demand-side) · § 5 (Customer segments — demand-side) · § 4.8 (the `kyc` Hold — owned by the Hold registry) · § 15 (lifecycle event families) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-3 / AC-K-FSM-4 (KYC + sanctions FSMs separate from the Customer FSM) · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 2 (Parties compliance floor in Phase 2) · openspec/changes/archive/2026-06-15-parties-core/proposal.md + openspec/changes/archive/2026-06-16-parties-producer-lifecycle/proposal.md (the supply-side and demand-side slice boundaries; compliance is this change; demand-side status follows)._
