---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` task 1.3 DONE — `ClubCredit` model + factory landed; ralph loop, 3/15 tasks).** Module K §11 Club Credit, greenfield, extends the `party-registry` capability. Shipped the persistence-only Eloquent model over `parties_club_credits` (casts + `profile()` belongsTo) + its factory + a feature test re-asserting the one-active partial index through the Eloquent write path. Enum (1.1) + schema (1.2) + model/factory (1.3) done; no `Profile` relation or Action yet. The artifacts ARE the plan.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: full suite 1510/1510 (8303 assn); PHPStan max 0 err; `pint --test` clean; `openspec validate club-credit --strict` valid.**
- **Run the full suite with `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`.** The `artisan test` wrapper (laravel/pao) spawns the real test process *without* the outer `-d memory_limit=-1`, so it OOMs at the 128 MB default (fatal in `filament/.../routes/web.php` during `setUp()`).
- **PG17 lane:** no local PG; migrations are Postgres-truthful by construction and the CI `tests-pgsql` lane verifies on the human push (the loop never pushes — development.md).

## Active Change & Next Task
- **`club-credit` — 3/15 done.** Next: **1.4 `Profile::activeClubCredit()`** within-module relation (`app/Modules/Parties/Models/Profile.php`): a `hasOne(ClubCredit::class)` scoped to `state = 'active'` (the at-most-one active credit) — both Module K, no boundary breach. Acceptance: returns the active credit when present, `null` for a `redeemed`/`forfeited`-only Profile; cover with a Profile test. Then 2.x issuance (`IssueClubCredit`, `amount = Club.fee` verbatim, `valid_to` = year-end) → 3.x redemption/K.17 carry-forward → 4.x forfeit/restore → 5.x §11.4 guard + i18n + docs + gate.
- 3 gate decisions RESOLVED (design L2/L3/L5): **audit-only writers** (no domain event — §11.4 makes `ClubCredit*` + `MembershipFeePaid` Module E's); **`Club.fee` verbatim** (full-fee→full-credit, `valid_to`=31 Dec); **full FSM + seams** (shipped Profile Actions untouched).

## Blockers & Decisions Needed
- **No active blocker.** No open ADR gate stepped through. `main` in sync with `origin/main`.
- Cross-module triggers are deferred SEAMS (not blockers): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-043 conversion; year-end scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **Parties model+factory idiom** (`Club`/`Profile` templates): `$table` + `$guarded=[]` + `casts()` (`MoneyCast` for Money pairs, enum cast for value-set cols, `immutable_datetime` for datetimes) + `newFactory()` override + `@property` docblock; factory passes `Money` objects straight through the cast and a `belongsTo` parent via a nested factory; set a value-set column explicitly when its DB default is absent.
- **`{@see \FQCN}` forward-ref trap (re-confirmed 1.3):** referencing a not-yet-existing class via `{@see \FQCN}` → Pint `fully_qualified_strict_types` hoists it to a real `use` → reds PHPStan. Plain backticks for forward refs; `{@see}` only for existing classes. (lessons 2026-06-13 → `knowledge/laravel/rules.md`.)
- **Raw-column reads:** `DB::table(...)->value('col')` (scalar), NOT `->first()->prop` (`stdClass|null` → PHPStan `property.nonObject`). One-active proof through Eloquent: factory-create one `active`, then `DB::transaction(fn () => second active for same profile)->toThrow(QueryException)`.
- **Parties migration idiom** (1.2): partial unique index via raw `DB::statement('CREATE UNIQUE INDEX … WHERE …')` on BOTH engines; PG-only `CHECK` from `Enum::cases()`; `down()` = bare `dropIfExists`. Don't down-test a migration (base `Migration` is untype-narrowable under PHPStan max).
