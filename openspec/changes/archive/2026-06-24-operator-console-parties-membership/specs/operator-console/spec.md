## ADDED Requirements

### Requirement: Operator creates a Profile through the console

The console SHALL let an operator create a **Profile** — Module K's per-Club membership entry — through a manual create surface that collects a target **Customer** and a target **Club** (both selected from within-module reads, no cross-module import beyond `{Models}`), invoking `CreateProfile($customerId, $clubId)` and returning the created model (never `$model->save()`). The surface SHALL construct **no** `Parties\Enums` operand enum and stay within the `{Models, Actions}` import surface. A created Profile SHALL be born **`Applied`** and SHALL record exactly one `ProfileCreated` domain event, tagged module `parties`, carrying the `actor_role: newco_ops` audit envelope. The create surface SHALL expose **no** `state` field — `state` is born `Applied` by the Action, never set at creation — and SHALL **not** collect a `tier` (single-tier at launch, DEC-062), `role`, or inviter (the invitation surface is deferred). A duplicate non-terminal Profile for the same Customer–Club pair SHALL be rejected (`DuplicateProfileForClub`) and surfaced on the form, with no Profile and no event created.

#### Scenario: Valid input creates an applied Profile and records ProfileCreated

- **WHEN** an operator submits a valid Customer + Club through the create surface
- **THEN** `CreateProfile` is invoked and a Profile exists in `Applied` for that Customer and Club
- **AND** exactly one `ProfileCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `Profile`

#### Scenario: A duplicate non-terminal Profile is rejected and surfaced

- **GIVEN** a Customer with a non-terminal Profile in a Club
- **WHEN** an operator submits a second Profile for the same Customer–Club pair
- **THEN** the domain raises a `DuplicateProfileForClub`, the console surfaces it on the form, and no second Profile and no event are created

#### Scenario: The create surface exposes the membership operands and no lifecycle field

- **WHEN** the Profile create surface is inspected
- **THEN** it exposes a Customer select and a Club select, exposes no `state`/`tier`/`role` field, and a created Profile is born `Applied`

_Source: openspec/specs/party-registry/spec.md (Profile — Multi-Profile Membership; Demand-Side Activation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.2.1 (Profile lifecycle — `Applied` on application), §3 + §4.2 (single-tier at launch, DEC-062) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-2 (Profile FSM + `ProfileCreated`), §4 AC-K-BR-Profile-1 (same FSM shape every Club) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (onboard — operator-driven; producer-initiated invitation operator-driven) · app/Modules/Parties/Actions/CreateProfile.php · app/Modules/Parties/Exceptions/DuplicateProfileForClub.php · app/Modules/Parties/Events/ProfileCreated.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md._

### Requirement: Operator approves or declines a Profile membership through the console

The console SHALL surface the Profile membership-approval verbs — **approve** (`ApproveProfile`, `Applied → Approved`) and **decline** (`DeclineProfile`, `Applied → Rejected`) — on the Profile view, each invoking the corresponding domain Action and never an Eloquent write. These are the **one retained producer write** at launch (membership approve/decline, the L-PP / K-Q4 producer write); exercised through the operator console they carry `actor_role: newco_ops` (DEC-083 / DEC-115 admin-parity). Each verb SHALL be **form-less** and **visibility-gated to the `Applied` from-state**, so the from-state rejection (`IllegalProfileTransition`) is unreachable through the surface. **Approve** SHALL record **no** Profile-named event; on the Customer's **first-ever** approval into any Club it SHALL additionally record exactly one `OriginatingClubLocked` event and set the Customer's `originating_club_id` (the one-shot Originating-Club lock, idempotent thereafter). **Decline** SHALL record **no** domain event (the `state = rejected` write is the audit record). The console SHALL author **no** `Applied → WaitingList` transition — that edge has no writer at launch and is deferred to a future change.

#### Scenario: Approve an applied Profile and lock the Originating Club on first approval

- **GIVEN** a Customer whose `originating_club_id` is unset, with an `Applied` Profile
- **WHEN** an operator approves the Profile
- **THEN** the Profile becomes `Approved`, the Customer's `originating_club_id` is set to that Profile's Club, and exactly one `OriginatingClubLocked` event is recorded with `actor_role: newco_ops`
- **WHEN** the operator later approves a second Club's `Applied` Profile for the same Customer
- **THEN** that Profile becomes `Approved` and **no** further `OriginatingClubLocked` event is recorded (the lock is one-shot)

#### Scenario: Decline an applied Profile is terminal and event-silent

- **GIVEN** an `Applied` Profile
- **WHEN** an operator declines the Profile
- **THEN** the Profile becomes `Rejected` and no domain event is recorded (the `state = rejected` write is the audit record)

#### Scenario: Approve and decline are offered only from Applied

- **WHEN** a Profile not in `Applied` is viewed
- **THEN** neither approve nor decline is offered, and an out-of-state approve/decline driven against the domain is rejected (`IllegalProfileTransition`) with state and the event log unchanged

_Source: openspec/specs/party-registry/spec.md (Profile Membership Approval; Demand-Side Activation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.2.1 (`Applied → Approved/Rejected`; `OriginatingClubLocked` on first-ever approval), §3.1 (the one retained producer write — membership approve/decline, L-PP / K-Q4; operator-exercisable via `newco_ops`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-2 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §2 + §3.K (the one producer write; operator-driven parity DEC-083) · app/Modules/Parties/Actions/{ApproveProfile,DeclineProfile}.php · app/Modules/Parties/Events/OriginatingClubLocked.php · app/Modules/Parties/Exceptions/IllegalProfileTransition.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md._

### Requirement: Operator advances a Profile through its lifecycle

The console SHALL surface the Profile's post-approval lifecycle transitions on the Profile view, each invoking its domain Action and never an Eloquent write, each **form-less** and **visibility-gated to its from-state**: **activate** (`ActivateProfile`, `Approved → Active`, recording `ProfileActivated`), **suspend** (`SuspendProfile`, `Active → Suspended`, recording `ProfileSuspended`), **reactivate** (`ReactivateProfile`, `Suspended → Active`, recording `ProfileReactivated`), **lapse** (`LapseProfile`, `Active → Lapsed`, recording `ProfileExpired`), **renew** (`RenewProfile`, `Lapsed → Active` within the 30-day grace, recording `ProfileRenewed`), **cancel** (`CancelProfile`, `Active | Lapsed → Cancelled`, **audit-only — no event**) and **deactivate** (`DeactivateProfile`, `Active → Inactive`, recording `ProfileInactive`). Each recorded event carries the `actor_role: newco_ops` audit envelope.

**Activation SHALL ship uncapped** — the Hero-Package capacity invariant (`Active` Profiles ≤ the Hero-Package Allocation `qty`) is a **deferred Module-A seam**; the console drives `ActivateProfile` without a capacity check and surfaces no cap. **Suspension SHALL be state-preserving** — it changes only `state`; vouchers, orders, allocation reservations and Club Credit are untouched (the Club-Credit freeze is enforced at the redemption site, a club-credit seam). Each from-state-gated rejection (`IllegalProfileTransition`) SHALL be unreachable through the surface (the verb is hidden off its from-state) and SHALL be rejected by the domain when driven directly, leaving state and the event log unchanged. **`renew` is the sole exception**: visible from `Lapsed`, a renew attempted past the 30-day grace SHALL be rejected by the domain and surfaced as a danger notification without state change (the grace sub-gate is domain-internal, not expressible as a visibility predicate). `Cancelled` and `Inactive` are **terminal soft-delete** states — the row is never hard-deleted and stays queryable.

#### Scenario: Activate an approved Profile, uncapped

- **GIVEN** an `Approved` Profile
- **WHEN** an operator activates it
- **THEN** the Profile becomes `Active` and exactly one `ProfileActivated` event is recorded with `actor_role: newco_ops`, with no capacity check applied (the Hero-Package cap is a deferred Module-A seam)

#### Scenario: Suspend then restore an active Profile, state-preserving

- **GIVEN** an `Active` Profile with an active voucher and an active Club Credit
- **WHEN** an operator suspends it
- **THEN** the Profile becomes `Suspended`, exactly one `ProfileSuspended` event is recorded, and the voucher and Club Credit are unchanged (suspension changes only `state`)
- **WHEN** the operator reactivates it
- **THEN** the Profile becomes `Active` and exactly one `ProfileReactivated` event is recorded

#### Scenario: Lapse, then renew within the 30-day grace

- **GIVEN** an `Active` Profile
- **WHEN** an operator lapses it
- **THEN** the Profile becomes `Lapsed` and exactly one `ProfileExpired` event is recorded
- **WHEN** the operator renews it within 30 days of lapse
- **THEN** the Profile becomes `Active` and exactly one `ProfileRenewed` event is recorded

#### Scenario: A renew past the 30-day grace is rejected and surfaced

- **GIVEN** a Profile that lapsed more than 30 days ago
- **WHEN** an operator drives `renew` (visible from `Lapsed`)
- **THEN** the domain raises an `IllegalProfileTransition`, the console surfaces a danger notification, and the Profile stays `Lapsed` with no event recorded

#### Scenario: Cancel is terminal, audit-only, and the row is preserved

- **GIVEN** an `Active` (or `Lapsed`) Profile
- **WHEN** an operator cancels it
- **THEN** the Profile becomes `Cancelled`, no domain event is recorded (audit-only), and the row remains queryable (soft-delete, never hard-deleted)

#### Scenario: Deactivate records ProfileInactive

- **GIVEN** an `Active` Profile
- **WHEN** an operator deactivates it
- **THEN** the Profile becomes `Inactive` and exactly one `ProfileInactive` event is recorded

#### Scenario: Each lifecycle verb is offered only from its from-state

- **WHEN** a Profile is viewed in any given state
- **THEN** only the verbs valid from that state are offered (activate from `Approved`; suspend/lapse/deactivate from `Active`; reactivate from `Suspended`; renew from `Lapsed`; cancel from `Active` or `Lapsed`), and an out-of-state transition driven against the domain is rejected (`IllegalProfileTransition`) with state and the event log unchanged

_Source: openspec/specs/party-registry/spec.md (Profile Activation; Profile Suspension and Restoration; Profile Lapse and Grace Renewal; Profile Cancellation and Deactivation; Demand-Side Status Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.2.1 (full Profile lifecycle), §13 (Hero-Package capacity invariant — Module-A-owned, enforced at transition into `Active`), §10.1 (suspension state-preservation) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-2 (FSM + events), AC-K-FSM-2a (suspension state-preservation), AC-K-FSM-12 (30-day lapse grace, DEC-034), AC-K-FSM-13 (terminal soft-delete) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (run suspension/offboarding — operator surface) · app/Modules/Parties/Actions/{ActivateProfile,SuspendProfile,ReactivateProfile,LapseProfile,RenewProfile,CancelProfile,DeactivateProfile}.php · app/Modules/Parties/Events/{ProfileActivated,ProfileSuspended,ProfileReactivated,ProfileExpired,ProfileRenewed,ProfileInactive}.php · app/Modules/Parties/Exceptions/IllegalProfileTransition.php · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: Operator advances a Customer's Account through its status lifecycle

The console SHALL surface the co-provisioned **Account**'s status transitions on the Customer view — **suspend** (`SuspendAccount`, `active → suspended`), **reactivate** (`ReactivateAccount`, `suspended → active`) and **close** (`CloseAccount`, `active | suspended → closed`) — each invoking its domain Action via the Customer's 1:1 Account (`account->id`) and never an Eloquent write. The Account has **no** activation verb (it is born `active` when co-provisioned with the Customer; there is no `ActivateAccount`). Each verb SHALL be **form-less** and **visibility-gated to its from-state**, so the from-state rejection (`IllegalAccountTransition`) is unreachable through the surface. All three transitions are **audit-only** — no Account domain event exists; the operator action carries the `actor_role: newco_ops` audit envelope. Account status is **orthogonal** to the Customer status FSM and to Profile state: an Account transition cascades to neither.

#### Scenario: Suspend then reactivate an Account, event-silently

- **GIVEN** a Customer whose co-provisioned Account is `active`
- **WHEN** an operator suspends the Account
- **THEN** the Account becomes `suspended` with no domain event recorded (audit-only), and neither the Customer status nor any Profile state changes
- **WHEN** the operator reactivates the Account
- **THEN** the Account becomes `active` with no domain event recorded

#### Scenario: Close an Account is terminal

- **GIVEN** an `active` (or `suspended`) Account
- **WHEN** an operator closes it
- **THEN** the Account becomes `closed` with no domain event recorded

#### Scenario: The Account has no activation verb and rejects illegal transitions

- **WHEN** the Customer view is inspected
- **THEN** it offers suspend/reactivate/close gated to the Account's from-state and offers no Account activation verb; an out-of-state Account transition driven against the domain is rejected (`IllegalAccountTransition`) with status and the event log unchanged

_Source: openspec/specs/party-registry/spec.md (Account Status Lifecycle; Demand-Side Status Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.7 (Account — transactional container; status parallels Customer; born `active`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-9 (Account FSM `active → suspended → closed`; Account Holds drive suspend; lift drives reactivate) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (operator-driven back-office write) · app/Modules/Parties/Actions/{SuspendAccount,ReactivateAccount,CloseAccount}.php · app/Modules/Parties/Exceptions/IllegalAccountTransition.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md._

## MODIFIED Requirements

### Requirement: Operator advances a Customer through its status lifecycle

The console SHALL surface the Customer's status-FSM transitions — **activate** (`ActivateCustomer`, `pending → active`, recording `CustomerActivated`), **suspend** (`SuspendCustomer`, `active → suspended`, recording `CustomerSuspended`), **reactivate** (`ReactivateCustomer`, `suspended → active`, recording `CustomerReactivated`) and **close** (`CloseCustomer`, `active | suspended → closed`, recording `CustomerClosed`) — each invoking the corresponding domain Action and recording its verbatim event with `actor_role: newco_ops`. These direct verbs are the **BR-K-Customer-1 "manual" suspension/reactivation path**; the Hold-mediated path (`PlaceHold` / `LiftHold`, whose coupling also moves Customer status) is realized by the _Operator places and lifts Customer Holds through the console_ requirement — the two paths coexist by design (ADR 2026-06-19). Each verb SHALL be **form-less** and SHALL present **no** "second actor required" affordance — Customer lifecycle is single-operator (the spec mandates no separation-of-duties for Customer). The console SHALL expose **no** submit, reject, or reopen action (the Customer FSM is not review-governed). The Customer's co-provisioned **Account** status verbs (suspend/reactivate/close) ARE surfaced on this same view by the separate _Operator advances a Customer's Account through its status lifecycle_ requirement; **Profile**-lifecycle verbs are **not** on this view — they live on the standalone `ProfileResource` (_Operator advances a Profile through its lifecycle_, with Profile create and membership approval their own requirements). The KYC verbs are the separate _Operator manages a Customer's KYC verification through the console_ requirement, sanctions screening the _Operator records a Customer's sanctions screening through the console_ requirement, and Hold place/lift the _Operator places and lifts Customer Holds through the console_ requirement, none part of these status verbs.

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
- **THEN** it exposes activate, suspend, reactivate and close for the Customer **plus** the Account suspend/reactivate/close verbs (the KYC, sanctions and Hold place/lift surfaces are realized by their own requirements), no submit/reject/reopen action, **no** Profile-lifecycle action (Profile verbs live on the `ProfileResource`), and no verb presents a "second actor required" affordance

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator suspends a Customer not in `active`, reactivates one not in `suspended`, or closes one in `pending`
- **THEN** the domain raises an `IllegalCustomerTransition`, the console surfaces it as a notification, and the Customer's `status` and the domain-event log are unchanged

_Source: openspec/specs/party-registry/spec.md (Customer Onboarding Activation; Customer Suspension and Closure; Demand-Side Status Events; Hold-Driven Status Coupling) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (status FSM `pending → active → suspended → closed`; T&C/privacy a hard gate on `pending → active`), §10.1 (Customer-level suspension; reactivation on Hold lift), BR-K-Customer-1 (suspension is explicit — manual or via Hold — not Profile-driven) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-1 (Customer FSM + the five status events), §4.2 AC-K-BR-Customer-1, §5 AC-K-EVT-1 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (onboard/edit + run suspension — operator surface), §5.2 (no SoD mandated for Customer) · app/Modules/Parties/Actions/{ActivateCustomer,SuspendCustomer,ReactivateCustomer,CloseCustomer}.php · app/Modules/Parties/Exceptions/IllegalCustomerTransition.php · app/Modules/Parties/Events/{CustomerActivated,CustomerSuspended,CustomerReactivated,CustomerClosed}.php · decisions/2026-06-19-hold-status-coupling.md · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: The Customer console surfaces the orthogonal compliance and membership context read-only

The console's list and infolist SHALL display the Customer's **status**, **KYC status**, **sanctions status**, the co-provisioned **Account's status**, and the Customer's **Profiles** (membership) — each rendered **read-only** via the model casts and within-module reads (no cross-module import beyond `{Models}`; state enums rendered through their cast, never imported). The KYC and sanctions lifecycles SHALL be presented as **independent** axes carried on the Customer, **distinct from** the status FSM (a Customer may show `kyc_status = verified` with `sanctions_status = pending`, or vice-versa, each rendered on its own). The Account lifecycle and Profile membership **write** surfaces are realized by their own requirements — Account suspend/reactivate/close by _Operator advances a Customer's Account through its status lifecycle_ (on this Customer view), and Profile create, approval and lifecycle by the standalone `ProfileResource` (_Operator creates a Profile through the console_, _Operator approves or declines a Profile membership through the console_, _Operator advances a Profile through its lifecycle_); the **Account status and Profiles rendered in this list/infolist remain read-only**. The KYC write surface is the separate _Operator manages a Customer's KYC verification through the console_ requirement, the sanctions write surface the separate _Operator records a Customer's sanctions screening through the console_ requirement, and the Hold place/lift affordance the separate _Operator places and lifts Customer Holds through the console_ requirement; the KYC and sanctions **status rendered here remains read-only** (the badges display state through the cast — the write verbs invoke domain Actions, never an in-place field edit).

#### Scenario: The list and infolist render the three lifecycles, account status and profiles read-only

- **WHEN** an operator opens a Customer in the console
- **THEN** the surface shows the Customer's status, KYC status, sanctions status, Account status and Profiles, all read-only, with no field editable in place

#### Scenario: KYC and sanctions are rendered as independent axes

- **GIVEN** a Customer with `kyc_status = verified` and `sanctions_status = pending`
- **WHEN** an operator views the Customer
- **THEN** both lifecycles are displayed independently, neither collapsed into the `pending/active/suspended/closed` status FSM

#### Scenario: Account status and Profiles render read-only, their write surfaces realized elsewhere

- **WHEN** the Customer console list and infolist are inspected
- **THEN** the Account status and the Customer's Profiles are shown read-only through the cast (no in-place field edit), with the Account write verbs realized by their own requirement on this view and Profile writes living on the `ProfileResource`

_Source: openspec/specs/party-registry/spec.md (Customer KYC Lifecycle; Customer Sanctions Screening Lifecycle; Account Status Lifecycle; Profile — Multi-Profile Membership) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (KYC + sanctions carried as separate lifecycles on Customer), §9.1 (KYC four-state lifecycle), §9.2 (sanctions four-state lifecycle), §9.4 (KYC and sanctions independent — both must clear independently), §4.7 (Account status parallels Customer) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-3 (KYC separate from Customer FSM), AC-K-FSM-4 (sanctions separate), AC-K-FSM-5 (KYC and sanctions independent), AC-K-FSM-9 (Account FSM) · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md (read-projection) · decisions/2026-06-21-operator-console-operand-enum-carveout.md (state enums rendered via cast, never imported)._

