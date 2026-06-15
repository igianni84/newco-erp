---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 14:10 (ralph iter — task 4.2 done, committing green).** End-to-end recorder proof (design D5): proved the operator-action pipeline composes — an `Operator` authenticated on the `operator` guard → the `ActorContext` seam (wired 4.1) → `DomainEventRecorder::record()` → a `domain_events` row with (`actor_role='newco_ops'`, `actor_id=` the operator id). **Test-only** (no app/config/migration change). New `tests/Feature/Modules/OperatorPanel/OperatorActorContextWiringTest.php` (2 tests/6 assert): a `recordWithResolvedActor()` helper resolves the singleton `ActorContext` *after* `actingAs` (exercises lazy per-call resolution) and records a **synthetic** `'OperatorActorContextDemoRecorded'` event (module `'platform'`, no consumer → afterCommit no-op) inside `DB::transaction()`. Test 1 (`actingAs(Operator,'operator')`): re-read row → `actor_role===NewcoOps` + raw stored token `'newco_ops'` + `actor_id->toEqual(operator key)`. Test 2 (no auth, guest precondition): `System`/null — envelope tracks the actual auth state, not a hardcoded role. Used `DatabaseMigrations` (not RefreshDatabase) so the recorder's level-0 guard keeps the txn wrapper load-bearing.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission 8.0.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/operator-auth-foundation`: **full suite 349/349 on SQLite AND 349/349 on PostgreSQL 17** (live `DRIVER=pgsql / SERVER=17.10` printed, container removed). phpstan **0 @ max**, pint clean, `openspec validate --strict` valid.
- Commit pending this iter: **4.2** (`feat(operator-auth-foundation): 4.2 end-to-end recorder proof`). 1.1+2.1+2.2+2.3+3.1+4.1+4.2 done (**7 of 12**); 5.1 next.

## Active Change & Next Task
- **ACTIVE: `operator-auth-foundation`** — 12 tasks; **7 done, 5 remain, no blocker.**
- **Next: 5.1 `RoleSeeder`** (design D4). Seed `Creator`/`Reviewer`/`Approver` on guard `operator` as **bare** roles (`firstOrCreate`, **NO permissions**, no role→capability map — mechanism not policy), idempotent. New test `tests/Feature/Modules/OperatorPanel/RoleSeederTest.php`: run seeder → `Role::where('guard_name','operator')->pluck('name')->sort()->values()->all() === ['Approver','Creator','Reviewer']`; every role's `permissions` empty; re-run → `Role::count()===3` (no dupes). **Confirm the spatie `Role` FQCN (`Spatie\Permission\Models\Role`) + `guard_name` usage in `vendor/` first.** Verify on PG17. Safe to relaunch `./ralph.sh`.

## Blockers & Decisions Needed
- **None.** 2.2 spec-vs-arch carve-out resolved (Option A, ADR `decisions/2026-06-15-auth-principal-table-naming.md`, design D7).
- Standing founder decisions: 2FA opt-in INCLUDED (enforcement→security review); User→Operator replacement; bootstrap operator holds all 3 roles.
- **Open ADR gates (don't step in):** queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack/SPA/Fortify-Sanctum (Module S) · authority-tier RBAC (`feedback_prd_rr_approval`) · SoD floor + lifecycle FSM (`catalog-lifecycle-approval`) · MFA enforcement (security review).

## Open Patterns
- **End-to-end actor-provenance proof (4.2):** feed the recorder values RESOLVED from `ActorContext` (`$ctx->role()`/`$ctx->actorId()`), never literals, under `actingAs($principal,'<guard>')` + `DatabaseMigrations` (level-0 guard keeps the `DB::transaction()` wrapper load-bearing); synthetic event name (no consumer → clean no-op); assert BOTH the model enum AND the raw stored token; `actor_id` uncast → `toEqual` (spans engines). Customer/producer guard slices copy verbatim.
- **Reading an operator id off the guard:** `check()` + `id()` are both on the `Guard` contract → no `instanceof`; but `Guard::id()` is `int|string|null` → real `(int)` cast, never `@var`/`assert()`.
- **Framework/vendor getters typed to a CONTRACT** → narrow off-contract methods with a real `instanceof`, never `assert()`/`@var`; `toBeInstanceOf` does NOT narrow the next `->and()`.
- **Cutover ordering:** panel guard flipped 3.1, `ActorContext` 4.1, seeder at 5.2 — auth tests before 5.2 use a factory `Operator`, NOT `OperatorSeeder` (still `User`).
- **Cross-engine discipline:** full suite on `postgres:17` for DB-touching tasks; confirm live `DRIVER=pgsql`; `pg_isready` busy-poll (foreground `sleep` blocked); remove container. **log.md:** append ONLY via `scripts/memlog.sh`; hot.md ≤550 words.
