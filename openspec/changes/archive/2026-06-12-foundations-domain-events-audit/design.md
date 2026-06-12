# Design — foundations-domain-events-audit

## Context

Repo state at authoring (2026-06-12): `foundations-modules-skeleton` (F1 1/3) merged + archived — nine module roots with registered providers, the `App\Modules\Module` registry, the always-on `tests/Architecture/` suite (conformance · boundary law both directions · persistence convention), `docs/module-template.md`. Suite 60/60 (317 assertions), PHPStan level max 0 errors, CI single `quality` job green on in-memory SQLite. Zero domain migrations exist; the only migrations are the Laravel framework baseline (`users`, `cache`, `jobs`).

This change implements ADR `decisions/2026-06-12-event-substrate-and-audit-store.md` **as decided** — transactional outbox on the app DB; the append-only log IS the 10-year audit/financial event store; per-consumer delivery ledger; inline launch delivery + scheduled sweep; three-layer immutability; no partitioning, no hash-chaining. Where this document goes beyond the ADR it is realization detail, not re-decision. The DB ADR (`decisions/2026-06-12-production-db-engine.md`) binds the migration style ("Postgres-truthful, SQLite-compatible") and requires the `pgsql` CI lane in this same change.

Two realization choices were founder-confirmed in the authoring interview (2026-06-12): the `App\Platform` namespace root (D1) and the hello-world shape — E2E tests + `php artisan events:demo` (D9).

## Goals / Non-Goals

**Goals:**
- The three platform tables exist with the ADR envelope, launch indexes, and trigger-enforced immutability with full SQLite/PostgreSQL parity.
- Modules (F2+) get the complete emit/consume machinery: in-transaction recorder, audit recorder, consumer registry on the provider seam, inline post-commit execution, sweep as the at-least-once guarantee.
- The Workplan Phase 1 hello-world exists and is CI-proven on both engines (first `pgsql` lane).
- Everything an F2+ change needs to know is in `docs/event-substrate.md`.

**Non-Goals:**
- `queued` mode and the queue driver (gate F4–F6; registration in `queued` mode is rejected with a gate-referencing error).
- Object storage (INV1 gate) — this store is entirely DB-resident.
- Money/i18n/feature-flag helpers and the reusable `actor_role` helper (→ `foundations-money-i18n-flags`); payload discipline ships as documented contract + tests, typed value objects come next change.
- Any real module event, consumer, contract or entity (→ F2+); Module K's GDPR redaction job (the seam ships, the job doesn't); the operator surface for failed deliveries; delivery-ledger pruning tooling; the `transactional` delivery mode (documented extension point only).
- Applying the REVOKE grants (no production database exists; runbook only, hosting gate applies it).
- Reopening anything the substrate ADR or the DB ADR decided.

## Decisions

### D1 — Platform root `App\Platform` (founder-confirmed 2026-06-12) + boundary-law amendment

All substrate code lives under a single new platform root:

```
app/Platform/
├── Events/
│   ├── DomainEvent.php             # Eloquent model → domain_events
│   ├── EventDelivery.php           # Eloquent model → event_deliveries
│   ├── ActorRole.php               # enum: newco_ops | producer | customer | system
│   ├── DeliveryStatus.php          # enum: pending | done | failed
│   ├── DeliveryMode.php            # enum: inline (queued arrives with the queue ADR)
│   ├── DomainEventRecorder.php     # the in-transaction append API
│   ├── Contracts/
│   │   └── DomainEventConsumer.php # the consumer contract modules implement (F2+)
│   ├── ConsumerRegistry.php        # event name → registered consumers
│   ├── InlineDeliveryExecutor.php  # post-commit + sweep execution engine
│   └── (sweep + demo console commands)
└── Audit/
    ├── AuditRecord.php             # Eloquent model → audit_records
    └── AuditRecorder.php           # the append API
```

Rationale: cohesive (the whole substrate in one place), ONE new entry in the boundary law's platform list, the natural home for F1 3/3 (`App\Platform\Money`, …), and no semantic collision with Laravel's `App\Events` convention (= event classes dispatched via the framework bus — which this substrate deliberately is not; delivery flows through the ledger, never `Event::dispatch`). Rejected: Laravel-flat (`App\Models` + `App\Events` + `App\Console\Commands`) — splits the substrate across three roots and `App\Events` would mislead future contributors about dispatch semantics.

**Amendment protocol (module-template § 3, skeleton design D7) executed here:** `tests/Architecture/ModuleBoundariesTest.php`'s `$platformNamespaces` array gains `'App\Platform'` — justification: the substrate is platform code (modules depend on it; it must never depend on modules), so the platform-never-imports-modules direction must cover it. Mandatory red-proof: a temporary class under `app/Platform/` type-referencing a module symbol → suite RED → remove → GREEN, both outputs recorded in `progress.md`. Console commands live with their concern under `App\Platform\Events\` and are registered explicitly (`withCommands()` in `bootstrap/app.php` or provider registration — verify the Laravel 13 idiom in vendor; auto-discovery only scans `app/Console/Commands`).

The boundary law itself does not change: modules MAY depend on platform code (existing rule), so F2+ modules call `DomainEventRecorder`/`AuditRecorder` and implement `DomainEventConsumer` with no further amendment. The persistence-convention arch test is untouched: it scans `App\Modules\**` only, and these three tables are platform tables, **unprefixed by design** (module-template § 6 names them explicitly).

### D2 — Schema realization (Postgres-truthful, SQLite-compatible)

- **`id`**: `$table->id()` — bigint auto-increment PK on both engines (PG bigserial/identity per Laravel's grammar). The contract is *monotonic insertion order*, which sequence-backed ids give on both engines; do not hand-roll identity DDL.
- **`event_id`**: `uuid` column, unique index, generated **application-side as UUIDv7** by the recorder (verify the installed helper in vendor before use — `Str::uuid7()` or equivalent; never write it from memory).
- **`occurred_at`**: `timestampTz` (PG `timestamptz`; SQLite stores TEXT — acceptable, documented fallback), **application-set in UTC** (time-travel-testable per the ADR).
- **`payload` / `before` / `after`**: `jsonb()` (PG JSONB; SQLite falls back to a JSON/TEXT column — documented in the migration per the policy; behavior under Eloquent `array`/`AsArrayObject` casts is identical).
- **`actor_role`**: string column, NOT NULL. Value-set enforcement: **PHP-enum cast (`ActorRole`) on both engines + a DB CHECK constraint where expressible**. SQLite cannot `ALTER TABLE … ADD CHECK` after create and Laravel's Blueprint has no portable check API — so the CHECK is added on PostgreSQL via a driver-branched raw statement, and the SQLite fallback (enum cast + NOT NULL only) is documented inline in the migration. This is exactly the policy's documented-fallback path; PG remains the truth.
- **`module`**: string, NOT NULL (the emitter). Module emitters persist `Module->value` (the registry anchor, skeleton D2); platform-emitted records (the demo) persist `'platform'`. A DB enum is deliberately avoided (additive evolution; SQLite parity).
- **`correlation_id`**: `uuid` NOT NULL. **`causation_id`**: nullable `unsignedBigInteger` + FK → `domain_events.id` (self-referencing FK on an append-only table is safe; gives integrity to the causal chain).
- **`entity_type` / `entity_id`**: strings, NOT NULL (the ADR envelope declares the primary subject; `entity_id` is a string to span bigint PKs and natural keys).
- **`event_deliveries`**: `domain_event_id` FK → `domain_events.id` · `consumer` string · `status` string NOT NULL default `pending` (enum cast `DeliveryStatus`) · `attempts` unsignedSmallInteger default 0 · `available_at` timestampTz nullable · `last_error` text nullable (operator diagnosis; the ADR's panel surface will read it) · timestamps. Unique `(domain_event_id, consumer)`.
- **Launch indexes (ADR, verbatim):** `domain_events`: PK `id`, unique `event_id`, `(entity_type, entity_id, id)`, `(name, id)`. `event_deliveries`: **partial index `WHERE status = 'pending'`** — partial indexes exist on both engines; if Blueprint's fluent API can't express the predicate in the installed version, use a driver-shared raw `CREATE INDEX … WHERE` (same SQL works on both). `audit_records`: `(entity_type, entity_id, id)` for the entity-history read; nothing more until a real query demands it. **No GIN on payload** (ADR: only when a real query demands it).
- **`audit_records` columns**: envelope core (`occurred_at`, `module`, `actor_role`, `actor_id`, `entity_type`, `entity_id`, `correlation_id`) + `action` string NOT NULL + `before`/`after` jsonb nullable + `authorization_basis` string NOT NULL. No `name`/`schema_version`/`causation_id`/`event_id` — those are event-log concerns (ADR: audit shares the envelope *core*).

### D3 — Recorder semantics

`DomainEventRecorder::record(...)` is the single write path for `domain_events`:

- **Transaction guard**: throws (dedicated exception) when `DB::transactionLevel() === 0` — the no-dual-write rule is enforced, not advised. In tests running inside `RefreshDatabase`'s wrapper transaction the guard is satisfied trivially; the atomicity tests use explicit `DB::transaction()` blocks.
- **Envelope assembly**: generates `event_id` (UUIDv7) and `occurred_at` (UTC now); `correlation_id` defaults to the event's own `event_id` when the caller passes none (root event); `causation_id` is the caller-passed `id` of the causing event, nullable; `schema_version` defaults to 1.
- **Signature shape** (realization, loop may refine names): `record(string $name, Module|string $module, ActorRole $actorRole, ?int $actorId, string $entityType, string $entityId, array $payload, ?string $correlationId = null, ?int $causationId = null): DomainEvent`. `Module|string` keeps the registry as the typed anchor for module emitters while letting the platform demo emit as `'platform'`.
- **Payload discipline is the caller's contract** (documented + tested, not coerced): money as integer minor units + ISO 4217 code; FX rates as decimal **strings**. The substrate must not silently cast — a float in an FX field is a caller bug the F1 3/3 value objects will make unrepresentable. Tests pin that a decimal-string survives the JSON round-trip exactly.
- **Delivery fan-out**: inside the same transaction, inserts one `pending` `event_deliveries` row per consumer registered for `$name`, then registers ONE post-commit hook per transaction (`DB::afterCommit`) handing the recorded event ids to the `InlineDeliveryExecutor`.

`AuditRecorder::record(...)` mirrors the envelope-core assembly for `audit_records` (plus `action`, `before`, `after`, `authorization_basis`); same transaction guard, no fan-out. (The ADR's audit floor assumes audit writes ride the acting transaction.)

### D4 — Consumers: contract, registry, identity, mode

- **Contract**: `interface DomainEventConsumer { public function handle(DomainEvent $event): void; }` under `App\Platform\Events\Contracts\` — the one interface F2+ module listeners implement. Handlers receive the persisted envelope (id, name, payload, …), not a transient object.
- **Registration**: `ConsumerRegistry::register(string $eventName, string $consumerClass, DeliveryMode $mode = DeliveryMode::Inline)` — called from module service providers' `boot()` (the wiring seam the skeleton change reserved for exactly this). The registry is a plain singleton; consumers are container-resolved at delivery time.
- **Identity in the ledger = the consumer class FQCN.** Simple, collision-free, requires no extra API. Trade-off accepted: renaming a consumer class orphans its *non-terminal* ledger rows — a rename must migrate `event_deliveries.consumer` in the same change (documented in `docs/event-substrate.md`; rare and reviewable). Rejected: a declared logical name (`public function name()`) — more API surface for a problem we don't yet have.
- **Mode**: `DeliveryMode` enum with the single case `Inline`. Registering anything else (when the enum grows) throws until the queue ADR lands; the ADR's classification — inline consumers do DB work only, never external I/O — is documented on the contract and in the docs. External calls follow Module E § 7's shape: the inline consumer records *intent* in module tables; a module-owned scheduled processor performs the call (F2+ scope).
- In the test environment all consumers run inline and synchronously regardless of declared mode (ADR) — trivially true at launch since `Inline` is the only mode.

### D5 — Inline execution (post-commit)

`InlineDeliveryExecutor::deliver(array $domainEventIds)`:

1. Loads the `pending` deliveries for those event ids, ordered by `domain_event_id` (= recorded/causal order, A § 12.4).
2. Per delivery: open a DB transaction; resolve the consumer; `handle($event)`; mark the row `done` (attempts+1); commit. **Handler effects and the status flip share that one transaction — exactly-once for DB effects.**
3. On handler throw: catch, roll back the handler transaction, then in a separate write mark the row's failure — attempts+1, `available_at = now + backoff(attempts)`, `last_error` truncated; status stays `pending` while `attempts < max`, becomes `failed` at max. The catch isolates consumers: the loop continues with the next delivery (R4 independence).
4. Never re-executes rows not in a retryable state (a `done` row is terminal by query construction).

The hook is `DB::afterCommit(...)` registered once per transaction by the recorder. **Testing landmine (verify in vendor before relying):** under `RefreshDatabase` Laravel runs tests inside a wrapper transaction and executes `afterCommit` callbacks via the testing `DatabaseTransactionsManager` when the *inner* transaction commits — confirm the exact behavior in the installed framework (`Illuminate\Foundation\Testing\DatabaseTransactionsManager`) and write the inline-delivery tests against real `DB::transaction()` blocks. If the installed behavior differs, fall back to invoking the executor explicitly in those tests and cover the hook itself in one targeted test without the wrapper (e.g. `DatabaseTruncation`-style) — the at-least-once property never depends on the hook anyway (the sweep is the guarantee).

### D6 — Sweep: command, schedule, tunables

- `php artisan events:sweep` — selects due deliveries (`status = 'pending'` AND (`available_at` IS NULL OR `available_at <= now`)), ordered `(consumer, domain_event_id)`, executes them through the same `InlineDeliveryExecutor` path. Rows in backoff (future `available_at`) are skipped without blocking later events for that consumer (no per-consumer FIFO — deliberate, ADR).
- **Schedule**: registered in `routes/console.php` (verify the Laravel 13 scheduling idiom in vendor) at a sub-minute cadence — default `everyThirtySeconds()`. Note for ops (documented, not built): sub-minute schedules need `schedule:work`/`schedule:run` per Laravel's scheduler runtime; irrelevant in tests (the command is invoked directly).
- **Tunables** in `config/events.php` (new config file, platform-owned): `sweep.max_attempts` (default 5), `sweep.backoff_base_seconds` (default 30, exponential: `base * 2^(attempts-1)`), `sweep.backoff_cap_seconds` (default 3600). Values are launch defaults, env-overridable; the ADR fixes the *shape* (exponential, capped, dead-letter at max), not the numbers.
- Dead-letter = `status = 'failed'` rows; queryable via the model; the operator surface (manual retry) is explicitly a later change.

### D7 — Immutability triggers, cross-engine

One migration creates the triggers immediately after the two tables, branched on `DB::getDriverName()` via `DB::unprepared()`, **both branches fully implemented** (full parity — policy):

- **PostgreSQL**: one trigger function raising `EXCEPTION` with a stable message (convention: contains `immutable`), attached `BEFORE UPDATE OR DELETE` on `domain_events`; on `audit_records`, `BEFORE DELETE` → raise, and `BEFORE UPDATE` → raise **unless** every structural column is `IS NOT DISTINCT FROM` its OLD value (i.e. only `before`/`after` may differ).
- **SQLite**: `CREATE TRIGGER … BEFORE UPDATE ON domain_events BEGIN SELECT RAISE(ABORT, '…immutable…'); END;` (same for DELETE); on `audit_records`, the UPDATE trigger guards with a `WHEN` clause comparing each structural column with null-safe `IS NOT` so a structural change aborts and a before/after-only change passes.
- Tests assert the *behavior* (a `QueryException` whose message contains the stable token; row unchanged afterward) — never engine-specific SQLSTATEs, so the same tests prove parity on both lanes.
- **Scope note (documented):** triggers guard DML, not DDL — `DROP TABLE`/migration rollback still works in dev. Production DDL discipline is the additive-only policy (layer 3) + REVOKE (layer 2, runbook). Layer 2's runbook (exact `CREATE ROLE redactor` / `GRANT INSERT, SELECT` / `GRANT UPDATE (before, after)` SQL) lives in `docs/event-substrate.md`, applied at the hosting gate.

### D8 — The `pgsql` CI lane

A **separate `tests-pgsql` job** in `.github/workflows/ci.yml`, not a matrix on the existing `quality` job: lint and PHPStan are engine-independent and should run once; only `php artisan test` runs twice. Shape:

- `services: postgres: image: postgres:17` (the DB ADR's floor — CI tests the floor, providers may run newer) with health-check options, throwaway credentials.
- Same checkout/PHP/composer-cache steps (plus `pgsql, pdo_pgsql` in the setup-php `extensions` list); `cp .env.example .env && php artisan key:generate`; then `php artisan test` with `DB_CONNECTION=pgsql` + `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD` as job env.
- **phpunit.xml precedence landmine (verify):** `<env name="DB_CONNECTION" value="sqlite"/>` must not override the job's real environment — PHPUnit's `<env>` does not clobber already-set real env vars unless `force="true"` (ours has no `force`). Confirm against the installed PHPUnit behavior; if it clobbers, switch the lane to `php artisan test` with explicit `--env`/`DB_*` wiring that demonstrably wins, and record the resolution in `progress.md`.
- `tests/Feature/CiWorkflowTest.php` is **extended** (it pins the CI contract): new pins for the `tests-pgsql` job name, the `postgres:17` image, the pgsql `php artisan test` run line, and `DB_CONNECTION: pgsql`. The existing gate-order pins stay untouched.
- The loop cannot push (RALPH rule) so it never *observes* the lane green — the lane's correctness is carried by the workflow pins + engine-agnostic tests; the human push after review is the first live run (risk logged below).

### D9 — Hello-world: E2E tests + `events:demo` (founder-confirmed 2026-06-12)

- The **E2E feature test** is the acceptance backbone: inside one `DB::transaction()` — a state change (a `cache` table write: platform table, idempotent, no domain pollution), a recorded demo event, an audit record; after commit — the registered demo consumer ran (its own DB effect visible), the delivery row is `done`; then the immutability probes (UPDATE/DELETE rejected on both tables, redaction UPDATE allowed).
- `php artisan events:demo`: same flow, operator-runnable, printing the trail (event with `event_id`/`actor_role`, audit record, delivery transition, immutability rejections) and exiting 0; covered by a feature test asserting exit code + key output lines. Reusable as a staging smoke probe at the hosting gate (F7).
- The demo consumer lives next to the command (e.g. `App\Platform\Events\Demo\…`) and does DB work only. **Demo identifiers are clearly synthetic** (e.g. event name `PlatformDemoRecorded`, module `'platform'`, action `platform.demo`): verbatim spec event names are reserved for real module events — never burn a real name on a demo.

### D10 — Documentation set

`docs/event-substrate.md` (new, doc-pin-tested like its siblings): how to emit (recorder API + envelope + payload discipline + PII rule), how to consume (contract, registration on the provider seam, idempotency/order-tolerance obligations + the watermark pattern, the no-external-I/O rule + intent-row shape), delivery semantics (inline, sweep, backoff, dead-letter), immutability (three layers + the REVOKE runbook SQL), and the additive-only migration policy. `docs/INDEX.md` gains the row; `docs/development.md` CI section documents the second lane; `docs/module-template.md` § 6's "a `pgsql` CI lane lands with the first domain migration (the next foundations change)" sentence is refreshed to point at the now-existing lane (template stays decided-conventions-only).

## Risks / Trade-offs

- **[Vendor APIs written from memory]** UUIDv7 helper, `jsonb()`/`timestampTz()` SQLite behavior, fluent partial-index support, `DB::afterCommit` semantics, the Laravel 13 command-registration and scheduling idioms, PHPUnit `<env>` precedence. → The standing lesson applies: **verify every one in `vendor/` before writing** (lessons.md discipline; skeleton D4 precedent). Each task's hints name the specific API to verify.
- **[`afterCommit` under test transactions]** Inline-delivery tests could pass vacuously or fail mysteriously depending on the testing `DatabaseTransactionsManager`. → D5's fallback ladder; the at-least-once property is carried by the sweep tests either way, so no correctness claim hangs on the hook alone.
- **[SQLite CHECK limitation]** `actor_role`'s DB CHECK exists on PostgreSQL only. → Enum casts enforce the value set in the application on both engines; the migration documents the fallback inline (the policy's prescribed shape); the NOT NULL (the invariant-8 floor) holds on both.
- **[pgsql lane unobserved until first push]** The loop cannot push, so the lane's first live run happens at human review. → Workflow pins in `CiWorkflowTest`, engine-agnostic assertions throughout (no SQLSTATE/driver-message coupling), and the reviewer treats the first push's CI run as part of acceptance. If it reds, the fix is a normal follow-up iteration before merge.
- **[Trigger-parity drift]** The two trigger dialects could diverge semantically (e.g. the audit structural-column list). → One authoritative structural-column list in the migration code drives both branches; the same behavior tests run on both engines via the lane; any new column on `audit_records` (additive-only) must extend the trigger — called out in `docs/event-substrate.md`.
- **[Vacuously-green substrate tests]** Immutability and boundary additions are red-path by nature, but the *arch amendment* could pass without ever failing. → Mandatory red-proof for the `$platformNamespaces` extension (temp violating fixture under `app/Platform/` → RED → remove → GREEN, outputs in `progress.md`), mirroring the skeleton discipline.
- **[Payload float leakage]** A caller could pass an FX rate as a PHP float and JSON would happily store it. → The discipline is contract + tests now (decimal-string round-trip pinned), made unrepresentable by F1 3/3's value objects; the docs state the rule with the D18 rationale.
- **[Inline latency on emitting requests]** Accepted by the ADR at launch volumes; the lever is flipping a consumer to `queued` post-gate, not redesign. Consumers in this change are demo-only.
- **[Sweep double-execution windows]** A sweep tick overlapping inline execution (or a second tick overlapping a slow first) could double-deliver. → Acceptable at launch volumes *for DB effects* because handler + status flip share one transaction (the second executor finds the row no longer `pending`/due); `withoutOverlapping()` on the schedule entry; exactly-once-for-DB-effects is the tested property.
- **[Schema regret on an immutable table]** Columns on `domain_events` cannot be altered later. → The envelope is ADR-fixed and deliberately minimal; anything speculative stays in `payload`; additive-only keeps the escape hatch (new nullable column) open.

## Migration Plan

Purely additive: three new tables + triggers + new platform code; no existing table or behavior is touched. Deploy = merge (no production environment exists yet — the first deploy target arrives at the hosting gate, which also applies the REVOKE runbook). Rollback = `git revert` of the merge; the migrations' `down()` drops the three tables (dev-only concern; triggers guard DML, not DDL, so `down()` works). The additive-only policy binds *future* migrations on these two tables from the moment they exist.

## Open Questions

None. The two points the ADR left to realization with founder-visible shape (platform namespace; hello-world form) were resolved in the authoring interview of 2026-06-12 and are recorded as D1 and D9. Everything else here is realization of decided ADR substance; if an implementation iteration finds a contradiction between this design and the ADR, the ADR wins and the iteration escalates (`HUMAN_NEEDED`) rather than reinterpreting.

