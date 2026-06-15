---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 17:24 (ralph iteration 5/20 — `parties-core` task 2.2 Supplier DONE).** Built the second `parties_*` table and the FIRST **minimal** spine entity: Supplier drops three legs the others carry — **no `status`, no `version`, no `*Created` event** (D7 silence; PRD §15 names none). Five new files: migration `parties_suppliers` (`legal_name`, `party_type` string + driver-guarded PG `CHECK` from `PartyType::cases()`, **no default** on the marker, `timestampsTz`, no FK — it is a leaf); `Models\Supplier` (sole `party_type→PartyType` cast); `SupplierFactory` (fixes `supplier`); `Actions\CreateSupplier` (no constructor — event-silent; one `DB::transaction`); `SupplierTest` (5/11). **5 of 11 done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Branch `ralph/parties-core`.** Pint ✅ · filtered ✅ (5/11) · full **389/1420 on SQLite AND PostgreSQL 17** ✅ · phpstan max 0 ✅ · pint --test ✅ · `openspec validate … --strict` ✅ · composer diff empty ✅. **PG CHECK proven directly:** `parties_suppliers_party_type_check` rejects `'winery'`, accepts `'supplier'`.

## Active Change & Next Task
- **`parties-core`** (capability `party-registry`; 11 tasks; 5 `*Created` events; 7 entities). APPROVED present. 5/11 done.
- **Next: task 3.1 — Club** (DB task → **PG17 gate applies**; first FK + first Money field). Migration `parties_clubs`: `display_name`, `producer_id` `unsignedBigInteger` FK `->constrained('parties_producers')` with a **short explicit index name** (63-char limit), `status` string+CHECK+`ClubStatus` cast default `active`, `fee` via `MoneyCast`→`fee_minor` int nullable + `fee_currency` string nullable, `registration_flow_type` string+CHECK+`ClubRegistrationFlowType` cast (**no default** — classifier, set at creation), `generates_credit` bool default true, `invite_only` bool default false, `version`, `timestampsTz`. `Models\Club` (`belongsTo` Producer within module; `fee`→MoneyCast). `ClubFactory`. `Events\ClubCreated` (payload `producer_id` by id + `fee` as `{minor_units, currency}`). `Exceptions\MissingClubProducer` (localized). `Actions\CreateClub`: reject missing/non-existent `producer_id` → insert `active` → record `ClubCreated` (one tx). Tests: `Money::of(25000, Currency::of('EUR'))` round-trips via `fee->equals()`; missing-producer throws; payload `fee`={minor_units:25000,currency:'EUR'} by key; producer_id immutable.

## Blockers & Decisions Needed
- **None.** No open ADR gate hit here.
- Asymmetries to keep: Supplier & Account emit **no** `*Created` (PRD §15); `party_type` marker takes **no default** (action sets it); Originating Club = field only (born NULL, no setter).

## Open Patterns
- **Minimal entity** (Supplier worked) — drops `version`+`status`+event+recorder/actor deps; the event-silent action keeps only `DB::transaction`. **Marker column** = full `PartyType::cases()` CHECK + NO default (reused at Customer 4.1).
- **Spine DB-entity template** — `parties_*` migration (+ driver-guarded enum CHECK from `Enum::cases()`) + `Models\X` (`$table`,`$guarded=[]`, typed `newFactory()`) + `XFactory` + `Events\XCreated` (PII-free `payload()`) + `Actions\CreateX` (`DB::transaction` insert→recorder). Club (3.1) re-adds the event leg + FK + Money.
- **Pint trap:** `{@see \FQN}` in a docblock becomes a real `use` import — name deliberately-unused classes in plain prose.
- **Faker PHPStan-max:** only annotated `@method` providers (`company`/`city`/`country`/`word`/`sentence`/`url`/`lastName`); `fake()->state()` is undefined → `method.notFound`.
- **Within-module only** — within-module FKs (`->constrained('parties_*')`, short index names) + relations allowed; `$table='parties_*'` on every model; arch tests stay green unamended.
- **PG17 gate** — reuse the docker `postgres:17` (port 55432) + `pg_isready` poll + `DB_CONNECTION=pgsql … php artisan test` block at every DB task; cross-engine close at 6.2. **log.md** via `scripts/memlog.sh` only; hot.md ≤550 words. **APPROVED = human-only.**
