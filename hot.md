---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 11) — `substrate-hardening` 4.1 (C11) DONE, 11/17.** Section 4 (CI) COMPLETE (1/1). Added a workflow-level `concurrency:` block to `.github/workflows/ci.yml`, placed between `permissions:` and `jobs:` (top-level → governs BOTH jobs): `group: ci-${{ github.ref }}` + `cancel-in-progress: true` (+4-line C11/design-D9 rationale comment). Cancels superseded in-flight runs on rapid same-ref pushes — most valuable for the `tests-pgsql` postgres-container lane; `github.ref` keys it per-branch/PR. New `CiWorkflowTest` test asserts the 3 substrings (`concurrency:`, the `ci-${{ github.ref }}` group, `cancel-in-progress: true`) + 2 structure guards (`substr_count('concurrency:')==1`; position before `jobs:`). RED-first confirmed (no block → failed first toContain), then GREEN (5 asserts). YAML cross-parsed clean with ruby/psych. Test+config only — zero app/DB surface.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` `php ^8.5` ✓.)
- **`ralph/substrate-hardening`**: suite **254/254 (904 asserts) on SQLite** · phpstan 0 @ max · pint --test clean · `openspec validate … --strict` valid. **No PG run for 4.1** (zero DB surface — CiWorkflowTest reads the YAML from disk); last full PG parity was 3.4 (253/253, 899===899). Task 6.1 is the cross-engine sweep; the standing `tests-pgsql` CI lane covers PG.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 11/17). Branch `ralph/substrate-hardening`. Sections 1 (3/3), 2 (3/3), 3 (4/4), 4 (1/1) DONE.
- **NEXT = task 5.1 — C12 GUIDE §2.7 local-PostgreSQL verify step** (design D10). `GUIDE.md` §2.7 (~:114-138): insert a local-PG-17 verify step BEFORE the merge step, exact command from `knowledge/testing/rules.md:9-13` (`docker run … postgres:17` → `DB_CONNECTION=pgsql … php artisan test` → `docker rm -f pg`), in Italian to match the guide. Do NOT touch the F1 status line (`FoundationsDocsTest` pins `foundations-money-i18n-flags` / `F1 completata 3/3`). Docs-only; suite stays green, no PG run.
- Then 5.2 (development.md: RALPH_MODEL + `.claude/` correction + PHP floor 8.5) · 5.3 (README exit codes + semantic-verify) · 5.4 (decisions/INDEX 4 gates) · 6.x cross-engine + validate + traceability.

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Task 5.4/C15 will register 4 more in decisions/INDEX.md: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Codebase Patterns in `progress.md`** (read first): engine-guarded CHECK test; white-box concurrency-guard via reflection; query-builder UPDATE skips casts; PHPStan-clean `Log::spy()`; connector-applied session-setting pin; uuid-version pin by string index; **NEW: a string-tested workflow YAML → also parse it for real (ruby/psych; python-yaml & php-yaml NOT installed here) + pin STRUCTURE (count==1, before `jobs:`) for a workflow-level directive, not just presence.**
- **Doc-pin map:** `DevelopmentDocsTest` (RALPH_EFFORT + locked versions, NOT the PHP-floor string), `FoundationsDocsTest` (GUIDE F1 status line), `CiWorkflowTest` (php 8.5 + gate order + concurrency), `PlatformRequirementsTest` (≥8.5). Update the pin in the SAME task.
- **PG verification (DB tasks):** local `docker run postgres:17` port 55432 (recipe + five traps `knowledge/testing/rules.md:9-25`); readiness via in-PHP PDO connect loop. CI `tests-pgsql` is the standing gate.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
