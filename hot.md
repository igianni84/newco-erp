---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (interactive close session) — `foundations-money-i18n-flags` MERGED + ARCHIVED. F1 foundations complete 3/3.** Ran the full GUIDE §2.7 close end-to-end: (1) re-verified the loop's self-report — 243 tests, phpstan 0 @ max, pint clean, `validate --strict` valid, composer diff Pennant-only, no protected-file loop edits (ralph.sh=human `bfcd885`; APPROVED=empty swept marker; GUIDE not protected); (2) semantic-verify via 4 parallel subagents — **33/33 scenarios covered, 0 CRITICAL, 0 WARNING** (a few non-blocking SUGGESTIONs, all deliberate scope: FxRate positivity→Module E; Money int-overflow theoretical; ActorContext non-System e2e thin); (3) **PG verify = CI `tests-pgsql` lane** — pushed branch → CI green on SQLite + PostgreSQL 17 BEFORE merge; (4) merged `--no-ff` → `main@1ee1e00`, pushed, deleted branch (local+remote); (5) `openspec archive` → +10 reqs into `openspec/specs/`, `main@8fe0ec4`, pushed.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in `substrate-hardening`.)
- **`main@8fe0ec4`**: suite 243/243 (860 assertions) · phpstan 0 @ max · pint clean · CI two-lane green (quality SQLite + tests-pgsql PG-17). No active branch.

## Active Change & Next Task
- **NO active change** (`openspec list` → empty). F1 done (skeleton ✅ · domain-events-audit ✅ · money-i18n-flags ✅).
- **NEXT = author the next change via `/spec-to-change`** → human `APPROVED` → `./ralph.sh`. Two candidates: (a) staged sibling **`substrate-hardening`** (`php ^8.3`→`^8.5` + deferred composer churn), or (b) start **F2 = Module 0 (PIM/Catalog spine) + Module K (Parties)**.
- **GATE before Module K: ADR 3 (identity/auth)** — `grill-with-docs` session → ADR → proceed. Module 0 has no open gate.

## Blockers & Decisions Needed
- None active. Founder default calls stand.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **PG verification is the CI `tests-pgsql` lane, NOT local** (PG not installed locally). Close ritual: push the ralph branch → CI runs both lanes on it → confirm green BEFORE merging (development.md: "fix before merge") → merge `--no-ff`, push main, delete branch local+remote. Keeps main always-green.
- **Read archived `progress.md` Codebase Patterns** (12 patterns: VOs, casts, enums, lang/, Pennant, ActorContext, doc-pins) at `openspec/changes/archive/2026-06-13-foundations-money-i18n-flags/`.
- **Gotchas:** Pennant undefined feature → `false` (pair "off" asserts with `defined()`/`cases()`). HTTP/instance Feature tests use `Pest\Laravel\*` typed globals, never `$this->`. `config/` outside PHPStan → config-pin Feature test.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB).
