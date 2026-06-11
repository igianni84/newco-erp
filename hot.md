---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-11
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-11** — Infrastructure pushed to GitHub; operator playbook (`GUIDE.md`) added. No application code yet.

## Build & Quality Status
- No Laravel app installed yet — quality commands not runnable (expected; bootstrap change does this).
- CI: not configured yet (task 3.2 of bootstrap change).
- Note: this OpenSpec CLI (1.4.1, core profile) has no `verify` artifact/command — semantic verification is prompt-based, see GUIDE.md §2.7.

## Active Change & Next Task
- `openspec/changes/bootstrap-laravel-app/` — drafted + strict-validated, **NOT yet approved**.
- Next human action: **GUIDE.md → Fase 0** (read change → `touch …/APPROVED` + commit → `./ralph.sh --change bootstrap-laravel-app 2` to observe, then `15`).
- After bootstrap: ADR sessions #1 (DB engine) and #2 (event substrate), then `/spec-to-change` for the three foundations changes (GUIDE.md §3–4).

## Blockers & Decisions Needed
- Open ADR gates (CLAUDE.md table): DB engine, event substrate, auth, queue, object storage, storefront, hosting EU. None blocks the bootstrap change.
- External sandbox credentials (Airwallex/Xero/HubSpot) needed before F6 changes — human-procured.

## Open Patterns
- None yet — first loop iterations will seed `knowledge/` and the change's `## Codebase Patterns`.
