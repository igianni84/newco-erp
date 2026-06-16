---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (interactive — GUIDE §2.7 close of `parties-producer-lifecycle` — FULLY FINALIZED).** Ran the full closing ritual: independent re-verify GREEN on BOTH engines, merged `ralph/parties-producer-lifecycle` → `main` `--no-ff` (`d5dab8b`), semantic-verify CLEAN (3 parallel audit agents, §2.7 rubric), archived (`3f6ae08`) → truth spec `party-registry` absorbed **+4 ADDED / ~1 MODIFIED** requirements. **`main` pushed to `origin/main` (human-OK'd) and the merged branch `ralph/parties-producer-lifecycle` deleted. Repo synced; clean slate.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 475/475 on SQLite AND on PostgreSQL 17** (1952 assertions), **phpstan max 0**, **pint --test clean**, truth spec `openspec validate party-registry --strict` valid. No migration / no composer drift in the merged change.

## Active Change & Next Task
- **No active change.** `parties-producer-lifecycle` merged, archived, and pushed to origin. Working tree clean; `main` == `origin/main`.
- **Next change deferred** — Giovanni decides later (chose "stop" at close). When resumed, pick one via `/spec-to-change`: `catalog-lifecycle-approval` (Module 0 now consumes the emitted `ProducerActivated`/`ProducerRetired` to gate Product Master activation, AC-K-XM-2 — now unblocked); OR the **demand-side** Parties slice (Customer/Account/Profile FSMs, Originating-Club lock, Hero capacity, segment view) — mirror this change's shape.

## Blockers & Decisions Needed
- **None.** Close fully finalized (merged + archived + pushed + branch cleaned).
- Two **non-blocking** semantic-verify SUGGESTIONs to fold into a future change (not defects): (1) stale docblock `app/Modules/Parties/Enums/ProducerAgreementStatus.php:16-17` still says supersession is "deferred to parties-membership-lifecycle" though this change now implements it; (2) no test forcing a mid-cascade `SunsetClub` failure to exercise partial-cascade rollback (correct by construction via nested `DB::transaction`).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin; misleading `NoTestCaseObjectOnCallStack` trace). PHPStan/Pint fine at default mem.
- **PG17 gate** (DB tasks / close): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; bounded `docker exec pg pg_isready -q` loop with in-container `docker exec pg sleep 1` (NO host sleep — blocked); env prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 …`; `docker rm -f pg`. Traps in `knowledge/testing/rules.md`.
- **GUIDE §2.7 close** = verify both engines → merge `--no-ff` → semantic-verify (delegate to parallel audit agents) → `openspec archive --yes` + commit → push (human-gated). log.md via `memlog.sh`; hot.md ≤550 words; APPROVED = human-only; **never `git push` without explicit human OK.**
