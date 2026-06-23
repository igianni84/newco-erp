---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` task 5.1 DONE — §11.4 event-ownership guard test; ralph loop, 12/15).** Added `tests/Feature/Modules/Parties/ClubCreditEventOwnershipTest.php` (2 tests / 9 assn), the §11.4 ownership boundary (design L3/L9; party-registry "Club Credit State Recording Is Module-E-Owned"). One `it()` per delta scenario: (1) **class-absence loop** — `glob(app_path('Modules/Parties/Events/*.php'))` → `basename` → `not->toContain` each of `MembershipFeePaid`/`ClubCreditIssued`/`ClubCreditApplied`/`ClubCreditRestored`/`ClubCreditForfeited` (mirrors `SupplyLifecycleChainTest`'s event-non-existence loop; non-empty guard against a vacuous pass); (2) **four-writer zero-event** — drives the FULL FSM end-to-end through all four REAL writers on ONE Profile (Issue → Apply partial/K.17 → Forfeit → re-Issue → Apply full→redeemed → Restore), `DomainEvent::query()->count()` delta = 0. Generalizes the per-writer deltas 2.2/3.2/4.3 into one boundary test.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: full suite 1541/1541 (8432 assn); PHPStan max 0; `pint --test` clean; `openspec validate club-credit --strict` valid.**
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (laravel/pao OOMs at 128 MB; lessons.md 2026-06-20).
- **PG17:** 5.1 test-only, no DDL. `glob`/`basename` reflection is filesystem-only; `count()` snapshot engine-identical; re-issue's `now()->endOfYear()` NOT asserted (only state/identity) → clock-free, no tz flake. CI `tests-pgsql` verifies on the human push.

## Active Change & Next Task
- **`club-credit` — 12/15 done.** Next: **5.2 i18n** (`lang/en/parties.php` — add a `club_credit` group with the 9 keys the exceptions reference: `cannot_apply`, `cannot_forfeit`, `cannot_restore`, `currency_mismatch`, `over_application`, `frozen_while_suspended`, `issuance_no_credit_policy`, `issuance_no_fee`, `restore_active_conflict`). English baseline only (DEC-127). Tests already assert by CLASS, so 5.2 just makes `__()` resolve real messages — verify each key against the exception factories' `__('parties.club_credit.…')` call before writing. Then 5.3 docs (CONTEXT.md glossary + closed freeze seam + knowledge) → 5.4 full-suite gate (both engines + PHPStan + Pint + openspec validate).
- Gate decisions RESOLVED (design L1/L2/L3/L6/L7): structural one-active partial index; `Club.fee` verbatim; audit-only writers; redemption guard order; restore one-active-respecting + `remaining = amount`.

## Blockers & Decisions Needed
- **No active blocker.** No open ADR gate stepped through. `main` in sync with `origin/main`.
- Deferred SEAMS (not blockers): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-110/111 + DEC-043 conversion + order-cancellation restore; year-end scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **To assert a class's ABSENCE under PHPStan max, use the filesystem `glob` — NEVER `class_exists(<literal-absent-FQN>)`** (reds `function.impossibleType`; PHPStan folds it to `false`). `class_exists` is safe ONLY for PRESENT classes (`SupplyLifecycleChainTest` line 285 positive floor). New lessons.md entry; standing trap for any future event-ownership guard.
- **The 4 writers (Issue/Apply/Forfeit/Restore) are COMPLETE**, all registered in `SupplyLifecycleChainTest`'s `$clubCreditWriters` allow-list. 5.x adds NO new Action.
- **`{@see}`-hoist trap (3rd recurrence this slice):** a `{@see \FQN}` to a not-yet-existing OR non-autoloadable sibling (incl. Pest test files) → Pint hoists to a real `use` → PHPStan red. Backticks for those; `{@see X}` only for existing app classes (`knowledge/laravel/rules.md`).
- **Exception families:** `IllegalClubCreditTransition` = FSM from-state (`cannotApply/Forfeit/Restore`); `ClubCredit{Issuance/Redemption/Restore}Precondition` = value/context guards. Localize via `(string) __()`; interpolate only ids/enum/ISO-currency (never money minor-units — PII).
