---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-11
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-11 12:00 (ralph iteration 3 — task 2.1 green)** — Pint wired: `pint.json` = `{"preset":"laravel"}` (explicit framework default), `composer format` (`pint`) + `composer lint` (`pint --test`) aliases added mirroring the CLAUDE.md Quality Commands table, and `tests/Feature/QualityToolingTest.php` pins those script keys + the preset against later `composer require` rewrites. Both aliases run green.

## Build & Quality Status
- **App: Laravel 13.15.0 · PHP 8.5.2 · Composer 2.9.2 · Pest 4.7 + pest-plugin-laravel 4.1 · Pint 1.29.1**. (phpunit/phpunit ^12.5.12 coexists in require-dev.) SQLite dev DB; tests on sqlite `:memory:` (phpunit.xml, pinned by `tests/Feature/EnvironmentTest.php`).
- Quality loop (last run, 12:00): format ✅ · test 9/9 (16 assertions) ✅ · lint ✅ (`composer lint` + raw `pint --test` both pass) · type_check **skipped** (Larastan arrives task 2.3). `php artisan test` auto-delegates to Pest.
- **Composer quality aliases:** `format`/`lint` added (2.1); `test` exists from skeleton (confirm in 2.2); `analyse` to come (2.3). Bare-binary form (`"format": "pint"`) — Composer extends PATH with `vendor/bin`.
- Quality-command output is hook-wrapped JSON (`{"tool":"pint","result":"passed"}` / `{"tool":"pest",...}`), not vanilla CLI output.
- CI: not configured yet (task 3.3). Guardrails live (60/60 hook tests green). OpenSpec CLI 1.4.1 has no `verify` command (semantic verify is prompt-based, GUIDE.md §2.7).

## Active Change & Next Task
- `openspec/changes/bootstrap-laravel-app/` — APPROVED, strict-valid, **3/10 tasks done** (1.1 ✅ 1.2 ✅ 2.1 ✅).
- **Next task: 2.2** — Pest confirmed as runner. Install ALREADY DONE (1.2). Remaining: the skeleton already ships a `composer test` script (keep/confirm), verify the `test_filter` command from CLAUDE.md (`php artisan test --filter={name}`) on one named test, and optionally convert the two skeleton ExampleTests (`tests/Unit` + `tests/Feature`, PHPUnit-class style) to Pest. Ensure an example **unit** test + the health feature test green via `php artisan test`.
- Then: 2.3 Larastan (`phpstan.neon`, level ≥8 no baseline, `composer analyse`) → 2.4 versions snapshot → 3.1 Filament 5.x (`/admin`, `OperatorSeeder` reading `OPERATOR_*`) → 3.2 Boost (`--dev`) → 3.3 CI → 3.4 docs.
- Branch: `ralph/bootstrap-laravel-app`. Pinned per ADR 2026-06-11: laravel ^13.0, filament ^5.0, boost --dev.

## Blockers & Decisions Needed
- None active for the bootstrap change.
- Open ADR gates (none block bootstrap): production DB engine, identity/auth, queue driver, event substrate, audit store, object storage, EU hosting, frontend stack (TanStack direction in `.claude/memory/frontend-stack-direction.md`).
- External sandbox credentials (Airwallex/Xero/HubSpot) needed before F6 changes — human-procured.
- Filament Blueprint (premium): not adopted; Giovanni's purchase decision.

## Open Patterns
- **`QualityToolingTest` must stay green through 3.1 (Filament) + 3.2 (Boost)** — both run `composer require` and can rewrite `composer.json`; re-run it after each to confirm the quality scripts survived.
- Pest 4.x: no `php artisan pest:install` — scaffold via `vendor/bin/pest --init` (creates only `tests/Pest.php`). PHPUnit-class tests run unchanged under Pest. Per-file `RefreshDatabase` via `uses(...)`.
- `OPERATOR_*` env contract is defined — task 3.1's `OperatorSeeder` must read exactly those names.
- Root `.gitignore` = curated union; new tooling ignores go in the bottom "Laravel skeleton defaults" section.
- Full list: `openspec/changes/bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
