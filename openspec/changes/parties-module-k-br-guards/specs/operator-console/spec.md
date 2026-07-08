## MODIFIED Requirements

### Requirement: Operator creates a Club through the console

The console SHALL let an operator create a **Club** — a Producer-operated membership group — through a manual create surface that collects the Club's **display name**, an operating **Producer** (a required picker), and a **registration flow type** (required), plus an optional **fee** (a minor-unit amount + an ISO 4217 currency, assembled into the `Money` value object) and a **generates-credit** flag (default true), invoking `CreateClub` and returning the created model (never `$model->save()`). The registration-flow select SHALL offer **only the launch-selectable values** — `application_with_approval` (default), `invitation_only`, `link_onboarding` — and SHALL **not** offer `open_registration` (carried latent, not selectable at launch; per *Club Registration Flow and Onboarding Channel*). There SHALL be **no** separate **invite-only** field — the invite-only channel is `invitation_only` (the former `invite_only` boolean is removed/subsumed). The create surface SHALL construct the `ClubRegistrationFlowType` **operand enum** from the selected value to perform the write-through (the carve-out this change widens — ADR 2026-06-21). A created Club SHALL be born **`active`** and SHALL record exactly one `ClubCreated` domain event, tagged module `parties`, carrying the `actor_role: newco_ops` audit envelope. A Club's operating Producer is **required**: a create referencing a Producer that does not exist SHALL be rejected (`MissingClubProducer`) and surfaced on the form, with no Club created. The create surface SHALL expose **no** status field — `status` is born `active` by the Action, never set at creation.

#### Scenario: Valid input creates an active Club and records ClubCreated

- **WHEN** an operator submits a valid Club (display name, an existing operating Producer, a launch-selectable registration flow type) through the create surface
- **THEN** `CreateClub` is invoked and a Club exists in `active` with its attributes persisted
- **AND** exactly one `ClubCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `Club`

#### Scenario: The optional fee is assembled into Money when provided

- **WHEN** an operator creates a Club supplying a fee amount and currency
- **THEN** the Club's `fee` is the `Money` of that minor-unit amount and currency
- **WHEN** an operator creates a Club leaving the fee blank
- **THEN** the Club is created with no fee (null)

#### Scenario: The registration-flow select offers only the launch-selectable channels

- **WHEN** the Club create surface is inspected
- **THEN** the registration-flow select offers `application_with_approval`, `invitation_only` and `link_onboarding`, and does **not** offer `open_registration`; and there is no separate invite-only field

#### Scenario: Creating a Club requires an existing operating Producer

- **WHEN** an operator submits a Club whose selected operating Producer does not exist
- **THEN** the domain raises a `MissingClubProducer`, the console surfaces it on the form, and no Club and no `ClubCreated` event are created

#### Scenario: The create surface exposes the Club attributes and no lifecycle field

- **WHEN** the Club create surface is inspected
- **THEN** it exposes display name, operating Producer, registration flow type, fee and generates-credit fields, exposes no invite-only field, and exposes no `status` field

_Source: openspec/specs/party-registry/spec.md (Club; **Club Registration Flow and Onboarding Channel**; Spine Creation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.3, §15.3 (`ClubCreated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-6, §4.4 AC-K-BR-Club-1, **§4.4 AC-K-BR-Club-6 (registration_flow entry-channel-only; `open` latent; `invite_only` subsumed)**, §5.3 AC-K-EVT-7 · canon **MVP-DEC-022** (LIVE `cmless/main` @ `360df0b`) via this change's mini-ADR `decisions/2026-07-07-adopt-mvp-dec-022-club-membership-governance.md` · app/Modules/Parties/Actions/CreateClub.php · app/Modules/Parties/Enums/ClubRegistrationFlowType.php · decisions/2026-06-21-operator-console-operand-enum-carveout.md · MODIFIES the *Operator creates a Club through the console* requirement (openspec/specs/operator-console/spec.md — which collected an **invite-only flag** and offered every `ClubRegistrationFlowType` value)._

### Requirement: Operator creates a ProducerAgreement through the console

The console SHALL let an operator create a **ProducerAgreement** — the commercial agreement governing a Producer relationship — through a manual create surface that collects a required **Producer** (a picker), an optional **Club** narrowing (a picker; blank = a Producer-wide agreement), optional **term start** and **term end** dates, and an optional **settlement cadence** **selected from the closed set** `{quarterly, monthly, semi-annual}` (default `quarterly`), invoking `CreateProducerAgreement` and returning the created model (never `$model->save()`). The create surface SHALL construct the `SettlementCadence` **operand enum** from the selected value (widening the operand-enum carve-out, ADR 2026-06-21); an out-of-set cadence SHALL be rejected server-side (domain + DB CHECK). The **Club picker SHALL offer only `active` Clubs** of the selected Producer — a `sunset`/`closed` Club is not selectable, and a create scoped to a non-`active` Club SHALL be rejected (`ProducerAgreementClubNotActive`) and surfaced (per BR-K-Agreement-4). A created ProducerAgreement SHALL be born **`draft`** and SHALL record exactly one `ProducerAgreementCreated` event with `actor_role: newco_ops`. The Producer reference is **required**: a create referencing a Producer that does not exist SHALL be rejected (`MissingAgreementProducer`) and surfaced. The single-active-per-scope invariant (and the cross-shape mutual exclusion) SHALL **not** be enforced at creation — draft agreements are created freely (both bind only at activation). The create surface SHALL expose **no** status field.

#### Scenario: Valid input creates a draft ProducerAgreement and records ProducerAgreementCreated

- **WHEN** an operator submits a valid ProducerAgreement (an existing Producer, optionally an `active` Club, term dates and a closed-set cadence) through the create surface
- **THEN** `CreateProducerAgreement` is invoked and a ProducerAgreement exists in `draft` with its attributes persisted
- **AND** exactly one `ProducerAgreementCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `ProducerAgreement`

#### Scenario: A Producer-wide agreement omits the Club narrowing

- **WHEN** an operator creates a ProducerAgreement leaving the Club picker blank
- **THEN** the agreement is created with a null `club_id` (Producer-wide scope)

#### Scenario: The Club picker offers only active Clubs, and settlement cadence is a closed set

- **WHEN** the ProducerAgreement create surface is inspected
- **THEN** the Club picker offers only the selected Producer's `active` Clubs (no `sunset`/`closed`), and the settlement-cadence field is a select over `{quarterly, monthly, semi-annual}` — not a free-text input

#### Scenario: Creating a ProducerAgreement requires an existing Producer

- **WHEN** an operator submits a ProducerAgreement whose selected Producer does not exist
- **THEN** the domain raises a `MissingAgreementProducer`, the console surfaces it, and no agreement and no event are created

#### Scenario: Draft creation does not enforce the single-active-per-scope invariant

- **WHEN** an operator creates two draft ProducerAgreements in the same Producer scope
- **THEN** both are created in `draft`, each recording its own `ProducerAgreementCreated` (the single-active and cross-shape rules bind only at activation)

_Source: openspec/specs/party-registry/spec.md (ProducerAgreement; Spine Creation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.6, §4.6.1, §15.5 (`ProducerAgreementCreated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §2 AC-K-J-11, §3 AC-K-FSM-8, §4.6 **AC-K-BR-Agreement-2 (settlement-cadence override) / AC-K-BR-Agreement-4 (per-Club scope requires an `active` Club)** · canon **MVP-DEC-010** (settlement `{quarterly, monthly, semi-annual}`, server-enforced) + **MVP-DEC-009** (per-Club scope requires active Club), LIVE `cmless/main` @ `360df0b`, via this change's mini-ADRs `decisions/2026-07-07-adopt-mvp-dec-010-settlement-cadence-closed-set.md` + `decisions/2026-07-07-adopt-mvp-dec-009-agreement-club-active-scope.md` · app/Modules/Parties/Actions/CreateProducerAgreement.php · decisions/2026-06-21-operator-console-operand-enum-carveout.md · MODIFIES the *Operator creates a ProducerAgreement through the console* requirement (openspec/specs/operator-console/spec.md — which took settlement cadence as a **free-text string** with "no operand enum" and admitted **any** Club narrowing)._

### Requirement: Operator creates a Customer through the console

The console SHALL let an operator create a **Customer** — Module K's natural-person registry entry — through a manual create surface that collects the Customer's **email**, **name**, a **preferred currency** (an ISO 4217 code assembled into the platform `Currency` value object) and a **preferred locale** (a supported-locale value), plus an optional **phone** and a **date of birth**, invoking `CreateCustomer` and returning the created model (never `$model->save()`). A created Customer SHALL be born **`pending`** and SHALL **co-provision exactly one Account** (born `Personal` / `active`) in the same transaction; the Account is event-silent. The create SHALL record exactly one `CustomerCreated` domain event, tagged module `parties`, carrying the `actor_role: newco_ops` audit envelope. The **age gate** SHALL apply: a `date_of_birth` implying an age **below the configured platform minimum** (default 18) — or a **missing** `date_of_birth` (attestation is mandatory, per *Registration Age Gate*, so the field is **effectively required**) — SHALL be rejected (`BelowMinimumRegistrationAge`) and surfaced on the **date-of-birth field**, with **no Customer, Account or event created**. The email is **unique**: a create whose email already exists SHALL be rejected (`DuplicateCustomerEmail`) and surfaced. The create SHALL **not** create any Profile. The create surface SHALL expose **no** status field.

#### Scenario: Valid input creates a pending Customer with a co-provisioned Account and records CustomerCreated

- **WHEN** an operator submits a valid Customer (email, name, preferred currency, preferred locale, and a `date_of_birth` at or above the minimum) through the create surface
- **THEN** `CreateCustomer` is invoked and a Customer exists in `pending` with its attributes persisted and exactly one co-provisioned Account in `active`
- **AND** exactly one `CustomerCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `Customer`

#### Scenario: An under-age registration is rejected and surfaced

- **WHEN** an operator submits a Customer whose `date_of_birth` implies an age below the configured minimum
- **THEN** the domain raises a `BelowMinimumRegistrationAge`, the console surfaces it on the date-of-birth field, and no Customer, no Account and no `CustomerCreated` event are created

#### Scenario: A missing date of birth is rejected and surfaced

- **WHEN** an operator submits a Customer with no date of birth
- **THEN** the domain raises a `BelowMinimumRegistrationAge` (attestation is mandatory — the field is effectively required), the console surfaces it on the date-of-birth field, and no Customer, no Account and no `CustomerCreated` event are created

#### Scenario: A duplicate email is rejected and surfaced

- **WHEN** an operator submits a Customer whose email already belongs to an existing Customer
- **THEN** the domain raises a `DuplicateCustomerEmail`, the console surfaces it on the email field, and no Customer, no Account and no `CustomerCreated` event are created

#### Scenario: The create surface exposes the identity fields, no lifecycle field, and creates no Profile

- **WHEN** the Customer create surface is inspected
- **THEN** it exposes email, name, preferred currency, preferred locale, phone and date-of-birth fields, exposes no `status` field, and a created Customer has zero Profiles

_Source: openspec/specs/party-registry/spec.md (Customer Identity; **Registration Age Gate**; Account — Billing Container) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1, §4.7, §7.1 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §2 AC-K-J-1, **§4.1 AC-K-BR-Identity-6 (18+ age-gate at registration)**, §5 AC-K-EVT-1 · canon **MVP-DEC-022** (LIVE `cmless/main` @ `360df0b`) + **BMD §2.8**, via this change's mini-ADR `decisions/2026-07-07-adopt-mvp-dec-022-club-membership-governance.md` · app/Modules/Parties/Actions/CreateCustomer.php · app/Modules/Parties/Exceptions/DuplicateCustomerEmail.php · MODIFIES the *Operator creates a Customer through the console* requirement (openspec/specs/operator-console/spec.md — which collected an **optional** `date_of_birth` with **no age gate**)._

### Requirement: Operator creates a Profile through the console

The console SHALL let an operator create a **Profile** — Module K's per-Club membership entry — through a manual create surface that collects a target **Customer** and a target **Club** (both selected from within-module reads, no cross-module import beyond `{Models}`), invoking `CreateProfile($customerId, $clubId)` and returning the created model (never `$model->save()`). The surface SHALL construct **no** `Parties\Enums` operand enum and stay within the `{Models, Actions}` import surface. A created Profile SHALL be born **`Applied`** and SHALL record exactly one `ProfileCreated` domain event, tagged module `parties`, carrying the `actor_role: newco_ops` audit envelope. The create surface SHALL expose **no** `state` field and SHALL **not** collect a `tier`, `role`, or inviter. A duplicate non-terminal Profile for the same Customer–Club pair SHALL be rejected (`DuplicateProfileForClub`) and surfaced. A create targeting a Club that is **not `active`** (`sunset` or `closed`) SHALL be rejected (`ClubNotAcceptingMemberships`) and surfaced on the form, with no Profile and no event created (per *Profile — Multi-Profile Membership*); the Club picker SHOULD present `active` Clubs.

#### Scenario: Valid input creates an applied Profile and records ProfileCreated

- **WHEN** an operator submits a valid Customer + an `active` Club through the create surface
- **THEN** `CreateProfile` is invoked and a Profile exists in `Applied` for that Customer and Club, with `auto_renew` inherited from the Club default
- **AND** exactly one `ProfileCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `Profile`

#### Scenario: A duplicate non-terminal Profile is rejected and surfaced

- **GIVEN** a Customer with a non-terminal Profile in a Club
- **WHEN** an operator submits a second Profile for the same Customer–Club pair
- **THEN** the domain raises a `DuplicateProfileForClub`, the console surfaces it on the form, and no second Profile and no event are created

#### Scenario: A sunset or closed Club is rejected and surfaced

- **WHEN** an operator submits a Profile targeting a Club in `sunset` or `closed`
- **THEN** the domain raises a `ClubNotAcceptingMemberships`, the console surfaces it on the form, and no Profile and no `ProfileCreated` event are created

#### Scenario: The create surface exposes the membership operands and no lifecycle field

- **WHEN** the Profile create surface is inspected
- **THEN** it exposes a Customer select and a Club select, exposes no `state`/`tier`/`role` field, and a created Profile is born `Applied`

_Source: openspec/specs/party-registry/spec.md (Profile — Multi-Profile Membership; Profile Auto-Renewal Preference; Demand-Side Activation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.2.1, §4.3 (**sunset blocks new memberships**) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-2, **AC-K-FSM-6 / §4.4 AC-K-BR-Club-3 (new-membership creation rejected when Club is `sunset`)** · app/Modules/Parties/Actions/CreateProfile.php · app/Modules/Parties/Exceptions/DuplicateProfileForClub.php · MODIFIES the *Operator creates a Profile through the console* requirement (openspec/specs/operator-console/spec.md — which rejected only `DuplicateProfileForClub` and applied **no Club-status gate**)._
