## MODIFIED Requirements

### Requirement: Operator places and lifts Customer Holds through the console

The console SHALL let an operator **place** and **lift** Holds on a Customer through the Customer view, routing every write through the Module K domain Actions (`PlaceHold` / `LiftHold`) and never an Eloquent write. The view SHALL render the Customer's Holds — those scoped to the Customer itself, to its co-provisioned **Account**, and to its **Profiles** — read-only as a table, each row showing the Hold's `hold_type`, `scope_type`, `status`, `reason`, the placement actor + moment and (once lifted) the lift actor + moment, read from the `Hold` model within the `{Models}` read surface (state enums rendered through their cast, never imported).

**Place.** A form-bearing header action SHALL collect a `hold_type` (the eight-value `HoldType` domain `admin | kyc | payment | fraud | compliance | credit | chargeback_review | storage_payment_failed` — canon MVP-DEC-008), a `scope_type` (`HoldScope` — `customer | account | profile`), the scope target resolved from the Customer (the Customer itself, its Account, or a selected Profile) and an optional `reason`, constructing the `HoldType` and `HoldScope` **operand** enums from the form values and invoking `PlaceHold`. The form's type options SHALL derive from `HoldType::cases()` (value-keyed, in enum order), so a manual operator-placement path SHALL exist for **every** one of the eight types (the registry is trigger-agnostic). Placement SHALL record exactly one `CustomerHoldPlaced` domain event carrying the `actor_role: newco_ops` audit envelope and a PII-free payload.

**Lift.** Each **active** Hold whose type is **operator-liftable** — `admin | fraud | compliance | credit | chargeback_review | storage_payment_failed`, i.e. NOT `HoldType::autoLiftable()` (the six operator-lift-only types; the two finance-driven types are operator-lift-only at launch — canon MVP-DEC-008) — SHALL expose a per-row `Lift` action collecting an optional `lift_reason` and invoking `LiftHold` with that Hold's id; lifting SHALL record exactly one `CustomerHoldLifted` event with the audit envelope. An **auto-managed** type (`kyc | payment`) SHALL expose **no** Lift action, and an attempt to lift one through the domain SHALL be rejected (`IllegalHoldLift`, auto-managed) and surfaced as a notification without state change. Lifting a Hold that is not `active` SHALL likewise be rejected (`IllegalHoldLift`, not-active) and surfaced without state change.

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
- **THEN** the `kyc` Hold row exposes no `Lift` action (`admin` / `fraud` / `compliance` / `credit` / `chargeback_review` / `storage_payment_failed` rows do)
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

_Source: openspec/specs/party-registry/spec.md (Hold Registry; Hold Lifecycle and Lift Discipline; Hold Events; Hold-Driven Status Coupling) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.8 (Hold — unified blocking mechanism; three scopes × **eight** types — canon MVP-DEC-008; audit metadata), §4.8.1 (Hold semantics — concurrency, cascade, isolation; DEC-160 per-type lift discipline; N2 trigger-agnostic registry, manual-placement path for every type), §15.8 (the two finance-driven types consumed from Module E), §14.8 BR-K-Hold-1..5 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-10 (Hold place + lift on each scope/type; audit metadata + events), AC-K-FSM-11 (per-type lift discipline — `kyc`/`payment` auto-lift, `admin`/`fraud`/`compliance`/`credit` + the two finance-driven types operator-lift), AC-K-EVT-18/19, AC-K-FSM-9 (Account FSM driven by Account-Hold coupling) · canon c-mless/documentation MVP_Decisions_Register_v0.1.md:133 (MVP-DEC-008) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (Place / lift a Customer Hold — the cross-cutting compliance action), §5.2 (no SoD mandated for Hold place/lift), §1.3 (`actor_role` envelope) · app/Modules/Parties/Actions/PlaceHold.php, LiftHold.php · app/Modules/Parties/Models/Hold.php · app/Modules/Parties/Enums/{HoldType,HoldScope,HoldStatus}.php · app/Modules/Parties/Events/{CustomerHoldPlaced,CustomerHoldLifted}.php · app/Modules/Parties/Exceptions/IllegalHoldLift.php · decisions/2026-07-01-adopt-dec-008-hold-types-8.md · decisions/2026-06-18-hold-lift-discipline-per-type.md · decisions/2026-06-19-hold-status-coupling.md · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md · decisions/2026-06-21-operator-console-operand-enum-carveout.md._
