---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter — task 6.2 DONE → `catalog-lifecycle-approval` COMPLETE, 17/17).** Added the end-to-end cross-module test `tests/Feature/Modules/Catalog/CatalogLifecycleChainTest.php` (2 tests / 26 assertions): drives the REAL Module-K path — `Producer::factory()` → `ActivateProducer` → genuine `ProducerActivated` fans out (recorder + inline executor) to Catalog's `ProducerLifecycleProjector` → projection `active` → full Master→Variant→Format→PR→CaseConfig→SKU spine activates parent-before-child → `RetireProductMasterCascade` retires the ownership tree (Format/CaseConfig preserved) → `RetireProducer` proves block-new/preserve. Test-only Parties imports (allowed — arch scan ignores `tests/`). One new test file; no production code, no migration, no protected files.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Green (iter 17, task 6.2): full suite 613/613 SQLite (was 611; +2); phpstan max 0; pint clean; `openspec validate --strict` ✓. PG17 cross-engine close: `tests/Feature/Modules/Catalog` + `tests/Unit/Modules/Catalog` + `tests/Architecture` = 209/209** (was 196 @4.6; +2 @5.1, +9 @5.2, +2 @6.2) — real fan-out, projection enable→block, gate read, cascade orderings all proven on Postgres; `ModuleBoundaries`/`ModulePersistenceConventions`/`CatalogNamingCascade` green unamended. No composer drift.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 17/17 DONE → CHANGE COMPLETE.** All tasks `[x]`: 1.x consumer/projection, 2.x FSM+governance, 3.x Master events/activate/retire/gate, 4.1–4.6 the six other entities, 5.1 activation cascade, 5.2 retirement cascade + ref-integrity, 6.1 docs, **6.2 e2e + cross-engine close**.
- **Returned `<promise>CHANGE_COMPLETE</promise>`.** NOT archived, NOT merged — the human does the GUIDE §2.7 close (review → merge → push → semantic-verify → `openspec archive catalog-lifecycle-approval --yes`). Branch `ralph/catalog-lifecycle-approval`.

## Blockers & Decisions Needed
- **None.** Deferred follow-ons (unchanged): `catalog-operator-console` (Filament approval-queue UI on the audit-derived rejection-pending), `parties-compliance` (KYC tightens `ActivateProducer` upstream — D6), Phase-3 referencers (the cross-module retirement-blocking leg — D8). After the human archives this change, the next `/spec-to-change` picks the next Build_Workplan slice.

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in the arch plugin).
- **PG17 gate** (lifecycle/cross-module tests use `DatabaseMigrations`): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; wait `docker exec pg bash -c 'until pg_isready -U newco -q; do sleep 0.5; done'`; run `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest tests/Feature/Modules/Catalog tests/Unit/Modules/Catalog tests/Architecture`; `docker rm -f pg`.
- **Real cross-module e2e shape:** `Model::factory()->create()` (no event) → real Action (the emit) → under `DatabaseMigrations` the post-commit inline consumer projects SYNCHRONOUSLY, so the projection is updated before the next statement. Test-only cross-module imports (factory + Actions) are allowed — `ModuleBoundariesTest` scans only `App\Modules\*`/`App\Platform`, never `tests/`.
- **Heredoc `cat <<EOF >> file` mentioning "spec" trips the git-guardrails Bash hook** — append to memory/progress files via the Edit tool (Read the tail first), not a shell redirect.
