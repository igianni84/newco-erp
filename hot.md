---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-holds` — task 1.1 DONE, 1/12).** Ralph loop running. Task 1.1 (test-only prep): dropped the now-stale `assertActionDoesNotExist('placeHold'/'liftHold')` guards from `CustomerLifecycleConsoleTest` (the only holder, grep-confirmed) and fixed its stale docblock/title; **kept** the `requireKyc` absence guard (KYC deferred → kyc-sanctions slice). No production code touched yet.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: SQLite 1397/1397 (7679 assn — −4 vs prior 7683 = the 2 removed guards ×2 internal each, test count unchanged); phpstan 0; pint clean; validate --strict valid.** PG17 NOT re-run this iteration (test-only edit; runs at closing-chain task 5.2).
- **Full suite OOMs at PHP's default 128 MB** → run `php -d memory_limit=-1 vendor/bin/pest`. `--filter` runs + `phpstan` are fine at the default.
- PG17 ritual (§2.7): `docker run -d --name pg … postgres:17 -p 55432:5432` → poll `pg_isready` → `DB_CONNECTION=pgsql DB_PORT=55432 … php -d memory_limit=-1 vendor/bin/pest <folder>` (+ a Catalog i18n test so the shared sink helper loads) → `docker rm -f pg`. i18n tests via `--filter`/full suite, NEVER a bare path.

## Active Change & Next Task
- **Active: `operator-console-parties-holds` (APPROVED). 1/12 done.**
- **Next: task 1.2** — pin the Filament 5 **non-relation** Holds table + per-row-action vehicle (table widget vs an `InteractsWithTable` component) hosted on the `ViewCustomer` `ViewRecord` page, verified against the INSTALLED Filament 5.6.7 (arch-from-memory ban); record the chosen vehicle in the `ViewCustomer` docblock; phpstan clean for the new file.
- **Then:** 1.3 (Hold i18n EN+IT + enumerate in `CustomerConsoleI18nTest`) → 2 (Holds read table) → 3 (Place Hold header form) → 4 (per-row Lift) → 5 (PG17 closing-chain) → 6 (quality + memory).

## Blockers & Decisions Needed
- **None blocking.** Landmines: (1) per-row Lift is the console's **first per-row action** — `LiftHold` keys off a **Hold id**, not the page record; build bespoke, reuse `surfaceLifecycleOutcome` (no trait fork). (2) `ModuleBoundariesTest` stays **UNCHANGED** — the carve-out already admits the whole `Parties\Enums` prefix; import `HoldType`/`HoldScope` as operands, do NOT widen. (3) Hold→status coupling is **domain-owned + additive** — console calls only `PlaceHold`/`LiftHold`, never `Suspend*`/`Reactivate*`, never recomputes suspension.

## Open Patterns
- **Operand-enum carve-out**: allowlist already admits the whole `Parties\Enums` prefix for OperatorPanel → operand enums import freely; state enums (`HoldStatus`) stay cast-only. No per-slice boundary-test edit.
- **Non-catalog status-FSM view = `ViewRecord` + `use SurfacesDomainActions` + bespoke `getHeaderActions()`** (rule-of-three / D8 CLOSED).
- **Multi-operand / Hold-id writes** not fitting `lifecycleAction(int $id)`: bespoke Filament action reusing `surfaceLifecycleOutcome()` for the uniform reject→`action_failed` notification (base-`RuntimeException` catch → no `Exceptions` import).
- **Console i18n completeness test = enumerate kit contract + 5 guards** (proven 6×); recipe in archived Customer change's `progress.md`.
