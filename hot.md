---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-11
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-11 12:27 (ralph iteration — task 2.4 green)** — Ran all five `CLAUDE.md` Quality Commands verbatim in table order, all green (format → test_filter → test → type_check → lint). Recorded the **canonical installed-version snapshot** in `progress.md` (exact patch versions, from `composer show` resolved output). Added `tests/Feature/PlatformRequirementsTest.php` — executable guards for the CLAUDE.md tech-stack **floors** (PHP ≥ 8.4 via `PHP_VERSION_ID`; Laravel ^13 via `app()->version()` ∈ [13.0.0, 14.0.0)). Floor checks, not exact pins — patch/minor bumps stay green; a drop below baseline fails loudly. No `composer require`, no lock churn.

## Build & Quality Status
- **Canonical version snapshot (task 2.4):** PHP **8.5.2** · Composer **2.9.2** · Laravel (laravel/framework) **13.15.0** · Filament **n/a** (installed in 3.1) · Pest (pestphp/pest) **4.7.2** (+ pest-plugin-laravel **4.1.0**; phpunit/phpunit ^12.5.12 coexists in require-dev) · PHPStan (phpstan/phpstan) **2.2.2** · Larastan (larastan/larastan) **3.10.0** · Pint (laravel/pint) **1.29.1**. SQLite dev DB; tests on sqlite `:memory:`.
- Quality loop (last run, 12:27): format ✅ · test_filter (2 tests) ✅ · full test **13/13 (25 assertions)** ✅ · type_check **✅ 0 errors @ level max** (bare `vendor/bin/phpstan analyse`) · lint ✅ · `openspec validate --strict` ✅.
- **PHPStan memory gotcha (SOLVED, committed):** this host's Homebrew CLI `memory_limit`=128M OOMs the bare phpstan (Larastan reflects the whole framework). Fix = `phpstan-bootstrap.php` (via `parameters.bootstrapFiles`) does `ini_set('memory_limit','1G')` — loads in main AND parallel workers (`CommandHelper::begin`), so NO `--memory-limit` flag needed (bare cmd, `composer analyse`, CI all covered). Leaves `-1` (CI default) untouched. PHPStan cache → system temp, nothing to gitignore.
- Quality-command output is shell-wrapped JSON (`{"tool":"phpstan","result":"passed","errors":0}` etc.), not vanilla CLI output.
- CI: not configured yet (task 3.3). Guardrails live (60/60 hook tests green). OpenSpec CLI 1.4.1 has no `verify` command.

## Active Change & Next Task
- `openspec/changes/bootstrap-laravel-app/` — APPROVED, strict-valid, **6/10 tasks done** (1.1 ✅ 1.2 ✅ 2.1 ✅ 2.2 ✅ 2.3 ✅ 2.4 ✅).
- **Next task: 3.1** — Filament **5.x**: `composer require filament/filament:"^5.0" --no-interaction`; panel id `admin` at `/admin`; `OperatorSeeder` reading `OPERATOR_NAME`/`OPERATOR_EMAIL`/`OPERATOR_PASSWORD` from env; Pest feature tests (unauth `GET /admin` → redirect to panel login; seeded operator authenticates + reaches dashboard, via Filament testing helpers `livewire()`/`actingAs`, user factory). Acceptance: `composer show filament/filament` reports 5.x.
- Then: 3.2 Boost (`--dev`, `boost:install`, AGENTS.md) → 3.3 CI (`.github/workflows/ci.yml`) → 3.4 docs (`docs/development.md` + INDEX).
- Branch: `ralph/bootstrap-laravel-app`. Pinned per ADR 2026-06-11: laravel ^13.0, filament ^5.0, boost --dev.

## Blockers & Decisions Needed
- None active for the bootstrap change.
- Open ADR gates (none block bootstrap): production DB engine, identity/auth, queue driver, event substrate, audit store, object storage, EU hosting, frontend stack (TanStack direction in `.claude/memory/frontend-stack-direction.md`).
- External sandbox credentials (Airwallex/Xero/HubSpot) needed before F6 changes — human-procured.
- Filament Blueprint (premium): not adopted; Giovanni's purchase decision.

## Open Patterns
- **3.1 baseline to preserve:** suite = **13 tests / 25 assertions**; phpstan = **0 errors @ level max**. PHPStan level max is strict — Filament's generated `AdminPanelProvider` + `OperatorSeeder` must be type-clean. Narrow `mixed`; never suppress (no baseline / `@phpstan-ignore` / inline `@var` / silencing casts). If generated code can't reach max, escape = narrow `excludePaths` (NOT a baseline) or step level down — documented decision at that gate.
- **`QualityToolingTest` (5 pins: format/lint/analyse/test/preset) must stay green through 3.1 (Filament) + 3.2 (Boost)** — both run `composer require` and can rewrite `composer.json`; re-run after each.
- **Versions:** read resolved versions from `composer show <pkg>` flat-list (`name version desc`), NOT the `versions :` line (prints the constraint `*`). `composer show` takes one package at a time.
- Pest 4.x: `--filter` is regex over the test *description* — pick a substring free of metachars (`(`, `^`, `>=`). Suite is fully Pest-native; new tests use `use function Pest\Laravel\get;`. Per-file `RefreshDatabase` via `uses(...)`.
- `OPERATOR_*` env contract is defined — task 3.1's `OperatorSeeder` must read exactly those names.
- Root `.gitignore` = curated union; new tooling ignores go in the bottom "Laravel skeleton defaults" section.
- Full list: `openspec/changes/bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
