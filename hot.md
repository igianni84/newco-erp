---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` task 4.1 DONE — `ForfeitClubCredit` Action shipped; ralph loop, 9/15 tasks).** Module K §11 Club Credit, greenfield, extends `party-registry`. `ForfeitClubCredit` is the SOLE writer of the `active → forfeited` edge, AUDIT-ONLY (no event/recorder/ActorContext). `handle(int $clubCreditId)`: one `DB::transaction`, `lockForUpdate()` re-read of the credit ONLY (no Profile re-read — forfeiture is NOT freeze-gated, unlike redemption), ONE from-state guard `! state->isActive()` → `IllegalClubCreditTransition::cannotForfeit` (the 2nd from-state factory after `cannotApply`), then `update(state=Forfeited)`. `remaining` left INTACT (residual = Module-S DEC-043 closure-conversion input). Added `cannotForfeit`; registered `ForfeitClubCredit` in `SupplyLifecycleChainTest`'s `$clubCreditWriters`. New `ClubCreditForfeitureTest.php` (4 tests: happy path + non-active reject + terminal ×2 [2nd-forfeit + apply-on-forfeited]). 4.3 expands this same file.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: full suite 1533/1533 (8394 assn); PHPStan max 0 err; `pint --test` clean; `openspec validate club-credit --strict` valid.**
- **Run the full suite with `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (the laravel/pao wrapper OOMs at 128 MB; lessons.md 2026-06-20).
- **PG17 lane:** 4.1 is no-DDL. Forfeiture = a single `update()` of `state` (engine-identical); no Money arithmetic, no clock-sensitive window. CI `tests-pgsql` verifies on the human push (the loop never pushes).

## Active Change & Next Task
- **`club-credit` — 9/15 done.** Next: **4.2 `RestoreClubCredit` Action** (`app/Modules/Parties/Actions/RestoreClubCredit.php`): one `DB::transaction`; `lockForUpdate()` re-read the credit + its Profile; guard `state === Redeemed` → else **`IllegalClubCreditTransition::cannotRestore`** (the THIRD/last from-state factory) **AND** a no-other-`active`-credit precondition → else a NEW **`ClubCreditRestorePrecondition`** (the conflict is NOT a from-state issue — the credit IS `redeemed` — so it's a precondition: reject rather than violate the one-active partial index, design L7); set `state = Active`. AUDIT-ONLY (no event/recorder/ActorContext). **Register `RestoreClubCredit` in `$clubCreditWriters`** (completes the 4-writer set). Docblock pins the Module-S order-cancellation-window trigger as the seam. **Use plain backticks (NOT `{@see}`) for any sibling created in a later task.** Then 4.3 forfeiture/restore tests → 5.x §11.4 guard + i18n + docs + gate.
- Gate decisions RESOLVED (design L1/L2/L3/L6/L7): structural one-active partial index; `Club.fee` verbatim; audit-only writers; redemption guard order + exception split; restore one-active-respecting.

## Blockers & Decisions Needed
- **No active blocker.** No open ADR gate stepped through. `main` in sync with `origin/main`.
- **4.2 micro-decision RESOLVED in plan:** the restore one-active-conflict exception is a NEW `ClubCreditRestorePrecondition` (sibling of `ClubCreditIssuancePrecondition`/`ClubCreditRedemptionPrecondition`), NOT a from-state factory. `cannotRestore` (from-state, requires `redeemed`) lives on `IllegalClubCreditTransition`.
- Cross-module triggers stay deferred SEAMS (not blockers): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-110/111 + DEC-043 conversion; year-end scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **Forfeiture vs redemption — guard asymmetry (4.1):** `ForfeitClubCredit` has ONE guard (from-state) and re-reads ONLY the credit; `ApplyClubCredit` has FOUR (from-state, currency, over-application, freeze) and ALSO re-reads the Profile. Forfeiture is NOT freeze-gated — §11.3 triggers (year-end/renewal/cancellation/closure) must fire on a suspended Profile's credit. Only redemption reads the owning Profile (AC-K-FSM-2a).
- **Pint `{@see}`-hoist trap RECURRED (1.3 lesson):** a fully-qualified `{@see \App\…\RestoreClubCredit}` to a not-yet-created sibling → Pint hoists it to a real `use` → PHPStan red (class not found). **Rule: backticks for not-yet-created siblings; `{@see}` only for existing classes.** Already in `knowledge/laravel/rules.md` "forward-refs" — apply UP FRONT in 4.2.
- **Exception families (3.1):** `Illegal{Entity}Transition` = FSM from-state guards ONLY (`cannot{Edge}`, `:state`); `{Entity}{Op}Precondition` = value/context guards. Both `extend RuntimeException`, localize via `(string) __()`, interpolate only ids/enum/ISO-currency (never money minor-units — PII). 4.1 added `cannotForfeit`; 4.2 adds `cannotRestore` + the new `ClubCreditRestorePrecondition`.
- **No-event delta idiom (2.2):** snapshot `DomainEvent::query()->count()` then `->toBe($before)`. 5.1 generalizes it to all 4 writers + the class-absence loop.
- **A NEW non-`Create` Parties Action reds `SupplyLifecycleChainTest`'s allow-list** — register each writer in `$clubCreditWriters` (now `IssueClubCredit`+`ApplyClubCredit`+`ForfeitClubCredit`; 4.2 appends `RestoreClubCredit`). ONLY Actions on disk.
