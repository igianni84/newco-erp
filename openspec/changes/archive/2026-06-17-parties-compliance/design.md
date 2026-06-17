# Design — parties-compliance

## Context

`parties-core` stood up Customer and Producer with their identity + status columns but **no compliance fields**; `parties-producer-lifecycle` shipped `ActivateProducer` **ungated**, recording in the shipped `party-registry` spec that the § 4.4 KYC precondition is a deferred seam *"`parties-compliance` SHALL tighten it."* This change is that tightening plus the KYC/sanctions state it needs. It is **additive**: nullable columns over two existing tables, new enums/Actions/events/exceptions, and **one MODIFIED behaviour** (`ActivateProducer` gains the KYC gate). It introduces **no new dependency**.

The platform substrate this builds on (verified in `app/Platform/Events` and `app/Modules/Parties`):

- `DomainEventRecorder::record(string $name, string $module, ActorRole $actorRole, ?int $actorId, string $entityType, string $entityId, array $payload, ?string $correlationId = null, ?int $causationId = null): DomainEvent` — appends the event inside the caller's open transaction (throws at `transactionLevel() === 0`); inline delivery post-commit (no `queued` consumer, no ADR gate).
- `ActorContext::role(): ActorRole` / `actorId(): ?int` (`ActorRole::System` until principals wire in).
- `Module::Parties->value === 'parties'`.
- The migration idiom (`2026_06_15_000005_create_parties_customers_table.php`): a value-set column is `string` + the backed-enum cast on both engines, **plus a PostgreSQL-only `CHECK`** whose accepted set derives from `Enum::cases()` (SQLite skips the `CHECK`; the cast carries the floor). This change reuses that idiom for **nullable** columns (`CHECK (col IS NULL OR col IN (...))`).
- The transition-Action idiom (`ActivateProducer`, `SunsetClub`): `DB::transaction` → `lockForUpdate` re-read → from-state assert → write → `record(...)`. The Models stay persistence-only (`$guarded = []`); the **Action is the sole writer** of state.
- Boundary law: every reference here is within Module K (`ModuleBoundariesTest`); no new model (columns on existing tables) → `ModulePersistenceConventionsTest` unaffected.

## Goals / Non-Goals

**Goals:**
- Add the Customer KYC FSM (`not_required → pending → verified|rejected`) + `kyc_required` + enhanced-KYC trigger fields, separate from the Customer status FSM.
- Add the Customer sanctions FSM (`pending → passed|failed|under_review`, `under_review → passed|failed`), independent of KYC, with `last_screening_at` / `next_rescreen_at` / `trigger_source`.
- Add the Producer KYC FSM, with the operator-waive (`→ not_required`).
- Record the four sanctions screening events (`CustomerOnboardingScreening{Passed,Failed}`, `CustomerRescreening{Passed,Failed}`); record **no** KYC event.
- **Tighten `ActivateProducer`** to `cleared = verified ∨ not_required ∨ NULL`, blocking on `pending`/`rejected` — closing the deferred seam, unblocking nothing further (Module 0 already gates on producer-`active`).
- Every transition from-state guarded and race-safe; manual-first (operator records verdicts); additive migration verified on PostgreSQL 17.

**Non-Goals:**
- The unified **Hold registry** (6 types, 3 scopes, trigger-agnostic), the **`kyc` Hold** auto-place/auto-lift coupling, Hold-lift discipline, and the **DEC-181 structural read-API gate** — all `parties-holds`.
- The **sanctions order-completion enforcement** (Module S S.15) and every other DEC-181 surface — the owning downstream modules. Module K is sanctions-blind by design.
- The **Customer `pending → active`** status gate (KYC/T&C) — demand-side (`parties-membership-lifecycle`).
- The **enhanced-KYC detection** (€10k/€50k scan), the **automated 12-month cadence** job, and the **AML-threshold auto re-screen** — deferred automation (no spend data; manual-first). Fields + operator ad-hoc re-screen ship.
- **KYC document handling/storage** (object-storage gate) and the **Filament compliance console** (`parties-operator-console`).

## Decisions

### L1 — Additive nullable columns; the NULL semantics are asymmetric **by design**
The migration is `Schema::table` over `parties_customers` (+ `kyc_status`, `kyc_required`, `enhanced_kyc_flag`, `enhanced_kyc_at`, `sanctions_status`, `last_screening_at`, `next_rescreen_at`, `screening_trigger_source`) and `parties_producers` (+ `kyc_status`). Every column is **nullable with no default** (DEC-071 — entities are creatable un-screened; no backfill). The value-set columns reuse the migration idiom: `string` + enum cast + a PG-only `CHECK (col IS NULL OR col IN (<Enum::cases()>))`. The two NULL meanings are **deliberately different**, and each is tested on both engines:
- **Producer `kyc_status = NULL` ⇒ cleared** (an existing/never-screened Producer activates — preserves the shipped ungated behaviour additively).
- **Customer `sanctions_status = NULL` ⇒ not-`passed`/blocked** (un-screened ≠ cleared; the downstream purchase gate, when it lands, blocks until an explicit `passed`).
_Alternative rejected:_ backfill columns to a non-null default. Rejected — DEC-071 mandates nullable (creatable un-screened); a `pending` backfill on Customers would silently change the un-screened semantics, and a `not_required` backfill on Producers is unnecessary (NULL already clears).

### L2 — Transitions are explicit operator Actions; the Action is the sole writer; two new guard exceptions
Each transition is a single-purpose Action under `app/Modules/Parties/Actions/`, mirroring `ActivateProducer`/`SunsetClub`: `DB::transaction` → `lockForUpdate` re-read → from-state assert → write the column → (sanctions only) `record(...)`. The Models stay persistence-only. Two new exceptions follow the `Illegal*Transition` house style: `IllegalKycTransition` (Customer + Producer KYC; named factories `::cannotVerify(KycStatus $from)`, `::cannotReject(...)`, `::cannotRequire(...)`, `::cannotWaive(...)`) and `IllegalSanctionsTransition` (`::onboardingAlreadyScreened()`, `::cannotResolve(SanctionsStatus $from)`), each resolving localized copy from new `lang/en/parties.php` groups (`kyc`, `sanctions`; `:state` placeholder — not PII). _Alternative rejected:_ transition methods on the models — scatters the writer surface, weakening the immutability discipline the spine rests on.

### L3 — KYC records **no** domain event; only sanctions emits (four events)
The PRD event catalog (§ 15.1) names **no KYC event** — KYC state changes are audit-only (the recorder still writes nothing; the change is observable via the audit trail / the column itself). Inventing `CustomerKycVerified`/`ProducerKycCleared` for symmetry is **rejected** (the same "invent no event" discipline `parties-producer-lifecycle` applied — only spec-named events). Sanctions emits exactly the four § 15.6 names, as `final` event classes (`const NAME`, `const ENTITY_TYPE = 'Customer'`, static PII-free `payload(Customer): array` → `{customer_id, sanctions_status, trigger_source}`), recorded in the screening Action's transaction. Consequence for `parties-holds`: because KYC has no event, the future `kyc` Hold coupling cannot be event-driven — it will be **within-module Action orchestration** (the KYC Action calls the Hold place/lift), exactly as `RetireProducer` calls `SunsetClub`. This change leaves the KYC Actions as the single writers so that coupling is a clean later addition.

### L4 — Sanctions FSM: verdict + trigger_source; onboarding-vs-rescreen by source; `under_review` is event-silent
One Action records a screening: it takes a verdict (`passed | failed | under_review`) and a `trigger_source`. It sets `sanctions_status`, stamps `last_screening_at = now()`, sets `next_rescreen_at = now()->addMonths(12)` (the scheduled moment — the **job that reads it is deferred**), and records the `trigger_source`. The event family is chosen by source: `trigger_source = onboarding` → `CustomerOnboardingScreening{Passed,Failed}`; any other source → `CustomerRescreening{Passed,Failed}`. A `passed`/`failed` outcome is a **completion** and emits; **`under_review` emits nothing** (the § 15.6 catalog has only Passed/Failed; a later resolution to passed/failed emits the rescreening event). Guard: a verdict with `trigger_source = onboarding` requires `last_screening_at IS NULL` (it is the first screen) — else `IllegalSanctionsTransition::onboardingAlreadyScreened()`. Re-screens are admissible from any prior state (re-screening can flip `passed → failed`), so the from-state guard is intentionally permissive there; the meaningful invariant is the onboarding-is-first one. _Alternative rejected:_ two separate Actions (RecordOnboarding / RecordRescreen) — duplicates the transition body; one Action keyed on `trigger_source` is the single writer of `sanctions_status` and the four events.

### L5 — Producer-gate retro-tighten: MODIFY `ActivateProducer`, flip the shipped test, add a NULL regression
`ActivateProducer` gains one precondition after the from-state assert: `kyc_status ∈ {verified, not_required} ∨ kyc_status IS NULL` ⇒ proceed; `pending`/`rejected` ⇒ reject with a localized reason (a new `IllegalProducerTransition::kycNotCleared(KycStatus $from)` factory, or a dedicated guard message — settled in tasks) leaving the Producer `draft` and recording no event. The shipped `ProducerLifecycleTest` scenario *"Activation enforces no KYC gate in this slice"* is **replaced** by the AC-K-FSM-7 matrix (positive `verified`/`not_required`/NULL; negative `pending`/`rejected`), and a **NULL-cleared regression** asserts a Producer created before this change (NULL `kyc_status`) still activates. This is the only behaviour change in the slice; everything else is additive. _Note:_ Module 0's gate is unaffected — it reads producer-`active`, inheriting the tightening transitively (design D6 of `catalog-lifecycle-approval`).

### L6 — Manual-first screening; the vendor adapter is a seam
Screening is operator-recorded (§ 9.5 — "the screen is non-negotiable; only the integration is deferrable"). The Actions take the verdict as input; **no screening-vendor adapter is built** (no HTTP client, no provider config). The acceptance criteria drive the lifecycles by setting state, exactly as § 9.5 anticipates. The automated synchronous adapter, the daily 12-month cadence job, and the AML-threshold cumulative-totals scan are documented seams (no code). This keeps the change off the (non-existent) screening-vendor ADR and the object-storage gate (no KYC documents handled here).

### L7 — Enhanced-KYC: fields ship, detection deferred
`enhanced_kyc_flag` + `enhanced_kyc_at` are additive nullable fields (§ 4.1). The **detection** (the €10k-single / €50k-cumulative crossing) reads cumulative-purchase data owned by Module S/E, which do not exist at launch — so **no detection job** is built; the fields are written only by a future automation (or an operator) when that data exists. Documented as a seam; a test asserts no code auto-sets them from totals.

### L8 — Operator actions via the `ActorContext` seam
These are Compliance-Reviewer / Producer-Onboarding operator transitions; the actor is resolved from `ActorContext` exactly as the spine Actions do (`ActorRole::System` until principals wire in). No new actor concept, no auth work — the operator-console that *drives* these Actions is `parties-operator-console`.

## Risks / Trade-offs

- **The asymmetric NULL semantics (L1) are a live cross-engine trap.** Producer-KYC-NULL-cleared vs Customer-sanctions-NULL-blocked must each be asserted on **PostgreSQL 17** (the nullable `CHECK (col IS NULL OR col IN (...))` must accept NULL on PG; a malformed `CHECK` would reject inserts). Every DB-touching task is PG17-verified before close (`knowledge/testing/rules.md`); SQLite-green is necessary, never sufficient.
- **Flipping a shipped, tested behaviour (L5).** `ActivateProducer` was tested as ungated; the test is **updated, not duplicated**, and a NULL-cleared regression guards the additive-safety of existing rows. The `parties-producer-lifecycle` archive's `progress.md` notes stay historical; the live spec is the MODIFIED requirement.
- **Scope creep into Hold / Module S / demand-side.** The MODIFIED *Birth States* requirement + a test assert no `kyc` Hold is placed, no Customer-status transition exists, and no sanctions enforcement (the order-completion gate) is implemented here. Adding any of those is out of scope.
- **Inventing a KYC event for symmetry (L3).** Rejected — only the four § 15.6 sanctions names. The `kyc` Hold coupling later is Action-orchestrated, not event-driven (KYC has no event).
- **`under_review` event-silence (L4).** A reviewer expecting "every screening emits" would be wrong; the catalog has only Passed/Failed. Tests assert `under_review` records nothing and the resolution emits the rescreening event.
- **`next_rescreen_at` with no consumer.** The field is stamped 12 months forward but no job reads it at launch (the cadence is deferred). This is the seam, not dead code — it is the exact field the deferred daily job will query.

## Open Questions

None blocking. The slice boundary (Hold split → `parties-holds`) was ratified with the user before authoring (AskUserQuestion 2026-06-17). The cleared-state semantics (`verified ∨ not_required`, NULL-cleared for additivity) are fixed by ADR `2026-06-17-producer-kyc-gate-not-required-clears.md`. Whether the **standalone** operator screening Actions are exercised at launch (vs only via the future console) is a `parties-operator-console` concern — the Actions ship regardless; their driving UI is deferred.
