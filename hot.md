---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (interactive — Dreaming Phase 2 merged to `main`).** PR #2 (`dream/2026-06-16-apply-proposed`) reviewed by a specialized agent (0 blockers, clean PASS; CI green) → merged `a6fc85f` + pushed + branch deleted. **Follow-up:** standardized `catalog-product-spine` confirmations on the archive-dir date + **codified that convention** (`.claude/CLAUDE.md` Knowledge System + `lessons.md`). (1) **Scheduled Dreaming:** a weekly claude.ai cloud routine (`trig_0178e8Bv8K5p1zfvcjPg76W5`, Mondays 06:00 UTC = 08:00 Rome, first auto-run 2026-06-22) runs `/dreaming` on **Opus 4.8 1M**, propose-only via a `dream/<date>` PR — ADR `decisions/2026-06-16-scheduled-memory-dreaming.md`. (2) **Applied the Proposed curations** from `dreams/2026-06-16.md`, each re-verified against archived `progress.md`: every pattern met/exceeded its claimed count.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Green on `main`: 613/613 SQLite AND 613/613 PostgreSQL 17** (last code change = catalog-lifecycle-approval close). This branch is **knowledge/docs-only — no code touched.**

## Active Change & Next Task
- **NONE active** (ERP build). `openspec list` → "No active changes found."
- **Next (ERP):** `/spec-to-change` for the next Build_Workplan slice. Deferred follow-ons: `catalog-operator-console`, `parties-compliance`, Phase-3 cross-module referencers (D8).
- **Dreaming Phase 2: DONE & merged** (PR #2 → `main` `a6fc85f`). Routine live; the weekly routine now carries the cadence (first auto-run 2026-06-22).

## Blockers & Decisions Needed
- **None.**

## Open Patterns
- **`knowledge/` now: architecture 2 · data-model 2+1 · laravel 3+1 · testing 4+1.** New `data-model` domain owns DDL: enum-`CHECK` (relocated from laravel) + 63-char index naming + the spine-template hypothesis. Promotion bar: **≥3 dated cross-change confirmations → rule-grade**.
- **Dreaming is the scheduled memory-curation actor.** Mechanical drift → `memory-health.sh` Stop hook; semantic curation → the weekly routine. Both propose-only, both via PR/warning, never auto-`main`.
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in the arch plugin).
- **PG17 gate** before a DB task is done: `docker run -d --name pg … postgres:17`; run with `DB_CONNECTION=pgsql … -p 55432`; `docker rm -f pg`.
- **Heredoc `cat <<EOF` mentioning "spec" trips the git-guardrails Bash hook** — write memory via Edit/Write or `scripts/memlog.sh`, never a shell redirect.
