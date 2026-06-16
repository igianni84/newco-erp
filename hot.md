---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter — task 5.2 DONE, green).** Implemented the retirement half of the Catalog FSM: `RetireProductMasterCascade` (operator-driven, ONE txn, parent-before-child, all-or-nothing) + the within-catalog reference-integrity guard (Option B — terminal sellable edge) on `RetireProductReference`/`RetireCaseConfiguration` only. The 14:54 HUMAN_NEEDED is fully discharged. 15/17 tasks done.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Green (iter 15, task 5.2): full suite 611/611 SQLite (was 602; +9); PG17 Catalog+Unit+Architecture 207/207 (was 198; +9); phpstan max 0; pint clean; `openspec validate --strict` ✓.** No new migration, no composer drift, no protected files.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 15/17 done.** Done: 1.x consumer/projection, 2.x FSM+governance, 3.x Master events/activate/retire/gate, 4.1–4.6 the six other entities, 5.1 activation cascade, **5.2 retirement cascade + ref-integrity**. Remaining: **6.1 (docs)**, 6.2 (e2e + cross-engine close).
- **6.1 to implement (DOCS ONLY — no code, no PG run):** extend `CONTEXT.md` with the resolved lifecycle/approval terms (review checkpoint, activate/retire/reopen, the SoD floor + role-count config, the producer-state projection / first cross-module read model) + a Catalog contract note documenting the **2 consumed** events (`ProducerActivated`/`ProducerRetired` → projection → gate) and the **14 emitted** lifecycle event payloads (PII-free), plus the two deferred seams (KYC via `parties-compliance`; cross-module retirement referencers via Phase-3). Acceptance: verbatim spec anchors; no canonical-term drift (`Product*` names, "Wine Master" only as display alias); quality green + `openspec validate --strict`.

## Blockers & Decisions Needed
- **None.** Deferred follow-ons (unchanged): `catalog-operator-console` (Filament queue UI), `parties-compliance` (KYC tightens `ActivateProducer` — D6), Phase-3 referencers (the cross-module retirement-blocking leg — D8).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin).
- **`transition()`'s `$gate` is GENERAL** (activation gate OR retirement ref-integrity guard); the **operator cascade is guard-free** (calls `transition()` directly, not the per-entity `Retire*` Actions) so it retires a PR with its still-`active` SKUs in order. Hierarchy parents (Master/Variant) stay guard-free + PRESERVE children (§4.5).
- **Atomicity test idiom:** bind a throwing `DomainEventRecorder` decorator (anon class `extends`, `parent::record()` except the target NAME) to force a mid-txn failure; build its ctor args with `app(class-string<T>)` (NOT `$app->make()` → `mixed` → phpstan). Recorder is not a singleton, so the bind injects into a fresh `LifecycleTransition`.
- **PG17 gate** (lifecycle/cascade tests use `DatabaseMigrations`): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; wait `docker exec pg bash -c 'until pg_isready -U newco -q; do sleep 0.5; done'`; env `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest <folders>`; `docker rm -f pg`.
