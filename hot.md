---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-holds` — task 2.1 DONE, 4/12).** Widened `CustomerHoldsTable` from the 1.2 customer-scope placeholder to the full read table: a scope-set UNION query `Hold::query()->where(orWhere customer ∪ orWhere account ∪ orWhere profile)` over the polymorphic `(scope_type, scope_id)` (no FK), mirroring `DatabaseComplianceStatusReader`'s cascade idiom. 8 read columns — `hold_type`/`scope_type`/`status` via a new import-free `castValueState()` (getAttribute + `instanceof BackedEnum` → `->value`), `reason`, `placed_by` (`role #id`), `created_at` (placed-at), `lifted_by`, `lifted_at`. Kept the inert `placeholder` row action (4.1 swaps for `lift`). Retyped `$record` `?Model → ?Customer`. Shows both active + lifted Holds.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: SQLite 1434/1434 (7763 assn, +1 test/+13 assn vs 1.3's 1433/7750 — the new 3-scope read test); CustomerHoldsConsoleTest 3/3 (16 assn); phpstan 0 (incl. NO `nullsafe.neverNull` on `$customer?->account?->id`); pint clean; validate --strict valid.** PG17 NOT re-run (render-only; PG run is task 5.2).
- **Full suite OOMs at PHP's default 128 MB** → run `php -d memory_limit=-1 vendor/bin/pest`. `--filter` runs + `phpstan` are fine at the default.
- PG17 ritual (§2.7): `docker run -d --name pg … postgres:17 -p 55432:5432` → poll `pg_isready` → `DB_CONNECTION=pgsql DB_PORT=55432 … php -d memory_limit=-1 vendor/bin/pest <folder>` (+ a Catalog i18n test so the shared sink helper loads) → `docker rm -f pg`. i18n tests via `--filter`/full suite, NEVER a bare path.

## Active Change & Next Task
- **Active: `operator-console-parties-holds` (APPROVED). 4/12 done.**
- **Next: task 3.1** — the `placeHold` HEADER action on `ViewCustomer` (NOT the widget): form with `hold_type` Select over `HoldType::cases()`, `scope_type` Select over `HoldScope::cases()`, a `profile_id` Select over `$record->profiles` visible only when `scope_type === 'profile'`, optional `reason` Textarea. Import `App\Modules\Parties\Enums\HoldType` + `HoldScope` (operand enums — the `CreateClub`→`ClubRegistrationFlowType` precedent; carve-out already admits the prefix). Use `fields.hold_type`/`fields.hold_scope`/`fields.profile`/`fields.reason` labels (DESCRIPTIVE form group), NOT `holds.columns.*` (terse headers). Test: form exposes 6 HoldType + 3 HoldScope options, `profile_id` hidden unless scope=profile.
- **Then:** 3.2 (write-through `surfaceLifecycleOutcome`+`app(PlaceHold::class)`) → 4 (per-row Lift + coupling) → 5 (PG17 closing-chain) → 6 (quality + memory).

## Blockers & Decisions Needed
- **None blocking.** Landmines: (1) per-row Lift (task 4) keys off a **Hold id**, not the page record — build bespoke, reuse `surfaceLifecycleOutcome`. (2) `ModuleBoundariesTest` stays **UNCHANGED** — `Parties\Enums` carve-out already admits the operand enums; state enum `HoldStatus` stays cast-only, do NOT widen. (3) Hold→status coupling is **domain-owned + additive** — console calls only `PlaceHold`/`LiftHold`, never `Suspend*`/`Reactivate*`, never recomputes suspension.

## Open Patterns
- **Concrete-model widget record**: type the public `$record` to the model (`?Customer`) once the widget reads its relations (`account`/`profiles`), not just `getKey()` — the Eloquent synth keys off the runtime value, so it rehydrates identically while phpstan resolves the relations without an `instanceof` dance.
- **Non-relation scope-set table** = `Hold::query()->where(orWhere×3)` over `(scope_type, scope_id)` (the `DatabaseComplianceStatusReader` idiom); nullable Account → `?->account?->id`; profile-less → empty `whereIn` (`0 = 1`). Cast enum columns import-free via `getStateUsing` + `instanceof BackedEnum`; composite `role #id` reads the typed `@property` enum directly (no import).
- **i18n terse-vs-descriptive split**: `holds.columns.*` = terse table headers (used by 2.1's read table); `fields.hold_*` = descriptive form labels (use for 3.1/4.1 place/lift forms). Keys front-loaded in 1.3.
- **Console i18n completeness test** = enumerate kit contract + 5 guards (proven 7×); IT-differs dataset auto-derives via `array_diff`.
