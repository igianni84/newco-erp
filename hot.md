---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 3) — `substrate-hardening` 1.3 (C2) DONE, 3/17. Section 1 complete (3/3).** Bounded the `events:sweep` overlap mutex to a 2-min lease: `routes/console.php:16` `->withoutOverlapping()` → `->withoutOverlapping(2)`. Vendor-verified: `withoutOverlapping($expiresAt = 1440)` sets public `Event::$expiresAt` (default 24h); `CacheEventMutex` lock TTL = `expiresAt * 60` s, so `2` = 120 s. A crashed sweep self-heals in ~2 min instead of stalling every 30 s tick for the 24h default. Updated the inline comment + `SweepCommand` docblock describing the guard. Test pin `+->and($sweep->expiresAt)->toBe(2)` on the schedule test, RED ("1440 != 2") → GREEN. Pure config, engine-agnostic.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump is the NEXT task, 2.1.)
- **`ralph/substrate-hardening`**: suite **248/248** on SQLite (878 asserts) AND PostgreSQL 17 (877 — the one pre-existing engine-guarded delta) · phpstan 0 @ max · pint clean · `openspec validate substrate-hardening --strict` valid.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 3/17). On branch `ralph/substrate-hardening`.
- **NEXT = task 2.1 — C4 raise the PHP floor to 8.5** (design D5). `composer.json:9` `"php": "^8.3"` → `"^8.5"`, then `composer update --lock` to re-sync the lock content-hash (NO package upgrades — verify the diff is lock-hash-only). `tests/Feature/PlatformRequirementsTest.php:7-9`: `80400` → `80500`, description `>= 8.4` → `>= 8.5`. VERIFY-ONLY: `.github/workflows/ci.yml` + `CiWorkflowTest:36` already pin/assert 8.5. Run that requirement test green; `composer validate` clean. (DevelopmentDocsTest pins neither → safe.)
- Then 2.2/2.3 config (.env sweep tunables · pgsql tz=UTC) · 3.x test gaps (uuidv7 · backoff cap · actor_role CHECK · combined immutable UPDATE) · 4.1 CI concurrency · 5.x docs (GUIDE/dev/README/INDEX) · 6.x cross-engine+validate+traceability.

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). C15 (task 5.4) will register four currently-untracked gates: secrets · observability · PCI boundary · security review.

## Open Patterns
- **PG verification:** local `docker run postgres:17` (recipe `knowledge/testing/rules.md:9-13`, port 55432) per DB-touching task; five portability traps in rules.md. CI `tests-pgsql` is the standing gate. (1.3 engine-agnostic, still PG-verified per the acceptance.)
- **Three consolidated patterns in `progress.md` Codebase Patterns** (read first each iteration): white-box concurrency-guard test via reflection; query-builder UPDATE skips casts + returns affected-row count; PHPStan-max-clean `Log::spy()` assertion (closure-as-2nd-arg context matcher, `is_string` not `(string)`).
- **Doc-pin map (design.md D-notes):** edits to docs/CI have pinning tests — `DevelopmentDocsTest` (RALPH_EFFORT token + locked versions), `FoundationsDocsTest` (GUIDE F1 line), `CiWorkflowTest` (php 8.5 + gate order + concurrency), `PlatformRequirementsTest` (≥8.5). Update the pin in the SAME task.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
- **Archived patterns:** `openspec/changes/archive/2026-06-13-foundations-money-i18n-flags/progress.md` (12 patterns: VOs, casts, enums, lang/, Pennant, ActorContext, doc-pins).
