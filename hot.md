---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 13:59 (ralph iter — task 4.1 done, committing green).** Wired the `App\Platform\Events\ActorContext` seam to the operator guard (design D5). It now resolves **lazily, per call**: (1) an active `runAs()` override; else (2) `Auth::guard('operator')->check()` → (`ActorRole::NewcoOps`, `(int) ->id()`); else (3) (`ActorRole::System`, null). Guard read **by name**; imports only the `Auth` facade + same-namespace `ActorRole` (nothing from OperatorPanel) → `ModuleBoundariesTest` green **unamended**. Override is a nullable `?ActorRole $overrideRole` distinct from the default (so steps 2/3 evaluate at query time, never memoised); `runAs` save/restore + nesting preserved. Public API unchanged → 8 Catalog callers + the `AppServiceProvider:26` singleton untouched. `ActorContextTest` rewritten (8 tests/28 assert, +`RefreshDatabase`). Green; ready for 4.2.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission 8.0.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/operator-auth-foundation`: **full suite 347/347 on SQLite AND 347/347 on PostgreSQL 17** (live `DRIVER=pgsql / SERVER=17.10` printed, container removed). phpstan **0 @ max**, pint clean, `openspec validate --strict` valid.
- Commit pending this iter: **4.1** (`feat(operator-auth-foundation): 4.1 wire ActorContext to the operator guard`). 1.1 + 2.1 + 2.2 + 2.3 + 3.1 + 4.1 done (**6 of 12**); 4.2 next.

## Active Change & Next Task
- **ACTIVE: `operator-auth-foundation`** — 12 tasks; **6 done, 6 remain, no blocker.**
- **Next: 4.2** — end-to-end recorder proof (design D5). New test `tests/Feature/Modules/OperatorPanel/OperatorActorContextWiringTest.php`: `actingAs(Operator::factory()->create(), 'operator')`, then inside a `DB::transaction` call `DomainEventRecorder::record()` with the **`ActorContext`-resolved** role/id and a **synthetic** demo event name (reserve verbatim spec event names for module changes); assert the `domain_events` row's `actor_role = 'newco_ops'` / `actor_id =` the operator id. **Verify `DomainEventRecorder::record()`'s exact arg order in `app/Platform/Events/` first.** Verify on PG17. Safe to relaunch `./ralph.sh`.

## Blockers & Decisions Needed
- **None.** 2.2 spec-vs-arch carve-out resolved (Option A, ADR `decisions/2026-06-15-auth-principal-table-naming.md`, design D7).
- Standing founder decisions: 2FA opt-in INCLUDED (enforcement→security review); User→Operator replacement; bootstrap operator holds all 3 roles.
- **Open ADR gates (don't step in):** queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack/SPA/Fortify-Sanctum (Module S) · authority-tier RBAC (`feedback_prd_rr_approval`) · SoD floor + lifecycle FSM (`catalog-lifecycle-approval`) · MFA enforcement (security review).

## Open Patterns
- **Reading an operator id off the guard (4.1):** `check()` + `id()` are BOTH on the `Guard` contract → no `instanceof` narrowing; but `Guard::id()` is `int|string|null` → coerce with a real `(int)` cast (`$id===null?null:(int)$id`), never `@var`/`assert()`. Customer/producer guard wiring copies this verbatim.
- **Framework/vendor getters typed to a CONTRACT, not the concrete class** — narrow off-contract methods with a real `instanceof` (`expect($x instanceof Concrete && $x->method())->toBeTrue()`), never `assert()`/`@var`. Seen for `getProvider()`/`getModel()` + `getMultiFactorAuthenticationProviders()`→`isRecoverable()`. `toBeInstanceOf` does NOT narrow the next `->and()`.
- **Cutover ordering:** panel guard flipped at 3.1, `ActorContext` at 4.1, seeder at 5.2 — tests authenticating in between use a factory `Operator`, NOT `OperatorSeeder` (still `User`).
- **Cross-engine discipline:** full suite on `postgres:17` for DB-touching tasks; confirm live `DRIVER=pgsql` (don't just echo); `pg_isready` busy-poll (foreground `sleep` blocked); remove container. **log.md:** append ONLY via `scripts/memlog.sh`; hot.md ≤550 words.
