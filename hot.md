---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter 10 — task 4.3 of `catalog-lifecycle-approval` DONE).** Product Variant lifecycle — the FIRST CHILD entity and the point where the 4.x recipe DIVERGES from the two standalone entities: activation now carries a WITHIN-MODULE activation-cascade gate (parent `ProductMaster` must be `active`). Established the within-catalog gate family — shared `ActivationCascadeGate` (pure `assertParentActive(?HasLifecycleState $parent, entity, parentLabel)`, null fail-closed, reads a sibling spine `lifecycle_state` directly — no projection) + parameterized `ActivationCascadeViolation` (`catalog.gate.parent_not_active`, `:entity`+`:parent`) — the within-catalog sibling of the cross-module Producer gate. Added the two `*Activated`/`*Retired` events (`{product_variant_id, product_master_id, lifecycle_state}`) + the five thin Actions (`ActivateProductVariant` passes the cascade `gate:` + event intent); `ProductVariant` model opted into `HasLifecycleState`; `ProductVariantCreated` "no sibling" docblock fixed.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 559/559 on SQLite** (was 546; +13), phpstan max 0, pint clean. **PG17 verified:** `tests/Feature/Modules/Catalog` + `tests/Unit/Modules/Catalog` + `tests/Architecture` = **155/155** (was 142; +13) — cascade gate's sibling-state read, per-entity governance lineage (scoped by `entity_type`, proven on the full Master+Variant chain where both keys are `1`), audit/event `actor_id` string-vs-int, jsonb payload, `FOR UPDATE` lock all proven; boundary/conventions/naming arch tests green. `openspec validate --strict` ✓. Guards: no new migration, no composer drift, no protected files, no Parties import.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 10/17 tasks.** Done: producer projection+consumer (1.x), shared FSM+governance+rejection (2.x), Product Master events+activate/retire+gate (3.x), Format (4.1), Case Configuration (4.2), Product Variant + the FIRST within-module gate (4.3). Remaining: three more CHILD entities (4.4–4.6), cascades (5.x), docs+e2e (6.x).
- **Next: task 4.4** — Product Reference lifecycle + gate (Variant active AND Format active). REUSES `ActivationCascadeGate` verbatim — Activate passes a `gate:` calling `assertParentActive` TWICE (Variant, Format). Events `ProductReferenceActivated`/`Retired` payload `{product_reference_id, product_variant_id, format_id, lifecycle_state}` (design D9) + the five Actions. **Verify** the `ProductReference` model needs `HasLifecycleState`+accessor; check `CreateProductReference` signature + how a PR binds `product_variant_id`/`format_id` (the test helper). Three negative cases: Variant inactive / Format inactive / both active. NO new gate service/exception/lang key (the cascade family is entity+parent-parameterized). Helper uniquely named. **DB-touching → PG17 required.**

## Blockers & Decisions Needed
- **None blocking.** Standing design calls (stable across ralph): (1) standalone entities (Format/CaseConfig) pass NO `$gate` but STILL run approval governance; (2) child entities (4.3 done; 4.4–4.6) add a within-module parent-active `gate:` via the shared `ActivationCascadeGate` — REUSE it, no new gate family; (3) child `*Activated`/`*Retired` payload = `{<entity>_id, <parent ids…>, lifecycle_state}` (lifecycle_state LAST) per design D9.
- Deferred follow-ons (named): `catalog-operator-console` (Filament approval queue UI), `parties-compliance` (KYC tightens `ActivateProducer` — D6), Phase-3 referencers (cross-module retirement-blocking refs — D8, extends 5.2).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin).
- **Per-entity lifecycle recipe (4.x) — proven on 3 entities (2 standalone + 1 child), stable:** two `final` events + model `HasLifecycleState` opt-in + five thin Actions + fix the `*Created` "no sibling" docblock line. Standalone → Activate passes NO `$gate`; children (4.4–4.6) → Activate passes a `gate:` calling the shared `ActivationCascadeGate::assertParentActive` once per parent (loop for Composite). Audit verb table-derived (Pattern #14). See progress.md Codebase Patterns (within-module cascade gate + full-chain active-parent helper).
- **Gate ordering (for test fixtures):** from-state FIRST → governance SECOND → gate THIRD. Self-approval negative needs an ACTIVE parent (isolate governance); from-state negative needs a DRAFT parent + draft child (prove FSM precedes gate). A factory-active parent (`ProductMaster::factory()->create(['lifecycle_state'=>Active])`) is a legit fixture for child governance/retire tests; reserve the full-chain helper for the flagship positive-activation test (the full chain is task 5.1's job).
- **Each `*LifecycleTest.php` names its helpers UNIQUELY** (one shared Pest namespace — a redeclare is a fatal full-suite load error). Don't pass BOTH a file AND its folder to one `pest` run. For PG pass the three folders (`Feature/Modules/Catalog` + `Unit/Modules/Catalog` + `Architecture`).
- **Uncast bigint cross-engine:** `actor_id` is int on SQLite, numeric STRING on PG — assert with `->toEqual()` (loose), never `->toBe()`. THE reason event/audit/governance-lineage work is PG-gated.
- **PG17 gate:** `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; wait `docker exec pg bash -c 'for i in $(seq 1 40); do pg_isready -U newco -q && exit 0; sleep 0.5; done'`; env `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest <folders>`; `docker rm -f pg`. Lifecycle tests use `DatabaseMigrations`, not `RefreshDatabase`.
