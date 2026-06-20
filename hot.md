---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph loop — `operator-console-catalog-spine` task 1.1 DONE; 1/10).** Extracted the shared **operator-console kit** (resolving the predecessor's design-L9 deferral; ADR 2026-06-20) and retrofitted Product Master's view page onto it — behaviour-preserving. New: a `SurfacesDomainActions` trait (the `surfaceLifecycleOutcome` wrapper + a `lifecycleAction` factory + a `recordOf` narrowing primitive) and an abstract `OperatorConsoleViewRecord` base that builds the five uniform lifecycle header actions from a per-entity `lifecycleInvocations()` map. `ViewProductMaster` now extends the base and defines only its Master-only cascade-retire.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 996/996 SQLite (5115 assertions) — unchanged vs the Master-merge baseline (a behaviour-preserving refactor adds no tests).** phpstan 0; pint clean. **PG17 ✓** this task: `tests/Feature/Modules/OperatorPanel` 76/76 on docker `postgres:17`. composer.json/lock diff vs main empty; no migrations; no protected files.
- **Run-cmd gotcha:** the FULL suite OOMs under bare `php artisan test` (128M; child pest ignores a parent `-d`). Run `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **ACTIVE:** `operator-console-catalog-spine` — 1/10 tasks done (`APPROVED` present).
- **Next: task 1.2** — extract the base read-only `OperatorConsoleResource` + base `OperatorConsoleCreateRecord` into `app/Modules/OperatorPanel/Filament/Console/` (alongside the 1.1 trait/base View), and retrofit `ProductMasterResource` + `CreateProductMaster` onto them (Master keeps its producer picker + dedup mapping as extensions). Base CreateRecord's `handleRecordCreation` MUST delegate to a per-entity `createViaAction` (never `new Model; save()` — the 1.2 PHPStan rule proves it). Keep the whole Master suite + arch/boundary green; composer diff empty. **PG17 task.** Then the six entities (2.1 Format → 2.2 Case Config → 3.1 Variant → 3.2 PR → 3.3 Sellable → 4.1 Composite), i18n (5.1), close (5.2).

## Blockers & Decisions Needed
- None for the loop. **`main` is LOCAL-ONLY — not pushed** (the prior Master merge + archive + this change's authoring + now task 1.1's commit are local). Humans push; the loop only commits locally. `ralph/operator-console-catalog-master` branch still present (merged) — delete after push if desired.

## Open Patterns
- **The operator-console kit is now SHIPPED (was only decided).** Home: `app/Modules/OperatorPanel/Filament/Console/` — NOT under `Filament/{Resources,Pages,Widgets}` (those three are Filament's discovery dirs; abstract bases there would be auto-discovered + fail). The six entities each reduce to: a `<Entity>Resource` (1.2 base, pending) + a `View<Entity>` (extends `OperatorConsoleViewRecord`, supplies 5 invocations) + a `Create<Entity>` (1.2 base) + `operator_console.<entity>.*` copy. Full mechanics + the two Filament gotchas (`Schemas\Components\Component`; action `data` is always an array) in the change's `progress.md` → Codebase Patterns.
- **No-shared-interface typing (phpstan max):** per-entity typed invoke-closures `fn (Model $r, string $notes) => app(<Action>::class)->handle($this->recordOf(<Model>::class, $r)[, $notes])`; the kit owns the uniform Action assembly, the subclass owns the one irreducible per-verb line. No boundary amendment needed (design L9 held — the kit catches `RuntimeException` by base type, imports only {Models, Actions} + framework).
