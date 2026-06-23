---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-24
---

# Hot Cache

## Last Updated
**2026-06-24 (`operator-console-parties-membership` — 16/16 tasks DONE, CHANGE_COMPLETE).** Final gate 8.2 passed: the demand-side **Parties membership operator console** is functionally complete and green, awaiting human review → archive → merge (GUIDE §2.7; do NOT archive/merge — humans do that). What shipped across groups 1–8: a read-only `ProfileResource` (list + approval-queue tabs + infolist; state badge via the `ProfileState` cast — no `Parties\Enums` import, design D2); a write-through `CreateProfile` surface (Customer+Club Selects → `app(CreateProfile)->handle()`, `DuplicateProfileForClub` surfaced on `club_id`); the full Profile FSM as form-less `lifecycleAction` header verbs on `ViewProfile` (approve/decline → activate/suspend/reactivate → lapse/renew/cancel/deactivate, each `->visible()`-gated to its from-state via the parametric `stateIs()`); the Account FSM (suspend/reactivate/close) on `ViewCustomer` (routed by nested `->account?->id`); full EN/IT i18n (DEC-127 EN-fallback for `label`/`plural_label`, `*.club` loanword carve-out) guarded by `ProfileConsoleI18nTest`; and the PG17 closing-chain proof `ProfileMembershipChainTest` (one self-contained `it` driving the whole console through the pages, composed across the orthogonal Profile + Account FSMs).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (8.2 final gate, SQLite):** full suite `php -d memory_limit=-1 vendor/bin/pest` → **1726/1726** (9425 assertions) · `php -d memory_limit=-1 vendor/bin/phpstan analyse` (max) → **0** · `vendor/bin/pint --test` clean · `openspec validate operator-console-parties-membership --strict` valid. PG17 ritual was run at 8.1 (ProfileMembershipChainTest 1/1, 98 assertions on PostgreSQL 17).
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (OOMs at 128 MB; lesson 2026-06-20).

## Active Change & Next Task
- **`operator-console-parties-membership` is COMPLETE (16/16).** This iteration replied `<promise>CHANGE_COMPLETE</promise>`. Nothing remains on THIS change — it awaits a human to review/merge branch `ralph/operator-console-parties-membership` and `openspec archive operator-console-parties-membership --yes`. Knowledge-promotion (confirmation dates = the archive-dir date) happens at archive time, not now.
- **Scope audit confirmed (design D8):** NO new `Parties\Actions\*` (empty `git diff main...HEAD`) → `SupplyLifecycleChainTest` whitelist (36 transitions = 42 files − 6 `Create*`) unchanged. NO new ADR / `decisions/` change. Branch touches only `app/Modules/OperatorPanel/**` (+`ViewCustomer`), `lang/**`, tests, memory.

## Blockers & Decisions Needed
- **No blocker.** Deferred seams stay out of scope (design Non-Goals): `Applied→WaitingList` (no writer), activation capacity cap (Module-A seam — activation ships uncapped), `MembershipFeePaid`/renewal trigger (Module-E seam), Hold→`suspended` coupling, Producer-Portal TanStack UI, Customer segments.

## Open Patterns
- **Operator-console slice = the reusable shape** now proven end-to-end (this change's `progress.md` Codebase Patterns, consolidated): read-only Resource (state badge via cast, relation columns via typed closure, approval tabs via `getTabs()`) → write-through create page (`OperatorConsoleCreateRecord` + model-backed Select helpers, `DatabaseMigrations`) → form-less `lifecycleAction` header verbs gated by a parametric `stateIs()`/`accountStatusIs()` (no enum import) → dual-block i18n guard → composed-FSM closing chain on PG17. Reference any `Illegal*Transition` in prose only (Pint import trap, lesson 2026-06-20).
