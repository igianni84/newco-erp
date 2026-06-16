---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (interactive — Dreaming memory-curation skill built + first curation merged via PR #1).** New `/dreaming` skill (`.claude/skills/dreaming/`, propose-only, `disable-model-invocation`): a scheduled curation pass over the memory system that promotes confirmed cross-change patterns to `knowledge/` rules, extracts lessons, and flags stale memory — applied on a `dream/<date>` branch + PR, never `main`. PR #1 (reviewed by an adversarial sub-agent → 0 blockers, 2 one-line fixes applied) merged `--no-ff` → `main` (`37f413a`), branch deleted, pushed. First run promoted **3 cross-change rules** (Pint `{@see \FQCN}` auto-import, PG enum-`CHECK` from `::cases()`, Pest global-helper namespace) + a **6th SQLite/PG portability trap**; 6 further candidates + a proposed `knowledge/data-model` domain are parked in `dreams/2026-06-16.md`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Green on `main`: full suite 613/613 SQLite AND 613/613 PostgreSQL 17** (last code change at the catalog-lifecycle-approval close). The Dreaming PR is docs/knowledge-only — no code touched, CI green (4/4).

## Active Change & Next Task
- **NONE active** (ERP build). `openspec list` → "No active changes found."
- **Next (ERP):** `/spec-to-change` for the next Build_Workplan slice. Deferred follow-ons: `catalog-operator-console`, `parties-compliance`, Phase-3 cross-module referencers (D8).
- **Next (Dreaming, Phase 2):** wire a scheduled cloud routine (`/schedule`, weekly) that runs `/dreaming`, + write ADR `decisions/2026-06-16-scheduled-memory-dreaming.md` (mechanism = cloud, propose-only-via-PR, cadence, cost). Optionally apply the 6 Proposed curations from `dreams/2026-06-16.md`.

## Blockers & Decisions Needed
- **None.**

## Open Patterns
- **Dreaming is now the memory-curation actor.** The hypotheses→rules promotion lifecycle (previously best-effort — all three `hypotheses.md` were empty while patterns piled up in archived `progress.md`) now runs on a cadence. Promotion bar: **≥3 dated cross-change confirmations → rule-grade** (direct to `rules.md`).
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in the arch plugin).
- **PG17 gate** before a DB task is done: `docker run -d --name pg … postgres:17`; run with `DB_CONNECTION=pgsql … -p 55432`; `docker rm -f pg`.
- **GUIDE §2.7 close** ritual for ralph changes; Dreaming PRs follow the same `merge --no-ff` + delete-branch + memory-close flow.
- **Heredoc `cat <<EOF` mentioning "spec" trips the git-guardrails Bash hook** — append memory via Edit/Write or `scripts/memlog.sh`, never a shell redirect.
