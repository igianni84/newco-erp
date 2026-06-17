---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-17
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-17 (ralph — `parties-compliance` task 3.1 green).** Shipped the four Producer KYC transition Actions under `app/Modules/Parties/Actions/`: `RequireProducerKyc` (`not_required`/NULL → `pending`), `RecordProducerKycVerified` / `RecordProducerKycRejected` (`pending → verified`/`rejected`), and `WaiveProducerKyc` (any state → `not_required` **except** already-`not_required` → `IllegalKycTransition::cannotWaive`). Direct reuse of the 2.1 **eventless-Action template** (no constructor/deps; `DB::transaction` → `lockForUpdate` re-read → from-state assert → `update()` → return). The Producer carries ONLY `kyc_status` (no `kyc_required` — that's Customer-side), so require writes `kyc_status` alone. **No domain event** (design L3), **no Hold**, Producer `status` (`draft`) never moved, `version` not bumped. Zero edits to the 1.1 enum / 1.2 column / 1.3 exception (verify/reject reuse the already-widened `?KycStatus` factories; waive's `cannotWaive` stays non-null — NULL is a legal from-state). Extended `SupplyLifecycleChainTest`'s scope-guard whitelist by the 4 Producer-KYC actions.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 681/681 SQLite** (659 baseline + 22 new). PHPStan max 0 · Pint clean · `openspec validate parties-compliance --strict` ✅ · `git diff main -- composer.json composer.lock` empty. Task 3.1 committed on `ralph/parties-compliance`. **PG17 verified:** Parties feature suite **132/132** on `postgres:17` (110 + 22); new file 22/22 in isolation — trap-5 verify-after-throw SELECT survives (guard throws before any DML inside the Action's savepoint).

## Active Change & Next Task
- **ACTIVE + APPROVED: `parties-compliance`** (11 tasks, 5/11 done). Branch `ralph/parties-compliance`.
- **Next (4.1):** the four sanctions screening events — `App\Modules\Parties\Events\{CustomerOnboardingScreeningPassed, CustomerOnboardingScreeningFailed, CustomerRescreeningPassed, CustomerRescreeningFailed}`, each `final` with untyped `const NAME` (verbatim § 15.6 name), `const ENTITY_TYPE = 'Customer'`, static `payload(Customer): array` → `{customer_id, sanctions_status, trigger_source}` (**PII-free** — no name/email/phone/DOB). Mirror the shipped supply-side event classes (`ProducerActivated` etc.) for house style. Test `tests/Unit/Modules/Parties/Events/ScreeningEventsTest.php` — assert each NAME byte-for-byte + payload keys exactly the three + no PII. **No DB → PG17 gate N/A.** Then **4.2** `RecordCustomerScreening` is the FIRST *evented* compliance Action (injects `DomainEventRecorder` + `ActorContext`, the spine idiom) and MUST add itself to the `SupplyLifecycleChainTest` whitelist.

## Blockers & Decisions Needed
- **None.** No open ADR gate crossed (KYC eventless; no Hold; no vendor adapter). Slice boundary + cleared-state semantics fixed by ADR 2026-06-17.

## Open Patterns
- **Eventless vs evented Action split is live.** KYC Actions (2.1 Customer, 3.1 Producer) = no deps, no event. Sanctions Action (4.2) = injects `DomainEventRecorder` + `ActorContext`, records the four § 15.6 events (the spine idiom). The future `kyc` Hold coupling is within-module Action orchestration, not events (design L3).
- **`WaiveProducerKyc` inverse-guard.** Legal from ANY state except already-`not_required`; `cannotWaive` non-null (NULL is legal → explicit `not_required`). Producer-only (Customer has no waive).
- **Scope-guard whitelist GROWS per compliance task.** `SupplyLifecycleChainTest` `toEqualCanonicalizing([...supply, ...compliance])` — 3.1 += 4 Producer-KYC; **4.2 += `RecordCustomerScreening`** or the full suite goes red. Intent preserved: no demand-side **status** transition.
- **Nullable from-state ⇒ `?KycStatus` factory + `->value ?? 'unset'`** (no `?->` — PHPStan `nullsafe.neverNull`). Don't call enum methods on a nullable `@property` in tests (`method.nonObject`) — assert `->toBe(KycStatus::X)`.
- **PG17 gate** each DB task: `docker run -d --name pg … postgres:17`; `DB_CONNECTION=pgsql … -p 55432`; `docker rm -f pg`. Filtered = file path; full = `php -d memory_limit=512M vendor/bin/pest`.
