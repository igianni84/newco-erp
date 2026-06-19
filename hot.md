---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-19
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-19 (ralph — `parties-membership-suspension` task 1.2 GREEN, committed on `ralph/parties-membership-suspension`).** From-state guard vocabulary shipped: `IllegalProfileTransition` +6 factories (`cannotSuspend/Reactivate/Lapse/Renew/Cancel/Deactivate`), `IllegalCustomerTransition` +3 (`cannotSuspend/Reactivate/Close`), **new** `IllegalAccountTransition extends RuntimeException` +3 (NO `cannotActivate` — Account born `active`). +12 `:state` lang keys across `profile`/`customer` + a **new `account` group** (PII-free, EN baseline DEC-127). **2 of 11 tasks done.**

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **857/857 green on SQLite** (839 baseline + 18 new `StatusTransitionExceptionsTest`). PHPStan 0 errors, Pint clean, `openspec validate parties-membership-suspension --strict` valid, `composer.json/lock` untouched. **No PG17 run** — 1.2 is exceptions + lang only (no DB). No guard test moved (additive).

## Active Change & Next Task
- **`parties-membership-suspension` — IN PROGRESS on `ralph/parties-membership-suspension`.** Tasks 1.1–1.2 done (2 of 11).
- **Next: task 1.3** — the eight demand-side status event classes `App\Modules\Parties\Events\{CustomerSuspended,CustomerReactivated,CustomerClosed,ProfileSuspended,ProfileReactivated,ProfileExpired,ProfileRenewed,ProfileInactive}`. Each `final`, untyped `const NAME` (verbatim § 15), `const ENTITY_TYPE` (`'Customer'`×3 / `'Profile'`×5), static `payload()`: Customer→`{customer_id, status}`, Profile→`{profile_id, state}` (enum `->value`, PII-free). Mirror shipped `CustomerActivated`/`ProfileActivated`. No DB → no PG run. `grep -rn` each name in `tests/` — none in a forbidden list (`SupplyLifecycleChainTest` glob forbids only `AccountActivated`/`ProfileApproved`/`CustomerSegmentChanged`), so creating them trips no existence guard.

## Blockers & Decisions Needed
- None. Reviewer items remain resolved (design.md/ADR): Hold coupling = coverage-recompute (ADR 2026-06-19); `CloseCustomer` does NOT cascade Profiles; cascade `ProfileSuspended` = causation child of `CustomerSuspended`.
- Still open (human's call): **push `main` → `origin`** + delete merged `ralph/parties-membership-activation` branch.

## Open Patterns
- **Transition-exception factory = verbatim `cannotApprove` template:** `(string) __('parties.<group>.cannot_x', ['state' => $from->value])`; `:state` IS interpolated (business value, not PII). New entity family → new lang group. Additive → moves no guard at 1.x.
- **§15 naming traps (load-bearing for 1.3/2.x):** `ProfileExpired` = `Active→Lapsed` (NO `ProfileLapsed`); `ProfileReactivated` = `Suspended→Active` ONLY; `ProfileRenewed` = `Lapsed→Active` grace; cancel + ALL Account transitions are **audit-only** (record no event); `ActivateAccount` stays forbidden.
- **Type-clean Pest for static factories:** call in-body (concrete return), feed literal `::class` to `toBeInstanceOf`, prove subclass via `ReflectionClass::isSubclassOf` (not `is_subclass_of`/`instanceof` — PHPStan constant-folds). Prove `:state` via a token absent from the template; keep digits out of copy. (Full detail in progress.md Codebase Patterns.)
- **Guard-test realignment starts at 2.1** (first Action), NOT 1.x. **PG17 recipe:** docker `postgres:17` :55432 → `php -d memory_limit=512M vendor/bin/pest`.
