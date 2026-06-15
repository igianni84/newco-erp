---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 (interactive — GUIDE §2.7 close of `operator-auth-foundation`, delegated to Claude).** Re-verified every gate on this machine before touching main: SQLite 359/359 + **PG17 359/359** (live `DRIVER=pgsql 17.10` confirmed, container removed) + phpstan 0 @ max + pint clean + `openspec validate --strict` valid; ran an independent **semantic verification** (subagent) over the 4 delta specs → **CLEAN, 0 CRITICAL** (2 non-blocking SUGGESTIONs, already documented in-code). Then executed the ritual: `git merge --no-ff` → main (`1e46082`), pushed, deleted `ralph/operator-auth-foundation`; `openspec archive` (`d74c8fc`), pushed. **main in sync with origin/main; no active change.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission 8.0.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **main @ `d74c8fc`** (`1e46082 merge` + `d74c8fc archive`), pushed to origin. Full suite 359/359 SQLite AND PG17, phpstan 0 @ max, pint clean — all re-verified at close.
- **operator-auth-foundation: MERGED + ARCHIVED** → `openspec/changes/archive/2026-06-15-operator-auth-foundation/`. Delta specs fused into `openspec/specs/` (operator-identity created; event-substrate, module-architecture, platform updated).

## Active Change & Next Task
- **No active change** (`openspec list` empty). The operator-auth foundation is live on main: spatie RBAC + `operators` table + `Operator` principal + `operator` guard + panel cutover (password reset + opt-in 2FA) + `ActorContext` seam (operator→`newco_ops`) + Role/Operator seeding; orphaned `User` removed.
- **Next:** pick the next change per `spec/05-release/Build_Workplan_v0.3-MVP.md` (F2 = Module 0 Catalog + Module K Parties) via `/spec-to-change` → human APPROVED → `./ralph.sh`. ADR 3 (identity-auth) is CLOSED, so Module K is unblocked.

## Blockers & Decisions Needed
- **None.**
- Open ADR gates (future work): queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack/SPA/Sanctum (Module S) · authority-tier RBAC · SoD floor + lifecycle FSM (`catalog-lifecycle-approval`) · MFA enforcement (security review).

## Open Patterns
- **§2.7 close discipline (this session):** verify-first — re-run ALL gates on the human's machine, don't trust the loop's screenshot; run semantic-verify BEFORE merge (safer than the GUIDE's post-merge order — a CRITICAL never reaches main). Order: review → PG17 → semantic → merge --no-ff → push → branch -d → archive → commit → push. Memory close (log.md + hot.md) as a final `chore`-style commit.
- **Cross-engine recipe** (reusable for any `*-close` and §2.7): `docker run -d --name pg … postgres:17`, busy-poll `pg_isready` (foreground `sleep` is sandbox-blocked), **confirm the live driver** before trusting a PG run, `DB_CONNECTION=pgsql … php artisan test`, `docker rm -f pg`. PG wall-clock ≈4× SQLite.
- **Auth-principal models EXEMPT from module-table-prefix** (D7; ADR `2026-06-15-auth-principal-table-naming`). **Laravel deep-merges base `config/auth.php`** — inert `web`/`users` linger; default guard = `operator`. Rules: `knowledge/laravel/rules.md`. **log.md:** append ONLY via `scripts/memlog.sh`; hot.md ≤550 words.
