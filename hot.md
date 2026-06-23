---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` task 5.2 DONE — i18n `club_credit` lang group; ralph loop, 13/15).** Added the `club_credit` group (9 keys) to `lang/en/parties.php` so the four Club Credit exceptions' `__('parties.club_credit.…')` calls resolve real English copy (invariant 12; English baseline only — DEC-127). Keys + placeholders verified against each factory first: `cannot_apply`/`cannot_forfeit`/`cannot_restore` (`:state`); `issuance_no_credit_policy`/`issuance_no_fee` (`:club`); `currency_mismatch` (`:expected`/`:actual`); `over_application`/`frozen_while_suspended`/`restore_active_conflict` (`:credit`). New `tests/Unit/Modules/Parties/Exceptions/ClubCreditExceptionsTest.php` (19 tests / 68 assn) mirrors `StatusTransitionExceptionsTest` with TWO PII registers: a digit-free `$assertStateRejection` (`:state` + currency tokens) and an id-bearing `$assertIdRejection` (`:club`/`:credit` ids — a digit is EXPECTED, no balance leaks because the factories take only `int`).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: full suite 1560/1560 (8500 assn); PHPStan max 0; `pint --test` clean; `openspec validate club-credit --strict` valid.**
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (laravel/pao OOMs at 128 MB; lessons.md 2026-06-20).
- **PG17:** 5.2 has NO DB touch — pure translator + lang-array resolution (`uses(TestCase::class)`, no `RefreshDatabase`). Engine-irrelevant; nothing for the `tests-pgsql` lane to differ on.

## Active Change & Next Task
- **`club-credit` — 13/15 done.** Next: **5.3 docs** (docs-only, no test, must not break the build). Three parts: (1) refine the **CONTEXT.md** Club Credit glossary entry — the entity + the 4 within-module writers (Issue/Apply/Forfeit/Restore) + the audit-only §11.4 boundary (Module K records state, Module E owns the events); (2) record the now-**CLOSED** `club-credit` freeze seam in the CONTEXT.md notes neighbouring the `ActivateProfile`/`RenewProfile`/`SuspendProfile`/`SuspendCustomer` docblocks (residual Module-E/S/scheduler/cancellation-cascade seams REMAIN — don't claim full closure); (3) add `knowledge/module-k` (or `data-model`) notes — the audit-only-writer-for-Module-E-owned-events pattern + the one-active partial-index ↔ restore interaction (update `knowledge/INDEX.md` if a new domain). Then 5.4 full-suite gate (both engines + PHPStan + Pint + openspec validate) → CHANGE_COMPLETE.

## Blockers & Decisions Needed
- **No active blocker.** No open ADR gate stepped through. `main` in sync with `origin/main`.
- Deferred SEAMS (not blockers): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-110/111 + DEC-043 conversion + order-cancellation restore; year-end scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **i18n task = lang group + sibling `{Domain}ExceptionsTest.php` with TWO PII registers** (NEW, 5.2, in progress.md Codebase Patterns): digit-free `:state`/currency keys get `preg_match('/\d/',$msg)===0` + no-`@`; id-bearing `:club`/`:credit` keys DON'T (the id is a digit, operator-facing not PII) — assert contains-id + no-`@`, lean on the `int`-only factory signature for no-balance-leak. Parametrize "resolves every key" with a token absent from all templates (`'retired'` / a distinctive id) so presence proves interpolation; a missing key makes Laravel echo it back.
- **The 4 writers (Issue/Apply/Forfeit/Restore) are COMPLETE + i18n-wired**, all in `SupplyLifecycleChainTest`'s `$clubCreditWriters` allow-list. 5.3/5.4 add NO new Action/key.
- **`{@see}`-hoist trap (recurred 3× this slice):** a `{@see \FQN}` to a not-yet-existing OR non-autoloadable sibling (incl. Pest test files) → Pint hoists to a real `use` → PHPStan red. Backticks for those; `{@see X}` only for existing app classes (`knowledge/laravel/rules.md`).
- **`class_exists(<literal-absent-FQN>)` reds PHPStan max** (`function.impossibleType`) — use filesystem `glob` for an absence proof; `class_exists` only for PRESENT classes (lessons.md, 5.1).
