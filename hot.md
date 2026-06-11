---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-11
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-11 13:15 (ralph iteration — task 3.4 green → CHANGE_COMPLETE)** — **`bootstrap-laravel-app` is DONE: 10/10 tasks.** Final task shipped `docs/development.md` (setup → serve, five Quality Commands verbatim, CI PHP-pin policy, full `./ralph.sh` run/monitor reference incl. `RALPH_EFFORT`/`CLAUDE_FLAGS`, AI-tooling section with `boost:install --guidelines -n` + https://filamentphp.com/docs/llms.txt, exact-version table incl. Boost v2.4.10 with composer.json-verified constraints), updated the `docs/INDEX.md` row, and added `DevelopmentDocsTest` (6 tests) whose **live doc↔composer.lock guard** fails the suite if a `composer update` drifts from the documented snapshot. The change now sits on `ralph/bootstrap-laravel-app` awaiting the **human ritual (GUIDE.md §2.7)**: review branch → merge `--no-ff` → semantic check → `openspec archive bootstrap-laravel-app --yes`. Loop never pushes; CI's first remote run happens on push.

## Build & Quality Status
- **Version snapshot (recorded in docs/development.md + guarded by test):** PHP **8.5.2** · Composer **2.9.2** · Laravel **13.15.0** (`^13.8`) · Filament **v5.6.7** (`^5.0`) + Livewire **v4.3.1** · **Boost v2.4.10** (dev) · Pest **4.7.2** (+plugin-laravel 4.1.0) · PHPStan **2.2.2** · Larastan **3.10.0** · Pint **1.29.1**. SQLite dev DB; tests on sqlite `:memory:`.
- Quality loop (last run, 13:15): format ✅ · test_filter ✅ · full test **36/36 (99 assertions)** ✅ · type_check **0 errors @ level max** ✅ · lint ✅ · `openspec validate --strict` ✅.
- CI workflow committed; not yet run remotely (first run on push).

## Active Change & Next Task
- `openspec/changes/bootstrap-laravel-app/` — **COMPLETE (10/10)**, strict-valid, branch `ralph/bootstrap-laravel-app`. **Next actor: human** (review/merge/verify/archive per GUIDE.md §2.7). No other change is approved/in-flight.
- After archive: next change comes from the Build Workplan via `/spec-to-change`; first Module 0 migration requires the **production-DB-engine ADR** first (open gate).

## Blockers & Decisions Needed
- None for the loop. Human actions pending: branch review/merge + archive; push to trigger first CI run.
- Open ADR gates (pre-module work): production DB engine, identity/auth (owns `User::canAccessPanel()` — current bootstrap stub returns `true`), queue driver, event substrate, audit store, object storage, EU hosting, frontend stack.
- External sandbox credentials (Airwallex/Xero/HubSpot) needed before F6 changes — human-procured.

## Open Patterns
- **Verify-before-write applies to docs too:** constraint strings (`^13.8` not `^13.0`, `^4.7`, `^3.10`) came from composer.json, not memory — same discipline as code identifiers.
- **Doc↔reality guards are cheap:** `DevelopmentDocsTest::lockedPackageVersion()` cross-checks the doc's version table against composer.lock (`ltrim(v)` — lock prefixes are inconsistent), making doc refresh an enforced part of dependency upgrades.
- Write memory files via Edit/Write tools, never Bash heredocs (git-guardrails' unanchored `(rm|mv)…spec` regex matches prose like "platform spec").
- Pin artifacts by executable form (`run: <cmd>`); Boost regen = `php artisan boost:install --guidelines -n`; Filament 5 auth FQCNs `Filament\Auth\Pages\*`.
- Full pattern list: `openspec/changes/bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
