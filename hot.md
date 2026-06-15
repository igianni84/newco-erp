---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 17:01 (ralph iteration 3/20 — `parties-core` task 1.3 DONE).** Created the five registry/membership backed string enums under `App\Modules\Parties\Enums\` (house style, mirroring 1.2): `ProducerStatus` (`draft`/`active`/`retired`, §4.4), `ClubStatus` (`active`/`sunset`/`closed`, §4.3), `ClubRegistrationFlowType` (`open_registration`/`application_with_approval`/`invitation_only`/`link_onboarding`, §4.3 — a classifier, not a lifecycle), `ProducerAgreementStatus` (`draft`/`active`/`superseded`/`terminated`, §4.6.1), `ProfileState` (the nine §4.2.1 states, born `applied`). Every value verified firsthand against `spec/02-prd/Module_K_PRD_v0.3-MVP.md`. Extended `EnumsTest.php` (now 18 tests): 5 verbatim+count maps, a ProfileState terminal-set `{rejected,cancelled,inactive}` pin (the D8 index predicate), one `from()` guard per enum. No DB. **All ten Parties enums now exist.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission 8.0.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Branch `ralph/parties-core`**, 3 of 11 tasks done. This iteration: 5 new enum files + 1 extended test, no DB. Pint ✅ · filtered ✅ (18/27) · full suite ✅ (377 tests/1374 assertions) · phpstan max ✅ (0) · pint --test ✅ · `openspec validate parties-core --strict` ✅ · composer diff vs main empty ✅. PG17 N/A (enum-only; gate applies to DB tasks — first is 2.1).

## Active Change & Next Task
- **`parties-core`** (capability `party-registry`; 11 tasks; 5 `*Created` events; 7 entities). APPROVED present.
- **Next: task 2.1 — Producer** (FIRST DB task → **PG17 gate applies**). Migration `parties_producers` (`name`, `region`, `appellation?`, `country`, `description` json via `TranslatableTextCast` nullable, `website?`, `status` string + driver-guarded PG `CHECK` + `ProducerStatus` cast **default `draft`**, `version` default 1, `timestampsTz`); `Models\Producer` (`$table='parties_producers'`, `$guarded=[]`, casts, typed `newFactory()`); `Database\Factories\Parties\ProducerFactory`; `Events\ProducerCreated` (`NAME`/`ENTITY_TYPE`, static PII-free `payload()`); `Actions\CreateProducer` (`DB::transaction` insert → `DomainEventRecorder::record(...)`). Follow the **Spine DB-entity template** (progress.md Codebase Patterns; `catalog-product-spine` precedent). Test `ProducerTest.php` (`RefreshDatabase`): status `Draft`; `description` round-trips via cast (`en`); one `ProducerCreated` row tagged `parties` (payload by key); **no** `parties_suppliers` row (D10).

## Blockers & Decisions Needed
- **None.** This slice steps through no open ADR gate.
- Spec-faithful asymmetries to keep: Supplier & Account emit **no** `*Created` (PRD §15); Originating Club = **field only** (`originating_club_id` born NULL, no setter).
- Open ADR gates (future, not hit here): queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **Spine DB-entity template** (read first): per evented entity = `parties_*` migration (+ driver-guarded enum `CHECK`) + `Models\X` (`$table`, `$guarded=[]`, typed `newFactory()`) + `XFactory` + `Events\XCreated` (final, static PII-free `payload()`) + `Actions\CreateX` (`DB::transaction` insert → recorder).
- **Two event silences** (D7): `CreateSupplier` + Account leg of `CreateCustomer` record NO event. **PII-free `CustomerCreated`** (omit email/name/phone/DOB).
- **Within-module only** — within-module FKs + relations allowed; no cross-module ref; `$table='parties_*'` on every model; arch tests stay green unamended.
- **PG17 gate** — every DB-touching task verified on local PostgreSQL 17 (`knowledge/testing/rules.md`); recorded at 6.2. Traps: enum CHECK driver-guard, MoneyCast 2-col round-trip (Club fee), TranslatableText jsonb by-key (Producer description), 63-char identifier limit (agreements), Profile partial-unique on both engines.
- **Verify-firsthand habit** — confirm every spec §/symbol before citing. **log.md:** append ONLY via `scripts/memlog.sh`; hot.md ≤550 words. **APPROVED = human-only.**
