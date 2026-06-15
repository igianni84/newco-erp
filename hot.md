---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 13:39 (ralph iter — task 2.3 done, committing green).** Added the `operator` **session guard** + `operators` provider + `operators` password broker to `config/auth.php`, ALONGSIDE the bootstrap `web`/`users`/`User` shell (untouched until cleanup 6.1; cutover discipline D1). New `OperatorGuardTest` (7/20) pins the wiring, proves `Auth::guard('operator')` authenticates an `Operator`, and confirms the `web` guard + app default guard are unchanged. Green; ready for 3.1.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission 8.0.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/operator-auth-foundation`: **full suite 343/343 on SQLite AND 343/343 on PostgreSQL 17** (DRIVER=pgsql printed, container removed). phpstan **0 @ max**, pint clean, `openspec validate --strict` valid.
- Last commit: **2.3** (`feat(operator-auth-foundation): 2.3 config/auth.php operator guard alongside web`). 1.1 + 2.1 + 2.2 + 2.3 done (**4 of 12**); 3.1 next.

## Active Change & Next Task
- **ACTIVE: `operator-auth-foundation`** — 12 tasks; **4 done, 8 remain, no blocker.**
- **Next: 3.1** — `AdminPanelProvider`: `->authGuard('operator')`, keep `->login()`, add `->passwordReset()`, add `->multiFactorAuthentication([AppAuthentication::make()…], isRequired: false)` with recovery codes. **Verify the exact MFA/recovery builder + `isRequired` flag in `vendor/filament` first.** MODIFY `tests/Feature/OperatorPanelTest.php`: `actingAs(Operator::factory()->create(), 'operator')` (was `User::factory()`); assert guest→login redirect, authed dashboard, NO registration route. **Verify on PG17.** Safe to relaunch `./ralph.sh`.

## Blockers & Decisions Needed
- **None.** 2.2 spec-vs-arch carve-out resolved (Option A, ADR `decisions/2026-06-15-auth-principal-table-naming.md`, design D7).
- Standing founder decisions: 2FA opt-in INCLUDED (enforcement→security review); User→Operator replacement; bootstrap operator holds all 3 roles.
- **Open ADR gates (don't step in):** queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack/SPA/Fortify-Sanctum (Module S) · authority-tier RBAC (`feedback_prd_rr_approval`) · SoD floor + lifecycle FSM (`catalog-lifecycle-approval`) · MFA enforcement (security review).

## Open Patterns
- **NEW (Codebase Pattern):** `Auth::guard($n)` / `Auth::createUserProvider($n)` are typed to the CONTRACT (`Guard|StatefulGuard` / `?UserProvider`), NOT concrete `SessionGuard`/`EloquentUserProvider` — at phpstan max you cannot call `getProvider()`/`getModel()` on them (don't `assert()`/`@var`). Assert via `toBeInstanceOf` + prove model wiring via `config(...)` + functional `actingAs($m,'<guard>')` → `user() instanceof <Model>`. Forward-applies to 4.1 + customer/producer guard tests.
- **config/auth.php is platform glue, outside arch-test scope** (D1) — may import the `Operator` model as a provider class-string. `AUTH_MODEL` is unset everywhere, so both providers keep distinct defaults until 6.1 removes the `users` one.
- **Auth-principal models EXEMPT from the module-table-prefix arch test** (design D7; ADR) — non-principal module models still need their prefix.
- **2FA cols fixed by Filament concern traits:** `app_authentication_secret` (`encrypted`) + `app_authentication_recovery_codes` (`encrypted:array`); larastan needs `@property array<string>|null` on the latter.
- **Cross-engine discipline:** full suite on `postgres:17` for DB-touching tasks; `pg_isready` busy-poll; remove container. **log.md:** append ONLY via `scripts/memlog.sh`; hot.md ≤550 words.
