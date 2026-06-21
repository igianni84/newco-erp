## ADDED Requirements

### Requirement: Operator creates a Customer through the console

The console SHALL let an operator create a **Customer** — Module K's natural-person registry entry — through a manual create surface that collects the Customer's **email**, **name**, a **preferred currency** (an ISO 4217 code assembled into the platform `Currency` value object) and a **preferred locale** (a supported-locale value), plus an optional **phone** and an optional **date of birth**, invoking `CreateCustomer` and returning the created model (never `$model->save()`). All create operands are platform-level (`App\Platform\Money\Currency`, `App\Platform\I18n\SupportedLocale`) or scalar, so the surface constructs **no** `Parties\Enums` operand enum and stays within the `{Models, Actions}` import surface. A created Customer SHALL be born **`pending`** and SHALL **co-provision exactly one Account** (born `Personal` / `active`) in the same transaction; the Account is event-silent (there is no `AccountCreated` event — the console SHALL NOT invent one). The create SHALL record exactly one `CustomerCreated` domain event, tagged module `parties`, carrying the `actor_role: newco_ops` audit envelope. The create SHALL **not** create any Profile — a new Customer has its Account and zero Profiles (Profile creation is a separate, deferred surface). The email is **unique**: a create whose email already exists SHALL be rejected (`DuplicateCustomerEmail`) and surfaced on the form, with no Customer and no event created. The create surface SHALL expose **no** status field — `status` is born `pending` by the Action, never set at creation.

#### Scenario: Valid input creates a pending Customer with a co-provisioned Account and records CustomerCreated

- **WHEN** an operator submits a valid Customer (email, name, preferred currency, preferred locale) through the create surface
- **THEN** `CreateCustomer` is invoked and a Customer exists in `pending` with its attributes persisted and exactly one co-provisioned Account in `active`
- **AND** exactly one `CustomerCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `Customer`

#### Scenario: A duplicate email is rejected and surfaced

- **WHEN** an operator submits a Customer whose email already belongs to an existing Customer
- **THEN** the domain raises a `DuplicateCustomerEmail`, the console surfaces it on the email field, and no Customer, no Account and no `CustomerCreated` event are created

#### Scenario: The create surface exposes the identity fields, no lifecycle field, and creates no Profile

- **WHEN** the Customer create surface is inspected
- **THEN** it exposes email, name, preferred currency, preferred locale, phone and date-of-birth fields, exposes no `status` field, and a created Customer has zero Profiles

_Source: openspec/specs/party-registry/spec.md (Customer Identity; Account — Billing Container; Spine Creation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (Customer — natural-person registry; born `pending`), §4.7 (Account — transactional container; one Customer = one Account, auto-provisions; payment-provider reference created lazily, not at registration) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §2 AC-K-J-1 (direct registration; Account + Party auto-provision), §5 AC-K-EVT-1 (`CustomerCreated` on creation) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (onboard/edit a Customer — operator-driven), §2.1 (operator-driven back-office write), §1.3 (`actor_role` envelope) · app/Modules/Parties/Actions/CreateCustomer.php · app/Modules/Parties/Exceptions/DuplicateCustomerEmail.php · app/Modules/Parties/Events/CustomerCreated.php · app/Platform/Money/Currency.php · app/Platform/I18n/SupportedLocale.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md._

### Requirement: Operator advances a Customer through its status lifecycle

The console SHALL surface the Customer's status-FSM transitions — **activate** (`ActivateCustomer`, `pending → active`, recording `CustomerActivated`), **suspend** (`SuspendCustomer`, `active → suspended`, recording `CustomerSuspended`), **reactivate** (`ReactivateCustomer`, `suspended → active`, recording `CustomerReactivated`) and **close** (`CloseCustomer`, `active | suspended → closed`, recording `CustomerClosed`) — each invoking the corresponding domain Action and recording its verbatim event with `actor_role: newco_ops`. These direct verbs are the **BR-K-Customer-1 "manual" suspension/reactivation path**; the Hold-mediated path (`PlaceHold` / `LiftHold`, whose coupling also moves Customer status) is **out of scope** for this slice and belongs to the compliance console — the two paths coexist by design (ADR 2026-06-19). Each verb SHALL be **form-less** and SHALL present **no** "second actor required" affordance — Customer lifecycle is single-operator (the spec mandates no separation-of-duties for Customer). The console SHALL expose **no** submit, reject, or reopen action (the Customer FSM is not review-governed), and **no** Hold, KYC, sanctions, Account-lifecycle or Profile-lifecycle verb.

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

#### Scenario: The console exposes the status verbs and none of the governance, Hold, or compliance verbs

- **WHEN** the Customer view surface is inspected
- **THEN** it exposes activate, suspend, reactivate and close, no submit/reject/reopen action, no place-hold/lift-hold/KYC/sanctions/account/profile action, and no verb presents a "second actor required" affordance

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator suspends a Customer not in `active`, reactivates one not in `suspended`, or closes one in `pending`
- **THEN** the domain raises an `IllegalCustomerTransition`, the console surfaces it as a notification, and the Customer's `status` and the domain-event log are unchanged

_Source: openspec/specs/party-registry/spec.md (Customer Onboarding Activation; Customer Suspension and Closure; Demand-Side Status Events; Hold-Driven Status Coupling) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (status FSM `pending → active → suspended → closed`; T&C/privacy a hard gate on `pending → active`), §10.1 (Customer-level suspension; reactivation on Hold lift), BR-K-Customer-1 (suspension is explicit — manual or via Hold — not Profile-driven) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-1 (Customer FSM + the five status events), §4.2 AC-K-BR-Customer-1, §5 AC-K-EVT-1 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (onboard/edit + run suspension — operator surface), §5.2 (no SoD mandated for Customer) · app/Modules/Parties/Actions/{ActivateCustomer,SuspendCustomer,ReactivateCustomer,CloseCustomer}.php · app/Modules/Parties/Exceptions/IllegalCustomerTransition.php · app/Modules/Parties/Events/{CustomerActivated,CustomerSuspended,CustomerReactivated,CustomerClosed}.php · decisions/2026-06-19-hold-status-coupling.md · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: The Customer console surfaces the orthogonal compliance and membership context read-only

The console's list and infolist SHALL display the Customer's **status**, **KYC status**, **sanctions status**, the co-provisioned **Account's status**, and the Customer's **Profiles** (membership) — each rendered **read-only** via the model casts and within-module reads (no cross-module import beyond `{Models}`; state enums rendered through their cast, never imported). The KYC and sanctions lifecycles SHALL be presented as **independent** axes carried on the Customer, **distinct from** the status FSM (a Customer may show `kyc_status = verified` with `sanctions_status = pending`, or vice-versa, each rendered on its own). The console SHALL expose **no write affordance** for KYC, sanctions, Holds, Account lifecycle, or Profile lifecycle in this slice — those surfaces belong to the compliance / account / profile slices.

#### Scenario: The list and infolist render the three lifecycles, account status and profiles read-only

- **WHEN** an operator opens a Customer in the console
- **THEN** the surface shows the Customer's status, KYC status, sanctions status, Account status and Profiles, all read-only, with no field editable in place

#### Scenario: KYC and sanctions are rendered as independent axes

- **GIVEN** a Customer with `kyc_status = verified` and `sanctions_status = pending`
- **WHEN** an operator views the Customer
- **THEN** both lifecycles are displayed independently, neither collapsed into the `pending/active/suspended/closed` status FSM

#### Scenario: The console exposes no compliance or membership write action

- **WHEN** the Customer console (list, view and create surfaces) is inspected
- **THEN** it exposes no action to set KYC, record a sanctions screening, place or lift a Hold, transition the Account, or transition a Profile

_Source: openspec/specs/party-registry/spec.md (Customer KYC Lifecycle; Customer Sanctions Screening Lifecycle; Account Status Lifecycle; Profile — Multi-Profile Membership) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (KYC + sanctions carried as separate lifecycles on Customer), §9.1 (KYC four-state lifecycle), §9.2 (sanctions four-state lifecycle), §9.4 (KYC and sanctions independent — both must clear independently), §4.7 (Account status parallels Customer) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-3 (KYC separate from Customer FSM), AC-K-FSM-4 (sanctions separate), AC-K-FSM-5 (KYC and sanctions independent), AC-K-FSM-9 (Account FSM) · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md (read-projection) · decisions/2026-06-21-operator-console-operand-enum-carveout.md (state enums rendered via cast, never imported)._
