---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — RM-03 task 1.2 DONE: `ApproveProfile` now drives `Applied → Approved → Active` ATOMICALLY in one `DB::transaction` (injects `ActivateProfile`; `Approved` transient; approve = charge = activation — MVP-DEC-016). Charge = no-op Module-S seam today. Inverted all 8 approve-outcome observers in one iteration. Full suite 1947/1947. Next = 2.1 (console `activate` verb removal + i18n realign).**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Latest green: full suite 1947/1947 on SQLite** (task 1.2, `php -d memory_limit=2G vendor/bin/pest`, 10460 assertions); PHPStan max 0; Pint clean; `openspec validate --strict` valid.
- ⚠ **Full suite = `php -d memory_limit=2G vendor/bin/pest`** (`php artisan test` re-spawns a child ignoring `-d` → 128M fatal in Filament panel tests). Filtered runs fit 128M.
- ⚠ **Bare-path `pest …/OperatorPanel/Parties/` reds 5 `*ConsoleI18nTest`** ("sink scanner not loaded" — `scanOperatorConsoleHardcodedSinks()` is full-suite-only; lesson 2026-06-20). NOT a regression.
- ⚠ **Local PG cross-engine (task 3.1):** `postgres:17` docker `--tmpfs …/data` + `--shm-size=256m` on port 55432 (5432 = invoicing PG16); full suite via the 2G pest cmd; `docker rm -f pg` after.

## Active Change & Next Task
- **ACTIVE (APPROVED): `parties-membership-charge-on-approval`** (branch `ralph/parties-membership-charge-on-approval`). RM-03 — atomic **approve = charge = activation**. **Progress: 2/5.** 1.1 ✅ (seam docblock) · 1.2 ✅ (atomic flip).
- **⭐ NEXT = task 2.1: remove the dead `activate` console verb + realign i18n.** Delete the `activate` verb (+ its `ActivateProfile` import) from `ViewProfile::getHeaderActions()` — structurally unreachable now (`Approved` never durable). Keep **Approve** (label unchanged); reword its success copy Q2 Opt A: EN "Membership approved and activated." / IT "Iscrizione approvata e attivata." **ATOMICALLY** drop `operator_console.profile.actions.activate` + `notifications.activated` from `lang/en/` AND `lang/it/operator_console.php` AND `profileConsoleKitKeys()` in `ProfileConsoleI18nTest` (landmine #5 — atomic across all three or the i18n guards red). **DELETE** `ProfileActivationConsoleTest`. Adjust `approved`-state visibility rows in `ProfileLifecycleConsoleTest` + `ProfileResourceTest` residue. Do NOT touch the six `customer.*` Account keys.
- Then 3.1 (full suite SQLite+PG17 + guards unamended + PHPStan/Pint), 4.1 (memory).
- Knowledge-promotion confirmation date for RM-03 = its future archive-dir date.

## Blockers & Decisions Needed
- **None blocking.** Q2 (console copy) settled Option A (task 2.1).
- **Deferred (NOT in RM-03):** real charge (mandate/instrument/`fee_paid_at`/invoice) → Module S/E (F4–F6); Hero-Package **seat gate** (MVP-DEC-017) → **RM-05** (⏸️ Module A `qty`); SoD/four-eyes → **RM-08**.
- **⚠ Number collision:** `MVP-DEC-016` (membership) ≠ greenfield `DEC-016` (AI-copilot) — always the full token.

## Open Patterns
- **2.1 landmines:** i18n key drop must be atomic across en+it+contract; keep the six `customer.*` keys; `ProfileActivationConsoleTest` deleted (its `activate` verb is gone); after 2.1 the `activate` verb no longer exists anywhere.
- **Pattern (progress.md):** a red-green FSM-shape flip inverts EVERY observer in one iteration — grep the enum + Action name across `tests/` to prove the design's checklist complete; delete double-drive precondition calls (no try/catch); source-scan guards stay diff-free iff no Action/Event class added.
- **Nested-Action atomicity:** an Action calling a sibling's `handle()` inside its tx → savepoint keeps `DomainEventRecorder` happy (guard is `transactionLevel()===0`); the sibling stays a ROOT (recorder threads only explicit causation).
- **F4 candidate (untriaged):** truth-spec *Hold Registry* still "six-value" vs code's 8 (RM-04 debt).
