---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-14
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-14 (ralph) — `catalog-product-spine` FINAL-PASS RE-VERIFIED → `<promise>CHANGE_COMPLETE</promise>` re-confirmed.** This iteration found ALL 11 tasks already `- [x]` and the work committed (HEAD `dc74e4a`, tree clean). Per RALPH.md's "all tasks checked" stop condition I did NOT trust the cache — I re-ran the whole quality gate from scratch: full suite **320/320** (1249 assertions) on SQLite, phpstan **0 @ max**, pint clean, `openspec validate catalog-product-spine --strict` valid, `git diff main -- composer.{json,lock}` empty. Confirmed the PG17 cross-engine record is present in progress.md (task 5.3 = **320/320 on `postgres:17`**, `DRIVER=pgsql SERVER=17.10`, container cleaned up; that gate caught a trap-3 jsonb key-order regression SQLite had hidden). No code/test/doc change this iteration — pure verification + memory write.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/catalog-product-spine`: suite **320/320** green on SQLite (re-run this iteration) AND previously on `postgres:17` · phpstan **0 @ max** · pint clean · `openspec validate … --strict` valid · composer diff vs main empty · `ModuleBoundariesTest` 2/2 (no amendment).
- The whole Catalog spine is proven end-to-end on both engines. Nothing outstanding for this change.

## Active Change & Next Task
- **`catalog-product-spine` is CODE-COMPLETE — ALL 11 tasks `- [x]`** (1.1 ✓, 2.x ✓, 3.x ✓, 4.x ✓, 5.1 ✓, 5.2 ✓, 5.3 ✓). 7 spine entities + their `*Created` events + §18 naming guard + glossary/event-contract docs + full-chain integration.
- **NEXT (human, NOT the loop):** review → merge `ralph/catalog-product-spine` → semantic-verify (GUIDE §2.7) → `openspec archive catalog-product-spine --yes`. The loop does NOT archive or merge.
- **After archive, next candidate slice:** `catalog-lifecycle-approval` (Draft→Reviewed→Active→Retired FSM + approval workflow + the `*Activated`/`*Retired` events this change DELIBERATELY deferred). **Blocked on the Identity/auth ADR** (operator principals for approval) — run `grill-with-docs` + write the ADR before kicking it off.

## Implementation landmines (read progress.md Codebase Patterns before every task)
- **Full menu in progress.md Codebase Patterns** (20+ entries): spine DB-entity template · multi-table (neutral core + per-type 1:1) · single-table · M:N join · naming-cascade arch guard · DB-unique vs app-dedup · FK onDelete asymmetry · event-vs-model NAME divergence (UPPER-`SKU`) · spec-fidelity-over-i18n · cross-ROW-count pre-tx rejection · producer-agnostic non-check · getColumnListing facade trap · schema-absence guard · localized rejection · 2 phpstan-max traps · full-chain integration-test shape · trap-3 also bites `array_keys()->toBe([…])` (jsonb key order non-portable — sort first).
- **Cross-engine discipline (the recurring win):** SQLite-green is necessary, NEVER sufficient — run the full suite on `postgres:17` for any DB/jsonb-touching test; print `DRIVER=pgsql` to prove it hit real PG; clean up the container. jsonb OBJECT keys reorder (sort before `toBe`); jsonb ARRAY element order is preserved.

## Blockers & Decisions Needed
- None for this change (it crossed NO open gate). The next slice needs the **Identity/auth ADR** (Module K gate).
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **Closing ritual:** `openspec list` + unchecked-task count are truth, not the ralph.sh footer.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB); hot.md ≤550 words.
