---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-holds` — task 5.1 DONE, 9/12).** TEST-ONLY (+1 test, no production change): new `CustomerHoldsChainTest.php` — the change's CLOSING integration proof. ONE `it()` drives the WHOLE Holds console slice end-to-end through the proven vehicles (placeHold = `callAction('placeHold', [...])` on `ViewCustomer`; per-row lift = `callTableAction('lift', $hold, [...])` on `CustomerHoldsTable`), then asserts the emergent `DomainEvent` set. Chain: A `active` — place `admin`→`suspended` (`CustomerHoldPlaced`+`CustomerSuspended`); place `fraud`→still `suspended`; lift `admin`→`CustomerHoldLifted`, NO restore; lift `fraud` (last)→2nd `CustomerHoldLifted`+`CustomerReactivated`→`active`; B `pending` — place `admin`→Hold recorded, stays `pending`; factory `kyc` Hold on B → `assertTableActionHidden('lift', record:$kyc)` + `expect(fn()=>app(LiftHold)->handle($kyc->id))->toThrow(IllegalHoldLift)`, Hold unchanged + no event. Emergent set via `pluck('name')->toEqualCanonicalizing([...])` = 3×`CustomerHoldPlaced` + 1×`CustomerSuspended` + 2×`CustomerHoldLifted` + 1×`CustomerReactivated` (7); SET-WIDE `module=parties`/`NewcoOps`/non-null + `toHaveCount(7)`; representative envelope spanning BOTH entity types.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: SQLite 1442/1442 (7950 assn, +1 test/+82 assn vs 4.2's 1441/7868); CustomerHoldsChainTest 1/1 (82 assn); phpstan max 0; pint + pint --test clean; validate --strict valid.** Arch tests (`ModuleBoundariesTest` + `NoEloquentWriteInOperatorPanelRule`) green within the full suite — test-only Parties imports are fine (the carve-out governs PRODUCTION code, not tests). **PG17 NOT YET run — that IS task 5.2.**
- **Full suite OOMs at PHP's default 128 MB** → run `php -d memory_limit=-1 vendor/bin/pest`. `--filter` + `phpstan` fine at default; a `--filter` that pulls in the arch tests can OOM the result PARSER → use the flag there too.

## Active Change & Next Task
- **Active: `operator-console-parties-holds` (APPROVED). 9/12 done.**
- **Next: task 5.2** — the PG17 ritual (GUIDE §2.7), VERIFY-ONLY (no push/merge): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17` → poll `pg_isready` → `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest tests/Feature/Modules/OperatorPanel/Parties tests/Feature/Modules/OperatorPanel/Catalog/ProductMasterConsoleI18nTest.php` (the appended Catalog i18n test loads the shared `scanOperatorConsoleHardcodedSinks` helper) → `docker rm -f pg`. Acceptance: the Parties OperatorPanel folder green under PG17. The new chain test is PG-safe by design (loose `actor_id` `toEqual`; events asserted by NAME + envelope, never a jsonb byte-compare).
- **Then:** 6.1 (quality gates: pint + phpstan max incl. arch tests UNCHANGED + full suite; diff touches no `spec/**`/`openspec/specs/**`, no composer dep, no migration) → 6.2 (validate --strict + memlog + hot.md + consolidate Codebase Patterns).

## Blockers & Decisions Needed
- **None blocking.** Landmines (all consolidated in progress.md `## Codebase Patterns`): closing-chain envelope is HETEROGENEOUS (Hold verbs → `entity_type=Hold`; coupling → `entity_type=Customer`) — disambiguate a repeated event by `->where('entity_id', (string)$hold->id)`. Filament HIDDEN record-action closure is unreachable via ANY test helper → kyc-lift reject = domain `toThrow`, not a widget notification (lessons.md 2026-06-22). Key per-row off typed `$record->id` (not `getKey()` → phpstan `cast.int`); state enums (`HoldStatus`) cast-only even in predicates.

## Open Patterns
- **NEW (consolidated in progress.md):** the closing-chain integration recipe — `DatabaseMigrations`, three-part emergent proof (`toEqualCanonicalizing` multiset → SET-WIDE loop + `toHaveCount` → representative per-event envelope), determinism guard (factories + rolled-back throws record no event; profile-less entities keep cascades silent), pin coupling no-ops at point-of-cause. Plus the per-row write-through + defense-in-depth reject patterns from 4.1/4.2. PG17 ritual recipe lives in tasks.md 5.2 + the Active section above.
