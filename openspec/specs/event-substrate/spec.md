# event-substrate Specification

## Purpose
TBD - created by archiving change foundations-domain-events-audit. Update Purpose after archive.
## Requirements
### Requirement: Transactional Event Recording

The platform SHALL provide a domain-event recorder that appends events to the `domain_events` table inside the caller's already-open database transaction, so that the state change and the events recorded with it commit or roll back atomically (no dual-write). The recorder SHALL refuse to record when no database transaction is active. Recording SHALL create, in that same transaction, one `pending` row in `event_deliveries` for every consumer registered for that event's name. The `domain_events` log SHALL serve simultaneously as the transactional outbox, the inter-module API record, the 10-year audit log for state transitions, and the financial event store — no separate financial-event table SHALL exist (Module E's financial event types are domain events in this same log).

_Source: decisions/2026-06-12-event-substrate-and-audit-store.md (Storage topology; Delivery semantics — "the emitting transaction commits state + event + `pending` delivery rows atomically") · spec/02-prd/Architecture_v0.3-MVP.md § 1.2 (all cross-module communication flows through versioned domain events) · § 5.3 (10-year retention; Module E retains the financial-event layer) · spec/02-prd/Module_E_PRD_v0.3-MVP.md § 4.7 (the engine reads the same recorded events) · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (event substrate + audit storage) · CLAUDE.md invariant 4._

#### Scenario: State, event and deliveries commit atomically

- **WHEN** a database transaction performs a state change, records a domain event, and commits
- **THEN** the state change, the `domain_events` row, and one `pending` `event_deliveries` row per registered consumer are all persisted

#### Scenario: Rollback discards state, event and deliveries together

- **WHEN** a database transaction records a domain event and then rolls back
- **THEN** no `domain_events` row and no `event_deliveries` row exist for it, and the state change is discarded

#### Scenario: Recording outside a transaction is refused

- **WHEN** the recorder is invoked while no database transaction is active
- **THEN** it throws and writes nothing (the no-dual-write guarantee is not silently forfeited)

### Requirement: Domain Event Envelope

Every recorded domain event SHALL persist the full envelope: `id` (monotonic bigint primary key — insertion order encodes intra-transaction causal order), `event_id` (UUIDv7, unique — the public identity for idempotency keys and references), `name` (the spec event name, verbatim), `schema_version` (small integer, default 1), `module` (the emitting module), `occurred_at` (timezone-aware timestamp, application-set), `actor_role` (NOT NULL, one of `newco_ops` | `producer` | `customer` | `system`), `actor_id` (nullable), `entity_type` + `entity_id` (the primary subject), `correlation_id` (UUID, NOT NULL — for a root event it defaults to the event's own `event_id`), `causation_id` (nullable reference to the causing event's `id`), and `payload` (JSON). Payloads SHALL record monetary amounts as integer minor units with an ISO 4217 currency code and FX rates as decimal strings (never floats), and SHALL carry entity ids and business data only — never PII (names, emails, addresses; profile data lives in module tables where GDPR erasure operates). The event history of one entity SHALL be retrievable as an envelope query (`entity_type` + `entity_id`, ordered by `id`).

_Source: decisions/2026-06-12-event-substrate-and-audit-store.md § Envelope + § PII/GDPR · spec/02-prd/Architecture_v0.3-MVP.md § 5.3 (the `actor_role` audit envelope on every operator action) · spec/02-prd/Module_E_PRD_v0.3-MVP.md § 7.2 (dual-record payload: amount, currency, eur_equivalent_amount, fx_rate as locked rate — refunds settle at the original captured rate, D18) · spec/02-prd/Module_A_PRD_v0.3-MVP.md § 12.4 (causal order within a transaction) · spec/02-prd/Module_B_PRD_v0.3-MVP.md § 18 (per-bottle provenance, append-only) · spec/02-prd/Module_K_PRD_v0.3-MVP.md § 8.2 (PII erasure operates on module tables, never the log) · CLAUDE.md invariants 5, 6, 8._

#### Scenario: Envelope persisted and read back complete

- **WHEN** an event is recorded with name, module, actor, entity reference, correlation and payload
- **THEN** reading it back yields every envelope field, with a unique UUIDv7 `event_id` and `schema_version` 1 by default

#### Scenario: actor_role is mandatory at the database layer

- **WHEN** a `domain_events` insert is attempted without an `actor_role`
- **THEN** the database rejects it

#### Scenario: FX rates survive as exact decimal strings

- **WHEN** an event is recorded whose payload carries an FX rate as the decimal string (e.g. `"1.0842"`)
- **THEN** the payload read back returns that exact string, not a float

#### Scenario: Intra-transaction ids are monotonic in emission order

- **WHEN** several events are recorded within one transaction
- **THEN** their `id` values strictly increase in the order they were recorded

#### Scenario: Provenance is an envelope query

- **WHEN** multiple events are recorded for the same `entity_type` + `entity_id` across transactions
- **THEN** querying the log by that entity returns exactly those events in `id` order

### Requirement: Audit Records

The platform SHALL provide an audit recorder that appends operator/system action records to `audit_records`, carrying the shared envelope core (`occurred_at`, `module`, `actor_role` NOT NULL, `actor_id`, `entity_type`/`entity_id`, `correlation_id`) plus `action`, `before` (JSON, nullable), `after` (JSON, nullable) and `authorization_basis`. Audit records SHALL be write-only with respect to the substrate: recording one creates no delivery rows and no consumer machinery reads them. The Workplan Phase 1 audit floor — every state transition + financial event captured, 10-year retention — SHALL be satisfied by the union of the two tables: cross-module state transitions and financial events as domain events, operator before/after actions as audit records ("per-module audit logs" realized as one physical table with a `module` column — one immutability mechanism, one retention policy).

_Source: spec/02-prd/Architecture_v0.3-MVP.md § 5.3 (immutable audit record: actor, action, timestamp, before/after, authorisation basis; per-module audit logs; 10-year retention) · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (audit floor: "every state transition + financial event captured for 10-year retention") · decisions/2026-06-12-event-substrate-and-audit-store.md (Storage topology — audit_records; the floor = the union of the two tables) · CLAUDE.md invariant 8._

#### Scenario: Operator action recorded with before and after state

- **WHEN** an operator action is recorded with `action`, `before`, `after` and `authorization_basis`
- **THEN** reading it back yields all fields including the envelope core with its `actor_role`

#### Scenario: Audit records create no deliveries

- **WHEN** an audit record is written
- **THEN** no `event_deliveries` row is created for it

### Requirement: Per-Consumer Delivery Ledger

Delivery state SHALL live exclusively in `event_deliveries` — one row per (event × registered consumer), with `status` (`pending` | `done` | `failed`), an `attempts` counter and an `available_at` backoff timestamp; one-row-per-pair SHALL be enforced by a uniqueness constraint. Consumers SHALL be independent: a consumer handler's failure SHALL NOT affect the emitter's committed transaction nor any sibling consumer's delivery row — the spec's R4 (`SupplierPaymentCompleted` emitted by Module E, consumed by Modules D and B independently) mechanized for every fan-out. Delivery rows are infrastructure, not audit: terminal-status rows MAY be pruned after a grace period (pruning tooling deferred; the decennial proof is the event itself).

_Source: decisions/2026-06-12-event-substrate-and-audit-store.md (Storage topology — event_deliveries; "R4 resolved structurally"; Context — R4 alone falsifies naked synchronous dispatch) · spec/02-prd/Architecture_v0.3-MVP.md § 8.1 (the R4 contract row: E emits, D and B consume) · spec/README.md "The single most important thing" #1 (SupplierPaymentCompleted is emitted by Module E, consumed by D and B)._

#### Scenario: Fan-out failure isolation (R4 mechanized)

- **WHEN** one event has two registered consumers and the first consumer's handler throws during delivery
- **THEN** the first consumer's row is marked for retry (failure recorded, backoff set), the second consumer's row completes `done`, and the emitter's committed data is untouched

#### Scenario: Retries are per-consumer

- **WHEN** the sweep later retries the failed delivery and the handler succeeds
- **THEN** only that consumer's row transitions to `done`; the sibling row is not re-executed

### Requirement: Inline Delivery and Scheduled Sweep

The launch delivery mode SHALL be `inline`: after the recording transaction commits, that transaction's pending deliveries execute in the same process, each consumer isolated by try/catch. A handler invocation and its delivery-status update SHALL share one database transaction (exactly-once for DB effects), and a delivery already `done` SHALL never re-execute. Inline consumers SHALL perform database work only — never external I/O (external calls live in module-owned scheduled processors reading intent rows that consumers record, per Module E § 7's shape). A scheduled sweep (sub-minute tick, tunable) SHALL be the at-least-once guarantee: it re-executes due deliveries — `pending` rows whose inline execution never ran (crash between commit and execution) and retryable failures whose exponential backoff has elapsed — up to a configurable maximum attempts, after which the row's status becomes `failed` and stays (dead-letter in place; the operator retry surface is a later change). Registering a consumer in `queued` mode SHALL be rejected until the queue-driver ADR lands (gate expected F4–F6).

_Source: decisions/2026-06-12-event-substrate-and-audit-store.md (Delivery modes and consumer classification 2–4; Delivery semantics — at-least-once with no lost events, exactly-once for DB effects, dead-letter in place; Gate boundaries — queued is the queue ADR's) · spec/02-prd/Module_E_PRD_v0.3-MVP.md § 7.1–7.2 (per-event sync FSM as the intent-row pattern; external sync is a module-owned processor) · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 ("event substrate (bus/queue, ordering + idempotency, audit storage)") · spec/02-prd/Module_B_PRD_v0.3-MVP.md § 22.1 (latency context the inline mode serves: ATP push sub-1s)._

#### Scenario: Inline delivery happy path

- **WHEN** a transaction that recorded an event for one registered consumer commits
- **THEN** the consumer's handler runs post-commit and its delivery row reads `done` with `attempts` 1

#### Scenario: Crash recovery — the sweep delivers what inline never ran

- **WHEN** an event's transaction committed but its inline execution never ran (simulated crash), and the sweep then runs
- **THEN** the pending delivery executes and completes `done` — no event is lost

#### Scenario: Exponential backoff then dead-letter

- **WHEN** a consumer's handler fails on every attempt across repeated sweeps
- **THEN** `attempts` increments with a growing `available_at` on each retry until the configured maximum, after which the row is `failed` and subsequent sweeps no longer execute it

#### Scenario: Done is terminal

- **WHEN** the sweep runs over a delivery already marked `done`
- **THEN** the handler is not invoked again

#### Scenario: Queued mode is gated

- **WHEN** a consumer registration declares `queued` mode
- **THEN** registration is rejected with an error referencing the open queue-driver ADR gate

### Requirement: Ordering and Consumer Obligations

Events recorded within one transaction SHALL be delivered to each consumer in `id` order — Module A § 12.4 verbatim: "cascading events within a single business transaction are emitted in causal order; consumers tolerate eventual-consistency arrival order across transactions". Across transactions, delivery is at-least-once and MAY arrive out of order; every consumer SHALL therefore be idempotent and order-tolerant (the documented pattern for latest-wins consumers is a per-entity id watermark: ignore events whose `id` is below the last applied for that entity). A failed delivery SHALL NOT block later events for the same consumer — there is no per-consumer FIFO; a poison event never stalls the consumer's stream.

_Source: spec/02-prd/Module_A_PRD_v0.3-MVP.md § 12.4 (the ordering statement, verbatim) · decisions/2026-06-12-event-substrate-and-audit-store.md (Delivery semantics — Ordering; No blocking per-consumer FIFO; the watermark pattern) · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (ordering + idempotency)._

#### Scenario: Causal order within a transaction

- **WHEN** three events are recorded in one transaction for the same registered consumer
- **THEN** the consumer receives them in their recorded `id` order

#### Scenario: A poison event does not stall the stream

- **WHEN** an earlier event's delivery is in failure/backoff for a consumer and a later event arrives for that same consumer
- **THEN** the later event is delivered (`done`) while the earlier row remains in its retry/failed state

### Requirement: Immutability Enforcement

`domain_events` SHALL reject every UPDATE and DELETE. `audit_records` SHALL reject every DELETE, and SHALL reject any UPDATE that changes anything other than the `before`/`after` columns — the GDPR redaction seam: redaction overwrites PII values inside `before`/`after` while the structure and record skeleton are preserved (anonymisation preserves transactional records while removing PII). Both SHALL be enforced by database triggers present with full parity on BOTH engines (PostgreSQL and SQLite), created by the same migrations that create the tables. Migrations touching these two tables SHALL be additive-only (adding nullable columns is allowed; altering or dropping existing columns never). The production REVOKE layer — application role granted INSERT+SELECT only on both tables; a dedicated `redactor` role holding column-level UPDATE on `audit_records.before`/`after`, used solely by Module K's GDPR erasure job (a later change) — SHALL be documented as a runbook, applied when a production database exists (hosting gate). Hash-chaining and partitioning are explicitly NOT part of the launch mechanism.

_Source: decisions/2026-06-12-event-substrate-and-audit-store.md (Immutability mechanism — three layers; PII/GDPR; No partitioning at launch) · CLAUDE.md invariant 4 (financial immutability; corrections only via credit notes) · spec/02-prd/Architecture_v0.3-MVP.md § 5.3 (GDPR anonymisation preserves transactional records while removing PII; 10-year retention) · spec/02-prd/Module_E_PRD_v0.3-MVP.md § 7.6 (post-sync immutability floor; corrections via credit notes only) · spec/02-prd/Module_K_PRD_v0.3-MVP.md § 8.2 + § 12 (PII overwrite in place on surviving rows, never DELETE)._

#### Scenario: domain_events UPDATE is rejected

- **WHEN** an UPDATE is attempted against any `domain_events` row, on either engine
- **THEN** the database raises an error and the row is unchanged

#### Scenario: domain_events DELETE is rejected

- **WHEN** a DELETE is attempted against any `domain_events` row, on either engine
- **THEN** the database raises an error and the row remains

#### Scenario: audit_records structural UPDATE is rejected

- **WHEN** an UPDATE against an `audit_records` row changes a structural column (e.g. `action`, `module`, `actor_role`)
- **THEN** the database raises an error and the row is unchanged

#### Scenario: audit_records redaction UPDATE is allowed

- **WHEN** an UPDATE against an `audit_records` row changes only the `before` and/or `after` columns
- **THEN** it succeeds — the GDPR redaction path stays open while everything else stays frozen

#### Scenario: audit_records DELETE is rejected

- **WHEN** a DELETE is attempted against any `audit_records` row
- **THEN** the database raises an error and the row remains

### Requirement: Hello-World Demonstration

The repository SHALL contain an end-to-end demonstration of "DB + event bus + audit trail": (a) feature tests exercising the full pipeline in one scenario — a state change, a domain event and an audit record committed in one transaction; inline delivery executing a registered consumer whose effect is itself database work; the immutability red-paths — and (b) an operator-runnable `php artisan events:demo` command that records a synthetic demo event + audit record inside one transaction, triggers delivery, probes immutability (an UPDATE and a DELETE both rejected) and prints the resulting trail, exiting 0. Demo identifiers SHALL be clearly synthetic — verbatim spec event names are reserved for real module events (F2+).

_Source: spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (deploy a "hello world" service exercising DB + event bus + audit trail + pipeline) · decisions/2026-06-12-event-substrate-and-audit-store.md (Context — Phase 1 prescribes the hello-world) · demo-command shape founder-confirmed 2026-06-12 (recorded in design.md D9)._

#### Scenario: End-to-end pipeline test

- **WHEN** the feature suite runs
- **THEN** one test exercises, in a single scenario: atomic record (state + event + audit), post-commit inline delivery with a consumer DB effect, and delivery-ledger completion

#### Scenario: Demo command runs the full trail

- **WHEN** `php artisan events:demo` runs against a migrated database
- **THEN** it exits 0 and its output shows the recorded event (with `event_id` and `actor_role`), the audit record, the delivery completing, and the immutability probes being rejected

### Requirement: Actor Context Resolution

The platform SHALL provide a reusable actor-context resolver that supplies the `actor_role` (and an optional `actor_id`) for the current execution context, so domain-event and audit emitters obtain the acting principal from one canonical seam rather than hardcoding a role at each call site. Until the identity/auth ADR is decided (the open gate that precedes Module K), the resolver SHALL default to `actor_role = system` with a null `actor_id` for console, queue and unauthenticated contexts, and SHALL support an explicit scoped run-as override that applies a given role (and optional actor id) for the duration of a callable and restores the prior context afterward. The resolver SHALL NOT read authentication state — mapping an authenticated operator, producer or customer to their `actor_role` is the identity/auth ADR's responsibility (Module K), so this seam does not step through that gate. The four-value `ActorRole` set (`newco_ops` | `producer` | `customer` | `system`) and the NOT-NULL `actor_role` envelope column are unchanged (they already exist from the domain-events-audit change); this requirement adds only the canonical way to populate them.

_Source: spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (MVP carries (iii): "the `actor_role` audit envelope — every operator action carries actor_role + identity + timestamp + action + entity reference — the Admin-Panel arm of the audit floor — is a Phase-1 audit-pattern concern") · spec/02-prd/Architecture_v0.3-MVP.md § 2.3 (the `actor_role` audit envelope) + § 5.3 (the `actor_role` audit envelope recorded on every operator action) · decisions/2026-06-12-event-substrate-and-audit-store.md (`actor_role` NOT NULL — invariant 8) · openspec/specs/event-substrate/spec.md Requirement: Domain Event Envelope (`actor_role` NOT NULL, one of `newco_ops` | `producer` | `customer` | `system`) · CLAUDE.md "Open stack decisions" (identity/auth — decide before Module K) + invariant 8 · spec/04-decisions/decisions.md DEC-083 + DEC-115 (`actor_role: producer | newco_ops` on parity writes — the future wiring this seam will serve)._

#### Scenario: Default context resolves to System with a null actor

- **WHEN** the resolver is queried in a console, queue or unauthenticated context with no override
- **THEN** it returns `actor_role = system` and a null `actor_id`

#### Scenario: A scoped run-as override applies and then restores

- **WHEN** a callable is run under a run-as override of `newco_ops` with actor id `42`
- **THEN** the resolver returns `newco_ops` / `42` for the duration of that callable, and reverts to the prior context (default `system`) afterward

#### Scenario: The resolver ignores authentication (gate-safe)

- **WHEN** an authenticated session exists and the resolver is queried with no explicit override
- **THEN** it still returns `system` — it reads no auth state, deferring operator/producer/customer wiring to the identity/auth ADR (Module K gate)

