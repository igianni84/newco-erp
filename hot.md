---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter 7 — task 3.2 of `catalog-lifecycle-approval` DONE).** Wired Product Master's two commercial-impact transitions: `ActivateProductMaster` (`reviewed→active` — governance + the NEW **Producer activation gate** + records `ProductMasterActivated`) and `RetireProductMaster` (`active→retired` — records `ProductMasterRetired`). The shared `LifecycleTransition::transition()` gained two optional seams 4.x/5.x reuse: a **`$gate` closure** (`(TModel):void`, run AFTER governance, BEFORE the write) and a **`$event` intent** (`(TModel): array{name,payload}`, recorded via `DomainEventRecorder` AFTER the write, in-txn — mechanism owns the envelope). New `ProducerActivationGate` (reads ONLY `catalog_producer_states`, fail-closed: no row≡retired→reject; lock-free read) + `ProducerActivationGateViolation` (localized `catalog.gate.producer_not_active`). The cross-module gate realizes AC-0-FSM-12/EVT-20/21/J-2/J-10 on the 1.2 consumer's projection.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 522/522 on SQLite** (was 514; +8), phpstan max 0, pint clean. **PG17 verified:** ProductMasterLifecycleTest 22/22 + `tests/Feature/Modules/Catalog` + `tests/Unit/Modules/Catalog` + `tests/Architecture` = **118/118** (uncast `actor_id` string-vs-int on the recorded events, jsonb payload, `FOR UPDATE` lock, producer-state CHECK, gate read all proven; ModuleBoundaries/ModulePersistenceConventions/CatalogNamingCascade green). `openspec validate --strict` ✓. Guards: no new migration, no composer drift, no protected files.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 7/17 tasks.** Done: producer projection+consumer (1.x), shared FSM+governance+rejection (2.x), Product Master events (3.1) + activate/retire+gate (3.2). Remaining: the other six entities' lifecycle (4.x), cascades (5.x), docs+e2e (6.x).
- **Next: task 4.1** — Format lifecycle (STANDALONE, no parent gate): `FormatActivated`/`FormatRetired` events (mirror 3.1's DB-free event shape) + `Submit/Activate/Retire/Reopen/RejectFormat` Actions (mirror 3.2's Action shape — Activate passes ONLY the `$event` intent + governance, NO `$gate`; Retire passes the `*Retired` intent). New `FormatLifecycleTest.php` (`DatabaseMigrations`, distinct operators): submit+approve→active+`FormatActivated`; illegal transition throws; from-state guard. **DB-touching → PG17 required.** Verify the `Format` model has the `HasLifecycleState` accessor (may need adding, as ProductMaster got in 2.2).

## Blockers & Decisions Needed
- **None blocking.** Standing design calls (accept/veto during ralph): (1) the activation gate is lock-free + ordered AFTER governance + AFTER the from-state assert (a self-approval throws governance, an out-of-state activate throws `IllegalLifecycleTransition`, only a reviewed+distinct-actor activate reaches the gate); (2) 4.x parent-active cascade gates read sibling `lifecycle_state` WITHIN module — a DIFFERENT exception family from this cross-module Producer gate (separate spec Requirement); (3) "draft producer" ≡ absent from projection (the read model only carries active/retired).
- Deferred follow-ons (named): `catalog-operator-console` (Filament approval queue UI), `parties-compliance` (KYC tightens `ActivateProducer` upstream — D6), Phase-3 referencers (cross-module retirement-blocking refs — D8, extends 5.2's within-catalog guard).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin).
- **`transition()` gate/event seam (4.x/5.x reuse):** Activate Action passes `gate:` + `event:`; Retire passes only `event:`; Submit/Reopen/Reject pass neither; `$entity` label = `XActivated::ENTITY_TYPE`. The array-intent is atomic + phpstan-max-clean through the nested `DB::transaction` + `use`. See progress.md Codebase Patterns.
- **Uncast bigint cross-engine:** `actor_id` on `domain_events`/`audit_records` is int on SQLite, numeric STRING on PG — assert with `->toEqual()` (loose), never `->toBe()`. THE reason event/audit-actor work is PG-gated.
- **Projecting a producer in a test:** `lifecycleProjectProducer()` records a Parties `ProducerActivated`/`Retired` in `DB::transaction` → inline consumer projects (needs `DatabaseMigrations`); a first-seen `ProducerRetired` seeds the row retired.
- **PG17 gate:** `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; env `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest <paths>`; `docker rm -f pg`. Lifecycle/consumer tests use `DatabaseMigrations`, not `RefreshDatabase`.
