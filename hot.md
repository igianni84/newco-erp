---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 14) — `substrate-hardening` 5.3 (C14) DONE, 14/17.** Section 5 docs now 3/4. `README.md`, 4 edits, each verified vs ground truth first: (a) **exit codes (:68)** — added `2` (preflight) + `5` (integrity violation); all six now listed, matching `docs/development.md:105` (ground truth: `ralph.sh:54` `fail()`→2, `:216`→5); kept README's terse altitude (short `(a protected layer was modified)` clarifier on 5, not the dev guide's full path list). (b) **ASCII-flow (:28)** — non-existent `/opsx:verify` → `semantic-verify (GUIDE §2.7)` (aligns with `:53`); **skills table (:94)** same phantom `verify` → `sync` (real opsx subcommand). (c) **floor-sweep (:39)** — `php >= 8.4 → 8.5`, the LAST stale `8.4` in the repo (C4/2.1 raised the floor; design enumerated :68/:28/:94 but was silent on :39 — fixed transparently, flagged for review). **No new test** (no README-reader test exists; docs-only — verify by suite-green + a read of touched lines).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant 1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` `php ^8.5` ✓.)
- **`ralph/substrate-hardening`**: suite **254/254 (904 asserts) SQLite** (UNCHANGED — 5.3 docs-only, zero code/DB surface) · phpstan 0 @ max · pint --test clean · `git diff README.md` exactly 4+/4− · `openspec validate … --strict` valid. **No PG run for 5.3** (docs-only); last full PG parity was 3.4 (253/253, 899===899). Task 6.1 is the cross-engine sweep; the standing `tests-pgsql` CI lane covers PG.

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 14/17). Branch `ralph/substrate-hardening`. Sections 1–4 DONE (3/3, 3/3, 4/4, 1/1); Section 5 docs 3/4.
- **NEXT = task 5.4 — C15 track 4 untracked gates in `decisions/INDEX.md`** (design D10/C15, `design.md:65`). Extend the "Open decisions" section (~:14-16 — VERIFY current line first) with explicit one-line trigger/gate entries for: **secrets management** · **observability** · **PCI boundary** · **architectural security review**. Keep them in `decisions/INDEX.md` (the existing editable open-gates registry), NOT a new gates doc (design's explicit decision — CLAUDE.md's stack-gate table is protected + stack-scoped). No test pins this → suite-green + a read of the added entries.
- Then 6.1 cross-engine sweep (SQLite quality cmds + local PG17 docker) · 6.2 `openspec validate --strict` + traceability (map delta scenarios → tests, record patterns in progress.md).

## Blockers & Decisions Needed
- None active.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Task 5.4/C15 registers 4 MORE in decisions/INDEX.md: secrets · observability · PCI boundary · security review.

## Open Patterns
- **Codebase Patterns in `progress.md`** (read first): white-box concurrency-guard via reflection; query-builder UPDATE skips casts; connector-applied session-setting pin; PHPStan-clean `Log::spy()` closure matcher; engine-guarded DB-CHECK test (assert BOTH halves, name not SQLSTATE); string-tested YAML → parse for real + pin STRUCTURE.
- **Docs-task policy (2.2/5.1/5.2/5.3):** non-code docs → suite-green + the file's doc-reader test (`DevelopmentDocsTest`/`FoundationsDocsTest`); README has NONE → suite-green + a read of the touched lines. Verify doc refs vs the AUTHORITATIVE source (ralph.sh for exit codes, the live skill registry for opsx cmds), not a sibling doc. Grep pinned tokens BEFORE the suite.
- **Doc-pin map:** `DevelopmentDocsTest` (RALPH_EFFORT + locked versions + 5 Quality Commands — NOT the PHP floor) · `FoundationsDocsTest` (GUIDE §4 F1 line) · `CiWorkflowTest` (php 8.5 + gate order + concurrency) · `PlatformRequirementsTest` (≥8.5 runtime). No test reads README.
- **PG verification (DB tasks):** local `docker run postgres:17` port 55432 (recipe + five traps `knowledge/testing/rules.md:9-25`); readiness via in-PHP PDO connect loop. CI `tests-pgsql` is the standing gate.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
