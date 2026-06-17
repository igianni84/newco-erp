---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-17
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-17 (interactive — `parties-compliance` MERGED to main + ARCHIVED).** Post-loop close-out (GUIDE §2.7) run by hand. Independently re-verified the loop's green: **718/718 SQLite** (3361 assertions), PHPStan max 0, Pint clean, `openspec validate --strict` valid, composer diff empty. Semantic verification (subagent, per §2.7) = **CLEAN-WITH-WARNINGS, 0 CRITICAL** → merge approved. Two non-blocking WARNINGs noted: (1) dead `IllegalSanctionsTransition::cannotResolve()` factory + its `parties.sanctions.cannot_resolve` lang key (never called in production — documented scope decision); (2) copy nuance in `parties.kyc.cannot_require` (omits NULL as legal from-state; harmless). Merged `ralph/parties-compliance` → `main` `--no-ff` (**b4e8561**); archived to `openspec/changes/archive/2026-06-17-parties-compliance/`, delta synced into `openspec/specs/party-registry/spec.md` (+4 req, ~2 mod) (**cad774b**). PG17 cross-engine close (168/168) was run by the loop, not re-run here.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 718/718 SQLite** (3361 assertions). PHPStan max 0 · Pint clean. PG17 168/168 (from the loop).
- Local `main` is **14 commits ahead of origin/main** (11 feat + approve + merge + archive). **NOT pushed** — see Blockers.

## Active Change & Next Task
- **No active change.** `parties-compliance` merged + archived (11th archived change); `openspec/changes/` clean.
- Build position: **end of Phase 2** (Catalog Mod 0 domain-complete; Parties Mod K ~70-75%). **Phases 3-6 (A/D/S/B/C/E) NOT started** — those module dirs are 30-line stub providers; no domain UI (no Filament resources). No commercial E2E possible yet.
- **Next ralph run needs a fresh APPROVED change.** Likely successors on the Parties slice: **`parties-holds`** (unified 6-type Hold registry + `kyc`-Hold coupling onto the KYC Actions) or **`parties-membership-lifecycle`** (demand-side Customer/Account/Profile status FSMs). Run `/spec-to-change` to author.

## Blockers & Decisions Needed
- **Push to origin/main PENDING human authorization.** The close-out `git push origin main` was blocked by the safety classifier (direct-to-default-branch). Local main carries merge+archive; the `ralph/parties-compliance` branch is **kept as a safety net** until the push lands, then `git branch -d` it. Resolve: human runs `git push origin main` (or grants the push permission).
- Optional follow-up micro-change: remove the dead `cannotResolve()` factory + its lang key + unit-test case.

## Open Patterns
- **Close-out ritual idiom (§2.7):** re-verify the loop's green independently; run semantic-verify as a GATE **before** merge (safer than the GUIDE's after-merge order); only then merge `--no-ff` + archive; hold the branch until the push lands.
- **PG17 gate recipe:** `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; wait `until docker exec pg pg_isready -U newco -q; do sleep 0.5; done`; `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest <paths>`; `docker rm -f pg`.
- **Full suite runner** = `php -d memory_limit=512M vendor/bin/pest` (plain `php artisan test` OOMs at 128M).
