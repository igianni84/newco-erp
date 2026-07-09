## ADDED Requirements

### Requirement: Hero Package Capacity Invariant

For any Club, the count of that Club's Profiles in the **seat-occupying set** SHALL NOT exceed the Club's Hero-Package capacity. The seat-occupying set SHALL be exactly **`Active` + `Suspended`**. A seat SHALL be freed **only** by a transition into `Lapsed`, `Cancelled` or `Inactive`, and SHALL **never** be held by a Profile in `Applied`, `WaitingList` or `Rejected`. A **`null` capacity SHALL mean uncapped** — every gate passes and the invariant is vacuously satisfied.

The invariant SHALL be evaluated at exactly the transitions that **newly consume** a seat: membership approval (`Applied → Active`), waitlist conversion (`WaitingList → Active`) and grace re-activation (`Lapsed → Active`). It SHALL gate nothing else. In particular:

- **`Suspended → Active` (`ReactivateProfile`) SHALL NEVER be capacity-checked and SHALL NEVER be blocked**, even when the Club is exactly at capacity — the seat was never freed. Re-checking would let a returning member exceed the cap, or let a *temporary* Hold **evict** a member.
- **`Approved → Active` (`ActivateProfile`) SHALL NOT be capacity-checked.** `Approved` is a transient pass-through never durably rested-in; its only caller is `ApproveProfile`, which evaluates the gate *before* delegating. Gating here would count the same seat twice.

Every seat-consuming transaction SHALL acquire a **row-level lock on the Club row** (`SELECT … FOR UPDATE` on `parties_clubs`) **before** counting occupancy, so that concurrent approvals into the same Club serialise while approvals into different Clubs stay parallel. A from-state guard on the Profile row is **not** sufficient: two concurrent approvals of *different* Profiles in one Club lock different rows, both observe `49/50`, and both pass.

A charge that fails at approval SHALL consume **no** seat (the Profile stays `Applied` — the shipped charge-fail contract).

#### Scenario: The seat-occupying set is Active plus Suspended

- **GIVEN** a Club whose capacity is 3, holding one `Active` Profile, one `Suspended` Profile, one `Applied` Profile, one `WaitingList` Profile, one `Rejected` Profile, one `Lapsed` Profile, one `Cancelled` Profile and one `Inactive` Profile
- **WHEN** the Club's seat occupancy is evaluated
- **THEN** exactly **2** seats are occupied (the `Active` and the `Suspended` Profile) and 1 seat is free

#### Scenario: The approval that would exceed capacity does not oversell

- **GIVEN** a Club whose capacity is 50, holding 50 Profiles in the seat-occupying set, and a 51st Profile in `Applied`
- **WHEN** `ApproveProfile` is invoked on the 51st Profile
- **THEN** the Club's seat occupancy stays 50, the 51st Profile is **not** `Active`, and no `ProfileActivated` event is recorded for it

#### Scenario: A suspended member keeps their seat and is restored at exact capacity

- **GIVEN** a Club at exactly its capacity, one of whose seat-occupying Profiles is `Suspended`
- **WHEN** `ReactivateProfile` is invoked on that `Suspended` Profile
- **THEN** the Profile becomes `Active`, exactly one `ProfileReactivated` event is recorded, and the transition is **not** rejected — no capacity check is applied, and the seat occupancy is unchanged (the seat was never freed)

#### Scenario: A suspension does not free a seat for someone else

- **GIVEN** a Club at exactly its capacity and a Profile in `Applied` for that Club
- **WHEN** a seat-occupying Profile of that Club transitions `Active → Suspended`, and `ApproveProfile` is then invoked on the `Applied` Profile
- **THEN** the seat occupancy is still at capacity and the `Applied` Profile is **not** activated (a Hold never evicts a member in favour of an applicant)

#### Scenario: Grace renewal re-consumes a seat and is rejected at capacity

- **GIVEN** a Club at exactly its capacity and a Profile in `Lapsed` whose `lapsed_at` is within the 30-day grace
- **WHEN** `RenewProfile` is invoked on that Profile
- **THEN** a localized `IllegalProfileTransition` naming the capacity reason is raised, the Profile stays `Lapsed` with its `lapsed_at` intact, no `ProfileRenewed` event is recorded, and the seat occupancy is unchanged
- **WHEN** a seat is subsequently freed and `RenewProfile` is invoked again within the grace window
- **THEN** the Profile becomes `Active`, `lapsed_at` is cleared, and exactly one `ProfileRenewed` event is recorded

#### Scenario: Concurrent approvals into one Club serialise on the Club row

- **GIVEN** a Club with exactly one free seat and two Profiles in `Applied` for that Club
- **WHEN** two concurrent transactions each invoke `ApproveProfile`, one per Profile, on a database engine that honours row-level locks
- **THEN** exactly **one** Profile becomes `Active` and the other lands in `WaitingList`; the Club's seat occupancy never exceeds its capacity

#### Scenario: An unconfigured capacity is uncapped

- **GIVEN** a Club for which no Hero-Package capacity is configured
- **WHEN** any number of Profiles are approved into that Club
- **THEN** every approval activates, no Profile is diverted to `WaitingList`, and no capacity rejection is raised

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (adopts canon **MVP-DEC-017** — seat set `Active`+`Suspended`, `Suspended → Active` never re-checked, enforcement at the atomic approve = charge = activation instant, charge-fail consumes no seat; and names the **oversell race** no canon artefact carries) · canon `c-mless/documentation` @ `360df0b` MVP_Decisions_Register_v0.1.md:142 (MVP-DEC-017) + :136 (MVP-DEC-011) · canon Module_K_PRD §13.1:625 (seat set; freed only by lapse/cancel/inactive), :627 (the gated transitions; the atomic instant), :629 (grace re-activation re-consumes a seat) · §10.1:532 · canon Module_K_Acceptance **AC-K-J-13**:92, **AC-K-FSM-2a**:114 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 13.1 (**superseded** — its *"only `Active` Profiles do"* consume capacity is the exact phrasing MVP-DEC-017 corrects), § 13.4 · decisions/2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval.md (the atomic instant this gate sits on) · decisions/2026-06-19-hold-status-coupling.md (a Hold must never evict a member) · CLAUDE.md invariant 1 (no-oversell) & 12 (i18n)._

### Requirement: WaitingList Placement, Conversion and Decline

The `WaitingList` Profile state SHALL become reachable, with **two** entry points, and `WaitingListJoined` SHALL be recorded at **both**:

- **Birth at application.** `CreateProfile` targeting a Club **at capacity** SHALL create the Profile in `WaitingList` rather than `Applied`, recording `ProfileCreated` **and** `WaitingListJoined`. Because neither `Applied` nor `WaitingList` holds a seat, this gate **cannot oversell**; it is a birth-state routing decision and SHALL **not** take a Club-row lock.
- **Divert at approval.** `ApproveProfile` on an `Applied` Profile whose Club is **at capacity** SHALL transition the Profile to `WaitingList` — **not** raise `IllegalProfileTransition` — recording `WaitingListJoined`, taking **no** charge, performing **no** Originating-Club lock, and recording **no** `ProfileActivated`.

`WaitingListJoined` SHALL be a Module K root domain event with `entity_type` `Profile` and a **PII-free** payload of entity ids and enum values only (`{profile_id, customer_id, club_id, state}`), recorded through the platform `DomainEventRecorder` within the writing transaction. Its declared consumer is HubSpot's waitlist confirmation.

**Conversion off the waitlist SHALL be the Producer's manual approval and nothing else.** `ApproveProfile`'s from-state set SHALL widen to `{Applied, WaitingList}`: a `WaitingList → Active` conversion is the same atomic approve = charge = activation instant, under the same capacity gate and the same Originating-Club one-shot rule. `DeclineProfile`'s from-state set SHALL widen to `{Applied, WaitingList}` (`WaitingList → Rejected`, audit-only, event-silent).

**There SHALL be no automatic promotion off the waitlist, on any trigger** — not FIFO, not priority-by-application-date, not producer ranking, and **not on a seat freed by attrition** (`Lapsed` / `Cancelled` / `Inactive`). No listener, scheduler, job or model observer SHALL convert a `WaitingList` Profile. A seat freed by attrition SHALL simply remain free until a Producer approves an applicant into it.

`ApproveProfile` invoked on a `WaitingList` Profile whose Club is **still at capacity** SHALL be rejected with a localized `IllegalProfileTransition` naming the capacity reason — there is no transition to make, and a silent no-op is indistinguishable from a defect. A `WaitingList` Profile SHALL **not** be re-recorded with a second `WaitingListJoined`.

#### Scenario: A Profile is born WaitingList when its target Club is at capacity

- **GIVEN** an `active` Club at exactly its Hero-Package capacity
- **WHEN** `CreateProfile` is invoked for a Customer against that Club
- **THEN** the Profile is created in `WaitingList` (not `Applied`), and exactly two events are recorded in the writing transaction — one `ProfileCreated` and one `WaitingListJoined`
- **WHEN** the same Club has a free seat
- **THEN** a newly created Profile is born `Applied` and only `ProfileCreated` is recorded

#### Scenario: An approval at capacity diverts to WaitingList, taking no charge and no Originating-Club lock

- **GIVEN** a Customer whose `originating_club_id` is unset, holding an `Applied` Profile in a Club at exactly its capacity
- **WHEN** `ApproveProfile` is invoked on that Profile
- **THEN** the Profile's `state` becomes `WaitingList`, exactly one `WaitingListJoined` event is recorded, and **no** `ProfileActivated` and **no** `OriginatingClubLocked` event is recorded
- **AND** the Customer's `originating_club_id` is still unset, and the Club's seat occupancy is unchanged

#### Scenario: A waitlisted Profile converts on manual approval once a seat frees

- **GIVEN** a Club at capacity with a Profile in `WaitingList`
- **WHEN** a seat-occupying Profile of that Club transitions to `Cancelled`, and an operator then invokes `ApproveProfile` on the waitlisted Profile
- **THEN** that Profile becomes `Active` in one atomic operation (passing transiently through `Approved`), exactly one `ProfileActivated` event is recorded, and the Originating-Club one-shot lock applies exactly as it does on an approval from `Applied`

#### Scenario: Nothing promotes a waitlisted Profile automatically

- **GIVEN** a Club at capacity with one or more Profiles in `WaitingList`
- **WHEN** a seat is freed by any attrition transition (`Active → Lapsed`, `Active → Cancelled`, `Lapsed → Cancelled` or `Active → Inactive`) and no operator acts
- **THEN** every `WaitingList` Profile is still in `WaitingList`, the freed seat stays unoccupied, and no `ProfileActivated` event is recorded
- **AND** the Parties code surface contains no listener, scheduler, job or observer that transitions a Profile out of `WaitingList`

#### Scenario: Approving a waitlisted Profile while still at capacity is rejected

- **GIVEN** a Club at exactly its capacity with a Profile in `WaitingList`
- **WHEN** `ApproveProfile` is invoked on that Profile
- **THEN** a localized `IllegalProfileTransition` naming the capacity reason is raised, the Profile stays `WaitingList`, **no** second `WaitingListJoined` event is recorded, and the event log is otherwise unchanged

#### Scenario: A waitlisted Profile is declined, terminally and event-silently

- **WHEN** `DeclineProfile` is invoked on a Profile in `WaitingList`
- **THEN** the Profile's `state` becomes `Rejected`, **no** domain event is recorded (the write is the audit record), and a subsequent re-application for the same Customer–Club pair creates a new Profile (the partial-unique index excludes `rejected`)

#### Scenario: WaitingListJoined carries a PII-free payload and is a root event

- **WHEN** `WaitingListJoined` is recorded, at either entry point
- **THEN** its payload is exactly `{profile_id, customer_id, club_id, state}`, its `entity_type` is `Profile`, its module is `parties`, it carries the `actor_role` resolved from `ActorContext`, and it is a root event (no `causation_id`; `correlation_id` defaults to its own `event_id`) with no name, email, phone or date of birth

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§5–§8 — `WaitingList` is an FSM state, not an entity; two entry points; `WaitingListJoined` at both, a recorded resolution of `AC-K-EVT-11`'s *"transitions to"* wording; `ApproveProfile` at parity **transitions, does not throw**; **no auto-promotion ever**) · canon `c-mless/documentation` @ `360df0b` Module_K_PRD §4.2.1:186 (`Applied → WaitingList`; exits to `Approved` or `Rejected`), §7.1 step 6 (`:399`, birth-in-`WaitingList` at capacity), §7.5:429, §13.5:655 (*"no automatic FIFO conversion at launch"*), §15.6:822 (`WaitingListJoined`) · MVP_Decisions_Register_v0.1.md:147 **MVP-DEC-022**(1) (Paolo **rejected** the tech team's proposed auto-convert `waiting_list → approved` FIFO — *"PRD wins"*) · canon issue #1 → MVP-DEC-011 (*"Shrink by attrition + **no-backfill**"* — an attrition-freed seat is never auto-filled) · canon Module_K_Acceptance **AC-K-J-13**:92, **AC-K-FSM-2**:113, **AC-K-EVT-11**:259 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1:186, § 7.1:392, § 13.5, § 15.6:796 · app/Modules/Parties/Enums/ProfileState.php:29 (`case WaitingList` — present and inert before this change) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional outbox; PII-free payloads; root `correlation_id` = own `event_id`) · CLAUDE.md invariants 4, 10, 12._

### Requirement: Hero Package Capacity Is Read from Module A, Never Stored in Module K

Module K SHALL **enforce** the Hero-Package capacity invariant while storing **no capacity value of any kind**. There SHALL be **no** capacity column on `parties_clubs`, **no** capacity table, and **no** capacity read-model in Module K — the capacity number is the Hero-Package Allocation's `qty`, owned by **Module A**.

Module K SHALL obtain the capacity through a **Module-K-owned read port**: an interface in the Parties `Contracts` namespace returning the capacity for a Club id, where **`null` means uncapped**. Its **launch adapter** SHALL be config-backed, bound in `PartiesServiceProvider::register()`, and swappable in one line for a live Module-A read (or for the `AllocationCapacity*`-fed read-model canon permits) when Module A lands. Module K SHALL import **no** Module A model, table or event, and the port SHALL commit to nothing about Module A's schema or event payloads.

The seat-occupancy count SHALL stay **internal to Module K**. Module K SHALL **not** publish a cross-module seat-occupancy contract until Module A (the capacity-decrease floor) or Module S (the Hero-Package offer gate) exists to consume it — a contract with zero consumers is dead code.

#### Scenario: No Module K schema carries a capacity attribute

- **WHEN** every Module K entity schema is inspected
- **THEN** no table carries a capacity, seat, quota or maximum-members attribute; specifically `parties_clubs` carries none, and no `parties_*` capacity table or capacity read-model exists

#### Scenario: The capacity is obtained through a Module-K-owned read port

- **WHEN** a seat-consuming transition evaluates the gate
- **THEN** it obtains the Club's capacity by resolving the Parties `Contracts` capacity read port from the container, and Module K imports no Module A model, table or event anywhere in its source

#### Scenario: The launch adapter is config-backed and returns a typed capacity

- **GIVEN** the config-backed launch adapter is bound
- **WHEN** the capacity is read for a Club with a configured capacity, and for a Club with none
- **THEN** the first returns that capacity as an `int` (never a string, even though the value may originate from an environment variable) and the second returns `null`, meaning uncapped

#### Scenario: The port is swappable without touching the gate

- **WHEN** a test binds a different implementation of the capacity read port to the container
- **THEN** every seat-consuming transition evaluates the gate against that implementation, with no change to any Action

#### Scenario: The seat-occupancy count is not published cross-module

- **WHEN** the Parties `Contracts` namespace is inspected
- **THEN** it exposes the capacity **read** port but **no** seat-occupancy reader contract (the count stays internal until a consumer exists)

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§9 + alternative (a) — **`AC-K-XM-20` is the binding constraint, stricter than the permissive `AC-K-XM-18`**; a K-owned read-model is rejected because *there is no signal to reconcile from*, making it authoritative-by-default; a Null/uncapped adapter is rejected because *a vacuous gate is worse than no gate*) · canon `c-mless/documentation` @ `360df0b` MVP_Decisions_Register_v0.1.md:145 **MVP-DEC-020** (Module A owns the number; Module K owns the invariant; *"a K-owned capacity number would be a **drift-prone mirror with no independent meaning**"*) · canon Module_K_Acceptance **AC-K-XM-18**:335 (*"a live read of Module A, or a derived, reconciling read-model … is an implementation choice"*), **AC-K-XM-20**:342 (*"NO Allocation, **capacity storage**, sub-pool, sourcing-model attribute"*, verified by *"inspect Module K entity schemas … assert absence"*) · canon Module_K_PRD §13.2:633 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 13.2:620, § 16:832 (*"it does not duplicate the value"*) · spec/04-decisions/decisions.md DEC-073 (physical representation delegated to the dev team) · app/Modules/Parties/Contracts/CustomerTransactionTotalsReader.php + Reads/NullCustomerTransactionTotalsReader.php + Providers/PartiesServiceProvider.php:35 (**the RM-02 read-port seam this mirrors**) · decisions/2026-06-11-modular-monolith-architecture.md (events + contracts only) · tests/Architecture/ModuleBoundariesTest.php · CLAUDE.md invariant 10._

## MODIFIED Requirements

### Requirement: Profile — Multi-Profile Membership

The Profile SHALL **be** the membership in one Club — there SHALL be no separate Membership entity (the Netflix-style Customer↔Profile model). A Profile SHALL belong to **exactly one** Customer and **exactly one** Club, both required at creation. A single Customer MAY hold **multiple** Profiles across different Clubs, but SHALL hold **at most one non-terminal Profile per Club** (uniqueness on the Customer–Club pair), so a second Profile for a (Customer, Club) pair that already has a live Profile SHALL be rejected. A Profile SHALL be created in the `Applied` state — **or in the `WaitingList` state when the target Club is at its Hero-Package capacity** (canon § 7.1 step 6; per the *WaitingList Placement, Conversion and Decline* requirement) — and SHALL record a `ProfileCreated` domain event on creation, plus a `WaitingListJoined` event when it is born waitlisted. The Customer–Club uniqueness is scoped to non-terminal states (the partial-unique index `(customer_id, club_id) WHERE state NOT IN ('rejected','cancelled','inactive')` excludes the terminal states, so a terminal Profile never blocks a fresh `Applied` Profile for the same pair; `suspended`, `lapsed` and `waiting_list` are **non-terminal** and so still block a second live Profile) — **no index migration is required**, the birth-in-`WaitingList` path reuses it unchanged.

The **target Club SHALL be `active`**: a `CreateProfile` targeting a Club in `sunset` or `closed` SHALL be **rejected** with a localized `ClubNotAcceptingMemberships` exception, and no Profile and no `ProfileCreated` event SHALL be created — enforcing the frozen rule that a `sunset` Club blocks new memberships (BR-K-Club-3 / AC-K-FSM-6, closing the deferral in *Club Lifecycle*). At creation, the Profile's `auto_renew` SHALL default-inherit the Club's `auto_renew_default` (per the *Profile Auto-Renewal Preference* requirement). The Club-status gate SHALL be evaluated **before** the capacity read: a `sunset` Club rejects the application outright rather than waitlisting it.

Because neither `Applied` nor `WaitingList` occupies a Hero-Package seat, the birth-state capacity read **cannot oversell** and SHALL **not** take a Club-row lock; it is a birth-state routing decision, not the invariant's enforcement point (which is membership approval).

#### Scenario: Create a Profile

- **WHEN** an operator creates a Profile for a Customer in an `active` Club with a free Hero-Package seat (or no configured capacity)
- **THEN** it is persisted in `Applied`, referencing exactly one Customer and one Club, with `auto_renew` inherited from the Club default, and a `ProfileCreated` event is recorded

#### Scenario: Create a Profile against a Club at capacity

- **WHEN** an operator creates a Profile for a Customer in an `active` Club that is at its Hero-Package capacity
- **THEN** it is persisted in `WaitingList` (not `Applied`), and both a `ProfileCreated` and a `WaitingListJoined` event are recorded in the writing transaction

#### Scenario: One non-terminal Profile per Customer–Club pair

- **WHEN** a second Profile is created for a (Customer, Club) pair that already has a live Profile
- **THEN** the creation is rejected

#### Scenario: A waitlisted Profile is non-terminal and blocks a second live Profile

- **GIVEN** a Customer whose Profile for Club C is in `WaitingList`
- **WHEN** a second Profile is created for the same Customer–Club pair
- **THEN** the creation is rejected (the partial-unique index treats `waiting_list` as non-terminal, exactly as it treats `suspended` and `lapsed`)

#### Scenario: A Customer may hold Profiles across many Clubs

- **WHEN** a Customer is given Profiles in three different (active) Clubs
- **THEN** all three are created (the multi-profile model), each unique on its own Customer–Club pair

#### Scenario: A terminal Profile does not block a fresh application

- **GIVEN** a Customer whose Profile for Club C is in a terminal state (`cancelled` or `inactive`)
- **WHEN** a new Profile is created for the same Customer–Club pair (C still `active`)
- **THEN** the new Profile is created in `Applied` (the partial-unique index excludes the terminal states), while a `suspended`, `lapsed` or `waiting_list` (non-terminal) Profile for the pair would still block it

#### Scenario: A non-active Club rejects new membership, whatever its capacity

- **WHEN** a `CreateProfile` targets a Club in `sunset` (or `closed`), whether or not that Club is at capacity
- **THEN** a `ClubNotAcceptingMemberships` is raised, and no Profile, no `ProfileCreated` and no `WaitingListJoined` event are created

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§6 — birth-in-`WaitingList` is the first of the two entry points; it carries no invariant weight and needs no lock) · canon `c-mless/documentation` @ `360df0b` Module_K_PRD § 7.1 step 6 (`:399` — *"each application creates a Profile in `Applied` state (or `WaitingList` if the target Club is at capacity — §13)"*) · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 3 (the Netflix-style Customer–Profile model) · § 4.2 / § 4.2.1 (Profile born `Applied`) · § 7.1:392 · § 4.3 (**sunset blocks new memberships**) · § 14.1 BR-K-Identity-2 (one Profile per Customer per Club) · § 14.4 **BR-K-Club-3** · § 15.2 (`ProfileCreated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-2, **AC-K-FSM-6**, § 4 AC-K-BR-Identity-2, **AC-K-BR-Club-3**, § 5 AC-K-EVT-5 · database/migrations/2026_06_15_000007_create_parties_profiles_table.php (the partial-unique index, unchanged) · openspec/specs/party-registry/spec.md (*Club Lifecycle*; *Profile Auto-Renewal Preference*; *WaitingList Placement, Conversion and Decline*) · MODIFIES the *Profile — Multi-Profile Membership* requirement (which admitted **only** an `Applied` birth state and applied no capacity read at creation)._

### Requirement: Profile Membership Approval

The Profile SHALL transition `Applied → Approved → Active`, **and equally `WaitingList → Approved → Active`**, as a **single atomic membership-approval operation** (`ApproveProfile`); and `Applied → Rejected` / `WaitingList → Rejected` (membership decline, `DeclineProfile`) — each the sole writer of the Profile `state` for its transition, each running inside one `DB::transaction` against a transaction-locked re-read of the Profile row. **`Approved` is a transient pass-through state, never a durable resting state** (canon MVP-DEC-016): a Customer is never observably left sitting in `Approved`. These Actions realize **the one retained producer write** ("membership approve/decline", L-PP / K-Q4); the producer-facing HTTP surface is deferred and the Actions are operator/console-invocable at launch (admin-parity, DEC-083). A `WaitingList → Active` conversion is **the same atomic instant** as an approval from `Applied`, subject to the same capacity gate and the same Originating-Club one-shot rule — it is **not** a distinct Action (per the *WaitingList Placement, Conversion and Decline* requirement).

`ApproveProfile` SHALL, in one transaction: acquire a **row-level lock on the Club row** (`parties_clubs`); count the Club's seat occupancy (`Active` + `Suspended`); read the Club's Hero-Package capacity through the Module-K-owned capacity read port (per the *Hero Package Capacity Is Read from Module A, Never Stored in Module K* requirement); and then, **only if a seat is free** (or the capacity is `null`, meaning uncapped): assert the from-state; write `state = Approved`; perform the conditional Originating-Club one-shot lock (below); and drive the `Approved → Active` activation through the within-module `ActivateProfile` writer (per the *Profile Activation* requirement), recording `ProfileActivated` — leaving the Profile in `Active`.

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

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (**RM-05** — §3 enforcement at the atomic instant; §7 `ApproveProfile` at parity *transitions, does not throw*, from-state widens to `{applied, waiting_list}`, as does `DeclineProfile`'s; §10 the **Club-row lock**, an oversell race no canon artefact carries) · decisions/2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval.md (RM-03 — the atomic approve = charge = activation instant this gate sits on; charge-fail → stays `Applied`) · canon `c-mless/documentation` @ `360df0b` MVP_Decisions_Register_v0.1.md:142 (**MVP-DEC-017** — seat set; enforcement at the atomic instant; charge-fail consumes no seat) + :141 (MVP-DEC-016) + :147 (MVP-DEC-022 (2) — approval mandatory for every channel, no auto-approve) · canon Module_K_PRD §4.2.1:186 (`Applied → WaitingList`; `WaitingList → Approved | Rejected`), :187 (charge-fail, no seat), §7.5:429 (mandate persists through waitlisting), §13.1:625/:627 · canon Module_K_Acceptance **AC-K-J-13**:92 (*"the Profile lands in `WaitingList`, `WaitingListJoined` fires, and no charge is taken"*), **AC-K-FSM-2**:113 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1, § 3.1 (the one retained producer write), § 6 / § 6.1 (the Originating-Club one-shot lock — DEC-040), § 13 / § 13.2, § 15.2 (no `ProfileApproved` / `ProfileRejected`) · spec/04-decisions/decisions.md DEC-040, DEC-083, DEC-073 · decisions/2026-06-12-event-substrate-and-audit-store.md · MODIFIES the *Profile Membership Approval* requirement (which shipped `ApproveProfile` **uncapped** from `Applied` only, with the capacity gate and the `Applied → WaitingList` path as documented deferred seams)._

### Requirement: Profile Activation

The Profile SHALL transition `Approved → Active` via an explicit `ActivateProfile` Action — the sole writer of the Profile `state` for this transition — running inside one `DB::transaction` against a transaction-locked re-read, recording a `ProfileActivated` event (per the *Demand-Side Activation Events* requirement) in the same transaction. `ActivateProfile` is invoked in two ways: (a) **synchronously by `ApproveProfile`** inside the approval transaction — the K-internal atomic activate-on-approval that makes `Approved` transient (per the *Profile Membership Approval* requirement); and (b), when Module S lands, by the **Module-S `MembershipFeePaid` listener**.

`ActivateProfile` SHALL **NOT** evaluate the Hero-Package capacity gate. `Approved` is a **transient** state never durably rested-in, so `Approved → Active` never *newly* consumes a seat: the seat is consumed by the seat-consuming caller, which evaluates the gate under the Club-row lock **before** delegating. Its only caller today is `ApproveProfile`. Gating here would count the same seat twice within one transaction. When the Module-S `MembershipFeePaid` listener lands it becomes a **new** seat-consuming entry point and SHALL carry the gate at **its own** boundary, under the same Club-row lock discipline. This is a deliberate non-gate, not an omission (per the *Hero Package Capacity Invariant* requirement).

In production the `Approved → Active` transition is driven by the membership-fee-paid signal, or a free-club activation where no fee applies (§ 4.2.1). Per canon MVP-DEC-016 the `MembershipFeePaid` signal is **re-homed to Module S**: **Module S emits** `MembershipFeePaid` (Module E **records**; Module K **consumes** — DEC-173) on payment-provider-confirmed capture of the Hero-Package fee, which fires **INV1 — there is no INV0** (DEC-157). It is a **docblock/seam-name-only** framing — **no** `MembershipFeePaid` event class is fabricated (Module K only *consumes* it). The `MembershipFeePaid` **listener** remains a deferred Module-S seam; `ActivateProfile` is the within-module writer, invoked by the approval / free-club / operator path now. `Profile.fee_paid_at` is **not** stamped in this change (no charge to stamp — forward to Module S). Every transition SHALL be **from-state guarded**: an `ActivateProfile` on a Profile not in `Approved` SHALL be rejected with a localized `IllegalProfileTransition`, leaving state and the event log unchanged.

#### Scenario: Activate an approved Profile

- **WHEN** `ActivateProfile` is invoked on a Profile in `Approved`
- **THEN** the Profile's `state` becomes `Active` and exactly one `ProfileActivated` event is recorded in the same transaction (module `parties`, `entity_type` `Profile`, PII-free payload)

#### Scenario: Activation applies no capacity check of its own

- **GIVEN** a Club at exactly its Hero-Package capacity and a Profile placed directly in `Approved`
- **WHEN** `ActivateProfile` is invoked on it
- **THEN** the Profile becomes `Active` and no capacity rejection is raised — the gate belongs to the seat-consuming caller (`ApproveProfile`), which evaluates it under the Club-row lock before delegating, so the seat is never counted twice

#### Scenario: Illegal activation is rejected

- **WHEN** `ActivateProfile` is invoked on a Profile not in `Approved` (e.g. `Applied`, `WaitingList` or already `Active`)
- **THEN** an `IllegalProfileTransition` is raised, the Profile's `state` is unchanged, and no `ProfileActivated` event is recorded

#### Scenario: The membership-fee trigger is a deferred Module-S seam

- **WHEN** the Parties code surface is inspected
- **THEN** `ActivateProfile` exists as the within-module writer of `Approved → Active`, invoked synchronously by `ApproveProfile` in the approval transaction; there is **no** `MembershipFeePaid` listener and **no** fabricated Module-S / Module-E event class in this change; the production trigger is documented as Module S's `MembershipFeePaid` (Module E records; Module K consumes — DEC-173), firing INV1 and no INV0 (DEC-157)

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§4 — the cap gates exactly the transitions that *newly consume* a seat; `Approved → Active` is not one of them) · decisions/2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval.md (RM-03 — `Approved` transient; the `MembershipFeePaid` seam re-home E→S is docblock-only) · canon `c-mless/documentation` @ `360df0b` MVP_Decisions_Register_v0.1.md:141 (MVP-DEC-016) + :142 (MVP-DEC-017) · canon Module_K_PRD §13.1:627 · Module_K_Acceptance_v0.3-MVP.md AC-K-J-16, AC-K-EVT-15 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1, § 13.1 (*"evaluated at every Profile transition into `Active`"* — read together with canon §13.1:627, which enumerates the three **newly-consuming** transitions), § 15.2 / § 15.8 · decisions/2026-06-12-event-substrate-and-audit-store.md · MODIFIES the *Profile Activation* requirement (whose *"`ActivateProfile` ships **uncapped** (RM-05)"* deferred-seam clause becomes false with this change, and is replaced by an explicit, reasoned **non-gate**)._

### Requirement: Profile Lapse and Grace Renewal

The Profile SHALL transition `Active → Lapsed` via an explicit `LapseProfile` Action and `Lapsed → Active` via an explicit `RenewProfile` Action — each the sole writer of the Profile `state` for its transition, running inside one `DB::transaction` against a transaction-locked re-read. `LapseProfile` SHALL set `state = Lapsed`, stamp the additive nullable `lapsed_at` timestamp, and record a **`ProfileExpired`** event (the § 15.2 event for this edge — the catalog names **no** `ProfileLapsed`). `RenewProfile` SHALL set `state = Active`, clear `lapsed_at`, and record a **`ProfileRenewed`** event (the § 15.2 event for the grace renewal — **not** `ProfileReactivated`, which is reserved for `Suspended → Active`).

`LapseProfile` **frees** a Hero-Package seat (`Lapsed` is outside the seat-occupying set) and SHALL take no Club-row lock and read no capacity — freeing a seat can never oversell. **A freed seat SHALL NOT trigger any promotion off the waitlist** (per the *WaitingList Placement, Conversion and Decline* requirement).

**`RenewProfile` SHALL be capacity-gated.** `Lapsed → Active` **re-consumes** a seat, so `RenewProfile` SHALL, in its transaction, acquire the **Club-row lock**, count the seat-occupying set (`Active` + `Suspended`), read the capacity through the Module-K-owned capacity read port, and proceed **only if a seat is free** (or the capacity is `null`). At capacity it SHALL be rejected with a localized `IllegalProfileTransition` naming the **capacity** reason, leaving `state = Lapsed`, `lapsed_at` intact (the 30-day grace clock keeps running) and the event log unchanged. It SHALL **not** divert the Profile to `WaitingList`: canon draws only `Applied → WaitingList`, no `Lapsed → WaitingList` edge exists, and inventing one would discard `lapsed_at` and burn the grace window.

> ⚠️ **Naming trap.** This `RenewProfile` (`Lapsed → Active`, the 30-day grace re-activation) is **not** the *grandfathered* renewal of canon `MVP-DEC-011` / `AC-K-J-15a`, which is the **period rollover of an `Active` Profile into a new club year** and is explicitly **not** cap-gated (the seat was never freed). That rollover **is not modelled** — `parties_profiles` carries no `valid_to`, no period column and no rollover Action — and is out of scope. Same word, opposite rule.

`RenewProfile` SHALL enforce the **30-day grace window** (DEC-034): it is permitted **only** when `state = Lapsed` **and** the current moment is within 30 days of `lapsed_at`; past the grace window it SHALL be rejected with a localized `IllegalProfileTransition` (the deferred scheduler instead transitions the Profile `Lapsed → Cancelled` — per the *Profile Cancellation and Deactivation* requirement). The **grace sub-gate SHALL be evaluated before the capacity gate**, so a past-grace renewal reports the grace reason regardless of capacity. The lapse trigger (membership-validity-period expiry, § 4.2.1) and the renewal trigger (the membership-fee-paid signal, § 15.8) are **deferred seams** — `LapseProfile`/`RenewProfile` are the within-module writers, invoked directly now; **no** cross-module event contract is fabricated. Every transition SHALL be **from-state guarded**: a `LapseProfile` on a Profile not in `Active`, or a `RenewProfile` on a Profile not in `Lapsed` (or past grace, or at capacity), SHALL be rejected, leaving state, `lapsed_at` and the event log unchanged.

#### Scenario: Lapse an active Profile and free its seat

- **WHEN** `LapseProfile` is invoked on a Profile in `Active`
- **THEN** the Profile's `state` becomes `Lapsed`, `lapsed_at` is stamped, exactly one `ProfileExpired` event is recorded in the same transaction (and **no** `ProfileLapsed` event, which the catalog does not name), and the Club's seat occupancy drops by one

#### Scenario: Renew a lapsed Profile within the 30-day grace when a seat is free

- **GIVEN** a Profile in `Lapsed` whose `lapsed_at` is within the last 30 days, in a Club with a free seat (or no configured capacity)
- **WHEN** `RenewProfile` is invoked
- **THEN** the Profile's `state` becomes `Active`, `lapsed_at` is cleared, exactly one `ProfileRenewed` event is recorded (not `ProfileReactivated`), and the Club's seat occupancy rises by one

#### Scenario: Renewal at capacity is rejected and the grace clock keeps running

- **GIVEN** a Profile in `Lapsed` whose `lapsed_at` is within the last 30 days, in a Club at exactly its capacity
- **WHEN** `RenewProfile` is invoked
- **THEN** a localized `IllegalProfileTransition` naming the capacity reason is raised; the Profile stays `Lapsed` with `lapsed_at` **unchanged**; it is **not** moved to `WaitingList`; and no event is recorded
- **WHEN** a seat is subsequently freed and `RenewProfile` is invoked again, still within the grace window
- **THEN** the renewal succeeds and exactly one `ProfileRenewed` event is recorded

#### Scenario: Renewal past the grace window is rejected regardless of capacity

- **GIVEN** a Profile in `Lapsed` whose `lapsed_at` is more than 30 days ago
- **WHEN** `RenewProfile` is invoked, whether the Club has a free seat or is at capacity
- **THEN** an `IllegalProfileTransition` naming the **grace** reason is raised (the grace sub-gate is evaluated first), the Profile stays `Lapsed`, and no event is recorded

#### Scenario: The lapse and renewal triggers are deferred seams

- **WHEN** the Parties code surface is inspected
- **THEN** `LapseProfile` and `RenewProfile` exist as the within-module writers, and there is **no** validity-period scheduler and **no** `MembershipFeePaid` listener or fabricated cross-module event class in this change

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (**§11 — the load-bearing naming trap**: our `RenewProfile` is a cap-gated re-activation; the *grandfathered* renewal is an unmodelled `Active` period rollover) · canon `c-mless/documentation` @ `360df0b` Module_K_PRD §13.1:629 (*"a re-activation within the 30-day grace **re-consumes a seat** (subject to the cap at re-activation time)"*), :627 (*"re-activation from `Lapsed` / `Cancelled`"* among the gated transitions; the grandfathered period rollover *"is **not** cap-gated"*), §4.2.1:186 (no `Lapsed → WaitingList` edge exists) · MVP_Decisions_Register_v0.1.md:136 (**MVP-DEC-011** — grandfathering; attrition drawdown) · canon Module_K_Acceptance **AC-K-J-15a**:95 (**absent from our frozen acceptance file** — the table jumps J-15 → J-16; legs (b)/(d) need the unmodelled rollover and are carved out) · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1, § 13.1:616 (**superseded**), § 15.2, § 15.8 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-12, AC-K-BR-Profile-3, AC-K-EVT-5 · spec/04-decisions/decisions.md DEC-034 (30-day lapsed grace) · MODIFIES the *Profile Lapse and Grace Renewal* requirement (which applied **no capacity gate** to `RenewProfile`)._

### Requirement: Profile Suspension and Restoration

The Profile SHALL transition `Active → Suspended` via an explicit `SuspendProfile` Action and `Suspended → Active` via an explicit `ReactivateProfile` Action — each the sole writer of the Profile `state` for its transition, running inside one `DB::transaction` against a transaction-locked re-read, recording a `ProfileSuspended` (respectively `ProfileReactivated`) event (per the *Demand-Side Status Events* requirement) in the same transaction. In production these transitions are driven by the Hold→`suspended` coupling (a Profile-level Hold, or a cascading Customer-level Hold — per the *Hold-Driven Status Coupling* requirement); the Actions are also directly operator-invocable (manual suspension — AC-K-BR-Customer-1 *"explicit (manual or via Hold)"*).

**A `Suspended` Profile KEEPS its Hero-Package seat.** `Suspended` is inside the seat-occupying set, so `SuspendProfile` frees **no** seat and `ReactivateProfile` consumes **no** new one. Consequently:

- **`ReactivateProfile` SHALL NEVER be capacity-gated and SHALL NEVER be blocked by capacity**, even when the Club is at exactly its capacity. It SHALL take no Club-row lock and read no capacity value. Gating it would let a *temporary* Hold permanently **evict** a member, or let a returning member exceed the cap — both forbidden.
- **`SuspendProfile` SHALL NOT free a seat for another applicant.** A Club at capacity holding a `Suspended` Profile is still at capacity: an `Applied` Profile in that Club SHALL NOT become approvable merely because a member was suspended.

Suspension SHALL be **state-preserving**: `SuspendProfile` SHALL write **only** the Profile `state` — it SHALL NOT cancel vouchers, pending orders or allocation reservations, nor mutate any Club Credit balance. Active vouchers stay ACTIVE, pending orders stay pending, reservations stay reserved (§ 10.1); Club Credit is frozen while suspended and mutable again on restore. Every transition SHALL be **from-state guarded**: a `SuspendProfile` on a Profile not in `Active`, or a `ReactivateProfile` on a Profile not in `Suspended`, SHALL be rejected with a localized `IllegalProfileTransition`, leaving state and the event log unchanged.

#### Scenario: Suspend an active Profile records ProfileSuspended and preserves state

- **WHEN** `SuspendProfile` is invoked on a Profile in `Active`
- **THEN** the Profile's `state` becomes `Suspended` and exactly one `ProfileSuspended` event is recorded in the same transaction (module `parties`, `entity_type` `Profile`, PII-free payload `{profile_id, state}`)
- **AND** no voucher, order, reservation or Club Credit record is created, cancelled or mutated by the Action (it writes only `Profile.state`)

#### Scenario: Restore a suspended Profile

- **WHEN** `ReactivateProfile` is invoked on a Profile in `Suspended`
- **THEN** the Profile's `state` becomes `Active` and exactly one `ProfileReactivated` event is recorded in the same transaction

#### Scenario: Restoration at exact capacity is never blocked

- **GIVEN** a Club at exactly its Hero-Package capacity, one of whose seat-occupying Profiles is `Suspended`
- **WHEN** `ReactivateProfile` is invoked on that Profile
- **THEN** it becomes `Active`, exactly one `ProfileReactivated` event is recorded, and **no** capacity check is performed and **no** capacity rejection is raised — a Hold never evicts a member

#### Scenario: Suspension does not free a seat

- **GIVEN** a Club at exactly its capacity and a Profile in `Applied` for that Club
- **WHEN** a seat-occupying Profile is suspended, and `ApproveProfile` is then invoked on the `Applied` Profile
- **THEN** the `Applied` Profile is **not** activated (it is diverted to `WaitingList`), because the suspended member still holds their seat

#### Scenario: Illegal suspend or restore is rejected

- **WHEN** `SuspendProfile` is invoked on a Profile not in `Active` (e.g. `Applied`, `WaitingList`, `Lapsed` or already `Suspended`), or `ReactivateProfile` on a Profile not in `Suspended`
- **THEN** an `IllegalProfileTransition` is raised, the Profile's `state` is unchanged, and no event is recorded

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§1 — the seat set is `Active`+`Suspended`; §2 — `Suspended → Active` is **never** capacity-re-checked and **never** blocked; `ReactivateProfile` stays untouched and earns an explicit regression test) · canon `c-mless/documentation` @ `360df0b` MVP_Decisions_Register_v0.1.md:142 (**MVP-DEC-017** Q1 — a Hold must never evict a member) · canon Module_K_PRD §13.1:625, §10.1:532, §4.2.1:191 · canon Module_K_Acceptance **AC-K-J-13**:92 (leg 2), **AC-K-FSM-2a**:114 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1, § 10.1, § 15.2 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-2, AC-K-FSM-2a, AC-K-EVT-5, AC-K-BR-Hold-5 · decisions/2026-06-19-hold-status-coupling.md (the coupling that drives these in production) · MODIFIES the *Profile Suspension and Restoration* requirement (which said nothing about seat occupancy, leaving the `Suspended`-keeps-its-seat rule and the never-gate on restoration undocumented)._

### Requirement: Demand-Side Activation Events

Each demand-side activation transition SHALL record its **verbatim** Module K event — `CustomerActivated` (on Customer `pending → active`), `ProfileActivated` (on Profile `Approved → Active`), `OriginatingClubLocked` (on the Customer's first-ever Profile approval **that reaches `Active`**) — through the platform `DomainEventRecorder`, **within the same database transaction** as the state write, tagged with module `parties`, the acting `actor_role` + id resolved from the `ActorContext` seam, the entity type and id, and a **PII-free** payload (entity ids + enum/business values only — never name, email, phone or date of birth). `CustomerActivated` SHALL carry `entity_type = 'Customer'` with payload `{customer_id, status}`; `ProfileActivated` SHALL carry `entity_type = 'Profile'` with payload `{profile_id, state}`; `OriginatingClubLocked` SHALL carry `entity_type = 'Customer'` with payload `{customer_id, club_id, profile_id, locked_at}` — the Customer, the locking Club, the triggering membership and the moment (§ 6.1 verbatim).

Because membership approval is the **atomic approve = charge = activation** (per the *Profile Membership Approval* requirement), `ProfileActivated` is recorded by the `Approved → Active` activation that `ApproveProfile` drives **as well as** by a standalone `ActivateProfile`: a **first-ever** `ApproveProfile` that reaches `Active` records **both** `OriginatingClubLocked` and `ProfileActivated`; a **subsequent** one records `ProfileActivated` (the Originating Club is already locked). A `WaitingList → Active` **conversion** records exactly the same events as an approval from `Applied` — the conversion is the same atomic instant, not a distinct transition.

A **capacity-diverted** `ApproveProfile` — one whose Club is at its Hero-Package capacity, leaving the Profile in `WaitingList` — SHALL record **none** of the three activation events. It records `WaitingListJoined` instead (governed by the *WaitingList Placement, Conversion and Decline* requirement, which owns that event's contract). The `→ Approved` approval write and the `→ Rejected` `DeclineProfile` write SHALL record **no** Profile approval/decline event of their own (audit-only — § 15.2 names no `ProfileApproved` / `ProfileRejected`). **No** event name outside the three-name **activation** set SHALL be recorded by an activation transition. Each of the three events SHALL be recorded as a **root** event (no `causation_id`; `correlation_id` defaults to its own `event_id`), since the transition it records has no parent event in the same transaction — when `OriginatingClubLocked` and `ProfileActivated` are co-recorded in a first-ever atomic approval, **both** are roots (the approval write parents neither). The downstream consumers `OriginatingClubLocked` names — Module S settlement-eligibility, Module E D19 accrual, HubSpot (§ 6 / § 15.6 / AC-K-EVT-10) — remain unwired: Module K records the event; all consumption is downstream and deferred.

#### Scenario: Each activation transition records its verbatim event PII-free

- **WHEN** any of the three activation transitions runs (`ActivateCustomer`, a standalone `ActivateProfile`, or the atomic `ApproveProfile` which drives the `Approved → Active` activation)
- **THEN** exactly its corresponding event (`CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked`) is recorded in the writing transaction, tagged module `parties`, with the entity type/id and an `actor_role` from `ActorContext`, and its payload contains only entity ids and enum/business values (no name, email, phone or date of birth)

#### Scenario: OriginatingClubLocked carries the four spec fields

- **WHEN** `OriginatingClubLocked` is recorded
- **THEN** its payload is exactly `{customer_id, club_id, profile_id, locked_at}` (the Customer, the locking Club, the triggering membership, and the moment), `entity_type` `Customer`, PII-free

#### Scenario: A first-ever approval records the lock and the activation; a later approval records only the activation

- **WHEN** a Customer's first-ever `ApproveProfile` runs against a Club with a free seat, then a second Club's `ApproveProfile` runs
- **THEN** the first records **both** `OriginatingClubLocked` and `ProfileActivated`, and the second records `ProfileActivated` only (no second `OriginatingClubLocked`)

#### Scenario: A capacity-diverted approval records no activation event

- **GIVEN** a Customer whose `originating_club_id` is unset, with an `Applied` Profile in a Club at exactly its capacity
- **WHEN** `ApproveProfile` is invoked
- **THEN** **no** `ProfileActivated` and **no** `OriginatingClubLocked` event is recorded (a `WaitingListJoined` is recorded instead), and the Customer's `originating_club_id` stays unset — the first-ever-approval lock fires only on an approval that reaches `Active`

#### Scenario: A waitlist conversion records the same events as a direct approval

- **GIVEN** a Customer whose `originating_club_id` is unset, with a Profile in `WaitingList` in a Club that now has a free seat
- **WHEN** `ApproveProfile` is invoked on it
- **THEN** exactly one `OriginatingClubLocked` and one `ProfileActivated` are recorded — the conversion is the same atomic instant as an approval from `Applied`

#### Scenario: Approve and decline record no approval/decline event

- **WHEN** `ApproveProfile` or `DeclineProfile` runs, from either `Applied` or `WaitingList`
- **THEN** no `ProfileApproved` / `ProfileRejected` event is recorded (the approval write and the decline write are audit-only), and no activation transition records any event name outside `{CustomerActivated, ProfileActivated, OriginatingClubLocked}`

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§6–§8 — a capacity-diverted approve takes no charge and performs **no** Originating-Club lock; the conversion is the same atomic instant) · decisions/2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval.md (RM-03 — the three-name activation set) · canon `c-mless/documentation` @ `360df0b` Module_K_PRD §4.2.1:187, §13.1:627 · canon Module_K_Acceptance **AC-K-J-13**:92 (*"no charge is taken"*), **AC-K-EVT-11**:259 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 15.1, § 15.2, § 15.6 (`OriginatingClubLocked`; `WaitingListJoined`), § 6.1 · decisions/2026-06-12-event-substrate-and-audit-store.md (root `correlation_id` = own `event_id`) · openspec/specs/event-substrate/spec.md (Transactional Event Recording; Domain Event Envelope) · CLAUDE.md invariants 4 & 10 · MODIFIES the *Demand-Side Activation Events* requirement (whose blanket *"no event name outside this three-name set SHALL be recorded"* clause predated `WaitingListJoined`, and which had no capacity-diverted branch)._

### Requirement: Birth States Recorded, Lifecycle Transitions Deferred

Every Parties entity that carries a lifecycle state SHALL define its full state domain and SHALL be created in its birth state: Customer `pending`, Account `active`, Producer `draft`, Club `active`, ProducerAgreement `draft`, Profile `Applied` — **or `WaitingList` when the target Club is at its Hero-Package capacity** (Supplier carries no lifecycle state). The **supply-side** lifecycle — Producer, ProducerAgreement and Club — SHALL implement its state transitions and emit its lifecycle events, as governed by the Requirements *Producer Lifecycle*, *ProducerAgreement Lifecycle*, *Club Lifecycle* and *Supply-Side Lifecycle Events*. The **Customer and Producer compliance-screening lifecycles** — the KYC FSM and the Customer sanctions FSM, **each separate from the Customer/Producer status FSM** — SHALL be implemented as governed by the Requirements *Customer KYC Lifecycle*, *Customer Sanctions Screening Lifecycle*, *Producer KYC Lifecycle* and *Sanctions Screening Events*; their fields are added additively (nullable — DEC-071). The **demand-side** status lifecycle is now **fully implemented** across activation and suspension. Activation (the Requirements *Customer Onboarding Activation*, *Profile Membership Approval*, *Profile Activation* and *Demand-Side Activation Events*): Customer `pending → active`; **Profile membership approval is the atomic approve = charge = activation** — `ApproveProfile` drives `Applied → Approved → Active` (and equally `WaitingList → Approved → Active`) in **one operation**, `Approved` a **transient** pass-through never durably rested-in (canon MVP-DEC-016) — plus `Applied | WaitingList → Rejected` decline and the Originating-Club one-shot lock — emitting `CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked` (approval and decline audit-only — § 15.2 names no `ProfileApproved` / `ProfileRejected`). The **Hero-Package seat gate is now implemented** (the Requirements *Hero Package Capacity Invariant*, *WaitingList Placement, Conversion and Decline* and *Hero Package Capacity Is Read from Module A, Never Stored in Module K*): the seat-occupying set is `Active` + `Suspended`, evaluated under a **`parties_clubs` row lock** at the atomic approve instant and at grace renewal, never at `Suspended → Active` and never at `Approved → Active`; the `WaitingList` state is reachable at both canon entry points and records `WaitingListJoined`; conversion off the waitlist is the Producer's **manual** approval and **nothing auto-promotes, on any trigger**; and the capacity number itself is **never stored in Module K** — it is read through a K-owned port whose launch adapter is config-backed (canon MVP-DEC-011 / MVP-DEC-017 / MVP-DEC-020). The **charge-on-approval capture** (Module S's `MembershipFeePaid`, INV1 / no INV0 — DEC-173 / DEC-157) remains a deferred seam. Suspension and the remaining status edges (the Requirements *Profile Suspension and Restoration*, *Profile Lapse and Grace Renewal*, *Profile Cancellation and Deactivation*, *Customer Suspension and Closure*, *Account Status Lifecycle*, *Hold-Driven Status Coupling* and *Demand-Side Status Events*): Profile `Active → Suspended | Lapsed | Cancelled | Inactive` and `Lapsed → Active` grace (**now capacity-gated**), Customer `active → suspended | closed` (suspension cascading to the Customer's Profiles), and Account `active → suspended → closed` — emitting `CustomerSuspended` / `CustomerReactivated` / `CustomerClosed` and `ProfileSuspended` / `ProfileReactivated` / `ProfileExpired` / `ProfileRenewed` / `ProfileInactive` (Account transitions and Profile cancellation are **audit-only** — § 15 names no Account event and the § 15.2 family names no `ProfileCancelled`). The **Hold→`suspended` status coupling** is implemented (the *Hold-Driven Status Coupling* requirement): placing a Hold drives every covered scope in its suspendable from-state to `suspended`, and lifting a Hold restores a covered scope **iff no other active Hold** still covers it (ADR 2026-06-19) — and because a `Suspended` Profile **keeps its seat**, restoration is never capacity-blocked; the unified Hold registry and the `kyc` Hold compliance coupling remain as governed by the Requirements *Hold Registry*, *Hold Lifecycle and Lift Discipline*, *Hold Events* and *Hold and Sanctions Read-API*. The **Club Credit** instrument is implemented as a Module K entity (the Requirements *Club Credit Entity and One-Active-Per-Profile Invariant*, *Club Credit Issuance*, *Club Credit Redemption and Carry-Forward*, *Club Credit Forfeiture and Restoration* and *Club Credit State Recording Is Module-E-Owned*): a per-Profile prepayment instrument created `active` on issuance, with the FSM `active → redeemed | forfeited` driven by the within-module writers `IssueClubCredit` / `ApplyClubCredit` (K.17 carry-forward) / `ForfeitClubCredit` / `RestoreClubCredit`, the structural one-active-per-Profile invariant, and the freeze-while-suspended guarantee. These writers are **audit-only** — § 11.4 makes the `ClubCreditIssued` / `ClubCreditApplied` / `ClubCreditRestored` / `ClubCreditForfeited` lifecycle events **Module E's**, so Module K records state and emits no Club Credit event; the `MembershipFeePaid` trigger is **Module S's** (Module E records; Module K consumes — DEC-173 / MVP-DEC-016). The Club Credit **cross-module triggers** remain deferred seams: the Module-S `MembershipFeePaid` listener + `ClubCredit*` consumers (Phase 6), the Module-S checkout redemption and the Club-closure → Discovery store-credit conversion (DEC-043), the year-end-lapse scheduler, and the Profile-cancellation → forfeit cascade.

Exactly **one** demand-side **status** seam SHALL remain deferred: **Customer-segment derivation** (and its `CustomerSegmentChanged` event) — until the follow-on change `parties-customer-segments` implements it. `ActivateAccount` SHALL NOT exist (the Account is born `active`; its only `→ active` edge is the restore `ReactivateAccount`).

Four **capacity** sub-behaviours SHALL remain deferred, each blocked outside Module K, and SHALL NOT be implemented by inventing a Module-K surface for them: the **mid-year capacity increase** signal (`AllocationCapacityIncreased`, Module A §5.3.4 — the waitlist-*conversion* half does ship); the **capacity-decrease seat floor** (executed on Module A's surface); the **grandfathered period rollover** of an `Active` Profile into a new club year (`valid_to` and a rollover Action exist nowhere in Module K — adding them would invent schema canon never names); and the **Hero-Package Offer SKU shapes** (Module 0 / Module S). Module K SHALL likewise **not** publish a cross-module seat-occupancy contract until a consumer exists.

#### Scenario: Each entity is born in its birth state

- **WHEN** a Customer, Account, Producer, Club, ProducerAgreement or Profile is created
- **THEN** its state is, respectively, `pending`, `active`, `draft`, `active`, `draft`, and `Applied` — or `WaitingList` when the Profile's target Club is at its Hero-Package capacity

#### Scenario: The capacity invariant and the WaitingList path now exist; only the segment seam remains

- **WHEN** the Parties code surface is inspected
- **THEN** Producer, ProducerAgreement and Club expose lifecycle-transition operations and record their lifecycle events; the Customer/Producer KYC and Customer sanctions screening FSMs expose their transitions; the unified Hold registry exposes place/lift with the `kyc` Hold auto-placed on KYC `pending` and auto-lifted on `verified`; the demand-side **activation** transitions exist (Customer `pending → active` via `ActivateCustomer`; Profile membership approval `ApproveProfile` drives `Applied → Approved → Active` **atomically**, and equally `WaitingList → Approved → Active`, plus `Applied | WaitingList → Rejected` via `DeclineProfile`, with the Originating-Club one-shot lock); AND the demand-side **status** transitions exist — Profile `Active → Suspended | Lapsed | Cancelled | Inactive` and `Lapsed → Active` grace, Customer `active → suspended | closed` (cascading to Profiles), Account `active → suspended → closed`
- **AND** the **Hero Package Capacity Invariant exists**: the seat-occupying set is `Active` + `Suspended`, gated under a `parties_clubs` row lock at membership approval, waitlist conversion and grace renewal — and **never** at `Suspended → Active` or `Approved → Active`
- **AND** the `Applied → WaitingList` path exists at **both** canon entry points (birth at application, divert at approval) and a `WaitingListJoined` event is recordable at each; `WaitingList → Approved` conversion and `WaitingList → Rejected` decline exist; and **no** listener, scheduler, job or observer promotes a Profile off the waitlist
- **AND** Customer-segment derivation does **not** exist; no `CustomerSegmentChanged` event is recordable; and no `ActivateAccount` Action exists

#### Scenario: Module K stores no capacity and publishes no seat count

- **WHEN** the Module K entity schemas and the Parties `Contracts` namespace are inspected
- **THEN** no table carries a capacity attribute, no capacity read-model exists, and the capacity is obtained through a Module-K-owned read port with a config-backed launch adapter
- **AND** no seat-occupancy reader contract is published cross-module, and no Module A model, table or event is imported anywhere in Module K

#### Scenario: The four capacity sub-behaviours stay deferred

- **WHEN** the Parties code surface is inspected
- **THEN** there is **no** consumer of a Module-A capacity-increase signal, **no** capacity-decrease surface, **no** `valid_to` column or period-rollover Action on the Profile, and **no** Hero-Package Offer or SKU-shape surface — each blocked on Module A, Module 0 or Module S

#### Scenario: The Club Credit entity and its within-module FSM exist, audit-only

- **WHEN** the Parties code surface is inspected
- **THEN** the Club Credit entity exists with the writers `IssueClubCredit` / `ApplyClubCredit` / `ForfeitClubCredit` / `RestoreClubCredit` driving `active → redeemed | forfeited` under the one-active-per-Profile invariant, redemption frozen while the Profile is suspended
- **AND** Module K records Club Credit state with **no** `ClubCredit*` domain event of its own and **no** fabricated `MembershipFeePaid` / `ClubCredit*` event class (the § 11.4 ownership boundary; `MembershipFeePaid` is Module S's per DEC-173)
- **AND** the Club Credit cross-module triggers — the Module-S `MembershipFeePaid` listener and `ClubCredit*` consumers, the Module-S checkout redemption and DEC-043 closure conversion, the year-end scheduler, and the Profile-cancellation cascade — do **not** exist (deferred seams)

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (**RM-05** — closes the capacity + `WaitingList` seams against a **documented subset**; the four carve-outs are named with their blockers) · decisions/2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval.md (RM-03) · canon `c-mless/documentation` @ `360df0b` MVP_Decisions_Register_v0.1.md:136 / :142 / :145 (MVP-DEC-011 / 017 / 020) + :147 (MVP-DEC-022 (1) — no auto-FIFO) · canon issue #1 (*"shrink by attrition + no-backfill"*) · canon Module_K_Acceptance **AC-K-J-13** / **AC-K-FSM-2** / **AC-K-FSM-2a** / **AC-K-EVT-11** / **AC-K-XM-18** / **AC-K-XM-20** (**met**) and **AC-K-J-14** / **AC-K-J-15** / **AC-K-J-15a** / **AC-K-XM-19** (**not met — carved out**) · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 / § 4.2.1 / § 4.3 / § 4.7, § 4.8 / § 4.8.1, § 10.1, § 9.1 / § 9.2, § 11 / § 11.1–11.5, § 13, § 5 (Customer segments — deferred), § 15 · decisions/2026-06-19-hold-status-coupling.md · MODIFIES the *Birth States Recorded, Lifecycle Transitions Deferred* requirement (whose closing sentence declared **three** remaining demand-side seams — *"the Hero Package Capacity Invariant (approval and activation ship **uncapped**)"*, *"the `Applied → WaitingList` path (and its `WaitingListJoined` event)"* and Customer segments — of which this change closes the first two, leaving one)._
