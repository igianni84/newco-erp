---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph loop — `operator-console-catalog-spine` task 4.1 Composite SKU console DONE; 8/10).** The **FINAL spine** console — the N-constituent bundle, the spine's only M:N entity — again PURE reuse of the kit (zero base/kit/boundary change): `CompositeSkuResource` + `List`/`View`/`Create` pages + `operator_console.composite_sku.*` EN/IT + two test files. Over the two-parent recipe it adds ONE thing on create: a SINGLE **ordered multi-select** `Select::make('constituents')->multiple()->options(productReferenceOptions())->required()` (NOT a parent FK; no producer picker). **Verified Filament 5:** a plain `multiple()` Select with NO `->relationship()` dehydrates normally + PRESERVES order through `fillForm→$data` (the relationship-only `dehydrated()` at `Select.php:977` doesn't apply; no sorting). `createViaAction` narrows to `list<int>` → `CreateCompositeSkuAction::handle`. The `<2 distinct` rejection is the localized domain `InsufficientCompositeConstituents` (RuntimeException) → the BASE create catch → `data.constituents` form error (NO PR-style framework catch). Producer-AGNOSTIC (2 masters w/ distinct `producer_id` → accepted); LEAF (retire no within-catalog block, like Sellable). Cascade gate (EVERY constituent active) + SoD + from-state surface FOR FREE. **Re-tripped + fixed the FQ-`@see`-to-`Catalog\Exceptions` Pint→boundary trap** (lessons.md 2026-06-20) — name domain Exceptions in prose backticks, never `{@see FQCN}`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 1100/1100 SQLite (6000 assertions) — +18 vs the 1082 baseline (the two new Composite files).** phpstan 0; pint clean. **PG17 ✓:** `tests/Feature/Modules/OperatorPanel` 180/180 (1354) on docker `postgres:17` (162 baseline + 18 new). composer.json/lock diff vs main empty; no migrations; no protected files (only app code under `Filament/` + lang + tests).
- **Run-cmd gotcha:** the FULL suite OOMs under bare `php artisan test` (128M). Run `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **ACTIVE:** `operator-console-catalog-spine` — 8/10 tasks done (`APPROVED` present). All SIX spine consoles now shipped (Format · Case Config · Variant · PR · Sellable SKU · Composite SKU).
- **Next: task 5.1** — **i18n EN + IT for all six consoles + sink-anchored scan** — a **DB-FREE** task (not PG17). Confirm `lang/{en,it}/operator_console.php` carry every key the six resources/pages use; route all six entities' strings through `__('operator_console.<entity>.…')`; run the predecessor's sink-anchored `token_get_all` scan over the new Filament classes (no raw user-facing literal — `__()`/`trans()`/`(string) __()` only); assert `IT ⊆ EN` (`array_diff(Arr::dot($it), Arr::dot($en)) === []`). Then 5.2 full-spine chain test + cross-engine PG17 close + CONTEXT.md `Operator console` term → `<promise>CHANGE_COMPLETE</promise>`.

## Blockers & Decisions Needed
- None. **`main` is LOCAL-ONLY — not pushed.** Humans push; the loop only commits locally.

## Open Patterns
- **READ `progress.md` `## Codebase Patterns` before 5.1** — kit pieces + standalone/hierarchical/leaf/N-constituent recipes + the i18n shape (`operator_console.<entity>.{label,plural_label,columns,fields,actions,affordance,notifications}`; EN baseline, IT per-key w/ label/plural_label omitted → EN fallback; the ONE console-owned key is `product_reference.duplicate_reference`).
- 5.1 reuses the predecessor's i18n test idiom (`Lang::has($k,'it',false)`, `__($k) === trans($k,[],'it')`, the `Arr::dot` IT⊆EN diff) + the sink-anchored token scan. DB-free → SQLite-only, no PG17 run.
