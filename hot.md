---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 13) — `substrate-hardening` 5.2 (C13) DONE, 13/17.** Section 5 (docs) now 2/4. Corrected `docs/development.md` on three fronts (design D10): (a) **env vars (~:93-95)** — added a `RALPH_MODEL` bullet (`default: claude-opus-4-8[1m]`) and rewrote the stale `CLAUDE_FLAGS` "no model option of its own" sentence (falsified by `ralph.sh:204` pinning `--model "${RALPH_MODEL:-claude-opus-4-8[1m]}" --effort "${RALPH_EFFORT:-max}"`); new wording: CLAUDE_FLAGS appends AFTER, later flag wins, still overrides the model. `RALPH_EFFORT` token preserved (DevelopmentDocsTest pins it). (b) **:114** — "nothing under `.claude/`" was false → "no MCP server (`.mcp.json` absent), but `.claude/` carries the loop's hooks, skills, and team memory" (verified on disk). (c) **PHP floor `8.4 → 8.5`** at :11, :76 (both mentions), :122 constraint column; `8.5.2` snapshot untouched. **No new test** (docs-only; 2.2/5.1 precedent — verify by suite-green + the file's doc-reader test).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` `php ^8.5` ✓.)
- **`ralph/substrate-hardening`**: suite **254/254 (904 asserts) on SQLite** (UNCHANGED — 5.2 is docs-only, zero code/DB surface) · `DevelopmentDocsTest` 6/6 (31 asserts — RALPH_EFFORT + locked versions intact) · phpstan 0 @ max · pint --test clean · `openspec validate … --strict` valid. **No PG run for 5.2** (docs-only); last full PG parity was 3.4 (253/253, 899===899). Task 6.1 is the cross-engine sweep; the standing `tests-pgsql` CI lane covers PG.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 13/17). Branch `ralph/substrate-hardening`. Sections 1 (3/3), 2 (3/3), 3 (4/4), 4 (1/1) DONE; Section 5 docs 2/4.
- **NEXT = task 5.3 — C14 `README.md` exit codes + semantic-verify** (design D10). (a) The ralph exit-codes line (**verify current line — was `:68`, NOT the stale "~19-21"**): add `2` (preflight error) and `5` (integrity violation) to match `docs/development.md:104`. (b) The ASCII-flow line (~:28): replace the non-existent `/opsx:verify` with the semantic-verify reference (GUIDE §2.7), aligning with `:53` (already correct); soften the skills-table "verify" mention (~:94) if needed. **No test pins these strings** — verify by suite-green + a read of the touched lines. Verify file:line first (line numbers may have shifted).
- Then 5.4 (decisions/INDEX 4 untracked gates: secrets · observability · PCI boundary · security review) · 6.1 cross-engine sweep + 6.2 validate + traceability.

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Task 5.4/C15 registers 4 more in decisions/INDEX.md: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Codebase Patterns in `progress.md`** (read first): white-box concurrency-guard via reflection; query-builder UPDATE skips casts; connector-applied session-setting pin; PHPStan-clean `Log::spy()` (closure 2nd-arg matcher); engine-guarded DB-CHECK test (assert BOTH halves, name not SQLSTATE); string-tested workflow YAML → parse for real + pin STRUCTURE.
- **Docs-task test policy (2.2 + 5.1 + 5.2):** non-code doc/template files → verify by "suite stays green" + a targeted run of the file's existing doc-reader test (`DevelopmentDocsTest` for development.md, `FoundationsDocsTest` for GUIDE.md), NOT a new pin. Grep the test's pinned tokens BEFORE running the suite so a dropped pin surfaces immediately. 5.3 (README) has NO doc-reader test → suite-green + a read of the touched lines.
- **Doc-pin map:** `DevelopmentDocsTest` (RALPH_EFFORT + locked versions + 5 Quality Commands + llms.txt/boost:install — NOT the PHP-floor string), `FoundationsDocsTest` (GUIDE §4 F1 status line — NOT §2.7/§8), `CiWorkflowTest` (php 8.5 + gate order + concurrency), `PlatformRequirementsTest` (≥8.5 runtime, not doc copy).
- **PG verification (DB tasks):** local `docker run postgres:17` port 55432 (recipe + five traps `knowledge/testing/rules.md:9-25`); readiness via in-PHP PDO connect loop. CI `tests-pgsql` is the standing gate.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
