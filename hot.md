---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 15) — `substrate-hardening` 5.4 (C15) DONE, 15/17.** Section 5 docs COMPLETE (4/4). `decisions/INDEX.md` `## Open decisions` split into two labeled groups (insertion-only, 9+/0−): **Stack gates** = the existing paragraph preserved verbatim, now labeled as the mirror of CLAUDE.md's protected table; **Operational / security gates** = a NEW bulleted group registering the four untracked C15 gates, each a one-line `gate = …`: **secrets management** · **observability** (notes the C3 log channel is a deliberate placeholder pending this gate) · **PCI boundary** (Module S/E; cardholder-data + SAQ scope) · **architectural security review**. Group intro encodes the design rationale (CLAUDE.md table protected + stack-scoped → INDEX.md is their home). **No test** — grep confirmed nothing in `tests/` reads INDEX.md or the gate tokens (docs-only → suite-green + a read).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant 1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17.
- **`ralph/substrate-hardening`**: suite **254/254 (904 asserts) SQLite** (UNCHANGED — 5.4 docs-only, zero code/DB surface) · phpstan 0 @ max · pint --test clean · `git diff decisions/INDEX.md` exactly 9+/0− · `openspec validate … --strict` valid. **No PG run for 5.4** (docs-only); last full PG parity was 3.4 (899===899). Task 6.1 is the change's first MANDATORY PG run since then; the standing `tests-pgsql` CI lane covers PG meanwhile.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 15/17). Branch `ralph/substrate-hardening`. Sections 1–5 ALL DONE; only Section 6 (final verification) remains.
- **NEXT = 6.1 Cross-engine green.** SQLite cmds all confirmed green THIS iter (pint --test / phpstan 0 @ max / 254 tests). Then the **PostgreSQL 17 lane locally**: `docker run … postgres:17` (port 55432; recipe + 5 traps `knowledge/testing/rules.md:9-25`; readiness via in-PHP PDO connect loop) + `DB_CONNECTION=pgsql … php artisan test` green; `docker rm -f pg`. First mandatory full PG run since 3.4 — expect 254 tests; engine-guarded tests (ActorRole CHECK, immutability triggers, pgsql timezone) shift their per-lane assert split, total parity holds.
- Then **6.2 spec validation + traceability**: `openspec validate --strict` (green) + map delta scenarios → tests (*Concurrent Delivery Safety* ×2 → 1.1 reflection tests; *Delivery Failure Observability* ×3 → 1.2 `Log::spy` tests); patterns already in progress.md. 6.2 likely → `<promise>CHANGE_COMPLETE</promise>`.

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). 5.4/C15 just registered 4 MORE in decisions/INDEX.md: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Codebase Patterns in `progress.md`** (read first): white-box concurrency-guard via reflection; query-builder UPDATE skips casts; connector-applied session-setting pin; PHPStan-clean `Log::spy()` closure matcher; engine-guarded DB-CHECK test (assert BOTH halves, name not SQLSTATE); string-tested YAML → parse for real + pin STRUCTURE.
- **Docs-task policy (2.2/5.1–5.4):** non-code docs → suite-green + the file's doc-reader test; INDEX.md & README have NONE → suite-green + a read of touched lines. Grep candidate tokens in `tests/` BEFORE the suite; verify refs vs the AUTHORITATIVE source; insertion-only regroup (`git diff --stat` 9+/0−) when extending a section.
- **PG verification (6.1):** local `docker run postgres:17` port 55432 (recipe + five traps `knowledge/testing/rules.md:9-25`); readiness via in-PHP PDO connect loop. CI `tests-pgsql` is the standing gate.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
