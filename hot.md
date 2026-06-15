---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 (ralph iter — `operator-auth-foundation` task 2.1 GREEN).** Added the `operators` table migration (`2026_06_15_100000_create_operators_table.php`) — the operator login principal, built **alongside** the bootstrap `users` table (cutover discipline D1; `users` removed only at cleanup 6.1). Columns: `id`, `name`, `email` unique, `email_verified_at` nullable (framework-compat/unused), `password`, `app_authentication_secret` (text, null), `app_authentication_recovery_codes` (text, null), `rememberToken()`, `timestamps()`. 2FA column names verified against Filament's MFA concern traits in `vendor/` first. Default 0001 migration (`users`/`password_reset_tokens`/`sessions`) left intact. New 4-test smoke test. 2.1 flipped; **10 tasks remain**.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission 8.0.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/operator-auth-foundation`: suite **328/328** green (SQLite) · **328/328 on PG17** (migrate:fresh clean, `operators` column listing confirmed, container removed) · phpstan **0 @ max** · pint clean. `openspec validate --strict` valid.
- `composer` unchanged this iter (authorised dep landed in 1.1). Published spatie migration stays phpstan-excluded (`phpstan.neon` glob); the new `operators` migration IS analysed (clean — plain auth table).

## Active Change & Next Task
- **ACTIVE: `operator-auth-foundation`** — 12 tasks / 6 groups; **1.1 + 2.1 done, 10 left**.
- **Next: 2.2 — `Operator` model + factory.** `App\Modules\OperatorPanel\Models\Operator extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery`; `use HasRoles, Notifiable`; `casts()`: `password=>hashed`, `email_verified_at=>datetime`, `app_authentication_secret=>encrypted`, `app_authentication_recovery_codes=>encrypted:array`; the four MFA accessors + `getAppAuthenticationHolderName()`; `canAccessPanel()` → `true`. Add `OperatorFactory`. Do NOT remove `User`/`UserFactory` yet. Contract signatures confirmed in `vendor/.../MultiFactor/App/Contracts/`; the `Concerns/InteractsWith*` traits satisfy both contracts + merge the encrypted casts (model may `use` them — see Open Patterns).
- Remaining: 2.2 model+factory → 2.3 `operator` guard → 3.1 panel cutover (authGuard/passwordReset/2FA) → 4.1/4.2 ActorContext wiring (guard BY NAME, no `Operator` import; arch test unchanged) → 5.1/5.2 RoleSeeder+OperatorSeeder → 6.1 remove `User`/finish config/auth → 6.2 docs → 6.3 cross-engine close.

## Blockers & Decisions Needed
- None blocking. Founder decisions standing: **2FA opt-in INCLUDED** (enforcement → security-review gate); **User→Operator replacement**; bootstrap operator holds all 3 roles.
- **Open ADR gates (don't step in):** queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack + SPA session mechanics + customer/producer guards + Fortify/Sanctum (Module S). Authority-tier RBAC policy → `feedback_prd_rr_approval`. SoD-floor transition + Draft→Reviewed→Active→Retired FSM → `catalog-lifecycle-approval`. MFA enforcement → security review.

## Open Patterns
- **2FA column names are fixed by Filament** — `Concerns/InteractsWith*`: `app_authentication_secret` (cast `encrypted`) + `app_authentication_recovery_codes` (cast `encrypted:array`), both merge-hidden; the concern traits satisfy the contracts.
- **Pint pulls `{@see \FQCN}` docblock refs into real `use` imports** and keeps them; phpstan-max does NOT flag docblock-only imports.
- **Vendor-published migration vs phpstan-max:** exclude the single file in `phpstan.neon`; never `@phpstan-ignore`/cast the vendor file.
- **Cross-engine discipline:** SQLite-green necessary, NEVER sufficient — full suite on `postgres:17` for any DB task; print `DRIVER=pgsql`; busy-poll `pg_isready` (no foreground `sleep`); remove container.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); hot.md ≤550 words.
