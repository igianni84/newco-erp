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

The Customer SHALL be NewCo's **natural-person** registry (B2C only; the record carries no B2C/B2B discriminator). A Customer's email SHALL be **globally unique** across all Customers, and a creation whose email collides with an existing Customer SHALL be rejected. A Customer SHALL be created in the `pending` state, SHALL carry the immutable party-type marker `customer`, a preferred currency (an ISO 4217 code from the launch set) and a preferred locale (from the launch set). A Customer SHALL carry an `originating_club_id` reference into the Club registry that is **created `NULL`** and is **set one-shot at the Customer's first-ever Profile approval** (per the *Profile Membership Approval* requirement) — **immutable thereafter** (no admin-override surface at launch) and permitted to **remain `NULL` indefinitely** for a Customer never approved into any Club (DEC-040). A Customer SHALL record a `CustomerCreated` domain event on creation whose payload is **PII-free** (no name, email, phone or date of birth).

#### Scenario: Create a Customer

- **WHEN** an operator creates a Customer with a unique email, a preferred currency and a preferred locale
- **THEN** it is persisted in `pending`, carrying the immutable party-type marker `customer` and an `originating_club_id` of `NULL`, and a `CustomerCreated` event is recorded with a payload that contains no name, email, phone or date of birth

#### Scenario: Duplicate email is rejected

- **WHEN** a Customer is created with an email that matches an existing Customer
- **THEN** the creation is rejected; two distinct emails both succeed

#### Scenario: The Originating Club is born unset and locked one-shot at first approval

- **WHEN** a newly created Customer is inspected
- **THEN** its `originating_club_id` is `NULL`
- **WHEN** that Customer's first-ever Profile is approved (per *Profile Membership Approval*)
- **THEN** `originating_club_id` is set one-shot to the approving Club and is immutable thereafter; a Customer never approved into any Club keeps it `NULL`

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer — natural-person registry; born `pending`; unique email; preferred currency/locale; no B2C/B2B discriminator) · § 6 / § 6.1 (Originating Club — set one-shot at first approval, immutable, may stay unset; the lock fires on the approval write) · § 14.1 BR-K-Identity-1/5 · § 15.1 (`CustomerCreated`) · spec/04-decisions/decisions.md DEC-066 / DEC-040 (OC = FK to Club, one-shot, nullable) · DEC-068 / DEC-017 (B2C only; no discriminator) · DEC-071 (sanctions/KYC fields nullable — Customers creatable un-screened) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 2 AC-K-J-1, AC-K-J-4 (first-approval OC lock), AC-K-BR-OC-1/2/3, § 4 AC-K-BR-Identity-1, AC-K-XM-25, § 5 AC-K-EVT-1 · decisions/2026-06-12-event-substrate-and-audit-store.md (PII-free payloads) · openspec/changes/parties-membership-activation (the one-shot lock that closes the parties-core no-mutation seam)._

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

Every Parties entity that carries a lifecycle state SHALL define its full state domain and SHALL be created in its birth state: Customer `pending`, Account `active`, Producer `draft`, Club `active`, ProducerAgreement `draft`, Profile `Applied` (Supplier carries no lifecycle state). The **supply-side** lifecycle — Producer, ProducerAgreement and Club — SHALL implement its state transitions and emit its lifecycle events, as governed by the Requirements *Producer Lifecycle*, *ProducerAgreement Lifecycle*, *Club Lifecycle* and *Supply-Side Lifecycle Events*. The **Customer and Producer compliance-screening lifecycles** — the KYC FSM and the Customer sanctions FSM, **each separate from the Customer/Producer status FSM** — SHALL be implemented as governed by the Requirements *Customer KYC Lifecycle*, *Customer Sanctions Screening Lifecycle*, *Producer KYC Lifecycle* and *Sanctions Screening Events*; their fields are added additively (nullable — DEC-071). The **demand-side activation** lifecycle is now implemented (the Requirements *Customer Onboarding Activation*, *Profile Membership Approval*, *Profile Activation* and *Demand-Side Activation Events*): the Customer `pending → active` transition, the Profile `Applied → Approved | Rejected` and `Approved → Active` transitions, and the Originating-Club one-shot lock — the `originating_club_id` field now has its mutation surface — exist and emit `CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked` (approval and decline are themselves audit-only — § 15.2 names no `ProfileApproved` / `ProfileRejected`). The **remaining demand-side status** lifecycle SHALL remain deferred: there SHALL be no `Active → Suspended | Lapsed | Cancelled | Inactive` Profile transition, no Account status transition (`ActivateAccount` / `SuspendAccount` / `CloseAccount`), no Customer `active → suspended | closed` transition, no **Hero Package Capacity Invariant** (approval and activation ship **uncapped** — the Module-A seam), no `Applied → WaitingList` path, and no Customer-segment derivation — and consequently no further demand-side status event (for example `CustomerSuspended`, `ProfileSuspended`, `ProfileExpired`, `WaitingListJoined`, `CustomerSegmentChanged`) SHALL be emitted, until the follow-on demand-side change(s) implement them. The **`kyc` Hold coupling** (auto-place on KYC `pending`, auto-lift on `verified`) and the **unified Hold registry** are implemented (the Requirements *Hold Registry*, *Hold Lifecycle and Lift Discipline*, *Hold Events* and *Hold and Sanctions Read-API*); the Hold→`suspended` **status** coupling, however, remains deferred with the remaining demand-side status FSMs (placing a Hold records the Hold but performs no status transition).

#### Scenario: Each entity is born in its birth state

- **WHEN** a Customer, Account, Producer, Club, ProducerAgreement or Profile is created
- **THEN** its state is, respectively, `pending`, `active`, `draft`, `active`, `draft`, `Applied`

#### Scenario: Supply-side, compliance, Hold and demand-side activation transitions exist; the remaining demand-side status transitions do not

- **WHEN** the Parties code surface is inspected
- **THEN** Producer, ProducerAgreement and Club expose lifecycle-transition operations and record their lifecycle events; the Customer/Producer KYC and Customer sanctions screening FSMs expose their transitions; the unified Hold registry exposes place/lift with the `kyc` Hold auto-placed on KYC `pending` and auto-lifted on `verified`; AND the demand-side **activation** transitions exist — Customer `pending → active` (`ActivateCustomer`), Profile `Applied → Approved | Rejected` (`ApproveProfile` / `DeclineProfile`) and `Approved → Active` (`ActivateProfile`), with the Originating-Club one-shot lock — recording `CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked`
- **AND** Customer `active → suspended | closed`, Account status transitions, Profile `Active → Suspended | Lapsed | Cancelled | Inactive`, the Hold→`suspended` coupling (placing a Hold performs no status transition), the Hero Package Capacity Invariant (approval and activation are uncapped), the `Applied → WaitingList` path, and Customer-segment derivation do **not** exist, and no `CustomerSuspended` / `ProfileSuspended` / `WaitingListJoined` / `CustomerSegmentChanged` event is recordable

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 / § 4.2.1 / § 4.3 / § 4.4 / § 4.6.1 / § 4.7 (per-entity state machines + birth states) · § 4.8 / § 4.8.1 (the unified Hold registry + the `kyc` Hold coupling — implemented) · § 9.1 / § 9.2 (KYC and sanctions screening FSMs) · § 6.1 (the Originating-Club one-shot lock — now implemented) · § 4.2.1 (Profile approval/activation — now implemented; the capacity gate deferred) · § 13 (Hero Package Capacity Invariant — deferred Module-A seam) · § 5 (Customer segments — deferred) · § 10.1 (Hold→suspension coupling — deferred with the remaining status FSMs) · § 15 (lifecycle event families) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-1 (Customer FSM + `CustomerActivated`), AC-K-FSM-2 (Profile FSM), AC-K-J-1 (Customer activation gates), AC-K-J-4 (first-approval OC lock), AC-K-EVT-1 / AC-K-EVT-5 / AC-K-EVT-10 (the three activation events), AC-K-FSM-3 / AC-K-FSM-4 (KYC + sanctions FSMs separate), AC-K-FSM-9 (Account `active`, no `pending`; Hold→Account-suspension deferred), AC-K-FSM-10 / AC-K-FSM-11 (Hold lifecycle + lift discipline), AC-K-J-13 / AC-K-XM-18 (Hero Package capacity reads Module A `qty` — deferred) · spec/05-release/Build_Workplan_v0.3-MVP.md § Phase 2 (the demand-side membership write + OC immutability are Phase-2 deliverables) · openspec/changes/parties-membership-activation/proposal.md (the activation subset implemented here; the remaining demand-side lifecycle deferred to follow-on changes)._

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

A Producer in `draft` SHALL transition to `active` on an `ActivateProducer` operation, recording **`ProducerActivated`**. Activation SHALL enforce the **KYC-cleared gate** (§ 4.4; BR-K-Producer-2): the Producer's `kyc_status` SHALL be **cleared** — `verified`, `not_required`, **or NULL** (a Producer never touched by KYC) — and the activation SHALL be **rejected** while `kyc_status` is `pending` or `rejected`, leaving the Producer in `draft` and recording no event. NULL is treated as cleared so the additive KYC field (DEC-071) does not break the activation of Producers created before this change; an operator may explicitly set `not_required` to **waive** KYC (ADR `2026-06-17-producer-kyc-gate-not-required-clears.md`). This closes the deferred seam the previously-shipped slice left ungated; `ProducerActivated` therefore fires on `draft → active` only when KYC is cleared (§ 15.4).

A Producer in `active` SHALL transition to `retired` on a `RetireProducer` operation, recording **`ProducerRetired`**, and SHALL **cascade**: every Club the Producer operates that is currently in `active` SHALL transition to `sunset` (recording its own `ClubSunset`, per the Club Lifecycle requirement) within the same transaction. Clubs already in `sunset` or `closed` SHALL be left unchanged (the cascade is idempotent over already-transitioned Clubs). The **Profile leg** of the § 10.2 offboarding cascade (per-Profile cancellation and the Module-S Club-Credit conversion signal) SHALL NOT be performed by this change — it is deferred with Profile lifecycle.

Every transition SHALL be **from-state guarded**: an `ActivateProducer` on a Producer not in `draft`, or a `RetireProducer` on a Producer not in `active`, SHALL be rejected with a localized `IllegalProducerTransition` and SHALL leave all state and the event log unchanged. The guard SHALL be evaluated against a transaction-locked re-read of the row so concurrent transition attempts cannot both succeed.

#### Scenario: Activate a draft Producer

- **WHEN** `ActivateProducer` is invoked on a Producer in `draft` whose `kyc_status` is cleared (`verified`, `not_required`, or NULL)
- **THEN** the Producer's status becomes `active` and a `ProducerActivated` event is recorded in the same transaction (module `parties`, entityType `Producer`, PII-free payload)

#### Scenario: Activation requires KYC cleared

- **WHEN** `ActivateProducer` is invoked on a `draft` Producer whose `kyc_status` is `verified`, `not_required`, or NULL
- **THEN** the activation succeeds and `ProducerActivated` is recorded
- **WHEN** `ActivateProducer` is invoked on a `draft` Producer whose `kyc_status` is `pending` or `rejected`
- **THEN** the activation is rejected, the Producer stays `draft`, and no `ProducerActivated` event is recorded

#### Scenario: Retire an active Producer cascades Club sunset

- **GIVEN** a Producer in `active` that operates two Clubs in `active` and one Club already in `closed`
- **WHEN** `RetireProducer` is invoked
- **THEN** the Producer's status becomes `retired` and a `ProducerRetired` event is recorded
- **AND** the two `active` Clubs transition to `sunset`, each recording a `ClubSunset` caused by the retirement, while the `closed` Club is left unchanged

#### Scenario: Illegal Producer transitions are rejected

- **WHEN** `ActivateProducer` is invoked on a Producer not in `draft`, or `RetireProducer` on a Producer not in `active`
- **THEN** an `IllegalProducerTransition` is raised, the Producer's status is unchanged, and no `ProducerActivated` / `ProducerRetired` (and no cascade `ClubSunset`) event is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.4 (Producer FSM `draft → active → retired`; **activation requires KYC cleared — `verified` or `not_required`**; retirement preserves Product Masters, blocks new activations) · § 10.2 (Producer offboarding cascade → Club sunset) · § 14.5 BR-K-Producer-2/4 · § 15.4 (`ProducerActivated`, `ProducerRetired`) · spec/04-decisions/decisions.md DEC-071 (KYC/sanctions fields nullable, added in compliance) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-7 (activation gated on KYC cleared — positive `verified`/`not_required`, negative `pending`/`rejected`), § 2 AC-K-J-10 / AC-K-J-19, § 5 AC-K-EVT-8, § 6 AC-K-XM-2 (Module 0 consumes these events to gate Product Master activation) · decisions/2026-06-17-producer-kyc-gate-not-required-clears.md (this change tightens the previously-deferred KYC gate; NULL treated as cleared) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional recording)._

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

### Requirement: Customer KYC Lifecycle

The Customer SHALL carry a **KYC lifecycle that is separate from the Customer status FSM**: the four-state domain `not_required → pending → verified | rejected`, held in an **additive nullable** `kyc_status` field (DEC-071 — a NULL `kyc_status` denotes a Customer created un-screened). The Customer SHALL also carry an administratively-set `kyc_required` flag and an enhanced-KYC trigger flag + timestamp, both additive nullable.

Setting `kyc_required` SHALL transition KYC `not_required → pending`. A Customer in KYC `pending` SHALL transition to `verified` (identity verification cleared) or to `rejected` (failed) via explicit operator Actions that are the sole writers of `kyc_status`. KYC `verified` and `not_required` are the **cleared** (non-blocking) states; `pending` and `rejected` are blocking. The blocking effect on purchases is realized by the **`kyc` Hold** (the *Hold Registry*): setting `kyc_required` SHALL **auto-place** a Customer-scope `kyc` Hold within the same transaction as the `not_required → pending` write; recording `verified` SHALL **auto-lift** the Customer's active `kyc` Hold(s) within the same transaction (the system auto-lift the per-type discipline permits — DEC-160); recording `rejected` SHALL **leave** the `kyc` Hold in place (Compliance reviews case-by-case — § 9.1). This coupling is within-module Action orchestration (the KYC Action calls the Hold place/lift), since KYC records no domain event of its own.

The enhanced-KYC trigger flag + timestamp SHALL exist as additive nullable fields recording whether the Customer crossed the €10,000-single / €50,000-cumulative threshold. The **detection** of that crossing (the periodic scan and the at-order-completion check) reads cumulative-spend data that does not exist at launch and is **deferred**; only the fields ship.

KYC state changes SHALL record **no KYC domain event** (the PRD event catalog § 15.1 names none); the KYC change is captured in the append-only audit trail only, while the coupled `kyc` Hold place/lift records its own `CustomerHoldPlaced` / `CustomerHoldLifted` (per the *Hold Events* requirement). Every KYC transition SHALL be **from-state guarded** against a transaction-locked re-read and SHALL reject an out-of-state call with a localized `IllegalKycTransition`, leaving state and the event log unchanged.

#### Scenario: The kyc_required flag transitions not_required to pending and auto-places the kyc Hold

- **WHEN** an operator sets a Customer's `kyc_required` flag and the Customer's `kyc_status` is `not_required` or NULL
- **THEN** `kyc_status` becomes `pending`, a Customer-scope `kyc` Hold is auto-placed in the same transaction, and the only domain event recorded is `CustomerHoldPlaced` (KYC itself records no event)

#### Scenario: Verified auto-lifts the kyc Hold; rejected leaves it in place

- **WHEN** a Customer in KYC `pending` (with an active `kyc` Hold) is recorded `verified`
- **THEN** `kyc_status` becomes `verified` (a cleared state), the active `kyc` Hold is auto-lifted, and `CustomerHoldLifted` is recorded (KYC itself records no event)
- **WHEN** a Customer in KYC `pending` is recorded `rejected`
- **THEN** `kyc_status` becomes `rejected` (a blocking state), the `kyc` Hold remains in place (no automatic onward transition — Compliance reviews case-by-case), and no Hold event is recorded

#### Scenario: The KYC FSM is separate from the Customer status FSM

- **WHEN** the Parties code surface is inspected
- **THEN** `kyc_status` is a field and FSM distinct from the Customer status (`pending / active / suspended / closed`), and a KYC transition does not move the Customer status

#### Scenario: Enhanced-KYC fields exist but detection is deferred

- **WHEN** a Customer is inspected
- **THEN** it carries a nullable enhanced-KYC flag and timestamp, and there is no operation in this change that auto-sets them from purchase totals (the detection job is a documented seam)

#### Scenario: Illegal KYC transitions are rejected

- **WHEN** `RecordKycVerified` or `RecordKycRejected` is invoked on a Customer whose `kyc_status` is not `pending`
- **THEN** an `IllegalKycTransition` is raised, `kyc_status` is unchanged, and no `kyc` Hold is placed or lifted

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer KYC state + `kyc_required` flag + enhanced-KYC trigger fields), § 9.1 (KYC four-state lifecycle; `not_required` default; setting `kyc_required` → `pending`; `pending` auto-places the `kyc` Hold, `verified` auto-lifts it, `rejected` leaves it; cleared = `verified` ∨ `not_required`), § 4.8 / § 4.8.1 (the `kyc` Hold — auto-place/auto-lift coupling; DEC-160), § 15.1 (no KYC event family; `CustomerHoldPlaced`/`CustomerHoldLifted`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-3 (KYC FSM separate; the `kyc` Hold auto-places on `pending` and auto-lifts on `verified`), AC-K-J-7 (KYC required → Hold blocks → verified → Hold lifts → purchases resume), AC-K-J-7a (enhanced-KYC trigger fields) · spec/04-decisions/decisions.md DEC-071, DEC-035, DEC-160 · decisions/2026-06-18-hold-lift-discipline-per-type.md (the `kyc` auto-lift is the system path; operator lift of a `kyc` Hold is rejected) · decisions/2026-06-12-event-substrate-and-audit-store.md (audit trail; transactional Hold events). The enhanced-KYC detection job (AC-K-J-7a) remains a deferred seam._

### Requirement: Customer Sanctions Screening Lifecycle

The Customer SHALL carry a **sanctions-screening lifecycle that is separate from both the Customer status FSM and the KYC FSM, and independent of KYC**: the four-state domain `pending → passed | failed | under_review` plus `under_review → passed | failed`, held in additive nullable fields (DEC-071) — `sanctions_status`, `last_screening_at`, `next_rescreen_at`, and the screening `trigger_source` (`onboarding | cadence | aml_threshold | compliance_ad_hoc`). A NULL `sanctions_status` denotes a Customer created un-screened and SHALL be treated, for any downstream purchase gate, as not-`passed` (blocked) — exactly like `pending`.

An explicit operator Action SHALL record each screening verdict (manual-first — the screen is the floor, the vendor integration is deferrable): it SHALL set `sanctions_status` to the verdict, stamp `last_screening_at`, set `next_rescreen_at` to the 12-month-forward moment, record the `trigger_source`, and (on a `passed`/`failed` completion) record the matching screening event per the *Sanctions Screening Events* requirement. A verdict carrying `trigger_source = onboarding` SHALL be the Customer's **first** screening (rejected with `IllegalSanctionsTransition` if `last_screening_at` is already set); every other `trigger_source` denotes a **re-screen**.

The sanctions lifecycle SHALL be **independent of KYC**: a sanctions transition SHALL NOT change `kyc_status` and a KYC transition SHALL NOT change `sanctions_status`; the two clear independently. The **enforcement** of `sanctions_status = passed` as a purchase precondition is **Module S's** at order completion (Module K is sanctions-blind by design) and is NOT in this change.

The **automated 12-month re-screen cadence** (the daily background job) and the **AML-threshold auto re-screen** (the cumulative-totals scan) are **deferred** (manual-first); the operator ad-hoc re-screen Action, the four events, the `trigger_source` field and the `next_rescreen_at` field ship now.

#### Scenario: Onboarding screening records the first verdict and event

- **WHEN** an operator records an onboarding sanctions screening of `passed` for a Customer whose `sanctions_status` is NULL or `pending` (no prior `last_screening_at`)
- **THEN** `sanctions_status` becomes `passed`, `last_screening_at` is stamped, `next_rescreen_at` is set 12 months forward, `trigger_source` is `onboarding`, and a `CustomerOnboardingScreeningPassed` event is recorded
- **WHEN** the onboarding verdict is `failed`
- **THEN** `sanctions_status` becomes `failed` and a `CustomerOnboardingScreeningFailed` event is recorded

#### Scenario: under_review resolves to passed or failed

- **WHEN** a Customer in `under_review` is re-screened to `passed` (resp. `failed`)
- **THEN** `sanctions_status` becomes that verdict and the matching `CustomerRescreening*` event is recorded

#### Scenario: Re-screening records the rescreening events with the trigger source

- **WHEN** an operator runs an ad-hoc re-screen on a previously-screened Customer to `passed`, with `trigger_source = compliance_ad_hoc`
- **THEN** `sanctions_status` becomes `passed`, `trigger_source` is recorded as `compliance_ad_hoc`, and a `CustomerRescreeningPassed` event is recorded

#### Scenario: Sanctions and KYC are independent state machines

- **WHEN** one Customer has `kyc_status = verified` and `sanctions_status = pending`, and another has `sanctions_status = passed` and `kyc_status = pending`
- **THEN** each pair is recorded independently — no sanctions transition changes `kyc_status` and no KYC transition changes `sanctions_status` (the purchase-gate consequence of each non-clean state is enforced downstream, not in this slice)

#### Scenario: Automated cadence and AML detection are deferred; the fields and ad-hoc path ship

- **WHEN** the Parties code surface is inspected
- **THEN** `last_screening_at`, `next_rescreen_at` and `trigger_source` exist and an operator can record an ad-hoc re-screen, but there is no daily cadence job and no cumulative-totals scan in this change (documented seams)

#### Scenario: An onboarding screening on an already-screened Customer is rejected

- **WHEN** a verdict with `trigger_source = onboarding` is recorded for a Customer whose `last_screening_at` is already set
- **THEN** an `IllegalSanctionsTransition` is raised and the sanctions state is unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer sanctions state, last-screening moment, next re-screen), § 9.2 (sanctions four-state lifecycle; EU+UIF+OFAC; 12-month cadence as a daily job; between-cycle trigger paths and trigger sources — DEC-030/DEC-035), § 9.3 (the order-completion gate is Module S — the single enforcement point; a Customer can exist `sanctions_status = pending`), § 9.4 (KYC and sanctions independent — both clear independently), § 9.5 (manual-first launch posture; the screen + gate are the floor, the integration is deferrable; acceptance drives state, not a live vendor) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-4 (sanctions FSM separate; `pending → passed/failed/under_review`, `under_review → passed/failed`; re-screening fires the events), AC-K-FSM-5 (KYC and sanctions independent), AC-K-EVT-12 (onboarding + rescreening events drive state), AC-K-EVT-12a (trigger source recorded; the cadence/AML automation is the seam) · spec/04-decisions/decisions.md DEC-071, DEC-030, DEC-041. The order-completion enforcement (AC-K-J-20) is Module S's; the cadence/AML automation is deferred._

### Requirement: Producer KYC Lifecycle

The Producer SHALL carry a **provenance-KYC lifecycle distinct from Customer KYC**: the four-state domain `not_required → pending → verified | rejected`, held in an additive nullable `kyc_status` field (DEC-071). A NULL `kyc_status` denotes a Producer never touched by KYC and SHALL be treated as **cleared** at the activation gate (so existing and never-screened Producers keep activating — see *Producer Lifecycle*).

Operator Actions SHALL be the sole writers of the Producer `kyc_status`: a require operation (`not_required`/NULL → `pending`), a verified operation (`pending → verified`), a rejected operation (`pending → rejected`), and a **waive** operation (→ `not_required`) — the operator "deselect" that clears the gate exactly as `verified`. KYC `verified` and `not_required` are the **cleared** states; `pending` and `rejected` block. Producer KYC changes record **no domain event** (the PRD names none); the cleared semantics are carried by `ProducerActivated` when activation fires. Every transition SHALL be **from-state guarded** and reject an out-of-state call with a localized `IllegalKycTransition`, leaving state unchanged.

#### Scenario: Require, verify, reject the Producer KYC

- **WHEN** the require operation is invoked on a Producer whose `kyc_status` is NULL or `not_required`
- **THEN** `kyc_status` becomes `pending`
- **WHEN** the Producer in `pending` is recorded `verified` (resp. `rejected`)
- **THEN** `kyc_status` becomes that state, and no domain event is recorded

#### Scenario: Operator waives Producer KYC to not_required

- **WHEN** the waive operation is invoked on a Producer in `pending` or `rejected`
- **THEN** `kyc_status` becomes `not_required` (a cleared state) — the operator-deselect that lets the Producer activate as if verified

#### Scenario: Producer KYC is distinct from Customer KYC

- **WHEN** the Producer entity is inspected
- **THEN** its `kyc_status` is a Producer-level field independent of any Customer KYC state

#### Scenario: Illegal Producer KYC transitions are rejected

- **WHEN** the verified or rejected operation is invoked on a Producer not in `pending`
- **THEN** an `IllegalKycTransition` is raised and `kyc_status` is unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.4 (Producer KYC four-state lifecycle; cleared = `verified` ∨ `not_required`; `not_required` ≡ `verified` at every gate; distinct from Customer KYC), § 14.5 BR-K-Producer-2 (KYC clearance gates Product Master activation), § 15.4 (`ProducerActivated` — KYC cleared) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-7 (Producer activation gated on KYC cleared — positive `verified`/`not_required`, negative `pending`/`rejected`) · spec/04-decisions/decisions.md DEC-071 · decisions/2026-06-17-producer-kyc-gate-not-required-clears.md (operator-waive via `not_required`; NULL treated as cleared for additivity)._

### Requirement: Sanctions Screening Events

Each sanctions screening **completion** SHALL record its **verbatim** Module K event through the platform `DomainEventRecorder`, within the same database transaction as the `sanctions_status` write, tagged module `parties`, the acting `actor_role` and id from the `ActorContext` seam, entity type `Customer` and id, and a **PII-free** payload (customer id plus the verdict / `trigger_source` enum values only — never name, email, phone or date of birth). The four event names are `CustomerOnboardingScreeningPassed`, `CustomerOnboardingScreeningFailed` (recorded when the **onboarding** screening completes), and `CustomerRescreeningPassed`, `CustomerRescreeningFailed` (recorded when any **re-screen** completes). A screening landing `under_review` is **not** a completion and SHALL record **no** event. No event name outside this set, and **no KYC event**, SHALL be recorded by this change.

#### Scenario: Onboarding completion records the onboarding event

- **WHEN** an onboarding screening completes `passed` (resp. `failed`)
- **THEN** `CustomerOnboardingScreeningPassed` (resp. `CustomerOnboardingScreeningFailed`) is recorded in the writing transaction, module `parties`, with a PII-free payload

#### Scenario: Re-screen completion records the rescreening event

- **WHEN** a re-screen (`trigger_source` cadence / aml_threshold / compliance_ad_hoc) completes `passed` (resp. `failed`)
- **THEN** `CustomerRescreeningPassed` (resp. `CustomerRescreeningFailed`) is recorded, PII-free

#### Scenario: under_review records no event

- **WHEN** a screening lands `under_review`
- **THEN** `sanctions_status` becomes `under_review` and no screening event is recorded; a later resolution to `passed`/`failed` records the corresponding `CustomerRescreening*` event

#### Scenario: No KYC event is recorded

- **WHEN** any KYC transition runs (Customer or Producer)
- **THEN** no domain event is recorded for it (audit only) — the PRD names no KYC event

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 15.6 (`CustomerOnboardingScreeningPassed`/`...Failed`, `CustomerRescreeningPassed`/`...Failed` — "the screening / re-screening pair is two events with two outcomes each"), § 15.1 (no KYC event family) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-EVT-12 (the four events drive sanctions state), AC-K-EVT-12a (trigger source recorded on the screening record) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional outbox; PII-free payloads) · openspec/specs/event-substrate/spec.md (Transactional Event Recording; Domain Event Envelope) · CLAUDE.md invariants 4 & 10._

### Requirement: Hold Registry

The Parties module SHALL provide a **unified, trigger-agnostic Hold registry** — the single account-restriction primitive that gates commercial activity. A Hold SHALL carry: a `hold_type` from the six-value domain `admin | kyc | payment | fraud | compliance | credit`; a **scope** comprising a `scope_type` from `customer | account | profile` and a `scope_id` (the id of the scoped Customer, Account or Profile — a within-module reference); a `status` from `active | lifted` (born `active`); an optional placement `reason`; the placement actor (role + id, from the `ActorContext` seam) and the placement moment; and, once lifted, the lift actor + lift moment + an optional `lift_reason`. The Hold SHALL be a new `parties_holds` table (the module-table-prefix convention) added by a single **additive** migration; its value-set columns SHALL carry the layered enforcement idiom (a string column + the backed-enum cast on both engines, plus a PostgreSQL-only `CHECK` deriving from `Enum::cases()`).

A scope MAY carry **multiple concurrent `active` Holds** (any one of which blocks the activity it gates — the blocking is the downstream surface's, per the *Hold and Sanctions Read-API* requirement). Module K SHALL be the **registry-of-record** for every Hold, and the registry SHALL be **trigger-agnostic**: it records the type and state of a Hold regardless of how the placement was triggered, and a **manual operator-placement path** SHALL exist for every type (the automatic triggers for `payment`/`fraud`/`compliance`/`credit` are Module E/S signals deferred to those modules; the registry is unchanged by their automation depth). The `hold_type` enum SHALL expose an `autoLiftable(): bool` predicate that is true for `kyc` and `payment` only (consumed by the *Hold Lifecycle and Lift Discipline* requirement).

#### Scenario: The Hold entity carries its type, scope, status and audit metadata

- **WHEN** a Hold is placed on a scope with a type
- **THEN** a `parties_holds` row persists carrying the `hold_type`, the `scope_type` + `scope_id`, `status = active`, the placement actor (role + id) and the placement moment, and (until lifted) a null lift actor / lift moment

#### Scenario: The six Hold types and the three scopes are the domain

- **WHEN** the `HoldType` and `HoldScope` enums are inspected
- **THEN** `HoldType` is exactly `admin | kyc | payment | fraud | compliance | credit` and `HoldScope` is exactly `customer | account | profile`, each a string-backed enum whose `->value` is the spec token
- **AND** `HoldType::autoLiftable()` is true for `kyc` and `payment` and false for `admin`, `fraud`, `compliance`, `credit`

#### Scenario: Multiple concurrent Holds may exist on one scope

- **WHEN** a `kyc` Hold and an `admin` Hold are both placed on one Customer
- **THEN** both are recorded `active` on that Customer scope concurrently (the scope is not single-Hold)

#### Scenario: The registry is trigger-agnostic with a manual-placement path for every type

- **WHEN** an operator manually places a Hold of any of the six types
- **THEN** the Hold is recorded identically regardless of type — Module K is the registry-of-record, and no automatic upstream trigger is required for the record to exist

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.8 (the unified Hold entity — six types `admin/kyc/payment/fraud/compliance/credit`, three scopes Customer/Account/Profile, placement/lift audit metadata; Module K registry-of-record), § 4.8.1 (multiple concurrent Holds; trigger-agnostic) · § 14.8 BR-K-Hold-1 (multiple Holds, any blocks) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-10 (Hold lifecycle — six types × three scopes, audit metadata), AC-K-BR-Hold-1 (multiple concurrent Holds), AC-K-MVP-2 (trigger-agnostic registry; manual-placement path) · spec/04-decisions/decisions.md DEC-181 (uniformity), DEC-160 (per-type lift — the `autoLiftable` partition), DEC-168 (K is registry-of-record), DEC-071 (additive nullable field pattern) · decisions/2026-06-18-hold-lift-discipline-per-type.md · decisions/2026-06-12-production-db-engine.md (Postgres-truthful migration; CHECK-from-cases idiom)._

### Requirement: Hold Lifecycle and Lift Discipline

A Hold SHALL be placed and lifted by explicit operator Actions that are the sole writers of `parties_holds`, each running inside one `DB::transaction`. `PlaceHold` SHALL create a Hold (`status = active`) recording the placement actor, moment and optional `reason`, and SHALL record the `CustomerHoldPlaced` event (per the *Hold Events* requirement) in the same transaction. `LiftHold` SHALL re-read the Hold under a transaction lock, set `status = lifted` recording the lift actor, lift moment and optional `lift_reason`, and record `CustomerHoldLifted` in the same transaction.

`LiftHold` SHALL enforce the **per-type lift discipline** (DEC-160; ADR `2026-06-18-hold-lift-discipline-per-type.md`): a Hold whose type is **auto-managed** (`HoldType::autoLiftable()` — `kyc` or `payment`) SHALL NOT be lifted by the operator path and SHALL be **rejected** with a localized `IllegalHoldLift`, because those types are lifted by the system on their clearing signal (the `kyc` auto-lift is wired in this change via the *Customer KYC Lifecycle* requirement; the `payment` auto-lift trigger is a deferred Module-E seam). A Hold of type `admin`, `fraud`, `compliance` or `credit` SHALL be lifted freely by the operator path. Lifting a Hold that is not `active` (already `lifted`) SHALL be **rejected** with a localized `IllegalHoldLift`, leaving state and the event log unchanged.

#### Scenario: Place a Hold records the placement actor, reason and moment

- **WHEN** `PlaceHold` is invoked with a type, a scope and a reason
- **THEN** an `active` Hold persists carrying that type, scope, reason, the placement actor (from `ActorContext`) and the placement moment, and a `CustomerHoldPlaced` event is recorded in the same transaction

#### Scenario: An operator lifts an operator-liftable Hold

- **WHEN** `LiftHold` is invoked on an `active` `admin` (resp. `fraud`, `compliance`, `credit`) Hold
- **THEN** the Hold's `status` becomes `lifted`, the lift actor + lift moment + `lift_reason` are recorded, and a `CustomerHoldLifted` event is recorded in the same transaction

#### Scenario: Operator-lift of an auto-managed Hold is rejected

- **WHEN** `LiftHold` is invoked on an `active` `kyc` or `payment` Hold (an auto-managed type)
- **THEN** an `IllegalHoldLift` is raised, the Hold stays `active`, and no `CustomerHoldLifted` event is recorded — these types lift only via their system clearing signal

#### Scenario: Lifting an already-lifted Hold is rejected

- **WHEN** `LiftHold` is invoked on a Hold whose `status` is already `lifted`
- **THEN** an `IllegalHoldLift` is raised and state and the event log are unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.8 (place/lift; placement & lift actor/moment recorded), § 4.8.1 (DEC-160 per-type lift discipline — auto-lift permitted on `kyc`/`payment`, operator lift required on `admin`/`fraud`/`compliance`/`credit`) · § 14.8 BR-K-Hold-1 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-10 (place/lift records actor + moment), AC-K-FSM-11 (lift discipline — `kyc`/`payment` auto-lift, the other four operator-lift; auto-lift on the four is rejected) · spec/04-decisions/decisions.md DEC-160 (E6-07 per-type lift) · decisions/2026-06-18-hold-lift-discipline-per-type.md · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional recording)._

### Requirement: Hold Events

Each Hold placement SHALL record a verbatim **`CustomerHoldPlaced`** domain event and each Hold lift a verbatim **`CustomerHoldLifted`** domain event (PRD § 15.1) through the platform `DomainEventRecorder`, **within the same database transaction** as the Hold write, tagged with module `parties`, the acting `actor_role` + id from the `ActorContext` seam, `entity_type = 'Hold'` and the Hold id, and a **PII-free** payload carrying the `hold_id`, `hold_type`, `scope_type`, `scope_id` and the business `reason` (no name, email, phone or date of birth). Because the PRD event catalog names only the `Customer`-scoped Hold events, these two names SHALL be recorded for Holds of **every** scope (the `scope_type` + `scope_id` in the payload distinguish a Customer-, Account- or Profile-scoped Hold — the zero-invention reading of AC-K-FSM-10's "or Profile/Account analogs"). No Hold event name outside this pair SHALL be recorded by this change.

#### Scenario: Placing a Hold records a PII-free CustomerHoldPlaced

- **WHEN** a Hold is placed
- **THEN** a `CustomerHoldPlaced` event is recorded in the writing transaction, tagged module `parties`, `entity_type` `Hold`, with a payload of `hold_id` / `hold_type` / `scope_type` / `scope_id` / `reason` and no personal data

#### Scenario: Lifting a Hold records a CustomerHoldLifted

- **WHEN** a Hold is lifted (by the operator path or the system auto-lift)
- **THEN** a `CustomerHoldLifted` event is recorded in the lifting transaction with a PII-free payload referencing the same Hold

#### Scenario: The two Hold event names cover every scope

- **WHEN** an Account-scoped or Profile-scoped Hold is placed
- **THEN** the event recorded is still `CustomerHoldPlaced`, with `scope_type` (`account` / `profile`) + `scope_id` in the payload identifying the scope — no other Hold event name is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 15.1 (`CustomerHoldPlaced` / `CustomerHoldLifted` — the only Hold events named) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-EVT-2 (`CustomerHoldPlaced`/`CustomerHoldLifted`; audit metadata on the payload), AC-K-FSM-10 ("or Profile/Account analogs") · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional outbox; PII-free payloads) · openspec/specs/event-substrate/spec.md (Transactional Event Recording; Domain Event Envelope) · CLAUDE.md invariants 4 & 10._

### Requirement: Hold and Sanctions Read-API

The Parties module SHALL expose a **uniform read contract** answering *"is this scope clear to transact?"* by returning the **`(sanctions_status, active-Hold-list)` tuple** (DEC-181). The contract SHALL be an interface returning a **PII-free DTO** (carrying the scope's `sanctions_status` and the list of active Hold types — never the `Hold` Eloquent model, preserving the no-model-leak boundary law), with a convenience predicate that a scope **is clear** iff its `sanctions_status` is `passed` **and** it has no `active` Hold. The contract SHALL resolve **scope cascade**: interrogating a **Profile** SHALL return the Profile's own active Holds **and** the active Holds of its parent Customer (a Customer-scope Hold blocks every Profile — BR-K-Hold-3), while a **Profile-scope** Hold SHALL be returned only for that Profile (BR-K-Hold-4).

This change SHALL **expose the contract ready** (interface + DTO + a bound implementation); the **downstream enforcement** that consumes it — every DEC-181 transaction-initiation surface (Module S order completion / cart-add / redemption-request, Module C pickup / SO `planned` / shipment-request, Module E INV3 charge / refund routing) — is the receiving module's and is **NOT** in this change (Module K is Hold-blind by design — it provides the tuple, it does not block).

#### Scenario: The read-API returns the sanctions/Hold tuple for a Customer

- **WHEN** the read contract is asked whether a Customer scope is clear
- **THEN** it returns the Customer's `sanctions_status` and the list of that Customer's active Hold types, and reports "clear" iff `sanctions_status` is `passed` and there is no active Hold

#### Scenario: A Customer-scope Hold cascades to the Customer's Profiles

- **GIVEN** an active Customer-scope `fraud` Hold on a Customer with two Profiles
- **WHEN** the read contract is asked whether either Profile is clear
- **THEN** the Customer's `fraud` Hold appears in that Profile's active-Hold-list and the Profile is reported not clear

#### Scenario: A Profile-scope Hold isolates to that Profile

- **GIVEN** an active Profile-scope `payment` Hold on Profile X of a Customer who also has Profile Y
- **WHEN** the read contract is asked about Profile Y
- **THEN** Profile X's Hold does NOT appear for Profile Y, and Profile Y is clear if it has no Hold of its own and its Customer is sanctions-`passed` with no Customer-scope Hold

#### Scenario: The contract returns a PII-free DTO, not a model

- **WHEN** a downstream module consumes the read contract
- **THEN** it receives a DTO of `sanctions_status` + active Hold types (no `Hold` Eloquent model, no personal data) — and no transaction-initiation enforcement surface is implemented by this change (the consumers are deferred to Modules S/C/E)

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.8.1 (DEC-181 — Module K exposes the `(sanctions_status, active-Hold-list)` tuple; enforcement is the downstream surface's; Module K is Hold-blind), § 9.3 (the floor chain — K exposes the read-API, the downstream surface enforces) · § 14.8 BR-K-Hold-2 (read at every transaction-initiation surface), BR-K-Hold-3 (Customer cascade), BR-K-Hold-4 (Profile isolation) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-XM-12 (uniform "is this scope clear?" read API returning the tuple; single source of truth), AC-K-XM-3 (order-completion read), AC-K-BR-Hold-3/4 (cascade + isolation), AC-K-J-20 (the K-side tuple under the sanctions × Hold matrix) · spec/04-decisions/decisions.md DEC-181 · decisions/2026-06-11-modular-monolith-architecture.md (events + small read contracts; no cross-module model import)._

### Requirement: Profile Membership Approval

The Profile SHALL transition `Applied → Approved` (membership approval) and `Applied → Rejected` (membership decline) via explicit operator Actions — `ApproveProfile` and `DeclineProfile` — that are the sole writers of the Profile `state` for these transitions, each running inside one `DB::transaction` against a transaction-locked re-read of the Profile row. These realize **the one retained producer write** ("membership approve/decline", L-PP / K-Q4); the producer-facing HTTP surface is deferred and the Actions are operator/console-invocable at launch (admin-parity, DEC-083).

`ApproveProfile` on a Profile in `Applied` SHALL set `state = Approved`. `DeclineProfile` on a Profile in `Applied` SHALL set `state = Rejected` — a terminal-for-this-application state; a re-application creates a **new** Profile row (per the *Profile — Multi-Profile Membership* requirement), and because the Customer–Club partial-unique index already excludes the terminal `rejected` state, the new `Applied` row inserts with **no** index migration.

Neither approval nor decline SHALL record a Profile lifecycle domain event: the PRD § 15.2 event catalog **names no `ProfileApproved` or `ProfileRejected`**, so — exactly as a KYC transition records no KYC event (audit trail only — the shipped *Customer KYC Lifecycle* precedent) — the state change is captured in the append-only audit trail only. The **sole** domain event the approve path MAY record is the conditional `OriginatingClubLocked`.

On `ApproveProfile`, **if and only if** this is the Customer's **first-ever** Profile approval across any Club — detected by the Customer's `originating_club_id` being currently unset (re-read under a transaction lock) — the Action SHALL, in the same transaction, set `Customer.originating_club_id` to the approving Profile's `club_id` and record an `OriginatingClubLocked` event (per the *Demand-Side Activation Events* requirement). The lock SHALL be **one-shot** (a subsequent approval on another Club neither re-fires the event nor changes the link), **immutable** thereafter (no admin-override surface at launch), and MAY remain unset indefinitely for a Customer never approved into any Club (DEC-040). The Originating-Club lock SHALL NOT be a standalone Action — it is exclusively an in-transaction side-effect of `ApproveProfile`.

The **Hero Package Capacity Invariant** (§ 13) — the membership no-oversell guard the PRD enforces "at every membership approval" — reads the cap from Module A's Hero-Package Allocation `qty` (§ 13.2 / AC-K-XM-18); Module A is unbuilt, so this slice ships `ApproveProfile` **uncapped** with the capacity gate as a documented deferred Module-A seam (the `Applied → WaitingList` capacity-exceeded path is likewise deferred). Every transition SHALL be **from-state guarded** against the transaction-locked re-read: an `ApproveProfile` or `DeclineProfile` on a Profile not in `Applied` SHALL be rejected with a localized `IllegalProfileTransition`, leaving state, the Originating-Club link and the event log unchanged.

#### Scenario: Approve a first-ever applied Profile locks the Originating Club

- **GIVEN** a Customer whose `originating_club_id` is unset, with a Profile in `Applied` for Club C
- **WHEN** `ApproveProfile` is invoked on that Profile
- **THEN** the Profile's `state` becomes `Approved`, the Customer's `originating_club_id` is set to Club C, and exactly one `OriginatingClubLocked` event is recorded in the same transaction (and no `ProfileApproved` event, which the catalog does not name)

#### Scenario: A second Club's approval does not re-lock or re-fire

- **GIVEN** a Customer whose `originating_club_id` is already set to Club C, with a Profile in `Applied` for a different Club D
- **WHEN** `ApproveProfile` is invoked on the Club-D Profile
- **THEN** that Profile's `state` becomes `Approved`, the Customer's `originating_club_id` stays Club C, and **no** `OriginatingClubLocked` event is recorded

#### Scenario: Decline an applied Profile is terminal and event-silent

- **WHEN** `DeclineProfile` is invoked on a Profile in `Applied`
- **THEN** the Profile's `state` becomes `Rejected`, **no** domain event is recorded, and a subsequent re-application for the same Customer–Club pair creates a new Profile in `Applied` (the partial-unique index admits it)

#### Scenario: Illegal approve/decline is rejected

- **WHEN** `ApproveProfile` or `DeclineProfile` is invoked on a Profile not in `Applied`
- **THEN** an `IllegalProfileTransition` is raised, the Profile's `state` and the Customer's `originating_club_id` are unchanged, and no event is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1 (Profile FSM `Applied → Approved | Rejected`; rejected not reused — a new Profile row), § 3.1 (the one retained producer write — membership approve/decline, L-PP / K-Q4; producer UI deferred, admin-parity), § 6 / § 6.1 (the Originating-Club one-shot lock at first `MembershipApprovedByProducer`; immutable; may stay unset — DEC-040), § 13 / § 13.2 (the Hero Package Capacity Invariant enforced at every membership approval; the cap lives on Module A's Allocation `qty` — deferred Module-A seam), § 15.2 (the Profile event family names no `ProfileApproved` / `ProfileRejected`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-2 (Profile FSM traversal), AC-K-J-4 (first-Club approval → `OriginatingClubLocked` fires once, sets the OC to the approving Club, later approvals do not re-fire), AC-K-BR-OC-1/2/3 (set at first approval; immutable, no admin-override; may stay unset), AC-K-J-13 / AC-K-XM-18 (capacity at approval reads Module A `qty`) · spec/04-decisions/decisions.md DEC-040 (OC nullable one-shot), DEC-083 (admin-parity), DEC-073 (physical representation) · decisions/2026-06-12-event-substrate-and-audit-store.md (audit trail; transactional, PII-free recording) · openspec/specs/party-registry/spec.md (the *Profile — Multi-Profile Membership* + *Customer Identity* seams this closes)._

### Requirement: Profile Activation

The Profile SHALL transition `Approved → Active` via an explicit `ActivateProfile` Action — the sole writer of the Profile `state` for this transition — running inside one `DB::transaction` against a transaction-locked re-read, recording a `ProfileActivated` event (per the *Demand-Side Activation Events* requirement) in the same transaction. In production this transition is driven by Module E's `MembershipFeePaid` event (the membership-fee-paid signal) or a free-club activation where no fee applies (§ 4.2.1); Module E does not exist, so the **`MembershipFeePaid` listener is a deferred Module-E seam** — `ActivateProfile` is the within-module writer, invoked by the free-club / operator path now, and **no** Module-E event contract is fabricated. The Hero Package Capacity Invariant gate at the transition-into-`Active` (§ 13.1) reads Module A's Allocation `qty` and is the **same deferred Module-A seam** as approval: `ActivateProfile` ships **uncapped**. Every transition SHALL be **from-state guarded**: an `ActivateProfile` on a Profile not in `Approved` SHALL be rejected with a localized `IllegalProfileTransition`, leaving state and the event log unchanged.

#### Scenario: Activate an approved Profile

- **WHEN** `ActivateProfile` is invoked on a Profile in `Approved`
- **THEN** the Profile's `state` becomes `Active` and exactly one `ProfileActivated` event is recorded in the same transaction (module `parties`, `entity_type` `Profile`, PII-free payload)

#### Scenario: Illegal activation is rejected

- **WHEN** `ActivateProfile` is invoked on a Profile not in `Approved` (e.g. `Applied` or already `Active`)
- **THEN** an `IllegalProfileTransition` is raised, the Profile's `state` is unchanged, and no `ProfileActivated` event is recorded

#### Scenario: The membership-fee trigger is a deferred seam

- **WHEN** the Parties code surface is inspected
- **THEN** `ActivateProfile` exists as the within-module writer of `Approved → Active`, and there is **no** `MembershipFeePaid` listener and no fabricated Module-E event class in this change (the cross-module trigger is a documented Module-E seam)

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1 (`Approved → Active` on Module E's `MembershipFeePaid`, or free-club activation where no fee applies), § 13.1 (the capacity invariant is evaluated at every transition into `Active` — the cap on Module A's Allocation `qty`, deferred), § 15.2 (`ProfileActivated` on the transition; "Module K consumes Module E's `MembershipFeePaid` to drive this"), § 15.8 (`MembershipFeePaid` is a Module E event Module K consumes) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-2 (`ProfileActivated` fires on the transition), AC-K-EVT-5 · openspec/changes/archive/2026-06-16-parties-producer-lifecycle (the precedent: ship the transition with the upstream trigger/gate as a documented seam) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional recording)._

### Requirement: Customer Onboarding Activation

The Customer SHALL transition `pending → active` via an explicit `ActivateCustomer` Action — the sole writer of the Customer `status` for this transition — running inside one `DB::transaction` against a transaction-locked re-read, recording a `CustomerActivated` event (per the *Demand-Side Activation Events* requirement) in the same transaction. Activation SHALL be a **hard composite gate** — the four onboarding gates plus the KYC-cleared rider: `email_verified_at` set **∧** `tc_accepted_at` set **∧** `privacy_accepted_at` set **∧** `sanctions_status = passed` **∧** KYC **cleared** (`kyc_status` ∈ {`verified`, `not_required`} or NULL) whenever `kyc_required` is set. The three acceptance moments SHALL be **additive nullable timestamp** columns on `parties_customers` (the gate inputs — § 4.1 acceptance is "tracked at Customer level with timestamps"; DEC-071 additive pattern; the physical column shape is the dev-team realization, DEC-073), born `NULL` and written by the (deferred) registration surface or an operator.

Activation SHALL be **explicit**: no sanctions screening verdict, KYC transition, or acceptance write SHALL auto-transition the Customer `status`. The Customer status FSM is separate from and independent of the KYC and sanctions FSMs (§ 9.4), and activation/suspension are explicit — never automatically driven by another FSM or by Profile state (AC-K-BR-Customer-1). Customer activation SHALL perform **no** Account transition: the Account is born `active` (it has no `pending` state — AC-K-FSM-9).

A gate-unmet `ActivateCustomer` (any of the five conditions unmet), or an `ActivateCustomer` on a Customer not in `pending`, SHALL be rejected with a localized `IllegalCustomerTransition`, leaving `status = pending` and the event log unchanged.

#### Scenario: Activate a Customer once all gates clear

- **GIVEN** a Customer in `pending` with `email_verified_at`, `tc_accepted_at` and `privacy_accepted_at` all set, `sanctions_status = passed`, and KYC cleared (or `kyc_required` unset)
- **WHEN** `ActivateCustomer` is invoked
- **THEN** the Customer's `status` becomes `active` and exactly one `CustomerActivated` event is recorded in the same transaction (module `parties`, `entity_type` `Customer`, PII-free payload), and the Customer's Account `status` is unchanged

#### Scenario: Any unmet gate blocks activation

- **WHEN** `ActivateCustomer` is invoked on a `pending` Customer with any one of the gates unmet — `email_verified_at` null, or `tc_accepted_at` null, or `privacy_accepted_at` null, or `sanctions_status ≠ passed`, or `kyc_required` set while `kyc_status` is `pending`/`rejected`
- **THEN** an `IllegalCustomerTransition` is raised, the Customer stays `pending`, and no `CustomerActivated` event is recorded

#### Scenario: Activation is explicit, not auto-driven by another FSM

- **WHEN** a Customer's sanctions screening is recorded `passed`, or a KYC transition runs, with no `ActivateCustomer` call
- **THEN** the Customer `status` is unchanged (no auto-activation) — the status FSM is independent of the KYC and sanctions FSMs

#### Scenario: Illegal from-state is rejected

- **WHEN** `ActivateCustomer` is invoked on a Customer not in `pending`
- **THEN** an `IllegalCustomerTransition` is raised and the Customer `status` is unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer FSM `pending → active`; `active` once email verified + T&C/privacy accepted + KYC cleared if required; acceptance a hard gate, tracked at Customer level with timestamps), § 7.1 (onboarding flow; the sanctions screen passing completes `pending → active` when the other gates are met), § 9.4 (KYC / sanctions / status FSMs independent), § 4.7 (Account born `active`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-J-1 (`pending → active` only after all four gates clear — email verification, T&C, privacy, sanctions), AC-K-BR-Identity-3 (acceptance a hard gate alongside email verification and KYC clearance), AC-K-FSM-1 (Customer FSM + `CustomerActivated`), AC-K-BR-Customer-1 (activation/suspension explicit, not auto-driven by Profile state), AC-K-FSM-9 (Account `active`, no `pending`), AC-K-EVT-1 (`CustomerActivated` on `pending → active`) · spec/04-decisions/decisions.md DEC-071 (additive nullable fields), DEC-073 (physical representation delegated to the dev team) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional, PII-free recording)._

### Requirement: Demand-Side Activation Events

Each demand-side activation transition SHALL record its **verbatim** Module K event — `CustomerActivated` (on Customer `pending → active`), `ProfileActivated` (on Profile `Approved → Active`), `OriginatingClubLocked` (on the Customer's first-ever Profile approval) — through the platform `DomainEventRecorder`, **within the same database transaction** as the state write, tagged with module `parties`, the acting `actor_role` + id resolved from the `ActorContext` seam, the entity type and id, and a **PII-free** payload (entity ids + enum/business values only — never name, email, phone or date of birth). `CustomerActivated` SHALL carry `entity_type = 'Customer'` with payload `{customer_id, status}`; `ProfileActivated` SHALL carry `entity_type = 'Profile'` with payload `{profile_id, state}`; `OriginatingClubLocked` SHALL carry `entity_type = 'Customer'` with payload `{customer_id, club_id, profile_id, locked_at}` — the Customer, the locking Club, the triggering membership and the moment (§ 6.1 verbatim).

The `ApproveProfile` and `DeclineProfile` transitions SHALL record **no** Profile event of their own (audit-only — § 15.2 names none). **No** event name outside this three-name set SHALL be recorded by this change. Each of the three events SHALL be recorded as a **root** event (no `causation_id`; `correlation_id` defaults to its own `event_id`), since the transition it records has no parent event in the same transaction. The downstream consumers `OriginatingClubLocked` names — Module S settlement-eligibility, Module E D19 accrual, HubSpot (§ 6 / § 15.6 / AC-K-EVT-10) — are **not** wired by this change: Module K records the event (the capture); all consumption is downstream and deferred.

#### Scenario: Each activation transition records its verbatim event PII-free

- **WHEN** any of the three activation transitions runs (`ActivateCustomer`, `ActivateProfile`, or the first-approval `ApproveProfile`)
- **THEN** exactly its corresponding event (`CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked`) is recorded in the writing transaction, tagged module `parties`, with the entity type/id and an `actor_role` from `ActorContext`, and its payload contains only entity ids and enum/business values (no name, email, phone or date of birth)

#### Scenario: OriginatingClubLocked carries the four spec fields

- **WHEN** `OriginatingClubLocked` is recorded
- **THEN** its payload is exactly `{customer_id, club_id, profile_id, locked_at}` (the Customer, the locking Club, the triggering membership, and the moment), `entity_type` `Customer`, PII-free

#### Scenario: Approve and decline record no Profile event; nothing outside the set fires

- **WHEN** `ApproveProfile` (on a non-first approval) or `DeclineProfile` runs
- **THEN** no Profile lifecycle event is recorded, and across the whole change no event name outside `{CustomerActivated, ProfileActivated, OriginatingClubLocked}` is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 15.1 (`CustomerActivated` on `pending → active`), § 15.2 (`ProfileActivated` on `Approved → Active`; the family names no `ProfileApproved` / `ProfileRejected`), § 15.6 (`OriginatingClubLocked` — payload Customer / locking Club / moment / triggering membership; consumers Module S / E / HubSpot), § 6.1 (the OC payload, verbatim) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-EVT-1 (`CustomerActivated`), AC-K-EVT-5 (`ProfileActivated`), AC-K-EVT-10 (`OriginatingClubLocked` payload fields + downstream consumers) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional outbox; PII-free payloads; root `correlation_id` = own `event_id`) · openspec/specs/event-substrate/spec.md (Transactional Event Recording; Domain Event Envelope) · CLAUDE.md invariants 4 & 10._

