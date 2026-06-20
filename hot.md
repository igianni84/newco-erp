---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (human close ritual GUIDE §2.7 — `operator-console-catalog-spine` CLOSED & ARCHIVED).** The loop finished 10/10; this session ran the close ritual end-to-end: cold review → **PG17 full-suite gate** → merge → semantic verify → archive. The change is now merged to `main` (local, `--no-ff`, commit `58f6ca8`) and archived as `openspec/changes/archive/2026-06-20-operator-console-catalog-spine/` (commit `c67a948`); its 5 delta requirements are folded into the living spec `openspec/specs/operator-console/spec.md` (now **12 requirements total**). The `ralph/operator-console-catalog-spine` branch is deleted (merged). Semantic verification came back **CLEAN — 5/5 requirements complete/correct/coherent, 0 CRITICAL/WARNING**, every scenario mapped to a real test. Delivered: all SIX Module-0 spine consoles (Format, Case Config, Variant, Product Reference, Sellable SKU, Composite SKU) on the shared operator-console kit + Master retrofit, i18n EN/IT.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **`main` GREEN: 1138/1138 SQLite (6442 assn) AND PG17 FULL-suite 1138/1138 (6442 assn, exit 0).** The close ritual re-ran the ENTIRE suite on docker `postgres:17` (not just the loop's 429-test module subset) — production engine confirmed before merge. phpstan 0; pint clean; `openspec validate --strict` OK.
- **Run-cmd gotcha:** the FULL suite OOMs under bare `php artisan test` (128M). Run `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **NO active change** — `operator-console-catalog-spine` archived. Clean slate for the next slice.
- **`main` is +13 vs `origin/main` and NOT pushed.** The loop/agent commits locally; **humans push** — Giovanni pushes `main` when ready.
- **Next: `spec-to-change` for the next spine slice.** Candidate per the closed change's forward note: a **Parties operator console** reusing the kit verbatim (per entity: resource + 3 pages + lang block + 2 tests + closing chain).

## Blockers & Decisions Needed
- None. Reminder: `main` is local-only until Giovanni pushes.

## Open Patterns
- **The operator-console kit is the template for all nine modules.** Before any reuse READ the archived `## Codebase Patterns`: `openspec/changes/archive/2026-06-20-operator-console-catalog-spine/progress.md`. Kit = 5 pieces (`Console/{OperatorConsoleResource,OperatorConsoleCreateRecord,OperatorConsoleViewRecord,Concerns/SurfacesDomainActions}`) + recipes: standalone / hierarchical / leaf / N-constituent, the kit-key i18n-completeness pattern, and the full-spine closing-chain shape (drive every console through its Filament pages; `toEqualCanonicalizing` emergent-event-set proof; event-free producer seed).
- Write-through-Actions + SoD/gates/reference-integrity surfaced-not-reimplemented are enforced by **registered PHPStan/arch rules** (`NoEloquentWriteInOperatorPanelRule`, `ModuleBoundariesTest` pinning the OperatorPanel→Catalog carve-out at `{Models, Actions}`), not convention.
