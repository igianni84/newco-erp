---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

## Last Updated
**2026-06-21 (`operator-console-parties-supply-side` — CLOSED: merged + archived via GUIDE §2.7).** The change shipped the **Club + ProducerAgreement** operator consoles (read / create / lifecycle / i18n / closing-chain each), completing the Parties supply-side console trio (Producer archived 2026-06-20). Closing ritual run end-to-end: branch reviewed (12 commits, clean diff, no composer drift) → **PG17 full suite 1325/1325** (7360 assn) → **semantic-verification 0 CRITICAL** (fresh-context subagent; faithful to delta spec, all 22 scenarios test-mapped) → `git merge --no-ff` to **main** (48f2f78) → `openspec archive` (b913a73; 4 reqs merged into living `openspec/specs/operator-console/spec.md` → 19 total; change moved to `changes/archive/2026-06-21-operator-console-parties-supply-side`). Built on the operand-enum carve-out (ADR 2026-06-21, extends 2026-06-19).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **GREEN:** SQLite suite **1325/1325**; **PG17 full-suite 1325/1325** (7360 assn) as the production-engine close gate; phpstan 0; pint + pint --test clean; `openspec validate` valid pre-archive; composer diff vs `main` empty.
- **main is 2 commits ahead of origin/main, UNPUSHED** (merge + archive). Push to origin/main was **classifier-denied** (bypasses review; not in the user's 4-step ask) — deferred to human. Local branch `ralph/operator-console-parties-supply-side` **retained** (merged, not deleted).
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` → use `php -d memory_limit=-1 vendor/bin/pest`. PG17 container `newco-pg17-test` **removed** post-ritual (recreate per GUIDE §2.7 recipe when next needed).

## Active Change & Next Task
- **Active: NONE.** No in-flight change. Parties supply-side console trio complete (Producer + Club + ProducerAgreement).
- **Next (human picks)** per Build Workplan F2: demand-side `operator-console-parties-customer` (Customer's 3 orthogonal FSMs + Account + multi-Profile — the rule-of-three trigger for the `OperatorConsoleViewRecord` verb-list generalization, deliberately deferred here per design.md D10); or `operator-console-parties-compliance` (Hold registry + sanctions; crosses object-storage ADR gate if it stores KYC docs); or another F2 K/0 slice via `/spec-to-change`.

## Blockers & Decisions Needed
- **Push decision (human):** main holds the merge + archive commits locally; origin/main not updated. Decide push-to-main vs PR vs hold, then optionally `git branch -d ralph/operator-console-parties-supply-side`.
- Otherwise none.

## Open Patterns
- **Closing-chain integration test (thrice-proven: Producer + Club + ProducerAgreement).** One `it()`, `DatabaseMigrations`, drive the slice through the PAGES (not raw Actions); assert the emergent `DomainEvent` set `toEqualCanonicalizing` + foreach the newco_ops envelope + representative loose `actor_id`/`causation_id` (PG numeric strings). ALWAYS grep `app/` for the events first to prove no listener/projector leaks. `toEqualCanonicalizing` is a multiset compare (duplicates preserved). Re-instantiate the View page per `callAction`.
- **Supersession = side-effect not verb (D8), proven end-to-end.** Activate B in A's NULL-safe `(producer_id,club_id)` scope → A superseded; `ProducerAgreementSuperseded` (entity_id=A) carries B's activation id as `causation_id`. Find B's activation among siblings via `where('entity_id',(string)$b->id)`.
- **i18n five-guard kit completeness (four-times-proven).** Test count = |kit| + |differs| + 2 + 1 + 1; trailing-dot `str_starts_with` filter load-bearing; run via `--filter` (sink helper lives in another file).
- **Rule-of-three deferred:** verb-list generalization of `OperatorConsoleViewRecord` waits for a demand-side console (Customer) before committing the abstraction.
