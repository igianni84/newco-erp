---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 (interactive close) — `catalog-product-spine` MERGED to `main` + ARCHIVED. GUIDE §2.7 ritual complete.** Verify-first close: independently re-ran every gate before touching `main` — diff stat clean (68 files, +5747/−15, NO `spec/**` or hand-edited `openspec/specs/**`), pint clean, `openspec validate --strict` valid, phpstan **0 @ max**, full suite **320/320** (1249 assert) on **PostgreSQL 17.10** (driver printed `pgsql`, container started+removed), and a delegated **semantic-verify subagent returned CLEAN** (0 CRITICAL, 0 WARNING; 3 forward-looking SUGGESTIONs for the lifecycle change). Then: `git merge --no-ff` → `main` (`5789f3a`) + push, local branch deleted (no remote branch existed), `openspec archive catalog-product-spine --yes` → living spec `openspec/specs/product-catalog/spec.md` (11 requirements) + change moved to `changes/archive/2026-06-15-catalog-product-spine/`, archive commit `0ef9539` + push. Post-merge `main` re-confirmed **320/320** on SQLite, tree clean, in sync with `origin/main`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- `main` @ `0ef9539`: suite **320/320** green (SQLite this session + PG17 pre-merge) · phpstan **0 @ max** · pint clean · in sync with `origin/main` · `ModuleBoundariesTest` 2/2.
- Catalog product spine is the first F2 slice landed: 7 spine entities (Format, CaseConfiguration, ProductMaster, ProductVariant, ProductReference, SellableSku, CompositeSku) + 7 `*Created` events (born `draft` only) + §18 naming guard + glossary/event-payload docs.

## Active Change & Next Task
- **NO active change** (`openspec list` → "No active changes found"). Nothing in flight.
- **NEXT candidate slice (per Build Workplan F2):** `catalog-lifecycle-approval` — Draft→Reviewed→Active→Retired FSM + approval workflow + the `*Activated`/`*Retired` events `catalog-product-spine` DELIBERATELY deferred. **BLOCKED on the Identity/auth ADR** (operator principals for approval). Run an ADR session (`grill-with-docs`, GUIDE §3 prompt) + write `decisions/2026-…-auth-identity.md` BEFORE `/spec-to-change`.
- Alt if you want to avoid the gate now: a Module K slice that doesn't need auth, or another Catalog enrichment slice — but lifecycle-approval is the natural F2 continuation.

## Implementation landmines (read archived progress.md Codebase Patterns before the next slice)
- Patterns now travel with the change at `openspec/changes/archive/2026-06-15-catalog-product-spine/progress.md` (20+ entries): spine DB-entity template · multi-table (neutral core + per-type 1:1) · single-table · M:N join · naming-cascade arch guard · DB-unique vs app-dedup · FK onDelete asymmetry (parent edges cascade / shared-ref edges restrict) · event-vs-model NAME divergence (UPPER-`SKU`) · spec-fidelity-over-i18n · producer-agnostic non-check · localized rejection · 2 phpstan-max traps · full-chain integration shape.
- **Cross-engine discipline (recurring win):** SQLite-green is necessary, NEVER sufficient — run the full suite on `postgres:17` for any DB/jsonb test; print `DRIVER=pgsql` to prove it; remove the container. jsonb OBJECT keys reorder (sort before `toBe`); jsonb ARRAY order preserved.

## Blockers & Decisions Needed
- **Identity/auth ADR (Module K gate)** — needed before `catalog-lifecycle-approval` and all of Module K. This is the next human bottleneck.
- **Open ADR gates (do not step into):** identity/auth (Module K, next) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **Closing ritual (just exercised):** `openspec list` + unchecked-task count are truth, not the ralph.sh footer; run gates (incl. PG17 + semantic-verify) BEFORE merging to `main`; pause if anything's off.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB); hot.md ≤550 words.
