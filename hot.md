---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-holds` — task 4.2 DONE, 8/12).** TEST-ONLY (+2 tests, no production change) on `CustomerHoldsConsoleTest`. (1) **Lift+restore coupling**: arranged via the REAL `app(PlaceHold::class)->handle(...)` (NOT the factory — it bypasses the coupling) → two active customer-scope Holds (`admin`+`fraud`) drive Customer `suspended`; lift `admin` via `callTableAction('lift', $admin, ['lift_reason'=>…])` → 1 `CustomerHoldLifted`, STAYS `suspended` (fraud still covers), NO `CustomerReactivated`; lift last (`fraud`) → 2nd `CustomerHoldLifted` + one `CustomerReactivated` (envelope module `parties`/entity_type `Customer`/entity_id `(string)$id`/`NewcoOps`+loose op-id) → `active`. (2) **kyc rejection (defense-in-depth)**: `assertTableActionHidden('lift', record:$kyc)` + `expect(fn()=>app(LiftHold::class)->handle($kyc->id))->toThrow(IllegalHoldLift::class)`, Hold unchanged + no event.

**DEVIATION (documented — tasks.md `> NOTE` + lessons.md):** the literal "drive a kyc lift THROUGH THE WIDGET + `assertNotified(action_failed)`" is **structurally infeasible**. Two probes proved Filament refuses to mount/run a HIDDEN action server-side (`getMountedActions()===[]`; a row hidden out-of-band stops invoking mid-flight — visibility re-resolved EVERY call). The lift's `->visible()` predicate is the EXACT complement of `LiftHold`'s reject conditions, so the widget's `action_failed` branch is UNREACHABLE for a lift rejection. Tested the achievable D6 halves; the kit's `RuntimeException→action_failed` is a shared guarantee (its SUCCESS half — `hold_lifted` — fires in this widget in test 1).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: SQLite 1441/1441 (7868 assn, +2 tests/+35 assn vs 4.1's 1439/7833); CustomerHoldsConsoleTest 10/10 (121 assn); phpstan max 0; pint + pint --test clean; validate --strict valid.** Arch tests (`ModuleBoundariesTest` + `NoEloquentWriteInOperatorPanelRule`) green UNCHANGED — test-only Parties imports are fine (the carve-out governs PRODUCTION code, not tests). PG17 NOT re-run (closing-chain PG run is task 5.2).
- **Full suite OOMs at PHP's default 128 MB** → run `php -d memory_limit=-1 vendor/bin/pest`. `--filter` + `phpstan` fine at default; a `--filter` that pulls in the arch tests can OOM the result PARSER → use the flag there too.

## Active Change & Next Task
- **Active: `operator-console-parties-holds` (APPROVED). 8/12 done.**
- **Next: task 5.1** — `tests/Feature/Modules/OperatorPanel/Parties/CustomerHoldsChainTest.php`, `uses(DatabaseMigrations::class)` (NOT `RefreshDatabase` — each action opens its own `DB::transaction`; the in-tx event append must commit). Drive the full chain through the page/table actions: place `admin` on `active` → `suspended` (`CustomerHoldPlaced`+`CustomerSuspended`); place `fraud` → still suspended; lift `admin` → no restore; lift `fraud` (last) → `CustomerReactivated`, `active`; place `admin` on a `pending` Customer → Hold recorded, no transition; assert per-row `lift` ABSENT on a `kyc` row + an operator lift of it rejected. Assert emergent `DomainEvent` set with `pluck('name')->toEqualCanonicalizing([...])` + per-event envelope. **Proven vehicles (re-use):** place = `callAction('placeHold', [...])` on `ViewCustomer`; lift = `callTableAction('lift', $hold, [...])` on `CustomerHoldsTable` (record is 2nd positional). **kyc-lift rejection = DOMAIN-THROW** (`toThrow(IllegalHoldLift)`), NOT an `action_failed` notification (widget can't surface it — see lessons.md 2026-06-22).
- **Then:** 5.2 (PG17 ritual) → 6.1/6.2 (quality + memory).

## Blockers & Decisions Needed
- **None blocking.** Landmine (NEW, lessons.md 2026-06-22): a Filament HIDDEN record-action's closure is unreachable via ANY test helper. Older landmines: key per-row off typed `$record->id` (not `getKey()` → phpstan `cast.int`); state enums (`HoldStatus`) cast-only even in predicates (`status->value==='active'`); `assertTableActionHidden/Visible('x', record:$h)` = per-record visibility vs `assert…Exists/DoesNotExist` = registry.

## Open Patterns
- **Testing a per-row write-through action + its defense-in-depth reject** consolidated in progress.md `## Codebase Patterns`: VISIBLE action → `callTableAction(name, $record, $data)` + `assertNotified(success)`; arrange coupling via the real domain Action (not the factory); HIDDEN reject branch is UNREACHABLE → test as `assertTableActionHidden` + domain `toThrow`, never a widget notification. PG17 ritual recipe (task 5.2) lives in tasks.md.
