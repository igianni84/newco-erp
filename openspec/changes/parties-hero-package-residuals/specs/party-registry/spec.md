## MODIFIED Requirements

### Requirement: Profile Membership Approval

The Profile SHALL transition `Applied → Approved → Active`, **and equally `WaitingList → Approved → Active`**, as a **single atomic membership-approval operation** (`ApproveProfile`); and `Applied → Rejected` / `WaitingList → Rejected` (membership decline, `DeclineProfile`) — each the sole writer of the Profile `state` for its transition, each running inside one `DB::transaction` against a transaction-locked re-read of the Profile row. **`Approved` is a transient pass-through state, never a durable resting state** (canon MVP-DEC-016): a Customer is never observably left sitting in `Approved`. These Actions realize **the one retained producer write** ("membership approve/decline", L-PP / K-Q4); the producer-facing HTTP surface is deferred and the Actions are operator/console-invocable at launch (admin-parity, DEC-083). A `WaitingList → Active` conversion is **the same atomic instant** as an approval from `Applied`, subject to the same capacity gate and the same Originating-Club one-shot rule — it is **not** a distinct Action (per the *WaitingList Placement, Conversion and Decline* requirement).

`ApproveProfile` SHALL, in one transaction and **in this order**: re-read the Profile row under a transaction lock; **assert the from-state** is `Applied` or `WaitingList`; acquire a **row-level lock on the Club row** (`parties_clubs`); count the Club's seat occupancy (`Active` + `Suspended`); read the Club's Hero-Package capacity through the Module-K-owned capacity read port (per the *Hero Package Capacity Is Read from Module A, Never Stored in Module K* requirement); and then, **only if a seat is free** (or the capacity is `null`, meaning uncapped): write `state = Approved`; perform the conditional Originating-Club one-shot lock (below); and drive the `Approved → Active` activation through the within-module `ActivateProfile` writer (per the *Profile Activation* requirement), recording `ProfileActivated` — leaving the Profile in `Active`.

**The from-state guard SHALL precede the Club-row lock, and that ordering is normative.** An `ApproveProfile` invoked on a Profile in a state outside `{Applied, WaitingList}` SHALL be rejected **before any Club row is locked** — a doomed call SHALL NOT serialise a Club against healthy concurrent approvals — and SHALL **never** be diverted onto the waiting list merely because its Club happens to be at capacity. The at-capacity branch SHALL be reachable **only** from `Applied` (which diverts) and from `WaitingList` (which is rejected).

**When the Club is at capacity**, `ApproveProfile` SHALL NOT raise `IllegalProfileTransition` for a Profile in `Applied`: it SHALL instead transition the Profile to `WaitingList`, record exactly one `WaitingListJoined` event, take **no** charge, perform **no** Originating-Club lock, and record **no** `ProfileActivated`. For a Profile already in `WaitingList` whose Club is **still** at capacity, `ApproveProfile` SHALL be rejected with a localized `IllegalProfileTransition` naming the capacity reason — there is no transition to make — leaving the state, the Originating-Club link and the event log unchanged, and recording **no** second `WaitingListJoined`.

The **Club-row lock SHALL be acquired before the occupancy count**, so that concurrent approvals into the same Club serialise and the no-oversell invariant holds under concurrency; the Profile-row lock alone is **not** sufficient (two approvals of different Profiles in one Club lock different rows). Approvals into **different** Clubs SHALL remain parallel.

In production the activation is gated on the **charge-on-approval capture**: producer approval atomically triggers the Hero-Package-fee charge, and the Module-S `MembershipFeePaid` capture signal drives the activation (MVP-DEC-016 — approval = charge = `Active`). **No payment infrastructure exists at launch** (Module S/E are stubs; no mandate, no instrument, no invoice entity), so the charge is a **documented no-op seam** and the activation is unconditional once the capacity gate passes. The **charge-fail contract** (canon-specified, the Module-S target) SHALL be: a charge that fails at approval → the Profile stays `Applied` (NOT a new or `Rejected` state), **no** `Approved`/`Active` transition, **no** Hero-Package seat consumed, **no** `OriginatingClubLocked`; the approval is re-attemptable.

`DeclineProfile` on a Profile in `Applied` **or `WaitingList`** SHALL set `state = Rejected` — a terminal-for-this-application state; a re-application creates a **new** Profile row (per the *Profile — Multi-Profile Membership* requirement), and because the Customer–Club partial-unique index already excludes the terminal `rejected` state, the new `Applied` (or `WaitingList`) row inserts with **no** index migration. `DeclineProfile` SHALL take **no** Club-row lock and SHALL read no capacity: a decline frees no seat and consumes none.

Neither the `→ Approved` approval write nor the `→ Rejected` decline write SHALL record a Profile **approval/decline** domain event: the PRD § 15.2 event catalog **names no `ProfileApproved` or `ProfileRejected`**, so the state change is captured in the append-only audit trail only. Across a **successful** atomic `ApproveProfile` the **only** domain events recorded are therefore the conditional `OriginatingClubLocked` (first-ever approval) and the `ProfileActivated` of the atomic activation; across a **capacity-diverted** `ApproveProfile` the only domain event recorded is `WaitingListJoined`.

On `ApproveProfile`, **if and only if** this is the Customer's **first-ever** Profile approval across any Club — detected by the Customer's `originating_club_id` being currently unset (re-read under a transaction lock) — the Action SHALL, in the same transaction, set `Customer.originating_club_id` to the approving Profile's `club_id` and record an `OriginatingClubLocked` event. The lock SHALL fire **only on an approval that reaches `Active`** — a capacity-diverted approval SHALL NOT lock the Originating Club. It SHALL be **one-shot** (a subsequent approval on another Club neither re-fires the event nor changes the link), **immutable** thereafter (no admin-override surface at launch), and MAY remain unset indefinitely for a Customer never approved into any Club (DEC-040). The Originating-Club lock SHALL NOT be a standalone Action — it is exclusively an in-transaction side-effect of `ApproveProfile`; the atomic `Approved → Active` activation SHALL NOT write `originating_club_id`.

Every transition SHALL be **from-state guarded** against the transaction-locked re-read: an `ApproveProfile` or `DeclineProfile` on a Profile in **neither** `Applied` **nor** `WaitingList` SHALL be rejected with a localized `IllegalProfileTransition`, leaving state, the Originating-Club link and the event log unchanged.

#### Scenario: Approve a first-ever applied Profile activates it atomically and locks the Originating Club

- **GIVEN** a Customer whose `originating_club_id` is unset, with a Profile in `Applied` for Club C, which has a free Hero-Package seat
- **WHEN** `ApproveProfile` is invoked on that Profile
- **THEN** the Profile's `state` becomes `Active` in one operation (passing transiently through `Approved`, never resting there), the Customer's `originating_club_id` is set to Club C, and exactly two demand-side events are recorded in the same transaction — one `OriginatingClubLocked` and one `ProfileActivated` — and **no** `ProfileApproved` event (which the catalog does not name)

#### Scenario: A second Club's approval activates without re-locking the Originating Club

- **GIVEN** a Customer whose `originating_club_id` is already set to Club C, with a Profile in `Applied` for a different Club D that has a free seat
- **WHEN** `ApproveProfile` is invoked on the Club-D Profile
- **THEN** that Profile's `state` becomes `Active` atomically, the Customer's `originating_club_id` stays Club C, exactly one `ProfileActivated` is recorded, and **no** second `OriginatingClubLocked` event is recorded

#### Scenario: Approval leaves no durable Approved resting state

- **WHEN** any successful `ApproveProfile` completes
- **THEN** the persisted Profile is in `Active`, never `Approved` — `Approved` is a transient pass-through within the one transaction, so no query ever observes a Profile durably resting in `Approved`

#### Scenario: An approval at capacity diverts to WaitingList instead of throwing

- **GIVEN** a Customer whose `originating_club_id` is unset, with a Profile in `Applied` for a Club at exactly its capacity
- **WHEN** `ApproveProfile` is invoked on that Profile
- **THEN** no exception is raised; the Profile's `state` becomes `WaitingList`, exactly one `WaitingListJoined` event is recorded, and **no** `ProfileActivated` and **no** `OriginatingClubLocked` event is recorded, leaving the Customer's `originating_club_id` unset

#### Scenario: Approving a still-waitlisted Profile at capacity is rejected

- **GIVEN** a Profile in `WaitingList` whose Club is still at exactly its capacity
- **WHEN** `ApproveProfile` is invoked
- **THEN** a localized `IllegalProfileTransition` naming the capacity reason is raised, the Profile stays `WaitingList`, and no event — in particular no second `WaitingListJoined` — is recorded

#### Scenario: An out-of-state approve is rejected before any Club row is locked

- **GIVEN** a Profile in a state outside `{Applied, WaitingList}` (for example `Active` or `Lapsed`), in a Club that is at exactly its Hero-Package capacity
- **WHEN** `ApproveProfile` is invoked on that Profile
- **THEN** a localized `IllegalProfileTransition` naming the **from-state** reason is raised — **not** the capacity reason — the Profile is **not** moved to `WaitingList`, and **no** statement against `parties_clubs` is issued (the from-state guard ran before the Club-row lock, so a doomed call serialises nothing)
- **AND** the same holds whether that Club is at capacity, has a free seat, or is explicitly uncapped — the rejection is independent of the capacity gate that never ran

#### Scenario: Concurrent approvals into one Club serialise on the Club row

- **GIVEN** a Club with exactly one free seat and two Profiles in `Applied` for it
- **WHEN** two concurrent transactions each invoke `ApproveProfile`, one per Profile, on an engine honouring row-level locks
- **THEN** exactly one Profile becomes `Active` and the other lands in `WaitingList`, and the Club's seat occupancy never exceeds its capacity

#### Scenario: The charge-on-approval capture and its charge-fail branch are a deferred Module-S seam

- **WHEN** the Parties code surface is inspected at launch
- **THEN** `ApproveProfile` drives `Applied → Approved → Active` in one transaction with **no** payment-provider call and **no** fabricated `MembershipFeePaid` event class; the charge-on-approval capture (mandate-at-application, pull-capable instrument, INV1) and its charge-fail branch (a failed charge leaves the Profile `Applied`, no seat, no `OriginatingClubLocked`) are a documented Module-S seam that will gate the activation when Module S lands

#### Scenario: Decline an applied or waitlisted Profile is terminal and event-silent

- **WHEN** `DeclineProfile` is invoked on a Profile in `Applied`, or on a Profile in `WaitingList`
- **THEN** the Profile's `state` becomes `Rejected`, **no** domain event is recorded, and a subsequent re-application for the same Customer–Club pair creates a new Profile (the partial-unique index admits it)

#### Scenario: Illegal approve/decline is rejected

- **WHEN** `ApproveProfile` or `DeclineProfile` is invoked on a Profile in neither `Applied` nor `WaitingList`
- **THEN** an `IllegalProfileTransition` is raised, the Profile's `state` and the Customer's `originating_club_id` are unchanged, and no event is recorded

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (**D8** — the gate table, whose capacity column reads `—` for every state outside `{applied, waiting_list}`; **D3** — the Club-row lock, ordered before the occupancy count) · app/Modules/Parties/Actions/ApproveProfile.php:131 (the from-state guard) evaluated before :139 (`ClubSeatOccupancy::lockAndCountOccupiedSeats()`) — **the shipped order, which the superseded prose contradicted** · openspec/changes/archive/2026-07-09-parties-hero-package/progress.md § Codebase Patterns (*"A from-state guard must precede a capacity gate, and the delta spec's prose says otherwise"* — recorded at implementation time, never carried into the requirement) · tests/Feature/Modules/Parties/ProfileApprovalCapacityGateTest.php:213 (`active`/`lapsed` throw `cannotApprove`, never divert) · tests/Feature/Modules/Parties/ProfileRenewalCapacityGateTest.php:185 (the negative `DB::listen` ordering pin this change reuses) · decisions/2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval.md (RM-03 — the atomic instant) · canon `c-mless/documentation` @ `360df0b` MVP_Decisions_Register_v0.1.md:142 (MVP-DEC-017) + :141 (MVP-DEC-016) · canon Module_K_Acceptance **AC-K-J-13**:92, **AC-K-FSM-2**:113 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1, § 6 / § 6.1, § 13, § 15.2 · spec/04-decisions/decisions.md DEC-040, DEC-083 · CLAUDE.md invariant 1 (no-oversell) · MODIFIES the *Profile Membership Approval* requirement, whose operation sequence read *"acquire a row-level lock on the Club row; count; read capacity; and then, **only if a seat is free**: assert the from-state; …"* — an ordering the shipped code deliberately inverts, and which, read literally, would divert an `Active` Profile onto the waiting list and let a doomed call serialise a Club._
