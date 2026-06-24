---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-24
---

# Hot Cache

## Last Updated
**2026-06-24 — `operator-console-parties-membership` CLOSED (GUIDE §2.7 ritual done, push pending).** The demand-side **Parties membership operator console** is reviewed, verified, merged to `main` (`a2d9172 merge: …`, `--no-ff`) and **archived** (`openspec/changes/archive/2026-06-24-operator-console-parties-membership/`; delta folded into the living `openspec/specs/operator-console/spec.md` — **4 added + 2 modified** requirements). What shipped (groups 1–8): read-only `ProfileResource` (list + approval-queue tabs + infolist, state badge via cast, no `Parties\Enums` import — D2); write-through `CreateProfile` (Customer+Club Selects → `app(CreateProfile)->handle()`, `DuplicateProfileForClub` on `club_id`); full Profile FSM as form-less `lifecycleAction` header verbs on `ViewProfile` (approve/decline → activate/suspend/reactivate → lapse/renew/cancel/deactivate, each `->visible()`-gated via parametric `stateIs()`); Account FSM (suspend/reactivate/close) on `ViewCustomer` (routed by nullable `->account?->id`); EN/IT i18n (DEC-127 fallback, `*.club` loanword) guarded by `ProfileConsoleI18nTest`; PG17 closing-chain `ProfileMembershipChainTest`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (close ritual, real PostgreSQL 17 via `postgres:17` Docker):** full suite `php -d memory_limit=-1 vendor/bin/pest` → **1726/1726** (9425 assertions) · PHPStan max 0 · Pint clean · `openspec validate operator-console --type spec --strict` valid.
- Semantic verify (fresh-context subagent, §2.7 prompt): **CLEAN — 0 CRITICAL**, 1 WARNING (a MODIFIED Customer surface-inspection scenario is covered indirectly, not by one direct assertion), 1 SUGGESTION (design.md `?->` Risks note now reads over-absolute — `account` is the sanctioned nullable `HasOne` exception). Both accepted; requirement met.
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (OOMs at 128 MB; lesson 2026-06-20).

## Active Change & Next Task
- **No active change** (`openspec list` empty). Next: `/spec-to-change` for the next Build-Workplan slice.
- **PUSH PENDING (close-ritual gate).** `main` is ahead of `origin/main` by 3 local commits: `e353ac6 approve:`, `a2d9172 merge:`, and the pending `archive:` commit. Branch `ralph/operator-console-parties-membership` is **not yet deleted** (delete only after push). Awaiting Giovanni's go to `git push`.

## Blockers & Decisions Needed
- **No technical blocker.** Only the human push-approval gate is open.
- **Knowledge promotion: none due.** This UI-only slice does not confirm any of the three 2/3 hypotheses (data-model create-entity spine, laravel config-test, testing absent-class-by-listing) — it stands up no new spine, config invariant, or absence test (D8: zero new Actions). Forcing a fit was declined.

## Open Patterns
- **Operator-console slice = the reusable shape**, now proven across **5** consoles (Customer, Club, Producer, ProducerAgreement, Profile/Account): read-only Resource (state badge via cast, relation columns via typed closure, approval tabs via `getTabs()`) → write-through create page (`OperatorConsoleCreateRecord` + model-backed Selects) → form-less `lifecycleAction` header verbs gated by parametric `stateIs()`/`accountStatusIs()` (no enum import) → dual-block i18n guard → composed-FSM PG17 closing chain. **No knowledge entry tracks it yet** — candidate to capture as a hypothesis. Reference any `Illegal*Transition` in prose only (Pint import trap, lesson 2026-06-20).
