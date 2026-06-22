---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-holds` — task 3.1 DONE, 5/12).** Added the `placeHold` BESPOKE header action to `ViewCustomer::getHeaderActions()` (a 5th action beside the four form-less status verbs) via `placeHoldAction()`. Built directly (`Action::make('placeHold')->schema([...])`) — NOT the kit's single-`notes` `lifecycleAction()` — for its multi-operand form: `hold_type` Select (6 HoldType), `scope_type` Select (3 HoldScope, `->live()`), `profile_id` Select (over `$record->profiles`, `->visible()` only when `scope_type==='profile'`), optional `reason` Textarea. Helpers `holdTypeOptions()`/`holdScopeOptions()` (value→value maps), `profileOptions()` (id→`club->display_name`). Form only COLLECTS — `->action(function (): void {})` is a WIP stub; write-through lands in 3.2. Imported `HoldType`+`HoldScope` (Parties\Enums prefix carve-out, NO widening), `Select`/`Textarea`/`Get`, `Profile`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: SQLite 1435/1435 (7780 assn, +1 test/+17 assn vs 3.1-start's 1434/7763 — the new form-inspect test); CustomerHoldsConsoleTest 4/4 (33 assn); phpstan max 0; pint + pint --test clean; validate --strict valid.** `ModuleBoundariesTest` + `NoEloquentWriteInOperatorPanelRule` green UNCHANGED. PG17 NOT re-run (render-only; PG run is task 5.2).
- **Full suite OOMs at PHP's default 128 MB** → run `php -d memory_limit=-1 vendor/bin/pest`. `--filter` runs + `phpstan` are fine at the default.
- PG17 ritual (task 5.2 only; GUIDE §2.7): docker `postgres:17 -p 55432` → poll `pg_isready` → `DB_CONNECTION=pgsql DB_PORT=55432 … pest <folder>` + a Catalog i18n test (loads the shared sink helper) → `docker rm -f pg`. i18n tests via `--filter`/full suite, never a bare path.

## Active Change & Next Task
- **Active: `operator-console-parties-holds` (APPROVED). 5/12 done.**
- **Next: task 3.2** — wire `placeHold` write-through (full recipe in progress.md). Replace the stub `->action(function (): void {})` with `->action(fn (array $data) => $this->surfaceLifecycleOutcome(fn () => app(PlaceHold::class)->handle(HoldType::from($type), HoldScope::from($scope), $scopeId, $reason), …hold_placed))`. Resolve `$scopeId`: `customer`→`$record->id`, `account`→`$record->account->id`, `profile`→ selected `profile_id`. Narrow EVERY `$data` value with `is_string`. NO `$model->save()`. Test: admin Hold on `active` → 1 Hold + `CustomerHoldPlaced`+`CustomerSuspended`, now `suspended`; on `pending` → no `CustomerSuspended`; `account` scope → Hold `scope_type=account`.
- **Then:** 4 (per-row Lift + coupling) → 5 (PG17 closing-chain) → 6 (quality + memory).

## Blockers & Decisions Needed
- **None blocking.** Landmines: (1) per-row Lift (task 4) keys off a **Hold id**, not the page record — build bespoke, reuse `surfaceLifecycleOutcome`. (2) `ModuleBoundariesTest` stays **UNCHANGED** — `Parties\Enums` carve-out admits the operand enums by namespace prefix (verified in source); state enum `HoldStatus` stays cast-only, do NOT widen. (3) Hold→status coupling is **domain-owned + additive** — console calls only `PlaceHold`/`LiftHold`, never `Suspend*`/`Reactivate*`, never recomputes suspension.

## Open Patterns
- **Bespoke form-bearing header action** = `Action::make(id)->schema([...])->action(…)` (multi-operand verbs the single-`notes` `lifecycleAction()` can't thread). Operand-enum Select options = value→value `collect(Enum::cases())->mapWithKeys(...)`. **Conditional visibility** (console's FIRST `->live()`): controller `->live()`, dependent `->visible(fn (Get $get) => $get('ctrl') === Enum::Case->value)` (`Get` = `Filament\Schemas\Components\Utilities\Get`). Record-dependent options pass a Closure so they resolve at render.
- **Form-action test** = `mountAction(id)` → `assertFormFieldExists('f', fn (Select $f) => array_keys($f->getOptions()) === array_map(...Enum::cases()))` (auto-targets mounted-action schema) → `setActionData([...])`/`assertFormFieldHidden|Visible` for `->live()` visibility. `getOptions()` returns the flat array verbatim.
- **(earlier patterns in progress.md Codebase Patterns)**: non-relation scope-set table (`Hold::query()->where(orWhere×3)`, the `DatabaseComplianceStatusReader` idiom; nullable Account `?->account?->id`; import-free cast columns); concrete-model widget `$record` (`?Customer`); i18n split `holds.columns.*` (terse headers) vs `fields.hold_*` (form labels).
