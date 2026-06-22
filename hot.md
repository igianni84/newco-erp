---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-holds` — task 1.3 DONE, 3/12).** Front-loaded the 17 Hold-surface i18n keys into the `customer` block of BOTH `lang/en` and `lang/it/operator_console.php`: `actions.{place_hold,lift_hold}`, `fields.{hold_type,hold_scope,profile,reason,lift_reason}`, a new `holds.columns.{hold_type,scope_type,status,reason,placed_by,placed_at,lifted_by,lifted_at}` sub-block, `notifications.{hold_placed,hold_lifted}` (reusing existing `action_failed`). Enumerated all 17 in `customerConsoleKitKeys()`; IT-differs set auto-derives. Every IT value differs from EN. Hold→IT `blocco` (NOT `sospensione` — distinct from Suspend/`Sospendi`).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: SQLite 1433/1433 (7750 assn, +34 tests/+68 assn vs 1.2's 1399/7682 — all from the expanded i18n dataset); CustomerConsoleI18nTest 88/88 (177 assn); phpstan 0; pint clean; validate --strict valid.** PG17 NOT re-run (i18n-only; PG run is task 5.2).
- **Full suite OOMs at PHP's default 128 MB** → run `php -d memory_limit=-1 vendor/bin/pest`. `--filter` runs + `phpstan` are fine at the default.
- PG17 ritual (§2.7): `docker run -d --name pg … postgres:17 -p 55432:5432` → poll `pg_isready` → `DB_CONNECTION=pgsql DB_PORT=55432 … php -d memory_limit=-1 vendor/bin/pest <folder>` (+ a Catalog i18n test so the shared sink helper loads) → `docker rm -f pg`. i18n tests via `--filter`/full suite, NEVER a bare path.

## Active Change & Next Task
- **Active: `operator-console-parties-holds` (APPROVED). 3/12 done.**
- **Next: task 2.1** — render the read-only Holds table sourced by a direct `Hold::query()` over the Customer's scope-set `(scope_type=customer AND scope_id=$record->id) OR (account AND $record->account?->id) OR (profile AND scope_id IN $record->profiles->pluck('id'))`. Columns via the now-authored `customer.holds.columns.*` keys, rendered through the model casts (`->value`); import NO state enum (`HoldStatus` stays cast-only). Test `CustomerHoldsConsoleTest`: seed Holds at all three scopes, assert each renders + NO inline edit/delete affordance. **phpstan max: avoid `nullsafe.neverNull` — `$record->account` is a nullable `hasOne`.**
- **Then:** 3 (placeHold header form + write-through) → 4 (per-row Lift + coupling) → 5 (PG17 closing-chain) → 6 (quality + memory).

## Blockers & Decisions Needed
- **None blocking.** Landmines: (1) per-row Lift (task 4) keys off a **Hold id**, not the page record — build bespoke, reuse `surfaceLifecycleOutcome`. (2) `ModuleBoundariesTest` stays **UNCHANGED** — the `Parties\Enums` carve-out already admits the operand enums (`HoldType`/`HoldScope`); state enum `HoldStatus` stays cast-only, do NOT widen. (3) Hold→status coupling is **domain-owned + additive** — console calls only `PlaceHold`/`LiftHold`, never `Suspend*`/`Reactivate*`, never recomputes suspension.

## Open Patterns
- **i18n terse-vs-descriptive split**: `holds.columns.*` = terse table headers (`Type`/`Tipo`); `fields.hold_*` = descriptive form labels (`Hold type`/`Tipo di blocco`). Use the right group per surface (columns→read table 2.1, fields→place/lift forms 3–4). Keys are front-loaded — authored before the resolving code.
- **Non-relation row-action table = `TableWidget` footer widget** on the `ViewRecord` via `getFooterWidgets()` + explicit `record`; `recordActions(array<Filament\Actions\Action|ActionGroup>)` (v5 rename of `actions()`); `$isLazy=false` for inline render. Under `…/{Resource}/Widgets/` (outside the panel's discovered sweep).
- **Operand-enum carve-out** admits the whole `Parties\Enums` prefix for OperatorPanel → operand enums import freely; state enums stay cast-only. No per-slice boundary-test edit.
- **Multi-operand / Hold-id writes** → bespoke Filament action reusing `surfaceLifecycleOutcome()` for the uniform reject→`action_failed` (base-`RuntimeException` catch → no `Exceptions` import).
- **Console i18n completeness test = enumerate kit contract + 5 guards** (proven 7×); IT-differs dataset auto-derives from the kit-keys list via `array_diff`.
