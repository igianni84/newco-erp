---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter 9 — task 4.2 of `catalog-lifecycle-approval` DONE).** Case Configuration lifecycle — the second of the six remaining spine entities and the second STANDALONE one (no parent ⇒ NO activation gate, only approval governance). A verbatim mirror of the 4.1 Format recipe with zero deviation: added `CaseConfigurationActivated`/`CaseConfigurationRetired` (`final`, payload `{case_configuration_id, lifecycle_state}` — the minimal PII-free pair, no parent ids) + the five thin Actions (`Submit/Reopen` bare audit-only, `Reject`, `Activate` passing ONLY the `event:` intent + governance NO `$gate`, `Retire` passing the `*Retired` intent). `CaseConfiguration` model gained `implements HasLifecycleState` + the `lifecycleState()` accessor; `CaseConfigurationCreated` docblock's stale "no sibling" line fixed to same-namespace cross-refs.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 546/546 on SQLite** (was 534; +12), phpstan max 0, pint clean. **PG17 verified:** `tests/Feature/Modules/Catalog` + `tests/Unit/Modules/Catalog` + `tests/Architecture` = **142/142** (was 130; +12) — audit/event `actor_id` string-vs-int, jsonb payload, `FOR UPDATE` lock, governance lineage reads all proven; ModuleBoundaries/ModulePersistenceConventions/CatalogNamingCascade green. `openspec validate --strict` ✓. Guards: no new migration, no composer drift, no protected files, no Parties import.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 9/17 tasks.** Done: producer projection+consumer (1.x), shared FSM+governance+rejection (2.x), Product Master events+activate/retire+gate (3.x), Format lifecycle (4.1), Case Configuration lifecycle (4.2). Remaining: the four CHILD entities (4.3–4.6), cascades (5.x), docs+e2e (6.x).
- **Next: task 4.3** — Product Variant lifecycle + the FIRST within-module activation gate. This is where the 4.x recipe DIVERGES from the two standalone entities: `ProductVariantActivated`/`Retired` events + five Actions, but **Activate passes a `gate:` closure requiring the parent `ProductMaster` (`product_master_id`) to be `active`** (read sibling `lifecycle_state` WITHIN module — a NEW within-catalog gate exception family, separate from the cross-module Producer gate; add a localized gate key to `lang/en/catalog.php`). **Verify** the `ProductVariant` model needs `HasLifecycleState`+accessor added (likely, as CaseConfig did); check `CreateProductVariant` signature + how a Variant binds `product_master_id`; the positive test needs the FULL parent chain active (producer projected `active` → Master submit+approve+activate) before the Variant can activate. Helper uniquely named `lifecycleCreateDraftVariant`. **DB-touching → PG17 required.**

## Blockers & Decisions Needed
- **None blocking.** Standing design calls (accept/veto during ralph): (1) the two standalone entities (Format 4.1, CaseConfig 4.2) pass NO `$gate` but STILL run approval governance — proven by self-approval-rejected tests; (2) 4.3–4.6 add a within-module parent-active `gate:` closure (Variant→Master, PR→Variant+Format, SKU→PR+CaseConfig, Composite→all constituents) — a NEW within-catalog gate exception family, distinct from the cross-module Producer gate (its own spec Requirement + its own localized reason key); (3) standalone `*Activated`/`*Retired` payload = `{<entity>_id, lifecycle_state}` only; child payloads add the parent ids per design D9.
- Deferred follow-ons (named): `catalog-operator-console` (Filament approval queue UI), `parties-compliance` (KYC tightens `ActivateProducer` — D6), Phase-3 referencers (cross-module retirement-blocking refs — D8, extends 5.2).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin).
- **Per-entity lifecycle recipe (4.x) — proven on TWO standalone entities, stable:** two `final` events + model `HasLifecycleState` opt-in + five thin Actions + fix the `*Created` "no sibling" docblock line. Standalone (Format/CaseConfig) → Activate passes NO `$gate`; the four children (4.3–4.6) → Activate passes a within-module parent-active `gate:` (the recipe's first DIVERGENCE). Audit verb is table-derived (Pattern #14, e.g. `catalog_case_configurations → catalog.case_configuration.<verb>`), no per-Action wiring. No new lang key for standalone (reuses entity-parameterized `lifecycle`/`approval`); children ADD a within-catalog gate key. See progress.md Codebase Patterns #19/#20.
- **Each `*LifecycleTest.php` names its create-helper UNIQUELY** (`lifecycleCreateDraftCaseConfiguration` ≠ `…Format` ≠ `…Master`) — one shared Pest namespace; a redeclare is a fatal full-suite load error.
- **Pest dup-registration:** never pass BOTH an explicit test file AND its containing folder in one `pest` run ("already uses the test case [Tests\TestCase]"). For PG, pass just the three folders (`Feature/Modules/Catalog` + `Unit/Modules/Catalog` + `Architecture`) — they include the new files.
- **Uncast bigint cross-engine:** `actor_id` is int on SQLite, numeric STRING on PG — assert with `->toEqual()` (loose), never `->toBe()`. THE reason event/audit-actor work is PG-gated.
- **PG17 gate:** `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; wait `docker exec pg bash -c 'for i in $(seq 1 40); do pg_isready -U newco -q && exit 0; sleep 0.5; done'`; env `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest <folders>`; `docker rm -f pg`. Lifecycle tests use `DatabaseMigrations`, not `RefreshDatabase`.
