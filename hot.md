---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-02 (`parties-enhanced-kyc-threshold`) task 4.2 ✅ (`EvaluateEnhancedKycThreshold` — the detection orchestrator: locked idempotent latch on `enhanced_kyc_flag`, €10k-single OR €50k-cumulative inclusive breach via `Money::minus()->minorUnits >= 0` fail-closed, 4 atomic writes a–d, whitelist +1 in `SupplyLifecycleChainTest`; 8-test `DatabaseMigrations` pin incl. atomic-rollback), green. 9/12 done.** Next: task 5.1 the periodic scan command.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Last green: full suite **1935/1935** (SQLite `:memory:`), PHPStan max **0**, Pint clean, `openspec validate --strict` valid. (+8 from RM-02 4.2: `EvaluateEnhancedKycThresholdTest`.)
- ⚠ **Run the full suite as `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child that ignores `-d`; the suite exhausts the 128M CLI default at result-collection (fatal, NOT a failure). Filtered `artisan test --filter=`/by-path is fine at 128M.
- ⚠ `CustomerConsoleI18nTest` **cannot run by bare path** — its sink scanner (`scanOperatorConsoleHardcodedSinks`) is declared in `ProductMasterConsoleI18nTest`, loaded only by `--filter`/full-suite.
- Branch `ralph/parties-enhanced-kyc-threshold`. PG17 not runnable locally → CHECK-rejection + bigint-string branches verify in CI / task 7.1 close.

## Active Change & Next Task
- **ACTIVE: `parties-enhanced-kyc-threshold` (RM-02, P0 compliance floor) — building. 9/12 done.** §2 Domain, §3 totals-port seam, §4 detection workflow COMPLETE (4.1 writer + 4.2 orchestrator).
- **⭐ NEXT: task 5.1** — `ScanEnhancedKycThresholds` command (`parties:scan-enhanced-kyc-thresholds`) in `app/Modules/Parties/Console/`: chunk-iterate Customers, call `EvaluateEnhancedKycThreshold`; register in `bootstrap/app.php` `withCommands([...])`; schedule `->daily()` in `routes/console.php` (the `events:sweep` precedent). Runs **inline on the scheduler tick** — NO queue (design D7; queue-driver gate NOT tripped). Actor = `System` in console. Feature test: fake reader flags customer A (not B); `artisan('parties:scan-enhanced-kyc-thresholds')` → A flagged + one review + `under_review`, B untouched; command registered (`Artisan::all()`); second run idempotent.
- **Then** §6 console read-surface (6.1), §7 integration + cross-engine close (7.1).

## Blockers & Decisions Needed
- **None blocking.**
- **⚠ task 5.1 possible whitelist:** a new `parties:*` console command MAY need adding to a console-command inventory/exact-set test if one exists — grep for a command-registry test before assuming green (mirrors the 4.2 `SupplyLifecycleChainTest` landmine, now resolved).
- **Design D2 landmine (durable):** resolving the AML `under_review` from the console re-tags `trigger_source=compliance_ad_hoc` (§9.5 — console never offers `aml_threshold`). CORRECT; AML origin stays durable on the review row + event. Do NOT force `aml_threshold` onto the resolution.

## Open Patterns
- **Compare two `Money` with `≥` via `$a->minus($b)->minorUnits >= 0`** — `Money` has NO comparison op (only plus/minus/negate/equals/toPayload); `minus()` throws on currency mismatch → fail-closed, integer-only. Thresholds = `int` minor-unit `const`s, `Money::of(self::X, Currency::EUR)` at the compare.
- **Mid-tx throw WITHOUT Mockery (repo has zero Mockery) = bind a throwing anonymous subclass** of the concrete collaborator (`app()->bind(Action::class, fn () => new class(app(dep)...) extends Action { handle → throw })`), signature mirroring the parent exactly. Pair with `DatabaseMigrations` (real outermost tx) for invariant-grade atomicity proofs.
- **Non-`Create*` Parties Action → add to `$complianceTransitions` in `SupplyLifecycleChainTest`** (exact-set `toEqualCanonicalizing`), NOT `ComplianceIndependenceTest` (negative forbidden-name list). `Create*` auto-filtered.
- **RAW DB scalar = `DB::table('t')->value('col')` (FACADE, bypasses casts)**, assert bigint/money via `->toEqual` (PG bigint-as-string).
- **Inbound cross-module seam = read-port + null adapter** (`CustomerTransactionTotalsReader`→`Null…`, bound in `PartiesServiceProvider`); real adapter lands with Module S. Bind a fake BEFORE `app(Action::class)` (port is constructor-injected).
