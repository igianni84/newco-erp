---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 7) — `substrate-hardening` 3.1 (C7) DONE, 7/17.** Pinned the public `event_id` as specifically **UUIDv7**: added `->and($read->event_id[14])->toBe('7')` (version nibble at string index 14) to the envelope read-back test in `tests/Feature/Platform/DomainEventRecorderTest.php:116`, right after the kept `Str::isUuid(...)`. Test-only (recorder already emits `(string) Str::uuid7()` at `DomainEventRecorder.php:83`) → GREEN-on-add. Non-vacuity proven directly (no artificial RED): `Str::uuid7()`→index14 `'7'` vs `Str::uuid()` v4→`'4'`, so the pin discriminates v7 from the framework default. PG proof: affected file 48 asserts on PG === 48 on SQLite (assertion unconditional → ran on both) + a direct `uuid`-column round-trip read back byte-identical with `index14=7`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` `php ^8.5` ✓.)
- **`ralph/substrate-hardening`**: suite **249/249** on **SQLite (880 asserts) AND PostgreSQL 17** (local docker) · phpstan 0 @ max · pint --test clean · `openspec validate … --strict` valid.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 7/17). Branch `ralph/substrate-hardening`. Section 1 (3/3) + Section 2 (3/3) DONE; Section 3 opens 1/4.
- **NEXT = task 3.2 — C8 assert the exponential-backoff CAP** (design D7). In `tests/Feature/Platform/SweepTest.php`: with `Config::set('events.sweep.backoff_base_seconds', 2000)` (cap stays 3600, max stays 5) under a frozen clock (`CarbonImmutable::setTestNow`), drive a failing consumer so the 1st failure window is `t+2000` (uncapped — proves the base path) and the 2nd is `t+3600` (capped, since `2000*2=4000>3600`); assert `available_at?->equalTo($t->addSeconds(3600))` at the capped attempt. Engine-agnostic (config + clock; existing `afterEach` resets the clock) → SQLite-green sufficient; standing `tests-pgsql` lane + task 6.1 cover PG.
- Then 3.3 (C9 actor_role CHECK — new file, engine-guarded) · 3.4 (C10 combined immutability) · 4.1 CI concurrency · 5.x docs · 6.x cross-engine + validate + traceability.

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Task 5.4/C15 will register 4 more: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Pinning a generated identifier's format/version** (uuid7/uuid4/ulid): assert the discriminating char by string index, prove non-vacuity by exhibiting the *alternative* value at that index (v4→`'4'`) instead of an artificial RED (a pin on shipping behaviour is GREEN-on-add). If it persists through a typed column, confirm PG preserves the representation (a `uuid` column canonicalises but keeps the version nibble; index 14 survives). Home: `DomainEventRecorderTest.php`.
- **PG verification (DB-touching tasks):** local `docker run postgres:17` (recipe + five portability traps in `knowledge/testing/rules.md`, port 55432). Readiness gate WITHOUT `sleep`: loop a PHP `new PDO('pgsql:…port=55432…')` connect (PHP boot self-paces; bounded ~80 tries) — tests the real TCP path, dodges the postgres-init double-restart false-positive. CI `tests-pgsql` is the standing gate.
- **Four more patterns in `progress.md` Codebase Patterns** (read first): white-box concurrency-guard via reflection; query-builder UPDATE skips casts + returns affected-row count; PHPStan-clean `Log::spy()` assertion; connector-applied session-setting pin (config-pin both lanes + engine-guarded read-back on PG).
- **Doc-pin map:** `DevelopmentDocsTest` (RALPH_EFFORT + versions), `FoundationsDocsTest` (GUIDE F1), `CiWorkflowTest` (php 8.5 + gate order + concurrency), `PlatformRequirementsTest` (≥8.5). Update the pin in the SAME task.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
