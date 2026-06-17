---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-17
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-17 (ralph — `parties-compliance` task 4.1 green).** Shipped the four sanctions screening event classes under `app/Modules/Parties/Events/`: `CustomerOnboardingScreeningPassed`, `CustomerOnboardingScreeningFailed`, `CustomerRescreeningPassed`, `CustomerRescreeningFailed` — each a standalone `final class` (no base/interface) mirroring `ProducerActivated`/`CustomerCreated`: untyped `const NAME` (verified **byte-for-byte vs PRD § 15.6** lines 797-798), `const ENTITY_TYPE = 'Customer'`, static `payload(Customer): array` → `{customer_id, sanctions_status, trigger_source}` (**PII-free** — ids + enum `->value`s only; `trigger_source` reads the `screening_trigger_source` column via **bare `?->value`**, PHPStan-max clean). These are the ONLY compliance events in the change — KYC records none (design L3), `under_review` is event-silent (design L4). **Events-only: zero edits** to any Action, the `SupplyLifecycleChainTest` whitelist, the migration, lang, or enums.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 688/688 SQLite** (681 baseline + 7 new). PHPStan max 0 · Pint clean · `openspec validate parties-compliance --strict` ✅ · `git diff main -- composer.json composer.lock` empty. Task 4.1 committed on `ralph/parties-compliance`. **PG17 gate N/A** for 4.1 — no DB touched (the unit test uses `factory()->make()` only, `uses(TestCase::class)` not RefreshDatabase). Last PG17 run: 132/132 Parties (task 3.1).

## Active Change & Next Task
- **ACTIVE + APPROVED: `parties-compliance`** (11 tasks, 6/11 done). Branch `ralph/parties-compliance`.
- **Next (4.2):** `RecordCustomerScreening` — the FIRST *evented* compliance Action. `handle(int $customerId, SanctionsStatus $verdict, ScreeningTriggerSource $source): Customer`: `DB::transaction` → `lockForUpdate` re-read → guard (`source === Onboarding` requires `last_screening_at IS NULL` else `IllegalSanctionsTransition::onboardingAlreadyScreened()`) → set `sanctions_status=$verdict`, stamp `last_screening_at=CarbonImmutable::now()`, `next_rescreen_at=now()->addMonths(12)`, `screening_trigger_source=$source` → if verdict is a **completion** (`Passed`/`Failed`) `record(...)` the event family by source (`Onboarding` → `CustomerOnboardingScreening{Passed,Failed}`; else → `CustomerRescreening{Passed,Failed}`); **`UnderReview` records NO event** (L4). Unlike all KYC Actions it **injects `DomainEventRecorder` + `ActorContext`** (the spine idiom — see `ActivateProducer`); never touches `kyc_status`. **MUST add `RecordCustomerScreening` to the `SupplyLifecycleChainTest` Actions whitelist** (`$complianceTransitions`) or the full suite goes RED. **DB-touching → PG17 gate REQUIRED** (assert the `timestamptz` round-trip + nullable writes on PG). Test `tests/Feature/Modules/Parties/CustomerSanctionsLifecycleTest.php` (RefreshDatabase).

## Blockers & Decisions Needed
- **None.** No open ADR gate crossed (sanctions manual-first — no vendor adapter; no Hold). Slice boundary + cleared-state semantics fixed by ADR 2026-06-17.

## Open Patterns
- **Domain-event-class idiom (4.1).** Standalone `final class`, untyped `const NAME`/`const ENTITY_TYPE`, static PII-free `payload()`. Nullable enum reads in a payload = **bare `?->value`** (never `?-> … ?? x` — the redundant-nullsafe rule). Key `trigger_source` ↔ column `screening_trigger_source`.
- **Event CLASSES ≠ recorded ROWS.** Adding event classes touches no arch/chain test (the chain counts *recorded* rows; the Events glob forbids only demand-side names). Only the *Action* that records them (4.2) extends the `SupplyLifecycleChainTest` Actions whitelist.
- **Eventless vs evented Action split.** KYC Actions (2.1/3.1) = no deps, no event. **4.2 sanctions = injects recorder + actor**, records the four § 15.6 events.
- **Scope-guard whitelist GROWS per Action task.** `toEqualCanonicalizing([...supply, ...compliance])` — **4.2 += `RecordCustomerScreening`**.
- **PG17 gate** each DB task: `docker run -d --name pg … postgres:17`; `DB_CONNECTION=pgsql … -p 55432`; `docker rm -f pg`. Full suite = `php -d memory_limit=512M vendor/bin/pest`.
