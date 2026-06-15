---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 14:31 (ralph iter — task 5.2 done, committing green).** Seeding cutover complete (design D6). `OperatorSeeder` now `updateOrCreate`s an **`Operator`** (was `User`) from the unchanged `OPERATOR_NAME/EMAIL/PASSWORD` env contract and grants it **all three** roles via `$operator->assignRole(RoleSeeder::ROLES)`. `DatabaseSeeder` now `$this->call([RoleSeeder::class, OperatorSeeder::class])` (roles **before** operator so the grant resolves) + dropped the `Test User` factory line **and** the now-purposeless `WithoutModelEvents` trait. Made `RoleSeeder::ROLES` **public** (single source of truth for the role set). Modified `tests/Feature/OperatorSeederTest.php` (`User`→`Operator`, +role-grant +operator-guard-auth proofs); new `tests/Feature/DatabaseSeederTest.php` (ordering + drop-Test-User).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission 8.0.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/operator-auth-foundation`: **full suite 356/356 on SQLite AND 356/356 on PostgreSQL 17** (live `DRIVER=pgsql / SERVER=17.10` printed, container removed). phpstan **0 @ max**, pint clean, `openspec validate --strict` valid.
- Commit pending this iter: **5.2** (`feat(operator-auth-foundation): 5.2 OperatorSeeder + DatabaseSeeder → Operator`). 1.1+2.1+2.2+2.3+3.1+4.1+4.2+5.1+5.2 done (**9 of 12**); 6.1 next.

## Active Change & Next Task
- **ACTIVE: `operator-auth-foundation`** — 12 tasks; **9 done, 3 remain, no blocker.**
- **Next: 6.1 `Remove the orphaned User` + finish `config/auth.php`** (design D1). Delete `app/Models/User.php` + `database/factories/UserFactory.php`; edit the default migration to drop **only** its `users` block (keep `password_reset_tokens` + `sessions`); remove the `users` provider from `config/auth.php` and repoint the app default guard (+ `AUTH_MODEL` default) to `operator`/`Operator`. Everything cut over in groups 3–5, so this stays green. New test `tests/Feature/Modules/OperatorPanel/AuthDefaultsTest.php` (default guard `operator`, no `users` provider, `hasTable('users')` false / `operators`+`sessions`+`password_reset_tokens` true). **Retire the transient `assertDatabaseCount('users', 0)` in `DatabaseSeederTest`** (table gone at 6.1). `grep -rE 'App\\Models\\User|UserFactory' app config database tests` must be empty. Verify on PG17. Safe to relaunch `./ralph.sh`.

## Blockers & Decisions Needed
- **None.** 2.2 spec-vs-arch carve-out resolved (Option A, ADR `decisions/2026-06-15-auth-principal-table-naming.md`, design D7).
- Standing founder decisions: 2FA opt-in INCLUDED (enforcement→security review); User→Operator replacement; bootstrap operator holds all 3 roles.
- **Open ADR gates (don't step in):** queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack/SPA/Fortify-Sanctum (Module S) · authority-tier RBAC (`feedback_prd_rr_approval`) · SoD floor + lifecycle FSM (`catalog-lifecycle-approval`) · MFA enforcement (security review).

## Open Patterns
- **Spatie `assignRole('Name')` resolves on the MODEL's guard, NOT the app default (5.2):** `Role::findByName('X', $model->getDefaultGuardName())` → `getConfigAuthGuards` picks the guard whose provider model === the model's class, so an `Operator` lands roles on `operator` even while the default is still `web`. `findByName`→`findByParam` is a DIRECT query (not the registrar cache) → role resolution is immune to `WithoutModelEvents`/stale caches; the grant seeder needs no `forgetCachedPermissions()`. `assignRole` idempotent via `array_diff`; `collectRoles` flattens so pass the array (`assignRole(RoleSeeder::ROLES)`). Customer/producer grant seeders copy verbatim.
- **Spatie role seeding (5.1):** `Role::firstOrCreate(['name'=>…,'guard_name'=>'<guard>'])` idempotent (bypasses static-`create()` throw); always pass `guard_name` explicitly (defaults to `web` until 6.1); `forgetCachedPermissions()` at seeder top. Role names are spec data identifiers (no i18n).
- **Cutover status:** panel guard 3.1, `ActorContext` 4.1, BOTH seeders 5.1/5.2 cut to `Operator`. The bootstrap `User` / `users` table / `users` provider / `web`-default-guard are the **last** `User`-isms, all removed at **6.1**. After 5.2 no seeder/auth-context code references `User`.
- **Framework/vendor getters typed to a CONTRACT** → narrow off-contract methods with a real `instanceof`, never `assert()`/`@var`; `Guard::id()` is `int|string|null` → real `(int)` cast.
- **Cross-engine discipline:** full suite on `postgres:17` for DB-touching tasks; confirm live `DRIVER=pgsql`; `pg_isready` busy-poll via `docker exec` (foreground `sleep` blocked); remove container. **log.md:** append ONLY via `scripts/memlog.sh`; hot.md ≤550 words.
