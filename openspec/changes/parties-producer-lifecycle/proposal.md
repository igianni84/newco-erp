# Proposal — parties-producer-lifecycle

## Why

This is the **first lifecycle slice of Module K (Parties)** and the direct follow-on to the archived `parties-core` spine. The spine stored every entity's full state domain as an enum column but **wrote no transition** (`parties-core` Requirement: "Birth States Recorded, Lifecycle Transitions Deferred"); the deferred-table named the umbrella follow-on `parties-membership-lifecycle`. That umbrella is far larger than one change (six FSMs, ~24 transitions, the Originating-Club lock, the Customer-segment view, plus Module-E and Module-A seams), so it is sliced — and this change is the **supply-side** slice: the lifecycle of the three producer-anchored entities **Producer, ProducerAgreement and Club**.

Supply-side goes first for two concrete reasons. (1) **It is self-contained**: Producer/ProducerAgreement/Club have **no `suspended` state** (so no dependency on the deferred `parties-holds` registry) and depend on **neither Module E (Finance) nor Module A (Allocation)** — the only external precondition is the KYC gate on Producer activation, which is a deferred seam (§ below). (2) **It unblocks Catalog**: this slice **emits `ProducerActivated` / `ProducerRetired`** (Module K PRD § 15.4), the exact events the Catalog follow-on `catalog-lifecycle-approval` consumes to gate Product Master activation (`Build_Workplan_v0.3-MVP.md` § Phase 2; PRD AC-K-XM-2). Delivering it as a small, early change unblocks Catalog sooner than the umbrella would.

It steps through **no open ADR gate**: domain events deliver **inline** at launch (`decisions/2026-06-12-event-substrate-and-audit-store.md`), so recording lifecycle events — like the spine's `*Created` events — introduces **no `queued` consumer**, and the cascade is orchestrated synchronously within the emitting transaction. No new architectural decision is required.

## What Changes

- **Producer FSM** (`draft → active → retired`; PRD § 4.4) gains its two transitions as explicit Actions:
  - **`ActivateProducer`** (`draft → active`) records **`ProducerActivated`** (§ 15.4). The PRD's KYC-verified precondition (AC-K-FSM-7) is a **deferred seam** — the KYC four-state lifecycle is owned by `parties-compliance` (DEC-071: sanctions/KYC fields are nullable, added additively there), so this slice ships the transition ungated; compliance tightens it later.
  - **`RetireProducer`** (`active → retired`) records **`ProducerRetired`** (§ 15.4) and **cascades**: every Club the Producer operates that is currently `active` transitions to `sunset` (PRD § 10.2 offboarding cascade; AC-K-J-19), each recording its own **`ClubSunset`** caused by the retirement. The **Profile leg** of the § 10.2 cascade (per-Profile cancellation) is **deferred** — Profile transitions are demand-side.
- **ProducerAgreement FSM** (`draft → active → superseded | terminated`; PRD § 4.6.1) gains:
  - **`ActivateProducerAgreement`** (`draft → active`) records **`ProducerAgreementActivated`** (§ 15.5) and enforces **BR-K-Agreement-1** (at most one `active` agreement per scope) at activation: if an `active` agreement already exists in the **same scope** — the `(producer_id, club_id)` tuple, treating a `NULL` `club_id` as the distinct Producer-wide scope — that prior agreement transitions `active → superseded` recording **`ProducerAgreementSuperseded`** in the same transaction, pairing old + new in the audit (AC-K-J-12).
  - **`TerminateProducerAgreement`** (`active → terminated`) records **`ProducerAgreementTerminated`** (§ 15.5). Termination does **not** cascade to Producer state (§ 4.6.1).
- **Club FSM** (`active → sunset → closed`; PRD § 4.3) gains:
  - **`SunsetClub`** (`active → sunset`) records **`ClubSunset`** (§ 15.3). It is both a standalone operator action and the routine the Producer-retirement cascade invokes (single `ClubSunset` writer).
  - **`CloseClub`** (`sunset → closed`) records **`ClubClosed`** (§ 15.3). The PRD's "all members migrated or expired" precondition (§ 4.3) reads Profile state, which does not exist in this slice; it is a **deferred seam** (vacuously satisfiable today — no Profile can be `Active` without the demand-side transitions — and tightened when Profile lifecycle lands).
- **Seven lifecycle events** (verbatim PRD § 15.3/15.4/15.5): `ProducerActivated`, `ProducerRetired`, `ProducerAgreementActivated`, `ProducerAgreementSuperseded`, `ProducerAgreementTerminated`, `ClubSunset`, `ClubClosed` — each a `final` event class (`NAME` / `ENTITY_TYPE` / static PII-free `payload()`), recorded through the platform `DomainEventRecorder` inside the transition's transaction, with **causation/correlation threading** on the two derived chains (cascade `ClubSunset` ← `ProducerRetired`; `ProducerAgreementSuperseded` ← `ProducerAgreementActivated`).
- **Three transition-guard exceptions** (`IllegalProducerTransition`, `IllegalProducerAgreementTransition`, `IllegalClubTransition`) + their localized operator copy in `lang/en/parties.php`: every transition is **from-state guarded** (race-safe via `lockForUpdate` re-read inside the transaction) and rejects an out-of-state call with a clean localized reason.
- **`Producer::clubs()`** within-module `hasMany` relation — the read the retirement cascade walks.
- **No migration.** Every state column and its driver-guarded `CHECK` already exist on the spine; this slice writes transitions over them and emits events from already-defined enums. The supersession audit links (`supersedes` / `superseded_by`) live in the **event payload**, not in new columns.
- **Docs**: extend `CONTEXT.md` with the resolved supply-side lifecycle terms (sunset, retire, supersede, the agreement scope), and a Parties contract note documenting the seven lifecycle event payloads and the two deferred seams (KYC-on-activation, all-members-gone-on-close).

### Slice boundary — deliberately NOT in this change

| Deferred concern | Future change | Why not here |
|---|---|---|
| **Customer / Account / Profile** FSM transitions, Profile **approval/activation**, the producer **membership approve/decline** write (L-PP), **`OriginatingClubLocked`** at first approval, the **Hero Package Capacity Invariant**, the **Customer-segment view**, and all demand-side lifecycle events | the **demand-side** slice(s) (`parties-membership-lifecycle` and/or a `parties-customer-lifecycle` split, decided when authored) | Profile activation needs Module E `MembershipFeePaid`; Hero capacity needs Module A `qty`; the OC lock fires on the membership-approval write — all demand-side. Out of the supply-side slice by construction. |
| **KYC / sanctions** gate on Producer (and Customer) activation | **`parties-compliance`** | The four-state KYC/sanctions lifecycles + provider are a separate change (DEC-071); their fields are nullable and added additively. This slice ships `ActivateProducer` with the gate as a documented seam. |
| The **Profile leg** of the § 10.2 retirement cascade (per-Profile cancellation; Module S Club-Credit conversion, DEC-043) | the **demand-side** slice | Requires Profile transitions, which do not exist until the demand-side slice. This slice cascades Producer → Club only. |
| **Hold-driven suspension** | **`parties-holds`** | Not applicable to supply-side anyway — Producer/Agreement/Club have no `suspended` state. |
| The richer **Creator → Reviewer → Approver** multi-role activation workflow (AC-K-J-10) and any **Filament** Parties console | **`parties-operator-console`** | Presentation / operator-workflow layer over these domain Actions. This slice is the domain transition + event contract; activation is a single operator Action via the `ActorContext` seam. |

## Capabilities

### New Capabilities

_None._ This change extends the existing `party-registry` capability with lifecycle behavior; it introduces no new capability.

### Modified Capabilities

- `party-registry`: the supply-side entities gain their lifecycle transitions and events. **ADDED** — *Producer Lifecycle* (activate, retire + Club-sunset cascade), *ProducerAgreement Lifecycle* (activate with per-scope supersession, terminate), *Club Lifecycle* (sunset, close), *Supply-Side Lifecycle Events* (the seven verbatim events, PII-free payloads, transactional recording, causation threading). **MODIFIED** — *Birth States Recorded, Lifecycle Transitions Deferred*: narrowed so the supply-side transitions are no longer deferred while the demand-side transitions, the Originating-Club lock, the Hero Package Capacity Invariant and the Customer-segment view remain deferred.

## Impact

- **New code** — `app/Modules/Parties/Events/{ProducerActivated,ProducerRetired,ProducerAgreementActivated,ProducerAgreementSuperseded,ProducerAgreementTerminated,ClubSunset,ClubClosed}.php`; `app/Modules/Parties/Actions/{ActivateProducer,RetireProducer,ActivateProducerAgreement,TerminateProducerAgreement,SunsetClub,CloseClub}.php`; `app/Modules/Parties/Exceptions/{IllegalProducerTransition,IllegalProducerAgreementTransition,IllegalClubTransition}.php`; a `clubs()` relation on `app/Modules/Parties/Models/Producer.php`; new groups in `lang/en/parties.php`; tests in `tests/Feature/Modules/Parties/` (+ exception unit coverage).
- **No migration, no new dependency** — `git diff main -- composer.json composer.lock` stays empty; `database/migrations/` is untouched (the state columns + their `CHECK`s ship from `parties-core`).
- **Reuses, does not modify** — `DomainEventRecorder` (including its `causationId` / `correlationId` parameters and `DomainEvent` return), `ActorContext`/`ActorRole`, the `Module` enum (`Module::Parties->value`), and the eight Parties enums (whose full state domains were defined for exactly this change). The spine Models stay persistence-only (`$guarded = []`); the **transition Action is the sole writer of state**, preserving the immutability discipline for `party_type` / `producer_id` / the Club↔Producer link.
- **Arch tests need no amendment** — every reference is within Module K (`ModuleBoundariesTest`); no new model means `ModulePersistenceConventionsTest` is unaffected.
- **DB engines** — the transitions and their events touch the database, so every transition task is verified on **PostgreSQL 17** before close, in addition to SQLite (`knowledge/testing/rules.md`); the partial scope query for supersession is written NULL-safe (`whereNull` for the Producer-wide scope) and verified on both engines.
- **Deliberate traceability gaps** — each deferred concern above maps to a named future change. Two spec-faithful seams are recorded explicitly: **Producer activation carries no KYC gate yet** (deferred to `parties-compliance`) and **Club closure carries no all-members-gone gate yet** (deferred with Profile lifecycle).
