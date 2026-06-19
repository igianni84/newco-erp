---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-19
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-19 (human close-out — `parties-membership-suspension` MERGED to main + ARCHIVED).** Ran the GUIDE §2.7 ritual interactively. Health-check green (949/949 SQLite, PHPStan 0, Pint clean, composer untouched, exactly 1 additive migration, 11/11 tasks). Re-ran the **PG17 pre-merge gate** (docker `postgres:17` :55432, `php -d memory_limit=512M vendor/bin/pest`) — full suite **949/949** (97.8s). Merged `--no-ff` into `main` (`ff0be4a`). **Semantic-verify (§2.7)** via 4 parallel subagents over all 9 delta requirements × 3 axes (completeness/correctness/coherence): **CLEAN — 0 CRITICAL, 0 WARNING, 3 non-blocking SUGGESTIONs**. `openspec archive` merged **+7 ADDED / ~2 MODIFIED** into the living `openspec/specs/party-registry/spec.md`; change now under `openspec/changes/archive/2026-06-19-parties-membership-suspension`.

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **949/949 green on SQLite + PG17.** PHPStan 0, Pint clean, `composer.json/lock` untouched.
- Living `party-registry` spec now carries the **full demand-side status lifecycle** of Module K: Customer suspend/restore/close (cascade), Profile suspend/lapse-grace/cancel/deactivate, Account FSM (audit-only), and the **Hold-driven status coupling** (place ⇒ suspend, lift ⇒ restore-iff-uncovered).

## Active Change & Next Task
- **No active change.** `openspec list` empty. The whole demand-side status lifecycle of Module K is shipped + archived.
- **Next:** pick the next change via `/spec-to-change`. Deferred Module-K seams the spec keeps open: **`parties-hero-package`** (Hero cap + `Applied → WaitingList`/`WaitingListJoined`, after Module A), **`parties-customer-segments`** (+`CustomerSegmentChanged`), **`parties-anonymisation`**; cross-module consumers — Module E `MembershipFeePaid` listener (drives `RenewProfile`), Module S AC-K-EVT-14 cancellation signal + Club-Credit conversion/freeze.

## Blockers & Decisions Needed
- **None.** `main` pushed to `origin/main` (in sync). Merged `ralph/*` branches deleted — only `main` remains (local + remote); the `ralph/parties-membership-activation` the prior cache flagged was already gone.

## Open Patterns
- All prior chain-test + coupling patterns hold (Hold→status on PLACE/LIFT, cascade-causation-child, coverage-recompute-under-lock; the `Membership*ChainTest` exact-multiset template).
- **New (benign) note from semantic-verify:** `PlaceHold` from-state pre-check reads the scope with `->first()` (no lock) before the invoked `Suspend*` re-reads under `lockForUpdate`. Under real concurrency (post queue-ADR) a race yields a **clean rollback** (no partial state, no wrong status) — not a defect today (single-threaded inline substrate); revisit when the queue/concurrency ADR lands.
