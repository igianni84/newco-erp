---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 14:48 (ralph iter — task 6.1 done, committing green).** Removed the last bootstrap `User`-isms (design D1, cutover complete). Deleted `app/Models/User.php` + `database/factories/UserFactory.php`. Edited the default migration `0001_01_01_000000_create_users_table.php` to drop **only** its `users` block (kept `password_reset_tokens` + `sessions`). Finished `config/auth.php`: dropped the `User` import, the `web` guard, the `users` provider (+ commented variant) and the `users` broker; repointed `defaults.guard→operator`, `defaults.passwords→operators` (sole `AUTH_MODEL` default now `Operator`). **`Operator`/`operators` is the only authenticatable.** New `tests/Feature/Modules/OperatorPanel/AuthDefaultsTest.php`; swept 4 extra `users`-touching tests (OperatorGuardTest dropped its obsolete web-guard test, EnvironmentTest + OperatorsTableMigrationTest schema flips, DatabaseSeederTest dropped the transient users-count).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission 8.0.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/operator-auth-foundation`: **full suite 359/359 on SQLite AND 359/359 on PostgreSQL 17** (live `DRIVER=pgsql / SERVER=17.10`; `migrate:fresh` clean, `users` gone / `operators`+`sessions`+`password_reset_tokens` present; container removed). phpstan **0 @ max**, pint clean, `openspec validate --strict` valid. Acceptance grep `App\Models\User|UserFactory` over `app config database tests` **empty**.
- Commit pending this iter: **6.1**. 1.1→6.1 done (**10 of 12**); **6.2 next**.

## Active Change & Next Task
- **ACTIVE: `operator-auth-foundation`** — 12 tasks; **10 done, 2 remain, no blocker.**
- **Next: 6.2 Docs (NO code).** Update `CONTEXT.md` *Actor context* — seam is wired (authenticated `operator` → `newco_ops`/`Operator.id`, `system` otherwise; customer/producer deferred); **remove** the "reads NO authentication state … until that ADR wires it" line. Record in `docs/development.md` the pinned spatie 8.0.0, the operator-auth wiring, and the operator credential env vars (`OPERATOR_NAME`/`OPERATOR_EMAIL`/`OPERATOR_PASSWORD`). Mark the Identity/auth gate **built** on `decisions/INDEX.md` open-decisions line. `DevelopmentDocsTest` already cross-checks spatie (added at 1.1) → 6.2 is prose, not a new package row. Then **6.3** full cross-engine close (suite SQLite+PG17, phpstan max, pint --test; record PG17 run + spatie version in progress.md). After 6.3 → `<promise>CHANGE_COMPLETE</promise>`. Safe to relaunch `./ralph.sh`.

## Blockers & Decisions Needed
- **None.** 6.1 framework wrinkle (Laravel base-merges inert `web`/`users` into `config('auth.*')` — un-removable by file edit) handled framework-honestly; hard acceptance bullets all met. New rule in `knowledge/laravel/rules.md`.
- Standing founder decisions: 2FA opt-in INCLUDED (enforcement→security review); User→Operator replacement; bootstrap operator holds all 3 roles.
- **Open ADR gates (don't step in):** queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack/SPA/Fortify-Sanctum (Module S) · authority-tier RBAC (`feedback_prd_rr_approval`) · SoD floor + lifecycle FSM (`catalog-lifecycle-approval`) · MFA enforcement (security review).

## Open Patterns
- **Laravel deep-merges the framework base `config/auth.php` UNDER the app's** for `guards`/`providers`/`passwords` (`LoadConfiguration::mergeableOptions`) — `array_merge(base,app)` adds app keys but never deletes base keys, so `web`/`users` are ALWAYS in the merged `config('auth.*')` and can't be removed by file edit (inert once default guard repointed + nothing resolves `web`). `defaults` (non-mergeable) IS fully overridden. Prove a model-shell removal via the empty source-sweep + dropped table, never config-key absence. Acceptance greps over `app|config|database|tests` scan **comments** too → don't write the removed FQCN/factory token there. Full rule: `knowledge/laravel/rules.md`. Binds the deferred customer/producer guard slices.
- **`actingAs($model)` with NO guard arg uses `config('auth.defaults.guard')`** (now `operator`) — strongest functional default-guard proof. Customer/producer slices must pass their explicit guard (default stays `operator`).
- **Cross-engine discipline:** full suite on `postgres:17` for DB-touching tasks; confirm live `DRIVER=pgsql`; `pg_isready` busy-poll via `docker exec` (foreground `sleep` blocked); remove container. **log.md:** append ONLY via `scripts/memlog.sh`; hot.md ≤550 words.
- **Auth-principal models are EXEMPT from the module-table-prefix arch test** (design D7; ADR `2026-06-15-auth-principal-table-naming`) — flat `operators`. Spatie `assignRole` resolves on the MODEL's guard via `getConfigAuthGuards`. Contract-typed vendor getters → narrow with real `instanceof`/cast, never `@var`/`assert()`.
