---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-holds` — task 4.1 DONE, 7/12).** Wired the per-row `lift` on `CustomerHoldsTable` — the console's FIRST per-row action — replacing the inert `placeholder`. The widget now `use SurfacesDomainActions` + `i18nKey(): 'customer'`, REUSING the page's `surfaceLifecycleOutcome` kit (no fork — D3). The action: `->visible(fn (Hold $record) => self::isOperatorLiftable($record))`, optional `lift_reason` `Textarea`, `->action(function (Hold $record, array $data){…})` narrows the note (blank→NULL) then routes `app(LiftHold::class)->handle($record->id, $reason)` through the kit. `isOperatorLiftable` = `$hold->status->value === 'active' && ! $hold->hold_type->autoLiftable()` — status via cast `->value` (D2: `HoldStatus` STATE enum NEVER imported; pseudocode `HoldStatus::Active` is shorthand). `IllegalHoldLift` in prose only (D6). +1 test; recipe in progress.md.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: SQLite 1439/1439 (7833 assn, +1 test/+15 assn vs 4.1-start's 1438/7818); CustomerHoldsConsoleTest 8/8 (86 assn); phpstan max 0; pint + pint --test clean; validate --strict valid.** `ModuleBoundariesTest` + `NoEloquentWriteInOperatorPanelRule` 4/4 (201 assn) green UNCHANGED (carve-out admits `Parties\Actions\LiftHold` by prefix; `HoldStatus` not imported; lift routes through the Action, no `$model->save()`). PG17 NOT re-run (closing-chain PG run is task 5.2).
- **Full suite OOMs at PHP's default 128 MB** → run `php -d memory_limit=-1 vendor/bin/pest`. `--filter` + `phpstan` are fine at the default, BUT a filter that includes the arch tests can OOM in the result PARSER → use the flag there too.
- PG17 ritual is task 5.2 only (GUIDE §2.7; full recipe in tasks.md): docker `postgres:17` → `DB_CONNECTION=pgsql … pest <Parties folder>` + a Catalog i18n test → `docker rm -f pg`.

## Active Change & Next Task
- **Active: `operator-console-parties-holds` (APPROVED). 7/12 done.**
- **Next: task 4.2** — TEST-ONLY (no production change): the lift+restore coupling + rejection path in `CustomerHoldsConsoleTest`. (a) two active customer-scope Holds (`admin`+`fraud`) → Customer `suspended`: lift `admin` → one `CustomerHoldLifted`, stays `suspended`, NO `CustomerReactivated`; lift last (`fraud`) → `CustomerHoldLifted` + `CustomerReactivated`, → `active`. (b) an operator lift of a `kyc` Hold raises `IllegalHoldLift` → `action_failed` danger notification, Hold unchanged, no event (`assertNotified((string) __('operator_console.customer.notifications.action_failed'))`). **Drive via the widget** — `callAction('lift', ['lift_reason'=>…], record: $hold)` on `CustomerHoldsTable`; **VERIFY the Filament 5 table `callAction` record-targeting signature against the vendor tree FIRST** (arch-from-memory ban). Suspend via `PlaceHold` (the coupling), not the bare factory.
- **Then:** 5.1 (SQLite chain test, `DatabaseMigrations`) → 5.2 (PG17 ritual) → 6 (quality + memory).

## Blockers & Decisions Needed
- **None blocking.** Landmines: (1) per-row action keys off `$record->id` (typed `@property int $id`), NOT `getKey()` — `getKey()` is `mixed` → phpstan `cast.int`/`argument.type`. (2) state enums stay cast-only even in PREDICATES (`status->value === 'active'`), not just rendering — design D2. (3) `assertTableActionVisible/Hidden('lift', record: $h)` = per-record visibility; `assertTableActionExists/DoesNotExist` = registry only.

## Open Patterns
- **Per-row write-through action on a `TableWidget`** = reuse `SurfacesDomainActions` on the widget (`use` + `i18nKey()`); callbacks inject `(Hold $record, array $data)` by NAME (`$record` = ROW model); key off `$record->id`; visibility predicate on a state enum via cast `->value` (extract to a `private static isXLiftable(Hold): bool`); domain Action still enforces what visibility surfaces (`IllegalHoldLift`→base `RuntimeException`→`action_failed`, never imported). Full recipe + earlier patterns in progress.md `## Codebase Patterns`.
