---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

## Last Updated
**2026-06-21 (`operator-console-parties-supply-side` — task 11.1 of 19 DONE → CHANGE COMPLETE, 19/19).** Added `ProducerAgreementConsoleChainTest.php` — the change's CLOSING integration proof, one `it()` driving the WHOLE ProducerAgreement slice through the PAGES (the renewal arc): seed Producer event-free → `CreateProducerAgreement` (A, Producer-wide, `settlement_cadence='monthly'`, draft) → `callAction('activate')` on A (no prior → no supersession) → `CreateProducerAgreement` (B, same NULL-club scope, `'quarterly'`, draft) → `callAction('activate')` on B (B→active, A→**superseded** inline, D8 OR-branch) → `callAction('terminate')` on B. A/B distinguished by settlement cadence (scope-neutral); `Livewire::test(ViewProducerAgreement::class,['record'=>$id])` re-instantiated per `callAction` (group-6 idiom). Asserts: (a) emergent set `toEqualCanonicalizing([Created,Activated,Created,Activated,Superseded,Terminated])` (6 events); (b) `toHaveCount(6)` + foreach `module==='parties'`/`actor_role===NewcoOps`/non-null `actor_id`; (c) representative `actor_id` `toEqual($operator->id)` on B's Created (create surface) + Terminated (view surface), loose for PG; (d) the single `ProducerAgreementSuperseded` is `entity_id`=A and carries B's `ProducerAgreementActivated` id as `causation_id` (loose; found via `where('entity_id',(string)$b->id)`). **Test-only — every page/key shipped groups 7–10.** Grep-verified zero listeners/projectors consume the four agreement events → emergent set is exactly 6, no projection leaks.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **GREEN — CHANGE COMPLETE:** full SQLite suite **1325/1325** (7360 assn, +1 test/+52 assn); `ProducerAgreementConsoleChainTest` 1/1 (52 assn); phpstan 0; pint + pint --test clean; **PG17 folder-wide run 202/202** (951 assn — `tests/Feature/Modules/OperatorPanel/Parties` + appended `Catalog/ProductMasterConsoleI18nTest.php` for the shared sink helper); `openspec validate operator-console-parties-supply-side --strict` valid; composer diff vs `main` empty.
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` → use `php -d memory_limit=-1 vendor/bin/pest` / `… phpstan analyse`. **i18n tests reuse `scanOperatorConsoleHardcodedSinks` (in `…/Catalog/ProductMasterConsoleI18nTest.php`) → run via `--filter`/full suite, NOT a bare path; APPEND that Catalog file for a folder-wide PG17 run.** PG17: docker container `newco-pg17-test` (Up), prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active:** `operator-console-parties-supply-side` — **ALL 19 tasks `- [x]`** (1.1, 2.1–2.3, 3.1–3.2, 4.1–4.2, 5.1, 6.1, 7.1–7.3, 8.1–8.2, 9.1–9.2, 10.1, 11.1). Branch `ralph/operator-console-parties-supply-side`. Replied `<promise>CHANGE_COMPLETE</promise>`.
- **Next: NONE — change complete.** Humans review/merge/archive (RALPH does NOT archive or merge). The slice ships the Club + ProducerAgreement operator consoles (read / create / lifecycle / i18n / closing-chain each), built on the operand-enum carve-out (ADR 2026-06-21). After human merge: `openspec archive operator-console-parties-supply-side --yes`.

## Blockers & Decisions Needed
- **None.** Cleanup: `newco-pg17-test` docker container is still running — `docker rm -f newco-pg17-test` when done with the change.

## Open Patterns
- **Closing-chain integration test (thrice-proven: Producer + Club + ProducerAgreement).** One `it()`, `DatabaseMigrations`, drive the whole slice through the PAGES (not raw Actions); assert the emergent `DomainEvent` set `toEqualCanonicalizing` + foreach the newco_ops envelope + representative `actor_id`/`causation_id` (loose `toEqual` — PG numeric strings). ALWAYS grep `app/` for the events first to prove no listener/projector leaks before a whole-log assertion. `toEqualCanonicalizing` is a multiset compare (duplicates preserved). Re-instantiate the View page per `callAction`.
- **Supersession = side-effect not verb (D8), proven end-to-end.** Activate B in A's NULL-safe `(producer_id,club_id)` scope → A superseded, `ProducerAgreementSuperseded` (entity_id=A) carries B's `ProducerAgreementActivated` id as `causation_id`. To find B's activation among same-named siblings: `where('entity_id',(string)$b->id)`.
- **i18n five-guard kit completeness (four-times-proven).** Test count = |kit| + |differs| + 2 + 1 + 1. Trailing dot in the `str_starts_with` filter is load-bearing; run via `--filter` (sink helper in another file).
