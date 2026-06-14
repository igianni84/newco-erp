---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-14
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-14 (ralph) — `catalog-product-spine` task 2.2 DONE (Case Configuration).** Second reference entity, spine template applied verbatim: `catalog_case_configurations` migration (id, name, `units_per_case`, `packaging_type`, `lifecycle_state` + driver-guarded PG CHECK, `version`, `timestampsTz`) **with NO breakability column** + `CaseConfiguration` model + factory + `CaseConfigurationCreated` event + `CreateCaseConfiguration` action. Added the **schema-absence guard** idiom to progress.md Codebase Patterns (hasColumn-false + getColumnListing substring sweep + payload-key absence) — reused by 3.1/3.2/3.3. **3 of 11 tasks done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/catalog-product-spine`: suite **267/267** (962 assertions) on SQLite · phpstan **0 @ max** · pint clean · `openspec validate catalog-product-spine --strict` valid · `git diff main -- composer.{json,lock}` empty.
- **PG17 cross-engine VERIFIED this task: 267/267 on `postgres:17`** (driver proof printed `DRIVER=pgsql` — real PG, not a SQLite fallback). PG17 run stays mandatory for every remaining DB task (3.1–5.3).

## Active Change & Next Task
- **Active change = `catalog-product-spine`** (implementing). 11 tasks, dependency-ordered. 1.1 ✓, 2.1 ✓, 2.2 ✓.
- **NEXT TASK = 3.1 Product Master** — the FIRST multi-table entity + the dedup gate. TWO migrations: `catalog_product_masters` (neutral core: `name`, `product_type` string + driver-guarded CHECK + `ProductType` cast, `producer_id` plain `unsignedBigInteger` **NO FK/relation**, `lifecycle_state`, `version`, `timestampsTz`) + `catalog_product_master_wine_attributes` (1:1; `product_master_id` FK **within module** `->constrained('catalog_product_masters')`; `appellation`; `region`; `winery_story` json via `App\Platform\I18n\TranslatableTextCast`). Models `ProductMaster` (within-module `hasOne` wineAttributes) + `ProductMasterWineAttributes`. `CreateProductMaster` action: in ONE tx run the **dedup check** (non-retired collision on `producer_id + name + appellation` via core⋈wine join → reject with a **localized** reason, invariant 12) THEN insert core+wine + record `ProductMasterCreated`. Reject non-`WINE` `product_type` (fail-closed). Test: assert `Schema::hasColumn('catalog_product_masters','appellation')===false` (neutral-core guard AC-0-GEN-2), resolve `winery_story` TranslatableText through the cast (English fallback), dedup negative path. **Verify on PG17 before done.** Then 3.2 Variant → 3.3 Reference → 4.1/4.2 SKUs → 5.1–5.3.

## Implementation landmines (design D1–D9 + ADR + progress.md Codebase Patterns — read before every task)
- **Spine template proven 2× (2.1/2.2)** — migration+model+factory+event+action shape is in progress.md Codebase Patterns; reuse verbatim. Off-convention factory needs typed `newFactory(): XFactory` (not just `$factory`) or Larastan infers `mixed`. `{@see}` only downward refs; prose for peer/upward (Pint imports FQCN `{@see}`).
- **DomainEventRecorder::record(name, module, actorRole, actorId, entityType, entityId, payload, …)** MUST run inside an open `DB::transaction`. `Module::Catalog->value==='catalog'`. PII-free payloads (ids only); actor from `ActorContext` (System default). Fetch event row with `->sole()`.
- **3.1 NEW vs 2.1/2.2:** first per-type attr table (D1), first `ProductType` enum CHECK+cast (verify the L13 two-source CHECK/enum-cast idiom in vendor), first `TranslatableTextCast` column (assert through cast, never byte-compare jsonb — trap 3), first dedup (localized rejection), first `producer_id` (plain int, **composite stays producer-agnostic** D9). Arch test `tests/Architecture/ModuleBoundariesTest.php` must stay green unamended.
- **5 SQLite↔PG traps** (`knowledge/testing/rules.md`): driver-guard enum CHECK; assert json/TranslatableText BY KEY; `->sole()`; named test doubles; nested `DB::transaction` (savepoint) for verify-after-throw on PG. **Scope guard:** born `draft`, only `*Created` events — NO `*Activated`/`*Retired`.

## Blockers & Decisions Needed
- None for this slice (crosses NO open gate). Next slice `catalog-lifecycle-approval` (FSM+approval) will need the Identity/auth ADR — not this one.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **Spine template + schema-absence guard + house enum style + enum-test convention** all in the change `progress.md` Codebase Patterns — read it before 3.1.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB); hot.md ≤550 words. Closing ritual: `openspec list` + unchecked-task count are truth, not the ralph.sh footer.
