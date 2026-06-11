---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-11
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-11 12:30 (ralph iteration — task 2.2 green)** — Pest confirmed as the runner. Both skeleton `ExampleTest`s converted to canonical Pest (Unit = `test('that true is true', fn() => expect(true)->toBeTrue())`; Feature = `use function Pest\Laravel\get;` matching `HealthCheckTest`) — suite is now fully Pest-native. `composer test` alias was already skeleton-provided, so it was **confirmed + pinned, not re-added**; `QualityToolingTest` gained a 4th test asserting `scripts.test` survives `composer require`. `test_filter` verified: `php artisan test --filter='that true is true'` → exactly 1 test (matches the Pest *description* string). composer.json untouched.

## Build & Quality Status
- **App: Laravel 13.15.0 · PHP 8.5.2 · Composer 2.9.2 · Pest 4.7 + pest-plugin-laravel 4.1 · Pint 1.29.1**. (phpunit/phpunit ^12.5.12 coexists in require-dev.) SQLite dev DB; tests on sqlite `:memory:` (phpunit.xml, pinned by `tests/Feature/EnvironmentTest.php`).
- Quality loop (last run, 12:30): format ✅ · test_filter (1 test) ✅ · full test **10/10 (18 assertions)** ✅ · lint ✅ (`composer lint` + raw `pint --test`) · `composer test` alias ✅ · type_check **skipped** (Larastan arrives task 2.3). `php artisan test` auto-delegates to Pest.
- **Composer quality aliases:** `format`/`lint` (2.1) + skeleton `test` (confirmed 2.2) all green and pinned by `QualityToolingTest` (4 tests). `analyse` to come (2.3). Bare-binary form (`"format": "pint"`) — Composer extends PATH with `vendor/bin`.
- Quality-command output is hook-wrapped JSON (`{"tool":"pint","result":"passed"}` / `{"tool":"pest","tests":N,...}`), not vanilla CLI output.
- CI: not configured yet (task 3.3). Guardrails live (60/60 hook tests green). OpenSpec CLI 1.4.1 has no `verify` command (semantic verify is prompt-based, GUIDE.md §2.7).

## Active Change & Next Task
- `openspec/changes/bootstrap-laravel-app/` — APPROVED, strict-valid, **4/10 tasks done** (1.1 ✅ 1.2 ✅ 2.1 ✅ 2.2 ✅).
- **Next task: 2.3** — Larastan: `composer require --dev larastan/larastan` (Larastan 3.x for Laravel 13 / PHP 8.5), add `phpstan.neon` at the highest level that passes WITHOUT a baseline (target ≥ 8), add `composer analyse` script (`phpstan analyse`), `vendor/bin/phpstan analyse` green. Then extend `QualityToolingTest` with a 5th pin for `scripts.analyse`. This unblocks quality-loop step 4 (type_check), skipped until now.
- Then: 2.4 versions snapshot → 3.1 Filament 5.x (`/admin`, `OperatorSeeder` reading `OPERATOR_*`) → 3.2 Boost (`--dev`) → 3.3 CI → 3.4 docs.
- Branch: `ralph/bootstrap-laravel-app`. Pinned per ADR 2026-06-11: laravel ^13.0, filament ^5.0, boost --dev.

## Blockers & Decisions Needed
- None active for the bootstrap change.
- Open ADR gates (none block bootstrap): production DB engine, identity/auth, queue driver, event substrate, audit store, object storage, EU hosting, frontend stack (TanStack direction in `.claude/memory/frontend-stack-direction.md`).
- External sandbox credentials (Airwallex/Xero/HubSpot) needed before F6 changes — human-procured.
- Filament Blueprint (premium): not adopted; Giovanni's purchase decision.

## Open Patterns
- **`QualityToolingTest` (4 pins: format/lint/test/preset) must stay green through 3.1 (Filament) + 3.2 (Boost)** — both run `composer require` and can rewrite `composer.json`; re-run it after each. Add a 5th pin (`analyse`) in 2.3.
- Pest 4.x: `--filter` keys off the test *description* (`--filter='that true is true'` → 1 test). No `php artisan pest:install` — scaffold via `vendor/bin/pest --init`. Suite is fully Pest-native; follow the `use function Pest\Laravel\get;` idiom for new tests. Per-file `RefreshDatabase` via `uses(...)`.
- `OPERATOR_*` env contract is defined — task 3.1's `OperatorSeeder` must read exactly those names.
- Root `.gitignore` = curated union; new tooling ignores go in the bottom "Laravel skeleton defaults" section.
- Full list: `openspec/changes/bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
