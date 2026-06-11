---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-11
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-11** — Development infrastructure scaffolded; no application code yet.

## Build & Quality Status
- No Laravel app installed yet — quality commands not runnable (expected; see bootstrap change).
- CI: not configured yet (task 3.2 of bootstrap change).

## Active Change & Next Task
- `openspec/changes/bootstrap-laravel-app/` — drafted, **NOT yet approved** (no APPROVED file).
- Next human action: review the change, `touch openspec/changes/bootstrap-laravel-app/APPROVED`, then `./ralph.sh --change bootstrap-laravel-app 15` as the loop smoke test.
- After bootstrap: `/spec-to-change` for Phase 1 foundations (event substrate, audit log, module skeleton) per `spec/05-release/Build_Workplan_v0.3-MVP.md`.

## Blockers & Decisions Needed
- Open ADR gates (root CLAUDE.md table): production DB engine (before Module 0 migrations), auth/identity (before Module K), event substrate (before first cross-module event), hosting EU (before staging). None blocks the bootstrap change.

## Open Patterns
- None yet — first iterations will seed `knowledge/` and `## Codebase Patterns` in the change's progress.md.
