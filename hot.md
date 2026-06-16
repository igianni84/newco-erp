---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter 14 — task 5.1 of `catalog-lifecycle-approval` DONE).** Activation cascade INTEGRATION test — the first section-5 task and the first cross-entity proof. **TEST-ONLY, no production glue:** confirmed parent-before-child `*Activated` `domain_events.id` ordering is EMERGENT — each `Activate*` commits in its OWN transaction (id ascends in activation order) AND the within-module gate forbids a child reaching `active` before its parent, so parent-before-child is the ONLY order the cascade permits (design D7 "falls out for free"). 1 new file `ActivationCascadeTest.php` (2 tests/21 assert): **Test A** builds the whole spine to `reviewed` (helper `cascadeReviewedSpine()`: project producer 7 active → create+submit Master/Variant/Format/PR/CaseConfig/SKU, fresh operator per step), activates in order, asserts all six `active` + the 4 hierarchy events plucked `orderBy('id')` == `[ProductMaster, ProductVariant, ProductReference, SellableSKU]Activated`; **Test B** from the all-reviewed spine, activating each child before its parent throws `ActivationCascadeViolation` naming the blocking parent (Variant→ProductMaster, PR→ProductVariant, SKU→ProductReference), child stays `reviewed`, zero catalog `*Activated`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 602/602 on SQLite** (was 600; +2), phpstan max 0, pint clean. **PG17 verified:** `tests/Feature/Modules/Catalog` + `tests/Unit/Modules/Catalog` + `tests/Architecture` = **198/198** (was 196; +2) — per-transaction auto-increment id ordering, gate composition across the real chain, producer projection via post-commit hook, `entity_id` string compare, module-filtered `%Activated%` query all proven. `openspec validate --strict` ✓. Guards: no new migration, no composer drift, no protected files, no Parties import in production.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 14/17 tasks.** Done: producer projection+consumer (1.x), shared FSM+governance+rejection (2.x), Master events+activate/retire+gate (3.x), all 6 other entities (4.1–4.6), activation cascade ordering integration (5.1). Remaining: 5.2 (retirement cascade + ref-integrity), 6.1 (docs), 6.2 (e2e + cross-engine close).
- **Next: task 5.2** — retirement cascade + within-catalog reference integrity (BR-Lifecycle-5). `App\Modules\Catalog\Actions\RetireProductMasterCascade`: retire Master + descendants in ONE transaction, parent-before-child (Master→Variants→PRs→SKUs), recording each `*Retired` in that order — **ordering is EXPLICIT here, NOT emergent** (single txn, multiple events — see Open Patterns / progress #24). Add the BR-Lifecycle-5 guard to single-entity `Retire*` Actions: reject a retire while the entity has ACTIVE within-catalog references (Master w/ active Variants; PR referenced by an active Sellable/Composite SKU), surfacing the open refs. Retiring a parent PRESERVES existing active children (no retroactive invalidation — AC-0-FSM-11), only blocks NEW activation. Cross-module leg = documented seam (Phase-3). `tests/Feature/Modules/Catalog/RetirementCascadeTest.php` (`DatabaseMigrations`). **DB-touching → PG17 required.** Reuse `cascadeReviewedSpine()` shape.

## Blockers & Decisions Needed
- **None blocking.** Standing design calls (stable across ralph): (1) standalone entities (Format/CaseConfig) pass NO `$gate` but STILL run approval governance; (2) child entities add a within-module parent-active `gate:` via the shared `ActivationCascadeGate` — REUSE it (single→once, multi→once per parent, N-constituent→loop the junction); (3) child `*Activated`/`*Retired` payload = `{<entity>_id, <parent ids…>, lifecycle_state}` (lean, no derived enrichments) per design D9.
- Deferred follow-ons (named): `catalog-operator-console` (Filament approval queue UI), `parties-compliance` (KYC tightens `ActivateProducer` — D6), Phase-3 referencers (cross-module retirement-blocking refs — D8, extends 5.2).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin).
- **ACTIVATION cascade ordering EMERGENT; RETIREMENT cascade ordering EXPLICIT (5.1 vs 5.2).** Activation: separate Actions → separate txns → `domain_events.id` ascends in order + gate forbids non-parent-before-child → no glue. Retirement cascade: ONE txn, multiple `*Retired` → must record Master→Variants→PRs→SKUs in sequence by hand. Don't assume retirement "falls out" like activation.
- **Per-entity lifecycle recipe (ALL 7 spine entities, stable):** two `final` events + model `HasLifecycleState` opt-in + five thin Actions + fix the `*Created` "no sibling" docblock. Standalone → Activate passes NO `$gate`; children → Activate passes a `gate:` calling `ActivationCascadeGate::assertParentActive`. SKU naming split: event NAME/class UPPER `SKU`, model/Actions/`ENTITY_TYPE` use `Sku`. Audit verb table-derived.
- **Each `*LifecycleTest.php`/cascade test names helpers UNIQUELY** (one shared Pest namespace — a redeclare is a fatal full-suite load error). Re-read rows via `Model::findOrFail` in assertions so every imported model is used in code (docblock-only `@return array{…: Model}` import risks no_unused_imports lint). Don't pass BOTH a file AND its folder to one `pest` run. For PG pass the three folders.
- **Uncast bigint cross-engine:** int on SQLite, numeric STRING on PG — assert with `->toEqual()` (loose), or `(int)`-cast at source for a clean contract (works when `@property int` makes the cast phpstan-clean). THE reason event/audit/governance-lineage work is PG-gated.
- **PG17 gate:** `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; wait `docker exec pg bash -c 'for i in $(seq 1 40); do pg_isready -U newco -q && exit 0; sleep 0.5; done'`; env `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest <folders>`; `docker rm -f pg`. Lifecycle/cascade tests use `DatabaseMigrations`, not `RefreshDatabase`.
