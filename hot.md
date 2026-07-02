---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-02 (`parties-enhanced-kyc-threshold`) task 2.3 ✅ (event `CustomerEnhancedKycReviewRequired`, PII-free, + unit test), green. 5/12 done.** Section 2 (Domain: enums + model + event) COMPLETE. Next: §3 totals port seam (task 3.1).

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Last green: full suite **1916/1916** (SQLite `:memory:`), PHPStan max **0**, Pint clean, `openspec validate --strict` valid. (+5 from RM-02 task 2.3: `EnhancedKycReviewEventTest`.)
- ⚠ **Run the full suite as `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child that ignores `-d`; the suite exhausts the 128M CLI default at result-collection (fatal, NOT a failure). Filtered `artisan test --filter=`/by-path is fine at 128M.
- ⚠ `CustomerConsoleI18nTest` **cannot run by bare path** — its sink scanner (`scanOperatorConsoleHardcodedSinks`) is declared in `ProductMasterConsoleI18nTest`, loaded only by `--filter`/full-suite. Bare-path run fails that one test (expected).
- Branch `ralph/parties-enhanced-kyc-threshold`. PG17 not runnable locally (no PG server) → CHECK-rejection + bigint-string branches verify in CI / task 7.1 close.

## Active Change & Next Task
- **ACTIVE: `parties-enhanced-kyc-threshold` (RM-02, P0 compliance floor) — building. 5/12 done.**
- **⭐ NEXT: task 3.1** — contract `CustomerTransactionTotalsReader::forCustomer(int $customerId): CustomerTransactionTotals` in `app/Modules/Parties/Contracts/` + DTO `CustomerTransactionTotals` carrying two readonly `App\Platform\Money\Money` (EUR): `largestSingleTransaction`, `trailingTwelveMonthCumulative`. **Docblock the rolling trailing-12-month window** (design D3) + that the real impl is Module S's (deferred). Test: an in-test fake returns caller-set totals; unit-assert the DTO holds them.
- **Then 3.2** `NullCustomerTransactionTotalsReader` in `app/Modules/Parties/Reads/` (both fields `Money::of(0, Currency::of('EUR'))`) bound in `PartiesServiceProvider::register()` (the `PartyComplianceStatusReader` precedent); arch assertion it references no `App\Modules\Commerce\*`. Then §4 Actions (4.1 `CreateComplianceReview`, 4.2 `EvaluateEnhancedKycThreshold`), §5 command, §6 console, §7 close.
- **Event landed (2.3):** `final CustomerEnhancedKycReviewRequired` — `NAME`/`ENTITY_TYPE='Customer'`, `static payload(Customer,ComplianceReview)` = `customer_id`, `enhanced_kyc_at` (`?->toIso8601String()`), `threshold_kind` (`->value`), `amount` (`Money::of($review->tripped_amount_minor, Currency::of($review->tripped_currency))->toPayload()`). Recorder writer is task 4.2's `EvaluateEnhancedKycThreshold` (not the model).

## Blockers & Decisions Needed
- **None blocking.**
- **Implementer landmine (design D2):** resolving the AML `under_review` from the console re-tags `trigger_source=compliance_ad_hoc` (§9.5 — console never offers `aml_threshold`). CORRECT; AML origin stays durable on the review-queue row + event. **Do NOT force `aml_threshold` onto the resolution.**
- **Deferred seams:** real Module-S totals source + order-completion trigger; 12-month cadence job; screening-vendor adapter; enhanced-KYC doc-FSM; review-queue resolve action. Ad-hoc re-screen ships.

## Open Patterns
- **Shipped event idiom:** `final` class; `const NAME`/`ENTITY_TYPE`; `static payload(...): array`; STRICT PII-free (Customer PII = name/email/phone/dob stays out); `Money::toPayload()` → `{minor_units, currency}` for a money key (`ClubCreated::fee` precedent); a persisted timestamp column serialises via `?->toIso8601String()` (single source of truth, `CustomerAnonymised` idiom). Net-new event admissible over frozen-§15.6 silence (D5, RM-01 `CustomerAnonymised` precedent).
- **Event/value unit test = no DB:** `TestCase` (no `RefreshDatabase`), fixtures via `factory()->make([...])`; override a `Customer::factory()` FK with an explicit `customer_id` so `make()` resolves no parent/touches no DB; a `make()`-only money scalar stays a PHP int → `->toBe` safe (PG bigint-as-string trap only bites DB reads).
- **Sibling model/factory idiom** (`Address`/`ClubCredit`): within-module `belongsTo`, `$guarded=[]`, per-module factory + `newFactory()` override; assert cast bigint with model `->toBe` AND raw column `->toEqual`.
- **`*EnumsTest` idiom:** verbatim order-sensitive case→value map, `toHaveCount(n)`, `from()` round-trip, out-of-domain `from()` → `ValueError`.
- **Value-set CHECK shape follows nullability;** derive from `Enum::cases()` (PG-guarded); SQLite floor is the cast. Read DB scalars with `->value('col')`.
- **Inbound cross-module seam = read-port + null adapter** (K needs Module-S data); ship contract + zero adapter now.
- **Scheduler tick ≠ queued consumer** (substrate ADR): a `->daily()` command doesn't trip the queue-driver gate.
- **Console i18n = chrome vs domain split** (`operator_console.customer.*` vs `parties.compliance_review.*`); enums carry no `label()`.
