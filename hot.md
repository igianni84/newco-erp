---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 15:04 (ralph iter — task 6.3 done → CHANGE_COMPLETE).** Final cross-engine close, **verification-only (NO product code)**. Ran the ENTIRE Pest suite on both engines + the static/format gates at max; confirmed the live PG driver before the PG run. **SQLite 359/359** (1347 assert) · **PostgreSQL 17 359/359** (1347 assert; confirmed live `DRIVER=pgsql SERVER=17.10`, container removed) · **phpstan 0 @ max** · **pint + pint --test clean** · `openspec validate --strict` valid. Recorded the PG17 run + the pinned `spatie/laravel-permission` **8.0.0** (re-verified from `composer.lock`) in `progress.md` per acceptance. Touched only `tasks.md` (6.3 flipped), `progress.md`, `hot.md`, `log.md`. **All 12 tasks `- [x]` → emitted `<promise>CHANGE_COMPLETE</promise>`.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission **8.0.0** · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/operator-auth-foundation`: **CLOSE-VERIFIED on BOTH engines** — full suite 359/359 SQLite AND 359/359 PG17 (live driver confirmed), phpstan 0 @ max, pint + pint --test clean, `openspec validate operator-auth-foundation --strict` valid.
- **operator-auth-foundation COMPLETE: 12 of 12 tasks done (1.1→6.3).** Commit pending this iter: **6.3** (final).

## Active Change & Next Task
- **`operator-auth-foundation` is COMPLETE** — all 12 tasks `- [x]`, no blockers, suite green on both engines. Emitted `<promise>CHANGE_COMPLETE</promise>` this iter.
- **Do NOT relaunch `./ralph.sh` for this change** — there is no next task. **Awaiting the HUMAN** for the GUIDE §2.7 close: review → merge `ralph/operator-auth-foundation` → push → semantic-verify → `openspec archive operator-auth-foundation --yes`. Ralph does NOT archive or merge (closing-ritual delegation: Claude may run the close on Giovanni's explicit say-so, verify-first).
- After archive, the next change is picked per `spec/05-release/Build_Workplan_v0.3-MVP.md`.

## Blockers & Decisions Needed
- **None.** Every acceptance bullet of 6.3 met (full suite green SQLite + PG17, phpstan 0 @ max, pint clean, PG run + spatie 8.0.0 recorded in progress.md).
- Standing founder decisions (built into the foundation): 2FA opt-in INCLUDED (enforcement→security review); User→Operator replacement DONE; bootstrap operator holds all 3 roles (Creator/Reviewer/Approver).
- **Open ADR gates (NOT stepped in by this change; future work):** queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack/SPA/Sanctum (Module S) · authority-tier RBAC (`feedback_prd_rr_approval`) · SoD floor + lifecycle FSM (`catalog-lifecycle-approval`) · MFA enforcement (security review).

## Open Patterns
- **Cross-engine close recipe** (used by 6.3; reusable for any `*-close`): `docker run -d --name pg … postgres:17`, busy-poll `pg_isready` (foreground `sleep` is sandbox-blocked), **confirm the live driver** (`DB::connection()->getDriverName()` + `PDO::ATTR_SERVER_VERSION`) before trusting a PG run, `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 … php artisan test`, `docker rm -f pg`. PG wall-clock ≈4× SQLite for the same suite (round-trips, not a hang). A close task owes BOTH the PG run AND the pinned-dep version (re-read from `composer.lock`) in `progress.md`.
- **Auth-principal models are EXEMPT from the module-table-prefix arch test** (D7; ADR `2026-06-15-auth-principal-table-naming`): `implementsInterface(Authenticatable)` → flat name (`Operator`→`operators`). Invariant 10 stays enforced by `ModuleBoundariesTest` + guard-by-name. Forward-binds deferred customer/producer principals.
- **Laravel deep-merges the framework base `config/auth.php` UNDER the app's** (`guards`/`providers`/`passwords`) — inert `web`/`users` linger; `defaults` IS fully overridden (default guard = `operator`). `actingAs($model)` (no guard arg) uses `config('auth.defaults.guard')` = `operator`. Full rules: `knowledge/laravel/rules.md`. **log.md:** append ONLY via `scripts/memlog.sh`; hot.md ≤550 words.
