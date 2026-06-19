---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-19
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-19 (ralph — `parties-membership-suspension` task 2.1 GREEN, committed on `ralph/parties-membership-suspension`).** Shipped the first two transition Actions: `SuspendProfile` (`Active → Suspended` → root `ProfileSuspended`) + `ReactivateProfile` (`Suspended → Active` → root `ProfileReactivated`), each the verbatim `ActivateProfile` template (inject recorder+actor; `DB::transaction` → `lockForUpdate` re-read → from-state guard FIRST → state-preserving `update(['state'=>…])` → root `record(::NAME,…,::payload($p))`). First guard realignment: `SupplyLifecycleChainTest` gained a `$demandSideStatusTransitions` sub-array spread into the exact-set whitelist (design L10). **4 of 11 tasks done.**

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **875/875 green on SQLite** (865 baseline + 10 new `ProfileSuspensionTest`) via `php -d memory_limit=512M vendor/bin/pest` (artisan test OOMs at 128M). PHPStan 0, Pint clean, `openspec validate parties-membership-suspension --strict` valid, `composer.json/lock` untouched.
- **Verified on PG17** (docker `postgres:17` :55432): `tests/Feature|Unit/Modules/Parties` **378/378** — includes the new test, the amended chain guard, `ComplianceIndependenceTest`, and the proven-on-PG `ProfileActivationTest`.

## Active Change & Next Task
- **`parties-membership-suspension` — IN PROGRESS on `ralph/parties-membership-suspension`.** Tasks 1.1–1.3 + 2.1 done (4 of 11).
- **Next: task 2.2** — `LapseProfile` (`Active → Lapsed`) + `RenewProfile` (`Lapsed → Active` grace). Same self-edge template as 2.1, PLUS: `LapseProfile` stamps `lapsed_at = CarbonImmutable::now()` + records `ProfileExpired` (NOT `ProfileLapsed`); `RenewProfile` guards `state===Lapsed` **AND** `now ≤ lapsed_at->addDays(30)` (DEC-034 — past-grace AND wrong-from-state both throw `::cannotRenew`), clears `lapsed_at`, records `ProfileRenewed`. Triggers (validity-expiry / Module-E `MembershipFeePaid`) are documented seams — invoke directly. **Narrow the guard** (L10): add both to the SAME `$demandSideStatusTransitions` sub-array in `SupplyLifecycleChainTest`. **DB-touching → PG17 run required.** Test hint: `ProfileLapseGraceTest.php`; assert the 30-day edge BOTH sides + `DomainEvent::where('name','ProfileLapsed')->count()===0`.

## Blockers & Decisions Needed
- None. Reviewer items remain resolved (design.md/ADR): Hold coupling = coverage-recompute (ADR 2026-06-19); `CloseCustomer` does NOT cascade Profiles; cascade `ProfileSuspended` = causation child of `CustomerSuspended` (pass `$root->id` as `causationId`+`correlationId`).
- Still open (human's call): **push `main` → `origin`** + delete merged `ralph/parties-membership-activation` branch.

## Open Patterns
- **Self-edge status-transition Action = `ActivateProfile` template** (now in progress.md Codebase Patterns): `lockForUpdate` re-read → from-state guard FIRST → state-preserving `update(['state'=>…])` → root `record(::NAME,…,::payload)`. Directly-invoked = ROOT (omit causation/correlation); a cascading Customer Action threads them (L11). Forward-task classes (`SuspendCustomer`/`RenewProfile`) in plain backticks in docblocks; `{@see}` only for shipped classes.
- **Guard blast radius:** ONLY `SupplyLifecycleChainTest` + `ComplianceIndependenceTest` glob `Actions/*.php`. A Profile self-edge trips ONLY the former's exact-set whitelist (add the Action name). `ComplianceIndependenceTest` stays green for Profile edges (forbidden list is Customer/Account/`*OriginatingClub`; `'originating_club_id'=>` write-count puts new Actions in the 0-writes branch) — its forbidden list drops `SuspendCustomer`/`CloseCustomer`/Account at 3.x. `HoldRegistryTest`/`HoldChainTest` invert ONLY at coupling 4.x. Always `grep -rn '<symbol>' tests/` to re-derive — don't trust design line numbers.
- **State-preservation proof:** snapshot `DomainEvent::count()` + sibling table counts before the call → assert delta is exactly 1 + others unchanged. **PG recipe:** docker `postgres:17` :55432 → `DB_CONNECTION=pgsql … php -d memory_limit=512M vendor/bin/pest` (artisan OOMs at 128M; pao swallows the PG summary). `createActiveProfile()` is a GLOBAL Pest helper (reuse, don't redeclare).
