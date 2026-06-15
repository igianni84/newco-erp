# party-registry Specification

## Purpose
TBD - created by archiving change parties-core. Update Purpose after archive.
## Requirements
### Requirement: Party-Type Marker on Subtype

Every Party-subtype entity SHALL carry a **party-type marker** whose domain is exactly `customer`, `supplier`, `third_party_owner`, set at creation and **immutable thereafter** — a Customer SHALL NOT become a Supplier or vice versa. At launch this marker SHALL be represented as an immutable attribute on each distinct subtype entity (Customer carries `customer`, Supplier carries `supplier`), NOT a shared Party-registry row; the unified Party Registry table, the dormant `third_party_owner` subtype entity, and any cross-marker overlap SHALL be deferred and SHALL NOT be modelled in this change. The **Producer** entity SHALL NOT be a Party subtype and SHALL NOT carry a party-type marker.

#### Scenario: The marker is set at creation and is immutable

- **WHEN** a Customer is created
- **THEN** it carries the party-type marker `customer`, and there is no operation in this change that changes a created entity's party-type marker
- **WHEN** a Supplier is created
- **THEN** it carries the party-type marker `supplier`

#### Scenario: A Customer and a Supplier are distinct typed entities

- **WHEN** the Parties code surface is inspected
- **THEN** Customer and Supplier are distinct `parties_*` entities each fixing its own marker, so a Customer can never become a Supplier; no shared `parties_parties` registry table and no `third_party_owner` entity exist in this change

#### Scenario: Producer is not a Party

- **WHEN** the Producer entity is inspected
- **THEN** it carries no party-type marker and is a standalone registry, not a Party subtype

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.4 (Producer is NOT a Party subtype — standalone registry) · § 4.5 (Supplier is a Party Registry subtype; marker on the entity) · § 14.1 BR-K-Identity-5 (the party-type marker is immutable once set) · spec/04-decisions/decisions.md DEC-067 (Party Registry with party_type) · DEC-073 (the product-spec layer delegates physical representation to the dev team) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 4 AC-K-BR-Identity-5 · decisions/2026-06-15-party-type-marker-on-subtype.md._

### Requirement: Producer Registry

The Producer SHALL be the winery identity registry — the source of the producer reference that Module 0's Product Master keys off — and SHALL be a **standalone** entity, not a Party subtype. A Producer SHALL be created in the `draft` state, SHALL carry its identity attributes (name, region, optional appellation, country) and a translatable customer-facing description held as i18n-keyed text with per-attribute English fallback, and SHALL record a `ProducerCreated` domain event on creation. Creating a Producer SHALL NOT auto-create a Supplier (and the converse SHALL also hold): the two registries are linked only by an explicit operator action that lives in Module D, never implicitly here.

#### Scenario: Create a Producer

- **WHEN** an operator creates a Producer with a name, region and country
- **THEN** it is persisted in `draft`, its description is held as translatable text resolvable with an English fallback, and a `ProducerCreated` event is recorded

#### Scenario: Creating a Producer does not auto-create a Supplier

- **WHEN** a Producer is created
- **THEN** no Supplier row is created as a side effect; the two remain independent registries

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.4 (Producer — standalone winery registry; born `draft`; identity attributes; translatable description) · § 14.5 BR-K-Producer-1/3 (standalone; no auto-cross-create) · § 15.4 (`ProducerCreated`) · § 8 / openspec/specs/i18n/spec.md (translatable text, six locales) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-7 (birth half), § 4 AC-K-BR-Producer-1/3, § 5 AC-K-EVT-8._

### Requirement: Supplier — Minimal Party Subtype

The Supplier SHALL be the commercial-counterpart entity and SHALL be a Party subtype carrying the immutable party-type marker `supplier`. At launch the Supplier SHALL be **deliberately minimal** — a legal name, the immutable party-type marker, and standard timestamps — with richer Supplier-side commercial state (Supplier Profile, payment terms, the Supplier↔Producer link) owned by Module D and **not** modelled here. Creating a Supplier SHALL NOT auto-create a Producer. The product-catalog event pattern notwithstanding, Supplier creation SHALL record **no** domain event, because the PRD event catalog names none.

#### Scenario: Create a minimal Supplier

- **WHEN** an operator creates a Supplier with a legal name
- **THEN** it is persisted carrying the immutable party-type marker `supplier` and standard timestamps, and carries no commercial-terms or Supplier↔Producer-link attributes (those are Module D's)

#### Scenario: Supplier creation records no event

- **WHEN** a Supplier is created
- **THEN** no `SupplierCreated` (or any Supplier) domain event is recorded — the PRD names none

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.5 (Supplier — minimal Party subtype: legal name + immutable party-type marker + timestamps; richer state in Module D; no auto-link) · § 14.1 BR-K-Identity-5 (immutable marker) · § 14.5 BR-K-Producer-3 (no auto-cross-create) · § 15 (no Supplier event family) · spec/04-decisions/decisions.md DEC-067 / DEC-084 (Supplier terms live on the Allocation, not Module K) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 4 AC-K-BR-Identity-5, AC-K-BR-Producer-3._

### Requirement: Club

A Club SHALL be a Producer-operated membership program associated with **exactly one** operating Producer; Club creation SHALL be rejected when the operating-Producer association is missing, and the operating-Producer link SHALL be **immutable** once set (it does not change with Club lifecycle state). A Club SHALL be created in the `active` state and SHALL carry its per-Club fee as an **integer count of minor units plus an ISO 4217 currency code** (never a float), a registration-flow type, and the single-tier-at-launch structure. A Club SHALL record a `ClubCreated` domain event on creation.

#### Scenario: Create a Club for a Producer

- **WHEN** an operator creates a Club naming an existing operating Producer, with a fee amount and currency
- **THEN** it is persisted in `active`, the fee is stored as integer minor units + an ISO 4217 currency code, the operating-Producer reference is captured, and a `ClubCreated` event is recorded

#### Scenario: Club creation rejects a missing Producer

- **WHEN** a Club is created with no operating-Producer association
- **THEN** the creation is rejected

#### Scenario: The operating-Producer link is immutable

- **WHEN** the Club entity is inspected
- **THEN** there is no operation in this change that reassigns a Club's operating Producer

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.3 (Club — one operating Producer, immutable; born `active`; fee model; registration-flow type; single-tier launch) · § 14.4 BR-K-Club-1/2/5 (Producer required; link immutable; single tier) · § 15.3 (`ClubCreated`) · spec/04-decisions/decisions.md DEC-062 (single-tier at launch; multi-tier structure carried) · CLAUDE.md invariant 6 (money = integer minor units + ISO 4217) · openspec/specs/money/spec.md · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-6, § 4 AC-K-BR-Club-1, § 5 AC-K-EVT-7._

### Requirement: ProducerAgreement

The ProducerAgreement SHALL be the commercial agreement between NewCo and a Producer — a NewCo net-new entity. It SHALL reference **exactly one** Producer (required) and MAY be narrowed to a specific Club (optional); it SHALL be created in the `draft` state, SHALL carry its term dates and a settlement-cadence attribute (the D19 seam Module E reads), and SHALL record a `ProducerAgreementCreated` domain event on creation. The "at most one **active** agreement per Producer scope" rule is an **activation-time** invariant and is therefore out of this creation-only slice; draft agreements MAY be created freely.

#### Scenario: Create a draft ProducerAgreement

- **WHEN** an operator creates a ProducerAgreement naming a Producer, optionally narrowed to one of that Producer's Clubs
- **THEN** it is persisted in `draft` with its term dates and settlement cadence, and a `ProducerAgreementCreated` event is recorded

#### Scenario: A ProducerAgreement requires a Producer

- **WHEN** a ProducerAgreement is created with no Producer reference
- **THEN** the creation is rejected

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.6 / § 4.6.1 (ProducerAgreement — Producer required, Club optional; born `draft`; settlement-cadence D19 seam; lifecycle `draft → active → superseded|terminated`) · § 14.6 BR-K-Agreement-1 (one active per scope — an activation rule) · § 15.5 (`ProducerAgreementCreated`) · spec/04-decisions/decisions.md DEC-070 (ProducerAgreement entity in Module K) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-8, § 4 AC-K-BR-Agreement-1, § 5 AC-K-EVT-9._

### Requirement: Customer Identity

The Customer SHALL be NewCo's **natural-person** registry (B2C only; the record carries no B2C/B2B discriminator). A Customer's email SHALL be **globally unique** across all Customers, and a creation whose email collides with an existing Customer SHALL be rejected. A Customer SHALL be created in the `pending` state, SHALL carry the immutable party-type marker `customer`, a preferred currency (an ISO 4217 code from the launch set) and a preferred locale (from the launch set). A Customer SHALL carry an `originating_club_id` reference into the Club registry that is **created `NULL`** and for which this change provides **no mutation surface** (the one-shot lock at first membership approval is a deferred, approval-time concern). A Customer SHALL record a `CustomerCreated` domain event on creation whose payload is **PII-free** (no name, email, phone or date of birth).

#### Scenario: Create a Customer

- **WHEN** an operator creates a Customer with a unique email, a preferred currency and a preferred locale
- **THEN** it is persisted in `pending`, carrying the immutable party-type marker `customer` and an `originating_club_id` of `NULL`, and a `CustomerCreated` event is recorded with a payload that contains no name, email, phone or date of birth

#### Scenario: Duplicate email is rejected

- **WHEN** a Customer is created with an email that matches an existing Customer
- **THEN** the creation is rejected; two distinct emails both succeed

#### Scenario: The Originating Club is born unset and has no mutation surface

- **WHEN** a newly created Customer is inspected
- **THEN** its `originating_club_id` is `NULL`, and there is no operation in this change that sets or changes it (the one-shot lock arrives with the membership-approval change)

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer — natural-person registry; born `pending`; unique email; preferred currency/locale; no B2C/B2B discriminator) · § 6 / § 6.1 (Originating Club — set one-shot at first approval, immutable, may stay unset; the lock fires on the approval write) · § 14.1 BR-K-Identity-1/5 · § 15.1 (`CustomerCreated`) · spec/04-decisions/decisions.md DEC-066 / DEC-040 (OC = FK to Club, one-shot, nullable) · DEC-068 / DEC-017 (B2C only; no discriminator) · DEC-071 (sanctions/KYC fields nullable — Customers creatable un-screened) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 2 AC-K-J-1, § 4 AC-K-BR-Identity-1, AC-K-XM-25, § 5 AC-K-EVT-1 · decisions/2026-06-12-event-substrate-and-audit-store.md (PII-free payloads)._

### Requirement: Account — Billing Container

The Account SHALL be the per-Customer transactional/billing container, distinct from the Customer (the natural-person identity). It SHALL be **co-provisioned** within the same transaction as the Customer (one Customer = one Account at launch), SHALL be created in the `active` state with account type `personal`, and SHALL carry a default currency. The Account SHALL **NOT** be a monetary-balance or credit ledger — there is no "Account Credit" instrument at NewCo (goodwill is vouchers; Club Credits live on the Profile). The payment-provider customer reference SHALL **NOT** be provisioned at Account creation (it is created lazily on first payment-related action — out of this slice). Account creation SHALL record **no** domain event, because the PRD event catalog names none.

#### Scenario: Customer creation provisions one Account

- **WHEN** a Customer is created
- **THEN** exactly one Account is provisioned for that Customer in the same transaction, in `active` state with type `personal` and a default currency

#### Scenario: The Account holds no monetary balance and emits no event

- **WHEN** the Account entity is inspected
- **THEN** it carries no monetary-balance / credit-ledger attribute and no payment-provider reference at creation, and no `AccountCreated` (or any Account) domain event was recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.7 (Account — billing container; co-provisioned; born `active`/`personal`; NOT a money ledger; payment-provider ref lazy) · § 7.1 step 3 (Customer + Account + Party created together in onboarding) · § 15 (no Account event family) · spec/04-decisions/decisions.md DEC-014 (payment-provider reference only; no PCI) · DEC-068 (personal account at launch) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 2 AC-K-J-1, § 3 AC-K-FSM-9, § 4 AC-K-XM-22._

### Requirement: Profile — Multi-Profile Membership

The Profile SHALL **be** the membership in one Club — there SHALL be no separate Membership entity (the Netflix-style Customer↔Profile model). A Profile SHALL belong to **exactly one** Customer and **exactly one** Club, both required at creation. A single Customer MAY hold **multiple** Profiles across different Clubs, but SHALL hold **at most one non-terminal Profile per Club** (uniqueness on the Customer–Club pair), so a second Profile for a (Customer, Club) pair that already has a live Profile SHALL be rejected. A Profile SHALL be created in the `Applied` state and SHALL record a `ProfileCreated` domain event on creation. _(Because rejected Profiles are not reused — a re-application creates a new Profile row — the Customer–Club uniqueness is scoped to non-terminal states; the terminal states `rejected`/`cancelled`/`inactive` become reachable only with the deferred Profile lifecycle, whose change refines the constraint accordingly.)_

#### Scenario: Create a Profile

- **WHEN** an operator creates a Profile for a Customer in a Club
- **THEN** it is persisted in `Applied`, referencing exactly one Customer and one Club, and a `ProfileCreated` event is recorded

#### Scenario: One non-terminal Profile per Customer–Club pair

- **WHEN** a second Profile is created for a (Customer, Club) pair that already has a live Profile
- **THEN** the creation is rejected

#### Scenario: A Customer may hold Profiles across many Clubs

- **WHEN** a Customer is given Profiles in three different Clubs
- **THEN** all three are created (the multi-profile model), each unique on its own Customer–Club pair

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 3 (the Netflix-style Customer–Profile model; Profile is the membership, no Membership table) · § 4.2 / § 4.2.1 (Profile belongs to one Customer + one Club; born `Applied`; rejected not reused) · § 14.1 BR-K-Identity-2 (one Profile per Customer per Club) · § 15.2 (`ProfileCreated`) · spec/04-decisions/decisions.md DEC-012 / DEC-024 (multi-profile; one profile per club) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-2 (birth half), § 4 AC-K-BR-Identity-2, § 5 AC-K-EVT-5._

### Requirement: Birth States Recorded, Lifecycle Transitions Deferred

Every Parties spine entity that carries a lifecycle state SHALL define its full state domain and SHALL be created in its birth state: Customer `pending`, Account `active`, Producer `draft`, Club `active`, ProducerAgreement `draft`, Profile `Applied` (Supplier carries no lifecycle state). This change SHALL record state but SHALL NOT implement any state transition, approval/activation workflow, suspension, the single producer write (membership approve/decline), the Originating-Club lock, the Hero Package Capacity Invariant, or the Customer-segment derivation; those behaviours are deferred to the `parties-membership-lifecycle` change and beyond. Consequently, no lifecycle or state-change domain event (for example `CustomerActivated`, `ProfileActivated`, `OriginatingClubLocked`, `ClubSunset`, `ProducerActivated`) SHALL be emitted by this change.

#### Scenario: Each entity is born in its birth state

- **WHEN** a Customer, Account, Producer, Club, ProducerAgreement or Profile is created
- **THEN** its state is, respectively, `pending`, `active`, `draft`, `active`, `draft`, `Applied`; the full state domain is defined on the column for the lifecycle change to drive

#### Scenario: No transition path or lifecycle event exists in this change

- **WHEN** the Parties code surface for this change is inspected
- **THEN** there is no operation that transitions any entity out of its birth state, and no `*Activated`/`*Suspended`/`*Sunset`/`OriginatingClubLocked` (or other lifecycle) event is ever recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 / § 4.2.1 / § 4.3 / § 4.4 / § 4.6.1 / § 4.7 (the per-entity state machines + birth states) · § 6.1 (the `OriginatingClubLocked` lock fires on the first approval) · § 15 (lifecycle/state-change event families) · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 2 (Parties lifecycle/business-rules are subtasks sliced after the spine) · openspec/changes/parties-core/proposal.md (slice boundary)._

### Requirement: Spine Creation Events

On the creation of a Customer, Profile, Producer, Club or ProducerAgreement, the Parties module SHALL record the entity's verbatim `*Created` domain event — `CustomerCreated`, `ProfileCreated`, `ProducerCreated`, `ClubCreated`, `ProducerAgreementCreated` — through the platform `DomainEventRecorder`, **within the same database transaction** as the write, tagged with module `parties`, the acting `actor_role` resolved from the `ActorContext` seam, the entity type and id, and a **PII-free** payload: other parties referenced **by id only**, monetary amounts as integer `minor_units` + ISO 4217 `currency`, and **never** personal data (name, email, phone, date of birth, address). Supplier and Account creation SHALL record **no** domain event (the PRD event catalog names none). No `*Activated`/lifecycle event SHALL be recorded by this change.

#### Scenario: Creating a Customer records a PII-free CustomerCreated

- **WHEN** a Customer is created
- **THEN** a `CustomerCreated` event is recorded in the same transaction, tagged module `parties`, with the customer's entity type and id and a payload that references parties only by id and contains no name, email, phone or date of birth

#### Scenario: Each evented entity records its Created event

- **WHEN** a Profile, Producer, Club or ProducerAgreement is created
- **THEN** the corresponding `*Created` event (`ProfileCreated`, `ProducerCreated`, `ClubCreated`, `ProducerAgreementCreated`) is recorded in the writing transaction with a PII-free payload (money as `minor_units` + `currency` where present)

#### Scenario: Supplier and Account creation are event-silent

- **WHEN** a Supplier or an Account is created
- **THEN** no domain event is recorded for that creation

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 15.1–15.5 (the five `*Created` events; Module K names unchanged by the cascade) · § 15 (no Supplier/Account event family) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional outbox; PII-free payloads; money/FX payload discipline) · openspec/specs/event-substrate/spec.md (Transactional Event Recording; Domain Event Envelope) · CLAUDE.md invariants 4 & 10 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 5 AC-K-EVT-1/5/7/8/9._

