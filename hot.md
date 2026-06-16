---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter 6 — task 3.1 of `catalog-lifecycle-approval` DONE).** Added the first two of the change's fourteen lifecycle event classes — `ProductMasterActivated` + `ProductMasterRetired` (design D9). Pure event contracts (no DB, no behaviour): `final` classes mirroring `ProductMasterCreated`, each `NAME` (verbatim §14.1) / `ENTITY_TYPE='ProductMaster'` / static `payload(ProductMaster)` → **`{product_master_id, producer_id, lifecycle_state}`** — PII-free, producer by id. §14.2: `*Activated`=`reviewed→active`, `*Retired`=`active→retired`; the `draft→reviewed`/`retired→reviewed` checkpoints stay audit-only (no `*Reviewed`). Also corrected the now-stale "No `*Activated`/`*Retired` sibling exists" line in `ProductMasterCreated` (same-namespace `{@see}` cross-refs). Task 3.2 will RECORD these via the shared `transition()` + the Producer gate.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 514/514 on SQLite** (was 510; +4), phpstan max 0, pint clean. **No PG run this iter** (pure event classes touch no DB — design D9; last PG gate was iter 5 task 2.3 at 106/106). `openspec validate --strict` ✓. Guards: no new migration (the change's single `catalog_producer_states` migration shipped in 1.1), no composer drift, no protected files.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 6/17 tasks.** Module 0 lifecycle FSM + governance (done 2.3) + 3.1 events; remaining: Producer gate (3.2), the other six entities (4.x), cascades (5.x), docs+e2e (6.x).
- **Next: task 3.2** — `ActivateProductMaster` (`reviewed→active`): governance guard already wired in `transition()` + the **NEW Producer gate** (read `catalog_producer_states` for the Master's `producer_id`, require `status=Active`, else a localized gate exception added to `lang/en/catalog.php`) → records `ProductMasterActivated`. `RetireProductMaster` (`active→retired`) → records `ProductMasterRetired`. Both via the shared mechanism (audit + event in ONE txn). Re-activation re-checks the gate (same Action). **DB-touching → PG17 run REQUIRED.** Extend `ProductMasterLifecycleTest.php` (distinct operators; project producer 7 `active` via a recorded `ProducerActivated` as in 1.2; three negative gate paths absent/`draft`/`retired`; block-new/preserve-actives; re-activation).

## Blockers & Decisions Needed
- **None blocking.** Standing design calls (accept/veto during ralph): (1) approval-step SoD distinctness is keyed on `=== Activate`; retire/reopen carry only the operator-principal floor; (2) "rejection-pending" is a derived read (latest `%.rejected` audit action), no schema flag; (3) KYC conjunct enforced upstream (D6 → `parties-compliance`), Module 0 gates only on producer `active`.
- Deferred follow-ons (named): `catalog-operator-console` (Filament approval queue UI), `parties-compliance` (KYC tightens `ActivateProducer`), Phase-3 referencers (cross-module retirement-blocking refs — D8).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin).
- **DB-free event-payload unit test** (the shape every 4.x event task reuses): `tests/Unit/.../Events/`, `uses(TestCase::class)`, NO `RefreshDatabase` (no migrated schema ⇒ self-guards zero queries); fixture via `Model::factory()->make([...])` (query-free, `$guarded=[]` lets you override `id`); a file-level `$fixture = fn(...) => …` threaded via `use ($fixture)` (NOT a top-level fn — redeclare-fatal on full-suite load); assert `array_keys(...)->toBe([...])` AND whole-array `->toBe([...])` (pure PHP array ⇒ order deterministic, jsonb caveat N/A); PII-free via `not->toHaveKey`/`not->toContain`. Naming Cascade already covered by `CatalogNamingCascadeTest` (scans for `^Wine`/`^BottleReference`).
- **Uncast bigint cross-engine:** `value('actor_id')` is `int` on SQLite but a numeric STRING on PG — normalize reads with `is_numeric($raw) ? (int) $raw : null` before `===` (only matters for the 3.2+ guards that read prior actors; pure event classes don't hit this).
- **Shared mechanism (3.2 extends it):** `transition()` already runs from-state assert → governance guard; 3.2 adds an optional Producer-gate predicate BEFORE the write + a `DomainEventRecorder` event factory AFTER. `LifecycleTransitionType::Activate/Retire` already encode their edges.
- **PG17 gate (needed for 3.2):** `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; env `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest <paths>`; `docker rm -f pg`. Lifecycle tests use `DatabaseMigrations`, not `RefreshDatabase`.
