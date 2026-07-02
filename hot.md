---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-02 (`parties-enhanced-kyc-threshold`) COMPLETE ✅ — all 12/12 tasks done. Task 7.1 (the LAST) shipped `EnhancedKycThresholdChainTest` — the closing integration proof driving the WHOLE enhanced-KYC lifecycle END-TO-END through the REAL Actions (only double = fake `CustomerTransactionTotalsReader`): (1) single-tx €10k breach → flag+`enhanced_kyc_at`, one open review, `under_review`/`aml_threshold`, breach-time event-SET = `{CustomerEnhancedKycReviewRequired}` (no `CustomerRescreening*` yet) → operator `RecordCustomerScreening(Passed, ComplianceAdHoc)` → `passed`/`compliance_ad_hoc`, flag+review stay DURABLE (`resolved_at` NULL), `CustomerRescreeningPassed` fires; emergent lifecycle event-SET via `DomainEvent::query()->distinct()->pluck('name')->all()` = `{CustomerEnhancedKycReviewRequired, CustomerRescreeningPassed}`, count===2; (2) cumulative €50k path converges identically; (3) idempotency — the `enhanced_kyc_flag` latch survives the sanctions resolution (a cleared customer is NOT nightly-scan-re-blocked). `DatabaseMigrations`, money via `->toEqual`. 3 tests/42 assertions.** → replied `<promise>CHANGE_COMPLETE</promise>`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Cross-engine GREEN (the change-complete gate): full suite 1947/1947 on SQLite AND 1947/1947 on PostgreSQL 17** (10459 assertions identical), PHPStan max **0**, Pint clean, `openspec validate --strict` valid. (+3 from 7.1.)
- ⚠ **Full suite = `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child ignoring `-d` (128M fatal at result-collection, NOT a failure). Filtered/by-path runs fit 128M.
- ⚠ **Local PG cross-engine recipe (task 7.1, now a Codebase Pattern):** `docker run -d --name pg --tmpfs /var/lib/postgresql/data:rw --shm-size=256m … postgres:17` (a default container fills the Docker VM disk → `pg_wal … No space left` PANIC → crash); run the **FULL** suite via `… php -d memory_limit=2G vendor/bin/pest` (a path SUBSET fails 5 `*ConsoleI18nTest` — the shared sink-scanner is declared only in Catalog's `ProductMasterConsoleI18nTest`); `docker rm -f pg` after.
- Branch `ralph/parties-enhanced-kyc-threshold`.

## Active Change & Next Task
- **`parties-enhanced-kyc-threshold` (RM-02, P0 compliance floor) is FULLY IMPLEMENTED — 12/12.** §1–§7 all complete: schema+copy, enums/model/event, totals port seam + null adapter, detection Actions (`CreateComplianceReview` + `EvaluateEnhancedKycThreshold`), daily scan command+schedule, read-only console surface, and the closing cross-engine integration test.
- **⭐ NEXT: HUMAN review → merge `ralph/parties-enhanced-kyc-threshold` → semantic-verify (GUIDE §2.7) → `openspec archive parties-enhanced-kyc-threshold --yes`.** Do NOT archive or merge in the loop — humans do that after review.
- After archive, the confirmation date for any knowledge promotion = the archive-dir date `openspec/changes/archive/YYYY-MM-DD-parties-enhanced-kyc-threshold/`.
- The next Ralph change is prepared by a human via `/spec-to-change` (RM-03+ on the Remediation_Tracker).

## Blockers & Decisions Needed
- **None.** Change complete, both engines green, all gates pass.
- **Durable design landmines to carry forward:** (D2) resolving the AML `under_review` from the console re-tags `trigger_source=compliance_ad_hoc` (§9.5 — console never offers `aml_threshold`); the AML origin stays durable on the review row + event. The sanctions clear leaves `enhanced_kyc_flag=true` + review `resolved_at=NULL` (resolve action deferred, § 9.1). Do NOT "fix" either.

## Open Patterns
- **Closing integration test = drive the chain through the REAL Actions (never factories), assert the emergent event-SET** (`DomainEvent::query()->distinct()->pluck('name')->all()->toEqualCanonicalizing([...])`) — a `knowledge/testing` rule. Beware an event NAME containing a substring you'd otherwise exclude (`CustomerEnhancedKycReviewRequired` contains "Kyc" → no `%Kyc%` sweep).
- **Deferred Module-S seams still open:** the real `CustomerTransactionTotalsReader` adapter (order/invoice EUR history) + the at-order-completion trigger land with Module S (Commerce, Phase 4); the null adapter makes detection a correct no-op until then. The 12-month re-screen cadence job + the review-queue resolve action remain separate deferred changes.
