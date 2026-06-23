---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` task 3.2 DONE — `ApplyClubCredit` redemption test matrix complete; ralph loop, 8/15 tasks).** Module K §11 Club Credit, greenfield, extends `party-registry`. Expanded `ClubCreditRedemptionTest.php` (+2 `it()`, now 8/8) with the only two cases 3.1 didn't ship: (1) **freeze-THEN-restore round-trip** via the REAL lifecycle Actions — `SuspendProfile` (active→suspended) → 9000-EUR redeem rejected (frozen), credit untouched; `ReactivateProfile` (suspended→active) → the SAME redeem succeeds (16000 left, stays active), proving the freeze is a LIVE Profile-state read; (2) **§11.4 no-event delta** — a FULL redeem (active→redeemed) records ZERO `domain_events` (snapshot `count()`, assert `->toBe($before)`, the 2.2 idiom). The six prior cases (partial/full/from-state/currency/over-application/freeze-reject) shipped in 3.1 — not duplicated.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: full suite 1529/1529 (8377 assn); PHPStan max 0 err; `pint --test` clean; `openspec validate club-credit --strict` valid.**
- **Run the full suite with `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (the laravel/pao wrapper OOMs at 128 MB; lessons.md 2026-06-20).
- **PG17 lane:** 3.2 is test-only, NO DDL. Redemption = integer minor-unit arithmetic + `update()`; Suspend/Reactivate write `Profile.state` + `domain_events` (PG17-green suspension idiom); no-event delta uses `count()`; no clock-sensitive window asserted. Engine-identical. CI `tests-pgsql` verifies on the human push (the loop never pushes).

## Active Change & Next Task
- **`club-credit` — 8/15 done.** Next: **4.1 `ForfeitClubCredit` Action** (`app/Modules/Parties/Actions/ForfeitClubCredit.php`): one `DB::transaction`, `lockForUpdate()` re-read; guard `state === Active` → else add **`IllegalClubCreditTransition::cannotForfeit`** (the SECOND from-state edge; `cannotApply` shipped in 3.1, same exception class); set `state = Forfeited` (terminal). AUDIT-ONLY (no event/recorder/ActorContext — mirror `IssueClubCredit`/`ApplyClubCredit`). **Register `ForfeitClubCredit` in `SupplyLifecycleChainTest`'s `$clubCreditWriters`** (the whitelist-registration pattern — every non-`Create*` Action must). Docblock pins the four §11.3 triggers as deferred seams + notes forfeit-before-issue is provable via the one-active index. Then 4.2 `RestoreClubCredit` → 4.3 forfeiture tests → 5.x §11.4 guard + i18n + docs + gate.
- Gate decisions RESOLVED (design L1/L2/L3/L6/L7): structural one-active partial index; `Club.fee` verbatim; audit-only writers; redemption guard order + exception split.

## Blockers & Decisions Needed
- **No active blocker.** No open ADR gate stepped through. `main` in sync with `origin/main`.
- **4.2 open micro-decision:** where the restore one-active-conflict exception lives — it's NOT a from-state issue (the credit IS `redeemed`), so likely a precondition (a `ClubCreditRestorePrecondition` or a sibling factory). Decide in 4.2.
- Cross-module triggers stay deferred SEAMS (not blockers): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-110/111 + DEC-043 conversion; year-end scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **Freeze-then-restore via REAL lifecycle Actions (NEW, 3.2):** for a "state X gates Y, restore lifts it" round-trip, drive the gate state with the real transition Actions (`SuspendProfile`→`ReactivateProfile`) and re-run the IDENTICAL gated call before/after — same input throws then succeeds, proving the guard reads LIVE state. Cleaner/more production-like than a born-suspended fixture. Suspend/Reactivate emit their own events — irrelevant to the round-trip test; the no-event assertion lives in a SEPARATE test that calls neither.
- **No-event delta idiom (2.2):** snapshot `DomainEvent::query()->count()` then `->toBe($before)`. 5.1 generalizes it to all 4 writers + the class-absence loop.
- **Exception split for a guarded transition Action (3.1):** `Illegal{Entity}Transition` = FSM from-state guards ONLY (`cannot{Edge}({Entity}State $from)`, `:state`); `{Entity}{Op}Precondition` = value/context guards. Both `extend RuntimeException`, localize via `(string) __()`, interpolate only ids/enum/ISO-currency (never money minor-units — PII). 4.1 adds `cannotForfeit`; 4.2 adds `cannotRestore`.
- **A NEW non-`Create` Parties Action reds `SupplyLifecycleChainTest`'s allow-list** — register each writer in `$clubCreditWriters`; ONLY Actions on disk. `IssueClubCredit`+`ApplyClubCredit` registered; 4.1/4.2 each append theirs.
