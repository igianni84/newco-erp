---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-02 (`parties-enhanced-kyc-threshold`) task 2.2 ✅ (model `ComplianceReview` + factory + model test), green. 4/12 tasks done.** Section 2 (Domain) half done: enums + model landed. Next: task 2.3 (event `CustomerEnhancedKycReviewRequired`).

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Last green: full suite **1911/1911** (SQLite `:memory:`), PHPStan max **0**, Pint clean, `openspec validate --strict` valid. (+2 from RM-02 task 2.2: `ComplianceReviewModelTest`.)
- ⚠ **Run the full suite as `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child that ignores `-d`; the suite exhausts the 128M CLI default at result-collection (fatal, NOT a failure). Filtered `artisan test --filter=`/by-path is fine at 128M.
- ⚠ `CustomerConsoleI18nTest` **cannot run by bare path** — its sink scanner (`scanOperatorConsoleHardcodedSinks`) is declared in `ProductMasterConsoleI18nTest`, loaded only by `--filter`/full-suite. Bare-path run fails that one test (expected).
- Branch `ralph/parties-enhanced-kyc-threshold`. PG17 not runnable locally (no PG server) → CHECK-rejection + bigint-string branches verify in CI / task 7.1 close.

## Active Change & Next Task
- **ACTIVE: `parties-enhanced-kyc-threshold` (RM-02, P0 compliance floor) — building. 4/12 done.**
- **⭐ NEXT: task 2.3** — event `CustomerEnhancedKycReviewRequired` (`final` class, mirror `CustomerRescreeningPassed`): `const NAME = 'CustomerEnhancedKycReviewRequired'`, `const ENTITY_TYPE = 'Customer'`, `static payload(Customer $customer, ComplianceReview $review): array` returning **ONLY** `customer_id`, `enhanced_kyc_at` (ISO), `threshold_kind` (`->value`), `amount` via `Money::of($review->tripped_amount_minor, Currency::of($review->tripped_currency))->toPayload()`. **PII-free** (no name/email/phone/dob). Test: `array_keys(...)->toEqualCanonicalizing(['customer_id','enhanced_kyc_at','threshold_kind','amount'])` + assert none of the Customer's PII field values appear in the payload.
- **Then 3.1/3.2** totals port seam: contract `CustomerTransactionTotalsReader::forCustomer(int): CustomerTransactionTotals` (DTO = two EUR `Money`: largestSingleTransaction, trailingTwelveMonthCumulative) + `NullCustomerTransactionTotalsReader` (zero EUR) bound in `PartiesServiceProvider::register()` (the `PartyComplianceStatusReader` precedent). Then §4 Actions, §5 command, §6 console, §7 close.
- **Model landed (2.2):** `ComplianceReview` persistence-only (`$guarded=[]`, `$table='parties_compliance_reviews'`, casts reason/threshold_kind enums + `tripped_amount_minor`→integer + `resolved_at`→immutable_datetime, within-module `belongsTo(Customer)`); factory has a `resolved()` state for 6.1/7.1. **NO MoneyCast** — `tripped_amount_minor`+`tripped_currency` are raw scalars the event re-assembles (2.3). Customer.php untouched (no inverse hasMany).

## Blockers & Decisions Needed
- **None blocking.**
- **Implementer landmine (design D2):** resolving the AML `under_review` from the console re-tags `trigger_source=compliance_ad_hoc` (§9.5 — console never offers `aml_threshold`). CORRECT; AML origin stays durable on the review-queue row + event. **Do NOT force `aml_threshold` onto the resolution.**
- **Deferred seams:** real Module-S totals source + order-completion trigger; 12-month cadence job; screening-vendor adapter; enhanced-KYC doc-FSM; review-queue resolve action. Ad-hoc re-screen ships.

## Open Patterns
- **Sibling model/factory idiom** (`Address`/`ClubCredit`): within-module `belongsTo`, `$guarded=[]`, per-module `Database\Factories\Parties\` factory + explicit `newFactory()` override + `@extends Factory<Model>`; assert cast bigint with model `->toBe` (int-proof) AND raw column `->toEqual` (PG bigint-as-string).
- **`*EnumsTest` idiom:** verbatim + order-sensitive case→value map (`->toBe`), `->toHaveCount(n)`, `from()` round-trip, out-of-domain `from()` → `ValueError`. Pins any `::cases()`-derived value-set CHECK against silent drift.
- **Console i18n = chrome vs domain split.** Chrome (headers/labels/feature groups) → `operator_console.customer.*`, front-load suffixes into `customerConsoleKitKeys()`; `sections.*` headings in the file's SECOND `customer` overlay (`array_replace_recursive([main],[overlay])`). Enum VALUE labels on a read surface → domain file `parties.compliance_review.*` (EN-only, enums have no `label()`).
- **Value-set CHECK shape follows nullability;** derive from `Enum::cases()` (PG-guarded); SQLite floor is the cast.
- **Read DB scalars with `->value('col')`;** assert money/bigint with `->toEqual` (PG bigint-as-string).
- **Inbound cross-module seam = read-port + null adapter** (K needs Module-S data); ship contract + zero adapter now.
- **Scheduler tick ≠ queued consumer** (substrate ADR): a `->daily()` command doesn't trip the queue-driver gate.
- **PII-free event over frozen-catalog silence** (RM-01 precedent): net-new audit/seam event admissible when §15.x names none.
