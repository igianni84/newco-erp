---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-19
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-19 (ralph — `parties-membership-suspension` task 4.2 GREEN, committed on `ralph/parties-membership-suspension`).** Wired the **Hold→`suspended` coupling, LIFT side** (ADR 2026-06-19) — the mirror of 4.1 — into `LiftHold` + `RecordKycVerified`. After recording `CustomerHoldLifted` (or the system kyc-lift loop) and **in the same transaction**, `match($scope_type)` → a per-scope `restore*IfUncovered` helper that **pre-checks the from-state** lock-free (`?->`, `suspended`/`Suspended`) AND **re-queries coverage under lock** (`->lockForUpdate()->get()` + `count()>0`), invoking the matching `Reactivate*` only when `suspended && ! stillCovered` — `customer ⇒ ReactivateCustomer` (cascade-restore), `account ⇒ ReactivateAccount` (audit-only), `profile ⇒ ReactivateProfile`. The just-lifted Hold is already `Lifted` before the re-query → self-excludes (no special-casing). Three coverage shapes: Customer = active Customer-scope Holds; Account = active Account-scope Holds; Profile = the `(Profile) OR (its Customer)` union. Injected the 3 Reactivate Actions in `LiftHold`, only `ReactivateCustomer` in `RecordKycVerified`. **10 of 11 tasks done.**

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **945/945 green on SQLite** (937 baseline + 8 new `HoldStatusCouplingLiftTest`) via `php -d memory_limit=512M vendor/bin/pest` (artisan test OOMs at 128M). PHPStan 0, Pint clean, `openspec validate parties-membership-suspension --strict` valid, `composer.json/lock` untouched.
- **Verified on PG17** (docker `postgres:17` :55432): `tests/Feature|Unit/Modules/Parties` **448/448** — the nested lift→Reactivate SAVEPOINT commits atomically with the lift; the lift→cascade-restore causation-child `ProfileReactivated` linkage holds; the audit-only Account restore records zero events on PG.

## Active Change & Next Task
- **`parties-membership-suspension` — IN PROGRESS on `ralph/parties-membership-suspension`.** Tasks 1.1–1.3 + 2.1–2.3 + 3.1–3.2 + 4.1–4.2 done (10 of 11).
- **Next: task 5.1 (FINAL)** — Full suspension chain + docs + cross-engine PG17 close. One feature test (`MembershipSuspensionChainTest`) driving the WHOLE slice (suspend→restore, lapse→renew grace + lapse→cancel past-grace, cancel+deactivate, the Customer cascade, the Account FSM, the Hold-driven place→suspend / lift→restore with a residual Profile Hold keeping it `Suspended`). Assert `DomainEvent::pluck('name')` `toEqualCanonicalizing([the 8 status events + Hold/spine events])` with **0** `ProfileLapsed`/`ProfileCancelled`/`AccountSuspended`/`AccountClosed`/`WaitingListJoined`/`CustomerSegmentChanged`, and a cascade `ProfileSuspended`'s `causation_id` = the `CustomerSuspended` id. **Docs:** extend `CONTEXT.md` (coverage-recompute coupling; `ProfileExpired`=lapse/no-`ProfileLapsed`; audit-only cancel+Account+no `ActivateAccount`; the deferred seams) + the 8 event payloads + `§` spec anchors. Then **entire** Parties suite + arch tests on PG17; audit all guard files green. After 5.1 → `<promise>CHANGE_COMPLETE</promise>`.

## Blockers & Decisions Needed
- None. Reviewer items remain resolved (design.md/ADR): coupling = coverage-recompute; `CloseCustomer` no cascade; cascade `ProfileSuspended` = causation child.
- Still open (human's call): **push `main` → `origin`** + delete merged `ralph/parties-membership-activation` branch.

## Open Patterns
- **Hold→status coupling on LIFT** (4.2) = the mirror of PLACE: inject the Reactivate Actions; after the lift, pre-check from-state lock-free + re-query coverage under lock; the just-lifted Hold self-excludes; restore iff `suspended && ! stillCovered`. `ReactivateCustomer` does its OWN per-Profile cascade coverage check, so `LiftHold` only checks Customer-level coverage before invoking it. **DRY seam:** `customerStillCovered`/`restoreCustomerIfUncovered` duplicated in `LiftHold`+`RecordKycVerified`; `profileStillCovered` ≡ `ReactivateCustomer::stillCovered` — a shared `HoldCoverage` reader is a clean future refactor (deferred — mid-slice extraction risks the `Actions/*.php`-globbing arch guards).
- **Both 4.x coupling tasks inverted ZERO guard tests** (verified empirically). The from-state pre-check keeps every birth-state place/lift inert — existing guards place/lift Holds on `pending` Customers (never suspended) or an Account whose Hold is never lifted. `grep`+RUN, don't pre-amend a green guard.
