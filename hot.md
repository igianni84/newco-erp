---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-14
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-14 (ralph) — `catalog-product-spine` task 3.1 DONE (Product Master).** The FIRST multi-table spine entity + the dedup gate. TWO migrations: `catalog_product_masters` (neutral core: `name`, `product_type` string + `ProductType` cast + driver-guarded CHECK, `producer_id` plain `unsignedBigInteger` **no FK/relation**, `lifecycle_state` + CHECK, `version`, `(producer_id,name)` index) + `catalog_product_master_wine_attributes` (1:1; within-module FK `->constrained(indexName:…)`, `appellation`, `region`, nullable `winery_story` json via `TranslatableTextCast`). Models `ProductMaster` (within-module `hasOne`) + `ProductMasterWineAttributes`; `ProductMasterCreated` (core-only PII-free payload); 2 localized exceptions + `lang/en/catalog.php`; `CreateProductMaster` (fail-closed non-WINE guard → tx: BR-Identity-1 dedup join → core+wine insert + event). **4 of 11 tasks done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/catalog-product-spine`: suite **276/276** (1041 assertions) on SQLite · phpstan **0 @ max** · pint clean · `openspec validate catalog-product-spine --strict` valid · `git diff main -- composer.{json,lock}` empty.
- **PG17 cross-engine VERIFIED this task: 276/276 on `postgres:17`** (driver proof `DRIVER=pgsql / SERVER=17.10`). PG17 run stays mandatory for every remaining DB task (3.2–5.3).

## Active Change & Next Task
- **Active change = `catalog-product-spine`** (implementing). 11 tasks, dependency-ordered. 1.1 ✓, 2.1 ✓, 2.2 ✓, 3.1 ✓.
- **NEXT TASK = 3.2 Product Variant** — the SAME multi-table shape as 3.1; copy 3.1 verbatim. TWO migrations: `catalog_product_variants` (neutral: `product_master_id` FK **within module**, `variant_identifier` type-neutral, `lifecycle_state` + CHECK, `version`, `timestampsTz`) + `catalog_product_variant_wine_attributes` (1:1; `vintage_year` nullable int, `non_vintage` bool, `tasting_notes` json via `TranslatableTextCast`). Models `ProductVariant` (`hasOne` wineAttributes **+ `belongsTo` master within module**) + `ProductVariantWineAttributes`; factory; `ProductVariantCreated` event; `CreateProductVariant` action (transactional + event). **NO dedup, NO fail-closed type guard** (those are Master-specific). Test: assert `Schema::hasColumn('catalog_product_variants','vintage_year')===false` (AC-0-GEN-3); single-parent FK; within-module `belongsTo`/`hasOne` works; event by key. **Verify on PG17 before done.** Then 3.3 Reference (single table + unique `(variant,format)`) → 4.1/4.2 SKUs → 5.1–5.3.

## Implementation landmines (read progress.md Codebase Patterns before every task)
- **Spine template + multi-table (core+per-type 1:1) + two-source CHECK + localized rejection + fail-closed string-boundary guard + 2 phpstan-max scaffolding traps** all consolidated in progress.md Codebase Patterns. The multi-table bullet has the 5 mechanics 3.2 repeats: short explicit FK/index names (PG 63-char limit), typed `hasOne`/`belongsTo` (`$this` declaring model), factory `afterCreating` 1:1 (FK-explicit, recursion-free, NO child factory), action writes child via relation, payload core-only.
- **2 phpstan traps:** Faker `randomElement()`/`unique()->x()` = `mixed` → use `@method string` providers; chaining MULTIPLE `->not->toContain()` on one `expect()` → `mixed` generic, use one-matcher-per-statement.
- **DomainEventRecorder::record(...)** inside open `DB::transaction`; `Module::Catalog->value==='catalog'`; PII-free payload (ids only); actor from `ActorContext` (System default); fetch event with `->sole()`.
- **5 SQLite↔PG traps** (`knowledge/testing/rules.md`): driver-guard CHECK; assert json/TranslatableText BY KEY/through cast; `->sole()`; named test doubles; app-exception throw inside the action's own tx = savepoint-isolated (no 25P02). **Scope guard:** born `draft`, only `*Created` — NO `*Activated`/`*Retired`.

## Blockers & Decisions Needed
- None for this slice (crosses NO open gate). Next slice `catalog-lifecycle-approval` (FSM+approval) needs the Identity/auth ADR — not this one.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **Closing ritual:** `openspec list` + unchecked-task count are truth, not the ralph.sh footer.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB); hot.md ≤550 words.
