---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 13:50 (ralph iter — task 3.1 done, committing green).** Cut the `/admin` Filament panel over to the **operator** session guard: `AdminPanelProvider` now `->authGuard('operator')` + keeps `->login()` + `->passwordReset()` + opt-in `->multiFactorAuthentication([AppAuthentication::make()->recoverable()], isRequired:false)`. No `->registration()`/`->emailVerification()` (scope guard). `tests/Feature/OperatorPanelTest.php` rewritten for the cutover (7 tests/25 assert) — builds the principal via `Operator::factory()` (NOT `OperatorSeeder`, still `User` until 5.2) and logs in through the real Filament `Login` page on the `operator` guard. Green; ready for 4.1.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission 8.0.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/operator-auth-foundation`: **full suite 346/346 on SQLite AND 346/346 on PostgreSQL 17** (DRIVER=pgsql printed, container removed). phpstan **0 @ max**, pint clean, `openspec validate --strict` valid.
- Last commit (pending this iter): **3.1** (`feat(operator-auth-foundation): 3.1 AdminPanelProvider → operator guard + reset + opt-in 2FA`). 1.1 + 2.1 + 2.2 + 2.3 + 3.1 done (**5 of 12**); 4.1 next.

## Active Change & Next Task
- **ACTIVE: `operator-auth-foundation`** — 12 tasks; **5 done, 7 remain, no blocker.**
- **Next: 4.1** — wire `ActorContext` (design D5): resolve **lazily per call** — (1) run-as override wins, else (2) `Auth::guard('operator')->check()` → (`ActorRole::NewcoOps`, `Auth::guard('operator')->id()`), else (3) (`ActorRole::System`, null). Read the guard **by name**; import **nothing** from `App\Modules\OperatorPanel`; `tests/Architecture/ModuleBoundariesTest.php` must stay green **unamended**. Make the override a nullable field distinct from the default so steps 2/3 evaluate at query time (not memoised). **REWRITE** the gate-safe scenario in `tests/Feature/Platform/Events/ActorContextTest.php` (was `actingAs(User…)`→`system`; now `actingAs(Operator…, 'operator')`→`NewcoOps`+id, no-auth→`System`, run-as overrides an operator session). **Verify on PG17.** Safe to relaunch `./ralph.sh`.

## Blockers & Decisions Needed
- **None.** 2.2 spec-vs-arch carve-out resolved (Option A, ADR `decisions/2026-06-15-auth-principal-table-naming.md`, design D7).
- Standing founder decisions: 2FA opt-in INCLUDED (enforcement→security review); User→Operator replacement; bootstrap operator holds all 3 roles.
- **Open ADR gates (don't step in):** queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack/SPA/Fortify-Sanctum (Module S) · authority-tier RBAC (`feedback_prd_rr_approval`) · SoD floor + lifecycle FSM (`catalog-lifecycle-approval`) · MFA enforcement (security review).

## Open Patterns
- **Framework/vendor getters typed to a CONTRACT, not the concrete class** — narrow with a real `instanceof` (`expect($x instanceof Concrete && $x->method())->toBeTrue()`), never `assert()`/`@var` (phpstan-max). Seen for `Auth::guard()`/`createUserProvider()` AND `Panel::getMultiFactorAuthenticationProviders()` (→`AppAuthentication::isRecoverable()` off-contract). `toBeInstanceOf` does NOT narrow the next `->and()`. Applies to 4.1's guard read.
- **Cutover ordering:** panel guard flips at 3.1, seeder at 5.2 — tests authenticating in between use a factory `Operator`, not `OperatorSeeder` (still `User`).
- **Filament auth route names:** `filament.admin.auth.{login|password-reset.request|register}`; `register` exists only if `hasRegistration()`.
- **Cross-engine discipline:** full suite on `postgres:17` for DB-touching tasks; `pg_isready` busy-poll; remove container. **log.md:** append ONLY via `scripts/memlog.sh`; hot.md ≤550 words.
