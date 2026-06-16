---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter 1 — task 1.1 of `catalog-lifecycle-approval` DONE).** Stood up the `catalog_producer_states` projection — the codebase's FIRST cross-module read model, the floor under 1.2's consumer & 3.2's gate. Three artifacts: enum `ProducerProjectionStatus` (active/retired), model `ProducerState` (persistence-only, no factory), the change's ONE migration (`id`, unique `producer_id`, `status`+PG CHECK from `cases()`, `last_event_id` watermark, `timestampsTz()`) + tests. No behaviour yet (consumer/gate land next).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 481/481 on SQLite** (was 475; +6 tests), phpstan max 0, pint clean. **PG17: Catalog+arch 77/77 green** (CHECK + unique proven; migration applies clean in full chain). `openspec validate catalog-lifecycle-approval --strict` green.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 1/17 tasks.** Module 0 lifecycle: 4-state FSM across 7 spine entities + Creator→Reviewer→Approver governance + Producer activation gate + first cross-module consumer + cascades + 14 `*Activated`/`*Retired` events.
- **Next: task 1.2** — `App\Modules\Catalog\Consumers\ProducerLifecycleProjector implements DomainEventConsumer`: `handle()` upserts `catalog_producer_states` on `ProducerActivated`→active / `ProducerRetired`→retired, advancing only when `$event->id` beats `last_event_id` (watermark, latest-wins). Register both event names in `CatalogServiceProvider::boot()` via the injected `ConsumerRegistry` (default inline). DB-work-only; imports ONLY `App\Platform\Events\DomainEvent` + the Catalog model (NO `App\Modules\Parties\*`). **Tests use `DatabaseMigrations`, NOT `RefreshDatabase`.**

## Blockers & Decisions Needed
- **None blocking.** Three design judgment calls still standing for accept/veto during ralph: (1) KYC conjunct enforced upstream — gate is producer-`active` only (D6); (2) producer-state projection in design D3, not a standalone ADR (now realized — promotable on request); (3) separation-of-duties is audit-derived, no governance columns (D5).
- Deferred follow-ons (named): `catalog-operator-console` (Filament approval-queue UI), `parties-compliance` (KYC model), Phase-3 referencers (cross-module retirement-blocking refs).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin; pao stdout fatal on PG).
- **Pint `fully_qualified_strict_types` auto-imports docblock `{@see \FQCN}`** → never `{@see}` a not-yet-created class (a future task's Action/Consumer/Event); use plain backticked text until it exists. Same-namespace `{@see ClassName}` is safe.
- **PG-only CHECK test** (template `tests/Feature/Platform/ActorRoleConstraintTest.php`): raw `DB::table()->insert()` past the cast, savepoint-wrapped (testing-rule #5); assert BOTH halves (PG names the constraint / SQLite accepts). `captureConstraintViolation()` is GLOBAL — don't redeclare.
- **Consumer/delivery tests use `DatabaseMigrations`, NOT `RefreshDatabase`** — the recorder's `DB::afterCommit` inline hook fires only at `transactionLevel 0`.
- **PG17 gate:** `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; bounded `docker exec pg pg_isready -q` loop (in-container sleep, NO host sleep); env prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco …`; `docker rm -f pg`. Traps in `knowledge/testing/rules.md`.
- **GUIDE §2.7 close** = verify both engines → merge `--no-ff` → semantic-verify → `openspec archive --yes` + commit → push (human-gated). APPROVED = human-only; never `git push` without explicit human OK.
