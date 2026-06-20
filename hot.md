---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-20 (ralph — `operator-console-catalog-master` task 1.2 green).** The operator-console write-discipline guard landed: a **PHPStan custom rule** (primary option, type-aware) that fails `type_check` on any Eloquent write in the Filament console. Suite 954/954 green; phpstan 0 errors.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **954/954 green** (953 prior + 1 new `NoEloquentWriteInOperatorPanelRuleTest`, 4710 assertions). phpstan 0 errors (rule loaded + clean tree green); pint clean. `composer.json/lock` untouched; no migrations; no protected files touched.
- ⚠ **Run the full suite as `php -d memory_limit=-1 vendor/bin/pest`** (and `… vendor/bin/phpstan analyse`) — bare `php artisan test` OOMs at the box's 128M in `laravel/pao` (false-red). See lessons.md / progress Codebase Patterns.

## Active Change & Next Task
- **Active: `operator-console-catalog-master`** (APPROVED, in progress — **2/11** done).
- **Done 1.2:** `tests/PHPStan/Rules/NoEloquentWriteInOperatorPanelRule.php` (flags save/saveQuietly/update/updateQuietly/delete/forceDelete/create/insert/fill/setAttribute on a `Model` receiver), registered in `phpstan.neon` `services:` scoped to `Modules/OperatorPanel/Filament/`; `RuleTestCase` + fixture prove red→green; also proven via a planted violation in a real Filament class. **Scope = `Filament/` only** so `Operator::save()` (auth model under `Models/`) stays out of scope.
- **Next 1.3:** amend the import-boundary test (`tests/Architecture/ModuleBoundariesTest.php`) to **permit** `App\Modules\OperatorPanel` → `App\Modules\*\Models` (read-binding carve-out, OperatorPanel source only) via an extra `->ignoring()` entry; a planted lateral import (Catalog → `Parties\Models`) must still fail. **Read what it asserts first.** Then **2.1** (`ProductMasterResource` read surface — first PG17 task).

## Blockers & Decisions Needed
- None. `openspec validate operator-console-catalog-master --strict` green; on branch `ralph/operator-console-catalog-master`.

## Open Patterns
- **Operator-console (ADR 2026-06-19):** resources read-bind module models read-only; every write `app(<Action>)->handle()`. 1.2 now enforces the no-write half in CI (PHPStan rule). 1.3 carves the read half into the import-boundary test.
- **Repo-local PHPStan custom rules** are viable here despite the phar (RuleTestCase autoloads; rule lives under dev-autoloaded `tests/` → no composer change; scope via constructor path-needle; `excludePaths` the fixture). See progress.md Codebase Patterns.
- Filament 5 write signatures (`handleRecordCreation`, `Filament\Actions\Action`) still to verify in `vendor/` before the create/lifecycle write tasks 3.1–5.2 (lessons.md).
