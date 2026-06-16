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

Every Parties entity that carries a lifecycle state SHALL define its full state domain and SHALL be created in its birth state: Customer `pending`, Account `active`, Producer `draft`, Club `active`, ProducerAgreement `draft`, Profile `Applied` (Supplier carries no lifecycle state). The **supply-side** lifecycle — Producer, ProducerAgreement and Club — SHALL now implement its state transitions and emit its lifecycle events, as governed by the Requirements *Producer Lifecycle*, *ProducerAgreement Lifecycle*, *Club Lifecycle* and *Supply-Side Lifecycle Events*. The **demand-side** lifecycle SHALL remain deferred: there SHALL be no Customer, Account or Profile state transition, no Profile approval/activation workflow, no producer membership approve/decline write, no Originating-Club lock (the `originating_club_id` field SHALL retain its no-mutation seam), no Hero Package Capacity Invariant, and no Customer-segment derivation — and consequently no demand-side lifecycle or state-change domain event (for example `CustomerActivated`, `ProfileActivated`, `OriginatingClubLocked`, `CustomerSegmentChanged`) SHALL be emitted, until the demand-side change(s) implement them.

#### Scenario: Each entity is born in its birth state

- **WHEN** a Customer, Account, Producer, Club, ProducerAgreement or Profile is created
- **THEN** its state is, respectively, `pending`, `active`, `draft`, `active`, `draft`, `Applied`

#### Scenario: Supply-side transitions exist; demand-side transitions do not

- **WHEN** the Parties code surface is inspected
- **THEN** Producer, ProducerAgreement and Club expose lifecycle-transition operations and record their lifecycle events
- **AND** Customer, Account and Profile expose no operation that transitions them out of their birth state, the `originating_club_id` field has no mutation surface, and no demand-side lifecycle event (`CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked` / `CustomerSegmentChanged`) is recordable

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 / § 4.2.1 / § 4.3 / § 4.4 / § 4.6.1 / § 4.7 (per-entity state machines + birth states) · § 6.1 (the Originating-Club lock fires on first approval — demand-side) · § 13 (Hero Package Capacity Invariant — demand-side) · § 5 (Customer segments — demand-side) · § 15 (lifecycle event families) · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 2 (Parties lifecycle sliced after the spine) · openspec/changes/archive/2026-06-15-parties-core/proposal.md (the deferred-table slice boundary; supply-side is this change, demand-side follows)._

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

### Requirement: Producer Lifecycle

The Producer SHALL transition through its state machine `draft → active → retired` (one operating direction; the FSM is linear) via explicit operator Actions that are the sole writers of `Producer.status`, each recording its lifecycle event in the same database transaction as the state write.

A Producer in `draft` SHALL transition to `active` on an `ActivateProducer` operation, recording **`ProducerActivated`**. The PRD precondition that activation requires KYC verification (§ 4.4) is a **deferred seam**: the KYC four-state lifecycle and its fields are owned by the future `parties-compliance` change (DEC-071 — sanctions/KYC fields are nullable and added additively), so this change SHALL implement the transition without enforcing a KYC gate; `parties-compliance` SHALL tighten it.

A Producer in `active` SHALL transition to `retired` on a `RetireProducer` operation, recording **`ProducerRetired`**, and SHALL **cascade**: every Club the Producer operates that is currently in `active` SHALL transition to `sunset` (recording its own `ClubSunset`, per the Club Lifecycle requirement) within the same transaction. Clubs already in `sunset` or `closed` SHALL be left unchanged (the cascade is idempotent over already-transitioned Clubs). The **Profile leg** of the § 10.2 offboarding cascade (per-Profile cancellation and the Module-S Club-Credit conversion signal) SHALL NOT be performed by this change — it is deferred with Profile lifecycle.

Every transition SHALL be **from-state guarded**: an `ActivateProducer` on a Producer not in `draft`, or a `RetireProducer` on a Producer not in `active`, SHALL be rejected with a localized `IllegalProducerTransition` and SHALL leave all state and the event log unchanged. The guard SHALL be evaluated against a transaction-locked re-read of the row so concurrent transition attempts cannot both succeed.

#### Scenario: Activate a draft Producer

- **WHEN** `ActivateProducer` is invoked on a Producer in `draft`
- **THEN** the Producer's status becomes `active` and a `ProducerActivated` event is recorded in the same transaction (module `parties`, entityType `Producer`, PII-free payload)

#### Scenario: Activation enforces no KYC gate in this slice

- **WHEN** a Producer in `draft` is activated and no KYC verdict exists (the KYC fields are not yet modelled)
- **THEN** the activation succeeds — the KYC-verified precondition is a documented seam tightened by `parties-compliance`, not enforced here

#### Scenario: Retire an active Producer cascades Club sunset

- **GIVEN** a Producer in `active` that operates two Clubs in `active` and one Club already in `closed`
- **WHEN** `RetireProducer` is invoked
- **THEN** the Producer's status becomes `retired` and a `ProducerRetired` event is recorded
- **AND** the two `active` Clubs transition to `sunset`, each recording a `ClubSunset` caused by the retirement, while the `closed` Club is left unchanged

#### Scenario: Illegal Producer transitions are rejected

- **WHEN** `ActivateProducer` is invoked on a Producer not in `draft`, or `RetireProducer` on a Producer not in `active`
- **THEN** an `IllegalProducerTransition` is raised, the Producer's status is unchanged, and no `ProducerActivated` / `ProducerRetired` (and no cascade `ClubSunset`) event is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.4 (Producer FSM `draft → active → retired`; activation requires KYC; retirement preserves Product Masters, blocks new activations) · § 10.2 (Producer offboarding cascade → Club sunset) · § 14.5 BR-K-Producer-2/4 · § 15.4 (`ProducerActivated`, `ProducerRetired`) · spec/04-decisions/decisions.md DEC-071 (KYC/sanctions fields nullable, added in compliance) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-7, § 2 AC-K-J-10 / AC-K-J-19, § 5 AC-K-EVT-8, § 6 AC-K-XM-2 (Module 0 consumes these events to gate Product Master activation) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional recording)._

### Requirement: ProducerAgreement Lifecycle

The ProducerAgreement SHALL transition through its state machine `draft → active → superseded | terminated` via explicit operator Actions that are the sole writers of `ProducerAgreement.status`, each recording its lifecycle event in the same database transaction as the state write.

A ProducerAgreement in `draft` SHALL transition to `active` on an `ActivateProducerAgreement` operation, recording **`ProducerAgreementActivated`**. Activation SHALL enforce **BR-K-Agreement-1** (at most one `active` agreement per scope): the **scope** is the `(producer_id, club_id)` tuple, where a `NULL` `club_id` denotes the distinct Producer-wide scope (a Producer-wide agreement and a Club-narrowed agreement therefore occupy different scopes and MAY both be `active`). If an `active` agreement already exists in the same scope as the agreement being activated, that prior agreement SHALL transition `active → superseded` in the same transaction, recording **`ProducerAgreementSuperseded`**, and the audit SHALL pair the two (the superseded agreement references the superseding one, and vice versa, in the event payloads).

A ProducerAgreement in `active` SHALL transition to `terminated` on a `TerminateProducerAgreement` operation, recording **`ProducerAgreementTerminated`**. Termination SHALL NOT cascade to any Producer-level state change (§ 4.6.1).

Every transition SHALL be **from-state guarded** against a transaction-locked re-read: an `ActivateProducerAgreement` on an agreement not in `draft`, or a `TerminateProducerAgreement` on an agreement not in `active`, SHALL be rejected with a localized `IllegalProducerAgreementTransition` and SHALL leave all state and the event log unchanged. The same-scope prior-active lookup SHALL be NULL-safe (a Producer-wide activation matches only other `NULL`-`club_id` agreements of that Producer).

#### Scenario: Activate a draft agreement with no prior active in scope

- **WHEN** `ActivateProducerAgreement` is invoked on a `draft` agreement and no `active` agreement exists in its `(producer_id, club_id)` scope
- **THEN** the agreement's status becomes `active`, a `ProducerAgreementActivated` event is recorded, and no `ProducerAgreementSuperseded` event is recorded

#### Scenario: Activating a replacement supersedes the prior active in the same scope

- **GIVEN** an `active` ProducerAgreement A for a Producer (Producer-wide, `club_id` NULL)
- **WHEN** a second `draft` agreement B for the same Producer (also `club_id` NULL) is activated
- **THEN** A transitions to `superseded` recording `ProducerAgreementSuperseded`, B transitions to `active` recording `ProducerAgreementActivated`, and the two events pair old + new in their payloads

#### Scenario: Scope isolation between Producer-wide and Club-narrowed agreements

- **GIVEN** an `active` Producer-wide agreement (`club_id` NULL) and an `active` Club-narrowed agreement (`club_id = C`) for the same Producer
- **WHEN** a new `draft` Club-narrowed agreement for the same `club_id = C` is activated
- **THEN** only the prior `club_id = C` agreement is superseded; the Producer-wide agreement remains `active`

#### Scenario: Terminate an active agreement without cascading

- **WHEN** `TerminateProducerAgreement` is invoked on an `active` agreement
- **THEN** the agreement's status becomes `terminated`, a `ProducerAgreementTerminated` event is recorded, and the Producer's state is unchanged

#### Scenario: Illegal ProducerAgreement transitions are rejected

- **WHEN** `ActivateProducerAgreement` is invoked on an agreement not in `draft`, or `TerminateProducerAgreement` on an agreement not in `active`
- **THEN** an `IllegalProducerAgreementTransition` is raised, no status changes, and no agreement lifecycle event is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.6 / § 4.6.1 (ProducerAgreement FSM `draft → active → superseded | terminated`; supersession pairs old + new; termination does not cascade to Producer) · § 14.6 BR-K-Agreement-1 (at most one active per Producer scope), BR-K-Agreement-3 (renewal pairs old + new in audit) · § 15.5 (`ProducerAgreementActivated`, `ProducerAgreementSuperseded`, `ProducerAgreementTerminated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-8, § 2 AC-K-J-11 / AC-K-J-12, § 5 AC-K-EVT-9 · AskUserQuestion 2026-06-15 (scope = `(producer_id, club_id)`, NULL `club_id` a distinct Producer-wide scope)._

### Requirement: Club Lifecycle

The Club SHALL transition through its state machine `active → sunset → closed` via explicit operator Actions that are the sole writers of `Club.status`, each recording its lifecycle event in the same database transaction as the state write.

A Club in `active` SHALL transition to `sunset` on a `SunsetClub` operation, recording **`ClubSunset`**. `SunsetClub` SHALL be the single writer of `ClubSunset` — invoked both as a standalone operator action and as the per-Club step of the Producer-retirement cascade (Producer Lifecycle requirement). Sunset blocks new memberships and new offers while preserving existing Profiles (§ 4.3); enforcement of those blocks at the membership/offer surfaces is a downstream concern, not part of this transition.

A Club in `sunset` SHALL transition to `closed` on a `CloseClub` operation, recording **`ClubClosed`**. The PRD precondition that closure occurs only once all members have migrated or expired (§ 4.3) reads Profile state, which does not exist in this slice; it is a **deferred seam** — `CloseClub` SHALL implement the transition without enforcing an all-members-gone gate (vacuously satisfiable today, as no Profile can be `Active` without the demand-side transitions), and the demand-side change SHALL tighten it.

Every transition SHALL be **from-state guarded** against a transaction-locked re-read: a `SunsetClub` on a Club not in `active`, or a `CloseClub` on a Club not in `sunset` (including an attempt to close an `active` Club directly), SHALL be rejected with a localized `IllegalClubTransition` and SHALL leave all state and the event log unchanged.

#### Scenario: Sunset an active Club

- **WHEN** `SunsetClub` is invoked on a Club in `active`
- **THEN** the Club's status becomes `sunset` and a `ClubSunset` event is recorded in the same transaction

#### Scenario: Close a sunset Club

- **WHEN** `CloseClub` is invoked on a Club in `sunset`
- **THEN** the Club's status becomes `closed` and a `ClubClosed` event is recorded — no all-members-gone precondition is enforced in this slice (the gate is a deferred seam)

#### Scenario: Illegal Club transitions are rejected

- **WHEN** `SunsetClub` is invoked on a Club not in `active`, or `CloseClub` on a Club not in `sunset` (e.g. an `active` Club)
- **THEN** an `IllegalClubTransition` is raised, the Club's status is unchanged, and no `ClubSunset` / `ClubClosed` event is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.3 (Club FSM `active → sunset → closed`; sunset blocks new memberships/offers, preserves Profiles; closed terminal once members migrated/expired) · § 10.2 (sunset is the per-Club leg of Producer retirement) · § 14.4 BR-K-Club-3 · § 15.3 (`ClubSunset`, `ClubClosed`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-6, § 2 AC-K-J-19 · AskUserQuestion 2026-06-15 (CloseClub included now, all-members-gone gate as a deferred seam)._

### Requirement: Supply-Side Lifecycle Events

Each supply-side transition SHALL record its **verbatim** Module K event — `ProducerActivated`, `ProducerRetired`, `ProducerAgreementActivated`, `ProducerAgreementSuperseded`, `ProducerAgreementTerminated`, `ClubSunset`, `ClubClosed` — through the platform `DomainEventRecorder`, **within the same database transaction** as the state write, tagged with module `parties`, the acting `actor_role` and id resolved from the `ActorContext` seam, the entity type (`Producer` / `ProducerAgreement` / `Club`) and id, and a **PII-free** payload (entity ids + enum/business values only — these three entities carry no personal data, and other parties are referenced by id only). No event name outside this set, and no demand-side lifecycle event, SHALL be recorded by this change.

The two **derived** event chains SHALL be causally linked using the recorder's `causationId` / `correlationId`: each cascade `ClubSunset` SHALL carry the `id` of the `ProducerRetired` event as its `causation_id` and share that event's `correlation_id`; the `ProducerAgreementSuperseded` recorded during an activation SHALL carry the `id` of the `ProducerAgreementActivated` event as its `causation_id` and share its `correlation_id`. The supersession pair SHALL additionally carry the linkage in payload — the `ProducerAgreementSuperseded` payload references the **superseding** agreement id, and the `ProducerAgreementActivated` payload references the **superseded** agreement id (null when the activation superseded nothing).

#### Scenario: Each transition records its verbatim event PII-free in the same transaction

- **WHEN** any supply-side transition runs
- **THEN** exactly its corresponding event (from the seven-name set) is recorded in the writing transaction, tagged module `parties` with the entity type/id and an `actor_role` from `ActorContext`, and its payload contains only entity ids and enum/business values (no name, email, phone or other personal data)

#### Scenario: Cascade events are causally linked to the retirement

- **WHEN** `RetireProducer` cascades to sunset N operated Clubs
- **THEN** the `ProducerRetired` event is the root and each of the N `ClubSunset` events carries that event's `id` as `causation_id` and shares its `correlation_id`

#### Scenario: Supersession events pair old and new

- **WHEN** activating an agreement supersedes a prior active one in the same scope
- **THEN** the `ProducerAgreementActivated` payload references the superseded agreement id, the `ProducerAgreementSuperseded` payload references the superseding agreement id, and the supersession event is caused by (and shares the correlation of) the activation event

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 15.3 / § 15.4 / § 15.5 (the seven verbatim event names; Module K names unchanged by the cascade — AC-K-MVP-1) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 5 AC-K-EVT-8 / AC-K-EVT-9, § 2 AC-K-J-12 / AC-K-J-19 · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional outbox; PII-free payloads; `correlation_id` / `causation_id` envelope) · openspec/specs/event-substrate/spec.md (Transactional Event Recording; Domain Event Envelope) · CLAUDE.md invariants 4 & 10._

