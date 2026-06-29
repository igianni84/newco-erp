# Data-Model — Rules (apply by default)

> Promoted from `hypotheses.md` (3 dated confirmations) or derived directly from a canonical decision / migration / CI finding. A contradiction demotes a rule back to `hypotheses.md`. Cross-engine **test** portability lives in `knowledge/testing/rules.md`.

## A PG-only `CHECK` goes in a `DB::getDriverName() === 'pgsql'` branch, with its enumerated values derived from `Enum::cases()`

**Rule.** A constraint only PostgreSQL can express (an enum-column `CHECK`, etc.) belongs in an `if (DB::getDriverName() === 'pgsql')` migration branch; the SQLite floor is the Eloquent enum **cast** (migrations stay Postgres-truthful, SQLite-compatible). **Derive the CHECK's allowed values from the backing enum's `::cases()`** (never a hand-typed `IN (...)` list) so the DB constraint can never drift from the PHP enum — one source of truth, two enforcers. Prove it engine-guarded: assert the named constraint **rejects** the bad insert on PG (assert the constraint NAME, never an engine SQLSTATE), wrapping the forbidden DML in a nested `DB::transaction()` (savepoint) so the verify-after-throw survives PG's aborted-transaction state.

**Confirmations (dated, cross-change).** 2026-06-12 `foundations-domain-events-audit` (`domain_events.actor_role`); 2026-06-15 `catalog-product-spine` (`lifecycle_state`); 2026-06-15 `parties-core` (per enum column; `party_type` proven on PG); 2026-06-16 `catalog-lifecycle-approval`. The engine-guarded CHECK-*test* idiom: 2026-06-13 `substrate-hardening`.

**Applies to.** Every migration introducing an enum-backed column. Pairs with the cross-engine portability rule in `knowledge/testing/rules.md`. *(Relocated 2026-06-16 from `knowledge/laravel/rules.md` — this is a DDL/migration pattern, so it lives here now.)*

## Give every index / FK / constraint an explicit SHORT name — Postgres silently truncates identifiers at 63 bytes

**Rule.** PostgreSQL truncates any identifier (index, constraint, FK, unique) to **63 bytes — silently**. Laravel's auto-generated names follow `{table}_{columns}_{type}`, which routinely overflows 63 chars for a long table name (`catalog_composite_sku_constituents`, `parties_producer_agreements`) — two distinct indexes can then collide after truncation, and the cross-engine assert-by-name proof breaks. **Pass an explicit short name everywhere the framework would auto-generate one:** `->constrained(table: '…', indexName: '<short_fk>')`, `->index('col', '<short_idx>')`, `->unique([...], '<short_name>')`, and a raw `CREATE [UNIQUE] INDEX <explicit_name>` for partial/expression indexes. Use a stable abbreviation prefix per table (`catalog_csc_*`, `parties_pa_*`). SQLite has no such limit, so this is **PG-only and invisible until the cross-engine run** — name them from the start.

**Confirmations (dated, cross-change).** 2026-06-12 `foundations-domain-events-audit` (explicit stable names — partial index `event_deliveries_pending_index`, `audit_records_actor_role_check`); 2026-06-15 `catalog-product-spine` (origin & richest — per-type FK / unique / join-table all overflow: `catalog_pv_wine_attrs_variant_fk`, `catalog_product_references_variant_format_unique`, `catalog_csc_*`); 2026-06-15 `parties-core` (`parties_pa_*`, `parties_profiles_customer_club_nonterminal_unique`); 2026-06-16 `catalog-lifecycle-approval` (projection migration: "explicit, <63-char index names").

**Applies to.** Every migration whose table name is long enough that an auto-generated index/FK/constraint/unique name would exceed 63 chars — in practice any module table with a multi-word name. Pairs with the enum-`CHECK` rule above and the cross-engine portability rule in `knowledge/testing/rules.md`.

## Spine create-entity template: one new evented entity = migration + model + factory + creation-event + record-in-transaction Action

**Rule.** Standing up a new evented persistence entity follows a fixed skeleton, every part co-moving:
1. **Migration** `{module}_x` — columns + a driver-guarded PG `CHECK` from the enum's `::cases()` (see the enum-`CHECK` rule above) + explicit short index names (see the index-naming rule above).
2. **Model** `Models\X` with `$guarded = []` (the Action is the sole writer) and a **typed** `protected newFactory(): XFactory` — factories live off the `App\Models` PSR-4 convention, so Larastan infers `mixed` without the explicit return type.
3. **Factory** `Database\Factories\{Module}\XFactory` — a pure fixture that bypasses the Action (records **no** event; never use it to prove an evented path — see the through-the-Actions integration rule in `knowledge/testing/rules.md`).
4. **Creation event** `Events\X{Created|<DomainVerb>ed}` — untyped `const NAME` / `const ENTITY_TYPE` + a static **PII-free** `payload($x)`. The verb need not be literal `Created`: when the domain names the bring-into-existence act, the event/Action carry that verb (`CustomerHoldPlaced` + `PlaceHold`, not `HoldCreated` + `CreateHold`) — the *structure* is the invariant, not the word.
5. **Action** `Actions\{Create|<DomainVerb>}X` — wraps `Model::create()` **and** `$recorder->record(...)` in ONE `DB::transaction` (the recorder's level-0 `NotInTransactionException` guard makes write+emit atomic).

**Confirmations (dated, cross-change).** 2026-06-15 `catalog-product-spine` (origin — task 2.1 across 7 entities: `catalog_*` + `Models\*` + `Database\Factories\Catalog\*Factory` + `*Created` + `Create*`); 2026-06-15 `parties-core` (named the template explicitly — Producer / Club / ProducerAgreement / Customer+Account / Profile / Supplier); 2026-06-18 `parties-holds` (3rd, the domain-verb variant: `parties_holds` migration + `Hold` model (`$guarded=[]`, typed `newFactory()`) + `HoldFactory` + `CustomerHoldPlaced` event + `PlaceHold` Action — `Hold::create()` + `recorder->record()` in one `DB::transaction`, verified `app/Modules/Parties/Actions/PlaceHold.php:76-98`). *(The 2026-06-16 lifecycle changes build a **transition**-action variant on the same `DB::transaction` + `record` shape — a descendant, not a fresh create-entity spine, so they are not counted here.)*

**Applies to.** Any new evented domain entity in a module slice.
