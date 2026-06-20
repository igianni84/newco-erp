---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph loop — `operator-console-catalog-spine` task 1.2 DONE; 2/10).** The **operator-console kit is now COMPLETE** (design L9 fully resolved): extracted the base read-only `OperatorConsoleResource` + the base write-through `OperatorConsoleCreateRecord` and retrofitted Product Master's Resource + Create page onto them — behaviour-preserving. The kit now has all five pieces (`SurfacesDomainActions` trait, `OperatorConsoleViewRecord`, `OperatorConsoleResource`, `OperatorConsoleCreateRecord`, + per-entity pages). The six spine entities (2.1–4.1) are now **pure reuse**.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 996/996 SQLite (5115 assertions) — unchanged vs the 1.1 baseline (a behaviour-preserving refactor adds no tests).** phpstan 0 (the `NoEloquentWriteInOperatorPanelRule` analyses both new `Console/` bases — green proves the base does NOT fall back to `new Model; save()`); pint clean. **PG17 ✓:** `tests/Feature/Modules/OperatorPanel` 76/76 (469) on docker `postgres:17`. composer.json/lock diff vs main empty; no migrations; no protected files (only app code under `Filament/`).
- **Run-cmd gotcha:** the FULL suite OOMs under bare `php artisan test` (128M; child pest ignores a parent `-d`). Run `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **ACTIVE:** `operator-console-catalog-spine` — 2/10 tasks done (`APPROVED` present).
- **Next: task 2.1** — **Format console** (first pure-reuse spine entity, standalone, no parent gate). Build: `FormatResource extends OperatorConsoleResource` read-binding `\App\Modules\Catalog\Models\Format` (columns: name, sizeLabel, volume + `static::lifecycleStateColumn()`/`versionColumn()`); `ViewFormat extends OperatorConsoleViewRecord` (5 invocations → the `*Format` actions); `CreateFormat extends OperatorConsoleCreateRecord` (`createRejectionField()='name'` + `createViaAction()` → `CreateFormat::handle(name, sizeLabel, volumeMl)`); a `ListFormats` (header create-link, copy from `ListProductMasters`); register pages on the resource; add `operator_console.format.*` EN+IT copy. Tests: `FormatResourceTest` + `FormatLifecycleConsoleTest`. **PG17 task.** No base change expected. Then 2.2 Case Config → 3.1 Variant → 3.2 PR → 3.3 Sellable → 4.1 Composite → 5.1 i18n → 5.2 close.

## Blockers & Decisions Needed
- None for the loop. **`main` is LOCAL-ONLY — not pushed** (prior Master merge + archive + this change's authoring + tasks 1.1/1.2 commits are all local). Humans push; the loop only commits locally. `ralph/operator-console-catalog-master` branch still present (merged) — delete after push if desired.

## Open Patterns
- **Kit COMPLETE — Codebase Patterns (top of the change's `progress.md`) carries all five pieces + the create-template + PR-special-case + the two Filament gotchas (`Schemas\Components\Component`; action `data` always an array). READ IT before 2.1.** Each spine entity reduces to: `<Entity>Resource` (+ `i18nKey()` + columns) · `View<Entity>` (5 invocations) · `Create<Entity>` (`createViaAction` + `createRejectionField`) · `List<Entity>` (create-link) · `operator_console.<entity>.*` copy.
- **Create-rejection surfacing (design L5):** base catch maps a localized domain `RuntimeException`→`data.<createRejectionField()>` (Master dedup, Composite `<2`). **PR (3.2) is the sole special case** — its `createViaAction` catches the framework `UniqueConstraintViolationException` and throws a `ValidationException` (not a RuntimeException → passes the base catch untouched) with a console-owned key, so no raw SQL leaks. No base change needed for it.
- **No-shared-interface typing (phpstan max):** per-entity typed invoke-closures `fn (Model $r, string $notes) => app(<Action>::class)->handle($this->recordOf(<Model>::class, $r)[, $notes])`; lifecycle_state badge via `getAttribute(...) instanceof BackedEnum ? (string) $state->value : ''`. The kit imports only {Models, Actions} + framework (design L9 holds — no boundary amendment).
