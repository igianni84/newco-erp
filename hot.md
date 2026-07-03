---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — RM-02 (`parties-enhanced-kyc-threshold`) CLOSE RITUAL DONE (GUIDE §2.7) ✅.** Reviewed → pre-merge gates green → **merged `--no-ff` to `main` (`eb05b84`)** → **semantic-verify via 3 parallel subagents: CLEAN, 0 CRITICAL / 0 WARNING** across all 4 requirements (Enhanced-KYC Threshold Detection, Compliance Review Queue, + the 2 MODIFIED lifecycle reqs; only non-blocking SUGGESTIONs) → **`openspec archive`d as `2026-07-03-parties-enhanced-kyc-threshold`** (party-registry **42→44** requirements: +2 added, ~2 modified). Local `archive:` commit staged with `log.md`+`hot.md`. **⚠ NOT pushed to origin** — push gate: ask Giovanni first.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Pre-merge confirmed green: full suite 1947/1947 on SQLite** (10459 assertions), PHPStan max **0**, Pint clean. Loop already recorded cross-engine **1947/1947 on PostgreSQL 17** at task 7.1 (the change-complete gate); NOT re-run in the close ritual (Giovanni's condensed §2.7 omits it).
- ⚠ **Full suite = `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child ignoring `-d` (128M fatal at result-collection in the Filament panel tests, NOT a regression). Filtered/by-path runs fit 128M.
- ⚠ **Local PG cross-engine recipe:** `docker run -d --name pg --tmpfs /var/lib/postgresql/data:rw --shm-size=256m … postgres:17` (a default container fills the Docker VM disk → `pg_wal` PANIC); run the FULL suite only via the 2G pest cmd; `docker rm -f pg` after.
- `main` is 14 commits ahead of `origin/main` (13 branch commits + the merge) + 1 pending local `archive:` commit.

## Active Change & Next Task
- **No active OpenSpec change** (`openspec list` empty). RM-02 is shipped + archived.
- **⭐ NEXT: (1) push `main` to origin** once Giovanni OKs (merge `eb05b84` + the `archive:` commit); optionally `git branch -d ralph/parties-enhanced-kyc-threshold`. **(2)** the next Ralph change (RM-03+ on the Remediation_Tracker) is prepared by a human via `/spec-to-change`.
- Knowledge-promotion confirmation date for anything learned here = the archive-dir date **2026-07-03**.

## Blockers & Decisions Needed
- **Push to `origin/main` pending Giovanni's OK** (close-ritual push gate) — the only open item.
- **Durable design landmines (shipped, do NOT "fix"):** (D2) resolving the AML `under_review` from the console re-tags `trigger_source=compliance_ad_hoc` (§9.5 — console never offers `aml_threshold`); the AML origin stays durable on the review row + event. The sanctions clear leaves `enhanced_kyc_flag=true` + review `resolved_at=NULL` (resolve action deferred, §9.1).

## Open Patterns
- **Semantic-verify SUGGESTIONs (non-blocking, candidates for a future change / knowledge note):** (a) `ScanEnhancedKycThresholds` iterates in a bare `foreach` — one per-Customer throw aborts the whole daily run (unreachable today: Customers never hard-deleted, adapters EUR-by-contract; fail-loud may be intentional for a compliance scan — a per-Customer `try/catch`+log would harden it). (b) console amount display uses `number_format($minor/100, 2)` (EUR-only, byte-identical to the `ClubResource` idiom) — revisit repo-wide only if a 0-/3-decimal currency (JPY) reaches a display surface.
- **Deferred Module-S seams still open:** the real `CustomerTransactionTotalsReader` adapter + the at-order-completion trigger land with Module S (Commerce, Phase 4). The 12-month re-screen cadence job + the review-queue resolve action remain separate deferred changes.
- **Closing integration test = drive the chain through the REAL Actions, assert the emergent event-SET** (`DomainEvent::query()->distinct()->pluck('name')->toEqualCanonicalizing([...])`) — a `knowledge/testing` rule.
