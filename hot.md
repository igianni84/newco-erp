---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-kyc-sanctions` — ralph loop RUNNING; task 4.1 done, 10/13; GROUP 4 SQLite half done).** Added the NEW closing-chain integration test `tests/Feature/Modules/OperatorPanel/Parties/CustomerKycSanctionsChainTest.php` (test-only — no production change), the `CustomerHoldsChainTest` shape one slice up (design D11). One `it()` drives the WHOLE slice end-to-end through the `ViewCustomer` PAGE + the shipped `CustomerHoldsTable` WIDGET on a PROFILE-LESS `active` Customer (`uses(DatabaseMigrations::class)`): **(a)** `callAction('requireKyc')` → `Pending`, auto-`kyc` Hold + suspend; widget render → `assertCanSeeTableRecords([$kycHold])` + `assertTableActionHidden('lift', record: $kycHold)` (auto-managed → not liftable); **(b)** `callAction('recordKycVerified')` → `Verified`+active, `$kycHold->refresh()->status===Lifted`; widget re-render proves lifted row still lists, lift still hidden; **(c)** `callAction('recordScreening', data:['verdict'=>'passed','trigger_source'=>'onboarding'])` → `Passed`/`Onboarding`, `kyc_status` STILL `Verified` (independence § 9.4/D7). Emergent set `pluck('name')->toEqualCanonicalizing([CustomerHoldPlaced, CustomerSuspended, CustomerHoldLifted, CustomerReactivated, CustomerOnboardingScreeningPassed])->toHaveCount(5)`, `CustomerKyc%`-count 0 (D7); set-wide `module=parties`/`actor_role=NewcoOps`/`actor_id` not null; per-event heterogeneous envelope (Hold→`Hold`/$kycHold->id; status+screening→`Customer`/$customer->id, `actor_id` loose `toEqual`). Fresh page mount per action (visibility re-resolves per record); widget takes the model instance (Livewire rehydrates fresh — staleness harmless).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session): full suite 1495/1495 (8263 assn, exit 0)** — SQLite (was 1494/8196; +1 test/+67 assn). `--filter=CustomerKycSanctionsChainTest` 1/1 (67 assn). PHPStan max 0 errors; `pint --test` clean; `openspec validate --strict` valid. This iteration's diff = the NEW chain-test file ONLY (untracked, test-only); no `spec/`/`openspec/specs/`/`tests/Architecture/`, no migration, no composer dep. `ModuleBoundariesTest` UNCHANGED.
- Full suite OOMs at PHP default 128 MB in result *parsing* (not a failure) — run pest with `php -d memory_limit=-1`. `--filter` + phpstan run fine at default. PG17 ritual is task 4.2 (not run this slice).

## Active Change & Next Task
- **`operator-console-parties-kyc-sanctions` — APPROVED, IN PROGRESS (10/13).** Delta on `operator-console`: 2 ADDED (KYC; sanctions) + 2 MODIFIED Customer reqs; 13 tasks / 5 groups. Groups 1+2 (KYC) + 3 (sanctions) DONE; group 4 SQLite half (4.1) DONE.
- **Next task 4.2:** run the PG17 ritual (GUIDE §2.7) — `docker run -d --name pg … postgres:17 -p 55432:5432` → poll `pg_isready` → `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest tests/Feature/Modules/OperatorPanel/Parties tests/Feature/Modules/OperatorPanel/Catalog/ProductMasterConsoleI18nTest.php` (the appended Catalog i18n test loads the shared `scanOperatorConsoleHardcodedSinks` helper) → `docker rm -f pg`. Verify-only (no push/merge); the Parties OperatorPanel folder must be green under PostgreSQL 17.
- Then 5.1 (quality gates — pint/phpstan max incl. `NoEloquentWriteInOperatorPanelRule` + `ModuleBoundariesTest` UNCHANGED; full pest green; diff adds no spec/arch/migration/dep), 5.2 (openspec validate + memory consolidation). After all 13: review/merge → semantic-verify (GUIDE §2.7) → `openspec archive`.

## Blockers & Decisions Needed
- **None blocking task 4.2.** No open-ADR gate crossed. 4.2 needs Docker running locally (the PG17 ritual spins up `postgres:17`).
- **Holds push gate STILL pending** (separate change) — origin/main push of the Holds archive+merge commits + `git branch -d ralph/operator-console-parties-holds` await Giovanni's go (classifier-gated). Local merge+archive already done.

## Open Patterns
- **Cross-slice closing-chain through a SHIPPED widget (D11):** drive the new slice's page actions (fresh `Livewire::test(ViewCustomer)` mount per action — visibility re-resolves per record), then render the PRIOR slice's `CustomerHoldsTable` widget (`['record' => $modelInstance]`) to prove the emergent coupling — auto-`kyc` Hold renders, `lift` hidden, flips to lifted on verify. Profile-less factory Customer → exact emergent count. Assert the whole-run multiset (`toEqualCanonicalizing`+`toHaveCount`), `CustomerKyc%`-count 0 (D7), heterogeneous per-event envelope. `DatabaseMigrations`, never `RefreshDatabase`. Consolidated in this change's `progress.md ## Codebase Patterns`.
- **kyc-sanctions enum discipline:** `KycStatus` = STATE enum (cast `->value`, NEVER imported in production); `SanctionsStatus`/`ScreeningTriggerSource` = OPERAND enums (imported — carve-out). KYC verbs event-silent (D7); sanctions screening EMITS (onboarding/rescreening × passed/failed; under_review/pending emit nothing). The chain test asserts exactly 5 events, none KYC-named.
- **Operator audit envelope on an EMITTING action:** `actingAs($operator,'operator')` → `ActorContext` resolves `NewcoOps`+operator id; assert `actor_id` LOOSE (`toEqual`, PG bigint-as-string), `entity_id` `(string)`. `next_rescreen_at === last_screening_at+12mo` via `?->toDateTimeString()` (CarbonImmutable casts).
