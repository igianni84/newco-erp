---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-17
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-17 (ralph — `parties-compliance` task 2.1 green).** Shipped the three Customer KYC transition Actions under `app/Modules/Parties/Actions/`: `RequireKyc` (`not_required`/NULL → `pending`, sets `kyc_required = true`), `RecordKycVerified` / `RecordKycRejected` (`pending → verified`/`rejected`). They are **eventless** (KYC records no domain event — design L3) → **no constructor/deps**; pure `DB::transaction` → `lockForUpdate` re-read → from-state assert → `update()` → return (the template 3.1 reuses). No Hold placed, `Customer.status` never moved (KYC FSM separate), `version` not bumped. Necessary refinement: widened 1.3's `IllegalKycTransition::cannotVerify`/`cannotReject` to **`?KycStatus`** (verify/reject are reachable from NULL = un-screened; NULL renders as the `unset` sentinel via `$from->value ?? 'unset'`, NOT `?->` — PHPStan strict `nullsafe.neverNull`). Also extended `SupplyLifecycleChainTest`'s scope-guard whitelist by the 3 KYC actions.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 659/659 SQLite** (642 baseline + 17 new). PHPStan max 0 · Pint clean · `openspec validate parties-compliance --strict` ✅ · `git diff main -- composer.json composer.lock` empty. Task 2.1 committed on `ralph/parties-compliance`. **PG17 verified:** Parties feature suite **110/110** on `postgres:17` (incl. the 17 KYC tests; trap-5 verify-after-throw SELECT survives — the guard throws before any DML inside the Action's own savepoint).

## Active Change & Next Task
- **ACTIVE + APPROVED: `parties-compliance`** (11 tasks, 4/11 done). Branch `ralph/parties-compliance`.
- **Next (3.1):** Producer KYC lifecycle — four Actions `RequireProducerKyc` + `RecordProducerKycVerified` + `RecordProducerKycRejected` + `WaiveProducerKyc` (design L2/L3; spec — Producer KYC Lifecycle). **Reuse the 2.1 eventless-Action template** (no deps; `DB::transaction`→`lockForUpdate`→guard→`update`). Producer already has the `kyc_status` enum cast (1.2), born `draft`. Require: `NotRequired`/NULL → `Pending`; verify/reject: `Pending →`; **`WaiveProducerKyc`: any state → `NotRequired`** except already-`NotRequired` throws `IllegalKycTransition::cannotWaive` (non-null `$from`). verify/reject reuse the already-widened `?KycStatus` factories. **No event, no Hold, Producer `status` (`draft`) untouched.** Test `tests/Feature/Modules/Parties/ProducerKycLifecycleTest.php` (`RefreshDatabase`). **MUST add the 4 Producer-KYC actions to `SupplyLifecycleChainTest` `$complianceTransitions`** (exact-set guard goes red otherwise). **DB-touching → PG17-verify before close.**

## Blockers & Decisions Needed
- **None.** No open ADR gate crossed (KYC eventless; no Hold; no vendor adapter). Slice boundary + cleared-state semantics fixed by ADR 2026-06-17.

## Open Patterns
- **Eventless vs evented Action split is live.** KYC Actions (2.1, 3.1) = no deps, no event. Sanctions Action (4.2) = injects `DomainEventRecorder` + `ActorContext`, records the four § 15.6 events (the spine idiom). The future `kyc` Hold coupling is within-module Action orchestration, not events (design L3).
- **Scope-guard whitelist GROWS per compliance task.** `SupplyLifecycleChainTest` `toEqualCanonicalizing([...supply, ...compliance])` — 3.1 += 4 actions, 4.2 += `RecordCustomerScreening`, or the full suite goes red. Intent preserved: no demand-side **status** transition.
- **Nullable from-state ⇒ `?KycStatus` factory + `->value ?? 'unset'`.** Reused by 3.1 verify/reject. Don't call enum methods on a nullable `@property` in tests (`method.nonObject`) — assert `->toBe(KycStatus::X)`, leave `clears()` to ComplianceEnumsTest (1.1).
- **PG17 gate** each DB task: `docker run -d --name pg … postgres:17`; `DB_CONNECTION=pgsql … -p 55432`; `docker rm -f pg`. Filtered = file path; full = `php -d memory_limit=512M vendor/bin/pest`.
