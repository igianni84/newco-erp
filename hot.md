---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-14
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-14 (ralph) — `catalog-product-spine` task 3.3 DONE (Product Reference).** The SHAPE CHANGE back to a SINGLE-table spine entity (no per-type attrs) — but the FIRST entity whose identity is a DB-enforced composite UNIQUE. ONE migration `catalog_product_references`: two WITHIN-module FKs (`product_variant_id` cascade-on-delete — the PR is in the Variant's identity subtree; `format_id` restrict/default — Format is a SHARED reference, not an owner), a DB `unique(product_variant_id, format_id)` (BR-Identity-3 two-dimension identity), **NO `case_configuration_id`** (absence guard), `lifecycle_state` + single-source driver-guarded PG CHECK, `version`, `timestampsTz`. All 3 index names short (auto unique-name ~62 chars ≈ PG 63 limit). Model `ProductReference` (two within-module `belongsTo`: variant + format); `ProductReferenceCreated` (PII-free, two dims by id); `CreateProductReference` action — thin like the Variant's, **NO dedup / NO localized exception**: a duplicate pair is rejected by the DB index, surfacing as `UniqueConstraintViolationException`. Factory builds both parents via their factories (recursion-free; no `afterCreating`). **6 of 11 tasks done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/catalog-product-spine`: suite **292/292** (1137 assertions) on SQLite · phpstan **0 @ max** · pint clean · `openspec validate catalog-product-spine --strict` valid · `git diff main -- composer.{json,lock}` empty · `ModuleBoundariesTest` 2/2 (no amendment).
- **PG17 cross-engine VERIFIED this task: 292/292 on `postgres:17`** (driver proof `DRIVER=pgsql SERVER=17.10`). PG17 run stays mandatory for every remaining DB task (4.1, 4.2, 5.3).

## Active Change & Next Task
- **Active change = `catalog-product-spine`** (implementing). 11 tasks, dependency-ordered. 1.1 ✓, 2.1 ✓, 2.2 ✓, 3.1 ✓, 3.2 ✓, 3.3 ✓.
- **NEXT TASK = 4.1 Sellable SKU (Intrinsic)** — single-table, the FIRST entity referencing BOTH a PR and a Case Configuration. Migration `catalog_sellable_skus` (`product_reference_id` FK + `case_configuration_id` FK + commercial attrs e.g. `commercial_name`, `lifecycle_state` + driver-guarded CHECK, `version`, `timestampsTz`). **NO DB unique on identity** (a Variant+Format+CaseConfig may yield many SKUs). Model `SellableSku` (two within-module `belongsTo`: reference + caseConfiguration); factory; `SellableSkuCreated` event; `CreateSellableSku` action (transactional + event, no dedup). Test completes "Packaging does not change the PR": build ONE Variant+Format → one PR; create three Case Configs (loose/OWC6/CARTON12) + three SKUs; assert all three `product_reference_id` EQUAL (the one PR). **Verify on PG17 before done.** Then 4.2 Composite (DB-unique idiom on constituents join + N≥2 + producer-agnostic, design D9) → 5.1 naming-cascade guard → 5.2 docs → 5.3 full-chain integration.

## Implementation landmines (read progress.md Codebase Patterns before every task)
- **Spine template + multi-table (core+per-type 1:1) + single-table + two/single-source CHECK + within-module `belongsTo` + DB-enforced-unique-identity (NEW 3.3) + FK onDelete asymmetry (NEW 3.3) + getColumnListing facade trap + schema-absence guard + localized rejection + fail-closed string guard + 2 phpstan-max traps** all in progress.md Codebase Patterns.
- **DB-enforced unique identity (NEW 3.3, reused 4.2):** single-table identity tuple = a DB `unique([...],'short_name')`; let it throw `UniqueConstraintViolationException` (both engines), NO app dedup / NO localized exception (that's only for CROSS-table identity, the Master). Test via the action's OWN `DB::transaction` (= the savepoint, trap 5): `expect(fn () => action(dupe))->toThrow(UniqueConstraintViolationException::class)` then assert count===1 + no-event-recorded; prove COMPOSITE (same-A+diff-B & diff-A+same-B both succeed).
- **FK onDelete asymmetry (NEW 3.3):** cascade ONLY from the owning parent (PR←Variant←Master); restrict (default) for a shared reference (Format/CaseConfig). Never blanket-cascade.
- **DomainEventRecorder::record(...)** inside open `DB::transaction`; `Module::Catalog->value==='catalog'`; PII-free payload (ids only); actor from `ActorContext` (System default); fetch event with `->sole()`.
- **5 SQLite↔PG traps** (`knowledge/testing/rules.md`): driver-guard CHECK; assert json/TranslatableText BY KEY/through cast; `->sole()`; named test doubles; app/DB-exception throw inside the action's own tx = savepoint-isolated. **Scope guard:** born `draft`, only `*Created` — NO `*Activated`/`*Retired`.

## Blockers & Decisions Needed
- None for this slice (crosses NO open gate). Next slice `catalog-lifecycle-approval` (FSM+approval) needs the Identity/auth ADR — not this one.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **Closing ritual:** `openspec list` + unchecked-task count are truth, not the ralph.sh footer.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB); hot.md ≤550 words.
