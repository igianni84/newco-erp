---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-02 (`parties-enhanced-kyc-threshold`) task 6.1 ✅ — the read-only enhanced-KYC operator surface on `ViewCustomer`, as TWO read projections: (a) a DISTINCT infolist Section (`CustomerResource::infolist()`, heading `sections.compliance_reviews`, icon `heroicon-o-document-check`) VISIBILITY-GATED to a flagged Customer (new `private static wasEnhancedKycFlagged()` → `enhanced_kyc_flag === true`) carrying `IconEntry->boolean()` (flag, null-safe) + `enhanced_kyc_at` dateTime — the gate makes "un-escalated shows NEITHER" literal; (b) footer widget `CustomerComplianceReviewsTable` (mirrors `CustomerHoldsTable`) listing OPEN reviews only (`where('customer_id',$id)->whereNull('resolved_at')`), columns reason/threshold_kind (cast `->value` → `parties.compliance_review.*` domain copy, no enum import) + amount (`number_format(minor/100,2).' '.currency`, ClubResource fee idiom) + opened_at, NO per-row action. Registered in `getFooterWidgets()`. 4-test pin (`RefreshDatabase`, factory-stood state). 11/12 done.** Next: task 7.1 the closing cross-engine integration test — the LAST task.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Last green: full suite **1944/1944** (SQLite `:memory:`), PHPStan max **0**, Pint clean, `openspec validate --strict` valid. (+4 from 6.1.) `NoEloquentWriteInOperatorPanelRule` green over the new console code (widget reads only).
- ⚠ **Run the full suite as `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child ignoring `-d`; the suite exhausts the 128M default at result-collection (fatal, NOT a failure). Filtered/by-path runs fit 128M.
- ⚠ `CustomerConsoleI18nTest` **cannot run by bare path** — its sink scanner is declared in `ProductMasterConsoleI18nTest`, loaded only by `--filter`/full-suite.
- Branch `ralph/parties-enhanced-kyc-threshold`. PG17 not runnable locally → CHECK-rejection + bigint-string branches verify in CI / task 7.1 cross-engine close.

## Active Change & Next Task
- **ACTIVE: `parties-enhanced-kyc-threshold` (RM-02, P0 compliance floor) — 11/12 done.** §2–§6 COMPLETE (domain, totals seam, detection workflow, periodic trigger, read-only console).
- **⭐ NEXT: task 7.1 (the LAST task)** — one Feature test driving the WHOLE chain **through the real Actions** (fake totals reader): breach → `EvaluateEnhancedKycThreshold` → assert flag+`enhanced_kyc_at`, review row, `CustomerEnhancedKycReviewRequired`, `sanctions_status=under_review`/`aml_threshold`, **no `CustomerRescreening*` yet**; THEN operator resolution via `RecordCustomerScreening(passed, compliance_ad_hoc)` (the `ViewCustomer` re-screen action) → assert `CustomerRescreeningPassed` fires (outcome events identical to the cadence path). Assert the emergent event-SET with `DomainEvent::query()->distinct()->pluck('name')->toEqualCanonicalizing([...])`. `DatabaseMigrations` for commit/rollback legs; money scalars via `->toEqual` (PG bigint-string). Run full suite + PHPStan + Pint on **PostgreSQL 17**.
- **On 7.1 done → final pass (re-verify every acceptance bullet, all gates green) then reply `<promise>CHANGE_COMPLETE</promise>`** (do NOT archive/merge — humans do that).

## Blockers & Decisions Needed
- **None blocking.**
- **Design D2 landmine (durable):** resolving the AML `under_review` from the console re-tags `trigger_source=compliance_ad_hoc` (§9.5 — console never offers `aml_threshold`). CORRECT; AML origin stays durable on the review row + event. Do NOT force `aml_threshold` onto the resolution (task 7.1 asserts `aml_threshold` **at breach**, `compliance_ad_hoc` at resolution).

## Open Patterns
- **Read-only NON-RELATION list on a Customer console = a SECOND footer `TableWidget`** (not RelationManager/RepeatableEntry): under `CustomerResource/Widgets/`, `?Customer $record`, `$isLazy=false` (inline render → page `assertSee`), `->query(Child::query()->where('customer_id',$this->record?->id)…)`, enum cols via cast `->value`→module domain copy (no enum import → `ModuleBoundariesTest` unchanged), money via `number_format(minor/100,2).' '.currency`, NO row action. Register in `getFooterWidgets()` with `::make(['record'=>…])`. A Customer SCALAR → infolist `Section` `->visible(fn(Customer $r)=>$r->flag===true)` when "un-escalated shows neither"; render a nullable bool via `IconEntry->boolean()` (null-safe — `fn(bool $state)` TypeErrors on NULL). Reuse only front-loaded i18n keys (adding keys risks `customerConsoleKitKeys()` pins).
- **Compare `Money` `≥` via `$a->minus($b)->minorUnits >= 0`** — no comparison op; `minus()` throws on currency mismatch → fail-closed. Thresholds = `int` minor-unit consts.
- **RAW DB scalar = `DB::table('t')->value('col')` (FACADE, bypasses casts)**; assert bigint/money via `->toEqual` (PG bigint-as-string).
- **Non-`Create*` Parties Action → add to `$complianceTransitions` in `SupplyLifecycleChainTest`** (exact-set). `Create*` auto-filtered.
