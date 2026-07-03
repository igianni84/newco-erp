---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — RM-03 `/spec-to-change` DONE: change `parties-membership-charge-on-approval` authored + `APPROVED` + committed (local, unpushed).** Adopts canon MVP-DEC-016 (membership charge-on-approval), grounded on the committed ADR + live canon. `openspec validate --strict` green. **Next = `./ralph.sh` (Giovanni runs it).**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Latest green: full suite 1947/1947 on SQLite AND PG17** (RM-02 task 7.1). **No code touched by RM-03 yet** — the change is an APPROVED proposal; the flip lands when ralph runs.
- ⚠ **Full suite = `php -d memory_limit=2G vendor/bin/pest`** (`php artisan test` re-spawns a child ignoring `-d` → 128M fatal in Filament panel tests). Filtered runs fit 128M.
- ⚠ **Local PG cross-engine:** `postgres:17` docker `--tmpfs …/data` + `--shm-size=256m` on port 55432 (5432 = invoicing PG16); full suite via the 2G pest cmd; `docker rm -f pg` after.

## Active Change & Next Task
- **ACTIVE (APPROVED): `parties-membership-charge-on-approval`.** RM-03 — collapse the two-step membership flow to one atomic **approve = charge = activation** (`Approved` transient); charge is a no-op Module-S seam today. **4 MODIFIED party-registry requirements**: Profile Membership Approval, Profile Activation, Demand-Side Activation Events, + the omnibus Birth States (Q1 = +ombrello).
- **4 task groups (green per iteration):** 1.1 `ActivateProfile` docblock seam re-home E→S (behaviour-neutral) → **1.2 `ApproveProfile` atomic flip + invert ~8 observer files in ONE iteration** (red-green atomic) → 2.1 console: remove dead `activate` verb + realign `ProfileConsoleI18nTest` → 3.1 full suite SQLite+PG17 + guards unamended → 4.1 docs.
- **⭐ NEXT: `./ralph.sh --change parties-membership-charge-on-approval <n>`** (Giovanni). Then review/merge → semantic-verify → `openspec archive`.
- Knowledge-promotion confirmation date for RM-03 = its future archive-dir date.

## Blockers & Decisions Needed
- **None blocking.** **Q2 (console copy):** proceeded on **Option A** (remove `activate`; Approve success → "approved and activated" / "approvata e attivata") — Giovanni away at ask-time; he can redirect before ralph.
- **Deferred (NOT in RM-03):** real charge (mandate/instrument/`fee_paid_at`/invoice) → Module S/E (F4–F6); Hero-Package **seat gate** (`Active`+`Suspended`, MVP-DEC-017) → **RM-05** (⏸️ Module A `qty`); SoD/four-eyes → **RM-08**.
- **⚠ Number collision:** `MVP-DEC-016` (membership) ≠ greenfield `DEC-016` (AI-copilot, superseded by DEC-021) — always the full token.

## Open Patterns
- **RM-03 landmines (implementer):** keep `Approved` enum case; no `MembershipFeePaid` class (K only consumes); `SupplyLifecycleChainTest` allow-list + `ProfileApproved`-absent + `ComplianceIndependenceTest` OC-write guard stay green UNAMENDED; i18n contract = `ProfileConsoleI18nTest` (not Customer).
- **Pattern candidate:** a behaviour flip inverts **every** observer in ONE iteration (no green-safe split); the isolated writer's contract (`ProfileActivationTest`) stands.
- **F4 candidate (untriaged):** truth-spec *Hold Registry* still "six-value" vs code's 8 (RM-04 debt).
- **Closing integration test = drive the chain through REAL Actions, assert the emergent event-SET** (`toEqualCanonicalizing`) — a `knowledge/testing` rule.
