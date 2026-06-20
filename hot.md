---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-20 (ralph — `operator-console-catalog-master` task 1.3 green).** The import-boundary test now encodes the operator-console carve-out: `App\Modules\OperatorPanel` may import each operated module's `Models\*` (read-bind) **and** `Actions\*` (write-through `app(<Action>)->handle()`), scoped OperatorPanel-only. Suite 955/955; phpstan 0.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **955/955 green** (4738 assertions; +1 boundary guard test vs 1.2). phpstan 0 errors; pint clean. `composer.json/lock` untouched; no migrations; only the non-protected `tests/Architecture/ModuleBoundariesTest.php` touched.
- ⚠ **Run the full suite as `php -d memory_limit=-1 vendor/bin/pest`** (and `… phpstan analyse`) — bare `php artisan test` OOMs at 128M in `laravel/pao` (false-red). See lessons.md.

## Active Change & Next Task
- **Active: `operator-console-catalog-master`** (APPROVED, in progress — **3/11** done).
- **Done 1.3:** `ModuleBoundariesTest.php` — extracted helper `moduleBoundaryAllowedImports(Module $source)` (single source of truth); baseline = each other module's `Contracts\*`+`Events\*`; **OperatorPanel-only** also gets `Models\*`+`Actions\*`. Added a guard test pinning the carve-out OperatorPanel-only (never whole-module, lateral peers excluded). Red→green→lateral proven with temp files (all deleted).
- **Next 2.1:** build `ProductMasterResource` (read-projection) + List + View under `app/Modules/OperatorPanel/Filament/Resources/Catalog/`; `$model = \App\Modules\Catalog\Models\ProductMaster::class`; producer column via `\App\Modules\Catalog\Models\ProducerState`; **no** Edit/Delete default action; labels localized (seed the i18n group). **First PG17 task** — seed via `CreateProductMaster` + a `ProducerState` row; run the test on docker `postgres:17` and record it in progress.md.

## Blockers & Decisions Needed
- None. `openspec validate operator-console-catalog-master --strict` green; on branch `ralph/operator-console-catalog-master`.

## Open Patterns
- **Console cross-module surface = exactly {Models, Actions}** (progress.md Codebase Patterns). For 3.1–5.2 keep it tight: **catch domain rejections via base types** (`\Throwable`/`DomainException`/`ValidationException` + `getMessage()`), NOT `use Catalog\Exceptions\…`; **render enums via the cast instance** (`$record->lifecycle_state->…`), NOT `use Catalog\Enums\…`. Then no later task re-amends the 1.3 carve-out.
- **Two console guards now live:** 1.2 PHPStan no-Eloquent-write rule (`tests/PHPStan/`, scoped to `OperatorPanel/Filament/`) + 1.3 boundary carve-out. Read/write discipline = ADR 2026-06-19.
- **PHPStan gotcha (lessons.md):** never chain `expect($list)->toContain()->toContain()->not->toContain()` — degrades to `mixed`/null under larastan; use `in_array(...,true)`+`toBeTrue/False`, one per `expect()`.
- Filament 5 write signatures (`handleRecordCreation`, `Filament\Actions\Action`) still to verify in `vendor/` before create/lifecycle tasks 3.1–5.2 (lessons.md).
