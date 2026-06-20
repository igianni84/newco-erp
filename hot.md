---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph loop — `operator-console-catalog-spine` task 2.1 Format console DONE; 3/10).** First spine entity shipped as **PURE reuse** of the operator-console kit (zero base/kit change, zero boundary amendment): `FormatResource` + `ListFormats`/`ViewFormat`/`CreateFormat` pages + `operator_console.format.*` EN/IT copy + two test files. Format is standalone — no parent gate, **no cascade-retire** (pinned by `assertActionDoesNotExist('retireCascade')`). The kit's "each entity = Resource + 3 pages + i18n + 2 tests" recipe is now demonstrated end-to-end; 2.2–4.1 follow it.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 1011/1011 SQLite (5242 assertions) — +15 vs the 996 baseline (the two new Format files).** phpstan 0 (the `NoEloquentWriteInOperatorPanelRule` analyses the new Format Filament classes — green proves no Eloquent write); pint clean. **PG17 ✓:** `tests/Feature/Modules/OperatorPanel` 91/91 (596) on docker `postgres:17` (76 baseline + 15 new). composer.json/lock diff vs main empty; no migrations; no protected files (only app code under `Filament/` + lang + tests).
- **Run-cmd gotcha:** the FULL suite OOMs under bare `php artisan test` (128M). Run `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **ACTIVE:** `operator-console-catalog-spine` — 3/10 tasks done (`APPROVED` present).
- **Next: task 2.2** — **Case Configuration console** (second standalone entity, no parent gate). Same recipe as Format: `CaseConfigurationResource extends OperatorConsoleResource` read-binding `\App\Modules\Catalog\Models\CaseConfiguration` (columns name/units_per_case/packaging_type + the two helpers); `ListCaseConfigurations`/`ViewCaseConfiguration`/`CreateCaseConfiguration` pages; `CreateCaseConfiguration::handle(name, unitsPerCase, packagingType)` — **NO breakability field**; `operator_console.case_configuration.*` EN+IT. **Extra vs Format:** the reference-integrity retire-block test — retire a Case Config referenced by an `active` Sellable SKU → blocked + surfaced (danger notification), stays `active`, 0 `CaseConfigurationRetired`; then retire once unreferenced → succeeds. Scaffold the blocking SKU via raw Catalog actions (CreateSellableSku + activate chain). **PG17 task.** Then 3.1 Variant → 3.2 PR → 3.3 Sellable → 4.1 Composite → 5.1 i18n → 5.2 close.

## Blockers & Decisions Needed
- None. **`main` is LOCAL-ONLY — not pushed.** Humans push; the loop only commits locally.

## Open Patterns
- **READ the change's `progress.md` `## Codebase Patterns` before 2.2** — it carries all five kit pieces + the create-template + PR-special-case + the Filament gotchas. New tip added on 2.1: **in the read-only Resource, name the create-action in PROSE backticks, not `{@see \App\…\Create<Entity>}`** — Pint's `fully_qualified_strict_types` otherwise imports the unused Action (harmless to the {Models,Actions} boundary but untidy + not auto-pruned). Resource imports the Model only; the Create page imports + `@see`s the Action (it calls it).
- **Each spine entity reduces to:** `<Entity>Resource` (`i18nKey()` + `$model`/`$recordTitleAttribute` + columns + form + infolist + getPages) · `List<Entity>` (header create-LINK) · `View<Entity>` (`i18nKey()` + 5 typed invocations; NO `getHeaderActions()` override for the six → kit's 5 actions, no cascade) · `Create<Entity>` (`createRejectionField()` + `createViaAction()` narrowing) · `operator_console.<entity>.*` EN/IT · `<Entity>ResourceTest` (RefreshDatabase) + `<Entity>LifecycleConsoleTest` (DatabaseMigrations). PR (3.2) + Composite (4.1) add their create form-error catch; entities with a retire reference-integrity block (CaseConfig 2.2, PR 3.2) add that test.
