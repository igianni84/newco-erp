---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-17
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-17 (ralph — `parties-compliance` task 6.1 green).** Shipped the slice's emergent-contract proof — **`ComplianceIndependenceTest`** (6 tests / 99 assertions), **test-only** (no production code; the behaviours already shipped 1.x–5.1). Three angles: (a) the **(kyc × sanctions) 4-cell** dataset — factory-set each pair, re-read, each column holds its own value with Customer `status` pinned `Pending` (persistence-level orthogonality); (b) a **dynamic independence sequence** through the real `RequireKyc`/`RecordKycVerified`/`RecordCustomerScreening` Actions — a KYC transition moves only `kyc_status` (sanctions NULL, status Pending, **0 events** — KYC audit-only L3), a sanctions screening moves only `sanctions_status` (kyc Verified, status Pending, exactly 1 `CustomerOnboardingScreeningPassed`), plus runtime scope guard (no `%Hold%`, no demand-side status event, no Account/Profile entity-type event); (c) the **structural scope guard** by reflection — the 14 compliance+supply Actions exist, no demand-side STATUS transition class does (**forbidden-name negative check**, NOT a 2nd copy of `SupplyLifecycleChainTest`'s exact-set whitelist), and `originating_club_id` has no setter. `SpineCreationChainTest`/`SupplyLifecycleChainTest`/`ModuleBoundariesTest`/`ModulePersistenceConventionsTest` all stay green **UNAMENDED**.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 710/710 SQLite** (704 baseline + 6 new). PHPStan max 0 · Pint clean · `openspec validate parties-compliance --strict` ✅ · `git diff main -- composer.json composer.lock` empty. Task 6.1 committed on `ralph/parties-compliance`.
- **PG17 verified for 6.1:** Parties feature suite **152/152** on `postgres:17` (146 + 6) — factory-set enum-pair writes + re-reads through the casts and the real-Action transition sequence hold on PG. No new schema this task.

## Active Change & Next Task
- **ACTIVE + APPROVED: `parties-compliance`** (11 tasks, 9/11 done). Branch `ralph/parties-compliance`.
- **Next (6.2):** **docs-only, NO code.** Extend `CONTEXT.md` with the resolved compliance terms (KYC four-state + cleared = `verified ∨ not_required`; the **NULL-cleared** producer-gate rule; sanctions four-state + independence; screening `trigger_source`; onboarding-vs-rescreen; `under_review` event-silence) and add a Parties compliance contract note documenting the four screening event payloads (PII-free) + the four deferred seams (`kyc` Hold → `parties-holds`; sanctions order-completion enforcement → Module S; enhanced-KYC + cadence/AML detection automation; KYC document handling). Verify by terminology re-read against the spec + `openspec validate --strict`. Then **6.3** = full compliance-chain feature test + cross-engine PG17 close.

## Blockers & Decisions Needed
- **None.** No open ADR gate crossed. Cleared-state semantics fixed by ADR `2026-06-17-producer-kyc-gate-not-required-clears.md` (NULL ≡ cleared for additivity; `not_required` ≡ `verified`).

## Open Patterns
- **Independence/scope-guard test idiom (6.1).** Emergent-contract test = state-pair matrix (dataset) + dynamic cross-transition sequence + reflection scope guard. **Exact-set whitelist has ONE home (`SupplyLifecycleChainTest`)** — companions use a forbidden-name negative check (robust to new Actions). **OC-no-setter source-scan targets `"'originating_club_id' =>"` (array-key write), NOT the bare column** — `CloseClub`'s docblock mentions it in prose (false positive). `class_exists($fqcn)` over `new ReflectionClass($runtimeString)` keeps the namespace walk PHPStan-max clean.
- **`cannotResolve` (1.3) stays unused** by `RecordCustomerScreening` — design L4 re-screens permissive. Left in place (tested in isolation).
- **PG17 gate** each DB task: `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; poll `docker exec pg pg_isready -U newco`; `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest tests/Feature/Modules/Parties`; `docker rm -f pg`.
