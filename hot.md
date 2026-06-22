---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-kyc-sanctions` — ralph loop RUNNING; task 1.1 done, 1/13).** Group-1 prep: dropped the now-stale `assertActionDoesNotExist('requireKyc')` guard from `CustomerLifecycleConsoleTest` (and fixed the self-contradictory file-header + test-title that named the KYC verb "deliberately ABSENT"), keeping the `submit`/`reject`/`reopen` catalog-governance guards. Test-only; no production code. Unblocks task 2.1 — wiring `requireKyc` would otherwise turn the old guard red.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session): full suite 1442/1442 (7948 assn, exit 0)** — SQLite. PHPStan max 0 errors; `pint --test` clean; `openspec validate --strict` green. (Assn 7950→7948 = the one removed absence assertion.)
- Full suite OOMs at PHP default 128 MB in result *parsing* (not a failure) — run pest with `php -d memory_limit=-1`. PG17 ritual is task 4.2 (not yet run this slice).

## Active Change & Next Task
- **`operator-console-parties-kyc-sanctions` — APPROVED, IN PROGRESS (1/13).** Delta on `operator-console`: 2 ADDED (KYC require/verify/reject; sanctions screening) + 2 MODIFIED Customer reqs; 13 tasks / 5 groups.
- **Next task 1.2:** pin the Filament 5 **page header-action** visibility API vs installed 5.6.7 — `assertActionVisible/Hidden` + mount-and-inspect (`mountAction`, `assertFormFieldExists`, `setActionData`) on `Livewire::test(ViewCustomer::class, ['record'=>$id])`. Do NOT write from memory (arch-from-memory ban); record confirmed helpers in the `ViewCustomer` docblock. Then 1.3 (i18n front-load).
- After all 13: review/merge → semantic-verify (GUIDE §2.7) → `openspec archive`.

## Blockers & Decisions Needed
- **None blocking task 1.2.** No open-ADR gate crossed (operator auth shipped; verbs invoke synchronous domain Actions).
- **Holds push gate STILL pending** (separate change) — origin/main push of the Holds archive+merge commits + `git branch -d ralph/operator-console-parties-holds` await Giovanni's go (classifier-gated). Local merge+archive already done.

## Open Patterns
- **kyc-sanctions landmines (read design.md/tasks.md before coding):** (1) header-action visibility is a NEW pattern — task 1.2 PINS the `->visible()` + `assertActionVisible/Hidden` API vs installed Filament, never from memory; (2) KYC verbs are **event-silent** — assert the coupled `CustomerHoldPlaced/Lifted` + `CustomerSuspended/Reactivated`, NEVER a KYC event (D7); (3) `KycStatus` = **state** enum (cast-value predicate, never imported); `SanctionsStatus`/`ScreeningTriggerSource` = **operand** enums (imported, carve-out — `ModuleBoundariesTest` UNCHANGED); (4) reject = surface-hides + domain-`toThrow` (hidden-action landmine), not `action_failed`; (5) the chain-test asserts exactly 5 events.
- **Surface-extension prep pattern** now in this change's `progress.md ## Codebase Patterns` (relax the prior slice's absence-guard first; fix the stale prose too).
