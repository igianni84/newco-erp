---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — Round-2 P0 compliance floor CLOSED; `Remediation_Tracker` reconciled.** RM-01 (`parties-anonymisation`) + RM-02 (`parties-enhanced-kyc-threshold`) are both **built → merged → archived → PUSHED**; `main`↔`origin/main` in sync (0/0). `docs/validation/Remediation_Tracker.md` brought current (§1/§3/§4/§6): RM-01 🟡→✅, RM-02 🔴→✅, next = RM-03. **No active OpenSpec change.** Doc-only bookkeeping session — no code touched.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Latest green: full suite 1947/1947 on SQLite AND PostgreSQL 17** (10459 assertions) at RM-02 task 7.1; PHPStan max 0, Pint clean. (RM-01 closed at 1883/1883 both engines.)
- ⚠ **Full suite = `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child ignoring `-d` (128M fatal at result-collection in the Filament panel tests, NOT a regression). Filtered/by-path runs fit 128M.
- ⚠ **Local PG cross-engine recipe:** `postgres:17` via docker with `--tmpfs /var/lib/postgresql/data` + `--shm-size=256m` on a free port (5432 held by the invoicing PG16 → use 55432); run the FULL suite via the 2G pest cmd; `docker rm -f pg` after.

## Active Change & Next Task
- **No active OpenSpec change** (`openspec list` empty). Round-2 P0 floor (RM-01 + RM-02) shipped + archived + pushed.
- **⭐ NEXT: RM-03 — membership charge-on-approval (`DEC-016`).** P1 canon, size L, one of Paolo's 3 walkthrough scenarios (the flow canon declares "wrong": a distinct `approved`-but-unpaid state). **Requires an ADR BEFORE implementing** (grill-with-docs: read `decisions/INDEX.md` + existing ADRs + spec Module K §9/§4; the crux is the **charge seam** since Module S/E are stubs — propose 2–3 options) → then `/spec-to-change` → human APPROVED → `./ralph.sh`.
- Knowledge-promotion confirmation date for anything learned in RM-02 = the archive-dir date **2026-07-03**.

## Blockers & Decisions Needed
- **None blocking.** RM-03 is gated on writing its ADR first (the charge-seam decision). **RM-05** (capacity seat-set) stays ⏸️ pending Module A (empty stub).
- **Durable design landmines (shipped, do NOT "fix"):** (RM-02) resolving AML `under_review` from the console re-tags `trigger_source=compliance_ad_hoc` (§9.5 — console never offers `aml_threshold`); the AML origin stays durable on the review row + event. Sanctions-clear leaves `enhanced_kyc_flag=true` + review `resolved_at=NULL` (resolve action deferred, §9.1).

## Open Patterns
- **Deferred Module-S seams still open (RM-02):** real `CustomerTransactionTotalsReader` adapter + at-order-completion trigger land with Module S (Commerce, Phase 4). The 12-month re-screen cadence job + the review-queue resolve action = separate deferred changes.
- **F4 candidate (untriaged):** truth-spec *Hold Registry* still "six-value" vs code's 8 (RM-04 delta debt) — flagged in RM-01 §4, not yet a §7 row in the tracker.
- **Closing integration test = drive the chain through the REAL Actions, assert the emergent event-SET** (`DomainEvent::query()->distinct()->pluck('name')->toEqualCanonicalizing([...])`) — a `knowledge/testing` rule.
