---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-17
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-17 (ralph — `parties-compliance` task 4.2 green).** Shipped `RecordCustomerScreening` — the FIRST *evented* compliance Action. Injects `DomainEventRecorder` + `ActorContext` (the spine idiom; KYC Actions inject nothing). `handle(int, SanctionsStatus $verdict, ScreeningTriggerSource $source): Customer`: `DB::transaction` → `lockForUpdate` → **onboarding-is-first guard** (`source===Onboarding && last_screening_at!==null` → `IllegalSanctionsTransition::onboardingAlreadyScreened()`; re-screens permissive — L4) → captures ONE `CarbonImmutable::now()`, writes `sanctions_status`/`last_screening_at`/`next_rescreen_at(+12mo)`/`screening_trigger_source` → records the matching § 15.6 completion event. Event picked by **`match(true)` → concrete event class-string** (`default => null` for `UnderReview`/`Pending` — event-silent); recorded via `$event::NAME`/`::ENTITY_TYPE`/`::payload()` (**PHPStan-max clean** — no `phpstan-strict-rules` in the neon). Never touches `kyc_status` (§ 9.4). Extended the `SupplyLifecycleChainTest` `$complianceTransitions` whitelist += `RecordCustomerScreening`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 697/697 SQLite** (688 baseline + 9 new). PHPStan max 0 · Pint clean · `openspec validate parties-compliance --strict` ✅ · `git diff main -- composer.json composer.lock` empty. Task 4.2 committed on `ralph/parties-compliance`.
- **PG17 verified for 4.2:** Parties feature suite **141/141** on `postgres:17` (132 + 9) — timestamptz round-trip (read via `immutable_datetime` cast, never raw — trap 4), nullable writes, jsonb payloads BY KEY (trap 3), rejected-onboarding verify-after-throw (guard throws pre-DML inside the Action savepoint — trap 5).

## Active Change & Next Task
- **ACTIVE + APPROVED: `parties-compliance`** (11 tasks, 7/11 done). Branch `ralph/parties-compliance`.
- **Next (5.1):** the ONE MODIFIED behaviour — **tighten `ActivateProducer` with the KYC-cleared gate**. After the existing `draft` assert in `app/Modules/Parties/Actions/ActivateProducer.php`, proceed iff `kyc_status === null || kyc_status->clears()` (`Verified`/`NotRequired`); else (`Pending`/`Rejected`) reject via a **new `IllegalProducerTransition::kycNotCleared(KycStatus $from)`** factory + a `lang/en/parties.php` **`producer.kyc_not_cleared`** key, leaving the Producer `draft`, recording NO `ProducerActivated`. **REPLACE** the shipped `ProducerLifecycleTest` "no KYC gate" scenario with the **AC-K-FSM-7 matrix** (positive `{null, NotRequired, Verified}` → Active + event; negative `{Pending, Rejected}` → throws, stays draft, 0 events) + a **NULL-cleared regression**; keep the `RetireProducer` cascade cases unamended. DB-touching → **PG17 gate**. `IllegalProducerTransition` already has `cannotActivate`/`cannotRetire` — ADD `kycNotCleared`, don't recreate.

## Blockers & Decisions Needed
- **None.** No open ADR gate crossed. Cleared-state semantics fixed by ADR `2026-06-17-producer-kyc-gate-not-required-clears.md` (NULL ≡ cleared for additivity; `not_required` ≡ `verified`).

## Open Patterns
- **Evented-screening-Action idiom (4.2).** Inject recorder + actor; `match(true)` → concrete event **class-string** (`default => null` for event-silent verdicts); record via `$event::NAME`/`::payload()` (const/static on a union of concrete class-strings = PHPStan-max clean here). Capture ONE `now()` → exact `+12mo` window. Contrast eventless KYC Actions (2.1/3.1, no deps/no event).
- **Scope-guard whitelist GROWS per Action task.** `SupplyLifecycleChainTest` `toEqualCanonicalizing([...supply, ...compliance])` — 4.2 added `RecordCustomerScreening`. 5.1 adds NOTHING (ActivateProducer already present; it's MODIFIED, not new).
- **`cannotResolve` (1.3) is unused** by the Action — design L4 re-screens are permissive. Left in place (tested in isolation).
- **PG17 gate** each DB task: `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 … php -d memory_limit=512M vendor/bin/pest tests/Feature/Modules/Parties`; `docker rm -f pg`.
