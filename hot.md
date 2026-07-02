---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-02 (`parties-enhanced-kyc-threshold`) task 2.1 ✅ (enum case→value+count unit pin), green. 3/12 tasks done.** Section 1 (Foundation) done + first of Section 2 (Domain). Next: task 2.2 (model `ComplianceReview`).

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Last green: full suite **1909/1909** (SQLite `:memory:`), PHPStan max **0**, Pint clean, `openspec validate --strict` valid. (+5 from RM-02 task 2.1: `ComplianceReviewEnumsTest`.)
- ⚠ **Run the full suite as `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child that ignores `-d`; the suite exhausts the 128M CLI default at result-collection (fatal, NOT a failure). Filtered `artisan test --filter=`/by-path is fine at 128M.
- ⚠ `CustomerConsoleI18nTest` **cannot run by bare path** — its sink scanner (`scanOperatorConsoleHardcodedSinks`) is declared in `ProductMasterConsoleI18nTest`, loaded only by `--filter`/full-suite. Bare-path run fails that one test (expected).
- Branch `ralph/parties-enhanced-kyc-threshold`. PG17 not runnable locally (no PG server) → CHECK-rejection branches verify in CI / task 7.1 close.

## Active Change & Next Task
- **ACTIVE: `parties-enhanced-kyc-threshold` (RM-02, P0 compliance floor) — building. 3/12 done.**
- **⭐ NEXT: task 2.2** — model `ComplianceReview` (persistence-only): `$guarded = []`, `protected $table = 'parties_compliance_reviews'`, casts (`reason`→`ComplianceReviewReason`, `threshold_kind`→`ThresholdKind`, `tripped_amount_minor`→`integer`, `resolved_at`→`immutable_datetime`), within-module `belongsTo(Customer::class,'customer_id')` (NO cross-module relation — `ModuleBoundariesTest` stays green). Factory under `Database\Factories\Parties\`. Test: create via factory, assert enum casts + `tripped_amount_minor` via **`->toEqual`** (PG bigint-as-string trap), assert `$review->customer` resolves.
- **Then 2.3** event `CustomerEnhancedKycReviewRequired` (PII-free payload: `customer_id`, `enhanced_kyc_at`, `threshold_kind`, `amount` via `Money::of(...)->toPayload()`), mirroring `CustomerRescreeningPassed`.
- Both enums (`ComplianceReviewReason` sole case, `ThresholdKind` single/cumulative) + i18n keys + migration already landed (tasks 1.1/1.2/2.1). Task 6.1 console resolves `operator_console.customer.compliance_reviews.*` + maps `reason`/`threshold_kind` `->value` through `parties.compliance_review.*` — no new copy needed.
- **Slice:** breach €10k single-tx OR €50k rolling-12mo cumulative → latch `enhanced_kyc_flag`+`_at`, create `parties_compliance_reviews` row, emit PII-free event, AML re-screen `under_review`+`aml_threshold`. Detection = `EvaluateEnhancedKycThreshold` (idempotent, flag-latched); totals via `CustomerTransactionTotalsReader` port + null adapter; periodic `parties:scan-enhanced-kyc-thresholds` inline on scheduler tick.

## Blockers & Decisions Needed
- **None blocking.**
- **Implementer landmine (design D2):** resolving the AML `under_review` from the console re-tags `trigger_source=compliance_ad_hoc` (§9.5 — console never offers `aml_threshold`). CORRECT; AML origin stays durable on the review-queue row + event. **Do NOT force `aml_threshold` onto the resolution.**
- **Deferred seams:** real Module-S totals source + order-completion trigger; 12-month cadence job; screening-vendor adapter; enhanced-KYC doc-FSM; review-queue resolve action. Ad-hoc re-screen ships.

## Open Patterns
- **`*EnumsTest` idiom:** verbatim + order-sensitive case→value map (`->toBe`), `->toHaveCount(n)`, `from()` round-trip, out-of-domain `from()` → `ValueError`. Pinning cases protects any `::cases()`-derived value-set CHECK from silent drift.
- **Console i18n = chrome vs domain split.** Chrome (headers/labels/feature groups) → `operator_console.customer.*`, front-load suffixes into `customerConsoleKitKeys()`; `sections.*` headings in the file's SECOND `customer` overlay (`array_replace_recursive([main],[overlay])`). Enum VALUE labels on a read surface → domain file `parties.compliance_review.*` (EN-only, enums have no `label()`).
- **Value-set CHECK shape follows nullability;** derive from `Enum::cases()` (PG-guarded); SQLite floor is the cast.
- **Read DB scalars with `->value('col')`;** assert money/bigint with `->toEqual` (PG bigint-as-string).
- **Inbound cross-module seam = read-port + null adapter** (K needs Module-S data); ship contract + zero adapter now.
- **Scheduler tick ≠ queued consumer** (substrate ADR): a `->daily()` command doesn't trip the queue-driver gate.
- **PII-free event over frozen-catalog silence** (RM-01 precedent): net-new audit/seam event admissible when §15.x names none.
