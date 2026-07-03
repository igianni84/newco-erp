## MODIFIED Requirements

### Requirement: Hold Registry

The Parties module SHALL provide a **unified, trigger-agnostic Hold registry** — the single account-restriction primitive that gates commercial activity. A Hold SHALL carry: a `hold_type` from the **eight-value** domain `admin | kyc | payment | fraud | compliance | credit | chargeback_review | storage_payment_failed` — the six § 4.8 base types plus the two finance-driven types § 4.8.1/§ 15.8 name and Module K consumes from Module E, `chargeback_review` (DEC-168) and `storage_payment_failed` (DEC-160), completed to eight first-class coordinate values by canon MVP-DEC-008 (ADR `2026-07-01-adopt-dec-008-hold-types-8.md`); a **scope** comprising a `scope_type` from `customer | account | profile` and a `scope_id` (the id of the scoped Customer, Account or Profile — a within-module reference); a `status` from `active | lifted` (born `active`); an optional placement `reason`; the placement actor (role + id, from the `ActorContext` seam) and the placement moment; and, once lifted, the lift actor + lift moment + an optional `lift_reason`. The Hold SHALL be a new `parties_holds` table (the module-table-prefix convention) added by a single **additive** migration; its value-set columns SHALL carry the layered enforcement idiom (a string column + the backed-enum cast on both engines, plus a PostgreSQL-only `CHECK` deriving from `Enum::cases()`).

A scope MAY carry **multiple concurrent `active` Holds** (any one of which blocks the activity it gates — the blocking is the downstream surface's, per the *Hold and Sanctions Read-API* requirement). Module K SHALL be the **registry-of-record** for every Hold, and the registry SHALL be **trigger-agnostic**: it records the type and state of a Hold regardless of how the placement was triggered, and a **manual operator-placement path** SHALL exist for every type (the automatic triggers for `payment`/`fraud`/`compliance`/`credit` and for the two finance-driven types `chargeback_review`/`storage_payment_failed` are Module E/S signals deferred to those modules; the registry is unchanged by their automation depth — AC-K-MVP-2). The `hold_type` enum SHALL expose an `autoLiftable(): bool` predicate that is true for `kyc` and `payment` only (consumed by the *Hold Lifecycle and Lift Discipline* requirement).

#### Scenario: The Hold entity carries its type, scope, status and audit metadata

- **WHEN** a Hold is placed on a scope with a type
- **THEN** a `parties_holds` row persists carrying the `hold_type`, the `scope_type` + `scope_id`, `status = active`, the placement actor (role + id) and the placement moment, and (until lifted) a null lift actor / lift moment

#### Scenario: The eight Hold types and the three scopes are the domain

- **WHEN** the `HoldType` and `HoldScope` enums are inspected
- **THEN** `HoldType` is exactly `admin | kyc | payment | fraud | compliance | credit | chargeback_review | storage_payment_failed` and `HoldScope` is exactly `customer | account | profile`, each a string-backed enum whose `->value` is the spec token
- **AND** `HoldType::autoLiftable()` is true for `kyc` and `payment` and false for `admin`, `fraud`, `compliance`, `credit`, `chargeback_review`, `storage_payment_failed`

#### Scenario: Multiple concurrent Holds may exist on one scope

- **WHEN** a `kyc` Hold and an `admin` Hold are both placed on one Customer
- **THEN** both are recorded `active` on that Customer scope concurrently (the scope is not single-Hold)

#### Scenario: The registry is trigger-agnostic with a manual-placement path for every type

- **WHEN** an operator manually places a Hold of any of the eight types
- **THEN** the Hold is recorded identically regardless of type — Module K is the registry-of-record, and no automatic upstream trigger is required for the record to exist

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.8 (the unified Hold entity — three scopes Customer/Account/Profile, placement/lift audit metadata; Module K registry-of-record), § 4.8.1 (multiple concurrent Holds; trigger-agnostic; names the two finance-driven types), § 15.8 (the consumed Module-E events `CustomerChargebackFlagged`/`StoragePaymentFailed` that create the two finance-driven Holds) · § 14.8 BR-K-Hold-1 (multiple Holds, any blocks) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-10 (Hold lifecycle — **eight** types × three scopes, audit metadata), AC-K-EVT-18/19 (Module K consumes `CustomerChargebackFlagged`→`chargeback_review` Hold, `StoragePaymentFailed`→`storage_payment_failed` Hold), AC-K-BR-Hold-1 (multiple concurrent Holds), AC-K-MVP-2 (trigger-agnostic registry; manual-placement path) · canon c-mless/documentation MVP_Decisions_Register_v0.1.md:133 (**MVP-DEC-008** — Hold-type enum completed to 8, Q-AD-3 Option B, Paolo-ratified; the two finance-driven types are 8 first-class coordinate values, not sub-types) · spec/04-decisions/decisions.md DEC-181 (uniformity), DEC-160 (per-type lift — the `autoLiftable` partition; `storage_payment_failed`), DEC-168 (K is registry-of-record; `chargeback_review`), DEC-071 (additive nullable field pattern) · decisions/2026-07-01-adopt-dec-008-hold-types-8.md (local adoption of DEC-008 — the eight-value domain, both new types operator-lift-only), decisions/2026-06-18-hold-lift-discipline-per-type.md · decisions/2026-06-12-production-db-engine.md (Postgres-truthful migration; CHECK-from-cases idiom)._

### Requirement: Hold Lifecycle and Lift Discipline

A Hold SHALL be placed and lifted by explicit operator Actions that are the sole writers of `parties_holds`, each running inside one `DB::transaction`. `PlaceHold` SHALL create a Hold (`status = active`) recording the placement actor, moment and optional `reason`, and SHALL record the `CustomerHoldPlaced` event (per the *Hold Events* requirement) in the same transaction. `LiftHold` SHALL re-read the Hold under a transaction lock, set `status = lifted` recording the lift actor, lift moment and optional `lift_reason`, and record `CustomerHoldLifted` in the same transaction.

`LiftHold` SHALL enforce the **per-type lift discipline** (DEC-160; canon MVP-DEC-008; ADR `2026-06-18-hold-lift-discipline-per-type.md`, ADR `2026-07-01-adopt-dec-008-hold-types-8.md`): a Hold whose type is **auto-managed** (`HoldType::autoLiftable()` — `kyc` or `payment`) SHALL NOT be lifted by the operator path and SHALL be **rejected** with a localized `IllegalHoldLift`, because those types are lifted by the system on their clearing signal (the `kyc` auto-lift is wired in this change via the *Customer KYC Lifecycle* requirement; the `payment` auto-lift trigger is a deferred Module-E seam). A Hold of type `admin`, `fraud`, `compliance`, `credit`, `chargeback_review` or `storage_payment_failed` SHALL be lifted freely by the operator path — the two finance-driven types are **operator-lift-only** at launch: `chargeback_review` is resolved by an operator dispute review with no auto-lift signal, and `storage_payment_failed` is manual-first (D4), its `StoragePaymentSucceeded` per-cycle auto-lift a deferred Module-E seam (AC-K-FSM-11; AC-K-EVT-18/19). Lifting a Hold that is not `active` (already `lifted`) SHALL be **rejected** with a localized `IllegalHoldLift`, leaving state and the event log unchanged.

#### Scenario: Place a Hold records the placement actor, reason and moment

- **WHEN** `PlaceHold` is invoked with a type, a scope and a reason
- **THEN** an `active` Hold persists carrying that type, scope, reason, the placement actor (from `ActorContext`) and the placement moment, and a `CustomerHoldPlaced` event is recorded in the same transaction

#### Scenario: An operator lifts an operator-liftable Hold

- **WHEN** `LiftHold` is invoked on an `active` `admin` (resp. `fraud`, `compliance`, `credit`, `chargeback_review`, `storage_payment_failed`) Hold
- **THEN** the Hold's `status` becomes `lifted`, the lift actor + lift moment + `lift_reason` are recorded, and a `CustomerHoldLifted` event is recorded in the same transaction

#### Scenario: Operator-lift of an auto-managed Hold is rejected

- **WHEN** `LiftHold` is invoked on an `active` `kyc` or `payment` Hold (an auto-managed type)
- **THEN** an `IllegalHoldLift` is raised, the Hold stays `active`, and no `CustomerHoldLifted` event is recorded — these types lift only via their system clearing signal

#### Scenario: Lifting an already-lifted Hold is rejected

- **WHEN** `LiftHold` is invoked on a Hold whose `status` is already `lifted`
- **THEN** an `IllegalHoldLift` is raised and state and the event log are unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.8 (place/lift; placement & lift actor/moment recorded), § 4.8.1 (DEC-160 per-type lift discipline — auto-lift permitted on `kyc`/`payment`, operator lift required on `admin`/`fraud`/`compliance`/`credit` and the two finance-driven types `chargeback_review`/`storage_payment_failed`), § 15.8 (the two finance-driven types + their Module-E lift signals) · § 14.8 BR-K-Hold-1 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-10 (place/lift records actor + moment), AC-K-FSM-11 (lift discipline — `kyc`/`payment` auto-lift, the other **six** operator-lift; `storage_payment_failed` per-cycle auto-lift on `StoragePaymentSucceeded` is the deferred Module-E path, `chargeback_review` no-auto-lift), AC-K-EVT-18/19 · canon c-mless/documentation MVP_Decisions_Register_v0.1.md:133 (MVP-DEC-008) · spec/04-decisions/decisions.md DEC-160 (E6-07 per-type lift), DEC-168 (chargeback registry-of-record) · decisions/2026-06-18-hold-lift-discipline-per-type.md, decisions/2026-07-01-adopt-dec-008-hold-types-8.md (both finance-driven types operator-lift-only at launch) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional recording)._

### Requirement: Hold-Driven Status Coupling

Placing and lifting a Hold SHALL drive the demand-side status of the covered scopes — closing the seam the *Birth States Recorded, Lifecycle Transitions Deferred* requirement held open (*"placing a Hold records the Hold but performs no status transition"*). A status-bearing scope (Customer, Account, Profile) SHALL be `suspended` **iff** it is covered by at least one **active** Hold, where coverage is: an active Hold on that exact scope, **plus** — for a Profile — an active **Customer-scope** Hold on its owning Customer (the BR-K-Hold-3 cascade; Profile-scope and Account-scope Holds isolate — BR-K-Hold-4). This is **recomputed from Hold coverage** on every placement/lift, not tracked by provenance (decisions/2026-06-19-hold-status-coupling.md).

`PlaceHold` SHALL, in its recording transaction and after appending the Hold, drive every covered scope **currently in its suspendable from-state** to `suspended` by invoking the matching explicit Action — `scope_type = customer` ⇒ `SuspendCustomer` (cascading to the Customer's `Active` Profiles), `account` ⇒ `SuspendAccount`, `profile` ⇒ `SuspendProfile`. A Hold whose covered scope is **not** in its suspendable from-state SHALL record the Hold and perform **no** transition — in particular the `kyc` Hold auto-placed on a **`pending`** Customer at onboarding suspends nothing (the from-state guard preserves the independence of the status FSM from the KYC/sanctions FSMs). `LiftHold` (operator) and the system `kyc`-lift in `RecordKycVerified` SHALL, after lifting, **restore** every covered scope **currently `suspended`** to `active` by invoking the matching `Reactivate*` Action — **iff** re-querying coverage shows **no other active Hold** still covers that scope (BR-K-Hold-1: many Holds may coexist; restore only when the last covering Hold is gone). The coupling SHALL remain **within Module K** (no cross-module access) and SHALL record the status events (`CustomerSuspended`/`ProfileSuspended`/… and their reactivations) per the *Demand-Side Status Events* requirement; a Hold placement/lift that drives no transition records only its own `CustomerHoldPlaced`/`CustomerHoldLifted`.

#### Scenario: Placing a Hold on an active scope suspends it

- **WHEN** `PlaceHold` records an `admin` Hold whose scope is an `active` Customer (respectively an `active` Account, an `Active` Profile)
- **THEN** in the same transaction the scope transitions to `suspended` and the corresponding suspension event(s) are recorded (a Customer-scope Hold additionally cascades `ProfileSuspended` to the Customer's `Active` Profiles)

#### Scenario: A Hold on a non-suspendable scope drives no transition

- **WHEN** the `kyc` Hold is auto-placed on a Customer in `pending` (onboarding KYC), or a Hold is placed on a Profile in `Applied`
- **THEN** the Hold is recorded (`CustomerHoldPlaced`) but the scope's status is unchanged and no suspension event is recorded (the from-state guard — the status FSM stays independent of the KYC/sanctions FSMs)

#### Scenario: Lifting the last covering Hold restores; an earlier lift with coverage remaining does not

- **GIVEN** a Profile driven to `Suspended` while it carries two active Holds (its own `admin` Hold and a cascading Customer-scope Hold)
- **WHEN** one of the two Holds is lifted
- **THEN** the Profile stays `Suspended` (coverage remains); **WHEN** the second (last covering) Hold is lifted **THEN** the Profile returns to `Active` with one `ProfileReactivated`

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1 (`Active → Suspended` via a Profile-level or cascading Customer-level Hold; `Suspended → Active` when the triggering Hold is lifted), § 4.7 (Account-level Holds drive `active → suspended`), § 10.1 (Customer-level Hold blocks all Profiles; reactivation when the triggering Hold is lifted), § 4.8 / § 4.8.1 (the unified Hold registry; **eight** types — canon MVP-DEC-008; the `*Reactivated` event on lift), § 14.8 BR-K-Hold-1/3/4 (multiple Holds coexist — any blocks; Customer-scope cascades, Profile-scope isolates) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-2a (suspension state preservation), AC-K-FSM-9 (Account Holds drive `active → suspended`, lift drives `suspended → active`), AC-K-BR-Hold-1/3/4 · decisions/2026-06-19-hold-status-coupling.md (coverage-recompute; explicit Actions invoked by the Hold paths; restore iff uncovered), decisions/2026-06-18-hold-lift-discipline-per-type.md (the system `kyc`-lift path), openspec/specs/party-registry/spec.md (the *Hold Registry*, *Hold Lifecycle and Lift Discipline* and *Hold and Sanctions Read-API* requirements this builds on)._
