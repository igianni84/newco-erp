---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` task 1.2 DONE — `parties_club_credits` migration landed; ralph loop, 2/15 tasks).** Module K §11 Club Credit, greenfield, extends the `party-registry` capability. Shipped the table + the one-active partial unique index + its raw-insert schema test. Enum (1.1) + schema (1.2) done; no model/Action yet. The artifacts ARE the plan.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: full suite 1507/1507 (8284 assn); PHPStan max 0 err; `pint --test` clean; `openspec validate club-credit --strict` valid.**
- **Run the full suite with `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`.** The `artisan test` wrapper (laravel/pao) spawns the real test process *without* the outer `-d memory_limit=-1`, so it OOMs at the 128 MB default (fatal in `filament/.../routes/web.php` during `setUp()`). `vendor/bin/pest` keeps it one unlimited process.
- **PG17 lane:** no local PG; migrations are Postgres-truthful by construction and the CI `tests-pgsql` lane verifies on the human push (the loop never pushes — development.md).

## Active Change & Next Task
- **`club-credit` — 2/15 done.** Next: **1.3 `ClubCredit` model + factory** (`app/Modules/Parties/Models/ClubCredit.php`, `database/factories/Parties/ClubCreditFactory.php`): `$table='parties_club_credits'`; `$guarded=[]`; casts `amount`/`remaining`→`MoneyCast`, `state`→`ClubCreditState`, `valid_from`/`valid_to`→`immutable_datetime`; within-module `profile()` `belongsTo`; `@property` docblock. Factory default: an `active` credit, `amount`=a credit-Club's fee (`ClubFactory` default `Money::of(25000, EUR)`), `remaining`=`amount`. **`state` has NO DB default → factory MUST set it.** Feature test re-asserts the one-active partial index **through the Eloquent path** (model-layer proof, not a dup). Then 1.4 `Profile::activeClubCredit()` → 2.x issuance → 3.x redemption (K.17) → 4.x forfeit/restore → 5.x §11.4 guard + i18n + docs + gate.
- 3 gate decisions RESOLVED (design L2/L3/L5): **audit-only writers** (no domain event — §11.4 makes `ClubCredit*` + `MembershipFeePaid` Module E's); **`Club.fee` verbatim** (full-fee→full-credit, `valid_to`=31 Dec); **full FSM + seams** (shipped Profile Actions untouched).

## Blockers & Decisions Needed
- **No active blocker.** No open ADR gate stepped through. `main` in sync with `origin/main`.
- Cross-module triggers are deferred SEAMS (not blockers): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-043 conversion; year-end scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **Parties migration idiom** (clubs/profiles templates): MoneyCast `{key}_minor`(int)+`{key}_currency`(str3); value-set col = string + enum cast + PG-only `CHECK` from `Enum::cases()`; partial unique index via raw `DB::statement('CREATE UNIQUE INDEX … WHERE …')` on BOTH engines (predicate token from the enum); `down()` = bare `dropIfExists`; index names <63 chars.
- **Raw-insert schema test** (`EventDeliveriesSchemaTest` template): assert partial index by **name** (`Schema::hasIndex`); constraint rejection via `expect(fn () => DB::transaction(fn () => …insert))->toThrow(QueryException)`; NOT-NULL floor via `unset` + throw; FK orphan via bad parent id.
- **Don't down-test a migration:** base `Migration` declares no `up()`/`down()` → `require`d migration is untype-narrowable under PHPStan max; no sibling down-tests. Assert the up-state only.
