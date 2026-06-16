---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter 8 — task 4.1 of `catalog-lifecycle-approval` DONE).** Format lifecycle — the first of the six remaining spine entities and the first STANDALONE one (no parent ⇒ NO activation gate, only approval governance). Added `FormatActivated`/`FormatRetired` (`final`, payload `{format_id, lifecycle_state}` — the minimal PII-free pair, no parent ids) + the five thin Actions (`Submit/Reopen` bare audit-only, `Reject`, `Activate` passing ONLY the `event:` intent + governance NO `$gate`, `Retire` passing the `*Retired` intent). `Format` model gained `implements HasLifecycleState` + the `lifecycleState()` accessor (as ProductMaster did in 2.2); `FormatCreated` docblock's stale "no sibling" line fixed to same-namespace cross-refs. Pure mirror of the 3.1 event shape + 3.2 Action shape, minus the Producer gate.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 534/534 on SQLite** (was 522; +12), phpstan max 0, pint clean. **PG17 verified:** Format lifecycle+events 12/12 + `tests/Feature/Modules/Catalog` + `tests/Unit/Modules/Catalog` + `tests/Architecture` = **130/130** (was 118; +12) — audit/event `actor_id` string-vs-int, jsonb payload, `FOR UPDATE` lock, governance lineage reads all proven; ModuleBoundaries/ModulePersistenceConventions/CatalogNamingCascade green. `openspec validate --strict` ✓. Guards: no new migration, no composer drift, no protected files.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 8/17 tasks.** Done: producer projection+consumer (1.x), shared FSM+governance+rejection (2.x), Product Master events+activate/retire+gate (3.x), Format lifecycle (4.1). Remaining: the other five entities (4.2–4.6), cascades (5.x), docs+e2e (6.x).
- **Next: task 4.2** — Case Configuration lifecycle (STANDALONE, no parent gate — EXACT mirror of 4.1): `CaseConfigurationActivated`/`Retired` events (payload `{case_configuration_id, lifecycle_state}`) + the five Actions via the shared mechanism (Activate passes ONLY `event:` + governance, NO `$gate`). New `CaseConfigurationLifecycleTest.php` + a `CaseConfigurationLifecycleEventsTest.php`. **Verify the `CaseConfiguration` model needs `HasLifecycleState` + the accessor added** (likely, as Format did); check `CreateCaseConfiguration` signature for the test helper. Uniquely-name the helper `lifecycleCreateDraftCaseConfiguration` (Pest shares one global namespace — collision = fatal). **DB-touching → PG17 required.**

## Blockers & Decisions Needed
- **None blocking.** Standing design calls (accept/veto during ralph): (1) standalone entities (Format 4.1, CaseConfig 4.2) pass NO `$gate` but STILL run approval governance — the test must prove a self-approval is rejected, else "no parent gate" mis-reads as "no precondition"; (2) 4.3–4.6 add a within-module parent-active `gate:` closure (Variant→Master, PR→Variant+Format, SKU→PR+CaseConfig, Composite→all constituents) — a NEW within-catalog gate exception family, distinct from the cross-module Producer gate; (3) standalone `*Activated`/`*Retired` payload = `{<entity>_id, lifecycle_state}` only (no parent ids).
- Deferred follow-ons (named): `catalog-operator-console` (Filament approval queue UI), `parties-compliance` (KYC tightens `ActivateProducer` — D6), Phase-3 referencers (cross-module retirement-blocking refs — D8, extends 5.2).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin).
- **Per-entity lifecycle recipe (4.x):** two `final` events + model `HasLifecycleState` opt-in + five thin Actions + fix the `*Created` "no sibling" docblock line. Standalone (Format/CaseConfig) → Activate passes NO `$gate`; the four children → Activate passes a within-module parent-active `gate:`. Audit verb is table-derived (Pattern #14). See progress.md Codebase Patterns.
- **Each `*LifecycleTest.php` names its create-helper UNIQUELY** (`lifecycleCreateDraftFormat` ≠ `…Master`) — one shared Pest namespace; a redeclare is a fatal full-suite load error. Reference `LifecycleTransition` in backticked prose, not a `{@see \FQCN}` (Pint auto-imports it, `no_unused_imports` won't strip it later).
- **Uncast bigint cross-engine:** `actor_id` is int on SQLite, numeric STRING on PG — assert with `->toEqual()` (loose), never `->toBe()`. THE reason event/audit-actor work is PG-gated.
- **PG17 gate:** `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; wait `docker exec pg bash -c 'for i in $(seq 1 40); do pg_isready -U newco -q && exit 0; sleep 0.5; done'`; env `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest <paths>`; `docker rm -f pg`. Lifecycle tests use `DatabaseMigrations`, not `RefreshDatabase`.
