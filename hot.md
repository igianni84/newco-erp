---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 10) — `substrate-hardening` 3.4 (C10) DONE, 10/17.** Section 3 (test-coverage gaps) COMPLETE (4/4). New test in `tests/Feature/Platform/ImmutabilityTest.php`: `it('rejects a combined structural + before/after UPDATE — a structural edit cannot ride inside a redaction')`, between the redaction-allowed and audit-DELETE tests. Exactly the task payload `update(['action'=>'tampered.action','before'=>$redacted,'after'=>$redacted])` via the savepoint-wrapped `captureImmutabilityError` (trap #5). Asserts `toContain('immutable')` + `action` still `voucher.cancel` (query-builder) + `before`/`after` still ORIGINAL `['email'=>'user@example.com']` NOT redacted (model array cast, trap #3) — the last two are the load-bearing extra over the existing structural-only test, proving the whole statement aborted ATOMICALLY (bundled redaction never landed). **Test-only — zero prod change** (trigger ships from `foundations-domain-events-audit`). Non-vacuity: transient-removed `'action'` from `$auditStructuralColumns` (migration 000004) → exactly 2 RED (existing structural test :116 + this :167, both `'' contains "immutable"`), other 5 green; reverted, `git diff` migration empty.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` `php ^8.5` ✓.)
- **`ralph/substrate-hardening`**: suite **253/253** on **SQLite (899 asserts) AND PostgreSQL 17** (local docker, 899 === 899) · phpstan 0 @ max · pint --test clean · `openspec validate … --strict` valid.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 10/17). Branch `ralph/substrate-hardening`. Sections 1 (3/3), 2 (3/3), 3 (4/4) DONE.
- **NEXT = task 4.1 — C11 workflow concurrency group** (design D9). `.github/workflows/ci.yml`: add a top-level `concurrency:` block AFTER the `permissions:` block (~:21) — `group: ci-${{ github.ref }}` + `cancel-in-progress: true` (one workflow-level block governs both lanes). `tests/Feature/CiWorkflowTest.php`: add a test asserting `ciWorkflow()->toContain('concurrency:')->toContain('cancel-in-progress: true')` and the `ci-${{ github.ref }}` group. CiWorkflowTest reads the file from disk → engine-agnostic, no DB surface (SQLite-green sufficient; no PG run needed).
- Then 5.x docs (GUIDE §2.7 PG step · development.md · README · decisions/INDEX gates) · 6.x cross-engine + validate + traceability.

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Task 5.4/C15 will register 4 more: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Codebase Patterns in `progress.md`** (read first): engine-guarded CHECK test; white-box concurrency-guard via reflection; query-builder UPDATE skips casts + returns affected-row count; PHPStan-clean `Log::spy()` assertion; connector-applied session-setting pin; uuid-version pin by string index. (3.4 added no new pattern — reused the immutability-test idiom + traps #5/#3.)
- **Immutability-test idiom:** savepoint-wrapped `captureImmutabilityError(fn () => …)` + behaviour-only `toContain('immutable')` (never SQLSTATE) → spans both engines; the PG (`IF … IS DISTINCT FROM … RAISE EXCEPTION`) and SQLite (`WHEN … IS NOT … RAISE(ABORT)`) triggers are SEPARATE code paths, so a PG run is real cross-dialect parity, not a re-run. Read jsonb `before`/`after` through the model array cast (trap #3).
- **Non-vacuity for a STRONGER variant of an existing guard test:** prove it by a transient mutation that flips BOTH the old and new test RED in lockstep (here remove `'action'` from migration 000004 `$auditStructuralColumns`); revert + confirm `git diff` empty. Safe in-loop (a forgotten revert fails quality-step 2, blocking the commit).
- **PG verification (DB tasks):** local `docker run postgres:17` port 55432 (recipe + five traps `knowledge/testing/rules.md:9-25`). Readiness via in-PHP PDO connect loop (no shell `sleep`); accepted attempt 1. CI `tests-pgsql` is the standing gate.
- **Doc-pin map:** `DevelopmentDocsTest` (RALPH_EFFORT + versions), `FoundationsDocsTest` (GUIDE F1), `CiWorkflowTest` (php 8.5 + gate order + concurrency), `PlatformRequirementsTest` (≥8.5). Update the pin in the SAME task.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
