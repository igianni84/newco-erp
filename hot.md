---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 5) — `substrate-hardening` 2.2 (C5) DONE, 5/17.** Documented the three `events:sweep` retry tunables in `.env.example` as a COMMENTED block right after `QUEUE_CONNECTION=database` (~:40-47): `# EVENTS_SWEEP_MAX_ATTEMPTS=5`, `# EVENTS_SWEEP_BACKOFF_BASE_SECONDS=30`, `# EVENTS_SWEEP_BACKOFF_CAP_SECONDS=3600` — verbatim the `config/events.php:25,28,31` defaults, plus a 3-line header comment. Kept commented so the env stays on the baked fallbacks. No test (template file). Verified: backed up real `.env`→/tmp, `cp .env.example .env` → `key:generate` (boots) → `config:clear` → `config('events.sweep.*')` read-back = `max=5 base=30 cap=3600` (proves commented keys fall back + env parses) → restored.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` `php ^8.5` ✓.)
- **`ralph/substrate-hardening`**: suite **248/248** on SQLite (878 asserts) · phpstan 0 @ max · pint --test clean · `openspec validate … --strict` valid. (2.2 was a template-file edit, zero test/DB surface → no PG run; `tests-pgsql` CI gate + task 6.1 cover cross-engine.)

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 5/17). On branch `ralph/substrate-hardening`. Section 1 (3/3) done; Section 2 = 2/3.
- **NEXT = task 2.3 — C6 pin the pgsql session timezone to UTC** (design D6). `config/database.php` `pgsql` connection (~:87-100): add `'timezone' => 'UTC',`. Connector issues `SET TIME ZONE 'UTC'`; `timestamptz` read-back unaffected (recorder/immutability tests already assert UTC). SQLite unaffected. **DB-touching → MUST run the PostgreSQL 17 lane** (local docker, `knowledge/testing/rules.md:9-13`), not just SQLite. Optionally assert `config('database.connections.pgsql.timezone') === 'UTC'`.
- Then 3.x test gaps (C7 uuid7 nibble, C8 backoff cap, C9 actor_role CHECK, C10 combined immutability) · 4.1 CI concurrency · 5.x docs · 6.x cross-engine + validate + traceability.

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Task 5.4/C15 will register 4 more: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Verify an `.env.example` edit without nuking dev `.env`:** back up real `.env`→`/tmp` (outside repo), `cp .env.example .env && php artisan key:generate && config:clear`, then a `config()` read-back to confirm commented keys fall back to defaults, then restore + final `config:clear`. Read-back = non-vacuous proof (a typo surfaces as a wrong value).
- **PG verification (DB-touching tasks only — 2.3 next):** local `docker run postgres:17` (recipe + five traps in `knowledge/testing/rules.md`, port 55432). CI `tests-pgsql` is the standing gate.
- **Three patterns in `progress.md` Codebase Patterns** (read first): white-box concurrency-guard via reflection; query-builder UPDATE skips casts + returns affected-row count; PHPStan-clean `Log::spy()` assertion.
- **Doc-pin map:** `DevelopmentDocsTest` (RALPH_EFFORT + versions), `FoundationsDocsTest` (GUIDE F1), `CiWorkflowTest` (php 8.5 + gate order + concurrency), `PlatformRequirementsTest` (≥8.5). Update the pin in the SAME task.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
