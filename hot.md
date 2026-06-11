---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-11
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-11 12:19 (ralph iteration — task 2.3 green)** — Larastan wired. `composer require --dev larastan/larastan` → **Larastan v3.10.0 + PHPStan 2.2.2**. `phpstan.neon` runs at **`level: max`** (highest level, no baseline) over `app`/`database`/`routes`/`tests`. Fixed 6 real level-max type errors in our OWN code (no suppression): removed dead `something()` stub from `tests/Pest.php`; made `QualityToolingTest::composerScript()` type-clean (narrow `json_decode` mixed via `is_array` + `array_filter(...,'is_string')`, not `(string)$mixed`). `composer analyse` alias added; `QualityToolingTest` now a **5-pin** guard (+analyse). **type_check (loop step 4) is now LIVE and green.**

## Build & Quality Status
- **App: Laravel 13.15.0 · PHP 8.5.2 · Composer 2.9.2 · Pest 4.7 (+plugin-laravel 4.1) · Pint 1.29.1 · Larastan v3.10.0 + PHPStan 2.2.2.** (phpunit/phpunit ^12.5.12 coexists in require-dev.) SQLite dev DB; tests on sqlite `:memory:`.
- Quality loop (last run, 12:19): format ✅ · test_filter (1 test) ✅ · full test **11/11 (21 assertions)** ✅ · type_check **✅ 0 errors @ level max** (bare `vendor/bin/phpstan analyse`) · lint ✅ · `composer analyse` ✅ · `openspec validate --strict` ✅.
- **PHPStan memory gotcha (SOLVED, committed):** this host's Homebrew CLI `memory_limit`=128M OOMs the bare phpstan (Larastan reflects the whole framework). Fix = `phpstan-bootstrap.php` (via `parameters.bootstrapFiles`) does `ini_set('memory_limit','1G')` — loads in main AND parallel workers (`CommandHelper::begin`), so NO `--memory-limit` flag needed (bare cmd, `composer analyse`, CI all covered). Leaves `-1` (CI default) untouched. PHPStan cache → system temp, nothing to gitignore.
- Quality-command output is shell-wrapped JSON (`{"tool":"phpstan","result":"passed","errors":0}` etc.), not vanilla CLI output.
- CI: not configured yet (task 3.3). Guardrails live (60/60 hook tests green). OpenSpec CLI 1.4.1 has no `verify` command.

## Active Change & Next Task
- `openspec/changes/bootstrap-laravel-app/` — APPROVED, strict-valid, **5/10 tasks done** (1.1 ✅ 1.2 ✅ 2.1 ✅ 2.2 ✅ 2.3 ✅).
- **Next task: 2.4** — run ALL FIVE Quality Commands from the CLAUDE.md table in order, all green, and record exact installed versions (PHP, Laravel, Filament n/a yet, Pest, **PHPStan/Larastan**, Pint) in `progress.md`. Versions already captured: PHP 8.5.2 · Laravel 13.15.0 · Pest 4.7 · Larastan v3.10.0 / PHPStan 2.2.2 · Pint 1.29.1. Mostly verification + documentation (no new install) — likely fast.
- Then: 3.1 Filament 5.x (`/admin`, `OperatorSeeder` reading `OPERATOR_*`) → 3.2 Boost (`--dev`) → 3.3 CI → 3.4 docs.
- Branch: `ralph/bootstrap-laravel-app`. Pinned per ADR 2026-06-11: laravel ^13.0, filament ^5.0, boost --dev.

## Blockers & Decisions Needed
- None active for the bootstrap change.
- Open ADR gates (none block bootstrap): production DB engine, identity/auth, queue driver, event substrate, audit store, object storage, EU hosting, frontend stack (TanStack direction in `.claude/memory/frontend-stack-direction.md`).
- External sandbox credentials (Airwallex/Xero/HubSpot) needed before F6 changes — human-procured.
- Filament Blueprint (premium): not adopted; Giovanni's purchase decision.

## Open Patterns
- **PHPStan level max is strict — write type-clean code or the loop fails (3.1 Filament provider + OperatorSeeder must pass it).** Narrow `mixed`; never suppress (no baseline / `@phpstan-ignore` / inline `@var` / silencing casts). If Filament-generated code can't reach max, escape = narrow `excludePaths` (NOT a baseline) or step level down — documented decision at that gate.
- **`QualityToolingTest` (5 pins: format/lint/analyse/test/preset) must stay green through 3.1 (Filament) + 3.2 (Boost)** — both run `composer require` and can rewrite `composer.json`; re-run after each.
- Pest 4.x: `--filter` keys off the test *description* string. Suite is fully Pest-native; new tests use `use function Pest\Laravel\get;`. Per-file `RefreshDatabase` via `uses(...)`.
- `OPERATOR_*` env contract is defined — task 3.1's `OperatorSeeder` must read exactly those names.
- Root `.gitignore` = curated union; new tooling ignores go in the bottom "Laravel skeleton defaults" section.
- Full list: `openspec/changes/bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
