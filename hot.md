---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-19
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-19 (ralph — `parties-membership-suspension` task 3.1 GREEN, committed on `ralph/parties-membership-suspension`).** Shipped the first CASCADE Actions + the first causation-child events: `SuspendCustomer` (`active → suspended` → root `CustomerSuspended` → re-read `Active` Profiles `->lockForUpdate()`, each → `Suspended` + `ProfileSuspended` as a **causation child** `causationId=$root->id, correlationId=$root->correlation_id`, L11) + `ReactivateCustomer` (`suspended → active` → root `CustomerReactivated` → restore each `Suspended` Profile **iff** no active Hold covers it — `(Profile) OR (its Customer)` coverage re-read `->lockForUpdate()->get()`, `count()>0`). Cascade INLINES child write+record (never delegates to `SuspendProfile`/`ReactivateProfile`, which record ROOTs). Added `Customer::profiles()` hasMany (mirrors `Producer::clubs()`). **7 of 11 tasks done.**

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **919/919 green on SQLite** (910 baseline + 9 new `CustomerSuspensionCascadeTest`) via `php -d memory_limit=512M vendor/bin/pest` (artisan test OOMs at 128M). PHPStan 0, Pint clean, `openspec validate parties-membership-suspension --strict` valid, `composer.json/lock` untouched.
- **Verified on PG17** (docker `postgres:17` :55432): `tests/Feature|Unit/Modules/Parties` **422/422** — causation-child linkage holds via `(int) $child->causation_id` vs int `$root->id` PK (causation_id is UNCAST → numeric string on PG); coverage restore correct; jsonb payloads round-trip.

## Active Change & Next Task
- **`parties-membership-suspension` — IN PROGRESS on `ralph/parties-membership-suspension`.** Tasks 1.1–1.3 + 2.1–2.3 + 3.1 done (7 of 11).
- **Next: task 3.2** — `CloseCustomer` + the Account FSM (`SuspendAccount`/`ReactivateAccount`/`CloseAccount`, audit-only). `CloseCustomer` (`active|suspended → closed`): guard `cannotClose` → `update(status=Closed)` → root `CustomerClosed`; **NO Profile cascade** (§ 15.1 names none — L7). The Account trio (each `handle(int $accountId): Account`): from-state guard (`active`/`suspended`/`{active,suspended}`) → `IllegalAccountTransition` → `update(status=…)` → **record NO event** (audit-only — L8; the `CancelProfile` no-recorder shape: inject NO recorder/actor). **NO `ActivateAccount`** (born `active`). **Guards:** add all four to `SupplyLifecycleChainTest`'s `$demandSideStatusTransitions`; drop `CloseCustomer`/`SuspendAccount`/`CloseAccount` from `ComplianceIndependenceTest`'s forbidden list — **KEEP `ActivateAccount`** + `Lock`/`Set`OriginatingClub. **DB-touching → PG17 run required.**

## Blockers & Decisions Needed
- None. Reviewer items remain resolved (design.md/ADR): Hold coupling = coverage-recompute (ADR 2026-06-19); `CloseCustomer` does NOT cascade Profiles; cascade `ProfileSuspended` = causation child of `CustomerSuspended`.
- Still open (human's call): **push `main` → `origin`** + delete merged `ralph/parties-membership-activation` branch.

## Open Patterns
- **Cascade Action template** (progress.md Codebase Patterns): root `record(…)` → re-read children `hasMany`+`->lockForUpdate()->get()` → inline `update` + `record(child,…,causationId:$root->id,correlationId:$root->correlation_id)`. INLINE, never delegate to the self-edge Action (records a ROOT). Resolve actor once. Assert child via `(int) $child->causation_id === $root->id` (uncast → PG numeric string); `correlation_id` is a uuid string (compare directly).
- **Coverage-recompute under lock:** `(Profile) OR (its Customer)` active-Hold union, `->lockForUpdate()->get()` + `count()>0` — NOT `->isNotEmpty()` (larastan `noUnnecessaryCollectionCall`) NOR `->lockForUpdate()->exists()` (FOR UPDATE in EXISTS — PG cross-engine risk). `PlaceHold` at ≤3.x records the Hold without a status transition (coupling lands 4.x).
- **Guard blast radius:** Profile self-edge + Customer cascade trip `SupplyLifecycleChainTest`'s exact-set whitelist; `ComplianceIndependenceTest` forbidden list drops Customer/Account Actions one task at a time (3.1 dropped `SuspendCustomer`; 3.2 drops `CloseCustomer`/`SuspendAccount`/`CloseAccount`, keeps `ActivateAccount`). `HoldRegistryTest`/`HoldChainTest` invert ONLY at coupling 4.x. Always `grep -rn '<symbol>' tests/`. **PG recipe:** docker `postgres:17` :55432 → `DB_CONNECTION=pgsql … php -d memory_limit=512M vendor/bin/pest`; `docker rm -f pg` after.
