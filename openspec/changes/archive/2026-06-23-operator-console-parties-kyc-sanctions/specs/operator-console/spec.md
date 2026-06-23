## ADDED Requirements

### Requirement: Operator manages a Customer's KYC verification through the console

The console SHALL let an operator drive the Customer's KYC verification lifecycle through the Customer view, routing every write through the Module K domain Actions (`RequireKyc` / `RecordKycVerified` / `RecordKycRejected`) and never an Eloquent write. The KYC FSM (`not_required → pending → verified | rejected`) is an **independent** axis carried on the Customer, distinct from the Customer status FSM and from the sanctions axis. Each verb SHALL be **form-less** and SHALL present **no** "second actor required" affordance — KYC is single-operator (the spec mandates no separation-of-duties for KYC). `kyc_status` is a **state** enum rendered and predicated through its model cast (`->value`), never imported.

**Require KYC.** A header action invoking `RequireKyc` SHALL transition `kyc_status` from `not_required` (or unset) to `pending`, set `kyc_required`, and — in the same domain transaction — **auto-place a Customer-scope `kyc` Hold**, recording exactly one `CustomerHoldPlaced` event. Via the shipped Hold-driven status coupling that placement SHALL suspend the Customer when it is `active` (recording `CustomerSuspended`). The verb SHALL record **no** KYC-specific domain event (the catalog names none). It SHALL be visible **iff** `kyc_status` is `not_required` or unset; a require attempted out of that from-state SHALL be rejected by the domain (`IllegalKycTransition::cannotRequire`) and surfaced as a notification without state change.

**Record KYC verified.** A header action invoking `RecordKycVerified` SHALL transition `kyc_status` from `pending` to `verified`, **auto-lift** every active `kyc` Hold (one `CustomerHoldLifted` each) and — via the coupling — reactivate the Customer when it was `suspended` and no other active Hold still covers it (recording `CustomerReactivated`). It SHALL record **no** KYC-specific event. It SHALL be visible **iff** `kyc_status` is `pending`; a verify out of that from-state SHALL be rejected (`IllegalKycTransition::cannotVerify`) and surfaced without state change.

**Record KYC rejected.** A header action invoking `RecordKycRejected` SHALL transition `kyc_status` from `pending` to `rejected`. It SHALL be **audit-only** — recording **no** domain event — and the active `kyc` Hold SHALL **remain** (the Customer stays restricted; rejection does not lift the Hold). It SHALL be visible **iff** `kyc_status` is `pending`; a reject out of that from-state SHALL be rejected (`IllegalKycTransition::cannotReject`) and surfaced without state change.

The console SHALL expose **no** Customer KYC "waive" verb — no such domain Action exists (only producer KYC is waivable). Visibility is the **complement** of each verb's domain from-state guard, so a rejected KYC transition is unreachable through the surface while the domain remains the enforcing floor for any out-of-band call.

#### Scenario: Require KYC on an active Customer flags pending, auto-places a kyc Hold, and suspends the Customer

- **GIVEN** an `active` Customer whose `kyc_status` is `not_required` (or unset)
- **WHEN** an operator requires KYC through the console
- **THEN** `RequireKyc` is invoked, `kyc_status` becomes `pending`, `kyc_required` is set, and a Customer-scope `kyc` Hold exists `active`
- **AND** the Customer becomes `suspended`, with exactly one `CustomerHoldPlaced` and one `CustomerSuspended` event recorded (`actor_role: newco_ops`, `actor_id` equal to the operator's id) and **no** KYC-specific event

#### Scenario: Record KYC verified auto-lifts the kyc Hold and reactivates the Customer

- **GIVEN** a `suspended` Customer with `kyc_status = pending`, covered only by its auto-placed `kyc` Hold
- **WHEN** an operator records KYC verified
- **THEN** `kyc_status` becomes `verified`, the `kyc` Hold becomes `lifted` with exactly one `CustomerHoldLifted` event, and the Customer becomes `active` with exactly one `CustomerReactivated` event — and **no** KYC-specific event

#### Scenario: Record KYC rejected is audit-only and the kyc Hold remains

- **GIVEN** a Customer with `kyc_status = pending` and an active `kyc` Hold
- **WHEN** an operator records KYC rejected
- **THEN** `kyc_status` becomes `rejected`, the `kyc` Hold stays `active` (the Customer stays restricted), and **no** domain event is recorded

#### Scenario: Each KYC verb is visible only in its legal from-state

- **WHEN** the Customer view surface is inspected
- **THEN** **Require KYC** is offered iff `kyc_status` is `not_required` or unset, and **Record KYC verified** and **Record KYC rejected** are offered iff `kyc_status` is `pending`
- **AND** an out-of-state invocation through the domain (require not from `not_required`/unset, verify or reject not from `pending`) raises an `IllegalKycTransition`, surfaced as a danger notification, with `kyc_status` and the domain-event log unchanged

#### Scenario: The console exposes no Customer KYC waive verb

- **WHEN** the Customer view surface is inspected
- **THEN** it exposes Require KYC, Record KYC verified and Record KYC rejected, exposes **no** KYC "waive" action, and no KYC verb presents a "second actor required" affordance

#### Scenario: The KYC verbs leave the sanctions axis untouched

- **GIVEN** a Customer with `sanctions_status = passed`
- **WHEN** an operator requires KYC and later records it verified
- **THEN** the Customer's `sanctions_status` stays `passed` throughout (KYC and sanctions are independent) and no sanctions screening event is recorded

_Source: openspec/specs/party-registry/spec.md (Customer KYC Lifecycle; Hold Lifecycle and Lift Discipline; Hold-Driven Status Coupling; Demand-Side Status Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §9.1 (KYC four-state lifecycle `not_required → pending → verified | rejected`; the `kyc` Hold auto-places on `pending` and auto-lifts on `verified`; manual-first launch posture), §9.4 (KYC and sanctions are independent), §9.5 (operator records the state — manual-first), §4.8 (Hold — unified blocking mechanism) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-3 (KYC FSM separate from Customer FSM; auto-Hold place on `pending`, auto-lift on `verified`), §2 AC-K-J-7 (KYC flag → verify journey with auto-Hold place then lift), §3 AC-K-FSM-5 (KYC and sanctions independent) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (Manage a KYC verification — operator action), §5.2 (no SoD mandated for KYC) · app/Modules/Parties/Actions/{RequireKyc,RecordKycVerified,RecordKycRejected}.php · app/Modules/Parties/Enums/KycStatus.php · app/Modules/Parties/Exceptions/IllegalKycTransition.php · app/Modules/Parties/Events/{CustomerHoldPlaced,CustomerHoldLifted,CustomerSuspended,CustomerReactivated}.php · decisions/2026-06-18-hold-lift-discipline-per-type.md · decisions/2026-06-19-hold-status-coupling.md · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md · decisions/2026-06-21-operator-console-operand-enum-carveout.md._

### Requirement: Operator records a Customer's sanctions screening through the console

The console SHALL let an operator record a sanctions-screening verdict on a Customer through the Customer view, routing the write through the Module K domain Action `RecordCustomerScreening` and never an Eloquent write. The sanctions FSM (`pending → passed | failed | under_review`) is an **independent** axis carried on the Customer, distinct from the Customer status FSM and from the KYC axis. The order-completion purchase gate that reads `sanctions_status = passed` is **Module S's** concern (Module K records the screening state; Module S enforces at checkout) and is out of this console's scope. The action SHALL be single-operator with **no** "second actor required" affordance (the spec mandates no separation-of-duties for sanctions).

A **form-bearing** header action SHALL collect a `verdict` (the four-value `SanctionsStatus` operand — `pending | passed | failed | under_review`) and a `trigger_source` (a restricted `ScreeningTriggerSource` operand offering only the operator-selectable `onboarding` and `compliance_ad_hoc` — the automated `cadence` and `aml_threshold` sources, deferred at launch per the manual-first posture, SHALL **never** be offered), constructing both operand enums from the form values and invoking `RecordCustomerScreening`. Recording SHALL set `sanctions_status`, stamp `last_screening_at`, set `next_rescreen_at` to **12 months** after that moment, and set `screening_trigger_source`.

Recording SHALL record **exactly one** of `CustomerOnboardingScreeningPassed` / `CustomerOnboardingScreeningFailed` / `CustomerRescreeningPassed` / `CustomerRescreeningFailed`, selected by **verdict × source**: `passed` + `onboarding` → onboarding-passed; `failed` + `onboarding` → onboarding-failed; `passed` + a non-onboarding source → rescreening-passed; `failed` + a non-onboarding source → rescreening-failed. A `pending` or `under_review` verdict SHALL update `sanctions_status` but record **no** screening event.

The `onboarding` source is **first-screening-only**: once `last_screening_at` is set, `onboarding` SHALL no longer be offered in the form, and a domain re-onboarding attempt SHALL be rejected (`IllegalSanctionsTransition::onboardingAlreadyScreened`) and surfaced as a notification without state change.

#### Scenario: An onboarding screening with a passed verdict records the onboarding-passed event and stamps the screening fields

- **GIVEN** a Customer never screened (`last_screening_at` unset)
- **WHEN** an operator records a screening with verdict `passed` and source `onboarding`
- **THEN** `sanctions_status` becomes `passed`, `last_screening_at` is stamped, `next_rescreen_at` is 12 months later, `screening_trigger_source` is `onboarding`, and exactly one `CustomerOnboardingScreeningPassed` event is recorded with `actor_role: newco_ops` and `actor_id` equal to the operator's id

#### Scenario: A compliance-ad-hoc re-screen with a failed verdict records the rescreening-failed event

- **GIVEN** a Customer already screened once (`last_screening_at` set)
- **WHEN** an operator records a screening with verdict `failed` and source `compliance_ad_hoc`
- **THEN** `sanctions_status` becomes `failed` and exactly one `CustomerRescreeningFailed` event is recorded with `actor_role: newco_ops`

#### Scenario: A pending or under-review verdict updates the state but records no event

- **WHEN** an operator records a screening with verdict `under_review` (or `pending`)
- **THEN** `sanctions_status` is updated to that verdict and **no** sanctions screening event is recorded

#### Scenario: The form offers only the operator-selectable sources, and onboarding is first-screening-only

- **WHEN** the Record-screening form is inspected on a never-screened Customer
- **THEN** the `trigger_source` field offers exactly `onboarding` and `compliance_ad_hoc` (never `cadence` or `aml_threshold`) and the `verdict` field offers the four `SanctionsStatus` values
- **WHEN** the form is inspected on a Customer already screened (`last_screening_at` set)
- **THEN** `onboarding` is no longer offered, and an attempt to re-record an `onboarding` screening through the domain raises `IllegalSanctionsTransition::onboardingAlreadyScreened`, surfaced as a notification with `sanctions_status` and the domain-event log unchanged

#### Scenario: The screening sets the next re-screen twelve months ahead

- **WHEN** an operator records any screening
- **THEN** `next_rescreen_at` is exactly 12 months after the stamped `last_screening_at`

#### Scenario: Recording a screening leaves the KYC axis untouched

- **GIVEN** a Customer with `kyc_status = pending`
- **WHEN** an operator records a sanctions screening
- **THEN** the Customer's `kyc_status` stays `pending` (sanctions and KYC are independent)

_Source: openspec/specs/party-registry/spec.md (Customer Sanctions Screening Lifecycle; Demand-Side Status Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §9.2 (sanctions four-state lifecycle `pending → passed | failed | under_review`; trigger sources; 12-month re-screen cadence; the four screening events), §9.3 (the order-completion gate is Module S — Module K records the state and is sanctions-blind at the gate), §9.4 (sanctions and KYC independent), §9.5 (manual-first launch posture — operator runs the check and records the state, automation deferred) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-4 (sanctions FSM separate; re-screening events), §3 AC-K-FSM-5 (KYC and sanctions independent), §5 AC-K-EVT-12 (the four sanctions screening events — onboarding + 12-month re-screen) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (Review a sanctions-screening match — operator action), §5.2 (no SoD mandated for sanctions) · app/Modules/Parties/Actions/RecordCustomerScreening.php · app/Modules/Parties/Enums/{SanctionsStatus,ScreeningTriggerSource}.php · app/Modules/Parties/Exceptions/IllegalSanctionsTransition.php · app/Modules/Parties/Events/{CustomerOnboardingScreeningPassed,CustomerOnboardingScreeningFailed,CustomerRescreeningPassed,CustomerRescreeningFailed}.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md · decisions/2026-06-21-operator-console-operand-enum-carveout.md._

## MODIFIED Requirements

### Requirement: Operator advances a Customer through its status lifecycle

The console SHALL surface the Customer's status-FSM transitions — **activate** (`ActivateCustomer`, `pending → active`, recording `CustomerActivated`), **suspend** (`SuspendCustomer`, `active → suspended`, recording `CustomerSuspended`), **reactivate** (`ReactivateCustomer`, `suspended → active`, recording `CustomerReactivated`) and **close** (`CloseCustomer`, `active | suspended → closed`, recording `CustomerClosed`) — each invoking the corresponding domain Action and recording its verbatim event with `actor_role: newco_ops`. These direct verbs are the **BR-K-Customer-1 "manual" suspension/reactivation path**; the Hold-mediated path (`PlaceHold` / `LiftHold`, whose coupling also moves Customer status) is realized by the _Operator places and lifts Customer Holds through the console_ requirement — the two paths coexist by design (ADR 2026-06-19). Each verb SHALL be **form-less** and SHALL present **no** "second actor required" affordance — Customer lifecycle is single-operator (the spec mandates no separation-of-duties for Customer). The console SHALL expose **no** submit, reject, or reopen action (the Customer FSM is not review-governed), and **no** Account-lifecycle or Profile-lifecycle verb — the KYC verbs are the separate _Operator manages a Customer's KYC verification through the console_ requirement, sanctions screening the _Operator records a Customer's sanctions screening through the console_ requirement, and Hold place/lift the _Operator places and lifts Customer Holds through the console_ requirement, none part of these status verbs.

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

#### Scenario: The console exposes the status verbs and none of the review-governance or deferred-lifecycle verbs

- **WHEN** the Customer view surface is inspected
- **THEN** it exposes activate, suspend, reactivate and close (the KYC, sanctions and Hold place/lift surfaces are realized by their own requirements), no submit/reject/reopen action, no Account-lifecycle or Profile-lifecycle action, and no verb presents a "second actor required" affordance

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator suspends a Customer not in `active`, reactivates one not in `suspended`, or closes one in `pending`
- **THEN** the domain raises an `IllegalCustomerTransition`, the console surfaces it as a notification, and the Customer's `status` and the domain-event log are unchanged

_Source: openspec/specs/party-registry/spec.md (Customer Onboarding Activation; Customer Suspension and Closure; Demand-Side Status Events; Hold-Driven Status Coupling) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (status FSM `pending → active → suspended → closed`; T&C/privacy a hard gate on `pending → active`), §10.1 (Customer-level suspension; reactivation on Hold lift), BR-K-Customer-1 (suspension is explicit — manual or via Hold — not Profile-driven) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-1 (Customer FSM + the five status events), §4.2 AC-K-BR-Customer-1, §5 AC-K-EVT-1 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (onboard/edit + run suspension — operator surface), §5.2 (no SoD mandated for Customer) · app/Modules/Parties/Actions/{ActivateCustomer,SuspendCustomer,ReactivateCustomer,CloseCustomer}.php · app/Modules/Parties/Exceptions/IllegalCustomerTransition.php · app/Modules/Parties/Events/{CustomerActivated,CustomerSuspended,CustomerReactivated,CustomerClosed}.php · decisions/2026-06-19-hold-status-coupling.md · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: The Customer console surfaces the orthogonal compliance and membership context read-only

The console's list and infolist SHALL display the Customer's **status**, **KYC status**, **sanctions status**, the co-provisioned **Account's status**, and the Customer's **Profiles** (membership) — each rendered **read-only** via the model casts and within-module reads (no cross-module import beyond `{Models}`; state enums rendered through their cast, never imported). The KYC and sanctions lifecycles SHALL be presented as **independent** axes carried on the Customer, **distinct from** the status FSM (a Customer may show `kyc_status = verified` with `sanctions_status = pending`, or vice-versa, each rendered on its own). The console SHALL expose **no write affordance** for Account lifecycle or Profile lifecycle in this slice — those surfaces belong to the account and profile slices. The KYC write surface is the separate _Operator manages a Customer's KYC verification through the console_ requirement, the sanctions write surface the separate _Operator records a Customer's sanctions screening through the console_ requirement, and the Hold place/lift affordance the separate _Operator places and lifts Customer Holds through the console_ requirement; the KYC and sanctions **status rendered here remains read-only** (the badges display state through the cast — the write verbs invoke domain Actions, never an in-place field edit).

#### Scenario: The list and infolist render the three lifecycles, account status and profiles read-only

- **WHEN** an operator opens a Customer in the console
- **THEN** the surface shows the Customer's status, KYC status, sanctions status, Account status and Profiles, all read-only, with no field editable in place

#### Scenario: KYC and sanctions are rendered as independent axes

- **GIVEN** a Customer with `kyc_status = verified` and `sanctions_status = pending`
- **WHEN** an operator views the Customer
- **THEN** both lifecycles are displayed independently, neither collapsed into the `pending/active/suspended/closed` status FSM

#### Scenario: The console exposes no deferred lifecycle write action

- **WHEN** the Customer console (list, view and create surfaces) is inspected
- **THEN** it exposes no action to transition the Account or transition a Profile (the KYC and sanctions write surfaces are realized by their own requirements)

_Source: openspec/specs/party-registry/spec.md (Customer KYC Lifecycle; Customer Sanctions Screening Lifecycle; Account Status Lifecycle; Profile — Multi-Profile Membership) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (KYC + sanctions carried as separate lifecycles on Customer), §9.1 (KYC four-state lifecycle), §9.2 (sanctions four-state lifecycle), §9.4 (KYC and sanctions independent — both must clear independently), §4.7 (Account status parallels Customer) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-3 (KYC separate from Customer FSM), AC-K-FSM-4 (sanctions separate), AC-K-FSM-5 (KYC and sanctions independent), AC-K-FSM-9 (Account FSM) · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md (read-projection) · decisions/2026-06-21-operator-console-operand-enum-carveout.md (state enums rendered via cast, never imported)._
