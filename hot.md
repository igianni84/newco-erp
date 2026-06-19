---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-19
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-19 (ralph — `parties-membership-suspension` task 3.2 GREEN, committed on `ralph/parties-membership-suspension`).** Completed the demand-side status-transition set (the LAST task adding new Action classes): `CloseCustomer` (`active|suspended → closed` → root `CustomerClosed`, **NO Profile cascade** — § 15.1 names none for closure, design L7; the `SuspendCustomer` template minus the child loop) + the audit-only Account FSM `SuspendAccount`/`ReactivateAccount`/`CloseAccount` (verbatim `CancelProfile` no-recorder shape — inject NEITHER recorder NOR actor; record NO event, § 15 names no Account-family event, design L8). **NO `ActivateAccount`** (Account born `active`; only `→ active` edge is the restore). **8 of 11 tasks done.**

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **931/931 green on SQLite** (919 baseline + 12 new `CustomerClosureAndAccountStatusTest`) via `php -d memory_limit=512M vendor/bin/pest` (artisan test OOMs at 128M). PHPStan 0, Pint clean, `openspec validate parties-membership-suspension --strict` valid, `composer.json/lock` untouched.
- **Verified on PG17** (docker `postgres:17` :55432): `tests/Feature|Unit/Modules/Parties` **434/434** — audit-only zero-event delta holds across the full Account walk; `CustomerClosed` `{customer_id,status}` jsonb round-trips; `where('entity_type','Account'|'Profile')->count()===0` engine-agnostic.

## Active Change & Next Task
- **`parties-membership-suspension` — IN PROGRESS on `ralph/parties-membership-suspension`.** Tasks 1.1–1.3 + 2.1–2.3 + 3.1–3.2 done (8 of 11).
- **Next: task 4.1** — `PlaceHold` coupling on PLACE (the FIRST coupling task, ADR 2026-06-19). In `PlaceHold.php`, after the Hold is recorded and **within the same transaction**, dispatch on `scope_type`: `customer` ⇒ if Customer `active` invoke `SuspendCustomer` (cascade); `account` ⇒ if Account `active` invoke `SuspendAccount`; `profile` ⇒ if Profile `Active` invoke `SuspendProfile`. From-state pre-check ⇒ a Hold on a non-suspendable scope (`pending` Customer's `kyc` Hold, `Applied` Profile) records the Hold + drives NO transition. **Remove** the "performs NO status transition … deferred demand-side seam" docblock note (PlaceHold.php ≈ L38-41). Adds NO new Action class. **Inverts** `HoldRegistryTest`/`HoldChainTest` (the "Hold performs no status transition" scope-guards — the Account assertion breaks first since Account is born `active`); KEEP the birth-state cases green; amend the demand-side-event-absence loops only where a place now records a suspend event. `grep -rn 'PlaceHold' tests/` first. **DB-touching + nested-tx savepoint → PG17 run is load-bearing.**

## Blockers & Decisions Needed
- None. Reviewer items remain resolved (design.md/ADR): Hold coupling = coverage-recompute (ADR 2026-06-19); `CloseCustomer` does NOT cascade Profiles; cascade `ProfileSuspended` = causation child of `CustomerSuspended`.
- Still open (human's call): **push `main` → `origin`** + delete merged `ralph/parties-membership-activation` branch.

## Open Patterns
- **Audit-only FSM trio** (Account) = `CancelProfile` no-recorder shape; prove via event-delta 0 + `ActivateAccount` absence (`glob` basenames + `class_exists` false). **No-cascade root transition** (`CloseCustomer`) = `SuspendCustomer` minus the child loop; proof = Profile untouched + `DomainEvent` delta exactly 1 + `entity_type='Profile'` count 0.
- **Guard realignment reached its TERMINAL shape at 3.2.** `SupplyLifecycleChainTest`'s `$demandSideStatusTransitions` holds all twelve demand-side status Actions; `ComplianceIndependenceTest`'s forbidden list is at its permanent floor (`ActivateAccount` + `Lock`/`Set`OriginatingClub). **Tasks 4.x add NO new Action** → they touch NEITHER list; they ONLY invert `HoldRegistryTest`/`HoldChainTest` + amend runtime event-absence loops where a place/lift now suspends/restores an `active`-born scope. Always `grep -rn '<symbol>' tests/` to re-derive blast radius.
- **Cascade Action template** (3.1): root `record(…)` → re-read children `hasMany`+`->lockForUpdate()->get()` → inline child `update`+`record(causationId:$root->id, correlationId:$root->correlation_id)`; INLINE, never delegate to the self-edge Action. Causation-child assert: `(int) $child->causation_id === $root->id` (uncast → numeric string on PG). **Coverage-recompute under lock:** `(Profile) OR (its Customer)` active-Hold union `->lockForUpdate()->get()` + `count()>0` (NOT `isNotEmpty()`/`exists()`). **PG recipe:** docker `postgres:17` :55432 → `DB_CONNECTION=pgsql … php -d memory_limit=512M vendor/bin/pest`; `docker rm -f pg` after.
