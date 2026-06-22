---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-holds` — task 1.2 DONE, 2/12).** Pinned the non-relation Holds-table vehicle: a `Filament\Widgets\TableWidget` (`CustomerHoldsTable`) hosting a `Hold::query()` table with a placeholder per-row `recordActions()` action, mounted on `ViewCustomer` via `getFooterWidgets()` (record passed explicitly). Finished a prior crashed iteration's uncommitted work; re-verified EVERY Filament API name against the installed 5.6.7 vendor tree (arch-from-memory ban). Vehicle recorded in the `ViewCustomer` docblock.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: SQLite 1399/1399 (7682 assn, +2 tests/+3 assn vs 1.1's 1397/7679); phpstan 0; pint clean; validate --strict valid.** PG17 NOT re-run (render-only vehicle; PG run is task 5.2).
- **Full suite OOMs at PHP's default 128 MB** → run `php -d memory_limit=-1 vendor/bin/pest`. `--filter` runs + `phpstan` are fine at the default.
- PG17 ritual (§2.7): `docker run -d --name pg … postgres:17 -p 55432:5432` → poll `pg_isready` → `DB_CONNECTION=pgsql DB_PORT=55432 … php -d memory_limit=-1 vendor/bin/pest <folder>` (+ a Catalog i18n test so the shared sink helper loads) → `docker rm -f pg`. i18n tests via `--filter`/full suite, NEVER a bare path.

## Active Change & Next Task
- **Active: `operator-console-parties-holds` (APPROVED). 2/12 done.**
- **Next: task 1.3** — front-load i18n: extend the `customer` block in `lang/en/` AND `lang/it/operator_console.php` with `actions.{place_hold,lift_hold}`, `fields.{hold_type,hold_scope,profile,reason,lift_reason}`, `holds.columns.{hold_type,scope_type,status,reason,placed_by,placed_at,lifted_by,lifted_at}`, `notifications.{hold_placed,hold_lifted}` (reuse existing `action_failed`). Every IT value MUST differ from EN. Enumerate the new keys in `CustomerConsoleI18nTest` (`customerConsoleKitKeys()` + IT-differs set). Verify via `--filter=CustomerConsoleI18nTest` or full suite.
- **Then:** 2 (full scope-set read table) → 3 (placeHold header form) → 4 (per-row Lift) → 5 (PG17 closing-chain) → 6 (quality + memory).

## Blockers & Decisions Needed
- **None blocking.** Landmines: (1) per-row Lift (task 4) keys off a **Hold id**, not the page record — build bespoke, reuse `surfaceLifecycleOutcome`. (2) `ModuleBoundariesTest` stays **UNCHANGED** — the carve-out already admits the whole `Parties\Enums` prefix; import `HoldType`/`HoldScope` as operands, do NOT widen. (3) Hold→status coupling is **domain-owned + additive** — console calls only `PlaceHold`/`LiftHold`, never `Suspend*`/`Reactivate*`, never recomputes suspension.

## Open Patterns
- **Non-relation row-action table = `TableWidget` footer widget** on the `ViewRecord` via `getFooterWidgets()` + explicit `record`; `recordActions(array<Filament\Actions\Action|ActionGroup>)` (v5 rename of `actions()`); `$isLazy=false` for inline render. Place under `…/{Resource}/Widgets/` (outside the panel's discovered `Filament/Widgets/`).
- **Operand-enum carve-out**: allowlist admits the whole `Parties\Enums` prefix for OperatorPanel → operand enums import freely; state enums (`HoldStatus`) stay cast-only. No per-slice boundary-test edit.
- **Non-catalog status-FSM view = `ViewRecord` + `use SurfacesDomainActions` + bespoke `getHeaderActions()`** (rule-of-three / D8 CLOSED).
- **Multi-operand / Hold-id writes** → bespoke Filament action reusing `surfaceLifecycleOutcome()` for the uniform reject→`action_failed` (base-`RuntimeException` catch → no `Exceptions` import).
- **Console i18n completeness test = enumerate kit contract + 5 guards** (proven 6×); recipe in archived Customer change's `progress.md`.
