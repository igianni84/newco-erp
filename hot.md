---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-kyc-sanctions` — ralph loop RUNNING; task 3.1 done, 7/13).** Added the sanctions-screening FORM to `ViewCustomer`: `private function recordScreeningAction(): Action` (bespoke, the `placeHold` precedent — NOT `lifecycleAction`), appended to `getHeaderActions()` after `placeHoldAction()`. `Action::make('recordScreening')->schema([verdict Select, trigger_source Select])->action(function (): void {})` — the no-op body mirrors placeHold's 3.1 step (write-through is 3.2). `verdict` → new `screeningVerdictOptions()` (`SanctionsStatus::cases()` keyed `->value=>->value`). `trigger_source` → `->options(fn () => $this->screeningSourceOptions($this->recordOf(Customer::class,$this->getRecord())))`; `screeningSourceOptions(Customer)` returns `['compliance_ad_hoc'…]`, prepending `['onboarding'…]` iff `last_screening_at===null` (design D6 — onboarding-first; `cadence`/`aml_threshold` never offered). Imported operand enums `Parties\Enums\{SanctionsStatus,ScreeningTriggerSource}` (BOTH used — carve-out); NOT `RecordCustomerScreening` (3.2) nor `KycStatus` (state enum). Test +1 `it`: mount → verdict options === `SanctionsStatus::cases()->value`; never-screened → `['onboarding','compliance_ad_hoc']`, screened → `['compliance_ad_hoc']`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session): full suite 1489/1489 (8146 assn, exit 0)** — SQLite (was 1488/8137; +1 test/+9 assn). `--filter=CustomerKycSanctionsConsoleTest` 27/27 (158 assn, was 26/149). PHPStan max 0 errors; `pint --test` clean; `openspec validate --strict` valid. Diff = `ViewCustomer.php` + `CustomerKycSanctionsConsoleTest.php` only; no production-source outside ViewCustomer, no `spec/`/`openspec/specs/`/`tests/Architecture/`, no migration, no composer dep. `ModuleBoundariesTest` UNCHANGED (operand-enum import rides the `Parties\Enums` namespace-prefix carve-out — verified line 61).
- Full suite OOMs at PHP default 128 MB in result *parsing* (not a failure) — run pest with `php -d memory_limit=-1`. `--filter` + phpstan run fine at default. PG17 ritual is task 4.2 (not run this slice).

## Active Change & Next Task
- **`operator-console-parties-kyc-sanctions` — APPROVED, IN PROGRESS (7/13).** Delta on `operator-console`: 2 ADDED (KYC; sanctions) + 2 MODIFIED Customer reqs; 13 tasks / 5 groups. Groups 1+2 (KYC) DONE; 3.1 (sanctions form) DONE.
- **Next task 3.2:** swap recordScreening's no-op for the write-through — `->action(function (array $data): void { … })` narrowing each operand with `is_string` (Holds form-data discipline), then `$this->surfaceLifecycleOutcome(fn () => app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::from($verdict), ScreeningTriggerSource::from($source)), (string) __('operator_console.customer.notifications.screening_recorded'))`. NO `$model->save()`. Import `RecordCustomerScreening` THEN. Test: passed/onboarding → `CustomerOnboardingScreeningPassed` + `next_rescreen_at` exactly 12mo after `last_screening_at` + `screening_trigger_source=onboarding`; failed/compliance_ad_hoc → `CustomerRescreeningFailed`; under_review/pending → zero screening events; kyc_status untouched (independence).
- Then 3.3 (onboarding-first floor `toThrow IllegalSanctionsTransition`), group 4 (PG17 chain test 4.1 + ritual 4.2), 5 (gates+memory). After all 13: review/merge → semantic-verify (GUIDE §2.7) → `openspec archive`.

## Blockers & Decisions Needed
- **None blocking task 3.2.** No open-ADR gate crossed (operator auth shipped; recordScreening invokes synchronous `RecordCustomerScreening`).
- **Holds push gate STILL pending** (separate change) — origin/main push of the Holds archive+merge commits + `git branch -d ralph/operator-console-parties-holds` await Giovanni's go (classifier-gated). Local merge+archive already done.

## Open Patterns
- **Bespoke form action = form-FIRST (no-op `->action(function (): void {})`) then write-through-NEXT** (the placeHold precedent; a form-bearing Action must have an `->action()` to mount). Record-dependent Select options = `->options(Closure)` reading `$this->getRecord()`, offered set the EXACT COMPLEMENT of the domain floor (onboarding drops once `last_screening_at!==null`). `Select::getOptions()` resolves the closure (`HasOptions` line 36 `evaluate`). Enum Select options keyed `->value=>->value`, NEVER magic strings. Consolidated in this change's `progress.md ## Codebase Patterns`.
- **kyc-sanctions enum discipline:** `KycStatus` = STATE enum (cast `->value`, NEVER imported in production); `SanctionsStatus`/`ScreeningTriggerSource` = OPERAND enums (imported — `Parties\Enums` carve-out, `ModuleBoundariesTest` unchanged). KYC verbs event-silent (assert coupled Hold/status events, never `CustomerKyc%` — D7); sanctions screening EMITS (onboarding/rescreening × passed/failed; `under_review`/`pending` emit nothing). Chain-test (4.1) asserts exactly 5 events.
- **`assertActionDoesNotExist` (verb NOT registered) ≠ `assertActionHidden` (registered, `->visible()` false).** Reject-floor `toThrow` under `DatabaseMigrations`: the Action's `DB::transaction` genuinely rolls back, so state + event log demonstrably unchanged.
