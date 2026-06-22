---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-customer` — COMPLETE: 9/9 tasks → `<promise>CHANGE_COMPLETE</promise>`).** Group 5 (task 5.1) added `CustomerConsoleChainTest` — the PG17 closing-chain integration proof. One `it()` drives a Customer through the console PAGES per D9: **(a)** create via `CreateCustomer` page → `pending` + 1 `CustomerCreated`, then a gate-UNMET `activate` via `ViewCustomer` → `action_failed`, stays `pending`, **records no event** (an intermediate `toEqual(['CustomerCreated'])` localises the D5 cross-slice-gate claim); **(b)** factory-seed a gate-MET, profile-less Customer (3 acceptances + `sanctions_status=Passed`, `kyc_required` null) and drive activate→suspend→reactivate→close → emergent set `toEqualCanonicalizing(['CustomerCreated','CustomerActivated','CustomerSuspended','CustomerReactivated','CustomerClosed'])` (5 events; profile-less seed kept the Profile cascade silent), every event `parties`/`NewcoOps`/actor non-null, representative actor_id loose-`toEqual` operator. Test-only iteration.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: SQLite 1397/1397 (7683 assn, +1 test/+54 assn), filtered `CustomerConsoleChainTest` 1/1 (54 assn); PG17 folder run 274/274 (1274 assn — Parties console folder + Catalog sink-helper loader, `postgres:17` on 55432, container torn down); phpstan 0; pint clean; `ModuleBoundariesTest` 3/3 (189 assn) UNCHANGED; validate --strict valid; composer diff vs main empty.**
- **main is 2 commits ahead of origin/main, UNPUSHED** (supply-side merge+archive; push classifier-denied earlier) — deferred to human. This change's branch `ralph/operator-console-parties-customer` is NOT yet merged.
- Run-cmd: full `php -d memory_limit=-1 vendor/bin/pest`. PG17 = GUIDE §2.7 ritual: `docker run -d --name pg … postgres:17 -p 55432:5432` → poll `pg_isready` → `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 … vendor/bin/pest <folder>` (append a Catalog i18n test so the shared sink helper loads) → `docker rm -f pg`. i18n tests via `--filter`/full suite, NEVER a bare path.

## Active Change & Next Task
- **`operator-console-parties-customer` COMPLETE — 9 of 9 tasks done.** Branch `ralph/operator-console-parties-customer` awaits **human review → merge to main → semantic-verify (GUIDE §2.7) → `openspec archive`** — the loop does NOT archive/merge/push.
- **No next task in this change.** Forward note: the next slice `operator-console-parties-compliance` will surface `PlaceHold`/`LiftHold`, whose coupling ALSO moves Customer status — its design MUST reference D4 here and treat the Hold-driven transition as additive to (not a replacement of) the direct activate/suspend/reactivate/close verbs shipped here.

## Blockers & Decisions Needed
- **Push decision (human, carried over):** main holds the supply-side merge+archive locally (2 commits); origin/main not updated. This Customer change adds further local commits on its ralph branch (also unpushed).
- Otherwise none.

## Open Patterns
- **Two-part closing-chain for a cross-slice-gated FSM:** drive the gate-UNMET path through the REAL create page (proves graceful reject, D5), then a factory-seeded gate-MET record through the full FSM; an intermediate single-element `toEqual([...])` localises the gate claim before the global `toEqualCanonicalizing`. Recipe in the change's `progress.md` §5.1.
- **Console i18n completeness test = enumerate kit contract + 5 guards (proven 5×).** Full recipe + the 2 gotchas in `progress.md` §Codebase Patterns.
- **Non-catalog status-FSM view page = `ViewRecord` + `use SurfacesDomainActions` + bespoke `getHeaderActions()`** (D8 CLOSED). The cross-slice activation gate (D5) is a test-seed concern, not code.
