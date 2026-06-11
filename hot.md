---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-11
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-11 10:59 (ralph iteration 2 — task 1.2 green)** — Environments configured: `OPERATOR_NAME`/`OPERATOR_EMAIL`/`OPERATOR_PASSWORD` documented in `.env.example` (placeholders, no secrets), **Pest 4.7 installed** (decision: task text demanded a Pest test, so install moved up from 2.2), `/up` health + `:memory:` environment Pest tests green, fresh-file `php artisan migrate` verified (9 tables).

## Build & Quality Status
- **App: Laravel 13.15.0 · PHP 8.5.2 · Composer 2.9.2 · Pest 4.7 + pest-plugin-laravel 4.1** (coexists with skeleton phpunit ^12.5.12). Pint ^1.27. SQLite dev DB; tests on sqlite `:memory:` (phpunit.xml, pinned by `tests/Feature/EnvironmentTest.php`).
- Quality loop (last run, 10:59): format ✅ · test 6/6 (9 assertions) ✅ · lint ✅ · type_check **skipped** (Larastan arrives task 2.3). `php artisan test` auto-delegates to Pest (hook output now `{"tool":"pest",...}`).
- Quality-command output is hook-wrapped JSON, not vanilla CLI output.
- CI: not configured yet (task 3.3).
- Guardrails live (60/60 hook tests green). OpenSpec CLI 1.4.1 has no `verify` command (semantic verify is prompt-based, GUIDE.md §2.7).

## Active Change & Next Task
- `openspec/changes/bootstrap-laravel-app/` — APPROVED, strict-valid, **2/10 tasks done** (1.1 ✅ 1.2 ✅).
- **Next task: 2.1** — Pint wired: config present (or explicit framework defaults), `composer format` + `composer lint` scripts added, both green. Note: Pint binary already runs green; the task is the composer scripts + config decision (pint.json vs documented defaults — design.md allows either).
- Then: 2.2 Pest confirmation (install ALREADY DONE in 1.2 — remaining: `composer test` script, test_filter verification per CLAUDE.md, optional ExampleTest→Pest conversion) → 2.3 Larastan → 2.4 versions snapshot → 3.1 Filament 5.x → 3.2 Boost → 3.3 CI → 3.4 docs.
- Branch: `ralph/bootstrap-laravel-app`. Pinned per ADR 2026-06-11: laravel ^13.0, filament ^5.0, boost --dev.

## Blockers & Decisions Needed
- None active for the bootstrap change.
- Open ADR gates (unchanged, none block bootstrap): production DB engine, identity/auth, queue driver, event substrate, audit store, object storage, EU hosting, frontend stack (TanStack direction in `.claude/memory/frontend-stack-direction.md`).
- External sandbox credentials (Airwallex/Xero/HubSpot) needed before F6 changes — human-procured.
- Filament Blueprint (premium): not adopted; Giovanni's purchase decision.

## Open Patterns
- Pest 4.x: `php artisan pest:install` does NOT exist — scaffold via `vendor/bin/pest --init` (creates only `tests/Pest.php`). PHPUnit-class tests run unchanged under Pest. Per-file `RefreshDatabase` via `uses(...)`.
- `OPERATOR_*` env contract is defined — task 3.1's `OperatorSeeder` must read exactly those names.
- Root `.gitignore` = curated union; new tooling ignores go in the bottom "Laravel skeleton defaults" section.
- Full list: `openspec/changes/bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
