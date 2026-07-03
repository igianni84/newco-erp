---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — RM-03 task 2.1 DONE: the dead `activate` console verb REMOVED from `ViewProfile` (now 8 verbs); i18n realigned — dropped `actions.activate` + `notifications.activated` from en+it + `profileConsoleKitKeys()`, reworded `notifications.approved` → EN "Membership approved and activated.". `ProfileActivationConsoleTest` retired → new `ProfileStatusConsoleTest` (suspend+reactivate coverage rehomed). Full suite 1951/1951. Progress 3/5. Next = 3.1.**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Latest green: full suite 1951/1951 on SQLite** (`php -d memory_limit=2G vendor/bin/pest`, 10419 assertions); PHPStan max 0; Pint clean; `openspec validate --strict` valid.
- ⚠ **Full suite = `php -d memory_limit=2G vendor/bin/pest`** (`php artisan test` re-spawns a child ignoring `-d` → 128M fatal in Filament panel tests). Filtered runs fit 128M.
- ⚠ **Bare path/dir on `OperatorPanel/**` reds the `*ConsoleI18nTest`s** ("sink scanner not loaded" — `scanOperatorConsoleHardcodedSinks()` is declared in `ProductMasterConsoleI18nTest`, full-suite-only). To run `ProfileConsoleI18nTest` alone, pass that ProductMaster file alongside it. NOT a regression.
- ⚠ **Local PG cross-engine (task 3.1):** `postgres:17` docker `--tmpfs …/data` + `--shm-size=256m` on port 55432 (5432 = invoicing PG16); full suite via the 2G pest cmd; `docker rm -f pg` after.

## Active Change & Next Task
- **ACTIVE (APPROVED): `parties-membership-charge-on-approval`** (branch `ralph/parties-membership-charge-on-approval`). RM-03 — atomic **approve = charge = activation**. **Progress 3/5.** 1.1 ✅ (seam docblock) · 1.2 ✅ (atomic flip) · 2.1 ✅ (console cleanup).
- **⭐ NEXT = task 3.1: full Parties suite green on SQLite AND PG17; guards unamended; static clean.** Run the whole suite on both engines (SQLite `:memory:` + the `postgres:17` recipe above). Confirm `SupplyLifecycleChainTest` (exact-set Action allow-list), `ComplianceIndependenceTest` (OC-write guard), `SpineCreationChainTest` (creation-chain) and `ProfileActivationTest` pass WITHOUT edits (`git diff --stat` shows them unchanged — already true; untouched by 1.1/1.2/2.1). PHPStan max 0; Pint --test clean. PG jsonb trap 3: assert event names/counts by name, payloads by key.
- Then 4.1 (memory). Knowledge-promotion confirmation date for RM-03 = its future archive-dir date.

## Blockers & Decisions Needed
- **⚠ FLAG for Giovanni (task 2.1 IT copy):** I used **"Adesione approvata e attivata."** (the block's documented «membership»→«adesione» convention, `lang/it` ~L630) instead of tasks.md's literal "Iscrizione approvata e attivata." (a one-off parallel term). Design marks this copy "subject to Giovanni's review" — one-word revert if he wants "Iscrizione".
- **Deferred (NOT in RM-03):** real charge (mandate/instrument/`fee_paid_at`/invoice) → Module S/E (F4–F6); Hero-Package **seat gate** (MVP-DEC-017) → **RM-05** (⏸️ Module A `qty`); SoD/four-eyes → **RM-08**.
- **⚠ Number collision:** `MVP-DEC-016` (membership) ≠ greenfield `DEC-016` (AI-copilot) — always the full token.

## Open Patterns
- **Relocate-before-delete (new lesson 2026-07-03):** a task's "delete file X" can under-describe X's coverage — grep first, rehome orthogonal coverage (X guarded suspend/reactivate + AC-K-FSM-2a, not just activate), then delete.
- **`activate` verb fully retired:** exists nowhere now; `ViewProfile` = 8 verbs (approve/decline/suspend/reactivate/lapse/renew/cancel/deactivate). `ProfileMembershipChainTest`/`ProfileLifecycleConsoleTest` cross-refs repointed to `ProfileStatusConsoleTest`.
- **F4 candidate (untriaged):** truth-spec *Hold Registry* still "six-value" vs code's 8 (RM-04 debt).
