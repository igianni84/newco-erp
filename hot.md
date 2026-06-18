---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph — `parties-holds` iteration 1/30, task 1.1 DONE).** The change is APPROVED and the loop is running. Shipped the three string-backed Hold enums under `app/Modules/Parties/Enums/`: `HoldType` (`admin/kyc/payment/fraud/compliance/credit`) + the `autoLiftable()` first predicate (true for `Kyc`/`Payment` only — mirrors `KycStatus::clears()`), `HoldScope` (`customer/account/profile`), `HoldStatus` (`active/lifted`). New `HoldEnumsTest` (8 tests). Quality loop fully green; committed.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 726/726 SQLite** (3380 assertions) — up from 718 (the 8 new `HoldEnumsTest` cases). PHPStan max 0 · Pint clean · `openspec validate parties-holds --strict` valid.
- No DB touched yet (pure enums); PG17 run not needed until the migration (task 1.2). Composer untouched (no new dep).

## Active Change & Next Task
- **`parties-holds` — IN PROGRESS (1/11 tasks done).** Unified `parties_holds` registry: 6 types / 3 scopes / `active|lifted`, PlaceHold/LiftHold + per-type lift discipline, the `kyc`-Hold coupling (auto-place on `RequireKyc`, auto-lift on `RecordKycVerified` — MODIFIES those 2 Actions), `CustomerHoldPlaced`/`CustomerHoldLifted` events, and the `(sanctions_status, active-Hold-list)` read-API tuple.
- **Next task: 1.2** — the additive migration `database/migrations/2026_06_18_000001_create_parties_holds_table.php` + `Hold` model + `HoldFactory`. **First PG17-gated task** (composite index `(scope_type,scope_id,status)` + non-nullable PG-only `CHECK`s from `Enum::cases()`, polymorphic `scope_id` with NO DB FK). Idiom = `Schema::create` (NEW table) → plain `CHECK (col IN (...))` per `2026_06_15_000005_create_parties_customers_table.php` (NOT the nullable form). Test: `tests/Feature/Modules/Parties/HoldSchemaTest.php`.
- Build position: end of Phase 2; Mod K ~75-80% once this slice lands (closes the Phase-2 Hold-workflows signoff floor).

## Blockers & Decisions Needed
- None. ADR `2026-06-18-hold-lift-discipline-per-type.md` is the standing authority for the per-type lift discipline (root `CLAUDE.md` Invariant #7 kept as-is by Giovanni's 2026-06-18 call — Protected file, not edited).
- No open ADR gate stepped through (events inline; no queue/object-storage/payment-provider/dependency).

## Open Patterns
- **Enum house style (carryover, re-confirmed 1.1):** `enum X: string`, PascalCase cases in spec order, predicate as the FIRST method, docblock = design L-tag + spec § + ADR. Test pins order-sensitive `case→value` maps + `toHaveCount` + `from()` round-trip + per-enum `ValueError` (bogus token chosen semantically-adjacent to document the domain edge).
- **`autoLiftable()` is the single source of truth** for both the `LiftHold` operator guard (task 3.2 rejects auto-managed) and the `RecordKycVerified` system-lift path (task 4.1). Positive list (`=== Kyc || === Payment`), not negation.
- **The Hold registry is trigger-agnostic:** the 6 types + manual path + lift discipline ship now; `payment`/`fraud`/`compliance`/`credit` auto-triggers (+ finance subtypes) are Module-E seams. Scope is polymorphic (`scope_type`+`scope_id`, no FK); cascade resolves at READ (the read-API), not by duplicate rows.
