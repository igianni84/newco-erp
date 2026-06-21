## ADDED Requirements

### Requirement: Operator creates a Club through the console

The console SHALL let an operator create a **Club** — a Producer-operated membership group — through a manual create surface that collects the Club's **display name**, an operating **Producer** (a required picker), and a **registration flow type** (required), plus an optional **fee** (a minor-unit amount + an ISO 4217 currency, assembled into the `Money` value object), a **generates-credit** flag (default true) and an **invite-only** flag (default false), invoking `CreateClub` and returning the created model (never `$model->save()`). The create surface SHALL construct the `ClubRegistrationFlowType` **operand enum** from the selected value to perform the write-through (the carve-out this change widens — ADR 2026-06-21). A created Club SHALL be born **`active`** and SHALL record exactly one `ClubCreated` domain event, tagged module `parties`, carrying the `actor_role: newco_ops` audit envelope. A Club's operating Producer is **required**: a create referencing a Producer that does not exist SHALL be rejected (`MissingClubProducer`) and surfaced on the form, with no Club created. The create surface SHALL expose **no** status field — `status` is born `active` by the Action, never set at creation.

#### Scenario: Valid input creates an active Club and records ClubCreated

- **WHEN** an operator submits a valid Club (display name, an existing operating Producer, a registration flow type) through the create surface
- **THEN** `CreateClub` is invoked and a Club exists in `active` with its attributes persisted
- **AND** exactly one `ClubCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `Club`

#### Scenario: The optional fee is assembled into Money when provided

- **WHEN** an operator creates a Club supplying a fee amount and currency
- **THEN** the Club's `fee` is the `Money` of that minor-unit amount and currency
- **WHEN** an operator creates a Club leaving the fee blank
- **THEN** the Club is created with no fee (null)

#### Scenario: Creating a Club requires an existing operating Producer

- **WHEN** an operator submits a Club whose selected operating Producer does not exist
- **THEN** the domain raises a `MissingClubProducer`, the console surfaces it on the form, and no Club and no `ClubCreated` event are created

#### Scenario: The create surface exposes the Club attributes and no lifecycle field

- **WHEN** the Club create surface is inspected
- **THEN** it exposes display name, operating Producer, registration flow type, fee, generates-credit and invite-only fields, and exposes no `status` field

_Source: openspec/specs/party-registry/spec.md (Club; Spine Creation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.3 (Club — Producer-operated; operating-Producer required + immutable; born `active`; fee/credit/flags/registration-flow config; "Club creation is a direct operator action") , §15.3 (`ClubCreated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-6 (Club FSM rooted at `active`), §4.4 AC-K-BR-Club-1 (operating Producer required), §5.3 AC-K-EVT-7 (`ClubCreated` on creation) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (Club config = operator action), §2.1 (operator-driven back-office write), §1.3 (`actor_role` envelope) · spec/03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md §3 AC-AP-INV-K (Club within the producer-onboarding operator surface) · app/Modules/Parties/Actions/CreateClub.php · app/Modules/Parties/Enums/ClubRegistrationFlowType.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md · decisions/2026-06-21-operator-console-operand-enum-carveout.md._

### Requirement: Operator advances a Club through its status lifecycle

The console SHALL surface the Club's status-FSM transitions — **sunset** (`SunsetClub`, `active → sunset`, recording `ClubSunset`) and **close** (`CloseClub`, `sunset → closed`, recording `ClubClosed`) — each invoking the corresponding domain Action and recording its verbatim event with `actor_role: newco_ops`. The console SHALL expose **no activate verb** — a Club is born `active`, so there is no `draft → active` transition to drive. Each transition SHALL present **no** "second actor required" affordance — Club lifecycle is a single-operator transition, not separation-of-duties governance (the spec mandates none for Club). The console SHALL expose **no** submit, reject, or reopen action — the Club FSM is linear (`active → sunset → closed`) with no review-governance step. Because `closed` is reachable only from `sunset`, a close attempted on an `active` Club SHALL be rejected by the domain (`IllegalClubTransition`) and surfaced; any out-of-state transition SHALL be surfaced as a notification without changing state.

#### Scenario: Sunset an active Club

- **WHEN** an operator sunsets an `active` Club
- **THEN** the Club becomes `sunset` and exactly one `ClubSunset` event is recorded with `actor_role: newco_ops` and `actor_id` equal to the operator's id

#### Scenario: Close a sunset Club

- **WHEN** an operator closes a `sunset` Club
- **THEN** the Club becomes `closed` and exactly one `ClubClosed` event is recorded with `actor_role: newco_ops`

#### Scenario: Close is rejected on an active Club — it must pass through sunset

- **WHEN** an operator attempts to close an `active` Club that has not been sunset
- **THEN** the domain raises an `IllegalClubTransition`, the console surfaces it as a notification, and the Club stays `active` with no event recorded

#### Scenario: The console exposes the linear verbs and none of the catalog governance verbs

- **WHEN** the Club view surface is inspected
- **THEN** it exposes sunset and close but no activate action, no submit/reject/reopen action, and neither transition presents a "second actor required" affordance

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator sunsets a Club not in `active`, or closes a Club not in `sunset`
- **THEN** the domain raises an `IllegalClubTransition`, the console surfaces it as a notification, and the Club's `status` and the domain-event log are unchanged

_Source: openspec/specs/party-registry/spec.md (Club Lifecycle; Supply-Side Lifecycle Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.3 (Club FSM `active → sunset → closed`; sunset blocks new memberships/offers, preserves Profiles; `closed` terminal), §10.2 (Club sunset is the per-Club leg of Producer offboarding; operator transitions `sunset → closed`), §15.3 (`ClubSunset`, `ClubClosed`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-6 (FSM path `active → sunset → closed` — `closed` reachable only from `sunset`), §5.3 AC-K-EVT-7 (`ClubSunset` on `active → sunset`; `ClubClosed` on `sunset → closed`) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (Club config operator surface), §5.2 (multi-actor discipline lists neither Club nor ProducerAgreement — single-operator) · app/Modules/Parties/Actions/SunsetClub.php, CloseClub.php · app/Modules/Parties/Exceptions/IllegalClubTransition.php · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: Operator creates a ProducerAgreement through the console

The console SHALL let an operator create a **ProducerAgreement** — the commercial agreement governing a Producer relationship — through a manual create surface that collects a required **Producer** (a picker), an optional **Club** narrowing (a picker; blank = a Producer-wide agreement), optional **term start** and **term end** dates, and an optional **settlement cadence** (a free-text string — the D19 Module-E seam), invoking `CreateProducerAgreement` and returning the created model (never `$model->save()`). All inputs are ids, dates, and a string — the surface constructs **no** operand enum and stays within the `{Models, Actions}` import surface. A created ProducerAgreement SHALL be born **`draft`** and SHALL record exactly one `ProducerAgreementCreated` event with `actor_role: newco_ops`. The Producer reference is **required**: a create referencing a Producer that does not exist SHALL be rejected (`MissingAgreementProducer`) and surfaced on the form. The single-active-per-scope invariant SHALL **not** be enforced at creation — draft agreements are created freely (it binds only at activation). The create surface SHALL expose **no** status field — `status` is born `draft`, never set at creation.

#### Scenario: Valid input creates a draft ProducerAgreement and records ProducerAgreementCreated

- **WHEN** an operator submits a valid ProducerAgreement (an existing Producer, optionally a Club and term/cadence) through the create surface
- **THEN** `CreateProducerAgreement` is invoked and a ProducerAgreement exists in `draft` with its attributes persisted
- **AND** exactly one `ProducerAgreementCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `ProducerAgreement`

#### Scenario: A Producer-wide agreement omits the Club narrowing

- **WHEN** an operator creates a ProducerAgreement leaving the Club picker blank
- **THEN** the agreement is created with a null `club_id` (Producer-wide scope)

#### Scenario: Creating a ProducerAgreement requires an existing Producer

- **WHEN** an operator submits a ProducerAgreement whose selected Producer does not exist
- **THEN** the domain raises a `MissingAgreementProducer`, the console surfaces it, and no agreement and no event are created

#### Scenario: Draft creation does not enforce the single-active-per-scope invariant

- **WHEN** an operator creates two draft ProducerAgreements in the same Producer scope
- **THEN** both are created in `draft`, each recording its own `ProducerAgreementCreated` (the single-active rule binds only at activation)

#### Scenario: The create surface exposes the agreement attributes and no lifecycle field

- **WHEN** the ProducerAgreement create surface is inspected
- **THEN** it exposes Producer, Club, term start, term end and settlement cadence fields, and exposes no `status` field

_Source: openspec/specs/party-registry/spec.md (ProducerAgreement; Spine Creation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.6 (ProducerAgreement — NewCo net-new entity; required Producer reference + optional Club narrowing, both shapes admitted; term dates, settlement cadence [D19], placeholders), §4.6.1 (born `draft`; terms may be incomplete), §15.5 (`ProducerAgreementCreated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §2 AC-K-J-11 (operator drafts against an active Producer → born `draft`), §3 AC-K-FSM-8 (FSM rooted at `draft`), §4.6 AC-K-BR-Agreement-1 (scope = Producer-wide OR per-Club) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (draft ProducerAgreement = operator action), §2.1/§3.1 (operator action by definition), §1.3 (`actor_role` envelope) · spec/03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md §3 AC-AP-INV-K (ProducerAgreement within the producer-onboarding operator surface) · app/Modules/Parties/Actions/CreateProducerAgreement.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md._

### Requirement: Operator advances a ProducerAgreement through its status lifecycle

The console SHALL surface the ProducerAgreement's status-FSM transitions — **activate** (`ActivateProducerAgreement`, `draft → active`, recording `ProducerAgreementActivated`) and **terminate** (`TerminateProducerAgreement`, `active → terminated`, recording `ProducerAgreementTerminated`) — each invoking the corresponding domain Action and recording its verbatim event with `actor_role: newco_ops`. Activation SHALL **supersede in-domain** any agreement already `active` in the same `(producer_id, club_id)` scope: the prior transitions `active → superseded`, recording a `ProducerAgreementSuperseded` causally linked to the activation — this is the Action's own behaviour, so the console SHALL expose **no standalone supersede verb** (`superseded` is never a direct operator transition). Termination SHALL **not** cascade to the Producer's state. Each verb SHALL present **no** "second actor required" affordance — ProducerAgreement lifecycle is single-operator (the spec mandates no SoD). The console SHALL expose **no** submit, reject, or reopen action. An out-of-state transition (`IllegalProducerAgreementTransition` — activate not from `draft`, terminate not from `active`) SHALL be surfaced as a notification without changing state.

#### Scenario: Activate a draft ProducerAgreement with no prior active in scope

- **WHEN** an operator activates a `draft` ProducerAgreement that has no prior active agreement in its `(producer_id, club_id)` scope
- **THEN** the agreement becomes `active` and exactly one `ProducerAgreementActivated` event is recorded with `actor_role: newco_ops` and `actor_id` equal to the operator's id, and no `ProducerAgreementSuperseded` is recorded

#### Scenario: Activation supersedes the prior active agreement in the same scope

- **GIVEN** an `active` ProducerAgreement and a second `draft` agreement in the same `(producer_id, club_id)` scope
- **WHEN** an operator activates the draft
- **THEN** the draft becomes `active`, the prior becomes `superseded`, and a `ProducerAgreementSuperseded` event is recorded carrying the `ProducerAgreementActivated` event's id as its causation

#### Scenario: Terminate an active ProducerAgreement without cascading to the Producer

- **GIVEN** an `active` ProducerAgreement whose Producer is `active`
- **WHEN** an operator terminates the agreement
- **THEN** the agreement becomes `terminated`, a `ProducerAgreementTerminated` event is recorded with `actor_role: newco_ops`, and the Producer's `status` is unchanged

#### Scenario: The console exposes activate and terminate but no standalone supersede or governance verbs

- **WHEN** the ProducerAgreement view surface is inspected
- **THEN** it exposes activate and terminate but no supersede action, no submit/reject/reopen action, and neither verb presents a "second actor required" affordance

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator activates an agreement not in `draft`, or terminates one not in `active`
- **THEN** the domain raises an `IllegalProducerAgreementTransition`, the console surfaces it as a notification, and the agreement's `status` and the domain-event log are unchanged

_Source: openspec/specs/party-registry/spec.md (ProducerAgreement Lifecycle; Supply-Side Lifecycle Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.6.1 (FSM `draft → active → superseded | terminated`; activation accepts terms; supersession = a replacement's activation transitions the prior to `superseded`, the two paired in audit; termination does not cascade to Producer-level state), §15.5 (`ProducerAgreementActivated`, `ProducerAgreementSuperseded`, `ProducerAgreementTerminated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §2 AC-K-J-11 (`draft → active`), §2 AC-K-J-12 (renewal via supersession — prior → `superseded`), §3 AC-K-FSM-8 (FSM `draft → active → superseded|terminated`), §4.6 AC-K-BR-Agreement-1 (single active per scope), AC-K-BR-Agreement-3 (renewal supersedes prior), §5.3 AC-K-EVT-9 (the four events; termination does NOT auto-cascade to Producer) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.K (activate ProducerAgreement = operator action), §5.2 (no SoD for ProducerAgreement) · app/Modules/Parties/Actions/ActivateProducerAgreement.php, TerminateProducerAgreement.php · app/Modules/Parties/Exceptions/IllegalProducerAgreementTransition.php · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._
