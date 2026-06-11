---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-11
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-11 10:51 (ralph iteration 1 — task 1.1 green)** — Laravel **13.15.0** installed at repo root via temp-dir merge (create-project → rsync `--ignore-existing` → `.gitignore` union → composer install + key:generate). Zero pre-existing files modified/deleted; only intentional `.gitignore` merge. First loop iteration worked end-to-end (task selection, quality loop, memory updates).

## Build & Quality Status
- **App: Laravel 13.15.0 · PHP 8.5.2 · Composer 2.9.2.** Skeleton ships PHPUnit 12.5 (Pest swap = task 2.2) + Pint ^1.27. SQLite dev DB migrated, drivers cache/queue/session = database.
- Quality loop (last run, 10:51): format ✅ · test 2/2 ✅ · lint ✅ · type_check **skipped** (Larastan arrives task 2.3).
- Quality-command output is hook-wrapped JSON (`{"tool":"pint","result":"passed"}`), not vanilla CLI output.
- CI: not configured yet (task 3.3).
- Guardrails live (60/60 hook tests green): protected-paths hook, git-guardrails, ralph.sh integrity gate. OpenSpec CLI 1.4.1 has no `verify` command (semantic verify is prompt-based, GUIDE.md §2.7).

## Active Change & Next Task
- `openspec/changes/bootstrap-laravel-app/` — APPROVED, strict-valid, **1/10 tasks done** (1.1 ✅).
- **Next task: 1.2** — environments: `.env.example` with SQLite config + documented `OPERATOR_*` seeder vars; tests on sqlite `:memory:`; `php artisan migrate` green on fresh SQLite; feature test for `GET /up` → 200. NOTE: skeleton is PHPUnit — tasks.md says "add a Pest feature test"; decide in 1.2 whether to install Pest there or land the test as PHPUnit and convert in 2.2 (2.2 owns Pest confirmation).
- Then: 2.1 Pint scripts → 2.2 Pest → 2.3 Larastan → 2.4 versions snapshot → 3.1 Filament 5.x → 3.2 Boost → 3.3 CI → 3.4 docs.
- Branch: `ralph/bootstrap-laravel-app`. Pinned per ADR 2026-06-11: laravel ^13.0, filament ^5.0, boost --dev.

## Blockers & Decisions Needed
- None active for the bootstrap change.
- Open ADR gates (unchanged, none block bootstrap): production DB engine, identity/auth, queue driver, event substrate, audit store, object storage, EU hosting, frontend stack (TanStack direction noted in `.claude/memory/frontend-stack-direction.md`).
- External sandbox credentials (Airwallex/Xero/HubSpot) needed before F6 changes — human-procured.
- Filament Blueprint (premium): not adopted; Giovanni's purchase decision.

## Open Patterns
- Laravel 13 skeleton: create-project post-scripts already run key:generate + sqlite touch + migrate in temp dir; rsync carries migrated sqlite + `.env` over (both gitignored).
- Root `.gitignore` = curated union: infra rules on top, "Laravel skeleton defaults" section at bottom; don't duplicate entries subsumed by existing globs.
- See `openspec/changes/bootstrap-laravel-app/progress.md` → `## Codebase Patterns` for the full list.
