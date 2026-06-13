---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 8) — `substrate-hardening` 3.2 (C8) DONE, 8/17.** Pinned the exponential-backoff **ceiling by value** (no prior test did). New test `it('caps the exponential backoff window at the configured ceiling')` in `tests/Feature/Platform/SweepTest.php` (placed between the growth and dead-letter tests): `Config::set('events.sweep.backoff_base_seconds', 2000)` (cap stays 3600, max 5) + frozen clock → attempt 1 window `t0+2000` (uncapped, base·2^0); advance clock to `t1=t0+2000` (row due again); attempt 2 window `t1+min(4000,3600)=t1+3600` (capped). Asserts `equalTo($t1->addSeconds(3600))` + `lessThan(+4000)`. Test-only (cap already ships: `backoffSeconds()` = `min($base*2**($attempts-1), $cap)`, `InlineDeliveryExecutor.php:258`). Non-vacuity by **transient RED**: removed the `min(…,$cap)` → test went RED at the cap assertion (`SweepTest.php:150`), then reverted (git diff empty).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` `php ^8.5` ✓.)
- **`ralph/substrate-hardening`**: suite **250/250** on **SQLite (889 asserts) AND PostgreSQL 17** (local docker, 889 === 889) · phpstan 0 @ max · pint --test clean · `openspec validate … --strict` valid.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 8/17). Branch `ralph/substrate-hardening`. Section 1 (3/3) + Section 2 (3/3) DONE; Section 3 now 2/4.
- **NEXT = task 3.3 — C9 `actor_role` CHECK rejection on PostgreSQL** (design D8; engine-guarded; NEW file). Create `tests/Feature/Platform/ActorRoleConstraintTest.php` (DatabaseMigrations). Insert a fully DB-valid `domain_events` row (and an `audit_records` row) whose `actor_role` is an out-of-enum literal via `DB::table()->insert()` (bypasses the `ActorRole` cast). Guard on `DB::getDriverName()`: `pgsql` → expect `QueryException` (CHECK `domain_events_actor_role_check`/`audit_records_actor_role_check`, migrations `2026_06_12_000001:70-79`/`000002:80-89`); wrap the bad insert in a nested `DB::transaction()` savepoint if asserting row-absence after the throw (trap #5). `sqlite` → raw insert accepted (no DB CHECK; floor there is the enum cast) → assert the row exists, documenting the asymmetry (positive, non-vacuous, never a skip). **PG lane MANDATORY here** (genuinely PG-specific, unlike 3.2).
- Then 3.4 (C10 combined immutability) · 4.1 CI concurrency · 5.x docs · 6.x cross-engine + validate + traceability.

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Task 5.4/C15 will register 4 more: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Driving N successive backoff failures under a frozen clock:** advance test-now to ≥ the previous `available_at` so the row re-qualifies as due (`available_at <= now`); the Nth window is computed against the clock *at that attempt*, so capture `$t1` and assert `$t1->addSeconds(cap)`, never `$t0+…`. (SweepTest.)
- **Non-vacuity for a pin on already-shipping clamp/min-max:** pick inputs so one data point is BELOW the bound and the next AT it (base 2000 → 2000<3600, then 4000>3600⇒3600); the below/at contrast proves the bound engages. Transient prod-edit RED→revert is the gold standard and SAFE in-loop (a forgotten revert fails the new test at quality-step 2, blocking the commit).
- **PG verification (DB tasks):** local `docker run postgres:17` port 55432 (recipe + five traps `knowledge/testing/rules.md:9-25`). Readiness WITHOUT shell `sleep`: PHP PDO connect loop, but **pace it with in-PHP `usleep`** — a bare `php -r` boots <100ms so an unpaced 90-try loop elapses before PG's first-boot initdb (~10-12s) finishes (hit this iter; container WAS fine, loop just too fast). CI `tests-pgsql` is the standing gate.
- **Codebase Patterns in `progress.md`** (read first): white-box concurrency-guard via reflection; query-builder UPDATE skips casts + returns affected-row count; PHPStan-clean `Log::spy()` assertion; connector-applied session-setting pin; uuid-version pin by string index.
- **Doc-pin map:** `DevelopmentDocsTest` (RALPH_EFFORT + versions), `FoundationsDocsTest` (GUIDE F1), `CiWorkflowTest` (php 8.5 + gate order + concurrency), `PlatformRequirementsTest` (≥8.5). Update the pin in the SAME task.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
