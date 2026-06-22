---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-holds` — task 3.2 DONE, 6/12).** Wired the `placeHold` write-through on `ViewCustomer::placeHoldAction()`: replaced the WIP stub with a `function (array $data): void` closure that `is_string`-narrows every operand (`hold_type`/`scope_type` floor to `''`, `profile_id` → `?string`, `reason` → NULL-on-blank), parses `$holdScope = HoldScope::from($scope)` ONCE, then `surfaceLifecycleOutcome(fn () => app(PlaceHold::class)->handle(HoldType::from($type), $holdScope, $this->holdScopeId(...), $reason), …hold_placed)`. New `holdScopeId(Customer, HoldScope, ?string): int`: `Customer => $customer->id`, `Account => (int) $customer->account?->id`, `Profile => (int) $profileId` (L4/D4). Reuses the kit trait (no fork — D3); console invokes ONLY `PlaceHold` (coupling domain-owned + additive — D7). +3 tests; full recipe in progress.md.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: SQLite 1438/1438 (7818 assn, +3 tests/+38 assn vs 3.2-start's 1435/7780); CustomerHoldsConsoleTest 7/7 (71 assn); phpstan max 0; pint + pint --test clean; validate --strict valid.** `ModuleBoundariesTest` + `NoEloquentWriteInOperatorPanelRule` 4/4 (201 assn) green UNCHANGED (carve-out admits `Parties\Actions\PlaceHold` + enums by prefix; write routes through the Action, no `$model->save()`). PG17 NOT re-run (closing-chain PG run is task 5.2).
- **Full suite OOMs at PHP's default 128 MB** → run `php -d memory_limit=-1 vendor/bin/pest`. `--filter` + `phpstan` are fine at the default, BUT a filter that includes the arch tests can OOM in the result PARSER (`TestResultParsable`) → use the flag there too.
- PG17 ritual is task 5.2 only (GUIDE §2.7): docker `postgres:17 -p 55432` → `DB_CONNECTION=pgsql DB_PORT=55432 … pest <Parties folder>` + a Catalog i18n test (shared sink helper) → `docker rm -f pg`.

## Active Change & Next Task
- **Active: `operator-console-parties-holds` (APPROVED). 6/12 done.**
- **Next: task 4.1** — per-row `lift` action on the Holds table widget `CustomerHoldsTable` (the console's FIRST per-row action). Visible iff `$row->status === HoldStatus::Active && ! $row->hold_type->autoLiftable()` (admin/fraud/compliance/credit). Optional `lift_reason` Textarea. Wire `->action(fn () => $this->surfaceLifecycleOutcome(fn () => app(LiftHold::class)->handle($row->getKey(), $reason), …hold_lifted))`. Do NOT import `IllegalHoldLift` (caught by base `RuntimeException`). **Verify FIRST:** `surfaceLifecycleOutcome`/`i18nKey` live on the page trait — the widget is a `TableWidget`, so confirm whether to `use SurfacesDomainActions` on it or wire equivalently.
- **Then:** 4.2 (lift/restore coupling + reject) → 5 (PG17 chain) → 6 (quality + memory).

## Blockers & Decisions Needed
- **None blocking.** Landmines: (1) per-row Lift keys off a **Hold id** (`$row->getKey()`), NOT the page record. (2) `ModuleBoundariesTest` stays **UNCHANGED** — `Parties\{Actions,Enums}` carve-out admits `LiftHold`/`HoldType` by prefix; `HoldStatus` stays cast-only. (3) coupling is domain-owned — console calls only place/lift.

## Open Patterns
- **Bespoke-action WRITE-THROUGH (multi-operand)** = `->action(function (array $data) { …is_string-narrow each… surfaceLifecycleOutcome(fn () => app(Action)->handle(…), $title); })` reusing the kit trait (no fork — D3). Filament injects `array $data` by name; read the record via `$this->getRecord()`. **phpstan trap:** an inline closure `@param array<string,mixed> $data` is NOT honored when `$data` is passed to a method typed `array<string,mixed>` — narrow IN the closure, pass typed scalars (`?string`) to the resolver. Scope `match`: nullable `hasOne` → `(int) $x?->id`.
- **(earlier patterns in progress.md)**: bespoke form-bearing header action + `->live()`/`->visible()` field; mount-and-inspect form test; non-relation scope-set OR-query table; `TableWidget` row-action vehicle + `getFooterWidgets()`; concrete-model widget `$record` (`?Customer`); i18n split `holds.columns.*` vs `fields.hold_*`.
