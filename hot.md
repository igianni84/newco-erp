---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-14
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-14 (ralph) — `catalog-product-spine` task 5.3 DONE → ALL 11 TASKS COMPLETE → `<promise>CHANGE_COMPLETE</promise>` emitted.** Final task: the full-chain integration test + the final cross-engine PG17 close. New `tests/Feature/Modules/Catalog/SpineCreationChainTest.php` (5 tests / 30 assertions) drives the WHOLE spine end-to-end THROUGH THE ACTIONS — Master→Variant→(two Formats)→two References→Intrinsic SKU + a Composite of both References — via one typed helper `createCatalogSpineChain()`. Asserts: (1) DISTINCT recorded event-name set === exactly the seven `*Created` families (UPPER-`SKU` events) + per-type counts + `%Activated%`/`%Retired%`===0 + all tagged `module=catalog`; (2) every entity re-fetched `draft`; (3) PII-free (producer by bare id; exact key SET + cross-event forbidden-key sweep); (4) BR-Identity-1 dedup rejection in the integrated flow; (5) producer-agnostic Composite accepted (D9/BR-SKU-5). NO new prod code / NO new DB — pure integration test. **The PG17 gate earned its keep:** first PG run was 319/320 — the PII test's `array_keys($payload)->toBe([fixed order])` passed on SQLite but failed on PG (`jsonb` reorders object keys by length-then-bytewise); fixed with the `sort($keys)`-then-compare idiom (trap-3 sharpening, now in Codebase Patterns).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/catalog-product-spine`: suite **320/320** (1249 assertions, +5 vs 315) on SQLite **AND on `postgres:17`** (driver proof `DRIVER=pgsql SERVER=17.10`; container cleaned up) · phpstan **0 @ max** · pint clean · `openspec validate catalog-product-spine --strict` valid · `git diff main -- composer.{json,lock}` empty · `ModuleBoundariesTest` 2/2 (no amendment).
- **Final cross-engine close DONE** — the whole Catalog spine is proven end-to-end on both engines.

## Active Change & Next Task
- **`catalog-product-spine` is CODE-COMPLETE — ALL 11 tasks `- [x]`** (1.1 ✓, 2.x ✓, 3.x ✓, 4.x ✓, 5.1 ✓, 5.2 ✓, 5.3 ✓). 7 spine entities + their `*Created` events + §18 naming guard + glossary/event-contract docs + full-chain integration, all green on SQLite + PG17.
- **NEXT (human, NOT the loop):** review → merge `ralph/catalog-product-spine` → semantic-verify (GUIDE §2.7) → `openspec archive catalog-product-spine --yes`. The loop does NOT archive or merge.
- **After archive, next candidate slice:** `catalog-lifecycle-approval` (the Draft→Reviewed→Active→Retired FSM + approval workflow + the `*Activated`/`*Retired` events this change DELIBERATELY deferred). **Blocked on the Identity/auth ADR** (operator principals for approval) — run `grill-with-docs` + write the ADR before kicking it off.

## Implementation landmines (read progress.md Codebase Patterns before every task)
- **Full menu in progress.md Codebase Patterns** (now 20+ entries): spine DB-entity template · multi-table (neutral core + per-type 1:1) · single-table · M:N join · naming-cascade arch guard · DB-unique vs app-dedup · FK onDelete asymmetry · event-vs-model NAME divergence (UPPER-`SKU`) · spec-fidelity-over-i18n · cross-ROW-count pre-tx rejection · producer-agnostic non-check · getColumnListing facade trap · schema-absence guard · localized rejection · 2 phpstan-max traps · **full-chain integration-test shape** · **trap-3 also bites `array_keys()->toBe([…])` (key order non-portable — sort first)**.
- **Cross-engine discipline (the recurring win):** SQLite-green is necessary, NEVER sufficient — run the full suite on `postgres:17` for any DB/jsonb-touching test; print `DRIVER=pgsql` to prove it hit real PG; clean up the container. jsonb OBJECT keys reorder (sort before `toBe`); jsonb ARRAY element order is preserved.

## Blockers & Decisions Needed
- None for this change (it crossed NO open gate). The next slice needs the **Identity/auth ADR** (Module K gate).
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **Closing ritual:** `openspec list` + unchecked-task count are truth, not the ralph.sh footer.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB); hot.md ≤550 words.
