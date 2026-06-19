## ADDED Requirements

### Requirement: Profile Membership Approval

The Profile SHALL transition `Applied → Approved` (membership approval) and `Applied → Rejected` (membership decline) via explicit operator Actions — `ApproveProfile` and `DeclineProfile` — that are the sole writers of the Profile `state` for these transitions, each running inside one `DB::transaction` against a transaction-locked re-read of the Profile row. These realize **the one retained producer write** ("membership approve/decline", L-PP / K-Q4); the producer-facing HTTP surface is deferred and the Actions are operator/console-invocable at launch (admin-parity, DEC-083).

`ApproveProfile` on a Profile in `Applied` SHALL set `state = Approved`. `DeclineProfile` on a Profile in `Applied` SHALL set `state = Rejected` — a terminal-for-this-application state; a re-application creates a **new** Profile row (per the *Profile — Multi-Profile Membership* requirement), and because the Customer–Club partial-unique index already excludes the terminal `rejected` state, the new `Applied` row inserts with **no** index migration.

Neither approval nor decline SHALL record a Profile lifecycle domain event: the PRD § 15.2 event catalog **names no `ProfileApproved` or `ProfileRejected`**, so — exactly as a KYC transition records no KYC event (audit trail only — the shipped *Customer KYC Lifecycle* precedent) — the state change is captured in the append-only audit trail only. The **sole** domain event the approve path MAY record is the conditional `OriginatingClubLocked`.

On `ApproveProfile`, **if and only if** this is the Customer's **first-ever** Profile approval across any Club — detected by the Customer's `originating_club_id` being currently unset (re-read under a transaction lock) — the Action SHALL, in the same transaction, set `Customer.originating_club_id` to the approving Profile's `club_id` and record an `OriginatingClubLocked` event (per the *Demand-Side Activation Events* requirement). The lock SHALL be **one-shot** (a subsequent approval on another Club neither re-fires the event nor changes the link), **immutable** thereafter (no admin-override surface at launch), and MAY remain unset indefinitely for a Customer never approved into any Club (DEC-040). The Originating-Club lock SHALL NOT be a standalone Action — it is exclusively an in-transaction side-effect of `ApproveProfile`.

The **Hero Package Capacity Invariant** (§ 13) — the membership no-oversell guard the PRD enforces "at every membership approval" — reads the cap from Module A's Hero-Package Allocation `qty` (§ 13.2 / AC-K-XM-18); Module A is unbuilt, so this slice ships `ApproveProfile` **uncapped** with the capacity gate as a documented deferred Module-A seam (the `Applied → WaitingList` capacity-exceeded path is likewise deferred). Every transition SHALL be **from-state guarded** against the transaction-locked re-read: an `ApproveProfile` or `DeclineProfile` on a Profile not in `Applied` SHALL be rejected with a localized `IllegalProfileTransition`, leaving state, the Originating-Club link and the event log unchanged.

#### Scenario: Approve a first-ever applied Profile locks the Originating Club

- **GIVEN** a Customer whose `originating_club_id` is unset, with a Profile in `Applied` for Club C
- **WHEN** `ApproveProfile` is invoked on that Profile
- **THEN** the Profile's `state` becomes `Approved`, the Customer's `originating_club_id` is set to Club C, and exactly one `OriginatingClubLocked` event is recorded in the same transaction (and no `ProfileApproved` event, which the catalog does not name)

#### Scenario: A second Club's approval does not re-lock or re-fire

- **GIVEN** a Customer whose `originating_club_id` is already set to Club C, with a Profile in `Applied` for a different Club D
- **WHEN** `ApproveProfile` is invoked on the Club-D Profile
- **THEN** that Profile's `state` becomes `Approved`, the Customer's `originating_club_id` stays Club C, and **no** `OriginatingClubLocked` event is recorded

#### Scenario: Decline an applied Profile is terminal and event-silent

- **WHEN** `DeclineProfile` is invoked on a Profile in `Applied`
- **THEN** the Profile's `state` becomes `Rejected`, **no** domain event is recorded, and a subsequent re-application for the same Customer–Club pair creates a new Profile in `Applied` (the partial-unique index admits it)

#### Scenario: Illegal approve/decline is rejected

- **WHEN** `ApproveProfile` or `DeclineProfile` is invoked on a Profile not in `Applied`
- **THEN** an `IllegalProfileTransition` is raised, the Profile's `state` and the Customer's `originating_club_id` are unchanged, and no event is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1 (Profile FSM `Applied → Approved | Rejected`; rejected not reused — a new Profile row), § 3.1 (the one retained producer write — membership approve/decline, L-PP / K-Q4; producer UI deferred, admin-parity), § 6 / § 6.1 (the Originating-Club one-shot lock at first `MembershipApprovedByProducer`; immutable; may stay unset — DEC-040), § 13 / § 13.2 (the Hero Package Capacity Invariant enforced at every membership approval; the cap lives on Module A's Allocation `qty` — deferred Module-A seam), § 15.2 (the Profile event family names no `ProfileApproved` / `ProfileRejected`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-2 (Profile FSM traversal), AC-K-J-4 (first-Club approval → `OriginatingClubLocked` fires once, sets the OC to the approving Club, later approvals do not re-fire), AC-K-BR-OC-1/2/3 (set at first approval; immutable, no admin-override; may stay unset), AC-K-J-13 / AC-K-XM-18 (capacity at approval reads Module A `qty`) · spec/04-decisions/decisions.md DEC-040 (OC nullable one-shot), DEC-083 (admin-parity), DEC-073 (physical representation) · decisions/2026-06-12-event-substrate-and-audit-store.md (audit trail; transactional, PII-free recording) · openspec/specs/party-registry/spec.md (the *Profile — Multi-Profile Membership* + *Customer Identity* seams this closes)._

### Requirement: Profile Activation

The Profile SHALL transition `Approved → Active` via an explicit `ActivateProfile` Action — the sole writer of the Profile `state` for this transition — running inside one `DB::transaction` against a transaction-locked re-read, recording a `ProfileActivated` event (per the *Demand-Side Activation Events* requirement) in the same transaction. In production this transition is driven by Module E's `MembershipFeePaid` event (the membership-fee-paid signal) or a free-club activation where no fee applies (§ 4.2.1); Module E does not exist, so the **`MembershipFeePaid` listener is a deferred Module-E seam** — `ActivateProfile` is the within-module writer, invoked by the free-club / operator path now, and **no** Module-E event contract is fabricated. The Hero Package Capacity Invariant gate at the transition-into-`Active` (§ 13.1) reads Module A's Allocation `qty` and is the **same deferred Module-A seam** as approval: `ActivateProfile` ships **uncapped**. Every transition SHALL be **from-state guarded**: an `ActivateProfile` on a Profile not in `Approved` SHALL be rejected with a localized `IllegalProfileTransition`, leaving state and the event log unchanged.

#### Scenario: Activate an approved Profile

- **WHEN** `ActivateProfile` is invoked on a Profile in `Approved`
- **THEN** the Profile's `state` becomes `Active` and exactly one `ProfileActivated` event is recorded in the same transaction (module `parties`, `entity_type` `Profile`, PII-free payload)

#### Scenario: Illegal activation is rejected

- **WHEN** `ActivateProfile` is invoked on a Profile not in `Approved` (e.g. `Applied` or already `Active`)
- **THEN** an `IllegalProfileTransition` is raised, the Profile's `state` is unchanged, and no `ProfileActivated` event is recorded

#### Scenario: The membership-fee trigger is a deferred seam

- **WHEN** the Parties code surface is inspected
- **THEN** `ActivateProfile` exists as the within-module writer of `Approved → Active`, and there is **no** `MembershipFeePaid` listener and no fabricated Module-E event class in this change (the cross-module trigger is a documented Module-E seam)

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1 (`Approved → Active` on Module E's `MembershipFeePaid`, or free-club activation where no fee applies), § 13.1 (the capacity invariant is evaluated at every transition into `Active` — the cap on Module A's Allocation `qty`, deferred), § 15.2 (`ProfileActivated` on the transition; "Module K consumes Module E's `MembershipFeePaid` to drive this"), § 15.8 (`MembershipFeePaid` is a Module E event Module K consumes) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-2 (`ProfileActivated` fires on the transition), AC-K-EVT-5 · openspec/changes/archive/2026-06-16-parties-producer-lifecycle (the precedent: ship the transition with the upstream trigger/gate as a documented seam) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional recording)._

### Requirement: Customer Onboarding Activation

The Customer SHALL transition `pending → active` via an explicit `ActivateCustomer` Action — the sole writer of the Customer `status` for this transition — running inside one `DB::transaction` against a transaction-locked re-read, recording a `CustomerActivated` event (per the *Demand-Side Activation Events* requirement) in the same transaction. Activation SHALL be a **hard composite gate** — the four onboarding gates plus the KYC-cleared rider: `email_verified_at` set **∧** `tc_accepted_at` set **∧** `privacy_accepted_at` set **∧** `sanctions_status = passed` **∧** KYC **cleared** (`kyc_status` ∈ {`verified`, `not_required`} or NULL) whenever `kyc_required` is set. The three acceptance moments SHALL be **additive nullable timestamp** columns on `parties_customers` (the gate inputs — § 4.1 acceptance is "tracked at Customer level with timestamps"; DEC-071 additive pattern; the physical column shape is the dev-team realization, DEC-073), born `NULL` and written by the (deferred) registration surface or an operator.

Activation SHALL be **explicit**: no sanctions screening verdict, KYC transition, or acceptance write SHALL auto-transition the Customer `status`. The Customer status FSM is separate from and independent of the KYC and sanctions FSMs (§ 9.4), and activation/suspension are explicit — never automatically driven by another FSM or by Profile state (AC-K-BR-Customer-1). Customer activation SHALL perform **no** Account transition: the Account is born `active` (it has no `pending` state — AC-K-FSM-9).

A gate-unmet `ActivateCustomer` (any of the five conditions unmet), or an `ActivateCustomer` on a Customer not in `pending`, SHALL be rejected with a localized `IllegalCustomerTransition`, leaving `status = pending` and the event log unchanged.

#### Scenario: Activate a Customer once all gates clear

- **GIVEN** a Customer in `pending` with `email_verified_at`, `tc_accepted_at` and `privacy_accepted_at` all set, `sanctions_status = passed`, and KYC cleared (or `kyc_required` unset)
- **WHEN** `ActivateCustomer` is invoked
- **THEN** the Customer's `status` becomes `active` and exactly one `CustomerActivated` event is recorded in the same transaction (module `parties`, `entity_type` `Customer`, PII-free payload), and the Customer's Account `status` is unchanged

#### Scenario: Any unmet gate blocks activation

- **WHEN** `ActivateCustomer` is invoked on a `pending` Customer with any one of the gates unmet — `email_verified_at` null, or `tc_accepted_at` null, or `privacy_accepted_at` null, or `sanctions_status ≠ passed`, or `kyc_required` set while `kyc_status` is `pending`/`rejected`
- **THEN** an `IllegalCustomerTransition` is raised, the Customer stays `pending`, and no `CustomerActivated` event is recorded

#### Scenario: Activation is explicit, not auto-driven by another FSM

- **WHEN** a Customer's sanctions screening is recorded `passed`, or a KYC transition runs, with no `ActivateCustomer` call
- **THEN** the Customer `status` is unchanged (no auto-activation) — the status FSM is independent of the KYC and sanctions FSMs

#### Scenario: Illegal from-state is rejected

- **WHEN** `ActivateCustomer` is invoked on a Customer not in `pending`
- **THEN** an `IllegalCustomerTransition` is raised and the Customer `status` is unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer FSM `pending → active`; `active` once email verified + T&C/privacy accepted + KYC cleared if required; acceptance a hard gate, tracked at Customer level with timestamps), § 7.1 (onboarding flow; the sanctions screen passing completes `pending → active` when the other gates are met), § 9.4 (KYC / sanctions / status FSMs independent), § 4.7 (Account born `active`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-J-1 (`pending → active` only after all four gates clear — email verification, T&C, privacy, sanctions), AC-K-BR-Identity-3 (acceptance a hard gate alongside email verification and KYC clearance), AC-K-FSM-1 (Customer FSM + `CustomerActivated`), AC-K-BR-Customer-1 (activation/suspension explicit, not auto-driven by Profile state), AC-K-FSM-9 (Account `active`, no `pending`), AC-K-EVT-1 (`CustomerActivated` on `pending → active`) · spec/04-decisions/decisions.md DEC-071 (additive nullable fields), DEC-073 (physical representation delegated to the dev team) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional, PII-free recording)._

### Requirement: Demand-Side Activation Events

Each demand-side activation transition SHALL record its **verbatim** Module K event — `CustomerActivated` (on Customer `pending → active`), `ProfileActivated` (on Profile `Approved → Active`), `OriginatingClubLocked` (on the Customer's first-ever Profile approval) — through the platform `DomainEventRecorder`, **within the same database transaction** as the state write, tagged with module `parties`, the acting `actor_role` + id resolved from the `ActorContext` seam, the entity type and id, and a **PII-free** payload (entity ids + enum/business values only — never name, email, phone or date of birth). `CustomerActivated` SHALL carry `entity_type = 'Customer'` with payload `{customer_id, status}`; `ProfileActivated` SHALL carry `entity_type = 'Profile'` with payload `{profile_id, state}`; `OriginatingClubLocked` SHALL carry `entity_type = 'Customer'` with payload `{customer_id, club_id, profile_id, locked_at}` — the Customer, the locking Club, the triggering membership and the moment (§ 6.1 verbatim).

The `ApproveProfile` and `DeclineProfile` transitions SHALL record **no** Profile event of their own (audit-only — § 15.2 names none). **No** event name outside this three-name set SHALL be recorded by this change. Each of the three events SHALL be recorded as a **root** event (no `causation_id`; `correlation_id` defaults to its own `event_id`), since the transition it records has no parent event in the same transaction. The downstream consumers `OriginatingClubLocked` names — Module S settlement-eligibility, Module E D19 accrual, HubSpot (§ 6 / § 15.6 / AC-K-EVT-10) — are **not** wired by this change: Module K records the event (the capture); all consumption is downstream and deferred.

#### Scenario: Each activation transition records its verbatim event PII-free

- **WHEN** any of the three activation transitions runs (`ActivateCustomer`, `ActivateProfile`, or the first-approval `ApproveProfile`)
- **THEN** exactly its corresponding event (`CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked`) is recorded in the writing transaction, tagged module `parties`, with the entity type/id and an `actor_role` from `ActorContext`, and its payload contains only entity ids and enum/business values (no name, email, phone or date of birth)

#### Scenario: OriginatingClubLocked carries the four spec fields

- **WHEN** `OriginatingClubLocked` is recorded
- **THEN** its payload is exactly `{customer_id, club_id, profile_id, locked_at}` (the Customer, the locking Club, the triggering membership, and the moment), `entity_type` `Customer`, PII-free

#### Scenario: Approve and decline record no Profile event; nothing outside the set fires

- **WHEN** `ApproveProfile` (on a non-first approval) or `DeclineProfile` runs
- **THEN** no Profile lifecycle event is recorded, and across the whole change no event name outside `{CustomerActivated, ProfileActivated, OriginatingClubLocked}` is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 15.1 (`CustomerActivated` on `pending → active`), § 15.2 (`ProfileActivated` on `Approved → Active`; the family names no `ProfileApproved` / `ProfileRejected`), § 15.6 (`OriginatingClubLocked` — payload Customer / locking Club / moment / triggering membership; consumers Module S / E / HubSpot), § 6.1 (the OC payload, verbatim) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-EVT-1 (`CustomerActivated`), AC-K-EVT-5 (`ProfileActivated`), AC-K-EVT-10 (`OriginatingClubLocked` payload fields + downstream consumers) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional outbox; PII-free payloads; root `correlation_id` = own `event_id`) · openspec/specs/event-substrate/spec.md (Transactional Event Recording; Domain Event Envelope) · CLAUDE.md invariants 4 & 10._

## MODIFIED Requirements

### Requirement: Customer Identity

The Customer SHALL be NewCo's **natural-person** registry (B2C only; the record carries no B2C/B2B discriminator). A Customer's email SHALL be **globally unique** across all Customers, and a creation whose email collides with an existing Customer SHALL be rejected. A Customer SHALL be created in the `pending` state, SHALL carry the immutable party-type marker `customer`, a preferred currency (an ISO 4217 code from the launch set) and a preferred locale (from the launch set). A Customer SHALL carry an `originating_club_id` reference into the Club registry that is **created `NULL`** and is **set one-shot at the Customer's first-ever Profile approval** (per the *Profile Membership Approval* requirement) — **immutable thereafter** (no admin-override surface at launch) and permitted to **remain `NULL` indefinitely** for a Customer never approved into any Club (DEC-040). A Customer SHALL record a `CustomerCreated` domain event on creation whose payload is **PII-free** (no name, email, phone or date of birth).

#### Scenario: Create a Customer

- **WHEN** an operator creates a Customer with a unique email, a preferred currency and a preferred locale
- **THEN** it is persisted in `pending`, carrying the immutable party-type marker `customer` and an `originating_club_id` of `NULL`, and a `CustomerCreated` event is recorded with a payload that contains no name, email, phone or date of birth

#### Scenario: Duplicate email is rejected

- **WHEN** a Customer is created with an email that matches an existing Customer
- **THEN** the creation is rejected; two distinct emails both succeed

#### Scenario: The Originating Club is born unset and locked one-shot at first approval

- **WHEN** a newly created Customer is inspected
- **THEN** its `originating_club_id` is `NULL`
- **WHEN** that Customer's first-ever Profile is approved (per *Profile Membership Approval*)
- **THEN** `originating_club_id` is set one-shot to the approving Club and is immutable thereafter; a Customer never approved into any Club keeps it `NULL`

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer — natural-person registry; born `pending`; unique email; preferred currency/locale; no B2C/B2B discriminator) · § 6 / § 6.1 (Originating Club — set one-shot at first approval, immutable, may stay unset; the lock fires on the approval write) · § 14.1 BR-K-Identity-1/5 · § 15.1 (`CustomerCreated`) · spec/04-decisions/decisions.md DEC-066 / DEC-040 (OC = FK to Club, one-shot, nullable) · DEC-068 / DEC-017 (B2C only; no discriminator) · DEC-071 (sanctions/KYC fields nullable — Customers creatable un-screened) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 2 AC-K-J-1, AC-K-J-4 (first-approval OC lock), AC-K-BR-OC-1/2/3, § 4 AC-K-BR-Identity-1, AC-K-XM-25, § 5 AC-K-EVT-1 · decisions/2026-06-12-event-substrate-and-audit-store.md (PII-free payloads) · openspec/changes/parties-membership-activation (the one-shot lock that closes the parties-core no-mutation seam)._

### Requirement: Birth States Recorded, Lifecycle Transitions Deferred

Every Parties entity that carries a lifecycle state SHALL define its full state domain and SHALL be created in its birth state: Customer `pending`, Account `active`, Producer `draft`, Club `active`, ProducerAgreement `draft`, Profile `Applied` (Supplier carries no lifecycle state). The **supply-side** lifecycle — Producer, ProducerAgreement and Club — SHALL implement its state transitions and emit its lifecycle events, as governed by the Requirements *Producer Lifecycle*, *ProducerAgreement Lifecycle*, *Club Lifecycle* and *Supply-Side Lifecycle Events*. The **Customer and Producer compliance-screening lifecycles** — the KYC FSM and the Customer sanctions FSM, **each separate from the Customer/Producer status FSM** — SHALL be implemented as governed by the Requirements *Customer KYC Lifecycle*, *Customer Sanctions Screening Lifecycle*, *Producer KYC Lifecycle* and *Sanctions Screening Events*; their fields are added additively (nullable — DEC-071). The **demand-side activation** lifecycle is now implemented (the Requirements *Customer Onboarding Activation*, *Profile Membership Approval*, *Profile Activation* and *Demand-Side Activation Events*): the Customer `pending → active` transition, the Profile `Applied → Approved | Rejected` and `Approved → Active` transitions, and the Originating-Club one-shot lock — the `originating_club_id` field now has its mutation surface — exist and emit `CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked` (approval and decline are themselves audit-only — § 15.2 names no `ProfileApproved` / `ProfileRejected`). The **remaining demand-side status** lifecycle SHALL remain deferred: there SHALL be no `Active → Suspended | Lapsed | Cancelled | Inactive` Profile transition, no Account status transition (`ActivateAccount` / `SuspendAccount` / `CloseAccount`), no Customer `active → suspended | closed` transition, no **Hero Package Capacity Invariant** (approval and activation ship **uncapped** — the Module-A seam), no `Applied → WaitingList` path, and no Customer-segment derivation — and consequently no further demand-side status event (for example `CustomerSuspended`, `ProfileSuspended`, `ProfileExpired`, `WaitingListJoined`, `CustomerSegmentChanged`) SHALL be emitted, until the follow-on demand-side change(s) implement them. The **`kyc` Hold coupling** (auto-place on KYC `pending`, auto-lift on `verified`) and the **unified Hold registry** are implemented (the Requirements *Hold Registry*, *Hold Lifecycle and Lift Discipline*, *Hold Events* and *Hold and Sanctions Read-API*); the Hold→`suspended` **status** coupling, however, remains deferred with the remaining demand-side status FSMs (placing a Hold records the Hold but performs no status transition).

#### Scenario: Each entity is born in its birth state

- **WHEN** a Customer, Account, Producer, Club, ProducerAgreement or Profile is created
- **THEN** its state is, respectively, `pending`, `active`, `draft`, `active`, `draft`, `Applied`

#### Scenario: Supply-side, compliance, Hold and demand-side activation transitions exist; the remaining demand-side status transitions do not

- **WHEN** the Parties code surface is inspected
- **THEN** Producer, ProducerAgreement and Club expose lifecycle-transition operations and record their lifecycle events; the Customer/Producer KYC and Customer sanctions screening FSMs expose their transitions; the unified Hold registry exposes place/lift with the `kyc` Hold auto-placed on KYC `pending` and auto-lifted on `verified`; AND the demand-side **activation** transitions exist — Customer `pending → active` (`ActivateCustomer`), Profile `Applied → Approved | Rejected` (`ApproveProfile` / `DeclineProfile`) and `Approved → Active` (`ActivateProfile`), with the Originating-Club one-shot lock — recording `CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked`
- **AND** Customer `active → suspended | closed`, Account status transitions, Profile `Active → Suspended | Lapsed | Cancelled | Inactive`, the Hold→`suspended` coupling (placing a Hold performs no status transition), the Hero Package Capacity Invariant (approval and activation are uncapped), the `Applied → WaitingList` path, and Customer-segment derivation do **not** exist, and no `CustomerSuspended` / `ProfileSuspended` / `WaitingListJoined` / `CustomerSegmentChanged` event is recordable

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 / § 4.2.1 / § 4.3 / § 4.4 / § 4.6.1 / § 4.7 (per-entity state machines + birth states) · § 4.8 / § 4.8.1 (the unified Hold registry + the `kyc` Hold coupling — implemented) · § 9.1 / § 9.2 (KYC and sanctions screening FSMs) · § 6.1 (the Originating-Club one-shot lock — now implemented) · § 4.2.1 (Profile approval/activation — now implemented; the capacity gate deferred) · § 13 (Hero Package Capacity Invariant — deferred Module-A seam) · § 5 (Customer segments — deferred) · § 10.1 (Hold→suspension coupling — deferred with the remaining status FSMs) · § 15 (lifecycle event families) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-1 (Customer FSM + `CustomerActivated`), AC-K-FSM-2 (Profile FSM), AC-K-J-1 (Customer activation gates), AC-K-J-4 (first-approval OC lock), AC-K-EVT-1 / AC-K-EVT-5 / AC-K-EVT-10 (the three activation events), AC-K-FSM-3 / AC-K-FSM-4 (KYC + sanctions FSMs separate), AC-K-FSM-9 (Account `active`, no `pending`; Hold→Account-suspension deferred), AC-K-FSM-10 / AC-K-FSM-11 (Hold lifecycle + lift discipline), AC-K-J-13 / AC-K-XM-18 (Hero Package capacity reads Module A `qty` — deferred) · spec/05-release/Build_Workplan_v0.3-MVP.md § Phase 2 (the demand-side membership write + OC immutability are Phase-2 deliverables) · openspec/changes/parties-membership-activation/proposal.md (the activation subset implemented here; the remaining demand-side lifecycle deferred to follow-on changes)._
