---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 12) — `substrate-hardening` 5.1 (C12) DONE, 12/17.** Section 5 (docs) opens 1/4. Inserted a local-PostgreSQL-17 verify step into the closing ritual `GUIDE.md` §2.7, placed BEFORE the merge step: new `# 2. Verifica locale su PostgreSQL 17` carries the docker recipe **verbatim** from `knowledge/testing/rules.md:10-12` (`docker run … postgres:17` → `DB_CONNECTION=pgsql … php artisan test` → `docker rm -f pg`) + a 2-line Italian rationale. Renumbered the ritual: Merge 2→3, Verifica semantica 3→4, Archive 4→5. Also added a one-line pointer in the §8 cheatsheet "Chiusura" block (mirrors the existing `→ verifica semantica … §2.7` idiom) so the quick-reference stays consistent with §2.7. **No new test** (docs-only; design D10 confirms `FoundationsDocsTest` only pins the F1 line → safe; matches the 2.2 precedent). F1 status line (§4 ~:174) untouched.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` `php ^8.5` ✓.)
- **`ralph/substrate-hardening`**: suite **254/254 (904 asserts) on SQLite** · `FoundationsDocsTest` 8/8 (GUIDE reader + F1 pin intact) · phpstan 0 @ max · pint --test clean · `openspec validate … --strict` valid. **No PG run for 5.1** (docs-only, zero code/DB surface — GUIDE.md edit); last full PG parity was 3.4 (253/253, 899===899). Task 6.1 is the cross-engine sweep; the standing `tests-pgsql` CI lane covers PG.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 12/17). Branch `ralph/substrate-hardening`. Sections 1 (3/3), 2 (3/3), 3 (4/4), 4 (1/1) DONE; Section 5 docs 1/4.
- **NEXT = task 5.2 — C13 `docs/development.md` corrections** (design D10). (a) Add a `RALPH_MODEL` bullet to the env-vars list (~:91-94) and correct the stale "The script has no model option of its own" sentence — `ralph.sh:24-25,204` pins `--model "${RALPH_MODEL:-claude-opus-4-8[1m]}" --effort "${RALPH_EFFORT:-max}"`; **keep the `RALPH_EFFORT` token** (`DevelopmentDocsTest` pins it). (b) Correct `:113` "nothing under `.claude/`" → no MCP server (`.mcp.json` absent) but `.claude/` carries hooks/skills/team-memory. (c) PHP floor `8.4 → 8.5` at `:11`, `:76`, and the `:121` constraint column. Keep `DevelopmentDocsTest` green (locked versions + `RALPH_EFFORT` token preserved). Verify file:line first — line numbers may have shifted.
- Then 5.3 (README exit codes 2+5 + semantic-verify, no test pins) · 5.4 (decisions/INDEX 4 gates) · 6.1 cross-engine sweep + 6.2 validate + traceability.

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Task 5.4/C15 will register 4 more in decisions/INDEX.md: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Codebase Patterns in `progress.md`** (read first): white-box concurrency-guard via reflection; query-builder UPDATE skips casts; connector-applied session-setting pin (config-pin both lanes + engine-guarded read-back); PHPStan-clean `Log::spy()` (closure 2nd-arg matcher); engine-guarded DB-CHECK test (assert BOTH halves, name not SQLSTATE); string-tested workflow YAML → parse for real + pin STRUCTURE.
- **Docs-task test policy (2.2 + 5.1):** non-code doc/template files → verify by "suite stays green" + a targeted run of the existing doc-reader test that covers the file (e.g. `FoundationsDocsTest` for GUIDE.md), NOT a new pin. When a doc edit adds a REQUIRED step that a cheatsheet in the SAME file mirrors, update the cheatsheet too (pointer line, not duplicated recipe).
- **Doc-pin map:** `DevelopmentDocsTest` (RALPH_EFFORT + locked versions, NOT the PHP-floor string), `FoundationsDocsTest` (GUIDE §4 F1 status line — NOT §2.7/§8), `CiWorkflowTest` (php 8.5 + gate order + concurrency), `PlatformRequirementsTest` (≥8.5). Update the pin in the SAME task.
- **PG verification (DB tasks):** local `docker run postgres:17` port 55432 (recipe + five traps `knowledge/testing/rules.md:9-25`); readiness via in-PHP PDO connect loop. CI `tests-pgsql` is the standing gate.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
