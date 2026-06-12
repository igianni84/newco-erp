# Proposal — foundations-domain-events-audit

## Why

Build Workplan Phase 1 demands an event substrate ("bus/queue, ordering + idempotency, audit storage"), the audit floor ("every state transition + financial event captured for 10-year retention"), and a hello-world "exercising DB + event bus + audit trail + pipeline" (`spec/05-release/Build_Workplan_v0.3-MVP.md` §2 Phase 1). The substrate ADR (`decisions/2026-06-12-event-substrate-and-audit-store.md`) decided the shape — a transactional outbox on the app DB whose append-only log IS the 10-year audit/financial event store — and the skeleton change (F1 1/3, archived) explicitly deferred its substance here: the event tables and migrations, the platform substrate namespace, and the first `pgsql` CI lane (`decisions/2026-06-12-production-db-engine.md` parity guardrail). This change implements that ADR; it decides nothing the ADR already decided. It is the second of the three F1 foundations changes (next: `foundations-money-i18n-flags`).

## What Changes

- **The three platform tables** (the repo's first domain migrations, "Postgres-truthful, SQLite-compatible"): `domain_events` (append-only — simultaneously transactional outbox, inter-module API record, 10-year audit log and financial event store), `audit_records` (operator/system action audit: before/after + `authorization_basis`), `event_deliveries` (mutable per-event×consumer delivery ledger). Launch indexes per the ADR; unprefixed names per `docs/module-template.md` §6 (platform, not module).
- **Immutability, three layers**: DB triggers on BOTH engines with full parity (`domain_events`: no UPDATE/DELETE ever; `audit_records`: no DELETE, UPDATE only when nothing but `before`/`after` changes — the GDPR redaction seam); the production REVOKE grants (app role INSERT+SELECT; `redactor` role column-UPDATE on `before`/`after`) documented as a runbook for the hosting gate; additive-only migration policy on both tables declared.
- **A new platform root `App\Platform`** (founder-confirmed 2026-06-12) hosting the substrate: `App\Platform\Events\*` (the three concerns' machinery — Eloquent models, `DomainEventRecorder` with in-transaction guard, consumer contract + registry, inline delivery executor, scheduled sweep) and `App\Platform\Audit\*` (`AuditRecord` model + recorder). The architecture-test platform list is extended in this same change per the amendment protocol (template §3, design D1).
- **Delivery semantics made real**: `inline` launch default (post-commit, same process, DB work only — never external I/O), per-consumer independent rows and retries (R4 mechanized), scheduled sweep as the at-least-once guarantee, exactly-once for DB effects (handler + ledger update share one transaction), exponential backoff, dead-letter as a `failed` status. `queued` is NOT built (queue-driver ADR gate, expected F4–F6).
- **Hello-world** (Workplan Phase 1): end-to-end feature tests covering record → deliver → audit → immutability, plus an operator-runnable `php artisan events:demo` command printing the full trail (founder-confirmed 2026-06-12).
- **The first `pgsql` CI lane**: the test suite runs against a PostgreSQL ≥ 17 service container next to the SQLite lane on every push/PR (the DB ADR's parity guardrail, due "in the same F1 change that creates the first domain migration" — this one).
- **Docs**: `docs/event-substrate.md` (emit/consume how-to, delivery semantics, immutability + REVOKE runbook, PII payload rule) + `docs/INDEX.md` row; `docs/development.md` CI section and `docs/module-template.md` §6 updated to reflect the now-existing lane.

## Capabilities

### New Capabilities

- `event-substrate`: the transactional-outbox domain-event substrate and audit store — atomic event recording, the envelope contract, audit records, the per-consumer delivery ledger, inline delivery + scheduled sweep semantics, ordering/idempotency obligations, the immutability mechanism, and the hello-world demonstration.

### Modified Capabilities

- `platform`: the Quality Pipeline requirement gains the `pgsql` CI lane — CI runs the test suite on PostgreSQL (≥ 17) in addition to in-memory SQLite on every push and pull request.

## Impact

- **Code:** `database/migrations/` (first domain migrations: three tables + immutability triggers), `app/Platform/**` (new root: Events + Audit substrate), `routes/console.php` (sweep schedule), `config/` (substrate tunables), `tests/Architecture/ModuleBoundariesTest.php` (platform-list amendment + red-proof), `tests/Feature/Platform/**` + `tests/Unit/Platform/**` (new), `tests/Feature/CiWorkflowTest.php` (pgsql-lane pins), `.github/workflows/ci.yml` (new job).
- **Docs:** `docs/event-substrate.md` (new), `docs/INDEX.md` (+1 row), `docs/development.md` (CI section), `docs/module-template.md` (§6 lane sentence refresh).
- **Dependencies:** none added — recorder/ledger/sweep are owned code per the ADR ("hand-rolled relay/sweep/ledger … consistent with the no-new-heavyweight-deps rule"). `composer.json`/`composer.lock` must show zero churn.
- **Slice boundary — deliberately NOT in this change (declared future homes):**
  - **`queued` delivery mode and the queue driver** → queue ADR gate at the first `queued` consumer (expected F4–F6). Requirements already placed on that ADR: at-least-once + per-job delay.
  - **Object storage** (invoice PDFs, statements) → INV1 gate; this store is entirely DB-resident.
  - **Money value objects, i18n, feature flags, the reusable `actor_role` helper** → `foundations-money-i18n-flags` (F1 3/3). Payload money/FX discipline ships here as the documented envelope contract; typed helpers come next change.
  - **Module domain events, contracts, consumers and entities** (the ~120-event catalogue, Module E's ~30 financial types) → F2+ module changes. This change ships the substrate they will use, not their events.
  - **Operator-panel surface for failed deliveries (manual retry)** → a later OperatorPanel/module change; dead-letter rows are queryable meanwhile.
  - **Module K's GDPR redaction job** → Module K changes; the structural-immutability seam and the documented `redactor` grant ship now.
  - **Delivery-ledger pruning** → deferred until volumes demand it (ADR declares terminal rows prunable; nothing to prune at launch).
  - **`transactional` delivery mode** (Phase 5 ATP option) → documented extension point only; built only if Phase 5 demands a zero-staleness window.
  - **Synchronous guards** (no-oversell L1/L2, KYC/sanctions/Hold) are NOT events and never become events (ADR delivery-classification rule 1; Module A §10.5) — in-transaction checks via read contracts, F2+ module scope.
- **Traceability gaps (deliberate):** of Workplan Phase 1's signoff list, this change covers the event substrate, the audit floor mechanism, the hello-world through CI, and the persistence/migration-discipline execution. Observability and the "frontend platform shell … with authentication" remain with F1 3/3 and the gated ADRs (auth → Module K gate; frontend → Module S gate), as recorded in the skeleton change's proposal.
