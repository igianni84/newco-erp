---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-kyc-sanctions` — ralph loop RUNNING; task 3.3 done, 9/13; GROUP 3 COMPLETE).** Added the onboarding-first FLOOR test to `CustomerKycSanctionsConsoleTest` (+1 `it`, test-only — no production change). It's the **D4 twin at the form-option level**: where a form-less verb's reject floor proves `assertActionHidden` + domain `toThrow` (task 2.3), a record-dependent OPTION's floor proves the option DROPPED + the same domain `toThrow`. Both halves in one test on an already-screened Customer (`last_screening_at => now()`, `sanctions_status => Passed`, `screening_trigger_source => Onboarding`): **(Half 1, surface)** `mountAction('recordScreening')` → `trigger_source` `getOptions()` keys `=== ['compliance_ad_hoc']` (onboarding withdrawn — also covered by 3.1, the deliberate 2.1↔2.3-style overlap); **(Half 2, domain)** `expect(fn () => app(RecordCustomerScreening::class)->handle($id, SanctionsStatus::Passed, ScreeningTriggerSource::Onboarding))->toThrow(IllegalSanctionsTransition::class)` — the guard (`RecordCustomerScreening` line 79-81) fires BEFORE any write; **unchanged** `sanctions_status`=Passed + `screening_trigger_source`=Onboarding + `DomainEvent::count()===0`. Imported `Actions\RecordCustomerScreening` + `Exceptions\IllegalSanctionsTransition` (tests import Parties freely — carve-out is production-only).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session): full suite 1494/1494 (8196 assn, exit 0)** — SQLite (was 1493/8189; +1 test/+7 assn). `--filter=CustomerKycSanctionsConsoleTest` 32/32 (208 assn, was 31/201). PHPStan max 0 errors; `pint --test` clean; `openspec validate --strict` valid. This iteration's diff = `CustomerKycSanctionsConsoleTest.php` ONLY (+49/−2, test-only); no `spec/`/`openspec/specs/`/`tests/Architecture/`, no migration, no composer dep. `ModuleBoundariesTest` UNCHANGED.
- Full suite OOMs at PHP default 128 MB in result *parsing* (not a failure) — run pest with `php -d memory_limit=-1`. `--filter` + phpstan run fine at default. PG17 ritual is task 4.2 (not run this slice).

## Active Change & Next Task
- **`operator-console-parties-kyc-sanctions` — APPROVED, IN PROGRESS (9/13).** Delta on `operator-console`: 2 ADDED (KYC; sanctions) + 2 MODIFIED Customer reqs; 13 tasks / 5 groups. Groups 1+2 (KYC) + 3 (sanctions: form/write-through/floor) DONE.
- **Next task 4.1:** the PG17 closing-CHAIN integration test (design D11). NEW file `tests/Feature/Modules/OperatorPanel/Parties/CustomerKycSanctionsChainTest.php` using `DatabaseMigrations` (NOT `RefreshDatabase` — each console action opens its own `DB::transaction`; the in-tx event append must commit). PROFILE-LESS Customer (silent suspend/reactivate cascade → exact event count). Drive requireKyc → recordKycVerified → recordScreening(passed/onboarding) through `ViewCustomer` AND the shipped `CustomerHoldsTable` footer widget (`assertTableActionHidden('lift', record: $kycHold)` — auto-managed). Assert the emergent set `pluck('name')->toEqualCanonicalizing(['CustomerHoldPlaced','CustomerSuspended','CustomerHoldLifted','CustomerReactivated','CustomerOnboardingScreeningPassed'])->toHaveCount(5)` — NO KYC event (D7); heterogeneous `entity_type` (Hold events → Hold/$hold->id; status+screening → Customer/$customer->id); SET-WIDE `module=parties`/`actor_role=NewcoOps`/`actor_id` not null.
- Then 4.2 (PG17 ritual), 5 (quality gates + memory). After all 13: review/merge → semantic-verify (GUIDE §2.7) → `openspec archive`.

## Blockers & Decisions Needed
- **None blocking task 4.1.** No open-ADR gate crossed.
- **Holds push gate STILL pending** (separate change) — origin/main push of the Holds archive+merge commits + `git branch -d ralph/operator-console-parties-holds` await Giovanni's go (classifier-gated). Local merge+archive already done.

## Open Patterns
- **Form-option-level floor = the D4 twin:** a record-dependent OPTION's floor proves the option dropped from `getOptions()` + a domain `toThrow`, seeded via the bare factory so the event log is a clean zero (the 2.3 reject-floor idiom, one level down). Consolidated in this change's `progress.md ## Codebase Patterns`.
- **kyc-sanctions enum discipline:** `KycStatus` = STATE enum (cast `->value`, NEVER imported in production); `SanctionsStatus`/`ScreeningTriggerSource` = OPERAND enums (imported — carve-out). KYC verbs event-silent (D7); sanctions screening EMITS (onboarding/rescreening × passed/failed; under_review/pending emit nothing). Chain-test (4.1) asserts exactly 5 events, none KYC-named.
- **Operator audit envelope on an EMITTING action:** `actingAs($operator,'operator')` → `ActorContext` resolves `NewcoOps`+operator id; assert `actor_id` LOOSE (`toEqual`, PG bigint-as-string), `entity_id` `(string)`. `next_rescreen_at === last_screening_at+12mo` via `?->toDateTimeString()` (CarbonImmutable casts).
