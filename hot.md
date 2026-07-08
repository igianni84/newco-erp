---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (eve) — task 6.2 green; both Variant maintenance modals ship.** Ralph loop on [`catalog-module-0-completeness-sweep`](openspec/changes/catalog-module-0-completeness-sweep/). **13 of 16 done.**

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2197/2197 SQLite** (11 597 assertions) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- PG17: 6.2's blast radius **1139/1139** (`tests/Feature/Modules/{OperatorPanel,Catalog}/` + `tests/Architecture/`). Last FULL PG17: 2080/2080 (pre-1.1) — 7.2 re-runs the whole suite there.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). **PG17:** prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **`catalog-module-0-completeness-sweep`** (branch `ralph/catalog-module-0-completeness-sweep`) — 12 delta reqs · design D1–D11 · 16 tasks. Interview decisions live in `design.md`.
- **DONE: 1.1–1.3 · 2.1–2.3 · 3.1–3.2 · 4.1 · 5.1–5.2 · 6.1 · 6.2.** **All domain work is complete.** What is left is ONE console page (6.3) + docs (7.1, 7.2).
- Every reusable mechanic: `progress.md` → `## Codebase Patterns` (121 bullets). **Read it first.**
- **NEXT: 6.3** — `ViewCompositeSku` edit-composition modal. Copy `ViewProductVariant::editEnrichmentAction()`: ONE operand (the ordered PR multi-select), so it needs neither the `live()` re-prefill nor the pair-scoped read 6.2's whitelist modal required. Prefill from the ordered `constituents()` junction; rejection field `constituents`; narrow to `list<int>` as `CreateCompositeSku::createViaAction()` does. `UpdateCompositeSkuComposition` owns N≥2 + the active-constituent re-check — assert, don't rewire.
- **Then 7.1** residual-claim sweep (6.2's progress entry lists every remaining hit) · **7.2** full verify.
- Landmines: R5 (console keys EN+IT; catalog **domain** reasons are EN-only), R6 (a `{@see FQCN}` on a `Catalog\Events`/`Lifecycle` type — Pint auto-imports it, redding `ModuleBoundariesTest`), `creatorOf` (below), Pint's `phpdoc_*` reflow.
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first** — own session.

## Blockers & Decisions Needed
- None. Loop proceeding task-by-task; humans push and archive.

## Open Patterns
- **A rejection's landing place is set by the console action's SHAPE, not its exception type.** Verb-shaped ⇒ danger notification; form-shaped ⇒ validation error on one designated field. 6.3 must not invent a fourth shape.
- **The whitelist gate is Module 0's ONLY gate whose empty read means PASS** — a *fixture* constraint too: a J-13 test must REDUCE the admitted set, never empty it. 6.2's first gate test failed here.
- **`assertNotified()` asserts the TITLE and PULLS the session.** A notification's BODY (the domain's localized message) must be snapshotted first, off `filament.claimed_notifications` — the key Livewire's `dehydrate` hook `put`s it under, overwriting per request.
- **A Filament blank `Textarea` dehydrates to `null`, never `''`** — a page's `=== ''` arm is unreachable defence-in-depth. Mark it; let no test claim to exercise it.
- **`ApprovalGovernance::creatorOf` reads the entity's EARLIEST `domain_events` row, unfiltered.** A factory-built entity has no creation event, so the first event an Action records takes the creator's seat: an SoD-subject test must build through the real `Create*` lineage.
- **`version` is the IDENTITY version.** *No `version`* / *no re-arm* / *no event* are ONE fact.
- **The grep is the candidate set; only the FULL suite is the blast radius.** Before shipping a defensive guard, delete it and see if anything reds.
