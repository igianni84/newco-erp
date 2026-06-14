---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-14
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-14 (ralph) — `catalog-product-spine` task 4.1 DONE (Sellable SKU Intrinsic).** The FIRST entity referencing BOTH a PR and a Case Configuration — single-table template (Format's shape) + two within-module `belongsTo` (the PR's idiom), nothing structurally new. Migration `catalog_sellable_skus`: `product_reference_id` FK **cascade** (the SKU is owned by its PR) + `case_configuration_id` FK **restrict** (Case Config is a shared standalone reference, like Format), §3.7 commercial attrs `commercial_name` (req) + `marketing_copy` (nullable text) as **PLAIN columns** (NOT TranslatableText — §8.1 scopes i18n to Master/Variant/PR, silent on the SKU), `lifecycle_state` + single-source driver-guarded PG CHECK, `version`, `timestampsTz`, **NO DB unique** (many SKUs may share one PR+CaseConfig). Model `SellableSku` (`reference()`+`caseConfiguration()`); event `SellableSKUCreated` (verbatim §14.1 UPPER-`SKU`; `ENTITY_TYPE='SellableSku'`); `CreateSellableSku` action (thin: insert draft + record; no dedup/type-guard/activation-prereq). Factory builds both parents recursion-free. Completes "Packaging does not change the PR" (3 Case Configs → 3 SKUs → all share the one PR). **7 of 11 tasks done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/catalog-product-spine`: suite **300/300** (1171 assertions) on SQLite · phpstan **0 @ max** · pint clean · `openspec validate catalog-product-spine --strict` valid · `git diff main -- composer.{json,lock}` empty · `ModuleBoundariesTest` 2/2 (no amendment).
- **PG17 cross-engine VERIFIED this task: 300/300 on `postgres:17`** (driver proof `DRIVER=pgsql SERVER=17.10`). PG17 run stays mandatory for every remaining DB task (4.2, 5.3).

## Active Change & Next Task
- **Active change = `catalog-product-spine`** (implementing). 11 tasks. 1.1 ✓, 2.1 ✓, 2.2 ✓, 3.1 ✓, 3.2 ✓, 3.3 ✓, 4.1 ✓.
- **NEXT TASK = 4.2 Composite SKU** — the LAST entity + the FIRST join table. TWO migrations: `catalog_composite_skus` (just `lifecycle_state` + `version` + `timestampsTz` — no constituent FK on the parent) + `catalog_composite_sku_constituents` (`composite_sku_id` FK **cascade**, `product_reference_id` FK **restrict**, `position`, **DB unique `(composite_sku_id, product_reference_id)`** — reuse the 3.3 DB-unique idiom, short index name). Model `CompositeSku` (constituents M:N ordered by `position` — a within-module `belongsToMany` or `hasMany` over the join model); event `CompositeSKUCreated` (UPPER-`SKU` again — see landmines); `CreateCompositeSku` action enforcing **N ≥ 2** (a localized rejection like the Master's — an in-action cross-row count, NOT a DB constraint) and **DELIBERATELY NOT** validating producer composition (design D9 / BR-SKU-5 — a multi-producer set is ACCEPTED; never add a single-producer guard — that's Module S). Test: <2 constituents → domain rejection; multi-producer (different `producer_id` on the Masters) ACCEPTED; one PR as constituent of two Composites (M:N) → both valid. **Verify on PG17.** Then 5.1 naming-cascade guard → 5.2 docs → 5.3 full-chain integration + cross-engine close.

## Implementation landmines (read progress.md Codebase Patterns before every task)
- **Spine template + multi-table + single-table + two/single-source CHECK + within-module `belongsTo` + DB-enforced-unique-identity (3.3) + FK onDelete asymmetry (3.3) + event-vs-model name divergence (NEW 4.1) + spec-fidelity-over-i18n (NEW 4.1) + getColumnListing facade trap + schema-absence guard + localized rejection + fail-closed string guard + 2 phpstan-max traps** all in progress.md Codebase Patterns.
- **Event-vs-model name divergence (NEW 4.1, reused 4.2 + 5.1):** the SKU events are `SellableSKUCreated`/`CompositeSKUCreated` (UPPER-`SKU`, verbatim §14.1, `const NAME` too) but the models are `SellableSku`/`CompositeSku` (§18); `ENTITY_TYPE` = model short name (lower-`Sku`), `NAME` = §14.1 (upper-`SKU`). 5.1 must `class_exists()` the upper-`SKU` EVENTS + lower-`Sku` MODELS — don't assume event class == model class.
- **N ≥ 2 for Composite (4.2):** a cross-ROW count rule (count constituents), so it's an in-action localized rejection (like the Master's dedup, NOT a DB unique). The DB unique on the JOIN table is the per-pair (composite, PR) idiom from 3.3.
- **FK onDelete asymmetry (3.3):** cascade from the OWNING parent (a constituent cascades from its CompositeSku); restrict for a shared reference (`product_reference_id`). Never blanket-cascade.
- **DomainEventRecorder::record(...)** inside open `DB::transaction`; `Module::Catalog->value==='catalog'`; PII-free payload (ids only); actor from `ActorContext` (System default); fetch event with `->sole()`.
- **5 SQLite↔PG traps** (`knowledge/testing/rules.md`): driver-guard CHECK; assert json/TranslatableText BY KEY; `->sole()`; named test doubles; app/DB-exception throw inside the action's own tx = savepoint-isolated. **Scope guard:** born `draft`, only `*Created` — NO `*Activated`/`*Retired`.

## Blockers & Decisions Needed
- None for this slice (crosses NO open gate). Next slice `catalog-lifecycle-approval` (FSM+approval) needs the Identity/auth ADR — not this one.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **Closing ritual:** `openspec list` + unchecked-task count are truth, not the ralph.sh footer.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB); hot.md ≤550 words.
