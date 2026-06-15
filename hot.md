---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 (ralph iter — `operator-auth-foundation` task 1.1 GREEN).** Installed `spatie/laravel-permission:^8.0` (resolved **8.0.0**), the operator RBAC mechanism (design D4, authorised by the identity/auth ADR). Published `config/permission.php` (**teams off** — operator-scoping keys on `guard_name`) + the `create_permission_tables` migration; left `models.*` defaults. Added a 5-table migration-smoke test. Recorded the pin in `docs/development.md` (table + note + the cross-checked test list). 1.1 checkbox flipped; **11 tasks remain**.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · **spatie/laravel-permission 8.0.0 (NEW)** · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/operator-auth-foundation`: suite **324/324** green (SQLite) · **324/324 on PG17** (migrate:fresh clean, container removed) · phpstan **0 @ max** · pint clean. `openspec validate --strict` valid.
- `composer.json`/`composer.lock` **changed** (the one authorised new dep — expected for this change). The published spatie migration is **excluded from phpstan** (`phpstan.neon` excludePaths, glob `*_create_permission_tables.php`) — it reads `config()` (mixed); vendor code, never hand-edited.

## Active Change & Next Task
- **ACTIVE: `operator-auth-foundation`** — 12 tasks / 6 groups; **1.1 done, 11 left**.
- **Next: 2.1 — `operators` table migration.** Build it **alongside** the existing `users` table (cutover discipline D1): new `…_create_operators_table` with `app_authentication_secret` + `app_authentication_recovery_codes` (text, nullable) for the Filament MFA contracts; do NOT touch `users`/`User` until cleanup task 6.1. Verify column names against the Filament MFA contracts in `vendor/` first; **verify on PG17**.
- Remaining order: 2.1 operators table → 2.2 `Operator` model+factory → 2.3 `operator` guard in `config/auth.php` → 3.1 panel cutover (authGuard/passwordReset/opt-in 2FA) → 4.1/4.2 ActorContext wiring (read guard BY NAME, no `Operator` import; arch test unchanged) → 5.1/5.2 RoleSeeder + OperatorSeeder → 6.1 remove `User` + finish config/auth → 6.2 docs → 6.3 cross-engine close.

## Blockers & Decisions Needed
- None blocking. Founder decisions standing: **2FA opt-in INCLUDED** (enforcement → security-review gate); **User→Operator replacement**; bootstrap operator holds all 3 roles.
- **Open ADR gates (don't step in):** queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack + SPA session mechanics + customer/producer guards + Fortify/Sanctum (Module S). Authority-tier RBAC policy → `feedback_prd_rr_approval`. SoD-floor transition + Draft→Reviewed→Active→Retired FSM → `catalog-lifecycle-approval`. MFA enforcement → security review.

## Open Patterns
- **spatie publish tags:** `laravel-package-tools` strips the `laravel-` prefix → `permission-config` / `permission-migrations`.
- **Vendor-published migration vs phpstan-max:** exclude the single file in `phpstan.neon`; never `@phpstan-ignore`/cast the vendor file.
- **New dep → docs:** row + dated note in `docs/development.md` + add to `DevelopmentDocsTest` package list (pennant/spatie precedent).
- **Cross-engine discipline:** SQLite-green necessary, NEVER sufficient — full suite on `postgres:17` for any DB task; print `DRIVER=pgsql`; remove the container.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); hot.md ≤550 words.
