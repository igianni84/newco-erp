# Design — parties-producer-lifecycle

## Context

`parties-core` (archived) stood up the seven Module K entities with their full state-domain enums but wrote **no transition** — every entity is born and frozen in its birth state, and the spine's tests assert that no `*Activated`/lifecycle event is recordable. This change is the first lifecycle slice: the **supply-side** transitions for Producer, ProducerAgreement and Club. It is purely additive domain logic + events + tests over the existing schema; it introduces **no migration** and **no new dependency**.

The platform substrate this change builds on (verified in `app/Platform/Events`):

- `DomainEventRecorder::record(string $name, string $module, ActorRole $actorRole, ?int $actorId, string $entityType, string $entityId, array $payload, ?string $correlationId = null, ?int $causationId = null): DomainEvent` — appends the event + one `pending` `event_deliveries` row per registered consumer, **inside the caller's open transaction** (throws `NotInTransactionException` at `transactionLevel() === 0`); `correlation_id` defaults to the event's own `event_id` for a root event; **returns the persisted `DomainEvent`** (so a follow-on event in the same transaction can use `$event->id` as its `causationId` and `$event->correlation_id` to thread a chain). Inline delivery fires post-commit (no `queued` consumer; no ADR gate).
- `ActorContext::role(): ActorRole` / `actorId(): ?int` — the acting principal (`ActorRole::System` until real principals wire in).
- `Module::Parties->value === 'parties'`.
- The eight Parties enums already declare the **full** state domains (e.g. `ProducerStatus{Draft,Active,Retired}`, `ClubStatus{Active,Sunset,Closed}`, `ProducerAgreementStatus{Draft,Active,Superseded,Terminated}`) — this change writes transitions across values that already exist with their PG `CHECK` constraints.
- Boundary law: `tests/Architecture/ModuleBoundariesTest.php` (every reference here is within Module K) and `ModulePersistenceConventionsTest.php` (no new model → unaffected).

## Goals / Non-Goals

**Goals:**
- Implement the supply-side FSMs: Producer `draft→active→retired`, ProducerAgreement `draft→active→superseded|terminated`, Club `active→sunset→closed`.
- Record the seven verbatim lifecycle events transactionally, PII-free, with causation/correlation threading on the two derived chains.
- Enforce BR-K-Agreement-1 (one active agreement per `(producer_id, club_id)` scope) at activation, with automatic supersession.
- Perform the Producer-retirement → Club-sunset cascade (Producer→Club leg only).
- Make every transition from-state guarded and race-safe.
- Emit `ProducerActivated`/`ProducerRetired` so `catalog-lifecycle-approval` is unblocked.

**Non-Goals:**
- Any Customer / Account / Profile transition, the Originating-Club lock, the Hero Package Capacity Invariant, the Customer-segment view, or the producer membership approve/decline (L-PP) write — all demand-side.
- The KYC/sanctions gate on activation (deferred to `parties-compliance`) and the all-members-gone gate on Club closure (deferred with Profile lifecycle).
- The Profile leg of the § 10.2 cascade; any Filament/operator-console surface; the multi-role Creator→Reviewer→Approver activation workflow.
- Any schema change. The state columns, their `CHECK` constraints, and the version column already exist.

## Decisions

### L1 — Transitions are explicit Actions; the Action is the sole state writer
Each transition is a single-purpose Action under `app/Modules/Parties/Actions/` (`ActivateProducer`, `RetireProducer`, `ActivateProducerAgreement`, `TerminateProducerAgreement`, `SunsetClub`, `CloseClub`), mirroring the spine's `Create*` seam. The Models stay **persistence-only** (`$guarded = []`, no transition methods): the immutability discipline carried over from `parties-core` — "`$guarded = []` is safe because the Action is the only writer" — extends to state. No transition Action writes `party_type`, `producer_id`, or the Club↔Producer link, so those stay immutable by construction; only `status` is written, which is precisely what the FSM is for. _Alternative rejected:_ transition methods on the model (`$producer->activate()`) — would scatter the writer surface and weaken the "Action is the sole writer" guarantee the spine's immutability rests on.

### L2 — From-state guard via a transaction-locked re-read; three entity-specific exceptions
Every Action runs `DB::transaction(fn () => …)`, re-reads the target row with `->lockForUpdate()`, asserts the expected from-state, and only then writes + records the event. The lock makes concurrent transition attempts serialize (on PostgreSQL; a harmless no-op on SQLite, where the single-writer model keeps the logic correct), so two operators cannot both "activate" the same draft. An out-of-state call throws a localized transition exception — `IllegalProducerTransition`, `IllegalProducerAgreementTransition`, `IllegalClubTransition` — each `extends RuntimeException` with named factory methods (`::cannotActivate(StatusEnum $from)`, `::cannotRetire(...)`, `::cannotSunset(...)`, `::cannotClose(...)`, `::cannotTerminate(...)`) resolving copy from new groups in `lang/en/parties.php` (dotted keys, `:state` placeholder — the state token is not PII). _Alternative rejected:_ one generic `IllegalStateTransition` — the spine's exception style is specific + self-documenting, and entity-specific classes give callers/tests a precise catch.

### L3 — No migration; transitions update `status` in place; `version` untouched
The state columns and their driver-guarded `CHECK`s ship from `parties-core`, so this change adds **zero** migrations. A transition `update`s the `status`/state column in place; the **domain event is the immutable audit record** of the change (10-year store), so there is no row-versioning of transitions — `version` stays reserved for identity-attribute revisions (its `parties-core` meaning) and is **not** bumped by a state transition. The supersession audit linkage lives in the **event payload** (`supersedes` / `superseded_by`), not in any new column.

### L4 — Seven `final` event classes; transactional, PII-free recording
Each event is a `final` class under `Events/` with untyped `const NAME` (the verbatim § 15 name), `const ENTITY_TYPE`, and a static `payload(Model): array`, exactly the `*Created` shape. The Action records via `DomainEventRecorder::record(...)` inside its transaction (`Module::Parties->value`, `ActorContext`-resolved role/id, stringified id). Payloads are PII-free by nature — Producer/ProducerAgreement/Club hold no personal data; other parties appear by id. Payload sketches: `ProducerActivated`/`ProducerRetired` → `{producer_id, status}`; `ClubSunset`/`ClubClosed` → `{club_id, producer_id, status}`; `ProducerAgreementActivated` → `{producer_agreement_id, producer_id, club_id, status, supersedes}`; `ProducerAgreementSuperseded` → `{producer_agreement_id, producer_id, club_id, status, superseded_by}`; `ProducerAgreementTerminated` → `{producer_agreement_id, producer_id, club_id, status}`.

### L5 — Causation / correlation threading on the two derived chains
`record()` returns the `DomainEvent`, so a chain is threaded by passing the root's `->id` as `causationId` and `->correlation_id` as `correlationId` on the derived events. Cascade: `RetireProducer` records `ProducerRetired` first, then each cascade `ClubSunset` with `causationId: $retired->id, correlationId: $retired->correlation_id`. Supersession: `ActivateProducerAgreement` records `ProducerAgreementActivated` first, then (if a prior active was found) `ProducerAgreementSuperseded` with `causationId: $activated->id, correlationId: $activated->correlation_id`. This makes the offboarding and renewal stories one queryable thread in the audit log — the right thing for a 10-year financial-grade store. _Note:_ `causationId` is the `int` `id`; `correlationId` is the `string` `event_id`/`correlation_id` — do not cross the types.

### L6 — Cascade is synchronous in-Action; `SunsetClub` is the single `ClubSunset` writer
`RetireProducer` orchestrates the cascade **inside its own transaction**: it transitions the Producer, then walks `Producer::clubs()` (a new within-module `hasMany`) filtered to `status = active`, calling `SunsetClub->handle($clubId, causationId: $retired->id, correlationId: $retired->correlation_id)` per Club. `SunsetClub->handle(int $clubId, ?int $causationId = null, ?string $correlationId = null)` is therefore the single writer of `ClubSunset`: standalone operator use passes nulls (a root event); the cascade passes the retirement linkage. Re-entrant `DB::transaction` nesting (savepoints) keeps the whole cascade all-or-nothing, and the recorder's open-transaction guard is satisfied throughout. _Alternative rejected:_ an event-driven listener on `ProducerRetired` that sunsets Clubs — adds a within-module consumer + delivery rows and runs post-commit (outside the retirement's transaction), losing the all-or-nothing guarantee a single multi-entity transaction gives; within a module, direct orchestration is allowed and stronger.

### L7 — Agreement supersession is automatic, application-enforced, scope-aware, NULL-safe
Activation enforces single-active-per-scope in the Action (the spec calls it an "activation-time invariant"), not via a structural index. Scope = `(producer_id, club_id)`; the prior-active lookup is **NULL-safe** — `whereNull('club_id')` when activating a Producer-wide agreement, `where('club_id', $clubId)` otherwise — because `where('club_id', null)` would emit `club_id = NULL` (never true). _Alternative rejected:_ a partial unique index `(producer_id, club_id) WHERE status = 'active'` — to catch two Producer-wide actives it needs `NULLS NOT DISTINCT` (PG15+), which **SQLite does not support**, breaking the Postgres-truthful/SQLite-compatible rule; and the spec models the rule as activation-time, so the application guard is the faithful home. (Belt-and-braces beyond launch could add a PG-only expression index keyed on `COALESCE(club_id, 0)`; out of scope here.)

### L8 — Two deferred seams, implemented as ungated transitions
Producer activation carries **no KYC gate** (the KYC fields don't exist; `parties-compliance` owns them per DEC-071) and Club closure carries **no all-members-gone gate** (it reads Profile state, which doesn't exist until the demand-side slice). Both transitions ship ungated, documented as seams the named future change tightens — the same "seam now, behavior later" discipline the spine used for `originating_club_id`. Tests assert the transition succeeds today and reference the future tightening.

### L9 — Operator actions via the `ActorContext` seam
These are operator transitions (Producer Onboarding Operator, etc.); the actor is resolved from `ActorContext` exactly as the spine's `Create*` actions do (`ActorRole::System` until principals wire in). No new actor concept, no auth work.

## Risks / Trade-offs

- **SQLite-green is necessary, never sufficient** → every transition task is verified on **PostgreSQL 17** before close (`knowledge/testing/rules.md`). Live traps: the NULL-scope supersession query (assert on PG that a second Producer-wide activation supersedes the first — `where('club_id', null)` silently matches nothing); `lockForUpdate` is a real row lock on PG and a no-op on SQLite (the from-state assert must carry correctness, not the lock); `timestamptz` `+00` if any raw timestamp is asserted; `causation_id` (int) vs `correlation_id` (string) must round-trip through the jsonb-free envelope columns.
- **Re-recording the spine's "no lifecycle event" assumption** → `tests/Feature/Modules/Parties/SpineCreationChainTest.php` asserts the **creation** chain emits no `*Activated`/`OriginatingClubLocked`. That stays true (creation ≠ transition) and must stay **green unamended**; the new transition events are exercised only by the new transition tests, each isolated under `RefreshDatabase`.
- **Scope creep into the demand-side** → the MODIFIED requirement + a test assert that Customer/Account/Profile still expose no transition and `originating_club_id` still has no mutation surface after this change. Adding a Profile or Customer transition here is out of scope.
- **Double-transition race** (two operators activate the same draft) → the `lockForUpdate` re-read inside the transaction (L2) serializes them; the second sees the post-first state and is rejected.
- **Cascade over-reach** → the cascade is Producer→Club only; the Profile leg is explicitly deferred (L6). A Club already `sunset`/`closed` is skipped, so re-running or partial states don't double-emit.
- **Inventing events for symmetry** → rejected: only the seven § 15 names. There is **no** `ProducerAgreement`-creation-style event for the no-op activation, no decline event, no Profile event.

## Open Questions

None blocking. The two scope forks (agreement-scope granularity; CloseClub inclusion) and the two seam choices (KYC, all-members-gone) were resolved before authoring (AskUserQuestion 2026-06-15 + DEC-071). Whether the **standalone** `SunsetClub`/`CloseClub` operator path is exercised at launch (vs Club sunset arriving only via the cascade) is an operator-console concern — both Actions ship regardless; their triggering UI is `parties-operator-console`.
