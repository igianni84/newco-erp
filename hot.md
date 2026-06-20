---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph loop — `operator-console-catalog-spine` task 3.2 Product Reference console DONE; 6/10).** The **second hierarchical** spine console, again PURE reuse of the kit (zero base/kit/boundary change): `ProductReferenceResource` + `List`/`View`/`Create` pages + `operator_console.product_reference.*` EN/IT + two test files. New vs 3.1: **TWO** parent pickers (Variant + Format) and the change's two standouts — (1) the **PR duplicate create-error**: a dup `(variant,format)` throws `UniqueConstraintViolationException` which (trap!) **IS a RuntimeException**, so the Create page catches it INSIDE `createViaAction` and re-throws `ValidationException` mapping the **one console-owned key** `operator_console.product_reference.duplicate_reference` (colon-free!) to `data.product_variant_id` — never raw SQL (design L5). (2) the **retire reference-integrity block** surfaces for free for BOTH active Sellable SKU AND active Composite SKU constituent. View page = exact Case-Config shape (5 invocations, no override). Binds NO producer.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 1065/1065 SQLite (5702 assertions) — +19 vs the 1046 baseline (the two new PR files).** phpstan 0; pint clean. **PG17 ✓:** `tests/Feature/Modules/OperatorPanel` 145/145 (1056) on docker `postgres:17` (126 baseline + 19 new). composer.json/lock diff vs main empty; no migrations; no protected files (only app code under `Filament/` + lang + tests).
- **Run-cmd gotcha:** the FULL suite OOMs under bare `php artisan test` (128M). Run `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **ACTIVE:** `operator-console-catalog-spine` — 6/10 tasks done (`APPROVED` present).
- **Next: task 3.3** — **Sellable SKU console** — same two-parent recipe as 3.2 (PR + Case Configuration pickers + commercial name / marketing-copy fields) → `CreateSellableSku::handle(productReferenceId, caseConfigurationId, commercialName, marketingCopy?)`. Cascade gate = PR **and** Case Config active. **NO create guard** (a PR+CaseConfig pair may back multiple SKUs — assert two SKUs over the same pair both succeed; do NOT add uniqueness). It is a **LEAF** — retire has NO within-catalog block (no reference-integrity test). Event names are SKU **UPPER-case** (`SellableSKUCreated`/`Activated`/`Retired`). **PG17 task.** Then 4.1 Composite (ordered N≥2 picker + `<2` form-error, a localized RuntimeException → base catch, NO framework catch) → 5.1 i18n scan → 5.2 close.

## Blockers & Decisions Needed
- None. **`main` is LOCAL-ONLY — not pushed.** Humans push; the loop only commits locally.

## Open Patterns
- **READ `progress.md` `## Codebase Patterns` before 3.3** — kit pieces + hierarchical recipe + (new on 3.2) the **PR create-error special case** (`UniqueConstraintViolationException` IS a RuntimeException → catch in `createViaAction`, convert to `ValidationException`; key colon-free), the **form-error MESSAGE assertion idiom** (`assertHasFormErrors(['field' => __(key)])`), and the **BOTH-edges PR retire block** scaffold.
- **Each spine entity = `<Entity>Resource` + `List`/`View`/`Create` pages + `operator_console.<entity>.*` EN/IT + `<Entity>ResourceTest`(RefreshDatabase) + `<Entity>LifecycleConsoleTest`(DatabaseMigrations).** Hierarchical entities add parent picker(s) + the cascade-gate-blocked activate test; Composite (4.1) adds the ordered picker + the `<2` localized-RuntimeException create-error (base catch). 3.3 Sellable is a LEAF: two pickers, no create guard, no retire block.
