---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph loop — `operator-console-catalog-spine` task 3.3 Sellable SKU console DONE; 7/10).** The **third hierarchical** spine console — the commercial unit (PR + Case Config + commercial attributes) — again PURE reuse of the kit (zero base/kit/boundary change): `SellableSkuResource` + `List`/`View`/`Create` pages + `operator_console.sellable_sku.*` EN/IT + two test files. It is the LEAF two-parent recipe: TWO parent pickers (PR + Case Config) + `commercial_name` (req) / `marketing_copy` (opt, a PLAIN string — NOT TranslatableText). The two divergences from PR: (1) **NO create guard / NO duplicate** — a PR+CaseConfig pair backs many SKUs (BR-SKU-1), so a dedicated test asserts **two SKUs over the same pair both succeed**; (2) **NO retire reference-integrity block** — it is a Module-0 LEAF, retire from `active` just succeeds. Cascade gate (PR **and** Case Config active) + SoD + from-state all surface FOR FREE. Two gotchas captured: the create-PAGE/create-ACTION **name collision** (alias `CreateSellableSku as CreateSellableSkuAction`), and the **combined-relation label nullsafe** (bind-local + `=== null` ternary — BOTH `?->…??` and plain `->` fail phpstan-max).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 1082/1082 SQLite (5852 assertions) — +17 vs the 1065 baseline (the two new SKU files).** phpstan 0; pint clean. **PG17 ✓:** `tests/Feature/Modules/OperatorPanel` 162/162 (1206) on docker `postgres:17` (145 baseline + 17 new). composer.json/lock diff vs main empty; no migrations; no protected files (only app code under `Filament/` + lang + tests).
- **Run-cmd gotcha:** the FULL suite OOMs under bare `php artisan test` (128M). Run `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **ACTIVE:** `operator-console-catalog-spine` — 7/10 tasks done (`APPROVED` present).
- **Next: task 4.1** — **Composite SKU console** — the FINAL spine entity (N-constituent cascade). New: an **ordered N≥2 Product-Reference picker** (verify the Filament 5 ordered-multi-input API in `vendor/`) → `CreateCompositeSku::handle(productReferenceIds)` (`list<int>`, producer-AGNOSTIC); **the `<2 distinct` rejection is a localized `InsufficientCompositeConstituents` (RuntimeException) → the BASE create catch → `data.<createRejectionField()>` form error** (NO PR-style framework catch — it's a domain RuntimeException, map `$e->getMessage()`); activate gate = EVERY constituent PR active. Model bundles PRs via the `constituents` junction (`catalog_composite_sku_constituents`, ordered by `position`), references NO Case Config. Events `CompositeSKU*` (SKU UPPER), entity_type `'CompositeSku'`. **PG17 task.** Then 5.1 i18n scan → 5.2 full-spine chain + close.

## Blockers & Decisions Needed
- None. **`main` is LOCAL-ONLY — not pushed.** Humans push; the loop only commits locally.

## Open Patterns
- **READ `progress.md` `## Codebase Patterns` before 4.1** — kit pieces + hierarchical recipe + (new on 3.3) the **LEAF two-parent recipe**, the **page/action name-collision alias**, and the **combined-relation label nullsafe** (bind-local + `=== null` ternary; the picker needs a `static function (…): array {…}` block, not a one-expression `fn`).
- **Each spine entity = `<Entity>Resource` + `List`/`View`/`Create` pages + `operator_console.<entity>.*` EN/IT + `<Entity>ResourceTest`(RefreshDatabase) + `<Entity>LifecycleConsoleTest`(DatabaseMigrations).** 4.1 Composite adds the ordered N≥2 picker + the `<2` localized-RuntimeException create-error (BASE catch, NO framework catch) + producer-agnostic; activate gate = every constituent active.
