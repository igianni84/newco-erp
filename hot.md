---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph iter 3 — `parties-membership-activation` T1.3 DONE).** The three `final` demand-side activation event classes — `CustomerActivated` `{customer_id, status}`, `ProfileActivated` `{profile_id, state}`, `OriginatingClubLocked` `{customer_id, club_id, profile_id, locked_at}` (§ 6.1 verbatim) — each untyped `const NAME` (verbatim § 15) + `const ENTITY_TYPE` + static `payload()`, mirroring `ProducerActivated`. All ROOT, PII-free. `OriginatingClubLocked.club_id` = triggering Profile's club; `locked_at` = `CarbonImmutable::now()->toIso8601String()`. Plus the first guard-edit of this change. Pure PHP, no DB → no PG run.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green 803/803** (was 797; +6 from `ActivationEventsTest`) on SQLite; phpstan 0; pint clean. `openspec validate parties-membership-activation --strict` valid. `git diff main -- composer.json composer.lock` empty.
- Branch `ralph/parties-membership-activation`; T1.3 committed locally next (not pushed — human's call). No PG run this task (no DB touched).

## Active Change & Next Task
- **`parties-membership-activation` — 3 / 7 tasks done.** Shipped: T1.1 migration (3 acceptance timestamps); T1.2 the two `Illegal*Transition` exceptions + 5 lang keys; T1.3 the three activation event classes + narrowed `SupplyLifecycleChainTest`'s event-class-existence loop (`glob(Events/*.php)` ~L328 — removed the 3 shipped, KEPT `AccountActivated`/`ProfileApproved`/`CustomerSegmentChanged`).
- **Next: T2.1** — `ApproveProfile` + `DeclineProfile` Actions (`Applied → Approved|Rejected`, from-state guarded, audit-only — NO `ProfileApproved` event) + the in-tx Originating-Club one-shot lock (first-ever approval → set `Customer.originating_club_id`, record `OriginatingClubLocked`, root). Mirror `ActivateProducer`; cap gate = documented Module-A seam (L7). **FIRST DB-touching Action task → PG17 REQUIRED.** **First ACTION-guard narrowing:** add `ApproveProfile`+`DeclineProfile` to `SupplyLifecycleChainTest`'s transition-whitelist (~L322-323) + remove from `ComplianceIndependenceTest`'s forbidden-list (~L149-156, KEEP `LockOriginatingClub`/`SetOriginatingClub`) + admit `ApproveProfile`'s conditional write in its `originating_club_id` write-scan (~L168-177). Grep-derive blast radius first. Then T2.2/T2.3 (Actions, PG17), T3.1 (chain + CONTEXT.md docs + full PG17 close).

## Blockers & Decisions Needed
- None. Deferred seams stay deferred (NOT reads): §13 Hero Package capacity → Module A (Approve/Activate ship uncapped, L7); `MembershipFeePaid` listener → Module E (`ActivateProfile` invoked directly, L5); Hold→`suspended`, segments, WaitingList, producer/Filament UI → later slices. The three acceptance cols (T1.1) have no production setter yet (deferred registration surface).

## Open Patterns
- **Guard topology (grep-verified):** event CLASSES trip only `SupplyLifecycleChainTest`'s `glob(Events/*.php)` existence loop; the other six guard files use runtime `count()===0` (green). ACTIONS (T2.x) trip the whitelist + `ComplianceIndependenceTest` forbidden-list + OC write-scan — narrow each in the task that ships the Action.
- **Event-payload moment idiom** (progress.md): a payload `*_at` with no column → `CarbonImmutable::now()->toIso8601String()`; test by freezing `setTestNow($m)` + `afterEach` reset; pin a same-typed source by setting the OTHER to a different fixture value.
- **Backend-green ≠ phase-complete**; the arch-OOM needs `php -d memory_limit=512M vendor/bin/pest` for the full suite (SQLite & PG).
