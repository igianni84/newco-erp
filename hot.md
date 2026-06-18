---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph iter 4 — `parties-membership-activation` T2.1 DONE).** The two demand-side Profile transition Actions (the one retained producer write — L-PP) + the Originating-Club one-shot lock. `ApproveProfile` (`applied → approved`, mirrors `ActivateProducer`): from-state guard → then re-reads the **Customer** `lockForUpdate` and **iff `originating_club_id === null`** sets it to the approving Profile's `club_id` + records a **root** `OriginatingClubLocked` (the approve path's ONLY event — idempotent + immutable). `DeclineProfile` (`applied → rejected`, terminal) is **event-silent** — mirrors `RecordKycRejected` (NO recorder/actor injection). Both audit-only on the Profile (§ 15.2 names no `ProfileApproved`/`ProfileRejected` — L2). First DB-touching Action task → PG17 verified.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green 812/812** (was 803; +9 from `ProfileMembershipApprovalTest`) on SQLite; phpstan 0; pint clean. **PG17 (docker `postgres:17`): whole Parties set 315/315** + focused file 9/9. `openspec validate parties-membership-activation --strict` valid. `git diff main -- composer.json composer.lock` empty.
- Branch `ralph/parties-membership-activation`; T2.1 committed locally next (not pushed — human's call).

## Active Change & Next Task
- **`parties-membership-activation` — 4 / 7 tasks done.** Shipped: T1.1 migration (3 acceptance timestamps); T1.2 the two `Illegal*Transition` exceptions; T1.3 the three activation event classes; T2.1 `ApproveProfile`+`DeclineProfile`+OC lock (+ narrowed `SupplyLifecycleChainTest` whitelist `$demandSideTransitions=[ApproveProfile,DeclineProfile]` and `ComplianceIndependenceTest` forbidden-list + OC write-scan).
- **Next: T2.2** — `ActivateProfile` Action (`Approved → Active`) → records `ProfileActivated` (root). EVENTED → inject recorder+actor, mirror `ActivateProducer`; guard `state === Approved` else `cannotActivate`. `MembershipFeePaid` listener = deferred Module-E seam (invoke directly); §13 cap = Module-A seam. **DB-touching → PG17 REQUIRED.** Narrow the SAME two guard files: ADD `ActivateProfile` to `$demandSideTransitions` (SupplyLifecycleChainTest ~L323) + REMOVE from `ComplianceIndependenceTest` forbidden-list (~L150-156). No OC write → no write-scan change. Then T2.3 (`ActivateCustomer` + composite gate, PG17), T3.1 (chain + CONTEXT.md + full PG17 close).

## Blockers & Decisions Needed
- None. Deferred seams stay deferred (NOT reads): §13 Hero Package capacity → Module A (approve/activate ship uncapped, L7); `MembershipFeePaid` listener → Module E (`ActivateProfile` invoked directly, L5); Hold→`suspended`, segments, WaitingList, producer/Filament UI → later slices. The three acceptance cols (T1.1) still have no production setter (deferred registration surface).

## Open Patterns
- **Event-silent transition Action injects NO recorder** (mirror `RecordKycRejected`); EVENTED one injects recorder+actor (mirror `ActivateProducer`). Decide by "does it record an event?", not surface symmetry.
- **ACTION-guard narrowing topology (CONFIRMED T2.1):** a demand-side transition Action trips (1) `SupplyLifecycleChainTest` exact-set whitelist, (2) `ComplianceIndependenceTest` forbidden-list (remove the shipped name, KEEP `LockOriginatingClub`/`SetOriginatingClub` forever), (3) iff it writes `originating_club_id`, the OC write-scan (add `elseif` admitting 1 set-write, never `=> null`). Event CLASSES trip a different slice (`glob(Events/*.php)`). Keep the Action docblock free of the literal `'originating_club_id' =>` token.
- **`originating_club_id` reads use loose `toEqual`** (uncast bigint FK — numeric string on PG, int on SQLite, trap 6); event-payload ids stay `toBe` (jsonb-decoded ints).
- **Backend-green ≠ phase-complete**; the arch-OOM needs `php -d memory_limit=512M vendor/bin/pest` for the full suite (SQLite & PG); focused per-module runs are clean under `php artisan test` at default memory.
