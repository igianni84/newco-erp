---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-02 (`parties-enhanced-kyc-threshold`) task 3.1 ✅ (contract `CustomerTransactionTotalsReader` + DTO `CustomerTransactionTotals` — the Module-S totals read-port seam, two EUR `Money` + rolling-12mo docblock, + pure-value unit test), green. 6/12 done.** Next: §3 close, task 3.2 (null adapter + binding).

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Last green: full suite **1920/1920** (SQLite `:memory:`), PHPStan max **0**, Pint clean, `openspec validate --strict` valid. (+4 from RM-02 task 3.1: `CustomerTransactionTotalsReaderTest`.)
- ⚠ **Run the full suite as `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child that ignores `-d`; the suite exhausts the 128M CLI default at result-collection (fatal, NOT a failure). Filtered `artisan test --filter=`/by-path is fine at 128M.
- ⚠ `CustomerConsoleI18nTest` **cannot run by bare path** — its sink scanner (`scanOperatorConsoleHardcodedSinks`) is declared in `ProductMasterConsoleI18nTest`, loaded only by `--filter`/full-suite. Bare-path run fails that one test (expected).
- Branch `ralph/parties-enhanced-kyc-threshold`. PG17 not runnable locally (no PG server) → CHECK-rejection + bigint-string branches verify in CI / task 7.1 close.

## Active Change & Next Task
- **ACTIVE: `parties-enhanced-kyc-threshold` (RM-02, P0 compliance floor) — building. 6/12 done.** §2 Domain + §3.1 port contract COMPLETE.
- **⭐ NEXT: task 3.2** — `NullCustomerTransactionTotalsReader` in `app/Modules/Parties/Reads/` returning `Money::of(0, Currency::of('EUR'))` for both DTO fields; bind `CustomerTransactionTotalsReader` → it in `PartiesServiceProvider::register()` (the `PartyComplianceStatusReader`→`DatabaseComplianceStatusReader` bind precedent, provider line 26). Test: feature — `app(CustomerTransactionTotalsReader::class)->forCustomer($id)` returns 0 EUR both fields; **arch assertion** the null adapter references no `App\Modules\Commerce\*` (`ModuleBoundariesTest` stays green).
- **Then §4 Actions:** 4.1 `CreateComplianceReview` (Create*, sole review-row writer, no event); 4.2 `EvaluateEnhancedKycThreshold` (idempotent workflow — locked re-read, €10k-single OR €50k-cumulative, flag+timestamp+review+event+`RecordCustomerScreening(under_review, aml_threshold)`; **add to the exact-set whitelist in `ComplianceIndependenceTest`**). Then §5 command, §6 console, §7 close.
- **Landed 3.1:** contract + DTO in `Contracts/` (plain readonly carrier — `ComplianceStatus` precedent); `forCustomer(int)` mirrors `PartyComplianceStatusReader`; rolling trailing-12mo window documented (design D3, NOT calendar-YTD); no currency guard (EUR-safety lives in 4.2's `Money` arithmetic).

## Blockers & Decisions Needed
- **None blocking.**
- **Implementer landmine (design D2):** resolving the AML `under_review` from the console re-tags `trigger_source=compliance_ad_hoc` (§9.5 — console never offers `aml_threshold`). CORRECT; AML origin stays durable on the review-queue row + event. **Do NOT force `aml_threshold` onto the resolution.**
- **Deferred seams:** real Module-S totals source + order-completion trigger; 12-month cadence job; screening-vendor adapter; enhanced-KYC doc-FSM; review-queue resolve action. Ad-hoc re-screen ships.

## Open Patterns
- **Boundary read-port idiom:** plain readonly DTO in `Contracts/` beside its interface; PII-free; `forCustomer(int)` naming (`ComplianceStatus`/`PartyComplianceStatusReader` → now `CustomerTransactionTotals`/`CustomerTransactionTotalsReader`). Inbound cross-module seam = read-port + null adapter; ship contract + zero adapter now (K needs Module-S data).
- **Pure-value unit test needs NO `uses(TestCase::class)`** — `tests/Pest.php` binds `TestCase` only to `Feature`; a no-DB/no-cast value test (Money + DTO + anonymous fake) runs on the plain PHPUnit base. Sibling event/model no-DB tests DO call `uses(TestCase::class)` — they need the boot for model enum/datetime CASTS; a pure DTO has none.
- **Shipped event idiom:** `final`; `const NAME`/`ENTITY_TYPE`; `static payload(...)`; STRICT PII-free; `Money::toPayload()` → `{minor_units, currency}`; persisted timestamp serialises via `?->toIso8601String()`.
- **`*EnumsTest` idiom / value-set CHECK follows nullability** (derive from `Enum::cases()`, PG-guarded); read DB scalars with `->value('col')` + assert money via `->toEqual`.
- **Scheduler tick ≠ queued consumer** (substrate ADR): a `->daily()` command doesn't trip the queue-driver gate.
