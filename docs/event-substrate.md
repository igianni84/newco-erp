# Event Substrate & Audit Store

The platform machinery every F2+ module uses to **emit domain events, record operator actions, consume events, and trust that history is immutable**. It implements ADR [`decisions/2026-06-12-event-substrate-and-audit-store.md`](../decisions/2026-06-12-event-substrate-and-audit-store.md) as decided, and the `foundations-domain-events-audit` change realizes it (design D1–D10). **This page documents decided conventions only; it invents nothing.** The boundary law, persistence prefixes and test conventions for modules live in [`module-template.md`](module-template.md); setup, quality commands and the two CI lanes in [`development.md`](development.md).

The substrate is the inter-module API: modules never share Eloquent models, joins or DB access (CLAUDE.md invariant 10). They talk by **publishing a domain event** and **registering a consumer** for the events they react to — the ~120 spec events are the contract surface.

## 1. Where it lives

All substrate code is under the single platform root `App\Platform` (design D1), never `App\Modules`:

```
app/Platform/
├── Events/
│   ├── DomainEvent.php            # Eloquent model → domain_events
│   ├── EventDelivery.php          # Eloquent model → event_deliveries
│   ├── ActorRole.php              # enum: newco_ops | producer | customer | system
│   ├── DeliveryStatus.php         # enum: pending | done | failed
│   ├── DeliveryMode.php           # enum: inline (queued arrives with the queue ADR)
│   ├── DomainEventRecorder.php    # the in-transaction append API for events
│   ├── Contracts/DomainEventConsumer.php
│   ├── ConsumerRegistry.php       # event name → registered consumers
│   ├── InlineDeliveryExecutor.php # post-commit + sweep execution engine
│   ├── SweepCommand.php           # php artisan events:sweep
│   └── Demo/                      # php artisan events:demo (hello-world)
└── Audit/
    ├── AuditRecord.php            # Eloquent model → audit_records
    └── AuditRecorder.php          # the append API for operator actions
```

**Three platform tables, deliberately unprefixed** (`domain_events`, `audit_records`, `event_deliveries` — they are platform, not module, so the module-prefix convention does not apply; see module-template § 6):

| Table | Role | Mutability |
|---|---|---|
| `domain_events` | Append-only log = transactional outbox **+** 10-year audit log for state transitions **+** financial event store (Module E's ~30 financial event types are rows here — there is no separate financial table). | **Fully immutable** (no UPDATE, no DELETE). |
| `audit_records` | Operator/system action trail (Architecture § 5.3): who did what to which entity, with the `before`/`after` snapshot and the authorization basis. Write-only — nobody consumes it. | **Structurally immutable**: only `before`/`after` may change (GDPR redaction); never DELETE. |
| `event_deliveries` | Per-(event × consumer) delivery ledger: `pending → done \| failed`, attempts, backoff clock. R4 resolved structurally — one row per consumer, independent retries. | **Mutable** (delivery infrastructure; terminal rows are prunable after a grace period). |

## 2. How to emit a domain event

`App\Platform\Events\DomainEventRecorder::record()` is the **single write path** for `domain_events`. Resolve it from the container (constructor injection or `app(DomainEventRecorder::class)`).

```php
public function record(
    string $name,            // the spec event name, VERBATIM
    string $module,          // Module::X->value, or 'platform' — never the Module enum (boundary law)
    ActorRole $actorRole,    // newco_ops | producer | customer | system (invariant 8)
    ?int $actorId,           // local user/party PK, or null for a system actor
    string $entityType,
    string $entityId,        // string — spans bigint PKs and natural keys
    array $payload,          // entity ids + business data ONLY (see Payload discipline)
    ?string $correlationId = null,  // defaults to the event's own event_id (root event)
    ?int $causationId = null,       // the id of the causing event, or null
): DomainEvent
```

**It MUST run inside the caller's already-open transaction.** `record()` appends the event row **and** one `pending` `event_deliveries` row per registered consumer, all in that one transaction, so the state change and the events recorded with it commit or roll back together (the no-dual-write rule). Recording outside a transaction throws `App\Platform\Events\NotInTransactionException` — the rule is enforced, not advised.

```php
use App\Modules\Commerce\Models\Voucher;
use App\Modules\Module;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($recorder): void {
    // 1. the state change (module tables)
    $voucher = Voucher::create([/* … */]);

    // 2. the event, in the SAME transaction
    $recorder->record(
        name: 'VoucherIssued',
        module: Module::Commerce->value,   // the registry stays the typed anchor; the substrate gets the string
        actorRole: ActorRole::Customer,
        actorId: $customerId,
        entityType: 'voucher',
        entityId: (string) $voucher->id,
        payload: [
            'amount_minor' => 12_000,      // integer minor units
            'currency' => 'EUR',           // ISO 4217 code
            'fx_rate' => '1.0842',         // a decimal string — NEVER a float
        ],
    );
});
```

### The envelope (`domain_events` columns)

| Column | Notes |
|---|---|
| `id` | bigint identity PK — monotonic insertion order **is** intra-transaction causal order (Module A § 12.4). |
| `event_id` | application-side **UUIDv7**, unique — the public identity (idempotency keys, module-ledger references, future broker export). |
| `name` | the spec event name, verbatim. |
| `schema_version` | smallint, default 1 — bumped only on a breaking payload change (the monolith deploys producers + consumers atomically). |
| `module` | the emitter (`Module::X->value` or `'platform'`). A plain string — the platform may not import `App\Modules`. |
| `occurred_at` | `timestamptz`, application-set in **UTC** (time-travel-testable). |
| `actor_role` | NOT NULL, one of the four `ActorRole` values (invariant 8). |
| `actor_id` | nullable bigint — local PK; does not foreclose the identity ADR. |
| `entity_type` / `entity_id` | the primary subject (a bottle's provenance is an `(entity_type, entity_id, id)` envelope query). |
| `correlation_id` | UUID — everything one trigger caused, cross-module; defaults to the event's own `event_id`. |
| `causation_id` | nullable bigint FK → `domain_events.id` — the causal chain, queryable cross-transaction. |
| `payload` | `jsonb` — see discipline below. |

### Payload discipline (the caller's contract — documented + tested, never coerced)

The substrate persists the `payload` array verbatim through the `jsonb` cast; it does **not** sanitize. Three rules every emitter follows:

- **Money is integer minor units + an ISO 4217 currency code.** Never a float, never a major-unit decimal (invariant 6).
- **FX rates are a decimal string**, e.g. `'1.0842'` — never a PHP float. Rationale (D18): every customer-facing financial event records the customer currency *and* EUR at a locked rate, and refunds settle at that **exact** original rate (invariant 5); a float would introduce binary-rounding drift that breaks exact-rate settlement. A test pins that a decimal string survives the JSON round-trip unchanged. Emitters build these payload fields with the `App\Platform\Money` value objects (`Money`, `FxRate`, `DualCurrencyAmount`), which make a float here unrepresentable.
- **No PII in `payload`.** `domain_events` is PII-free **by design** — that is what earns it absolute immutability. Payloads carry ids, amounts, quantities, states and dates; names/emails/addresses live in module tables where Module K's overwrite-in-place GDPR erasure operates. GDPR erasure never touches the event log.

## 3. How to record an operator action (audit)

`App\Platform\Audit\AuditRecorder::record()` is the single write path for `audit_records` — actions with a `before`/`after` snapshot and an authorization basis, **including those that emit no cross-module event** (e.g. a description edit). It shares the envelope core and the same transaction guard, but creates **no** `event_deliveries` rows (audit is write-only).

```php
public function record(
    string $action,                 // e.g. 'parties.hold.placed'
    string $module,
    ActorRole $actorRole,
    ?int $actorId,
    string $entityType,
    string $entityId,
    ?array $before,                 // null for a creation
    ?array $after,                  // both nulled on GDPR redaction
    string $authorizationBasis,     // why the actor was allowed to do this
    ?string $correlationId = null,  // defaults to a fresh UUIDv7 (independent of any event)
): AuditRecord
```

Unlike the event recorder, `correlation_id` defaults to a **fresh independent** UUIDv7 (an audit action is its own root); pass an event's `correlation_id` to tie an action to the events it triggered. `before`/`after` may legitimately contain PII — that is why this table is only *structurally* immutable (§ 5).

## 4. How to consume an event

### The contract

A consumer implements the one interface `App\Platform\Events\Contracts\DomainEventConsumer`:

```php
interface DomainEventConsumer
{
    public function handle(DomainEvent $event): void;   // receives the PERSISTED envelope
}
```

The handler receives the persisted `DomainEvent` model (id, name, payload, …), **not** a transient object — delivery flows through the `event_deliveries` ledger, never Laravel's framework event bus (`Event::dispatch`).

### Registration on the provider seam

Register consumers from a module's `{Name}ServiceProvider::boot()` (the wiring seam module-template § 4 reserved) against the shared `ConsumerRegistry` singleton:

```php
use App\Modules\Procurement\Listeners\SettleSupplierLedger;
use App\Platform\Events\ConsumerRegistry;

public function boot(ConsumerRegistry $registry): void
{
    $registry->register('SupplierPaymentCompleted', SettleSupplierLedger::class);
}
```

`register()` validates the class implements `DomainEventConsumer` (else `InvalidArgumentException`); registering the same `(event, consumer)` pair twice is **idempotent** (first wins). The third argument is `DeliveryMode $mode = DeliveryMode::Inline` — `Inline` is the only mode at launch; `queued` is gated behind the queue-driver ADR (F4–F6) as a compile-time guarantee (the single-case enum makes a non-inline registration unrepresentable).

### Consumer obligations (the hard rules)

Delivery is **at-least-once with possible cross-transaction disorder** (Module A § 12.4: causal order holds *within* a transaction; across transactions arrival order is tolerable). Therefore every consumer is:

- **Idempotent** — `handle()` can run more than once for the same event (a retry, a sweep racing the inline hook). The same event delivered twice must produce the same DB state. Use `event_id` as the natural idempotency key.
- **Order-tolerant** — a later event for an entity may arrive before an earlier one. A *latest-wins* consumer guards with a **per-entity watermark**: track the highest `domain_events.id` already applied for that entity and ignore any event whose `id` is not greater.

```php
public function handle(DomainEvent $event): void
{
    $entityKey = $event->entity_type.':'.$event->entity_id;

    // Per-entity watermark: skip an out-of-order or duplicate replay for this entity.
    if ($event->id <= $this->lastAppliedId($entityKey)) {
        return;
    }

    // … apply the projection, then advance the watermark to $event->id …
}
```

- **DB work only — no external I/O inside `handle()`.** A handler's writes and the delivery's `done` flip share one transaction (exactly-once for DB effects); doing an HTTP call there would hold a DB transaction open across the network and break that guarantee.
- **External calls follow Module E § 7's intent-row shape.** The inline consumer records *intent* in a module-owned table (e.g. a `pending` Xero-sync row); a module-owned **scheduled processor** performs the actual call (Xero / Airwallex / Logilize) with its own retry policy and uses `event_id` as the idempotency key toward the external system. No external system is ever called from a delivery. (Processors are F2+ scope.)

### Consumer identity & the rename ledger note

A delivery row identifies its consumer by the **class FQCN** stored in `event_deliveries.consumer` (design D4 — simple, collision-free, no extra API). Trade-off: **renaming or moving a consumer class orphans its non-terminal (`pending`) ledger rows** — the executor would resolve the old FQCN and fail. So a rename/move of a registered consumer **must migrate `event_deliveries.consumer`** (an `UPDATE … SET consumer = '<new FQCN>' WHERE consumer = '<old FQCN>'`) in the *same* change. Rare and reviewable.

## 5. Delivery semantics

One engine — `App\Platform\Events\InlineDeliveryExecutor` — runs every delivery, from two entry points sharing one "due" definition:

- **Inline fast path** — the recorder registers a `DB::afterCommit` hook per `record()` call; once the emitting transaction **commits**, the just-recorded event's deliveries run in `domain_event_id` then `id` order (causal order). A rolled-back transaction delivers nothing (Laravel discards uncommitted after-commit callbacks). Multiple records in one transaction fire FIFO in recorded (= id) order.
- **The sweep — the durability guarantee.** `php artisan events:sweep` (`App\Platform\Events\SweepCommand`) drains **all due** deliveries through the same path, ordered `(consumer, domain_event_id)`. It is scheduled in `routes/console.php` at `everyThirtySeconds()->withoutOverlapping()`. The inline hook is opportunistic; the sweep is what makes delivery **at-least-once** — a crash between commit and the inline hook leaves `pending` rows the next tick picks up. (Sub-minute schedules need `schedule:work` running in production — an ops note, irrelevant in tests where the command is invoked directly.)

A delivery is **due** when it is `pending` **and** its backoff has elapsed (`available_at` is NULL, or now-or-past). Terminal rows (`done`, `failed`) are excluded by query construction — `done` is terminal, so re-running the executor never re-invokes a completed handler.

**Guarantees and failure handling:**

- **Exactly-once for DB effects** — the consumer's handler and the `done` flip (attempts + 1) share **one** transaction; they commit together or not at all.
- **Per-consumer failure isolation (R4)** — each delivery runs in its own try/catch; a poison consumer's failure never touches a sibling consumer's row nor the emitter's already-committed data. On a throw the handler transaction rolls back (discarding partial effects) and the failure is recorded in a *separate* write.
- **Exponential backoff, capped, then dead-letter** — a failed attempt sets `available_at = now + base · 2^(attempts−1)` (capped), stores the truncated `last_error`, and stays `pending` until `attempts` reach the maximum, at which point status becomes `failed` (dead-letter in place). A `failed` row is never swept again; the operator retry surface is a later change.
- **No blocking per-consumer FIFO** — a row in backoff is simply not due and is skipped; a later event for the same consumer still delivers (a poison event must not stall the whole stream). The only hard ordering the spec demands (Module E § 7 reversal-after-source) lives in E's FSM as consumer logic, never in the substrate.

**Tunables** live in `config/events.php` (platform-owned, env-overridable); the ADR fixes the *shape*, not the numbers:

| Key | Default | Meaning |
|---|---|---|
| `events.sweep.max_attempts` | `5` | Attempts before a row is dead-lettered (`failed`). |
| `events.sweep.backoff_base_seconds` | `30` | Backoff base; the window after attempt *N* is `base · 2^(N−1)`. |
| `events.sweep.backoff_cap_seconds` | `3600` | Upper bound on a single backoff window (one hour). |

## 6. Immutability — three layers

History is protected at three independent layers (ADR "Immutability mechanism"). The threat model is **application bugs and operator mistakes**, *not* a hostile DBA — this is explicitly **not** WORM storage or cryptographic tamper-evidence (hash-chaining was rejected; a provider superuser can always drop a trigger).

### Layer 1 — DB triggers (both engines, travel with the schema)

Migration `…_add_immutability_triggers` installs triggers with **full SQLite/PostgreSQL parity** (the dev/test lane and the production engine enforce the same rule). Every rejection message contains the stable token **`immutable`**, and tests assert that token + the row unchanged — never engine-specific SQLSTATEs, so one test suite proves both lanes:

- `domain_events` — **every** `UPDATE` and **every** `DELETE` is rejected (fully append-only).
- `audit_records` — every `DELETE` is rejected; an `UPDATE` is rejected **unless only `before`/`after` change**. Overwriting only those two JSONB columns is the *sole* permitted mutation — the GDPR redaction seam (Module K's erasure job overwrites PII inside the snapshots in place, never DELETE).
- `event_deliveries` — **no trigger** (deliberately mutable delivery infrastructure).

The "structural" definition of an audit row is one authoritative column list (`$auditStructuralColumns`) in the migration that drives both the PostgreSQL (`IS DISTINCT FROM`) and SQLite (`IS NOT`) branches, so the two dialects cannot drift. **Triggers guard DML, not DDL** — `down()` / `migrate:fresh` still drop the tables in dev; production DDL discipline is layers 2 + 3.

### Layer 2 — production REVOKE runbook (PostgreSQL; applied at the hosting gate)

Defence in depth at the privilege layer: the application role may only **INSERT + SELECT** the two append-only tables; a dedicated **`redactor`** role holds the *only* mutation right — column-level `UPDATE` on `audit_records.before` / `.after`, used solely by Module K's GDPR erasure job. No production database exists yet, so this is a runbook applied at the hosting gate (F7), not code in this change.

```sql
-- Replace <app_role> with the hosting-provided application role.

-- The application role: read + append only on both append-only tables.
REVOKE UPDATE, DELETE, TRUNCATE ON domain_events  FROM <app_role>;
REVOKE UPDATE, DELETE, TRUNCATE ON audit_records   FROM <app_role>;
GRANT  INSERT, SELECT              ON domain_events TO   <app_role>;
GRANT  INSERT, SELECT              ON audit_records  TO   <app_role>;

-- The redactor role: the sole UPDATE right, column-scoped to the redaction snapshots.
-- NOLOGIN — assumed by Module K's erasure job via SET ROLE redactor, never a login.
CREATE ROLE redactor NOLOGIN;
GRANT  SELECT                      ON audit_records  TO   redactor;
GRANT  UPDATE (before, after)      ON audit_records  TO   redactor;
```

### Layer 3 — additive-only migration policy (discipline + review)

Migrations on `domain_events` and `audit_records` are **additive-only**: adding a nullable column is allowed; `ALTER`/`DROP` of an existing column is never. Migrations run as the table owner, so this layer is discipline enforced in review, declared as an invariant.

> **Extend-the-trigger rule.** Any new column added to `audit_records` **must also be added to `$auditStructuralColumns`** in the immutability migration — otherwise the structural-UPDATE trigger silently lets the new column be mutated, breaking the audit guarantee. Adding a column is additive (allowed); forgetting the trigger list is the trap. (`domain_events` needs no such care — it forbids *all* updates regardless of column.)

## 7. Cross-engine proof & the hello-world

Tests run on SQLite `:memory:`; the **`pgsql` CI lane** re-runs the suite against PostgreSQL 17 (the production-DB floor), proving the Postgres-truthful branches the SQLite lane can't reach — the `actor_role` CHECK, the partial `WHERE status = 'pending'` index, and the plpgsql immutability trigger functions. See [`development.md`](development.md) for the two-lane CI story.

`php artisan events:demo` (design D9) is the runnable hello-world: in one transaction it makes a state change, records a synthetic demo event and an audit record, delivers the event inline to a demo consumer, then probes the immutability triggers — printing the whole trail and exiting 0. Reusable as a staging smoke probe at the hosting gate. Its identifiers are deliberately synthetic (`PlatformDemoRecorded`, `platform.demo`); verbatim spec event names are reserved for real module events.
