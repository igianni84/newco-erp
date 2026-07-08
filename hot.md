---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (eve) — task 6.1 green; the console edit kit exists.** Ralph loop on [`catalog-module-0-completeness-sweep`](openspec/changes/catalog-module-0-completeness-sweep/). **12 of 16 done.**

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2180/2180 SQLite** (11 459 assertions) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- PG17: 6.1's blast radius **862/862** (`tests/Feature/Modules/OperatorPanel/` + `tests/Architecture/` — the console kit base is shared by all 13 View pages). Last FULL PG17 run: 2080/2080 (pre-1.1) — task 7.2 re-runs the whole suite there.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). **PG17:** prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **`catalog-module-0-completeness-sweep`** (branch `ralph/catalog-module-0-completeness-sweep`) — 12 delta reqs · design D1–D11 · 16 tasks. Interview decisions live in `design.md`; don't re-litigate them.
- **DONE: 1.1–1.3** (whitelist pivot · 4-suffix freshness · `CatalogContentEdit`) · **2.1–2.3** (identity edit · composite composition · re-arm e2e) · **3.1–3.2** (`SetVariantCaseWhitelist` · activation gate) · **4.1** (`EnrichmentDataUpdated`) · **5.1–5.2** (`ProducerCreated` → `registered` · creation-existence guard) · **6.1** (`editIdentity` modal + create-page mapping).
- **All domain work is complete.** RM-12/13/14/15 need nothing further. What is left is console (6.2, 6.3) + docs (7.1, 7.2).
- Every reusable mechanic: `progress.md` → `## Codebase Patterns`. **Read it first.**
- **NEXT: 6.2** — `ViewProductVariant` edit-enrichment + manage-whitelist modals. **The kit primitive now exists:** `OperatorConsoleViewRecord::contentEditAction($verb, $successKey, $rejectionField, $form, $fill, $invoke)` — copy `ViewProductMaster::editIdentityAction()`. Whitelist is the first modal with TWO operands (Format + CC set); narrow both inside `$invoke`. The domain surface (`UpdateProductVariantEnrichment`, `SetVariantCaseWhitelist`, `CaseConfigurationNotWhitelisted`) is complete — assert it, do not rewire it. Console keys EN+IT (R5).
- **Then 6.3** (Composite composition modal) · **7.1** residual-claim sweep — 5.1/5.2/6.1 each left precise file:line lists in their progress entries · **7.2** full verify.
- Landmines: R5 (console keys EN+IT; catalog **domain** reasons are EN-only — `lang/it/catalog.php` does not exist), R6 (`{@see FQCN}` — Pint auto-imports it, redding `ModuleBoundariesTest`), `creatorOf` (below), and Pint's `phpdoc_*` reflow (`lessons.md` 2026-07-08).
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first (grill-with-docs)** — own session.

## Blockers & Decisions Needed
- None. Loop proceeding task-by-task; humans push and archive.

## Open Patterns
- **A rejection's landing place is set by the console action's SHAPE, not its exception type.** Verb-shaped write ⇒ danger notification; form-shaped write (create page, `contentEditAction` modal) ⇒ validation error on one designated field. The console cannot type-discriminate (no `Exceptions` import). 6.2/6.3 must not invent a fourth shape.
- **A Filament `Select` contributes `Rule::in(<its options>)`.** So a domain guard behind an operator-chosen FK whose option list reads the same source is structurally unreachable from that surface — a *backstop*, exactly as the delta words it. Assert the observable contract at the console; prove the guard at the domain.
- **`ApprovalGovernance::creatorOf` reads the entity's EARLIEST `domain_events` row, unfiltered.** Sound in production, a trap in tests: a factory-built entity has no creation event, so the first event an Action records takes the creator's seat. Any SoD-subject test must build through the real `Create*` lineage.
- **`version` is the IDENTITY version.** "Should this bump `version`?" is never the question — "is this the entity's identity?" is. `edit()` vs `maintain()` encodes it at the call site. *No `version`* / *no re-arm* / *no event* are ONE fact.
- **The whitelist gate is Module 0's ONLY gate whose empty read means PASS.** The cascade and producer gates are fail-closed; a new gate must declare which family it joins.
- **The grep is the candidate set; only the FULL suite is the blast radius.**
- **Before shipping a defensive re-read or guard, delete it and see if anything reds.** 6.1's `$record->refresh()` was dead code: the domain mechanism mutates the page's own model instance.
