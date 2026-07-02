---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-02 (`parties-enhanced-kyc-threshold`) BUILD STARTED. Task 1.1 ✅ (migration + 2 enums + schema test), green.** Iteration 1/20 done; `parties_compliance_reviews` table landed. Next: task 1.2 (i18n copy + CONTEXT.md glossary).

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Last green: full suite **1889/1889** (SQLite `:memory:`), PHPStan max **0**, Pint clean, `openspec validate --strict` valid. (+6 tests from RM-02 task 1.1.)
- ⚠ **Run the full suite as `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child that ignores `-d`, and 1889 tests exhaust the 128M CLI default at result-collection (fatal, NOT a failure). Filtered `artisan test --filter=` is fine at 128M.
- Branch `ralph/parties-enhanced-kyc-threshold`. PG17 not runnable locally (no PG server) → CHECK-rejection branches verify in CI / task 7.1 close.

## Active Change & Next Task
- **ACTIVE: `parties-enhanced-kyc-threshold` (RM-02, P0 compliance floor) — building. 1/12 tasks done.**
- **NEXT: task 1.2** — localized copy (`lang/en/parties.php`, `lang/{en,it}/operator_console.php`) + extend root `CONTEXT.md` (Compliance Review Queue, Enhanced-KYC Threshold €10k/€50k OR, `CustomerTransactionTotalsReader`, `CustomerEnhancedKycReviewRequired`, `aml_threshold`). No hardcoded strings (inv. 12).
- **⭐ Task 2.1 shortcut:** its two enums (`ComplianceReviewReason` = `enhanced_kyc_threshold`; `ThresholdKind` = `single_transaction`/`cumulative_annual`) ALREADY EXIST (created in 1.1 as the CHECK-derivation prereq) → 2.1 = ONLY the enum unit test.
- **Slice:** breach €10k single-tx OR €50k rolling-12mo cumulative → latch `enhanced_kyc_flag`+`_at`, create `parties_compliance_reviews` row, emit PII-free `CustomerEnhancedKycReviewRequired`, AML re-screen `under_review`. Detection = `EvaluateEnhancedKycThreshold` (idempotent, flag-latched); totals via `CustomerTransactionTotalsReader` port + null adapter (Module-S seam); periodic `parties:scan-enhanced-kyc-thresholds` inline on scheduler tick.

## Blockers & Decisions Needed
- **None blocking.**
- **Implementer landmine (design D2):** resolving the AML `under_review` from the console re-tags `trigger_source=compliance_ad_hoc` (§9.5 — console never offers `aml_threshold`). CORRECT; AML origin stays durable on the review-queue row + the event. **Do NOT force `aml_threshold` onto the resolution.**
- **Deferred seams:** real Module-S totals source + order-completion trigger; 12-month cadence job; screening-vendor adapter; enhanced-KYC doc-FSM; review-queue resolve action. Ad-hoc re-screen already ships.

## Open Patterns
- **Value-set CHECK shape follows nullability:** NOT-NULL enum col = plain `IN (...)`; additive-nullable = `IS NULL OR IN (...)`. Both derive from `Enum::cases()` (PG-guarded); SQLite floor is the cast.
- **Read DB scalars with `->value('col')`, not `->first()->prop`** (PHPStan max: null-object). Assert bigint/money with `->toEqual` (PG bigint-as-string).
- **Inbound cross-module seam = consumer-defined read-port + null adapter** (K needs Module-S data): ship contract + zero-returning adapter now; real impl lands with the upstream module. (RM-01 `CustomerAnonymised` / HoldType DEC-008 precedent.)
- **Scheduler tick ≠ queued consumer** (substrate ADR line 66): a `->daily()` command does NOT trip the queue-driver gate.
- **PII-free event over frozen-catalog silence** (RM-01 precedent): net-new audit/seam event admissible when §15.x names none, if tracker/scope calls for it.
- **Compliance gate:** key on `HoldType::Compliance` via `PartyComplianceStatusReader`, never the `Hold` model; screening writes go only through `RecordCustomerScreening`.
