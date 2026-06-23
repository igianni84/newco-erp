---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`operator-console-parties-membership` AUTHORED + APPROVED).** `spec-to-change` authored the demand-side **membership** operator console: a standalone `ProfileResource` (cross-Customer **approval queue**, Pending/All tabs) + `ViewProfile` carrying all **9 Profile lifecycle verbs**, the Profile **create** surface, and the **3 Account verbs** bundled onto `ViewCustomer`. Pure operator-console slice — zero domain code, migration, dep, or `party-registry` change. Delta on the `operator-console` capability: **4 ADDED + 2 MODIFIED** requirements, 25 scenarios, `validate --strict` green, 4/4 artifacts. `APPROVED` created; ralph not yet launched.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (club-credit close, 2026-06-23):** full suite **1560/1560** on real PG17 Docker; PHPStan max 0; Pint clean. **No code written this session** (authoring only) — the suite stands at 1560.
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (OOMs at 128 MB; lessons.md 2026-06-20). PG17 runnable locally via `docker run … postgres:17` for the close ritual.

## Active Change & Next Task
- **`operator-console-parties-membership` — APPROVED, ready for ralph.** Launch: `./ralph.sh --change operator-console-parties-membership <iterations>` (branches `ralph/operator-console-parties-membership` from main; preflight finds a clean change dir since the `approve:` commit pre-staged it).
- **First task 1.1:** `ProfileResource.php` — read-bind `Parties\Models\Profile`; list = approval queue (Pending/All tabs, `where('state','applied')`); `state` badge via cast; `getPages` index/create/view. **8 task-groups, ~17 tasks.**
- **Zero `Parties\Enums` import** across the slice (all Action operands are ints; state read via `->state->value`) → `ModuleBoundariesTest` green by construction.

## Blockers & Decisions Needed
- **No blocker.** Strict-valid; all driven Actions verified on disk; 4 cited ADRs exist.
- **Boundary seams (out of scope, documented):** `Applied→WaitingList` (no writer — awaits `parties-hero-package`); activation **capacity cap** (Module-A seam — ships uncapped); Producer-Portal TanStack UI (operator drives via `newco_ops`); `MembershipFeePaid` trigger (Module-E seam).

## Open Patterns
- **Standing console landmines for ralph** (design.md Risks): a from-state-hidden reject is undriveable → assert the **domain throw** + `assertActionHidden`, NOT a notification (lesson 2026-06-22) — **except** `renew` past-grace (UI-reachable → assert `action_failed`); `{@see \…\IllegalProfileTransition}`/`IllegalAccountTransition` in a docblock makes Pint add a boundary-breaching `use` → **prose only**; the i18n scanner is suite-wide → verify via `--filter`, never a bare file path.
- **Event surface (reconciled vs code):** `CreateProfile`→`ProfileCreated`; approve→**only** `OriginatingClubLocked` on the first-ever approval; decline / cancel / all Account verbs → **audit-only** (no event).
- **No new `Parties\Actions\*`** → `SupplyLifecycleChainTest` whitelist needs no change (design D8).
