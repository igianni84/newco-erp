---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-14
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-14 (ralph) — `catalog-product-spine` task 5.1 DONE (Naming-cascade guard).** A pure CONVENTION/arch test, **NO new DB, NO production-code change** (the 3 model docblock aliases were already added in 3.1–3.3). New file `tests/Architecture/CatalogNamingCascadeTest.php` — three boot-free legs mirroring `ModuleConformanceTest`/`ModulePersistenceConventionsTest` (reflect `Module::Catalog->namespace()`/`->name`, no container): (1) **positive existence** — `class_exists()` the 7 canonical models + 7 `*Created` events (UPPER-`SKU` events `SellableSKUCreated`/`CompositeSKUCreated` vs lower-`Sku` models) + `toHaveCount(7)` set guards; (2) **negative scan** — recursive walk of the Catalog subtree, collect each `.php` `getBasename('.php')` (PSR-4 short-name), `array_filter` the forbidden category-PREFIX `/^(Wine|BottleReference)/` → `toBe([])`, non-vacuity proven THROUGH the tricky case (`toContain('ProductMasterWineAttributes')` — the suffix-qualified per-type class that CONTAINS "Wine" yet stays legal per design D1); (3) **alias-retention** — reflect `getDocComment()` on the 3 models, assert each carries its wine-display alias. **Anchored regex, NOT the test-hint's loose `/Wine/`** (which would wrongly flag the per-type `*WineAttributes` classes; the spec forbids the PREFIXES `WineMaster*`/`WineVariant*`/`BottleReference*`). **9 of 11 tasks done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/catalog-product-spine`: suite **315/315** (1219 assertions) on SQLite · phpstan **0 @ max** · pint clean · `openspec validate catalog-product-spine --strict` valid · `git diff main -- composer.{json,lock}` empty · `ModuleBoundariesTest` 2/2 (no amendment).
- **No PG run this task** (5.1 adds no schema). Last cross-engine close was 4.2 (312/312 on `postgres:17`). 5.2 is DB-free too; **5.3 is the FINAL full-Catalog cross-engine PG17 close**.

## Active Change & Next Task
- **Active change = `catalog-product-spine`** (implementing). 11 tasks. 1.1 ✓, 2.x ✓, 3.x ✓, 4.x ✓, 5.1 ✓. ALL 7 spine entities exist + the §18 naming cascade is now MECHANICALLY enforced.
- **NEXT TASK = 5.2 Docs** (DB-free, docs-only — NO test, run lint/format + `openspec validate --strict`; full suite must stay green). Extend root `CONTEXT.md` with the resolved spine glossary (Product Master, Product Variant, Product Reference [+ "Bottle Reference" alias], Format, Case Configuration, Sellable SKU [Intrinsic/Composite], Product Type, the naming-cascade alias rule) AND a Catalog event-contract note documenting the SEVEN `*Created` payload shapes (PII-free, ids only). Read the 7 `Events/*Created.php` `payload()` methods for the exact keys; mind UPPER-`SKU` event names. Then **5.3** (full-chain integration Master→Variant→Format→Reference→Intrinsic SKU + Composite; assert all `*Created` / zero `*Activated`/`*Retired`; the FINAL cross-engine PG17 close).

## Implementation landmines (read progress.md Codebase Patterns before every task)
- **Spine template + multi-table + single-table + M:N-join + naming-cascade-arch-guard (NEW 5.1) + DB-unique-identity + FK onDelete asymmetry + event-vs-model name divergence + spec-fidelity-over-i18n + cross-ROW-count-pre-tx-rejection + producer-agnostic-non-check + getColumnListing facade trap + schema-absence guard + localized rejection + 2 phpstan-max traps** — ALL in progress.md Codebase Patterns.
- **5.2 is docs-only:** NO code, NO test; the deliverable is `CONTEXT.md` prose. Quality loop = pint/pint --test + `openspec validate --strict`. Ground every payload-key claim in the actual `payload()` method (don't guess — the 4.1/4.2 "ground-it-don't-guess" discipline).
- **DomainEventRecorder::record(...)** inside open `DB::transaction`; `Module::Catalog->value==='catalog'`; PII-free payload (ids only); `->sole()` to fetch.

## Blockers & Decisions Needed
- None for this slice (crosses NO open gate). Next slice `catalog-lifecycle-approval` (FSM+approval) needs the Identity/auth ADR — not this one.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **Closing ritual:** `openspec list` + unchecked-task count are truth, not the ralph.sh footer.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB); hot.md ≤550 words.
