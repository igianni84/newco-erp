---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph iter 6 — `parties-membership-activation` T2.3 DONE).** `ActivateCustomer` — the LAST demand-side Action: evented Customer activation (`pending → active`). Mirrors `ActivateProducer`/`ActivateProfile` (inject recorder+actor → `DB::transaction` → `lockForUpdate` re-read → from-state guard `status === Pending` else `IllegalCustomerTransition::cannotActivate` → **composite onboarding gate** else `gateNotMet()` → `update(status=Active)` → root `CustomerActivated` `{customer_id, status}`). Gate extracted to a `private onboardingGateClears()`: `email_verified_at ∧ tc_accepted_at ∧ privacy_accepted_at` all set ∧ `sanctions_status === Passed` ∧ `(! kyc_required || kyc_status?->clears() !== false)` (NULL kyc = cleared, DEC-071). Touches ONLY the Customer (no Account transition — § 4.7); NOT auto-fired from screening/KYC (explicit — L6/§ 9.4).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green 833/833** (was 817; +16 from `CustomerOnboardingActivationTest`) on SQLite; phpstan 0; pint clean. **PG17 (docker `postgres:17`, port 55432): whole Parties set (Feature+Unit) 336/336** + focused file 16/16. `openspec validate parties-membership-activation --strict` valid. `git diff main -- composer.json composer.lock` empty. Both guard tests green.
- Branch `ralph/parties-membership-activation`; T2.3 committed locally next (not pushed — human's call).

## Active Change & Next Task
- **`parties-membership-activation` — 6 / 7 tasks done.** Shipped: T1.1 migration (3 acceptance timestamps); T1.2 the two `Illegal*Transition` exceptions; T1.3 the three activation event classes; T2.1 `ApproveProfile`+`DeclineProfile`+OC one-shot lock; T2.2 `ActivateProfile`+`ProfileActivated`; T2.3 `ActivateCustomer`+`CustomerActivated` (+ both guards narrowed: `$demandSideTransitions` now 4; forbidden-list lost `ActivateCustomer`).
- **Next: T3.1 — the close (full chain + docs + cross-engine PG17 close).** One feature test driving the WHOLE slice through the REAL Actions on one Customer: create (Pending, OC NULL) → set 3 acceptance timestamps + `RecordCustomerScreening`(passed) → `ActivateCustomer` (assert `CustomerActivated`) → `CreateProfile` (Applied) → `ApproveProfile` (assert `OriginatingClubLocked` once + OC set) → 2nd-Club `CreateProfile`+`ApproveProfile` (assert NO second lock) → `ActivateProfile` (assert `ProfileActivated`); assert the **exact** `domain_events` name-set `{CustomerCreated, ProfileCreated×2, CustomerOnboardingScreeningPassed, CustomerActivated, OriginatingClubLocked, ProfileActivated}` with **no** `ProfileApproved`/`ProfileRejected`/`MembershipApprovedByProducer`. **Docs:** extend `CONTEXT.md` (approve/decline = audit-only; OC one-shot lock; `ProfileActivated` on fee-paid/free-club; `CustomerActivated` composite gate + the deferred seams). Then run the ENTIRE Parties suite + arch tests on PG17; audit the other 5 guard files stay green unamended. **DB-touching → PG17 REQUIRED.** Then `<promise>CHANGE_COMPLETE</promise>`.

## Blockers & Decisions Needed
- None. Deferred seams stay deferred (NOT reads): §13 Hero Package capacity → Module A (approve/activate ship uncapped, L7); `MembershipFeePaid` listener → Module E (`ActivateProfile` invoked directly, L5); Hold→`suspended`, segments, WaitingList, producer/Filament UI → later slices. The three acceptance cols still have no production setter (deferred registration surface).

## Open Patterns
- **NEW (T2.3): multi-condition-gate Action + message-distinguished rejection paths.** A ≥3-conjunct gate extracts to a `private <gate>Clears(Model): bool` (only `CreateCustomer` has the methods-exactly-`[__construct,handle]` arch assert — others may add helpers); the from-state guard MUST precede the gate. Negative tests pin WHICH guard fired by message substring (`'only from pending'` vs `'onboarding gate'`) — a fully-gated wrong-state fixture proves the from-state guard runs first.
- **NEW (T2.3): dataset-spread PHPStan-max trap (lessons.md).** Never `...$override` a bare-`array` Pest dataset param into a string-keyed literal you pass to `array<string,mixed>` (`Factory::create`) — widens the key to `array-key`, reds `argument.type`. Use scalar dataset params (`string $field` + `mixed $value` dynamic key). Same family as `$this->get()` / arch-array-under-`not` / two-chained-`not`.
- **ACTION-guard narrowing topology (CONFIRMED 4×):** an EVENTED demand-side transition Action trips (1) `SupplyLifecycleChainTest` whitelist (ADD), (2) `ComplianceIndependenceTest` forbidden-list (REMOVE the shipped name; KEEP `LockOriginatingClub`/`SetOriginatingClub`/Account+suspend set forever), (3) iff it writes `originating_club_id`, the OC write-scan (`ActivateCustomer` writes only `status` → no scan change). Event CLASSES trip a different slice.
- **`originating_club_id` reads use loose `toEqual`** (uncast bigint FK — trap 6); event-payload ids stay `toBe` (jsonb ints, trap 3).
- **Backend-green ≠ phase-complete**; the arch-OOM needs `php -d memory_limit=512M vendor/bin/pest` for the full suite (SQLite & PG); focused per-module runs are clean under `php artisan test` at default memory.
