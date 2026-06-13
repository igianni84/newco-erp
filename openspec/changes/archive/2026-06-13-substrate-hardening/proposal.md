## Why

A 360° audit of the event/audit substrate (`app/Platform/**`) on 2026-06-13 surfaced a focused set of correctness and hygiene gaps to close before any F2+ module starts consuming the bus. The substrate is the financial event store and the 10-year audit log (CLAUDE.md invariants 4 and 8), so a delivery-ledger race that could re-invoke a consumer or resurrect a completed delivery is a genuine exactly-once hazard. The rest are config drift (PHP floor, sweep overlap-mutex TTL, PostgreSQL session timezone), missing test coverage of already-specified guarantees (UUIDv7, the backoff cap, the `actor_role` CHECK, combined structural + redaction UPDATE), and doc/CI hardening. Fixing them now keeps the substrate's guarantees honest while the surface area is still small and single-owner.

## What Changes

**Substrate correctness (TDD, red→green):**
- Close an inline-vs-sweep delivery race in `InlineDeliveryExecutor`: re-fetch the delivery under `lockForUpdate()` inside the attempt transaction and return if it is no longer `pending`, and make the failure record a *conditional* update (`WHERE status = pending`) so a `done` delivery completed by a sibling runner can never be re-invoked or resurrected to `pending`/`failed`.
- Add dead-letter observability: `Log::warning` per failed attempt and `Log::error` on the transition to `failed` (dead-letter) in the executor; a swept/failed summary line in `events:sweep`.

**Schedule durability:**
- Cap the `events:sweep` overlap mutex TTL at 2 minutes — today it inherits the framework's 24h default, so a hard crash mid-sweep could block the sweep for up to a day.

**Config / deps (PHP 8.5):**
- Raise the declared PHP floor from `^8.3` to `^8.5` (composer.json + the runtime requirement test). The installed runtime (8.5.2) and both CI lanes are already on 8.5; this aligns the declared floor with reality.
- Document the `EVENTS_SWEEP_*` tunables (commented) in `.env.example`, matching `config/events.php` defaults.
- Pin the PostgreSQL connection session `timezone` to `UTC`.

**Test-coverage gaps (already-specified behavior, no spec change):**
- Pin `event_id` as UUIDv7 (version nibble), not merely "a UUID".
- Assert the exponential-backoff **cap** (today only the growth is proven, never the ceiling).
- Assert the PostgreSQL `actor_role` CHECK rejects an out-of-enum value (engine-guarded; SQLite's floor is the enum cast).
- Assert an UPDATE touching a structural column **and** `before`/`after` together is still rejected (the redaction seam is `before`/`after`-only).

**CI:**
- Add a workflow `concurrency` group that cancels superseded in-flight runs per ref.

**Docs (non-protected):**
- `GUIDE.md` §2.7: add the local PostgreSQL 17 verify step (currently only in `knowledge/testing/rules.md`).
- `docs/development.md`: document `RALPH_MODEL`/`RALPH_EFFORT` (ralph.sh now pins the model) and correct the stale "nothing under `.claude/`" claim; PHP floor → 8.5.
- `README.md`: document ralph exit codes `2` and `5`; replace the non-existent `/opsx:verify` reference with the real semantic-verify (GUIDE §2.7).
- `decisions/INDEX.md`: track the currently-untracked gates — secrets management, observability, PCI boundary, architectural security review.

## Capabilities

### New Capabilities
<!-- none — this change adds no new capability spec -->

### Modified Capabilities
- `event-substrate`: adds two requirements that make existing implicit guarantees explicit and observable —
  - **Concurrent Delivery Safety** — a delivery completed by one runner (inline or sweep) is never double-invoked nor resurrected by a concurrent runner.
  - **Delivery Failure Observability** — failed delivery attempts and dead-letter transitions are logged, and each sweep emits a swept/failed summary.

  Every other item above either strengthens *test coverage* of an already-specified requirement (UUIDv7 envelope, backoff cap, immutability triggers, `actor_role` CHECK) or changes only config/CI/docs — none alter spec behavior, so they carry no delta.

## Impact

- **Code:** `app/Platform/Events/InlineDeliveryExecutor.php`, `app/Platform/Events/SweepCommand.php`, `routes/console.php`, `config/database.php`, `config/events.php` (read-only reference), `composer.json`, `.env.example`.
- **Tests:** `tests/Feature/Platform/{InlineDeliveryTest,SweepTest,DomainEventRecorderTest,ImmutabilityTest}.php`, `tests/Feature/PlatformRequirementsTest.php`, `tests/Feature/CiWorkflowTest.php`, plus a new `tests/Feature/Platform/ActorRoleConstraintTest.php`.
- **CI/docs:** `.github/workflows/ci.yml`, `GUIDE.md`, `docs/development.md`, `README.md`, `decisions/INDEX.md`.
- **Engines:** all behavior verified on both SQLite (`:memory:`) and PostgreSQL 17; the `lockForUpdate` re-check is a real row lock on PG and a no-op on single-connection SQLite, and the `actor_role` CHECK test is engine-guarded.
- **Dependencies:** none added. PHP floor raised to `^8.5` (no environment regresses — runtime and CI are already 8.5).
- **Invariants:** reinforces invariant 4 (financial immutability — no resurrection of terminal delivery state) and the substrate's exactly-once-for-DB-effects guarantee; touches no module boundary.
