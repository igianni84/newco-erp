---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (pm) — task 5.1 green; the producer projection is now 3-status.** Ralph loop on [`catalog-module-0-completeness-sweep`](openspec/changes/catalog-module-0-completeness-sweep/). **10 of 16 done.**

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2167/2167 SQLite** (11 363 assertions) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- PG17: 5.1's blast radius **60/60** across 7 files. Last FULL PG17 run: 2080/2080 (pre-1.1) — task 7.2 re-runs the whole suite there.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). **PG17:** prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **`catalog-module-0-completeness-sweep`** (branch `ralph/catalog-module-0-completeness-sweep`) — 12 delta reqs · design D1–D11 · 16 tasks. Interview decisions live in `design.md`; don't re-litigate them.
- **DONE: 1.1** pivot · **1.2** 4-suffix freshness · **1.3** `CatalogContentEdit` · **2.1** `UpdateProductMasterIdentity` · **2.2** `UpdateCompositeSkuComposition` · **2.3** re-arm e2e · **3.1** `SetVariantCaseWhitelist` · **3.2** whitelist gate · **4.1** `EnrichmentDataUpdated` · **5.1** `ProducerCreated` → `registered`.
- **RM-12 and RM-13 need no further domain work.** 6.2 owes only the console modals.
- Every reusable mechanic: `progress.md` → `## Codebase Patterns`. **Read it first.**
- **NEXT: 5.2** — `CreateProductMaster` existence guard (D7, R1). Reuse `UnknownCatalogReference::forIds('Producer', [$id])`; do NOT mint `UnknownProducerReference` (the task's class name was an `e.g.`). Guard inside the transaction, before any write: no `ProducerState` row ⇒ reject, no Master, no `ProductMasterCreated`; a `registered` or `retired` row ADMITS creation (existence ≠ activeness). **`DemoSeeder` needs no change — 5.1's progress entry walked it and says why**, but still run the grep (`grep -rn 'CreateProductMaster' app/ tests/ database/`): the task list is not the blast radius, only the FULL suite is.
- **Then 6.x** (console modals) · **7.1** residual-claim sweep — 5.1 left it a precise file:line list in its progress entry.
- Landmines: R5 (i18n scanners cover **console** keys EN+IT; catalog domain reasons are EN-only by precedent), R6 (`{@see FQCN}` — Pint auto-imports it, redding `ModuleBoundariesTest`).
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first (grill-with-docs)** — own session.

## Blockers & Decisions Needed
- None. Loop proceeding task-by-task; humans push and archive.

## Open Patterns
- **`ApprovalGovernance::creatorOf` reads the entity's EARLIEST `domain_events` row, unfiltered.** Sound in production (nothing precedes `*Created`), a trap in tests: a factory-built entity has no creation event, so the first event an Action records takes the creator's seat in the SoD triple. Forces such tests onto the real `Create*` lineage (bit 4.1 twice; 6.2 inherits it).
- **`version` is the IDENTITY version.** "Should this bump `version`?" is never the question — "is this the entity's identity?" is. `edit()` vs `maintain()` encodes it at the call site; *no `version`* / *no re-arm* / *no event* are ONE fact.
- **The whitelist gate is Module 0's ONLY gate whose empty read means PASS.** The cascade and producer gates are fail-closed; a new gate must declare which family it joins.
- **A widened enum's PG CHECK is proven by ADMISSION, never by the existing rejection test** — and SQLite can never red on a stale one. Now a rule in `knowledge/data-model/rules.md`.
