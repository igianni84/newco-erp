---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-02 (`parties-enhanced-kyc-threshold`) task 5.1 ✅ — `ScanEnhancedKycThresholds` (the periodic trigger path): command `parties:scan-enhanced-kyc-thresholds` `foreach (Customer::lazyById())` → `EvaluateEnhancedKycThreshold`; registered in `bootstrap/app.php` `withCommands()`, scheduled `->daily()` (`0 0 * * *`) in `routes/console.php`; inline on the tick, no queue gate; actor = System for free. 5-test pin (per-customer fake reader), green. 10/12 done.** Next: task 6.1 the read-only operator-console surface.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Last green: full suite **1940/1940** (SQLite `:memory:`), PHPStan max **0**, Pint clean, `openspec validate --strict` valid. (+5 from 5.1.) Runtime `artisan list`/`schedule:list` confirm the command + `0 0 * * *`.
- ⚠ **Run the full suite as `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child ignoring `-d`; the suite exhausts the 128M default at result-collection (fatal, NOT a failure). Filtered/by-path runs fit 128M.
- ⚠ `CustomerConsoleI18nTest` **cannot run by bare path** — its sink scanner is declared in `ProductMasterConsoleI18nTest`, loaded only by `--filter`/full-suite.
- Branch `ralph/parties-enhanced-kyc-threshold`. PG17 not runnable locally → CHECK-rejection + bigint-string branches verify in CI / task 7.1 close.

## Active Change & Next Task
- **ACTIVE: `parties-enhanced-kyc-threshold` (RM-02, P0 compliance floor) — 10/12 done.** §2 Domain, §3 totals-port seam, §4 detection workflow, §5 periodic trigger path COMPLETE.
- **⭐ NEXT: task 6.1** — read-only surface on the OperatorPanel Customer console (`ViewCustomer`): `enhanced_kyc_flag`/`enhanced_kyc_at` + the Customer's OPEN reviews (`resolved_at IS NULL`) with `threshold_kind` + amount. **Read-projection ONLY** (no write action — resolve deferred). i18n keys ALREADY landed (task 1.2): chrome `operator_console.customer.compliance_reviews.*` + heading `customer.sections.compliance_reviews` + domain labels `parties.compliance_review.{reason,threshold_kind}.*`. `NoEloquentWriteInOperatorPanelRuleTest` MUST stay green. Test: Filament/Livewire page — escalate via the Action, `assertSee` flag+review; `assertDontSee` for an un-escalated Customer.
- **Then** task 7.1 integration + cross-engine close: whole chain through real Actions + operator resolution → `CustomerRescreeningPassed`; full suite + PHPStan + Pint on **PostgreSQL 17**.

## Blockers & Decisions Needed
- **None blocking.**
- **Design D2 landmine (durable):** resolving the AML `under_review` from the console re-tags `trigger_source=compliance_ad_hoc` (§9.5 — console never offers `aml_threshold`). CORRECT; AML origin stays durable on the review row + event. Do NOT force `aml_threshold` onto the resolution.

## Open Patterns
- **Scheduled MODULE console command = 3 wiring points + 5-part test** (full recipe in progress.md Codebase Patterns): class in `app/Modules/{M}/Console/` (not auto-discovered) → `bootstrap/app.php` `withCommands()` + `routes/console.php` `Schedule::command('sig')->daily()`; iterate `foreach (Model::query()->lazyById() as $r)` (id-cursor, mutation-safe); actor = System free (no `runAs` in app). Test under `DatabaseMigrations` (`Artisan::call`): behaviour + `Artisan::all()->toHaveKey('sig')` + schedule via kernel bootstrap → `Schedule::events()->sole()` → `$e->expression==='0 0 * * *'`. Per-caller fake keyed on the loop id proves one-row escalation.
- **Compare `Money` `≥` via `$a->minus($b)->minorUnits >= 0`** — no comparison op; `minus()` throws on currency mismatch → fail-closed. Thresholds = `int` minor-unit consts.
- **Mid-tx throw WITHOUT Mockery = throwing anonymous subclass** of the concrete collaborator (signature mirrors parent). Pair with `DatabaseMigrations` for atomicity proofs.
- **Non-`Create*` Parties Action → add to `$complianceTransitions` in `SupplyLifecycleChainTest`** (exact-set). `Create*` auto-filtered.
- **RAW DB scalar = `DB::table('t')->value('col')` (FACADE, bypasses casts)**; assert bigint/money via `->toEqual` (PG bigint-as-string).
