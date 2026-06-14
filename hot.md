---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-14
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-14 (ralph) — `catalog-product-spine` task 5.2 DONE (Docs).** Docs-only, DB-free, **no code/test/schema**. Extended root `CONTEXT.md` with a new **Product Catalog (PIM)** section (placed FIRST among domain sections — Module 0 is foundational; the PR is "the universal product key across modules"): 8 glossary terms in the house `**Term**: … _Avoid_:` style — Product Master, Product Variant, Product Reference (PR), Format, Case Configuration, Sellable SKU (one entry, both Intrinsic + Composite shapes), Product Type, Naming cascade — each aliased term carrying its wine-display alias ("Wine Master"/"Wine Variant"/"Bottle Reference (BR)") **and** an `_Avoid_` line marking the alias "never a code/contract name" (the §18 canonical/alias distinction). Plus a **Catalog spine creation events — payload contract** subsection: a 7-row table of every `*Created` event's `name`, `entity_type`, and EXACT payload keys (grounded by reading all seven `Events/*Created.php` `payload()` methods — the 4.1/4.2 ground-it-don't-guess discipline; keys like `size_label`/`volume_ml`/`variant_identifier`/`constituent_product_reference_ids` are NOT guessable). Framed as "the published inter-module contract" so the payload-key table reads as wire-contract, not implementation (the CONTEXT.md "definitions only" preamble left untouched). **10 of 11 tasks done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/catalog-product-spine`: suite **315/315** (1219 assertions, UNCHANGED — docs-only) on SQLite · phpstan **0 @ max** · pint clean · `openspec validate catalog-product-spine --strict` valid · `git diff main -- composer.{json,lock}` empty · `ModuleBoundariesTest` 2/2.
- **No PG run this task** (5.2 adds no schema). **5.3 is the FINAL full-Catalog cross-engine PG17 close.**

## Active Change & Next Task
- **Active change = `catalog-product-spine`** (implementing). 11 tasks. 1.1 ✓, 2.x ✓, 3.x ✓, 4.x ✓, 5.1 ✓, 5.2 ✓. ALL 7 spine entities exist + §18 naming mechanically enforced + spine glossary & event-contract documented.
- **NEXT TASK = 5.3 (FINAL) Full-chain integration + cross-engine close** (AC-0-J-4 creation half). ONE feature test `tests/Feature/Modules/Catalog/SpineCreationChainTest.php` driving the whole spine: Master→Variant→Format→Reference→Intrinsic SKU + a Composite. Assert: each of the SEVEN `*Created` recorded (mind UPPER-`SKU` event names `SellableSKUCreated`/`CompositeSKUCreated`); `where('name','like','%Activated%')->count()===0` AND same for `Retired`; EVERY entity `lifecycle_state===Draft`; payloads PII-free (producer by id only); the dedup rejection AND the producer-agnostic multi-producer Composite both hold in the integrated flow. Then **run the full Catalog suite on local PostgreSQL 17** (`knowledge/testing/rules.md` command block) — SQLite-green is necessary, NEVER sufficient; record the PG run in progress.md. On green + checkbox → ALL 11 done → emit `<promise>CHANGE_COMPLETE</promise>` (do NOT archive/merge — humans do that).

## Implementation landmines (read progress.md Codebase Patterns before every task)
- **Full menu in progress.md Codebase Patterns:** spine DB-entity template · multi-table (neutral core + per-type 1:1) · single-table · M:N join · naming-cascade arch guard · DB-unique-identity vs app-dedup · FK onDelete asymmetry · event-vs-model NAME divergence (UPPER-`SKU`) · spec-fidelity-over-i18n · cross-ROW-count pre-tx rejection · producer-agnostic non-check · getColumnListing facade trap · schema-absence guard · localized rejection · 2 phpstan-max traps.
- **5.3 reuses, adds nothing structural:** build the chain via the within-module factories (`ProductMaster::factory()` → Variant override `product_master_id` → PR override `product_variant_id`+`format_id` → SKU). Fetch events with `->sole()`; assert payload BY KEY (trap 3). The dedup path throws `DuplicateProductMasterIdentity`; the Composite N≥2 throws `InsufficientCompositeConstituents` (both localized, pre-tx). Multi-producer Composite = ACCEPTED (the absence-of-a-check proof).
- **DomainEventRecorder::record(...)** inside open `DB::transaction`; `Module::Catalog->value==='catalog'`; PII-free payload (ids only). **PG17 run:** `knowledge/testing/rules.md` command block; print the driver (`DRIVER=pgsql SERVER=17.x`) to prove it hit real PG, not a silent SQLite fallback; clean up the container after.

## Blockers & Decisions Needed
- None for this slice (crosses NO open gate). Next slice `catalog-lifecycle-approval` (FSM+approval) needs the Identity/auth ADR — not this one.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **Closing ritual:** `openspec list` + unchecked-task count are truth, not the ralph.sh footer.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB); hot.md ≤550 words.
