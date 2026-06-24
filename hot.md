---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-24
---

# Hot Cache

## Last Updated
**2026-06-24 — `operator-console-parties-membership` CLOSED & PUSHED (GUIDE §2.7 ritual complete).** `main` is synced with `origin/main` (`…→e5655f6`); the `ralph/operator-console-parties-membership` branch is merged and deleted. The demand-side **Parties membership operator console** was reviewed, verified, merged to `main` (`a2d9172 merge: …`, `--no-ff`) and **archived** (`openspec/changes/archive/2026-06-24-operator-console-parties-membership/`; delta folded into the living `openspec/specs/operator-console/spec.md` — **4 added + 2 modified** requirements). What shipped (groups 1–8): read-only `ProfileResource` (list + approval-queue tabs + infolist, state badge via cast, no `Parties\Enums` import — D2); write-through `CreateProfile` (Customer+Club Selects → `app(CreateProfile)->handle()`, `DuplicateProfileForClub` on `club_id`); full Profile FSM as form-less `lifecycleAction` header verbs on `ViewProfile` (approve/decline → activate/suspend/reactivate → lapse/renew/cancel/deactivate, each `->visible()`-gated via parametric `stateIs()`); Account FSM (suspend/reactivate/close) on `ViewCustomer` (routed by nullable `->account?->id`); EN/IT i18n (DEC-127 fallback, `*.club` loanword) guarded by `ProfileConsoleI18nTest`; PG17 closing-chain `ProfileMembershipChainTest`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (close ritual, real PostgreSQL 17 via `postgres:17` Docker):** full suite `php -d memory_limit=-1 vendor/bin/pest` → **1726/1726** (9425 assertions) · PHPStan max 0 · Pint clean · `openspec validate operator-console --type spec --strict` valid.
- Semantic verify (fresh-context subagent, §2.7 prompt): **CLEAN — 0 CRITICAL**, 1 WARNING (a MODIFIED Customer surface-inspection scenario is covered indirectly, not by one direct assertion), 1 SUGGESTION (design.md `?->` Risks note now reads over-absolute — `account` is the sanctioned nullable `HasOne` exception). Both accepted; requirement met.
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (OOMs at 128 MB; lesson 2026-06-20).

## Active Change & Next Task
- **No active change** (`openspec list` empty). `main` synced with `origin/main`; ralph branch deleted. Next: `/spec-to-change` for the next Build-Workplan slice.

## Blockers & Decisions Needed
- **No blocker.** Close ritual fully complete (merged, archived, pushed, branch deleted).
- **Knowledge promotion: none due.** This UI-only slice does not confirm any of the three 2/3 hypotheses (data-model create-entity spine, laravel config-test, testing absent-class-by-listing) — it stands up no new spine, config invariant, or absence test (D8: zero new Actions). Forcing a fit was declined.

## Open Patterns
- **Operator-console slice = the reusable shape** — now captured as a **rule** in the new `knowledge/filament/` domain (created 2026-06-24): read-only Resource (state via cast, no enum import) → write-through create page (`OperatorConsoleCreateRecord`) → form-less `lifecycleAction` header verbs gated as the exact complement of each Action's from-state → EN/IT i18n guard → PG17 closing chain. Proven across 12 Resources / 8 console slices; enforced by `ModuleBoundariesTest` + `NoEloquentWriteInOperatorPanelRule`. Reference any `Illegal*Transition` in prose only (Pint import trap, lesson 2026-06-20).
