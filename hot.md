---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (eve) — task 6.3 green; ALL console surfaces ship.** Ralph loop on [`catalog-module-0-completeness-sweep`](openspec/changes/catalog-module-0-completeness-sweep/). **14 of 16 done.** Only docs remain.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2205/2205 SQLite** (11 673 assertions) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- PG17: 6.3's blast radius **228/228** (`tests/Feature/Modules/OperatorPanel/Catalog/`). Last FULL PG17: 2080/2080 (pre-1.1) — **7.2 re-runs the whole suite there**.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). **PG17:** prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **`catalog-module-0-completeness-sweep`** (branch `ralph/catalog-module-0-completeness-sweep`) — 12 delta reqs · design D1–D11 · 16 tasks. Interview decisions live in `design.md`.
- **DONE: 1.1–1.3 · 2.1–2.3 · 3.1–3.2 · 4.1 · 5.1–5.2 · 6.1–6.3.** **All code is written.** Left: docs (7.1) + full verify (7.2).
- Every reusable mechanic: `progress.md` → `## Codebase Patterns` (125 bullets). **Read it first.**
- **NEXT: 7.1** — CONTEXT.md rewrite + residual-claim sweep. **Grep, don't recall**; no code-behavior change. 6.3 fixed the two Composite "ships no update Action" rationales; 6.2's and 6.3's progress entries list every remaining hit (task 7.1 in `tasks.md` names the grep families). CONTEXT.md and `decisions/` prose are first-class claim-holders.
- **Then 7.2** full verify (incl. FULL PG17) + delta→test traceability table in progress.md.
- Landmines: R5 (console keys EN+IT; catalog **domain** reasons are EN-only), R6 (a `{@see FQCN}` on a `Catalog\Events`/`Lifecycle` type — Pint auto-imports it, redding `ModuleBoundariesTest`), `creatorOf` (below).
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first** — own session.

## Blockers & Decisions Needed
- None. Loop proceeding task-by-task; humans push and archive.

## Open Patterns
- **Two Filament test helpers lie, and both fail as if the SUT were broken** (new, `knowledge/filament/hypotheses.md`). `callAction($n, ['list' => [a]])` against an `[a,b]` prefill submits `[a,b]` — shrink via `mountAction → set('mountedActions.0.data.<f>', […]) → callMountedAction()`. And `assertHasActionErrors(['f' => $msg])` compares `Str::before($msg, ':')` — a colon-bearing message never matches (use the Closure overload).
- **A rejection's landing place is set by the console action's SHAPE.** Verb-shaped ⇒ danger notification; form-shaped ⇒ validation error on ONE designated field — *every* rejection of that action, since the kit cannot type-discriminate `RuntimeException`s (and must not, design L4).
- **`assertNotified()` asserts the TITLE and PULLS the session.** Snapshot a notification's BODY first, off `filament.claimed_notifications`.
- **A Filament blank `Textarea` dehydrates to `null`, never `''`** — a page's `=== ''` arm is unreachable defence-in-depth. Mark it; let no test claim to exercise it.
- **`ApprovalGovernance::creatorOf` reads the entity's EARLIEST `domain_events` row, unfiltered.** A factory-built entity has no creation event, so an SoD-subject test must build through the real `Create*` lineage.
- **`version` is the IDENTITY version.** *No `version`* / *no re-arm* / *no event* are ONE fact. A Composite's ordered constituent set IS its identity (it re-versions and re-arms); enrichment and the whitelist are not.
- **The grep is the candidate set; only the FULL suite is the blast radius.** Before shipping a defensive guard, delete it and see if anything reds.
