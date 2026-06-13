---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 2) — `substrate-hardening` 1.2 (C3) DONE, 2/17.** Dead-letter logging + sweep run summary. New `AttemptOutcome` enum (`Delivered`/`Failed`/`Skipped`) — `attempt()` now returns it (`Skipped` = the C1 "sibling already won the row" outcome). `recordFailure()` branches on the builder `->update()` affected-row count: `0` ⇒ `Skipped`, no log; else `Log::warning` (retryable) or `Log::error` (dead-letter transition at `$attempts >= max`), context `{delivery_id, consumer, attempts, error}`. `deliverDue()`: `void` → `array{delivered:int,failed:int}` (a `Skipped` counts as neither); `deliver()` stays `void`. `SweepCommand::handle()` logs `Log::info` `swept=%d failed=%d` (swept = delivered+failed), still SUCCESS. TDD: 3 *Delivery Failure Observability* scenarios via `Log::spy()`, RED→GREEN.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump is task 2.1.)
- **`ralph/substrate-hardening`**: suite **248/248** (245 + 3 new C3 tests) on SQLite AND PostgreSQL 17 (docker) · phpstan 0 @ max · pint clean · `openspec validate substrate-hardening --strict` valid.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 2/17). On branch `ralph/substrate-hardening`.
- **NEXT = task 1.3 — C2 bound the sweep overlap-mutex TTL** (design D4, pure config, engine-agnostic). `routes/console.php:16`: `->withoutOverlapping()` → `->withoutOverlapping(2)` (2-min lease vs 24h default; TTL = `$expiresAt * 60` s). Update the inline comment in `routes/console.php` AND the `SweepCommand` class docblock (`:18-21`) that describe the guard. Update the pinned schedule assertion in `tests/Feature/Platform/SweepTest.php` schedule test (now ~:271, was :206-218 pre-1.2 insert): add `->and($sweep->expiresAt)->toBe(2)` beside `repeatSeconds`/`withoutOverlapping`. Green on both engines.
- Then 2.x config (php 8.5 floor/.env tunables/pgsql tz) · 3.x test gaps (uuidv7, backoff cap, actor_role CHECK, combined immutable UPDATE) · 4.1 CI concurrency · 5.x docs (GUIDE/dev/README/INDEX) · 6.x cross-engine+validate+traceability.

## Blockers & Decisions Needed
- None active. The 1.2 test insert shifted SweepTest's schedule-test line down (~:271) — 1.3 must re-grep, not trust the :206 in the task text.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). C15 will track: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Log-assertion (PHPStan-max-clean, NEW → progress.md Codebase Patterns).** No `Log::fake()` on this framework — use `$log = Log::spy()` then `$log->shouldHaveReceived('warning')` / `->shouldNotHaveReceived('error')`. Match context-not-wording via a closure as the 2nd arg: `shouldHaveReceived('info', fn (string $m, array $c): bool => …)` (returns `self`, asserts ≥1 match). Gotchas: `/** @param array<string,mixed> $c */` docblock on the closure; narrow `mixed` with `is_string()` not `(string)` (trips `cast.string`); spy doesn't throw on un-asserted calls so non-spying FailingConsumer tests still log to `storage/logs` harmlessly.
- **Builder `->update()` returns the affected-row count** — branch on it (`$recorded === 0` ⇒ sibling won; nothing to log).
- **PG verification:** local `docker run postgres:17` (recipe `knowledge/testing/rules.md:9-13`, port 55432) per DB-touching task; five portability traps in rules.md. CI `tests-pgsql` is the standing gate.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
- **Archived patterns:** `openspec/changes/archive/2026-06-13-foundations-money-i18n-flags/progress.md` (12 patterns: VOs, casts, enums, lang/, Pennant, ActorContext, doc-pins).
