---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (interactive — `parties-holds` finished, MERGED to main + ARCHIVED).** The ralph loop had stopped at 10/11 (it printed the §2.7 "human steps" banner prematurely); finished task 6.3 interactively — `HoldChainTest` (closing end-to-end proof, 2 `it`/46 assertions), full suite **783/783 on SQLite AND PostgreSQL 17**. Then ran the full GUIDE §2.7 ritual: review → independent semantic verify (**CLEAN** — 0 critical/warning, 2 deferrable suggestions) → `git merge --no-ff` (merge commit `6c6275b`) → `openspec archive parties-holds` (synced **+4 ADDED / ~2 MODIFIED** requirements into `openspec/specs/party-registry/spec.md`; archived dir `2026-06-18-parties-holds`). The Module K Hold slice — registry (6 types × 3 scopes), lifecycle + per-type lift discipline, 2 events, sanctions/Hold read-API with cascade, KYC↔Hold coupling — is now live in the living spec.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 783/783 SQLite AND 783/783 on PostgreSQL 17** (full suite — the §2.7 pre-merge gate). PHPStan max 0 · Pint --test clean. `openspec validate --specs` → 9/9 specs valid post-archive.
- main HEAD = the `parties-holds` merge + the archive commit. **NOT pushed this session — `origin/main` is behind; push is the human's call.** Local branch `ralph/parties-holds` still exists (safe to `git branch -d`).

## Active Change & Next Task
- **No active change** (`openspec list` → none).
- **Next:** pick the next slice from `spec/05-release/Build_Workplan_v0.3-MVP.md` → `/spec-to-change` → human `APPROVED` → `./ralph.sh`. Pending optional cleanup: `git push` main; `git branch -d ralph/parties-holds`; in a future change reword the stale "deferred `parties-holds`" docblock in `RecordKycRejected.php` (semantic-verify SUGGESTION-1 — cosmetic, behavior correct & tested).

## Blockers & Decisions Needed
- None. Root `CLAUDE.md` Invariant #7 reword (per-type lift) remains the human's call (Protected file); ADR `2026-06-18-hold-lift-discipline-per-type.md` is the standing authority. Knowledge-promotion confirmation date for this change = `2026-06-18` (its archive-dir date).

## Open Patterns
- **Loop-end banner ≠ change complete.** A ralph run can print the §2.7 "next steps (human)" banner while `openspec list` still shows N−1/N. Always check `openspec list` / `tasks.md` before trusting the banner — finish the last task before any merge.
- **Closing chain-test idioms** (now in the archived `progress.md` of `2026-06-18-parties-holds`, candidates for `knowledge/testing`): read active Holds via the read-API not a re-declared global helper (Pest loads all files → fatal redeclare); order-insensitive `list<Enum>` set = `->toContain(e)->toContain(e)->toHaveCount(n)`; a pre-DML app-guard throw needs no savepoint for verify-after-throw on PG (trap 5 is constraint-RAISE only).
