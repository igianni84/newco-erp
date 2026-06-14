---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-14
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-14 (ralph) — `catalog-product-spine` task 3.2 DONE (Product Variant).** The SECOND multi-table spine entity — the 3.1 shape copied verbatim, minus the Master-specific dedup + fail-closed type guard, PLUS a within-module `belongsTo` to the parent Master. TWO migrations: `catalog_product_variants` (neutral core: single-parent `product_master_id` FK **within module**, type-neutral `variant_identifier`, `lifecycle_state` + SINGLE-source driver-guarded PG CHECK — **no `product_type`** on the Variant, the type is the Master's — `version`, `timestampsTz`) + `catalog_product_variant_wine_attributes` (1:1; `vintage_year` nullable int, `non_vintage` bool default false, `tasting_notes` json via `TranslatableTextCast`; short FK name `catalog_pv_wine_attrs_variant_fk` — auto-name overflows PG 63-char). Models `ProductVariant` (`hasOne` wineAttributes + `belongsTo` master) + `ProductVariantWineAttributes`; `ProductVariantCreated` (core-only PII-free payload, parent Master by id); `CreateProductVariant` (one tx: core + wine via relation + event; NO dedup, NO type guard); factory auto-attaches 1:1 + parent via `ProductMaster::factory()`. **5 of 11 tasks done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/catalog-product-spine`: suite **284/284** (1095 assertions) on SQLite · phpstan **0 @ max** · pint clean · `openspec validate catalog-product-spine --strict` valid · `git diff main -- composer.{json,lock}` empty · `ModuleBoundariesTest` 2/2 (no amendment).
- **PG17 cross-engine VERIFIED this task: 284/284 on `postgres:17`** (driver proof `DRIVER=pgsql SERVER=17.10`). PG17 run stays mandatory for every remaining DB task (3.3–5.3).

## Active Change & Next Task
- **Active change = `catalog-product-spine`** (implementing). 11 tasks, dependency-ordered. 1.1 ✓, 2.1 ✓, 2.2 ✓, 3.1 ✓, 3.2 ✓.
- **NEXT TASK = 3.3 Product Reference** — a SHAPE CHANGE: back to a SINGLE-table entity (no per-type attrs). Migration `catalog_product_references` (TWO within-module FKs `product_variant_id` + `format_id`, **DB unique `(product_variant_id, format_id)`**, **NO `case_configuration_id`** — BR-Identity-3 absence guard, `lifecycle_state` + driver-guarded CHECK, `version`, `timestampsTz`). Model `ProductReference` (two `belongsTo` within module: variant + format). Factory; `ProductReferenceCreated` event; `CreateProductReference` action (transactional + event, NO dedup join). Test: `Schema::hasColumn('catalog_product_references','case_configuration_id')===false`; insert a duplicate `(variant,format)` inside a **nested `DB::transaction`** (savepoint — trap 5, so verify-after-throw survives on PG) and assert the unique violation surfaces; same `(variant,format)` resolves to one PR id. **Verify on PG17 before done.** Then 4.1/4.2 SKUs → 5.1 naming-cascade guard → 5.2 docs → 5.3 full-chain integration.

## Implementation landmines (read progress.md Codebase Patterns before every task)
- **Spine template + multi-table (core+per-type 1:1) + two/single-source CHECK + within-module `belongsTo` + `getColumnListing` facade trap + localized rejection + fail-closed string guard + 2 phpstan-max traps** all consolidated in progress.md Codebase Patterns.
- **within-module `belongsTo` (NEW 3.2, reused 3.3/4.x):** `BelongsTo<Parent, $this>`; `@property-read Parent|null`; in tests resolve via `child->rel()->sole()` (non-null, PHPStan-happy) NOT the nullable dynamic prop; factory FK via `Parent::factory()` (recursion-free). Single scalar FK = structural single-parent (BR-Identity-2); SQLite enforces FKs (`DB_FOREIGN_KEYS` default true).
- **`getColumnListing` facade type-loss (NEW 3.2):** Builder is `list<string>` but the `Schema` FACADE yields `mixed` elements → `array_filter(fn(string))` fails at max. Use `foreach`+`expect()` (mixed-ok) or `sort($cols); expect($cols)->toBe([...alphabetical])` (order-independent, cross-engine). Never `str_contains` a raw element, never cast to silence.
- **2 phpstan traps:** Faker `randomElement()`/`unique()->x()` = `mixed` → use `@method string` providers; chaining MULTIPLE `->not->toContain()` on one `expect()` = `mixed` generic, use one-matcher-per-statement.
- **DomainEventRecorder::record(...)** inside open `DB::transaction`; `Module::Catalog->value==='catalog'`; PII-free payload (ids only); actor from `ActorContext` (System default); fetch event with `->sole()`.
- **5 SQLite↔PG traps** (`knowledge/testing/rules.md`): driver-guard CHECK; assert json/TranslatableText BY KEY/through cast; `->sole()`; named test doubles; app-exception throw inside the action's own tx = savepoint-isolated. **Scope guard:** born `draft`, only `*Created` — NO `*Activated`/`*Retired`.

## Blockers & Decisions Needed
- None for this slice (crosses NO open gate). Next slice `catalog-lifecycle-approval` (FSM+approval) needs the Identity/auth ADR — not this one.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **Closing ritual:** `openspec list` + unchecked-task count are truth, not the ralph.sh footer.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB); hot.md ≤550 words.
