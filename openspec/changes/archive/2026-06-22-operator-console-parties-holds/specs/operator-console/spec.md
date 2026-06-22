## ADDED Requirements

### Requirement: Operator places and lifts Customer Holds through the console

The console SHALL let an operator **place** and **lift** Holds on a Customer through the Customer view, routing every write through the Module K domain Actions (`PlaceHold` / `LiftHold`) and never an Eloquent write. The view SHALL render the Customer's Holds — those scoped to the Customer itself, to its co-provisioned **Account**, and to its **Profiles** — read-only as a table, each row showing the Hold's `hold_type`, `scope_type`, `status`, `reason`, the placement actor + moment and (once lifted) the lift actor + moment, read from the `Hold` model within the `{Models}` read surface (state enums rendered through their cast, never imported).

**Place.** A form-bearing header action SHALL collect a `hold_type` (the six-value `HoldType` domain `admin | kyc | payment | fraud | compliance | credit`), a `scope_type` (`HoldScope` — `customer | account | profile`), the scope target resolved from the Customer (the Customer itself, its Account, or a selected Profile) and an optional `reason`, constructing the `HoldType` and `HoldScope` **operand** enums from the form values and invoking `PlaceHold`. A manual operator-placement path SHALL exist for **every** one of the six types (the registry is trigger-agnostic). Placement SHALL record exactly one `CustomerHoldPlaced` domain event carrying the `actor_role: newco_ops` audit envelope and a PII-free payload.

**Lift.** Each **active** Hold whose type is **operator-liftable** — `admin | fraud | compliance | credit`, i.e. NOT `HoldType::autoLiftable()` — SHALL expose a per-row `Lift` action collecting an optional `lift_reason` and invoking `LiftHold` with that Hold's id; lifting SHALL record exactly one `CustomerHoldLifted` event with the audit envelope. An **auto-managed** type (`kyc | payment`) SHALL expose **no** Lift action, and an attempt to lift one through the domain SHALL be rejected (`IllegalHoldLift`, auto-managed) and surfaced as a notification without state change. Lifting a Hold that is not `active` SHALL likewise be rejected (`IllegalHoldLift`, not-active) and surfaced without state change.

**Status coupling (domain-owned, additive).** Placing and lifting SHALL drive the covered scope's status as the domain Action's own behaviour — `PlaceHold` suspends every covered scope currently in its suspendable from-state, and `LiftHold` restores a `suspended` scope only when no other active Hold still covers it — recording the corresponding status events. The console SHALL invoke **only** `PlaceHold` / `LiftHold`; it SHALL NOT call the `Suspend*` / `Reactivate*` Actions itself, SHALL NOT recompute suspension from Holds, and SHALL NOT suppress the coupling. This Hold-mediated path **coexists** with the direct status verbs (the manual path) of the _Operator advances a Customer through its status lifecycle_ requirement.

#### Scenario: Placing a Hold records CustomerHoldPlaced with the audit metadata

- **WHEN** an operator places a Hold of a chosen type and scope on a Customer through the place form
- **THEN** `PlaceHold` is invoked and a Hold exists `active` with its type, scope, reason and the placement actor + moment recorded
- **AND** exactly one `CustomerHoldPlaced` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, entity type `Hold`, and a PII-free payload (no name, email, phone or date of birth)

#### Scenario: Placing an operator Hold on an active Customer suspends it

- **GIVEN** an `active` Customer
- **WHEN** an operator places an `admin` Hold on the Customer
- **THEN** the Customer becomes `suspended`, and a `CustomerHoldPlaced` and a `CustomerSuspended` event are recorded

#### Scenario: A Hold on a non-suspendable scope drives no status transition

- **GIVEN** a `pending` Customer
- **WHEN** an operator places an `admin` Hold on the Customer
- **THEN** the Hold is recorded `active` and the Customer stays `pending`, with a `CustomerHoldPlaced` event and no `CustomerSuspended` event

#### Scenario: An operator lifts an operator-liftable Hold; the last covering lift restores

- **GIVEN** an `active` Customer covered by two active customer-scope Holds (`admin` and `fraud`), hence `suspended`
- **WHEN** the operator lifts the `admin` Hold
- **THEN** that Hold becomes `lifted`, a `CustomerHoldLifted` event is recorded, and the Customer stays `suspended` (the `fraud` Hold still covers it) — no `CustomerReactivated`
- **WHEN** the operator then lifts the `fraud` Hold (the last covering Hold)
- **THEN** the Customer becomes `active` and a `CustomerReactivated` event is recorded

#### Scenario: Operator-lift of an auto-managed Hold is not offered and is rejected by the domain

- **GIVEN** a Customer carrying an active `kyc` Hold
- **WHEN** the Holds table is inspected
- **THEN** the `kyc` Hold row exposes no `Lift` action (`admin` / `fraud` / `compliance` / `credit` rows do)
- **AND** an attempt to lift the `kyc` Hold through the domain raises `IllegalHoldLift`, surfaced as a danger notification, with the Hold unchanged and no event recorded

#### Scenario: Lifting an already-lifted Hold is rejected

- **GIVEN** a Hold already in status `lifted`
- **WHEN** an operator attempts to lift it
- **THEN** the domain raises `IllegalHoldLift` (not-active), the console surfaces it as a notification, and no `CustomerHoldLifted` event is recorded

#### Scenario: A Hold can be placed on each of the three scopes

- **GIVEN** a Customer with a co-provisioned Account and at least one Profile
- **WHEN** an operator places a Hold with scope `customer`, `account`, or `profile`
- **THEN** `PlaceHold` is invoked with the matching `HoldScope` and the scope id resolved from the Customer, and the placed Hold carries that `scope_type` and `scope_id`

#### Scenario: The Holds table renders the Customer's Holds read-only across scopes

- **WHEN** an operator opens a Customer that has Holds at `customer`, `account` and `profile` scope
- **THEN** the table shows each Hold's type, scope, status, reason, placement actor + moment (and lift actor + moment once lifted), with no field editable in place

_Source: openspec/specs/party-registry/spec.md (Hold Registry; Hold Lifecycle and Lift Discipline; Hold Events; Hold-Driven Status Coupling) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.8 (Hold — unified blocking mechanism; three scopes × six types; audit metadata), §4.8.1 (Hold semantics — concurrency, cascade, isolation; DEC-160 per-type lift discipline; N2 trigger-agnostic registry, manual-placement path for every type), §14.8 BR-K-Hold-1..5 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-10 (Hold place + lift on each scope/type; audit metadata + events), AC-K-FSM-11 (per-type lift discipline — `kyc`/`payment` auto-lift, `admin`/`fraud`/`compliance`/`credit` operator-lift), AC-K-FSM-9 (Account FSM driven by Account-Hold coupling) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (Place / lift a Customer Hold — the cross-cutting compliance action), §5.2 (no SoD mandated for Hold place/lift), §1.3 (`actor_role` envelope) · app/Modules/Parties/Actions/PlaceHold.php, LiftHold.php · app/Modules/Parties/Models/Hold.php · app/Modules/Parties/Enums/{HoldType,HoldScope,HoldStatus}.php · app/Modules/Parties/Events/{CustomerHoldPlaced,CustomerHoldLifted}.php · app/Modules/Parties/Exceptions/IllegalHoldLift.php · decisions/2026-06-18-hold-lift-discipline-per-type.md · decisions/2026-06-19-hold-status-coupling.md · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md · decisions/2026-06-21-operator-console-operand-enum-carveout.md._

## MODIFIED Requirements

### Requirement: Operator advances a Customer through its status lifecycle

The console SHALL surface the Customer's status-FSM transitions — **activate** (`ActivateCustomer`, `pending → active`, recording `CustomerActivated`), **suspend** (`SuspendCustomer`, `active → suspended`, recording `CustomerSuspended`), **reactivate** (`ReactivateCustomer`, `suspended → active`, recording `CustomerReactivated`) and **close** (`CloseCustomer`, `active | suspended → closed`, recording `CustomerClosed`) — each invoking the corresponding domain Action and recording its verbatim event with `actor_role: newco_ops`. These direct verbs are the **BR-K-Customer-1 "manual" suspension/reactivation path**; the Hold-mediated path (`PlaceHold` / `LiftHold`, whose coupling also moves Customer status) is realized by the _Operator places and lifts Customer Holds through the console_ requirement — the two paths coexist by design (ADR 2026-06-19). Each verb SHALL be **form-less** and SHALL present **no** "second actor required" affordance — Customer lifecycle is single-operator (the spec mandates no separation-of-duties for Customer). The console SHALL expose **no** submit, reject, or reopen action (the Customer FSM is not review-governed), and **no** KYC, sanctions, Account-lifecycle or Profile-lifecycle verb (the Hold place/lift surface is the separate _Operator places and lifts Customer Holds through the console_ requirement, not part of these status verbs).

Activation SHALL be governed by the Action's **composite, cross-slice gate** (acceptance of T&C and privacy + verified email + `sanctions_status = passed` + KYC cleared if required): an activation attempted with the gate unmet SHALL be rejected (`IllegalCustomerTransition::gateNotMet`) and surfaced as a notification without changing state — this slice provides **no** surface to satisfy those preconditions (they are set by the consumer-onboarding flow and the compliance console). Suspension SHALL cascade `ProfileSuspended` to the Customer's active Profiles, and reactivation SHALL cascade `ProfileReactivated` to its suspended-and-uncovered Profiles; these cascades are the domain Action's own behaviour, inherited by driving the Action (the console neither re-implements nor suppresses them). Any out-of-state transition (`IllegalCustomerTransition` — activate not from `pending`, suspend not from `active`, reactivate not from `suspended`, close not from `active | suspended`) SHALL be surfaced as a notification without changing state or recording an event.

#### Scenario: Activate a gate-met pending Customer

- **GIVEN** a `pending` Customer whose activation gate is satisfied (email verified, T&C + privacy accepted, `sanctions_status = passed`, KYC not required)
- **WHEN** an operator activates the Customer
- **THEN** the Customer becomes `active` and exactly one `CustomerActivated` event is recorded with `actor_role: newco_ops` and `actor_id` equal to the operator's id

#### Scenario: Activation with the gate unmet is rejected and surfaced

- **GIVEN** a `pending` Customer freshly created through the console (no onboarding acceptance recorded, `sanctions_status` unset)
- **WHEN** an operator attempts to activate the Customer
- **THEN** the domain raises an `IllegalCustomerTransition` (gate-not-met), the console surfaces it as a danger notification, and the Customer stays `pending` with no event recorded

#### Scenario: Suspend an active Customer, then reactivate it

- **GIVEN** an `active` Customer
- **WHEN** an operator suspends the Customer
- **THEN** the Customer becomes `suspended` and exactly one `CustomerSuspended` event is recorded with `actor_role: newco_ops`
- **WHEN** the operator then reactivates the Customer
- **THEN** the Customer becomes `active` and exactly one `CustomerReactivated` event is recorded

#### Scenario: Close a Customer

- **GIVEN** an `active` Customer (or a `suspended` Customer)
- **WHEN** an operator closes the Customer
- **THEN** the Customer becomes `closed` and exactly one `CustomerClosed` event is recorded with `actor_role: newco_ops`

#### Scenario: The console exposes the status verbs and none of the review-governance or deferred-compliance verbs

- **WHEN** the Customer view surface is inspected
- **THEN** it exposes activate, suspend, reactivate and close (and, per _Operator places and lifts Customer Holds through the console_, the Hold place/lift surface), no submit/reject/reopen action, no KYC/sanctions/account/profile action, and no verb presents a "second actor required" affordance

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator suspends a Customer not in `active`, reactivates one not in `suspended`, or closes one in `pending`
- **THEN** the domain raises an `IllegalCustomerTransition`, the console surfaces it as a notification, and the Customer's `status` and the domain-event log are unchanged

_Source: openspec/specs/party-registry/spec.md (Customer Onboarding Activation; Customer Suspension and Closure; Demand-Side Status Events; Hold-Driven Status Coupling) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (status FSM `pending → active → suspended → closed`; T&C/privacy a hard gate on `pending → active`), §10.1 (Customer-level suspension; reactivation on Hold lift), BR-K-Customer-1 (suspension is explicit — manual or via Hold — not Profile-driven) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-1 (Customer FSM + the five status events), §4.2 AC-K-BR-Customer-1, §5 AC-K-EVT-1 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (onboard/edit + run suspension — operator surface), §5.2 (no SoD mandated for Customer) · app/Modules/Parties/Actions/{ActivateCustomer,SuspendCustomer,ReactivateCustomer,CloseCustomer}.php · app/Modules/Parties/Exceptions/IllegalCustomerTransition.php · app/Modules/Parties/Events/{CustomerActivated,CustomerSuspended,CustomerReactivated,CustomerClosed}.php · decisions/2026-06-19-hold-status-coupling.md · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: The Customer console surfaces the orthogonal compliance and membership context read-only

The console's list and infolist SHALL display the Customer's **status**, **KYC status**, **sanctions status**, the co-provisioned **Account's status**, and the Customer's **Profiles** (membership) — each rendered **read-only** via the model casts and within-module reads (no cross-module import beyond `{Models}`; state enums rendered through their cast, never imported). The KYC and sanctions lifecycles SHALL be presented as **independent** axes carried on the Customer, **distinct from** the status FSM (a Customer may show `kyc_status = verified` with `sanctions_status = pending`, or vice-versa, each rendered on its own). The console SHALL expose **no write affordance** for KYC, sanctions, Account lifecycle, or Profile lifecycle in this slice — those surfaces belong to the KYC/sanctions, account and profile slices. The Hold place/lift affordance is the separate _Operator places and lifts Customer Holds through the console_ requirement (the one compliance write surface realized here).

#### Scenario: The list and infolist render the three lifecycles, account status and profiles read-only

- **WHEN** an operator opens a Customer in the console
- **THEN** the surface shows the Customer's status, KYC status, sanctions status, Account status and Profiles, all read-only, with no field editable in place

#### Scenario: KYC and sanctions are rendered as independent axes

- **GIVEN** a Customer with `kyc_status = verified` and `sanctions_status = pending`
- **WHEN** an operator views the Customer
- **THEN** both lifecycles are displayed independently, neither collapsed into the `pending/active/suspended/closed` status FSM

#### Scenario: The console exposes no deferred compliance or membership write action

- **WHEN** the Customer console (list, view and create surfaces) is inspected
- **THEN** it exposes no action to set KYC, record a sanctions screening, transition the Account, or transition a Profile

_Source: openspec/specs/party-registry/spec.md (Customer KYC Lifecycle; Customer Sanctions Screening Lifecycle; Account Status Lifecycle; Profile — Multi-Profile Membership) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (KYC + sanctions carried as separate lifecycles on Customer), §9.1 (KYC four-state lifecycle), §9.2 (sanctions four-state lifecycle), §9.4 (KYC and sanctions independent — both must clear independently), §4.7 (Account status parallels Customer) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-3 (KYC separate from Customer FSM), AC-K-FSM-4 (sanctions separate), AC-K-FSM-5 (KYC and sanctions independent), AC-K-FSM-9 (Account FSM) · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md (read-projection) · decisions/2026-06-21-operator-console-operand-enum-carveout.md (state enums rendered via cast, never imported)._
