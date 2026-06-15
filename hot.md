---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 13:23 (interactive — resolved the `operator-auth-foundation` task 2.2 HUMAN_NEEDED blocker, committed).** Founder chose **Option A**: auth-principal models are exempt from the module-table-prefix arch convention. Built the resolution (test skip + truth-spec MODIFIED delta + design D7 + new ADR), proved it green on **both engines**, flipped 2.2, committed. The loop is **stopped** and ready to relaunch from a clean green state.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission 8.0.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/operator-auth-foundation`: **full suite 336/336 on SQLite AND 336/336 on PostgreSQL 17** (DRIVER=pgsql printed, container removed). phpstan **0 @ max**, pint clean, `openspec validate --strict` valid.
- Last commit: **2.2** (`feat(operator-auth-foundation): 2.2 Operator model + factory + auth-principal table-naming carve-out`). 1.1 + 2.1 + 2.2 done (**3 of 12**); 2.3 next.

## Active Change & Next Task
- **ACTIVE: `operator-auth-foundation`** — 12 tasks; **3 done, 9 remain, no blocker.**
- **Next: 2.3** — `config/auth.php`: add the `operator` session guard alongside `web` (provider = `Operator`; `operators` password broker), keep `web`/`users`/`User` + the app default guard unchanged until cleanup 6.1. Safe to relaunch `./ralph.sh`.

## Blockers & Decisions Needed
- **None.** The 2.2 spec-vs-arch-invariant collision is resolved (Option A, founder-authorised 2026-06-15): a model implementing `Illuminate\Contracts\Auth\Authenticatable` is exempt from the `operator_panel_`-prefix rule in `ModulePersistenceConventionsTest`. Recorded in ADR `decisions/2026-06-15-auth-principal-table-naming.md`, design D7, and the `module-architecture` MODIFIED delta (truth spec updates on archive). Invariant 10 substance intact (`ModuleBoundariesTest` unchanged).
- Standing founder decisions: 2FA opt-in INCLUDED (enforcement→security review); User→Operator replacement; bootstrap operator holds all 3 roles.
- **Open ADR gates (don't step in):** queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack/SPA/Fortify-Sanctum (Module S) · authority-tier RBAC (`feedback_prd_rr_approval`) · SoD floor + lifecycle FSM (`catalog-lifecycle-approval`) · MFA enforcement (security review).

## Open Patterns
- **NEW PATTERN (now an ADR):** auth-principal models (`Authenticatable`) keep the framework's flat auth-table name (`Operator`→`operators`) and are skipped by the module-prefix arch test — but **every non-principal module model still needs its module prefix**. Forward-binds future `customer-identity`/`producer-identity` principals.
- **2FA column names fixed by Filament** concern traits: `app_authentication_secret` (`encrypted`) + `app_authentication_recovery_codes` (`encrypted:array`), both merge-hidden. Hand-rolled accessors match vendor signatures (`#[SensitiveParameter]`, `save()` in setters).
- **larastan + `encrypted:array`:** add `@property array<string>|null` or it infers the schema `string` and the `?array` return mismatches at max.
- **Pint `fully_qualified_strict_types`** pulls `{@see \FQCN}` into real `use` imports — for a soon-deleted class (`App\Models\User`) use plain backtick text, not `{@see}`, or task 6.1's grep + the import linger.
- **Cross-engine discipline:** SQLite-green necessary, never sufficient — full suite on `postgres:17` for DB tasks; `pg_isready` busy-poll; remove container.
- **log.md:** append ONLY via `scripts/memlog.sh`; hot.md ≤550 words.
