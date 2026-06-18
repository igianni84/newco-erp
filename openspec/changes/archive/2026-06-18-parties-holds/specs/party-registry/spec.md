## ADDED Requirements

### Requirement: Hold Registry

The Parties module SHALL provide a **unified, trigger-agnostic Hold registry** — the single account-restriction primitive that gates commercial activity. A Hold SHALL carry: a `hold_type` from the six-value domain `admin | kyc | payment | fraud | compliance | credit`; a **scope** comprising a `scope_type` from `customer | account | profile` and a `scope_id` (the id of the scoped Customer, Account or Profile — a within-module reference); a `status` from `active | lifted` (born `active`); an optional placement `reason`; the placement actor (role + id, from the `ActorContext` seam) and the placement moment; and, once lifted, the lift actor + lift moment + an optional `lift_reason`. The Hold SHALL be a new `parties_holds` table (the module-table-prefix convention) added by a single **additive** migration; its value-set columns SHALL carry the layered enforcement idiom (a string column + the backed-enum cast on both engines, plus a PostgreSQL-only `CHECK` deriving from `Enum::cases()`).

A scope MAY carry **multiple concurrent `active` Holds** (any one of which blocks the activity it gates — the blocking is the downstream surface's, per the *Hold and Sanctions Read-API* requirement). Module K SHALL be the **registry-of-record** for every Hold, and the registry SHALL be **trigger-agnostic**: it records the type and state of a Hold regardless of how the placement was triggered, and a **manual operator-placement path** SHALL exist for every type (the automatic triggers for `payment`/`fraud`/`compliance`/`credit` are Module E/S signals deferred to those modules; the registry is unchanged by their automation depth). The `hold_type` enum SHALL expose an `autoLiftable(): bool` predicate that is true for `kyc` and `payment` only (consumed by the *Hold Lifecycle and Lift Discipline* requirement).

#### Scenario: The Hold entity carries its type, scope, status and audit metadata

- **WHEN** a Hold is placed on a scope with a type
- **THEN** a `parties_holds` row persists carrying the `hold_type`, the `scope_type` + `scope_id`, `status = active`, the placement actor (role + id) and the placement moment, and (until lifted) a null lift actor / lift moment

#### Scenario: The six Hold types and the three scopes are the domain

- **WHEN** the `HoldType` and `HoldScope` enums are inspected
- **THEN** `HoldType` is exactly `admin | kyc | payment | fraud | compliance | credit` and `HoldScope` is exactly `customer | account | profile`, each a string-backed enum whose `->value` is the spec token
- **AND** `HoldType::autoLiftable()` is true for `kyc` and `payment` and false for `admin`, `fraud`, `compliance`, `credit`

#### Scenario: Multiple concurrent Holds may exist on one scope

- **WHEN** a `kyc` Hold and an `admin` Hold are both placed on one Customer
- **THEN** both are recorded `active` on that Customer scope concurrently (the scope is not single-Hold)

#### Scenario: The registry is trigger-agnostic with a manual-placement path for every type

- **WHEN** an operator manually places a Hold of any of the six types
- **THEN** the Hold is recorded identically regardless of type — Module K is the registry-of-record, and no automatic upstream trigger is required for the record to exist

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.8 (the unified Hold entity — six types `admin/kyc/payment/fraud/compliance/credit`, three scopes Customer/Account/Profile, placement/lift audit metadata; Module K registry-of-record), § 4.8.1 (multiple concurrent Holds; trigger-agnostic) · § 14.8 BR-K-Hold-1 (multiple Holds, any blocks) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-10 (Hold lifecycle — six types × three scopes, audit metadata), AC-K-BR-Hold-1 (multiple concurrent Holds), AC-K-MVP-2 (trigger-agnostic registry; manual-placement path) · spec/04-decisions/decisions.md DEC-181 (uniformity), DEC-160 (per-type lift — the `autoLiftable` partition), DEC-168 (K is registry-of-record), DEC-071 (additive nullable field pattern) · decisions/2026-06-18-hold-lift-discipline-per-type.md · decisions/2026-06-12-production-db-engine.md (Postgres-truthful migration; CHECK-from-cases idiom)._

### Requirement: Hold Lifecycle and Lift Discipline

A Hold SHALL be placed and lifted by explicit operator Actions that are the sole writers of `parties_holds`, each running inside one `DB::transaction`. `PlaceHold` SHALL create a Hold (`status = active`) recording the placement actor, moment and optional `reason`, and SHALL record the `CustomerHoldPlaced` event (per the *Hold Events* requirement) in the same transaction. `LiftHold` SHALL re-read the Hold under a transaction lock, set `status = lifted` recording the lift actor, lift moment and optional `lift_reason`, and record `CustomerHoldLifted` in the same transaction.

`LiftHold` SHALL enforce the **per-type lift discipline** (DEC-160; ADR `2026-06-18-hold-lift-discipline-per-type.md`): a Hold whose type is **auto-managed** (`HoldType::autoLiftable()` — `kyc` or `payment`) SHALL NOT be lifted by the operator path and SHALL be **rejected** with a localized `IllegalHoldLift`, because those types are lifted by the system on their clearing signal (the `kyc` auto-lift is wired in this change via the *Customer KYC Lifecycle* requirement; the `payment` auto-lift trigger is a deferred Module-E seam). A Hold of type `admin`, `fraud`, `compliance` or `credit` SHALL be lifted freely by the operator path. Lifting a Hold that is not `active` (already `lifted`) SHALL be **rejected** with a localized `IllegalHoldLift`, leaving state and the event log unchanged.

#### Scenario: Place a Hold records the placement actor, reason and moment

- **WHEN** `PlaceHold` is invoked with a type, a scope and a reason
- **THEN** an `active` Hold persists carrying that type, scope, reason, the placement actor (from `ActorContext`) and the placement moment, and a `CustomerHoldPlaced` event is recorded in the same transaction

#### Scenario: An operator lifts an operator-liftable Hold

- **WHEN** `LiftHold` is invoked on an `active` `admin` (resp. `fraud`, `compliance`, `credit`) Hold
- **THEN** the Hold's `status` becomes `lifted`, the lift actor + lift moment + `lift_reason` are recorded, and a `CustomerHoldLifted` event is recorded in the same transaction

#### Scenario: Operator-lift of an auto-managed Hold is rejected

- **WHEN** `LiftHold` is invoked on an `active` `kyc` or `payment` Hold (an auto-managed type)
- **THEN** an `IllegalHoldLift` is raised, the Hold stays `active`, and no `CustomerHoldLifted` event is recorded — these types lift only via their system clearing signal

#### Scenario: Lifting an already-lifted Hold is rejected

- **WHEN** `LiftHold` is invoked on a Hold whose `status` is already `lifted`
- **THEN** an `IllegalHoldLift` is raised and state and the event log are unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.8 (place/lift; placement & lift actor/moment recorded), § 4.8.1 (DEC-160 per-type lift discipline — auto-lift permitted on `kyc`/`payment`, operator lift required on `admin`/`fraud`/`compliance`/`credit`) · § 14.8 BR-K-Hold-1 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-10 (place/lift records actor + moment), AC-K-FSM-11 (lift discipline — `kyc`/`payment` auto-lift, the other four operator-lift; auto-lift on the four is rejected) · spec/04-decisions/decisions.md DEC-160 (E6-07 per-type lift) · decisions/2026-06-18-hold-lift-discipline-per-type.md · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional recording)._

### Requirement: Hold Events

Each Hold placement SHALL record a verbatim **`CustomerHoldPlaced`** domain event and each Hold lift a verbatim **`CustomerHoldLifted`** domain event (PRD § 15.1) through the platform `DomainEventRecorder`, **within the same database transaction** as the Hold write, tagged with module `parties`, the acting `actor_role` + id from the `ActorContext` seam, `entity_type = 'Hold'` and the Hold id, and a **PII-free** payload carrying the `hold_id`, `hold_type`, `scope_type`, `scope_id` and the business `reason` (no name, email, phone or date of birth). Because the PRD event catalog names only the `Customer`-scoped Hold events, these two names SHALL be recorded for Holds of **every** scope (the `scope_type` + `scope_id` in the payload distinguish a Customer-, Account- or Profile-scoped Hold — the zero-invention reading of AC-K-FSM-10's "or Profile/Account analogs"). No Hold event name outside this pair SHALL be recorded by this change.

#### Scenario: Placing a Hold records a PII-free CustomerHoldPlaced

- **WHEN** a Hold is placed
- **THEN** a `CustomerHoldPlaced` event is recorded in the writing transaction, tagged module `parties`, `entity_type` `Hold`, with a payload of `hold_id` / `hold_type` / `scope_type` / `scope_id` / `reason` and no personal data

#### Scenario: Lifting a Hold records a CustomerHoldLifted

- **WHEN** a Hold is lifted (by the operator path or the system auto-lift)
- **THEN** a `CustomerHoldLifted` event is recorded in the lifting transaction with a PII-free payload referencing the same Hold

#### Scenario: The two Hold event names cover every scope

- **WHEN** an Account-scoped or Profile-scoped Hold is placed
- **THEN** the event recorded is still `CustomerHoldPlaced`, with `scope_type` (`account` / `profile`) + `scope_id` in the payload identifying the scope — no other Hold event name is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 15.1 (`CustomerHoldPlaced` / `CustomerHoldLifted` — the only Hold events named) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-EVT-2 (`CustomerHoldPlaced`/`CustomerHoldLifted`; audit metadata on the payload), AC-K-FSM-10 ("or Profile/Account analogs") · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional outbox; PII-free payloads) · openspec/specs/event-substrate/spec.md (Transactional Event Recording; Domain Event Envelope) · CLAUDE.md invariants 4 & 10._

### Requirement: Hold and Sanctions Read-API

The Parties module SHALL expose a **uniform read contract** answering *"is this scope clear to transact?"* by returning the **`(sanctions_status, active-Hold-list)` tuple** (DEC-181). The contract SHALL be an interface returning a **PII-free DTO** (carrying the scope's `sanctions_status` and the list of active Hold types — never the `Hold` Eloquent model, preserving the no-model-leak boundary law), with a convenience predicate that a scope **is clear** iff its `sanctions_status` is `passed` **and** it has no `active` Hold. The contract SHALL resolve **scope cascade**: interrogating a **Profile** SHALL return the Profile's own active Holds **and** the active Holds of its parent Customer (a Customer-scope Hold blocks every Profile — BR-K-Hold-3), while a **Profile-scope** Hold SHALL be returned only for that Profile (BR-K-Hold-4).

This change SHALL **expose the contract ready** (interface + DTO + a bound implementation); the **downstream enforcement** that consumes it — every DEC-181 transaction-initiation surface (Module S order completion / cart-add / redemption-request, Module C pickup / SO `planned` / shipment-request, Module E INV3 charge / refund routing) — is the receiving module's and is **NOT** in this change (Module K is Hold-blind by design — it provides the tuple, it does not block).

#### Scenario: The read-API returns the sanctions/Hold tuple for a Customer

- **WHEN** the read contract is asked whether a Customer scope is clear
- **THEN** it returns the Customer's `sanctions_status` and the list of that Customer's active Hold types, and reports "clear" iff `sanctions_status` is `passed` and there is no active Hold

#### Scenario: A Customer-scope Hold cascades to the Customer's Profiles

- **GIVEN** an active Customer-scope `fraud` Hold on a Customer with two Profiles
- **WHEN** the read contract is asked whether either Profile is clear
- **THEN** the Customer's `fraud` Hold appears in that Profile's active-Hold-list and the Profile is reported not clear

#### Scenario: A Profile-scope Hold isolates to that Profile

- **GIVEN** an active Profile-scope `payment` Hold on Profile X of a Customer who also has Profile Y
- **WHEN** the read contract is asked about Profile Y
- **THEN** Profile X's Hold does NOT appear for Profile Y, and Profile Y is clear if it has no Hold of its own and its Customer is sanctions-`passed` with no Customer-scope Hold

#### Scenario: The contract returns a PII-free DTO, not a model

- **WHEN** a downstream module consumes the read contract
- **THEN** it receives a DTO of `sanctions_status` + active Hold types (no `Hold` Eloquent model, no personal data) — and no transaction-initiation enforcement surface is implemented by this change (the consumers are deferred to Modules S/C/E)

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.8.1 (DEC-181 — Module K exposes the `(sanctions_status, active-Hold-list)` tuple; enforcement is the downstream surface's; Module K is Hold-blind), § 9.3 (the floor chain — K exposes the read-API, the downstream surface enforces) · § 14.8 BR-K-Hold-2 (read at every transaction-initiation surface), BR-K-Hold-3 (Customer cascade), BR-K-Hold-4 (Profile isolation) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-XM-12 (uniform "is this scope clear?" read API returning the tuple; single source of truth), AC-K-XM-3 (order-completion read), AC-K-BR-Hold-3/4 (cascade + isolation), AC-K-J-20 (the K-side tuple under the sanctions × Hold matrix) · spec/04-decisions/decisions.md DEC-181 · decisions/2026-06-11-modular-monolith-architecture.md (events + small read contracts; no cross-module model import)._

## MODIFIED Requirements

### Requirement: Customer KYC Lifecycle

The Customer SHALL carry a **KYC lifecycle that is separate from the Customer status FSM**: the four-state domain `not_required → pending → verified | rejected`, held in an **additive nullable** `kyc_status` field (DEC-071 — a NULL `kyc_status` denotes a Customer created un-screened). The Customer SHALL also carry an administratively-set `kyc_required` flag and an enhanced-KYC trigger flag + timestamp, both additive nullable.

Setting `kyc_required` SHALL transition KYC `not_required → pending`. A Customer in KYC `pending` SHALL transition to `verified` (identity verification cleared) or to `rejected` (failed) via explicit operator Actions that are the sole writers of `kyc_status`. KYC `verified` and `not_required` are the **cleared** (non-blocking) states; `pending` and `rejected` are blocking. The blocking effect on purchases is realized by the **`kyc` Hold** (the *Hold Registry*): setting `kyc_required` SHALL **auto-place** a Customer-scope `kyc` Hold within the same transaction as the `not_required → pending` write; recording `verified` SHALL **auto-lift** the Customer's active `kyc` Hold(s) within the same transaction (the system auto-lift the per-type discipline permits — DEC-160); recording `rejected` SHALL **leave** the `kyc` Hold in place (Compliance reviews case-by-case — § 9.1). This coupling is within-module Action orchestration (the KYC Action calls the Hold place/lift), since KYC records no domain event of its own.

The enhanced-KYC trigger flag + timestamp SHALL exist as additive nullable fields recording whether the Customer crossed the €10,000-single / €50,000-cumulative threshold. The **detection** of that crossing (the periodic scan and the at-order-completion check) reads cumulative-spend data that does not exist at launch and is **deferred**; only the fields ship.

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

#### Scenario: Enhanced-KYC fields exist but detection is deferred

- **WHEN** a Customer is inspected
- **THEN** it carries a nullable enhanced-KYC flag and timestamp, and there is no operation in this change that auto-sets them from purchase totals (the detection job is a documented seam)

#### Scenario: Illegal KYC transitions are rejected

- **WHEN** `RecordKycVerified` or `RecordKycRejected` is invoked on a Customer whose `kyc_status` is not `pending`
- **THEN** an `IllegalKycTransition` is raised, `kyc_status` is unchanged, and no `kyc` Hold is placed or lifted

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer KYC state + `kyc_required` flag + enhanced-KYC trigger fields), § 9.1 (KYC four-state lifecycle; `not_required` default; setting `kyc_required` → `pending`; `pending` auto-places the `kyc` Hold, `verified` auto-lifts it, `rejected` leaves it; cleared = `verified` ∨ `not_required`), § 4.8 / § 4.8.1 (the `kyc` Hold — auto-place/auto-lift coupling; DEC-160), § 15.1 (no KYC event family; `CustomerHoldPlaced`/`CustomerHoldLifted`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-3 (KYC FSM separate; the `kyc` Hold auto-places on `pending` and auto-lifts on `verified`), AC-K-J-7 (KYC required → Hold blocks → verified → Hold lifts → purchases resume), AC-K-J-7a (enhanced-KYC trigger fields) · spec/04-decisions/decisions.md DEC-071, DEC-035, DEC-160 · decisions/2026-06-18-hold-lift-discipline-per-type.md (the `kyc` auto-lift is the system path; operator lift of a `kyc` Hold is rejected) · decisions/2026-06-12-event-substrate-and-audit-store.md (audit trail; transactional Hold events). The enhanced-KYC detection job (AC-K-J-7a) remains a deferred seam._

### Requirement: Birth States Recorded, Lifecycle Transitions Deferred

Every Parties entity that carries a lifecycle state SHALL define its full state domain and SHALL be created in its birth state: Customer `pending`, Account `active`, Producer `draft`, Club `active`, ProducerAgreement `draft`, Profile `Applied` (Supplier carries no lifecycle state). The **supply-side** lifecycle — Producer, ProducerAgreement and Club — SHALL implement its state transitions and emit its lifecycle events, as governed by the Requirements *Producer Lifecycle*, *ProducerAgreement Lifecycle*, *Club Lifecycle* and *Supply-Side Lifecycle Events*. The **Customer and Producer compliance-screening lifecycles** — the KYC FSM and the Customer sanctions FSM, **each separate from the Customer/Producer status FSM** — SHALL be implemented as governed by the Requirements *Customer KYC Lifecycle*, *Customer Sanctions Screening Lifecycle*, *Producer KYC Lifecycle* and *Sanctions Screening Events*; their fields are added additively (nullable — DEC-071). The **demand-side status** lifecycle SHALL remain deferred: there SHALL be no Customer, Account or Profile **status** transition (`pending → active → …`), no Profile approval/activation workflow, no producer membership approve/decline write, no Originating-Club lock (the `originating_club_id` field SHALL retain its no-mutation seam), no Hero Package Capacity Invariant, and no Customer-segment derivation — and consequently no demand-side status-change domain event (for example `CustomerActivated`, `ProfileActivated`, `OriginatingClubLocked`, `CustomerSegmentChanged`) SHALL be emitted, until the demand-side change(s) implement them. The **`kyc` Hold coupling** (auto-place on KYC `pending`, auto-lift on `verified`) and the **unified Hold registry** are now implemented (the Requirements *Hold Registry*, *Hold Lifecycle and Lift Discipline*, *Hold Events* and *Hold and Sanctions Read-API*); the Hold→`suspended` **status** coupling, however, remains deferred with the demand-side status FSMs (placing a Hold records the Hold but performs no status transition).

#### Scenario: Each entity is born in its birth state

- **WHEN** a Customer, Account, Producer, Club, ProducerAgreement or Profile is created
- **THEN** its state is, respectively, `pending`, `active`, `draft`, `active`, `draft`, `Applied`

#### Scenario: Supply-side, compliance and Hold transitions exist; demand-side status transitions do not

- **WHEN** the Parties code surface is inspected
- **THEN** Producer, ProducerAgreement and Club expose lifecycle-transition operations and record their lifecycle events, the Customer/Producer KYC and Customer sanctions screening FSMs expose their transitions, and the unified Hold registry exposes place/lift with the `kyc` Hold auto-placed on KYC `pending` and auto-lifted on `verified`
- **AND** Customer, Account and Profile expose no operation that transitions their **status** out of its birth state, the `originating_club_id` field has no mutation surface, no demand-side status event (`CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked` / `CustomerSegmentChanged`) is recordable, and placing a Hold performs no status transition (the Hold→`suspended` coupling is deferred with the demand-side FSMs)

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 / § 4.2.1 / § 4.3 / § 4.4 / § 4.6.1 / § 4.7 (per-entity state machines + birth states) · § 4.8 / § 4.8.1 (the unified Hold registry + the `kyc` Hold coupling — now implemented) · § 9.1 / § 9.2 (KYC and sanctions screening FSMs) · § 6.1 (the Originating-Club lock — demand-side) · § 13 (Hero Package Capacity Invariant — demand-side) · § 5 (Customer segments — demand-side) · § 10.1 (Hold→suspension coupling — demand-side) · § 15 (lifecycle event families) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-3 / AC-K-FSM-4 (KYC + sanctions FSMs separate), AC-K-FSM-10 / AC-K-FSM-11 (Hold lifecycle + lift discipline), AC-K-FSM-9 (the Hold→Account-suspension coupling — deferred demand-side) · spec/05-release/Build_Workplan_v0.3-MVP.md § Phase 2 (the unified Hold is a Phase-2 deliverable) · openspec/changes/archive/2026-06-17-parties-compliance/proposal.md (the Hold split to this change)._
