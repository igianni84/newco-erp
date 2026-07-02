---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-02 (`parties-enhanced-kyc-threshold`) task 1.2 ✅ (i18n copy + CONTEXT.md glossary), green. 2/12 tasks done.** Foundation section (1) COMPLETE. Next: task 2.1 (enum unit test — the enums already exist).

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Last green: full suite **1904/1904** (SQLite `:memory:`), PHPStan max **0**, Pint clean, `openspec validate --strict` valid. (+15 from RM-02 task 1.2: 3 new copy tests + 12 dataset cases.)
- ⚠ **Run the full suite as `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child that ignores `-d`; 1904 tests exhaust the 128M CLI default at result-collection (fatal, NOT a failure). Filtered `artisan test --filter=`/by-path is fine at 128M.
- ⚠ `CustomerConsoleI18nTest` **cannot run by bare path** — its sink scanner (`scanOperatorConsoleHardcodedSinks`) is declared in `ProductMasterConsoleI18nTest`, loaded only by `--filter`/full-suite. Bare-path run fails that one test (expected).
- Branch `ralph/parties-enhanced-kyc-threshold`. PG17 not runnable locally (no PG server) → CHECK-rejection branches verify in CI / task 7.1 close.

## Active Change & Next Task
- **ACTIVE: `parties-enhanced-kyc-threshold` (RM-02, P0 compliance floor) — building. 2/12 done.**
- **⭐ NEXT: task 2.1** — the two enums `ComplianceReviewReason` (`enhanced_kyc_threshold`, sole case) + `ThresholdKind` (`single_transaction`/`cumulative_annual`) **ALREADY EXIST** (created in 1.1 as the CHECK-derivation prereq). Task 2.1 = ONLY the dedicated enum unit test (case→value mapping + `count(ComplianceReviewReason::cases())===1`).
- **Then 2.2** model `ComplianceReview`, **2.3** event `CustomerEnhancedKycReviewRequired` (PII-free payload: `customer_id`, `enhanced_kyc_at`, `threshold_kind`, `amount`).
- **i18n keys are laid down (task 1.2)** — task 6.1 console panel resolves `operator_console.customer.compliance_reviews.*` (chrome, in the i18n contract) + `customer.sections.compliance_reviews`, and maps review `reason`/`threshold_kind` `->value` through `parties.compliance_review.*` (domain labels). No new copy needed there.
- **Slice:** breach €10k single-tx OR €50k rolling-12mo cumulative → latch `enhanced_kyc_flag`+`_at`, create `parties_compliance_reviews` row, emit PII-free event, AML re-screen `under_review`+`aml_threshold`. Detection = `EvaluateEnhancedKycThreshold` (idempotent, flag-latched); totals via `CustomerTransactionTotalsReader` port + null adapter; periodic `parties:scan-enhanced-kyc-thresholds` inline on scheduler tick.

## Blockers & Decisions Needed
- **None blocking.**
- **Implementer landmine (design D2):** resolving the AML `under_review` from the console re-tags `trigger_source=compliance_ad_hoc` (§9.5 — console never offers `aml_threshold`). CORRECT; AML origin stays durable on the review-queue row + event. **Do NOT force `aml_threshold` onto the resolution.**
- **Deferred seams:** real Module-S totals source + order-completion trigger; 12-month cadence job; screening-vendor adapter; enhanced-KYC doc-FSM; review-queue resolve action. Ad-hoc re-screen ships.

## Open Patterns
- **Console i18n = chrome vs domain split.** Chrome (headers/labels/feature groups) → `operator_console.customer.*`, guarded by front-loading suffixes into `customerConsoleKitKeys()`; `sections.*` headings live in the file's SECOND `customer` overlay block (`array_replace_recursive([main],[overlay])`). Enum VALUE labels on a read surface → domain file `parties.compliance_review.*` (EN-only, enums have no `label()`).
- **Value-set CHECK shape follows nullability;** derive from `Enum::cases()` (PG-guarded); SQLite floor is the cast.
- **Read DB scalars with `->value('col')`;** assert money/bigint with `->toEqual` (PG bigint-as-string).
- **Inbound cross-module seam = read-port + null adapter** (K needs Module-S data); ship contract + zero adapter now.
- **Scheduler tick ≠ queued consumer** (substrate ADR): a `->daily()` command doesn't trip the queue-driver gate.
- **PII-free event over frozen-catalog silence** (RM-01 precedent): net-new audit/seam event admissible when §15.x names none.
