---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (eve) — task 7.1 green; the docs now tell the truth.** Ralph loop on `catalog-module-0-completeness-sweep`. **15 of 16 done**; only the full verify (7.2) remains.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2205/2205 SQLite** (11 673 assertions — 7.1 is comment-only, counts unchanged) · PHPStan **0** · Pint clean · `validate --strict` valid.
- PG17: last FULL run 2080/2080 (pre-1.1); 6.3 re-ran 228/228 on the console catalog surfaces. **7.2 re-runs the WHOLE suite there.**
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). **PG17:** prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- Branch `ralph/catalog-module-0-completeness-sweep` — 12 delta reqs · design D1–D11 · 16 tasks. Interview decisions: `design.md`.
- **DONE: 1.1–1.3 · 2.1–2.3 · 3.1–3.2 · 4.1 · 5.1–5.2 · 6.1–6.3 · 7.1.** All code shipped; all stale claims swept.
- Every reusable mechanic: `progress.md` → `## Codebase Patterns` (129 bullets). **Read it first.**
- **NEXT: 7.2** — full verify + wrap. (a) FULL suite on **PG17** *and* SQLite; PHPStan 0; Pint; `validate --strict`. (b) **Traceability table in progress.md**: every ADDED/MODIFIED delta requirement's scenarios → its covering tests. (c) Name the deferred seams once: Module A Layer-2 · the Module S `EnrichmentDataUpdated` consumer · enrichment adapter columns · the `producer_name` runtime projection. (d) Consolidate Codebase Patterns. Tracker/hot/log = **session-close**, not 7.2.
- Landmines: R5 (console keys EN+IT; catalog **domain** reasons EN-only), R6 (a `{@see FQCN}` on a `Catalog\Events`/`Lifecycle` type — Pint auto-imports it, redding `ModuleBoundariesTest`), `creatorOf` (below).
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first** — own session.

## Blockers & Decisions Needed
- None. Humans push and archive.

## Open Patterns
- **Uniformity of the OLD claim is no evidence of uniformity of the NEW one** (new, `lessons.md` 2026-07-08). A scripted doc sweep fans a *new* assertion out N times: 7.1's first pass told all 7 `Resubmit*` docblocks that re-submit clears an identity edit — only Master + Composite have that path. Enumerate from the code which files the replacement is TRUE for.
- **Three Filament test helpers lie, each failing as if the SUT were broken** (`knowledge/filament/hypotheses.md`): `callAction($n, ['list' => [a]])` against an `[a,b]` prefill submits `[a,b]` (shrink via `mountAction → set('mountedActions.0.data.<f>', […]) → callMountedAction()`); `assertHasActionErrors(['f' => $msg])` compares `Str::before($msg, ':')`, so a colon-bearing message never matches (use the Closure overload); `assertNotified()` asserts the TITLE and PULLS the session (snapshot a BODY off `filament.claimed_notifications` first).
- **A rejection's landing place is set by the console action's SHAPE.** Verb-shaped ⇒ danger notification; form-shaped ⇒ validation error on ONE designated field, for *every* rejection of that action (design L4).
- **`ApprovalGovernance::creatorOf` reads the EARLIEST `domain_events` row, unfiltered.** A factory-built entity has no creation event, so an SoD-subject test must build through the real `Create*` lineage.
- **`version` is the IDENTITY version.** *No `version`* / *no re-arm* / *no event* are ONE fact — a Composite's constituent set IS its identity; enrichment and the whitelist are not.
- **The grep is the candidate set; only the FULL suite is the blast radius.**
