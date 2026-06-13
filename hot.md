---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (interactive close) — `substrate-hardening` 6.2 DONE → 17/17, `CHANGE_COMPLETE`.** The ralph loop EXITED one iteration early (iter 16 did 6.1; it never ran iter 17). Verified state, then completed 6.2 manually (RALPH discipline, one task): `openspec validate substrate-hardening --strict` valid; `openspec list` → **✓ Complete**; all 5 delta scenarios map to real named passing tests (Concurrent Delivery Safety ×2 → `InlineDeliveryTest.php:170,189`; Delivery Failure Observability ×3 → `SweepTest.php:241,271,295`); the 2 required patterns confirmed at top of progress.md. 6.2 touched only `tasks.md`+`progress.md` (read by no test) → suite unaffected. Committed on branch.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant 1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17.
- **`ralph/substrate-hardening`** (17/17): suite **254/254 (904 asserts) on SQLite AND PostgreSQL 17.10** (proven by 6.1; no code touched since — 6.2 is doc-only) · phpstan 0 @ max · pint --test clean · `openspec validate … --strict` valid. Cross-engine green locked in.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** — 17/17, `✓ Complete`, validate valid. Branch `ralph/substrate-hardening`, 17 commits ahead of `main`, tree clean.
- **NEXT = closing ritual (GUIDE §2.7), human-delegated.** Awaiting user GO to MERGE to main. Sequence once confirmed: (1) review `git log/diff main..ralph/substrate-hardening`; (2) local PostgreSQL 17 verify (6.1 already proved it; re-run is the §2.7 gate — 6.2 added no code/DB surface); (3) `git checkout main && git merge --no-ff` + `git push` + delete branch; (4) **semantic verify** (GUIDE §2.7 prompt) — CRITICAL findings → fix as new tasks, don't archive; clean → (5) `openspec archive substrate-hardening --yes` + commit + push. **Do NOT merge/archive without the GO** (loop exited early — pause-before-main rule).

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Plus the 4 registered by 5.4/C15 in decisions/INDEX.md: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Codebase Patterns in `progress.md`** (read first): white-box concurrency-guard via reflection; query-builder UPDATE skips casts; connector-applied session-setting pin; PHPStan-clean `Log::spy()` closure matcher; engine-guarded DB-CHECK test (assert BOTH halves, name not SQLSTATE); string-tested YAML → parse for real + pin STRUCTURE.
- **Cross-engine verification (6.1 recipe):** local `docker run postgres:17` port 55432; readiness via in-PHP PDO connect loop (no sleep/pg_isready — TCP-listening is post-init, so a TCP connect = real server); run SQLite quality cmds first, then `DB_CONNECTION=pgsql … php artisan test`; `docker rm -f pg`. Assertion PARITY across lanes ≠ skipped — net-zero engine guards still execute on both; the proof is the per-test breakdown, not the grand total.
- **Closing-ritual delegation:** Giovanni delegates the GUIDE §2.7 close to Claude; verify-first, confirm the loop finished ALL tasks, pause before main if anything's off. The ralph.sh "Next steps (human)" footer prints on EVERY exit — it is not a completion signal; check `openspec list` + unchecked-task count.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
