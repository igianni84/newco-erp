---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-02 (`parties-enhanced-kyc-threshold`) task 3.2 ✅ (`NullCustomerTransactionTotalsReader` + `PartiesServiceProvider` bind — the Module-S totals seam bound to a zero-EUR null adapter; arch-pinned Feature test), green. §3 CLOSED. 7/12 done.** Next: §4 Actions, task 4.1.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Last green: full suite **1924/1924** (SQLite `:memory:`), PHPStan max **0**, Pint clean, `openspec validate --strict` valid. (+4 from RM-02 3.2: `CustomerTransactionTotalsReaderBindingTest`.)
- ⚠ **Run the full suite as `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child that ignores `-d`; the suite exhausts the 128M CLI default at result-collection (fatal, NOT a failure). Filtered `artisan test --filter=`/by-path is fine at 128M.
- ⚠ `CustomerConsoleI18nTest` **cannot run by bare path** — its sink scanner (`scanOperatorConsoleHardcodedSinks`) is declared in `ProductMasterConsoleI18nTest`, loaded only by `--filter`/full-suite.
- Branch `ralph/parties-enhanced-kyc-threshold`. PG17 not runnable locally → CHECK-rejection + bigint-string branches verify in CI / task 7.1 close.

## Active Change & Next Task
- **ACTIVE: `parties-enhanced-kyc-threshold` (RM-02, P0 compliance floor) — building. 7/12 done.** §2 Domain + §3 totals-port seam COMPLETE.
- **⭐ NEXT: task 4.1** — `CreateComplianceReview` action (`Create*`, auto-allowed by the arch whitelist). Sole writer of ONE `parties_compliance_reviews` row `(customerId, reason, thresholdKind, Money $trippedAmount)` → persists `tripped_amount_minor` + `tripped_currency` from the `Money`; returns the model; records **NO** domain event (4.2's detection Action records `CustomerEnhancedKycReviewRequired`). Test (feature): one row (`reason=enhanced_kyc_threshold`, right `threshold_kind`, amount via `->toEqual`), `DomainEvent::count()` unchanged.
- **Then task 4.2** `EvaluateEnhancedKycThreshold` (idempotent: locked re-read, no-op if flag set; €10k-single OR €50k-cumulative inclusive; flag+`enhanced_kyc_at`+`CreateComplianceReview`+event+`RecordCustomerScreening(under_review, aml_threshold)`, all one tx). Then §5 command, §6 console, §7 close.

## Blockers & Decisions Needed
- **None blocking.**
- **⚠ VERIFIED LANDMINE for task 4.2 — the RALPH task text names the WRONG file.** Add the new non-`Create*` action `EvaluateEnhancedKycThreshold` to the exact-set whitelist in **`SupplyLifecycleChainTest.php`** — the `$complianceTransitions` array (line ~305, beside `RecordCustomerScreening`); the `toEqualCanonicalizing([...])` at line ~405 fails otherwise. Do **NOT** touch `ComplianceIndependenceTest` (task text says to, but its check is a NEGATIVE forbidden-name list — its own docblock: a new compliance Action "need not be declared" there; adding it would forbid an action that must exist). `Create*` actions are auto-filtered (line 404), so task 4.1 needs no whitelist edit.
- **Design D2 landmine:** resolving the AML `under_review` from the console re-tags `trigger_source=compliance_ad_hoc` (§9.5 — console never offers `aml_threshold`). CORRECT; AML origin stays durable on the review row + event.

## Open Patterns
- **`Currency::EUR` (literal enum case) for a compile-time-constant EUR** (`MoneyTest` / `Currency.php` docblock); `Currency::of('EUR')` is ONLY for dynamic string→currency rehydration (a DB column — `ComplianceReview`, the event payload). PHPStan does not flag an interface-mandated unused param.
- **Inbound cross-module seam = read-port + null adapter:** ship contract + zero adapter now (K needs Module-S data); real adapter lands with Module S. Bind precedent: `PartiesServiceProvider` (`PartyComplianceStatusReader`→`Database…`, now `CustomerTransactionTotalsReader`→`Null…`).
- **Arch assertion on ONE class:** `expect(FQCN::class)->not->toUse('App\Modules\X')` works in a Feature file (pest-arch treats a FQCN as a singleton layer) — no need to route through `tests/Architecture/`.
- **Pure-value unit test needs NO `uses(TestCase::class)`** (Pest binds `TestCase` to `Feature` only); a container/binding Feature test that touches no DB needs no `RefreshDatabase`.
- **`*EnumsTest` idiom / value-set CHECK follows nullability**; read DB scalars with `->value('col')` + assert money via `->toEqual` (PG bigint-as-string).
