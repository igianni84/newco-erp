---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — RM-03 task 1.1 DONE (local commit): `ActivateProfile` docblock re-homes the `MembershipFeePaid` seam E→S (Module S emits / E records / K consumes — DEC-173; INV1, no INV0 — DEC-157) + states the two invocation modes. Behaviour-neutral (docblock-only). Next = 1.2 (the atomic flip).**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Latest green: full suite 1947/1947 on SQLite** (task 1.1, `php -d memory_limit=2G vendor/bin/pest`, 10459 assertions); PHPStan max 0; Pint clean; `openspec validate --strict` valid.
- ⚠ **Full suite = `php -d memory_limit=2G vendor/bin/pest`** (`php artisan test` re-spawns a child ignoring `-d` → 128M fatal in Filament panel tests). Filtered runs fit 128M.
- ⚠ **Local PG cross-engine (task 3.1):** `postgres:17` docker `--tmpfs …/data` + `--shm-size=256m` on port 55432 (5432 = invoicing PG16); full suite via the 2G pest cmd; `docker rm -f pg` after.

## Active Change & Next Task
- **ACTIVE (APPROVED): `parties-membership-charge-on-approval`** (branch `ralph/parties-membership-charge-on-approval`). RM-03 — atomic **approve = charge = activation** (`Approved` transient); charge = no-op Module-S seam today. **Progress: 1/5.** 1.1 ✅.
- **⭐ NEXT = task 1.2: `ApproveProfile` atomic flip.** Inject `ActivateProfile`; after `Applied → Approved` + the conditional OC lock, invoke `app(ActivateProfile)` **inside the same `DB::transaction`** → `Active`. **RED-GREEN ATOMIC — same iteration inverts ~8 approve-outcome observers:** `ProfileMembershipApprovalTest` (approve → `Active`; events 1→2/1→3), `MembershipActivationChainTest` (drop explicit `ActivateProfile`; `ProfileActivated` ×2; multiset 7→8), the 4 precondition helpers (`ProfileCancellationTest:64-65` / `ProfileSuspensionTest:53-54` / `ProfileLapseGraceTest:65-66` / `MembershipSuspensionChainTest:150-153` — **DELETE** the now-illegal `ActivateProfile` line, no try/catch), `ProfileApprovalConsoleTest`, `ProfileMembershipChainTest` (drop the separate `activate` leg).
- Then 2.1 (console: remove dead `activate` verb + `ProfileConsoleI18nTest`), 3.1 (full suite SQLite+PG17 + guards unamended), 4.1 (docs).
- Knowledge-promotion confirmation date for RM-03 = its future archive-dir date.

## Blockers & Decisions Needed
- **None blocking.** Q2 (console copy) settled Option A: EN "Membership approved and activated." / IT "Iscrizione approvata e attivata." (task 2.1).
- **Deferred (NOT in RM-03):** real charge (mandate/instrument/`fee_paid_at`/invoice) → Module S/E (F4–F6); Hero-Package **seat gate** (MVP-DEC-017) → **RM-05** (⏸️ Module A `qty`); SoD/four-eyes → **RM-08**.
- **⚠ Number collision:** `MVP-DEC-016` (membership) ≠ greenfield `DEC-016` (AI-copilot) — always the full token.

## Open Patterns
- **1.2 landmines (design.md Risks):** keep the `Approved` enum case; no `MembershipFeePaid` class; the internal `ActivateProfile` writes NO `originating_club_id` (`ComplianceIndependenceTest`); `SupplyLifecycleChainTest` allow-list + `ProfileApproved`-absent guard stay green UNAMENDED; `ProfileActivationTest` + `DeclineProfile` untouched.
- **Green-between-tasks:** after 1.2 the console `activate` verb is dead-but-present; `ProfileActivationConsoleTest` stays green until 2.1 removes both.
- **Pattern (progress.md):** a behaviour-neutral docblock re-home is proven by "every diff line is a `*` comment" + isolated contract test unedited + PHPStan resolves `{@see}`.
- **F4 candidate (untriaged):** truth-spec *Hold Registry* still "six-value" vs code's 8 (RM-04 debt).
