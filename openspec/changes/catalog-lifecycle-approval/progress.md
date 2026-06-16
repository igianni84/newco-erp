# Progress — catalog-lifecycle-approval

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Pint auto-imports docblock `{@see \FQCN}` refs (`fully_qualified_strict_types`).** If you `{@see \App\Modules\…\SomeClass}` a class a LATER task creates, Pint rewrites it into a real `use` import → PHPStan then breaks on the unknown class. **Rule:** reference a not-yet-existent class (a future task's Action/Consumer/Event) as plain backticked text — `` `ProducerLifecycleProjector` (task 1.2) `` — never `{@see \FQCN}`, until the class exists. Same-namespace `{@see ClassName}` / `{@see Class::$prop}` are safe (Pint leaves them, no import needed).
- **PG-only CHECK test idiom** (template: `tests/Feature/Platform/ActorRoleConstraintTest.php`): a raw `DB::table('t')->insert([...])` bypasses the Eloquent enum cast (the app-layer floor), so on PG the DB CHECK is the SOLE gate. Wrap the probe insert in `DB::transaction(...)` (a SAVEPOINT, testing-rule #5) so a PG constraint-abort stays isolated and the row-state check after the throw is valid. Assert BOTH halves of the engine asymmetry, never a vacuous skip: `pgsql` → message `toContain('<named_constraint>')` + row absent; `sqlite` → message `''` + row present (the cast, not a DB CHECK, is the floor). NB: `captureConstraintViolation()` in that file is a top-level (GLOBAL) function — do NOT redeclare it in another test file (fatal on full-suite load); inline the try/catch or name a fresh helper.
- **New projection/read-model migration shape** (mirrors the spine): `$table->id()` + a plain `unsignedBigInteger('<fk>_id')` (NEVER a cross-module FK/relation — invariant 10) + a `string('status')` whose value-set is enforced by the enum cast on both engines PLUS a PG-only `CHECK (… IN (…))` derived from `Enum::cases()` (so it can never drift) + `timestampsTz()`. Explicit, <63-char index names. CHECK DDL guarded by `if (DB::getDriverName() === 'pgsql')`.

---

## [2026-06-16 10:47] — 1.1 catalog_producer_states projection — migration + model + enum
- **What:** Stood up the Catalog-owned producer-state projection (the codebase's first cross-module read model) — the persistence floor under task 1.2's consumer and task 3.2's gate. Three artifacts + tests, no behaviour yet (the consumer/gate land next).
- **Files changed:**
  - `app/Modules/Catalog/Enums/ProducerProjectionStatus.php` (NEW) — backed enum `Active='active'`/`Retired='retired'` (the two gate-relevant states; `draft`/`reviewed` never reach this read model — design D3).
  - `app/Modules/Catalog/Models/ProducerState.php` (NEW) — `$table='catalog_producer_states'`, `$guarded=[]`, casts `status`→enum, `producer_id`/`last_event_id`→`integer`. Persistence-only; no `HasFactory` (the projector is the sole writer; tests `create()` directly). `producer_id` is a plain id (no relation — invariant 10).
  - `database/migrations/2026_06_16_000001_create_catalog_producer_states_table.php` (NEW) — the change's ONE migration: `id`, `producer_id` (unique), `status` (string + PG CHECK from `cases()`), `last_event_id` (watermark, plain bigint — no FK into the platform event store), `timestampsTz()`.
  - `tests/Feature/Modules/Catalog/ProducerStateProjectionTest.php` (NEW, `DatabaseMigrations`) — 4 tests: schema/columns on both engines; cast round-trip (status→enum, ids→int); duplicate `producer_id` rejected (unique, both engines); status CHECK asserted both-halves (PG names the constraint / SQLite accepts the raw insert).
  - `tests/Unit/Modules/Catalog/Enums/EnumsTest.php` (EDIT) — pinned `ProducerProjectionStatus` case/value map verbatim + a `from()` rejection (protects the migration's `cases()`-derived CHECK from silent drift).
- **Quality loop: green.** Pint format ✓ · filtered tests 10/10 ✓ · full suite 481/481 (was 475; +6) ✓ · phpstan 0 errors ✓ · pint --test ✓. **PG17:** new tests 10/10 + `tests/Feature/Modules/Catalog` + `tests/Unit/Modules/Catalog` + `tests/Architecture` = 77/77 green (CHECK + unique proven on PG; migration applies cleanly in the full chain; `ModulePersistenceConventionsTest`/`ModuleBoundariesTest`/`CatalogNamingCascadeTest` green). `openspec validate --strict` ✓.
- **Acceptance:** all bullets met — table on both engines with status CHECK + unique `producer_id`; model casts status to the enum; `ModulePersistenceConventionsTest` green; quality green; verified on PG17.
- **Learnings for future iterations:**
  - Hit the Pint `{@see \FQCN}` → auto-import trap referencing the not-yet-existent `ProducerLifecycleProjector` (task 1.2) — fixed by using plain backticked text. See Codebase Patterns #1; task 1.2 will create that class, at which point a `{@see \…}` becomes safe.
  - The model deliberately has NO factory — the projection is consumer-written, and tests create rows directly. Task 1.2's consumer is the first real writer; 3.2's gate the first reader.
  - PG run cmd (avoids arch-OOM + pao stdout fatal): `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest <paths>`.
---
