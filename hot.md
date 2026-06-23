---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` task 2.1 DONE — `IssueClubCredit` Action landed; ralph loop, 5/15 tasks).** Module K §11 Club Credit, greenfield, extends `party-registry`. The first writer Action: AUDIT-ONLY (no domain event, no `DomainEventRecorder`/`ActorContext` — mirrors `SuspendAccount`/`RecordKycVerified`), one `DB::transaction`, Profile+Club `lockForUpdate()`, two issuance preconditions, creates an `active` credit `amount = Club.fee` verbatim / `remaining = amount` / `valid_to = now()->endOfYear()`. The artifacts ARE the plan.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: full suite 1517/1517 (8333 assn); PHPStan max 0 err; `pint --test` clean; `openspec validate club-credit --strict` valid.**
- **Run the full suite with `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (the laravel/pao wrapper OOMs at 128 MB; lessons.md 2026-06-20).
- **PG17 lane:** no local PG; 2.1 added no DDL (the Action writes 1.2's table; index-violation + timestamptz window are engine-identical). CI `tests-pgsql` verifies on the human push (the loop never pushes).

## Active Change & Next Task
- **`club-credit` — 5/15 done.** Next: **2.2 `IssueClubCredit` tests** — EXPAND `tests/Feature/Modules/Parties/ClubCreditIssuanceTest.php` (already holds happy-path + one-active re-issue from 2.1). Add: **reject** `generates_credit = false` (no row) + **reject** null `fee` (no row), asserting by exception CLASS `ClubCreditIssuancePrecondition` (i18n keys land in 5.2 — `__()` returns the key meanwhile); **Hold-asymmetry** (place an account/Profile Hold → issuance STILL succeeds, §11.2 — NOT Hold-gated); **§11.4 no-event** (snapshot `DomainEvent::query()->count()` across the issue → delta 0). Verify on PG17. Then 3.x apply/K.17 carry-forward (+ create `IllegalClubCreditTransition` — the first FSM from-state guard) → 4.x forfeit/restore → 5.x §11.4 guard + i18n + docs + gate.
- Gate decisions RESOLVED (design L1/L2/L3): structural one-active partial index (NO app-level pre-check); `Club.fee` verbatim; audit-only writers.

## Blockers & Decisions Needed
- **No active blocker.** No open ADR gate stepped through. `main` in sync with `origin/main`.
- Cross-module triggers stay deferred SEAMS (not blockers): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-043 conversion; year-end scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **A NEW non-`Create` Parties Action reds `SupplyLifecycleChainTest`'s exhaustive Action allow-list (NEW, 2.1):** that test `toEqualCanonicalizing`s every non-`Create*` Action globbed from disk. Register each writer in its `$…Transitions`/`$clubCreditWriters` array + the spread — ONLY Actions already on disk (pre-listing a future sibling fails the other way). Hits 3.1/4.1/4.2. Captured in lessons.md + progress.md.
- **`Club.fee` is `Money|null` → never pass straight to `Money::equals()`/`minus()`:** build an explicit non-null `Money::of(…)` local for tests; the Action narrows via the `if ($fee === null) throw` fee-null guard. (progress.md Codebase Patterns.)
- **Scoped within-module `hasOne` + nullable-accessor `?->` test idiom; Parties model+factory idiom; `{@see \FQCN}` forward-ref Pint trap; Parties migration idiom.** (progress.md.)
