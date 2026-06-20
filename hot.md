---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph loop — `operator-console-catalog-spine` task 2.2 Case Configuration console DONE; 4/10).** Second spine entity shipped as **PURE reuse** of the operator-console kit (zero base/kit change, zero boundary amendment): `CaseConfigurationResource` + `ListCaseConfigurations`/`ViewCaseConfiguration`/`CreateCaseConfiguration` pages + `operator_console.case_configuration.*` EN/IT + two test files. Standalone (no parent gate, no cascade — pinned by `assertActionDoesNotExist('retireCascade')`); create form has **NO breakability field** (BR-RefData-2, pinned by `assertFormFieldDoesNotExist`). New vs Format: the **retire reference-integrity block** — a Case Config under an active Sellable SKU can't retire (surfaced as `action_failed` danger notification, stays `active`, 0 `*Retired`), then retires once the SKU is closed.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 1029/1029 SQLite (5393 assertions) — +18 vs the 1011 baseline (the two new Case Config files).** phpstan 0 (the `NoEloquentWriteInOperatorPanelRule` analyses the new Filament classes — green proves no Eloquent write); pint clean. **PG17 ✓:** `tests/Feature/Modules/OperatorPanel` 109/109 (747) on docker `postgres:17` (91 baseline + 18 new). composer.json/lock diff vs main empty; no migrations; no protected files (only app code under `Filament/` + lang + tests).
- **Run-cmd gotcha:** the FULL suite OOMs under bare `php artisan test` (128M). Run `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **ACTIVE:** `operator-console-catalog-spine` — 4/10 tasks done (`APPROVED` present).
- **Next: task 3.1** — **Product Variant console** — the FIRST hierarchical entity (parent-active cascade gate). Same kit recipe PLUS: a **Product Master picker** on the create form (`CreateProductVariant::handle(productMasterId, variantIdentifier, vintageYear?, nonVintage=false, tastingNotes?)` — confirm signature in `app/Modules/Catalog/Actions/`); list/view columns variant identifier + parent Master + vintage/non-vintage + the wine attrs off the `wineAttributes` relation (confirm relation name in `ProductVariant` model); activate surfaces the **cascade gate** (Variant ← Master active) as a danger notification — `catalog.gate.parent_not_active` is already shipped, console re-checks nothing (design L4). **Scaffold** a parent Master `active`/non-active via `ProductMaster::factory()->create(['lifecycle_state' => Active|Reviewed])` for the success/gate-blocked activate paths. **PG17 task.** Then 3.2 PR (dup→form-error + retire-block) → 3.3 Sellable → 4.1 Composite → 5.1 i18n → 5.2 close.

## Blockers & Decisions Needed
- None. **`main` is LOCAL-ONLY — not pushed.** Humans push; the loop only commits locally.

## Open Patterns
- **READ the change's `progress.md` `## Codebase Patterns` before 3.1** — it carries all five kit pieces + the create-template + PR-special-case + the Filament gotchas + (new on 2.2) the **reference-integrity retire-block scaffold** (factory-active blocking referencer — no Create-action chain — `RetirementReferenceIntegrityViolation extends RuntimeException` → kit catches → `action_failed`; domain body already in `catalog.php`, no console key needed). 3.2 (PR) reuses that block shape (SKU by `product_reference_id` + a `CompositeSku::factory()->hasAttached($pr,['position'=>1],'constituents')` constituent referencer).
- **Each spine entity = `<Entity>Resource` + `List`/`View`/`Create` pages + `operator_console.<entity>.*` EN/IT + `<Entity>ResourceTest`(RefreshDatabase) + `<Entity>LifecycleConsoleTest`(DatabaseMigrations).** Hierarchical entities (3.1–4.1) add a parent picker on create + the cascade-gate-blocked activate test; PR (3.2) + Composite (4.1) add their create form-error catch; entities with a retire reference-integrity block (PR 3.2) add that test.
