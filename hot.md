---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 6) — `substrate-hardening` 2.3 (C6) DONE, 6/17.** Added `'timezone' => 'UTC',` to the `pgsql` connection in `config/database.php` (after `search_path`, +3-line rationale comment). Verified `PostgresConnector::configureTimezone()` (`:136-142`) reads the key and issues `SET TIME ZONE 'UTC'` on connect → deterministic `timestamptz` rendering; SQLite has no such key (unaffected). Test in `tests/Feature/EnvironmentTest.php`: config-pin `config('database.connections.pgsql.timezone')==='UTC'` (both lanes) + engine-guarded behavioural read-back `DB::scalar('show time zone')==='UTC'` (PG only). RED→GREEN. Non-vacuous PG proof: filter ran 2 asserts on PG vs 1 on SQLite, and `tinker` printed `driver=pgsql show-time-zone=UTC`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` `php ^8.5` ✓.)
- **`ralph/substrate-hardening`**: suite **249/249** on **SQLite (879 asserts) AND PostgreSQL 17** (local docker) · phpstan 0 @ max · pint --test clean · `openspec validate … --strict` valid.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 6/17). Branch `ralph/substrate-hardening`. Section 1 (3/3) + Section 2 (3/3) DONE.
- **NEXT = task 3.1 — C7 pin `event_id` as UUIDv7** (design D7). In `tests/Feature/Platform/DomainEventRecorderTest.php` envelope read-back (~:114-115): add `->and($read->event_id[14])->toBe('7')` (UUIDv7 version nibble at string index 14; recorder emits `Str::uuid7()` at `DomainEventRecorder.php:83`). Keep the existing `Str::isUuid(...)`. Engine-agnostic → SQLite-green sufficient (no new DB write shape); the standing `tests-pgsql` lane + task 6.1 cover PG.
- Then 3.2 (C8 backoff cap) · 3.3 (C9 actor_role CHECK — new file, engine-guarded) · 3.4 (C10 combined immutability) · 4.1 CI concurrency · 5.x docs · 6.x cross-engine + validate + traceability.

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Task 5.4/C15 will register 4 more: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Connector-applied DB session setting:** prove with a config-pin (`config('database.connections.pgsql.timezone')==='UTC'`, both lanes) + an engine-guarded behavioural read-back (`DB::scalar('show time zone')` on PG). Off-lane stays a positive assertion, never a vacuous skip. Home: `EnvironmentTest.php`.
- **PG verification (DB-touching tasks):** local `docker run postgres:17` (recipe + five portability traps in `knowledge/testing/rules.md`, port 55432). Readiness gate WITHOUT `sleep`: loop a PHP `new PDO('pgsql:…port=55432…')` connect (PHP boot self-paces; bounded ~80 tries) — tests the real TCP path, dodges the postgres-init double-restart false-positive. CI `tests-pgsql` is the standing gate.
- **Three more patterns in `progress.md` Codebase Patterns** (read first): white-box concurrency-guard via reflection; query-builder UPDATE skips casts + returns affected-row count; PHPStan-clean `Log::spy()` assertion.
- **Doc-pin map:** `DevelopmentDocsTest` (RALPH_EFFORT + versions), `FoundationsDocsTest` (GUIDE F1), `CiWorkflowTest` (php 8.5 + gate order + concurrency), `PlatformRequirementsTest` (≥8.5). Update the pin in the SAME task.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
