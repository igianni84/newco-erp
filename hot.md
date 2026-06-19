---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-19
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-19 (ralph — `parties-membership-suspension` task 1.3 GREEN, committed on `ralph/parties-membership-suspension`).** Shipped the eight `final` demand-side status event classes under `app/Modules/Parties/Events/`: Customer family (`CustomerSuspended`/`CustomerReactivated`/`CustomerClosed`, `ENTITY_TYPE='Customer'`, `payload(Customer)`→`{customer_id, status}`) + Profile family (`ProfileSuspended`/`ProfileReactivated`/`ProfileExpired`/`ProfileRenewed`/`ProfileInactive`, `'Profile'`, `payload(Profile)`→`{profile_id, state}`, enum `->value`, PII-free). Each = untyped `const NAME` (verbatim § 15), `const ENTITY_TYPE`, static `payload()` — the shipped `CustomerActivated`/`ProfileActivated` shape. **Foundations block complete. 3 of 11 tasks done.**

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **865/865 green on SQLite** (857 baseline + 8 new `StatusEventsTest`). PHPStan 0 errors, Pint clean, `openspec validate parties-membership-suspension --strict` valid, `composer.json/lock` untouched. **No PG17 run** — 1.3 is pure event classes + a no-DB unit test. **No guard test touched** (additive — realignment begins at 2.1).

## Active Change & Next Task
- **`parties-membership-suspension` — IN PROGRESS on `ralph/parties-membership-suspension`.** Tasks 1.1–1.3 done (3 of 11). Foundations (migration + exceptions + events) complete.
- **Next: task 2.1** — `SuspendProfile` + `ReactivateProfile` (`Active ↔ Suspended`), each `handle(int $profileId): Profile`, inject `DomainEventRecorder` + `ActorContext`; one `DB::transaction` + `lockForUpdate` re-read + from-state guard (`IllegalProfileTransition::cannotSuspend`/`cannotReactivate`, thrown FIRST) + `update(['state' => …])` + `record(ProfileSuspended|ProfileReactivated::NAME, …, entityType:'Profile', entityId:(string)$profile->id, payload:…::payload($profile))` (root). State-preserving — writes ONLY `state`. Mirror `ActivateCustomer`/`ActivateProfile`. **FIRST guard-realigning task:** add both Actions to `SupplyLifecycleChainTest`'s Actions exact-set (`toEqualCanonicalizing`, ≈ L338-339 — new `demandSideStatusTransitions` sub-array) IN THE SAME TASK or the suite goes red. **DB-touching → PG17 run required.**

## Blockers & Decisions Needed
- None. Reviewer items remain resolved (design.md/ADR): Hold coupling = coverage-recompute (ADR 2026-06-19); `CloseCustomer` does NOT cascade Profiles; cascade `ProfileSuspended` = causation child of `CustomerSuspended` (pass `$root->id` as `causationId`+`correlationId`).
- Still open (human's call): **push `main` → `origin`** + delete merged `ralph/parties-membership-activation` branch.

## Open Patterns
- **Event class = `CustomerActivated`/`ProfileActivated` shape:** `final`, untyped `const NAME` (verbatim § 15), `const ENTITY_TYPE`, static `payload($model): array` (enum→`->value`; ids + business values only, no PII). Action calls `::NAME` + `::payload($model)` AFTER `update()` (payload snapshots the new state). Cascade events (`ProfileSuspended`/`ProfileReactivated`) are causation children inside the Customer cascade, but the CLASS is identical — causation threaded by the Action, not the payload (design L11).
- **§15 naming traps (load-bearing for 2.x):** `ProfileExpired` = `Active→Lapsed` (NO `ProfileLapsed`); `ProfileReactivated` = `Suspended→Active` ONLY; `ProfileRenewed` = `Lapsed→Active` grace; cancel + ALL Account transitions are **audit-only** (record no event); `ActivateAccount` stays forbidden.
- **Guard-test realignment starts at 2.1** (first Action): `SupplyLifecycleChainTest` Actions exact-set must gain each new Action in the same task; `ComplianceIndependenceTest` forbidden-list drops the now-shipped Actions at 3.x; `HoldRegistryTest`/`HoldChainTest` invert ONLY at coupling tasks 4.x. Always `grep -rn '<symbol>' tests/` to re-derive blast radius — don't trust the design's line numbers.
- **No-DB unit test recipe:** `uses(TestCase::class)` (boots casts, no schema); `factory()->make()` + EVERY FK overridden to an explicit id (no query). Explicit `expect()->and()` chains, NOT callable-datasets (PHPStan `mixed` gotcha). **PG17 recipe (DB tasks):** docker `postgres:17` :55432 → `php -d memory_limit=512M vendor/bin/pest` (artisan test OOMs at 128M).
