---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (interactive — `operator-console-catalog-master` MERGED + ARCHIVED).** The ralph loop closed the change (11/11, `CHANGE_COMPLETE` at iter 11/20); this session ran the GUIDE §2.7 close ritual with a **dual pre-merge gate**. **Gate 1 — semantic verify (independent subagent): CLEAN** — 7/7 requirements pass Completeness/Correctness/Coherence, no CRITICAL, no coverage gaps (incl. a live red→green re-proof of the PHPStan no-Eloquent-write rule). **Gate 2 — PG17 full suite: 996/996, 5115 assertions, exit 0** on docker `postgres:17`. Then merge `--no-ff` (`a3b2943`) → `openspec archive` (`caa8fad`). The first operator console (Catalog **Product Master** lifecycle) + the `operator-console` capability foundation are now on `main` and in the living spec.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **996/996 green on SQLite AND PG17** (5115 assertions). phpstan 0; pint clean. composer untouched; no migrations.
- **Run-cmd gotcha (bit me this session, was already noted here):** the FULL suite OOMs under bare `php artisan test` (PHP CLI default 128M; the child pest process ignores a parent `-d`). Run pest directly with a raised limit: `php -d memory_limit=1024M vendor/bin/pest`. PG17: prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (docker pg start + teardown per run). GUIDE §2.7 still prints bare `php artisan test` — superseded by this line.

## Active Change & Next Task
- **No active change.** `operator-console-catalog-master` → `openspec/changes/archive/2026-06-20-operator-console-catalog-master/`; truth spec `openspec/specs/operator-console/spec.md` created (+7 requirements).
- **Next:** `operator-console-catalog-spine` (the other six catalog spine entities — Variant/Format/PR/CaseConfig/SKU/Composite), then the **Parties console** (change 2). The spine is where the shared base-Resource / operator-action abstraction is meant to emerge (design L9 — deliberately NOT extracted yet). Reuse the console Codebase Patterns from the archived change's `progress.md`.

## Blockers & Decisions Needed
- **`main` is LOCAL-ONLY — not pushed.** Merge (`a3b2943`) + archive (`caa8fad`) + this close-out are local; `main` is ahead of `origin/main`. Human pushes.
- **`ralph/operator-console-catalog-master` branch still present** (fully merged) — delete after push if desired (`git branch -d`).

## Open Patterns
- **Operator-console pattern (now in the living spec):** Filament resources read-bind module models read-only; every write `app(<Action>)->handle()`; no Edit page / no DeleteAction; SoD (ApprovalGovernance) + Producer-activation gate are **surfaced, not reimplemented**; a PHPStan rule bans Eloquent writes in `App\Modules\OperatorPanel`. Reusable verbatim for the spine + Parties consoles.
- **Close-ritual pattern (this session):** dual gate BEFORE merge (independent semantic-verify subagent + full PG17 suite), then merge → archive → memory close-out. Always confirm the real pest result (JSON / exit code) — the outer bash `exit 0` masked a pest OOM (exit 255) here.
