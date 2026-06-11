---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-11
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-11** — Guardrail hardening (P0) completed: protection of immutable layers is now mechanical, not just textual. No application code yet.

## Build & Quality Status
- No Laravel app installed yet — quality commands not runnable (expected; bootstrap change does this).
- CI: not configured yet (task 3.2 of bootstrap change).
- Guardrails live (60/60 hook tests green, 2026-06-11):
  - `.claude/hooks/protected-paths.sh` (PreToolUse Edit/Write): spec/, openspec/specs/, APPROVED blocked in every mode; CLAUDE.md/RALPH.md/ralph.sh/.claude/** (except .claude/memory/) + non-active changes blocked in loop mode (RALPH_LOOP=1 or bypassPermissions).
  - `.claude/hooks/git-guardrails.sh`: write-verbs (redirect/sed -i/tee/cp/touch) onto immutable layers blocked any-mode; `git push`, APPROVED creation, `openspec archive` blocked in loop mode.
  - `ralph.sh`: exports RALPH_LOOP + RALPH_ACTIVE_CHANGE; per-iteration integrity gate vs loop-start baseline → exit 5 on violation (documented in GUIDE.md §5).
- Note: protected-paths hook activates for interactive sessions at next session start (settings.json is read at startup).
- Note: OpenSpec CLI (1.4.1, core profile) has no `verify` command — semantic verification is prompt-based, see GUIDE.md §2.7.

## Active Change & Next Task
- `openspec/changes/bootstrap-laravel-app/` — drafted + strict-validated, **NOT yet approved**.
- Next human action: commit the guardrail hardening, then **GUIDE.md → Fase 0** (read change → `touch …/APPROVED` + commit → `./ralph.sh --change bootstrap-laravel-app 2` to observe, then `15`).
- After bootstrap: ADR sessions #1 (DB engine) and #2 (event substrate), then `/spec-to-change` for the three foundations changes (GUIDE.md §3–4).
- Strategy notes (2026-06-11 analysis): foundations changes must bake in mechanical invariant enforcement — Pest arch tests for module boundaries, domain-event registry, Money value object, i18n skeleton; add a PostgreSQL CI lane from the first invariant-bearing change (Module A) because `lockForUpdate()` is a no-op on SQLite, so no-oversell concurrency is untestable there.

## Blockers & Decisions Needed
- Open ADR gates (CLAUDE.md table): DB engine, event substrate, auth, queue, object storage, storefront, hosting EU. None blocks the bootstrap change.
- External sandbox credentials (Airwallex/Xero/HubSpot) needed before F6 changes — human-procured.

## Open Patterns
- None yet — first loop iterations will seed `knowledge/` and the change's `## Codebase Patterns`.
