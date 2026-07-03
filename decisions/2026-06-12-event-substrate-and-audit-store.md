---
type: decision
status: active
date: 2026-06-12
---

## Decision: Event substrate = transactional outbox on the app DB; the event log IS the 10-year audit/financial event store

Closes **two** gates with one coupled decision: "Domain-event substrate" and "Audit/financial event store". The load-bearing insight: **one transactional write serves both concerns** — the outbox is not an ephemeral technical table next to the domain; it is the decennial event log itself, with delivery state separated into a mutable ledger.

### Storage topology (two platform tables + module ledgers)

- **`domain_events`** — append-only log, written **in the same DB transaction** as the state change it records (atomicity state+event, no dual-write). Simultaneously: the transactional outbox, the inter-module API record, the 10-year audit log for state transitions, and **the financial event store** — Module E's ~30 financial event types are domain events in this same log (what makes them financial is type+emitter; settlement aggregation reads them via JSONB expression indexes, per Module E §4.7 and the PG ADR). Splitting financial events out was rejected: `SupplierPaymentCompleted` is both the R4 API event and a financial event — a separate store creates a dual-home problem.
- **`audit_records`** — operator/system action audit per Architecture §5.3: shared envelope core plus `action`, `before` JSONB, `after` JSONB, `authorization_basis`. Write-only (nobody consumes it). Covers actions with no cross-module event (e.g. a description edit). **The Workplan Phase 1 audit floor ("every state transition + financial event captured, 10-year") = the union of the two tables**: cross-module FSM transitions are domain events (Module B §18 provenance included), operator before/after are audit records. "Per-module audit logs" (§5.3) is read as a logical requirement: one physical table with a `module` column — one immutability mechanism, one retention policy, one admin surface.
- **`event_deliveries`** — mutable ledger per (event × consumer): pending/done/failed, attempts, `available_at` backoff. **R4 resolved structurally**: D and B get independent rows, independent retries; a consumer failure never touches the emitter or sibling consumers. Infrastructure, NOT audit: rows in terminal state are prunable after a grace period (the decennial proof is the event itself plus its effects in module tables).
- **Module-owned process ledgers** — pattern: mutable FSM state referencing immutable events by id, in the owning module's tables. Module E's Xero sync FSM (`pending→syncing→synced/sync_failed`, §7) is E's table; reversal-ordering ("reversal queues until its source is synced") is E consumer logic, never substrate logic. The platform ledger must not know finance-specific transitions (invariant 10).

### Envelope (`domain_events`)

`id` bigint identity PK (monotonic insertion order = intra-transaction causal order, A §12.4) · `event_id` UUIDv7 unique (public identity: external idempotency keys, module-ledger references, future broker export) · `name` string (spec event name **verbatim**) · `schema_version` smallint default 1 · `module` string (emitter) · `occurred_at` timestamptz (app-side, time-travel-testable) · `actor_role` NOT NULL (`newco_ops|producer|customer|system` — invariant 8) · `actor_id` nullable bigint (local user/party PK; does **not** foreclose the identity ADR — entities stay local rows under any IdP) · `entity_type` + `entity_id` strings (primary subject; provenance of a bottle = an envelope query) · `correlation_id` UUID (everything one trigger caused, cross-module) · `causation_id` nullable bigint (causal chain queryable cross-transaction) · `payload` JSONB.

Payload discipline: money = integer minor units + ISO 4217 code; **FX rates = decimal strings, never floats** (D18: refunds settle at the exact locked rate). `audit_records` shares the envelope core (occurred_at, module, actor_role, actor_id, entity ref, correlation_id).

**Versioning:** the monolith deploys producers and consumers atomically — additive payload changes don't bump; breaking changes bump `schema_version` and update consumers in the same deploy. The column exists for the decennial log (every row declares the schema it was born under), not for runtime version coexistence.

### Delivery modes and consumer classification

1. **Guards are not events.** No-oversell L1/L2 and compliance gates (KYC/sanctions/Hold) are in-transaction checks via module read contracts — spec-confirmed: L1 over-issuance is an operation-level rejection emitting no event (Module A §10.5); both layers evaluate at the transactional boundary. The substrate is not in the guard path, and no future change may "eventify" a guard.
2. **`inline` — launch default for all consumers.** Post-commit, same process, per-consumer try/catch; failure → `failed` row + sweep retry. Hard rule: **inline consumers do DB work only, never external I/O.** The B→A ATP push lands at ~ms, inside Module B §22.1's sub-1s floor with wide margin.
3. **`queued` — per-consumer upgrade** once the queue ADR lands (dependency declared below). Design unchanged; only the executor changes.
4. **External I/O (Xero, Airwallex, Logilize) never inside a delivery.** The inline consumer records *intent* (e.g. E's `pending` sync row); a module-owned scheduled processor performs the call with its own retry policy — exactly Module E §7's shape.
5. **Phase 5 ATP pattern stays open** per Build Workplan ("synchronous push vs eventual-consistency-with-reconciliation-gate"): `inline` serves it today; if Phase 5 demands a zero-staleness window, a `transactional` mode (consumer inside the emitting transaction, same-DB projections only, no I/O) is a small documented extension of the ledger. Supported, not built.

In tests (SQLite `:memory:`) all consumers run inline, synchronous, deterministic, regardless of declared mode.

### Delivery semantics

- **At-least-once, with no lost events:** the emitting transaction commits state + event + `pending` delivery rows atomically; inline execution is opportunistic, the scheduled sweep (sub-minute scheduler tick, tunable) is the guarantee — a crash between commit and execution leaves `pending` rows for the sweep. (Naked Laravel `afterCommit` listeners die with the process; the ledger is what makes delivery durable.)
- **Exactly-once for DB effects:** handler and its delivery-status update share one transaction (consumers and ledger live in the same DB — monolith dividend). External effects use `event_id` as the idempotency key toward Xero/Airwallex.
- **Ordering:** causal intra-transaction via monotonic ids processed in order (A §12.4 verbatim); cross-transaction = at-least-once with possible disorder, which the spec declares tolerable — elevated here to a design obligation: **every consumer is idempotent and order-tolerant cross-transaction**; latest-wins consumers guard with a per-entity id watermark (ignore events with `id` < last applied for that entity).
- **No blocking per-consumer FIFO:** a failed delivery retries with exponential backoff but never blocks subsequent events for that consumer (a poison event must not stall all of B's ownership flips). The only hard ordering the spec demands (reversal after its source, E §7) lives in E's FSM as consumer logic. No speculative `sequential` mode; if a real consumer ever proves order-intolerant, the pattern is the per-entity watermark.
- **Dead-letter in place:** max attempts → status `failed`, surfaced in the operator panel (later change) with manual retry. No separate dead-letter system to operate.

### Immutability mechanism (three layers)

1. **DB triggers on both engines** (travel with the schema; full SQLite parity): `domain_events` — `BEFORE UPDATE OR DELETE` → raise, unconditional; `audit_records` — DELETE always forbidden, UPDATE forbidden unless **only** `before`/`after` change (trigger compares OLD/NEW on structural columns).
2. **Production REVOKE** (PG only; documented fallback per the migration policy): app role gets INSERT+SELECT only on both tables; a dedicated `redactor` role gets the column-level `GRANT UPDATE (before, after) ON audit_records`, used solely by Module K's GDPR erasure job.
3. **Additive-only migrations** on these two tables: nullable column adds yes, alter/drop never (migrations run as owner — this layer is discipline + review, declared invariant).

Explicitly NOT claimed: WORM storage or cryptographic tamper-evidence — a provider superuser can drop a trigger; the spec's threat model is application bugs and operator mistakes, not a hostile DBA. **Hash-chaining rejected**: it serializes inserts on a global contention point (each row must read the previous hash) for a property no spec section requires; reopenable on regulatory demand.

### PII / GDPR

- **`domain_events` is PII-free by design → absolute immutability.** Payloads carry ids and business data (amounts, quantities, states, dates), never names/emails/addresses — profile data lives in module tables, where Module K's GDPR erasure operates **in place** (PII overwrite, never DELETE) and never touches the event log. *(Rectification 2026-07-02, RM-09/§7-F3: originally "erasure already works" — the customer erasure **flow**, K J-9/9a (anonymise action, `anonymised_at`, Address entity), is not yet built and lands with RM-01, `docs/validation/Remediation_Tracker.md`. This ADR builds only its **seam**: PII-free events + the `audit_records` `before`/`after` redaction path. **Update (2026-07-03):** RM-01 has since shipped (merged + archived `2026-07-02-parties-anonymisation`) — the erasure *flow* now exists; this ADR still builds only its seam, and the "in place (PII overwrite, never DELETE)" invariant above is now enforced by that flow.)* Financial events are doubly safe: ids+amounts only, and Italian fiscal retention is an Art. 17(3)(b) lawful basis overriding erasure.
- **`audit_records` may contain PII in before/after → structural immutability + redaction.** The GDPR job overwrites PII fields inside the JSONB with redaction markers via the `redactor` role; structure and record skeleton preserved — precisely §5.3's "anonymisation preserves transactional records while removing PII".
- Crypto-shredding (per-party keys, erasure = key destruction) rejected: decennial key management operated by one person is a worse risk than the problem it solves.

### No partitioning at launch; option preserved

The classic motivations evaporate here: append-only ⇒ no dead tuples to vacuum; pruning is forbidden ≥10 years (and the floor is ≥, not ≤); launch volumes (low hundreds of thousands of rows/year) are trivial for unpartitioned PG. Costs avoided: composite `(id, occurred_at)` PK polluting every module-ledger FK, day-1 SQLite divergence, migration complexity. **Revisit trigger:** measurable query degradation or volumes an order of magnitude past estimates; conversion path = copy-and-swap (append-only ⇒ trivially consistent copy). The PG ADR's FK-on-partitioned-tables headroom remains usable.

Launch indexes: PK `id` · unique `event_id` · `(entity_type, entity_id, id)` (B §18 provenance as envelope query) · `(name, id)` · partial `WHERE status='pending'` on `event_deliveries`. GIN on payload only when a real query demands it.

### Gate boundaries declared, not decided

- **Queue driver:** this substrate does NOT trigger the queue gate — scheduler ticks (sweep, module processors) are not an async workflow. The gate fires at the first consumer/processor needing `queued`, expected F4–F6 (Module S checkout cascades or Module E external syncs). Requirements placed on that ADR now: at-least-once + per-job delay (both Redis+Horizon and the database driver qualify). The outbox deliberately lowers that ADR's stakes: the queue is an executor, the ledger is the truth — a lost job re-sweeps; driver choice becomes throughput/ops, not correctness.
- **Object storage:** this store is entirely DB-resident; documents (invoice PDFs, statements) belong to the INV1 gate. No dependency created.

## Context

The CLAUDE.md gate table blocks all of F1 (`foundations-domain-events-audit` in particular) on the two rows this ADR closes; Build_Workplan Phase 1 prescribes exactly this decision ("event substrate (bus/queue, ordering + idempotency, audit storage)") plus a hello-world exercising "DB + event bus + audit trail". The spec is deliberately tech-agnostic (DEC-073: transport, serialisation, broker semantics, ordering, idempotency are dev-team scope) — the binding constraints are contracts:

- **R4 / Architecture §8.1:** `SupplierPaymentCompleted` emitted by E, consumed by D and B **independently**; further real fan-outs (`VoucherIssued` S→{D,B}, `ProductReferenceActivated` 0→{A,S,D,B,C}, `InboundEventPhysicallyAccepted` D→{B,C,S}, B inventory events →{A,E}) demand per-consumer independent retries.
- **Module A §12.4 (verbatim):** "cascading events within a single business transaction are emitted in causal order; consumers tolerate eventual-consistency arrival order across transactions."
- **Module A §7.1/§10.5 + Module B:** both no-oversell layers are synchronous in-transaction checks; L1 over-issuance emits no event — the guard path is not event-mediated.
- **Module B §22.1:** ATP push sub-1s end-to-end; hold ≤200ms p99; storefront staleness ≤5s. Workplan Phase 5 keeps the push pattern choice open — the substrate must support both.
- **Module E §7:** per-event sync FSM toward Xero, reversal-ordering, ~30 financial event types, post-sync immutability FLOOR, corrections via credit notes only, 10-year retention.
- **Architecture §5.3:** audit record = actor, action, timestamp, before/after, authorization basis; `actor_role` on every operator action; GDPR anonymisation preserving transactional records (Module K: PII overwrite, never DELETE).
- **Prior ADRs:** the monolith ADR fixed in-process dispatch at launch with events append-logged (substrate left open); the PG ADR fixed PostgreSQL ≥17, zero extensions, JSONB + expression indexes for queryable financial payloads, and explicitly preserved FK-on-partitioned-tables headroom for this store.

Key finding of the grill (2026-06-12, 8 questions, all confirmed by the founder): **R4 alone falsifies naked synchronous dispatch** — listeners inside the emitter's transaction couple consumers (B's failure would roll back E's payment record). "In-process at launch" must therefore be realized as a delivery mechanism decoupled from the emitting transaction; the things that must be synchronous are not events at all.

## Alternatives considered

- **Naked synchronous dispatch** (Laravel listeners in the emitting transaction + log row in-transaction): zero infrastructure, perfect parity, causal ordering free — but R4-impossible (coupled consumers), Module E's FSM homeless, and Xero HTTP inside a DB transaction means holding locks during external I/O. Rejected by the spec, not by taste; survives only as the test-mode execution shape.
- **Broker** (Redis Streams / RabbitMQ / SQS): native fan-out and retries, scales past the monolith — but contradicts in-process-at-launch (monolith ADR, not reopened); the dual-write problem forces an outbox *anyway* (broker is additive, not substitutive); new solo-operated infrastructure; no `:memory:` parity; a decennial audit cannot live in a broker, so the DB store would exist regardless. Certain cost, null benefit at launch volumes. Extraction seam documented: the log can feed a broker later without touching emitters.
- **Separate financial-event store** next to the domain-event log: rejected — dual-home problem on R4 (see Decision); one immutable log + module-owned process ledgers satisfies "Module E retains the financial-event layer".
- **Hash-chaining** for tamper-evidence and **crypto-shredding** for GDPR: rejected (see Decision sections) — each buys a property the spec doesn't ask for at an operational cost the solo founder would pay daily.
- **Time-partitioning from day 1:** rejected (see Decision section); option explicitly preserved.

## Reasoning

1. The only shape that satisfies simultaneously R4 (per-consumer ledger), Module E §7 (immutable payload / mutable process state separation), Module A §12.4 (monotonic ids encode causal order), and SQLite `:memory:` parity — with zero new infrastructure.
2. One transactional write eliminates dual-write: state + event + deliveries commit atomically; the sweep guarantees no event loss across crashes.
3. Same-DB consumers upgrade at-least-once to exactly-once-for-DB-effects for free — the monolith dividend made concrete.
4. The two prior ADRs converge here: "in-process, append-logged" (monolith) and JSONB/partial-index/FK-headroom (PostgreSQL) were bought for exactly this design.
5. Solo-founder operability: at launch zero processes beyond the existing scheduler; dead-letter is a status, not a system; the queue ADR is deliberately de-risked to a throughput/ops choice.

## Trade-offs accepted

- **Hand-rolled relay/sweep/ledger** (~few hundred lines) instead of a packaged solution — owned code, consistent with the no-new-heavyweight-deps rule; cost is ours to maintain.
- **Inline consumers add latency to emitting requests** at launch — accepted at launch volumes; the scale lever is flipping a consumer to `queued`, not a redesign.
- **Polling sweep = constant small DB load** — negligible at these volumes.
- **Immutability is not cryptographic** — the threat model consciously excludes a hostile DBA; compensating layers are provider access control and (future gates) backup/retention policy.
- **PII-free events cost a per-event review line** — buys absolute immutability exactly where evidential value is highest.
- **Unpartitioned store bets on low volumes** — mitigated by the declared revisit trigger and a documented conversion path.

## References

`spec/02-prd/Architecture_v0.3-MVP.md` §0.5, §1.2, §5.3, §8.1 · `spec/05-release/Build_Workplan_v0.3-MVP.md` Phase 1 (substrate + hello-world), Phase 5 (ATP push pattern open) · `spec/02-prd/Module_A_PRD_v0.3-MVP.md` §7.1, §10.5, §11.5.1, §12.4 · `spec/02-prd/Module_B_PRD_v0.3-MVP.md` §10.2, §18 (BR-B-Provenance), §22.1 · `spec/02-prd/Module_E_PRD_v0.3-MVP.md` §4.7, §7 · `spec/02-prd/Module_K_PRD_v0.3-MVP.md` §8.2, §12 (GDPR overwrite) · spec R1–R4 (`spec/README.md`) · [[2026-06-11-modular-monolith-architecture]] · [[2026-06-12-production-db-engine]]
