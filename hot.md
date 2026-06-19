---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-19
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-19 (ralph — `parties-membership-suspension` task 1.1 GREEN, committed on `ralph/parties-membership-suspension`).** The change's one additive migration shipped: two nullable columns on `parties_profiles` — `lapsed_at` (`timestampTz`, the 30-day-grace anchor DEC-034) + `cancellation_reason` (`string`, the audit-only Producer cancel reason). `Profile` model gains the `immutable_datetime` cast + both `@property`. Partial-unique index untouched. Verified SQLite + PG17. **1 of 11 tasks done.**

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **839/839 green on SQLite AND PostgreSQL 17** (836 baseline + 3 new `ProfileLifecycleColumnsTest`). PHPStan 0 errors, Pint clean, `openspec validate parties-membership-suspension --strict` valid, `composer.json/lock` untouched. PG17 `down()` reversibility proven (rollback drops both cols, table survives, re-migrate restores).

## Active Change & Next Task
- **`parties-membership-suspension` — IN PROGRESS on branch `ralph/parties-membership-suspension`.** Task 1.1 done (1 of 11).
- **Next: task 1.2** — Transition exceptions + localized copy: extend `IllegalProfileTransition` (`::cannotSuspend/cannotReactivate/cannotLapse/cannotRenew/cannotCancel/cannotDeactivate`) + `IllegalCustomerTransition` (`::cannotSuspend/cannotReactivate/cannotClose`); add **new** `IllegalAccountTransition extends RuntimeException` (`::cannotSuspend/cannotReactivate/cannotClose`). New keys in `profile`/`customer` groups + a **new `account` group** of `lang/en/parties.php`, each with a `:state` token. No DB → no PG run needed. Then 1.3 (eight event classes), then 2.x Actions.

## Blockers & Decisions Needed
- None. Reviewer items remain resolved in design.md/ADR: Hold coupling = coverage-recompute (ADR 2026-06-19); `CloseCustomer` does NOT cascade Profiles (§15.1 silence); cascade `ProfileSuspended` = causation child of `CustomerSuspended`.
- Still open from prior session (human's call): **push `main` → `origin`** + delete merged `ralph/parties-membership-activation` branch. The foundational ADR + `decisions/INDEX.md` row (authored 2026-06-19) were committed into THIS branch with task 1.1 (working tree was carrying them uncommitted).

## Open Patterns
- **Additive-column migration = the `2026_06_18_000002` template:** nullable, no default/backfill, `down()` only `dropColumn`s; `timestamptz` → `immutable_datetime` cast (no CHECK); free-text → plain `string` **uncast**. Never re-migrate the `parties_profiles` partial-unique index (already excludes the terminal set).
- **Guard-test realignment starts at task 2.1** (first new Action), NOT 1.x: a pure schema/exception/event-class task moves no guard (`SupplyLifecycleChainTest`/`ComplianceIndependenceTest`/`HoldRegistryTest`/`HoldChainTest` stayed green unamended at 1.1). Narrow each guard in the SAME task that introduces the tripping name; `grep -rn` the symbol across `tests/` first.
- **§15 naming traps:** `ProfileExpired` = `Active→Lapsed` (NO `ProfileLapsed`); `ProfileReactivated` = `Suspended→Active` only; `ProfileRenewed` = `Lapsed→Active` grace; cancel + all Account transitions are **audit-only** (record no event); `ActivateAccount` stays forbidden (Account born `active`).
- **PG17 recipe:** docker `postgres:17` :55432 → `php -d memory_limit=512M vendor/bin/pest` (artisan test OOMs at 128M + pao swallows the PG summary).
