---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` task 1.4 DONE — `Profile::activeClubCredit()` relation landed; ralph loop, 4/15 tasks).** Module K §11 Club Credit, greenfield, extends the `party-registry` capability. Added the within-module `hasOne(ClubCredit::class)->where('state','active')` inverse on `Profile` (the at-most-one active credit, riding the structural one-active partial index — design L1). Schema (1.2) + model/factory (1.3) + this relation (1.4) done. No writer Action yet. The artifacts ARE the plan.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: full suite 1515/1515 (8313 assn); PHPStan max 0 err; `pint --test` clean; `openspec validate club-credit --strict` valid.**
- **Run the full suite with `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`.** The `artisan test` wrapper (laravel/pao) spawns the real test process *without* the outer `-d memory_limit=-1`, so it OOMs at the 128 MB default (fatal in `filament/.../routes/web.php` during `setUp()`).
- **PG17 lane:** no local PG; 1.4 added no DDL (the relation reads 1.2's table, engine-identical SQL). The CI `tests-pgsql` lane verifies on the human push (the loop never pushes — development.md).

## Active Change & Next Task
- **`club-credit` — 4/15 done.** Next: **2.1 `IssueClubCredit` Action** (`app/Modules/Parties/Actions/IssueClubCredit.php`) + exceptions (`IllegalClubCreditTransition` and/or a `ClubCreditIssuancePrecondition`). One `DB::transaction`; re-read Profile + Club `lockForUpdate()`; guard `Club.generates_credit === true` AND `Club.fee !== null` (design L2 fee-null guard); create credit `active` with `amount = Club.fee` verbatim, `remaining = amount`, `valid_from = CarbonImmutable::now()`, `valid_to = now()->endOfYear()`. **Audit-only**: NO `record(...)`, NO `MembershipFeePaid`/`ClubCredit*` event class (§11.4 — Module E's). The one-active index makes re-issue while `active` fail. Mirror `ActivateProfile`/`LapseProfile` docblock style (pin K.18/K.19 + Module-E seams). Then 2.2 tests → 3.x apply/K.17 carry-forward → 4.x forfeit/restore → 5.x §11.4 guard + i18n + docs + gate.
- 3 gate decisions RESOLVED (design L2/L3/L5): **audit-only writers**; **`Club.fee` verbatim** (full-fee→full-credit, `valid_to`=31 Dec); **full FSM + seams** (shipped Profile Actions untouched).

## Blockers & Decisions Needed
- **No active blocker.** No open ADR gate stepped through. `main` in sync with `origin/main`.
- Cross-module triggers are deferred SEAMS (not blockers): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-043 conversion; year-end scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **Scoped within-module `hasOne` + nullable-accessor test (NEW, 1.4):** `hasOne(Related::class)->where('col', Enum::Case->value)`, return `HasOne<Related,$this>` (larastan 3.x keeps the generic through `->where()`); `@property-read Related|null` (a scoped hasOne CAN be null — unlike a required `belongsTo`, typed non-null). In tests, `expect()->not->toBeNull()` does NOT narrow for PHPStan max → chain via the suite-wide `?->` idiom (`$rel?->is($x)`), still strict.
- **Parties model+factory idiom** (`Club`/`Profile`): `$table`+`$guarded=[]`+`casts()` (`MoneyCast` pairs, enum cast, `immutable_datetime`)+`newFactory()`+`@property` docblock; factory passes `Money` straight through the cast + a `belongsTo` parent via nested factory; set a value-set column explicitly when its DB default is absent.
- **`{@see \FQCN}` forward-ref trap:** referencing a not-yet-existing class via `{@see \FQCN}` → Pint hoists it to a real `use` → reds PHPStan. Plain backticks for forward refs.
- **Parties migration idiom** (1.2): partial unique index via raw `DB::statement('CREATE UNIQUE INDEX … WHERE …')` on BOTH engines; PG-only `CHECK` from `Enum::cases()`; `down()` = bare `dropIfExists`; don't down-test a migration.
