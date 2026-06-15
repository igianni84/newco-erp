---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 17:12 (ralph iteration 4/20 — `parties-core` task 2.1 Producer DONE).** Built the FIRST Parties DB entity (first DB-touching task → **PG17 gate exercised and passed**) via the Spine DB-entity template, `catalog`'s `ProductMaster` as the worked analogue. Six new files: migration `parties_producers` (identity cols + `description` json + `status` default `draft` + `version` + driver-guarded PG `CHECK` from `ProducerStatus::cases()`, no FK); `Models\Producer`; `ProducerFactory`; `Events\ProducerCreated` (PII-free `payload()`, `description` omitted); `Actions\CreateProducer` (one `DB::transaction`: insert born `draft` → recorder; no dedup, no auto-Supplier per D10); `ProducerTest` (7/34). **4 of 11 done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission 8.0.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Branch `ralph/parties-core`.** Pint ✅ · filtered ✅ (7/34) · full suite ✅ **384/1408 on SQLite AND PostgreSQL 17** (ProducerTest 7/7 on PG; `parties_producers_status_check` confirmed) · phpstan max ✅ (0) · pint --test ✅ · `openspec validate … --strict` ✅ · composer diff empty ✅.

## Active Change & Next Task
- **`parties-core`** (capability `party-registry`; 11 tasks; 5 `*Created` events; 7 entities). APPROVED present. 4/11 done.
- **Next: task 2.2 — Supplier** (DB task → **PG17 gate applies**). Migration `parties_suppliers` (`legal_name`, `party_type` string + driver-guarded `CHECK` + `PartyType` cast, `timestampsTz` — **NO status column, NO version**); `Models\Supplier` (`$table='parties_suppliers'`, `$guarded=[]`, casts `party_type→PartyType`, typed `newFactory()`); `SupplierFactory`; `Actions\CreateSupplier` (one `DB::transaction`: insert `party_type=supplier` → records **NO** event — the D7 silence). Test `SupplierTest.php`: `party_type===PartyType::Supplier`; **`DomainEvent::count()===0`** after creation; `Schema::hasColumn('parties_suppliers','status')===false`; no `parties_producers` row (no auto-cross-create). Follow the Spine template but DROP the event/status/version legs.

## Blockers & Decisions Needed
- **None.** This slice steps through no open ADR gate.
- Spec-faithful asymmetries to keep: Supplier & Account emit **no** `*Created` (PRD §15); Originating Club = **field only** (`originating_club_id` born NULL, no setter).
- Open ADR gates (future, not hit here): queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **Spine DB-entity template** (Producer is the first worked Parties instance): `parties_*` migration (+ driver-guarded enum `CHECK` from `Enum::cases()`) + `Models\X` (`$table`, `$guarded=[]`, typed `newFactory()`) + `XFactory` (bypasses action) + `Events\XCreated` (final, static PII-free `payload()`) + `Actions\CreateX` (`DB::transaction` insert → recorder). Supplier (2.2) DROPS the event + status + version.
- **Faker-typed-providers trap (PHPStan max):** `fake()->state()` is undefined in Faker's `@method` typedef → `method.notFound`. Use only annotated providers (`city`/`country`/`word`/`sentence`/`url`/`lastName`); mirror the catalog factories.
- **Two event silences** (D7): `CreateSupplier` + Account leg of `CreateCustomer` record NO event. **PII-free `CustomerCreated`** (omit email/name/phone/DOB). Translatable fields are omitted from `*Created` payloads (read via contract).
- **Within-module only** — within-module FKs + relations allowed; no cross-module ref; `$table='parties_*'` on every model; arch tests stay green unamended.
- **PG17 gate** — reuse the docker `postgres:17` (port 55432) + `pg_isready` poll + `DB_CONNECTION=pgsql … php artisan test` block at every DB task; cross-engine close at 6.2. **log.md** via `scripts/memlog.sh` only; hot.md ≤550 words. **APPROVED = human-only.**
