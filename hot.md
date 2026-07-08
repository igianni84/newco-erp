---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (eve) — `catalog-module-0-completeness-sweep` is CODE-COMPLETE, 16/16 tasks.** Awaiting human review → merge → semantic-verify → archive.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Full verify (task 7.2), both engines:** SQLite **2206/2206** (11 682 assertions) · PG17 **2206/2206** (11 685 — the surplus is the PG-only CHECK lane). PHPStan **0** · Pint clean · `validate --strict` valid.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). **PG17:** prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- Branch `ralph/catalog-module-0-completeness-sweep` — 12 delta reqs · 52 scenarios · design D1–D11 · 16 tasks, **all `[x]`**. Interview decisions: `design.md`.
- `progress.md` carries the **delta→test traceability table** (every scenario → its named tests) and names the 5 deferred seams once (Module A Layer-2 · the Module S `EnrichmentDataUpdated` consumer · enrichment adapter columns · `producer_name` · the DEC-071 KYC tightening).
- Every reusable mechanic: `progress.md` → `## Codebase Patterns` (132 bullets). **Read it first.**
- **NEXT: RM-05** (capacity seat-set + WaitingList, the last P1) via the **K-side seam, ADR-first** — its own session.
- Landmines: R5 (console keys EN+IT; catalog **domain** reasons EN-only), R6 (a `{@see FQCN}` on a `Catalog\Events`/`Lifecycle` type — Pint auto-imports it, redding `ModuleBoundariesTest`).

## Blockers & Decisions Needed
- None. Humans push, merge and archive.

## Open Patterns
- **A scenario's coverage is an ORDERING claim, not a set-of-facts claim** (new, `knowledge/testing/hypotheses.md`). Where a GIVEN establishes state and the WHEN is an event *landing on* it, a test that builds the state AFTER the trigger asserts every fact and drops the "…left unchanged as a side effect" clause. 7.2 found exactly this: ten tests delivered `ProducerActivated`, all ten before any Master existed — while the sibling `ProducerRetired` scenario was always tested the right way round. **Sibling scenarios diverge silently in test SHAPE.**
- **An "untouched" ids-snapshot passes for free on an EMPTY trail** (`[] === []`). Pin the literal ordered ACTION list so the assertion proves its own non-vacuity.
- **A residual-claim sweep must include `tests/`.** 7.1 swept `app/`; a stale `draft-as-absent` gate description survived in a test docblock. Test prose rots and nothing catches it.
- **Uniformity of the OLD claim is no evidence of uniformity of the NEW one** (`lessons.md` 2026-07-08). Enumerate from the code which files a replacement is TRUE for.
- **Three Filament test helpers lie**, failing as if the SUT were broken (`knowledge/filament/hypotheses.md`): `callAction()` cannot SHRINK a prefilled list; `assertHasActionErrors()` truncates the message at the first `:`; `assertNotified()` asserts the TITLE and PULLS the session.
- **A rejection's landing place is set by the console action's SHAPE.** Verb-shaped ⇒ danger notification; form-shaped ⇒ validation error on ONE designated field (design L4).
- **`ApprovalGovernance::creatorOf` reads the EARLIEST `domain_events` row, unfiltered.** A factory-built entity has no creation event, so an SoD-subject test must build through the real `Create*` lineage.
- **`version` is the IDENTITY version.** *No `version`* / *no re-arm* / *no event* are ONE fact.
- **The grep is the candidate set; only the FULL suite is the blast radius.**
