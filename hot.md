---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 9) — `substrate-hardening` 3.3 (C9) DONE, 9/17.** New `tests/Feature/Platform/ActorRoleConstraintTest.php` (`DatabaseMigrations`): 2 engine-guarded tests proving the `actor_role` value-set is a DB CHECK on PG (`domain_events_actor_role_check`/`audit_records_actor_role_check`) but the app cast on SQLite. A raw `DB::table()->insert()` of a complete valid row with out-of-enum `actor_role='intruder'` (bypasses the `ActorRole` cast), wrapped in `DB::transaction()` via local `captureConstraintViolation()` (trap #5). Branch on `DB::getDriverName()`: `pgsql` → `$message` contains the constraint NAME + row absent; `sqlite` → `$message===''` + row present (accept-half, never a vacuous skip). Premise guard (`ActorRole` values `not->toContain('intruder')`) per test. Locally-named row builders (Pest shares one fn namespace). **Test-only — zero prod change** (CHECKs already ship). Non-vacuity: direct PG probe → `valid(system)=ACCEPTED`, `invalid(intruder)=REJECTED [names-CHECK] sqlstate=23514`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` `php ^8.5` ✓.)
- **`ralph/substrate-hardening`**: suite **252/252** on **SQLite (895 asserts) AND PostgreSQL 17** (local docker, 895 === 895) · phpstan 0 @ max · pint --test clean · `openspec validate … --strict` valid.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 9/17). Branch `ralph/substrate-hardening`. Section 1 (3/3) + Section 2 (3/3) DONE; Section 3 now 3/4.
- **NEXT = task 3.4 — C10 combined structural + before/after UPDATE rejected** (design D8). In `tests/Feature/Platform/ImmutabilityTest.php`: seed a valid audit row, then `captureImmutabilityError(fn () => DB::table('audit_records')->where('id',$id)->update(['action'=>'tampered.action','before'=>$redacted,'after'=>$redacted]))`; assert `toContain('immutable')` AND `action` unchanged (`voucher.cancel`). Proves the trigger fires on ANY structural change regardless of before/after — the redaction seam is strictly before/after-only. Reuses the existing savepoint helper → behaviour-only, both engines (no PG-specific surface, but a PG run is cheap insurance; NOT genuinely PG-specific like 3.3 was).
- Then 4.1 CI concurrency · 5.x docs · 6.x cross-engine + validate + traceability.

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Task 5.4/C15 will register 4 more: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Engine-guarded DB-CHECK-constraint test (NEW, consolidated in progress.md):** assert BOTH halves of the SQLite/PG asymmetry, never a skip — `pgsql` throws `QueryException` naming the declared CHECK (assert the NAME, not the SQLSTATE) + row absent; `sqlite` accepts the raw builder insert (cast is the floor) + row present. Savepoint-wrap the bad insert (trap #5); complete well-typed row so the CHECK is the sole cause (verify 23514 on PG); valid-value accept-probe = non-vacuity. Same assert COUNT both lanes.
- **PG verification (DB tasks):** local `docker run postgres:17` port 55432 (recipe + five traps `knowledge/testing/rules.md:9-25`). Readiness WITHOUT shell `sleep`: PHP PDO connect loop paced with in-PHP `usleep` (a bare `php -r` boots <100ms; this run PG accepted on attempt 1). CI `tests-pgsql` is the standing gate.
- **Codebase Patterns in `progress.md`** (read first): engine-guarded CHECK test; white-box concurrency-guard via reflection; query-builder UPDATE skips casts + returns affected-row count; PHPStan-clean `Log::spy()` assertion; connector-applied session-setting pin; uuid-version pin by string index.
- **Doc-pin map:** `DevelopmentDocsTest` (RALPH_EFFORT + versions), `FoundationsDocsTest` (GUIDE F1), `CiWorkflowTest` (php 8.5 + gate order + concurrency), `PlatformRequirementsTest` (≥8.5). Update the pin in the SAME task.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
