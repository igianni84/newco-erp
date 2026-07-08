## ADDED Requirements

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

## MODIFIED Requirements

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

### Requirement: Profile — Multi-Profile Membership

The Profile SHALL **be** the membership in one Club — there SHALL be no separate Membership entity (the Netflix-style Customer↔Profile model). A Profile SHALL belong to **exactly one** Customer and **exactly one** Club, both required at creation. A single Customer MAY hold **multiple** Profiles across different Clubs, but SHALL hold **at most one non-terminal Profile per Club** (uniqueness on the Customer–Club pair), so a second Profile for a (Customer, Club) pair that already has a live Profile SHALL be rejected. A Profile SHALL be created in the `Applied` state and SHALL record a `ProfileCreated` domain event on creation. The Customer–Club uniqueness is scoped to non-terminal states (the partial-unique index `(customer_id, club_id) WHERE state NOT IN ('rejected','cancelled','inactive')` excludes the terminal states, so a terminal Profile never blocks a fresh `Applied` Profile for the same pair; `suspended` and `lapsed` are **non-terminal** and so still block a second live Profile).

The **target Club SHALL be `active`**: a `CreateProfile` targeting a Club in `sunset` or `closed` SHALL be **rejected** with a localized `ClubNotAcceptingMemberships` exception, and no Profile and no `ProfileCreated` event SHALL be created — enforcing the frozen rule that a `sunset` Club blocks new memberships (BR-K-Club-3 / AC-K-FSM-6, closing the deferral in *Club Lifecycle*). At creation, the Profile's `auto_renew` SHALL default-inherit the Club's `auto_renew_default` (per the *Profile Auto-Renewal Preference* requirement).

#### Scenario: Create a Profile

- **WHEN** an operator creates a Profile for a Customer in an `active` Club
- **THEN** it is persisted in `Applied`, referencing exactly one Customer and one Club, with `auto_renew` inherited from the Club default, and a `ProfileCreated` event is recorded

#### Scenario: One non-terminal Profile per Customer–Club pair

- **WHEN** a second Profile is created for a (Customer, Club) pair that already has a live Profile
- **THEN** the creation is rejected

#### Scenario: A Customer may hold Profiles across many Clubs

- **WHEN** a Customer is given Profiles in three different (active) Clubs
- **THEN** all three are created (the multi-profile model), each unique on its own Customer–Club pair

#### Scenario: A terminal Profile does not block a fresh application

- **GIVEN** a Customer whose Profile for Club C is in a terminal state (`cancelled` or `inactive`)
- **WHEN** a new Profile is created for the same Customer–Club pair (C still `active`)
- **THEN** the new Profile is created in `Applied` (the partial-unique index excludes the terminal states), while a `suspended` or `lapsed` (non-terminal) Profile for the pair would still block it

#### Scenario: A non-active Club rejects new membership

- **WHEN** a `CreateProfile` targets a Club in `sunset` (or `closed`)
- **THEN** a `ClubNotAcceptingMemberships` is raised, and no Profile and no `ProfileCreated` event are created

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 3 (the Netflix-style Customer–Profile model) · § 4.2 / § 4.2.1 (Profile born `Applied`) · § 4.3 (**sunset blocks new memberships**) · § 14.1 BR-K-Identity-2 (one Profile per Customer per Club) · § 14.4 **BR-K-Club-3** · § 15.2 (`ProfileCreated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-2, **AC-K-FSM-6 (sunset blocks new memberships)**, § 4 AC-K-BR-Identity-2, **AC-K-BR-Club-3 (new-membership creation rejected when Club is `sunset`)**, § 5 AC-K-EVT-5 · openspec/specs/party-registry/spec.md (*Club Lifecycle*; *Profile Auto-Renewal Preference*) · MODIFIES the *Profile — Multi-Profile Membership* requirement (openspec/specs/party-registry/spec.md — which guarded only Customer–Club uniqueness and applied **no Club-status gate** at Profile creation)._

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
