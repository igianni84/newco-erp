---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` task 4.2 DONE — `RestoreClubCredit` Action shipped; ralph loop, 10/15 tasks).** Module K §11 Club Credit, greenfield, extends `party-registry`. `RestoreClubCredit` is the SOLE writer of `redeemed → active` (the order-cancellation downstream effect) and COMPLETES the 4-writer set (Issue/Apply/Forfeit/Restore). AUDIT-ONLY (no event/recorder/ActorContext). `handle(int $clubCreditId)`: one `DB::transaction`, `lockForUpdate()` re-read of the credit **AND its Profile** (the Profile lock serializes a concurrent restore/issue — the one-active race), then TWO guards: (1) `state !== Redeemed` → `IllegalClubCreditTransition::cannotRestore` (3rd/last from-state factory; explicit `!== Redeemed`, no `isRedeemed()` helper); (2) `$profile->activeClubCredit()->exists()` → NEW `ClubCreditRestorePrecondition::profileHasActiveCredit` (reject rather than breach the partial index — design L1/L7). Then `update(state=Active, remaining=amount)`. Registered `RestoreClubCredit` in `SupplyLifecycleChainTest`'s `$clubCreditWriters` (now COMPLETE). 3 restore tests added to `ClubCreditForfeitureTest.php` (7 total now).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: full suite 1536/1536 (8408 assn); PHPStan max 0 err; `pint --test` clean; `openspec validate club-credit --strict` valid.**
- **Run the full suite with `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (the laravel/pao wrapper OOMs at 128 MB; lessons.md 2026-06-20).
- **PG17 lane:** 4.2 is no-DDL. Restore writes `state` + `remaining=amount` (a Money COPY, no arithmetic — engine-identical); `activeClubCredit()->exists()` is identical SQL both engines; `lockForUpdate` real on PG / no-op SQLite; no clock-sensitive window. CI `tests-pgsql` verifies on the human push (the loop never pushes).

## Active Change & Next Task
- **`club-credit` — 10/15 done.** Next: **4.3 Forfeiture/Restoration tests** (EXPAND `tests/Feature/Modules/Parties/ClubCreditForfeitureTest.php` — do NOT duplicate 4.1/4.2's shipped cases). Add ONLY: (a) **forfeit-before-issue ordering** — active credit → `IssueClubCredit` rejects on one-active; then `ForfeitClubCredit` + `IssueClubCredit` succeeds (needs a Profile whose Club has `generates_credit=true` + non-null `fee` — the issuance-test idiom; the plain factory Profile's default Club qualifies); (b) **restore-after-forfeit** terminal edge — forfeit active → `RestoreClubCredit` rejects via `cannotRestore` (the 3rd terminal edge); (c) **§11.4 no-`domain_events` delta** — snapshot `DomainEvent::query()->count()` across the writers → `->toBe($before)` (the 2.2 idiom). Verify PG17. Then 5.x: §11.4 audit-only guard test (5.1) → i18n (5.2) → docs (5.3) → full-suite gate (5.4).
- Gate decisions RESOLVED (design L1/L2/L3/L6/L7): structural one-active partial index; `Club.fee` verbatim; audit-only writers; redemption guard order; restore one-active-respecting + **`remaining = amount` restoration**.

## Blockers & Decisions Needed
- **No active blocker.** No open ADR gate stepped through. `main` in sync with `origin/main`.
- **i18n keys referenced, land in 5.2** (`__()` returns the key meanwhile; tests assert by CLASS): `parties.club_credit.{cannot_apply, cannot_forfeit, cannot_restore, currency_mismatch, over_application, frozen_while_suspended, issuance_no_credit_policy, issuance_no_fee, restore_active_conflict}`.
- Cross-module triggers stay deferred SEAMS (not blockers): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-110/111 + DEC-043 conversion + the order-cancellation restore trigger; year-end scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **Terse task step under-specifies vs the delta-spec SCENARIO (4.2):** task 4.2 said "set `state = Active`" but the scenario + design L7 require `remaining` **restored** → since `redeemed` ⟹ `remaining = 0`, restore writes `remaining = amount`. **Rule: read the delta-spec scenario's THEN clauses + cited design before coding field writes; money-adjacent (invariant 6) writes must trace to the spec, not the headline verb.** (New Codebase Pattern in progress.md.)
- **Writer Profile-read asymmetry:** only **forfeiture** reads just the credit; **Issue/Apply/Restore** all `lockForUpdate` the Profile too (Apply for the freeze guard; Issue/Restore for the one-active serialization). Restore's one-active check = `$profile->activeClubCredit()->exists()` (the 1.4 relation).
- **Exception families:** `Illegal{Entity}Transition` = FSM from-state guards ONLY (`cannot{Edge}`; now ships `cannotApply`/`cannotForfeit`/`cannotRestore`); `{Entity}{Op}Precondition` = value/context guards (`ClubCreditIssuance`/`Redemption`/`Restore` Precondition). Both `extend RuntimeException`, localize via `(string) __()`, interpolate only ids/enum/ISO-currency (never money minor-units — PII).
- **`{@see}` hoist trap:** fully-qualified `{@see \App\…\X}` to a not-yet-created sibling → Pint hoists to a real `use` → PHPStan red. Use backticks for not-yet-existing classes; unqualified `{@see X}` (same-namespace or prose) is safe. `knowledge/laravel/rules.md` "forward-refs".
- **A NEW non-`Create` Parties Action reds `SupplyLifecycleChainTest`'s allow-list** — register each writer in `$clubCreditWriters` (now COMPLETE: Issue/Apply/Forfeit/Restore). ONLY Actions on disk.
