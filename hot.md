---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (pm) — task 5.2 green; RM-15 is complete.** Ralph loop on [`catalog-module-0-completeness-sweep`](openspec/changes/catalog-module-0-completeness-sweep/). **11 of 16 done.**

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2172/2172 SQLite** (11 379 assertions) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- PG17: 5.2's blast radius **90/90** across 9 files. Last FULL PG17 run: 2080/2080 (pre-1.1) — task 7.2 re-runs the whole suite there.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). **PG17:** prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **`catalog-module-0-completeness-sweep`** (branch `ralph/catalog-module-0-completeness-sweep`) — 12 delta reqs · design D1–D11 · 16 tasks. Interview decisions live in `design.md`; don't re-litigate them.
- **DONE: 1.1** pivot · **1.2** 4-suffix freshness · **1.3** `CatalogContentEdit` · **2.1** `UpdateProductMasterIdentity` · **2.2** `UpdateCompositeSkuComposition` · **2.3** re-arm e2e · **3.1** `SetVariantCaseWhitelist` · **3.2** whitelist gate · **4.1** `EnrichmentDataUpdated` · **5.1** `ProducerCreated` → `registered` · **5.2** creation-existence guard.
- **RM-12, RM-13, RM-14, RM-15 need no further domain work.** Everything left is console (6.x) + docs (7.x).
- Every reusable mechanic: `progress.md` → `## Codebase Patterns`. **Read it first.**
- **NEXT: 6.1** — `ViewProductMaster` edit-identity modal + create-page unknown-producer mapping (D8). Two shortcuts, both verified in 5.2's progress entry: (a) the unknown-producer path **already** maps to a form error — `UnknownCatalogReference extends RuntimeException`, and `OperatorConsoleCreateRecord` catches by base type onto `createRejectionField()` (`name`); write the Livewire test, don't add code. (b) `producerOptions()` applies **no status filter**, so the select already lists `registered` producers (creatable ≠ activatable, D8) — nothing to change. Reuse the create-form translatable-prose field pattern (R8). Keys EN+IT for **console** strings only.
- **Then 6.2/6.3** (Variant enrichment + whitelist modals; Composite composition modal) · **7.1** residual-claim sweep — 5.1 and 5.2 both left precise file:line lists in their progress entries.
- Landmines: R5 (i18n scanners cover **console** keys EN+IT; catalog **domain** reasons are EN-only — `lang/it/catalog.php` does not exist), R6 (`{@see FQCN}` — Pint auto-imports it, redding `ModuleBoundariesTest`), and `creatorOf` (below).
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first (grill-with-docs)** — own session.

## Blockers & Decisions Needed
- None. Loop proceeding task-by-task; humans push and archive.

## Open Patterns
- **`ApprovalGovernance::creatorOf` reads the entity's EARLIEST `domain_events` row, unfiltered.** Sound in production (nothing precedes `*Created`), a trap in tests: a factory-built entity has no creation event, so the first event an Action records takes the creator's seat in the SoD triple. Any test whose subject is SoD must build through the real `Create*` lineage (bit 4.1 twice; 5.2 designed around it; 6.2 inherits it).
- **`version` is the IDENTITY version.** "Should this bump `version`?" is never the question — "is this the entity's identity?" is. `edit()` vs `maintain()` encodes it at the call site; *no `version`* / *no re-arm* / *no event* are ONE fact.
- **The whitelist gate is Module 0's ONLY gate whose empty read means PASS.** The cascade and producer gates are fail-closed; a new gate must declare which family it joins.
- **The grep is the candidate set; only the FULL suite is the blast radius.** 5.2: 30 files greped, 5 actually broke. Wire the guard, run everything, then migrate what reds.
- **A new upstream guard relocates a downstream test's premise — it does not invalidate the test.** Ask which real states still reach the branch, and construct one (5.2 kept the gate's absent-row case by deleting the projection row *after* a real-lineage create).
