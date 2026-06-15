---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 (interactive ADR session) — Identity/auth gate CLOSED.** grill-with-docs, 5 questions all founder-confirmed → `decisions/2026-06-15-identity-auth.md` (active). Decision: **first-party for all actors**, no external IdP at launch (Laravel **Fortify + Sanctum**, EU-resident); **auth is a platform foundation** whose principal **references the Module K party by id** (Module K stays pure identity — a Customer is never a login); **multi-guard** (operator = Filament session; customer/producer = Sanctum SPA); ActorContext resolves `actor_role` + `actor_id` = party/operator id (substrate contract `{newco_ops|producer|customer|system}`); operator RBAC = **`spatie/laravel-permission`** (operator-scoped, DB-backed), the Creator→Reviewer→Approver separation-of-duties floor enforced as **module logic**, never a permission. Supabase explicitly evaluated + rejected (backend reading breaks PG + monolith ADRs + invariants; auth-only = strictly-worse external IdP). Deferred to the Module S / TanStack gate: SPA session mechanics (cookie vs token) + producer-portal login activation. CONTEXT.md += `## Identity & Access` (Operator, Authentication principal); INDEX.md updated. **No code changed — docs/ADR only.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- `main` @ `fe50776`: suite **320/320** green · phpstan **0 @ max** · pint clean · in sync with `origin/main`. Unchanged this session (ADR only — no migrations/code).
- `spatie/laravel-permission` **not yet installed** — added at the `catalog-lifecycle-approval` implementation (pin version in `docs/development.md` per the stack-ADR discipline).

## Active Change & Next Task
- **NO active change** (`openspec list` → none in flight).
- **Identity/auth ADR DONE → `catalog-lifecycle-approval` is UNBLOCKED.** Next: `/spec-to-change` for it — Draft→Reviewed→Active→Retired FSM + approval workflow + the `*Activated`/`*Retired` events `catalog-product-spine` deferred. It consumes: operator auth, operator roles (spatie), and the no-self-approval floor as transition logic.
- **Protected-file action pending (human):** Giovanni removes the `Identity/auth` row from CLAUDE.md "Open stack decisions" table by hand (exact line given in chat).

## Blockers & Decisions Needed
- **Identity/auth: RESOLVED** — no longer a blocker.
- **Open ADR gates (do not step into):** queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack + SPA session mechanics + producer-portal login (Module S). Operational gates tracked in INDEX.md (secrets, observability, PCI, security review).

## Open Patterns
- **grill-with-docs close:** resolved terms → CONTEXT.md inline (Operator, Authentication principal done); gate-closing ADR → `decisions/` + INDEX row + drop from open-decisions; the protected CLAUDE.md gate table is edited by the human, not the agent.
- **Cross-engine discipline:** SQLite-green is necessary, NEVER sufficient — run the full suite on `postgres:17` for any DB/jsonb test; print `DRIVER=pgsql`; remove the container.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB); hot.md ≤550 words.
