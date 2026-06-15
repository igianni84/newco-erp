---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 14:18 (ralph iter — task 5.1 done, committing green).** New `Database\Seeders\RoleSeeder` (design D4): seeds the three operator authority-tier roles `Creator`/`Reviewer`/`Approver` as **bare** roles on the `operator` guard — the RBAC **mechanism** only (NO permissions, NO role→capability map; policy + SoD floor deferred). Idempotent: `Role::firstOrCreate(['name'=>…,'guard_name'=>'operator'])` (bypasses spatie's static-`create()` `RoleAlreadyExists` throw) + `forgetCachedPermissions()` reset at top. **Scope-clean** — created only the seeder + its test; did NOT touch `DatabaseSeeder`/`OperatorSeeder` (that is 5.2). New `tests/Feature/Modules/OperatorPanel/RoleSeederTest.php` (3 tests/6 assert): sorted names `===['Approver','Creator','Reviewer']`; each role `permissions->toHaveCount(0)`; double-seed → `count()===3`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission 8.0.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/operator-auth-foundation`: **full suite 352/352 on SQLite AND 352/352 on PostgreSQL 17** (live `DRIVER=pgsql / SERVER=17.10` printed, container removed). phpstan **0 @ max**, pint clean, `openspec validate --strict` valid.
- Commit pending this iter: **5.1** (`feat(operator-auth-foundation): 5.1 RoleSeeder`). 1.1+2.1+2.2+2.3+3.1+4.1+4.2+5.1 done (**8 of 12**); 5.2 next.

## Active Change & Next Task
- **ACTIVE: `operator-auth-foundation`** — 12 tasks; **8 done, 4 remain, no blocker.**
- **Next: 5.2 `OperatorSeeder` + `DatabaseSeeder` → `Operator`** (design D6). Repurpose `OperatorSeeder` to `updateOrCreate` an **`Operator`** (not `User`) from the same `OPERATOR_NAME/EMAIL/PASSWORD` env vars (preserve names; never committed), idempotent; assign it all three roles (`Creator`/`Reviewer`/`Approver`). `DatabaseSeeder` runs `RoleSeeder` **then** `OperatorSeeder` and drops the `Test User` factory line. **MODIFY** `tests/Feature/OperatorSeederTest.php` (currently asserts a `User` via `User::query()`) → assert an `Operator` with the env email, idempotent on re-run, holding the three roles. Confirm `HasRoles::assignRole` + the role-resolution path in `vendor/` first. Verify on PG17. Safe to relaunch `./ralph.sh`.

## Blockers & Decisions Needed
- **None.** 2.2 spec-vs-arch carve-out resolved (Option A, ADR `decisions/2026-06-15-auth-principal-table-naming.md`, design D7).
- Standing founder decisions: 2FA opt-in INCLUDED (enforcement→security review); User→Operator replacement; bootstrap operator holds all 3 roles.
- **Open ADR gates (don't step in):** queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack/SPA/Fortify-Sanctum (Module S) · authority-tier RBAC (`feedback_prd_rr_approval`) · SoD floor + lifecycle FSM (`catalog-lifecycle-approval`) · MFA enforcement (security review).

## Open Patterns
- **Spatie role/permission seeding (5.1):** `Role::firstOrCreate(['name'=>…,'guard_name'=>'<guard>'])` is idempotent (bypasses the static-`create()` throw); **always pass `guard_name` explicitly** (spatie defaults it from `config('auth.defaults.guard')`=`web` until 6.1); `forgetCachedPermissions()` at seeder top forward-protects the chained assign in 5.2; role names are spec data identifiers (no i18n); assert empty `$role->permissions` with `->toHaveCount(0)`. Customer/producer guard slices copy verbatim.
- **Cutover ordering:** panel guard 3.1, `ActorContext` 4.1, RoleSeeder 5.1; `DatabaseSeeder`/`OperatorSeeder` still `User`-based until **5.2**, `User`/`users` removed at **6.1**. Auth/seed tests in this window build the principal with `Operator::factory()`, never the (still-`User`) seeder.
- **Framework/vendor getters typed to a CONTRACT** → narrow off-contract methods with a real `instanceof`, never `assert()`/`@var`; `Guard::id()` is `int|string|null` → real `(int)` cast.
- **Cross-engine discipline:** full suite on `postgres:17` for DB-touching tasks; confirm live `DRIVER=pgsql`; `pg_isready` busy-poll (foreground `sleep` blocked); remove container. **log.md:** append ONLY via `scripts/memlog.sh`; hot.md ≤550 words.
