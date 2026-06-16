---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter 11 — task 4.4 of `catalog-lifecycle-approval` DONE).** Product Reference lifecycle — the SECOND CHILD and the FIRST MULTI-PARENT entity (a PR depends on BOTH its Product Variant AND its Format). First reuse of the 4.3 within-module cascade gate on a multi-parent child: the SAME `ActivationCascadeGate::assertParentActive` primitive called TWICE in the Activate gate closure (Variant first, then Format), each with its own `$parentLabel` so the rejection NAMES the blocking parent. ZERO new gate service/exception/lang key (Patterns #22/#23 confirmed, not extended — per-PARENT primitive needed no change). Added the two `*Activated`/`*Retired` events (`{product_reference_id, product_variant_id, format_id, lifecycle_state}`, design D9) + the five thin Actions; `ProductReference` opted into `HasLifecycleState`; `ProductReferenceCreated` "no sibling" docblock fixed.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 573/573 on SQLite** (was 559; +14), phpstan max 0, pint clean. **PG17 verified:** `tests/Feature/Modules/Catalog` + `tests/Unit/Modules/Catalog` + `tests/Architecture` = **169/169** (was 155; +14) — the TWO sibling `lifecycle_state` reads (both parents), per-entity governance lineage scoped by `entity_type`+`entity_id` (isolated across the producer→Master→Variant→Format→PR chain), audit/event `actor_id` string-vs-int, jsonb payload, `FOR UPDATE` lock all proven; boundary/conventions/naming arch tests green. `openspec validate --strict` ✓. Guards: no new migration, no composer drift, no protected files, no Parties import in production.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 11/17 tasks.** Done: producer projection+consumer (1.x), shared FSM+governance+rejection (2.x), Product Master events+activate/retire+gate (3.x), Format (4.1), Case Configuration (4.2), Product Variant + first within-module gate (4.3), Product Reference + first multi-parent gate (4.4). Remaining: two more CHILD entities (4.5–4.6), cascades (5.x), docs+e2e (6.x).
- **Next: task 4.5** — Sellable SKU lifecycle + gate (PR active AND Case Configuration active). SAME multi-parent shape as 4.4 — Activate gate closure calls `assertParentActive` TWICE (PR + CaseConfiguration). Events `SellableSKUActivated`/`Retired` (UPPER-case `SKU` in NAME/class; model stays `SellableSku` — the casing the Naming Cascade test pins) payload `{sellable_sku_id, product_reference_id, case_configuration_id, lifecycle_state}` (verify vs design D9) + the five Actions. **Verify** `SellableSku` model needs `HasLifecycleState`+accessor; check `CreateSellableSku` signature + how a SKU binds `product_reference_id`/`case_configuration_id` (test helper). Three negatives: PR inactive / CaseConfig inactive / both active. NO new gate family. Uniquely-name helpers. **DB-touching → PG17 required.**

## Blockers & Decisions Needed
- **None blocking.** Standing design calls (stable across ralph): (1) standalone entities (Format/CaseConfig) pass NO `$gate` but STILL run approval governance; (2) child entities add a within-module parent-active `gate:` via the shared `ActivationCascadeGate` — REUSE it, no new gate family (multi-parent → call the primitive once per parent; N-constituent → loop it); (3) child `*Activated`/`*Retired` payload = `{<entity>_id, <parent ids…>, lifecycle_state}` (lifecycle_state LAST) per design D9.
- Deferred follow-ons (named): `catalog-operator-console` (Filament approval queue UI), `parties-compliance` (KYC tightens `ActivateProducer` — D6), Phase-3 referencers (cross-module retirement-blocking refs — D8, extends 5.2).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin).
- **Per-entity lifecycle recipe (4.x) — proven on 4 entities (2 standalone + 2 children, one multi-parent), stable:** two `final` events + model `HasLifecycleState` opt-in + five thin Actions + fix the `*Created` "no sibling" docblock. Standalone → Activate passes NO `$gate`; children → Activate passes a `gate:` calling `ActivationCascadeGate::assertParentActive` once per parent (multi-parent: call N times, ordered, each with its own `$parentLabel`; assert the `:parent` token to prove each parent independently gated). Audit verb table-derived (Pattern #14). See progress.md Codebase Patterns.
- **Gate ordering (test fixtures):** from-state FIRST → governance SECOND → gate THIRD. Self-approval negative needs ACTIVE parents (isolate governance); from-state negative needs DRAFT child (prove FSM precedes gate); multi-parent negative holds the OTHER parent active so only the tested parent's gate fires (and names it). Factory-`lifecycle_state` parents are legit for negatives (gate reads only `lifecycleState()`); reserve genuinely-active immediate parents for the flagship positive (full five-level chain w/ event ordering = task 5.1).
- **Each `*LifecycleTest.php` names helpers UNIQUELY** (one shared Pest namespace — a redeclare is a fatal full-suite load error). Don't pass BOTH a file AND its folder to one `pest` run. For PG pass the three folders (`Feature/Modules/Catalog` + `Unit/Modules/Catalog` + `Architecture`).
- **Uncast bigint cross-engine:** `actor_id` is int on SQLite, numeric STRING on PG — assert with `->toEqual()` (loose), never `->toBe()`. THE reason event/audit/governance-lineage work is PG-gated.
- **PG17 gate:** `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; wait `docker exec pg bash -c 'for i in $(seq 1 40); do pg_isready -U newco -q && exit 0; sleep 0.5; done'`; env `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest <folders>`; `docker rm -f pg`. Lifecycle tests use `DatabaseMigrations`, not `RefreshDatabase`.
