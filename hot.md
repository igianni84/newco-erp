---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-17
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-17 (ralph — `parties-compliance` task 5.1 green).** Shipped the slice's ONE MODIFIED behaviour — **`ActivateProducer` now enforces the KYC-cleared gate**. After the existing `draft` from-state assert, a SECOND guard reads `$producer->kyc_status` and proceeds iff `NULL` or `->clears()` (`Verified`/`NotRequired`); `Pending`/`Rejected` throw the **new** `IllegalProducerTransition::kycNotCleared(KycStatus $from)` (non-null — the `$kyc !== null` check narrows before the throw, like `cannotWaive` in 3.1), rolling back so the Producer stays `draft` with no `ProducerActivated`. **NULL ≡ cleared** is a GATE concern (additive-safety for pre-change Producers, DEC-071), NOT in the enum. New `producer.kyc_not_cleared` lang key. Replaced the shipped `ProducerLifecycleTest` "no KYC gate" scenario with the **AC-K-FSM-7 matrix** (positive `{null, NotRequired, Verified}` → Active+event; negative `{Pending, Rejected}` → `->toThrow(..., 'KYC')`, stays draft, 0 events) + an explicit NULL-cleared regression; pinned the new factory in the EXISTING `TransitionExceptionsTest` (+ lang dataset row). The retire-cascade path is untouched. **NOTHING added to the `SupplyLifecycleChainTest` whitelist** (ActivateProducer is MODIFIED, already listed).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 704/704 SQLite** (697 baseline + 5 feature + 2 unit). PHPStan max 0 · Pint clean · `openspec validate parties-compliance --strict` ✅ · `git diff main -- composer.json composer.lock` empty. Task 5.1 committed on `ralph/parties-compliance`.
- **PG17 verified for 5.1:** Parties feature suite **146/146** on `postgres:17` (141 + 5 new ProducerLifecycle) — the Producer-KYC-**NULL-cleared** semantics (positive matrix `null` row + the additive regression) hold on PG, satisfying the design-L1 asymmetric-NULL assertion (Producer side). The `TransitionExceptionsTest` additions are no-DB (translator only) → engine-agnostic, not in the PG run.

## Active Change & Next Task
- **ACTIVE + APPROVED: `parties-compliance`** (11 tasks, 8/11 done). Branch `ralph/parties-compliance`.
- **Next (6.1):** the **independence + scope-guard feature test** — `tests/Feature/Modules/Parties/ComplianceIndependenceTest.php`. Drive the (kyc × sanctions) **4-cell** at the state level (`kyc ∈ {pending,verified} × sanctions ∈ {pending,passed}`) and assert each column holds its own value with the Customer `status` **never moving** (FSMs separate + independent). Assert the **scope guards**: reflect `App\Modules\Parties\Actions` → no class transitions Customer/Account/Profile **status**, `originating_club_id` has no setter; `domain_events` has no `%Hold%` row and no `CustomerActivated`/`ProfileActivated`/`OriginatingClubLocked`/`CustomerSegmentChanged`. `SpineCreationChainTest` + `ModuleBoundariesTest` + `ModulePersistenceConventionsTest` stay green **unamended**. DB-touching → **PG17 gate**.

## Blockers & Decisions Needed
- **None.** No open ADR gate crossed. Cleared-state semantics fixed by ADR `2026-06-17-producer-kyc-gate-not-required-clears.md` (NULL ≡ cleared for additivity; `not_required` ≡ `verified`).

## Open Patterns
- **Producer-gate KYC idiom (5.1).** `$kyc !== null && ! $kyc->clears()` narrows `?KycStatus → KycStatus` before the throw → `kycNotCleared(KycStatus)` stays non-null. NULL ≡ cleared at the gate (additive safety), not in `clears()`. New factory on an EXISTING exception ⇒ pin in the EXISTING `TransitionExceptionsTest` (+ lang dataset row), not a new test file. AC-K-FSM-7 = positive/negative datasets; `->toThrow(IllegalProducerTransition::class, 'KYC')` distinguishes the KYC gate from the from-state guard.
- **Scope-guard whitelist GROWS only for NEW transition Actions.** `SupplyLifecycleChainTest` `toEqualCanonicalizing([...supply, ...compliance])`: 5.1 added NOTHING (ActivateProducer MODIFIED, already present). 6.1 adds nothing either — it's the independence/scope-guard *angle* (`ComplianceIndependenceTest`), not a new Action.
- **`cannotResolve` (1.3) stays unused** by `RecordCustomerScreening` — design L4 re-screens are permissive. Left in place (tested in isolation).
- **PG17 gate** each DB task: `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; poll `docker exec pg pg_isready -U newco`; `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest tests/Feature/Modules/Parties`; `docker rm -f pg`.
