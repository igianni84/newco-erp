---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-02 (`parties-enhanced-kyc-threshold`) task 4.1 ✅ (`CreateComplianceReview` — thin `Create*` action, sole review-row writer; splits `Money`→`tripped_amount_minor`+`tripped_currency`; NO event, NO tx; 3-test Feature pin), green. 8/12 done.** Next: task 4.2 `EvaluateEnhancedKycThreshold` (the orchestrator).

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Last green: full suite **1927/1927** (SQLite `:memory:`), PHPStan max **0**, Pint clean, `openspec validate --strict` valid. (+3 from RM-02 4.1: `CreateComplianceReviewTest`.)
- ⚠ **Run the full suite as `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child that ignores `-d`; the suite exhausts the 128M CLI default at result-collection (fatal, NOT a failure). Filtered `artisan test --filter=`/by-path is fine at 128M.
- ⚠ `CustomerConsoleI18nTest` **cannot run by bare path** — its sink scanner (`scanOperatorConsoleHardcodedSinks`) is declared in `ProductMasterConsoleI18nTest`, loaded only by `--filter`/full-suite.
- Branch `ralph/parties-enhanced-kyc-threshold`. PG17 not runnable locally → CHECK-rejection + bigint-string branches verify in CI / task 7.1 close.

## Active Change & Next Task
- **ACTIVE: `parties-enhanced-kyc-threshold` (RM-02, P0 compliance floor) — building. 8/12 done.** §2 Domain, §3 totals-port seam, task 4.1 writer COMPLETE.
- **⭐ NEXT: task 4.2** — `EvaluateEnhancedKycThreshold` action (`handle(int $customerId)`). In ONE `DB::transaction`: `lockForUpdate()` re-read Customer; **no-op if `enhanced_kyc_flag` set**; else read totals via injected `CustomerTransactionTotalsReader`; breach = `largestSingleTransaction ≥ Money::of(1_000_000,'EUR')` **OR** `trailingTwelveMonthCumulative ≥ Money::of(5_000_000,'EUR')` (inclusive; named constants; single wins if both). On breach: (a) set `enhanced_kyc_flag=true`+`enhanced_kyc_at=now`; (b) `CreateComplianceReview`; (c) record `CustomerEnhancedKycReviewRequired` via `DomainEventRecorder` (actor from `ActorContext`); (d) `RecordCustomerScreening($id, SanctionsStatus::UnderReview, ScreeningTriggerSource::AmlThreshold)` (reused unchanged). Feature test with a fake totals reader: single-tx + cumulative breach, idempotent re-run, sub-threshold no-op, atomic mid-tx-throw, event-set excludes `CustomerRescreening*`.
- **Then** §5 command `parties:scan-enhanced-kyc-thresholds` (5.1), §6 console read-surface (6.1), §7 integration+cross-engine close (7.1).

## Blockers & Decisions Needed
- **None blocking.**
- **⚠ VERIFIED LANDMINE for task 4.2 — the RALPH task text names the WRONG file.** Add the new non-`Create*` action `EvaluateEnhancedKycThreshold` to the exact-set whitelist in **`SupplyLifecycleChainTest.php`** — the `$complianceTransitions` array (line ~305, beside `RecordCustomerScreening`); the `toEqualCanonicalizing([...])` at line ~405 fails otherwise. Do **NOT** touch `ComplianceIndependenceTest` (task text says to, but its check is a NEGATIVE forbidden-name list — adding an action there forbids one that must exist). `Create*` actions auto-filtered (line 404) — 4.1 needed no edit (confirmed green).
- **Design D2 landmine:** resolving the AML `under_review` from the console re-tags `trigger_source=compliance_ad_hoc` (§9.5 — console never offers `aml_threshold`). CORRECT; AML origin stays durable on the review row + event.

## Open Patterns
- **RAW DB scalar = `DB::table('t')->value('col')` (FACADE, bypasses casts), NOT `Model::query()->value('col')`** (Eloquent re-applies casts → returns the enum/Carbon, not the raw string). Assert bigint/money via `->toEqual` (PG bigint-as-string); a closure `fn () => DB::table(...)->where('id',$id)` gives a fresh builder per read.
- **Thin `Create*` action = no event, no tx** (`CreateCustomerAddress`/`CreateComplianceReview`): a single `Model::create()` is atomic and composes into a caller's outer `DB::transaction`. Named `Create*` so `SupplyLifecycleChainTest` auto-filters it (not a lifecycle transition).
- **`Currency::EUR` (literal enum case) for a compile-time-constant EUR**; `Currency::of('EUR')` is ONLY for dynamic string→currency rehydration (a DB column / payload). `Money` splits to scalars via `->minorUnits` + `->currency->value`; re-assembles via `Money::of($minor, Currency::of($code))`.
- **Inbound cross-module seam = read-port + null adapter** (`CustomerTransactionTotalsReader`→`Null…`, bound in `PartiesServiceProvider`); real adapter lands with Module S.
- **Arch assertion on ONE class:** `expect(FQCN::class)->not->toUse('App\Modules\X')` works in a Feature file (pest-arch treats a FQCN as a singleton layer).
