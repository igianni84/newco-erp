# Progress — catalog-module-0-completeness-sweep

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Cross-engine constraint proof = nested `DB::transaction()` savepoint + the RIGHT exception class.** Wrap the violating DML in `DB::transaction(fn () => …)` so PostgreSQL's aborted-transaction state rolls back to the savepoint and the follow-on row-state assertions still run (SQLite needs no such care — PG does). Assert `UniqueConstraintViolationException` for a unique violation, plain `QueryException` for an FK `RESTRICT` (Laravel narrows only the unique case). Precedents: `ProductReferenceTest` (unique), `ProducerStateProjectionTest` (raw-insert CHECK).
- **Verify the 63-byte identifier rule empirically, don't eyeball it.** After a PG run the schema persists in `newco_test`: `docker exec pg psql -U newco -d newco_test -c "SELECT conname, LENGTH(conname), contype FROM pg_constraint WHERE conrelid = '<table>'::regclass ORDER BY conname;"` shows every FK/unique/CHECK name and its real byte length. PG truncates *silently*, so an assertion-by-name test can pass for the wrong reason if two names collide after truncation — this query is the cheap proof they didn't.
- **A composite unique's leftmost prefix IS the supporting index for the narrower lookup.** Both engines answer `WHERE a = ? AND b = ?` from a `UNIQUE (a, b, c)` index. Adding a separate `(a, b)` index alongside it is pure duplication. Order the unique's columns so the hot lookup is its prefix, then say so in the migration docblock (this is why `catalog_variant_case_whitelists` has no `catalog_vcw_variant_format_index`, despite design D6's prose).
- **`Schema::getColumnListing()` + `toEqualCanonicalizing()` pins a table's full shape cross-engine.** PG and SQLite return the columns in different orders; canonicalizing compares as sets. Pairs well with the `foreach ($columns as $column) expect($column)->not->toContain('break')` guard idiom (from `CaseConfigurationTest`) when a spec's contract is the ABSENCE of a field.

---

## [2026-07-08 15:03] — 1.1 Layer-1 whitelist pivot: migration + model + relation

- **What was implemented.** The Layer-1 possible-case-configurations whitelist substrate (design D6; product-catalog — *Layer-1 Case-Configuration Whitelist*; Module 0 PRD § 3.3 + § 7.1; AC-0-J-13 / AC-0-XM-11).
  - `catalog_variant_case_whitelists`: surrogate `id`; `product_variant_id` FK **cascade** (a whitelist entry is a statement *about* its Variant); `format_id` + `case_configuration_id` FK **restrict** (framework default — both are standalone SHARED reference entities, same asymmetry as `catalog_product_references` and `catalog_sellable_skus`); `timestampsTz()`; `UNIQUE (product_variant_id, format_id, case_configuration_id)`. No `lifecycle_state`, no `version`, no boolean — the whitelist rides its Variant's lifecycle.
  - `VariantCaseWhitelistEntry` model (persistence-only, `$guarded = []`, three ids cast to `integer`, no `belongsTo` accessors — nothing reads an entry as an object graph, only as a set of ids).
  - `ProductVariant::caseWhitelistEntries(): HasMany` — within-module relation (architecture rule; no boundary amendment needed).
- **Files changed.** `database/migrations/2026_07_08_000001_create_catalog_variant_case_whitelists_table.php` (new) · `app/Modules/Catalog/Models/VariantCaseWhitelistEntry.php` (new) · `app/Modules/Catalog/Models/ProductVariant.php` (relation + import + `@property-read`) · `tests/Feature/Modules/Catalog/VariantCaseWhitelistSchemaTest.php` (new, 7 tests / 29 assertions).
- **Quality loop: green.** Pint clean · new test 7/7 on **SQLite AND PG17** · full suite **2087/2087** (10 883 assertions, was 2080 — +7) · PHPStan max **0 errors** · `openspec validate --strict` valid. Constraint names verified untruncated on PG: `catalog_vcw_variant_fk` (22) / `catalog_vcw_format_fk` (21) / `catalog_vcw_case_config_fk` (26) / `catalog_vcw_variant_format_case_unique` (38) — all < 63 bytes.

### Acceptance walk
| Bullet | Evidence |
|---|---|
| Columns + FK modes + `timestampsTz` + unique triple | migration; `it('persists an admitted …triple…')`, `it('restricts deleting a Format or a Case Configuration…')`, `it('cascades whitelist entries away with the Variant…')` |
| Names `catalog_vcw_*`, < 63 bytes | `pg_constraint` query above (the only auto-named object is the `$table->id()` pkey, 36 bytes — identical to every sibling spine table) |
| Model casts three ids to integer; Variant relation | `VariantCaseWhitelistEntry::casts()`; `it('exposes the Variant\'s whitelist entries through a within-module relation')` |
| Unique triple rejects a duplicate **on both engines** | `it('rejects a duplicate triple at the DB unique index…')`, run green under SQLite and PG17 |
| FK restrict on format/case-config delete | `it('restricts deleting a Format or a Case Configuration that a whitelist entry names')` |
| XM-11 absence half — no `is_breakable`/`breakable` column | `it('carries no breakability attribute or column…')`: three `Schema::hasColumn` negatives + the `not->toContain('break')` column scan (mirrors `CaseConfigurationTest`) + a canonical full-column-set assertion |

- **Learnings for future iterations:**
  - **Deliberate, documented micro-deviation from design D6.** D6 says "unique on the triple **+ supporting index on the pair**". The unique is ordered `(variant, format, case_configuration)`, so its leftmost prefix already serves both reads this table will ever have — the pair's admitted set (task 3.1's replace) and "is this CC admitted for this pair?" (task 3.2's activation gate). A separate pair index would be duplication on both engines. Recorded in the migration docblock and the Codebase Patterns above; **no functional consequence for 3.1/3.2**, which should just query `where('product_variant_id', …)->where('format_id', …)`.
  - **Task 3.1's replace has a stable PK to work with**, unlike `catalog_composite_sku_constituents` (natural-key pivot, no `id`). That was the reason for the surrogate `id` — delete-then-insert of a pair's rows stays an ordinary Eloquent write.
  - **No factory was created.** Nothing in the architecture suite requires one (`ModulePersistenceConventionsTest` only pins the module-prefixed `$table`; `ModuleConformanceTest` only pins directories). Tests write rows straight through the model. If tasks 3.x/6.2 want a fixture helper, a factory is a free add — but note Pest top-level function names share one global namespace, so any new helper must stay uniquely named per file (this file's is `vcwEntry()`).
  - **`ProductVariant` now imports `Illuminate\Database\Eloquent\Collection`** for the `@property-read` on the new relation — PHPStan max needs the generic `Collection<int, VariantCaseWhitelistEntry>`, mirroring `CompositeSku::$constituents`.
  - **Seams still open (expected, not blockers):** no maintenance Action (task 3.1) and no activation-gate read (task 3.2) exist yet, so nothing writes this table in production code — it is currently reachable only from tests. `DemoSeeder`'s truncation sweep still needs the design's Migration-note check (task 5.2).

---
