## ADDED Requirements

### Requirement: Profile Suspension and Restoration

The Profile SHALL transition `Active → Suspended` via an explicit `SuspendProfile` Action and `Suspended → Active` via an explicit `ReactivateProfile` Action — each the sole writer of the Profile `state` for its transition, running inside one `DB::transaction` against a transaction-locked re-read, recording a `ProfileSuspended` (respectively `ProfileReactivated`) event (per the *Demand-Side Status Events* requirement) in the same transaction. In production these transitions are driven by the Hold→`suspended` coupling (a Profile-level Hold, or a cascading Customer-level Hold — per the *Hold-Driven Status Coupling* requirement); the Actions are also directly operator-invocable (manual suspension — AC-K-BR-Customer-1 *"explicit (manual or via Hold)"*).

Suspension SHALL be **state-preserving**: `SuspendProfile` SHALL write **only** the Profile `state` — it SHALL NOT cancel vouchers, pending orders or allocation reservations, nor mutate any Club Credit balance. Active vouchers stay ACTIVE, pending orders stay pending, reservations stay reserved (§ 10.1); the Club-Credit freeze-while-suspended / unfreeze-on-restore is a **deferred `club-credit` seam** (the Club Credit entity is unbuilt — Module S/E; there is nothing to freeze in this change, and nothing destructive happens on suspend). Every transition SHALL be **from-state guarded**: a `SuspendProfile` on a Profile not in `Active`, or a `ReactivateProfile` on a Profile not in `Suspended`, SHALL be rejected with a localized `IllegalProfileTransition`, leaving state and the event log unchanged.

#### Scenario: Suspend an active Profile records ProfileSuspended and preserves state

- **WHEN** `SuspendProfile` is invoked on a Profile in `Active`
- **THEN** the Profile's `state` becomes `Suspended` and exactly one `ProfileSuspended` event is recorded in the same transaction (module `parties`, `entity_type` `Profile`, PII-free payload `{profile_id, state}`)
- **AND** no voucher, order, reservation or Club Credit record is created, cancelled or mutated by the Action (it writes only `Profile.state`)

#### Scenario: Restore a suspended Profile

- **WHEN** `ReactivateProfile` is invoked on a Profile in `Suspended`
- **THEN** the Profile's `state` becomes `Active` and exactly one `ProfileReactivated` event is recorded in the same transaction

#### Scenario: Illegal suspend or restore is rejected

- **WHEN** `SuspendProfile` is invoked on a Profile not in `Active` (e.g. `Applied`, `Lapsed` or already `Suspended`), or `ReactivateProfile` on a Profile not in `Suspended`
- **THEN** an `IllegalProfileTransition` is raised, the Profile's `state` is unchanged, and no event is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1 (`Active → Suspended` on a Profile-level or cascading Customer-level Hold; `Suspended → Active` when the triggering Hold is lifted), § 10.1 (suspension model — state preservation: vouchers stay ACTIVE, reservations reserved, Club Credit frozen; restore on lift), § 15.2 (`ProfileSuspended` on `Active → Suspended`; `ProfileReactivated` on `Suspended → Active`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-2 (Profile FSM traversal), AC-K-FSM-2a (suspension state-preservation guarantee), AC-K-EVT-5 (`ProfileSuspended`/`ProfileReactivated` fire on the transitions), AC-K-BR-Hold-5 (Holds block new commitment, not in-flight) · decisions/2026-06-19-hold-status-coupling.md (the coupling that drives these in production), decisions/2026-06-12-event-substrate-and-audit-store.md (transactional, PII-free recording)._

### Requirement: Profile Lapse and Grace Renewal

The Profile SHALL transition `Active → Lapsed` via an explicit `LapseProfile` Action and `Lapsed → Active` via an explicit `RenewProfile` Action — each the sole writer of the Profile `state` for its transition, running inside one `DB::transaction` against a transaction-locked re-read. `LapseProfile` SHALL set `state = Lapsed`, stamp the additive nullable `lapsed_at` timestamp, and record a **`ProfileExpired`** event (the § 15.2 event for this edge — the catalog names **no** `ProfileLapsed`). `RenewProfile` SHALL set `state = Active`, clear `lapsed_at`, and record a **`ProfileRenewed`** event (the § 15.2 event for the grace renewal — **not** `ProfileReactivated`, which is reserved for `Suspended → Active`).

`RenewProfile` SHALL enforce the **30-day grace window** (DEC-034): it is permitted **only** when `state = Lapsed` **and** the current moment is within 30 days of `lapsed_at`; past the grace window it SHALL be rejected with a localized `IllegalProfileTransition` (the deferred scheduler instead transitions the Profile `Lapsed → Cancelled` — per the *Profile Cancellation and Deactivation* requirement). The lapse trigger (membership-validity-period expiry, § 4.2.1) and the renewal trigger (Module E's `MembershipFeePaid`, § 15.8) are **deferred seams** — `LapseProfile`/`RenewProfile` are the within-module writers, invoked directly now; **no** Module-E event contract is fabricated. Every transition SHALL be **from-state guarded**: a `LapseProfile` on a Profile not in `Active`, or a `RenewProfile` on a Profile not in `Lapsed` (or past grace), SHALL be rejected, leaving state, `lapsed_at` and the event log unchanged.

#### Scenario: Lapse an active Profile

- **WHEN** `LapseProfile` is invoked on a Profile in `Active`
- **THEN** the Profile's `state` becomes `Lapsed`, `lapsed_at` is stamped, and exactly one `ProfileExpired` event is recorded in the same transaction (and **no** `ProfileLapsed` event, which the catalog does not name)

#### Scenario: Renew a lapsed Profile within the 30-day grace

- **GIVEN** a Profile in `Lapsed` whose `lapsed_at` is within the last 30 days
- **WHEN** `RenewProfile` is invoked
- **THEN** the Profile's `state` becomes `Active`, `lapsed_at` is cleared, and exactly one `ProfileRenewed` event is recorded (not `ProfileReactivated`)

#### Scenario: Renewal past the grace window is rejected

- **GIVEN** a Profile in `Lapsed` whose `lapsed_at` is more than 30 days ago
- **WHEN** `RenewProfile` is invoked
- **THEN** an `IllegalProfileTransition` is raised, the Profile stays `Lapsed`, and no event is recorded

#### Scenario: The lapse and renewal triggers are deferred seams

- **WHEN** the Parties code surface is inspected
- **THEN** `LapseProfile` and `RenewProfile` exist as the within-module writers, and there is **no** validity-period scheduler and **no** `MembershipFeePaid` listener or fabricated Module-E event class in this change

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1 (`Active → Lapsed` on validity-period expiry, stamps `lapsed_at`; `Lapsed → Active` within the 30-day grace on a renewal payment; `Lapsed → Cancelled` after 30 days), § 15.2 (`ProfileExpired` on `Active → Lapsed`; `ProfileRenewed` when a renewal cycle's `MembershipFeePaid` extends validity), § 15.8 (`MembershipFeePaid` is a Module E event Module K consumes) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-12 (lapsed grace: 30-day return-to-active, else terminal Cancelled), AC-K-BR-Profile-3 (30-day grace, no re-application), AC-K-EVT-5 (`ProfileExpired`/`ProfileRenewed`) · spec/04-decisions/decisions.md DEC-034 (30-day lapsed grace) · openspec/changes/archive/2026-06-19-parties-membership-activation (the precedent: ship the transition with the upstream trigger as a documented seam)._

### Requirement: Profile Cancellation and Deactivation

The Profile SHALL transition `Active | Lapsed → Cancelled` via an explicit `CancelProfile` Action and `Active → Inactive` via an explicit `DeactivateProfile` Action — each the sole writer of the Profile `state` for its transition, running inside one `DB::transaction` against a transaction-locked re-read. Both `Cancelled` and `Inactive` are **terminal soft-delete** states: the Profile is **never hard-deleted** at launch, preserving audit history (re-entry requires a fresh application, except the lapse-grace path). `CancelProfile` SHALL set `state = Cancelled` and record the optional Producer-initiated `cancellation_reason`; `DeactivateProfile` SHALL set `state = Inactive` and record a `ProfileInactive` event.

`CancelProfile` SHALL record **no** domain event — the § 15.2 Profile event family names **no `ProfileCancelled`**, and § 15.7 explicitly defers the cancellation-signal shape as a downstream consumer concern; so (exactly as `ApproveProfile`/`DeclineProfile` are audit-only) the `state = Cancelled` write captured in the append-only audit trail **is** the record. The per-Profile cancellation **signal** Module S consumes for Club-Credit conversion at Producer offboarding (AC-K-EVT-14 / § 10.2 / DEC-043) is a **deferred Module-S seam**: this change ships the within-module `→ Cancelled` transition + the cancellation reason, not the offboarding orchestration. Because the Customer–Club partial-unique index already excludes the terminal `{rejected, cancelled, inactive}` states, a `Cancelled` (or `Inactive`) Profile SHALL NOT block a fresh `Applied` Profile for the same Customer–Club pair — with no index migration. Every transition SHALL be **from-state guarded**: a `CancelProfile` on a Profile not in `Active`/`Lapsed`, or a `DeactivateProfile` on a Profile not in `Active`, SHALL be rejected with a localized `IllegalProfileTransition`.

#### Scenario: Cancel an active Profile is terminal and event-silent

- **WHEN** `CancelProfile` is invoked on a Profile in `Active` (or `Lapsed`) with a cancellation reason
- **THEN** the Profile's `state` becomes `Cancelled`, the `cancellation_reason` is recorded, **no** domain event is recorded (the catalog names no `ProfileCancelled`), and a subsequent application for the same Customer–Club pair creates a new Profile in `Applied` (the partial-unique index admits it)

#### Scenario: Deactivate an active Profile records ProfileInactive

- **WHEN** `DeactivateProfile` is invoked on a Profile in `Active`
- **THEN** the Profile's `state` becomes `Inactive` and exactly one `ProfileInactive` event is recorded in the same transaction

#### Scenario: Illegal cancel or deactivate is rejected

- **WHEN** `CancelProfile` is invoked on a Profile not in `Active`/`Lapsed`, or `DeactivateProfile` on a Profile not in `Active`
- **THEN** an `IllegalProfileTransition` is raised, the Profile's `state` is unchanged, and no event is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1 (`Active → Cancelled` voluntary/admin/Producer-offboarding/death; `Lapsed → Cancelled` after grace; `Active → Inactive` operational corner case; Cancelled/Inactive terminal soft-delete, never hard-deleted; re-activation from terminal requires a fresh application), § 10.2 (Producer-offboarding per-Profile cancellation with a Producer-initiated reason; Module K's role ends at the upstream per-Profile cancellation signal), § 15.2 (`ProfileInactive` on `Active → Inactive`; the family names no `ProfileCancelled`), § 15.7 (the per-Profile cancellation signal shape is a deferred downstream-consumer concern) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-2 (Profile FSM traversal), AC-K-FSM-13 (terminal soft-delete preserves audit history; never hard-deleted), AC-K-BR-Profile-2 (Cancelled/Inactive terminal soft-delete), AC-K-EVT-14 (Producer-offboarding per-Profile cancellation signal — deferred Module-S consumer) · spec/04-decisions/decisions.md DEC-043 (Club-Credit conversion at offboarding — Module S) · openspec/specs/party-registry/spec.md (the *Profile — Multi-Profile Membership* uniqueness constraint this change makes terminal-reachable)._

### Requirement: Customer Suspension and Closure

The Customer SHALL transition `active → suspended` via `SuspendCustomer`, `suspended → active` via `ReactivateCustomer`, and `active | suspended → closed` via `CloseCustomer` — each the sole writer of the Customer `status` for its transition, running inside one `DB::transaction` against a transaction-locked re-read, recording (respectively) a `CustomerSuspended`, `CustomerReactivated`, or `CustomerClosed` event in the same transaction. Suspension SHALL be **explicit** — manual (operator) or via the Hold coupling — and SHALL NOT be automatically driven by Profile state changes or by a KYC/sanctions verdict (the status FSM is independent of the compliance FSMs — § 9.4; AC-K-BR-Customer-1).

`SuspendCustomer` SHALL **cascade** to the Customer's Profiles: in the same transaction it SHALL transition every Profile currently in `Active` to `Suspended` (recording one `ProfileSuspended` per Profile — § 15.1 *"Cascades to all the Customer's Profiles"*); non-`Active` Profiles are skipped (the FSM has only `Active → Suspended`; the Customer-scope Hold blocks them logically via the read-API). `ReactivateCustomer` SHALL cascade-restore every Profile currently in `Suspended` to `Active` (recording `ProfileReactivated`) **iff** that Profile is no longer covered by any active Hold (a Profile retaining its own active Hold — or under a Customer that retains another active Hold — stays `Suspended`). `CloseCustomer` SHALL **not** cascade to Profiles — § 15.1 `CustomerClosed` names no cascade (contrast `CustomerSuspended`); `closed` is **terminal** and is **orthogonal to** anonymisation (a `closed` Customer stays admin-queryable until separately anonymised — AC-K-BR-Customer-2; anonymisation is out of scope). Every transition SHALL be **from-state guarded**: a `SuspendCustomer` on a Customer not in `active`, a `ReactivateCustomer` not in `suspended`, or a `CloseCustomer` not in `active`/`suspended`, SHALL be rejected with a localized `IllegalCustomerTransition`, leaving status, the cascade and the event log unchanged.

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

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer FSM `pending → active → suspended → closed`; suspension explicit on cross-cutting Holds; closed terminal, orthogonal to anonymisation), § 10.1 (Customer-level suspension blocks all the Customer's Profiles; restore on lift), § 14.2 BR-K-Customer-1/2 (suspension explicit, not auto-driven by Profile state; closed and anonymised orthogonal), § 15.1 (`CustomerSuspended` cascades to all Profiles; `CustomerReactivated`; `CustomerClosed` terminal, names no cascade) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-1 (Customer FSM + the five events), AC-K-BR-Customer-1 (suspension explicit, not auto-driven), AC-K-BR-Customer-2 (closed queryable until anonymised; independent operations), AC-K-BR-Hold-3 (a Customer-level Hold blocks every Profile), AC-K-EVT-1 (`CustomerSuspended`/`CustomerReactivated`/`CustomerClosed`) · decisions/2026-06-19-hold-status-coupling.md (the cascade + coverage-recompute restore), decisions/2026-06-12-event-substrate-and-audit-store.md (transactional recording)._

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

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.2.1 (`Active → Suspended` via a Profile-level or cascading Customer-level Hold; `Suspended → Active` when the triggering Hold is lifted), § 4.7 (Account-level Holds drive `active → suspended`), § 10.1 (Customer-level Hold blocks all Profiles; reactivation when the triggering Hold is lifted), § 4.8 / § 4.8.1 (the unified Hold registry; six types; the `*Reactivated` event on lift), § 14.8 BR-K-Hold-1/3/4 (multiple Holds coexist — any blocks; Customer-scope cascades, Profile-scope isolates) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-2a (suspension state preservation), AC-K-FSM-9 (Account Holds drive `active → suspended`, lift drives `suspended → active`), AC-K-BR-Hold-1/3/4 · decisions/2026-06-19-hold-status-coupling.md (coverage-recompute; explicit Actions invoked by the Hold paths; restore iff uncovered), decisions/2026-06-18-hold-lift-discipline-per-type.md (the system `kyc`-lift path), openspec/specs/party-registry/spec.md (the *Hold Registry*, *Hold Lifecycle and Lift Discipline* and *Hold and Sanctions Read-API* requirements this builds on)._

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

## MODIFIED Requirements

### Requirement: Profile — Multi-Profile Membership

The Profile SHALL **be** the membership in one Club — there SHALL be no separate Membership entity (the Netflix-style Customer↔Profile model). A Profile SHALL belong to **exactly one** Customer and **exactly one** Club, both required at creation. A single Customer MAY hold **multiple** Profiles across different Clubs, but SHALL hold **at most one non-terminal Profile per Club** (uniqueness on the Customer–Club pair), so a second Profile for a (Customer, Club) pair that already has a live Profile SHALL be rejected. A Profile SHALL be created in the `Applied` state and SHALL record a `ProfileCreated` domain event on creation. _(Because rejected, cancelled and inactive Profiles are not reused — a re-application creates a new Profile row — the Customer–Club uniqueness is scoped to non-terminal states. With this change the terminal states `rejected`/`cancelled`/`inactive` are **all reachable** — `rejected` via `DeclineProfile`, and `cancelled`/`inactive` via the *Profile Cancellation and Deactivation* requirement — and the partial-unique index `(customer_id, club_id) WHERE state NOT IN ('rejected','cancelled','inactive')` already excludes all three, so a terminal Profile never blocks a fresh `Applied` Profile for the same pair, with no index migration. `suspended` and `lapsed` are **non-terminal** and so still block a second live Profile.)_

#### Scenario: Create a Profile

- **WHEN** an operator creates a Profile for a Customer in a Club
- **THEN** it is persisted in `Applied`, referencing exactly one Customer and one Club, and a `ProfileCreated` event is recorded

#### Scenario: One non-terminal Profile per Customer–Club pair

- **WHEN** a second Profile is created for a (Customer, Club) pair that already has a live Profile
- **THEN** the creation is rejected

#### Scenario: A Customer may hold Profiles across many Clubs

- **WHEN** a Customer is given Profiles in three different Clubs
- **THEN** all three are created (the multi-profile model), each unique on its own Customer–Club pair

#### Scenario: A terminal Profile does not block a fresh application

- **GIVEN** a Customer whose Profile for Club C is in a terminal state (`cancelled` or `inactive`)
- **WHEN** a new Profile is created for the same Customer–Club pair
- **THEN** the new Profile is created in `Applied` (the partial-unique index excludes the terminal states), while a `suspended` or `lapsed` (non-terminal) Profile for the pair would still block it

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 3 (the Netflix-style Customer–Profile model; Profile is the membership, no Membership table) · § 4.2 / § 4.2.1 (Profile belongs to one Customer + one Club; born `Applied`; rejected/cancelled/inactive not reused; suspended/lapsed non-terminal) · § 14.1 BR-K-Identity-2 (one Profile per Customer per Club) · § 15.2 (`ProfileCreated`) · spec/04-decisions/decisions.md DEC-012 / DEC-024 (multi-profile; one profile per club) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-2, § 4 AC-K-BR-Identity-2, AC-K-FSM-13 (terminal soft-delete), § 5 AC-K-EVT-5 · openspec/changes/parties-membership-suspension (the Profile lifecycle that makes `cancelled`/`inactive` reachable — the constraint refinement this requirement deferred)._

### Requirement: Birth States Recorded, Lifecycle Transitions Deferred

Every Parties entity that carries a lifecycle state SHALL define its full state domain and SHALL be created in its birth state: Customer `pending`, Account `active`, Producer `draft`, Club `active`, ProducerAgreement `draft`, Profile `Applied` (Supplier carries no lifecycle state). The **supply-side** lifecycle — Producer, ProducerAgreement and Club — SHALL implement its state transitions and emit its lifecycle events, as governed by the Requirements *Producer Lifecycle*, *ProducerAgreement Lifecycle*, *Club Lifecycle* and *Supply-Side Lifecycle Events*. The **Customer and Producer compliance-screening lifecycles** — the KYC FSM and the Customer sanctions FSM, **each separate from the Customer/Producer status FSM** — SHALL be implemented as governed by the Requirements *Customer KYC Lifecycle*, *Customer Sanctions Screening Lifecycle*, *Producer KYC Lifecycle* and *Sanctions Screening Events*; their fields are added additively (nullable — DEC-071). The **demand-side** status lifecycle is now **fully implemented** across activation and suspension. Activation (the Requirements *Customer Onboarding Activation*, *Profile Membership Approval*, *Profile Activation* and *Demand-Side Activation Events*): Customer `pending → active`, Profile `Applied → Approved | Rejected` and `Approved → Active`, and the Originating-Club one-shot lock — emitting `CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked` (approval and decline audit-only — § 15.2 names no `ProfileApproved` / `ProfileRejected`). Suspension and the remaining status edges (the Requirements *Profile Suspension and Restoration*, *Profile Lapse and Grace Renewal*, *Profile Cancellation and Deactivation*, *Customer Suspension and Closure*, *Account Status Lifecycle*, *Hold-Driven Status Coupling* and *Demand-Side Status Events*): Profile `Active → Suspended | Lapsed | Cancelled | Inactive` and `Lapsed → Active` grace, Customer `active → suspended | closed` (suspension cascading to the Customer's Profiles), and Account `active → suspended → closed` — emitting `CustomerSuspended` / `CustomerReactivated` / `CustomerClosed` and `ProfileSuspended` / `ProfileReactivated` / `ProfileExpired` / `ProfileRenewed` / `ProfileInactive` (Account transitions and Profile cancellation are **audit-only** — § 15 names no Account event and the § 15.2 family names no `ProfileCancelled`). The **Hold→`suspended` status coupling** is now implemented (the *Hold-Driven Status Coupling* requirement): placing a Hold drives every covered scope in its suspendable from-state to `suspended`, and lifting a Hold restores a covered scope **iff no other active Hold** still covers it (ADR 2026-06-19); the unified Hold registry and the `kyc` Hold compliance coupling (auto-place on KYC `pending`, auto-lift on `verified`) remain as governed by the Requirements *Hold Registry*, *Hold Lifecycle and Lift Discipline*, *Hold Events* and *Hold and Sanctions Read-API*. Only three demand-side seams SHALL remain deferred: the **Hero Package Capacity Invariant** (approval and activation ship **uncapped** — the Module-A seam), the `Applied → WaitingList` path (and its `WaitingListJoined` event), and **Customer-segment derivation** (and its `CustomerSegmentChanged` event) — until the follow-on changes (`parties-hero-package`, `parties-customer-segments`) implement them. `ActivateAccount` SHALL NOT exist (the Account is born `active`; its only `→ active` edge is the restore `ReactivateAccount`).

#### Scenario: Each entity is born in its birth state

- **WHEN** a Customer, Account, Producer, Club, ProducerAgreement or Profile is created
- **THEN** its state is, respectively, `pending`, `active`, `draft`, `active`, `draft`, `Applied`

#### Scenario: The demand-side status transitions and the Hold coupling exist; only the capacity, WaitingList and segment seams remain

- **WHEN** the Parties code surface is inspected
- **THEN** Producer, ProducerAgreement and Club expose lifecycle-transition operations and record their lifecycle events; the Customer/Producer KYC and Customer sanctions screening FSMs expose their transitions; the unified Hold registry exposes place/lift with the `kyc` Hold auto-placed on KYC `pending` and auto-lifted on `verified`; the demand-side **activation** transitions exist (Customer `pending → active` via `ActivateCustomer`, Profile `Applied → Approved | Rejected` via `ApproveProfile` / `DeclineProfile` and `Approved → Active` via `ActivateProfile`, with the Originating-Club one-shot lock); AND the demand-side **status** transitions exist — Profile `Active → Suspended | Lapsed | Cancelled | Inactive` and `Lapsed → Active` grace, Customer `active → suspended | closed` (cascading to Profiles), Account `active → suspended → closed` — recording `CustomerSuspended` / `CustomerReactivated` / `CustomerClosed` / `ProfileSuspended` / `ProfileReactivated` / `ProfileExpired` / `ProfileRenewed` / `ProfileInactive` (Account transitions and Profile cancellation audit-only)
- **AND** placing a Hold drives the covered scope (in its suspendable from-state) to `suspended` and lifting the last covering Hold restores it
- **AND** the Hero Package Capacity Invariant (approval and activation stay uncapped), the `Applied → WaitingList` path, and Customer-segment derivation do **not** exist; no `WaitingListJoined` / `CustomerSegmentChanged` event is recordable; and no `ActivateAccount` Action exists

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 / § 4.2.1 / § 4.3 / § 4.7 (per-entity state machines + birth states; the demand-side status FSMs now implemented) · § 4.8 / § 4.8.1 (the unified Hold registry + the `kyc` compliance coupling) · § 10.1 (Hold→suspension coupling — now implemented) · § 9.1 / § 9.2 (KYC and sanctions screening FSMs) · § 13 (Hero Package Capacity Invariant — deferred Module-A seam) · § 5 (Customer segments — deferred) · § 15 (lifecycle event families; no Account event; no `ProfileCancelled`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-1 (Customer FSM + events), AC-K-FSM-2 / AC-K-FSM-2a (Profile FSM + suspension state-preservation), AC-K-FSM-9 (Account FSM; Holds drive `active → suspended`), AC-K-FSM-12 (lapsed grace), AC-K-FSM-13 (terminal soft-delete), AC-K-EVT-1 / AC-K-EVT-5 (the status events), AC-K-J-13 / AC-K-XM-18 (Hero Package capacity reads Module A `qty` — deferred) · decisions/2026-06-19-hold-status-coupling.md (the Hold→status coupling) · spec/05-release/Build_Workplan_v0.3-MVP.md § Phase 2 (the demand-side membership lifecycle) · openspec/changes/parties-membership-suspension/proposal.md (the suspension subset implemented here; only the capacity, WaitingList and segment seams remain deferred)._
