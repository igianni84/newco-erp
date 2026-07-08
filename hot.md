---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (pm) — Remediation scope decision (Giovanni): next = ONE batched "P3 sweep" change (RM-12+13+14+15, Module 0 completeness) + RM-05 unblocked via the K-side capacity seam (ADR-first) right after.** Earlier the same day `parties-module-k-br-guards` (RM-19/20/21/22/23) CLOSED via the full §2.7 ritual — merged `--no-ff` (`40f6c0a`), archived (`2026-07-08-parties-module-k-br-guards`), pushed (`main`↔`origin` in sync); semantic-verify 5 subagents × 14 delta requirements → 1 CRITICAL (CONTEXT.md R3 coexistence residual) + 4/6 WARNINGs fixed in-place. Details: tracker §1/§6 + the archived `progress.md`.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2080/2080 on SQLite AND PG17** (10 854 assertions each) · PHPStan max **0** · Pint **clean**.
- Run the suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB). **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container on :55432, running).

## Active Change & Next Task
- **No change in flight** — `openspec/changes/` holds only `archive/`.
- **NEXT (decided 2026-07-08): author the "P3 sweep" batched change via `/spec-to-change` in a fresh window (prep-only, no build, no APPROVED)** — RM-12 (Layer-1 case-config whitelist, 0 J-13/XM-11) + RM-13 (`EnrichmentDataUpdated` + post-active enrichment edit, EVT-8) + RM-14 (re-versioning on identity edit, BR-Audit-1 — introduces the catalog edit path) + RM-15 (Producer-existence at Master creation, XM-2, maybe-ADR). **Mandatory fold-in: the RM-06 S1 verb-filter** on `ApprovalGovernance::assertNotRejectionPending` — its raw-latest-audit read is safe only while `LifecycleTransition` is the sole catalog audit writer; any edit path breaks that, so the governance-verb filter lands in the SAME change. Approved M-batch deviation recorded in `lessons.md` (amendment) + tracker §1/§6.
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first (grill-with-docs)** — unblocked by choice 2026-07-08, no longer waiting on Module A `qty`; the seam consumes the real Module-A signal when it exists.

## Blockers & Decisions Needed
- None. (RM-05's Module-A block dissolved by the seam decision; the seam ADR is the next decision gate after the P3 sweep.)

## Open Patterns
(full forms in the archived change's `progress.md` `## Codebase Patterns`)
- **Inversion sweeps include CONTEXT.md** — the glossary of record is a first-class claim-holder archive-sync never rewrites (2026-07-08 lesson; caught as the close's one CRITICAL).
- Module-K guard family shipped 2026-07-08: model `saving` value-domain reject (Club-6) · conditional `updating` content lock on persisted status (Producer-5) · pre-txn fail-fast input gates (Identity-6 age, RM-22 cadence) · in-txn reference guards (RM-21, Agreement-4) · activation-time cross-shape exclusion (RM-20) · batch-walk cascade reusing an audit-only Action with a load-bearing from-state filter (RM-19).
- Console: operand-enum Selects with server floors · two-field create-rejection routing via condition re-derivation (no exception import) · zero-page-code guard surfacing · ungated preference affordances.
