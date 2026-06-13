---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (interactive close) — `substrate-hardening` MERGED + ARCHIVED. `main@ecead30`, in sync with origin, tree clean.** Full GUIDE §2.7 ritual executed end-to-end: re-verified SQLite (pint/phpstan-max/`254-254-904`) + local PostgreSQL 17.10 (`254-254-904`); `git merge --no-ff` (`0e2f3a5`) + push; **semantic verify CLEAN** (independent subagent that spun up its OWN pg17 container — 0 CRITICAL, 3 non-blocking SUGGESTIONs); `openspec archive` (`ecead30`) merged the 2 ADDED requirements (Concurrent Delivery Safety, Delivery Failure Observability) into `openspec/specs/event-substrate/spec.md`; branch `ralph/substrate-hardening` deleted. **NB:** the ralph loop had exited early at 16/17 (iter 16 did 6.1, never ran 6.2); 6.2 (pure-doc traceability) was completed interactively before the close — the ralph.sh "Next steps (human)" footer prints on EVERY exit and is NOT a completion signal.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant 1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17.
- **`main@ecead30`**: suite **254/254 (904 asserts) on SQLite AND PostgreSQL 17.10** · phpstan 0 @ max · pint --test clean. Event substrate hardened (C1–C15); CI two-lane (quality SQLite + tests-pgsql PG17) + workflow-level concurrency cancel-in-progress.

## Active Change & Next Task
- **No active change.** `openspec list` → "No active changes found." substrate-hardening fully closed.
- **NEXT = author the next change** via `/spec-to-change` against `spec/05-release/Build_Workplan_v0.3-MVP.md`. F1 foundations complete (3/3) + substrate hardened. Pick the next Build Workplan slice with Giovanni; never two loops in parallel.

## Semantic-verify SUGGESTIONs (non-blocking; capture in knowledge/ or a future change if revisited)
- `InlineDeliveryExecutor.php` ~:175 — `$delivery->refresh()` before `recordFailure()` isn't null-safe IF a delivery row were ever deleted; safe today (substrate never deletes deliveries). Defensive-only.
- `SweepTest.php` ~:120 — backoff-cap test doesn't hit the exact `base*2^(n-1) == cap` equality boundary (`min()` handles it; theoretical).
- `InlineDeliveryExecutor.php` ~:258 — backoff exponent `2**(attempts-1)` confirmed correct (no off-by-one); note only.

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Plus 4 in decisions/INDEX.md: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Codebase Patterns** now live in `openspec/changes/archive/2026-06-13-substrate-hardening/progress.md` (6 patterns: white-box concurrency-guard via reflection; query-builder UPDATE skips casts; connector-applied session-setting pin; PHPStan-clean `Log::spy()` closure matcher; engine-guarded DB-CHECK test; string-tested YAML → parse for real + pin STRUCTURE).
- **Closing ritual:** `openspec list` + unchecked-task count are the truth, NOT the ralph.sh footer. Pause before main if the loop didn't finish all tasks. Cross-engine verify: local `docker run postgres:17` p55432, readiness via in-PHP PDO TCP loop (no sleep/pg_isready), `DB_CONNECTION=pgsql … php artisan test`, `docker rm -f pg`.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
