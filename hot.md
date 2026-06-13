---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 4) — `substrate-hardening` 2.1 (C4) DONE, 4/17.** Raised the PHP floor to match reality (runtime 8.5.2, CI 8.5, CLAUDE.md "PHP ≥ 8.5"). `composer.json:9` `^8.3`→`^8.5`; `composer update --lock` — verified the lock diff is **exactly** hash + `platform.php` mirror, **zero package upgrades**. `PlatformRequirementsTest.php` `80400`→`80500` + name/comment `>= 8.4`→`>= 8.5`. Also fixed 2 floor comments the bump *falsified* (no test pins them): `ci.yml:2` and the `PlatformRequirementsTest:4` header. The CI `php-version: '8.5'` pins (`ci.yml:34,107`, `CiWorkflowTest:36`) were already 8.5 → VERIFY-ONLY, untouched. Doc floor refs left for 5.2/5.3.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` now `php ^8.5` ✓.)
- **`ralph/substrate-hardening`**: suite **248/248** on SQLite (878 asserts) · phpstan 0 @ max · pint clean · `composer validate` clean · `openspec validate … --strict` valid. (2.1 was non-DB → no PG run; `tests-pgsql` CI gate + task 6.1 cover cross-engine.)

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 4/17). On branch `ralph/substrate-hardening`. Section 1 (3/3) done; Section 2 = 1/3.
- **NEXT = task 2.2 — C5 document the sweep tunables in `.env.example`** (design D6). Commented block after `QUEUE_CONNECTION=database` (~:38) with the three `EVENTS_SWEEP_*` keys VERBATIM from `config/events.php:25,28,31` (MAX_ATTEMPTS=5, BACKOFF_BASE_SECONDS=30, BACKOFF_CAP_SECONDS=3600), kept COMMENTED so the env stays on defaults. No test; verify `cp .env.example .env && php artisan key:generate` boots.
- Then 2.3 (pgsql tz=UTC, DB-touching) · 3.x test gaps · 4.1 CI concurrency · 5.x docs · 6.x cross-engine + validate + traceability.

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Task 5.4/C15 will register 4 more: secrets · observability · PCI boundary · security review.

## Open Patterns
- **`composer update --lock` on a constraint change** touches only `content-hash` + the lock's `platform.*` mirror — `git diff composer.lock` MUST show zero `packages[]` bumps. Composer's "Nothing to modify… / Writing lock file" pairing is normal — trust the diff, not the message.
- **Falsified-comment rule:** a config/dep bump that makes an existing comment factually wrong → fix that comment in the same task (not scope creep); leave unrelated refs to their own tasks.
- **PG verification (DB-touching tasks only):** local `docker run postgres:17` (recipe + five traps in `knowledge/testing/rules.md`, port 55432). CI `tests-pgsql` is the standing gate.
- **Three patterns in `progress.md` Codebase Patterns** (read first): white-box concurrency-guard via reflection; query-builder UPDATE skips casts + returns affected-row count; PHPStan-clean `Log::spy()` assertion.
- **Doc-pin map:** `DevelopmentDocsTest` (RALPH_EFFORT + versions), `FoundationsDocsTest` (GUIDE F1), `CiWorkflowTest` (php 8.5 + gate order + concurrency), `PlatformRequirementsTest` (≥8.5). Update the pin in the SAME task.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
