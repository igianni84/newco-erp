---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 1) — `substrate-hardening` 1.1 (C1) DONE, 1/17.** Closed the inline-vs-sweep delivery race in `InlineDeliveryExecutor`: `attempt()` re-reads the row under `->lockForUpdate()->first()` inside its txn and returns early (no handler) if the row is gone or not `pending`, operating on the locked row for the handler + `done` flip; `recordFailure()` became a conditional builder update guarded on `where('status','pending')` so a sibling-completed `done` row is never resurrected. TDD: 2 engine-agnostic reflection guards in `InlineDeliveryTest.php` (`inlineInvokePrivate()`), confirmed RED→GREEN. Both delta-spec *Concurrent Delivery Safety* scenarios now covered; verified on BOTH engines via local docker PG.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump is task 2.1.)
- **`ralph/substrate-hardening`**: suite **245/245** (243 + 2 new C1 tests) on SQLite AND PostgreSQL 17 (docker) · phpstan 0 @ max · pint clean · `openspec validate substrate-hardening --strict` valid.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 1/17). On branch `ralph/substrate-hardening`.
- **NEXT = task 1.2 — C3 dead-letter logging + sweep run summary** (TDD, design D3). RED with `Log::fake()`/spy (assert channel+level, not wording): `recordFailure()` logs `Log::warning` for still-retryable, `Log::error` on transition to `failed`; `deliverDue()` returns `array{delivered:int,failed:int}` tallied per `attempt()` (`deliver()` stays void); `SweepCommand::handle()` logs `Log::info` summary, still returns SUCCESS.
  - **Heads-up from 1.1:** `attempt()` now has a THIRD outcome — the early-return *skip* (a sibling already won the row) — neither delivered nor failed; the 1.2 tally must not count a skip as delivered.
- Then 1.3 (C2 mutex TTL) · 2.x config (php 8.5/.env/pgsql tz) · 3.x test gaps · 4.x CI · 5.x docs · 6.x cross-engine+validate.

## Blockers & Decisions Needed
- None active. C1 guards are white-box (private `attempt()`/`recordFailure()`); engine-agnostic, PG is the true-contention proof (`lockForUpdate()` real on PG, no-op on single-writer SQLite).
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). C15 will track: secrets · observability · PCI boundary · security review.

## Open Patterns
- **NEW (this change → progress.md Codebase Patterns):** (1) white-box concurrency-guard test via reflection (`inlineInvokePrivate()`) — construct the post-race state, invoke the private guard, make the discriminating assertion load-bearing (`$handled` / terminal status, not attempts). (2) query-builder `->update()` runs NO model casts: enum→`->value`, Carbon formatted by the grammar = same `Y-m-d H:i:s` the cast writes → use for conditional `where(...)->update(...)`.
- **PG verification:** local `docker run postgres:17` (recipe `knowledge/testing/rules.md:9-13`, port 55432) now used per-task for DB-touching work; CI `tests-pgsql` lane is the standing gate. Five portability traps in rules.md.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
- **Archived patterns:** `openspec/changes/archive/2026-06-13-foundations-money-i18n-flags/progress.md` (12 patterns: VOs, casts, enums, lang/, Pennant, ActorContext, doc-pins).
