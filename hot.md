---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-14
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-14 (ralph) — `catalog-product-spine` task 4.2 DONE (Composite SKU).** The SEVENTH and LAST spine entity + the spine's FIRST many-to-many. TWO migrations: `catalog_composite_skus` (parent — `lifecycle_state`+PG CHECK+`version`+`timestampsTz` and NOTHING else; §3.8 "cheap at PIM, registration+lifecycle only" — confirmed no commercial attrs via an Explore subagent over PRD §3.8) + `catalog_composite_sku_constituents` (PURE link table — `composite_sku_id` FK **cascade**, `product_reference_id` FK **restrict**, `position`, DB unique `(composite_sku_id, product_reference_id)`; NO surrogate id, NO timestamps; abbreviated index names `catalog_csc_*` — long join name overflows PG's 63-char limit). Model `CompositeSku` (ordered `belongsToMany` `constituents()` with `withPivot('position')->orderByPivot('position')`); event `CompositeSKUCreated` (UPPER-`SKU` verbatim §14.1; `ENTITY_TYPE='CompositeSku'`); `CreateCompositeSku` action (dedupe+order input → **N≥2 over DISTINCT set, PRE-tx** localized rejection → tx: insert `draft` + single keyed `attach()` + record event; **DELIBERATELY no producer check** — design D9/BR-SKU-5). Exception `InsufficientCompositeConstituents` + `catalog.composite_sku.*` lang key. **8 of 11 tasks done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/catalog-product-spine`: suite **312/312** (1211 assertions) on SQLite · phpstan **0 @ max** · pint clean · `openspec validate catalog-product-spine --strict` valid · `git diff main -- composer.{json,lock}` empty · `ModuleBoundariesTest` 2/2 (no amendment).
- **PG17 cross-engine VERIFIED this task: 312/312 on `postgres:17`** (driver proof `DRIVER=pgsql SERVER=17.10`). 5.3 is the final full-suite cross-engine close; 5.1/5.2 add NO DB so no PG run needed there.

## Active Change & Next Task
- **Active change = `catalog-product-spine`** (implementing). 11 tasks. 1.1 ✓, 2.x ✓, 3.x ✓, 4.1 ✓, 4.2 ✓. **ALL 7 spine entities now exist.**
- **NEXT TASK = 5.1 Naming-cascade guard** (design D7; §18 / AC-0-GEN-6) — a CONVENTION/ARCH test, **NO new DB**. `tests/Architecture/CatalogNamingCascadeTest.php`: `class_exists()` the 7 canonical MODELS (`ProductMaster`, `ProductVariant`, `ProductReference`, `Format`, `CaseConfiguration`, `SellableSku`, `CompositeSku`) + the 7 `*Created` EVENTS — **mind the UPPER-`SKU` events `SellableSKUCreated`/`CompositeSKUCreated`** vs lower-`Sku` models (the divergence pattern); assert NO Catalog class OR event name matches `/Wine|BottleReference/` as a structural identifier (scan `app/Modules/Catalog` class names + the `Events/` dir); add wine-display-alias docblocks ("Wine Master"/"Wine Variant"/"Bottle Reference (BR)") to the relevant models (ProductReference already has its BR alias). Then **5.2** (docs — CONTEXT.md spine glossary + the 7-event payload-shape contract note, NO code) → **5.3** (full-chain integration test Master→Variant→Format→Reference→Intrinsic SKU + Composite, all `*Created`/zero `*Activated`/`*Retired`, then the FINAL full-Catalog cross-engine PG17 close).

## Implementation landmines (read progress.md Codebase Patterns before every task)
- **Spine template + multi-table + single-table + M:N-join-table (NEW 4.2) + two/single-source CHECK + within-module belongsTo + belongsToMany-ordered (NEW 4.2) + DB-unique-identity (3.3, reused on the join) + FK onDelete asymmetry + event-vs-model name divergence + spec-fidelity-over-i18n + cross-ROW-count-pre-tx-rejection (NEW 4.2) + producer-agnostic-non-check-as-contract (NEW 4.2) + getColumnListing facade trap + schema-absence guard + localized rejection + fail-closed string guard + 2 phpstan-max traps** — ALL in progress.md Codebase Patterns.
- **5.1 is the divergence-pattern payoff:** the guard MUST `class_exists()` BOTH upper-`SKU` events AND lower-`Sku` models — don't assume event class == model class.
- **DomainEventRecorder::record(...)** inside open `DB::transaction`; `Module::Catalog->value==='catalog'`; PII-free payload (ids only); actor from `ActorContext` (System default); fetch event with `->sole()`.
- **5 SQLite↔PG traps** (`knowledge/testing/rules.md`): driver-guard CHECK; assert json/payload-array BY KEY/order (jsonb reorders OBJECT keys, not ARRAYS — the constituent-id list is order-stable); `->sole()`; named test doubles; app/DB-exception throw inside the action's own tx = savepoint-isolated.

## Blockers & Decisions Needed
- None for this slice (crosses NO open gate). Next slice `catalog-lifecycle-approval` (FSM+approval) needs the Identity/auth ADR — not this one.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **Closing ritual:** `openspec list` + unchecked-task count are truth, not the ralph.sh footer.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB); hot.md ≤550 words.
