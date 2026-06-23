---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` task 2.2 DONE — `IssueClubCredit` test matrix complete; ralph loop, 6/15 tasks).** Module K §11 Club Credit, greenfield, extends `party-registry`. Expanded `ClubCreditIssuanceTest.php` with the 4 remaining cases beside 2.1's happy-path + one-active: reject `generates_credit=false` (no row), reject null `fee` (no row) — both by exception CLASS `ClubCreditIssuancePrecondition` (i18n keys land 5.2); §11.2 Hold-asymmetry (active Profile-scope Hold does NOT block issuance); §11.4 no-event (DomainEvent count delta 0). The artifacts ARE the plan.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: full suite 1521/1521 (8343 assn); PHPStan max 0 err; `pint --test` clean; `openspec validate club-credit --strict` valid.**
- **Run the full suite with `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (the laravel/pao wrapper OOMs at 128 MB; lessons.md 2026-06-20).
- **PG17 lane:** 2.2 is test-only, NO DDL — exercises 1.2's schema + the PG17-green `parties_holds`/`domain_events` tables via the identical `ComplianceReadApiTest`/`ClubTest` factory idioms (precondition rejections throw BEFORE any insert; Hold/event paths engine-identical). CI `tests-pgsql` verifies on the human push (the loop never pushes).

## Active Change & Next Task
- **`club-credit` — 6/15 done.** Next: **3.1 `ApplyClubCredit` Action** (`app/Modules/Parties/Actions/ApplyClubCredit.php`) — inputs `clubCreditId` + redeemed `Money`; one `DB::transaction`, `lockForUpdate()` re-read credit + Profile. FOUR guards before any write: credit `state === Active`; redeemed `currency` equals credit currency (explicit equality → localized exception so `Money::minus` never throws); redeemed `minorUnits <= remaining.minorUnits`; Profile `state !== Suspended` (freeze — AC-K-FSM-2a). Then `remaining = remaining->minus($redeemed)`; if `remaining.minorUnits === 0` → `state = Redeemed`, else stay `Active` (K.17 carry-forward). **Audit-only** (no event). Create **`IllegalClubCreditTransition`** (first FSM from-state guard). Register `ApplyClubCredit` in `SupplyLifecycleChainTest`'s `$clubCreditWriters`. Then 3.2 tests → 4.x forfeit/restore → 5.x §11.4 guard + i18n + docs + gate.
- Gate decisions RESOLVED (design L1/L2/L3): structural one-active partial index; `Club.fee` verbatim; audit-only writers.

## Blockers & Decisions Needed
- **No active blocker.** No open ADR gate stepped through. `main` in sync with `origin/main`.
- Cross-module triggers stay deferred SEAMS (not blockers): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-043 conversion; year-end scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **Hold-fixture for a "covered scope" precondition (NEW, 2.2):** when a test needs only an INERT active Hold over a scope (no suspension coupling), use `Hold::factory()->create(['scope_type'=>HoldScope::Profile,'scope_id'=>$x->id,'hold_type'=>HoldType::Fraud])` — bypasses `PlaceHold`, so NO event + NO Hold→suspended coupling (HoldFactory = pure fixture; `ComplianceReadApiTest` idiom). 3.2's freeze test, by contrast, needs the REAL suspended Profile.
- **No-event delta idiom (2.2):** snapshot `$before = DomainEvent::query()->count()` then `->toBe($before)` — more honest than bare `->toBe(0)`. Reused by 3.1/4.x; 5.1 generalizes to all 4 writers + class-absence loop.
- **A NEW non-`Create` Parties Action reds `SupplyLifecycleChainTest`'s allow-list** — register each writer in `$clubCreditWriters` + spread; ONLY Actions on disk. Hits 3.1/4.1/4.2.
- **`Club.fee` is `Money|null` → build an explicit non-null `Money::of(…)` local in tests** (never pass `$club->fee` straight to `Money::equals()`). Parties model+factory, scoped `hasOne` `?->`, migration, and `{@see \FQCN}` forward-ref Pint-trap idioms all in progress.md Codebase Patterns.
