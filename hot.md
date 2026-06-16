---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter — task 6.1 DONE, docs-only, green).** Extended `CONTEXT.md` (the glossary of record) with the resolved lifecycle/approval vocabulary (8 new terms: the four-state FSM, review checkpoint, reopen, approval governance, the Producer gate, the producer-state projection, the activation + retirement cascades) + a new `### Catalog spine lifecycle events — payload contract` subsection (2 consumed + 14 emitted event payloads + the two deferred seams). No code touched. **16/17 tasks done** — only the final task 6.2 remains.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Green (iter 16, task 6.1): full suite 611/611 SQLite (unchanged — zero code touched); phpstan max 0; pint clean; `openspec validate --strict` ✓.** Docs-only: only `CONTEXT.md` changed (+64/−2); no PG run, no migration, no composer drift, no protected files.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 16/17 done.** Done: 1.x consumer/projection, 2.x FSM+governance, 3.x Master events/activate/retire/gate, 4.1–4.6 the six other entities, 5.1 activation cascade, 5.2 retirement cascade + ref-integrity, **6.1 docs**. Remaining: **6.2 (FINAL)**.
- **6.2 to implement (DB-touching → PG17 run REQUIRED):** one e2e feature test `tests/Feature/Modules/Catalog/CatalogLifecycleChainTest.php` (`DatabaseMigrations`) driving the REAL cross-module path — create a Parties Producer + `ActivateProducer` (Module K) → assert Catalog's consumer projects it `active` → create+submit+approve (distinct operators) a Product Master → activate a full Variant→PR→SellableSKU chain → retire the Master via `RetireProductMasterCascade` → `RetireProducer` (K) and assert the consumer projects `retired` + NEW Master activation now blocked while existing actives untouched. Assert `SpineCreationChainTest` still emits only `*Created` (unamended) + `DomainEvent::where('name','like','%Reviewed%')->count() === 0`. Test-only cross-module use of Parties factories/Actions is ALLOWED (proves the fan-out). Then the cross-engine close: whole Catalog suite + arch tests on PG17. Acceptance: AC-K-XM-2 e2e; block-new/preserve vs a real `ProducerRetired`; `ModuleBoundariesTest`/`ModulePersistenceConventionsTest` green (no amendment); record the PG run in progress.md. After 6.2 → `<promise>CHANGE_COMPLETE</promise>` (do NOT archive/merge — human does that).

## Blockers & Decisions Needed
- **None.** Deferred follow-ons (unchanged): `catalog-operator-console` (Filament queue UI), `parties-compliance` (KYC tightens `ActivateProducer` — D6), Phase-3 referencers (the cross-module retirement-blocking leg — D8).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in the arch plugin).
- **PG17 gate** (lifecycle/cross-module tests use `DatabaseMigrations`): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; wait `docker exec pg bash -c 'until pg_isready -U newco -q; do sleep 0.5; done'`; run `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest tests/Feature/Modules/Catalog tests/Unit/Modules/Catalog tests/Architecture`; `docker rm -f pg`.
- **Test-only cross-module use is allowed** (6.2 drives Parties `ActivateProducer`/`RetireProducer` + factories to prove the real fan-out); production code stays event-payload-only (invariant 10, `ModuleBoundariesTest`).
- **Heredoc `cat <<EOF >> file` mentioning "spec" trips the git-guardrails Bash hook** — append to memory/progress files via the Edit tool (Read the tail first), not a shell redirect.
