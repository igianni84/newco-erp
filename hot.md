---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 16) — `substrate-hardening` 6.1 (Cross-engine green) DONE, 16/17.** The change is proven green on BOTH engines. **SQLite:** pint --test clean · phpstan **0 @ max** · `php artisan test` **254/254 (904 asserts)**. **PostgreSQL 17.10** (local docker, port 55432, `knowledge/testing/rules.md:9-13`): `DB_CONNECTION=pgsql … php artisan test` **254/254 (904 asserts)** → `docker rm -f pg`, tree clean. `openspec validate substrate-hardening --strict` valid. **Verification-only task — zero production/test file changes** (the "test" IS the full suite, run cross-engine). 904===904 parity is the CORRECT state: a pre-existing SQLite-only assertion is balanced by the PG-only `show time zone` read-back from 2.3 (net-zero engine guards, both genuinely executing). Non-vacuity confirmed: PG ran the full 254-test set; the engine-specific tests (ActorRole CHECK reject-half, immutability plpgsql trigger, pgsql timezone) all fired on the PG lane.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant 1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17.
- **`ralph/substrate-hardening`**: suite **254/254 (904 asserts) on SQLite AND PostgreSQL 17.10** (THIS iter — first mandatory full PG run since 3.4) · phpstan 0 @ max · pint --test clean · `openspec validate … --strict` valid. Cross-engine green is locked in.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 16/17). Branch `ralph/substrate-hardening`. Sections 1–5 ALL DONE; 6.1 DONE. Only **6.2** remains.
- **NEXT = 6.2 Spec validation + traceability** (final task). `openspec validate substrate-hardening --strict` (already re-confirmed green THIS iter). Then the scenario→test traceability mapping — pure documentation, no code: *Concurrent Delivery Safety* ×2 → the 1.1 reflection tests (re-invocation guard + resurrection guard in `InlineDeliveryTest.php`); *Delivery Failure Observability* ×3 → the 1.2 `Log::spy` tests in `SweepTest.php` (retryable→warning / dead-letter→error / sweep→summary). Confirm the two durable patterns are recorded at the TOP of progress.md (they ARE: white-box concurrency-guard via reflection; engine-guarded DB-CHECK test). **6.2 likely → `<promise>CHANGE_COMPLETE</promise>`** (then humans review/merge/archive — do NOT archive or merge).

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Plus the 4 registered by 5.4/C15 in decisions/INDEX.md: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Codebase Patterns in `progress.md`** (read first): white-box concurrency-guard via reflection; query-builder UPDATE skips casts; connector-applied session-setting pin; PHPStan-clean `Log::spy()` closure matcher; engine-guarded DB-CHECK test (assert BOTH halves, name not SQLSTATE); string-tested YAML → parse for real + pin STRUCTURE.
- **Cross-engine verification (6.1 recipe):** local `docker run postgres:17` port 55432; readiness via in-PHP PDO connect loop (no sleep/pg_isready — TCP-listening is post-init, so a TCP connect = real server); run SQLite quality cmds first, then `DB_CONNECTION=pgsql … php artisan test`; `docker rm -f pg`. Assertion PARITY across lanes ≠ skipped — net-zero engine guards still execute on both; the proof is the per-test breakdown, not the grand total.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
