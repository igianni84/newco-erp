---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` COMPLETE — 15/15, `CHANGE_COMPLETE` emitted).** Task 5.4 was the final full-suite gate: verification-only, **no new code** (just the 5.4 checkbox flip + memory files). All gates green and every task's acceptance re-verified at a glance. The change now awaits the human: push → CI both lanes → review → merge → `openspec archive club-credit` (the ralph loop never archives/merges/pushes).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (5.4 gate, 2026-06-23 13:00):** full suite **1560/1560 (8500 assn)** via `php -d memory_limit=-1 vendor/bin/pest`; PHPStan max **0**; `pint --test` clean; `openspec validate club-credit --strict` valid. Working tree clean.
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (laravel/pao OOMs at 128 MB; lessons.md 2026-06-20).
- **PG17:** no local PG for this project by design (`psql`/`pg_ctl` absent; the running `invoicing-system-db-1` container is a DIFFERENT project — do NOT co-opt). The change's DDL is Postgres-truthful by construction (engine-identical raw partial index + driver-guarded CHECK; Actions pure Eloquent). CI `tests-pgsql` lane (`postgres:17` service container) runs on the human push.

## Active Change & Next Task
- **`club-credit` — 15/15 DONE. `<promise>CHANGE_COMPLETE</promise>` returned.** No further ralph task remains in this change. Branch `ralph/club-credit` carries the 15 local commits (last: 5.4 gate). **Next human steps:** push → CI green (quality + tests-pgsql) → review → merge → `openspec archive club-credit`.
- **After archive:** no in-flight change. The next slice is a human-driven `/spec-to-change` (Module K continues, or whatever the build-workplan calls next). The loop has nothing to pick up until a new change has an `APPROVED` file + unchecked tasks.

## Blockers & Decisions Needed
- **No blocker.** No open ADR gate stepped through this change. `main` in sync with `origin/main`; the work lives on `ralph/club-credit` unpushed.
- Deferred SEAMS recorded in CONTEXT.md + the glossary (NOT blockers, future modules): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-110/111 mutual-exclusion + DEC-043 closure-conversion + order-cancellation restore; year-end forfeiture scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **The 4 writers (Issue/Apply/Forfeit/Restore) are COMPLETE, audit-only, i18n-wired, docs-landed** — all in `SupplyLifecycleChainTest`'s `$clubCreditWriters` allow-list. Any new non-`Create` Parties Action MUST be added there or the exact-match `toEqualCanonicalizing` reds.
- **A gate-task's PG17 proof is structural, not local (NEW, 5.4):** engine-identical raw SQL + driver-guarded CHECK + pure-Eloquent Actions ⇒ "by construction + CI on push." There is no local PG for this project; don't fake a run against another project's stray container.
- **Closing a seam = grep + de-stale every breadcrumb** (5.3): a deferred-seam claim scatters across many docblocks; `grep -rniE -A1 "<entity>" app/… | grep unbuilt` finds them all. Module S/E will inherit many.
- **The freeze is enforced at the REDEMPTION site, not suspension:** `Suspend*` stays state-preserving; `ApplyClubCredit` reads the owning Profile's live `state`.
