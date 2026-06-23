---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` task 3.1 DONE — `ApplyClubCredit` redemption Action; ralph loop, 7/15 tasks).** Module K §11 Club Credit, greenfield, extends `party-registry`. Shipped the SOLE writer of credit `remaining` + the `active → redeemed` transition: `handle(int $clubCreditId, Money $redeemed)`, one `DB::transaction`, `lockForUpdate()` re-read of credit + Profile, FOUR pre-write guards (state→currency→over-application→freeze), K.17 carry-forward (`minus`; zero→`Redeemed`, positive→stay `Active`), AUDIT-ONLY (no event). Created two exceptions mirroring the issuance split. The artifacts ARE the plan.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: full suite 1527/1527 (8368 assn); PHPStan max 0 err; `pint --test` clean; `openspec validate club-credit --strict` valid.**
- **Run the full suite with `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (the laravel/pao wrapper OOMs at 128 MB; lessons.md 2026-06-20).
- **PG17 lane:** 3.1 is NO-DDL (writes 1.2's table). Redemption path is engine-identical — integer minor-unit arithmetic + `update()` of `remaining`/`state`; no clock-sensitive validity window asserted. CI `tests-pgsql` verifies on the human push (the loop never pushes).

## Active Change & Next Task
- **`club-credit` — 7/15 done.** Next: **3.2 `ApplyClubCredit` tests** (`tests/Feature/Modules/Parties/ClubCreditRedemptionTest.php` — EXPAND, file already exists with 6 tests from 3.1). Add ONLY the two cases 3.1 didn't ship: (1) **freeze-THEN-restore round-trip** — suspend the Profile → `ApplyClubCredit` rejected; `ReactivateProfile` the Profile → the SAME redeem now succeeds (proves the credit becomes mutable again on restore — needs the REAL suspended→active Profile, not a Hold fixture); (2) **§11.4 no-event delta** — snapshot `$before = DomainEvent::query()->count()`, redeem, assert `->toBe($before)` (the 2.2 idiom). Partial/full/over-application/currency-mismatch/freeze-reject already shipped in 3.1 — do NOT duplicate. Verify PG17. Then 4.1 `ForfeitClubCredit` → 4.2 `RestoreClubCredit` → 4.3 forfeiture tests → 5.x §11.4 guard + i18n + docs + gate.
- Gate decisions RESOLVED (design L1/L2/L3/L6/L7): structural one-active partial index; `Club.fee` verbatim; audit-only writers; redemption guard order + exception split.

## Blockers & Decisions Needed
- **No active blocker.** No open ADR gate stepped through. `main` in sync with `origin/main`.
- **4.2 open micro-decision:** where the restore one-active-conflict exception lives — it's NOT a from-state issue (the credit IS `redeemed`), so likely a precondition (a `ClubCreditRestorePrecondition` or a sibling factory). Decide in 4.2.
- Cross-module triggers stay deferred SEAMS (not blockers): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-110/111 + DEC-043 conversion; year-end scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **Exception split for a guarded transition Action (NEW, 3.1):** `Illegal{Entity}Transition` = FSM from-state guards ONLY (`cannot{Edge}({Entity}State $from)`, `:state`); `{Entity}{Operation}Precondition` = value/context guards on an otherwise-valid-from-state row (currency/over-application/freeze). Both `extend RuntimeException`, localize via `(string) __()`, interpolate only ids/enum/ISO-currency tokens (never money minor-units — PII). Guard ORDER from-state→currency→over-application→freeze isolates each in tests. Reused by 4.1/4.2.
- **Pint hoists docblock `{@see Class::m()}` to a real `use` (3.1):** BENIGN when the class EXISTS (PHPStan ignores docblock-only imports; Pint is idempotent). The 1.3 forward-ref trap was the OPPOSITE — `{@see}` to a not-yet-created class → PHPStan red. Rule: `{@see}` an existing class freely; backtick a not-yet-shipped sibling.
- **No-event delta idiom (2.2):** snapshot `DomainEvent::query()->count()` then `->toBe($before)`. 3.2 reuses it; 5.1 generalizes to all 4 writers + the class-absence loop.
- **A NEW non-`Create` Parties Action reds `SupplyLifecycleChainTest`'s allow-list** — register each writer in `$clubCreditWriters` + spread; ONLY Actions on disk. `IssueClubCredit`+`ApplyClubCredit` registered; 4.1/4.2 each append theirs.
