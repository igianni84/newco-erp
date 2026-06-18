---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph iter 5 — `parties-membership-activation` T2.2 DONE).** `ActivateProfile` — the evented Profile activation Action (`approved → active`). Mirrors `ActivateProducer` verbatim: inject recorder+actor → `DB::transaction` → `lockForUpdate` re-read → from-state guard (`state === Approved` else `IllegalProfileTransition::cannotActivate`) → `update(state=Active)` → records a **root** `ProfileActivated` (`{profile_id, state}`, post-transition). Two deferred seams documented in the docblock: `MembershipFeePaid` listener → **Module E** (Action invoked directly now, no contract fabricated — L5); §13 Hero-Package capacity → **Module A** (ships uncapped — L7). No `originating_club_id` write → OC write-scan untouched.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green 817/817** (was 812; +5 from `ProfileActivationTest`) on SQLite; phpstan 0; pint clean. **PG17 (docker `postgres:17`, port 55432): whole Parties set (Feature+Unit) 320/320** + focused file 5/5. `openspec validate parties-membership-activation --strict` valid. `git diff main -- composer.json composer.lock` empty. Both guard tests 11/11.
- Branch `ralph/parties-membership-activation`; T2.2 committed locally next (not pushed — human's call).

## Active Change & Next Task
- **`parties-membership-activation` — 5 / 7 tasks done.** Shipped: T1.1 migration (3 acceptance timestamps); T1.2 the two `Illegal*Transition` exceptions; T1.3 the three activation event classes; T2.1 `ApproveProfile`+`DeclineProfile`+OC one-shot lock; T2.2 `ActivateProfile`+`ProfileActivated` (+ narrowed `SupplyLifecycleChainTest` whitelist [now `ApproveProfile`,`DeclineProfile`,`ActivateProfile`] + `ComplianceIndependenceTest` forbidden-list removal).
- **Next: T2.3** — `ActivateCustomer` Action (`Pending → Active`) → records `CustomerActivated` (root). EVENTED → inject recorder+actor, mirror `ActivateProducer`/`ActivateProfile`; guard `status === CustomerStatus::Pending` else `cannotActivate`; THEN the composite gate (`email_verified_at` ∧ `tc_accepted_at` ∧ `privacy_accepted_at` all set ∧ `sanctions_status === SanctionsStatus::Passed` ∧ (`! kyc_required || kyc_status?->clears() !== false`) — NULL kyc = cleared, DEC-071) else `gateNotMet()`; `update(status=Active)`. **MUST NOT touch the Account** (born active) and **MUST NOT** be auto-fired from `RecordCustomerScreening`/any KYC Action (L6 — keep `ComplianceIndependenceTest` "screening performs no status transition" green). Same two-guard narrowing (whitelist ADD + forbidden-list REMOVE `ActivateCustomer`). **DB-touching → PG17 REQUIRED.** Then T3.1 (full chain + CONTEXT.md + full PG17 close).

## Blockers & Decisions Needed
- None. Deferred seams stay deferred (NOT reads): §13 Hero Package capacity → Module A (approve/activate ship uncapped, L7); `MembershipFeePaid` listener → Module E (`ActivateProfile` invoked directly, L5); Hold→`suspended`, segments, WaitingList, producer/Filament UI → later slices. The three acceptance cols (T1.1) still have no production setter (deferred registration surface).

## Open Patterns
- **NEW (T2.2): deferred cross-module-trigger seam-proof.** An evented Action shipping the within-module *writer* of a transition whose production *trigger* is a deferred other-module event proves the seam by asserting on the event FILE NAME (`glob(Events/*.php)` basename `not->toContain`) + `class_exists(...)->toBeFalse()` on both modules — NEVER `file_get_contents` substring (docblocks mention the seam in prose). A listener can only type-hint an absent class → the absent contract forecloses it.
- **ACTION-guard narrowing topology (CONFIRMED T2.1+T2.2):** a demand-side transition Action trips (1) `SupplyLifecycleChainTest` exact-set whitelist (ADD), (2) `ComplianceIndependenceTest` forbidden-list (REMOVE the shipped name; KEEP `LockOriginatingClub`/`SetOriginatingClub` forever), (3) iff it writes `originating_club_id`, the OC write-scan. Event CLASSES trip a different slice (`glob(Events/*.php)` existence loop).
- **`originating_club_id` reads use loose `toEqual`** (uncast bigint FK — trap 6); event-payload ids stay `toBe` (jsonb ints, trap 3).
- **Backend-green ≠ phase-complete**; the arch-OOM needs `php -d memory_limit=512M vendor/bin/pest` for the full suite (SQLite & PG); focused per-module runs are clean under `php artisan test` at default memory.
