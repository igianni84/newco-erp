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

The ProducerAgreement SHALL be the commercial agreement between NewCo and a Producer — a NewCo net-new entity. It SHALL reference **exactly one** Producer (required) and MAY be narrowed to a specific Club (optional). A per-Club-narrowed agreement's Club **SHALL be `active`** at the time of scoping: a new ProducerAgreement scoped to a `sunset` or `closed` Club SHALL be **rejected** with a localized `ProducerAgreementClubNotActive` exception (BR-K-Agreement-4 / MVP-DEC-009). Producer-wide scope (`club_id` NULL) is **ungated**; and supersession/renewal (BR-K-Agreement-3) **inherits** the superseded agreement's scope and is **exempt** from this Club-active check (a wind-down amendment on a since-`sunset` Club is unaffected). It SHALL be created in the `draft` state, SHALL carry its term dates and a **settlement-cadence** attribute drawn from a **closed set** — `quarterly` (the default), `monthly`, `semi-annual` — **enforced server-side (domain + DB CHECK, not UI-only)**: a create carrying an out-of-set cadence SHALL be **rejected** (the value times settlement in Module E and PO issuance in Module D — an out-of-set value would mis-time money movement). It SHALL record a `ProducerAgreementCreated` domain event on creation. The "at most one **active** agreement per Producer scope" rule is an **activation-time** invariant (per *ProducerAgreement Lifecycle*) and is out of this creation path; draft agreements MAY otherwise be created freely.

#### Scenario: Create a draft ProducerAgreement

- **WHEN** an operator creates a ProducerAgreement naming a Producer, optionally narrowed to one of that Producer's **active** Clubs, with a settlement cadence in the closed set
- **THEN** it is persisted in `draft` with its term dates and settlement cadence, and a `ProducerAgreementCreated` event is recorded

#### Scenario: A ProducerAgreement requires a Producer

- **WHEN** a ProducerAgreement is created with no Producer reference
- **THEN** the creation is rejected

#### Scenario: A per-Club agreement requires an active Club

- **WHEN** a new ProducerAgreement is scoped to a Club that is `sunset` or `closed`
- **THEN** a `ProducerAgreementClubNotActive` is raised and no agreement is created
- **WHEN** a new ProducerAgreement is Producer-wide (`club_id` NULL), or a supersession inherits the scope of an agreement whose Club has since become `sunset`
- **THEN** it is admitted (Producer-wide is ungated; supersession is exempt)

#### Scenario: Settlement cadence is a server-enforced closed set

- **WHEN** a ProducerAgreement is created with a settlement cadence of `quarterly`, `monthly`, or `semi-annual`
- **THEN** it is admitted
- **WHEN** a ProducerAgreement is created with an out-of-set cadence (e.g. `annual` or `weekly`)
- **THEN** the creation is rejected server-side, and no agreement is created

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.6 / § 4.6.1 (ProducerAgreement — Producer required, Club optional; born `draft`; settlement-cadence D19 seam) · § 14.6 BR-K-Agreement-2 (settlement cadence) / **BR-K-Agreement-4 (new per-Club agreement requires an `active` Club; supersession inherits scope, exempt)** · § 15.5 (`ProducerAgreementCreated`) · spec/04-decisions/decisions.md DEC-042 (quarterly default, agreement-configurable) / DEC-070 (ProducerAgreement entity) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-8, § 4 **AC-K-BR-Agreement-2 (settlement-cadence override) / AC-K-BR-Agreement-4 (Club-active scoping)**, § 5 AC-K-EVT-9 · canon **MVP-DEC-010** (settlement_cadence closed to `{quarterly, monthly, semi-annual}`, server-enforced; `annual`/sub-monthly excluded) + **MVP-DEC-009** (per-Club scope requires an `active` Club), LIVE `cmless/main` @ `360df0b`, adopted via this change's mini-ADRs `decisions/2026-07-07-adopt-mvp-dec-010-settlement-cadence-closed-set.md` + `decisions/2026-07-07-adopt-mvp-dec-009-agreement-club-active-scope.md` — absent from the frozen `spec/`@MVP-DEC-007 · MODIFIES the *ProducerAgreement* requirement (openspec/specs/party-registry/spec.md — which carried settlement-cadence as a **free-text** "D19 seam" with no closed set, and admitted a per-Club narrowing with **no Club-status gate")._

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

The Profile SHALL **be** the membership in one Club — there SHALL be no separate Membership entity (the Netflix-style Customer↔Profile model). A Profile SHALL belong to **exactly one** Customer and **exactly one** Club, both required at creation. A single Customer MAY hold **multiple** Profiles across different Clubs, but SHALL hold **at most one non-terminal Profile per Club** (uniqueness on the Customer–Club pair), so a second Profile for a (Customer, Club) pair that already has a live Profile SHALL be rejected. A Profile SHALL be created in the `Applied` state — **or in the `WaitingList` state when the target Club is at its Hero-Package capacity** (canon § 7.1 step 6; per the *WaitingList Placement, Conversion and Decline* requirement) — and SHALL record a `ProfileCreated` domain event on creation, plus a `WaitingListJoined` event when it is born waitlisted. The Customer–Club uniqueness is scoped to non-terminal states (the partial-unique index `(customer_id, club_id) WHERE state NOT IN ('rejected','cancelled','inactive')` excludes the terminal states, so a terminal Profile never blocks a fresh `Applied` Profile for the same pair; `suspended`, `lapsed` and `waiting_list` are **non-terminal** and so still block a second live Profile) — **no index migration is required**, the birth-in-`WaitingList` path reuses it unchanged.

The **target Club SHALL be `active`**: a `CreateProfile` targeting a Club in `sunset` or `closed` SHALL be **rejected** with a localized `ClubNotAcceptingMemberships` exception, and no Profile and no `ProfileCreated` event SHALL be created — enforcing the frozen rule that a `sunset` Club blocks new memberships (BR-K-Club-3 / AC-K-FSM-6, closing the deferral in *Club Lifecycle*). At creation, the Profile's `auto_renew` SHALL default-inherit the Club's `auto_renew_default` (per the *Profile Auto-Renewal Preference* requirement). The Club-status gate SHALL be evaluated **before** the capacity read: a `sunset` Club rejects the application outright rather than waitlisting it.

Because neither `Applied` nor `WaitingList` occupies a Hero-Package seat, the birth-state capacity read **cannot oversell** and SHALL **not** take a Club-row lock; it is a birth-state routing decision, not the invariant's enforcement point (which is membership approval).

#### Scenario: Create a Profile

- **WHEN** an operator creates a Profile for a Customer in an `active` Club with a free Hero-Package seat (or no configured capacity)
- **THEN** it is persisted in `Applied`, referencing exactly one Customer and one Club, with `auto_renew` inherited from the Club default, and a `ProfileCreated` event is recorded

#### Scenario: Create a Profile against a Club at capacity

- **WHEN** an operator creates a Profile for a Customer in an `active` Club that is at its Hero-Package capacity
- **THEN** it is persisted in `WaitingList` (not `Applied`), and both a `ProfileCreated` and a `WaitingListJoined` event are recorded in the writing transaction

#### Scenario: One non-terminal Profile per Customer–Club pair

- **WHEN** a second Profile is created for a (Customer, Club) pair that already has a live Profile
- **THEN** the creation is rejected

#### Scenario: A waitlisted Profile is non-terminal and blocks a second live Profile

- **GIVEN** a Customer whose Profile for Club C is in `WaitingList`
- **WHEN** a second Profile is created for the same Customer–Club pair
- **THEN** the creation is rejected (the partial-unique index treats `waiting_list` as non-terminal, exactly as it treats `suspended` and `lapsed`)

#### Scenario: A Customer may hold Profiles across many Clubs

- **WHEN** a Customer is given Profiles in three different (active) Clubs
- **THEN** all three are created (the multi-profile model), each unique on its own Customer–Club pair

#### Scenario: A terminal Profile does not block a fresh application

- **GIVEN** a Customer whose Profile for Club C is in a terminal state (`cancelled` or `inactive`)
- **WHEN** a new Profile is created for the same Customer–Club pair (C still `active`)
- **THEN** the new Profile is created in `Applied` (the partial-unique index excludes the terminal states), while a `suspended`, `lapsed` or `waiting_list` (non-terminal) Profile for the pair would still block it

#### Scenario: A non-active Club rejects new membership, whatever its capacity

- **WHEN** a `CreateProfile` targets a Club in `sunset` (or `closed`), whether or not that Club is at capacity
- **THEN** a `ClubNotAcceptingMemberships` is raised, and no Profile, no `ProfileCreated` and no `WaitingListJoined` event are created

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§6 — birth-in-`WaitingList` is the first of the two entry points; it carries no invariant weight and needs no lock) · canon `c-mless/documentation` @ `360df0b` Module_K_PRD § 7.1 step 6 (`:399` — *"each application creates a Profile in `Applied` state (or `WaitingList` if the target Club is at capacity — §13)"*) · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 3 (the Netflix-style Customer–Profile model) · § 4.2 / § 4.2.1 (Profile born `Applied`) · § 7.1:392 · § 4.3 (**sunset blocks new memberships**) · § 14.1 BR-K-Identity-2 (one Profile per Customer per Club) · § 14.4 **BR-K-Club-3** · § 15.2 (`ProfileCreated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-2, **AC-K-FSM-6**, § 4 AC-K-BR-Identity-2, **AC-K-BR-Club-3**, § 5 AC-K-EVT-5 · database/migrations/2026_06_15_000007_create_parties_profiles_table.php (the partial-unique index, unchanged) · openspec/specs/party-registry/spec.md (*Club Lifecycle*; *Profile Auto-Renewal Preference*; *WaitingList Placement, Conversion and Decline*) · MODIFIES the *Profile — Multi-Profile Membership* requirement (which admitted **only** an `Applied` birth state and applied no capacity read at creation)._

### Requirement: Birth States Recorded, Lifecycle Transitions Deferred

Every Parties entity that carries a lifecycle state SHALL define its full state domain and SHALL be created in its birth state: Customer `pending`, Account `active`, Producer `draft`, Club `active`, ProducerAgreement `draft`, Profile `Applied` — **or `WaitingList` when the target Club is at its Hero-Package capacity** (Supplier carries no lifecycle state). The **supply-side** lifecycle — Producer, ProducerAgreement and Club — SHALL implement its state transitions and emit its lifecycle events, as governed by the Requirements *Producer Lifecycle*, *ProducerAgreement Lifecycle*, *Club Lifecycle* and *Supply-Side Lifecycle Events*. The **Customer and Producer compliance-screening lifecycles** — the KYC FSM and the Customer sanctions FSM, **each separate from the Customer/Producer status FSM** — SHALL be implemented as governed by the Requirements *Customer KYC Lifecycle*, *Customer Sanctions Screening Lifecycle*, *Producer KYC Lifecycle* and *Sanctions Screening Events*; their fields are added additively (nullable — DEC-071). The **demand-side** status lifecycle is now **fully implemented** across activation and suspension. Activation (the Requirements *Customer Onboarding Activation*, *Profile Membership Approval*, *Profile Activation* and *Demand-Side Activation Events*): Customer `pending → active`; **Profile membership approval is the atomic approve = charge = activation** — `ApproveProfile` drives `Applied → Approved → Active` (and equally `WaitingList → Approved → Active`) in **one operation**, `Approved` a **transient** pass-through never durably rested-in (canon MVP-DEC-016) — plus `Applied | WaitingList → Rejected` decline and the Originating-Club one-shot lock — emitting `CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked` (approval and decline audit-only — § 15.2 names no `ProfileApproved` / `ProfileRejected`). The **Hero-Package seat gate is now implemented** (the Requirements *Hero Package Capacity Invariant*, *WaitingList Placement, Conversion and Decline* and *Hero Package Capacity Is Read from Module A, Never Stored in Module K*): the seat-occupying set is `Active` + `Suspended`, evaluated under a **`parties_clubs` row lock** at the atomic approve instant and at grace renewal, never at `Suspended → Active` and never at `Approved → Active`; the `WaitingList` state is reachable at both canon entry points and records `WaitingListJoined`; conversion off the waitlist is the Producer's **manual** approval and **nothing auto-promotes, on any trigger**; and the capacity number itself is **never stored in Module K** — it is read through a K-owned port whose launch adapter is config-backed (canon MVP-DEC-011 / MVP-DEC-017 / MVP-DEC-020). The **charge-on-approval capture** (Module S's `MembershipFeePaid`, INV1 / no INV0 — DEC-173 / DEC-157) remains a deferred seam. Suspension and the remaining status edges (the Requirements *Profile Suspension and Restoration*, *Profile Lapse and Grace Renewal*, *Profile Cancellation and Deactivation*, *Customer Suspension and Closure*, *Account Status Lifecycle*, *Hold-Driven Status Coupling* and *Demand-Side Status Events*): Profile `Active → Suspended | Lapsed | Cancelled | Inactive` and `Lapsed → Active` grace (**now capacity-gated**), Customer `active → suspended | closed` (suspension cascading to the Customer's Profiles), and Account `active → suspended → closed` — emitting `CustomerSuspended` / `CustomerReactivated` / `CustomerClosed` and `ProfileSuspended` / `ProfileReactivated` / `ProfileExpired` / `ProfileRenewed` / `ProfileInactive` (Account transitions and Profile cancellation are **audit-only** — § 15 names no Account event and the § 15.2 family names no `ProfileCancelled`). The **Hold→`suspended` status coupling** is implemented (the *Hold-Driven Status Coupling* requirement): placing a Hold drives every covered scope in its suspendable from-state to `suspended`, and lifting a Hold restores a covered scope **iff no other active Hold** still covers it (ADR 2026-06-19) — and because a `Suspended` Profile **keeps its seat**, restoration is never capacity-blocked; the unified Hold registry and the `kyc` Hold compliance coupling remain as governed by the Requirements *Hold Registry*, *Hold Lifecycle and Lift Discipline*, *Hold Events* and *Hold and Sanctions Read-API*. The **Club Credit** instrument is implemented as a Module K entity (the Requirements *Club Credit Entity and One-Active-Per-Profile Invariant*, *Club Credit Issuance*, *Club Credit Redemption and Carry-Forward*, *Club Credit Forfeiture and Restoration* and *Club Credit State Recording Is Module-E-Owned*): a per-Profile prepayment instrument created `active` on issuance, with the FSM `active → redeemed | forfeited` driven by the within-module writers `IssueClubCredit` / `ApplyClubCredit` (K.17 carry-forward) / `ForfeitClubCredit` / `RestoreClubCredit`, the structural one-active-per-Profile invariant, and the freeze-while-suspended guarantee. These writers are **audit-only** — § 11.4 makes the `ClubCreditIssued` / `ClubCreditApplied` / `ClubCreditRestored` / `ClubCreditForfeited` lifecycle events **Module E's**, so Module K records state and emits no Club Credit event; the `MembershipFeePaid` trigger is **Module S's** (Module E records; Module K consumes — DEC-173 / MVP-DEC-016). The Club Credit **cross-module triggers** remain deferred seams: the Module-S `MembershipFeePaid` listener + `ClubCredit*` consumers (Phase 6), the Module-S checkout redemption and the Club-closure → Discovery store-credit conversion (DEC-043), the year-end-lapse scheduler, and the Profile-cancellation → forfeit cascade.

Exactly **one** demand-side **status** seam SHALL remain deferred: **Customer-segment derivation** (and its `CustomerSegmentChanged` event) — until the follow-on change `parties-customer-segments` implements it. `ActivateAccount` SHALL NOT exist (the Account is born `active`; its only `→ active` edge is the restore `ReactivateAccount`).

Four **capacity** sub-behaviours SHALL remain deferred, each blocked outside Module K, and SHALL NOT be implemented by inventing a Module-K surface for them: the **mid-year capacity increase** signal (`AllocationCapacityIncreased`, Module A §5.3.4 — the waitlist-*conversion* half does ship); the **capacity-decrease seat floor** (executed on Module A's surface); the **grandfathered period rollover** of an `Active` Profile into a new club year (`valid_to` and a rollover Action exist nowhere in Module K — adding them would invent schema canon never names); and the **Hero-Package Offer SKU shapes** (Module 0 / Module S). Module K SHALL likewise **not** publish a cross-module seat-occupancy contract until a consumer exists.

#### Scenario: Each entity is born in its birth state

- **WHEN** a Customer, Account, Producer, Club, ProducerAgreement or Profile is created
- **THEN** its state is, respectively, `pending`, `active`, `draft`, `active`, `draft`, and `Applied` — or `WaitingList` when the Profile's target Club is at its Hero-Package capacity

#### Scenario: The capacity invariant and the WaitingList path now exist; only the segment seam remains

- **WHEN** the Parties code surface is inspected
- **THEN** Producer, ProducerAgreement and Club expose lifecycle-transition operations and record their lifecycle events; the Customer/Producer KYC and Customer sanctions screening FSMs expose their transitions; the unified Hold registry exposes place/lift with the `kyc` Hold auto-placed on KYC `pending` and auto-lifted on `verified`; the demand-side **activation** transitions exist (Customer `pending → active` via `ActivateCustomer`; Profile membership approval `ApproveProfile` drives `Applied → Approved → Active` **atomically**, and equally `WaitingList → Approved → Active`, plus `Applied | WaitingList → Rejected` via `DeclineProfile`, with the Originating-Club one-shot lock); AND the demand-side **status** transitions exist — Profile `Active → Suspended | Lapsed | Cancelled | Inactive` and `Lapsed → Active` grace, Customer `active → suspended | closed` (cascading to Profiles), Account `active → suspended → closed`
- **AND** the **Hero Package Capacity Invariant exists**: the seat-occupying set is `Active` + `Suspended`, gated under a `parties_clubs` row lock at membership approval, waitlist conversion and grace renewal — and **never** at `Suspended → Active` or `Approved → Active`
- **AND** the `Applied → WaitingList` path exists at **both** canon entry points (birth at application, divert at approval) and a `WaitingListJoined` event is recordable at each; `WaitingList → Approved` conversion and `WaitingList → Rejected` decline exist; and **no** listener, scheduler, job or observer promotes a Profile off the waitlist
- **AND** Customer-segment derivation does **not** exist; no `CustomerSegmentChanged` event is recordable; and no `ActivateAccount` Action exists

#### Scenario: Module K stores no capacity and publishes no seat count

- **WHEN** the Module K entity schemas and the Parties `Contracts` namespace are inspected
- **THEN** no table carries a capacity attribute, no capacity read-model exists, and the capacity is obtained through a Module-K-owned read port with a config-backed launch adapter
- **AND** no seat-occupancy reader contract is published cross-module, and no Module A model, table or event is imported anywhere in Module K

#### Scenario: The four capacity sub-behaviours stay deferred

- **WHEN** the Parties code surface is inspected
- **THEN** there is **no** consumer of a Module-A capacity-increase signal, **no** capacity-decrease surface, **no** `valid_to` column or period-rollover Action on the Profile, and **no** Hero-Package Offer or SKU-shape surface — each blocked on Module A, Module 0 or Module S

#### Scenario: The Club Credit entity and its within-module FSM exist, audit-only

- **WHEN** the Parties code surface is inspected
- **THEN** the Club Credit entity exists with the writers `IssueClubCredit` / `ApplyClubCredit` / `ForfeitClubCredit` / `RestoreClubCredit` driving `active → redeemed | forfeited` under the one-active-per-Profile invariant, redemption frozen while the Profile is suspended
- **AND** Module K records Club Credit state with **no** `ClubCredit*` domain event of its own and **no** fabricated `MembershipFeePaid` / `ClubCredit*` event class (the § 11.4 ownership boundary; `MembershipFeePaid` is Module S's per DEC-173)
- **AND** the Club Credit cross-module triggers — the Module-S `MembershipFeePaid` listener and `ClubCredit*` consumers, the Module-S checkout redemption and DEC-043 closure conversion, the year-end scheduler, and the Profile-cancellation cascade — do **not** exist (deferred seams)

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (**RM-05** — closes the capacity + `WaitingList` seams against a **documented subset**; the four carve-outs are named with their blockers) · decisions/2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval.md (RM-03) · canon `c-mless/documentation` @ `360df0b` MVP_Decisions_Register_v0.1.md:136 / :142 / :145 (MVP-DEC-011 / 017 / 020) + :147 (MVP-DEC-022 (1) — no auto-FIFO) · canon issue #1 (*"shrink by attrition + no-backfill"*) · canon Module_K_Acceptance **AC-K-J-13** / **AC-K-FSM-2** / **AC-K-FSM-2a** / **AC-K-EVT-11** / **AC-K-XM-18** / **AC-K-XM-20** (**met**) and **AC-K-J-14** / **AC-K-J-15** / **AC-K-J-15a** / **AC-K-XM-19** (**not met — carved out**) · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 / § 4.2.1 / § 4.3 / § 4.7, § 4.8 / § 4.8.1, § 10.1, § 9.1 / § 9.2, § 11 / § 11.1–11.5, § 13, § 5 (Customer segments — deferred), § 15 · decisions/2026-06-19-hold-status-coupling.md · MODIFIES the *Birth States Recorded, Lifecycle Transitions Deferred* requirement (whose closing sentence declared **three** remaining demand-side seams — *"the Hero Package Capacity Invariant (approval and activation ship **uncapped**)"*, *"the `Applied → WaitingList` path (and its `WaitingListJoined` event)"* and Customer segments — of which this change closes the first two, leaving one)._

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

A Producer in `draft` SHALL transition to `active` on an `ActivateProducer` operation, recording **`ProducerActivated`**. Activation SHALL enforce the **KYC-cleared gate** (§ 4.4; BR-K-Producer-2): the Producer's `kyc_status` SHALL be **cleared** — `verified`, `not_required`, **or NULL** (a Producer never touched by KYC) — and the activation SHALL be **rejected** while `kyc_status` is `pending` or `rejected`, leaving the Producer in `draft` and recording no event. NULL is treated as cleared so the additive KYC field (DEC-071) does not break the activation of Producers created before this change; an operator may explicitly set `not_required` to **waive** KYC (ADR `2026-06-17-producer-kyc-gate-not-required-clears.md`).

`ActivateProducer` SHALL additionally enforce the **separation-of-duties floor** (Admin Panel PRD § 5.2; AC-K-J-10; the resolved distinct-actor floor of `decisions/2026-06-17-approval-separation-of-duties-role-gated.md`). The activating actor SHALL be an **authenticated operator principal** — `actor_role = newco_ops` with a non-null `actor_id` — so a `system`/null actor SHALL be **rejected** with a localized separation-of-duties violation, leaving the Producer in `draft` and recording no event. The activating actor SHALL be a **distinct actor from the Producer's creator** — the `actor_id` recorded on the Producer's `ProducerCreated` event, recovered as the **earliest** append-only domain event for the Producer; an activation whose actor equals the creator (self-approval) SHALL be **rejected** on the separation-of-duties floor. A Producer with **no recoverable creator actor** imposes no creator-distinctness constraint but still requires the operator-principal floor. This is the spec-admissible **2-step Creator → Approver** depth. Both gates SHALL hold: a violation of **either** the KYC-cleared gate or the separation-of-duties floor leaves the Producer in `draft` with no `ProducerActivated` event recorded.

A Producer in `active` SHALL transition to `retired` on a `RetireProducer` operation, recording **`ProducerRetired`**, and SHALL **cascade**: every Club the Producer operates that is currently in `active` SHALL transition to `sunset` (recording its own `ClubSunset`, per the Club Lifecycle requirement) within the same transaction. Clubs already in `sunset` or `closed` SHALL be left unchanged (the cascade is idempotent over already-transitioned Clubs). The cascade SHALL **then perform the Profile leg** of the § 10.2 offboarding: for **every Profile currently in `Active` or `Lapsed` under each sunsetting Club**, `RetireProducer` SHALL drive `CancelProfile` with a **Producer-initiated cancellation reason** (per the *Profile Cancellation and Deactivation* requirement), in the **same transaction** and **after** the corresponding `ClubSunset` (parent-before-child order — AC-K-EVT-20 / § 10.2). Because the model carries **no `Club → Profile` relation**, the walk SHALL query Profiles by the sunsetting Clubs' ids. Profiles in states other than `Active`/`Lapsed` (e.g. `Applied`, `Suspended`) are left to their own lifecycle and are out of this leg. Consistent with frozen § 15.2 (which names **no** `ProfileCancelled`) and § 15.7 (which defers the exact **signal-event** shape to the downstream consumer), the per-Profile cancellation is **audit-only** and emits **no** domain event; the subscribable Module-S signal event and the Club-Credit **conversion math** (DEC-043 / AC-K-XM-23) stay the **deferred Module-S seam** — Module K's role ends at the per-Profile cancellation with its reason.

Every transition SHALL be **from-state guarded**: an `ActivateProducer` on a Producer not in `draft`, or a `RetireProducer` on a Producer not in `active`, SHALL be rejected with a localized `IllegalProducerTransition` and SHALL leave all state and the event log unchanged. The guard SHALL be evaluated against a transaction-locked re-read of the row so concurrent transition attempts cannot both succeed.

#### Scenario: Activate a draft Producer

- **GIVEN** a Producer in `draft` whose `kyc_status` is cleared (`verified`, `not_required`, or NULL), created by one operator
- **WHEN** a **distinct** authenticated `newco_ops` operator invokes `ActivateProducer`
- **THEN** the Producer's status becomes `active` and a `ProducerActivated` event is recorded in the same transaction (module `parties`, entityType `Producer`, PII-free payload)

#### Scenario: Self-approval by the Producer's creator is rejected

- **GIVEN** a Producer in `draft` with KYC cleared, created by operator A
- **WHEN** operator A invokes `ActivateProducer` on that Producer
- **THEN** the activation is rejected on the separation-of-duties floor, the Producer stays `draft`, and no `ProducerActivated` event is recorded

#### Scenario: A system or unauthenticated actor cannot activate

- **WHEN** `ActivateProducer` is invoked in a `system`/unauthenticated context (no `newco_ops` operator principal) on a `draft` Producer with KYC cleared
- **THEN** the activation is rejected — the distinct-actor floor cannot be satisfied without an operator principal — the Producer stays `draft`, and no `ProducerActivated` event is recorded

#### Scenario: Activation requires KYC cleared

- **WHEN** a distinct authenticated operator invokes `ActivateProducer` on a `draft` Producer whose `kyc_status` is `verified`, `not_required`, or NULL
- **THEN** the activation succeeds and `ProducerActivated` is recorded
- **WHEN** a distinct authenticated operator invokes `ActivateProducer` on a `draft` Producer whose `kyc_status` is `pending` or `rejected`
- **THEN** the activation is rejected, the Producer stays `draft`, and no `ProducerActivated` event is recorded

#### Scenario: Retire an active Producer cascades Club sunset

- **GIVEN** a Producer in `active` that operates two Clubs in `active` and one Club already in `closed`
- **WHEN** `RetireProducer` is invoked
- **THEN** the Producer's status becomes `retired` and a `ProducerRetired` event is recorded
- **AND** the two `active` Clubs transition to `sunset`, each recording a `ClubSunset` caused by the retirement, while the `closed` Club is left unchanged

#### Scenario: Retirement cascades per-Profile cancellation under sunsetting Clubs

- **GIVEN** a Producer in `active` operating a Club in `active` that has two Profiles in `Active`, one in `Lapsed`, and one already in `Cancelled`
- **WHEN** `RetireProducer` is invoked
- **THEN** the Producer becomes `retired` (`ProducerRetired`), the Club becomes `sunset` (`ClubSunset`), AND each of the two `Active` Profiles and the one `Lapsed` Profile becomes `Cancelled` carrying a Producer-initiated cancellation reason (audit-only — **no** `ProfileCancelled` event), while the already-`Cancelled` Profile is unchanged
- **AND** the per-Profile cancellations are recorded **after** the `ClubSunset` in the same transaction (parent-before-child)

#### Scenario: Illegal Producer transitions are rejected

- **WHEN** `ActivateProducer` is invoked on a Producer not in `draft`, or `RetireProducer` on a Producer not in `active`
- **THEN** an `IllegalProducerTransition` is raised, the Producer's status is unchanged, and no `ProducerActivated` / `ProducerRetired` (and no cascade `ClubSunset` or per-Profile cancellation) is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.4 (Producer FSM; KYC-cleared gate; content-approval workflow) · § 10.2 (Producer offboarding cascade → Club sunset → **per-Profile cancellation with a Producer-initiated reason**; Module K's role ends at the upstream signal) · § 14.5 BR-K-Producer-2/4 · § 15.2 (the Profile family names **no `ProfileCancelled`**) · § 15.4 (`ProducerActivated`, `ProducerRetired`) · § 15.7 (the per-Profile cancellation **signal-event shape** is a deferred downstream-consumer concern) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md § 5.2 (the SoD discipline) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-7 (KYC gate), § 2 AC-K-J-10 (SoD), **AC-K-J-19 (offboarding cascade — one per-Profile cancellation signal per Profile)**, § 5 AC-K-EVT-8, **AC-K-EVT-14 (per-Profile producer-initiated cancellation signal)**, **AC-K-EVT-20 (parent-before-child cascade order)**, § 6 **AC-K-XM-23 (Module K stops at the signal; no Club-Credit conversion math)** · decisions/2026-06-17-approval-separation-of-duties-role-gated.md · decisions/2026-06-17-producer-kyc-gate-not-required-clears.md · decisions/2026-06-12-event-substrate-and-audit-store.md · openspec/specs/party-registry/spec.md (*Profile Cancellation and Deactivation* — the `CancelProfile` transition the cascade drives) · CLAUDE.md invariants 8 & 10 · MODIFIES the *Producer Lifecycle* requirement (openspec/specs/party-registry/spec.md — which deferred the **Profile leg** of the § 10.2 offboarding cascade: "The Profile leg … SHALL NOT be performed by this change — it is deferred with Profile lifecycle")._

### Requirement: ProducerAgreement Lifecycle

The ProducerAgreement SHALL transition through its state machine `draft → active → superseded | terminated` via explicit operator Actions that are the sole writers of `ProducerAgreement.status`, each recording its lifecycle event in the same database transaction as the state write.

A ProducerAgreement in `draft` SHALL transition to `active` on an `ActivateProducerAgreement` operation, recording **`ProducerAgreementActivated`**. Activation SHALL enforce **BR-K-Agreement-1** at two levels. **(1) Same-scope supersession:** the **scope** is the `(producer_id, club_id)` tuple, where a `NULL` `club_id` denotes the distinct Producer-wide scope; if an `active` agreement already exists in the **same** scope as the agreement being activated, that prior agreement SHALL transition `active → superseded` in the same transaction, recording **`ProducerAgreementSuperseded`**, and the audit SHALL pair the two (each event payload references the other). **(2) Cross-shape mutual exclusion (BR-K-Agreement-1 clause 2):** the Producer-wide and per-Club shapes are **mutually exclusive on the same Producer at the same time** — activating a **Producer-wide** agreement while **any** per-Club agreement of that Producer is `active`, or activating a **per-Club** agreement while that Producer's **Producer-wide** agreement is `active`, SHALL be **rejected** with a localized `ProducerAgreementScopeConflict`, leaving all state and the event log unchanged (the operator SHALL first terminate/supersede the existing-shape agreement). The same-scope prior-active lookup SHALL be NULL-safe (a Producer-wide activation's supersession matches only other `NULL`-`club_id` agreements of that Producer; the cross-shape check inspects the opposite shape).

A ProducerAgreement in `active` SHALL transition to `terminated` on a `TerminateProducerAgreement` operation, recording **`ProducerAgreementTerminated`**. Termination SHALL NOT cascade to any Producer-level state change (§ 4.6.1).

Every transition SHALL be **from-state guarded** against a transaction-locked re-read: an `ActivateProducerAgreement` on an agreement not in `draft`, or a `TerminateProducerAgreement` on an agreement not in `active`, SHALL be rejected with a localized `IllegalProducerAgreementTransition` and SHALL leave all state and the event log unchanged.

#### Scenario: Activate a draft agreement with no prior active in scope

- **WHEN** `ActivateProducerAgreement` is invoked on a `draft` agreement and no `active` agreement exists in its `(producer_id, club_id)` scope and no `active` agreement of the opposite shape exists for the Producer
- **THEN** the agreement's status becomes `active`, a `ProducerAgreementActivated` event is recorded, and no `ProducerAgreementSuperseded` event is recorded

#### Scenario: Activating a replacement supersedes the prior active in the same scope

- **GIVEN** an `active` ProducerAgreement A for a Producer (Producer-wide, `club_id` NULL)
- **WHEN** a second `draft` agreement B for the same Producer (also `club_id` NULL) is activated
- **THEN** A transitions to `superseded` recording `ProducerAgreementSuperseded`, B transitions to `active` recording `ProducerAgreementActivated`, and the two events pair old + new in their payloads

#### Scenario: Producer-wide and Club-narrowed shapes are mutually exclusive

- **GIVEN** an `active` Producer-wide agreement (`club_id` NULL) for a Producer
- **WHEN** a `draft` Club-narrowed agreement (`club_id = C`) for the same Producer is activated
- **THEN** the activation is rejected with a `ProducerAgreementScopeConflict`, the Producer-wide agreement stays `active`, the Club-narrowed agreement stays `draft`, and no event is recorded
- **GIVEN** an `active` Club-narrowed agreement for a Producer
- **WHEN** a `draft` Producer-wide agreement for the same Producer is activated
- **THEN** the activation is likewise rejected with a `ProducerAgreementScopeConflict`, leaving both agreements and the event log unchanged

#### Scenario: Terminate an active agreement without cascading

- **WHEN** `TerminateProducerAgreement` is invoked on an `active` agreement
- **THEN** the agreement's status becomes `terminated`, a `ProducerAgreementTerminated` event is recorded, and the Producer's state is unchanged

#### Scenario: Illegal ProducerAgreement transitions are rejected

- **WHEN** `ActivateProducerAgreement` is invoked on an agreement not in `draft`, or `TerminateProducerAgreement` on an agreement not in `active`
- **THEN** an `IllegalProducerAgreementTransition` is raised, no status changes, and no agreement lifecycle event is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.6 / § 4.6.1 (ProducerAgreement FSM; supersession pairs old + new; termination does not cascade) · § 14.6 **BR-K-Agreement-1** (at most one active per Producer scope — clause 1 same-scope supersession; **clause 2: "Multi-Club Producers may have either Producer-wide or per-Club scoping; the two shapes are mutually exclusive on the same Producer at the same time"**) · § 15.5 (the three agreement events) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-8, § 2 AC-K-J-11 / AC-K-J-12, § 4 **AC-K-BR-Agreement-1 ("attempt Producer-wide + per-Club both active on same Producer, assert rejection")**, § 5 AC-K-EVT-9 · AskUserQuestion 2026-06-15 (scope = `(producer_id, club_id)`, NULL `club_id` a distinct Producer-wide scope) · MODIFIES the *ProducerAgreement Lifecycle* requirement (openspec/specs/party-registry/spec.md — which enforced only clause 1 and asserted the **opposite** of clause 2: "a Producer-wide agreement and a Club-narrowed agreement therefore occupy different scopes and MAY both be `active`", with a "Scope isolation … MAY both be active" scenario now replaced)._

### Requirement: Club Lifecycle

The Club SHALL transition through its state machine `active → sunset → closed` via explicit operator Actions that are the sole writers of `Club.status`, each recording its lifecycle event in the same database transaction as the state write.

A Club in `active` SHALL transition to `sunset` on a `SunsetClub` operation, recording **`ClubSunset`**. `SunsetClub` SHALL be the single writer of `ClubSunset` — invoked both as a standalone operator action and as the per-Club step of the Producer-retirement cascade (Producer Lifecycle requirement). Sunset blocks new memberships and new offers while preserving existing Profiles (§ 4.3); the **new-membership block is now enforced** at the membership-creation surface — a Profile SHALL NOT be created against a `sunset` (or `closed`) Club, per the *Profile — Multi-Profile Membership* requirement (BR-K-Club-3 / AC-K-FSM-6). The new-**offer** block remains a downstream (Module S) concern, not part of this transition.

A Club in `sunset` SHALL transition to `closed` on a `CloseClub` operation, recording **`ClubClosed`**. The PRD precondition that closure occurs only once all members have migrated or expired (§ 4.3) reads Profile state; `CloseClub` SHALL implement the transition without enforcing an all-members-gone gate — the demand-side tightening of that gate is a **deferred seam** (unchanged by this change).

Every transition SHALL be **from-state guarded** against a transaction-locked re-read: a `SunsetClub` on a Club not in `active`, or a `CloseClub` on a Club not in `sunset` (including an attempt to close an `active` Club directly), SHALL be rejected with a localized `IllegalClubTransition` and SHALL leave all state and the event log unchanged.

#### Scenario: Sunset an active Club

- **WHEN** `SunsetClub` is invoked on a Club in `active`
- **THEN** the Club's status becomes `sunset` and a `ClubSunset` event is recorded in the same transaction

#### Scenario: Close a sunset Club

- **WHEN** `CloseClub` is invoked on a Club in `sunset`
- **THEN** the Club's status becomes `closed` and a `ClubClosed` event is recorded — no all-members-gone precondition is enforced in this slice (the gate is a deferred seam)

#### Scenario: A sunset or closed Club blocks new membership creation

- **WHEN** a `CreateProfile` targets a Club in `sunset` or `closed`
- **THEN** the membership creation is rejected (per *Profile — Multi-Profile Membership*), while existing Profiles under that Club are preserved

#### Scenario: Illegal Club transitions are rejected

- **WHEN** `SunsetClub` is invoked on a Club not in `active`, or `CloseClub` on a Club not in `sunset` (e.g. an `active` Club)
- **THEN** an `IllegalClubTransition` is raised, the Club's status is unchanged, and no `ClubSunset` / `ClubClosed` event is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.3 (Club FSM `active → sunset → closed`; **sunset blocks new memberships/offers**, preserves Profiles; closed terminal) · § 10.2 (sunset is the per-Club leg of Producer retirement) · § 14.4 **BR-K-Club-3 (sunset blocks new memberships)** · § 15.3 (`ClubSunset`, `ClubClosed`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 **AC-K-FSM-6 (sunset blocks new memberships)**, § 4 **AC-K-BR-Club-3 ("verify new-membership creation rejected when Club is `sunset`")**, § 2 AC-K-J-19 · AskUserQuestion 2026-06-15 (CloseClub included; all-members-gone gate deferred) · openspec/specs/party-registry/spec.md (*Profile — Multi-Profile Membership* — where the new-membership block is enforced) · MODIFIES the *Club Lifecycle* requirement (openspec/specs/party-registry/spec.md — which deferred the block: "enforcement of those blocks at the membership/offer surfaces is a downstream concern, not part of this transition")._

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

The enhanced-KYC trigger flag + timestamp SHALL exist as additive nullable fields recording whether the Customer crossed the €10,000-single / €50,000-cumulative threshold. The **detection** of that crossing is implemented by the *Enhanced-KYC Threshold Detection* requirement: a periodic scan (and a deferred at-order-completion trigger) reads the Customer's transaction totals through the `CustomerTransactionTotalsReader` contract — null-bound until Module S provides the data — and, on the first crossing, SHALL set `enhanced_kyc_flag` + `enhanced_kyc_at`, raise a Compliance review-queue entry and initiate the AML-threshold re-screen. **Setting the flag SHALL be orthogonal to `kyc_status`** — it does not, by itself, move the KYC FSM (enhanced review is handled operationally, § 9.1, with no enhanced-KYC state machine).

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

#### Scenario: Enhanced-KYC detection sets the flag on a threshold crossing

- **WHEN** a Customer's transaction totals cross €10,000 in a single transaction or €50,000 rolling trailing-12-month cumulative, and `enhanced_kyc_flag` is not set
- **THEN** the *Enhanced-KYC Threshold Detection* workflow sets `enhanced_kyc_flag` and stamps `enhanced_kyc_at`, and `kyc_status` is unchanged (the flag is orthogonal to the KYC FSM)

#### Scenario: Illegal KYC transitions are rejected

- **WHEN** `RecordKycVerified` or `RecordKycRejected` is invoked on a Customer whose `kyc_status` is not `pending`
- **THEN** an `IllegalKycTransition` is raised, `kyc_status` is unchanged, and no `kyc` Hold is placed or lifted

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer KYC state + `kyc_required` flag + enhanced-KYC trigger fields), § 9.1 (KYC four-state lifecycle; `not_required` default; setting `kyc_required` → `pending`; `pending` auto-places the `kyc` Hold, `verified` auto-lifts it, `rejected` leaves it; cleared = `verified` ∨ `not_required`), § 4.8 / § 4.8.1 (the `kyc` Hold — auto-place/auto-lift coupling; DEC-160), § 15.1 (no KYC event family; `CustomerHoldPlaced`/`CustomerHoldLifted`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-3 (KYC FSM separate; the `kyc` Hold auto-places on `pending` and auto-lifts on `verified`), AC-K-J-7 (KYC required → Hold blocks → verified → Hold lifts → purchases resume), AC-K-J-7a (enhanced-KYC trigger fields + detection) · spec/04-decisions/decisions.md DEC-071, DEC-035, DEC-160 · decisions/2026-06-18-hold-lift-discipline-per-type.md (the `kyc` auto-lift is the system path; operator lift of a `kyc` Hold is rejected) · decisions/2026-06-12-event-substrate-and-audit-store.md (audit trail; transactional Hold events). The enhanced-KYC detection (AC-K-J-7a) is implemented by the *Enhanced-KYC Threshold Detection* requirement._

### Requirement: Customer Sanctions Screening Lifecycle

The Customer SHALL carry a **sanctions-screening lifecycle that is separate from both the Customer status FSM and the KYC FSM, and independent of KYC**: the four-state domain `pending → passed | failed | under_review` plus `under_review → passed | failed`, held in additive nullable fields (DEC-071) — `sanctions_status`, `last_screening_at`, `next_rescreen_at`, and the screening `trigger_source` (`onboarding | cadence | aml_threshold | compliance_ad_hoc`). A NULL `sanctions_status` denotes a Customer created un-screened and SHALL be treated, for any downstream purchase gate, as not-`passed` (blocked) — exactly like `pending`.

An explicit operator Action SHALL record each screening verdict (manual-first — the screen is the floor, the vendor integration is deferrable): it SHALL set `sanctions_status` to the verdict, stamp `last_screening_at`, set `next_rescreen_at` to the 12-month-forward moment, record the `trigger_source`, and (on a `passed`/`failed` completion) record the matching screening event per the *Sanctions Screening Events* requirement. A verdict carrying `trigger_source = onboarding` SHALL be the Customer's **first** screening (rejected with `IllegalSanctionsTransition` if `last_screening_at` is already set); every other `trigger_source` denotes a **re-screen**.

The sanctions lifecycle SHALL be **independent of KYC**: a sanctions transition SHALL NOT change `kyc_status` and a KYC transition SHALL NOT change `sanctions_status`; the two clear independently. The **enforcement** of `sanctions_status = passed` as a purchase precondition is **Module S's** at order completion (Module K is sanctions-blind by design) and is NOT in this change.

The **AML-threshold re-screen** is now implemented (per the *Enhanced-KYC Threshold Detection* requirement): on a €10k-single / €50k-cumulative breach the detection SHALL record an **`under_review`** verdict with `trigger_source = aml_threshold` through this requirement's sole screening-writer Action — a **lightweight re-screen** that records **no** completion event at initiation (`under_review` is not a completion) and **blocks the Customer from transacting** until Compliance resolves it; the resolution records the matching `CustomerRescreening{Passed,Failed}` (the same outcome events as the cadence path). The **automated 12-month re-screen cadence** (the daily background job reading `next_rescreen_at`) remains **deferred** (manual-first); the operator ad-hoc re-screen Action, the four events, the `trigger_source` field and the `next_rescreen_at` field continue to ship.

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

#### Scenario: An AML-threshold breach initiates an under_review re-screen tagged aml_threshold

- **WHEN** the *Enhanced-KYC Threshold Detection* workflow escalates a Customer on a threshold breach
- **THEN** the Customer's `sanctions_status` becomes `under_review` with `trigger_source = aml_threshold` (recorded through the sole screening-writer), no completion screening event is recorded at initiation, and the Customer is blocked from transacting until an operator resolves the re-screen — whose `passed`/`failed` resolution records the matching `CustomerRescreening{Passed,Failed}` event

#### Scenario: The 12-month cadence job remains deferred

- **WHEN** the Parties code surface is inspected
- **THEN** `last_screening_at`, `next_rescreen_at` and `trigger_source` exist and both the AML-threshold re-screen and the operator ad-hoc path operate, but there is no daily cadence job reading `next_rescreen_at` in this change (a documented seam)

#### Scenario: An onboarding screening on an already-screened Customer is rejected

- **WHEN** a verdict with `trigger_source = onboarding` is recorded for a Customer whose `last_screening_at` is already set
- **THEN** an `IllegalSanctionsTransition` is raised and the sanctions state is unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer sanctions state, last-screening moment, next re-screen), § 9.2 (sanctions four-state lifecycle; EU+UIF+OFAC; 12-month cadence as a daily job; between-cycle trigger paths and trigger sources — DEC-030/DEC-035), § 9.3 (the order-completion gate is Module S — the single enforcement point; a Customer can exist `sanctions_status = pending`), § 9.4 (KYC and sanctions independent — both clear independently), § 9.5 (manual-first launch posture; the screen + gate are the floor, the integration is deferrable; acceptance drives state, not a live vendor) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-4 (sanctions FSM separate; `pending → passed/failed/under_review`, `under_review → passed/failed`; re-screening fires the events), AC-K-FSM-5 (KYC and sanctions independent), AC-K-EVT-12 (onboarding + rescreening events drive state), AC-K-EVT-12a (AML-threshold breach → lightweight re-screen; trigger source recorded) · spec/04-decisions/decisions.md DEC-071, DEC-030, DEC-041. The order-completion enforcement (AC-K-J-20) is Module S's; the AML-threshold re-screen is implemented by the *Enhanced-KYC Threshold Detection* requirement, while the 12-month cadence job remains deferred._

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

The Parties module SHALL provide a **unified, trigger-agnostic Hold registry** — the single account-restriction primitive that gates commercial activity. A Hold SHALL carry: a `hold_type` from the **eight-value** domain `admin | kyc | payment | fraud | compliance | credit | chargeback_review | storage_payment_failed` — the six § 4.8 base types plus the two finance-driven types § 4.8.1/§ 15.8 name and Module K consumes from Module E, `chargeback_review` (DEC-168) and `storage_payment_failed` (DEC-160), completed to eight first-class coordinate values by canon MVP-DEC-008 (ADR `2026-07-01-adopt-dec-008-hold-types-8.md`); a **scope** comprising a `scope_type` from `customer | account | profile` and a `scope_id` (the id of the scoped Customer, Account or Profile — a within-module reference); a `status` from `active | lifted` (born `active`); an optional placement `reason`; the placement actor (role + id, from the `ActorContext` seam) and the placement moment; and, once lifted, the lift actor + lift moment + an optional `lift_reason`. The Hold SHALL be a new `parties_holds` table (the module-table-prefix convention) added by a single **additive** migration; its value-set columns SHALL carry the layered enforcement idiom (a string column + the backed-enum cast on both engines, plus a PostgreSQL-only `CHECK` deriving from `Enum::cases()`).

A scope MAY carry **multiple concurrent `active` Holds** (any one of which blocks the activity it gates — the blocking is the downstream surface's, per the *Hold and Sanctions Read-API* requirement). Module K SHALL be the **registry-of-record** for every Hold, and the registry SHALL be **trigger-agnostic**: it records the type and state of a Hold regardless of how the placement was triggered, and a **manual operator-placement path** SHALL exist for every type (the automatic triggers for `payment`/`fraud`/`compliance`/`credit` and for the two finance-driven types `chargeback_review`/`storage_payment_failed` are Module E/S signals deferred to those modules; the registry is unchanged by their automation depth — AC-K-MVP-2). The `hold_type` enum SHALL expose an `autoLiftable(): bool` predicate that is true for `kyc` and `payment` only (consumed by the *Hold Lifecycle and Lift Discipline* requirement).

#### Scenario: The Hold entity carries its type, scope, status and audit metadata

- **WHEN** a Hold is placed on a scope with a type
- **THEN** a `parties_holds` row persists carrying the `hold_type`, the `scope_type` + `scope_id`, `status = active`, the placement actor (role + id) and the placement moment, and (until lifted) a null lift actor / lift moment

#### Scenario: The eight Hold types and the three scopes are the domain

- **WHEN** the `HoldType` and `HoldScope` enums are inspected
- **THEN** `HoldType` is exactly `admin | kyc | payment | fraud | compliance | credit | chargeback_review | storage_payment_failed` and `HoldScope` is exactly `customer | account | profile`, each a string-backed enum whose `->value` is the spec token
- **AND** `HoldType::autoLiftable()` is true for `kyc` and `payment` and false for `admin`, `fraud`, `compliance`, `credit`, `chargeback_review`, `storage_payment_failed`

#### Scenario: Multiple concurrent Holds may exist on one scope

- **WHEN** a `kyc` Hold and an `admin` Hold are both placed on one Customer
- **THEN** both are recorded `active` on that Customer scope concurrently (the scope is not single-Hold)

#### Scenario: The registry is trigger-agnostic with a manual-placement path for every type

- **WHEN** an operator manually places a Hold of any of the eight types
- **THEN** the Hold is recorded identically regardless of type — Module K is the registry-of-record, and no automatic upstream trigger is required for the record to exist

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.8 (the unified Hold entity — three scopes Customer/Account/Profile, placement/lift audit metadata; Module K registry-of-record), § 4.8.1 (multiple concurrent Holds; trigger-agnostic; names the two finance-driven types), § 15.8 (the consumed Module-E events `CustomerChargebackFlagged`/`StoragePaymentFailed` that create the two finance-driven Holds) · § 14.8 BR-K-Hold-1 (multiple Holds, any blocks) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-10 (Hold lifecycle — **eight** types × three scopes, audit metadata), AC-K-EVT-18/19 (Module K consumes `CustomerChargebackFlagged`→`chargeback_review` Hold, `StoragePaymentFailed`→`storage_payment_failed` Hold), AC-K-BR-Hold-1 (multiple concurrent Holds), AC-K-MVP-2 (trigger-agnostic registry; manual-placement path) · canon c-mless/documentation MVP_Decisions_Register_v0.1.md:133 (**MVP-DEC-008** — Hold-type enum completed to 8, Q-AD-3 Option B, Paolo-ratified; the two finance-driven types are 8 first-class coordinate values, not sub-types) · spec/04-decisions/decisions.md DEC-181 (uniformity), DEC-160 (per-type lift — the `autoLiftable` partition; `storage_payment_failed`), DEC-168 (K is registry-of-record; `chargeback_review`), DEC-071 (additive nullable field pattern) · decisions/2026-07-01-adopt-dec-008-hold-types-8.md (local adoption of DEC-008 — the eight-value domain, both new types operator-lift-only), decisions/2026-06-18-hold-lift-discipline-per-type.md · decisions/2026-06-12-production-db-engine.md (Postgres-truthful migration; CHECK-from-cases idiom)._

### Requirement: Hold Lifecycle and Lift Discipline

A Hold SHALL be placed and lifted by explicit operator Actions that are the sole writers of `parties_holds`, each running inside one `DB::transaction`. `PlaceHold` SHALL create a Hold (`status = active`) recording the placement actor, moment and optional `reason`, and SHALL record the `CustomerHoldPlaced` event (per the *Hold Events* requirement) in the same transaction. `LiftHold` SHALL re-read the Hold under a transaction lock, set `status = lifted` recording the lift actor, lift moment and optional `lift_reason`, and record `CustomerHoldLifted` in the same transaction.

`LiftHold` SHALL enforce the **per-type lift discipline** (DEC-160; canon MVP-DEC-008; ADR `2026-06-18-hold-lift-discipline-per-type.md`, ADR `2026-07-01-adopt-dec-008-hold-types-8.md`): a Hold whose type is **auto-managed** (`HoldType::autoLiftable()` — `kyc` or `payment`) SHALL NOT be lifted by the operator path and SHALL be **rejected** with a localized `IllegalHoldLift`, because those types are lifted by the system on their clearing signal (the `kyc` auto-lift is wired in this change via the *Customer KYC Lifecycle* requirement; the `payment` auto-lift trigger is a deferred Module-E seam). A Hold of type `admin`, `fraud`, `compliance`, `credit`, `chargeback_review` or `storage_payment_failed` SHALL be lifted freely by the operator path — the two finance-driven types are **operator-lift-only** at launch: `chargeback_review` is resolved by an operator dispute review with no auto-lift signal, and `storage_payment_failed` is manual-first (D4), its `StoragePaymentSucceeded` per-cycle auto-lift a deferred Module-E seam (AC-K-FSM-11; AC-K-EVT-18/19). Lifting a Hold that is not `active` (already `lifted`) SHALL be **rejected** with a localized `IllegalHoldLift`, leaving state and the event log unchanged.

#### Scenario: Place a Hold records the placement actor, reason and moment

- **WHEN** `PlaceHold` is invoked with a type, a scope and a reason
- **THEN** an `active` Hold persists carrying that type, scope, reason, the placement actor (from `ActorContext`) and the placement moment, and a `CustomerHoldPlaced` event is recorded in the same transaction

#### Scenario: An operator lifts an operator-liftable Hold

- **WHEN** `LiftHold` is invoked on an `active` `admin` (resp. `fraud`, `compliance`, `credit`, `chargeback_review`, `storage_payment_failed`) Hold
- **THEN** the Hold's `status` becomes `lifted`, the lift actor + lift moment + `lift_reason` are recorded, and a `CustomerHoldLifted` event is recorded in the same transaction

#### Scenario: Operator-lift of an auto-managed Hold is rejected

- **WHEN** `LiftHold` is invoked on an `active` `kyc` or `payment` Hold (an auto-managed type)
- **THEN** an `IllegalHoldLift` is raised, the Hold stays `active`, and no `CustomerHoldLifted` event is recorded — these types lift only via their system clearing signal

#### Scenario: Lifting an already-lifted Hold is rejected

- **WHEN** `LiftHold` is invoked on a Hold whose `status` is already `lifted`
- **THEN** an `IllegalHoldLift` is raised and state and the event log are unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.8 (place/lift; placement & lift actor/moment recorded), § 4.8.1 (DEC-160 per-type lift discipline — auto-lift permitted on `kyc`/`payment`, operator lift required on `admin`/`fraud`/`compliance`/`credit` and the two finance-driven types `chargeback_review`/`storage_payment_failed`), § 15.8 (the two finance-driven types + their Module-E lift signals) · § 14.8 BR-K-Hold-1 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-10 (place/lift records actor + moment), AC-K-FSM-11 (lift discipline — `kyc`/`payment` auto-lift, the other **six** operator-lift; `storage_payment_failed` per-cycle auto-lift on `StoragePaymentSucceeded` is the deferred Module-E path, `chargeback_review` no-auto-lift), AC-K-EVT-18/19 · canon c-mless/documentation MVP_Decisions_Register_v0.1.md:133 (MVP-DEC-008) · spec/04-decisions/decisions.md DEC-160 (E6-07 per-type lift), DEC-168 (chargeback registry-of-record) · decisions/2026-06-18-hold-lift-discipline-per-type.md, decisions/2026-07-01-adopt-dec-008-hold-types-8.md (both finance-driven types operator-lift-only at launch) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional recording)._

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

The Profile SHALL transition `Applied → Approved → Active`, **and equally `WaitingList → Approved → Active`**, as a **single atomic membership-approval operation** (`ApproveProfile`); and `Applied → Rejected` / `WaitingList → Rejected` (membership decline, `DeclineProfile`) — each the sole writer of the Profile `state` for its transition, each running inside one `DB::transaction` against a transaction-locked re-read of the Profile row. **`Approved` is a transient pass-through state, never a durable resting state** (canon MVP-DEC-016): a Customer is never observably left sitting in `Approved`. These Actions realize **the one retained producer write** ("membership approve/decline", L-PP / K-Q4); the producer-facing HTTP surface is deferred and the Actions are operator/console-invocable at launch (admin-parity, DEC-083). A `WaitingList → Active` conversion is **the same atomic instant** as an approval from `Applied`, subject to the same capacity gate and the same Originating-Club one-shot rule — it is **not** a distinct Action (per the *WaitingList Placement, Conversion and Decline* requirement).

`ApproveProfile` SHALL, in one transaction: acquire a **row-level lock on the Club row** (`parties_clubs`); count the Club's seat occupancy (`Active` + `Suspended`); read the Club's Hero-Package capacity through the Module-K-owned capacity read port (per the *Hero Package Capacity Is Read from Module A, Never Stored in Module K* requirement); and then, **only if a seat is free** (or the capacity is `null`, meaning uncapped): assert the from-state; write `state = Approved`; perform the conditional Originating-Club one-shot lock (below); and drive the `Approved → Active` activation through the within-module `ActivateProfile` writer (per the *Profile Activation* requirement), recording `ProfileActivated` — leaving the Profile in `Active`.

**When the Club is at capacity**, `ApproveProfile` SHALL NOT raise `IllegalProfileTransition` for a Profile in `Applied`: it SHALL instead transition the Profile to `WaitingList`, record exactly one `WaitingListJoined` event, take **no** charge, perform **no** Originating-Club lock, and record **no** `ProfileActivated`. For a Profile already in `WaitingList` whose Club is **still** at capacity, `ApproveProfile` SHALL be rejected with a localized `IllegalProfileTransition` naming the capacity reason — there is no transition to make — leaving the state, the Originating-Club link and the event log unchanged, and recording **no** second `WaitingListJoined`.

The **Club-row lock SHALL be acquired before the occupancy count**, so that concurrent approvals into the same Club serialise and the no-oversell invariant holds under concurrency; the Profile-row lock alone is **not** sufficient (two approvals of different Profiles in one Club lock different rows). Approvals into **different** Clubs SHALL remain parallel.

In production the activation is gated on the **charge-on-approval capture**: producer approval atomically triggers the Hero-Package-fee charge, and the Module-S `MembershipFeePaid` capture signal drives the activation (MVP-DEC-016 — approval = charge = `Active`). **No payment infrastructure exists at launch** (Module S/E are stubs; no mandate, no instrument, no invoice entity), so the charge is a **documented no-op seam** and the activation is unconditional once the capacity gate passes. The **charge-fail contract** (canon-specified, the Module-S target) SHALL be: a charge that fails at approval → the Profile stays `Applied` (NOT a new or `Rejected` state), **no** `Approved`/`Active` transition, **no** Hero-Package seat consumed, **no** `OriginatingClubLocked`; the approval is re-attemptable.

`DeclineProfile` on a Profile in `Applied` **or `WaitingList`** SHALL set `state = Rejected` — a terminal-for-this-application state; a re-application creates a **new** Profile row (per the *Profile — Multi-Profile Membership* requirement), and because the Customer–Club partial-unique index already excludes the terminal `rejected` state, the new `Applied` (or `WaitingList`) row inserts with **no** index migration. `DeclineProfile` SHALL take **no** Club-row lock and SHALL read no capacity: a decline frees no seat and consumes none.

Neither the `→ Approved` approval write nor the `→ Rejected` decline write SHALL record a Profile **approval/decline** domain event: the PRD § 15.2 event catalog **names no `ProfileApproved` or `ProfileRejected`**, so the state change is captured in the append-only audit trail only. Across a **successful** atomic `ApproveProfile` the **only** domain events recorded are therefore the conditional `OriginatingClubLocked` (first-ever approval) and the `ProfileActivated` of the atomic activation; across a **capacity-diverted** `ApproveProfile` the only domain event recorded is `WaitingListJoined`.

On `ApproveProfile`, **if and only if** this is the Customer's **first-ever** Profile approval across any Club — detected by the Customer's `originating_club_id` being currently unset (re-read under a transaction lock) — the Action SHALL, in the same transaction, set `Customer.originating_club_id` to the approving Profile's `club_id` and record an `OriginatingClubLocked` event. The lock SHALL fire **only on an approval that reaches `Active`** — a capacity-diverted approval SHALL NOT lock the Originating Club. It SHALL be **one-shot** (a subsequent approval on another Club neither re-fires the event nor changes the link), **immutable** thereafter (no admin-override surface at launch), and MAY remain unset indefinitely for a Customer never approved into any Club (DEC-040). The Originating-Club lock SHALL NOT be a standalone Action — it is exclusively an in-transaction side-effect of `ApproveProfile`; the atomic `Approved → Active` activation SHALL NOT write `originating_club_id`.

Every transition SHALL be **from-state guarded** against the transaction-locked re-read: an `ApproveProfile` or `DeclineProfile` on a Profile in **neither** `Applied` **nor** `WaitingList` SHALL be rejected with a localized `IllegalProfileTransition`, leaving state, the Originating-Club link and the event log unchanged.

#### Scenario: Approve a first-ever applied Profile activates it atomically and locks the Originating Club

- **GIVEN** a Customer whose `originating_club_id` is unset, with a Profile in `Applied` for Club C, which has a free Hero-Package seat
- **WHEN** `ApproveProfile` is invoked on that Profile
- **THEN** the Profile's `state` becomes `Active` in one operation (passing transiently through `Approved`, never resting there), the Customer's `originating_club_id` is set to Club C, and exactly two demand-side events are recorded in the same transaction — one `OriginatingClubLocked` and one `ProfileActivated` — and **no** `ProfileApproved` event (which the catalog does not name)

#### Scenario: A second Club's approval activates without re-locking the Originating Club

- **GIVEN** a Customer whose `originating_club_id` is already set to Club C, with a Profile in `Applied` for a different Club D that has a free seat
- **WHEN** `ApproveProfile` is invoked on the Club-D Profile
- **THEN** that Profile's `state` becomes `Active` atomically, the Customer's `originating_club_id` stays Club C, exactly one `ProfileActivated` is recorded, and **no** second `OriginatingClubLocked` event is recorded

#### Scenario: Approval leaves no durable Approved resting state

- **WHEN** any successful `ApproveProfile` completes
- **THEN** the persisted Profile is in `Active`, never `Approved` — `Approved` is a transient pass-through within the one transaction, so no query ever observes a Profile durably resting in `Approved`

#### Scenario: An approval at capacity diverts to WaitingList instead of throwing

- **GIVEN** a Customer whose `originating_club_id` is unset, with a Profile in `Applied` for a Club at exactly its capacity
- **WHEN** `ApproveProfile` is invoked on that Profile
- **THEN** no exception is raised; the Profile's `state` becomes `WaitingList`, exactly one `WaitingListJoined` event is recorded, and **no** `ProfileActivated` and **no** `OriginatingClubLocked` event is recorded, leaving the Customer's `originating_club_id` unset

#### Scenario: Approving a still-waitlisted Profile at capacity is rejected

- **GIVEN** a Profile in `WaitingList` whose Club is still at exactly its capacity
- **WHEN** `ApproveProfile` is invoked
- **THEN** a localized `IllegalProfileTransition` naming the capacity reason is raised, the Profile stays `WaitingList`, and no event — in particular no second `WaitingListJoined` — is recorded

#### Scenario: Concurrent approvals into one Club serialise on the Club row

- **GIVEN** a Club with exactly one free seat and two Profiles in `Applied` for it
- **WHEN** two concurrent transactions each invoke `ApproveProfile`, one per Profile, on an engine honouring row-level locks
- **THEN** exactly one Profile becomes `Active` and the other lands in `WaitingList`, and the Club's seat occupancy never exceeds its capacity

#### Scenario: The charge-on-approval capture and its charge-fail branch are a deferred Module-S seam

- **WHEN** the Parties code surface is inspected at launch
- **THEN** `ApproveProfile` drives `Applied → Approved → Active` in one transaction with **no** payment-provider call and **no** fabricated `MembershipFeePaid` event class; the charge-on-approval capture (mandate-at-application, pull-capable instrument, INV1) and its charge-fail branch (a failed charge leaves the Profile `Applied`, no seat, no `OriginatingClubLocked`) are a documented Module-S seam that will gate the activation when Module S lands

#### Scenario: Decline an applied or waitlisted Profile is terminal and event-silent

- **WHEN** `DeclineProfile` is invoked on a Profile in `Applied`, or on a Profile in `WaitingList`
- **THEN** the Profile's `state` becomes `Rejected`, **no** domain event is recorded, and a subsequent re-application for the same Customer–Club pair creates a new Profile (the partial-unique index admits it)

#### Scenario: Illegal approve/decline is rejected

- **WHEN** `ApproveProfile` or `DeclineProfile` is invoked on a Profile in neither `Applied` nor `WaitingList`
- **THEN** an `IllegalProfileTransition` is raised, the Profile's `state` and the Customer's `originating_club_id` are unchanged, and no event is recorded

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (**RM-05** — §3 enforcement at the atomic instant; §7 `ApproveProfile` at parity *transitions, does not throw*, from-state widens to `{applied, waiting_list}`, as does `DeclineProfile`'s; §10 the **Club-row lock**, an oversell race no canon artefact carries) · decisions/2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval.md (RM-03 — the atomic approve = charge = activation instant this gate sits on; charge-fail → stays `Applied`) · canon `c-mless/documentation` @ `360df0b` MVP_Decisions_Register_v0.1.md:142 (**MVP-DEC-017** — seat set; enforcement at the atomic instant; charge-fail consumes no seat) + :141 (MVP-DEC-016) + :147 (MVP-DEC-022 (2) — approval mandatory for every channel, no auto-approve) · canon Module_K_PRD §4.2.1:186 (`Applied → WaitingList`; `WaitingList → Approved | Rejected`), :187 (charge-fail, no seat), §7.5:429 (mandate persists through waitlisting), §13.1:625/:627 · canon Module_K_Acceptance **AC-K-J-13**:92 (*"the Profile lands in `WaitingList`, `WaitingListJoined` fires, and no charge is taken"*), **AC-K-FSM-2**:113 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1, § 3.1 (the one retained producer write), § 6 / § 6.1 (the Originating-Club one-shot lock — DEC-040), § 13 / § 13.2, § 15.2 (no `ProfileApproved` / `ProfileRejected`) · spec/04-decisions/decisions.md DEC-040, DEC-083, DEC-073 · decisions/2026-06-12-event-substrate-and-audit-store.md · MODIFIES the *Profile Membership Approval* requirement (which shipped `ApproveProfile` **uncapped** from `Applied` only, with the capacity gate and the `Applied → WaitingList` path as documented deferred seams)._

### Requirement: Profile Activation

The Profile SHALL transition `Approved → Active` via an explicit `ActivateProfile` Action — the sole writer of the Profile `state` for this transition — running inside one `DB::transaction` against a transaction-locked re-read, recording a `ProfileActivated` event (per the *Demand-Side Activation Events* requirement) in the same transaction. `ActivateProfile` is invoked in two ways: (a) **synchronously by `ApproveProfile`** inside the approval transaction — the K-internal atomic activate-on-approval that makes `Approved` transient (per the *Profile Membership Approval* requirement); and (b), when Module S lands, by the **Module-S `MembershipFeePaid` listener**.

`ActivateProfile` SHALL **NOT** evaluate the Hero-Package capacity gate. `Approved` is a **transient** state never durably rested-in, so `Approved → Active` never *newly* consumes a seat: the seat is consumed by the seat-consuming caller, which evaluates the gate under the Club-row lock **before** delegating. Its only caller today is `ApproveProfile`. Gating here would count the same seat twice within one transaction. When the Module-S `MembershipFeePaid` listener lands it becomes a **new** seat-consuming entry point and SHALL carry the gate at **its own** boundary, under the same Club-row lock discipline. This is a deliberate non-gate, not an omission (per the *Hero Package Capacity Invariant* requirement).

In production the `Approved → Active` transition is driven by the membership-fee-paid signal, or a free-club activation where no fee applies (§ 4.2.1). Per canon MVP-DEC-016 the `MembershipFeePaid` signal is **re-homed to Module S**: **Module S emits** `MembershipFeePaid` (Module E **records**; Module K **consumes** — DEC-173) on payment-provider-confirmed capture of the Hero-Package fee, which fires **INV1 — there is no INV0** (DEC-157). It is a **docblock/seam-name-only** framing — **no** `MembershipFeePaid` event class is fabricated (Module K only *consumes* it). The `MembershipFeePaid` **listener** remains a deferred Module-S seam; `ActivateProfile` is the within-module writer, invoked by the approval / free-club / operator path now. `Profile.fee_paid_at` is **not** stamped in this change (no charge to stamp — forward to Module S). Every transition SHALL be **from-state guarded**: an `ActivateProfile` on a Profile not in `Approved` SHALL be rejected with a localized `IllegalProfileTransition`, leaving state and the event log unchanged.

#### Scenario: Activate an approved Profile

- **WHEN** `ActivateProfile` is invoked on a Profile in `Approved`
- **THEN** the Profile's `state` becomes `Active` and exactly one `ProfileActivated` event is recorded in the same transaction (module `parties`, `entity_type` `Profile`, PII-free payload)

#### Scenario: Activation applies no capacity check of its own

- **GIVEN** a Club at exactly its Hero-Package capacity and a Profile placed directly in `Approved`
- **WHEN** `ActivateProfile` is invoked on it
- **THEN** the Profile becomes `Active` and no capacity rejection is raised — the gate belongs to the seat-consuming caller (`ApproveProfile`), which evaluates it under the Club-row lock before delegating, so the seat is never counted twice

#### Scenario: Illegal activation is rejected

- **WHEN** `ActivateProfile` is invoked on a Profile not in `Approved` (e.g. `Applied`, `WaitingList` or already `Active`)
- **THEN** an `IllegalProfileTransition` is raised, the Profile's `state` is unchanged, and no `ProfileActivated` event is recorded

#### Scenario: The membership-fee trigger is a deferred Module-S seam

- **WHEN** the Parties code surface is inspected
- **THEN** `ActivateProfile` exists as the within-module writer of `Approved → Active`, invoked synchronously by `ApproveProfile` in the approval transaction; there is **no** `MembershipFeePaid` listener and **no** fabricated Module-S / Module-E event class in this change; the production trigger is documented as Module S's `MembershipFeePaid` (Module E records; Module K consumes — DEC-173), firing INV1 and no INV0 (DEC-157)

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§4 — the cap gates exactly the transitions that *newly consume* a seat; `Approved → Active` is not one of them) · decisions/2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval.md (RM-03 — `Approved` transient; the `MembershipFeePaid` seam re-home E→S is docblock-only) · canon `c-mless/documentation` @ `360df0b` MVP_Decisions_Register_v0.1.md:141 (MVP-DEC-016) + :142 (MVP-DEC-017) · canon Module_K_PRD §13.1:627 · Module_K_Acceptance_v0.3-MVP.md AC-K-J-16, AC-K-EVT-15 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1, § 13.1 (*"evaluated at every Profile transition into `Active`"* — read together with canon §13.1:627, which enumerates the three **newly-consuming** transitions), § 15.2 / § 15.8 · decisions/2026-06-12-event-substrate-and-audit-store.md · MODIFIES the *Profile Activation* requirement (whose *"`ActivateProfile` ships **uncapped** (RM-05)"* deferred-seam clause becomes false with this change, and is replaced by an explicit, reasoned **non-gate**)._

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

Each demand-side activation transition SHALL record its **verbatim** Module K event — `CustomerActivated` (on Customer `pending → active`), `ProfileActivated` (on Profile `Approved → Active`), `OriginatingClubLocked` (on the Customer's first-ever Profile approval **that reaches `Active`**) — through the platform `DomainEventRecorder`, **within the same database transaction** as the state write, tagged with module `parties`, the acting `actor_role` + id resolved from the `ActorContext` seam, the entity type and id, and a **PII-free** payload (entity ids + enum/business values only — never name, email, phone or date of birth). `CustomerActivated` SHALL carry `entity_type = 'Customer'` with payload `{customer_id, status}`; `ProfileActivated` SHALL carry `entity_type = 'Profile'` with payload `{profile_id, state}`; `OriginatingClubLocked` SHALL carry `entity_type = 'Customer'` with payload `{customer_id, club_id, profile_id, locked_at}` — the Customer, the locking Club, the triggering membership and the moment (§ 6.1 verbatim).

Because membership approval is the **atomic approve = charge = activation** (per the *Profile Membership Approval* requirement), `ProfileActivated` is recorded by the `Approved → Active` activation that `ApproveProfile` drives **as well as** by a standalone `ActivateProfile`: a **first-ever** `ApproveProfile` that reaches `Active` records **both** `OriginatingClubLocked` and `ProfileActivated`; a **subsequent** one records `ProfileActivated` (the Originating Club is already locked). A `WaitingList → Active` **conversion** records exactly the same events as an approval from `Applied` — the conversion is the same atomic instant, not a distinct transition.

A **capacity-diverted** `ApproveProfile` — one whose Club is at its Hero-Package capacity, leaving the Profile in `WaitingList` — SHALL record **none** of the three activation events. It records `WaitingListJoined` instead (governed by the *WaitingList Placement, Conversion and Decline* requirement, which owns that event's contract). The `→ Approved` approval write and the `→ Rejected` `DeclineProfile` write SHALL record **no** Profile approval/decline event of their own (audit-only — § 15.2 names no `ProfileApproved` / `ProfileRejected`). **No** event name outside the three-name **activation** set SHALL be recorded by an activation transition. Each of the three events SHALL be recorded as a **root** event (no `causation_id`; `correlation_id` defaults to its own `event_id`), since the transition it records has no parent event in the same transaction — when `OriginatingClubLocked` and `ProfileActivated` are co-recorded in a first-ever atomic approval, **both** are roots (the approval write parents neither). The downstream consumers `OriginatingClubLocked` names — Module S settlement-eligibility, Module E D19 accrual, HubSpot (§ 6 / § 15.6 / AC-K-EVT-10) — remain unwired: Module K records the event; all consumption is downstream and deferred.

#### Scenario: Each activation transition records its verbatim event PII-free

- **WHEN** any of the three activation transitions runs (`ActivateCustomer`, a standalone `ActivateProfile`, or the atomic `ApproveProfile` which drives the `Approved → Active` activation)
- **THEN** exactly its corresponding event (`CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked`) is recorded in the writing transaction, tagged module `parties`, with the entity type/id and an `actor_role` from `ActorContext`, and its payload contains only entity ids and enum/business values (no name, email, phone or date of birth)

#### Scenario: OriginatingClubLocked carries the four spec fields

- **WHEN** `OriginatingClubLocked` is recorded
- **THEN** its payload is exactly `{customer_id, club_id, profile_id, locked_at}` (the Customer, the locking Club, the triggering membership, and the moment), `entity_type` `Customer`, PII-free

#### Scenario: A first-ever approval records the lock and the activation; a later approval records only the activation

- **WHEN** a Customer's first-ever `ApproveProfile` runs against a Club with a free seat, then a second Club's `ApproveProfile` runs
- **THEN** the first records **both** `OriginatingClubLocked` and `ProfileActivated`, and the second records `ProfileActivated` only (no second `OriginatingClubLocked`)

#### Scenario: A capacity-diverted approval records no activation event

- **GIVEN** a Customer whose `originating_club_id` is unset, with an `Applied` Profile in a Club at exactly its capacity
- **WHEN** `ApproveProfile` is invoked
- **THEN** **no** `ProfileActivated` and **no** `OriginatingClubLocked` event is recorded (a `WaitingListJoined` is recorded instead), and the Customer's `originating_club_id` stays unset — the first-ever-approval lock fires only on an approval that reaches `Active`

#### Scenario: A waitlist conversion records the same events as a direct approval

- **GIVEN** a Customer whose `originating_club_id` is unset, with a Profile in `WaitingList` in a Club that now has a free seat
- **WHEN** `ApproveProfile` is invoked on it
- **THEN** exactly one `OriginatingClubLocked` and one `ProfileActivated` are recorded — the conversion is the same atomic instant as an approval from `Applied`

#### Scenario: Approve and decline record no approval/decline event

- **WHEN** `ApproveProfile` or `DeclineProfile` runs, from either `Applied` or `WaitingList`
- **THEN** no `ProfileApproved` / `ProfileRejected` event is recorded (the approval write and the decline write are audit-only), and no activation transition records any event name outside `{CustomerActivated, ProfileActivated, OriginatingClubLocked}`

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§6–§8 — a capacity-diverted approve takes no charge and performs **no** Originating-Club lock; the conversion is the same atomic instant) · decisions/2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval.md (RM-03 — the three-name activation set) · canon `c-mless/documentation` @ `360df0b` Module_K_PRD §4.2.1:187, §13.1:627 · canon Module_K_Acceptance **AC-K-J-13**:92 (*"no charge is taken"*), **AC-K-EVT-11**:259 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 15.1, § 15.2, § 15.6 (`OriginatingClubLocked`; `WaitingListJoined`), § 6.1 · decisions/2026-06-12-event-substrate-and-audit-store.md (root `correlation_id` = own `event_id`) · openspec/specs/event-substrate/spec.md (Transactional Event Recording; Domain Event Envelope) · CLAUDE.md invariants 4 & 10 · MODIFIES the *Demand-Side Activation Events* requirement (whose blanket *"no event name outside this three-name set SHALL be recorded"* clause predated `WaitingListJoined`, and which had no capacity-diverted branch)._

### Requirement: Profile Suspension and Restoration

The Profile SHALL transition `Active → Suspended` via an explicit `SuspendProfile` Action and `Suspended → Active` via an explicit `ReactivateProfile` Action — each the sole writer of the Profile `state` for its transition, running inside one `DB::transaction` against a transaction-locked re-read, recording a `ProfileSuspended` (respectively `ProfileReactivated`) event (per the *Demand-Side Status Events* requirement) in the same transaction. In production these transitions are driven by the Hold→`suspended` coupling (a Profile-level Hold, or a cascading Customer-level Hold — per the *Hold-Driven Status Coupling* requirement); the Actions are also directly operator-invocable (manual suspension — AC-K-BR-Customer-1 *"explicit (manual or via Hold)"*).

**A `Suspended` Profile KEEPS its Hero-Package seat.** `Suspended` is inside the seat-occupying set, so `SuspendProfile` frees **no** seat and `ReactivateProfile` consumes **no** new one. Consequently:

- **`ReactivateProfile` SHALL NEVER be capacity-gated and SHALL NEVER be blocked by capacity**, even when the Club is at exactly its capacity. It SHALL take no Club-row lock and read no capacity value. Gating it would let a *temporary* Hold permanently **evict** a member, or let a returning member exceed the cap — both forbidden.
- **`SuspendProfile` SHALL NOT free a seat for another applicant.** A Club at capacity holding a `Suspended` Profile is still at capacity: an `Applied` Profile in that Club SHALL NOT become approvable merely because a member was suspended.

Suspension SHALL be **state-preserving**: `SuspendProfile` SHALL write **only** the Profile `state` — it SHALL NOT cancel vouchers, pending orders or allocation reservations, nor mutate any Club Credit balance. Active vouchers stay ACTIVE, pending orders stay pending, reservations stay reserved (§ 10.1); Club Credit is frozen while suspended and mutable again on restore. Every transition SHALL be **from-state guarded**: a `SuspendProfile` on a Profile not in `Active`, or a `ReactivateProfile` on a Profile not in `Suspended`, SHALL be rejected with a localized `IllegalProfileTransition`, leaving state and the event log unchanged.

#### Scenario: Suspend an active Profile records ProfileSuspended and preserves state

- **WHEN** `SuspendProfile` is invoked on a Profile in `Active`
- **THEN** the Profile's `state` becomes `Suspended` and exactly one `ProfileSuspended` event is recorded in the same transaction (module `parties`, `entity_type` `Profile`, PII-free payload `{profile_id, state}`)
- **AND** no voucher, order, reservation or Club Credit record is created, cancelled or mutated by the Action (it writes only `Profile.state`)

#### Scenario: Restore a suspended Profile

- **WHEN** `ReactivateProfile` is invoked on a Profile in `Suspended`
- **THEN** the Profile's `state` becomes `Active` and exactly one `ProfileReactivated` event is recorded in the same transaction

#### Scenario: Restoration at exact capacity is never blocked

- **GIVEN** a Club at exactly its Hero-Package capacity, one of whose seat-occupying Profiles is `Suspended`
- **WHEN** `ReactivateProfile` is invoked on that Profile
- **THEN** it becomes `Active`, exactly one `ProfileReactivated` event is recorded, and **no** capacity check is performed and **no** capacity rejection is raised — a Hold never evicts a member

#### Scenario: Suspension does not free a seat

- **GIVEN** a Club at exactly its capacity and a Profile in `Applied` for that Club
- **WHEN** a seat-occupying Profile is suspended, and `ApproveProfile` is then invoked on the `Applied` Profile
- **THEN** the `Applied` Profile is **not** activated (it is diverted to `WaitingList`), because the suspended member still holds their seat

#### Scenario: Illegal suspend or restore is rejected

- **WHEN** `SuspendProfile` is invoked on a Profile not in `Active` (e.g. `Applied`, `WaitingList`, `Lapsed` or already `Suspended`), or `ReactivateProfile` on a Profile not in `Suspended`
- **THEN** an `IllegalProfileTransition` is raised, the Profile's `state` is unchanged, and no event is recorded

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§1 — the seat set is `Active`+`Suspended`; §2 — `Suspended → Active` is **never** capacity-re-checked and **never** blocked; `ReactivateProfile` stays untouched and earns an explicit regression test) · canon `c-mless/documentation` @ `360df0b` MVP_Decisions_Register_v0.1.md:142 (**MVP-DEC-017** Q1 — a Hold must never evict a member) · canon Module_K_PRD §13.1:625, §10.1:532, §4.2.1:191 · canon Module_K_Acceptance **AC-K-J-13**:92 (leg 2), **AC-K-FSM-2a**:114 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1, § 10.1, § 15.2 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-2, AC-K-FSM-2a, AC-K-EVT-5, AC-K-BR-Hold-5 · decisions/2026-06-19-hold-status-coupling.md (the coupling that drives these in production) · MODIFIES the *Profile Suspension and Restoration* requirement (which said nothing about seat occupancy, leaving the `Suspended`-keeps-its-seat rule and the never-gate on restoration undocumented)._

### Requirement: Profile Lapse and Grace Renewal

The Profile SHALL transition `Active → Lapsed` via an explicit `LapseProfile` Action and `Lapsed → Active` via an explicit `RenewProfile` Action — each the sole writer of the Profile `state` for its transition, running inside one `DB::transaction` against a transaction-locked re-read. `LapseProfile` SHALL set `state = Lapsed`, stamp the additive nullable `lapsed_at` timestamp, and record a **`ProfileExpired`** event (the § 15.2 event for this edge — the catalog names **no** `ProfileLapsed`). `RenewProfile` SHALL set `state = Active`, clear `lapsed_at`, and record a **`ProfileRenewed`** event (the § 15.2 event for the grace renewal — **not** `ProfileReactivated`, which is reserved for `Suspended → Active`).

`LapseProfile` **frees** a Hero-Package seat (`Lapsed` is outside the seat-occupying set) and SHALL take no Club-row lock and read no capacity — freeing a seat can never oversell. **A freed seat SHALL NOT trigger any promotion off the waitlist** (per the *WaitingList Placement, Conversion and Decline* requirement).

**`RenewProfile` SHALL be capacity-gated.** `Lapsed → Active` **re-consumes** a seat, so `RenewProfile` SHALL, in its transaction, acquire the **Club-row lock**, count the seat-occupying set (`Active` + `Suspended`), read the capacity through the Module-K-owned capacity read port, and proceed **only if a seat is free** (or the capacity is `null`). At capacity it SHALL be rejected with a localized `IllegalProfileTransition` naming the **capacity** reason, leaving `state = Lapsed`, `lapsed_at` intact (the 30-day grace clock keeps running) and the event log unchanged. It SHALL **not** divert the Profile to `WaitingList`: canon draws only `Applied → WaitingList`, no `Lapsed → WaitingList` edge exists, and inventing one would discard `lapsed_at` and burn the grace window.

> ⚠️ **Naming trap.** This `RenewProfile` (`Lapsed → Active`, the 30-day grace re-activation) is **not** the *grandfathered* renewal of canon `MVP-DEC-011` / `AC-K-J-15a`, which is the **period rollover of an `Active` Profile into a new club year** and is explicitly **not** cap-gated (the seat was never freed). That rollover **is not modelled** — `parties_profiles` carries no `valid_to`, no period column and no rollover Action — and is out of scope. Same word, opposite rule.

`RenewProfile` SHALL enforce the **30-day grace window** (DEC-034): it is permitted **only** when `state = Lapsed` **and** the current moment is within 30 days of `lapsed_at`; past the grace window it SHALL be rejected with a localized `IllegalProfileTransition` (the deferred scheduler instead transitions the Profile `Lapsed → Cancelled` — per the *Profile Cancellation and Deactivation* requirement). The **grace sub-gate SHALL be evaluated before the capacity gate**, so a past-grace renewal reports the grace reason regardless of capacity. The lapse trigger (membership-validity-period expiry, § 4.2.1) and the renewal trigger (the membership-fee-paid signal, § 15.8) are **deferred seams** — `LapseProfile`/`RenewProfile` are the within-module writers, invoked directly now; **no** cross-module event contract is fabricated. Every transition SHALL be **from-state guarded**: a `LapseProfile` on a Profile not in `Active`, or a `RenewProfile` on a Profile not in `Lapsed` (or past grace, or at capacity), SHALL be rejected, leaving state, `lapsed_at` and the event log unchanged.

#### Scenario: Lapse an active Profile and free its seat

- **WHEN** `LapseProfile` is invoked on a Profile in `Active`
- **THEN** the Profile's `state` becomes `Lapsed`, `lapsed_at` is stamped, exactly one `ProfileExpired` event is recorded in the same transaction (and **no** `ProfileLapsed` event, which the catalog does not name), and the Club's seat occupancy drops by one

#### Scenario: Renew a lapsed Profile within the 30-day grace when a seat is free

- **GIVEN** a Profile in `Lapsed` whose `lapsed_at` is within the last 30 days, in a Club with a free seat (or no configured capacity)
- **WHEN** `RenewProfile` is invoked
- **THEN** the Profile's `state` becomes `Active`, `lapsed_at` is cleared, exactly one `ProfileRenewed` event is recorded (not `ProfileReactivated`), and the Club's seat occupancy rises by one

#### Scenario: Renewal at capacity is rejected and the grace clock keeps running

- **GIVEN** a Profile in `Lapsed` whose `lapsed_at` is within the last 30 days, in a Club at exactly its capacity
- **WHEN** `RenewProfile` is invoked
- **THEN** a localized `IllegalProfileTransition` naming the capacity reason is raised; the Profile stays `Lapsed` with `lapsed_at` **unchanged**; it is **not** moved to `WaitingList`; and no event is recorded
- **WHEN** a seat is subsequently freed and `RenewProfile` is invoked again, still within the grace window
- **THEN** the renewal succeeds and exactly one `ProfileRenewed` event is recorded

#### Scenario: Renewal past the grace window is rejected regardless of capacity

- **GIVEN** a Profile in `Lapsed` whose `lapsed_at` is more than 30 days ago
- **WHEN** `RenewProfile` is invoked, whether the Club has a free seat or is at capacity
- **THEN** an `IllegalProfileTransition` naming the **grace** reason is raised (the grace sub-gate is evaluated first), the Profile stays `Lapsed`, and no event is recorded

#### Scenario: The lapse and renewal triggers are deferred seams

- **WHEN** the Parties code surface is inspected
- **THEN** `LapseProfile` and `RenewProfile` exist as the within-module writers, and there is **no** validity-period scheduler and **no** `MembershipFeePaid` listener or fabricated cross-module event class in this change

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (**§11 — the load-bearing naming trap**: our `RenewProfile` is a cap-gated re-activation; the *grandfathered* renewal is an unmodelled `Active` period rollover) · canon `c-mless/documentation` @ `360df0b` Module_K_PRD §13.1:629 (*"a re-activation within the 30-day grace **re-consumes a seat** (subject to the cap at re-activation time)"*), :627 (*"re-activation from `Lapsed` / `Cancelled`"* among the gated transitions; the grandfathered period rollover *"is **not** cap-gated"*), §4.2.1:186 (no `Lapsed → WaitingList` edge exists) · MVP_Decisions_Register_v0.1.md:136 (**MVP-DEC-011** — grandfathering; attrition drawdown) · canon Module_K_Acceptance **AC-K-J-15a**:95 (**absent from our frozen acceptance file** — the table jumps J-15 → J-16; legs (b)/(d) need the unmodelled rollover and are carved out) · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1, § 13.1:616 (**superseded**), § 15.2, § 15.8 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-12, AC-K-BR-Profile-3, AC-K-EVT-5 · spec/04-decisions/decisions.md DEC-034 (30-day lapsed grace) · MODIFIES the *Profile Lapse and Grace Renewal* requirement (which applied **no capacity gate** to `RenewProfile`)._

### Requirement: Profile Cancellation and Deactivation

The Profile SHALL transition `Active | Lapsed → Cancelled` via an explicit `CancelProfile` Action and `Active → Inactive` via an explicit `DeactivateProfile` Action — each the sole writer of the Profile `state` for its transition, running inside one `DB::transaction` against a transaction-locked re-read. Both `Cancelled` and `Inactive` are **terminal soft-delete** states: the Profile is **never hard-deleted** at launch, preserving audit history (re-entry requires a fresh application, except the lapse-grace path). `CancelProfile` SHALL set `state = Cancelled` and record the optional Producer-initiated `cancellation_reason`; `DeactivateProfile` SHALL set `state = Inactive` and record a `ProfileInactive` event.

`CancelProfile` SHALL record **no** domain event — the § 15.2 Profile event family names **no `ProfileCancelled`**, and § 15.7 explicitly defers the cancellation-signal shape as a downstream consumer concern; so (exactly as `ApproveProfile`/`DeclineProfile` are audit-only) the `state = Cancelled` write captured in the append-only audit trail **is** the record. `CancelProfile` SHALL be invoked both **standalone** (operator-initiated cancellation) and as the **per-Profile leg of the Producer-offboarding cascade** (per the *Producer Lifecycle* requirement): when `RetireProducer` sunsets a Club, it SHALL drive `CancelProfile` with a **Producer-initiated `cancellation_reason`** for every `Active`/`Lapsed` Profile under that Club, in the same transaction and after the `ClubSunset`. This delivers § 15.7's stated Module-K contribution — "the producer-initiated transition logic + the cancellation reason at the originating boundary." The subscribable per-Profile cancellation **signal event** Module S consumes for Club-Credit conversion (AC-K-EVT-14 / § 10.2 / DEC-043) — and the conversion math itself — remain a **deferred Module-S seam** (audit-only, no new event ships in this change). Because the Customer–Club partial-unique index already excludes the terminal `{rejected, cancelled, inactive}` states, a `Cancelled` (or `Inactive`) Profile SHALL NOT block a fresh `Applied` Profile for the same Customer–Club pair — with no index migration. Every transition SHALL be **from-state guarded**: a `CancelProfile` on a Profile not in `Active`/`Lapsed`, or a `DeactivateProfile` on a Profile not in `Active`, SHALL be rejected with a localized `IllegalProfileTransition`.

#### Scenario: Cancel an active Profile is terminal and event-silent

- **WHEN** `CancelProfile` is invoked on a Profile in `Active` (or `Lapsed`) with a cancellation reason
- **THEN** the Profile's `state` becomes `Cancelled`, the `cancellation_reason` is recorded, **no** domain event is recorded (the catalog names no `ProfileCancelled`), and a subsequent application for the same Customer–Club pair creates a new Profile in `Applied` (the partial-unique index admits it)

#### Scenario: The offboarding cascade cancels each active Profile with a producer reason

- **GIVEN** a Producer offboarding that sunsets a Club with two `Active` Profiles
- **WHEN** the `RetireProducer` cascade reaches the Profile leg
- **THEN** each `Active` Profile is transitioned to `Cancelled` by `CancelProfile` carrying a Producer-initiated `cancellation_reason`, recorded audit-only (no domain event), after the `ClubSunset` in the same transaction

#### Scenario: Deactivate an active Profile records ProfileInactive

- **WHEN** `DeactivateProfile` is invoked on a Profile in `Active`
- **THEN** the Profile's `state` becomes `Inactive` and exactly one `ProfileInactive` event is recorded in the same transaction

#### Scenario: Illegal cancel or deactivate is rejected

- **WHEN** `CancelProfile` is invoked on a Profile not in `Active`/`Lapsed`, or `DeactivateProfile` on a Profile not in `Active`
- **THEN** an `IllegalProfileTransition` is raised, the Profile's `state` is unchanged, and no event is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1 (`Active → Cancelled` voluntary/admin/**Producer-offboarding**/death; `Lapsed → Cancelled` after grace; `Active → Inactive`; terminal soft-delete), § 10.2 (**Producer-offboarding per-Profile cancellation with a Producer-initiated reason; Module K's role ends at the upstream signal**), § 15.2 (`ProfileInactive`; the family names no `ProfileCancelled`), § 15.7 (the per-Profile cancellation signal shape is a deferred downstream-consumer concern) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-2, AC-K-FSM-13 (terminal soft-delete), AC-K-BR-Profile-2, **AC-K-EVT-14 (Producer-offboarding per-Profile cancellation signal — deferred Module-S consumer)**, **AC-K-J-19** · spec/04-decisions/decisions.md DEC-043 (Club-Credit conversion at offboarding — Module S) · openspec/specs/party-registry/spec.md (*Producer Lifecycle* — the offboarding cascade that now drives this transition; *Profile — Multi-Profile Membership*) · MODIFIES the *Profile Cancellation and Deactivation* requirement (openspec/specs/party-registry/spec.md — which shipped the within-module `→ Cancelled` transition + reason but **not the offboarding orchestration**: "this change ships the within-module `→ Cancelled` transition + the cancellation reason, not the offboarding orchestration")._

### Requirement: Customer Suspension and Closure

The Customer SHALL transition `active → suspended` via `SuspendCustomer`, `suspended → active` via `ReactivateCustomer`, and `active | suspended → closed` via `CloseCustomer` — each the sole writer of the Customer `status` for its transition, running inside one `DB::transaction` against a transaction-locked re-read, recording (respectively) a `CustomerSuspended`, `CustomerReactivated`, or `CustomerClosed` event in the same transaction. Suspension SHALL be **explicit** — manual (operator) or via the Hold coupling — and SHALL NOT be automatically driven by Profile state changes or by a KYC/sanctions verdict (the status FSM is independent of the compliance FSMs — § 9.4; AC-K-BR-Customer-1).

`SuspendCustomer` SHALL **cascade** to the Customer's Profiles: in the same transaction it SHALL transition every Profile currently in `Active` to `Suspended` (recording one `ProfileSuspended` per Profile — § 15.1 *"Cascades to all the Customer's Profiles"*); non-`Active` Profiles are skipped (the FSM has only `Active → Suspended`; the Customer-scope Hold blocks them logically via the read-API). `ReactivateCustomer` SHALL cascade-restore every Profile currently in `Suspended` to `Active` (recording `ProfileReactivated`) **iff** that Profile is no longer covered by any active Hold (a Profile retaining its own active Hold — or under a Customer that retains another active Hold — stays `Suspended`). `CloseCustomer` SHALL **not** cascade to Profiles — § 15.1 `CustomerClosed` names no cascade (contrast `CustomerSuspended`); `closed` is **terminal** and is **orthogonal to** anonymisation (a `closed` Customer stays admin-queryable until separately anonymised — AC-K-BR-Customer-2; **anonymisation is an independent operation, implemented by the *Customer Anonymisation (Right-to-Erasure)* requirement** — a `closed` Customer MAY be anonymised and remains queryable only as an opaque identifier thereafter). Every transition SHALL be **from-state guarded**: a `SuspendCustomer` on a Customer not in `active`, a `ReactivateCustomer` not in `suspended`, or a `CloseCustomer` not in `active`/`suspended`, SHALL be rejected with a localized `IllegalCustomerTransition`, leaving status, the cascade and the event log unchanged.

#### Scenario: Suspend an active Customer cascades to its active Profiles

- **GIVEN** a Customer in `active` with two Profiles in `Active` and one in `Lapsed`
- **WHEN** `SuspendCustomer` is invoked
- **THEN** the Customer's `status` becomes `suspended` and one `CustomerSuspended` event is recorded, AND each of the two `Active` Profiles becomes `Suspended` with one `ProfileSuspended` each, AND the `Lapsed` Profile is unchanged

#### Scenario: Restore a Customer reactivates only the Profiles no longer covered by a Hold

- **GIVEN** a `suspended` Customer whose suspension cascaded two Profiles to `Suspended`, one of which also carries its own active Profile-scope Hold
- **WHEN** `ReactivateCustomer` is invoked (the Customer-scope Hold having been lifted)
- **THEN** the Customer's `status` becomes `active` with one `CustomerReactivated` event, AND the Profile with no remaining Hold returns to `Active` with one `ProfileReactivated`, AND the Profile still carrying its own active Hold stays `Suspended`

#### Scenario: Close a Customer is terminal and does not cascade to Profiles

- **WHEN** `CloseCustomer` is invoked on a Customer in `active` or `suspended`
- **THEN** the Customer's `status` becomes `closed`, one `CustomerClosed` event is recorded, and no Profile is transitioned by the Action (closure names no cascade)

#### Scenario: Illegal Customer status transition is rejected

- **WHEN** `SuspendCustomer` is invoked on a Customer not in `active`, or `ReactivateCustomer` not in `suspended`, or `CloseCustomer` not in `active`/`suspended`
- **THEN** an `IllegalCustomerTransition` is raised and the Customer `status` (and any Profiles) are unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer FSM `pending → active → suspended → closed`; suspension explicit on cross-cutting Holds; closed terminal, orthogonal to anonymisation), § 10.1 (Customer-level suspension blocks all the Customer's Profiles; restore on lift), § 14.2 BR-K-Customer-1/2 (suspension explicit, not auto-driven by Profile state; closed and anonymised orthogonal), § 15.1 (`CustomerSuspended` cascades to all Profiles; `CustomerReactivated`; `CustomerClosed` terminal, names no cascade) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-1 (Customer FSM + the five events), AC-K-BR-Customer-1 (suspension explicit, not auto-driven), AC-K-BR-Customer-2 (closed queryable until anonymised; independent operations), AC-K-BR-Hold-3 (a Customer-level Hold blocks every Profile), AC-K-EVT-1 (`CustomerSuspended`/`CustomerReactivated`/`CustomerClosed`) · decisions/2026-06-19-hold-status-coupling.md (the cascade + coverage-recompute restore), decisions/2026-06-12-event-substrate-and-audit-store.md (transactional recording) · openspec/specs/party-registry/spec.md (the *Customer Anonymisation (Right-to-Erasure)* requirement — anonymisation is now implemented as an independent operation, closing this requirement's former "out of scope" note)._

### Requirement: Account Status Lifecycle

The Account SHALL transition `active → suspended` via `SuspendAccount`, `suspended → active` via `ReactivateAccount`, and `active | suspended → closed` via `CloseAccount` — each the sole writer of the Account `status` for its transition, running inside one `DB::transaction` against a transaction-locked re-read. These transitions SHALL record **no** domain event: the PRD § 15 event catalog names **no** Account-family event (the Account is event-silent at creation too — it records no `AccountCreated`), so the `status` write captured in the append-only audit trail **is** the record. In production `active → suspended` is driven by an Account-level Hold and `suspended → active` by its lift (per the *Hold-Driven Status Coupling* requirement); the Actions are also directly operator-invocable.

The Account SHALL have **no** `ActivateAccount` Action: the Account is born `active` (it has no `pending` state — AC-K-FSM-9), so its only `→ active` edge is the restore `ReactivateAccount`. Every transition SHALL be **from-state guarded**: a `SuspendAccount` on an Account not in `active`, a `ReactivateAccount` not in `suspended`, or a `CloseAccount` not in `active`/`suspended`, SHALL be rejected with a localized `IllegalAccountTransition`, leaving status and the event log unchanged.

#### Scenario: Suspend and restore an Account, event-silently

- **WHEN** `SuspendAccount` is invoked on an Account in `active`, then `ReactivateAccount` on the resulting `suspended` Account
- **THEN** the Account's `status` becomes `suspended` then `active` again, and **no** domain event is recorded by either transition (the catalog names no Account event)

#### Scenario: Close an Account is terminal

- **WHEN** `CloseAccount` is invoked on an Account in `active` or `suspended`
- **THEN** the Account's `status` becomes `closed` and no domain event is recorded

#### Scenario: Account has no activation Action and rejects illegal transitions

- **WHEN** the Parties code surface is inspected, and `SuspendAccount`/`ReactivateAccount`/`CloseAccount` are invoked from a wrong from-state
- **THEN** no `ActivateAccount` Action exists (the Account is born `active`), and each wrong-from-state call raises a localized `IllegalAccountTransition` leaving `status` unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.7 (Account FSM `active → suspended → closed`, parallel to Customer, blocked by Account-level Holds; one Account auto-provisioned on Customer activation, born `active`), § 15 (no Account-family event in the catalog) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-9 (Account FSM `active → suspended → closed`; Account-level Holds drive `active → suspended`, lift drives `suspended → active`) · openspec/specs/party-registry/spec.md (the *Account — Billing Container* requirement — born `active`, records no event) · decisions/2026-06-19-hold-status-coupling.md (the Hold coupling driving the Account transitions)._

### Requirement: Hold-Driven Status Coupling

Placing and lifting a Hold SHALL drive the demand-side status of the covered scopes — closing the seam the *Birth States Recorded, Lifecycle Transitions Deferred* requirement held open (*"placing a Hold records the Hold but performs no status transition"*). A status-bearing scope (Customer, Account, Profile) SHALL be `suspended` **iff** it is covered by at least one **active** Hold, where coverage is: an active Hold on that exact scope, **plus** — for a Profile — an active **Customer-scope** Hold on its owning Customer (the BR-K-Hold-3 cascade; Profile-scope and Account-scope Holds isolate — BR-K-Hold-4). This is **recomputed from Hold coverage** on every placement/lift, not tracked by provenance (decisions/2026-06-19-hold-status-coupling.md).

`PlaceHold` SHALL, in its recording transaction and after appending the Hold, drive every covered scope **currently in its suspendable from-state** to `suspended` by invoking the matching explicit Action — `scope_type = customer` ⇒ `SuspendCustomer` (cascading to the Customer's `Active` Profiles), `account` ⇒ `SuspendAccount`, `profile` ⇒ `SuspendProfile`. A Hold whose covered scope is **not** in its suspendable from-state SHALL record the Hold and perform **no** transition — in particular the `kyc` Hold auto-placed on a **`pending`** Customer at onboarding suspends nothing (the from-state guard preserves the independence of the status FSM from the KYC/sanctions FSMs). `LiftHold` (operator) and the system `kyc`-lift in `RecordKycVerified` SHALL, after lifting, **restore** every covered scope **currently `suspended`** to `active` by invoking the matching `Reactivate*` Action — **iff** re-querying coverage shows **no other active Hold** still covers that scope (BR-K-Hold-1: many Holds may coexist; restore only when the last covering Hold is gone). The coupling SHALL remain **within Module K** (no cross-module access) and SHALL record the status events (`CustomerSuspended`/`ProfileSuspended`/… and their reactivations) per the *Demand-Side Status Events* requirement; a Hold placement/lift that drives no transition records only its own `CustomerHoldPlaced`/`CustomerHoldLifted`.

#### Scenario: Placing a Hold on an active scope suspends it

- **WHEN** `PlaceHold` records an `admin` Hold whose scope is an `active` Customer (respectively an `active` Account, an `Active` Profile)
- **THEN** in the same transaction the scope transitions to `suspended` and the corresponding suspension event(s) are recorded (a Customer-scope Hold additionally cascades `ProfileSuspended` to the Customer's `Active` Profiles)

#### Scenario: A Hold on a non-suspendable scope drives no transition

- **WHEN** the `kyc` Hold is auto-placed on a Customer in `pending` (onboarding KYC), or a Hold is placed on a Profile in `Applied`
- **THEN** the Hold is recorded (`CustomerHoldPlaced`) but the scope's status is unchanged and no suspension event is recorded (the from-state guard — the status FSM stays independent of the KYC/sanctions FSMs)

#### Scenario: Lifting the last covering Hold restores; an earlier lift with coverage remaining does not

- **GIVEN** a Profile driven to `Suspended` while it carries two active Holds (its own `admin` Hold and a cascading Customer-scope Hold)
- **WHEN** one of the two Holds is lifted
- **THEN** the Profile stays `Suspended` (coverage remains); **WHEN** the second (last covering) Hold is lifted **THEN** the Profile returns to `Active` with one `ProfileReactivated`

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1 (`Active → Suspended` via a Profile-level or cascading Customer-level Hold; `Suspended → Active` when the triggering Hold is lifted), § 4.7 (Account-level Holds drive `active → suspended`), § 10.1 (Customer-level Hold blocks all Profiles; reactivation when the triggering Hold is lifted), § 4.8 / § 4.8.1 (the unified Hold registry; **eight** types — canon MVP-DEC-008; the `*Reactivated` event on lift), § 14.8 BR-K-Hold-1/3/4 (multiple Holds coexist — any blocks; Customer-scope cascades, Profile-scope isolates) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-2a (suspension state preservation), AC-K-FSM-9 (Account Holds drive `active → suspended`, lift drives `suspended → active`), AC-K-BR-Hold-1/3/4 · decisions/2026-06-19-hold-status-coupling.md (coverage-recompute; explicit Actions invoked by the Hold paths; restore iff uncovered), decisions/2026-06-18-hold-lift-discipline-per-type.md (the system `kyc`-lift path), openspec/specs/party-registry/spec.md (the *Hold Registry*, *Hold Lifecycle and Lift Discipline* and *Hold and Sanctions Read-API* requirements this builds on)._

### Requirement: Demand-Side Status Events

Each demand-side status transition SHALL record its **verbatim** Module K event — `CustomerSuspended` (Customer `active → suspended`), `CustomerReactivated` (`suspended → active`), `CustomerClosed` (`active | suspended → closed`), `ProfileSuspended` (Profile `Active → Suspended`), `ProfileReactivated` (`Suspended → Active`), `ProfileExpired` (`Active → Lapsed`), `ProfileRenewed` (`Lapsed → Active`), `ProfileInactive` (`Active → Inactive`) — through the platform `DomainEventRecorder`, **within the same database transaction** as the state write, tagged with module `parties`, the acting `actor_role` + id from the `ActorContext` seam, the entity type and id, and a **PII-free** payload (entity ids + enum/business values only — never name, email, phone or date of birth). The Customer events SHALL carry `entity_type = 'Customer'` with payload `{customer_id, status}`; the Profile events SHALL carry `entity_type = 'Profile'` with payload `{profile_id, state}`.

The `CancelProfile` transition (`→ Cancelled`) and **all** Account transitions (`SuspendAccount`/`ReactivateAccount`/`CloseAccount`) SHALL record **no** domain event (audit-only — § 15.2 names no `ProfileCancelled`; § 15 names no Account event). **No** event name outside the eight-name set above SHALL be recorded by this change (no `ProfileLapsed`, `ProfileCancelled`, `AccountSuspended`, `AccountClosed`, `WaitingListJoined` or `CustomerSegmentChanged`). A directly-invoked Profile or Customer transition SHALL record a **root** event (no `causation_id`; `correlation_id` defaults to its own `event_id`); a `ProfileSuspended`/`ProfileReactivated` recorded **inside** the `SuspendCustomer`/`ReactivateCustomer` cascade SHALL be a **causation child** of the parent `CustomerSuspended`/`CustomerReactivated` (its `causation_id` and `correlation_id` set to the parent), so the cascade is one honest causal chain.

#### Scenario: Each status transition records its verbatim event PII-free

- **WHEN** any of the eight evented transitions runs
- **THEN** exactly its corresponding event is recorded in the writing transaction, tagged module `parties`, with the entity type/id and an `actor_role` from `ActorContext`, and its payload contains only entity ids and enum/business values (no name, email, phone or date of birth)

#### Scenario: Cancellation and Account transitions are audit-only; nothing outside the set fires

- **WHEN** `CancelProfile`, `SuspendAccount`, `ReactivateAccount` or `CloseAccount` runs
- **THEN** no domain event is recorded for that transition, and across the whole change no event name outside `{CustomerSuspended, CustomerReactivated, CustomerClosed, ProfileSuspended, ProfileReactivated, ProfileExpired, ProfileRenewed, ProfileInactive}` is recorded

#### Scenario: A cascaded Profile event is a causation child of the Customer event

- **WHEN** `SuspendCustomer` cascades `ProfileSuspended` to a Profile
- **THEN** that `ProfileSuspended` carries `causation_id` and `correlation_id` referencing the same-transaction `CustomerSuspended` root event

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 15.1 (`CustomerSuspended` — cascades to all Profiles; `CustomerReactivated`; `CustomerClosed` — terminal), § 15.2 (`ProfileSuspended`/`ProfileReactivated`/`ProfileExpired`/`ProfileRenewed`/`ProfileInactive`; the family names no `ProfileCancelled`), § 15 (no Account-family event), § 15.9 (lifecycle-event naming convention) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-EVT-1 (the Customer events), AC-K-EVT-5 (the Profile events), AC-K-BR-Profile-4 (every Profile-status-boundary transition fires its event) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional outbox; PII-free payloads; root `correlation_id` = own `event_id`; causation chains) · openspec/specs/event-substrate/spec.md (Transactional Event Recording; Domain Event Envelope) · CLAUDE.md invariants 4 & 10._

### Requirement: Club Credit Entity and One-Active-Per-Profile Invariant

The system SHALL persist **Club Credit** as a Module K entity (`parties_club_credits`) — a per-Profile **prepayment instrument**, entirely distinct from the Voucher. Each Club Credit SHALL reference exactly one **Profile** (a within-module reference) and SHALL carry: an `amount` and a `remaining` balance, each an integer count of minor units plus an ISO 4217 currency code (a `Money`, never a float — invariant 6); a validity window (`valid_from`, `valid_to`); and a `state` ∈ {`active`, `redeemed`, `forfeited`}. The FSM SHALL be `active → redeemed | forfeited`: a Club Credit is created `active`; `redeemed` and `forfeited` are reached only through the writer Actions; and `redeemed → active` is reachable only via `RestoreClubCredit` (a downstream order-cancellation effect, not a Club Credit primitive). The `amount` and `remaining` currencies SHALL be equal and SHALL be **immutable** across the credit's lifetime (set once at issuance).

At most **one `active` Club Credit per Profile** SHALL exist at any moment. This invariant SHALL be enforced **structurally** by a partial unique index on `(profile_id)` scoped to `state = 'active'` — so a `redeemed` or `forfeited` credit frees the slot and the next issuance inserts cleanly, while a second concurrent `active` insert for the same Profile is rejected at the database level.

#### Scenario: A Club Credit carries amount, remaining, validity and an FSM state

- **WHEN** a Club Credit is created
- **THEN** it references exactly one Profile, carries `amount` and `remaining` as `Money` (integer minor units + ISO 4217 currency), a `valid_from`/`valid_to` window, and `state = active`, with `amount` and `remaining` sharing one immutable currency

#### Scenario: At most one active Club Credit per Profile (structural)

- **GIVEN** a Profile that already holds an `active` Club Credit
- **WHEN** a second `active` Club Credit is inserted for that Profile
- **THEN** the partial unique index rejects it (the one-active invariant holds at the database level)

#### Scenario: A terminal credit frees the slot

- **GIVEN** a Profile whose only Club Credit is `redeemed` or `forfeited`
- **WHEN** a new `active` Club Credit is inserted for that Profile
- **THEN** it inserts cleanly (the partial index covers only `active` rows)

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md §11 (Club Credit — per-Profile prepayment instrument; fields: Profile reference, amount + currency, status lifecycle `active → redeemed | forfeited` with `redeemed → active` only on order cancellation, validity window, remaining balance; "only one active Club Credit per Profile at any moment"; currency immutable across lifetime) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-17 (Club Credit FSM + one-active-per-Profile invariant preserved across all paths) · spec/04-decisions/decisions.md DEC-007 (hero-package fee → club credit) · CONTEXT.md (Club Credit — a monetary credit entity attached to a membership, distinct from Voucher) · CLAUDE.md invariant 6 (money = integer minor units + currency) · openspec/specs/money/spec.md (Money Value Object) · openspec/specs/party-registry/spec.md (Profile — Multi-Profile Membership — the within-module parent)._

### Requirement: Club Credit Issuance

A Club Credit SHALL be issued by an explicit within-module `IssueClubCredit` Action — the sole creator of a Club Credit row — running inside one `DB::transaction`. Issuance SHALL be gated on the Profile's Club having `generates_credit = true`; an issuance for a Profile whose Club has `generates_credit = false` SHALL be rejected with a localized exception and create no row. The issued credit's `amount` SHALL equal the Club's `fee` **verbatim** (both minor units and currency) — at launch the welcome-window proportional scaling (K.18) is deferred, so **full fee → full credit**; `remaining` SHALL be initialized equal to `amount`; `valid_from` SHALL be the issuance moment and `valid_to` SHALL default to **31 December of the issuance year**; and the credit SHALL be created in `state = active`. A Club with `generates_credit = true` but **no `fee`** cannot define an amount; such an issuance SHALL be rejected (no zero/undefined credit).

In production the issuance trigger is Module E's `MembershipFeePaid` event, gated on payment-provider-confirmed payment success (§11.1); Module E does not exist, so the **`MembershipFeePaid` listener is a deferred Module-E seam** — `IssueClubCredit` is the within-module writer, invoked by the operator/seam path now and directly in tests, and **no** Module-E event contract is fabricated. Issuance SHALL NOT be blocked by Holds (the entitlement is recorded once the fee is paid — only redemption is Hold-gated, §11.2). The operator manual-issuance path (K.19) SHALL NOT be built at launch (launch goodwill routes through the Module S `REFUND_COMPENSATION` coupon); the `IssueClubCredit` writer is itself the retained K.19 seam.

#### Scenario: Issue a full-fee credit on a credit-generating Club

- **GIVEN** a Profile whose Club has `generates_credit = true` and a non-null `fee`, and no `active` Club Credit
- **WHEN** `IssueClubCredit` is invoked for that Profile
- **THEN** a Club Credit is created `active` with `amount` equal to the Club's `fee` (minor units + currency), `remaining` equal to `amount`, `valid_from` the issuance moment and `valid_to` 31 December of the issuance year

#### Scenario: Issuance is refused when the Club does not generate credit

- **WHEN** `IssueClubCredit` is invoked for a Profile whose Club has `generates_credit = false`
- **THEN** the issuance is rejected and no Club Credit row is created

#### Scenario: Issuance is refused when the Club has no fee

- **WHEN** `IssueClubCredit` is invoked for a Profile whose Club has `generates_credit = true` but `fee` is null
- **THEN** the issuance is rejected (no amount can be defined) and no Club Credit row is created

#### Scenario: The fee-paid trigger is a deferred Module-E seam

- **WHEN** the Parties code surface is inspected
- **THEN** `IssueClubCredit` exists as the within-module writer of issuance, and there is **no** `MembershipFeePaid` listener and no fabricated Module-E event class in this change

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md §11.1 (issuance — auto-generated when Module K consumes Module E's `MembershipFeePaid` and the Profile's Club has `generates_credit = true`; gated on payment-provider-confirmed success; K.18 welcome-window scaling DEFERRED — launch full-fee → full-credit, the `policy × (fee_paid/full_fee)` hook retained in Module K; K.19 operator manual issuance DEFERRED with retained seam), §11 (validity window default 31 Dec of issuance year; amount/currency set at issuance), §11.2 (issuance not Hold-gated; only redemption is) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-J-16 (auto-issuance on `generates_credit = true`), AC-K-MVP-3 (K.18 deferred-with-seam — launch full-fee → full-credit), AC-K-MVP-4 / AC-K-J-16a (K.19 operator manual issuance deferred; goodwill via REFUND_COMPENSATION), AC-K-J-17 (welcome-window scaling criterion retained for restore) · spec/04-decisions/decisions.md DEC-007 (fee → club credit) · openspec/changes/archive/2026-06-19-parties-membership-activation (design L5 — the `MembershipFeePaid` deferred-Module-E-seam precedent) · decisions/2026-06-12-event-substrate-and-audit-store.md._

### Requirement: Club Credit Redemption and Carry-Forward

A Club Credit SHALL be redeemed by an explicit within-module `ApplyClubCredit` Action — the sole writer of `remaining` and of the `active → redeemed` transition — running inside one `DB::transaction` against a transaction-locked re-read. Given a redeemed amount (a `Money`), the Action SHALL, before any write: reject unless the credit is `active`; reject unless the redeemed amount's currency equals the credit currency; and reject unless the redeemed amount does not exceed `remaining` (no negative balance — a package exceeding the credit applies the full `remaining` and the difference is paid in cash, a Module S concern). It SHALL then set `remaining = remaining − redeemed`: if the new `remaining` is **zero**, the credit SHALL transition to `redeemed`; if the new `remaining` is **positive**, the credit SHALL stay `active` and the balance **carries forward** for future purchases (**K.17**).

Redemption SHALL be **frozen while the owning Profile is suspended**: `ApplyClubCredit` SHALL reject when the Profile's `state = Suspended` (no redemption during suspension — AC-K-FSM-2a; the credit becomes mutable again once the Profile is restored). The data Module K exposes for eligibility is the credit's `active` state, its `remaining`, its currency, and its issuing Club (via `profile.club_id`); the **checkout decision** — Offer matching (`credit.profile.club_id ∈ offer.club_ids`), currency-match at price resolution, the coupon **mutual-exclusion** (one coupon XOR one Club Credit per checkout), auto-apply, and the Hold-gated price resolution — is a **Module S** concern and SHALL NOT be built in this change.

#### Scenario: Partial redemption carries the balance forward (K.17)

- **GIVEN** an `active` Club Credit with `remaining` of 25000 minor units (EUR)
- **WHEN** `ApplyClubCredit` redeems 9000 minor units (EUR)
- **THEN** `remaining` becomes 16000 minor units and the credit stays `active` (the balance carries forward to future purchases)

#### Scenario: Full redemption transitions to redeemed

- **GIVEN** an `active` Club Credit with `remaining` of 16000 minor units (EUR)
- **WHEN** `ApplyClubCredit` redeems 16000 minor units (EUR)
- **THEN** `remaining` becomes zero and the credit transitions to `redeemed`

#### Scenario: Over-application and currency mismatch are rejected

- **WHEN** `ApplyClubCredit` redeems more than `remaining`, or redeems an amount whose currency differs from the credit currency
- **THEN** the Action is rejected with a localized exception and the credit's `remaining` and `state` are unchanged

#### Scenario: Redemption is frozen while the Profile is suspended

- **GIVEN** an `active` Club Credit whose owning Profile is `Suspended`
- **WHEN** `ApplyClubCredit` is invoked
- **THEN** it is rejected (the credit is frozen during suspension); after the Profile is restored to `Active`, the same redemption succeeds

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md §11.2 (redemption — against the issuing Club only, `credit.profile.club_id ∈ offer.club_ids`, currency-match required; mechanics live in Module S; Module K provides the eligibility data; redemption Hold-gated, issuance not), §11 (remaining balance / K.17 partial-redemption carry-forward — "carries forward for future purchases until forfeiture"; full redemption is the norm, the Customer pays any difference), §11.5 (commercial-coupon mutual-exclusion at checkout — Module S enforcement), §10.1 (Club Credit frozen, no accrual/redemption while the Profile is suspended; mutable again on restore) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-J-18 (redemption against the issuing Club; partial redemption leaves a `remaining_balance` that carries forward; cross-club rejected), AC-K-FSM-2a (Profile suspension freezes Club Credit — no accrual, no redemption) · spec/04-decisions/decisions.md DEC-110 (price-resolution stacking; promo + club credit mutually exclusive — Module S), DEC-111 (club-credit auto-apply at checkout — Module S) · openspec/specs/party-registry/spec.md (Profile Suspension and Restoration — the `Suspended` state this freeze reads)._

### Requirement: Club Credit Forfeiture and Restoration

A Club Credit SHALL be forfeited by an explicit within-module `ForfeitClubCredit` Action — the sole writer of the `active → forfeited` transition — running inside one `DB::transaction` against a transaction-locked re-read; it SHALL reject unless the credit is `active`, and `forfeited` SHALL be **terminal** (at most one forfeiture per credit lifetime). A Club Credit SHALL be restored by an explicit within-module `RestoreClubCredit` Action — the sole writer of the `redeemed → active` transition — which SHALL reject unless the credit is `redeemed` **and** the Profile holds no other `active` Club Credit (the one-active invariant is respected, not violated).

The **forfeiture triggers** (§11.3) are documented cross-module / scheduler seams **not** wired by this change: year-end lapse past `valid_to` (a **scheduler** seam — mirrors the `LapseProfile` validity-period seam); renewal-triggered replacement (**forfeit-before-issue**, sequenced within the Module-E renewal-time `MembershipFeePaid` consumption); Profile cancellation (a within-module follow-on cascade); and Club closure, on which the residual balance is converted to Discovery store credit at face value with 12-month validity (**DEC-043**) — an operation **owned by Module S**, with Module K's role ending at the upstream cancellation/closure signal (**AC-K-XM-23**). The **forfeit-before-issue ordering** is nonetheless exercised at launch: because the one-active invariant makes `IssueClubCredit` reject when an `active` credit exists, re-issuance requires `ForfeitClubCredit` then `IssueClubCredit`. The **order-cancellation-window** trigger for `RestoreClubCredit` is likewise a Module-S seam; the writer ships and is tested directly.

#### Scenario: Forfeit an active credit (terminal)

- **WHEN** `ForfeitClubCredit` is invoked on an `active` credit
- **THEN** its `state` becomes `forfeited`; any subsequent forfeiture, apply or restore on it is rejected (terminal)

#### Scenario: Forfeit-before-issue ordering holds via the one-active invariant

- **GIVEN** a Profile with an `active` Club Credit
- **WHEN** `IssueClubCredit` is invoked again for that Profile
- **THEN** it is rejected (one-active); and after `ForfeitClubCredit` forfeits the existing credit, `IssueClubCredit` then succeeds — the forfeit-before-issue ordering the renewal listener will perform

#### Scenario: Restore a redeemed credit, one-active-respecting

- **GIVEN** a `redeemed` Club Credit whose Profile holds no other `active` credit
- **WHEN** `RestoreClubCredit` is invoked
- **THEN** the credit returns to `active` with its `remaining` restored
- **WHEN** instead the Profile already holds another `active` credit
- **THEN** `RestoreClubCredit` is rejected (the one-active invariant is preserved)

#### Scenario: Club-closure conversion is not owned by Module K

- **WHEN** the Parties code surface is inspected
- **THEN** there is **no** Club-Credit-to-store-credit conversion logic in Module K (the DEC-043 conversion is a Module S concern); Module K ships only the `ForfeitClubCredit` writer and ends at the upstream signal

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md §11.3 (forfeiture triggers: year-end lapse past `valid_to` via a daily job; renewal-triggered replacement — forfeit-before-issue, sequenced within the renewal-time `MembershipFeePaid` consumption; Profile cancellation; Club closure → DEC-043 conversion owned by Module S; at most one forfeiture per lifetime — terminal), §11 (status lifecycle — `redeemed → active` only on order cancellation within the cancellation window, a downstream effect) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-17 (forfeiture paths + one-active invariant preserved across all paths; club-closure forfeiture = DEC-043 conversion trigger owned by Module S), AC-K-XM-23 (Module K does NOT execute the conversion math; its role ends at the upstream signal) · spec/04-decisions/decisions.md DEC-043 (Club Credit → Discovery store credit at face, 12-month validity, on producer offboarding) · openspec/specs/party-registry/spec.md (Profile Lapse and Grace Renewal — the `LapseProfile` scheduler-seam precedent)._

### Requirement: Club Credit State Recording Is Module-E-Owned

Module K SHALL NOT emit any Club Credit lifecycle domain event. The events `ClubCreditIssued`, `ClubCreditApplied`, `ClubCreditRestored` and `ClubCreditForfeited` — and the upstream `MembershipFeePaid` — are **Module E's** events (§11.4 / §15.8); Module K consumes them and records the resulting state on its own Club Credit entity. Because Module E does not exist (Phase 6), this change SHALL build the within-module writer Actions as **audit-only** state writers — they `update()` the credit `state`/`remaining` and record **no** domain event — and SHALL fabricate **no** `MembershipFeePaid` or `ClubCredit*` event class. This mirrors the audit-only-write precedent (a KYC transition records `kyc_status` with no KYC event; the Account family writes `status` with no event). When Module E lands, its `MembershipFeePaid` listener and its `ClubCredit*` consumers SHALL invoke these same within-module writers; the entity-state authority (Module K) and the financial-event authority (Module E) compose without rework.

#### Scenario: No Club Credit domain event is emitted by Module K

- **WHEN** any Club Credit writer (`IssueClubCredit` / `ApplyClubCredit` / `ForfeitClubCredit` / `RestoreClubCredit`) runs
- **THEN** the credit's state is updated and **no** `domain_events` row named `ClubCreditIssued` / `ClubCreditApplied` / `ClubCreditRestored` / `ClubCreditForfeited` (or `MembershipFeePaid`) is recorded by Module K

#### Scenario: No Module-E event class is fabricated

- **WHEN** the Parties code surface is inspected
- **THEN** no `MembershipFeePaid`, `ClubCreditIssued`, `ClubCreditApplied`, `ClubCreditRestored` or `ClubCreditForfeited` event class exists under `app/Modules/Parties` (the §11.4 ownership boundary; the invent-no-event discipline)

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md §11.4 (Module K does NOT emit Club Credit lifecycle events; Module E emits `ClubCreditIssued` / `ClubCreditApplied` / `ClubCreditRestored` / `ClubCreditForfeited`; Module K consumes and records the resulting state), §15.8 (events Module K consumes, recorded by Module E — `MembershipFeePaid` + the four `ClubCredit*`), §14 BR-K-Contract-2 (Module K records state; Module E records the financial events; Xero decides GL treatment) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-J-16 / AC-K-FSM-17 (Module K records the Club Credit state) · openspec/changes/archive/2026-06-19-parties-membership-activation (design L2 — the audit-only-write precedent: a status transition that records no domain event; L5 — no Module-E contract fabricated) · decisions/2026-06-12-event-substrate-and-audit-store.md (the `domain_events` outbox — what is and is not recorded) · decisions/2026-06-11-modular-monolith-architecture.md (events are the inter-module API; the R-reconciliations of event ownership) · CLAUDE.md invariant 4 (financial immutability — Module E is the financial event recorder)._

### Requirement: Customer Anonymisation (Right-to-Erasure)

NewCo SHALL provide an `AnonymiseCustomer` operator action that executes the GDPR right-to-erasure by **overwriting personal data in place** (never deleting rows), preserving the keyed transactional history for the retention window. In one `DB::transaction` against a transaction-locked re-read, and subject to the *Anonymisation Hold Precedence* gate, the action SHALL: (a) overwrite the Customer's PII fields — `name`, `email`, `phone`, `date_of_birth` — and the personal fields of every Address scoped to that Customer with **deterministic, per-Customer-unique placeholders** (reproducible, derived from the Customer id, **never random**) so that the globally-unique-email invariant is preserved; (b) set the Customer's `anonymised_at` timestamp; (c) **redact the Customer's own `audit_records`** by nulling their `before`/`after` snapshots via the reserved redaction path (the sole mutation the audit immutability triggers permit); and (d) record a **PII-free** `CustomerAnonymised` domain event carrying only the Customer id and `anonymised_at`. All FK-linked transactional history (Profile, Order, Voucher, Invoice) SHALL survive keyed by the now-anonymised Customer and remain queryable via the opaque anonymised identifier; vouchers SHALL remain valid.

Anonymisation SHALL be **orthogonal** to the Customer status FSM (`pending | active | suspended | closed`): the action SHALL NOT change `status`; a Customer in **any** status (typically `closed`) MAY be anonymised; and an anonymised Customer SHALL retain its status — the two are **independent operations** (`BR-K-Customer-2`). Anonymisation is a boolean-derivable state (`anonymised_at IS NOT NULL`), not a status value. Re-invoking the action on an already-anonymised Customer SHALL be an **idempotent no-op** that changes nothing and records **no** second `CustomerAnonymised`.

#### Scenario: Anonymisation severs PII and preserves keyed history

- **WHEN** `AnonymiseCustomer` is invoked on a Customer (not blocked by a `compliance` Hold) who owns Profiles and an Address
- **THEN** the Customer's `name`/`email`/`phone`/`date_of_birth` and the Address's personal fields are overwritten with deterministic placeholders, `anonymised_at` is set, the Profile/Address rows are **not** deleted (they survive keyed to the Customer), and the Customer remains queryable only as the opaque anonymised identifier

#### Scenario: Placeholders are deterministic and per-Customer-unique

- **WHEN** two distinct Customers are anonymised
- **THEN** each receives placeholders derived from its own id, the two anonymised emails differ (the globally-unique-email invariant holds), and re-deriving a placeholder for the same Customer yields the same value (reproducible, never random)

#### Scenario: Anonymisation is orthogonal to closure

- **GIVEN** a Customer in `closed`
- **WHEN** `AnonymiseCustomer` is invoked
- **THEN** the Customer is anonymised (PII overwritten, `anonymised_at` set), its `status` remains `closed` (the action writes no status transition and records no `CustomerClosed`/`CustomerReactivated`), and it stays admin-queryable as an opaque identifier

#### Scenario: CustomerAnonymised is PII-free

- **WHEN** a Customer is anonymised
- **THEN** exactly one `CustomerAnonymised` domain event is recorded in the same transaction, tagged module `parties` with the Customer's entity type and id, whose payload contains only the id and `anonymised_at` — no name, email, phone, date of birth or address

#### Scenario: The Customer's audit records are redacted

- **WHEN** a Customer whose `audit_records` carry personal data in their `before`/`after` snapshots is anonymised
- **THEN** those snapshots are nulled via the redaction path (the only mutation the immutability triggers permit — the rows themselves are neither deleted nor structurally altered), so no PII survives in the append-only audit trail

#### Scenario: Re-anonymising is an idempotent no-op

- **WHEN** `AnonymiseCustomer` is invoked on a Customer whose `anonymised_at` is already set
- **THEN** nothing changes and no second `CustomerAnonymised` event is recorded

_Source: spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 2 AC-K-J-9 (soft-delete + anonymisation FLOOR — overwrite Customer PII + Address personal fields with deterministic placeholders; HubSpot sync removes PII; history survives keyed by the anonymised identifier; vouchers remain valid), § 3 AC-K-FSM-16 (anonymisation records the moment; orthogonal to `closed`), § 4.2 AC-K-BR-Customer-2 · spec/02-prd/Module_K_PRD_v0.3-MVP.md § 8.2 (soft-delete + anonymisation; overwrite-in-place; deterministic placeholders; keyed history; vouchers valid; closed/anonymised orthogonal), § 12 (right to erasure), § 14.2 BR-K-Customer-2 · spec/04-decisions/decisions.md DEC-027 (GDPR posture — soft-delete + anonymise + 10-yr retention) · openspec/specs/event-substrate/spec.md (the `audit_records` before/after redaction path; the `redactor` role reserved for Module K's erasure job) · decisions/2026-06-12-event-substrate-and-audit-store.md + decisions/2026-06-15-identity-auth.md (the erasure seam this change completes; the RM-09 correction) · decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md (the `CustomerAnonymised` PII-free event added over the frozen spec's event-free anonymisation, as the HubSpot seam) · CLAUDE.md invariants 4 (financial immutability — corrections not deletes), 8 (audit envelope — append-only + GDPR redaction), 10 (module boundaries)._

### Requirement: Anonymisation Hold Precedence

The `AnonymiseCustomer` action SHALL enforce a regulatory-retention **Hold-precedence gate**: anonymisation SHALL be **blocked while the Customer is covered by an active `compliance` Hold**, and **no other Hold type** SHALL block anonymisation. Coverage SHALL be read through the existing within-module Hold read-contract (an active `compliance` Hold on the Customer scope). When blocked, the action SHALL raise a **localized exception**, leave the Customer **un-anonymised** (no PII overwrite, no `anonymised_at`, no audit redaction, no Address overwrite), and record **no** `CustomerAnonymised`. When the Customer's only active Holds are non-`compliance` types, anonymisation SHALL **proceed**: because Hold `reason`/`lift_reason` are controlled non-PII strings (never personal data), no Hold-metadata overwrite is required — the frozen spec's "Hold metadata anonymises alongside the PII" is satisfied by construction — and each Hold's structural blocking state is preserved.

This adopts canon **MVP-DEC-015** (`compliance`-only over the full Hold-type set; **no separate `sanctions` Hold type**) per `decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md`, which also resolves the frozen-spec contradiction (greenfield `DEC-027`: only `sanctions` blocks; PRD §8.2 / `AC-K-J-9a`: `compliance` + sanctions-OFAC block). A sanctioned Customer whose identifiable data must be retained is gated by Compliance **placing a `compliance` Hold** — sanctions state lives in the separate `sanctions_status` FSM, not a Hold type.

#### Scenario: A compliance Hold blocks anonymisation

- **GIVEN** a Customer covered by an active `compliance` Hold
- **WHEN** `AnonymiseCustomer` is invoked
- **THEN** the action raises a localized exception, the Customer is left un-anonymised (`anonymised_at` still NULL, PII intact, Address intact, audit un-redacted), and no `CustomerAnonymised` event is recorded

#### Scenario: A non-compliance Hold does not block anonymisation

- **GIVEN** a Customer whose only active Hold is a non-`compliance` type (e.g. `payment` or `fraud`) and no active `compliance` Hold
- **WHEN** `AnonymiseCustomer` is invoked
- **THEN** anonymisation proceeds (PII overwritten, `anonymised_at` set, `CustomerAnonymised` recorded), and the non-`compliance` Hold's structural blocking state is preserved

#### Scenario: Lifting the blocking compliance Hold unblocks anonymisation

- **GIVEN** a Customer whose anonymisation was blocked by an active `compliance` Hold
- **WHEN** the `compliance` Hold is lifted and `AnonymiseCustomer` is invoked again
- **THEN** anonymisation proceeds and completes

_Source: spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 2 AC-K-J-9a (GDPR right-to-erasure × active-Hold precedence: regulatory Holds block, non-regulatory do not; blocked request leaves the Customer informed; non-regulatory path proceeds with Hold metadata anonymised alongside) · spec/02-prd/Module_K_PRD_v0.3-MVP.md § 8.2 (Stage 6.5 DEC-027 — per-Hold-type anonymisation precedence) · spec/04-decisions/decisions.md DEC-027 (Stage-6.5 clarification — regulatory-retention Holds block) · decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md (canon MVP-DEC-015 — `compliance`-only; no separate `sanctions` Hold type; resolves the frozen-spec DEC-027-vs-PRD contradiction; sanctions-retention via a `compliance` Hold) · openspec/specs/party-registry/spec.md (the *Hold and Sanctions Read-API* requirement — the within-module `PartyComplianceStatusReader` coverage read) · CLAUDE.md invariant 7 (compliance / Hold gates; Holds never auto-lifted)._

### Requirement: Customer Address

Module K SHALL model an **Address** entity (`parties_addresses`) scoped to the Customer (the natural person; the Customer record itself carries no company data and no B2C/B2B discriminator). A Customer MAY have zero or more Addresses (one-to-many, **within-module** — no cross-module Eloquent relationship or model import). Each Address SHALL carry the standard personal address fields (address lines, locality, region, postal code, country) and, for the **company-billing affordance** (DEC-068), **optional** `company_name` and `vat_id` fields — supporting an individual collector who transacts through their own company for fiscal reasons; the Customer remains the natural person. On anonymisation (see *Customer Anonymisation (Right-to-Erasure)*) the Address's **personal fields** (and any `company_name` / `vat_id`) SHALL be overwritten with deterministic placeholders in the **same** operation as the Customer PII overwrite, and the Address row SHALL be **preserved** (never deleted). At launch only **billing** Addresses are modelled; shipping Addresses and the "Address used at purchase" invoice snapshot are downstream concerns (Module C / Module S+E) and are out of this change.

#### Scenario: A Customer has billing Addresses with optional company fields

- **WHEN** a billing Address is created for a Customer with a `company_name` and `vat_id`
- **THEN** the Address is persisted scoped to that Customer, carries the personal address fields plus the optional `company_name`/`vat_id`, and the Customer record itself carries no company data and no B2C/B2B discriminator

#### Scenario: Address personal fields are overwritten on anonymisation, row preserved

- **GIVEN** a Customer with one or more Addresses
- **WHEN** the Customer is anonymised
- **THEN** each Address's personal fields (and any `company_name`/`vat_id`) are overwritten with deterministic placeholders in the same operation, and every Address row survives (none is deleted)

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (company-billing affordance at Address level — optional company name + VAT id; Customer stays the natural person; no B2C/B2B discriminator), § 8.2 (Address records scoped to the Customer have their personal fields overwritten on anonymisation), § 16 (Module K stores company-billing fields on Address; Module E reads them for invoicing) · spec/04-decisions/decisions.md DEC-068 (B2B dropped at Customer level; company-billing preserved at Address — `BillingAddress` with optional `company_name` + `vat_id`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 6.6 AC-K-XM-25 (company-billing fields exist on Address; Customer carries no B2C/B2B discriminator) · CLAUDE.md invariant 10 (module boundaries — within-module Eloquent only)._

### Requirement: Customer Data Export

Module K SHALL provide a minimal, synchronous `ExportCustomerData` operator action satisfying the GDPR right of access / data portability (canon `J-9b`). Given a Customer, the action SHALL assemble a structured **in-memory** payload containing the Customer's personal data and a **manifest of references** (by id) to the retained transactional history (the Customer's Profiles, and the downstream Order / Voucher / Invoice references as those exist), and SHALL return it to the operator **without persisting any file**. The export SHALL be **read-only**: it SHALL NOT mutate the Customer, SHALL NOT emit a domain event, and SHALL leave no durable artifact. For an **anonymised** Customer the export SHALL reflect the anonymised (placeholder) PII, not the original data.

#### Scenario: Export assembles PII plus a transactional-history manifest, read-only

- **WHEN** `ExportCustomerData` is invoked on a Customer with Profiles
- **THEN** it returns a structured in-memory payload containing the Customer's personal data and a manifest referencing the Customer's transactional history by id, and the Customer is unchanged

#### Scenario: Export persists nothing and emits no event

- **WHEN** `ExportCustomerData` is invoked
- **THEN** no file or durable artifact is written and no domain event is recorded (a read-only assembly)

#### Scenario: Export of an anonymised Customer returns placeholder PII

- **GIVEN** an anonymised Customer
- **WHEN** `ExportCustomerData` is invoked
- **THEN** the returned payload reflects the anonymised placeholder PII, not the original personal data

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 12 (right of access / data portability — a Customer can request export of their personal data + transactional history in a standard format; operationally executed, "not modelled as a state machine") · spec/04-decisions/decisions.md DEC-027 (data-subject rights include portability) · docs/validation/Module_K_Verdict_v0.3-MVP.md § Canon Overlay (`J-9b` data-export, NEW — no committed canon definition exists) · decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md (canon J-9b scoped minimal / synchronous / in-memory to satisfy the compliance narrative without inventing an async/document pipeline or tripping the undecided object-storage ADR — the mechanism is the design decision recorded there)._

### Requirement: Enhanced-KYC Threshold Detection

NewCo SHALL detect when a Customer crosses an enhanced-KYC monetary threshold and escalate the Customer to enhanced review. The two thresholds are **independent (OR)** triggers, each measured in EUR minor units: a **single completed transaction ≥ €10,000**, OR a **rolling trailing-12-month cumulative purchase total ≥ €50,000** (DEC-035). Detection SHALL read the Customer's transaction totals only through the within-module `CustomerTransactionTotalsReader` contract — never by cross-module database access to Module S (invariant 10); the real source is Module S order/invoice history (deferred), and the launch binding SHALL be a null adapter returning zero totals, so detection is a correct no-op until Module S provides the data (a documented seam).

Detection SHALL be **idempotent per Customer, latched on `enhanced_kyc_flag`**: the escalation fires at most once, on the first crossing. On a first crossing, in one `DB::transaction` against a transaction-locked re-read, the workflow SHALL: (a) set `enhanced_kyc_flag = true` and stamp `enhanced_kyc_at`; (b) create exactly one Compliance review-queue entry recording the tripping `threshold_kind` and amount (per the *Compliance Review Queue* requirement); (c) record the PII-free `CustomerEnhancedKycReviewRequired` event; and (d) initiate the lightweight AML re-screen by recording an `under_review` sanctions verdict with `trigger_source = aml_threshold` through the sole sanctions-writer (per the *Customer Sanctions Screening Lifecycle* requirement), which blocks the Customer from transacting until Compliance resolves it. When `enhanced_kyc_flag` is already set, detection SHALL be a **no-op** — no second review-queue entry, no second event, no sanctions write.

Detection SHALL run via **two trigger paths sharing one workflow**: (i) a **periodic background job** — a daily scheduled command evaluating every Customer, built in this change and running as a scheduler tick (not a queued consumer); and (ii) an **at-order-completion check** for the just-completed order's Customer, invoking the same workflow — its trigger wired by Module S when checkout lands (deferred). Both paths SHALL produce identical Customer state and identical recorded events.

#### Scenario: A single transaction ≥ €10k triggers escalation

- **WHEN** the totals reader reports a largest single transaction ≥ €10,000 EUR for a Customer whose `enhanced_kyc_flag` is not set
- **THEN** `enhanced_kyc_flag` is set and `enhanced_kyc_at` stamped, one Compliance review-queue entry recording the `single_transaction` trigger is created, a `CustomerEnhancedKycReviewRequired` event is recorded, and the Customer's `sanctions_status` becomes `under_review` with `trigger_source = aml_threshold`

#### Scenario: Cumulative annual ≥ €50k triggers the same escalation

- **WHEN** the totals reader reports a rolling trailing-12-month cumulative total ≥ €50,000 EUR (via several sub-threshold transactions) for an un-flagged Customer
- **THEN** the same downstream signals occur — flag + timestamp, one review-queue entry recording the `cumulative_annual` trigger, the `CustomerEnhancedKycReviewRequired` event, and `sanctions_status = under_review` with `trigger_source = aml_threshold`

#### Scenario: Detection is idempotent for an already-flagged Customer

- **WHEN** detection evaluates a Customer whose `enhanced_kyc_flag` is already set (e.g. the daily scan runs again while the Customer is still above threshold)
- **THEN** nothing changes — no second review-queue entry, no second event, and no sanctions write

#### Scenario: Both trigger paths produce identical state

- **WHEN** the same crossing is evaluated through the periodic scan and (when wired) the at-order-completion check
- **THEN** the resulting Customer state and the recorded events are identical (the two paths invoke one workflow)

#### Scenario: The totals source is a deferred Module-S seam

- **WHEN** the Parties code surface is inspected at launch
- **THEN** `CustomerTransactionTotalsReader` is bound to a null adapter returning zero totals (no cross-module access to Module S order data), so the periodic scan runs and detects nothing until Module S provides the real adapter

_Source: spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 2 AC-K-J-7a (single-tx €10k / cumulative-annual €50k → enhanced-KYC flag + timestamp + Compliance review-queue entry; both periodic-job and at-order-completion paths; assert identical state) · § 5.4 AC-K-EVT-12a (AML-threshold breach → lightweight re-screen + trigger-source aml-threshold) · spec/02-prd/Module_K_PRD_v0.3-MVP.md § 9.1 (DEC-035 — enhanced-KYC trigger: crosses €10,000 single OR €50,000 cumulative annual; detection runs both as a periodic background job and at order completion; no state machine beyond the flag + timestamp), § 9.2 (DEC-030 E6-10 — AML-threshold detection, daily scan of cumulative annual totals, fires the lightweight DEC-030 sanctions re-check + the enhanced-KYC review-queue entry), § 9.5 (manual-first launch posture — the screen is the floor, the vendor integration deferrable) · spec/04-decisions/decisions.md DEC-035, DEC-030 · decisions/2026-06-12-event-substrate-and-audit-store.md (scheduler ticks are not queued consumers — the queue-driver gate is not tripped) · CLAUDE.md invariants 6 (money = minor units + currency), 7 (compliance gates; blocks never auto-lifted), 10 (module boundaries — events + read contracts, no cross-module DB access)._

### Requirement: Compliance Review Queue

NewCo SHALL maintain a **Compliance review-queue** — a within-module store (`parties_compliance_reviews`) of Compliance work-items raised when a Customer requires review. Each entry SHALL record: the Customer it concerns (a `customer_id` FK to `parties_customers`; no cross-module relation), a `reason` (the enhanced-KYC threshold breach — `enhanced_kyc_threshold` — is the sole reason in this change), the tripping `threshold_kind` (`single_transaction` | `cumulative_annual`), the tripping amount as **EUR minor units + currency code** (money discipline, invariant 6), and a `resolved_at` timestamp that is NULL on creation. Open vs resolved SHALL be **boolean-derivable** (`resolved_at IS NOT NULL`), NOT an FSM — enhanced-KYC is handled operationally, with no state machine (§ 9.1). Entries SHALL be created only by the *Enhanced-KYC Threshold Detection* workflow.

Creating a review-queue entry SHALL record a **PII-free** `CustomerEnhancedKycReviewRequired` domain event carrying only the `customer_id`, `enhanced_kyc_at`, `threshold_kind`, and the tripping amount (minor units + currency) — never `name` / `email` / `phone` / `date_of_birth` (the 10-year event store holds no PII). It is a net-new event over the frozen § 15.6 catalog (which names none), added as the audit anchor for the breach and as the seam a future Compliance surface / Module-E consumer reads.

Resolving a review-queue entry SHALL be handled operationally and is NOT modeled in this change: entries ship creatable + readable (`resolved_at` open), and the operator resolve action is deferred.

#### Scenario: A threshold breach creates one open review-queue entry

- **WHEN** the *Enhanced-KYC Threshold Detection* workflow escalates a Customer
- **THEN** exactly one `parties_compliance_reviews` row exists for that Customer with `reason = enhanced_kyc_threshold`, the tripping `threshold_kind` and amount recorded, and `resolved_at` NULL (open)

#### Scenario: The review event is PII-free

- **WHEN** a review-queue entry is created and `CustomerEnhancedKycReviewRequired` is recorded
- **THEN** the event payload carries only `customer_id`, `enhanced_kyc_at`, `threshold_kind` and the amount (minor units + currency), and none of the Customer's `name` / `email` / `phone` / `date_of_birth`

#### Scenario: The queue is within-module

- **WHEN** the review-queue schema and model are inspected
- **THEN** the entry links to the Customer by `customer_id` (a within-module FK) and holds no relationship to Module S / Module E tables

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 9.2 (DEC-030 E6-10 — the enhanced-KYC review-queue entry created on an AML-threshold breach), § 9.1 (DEC-035 — enhanced-KYC handled operationally at launch; no separate state machine beyond the flag + timestamp), § 2 Personas (the Compliance Reviewer reviews enhanced-KYC threshold trips) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 2 AC-K-J-7a (a Compliance review-queue entry is created) · docs/validation/Remediation_Tracker.md RM-02 (the `enhanced-kyc` review event) · openspec/specs/party-registry/spec.md (the *Customer Anonymisation (Right-to-Erasure)* requirement — the precedent for a net-new PII-free event over the frozen-catalog silence) · decisions/2026-06-12-event-substrate-and-audit-store.md (the PII-free 10-year event store) · CLAUDE.md invariants 6 (money), 10 (module boundaries — within-module FK only)._

### Requirement: Club Registration Flow and Onboarding Channel

A Club's `registration_flow_type` SHALL select the **onboarding entry channel only** — it SHALL NOT be an approval bypass. Producer approval (the atomic approve = charge = activation, § 4.2.1) SHALL be **mandatory for every** `registration_flow_type` value, and **no value SHALL auto-approve** a membership into `Active`. The launch-selectable values SHALL be `application_with_approval` (open self-application, § 7.1 — the default), `invitation_only` (entry only via a producer/operator invitation, § 7.3), and `link_onboarding` (entry via a shared Club link, § 7.2). The `open_registration` (auto-join without approval) value SHALL be **carried latent** in the enum and **SHALL NOT be selectable** at launch (it would contradict the mandatory producer write, DEC-069). The former separate `invite_only` boolean SHALL be **removed and subsumed** — `invite_only = true` is exactly `invitation_only` — so a Club carries **no** `invite_only` attribute distinct from `registration_flow_type`.

#### Scenario: Every launch-selectable flow still routes to mandatory producer approval

- **GIVEN** a Club created with any launch-selectable `registration_flow_type` (`application_with_approval`, `invitation_only`, or `link_onboarding`)
- **WHEN** a Profile application is created against that Club and advanced
- **THEN** the membership still requires the producer/operator approval write to reach `Active` — no `registration_flow_type` value auto-approves it

#### Scenario: The auto-join value is not selectable at launch

- **WHEN** a Club create or update attempts to set `registration_flow_type = open_registration`
- **THEN** it is rejected as not selectable at launch (the value is carried latent), while the three launch-selectable values are admitted

#### Scenario: No separate invite-only attribute exists

- **WHEN** the Club entity and its create surface are inspected
- **THEN** there is no `invite_only` boolean distinct from `registration_flow_type` (the invite-only channel is `invitation_only`)

_Source: canon **MVP-DEC-022** (CML-89 sub-2) / **AC-K-BR-Club-6** (LIVE `cmless/main` @ `360df0b` — `registration_flow` is an entry channel, never an approval bypass; `open` latent; `invite_only` subsumed), adopted locally via this change's **MVP-DEC-022 mini-ADR** `decisions/2026-07-07-adopt-mvp-dec-022-club-membership-governance.md` (tasks 1.x) — absent from the frozen `spec/`@MVP-DEC-007 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.3 (registration-flow type) / § 7.1–§ 7.3 (onboarding flows) / § 4.2.1 (atomic approve = charge = activation; DEC-069 no auto-approve) · app/Modules/Parties/Enums/ClubRegistrationFlowType.php (the four-case enum; `OpenRegistration` becomes latent) · database/migrations/2026_06_15_000003_create_parties_clubs_table.php (the `invite_only` column removed) · decisions/2026-06-21-operator-console-operand-enum-carveout.md · CLAUDE.md invariant 12 (i18n)._

### Requirement: Registration Age Gate

Customer registration SHALL be **blocked** when the prospect's self-attested `date_of_birth` implies an age **below the configured platform minimum at the registration date**, and **no Customer record and no co-provisioned Account SHALL be created**, rejected with a localized `BelowMinimumRegistrationAge` exception. A registration attesting **no** `date_of_birth` at all SHALL likewise be rejected with the same localized exception — age attestation is **mandatory at launch** (design D7; BMD § 2.8; the null→block interpretation recorded in the MVP-DEC-022 mini-ADR) — creating nothing. The minimum age SHALL be an **admin-configurable platform constant** (default **18** — the EU alcohol-purchase baseline across the launch markets), **not hard-coded**; its representation is the dev team's call (DEC-073), mirroring the enhanced-KYC threshold constants (RM-02 / MVP-DEC-014). At launch the check SHALL be **self-attestation** plus the payment-method-bound minimum-age signal — **no physical-document verification** (BMD § 2.8). The gate SHALL apply to **every onboarding entry channel** (§ 7.1 / § 7.2 / § 7.3). A `date_of_birth` at or above the minimum SHALL be admitted; per-shipping-jurisdiction higher floors (e.g. 21 for US destinations) are a post-launch refinement out of this change.

#### Scenario: An under-age registration is rejected and creates nothing

- **WHEN** a Customer registration is submitted with a self-attested `date_of_birth` whose implied age at the registration date is below the configured minimum
- **THEN** a `BelowMinimumRegistrationAge` is raised, and no Customer, no Account and no `CustomerCreated` event are created

#### Scenario: A registration without a date of birth is rejected

- **WHEN** a Customer registration is submitted with no self-attested `date_of_birth`
- **THEN** a `BelowMinimumRegistrationAge` is raised (age attestation is mandatory at launch), and no Customer, no Account and no `CustomerCreated` event are created

#### Scenario: An at-or-over-minimum registration is admitted

- **WHEN** a Customer registration is submitted with a `date_of_birth` whose implied age is at or above the configured minimum
- **THEN** the Customer is created (per *Customer Identity*), with its co-provisioned Account and a `CustomerCreated` event

#### Scenario: The minimum age is a configurable platform constant, not hard-coded

- **WHEN** the age-gate configuration is inspected
- **THEN** the minimum age is a platform-level admin-configurable constant defaulting to 18, and the same gate is evaluated across all onboarding entry channels

_Source: canon **MVP-DEC-022** (CML-89 sub-5b — the BMD-mandated age-gate the Module K PRD had dropped) / **AC-K-BR-Identity-6** (LIVE `cmless/main` @ `360df0b`) + **BMD § 2.8** (mandatory age verification at registration; self-attest + card-on-file; no documents at launch), adopted locally via this change's **MVP-DEC-022 mini-ADR** `decisions/2026-07-07-adopt-mvp-dec-022-club-membership-governance.md` (tasks 1.x) — absent from the frozen `spec/`@MVP-DEC-007 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 7.1 (registration) · app/Modules/Parties/Models/Customer.php (`date_of_birth` already present) · app/Modules/Parties/Actions/CreateCustomer.php (the creation chokepoint) · openspec/specs/party-registry/spec.md (*Customer Identity* — the creation this gate precedes) · MVP-DEC-014 / RM-02 (the platform-constant threshold precedent) · CLAUDE.md invariant 12 (i18n)._

### Requirement: Profile Auto-Renewal Preference

A Profile SHALL carry an `auto_renew` preference. At Profile creation, `auto_renew` SHALL **default-inherit the owning Club's `auto_renew_default`** — the `auto_renew` element of the (otherwise deferred) `renewal_policy` config, shipped here as a standalone `parties_clubs.auto_renew_default` boolean while the fuller `renewal_policy` blob stays deferred (canon MVP-DEC-013, out of this change). An **operator MAY set** a Profile's `auto_renew` after creation via an explicit operator Action that is its sole writer, running inside one `DB::transaction`; the change is captured in the append-only audit trail and records **no** domain event (the § 15.2 Profile event family names none for `auto_renew`). The **customer self-toggle via the Consumer Portal** (BMD § 2.4 / B2) is a **deferred frontend seam** — the Consumer Portal does not exist at launch — so only the inheritance-at-creation and the operator override ship here.

#### Scenario: A new Profile inherits the Club's auto-renew default

- **GIVEN** a Club whose `auto_renew_default` is `true`
- **WHEN** a Profile is created under that Club
- **THEN** the Profile's `auto_renew` is `true` (inherited at creation)
- **WHEN** a Profile is created under a Club whose `auto_renew_default` is `false`
- **THEN** the Profile's `auto_renew` is `false`

#### Scenario: An operator overrides a Profile's auto-renew

- **WHEN** an operator sets a Profile's `auto_renew` to a new value through the explicit operator Action
- **THEN** the Profile's `auto_renew` flips and persists, the change is audit-recorded, and no domain event is recorded

#### Scenario: The customer self-toggle is a deferred seam

- **WHEN** the Parties code surface is inspected
- **THEN** there is no Consumer-Portal `auto_renew` write in this change (the customer self-toggle is deferred to the Consumer Portal frontend); only inheritance-at-creation and the operator override exist

_Source: canon **MVP-DEC-022** (CML-89 sub-7 — the BMD-mandated customer self-serve auto-renewal; the K-side inheritance + operator override ship, the Consumer-Portal self-toggle deferred) / **AC-K-BR-Profile-5** (LIVE `cmless/main` @ `360df0b`) + **BMD § 2.4 / B2**, adopted locally via this change's **MVP-DEC-022 mini-ADR** `decisions/2026-07-07-adopt-mvp-dec-022-club-membership-governance.md` (tasks 1.x) — absent from the frozen `spec/`@MVP-DEC-007 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2 (Profile) / § 4.3 (`renewal_policy`) · new columns `parties_profiles.auto_renew` + `parties_clubs.auto_renew_default` · canon MVP-DEC-013 (the fuller `renewal_policy` config blob, deferred) · openspec/specs/party-registry/spec.md (*Profile — Multi-Profile Membership* — the creation that sets the inherited default) · CLAUDE.md invariant 10 (module-local)._

### Requirement: Producer Review-Governed Content Lock

The review-governed descriptive content of a Producer — `name`, `description`, `region`, `website` — SHALL be **immutable while the Producer is `active`**: an update that dirties any of these fields on an `active` Producer SHALL be **rejected** with a localized `ProducerReviewGovernedContentLocked` exception, leaving the Producer and its content unchanged. This SHALL be a **model-level, path-complete chokepoint** (the RM-24 immutability-guard pattern — a `Producer` `updating` guard keyed on `isDirty` of the review-governed set while the persisted `status` is `active`), enforced regardless of the writing surface, since there is no Action-layer content-edit writer to guard. A Producer in `draft` (pre-activation) SHALL set this content freely, and a transition that dirties only `status`/`kyc_status`/`version` (activation, retirement, KYC) SHALL pass untouched.

This is the **interim** adoption of canon **BR-K-Producer-5**: it codifies the review-freshness rule's **safety core** — unreviewed descriptive content never publishes on an `active` Producer — but **not** the full "edit re-enters the Creator → Reviewer → Approver workflow and re-publishes on a fresh pass" UX. That UX is a **deferred change**: the Producer FSM is linear `draft → active → retired` with **no `reviewed` review-governance state and no content-edit path** today, so building the re-arm now would be dead code (the RM-06 / RM-14 precedent). When the Producer content-edit path + review sub-FSM lands, this hard lock SHALL be **replaced** by the edit-re-arms-review behavior.

#### Scenario: Editing review-governed content on an active Producer is rejected

- **GIVEN** a Producer in `active`
- **WHEN** an update dirties any of `name`, `description`, `region`, or `website`
- **THEN** a `ProducerReviewGovernedContentLocked` is raised and the Producer's content and status are unchanged

#### Scenario: A draft Producer sets its content freely

- **GIVEN** a Producer in `draft`
- **WHEN** its `name` / `description` / `region` / `website` are set
- **THEN** the write succeeds (the lock applies only while `active`)

#### Scenario: A status-only transition passes the lock

- **WHEN** a Producer is activated or retired (the write dirties only `status`, not the review-governed content)
- **THEN** the transition succeeds — the lock keys on the review-governed fields being dirty, not on the lifecycle transition

_Source: canon **MVP-DEC-022** (CML-89 sub-4 — producer content edits RE-ARM review; the dev's "no re-review" default rejected) / **AC-K-BR-Producer-5** (LIVE `cmless/main` @ `360df0b`) + canon **MVP-DEC-019** (the Module-0 review-freshness invariant, inherited by Module K § 4.4), adopted locally via this change's **MVP-DEC-022 mini-ADR** `decisions/2026-07-07-adopt-mvp-dec-022-club-membership-governance.md` (tasks 1.x — recording the **interim** posture + the deferred full re-arm) — absent from the frozen `spec/`@MVP-DEC-007 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.4 (Producer content-approval workflow) · app/Modules/Parties/Models/Producer.php (`name`/`region`/`description`/`website`; `$guarded = []`) · decisions/2026-07-02-adopt-dec-023-product-type-immutable.md (the RM-24 model-`updating` immutability-guard pattern this mirrors) · openspec/specs/party-registry/spec.md (*Producer Lifecycle* — the linear FSM with no `reviewed` state) · CLAUDE.md invariant 12 (i18n)._

### Requirement: Hero Package Capacity Invariant

For any Club, the count of that Club's Profiles in the **seat-occupying set** SHALL NOT exceed the Club's Hero-Package capacity. The seat-occupying set SHALL be exactly **`Active` + `Suspended`**. A seat SHALL be freed **only** by a transition into `Lapsed`, `Cancelled` or `Inactive`, and SHALL **never** be held by a Profile in `Applied`, `WaitingList` or `Rejected`. A **`null` capacity SHALL mean uncapped** — every gate passes and the invariant is vacuously satisfied.

The invariant SHALL be evaluated at exactly the transitions that **newly consume** a seat: membership approval (`Applied → Active`), waitlist conversion (`WaitingList → Active`) and grace re-activation (`Lapsed → Active`). It SHALL gate nothing else. In particular:

- **`Suspended → Active` (`ReactivateProfile`) SHALL NEVER be capacity-checked and SHALL NEVER be blocked**, even when the Club is exactly at capacity — the seat was never freed. Re-checking would let a returning member exceed the cap, or let a *temporary* Hold **evict** a member.
- **`Approved → Active` (`ActivateProfile`) SHALL NOT be capacity-checked.** `Approved` is a transient pass-through never durably rested-in; its only caller is `ApproveProfile`, which evaluates the gate *before* delegating. Gating here would count the same seat twice.

Every seat-consuming transaction SHALL acquire a **row-level lock on the Club row** (`SELECT … FOR UPDATE` on `parties_clubs`) **before** counting occupancy, so that concurrent approvals into the same Club serialise while approvals into different Clubs stay parallel. A from-state guard on the Profile row is **not** sufficient: two concurrent approvals of *different* Profiles in one Club lock different rows, both observe `49/50`, and both pass.

A charge that fails at approval SHALL consume **no** seat (the Profile stays `Applied` — the shipped charge-fail contract).

#### Scenario: The seat-occupying set is Active plus Suspended

- **GIVEN** a Club whose capacity is 3, holding one `Active` Profile, one `Suspended` Profile, one `Applied` Profile, one `WaitingList` Profile, one `Rejected` Profile, one `Lapsed` Profile, one `Cancelled` Profile and one `Inactive` Profile
- **WHEN** the Club's seat occupancy is evaluated
- **THEN** exactly **2** seats are occupied (the `Active` and the `Suspended` Profile) and 1 seat is free

#### Scenario: The approval that would exceed capacity does not oversell

- **GIVEN** a Club whose capacity is 50, holding 50 Profiles in the seat-occupying set, and a 51st Profile in `Applied`
- **WHEN** `ApproveProfile` is invoked on the 51st Profile
- **THEN** the Club's seat occupancy stays 50, the 51st Profile is **not** `Active`, and no `ProfileActivated` event is recorded for it

#### Scenario: A suspended member keeps their seat and is restored at exact capacity

- **GIVEN** a Club at exactly its capacity, one of whose seat-occupying Profiles is `Suspended`
- **WHEN** `ReactivateProfile` is invoked on that `Suspended` Profile
- **THEN** the Profile becomes `Active`, exactly one `ProfileReactivated` event is recorded, and the transition is **not** rejected — no capacity check is applied, and the seat occupancy is unchanged (the seat was never freed)

#### Scenario: A suspension does not free a seat for someone else

- **GIVEN** a Club at exactly its capacity and a Profile in `Applied` for that Club
- **WHEN** a seat-occupying Profile of that Club transitions `Active → Suspended`, and `ApproveProfile` is then invoked on the `Applied` Profile
- **THEN** the seat occupancy is still at capacity and the `Applied` Profile is **not** activated (a Hold never evicts a member in favour of an applicant)

#### Scenario: Grace renewal re-consumes a seat and is rejected at capacity

- **GIVEN** a Club at exactly its capacity and a Profile in `Lapsed` whose `lapsed_at` is within the 30-day grace
- **WHEN** `RenewProfile` is invoked on that Profile
- **THEN** a localized `IllegalProfileTransition` naming the capacity reason is raised, the Profile stays `Lapsed` with its `lapsed_at` intact, no `ProfileRenewed` event is recorded, and the seat occupancy is unchanged
- **WHEN** a seat is subsequently freed and `RenewProfile` is invoked again within the grace window
- **THEN** the Profile becomes `Active`, `lapsed_at` is cleared, and exactly one `ProfileRenewed` event is recorded

#### Scenario: Concurrent approvals into one Club serialise on the Club row

- **GIVEN** a Club with exactly one free seat and two Profiles in `Applied` for that Club
- **WHEN** two concurrent transactions each invoke `ApproveProfile`, one per Profile, on a database engine that honours row-level locks
- **THEN** exactly **one** Profile becomes `Active` and the other lands in `WaitingList`; the Club's seat occupancy never exceeds its capacity

#### Scenario: An unconfigured capacity is uncapped

- **GIVEN** a Club for which no Hero-Package capacity is configured
- **WHEN** any number of Profiles are approved into that Club
- **THEN** every approval activates, no Profile is diverted to `WaitingList`, and no capacity rejection is raised

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (adopts canon **MVP-DEC-017** — seat set `Active`+`Suspended`, `Suspended → Active` never re-checked, enforcement at the atomic approve = charge = activation instant, charge-fail consumes no seat; and names the **oversell race** no canon artefact carries) · canon `c-mless/documentation` @ `360df0b` MVP_Decisions_Register_v0.1.md:142 (MVP-DEC-017) + :136 (MVP-DEC-011) · canon Module_K_PRD §13.1:625 (seat set; freed only by lapse/cancel/inactive), :627 (the gated transitions; the atomic instant), :629 (grace re-activation re-consumes a seat) · §10.1:532 · canon Module_K_Acceptance **AC-K-J-13**:92, **AC-K-FSM-2a**:114 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 13.1 (**superseded** — its *"only `Active` Profiles do"* consume capacity is the exact phrasing MVP-DEC-017 corrects), § 13.4 · decisions/2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval.md (the atomic instant this gate sits on) · decisions/2026-06-19-hold-status-coupling.md (a Hold must never evict a member) · CLAUDE.md invariant 1 (no-oversell) & 12 (i18n)._

### Requirement: WaitingList Placement, Conversion and Decline

The `WaitingList` Profile state SHALL become reachable, with **two** entry points, and `WaitingListJoined` SHALL be recorded at **both**:

- **Birth at application.** `CreateProfile` targeting a Club **at capacity** SHALL create the Profile in `WaitingList` rather than `Applied`, recording `ProfileCreated` **and** `WaitingListJoined`. Because neither `Applied` nor `WaitingList` holds a seat, this gate **cannot oversell**; it is a birth-state routing decision and SHALL **not** take a Club-row lock.
- **Divert at approval.** `ApproveProfile` on an `Applied` Profile whose Club is **at capacity** SHALL transition the Profile to `WaitingList` — **not** raise `IllegalProfileTransition` — recording `WaitingListJoined`, taking **no** charge, performing **no** Originating-Club lock, and recording **no** `ProfileActivated`.

`WaitingListJoined` SHALL be a Module K root domain event with `entity_type` `Profile` and a **PII-free** payload of entity ids and enum values only (`{profile_id, customer_id, club_id, state}`), recorded through the platform `DomainEventRecorder` within the writing transaction. Its declared consumer is HubSpot's waitlist confirmation.

**Conversion off the waitlist SHALL be the Producer's manual approval and nothing else.** `ApproveProfile`'s from-state set SHALL widen to `{Applied, WaitingList}`: a `WaitingList → Active` conversion is the same atomic approve = charge = activation instant, under the same capacity gate and the same Originating-Club one-shot rule. `DeclineProfile`'s from-state set SHALL widen to `{Applied, WaitingList}` (`WaitingList → Rejected`, audit-only, event-silent).

**There SHALL be no automatic promotion off the waitlist, on any trigger** — not FIFO, not priority-by-application-date, not producer ranking, and **not on a seat freed by attrition** (`Lapsed` / `Cancelled` / `Inactive`). No listener, scheduler, job or model observer SHALL convert a `WaitingList` Profile. A seat freed by attrition SHALL simply remain free until a Producer approves an applicant into it.

`ApproveProfile` invoked on a `WaitingList` Profile whose Club is **still at capacity** SHALL be rejected with a localized `IllegalProfileTransition` naming the capacity reason — there is no transition to make, and a silent no-op is indistinguishable from a defect. A `WaitingList` Profile SHALL **not** be re-recorded with a second `WaitingListJoined`.

#### Scenario: A Profile is born WaitingList when its target Club is at capacity

- **GIVEN** an `active` Club at exactly its Hero-Package capacity
- **WHEN** `CreateProfile` is invoked for a Customer against that Club
- **THEN** the Profile is created in `WaitingList` (not `Applied`), and exactly two events are recorded in the writing transaction — one `ProfileCreated` and one `WaitingListJoined`
- **WHEN** the same Club has a free seat
- **THEN** a newly created Profile is born `Applied` and only `ProfileCreated` is recorded

#### Scenario: An approval at capacity diverts to WaitingList, taking no charge and no Originating-Club lock

- **GIVEN** a Customer whose `originating_club_id` is unset, holding an `Applied` Profile in a Club at exactly its capacity
- **WHEN** `ApproveProfile` is invoked on that Profile
- **THEN** the Profile's `state` becomes `WaitingList`, exactly one `WaitingListJoined` event is recorded, and **no** `ProfileActivated` and **no** `OriginatingClubLocked` event is recorded
- **AND** the Customer's `originating_club_id` is still unset, and the Club's seat occupancy is unchanged

#### Scenario: A waitlisted Profile converts on manual approval once a seat frees

- **GIVEN** a Club at capacity with a Profile in `WaitingList`
- **WHEN** a seat-occupying Profile of that Club transitions to `Cancelled`, and an operator then invokes `ApproveProfile` on the waitlisted Profile
- **THEN** that Profile becomes `Active` in one atomic operation (passing transiently through `Approved`), exactly one `ProfileActivated` event is recorded, and the Originating-Club one-shot lock applies exactly as it does on an approval from `Applied`

#### Scenario: Nothing promotes a waitlisted Profile automatically

- **GIVEN** a Club at capacity with one or more Profiles in `WaitingList`
- **WHEN** a seat is freed by any attrition transition (`Active → Lapsed`, `Active → Cancelled`, `Lapsed → Cancelled` or `Active → Inactive`) and no operator acts
- **THEN** every `WaitingList` Profile is still in `WaitingList`, the freed seat stays unoccupied, and no `ProfileActivated` event is recorded
- **AND** the Parties code surface contains no listener, scheduler, job or observer that transitions a Profile out of `WaitingList`

#### Scenario: Approving a waitlisted Profile while still at capacity is rejected

- **GIVEN** a Club at exactly its capacity with a Profile in `WaitingList`
- **WHEN** `ApproveProfile` is invoked on that Profile
- **THEN** a localized `IllegalProfileTransition` naming the capacity reason is raised, the Profile stays `WaitingList`, **no** second `WaitingListJoined` event is recorded, and the event log is otherwise unchanged

#### Scenario: A waitlisted Profile is declined, terminally and event-silently

- **WHEN** `DeclineProfile` is invoked on a Profile in `WaitingList`
- **THEN** the Profile's `state` becomes `Rejected`, **no** domain event is recorded (the write is the audit record), and a subsequent re-application for the same Customer–Club pair creates a new Profile (the partial-unique index excludes `rejected`)

#### Scenario: WaitingListJoined carries a PII-free payload and is a root event

- **WHEN** `WaitingListJoined` is recorded, at either entry point
- **THEN** its payload is exactly `{profile_id, customer_id, club_id, state}`, its `entity_type` is `Profile`, its module is `parties`, it carries the `actor_role` resolved from `ActorContext`, and it is a root event (no `causation_id`; `correlation_id` defaults to its own `event_id`) with no name, email, phone or date of birth

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§5–§8 — `WaitingList` is an FSM state, not an entity; two entry points; `WaitingListJoined` at both, a recorded resolution of `AC-K-EVT-11`'s *"transitions to"* wording; `ApproveProfile` at parity **transitions, does not throw**; **no auto-promotion ever**) · canon `c-mless/documentation` @ `360df0b` Module_K_PRD §4.2.1:186 (`Applied → WaitingList`; exits to `Approved` or `Rejected`), §7.1 step 6 (`:399`, birth-in-`WaitingList` at capacity), §7.5:429, §13.5:655 (*"no automatic FIFO conversion at launch"*), §15.6:822 (`WaitingListJoined`) · MVP_Decisions_Register_v0.1.md:147 **MVP-DEC-022**(1) (Paolo **rejected** the tech team's proposed auto-convert `waiting_list → approved` FIFO — *"PRD wins"*) · canon issue #1 → MVP-DEC-011 (*"Shrink by attrition + **no-backfill**"* — an attrition-freed seat is never auto-filled) · canon Module_K_Acceptance **AC-K-J-13**:92, **AC-K-FSM-2**:113, **AC-K-EVT-11**:259 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1:186, § 7.1:392, § 13.5, § 15.6:796 · app/Modules/Parties/Enums/ProfileState.php:29 (`case WaitingList` — present and inert before this change) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional outbox; PII-free payloads; root `correlation_id` = own `event_id`) · CLAUDE.md invariants 4, 10, 12._

### Requirement: Hero Package Capacity Is Read from Module A, Never Stored in Module K

Module K SHALL **enforce** the Hero-Package capacity invariant while storing **no capacity value of any kind**. There SHALL be **no** capacity column on `parties_clubs`, **no** capacity table, and **no** capacity read-model in Module K — the capacity number is the Hero-Package Allocation's `qty`, owned by **Module A**.

Module K SHALL obtain the capacity through a **Module-K-owned read port**: an interface in the Parties `Contracts` namespace returning the capacity for a Club id, where **`null` means uncapped**. Its **launch adapter** SHALL be config-backed, bound in `PartiesServiceProvider::register()`, and swappable in one line for a live Module-A read (or for the `AllocationCapacity*`-fed read-model canon permits) when Module A lands. Module K SHALL import **no** Module A model, table or event, and the port SHALL commit to nothing about Module A's schema or event payloads.

The seat-occupancy count SHALL stay **internal to Module K**. Module K SHALL **not** publish a cross-module seat-occupancy contract until Module A (the capacity-decrease floor) or Module S (the Hero-Package offer gate) exists to consume it — a contract with zero consumers is dead code.

#### Scenario: No Module K schema carries a capacity attribute

- **WHEN** every Module K entity schema is inspected
- **THEN** no table carries a capacity, seat, quota or maximum-members attribute; specifically `parties_clubs` carries none, and no `parties_*` capacity table or capacity read-model exists

#### Scenario: The capacity is obtained through a Module-K-owned read port

- **WHEN** a seat-consuming transition evaluates the gate
- **THEN** it obtains the Club's capacity by resolving the Parties `Contracts` capacity read port from the container, and Module K imports no Module A model, table or event anywhere in its source

#### Scenario: The launch adapter is config-backed and returns a typed capacity

- **GIVEN** the config-backed launch adapter is bound
- **WHEN** the capacity is read for a Club with a configured capacity, and for a Club with none
- **THEN** the first returns that capacity as an `int` (never a string, even though the value may originate from an environment variable) and the second returns `null`, meaning uncapped

#### Scenario: The port is swappable without touching the gate

- **WHEN** a test binds a different implementation of the capacity read port to the container
- **THEN** every seat-consuming transition evaluates the gate against that implementation, with no change to any Action

#### Scenario: The seat-occupancy count is not published cross-module

- **WHEN** the Parties `Contracts` namespace is inspected
- **THEN** it exposes the capacity **read** port but **no** seat-occupancy reader contract (the count stays internal until a consumer exists)

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§9 + alternative (a) — **`AC-K-XM-20` is the binding constraint, stricter than the permissive `AC-K-XM-18`**; a K-owned read-model is rejected because *there is no signal to reconcile from*, making it authoritative-by-default; a Null/uncapped adapter is rejected because *a vacuous gate is worse than no gate*) · canon `c-mless/documentation` @ `360df0b` MVP_Decisions_Register_v0.1.md:145 **MVP-DEC-020** (Module A owns the number; Module K owns the invariant; *"a K-owned capacity number would be a **drift-prone mirror with no independent meaning**"*) · canon Module_K_Acceptance **AC-K-XM-18**:335 (*"a live read of Module A, or a derived, reconciling read-model … is an implementation choice"*), **AC-K-XM-20**:342 (*"NO Allocation, **capacity storage**, sub-pool, sourcing-model attribute"*, verified by *"inspect Module K entity schemas … assert absence"*) · canon Module_K_PRD §13.2:633 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md § 13.2:620, § 16:832 (*"it does not duplicate the value"*) · spec/04-decisions/decisions.md DEC-073 (physical representation delegated to the dev team) · app/Modules/Parties/Contracts/CustomerTransactionTotalsReader.php + Reads/NullCustomerTransactionTotalsReader.php + Providers/PartiesServiceProvider.php:35 (**the RM-02 read-port seam this mirrors**) · decisions/2026-06-11-modular-monolith-architecture.md (events + contracts only) · tests/Architecture/ModuleBoundariesTest.php · CLAUDE.md invariant 10._

