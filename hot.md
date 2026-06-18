---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph — `parties-holds` iteration 2/30, task 1.2 DONE).** Shipped the additive `parties_holds` table + `Hold` model + `HoldFactory`. Migration: `hold_type`/`scope_type`/`status` (string + enum cast + PG-only `CHECK (col IN (...))` from `Enum::cases()`, emitted in one `foreach` over a `$valueSets` map, constraint name derived from the column); polymorphic `scope_id` (`unsignedBigInteger`, NO DB FK — L1); placement + lift audit columns; composite index `parties_holds_scope_status_index (scope_type, scope_id, status)`. Model is persistence-only (`$guarded=[]`, three enum casts + ActorRole on both role cols + integer casts on ids + `lifted_at` immutable). New `HoldSchemaTest` (6 tests). **First PG17-gated task — fully verified on PG17.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 732/732 SQLite** (3405 assertions) — up from 726 (the 6 new `HoldSchemaTest` cases). PHPStan max 0 · Pint clean · `openspec validate parties-holds --strict` valid.
- **PG17 proven** (docker `postgres:17`): `HoldSchemaTest` 6/6 + `tests/Feature/Modules/Parties`+`tests/Architecture` **174/174** on PG. Catalog inspection: 3 named CHECKs with verbatim value-sets + the composite btree index; `migrate:fresh`/`migrate:rollback` clean. Composer untouched (no new dep).

## Active Change & Next Task
- **`parties-holds` — IN PROGRESS (2/11 tasks done).** Unified `parties_holds` registry: 6 types / 3 scopes / `active|lifted`, PlaceHold/LiftHold + per-type lift discipline, the `kyc`-Hold coupling (auto-place on `RequireKyc`, auto-lift on `RecordKycVerified`), `CustomerHoldPlaced`/`CustomerHoldLifted` events, the `(sanctions_status, active-Hold-list)` read-API.
- **Next task: 1.3** — `App\Modules\Parties\Exceptions\IllegalHoldLift extends RuntimeException` with `::autoManaged(HoldType)` + `::notActive(HoldStatus)`, localized copy in a NEW `hold` group in `lang/en/parties.php` (keys `cannot_lift_auto_managed` `:type` / `cannot_lift_not_active` `:state`). **Mirror the existing `IllegalKycTransition.php`** (sibling in `app/Modules/Parties/Exceptions/`). PRESERVE the 7 existing lang groups (producer/club/producer_agreement/customer/profile/kyc/sanctions). No DB — test `tests/Unit/Modules/Parties/Exceptions/HoldExceptionTest.php`.
- Build position: end of Phase 2; Mod K ~75-80% once this slice lands.

## Blockers & Decisions Needed
- None. ADR `2026-06-18-hold-lift-discipline-per-type.md` is the standing authority for the per-type lift discipline (root `CLAUDE.md` Invariant #7 kept as-is — Protected file). No open ADR gate stepped (events inline; no queue/object-storage/payment-provider/dependency).

## Open Patterns
- **Migration NON-nullable CHECK idiom (SHIPPED 1.2):** `Schema::create` → one `foreach` over `/** @var array<string,list<BackedEnum>> */ $valueSets`, constraint name `parties_holds_{col}_check`. `BackedEnum` unqualified (global) in the no-namespace file; Pint keeps it. Cast scope/actor ids to `integer` (PG numeric-string trap, testing-rule #6). `Schema::getIndexes()` works on both engines — match a composite index by its column tuple, not name.
- **Pint `{@see \FQCN}` forward-ref trap (re-confirmed 1.2):** a docblock citing a class built in a LATER task → plain backticks, never `{@see}` (Pint auto-imports the FQCN → unresolvable `use` reds PHPStan). The `knowledge/laravel/rules.md` rule.
- **Enum house style + `autoLiftable()` single source of truth** (the `LiftHold` guard 3.2 + the `RecordKycVerified` system-lift 4.1). Read-API cascade resolves at READ, not by duplicate rows. The Hold registry is trigger-agnostic; `payment`/`fraud`/`compliance`/`credit` auto-triggers are Module-E seams.
