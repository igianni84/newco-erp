---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph loop — `operator-console-catalog-spine` task 3.1 Product Variant console DONE; 5/10).** The **first hierarchical** spine console shipped as **PURE reuse** of the operator-console kit (zero base/kit change, zero boundary amendment): `ProductVariantResource` + `List`/`View`/`Create` pages + `operator_console.product_variant.*` EN/IT + two test files. New vs the two standalone consoles: a required **Product Master picker** on the create form (`productMasterOptions()`, mirrors Master's `producerOptions()`) and the **activation-cascade gate surfaced for free** — `ActivateProductVariant` throws `ActivationCascadeViolation` (extends `RuntimeException`) when the parent Master isn't `active`, the kit's `surfaceLifecycleOutcome` catches it → `action_failed` danger title (body `catalog.gate.parent_not_active`). View page = exact Case-Config shape (5 invocations, no `getHeaderActions()` override, no cascade-retire). Binds **NO producer** (pinned by `assertFormFieldDoesNotExist('producer_id')`).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 1046/1046 SQLite (5544 assertions) — +17 vs the 1029 baseline (the two new Variant files).** phpstan 0; pint clean. **PG17 ✓:** `tests/Feature/Modules/OperatorPanel` 126/126 (898) on docker `postgres:17` (109 baseline + 17 new). composer.json/lock diff vs main empty; no migrations; no protected files (only app code under `Filament/` + lang + tests).
- **Run-cmd gotcha:** the FULL suite OOMs under bare `php artisan test` (128M). Run `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **ACTIVE:** `operator-console-catalog-spine` — 5/10 tasks done (`APPROVED` present).
- **Next: task 3.2** — **Product Reference console** — same hierarchical recipe as 3.1 but with **TWO** parent pickers (Variant + Format), PLUS two specials: (1) the **PR duplicate** create-error — `CreateProductReference::handle(productVariantId, formatId)` has NO app dedup; a dup `(variant,format)` throws `Illuminate\Database\UniqueConstraintViolationException` (a framework class, not a domain RuntimeException → it sails past the base catch). The Create page must catch it **specifically** and throw a `ValidationException` mapping a **console-owned** key `operator_console.product_reference.duplicate_reference` to `data.<field>` (design L5 — NEVER render the raw `$e->getMessage()` SQL). This is the ONE console-owned i18n key the whole change adds. (2) the **retire reference-integrity block** (2.2 scaffold: `SellableSku::factory()->create(['product_reference_id' => $pr->id, 'lifecycle_state' => Active])` + a `CompositeSku::factory()->hasAttached($pr, ['position' => 1], 'constituents')` constituent referencer → blocked retire surfaced as `action_failed`). Activate surfaces the cascade gate (PR ← Variant **and** Format active). **PG17 task.** Then 3.3 Sellable → 4.1 Composite → 5.1 i18n → 5.2 close.

## Blockers & Decisions Needed
- None. **`main` is LOCAL-ONLY — not pushed.** Humans push; the loop only commits locally.

## Open Patterns
- **READ the change's `progress.md` `## Codebase Patterns` before 3.2** — it now carries the five kit pieces + the create-template + PR-special-case + the Filament gotchas + the 2.2 reference-integrity retire-block scaffold + (new on 3.1) the **hierarchical-entity recipe** (parent picker via a `<parent>Options()` helper rendering `lifecycle_state->value`; cascade gate surfaced for free; the gate-blocked-activate test with a factory-non-active parent + 3 distinct actors; the active-child-preserving single-entity retire test; the `wineAttributes` `?->update`/local-bind phpstan gotchas).
- **Each spine entity = `<Entity>Resource` + `List`/`View`/`Create` pages + `operator_console.<entity>.*` EN/IT + `<Entity>ResourceTest`(RefreshDatabase) + `<Entity>LifecycleConsoleTest`(DatabaseMigrations).** Hierarchical entities (3.1 done, 3.2–4.1) add a parent picker on create + the cascade-gate-blocked activate test; PR (3.2) + Composite (4.1) add their create form-error catch; entities with a retire reference-integrity block (PR 3.2) add that test.
