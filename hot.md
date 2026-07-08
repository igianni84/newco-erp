---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (pm) — task 3.2 green; RM-12 complete, writer + gate.** Ralph loop on [`catalog-module-0-completeness-sweep`](openspec/changes/catalog-module-0-completeness-sweep/). **8 of 16 done.**

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2148/2148 SQLite** (11 262 assertions) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- PG17: 3.2's blast radius **69/69** across 9 files. Last FULL PG17 run: 2080/2080 (pre-1.1) — task 7.2 re-runs the whole suite there.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB). **PG17:** prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **`catalog-module-0-completeness-sweep`** (branch `ralph/catalog-module-0-completeness-sweep`) — 12 delta reqs · design D1–D11 · 16 tasks. Interview decisions live in `design.md`; don't re-litigate them.
- **DONE: 1.1** pivot · **1.2** 4-suffix freshness derivation · **1.3** `CatalogContentEdit` · **2.1** `UpdateProductMasterIdentity` · **2.2** `UpdateCompositeSkuComposition` · **2.3** re-arm e2e · **3.1** `SetVariantCaseWhitelist` · **3.2** `CaseConfigurationWhitelistGate`.
- **RM-12 needs no further domain work.** The pivot has ONE writer and ONE reader (the gate, called only from `ActivateSellableSku`'s closure) — R10 holds structurally, not just by test. 6.2 owes only the console modal.
- Every reusable mechanic: `progress.md` → `## Codebase Patterns`. **Read it first.**
- **NEXT: 4.1** — `EnrichmentDataUpdated` event + `UpdateProductVariantEnrichment` action (D11, D2). Verb `enrichment_updated`, NO `version`; event fires in-transaction ONLY when stored `tasting_notes` actually changes; identical value ⇒ silent no-op (no event, no audit, no write). **Use `CatalogContentEdit::maintain()` — do not reopen `edit()`.** The no-op is a **`$apply`-contract change**: the closure returns `null` for *nothing to record*, and `perform()` writes nothing. Payload `{product_variant_id}` only (PII-free).
- Landmines: R1 (RM-15 blast radius — grep `CreateProductMaster` callers), R2 (`EnumsTest` 2→3), R5 (i18n scanners cover **console** keys EN+IT; domain reasons are EN-only), R6 (`{@see FQCN}` — Pint auto-imports it, redding `ModuleBoundariesTest`).
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first (grill-with-docs)** — dedicated session, not folded into the sweep.

## Blockers & Decisions Needed
- None. Loop proceeding task-by-task; humans push and archive.

## Open Patterns
- **`version` is the IDENTITY version.** "Should this bump `version`?" is never the question — "is this the entity's identity?" is. The three facts *no `version`* / *no re-arm* / *no event* are ONE fact; `edit()` vs `maintain()` encodes it at the call site.
- **The whitelist gate is Module 0's ONLY gate whose empty read means PASS.** Cascade + producer gates are fail-closed. `if ($rows === []) return;` and `if ($row === null) throw;` are one character of intent apart — a new gate must declare which family it joins.
- **A throwing assert whose caller also READS the value returns it; never `@phpstan-assert`** (reported redundant wherever the argument is already non-nullable). Fallout to budget: `gate: fn (…) => assert(…)` arrow fns break, since an arrow fn cannot declare `: void`.
- **"Untouched" is asserted against a snapshot taken BEFORE the disturbance** (audit-row ids, in order) — only that catches a spurious added row.
