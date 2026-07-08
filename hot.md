---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 — `parties-module-k-br-guards` CLOSED via the full §2.7 ritual: merged to main (`40f6c0a`, `--no-ff`) + archived (`2026-07-08-parties-module-k-br-guards`, commit `2671dde`). RM-19/20/21/22/23 → ✅ in the tracker.** The §7 human-gated close ran in-session: 7.1 full gate on BOTH engines; **semantic verify = 5 independent subagents × all 14 delta requirements → 1 CRITICAL fixed in-place** (`CONTEXT.md` "Agreement scope" still asserted the pre-guard "may both be active" — the design-R3 residual; → new lessons.md rule: inversion sweeps must include CONTEXT.md) **+ 4/6 WARNINGs fixed** (stale free-string cadence comments ×2; `REVIEW_GOVERNED_FIELDS` const↔test coupling + pin test; delta-spec null-DOB clause + 2 scenarios; console `auto_renew`-inheritance assertion), 2 accepted + ~12 SUGGESTIONs logged (archived `progress.md` §7); 7.2 traceability matrix (14 req → every scenario ≥1 test) in the archived `progress.md`; 7.3 tracker (§1/§3/§4/§6 + stale F4 flag flipped ✅). Truth specs post-archive: party-registry **+4 ADDED / ~6 MODIFIED**, operator-console **~4 MODIFIED**; `openspec validate --all --strict` **10/10**.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2080/2080 on SQLite AND PG17** (10 854 assertions each) · PHPStan max **0** · Pint **clean**.
- Run the suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB). **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container on :55432, running).

## Active Change & Next Task
- **No change in flight** — `openspec/changes/` holds only `archive/`. Branch `ralph/parties-module-k-br-guards` merged (deletable after push).
- **PUSH PENDING (classifier-gated — ASK Giovanni before `git push`):** local `main` is ahead of `origin/main` by the whole batch — pre-batch memory/approve commits (`fe31c4d`, `dc15eeb`), the 24 branch commits (incl. the two §7-close ones), merge `40f6c0a`, archive `2671dde`, + the close memory-sync commit.
- **Next candidates** (tracker §1): **RM-15** (Module 0 Producer-existence at creation — kept separate, S, maybe-ADR) · P3 Module-0 completeness (RM-12/13/14) · **RM-05** stays ⏸️ (Module A `qty`). F2 (prod operator-management) is pre-go-live. Or advance the Build Workplan (F2 → Module A/D slices via `/spec-to-change`).

## Blockers & Decisions Needed
- None technical. Only the push gate (ask first), then `git branch -d ralph/parties-module-k-br-guards`.

## Open Patterns
(full forms in the archived change's `progress.md` `## Codebase Patterns`)
- **Inversion sweeps include CONTEXT.md** — the glossary of record is a first-class claim-holder archive-sync never rewrites (2026-07-08 lesson; caught as the close's one CRITICAL).
- Module-K guard family shipped this batch: model `saving` value-domain reject (Club-6) · conditional `updating` content lock on persisted status (Producer-5) · pre-txn fail-fast input gates (Identity-6 age, RM-22 cadence) · in-txn reference guards (RM-21, Agreement-4) · activation-time cross-shape exclusion (RM-20) · batch-walk cascade reusing an audit-only Action with a load-bearing from-state filter (RM-19).
- Console: operand-enum Selects with server floors · two-field create-rejection routing via condition re-derivation (no exception import) · zero-page-code guard surfacing · ungated preference affordances.
