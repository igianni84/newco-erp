---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter 2 — task 1.2 of `catalog-lifecycle-approval` DONE).** Wired the codebase's FIRST registered cross-module domain-event consumer: `ProducerLifecycleProjector` consumes Module K's `ProducerActivated`/`ProducerRetired` into the 1.1 `catalog_producer_states` projection (active/retired), idempotent + order-tolerant via the per-producer `last_event_id` watermark (strict-advance upsert). Registered in `CatalogServiceProvider::boot(ConsumerRegistry)` (method injection, inline mode). Keys off BARE event-name strings (no `App\Modules\Parties\*` import — invariant 10). The read-model writer under 3.2's gate; no gate/FSM yet.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 487/487 on SQLite** (was 481; +6), phpstan max 0, pint clean. **PG17: Catalog+unit+arch 83/83 green** (was 77; +6 — watermark + `lockForUpdate` + projection writes proven on a real PG lock; `ModuleBoundariesTest`/`ModulePersistenceConventionsTest` green). `openspec validate catalog-lifecycle-approval --strict` green.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 2/17 tasks.** Module 0 lifecycle: 4-state FSM across 7 spine entities + Creator→Reviewer→Approver governance + Producer activation gate + first cross-module consumer (DONE) + cascades + 14 `*Activated`/`*Retired` events.
- **Next: task 2.1** — `App\Modules\Catalog\Exceptions\IllegalLifecycleTransition extends RuntimeException` with named factories (`::cannotSubmit/cannotActivate/cannotRetire/cannotReopen(LifecycleState $from, string $entity)`), each resolving localized copy from a NEW `lang/en/catalog.php` `lifecycle` group (dotted keys, `:state`/`:entity` placeholders — neither PII). **No DB → no PG run needed**, but quality commands still green. Test: `tests/Unit/Modules/Catalog/Exceptions/IllegalLifecycleTransitionTest.php` (no `RefreshDatabase`) — each factory returns the class + a non-empty resolved message containing the state+entity tokens.

## Blockers & Decisions Needed
- **None blocking.** Three design judgment calls still standing for accept/veto during ralph: (1) KYC conjunct enforced upstream — gate is producer-`active` only (D6); (2) producer-state projection in design D3, not a standalone ADR (promotable on request); (3) separation-of-duties is audit-derived, no governance columns (D5).
- Deferred follow-ons (named): `catalog-operator-console` (Filament approval-queue UI), `parties-compliance` (KYC model + tightens `ActivateProducer`), Phase-3 referencers (cross-module retirement-blocking refs).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin; pao stdout fatal on PG).
- **First-consumer seam:** `{Module}ServiceProvider::boot(ConsumerRegistry $registry)` (method injection; registry is an AppServiceProvider singleton, shared with the recorder). Cross-module consumers key off BARE event-name strings anchored as the consumer's own `public const` (never the emitter's event class — invariant 10). A registration test asserts `consumersFor(NAME)->toContain(Consumer::class)`.
- **Watermark-upsert consumer:** `->where(fk)->lockForUpdate()->first()`; null → create; else apply only when `$event->id` STRICTLY advances `last_event_id` (`<=` no-op). Handler opens NO transaction (executor wraps it — DB-work-only). Test the guard via direct `app(Consumer)->handle($staleEvent)` (a re-`deliver()` is a no-op on a `done` row).
- **phpstan-max jsonb payload read:** never `(int) $event->payload['k']` (cast.int on mixed) — narrow `$v = …['k'] ?? null; if (! is_int($v)) throw`. Laravel `array` cast round-trips a JSON int back as PHP int (so `is_int` holds on the happy path).
- **Pint `fully_qualified_strict_types` auto-imports docblock `{@see \FQCN}`** → for not-yet-created or non-imported classes use plain backticked text (`` `Foo::boot()` ``); same-namespace `{@see ClassName}` is safe.
- **Consumer/delivery tests use `DatabaseMigrations`, NOT `RefreshDatabase`** — the recorder's `DB::afterCommit` inline hook fires only at `transactionLevel 0`.
- **PG17 gate:** `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; bounded `docker exec pg pg_isready -q` loop (in-container sleep, NO host sleep); env prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco …`; `docker rm -f pg`. Traps in `knowledge/testing/rules.md`.
- **GUIDE §2.7 close** = verify both engines → merge `--no-ff` → semantic-verify → `openspec archive --yes` + commit → push (human-gated). APPROVED = human-only; never `git push` without explicit human OK.
