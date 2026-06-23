---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` task 4.3 DONE — Forfeiture/Restoration tests complete; ralph loop, 11/15).** Section 4 is now COMPLETE. Task 4.3 EXPANDED `tests/Feature/Modules/Parties/ClubCreditForfeitureTest.php` (now 10 tests / 40 assn) with the THREE cases 4.1/4.2 didn't ship (did NOT duplicate the seven present): (1) **forfeit-before-issue ordering** (§11.3 / design L5) — REAL `IssueClubCredit` mints active → re-issue while active throws `QueryException` (partial index, 1 active remains) → `ForfeitClubCredit` frees the slot → re-issue mints a FRESH active (`$second->id !== $first->id`), original `forfeited`; (2) **restore-after-forfeit terminal edge** — forfeit active → `RestoreClubCredit` rejected via `IllegalClubCreditTransition` (`cannotRestore`, 3rd/last terminal edge); (3) **§11.4 no-`domain_events` delta across BOTH forfeit+restore** — `$before = count()` → run both → `->toBe($before)` (2.2/3.2 idiom).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: full suite 1539/1539 (8423 assn); PHPStan max 0; `pint --test` clean; `openspec validate club-credit --strict` valid.**
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (laravel/pao OOMs at 128 MB; lessons.md 2026-06-20).
- **PG17:** 4.3 test-only, no DDL. Forfeit-before-issue relies on the partial-index violation aborting only the inner SAVEPOINT (PG17-green issuance one-active idiom — `IssueClubCredit` wraps in `DB::transaction`); re-issue's `now()->endOfYear()` NOT asserted (only state/identity) → clock-free, no tz flake. CI `tests-pgsql` verifies on the human push.

## Active Change & Next Task
- **`club-credit` — 11/15 done.** Next: **5.1 §11.4 audit-only guard test** (NEW `tests/Feature/Modules/Parties/ClubCreditEventOwnershipTest.php`): (a) a **class-absence loop** over `app/Modules/Parties/Events` asserting NO `MembershipFeePaid` / `ClubCreditIssued` / `ClubCreditApplied` / `ClubCreditRestored` / `ClubCreditForfeited` class exists (mirror `SupplyLifecycleChainTest`'s event-non-existence loop; design L9 pre-named no forbidden-list); (b) the **four writers record zero `domain_events`** (generalizes the deltas 2.2/3.2/4.3 into one ownership test). Then 5.2 i18n (`club_credit`, 9 keys below) → 5.3 docs → 5.4 gate.
- Gate decisions RESOLVED (design L1/L2/L3/L6/L7): structural one-active partial index; `Club.fee` verbatim; audit-only writers; redemption guard order; restore one-active-respecting + `remaining = amount`.

## Blockers & Decisions Needed
- **No active blocker.** No open ADR gate stepped through. `main` in sync with `origin/main`.
- **i18n keys land in 5.2** (`__()` returns the key meanwhile; tests assert by CLASS): `parties.club_credit.{cannot_apply, cannot_forfeit, cannot_restore, currency_mismatch, over_application, frozen_while_suspended, issuance_no_credit_policy, issuance_no_fee, restore_active_conflict}`.
- Deferred SEAMS (not blockers): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-110/111 + DEC-043 conversion + order-cancellation restore; year-end scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **The 4 writers (Issue/Apply/Forfeit/Restore) are COMPLETE, all registered in `SupplyLifecycleChainTest`'s `$clubCreditWriters` allow-list** (ONLY Actions on disk red the exact-match `toEqualCanonicalizing`). 5.1 onward adds NO new Action.
- **Profile-read asymmetry:** only forfeiture reads just the credit; Issue/Apply/Restore all `lockForUpdate` the Profile (Apply for freeze, Issue/Restore for one-active serialization). Restore's check = `$profile->activeClubCredit()->exists()`.
- **Exception families:** `IllegalClubCreditTransition` = FSM from-state ONLY (`cannotApply/Forfeit/Restore` all ship); `ClubCredit{Issuance/Redemption/Restore}Precondition` = value/context guards. Localize via `(string) __()`; interpolate only ids/enum/ISO-currency (never money minor-units — PII).
- **`{@see}` hoist trap:** fully-qualified `{@see \App\…\X}` to a not-yet-created sibling → Pint hoists to a real `use` → PHPStan red. Backticks for not-yet-existing; `{@see X}` to an existing class is safe (`knowledge/laravel/rules.md`).
