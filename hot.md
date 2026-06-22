---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-kyc-sanctions` — ralph loop RUNNING; task 3.2 done, 8/13).** Wired the sanctions write-through on `ViewCustomer::recordScreeningAction()`: swapped the 3.1 no-op `->action(function (): void {})` for the `placeHold`-shaped `->action(function (array $data): void { … })`. Narrows BOTH operands with `is_string($data[...] ?? null) ? ... : ''` (`$verdict`/`$source`, the Holds form-data discipline), then `$this->surfaceLifecycleOutcome(fn () => app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::from($verdict), ScreeningTriggerSource::from($source)), (string) __('operator_console.customer.notifications.screening_recorded'))`. NO `$model->save()`. Imported `Parties\Actions\RecordCustomerScreening` (Actions carve-out; the two operand enums already imported in 3.1). Docblock updated (write-through now wired; `IllegalSanctionsTransition::onboardingAlreadyScreened` named in PROSE → no forbidden import). Test +3 `it`/+4 cases: (a) never-screened+`kyc_status=Verified` → passed/onboarding → `Passed` + 12-mo window + `kyc_status` STILL Verified (independence) + one `CustomerOnboardingScreeningPassed` w/ operator envelope; (b) already-screened → failed/compliance_ad_hoc → one `CustomerRescreeningFailed`; (c) under_review/pending × onboarding → status moves, ZERO `Customer%creening%` events.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session): full suite 1493/1493 (8189 assn, exit 0)** — SQLite (was 1489/8146; +4 tests/+43 assn). `--filter=CustomerKycSanctionsConsoleTest` 31/31 (201 assn, was 27/178). RED-first confirmed (4 new tests "notification not sent" against the no-op). PHPStan max 0 errors; `pint --test` clean; `openspec validate --strict` valid. Diff = `ViewCustomer.php` + `CustomerKycSanctionsConsoleTest.php` only; no `spec/`/`openspec/specs/`/`tests/Architecture/`, no migration, no composer dep. `ModuleBoundariesTest` UNCHANGED (the `RecordCustomerScreening` import rides the `Parties\Actions` carve-out).
- Full suite OOMs at PHP default 128 MB in result *parsing* (not a failure) — run pest with `php -d memory_limit=-1`. `--filter` + phpstan run fine at default. PG17 ritual is task 4.2 (not run this slice).

## Active Change & Next Task
- **`operator-console-parties-kyc-sanctions` — APPROVED, IN PROGRESS (8/13).** Delta on `operator-console`: 2 ADDED (KYC; sanctions) + 2 MODIFIED Customer reqs; 13 tasks / 5 groups. Groups 1+2 (KYC) DONE; 3.1 (form) + 3.2 (write-through) DONE.
- **Next task 3.3:** the onboarding-first FLOOR (design D6, the D4 twin at the form-option level). In `CustomerKycSanctionsConsoleTest`: on an already-screened Customer the form drops `onboarding` (covered by 3.1) AND the domain is the floor — `expect(fn () => app(RecordCustomerScreening::class)->handle($id, SanctionsStatus::Passed, ScreeningTriggerSource::Onboarding))->toThrow(IllegalSanctionsTransition::class)` (import it FREELY in the test), `sanctions_status` + event log unchanged. `IllegalSanctionsTransition extends RuntimeException`; arrange the already-screened Customer via factory `last_screening_at`/`sanctions_status`.
- Then group 4 (PG17 chain test 4.1 + ritual 4.2), 5 (quality gates + memory). After all 13: review/merge → semantic-verify (GUIDE §2.7) → `openspec archive`.

## Blockers & Decisions Needed
- **None blocking task 3.3.** No open-ADR gate crossed (operator auth shipped; recordScreening invokes synchronous `RecordCustomerScreening`).
- **Holds push gate STILL pending** (separate change) — origin/main push of the Holds archive+merge commits + `git branch -d ralph/operator-console-parties-holds` await Giovanni's go (classifier-gated). Local merge+archive already done.

## Open Patterns
- **Bespoke form action write-through = the EXACT placeHold shape:** narrow each `$data[...]` operand via `is_string(... ?? null) ? ... : ''`, build the typed enum with `::from()` INSIDE the `surfaceLifecycleOutcome` closure (the `: ''` floor would `ValueError` but `->required()` gates it — the risk profile placeHold accepts). Consolidated in this change's `progress.md ## Codebase Patterns`.
- **Operator audit envelope on an EMITTING action:** `actingAs($operator,'operator')` → `ActorContext` resolves `NewcoOps`+operator id; assert `actor_id` LOOSE (`toEqual`, PG bigint-as-string), `entity_id` `(string)`. The domain `CustomerSanctionsLifecycleTest` asserts the same event w/ `ActorRole::System` (no actingAs) — the console test is the operator twin. `next_rescreen_at === last_screening_at+12mo` via `?->toDateTimeString()` on both (CarbonImmutable casts).
- **kyc-sanctions enum discipline:** `KycStatus` = STATE enum (cast `->value`, NEVER imported in production); `SanctionsStatus`/`ScreeningTriggerSource` = OPERAND enums (imported — carve-out). KYC verbs event-silent (D7); sanctions screening EMITS (onboarding/rescreening × passed/failed; under_review/pending emit nothing). Chain-test (4.1) asserts exactly 5 events, none KYC-named.
