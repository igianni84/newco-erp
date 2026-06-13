## Context

The event/audit substrate (`app/Platform/Events`, `app/Platform/Audit`) was delivered by the archived `foundations-domain-events-audit` change and is the system of record: `domain_events` is the transactional outbox + 10-year audit + financial event store, `audit_records` the operator trail, `event_deliveries` the per-consumer delivery ledger. A 360° audit on 2026-06-13 produced 15 findings (C1–C15). This change implements them with zero new dependencies, on PHP 8.5, verified on both engines (SQLite `:memory:` dev/test and PostgreSQL 17 production floor).

The substantive correctness item is C1: the inline post-commit hook (`DomainEventRecorder` → `DB::afterCommit` → `InlineDeliveryExecutor::deliver`) and the scheduled sweep (`events:sweep` → `InlineDeliveryExecutor::deliverDue`) both run the same ledger and can contend for the same `pending` row. `InlineDeliveryExecutor::attempt()` (current `app/Platform/Events/InlineDeliveryExecutor.php:117`) neither locks nor re-checks status before invoking the handler, and `recordFailure()` (current `:155`) writes unconditionally — so a delivery completed `done` by one runner can be re-invoked, or resurrected to `pending`/`failed`, by a sibling. Everything else is config/test/CI/doc hardening that operationalizes or strengthens coverage of already-specified behavior.

Constraints (CLAUDE.md + `knowledge/testing/rules.md`): money/i18n untouched; module boundaries untouched; tests green on BOTH engines (SQLite necessary, never sufficient); engine-specific behavior is engine-guarded; spec truth (`openspec/specs/**`) is never hand-edited (the wording fixes for Purpose-TBD / queued-scenario are explicitly out of scope and ride future changes).

## Goals / Non-Goals

**Goals:**
- Make the existing "a delivery already `done` SHALL never re-execute" guarantee hold under inline-vs-sweep concurrency (C1), with deterministic tests on both engines.
- Make dead-letters observable (C3); bound the sweep overlap-mutex stall (C2).
- Raise the declared PHP floor to 8.5 and close the small config gaps (C4–C6).
- Close four test-coverage gaps for already-specified behavior (C7–C10).
- Harden CI (C11) and the non-protected docs/gate-tracking (C12–C15).

**Non-Goals:**
- No operator retry surface for dead-lettered deliveries (still a later change — this only makes them visible).
- No queue driver / `queued` mode (gated behind the queue ADR, F4–F6).
- No schema migration, no new dependency, no module code.
- No edits to `openspec/specs/**`, `spec/**`, or any protected file; the Purpose-TBD/queued-scenario wording fixes are not in this change.

## Decisions

### D1 — C1 race fix: row-lock re-check in `attempt()` + conditional `recordFailure()`
Inside `attempt()`'s `DB::transaction`, re-fetch the row with `EventDelivery::query()->whereKey($delivery->getKey())->lockForUpdate()->first()` and `return` if it is null or its `status !== Pending` (a sibling already won it) — only then resolve the consumer, invoke the handler, and flip the locked row to `done`. In `recordFailure()`, replace the model `->update([...])` with a builder-level **conditional** update guarded on `where('status', DeliveryStatus::Pending->value)`, so a row a sibling completed `done` between the throw and the failure-write matches zero rows and is never resurrected.

*Rationale:* the minimal, portable pair. The lock serializes the winning attempt and the status re-check enforces done-is-terminal under contention; the pending-guard closes the failure-path resurrection. *Alternatives:* an optimistic version column or a `claimed_at` lease (more migration surface, additive trigger concern on the immutable-adjacent table); PG advisory locks or `SKIP LOCKED` (PG-only, non-portable, and `SKIP LOCKED` alone still leaves the failure-path resurrection open). `lockForUpdate()` is a real row lock on PostgreSQL and a documented no-op on single-writer SQLite — both correct; only PG exercises true contention.

### D2 — C1 test strategy: drive the post-race state via the private methods (reflection)
SQLite `:memory:` is a single connection: a test cannot truly interleave two runners, and a second connection would be a different in-memory database — so the race is not reproducible through the public `deliver()`/`deliverDue()` surface. Instead, test the *guards* deterministically by constructing the exact post-race state and invoking the private `attempt()`/`recordFailure()` (reflection or a bound closure):
- **(a) conditional recordFailure:** seed a delivery, set its DB row `done`/`attempts 1`, invoke `recordFailure($model, new RuntimeException(...))`, assert the row stays `done`/`attempts 1` (without the fix it flips to `pending`/`failed`).
- **(b) lock re-check:** seed a `pending` delivery for `RecordingConsumer`, flip the DB row to `done` while keeping a stale in-memory `pending` model, invoke `attempt($staleModel)`, assert `RecordingConsumer::$handled` is empty and the row stays `done`/`attempts 1` (without the fix the handler re-runs and `attempts` becomes 2).

*Rationale:* reflection is the honest, deterministic tool for unit-testing a concurrency guard on a single-connection test DB; thread/sleep races are flaky and non-portable. Both assertions are non-vacuous (named failure modes above) and engine-agnostic, so they run identically on both lanes; the PG CI lane is the production-faithful contention proof. This is a new "white-box concurrency guard" test pattern for the substrate — note it in `progress.md` Codebase Patterns.

### D3 — C3 surfacing counts + log levels
`deliverDue()` returns `void` today. Give it a small return — `array{delivered:int, failed:int}` (or a tiny readonly DTO) — tallied from per-`attempt()` outcomes; `deliver()` (the inline hook) stays `void` (no summary surface there). `SweepCommand::handle()` logs the summary (`Log::info`) from that result. In `recordFailure()`, log `Log::warning` for a still-retryable failure and `Log::error` when the new status is `failed` (reusing the existing `$attempts >= $maxAttempts` branch as the dead-letter signal); each line carries the delivery id, consumer, and error. *Alternative:* re-query the ledger for counts after the run — racy and redundant; in-loop counting is exact for that run. Tests use `Log::fake()`/spy and assert the channel+level, not message wording.

### D4 — C2 overlap-mutex TTL
`routes/console.php:16`: `->withoutOverlapping()` → `->withoutOverlapping(2)` (verified: `Illuminate\Console\Scheduling\ManagesAttributes::withoutOverlapping($expiresAt = 1440)` sets the public `$expiresAt`; the cache mutex TTL is `expiresAt * 60` seconds, so `2` = a 2-minute lease vs the 24h default). Update the inline doc-comments in `routes/console.php` and `SweepCommand` that describe the guard. **Test pin:** `SweepTest` schedule test (current `:206–218`) asserts `repeatSeconds === 30` and `withoutOverlapping === true`; add `->and($sweep->expiresAt)->toBe(2)`.

### D5 — C4 PHP floor 8.5
`composer.json:9` `"php": "^8.3"` → `"^8.5"`; `tests/Feature/PlatformRequirementsTest.php:7–9` `80400` → `80500` and the description string `>= 8.4` → `>= 8.5`. The CI `php-version: '8.5'` (`ci.yml:34,107`) and its `CiWorkflowTest:36` pin are **already** 8.5 — verify-only, no edit. *Safety:* `DevelopmentDocsTest` pins package versions from `composer.lock` and the `RALPH_EFFORT` token, but neither the PHP floor string nor the PHP table row — so the doc floor-text changes (D9) are safe. No environment regresses (runtime 8.5.2, both CI lanes 8.5); this aligns the declared floor with reality, so it is treated as a floor correction, not a breaking API change.

### D6 — C5/C6 config
`.env.example`: add a commented block (near `QUEUE_CONNECTION`, line 38) — `# EVENTS_SWEEP_MAX_ATTEMPTS=5`, `# EVENTS_SWEEP_BACKOFF_BASE_SECONDS=30`, `# EVENTS_SWEEP_BACKOFF_CAP_SECONDS=3600` — verbatim the `config/events.php` defaults (`:25,28,31`), commented so the env stays on defaults. `config/database.php` `pgsql` block (`:87–100`): add `'timezone' => 'UTC',`. *Rationale:* Laravel's Postgres connector issues `SET TIME ZONE` when `timezone` is set; pinning UTC makes `timestamptz` rendering deterministic regardless of the server's local zone, matching the substrate's app-set-UTC discipline (`occurred_at`/`available_at`). SQLite is unaffected (no such key).

### D7 — C7/C8 envelope + backoff-cap coverage
- **C7:** `DomainEventRecorderTest.php` (envelope read-back, current `:114–115`) — add `->and($read->event_id[14])->toBe('7')` (the UUIDv7 version nibble; the recorder already emits `Str::uuid7()` at `DomainEventRecorder.php:83`, and `Str::uuid7()` was confirmed to place `'7'` at string index 14). Optionally strengthen the root-correlation read-back (`:243`) the same way.
- **C8:** `SweepTest.php` — assert the cap. With `Config::set('events.sweep.backoff_base_seconds', 2000)` (cap stays 3600, max stays 5) under a frozen clock: the first failure's window is `t+2000` (uncapped — proves the base path), the second is `t+min(4000,3600) = t+3600` (capped). Assert `available_at->equalTo(t+3600)` at the capped attempt. Pure config + `CarbonImmutable::setTestNow()` — engine-agnostic. (Today's dead-letter test advances by hours but never asserts the ceiling value.)

### D8 — C9/C10 immutability/constraint coverage
- **C9 (new `tests/Feature/Platform/ActorRoleConstraintTest.php`):** insert a complete, DB-valid `domain_events` row (and an `audit_records` row) whose `actor_role` is an out-of-enum literal, via `DB::table()->insert()` (bypassing the `ActorRole` cast). **Engine-guarded:** on `pgsql` expect a `QueryException` (the `domain_events_actor_role_check` / `audit_records_actor_role_check` CHECK from migrations `000001:70–79` / `000002:80–89`); on `sqlite` the raw insert is accepted — the value-set floor there is the application enum cast, not a DB CHECK — so assert the row inserts and document the asymmetry (so the SQLite branch is a positive assertion, never a vacuous skip). If the PG branch verifies row-absence after the throw, wrap the bad insert in a nested `DB::transaction()` (savepoint) per testing-rule #5 (a PG CHECK violation aborts the whole transaction). Build a fully-valid row so the CHECK is the sole failure cause (no NOT-NULL throwing first).
- **C10 (`ImmutabilityTest.php`):** add a scenario — an UPDATE changing a structural column **and** `before`/`after` together is still rejected. The trigger's guard (`OLD.col IS DISTINCT FROM NEW.col` on PG, `IS NOT` on SQLite, migration `000004`) fires on ANY structural change regardless of `before`/`after`, so the combined update raises. Reuse `captureImmutabilityError()` (already savepoint-wrapped) + assert `toContain('immutable')` and the structural column unchanged. Proves the redaction seam is strictly `before`/`after`-only — a structural edit can't be smuggled inside a redaction.

### D9 — C11 CI concurrency + pin
`ci.yml`: add a top-level `concurrency:` block (after the `permissions:` block, ~`:21`) — `group: ci-${{ github.ref }}`, `cancel-in-progress: true`. One workflow-level block governs both lanes. `CiWorkflowTest`: add a test asserting `toContain('concurrency:')`, `toContain('cancel-in-progress: true')`, and the `ci-${{ github.ref }}` group. *Rationale:* cancels superseded in-flight runs on rapid pushes — most valuable for the `tests-pgsql` service-container lane.

### D10 — C12–C15 docs (all non-protected)
- **C12 `GUIDE.md` §2.7:** insert a local-PostgreSQL-17 verify step *before* the merge step, with the exact command from `knowledge/testing/rules.md:9–13` (`docker run … postgres:17` → `DB_CONNECTION=pgsql … php artisan test` → `docker rm -f pg`), in Italian to match the guide. `FoundationsDocsTest` only pins the F1 status line (`foundations-money-i18n-flags`, `F1 completata 3/3`) → safe.
- **C13 `docs/development.md`:** (a) add a `RALPH_MODEL` bullet to the env-vars list and correct the stale "The script has no model option of its own" sentence — `ralph.sh` pins `--model "${RALPH_MODEL:-claude-opus-4-8[1m]}" --effort "${RALPH_EFFORT:-max}"` (`ralph.sh:24–25,204`); keep the `RALPH_EFFORT` token (DevelopmentDocsTest pins it). (b) correct `:113` "nothing under `.claude/`" — `.claude/` holds hooks, skills, and team memory; reword to "no MCP server (`.mcp.json` absent), but `.claude/` carries hooks/skills/memory". (c) PHP floor `8.4 → 8.5` (`:11`, `:76`, and the `:121` constraint column).
- **C14 `README.md`:** (a) the exit-codes line (current `:68`, not the stale "~19-21") — add `2` (preflight error) and `5` (integrity violation) to match `docs/development.md:104`. (b) the ASCII-flow line (`:28`) — replace the non-existent `/opsx:verify` with the semantic-verify reference (GUIDE §2.7), aligning with `:53` which is already correct; soften the skills-table "verify" mention (`:94`) if needed.
- **C15 `decisions/INDEX.md`:** extend the "Open decisions" section (`:14–16`) with explicit entries for the four currently-untracked gates — secrets management, observability, PCI boundary, architectural security review — each with a one-line trigger. *Decision:* keep them in `decisions/INDEX.md` (the existing editable open-gates registry) rather than spawning a new gates doc — CLAUDE.md's stack-gate table is protected and stack-scoped, so INDEX.md is the right single home for these cross-cutting operational/security gates.

## Risks / Trade-offs

- **[C1 reflection tests are white-box — coupled to private method names]** → acceptable: `attempt()`/`recordFailure()` are stable substrate internals; the alternative (non-deterministic thread races) is worse, and the existing black-box guards (done-is-terminal, fan-out isolation) stay. If the methods are later refactored, these tests move with them.
- **[`lockForUpdate` is a no-op on SQLite → true contention only on PG]** → the guard *logic* (re-check + conditional write) is asserted deterministically on both engines via D2; PG is the contention proof. The new GUIDE §2.7 local-PG step (C12) makes that run routine before merge.
- **[C3 changes `deliverDue()`'s signature]** → internal only (executor + `SweepCommand` + tests); no module depends on it; PHPStan max types the new return.
- **[PHP floor bump]** → no known environment regresses (runtime 8.5.2, both CI lanes 8.5); it corrects the declared floor to match reality.
- **[Engine-split test (C9) passing vacuously on SQLite]** → the SQLite branch asserts positively (row inserted), documenting the asymmetry rather than silently skipping; the PG CI lane is the real CHECK proof.
- **[Doc edits drifting from their pinning tests]** → mapped each: `DevelopmentDocsTest` (RALPH_EFFORT token + locked versions), `FoundationsDocsTest` (GUIDE F1 line), `CiWorkflowTest` (php 8.5, gate order, + the new concurrency token), `PlatformRequirementsTest` (≥8.5). Each edit keeps its pin green; where a test "pins a string that changes" (CiWorkflowTest concurrency, SweepTest TTL) the pin is updated in the same task.

## Migration Plan

No data/schema migration — config, code, test, and doc only. The `pgsql` `timezone` setting and the PHP floor take effect on the next CI run / deploy with no backfill. Rollback is a straight revert of the change (no irreversible step). The substrate's append-only tables and immutability triggers are untouched.

## Open Questions

- **C15 home:** chosen `decisions/INDEX.md` open list over a standalone gates doc; flag at human review if a dedicated `decisions/gates.md` is preferred instead.
- **C3 channel:** the sweep summary defaults to `Log::info` on the app log; revisit the channel/structured-logging shape when the observability gate (now tracked by C15) is actually decided.
