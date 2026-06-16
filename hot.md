---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (interactive — GUIDE §2.7 close of `catalog-lifecycle-approval` DONE).** The completed change (17/17, loop HEAD `ac7fa90`) is now **merged to `main` and archived**. Steps executed: (1) branch review; (2) **PG17 gate — the FULL suite, not just the Catalog subset, ran 613/613 on PostgreSQL 17** *and* 613/613 on SQLite; (3) `merge --no-ff` → `0fa2fb6`, pushed, branch `ralph/catalog-lifecycle-approval` deleted; (4) **semantic verify via 4 parallel sub-agents** over all 8 delta requirements → **0 CRITICAL / 0 WARNING** (only test-symmetry SUGGESTIONs — invariant 10 clean, watermark/idempotency correct, Option-B retirement scoping exact, cascade atomicity+ordering proven); (5) `openspec archive` → **7 ADDED + 1 MODIFIED requirement merged into `openspec/specs/product-catalog/spec.md`** (now 18 reqs, validates `--strict`), archived as `2026-06-16-catalog-lifecycle-approval`, commit `5308dc3`, pushed. `main` in sync with `origin/main`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Green on `main`: full suite 613/613 SQLite AND 613/613 on PostgreSQL 17** (2806 assertions; the whole suite is PG-clean, not just Catalog). phpstan max 0; pint clean at last loop iter. No composer drift.

## Active Change & Next Task
- **NONE active.** `openspec list` → "No active changes found." `catalog-lifecycle-approval` is fully closed (merged + archived).
- **Next:** run `/spec-to-change` to convert the next Build_Workplan slice into a change (human review → `APPROVED` → `./ralph.sh`). Candidate follow-ons below.

## Blockers & Decisions Needed
- **None.** Deferred follow-ons (carried forward from the catalog change): `catalog-operator-console` (Filament approval-queue UI on the audit-derived rejection-pending state), `parties-compliance` (KYC tightens `ActivateProducer` upstream — D6), Phase-3 referencers (the cross-module retirement-blocking leg — D8). Accepted SUGGESTIONs from semantic verify (optional test-hardening: per-entity reviewer-approves / role_count=2 symmetry; assert standalone Format/CaseConfig survive a cascade) → fold into a future change or `knowledge/testing/`, not blocking.

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in the arch plugin).
- **PG17 gate** (lifecycle/cross-module tests use `DatabaseMigrations`): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; wait `docker exec pg bash -c 'until pg_isready -U newco -q; do sleep 0.5; done'`; run with `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`; `docker rm -f pg`. At §2.7 close, run the WHOLE suite on PG (it passes), not just the change subset.
- **GUIDE §2.7 close (delegated to Claude):** review → PG17 verify → `merge --no-ff` + push + delete branch → semantic verify (sub-agents; pause before `main` if any CRITICAL) → `openspec archive <name> --yes` + `git add -A && commit "archive: <name>" && push`. Archive renames the change folder into `changes/archive/YYYY-MM-DD-<name>/` and merges delta specs into `openspec/specs/`.
- **Heredoc `cat <<EOF >> file` mentioning "spec" trips the git-guardrails Bash hook** — append to memory/progress files via the Edit tool (Read the tail first), not a shell redirect. `scripts/memlog.sh` is the sanctioned log.md appender.
